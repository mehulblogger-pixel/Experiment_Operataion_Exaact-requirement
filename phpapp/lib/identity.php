<?php
// ============================================================================
//  Identity documents — passport, visa, ID — held for one stated reason
//
//  Why this exists at all. An engineer cannot walk into a refinery, a port, a
//  shipyard or a defence yard without a gate pass, and a gate pass is issued
//  against a document. So the coordinator ends up holding a scan of somebody's
//  passport whether the software helps or not — today it sits in a mailbox and
//  on a laptop, copied to whoever asked last. That is the actual risk, and it
//  is worse than holding it here.
//
//  The owner's instruction was short: "That is required for identity
//  verification." Good — that is a purpose, and under the DPDP Act 2023 a
//  purpose is the thing that makes the holding lawful. Everything in this file
//  follows from taking that purpose seriously rather than treating it as a
//  form of words:
//
//   · PURPOSE ON THE SCREEN. Not in a policy document nobody opens. The person
//     uploading reads what it will be used for, and the purpose is copied onto
//     the row, so a later change of policy cannot rewrite what was agreed at
//     the time.
//   · A SEPARATE PERMISSION. Being a manager does not get you a passport. Two
//     permissions, granted deliberately: one to look, one to hold.
//   · A RETENTION LIMIT THAT ACTUALLY RUNS. A date on every document, and a
//     redaction that empties the file and the number when that date passes.
//     The row survives — it is the evidence that identity WAS verified — but
//     the copy of the passport does not.
//   · A RECORD OF WHO LOOKED. Every open, every download, every reveal of a
//     full number, and every time a copy is sent to a site. This is the part
//     people skip, and it is the only part that turns "we are careful" into
//     something an auditor can check.
//
//  Two deliberate refusals:
//
//   · The number is masked by default. Seeing that a valid passport is on file
//     is what a coordinator needs ninety-nine times out of a hundred; reading
//     the number out is the hundredth, and it costs a click and a reason.
//   · No allocation is blocked on this. ISO/IEC 17020 does not ask for it, and
//     a body that cannot depute an engineer because a visa scan is missing has
//     built a compliance problem, not solved one. What the deputation screen
//     does is TELL the coordinator, which is the useful half.
// ============================================================================

// The kinds of document a site actually asks for. Company-editable — this is a
// seeded lookup, not a fixed list, because the mix differs by country and by
// industry (offshore wants a medical, defence wants police verification).
const IDDOC_KINDS = [
    'PASSPORT'   => 'Passport',
    'VISA'       => 'Visa / entry permit',
    'NATIONAL_ID'=> 'National identity card (e.g. Aadhaar)',
    'DRIVING_LIC'=> 'Driving licence',
    'WORK_PERMIT'=> 'Work permit',
    'GATE_PASS'  => 'Site / plant gate pass',
    'MEDICAL'    => 'Medical fitness certificate',
    'POLICE_VER' => 'Police verification',
    // Onboarding / KYC papers — held for the same person, especially for a
    // freelancer or an agency-provided engineer. Generic on purpose (a tenant
    // renames them under the 'identity_doc' list); most carry no expiry.
    'TAX_ID'     => 'Tax ID (e.g. PAN)',
    'DECLARATION'=> 'Signed declaration',
    'EDUCATION'  => 'Education / qualification certificate',
    'EXPERIENCE' => 'Experience certificate',
];

// Which kinds actually carry an expiry, and which carry a document number. A
// passport expires and has a number; a signed declaration or a degree does
// neither, so the register must not force them — otherwise the papers simply do
// not get filed. Everything not named here behaves the old way (expiry +
// number expected), so the gate-pass discipline is unchanged.
const IDDOC_NO_EXPIRY = ['TAX_ID', 'DECLARATION', 'EDUCATION', 'EXPERIENCE'];
const IDDOC_NO_NUMBER = ['DECLARATION', 'EDUCATION', 'EXPERIENCE'];
function iddoc_kind_expires($kind)   { return !in_array((string)$kind, IDDOC_NO_EXPIRY, true); }
function iddoc_kind_has_number($kind) { return !in_array((string)$kind, IDDOC_NO_NUMBER, true); }

// ---- What papers agency staff must have -------------------------------------
// The set a freelancer / sub-contractor is expected to have on file. Company
// editable (a setting), with a sensible default. Kept generic — a tenant that
// does not use PAN simply unticks it.
const PERSON_REQ_DOCS_DEFAULT = ['NATIONAL_ID', 'TAX_ID', 'DECLARATION', 'EDUCATION'];
function person_req_docs() {
    $raw = trim((string)setting_get('person_req_docs', ''));
    if ($raw === '__NONE__') return [];   // explicitly cleared
    if ($raw === '') return PERSON_REQ_DOCS_DEFAULT;
    $set = array_values(array_filter(array_map('trim', explode(',', $raw))));
    return $set ?: PERSON_REQ_DOCS_DEFAULT;
}

// Per-person document completeness against the required set. Presence-only (it
// never reads a number or a file), so it is safe to show wider than the
// register itself. A required document counts as present when a non-redacted
// one is on file and — for the kinds that expire — has not expired.
function person_docs_summary($personId, $kind = 'INSPECTOR') {
    $req  = person_req_docs();
    $opts = iddoc_kind_options();
    $byKind = [];
    foreach (iddoc_list($personId, $kind) as $d) $byKind[$d['doc_kind']][] = $d;
    $present = function ($k) use ($byKind) {
        foreach ($byKind[$k] ?? [] as $d) {
            if (!empty($d['redacted_at'])) continue;
            if (iddoc_kind_expires($k) && ($d['expires_on'] ?? '') !== '' && $d['expires_on'] < date('Y-m-d')) continue;
            return true;
        }
        return false;
    };
    $rows = []; $missing = [];
    foreach ($req as $k) {
        $ok = $present($k);
        $rows[$k] = ['label' => $opts[$k] ?? $k, 'ok' => $ok];
        if (!$ok) $missing[] = $opts[$k] ?? $k;
    }
    $total = count($req); $need = count($missing);
    return ['rows' => $rows, 'total' => $total, 'have' => $total - $need, 'need' => $need,
            'missing' => $missing, 'complete' => ($total > 0 && $need === 0),
            'total_docs' => array_sum(array_map('count', $byKind))];
}

