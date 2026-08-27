<?php
// ============================================================================
//  Competence — is this engineer allowed to do this work, on this date?
//
//  ISO/IEC 17020 §6.1 requires that inspections are carried out only by people
//  the body has judged competent and has authorised. The app already held
//  certificates and e-mailed a reminder 30 days before one expired — but
//  nothing stopped a coordinator deputing somebody whose ticket had already
//  lapsed. A reminder is not a control.
//
//  The judgement call this file makes, and why:
//
//    Not every certificate an engineer holds is needed for every job. Blocking
//    on ANY lapsed certificate would stop a welding inspection because somebody's
//    first-aid card ran out, and a coordinator would rightly stop trusting the
//    system. So a certificate is only a gate when it is marked REQUIRED. That
//    is a deliberate act by whoever maintains the engineer's record.
//
//    And it can be overridden — by a manager, with a reason, recorded on the
//    deputation. Refusing outright would push people to edit the expiry date to
//    get past the gate, which destroys the very record an assessor comes to
//    read. A logged override is honest; a back-dated certificate is not.
//
//  Phase 3.2 extends this file with the authorisation matrix proper — which
//  inspection types and methods each person may sign for, and the witnessed
//  inspection record. The gate below is the part that could not wait.
// ============================================================================

function competence_migrate() {
    static $done = false; if ($done) return; $done = true;
    // Only a certificate marked required can stop an allocation.
    ensure_column('inspector_certs', 'is_mandatory', 'INT DEFAULT 0');
    // Who let a lapsed one through, and why. Held on the deputation because
    // that is the record an assessor will be looking at.
    ensure_column('jobs', 'cert_override_note', "VARCHAR(400) DEFAULT ''");
    ensure_column('jobs', 'cert_override_by', "VARCHAR(150) DEFAULT ''");
    // Slice P1 — verification state for a held credential (additive; a blank
    // value reproduces the previous date-only behaviour exactly). Lets the
    // Credential Vault show Under verification / Rejected / Superseded, which a
    // valid_to date alone cannot express.
    ensure_column('inspector_certs', 'verify_status', "VARCHAR(20) DEFAULT ''");
    ensure_column('inspector_certs', 'verified_by',   "VARCHAR(150) DEFAULT ''");
    ensure_column('inspector_certs', 'verified_at',   "VARCHAR(30) DEFAULT ''");
    ensure_column('inspector_certs', 'verify_note',   "VARCHAR(400) DEFAULT ''");
}

