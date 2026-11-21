<?php
// Simple Sales — Orders & payments (the owner's Excel ORDERS sheet): accepted
// quotations → invoiced against them → received. Billing columns only for people
// who may see receivables (ar_can), as the receivables screen decides.
require __DIR__ . '/_sx_ui.php';
$q = $whoKey !== 'me' ? '&who=' . rawurlencode((string) $whoKey) : '';
$tot = ['v' => 0.0, 'b' => 0.0, 'r' => 0.0];
foreach ($rows as $o) { $tot['v'] += (float) $o['total_amount']; $tot['b'] += (float) ($o['_billed'] ?? 0); $tot['r'] += (float) ($o['_recv'] ?? 0); }
$overAmt = 0.0; $overN = 0; if ($overdue) foreach ($overdue as $r) if ((int) $r['age_days'] > 0) { $overAmt += (float) $r['amount']; $overN++; }
?>
<div class="sx">
  <div class="sxhead"><h1>💰 Orders &amp; payments</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <nav class="sxchips" aria-label="Period"><?php foreach (['fy' => 'This FY', 'quarter' => 'Quarter', 'month' => 'Month', '90' => '90 days'] as $k => $lab): ?><a class="sxchip<?= $p === $k ? ' on' : '' ?>" href="/sales-orders?p=<?= e($k) . e($q) ?>"><?= e($lab) ?></a><?php endforeach; ?></nav>
      <?php sx_person_chips('sales-orders', $whoKey, $people, $seeAll, '&p=' . rawurlencode($p)); ?></div></div>
  <p class="sxwhy"><b>Why you're here:</b> every order won (an accepted <?= e(Tl('quote')) ?>) and how much of it has been billed and paid. <b>What to do:</b> bill what is still to bill, chase what is overdue. Amounts include GST.</p>
  <div class="sxkpis">
    <?php sx_kpi('green', '🏆 Orders won', sx_short($tot['v']), count($rows) . ' order' . (count($rows) === 1 ? '' : 's') . ' · ' . $plab);
          if ($ar) { sx_kpi('blue', '🧾 Still to bill', sx_short(max(0, $tot['v'] - $tot['b'])), 'order in hand');
                     sx_kpi('teal', '✅ Received', sx_short($tot['r']), 'against these orders');
                     if ($recvMonth !== null) sx_kpi('slate', '📅 Received this month', sx_short($recvMonth), $collectHint ?? 'all customers');
                     sx_kpi('red', '⏰ Overdue', sx_short($overAmt), $overN . ' invoice' . ($overN === 1 ? '' : 's'), '/receivables');
                     sx_kpi('purple', '⌛ Days to get paid', $avgPay !== null ? $avgPay . ' d' : '—', 'average · last 6 months · ' . ($collectHint ?? 'all customers')); } ?>
  </div>
  <?php if (!$ar): ?><p class="muted" style="margin-top:10px">Billed and received amounts are shown to people who may see receivables.</p><?php endif; ?>
  <div class="sxcard"><h2>Orders · <?= e($plab) ?></h2>
    <?php if (!$rows): ?><p class="sxempty">No accepted <?= e(Tlp('quote')) ?> in this period.</p><?php else: ?>
    <div class="tbl"><table class="sxt">
      <tr><th><?= e(T('quote')) ?></th><th><?= e(T('client')) ?></th><th>PO</th><th>Won on</th><th class="n">Order (incl. GST)</th><?php if ($ar): ?><th class="n">Billed</th><th class="n">Received</th><th class="n">To bill</th><th>Status</th><?php endif; ?></tr>
      <?php foreach ($rows as $o): $v = (float) $o['total_amount']; $b = (float) ($o['_billed'] ?? 0); $r = (float) ($o['_recv'] ?? 0);
            $st = $b <= 0.005 ? ['Not billed yet', 'p-warn'] : ($r + 0.5 >= $b ? ($b + 0.5 >= $v ? ['Fully paid', 'p-ok'] : ['Paid so far', 'p-ok']) : ['Payment due', 'p-bad']); ?>
        <tr><td><a href="/quote?id=<?= (int) $o['id'] ?>"><?= e($o['quote_no']) ?><?= (int) $o['rev'] ? ' R' . (int) $o['rev'] : '' ?></a></td>
          <td><?= $o['client_id'] ? '<a href="/partner?id=' . (int) $o['client_id'] . '">' . e($o['cname']) . '</a>' : e($o['cname']) ?></td>
          <td class="nw"><?= e(trim(($o['po_number'] ?? '') . (($o['po_date'] ?? '') !== '' ? ' · ' . fdate($o['po_date']) : ''), ' ·') ?: '—') ?></td>
          <td class="nw"><?= e(fdate(substr((string) ($o['accepted_date'] ?: $o['updated_at']), 0, 10))) ?></td>
          <td class="n"><?= e(sx_money($v)) ?></td>
          <?php if ($ar): ?><td class="n"><?= $b > 0 ? e(sx_money($b)) : '—' ?></td><td class="n"><?= $r > 0 ? e(sx_money($r)) : '—' ?></td>
            <td class="n"><?= $v - $b > 0.5 ? e(sx_money($v - $b)) : '—' ?></td><td><span class="sxp <?= e($st[1]) ?>"><?= e($st[0]) ?></span></td><?php endif; ?></tr>
      <?php endforeach; ?>
    </table></div><?php endif; ?></div>
</div>
