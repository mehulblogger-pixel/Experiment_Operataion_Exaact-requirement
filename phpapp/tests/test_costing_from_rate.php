<?php
// Owner, 13 Nov 2026 — costing the other way round: "Yes, both ways, but the
// screen shall not be very confusing." Given the rate the client pays and the
// margin wanted, work back to the CTC the company can afford and its break-up.
// The proof is always the same: feed the answer to the costing's OWN calculation
// (pc_line_calc) and the margin it shows must be the margin that was asked for.
t_section('Costing — from the rate the client pays, back to the CTC');
$pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
try {
    t_as_admin();
    if (function_exists('pc_migrate')) pc_migrate(); ch_migrate(); comp_migrate();
    //  $check: rate + margin → CTC → heads → the costing's own sum. Returns [ctc, margin shown, rate the client is shown].
    $check = function (array $h, $rate, $tm, $dpm = 26) {
        $b = pc_ctc_from_rate($rate, $tm, $h, $dpm);
        if (!empty($b['err'])) return ['err' => $b['err']];
        $r = pc_heads_from_ctc($b['ctc']);
        if (!empty($r['err'])) return ['err' => $r['err']];
        $c = pc_line_calc(['spec' => json_encode(['items' => $r['items'], 'dpm' => $dpm]), 'target_margin' => $tm, 'rate_manual' => $b['rate_manual'], 'boq_qty' => 12, 'headcount' => 1], $h);
        return ['ctc' => $b['ctc'], 'margin' => (float)$c['margin'], 'proposed' => (float)$c['proposed'], 'direct' => (float)$c['direct']];
    };
    $base = ['basis' => 'LONG', 'overhead_pct' => 0, 'insurance_pct' => 0, 'baddebt_pct' => 0, 'contingency_pct' => 0, 'negotiation_pct' => 0, 'load_on' => 'COST'];

    $a = $check($base, 60000, 25);
    t_ok(empty($a['err']), 'ARMING · a rate of 60,000 at 25% works back to a CTC' . (!empty($a['err']) ? ': ' . $a['err'] : ''));
    t_eq($a['ctc'] ?? null, 45000.0, 'no loadings: 60,000 at 25% leaves a CTC of 45,000');
    t_ok(abs($a['margin'] - 25) < 0.01 && abs($a['proposed'] - 60000) < 0.01, 'the costing then shows 25% margin at 60,000 — the two directions agree');

    //  Forward and back are the same sum: CTC 45,000 at 25% gives 60,000; 60,000 at 25% gives 45,000.
    $fw = pc_line_calc(['spec' => json_encode(['items' => pc_heads_from_ctc(45000)['items'], 'dpm' => 26]), 'target_margin' => 25, 'boq_qty' => 1, 'headcount' => 1], $base);
    t_ok(abs((float)$fw['proposed'] - 60000) < 0.01, 'and the forward way (CTC 45,000 at 25%) gives the same 60,000');

    $cases = [
        'loadings on the cost'                    => [['overhead_pct' => 8, 'insurance_pct' => 1, 'baddebt_pct' => 0.5, 'contingency_pct' => 2], 66000, 22, 26],
        'loadings on the rate'                    => [['overhead_pct' => 8, 'insurance_pct' => 1, 'load_on' => 'RATE'], 66000, 22, 26],
        'a negotiation padding'                   => [['overhead_pct' => 8, 'negotiation_pct' => 5], 66000, 22, 26],
        'padding with loadings on the rate'       => [['overhead_pct' => 8, 'negotiation_pct' => 5, 'load_on' => 'RATE'], 66000, 22, 26],
        'a daily sheet (rate a man-day)'          => [['basis' => 'DAILY', 'overhead_pct' => 8], 2800, 20, 26],
        'a daily sheet with a 30-day month'       => [['basis' => 'DAILY', 'overhead_pct' => 8, 'load_on' => 'RATE'], 2800, 20, 30],
        'a lump-sum sheet'                        => [['basis' => 'FIXED', 'overhead_pct' => 10], 90000, 18, 26],
        'no margin at all'                        => [['overhead_pct' => 8], 66000, 0, 26],
    ];
    foreach ($cases as $name => [$over, $rate, $tm, $dpm]) {
        $x = $check($over + $base, $rate, $tm, $dpm);
        if (!empty($x['err'])) { t_ok(false, $name . ': ' . $x['err']); continue; }
        //  The CTC is rounded DOWN to a rupee, so the margin is never under the one asked — and never more than a whisker over.
        t_ok($x['margin'] >= $tm - 0.005 && $x['margin'] < $tm + 0.05 && abs($x['proposed'] - $rate) < 0.01 && $x['ctc'] > 0,
            $name . ': the costing shows the margin asked (' . $tm . '% → ' . $x['margin'] . '%) at the client\'s rate, CTC ' . number_format($x['ctc']));
    }
    //  A day rate on a 26-day month affords a smaller monthly CTC than on a 30-day month.
    $d26 = pc_ctc_from_rate(2800, 20, ['basis' => 'DAILY'] + $base, 26); $d30 = pc_ctc_from_rate(2800, 20, ['basis' => 'DAILY'] + $base, 30);
    t_eq([$d26['ctc'], $d30['ctc']], [58240.0, 67200.0], 'a day rate is turned into the month\'s CTC by the working days typed on the page');

    t_section('Costing — from the rate: it says so when the sum cannot work');
    t_ok(!empty(pc_ctc_from_rate(0, 22, $base)['err']), 'no rate — it asks for the rate');
    t_ok(!empty(pc_ctc_from_rate(66000, 100, $base)['err']) && !empty(pc_ctc_from_rate(66000, -1, $base)['err']), 'a margin of 100% or below zero is refused');
    $none = pc_ctc_from_rate(66000, 95, ['overhead_pct' => 8, 'load_on' => 'RATE'] + $base);
    t_ok(!empty($none['err']) && strpos($none['err'], 'nothing is left') !== false, 'margin plus loadings eat the whole rate — it says nothing is left for the pay, and what to change');
    //  Owner, 13 Nov 2026 (SAL-176 step 8): "No such red line observed … 66000 and margin 95% showed a month rate of 1510740".
    //  Two different sums, both right — the test step must say which one it means.
    $h9 = ['overhead_pct' => 8, 'insurance_pct' => 0.5, 'baddebt_pct' => 0, 'contingency_pct' => 0.5, 'negotiation_pct' => 5, 'basis' => 'LONG'];
    t_ok(!empty(pc_ctc_from_rate(66000, 95, ['load_on' => 'RATE'] + $h9)['err']), 'the owner\'s settings, loadings on the RATE: 95% + 9% is more than the whole rate — the red line');
    $onCost = pc_ctc_from_rate(66000, 95, ['load_on' => 'COST'] + $h9);
    t_eq($onCost['ctc'] ?? null, 3027.0, '…loadings on the COST: 5% of the rate is left, less 9% loadings — a CTC of ₹3,027, no red line (the sum works, however small)');
    $c66 = pc_line_calc(['heads' => json_encode(['PERSONNEL' => 66000]), 'target_margin' => 95, 'boq_qty' => 1], ['load_on' => 'COST'] + $h9);
    t_eq($c66['proposed'], 1510740.0, '…and 66,000 typed as the CTC (the other question) at 95%: 66,000 + 9% = 71,940, ÷ 5% = 14,38,800, + 5% negotiation = 15,10,740 — the figure the owner saw');

    t_section('Costing — from the rate: the screen asks one question');
    $v = (string)file_get_contents(__DIR__ . '/../views/ops/project_costing.php');
    //  Owner, 14 Nov 2026: one Price box — "Cost + my margin" or "The client's rate is fixed"; each figure once.
    t_ok(strpos($v, 'Cost + my margin — the rate follows') !== false && strpos($v, "The <?= \$e(Tl('client')) ?>'s rate is fixed") !== false, 'the Price box asks one question: cost + my margin, or the client\'s rate is fixed');
    t_ok(strpos($v, 'data-ctc-mode="rate"') !== false && strpos($v, "l.hidden=l.getAttribute('data-ctc-mode')!==m") !== false, 'only the chosen direction\'s box is shown — never both');
    t_eq([substr_count($v, 'name="target_margin"'), substr_count($v, 'name="boq_qty"'), substr_count($v, 'name="rate_manual"'), substr_count($v, 'id="ch_ctc_tm"')], [1, 1, 0, 0],
        'margin and quantity are each one box; there is no second "fixed rate override" or second margin box');
    t_ok(strpos($v, "localStorage.setItem('pc_ctc_mode',m)") !== false && strpos($v, "localStorage.getItem('pc_ctc_mode')") !== false,
        'the question last chosen is remembered — "Save settings" reloads the page and used to flip it back to "I know the CTC"');
    //  Owner, 13 Nov 2026 (SAL-176 11 & 12): "CTC is man-day only instead of man-month".
    t_ok(strpos($v, "\$perDay = \$lb === 'DAILY'") !== false && strpos($v, 'id="pv_pay_m"') !== false && strpos($v, 'id="pv_direct_m"') !== false,
        'a per-man-day role shows the month beside the day salary and direct cost (only for a man-day role — the basis is per role from 14 Nov 2026)');
    t_ok(strpos($v, "money(Math.round(x[1]*dpm))+' a month'") !== false, '…worked at the "Working days in a month" typed on the page');
    $day = pc_ctc_from_rate(2800, 22, ['basis' => 'DAILY', 'overhead_pct' => 7.5, 'insurance_pct' => 0.5, 'contingency_pct' => 0.5, 'load_on' => 'COST'], 26);
    $back = pc_line_calc(['heads' => json_encode(['PERSONNEL' => $day['ctc'] / 26]), 'target_margin' => 22, 'boq_qty' => 1], ['basis' => 'DAILY', 'overhead_pct' => 7.5, 'insurance_pct' => 0.5, 'contingency_pct' => 0.5, 'load_on' => 'COST']);
    t_ok($day['ctc'] == 52335.0 && abs($back['proposed'] - 2800) < 1, 'SAL-176 step 11–12 figures: ₹2,800 a man-day at 22% → CTC ₹52,335 a month; back the other way → ₹' . $back['proposed'] . ' a man-day');
    $back5 = pc_line_calc(['heads' => json_encode(['PERSONNEL' => $day['ctc'] / 26]), 'target_margin' => 22, 'boq_qty' => 1], ['basis' => 'DAILY', 'overhead_pct' => 7.5, 'insurance_pct' => 0.5, 'contingency_pct' => 0.5, 'negotiation_pct' => 5, 'load_on' => 'COST']);
    t_ok(abs($back5['proposed'] - 2940) < 1, '…with 5% negotiation the CTC question adds the padding on top: ₹' . $back5['proposed'] . ' (= ₹2,800 + 5%) — the edge step explains it');
    t_ok(substr_count($v, "try{ localStorage") >= 1 && strpos($v, "}catch(e){}") !== false, '…and the page still works where the browser keeps nothing (private window): it opens on "I know the CTC"');
    $lib = (string)file_get_contents(__DIR__ . '/../lib/projcosting.php');
    t_ok(strpos($lib, "(\$_POST['mode'] ?? '') === 'rate'") !== false && strpos($lib, "if (!pc_can_edit())") !== false, 'the same endpoint, behind the same "can edit costings" gate');
} finally {
    if ($own && $pdo->inTransaction()) $pdo->rollBack();
}
