<?php
// ============================================================================
//  Internal audits (§8.8) and management review (§8.9)
//
//  These are the two clauses a body most often satisfies the night before the
//  assessment. Both produce a document; neither produces one that stands up,
//  because the evidence they are supposed to summarise was never gathered.
//
//  So two things here are unusual, and both are the point:
//
//   1. AN AUDITOR MAY NOT AUDIT THEIR OWN WORK. §8.8.2. Named on the plan,
//      checked, and refused. It is the commonest way a small body's internal
//      audit becomes worthless — the person who runs the process audits the
//      process — and it takes one line to prevent.
//
//   2. THE MANAGEMENT REVIEW READS ITSELF OFF THE RUNNING SYSTEM. §8.9.2 lists
//      fifteen required inputs. Fourteen of them are questions this application
//      already knows the answer to: how many complaints, how many upheld, how
//      many corrective actions still open, how much equipment is out of
//      calibration, whose authorisation lapsed, which impartiality threats are
//      undecided. Those figures are MEASURED and put in front of the chair,
//      who then writes what they mean. That is the half software can do; the
//      judgement is the half it must not pretend to.
//
//  A review is not complete until every required input has been addressed and
//  at least one decision has come out of it. §8.9.3 asks for outputs; a review
//  with fifteen inputs and no decisions is minutes of a meeting, not a review.
// ============================================================================

// The clause headings a body audits against. Editable, because numbering shifts
// between editions of the standard and some bodies run their own scheme — these
// are shipped as a starting point, not as gospel.
const AUDIT_CLAUSES = [
    '4.1' => '4.1 Impartiality and independence',
    '4.2' => '4.2 Confidentiality',
    '5.1' => '5.1 Administrative requirements',
    '5.2' => '5.2 Organisation and management',
    '6.1' => '6.1 Personnel',
    '6.2' => '6.2 Facilities and equipment',
    '6.3' => '6.3 Subcontracting',
    '7.1' => '7.1 Inspection methods and procedures',
    '7.2' => '7.2 Handling of inspection items and samples',
    '7.3' => '7.3 Inspection records',
    '7.4' => '7.4 Inspection reports and certificates',
    '7.5' => '7.5 Complaints and appeals',
    '7.6' => '7.6 Appeals',
    '8.2' => '8.2 Management system documentation',
    '8.3' => '8.3 Control of documents',
    '8.4' => '8.4 Control of records',
    '8.5' => '8.5 Actions to address risks and opportunities',
    '8.6' => '8.6 Improvement',
    '8.7' => '8.7 Corrective actions',
    '8.8' => '8.8 Internal audits',
    '8.9' => '8.9 Management reviews',
];

const AUDIT_STATUS = [
    'PLANNED'     => 'Planned',
    'IN_PROGRESS' => 'Being carried out',
    'REPORTED'    => 'Reported — findings raised',
    'CLOSED'      => 'Closed — every finding dealt with',
];

const FINDING_KINDS = [
    'NC_MAJOR'    => 'Major nonconformity',
    'NC_MINOR'    => 'Minor nonconformity',
    'OBSERVATION' => 'Observation',
    'CONFORMS'    => 'Conforms — nothing to raise',
];
// The kinds that must end up as a corrective action before the audit closes.
const FINDING_NEEDS_CAPA = ['NC_MAJOR', 'NC_MINOR'];

// How long a body may go without covering a clause before it is flagged.
const AUDIT_CYCLE_DEFAULT = 365;

// ---- §8.9.2, the required inputs -------------------------------------------
// Each carries how it is measured. 'auto' names the figure this application can
// produce; the note is always the chair's.
const MR_INPUTS = [
    'EXT_INT_ISSUES'  => ['Changes in internal and external issues relevant to the body', ''],
    'OBJECTIVES'      => ['Fulfilment of objectives', ''],
    'POLICIES'        => ['Suitability of policies and procedures', ''],
    'PREV_ACTIONS'    => ['Status of actions from previous management reviews', 'prev_actions'],
    'INTERNAL_AUDIT'  => ['Outcome of recent internal audits', 'audits'],
    'CORRECTIVE'      => ['Corrective actions', 'capa'],
    'EXTERNAL_ASSESS' => ['Assessments by external bodies', ''],
    'WORK_VOLUME'     => ['Changes in the volume and type of work', 'work'],
    'FEEDBACK'        => ['Feedback from clients and from our own people', ''],
    'COMPLAINTS'      => ['Complaints and appeals', 'complaints'],
    'IMPROVEMENTS'    => ['Effectiveness of the improvements we made', 'improvements'],
    'RESOURCES'       => ['Adequacy of resources — people, equipment, competence', 'resources'],
    'RISKS'           => ['Results of risk identification', 'risks'],
    'QC_RESULTS'      => ['Assurance of the validity of results', 'quality'],
    'OTHER'           => ['Anything else — training, monitoring, whatever mattered this period', ''],
];

