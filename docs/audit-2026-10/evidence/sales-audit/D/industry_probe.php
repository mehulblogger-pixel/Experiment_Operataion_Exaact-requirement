<?php
// Dumps every industry template, then (as the Business Director) applies one and
// reports the company-wide side effects. Run on its own fresh seed.
require __DIR__ . '/flow_lib.php';
require __DIR__ . '/lib_boot.php';


$md = "| Key | Label | Opportunity funnel: stage (kind) prob% / SLA days | Alternate funnel | Lead stages | Sources | Lost reasons |\n|---|---|---|---|---|---|---|\n";
foreach (INDUSTRY_TEMPLATES as $k => $t) {
    $f = implode(' → ', array_map(fn($s) => "{$s[0]}" . ($s[1] !== 'OPEN' ? " ({$s[1]})" : '') . " {$s[2]}%/{$s[3]}d", $t['funnel']));
    $alt = []; foreach ($t['alt'] ?? [] as $n => $st) $alt[] = "$n: " . implode(' → ', array_map(fn($s) => "{$s[0]} {$s[2]}%/{$s[3]}d", $st));
    $ld = implode(' → ', array_map(fn($s) => "{$s[0]} {$s[3]}d", $t['leads']));
    $md .= "| $k | {$t['label']} | $f | " . implode('; ', $alt) . " | $ld | " . implode(', ', $t['sources']) . ' | ' . implode(', ', $t['lost']) . " |\n";
}
file_put_contents(__DIR__ . '/industry_templates.md', $md);
echo "templates: ", count(INDUSTRY_TEMPLATES), " -> ", implode(', ', array_keys(INDUSTRY_TEMPLATES)), "\n";
echo "INDUSTRY_TERM_MAP: ", json_encode(INDUSTRY_TERM_MAP), "\n";
// side effects of applying, as the Business Director
packs_save('inspection');
$before = ['accredited_pack_on' => accredited_pack_on(), 'packs_enabled' => setting_get('packs_enabled', ''), 'terms_pack' => setting_get('terms_pack', ''),
           'default_opp_pipeline' => pipeline_default('OPPORTUNITY')['name'] ?? ''];
$r = act('bd', 'industry-apply', 'POST', [], ['key' => 'staffing']);
$after = ['packs_enabled' => qv("SELECT svalue FROM settings WHERE skey='packs_enabled'"), 'terms_pack' => qv("SELECT svalue FROM settings WHERE skey='terms_pack'"),
          'industry_template' => qv("SELECT svalue FROM settings WHERE skey='industry_template'"),
          'default_opp_pipeline' => qv("SELECT name FROM pipelines WHERE entity_kind='OPPORTUNITY' AND is_default=1"),
          'pipelines' => q("SELECT id,name,entity_kind,is_default FROM pipelines")];
step('I1', 'BUSINESS_DIRECTOR (matrix: Settings —) applies the "Recruitment & staffing" template on an inspection company whose ISO/IEC 17020 pack is on',
     'refused (settings are Admin-only), or at least the accreditation pack is left alone',
     $r['say'] . "\n      before: " . json_encode($before) . "\n      after:  " . json_encode($after));
file_put_contents(__DIR__ . '/industry_probe.out', implode('', $GLOBALS['LOG']));
