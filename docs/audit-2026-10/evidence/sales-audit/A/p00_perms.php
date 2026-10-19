<?php
// Effective rights per role for every Sales right the lifecycle handlers ask.
require __DIR__ . '/plib.php';
$perms = ['mod.leads.view','mod.leads.edit','mod.inquiries.view','mod.inquiries.edit','mod.quotes.view','mod.quotes.edit',
          'crm.quote.create','crm.quote.approve','crm.quote.send','crm.contract.register'];
printf("%-6s %s  admin_lvl\n", 'role', implode(' ', array_map(fn($p) => str_pad(str_replace(['mod.','crm.'], '', $p), 15), $perms)));
foreach ($GLOBALS['U'] as $k => $uid) {
    $_SESSION['uid'] = $uid; current_user(true); if (function_exists('ua')) ua(true);
    printf("%-6s %s  %s\n", $k, implode(' ', array_map(fn($p) => str_pad(can($p) ? 'Y' : '.', 15), $perms)), is_admin_level() ? 'Y' : '.');
}
