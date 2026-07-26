<?php
// ===========================================================================
//  Compliance
//
//  An Indian company running software that holds other people's data has four
//  things to satisfy, and only some of them are code:
//
//    · the IT Act 2000, s.43A and the SPDI Rules — "reasonable security
//      practices", for which ISO/IEC 27001 is the named benchmark;
//    · the CERT-In directions — a six-hour window to report an incident, logs
//      kept at least 180 days inside India, clocks synchronised to NPL or NIST,
//      a software bill of materials, and an audit once a year by an empanelled
//      auditor;
//    · the DPDP Act 2023 — notice, consent, purpose limitation, breach
//      notification to the Data Protection Board and to the people affected,
//      and a named person to complain to;
//    · whatever the client's own contract adds on top.
//
//  Software cannot make a company compliant. What it can do is make the
//  obligations impossible to forget: hold the clock on the six hours, keep the
//  logs long enough, be able to hand somebody their own data or delete it, and
//  say plainly on one screen which of these is done and which is not. That is
//  what this file is.
// ===========================================================================

function compliance_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = (db_driver() === 'sqlite') ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    try {
        // The incident register. One row per event, with the clock on it.
        db()->exec("CREATE TABLE IF NOT EXISTS security_incidents (
            id $pk,
            ref VARCHAR(30) DEFAULT '',
            detected_at VARCHAR(30) DEFAULT '',
            kind VARCHAR(40) DEFAULT '',
            severity VARCHAR(20) DEFAULT 'MEDIUM',
            summary TEXT,
            systems TEXT,
            people_affected INT DEFAULT 0,
            data_kinds TEXT,
            immediate_action TEXT,
            root_cause TEXT,
            certin_reported_at VARCHAR(30) DEFAULT '',
            certin_ref VARCHAR(80) DEFAULT '',
            dpb_reported_at VARCHAR(30) DEFAULT '',
            people_told_at VARCHAR(30) DEFAULT '',
            status VARCHAR(20) DEFAULT 'OPEN',
            closed_at VARCHAR(30) DEFAULT '',
            created_by VARCHAR(120) DEFAULT '',
            created_at VARCHAR(30) DEFAULT '')");
        // What we told somebody we would use their data for, and when they
        // agreed. The DPDP Act turns on this being recorded, not assumed.
        db()->exec("CREATE TABLE IF NOT EXISTS data_consents (
            id $pk,
            subject_kind VARCHAR(20) DEFAULT '',
            subject_id INT DEFAULT 0,
            subject_name VARCHAR(200) DEFAULT '',
            purpose VARCHAR(200) DEFAULT '',
            basis VARCHAR(40) DEFAULT 'CONSENT',
            given_at VARCHAR(30) DEFAULT '',
            withdrawn_at VARCHAR(30) DEFAULT '',
            note TEXT,
            recorded_by VARCHAR(120) DEFAULT '')");
        // Somebody asking for their data, or asking for it to go.
        db()->exec("CREATE TABLE IF NOT EXISTS data_requests (
            id $pk,
            received_at VARCHAR(30) DEFAULT '',
            requester VARCHAR(200) DEFAULT '',
            contact VARCHAR(200) DEFAULT '',
            kind VARCHAR(20) DEFAULT 'ACCESS',
            subject_kind VARCHAR(20) DEFAULT '',
            subject_id INT DEFAULT 0,
            detail TEXT,
            status VARCHAR(20) DEFAULT 'OPEN',
            answered_at VARCHAR(30) DEFAULT '',
            answer TEXT,
            handled_by VARCHAR(120) DEFAULT '')");
    } catch (Throwable $e) {}
}

