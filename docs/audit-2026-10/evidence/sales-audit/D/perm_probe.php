<?php
// Per-role evaluation of every access predicate the sales handlers ask, as that user.
require __DIR__ . '/lib_boot.php';
$ids = json_decode(file_get_contents(__DIR__ . '/ids.json'), true);
$roles = ['master','bd','bmA','coA','mm','bdmA','kamA','meA','fin','insA'];
$checks = [
 'mod.leads.view'=>fn()=>can('mod.leads.view'), 'mod.leads.edit'=>fn()=>can('mod.leads.edit'), 'mod.leads.delete'=>fn()=>can('mod.leads.delete'),
 'leads_can_view()'=>fn()=>leads_can_view(), 'opp_can_edit()'=>fn()=>opp_can_edit(),
 'mod.inquiries.view'=>fn()=>can('mod.inquiries.view'), 'mod.inquiries.edit'=>fn()=>can('mod.inquiries.edit'), 'mod.inquiries.delete'=>fn()=>can('mod.inquiries.delete'),
 'inquiry-delete guard (is_admin_level)'=>fn()=>is_admin_level()||is_master(),
 'mod.quotes.view'=>fn()=>can('mod.quotes.view'), 'mod.quotes.edit'=>fn()=>can('mod.quotes.edit'),
 'crm.quote.create'=>fn()=>can('crm.quote.create'), 'crm.quote.approve'=>fn()=>can('crm.quote.approve'), 'crm.quote.send'=>fn()=>can('crm.quote.send'),
 'crm.contract.register'=>fn()=>can('crm.contract.register'), 'crm.template.manage'=>fn()=>can('crm.template.manage'),
 'mod.crm_orders.edit'=>fn()=>can('mod.crm_orders.edit'), 'mod.crm_reports.view'=>fn()=>can('mod.crm_reports.view'),
 'crmdash_can()'=>fn()=>crmdash_can(), 'pipe_can_view()'=>fn()=>pipe_can_view(), 'pipe_can()'=>fn()=>pipe_can(),
 'industry_can()'=>fn()=>industry_can(), 'gate_can_view()'=>fn()=>gate_can_view(),
 'pc_can()'=>fn()=>pc_can(), 'pc_can_edit()'=>fn()=>pc_can_edit(), 'pc_can_approve()'=>fn()=>pc_can_approve(),
 'data.profitability'=>fn()=>can('data.profitability'), 'data.salary'=>fn()=>can('data.salary'),
 'is_admin_level()'=>fn()=>is_admin_level(),
 'scope offices'=>fn()=>is_array(scope_offices())?implode(',',scope_offices()):scope_offices(),
 'scope sbus'=>fn()=>is_array(scope_sbus())?implode(',',scope_sbus()):scope_sbus(),
];
$hdr = "check\t" . implode("\t", $roles) . "\n"; $out = $hdr; echo $hdr;
foreach ($checks as $k => $fn) {
  $row = [$k];
  foreach ($roles as $r) { as_user($ids['U'][$r]); $v = $fn(); $row[] = is_bool($v) ? ($v ? 'Y' : '-') : (string)$v; }
  $line = implode("\t", $row) . "\n"; $out .= $line; echo $line;
}
file_put_contents(__DIR__ . '/perm_probe.tsv', $out);
