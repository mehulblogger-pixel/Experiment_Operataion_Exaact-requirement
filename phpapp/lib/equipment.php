<?php
// ============================================================================
//  Measuring and test equipment, and whether it is fit to be used
//
//  ISO/IEC 17020 §6.2: an inspection body must have the equipment it needs,
//  must control it, and must be able to show it was calibrated and working at
//  the time it was used. Before this file the app had no equipment at all —
//  yet a report could print the standard sentence "the measuring and test
//  equipment used was verified to be within its valid calibration period"
//  with nothing whatsoever behind it. An assessor asks for the certificate.
//
//  The four things this file exists to make true:
//
//   1. Every instrument is on a register with an identity somebody can read
//      off the label — the code, the serial number, whose branch owns it.
//   2. Calibration is a HISTORY, not a field. "Valid to 12 Aug" tells you
//      nothing about what state the instrument was in on 3 March. A report
//      issued in March is judged against the certificate that was live in
//      March, and that is only possible if the old certificates are kept.
//   3. An instrument is NAMED ON THE REPORT that relied on it. A register
//      nobody links to is paperwork; the link is what makes it evidence.
//   4. A report will not finalise while it names an instrument whose
//      calibration had lapsed on the inspection date. Checked on the server.
//
//  Deliberately NOT done: automatic scrapping, condition monitoring, or
//  booking equipment in and out day by day. Those are asset-management
//  features; §6.2 does not ask for them, and every field added here is a field
//  somebody has to keep true.
// ============================================================================

const EQUIP_STATUS = [
    'ACTIVE'     => 'In service',
    'CALIBRATION'=> 'Away for calibration',
    'REPAIR'     => 'Under repair',
    'QUARANTINE' => 'Quarantined — not to be used',
    'RETIRED'    => 'Retired / disposed',
];

// Statuses in which an instrument must not be used on an inspection at all,
// whatever its calibration says.
const EQUIP_UNUSABLE = ['QUARANTINE', 'RETIRED', 'REPAIR'];

function equipment_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = pk_clause();
    $pdo->exec("CREATE TABLE IF NOT EXISTS equipment (
        id $pk, code VARCHAR(60), name VARCHAR(200) DEFAULT '', kind VARCHAR(80) DEFAULT '',
        make VARCHAR(120) DEFAULT '', model VARCHAR(120) DEFAULT '', serial_no VARCHAR(120) DEFAULT '',
        range_spec VARCHAR(160) DEFAULT '', accuracy VARCHAR(160) DEFAULT '',
        office_id INT NULL, inspector_id INT NULL,
        status VARCHAR(20) DEFAULT 'ACTIVE',
        cal_interval_months INT DEFAULT 12,
        owned_by VARCHAR(20) DEFAULT 'OWN',
        notes VARCHAR(500) DEFAULT '',
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // One row per certificate. Never overwritten — that is the whole point.
    $pdo->exec("CREATE TABLE IF NOT EXISTS equipment_calibrations (
        id $pk, equipment_id INT, cert_no VARCHAR(120) DEFAULT '',
        cal_date VARCHAR(20) DEFAULT '', valid_to VARCHAR(20) DEFAULT '',
        cal_body VARCHAR(200) DEFAULT '', traceable_to VARCHAR(200) DEFAULT '',
        result VARCHAR(20) DEFAULT 'PASS', remarks VARCHAR(400) DEFAULT '',
        file_name VARCHAR(255) DEFAULT '', mime VARCHAR(100) DEFAULT '', file_data LONGTEXT,
        uploaded_by VARCHAR(150) DEFAULT '', uploaded_at VARCHAR(30) DEFAULT '')");
    // What a report relied on. The link that turns a register into evidence.
    $pdo->exec("CREATE TABLE IF NOT EXISTS report_equipment (
        id $pk, report_doc_id INT, equipment_id INT,
        used_on VARCHAR(20) DEFAULT '', calibration_id INT NULL,
        added_by VARCHAR(150) DEFAULT '', added_at VARCHAR(30) DEFAULT '')");
}

