<?php
// Simple Sales — Sales home (the owner's Excel HOME sheet). Every figure is read
// from the existing registers (lib/sales_simple.php); every card and tile opens
// the screen where that work is done.
require __DIR__ . '/_sx_ui.php';
$u = current_user(); $c = $d['counts'];
$company = function_exists('company_name') ? company_name() : (string) (setting_get('company_name', '') ?? '');
$hour = (int) date('G'); $greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$q = $whoKey !== 'me' ? '?who=' . rawurlencode((string) $whoKey) : '';
$openV = array_sum(array_map(fn($o) => (float) $o['value'], $d['open_deals']));
$tiles = [
    ['sx-red', '⏰ Today', 'Daily action list', 'Everyone · daily', '/sales-today'],
    ['sx-blue', '🧭 Pipeline', 'Board by stage', 'Everyone · daily', '/sales-board'],
    ['sx-slate', '📊 Dashboard', 'KPIs, charts, targets', 'Managers · weekly', '/sales-dashboard'],
    ['sx-teal', '🧲 Leads', 'New enquiries', 'Sales · as they come', '/leads'],
    ['sx-green', '💼 Deals', 'Pipeline & stages', 'Sales · daily', '/opportunities'],
    ['sx-orange', '🧾 ' . (function_exists('THP') ? THP('quote') : 'Quotes'), 'GST / export quotes', 'Sales · quoting', '/quotes'],
    ['sx-purple', '📨 ' . (function_exists('THP') ? THP('inquiry') : 'Enquiries'), 'Requests to quote', 'Sales', '/inquiries'],
    ['sx-brown', '💰 Orders & payments', 'Won → billed → received', 'Sales + Accounts', '/sales-orders'],
    ['sx-pink', '🔁 Renewals', 'Contracts ending soon', 'Sales', '/sales-renewals'],
    ['sx-teal', '🎯 Targets', 'Monthly target per person', 'Managers · monthly', '/sales-targets'],
    ['sx-slate', '🏢 Customers', 'Companies & contacts — the one list', 'Sales + Directory', '/clients?view=sales'],
];
?>
<div class="sx">
  <div class="sxband">
    <h1><?= e($company !== '' ? $company . ' · ' : '') ?>Sales</h1>
    <p>👋 <?= e($greet . ', ' . (trim((string) ($u['first_name'] ?? '')) ?: user_name($u))) ?>. Today is <?= e(date('l, j F Y')) ?> · everything below updates by itself.</p>
  </div>
  <div class="sxqa">
    <a href="/sales-lead">➕ New lead</a><a href="/sales-today">📝 Log activity</a><a href="/opportunity-new">💼 New deal</a>
    <a href="/quote-edit">🧾 New <?= e(function_exists('Tl') ? Tl('quote') : 'quote') ?></a><a href="/sales-orders">💰 Orders won</a><a href="/quotes">🖨 Print a <?= e(function_exists('Tl') ? Tl('quote') : 'quote') ?></a>
  </div>
  <div class="sxhead"><div class="sxlab" style="margin:0">⚡ Right now<?= $who && $whoKey !== 'me' ? ' · ' . e($people[$who] ?? '') : ($who ? '' : ' · everyone') ?></div>
    <?php sx_person_chips('sales-home', $whoKey, $people, $seeAll); ?></div>
  <div class="sxkpis" style="margin-top:8px">
    <?php sx_kpi('red', '🔴 Overdue follow-ups', (string) $c['overdue'], '→ open Today', '/sales-today' . $q);
          sx_kpi('orange', '🟠 Due today', (string) $c['today'], 'deals + leads', '/sales-today' . $q);
          sx_kpi('blue', '💼 Open pipeline', sx_short($openV), count($d['open_deals']) . ' open deals', '/sales-board' . $q);
          sx_kpi('green', '🏆 Won this FY', sx_short($wonFy), 'since the start of the year', '/sales-dashboard' . $q);
          if ($d['ar']) sx_kpi('brown', '💰 Overdue payments', sx_short($d['overdue_amt']), $d['overdue_n'] . ' invoice' . ($d['overdue_n'] === 1 ? '' : 's'), '/sales-today' . $q . '#pay');
          sx_kpi('pink', '🔁 Renewals due', (string) count($d['renewals']), 'in ' . (function_exists('contract_warn_days') ? contract_warn_days() : 30) . ' days', '/sales-today' . $q . '#renew'); ?>
  </div>

  <div class="sxlab">🧭 Your daily routine · 4 steps, 15 minutes</div>
  <div class="sxsteps">
    <a class="sxst" href="/sales-today<?= e($q) ?>"><b class="sx-red">1 ⏰ Open Today ›</b><span>Who to call, which deals are stuck, which payment to chase.</span></a>
    <a class="sxst" href="/sales-today<?= e($q) ?>"><b class="sx-orange">2 📞 Take action ›</b><span>WhatsApp or call straight from the list.</span></a>
    <a class="sxst" href="/sales-today<?= e($q) ?>"><b class="sx-yellow">3 📝 Log it (1 min) ›</b><span>Press 📝 Log on the row, pick what happened — the next date fills itself in.</span></a>
    <a class="sxst" href="/sales-board<?= e($q) ?>"><b class="sx-green">4 💼 Update the deal ›</b><span>Move the stage; won? Raise the order.</span></a>
  </div>

  <div class="sxlab">🗂 Where to go</div>
  <div class="sxtiles">
    <?php foreach ($tiles as [$tone, $t, $s, $who2, $href]): if (function_exists('feat_on') && !feat_on($href)) continue; ?>
      <a class="sxtile" href="<?= e($href) ?>"><b class="<?= e($tone) ?>"><?= e($t) ?></b><span><?= e($s) ?></span><i><?= e($who2) ?></i></a>
    <?php endforeach; ?>
  </div>

  <div class="sxgrid2" style="margin-top:14px">
    <div class="sxcard"><h2>🩺 Data health · fix these to keep reports trustworthy</h2>
      <div class="tbl"><table class="sxt">
        <?php foreach ($health as [$lab, $n, $href]): ?>
          <tr><td><?= e($lab) ?></td><td class="n"><span class="sxp <?= $n ? ($n > 2 ? 'p-bad' : 'p-warn') : 'p-ok' ?>"><?= (int) $n ?></span></td><td class="n"><a class="sxfix" href="<?= e($href) ?>">Fix ›</a></td></tr>
        <?php endforeach; ?>
      </table></div></div>
    <div class="sxcard"><h2>👥 Who does what</h2>
      <div class="tbl"><table class="sxt">
        <tr><td><b>Sales executive</b></td><td>Today → act → log it → update the deal.</td></tr>
        <tr><td><b>Sales manager</b></td><td>Today's "who has what"; weekly Dashboard with each person.</td></tr>
        <tr><td><b>Accounts</b></td><td>Record each payment; collections update everywhere.</td></tr>
        <tr><td><b>Admin</b></td><td>Once: stages and win %, sources, lost reasons, team.</td></tr>
      </table></div></div>
  </div>
  <div class="sxlegend"><span class="sxp p-bad">🔴 Overdue / problem</span><span class="sxp p-warn">🟠 Due today / warning</span><span class="sxp p-week">🟡 This week</span><span class="sxp p-ok">🟢 On track / won</span><span class="sxp p-mut">Grey · filled in automatically</span></div>
</div>
