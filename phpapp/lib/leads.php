<?php
// ============================================================================
//  Leads and the pipeline — blueprint 001 P2
//
//  You sell to companies that are not yet on the master; that answer is what
//  makes this a real table rather than a thin one. A lead is somebody we are
//  chasing before they are a customer, and the whole point is that the moment
//  they become one, NOTHING IS RETYPED.
//
//  Five rules make this a pipeline rather than a list of names. Each exists
//  because leaving it out is how CRM data rots:
//
//   1. **Stages are configurable, per pipeline.** A manufacturer's stages are
//      not an inspection body's. Nothing here hard-codes a sales process — the
//      stages are rows, and a pipeline belongs to a business unit or to
//      everybody. This is the universal requirement, applied to the one place
//      most CRMs hard-code.
//   2. **Winning a lead means converting it, not ticking a box.** A lead moved
//      to a WON stage MUST produce a customer and an inquiry. Otherwise "won"
//      is a word in a column and the funnel it fed is empty — which is exactly
//      the disconnection you called out.
//   3. **Losing a lead needs a reason from the list.** Reusing the same
//      lost-reason master the quotations already use, so win/loss analysis
//      spans the whole funnel instead of two incompatible vocabularies.
//   4. **Duplicates are caught on the way in.** The application already knows
//      how to spot a partner by GSTIN, PAN, TAN and normalised name. A lead
//      for a company we already deal with should say so before somebody builds
//      a parallel relationship with them.
//   5. **Time in stage is recorded, not inferred.** "How long does a lead sit
//      in negotiation" is the question a sales manager actually asks, and it
//      is unanswerable from a status column alone.
//
//  Scoring is deliberately a RULES ENGINE, not "AI". It reads what is on the
//  record — value, recency, stage, whether anybody has spoken to them — and
//  says which rule fired. A score whose reasoning cannot be read is a score
//  nobody trusts, and there is no outcome history to learn from yet anyway.
// ============================================================================

const LEAD_STATUS = [
    'OPEN'      => 'Open',
    'CONVERTED' => 'Converted',
    'LOST'      => 'Lost',
];

// A stage is one of three kinds. The names are the customer's; the kinds are
// what the software reasons about, so a pipeline can be renamed freely without
// breaking the rules.
const STAGE_KINDS = [
    'OPEN' => 'Still in play',
    'WON'  => 'Won — converts to a customer',
    'LOST' => 'Lost',
];

// Shipped as a starting point for a brand-new installation. Editable, and a
// business that wants seven stages called something else just changes them.
const LEAD_DEFAULT_STAGES = [
    ['New',           'OPEN', 10,  7],
    ['Contacted',     'OPEN', 25,  7],
    ['Qualified',     'OPEN', 45, 14],
    ['Proposal sent', 'OPEN', 65, 14],
    ['Negotiation',   'OPEN', 80, 10],
    ['Won',           'WON',  100, 0],
    ['Lost',          'LOST', 0,   0],
];

const LEAD_SOURCES = [
    'REFERRAL'   => 'Referral',
    'WEBSITE'    => 'Website',
    'CALL_IN'    => 'They telephoned us',
    'EMAIL_IN'   => 'They e-mailed us',
    'CAMPAIGN'   => 'Campaign',
    'EXHIBITION' => 'Exhibition or event',
    'EXISTING'   => 'An existing customer',
    'TENDER'     => 'Tender or public notice',
    'COLD'       => 'We approached them',
    'OTHER'      => 'Other',
];

// What a file attached to a lead can be. The kinds mirror the quote's, so a
// document filed against a lead carries the same meaning once the lead becomes
// a quote and the file travels with it.
const LEAD_FILE_KINDS = [
    'REQUIREMENT' => 'Requirement / RFQ from the customer',
    'ATTACHMENT'  => 'General attachment',
    'SPEC'        => 'Specification / drawing / QAP',
    'EMAIL'       => 'E-mail / correspondence',
    'OTHER'       => 'Other',
];
const LEAD_FILE_MAX = 8388608; // 8 MB per file, same ceiling as a quote's files.

function leads_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = pk_clause();
    $pdo->exec("CREATE TABLE IF NOT EXISTS pipelines (
        id $pk, name VARCHAR(120) DEFAULT '', entity_kind VARCHAR(20) DEFAULT 'LEAD',
        sbu VARCHAR(20) DEFAULT '', is_default INT DEFAULT 0, active INT DEFAULT 1,
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pipeline_stages (
        id $pk, pipeline_id INT, seq INT DEFAULT 0, name VARCHAR(120) DEFAULT '',
        kind VARCHAR(10) DEFAULT 'OPEN', probability INT DEFAULT 0,
        sla_days INT DEFAULT 0, active INT DEFAULT 1)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS leads (
        id $pk, ref VARCHAR(40) DEFAULT '',
        pipeline_id INT NULL, stage_id INT NULL,
        company_name VARCHAR(200) DEFAULT '',
        partner_id INT NULL,
        contact_name VARCHAR(150) DEFAULT '', contact_email VARCHAR(200) DEFAULT '',
        contact_phone VARCHAR(60) DEFAULT '',
        source VARCHAR(20) DEFAULT '', source_note VARCHAR(200) DEFAULT '',
        requirement TEXT,
        value DECIMAL(16,2) DEFAULT 0, expected_close VARCHAR(20) DEFAULT '',
        owner_user_id INT NULL, owner_name VARCHAR(150) DEFAULT '',
        office_id INT NULL, sbu VARCHAR(20) DEFAULT '',
        status VARCHAR(20) DEFAULT 'OPEN',
        stage_since VARCHAR(30) DEFAULT '',
        next_action_on VARCHAR(20) DEFAULT '', next_action VARCHAR(255) DEFAULT '',
        lost_reason VARCHAR(40) DEFAULT '', lost_note VARCHAR(500) DEFAULT '',
        converted_partner_id INT NULL, converted_inquiry_id INT NULL,
        converted_at VARCHAR(30) DEFAULT '', converted_by VARCHAR(150) DEFAULT '',
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '',
        updated_at VARCHAR(30) DEFAULT '')");
    // Time in stage is recorded as it happens. Deriving it later from an audit
    // log is guesswork, and "how long do we sit in negotiation" is the question
    // a sales manager actually asks.
    $pdo->exec("CREATE TABLE IF NOT EXISTS lead_stage_history (
        id $pk, lead_id INT, from_stage_id INT NULL, to_stage_id INT NULL,
        from_name VARCHAR(120) DEFAULT '', to_name VARCHAR(120) DEFAULT '',
        days_in_previous INT DEFAULT 0,
        moved_by VARCHAR(150) DEFAULT '', moved_at VARCHAR(30) DEFAULT '')");
    // Documents filed against a lead — the customer's RFQ, a spec, an e-mail
    // thread. Any number, each up to 8 MB, stored in-row as base64 exactly like
    // a quote's files. They stay for future reference and travel onto the quote
    // when one is raised from the lead. `carried_to_quote` stops a file being
    // copied onto the same quote twice.
    $pdo->exec("CREATE TABLE IF NOT EXISTS lead_files (
        id $pk, lead_id INT, kind VARCHAR(20) DEFAULT 'ATTACHMENT',
        file_name VARCHAR(200) DEFAULT '', mime VARCHAR(100) DEFAULT '', file_data LONGTEXT,
        note VARCHAR(255) DEFAULT '', carried_to_quote INT DEFAULT 0,
        uploaded_by VARCHAR(150) DEFAULT '', uploaded_at VARCHAR(30) DEFAULT '')");
    if (function_exists('act_index')) act_index('lead_files', 'idx_lead_files', '(lead_id)');
    // Preferred way to reach them. A lead you keep ringing who only ever answers
    // on WhatsApp is a lead you are annoying, not chasing. Added on an existing
    // database, so ensure_column, not a schema change nobody would re-run.
    if (function_exists('ensure_column'))
        ensure_column('leads', 'pref_contact', "VARCHAR(20) DEFAULT ''");
    if (function_exists('act_index')) {
        act_index('leads', 'idx_lead_stage', '(stage_id, status)');
        act_index('leads', 'idx_lead_owner', '(owner_user_id, status)');
        act_index('lead_stage_history', 'idx_lsh_lead', '(lead_id)');
    }
    if (function_exists('lk_ensure_type_map')) lk_ensure_type_map('lead_source', 'Lead source', LEAD_SOURCES, 'leads');
    leads_seed_default_pipeline();
}

