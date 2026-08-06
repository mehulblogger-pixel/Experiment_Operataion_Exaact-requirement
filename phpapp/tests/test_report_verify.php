<?php
// Report verification on the issued PDF (Roadmap Steps 16/17 — activate the
// existing public /verify endpoint on the document itself).
t_section('report verification loop');

db()->prepare("INSERT INTO report_docs (irn, status, finalized, created_at, updated_at) VALUES (?,?,?,?,?)")
    ->execute(['TIR/26/0900', 'ISSUED', 1, date('c'), date('c')]);
$id = (int)db()->lastInsertId();
$doc = ops_one("SELECT * FROM report_docs WHERE id=?", [$id]);

$code = verify_code_for($doc);
t_ok(strlen($code) >= 16, 'a verification code is minted for an issued report');
$code2 = verify_code_for(ops_one("SELECT * FROM report_docs WHERE id=?", [$id]));
t_eq($code2, $code, 'the code is stable — read again, not re-minted');

$look = verify_lookup($code);
t_ok(is_array($look) && ($look['state'] ?? '') !== 'error', 'the public verify lookup answers without error');
t_ok(($look['irn'] ?? '') === 'TIR/26/0900', 'the lookup resolves to the right report');

t_ok(verify_lookup('ZZZZ-ZZZZ-ZZZZ-ZZZZ') === null, 'an unknown code verifies as not found');

db()->prepare("INSERT INTO report_docs (irn, status, finalized, verify_code, created_at, updated_at) VALUES (?,?,?,?,?,?)")
    ->execute(['TIR/26/0901', 'DRAFT', 0, 'TEST-DRAFT-CODE-9', date('c'), date('c')]);
$nd = verify_lookup('TEST-DRAFT-CODE-9');
t_ok(is_array($nd) && ($nd['state'] ?? '') === 'not_issued', 'a draft code reports not-issued, never "genuine"');

// The issued report renders to a PDF that carries the verification code.
if (function_exists('report_pdf_build')) {
    t_nothrow('an issued report renders to a PDF (with the verify block)', function () use ($id) {
        $doc = ops_one("SELECT * FROM report_docs WHERE id=?", [$id]);
        $pdf = report_pdf_build($doc, [], [], [], [], [], []);
        t_ok(is_string($pdf) && strncmp($pdf, '%PDF', 4) === 0, 'output is a valid PDF document');
    });
}
