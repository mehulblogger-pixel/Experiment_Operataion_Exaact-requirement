<?php
// ============================================================================
//  Opportunities — the stage between "they are interested" and "here is a price"
//
//  Blueprint 001 P6. The owner confirmed these should be DISTINCT from
//  quotations, and that distinction is the whole point of the file, so it is
//  worth being precise about why rather than assuming it is obvious.
//
//  A quotation is a DOCUMENT. It has a number, a revision, a total, terms, and
//  it goes to the customer. An opportunity is a PIECE OF BUSINESS you are
//  trying to win. The two are not the same thing and the differences are the
//  reasons a pipeline built out of quotations reports nonsense:
//
//    * One opportunity often carries SEVERAL quotations — three options, or a
//      revision after a negotiation. Counting quotations counts the same deal
//      three times, and the forecast is three times too big.
//    * An opportunity exists BEFORE any quotation does. "Reliance are retendering
//      their vessel inspection in March, worth about 40 lakh" is a real thing to
//      forecast against, and there is no document to hang it on.
//    * An opportunity can be lost without a quotation ever being raised, and
//      that is the most important loss to record — it is the one where the
//      reason is "we never got to bid".
//    * A quotation has a value fixed by its lines. An opportunity has an
//      ESTIMATE, which moves as you learn more, and a PROBABILITY from its
//      stage. Weighted forecast is estimate × probability, and a quotation has
//      no honest place to keep either.
//
//  WHERE IT SITS. The flow was lead → enquiry → quotation. It is now
//  lead → opportunity → quotation, with the enquiry raised alongside so the
//  existing funnel keeps working. Winning an opportunity is what marks the deal
//  won; a quotation being accepted is what usually causes that.
//
//  REUSED, NOT REBUILT. Pipelines and stages already exist for leads, with
//  configurable stages carrying a probability, an SLA and a WON/LOST/OPEN kind.
//  Opportunities use the same two tables with entity_kind='OPPORTUNITY'. A
//  second stage engine would have been a second thing to keep in step.
// ============================================================================

const OPP_STATUS = ['OPEN' => 'Open', 'WON' => 'Won', 'LOST' => 'Lost'];

// A default pipeline so a new installation is usable without reading anything.
// Deliberately different from the lead stages: a lead is about whether they are
// real, an opportunity is about whether you will win it.
const OPP_DEFAULT_STAGES = [
    ['Qualified',        'OPEN',  10, 14],
    ['Needs understood', 'OPEN',  25, 14],
    ['Quotation sent',   'OPEN',  50, 10],
    ['Negotiation',      'OPEN',  75, 10],
    ['Won',              'WON',  100,  0],
    ['Lost',             'LOST',   0,  0],
];

// Why a deal was lost. Shares the quotation list so the two agree — a company
// that adds "price" as a reason should not have to add it twice.
function opp_lost_reasons() {
    return function_exists('lk_options_or') && defined('QUOTE_LOST_REASONS')
        ? lk_options_or('quote_lost_reason', QUOTE_LOST_REASONS)
        : ['PRICE_HIGH' => 'Price', 'NO_RESPONSE' => 'No response', 'OTHER' => 'Other'];
}

function opp_can_view() { return can('mod.leads.view') || can('mod.quotes.view') || can('mod.inquiries.view') || is_master_of(['leads','quotes','inquiries']); }
function opp_can_edit() { return can('mod.leads.edit') || can('mod.quotes.edit') || can('mod.inquiries.edit') || is_master_of(['leads','quotes','inquiries']); }

function opp_try($fn, $fb = []) {
    try { return $fn(); }
    catch (Throwable $e) {
        $m = $e->getMessage();
        if (stripos($m, 'no such table') !== false || stripos($m, "doesn't exist") !== false
            || stripos($m, 'no such column') !== false || stripos($m, 'Unknown column') !== false) return $fb;
        throw $e;
    }
}