// True when the table is simply not built yet, which is the ONLY thing these
// helpers should tolerate. Swallowing every error hid an ambiguous-column bug
// that made the register look permanently empty — a broken query and an empty
// table are not the same thing and must not be treated the same way.
function equip_missing_table(Throwable $e) {
    $m = $e->getMessage();
    return stripos($m, 'no such table') !== false || stripos($m, "doesn't exist") !== false;
}

// ---- The register ----------------------------------------------------------
function equipment_all($includeRetired = false) {
    // e.status, NOT status: inspectors has a status column too, and the join
    // below makes a bare name ambiguous. SQLite and MySQL both refuse it.
    $w = $includeRetired ? '1=1' : "e.status <> 'RETIRED'";
    [$sw, $sa] = scope_clause('e.office_id', null);
    try {
        return ops_all("SELECT e.*, o.name office_name, i.name inspector_name
                        FROM equipment e
                        LEFT JOIN offices o ON o.id = e.office_id
                        LEFT JOIN inspectors i ON i.id = e.inspector_id
                        WHERE $w AND $sw ORDER BY e.code, e.id", $sa);
    } catch (Throwable $e) { if (equip_missing_table($e)) return []; throw $e; }
}

function equipment_get($id) {
    try { return ops_one("SELECT * FROM equipment WHERE id=?", [(int)$id]); }
    catch (Throwable $e) { if (equip_missing_table($e)) return null; throw $e; }
}

// Every certificate this instrument has ever had, newest first.
function equipment_calibrations($equipmentId) {
    try {
        return ops_all("SELECT id, equipment_id, cert_no, cal_date, valid_to, cal_body, traceable_to,
                               result, remarks, file_name, mime, uploaded_by, uploaded_at
                        FROM equipment_calibrations WHERE equipment_id=?
                        ORDER BY valid_to DESC, id DESC", [(int)$equipmentId]);
    } catch (Throwable $e) { if (equip_missing_table($e)) return []; throw $e; }
}

// The certificate that was in force on a given date — NOT simply the newest.
// A report issued in March must be judged against March's certificate, which
// is why the history is kept rather than one "valid to" column overwritten
// each year.
function equipment_calibration_on($equipmentId, $onDate = null) {
    $onDate = $onDate ?: date('Y-m-d');
    foreach (equipment_calibrations($equipmentId) as $c) {
        if ($c['result'] !== 'PASS') continue;
        if ($c['cal_date'] !== '' && $c['cal_date'] > $onDate) continue;   // not yet issued
        if ($c['valid_to'] !== '' && $c['valid_to'] < $onDate) continue;   // already lapsed
        return $c;
    }
    return null;
}

// The current one, for the register listing.
function equipment_current_calibration($equipmentId) {
    return equipment_calibration_on($equipmentId, date('Y-m-d'));
}

// ---- Is this instrument fit to be used on this date? ------------------------
// '' when it is, otherwise the reason it is not. One function, used by the
// register, the report screen and the finalise gate, so the three can never
// disagree about what "calibrated" means.
function equipment_block($equipmentId, $onDate = null) {
    $e = equipment_get($equipmentId);
    if (!$e) return 'That instrument is not on the register.';
    $onDate = $onDate ?: date('Y-m-d');
    $label = trim(($e['code'] ?? '') . ' ' . ($e['name'] ?? '')) ?: 'Instrument';
    if (in_array($e['status'], EQUIP_UNUSABLE, true))
        return $label . ' is marked "' . (EQUIP_STATUS[$e['status']] ?? $e['status']) . '" and must not be used.';
    $cal = equipment_calibration_on($e['id'], $onDate);
    if (!$cal) {
        $last = equipment_calibrations($e['id']);
        if (!$last) return $label . ' has no calibration certificate on file.';
        $newest = $last[0];
        return $label . ' was not within calibration on ' . fdate($onDate)
             . ' — the latest certificate on file (' . ($newest['cert_no'] ?: 'no number')
             . ') was valid to ' . ($newest['valid_to'] ? fdate($newest['valid_to']) : '—') . '.';
    }
    return '';
}

// Days until the current certificate lapses; null when there is none.
function equipment_days_left($equipmentId) {
    $cal = equipment_current_calibration($equipmentId);
    if (!$cal || $cal['valid_to'] === '') return null;
    return (int)round((strtotime($cal['valid_to']) - strtotime(date('Y-m-d'))) / 86400);
}

// ---- What a report relied on ----------------------------------------------
function report_equipment($docId) {
    try {
        return ops_all("SELECT re.*, e.code, e.name, e.serial_no, e.status
                        FROM report_equipment re JOIN equipment e ON e.id = re.equipment_id
                        WHERE re.report_doc_id=? ORDER BY e.code", [(int)$docId]);
    } catch (Throwable $e) { if (equip_missing_table($e)) return []; throw $e; }
}

function report_equipment_add($docId, $equipmentId, $usedOn) {
    $usedOn = $usedOn ?: date('Y-m-d');
    // Stamp WHICH certificate was relied on, so re-calibrating the instrument
    // later can never quietly rewrite what an issued report rested on.
    $cal = equipment_calibration_on($equipmentId, $usedOn);
    try {
        if ((int)ops_val("SELECT COUNT(*) FROM report_equipment WHERE report_doc_id=? AND equipment_id=?",
                         [(int)$docId, (int)$equipmentId])) return 'That instrument is already listed.';
        db()->prepare("INSERT INTO report_equipment (report_doc_id,equipment_id,used_on,calibration_id,added_by,added_at)
                       VALUES (?,?,?,?,?,?)")
            ->execute([(int)$docId, (int)$equipmentId, $usedOn, $cal['id'] ?? null,
                       user_name(current_user()), date('c')]);
    } catch (Throwable $e) { return 'Could not add that instrument.'; }
    return '';
}

// The finalise gate. Empty means the report may be issued.
function report_equipment_block($doc) {
    $bad = [];
    $onDate = report_equipment_date($doc);
    foreach (report_equipment((int)($doc['id'] ?? 0)) as $r) {
        $why = equipment_block((int)$r['equipment_id'], $r['used_on'] ?: $onDate);
        if ($why !== '') $bad[] = $why;
    }
    if (!$bad) return '';
    return 'This ' . Tl('report') . ' names equipment that was not fit to be used: ' . implode(' ', $bad);
}

// The date the instrument had to be calibrated on — when the work happened,
// not when somebody got round to issuing the report.
function report_equipment_date($doc) {
    foreach (['inspection_date', 'issue_date'] as $k)
        if (!empty($doc[$k])) return substr((string)$doc[$k], 0, 10);
    if (!empty($doc['job_id'])) {
        $j = ops_one("SELECT inspection_start_date, scheduled_date FROM jobs WHERE id=?", [(int)$doc['job_id']]);
        foreach (['inspection_start_date', 'scheduled_date'] as $k)
            if (!empty($j[$k])) return substr((string)$j[$k], 0, 10);
    }
    return date('Y-m-d');
}

// ---- Impact awareness: which released work rested on this instrument -------
// The register answers "does THIS report's instrument have a live certificate?"
// (the finalise gate). The reverse question was never asked: when a certificate
// lapses, or is later found bad, WHICH already-issued reports and jobs rested on
// this instrument? The foreign key exists and is indexed (report_equipment.
// equipment_id, ix_req_equip) — this reads it. Read-only: it FLAGS work for a
// controlled quality review; it never auto-invalidates a report.

// Every report that named this instrument, newest issue first, with its status,
// its job and the certificate it was stamped against.
function reports_using_equipment($equipmentId) {
    try {
        return ops_all("SELECT re.id link_id, re.used_on, re.calibration_id, re.added_at,
                               d.id doc_id, d.irn, d.type_code, d.title, d.status, d.finalized,
                               d.inspection_date, d.issue_date, d.job_id, j.job_code
                        FROM report_equipment re
                        JOIN report_docs d ON d.id = re.report_doc_id
                        LEFT JOIN jobs j ON j.id = d.job_id
                        WHERE re.equipment_id=? AND COALESCE(d.deleted,0)=0
                        ORDER BY (d.issue_date <> '') DESC, d.issue_date DESC, d.id DESC",
                       [(int)$equipmentId]);
    } catch (Throwable $e) { if (equip_missing_table($e)) return []; throw $e; }
}

// A read-only verdict per report. REVIEW never means "invalidated" — it means a
// human should look, per the quality procedure. The two things that raise it:
//   * the certificate the report rested on is no longer good — it was later
//     marked FAIL, or the certificate record was removed (revoked); or
//   * no valid certificate was in force on the work date at all (a coverage gap
//     the report should never have issued through).
// Ordinary expiry AFTER the work, and supersession by a newer certificate, do
// NOT raise REVIEW — correctly-covered past work stays OK (see the tests). Only
// released work (APPROVED / ISSUED / finalised) is counted as impact; a draft is
// listed but not counted, because it re-hits the hard block on issue.
function equipment_calibration_impact($equipmentId) {
    $equipmentId = (int)$equipmentId;
    $cals = [];
    foreach (equipment_calibrations($equipmentId) as $c) $cals[(int)$c['id']] = $c;
    $out = ['reports' => [], 'review' => 0, 'released' => 0];
    foreach (reports_using_equipment($equipmentId) as $r) {
        $released = !empty($r['finalized']) || in_array($r['status'], ['APPROVED', 'ISSUED'], true);
        $usedOn = $r['used_on'] ?: report_equipment_date([
            'inspection_date' => $r['inspection_date'], 'issue_date' => $r['issue_date'], 'job_id' => $r['job_id']]);
        $stampId = (int)($r['calibration_id'] ?? 0);
        $rested  = ($stampId && isset($cals[$stampId])) ? $cals[$stampId] : null;

        $verdict = 'OK'; $why = '';
        if ($stampId && !$rested) {
            $verdict = 'REVIEW'; $why = 'the certificate it was recorded against is no longer on file (revoked or removed)';
        } elseif ($rested && $rested['result'] !== 'PASS') {
            $verdict = 'REVIEW'; $why = 'the certificate it was recorded against is now marked ' . $rested['result'];
        } elseif (!equipment_calibration_on($equipmentId, $usedOn)) {
            $verdict = 'REVIEW'; $why = 'no valid certificate was in force on ' . fdate($usedOn);
        }

        if ($released) $out['released']++;
        if ($verdict === 'REVIEW' && $released) $out['review']++;
        $out['reports'][] = $r + ['released' => $released, 'verdict' => $verdict, 'why' => $why, 'used_on_eff' => $usedOn];
    }
    return $out;
}

// ---- Reminders -------------------------------------------------------------
// Same shape as the certificate reminder that already runs from cron.php.
function equipment_run_cal_reminders($today = null) {
    $today = $today ?: date('Y-m-d');
    $to = getenv('QAC_EMAIL') ?: coordinator_emails();
    $sent = 0;
    foreach (equipment_all() as $e) {
        $days = equipment_days_left($e['id']);
        if ($days === null || $days > 30) continue;
        $cal = equipment_current_calibration($e['id']);
        $when = $days < 0 ? 'expired ' . abs($days) . ' day(s) ago' : "due in $days day(s)";
        // Blast radius: how many already-released reports rested on this instrument,
        // so the reader knows what is downstream (review at the equipment screen).
        $impact = equipment_calibration_impact($e['id']);
        $blast = (int)$impact['released'] > 0
            ? "Used on " . (int)$impact['released'] . " released " . Tlp('report')
              . " — review the calibration impact at /equip-edit?id=" . (int)$e['id'] . ".\n"
            : '';
        $body = "Calibration follow-up.\n\nInstrument: {$e['code']} {$e['name']}\n"
              . "Serial: " . ($e['serial_no'] ?: '—') . "\n"
              . "Certificate: " . ($cal['cert_no'] ?? '—') . "\n"
              . "Valid to: " . ($cal['valid_to'] ?? '—') . " — {$when}.\n"
              . $blast . "\n"
              . "An instrument out of calibration must not be used, and a report naming one will not issue.\n\n"
              . app_name();
        ops_mail($to, "Calibration due: {$e['code']} {$e['name']} ($when)", $body, '', 'calibration');
        $sent++;
    }
    return $sent;
}

// Everything due within N days — for the register banner and the dashboard.
function equipment_due_soon($days = 30) {
    $out = [];
    foreach (equipment_all() as $e) {
        $d = equipment_days_left($e['id']);
        if ($d === null || $d <= $days) $out[] = $e + ['days_left' => $d];
    }
    return $out;
}

function equipment_can_manage() { return can('master.manage') || is_admin_level() || is_master(); }

// ---- Screens ---------------------------------------------------------------
function ops_equipment($route, $method) {
    if ($route === 'equip-cert') {                      // download a certificate
        $c = ops_one("SELECT * FROM equipment_calibrations WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$c) { http_response_code(404); view('notfound'); return true; }
        send_uploaded_file(base64_decode((string)$c['file_data']), $c['file_name'] ?: 'certificate', $c['mime'] ?: 'application/octet-stream');
        return true;
    }
    if ($route === 'equipment') {
        view('ops/equipment_list', [
            'rows'    => equipment_all(!empty($_GET['all'])),
            'dueSoon' => equipment_due_soon(30),
            'canEdit' => equipment_can_manage(),
            'showAll' => !empty($_GET['all']),
        ]);
        return true;
    }
    // everything below changes something
    ops_require(equipment_can_manage(), 'Only a manager can change the equipment register.');

    if ($route === 'equip-new' || $route === 'equip-edit') {
        $e = $route === 'equip-edit' ? equipment_get($_GET['id'] ?? 0) : null;
        if ($route === 'equip-edit' && !$e) { http_response_code(404); view('notfound'); return true; }
        if ($method === 'POST') {
            $b = $_POST;
            $code = trim((string)($b['code'] ?? ''));
            if ($code === '') {
                view('ops/equipment_form', equipment_form_vars($e, 'An identification code is required — it is what somebody reads off the label.'));
                return true;
            }
            $f = ['code','name','kind','make','model','serial_no','range_spec','accuracy','status','owned_by','notes'];
            $vals = array_map(fn($k) => trim((string)($b[$k] ?? '')), $f);
            $office = ($b['office_id'] ?? '') !== '' ? (int)$b['office_id'] : null;
            $insp   = ($b['inspector_id'] ?? '') !== '' ? (int)$b['inspector_id'] : null;
            $months = max(0, (int)($b['cal_interval_months'] ?? 12));
            if ($e) {
                $set = implode(',', array_map(fn($k) => "$k=?", $f));
                db()->prepare("UPDATE equipment SET $set, office_id=?, inspector_id=?, cal_interval_months=? WHERE id=?")
                    ->execute(array_merge($vals, [$office, $insp, $months, $e['id']]));
                flash('Instrument updated.');
                redirect('/equip-edit?id=' . $e['id']);
            }
            db()->prepare("INSERT INTO equipment (" . implode(',', $f) . ",office_id,inspector_id,cal_interval_months,created_by,created_at)
                           VALUES (" . implode(',', array_fill(0, count($f), '?')) . ",?,?,?,?,?)")
                ->execute(array_merge($vals, [$office, $insp, $months, user_name(current_user()), date('c')]));
            flash('Instrument added. Now upload its calibration certificate — until one is on file it cannot be used on a report.');
            redirect('/equip-edit?id=' . db()->lastInsertId());
        }
        view('ops/equipment_form', equipment_form_vars($e, null));
        return true;
    }
    if ($route === 'equip-cal-add' && $method === 'POST') {
        $e = equipment_get($_POST['equipment_id'] ?? 0);
        if (!$e) { http_response_code(404); view('notfound'); return true; }
        $file  = $_FILES['cert_file'] ?? null;
        $bytes = ($file && ($file['tmp_name'] ?? '') !== '' && is_uploaded_file($file['tmp_name']))
            ? (string)file_get_contents($file['tmp_name']) : '';
        if ($bytes !== '' && ($why = upload_reject_reason($bytes, $file['name'] ?? '', $file['type'] ?? '')) !== '') {
            flash($why, 'error'); redirect('/equip-edit?id=' . $e['id']);
        }
        $calDate = (string)($_POST['cal_date'] ?? '');
        $validTo = (string)($_POST['valid_to'] ?? '');
        // If the validity was left blank, take it from the interval on the
        // instrument rather than storing a certificate that never expires.
        if ($validTo === '' && $calDate !== '' && (int)$e['cal_interval_months'] > 0)
            $validTo = date('Y-m-d', strtotime($calDate . ' +' . (int)$e['cal_interval_months'] . ' months'));
        db()->prepare("INSERT INTO equipment_calibrations
            (equipment_id,cert_no,cal_date,valid_to,cal_body,traceable_to,result,remarks,file_name,mime,file_data,uploaded_by,uploaded_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$e['id'], trim((string)($_POST['cert_no'] ?? '')), $calDate, $validTo,
                       trim((string)($_POST['cal_body'] ?? '')), trim((string)($_POST['traceable_to'] ?? '')),
                       ($_POST['result'] ?? 'PASS') === 'FAIL' ? 'FAIL' : 'PASS',
                       trim((string)($_POST['remarks'] ?? '')),
                       substr((string)($file['name'] ?? ''), 0, 255), (string)($file['type'] ?? ''),
                       $bytes === '' ? '' : base64_encode($bytes),
                       user_name(current_user()), date('c')]);
        flash('Calibration certificate filed.');
        redirect('/equip-edit?id=' . $e['id']);
    }
    if ($route === 'equip-cal-del' && $method === 'POST') {
        $c = ops_one("SELECT * FROM equipment_calibrations WHERE id=?", [(int)($_POST['id'] ?? 0)]);
        if ($c) {
            db()->prepare("DELETE FROM equipment_calibrations WHERE id=?")->execute([(int)$c['id']]);
            flash('Certificate removed.');
            redirect('/equip-edit?id=' . (int)$c['equipment_id']);
        }
        redirect('/equipment');
    }
    // Naming an instrument on a report, and taking one off again.
    if ($route === 'report-equip-add' && $method === 'POST') {
        $docId = (int)($_POST['doc_id'] ?? 0);
        $why = report_equipment_add($docId, (int)($_POST['equipment_id'] ?? 0), (string)($_POST['used_on'] ?? ''));
        flash($why ?: 'Instrument recorded against this ' . Tl('report') . '.', $why ? 'error' : 'success');
        redirect('/document?id=' . $docId);
    }
    if ($route === 'report-equip-del' && $method === 'POST') {
        $docId = (int)($_POST['doc_id'] ?? 0);
        db()->prepare("DELETE FROM report_equipment WHERE id=? AND report_doc_id=?")
            ->execute([(int)($_POST['id'] ?? 0), $docId]);
        flash('Instrument removed from this ' . Tl('report') . '.');
        redirect('/document?id=' . $docId);
    }
    redirect('/equipment');
    return true;
}

function equipment_form_vars($e, $error) {
    return ['e' => $e, 'error' => $error,
            'offices' => offices_list(), 'inspectors' => inspectors_list(),
            'cals' => $e ? equipment_calibrations($e['id']) : [],
            'block' => $e ? equipment_block($e['id']) : '',
            'impact' => $e ? equipment_calibration_impact($e['id']) : ['reports' => [], 'review' => 0, 'released' => 0]];
}
