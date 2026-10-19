<?php
$root = '/home/user/Experiment_Operataion_Exaact-requirement/phpapp';
require $root . '/tests/lib.php'; require $root . '/tests/bootstrap.php';
$roles = array_keys(ORG_ROLES);
$keys = ['mod.leads.view','mod.inquiries.view','mod.quotes.view','mod.quotes.add','crm.quote.create','crm.quote.approve','crm.quote.send','crm.contract.register'];
printf("%-22s %s\n", 'role', implode(' ', array_map(fn($k)=>str_pad(str_replace(['mod.','crm.'],'',$k),14), $keys)));
foreach ($roles as $r) {
  $p = role_perms($r);
  printf("%-22s %s\n", $r, implode(' ', array_map(fn($k)=>str_pad(in_array($k,$p,true)?'yes':'-',14), $keys)));
}
