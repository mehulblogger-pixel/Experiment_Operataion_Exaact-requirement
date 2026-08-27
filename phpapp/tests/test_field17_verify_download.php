<?php
// Field-finding #17 — the genuineness QR / verify page should also let the reader download the
// report, not just see a verdict. Approved decision: a public PDF on /verify, gated only by the
// 16-char verify code printed on the report, ISSUED reports only, every download logged.
t_section('Field #17 — the verify page offers a public report download');

if (function_exists('idems_seed_report_types')) idems_seed_report_types();
$rt = (int)ops_val("SELECT id FROM report_types LIMIT 1");
$pdo = db();

// An ISSUED report with a verify code, and a DRAFT that also carries a code.
$pdo->prepare("INSERT INTO report_docs (report_type_id,irn,title,status,finalized,finalized_by,finalized_at,verify_code,data,created_at,deleted)
               VALUES (?,?,?,?,1,?,?,?,?,?,0)")
   ->execute([$rt, 'IRN-F17-1', 'Test', 'ISSUED', 'Tester', date('c'), 'F17A-BBBB-CCCC-DDDD', '[]', date('c')]);
$issuedId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO report_docs (report_type_id,irn,title,status,finalized,verify_code,data,created_at,deleted)
               VALUES (?,?,?,?,0,?,?,?,0)")
   ->execute([$rt, 'IRN-F17-2', 'Draft', 'DRAFT', 'F17D-EEEE-FFFF-GGGG', '[]', date('c')]);
$draftId = (int)$pdo->lastInsertId();

$call = function ($code) { $_GET = ['c' => $code]; ob_start(); ops_verify_pdf(); return ob_get_clean(); };

// A genuine, issued report downloads as a real PDF.
$pdf = $call('F17A-BBBB-CCCC-DDDD');
t_ok(strncmp($pdf, '%PDF', 4) === 0, 'a genuine issued report downloads as a PDF');
t_ok(strlen($pdf) > 1000, 'the PDF has real content');

// A DRAFT must never be pulled by code (a draft carries no code that "verifies").
$d = $call('F17D-EEEE-FFFF-GGGG');
t_ok(strncmp($d, '%PDF', 4) !== 0 && stripos($d, 'No issued report') !== false,
     'a draft is refused — never downloadable by code');

// Unknown / missing codes are refused with a neutral message (no stack trace).
t_ok(stripos($call('ZZZZ-ZZZZ-ZZZZ-ZZZZ'), 'No issued report') !== false, 'an unknown code is refused');
t_ok(stripos($call(''), 'Missing verification code') !== false, 'a blank code is refused');

// Every successful download is logged (report content leaving the confidentiality boundary).
$logs = (int)ops_val("SELECT COUNT(*) FROM idems_audit WHERE action='PUBLIC_PDF' AND entity_id=?", [$issuedId]);
t_ok($logs >= 1, 'each public download is recorded on the audit trail');

// Wiring: the public route is dispatched BEFORE the login gate, and is licence-exempt.
$idx = file_get_contents(__DIR__ . '/../index.php');
$posV = strpos($idx, "\$route === 'verify-pdf'");
$posLogin = strpos($idx, "\nrequire_login();");
t_ok($posV !== false && $posV < $posLogin, 'the verify-pdf route is public (dispatched before require_login)');
$lk = file_get_contents(__DIR__ . '/../lib/licencekey.php');
t_ok(strpos($lk, "'verify-pdf'") !== false, 'verify-pdf keeps working even under a lapsed licence');

// The verify page shows the download link (only in the genuine branch) and its note is reconciled.
$view = file_get_contents(__DIR__ . '/../views/ops/verify.php');
t_ok(strpos($view, '/verify-pdf?c=<?= e($code) ?>') !== false, 'the verify page offers a "Download the report (PDF)" link');
t_ok(strpos($view, 'A verification page that leaks the report') === false,
     'the old "we never show the report" note is reconciled with the new download');

// Clean up (shared DB, no rollback).
$pdo->prepare("DELETE FROM idems_audit WHERE action='PUBLIC_PDF' AND entity_id=?")->execute([$issuedId]);
$pdo->prepare("DELETE FROM report_docs WHERE id IN (?,?)")->execute([$issuedId, $draftId]);
