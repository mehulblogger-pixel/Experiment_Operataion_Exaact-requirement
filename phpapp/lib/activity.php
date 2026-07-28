<?php
// ============================================================================
//  The activity spine — one table, everything that ever happened
//
//  Blueprint 001 P1, and the highest-value table in the whole CRM plan, because
//  Customer 360 is impossible without it and every other CRM feature reads from
//  it. Nothing here is new *information* — the application already knows a quote
//  was sent, a report was issued, a complaint was raised. What it has never had
//  is one place to ask "what has happened with this customer", in date order,
//  without opening nine screens.
//
//  Four decisions, all of them consequences of the hosting answer (MilesWeb
//  shared, MySQL). They are written down because on a bigger box I would have
//  chosen differently:
//
//   1. **partner_id is denormalised onto every row.** The polymorphic link says
//      what the activity is *about* — a quotation, a deputation, a complaint.
//      But Customer 360 asks "everything for this customer", and resolving that
//      through five joins at read time is what makes a shared-hosting page take
//      four seconds. So the customer is resolved ONCE at write time and stored.
//      The cost is a denormalised column; the benefit is one indexed query.
//   2. **Indexes are created explicitly**, because nothing else in this codebase
//      does and MySQL will not invent them. A timeline without an index on
//      (partner_id, occurred_at) is a table scan on every customer page.
//   3. **`auto` separates what the system wrote from what a person typed.** A
//      timeline where "Quote sent" and "Rang him, he is thinking about it" look
//      identical teaches people to distrust both.
//   4. **Writing an activity must never break the thing that caused it.** Every
//      call is wrapped. A timeline is a record of work, not a precondition for
//      it — if the log fails, the quote still goes out.
//
//  What this file does NOT do: decide anything. It records. The gates live in
//  the modules that own the rule.
// ============================================================================

const ACT_KINDS = [
    'NOTE'      => 'Note',
    'CALL'      => 'Telephone call',
    'MEETING'   => 'Meeting',
    'VISIT'     => 'Site visit',
    'EMAIL'     => 'E-mail',
    'WHATSAPP'  => 'WhatsApp',
    'TASK'      => 'Task',
    'SYSTEM'    => 'Recorded by the system',
];

// What an activity can be about. Kept as a short code plus the route that opens
// it, so the timeline can link back without every caller passing a URL.
const ACT_ENTITIES = [
    'PARTNER'   => ['Customer',        '/client?id='],
    'LEAD'      => ['Lead',            '/lead?id='],
    'INQUIRY'   => ['Inquiry',         '/inquiry-edit?id='],
    'QUOTE'     => ['Quotation',       '/quote?id='],
    'CALL'      => ['Inspection call', '/call?id='],
    'JOB'       => ['Deputation',      '/job?id='],
    'REPORT'    => ['Report',          '/document?id='],
    'INVOICE'   => ['Invoice',         '/job?id='],
    'COMPLAINT' => ['Complaint',       '/complaint?id='],
    'NCR'       => ['Nonconformity',   '/ncr-item?id='],
    'CAPA'      => ['Corrective action','/capa-item?id='],
];

const ACT_DIRECTIONS = ['IN' => 'Incoming', 'OUT' => 'Outgoing', '' => ''];

function act_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = pk_clause();
    $pdo->exec("CREATE TABLE IF NOT EXISTS activities (
        id $pk,
        kind VARCHAR(20) DEFAULT 'NOTE',
        entity_kind VARCHAR(20) DEFAULT '', entity_id INT NULL,
        partner_id INT NULL,
        subject VARCHAR(255) DEFAULT '', body TEXT,
        direction VARCHAR(4) DEFAULT '',
        occurred_at VARCHAR(30) DEFAULT '',
        duration_mins INT DEFAULT 0,
        outcome VARCHAR(60) DEFAULT '',
        with_whom VARCHAR(200) DEFAULT '',
        owner VARCHAR(150) DEFAULT '',
        office_id INT NULL, sbu VARCHAR(20) DEFAULT '',
        auto INT DEFAULT 0,
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // Nothing else in this codebase creates an index, and MySQL will not invent
    // one. Without these, every Customer 360 page is a table scan — which is
    // survivable at 160 rows and is not at 160,000.
    act_index('activities', 'idx_act_partner', '(partner_id, occurred_at)');
    act_index('activities', 'idx_act_entity',  '(entity_kind, entity_id)');
    act_index('activities', 'idx_act_when',    '(occurred_at)');
}

