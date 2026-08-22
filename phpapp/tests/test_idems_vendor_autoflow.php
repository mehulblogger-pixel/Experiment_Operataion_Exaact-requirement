<?php
// The vendor/site on a report auto-flows from the call it was raised against.
// When the call names a separate vendor, that vendor carries. When it does NOT
// (the inspection is at the client's own premises), the vendor/site defaults to
// the CLIENT — "if the vendor place is the same as the client place, the same
// shall be there" — so the report opens with the site already filled.
t_section('a report auto-flows the vendor from its call (client when none named)');

$pdo = db();
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name) VALUES ('Autoflow Client Ltd', 'Autoflow Client')")->execute();
$clientId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name) VALUES ('Autoflow Vendor Ltd', 'Autoflow Vendor')")->execute();
$vendorId = (int)$pdo->lastInsertId();

// A call WITH a separate vendor → that vendor carries onto the report.
$pdo->prepare("INSERT INTO calls (call_code, client_id, vendor_id, created_at) VALUES ('AF-1', ?, ?, ?)")
    ->execute([$clientId, $vendorId, date('c')]);
$c1 = (int)$pdo->lastInsertId();
$ctx1 = idems_context_for($c1);
t_ok((int)$ctx1['vendor_id'] === $vendorId, 'a named vendor carries onto the report');
t_ok(empty($ctx1['vendor_is_client']), 'the named-vendor case is not flagged as client-premises');

// A call with NO vendor → the vendor/site defaults to the client.
$pdo->prepare("INSERT INTO calls (call_code, client_id, created_at) VALUES ('AF-2', ?, ?)")
    ->execute([$clientId, date('c')]);
$c2 = (int)$pdo->lastInsertId();
$ctx2 = idems_context_for($c2);
t_ok((int)$ctx2['vendor_id'] === $clientId, 'with no vendor, the report vendor/site defaults to the client');
t_ok(($ctx2['vendor_name'] ?? '') === 'Autoflow Client', 'the client name is used as the vendor/site name');
t_ok(!empty($ctx2['vendor_is_client']), 'the client-premises default is flagged');
