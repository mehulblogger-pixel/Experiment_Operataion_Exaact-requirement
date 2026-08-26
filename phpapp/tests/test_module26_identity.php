<?php
// Module 26 — Identity documents (DPDP). A company-wide DPO access-review over the existing
// access log (who looked / revealed / copied out — never a document number), plus the first
// coverage of the security-critical behaviours (masking, reveal logging, redaction preserves
// the record).
t_section('Module 26 — identity DPO access review + safeguards');

$lib = file_get_contents(__DIR__ . '/../lib/identity.php');

t_ok(function_exists('iddoc_access_review'), 'iddoc_access_review() exists');
t_ok(function_exists('iddoc_access_summary'), 'iddoc_access_summary() exists');

// ---- masking (never coverage-tested before) ----
t_eq(iddoc_mask(['number_last4' => '4321']), '•••• 4321', 'a number is masked to dots + last 4');
t_eq(iddoc_mask(['number_last4' => '']), '—', 'a document with no number shows a dash, not empty dots');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('identity_migrate')) identity_migrate();
    $pdo = db();

    $pdo->prepare("INSERT INTO inspectors (name, status) VALUES ('Doc Person','ACTIVE')")->execute();
    $pid = (int)$pdo->lastInsertId();

    // File a document with a full number; the list reader must never surface the full number.
    if (function_exists('iddoc_add')) {
        iddoc_add($pid, ['doc_kind' => 'PASSPORT', 'doc_number' => 'P1234567', 'expires_on' => '2030-01-01',
                         'purpose' => 'Site access', 'consent_on' => '2026-01-01'], null);
    }
    $list = iddoc_list($pid);
    t_ok(!empty($list), 'the document is on file');
    $doc = $list[0];
    t_ok(!array_key_exists('doc_number', $doc), 'the list reader never selects the full document number');
    t_ok(($doc['number_last4'] ?? '') === '4567', 'only the last 4 of the number are stored/exposed for identification');
    $docId = (int)$doc['id'];

    // A reveal is logged with its reason (simulate the handler's log call).
    iddoc_log($docId, $pid, 'REVEAL', 'gate pass for site X');
    iddoc_log($docId, $pid, 'SHARE', 'sent to client security', 'security@client.test');

    // Redaction preserves the record but wipes the number + file.
    if (function_exists('iddoc_redact')) iddoc_redact($docId);
    $after = iddoc_row($docId, true);
    t_ok($after !== null, 'the record survives redaction (evidence identity was checked)');
    t_ok(trim((string)($after['doc_number'] ?? '')) === '' && !empty($after['redacted_at']),
         'redaction wipes the number and stamps redacted_at, keeping the row');

    // ---- the DPO company-wide review ----
    $all = iddoc_access_review();
    $acts = array_column($all, 'action');
    t_ok(in_array('REVEAL', $acts, true) && in_array('SHARE', $acts, true), 'the review sees reveals and copy-outs across everyone');
    $rev = null; foreach ($all as $r) if ($r['action'] === 'REVEAL') { $rev = $r; break; }
    t_ok($rev && ($rev['person_name'] ?? '') === 'Doc Person', 'the review names whose document was accessed');
    t_ok($rev && !array_key_exists('doc_number', $rev), 'the DPO review never carries a document number');

    // Filter by action.
    $onlyShare = iddoc_access_review('SHARE');
    t_ok(count($onlyShare) >= 1 && count(array_filter($onlyShare, fn($r) => $r['action'] !== 'SHARE')) === 0,
         'filtering by action returns only that action');

    // Summary counts by action.
    $sum = iddoc_access_summary();
    t_ok(($sum['REVEAL'] ?? 0) >= 1 && ($sum['SHARE'] ?? 0) >= 1, 'the summary counts each action');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- preservation ----
t_ok(strpos($lib, 'function iddoc_can_manage') !== false, 'the manage gate is unchanged');
t_ok(strpos($lib, "\$route === 'iddoc-access'") !== false, 'the DPO review is a manage-gated route');
t_ok(!preg_match("/iddoc_access_review[\\s\\S]{0,400}doc_number/", $lib), 'the review query never selects a document number');
t_ok(!preg_match("/can\\('person\\.iddoc\\.[a-z]+2/", $lib), 'Module 26 introduces no new permission constant');
