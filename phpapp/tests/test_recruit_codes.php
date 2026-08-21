<?php
// Structured, meaningful requisition & candidate codes:
//   REQ/<office>/<client>/<YYMM>/C<client-no>/O<office-no>  and  <req>-CV<nn>
t_section('structured requisition & candidate IDs');
if (function_exists('req_migrate')) req_migrate();

t_eq(recruit_shortcode('L&T'), 'LT', 'a short code strips symbols');
t_eq(recruit_shortcode('Narmada Industries'), 'NAR', 'a short code takes the first letters');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    db()->prepare("INSERT INTO offices (code, name) VALUES (?,?)")->execute(['AMD', 'Ahmedabad']);
    $off = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO business_partners (legal_name, display_name, code, is_client, status, created_at) VALUES (?,?,?,?,?,?)")
        ->execute(['Larsen Toubro', 'LNT Group', 'C-LNT', 1, 'ACTIVE', 'x']);
    $cli = (int)db()->lastInsertId();

    $code = recruit_req_code($off, $cli, '2026-08-15');
    t_ok(preg_match('#^REQ/AMD/LNT/2608/C\d{3}/O\d{3}$#', $code) === 1,
        'a requisition code encodes office/client/YYMM + client-no + office-no: ' . $code);

    $na = recruit_req_code($off, 0, '2026-08-15');
    t_ok(strpos($na, '/NA/') !== false, 'a requirement with no client yet uses NA and is still valid: ' . $na);

    db()->prepare("INSERT INTO requisitions (req_code, office_id, client_id, created_at) VALUES (?,?,?,?)")
        ->execute([$code, $off, $cli, 'x']);
    $rid = (int)db()->lastInsertId();
    t_eq(recruit_cand_code(['id' => $rid, 'req_code' => $code]), $code . '-CV01',
        'the first CV against a requirement is <req-code>-CV01');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

$ops = (string)file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, 'recruit_req_code(') !== false, 'the requisition save uses the structured code');
t_ok(strpos($ops, 'recruit_cand_code(') !== false, 'the candidate save uses the requirement-linked code');
t_ok(strpos($ops, "'notes','quotation_ref'") !== false, 'the quotation ref is a saved requisition field');
