<?php
// ============================================================================
//  Confidentiality — the clause with nothing behind it
//
//  ISO/IEC 17020 §4.2 requires the inspection body to be responsible, through
//  legally enforceable commitments, for the management of all information
//  obtained during inspection, and to tell the client in advance what it
//  intends to place in the public domain. The 2026 edition keeps it and leans
//  harder on information obtained from sources OTHER than the client.
//
//  Before this file, the word "confidential" appeared in three sentences of
//  prose in the audit module and nowhere else. There was no undertaking signed
//  by anybody, no record of what a particular client had additionally
//  imposed, and nowhere to record a breach. An assessor asking "show me the
//  confidentiality commitments for the four people who worked on this job"
//  would have been shown nothing at all.
//
//  Three objects, because they answer three different questions:
//
//   1. **The undertaking** — what each of our own people has signed, and when.
//      §4.2 says legally enforceable, which in practice means a signed
//      document with a date, renewed when the wording changes. Includes
//      subcontractors, because §6.3 makes their obligations ours.
//   2. **The client's own terms** — an NDA a client made us sign, with its
//      own expiry and its own rules. It is not the same as our standard
//      undertaking and it usually outlives the contract.
//   3. **The breach register** — what got out, to whom, what was done. This is
//      the one nobody builds, and the one an assessor asks for. A breach
//      raises a nonconformity, because that is what it is.
//
//  Two rules that make it more than a filing cabinet:
//
//   - **An undertaking that has lapsed is reported as lapsed**, not quietly
//     treated as signed. The register's whole value is telling you who is NOT
//     covered.
//   - **A breach cannot be closed without saying whether the affected party
//     was told.** Deciding not to tell them is a legitimate answer; not having
//     decided is not.
// ============================================================================

const CONF_UNDERTAKING_KINDS = [
    'EMPLOYEE'    => 'Employee confidentiality undertaking',
    'SUBCON'      => 'Subcontractor / agency confidentiality undertaking',
    'CONTRACTOR'  => 'Individual contractor undertaking',
    'VISITOR'     => 'Visitor or observer undertaking',
];

const CONF_BREACH_KINDS = [
    'WRONG_RECIPIENT' => 'Sent to the wrong person',
    'UNAUTHORISED'    => 'Seen by somebody not entitled to see it',
    'LOSS'            => 'Device, file or document lost or stolen',
    'PUBLICATION'     => 'Published or disclosed without authority',
    'VERBAL'          => 'Disclosed in conversation',
    'SYSTEM'          => 'Through a fault in a system',
    'LEGAL'           => 'Required by law or a regulator (not a fault)',
];

const CONF_BREACH_STATUS = [
    'OPEN'       => 'Open',
    'CONTAINED'  => 'Contained',
    'CLOSED'     => 'Closed',
];

