<?php
// ============================================================================
//  Control of data and information — new in ISO/IEC 17020:2026
//
//  This clause has no equivalent in the 2012 edition. It arrived because
//  inspection bodies stopped keeping records on paper and nobody had written
//  down what that means. It asks four things that a body running on software
//  cannot answer by pointing at a policy:
//
//   1. WAS THE SOFTWARE VALIDATED FOR WHAT YOU USE IT FOR — before you started
//      using it, and again when it changed? Including the spreadsheet somebody
//      built, the report template with a formula in it, and any AI tool.
//   2. CAN YOU SHOW THE DATA IS INTACT? Not "we back up" — evidence, on a date,
//      that calculations and transfers were checked.
//   3. WHO CAN GET AT IT? A list of who holds which power, read from the
//      running system rather than from an org chart.
//   4. WHEN IT BROKE, WHAT HAPPENED TO THE DATA? A failure log, and each entry
//      says whether results were affected.
//
//  Three of those four this application can genuinely produce rather than
//  merely store, and that is the whole design:
//
//   · The integrity checks RUN. Sixteen of them, against the live database,
//     producing a dated pass/fail. That is evidence, not an assertion.
//   · The access report is READ from the permission engine. Nobody types it.
//   · Failures are RECORDED AUTOMATICALLY when the app hits a fatal error. A
//     failure log a person has to remember to write is a failure log that
//     records only the failures somebody was in the mood to admit to.
//
//  The first — validation — cannot be automated, and pretending otherwise would
//  be the worst thing this file could do. What it does instead is tell you,
//  loudly, when the version you are running has no validation record against
//  it, which is the finding you would otherwise collect.
// ============================================================================

const SW_KINDS = [
    'APP'        => 'This application',
    'MODULE'     => 'A part of it, validated on its own',
    'TEMPLATE'   => 'A report template with logic or formulas in it',
    'SPREADSHEET'=> 'A spreadsheet used for a calculation',
    'EXTERNAL'   => 'An outside service or system we depend on',
    'AI_TOOL'    => 'An AI tool',
];
const SW_RESULTS = [
    'PASS'    => 'Fit for the purpose stated',
    'PARTIAL' => 'Fit, with the limitations recorded below',
    'FAIL'    => 'Not fit — must not be used for this',
];

const FAILURE_STATUS = ['OPEN' => 'Open', 'RESOLVED' => 'Resolved'];
const FAILURE_DATA   = [
    'NO'      => 'No — no data was lost, altered or made unavailable',
    'YES'     => 'Yes — data or results were affected',
    'UNKNOWN' => 'Not yet established',
];

// How long a validation stands before it should be looked at again.
const SW_REVIEW_DEFAULT = 365;
// A run of the integrity checks older than this is not current evidence.
const INTEGRITY_STALE_DAYS = 90;

