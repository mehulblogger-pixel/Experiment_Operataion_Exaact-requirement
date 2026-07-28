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
    return can('jobs.edit') && (is_admin_level() || is_master());
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
    'JUNIOR'     => 'Junior inspector',
    'INSPECTOR'  => 'Inspector',
    'SENIOR'     => 'Senior inspector',
    'LEAD'       => 'Lead inspector',
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
    return $who . ' is not authorised for this work' . $why
         . ' ISO/IEC 17020 §6.1: only authorised personnel may perform an inspection.';
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

// ---- Screens ---------------------------------------------------------------
function ops_competence($route, $method) {
    ops_require(can('mod.users.view') || is_admin_level() || is_master_of('users'),
                'Only a manager can open the competence register.');

    if ($route === 'competence') {
        view('ops/competence', ['matrix' => competence_matrix(), 'ready' => competence_readiness(),
                                'canEdit' => competence_can_authorise()]);
        return true;
    }
    ops_require(competence_can_authorise(), 'Only a manager can grant or withdraw an authorisation.');

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
        db()->prepare("INSERT INTO authorisations (inspector_id,level,scope_kind,scope_value,valid_from,valid_to,status,granted_by,granted_at,notes)
                       VALUES (?,?,?,?,?,?, 'ACTIVE', ?,?,?)")
            ->execute([$ins, (string)($_POST['level'] ?? 'INSPECTOR'), $kind,
                       $kind === 'ANY' ? '' : (string)($_POST['scope_value'] ?? ''),
                       (string)($_POST['valid_from'] ?? date('Y-m-d')), (string)($_POST['valid_to'] ?? ''),
                       user_name(current_user()), date('c'), substr(trim((string)($_POST['notes'] ?? '')), 0, 500)]);
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
        db()->prepare("INSERT INTO witness_assessments (inspector_id,job_id,assessed_on,assessor,location,scores,outcome,remarks,next_due,created_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$ins, ($_POST['job_id'] ?? '') !== '' ? (int)$_POST['job_id'] : null,
                       (string)($_POST['assessed_on'] ?? date('Y-m-d')), user_name(current_user()),
                       substr(trim((string)($_POST['location'] ?? '')), 0, 200),
                       json_encode($scores), $outcome,
                       substr(trim((string)($_POST['remarks'] ?? '')), 0, 1000),
                       (string)($_POST['next_due'] ?? ''), date('c')]);
        // A failed assessment suspends what it was testing — that is the point
        // of watching somebody work rather than filing a form about it.
        if ($outcome !== 'PASS') {
            foreach (authorisations_for($ins) as $a)
                db()->prepare("UPDATE authorisations SET status='SUSPENDED', status_reason=?, changed_by=?, changed_at=? WHERE id=?")
                    ->execute(['Suspended by witnessed assessment on ' . ($_POST['assessed_on'] ?? date('Y-m-d'))
                               . ': ' . WITNESS_OUTCOME[$outcome] . '.', user_name(current_user()), date('c'), $a['id']]);
            flash('Assessment recorded. Their authorisations have been suspended — ' . strtolower(WITNESS_OUTCOME[$outcome]) . '.', 'warning');
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
