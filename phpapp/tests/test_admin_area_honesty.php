<?php
// R11 — the "Admin" area (which reads as administrative power) appeared for roles with
// no real admin access: ASST_MANAGER via the "SLA targets" tile and MARKETING_MANAGER
// via "Document templates". Those two tiles moved to accurate homes (SLA → Quality;
// Document templates → Sales for crm.template.manage), so those roles no longer see Admin.
t_section('the Admin area no longer shows for roles without admin access (R11)');

$pdo = db();
$mk = function($u, $role, $super = 0) use ($pdo) {
    $pdo->prepare("INSERT INTO users (username, first_name, role, is_active, is_superuser) VALUES (?,?,?,1,?)")
        ->execute([$u, ucfirst(strtolower($role)), $role, $super]);
    return (int)$pdo->lastInsertId();
};
$asst  = $mk('r11_asst',   'ASST_MANAGER');
$mktg  = $mk('r11_mktg',   'MARKETING_MANAGER');
$mast  = $mk('r11_master', 'MASTER_ADMIN', 1);

$_SESSION['uid'] = $asst; current_user(true); ua(true);
t_ok(ops_area_has('admin') === false, 'an asst. manager no longer sees the Admin area');

$_SESSION['uid'] = $mktg; current_user(true); ua(true);
t_ok(ops_area_has('admin') === false, 'a marketing manager no longer sees the Admin area');
// They keep access to the template library — now under Sales.
t_ok(in_array('templates', ops_area_routes('sales'), true), 'document templates are reachable under Sales');

$_SESSION['uid'] = $mast; current_user(true); ua(true);
t_ok(ops_area_has('admin') === true, 'a master admin still sees the Admin area');

unset($_SESSION['uid']); current_user(true); ua(true);

// The tiles moved (source + route wiring), and Admin no longer carries them.
$areas = file_get_contents(__DIR__ . '/../lib/areas.php');
t_ok(in_array('sla-targets', ops_area_routes('quality'), true), 'SLA targets route now lives under Quality');
t_ok(strpos($areas, "'SLA targets', '/sla-targets', 'Turnaround targets for service delivery.'") !== false,
    'the SLA targets tile is defined in the Quality area');
// The Admin Document-templates tile no longer grants via crm.template.manage.
t_ok(strpos($areas, "(can('idems.type.manage') || is_master()), '📝', 'Document templates'") !== false,
    'the Admin templates tile is idems/master only (crm grant moved to Sales)');
// The Admin subtitle is honest about who it is for.
t_ok(strpos($areas, "For administrators: masters, people, access") !== false, 'the Admin area subtitle names administrators');