function opp_migrate() {
    static $done = false; if ($done) return; $done = true;
    if (function_exists('leads_migrate')) leads_migrate();   // pipelines/stages live there
    $pdo = db(); $pk = pk_clause();
    $pdo->exec("CREATE TABLE IF NOT EXISTS opportunities (
        id $pk, ref VARCHAR(40) DEFAULT '',
        name VARCHAR(255) DEFAULT '',
        partner_id INT NULL, partner_name VARCHAR(200) DEFAULT '',
        lead_id INT NULL, inquiry_id INT NULL,
        pipeline_id INT NULL, stage_id INT NULL,
        value DECIMAL(16,2) DEFAULT 0, currency VARCHAR(8) DEFAULT 'INR',
        probability INT DEFAULT 0,
        expected_close VARCHAR(20) DEFAULT '',
        source VARCHAR(20) DEFAULT '', requirement TEXT,
        competitor VARCHAR(200) DEFAULT '',
        contact_name VARCHAR(150) DEFAULT '', contact_email VARCHAR(200) DEFAULT '',
        contact_phone VARCHAR(60) DEFAULT '',
        owner_user_id INT NULL, owner_name VARCHAR(150) DEFAULT '',
        office_id INT NULL, sbu VARCHAR(20) DEFAULT '',
        status VARCHAR(20) DEFAULT 'OPEN',
        stage_since VARCHAR(30) DEFAULT '',
        next_action_on VARCHAR(20) DEFAULT '', next_action VARCHAR(255) DEFAULT '',
        lost_reason VARCHAR(40) DEFAULT '', lost_note VARCHAR(500) DEFAULT '',
        lost_to VARCHAR(200) DEFAULT '',
        won_at VARCHAR(30) DEFAULT '', won_by VARCHAR(150) DEFAULT '',
        won_quotation_id INT NULL,
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '',
        updated_at VARCHAR(30) DEFAULT '')");
    // Which quotations belong to which opportunity. A join table rather than a
    // column on the quotation, because a quotation raised before opportunities
    // existed has none and must not be forced to invent one.
    $pdo->exec("CREATE TABLE IF NOT EXISTS opportunity_quotes (
        id $pk, opportunity_id INT, quotation_id INT,
        linked_by VARCHAR(150) DEFAULT '', linked_at VARCHAR(30) DEFAULT '')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS opportunity_stage_history (
        id $pk, opportunity_id INT, from_stage_id INT NULL, to_stage_id INT NULL,
        from_name VARCHAR(120) DEFAULT '', to_name VARCHAR(120) DEFAULT '',
        days_in_previous INT DEFAULT 0,
        moved_by VARCHAR(150) DEFAULT '', moved_at VARCHAR(30) DEFAULT '')");
    if (function_exists('act_index')) {
        act_index('opportunities', 'idx_opp_stage', '(stage_id, status)');
        act_index('opportunities', 'idx_opp_partner', '(partner_id, status)');
        act_index('opportunity_quotes', 'idx_oq_opp', '(opportunity_id)');
        act_index('opportunity_quotes', 'idx_oq_quote', '(quotation_id)');
    }
    opp_seed_pipeline();
}

