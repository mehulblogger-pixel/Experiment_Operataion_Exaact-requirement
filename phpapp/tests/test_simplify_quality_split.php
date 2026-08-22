<?php
// Simplification, step 6 — Quality is re-sectioned into two clear halves:
// "Everyday quality" (touched during live jobs) and "Accreditation registers"
// (the set-once/periodic records an assessor asks for). Pure re-grouping —
// every tile keeps its route, gate and count; nothing leaves the area.
t_section('Quality split into everyday vs accreditation (simplify step 6)');

$a = (string)file_get_contents(__DIR__ . '/../lib/areas.php');
$quality = substr($a, strpos($a, "case 'quality':"), 6000);

$everyday_at = strpos($quality, "\$sec('Everyday quality')");
$accred_at   = strpos($quality, "\$sec('Accreditation registers')");
t_ok($everyday_at !== false, 'Quality has an "Everyday quality" section');
t_ok($accred_at !== false, 'Quality has an "Accreditation registers" section');
t_ok($everyday_at !== false && $accred_at !== false && $everyday_at < $accred_at,
    'Everyday quality comes before Accreditation registers');

// The old mixed section headers are gone.
foreach (['Measurement & docs', 'Risk & competence', 'Nonconformity & audit', 'Customer & portals'] as $old) {
    t_ok(strpos($quality, "'$old'") === false, "the old \"$old\" section header is gone");
}

// Everyday half holds the live-job work; accreditation half holds the registers.
$everyday = substr($quality, $everyday_at, $accred_at - $everyday_at);
$accred   = substr($quality, $accred_at);
foreach (['/complaints', '/report-reviews', '/satisfaction', '/samples', '/hold-points',
          '/evidence-review', '/ncr', '/issues', '/capa'] as $rt) {
    t_ok(strpos($everyday, "'$rt'") !== false, "$rt sits under Everyday quality");
}
foreach (['/equipment', '/methods', '/drules', '/cdocs', '/retention', '/data-control',
          '/risks', '/competence', '/impartiality', '/disclosure', '/internal-audits',
          '/management-reviews', '/confidentiality', '/site-docs', '/identity'] as $rt) {
    t_ok(strpos($accred, "'$rt'") !== false, "$rt sits under Accreditation registers");
}

// Nothing left the area — every route still highlights Quality.
foreach (['equipment','samples','ncr','capa','complaints','risks','competence','identity'] as $rt) {
    t_ok(strpos($quality, "'$rt'") !== false, "$rt still highlights Quality (route intact)");
}