// A brand-new installation gets one working pipeline rather than an empty
// screen that cannot be used until somebody reads the manual.
function leads_seed_default_pipeline() {
    try {
        if ((int)ops_val("SELECT COUNT(*) FROM pipelines") > 0) return;
        db()->prepare("INSERT INTO pipelines (name,entity_kind,is_default,active,created_by,created_at)
                       VALUES ('Sales pipeline','LEAD',1,1,'system',?)")->execute([date('c')]);
        $pid = (int)db()->lastInsertId();
        $st = db()->prepare("INSERT INTO pipeline_stages (pipeline_id,seq,name,kind,probability,sla_days,active)
                             VALUES (?,?,?,?,?,?,1)");
        foreach (LEAD_DEFAULT_STAGES as $i => [$n, $k, $p, $sla]) $st->execute([$pid, $i + 1, $n, $k, $p, $sla]);
    } catch (Throwable $e) { /* built on the next boot */ }
}

function leads_missing(Throwable $e) {
    $m = $e->getMessage();
    return stripos($m, 'no such table') !== false || stripos($m, "doesn't exist") !== false;
}
function leads_try($fn, $fb = []) {
    try { return $fn(); } catch (Throwable $e) { if (!leads_missing($e)) throw $e; return $fb; }
}

function leads_can_view() { return can('mod.leads.view') || can('mod.inquiries.view') || is_master_of(['leads','inquiries']); }
function leads_can_edit() { return can('mod.leads.edit') || can('mod.inquiries.edit') || is_master_of(['leads','inquiries']); }

// ---- Pipelines and stages --------------------------------------------------
function pipelines_all($kind = 'LEAD') {
    leads_migrate();
    return leads_try(fn() => ops_all(
        "SELECT * FROM pipelines WHERE entity_kind=? AND active=1 ORDER BY is_default DESC, name", [$kind]));
}

function pipeline_default($kind = 'LEAD') {
    $all = pipelines_all($kind);
    return $all ? $all[0] : null;
}

function pipeline_stages($pipelineId) {
    leads_migrate();
    return leads_try(fn() => ops_all(
        "SELECT * FROM pipeline_stages WHERE pipeline_id=? AND active=1 ORDER BY seq, id", [(int)$pipelineId]));
}

function stage_row($stageId) {
    return leads_try(fn() => ops_one("SELECT * FROM pipeline_stages WHERE id=?", [(int)$stageId]), null) ?: null;
}

// ---- Reading ---------------------------------------------------------------
function lead_ref_next() {
    $y = date('Y');
    $n = (int)ops_val("SELECT COUNT(*) FROM leads WHERE ref LIKE ?", ["L-$y-%"]);
    do { $n++; $ref = sprintf('L-%s-%04d', $y, $n); }
    while ((int)ops_val("SELECT COUNT(*) FROM leads WHERE ref=?", [$ref]) > 0);
    return $ref;
}

// The filter, built once. Counting rows and fetching a page of them must ask
// the same question or the pager lies about how many pages there are.
function leads_where($filter = []) {
    [$sw, $sa] = scope_office_clause('l.office_id');
    $w = [$sw]; $a = $sa;
    if (!empty($filter['status'])) { $w[] = 'l.status = ?'; $a[] = $filter['status']; }
    if (!empty($filter['open']))   { $w[] = "l.status = 'OPEN'"; }
    if (!empty($filter['pipeline'])) { $w[] = 'l.pipeline_id = ?'; $a[] = (int)$filter['pipeline']; }
    if (!empty($filter['owner']))  { $w[] = 'l.owner_user_id = ?'; $a[] = (int)$filter['owner']; }
    if (!empty($filter['q'])) {
        $w[] = '(l.company_name LIKE ? OR l.contact_name LIKE ? OR l.ref LIKE ? OR l.contact_email LIKE ?)';
        $like = '%' . $filter['q'] . '%'; array_push($a, $like, $like, $like, $like);
    }
    // Financial-year window (empty when "All years").
    if (!empty($filter['fy_from']))    { $w[] = 'l.created_at >= ?'; $a[] = $filter['fy_from']; }
    if (!empty($filter['fy_to_excl'])) { $w[] = 'l.created_at < ?';  $a[] = $filter['fy_to_excl']; }
    return [implode(' AND ', $w), $a];
}

const LEADS_FROM = "FROM leads l
         LEFT JOIN pipeline_stages s ON s.id = l.stage_id
         LEFT JOIN pipelines p ON p.id = l.pipeline_id
         LEFT JOIN offices o ON o.id = l.office_id
         LEFT JOIN business_partners bp ON bp.id = l.partner_id";

const LEADS_SELECT = "SELECT l.*, s.name stage_name, s.kind stage_kind, s.probability, s.sla_days, s.seq stage_seq,
                p.name pipeline_name, o.name office_name, bp.display_name, bp.legal_name ";

// $tail is an ORDER BY / LIMIT built by the register component from its own
// whitelist. Nothing from the address bar arrives here as SQL.
function leads_all($filter = [], $tail = '') {
    leads_migrate();
    [$w, $a] = leads_where($filter);
    $order = $tail ?: " ORDER BY (l.status='OPEN') DESC, s.seq, l.value DESC, l.id DESC";
    return leads_try(fn() => ops_all(LEADS_SELECT . LEADS_FROM . " WHERE $w" . $order, $a));
}

function leads_count($filter = []) {
    leads_migrate();
    [$w, $a] = leads_where($filter);
    return (int)leads_try(fn() => ops_val("SELECT COUNT(*) " . LEADS_FROM . " WHERE $w", $a), 0);
}

function lead_row($id) {
    leads_migrate();
    return leads_try(fn() => ops_one(
        "SELECT l.*, s.name stage_name, s.kind stage_kind, s.probability, s.sla_days,
                p.name pipeline_name, o.name office_name, bp.display_name, bp.legal_name
         FROM leads l
         LEFT JOIN pipeline_stages s ON s.id = l.stage_id
         LEFT JOIN pipelines p ON p.id = l.pipeline_id
         LEFT JOIN offices o ON o.id = l.office_id
         LEFT JOIN business_partners bp ON bp.id = l.partner_id
         WHERE l.id=?", [(int)$id]), null) ?: null;
}

function lead_history($id) {
    return leads_try(fn() => ops_all(
        "SELECT * FROM lead_stage_history WHERE lead_id=? ORDER BY id DESC", [(int)$id]));
}

// How long it has sat where it is. The number behind "this one has gone quiet".
function lead_days_in_stage($l, $today = null) {
    $since = trim((string)($l['stage_since'] ?? '')) ?: (string)($l['created_at'] ?? '');
    if ($since === '') return 0;
    return (int)floor(((strtotime($today ?: 'now')) - strtotime($since)) / 86400);
}

// Past the stage's own service level. Not "overdue" — a stage decides its own.
function lead_stalled($l, $today = null) {
    $sla = (int)($l['sla_days'] ?? 0);
    return ($l['status'] ?? '') === 'OPEN' && $sla > 0 && lead_days_in_stage($l, $today) > $sla;
}

// Module 17 — the open leads that need attention NOW: past their stage service
// level, or with a follow-up date (next_action_on) that has come and gone. The
// second signal finally reads next_action_on, which was stored and shown as "it
// drives your follow-up list" but which no query anywhere actually read. Read-only;
// a lead is never auto-closed — this is the weekly working list the owner clears.
function leads_due($today = null) {
    $today = $today ?: date('Y-m-d');
    $out = [];
    foreach (leads_all(['open' => 1]) as $l) {
        $reasons = [];
        if (lead_stalled($l, $today))
            $reasons[] = 'past its ' . (int)$l['sla_days'] . '-day stage service level';
        $na = substr(trim((string)($l['next_action_on'] ?? '')), 0, 10);
        if ($na !== '' && $na < $today)
            $reasons[] = 'follow-up was due ' . fdate($na) . (trim((string)($l['next_action'] ?? '')) !== '' ? ' — ' . $l['next_action'] : '');
        if (!$reasons) continue;
        $out[] = $l + ['due_reasons' => $reasons, 'due_days' => lead_days_in_stage($l, $today)];
    }
    usort($out, fn($a, $b) => (int)$b['due_days'] <=> (int)$a['due_days']);   // most urgent first
    return $out;
}
function leads_due_count($today = null) { return count(leads_due($today)); }

// ---- Scoring — a rules engine that shows its working ------------------------
// Deliberately not "AI". There is no outcome history to learn from yet, and a
// score whose reasoning cannot be read is a score nobody acts on. Every rule
// returns the sentence that justifies it.
function lead_score($l) {
    $score = 0; $why = [];
    $stageP = (int)($l['probability'] ?? 0);
    if ($stageP) { $score += (int)round($stageP * 0.4); $why[] = "stage suggests {$stageP}%"; }

    $val = (float)($l['value'] ?? 0);
    if ($val >= 1000000)   { $score += 20; $why[] = 'large value'; }
    elseif ($val >= 250000){ $score += 12; $why[] = 'good value'; }
    elseif ($val > 0)      { $score += 5;  $why[] = 'value recorded'; }
    else                   { $why[] = 'no value recorded'; }

    // Somebody actually talking to them is the strongest signal we have.
    $touch = function_exists('act_last_touch') && !empty($l['partner_id'])
        ? act_last_touch((int)$l['partner_id']) : '';
    $days = $touch ? (int)floor((time() - strtotime($touch)) / 86400) : null;
    if ($days === null)   { $why[] = 'nobody has logged a conversation'; }
    elseif ($days <= 7)   { $score += 20; $why[] = 'spoken to this week'; }
    elseif ($days <= 30)  { $score += 10; $why[] = "last spoken to {$days} days ago"; }
    else                  { $score -= 10; $why[] = "silent for {$days} days"; }

    if (lead_stalled($l)) { $score -= 15; $why[] = 'past this stage\'s service level'; }
    if (trim((string)($l['expected_close'] ?? '')) !== '' && $l['expected_close'] >= date('Y-m-d')) {
        $score += 10; $why[] = 'has a close date in the future';
    }
    if (trim((string)($l['contact_email'] ?? '')) === '' && trim((string)($l['contact_phone'] ?? '')) === '') {
        $score -= 15; $why[] = 'no way to contact them';
    }
    return ['score' => max(0, min(100, $score)), 'why' => $why];
}

// ---- Writing ---------------------------------------------------------------
// Duplicate detection on the way in, reusing the engine the partner master
// already has, so a lead for a company we already deal with says so before
// somebody builds a parallel relationship.
function lead_possible_duplicate($company, $email = '') {
    $company = trim((string)$company);
    if ($company === '') return null;
    if (function_exists('find_duplicate_partner')) {
        $hit = find_duplicate_partner($company, '', '', '', 0);
        if ($hit) return ['kind' => 'partner', 'by' => $hit['by'], 'row' => $hit['row']];
    }
    $l = leads_try(fn() => ops_one(
        "SELECT id, ref, company_name, status FROM leads WHERE LOWER(company_name)=? AND status='OPEN' LIMIT 1",
        [strtolower($company)]), null);
    if ($l) return ['kind' => 'lead', 'by' => 'name', 'row' => $l];
    return null;
}

function lead_create(array $b) {
    leads_migrate();

    // WHO WE ARE CHASING — a company already on the master, or a new name.
    //  Reported: the company field was a plain box, so an existing customer got
    //  re-typed (often mis-spelled) as if we had never heard of them, and the
    //  lead never linked back to the customer it was really for. The form now
    //  offers the client master as a dropdown. When one is picked its own name
    //  is authoritative — the free-text box is ignored, exactly as the quotation
    //  screen already does — so the lead points at the real customer and the
    //  name cannot drift. Type a name instead and it is a fresh company or a
    //  person, with no master link, which is the whole point of a lead.
    $partnerId = (int)($b['partner_id'] ?? 0);
    $company   = trim((string)($b['company_name'] ?? ''));
    if ($partnerId) {
        $bp = ops_one("SELECT id, display_name, legal_name FROM business_partners WHERE id=? AND is_client=1", [$partnerId]);
        if ($bp) $company = (string)($bp['display_name'] ?: $bp['legal_name']);
        else     $partnerId = 0;   // a stale id is not a customer; fall back to the typed name
    }
    if ($company === '') return ['err' => 'A lead needs a company or person to chase. Pick one from the master, or type a name.'];
    $b['partner_id'] = $partnerId ?: null;

    $pipe = (int)($b['pipeline_id'] ?? 0) ?: (int)(pipeline_default()['id'] ?? 0);
    $stages = pipeline_stages($pipe);
    if (!$stages) return ['err' => 'That pipeline has no stages. Add some first.'];
    $stage = (int)($b['stage_id'] ?? 0) ?: (int)$stages[0]['id'];

    $u = current_user();
    $ref = lead_ref_next();
    // Who it is allocated to. A posted id wins; otherwise it belongs to whoever
    // is entering it, which is the common case and the right default.
    $ownerId = ($b['owner_user_id'] ?? '') !== '' ? (int)$b['owner_user_id'] : (int)($u['id'] ?? 0);
    $ownerNm = '';
    if ($ownerId) {
        $ou = ops_one("SELECT * FROM users WHERE id=? AND is_active=1", [$ownerId]);
        if ($ou) $ownerNm = user_name($ou);
        else { $ownerId = (int)($u['id'] ?? 0); $ownerNm = $u ? user_name($u) : ''; }
    }
    db()->prepare("INSERT INTO leads
        (ref,pipeline_id,stage_id,company_name,partner_id,contact_name,contact_email,contact_phone,
         source,source_note,requirement,value,expected_close,owner_user_id,owner_name,office_id,sbu,
         status,stage_since,next_action_on,next_action,created_by,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'OPEN', ?,?,?,?,?,?)")
        ->execute([$ref, $pipe, $stage, substr($company, 0, 200),
            (int)($b['partner_id'] ?? 0) ?: null,
            substr(trim((string)($b['contact_name'] ?? '')), 0, 150),
            substr(trim((string)($b['contact_email'] ?? '')), 0, 200),
            substr(trim((string)($b['contact_phone'] ?? '')), 0, 60),
            (string)($b['source'] ?? ''), substr(trim((string)($b['source_note'] ?? '')), 0, 200),
            (string)($b['requirement'] ?? ''),
            (float)($b['value'] ?? 0), (string)($b['expected_close'] ?? ''),
            // The id and the name must come from the SAME place or they drift:
            // allocate to Nisha and the row could still read "admin".
            $ownerId, substr($ownerNm, 0, 150),
            ($b['office_id'] ?? '') !== '' ? (int)$b['office_id'] : (($u['home_office_id'] ?? null) ?: null),
            (string)($b['sbu'] ?? ''),
            date('c'),
            (string)($b['next_action_on'] ?? ''), substr(trim((string)($b['next_action'] ?? '')), 0, 255),
            $u ? user_name($u) : 'system', date('c'), date('c')]);
    $id = (int)db()->lastInsertId();
    if (function_exists('act_log'))
        act_log('LEAD', $id, 'SYSTEM', 'Lead ' . $ref . ' created — ' . $company,
                ['auto' => 1, 'partner_id' => (int)($b['partner_id'] ?? 0) ?: null,
                 'body' => (string)($b['requirement'] ?? '')]);
    // Tell Ads Pro this exists. Queued, never sent from here — a save must not
    // wait on another server, and must not fail because that server is down.
    if (function_exists('ads_queue_lead')) ads_queue_lead($id, 'Lead created');
    return ['id' => $id, 'ref' => $ref];
}

// The ways you can reach a prospect, and the sensible default follow-up gap for
// each. A phone call you say you will "follow up on" usually means in a couple
// of days; a WhatsApp, sooner. The gap only pre-fills a date the user can change.
const LEAD_CONTACT_METHODS = [
    'CALL'     => 'Telephone call',
    'WHATSAPP' => 'WhatsApp',
    'EMAIL'    => 'E-mail',
    'MEETING'  => 'Meeting',
    'VISIT'    => 'Site visit',
    'NOTE'     => 'Note to self',
];
// How the prospect prefers to be reached — a shorter list, because "meet me"
// is not a channel you pick up the phone and choose.
const LEAD_PREF_CONTACT = [
    'CALL'     => 'Telephone',
    'WHATSAPP' => 'WhatsApp',
    'EMAIL'    => 'E-mail',
];

// Logging a conversation — the thing a CRM is for and the thing this one could
// not do. It writes to the shared activity timeline (so it also shows on
// Customer 360) and, when a next step is given, sets the lead's follow-up so
// nobody has to remember it. Everything is optional except that SOMETHING was
// said: a blank note on a call is still a useful record that the call happened.
function lead_log_contact($leadId, array $b = []) {
    leads_migrate();
    $l = lead_row($leadId);
    if (!$l) return ['err' => 'That lead no longer exists.'];

    $method = (string)($b['method'] ?? 'CALL');
    if (!isset(LEAD_CONTACT_METHODS[$method])) $method = 'NOTE';
    $dir  = in_array(($b['direction'] ?? ''), ['IN', 'OUT'], true) ? (string)$b['direction'] : '';
    $note = trim((string)($b['note'] ?? ''));
    $when = trim((string)($b['occurred_on'] ?? '')) ?: date('Y-m-d');
    // A typed date is a day; the timeline sorts on a full timestamp, so keep the
    // time-of-day when the day is today and pin older days to their noon.
    $occurredAt = (substr($when, 0, 10) === date('Y-m-d')) ? date('c') : ($when . 'T12:00:00');

    // The method and the direction are shown as their own tags on the timeline,
    // so the subject need not repeat them — it carries who, which they do not.
    $who = trim((string)($b['with_whom'] ?? ''));
    $subject = $who !== '' ? $who : LEAD_CONTACT_METHODS[$method];

    if (function_exists('act_log'))
        act_log('LEAD', (int)$l['id'], $method, $subject, [
            'direction'  => $dir,
            'body'       => $note,
            'occurred_at'=> $occurredAt,
            'outcome'    => (string)($b['outcome'] ?? ''),
            'with_whom'  => $who ?? '',
            // How long it took — the raw material of the "effort to win" figure.
            'duration_mins' => max(0, (int)($b['time_spent'] ?? 0)),
            'partner_id' => $l['partner_id'] ?: null,
            'office_id'  => $l['office_id'] ?? '',
            'sbu'        => $l['sbu'] ?? '',
        ]);

    // The follow-up. If they told us the next step, record it on the lead so it
    // drives the "what needs chasing" list — this is the bit people forget.
    $nextAction = trim((string)($b['next_action'] ?? ''));
    $nextOn     = trim((string)($b['next_action_on'] ?? ''));
    $sets = []; $args = [];
    if ($nextAction !== '' || $nextOn !== '') {
        $sets[] = 'next_action=?';    $args[] = substr($nextAction, 0, 255);
        $sets[] = 'next_action_on=?'; $args[] = $nextOn;
    }
    // Remember how they like to be reached, if the user set it here.
    if (isset(LEAD_PREF_CONTACT[$b['pref_contact'] ?? ''])) {
        $sets[] = 'pref_contact=?';   $args[] = (string)$b['pref_contact'];
    }
    if ($sets) {
        $args[] = date('c'); $args[] = (int)$l['id'];
        db()->prepare("UPDATE leads SET " . implode(',', $sets) . ", updated_at=? WHERE id=?")->execute($args);
    }
    return ['ok' => true];
}

// Quotations raised from this lead. Tolerant on purpose: a Sales-off install
// has no quotations table, and a database whose CRM tables have not been
// migrated yet has the table but not the lead_id column. Either way this means
// "none to show", never a 500 — and it must NEVER run the CRM migration itself.
// It is called on every lead view, and migrating on a hot read path runs dozens
// of extra queries and holds the one database connection far longer, which on a
// shared server is exactly how "too many connections" is provoked. The column
// is created the ordinary way, the first time any quotation screen is opened.
function lead_quotes($leadId) {
    try {
        return ops_all("SELECT id, quote_no, rev, status, total_amount, created_at
                        FROM quotations WHERE lead_id=? ORDER BY id DESC", [(int)$leadId]);
    } catch (Throwable $e) { return []; }
}

// Files on a lead (metadata only — the base64 bytes are never loaded into a list).
function lead_files($leadId) {
    try {
        return ops_all("SELECT id, lead_id, kind, file_name, mime, note, uploaded_by, uploaded_at
                        FROM lead_files WHERE lead_id=? ORDER BY id", [(int)$leadId]);
    } catch (Throwable $e) { return []; }
}
// Copy a lead's attachments onto a quote raised from it, once each. Called when
// a quote is created/prefilled from a lead, so the papers stay with the quote
// for future reference. `carried_to_quote` guards against copying on a revision.
function lead_files_carry_to_quote($leadId, $quoteId) {
    if (!$leadId || !$quoteId) return 0;
    try {
        $rows = ops_all("SELECT * FROM lead_files WHERE lead_id=? AND carried_to_quote=0", [(int)$leadId]);
        if (!$rows) return 0;
        $ins = db()->prepare("INSERT INTO quote_files (quote_id,kind,file_name,mime,file_data,note,share_with_inspector,uploaded_by,uploaded_at) VALUES (?,?,?,?,?,?,?,?,?)");
        $mk  = db()->prepare("UPDATE lead_files SET carried_to_quote=1 WHERE id=?");
        $n = 0;
        foreach ($rows as $f) {
            // A lead's REQUIREMENT/SPEC map to the quote's CLIENT_DOC/INSP_DOC; the rest stay a general attachment.
            $qk = $f['kind'] === 'SPEC' ? 'INSP_DOC' : ($f['kind'] === 'REQUIREMENT' ? 'CLIENT_DOC' : 'ATTACHMENT');
            $note = trim('From lead — ' . ($f['note'] ?: LEAD_FILE_KINDS[$f['kind']] ?? ''));
            $ins->execute([(int)$quoteId, $qk, $f['file_name'], $f['mime'], $f['file_data'], substr($note, 0, 255), 1, $f['uploaded_by'], date('c')]);
            $mk->execute([(int)$f['id']]);
            $n++;
        }
        return $n;
    } catch (Throwable $e) { return 0; }
}

// Moving a stage is where the rules live.
function lead_move($leadId, $stageId, array $b = []) {
    $l = lead_row($leadId);
    if (!$l) return ['err' => 'That lead no longer exists.'];
    if ($l['status'] !== 'OPEN') return ['err' => 'That lead is already ' . strtolower($l['status']) . '.'];
    $to = stage_row($stageId);
    if (!$to || (int)$to['pipeline_id'] !== (int)$l['pipeline_id'])
        return ['err' => 'That stage does not belong to this lead\'s pipeline.'];

    // Same reason as the opportunity board: a move to the stage it already sits
    // in is not a move, and recording one resets the clock that the stalled-lead
    // figures count from.
    if ((int)$to['id'] === (int)$l['stage_id'])
        return ['err' => 'This lead is already at "' . $to['name'] . '".'];

    // Winning means converting. A lead marked won that produced no customer is
    // the disconnection this whole module exists to remove.
    if ($to['kind'] === 'WON') return ['convert' => true, 'stage_id' => (int)$to['id']];

    if ($to['kind'] === 'LOST') {
        $reason = (string)($b['lost_reason'] ?? '');
        if ($reason === '') return ['err' => 'Say why it was lost — the reason is what makes win/loss analysis possible.'];
        db()->prepare("UPDATE leads SET status='LOST', stage_id=?, lost_reason=?, lost_note=?,
                       stage_since=?, updated_at=? WHERE id=?")
            ->execute([(int)$to['id'], $reason, substr(trim((string)($b['lost_note'] ?? '')), 0, 500),
                       date('c'), date('c'), (int)$l['id']]);
        lead_record_move($l, $to);
        if (function_exists('act_log'))
            act_log('LEAD', (int)$l['id'], 'SYSTEM', 'Lead ' . $l['ref'] . ' lost',
                    ['auto' => 1, 'partner_id' => $l['partner_id'], 'outcome' => 'lost',
                     'body' => (string)($b['lost_note'] ?? '')]);
        if (function_exists('ads_queue_lead')) ads_queue_lead((int)$l['id'], 'Marked lost');
        return ['ok' => true];
    }

    db()->prepare("UPDATE leads SET stage_id=?, stage_since=?, updated_at=? WHERE id=?")
        ->execute([(int)$to['id'], date('c'), date('c'), (int)$l['id']]);
    lead_record_move($l, $to);
    if (function_exists('act_log'))
        act_log('LEAD', (int)$l['id'], 'SYSTEM',
                'Lead ' . $l['ref'] . ' moved to ' . $to['name'],
                ['auto' => 1, 'partner_id' => $l['partner_id']]);
    // A stage move can change what Ads Pro should call this contact — a lead that
    // reaches a stage with real probability is "qualified" over there, and that is
    // what stops it being advertised to as a stranger.
    if (function_exists('ads_queue_lead')) ads_queue_lead((int)$l['id'], 'Moved to ' . $to['name']);
    return ['ok' => true];
}

function lead_record_move($l, $to) {
    db()->prepare("INSERT INTO lead_stage_history
        (lead_id,from_stage_id,to_stage_id,from_name,to_name,days_in_previous,moved_by,moved_at)
        VALUES (?,?,?,?,?,?,?,?)")
        ->execute([(int)$l['id'], (int)$l['stage_id'] ?: null, (int)$to['id'],
                   (string)($l['stage_name'] ?? ''), (string)$to['name'],
                   lead_days_in_stage($l), user_name(current_user()), date('c')]);
}

// ---- Conversion — the point of the whole module ----------------------------
// A won lead becomes a customer AND an inquiry, carrying everything across.
// Nothing is retyped, and the funnel that already exists picks it up.
function lead_convert($leadId, array $b = []) {
    $l = lead_row($leadId);
    if (!$l) return ['err' => 'That lead no longer exists.'];
    if ($l['status'] === 'CONVERTED') return ['err' => 'That lead has already been converted.'];

    $pdo = db();
    $partnerId = (int)($b['partner_id'] ?? 0) ?: (int)($l['partner_id'] ?? 0);

    // Either attach to a customer somebody picked, or create one.
    if (!$partnerId) {
        $name = trim((string)($b['company_name'] ?? $l['company_name']));
        if ($name === '') return ['err' => 'The customer needs a name.'];
        $code = 'C-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 8)) . '-'
              . str_pad((string)((int)ops_val("SELECT COUNT(*) FROM business_partners") + 1), 4, '0', STR_PAD_LEFT);
        $pdo->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,is_vendor,status,state,created_at)
                       VALUES (?,?,?,1,0,'ACTIVE',?,?)")
            ->execute([$code, $name, $name, (string)($b['state'] ?? ''), date('c')]);
        $partnerId = (int)$pdo->lastInsertId();
        // The contact comes across too, or somebody types it again tomorrow.
        if (trim((string)$l['contact_name']) !== '') {
            try {
                $pdo->prepare("INSERT INTO partner_contacts (partner_id,name,email,mobile,is_primary)
                               VALUES (?,?,?,?,1)")
                    ->execute([$partnerId, $l['contact_name'], $l['contact_email'], $l['contact_phone']]);
            } catch (Throwable $e) { /* contact shape differs on an older install */ }
        }
    }

    // And an inquiry, so the funnel that already exists takes over.
    //
    // THE SUBJECT HAS TO SAY WHAT THE WORK IS. It used to read "From lead
    // L-2026-0011", which tells a reader nothing and — because the quotation
    // form takes its subject straight off the inquiry — meant the scope was
    // typed out a second time on the quote, having already been typed on the
    // lead. Where the lead came from is recorded in the notes below and in the
    // record links, so the subject is free to carry the work itself.
    $subject = trim(preg_replace('/\s+/', ' ', (string)$l['requirement']));
    if ($subject === '') $subject = 'From lead ' . $l['ref'];
    elseif (mb_strlen($subject) > 180) $subject = mb_substr($subject, 0, 177) . '...';

    $inqId = null;
    try {
        $no = 'INQ-' . date('ym') . '-' . str_pad((string)((int)ops_val("SELECT COUNT(*) FROM crm_inquiries") + 1), 3, '0', STR_PAD_LEFT);
        $pdo->prepare("INSERT INTO crm_inquiries
            (inquiry_no,client_id,client_name,contact_name,contact_email,contact_mobile,subject,
             service_requirement,sbu,source,received_date,assigned_to,status,notes,created_by,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'OPEN', ?,?,?)")
            ->execute([$no, $partnerId, $l['company_name'], $l['contact_name'], $l['contact_email'],
                       $l['contact_phone'],
                       $subject,
                       (string)$l['requirement'], (string)$l['sbu'], (string)$l['source'],
                       date('Y-m-d'), (string)$l['owner_name'],
                       'Converted from lead ' . $l['ref'] . '.', user_name(current_user()), date('c')]);
        $inqId = (int)$pdo->lastInsertId();
        // Record the origin as a real link, not just the note above. Best-effort:
        // an older install without the column must still convert the lead.
        try { $pdo->prepare("UPDATE crm_inquiries SET lead_id=? WHERE id=?")->execute([(int)$leadId, $inqId]); }
        catch (Throwable $e) { /* lead_id column not present yet */ }
    } catch (Throwable $e) { /* CRM tables not built on this install */ }

    $stageId = (int)($b['stage_id'] ?? 0) ?: (int)$l['stage_id'];
    $pdo->prepare("UPDATE leads SET status='CONVERTED', stage_id=?, partner_id=?, converted_partner_id=?,
                   converted_inquiry_id=?, converted_at=?, converted_by=?, stage_since=?, updated_at=? WHERE id=?")
        ->execute([$stageId, $partnerId, $partnerId, $inqId, date('c'),
                   user_name(current_user()), date('c'), date('c'), (int)$l['id']]);

    // THE DEAL ALREADY OPENED FROM THIS LEAD NEEDS THE CUSTOMER TOO. Opening an
    // opportunity happens BEFORE the lead is converted — that is the normal
    // order, you work the deal and only make them a customer when it is real.
    // But the opportunity was created with no partner_id and nothing ever went
    // back to fill it in, so a deal that came from a lead reached Won and
    // stopped dead: "no customer on the master yet, so an order has nowhere to
    // point", with no screen anywhere that could set one. The conversion knows
    // the answer; it just never told the deal.
    try {
        $pdo->prepare("UPDATE opportunities SET partner_id=?, partner_name=?, updated_at=?
                       WHERE lead_id=? AND (partner_id IS NULL OR partner_id=0)")
            ->execute([$partnerId, (string)$l['company_name'], date('c'), (int)$l['id']]);
    } catch (Throwable $e) { /* opportunities not built on this install */ }

    // Converted here means "customer" over there, and a customer must stop being
    // advertised to as a prospect. Also queue the enquiry the conversion raised.
    if (function_exists('ads_queue_lead')) ads_queue_lead((int)$l['id'], 'Converted to a customer');
    if ($inqId && function_exists('ads_queue_inquiry')) ads_queue_inquiry($inqId, 'Raised by a conversion');

    $toStage = stage_row($stageId);
    if ($toStage) lead_record_move($l, $toStage);
    if (function_exists('act_log'))
        act_log('LEAD', (int)$l['id'], 'SYSTEM',
                'Lead ' . $l['ref'] . ' converted' . ($inqId ? ' — inquiry raised' : ''),
                ['auto' => 1, 'partner_id' => $partnerId, 'outcome' => 'won']);
    return ['ok' => true, 'partner_id' => $partnerId, 'inquiry_id' => $inqId];
}

// ---- The board -------------------------------------------------------------
// Grouped by stage, which is how a pipeline is read.
function lead_board($pipelineId = 0) {
    leads_migrate();
    $pipe = $pipelineId ?: (int)(pipeline_default()['id'] ?? 0);
    // If no pipeline was explicitly chosen and the default one holds no open
    // leads, show the pipeline that actually has the work. Without this, leads
    // stranded on a previous pipeline — e.g. after an industry template made a
    // NEW default pipeline (existing leads keep their old one on purpose) —
    // silently vanish from the board while the list still shows them.
    if (!$pipelineId) {
        $openHere = (int) leads_try(fn() => ops_val("SELECT COUNT(*) FROM leads WHERE status='OPEN' AND pipeline_id=?", [$pipe]), 0);
        if ($openHere === 0) {
            $alt = leads_try(fn() => ops_val("SELECT pipeline_id FROM leads WHERE status='OPEN' AND pipeline_id IS NOT NULL GROUP BY pipeline_id ORDER BY COUNT(*) DESC LIMIT 1"), null);
            if ($alt) $pipe = (int)$alt;
        }
    }
    $stages = pipeline_stages($pipe);
    $rows = leads_all(['pipeline' => $pipe, 'open' => 1]);
    $by = [];
    foreach ($stages as $s) $by[(int)$s['id']] = ['stage' => $s, 'leads' => [], 'value' => 0.0];
    foreach ($rows as $r) {
        $sid = (int)$r['stage_id'];
        if (!isset($by[$sid])) continue;
        $by[$sid]['leads'][] = $r;
        $by[$sid]['value'] += (float)$r['value'];
    }
    return ['pipeline' => $pipe, 'stages' => $stages, 'columns' => $by];
}

function leads_counts() {
    leads_migrate();
    [$sw, $sa] = scope_office_clause('l.office_id');
    $q = function ($extra, $args = []) use ($sw, $sa) {
        return leads_try(fn() => (int)ops_val("SELECT COUNT(*) FROM leads l WHERE $sw AND ($extra)", array_merge($sa, $args)), 0);
    };
    $open = leads_all(['open' => 1]);
    $stalled = 0; $value = 0.0;
    foreach ($open as $l) { if (lead_stalled($l)) $stalled++; $value += (float)$l['value']; }
    return [
        'open' => count($open), 'value' => $value, 'stalled' => $stalled,
        'converted' => $q("l.status='CONVERTED'"), 'lost' => $q("l.status='LOST'"),
    ];
}

// ---- The register's columns -------------------------------------------------
// Sortable by anything a sales manager sorts by: biggest first, oldest in stage
// first, nearest expected close first. Each 'sort' is an expression this file
// wrote, which is why the sort key in the address bar can never be dangerous.
function leads_dt_columns() {
    return [
        'ref' => ['label' => 'Ref', 'sort' => 'l.ref', 'render' => fn($l) =>
            '<a href="/lead?id=' . (int)$l['id'] . '"><b>' . e($l['ref']) . '</b></a>'],
        'company' => ['label' => 'Company', 'sort' => 'l.company_name', 'render' => function ($l) {
            $h = e($l['company_name']);
            if ($l['contact_name']) $h .= '<br><span class="muted" style="font-size:12px">' . e($l['contact_name']) . '</span>';
            return $h;
        }],
        'stage' => ['label' => 'Stage', 'sort' => 's.seq', 'render' => fn($l) => e($l['stage_name'] ?: '—')],
        'value' => ['label' => 'Value', 'sort' => 'l.value', 'num' => true,
            'render' => fn($l) => $l['value'] ? e(fmoney($l['value'])) : '—'],
        'owner' => ['label' => 'Owner', 'sort' => 'l.owner_name',
            'render' => fn($l) => e($l['owner_name'] ?: '—')],
        'branch' => ['label' => 'Branch', 'sort' => 'o.name', 'optional' => true,
            'render' => fn($l) => e($l['office_name'] ?: '—')],
        'source' => ['label' => 'Source', 'sort' => 'l.source', 'optional' => true,
            'render' => fn($l) => e($l['source'] ?: '—')],
        'close' => ['label' => 'Expected close', 'sort' => 'l.expected_close', 'optional' => true,
            'render' => fn($l) => $l['expected_close'] ? e(fdate($l['expected_close'])) : '—'],
        'instage' => ['label' => 'In stage', 'sort' => 'l.stage_since', 'num' => true,
            'render' => fn($l) => lead_days_in_stage($l) . ' d'
                . (lead_stalled($l) ? '<br><span class="pill p-bad">late</span>' : '')],
        // Score is computed in PHP by a rules engine, so it cannot be sorted in
        // SQL without duplicating the rules there — and two copies of a rule is
        // how the two drift apart. It stays unsortable rather than half-right.
        'score' => ['label' => 'Score', 'num' => true, 'render' => function ($l) {
            $sc = lead_score($l);
            return '<span class="pill ' . ($sc['score'] >= 60 ? 'p-ok' : ($sc['score'] >= 35 ? 'p-warn' : 'p-mut'))
                 . '">' . (int)$sc['score'] . '</span>';
        }],
        'status' => ['label' => 'Status', 'sort' => 'l.status', 'render' => fn($l) =>
            '<span class="pill ' . ($l['status'] === 'CONVERTED' ? 'p-ok' : ($l['status'] === 'LOST' ? 'p-bad' : 'p-warn'))
            . '">' . e(LEAD_STATUS[$l['status']] ?? $l['status']) . '</span>'],
    ];
}

// Acting on a handful of leads at once. Both of these are things somebody does
// after a sales meeting — "these six are mine now", "these four went quiet" —
// and doing them one screen at a time is why they don't get done at all.
// The ONE eligibility rule for a bulk action on one lead, used by BOTH the preview
// (leads_bulk_plan) and the executor (leads_bulk) so the two can never disagree.
// Returns ['ok'=>bool, 'reason'=>string]. Assumes the id is already scope-allowed.
function leads_bulk_eligible($action, $id) {
    if ($action === 'lost') {
        // Already closed one way or another — skip rather than overwrite a
        // conversion with a loss.
        $st = (string)ops_val("SELECT status FROM leads WHERE id=?", [(int)$id]);
        if ($st !== 'OPEN') return ['ok' => false, 'reason' => 'already closed'];
    }
    return ['ok' => true, 'reason' => ''];
}

// The scope-allowed subset of the ticked ids — a row hidden by branch scope must
// not become reachable by posting its id. Shared by preview and executor.
function leads_bulk_allowed(array $ids) {
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) return [];
    [$w, $a] = leads_where([]);
    $in = implode(',', array_fill(0, count($ids), '?'));
    return array_map('intval', array_column(
        leads_try(fn() => ops_all("SELECT l.id " . LEADS_FROM . " WHERE ($w) AND l.id IN ($in)",
                                  array_merge($a, $ids))), 'id'));
}

// §48 preview: what a bulk action WOULD do, before it is committed. Same scope and
// same eligibility as the executor.
function leads_bulk_plan($action, array $ids) {
    $allowed = leads_bulk_allowed($ids);
    return bulk_plan($allowed, fn($id) => leads_bulk_eligible($action, $id));
}

function leads_bulk($action, array $ids) {
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) return 'Nothing was ticked.';
    // Only leads this person can already see. A row hidden by branch scope must
    // not become reachable by posting its id.
    $allowed = leads_bulk_allowed($ids);
    if (!$allowed) return 'None of those are yours to change.';
    $u = current_user();
    $now = date('c');
    $n = 0;
    foreach ($allowed as $id) {
        // Allocating to a colleague, not only to yourself. "Assign to me" was
        // the ONLY way to give a lead an owner from the register, which is the
        // opposite of what a manager standing over a list of new enquiries
        // actually needs to do.
        if ($action === 'assign') {
            $to = (int)($_POST['assign_to'] ?? 0);
            $ou = $to ? ops_one("SELECT * FROM users WHERE id=? AND is_active=1", [$to]) : null;
            if (!$ou) return 'Choose an active person to allocate these to.';
            db()->prepare("UPDATE leads SET owner_user_id=?, owner_name=?, updated_at=? WHERE id=?")
                ->execute([(int)$ou['id'], user_name($ou), $now, $id]);
            if (function_exists('act_log')) act_log('LEAD', $id, 'OWNER', 'Allocated to ' . user_name($ou) . ' in a bulk update');
            $n++;
            continue;
        }
        if ($action === 'mine') {
            // user_name() is what lead_create() writes, so a lead reassigned in
            // bulk carries the same name as one created by hand.
            db()->prepare("UPDATE leads SET owner_user_id=?, owner_name=?, updated_at=? WHERE id=?")
               ->execute([(int)$u['id'], substr(user_name($u), 0, 150), $now, $id]);
            $n++;
        } elseif ($action === 'lost') {
            // Same eligibility the preview showed: skip a lead that is already
            // closed rather than overwrite a conversion with a loss.
            if (!leads_bulk_eligible('lost', $id)['ok']) continue;
            db()->prepare("UPDATE leads SET status='LOST', lost_reason=?, updated_at=? WHERE id=?")
               ->execute([(string)($_POST['lost_reason'] ?? 'NO_RESPONSE'), $now, $id]);
            if (function_exists('act_log')) act_log('LEAD', $id, 'STATUS', 'Marked lost in a bulk update');
            if (function_exists('ads_queue_lead')) ads_queue_lead($id, 'Marked lost in a bulk update');
            $n++;
        }
    }
    $skipped = count($allowed) - $n;
    return $n . ' lead' . ($n === 1 ? '' : 's') . ' updated.'
         . ($skipped > 0 ? ' ' . $skipped . ' left alone because ' . ($skipped === 1 ? 'it was' : 'they were') . ' already closed.' : '');
}

// ---- Screens ----------------------------------------------------------------
function ops_leads($route, $method) {
    ops_require(leads_can_view(), 'You cannot open the lead register.');
    leads_migrate();
    $canEdit = leads_can_edit();

    if ($route === 'leads-bulk' && $method === 'POST') {
        ops_require($canEdit, 'You cannot change a lead.');
        $action = (string)($_POST['bulk'] ?? '');
        // §48 — a dry-run: show exactly what would change, and what would be left
        // alone and why, WITHOUT committing anything. Same scope + eligibility as
        // the real run, so the preview and the result cannot disagree.
        if (!empty($_POST['preview'])) {
            $plan = leads_bulk_plan($action, (array)($_POST['ids'] ?? []));
            $verb = $action === 'lost' ? 'marked lost' : 'reassigned';
            flash('Preview — nothing changed yet. ' . bulk_plan_summary($plan, $verb, Tl('lead')), 'info');
            redirect_back('/leads?v=list');
        }
        $msg = leads_bulk($action, (array)($_POST['ids'] ?? []));
        // 'ok' is not one of the styled tags — it renders as an unstyled strip.
        flash($msg, strpos($msg, 'updated') === false ? 'error' : 'success');
        redirect_back('/leads?v=list');
    }

    if ($route === 'leads') {
        $view = $_GET['v'] ?? 'board';
        $cols = leads_dt_columns();
        $dt   = dt_state('leads', $cols, ['default_sort' => 'value', 'default_dir' => 'desc', 'per' => 50]);
        $fy   = fy_filter();
        $filter = ['status' => (string)($_GET['status'] ?? ''), 'q' => $dt['q'],
                   'fy_from' => $fy['all'] ? '' : $fy['from'], 'fy_to_excl' => $fy['all'] ? '' : $fy['to_excl']];

        if (wants_csv()) {
            // Every row the filters match, not the page on screen.
            $csv = [['Ref','Company','Contact','Stage','Status','Value','Owner','Branch','Source','Expected close','Days in stage','Score']];
            foreach (leads_all($filter) as $r) {
                $sc = lead_score($r);
                $csv[] = [$r['ref'], $r['company_name'], $r['contact_name'], $r['stage_name'], $r['status'],
                          (float)$r['value'], $r['owner_name'], $r['office_name'], $r['source'],
                          $r['expected_close'], lead_days_in_stage($r), $sc['score']];
            }
            csv_download('leads-' . date('Y-m-d') . '.csv', $csv);
        }

        // The board needs every open lead to draw its columns; the list pages.
        // Only one of the two is fetched, so opening the board does not pay for
        // the list and the other way round.
        $rows = $total = null;
        if ($view === 'list') {
            $total = leads_count($filter);
            $rows  = leads_all($filter, dt_sql_tail($dt, $cols, "(l.status='OPEN') DESC, s.seq, l.value DESC, l.id DESC"));
        }
        view('ops/leads', [
            'view' => $view, 'rows' => $rows ?: [], 'total' => (int)$total, 'fy' => $fy['fy'],
            'dt' => $dt, 'cols' => $cols, 'counts' => leads_counts(), 'dueCount' => leads_due_count(),
            'board' => $view === 'list' ? ['pipeline' => null, 'stages' => [], 'columns' => []]
                                        : lead_board((int)($_GET['pipeline'] ?? 0)),
            'pipelines' => pipelines_all(), 'canEdit' => $canEdit,
            'lostReasons' => function_exists('lk_options_or') ? lk_options_or('quote_lost_reason', QUOTE_LOST_REASONS) : [],
            // The people a batch of leads can be allocated to from the register.
            'users' => ops_all("SELECT id, first_name, last_name, username FROM users
                                WHERE is_active=1 ORDER BY first_name, last_name"),
        ]);
        return true;
    }

    if ($route === 'lead') {
        $l = lead_row($_GET['id'] ?? 0);
        if (!$l) { http_response_code(404); view('notfound'); return true; }
        view('ops/lead_detail', [
            'l' => $l, 'score' => lead_score($l), 'history' => lead_history((int)$l['id']),
            'stages' => pipeline_stages((int)$l['pipeline_id']),
            'timeline' => function_exists('act_for_entity') ? act_for_entity('LEAD', (int)$l['id'], 50) : [],
            'lostReasons' => function_exists('lk_options_or') ? lk_options_or('quote_lost_reason', QUOTE_LOST_REASONS) : [],
            // Quotations raised straight off this lead. Newest first, so the
            // current offer is at the top.
            'quotes' => lead_quotes((int)$l['id']),
            // Documents filed against this lead, and the kinds one can be.
            'files' => lead_files((int)$l['id']), 'fileKinds' => LEAD_FILE_KINDS,
            'canQuote' => function_exists('licence_enabled') && licence_enabled('sales')
                          && (can('crm.quote.create') || can('mod.quotes.edit') || is_master_of('quotes')),
            'methods' => LEAD_CONTACT_METHODS, 'prefMethods' => LEAD_PREF_CONTACT,
            'today' => date('Y-m-d'),
            // Effort so far — this lead's logged time plus the deal it became.
            'effort' => function_exists('act_effort') ? act_effort([
                ['LEAD', (int)$l['id']],
                ['OPPORTUNITY', (int)(function_exists('opp_try')
                    ? opp_try(fn() => ops_val("SELECT id FROM opportunities WHERE lead_id=? ORDER BY id DESC LIMIT 1", [(int)$l['id']]), 0)
                    : 0)],
            ]) : ['mins' => 0, 'touches' => 0],
            'canEdit' => $canEdit, 'days' => lead_days_in_stage($l), 'stalled' => lead_stalled($l),
            'clients' => ops_all("SELECT id, display_name, legal_name FROM business_partners WHERE is_client=1 ORDER BY COALESCE(display_name, legal_name) LIMIT 500"),
            // Who a lead can be allocated to. Active logins only — allocating
            // work to somebody who cannot sign in is the same as not allocating it.
            'users' => ops_all("SELECT id, first_name, last_name, username, role FROM users
                                WHERE is_active=1 ORDER BY first_name, last_name"),
        ]);
        return true;
    }

    // Downloading a lead's file needs only view rights (already checked above).
    if ($route === 'lead-file') {
        $f = ops_one("SELECT * FROM lead_files WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$f) { http_response_code(404); echo 'Not found'; return true; }
        $bin = base64_decode((string)$f['file_data']);
        header('Content-Type: ' . ($f['mime'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $f['file_name']) . '"');
        header('Content-Length: ' . strlen($bin));
        echo $bin; return true;
    }

    ops_require($canEdit, 'You cannot change a lead.');

    // ---- Files attached to a lead — the customer's RFQ, specs, correspondence.
    if ($route === 'lead-files' && $method === 'POST') {
        $l = lead_row($_GET['id'] ?? 0);
        if (!$l) { http_response_code(404); view('notfound'); return true; }
        $kind = in_array($_POST['kind'] ?? '', array_keys(LEAD_FILE_KINDS), true) ? $_POST['kind'] : 'ATTACHMENT';
        $note = trim($_POST['note'] ?? '');
        $n = 0; $skipped = [];
        $files = $_FILES['files'] ?? null;
        if ($files && is_array($files['tmp_name'])) {
            foreach ($files['tmp_name'] as $i => $tmp) {
                if (!$tmp || !is_uploaded_file($tmp)) continue;
                $size = (int)($files['size'][$i] ?? 0);
                if ($size <= 0 || $size > LEAD_FILE_MAX) { $skipped[] = $files['name'][$i] . ' (over ' . round(LEAD_FILE_MAX / 1048576) . ' MB)'; continue; }
                db()->prepare("INSERT INTO lead_files (lead_id,kind,file_name,mime,file_data,note,uploaded_by,uploaded_at) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([(int)$l['id'], $kind, substr((string)$files['name'][$i], 0, 200),
                        (string)($files['type'][$i] ?? ''), base64_encode((string)file_get_contents($tmp)),
                        $note, user_name(current_user()), date('c')]);
                $n++;
            }
        }
        if ($n && function_exists('act_log')) act_log('LEAD', (int)$l['id'], 'FILE', $n . ' ' . strtolower(LEAD_FILE_KINDS[$kind]) . ' file(s) attached');
        flash($n ? ($n . ' file(s) attached.' . ($skipped ? ' Skipped: ' . implode(', ', $skipped) : '')) : ('Nothing uploaded.' . ($skipped ? ' Skipped: ' . implode(', ', $skipped) : '')), $n ? 'success' : 'error');
        redirect('/lead?id=' . (int)$l['id']);
    }
    if ($route === 'lead-file-delete' && $method === 'POST') {
        $f = ops_one("SELECT * FROM lead_files WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$f) { flash('File not found.', 'error'); redirect('/leads'); }
        db()->prepare("DELETE FROM lead_files WHERE id=?")->execute([(int)$f['id']]);
        flash('Attachment removed.');
        redirect('/lead?id=' . (int)$f['lead_id']);
    }

    if ($route === 'lead-new') {
        if ($method === 'POST') {
            $r = lead_create($_POST);
            if (!empty($r['err'])) { form_stash('lead-new', $_POST); flash($r['err'], 'error'); redirect('/lead-new'); }
            flash('Lead ' . $r['ref'] . ' created.');
            redirect('/lead?id=' . $r['id']);
        }
        // Warn about a company we already know, before anything is typed twice.
        $dup = ($_GET['company'] ?? '') !== '' ? lead_possible_duplicate((string)$_GET['company']) : null;
        view('ops/lead_form', [
            'pipelines' => pipelines_all(), 'dup' => $dup, 'prefill' => $_GET,
            // The client master, so a company we already know is picked, not
            // re-typed. Individuals and brand-new companies are still typed.
            'clients' => function_exists('clients_list') ? clients_list() : [],
            'offices' => ops_all("SELECT id, name FROM offices ORDER BY name"),
            'sources' => function_exists('lk_options_or') ? lk_options_or('lead_source', LEAD_SOURCES) : LEAD_SOURCES,
            // Allocating at the moment of creation. Reported from a real test:
            // a lead was added and the only way to give it to somebody was
            // "Assign to me" on the register afterwards. Whoever takes the
            // enquiry is very often not the person who will chase it.
            'users' => ops_all("SELECT id, first_name, last_name, username FROM users
                                WHERE is_active=1 ORDER BY first_name, last_name"),
            // "Next thing to do" was a free-text box, so twenty people wrote
            // twenty versions of "send profile". A master list makes it
            // countable — and it stays typeable for the one that is not on it.
            'nextActions' => function_exists('lk_options') ? lk_options('next_action') : [],
        ]);
        return true;
    }

    if ($route === 'lead-move' && $method === 'POST') {
        $r = lead_move((int)($_POST['id'] ?? 0), (int)($_POST['stage_id'] ?? 0), $_POST);
        if (!empty($r['err'])) { flash($r['err'], 'error'); redirect('/lead?id=' . (int)($_POST['id'] ?? 0)); }
        if (!empty($r['convert'])) {
            flash('That stage means the lead is won — which means it becomes a customer. Confirm the details below; nothing is retyped.', 'warning');
            redirect('/lead-convert?id=' . (int)($_POST['id'] ?? 0) . '&stage_id=' . (int)$r['stage_id']);
        }
        flash('Moved.');
        redirect('/lead?id=' . (int)($_POST['id'] ?? 0));
    }

    if ($route === 'lead-convert') {
        $l = lead_row($_GET['id'] ?? $_POST['id'] ?? 0);
        if (!$l) { http_response_code(404); view('notfound'); return true; }
        if ($method === 'POST') {
            $r = lead_convert((int)$l['id'], $_POST);
            if (!empty($r['err'])) { flash($r['err'], 'error'); redirect('/lead-convert?id=' . (int)$l['id']); }
            flash('Converted. ' . ($r['inquiry_id']
                ? 'The customer is on the master and an inquiry has been raised — carry on from there.'
                : 'The customer is on the master.'));
            redirect($r['inquiry_id'] ? '/inquiries' : '/client?id=' . (int)$r['partner_id']);
        }
        view('ops/lead_convert', [
            'l' => $l, 'stage_id' => (int)($_GET['stage_id'] ?? $l['stage_id']),
            'dup' => lead_possible_duplicate((string)$l['company_name']),
            'clients' => ops_all("SELECT id, display_name, legal_name FROM business_partners WHERE is_client=1 ORDER BY COALESCE(display_name, legal_name) LIMIT 500"),
        ]);
        return true;
    }

    if ($route === 'lead-edit' && $method === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        // Allocation, properly. Owner used to be a free-text box: you could type
        // any name at all, nothing linked to a real login, and owner_user_id —
        // which every "my leads" filter and every reminder reads — stayed empty.
        // A lead you cannot allocate is a lead nobody is accountable for.
        $ownerId = ($_POST['owner_user_id'] ?? '') !== '' ? (int)$_POST['owner_user_id'] : null;
        $ownerNm = '';
        if ($ownerId) {
            $ou = ops_one("SELECT * FROM users WHERE id=? AND is_active=1", [$ownerId]);
            if (!$ou) { flash('That person is not an active user.', 'error'); redirect('/lead?id=' . $id); }
            $ownerNm = user_name($ou);
        }
        $pref = isset(LEAD_PREF_CONTACT[$_POST['pref_contact'] ?? '']) ? (string)$_POST['pref_contact'] : '';
        db()->prepare("UPDATE leads SET company_name=?, contact_name=?, contact_email=?, contact_phone=?,
                       source=?, requirement=?, value=?, expected_close=?, owner_user_id=?, owner_name=?,
                       next_action_on=?, next_action=?, pref_contact=?, updated_at=? WHERE id=?")
            ->execute([substr(trim((string)($_POST['company_name'] ?? '')), 0, 200),
                substr(trim((string)($_POST['contact_name'] ?? '')), 0, 150),
                substr(trim((string)($_POST['contact_email'] ?? '')), 0, 200),
                substr(trim((string)($_POST['contact_phone'] ?? '')), 0, 60),
                (string)($_POST['source'] ?? ''), (string)($_POST['requirement'] ?? ''),
                (float)($_POST['value'] ?? 0), (string)($_POST['expected_close'] ?? ''),
                $ownerId, substr($ownerNm, 0, 150),
                (string)($_POST['next_action_on'] ?? ''),
                substr(trim((string)($_POST['next_action'] ?? '')), 0, 255),
                $pref, date('c'), $id]);
        if (function_exists('ads_queue_lead')) ads_queue_lead($id, 'Details edited');
        flash('Saved.');
        redirect('/lead?id=' . $id);
    }

    // Logging a conversation and, if given, the next follow-up. The one thing a
    // CRM must do that this one could not.
    if ($route === 'lead-contact' && $method === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $r = lead_log_contact($id, $_POST);
        if (!empty($r['err'])) { flash($r['err'], 'error'); redirect('/lead?id=' . $id); }
        flash('Logged. ' . (trim((string)($_POST['next_action'] ?? '')) !== ''
            ? 'The next step is on the lead.' : 'Add the next step whenever you know it.'));
        redirect('/lead?id=' . $id . '#timeline');
    }

    // Deleting a lead. There was no way to do this at all, which is why the
    // register fills with test rows nobody can clear.
    //
    // A lead that has been CONVERTED is not deleted: an opportunity, an inquiry
    // or a customer downstream points back at it, and removing the row would
    // orphan real work and destroy the record of where a customer came from.
    // Those are marked lost instead, which is what the register already models.
    if ($route === 'lead-delete' && $method === 'POST') {
        ops_require(can('mod.leads.edit') || is_master(), 'You cannot delete leads.');
        $id = (int)($_POST['id'] ?? 0);
        $l  = $id ? ops_one("SELECT * FROM leads WHERE id=?", [$id]) : null;
        if (!$l) { flash('That lead no longer exists.', 'error'); redirect('/leads'); }
        if (($l['status'] ?? '') === 'CONVERTED' || !empty($l['converted_partner_id']) || !empty($l['converted_inquiry_id'])) {
            flash('This lead has already been converted, so it is the record of where that customer came from. '
                . 'Mark it lost if it should not be chased, but it cannot be deleted.', 'error');
            redirect('/lead?id=' . $id);
        }
        if (function_exists('act_log')) act_log('LEAD', $id, 'DELETED', 'Lead ' . $l['ref'] . ' deleted — ' . $l['company_name']);
        db()->prepare("DELETE FROM leads WHERE id=?")->execute([$id]);
        flash('Lead ' . $l['ref'] . ' deleted.');
        redirect('/leads');
    }
    redirect('/leads');
}
