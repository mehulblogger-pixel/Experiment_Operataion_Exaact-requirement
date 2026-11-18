<?php
// Simple Sales — Today (the owner's Excel TODAY sheet): everything that needs
// attention today, worked top to bottom. Read from the existing leads, deals,
// receivables and contracts; every row opens its own record.
require __DIR__ . '/_sx_ui.php';
$c = $d['counts']; $q = $whoKey !== 'me' ? '?who=' . rawurlencode((string) $whoKey) : '';
?>
<div class="sx">
  <div class="sxhead"><h1>⏰ Today · <?= e(date('l, j M Y')) ?></h1><?php sx_person_chips('sales-today', $whoKey, $people, $seeAll); ?></div>
  <p class="sxwhy"><b>Why you're here:</b> everything that needs attention today, in one place. <b>What to do:</b> 1) work top to bottom 2) 💬 / 📞 to contact 3) 📝 Log what happened — the next follow-up date fills itself in.</p>
  <div class="sxkpis">
    <?php sx_kpi('red', '🔴 Overdue', (string) $c['overdue'], 'deals + leads', '#deals');
          sx_kpi('orange', '🟠 Due today', (string) $c['today'], 'deals + leads', '#deals');
          sx_kpi('yellow', '🟡 This week', (string) $c['week'], 'next 7 days', '#deals');
          sx_kpi('purple', '❗ No next step', (string) $c['none'], 'set a date!', '#none');
          sx_kpi('slate', '💤 Stale deals', (string) $c['stale'], 'no activity > ' . sx_stale_days() . ' days', '#stale');
          if ($d['ar']) sx_kpi('green', '💰 Overdue ₹', sx_short($d['overdue_amt']), $d['overdue_n'] . ' invoice' . ($d['overdue_n'] === 1 ? '' : 's'), '#pay');
          sx_kpi('pink', '🔁 Renewals', (string) count($d['renewals']), 'in ' . (function_exists('contract_warn_days') ? contract_warn_days() : 30) . ' days', '#renew'); ?>
  </div>

  <?php if ($seeAll && $whoRows): ?>
  <div class="sxcard"><h2>👥 Who has what today</h2><div class="tbl"><table class="sxt">
    <tr><th>Salesperson</th><th>🔴 Overdue</th><th>🟠 Today</th><th>🟡 This week</th><th>❗ No next step</th><th>💤 Stale</th></tr>
    <?php foreach ($whoRows as $r): ?>
      <tr><td><a href="/sales-today?who=<?= (int) $r['id'] ?>"><?= e($r['name']) ?></a></td>
        <td><?= $r['overdue'] ? '<span class="sxp p-bad">' . (int) $r['overdue'] . '</span>' : '—' ?></td>
        <td><?= $r['today'] ? '<span class="sxp p-warn">' . (int) $r['today'] . '</span>' : '—' ?></td>
        <td><?= $r['week'] ? (int) $r['week'] : '—' ?></td>
        <td><?= $r['none'] ? '<span class="sxp p-warn">' . (int) $r['none'] . '</span>' : '—' ?></td>
        <td><?= $r['stale'] ? (int) $r['stale'] : '—' ?></td></tr>
    <?php endforeach; ?>
  </table></div></div>
  <?php endif; ?>

  <div class="sxcard" id="deals"><h2>💼 Deal follow-ups</h2>
    <?php if (!$d['deals']): ?><p class="sxempty">Nothing due. 👍</p><?php else: ?>
    <div class="tbl"><table class="sxt">
      <tr><th>When</th><th>Deal</th><th>Customer</th><th>Stage</th><th class="n">Value</th><th>Next step</th><th>Idle</th><th></th></tr>
      <?php foreach ($d['deals'] as $o): [$lab, $cls] = sx_due_label($o['next_action_on'] ?? ''); ?>
        <tr<?= $o['_due'] === 'none' ? ' id="none"' : '' ?>><td><span class="sxp <?= e($cls) ?>"><?= e($lab) ?></span></td>
          <td><a href="/opportunity?id=<?= (int) $o['id'] ?>"><?= e($o['ref']) ?></a> <?= e($o['name']) ?></td>
          <td><?= e($o['client_name'] ?: $o['partner_name']) ?></td><td><?= e($o['stage_name']) ?></td>
          <td class="n"><?= e(sx_money($o['value'])) ?></td><td><?= e($o['next_action'] ?: '—') ?></td>
          <td class="nw"><?= $o['_idle'] !== null ? (int) $o['_idle'] . ' d' : '—' ?></td>
          <td class="nw"><?= sx_contact_btns($o['contact_phone'] ?? '', $o['name']) ?> <a class="sxc" href="/sales-log?deal=<?= (int) $o['id'] ?>" aria-label="Log activity on <?= e($o['ref']) ?>">📝 Log</a></td></tr>
      <?php endforeach; ?>
    </table></div><?php endif; ?></div>

  <div class="sxgrid2" style="margin-top:12px">
    <div class="sxcard"><h2>🧲 Lead follow-ups</h2>
      <?php if (!$d['leads']): ?><p class="sxempty">Nothing due.</p><?php else: ?>
      <div class="tbl"><table class="sxt">
        <?php foreach ($d['leads'] as $l): [$lab, $cls] = sx_due_label($l['next_action_on'] ?? ''); ?>
          <tr><td><span class="sxp <?= e($cls) ?>"><?= e($lab) ?></span></td>
            <td><a href="/lead?id=<?= (int) $l['id'] ?>"><?= e($l['company_name']) ?></a><br><span class="muted" style="font-size:12px"><?= e(trim(($l['next_action'] ?: '') . ((float) $l['value'] > 0 ? ' · ' . sx_short($l['value']) . ' est.' : ''), ' ·')) ?></span></td>
            <td class="nw"><?= sx_contact_btns($l['contact_phone'] ?? '', $l['company_name']) ?> <a class="sxc" href="/sales-log?lead=<?= (int) $l['id'] ?>" aria-label="Log activity on <?= e($l['company_name']) ?>">📝 Log</a></td></tr>
        <?php endforeach; ?>
      </table></div><?php endif; ?></div>
    <div class="sxcard" id="stale"><h2>💤 Stale deals · no activity for over <?= (int) sx_stale_days() ?> days</h2>
      <?php if (!$d['stale']): ?><p class="sxempty">None.</p><?php else: ?>
      <div class="tbl"><table class="sxt">
        <?php foreach (array_slice($d['stale'], 0, 15) as $o): ?>
          <tr><td><a href="/opportunity?id=<?= (int) $o['id'] ?>"><?= e($o['ref']) ?></a> <?= e($o['client_name'] ?: $o['partner_name']) ?></td><td class="n"><?= e(sx_short($o['value'])) ?></td><td><span class="sxp p-mut"><?= (int) $o['_idle'] ?> days</span></td></tr>
        <?php endforeach; ?>
      </table></div><?php endif; ?></div>
  </div>

  <div class="sxgrid2" style="margin-top:12px">
    <div class="sxcard" id="pay"><h2>💰 Payments to collect · overdue and due in 7 days</h2>
      <?php if (!$d['ar']): ?><p class="sxempty">Payments are shown to people who may see receivables.</p>
      <?php elseif (!$d['pay']): ?><p class="sxempty">Nothing to chase. 👍</p><?php else: ?>
      <div class="tbl"><table class="sxt">
        <?php foreach (array_slice($d['pay'], 0, 15) as $r): $a = (int) $r['age_days']; ?>
          <tr><td><a href="/invoice?id=<?= (int) $r['id'] ?>"><?= e($r['invoice_no']) ?></a> · <?= e($r['partner_name'] ?? '') ?></td><td class="n"><?= e(sx_money($r['amount'])) ?></td>
            <td><span class="sxp <?= $a > 0 ? 'p-bad' : ($a === 0 ? 'p-warn' : 'p-week') ?>"><?= $a > 0 ? $a . ' days late' : ($a === 0 ? 'due today' : 'in ' . -$a . ' days') ?></span></td></tr>
        <?php endforeach; ?>
      </table></div><?php endif; ?></div>
    <div class="sxcard" id="renew"><h2>🔁 Renewals due</h2>
      <?php if (!$d['renewals']): ?><p class="sxempty">None in the next <?= (int) (function_exists('contract_warn_days') ? contract_warn_days() : 30) ?> days.</p><?php else: ?>
      <div class="tbl"><table class="sxt">
        <?php foreach ($d['renewals'] as $r): $left = (int) round((strtotime($r['end_date']) - strtotime(date('Y-m-d'))) / 86400); ?>
          <tr><td><a href="/contract?id=<?= (int) $r['id'] ?>"><?= e($r['contract_number'] ?: $r['title']) ?></a> · <?= e($r['client_name'] ?? '') ?></td><td class="n"><?= (float) $r['value'] > 0 ? e(sx_money($r['value'])) : '' ?></td>
            <td><span class="sxp <?= $left <= 15 ? 'p-warn' : 'p-week' ?>">in <?= $left ?> days</span></td></tr>
        <?php endforeach; ?>
      </table></div><?php endif; ?></div>
  </div>
</div>
