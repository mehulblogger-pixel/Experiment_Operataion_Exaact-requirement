<?php
// Sales home — the Sales Command Centre (owner, 21 Nov 2026: Stage 1 of the
// Sales redesign, from the owner's own pictures). Everything the page showed
// before is still here — greeting, target-changed notice, six quick buttons,
// the person filter, the six figures, the four-step routine, the eleven "where
// to go" tiles, data health, who does what and the colour legend — plus what the
// Sales menu page (/sales) carries: every Sales tile with its badge, and your
// ranked next actions. Every figure is read from the same registers as before
// (lib/sales_simple.php); the old page is kept in tests/fixtures/pre_stage1/.
require __DIR__ . '/_sxw_ui.php';
$u = current_user(); $c = $d['counts'];
$company = function_exists('company_name') ? company_name() : (string) (setting_get('company_name', '') ?? '');
$hour = (int) date('G'); $greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$q = $whoKey !== 'me' ? '?who=' . rawurlencode((string) $whoKey) : '';
$openV = array_sum(array_map(fn($o) => (float) $o['value'], $d['open_deals']));
//  The eleven "where to go" tiles, as before (shown unless their feature is switched off).
$tiles = [
    ['clock', 'Today', 'Daily action list', 'Everyone · daily', '/sales-today'],
    ['flow', 'Pipeline', 'Board by stage', 'Everyone · daily', '/sales-board'],
    ['chart', 'Dashboard', 'KPIs, charts, targets', 'Managers · weekly', '/sales-dashboard'],
    ['users', 'Leads', 'New enquiries', 'Sales · as they come', '/leads'],
    ['brief', 'Deals', 'Pipeline & stages', 'Sales · daily', '/opportunities'],
    ['file', (function_exists('THP') ? THP('quote') : 'Quotes'), 'GST / export quotes', 'Sales · quoting', '/quotes'],
    ['mail', (function_exists('THP') ? THP('inquiry') : 'Enquiries'), 'Requests to quote', 'Sales', '/inquiries'],
    ['rupee', 'Orders & payments', 'Won → billed → received', 'Sales + Accounts', '/sales-orders'],
    ['swap', 'Renewals', 'Contracts ending soon', 'Sales', '/sales-renewals'],
    ['target', 'Targets', 'Monthly target per person', 'Managers · monthly', '/sales-targets'],
    ['building', 'Customers', 'Companies & contacts — the one list', 'Sales + Directory', '/clients?view=sales'],
];
$area = sxw_area_tiles();
$tOn = !function_exists('feat_on') || feat_on('/sales-today');
//  With "Today" switched off the figures still show; they open the nearest screen that is on (as before).
$tUrl = fn($frag = '', $alt = '/sales-board') => $tOn ? '/sales-today' . $q . $frag : ($alt !== '' && (!function_exists('feat_on') || feat_on($alt)) ? $alt . ($alt === '/sales-board' ? $q : '') : '');
$tch = function_exists('sxt_unseen_for') ? sxt_unseen_for((int) ($u['id'] ?? 0)) : [];
$whoLab = $who && $whoKey !== 'me' ? ' · ' . ($people[$who] ?? '') : ($who ? '' : ' · everyone');
$fixN = count(array_filter($health, fn($h) => (int) $h[1] > 0));
?>
<div class="rec sxw has-bn">
  <?php sxw_nav('/sales-home'); ?>
  <?php sxw_head('Sales Command Centre', ($company !== '' ? $company . ' · ' : '') . 'Sales — leads, deals, quotations, orders, renewals and targets', 'chart'); ?>

  <div class="sxw-hello" role="<?= $tch ? 'status' : 'note' ?>">
    <span style="color:var(--warn)"><?= rec_icon($tch ? 'target' : 'spark') ?></span>
    <div class="tx"><b class="t"><?= e($greet . ', ' . (trim((string) ($u['first_name'] ?? '')) ?: user_name($u))) ?></b>
      Today is <?= e(date('l, j F Y')) ?> · everything below updates by itself.
      <?php if ($tch): ?>
        <div style="margin-top:6px"><b>Your target was changed</b></div>
        <table><?php foreach ($tch as $r): $pv = $r['prev_amount'] ?? null; ?>
          <tr><td><?= e(date('M Y', strtotime($r['ym'] . '-01'))) ?></td><td><?= $pv === null ? '<span class="muted">set to</span>' : e(sx_money($pv)) . ' →' ?> <b><?= e(sx_money($r['amount'])) ?></b></td><td class="muted">by <?= e($r['set_by']) ?> · <?= e(fdate(substr((string) $r['set_at'], 0, 10))) ?></td></tr>
        <?php endforeach; ?></table>
      <?php endif; ?>
    </div>
    <?php if ($tch): ?>
      <form method="post" action="/sales-home"><input type="hidden" name="seen" value="1">
        <?php if (!function_exists('feat_on') || feat_on('/sales-targets')): ?><a class="sxw-bt" href="/sales-targets">Open Targets</a><?php endif; ?><button class="sxw-bt p" type="submit">OK, seen</button></form>
    <?php endif; ?>
  </div>

  <div class="sxw-qt">
    <a class="p" href="/sales-lead"><?= rec_icon('plus') ?>New lead</a>
    <a href="/sales-today"><?= rec_icon('note') ?>Log activity</a>
    <a href="/opportunity-new"><?= rec_icon('brief') ?>New deal</a>
    <a href="/quote-edit"><?= rec_icon('file') ?>New <?= e(function_exists('Tl') ? Tl('quote') : 'quote') ?></a>
    <a href="/sales-orders"><?= rec_icon('trophy') ?>Orders won</a>
    <a href="/quotes"><?= rec_icon('copy') ?>Print a <?= e(function_exists('Tl') ? Tl('quote') : 'quote') ?></a>
  </div>

  <div class="sxw-hd" style="align-items:center;margin-bottom:8px">
    <div style="font-size:12px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)">Right now<?= e($whoLab) ?></div>
    <?= sxw_person('sales-home', $whoKey, $people, $seeAll) ?>
  </div>
  <?php $kn = 5 + ($d['ar'] ? 1 : 0); ?>
  <div class="sxw-kp" style="--n:<?= $kn ?>">
    <?php sxw_kpi('bad', 'cal', 'Overdue follow-ups', 'Overdue follow-ups', (string) $c['overdue'], $tOn ? 'deals + leads → open Today' : 'deals + leads', $tUrl());
          sxw_kpi('warn', 'clock', 'Due today', 'Due today', (string) $c['today'], 'deals + leads', $tUrl());
          sxw_kpi('info', 'flow', 'Open pipeline', 'Open pipeline', sx_short($openV), count($d['open_deals']) . ' open deals', '/sales-board' . $q);
          sxw_kpi('ok', 'trophy', 'Won this FY', 'Won this FY', sx_short($wonFy), 'since the start of the year', '/sales-dashboard' . $q);
          if ($d['ar']) sxw_kpi('bad', 'rupee', 'Overdue payments', 'Overdue payments', sx_short($d['overdue_amt']), $d['overdue_n'] . ' invoice' . ($d['overdue_n'] === 1 ? '' : 's'), $tUrl('#pay', ''));
          sxw_kpi('teal', 'swap', 'Renewals due', 'Renewals due', (string) count($d['renewals']), 'in ' . (function_exists('contract_warn_days') ? contract_warn_days() : 30) . ' days', $tUrl('#renew', '/sales-renewals')); ?>
  </div>

  <div class="sxw-g2 w">
    <?php rec_card_open('Your daily routine · 4 steps, 15 minutes', 'flow'); ?>
      <div class="sxw-rt">
        <a href="/sales-today<?= e($q) ?>"><i>1</i>Open Today ›<small>Who to call, which deals are stuck, which payment to chase.</small></a>
        <a href="/sales-today<?= e($q) ?>"><i>2</i>Take action ›<small>WhatsApp or call straight from the list.</small></a>
        <a href="/sales-today<?= e($q) ?>"><i>3</i>Log it (1 min) ›<small>Press Log on the row, pick what happened — the next date fills itself in.</small></a>
        <a href="/sales-board<?= e($q) ?>"><i>4</i>Update the deal ›<small>Move the stage; won? Raise the order.</small></a>
      </div>
    <?php rec_card_close(); ?>
    <?php rec_card_open('Where to go', 'list'); ?>
      <?php //  Test-book finding F-02 (21 Nov 2026): a tile is shown only when this person may open
            //  its screen — the Sales area's own tile rules (lib/areas.php, the same gates as the
            //  routes and the Sales menu page, plus the route's module gate — sxw_area_tiles()) — not
            //  to everyone. Feature switches as before.
            $shown = array_values(array_filter($tiles, fn($t) => isset($area[$t[4]]) && (!function_exists('feat_on') || feat_on($t[4])))); ?>
      <div class="sxw-gt" style="--n:6">
        <?php foreach ($shown as [$ic, $t, $s, $who2, $href]): $cn = $area[$href]['count'] ?? null; $tone = $area[$href]['tone'] ?? ''; ?>
          <a href="<?= e($href) ?>" title="<?= e($s . ' · ' . $who2) ?>"><?= rec_icon($ic, 's') ?><?= e($t) ?><?= $cn ? '<em class="' . e($tone) . '">' . (int) $cn . '</em>' : '' ?></a>
        <?php endforeach; ?>
      </div>
    <?php rec_card_close(); ?>
  </div>

  <div class="sxw-g2">
    <?php $acts = function_exists('action_centre') ? action_centre(4) : [];
          rec_card_open('Your next actions', 'task', '<a class="rec-lnk" href="/my-work">My Work ' . rec_icon('right', 's') . '</a>'); ?>
      <?php if (!$acts): ?><p class="sxw-empty">Nothing waiting for you right now.</p><?php endif; ?>
      <?php foreach ($acts as $a): ?>
        <a class="sxw-li" style="grid-template-columns:10px minmax(0,1fr) auto;color:var(--ink)" href="<?= e($a['href']) ?>">
          <span class="sxw-dot <?= e(['bad' => 'bad', 'warn' => 'warn'][$a['tone']] ?? '') ?>"></span><b style="font-weight:600"><?= e($a['title']) ?></b>
          <?= !empty($a['overdue']) ? '<span class="rec-tag bad">overdue</span>' : rec_icon('right', 's') ?></a>
      <?php endforeach; ?>
      <p class="rec-note">From your whole workspace, most urgent first — the same list as My Work. The workspace pulse is on the <a href="/command-centre">Command Centre</a>; every Sales screen is also on the <a href="/sales">Sales menu page</a>.</p>
    <?php rec_card_close(); ?>
    <?php rec_card_open('Data health · fix these to keep reports trustworthy', 'alert', $fixN ? '<span class="rec-tag warn">' . $fixN . ' to fix</span>' : '<span class="rec-tag ok">all clear</span>'); ?>
      <?php foreach ($health as [$lab, $n, $href]): ?>
        <div class="sxw-li" style="grid-template-columns:minmax(0,1fr) 40px 44px"><span style="font-size:13px"><?= e($lab) ?></span>
          <span class="sxw-due <?= $n ? ($n > 2 ? 'p-bad' : 'p-warn') : 'p-ok' ?>" style="justify-content:center"><?= (int) $n ?></span>
          <a class="sxw-fix" href="<?= e($href) ?>">Fix ›</a></div>
      <?php endforeach; ?>
    <?php rec_card_close(); ?>
  </div>

  <?php //  The rest of the Sales menu page's tiles (lib/areas.php): set-up screens and tools, same rules and badges.
        $homeR = array_flip(array_map(fn($t) => $t[4], $tiles)) + ['/sales-home' => 1, '/sales-lead' => 1];
        $tools = array_values(array_filter($area, fn($t) => !isset($homeR[$t['route']])));
        $icMap = ['/sales-settings' => 'cal', '/templates?kind=quote' => 'file', '/sales-setup' => 'task', '/preorder-checklist' => 'checkc', '/project-costings' => 'calc', '/approvals' => 'lock', '/pipelines' => 'flow', '/ads-roi' => 'trend'];
        if ($tools): rec_card_open('Set up & tools', 'spark', '<span class="rec-note" style="margin:0">only the ones you may open</span>'); ?>
      <div class="sxw-gt" style="--n:<?= min(8, max(4, count($tools))) ?>">
        <?php foreach ($tools as $t): ?>
          <a href="<?= e($t['route']) ?>" title="<?= e((string) $t['desc']) ?>"<?= !empty($t['ext']) ? ' target="_blank" rel="noopener"' : '' ?>><?= rec_icon($icMap[$t['route']] ?? 'list', 's') ?><?= e($t['label']) ?><?= !empty($t['count']) ? '<em class="' . e($t['tone']) . '">' . (int) $t['count'] . '</em>' : '' ?></a>
        <?php endforeach; ?>
      </div>
    <?php rec_card_close(); endif; ?>

  <details class="sxw-fold"><summary><?= rec_icon('users', 's') ?><span class="x">Who does what</span><span class="chev"><?= rec_icon('down', 's') ?></span></summary>
    <div class="fb"><table class="rec-tbl">
      <tr><td><b>Sales executive</b></td><td>Today → act → log it → update the deal.</td></tr>
      <tr><td><b>Sales manager</b></td><td>Today's "who has what"; weekly Dashboard with each person.</td></tr>
      <tr><td><b>Accounts</b></td><td>Record each payment; collections update everywhere.</td></tr>
      <tr><td><b>Admin</b></td><td>Once: stages and win %, sources, lost reasons, team.</td></tr>
    </table></div></details>
  <div class="sxw-legend"><b style="color:var(--ink)">Status:</b><span><i style="background:var(--bad)"></i>Overdue / problem</span><span><i style="background:var(--warn)"></i>Due today / warning</span><span><i style="background:#c8a800"></i>This week</span><span><i style="background:var(--ok)"></i>On track / won</span><span><i style="background:var(--muted)"></i>Grey · filled in automatically</span></div>
</div>
<?php sxw_bottom('/sales-home', (int) $c['overdue'] + (int) $c['today']); ?>