// What every one of these is held for. Shown on the screen, and stamped onto
// the row at the moment of upload.
const IDDOC_PURPOSE = 'Identity verification for site access — issuing gate passes and '
    . 'satisfying a client’s or plant’s entry requirements.';

// Actions worth writing down. UPLOAD and REDACT are here too, so the log reads
// as the whole life of the document rather than only the looking at it.
const IDDOC_ACTIONS = [
    'UPLOAD'   => 'Filed',
    'VIEW'     => 'Opened the register entry',
    'DOWNLOAD' => 'Downloaded the file',
    'REVEAL'   => 'Read the full number',
    'SHARE'    => 'Sent a copy out',
    'REDACT'   => 'Redacted',
    'DELETE'   => 'Deleted',
];

// How long a document is kept after it stops being needed. Default two years,
// which is long enough to answer "who did you send to our plant in March" and
// short enough not to be a standing liability. Company-settable.
const IDDOC_RETAIN_DEFAULT = 730;

function identity_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = pk_clause();
    $pdo->exec("CREATE TABLE IF NOT EXISTS person_documents (
        id $pk, person_kind VARCHAR(20) DEFAULT 'INSPECTOR', person_id INT,
        doc_kind VARCHAR(30) DEFAULT 'PASSPORT',
        doc_number VARCHAR(80) DEFAULT '', number_last4 VARCHAR(8) DEFAULT '',
        issuing_authority VARCHAR(150) DEFAULT '', issuing_country VARCHAR(80) DEFAULT '',
        issued_on VARCHAR(20) DEFAULT '', expires_on VARCHAR(20) DEFAULT '',
        file_name VARCHAR(255) DEFAULT '', mime VARCHAR(100) DEFAULT '', file_data LONGTEXT,
        purpose VARCHAR(400) DEFAULT '',
        consent_on VARCHAR(20) DEFAULT '', consent_note VARCHAR(400) DEFAULT '',
        retain_until VARCHAR(20) DEFAULT '',
        redacted_at VARCHAR(30) DEFAULT '', redacted_by VARCHAR(150) DEFAULT '',
        note VARCHAR(400) DEFAULT '',
        uploaded_by VARCHAR(150) DEFAULT '', uploaded_at VARCHAR(30) DEFAULT '')");
    // Phase 2 §53 — the full document number, encrypted at rest (VARCHAR(80) is too
    // small for ciphertext, and file_data (LONGTEXT) holds the encrypted scan). The
    // legacy plaintext doc_number column is kept for backward compatibility and is
    // blanked as each row is migrated.
    if (function_exists('ensure_column')) ensure_column('person_documents', 'doc_number_enc', 'TEXT');
    // Who looked, when, from where, and — where it matters — why.
    $pdo->exec("CREATE TABLE IF NOT EXISTS person_document_access (
        id $pk, document_id INT, person_id INT,
        action VARCHAR(20) DEFAULT 'VIEW', username VARCHAR(150) DEFAULT '',
        reason VARCHAR(400) DEFAULT '', recipient VARCHAR(200) DEFAULT '',
        ip VARCHAR(60) DEFAULT '', at VARCHAR(30) DEFAULT '')");
}

function iddoc_missing_table(Throwable $e) {
    $m = $e->getMessage();
    return stripos($m, 'no such table') !== false || stripos($m, "doesn't exist") !== false;
}

