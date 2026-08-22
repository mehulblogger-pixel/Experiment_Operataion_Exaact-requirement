<?php
// Simplification, step 7 — the five accounts billing screens (to-bill, invoices,
// money in, ageing, tally export) become ONE tabbed workspace via a shared tab
// strip. The light per-job Invoice tracker stays its own tile. Nothing deleted:
// every route still resolves and each screen keeps its own handler and gate.
t_section('billing folded into one tabbed workspace (simplify step 7)');

$areas = (string)file_get_contents(__DIR__ . '/../lib/areas.php');
$money = substr($areas, strpos($areas, "case 'money':"), 2800);

// Nav: the tracker stays separate, the five screens collapse to one workspace tile.
t_ok(strpos($money, "'Invoice tracker', '/invoicing'") !== false, 'the light Invoice tracker stays its own tile');
t_ok(strpos($money, "'Billing workspace', '/to-bill'") !== false, 'one Billing workspace tile replaces the five');
t_ok(strpos($money, "'Waiting to be billed', '/to-bill'") === false, 'the separate to-bill tile is gone from nav');
t_ok(strpos($money, "'Invoices', '/invoices'") === false, 'the separate Invoices tile is gone from nav');
t_ok(strpos($money, "'Money in', '/receipts'") === false, 'the separate Money in tile is gone from nav');
t_ok(strpos($money, "'Receivables ageing', '/receivables'") === false, 'the separate Ageing tile is gone from nav');
t_ok(strpos($money, "'Tally export', '/tally'") === false, 'the separate Tally export tile is gone from nav');
// Routes untouched — every screen still highlights Money.
foreach (['to-bill','invoices','invoice','receipts','receivables','tally'] as $rt) {
    t_ok(strpos($money, "'$rt'") !== false, "$rt still highlights Money (route intact)");
}

// The shared tab strip helper exists and covers the five tabs.
$booksui = (string)file_get_contents(__DIR__ . '/../lib/booksui.php');
t_ok(strpos($booksui, 'function billing_tabs(') !== false, 'the billing_tabs() tab strip helper exists');
foreach ([['to-bill','/to-bill'], ['invoices','/invoices'], ['receipts','/receipts'],
          ['receivables','/receivables'], ['tally','/tally']] as [$key, $href]) {
    t_ok(strpos($booksui, "'$key'") !== false && strpos($booksui, "'$href'") !== false,
        "the tab strip includes the $key tab");
}

// Each of the five views renders the tab strip, marking its own tab active.
$views = ['to_bill' => 'to-bill', 'invoices' => 'invoices', 'receipts' => 'receipts',
          'receivables' => 'receivables', 'tally_export' => 'tally'];
foreach ($views as $file => $active) {
    $v = (string)file_get_contents(__DIR__ . "/../views/ops/$file.php");
    t_ok(strpos($v, "billing_tabs('$active')") !== false, "views/ops/$file.php shows the tab strip (active: $active)");
}
