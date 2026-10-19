<?php
@unlink(__DIR__ . '/roles.sqlite'); putenv('PROBE_DB=' . __DIR__ . '/roles.sqlite'); putenv('FRESH=1');
require __DIR__ . '/boot.php';
$keys = ['mod.quotes.view','mod.quotes.edit','crm.quote.create','crm.quote.approve','crm.quote.send','crm.contract.register','mod.calls.view','ops.call.create','mod.inquiries.edit','mod.leads.view','finance.reconcile','data.credit','mod.invoicing.view'];
printf("%-22s", 'role'); foreach ($keys as $k) printf("%-6s", substr(str_replace(['mod.','crm.','ops.'],'',$k),0,5)); echo "\n";
foreach (array_keys(ORG_ROLES) as $r) { $p = role_perms($r); if (!is_array($p)) continue; printf("%-22s", $r);
  foreach ($keys as $k) printf("%-6s", in_array($k,$p,true)?'Y':'.'); echo "\n"; }
echo "\nkeys: " . implode(' | ', $keys) . "\n";