// --- the six-hour clock ----------------------------------------------------
const CERTIN_HOURS = 6;
function incident_hours_left($inc) {
    if (trim((string)$inc['certin_reported_at']) !== '') return null;   // already reported
    $det = strtotime((string)$inc['detected_at']);
    if (!$det) return null;
    return round(CERTIN_HOURS - (time() - $det) / 3600, 1);
}
function incident_clock_text($inc) {
    $h = incident_hours_left($inc);
    if ($h === null) return trim((string)$inc['certin_reported_at']) !== '' ? 'reported' : '—';
    if ($h <= 0) return 'overdue by ' . abs($h) . ' h';
    return $h . ' h left';
}
function incident_ref_next() {
    $n = (int)ops_val("SELECT COUNT(*) FROM security_incidents") + 1;
    return 'INC-' . date('Y') . '-' . str_pad((string)$n, 3, '0', STR_PAD_LEFT);
}
const INCIDENT_KINDS = [
    'UNAUTHORISED_ACCESS' => 'Somebody got in who should not have',
    'DATA_LEAK'           => 'Data went somewhere it should not have',
    'RANSOMWARE'          => 'Ransomware or other malware',
    'PHISHING'            => 'Phishing or a fake message that worked',
    'ACCOUNT_COMPROMISE'  => 'An account was taken over',
    'DEFACEMENT'          => 'The site or a document was tampered with',
    'DENIAL_OF_SERVICE'   => 'The system was made unavailable',
    'LOST_DEVICE'         => 'A laptop or phone with access was lost or stolen',
    'VENDOR'              => 'A supplier or hosting provider had an incident',
    'OTHER'               => 'Something else',
];
const INCIDENT_SEVERITY = ['LOW'=>'Low', 'MEDIUM'=>'Medium', 'HIGH'=>'High', 'CRITICAL'=>'Critical'];
const DATA_REQUEST_KINDS = [
    'ACCESS'     => 'A copy of their data',
    'CORRECTION' => 'Correct something that is wrong',
    'ERASURE'    => 'Delete their data',
    'WITHDRAW'   => 'Withdraw consent',
    'GRIEVANCE'  => 'A complaint',
];

// ---------------------------------------------------------------------------
//  Where the company's own answers live
//
//  The DPDP Act requires a named person to complain to and a notice saying what
//  is collected and why. Neither can be written by software — but leaving them
//  blank is itself the finding, so they are settings with a check against them.
// ---------------------------------------------------------------------------
function grievance_officer() {
    return [
        'name'  => trim((string)setting_get('grievance_name', '')),
        'email' => trim((string)setting_get('grievance_email', '')),
        'phone' => trim((string)setting_get('grievance_phone', '')),
    ];
}
function privacy_notice_text() { return (string)setting_get('privacy_notice', ''); }

// ---------------------------------------------------------------------------
//  Keeping the trail exactly as long as it should be kept
//
//  Two failures are possible and both matter. Too short breaks the CERT-In
//  180-day floor. Kept for ever, on a shared host, is a growing pile of who
//  did what — so the retention is a decision, written down, and applied.
// ---------------------------------------------------------------------------
function audit_trim_old() {
    $cut = date('c', time() - audit_retain_days() * 86400);
    try {
        $n = (int)ops_val("SELECT COUNT(*) FROM idems_audit WHERE created_at < ?", [$cut]);
        if ($n > 0) db()->prepare("DELETE FROM idems_audit WHERE created_at < ?")->execute([$cut]);
        return $n;
    } catch (Throwable $e) { return 0; }
}
function audit_oldest_days() {
    $o = ops_val("SELECT MIN(created_at) FROM idems_audit");
    if (!$o) return 0;
    return (int)floor((time() - strtotime((string)$o)) / 86400);
}

