<?php
// ============================================================================
//  CONNECT — Manpower Marketplace core  (slice K2a, additive)
//
//  The two-sided marketplace folded into EXAACT: a POST (a technical-manpower
//  requirement) and an APPLY (a professional puts themselves forward), with the
//  post→shortlist→award lifecycle. Adopted from the Inspect Connect blueprint
//  (M7/M8/M9 intake side). See docs/connect/00-integration-program.md.
//
//  K2a scope = the engine + lifecycles + the STAFF desk, gated on existing
//  internal permissions (coordinator/master). External self-service (client
//  portal posts, vendor/inspector portal applies) is K2b, after the role→portal
//  mapping is confirmed — so this slice invents NO new external permission.
//
//  ADDITIVE CONTRACT: two new cx_ tables only; new statuses documented in
//  docs/03-object-lifecycles.md in the same commit; no existing table, route,
//  view, permission or status touched.
// ============================================================================

// --- Lifecycles (documented in docs/03-object-lifecycles.md) ----------------
const CX_REQ_STATUSES = ['DRAFT', 'OPEN', 'SHORTLISTING', 'AWARDED', 'CLOSED', 'CANCELLED', 'EXPIRED'];
const CX_REQ_TRANSITIONS = [
    'DRAFT'        => ['OPEN', 'CANCELLED'],
    'OPEN'         => ['SHORTLISTING', 'CLOSED', 'CANCELLED', 'EXPIRED'],
    'SHORTLISTING' => ['AWARDED', 'OPEN', 'CLOSED', 'CANCELLED'],
    'AWARDED'      => ['CLOSED'],
    'CLOSED'       => [], 'CANCELLED' => [], 'EXPIRED' => [],
];
const CX_APP_STATUSES = ['APPLIED', 'SHORTLISTED', 'OFFERED', 'ACCEPTED', 'DECLINED', 'WITHDRAWN', 'REJECTED'];
const CX_APP_TRANSITIONS = [
    'APPLIED'     => ['SHORTLISTED', 'REJECTED', 'WITHDRAWN'],
    'SHORTLISTED' => ['OFFERED', 'REJECTED', 'WITHDRAWN'],
    'OFFERED'     => ['ACCEPTED', 'DECLINED', 'WITHDRAWN'],
    'ACCEPTED'    => [], 'DECLINED' => [], 'WITHDRAWN' => [], 'REJECTED' => [],
];

function cx_req_can_transition($from, $to) {
    return in_array($to, CX_REQ_TRANSITIONS[strtoupper((string)$from)] ?? [], true);
}
function cx_app_can_transition($from, $to) {
    return in_array($to, CX_APP_TRANSITIONS[strtoupper((string)$from)] ?? [], true);
}

/** Additive tables — requirements (the post) and applications (the apply). */
function connect_market_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS cx_requirements (
        id $pk, ref_code VARCHAR(24) DEFAULT '', title VARCHAR(200) DEFAULT '',
        poster_party_id INT DEFAULT 0, poster_name VARCHAR(200) DEFAULT '',
        sector_code VARCHAR(40) DEFAULT '', discipline_code VARCHAR(40) DEFAULT '',
        equipment_group VARCHAR(40) DEFAULT '', material_code VARCHAR(40) DEFAULT '',
        location VARCHAR(160) DEFAULT '', work_type VARCHAR(40) DEFAULT '',
        start_date VARCHAR(20) DEFAULT '', end_date VARCHAR(20) DEFAULT '',
        positions INT DEFAULT 1, rate_min REAL DEFAULT 0, rate_max REAL DEFAULT 0,
        rate_unit VARCHAR(20) DEFAULT '', description TEXT,
        status VARCHAR(16) DEFAULT 'DRAFT', awarded_application_id INT DEFAULT 0,
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '',
        posted_at VARCHAR(30) DEFAULT '', closed_at VARCHAR(30) DEFAULT '',
        updated_at VARCHAR(30) DEFAULT '')");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_applications (
        id $pk, requirement_id INT DEFAULT 0, inspector_id INT DEFAULT 0,
        applicant_name VARCHAR(200) DEFAULT '', cover_note TEXT,
        proposed_rate REAL DEFAULT 0, status VARCHAR(16) DEFAULT 'APPLIED',
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '',
        updated_at VARCHAR(30) DEFAULT '')");
    // K2b — an external applicant (an agency/vendor applying via its portal) is
    // a party, not a pool inspector. Additive column so those applications
    // dedupe correctly on the applying party.
    if (function_exists('ensure_column')) ensure_column('cx_applications', 'applicant_party_id', 'INT DEFAULT 0');
}

