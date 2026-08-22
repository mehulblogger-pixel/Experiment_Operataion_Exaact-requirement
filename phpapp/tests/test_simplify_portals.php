<?php
// Simplification, step 5 — portal administration moves out of Quality into
// Directory (it manages portal sign-ins, not a quality register). Home-move;
// routes/screens untouched.
t_section('portal admin moved to Directory (simplify step 5)');

$a = (string)file_get_contents(__DIR__ . '/../lib/areas.php');
$quality = substr($a, strpos($a, "case 'quality':"), 4000);
$directory = substr($a, strpos($a, "case 'directory':"), 2200);

t_ok(strpos($quality, "'Client portal', '/portal-users'") === false, 'Client portal is removed from Quality');
t_ok(strpos($quality, "'Vendor portal', '/vendor-users'") === false, 'Vendor portal is removed from Quality');
t_ok(strpos($directory, "'Client portal', '/portal-users'") !== false, 'Client portal now lives under Directory');
t_ok(strpos($directory, "'Vendor portal', '/vendor-users'") !== false, 'Vendor portal now lives under Directory');
t_ok(strpos($directory, "\$sec('Portals')") !== false, 'Directory has a Portals section');
t_ok(strpos($directory, "'portal-users','vendor-users'") !== false, 'the portal routes highlight Directory now');
t_ok(strpos($quality, "portal-users") === false, 'the portal routes are gone from Quality');