// ---- §8.9.3, what has to come out of it ------------------------------------
const MR_ACTION_KINDS = [
    'EFFECTIVENESS' => 'The management system and its processes',
    'IMPROVEMENT'   => 'Improving what we do against the standard',
    'RESOURCES'     => 'Resources we need to provide',
    'CHANGE'        => 'A change we are making',
];

function audits_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = pk_clause();
    $pdo->exec("CREATE TABLE IF NOT EXISTS internal_audits (
        id $pk, ref VARCHAR(40) DEFAULT '',
        planned_on VARCHAR(20) DEFAULT '', carried_out_on VARCHAR(20) DEFAULT '',
        clauses VARCHAR(500) DEFAULT '', scope VARCHAR(1000) DEFAULT '',
        office_id INT NULL, area_owner VARCHAR(150) DEFAULT '', auditor VARCHAR(150) DEFAULT '',
        method VARCHAR(400) DEFAULT '', summary TEXT,
        status VARCHAR(20) DEFAULT 'PLANNED',
        reported_on VARCHAR(20) DEFAULT '', closed_on VARCHAR(20) DEFAULT '', closed_by VARCHAR(150) DEFAULT '',
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_findings (
        id $pk, audit_id INT, clause VARCHAR(20) DEFAULT '', kind VARCHAR(20) DEFAULT 'OBSERVATION',
        detail TEXT, evidence VARCHAR(1000) DEFAULT '',
        capa_ref VARCHAR(40) DEFAULT '', capa_id INT NULL,
        raised_by VARCHAR(150) DEFAULT '', raised_on VARCHAR(20) DEFAULT '')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS mgmt_reviews (
        id $pk, ref VARCHAR(40) DEFAULT '',
        held_on VARCHAR(20) DEFAULT '', period_from VARCHAR(20) DEFAULT '', period_to VARCHAR(20) DEFAULT '',
        chair VARCHAR(150) DEFAULT '', attendees VARCHAR(1000) DEFAULT '',
        status VARCHAR(20) DEFAULT 'DRAFT',
        completed_on VARCHAR(20) DEFAULT '', completed_by VARCHAR(150) DEFAULT '',
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS mr_inputs (
        id $pk, review_id INT, input_key VARCHAR(30) DEFAULT '',
        measured VARCHAR(1000) DEFAULT '', note TEXT,
        noted_by VARCHAR(150) DEFAULT '', noted_at VARCHAR(30) DEFAULT '')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS mr_actions (
        id $pk, review_id INT, kind VARCHAR(20) DEFAULT 'IMPROVEMENT',
        decision TEXT, owner VARCHAR(150) DEFAULT '', due_on VARCHAR(20) DEFAULT '',
        status VARCHAR(20) DEFAULT 'OPEN', done_on VARCHAR(20) DEFAULT '', done_note VARCHAR(1000) DEFAULT '',
        capa_ref VARCHAR(40) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
}

function aud_missing_table(Throwable $e) {
    $m = $e->getMessage();
    return stripos($m, 'no such table') !== false || stripos($m, "doesn't exist") !== false;
}
function aud_try($fn, $fallback = []) {
    try { return $fn(); } catch (Throwable $e) { if (aud_missing_table($e)) return $fallback; throw $e; }
}

function audit_cycle_days() {
    $d = (int)setting_get('audit_cycle_days', (string)AUDIT_CYCLE_DEFAULT);
    return $d > 0 ? $d : AUDIT_CYCLE_DEFAULT;
}
function audit_clause_options() { return lk_options_or('audit_clause', AUDIT_CLAUSES); }

// ============================================================================
//  Internal audits — §8.8
// ============================================================================
function audits_all($filter = []) {
    $w = ['1=1']; $a = [];
    if (!empty($filter['status'])) { $w[] = 'status = ?'; $a[] = $filter['status']; }
    if (!empty($filter['from']))   { $w[] = "COALESCE(NULLIF(carried_out_on,''), planned_on) >= ?"; $a[] = $filter['from']; }
    if (!empty($filter['to']))     { $w[] = "COALESCE(NULLIF(carried_out_on,''), planned_on) <= ?"; $a[] = $filter['to']; }
    return aud_try(fn() => ops_all("SELECT * FROM internal_audits WHERE " . implode(' AND ', $w)
                                 . " ORDER BY COALESCE(NULLIF(carried_out_on,''), planned_on) DESC, id DESC", $a));
}
function audit_row($id) { return aud_try(fn() => ops_one("SELECT * FROM internal_audits WHERE id=?", [(int)$id]), null); }
function audit_findings($id) {
    return aud_try(fn() => ops_all("SELECT * FROM audit_findings WHERE audit_id=? ORDER BY clause, id", [(int)$id]));
}
function audit_ref_next() {
    $n = (int)aud_try(fn() => ops_val("SELECT COUNT(*) FROM internal_audits"), 0);
    return 'IA-' . date('Y') . '-' . str_pad((string)($n + 1), 3, '0', STR_PAD_LEFT);
}
function audit_clauses_of($a) {
    return array_values(array_filter(array_map('trim', explode(',', (string)($a['clauses'] ?? '')))));
}

// §8.8.2 — auditors shall not audit their own work. The one line that decides
// whether a small body's internal audit is worth anything at all.
function audit_auditor_block($auditor, $areaOwner) {
    $auditor = trim((string)$auditor); $areaOwner = trim((string)$areaOwner);
    if ($auditor === '') return 'Name the auditor. An audit with no auditor on it is not evidence of anything.';
    if ($areaOwner !== '' && strcasecmp($auditor, $areaOwner) === 0)
        return $auditor . ' runs this area, so they cannot audit it. ' . accreditation_std_name() . ' §8.8.2 — auditors shall not '
             . 'audit their own work. In a small body this usually means swapping areas with a colleague, or '
             . 'bringing somebody in for the day.';
    return '';
}

// Which clauses have not been covered inside the cycle. This is the view an
// assessor asks for and almost nobody has: not "did you audit", but "did you
// audit ALL of it".
function audit_coverage($today = null) {
    $today = $today ?: date('Y-m-d');
    $since = date('Y-m-d', strtotime($today . ' -' . audit_cycle_days() . ' days'));
    $seen = [];
    foreach (audits_all() as $a) {
        $when = $a['carried_out_on'] !== '' ? $a['carried_out_on'] : $a['planned_on'];
        if ($when === '' || $when < $since) continue;
        if (!in_array($a['status'], ['REPORTED', 'CLOSED', 'IN_PROGRESS'], true)) continue;
        foreach (audit_clauses_of($a) as $cl)
            if (!isset($seen[$cl]) || $seen[$cl] < $when) $seen[$cl] = $when;
    }
    $out = [];
    foreach (audit_clause_options() as $k => $label)
        $out[$k] = ['label' => $label, 'last' => $seen[$k] ?? '', 'covered' => isset($seen[$k])];
    return $out;
}

function audit_close_missing($a) {
    $miss = [];
    if (($a['carried_out_on'] ?? '') === '') $miss[] = 'record the date it was actually carried out';
    if (trim((string)($a['summary'] ?? '')) === '') $miss[] = 'write what the audit found';
    $f = audit_findings((int)($a['id'] ?? 0));
    if (!$f) $miss[] = 'record at least one finding, even if it is "conforms"';
    foreach ($f as $x)
        if (in_array($x['kind'], FINDING_NEEDS_CAPA, true) && trim((string)$x['capa_ref']) === '') {
            $miss[] = 'raise a corrective action for every nonconformity found';
            break;
        }
    return $miss;
}
function audit_close_block($a) {
    $miss = audit_close_missing($a);
    if (!$miss) return '';
    return 'This audit cannot be closed yet. Still to do: ' . implode('; ', $miss)
         . '. A nonconformity recorded and never acted on is worse than one never found — it proves we knew.';
}

function audits_readiness($today = null) {
    $cov = audit_coverage($today);
    $uncovered = 0; foreach ($cov as $c) if (!$c['covered']) $uncovered++;
    $all = audits_all();
    $open = 0; $ncOpen = 0;
    foreach ($all as $a) {
        if ($a['status'] !== 'CLOSED') $open++;
        foreach (audit_findings((int)$a['id']) as $f)
            if (in_array($f['kind'], FINDING_NEEDS_CAPA, true) && trim((string)$f['capa_ref']) === '') $ncOpen++;
    }
    return [
        'total' => count($all), 'open' => $open,
        'clauses' => count($cov), 'uncovered' => $uncovered,
        'nc_without_capa' => $ncOpen, 'cycle_days' => audit_cycle_days(),
    ];
}

// ============================================================================
//  Management review — §8.9
// ============================================================================
function reviews_all() {
    return aud_try(fn() => ops_all("SELECT * FROM mgmt_reviews ORDER BY held_on DESC, id DESC"));
}
function review_row($id) { return aud_try(fn() => ops_one("SELECT * FROM mgmt_reviews WHERE id=?", [(int)$id]), null); }
function review_inputs($id) {
    $rows = aud_try(fn() => ops_all("SELECT * FROM mr_inputs WHERE review_id=?", [(int)$id]));
    $by = []; foreach ($rows as $r) $by[$r['input_key']] = $r;
    return $by;
}
function review_actions($id) {
    return aud_try(fn() => ops_all("SELECT * FROM mr_actions WHERE review_id=? ORDER BY id", [(int)$id]));
}
function review_ref_next() {
    $n = (int)aud_try(fn() => ops_val("SELECT COUNT(*) FROM mgmt_reviews"), 0);
    return 'MR-' . date('Y') . '-' . str_pad((string)($n + 1), 2, '0', STR_PAD_LEFT);
}

// The half software can honestly do: go and count. Every figure below is read
// from the running system for the review's own period, so the chair argues with
// real numbers instead of remembering.
// How many instruments have no calibration in force today. Counted here rather
// than assumed, and it works whether or not anything is on the register yet.
function mr_equipment_overdue() {
    if (!function_exists('equipment_all') || !function_exists('equipment_current_calibration')) return 0;
    $n = 0;
    foreach (equipment_all() as $e) if (!equipment_current_calibration((int)$e['id'])) $n++;
    return $n;
}

function mr_measure($key, $from, $to, $reviewId = 0) {
    switch ($key) {
        case 'complaints':
            if (!function_exists('cmp_all')) return 'The complaints register is not switched on.';
            $rows = array_filter(cmp_all(), fn($c) => $c['received_on'] >= $from && $c['received_on'] <= $to);
            $up = 0; $ap = 0; $late = 0;
            foreach ($rows as $c) {
                if (in_array($c['outcome'], ['UPHELD', 'PARTLY'], true)) $up++;
                if ($c['kind'] === 'APPEAL') $ap++;
                if (cmp_ack_overdue($c) || cmp_decide_overdue($c)) $late++;
            }
            return count($rows) . ' received (' . $ap . ' appeals); ' . $up . ' upheld or partly upheld; '
                 . $late . ' missed one of our own deadlines.';
        case 'capa':
            if (!function_exists('capa_all')) return 'The corrective-action register is not switched on.';
            $rows = capa_all(['from' => $from, 'to' => $to]);
            $open = 0; $failed = 0; $verifyLate = 0;
            foreach ($rows as $c) {
                if (capa_is_open($c)) $open++;
                if ($c['status'] === 'CLOSED_FAILED') $failed++;
                if (capa_verify_overdue($c)) $verifyLate++;
            }
            return count($rows) . ' raised; ' . $open . ' still open; ' . $failed
                 . ' found not to have worked and carried forward; ' . $verifyLate
                 . ' done but never checked for effectiveness.';
        case 'audits':
            $rows = audits_all(['from' => $from, 'to' => $to]);
            $r = audits_readiness();
            $nc = 0;
            foreach ($rows as $a) foreach (audit_findings((int)$a['id']) as $f)
                if (in_array($f['kind'], FINDING_NEEDS_CAPA, true)) $nc++;
            return count($rows) . ' audits in the period, ' . $nc . ' nonconformities raised; '
                 . $r['uncovered'] . ' of ' . $r['clauses'] . ' clauses not covered in the last '
                 . $r['cycle_days'] . ' days.';
        case 'prev_actions':
            $prev = null;
            foreach (reviews_all() as $r) {
                if ($reviewId && (int)$r['id'] === (int)$reviewId) continue;
                if ($r['held_on'] !== '' && $r['held_on'] < $from) { $prev = $r; break; }
            }
            if (!$prev) return 'No earlier management review on file — this appears to be the first.';
            $acts = review_actions((int)$prev['id']);
            $openA = count(array_filter($acts, fn($a) => $a['status'] !== 'DONE'));
            return $prev['ref'] . ' (' . fdate($prev['held_on']) . ') produced ' . count($acts)
                 . ' decisions; ' . $openA . ' still not done.';
        case 'work':
            $calls = (int)ops_val("SELECT COUNT(*) FROM calls WHERE created_at >= ? AND created_at <= ?", [$from, $to . 'z']);
            $jobs  = (int)ops_val("SELECT COUNT(*) FROM jobs WHERE COALESCE(scheduled_date,'') >= ? AND COALESCE(scheduled_date,'') <= ?", [$from, $to]);
            return $calls . ' ' . Tlp('call') . ' raised and ' . $jobs . ' ' . Tlp('job') . ' scheduled in the period.';
        case 'resources':
            $people = (int)ops_val("SELECT COUNT(*) FROM inspectors WHERE status='ACTIVE'");
            $bits = [$people . ' active ' . Tlp('engineer')];
            if (function_exists('competence_readiness')) {
                $cr = competence_readiness();
                $bits[] = $cr['authorised'] . ' authorised for something';
                $bits[] = $cr['lapsed'] . ' with a lapsed required certificate';
            }
            $bits[] = mr_equipment_overdue() . ' instruments out of calibration';
            return implode('; ', $bits) . '.';
        case 'risks':
            if (!function_exists('imp_readiness')) return 'The impartiality register is not switched on.';
            $ir = imp_readiness();
            return $ir['open'] . ' threats to impartiality not yet decided, ' . $ir['unacceptable']
                 . ' judged unacceptable, ' . $ir['declaration_due'] . ' people owing a declaration.';
        case 'quality':
            $bits = [mr_equipment_overdue() . ' instruments out of calibration'];
            if (function_exists('competence_readiness'))
                $bits[] = competence_readiness()['witness_due'] . ' people overdue a witnessed assessment';
            return implode('; ', $bits) . '.';
        case 'improvements':
            if (!function_exists('capa_all')) return '';
            $rows = capa_all(['from' => $from, 'to' => $to]);
            $eff = count(array_filter($rows, fn($c) => $c['effective'] === 'YES'));
            $inEff = count(array_filter($rows, fn($c) => $c['effective'] === 'NO'));
            return $eff . ' actions checked and found to have worked; ' . $inEff . ' that did not.';
    }
    return '';
}

// Fill in every measurable input for a review, without touching the notes a
// person already wrote. Safe to run again as the period's figures move.
function mr_refresh_measures($reviewId) {
    $r = review_row($reviewId);
    if (!$r) return 0;
    $have = review_inputs($reviewId);
    $n = 0;
    foreach (MR_INPUTS as $key => [$label, $auto]) {
        $measured = $auto !== '' ? mr_measure($auto, $r['period_from'], $r['period_to'], (int)$r['id']) : '';
        if (isset($have[$key])) {
            db()->prepare("UPDATE mr_inputs SET measured=? WHERE id=?")
                ->execute([substr($measured, 0, 1000), (int)$have[$key]['id']]);
        } else {
            db()->prepare("INSERT INTO mr_inputs (review_id,input_key,measured,note) VALUES (?,?,?,'')")
                ->execute([(int)$reviewId, $key, substr($measured, 0, 1000)]);
        }
        $n++;
    }
    return $n;
}

// §8.9.2 wants every input considered; §8.9.3 wants decisions out of it.
function review_complete_missing($id) {
    $r = review_row($id);
    if (!$r) return ['open the review'];
    $miss = [];
    if (($r['held_on'] ?? '') === '') $miss[] = 'record the date it was held';
    if (trim((string)($r['chair'] ?? '')) === '') $miss[] = 'record who chaired it';
    $have = review_inputs($id);
    $blank = [];
    foreach (MR_INPUTS as $key => [$label, $auto])
        if (trim((string)($have[$key]['note'] ?? '')) === '') $blank[] = $label;
    if ($blank) $miss[] = count($blank) . ' required input(s) with nothing written against them';
    if (!review_actions($id)) $miss[] = 'record at least one decision that came out of it';
    return $miss;
}
function review_blank_inputs($id) {
    $have = review_inputs($id);
    $out = [];
    foreach (MR_INPUTS as $key => [$label, $auto])
        if (trim((string)($have[$key]['note'] ?? '')) === '') $out[$key] = $label;
    return $out;
}
function review_complete_block($id) {
    $miss = review_complete_missing($id);
    if (!$miss) return '';
    return 'This review is not complete. Still to do: ' . implode('; ', $miss)
         . '. ' . accreditation_std_name() . ' §8.9.2 lists the inputs that must be considered and §8.9.3 the decisions that '
         . 'must come out — a review with fifteen inputs and no decisions is minutes of a meeting.';
}

function reviews_readiness() {
    $all = reviews_all();
    $last = null;
    foreach ($all as $r) if ($r['status'] === 'COMPLETE' && ($last === null || $r['held_on'] > $last['held_on'])) $last = $r;
    $daysSince = $last && $last['held_on'] !== ''
        ? (int)floor((time() - strtotime($last['held_on'])) / 86400) : null;
    $openActions = 0;
    foreach ($all as $r) foreach (review_actions((int)$r['id']) as $a) if ($a['status'] !== 'DONE') $openActions++;
    return [
        'total' => count($all), 'last' => $last, 'days_since' => $daysSince,
        'overdue' => $daysSince === null || $daysSince > 365,
        'open_actions' => $openActions,
    ];
}

function aud_can_view()  { return can('mod.audits.view'); }
function aud_can_edit()  { return can('mod.audits.edit'); }

// ============================================================================
//  Screens
// ============================================================================
function ops_audits($route, $method) {
    ops_require(aud_can_view(), 'You don’t have access to internal audits. Ask your administrator.');

    if ($route === 'internal-audits') {
        view('ops/audits_list', ['ready' => audits_readiness(), 'rows' => audits_all(),
                                 'coverage' => audit_coverage(), 'canEdit' => aud_can_edit()]);
        return true;
    }
    if ($route === 'internal-audit') {
        $a = audit_row((int)($_GET['id'] ?? 0));
        if (!$a) { http_response_code(404); view('notfound'); return true; }
        view('ops/audit_detail', [
            'a' => $a, 'findings' => audit_findings($a['id']),
            'missing' => audit_close_missing($a), 'block' => audit_close_block($a),
            'clauses' => audit_clause_options(), 'canEdit' => aud_can_edit(),
        ]);
        return true;
    }

    ops_require(aud_can_edit(), 'Only somebody with the internal-audit permission can plan or record one.');

    if ($route === 'internal-audit-new') {
        if ($method === 'POST') {
            $auditor = trim((string)($_POST['auditor'] ?? ''));
            $owner   = trim((string)($_POST['area_owner'] ?? ''));
            $why = audit_auditor_block($auditor, $owner);
            if ($why !== '') { flash($why, 'error'); redirect('/internal-audit-new'); }
            $cl = array_values(array_filter((array)($_POST['clauses'] ?? [])));
            $valid = audit_clause_options();
            $cl = array_values(array_filter($cl, fn($k) => isset($valid[$k])));
            if (!$cl) { flash('Choose at least one clause this audit covers.', 'error'); redirect('/internal-audit-new'); }
            $ref = audit_ref_next();
            db()->prepare("INSERT INTO internal_audits (ref,planned_on,clauses,scope,office_id,area_owner,auditor,method,status,created_by,created_at)
                           VALUES (?,?,?,?,?,?,?,?,'PLANNED',?,?)")
                ->execute([$ref, (string)($_POST['planned_on'] ?? date('Y-m-d')), implode(',', $cl),
                           substr(trim((string)($_POST['scope'] ?? '')), 0, 1000),
                           ($_POST['office_id'] ?? '') !== '' ? (int)$_POST['office_id'] : null,
                           substr($owner, 0, 150), substr($auditor, 0, 150),
                           substr(trim((string)($_POST['method'] ?? '')), 0, 400),
                           user_name(current_user()), date('c')]);
            flash('Planned as ' . $ref . '.');
            redirect('/internal-audit?id=' . (int)db()->lastInsertId());
        }
        view('ops/audit_form', ['clauses' => audit_clause_options()]);
        return true;
    }

    if ($route === 'audit-settings' && $method === 'POST') {
        ops_require(is_admin_level() || is_master(), 'Only an administrator can change this.');
        $d = (int)($_POST['audit_cycle_days'] ?? 0);
        if ($d > 0) setting_set('audit_cycle_days', (string)$d);
        flash('Saved.');
        redirect('/internal-audits');
    }

    $a = audit_row((int)($_POST['id'] ?? $_GET['id'] ?? 0));
    if (!$a) redirect('/internal-audits');

    if ($route === 'audit-record' && $method === 'POST') {
        $auditor = trim((string)($_POST['auditor'] ?? $a['auditor']));
        $owner   = trim((string)($_POST['area_owner'] ?? $a['area_owner']));
        $why = audit_auditor_block($auditor, $owner);
        if ($why !== '') { flash($why, 'error'); redirect('/internal-audit?id=' . $a['id']); }
        $on = (string)($_POST['carried_out_on'] ?? '');
        db()->prepare("UPDATE internal_audits SET carried_out_on=?, auditor=?, area_owner=?, method=?, summary=?,
                       status=?, reported_on=? WHERE id=?")
            ->execute([$on, substr($auditor, 0, 150), substr($owner, 0, 150),
                       substr(trim((string)($_POST['method'] ?? '')), 0, 400),
                       (string)($_POST['summary'] ?? ''),
                       $a['status'] === 'CLOSED' ? 'CLOSED' : ($on !== '' ? 'REPORTED' : 'IN_PROGRESS'),
                       $on !== '' ? ($a['reported_on'] ?: date('Y-m-d')) : '', $a['id']]);
        flash('Saved.');
        redirect('/internal-audit?id=' . $a['id']);
    }

    if ($route === 'audit-finding-add' && $method === 'POST') {
        $kind = (string)($_POST['kind'] ?? '');
        if (!isset(FINDING_KINDS[$kind])) $kind = 'OBSERVATION';
        $detail = trim((string)($_POST['detail'] ?? ''));
        if ($detail === '') { flash('Say what was found. A finding with no detail cannot be acted on.', 'error'); redirect('/internal-audit?id=' . $a['id']); }
        $cl = (string)($_POST['clause'] ?? '');
        db()->prepare("INSERT INTO audit_findings (audit_id,clause,kind,detail,evidence,raised_by,raised_on)
                       VALUES (?,?,?,?,?,?,?)")
            ->execute([$a['id'], substr($cl, 0, 20), $kind, $detail,
                       substr(trim((string)($_POST['evidence'] ?? '')), 0, 1000),
                       user_name(current_user()), date('Y-m-d')]);
        flash(in_array($kind, FINDING_NEEDS_CAPA, true)
            ? 'Recorded. A nonconformity needs a corrective action before this audit can close.'
            : 'Recorded.');
        redirect('/internal-audit?id=' . $a['id']);
    }

    if ($route === 'audit-finding-delete' && $method === 'POST') {
        db()->prepare("DELETE FROM audit_findings WHERE id=? AND audit_id=?")
            ->execute([(int)($_POST['finding_id'] ?? 0), $a['id']]);
        flash('Removed.');
        redirect('/internal-audit?id=' . $a['id']);
    }

    // The join between §8.8 and §8.7: a nonconformity becomes a real corrective
    // action, with the audit's words carried into it rather than retyped.
    if ($route === 'audit-finding-capa' && $method === 'POST') {
        $f = ops_one("SELECT * FROM audit_findings WHERE id=? AND audit_id=?", [(int)($_POST['finding_id'] ?? 0), $a['id']]);
        if (!$f || !function_exists('capa_create')) redirect('/internal-audit?id=' . $a['id']);
        if (trim((string)$f['capa_ref']) !== '') { flash('That finding already has one.', 'error'); redirect('/internal-audit?id=' . $a['id']); }
        $id = capa_create([
            'source' => 'INTERNAL_AUDIT', 'source_ref' => $a['ref'], 'audit_finding_id' => (int)$f['id'],
            'title' => $a['ref'] . ' ' . ($f['clause'] ?: '') . ': ' . substr(strip_tags($f['detail']), 0, 180),
            'description' => "Raised by internal audit " . $a['ref'] . ".\n\nFinding:\n" . $f['detail']
                           . ($f['evidence'] !== '' ? "\n\nEvidence seen:\n" . $f['evidence'] : ''),
            'clause' => $f['clause'], 'office_id' => $a['office_id'],
            'severity' => $f['kind'] === 'NC_MAJOR' ? 'MAJOR' : 'MINOR',
        ]);
        if (!$id) redirect('/internal-audit?id=' . $a['id']);
        $ref = capa_row($id)['ref'];
        db()->prepare("UPDATE audit_findings SET capa_ref=?, capa_id=? WHERE id=?")->execute([$ref, $id, (int)$f['id']]);
        flash('Raised ' . $ref . ' from that finding.');
        redirect('/capa-item?id=' . $id);
    }

    if ($route === 'audit-close' && $method === 'POST') {
        $why = audit_close_block($a);
        if ($why !== '') { flash($why, 'error'); redirect('/internal-audit?id=' . $a['id']); }
        db()->prepare("UPDATE internal_audits SET status='CLOSED', closed_on=?, closed_by=? WHERE id=?")
            ->execute([date('Y-m-d'), user_name(current_user()), $a['id']]);
        flash('Closed.');
        redirect('/internal-audit?id=' . $a['id']);
    }

    redirect('/internal-audits');
    return true;
}

function ops_reviews($route, $method) {
    ops_require(aud_can_view(), 'You don’t have access to management reviews. Ask your administrator.');

    if ($route === 'management-reviews') {
        view('ops/reviews_list', ['ready' => reviews_readiness(), 'rows' => reviews_all(),
                                  'canEdit' => aud_can_edit()]);
        return true;
    }
    if ($route === 'management-review') {
        $r = review_row((int)($_GET['id'] ?? 0));
        if (!$r) { http_response_code(404); view('notfound'); return true; }
        if (!review_inputs($r['id'])) mr_refresh_measures((int)$r['id']);
        view('ops/review_detail', [
            'r' => $r, 'inputs' => review_inputs($r['id']), 'actions' => review_actions($r['id']),
            'missing' => review_complete_missing($r['id']), 'blank' => review_blank_inputs($r['id']),
            'canEdit' => aud_can_edit(),
        ]);
        return true;
    }

    ops_require(aud_can_edit(), 'Only somebody with the internal-audit permission can hold a management review.');

    if ($route === 'management-review-new' && $method === 'POST') {
        $to   = (string)($_POST['period_to'] ?? date('Y-m-d'));
        $from = (string)($_POST['period_from'] ?? date('Y-m-d', strtotime($to . ' -1 year')));
        $ref  = review_ref_next();
        db()->prepare("INSERT INTO mgmt_reviews (ref,held_on,period_from,period_to,chair,attendees,status,created_by,created_at)
                       VALUES (?,?,?,?,?,?,'DRAFT',?,?)")
            ->execute([$ref, (string)($_POST['held_on'] ?? date('Y-m-d')), $from, $to,
                       substr(trim((string)($_POST['chair'] ?? user_name(current_user()))), 0, 150),
                       substr(trim((string)($_POST['attendees'] ?? '')), 0, 1000),
                       user_name(current_user()), date('c')]);
        $id = (int)db()->lastInsertId();
        $n = mr_refresh_measures($id);
        flash($ref . ' opened. ' . $n . ' required inputs are listed, and the ones this system can count '
            . 'have been counted for you — the judgement is still yours to write.');
        redirect('/management-review?id=' . $id);
    }

    $r = review_row((int)($_POST['id'] ?? $_GET['id'] ?? 0));
    if (!$r) redirect('/management-reviews');

    if ($route === 'review-refresh' && $method === 'POST') {
        $n = mr_refresh_measures((int)$r['id']);
        flash('Re-counted ' . $n . ' inputs from the running system. Nothing you wrote was touched.');
        redirect('/management-review?id=' . $r['id']);
    }

    if ($route === 'review-header' && $method === 'POST') {
        db()->prepare("UPDATE mgmt_reviews SET held_on=?, period_from=?, period_to=?, chair=?, attendees=? WHERE id=?")
            ->execute([(string)($_POST['held_on'] ?? $r['held_on']),
                       (string)($_POST['period_from'] ?? $r['period_from']),
                       (string)($_POST['period_to'] ?? $r['period_to']),
                       substr(trim((string)($_POST['chair'] ?? '')), 0, 150),
                       substr(trim((string)($_POST['attendees'] ?? '')), 0, 1000), $r['id']]);
        mr_refresh_measures((int)$r['id']);
        flash('Saved, and the figures re-counted for the new period.');
        redirect('/management-review?id=' . $r['id']);
    }

    if ($route === 'review-input' && $method === 'POST') {
        $key = (string)($_POST['input_key'] ?? '');
        if (!isset(MR_INPUTS[$key])) redirect('/management-review?id=' . $r['id']);
        $note = (string)($_POST['note'] ?? '');
        $have = review_inputs($r['id']);
        if (isset($have[$key]))
            db()->prepare("UPDATE mr_inputs SET note=?, noted_by=?, noted_at=? WHERE id=?")
                ->execute([$note, user_name(current_user()), date('c'), (int)$have[$key]['id']]);
        else
            db()->prepare("INSERT INTO mr_inputs (review_id,input_key,measured,note,noted_by,noted_at) VALUES (?,?,'',?,?,?)")
                ->execute([$r['id'], $key, $note, user_name(current_user()), date('c')]);
        redirect('/management-review?id=' . $r['id'] . '#in-' . $key);
    }

    if ($route === 'review-action-add' && $method === 'POST') {
        $d = trim((string)($_POST['decision'] ?? ''));
        if ($d === '') { flash('Write the decision. §8.9.3 asks for decisions and actions, not a list of topics.', 'error'); redirect('/management-review?id=' . $r['id']); }
        $k = (string)($_POST['kind'] ?? '');
        if (!isset(MR_ACTION_KINDS[$k])) $k = 'IMPROVEMENT';
        db()->prepare("INSERT INTO mr_actions (review_id,kind,decision,owner,due_on,status,created_at)
                       VALUES (?,?,?,?,?,'OPEN',?)")
            ->execute([$r['id'], $k, $d, substr(trim((string)($_POST['owner'] ?? '')), 0, 150),
                       (string)($_POST['due_on'] ?? ''), date('c')]);
        flash('Decision recorded.');
        redirect('/management-review?id=' . $r['id']);
    }

    if ($route === 'review-action-done' && $method === 'POST') {
        db()->prepare("UPDATE mr_actions SET status='DONE', done_on=?, done_note=? WHERE id=? AND review_id=?")
            ->execute([date('Y-m-d'), substr(trim((string)($_POST['done_note'] ?? '')), 0, 1000),
                       (int)($_POST['action_id'] ?? 0), $r['id']]);
        flash('Marked done.');
        redirect('/management-review?id=' . $r['id']);
    }

    if ($route === 'review-complete' && $method === 'POST') {
        $why = review_complete_block((int)$r['id']);
        if ($why !== '') { flash($why, 'error'); redirect('/management-review?id=' . $r['id']); }
        db()->prepare("UPDATE mgmt_reviews SET status='COMPLETE', completed_on=?, completed_by=? WHERE id=?")
            ->execute([date('Y-m-d'), user_name(current_user()), $r['id']]);
        flash('Recorded as complete.');
        redirect('/management-review?id=' . $r['id']);
    }

    redirect('/management-reviews');
    return true;
}
