<?php
// Orders & payments (owner, 21 Nov 2026: Stage 5 of the Sales redesign). The same
// orders (accepted quotations), from the same sx_orders(); the old page is kept in
// tests/fixtures/pre_stage5/. What is new is decided by the owner:
//   DEF-06 — the order's value BEFORE GST is the main figure, with GST beside it;
//   DEF-07 — "Received" = payments received + the TDS actually deducted on them;
//            credit notes are shown apart (they reduce what is owed, they are not money in);
//   item 15 — the same list as a CSV.
// Billing and payment figures only for people who may see receivables (ar_can), as before.
require __DIR__ . '/_sxw_ui.php';
$q = $whoKey !== 'me' ? '&who=' . rawurlencode((string) $whoKey) : '';
$tot = ['v' => 0.0, 'vg' => 0.0, 'b' => 0.0, 'r' => 0.0, 'cn' => 0.0, 'tds' => 0.0];
foreach ($rows as $o) {
    $tot['v'] += (float) ($o['_value'] ?? $o['total_amount']); $tot['vg'] += (float) $o['total_amount'];
    $tot['b'] += (float) ($o['_billed'] ?? 0); $tot['r'] += (float) ($o['_recv'] ?? 0); $tot['cn'] += (float) ($o['_credit'] ?? 0); $tot['tds'] += (float) ($o['_tds'] ?? 0);
}
$overAmt = 0.0; $overN = 0; if ($overdue) foreach ($overdue as $r) if ((int) $r['age_days'] > 0) { $overAmt += (float) $r['amount']; $overN++; }
$periods = ['fy' => 'This FY', 'quarter' => 'Quarter', 'month' => 'Month', '90' => 'Last 90 days'];
$per = '<nav class="sxw-seg" aria-label="Period">';
foreach ($periods as $k => $lab) $per .= '<a href="/sales-orders?p=' . e($k . $q) . '"' . ($p === $k ? ' class="on" aria-current="true"' : '') . '>' . e($lab) . '</a>';
$per .= '</nav>';
$csvHref = '/sales-orders?p=' . rawurlencode($p) . $q . '&export=csv';
//  Owner, 22 Nov 2026 (findings IP-01, design request 7c): every card opens its list. The
//  first four narrow this page's own order list (the totals above are unchanged); the money
//  figures that live in Receivables open Receivables, as Overdue always did.
$stF = ['tobill' => 'still to bill', 'received' => 'with money received', 'credit' => 'reduced by a credit note'];
$stSel = isset($stF[$_GET['st'] ?? '']) ? (string) $_GET['st'] : '';
$stHref = fn($s) => '/sales-orders?p=' . rawurlencode($p) . $q . ($s !== '' ? '&st=' . $s : '') . '#orders';
$listRows = array_values(array_filter($rows, function ($o) use ($stSel) {
    $vg = (float) $o['total_amount']; $b = (float) ($o['_billed'] ?? 0);
    if ($stSel === 'tobill')   return $vg - $b > 0.5;
    if ($stSel === 'received') return (float) ($o['_recv'] ?? 0) > 0;
    if ($stSel === 'credit')   return (float) ($o['_credit'] ?? 0) > 0;
    return true;
}));
?>
<div class="rec sxw has-bn">
  <?php sxw_nav('/sales-orders'); ?>
  <?php sxw_head('Orders & payments', 'Every accepted ' . Tl('quote') . ' in the period: what was ordered, what has been billed, what has come in. Bill what is still to bill; chase what is overdue.', 'rupee',
        '<div class="sxw-ctl">' . $per . sxw_person('sales-orders', $whoKey, $people, $seeAll, '&p=' . rawurlencode($p))
        . ($rows ? '<a class="sxw-bt" href="' . e($csvHref) . '">' . rec_icon('dl', 's') . 'CSV</a>' : '') . '</div>'); ?>

  <div class="sxw-kp" style="--n:<?= $ar ? 4 : 1 ?>">
    <?php sxw_kpi('ok', 'trophy', 'Orders won', 'Orders won', sx_short($tot['v']), count($rows) . ' order' . (count($rows) === 1 ? '' : 's') . ' · before GST · ' . $plab, $stHref(''));
          if ($ar) {
              sxw_kpi('warn', 'file', 'Still to bill', 'Still to bill', sx_short(max(0, $tot['vg'] - $tot['b'])), 'order in hand · with GST', $stHref('tobill'));
              sxw_kpi('teal', 'checkc', 'Received', 'Received', sx_short($tot['r']), 'payments + TDS deducted' . ($tot['tds'] > 0 ? ' (TDS ' . sx_short($tot['tds']) . ')' : ''), $stHref('received'));
              sxw_kpi('grey', 'swap', 'Reduced by credit note', 'Reduced by credit note', sx_short($tot['cn']), 'shown apart — not money in', $stHref('credit'));
              if ($recvMonth !== null) sxw_kpi('info', 'cal', 'Received this month', 'Received this month', sx_short($recvMonth), 'payments · ' . ($collectHint ?? 'all customers'), '/receivables');
              sxw_kpi('bad', 'alert', 'Overdue', 'Overdue', sx_short($overAmt), $overN . ' invoice' . ($overN === 1 ? '' : 's'), '/receivables');
              sxw_kpi('grey', 'hour', 'Days to get paid', 'Days to get paid', $avgPay !== null ? $avgPay . ' d' : '—', 'average · last 6 months · ' . ($collectHint ?? 'all customers'), '/receivables');
          } ?>
  </div>
  <?php if (!$ar): ?><p class="muted" style="margin:10px 0 0">Billed and received amounts are shown to people who may see receivables.</p>
  <?php else: ?><p class="muted" style="margin:10px 0 0;font-size:12.5px"><b>Received</b> = payments received plus the TDS the <?= e(Tl('client')) ?> deducted on them (only where TDS was actually deducted). Credit notes reduce what is owed and are shown apart. <b>Order</b> is before GST; billed, received and to bill are invoice amounts, with GST.</p><?php endif; ?>

  <div id="orders"></div>
  <?php rec_card_open('Orders · ' . $plab . ($stSel !== '' ? ' · ' . $stF[$stSel] : ''), 'list'); ?>
    <?php if ($stSel !== ''): ?><p class="muted" style="margin:0 0 8px;font-size:12.5px">Showing <?= count($listRows) ?> of <?= count($rows) ?> orders <?= e($stF[$stSel]) ?>. <a href="<?= e($stHref('')) ?>">Show all</a></p><?php endif; ?>
    <?php if (!$rows): ?><p class="sxw-empty">No accepted <?= e(Tlp('quote')) ?> in this period.</p><?php else: ?>
    <div class="rec-scroll"><table class="rec-tbl">
      <thead><tr><th><?= e(T('quote')) ?></th><th><?= e(T('client')) ?></th><th>PO</th><th>Won on</th><th class="n">Order · before GST</th>
        <?php if ($ar): ?><th class="n">Billed</th><th class="n">Received</th><th class="n">Credit notes</th><th class="n">To bill</th><th>Status</th><th>Invoices</th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($listRows as $o): $v = (float) ($o['_value'] ?? $o['total_amount']); $vg = (float) $o['total_amount']; $b = (float) ($o['_billed'] ?? 0); $r = (float) ($o['_recv'] ?? 0); $cn = (float) ($o['_credit'] ?? 0);
            //  Settled = received + credit notes (what is no longer owed on the billed amount).
            $st = $b <= 0.005 ? ['Not billed yet', 'p-warn'] : ($r + $cn + 0.5 >= $b ? ($b + 0.5 >= $vg ? ['Fully paid', 'p-ok'] : ['Paid so far', 'p-ok']) : ['Payment due', 'p-bad']); ?>
        <tr><td><a href="/quote?id=<?= (int) $o['id'] ?>"><b><?= e($o['quote_no']) ?></b><?= (int) $o['rev'] ? ' R' . (int) $o['rev'] : '' ?></a></td>
          <td><?= $o['client_id'] ? '<a href="/partner?id=' . (int) $o['client_id'] . '&amp;from=sales">' . e($o['cname']) . '</a>' : e($o['cname']) ?></td>
          <td class="nw"><?= e(trim((string) ($o['po_number'] ?? '')) ?: '—') ?><?= ($o['po_date'] ?? '') !== '' ? '<br><span class="muted" style="font-size:12px">' . e(fdate($o['po_date'])) . '</span>' : '' ?></td>
          <td class="nw"><?= e(fdate(substr((string) ($o['accepted_date'] ?: $o['updated_at']), 0, 10))) ?></td>
          <td class="n"><b><?= e(sx_money($v)) ?></b><?php if (abs($vg - $v) > 0.5): ?><br><span class="muted" style="font-size:12px"><?= e(sx_money($vg)) ?> incl. GST</span><?php endif; ?></td>
          <?php if ($ar): ?><td class="n"><?= $b > 0 ? e(sx_money($b)) : '—' ?></td>
            <td class="n"><?= $r > 0 ? e(sx_money($r)) : '—' ?><?php if ((float) ($o['_tds'] ?? 0) > 0): ?><br><span class="muted" style="font-size:12px">incl. TDS <?= e(sx_money($o['_tds'])) ?></span><?php endif; ?></td>
            <td class="n"><?= $cn > 0 ? e(sx_money($cn)) : '—' ?></td>
            <td class="n"><?= $vg - $b > 0.5 ? e(sx_money($vg - $b)) : '—' ?></td><td><span class="sxw-due <?= e($st[1]) ?>"><?= e($st[0]) ?></span></td>
            <td class="nw"><?php foreach ($o['_invs'] ?? [] as $iv): ?><a href="/invoice?id=<?= (int) $iv['id'] ?>"><?= e($iv['invoice_no']) ?></a><br><?php endforeach; ?><?= empty($o['_invs']) ? '—' : '' ?></td><?php endif; ?></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <p class="muted" style="font-size:12px;margin:8px 0 0">Rows: <?= e(Tlp('quote')) ?> of your branches<?= $ar ? ' · billing and payments: the receivables right' : '' ?>.</p>
    <?php endif; ?>
  <?php rec_card_close(); ?>
</div>
<?php sxw_bottom('/sales-orders'); ?>