function conf_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = pk_clause();
    // What one of our people has signed. Never overwritten on renewal — a new
    // row, so "what was in force in March" is answerable.
    $pdo->exec("CREATE TABLE IF NOT EXISTS confidentiality_undertakings (
        id $pk, person_kind VARCHAR(20) DEFAULT 'INSPECTOR', person_id INT NULL,
        person_name VARCHAR(150) DEFAULT '',
        kind VARCHAR(20) DEFAULT 'EMPLOYEE',
        wording_ref VARCHAR(120) DEFAULT '',
        signed_on VARCHAR(20) DEFAULT '', valid_to VARCHAR(20) DEFAULT '',
        file_name VARCHAR(255) DEFAULT '', mime VARCHAR(100) DEFAULT '', file_data LONGTEXT,
        note VARCHAR(500) DEFAULT '',
        recorded_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // An NDA the CLIENT imposed on us. Different from our own undertaking, and
    // it usually outlives the contract it came with.
    $pdo->exec("CREATE TABLE IF NOT EXISTS client_ndas (
        id $pk, partner_id INT, title VARCHAR(200) DEFAULT '',
        signed_on VARCHAR(20) DEFAULT '', valid_to VARCHAR(20) DEFAULT '',
        survives_months INT DEFAULT 0,
        terms TEXT,
        file_name VARCHAR(255) DEFAULT '', mime VARCHAR(100) DEFAULT '', file_data LONGTEXT,
        recorded_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // What got out. The register nobody builds and every assessor asks for.
    $pdo->exec("CREATE TABLE IF NOT EXISTS confidentiality_breaches (
        id $pk, ref VARCHAR(40) DEFAULT '',
        kind VARCHAR(20) DEFAULT 'WRONG_RECIPIENT',
        partner_id INT NULL, job_id INT NULL, office_id INT NULL,
        happened_on VARCHAR(20) DEFAULT '', discovered_on VARCHAR(20) DEFAULT '',
        what_got_out TEXT, who_saw_it VARCHAR(400) DEFAULT '',
        by_whom VARCHAR(150) DEFAULT '',
        containment TEXT,
        party_told VARCHAR(10) DEFAULT '', party_told_on VARCHAR(20) DEFAULT '',
        party_told_note VARCHAR(1000) DEFAULT '',
        regulator_told VARCHAR(10) DEFAULT '', regulator_told_note VARCHAR(500) DEFAULT '',
        ncr_id INT NULL, ncr_ref VARCHAR(40) DEFAULT '',
        status VARCHAR(20) DEFAULT 'OPEN',
        closed_on VARCHAR(20) DEFAULT '', closed_by VARCHAR(150) DEFAULT '',
        raised_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
}

function conf_missing_table(Throwable $e) {
    $m = $e->getMessage();
    return stripos($m, 'no such table') !== false || stripos($m, "doesn't exist") !== false;
}
function conf_try($fn, $fallback = []) {
    try { return $fn(); } catch (Throwable $e) { if (!conf_missing_table($e)) throw $e; return $fallback; }
}

function conf_can_view()   { return can('mod.confidentiality.view') || can('mod.identity.view') || is_master(); }
function conf_can_manage() { return can('mod.confidentiality.edit') || can('mod.identity.edit') || is_master(); }

// ---- Undertakings ----------------------------------------------------------
// In force on a date: signed on or before it, and not expired by it.
function conf_undertaking_live($u, $onDate = null) {
    $onDate = $onDate ?: date('Y-m-d');
    if (trim((string)$u['signed_on']) === '' || $u['signed_on'] > $onDate) return false;
    if (trim((string)$u['valid_to']) !== '' && $u['valid_to'] < $onDate) return false;
    return true;
}

function conf_undertakings($personId = 0, $kind = 'INSPECTOR') {
    conf_migrate();
    $w = '1=1'; $a = [];
    if ($personId) { $w = 'person_kind=? AND person_id=?'; $a = [$kind, (int)$personId]; }
    return conf_try(fn() => ops_all(
        "SELECT * FROM confidentiality_undertakings WHERE $w ORDER BY signed_on DESC, id DESC", $a));
}

// The point of the register: who is NOT covered. Everyone active, with what
// they have signed and whether it still stands.
function conf_coverage($onDate = null) {
    conf_migrate();
    $onDate = $onDate ?: date('Y-m-d');
    $out = [];
    $held = [];
    foreach (conf_undertakings() as $u) $held[$u['person_kind'] . ':' . (int)$u['person_id']][] = $u;
    foreach (conf_try(fn() => ops_all("SELECT id, name, email FROM inspectors ORDER BY name")) as $p) {
        $mine = $held['INSPECTOR:' . (int)$p['id']] ?? [];
        $live = null;
        foreach ($mine as $u) if (conf_undertaking_live($u, $onDate)) { $live = $u; break; }
        $lapsed = !$live && $mine ? $mine[0] : null;
        $out[] = ['id' => (int)$p['id'], 'name' => $p['name'], 'email' => $p['email'],
                  'live' => $live, 'lapsed' => $lapsed,
                  'state' => $live ? 'ok' : ($lapsed ? 'lapsed' : 'none')];
    }
    return $out;
}

function conf_readiness() {
    $c = conf_coverage();
    return [
        'people'  => count($c),
        'covered' => count(array_filter($c, fn($x) => $x['state'] === 'ok')),
        'lapsed'  => count(array_filter($c, fn($x) => $x['state'] === 'lapsed')),
        'none'    => count(array_filter($c, fn($x) => $x['state'] === 'none')),
    ];
}

// ---- The client's own terms -------------------------------------------------
function conf_ndas($partnerId = 0) {
    conf_migrate();
    $w = '1=1'; $a = [];
    if ($partnerId) { $w = 'n.partner_id = ?'; $a = [(int)$partnerId]; }
    return conf_try(fn() => ops_all(
        "SELECT n.*, bp.display_name, bp.legal_name FROM client_ndas n
         LEFT JOIN business_partners bp ON bp.id = n.partner_id
         WHERE $w ORDER BY COALESCE(bp.display_name, bp.legal_name), n.id DESC", $a));
}

// An NDA usually survives the agreement that carried it. This is the date the
// obligation actually ends, which is the one that matters.
function conf_nda_obligation_ends($n) {
    $to = trim((string)$n['valid_to']);
    $sur = (int)$n['survives_months'];
    if ($to === '') return '';
    return $sur > 0 ? date('Y-m-d', strtotime($to . ' +' . $sur . ' months')) : $to;
}

// ---- Breaches ---------------------------------------------------------------
function conf_breach_ref_next() {
    $y = date('Y');
    $n = (int)ops_val("SELECT COUNT(*) FROM confidentiality_breaches WHERE ref LIKE ?", ["CB-$y-%"]);
    do { $n++; $ref = sprintf('CB-%s-%03d', $y, $n); }
    while ((int)ops_val("SELECT COUNT(*) FROM confidentiality_breaches WHERE ref=?", [$ref]) > 0);
    return $ref;
}

function conf_breaches($filter = 'open') {
    conf_migrate();
    [$sw, $sa] = scope_office_clause('b.office_id');
    $f = $filter === 'closed' ? "b.status='CLOSED'" : ($filter === 'all' ? '1=1' : "b.status <> 'CLOSED'");
    return conf_try(fn() => ops_all(
        "SELECT b.*, bp.display_name, bp.legal_name, o.name office_name, j.job_code
         FROM confidentiality_breaches b
         LEFT JOIN business_partners bp ON bp.id = b.partner_id
         LEFT JOIN offices o ON o.id = b.office_id
         LEFT JOIN jobs j ON j.id = b.job_id
         WHERE $sw AND ($f) ORDER BY (b.status<>'CLOSED') DESC, b.discovered_on DESC, b.id DESC", $sa));
}

function conf_breach_row($id) {
    conf_migrate();
    return conf_try(fn() => ops_one(
        "SELECT b.*, bp.display_name, bp.legal_name, o.name office_name, j.job_code
         FROM confidentiality_breaches b
         LEFT JOIN business_partners bp ON bp.id = b.partner_id
         LEFT JOIN offices o ON o.id = b.office_id
         LEFT JOIN jobs j ON j.id = b.job_id WHERE b.id=?", [(int)$id]), null) ?: null;
}

function conf_breach_create(array $b) {
    conf_migrate();
    $what = trim((string)($b['what_got_out'] ?? ''));
    if ($what === '') return ['err' => 'Say what actually got out.'];
    $ref = conf_breach_ref_next();
    $office = ($b['office_id'] ?? '') !== '' ? (int)$b['office_id'] : null;
    $jobId  = ($b['job_id'] ?? '') !== '' ? (int)$b['job_id'] : null;
    if (!$office && $jobId) {
        $j = ops_one("SELECT executing_office_id FROM jobs WHERE id=?", [$jobId]);
        if ($j) $office = $j['executing_office_id'] !== null ? (int)$j['executing_office_id'] : null;
    }
    db()->prepare("INSERT INTO confidentiality_breaches
        (ref,kind,partner_id,job_id,office_id,happened_on,discovered_on,what_got_out,who_saw_it,by_whom,
         containment,status,raised_by,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?, 'OPEN', ?,?)")
        ->execute([$ref,
            isset(CONF_BREACH_KINDS[$b['kind'] ?? '']) ? $b['kind'] : 'WRONG_RECIPIENT',
            ($b['partner_id'] ?? '') !== '' ? (int)$b['partner_id'] : null,
            $jobId, $office,
            (string)($b['happened_on'] ?? ''),
            (string)($b['discovered_on'] ?? date('Y-m-d')),
            $what,
            substr(trim((string)($b['who_saw_it'] ?? '')), 0, 400),
            substr(trim((string)($b['by_whom'] ?? '')), 0, 150),
            (string)($b['containment'] ?? ''),
            user_name(current_user()), date('c')]);
    $id = (int)db()->lastInsertId();

    // A confidentiality breach IS a nonconformity. Raising it automatically is
    // the difference between a register and a diary.
    if (function_exists('ncr_create')) {
        $ncrId = ncr_create([
            'source' => 'INTERNAL', 'source_note' => 'Confidentiality breach ' . $ref,
            'job_id' => $jobId, 'partner_id' => ($b['partner_id'] ?? '') !== '' ? (int)$b['partner_id'] : null,
            'office_id' => $office,
            'title' => 'Confidentiality breach ' . $ref,
            'clause' => '4.2',
            // Losing control of a client's information is not a minor lapse.
            'severity' => 'MAJOR',
            'description' => (CONF_BREACH_KINDS[$b['kind'] ?? ''] ?? '') . "\n\n" . $what
                           . "\n\nSeen by: " . (string)($b['who_saw_it'] ?? '—'),
            'containment' => (string)($b['containment'] ?? ''),
        ]);
        if ($ncrId) {
            $ref2 = (string)ops_val("SELECT ref FROM nonconformities WHERE id=?", [(int)$ncrId]);
            db()->prepare("UPDATE confidentiality_breaches SET ncr_id=?, ncr_ref=? WHERE id=?")
                ->execute([(int)$ncrId, $ref2, $id]);
        }
    }
    return ['id' => $id, 'ref' => $ref];
}

// Everything standing between this breach and being closed.
function conf_breach_close_missing($b) {
    $m = [];
    if (trim((string)($b['containment'] ?? '')) === '')
        $m[] = 'what was done to contain it';
    // Deciding NOT to tell them is a legitimate answer. Not having decided is not.
    if (trim((string)($b['party_told'] ?? '')) === '')
        $m[] = 'whether the affected party was told — "no, because…" is an answer, blank is not';
    if (($b['party_told'] ?? '') === 'NO' && trim((string)($b['party_told_note'] ?? '')) === '')
        $m[] = 'the reason for not telling them';
    if (!empty($b['ncr_id']) && function_exists('ncr_row')) {
        $n = ncr_row((int)$b['ncr_id']);
        if ($n && ($n['status'] ?? '') !== 'CLOSED')
            $m[] = 'its nonconformity ' . ($n['ref'] ?? '') . ' to be closed first';
    }
    return $m;
}

// ---- Screens ----------------------------------------------------------------
function ops_confidentiality($route, $method) {
    ops_require(conf_can_view(), 'You cannot open the confidentiality register.');
    conf_migrate();
    $canEdit = conf_can_manage();

    if ($route === 'confidentiality') {
        $tab = $_GET['t'] ?? 'people';
        view('ops/confidentiality', [
            'tab' => $tab, 'canEdit' => $canEdit,
            'coverage' => conf_coverage(), 'ready' => conf_readiness(),
            'ndas' => conf_ndas(), 'breaches' => conf_breaches($_GET['f'] ?? 'open'),
            'f' => $_GET['f'] ?? 'open',
            'inspectors' => ops_all("SELECT id, name FROM inspectors ORDER BY name"),
            'clients' => ops_all("SELECT id, display_name, legal_name FROM business_partners
                                  WHERE is_client=1 ORDER BY COALESCE(display_name, legal_name)"),
        ]);
        return true;
    }

    ops_require($canEdit, 'You cannot change the confidentiality register.');

    if ($route === 'conf-undertaking-add' && $method === 'POST') {
        $pid = (int)($_POST['person_id'] ?? 0);
        if (!$pid) { flash('Choose whose undertaking this is.', 'error'); redirect('/confidentiality'); }
        $name = (string)ops_val("SELECT name FROM inspectors WHERE id=?", [$pid]);
        $file = $_FILES['file'] ?? null;
        $bytes = ($file && ($file['tmp_name'] ?? '') !== '' && is_uploaded_file($file['tmp_name']))
            ? (string)file_get_contents($file['tmp_name']) : '';
        if ($bytes !== '' && function_exists('upload_reject_reason')
            && ($why = upload_reject_reason($bytes, $file['name'] ?? '', $file['type'] ?? '')) !== '') {
            flash($why, 'error'); redirect('/confidentiality');
        }
        db()->prepare("INSERT INTO confidentiality_undertakings
            (person_kind,person_id,person_name,kind,wording_ref,signed_on,valid_to,file_name,mime,file_data,note,recorded_by,created_at)
            VALUES ('INSPECTOR',?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$pid, $name,
                isset(CONF_UNDERTAKING_KINDS[$_POST['kind'] ?? '']) ? $_POST['kind'] : 'EMPLOYEE',
                substr(trim((string)($_POST['wording_ref'] ?? '')), 0, 120),
                (string)($_POST['signed_on'] ?? date('Y-m-d')),
                (string)($_POST['valid_to'] ?? ''),
                substr((string)($file['name'] ?? ''), 0, 255), (string)($file['type'] ?? ''),
                $bytes === '' ? '' : base64_encode($bytes),
                substr(trim((string)($_POST['note'] ?? '')), 0, 500),
                user_name(current_user()), date('c')]);
        flash('Undertaking recorded for ' . $name . '.');
        redirect('/confidentiality?t=people');
    }

    if ($route === 'conf-nda-add' && $method === 'POST') {
        $pid = (int)($_POST['partner_id'] ?? 0);
        if (!$pid) { flash('Choose which client the agreement is with.', 'error'); redirect('/confidentiality?t=ndas'); }
        $file = $_FILES['file'] ?? null;
        $bytes = ($file && ($file['tmp_name'] ?? '') !== '' && is_uploaded_file($file['tmp_name']))
            ? (string)file_get_contents($file['tmp_name']) : '';
        db()->prepare("INSERT INTO client_ndas
            (partner_id,title,signed_on,valid_to,survives_months,terms,file_name,mime,file_data,recorded_by,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$pid, substr(trim((string)($_POST['title'] ?? '')), 0, 200),
                (string)($_POST['signed_on'] ?? ''), (string)($_POST['valid_to'] ?? ''),
                max(0, (int)($_POST['survives_months'] ?? 0)),
                (string)($_POST['terms'] ?? ''),
                substr((string)($file['name'] ?? ''), 0, 255), (string)($file['type'] ?? ''),
                $bytes === '' ? '' : base64_encode($bytes),
                user_name(current_user()), date('c')]);
        flash('Agreement recorded.');
        redirect('/confidentiality?t=ndas');
    }

    if ($route === 'conf-breach-add' && $method === 'POST') {
        $r = conf_breach_create($_POST);
        if (!empty($r['err'])) { flash($r['err'], 'error'); redirect('/confidentiality?t=breaches'); }
        flash('Breach ' . $r['ref'] . ' recorded, and a nonconformity was raised against it automatically.');
        redirect('/conf-breach?id=' . $r['id']);
    }

    if ($route === 'conf-breach') {
        $b = conf_breach_row($_GET['id'] ?? $_POST['id'] ?? 0);
        if (!$b) { http_response_code(404); view('notfound'); return true; }
        if ($method === 'POST') {
            db()->prepare("UPDATE confidentiality_breaches SET containment=?, party_told=?, party_told_on=?,
                           party_told_note=?, regulator_told=?, regulator_told_note=?,
                           status=CASE WHEN status='OPEN' THEN 'CONTAINED' ELSE status END WHERE id=?")
                ->execute([(string)($_POST['containment'] ?? ''),
                    in_array($_POST['party_told'] ?? '', ['YES','NO'], true) ? $_POST['party_told'] : '',
                    (string)($_POST['party_told_on'] ?? ''),
                    substr(trim((string)($_POST['party_told_note'] ?? '')), 0, 1000),
                    in_array($_POST['regulator_told'] ?? '', ['YES','NO'], true) ? $_POST['regulator_told'] : '',
                    substr(trim((string)($_POST['regulator_told_note'] ?? '')), 0, 500),
                    (int)$b['id']]);
            flash('Saved.');
            redirect('/conf-breach?id=' . (int)$b['id']);
        }
        view('ops/conf_breach', ['b' => $b, 'missing' => conf_breach_close_missing($b), 'canEdit' => $canEdit,
                                 'ncr' => (!empty($b['ncr_id']) && function_exists('ncr_row')) ? ncr_row((int)$b['ncr_id']) : null]);
        return true;
    }

    if ($route === 'conf-breach-close' && $method === 'POST') {
        $b = conf_breach_row($_POST['id'] ?? 0);
        if (!$b) { http_response_code(404); view('notfound'); return true; }
        $miss = conf_breach_close_missing($b);
        if ($miss) { flash('Not yet — still needed: ' . implode('; ', $miss) . '.', 'error'); redirect('/conf-breach?id=' . (int)$b['id']); }
        db()->prepare("UPDATE confidentiality_breaches SET status='CLOSED', closed_on=?, closed_by=? WHERE id=?")
            ->execute([date('Y-m-d'), user_name(current_user()), (int)$b['id']]);
        flash('Breach ' . $b['ref'] . ' closed.');
        redirect('/conf-breach?id=' . (int)$b['id']);
    }
    redirect('/confidentiality');
}
