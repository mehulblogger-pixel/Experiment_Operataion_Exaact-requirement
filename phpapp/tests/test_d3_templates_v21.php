<?php
// Merge of D3 (a quotation before the quotation stage) with the V2.1 industry
// templates (owner, 21 Nov 2026): every V2.1 template names its quotation step,
// a funnel created from it is recognised by its name, and a funnel made under the
// old TPIA name ("Inspection, testing & certification") is still recognised.
t_section('D3 × industry templates V2.1 — each template\'s quotation step');

$want = ['inspection' => 'Commercial proposal submitted', 'manpower' => 'Profiles / CVs submitted',
         'recruitment' => 'Profiles submitted', 'audit' => 'Proposal submitted'];
foreach ($want as $k => $nm) {
    $t = INDUSTRY_TEMPLATES[$k];
    t_ok(array_key_exists('quote', $t) && $t['quote'] !== null, "template \"$k\" carries a quotation step");
    t_eq($t['funnel'][(int) $t['quote']][0], $nm, "template \"$k\": quotation step is \"$nm\"");
    t_eq($t['funnel'][(int) $t['quote']][1], 'OPEN', "template \"$k\": that step is an open stage");
}
// Every template in the list says what its step is (a number, or null for none).
foreach (INDUSTRY_TEMPLATES as $k => $t) t_ok(array_key_exists('quote', $t), "template \"$k\" states its quotation step (or none)");

// A funnel named after the template is recognised (by its name), old TPIA name too.
$pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
try {
    opp_migrate();
    $mk = function ($name) use ($pdo) {
        $pdo->prepare("INSERT INTO pipelines (name, entity_kind, is_default, active, created_by, created_at) VALUES (?, 'OPPORTUNITY', 0, 1, 'test', ?)")->execute([$name, date('c')]);
        return (int) $pdo->lastInsertId();
    };
    foreach ($want as $k => $nm) {
        $pid = $mk(INDUSTRY_TEMPLATES[$k]['label'] . ' — new business');
        t_eq(opp_quote_stage_pct($pid), (int) INDUSTRY_TEMPLATES[$k]['funnel'][INDUSTRY_TEMPLATES[$k]['quote']][2], "a \"$k\" funnel: the quotation stage's chance of winning is the template's");
    }
    $old = $mk('Inspection, testing & certification — new business');
    t_eq(opp_quote_stage_pct($old), 50, 'a funnel created under the old TPIA name is still recognised (50%)');
} finally { if ($own) $pdo->rollBack(); }
