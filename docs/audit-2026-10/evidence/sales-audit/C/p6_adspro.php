<?php
// P6 — Ads Pro lead import dedupe, against a LOCAL stub on 127.0.0.1 (no external host).
putenv('PROBE_DB=' . __DIR__ . '/probe.sqlite');
require __DIR__ . '/boot.php'; require __DIR__ . '/lib.php';
$I = json_decode(file_get_contents(__DIR__ . '/ids.json'), true);
as_user($I['business_dev_manager']);
setting_set('adspro_url', 'http://127.0.0.1:8765'); setting_set('adspro_workspace', 'probe-ws'); setting_set('adspro_token', 'probe-token'); setting_set('adspro_autopush', '0');
ads_config(true); echo "ads_on=" . var_export(ads_on(), true) . " base=" . ads_config()['base'] . "\n";
// a lead typed by hand BEFORE the import, for the same person/company as AP-4
$r = lead_create(['company_name' => 'Manual First Ltd', 'contact_name' => 'Kiran Patel', 'contact_email' => 'kiran@manualfirst.invalid', 'contact_phone' => '9800000004', 'source' => 'PHONE']);
echo "manual lead: " . json_encode($r) . "\n";
$leads0 = (int)val("SELECT COUNT(*) FROM leads"); $inq0 = (int)val("SELECT COUNT(*) FROM crm_inquiries");
echo "\n[6a] ads_pull() #1\n"; print_r(ads_pull());
echo "\n[6b] ads_pull() #2 (same payload)\n"; print_r(ads_pull());
echo "\n[6c] legacy ads_import_leads() on the same payload\n"; print_r(ads_import_leads());
echo "\nleads +" . ((int)val("SELECT COUNT(*) FROM leads") - $leads0) . ", inquiries +" . ((int)val("SELECT COUNT(*) FROM crm_inquiries") - $inq0) . "\n";
foreach (rows("SELECT id, ref, company_name, partner_id, contact_email, contact_phone, source FROM leads WHERE contact_email LIKE '%acme%' OR contact_email LIKE '%manualfirst%' OR LOWER(contact_email) LIKE '%rshah%' ORDER BY id") as $x) echo "  lead " . json_encode($x) . "\n";
foreach (rows("SELECT id, inquiry_no, client_id, client_name, contact_email FROM crm_inquiries WHERE created_by='Ads Pro'") as $x) echo "  inquiry " . json_encode($x) . "\n";
echo "  (Zenith Gears LLP already exists as client #" . val("SELECT id FROM business_partners WHERE legal_name='Zenith Gears LLP'") . ")\n";
echo "  lead_possible_duplicate('Manual First Ltd') = " . json_encode(lead_possible_duplicate('Manual First Ltd')) . "\n";
echo "  ads_leads links: " . json_encode(rows("SELECT ext_id, local_kind, local_id FROM ads_leads ORDER BY id")) . "\n";