/** Next requirement reference — CX-REQ-0001, monotonic on id. */
function cx_req_next_code() {
    $n = (int)ops_val("SELECT COALESCE(MAX(id),0) FROM cx_requirements") + 1;
    return 'CX-REQ-' . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

/** Create a requirement (DRAFT), or post it straight to OPEN. Returns its id. */
function cx_requirement_create(array $in, $post = false) {
    connect_market_migrate();
    $now = date('c');
    $status = $post ? 'OPEN' : 'DRAFT';
    db()->prepare("INSERT INTO cx_requirements
        (ref_code,title,poster_party_id,poster_name,sector_code,discipline_code,equipment_group,material_code,
         location,work_type,start_date,end_date,positions,rate_min,rate_max,rate_unit,description,status,
         created_by,created_at,posted_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([
            cx_req_next_code(), trim((string)($in['title'] ?? '')), (int)($in['poster_party_id'] ?? 0),
            trim((string)($in['poster_name'] ?? '')), (string)($in['sector_code'] ?? ''), (string)($in['discipline_code'] ?? ''),
            (string)($in['equipment_group'] ?? ''), (string)($in['material_code'] ?? ''),
            trim((string)($in['location'] ?? '')), (string)($in['work_type'] ?? ''),
            (string)($in['start_date'] ?? ''), (string)($in['end_date'] ?? ''),
            max(1, (int)($in['positions'] ?? 1)), (float)($in['rate_min'] ?? 0), (float)($in['rate_max'] ?? 0),
            (string)($in['rate_unit'] ?? ''), trim((string)($in['description'] ?? '')), $status,
            function_exists('user_name') ? user_name(current_user()) : '', $now, $post ? $now : '', $now,
        ]);
    return (int)db()->lastInsertId();
}

function cx_requirement_get($id) { return ops_one("SELECT * FROM cx_requirements WHERE id=?", [(int)$id]) ?: null; }

/** Move a requirement to a new status if the transition is legal. */
function cx_requirement_transition($id, $to) {
    $r = cx_requirement_get($id); if (!$r) return false;
    $to = strtoupper((string)$to);
    if (!cx_req_can_transition($r['status'], $to)) return false;
    $extra = ''; $args = [$to, date('c')];
    if ($to === 'OPEN' && ($r['posted_at'] ?? '') === '') { $extra = ', posted_at=?'; $args[] = date('c'); }
    if (in_array($to, ['CLOSED', 'CANCELLED', 'EXPIRED'], true)) { $extra = ', closed_at=?'; $args[] = date('c'); }
    $args[] = (int)$id;
    db()->prepare("UPDATE cx_requirements SET status=?, updated_at=?$extra WHERE id=?")->execute($args);
    return true;
}

/** Record an application to a requirement (APPLIED). One per inspector per req. */
function cx_application_add($requirementId, array $in) {
    connect_market_migrate();
    $requirementId = (int)$requirementId;
    $inspectorId = (int)($in['inspector_id'] ?? 0);
    $partyId = (int)($in['applicant_party_id'] ?? 0);
    $proId = (int)($in['applicant_professional_id'] ?? 0);
    // One live application per applicant per requirement — keyed on the pool
    // inspector, the applying party (agency/vendor), or the self-listed
    // professional (a freelancer applying as themselves).
    if ($inspectorId > 0) {
        if ((int)ops_val("SELECT COUNT(*) FROM cx_applications WHERE requirement_id=? AND inspector_id=?", [$requirementId, $inspectorId]) > 0) return 0;
    } elseif ($proId > 0) {
        if ((int)ops_val("SELECT COUNT(*) FROM cx_applications WHERE requirement_id=? AND applicant_professional_id=?", [$requirementId, $proId]) > 0) return 0;
    } elseif ($partyId > 0) {
        if ((int)ops_val("SELECT COUNT(*) FROM cx_applications WHERE requirement_id=? AND applicant_party_id=?", [$requirementId, $partyId]) > 0) return 0;
    }
    $name = trim((string)($in['applicant_name'] ?? ''));
    if ($name === '' && $inspectorId > 0) $name = (string)ops_val("SELECT name FROM inspectors WHERE id=?", [$inspectorId]);
    if ($name === '' && $proId > 0) $name = (string)ops_val("SELECT name FROM cx_professionals WHERE id=?", [$proId]);
    db()->prepare("INSERT INTO cx_applications (requirement_id,inspector_id,applicant_party_id,applicant_professional_id,applicant_name,cover_note,proposed_rate,status,created_by,created_at,updated_at)
                   VALUES (?,?,?,?,?,?,?, 'APPLIED', ?,?,?)")
        ->execute([$requirementId, $inspectorId, $partyId, $proId, $name, trim((string)($in['cover_note'] ?? '')),
                   (float)($in['proposed_rate'] ?? 0), function_exists('user_name') ? user_name(current_user()) : '', date('c'), date('c')]);
    return (int)db()->lastInsertId();
}

function cx_application_get($id) { return ops_one("SELECT * FROM cx_applications WHERE id=?", [(int)$id]) ?: null; }

function cx_application_transition($id, $to) {
    $a = cx_application_get($id); if (!$a) return false;
    $to = strtoupper((string)$to);
    if (!cx_app_can_transition($a['status'], $to)) return false;
    db()->prepare("UPDATE cx_applications SET status=?, updated_at=? WHERE id=?")->execute([$to, date('c'), (int)$id]);
    return true;
}

/**
 * Award a requirement to one application: the application is accepted (from
 * SHORTLISTED or OFFERED it goes to ACCEPTED) and the requirement moves to
 * AWARDED. A no-op unless both moves are legal.
 */
function cx_requirement_award($requirementId, $applicationId) {
    $r = cx_requirement_get($requirementId); $a = cx_application_get($applicationId);
    if (!$r || !$a || (int)$a['requirement_id'] !== (int)$r['id']) return false;
    if (!cx_req_can_transition($r['status'], 'AWARDED')) return false;
    // Bring the chosen application to ACCEPTED via any legal path.
    $st = strtoupper((string)$a['status']);
    if ($st === 'SHORTLISTED') { cx_application_transition($applicationId, 'OFFERED'); $st = 'OFFERED'; }
    if ($st === 'OFFERED') cx_application_transition($applicationId, 'ACCEPTED');
    if (strtoupper((string)cx_application_get($applicationId)['status']) !== 'ACCEPTED') return false;
    db()->prepare("UPDATE cx_requirements SET status='AWARDED', awarded_application_id=?, updated_at=? WHERE id=?")
        ->execute([(int)$applicationId, date('c'), (int)$requirementId]);
    return true;
}

function cx_applications_for($requirementId) {
    return ops_all("SELECT * FROM cx_applications WHERE requirement_id=? ORDER BY id", [(int)$requirementId]) ?: [];
}
function cx_requirements_list($status = '') {
    if ($status !== '') return ops_all("SELECT * FROM cx_requirements WHERE status=? ORDER BY id DESC", [$status]) ?: [];
    return ops_all("SELECT * FROM cx_requirements ORDER BY id DESC") ?: [];
}
/** K2b — a poster party's own requirements (client portal / agency portal). */
function cx_requirements_for_party($partyId) {
    return ops_all("SELECT * FROM cx_requirements WHERE poster_party_id=? ORDER BY id DESC", [(int)$partyId]) ?: [];
}
/** K2b — the open board a supplier browses to apply. */
function cx_open_requirements($limit = 200) {
    return ops_all("SELECT * FROM cx_requirements WHERE status IN ('OPEN','SHORTLISTING') ORDER BY id DESC LIMIT " . max(1, (int)$limit)) ?: [];
}
/** K2b — has an applying party already applied to this requirement? */
function cx_party_applied($requirementId, $partyId) {
    return (int)ops_val("SELECT COUNT(*) FROM cx_applications WHERE requirement_id=? AND applicant_party_id=?", [(int)$requirementId, (int)$partyId]) > 0;
}
/** K2b — count of applications on a requirement (poster's list badge). */
function cx_applications_count($requirementId) {
    return (int)ops_val("SELECT COUNT(*) FROM cx_applications WHERE requirement_id=?", [(int)$requirementId]);
}
function cx_market_summary() {
    $c = function($w = '', $a = []) { try { return (int)ops_val("SELECT COUNT(*) FROM cx_requirements" . ($w ? " WHERE $w" : ''), $a); } catch (Throwable $e) { return 0; } };
    return [
        'total'  => $c(),
        'open'   => $c("status IN ('OPEN','SHORTLISTING')"),
        'draft'  => $c("status='DRAFT'"),
        'awarded'=> $c("status='AWARDED'"),
        'apps'   => (function(){ try { return (int)ops_val("SELECT COUNT(*) FROM cx_applications"); } catch (Throwable $e) { return 0; } })(),
    ];
}

/**
 * The single on/off switch for the whole Connect marketplace module — dissolved
 * into EXAACT, but cleanly optional. Default ON; an installation can turn the
 * marketplace off in one place (`connect_enabled` setting) without touching core
 * operations. Every Connect entry point (staff routes, portals, public passport)
 * respects it.
 */
function connect_enabled() {
    return function_exists('setting_get') ? setting_get('connect_enabled', '1') === '1' : true;
}

/** Staff gate — reuses existing helpers; introduces NO new permission. */
function connect_market_can() {
    if (function_exists('connect_enabled') && !connect_enabled()) return false;
    if (function_exists('is_master') && is_master()) return true;
    if (function_exists('is_coordinator_level') && is_coordinator_level()) return true;
    return false;
}

/** Board: post a requirement + list requirements. */
function ops_connect_requirements($route, $method) {
    ops_require(connect_market_can(), 'The manpower marketplace desk is for coordinators, managers and admins.');
    connect_market_migrate();
    if ($method === 'POST') {
        $act = (string)($_POST['action'] ?? 'create');
        if ($act === 'create' || $act === 'create_post') {
            if (trim((string)($_POST['title'] ?? '')) === '') { flash('Give the requirement a title.', 'error'); redirect('/connect-requirements'); }
            $id = cx_requirement_create($_POST, $act === 'create_post');
            flash($act === 'create_post' ? 'Requirement posted — it is now open for applications.' : 'Requirement saved as a draft.');
            redirect('/connect-requirement?id=' . $id);
        }
        redirect('/connect-requirements');
    }
    view('ops/connect_requirements', [
        'summary'    => cx_market_summary(),
        'rows'       => cx_requirements_list(),
        'sectors'    => function_exists('connect_tx_rows') ? connect_tx_rows('cx_sectors') : [],
        'disciplines'=> function_exists('connect_tx_rows') ? connect_tx_rows('cx_disciplines') : [],
        'partners'   => ops_all("SELECT id, COALESCE(NULLIF(display_name,''), legal_name) AS nm FROM business_partners WHERE COALESCE(status,'ACTIVE')='ACTIVE' ORDER BY nm") ?: [],
    ]);
    return true;
}

/** Detail: a requirement, its applications, and every lifecycle action. */
function ops_connect_requirement($method) {
    ops_require(connect_market_can(), 'The manpower marketplace desk is for coordinators, managers and admins.');
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($method === 'POST') {
        $act = (string)($_POST['action'] ?? '');
        if ($act === 'req_transition') {
            cx_requirement_transition($id, $_POST['to'] ?? '') ? flash('Requirement updated.') : flash('That change is not allowed from the current status.', 'error');
        } elseif ($act === 'apply') {
            $newId = cx_application_add($id, $_POST);
            flash($newId ? 'Application recorded.' : 'That professional has already applied to this requirement.', $newId ? 'success' : 'error');
        } elseif ($act === 'app_transition') {
            cx_application_transition((int)($_POST['application_id'] ?? 0), $_POST['to'] ?? '') ? flash('Application updated.') : flash('That change is not allowed from the current status.', 'error');
        } elseif ($act === 'award') {
            cx_requirement_award($id, (int)($_POST['application_id'] ?? 0)) ? flash('Requirement awarded.') : flash('Could not award — shortlist the application first.', 'error');
        } elseif ($act === 'rate' && function_exists('cx_rating_add')) {   // K9 — two-way rating
            $newId = cx_rating_add($id, (string)($_POST['direction'] ?? ''), $_POST);
            flash($newId ? 'Rating recorded.' : 'That rating could not be saved (already rated, or the engagement is not complete).', $newId ? 'success' : 'error');
        } elseif ($act === 'dispute_raise' && function_exists('cx_dispute_raise')) {   // K9b — raise a concern
            $newId = cx_dispute_raise($id, $_POST);
            flash($newId ? 'Concern raised.' : 'Give the concern a subject first.', $newId ? 'success' : 'error');
        } elseif ($act === 'dispute_transition' && function_exists('cx_dispute_transition')) {
            $dp = cx_dispute_get((int)($_POST['dispute_id'] ?? 0));
            if ($dp && (int)$dp['requirement_id'] === (int)$id)
                cx_dispute_transition((int)$dp['id'], $_POST['to'] ?? '', (string)($_POST['resolution'] ?? ''))
                    ? flash('Concern updated.') : flash('That change is not allowed from the current status.', 'error');
        } elseif ($act === 'terms_save' && function_exists('cx_terms_save')) {   // K10 — commercial term-sheet
            cx_terms_save($id, $_POST);
            flash('Commercial terms saved.');
        } elseif ($act === 'readiness_toggle' && function_exists('cx_readiness_set')) {   // K10 — site readiness
            cx_readiness_set($id, (string)($_POST['item_key'] ?? ''), !empty($_POST['checked']));
        } elseif ($act === 'send_to_billing' && function_exists('connect_engagement_billable')) {   // Award → invoice bridge
            $ev = connect_engagement_billable($id);
            flash($ev ? 'Engagement sent to billing — it is now a pending billable event for finance to invoice.'
                      : 'Could not send to billing — award the requirement and set a rate first.', $ev ? 'success' : 'error');
        } elseif ($act === 'position_add' && function_exists('cx_position_add')) {   // M10 — crew manifest
            cx_position_add($id, $_POST) ? flash('Position added to the crew.') : flash('Give the position a role.', 'error');
        } elseif ($act === 'position_delete' && function_exists('cx_position_delete')) {
            cx_position_delete((int)($_POST['position_id'] ?? 0), $id); flash('Position removed.');
        } elseif ($act === 'book_engagement' && function_exists('connect_engage_save_for_requirement')) {   // K20 — booking basis
            [$eok, $emsg] = connect_engage_save_for_requirement($id, $_POST);
            flash($emsg, $eok ? 'success' : 'error');
        } elseif ($act === 'engagement_status' && function_exists('connect_engage_set_status')) {
            [$eok, $emsg] = connect_engage_set_status((int)($_POST['engagement_id'] ?? 0), (string)($_POST['status'] ?? ''));
            flash($emsg, $eok ? 'success' : 'error');
        }
        redirect('/connect-requirement?id=' . $id);
    }
    $req = cx_requirement_get($id);
    if (!$req) { flash('That requirement was not found.', 'error'); redirect('/connect-requirements'); }
    $open = in_array(strtoupper((string)$req['status']), ['OPEN', 'SHORTLISTING'], true);
    view('ops/connect_requirement', [
        'req'          => $req,
        'apps'         => cx_applications_for($id),
        'inspectors'   => ops_all("SELECT id, name FROM inspectors WHERE COALESCE(status,'ACTIVE')='ACTIVE' ORDER BY name") ?: [],
        'req_next'     => CX_REQ_TRANSITIONS[strtoupper((string)$req['status'])] ?? [],
        // K3 — ranked recommendations from the pool (only worth showing while open).
        // #6 — optional AI re-ranking when a provider is configured and ?ai=1 asked.
        'matches'      => ($open && function_exists('connect_match_for_requirement'))
                            ? (function () use ($req) {
                                $wantAi = ($_GET['ai'] ?? '') === '1' && function_exists('connect_match_ai_available') && connect_match_ai_available();
                                if ($wantAi && function_exists('connect_match_for_requirement_ranked')) {
                                    [$rows, $used] = connect_match_for_requirement_ranked($req, 6, true);
                                    $GLOBALS['__cx_ai_used'] = $used; return $rows;
                                }
                                return connect_match_for_requirement($req, 6);
                              })() : [],
        'ai_available' => function_exists('connect_match_ai_available') && connect_match_ai_available(),
        'ai_used'      => $GLOBALS['__cx_ai_used'] ?? false,
        // #7 — agency bench people allocated to this requirement (fulfilment view).
        'bench_allocs' => function_exists('connect_bench_allocs_for_requirement') ? connect_bench_allocs_for_requirement($id) : [],
        // K20 — the booking/engagement basis once awarded (man-days / months / …).
        'engagement'   => function_exists('connect_engage_for_requirement') ? connect_engage_for_requirement($id) : null,
        'engage_bases' => function_exists('connect_engage_bases') ? connect_engage_bases() : [],
        // K9 — two-way ratings once the engagement is awarded/closed.
        'can_rate'     => function_exists('cx_rating_allowed') && cx_rating_allowed($req),
        'ratings'      => function_exists('cx_ratings_for_requirement') ? cx_ratings_for_requirement($id) : [],
        // K9b — disputes raised on this engagement.
        'disputes'     => function_exists('cx_disputes_for_requirement') ? cx_disputes_for_requirement($id) : [],
        // K10 — commercial terms (F1) + site-readiness verdict (F3).
        'terms'        => function_exists('cx_terms_get') ? cx_terms_get($id) : null,
        'terms_fields' => function_exists('cx_terms_fields') ? cx_terms_fields() : [],
        'readiness'    => function_exists('cx_readiness_get') ? cx_readiness_get($id) : [],
        'readiness_items' => function_exists('cx_readiness_items') ? cx_readiness_items() : [],
        'readiness_score' => function_exists('cx_readiness_score') ? cx_readiness_score($id) : null,
        // K12 — Operations Advisor verdict (delay risk + what to do).
        'advisor'      => function_exists('connect_advisor_for_requirement') ? connect_advisor_for_requirement($req) : null,
        // Bridge — the billable event for this awarded engagement (if sent to billing).
        'billable'     => function_exists('connect_engagement_billable_row') ? connect_engagement_billable_row($id) : null,
        // M10 — crew manifest (positions) + rollup.
        'positions'    => function_exists('cx_positions_for') ? cx_positions_for($id) : [],
        'crew'         => function_exists('cx_crew_summary') ? cx_crew_summary($id) : null,
        'disciplines'  => function_exists('connect_tx_rows') ? connect_tx_rows('cx_disciplines') : [],
    ]);
    return true;
}