// CREATE INDEX IF NOT EXISTS is SQLite-only; MySQL throws on a duplicate, so
// the error is swallowed and only that one.
function act_index($table, $name, $cols) {
    try { db()->exec("CREATE INDEX $name ON $table $cols"); }
    catch (Throwable $e) {
        $m = strtolower($e->getMessage());
        if (strpos($m, 'exist') === false && strpos($m, 'duplicate') === false) throw $e;
    }
}

function act_missing_table(Throwable $e) {
    $m = $e->getMessage();
    return stripos($m, 'no such table') !== false || stripos($m, "doesn't exist") !== false;
}
function act_try($fn, $fallback = []) {
    try { return $fn(); } catch (Throwable $e) { if (!act_missing_table($e)) throw $e; return $fallback; }
}

// ---- Resolving the customer ------------------------------------------------
// Done ONCE, at write time. Given what an activity is about, work out which
// customer it belongs to so Customer 360 never has to join its way there.
function act_partner_for($entityKind, $entityId) {
    $id = (int)$entityId;
    if (!$id) return null;
    $q = function ($sql) use ($id) {
        try { $v = ops_val($sql, [$id]); return $v ? (int)$v : null; }
        catch (Throwable $e) { return null; }
    };
    switch ($entityKind) {
        case 'PARTNER':   return $id;
        case 'LEAD':      return $q("SELECT partner_id FROM leads WHERE id=?");
        case 'INQUIRY':   return $q("SELECT client_id FROM crm_inquiries WHERE id=?");
        case 'QUOTE':     return $q("SELECT client_id FROM quotations WHERE id=?");
        case 'CALL':      return $q("SELECT client_id FROM calls WHERE id=?");
        case 'JOB':
        case 'INVOICE':   return $q("SELECT c.client_id FROM jobs j LEFT JOIN calls c ON c.id=j.call_id WHERE j.id=?");
        case 'REPORT':    return $q("SELECT client_id FROM report_docs WHERE id=?");
        case 'COMPLAINT': return $q("SELECT partner_id FROM complaints WHERE id=?");
        case 'NCR':       return $q("SELECT partner_id FROM nonconformities WHERE id=?");
        case 'CAPA':      return null;
    }
    return null;
}