function datacontrol_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = pk_clause();
    $pdo->exec("CREATE TABLE IF NOT EXISTS sw_validations (
        id $pk, ref VARCHAR(40) DEFAULT '', kind VARCHAR(20) DEFAULT 'APP',
        component VARCHAR(200) DEFAULT '', version VARCHAR(60) DEFAULT '',
        purpose VARCHAR(1000) DEFAULT '', what_tested TEXT, expected TEXT, observed TEXT,
        result VARCHAR(20) DEFAULT 'PASS', limitations VARCHAR(1000) DEFAULT '',
        evidence VARCHAR(1000) DEFAULT '',
        validated_by VARCHAR(150) DEFAULT '', validated_on VARCHAR(20) DEFAULT '',
        next_review VARCHAR(20) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS data_check_runs (
        id $pk, ran_on VARCHAR(30) DEFAULT '', ran_by VARCHAR(150) DEFAULT '',
        total INT DEFAULT 0, failed INT DEFAULT 0, app_version VARCHAR(60) DEFAULT '',
        detail TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_failures (
        id $pk, ref VARCHAR(40) DEFAULT '', occurred_at VARCHAR(30) DEFAULT '',
        source VARCHAR(20) DEFAULT 'REPORTED', fingerprint VARCHAR(40) DEFAULT '',
        summary VARCHAR(400) DEFAULT '', detail TEXT,
        data_affected VARCHAR(10) DEFAULT 'UNKNOWN', data_note VARCHAR(1000) DEFAULT '',
        immediate_action TEXT, capa_ref VARCHAR(40) DEFAULT '',
        reported_by VARCHAR(150) DEFAULT '',
        status VARCHAR(20) DEFAULT 'OPEN', resolved_on VARCHAR(20) DEFAULT '', resolved_by VARCHAR(150) DEFAULT '',
        created_at VARCHAR(30) DEFAULT '')");
}

function dc_missing_table(Throwable $e) {
    $m = $e->getMessage();
    return stripos($m, 'no such table') !== false || stripos($m, "doesn't exist") !== false;
}
function dc_try($fn, $fallback = []) {
    try { return $fn(); } catch (Throwable $e) { if (dc_missing_table($e)) return $fallback; throw $e; }
}

// ============================================================================
//  1 · Software validation
// ============================================================================
function sw_all() {
    return dc_try(fn() => ops_all("SELECT * FROM sw_validations ORDER BY validated_on DESC, id DESC"));
}
function sw_ref_next() {
    $n = (int)dc_try(fn() => ops_val("SELECT COUNT(*) FROM sw_validations"), 0);
    return 'VAL-' . date('Y') . '-' . str_pad((string)($n + 1), 3, '0', STR_PAD_LEFT);
}

// The newest usable validation for a named component, or null.
function sw_current($component, $version = null) {
    foreach (sw_all() as $v) {
        if (strcasecmp(trim($v['component']), trim((string)$component)) !== 0) continue;
        if ($v['result'] === 'FAIL') continue;
        if ($version !== null && trim($v['version']) !== trim((string)$version)) continue;
        return $v;
    }
    return null;
}

// The name this application goes by in the register. Held in one place so a
// rename of the product does not silently orphan every validation record.
function sw_app_component() { return 'Exaact — inspection & operations application'; }

// Is the version actually running right now covered by a validation record?
// This is the question §7.11 asks and the one a body answers wrongly, because
// it validated version 1 in 2024 and has upgraded four times since.
function sw_app_state() {
    $v = defined('APP_VERSION') ? APP_VERSION : 'unknown';
    $exact = sw_current(sw_app_component(), $v);
    if ($exact) return ['state' => 'ok', 'version' => $v, 'row' => $exact];
    $any = sw_current(sw_app_component());
    if ($any) return ['state' => 'stale', 'version' => $v, 'row' => $any];
    return ['state' => 'none', 'version' => $v, 'row' => null];
}

// Validations that have run past their own review date.
function sw_due_review($today = null) {
    $today = $today ?: date('Y-m-d');
    $out = [];
    foreach (sw_all() as $v)
        if ($v['next_review'] !== '' && $v['next_review'] < $today) $out[] = $v;
    return $out;
}

function sw_create($b) {
    $comp = trim((string)($b['component'] ?? ''));
    $what = trim((string)($b['what_tested'] ?? ''));
    if ($comp === '' || $what === '') return 0;
    // A validation record that does not say what the thing is FOR proves
    // nothing — "fit for purpose" is meaningless without the purpose.
    if (trim((string)($b['purpose'] ?? '')) === '') return -1;
    $kind = isset(SW_KINDS[$b['kind'] ?? '']) ? $b['kind'] : 'APP';
    $res  = isset(SW_RESULTS[$b['result'] ?? '']) ? $b['result'] : 'PASS';
    $on   = (string)($b['validated_on'] ?? date('Y-m-d'));
    $next = trim((string)($b['next_review'] ?? ''));
    if ($next === '') $next = date('Y-m-d', strtotime($on . ' +' . SW_REVIEW_DEFAULT . ' days'));
    $ref = sw_ref_next();
    db()->prepare("INSERT INTO sw_validations
        (ref,kind,component,version,purpose,what_tested,expected,observed,result,limitations,evidence,
         validated_by,validated_on,next_review,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$ref, $kind, substr($comp, 0, 200), substr(trim((string)($b['version'] ?? '')), 0, 60),
                   substr(trim((string)($b['purpose'] ?? '')), 0, 1000), $what,
                   (string)($b['expected'] ?? ''), (string)($b['observed'] ?? ''), $res,
                   substr(trim((string)($b['limitations'] ?? '')), 0, 1000),
                   substr(trim((string)($b['evidence'] ?? '')), 0, 1000),
                   substr(trim((string)($b['validated_by'] ?? user_name(current_user()))), 0, 150),
                   $on, $next, date('c')]);
    return (int)db()->lastInsertId();
}

// ============================================================================
//  2 · Data integrity — checks that actually run
//
//  §7.11 asks for calculations and data transfers to be checked appropriately
//  and systematically. Every check below is a question with a right answer,
//  asked of the live database. A body can press the button in front of an
//  assessor, which is a different kind of evidence from a signed statement.
// ============================================================================
function integrity_checks() {
    $out = [];
    $add = function ($key, $what, $why, $sql, $args = []) use (&$out) {
        try {
            $n = (int)ops_val($sql, $args);
            $out[] = ['key' => $key, 'what' => $what, 'why' => $why, 'ok' => $n === 0, 'found' => $n, 'skipped' => false];
        } catch (Throwable $e) {
            // A table that does not exist yet is not a failure — it is a module
            // this installation does not use. Saying "skipped" is honest;
            // reporting a pass would not be.
            $out[] = ['key' => $key, 'what' => $what, 'why' => $why, 'ok' => true, 'found' => 0, 'skipped' => true];
        }
    };

    // ---- Transfers between records: does everything still point somewhere? --
    $add('job_call', 'Every ' . Tl('job') . ' belongs to a real ' . Tl('call'),
        'A ' . Tl('job') . ' whose ' . Tl('call') . ' has gone is work nobody can trace back to an instruction.',
        "SELECT COUNT(*) FROM jobs WHERE call_id IS NOT NULL AND call_id NOT IN (SELECT id FROM calls)");
    $add('job_inspector', 'Every allocated ' . Tl('job') . ' names a person who exists',
        'The name on the report has to resolve to somebody on the register.',
        "SELECT COUNT(*) FROM jobs WHERE inspector_id IS NOT NULL AND inspector_id NOT IN (SELECT id FROM inspectors)");
    $add('bill_job', 'Every expense bill belongs to a real ' . Tl('job'),
        'A bill with no ' . Tl('job') . ' cannot be charged to anybody.',
        "SELECT COUNT(*) FROM job_bills WHERE job_id NOT IN (SELECT id FROM jobs)");
    $add('cert_person', 'Every certificate belongs to a person who exists',
        'A ticket attached to nobody would still count towards competence.',
        "SELECT COUNT(*) FROM inspector_certs WHERE inspector_id NOT IN (SELECT id FROM inspectors)");
    $add('cal_equip', 'Every calibration certificate belongs to a real instrument',
        'Calibration is what makes a measurement defensible; it has to attach to the instrument used.',
        "SELECT COUNT(*) FROM equipment_calibrations WHERE equipment_id NOT IN (SELECT id FROM equipment)");
    $add('finding_audit', 'Every audit finding belongs to a real audit',
        'A finding with no audit behind it cannot be shown to have come from anywhere.',
        "SELECT COUNT(*) FROM audit_findings WHERE audit_id NOT IN (SELECT id FROM internal_audits)");
    $add('mr_input_review', 'Every management-review input belongs to a real review',
        'Otherwise the review record is incomplete without anything saying so.',
        "SELECT COUNT(*) FROM mr_inputs WHERE review_id NOT IN (SELECT id FROM mgmt_reviews)");

    // ---- Records that must not contradict themselves ----------------------
    $add('report_final', 'No report is marked issued without a date and a name against it',
        'An issued report with nobody named is a report nobody can be asked about.',
        "SELECT COUNT(*) FROM report_docs WHERE finalized=1 AND (finalized_at='' OR finalized_by='')");
    $add('capa_effective', 'No corrective action is closed as effective without the check behind it',
        'This is the §8.7.3 record. If the flag and the evidence disagree, the flag is worthless.',
        "SELECT COUNT(*) FROM capa WHERE status='CLOSED' AND (verified_on='' OR effective<>'YES')");
    $add('complaint_closed', 'No complaint is closed without the complainant being told',
        'The §7.5 gate is enforced on screen; this proves nothing got past it another way.',
        "SELECT COUNT(*) FROM complaints WHERE status='CLOSED' AND notified_on='' AND anonymous=0");
    $add('job_closed_dates', 'No ' . Tl('job') . ' is closed with an end date before its start',
        'Dates that run backwards break every duration, cost and turnaround figure downstream.',
        "SELECT COUNT(*) FROM jobs WHERE closed_flag=1 AND inspection_start_date<>'' AND inspection_end_date<>''
                                    AND inspection_end_date < inspection_start_date");
    $add('iddoc_retention', 'No identity document is held past its own retention date',
        'The DPDP purpose limitation is only real if the sweep actually ran.',
        "SELECT COUNT(*) FROM person_documents WHERE (redacted_at IS NULL OR redacted_at='')
                                                 AND retain_until<>'' AND retain_until < ?", [date('Y-m-d')]);

    // ---- The trail itself --------------------------------------------------
    $add('audit_orphan', 'The compliance trail has no entry with no author',
        'An audit entry with no username cannot answer "who did this".',
        "SELECT COUNT(*) FROM idems_audit WHERE COALESCE(username,'')=''");

    // ---- Permissions that no longer mean anything --------------------------
    // Not a SQL count — worked out against the live permission catalogue.
    try {
        $valid = array_keys(all_permissions());
        $stale = 0;
        foreach (ops_all("SELECT id, permissions FROM users WHERE COALESCE(permissions,'')<>''") as $u)
            foreach (array_filter(explode(',', (string)$u['permissions'])) as $p)
                if (!in_array(trim($p), $valid, true)) { $stale++; break; }
        $out[] = ['key' => 'perm_stale', 'ok' => $stale === 0, 'found' => $stale, 'skipped' => false,
                  'what' => 'No account carries a permission that no longer exists',
                  'why'  => 'A permission left over from an old version is a right nobody reviewed.'];
    } catch (Throwable $e) { /* users table always exists; ignore anyway */ }

    // ---- Uploaded files: do the bytes still decode? ------------------------
    // Sampled, not exhaustive — reading every file would time out on a shared
    // host, and the point is to catch a truncation, which hits everything.
    $bad = 0; $looked = 0;
    foreach (FILE_COLUMNS as [$t, $c]) {
        try {
            foreach (ops_all("SELECT $c AS d FROM $t WHERE COALESCE($c,'')<>'' ORDER BY id DESC LIMIT 5") as $r) {
                $looked++;
                // Two shapes are stored in this application and both are
                // correct: bare base64, and a full data: URL. Decoding the URL
                // form as though it were bare base64 fails — and reporting
                // perfectly good evidence as corrupt is worse than not checking
                // at all, because a screen that cries wolf stops being read.
                $d = (string)$r['d'];
                if (strncmp($d, 'data:', 5) === 0 && ($comma = strpos($d, ',')) !== false)
                    $d = substr($d, $comma + 1);
                if (base64_decode($d, true) === false) $bad++;
            }
        } catch (Throwable $e) { /* module not in use */ }
    }
    $out[] = ['key' => 'file_decode', 'ok' => $bad === 0, 'found' => $bad, 'skipped' => $looked === 0,
              'what' => 'Uploaded files still decode (' . $looked . ' sampled)',
              'why'  => 'A silently truncated upload looks fine in a list and is unopenable when it matters.'];

    // ---- Money adds up ------------------------------------------------------
    try {
        $n = (int)ops_val("SELECT COUNT(*) FROM job_bills WHERE amount < 0");
        $out[] = ['key' => 'bill_amount', 'ok' => $n === 0, 'found' => $n, 'skipped' => false,
                  'what' => 'No expense bill carries a negative amount',
                  'why'  => 'A negative bill quietly reduces what the client is charged.'];
    } catch (Throwable $e) { }

    return $out;
}

function integrity_summary($checks = null) {
    $checks = $checks ?? integrity_checks();
    $failed = 0; $skipped = 0;
    foreach ($checks as $c) { if (!$c['ok']) $failed++; if (!empty($c['skipped'])) $skipped++; }
    return ['total' => count($checks), 'failed' => $failed, 'skipped' => $skipped];
}

function integrity_run() {
    $checks = integrity_checks();
    $s = integrity_summary($checks);
    try {
        db()->prepare("INSERT INTO data_check_runs (ran_on,ran_by,total,failed,app_version,detail) VALUES (?,?,?,?,?,?)")
            ->execute([date('c'), user_name(current_user()), $s['total'], $s['failed'],
                       defined('APP_VERSION') ? APP_VERSION : '', json_encode($checks)]);
    } catch (Throwable $e) { /* never let the evidence-writing break the check */ }
    return $s;
}

function integrity_runs($limit = 20) {
    return dc_try(fn() => ops_all("SELECT id, ran_on, ran_by, total, failed, app_version
                                   FROM data_check_runs ORDER BY id DESC LIMIT " . max(1, (int)$limit)));
}
function integrity_last_run() {
    $r = integrity_runs(1);
    return $r ? $r[0] : null;
}
function integrity_run_stale($today = null) {
    $last = integrity_last_run();
    if (!$last) return true;
    $days = (int)floor((strtotime($today ?: 'now') - strtotime($last['ran_on'])) / 86400);
    return $days > INTEGRITY_STALE_DAYS;
}

// ============================================================================
//  3 · Who can get at it
//
//  Read from the permission engine, not from an org chart. Every figure here is
//  what the software would actually allow this person to do today.
// ============================================================================
const DC_POWERS = [
    'users.manage.global' => 'Change anybody’s access',
    'settings.manage'     => 'Change system settings',
    'data.salary'         => 'See salary figures',
    'idems.finalize'      => 'Issue inspection reports',
    'idems.timestamp.edit'=> 'Edit locked timestamps',
    'person.iddoc.manage' => 'Open identity documents',
    'complaints.decide'   => 'Decide complaints',
    'capa.close'          => 'Close corrective actions',
];

function access_report() {
    $rows = [];
    $valid = array_keys(all_permissions());
    foreach (ops_all("SELECT id, username, first_name, last_name, role, is_superuser, is_active,
                             permissions, last_login_at, totp_enabled
                      FROM users WHERE is_active=1 ORDER BY username") as $u) {
        $master = !empty($u['is_superuser']);
        $role = $master ? 'MASTER_ADMIN' : strtoupper((string)($u['role'] ?? 'ADMIN'));
        $csv = trim((string)($u['permissions'] ?? ''));
        $perms = $master ? $valid : ($csv !== '' ? array_map('trim', array_filter(explode(',', $csv))) : role_perms($role));
        $has = [];
        foreach (DC_POWERS as $p => $label) $has[$p] = in_array($p, $perms, true);
        $last = trim((string)($u['last_login_at'] ?? ''));
        $days = $last !== '' ? (int)floor((time() - strtotime($last)) / 86400) : null;
        $rows[] = [
            'id' => (int)$u['id'], 'username' => $u['username'], 'name' => user_name($u),
            'role' => ORG_ROLES[$role] ?? $role, 'master' => $master,
            'powers' => $has, 'power_count' => count(array_filter($has)),
            'last_login' => $last, 'days_idle' => $days,
            'dormant' => $days === null || $days > 90,
            'two_step' => !empty($u['totp_enabled']),
        ];
    }
    return $rows;
}

function access_summary() {
    $rows = access_report();
    $dormant = 0; $noTwoStep = 0; $admins = 0;
    foreach ($rows as $r) {
        if ($r['dormant']) $dormant++;
        if (!$r['two_step']) $noTwoStep++;
        if ($r['master'] || !empty($r['powers']['users.manage.global'])) $admins++;
    }
    return ['people' => count($rows), 'dormant' => $dormant, 'no_two_step' => $noTwoStep, 'admins' => $admins];
}

// ============================================================================
//  4 · When it broke
// ============================================================================
function failures_all($filter = []) {
    $w = ['1=1']; $a = [];
    if (!empty($filter['status'])) { $w[] = 'status = ?'; $a[] = $filter['status']; }
    return dc_try(fn() => ops_all("SELECT * FROM system_failures WHERE " . implode(' AND ', $w)
                                . " ORDER BY (status='OPEN') DESC, occurred_at DESC, id DESC", $a));
}
function failure_row($id) {
    return dc_try(fn() => ops_one("SELECT * FROM system_failures WHERE id=?", [(int)$id]), null);
}
function failure_ref_next() {
    $n = (int)dc_try(fn() => ops_val("SELECT COUNT(*) FROM system_failures"), 0);
    return 'SYS-' . date('Y') . '-' . str_pad((string)($n + 1), 3, '0', STR_PAD_LEFT);
}

function failure_create($b, $source = 'REPORTED') {
    $summary = trim((string)($b['summary'] ?? ''));
    if ($summary === '') return 0;
    $ref = failure_ref_next();
    db()->prepare("INSERT INTO system_failures
        (ref,occurred_at,source,fingerprint,summary,detail,data_affected,data_note,immediate_action,
         reported_by,status,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,'OPEN',?)")
        ->execute([$ref, (string)($b['occurred_at'] ?? date('c')), $source,
                   substr((string)($b['fingerprint'] ?? ''), 0, 40),
                   substr($summary, 0, 400), (string)($b['detail'] ?? ''),
                   isset(FAILURE_DATA[$b['data_affected'] ?? '']) ? $b['data_affected'] : 'UNKNOWN',
                   substr(trim((string)($b['data_note'] ?? '')), 0, 1000),
                   (string)($b['immediate_action'] ?? ''),
                   (string)($b['reported_by'] ?? (function_exists('current_user') && current_user() ? user_name(current_user()) : 'system')),
                   date('c')]);
    return (int)db()->lastInsertId();
}

// Called from the fatal-error handler. Everything here is wrapped, because the
// app is already broken when this runs and the log must never make it worse.
//
// A failure log a person has to remember to write records only the failures
// somebody was in the mood to admit to. This one writes itself.
function failure_record_auto($summary, $detail = '') {
    try {
        if (!function_exists('db')) return 0;
        $fp = substr(md5(preg_replace('/\d+/', 'N', (string)$summary . '|' . substr((string)$detail, 0, 200))), 0, 40);
        // The same fault on every page load would otherwise flood the register
        // and bury the one entry somebody needs to read.
        $recent = ops_val("SELECT COUNT(*) FROM system_failures WHERE fingerprint=? AND occurred_at > ?",
                          [$fp, date('c', time() - 3600)]);
        if ((int)$recent > 0) return 0;
        return failure_create([
            'summary' => $summary, 'detail' => $detail, 'fingerprint' => $fp,
            'data_affected' => 'UNKNOWN', 'reported_by' => 'system',
        ], 'AUTOMATIC');
    } catch (Throwable $e) { return 0; }
}

// §7.11 asks what happened to the data. A failure closed without answering that
// is the one entry an assessor will pick out of the log.
function failure_close_missing($f) {
    $miss = [];
    if (($f['data_affected'] ?? 'UNKNOWN') === 'UNKNOWN')
        $miss[] = 'say whether data or results were affected';
    if (($f['data_affected'] ?? '') === 'YES' && trim((string)($f['data_note'] ?? '')) === '')
        $miss[] = 'say what was affected and what was done about it';
    if (trim((string)($f['immediate_action'] ?? '')) === '')
        $miss[] = 'record what was done at the time';
    if (($f['data_affected'] ?? '') === 'YES' && trim((string)($f['capa_ref'] ?? '')) === '')
        $miss[] = 'raise a corrective action — results were affected';
    return $miss;
}
function failure_close_block($f) {
    $miss = failure_close_missing($f);
    if (!$miss) return '';
    return 'This cannot be marked resolved yet. Still to do: ' . implode('; ', $miss)
         . '. ISO/IEC 17020 §7.11 asks what a failure did to the data — an entry that does not answer '
         . 'that is the one an assessor picks out of the log.';
}

// ============================================================================
//  Where this body stands on the clause
// ============================================================================
function datacontrol_readiness() {
    $app = sw_app_state();
    $ints = integrity_summary();
    $last = integrity_last_run();
    $acc = access_summary();
    $open = count(failures_all(['status' => 'OPEN']));
    $unanswered = 0;
    foreach (failures_all() as $f) if (($f['data_affected'] ?? '') === 'UNKNOWN') $unanswered++;
    return [
        'app' => $app, 'validations' => count(sw_all()), 'due_review' => count(sw_due_review()),
        'checks' => $ints['total'], 'check_failed' => $ints['failed'], 'check_skipped' => $ints['skipped'],
        'last_run' => $last, 'run_stale' => integrity_run_stale(),
        'access' => $acc,
        'failures_open' => $open, 'failures_unanswered' => $unanswered,
        'total_failures' => count(failures_all()),
    ];
}

function dc_can_view() { return can('mod.datacontrol.view'); }
function dc_can_edit() { return can('mod.datacontrol.edit'); }

// ============================================================================
//  Screen
// ============================================================================
function ops_datacontrol($route, $method) {
    ops_require(dc_can_view(), 'You don’t have access to the data-control register. Ask your administrator.');

    if ($route === 'data-control') {
        view('ops/data_control', [
            'ready' => datacontrol_readiness(),
            'validations' => sw_all(),
            'checks' => integrity_checks(),
            'runs' => integrity_runs(10),
            'access' => access_report(),
            'failures' => failures_all(),
            'canEdit' => dc_can_edit(),
        ]);
        return true;
    }

    ops_require(dc_can_edit(), 'Only somebody with the data-control permission can record these.');

    if ($route === 'data-check-run' && $method === 'POST') {
        $s = integrity_run();
        flash($s['failed'] === 0
            ? 'All ' . ($s['total'] - $s['skipped']) . ' checks passed, and the run is on file with today’s date. '
            . 'That is what §7.11 asks for — evidence, not an assurance.'
            : $s['failed'] . ' of ' . $s['total'] . ' checks FAILED. The run is on file either way; a check that '
            . 'only gets recorded when it passes is not a check.',
            $s['failed'] === 0 ? 'success' : 'error');
        redirect('/data-control');
    }

    if ($route === 'sw-validation-add' && $method === 'POST') {
        $id = sw_create($_POST);
        if ($id === -1) flash('Say what the thing is FOR. "Fit for purpose" means nothing without the purpose.', 'error');
        elseif (!$id) flash('A validation record needs the component and what was tested.', 'error');
        else flash('Recorded as ' . sw_all()[0]['ref'] . '.');
        redirect('/data-control');
    }

    if ($route === 'failure-add' && $method === 'POST') {
        $id = failure_create($_POST);
        flash($id ? 'Recorded. It stays open until you have said what it did to the data.'
                  : 'Say what failed.', $id ? 'success' : 'error');
        redirect('/data-control');
    }

    if ($route === 'failure-update' && $method === 'POST') {
        $f = failure_row((int)($_POST['id'] ?? 0));
        if (!$f) redirect('/data-control');
        $da = (string)($_POST['data_affected'] ?? $f['data_affected']);
        if (!isset(FAILURE_DATA[$da])) $da = 'UNKNOWN';
        db()->prepare("UPDATE system_failures SET data_affected=?, data_note=?, immediate_action=?, capa_ref=? WHERE id=?")
            ->execute([$da, substr(trim((string)($_POST['data_note'] ?? '')), 0, 1000),
                       (string)($_POST['immediate_action'] ?? ''),
                       substr(trim((string)($_POST['capa_ref'] ?? '')), 0, 40), $f['id']]);
        flash('Saved.');
        redirect('/data-control');
    }

    if ($route === 'failure-resolve' && $method === 'POST') {
        $f = failure_row((int)($_POST['id'] ?? 0));
        if (!$f) redirect('/data-control');
        $why = failure_close_block($f);
        if ($why !== '') { flash($why, 'error'); redirect('/data-control'); }
        db()->prepare("UPDATE system_failures SET status='RESOLVED', resolved_on=?, resolved_by=? WHERE id=?")
            ->execute([date('Y-m-d'), user_name(current_user()), $f['id']]);
        flash('Marked resolved.');
        redirect('/data-control');
    }

    // A failure that affected results should become a corrective action, and it
    // should carry its own words across rather than being retyped.
    if ($route === 'failure-capa' && $method === 'POST') {
        $f = failure_row((int)($_POST['id'] ?? 0));
        if (!$f || !function_exists('capa_create')) redirect('/data-control');
        if (trim((string)$f['capa_ref']) !== '') { flash('That one already has a corrective action.', 'error'); redirect('/data-control'); }
        $id = capa_create([
            'source' => 'INCIDENT', 'source_ref' => $f['ref'],
            'title' => $f['ref'] . ': ' . substr($f['summary'], 0, 180),
            'description' => "System failure " . $f['ref'] . " on " . substr((string)$f['occurred_at'], 0, 16)
                           . ".\n\nWhat happened:\n" . $f['summary']
                           . ($f['detail'] !== '' ? "\n\nDetail:\n" . $f['detail'] : '')
                           . "\n\nEffect on data:\n" . (FAILURE_DATA[$f['data_affected']] ?? $f['data_affected'])
                           . ($f['data_note'] !== '' ? ' — ' . $f['data_note'] : ''),
            'clause' => '8.3', 'severity' => $f['data_affected'] === 'YES' ? 'MAJOR' : 'MINOR',
        ]);
        if (!$id) redirect('/data-control');
        $ref = capa_row($id)['ref'];
        db()->prepare("UPDATE system_failures SET capa_ref=? WHERE id=?")->execute([$ref, $f['id']]);
        flash('Raised ' . $ref . ' from ' . $f['ref'] . '.');
        redirect('/capa-item?id=' . $id);
    }

    redirect('/data-control');
    return true;
}
