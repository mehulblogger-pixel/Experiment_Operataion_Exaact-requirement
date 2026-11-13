<?php
// Owner, 13 Nov 2026 — the costing page:
//  1. "It said costing is saved but then it shows zero … Where the saved costing
//     is gone." — "Save settings" saved the settings box only and threw away the
//     role being typed. Either button now saves the whole page.
//  2. "we must enter CTC, margin shall be entered and salary structure shall be
//     generated accordingly" — a salary break-up from a CTC, with the company's
//     own salary components (no percentage invented here).
//  3. "it bleeds and is weird, confusing" — the "% of other heads" picker.
t_section('Costing — a salary break-up from the CTC');
$pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
try {
    t_as_admin();
    if (function_exists('pc_migrate')) pc_migrate(); ch_migrate(); comp_migrate();
    $r = pc_heads_from_ctc(45000);
    t_ok(empty($r['err']) && !empty($r['items']), 'a CTC of 45,000 gives a break-up' . (!empty($r['err']) ? ': ' . $r['err'] : ''));
    $ev = ch_evaluate($r['items'], 'MONTH');
    t_ok($ev['ok'] && abs((float)$ev['direct'] - 45000) < 0.5, 'the heads add up to the CTC exactly (got ' . round((float)$ev['direct'], 2) . ')');
    $solved = comp_solve_from_ctc(45000); $want = [];
    foreach ($solved['lines'] as $l) if (in_array($l['section'], ['EARNING', 'EMPLOYER'], true) && (float)$l['amount'] > 0) $want[(string)$l['name']] = round((float)$l['amount'], 2);
    $got = []; foreach ($r['items'] as $it) $got[(string)$it['label']] = round((float)($ev['heads'][$it['code']] ?? 0), 2);
    t_eq($got, $want, 'each head is the company\'s own salary component, at the figure the offer-letter engine gives');
    $deds = array_column(array_filter($solved['lines'], fn($l) => $l['section'] === 'DEDUCTION'), 'name');
    t_ok(!array_intersect($deds, array_keys($got)), 'what is deducted from the employee\'s pay is not a company cost, so it is not a head');
    $grp = array_column($r['items'], 'grp', 'label');
    $firstEmp = null; foreach ($solved['lines'] as $l) if ($l['section'] === 'EMPLOYER' && (float)$l['amount'] > 0) { $firstEmp = (string)$l['name']; break; }
    t_ok(($grp['Basic'] ?? $grp[array_key_first($grp)]) === 'PAY' && ($firstEmp === null || $grp[$firstEmp] === 'STAT'), 'earnings are salary heads; employer contributions are statutory heads');
    t_ok(!empty(pc_heads_from_ctc(0)['err']), 'no CTC, no break-up — it says so');
    t_ok(strpos((string)$r['note'], 'Basic is') !== false, 'the note says which rule built it (Basic as a % of the CTC)');

    t_section('Costing — either Save keeps the whole page');
    $pdo->prepare("INSERT INTO project_costings (code, title, basis, status, overhead_pct, insurance_pct, baddebt_pct, contingency_pct, negotiation_pct, created_at) VALUES (?, 'CTC probe', 'LONG', 'DRAFT', 0, 0, 0, 0, 0, ?)")
        ->execute(['CSV-' . getmypid(), date('c')]);
    $cid = (int)$pdo->lastInsertId(); $h = pc_get($cid);
    t_ok(!pc_post_has_role(['title' => 'x']), 'a settings save with nothing typed in the role box carries no role');
    t_ok(!pc_post_has_role(['items_json' => '[]', 'role_label' => '']), 'an empty role box is not a role');
    $post = ['title' => 'CTC probe — renamed', 'overhead_pct' => '8', 'items_json' => json_encode($r['items']), 'role_label' => 'Shift Engineer',
             'headcount' => 2, 'boq_qty' => 12, 'target_margin' => 22, 'dpm' => 26];
    t_ok(pc_post_has_role($post), 'ARMING · the page sent a role with the settings');
    pc_settings_save($h, $post);
    $res = pc_role_save(pc_get($cid), $post);
    t_eq([$res['errs'], $res['role']], [[], 'Shift Engineer'], 'the role typed above is saved by "Save settings" — it is not thrown away');
    $h2 = pc_get($cid);
    t_eq([$h2['title'], (float)$h2['overhead_pct']], ['CTC probe — renamed', 8.0], 'and so are the settings');
    $roll = pc_rollup($cid);
    t_ok($roll && (float)$roll['totals']['revenue'] > 0 && (float)$roll['totals']['cost'] > 0, 'the costing no longer shows zero: revenue and cost come from the saved role');
    $bad = pc_role_save(pc_get($cid), ['items_json' => json_encode($r['items']), 'role_label' => '']);
    t_ok($bad['errs'] && !empty($_SESSION['pc_line_draft'][$cid]), 'a role that cannot be saved (no name) is kept on the page to finish, not lost');
    t_eq((int)ops_val("SELECT COUNT(*) FROM project_costing_lines WHERE costing_id=?", [$cid]), 1, 'and nothing half-saved is stored');
    unset($_SESSION['pc_line_draft'][$cid]);

    t_section('Costing — the screen');
    $v = (string)file_get_contents(__DIR__ . '/../views/ops/project_costing.php');
    t_ok(strpos($v, 'Quick start — from the CTC') !== false && strpos($v, '/project-costing-ctc-heads') !== false, 'the role box offers "Quick start — from the CTC"');
    t_ok(strpos($v, "getAttribute('id')!=='pcSettings'") !== false && strpos($v, "name='with_settings'") !== false, 'each button sends the other box along');
    t_ok(strpos($v, 'Also saves a role you are typing above') !== false, '"Save settings" says it keeps the role too');
    t_ok(strpos($v, 'class="btn small ofdone"') !== false && strpos($v, '.che .ofd .ofbox{position:absolute') !== false, 'the "% of" picker is a pop-over with Done, above the page — it no longer spills over the row');
    t_ok(strpos($v, 'class="mini"') === false, 'the two limits sit in the fold with labels, not squeezed into the value column');
    $lib = (string)file_get_contents(__DIR__ . '/../lib/projcosting.php');
    t_ok(strpos($lib, "flash('Costing saved.')") === false, 'the misleading "Costing saved." message is gone');
} finally {
    if ($own && $pdo->inTransaction()) $pdo->rollBack();
}
