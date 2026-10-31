<?php
// Owner, 31 Oct 2026 (test book ONB-010, ONB-012): requests are "deep inside the
// panel" with nothing on the landing page; a declined applicant gets no e-mail;
// Companies does not show requests at all. Checked on a local copy (a request
// waiting, the Owner home card, the Companies panel, a decline with a reason).
t_section('Workspace requests — seen on Owner home and Companies; a decline is e-mailed');
$pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
try {
    tenant_signup_migrate();
    $pdo->prepare("INSERT INTO tenant_requests (company,contact_name,email,phone,tenant_sub,status,created_at) VALUES ('TB Apply Co','Asha','asha@example.com','9999999999','tbapply','PENDING',?)")->execute([date('c')]);
    $id = (int) $pdo->lastInsertId();
    t_ok(tenant_requests_pending_count() >= 1, 'a waiting request is counted');
    $sent = null;
    $GLOBALS['__mail_capture'] = function ($to, $subject, $body) use (&$sent) { $sent = [$to, $subject, $body]; };
    [$ok, $msg] = tenant_request_reject($id, 'We only onboard inspection companies at present.');
    unset($GLOBALS['__mail_capture']);
    t_ok($ok && (string) ops_val("SELECT status FROM tenant_requests WHERE id=?", [$id]) === 'REJECTED', 'the request is declined');
    t_ok(is_array($sent) && $sent[0] === 'asha@example.com' && strpos($sent[2], 'Reason: We only onboard inspection companies') !== false,
         'the applicant is e-mailed, with the reason');
} finally { unset($GLOBALS['__mail_capture']); if ($own) $pdo->rollBack(); }
$oh = (string) file_get_contents(__DIR__ . '/../views/ops/owner_home.php');
t_ok(strpos($oh, 'tenant_requests_pending_count()') !== false && strpos($oh, ' waiting for you') !== false, 'Owner home says how many requests are waiting');
$co = (string) file_get_contents(__DIR__ . '/../views/ops/saas_companies.php');
t_ok(strpos($co, '<h2>Workspace requests') !== false && strpos($co, '/tenants#requests') !== false, 'Companies lists the requests, with the way to act on them');