function opp_seed_pipeline() {
    try {
        if ((int)ops_val("SELECT COUNT(*) FROM pipelines WHERE entity_kind='OPPORTUNITY'") > 0) return;
        db()->prepare("INSERT INTO pipelines (name,entity_kind,is_default,active,created_by,created_at)
                       VALUES ('Opportunity pipeline','OPPORTUNITY',1,1,'system',?)")->execute([date('c')]);
        $pid = (int)db()->lastInsertId();
        $st = db()->prepare("INSERT INTO pipeline_stages (pipeline_id,seq,name,kind,probability,sla_days,active)
                             VALUES (?,?,?,?,?,?,1)");
        foreach (OPP_DEFAULT_STAGES as $i => [$n, $k, $p, $sla]) $st->execute([$pid, $i + 1, $n, $k, $p, $sla]);
    } catch (Throwable $e) { /* built on the next boot */ }
}

function opp_ref_next() {
    $y = date('Y');
    $last = opp_try(fn() => ops_val("SELECT ref FROM opportunities WHERE ref LIKE ? ORDER BY ref DESC LIMIT 1", ["OPP-$y-%"]), '');
    $n = $last ? ((int)substr((string)$last, strrpos((string)$last, '-') + 1)) + 1 : 1;
    return sprintf('OPP-%s-%04d', $y, $n);
}

// ---- Reading ----------------------------------------------------------------
const OPP_FROM = "FROM opportunities o
         LEFT JOIN pipeline_stages s ON s.id = o.stage_id
         LEFT JOIN pipelines p ON p.id = o.pipeline_id
         LEFT JOIN offices f ON f.id = o.office_id
         LEFT JOIN business_partners bp ON bp.id = o.partner_id";

const OPP_SELECT = "SELECT o.*, s.name stage_name, s.kind stage_kind, s.probability stage_prob,
                s.sla_days, s.seq stage_seq, p.name pipeline_name, f.name office_name,
                COALESCE(bp.display_name, bp.legal_name) client_name ";

function opp_where(array $f = []) {
    [$sw, $sa] = scope_office_clause('o.office_id');
    $w = [$sw]; $a = $sa;
    if (!empty($f['status']))   { $w[] = 'o.status = ?'; $a[] = $f['status']; }
    if (!empty($f['open']))     { $w[] = "o.status = 'OPEN'"; }
    if (!empty($f['pipeline'])) { $w[] = 'o.pipeline_id = ?'; $a[] = (int)$f['pipeline']; }
    if (!empty($f['partner']))  { $w[] = 'o.partner_id = ?'; $a[] = (int)$f['partner']; }
    if (!empty($f['q'])) {
        $w[] = '(o.ref LIKE ? OR o.name LIKE ? OR o.partner_name LIKE ? OR o.contact_name LIKE ?)';
        $like = '%' . $f['q'] . '%'; array_push($a, $like, $like, $like, $like);
    }
    return [implode(' AND ', $w), $a];
}

function opp_all(array $f = [], $tail = '') {
    opp_migrate();
    [$w, $a] = opp_where($f);
    return opp_try(fn() => ops_all(OPP_SELECT . OPP_FROM . " WHERE $w "
        . ($tail ?: "ORDER BY (o.status='OPEN') DESC, s.seq, o.value DESC, o.id DESC"), $a));
}

function opp_count(array $f = []) {
    opp_migrate();
    [$w, $a] = opp_where($f);
    return (int)opp_try(fn() => ops_val("SELECT COUNT(*) " . OPP_FROM . " WHERE $w", $a), 0);
}

function opp_row($id) {
    opp_migrate();
    return opp_try(fn() => ops_one(OPP_SELECT . OPP_FROM . " WHERE o.id=?", [(int)$id]), null) ?: null;
}

function opp_quotes($oppId) {
    opp_migrate();
    return opp_try(fn() => ops_all(
        "SELECT q.id, q.quote_no, q.rev, q.status, q.total_amount, q.is_current, q.created_at
         FROM opportunity_quotes oq JOIN quotations q ON q.id = oq.quotation_id
         WHERE oq.opportunity_id=? ORDER BY q.quote_no, q.rev", [(int)$oppId]));
}

function opp_history($oppId) {
    return opp_try(fn() => ops_all(
        "SELECT * FROM opportunity_stage_history WHERE opportunity_id=? ORDER BY id DESC", [(int)$oppId]));
}

function opp_days_in_stage($o, $today = null) {
    $since = trim((string)($o['stage_since'] ?? '')) ?: (string)($o['created_at'] ?? '');
    if ($since === '') return 0;
    $t = strtotime($since); if ($t === false) return 0;
    return max(0, (int)floor((strtotime($today ?: date('Y-m-d')) - $t) / 86400));
}

function opp_stalled($o, $today = null) {
    if (($o['status'] ?? '') !== 'OPEN') return false;
    $sla = (int)($o['sla_days'] ?? 0);
    return $sla > 0 && opp_days_in_stage($o, $today) > $sla;
}

// The number a sales manager is actually asked for. Value alone over-promises;
// the stage's own probability is the only honest weighting available, and it is
// configurable per stage rather than guessed here.
function opp_weighted($o) {
    $p = (int)($o['probability'] ?: $o['stage_prob'] ?? 0);
    return round((float)$o['value'] * max(0, min(100, $p)) / 100, 2);
}

function opp_counts() {
    opp_migrate();
    $open = opp_all(['open' => 1]);
    $value = $weighted = 0.0; $stalled = 0;
    foreach ($open as $o) {
        $value += (float)$o['value'];
        $weighted += opp_weighted($o);
        if (opp_stalled($o)) $stalled++;
    }
    [$sw, $sa] = scope_office_clause('o.office_id');
    $n = fn($extra) => (int)opp_try(fn() => ops_val("SELECT COUNT(*) FROM opportunities o WHERE $sw AND ($extra)", $sa), 0);
    return ['open' => count($open), 'value' => $value, 'weighted' => $weighted,
            'stalled' => $stalled, 'won' => $n("o.status='WON'"), 'lost' => $n("o.status='LOST'")];
}

// Won and lost over a window, which is the only way to state a win rate that
// means anything — a lifetime figure never moves and nobody looks at it twice.
function opp_win_rate($days = 365) {
    opp_migrate();
    $cut = date('Y-m-d', strtotime("-$days days"));
    [$sw, $sa] = scope_office_clause('o.office_id');
    $q = fn($st) => opp_try(fn() => ops_one(
        "SELECT COUNT(*) n, COALESCE(SUM(o.value),0) v FROM opportunities o
         WHERE $sw AND o.status=? AND COALESCE(NULLIF(o.won_at,''), o.updated_at, o.created_at) >= ?",
        array_merge($sa, [$st, $cut])), ['n' => 0, 'v' => 0]);
    $w = $q('WON'); $l = $q('LOST');
    $tot = (int)$w['n'] + (int)$l['n'];
    return ['won' => (int)$w['n'], 'lost' => (int)$l['n'],
            'won_value' => (float)$w['v'], 'lost_value' => (float)$l['v'],
            'rate' => $tot ? round((int)$w['n'] * 100 / $tot) : null, 'days' => $days];
}

// Why deals are lost, counted. The question behind every sales review, and it
// could not be answered at all before — a lost quotation had a reason, but a
// deal lost before anybody quoted had nowhere to record one.
function opp_loss_reasons($days = 365) {
    opp_migrate();
    $cut = date('Y-m-d', strtotime("-$days days"));
    [$sw, $sa] = scope_office_clause('o.office_id');
    $labels = opp_lost_reasons();
    $out = [];
    foreach (opp_try(fn() => ops_all(
        "SELECT o.lost_reason, COUNT(*) n, COALESCE(SUM(o.value),0) v FROM opportunities o
         WHERE $sw AND o.status='LOST' AND COALESCE(NULLIF(o.won_at,''), o.updated_at, o.created_at) >= ?
         GROUP BY o.lost_reason ORDER BY n DESC", array_merge($sa, [$cut]))) as $r)
        $out[] = ['reason' => $labels[$r['lost_reason']] ?? ($r['lost_reason'] ?: 'Not recorded'),
                  'n' => (int)$r['n'], 'value' => (float)$r['v']];
    return $out;
}

function opp_board($pipelineId = 0) {
    opp_migrate();
    $pipe = $pipelineId ?: (int)(pipeline_default('OPPORTUNITY')['id'] ?? 0);
    $stages = pipeline_stages($pipe);
    $rows = opp_all(['pipeline' => $pipe, 'open' => 1]);
    $by = [];
    foreach ($stages as $s) $by[(int)$s['id']] = ['stage' => $s, 'rows' => [], 'value' => 0.0, 'weighted' => 0.0];
    foreach ($rows as $r) {
        $sid = (int)$r['stage_id'];
        if (!isset($by[$sid])) continue;
        $by[$sid]['rows'][] = $r;
        $by[$sid]['value'] += (float)$r['value'];
        $by[$sid]['weighted'] += opp_weighted($r);
    }
    return ['pipeline' => $pipe, 'stages' => $stages, 'columns' => $by];
}

// ---- Writing ----------------------------------------------------------------
function opp_create(array $b) {
    opp_migrate();
    $name = trim((string)($b['name'] ?? ''));
    if ($name === '') return ['err' => 'Give the opportunity a name — what is the piece of business?'];
    $pid = (int)($b['partner_id'] ?? 0);
    $pname = trim((string)($b['partner_name'] ?? ''));
    if (!$pid && $pname === '') return ['err' => 'Say which customer, or who it is for.'];
    if ($pid && $pname === '') {
        $p = ops_one("SELECT display_name, legal_name FROM business_partners WHERE id=?", [$pid]);
        $pname = $p ? (string)($p['display_name'] ?: $p['legal_name']) : '';
    }
    $pipe = (int)($b['pipeline_id'] ?? 0) ?: (int)(pipeline_default('OPPORTUNITY')['id'] ?? 0);
    $stage = (int)($b['stage_id'] ?? 0) ?: (int)(($pipeline_stages = pipeline_stages($pipe))[0]['id'] ?? 0);
    $u = current_user();
    $ref = opp_ref_next();
    db()->prepare("INSERT INTO opportunities
        (ref,name,partner_id,partner_name,lead_id,inquiry_id,pipeline_id,stage_id,value,probability,
         expected_close,source,requirement,competitor,contact_name,contact_email,contact_phone,
         owner_user_id,owner_name,office_id,sbu,status,stage_since,next_action_on,next_action,
         created_by,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'OPEN', ?,?,?,?,?,?)")
      ->execute([$ref, $name, $pid ?: null, $pname,
                 (int)($b['lead_id'] ?? 0) ?: null, (int)($b['inquiry_id'] ?? 0) ?: null,
                 $pipe ?: null, $stage ?: null,
                 (float)($b['value'] ?? 0), (int)($b['probability'] ?? 0),
                 (string)($b['expected_close'] ?? ''), (string)($b['source'] ?? ''),
                 (string)($b['requirement'] ?? ''), (string)($b['competitor'] ?? ''),
                 (string)($b['contact_name'] ?? ''), (string)($b['contact_email'] ?? ''),
                 (string)($b['contact_phone'] ?? ''),
                 $u ? (int)$u['id'] : null,
                 trim((string)($b['owner_name'] ?? '')) ?: ($u ? user_name($u) : ''),
                 (int)($b['office_id'] ?? 0) ?: ($u['home_office_id'] ?? null),
                 (string)($b['sbu'] ?? ''),
                 date('c'), (string)($b['next_action_on'] ?? ''), (string)($b['next_action'] ?? ''),
                 $u ? user_name($u) : '', date('c'), date('c')]);
    $id = (int)db()->lastInsertId();
    if (function_exists('act_log'))
        act_log('OPPORTUNITY', $id, 'SYSTEM', 'Opportunity ' . $ref . ' opened — ' . $name,
                ['auto' => 1, 'partner_id' => $pid ?: null]);
    return ['id' => $id, 'ref' => $ref];
}

function opp_record_move($o, $to) {
    opp_try(fn() => db()->prepare(
        "INSERT INTO opportunity_stage_history
         (opportunity_id,from_stage_id,to_stage_id,from_name,to_name,days_in_previous,moved_by,moved_at)
         VALUES (?,?,?,?,?,?,?,?)")
      ->execute([(int)$o['id'], (int)$o['stage_id'] ?: null, (int)$to['id'],
                 (string)($o['stage_name'] ?? ''), (string)$to['name'],
                 opp_days_in_stage($o), user_name(current_user()), date('c')]), null);
}

// Moving to a WON or LOST stage is not the same as moving between open stages:
// it closes the deal, and a loss needs a reason. The stage's `kind` decides,
// which is why stages are configurable and the rule is not.
function opp_move($oppId, $stageId, array $b = []) {
    opp_migrate();
    $o = opp_row($oppId);
    if (!$o) return ['err' => 'That opportunity no longer exists.'];
    if ($o['status'] !== 'OPEN') return ['err' => 'That opportunity is already ' . strtolower(OPP_STATUS[$o['status']] ?? $o['status']) . '.'];
    $to = stage_row((int)$stageId);
    if (!$to) return ['err' => 'Choose a stage to move it to.'];

    $kind = (string)$to['kind'];
    if ($kind === 'LOST' && trim((string)($b['lost_reason'] ?? '')) === '')
        return ['err' => 'A deal marked lost needs a reason — it is the only thing that makes the loss useful later.'];

    $status = $kind === 'WON' ? 'WON' : ($kind === 'LOST' ? 'LOST' : 'OPEN');
    $u = current_user();
    opp_record_move($o, $to);
    db()->prepare("UPDATE opportunities SET stage_id=?, status=?, probability=?, stage_since=?,
                   lost_reason=?, lost_note=?, lost_to=?, won_at=?, won_by=?, updated_at=? WHERE id=?")
       ->execute([(int)$to['id'], $status, (int)$to['probability'], date('c'),
                  $kind === 'LOST' ? (string)($b['lost_reason'] ?? '') : (string)$o['lost_reason'],
                  $kind === 'LOST' ? (string)($b['lost_note'] ?? '') : (string)$o['lost_note'],
                  $kind === 'LOST' ? (string)($b['lost_to'] ?? '') : (string)$o['lost_to'],
                  $status !== 'OPEN' ? date('c') : (string)$o['won_at'],
                  $status !== 'OPEN' ? ($u ? user_name($u) : '') : (string)$o['won_by'],
                  date('c'), (int)$o['id']]);
    if (function_exists('act_log'))
        act_log('OPPORTUNITY', (int)$o['id'], 'SYSTEM',
                'Moved to ' . $to['name'] . ($status !== 'OPEN' ? ' — ' . strtolower(OPP_STATUS[$status]) : ''),
                ['auto' => 1, 'partner_id' => $o['partner_id'] ?: null,
                 'outcome' => $status === 'WON' ? 'won' : ($status === 'LOST' ? 'lost' : '')]);
    return ['ok' => true, 'status' => $status];
}

function opp_update($oppId, array $b) {
    opp_migrate();
    $o = opp_row($oppId);
    if (!$o) return 'That opportunity no longer exists.';
    db()->prepare("UPDATE opportunities SET name=?, value=?, expected_close=?, requirement=?, competitor=?,
                   contact_name=?, contact_email=?, contact_phone=?, owner_name=?, office_id=?, sbu=?,
                   next_action_on=?, next_action=?, updated_at=? WHERE id=?")
       ->execute([trim((string)($b['name'] ?? $o['name'])), (float)($b['value'] ?? $o['value']),
                  (string)($b['expected_close'] ?? ''), (string)($b['requirement'] ?? ''),
                  (string)($b['competitor'] ?? ''), (string)($b['contact_name'] ?? ''),
                  (string)($b['contact_email'] ?? ''), (string)($b['contact_phone'] ?? ''),
                  (string)($b['owner_name'] ?? ''), (int)($b['office_id'] ?? 0) ?: null,
                  (string)($b['sbu'] ?? ''), (string)($b['next_action_on'] ?? ''),
                  (string)($b['next_action'] ?? ''), date('c'), (int)$o['id']]);
    return '';
}

// Attaching a quotation is what makes "one deal, three quotations" countable.
function opp_link_quote($oppId, $quoteId) {
    opp_migrate();
    $o = opp_row($oppId);
    if (!$o) return 'That opportunity no longer exists.';
    $q = opp_try(fn() => ops_one("SELECT id, quote_no FROM quotations WHERE id=?", [(int)$quoteId]), null);
    if (!$q) return 'That quotation no longer exists.';
    if (opp_try(fn() => ops_val("SELECT id FROM opportunity_quotes WHERE opportunity_id=? AND quotation_id=?",
                                [(int)$oppId, (int)$quoteId]), null)) return 'It is already attached.';
    db()->prepare("INSERT INTO opportunity_quotes (opportunity_id,quotation_id,linked_by,linked_at) VALUES (?,?,?,?)")
       ->execute([(int)$oppId, (int)$quoteId, user_name(current_user()), date('c')]);
    if (function_exists('act_log'))
        act_log('OPPORTUNITY', (int)$oppId, 'SYSTEM', 'Quotation ' . $q['quote_no'] . ' attached',
                ['auto' => 1, 'partner_id' => $o['partner_id'] ?: null]);
    return '';
}

function opp_unlink_quote($oppId, $quoteId) {
    opp_migrate();
    db()->prepare("DELETE FROM opportunity_quotes WHERE opportunity_id=? AND quotation_id=?")
       ->execute([(int)$oppId, (int)$quoteId]);
    return '';
}

// A lead that is worth pursuing becomes an opportunity. This replaces going
// straight to an enquiry: the enquiry is still raised, because the existing
// funnel reads it, but the DEAL now has somewhere to live while it is being won.
function opp_from_lead($leadId, array $b = []) {
    opp_migrate();
    if (!function_exists('lead_row')) return ['err' => 'Leads are not available on this installation.'];
    $l = lead_row($leadId);
    if (!$l) return ['err' => 'That lead no longer exists.'];
    $existing = opp_try(fn() => ops_val("SELECT id FROM opportunities WHERE lead_id=?", [(int)$leadId]), null);
    if ($existing) return ['id' => (int)$existing, 'existing' => true];

    $r = opp_create([
        'name'           => trim((string)($b['name'] ?? '')) ?: ($l['company_name'] . ' — ' . ($l['requirement'] ? mb_substr((string)$l['requirement'], 0, 60) : 'opportunity')),
        'partner_id'     => (int)($b['partner_id'] ?? 0) ?: (int)($l['partner_id'] ?? 0),
        'partner_name'   => (string)$l['company_name'],
        'lead_id'        => (int)$l['id'],
        'value'          => (float)($b['value'] ?? $l['value']),
        'expected_close' => (string)($b['expected_close'] ?? $l['expected_close']),
        'source'         => (string)$l['source'],
        'requirement'    => (string)$l['requirement'],
        'contact_name'   => (string)$l['contact_name'],
        'contact_email'  => (string)$l['contact_email'],
        'contact_phone'  => (string)$l['contact_phone'],
        'owner_name'     => (string)$l['owner_name'],
        'office_id'      => (int)($l['office_id'] ?? 0),
        'sbu'            => (string)$l['sbu'],
    ]);
    return $r;
}

// ---- The register's columns -------------------------------------------------
function opp_dt_columns() {
    return [
        'ref' => ['label' => 'Ref', 'sort' => 'o.ref', 'render' => fn($o) =>
            '<a href="/opportunity?id=' . (int)$o['id'] . '"><b>' . e($o['ref']) . '</b></a>'],
        'name' => ['label' => 'Opportunity', 'sort' => 'o.name', 'render' => function ($o) {
            $h = '<a href="/opportunity?id=' . (int)$o['id'] . '">' . e($o['name']) . '</a>';
            $h .= '<br><span class="muted" style="font-size:12px">'
                . e($o['client_name'] ?: $o['partner_name']) . '</span>';
            return $h;
        }],
        'stage' => ['label' => 'Stage', 'sort' => 's.seq', 'render' => fn($o) => e($o['stage_name'] ?: '—')],
        'value' => ['label' => 'Estimate', 'sort' => 'o.value', 'num' => true,
            'render' => fn($o) => (float)$o['value'] ? e(fmoney($o['value'])) : '—'],
        'weighted' => ['label' => 'Weighted', 'num' => true, 'render' => fn($o) =>
            '<span title="Estimate × the stage probability">' . e(fmoney(opp_weighted($o))) . '</span>'],
        'prob' => ['label' => '%', 'sort' => 'o.probability', 'num' => true, 'optional' => true,
            'render' => fn($o) => (int)($o['probability'] ?: $o['stage_prob']) . '%'],
        'close' => ['label' => 'Expected close', 'sort' => 'o.expected_close',
            'render' => fn($o) => $o['expected_close'] ? e(fdate($o['expected_close'])) : '<span class="muted">—</span>'],
        'owner' => ['label' => 'Owner', 'sort' => 'o.owner_name', 'render' => fn($o) => e($o['owner_name'] ?: '—')],
        'branch' => ['label' => 'Branch', 'sort' => 'f.name', 'optional' => true,
            'render' => fn($o) => e($o['office_name'] ?: '—')],
        'competitor' => ['label' => 'Against', 'sort' => 'o.competitor', 'optional' => true,
            'render' => fn($o) => e($o['competitor'] ?: '—')],
        'instage' => ['label' => 'In stage', 'sort' => 'o.stage_since', 'num' => true,
            'render' => fn($o) => opp_days_in_stage($o) . ' d'
                . (opp_stalled($o) ? '<br><span class="pill p-bad">late</span>' : '')],
        'status' => ['label' => 'Status', 'sort' => 'o.status', 'render' => fn($o) =>
            '<span class="pill ' . ($o['status'] === 'WON' ? 'p-ok' : ($o['status'] === 'LOST' ? 'p-bad' : 'p-warn'))
            . '">' . e(OPP_STATUS[$o['status']] ?? $o['status']) . '</span>'],
    ];
}

// ---- Screens ----------------------------------------------------------------
function ops_opportunities($route, $method) {
    ops_require(opp_can_view(), 'You cannot open the opportunity register.');
    opp_migrate();
    $canEdit = opp_can_edit();

    if ($route === 'opportunities') {
        $view = $_GET['v'] ?? 'board';
        $cols = opp_dt_columns();
        $dt   = dt_state('opportunities', $cols, ['default_sort' => 'value', 'default_dir' => 'desc', 'per' => 50]);
        $filter = ['status' => (string)($_GET['status'] ?? ''), 'q' => $dt['q']];

        if (wants_csv()) {
            $csv = [['Ref','Opportunity','Customer','Stage','Status','Estimate','Weighted','%','Expected close',
                     'Owner','Branch','Against','Days in stage','Lost reason']];
            foreach (opp_all($filter) as $o)
                $csv[] = [$o['ref'], $o['name'], $o['client_name'] ?: $o['partner_name'], $o['stage_name'],
                          OPP_STATUS[$o['status']] ?? $o['status'], (float)$o['value'], opp_weighted($o),
                          (int)($o['probability'] ?: $o['stage_prob']), $o['expected_close'], $o['owner_name'],
                          $o['office_name'], $o['competitor'], opp_days_in_stage($o), $o['lost_reason']];
            csv_download('opportunities-' . date('Y-m-d') . '.csv', $csv);
        }
        $rows = $total = null;
        if ($view === 'list') {
            $total = opp_count($filter);
            $rows  = opp_all($filter, dt_sql_tail($dt, $cols, "(o.status='OPEN') DESC, s.seq, o.value DESC, o.id DESC"));
        }
        view('ops/opportunities', [
            'view' => $view, 'rows' => $rows ?: [], 'total' => (int)$total,
            'dt' => $dt, 'cols' => $cols, 'counts' => opp_counts(),
            'board' => $view === 'list' ? ['pipeline' => null, 'stages' => [], 'columns' => []] : opp_board((int)($_GET['pipeline'] ?? 0)),
            'pipelines' => pipelines_all('OPPORTUNITY'), 'canEdit' => $canEdit,
            'win' => opp_win_rate(365), 'losses' => opp_loss_reasons(365),
        ]);
        return true;
    }

    if ($route === 'opportunity') {
        $o = opp_row($_GET['id'] ?? 0);
        if (!$o) { http_response_code(404); view('notfound'); return true; }
        view('ops/opportunity_detail', [
            'o' => $o, 'quotes' => opp_quotes((int)$o['id']), 'history' => opp_history((int)$o['id']),
            'stages' => pipeline_stages((int)$o['pipeline_id']),
            'timeline' => function_exists('act_for_entity') ? act_for_entity('OPPORTUNITY', (int)$o['id'], 40) : [],
            'lostReasons' => opp_lost_reasons(), 'canEdit' => $canEdit,
            'days' => opp_days_in_stage($o), 'stalled' => opp_stalled($o),
            'offices' => ops_all("SELECT id, name FROM offices WHERE is_active=1 ORDER BY name"),
            'openQuotes' => $o['partner_id'] ? opp_try(fn() => ops_all(
                "SELECT id, quote_no, rev, status, total_amount FROM quotations
                 WHERE client_id=? AND id NOT IN (SELECT quotation_id FROM opportunity_quotes WHERE opportunity_id=?)
                 ORDER BY id DESC LIMIT 40", [(int)$o['partner_id'], (int)$o['id']])) : [],
        ]);
        return true;
    }

    ops_require($canEdit, 'You cannot change an opportunity.');

    if ($route === 'opportunity-new') {
        if ($method === 'POST') {
            $r = opp_create($_POST);
            if (!empty($r['err'])) { flash($r['err'], 'error'); redirect_back('/opportunities'); }
            flash('Opportunity ' . $r['ref'] . ' opened.');
            redirect('/opportunity?id=' . $r['id']);
        }
        view('ops/opportunity_form', [
            'pipelines' => pipelines_all('OPPORTUNITY'), 'prefill' => $_GET,
            'offices' => ops_all("SELECT id, name FROM offices WHERE is_active=1 ORDER BY name"),
            'clients' => ops_all("SELECT id, display_name, legal_name FROM business_partners WHERE is_client=1 AND status='ACTIVE' ORDER BY COALESCE(display_name, legal_name) LIMIT 800"),
            'sources' => function_exists('lk_options_or') && defined('LEAD_SOURCES') ? lk_options_or('lead_source', LEAD_SOURCES) : [],
        ]);
        return true;
    }

    if ($route === 'opportunity-edit' && $method === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        if (($e = opp_update($id, $_POST)) !== '') flash($e, 'error'); else flash('Saved.');
        redirect('/opportunity?id=' . $id);
    }

    if ($route === 'opportunity-move' && $method === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $r = opp_move($id, (int)($_POST['stage_id'] ?? 0), $_POST);
        if (!empty($r['err'])) flash($r['err'], 'error');
        else flash($r['status'] === 'WON' ? 'Won. Raise the order when the customer confirms.'
                 : ($r['status'] === 'LOST' ? 'Recorded as lost, with the reason.' : 'Moved.'));
        redirect('/opportunity?id=' . $id);
    }

    if ($route === 'opportunity-quote' && $method === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $e = ($_POST['act'] ?? '') === 'unlink'
             ? opp_unlink_quote($id, (int)($_POST['quotation_id'] ?? 0))
             : opp_link_quote($id, (int)($_POST['quotation_id'] ?? 0));
        if ($e !== '') flash($e, 'error');
        redirect('/opportunity?id=' . $id);
    }

    if ($route === 'opportunity-from-lead' && $method === 'POST') {
        $r = opp_from_lead((int)($_POST['lead_id'] ?? 0), $_POST);
        if (!empty($r['err'])) { flash($r['err'], 'error'); redirect_back('/leads'); }
        flash(!empty($r['existing'])
              ? 'That lead already has an opportunity — here it is.'
              : 'Opportunity opened from the lead. The lead stays as the record of where it came from.');
        redirect('/opportunity?id=' . $r['id']);
    }

    return false;
}
