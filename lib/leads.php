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
    $company = trim((string)($b['company_name'] ?? ''));
    if ($company === '') return ['err' => 'A lead needs a company or person to chase.'];

    $pipe = (int)($b['pipeline_id'] ?? 0) ?: (int)(pipeline_default()['id'] ?? 0);
    $stages = pipeline_stages($pipe);
    if (!$stages) return ['err' => 'That pipeline has no stages. Add some first.'];
    $stage = (int)($b['stage_id'] ?? 0) ?: (int)$stages[0]['id'];

    $u = current_user();
    $ref = lead_ref_next();
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
            ($b['owner_user_id'] ?? '') !== '' ? (int)$b['owner_user_id'] : ($u['id'] ?? null),
            substr(trim((string)($b['owner_name'] ?? ($u ? user_name($u) : ''))), 0, 150),
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

// Moving a stage is where the rules live.
function lead_move($leadId, $stageId, array $b = []) {
    $l = lead_row($leadId);
    if (!$l) return ['err' => 'That lead no longer exists.'];
    if ($l['status'] !== 'OPEN') return ['err' => 'That lead is already ' . strtolower($l['status']) . '.'];
    $to = stage_row($stageId);
    if (!$to || (int)$to['pipeline_id'] !== (int)$l['pipeline_id'])
        return ['err' => 'That stage does not belong to this lead\'s pipeline.'];

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
    $inqId = null;
    try {
        $no = 'INQ-' . date('ym') . '-' . str_pad((string)((int)ops_val("SELECT COUNT(*) FROM crm_inquiries") + 1), 3, '0', STR_PAD_LEFT);
        $pdo->prepare("INSERT INTO crm_inquiries
            (inquiry_no,client_id,client_name,contact_name,contact_email,contact_mobile,subject,
             service_requirement,sbu,source,received_date,assigned_to,status,notes,created_by,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'OPEN', ?,?,?)")
            ->execute([$no, $partnerId, $l['company_name'], $l['contact_name'], $l['contact_email'],
                       $l['contact_phone'],
                       'From lead ' . $l['ref'],
                       (string)$l['requirement'], (string)$l['sbu'], (string)$l['source'],
                       date('Y-m-d'), (string)$l['owner_name'],
                       'Converted from lead ' . $l['ref'] . '.', user_name(current_user()), date('c')]);
        $inqId = (int)$pdo->lastInsertId();
    } catch (Throwable $e) { /* CRM tables not built on this install */ }

    $stageId = (int)($b['stage_id'] ?? 0) ?: (int)$l['stage_id'];
    $pdo->prepare("UPDATE leads SET status='CONVERTED', stage_id=?, partner_id=?, converted_partner_id=?,
                   converted_inquiry_id=?, converted_at=?, converted_by=?, stage_since=?, updated_at=? WHERE id=?")
        ->execute([$stageId, $partnerId, $partnerId, $inqId, date('c'),
                   user_name(current_user()), date('c'), date('c'), (int)$l['id']]);

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
function leads_bulk($action, array $ids) {
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) return 'Nothing was ticked.';
    // Only leads this person can already see. A row hidden by branch scope must
    // not become reachable by posting its id.
    [$w, $a] = leads_where([]);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $allowed = array_map('intval', array_column(
        leads_try(fn() => ops_all("SELECT l.id " . LEADS_FROM . " WHERE ($w) AND l.id IN ($in)",
                                  array_merge($a, $ids))), 'id'));
    if (!$allowed) return 'None of those are yours to change.';
    $u = current_user();
    $now = date('c');
    $n = 0;
    foreach ($allowed as $id) {
        if ($action === 'mine') {
            // user_name() is what lead_create() writes, so a lead reassigned in
            // bulk carries the same name as one created by hand.
            db()->prepare("UPDATE leads SET owner_user_id=?, owner_name=?, updated_at=? WHERE id=?")
               ->execute([(int)$u['id'], substr(user_name($u), 0, 150), $now, $id]);
            $n++;
        } elseif ($action === 'lost') {
            // Already closed one way or another — skip rather than overwrite a
            // conversion with a loss.
            $st = (string)ops_val("SELECT status FROM leads WHERE id=?", [$id]);
            if ($st !== 'OPEN') continue;
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
        $msg = leads_bulk((string)($_POST['bulk'] ?? ''), (array)($_POST['ids'] ?? []));
        // 'ok' is not one of the styled tags — it renders as an unstyled strip.
        flash($msg, strpos($msg, 'updated') === false ? 'error' : 'success');
        redirect_back('/leads?v=list');
    }

    if ($route === 'leads') {
        $view = $_GET['v'] ?? 'board';
        $cols = leads_dt_columns();
        $dt   = dt_state('leads', $cols, ['default_sort' => 'value', 'default_dir' => 'desc', 'per' => 50]);
        $filter = ['status' => (string)($_GET['status'] ?? ''), 'q' => $dt['q']];

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
            'view' => $view, 'rows' => $rows ?: [], 'total' => (int)$total,
            'dt' => $dt, 'cols' => $cols, 'counts' => leads_counts(),
            'board' => $view === 'list' ? ['pipeline' => null, 'stages' => [], 'columns' => []]
                                        : lead_board((int)($_GET['pipeline'] ?? 0)),
            'pipelines' => pipelines_all(), 'canEdit' => $canEdit,
            'lostReasons' => function_exists('lk_options_or') ? lk_options_or('quote_lost_reason', QUOTE_LOST_REASONS) : [],
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
            'canEdit' => $canEdit, 'days' => lead_days_in_stage($l), 'stalled' => lead_stalled($l),
            'clients' => ops_all("SELECT id, display_name, legal_name FROM business_partners WHERE is_client=1 ORDER BY COALESCE(display_name, legal_name) LIMIT 500"),
        ]);
        return true;
    }

    ops_require($canEdit, 'You cannot change a lead.');

    if ($route === 'lead-new') {
        if ($method === 'POST') {
            $r = lead_create($_POST);
            if (!empty($r['err'])) { flash($r['err'], 'error'); redirect('/lead-new'); }
            flash('Lead ' . $r['ref'] . ' created.');
            redirect('/lead?id=' . $r['id']);
        }
        // Warn about a company we already know, before anything is typed twice.
        $dup = ($_GET['company'] ?? '') !== '' ? lead_possible_duplicate((string)$_GET['company']) : null;
        view('ops/lead_form', [
            'pipelines' => pipelines_all(), 'dup' => $dup, 'prefill' => $_GET,
            'offices' => ops_all("SELECT id, name FROM offices ORDER BY name"),
            'sources' => function_exists('lk_options_or') ? lk_options_or('lead_source', LEAD_SOURCES) : LEAD_SOURCES,
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
        db()->prepare("UPDATE leads SET company_name=?, contact_name=?, contact_email=?, contact_phone=?,
                       source=?, requirement=?, value=?, expected_close=?, owner_name=?,
                       next_action_on=?, next_action=?, updated_at=? WHERE id=?")
            ->execute([substr(trim((string)($_POST['company_name'] ?? '')), 0, 200),
                substr(trim((string)($_POST['contact_name'] ?? '')), 0, 150),
                substr(trim((string)($_POST['contact_email'] ?? '')), 0, 200),
                substr(trim((string)($_POST['contact_phone'] ?? '')), 0, 60),
                (string)($_POST['source'] ?? ''), (string)($_POST['requirement'] ?? ''),
                (float)($_POST['value'] ?? 0), (string)($_POST['expected_close'] ?? ''),
                substr(trim((string)($_POST['owner_name'] ?? '')), 0, 150),
                (string)($_POST['next_action_on'] ?? ''),
                substr(trim((string)($_POST['next_action'] ?? '')), 0, 255),
                date('c'), $id]);
        if (function_exists('ads_queue_lead')) ads_queue_lead($id, 'Details edited');
        flash('Saved.');
        redirect('/lead?id=' . $id);
    }
    redirect('/leads');
}
