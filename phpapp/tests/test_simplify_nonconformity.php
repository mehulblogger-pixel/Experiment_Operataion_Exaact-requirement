<?php
// Simplification, step 8 — the nonconformity family (Issues, NCRs, corrective
// actions, departures) becomes ONE tabbed workspace via a shared nc_tabs()
// strip. Nothing deleted: every route still resolves and each screen keeps its
// own handler and permission gate.
t_section('nonconformity folded into one tabbed workspace (simplify step 8)');

$areas = (string)file_get_contents(__DIR__ . '/../lib/areas.php');
$quality = substr($areas, strpos($areas, "case 'quality':"), 6000);

// Nav: three tiles collapse into one workspace tile.
t_ok(strpos($quality, "'Nonconformity workspace'") !== false, 'one Nonconformity workspace tile exists');
t_ok(strpos($quality, "'Nonconformities', '/ncr'") === false, 'the separate NCR tile is gone from nav');
t_ok(strpos($quality, "'Issues & departures', '/issues'") === false, 'the separate Issues tile is gone from nav');
t_ok(strpos($quality, "'Corrective actions', '/capa'") === false, 'the separate Corrective actions tile is gone from nav');
// It lands on the umbrella when the service is on, and NCR otherwise.
t_ok(strpos($quality, "ncdca_enabled()) ? '/issues' : '/ncr'") !== false,
    'the workspace lands on Issues when NCR_CAPA is on, else NCR');
// Routes untouched — every screen still highlights Quality.
foreach (['ncr','issues','departures','capa'] as $rt) {
    t_ok(strpos($quality, "'$rt'") !== false, "$rt still highlights Quality (route intact)");
}
// Hold & witness points is unrelated and stays its own tile.
t_ok(strpos($quality, "'Hold & witness points', '/hold-points'") !== false,
    'Hold & witness points stays its own tile (not part of the merge)');

// The shared tab strip helper exists and covers the four tabs.
$ncdca = (string)file_get_contents(__DIR__ . '/../lib/ncdca.php');
t_ok(strpos($ncdca, 'function nc_tabs(') !== false, 'the nc_tabs() tab strip helper exists');
foreach ([['issues','/issues'], ['ncr','/ncr'], ['capa','/capa'], ['departures','/departures']] as [$key, $href]) {
    t_ok(strpos($ncdca, "'$key'") !== false && strpos($ncdca, "'$href'") !== false,
        "the tab strip includes the $key tab");
}
// Issues + Departures tabs are gated behind the NCR_CAPA service.
t_ok(strpos($ncdca, '$ncdca = ncdca_enabled();') !== false,
    'Issues and Departures tabs are gated behind the NCR_CAPA service');

// Each of the four list views renders the tab strip, marking its own tab active.
$views = ['ncr_list' => 'ncr', 'capa_list' => 'capa', 'issues' => 'issues', 'departures' => 'departures'];
foreach ($views as $file => $active) {
    $v = (string)file_get_contents(__DIR__ . "/../views/ops/$file.php");
    t_ok(strpos($v, "nc_tabs('$active')") !== false, "views/ops/$file.php shows the tab strip (active: $active)");
}