// ---- Writing ---------------------------------------------------------------
// The one function everything calls. Wrapped, because a timeline is a record of
// work and not a precondition for it: if this fails, the quote still goes out.
//
//   act_log('QUOTE', 42, 'EMAIL', 'Quotation Q-00042 sent to the customer',
//           ['auto' => 1, 'direction' => 'OUT']);
function act_log($entityKind, $entityId, $kind, $subject, array $opt = []) {
    try {
        act_migrate();
        if (!isset(ACT_ENTITIES[$entityKind])) $entityKind = '';
        if (!isset(ACT_KINDS[$kind])) $kind = 'NOTE';
        $partner = array_key_exists('partner_id', $opt)
            ? ($opt['partner_id'] ? (int)$opt['partner_id'] : null)
            : act_partner_for($entityKind, $entityId);
        $u = function_exists('current_user') ? current_user() : null;
        db()->prepare("INSERT INTO activities
            (kind,entity_kind,entity_id,partner_id,subject,body,direction,occurred_at,
             duration_mins,outcome,with_whom,owner,office_id,sbu,auto,created_by,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$kind, $entityKind, (int)$entityId ?: null, $partner,
                substr(trim((string)$subject), 0, 255),
                (string)($opt['body'] ?? ''),
                isset(ACT_DIRECTIONS[$opt['direction'] ?? '']) ? (string)($opt['direction'] ?? '') : '',
                (string)($opt['occurred_at'] ?? date('c')),
                (int)($opt['duration_mins'] ?? 0),
                substr(trim((string)($opt['outcome'] ?? '')), 0, 60),
                substr(trim((string)($opt['with_whom'] ?? '')), 0, 200),
                substr(trim((string)($opt['owner'] ?? ($u ? user_name($u) : ''))), 0, 150),
                ($opt['office_id'] ?? '') !== '' ? (int)$opt['office_id'] : (($u['home_office_id'] ?? null) ?: null),
                (string)($opt['sbu'] ?? ''),
                !empty($opt['auto']) ? 1 : 0,
                $u ? user_name($u) : (string)($opt['created_by'] ?? 'system'),
                date('c')]);
        return (int)db()->lastInsertId();
    } catch (Throwable $e) {
        return 0;   // never break the work that caused it
    }
}

// ---- Reading ---------------------------------------------------------------
// Everything for one customer — the Customer 360 query. One index, no joins to
// find the customer, because that was resolved on the way in.
function act_for_partner($partnerId, $limit = 200, $kinds = []) {
    act_migrate();
    $w = 'partner_id = ?'; $a = [(int)$partnerId];
    if ($kinds) {
        $w .= ' AND kind IN (' . implode(',', array_fill(0, count($kinds), '?')) . ')';
        foreach ($kinds as $k) $a[] = $k;
    }
    return act_try(fn() => ops_all(
        "SELECT * FROM activities WHERE $w ORDER BY occurred_at DESC, id DESC LIMIT " . max(1, (int)$limit), $a));
}

// Everything about one record — the panel that goes on a quote, a deputation,
// a complaint.
function act_for_entity($entityKind, $entityId, $limit = 100) {
    act_migrate();
    return act_try(fn() => ops_all(
        "SELECT * FROM activities WHERE entity_kind=? AND entity_id=?
         ORDER BY occurred_at DESC, id DESC LIMIT " . max(1, (int)$limit),
        [$entityKind, (int)$entityId]));
}

// When did anything last happen with this customer? The question behind
// "customers not contacted in 30 days", which is the most-asked CRM report.
function act_last_touch($partnerId) {
    act_migrate();
    return (string)act_try(fn() => ops_val(
        "SELECT MAX(occurred_at) FROM activities WHERE partner_id=?", [(int)$partnerId]), '');
}