// Phase 2 §53 — the full document number for a row: the encrypted column decrypted
// when present, else the legacy plaintext. One choke point, so every reader is
// consistent and no caller has to know about encryption.
function iddoc_number_read($row) {
    $enc = (string)($row['doc_number_enc'] ?? '');
    if ($enc !== '' && function_exists('app_decrypt')) return app_decrypt($enc);
    return (string)($row['doc_number'] ?? '');
}
// The raw scanned-document bytes for a row: file_data is stored base64, optionally
// wrapped in encryption. Decrypt (no-op for legacy) then base64-decode.
function iddoc_file_bytes($row) {
    $fd = (string)($row['file_data'] ?? '');
    if ($fd === '') return '';
    if (function_exists('app_decrypt')) $fd = app_decrypt($fd);
    return base64_decode($fd);
}
// Encrypt any identity documents still at rest in plaintext, once a key is set. Uses
// the encrypted column for the number and blanks the legacy plaintext. Idempotent and
// bounded; safe to run nightly. No-op when APP_ENCRYPTION_KEY is not configured.
function iddoc_encrypt_backfill($limit = 200) {
    if (!function_exists('app_enc_available') || !app_enc_available()) return 0;
    $n = 0;
    try {
        $rows = ops_all("SELECT id, doc_number, file_data FROM person_documents
                         WHERE (COALESCE(doc_number,'') <> '' OR (COALESCE(file_data,'') <> '' AND file_data NOT LIKE 'enc:v1:%'))
                         LIMIT " . max(1, (int)$limit)) ?: [];
        foreach ($rows as $r) {
            $encNum = ((string)($r['doc_number'] ?? '') !== '') ? app_encrypt((string)$r['doc_number']) : '';
            $fd = (string)($r['file_data'] ?? '');
            $encFd = ($fd !== '' && !app_is_encrypted($fd)) ? app_encrypt($fd) : $fd;
            db()->prepare("UPDATE person_documents SET doc_number_enc = CASE WHEN ?<>'' THEN ? ELSE doc_number_enc END, doc_number='', file_data=? WHERE id=?")
                ->execute([$encNum, $encNum, $encFd, (int)$r['id']]);
            $n++;
        }
    } catch (Throwable $e) {}
    return $n;
}
function iddoc_plaintext_count() {
    try {
        return (int)ops_val("SELECT COUNT(*) FROM person_documents
            WHERE COALESCE(doc_number,'') <> '' OR (COALESCE(file_data,'') <> '' AND file_data NOT LIKE 'enc:v1:%')");
    } catch (Throwable $e) { return 0; }
}

// ---- The company's own answers ---------------------------------------------
function iddoc_kind_options() { return lk_options_or('identity_doc', IDDOC_KINDS); }
function iddoc_retain_days() {
    $d = (int)setting_get('iddoc_retain_days', (string)IDDOC_RETAIN_DEFAULT);
    return $d > 0 ? $d : IDDOC_RETAIN_DEFAULT;
}
// The purpose as this company words it. Ours is the default; a company that
// writes its own keeps its own, and that is the wording stamped on new rows.
function iddoc_purpose() {
    $p = trim((string)setting_get('iddoc_purpose', ''));
    return $p !== '' ? $p : IDDOC_PURPOSE;
}

// ---- Permissions ------------------------------------------------------------
// Deliberately NOT is_admin_level(). A branch manager runs operations; that is
// not a reason to read a colleague's passport. The master admin still gets
// through can() — as they do everywhere — and every look is logged, which is
// the honest treatment of an account that can do anything.
function iddoc_can_view()   { return can('person.iddoc.view') || can('person.iddoc.manage'); }
function iddoc_can_manage() { return can('person.iddoc.manage'); }

// Every account that can open a document, apart from the master administrator.
// Written out because "who in this company can read a passport" is a question
// somebody will eventually be asked, and counting it by hand across role
// defaults and per-user overrides is how the wrong answer gets given.
function iddoc_holders($perm = 'person.iddoc.manage') {
    $out = [];
    foreach (ops_all("SELECT id, username, first_name, last_name, role, is_superuser, permissions
                      FROM users WHERE is_active=1 ORDER BY username") as $u) {
        if (!empty($u['is_superuser'])) continue;          // counted separately, and always logged
        $role = strtoupper((string)($u['role'] ?? 'ADMIN'));
        $csv  = trim((string)($u['permissions'] ?? ''));
        $perms = $csv !== '' ? array_values(array_filter(explode(',', $csv))) : role_perms($role);
        if (in_array($perm, $perms, true)) $out[] = $u;
    }
    return $out;
}

// ---- Masking ----------------------------------------------------------------
// Enough to tell two documents apart, not enough to use one.
function iddoc_mask($doc) {
    $l4 = (string)($doc['number_last4'] ?? '');
    if ($l4 === '') return '—';
    return '•••• ' . $l4;
}

// ---- Reading ----------------------------------------------------------------
// Never selects file_data. The bytes come out through one function, below, and
// that function writes to the access log. Keeping the two apart is what stops
// a future screen from quietly rendering somebody's passport in a list.
function iddoc_list($personId, $kind = 'INSPECTOR') {
    try {
        return ops_all("SELECT id, person_kind, person_id, doc_kind, number_last4,
                               issuing_authority, issuing_country, issued_on, expires_on,
                               file_name, mime, purpose, consent_on, consent_note, retain_until,
                               redacted_at, redacted_by, note, uploaded_by, uploaded_at
                        FROM person_documents
                        WHERE person_kind=? AND person_id=? ORDER BY doc_kind, id DESC",
                       [$kind, (int)$personId]);
    } catch (Throwable $e) { if (iddoc_missing_table($e)) return []; throw $e; }
}

function iddoc_row($id, $withFile = false) {
    $cols = $withFile ? '*' : 'id, person_kind, person_id, doc_kind, number_last4, issuing_authority,
                              issuing_country, issued_on, expires_on, file_name, mime, purpose,
                              consent_on, consent_note, retain_until, redacted_at, redacted_by,
                              note, uploaded_by, uploaded_at';
    try { return ops_one("SELECT $cols FROM person_documents WHERE id=?", [(int)$id]); }
    catch (Throwable $e) { if (iddoc_missing_table($e)) return null; throw $e; }
}

// A document that is still usable: not redacted, and not past its own expiry.
function iddoc_current($doc, $onDate = null) {
    $onDate = $onDate ?: date('Y-m-d');
    if (!empty($doc['redacted_at'])) return false;
    if (($doc['expires_on'] ?? '') !== '' && $doc['expires_on'] < $onDate) return false;
    return true;
}

// Does this person have a live identity document on file? This is the whole
// question a coordinator arranging a gate pass is actually asking.
function iddoc_verified($personId, $kind = 'INSPECTOR', $onDate = null) {
    foreach (iddoc_list($personId, $kind) as $d) if (iddoc_current($d, $onDate)) return true;
    return false;
}

// The advisory sentence for the deputation screen. Not a block — see the note
// at the top of this file.
function iddoc_note_for_person($personId, $kind = 'INSPECTOR') {
    $all = iddoc_list($personId, $kind);
    if (!$all) return 'No identity document is on file for site access.';
    if (!iddoc_verified($personId, $kind))
        return 'Every identity document on file has expired or been redacted.';
    return '';
}

// ---- Writing ----------------------------------------------------------------
function iddoc_log($documentId, $personId, $action, $reason = '', $recipient = '') {
    try {
        db()->prepare("INSERT INTO person_document_access (document_id,person_id,action,username,reason,recipient,ip,at)
                       VALUES (?,?,?,?,?,?,?,?)")
            ->execute([(int)$documentId, (int)$personId, $action, user_name(current_user()),
                       substr((string)$reason, 0, 400), substr((string)$recipient, 0, 200),
                       function_exists('client_ip') ? client_ip() : ($_SERVER['REMOTE_ADDR'] ?? ''),
                       date('c')]);
    } catch (Throwable $e) { /* the log must never be the thing that breaks a screen */ }
}

function iddoc_access_log($documentId = 0, $personId = 0, $limit = 200) {
    $w = ['1=1']; $a = [];
    if ($documentId) { $w[] = 'document_id=?'; $a[] = (int)$documentId; }
    if ($personId)   { $w[] = 'person_id=?';   $a[] = (int)$personId; }
    try {
        return ops_all("SELECT * FROM person_document_access WHERE " . implode(' AND ', $w)
                     . " ORDER BY id DESC LIMIT " . max(1, (int)$limit), $a);
    } catch (Throwable $e) { if (iddoc_missing_table($e)) return []; throw $e; }
}

// Module 26 — the company-wide access review a Data Protection Officer needs:
// every look / reveal / copy-out / redact ACROSS ALL PEOPLE, with the person's
// name and the document kind, filterable by action and date. Reads the same
// person_document_access log that is already written on every access — it exposes
// reasons, recipients, actors and IPs, but NEVER a document number (the log never
// stored one). So this widens nobody's sight of a number; it only lets the DPO see,
// in one place, what was already recorded one person at a time.
function iddoc_access_review($action = '', $from = '', $to = '', $limit = 500) {
    $w = ['1=1']; $a = [];
    if ($action !== '') { $w[] = 'x.action=?'; $a[] = $action; }
    if ($from !== '')   { $w[] = 'substr(x.at,1,10) >= ?'; $a[] = $from; }
    if ($to !== '')     { $w[] = 'substr(x.at,1,10) <= ?'; $a[] = $to; }
    try {
        return ops_all("SELECT x.*, i.name person_name, d.doc_kind
                        FROM person_document_access x
                        LEFT JOIN inspectors i ON i.id = x.person_id
                        LEFT JOIN person_documents d ON d.id = x.document_id
                        WHERE " . implode(' AND ', $w) . "
                        ORDER BY x.id DESC LIMIT " . max(1, (int)$limit), $a);
    } catch (Throwable $e) { if (iddoc_missing_table($e)) return []; throw $e; }
}

// A count-by-action summary over the same window, for the DPO screen header.
function iddoc_access_summary($from = '', $to = '') {
    $w = ['1=1']; $a = [];
    if ($from !== '') { $w[] = 'substr(at,1,10) >= ?'; $a[] = $from; }
    if ($to !== '')   { $w[] = 'substr(at,1,10) <= ?'; $a[] = $to; }
    $out = [];
    try {
        foreach (ops_all("SELECT action, COUNT(*) n FROM person_document_access WHERE "
                        . implode(' AND ', $w) . " GROUP BY action", $a) as $r)
            $out[(string)$r['action']] = (int)$r['n'];
    } catch (Throwable $e) { if (!iddoc_missing_table($e)) throw $e; }
    return $out;
}

// When this copy stops being kept. Counted from whichever comes later — the
// document's own expiry or the day it was filed — so a passport valid for
// another eight years is not thrown away next year.
function iddoc_retain_until($expiresOn, $from = null) {
    $from = $from ?: date('Y-m-d');
    $base = ($expiresOn !== '' && $expiresOn > $from) ? $expiresOn : $from;
    return date('Y-m-d', strtotime($base . ' +' . iddoc_retain_days() . ' days'));
}

function iddoc_add($personId, $post, $file, $kind = 'INSPECTOR') {
    $docKind = (string)($post['doc_kind'] ?? '');
    $valid = iddoc_kind_options();
    if (!isset($valid[$docKind])) return 'Choose which kind of document this is.';
    $number = trim((string)($post['doc_number'] ?? ''));
    // A passport is useless without its number; a signed declaration or a degree
    // certificate has none, so it is only required for the kinds that carry one.
    if ($number === '' && iddoc_kind_has_number($docKind))
        return 'The document number is what makes the record useful — enter it.';
    $bytes = ($file && ($file['tmp_name'] ?? '') !== '' && is_uploaded_file($file['tmp_name']))
        ? (string)file_get_contents($file['tmp_name']) : '';
    if ($bytes !== '' && ($why = upload_reject_reason($bytes, $file['name'] ?? '', $file['type'] ?? '')) !== '')
        return $why;
    // A scan of a passport with no expiry date is a copy nobody can ever retire
    // on the document's own terms, so it is asked for — but a PAN, a declaration
    // or a degree does not expire, so those are not forced (retention then runs
    // from the day it was filed instead).
    $expires = (string)($post['expires_on'] ?? '');
    if ($expires === '' && iddoc_kind_expires($docKind))
        return 'Enter the expiry date. Without it this copy can never be retired on time.';
    // Phase 2 §53 — encrypt at rest when a key is configured. The number goes into the
    // encrypted column (plaintext left blank); number_last4 stays plaintext for masking;
    // the scan is base64 then encrypted. With no key the behaviour is exactly as before.
    $encOn   = function_exists('app_enc_available') && app_enc_available();
    $numPlain = $encOn ? '' : substr($number, 0, 80);
    $numEnc   = ($encOn && $number !== '') ? app_encrypt($number) : '';
    $fileB64  = $bytes !== '' ? base64_encode($bytes) : '';
    $fileStore = ($encOn && $fileB64 !== '') ? app_encrypt($fileB64) : $fileB64;
    db()->prepare("INSERT INTO person_documents
        (person_kind,person_id,doc_kind,doc_number,doc_number_enc,number_last4,issuing_authority,issuing_country,
         issued_on,expires_on,file_name,mime,file_data,purpose,consent_on,consent_note,retain_until,note,uploaded_by,uploaded_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$kind, (int)$personId, $docKind, $numPlain, $numEnc, substr($number, -4),
                   substr(trim((string)($post['issuing_authority'] ?? '')), 0, 150),
                   substr(trim((string)($post['issuing_country'] ?? '')), 0, 80),
                   (string)($post['issued_on'] ?? ''), $expires,
                   $bytes !== '' ? substr((string)($file['name'] ?? 'document'), 0, 255) : '',
                   $bytes !== '' ? (string)($file['type'] ?? '') : '',
                   $fileStore,
                   substr(iddoc_purpose(), 0, 400),
                   (string)($post['consent_on'] ?? date('Y-m-d')),
                   substr(trim((string)($post['consent_note'] ?? '')), 0, 400),
                   iddoc_retain_until($expires),
                   substr(trim((string)($post['note'] ?? '')), 0, 400),
                   user_name(current_user()), date('c')]);
    iddoc_log((int)db()->lastInsertId(), (int)$personId, 'UPLOAD', $valid[$docKind]);
    return '';
}

// Redaction, not deletion. The file and the number go; the row stays, so the
// company can still show that identity was verified on a given date without
// still holding the passport it was verified against.
function iddoc_redact($id, $why = 'Retention period reached') {
    $d = iddoc_row($id);
    if (!$d || !empty($d['redacted_at'])) return false;
    db()->prepare("UPDATE person_documents SET doc_number='', doc_number_enc='', file_data=NULL, file_name='', mime='',
                   redacted_at=?, redacted_by=? WHERE id=?")
        ->execute([date('c'), user_name(current_user()) ?: 'system', (int)$id]);
    iddoc_log((int)$id, (int)$d['person_id'], 'REDACT', $why);
    return true;
}

// Documents whose retention date has passed and which still hold a copy.
function iddoc_due_redaction($onDate = null) {
    $onDate = $onDate ?: date('Y-m-d');
    try {
        return ops_all("SELECT id, person_id, doc_kind, retain_until FROM person_documents
                        WHERE (redacted_at IS NULL OR redacted_at='')
                          AND retain_until <> '' AND retain_until < ?", [$onDate]);
    } catch (Throwable $e) { if (iddoc_missing_table($e)) return []; throw $e; }
}

// Run from the nightly job. A retention limit that nobody runs is a sentence in
// a policy, which is exactly what this module is meant to replace.
function iddoc_run_retention($onDate = null) {
    $n = 0;
    foreach (iddoc_due_redaction($onDate) as $d)
        if (iddoc_redact((int)$d['id'], 'Retention period reached (' . $d['retain_until'] . ').')) $n++;
    return $n;
}

// ---- How this looks across the company -------------------------------------
function iddoc_readiness() {
    $people = ops_all("SELECT id, name FROM inspectors WHERE status='ACTIVE'");
    $with = 0; $expiring = 0; $expired = 0; $held = 0;
    $soon = date('Y-m-d', strtotime('+60 days'));
    $today = date('Y-m-d');
    foreach ($people as $p) {
        $docs = iddoc_list($p['id']);
        if (!$docs) continue;
        if (iddoc_verified($p['id'])) $with++;
        foreach ($docs as $d) {
            if (!empty($d['redacted_at'])) continue;
            if ($d['file_name'] !== '') $held++;
            if ($d['expires_on'] === '') continue;
            if ($d['expires_on'] < $today) $expired++;
            elseif ($d['expires_on'] <= $soon) $expiring++;
        }
    }
    return [
        'people' => count($people), 'verified' => $with,
        'without' => count($people) - $with,
        'expiring' => $expiring, 'expired' => $expired,
        'copies_held' => $held,
        'due_redaction' => count(iddoc_due_redaction()),
        'retain_days' => iddoc_retain_days(),
    ];
}

// ---- Screens ----------------------------------------------------------------
function ops_identity($route, $method) {
    // The agency-staff roster is presence-only (who has which papers, not the
    // papers themselves), so a coordinator or manager can see it without the
    // identity-document permission. Filing a document still needs that permission.
    if ($route === 'agency-staff') {
        ops_require(is_coordinator_level() || iddoc_can_view(),
            'The agency-staff roster is for coordinators and managers.');
        $fAgency = (int)($_GET['agency'] ?? 0);
        $onlyGap = ($_GET['gap'] ?? '') === '1';
        // Freelancers and sub-contractors — the people engaged through an agency.
        $sql = "SELECT id, name, emp_code, staff_kind, agency_id, agency_name, trade_id
                FROM inspectors WHERE status='ACTIVE' AND COALESCE(staff_kind,'ASSET') IN ('FREELANCER','SUBCON')";
        $args = [];
        if ($fAgency) { $sql .= " AND agency_id=?"; $args[] = $fAgency; }
        $sql .= " ORDER BY COALESCE(agency_name,''), name";
        $groups = [];
        foreach (ops_all($sql, $args) as $p) {
            $sum = person_docs_summary((int)$p['id']);
            if ($onlyGap && $sum['complete']) continue;
            $key = $p['agency_name'] !== '' ? $p['agency_name'] : '— no agency set —';
            $groups[$key][] = $p + ['sum' => $sum, 'trade' => trade_label($p['trade_id'] ?? 0)];
        }
        ksort($groups);
        view('ops/agency_staff', [
            'groups' => $groups,
            'agencies' => agencies_list(),
            'fAgency' => $fAgency,
            'onlyGap' => $onlyGap,
            'reqDocs' => person_req_docs(),
            'kinds' => iddoc_kind_options(),
            'canFile' => iddoc_can_view(),
        ]);
        return true;
    }

    ops_require(iddoc_can_view(),
        'Identity documents are held under a separate permission. Ask your administrator for '
      . '“See identity documents” if your work needs it.');

    if ($route === 'identity') {
        $pid = (int)($_GET['i'] ?? 0);
        $people = ops_all("SELECT id, name, emp_code, status FROM inspectors WHERE status='ACTIVE' ORDER BY name");
        $rows = [];
        foreach ($people as $p) {
            $docs = iddoc_list($p['id']);
            $rows[] = ['p' => $p, 'n' => count($docs), 'ok' => iddoc_verified($p['id'])];
        }
        $person = $pid ? ops_one("SELECT * FROM inspectors WHERE id=?", [$pid]) : null;
        if ($person) iddoc_log(0, $pid, 'VIEW', 'Opened the register for ' . $person['name']);
        view('ops/identity', [
            'ready'   => iddoc_readiness(),
            'people'  => $rows,
            'person'  => $person,
            'docs'    => $person ? iddoc_list($pid) : [],
            'log'     => $person ? iddoc_access_log(0, $pid, 60) : [],
            'kinds'   => iddoc_kind_options(),
            'purpose' => iddoc_purpose(),
            'reqDocs' => person_req_docs(),
            'canManage' => iddoc_can_manage(),
            'reveal'  => (int)($_GET['reveal'] ?? 0),
            'revealed'=> iddoc_flash_number(),
        ]);
        return true;
    }

    ops_require(iddoc_can_manage(),
        'You may see that a document is on file. Adding, revealing or removing one needs '
      . '“Hold identity documents”.');

    // Module 26 — the DPO's company-wide access review. Read-only over the log that
    // is already written on every access; shows no document number.
    if ($route === 'iddoc-access') {
        $action = trim((string)($_GET['action'] ?? ''));
        $from = trim((string)($_GET['from'] ?? ''));
        $to   = trim((string)($_GET['to'] ?? ''));
        view('ops/identity_access', [
            'rows'    => iddoc_access_review($action, $from, $to),
            'summary' => iddoc_access_summary($from, $to),
            'holders' => iddoc_holders(),
            'actions' => IDDOC_ACTIONS,
            'f'       => ['action' => $action, 'from' => $from, 'to' => $to],
        ]);
        return true;
    }

    if ($route === 'iddoc-file') {
        $d = iddoc_row((int)($_GET['id'] ?? 0), true);
        if (!$d) { http_response_code(404); view('notfound'); return true; }
        if (empty($d['file_data'])) {
            flash(!empty($d['redacted_at'])
                ? 'That copy was redacted on ' . substr((string)$d['redacted_at'], 0, 10) . '. The record of the check remains.'
                : 'No file was uploaded with that entry.', 'error');
            redirect('/identity?i=' . (int)$d['person_id']);
        }
        iddoc_log((int)$d['id'], (int)$d['person_id'], 'DOWNLOAD',
                  trim((string)($_GET['why'] ?? '')) ?: 'Not stated');
        send_uploaded_file(iddoc_file_bytes($d),
                           $d['file_name'] ?: 'document', $d['mime'] ?: 'application/octet-stream');
        return true;
    }

    if ($route === 'iddoc-add' && $method === 'POST') {
        $pid = (int)($_POST['person_id'] ?? 0);
        $why = iddoc_add($pid, $_POST, $_FILES['doc_file'] ?? null);
        flash($why !== '' ? $why : 'Filed, and held for ' . iddoc_retain_days()
              . ' days past expiry. Everyone who opens it after this is recorded.',
              $why !== '' ? 'error' : 'success');
        redirect('/identity?i=' . $pid);
    }

    // Reading a number out loud. Costs a reason, and the reason is kept.
    if ($route === 'iddoc-reveal' && $method === 'POST') {
        $d = iddoc_row((int)($_POST['id'] ?? 0), true);
        if (!$d) redirect('/identity');
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($reason === '') {
            flash('Say why the full number is needed. The reason is kept with the record.', 'error');
            redirect('/identity?i=' . (int)$d['person_id']);
        }
        if (!empty($d['redacted_at'])) {
            flash('That number was redacted on ' . substr((string)$d['redacted_at'], 0, 10) . '.', 'error');
            redirect('/identity?i=' . (int)$d['person_id']);
        }
        iddoc_log((int)$d['id'], (int)$d['person_id'], 'REVEAL', $reason);
        iddoc_flash_number(iddoc_number_read($d), (int)$d['id']);
        redirect('/identity?i=' . (int)$d['person_id'] . '&reveal=' . (int)$d['id']);
    }

    // Sending a copy to a plant's security desk is a disclosure. It is the one
    // thing that most often goes unrecorded and most often needs answering for,
    // so it gets its own button rather than living in somebody's sent items.
    if ($route === 'iddoc-share' && $method === 'POST') {
        $d = iddoc_row((int)($_POST['id'] ?? 0));
        if (!$d) redirect('/identity');
        $to = trim((string)($_POST['recipient'] ?? ''));
        if ($to === '') {
            flash('Name who it went to — a company, a site, or a person.', 'error');
            redirect('/identity?i=' . (int)$d['person_id']);
        }
        iddoc_log((int)$d['id'], (int)$d['person_id'], 'SHARE',
                  trim((string)($_POST['reason'] ?? '')) ?: iddoc_purpose(), $to);
        flash('Recorded: a copy went to ' . $to . '.');
        redirect('/identity?i=' . (int)$d['person_id']);
    }

    if ($route === 'iddoc-redact' && $method === 'POST') {
        $d = iddoc_row((int)($_POST['id'] ?? 0));
        if (!$d) redirect('/identity');
        iddoc_redact((int)$d['id'], trim((string)($_POST['reason'] ?? '')) ?: 'Redacted on request.');
        flash('The copy and the number are gone. The record that identity was checked remains.');
        redirect('/identity?i=' . (int)$d['person_id']);
    }

    if ($route === 'iddoc-retention' && $method === 'POST') {
        $d = (int)($_POST['iddoc_retain_days'] ?? 0);
        if ($d > 0) setting_set('iddoc_retain_days', (string)$d);
        $p = trim((string)($_POST['iddoc_purpose'] ?? ''));
        setting_set('iddoc_purpose', $p);
        // Which documents agency staff must have on file (the checklist). An
        // empty tick-set is stored as a sentinel so it means "none required"
        // rather than "fall back to the default".
        if (array_key_exists('person_req_docs', $_POST)) {
            $valid = array_keys(iddoc_kind_options());
            $picked = array_values(array_intersect($valid, (array)($_POST['person_req_docs'] ?? [])));
            setting_set('person_req_docs', $picked ? implode(',', $picked) : '__NONE__');
        }
        $n = iddoc_run_retention();
        flash('Retention set to ' . iddoc_retain_days() . ' days past expiry.'
            . ($n ? ' ' . $n . ' document(s) past that limit were redacted now.' : ''));
        redirect('/identity');
    }

    redirect('/identity');
    return true;
}

// A revealed number is shown once, on the next screen, and never stored in the
// URL or the page history. The session is the only place it lives, and it is
// taken out the moment it is read.
function iddoc_flash_number($number = null, $docId = 0) {
    if ($number !== null) { $_SESSION['iddoc_reveal'] = ['id' => (int)$docId, 'n' => $number]; return ''; }
    $v = $_SESSION['iddoc_reveal'] ?? null;
    unset($_SESSION['iddoc_reveal']);
    return $v;
}

// ============================================================================
//  What a site demands before it will let our engineer through the gate
//
//  The register above holds passports, visas, gate passes and medicals under a
//  stated purpose, with a retention limit and a log of every look. What it did
//  NOT do was any work: nothing ever consulted it. An engineer could be deputed
//  to a refinery that requires a medical fitness certificate and a police
//  verification, hold neither, and find out at the gate — which is a wasted
//  day, a missed inspection and an invoice nobody can raise.
//
//  So a client — or one particular site of that client — can now say which
//  documents it demands, and the allocation checks them.
//
//  Three decisions:
//
//   1. **A mandatory requirement blocks, but a manager may override with a
//      recorded reason.** Exactly the pattern the certificate gate already
//      uses, and for the same reason: refusing outright teaches people to
//      back-date a document to get past the gate, which destroys the evidence
//      the register exists to hold. A recorded override is a fact an assessor
//      can read; a forged date is not.
//   2. **It checks validity on the DAY OF THE VISIT, not today.** A passport
//      valid this morning and expired by the inspection date is not a document
//      somebody holds. Some sites also want a margin — "valid for 30 days
//      beyond the visit" — so the requirement carries its own lead time.
//   3. **A document nobody has recorded and a document that has expired are
//      different failures**, and are reported differently, because the fix is
//      different: one is "get it from them", the other is "it has to be renewed".
// ============================================================================

function sitedoc_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = pk_clause();
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_doc_requirements (
        id $pk, partner_id INT, site_address_id INT NULL,
        doc_kind VARCHAR(30) DEFAULT 'GATE_PASS',
        is_mandatory INT DEFAULT 1,
        valid_days_after INT DEFAULT 0,
        note VARCHAR(400) DEFAULT '',
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // Why an engineer was sent without one. Kept on the deputation, because
    // that is the record an assessor reads.
    ensure_column('jobs', 'sitedoc_override_note', "VARCHAR(400) DEFAULT ''");
    ensure_column('jobs', 'sitedoc_override_by', "VARCHAR(150) DEFAULT ''");
}

// What this client, at this site, asks for. A requirement with no site applies
// to every site of that client, so a company-wide rule is one row.
function sitedoc_required($partnerId, $siteAddressId = null) {
    sitedoc_migrate();
    if (!$partnerId) return [];
    $w = 'partner_id = ?'; $a = [(int)$partnerId];
    if ($siteAddressId) { $w .= ' AND (site_address_id IS NULL OR site_address_id = ?)'; $a[] = (int)$siteAddressId; }
    else                { $w .= ' AND site_address_id IS NULL'; }
    try { return ops_all("SELECT * FROM site_doc_requirements WHERE $w ORDER BY is_mandatory DESC, doc_kind", $a); }
    catch (Throwable $e) { if (iddoc_missing_table($e)) return []; throw $e; }
}

// Everything wrong with sending THIS person to THIS site on THIS date.
// Returns ['block' => [...], 'warn' => [...]] — sentences, not codes, because
// they are shown to the coordinator who has to fix them.
function sitedoc_check($inspectorId, $partnerId, $siteAddressId, $visitDate = null) {
    $out = ['block' => [], 'warn' => []];
    if (!$inspectorId || !$partnerId) return $out;
    $reqs = sitedoc_required($partnerId, $siteAddressId);
    if (!$reqs) return $out;
    $visit = $visitDate ?: date('Y-m-d');
    $kinds = iddoc_kind_options();
    $held  = [];
    foreach (iddoc_list((int)$inspectorId) as $d) $held[(string)$d['doc_kind']][] = $d;

    foreach ($reqs as $r) {
        $kind  = (string)$r['doc_kind'];
        $label = $kinds[$kind] ?? $kind;
        $bucket = !empty($r['is_mandatory']) ? 'block' : 'warn';
        $mine = $held[$kind] ?? [];
        if (!$mine) {
            $out[$bucket][] = "no $label is on file for this engineer";
            continue;
        }
        // Valid on the day of the visit, plus any margin the site insists on.
        $needUntil = (int)$r['valid_days_after'] > 0
            ? date('Y-m-d', strtotime($visit . ' +' . (int)$r['valid_days_after'] . ' days'))
            : $visit;
        $ok = false; $bestExpiry = '';
        foreach ($mine as $d) {
            if (!empty($d['redacted_at'])) continue;
            $exp = (string)($d['expires_on'] ?? '');
            if ($exp === '' || $exp >= $needUntil) { $ok = true; break; }
            if ($exp > $bestExpiry) $bestExpiry = $exp;
        }
        if (!$ok) {
            // "Expired" and "missing" are different failures with different fixes.
            $out[$bucket][] = $bestExpiry !== ''
                ? "their $label expires " . fdate($bestExpiry) . ', before '
                  . ((int)$r['valid_days_after'] > 0
                     ? 'the ' . (int)$r['valid_days_after'] . ' days this site requires beyond the visit'
                     : 'the inspection date')
                : "their $label has been withdrawn from the register";
        }
    }
    return $out;
}

// One sentence for the allocation screen, or '' when there is nothing to say.
function sitedoc_block_message(array $check) {
    if (!$check['block']) return '';
    return 'This site will not admit the engineer: ' . implode('; ', $check['block']) . '.';
}

// Documents running out, for the nightly chase. Looks ahead far enough that a
// visa can actually be renewed in time.
function sitedoc_expiring($days = 45, $today = null) {
    sitedoc_migrate();
    $today = $today ?: date('Y-m-d');
    $until = date('Y-m-d', strtotime($today . " +$days days"));
    try {
        return ops_all(
            "SELECT d.id, d.person_id, d.doc_kind, d.expires_on, i.name inspector_name, i.email
             FROM person_documents d
             LEFT JOIN inspectors i ON i.id = d.person_id
             WHERE d.person_kind='INSPECTOR' AND (d.redacted_at IS NULL OR d.redacted_at='')
               AND d.expires_on <> '' AND d.expires_on >= ? AND d.expires_on <= ?
             ORDER BY d.expires_on", [$today, $until]);
    } catch (Throwable $e) { if (iddoc_missing_table($e)) return []; throw $e; }
}

// ---- The requirements register ---------------------------------------------
function ops_sitedocs($route, $method) {
    ops_require(iddoc_can_view() || can('mod.clients.view') || is_master_of(['identity','clients']),
                'You cannot open the site-document requirements.');
    sitedoc_migrate();

    $canEdit = iddoc_can_manage() || can('mod.clients.edit') || is_master_of(['identity','clients']);

    if ($route === 'site-docs-add' || $route === 'site-docs-delete') {
        ops_require($canEdit, 'You cannot change what a site requires.');
        if ($method === 'POST' && $route === 'site-docs-add') {
            $pid = (int)($_POST['partner_id'] ?? 0);
            if (!$pid) { flash('Choose which client the requirement belongs to.', 'error'); redirect('/site-docs'); }
            db()->prepare("INSERT INTO site_doc_requirements
                (partner_id,site_address_id,doc_kind,is_mandatory,valid_days_after,note,created_by,created_at)
                VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$pid,
                    ($_POST['site_address_id'] ?? '') !== '' ? (int)$_POST['site_address_id'] : null,
                    (string)($_POST['doc_kind'] ?? 'GATE_PASS'),
                    !empty($_POST['is_mandatory']) ? 1 : 0,
                    max(0, (int)($_POST['valid_days_after'] ?? 0)),
                    substr(trim((string)($_POST['note'] ?? '')), 0, 400),
                    user_name(current_user()), date('c')]);
            flash('Requirement added. It is checked whenever somebody is deputed to that client.');
        }
        if ($method === 'POST' && $route === 'site-docs-delete') {
            db()->prepare("DELETE FROM site_doc_requirements WHERE id=?")->execute([(int)($_POST['id'] ?? 0)]);
            flash('Requirement removed.');
        }
        redirect('/site-docs');
    }

    $rows = ops_all(
        "SELECT r.*, bp.display_name, bp.legal_name, a.label site_label, a.city site_city
         FROM site_doc_requirements r
         LEFT JOIN business_partners bp ON bp.id = r.partner_id
         LEFT JOIN partner_addresses a ON a.id = r.site_address_id
         ORDER BY COALESCE(bp.display_name, bp.legal_name), r.is_mandatory DESC, r.doc_kind");
    view('ops/site_docs', [
        'rows' => $rows, 'canEdit' => $canEdit,
        'clients' => ops_all("SELECT id, display_name, legal_name FROM business_partners
                              WHERE is_client=1 AND status='ACTIVE' ORDER BY COALESCE(display_name, legal_name)"),
        'kinds' => iddoc_kind_options(),
        'expiring' => sitedoc_expiring(45),
    ]);
    return true;
}
