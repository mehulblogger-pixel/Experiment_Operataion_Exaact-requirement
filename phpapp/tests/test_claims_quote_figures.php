<?php
// ============================================================================
//  Expense claims, job-closure expenses and quotations refuse figures no
//  accountant would accept. (GAP-046 / GAP-047)
//
//  The routes themselves were driven over HTTP against throwaway copies
//  (docs/audit-2026-10/evidence/live-claims-quotes/). This guard rail pins the
//  two validators and that each route calls its validator BEFORE it writes.
//  (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('claims and quotations — figures validated before anything is written');

// ---- the shared validator ------------------------------------------------------
t_eq(figures_problem([['4', 'Hours', 0, 8.5]]), '', 'control: 4 hours is fine');
t_eq(figures_problem([['', 'Hours', 0, 8.5]]), '', 'a blank reads as 0 and is fine');
t_eq(figures_problem([['-5', 'Hours', 0, 8.5]]), 'Hours cannot be negative.', 'negative hours are refused, naming the field');
t_eq(figures_problem([['12', 'Hours', 0, 8.5]]), 'Hours cannot be more than 8.5.', 'more than the cap on one row is refused');
t_eq(figures_problem([['abc', 'KM', 0, null]]), 'KM must be a number.', 'a non-number is refused, not read as 0');
t_eq(figures_problem([['1e400', 'KM', 0, null]]), 'KM must be a number.', 'an infinite figure is refused');
t_eq(figures_problem([['3', 'A', 0, null], ['-1', 'B', 0, null]]), 'B cannot be negative.', 'the first bad field in a set is the one named');

// ---- quotation rules -------------------------------------------------------------
$q = fn($gst, array $lines, $extra = []) => crm_quote_figures_problem(array_merge([
    'gst_pct' => $gst,
    'l_desc'  => array_map(fn($l) => $l[0], $lines),
    'l_qty'   => array_map(fn($l) => $l[1], $lines),
    'l_rate'  => array_map(fn($l) => $l[2], $lines)], $extra));
t_eq($q('18', [['Inspection', '2', '5000']]), '', 'control: 2 × 5,000 @ 18%');
t_eq($q('18', [['Inspection', '2', '5000'], ['Discount', '1', '-1000']]), '', 'a negative-RATE discount line is allowed (there is no other discount field)');
t_ok(strpos($q('18', [['Inspection', '-3', '5000']]), 'quantity on line 1 cannot be negative') !== false, 'a negative quantity is refused, naming the line');
t_ok(strpos($q('500', [['Inspection', '2', '5000']]), 'GST %') !== false, 'GST 500% is refused');
t_ok(strpos($q('-18', [['Inspection', '2', '5000']]), 'GST %') !== false, 'negative GST is refused');
t_ok(strpos($q('18', [['Inspection', '1', '1000'], ['Discount', '1', '-5000']]), 'less than nothing') !== false, 'a discount bigger than the work is refused');
t_ok(strpos($q('18', [['Inspection', '1', 'lots']]), 'rate on line 1 must be a number') !== false, 'a non-numeric rate is refused');
t_eq($q('18', [['', '', '']]), '', 'an untouched blank row is skipped, as the save skips it');
t_ok(strpos($q('18', [['Inspection', '1', '100']], ['advance_pct' => '150']), 'Advance %') !== false, 'an advance over 100% is refused');

// ---- each route validates BEFORE it writes --------------------------------------------
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
$crm = file_get_contents(__DIR__ . '/../lib/crm.php');
$before = function ($src, $guard, $write, $from) {
    $a = strpos($src, $from); if ($a === false) return false;
    $g = strpos($src, $guard, $a); $w = strpos($src, $write, $a);
    return $g !== false && $w !== false && $g < $w;
};
t_ok($before($ops, 'figures_problem($f)', 'UPDATE voucher_entries SET hours=?, line_no=?, file_no=?, mode_code=?', "\$route === 'voucher-save'"),
     'the claim sheet save checks every hour, km and amount before its first UPDATE');
t_ok($before($ops, "figures_problem([[\$b['hours']", 'UPDATE voucher_entries SET hours=?, line_no=?, file_no=?, notes=?', "\$route === 'voucher-entry'"),
     'editing or adding a day checks the hours before writing');
t_ok($before($ops, 'figures_problem($expF)', 'INSERT INTO expenses (job_id,inspector_id', '// collect any configurable (extra) headings') || $before($ops, 'figures_problem($expF)', 'INSERT INTO expenses (job_id,inspector_id', 'The day\'s expenses are real money'),
     'job closure checks the day\'s expenses before recording them');
t_ok($before($crm, 'crm_quote_figures_problem($b)', 'UPDATE quotations SET $set', "\$route === 'quote-new' || \$route === 'quote-edit'"),
     'the quotation save checks the figures before it writes the header or the lines');
t_ok($before($crm, 'crm_quote_figures_problem($b)', 'INSERT INTO quotations (', "\$route === 'quote-external'"),
     'the external-quotation save checks them too');

// ---- the forms say no first --------------------------------------------------------
$vd = file_get_contents(__DIR__ . '/../views/ops/voucher_detail.php');
t_ok(substr_count($vd, 'min="0"') >= 5, 'the claim sheet\'s hours, km and amount fields carry min="0"');
$qf = file_get_contents(__DIR__ . '/../views/ops/crm/quote_form.php');
t_ok(strpos($qf, 'min="0" max="100" id="gst_pct"') !== false && strpos($qf, 'min="0" name="l_qty[]"') !== false, 'the quotation form limits GST to 0–100 and quantity to 0 or more');
$jc = file_get_contents(__DIR__ . '/../views/ops/job_close.php');
t_eq(preg_match_all('/min="0" name="(travel|local|food|lodging|misc)"/', $jc), 5, 'the five job-closure expense fields carry min="0"');