function act_silent_customers($days = 30, $limit = 100) {
    act_migrate();
    $cut = date('c', strtotime("-$days days"));
    return act_try(fn() => ops_all(
        "SELECT bp.id, bp.display_name, bp.legal_name, MAX(a.occurred_at) last_touch
         FROM business_partners bp
         LEFT JOIN activities a ON a.partner_id = bp.id
         WHERE bp.is_client = 1 AND bp.status = 'ACTIVE'
         GROUP BY bp.id, bp.display_name, bp.legal_name
         HAVING MAX(a.occurred_at) IS NULL OR MAX(a.occurred_at) < ?
         ORDER BY last_touch ASC LIMIT " . max(1, (int)$limit), [$cut]));
}

function act_counts($partnerId) {
    act_migrate();
    $out = [];
    foreach (act_try(fn() => ops_all(
        "SELECT kind, COUNT(*) n FROM activities WHERE partner_id=? GROUP BY kind", [(int)$partnerId])) as $r)
        $out[$r['kind']] = (int)$r['n'];
    return $out;
}

// Where an activity points, for the timeline's "open it" link.
function act_link($a) {
    $k = (string)$a['entity_kind'];
    if ($k === '' || empty($a['entity_id']) || !isset(ACT_ENTITIES[$k])) return '';
    return ACT_ENTITIES[$k][1] . (int)$a['entity_id'];
}
function act_entity_label($a) {
    $k = (string)$a['entity_kind'];
    return isset(ACT_ENTITIES[$k]) ? ACT_ENTITIES[$k][0] : '';
}

// ---- The screen ------------------------------------------------------------
function act_can_view()  { return can('mod.clients.view') || can('mod.calls.view') || is_master(); }
function act_can_write() { return can('mod.clients.edit') || can('mod.calls.edit') || is_master(); }

// The register's columns, declared once. The 'sort' value is a SQL expression
// the register owns — it is the ONLY thing that can reach ORDER BY, which is
// what makes ?sort= in the address bar safe. 'optional' means hidden until
// somebody asks for it, so the default view stays readable.
function act_dt_columns() {
    return [
        'when' => ['label' => 'When', 'sort' => 'a.occurred_at', 'render' => fn($r) =>
            '<span style="white-space:nowrap">' . e(fdate(substr((string)$r['occurred_at'], 0, 10))) . '</span>'
            . '<br><span class="muted" style="font-size:12px">' . e(substr((string)$r['occurred_at'], 11, 5)) . '</span>'],
        'what' => ['label' => 'What happened', 'sort' => 'a.subject', 'render' => function ($r) {
            $h = '<span class="pill ' . ($r['auto'] ? 'p-mut' : 'p-ok') . '" style="font-size:11px">'
               . e(ACT_KINDS[$r['kind']] ?? $r['kind']) . '</span>';
            if ($r['direction']) $h .= ' <span class="muted" style="font-size:11px">'
                                     . e(ACT_DIRECTIONS[$r['direction']] ?? '') . '</span>';
            $h .= '<br>' . e($r['subject']);
            if (trim((string)$r['body']) !== '')
                $h .= '<br><span class="muted" style="font-size:12px;white-space:pre-wrap">'
                    . e(mb_substr((string)$r['body'], 0, 140)) . '</span>';
            return $h;
        }],
        'customer' => ['label' => 'Customer', 'sort' => 'COALESCE(bp.display_name, bp.legal_name)',
            'render' => fn($r) => $r['partner_id']
                ? '<a href="/activities?partner=' . (int)$r['partner_id'] . '">'
                  . e($r['display_name'] ?: $r['legal_name']) . '</a>'
                : '<span class="muted">—</span>'],
        'about' => ['label' => 'About', 'sort' => 'a.entity_kind',
            'render' => fn($r) => e(act_entity_label($r) ?: '—')],
        'who' => ['label' => 'Who', 'sort' => 'a.owner', 'render' => function ($r) {
            $h = e($r['owner'] ?: '—');
            if ($r['with_whom']) $h .= '<br><span class="muted" style="font-size:12px">with ' . e($r['with_whom']) . '</span>';
            return $h;
        }],
        'outcome' => ['label' => 'Outcome', 'sort' => 'a.outcome', 'optional' => true,
            'render' => fn($r) => e($r['outcome'] ?: '—')],
        'mins' => ['label' => 'Minutes', 'sort' => 'a.duration_mins', 'num' => true, 'optional' => true,
            'render' => fn($r) => (int)$r['duration_mins'] ?: '<span class="muted">—</span>'],
        'open' => ['label' => '', 'render' => fn($r) =>
            ($l = act_link($r)) ? '<a class="btn small" href="' . e($l) . '">Open →</a>' : ''],
    ];
}

function ops_activity($route, $method) {
    ops_require(act_can_view(), 'You cannot open the activity timeline.');
    act_migrate();

    if ($route === 'activity-add' && $method === 'POST') {
        ops_require(act_can_write(), 'You cannot record an activity.');
        $subject = trim((string)($_POST['subject'] ?? ''));
        if ($subject === '') { flash('Say what happened, in a line.', 'error'); redirect_back('/activities'); }
        // A person typing this is never an automatic entry, whatever is posted.
        act_log((string)($_POST['entity_kind'] ?? ''), (int)($_POST['entity_id'] ?? 0),
                (string)($_POST['kind'] ?? 'NOTE'), $subject, [
                    'body' => (string)($_POST['body'] ?? ''),
                    'direction' => (string)($_POST['direction'] ?? ''),
                    'occurred_at' => (string)($_POST['occurred_at'] ?? '') ?: date('c'),
                    'duration_mins' => (int)($_POST['duration_mins'] ?? 0),
                    'outcome' => (string)($_POST['outcome'] ?? ''),
                    'with_whom' => (string)($_POST['with_whom'] ?? ''),
                    'partner_id' => (int)($_POST['partner_id'] ?? 0) ?: null,
                    'auto' => 0,
                ]);
        flash('Recorded.');
        redirect_back('/activities');
    }

    // The register. It used to be "newest 300 and no more", which on a busy
    // installation quietly hid everything older without saying so. It pages now,
    // so the whole timeline is reachable and the count at the top is the truth.
    $kind = (string)($_GET['kind'] ?? '');
    $pid  = (int)($_GET['partner'] ?? 0);
    $cols = act_dt_columns();
    $dt   = dt_state('activities', $cols, ['default_sort' => 'when', 'default_dir' => 'desc', 'per' => 50]);

    $w = '1=1'; $a = [];
    if ($kind !== '' && isset(ACT_KINDS[$kind])) { $w .= ' AND a.kind=?'; $a[] = $kind; }
    if ($pid) { $w .= ' AND a.partner_id=?'; $a[] = $pid; }
    if (($_GET['manual'] ?? '') === '1') $w .= ' AND a.auto=0';
    if ($dt['q'] !== '') {
        $w .= ' AND (a.subject LIKE ? OR a.body LIKE ? OR a.with_whom LIKE ? OR a.outcome LIKE ?)';
        $like = '%' . $dt['q'] . '%';
        array_push($a, $like, $like, $like, $like);
    }
    $from = "FROM activities a LEFT JOIN business_partners bp ON bp.id = a.partner_id WHERE $w";

    if (wants_csv()) {
        // An export that only carried the page you were looking at would be a
        // trap. It carries every row the filters match — the same filters, not
        // the same page.
        $all = act_try(fn() => ops_all("SELECT a.*, bp.display_name, bp.legal_name $from
                                        ORDER BY a.occurred_at DESC, a.id DESC LIMIT 20000", $a));
        $csv = [['When','Kind','Subject','Customer','About','Direction','With','Outcome','Minutes','Owner','Recorded by system']];
        foreach ($all as $r)
            $csv[] = [substr((string)$r['occurred_at'],0,16), ACT_KINDS[$r['kind']] ?? $r['kind'], $r['subject'],
                      $r['display_name'] ?: $r['legal_name'], act_entity_label($r),
                      ACT_DIRECTIONS[$r['direction']] ?? '', $r['with_whom'], $r['outcome'],
                      (int)$r['duration_mins'], $r['owner'], $r['auto'] ? 'Yes' : 'No'];
        csv_download('activities-' . date('Y-m-d') . '.csv', $csv);
    }

    $total = (int)act_try(fn() => ops_val("SELECT COUNT(*) $from", $a), 0);
    $rows  = act_try(fn() => ops_all("SELECT a.*, bp.display_name, bp.legal_name $from"
                                     . dt_sql_tail($dt, $cols, 'a.occurred_at DESC, a.id DESC'), $a));

    view('ops/activities', ['rows' => $rows, 'total' => $total, 'dt' => $dt, 'cols' => $cols,
                            'kind' => $kind, 'pid' => $pid,
                            'silent' => act_silent_customers(30, 20),
                            'canWrite' => act_can_write()]);
    return true;
}