// ---------------------------------------------------------------------------
//  Handing somebody their own data, or removing it
//
//  Under the DPDP Act a person can ask for a copy of what is held about them,
//  and can ask for it to go. Doing that by hand across fifteen tables is how
//  it gets done wrongly, so it is collected in one place.
//
//  Erasure is deliberately not a delete of everything. An inspection report
//  that a client relies on, and the audit trail behind it, are records this
//  company is required to keep — so what goes is the contact detail that
//  identifies a person, and what stays is the work, with the name replaced.
//  That is the honest position and it is what is written on the screen.
// ---------------------------------------------------------------------------
function person_data_export($kind, $id) {
    $out = ['collected_at' => date('c'), 'subject' => ['kind' => $kind, 'id' => (int)$id]];
    if ($kind === 'user') {
        $u = ops_one("SELECT id, username, first_name, last_name, email, role, position_title,
                             home_office_id, last_login_at, last_login_ip, pwd_changed_at, is_active
                      FROM users WHERE id=?", [(int)$id]);
        if (!$u) return null;
        $out['subject']['name'] = trim($u['first_name'] . ' ' . $u['last_name']) ?: $u['username'];
        $out['account'] = $u;
        $out['inspector'] = ops_one("SELECT * FROM inspectors WHERE id=(SELECT inspector_id FROM users WHERE id=?)", [(int)$id]);
        $out['what_they_did'] = ops_all("SELECT created_at, entity, irn, action, field, new_value
                                         FROM idems_audit WHERE username=? ORDER BY id DESC", [$u['username']]);
    } elseif ($kind === 'contact') {
        $c = ops_one("SELECT * FROM partner_contacts WHERE id=?", [(int)$id]);
        if (!$c) return null;
        $out['subject']['name'] = $c['name'];
        $out['contact'] = $c;
        $out['works_for'] = ops_one("SELECT id, legal_name, display_name FROM business_partners WHERE id=?", [(int)$c['partner_id']]);
    } else {
        return null;
    }
    return $out;
}

// Returns a plain-language list of what erasing this person would and would not
// remove, so the decision is made with the facts in view.
function person_erase_preview($kind, $id) {
    if ($kind === 'contact') {
        $c = ops_one("SELECT * FROM partner_contacts WHERE id=?", [(int)$id]);
        if (!$c) return null;
        return [
            'name'    => $c['name'],
            'removed' => ['their name', 'e-mail address', 'mobile and phone number', 'designation and department'],
            'kept'    => ['the inspection calls and reports this contact appears on, with the name replaced by "Removed at request"',
                          'the audit trail of who changed what, which this company is required to keep'],
        ];
    }
    if ($kind === 'user') {
        $u = ops_one("SELECT * FROM users WHERE id=?", [(int)$id]);
        if (!$u) return null;
        return [
            'name'    => trim($u['first_name'] . ' ' . $u['last_name']) ?: $u['username'],
            'removed' => ['their name and e-mail address', 'the sign-in itself, which is switched off',
                          'the stored signature', 'the last-seen address'],
            'kept'    => ['reports they approved or issued, and the signature already printed on those — a report cannot be unsigned after a client has relied on it',
                          'the audit trail, under the username, which is what an inspection audit examines'],
        ];
    }
    return null;
}

function person_erase($kind, $id, $reason) {
    $pdo = db();
    if ($kind === 'contact') {
        $c = ops_one("SELECT * FROM partner_contacts WHERE id=?", [(int)$id]);
        if (!$c) return false;
        $pdo->prepare("UPDATE partner_contacts SET name='Removed at request', email='', mobile='',
                       phone='', designation='', department='' WHERE id=?")->execute([(int)$id]);
        idems_log('partner_contact', (int)$id, 'PERSON_ERASED', ['field'=>$c['name'], 'reason'=>$reason]);
        return true;
    }
    if ($kind === 'user') {
        $u = ops_one("SELECT * FROM users WHERE id=?", [(int)$id]);
        if (!$u) return false;
        $pdo->prepare("UPDATE users SET first_name='Removed', last_name='at request', email='',
                       is_active=0, signature='', totp_secret='', totp_enabled=0, recovery_codes='',
                       last_login_ip='' WHERE id=?")->execute([(int)$id]);
        idems_log('user', (int)$id, 'PERSON_ERASED', ['field'=>$u['username'], 'reason'=>$reason]);
        return true;
    }
    return false;
}

// ---------------------------------------------------------------------------
//  The one screen that says where we stand
//
//  Every line is measured from the running system, not from a checklist
//  somebody ticked. A line that cannot be measured from here says so, and says
//  who can answer it, rather than being quietly marked green.
// ---------------------------------------------------------------------------
function compliance_status() {
    $out = [];
    $add = function ($area, $what, $state, $detail, $todo = '') use (&$out) {
        $out[] = compact('area', 'what', 'state', 'detail', 'todo');
    };

    // --- IT Act s.43A / SPDI: reasonable security practices --------------
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $add('IT Act 2000 s.43A', 'Traffic encrypted in transit (HTTPS)',
        $https ? 'ok' : 'bad',
        $https ? 'This page was served over HTTPS.' : 'This page was served without HTTPS.',
        $https ? '' : 'Switch on the free certificate in your hosting panel, then uncomment the four HTTPS lines in .htaccess. Until then passwords travel in the clear and the session cookie cannot be marked secure.');

    $add('IT Act 2000 s.43A', 'Passwords stored irreversibly',
        'ok', 'Bcrypt. No password is stored or recoverable, including by an administrator.');

    $defaults = accounts_on_default_password();
    $add('IT Act 2000 s.43A', 'No account left on a shipped password',
        $defaults ? 'bad' : 'ok',
        $defaults ? count($defaults) . ' account(s): ' . implode(', ', array_column($defaults, 'username')) : 'None.',
        $defaults ? 'Open each account under Users and set a password, ticking "they must choose their own at the next sign-in".' : '');

    $twofaRoles = twofa_required_roles();
    $owed = (int)ops_val("SELECT COUNT(*) FROM users WHERE is_active=1 AND totp_enabled=0");
    $on   = (int)ops_val("SELECT COUNT(*) FROM users WHERE is_active=1 AND totp_enabled=1");
    $add('IT Act 2000 s.43A', 'Second factor on sign-in',
        $twofaRoles ? ($on > 0 ? 'ok' : 'warn') : 'warn',
        $twofaRoles ? ($on . ' of ' . ($on + $owed) . ' active accounts have it on; required for: ' . implode(', ', $twofaRoles))
                    : 'Available, but no role has been told to use it.',
        $twofaRoles ? '' : 'Settings → Security → name the roles that must use two-step sign-in. Start with the ones that can move money or change permissions.');

    // --- CERT-In directions ----------------------------------------------
    $keep = audit_retain_days();
    $add('CERT-In directions', 'Logs kept at least 180 days',
        $keep >= 180 ? 'ok' : 'bad',
        'Set to ' . $keep . ' days. Oldest entry on file: ' . audit_oldest_days() . ' days.');

    $add('CERT-In directions', 'Logs held inside India',
        'unknown', 'Depends on where your hosting account is. The app writes everything to your own database and sends nothing anywhere else.',
        'Confirm with MilesWeb in writing that the account and its backups sit in an Indian data centre, and keep that confirmation.');

    $open = ops_all("SELECT * FROM security_incidents WHERE status='OPEN'");
    $late = 0; foreach ($open as $i) { $h = incident_hours_left($i); if ($h !== null && $h <= 0) $late++; }
    $add('CERT-In directions', 'Incidents reported within six hours',
        $late ? 'bad' : 'ok',
        $late ? $late . ' incident(s) past the six-hour mark and not yet reported.'
              : count($open) . ' open, none past the six-hour mark.',
        $late ? 'Open the incident register and report to incident@cert-in.org.in now, then record the reference against the entry.' : '');

    $add('CERT-In directions', 'Clocks synchronised to NPL or NIST',
        'unknown', 'Server time now: ' . date('d M Y H:i:s T') . '. A web application cannot set the machine clock.',
        'Ask MilesWeb to confirm NTP is synchronised to time.nplindia.org (or an NIST server) and keep the reply.');

    $sbom = file_exists(__DIR__ . '/../SBOM.json');
    $add('CERT-In directions', 'Software bill of materials',
        $sbom ? 'ok' : 'warn',
        $sbom ? 'SBOM.json is present and is generated from the source at each release.' : 'Not generated yet.',
        $sbom ? '' : 'Run tools/sbom.php and keep the result with your release.');

    $lastAudit = trim((string)setting_get('last_cert_audit', ''));
    $due = $lastAudit ? (strtotime($lastAudit) < time() - 365 * 86400) : true;
    $add('CERT-In directions', 'Independent audit within the last year',
        $due ? 'bad' : 'ok',
        $lastAudit ? 'Last recorded: ' . fdate($lastAudit) : 'No audit recorded.',
        $due ? 'Engage a CERT-In empanelled auditor. The list is published on cert-in.org.in. Record the date under Settings when it is done.' : '');

    // --- DPDP Act 2023 -----------------------------------------------------
    $g = grievance_officer();
    $add('DPDP Act 2023', 'A named person to complain to',
        ($g['name'] && $g['email']) ? 'ok' : 'bad',
        ($g['name'] && $g['email']) ? $g['name'] . ' — ' . $g['email'] : 'Not named.',
        ($g['name'] && $g['email']) ? '' : 'Settings → Compliance → name a grievance officer with an e-mail address that is actually read. The Act requires this to be published.');

    $notice = privacy_notice_text();
    $add('DPDP Act 2023', 'A notice saying what is collected and why',
        $notice !== '' ? 'ok' : 'bad',
        $notice !== '' ? strlen($notice) . ' characters on file, shown at /privacy.' : 'Not written.',
        $notice !== '' ? '' : 'Settings → Compliance → write the notice. A starting draft is offered there.');

    $reqOpen = (int)ops_val("SELECT COUNT(*) FROM data_requests WHERE status='OPEN'");
    $add('DPDP Act 2023', 'Requests from people about their own data',
        $reqOpen ? 'warn' : 'ok',
        $reqOpen ? $reqOpen . ' open request(s).' : 'None open.',
        $reqOpen ? 'Open the register and answer them. A copy of somebody\'s data can be produced from the person\'s record in one click.' : '');

    $add('DPDP Act 2023', 'Breach notified to the Board and to the people affected',
        'ok', 'The incident register holds both dates and shows them as outstanding until they are filled in.');

    // --- things software cannot answer ------------------------------------
    $add('Everything else', 'Backups, and a restore that has been tried',
        'unknown', 'Every photograph, report and voucher in this system is in one database.',
        'Take a scheduled backup in the hosting panel, download a copy somewhere else, and restore it once to prove it works. An untested backup is not a backup.');

    $add('Everything else', 'ISO/IEC 27001 or SOC 2',
        'unknown', 'These certify the company and how it works, not this software.',
        'Neither is required by Indian law. ISO 27001 is the benchmark the IT Act names for "reasonable security", and enterprise clients ask for one or the other. Worth starting only when a client asks or a deal depends on it.');

    return $out;
}
// ===========================================================================
//  Screens
// ===========================================================================

function ops_compliance($method) {
    ops_require(is_master() || can('settings.manage'), 'Only an administrator can open the compliance view.');
    if ($method === 'POST' && ($_POST['_do'] ?? '') === 'trim') {
        $n = audit_trim_old();
        flash($n ? $n . ' audit entries older than ' . audit_retain_days() . ' days were removed.'
                 : 'Nothing on the trail is older than ' . audit_retain_days() . ' days.', $n ? 'success' : 'info');
        redirect('/compliance');
    }
    $rows = compliance_status();
    view('ops/compliance', ['rows'=>$rows, 'counts'=>compliance_counts($rows),
        'incidents'=>ops_all("SELECT * FROM security_incidents ORDER BY id DESC LIMIT 10"),
        'requests'=>ops_all("SELECT * FROM data_requests ORDER BY id DESC LIMIT 10")]);
}

function ops_incidents($route, $method) {
    ops_require(is_master() || can('settings.manage'), 'Only an administrator can open the incident register.');
    $pdo = db();

    if ($route === 'incident-new' || $route === 'incident-edit') {
        $inc = null;
        if ($route === 'incident-edit') {
            $inc = ops_one("SELECT * FROM security_incidents WHERE id=?", [(int)($_GET['id'] ?? 0)]);
            if (!$inc) { http_response_code(404); view('notfound'); return; }
        }
        if ($method === 'POST') {
            $b = $_POST;
            $f = [
                'detected_at'      => trim($b['detected_at'] ?? '') ?: date('c'),
                'kind'             => isset(INCIDENT_KINDS[$b['kind'] ?? '']) ? $b['kind'] : 'OTHER',
                'severity'         => isset(INCIDENT_SEVERITY[$b['severity'] ?? '']) ? $b['severity'] : 'MEDIUM',
                'summary'          => trim($b['summary'] ?? ''),
                'systems'          => trim($b['systems'] ?? ''),
                'people_affected'  => max(0, (int)($b['people_affected'] ?? 0)),
                'data_kinds'       => trim($b['data_kinds'] ?? ''),
                'immediate_action' => trim($b['immediate_action'] ?? ''),
                'root_cause'       => trim($b['root_cause'] ?? ''),
                'certin_reported_at' => trim($b['certin_reported_at'] ?? ''),
                'certin_ref'       => trim($b['certin_ref'] ?? ''),
                'dpb_reported_at'  => trim($b['dpb_reported_at'] ?? ''),
                'people_told_at'   => trim($b['people_told_at'] ?? ''),
                'status'           => in_array($b['status'] ?? '', ['OPEN','CLOSED'], true) ? $b['status'] : 'OPEN',
            ];
            $f['closed_at'] = $f['status'] === 'CLOSED' ? (trim((string)($inc['closed_at'] ?? '')) ?: date('c')) : '';
            if ($inc) {
                $sets = implode(',', array_map(function ($k) { return "$k=?"; }, array_keys($f)));
                $pdo->prepare("UPDATE security_incidents SET $sets WHERE id=?")
                    ->execute(array_merge(array_values($f), [$inc['id']]));
                flash('Incident updated.');
                redirect('/incident?id=' . (int)$inc['id']);
            }
            $f['ref'] = incident_ref_next();
            $f['created_by'] = user_name(current_user());
            $f['created_at'] = date('c');
            $cols = implode(',', array_keys($f));
            $qs   = implode(',', array_fill(0, count($f), '?'));
            $pdo->prepare("INSERT INTO security_incidents ($cols) VALUES ($qs)")->execute(array_values($f));
            $id = (int)$pdo->lastInsertId();
            idems_log('incident', $id, 'INCIDENT', ['field'=>$f['ref'], 'new'=>$f['kind']]);
            flash('Incident ' . $f['ref'] . ' recorded. The six-hour clock started when it was detected, not now.', 'warning');
            redirect('/incident?id=' . $id);
        }
        view('ops/incident_form', ['inc'=>$inc]);
        return;
    }

    if ($route === 'incident') {
        $inc = ops_one("SELECT * FROM security_incidents WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$inc) { http_response_code(404); view('notfound'); return; }
        view('ops/incident_detail', ['inc'=>$inc, 'g'=>grievance_officer()]);
        return;
    }

    $rows = ops_all("SELECT * FROM security_incidents ORDER BY id DESC");
    view('ops/incidents', ['rows'=>$rows]);
}

function ops_data_requests($route, $method) {
    ops_require(is_master() || can('settings.manage'), 'Only an administrator can open this register.');
    $pdo = db();

    // A copy of one person's data, as a file they can be sent.
    if ($route === 'person-data') {
        $kind = $_GET['kind'] ?? ''; $id = (int)($_GET['id'] ?? 0);
        $data = person_data_export($kind, $id);
        if (!$data) { http_response_code(404); view('notfound'); return; }
        idems_log($kind === 'user' ? 'user' : 'partner_contact', $id, 'PERSON_EXPORT',
                  ['field'=>$data['subject']['name'] ?? '']);
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)($data['subject']['name'] ?? 'person'));
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="data-held-about-' . $name . '-' . date('Ymd') . '.json"');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    if ($route === 'person-erase') {
        $kind = $_GET['kind'] ?? $_POST['kind'] ?? ''; $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        $prev = person_erase_preview($kind, $id);
        if (!$prev) { http_response_code(404); view('notfound'); return; }
        if ($method === 'POST' && ($_POST['_do'] ?? '') === 'erase') {
            if (trim($_POST['confirm'] ?? '') !== 'ERASE') {
                flash('Type ERASE to confirm. Nothing was changed.', 'error');
                redirect('/person-erase?kind=' . urlencode($kind) . '&id=' . $id);
            }
            person_erase($kind, $id, trim($_POST['reason'] ?? ''));
            flash('Done. What was kept, and why, is on the audit trail.', 'warning');
            redirect('/compliance');
        }
        view('ops/person_erase', ['kind'=>$kind, 'id'=>$id, 'prev'=>$prev]);
        return;
    }

    if ($method === 'POST') {
        $b = $_POST;
        if (($b['_do'] ?? '') === 'answer') {
            $pdo->prepare("UPDATE data_requests SET status='CLOSED', answered_at=?, answer=?, handled_by=? WHERE id=?")
                ->execute([date('c'), trim($b['answer'] ?? ''), user_name(current_user()), (int)$b['id']]);
            flash('Request closed.');
        } else {
            $pdo->prepare("INSERT INTO data_requests (received_at, requester, contact, kind, subject_kind, subject_id, detail, status)
                           VALUES (?,?,?,?,?,?,?, 'OPEN')")
                ->execute([trim($b['received_at'] ?? '') ?: date('c'), trim($b['requester'] ?? ''),
                           trim($b['contact'] ?? ''), isset(DATA_REQUEST_KINDS[$b['kind'] ?? '']) ? $b['kind'] : 'ACCESS',
                           trim($b['subject_kind'] ?? ''), (int)($b['subject_id'] ?? 0), trim($b['detail'] ?? '')]);
            flash('Request recorded. The Act expects a reasonable answer without undue delay — do not let it sit.', 'warning');
        }
        redirect('/data-requests');
    }
    view('ops/data_requests', ['rows'=>ops_all("SELECT * FROM data_requests ORDER BY status='CLOSED', id DESC")]);
}

function compliance_counts($rows) {
    $c = ['ok'=>0, 'warn'=>0, 'bad'=>0, 'unknown'=>0];
    foreach ($rows as $r) $c[$r['state']] = ($c[$r['state']] ?? 0) + 1;
    return $c;
}
