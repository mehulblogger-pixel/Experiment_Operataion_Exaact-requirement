<?php
// LOCAL stub of the Ads Pro api.php (127.0.0.1 only). Returns a fixed contact list.
header('Content-Type: application/json');
$a = $_GET['action'] ?? '';
file_put_contents(__DIR__ . '/hits.log', date('c') . " $a\n", FILE_APPEND);
if ($a === 'crm_import_wa_leads') { echo json_encode(['ok' => true]); return; }
if ($a === 'crm_get_contacts') {
  echo json_encode(['contacts' => [
    ['id' => 'AP-1', 'name' => 'Ravi Shah', 'company' => 'Acme Valves Pvt Ltd', 'email' => 'rshah@acme.invalid', 'phone' => '9800000001', 'source' => 'meta_ads', 'status' => 'lead'],
    ['id' => 'AP-2', 'name' => 'Ravi Shah', 'company' => 'Acme Valves', 'email' => 'RShah@acme.invalid ', 'phone' => '+91 98000 00001', 'source' => 'google', 'status' => 'lead'],
    ['id' => 'AP-3', 'name' => 'Meera Rao', 'company' => 'Zenith Gears LLP', 'email' => 'meera@zenith.invalid', 'phone' => '9800000003', 'source' => 'website', 'status' => 'customer'],
    ['id' => 'AP-4', 'name' => 'Kiran Patel', 'company' => 'Manual First Ltd', 'email' => 'kiran@manualfirst.invalid', 'phone' => '9800000004', 'source' => 'whatsapp', 'status' => 'lead'],
  ]]); return;
}
echo json_encode(['ok' => true]);
