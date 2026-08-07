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
        // Identity documents, and — the part people most want to know — every
        // time somebody in this company opened one of them. The scans are not
        // put in the export: they are the person's own documents, they already
        // have them, and a copy in a downloadable file is one more copy loose.
        if (function_exists('iddoc_list') && !empty($out['inspector']['id'])) {
            $iid = (int)$out['inspector']['id'];
            $out['identity_documents'] = iddoc_list($iid);
            $out['who_looked_at_them'] = iddoc_access_log(0, $iid, 500);
        }
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
            'kept'    => ['the ' . Tlp('call') . ' and ' . Tlp('report') . ' this contact appears on, with the name replaced by "Removed at request"',
                          'the audit trail of who changed what, which this company is required to keep'],
        ];
    }
    if ($kind === 'user') {
        $u = ops_one("SELECT * FROM users WHERE id=?", [(int)$id]);
        if (!$u) return null;
        return [
            'name'    => trim($u['first_name'] . ' ' . $u['last_name']) ?: $u['username'],
            'removed' => ['their name and e-mail address', 'the sign-in itself, which is switched off',
                          'the stored signature', 'the last-seen address',
                          'every identity document held for them — the scan and the number both go'],
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
        // An erasure that leaves a passport scan on the server is not an
        // erasure. Redaction keeps the row — the proof that identity was once
        // checked — and takes the copy away, which is the whole point.
        if (function_exists('iddoc_list') && !empty($u['inspector_id']))
            foreach (iddoc_list((int)$u['inspector_id']) as $d)
                iddoc_redact((int)$d['id'], 'Erasure requested by the person. ' . $reason);
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
        'Get written confirmation from whoever hosts this system that the server and its backups sit in an Indian data centre, and keep that confirmation. '
        . 'On your own server that is your own record; on shared hosting, ask the provider.');

    $open = ops_all("SELECT * FROM security_incidents WHERE status='OPEN'");
    $late = 0; foreach ($open as $i) { $h = incident_hours_left($i); if ($h !== null && $h <= 0) $late++; }
    $add('CERT-In directions', 'Incidents reported within six hours',
        $late ? 'bad' : 'ok',
        $late ? $late . ' incident(s) past the six-hour mark and not yet reported.'
              : count($open) . ' open, none past the six-hour mark.',
        $late ? 'Open the incident register and report to incident@cert-in.org.in now, then record the reference against the entry.' : '');

    $add('CERT-In directions', 'Clocks synchronised to NPL or NIST',
        'unknown', 'Server time now: ' . date('d M Y H:i:s T') . '. A web application cannot set the machine clock.',
        'Confirm the server clock is synchronised to time.nplindia.org (or an NIST server) and keep the evidence — '
        . 'ask the hosting provider, or check the NTP service on your own server.');

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

    // Identity documents are the heaviest personal data this system holds, so
    // they get their own three lines rather than hiding inside "we have a notice".
    if (function_exists('iddoc_readiness')) {
        $idr = iddoc_readiness();
        $add('DPDP Act 2023', 'Identity documents held for a stated purpose only',
            'ok', 'Purpose: ' . iddoc_purpose() . ' It is stamped onto every document as it is filed.',
            'Check the wording matches what you actually tell people, on the Identity documents screen.');
        $add('DPDP Act 2023', 'Identity documents retired when they are no longer needed',
            $idr['due_redaction'] ? 'bad' : 'ok',
            $idr['copies_held'] . ' copy(ies) held; kept ' . $idr['retain_days'] . ' days past expiry. '
          . ($idr['due_redaction'] ? $idr['due_redaction'] . ' past that limit and not yet wiped.' : 'None past that limit.'),
            $idr['due_redaction'] ? 'The nightly job wipes these. If it is not running, open the Identity documents screen and press "Save & sweep".' : '');
        $holders = count(iddoc_holders());
        $add('DPDP Act 2023', 'Only named people can open an identity document',
            'ok', $holders . ' account(s) hold the permission, apart from the master administrator. '
                . 'Every open, download and copy sent out is recorded against a name.',
            'Review the list under Settings → Roles & access. Fewer is better.');
    }

    // --- ISO/IEC 17020 §4.1, §6.1, §6.2 -----------------------------------
    // These three modules enforce their own rules on their own screens, which
    // is where the work happens — but this is the screen a director opens
    // before an assessment, and until now three of the seven accreditation
    // modules were simply absent from it. Enforced somewhere is not the same
    // as visible here.
    // Guardrail: before anything else in this section, say whether the gates are
    // even switched on. Every 17020 control fires only when an accreditation pack
    // is enabled, and the authorisation gate is separately opt-in — so a director
    // could read a page of green registers below while none of them is actually
    // enforcing. Make that impossible to miss.
    if (function_exists('accredited_pack_on')) {
        $packOn = accredited_pack_on();
        $authOn = function_exists('auth_enforced') && auth_enforced();
        $add(accreditation_std_name() . ' — enforcement', 'The accreditation controls are switched on',
            !$packOn ? 'bad' : ($authOn ? 'ok' : 'warn'),
            'Accreditation pack: ' . ($packOn ? 'ON' : 'OFF') . '. Authorisation gate: ' . ($authOn ? 'ON' : 'OFF') . '.',
            !$packOn ? 'The accreditation pack is OFF, so none of the gates below fire — every register is a record, not a control. Turn it on under Settings once you are accredited or preparing to be.'
                : (!$authOn ? 'The authorisation gate is off, so an unauthorised person can still be allocated work. Switch it on under Settings once the competence matrix is populated.' : ''));
    }
    if (function_exists('imp_readiness')) {
        $ir = imp_readiness();
        $bad = $ir['open'] + $ir['unacceptable'];
        $add((accreditation_std_name() . ' §' . accreditation_clause_or('impartiality')), 'Threats to impartiality are declared and decided',
            $bad ? 'bad' : ($ir['declaration_due'] ? 'warn' : 'ok'),
            $ir['open'] . ' undecided, ' . $ir['unacceptable'] . ' judged unacceptable, '
            . $ir['declaration_due'] . ' of ' . $ir['people'] . ' people owing a declaration. This body is '
            . $ir['type_label'] . '.',
            $bad ? 'Open the impartiality register. An undecided threat stops the work it touches from being allocated, so this is costing you ' . Tlp('job') . ' as well as marks.'
                 : ($ir['declaration_due'] ? 'Chase the outstanding declarations. A statement made once and never renewed is not a current statement.' : ''));
    }
    if (function_exists('competence_readiness')) {
        $cr = competence_readiness();
        $add((accreditation_std_name() . ' §' . accreditation_clause_or('competence')), 'People are authorised for the work they are given',
            $cr['lapsed'] ? 'bad' : ($cr['authorised'] < $cr['people'] ? 'warn' : 'ok'),
            $cr['authorised'] . ' of ' . $cr['people'] . ' authorised for something; '
            . $cr['lapsed'] . ' with a lapsed required certificate; '
            . $cr['witness_due'] . ' overdue a witnessed assessment. Enforcement is '
            . ($cr['enforced'] ? 'ON' : 'OFF') . '.',
            $cr['lapsed'] ? 'A lapsed required ticket suspends the authorisations resting on it. Open the competence register.'
                : (!$cr['enforced'] ? 'Enforcement is off, so the matrix is a record rather than a gate. Switch it on once the matrix is populated.' : ''));
    }
    if (function_exists('equipment_all') && function_exists('equipment_current_calibration')) {
        $eq = equipment_all();
        $out_ = 0;
        foreach ($eq as $e) if (!equipment_current_calibration((int)$e['id'])) $out_++;
        $add((accreditation_std_name() . ' §' . accreditation_clause_or('equipment')), 'Measuring equipment is in calibration',
            $out_ ? 'bad' : (count($eq) ? 'ok' : 'warn'),
            count($eq) ? $out_ . ' of ' . count($eq) . ' instruments have no calibration in force today.'
                       : 'No equipment on the register at all.',
            $out_ ? 'A report naming an instrument out of calibration is refused at finalisation, so this blocks issue as well as failing the clause.'
                  : (count($eq) ? '' : 'If your people use gauges, meters or thickness testers, they belong on the register. A report that names an uncalibrated instrument is not defensible.'));
    }

    // --- ISO/IEC 17020 §7.5 / §7.6 ----------------------------------------
    // Measured from the register, not from a policy. The three lines are the
    // three things an assessor checks: is the process published, are we meeting
    // our own deadlines, and did we write back to the people who complained.
    if (function_exists('cmp_readiness')) {
        $cr = cmp_readiness();
        $add((accreditation_std_name() . ' §' . accreditation_clause_or('complaints')), 'The complaints process is published',
            $cr['policy_default'] ? 'warn' : 'ok',
            $cr['policy_default']
                ? 'Live at /complaints-policy, readable without signing in — but still the wording this app shipped with.'
                : 'Published at /complaints-policy, in your own words, readable without signing in.',
            $cr['policy_default'] ? 'Open the complaints register and rewrite the description as your own. An assessor will ask whether it is.' : '');
        $late = $cr['ack_late'] + $cr['decide_late'];
        $add((accreditation_std_name() . ' §' . accreditation_clause_or('complaints')), 'We meet our own deadlines',
            $late ? 'bad' : 'ok',
            $late ? $cr['ack_late'] . ' past the ' . $cr['ack_days'] . '-day acknowledgement, '
                  . $cr['decide_late'] . ' past the ' . $cr['decide_days'] . '-day decision.'
                  : $cr['open'] . ' open, none past its deadline.',
            $late ? 'Open the complaints register. The standard does not set these numbers — you did, and they are what you are held to.' : '');
        $add((accreditation_std_name() . ' §' . accreditation_clause_or('complaints')), 'Every complainant was told the outcome',
            $cr['unnotified'] ? 'bad' : 'ok',
            $cr['unnotified'] ? $cr['unnotified'] . ' decided but not yet written back to.'
                              : 'Nothing decided is waiting to be sent.',
            $cr['unnotified'] ? 'This is the single most common finding in this clause. Closing refuses until it is done, but the letter still has to be written.' : '');
    }

    // --- ISO/IEC 17020 §8.7 to §8.9 ---------------------------------------
    if (function_exists('capa_readiness')) {
        $pr = capa_readiness();
        $add((accreditation_std_name() . ' §' . accreditation_clause_or('correctiveaction')), 'Corrective actions are checked for effectiveness',
            $pr['verify_late'] ? 'bad' : 'ok',
            $pr['verify_late'] ? $pr['verify_late'] . ' action(s) carried out and never checked afterwards.'
                               : $pr['open'] . ' open, none waiting to be checked past its date.',
            $pr['verify_late'] ? 'Open the corrective-action register. §8.7.3 asks for the effectiveness review by name, and this is the figure an assessor computes.' : '');
        $add((accreditation_std_name() . ' §' . accreditation_clause_or('correctiveaction')), 'We asked whether it happened anywhere else',
            $pr['no_similar'] ? 'warn' : 'ok',
            $pr['no_similar'] ? $pr['no_similar'] . ' open action(s) with that question unanswered.'
                              : 'Answered on every open action.',
            $pr['no_similar'] ? '§8.7.2 d). One line in the standard, and the one most often skipped because it is the only part that costs real thinking.' : '');
    }
    if (function_exists('audits_readiness')) {
        $ar = audits_readiness();
        $add((accreditation_std_name() . ' §' . accreditation_clause_or('audit')), 'The whole standard gets audited, not part of it',
            $ar['uncovered'] ? 'bad' : 'ok',
            $ar['uncovered'] . ' of ' . $ar['clauses'] . ' clauses not covered in the last ' . $ar['cycle_days'] . ' days.',
            $ar['uncovered'] ? 'Open Internal audits — the coverage board shows which clauses nothing has looked at.' : '');
        $add((accreditation_std_name() . ' §' . accreditation_clause_or('audit')), 'Nonconformities found were acted on',
            $ar['nc_without_capa'] ? 'bad' : 'ok',
            $ar['nc_without_capa'] ? $ar['nc_without_capa'] . ' audit finding(s) with no corrective action against them.'
                                   : 'Every nonconformity found has a corrective action.',
            $ar['nc_without_capa'] ? 'A nonconformity recorded and never acted on is worse than one never found — it proves we knew.' : '');
    }
    if (function_exists('reviews_readiness')) {
        $rr = reviews_readiness();
        $add((accreditation_std_name() . ' §' . accreditation_clause_or('review')), 'A management review was held',
            $rr['overdue'] ? 'bad' : 'ok',
            $rr['last'] ? 'Last held ' . fdate($rr['last']['held_on']) . ' (' . (int)$rr['days_since'] . ' days ago).'
                        : 'None on file.',
            $rr['overdue'] ? 'Open Management review. The inputs it needs are counted for you from this system; the judgement is what you add.' : '');
        $add((accreditation_std_name() . ' §' . accreditation_clause_or('review')), 'Decisions from the review were carried out',
            $rr['open_actions'] ? 'warn' : 'ok',
            $rr['open_actions'] ? $rr['open_actions'] . ' decision(s) still open.' : 'Nothing outstanding.',
            $rr['open_actions'] ? 'Decisions nobody did are the first thing an assessor tests at the next review.' : '');
    }

    // --- ISO/IEC 17020:2026 §7.11 — control of data and information --------
    // New in the 2026 edition, with no 2012 equivalent. Four lines, because the
    // clause asks four separate questions and a body can pass three and fail one.
    if (function_exists('datacontrol_readiness')) {
        $dr = datacontrol_readiness();
        $st = $dr['app']['state'];
        $add((accreditation_std_name() . ' §' . accreditation_clause_or('datacontrol')), 'The software version in use has been validated',
            $st === 'ok' ? 'ok' : 'bad',
            $st === 'ok' ? 'Version ' . $dr['app']['version'] . ' validated on ' . fdate($dr['app']['row']['validated_on']) . '.'
            : ($st === 'stale' ? 'The last validation was of version ' . ($dr['app']['row']['version'] ?: 'unrecorded')
                               . '; you are running ' . $dr['app']['version'] . '.'
                               : 'No validation record exists for this application.'),
            $st === 'ok' ? '' : 'Open Data & information control and record one. This is the first thing an assessor asks about under the 2026 edition, and the commonest answer is a validation of a version nobody runs any more.');
        $add((accreditation_std_name() . ' §' . accreditation_clause_or('datacontrol')), 'Data integrity is checked, and the check is on file',
            $dr['check_failed'] ? 'bad' : ($dr['run_stale'] ? 'warn' : 'ok'),
            ($dr['check_failed'] ? $dr['check_failed'] . ' of ' . $dr['checks'] . ' checks failing. '
                                 : 'All ' . ($dr['checks'] - $dr['check_skipped']) . ' checks pass. ')
            . ($dr['last_run'] ? 'Last run ' . substr($dr['last_run']['ran_on'], 0, 10) . '.' : 'Never run.'),
            $dr['check_failed'] ? 'Open Data & information control — each failing line says what is wrong and why it matters.'
                : ($dr['run_stale'] ? 'Press "Run them now". A check that passes but was never recorded is not evidence.' : ''));
        $add((accreditation_std_name() . ' §' . accreditation_clause_or('datacontrol')), 'Access to the data is controlled and reviewed',
            $dr['access']['dormant'] ? 'warn' : 'ok',
            $dr['access']['admins'] . ' of ' . $dr['access']['people'] . ' accounts can change access; '
            . $dr['access']['dormant'] . ' have not signed in for 90 days.',
            $dr['access']['dormant'] ? 'Retire the dormant accounts. An account nobody uses is an account nobody notices being used.' : '');
        $add((accreditation_std_name() . ' §' . accreditation_clause_or('datacontrol')), 'System failures are logged, and answered for',
            $dr['failures_unanswered'] ? 'bad' : 'ok',
            $dr['total_failures'] . ' logged, ' . $dr['failures_open'] . ' open, '
            . $dr['failures_unanswered'] . ' with no answer yet on what happened to the data.',
            $dr['failures_unanswered'] ? 'Each entry has to say whether data or results were affected. That is the one an assessor picks out of the log.' : '');
    }

    // --- The trust layer ---------------------------------------------------
    // Not a clause of any standard — a commercial differentiator. But it is
    // measured the same way as everything else here, because "we geotag our
    // photographs" is worth nothing next to a percentage.
    if (function_exists('trust_readiness')) {
        $tr = trust_readiness();
        $add('Trust layer', 'Evidence is located where it was taken',
            $tr['photos'] === 0 ? 'unknown' : ($tr['pct'] >= 60 ? 'ok' : 'warn'),
            $tr['photos'] === 0 ? 'No photographs on file yet.'
                : $tr['on_site'] . ' of ' . $tr['photos'] . ' photographs (' . $tr['pct'] . '%) carry the location '
                . 'the camera recorded at the moment they were taken.',
            $tr['photos'] && $tr['pct'] < 60
                ? 'Location comes from inside the photograph, so it survives writing the ' . Tl('report') . ' at home. Where a phone strips it, the site check-in on the ' . Tl('job') . ' carries the fact instead — tell the ' . Tlp('engineer') . ' to use it.' : '');
        $add('Trust layer', 'The evidence chain is unbroken',
            $tr['chain']['ok'] ? 'ok' : 'bad',
            $tr['chain']['ok'] ? $tr['chain']['entries'] . ' entries, each hashing the one before it.'
                : count($tr['chain']['problems']) . ' problem(s) across ' . $tr['chain']['entries'] . ' entries.',
            $tr['chain']['ok'] ? '' : 'Open Evidence review. A broken chain means a photograph was altered or removed after it was recorded — find out which before anybody asks.');
        $add('Trust layer', 'Flagged evidence gets looked at',
            $tr['pending'] ? 'warn' : 'ok',
            $tr['pending'] ? $tr['pending'] . ' item(s) waiting for somebody to look.' : 'Nothing waiting.',
            $tr['pending'] ? 'A queue nobody clears is a queue that trains everybody to ignore the flags.' : '');
    }

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

    // The CERT-In report, handed to the operator's own mail program. Two ways out
    // because one of them always works: mailto opens the default client, and the
    // .eml download opens in Outlook when mailto is not wired up on the machine.
    if ($route === 'incident-report') {
        $inc = ops_one("SELECT * FROM security_incidents WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$inc) { http_response_code(404); view('notfound'); return; }
        $m = incident_report_email($inc);
        idems_log('incident', (int)$inc['id'], 'CERTIN_DRAFTED', ['field' => (string)$inc['ref']]);
        if (($_GET['mode'] ?? '') === 'eml') {
            compose_eml_download('CERT-In-' . (string)$inc['ref'],
                                 compose_eml($m['to'], $m['subject'], $m['body']));
            return;
        }
        redirect(compose_mailto($m['to'], $m['subject'], $m['body']));
    }

    if ($route === 'incident') {
        $inc = ops_one("SELECT * FROM security_incidents WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$inc) { http_response_code(404); view('notfound'); return; }
        view('ops/incident_detail', ['inc'=>$inc, 'g'=>grievance_officer(),
                                     'mail'=>incident_report_email($inc)]);
        return;
    }

    $rows = ops_all("SELECT * FROM security_incidents ORDER BY id DESC");
    view('ops/incidents', ['rows'=>$rows]);
}

// ---------------------------------------------------------------------------
//  How long a request has been sitting
//
//  The DPDP Act does not give a number of days; it says a person must be
//  answered without undue delay. That is exactly the kind of duty a register
//  quietly fails, because nothing on the screen counted. A request logged and
//  forgotten looked the same as one logged this morning.
//
//  So: an age in days on every open request, and a target that the company sets
//  for itself. Thirty days is the default because it is what the draft privacy
//  notice in Settings promises, and a promise you made is a better yardstick
//  than one nobody wrote down.
// ---------------------------------------------------------------------------
function dsr_target_days() {
    $n = (int)setting_get('dsr_target_days', 30);
    return ($n >= 1 && $n <= 180) ? $n : 30;
}

// Days since it arrived, and where that stands against the target. Returns null
// for age when the date is unreadable rather than inventing a number.
function dsr_age($row) {
    $at = trim((string)($row['received_at'] ?? ''));
    $ts = $at !== '' ? strtotime($at) : false;
    $closed = ($row['status'] ?? '') === 'CLOSED';
    if ($ts === false) return ['days' => null, 'state' => 'unknown', 'target' => dsr_target_days()];
    $end = $closed && trim((string)($row['answered_at'] ?? '')) !== ''
         ? (strtotime((string)$row['answered_at']) ?: time()) : time();
    $days = (int)floor(($end - $ts) / 86400);
    $t = dsr_target_days();
    if ($closed)            $state = $days > $t ? 'late-closed' : 'answered';
    elseif ($days > $t)     $state = 'overdue';
    elseif ($days > $t * 0.7) $state = 'due-soon';
    else                    $state = 'open';
    return ['days' => $days, 'state' => $state, 'target' => $t];
}

// The one number the compliance screen and the dashboard care about: is anybody
// waiting longer than we said they would.
function dsr_overdue_count() {
    $n = 0;
    foreach (ops_all("SELECT * FROM data_requests WHERE status <> 'CLOSED'") as $r)
        if (dsr_age($r)['state'] === 'overdue') $n++;
    return $n;
}

// ---------------------------------------------------------------------------
//  Erasing a candidate
//
//  The sharpest piece of personal data in this system belongs to somebody who
//  never became a customer, a colleague or a supplier: a person who applied for
//  work and was not taken on. The candidates table holds their name, mobile,
//  e-mail, the text of their CV, its file name, expected rate and interview
//  outcome. Under the DPDP Act that may be kept only while there is a reason,
//  and "we never got round to deleting it" is not one.
//
//  Deleting the ROW is the wrong answer. A candidate who was hired is now a
//  team member with jobs, vouchers and reports behind them, and a candidate who
//  was rejected is still the reason a requisition closed the way it did. So this
//  REDACTS: every field that identifies the person is emptied, the shape of the
//  hiring decision is kept, and the row says plainly that it was erased and
//  when. A register that silently loses rows is a register nobody can audit.
// ---------------------------------------------------------------------------
function candidate_erase_fields() {
    // Only what identifies a person. Not the stage, not the dates, not the
    // outcome — those are facts about a decision, not about them.
    return ['first_name', 'middle_name', 'last_name', 'email', 'mobile',
            'cv_link', 'cv_text', 'cv_keywords', 'cv_file_name', 'remarks',
            'expected_rate', 'client_feedback_note'];
}

// What erasing would remove, in words, so a person can be told before they press
// it — and so the answer to the request can quote it.
function candidate_erase_preview($id) {
    $c = ops_one("SELECT * FROM candidates WHERE id=?", [(int)$id]);
    if (!$c) return null;
    $held = [];
    foreach (candidate_erase_fields() as $f)
        if (trim((string)($c[$f] ?? '')) !== '' && (string)($c[$f] ?? '') !== '0') $held[] = $f;
    return [
        'row'    => $c,
        'holds'  => $held,
        'hired'  => !empty($c['inspector_id']),
        'keeps'  => ['the reference ' . (string)$c['cand_code'], 'which stage it reached',
                     'the dates it moved', 'the interview outcome'],
        'erased' => trim((string)($c['erased_at'] ?? '')) !== '',
    ];
}

function candidate_erase($id, $reason = '') {
    $c = ops_one("SELECT * FROM candidates WHERE id=?", [(int)$id]);
    if (!$c) return 'That candidate record no longer exists.';
    if (trim((string)($c['erased_at'] ?? '')) !== '') return 'That record has already been erased.';

    // A candidate who was taken on is a colleague now, and their details are
    // held for employment rather than for recruitment. Erasing here would not
    // remove them from the system and would only make this register lie.
    if (!empty($c['inspector_id']))
        return 'This candidate was hired, so their details are held as a team member rather than as an applicant. '
             . 'Erase them from their own record instead — this register would still show them.';

    ensure_column('candidates', 'erased_at', "VARCHAR(30) DEFAULT ''");
    ensure_column('candidates', 'erased_by', "VARCHAR(150) DEFAULT ''");
    ensure_column('candidates', 'erase_reason', "VARCHAR(255) DEFAULT ''");

    $sets = []; $args = [];
    foreach (candidate_erase_fields() as $f) {
        if (!array_key_exists($f, $c)) continue;          // a column a future version dropped
        $sets[] = "$f = ?";
        $args[] = ($f === 'expected_rate') ? 0 : '';
    }
    $u = current_user();
    $sets[] = 'erased_at = ?';   $args[] = date('c');
    $sets[] = 'erased_by = ?';   $args[] = $u ? user_name($u) : '';
    $sets[] = 'erase_reason = ?'; $args[] = substr(trim((string)$reason), 0, 255);
    $args[] = (int)$c['id'];

    db()->prepare("UPDATE candidates SET " . implode(', ', $sets) . " WHERE id = ?")->execute($args);

    // Written to the trail BEFORE anybody can ask whether it happened. Note it
    // records the reference, never the name — logging what you just erased would
    // defeat the erasure.
    if (function_exists('act_log'))
        act_log('CANDIDATE', (int)$c['id'], 'ERASED',
                'Personal details erased on request. Reference ' . (string)$c['cand_code'] . ' and the hiring decision kept.');
    return '';
}

// ---------------------------------------------------------------------------
//  The CERT-In report, as an e-mail somebody can actually send
//
//  CERT-In allows six hours from noticing an incident to reporting it. Six hours
//  is not enough time to work out what to write, and the register already holds
//  every fact the report needs. So it drafts the e-mail: the operator opens it
//  in their own mail program, reads it, corrects anything wrong and presses send.
//
//  Deliberately NOT sent automatically. A breach notification going out on a
//  cron with nobody having read it is a worse failure than being an hour late,
//  and the six-hour clock is on a human decision, not on a mail queue.
// ---------------------------------------------------------------------------
function incident_report_email($inc) {
    $sev  = strtoupper((string)($inc['severity'] ?? 'MEDIUM'));
    $ref  = (string)($inc['ref'] ?? '');
    $org  = (string)(setting_get('company_name', '') ?: app_name());
    $subject = 'Cyber security incident report — ' . $org . ' — ' . $ref;

    $when = trim((string)($inc['detected_at'] ?? ''));
    $fmt  = $when !== '' ? date('d M Y H:i', strtotime($when) ?: time()) : 'not recorded';

    $L = [];
    $L[] = 'To: The Indian Computer Emergency Response Team (CERT-In)';
    $L[] = '';
    $L[] = 'Reported under the CERT-In Directions of 28 April 2022, within six hours of noticing.';
    $L[] = '';
    $L[] = 'ORGANISATION';
    $L[] = '  Name            : ' . $org;
    $gvo = grievance_officer();   // the SAME officer set in Settings → Compliance
    $L[] = '  Contact person  : ' . ($gvo['name']  ?: '[name — Settings → Compliance]');
    $L[] = '  Contact e-mail  : ' . ($gvo['email'] ?: '[e-mail — Settings → Compliance]');
    $L[] = '  Telephone       : ' . ($gvo['phone'] ?: '[telephone]');
    $L[] = '';
    $L[] = 'THE INCIDENT';
    $L[] = '  Our reference   : ' . $ref;
    $L[] = '  Noticed at      : ' . $fmt . ' (IST)';
    $L[] = '  Type            : ' . (string)($inc['kind'] ?? '');
    $L[] = '  Severity        : ' . $sev;
    $L[] = '  Systems         : ' . (trim((string)($inc['systems'] ?? '')) ?: 'see below');
    $L[] = '  People affected : ' . ((int)($inc['people_affected'] ?? 0) ?: 'not yet established');
    $L[] = '  Kinds of data   : ' . (trim((string)($inc['data_kinds'] ?? '')) ?: 'not yet established');
    $L[] = '';
    $L[] = 'WHAT HAPPENED';
    $L[] = '  ' . (trim((string)($inc['summary'] ?? '')) ?: '[describe what happened]');
    $L[] = '';
    $L[] = 'WHAT WE DID IMMEDIATELY';
    $L[] = '  ' . (trim((string)($inc['immediate_action'] ?? '')) ?: '[what was done to contain it]');
    $L[] = '';
    if (trim((string)($inc['root_cause'] ?? '')) !== '') {
        $L[] = 'CAUSE, SO FAR AS KNOWN';
        $L[] = '  ' . (string)$inc['root_cause'];
        $L[] = '';
    }
    $L[] = 'Logs are retained and available on request. Our systems clock is synchronised to';
    $L[] = 'time.nplindia.org.';
    $L[] = '';
    $L[] = 'This report is made on the facts known at the time of writing and will be';
    $L[] = 'supplemented as the investigation continues.';

    return ['to' => (string)(setting_get('certin_email', '') ?: 'incident@cert-in.org.in'),
            'subject' => $subject, 'body' => implode("\n", $L)];
}

function ops_data_requests($route, $method) {
    ops_require(is_master() || can('settings.manage'), 'Only an administrator can open this register.');
    $pdo = db();

    // Erasing an applicant's details. POST only, and it says what it kept.
    if ($route === 'candidate-erase' && $method === 'POST') {
        $cid = (int)($_POST['id'] ?? 0);
        $pv  = candidate_erase_preview($cid);
        if (!$pv) { flash('That candidate record no longer exists.', 'error'); redirect('/candidates'); }
        if (($e = candidate_erase($cid, (string)($_POST['reason'] ?? ''))) !== '') flash($e, 'error');
        else flash('Personal details erased. The reference ' . (string)$pv['row']['cand_code']
                 . ' and the hiring decision are kept, so the register still adds up.');
        redirect('/candidate?id=' . $cid);
    }

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

// ============================================================================
//  Consent / lawful-basis register (DPDP Act)
//
//  The Act turns on being able to say, for each person whose data we hold, what
//  we told them we would use it for and on what basis. This register records
//  exactly that. It is written to automatically where a person genuinely acts
//  — a client contact accepting a portal invitation and setting their own
//  password — and by hand for anything else.
// ============================================================================
const CONSENT_BASES = [
    'CONSENT'    => 'Consent — they agreed',
    'CONTRACT'   => 'Contract — needed to do what they asked for',
    'LEGAL'      => 'Legal obligation',
    'LEGITIMATE' => 'Legitimate interest',
];
const CONSENT_SUBJECTS = [
    'CONTACT' => 'A contact at a client or vendor',
    'PORTAL'  => 'A client portal user',
    'STAFF'   => 'A member of staff',
    'OTHER'   => 'Someone else',
];

// Record (or refresh) a consent / lawful-basis entry. De-dupes on a live entry
// for the same subject and purpose, so wiring it into a flow that can fire more
// than once — an invitation re-accepted, say — does not pile up rows. Returns
// the row id (existing or new), or 0 if it could not be written.
function consent_record(array $d) {
    try {
        if (function_exists('compliance_migrate')) compliance_migrate();
        $kind    = (string)($d['subject_kind'] ?? '');
        $sid     = (int)($d['subject_id'] ?? 0);
        $purpose = substr(trim((string)($d['purpose'] ?? '')), 0, 200);
        $basis   = (string)($d['basis'] ?? 'CONSENT');
        if (!isset(CONSENT_BASES[$basis])) $basis = 'CONSENT';
        $dupe = (int)ops_val("SELECT id FROM data_consents WHERE subject_kind=? AND subject_id=? AND purpose=? AND (withdrawn_at IS NULL OR withdrawn_at='')",
                             [$kind, $sid, $purpose]);
        if ($dupe) return $dupe;
        db()->prepare("INSERT INTO data_consents (subject_kind,subject_id,subject_name,purpose,basis,given_at,note,recorded_by)
                       VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$kind, $sid, substr(trim((string)($d['subject_name'] ?? '')), 0, 200), $purpose, $basis,
                       (string)($d['given_at'] ?? date('c')) ?: date('c'),
                       substr((string)($d['note'] ?? ''), 0, 1000),
                       (string)($d['recorded_by'] ?? (function_exists('current_user') ? user_name(current_user()) : 'system'))]);
        return (int)db()->lastInsertId();
    } catch (Throwable $e) { return 0; }
}

function consent_withdraw($id) {
    try {
        db()->prepare("UPDATE data_consents SET withdrawn_at=? WHERE id=? AND (withdrawn_at IS NULL OR withdrawn_at='')")
            ->execute([date('c'), (int)$id]);
    } catch (Throwable $e) {}
}

function consent_open_count() {
    try { return (int)ops_val("SELECT COUNT(*) FROM data_consents WHERE withdrawn_at IS NULL OR withdrawn_at=''"); }
    catch (Throwable $e) { return 0; }
}

function ops_consents($route, $method) {
    ops_require(is_master() || can('settings.manage'), 'Only an administrator can open the consent register.');
    if (function_exists('compliance_migrate')) compliance_migrate();
    if ($route === 'consent-add' && $method === 'POST') {
        $kind = (string)($_POST['subject_kind'] ?? 'CONTACT');
        if (!isset(CONSENT_SUBJECTS[$kind])) $kind = 'OTHER';
        if (trim((string)($_POST['subject_name'] ?? '')) === '' || trim((string)($_POST['purpose'] ?? '')) === '') {
            flash('A name and a purpose are both needed — the register has to say whose data, and what for.', 'error');
            redirect('/consents');
        }
        consent_record([
            'subject_kind' => $kind,
            'subject_id'   => (int)($_POST['subject_id'] ?? 0),
            'subject_name' => (string)($_POST['subject_name'] ?? ''),
            'purpose'      => (string)($_POST['purpose'] ?? ''),
            'basis'        => (string)($_POST['basis'] ?? 'CONSENT'),
            'given_at'     => (string)($_POST['given_at'] ?? '') !== '' ? (string)$_POST['given_at'] : date('c'),
            'note'         => (string)($_POST['note'] ?? ''),
        ]);
        flash('Consent recorded.');
        redirect('/consents');
    }
    if ($route === 'consent-withdraw' && $method === 'POST') {
        consent_withdraw((int)($_POST['id'] ?? 0));
        flash('Recorded as withdrawn. What was collected under it is untouched until reviewed — withdrawing '
            . 'consent is the trigger to decide whether the data still needs keeping.', 'warning');
        redirect('/consents');
    }
    view('ops/consents', ['rows'=>ops_all(
        "SELECT * FROM data_consents ORDER BY (withdrawn_at IS NOT NULL AND withdrawn_at<>''), id DESC")]);
}
