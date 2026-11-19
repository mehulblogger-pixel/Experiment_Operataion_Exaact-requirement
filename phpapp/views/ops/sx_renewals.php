<?php
// Simple Sales — Renewals (the owner's Excel RENEWALS sheet): contracts by days
// left, plus those ended in the last 30 days (still worth a call).
require __DIR__ . '/_sx_ui.php';
$t = strtotime(date('Y-m-d')); $c = ['late' => 0, 'm' => 0, 'later' => 0, 'v' => 0.0];
foreach ($rows as $r) { $left = (int) round((strtotime($r['end_date']) - $t) / 86400); $c[$left < 0 ? 'late' : ($left <= 30 ? 'm' : 'later')]++; $c['v'] += (float) $r['value']; }
?>
<div class="sx">
  <div class="sxhead"><h1>🔁 Renewals</h1>
    <nav class="sxchips" aria-label="Look ahead"><?php foreach ([30, 60, 90, 180] as $d): ?><a class="sxchip<?= $days === $d ? ' on' : '' ?>" href="/sales-renewals?d=<?= $d ?>">Next <?= $d ?> days</a><?php endforeach; ?></nav></div>
  <p class="sxwhy"><b>Why you're here:</b> contracts ending soon. <b>What to do:</b> call 60–90 days before the end date, send the renewal <?= e(Tl('quote')) ?>, and log it.</p>
  <div class="sxkpis">
    <?php sx_kpi('red', '⛔ Ended (last 30 days)', (string) $c['late'], 'call now');
          sx_kpi('orange', '🟠 Ending in 30 days', (string) $c['m'], 'renewal quote');
          sx_kpi('yellow', '🟡 Later', (string) $c['later'], 'within ' . $days . ' days');
          sx_kpi('green', '💰 Their value', sx_short($c['v']), 'contract values'); ?>
  </div>
  <div class="sxcard"><h2>Contracts</h2>
    <?php if (!$rows): ?><p class="sxempty">Nothing ends in the next <?= (int) $days ?> days.</p><?php else: ?>
    <div class="tbl"><table class="sxt">
      <tr><th>Contract</th><th><?= e(T('client')) ?></th><th>Ends</th><th>Days left</th><th class="n">Value</th><th></th></tr>
      <?php foreach ($rows as $r): $left = (int) round((strtotime($r['end_date']) - $t) / 86400); ?>
        <tr><td><a href="/contract?id=<?= (int) $r['id'] ?>"><?= e($r['contract_number'] ?: $r['title']) ?></a><?= ($r['title'] ?? '') !== '' && $r['contract_number'] ? '<br><span class="muted" style="font-size:12px">' . e($r['title']) . '</span>' : '' ?></td>
          <td><?= $r['partner_id'] ? '<a href="/partner?id=' . (int) $r['partner_id'] . '">' . e($r['client_name'] ?? '') . '</a>' : '—' ?></td>
          <td class="nw"><?= e(fdate($r['end_date'])) ?></td>
          <td><span class="sxp <?= $left < 0 ? 'p-bad' : ($left <= 30 ? 'p-warn' : ($left <= 60 ? 'p-week' : 'p-mut')) ?>"><?= $left < 0 ? 'ended ' . -$left . ' d ago' : ($left === 0 ? 'ends today' : $left . ' days') ?></span></td>
          <td class="n"><?= (float) $r['value'] > 0 ? e(sx_money($r['value'])) : '—' ?></td>
          <td class="nw"><?php if ($r['partner_id'] && (can('crm.quote.create') || can('mod.quotes.edit') || is_master_of('quotes'))): ?><a class="sxc" href="/quote-new?client=<?= (int) $r['partner_id'] ?>">🧾 Renewal <?= e(Tl('quote')) ?></a><?php endif; ?></td></tr>
      <?php endforeach; ?>
    </table></div><?php endif; ?></div>
</div>