// Certificates that are required and had already lapsed on $onDate.
// $onDate is the date the work happens — not today. Deputing somebody for next
// month against a certificate that expires next week has to fail now, not in
// three weeks when nobody is looking.
function competence_lapsed($inspectorId, $onDate = null) {
    $inspectorId = (int)$inspectorId;
    if (!$inspectorId) return [];
    $onDate = $onDate ?: date('Y-m-d');
    try {
        $rows = ops_all(
            "SELECT name, number, valid_to FROM inspector_certs
             WHERE inspector_id=? AND COALESCE(is_mandatory,0)=1 AND valid_to <> '' AND valid_to < ?
             ORDER BY valid_to", [$inspectorId, $onDate]);
    } catch (Throwable $e) { return []; }   // pre-migration
    return $rows;
}

// The sentence shown to whoever is being stopped, or '' when nothing is.
function competence_block($inspectorId, $onDate = null) {
    $bad = competence_lapsed($inspectorId, $onDate);
    if (!$bad) return '';
    $who = ops_val("SELECT name FROM inspectors WHERE id=?", [(int)$inspectorId]) ?: 'This ' . Tl('engineer');
    $bits = [];
    foreach ($bad as $c)
        $bits[] = $c['name'] . ($c['number'] ? ' (' . $c['number'] . ')' : '') . ' — expired ' . fdate($c['valid_to']);
    return $who . ' cannot be put on work dated ' . fdate($onDate)
         . ': a required certificate had lapsed by then — ' . implode('; ', $bits) . '.';
}

// The earliest date the deputation actually covers, because that is the date
// the certificate has to be valid on. Falls back sensibly on a half-filled form.
function competence_job_date($b, $call = null) {
    foreach (['inspection_start_date', 'scheduled_date', 'inspection_end_date'] as $k)
        if (!empty($b[$k])) return substr((string)$b[$k], 0, 10);
    $dates = call_dates_parse((string)($b['inspection_dates'] ?? ''));
    if ($dates) return $dates[0];
    if ($call) foreach (['inspection_required_date', 'call_received_date'] as $k)
        if (!empty($call[$k])) return substr((string)$call[$k], 0, 10);
    return date('Y-m-d');
}

// Only a manager may let a lapsed certificate through, and only with a reason.
function competence_can_override() {
    return can('mod.jobs.edit') && (is_admin_level() || is_master());
}

// Module 24 — a single per-(inspector × job) eligibility verdict, shown while an
// inspector is being CHOSEN (the hard gate already fires on save). It MIRRORS that
// gate — BLOCKED means exactly what the save will block on — and adds advisory
// signals (expiring certs, wrong discipline, out-of-SBU) that never block. Every
// probe is guarded; missing data degrades to ELIGIBLE, never an error.
// $ctx: on_date, req_trade_id, sbu, inspection_type, activity_id, client_id.
// Returns ['status'=>'ELIGIBLE'|'CHECK'|'EXPIRING'|'BLOCKED', 'reasons'=>[{level,text}]].
function inspector_eligibility($inspectorId, $ctx = []) {
    $inspectorId = (int)$inspectorId;
    $status = 'ELIGIBLE'; $reasons = [];
    if (!$inspectorId) return ['status' => $status, 'reasons' => $reasons];
    $rank = ['ELIGIBLE' => 0, 'CHECK' => 1, 'EXPIRING' => 2, 'BLOCKED' => 3];
    $bump = function ($s) use (&$status, $rank) { if (($rank[$s] ?? 0) > ($rank[$status] ?? 0)) $status = $s; };
    $add  = function ($level, $text) use (&$reasons) { $reasons[] = ['level' => $level, 'text' => $text]; };
    $onDate = trim((string)($ctx['on_date'] ?? '')) ?: date('Y-m-d');

    // 1. Lapsed MANDATORY certificate on the work date → BLOCKED (the real hard gate).
    try {
        foreach ((array)competence_lapsed($inspectorId, $onDate) as $c) {
            $bump('BLOCKED');
            $add('block', 'Required certificate lapsed: ' . $c['name'] . (!empty($c['valid_to']) ? ' (expired ' . $c['valid_to'] . ')' : ''));
        }
    } catch (Throwable $e) {}

    // 2. Authorisation coverage — BLOCKED only when enforcement is on (mirrors auth_block);
    //    advisory CHECK when enforcement is off.
    try {
        $it = (string)($ctx['inspection_type'] ?? ''); $act = (int)($ctx['activity_id'] ?? 0); $cli = (int)($ctx['client_id'] ?? 0);
        if (function_exists('auth_enforced') && auth_enforced()) {
            if (function_exists('auth_covers') && !auth_covers($inspectorId, $it, $act, $cli, $onDate)) {
                $bump('BLOCKED'); $add('block', 'Not authorised for this work (authorisation enforcement is on)');
            }
        } elseif ($it !== '' && function_exists('auth_covers') && !auth_covers($inspectorId, $it, $act, $cli, $onDate)) {
            $bump('CHECK'); $add('check', 'No matching authorisation on record for this work');
        }
    } catch (Throwable $e) {}

    // 3. Certificate expiring soon (valid, but within 45 days of the work date) → EXPIRING
    //    (advisory). Computed inline so it never depends on an un-loaded helper.
    try {
        $soonBy = date('Y-m-d', strtotime($onDate . ' +45 days'));
        foreach (ops_all("SELECT name, valid_to FROM inspector_certs WHERE inspector_id=? AND COALESCE(valid_to,'')<>''", [$inspectorId]) as $c) {
            $vt = substr((string)$c['valid_to'], 0, 10);
            if ($vt >= $onDate && $vt <= $soonBy) { $bump('EXPIRING'); $add('warn', 'Certificate expiring soon: ' . $c['name'] . ' (' . $vt . ')'); }
        }
    } catch (Throwable $e) {}

    // 4. Discipline (trade) mismatch → CHECK (advisory; a single trade field under-describes
    //    a multi-skilled inspector, so it never blocks).
    $reqTrade = (int)($ctx['req_trade_id'] ?? 0);
    if ($reqTrade) {
        try {
            $t = (int)ops_val("SELECT trade_id FROM inspectors WHERE id=?", [$inspectorId]);
            if ($t && $t !== $reqTrade) { $bump('CHECK'); $add('check', 'Different discipline than the job asks for'); }
        } catch (Throwable $e) {}
    }

    // 5. Out-of-SBU scope → CHECK (advisory).
    $sbu = trim((string)($ctx['sbu'] ?? ''));
    if ($sbu !== '') {
        try {
            $row = ops_one("SELECT sbu, sbus FROM inspectors WHERE id=?", [$inspectorId]);
            $scope = array_values(array_filter(array_map('trim', explode(',', (string)($row['sbus'] ?? '')))));
            if (trim((string)($row['sbu'] ?? '')) !== '') $scope[] = trim((string)$row['sbu']);
            if ($scope && !in_array($sbu, $scope, true)) { $bump('CHECK'); $add('check', 'Outside their usual ' . $sbu . ' business unit'); }
        } catch (Throwable $e) {}
    }

    return ['status' => $status, 'reasons' => $reasons];
}

// The pill label + class for a verdict, for the picker / profile surfaces.
function inspector_eligibility_pill($status) {
    return [
        'ELIGIBLE' => ['✓ Eligible', 'p-ok'],
        'EXPIRING' => ['⏳ Expiring', 'p-warn'],
        'CHECK'    => ['⚠ Check', 'p-warn'],
        'BLOCKED'  => ['⛔ Blocked', 'p-bad'],
    ][$status] ?? ['—', 'p-mut'];
}

// ============================================================================
//  THE AUTHORISATION SPINE  (roadmap 3.2a)
//
//  §6.1 asks for more than a drawer of certificates. It asks the body to say,
//  and be able to show, WHAT each person is permitted to do — and to stop them
//  doing anything else. That is an authorisation, and it is a different thing
//  from a qualification:
//
//      a qualification is what somebody HAS  (CSWIP 3.1, valid to 2027)
//      an authorisation is what WE PERMIT    (may sign final inspections for
//                                             this client, to March, at this level)
//
//  Four decisions, all of which will be argued about, so they are written down:
//
//  1. ENFORCEMENT IS OPT-IN, DEFAULT OFF. Switching this on for an existing
//     customer who has recorded no authorisations yet would make every
//     allocation fail on the same afternoon. They switch it on when the matrix
//     is populated — and the screen tells them how many people are covered
//     before they do.
//
//  2. SCOPE REUSES THE APP'S OWN MASTERS. The type of inspection and the
//     activity code are already configurable lists that every call and
//     deputation carries. Inventing parallel "industry / asset / activity"
//     taxonomies would mean three more lists to keep true and no link to the
//     work actually being scheduled. A company that wants industry-level
//     authorisation adds an industry value to its own master.
//
//  3. AN AUTHORISATION CAN BE SUSPENDED WITHOUT BEING DELETED. A withdrawn
//     permission is evidence — of a decision, on a date, by somebody. Deleting
//     it destroys the only record that the body acted.
//
//  4. LEVELS ARE A LIST, NOT AN ENUM IN CODE. Trainee through Technical
//     Authority is the shipped starting point; a body with different grades
//     edits the list rather than waiting for a new version of the software.
// ============================================================================

const AUTH_STATUS = ['ACTIVE' => 'Active', 'SUSPENDED' => 'Suspended', 'EXPIRED' => 'Expired', 'WITHDRAWN' => 'Withdrawn'];
const AUTH_SCOPES = ['ANY' => 'Any work', 'INSPECTION_TYPE' => 'A type of inspection', 'ACTIVITY' => 'An activity code', 'CLIENT' => 'One client'];
const WITNESS_OUTCOME = ['PASS' => 'Competent — authorisation may stand', 'RETRAIN' => 'Retraining required', 'REASSESS' => 'Re-assessment required'];

// The grades a body authorises at. A shipped starting point, editable as a
// master list like everything else in this app.
const AUTH_LEVELS = [
    'TRAINEE'    => 'Trainee',
    'SUPERVISED' => 'Under supervision',
    'JUNIOR'     => 'Junior',
    'INSPECTOR'  => 'Inspector',
    'SENIOR'     => 'Senior',
    'LEAD'       => 'Lead',
    'SPECIALIST' => 'Technical specialist',
    'AUTHORITY'  => 'Technical authority',
];

// What the assessor scores at a witnessed inspection. Editable the same way.
const WITNESS_CRITERIA = [
    'preparation' => 'Preparation & planning',
    'safety'      => 'Safety behaviour on site',
    'technical'   => 'Technical knowledge',
    'standards'   => 'Knowledge of the applicable standards',
    'technique'   => 'Inspection technique',
    'judgement'   => 'Professional judgement',
    'reporting'   => 'Report writing & evidence',
    'conduct'     => 'Client handling & impartiality',
];

function competence_spine_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = pk_clause();
    // The library of qualifications the body recognises. Configurable, because
    // a body inspecting lifts and a body inspecting pipelines share nothing.
    $pdo->exec("CREATE TABLE IF NOT EXISTS qualifications (
        id $pk, code VARCHAR(60), name VARCHAR(200) DEFAULT '', scheme VARCHAR(80) DEFAULT '',
        issuing_body VARCHAR(200) DEFAULT '', level VARCHAR(60) DEFAULT '',
        renewal_months INT DEFAULT 0, active INT DEFAULT 1, notes VARCHAR(500) DEFAULT '',
        created_at VARCHAR(30) DEFAULT '')");
    // What we permit each person to do.
    $pdo->exec("CREATE TABLE IF NOT EXISTS authorisations (
        id $pk, inspector_id INT, level VARCHAR(30) DEFAULT 'INSPECTOR',
        scope_kind VARCHAR(30) DEFAULT 'ANY', scope_value VARCHAR(80) DEFAULT '',
        valid_from VARCHAR(20) DEFAULT '', valid_to VARCHAR(20) DEFAULT '',
        status VARCHAR(20) DEFAULT 'ACTIVE', status_reason VARCHAR(400) DEFAULT '',
        granted_by VARCHAR(150) DEFAULT '', granted_at VARCHAR(30) DEFAULT '',
        changed_by VARCHAR(150) DEFAULT '', changed_at VARCHAR(30) DEFAULT '',
        notes VARCHAR(500) DEFAULT '')");
    // §6.1.8 — the body must monitor its people, not merely qualify them once.
    $pdo->exec("CREATE TABLE IF NOT EXISTS witness_assessments (
        id $pk, inspector_id INT, job_id INT NULL, assessed_on VARCHAR(20) DEFAULT '',
        assessor VARCHAR(150) DEFAULT '', location VARCHAR(200) DEFAULT '',
        scores TEXT, outcome VARCHAR(20) DEFAULT 'PASS', remarks VARCHAR(1000) DEFAULT '',
        next_due VARCHAR(20) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // Tie a held certificate to the library entry it is an instance of.
    ensure_column('inspector_certs', 'qualification_id', 'INT NULL');
}

// ---- Is enforcement switched on? -------------------------------------------
// Default OFF, and it stays off until somebody deliberately turns it on.
function auth_enforced() { return setting_get('authorisation_enforce', '0') === '1'; }

// ---- The matrix ------------------------------------------------------------
function authorisations_for($inspectorId, $includeInactive = false) {
    $w = $includeInactive ? '' : " AND status='ACTIVE'";
    try {
        return ops_all("SELECT * FROM authorisations WHERE inspector_id=?$w ORDER BY scope_kind, scope_value, id",
                       [(int)$inspectorId]);
    } catch (Throwable $e) { return []; }
}

// Has this authorisation actually run out, whatever the stored status says?
function auth_live($a, $onDate = null) {
    $onDate = $onDate ?: date('Y-m-d');
    if (($a['status'] ?? '') !== 'ACTIVE') return false;
    if (!empty($a['valid_from']) && $a['valid_from'] > $onDate) return false;
    if (!empty($a['valid_to'])   && $a['valid_to']   < $onDate) return false;
    return true;
}

// Does this person hold an authorisation covering this work on this date?
// ANY covers everything; a scoped one must match the value on the deputation.
function auth_covers($inspectorId, $inspectionType, $activityId, $clientId, $onDate = null) {
    foreach (authorisations_for($inspectorId) as $a) {
        if (!auth_live($a, $onDate)) continue;
        switch ($a['scope_kind']) {
            case 'ANY': return $a;
            case 'INSPECTION_TYPE': if ($inspectionType !== '' && $a['scope_value'] === $inspectionType) return $a; break;
            case 'ACTIVITY':        if ($activityId && (string)$a['scope_value'] === (string)$activityId) return $a; break;
            case 'CLIENT':          if ($clientId && (string)$a['scope_value'] === (string)$clientId) return $a; break;
        }
    }
    return null;
}

// The sentence shown when somebody is not authorised. '' when they are, or
// when enforcement is switched off.
function auth_block($inspectorId, $inspectionType, $activityId, $clientId, $onDate = null) {
    if (!auth_enforced() || !$inspectorId) return '';
    if (auth_covers($inspectorId, $inspectionType, $activityId, $clientId, $onDate)) return '';
    $who = ops_val("SELECT name FROM inspectors WHERE id=?", [(int)$inspectorId]) ?: 'This ' . Tl('engineer');
    $held = authorisations_for($inspectorId, true);
    $why = !$held
        ? ' — nothing is on their authorisation record at all.'
        : ' — what they hold does not cover this work, or has lapsed or been suspended.';
    $ref = function_exists('accreditation_ref') ? accreditation_ref('competence') : 'ISO/IEC 17020 §6.1';
    if ($ref === '') $ref = 'The accreditation standard';
    return $who . ' is not authorised for this work' . $why
         . ' ' . $ref . ': only authorised personnel may carry out the work.';
}

// ---- Keeping the matrix honest --------------------------------------------
// Run from cron. Two jobs: retire authorisations that have run out, and
// suspend any that rest on a required certificate which has lapsed. Both are
// recorded with a reason, because a withdrawn permission is evidence of a
// decision and must never look like a data-entry accident.
function auth_run_maintenance($today = null) {
    $today = $today ?: date('Y-m-d');
    $expired = 0; $suspended = 0;
    try {
        foreach (ops_all("SELECT * FROM authorisations WHERE status='ACTIVE'") as $a) {
            if (!empty($a['valid_to']) && $a['valid_to'] < $today) {
                db()->prepare("UPDATE authorisations SET status='EXPIRED', status_reason=?, changed_by=?, changed_at=? WHERE id=?")
                    ->execute(['Ran out on ' . $a['valid_to'] . '.', 'system', date('c'), $a['id']]);
                $expired++;
                continue;
            }
            // A person whose required ticket has lapsed cannot hold a live
            // authorisation, whatever its own end date says.
            if (competence_lapsed((int)$a['inspector_id'], $today)) {
                $bad = competence_lapsed((int)$a['inspector_id'], $today);
                db()->prepare("UPDATE authorisations SET status='SUSPENDED', status_reason=?, changed_by=?, changed_at=? WHERE id=?")
                    ->execute(['Suspended automatically: required certificate lapsed (' . $bad[0]['name'] . ').',
                               'system', date('c'), $a['id']]);
                $suspended++;
            }
        }
    } catch (Throwable $e) { return ['expired' => 0, 'suspended' => 0]; }
    return ['expired' => $expired, 'suspended' => $suspended];
}

// ---- Witness assessments ---------------------------------------------------
function witness_for($inspectorId) {
    try { return ops_all("SELECT * FROM witness_assessments WHERE inspector_id=? ORDER BY assessed_on DESC, id DESC", [(int)$inspectorId]); }
    catch (Throwable $e) { return []; }
}
function witness_latest($inspectorId) { $r = witness_for($inspectorId); return $r[0] ?? null; }

// Overdue when the last assessment said "come back by" and that date has gone,
// or when there has never been one at all.
function witness_overdue($inspectorId, $today = null) {
    $today = $today ?: date('Y-m-d');
    $last = witness_latest($inspectorId);
    if (!$last) return true;
    return !empty($last['next_due']) && $last['next_due'] < $today;
}

// ---- Audit readiness -------------------------------------------------------
// The one screen an assessor is shown. Everything about every person, in the
// order the questions get asked: are they qualified, are they authorised, is
// anything lapsed, when were they last watched doing the job.
function competence_matrix() {
    $rows = [];
    foreach (ops_all("SELECT id, name, emp_code, status FROM inspectors WHERE status='ACTIVE' ORDER BY name") as $i) {
        $auth   = authorisations_for($i['id']);
        $live   = array_values(array_filter($auth, fn($a) => auth_live($a)));
        $lapsed = competence_lapsed($i['id']);
        $certs  = [];
        try { $certs = ops_all("SELECT name, valid_to, is_mandatory FROM inspector_certs WHERE inspector_id=? ORDER BY valid_to", [$i['id']]); }
        catch (Throwable $e) {}
        $due = 0;
        foreach ($certs as $c)
            if ($c['valid_to'] !== '' && $c['valid_to'] >= date('Y-m-d')
                && $c['valid_to'] <= date('Y-m-d', strtotime('+30 days'))) $due++;
        $rows[] = [
            'inspector'   => $i,
            'certs'       => count($certs),
            'mandatory'   => count(array_filter($certs, fn($c) => !empty($c['is_mandatory']))),
            'lapsed'      => count($lapsed),
            'due_soon'    => $due,
            'auth_live'   => count($live),
            'auth_total'  => count(authorisations_for($i['id'], true)),
            'scopes'      => array_values(array_unique(array_map(fn($a) => $a['scope_kind'] === 'ANY'
                                 ? 'any work' : auth_scope_label($a), $live))),
            'witness'     => witness_latest($i['id']),
            'witness_due' => witness_overdue($i['id']),
        ];
    }
    return $rows;
}

// Module 43 — the ACTIONABLE drill-down the matrix counts don't give: exactly which
// person and which certificate/training ticket is lapsed or coming up for refresh,
// across every active inspector, in one worklist. Read-only over inspector_certs;
// it changes nothing (certs already remind on their own expiry — this is the "who +
// what" a manager works from).
function competence_training_watch($withinDays = 45, $today = null) {
    $today = $today ?: date('Y-m-d');
    $soon = date('Y-m-d', strtotime($today . ' +' . max(0, (int)$withinDays) . ' days'));
    $out = [];
    try {
        $rows = ops_all(
            "SELECT c.inspector_id, i.name inspector, i.emp_code, c.name cert, c.number, c.valid_to, c.is_mandatory
             FROM inspector_certs c JOIN inspectors i ON i.id = c.inspector_id
             WHERE i.status='ACTIVE' AND COALESCE(c.valid_to,'') <> '' AND c.valid_to <= ?
             ORDER BY c.valid_to", [$soon]) ?: [];
    } catch (Throwable $e) { return []; }   // pre-migration
    foreach ($rows as $r) {
        $vt = (string)$r['valid_to'];
        $out[] = $r + ['state' => $vt < $today ? 'lapsed' : 'expiring',
                       'days' => (int)floor((strtotime($vt) - strtotime($today)) / 86400)];
    }
    usort($out, fn($a, $b) => [$a['state'] !== 'lapsed', $a['days']] <=> [$b['state'] !== 'lapsed', $b['days']]);
    return $out;
}
function competence_training_watch_counts($withinDays = 45) {
    $l = 0; $e = 0;
    foreach (competence_training_watch($withinDays) as $x) { if ($x['state'] === 'lapsed') $l++; else $e++; }
    return ['lapsed' => $l, 'expiring' => $e, 'total' => $l + $e];
}

// Human label for what an authorisation covers.
function auth_scope_label($a) {
    switch ($a['scope_kind']) {
        case 'ANY': return 'Any work';
        case 'INSPECTION_TYPE':
            return (lk_options_or('inspection_type', INSPECTION_TYPES)[$a['scope_value']] ?? $a['scope_value']);
        case 'ACTIVITY':
            $v = $a['scope_value'] ? lk_value((int)$a['scope_value']) : null;
            return $v['label'] ?? ('Activity ' . $a['scope_value']);
        case 'CLIENT':
            return (string)(ops_val("SELECT COALESCE(display_name, legal_name) FROM business_partners WHERE id=?",
                                    [(int)$a['scope_value']]) ?: 'Client ' . $a['scope_value']);
    }
    return $a['scope_value'];
}

// How ready this body is for an assessment, as four numbers and a verdict.
function competence_readiness() {
    $m = competence_matrix();
    $n = count($m);
    $withAuth = count(array_filter($m, fn($r) => $r['auth_live'] > 0));
    $lapsed   = count(array_filter($m, fn($r) => $r['lapsed'] > 0));
    $noWitness= count(array_filter($m, fn($r) => $r['witness_due']));
    return [
        'people' => $n, 'authorised' => $withAuth, 'lapsed' => $lapsed,
        'witness_due' => $noWitness,
        'pct' => $n ? (int)round($withAuth / $n * 100) : 0,
        'enforced' => auth_enforced(),
    ];
}

// Grant (or refresh) an authorisation from one set of facts — used by the
// manual grant form AND by a passing witnessed assessment, so a competence is
// recorded once instead of typed twice: assess the person, and the permission
// that assessment supports is created from it, carrying its basis and a
// reference back to the evidence. Returns the new row id.
function auth_grant(array $a) {
    $kind = (string)($a['scope_kind'] ?? 'ANY');
    if (!isset(AUTH_SCOPES[$kind])) $kind = 'ANY';
    db()->prepare("INSERT INTO authorisations
        (inspector_id,level,scope_kind,scope_value,valid_from,valid_to,status,granted_by,granted_at,notes,basis,basis_ref,review_months,witness_every_months)
        VALUES (?,?,?,?,?,?, 'ACTIVE', ?,?,?,?,?,?,?)")
        ->execute([
            (int)($a['inspector_id'] ?? 0), (string)($a['level'] ?? 'INSPECTOR'), $kind,
            $kind === 'ANY' ? '' : (string)($a['scope_value'] ?? ''),
            (string)($a['valid_from'] ?? date('Y-m-d')), (string)($a['valid_to'] ?? ''),
            user_name(current_user()), date('c'),
            substr((string)($a['notes'] ?? ''), 0, 500),
            (string)($a['basis'] ?? ''), substr((string)($a['basis_ref'] ?? ''), 0, 200),
            (int)($a['review_months'] ?? 0), (int)($a['witness_every_months'] ?? 0),
        ]);
    return (int)db()->lastInsertId();
}

// ---- Screens ---------------------------------------------------------------
function ops_competence($route, $method) {
    ops_require(can('mod.users.view') || is_admin_level() || is_master_of('users'),
                'Only a manager can open the competence register.');

    if ($route === 'competence') {
        view('ops/competence', ['matrix' => competence_matrix(), 'ready' => competence_readiness(),
                                'canEdit' => competence_can_authorise(),
                                'trainWatch' => competence_training_watch()]);   // Module 43
        return true;
    }
    ops_require(competence_can_authorise(), 'Only a manager can grant or withdraw an authorisation.');

    // Slice P1 — set a held credential's verification verdict.
    if ($route === 'cert-verify' && $method === 'POST') {
        $cid  = (int)($_POST['id'] ?? 0);
        $cert = $cid ? ops_one("SELECT * FROM inspector_certs WHERE id=?", [$cid]) : null;
        if (!$cert) { flash('Certificate not found.', 'error'); redirect('/competence'); }
        $vs = strtoupper(trim((string)($_POST['verify_status'] ?? '')));
        if (!isset(CREDENTIAL_VERIFY_STATES[$vs])) $vs = '';
        db()->prepare("UPDATE inspector_certs SET verify_status=?, verified_by=?, verified_at=?, verify_note=? WHERE id=?")
            ->execute([$vs, user_name(current_user()), date('c'),
                       substr(trim((string)($_POST['verify_note'] ?? '')), 0, 400), $cid]);
        flash('Credential verification updated.');
        redirect('/entity-360?kind=INSPECTOR&id=' . (int)$cert['inspector_id']);
    }
    // Slice P1 — create the customforms register that holds requirement sets.
    if ($route === 'credential-req-init' && $method === 'POST') {
        $f = credential_req_form_ensure();
        if ($f) { flash('Competency requirement register created. Add its fields, then create sets.'); redirect('/custom-fields?entity=' . $f['slug']); }
        flash('Could not create the register.', 'error');
        redirect('/competence');
    }

    if ($route === 'auth-enforce' && $method === 'POST') {
        $on = !empty($_POST['on']) ? '1' : '0';
        // Refuse to switch it on while nobody is authorised — that would stop
        // every allocation in the company on the same afternoon.
        if ($on === '1') {
            $r = competence_readiness();
            if ($r['authorised'] === 0) {
                flash('Nobody has an authorisation yet, so switching this on would stop every allocation. '
                    . 'Grant at least one first.', 'error');
                redirect('/competence');
            }
            if ($r['authorised'] < $r['people'])
                flash('Enforcement is ON. ' . ($r['people'] - $r['authorised']) . ' of ' . $r['people']
                    . ' active ' . Tlp('engineer') . ' have no live authorisation and cannot now be allocated.', 'warning');
            else flash('Enforcement is ON. Every active ' . Tl('engineer') . ' is covered.');
        } else {
            flash('Enforcement is OFF. Authorisations are still recorded, but they no longer stop an allocation.');
        }
        setting_set('authorisation_enforce', $on);
        redirect('/competence');
    }
    if ($route === 'auth-add' && $method === 'POST') {
        $ins = (int)($_POST['inspector_id'] ?? 0);
        $kind = (string)($_POST['scope_kind'] ?? 'ANY');
        if (!isset(AUTH_SCOPES[$kind])) $kind = 'ANY';
        $basis = (string)($_POST['basis'] ?? '');
        if (!isset(AUTH_BASES[$basis])) $basis = '';
        auth_grant([
            'inspector_id' => $ins,
            'level'        => (string)($_POST['level'] ?? 'INSPECTOR'),
            'scope_kind'   => $kind,
            'scope_value'  => $kind === 'ANY' ? '' : (string)($_POST['scope_value'] ?? ''),
            'valid_from'   => (string)($_POST['valid_from'] ?? date('Y-m-d')),
            'valid_to'     => (string)($_POST['valid_to'] ?? ''),
            'notes'        => substr(trim((string)($_POST['notes'] ?? '')), 0, 500),
            'basis'        => $basis,
            'basis_ref'    => substr(trim((string)($_POST['basis_ref'] ?? '')), 0, 200),
            'review_months'        => (int)($_POST['review_months'] ?? 0),
            'witness_every_months' => (int)($_POST['witness_every_months'] ?? 0),
        ]);
        flash('Authorisation granted.');
        redirect('/competence?i=' . $ins);
    }
    if ($route === 'auth-status' && $method === 'POST') {
        $a = ops_one("SELECT * FROM authorisations WHERE id=?", [(int)($_POST['id'] ?? 0)]);
        $to = (string)($_POST['status'] ?? '');
        $reason = trim((string)($_POST['reason'] ?? ''));
        if (!$a || !isset(AUTH_STATUS[$to])) redirect('/competence');
        if ($to !== 'ACTIVE' && $reason === '') {
            flash('Say why it is being ' . strtolower(AUTH_STATUS[$to]) . '. A withdrawn permission is a decision, '
                . 'and an assessor will ask who took it and on what grounds.', 'error');
            redirect('/competence?i=' . (int)$a['inspector_id']);
        }
        db()->prepare("UPDATE authorisations SET status=?, status_reason=?, changed_by=?, changed_at=? WHERE id=?")
            ->execute([$to, $reason, user_name(current_user()), date('c'), $a['id']]);
        flash('Authorisation ' . strtolower(AUTH_STATUS[$to]) . '.');
        redirect('/competence?i=' . (int)$a['inspector_id']);
    }
    if ($route === 'witness-add' && $method === 'POST') {
        $ins = (int)($_POST['inspector_id'] ?? 0);
        $scores = [];
        foreach (WITNESS_CRITERIA as $k => $lbl) {
            $v = (int)($_POST['s'][$k] ?? 0);
            if ($v >= 1 && $v <= 5) $scores[$k] = $v;
        }
        $outcome = (string)($_POST['outcome'] ?? 'PASS');
        if (!isset(WITNESS_OUTCOME[$outcome])) $outcome = 'PASS';
        $on = (string)($_POST['assessed_on'] ?? date('Y-m-d'));
        db()->prepare("INSERT INTO witness_assessments (inspector_id,job_id,assessed_on,assessor,location,scores,outcome,remarks,next_due,created_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$ins, ($_POST['job_id'] ?? '') !== '' ? (int)$_POST['job_id'] : null,
                       $on, user_name(current_user()),
                       substr(trim((string)($_POST['location'] ?? '')), 0, 200),
                       json_encode($scores), $outcome,
                       substr(trim((string)($_POST['remarks'] ?? '')), 0, 1000),
                       (string)($_POST['next_due'] ?? ''), date('c')]);
        $waId = (int)db()->lastInsertId();
        // A failed assessment suspends what it was testing — that is the point
        // of watching somebody work rather than filing a form about it.
        if ($outcome !== 'PASS') {
            foreach (authorisations_for($ins) as $a)
                db()->prepare("UPDATE authorisations SET status='SUSPENDED', status_reason=?, changed_by=?, changed_at=? WHERE id=?")
                    ->execute(['Suspended by witnessed assessment on ' . $on
                               . ': ' . WITNESS_OUTCOME[$outcome] . '.', user_name(current_user()), date('c'), $a['id']]);
            flash('Assessment recorded. Their authorisations have been suspended — ' . strtolower(WITNESS_OUTCOME[$outcome]) . '.', 'warning');
        } else if (($_POST['grant_auth'] ?? '') === '1') {
            // Competent, and asked to authorise from it: grant the permission in
            // the same step, with its basis set to this assessment. No second
            // trip to the grant form, and the authorisation points back at the
            // evidence an assessor will ask for.
            auth_grant([
                'inspector_id' => $ins,
                'level'        => (string)($_POST['level'] ?? 'INSPECTOR'),
                'scope_kind'   => (string)($_POST['scope_kind'] ?? 'ANY'),
                'scope_value'  => (string)($_POST['scope_value'] ?? ''),
                'valid_from'   => $on,
                'notes'        => 'Granted from a witnessed assessment.',
                'basis'        => 'WITNESS',
                'basis_ref'    => 'Witnessed assessment on ' . $on . ' by ' . user_name(current_user())
                                  . ($waId ? ' (#' . $waId . ')' : ''),
                'review_months'        => (int)($_POST['review_months'] ?? 12),
                'witness_every_months' => (int)($_POST['witness_every_months'] ?? 12),
            ]);
            flash('Assessment recorded, and an authorisation granted from it.');
        } else flash('Assessment recorded.');
        redirect('/competence?i=' . $ins);
    }
    redirect('/competence');
    return true;
}

function competence_can_authorise() { return is_admin_level() || is_master(); }

// ============================================================================
//  How somebody BECOMES authorised, and how the authorisation stays true
//
//  The matrix already said who may do what, and the allocation gate already
//  read it. What it could not answer was the two questions an assessor asks
//  straight afterwards:
//
//    "On what basis was this authorisation granted?"  and
//    "When was it last reviewed?"
//
//  An authorisation typed into a screen by whoever had the password, with no
//  basis and no review date, is an assertion rather than a record — and §6.1.8
//  asks for the competence of personnel to be MONITORED, which is a verb about
//  the future, not a box ticked once.
//
//  So an authorisation now carries:
//
//   - **the basis it rests on** — training, a witnessed job, an examination, or
//     documented experience — with a reference to the evidence;
//   - **a review cycle**, so it comes round again rather than standing for ever;
//   - **a witnessing interval**, because watching somebody work is the only
//     evidence of competence that is not paperwork about paperwork.
//
//  And the report is checked: §6.1 is not satisfied by a competent person being
//  ON the job if somebody else signed the result. Checked at finalisation, and
//  DELIBERATELY as a warning rather than a block — unlike calibration, where an
//  uncalibrated instrument makes the measurement void, a signature by an
//  unauthorised person is a management failure that must be recorded and put
//  right, not a reason to withhold a report a client is waiting for.
// ============================================================================

const AUTH_BASES = [
    ''            => '— not recorded —',
    'TRAINING'    => 'Training completed and assessed',
    'WITNESS'     => 'Witnessed on the job by an assessor',
    'EXAM'        => 'Examination or formal certification',
    'EXPERIENCE'  => 'Documented experience, reviewed and accepted',
    'GRANDFATHER' => 'Carried over when the register was set up',
];

function competence_cycle_migrate() {
    static $done = false; if ($done) return; $done = true;
    ensure_column('authorisations', 'basis', "VARCHAR(20) DEFAULT ''");
    ensure_column('authorisations', 'basis_ref', "VARCHAR(200) DEFAULT ''");
    ensure_column('authorisations', 'review_months', 'INT DEFAULT 0');
    ensure_column('authorisations', 'last_review_on', "VARCHAR(20) DEFAULT ''");
    ensure_column('authorisations', 'last_review_by', "VARCHAR(150) DEFAULT ''");
    ensure_column('authorisations', 'witness_every_months', 'INT DEFAULT 0');
}

// When this authorisation next has to be looked at. Counts from the last
// review if there has been one, otherwise from the day it was granted —
// otherwise an authorisation nobody ever reviewed is never due.
function auth_review_due($a) {
    $m = (int)($a['review_months'] ?? 0);
    if ($m <= 0) return '';
    $from = trim((string)($a['last_review_on'] ?? '')) ?: trim((string)($a['valid_from'] ?? ''));
    if ($from === '') return '';
    return date('Y-m-d', strtotime($from . ' +' . $m . ' months'));
}

function auth_review_overdue($a, $today = null) {
    $due = auth_review_due($a);
    return $due !== '' && $due < ($today ?: date('Y-m-d'));
}

// Everything that has fallen out of date across the whole register: expired
// authorisations, reviews that have come round, and witnessing that is due.
function competence_due($today = null) {
    competence_cycle_migrate();
    $today = $today ?: date('Y-m-d');
    $out = ['expired' => [], 'review' => [], 'witness' => [], 'no_basis' => []];
    try {
        $rows = ops_all("SELECT a.*, i.name inspector_name, i.email
                         FROM authorisations a LEFT JOIN inspectors i ON i.id = a.inspector_id
                         WHERE a.status='ACTIVE'");
    } catch (Throwable $e) { return $out; }
    foreach ($rows as $a) {
        if (trim((string)$a['valid_to']) !== '' && $a['valid_to'] < $today) { $out['expired'][] = $a; continue; }
        if (auth_review_overdue($a, $today)) $out['review'][] = $a;
        // An authorisation with no basis is not an emergency, but it is the
        // question an assessor asks first, so it is counted.
        if (trim((string)($a['basis'] ?? '')) === '') $out['no_basis'][] = $a;
        $wm = (int)($a['witness_every_months'] ?? 0);
        if ($wm > 0 && function_exists('witness_latest')) {
            $last = witness_latest((int)$a['inspector_id']);
            $from = $last ? (string)$last['assessed_on'] : (string)$a['valid_from'];
            if ($from !== '' && date('Y-m-d', strtotime($from . " +$wm months")) < $today) $out['witness'][] = $a;
        }
    }
    return $out;
}

function competence_due_counts($today = null) {
    $d = competence_due($today);
    return ['expired' => count($d['expired']), 'review' => count($d['review']),
            'witness' => count($d['witness']), 'no_basis' => count($d['no_basis'])];
}

// ---- The report signatory --------------------------------------------------
// §6.1: a competent person being on the job is not the same as a competent
// person signing the result. Returns '' when there is nothing to say.
function report_signatory_warning($doc) {
    if (!function_exists('auth_block') || !function_exists('auth_enforced') || !auth_enforced()) return '';
    $insp = (int)($doc['inspector_id'] ?? 0);
    if (!$insp) return '';
    $on = (string)($doc['inspection_date'] ?? '') ?: (string)($doc['issue_date'] ?? '') ?: date('Y-m-d');
    $why = auth_block($insp, (string)($doc['type_code'] ?? ''), 0, (int)($doc['client_id'] ?? 0), $on);
    if ($why === '') return '';
    return 'The engineer named on this ' . (function_exists('Tl') ? Tl('report') : 'report')
         . ' was not authorised for this work on ' . $on . '. ' . $why;
}

// ============================================================================
//  Slice P1 — the Credential Vault
//
//  Certificates, qualifications, authorisations, witness records and identity
//  documents already exist, on separate screens. This adds ONE per-person view
//  that reads them together and names each credential's status in a single
//  vocabulary — Valid / Expiring soon / Expired / Under verification / Rejected /
//  Superseded / Missing. Read-first; it composes the engines above and the
//  identity summary, and adds no data of its own beyond the optional verify state.
//
//  Non-destructive: the status derivation is a pure function; a blank
//  verify_status reproduces the old date-only behaviour exactly; the allocation
//  gate (competence_lapsed / auth_block) is untouched — the vault only reads it.
// ============================================================================

const CREDENTIAL_STATUS = [
    'VALID'              => 'Valid',
    'EXPIRING'           => 'Expiring soon',
    'EXPIRED'            => 'Expired',
    'UNDER_VERIFICATION' => 'Under verification',
    'REJECTED'           => 'Rejected',
    'SUPERSEDED'         => 'Superseded',
    'MISSING'            => 'Missing',
];

// The verification verdicts a manager may set on a held credential. '' = the
// default (not verified), which classifies by date exactly as before.
const CREDENTIAL_VERIFY_STATES = [
    ''                   => 'Not verified',
    'UNDER_VERIFICATION' => 'Under verification',
    'VERIFIED'           => 'Verified',
    'REJECTED'           => 'Rejected',
    'SUPERSEDED'         => 'Superseded',
];

// The "expiring soon" window, shared with the training watch so the two agree.
function credential_window_days() { return 45; }

// Derive one credential's status from its stored fields. Pure/read-only.
// A REJECTED/SUPERSEDED verdict stands whatever the dates say; otherwise the
// date decides, with UNDER_VERIFICATION surfaced when nothing worse applies.
function credential_status($cert, $onDate = null) {
    $onDate = $onDate ?: date('Y-m-d');
    $vs = strtoupper(trim((string)($cert['verify_status'] ?? '')));
    if ($vs === 'REJECTED')   return 'REJECTED';
    if ($vs === 'SUPERSEDED') return 'SUPERSEDED';
    $vt = substr(trim((string)($cert['valid_to'] ?? '')), 0, 10);
    if ($vt !== '') {
        if ($vt < $onDate) return 'EXPIRED';
        $soon = date('Y-m-d', strtotime($onDate . ' +' . credential_window_days() . ' days'));
        if ($vt <= $soon) return ($vs === 'UNDER_VERIFICATION') ? 'UNDER_VERIFICATION' : 'EXPIRING';
    }
    return ($vs === 'UNDER_VERIFICATION') ? 'UNDER_VERIFICATION' : 'VALID';
}

// Label + pill class for a credential status.
function credential_status_pill($status) {
    return [
        'VALID'              => ['✓ Valid', 'p-ok'],
        'EXPIRING'           => ['⏳ Expiring soon', 'p-warn'],
        'EXPIRED'            => ['⛔ Expired', 'p-bad'],
        'UNDER_VERIFICATION' => ['🔍 Under verification', 'p-warn'],
        'REJECTED'           => ['✗ Rejected', 'p-bad'],
        'SUPERSEDED'         => ['↩ Superseded', 'p-mut'],
        'MISSING'            => ['— Missing', 'p-mut'],
    ][$status] ?? ['—', 'p-mut'];
}

// ---- Client competency requirement sets (Option A: reuse customforms) -------
// The requirement sets live in a normal customforms register, so an admin builds
// and edits them with the no-code builder. A setting points the vault at that
// register; the vault reads its records. No new table.
function credential_req_form_slug() { return function_exists('setting_get') ? setting_get('competency_req_form_slug', '') : ''; }

function credential_req_form() {
    $slug = credential_req_form_slug();
    if ($slug === '' || !function_exists('cform_by_slug')) return null;
    return cform_by_slug($slug);
}

// Manager one-click: create the register (reusing customforms) and remember it.
// Field definition stays admin-driven — exactly how customforms works.
function credential_req_form_ensure() {
    if (!function_exists('cforms_migrate')) return null;
    cforms_migrate();
    if ($f = credential_req_form()) return $f;
    $slug = function_exists('cform_make_slug') ? cform_make_slug('Competency requirement set') : 'f_competency_req';
    db()->prepare("INSERT INTO custom_forms (name,slug,nav_group,icon,help,active,sort_order,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute(['Competency requirement set', $slug, 'Quality & accreditation', '🎓',
                   'Per-client / per-scope required credentials, surfaced in the Credential Vault.', 1, 50,
                   function_exists('user_name') ? user_name(current_user()) : '', date('c')]);
    if (function_exists('setting_set')) setting_set('competency_req_form_slug', $slug);
    return credential_req_form();
}

function credential_req_sets() {
    $f = credential_req_form();
    if (!$f) return [];
    try { return ops_all("SELECT * FROM custom_records WHERE form_id=? ORDER BY title", [(int)$f['id']]) ?: []; }
    catch (Throwable $e) { return []; }
}

// ---- The vault panel --------------------------------------------------------
// Rendered by the Entity-360 'credential' tab and reusable anywhere. INSPECTOR
// only for now (id = inspectors.id). $opts['editable'] shows the manager verify
// control; identity is always masked unless the viewer holds person.iddoc.view.
function credential_vault_render($kind, $id, array $opts = []) {
    $kind = strtoupper((string)$kind); $id = (int)$id;
    if ($kind !== 'INSPECTOR' || !$id) return;
    $ins = ops_one("SELECT * FROM inspectors WHERE id=?", [$id]);
    if (!$ins) return;
    $editable = !empty($opts['editable']);
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $today = date('Y-m-d');

    echo '<div class="panel"><h3 class="tab-sub" style="margin-top:0">🎓 Credential vault'
       . ' <span class="muted" style="font-weight:400;font-size:12px">— what this ' . $e(function_exists('Tl') ? Tl('engineer') : 'engineer')
       . ' holds, and whether it stands</span></h3>';

    // Eligibility headline (reuses the allocation-gate mirror).
    if (function_exists('inspector_eligibility') && function_exists('inspector_eligibility_pill')) {
        $elig = inspector_eligibility($id, ['on_date' => $today]);
        [$elLbl, $elCls] = inspector_eligibility_pill($elig['status']);
        echo '<p style="margin:2px 0 12px"><span class="pill ' . $elCls . '">' . $e($elLbl) . '</span>'
           . ' <span class="muted" style="font-size:12px">today’s allocation verdict</span></p>';
    }

    // Certificates & tickets, with derived status.
    $certs = [];
    try { $certs = ops_all("SELECT * FROM inspector_certs WHERE inspector_id=? ORDER BY valid_to", [$id]) ?: []; } catch (Throwable $ex) {}
    echo '<div class="tab-sub" style="font-weight:600;margin:8px 0 4px">Certificates &amp; tickets</div>';
    if (!$certs) echo '<p class="muted">No certificates on record.</p>';
    else {
        echo '<table class="grid"><thead><tr><th>Certificate</th><th>Number</th><th>Valid to</th><th>Status</th><th>Required</th>'
           . ($editable ? '<th>Verification</th>' : '') . '</tr></thead><tbody>';
        foreach ($certs as $c) {
            $st = credential_status($c, $today); [$lbl, $cls] = credential_status_pill($st);
            echo '<tr><td>' . $e($c['name']) . '</td><td>' . $e($c['number'] ?: '—') . '</td><td>' . $e($c['valid_to'] ?: '—')
               . '</td><td><span class="pill ' . $cls . '">' . $e($lbl) . '</span></td>'
               . '<td>' . (!empty($c['is_mandatory']) ? 'Required' : '—') . '</td>';
            if ($editable) {
                echo '<td><form method="post" action="/cert-verify" style="display:flex;gap:4px;align-items:center;margin:0">'
                   . '<input type="hidden" name="id" value="' . (int)$c['id'] . '">'
                   . '<select class="form-control" style="width:150px" name="verify_status">';
                foreach (CREDENTIAL_VERIFY_STATES as $k => $v) {
                    $sel = (strtoupper((string)($c['verify_status'] ?? '')) === $k) ? ' selected' : '';
                    echo '<option value="' . $e($k) . '"' . $sel . '>' . $e($v) . '</option>';
                }
                echo '</select><button class="btn small secondary" type="submit">Save</button></form></td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    // Qualifications the person holds (library-linked), if the spine is present.
    try {
        $quals = ops_all("SELECT q.name, q.scheme, q.issuing_body FROM inspector_certs c
                          JOIN qualifications q ON q.id = c.qualification_id
                          WHERE c.inspector_id=? AND c.qualification_id IS NOT NULL ORDER BY q.name", [$id]) ?: [];
        if ($quals) {
            echo '<div class="tab-sub" style="font-weight:600;margin:12px 0 4px">Recognised qualifications</div><ul style="margin:0">';
            foreach ($quals as $q) echo '<li>' . $e($q['name']) . ($q['scheme'] ? ' <span class="muted">(' . $e($q['scheme']) . ')</span>' : '') . '</li>';
            echo '</ul>';
        }
    } catch (Throwable $ex) {}

    // Authorisations — what the body permits.
    if (function_exists('authorisations_for') && function_exists('auth_live') && function_exists('auth_scope_label')) {
        $auths = authorisations_for($id, true);
        echo '<div class="tab-sub" style="font-weight:600;margin:12px 0 4px">Authorisations</div>';
        if (!$auths) echo '<p class="muted">None recorded.</p>';
        else {
            echo '<ul style="margin:0">';
            foreach ($auths as $a) {
                $live = auth_live($a, $today);
                $cls = $live ? 'p-ok' : (($a['status'] ?? '') === 'ACTIVE' ? 'p-warn' : 'p-mut');
                $stLbl = $live ? 'Live' : (AUTH_STATUS[$a['status']] ?? $a['status']);
                echo '<li><span class="pill ' . $cls . '">' . $e($stLbl) . '</span> ' . $e(auth_scope_label($a))
                   . ($a['valid_to'] ? ' <span class="muted">to ' . $e($a['valid_to']) . '</span>' : '') . '</li>';
            }
            echo '</ul>';
        }
    }

    // Witness assessment — last watched doing the job.
    if (function_exists('witness_latest') && function_exists('witness_overdue')) {
        $w = witness_latest($id); $overdue = witness_overdue($id);
        echo '<div class="tab-sub" style="font-weight:600;margin:12px 0 4px">Witnessed assessment</div>';
        if (!$w) echo '<p class="muted">Never witnessed. <span class="pill p-warn">Due</span></p>';
        else echo '<p>Last on ' . $e($w['assessed_on'] ?: '—') . ' — ' . $e(WITNESS_OUTCOME[$w['outcome']] ?? $w['outcome'])
                . ($overdue ? ' <span class="pill p-warn">Re-assessment due</span>' : '') . '</p>';
    }

    // Identity documents — masked unless the viewer holds the DPDP right.
    if (function_exists('person_docs_summary')) {
        echo '<div class="tab-sub" style="font-weight:600;margin:12px 0 4px">Identity documents</div>';
        if (function_exists('iddoc_can_view') && iddoc_can_view()) {
            try {
                $s = person_docs_summary($id, 'INSPECTOR');
                $cls = $s['complete'] ? 'p-ok' : 'p-warn';
                echo '<p><span class="pill ' . $cls . '">' . (int)$s['have'] . ' / ' . (int)$s['total'] . ' held</span>'
                   . ($s['missing'] ? ' <span class="muted">missing: ' . $e(implode(', ', $s['missing'])) . '</span>' : '') . '</p>';
            } catch (Throwable $ex) { echo '<p class="muted">Not available.</p>'; }
        } else {
            echo '<p class="muted">🔒 Restricted — needs the identity-documents permission.</p>';
        }
    }

    // Client competency requirement sets (from the customforms register, if wired).
    $sets = credential_req_sets();
    echo '<div class="tab-sub" style="font-weight:600;margin:12px 0 4px">Client competency requirements</div>';
    if ($sets) {
        echo '<ul style="margin:0">';
        foreach ($sets as $r)
            echo '<li><a href="/cform-view?id=' . (int)$r['id'] . '">' . $e($r['title'] ?: ('Set #' . $r['id'])) . '</a></li>';
        echo '</ul><p class="muted" style="font-size:12px;margin-top:4px">Requirement sets are defined in the '
           . '“Competency requirement set” register. Per-person automated matching lands in the next slice.</p>';
    } else {
        echo '<p class="muted">No requirement register configured yet.'
           . ($editable ? ' <form method="post" action="/credential-req-init" style="display:inline;margin:0">'
                        . '<button class="btn small secondary" type="submit">Create the requirement register</button></form>' : '')
           . '</p>';
    }

    echo '</div>';
}
