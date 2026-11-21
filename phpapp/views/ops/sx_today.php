<?php
// Today / My work (owner, 21 Nov 2026: Stage 1 of the Sales redesign). The same
// lists as before — deal follow-ups, lead follow-ups, stale deals, payments to
// collect, renewals due, and for managers who has what — read from the same
// sx_today_data(). Deals and leads now sit in one list, each marked Deal or
// Lead, in the same order as before (overdue, no next step, today, this week).
// The old page is kept in tests/fixtures/pre_stage1/.
require __DIR__ . '/_sxw_ui.php';
$c = $d['counts']; $q = $whoKey !== 'me' ? '?who=' . rawurlencode((string) $whoKey) : '';
//  One follow-up list: the deal rows and the lead rows as sx_today_data() gives them,
//  merged with the same ordering rule each list already used.
$order = ['overdue' => 0, 'none' => 1, 'today' => 2, 'week' => 3];
$fu = [];
foreach ($d['deals'] as $o) $fu[] = ['k' => 'D', 'r' => $o];
foreach ($d['leads'] as $l) $fu[] = ['k' => 'L', 'r' => $l];
usort($fu, fn($a, $b) => [$order[$a['r']['_due']] ?? 9, (string) $a['r']['next_action_on']] <=> [$order[$b['r']['_due']] ?? 9, (string) $b['r']['next_action_on']]);
$noneMarked = false;
// Same window the renewals count uses (Sales settings → Renewals, DEF-10).
$warn = function_exists('sx_renewal_days') ? sx_renewal_days() : 30;
//  D2 (owner, 21 Nov 2026): Log only on a lead or deal this person may work.
$canWork = fn($r) => !function_exists('sales_record_can_work') || sales_record_can_work((int) ($r['owner_user_id'] ?? 0));
?>
<div class="rec sxw has-bn">
  <?php sxw_nav('/sales-today'); ?>
  <?php sxw_head('Today — My work · ' . date('l, j M Y'), 'Why you\'re here: everything that needs attention today, in one place. Work top to bottom · WhatsApp / call to contact · Log what happened — the next follow-up date fills itself in.', 'task', sxw_person('sales-today', $whoKey, $people, $seeAll)); ?>

  <div class="sxw-kp sm" style="--n:<?= 6 + ($d['ar'] ? 1 : 0) ?>">
    <?php sxw_kpi('bad', 'cal', 'Overdue', 'Overdue', (string) $c['overdue'], 'deals + leads', '#deals');
          sxw_kpi('warn', 'clock', 'Due today', 'Due today', (string) $c['today'], 'deals + leads', '#deals');
          sxw_kpi('info', 'cal', 'This week', 'This week', (string) $c['week'], 'next 7 days', '#deals');
          sxw_kpi('bad', 'alert', 'No next step', 'No next step', (string) $c['none'], 'set a date!', '#none');
          sxw_kpi('grey', 'hist', 'Stale deals', 'Stale deals', (string) $c['stale'], 'no activity > ' . sx_stale_days() . ' days', '#stale');
          if ($d['ar']) sxw_kpi('bad', 'rupee', 'Overdue ₹', 'Overdue ₹', sx_short($d['overdue_amt']), $d['overdue_n'] . ' invoice' . ($d['overdue_n'] === 1 ? '' : 's'), '#pay');
          sxw_kpi('teal', 'swap', 'Renewals', 'Renewals', (string) count($d['renewals']), 'in ' . $warn . ' days', '#renew'); ?>
  </div>

  <?php if ($seeAll && $whoRows): ?>
  <?php rec_card_open('Who has what today', 'users', '<span class="rec-note" style="margin:0">tap a name for their list</span>'); ?>
    <div class="rec-scroll"><table class="rec-tbl sxw-team"><thead><tr><th>Salesperson</th><th style="text-align:right">Overdue</th><th style="text-align:right">Today</th><th style="text-align:right">This week</th><th style="text-align:right">No next step</th><th style="text-align:right">Stale</th></tr></thead><tbody>
    <?php foreach ($whoRows as $r): ?>
      <tr><td><a href="/sales-today?who=<?= (int) $r['id'] ?>"><?= e($r['name']) ?></a></td>
        <td style="text-align:right"><?= $r['overdue'] ? '<span class="sxw-due p-bad">' . (int) $r['overdue'] . '</span>' : '—' ?></td>
        <td style="text-align:right"><?= $r['today'] ? '<span class="sxw-due p-warn">' . (int) $r['today'] . '</span>' : '—' ?></td>
        <td style="text-align:right"><?= $r['week'] ? (int) $r['week'] : '—' ?></td>
        <td style="text-align:right"><?= $r['none'] ? '<span class="sxw-due p-warn">' . (int) $r['none'] . '</span>' : '—' ?></td>
        <td style="text-align:right"><?= $r['stale'] ? (int) $r['stale'] : '—' ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php rec_card_close(); ?>
  <?php endif; ?>

  <section class="rec-card">
    <div style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap">
      <div class="sxw-tabs" data-sxw-tabs>
        <button type="button" data-for="deals" class="on">Follow-ups <em><?= count($fu) ?></em></button>
        <button type="button" data-for="stale">Stale deals <em><?= count($d['stale']) ?></em></button>
        <button type="button" data-for="pay">Payments to collect <em><?= $d['ar'] ? count($d['pay']) : '—' ?></em></button>
        <button type="button" data-for="renew">Renewals due <em><?= count($d['renewals']) ?></em></button>
      </div>
    </div>

    <div id="deals" data-sxw-panel>
      <div class="rec-ch" style="margin:4px 0 8px"><span class="ico"><?= rec_icon('brief') ?></span><h3>Deal and lead follow-ups</h3>
        <input class="sxw-srch" type="search" placeholder="Search company, deal, next step" aria-label="Search the follow-ups" data-sxw-search="sxw-fu"></div>
      <?php if (!$fu): ?><p class="sxw-empty">Nothing due. 👍</p><?php else: ?>
      <table class="sxw-fu fu" id="sxw-fu" data-sxw-sort>
        <thead><tr><th data-sort="text">Type</th><th data-sort="text">When</th><th data-sort="text">Company / subject</th><th data-sort="text">Stage</th><th class="r" data-sort="num">Value</th><th>Next step</th><th class="r" data-sort="num">Idle</th><th data-sort="text">Owner</th><th class="r">Action</th></tr></thead>
        <tbody>
        <?php foreach ($fu as $i => $x): $r = $x['r']; $isD = $x['k'] === 'D'; $rank = ($order[$r['_due']] ?? 9) . '|' . $r['next_action_on'];
              $idAttr = ($r['_due'] === 'none' && !$noneMarked) ? ' id="none"' : ''; if ($idAttr) $noneMarked = true; ?>
          <tr<?= $idAttr ?>>
            <td><span class="sxw-typ <?= $isD ? 'd' : 'l' ?>"><?= $isD ? e(function_exists('Tl') ? ucfirst(Tl('opportunity')) : 'Deal') : 'Lead' ?></span></td>
            <td data-s="<?= e($rank) ?>"><?= sxw_due($r['next_action_on'] ?? '') ?></td>
            <?php if ($isD): ?>
              <td><a href="/opportunity?id=<?= (int) $r['id'] ?>"><?= e($r['ref']) ?></a> <?= e($r['name']) ?><div class="sub"><?= e($r['client_name'] ?: $r['partner_name']) ?></div></td>
              <td><span class="k">Stage</span><?= e($r['stage_name']) ?></td>
              <td class="r" data-s="<?= (float) $r['value'] ?>"><span class="k">Value</span><?= e(sx_money($r['value'])) ?></td>
              <td><span class="k">Next step</span><?= e($r['next_action'] ?: '—') ?></td>
              <td class="r" data-s="<?= $r['_idle'] !== null ? (int) $r['_idle'] : -1 ?>"><span class="k">Idle</span><?= $r['_idle'] !== null ? (int) $r['_idle'] . ' d' : '—' ?></td>
              <td><span class="k">Owner</span><?= e(($r['owner_name'] ?? '') !== '' ? $r['owner_name'] : '—') ?></td>
              <td class="ac"><div class="sxw-acts"><?= sxw_contact($r['contact_phone'] ?? '', $r['name']) ?><?php if ($canWork($r)): ?><a class="sxw-ib" href="<?= e(sx_log_url('OPPORTUNITY', (int) $r['id'], '/sales-today' . $q)) ?>" aria-label="Log activity on <?= e($r['ref']) ?>"><?= rec_icon('note') ?><span>Log</span></a><?php endif; ?></div></td>
            <?php else: ?>
              <td><a href="/lead?id=<?= (int) $r['id'] ?>"><?= e($r['company_name']) ?></a><div class="sub"><?= e(trim(($r['next_action'] ?: '') . ((float) $r['value'] > 0 ? ' · ' . sx_short($r['value']) . ' est.' : ''), ' ·')) ?></div></td>
              <td><span class="k">Stage</span><?= e($r['stage_name'] ?? '') ?></td>
              <td class="r" data-s="<?= (float) $r['value'] ?>"><span class="k">Value</span><?= (float) $r['value'] > 0 ? e(sx_short($r['value'])) . ' est.' : '—' ?></td>
              <td><span class="k">Next step</span><?= e($r['next_action'] ?: '—') ?></td>
              <td class="r" data-s="-1"><span class="k">Idle</span>—</td>
              <td><span class="k">Owner</span><?= e(($r['owner_name'] ?? '') !== '' ? $r['owner_name'] : '—') ?></td>
              <td class="ac"><div class="sxw-acts"><?= sxw_contact($r['contact_phone'] ?? '', $r['company_name']) ?><?php if ($canWork($r)): ?><a class="sxw-ib" href="<?= e(sx_log_url('LEAD', (int) $r['id'], '/sales-today' . $q)) ?>" aria-label="Log activity on <?= e($r['company_name']) ?>"><?= rec_icon('note') ?><span>Log</span></a><?php endif; ?></div></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="rec-note"><?= count($d['deals']) ?> deal follow-up<?= count($d['deals']) === 1 ? '' : 's' ?> and <?= count($d['leads']) ?> lead follow-up<?= count($d['leads']) === 1 ? '' : 's' ?> — overdue first, then no next step, today, this week. Click a column title to sort.</p>
      <?php endif; ?>
    </div>

    <div id="stale" data-sxw-panel>
      <div class="rec-ch" style="margin:4px 0 8px"><span class="ico"><?= rec_icon('hist') ?></span><h3>Stale deals · no activity for over <?= (int) sx_stale_days() ?> days</h3></div>
      <?php if (!$d['stale']): ?><p class="sxw-empty">None.</p><?php else: ?>
        <?php foreach (array_slice($d['stale'], 0, 15) as $o): ?>
          <div class="sxw-li" style="grid-template-columns:minmax(0,1fr) auto auto"><span><a href="/opportunity?id=<?= (int) $o['id'] ?>"><?= e($o['ref']) ?></a> <?= e($o['client_name'] ?: $o['partner_name']) ?></span><b><?= e(sx_short($o['value'])) ?></b><span class="sxw-due p-mut"><?= (int) $o['_idle'] ?> days</span></div>
        <?php endforeach; ?>
        <p class="rec-note">The first 15, longest idle first.</p>
      <?php endif; ?>
    </div>

    <div id="pay" data-sxw-panel>
      <div class="rec-ch" style="margin:4px 0 8px"><span class="ico"><?= rec_icon('rupee') ?></span><h3>Payments to collect · overdue and due in 7 days</h3></div>
      <?php if (!$d['ar']): ?><p class="sxw-empty">Payments are shown to people who may see receivables.</p>
      <?php elseif (!$d['pay']): ?><p class="sxw-empty">Nothing to chase. 👍</p><?php else: ?>
        <?php foreach (array_slice($d['pay'], 0, 15) as $r): $a = (int) $r['age_days']; ?>
          <div class="sxw-li" style="grid-template-columns:minmax(0,1fr) auto auto"><span><a href="/invoice?id=<?= (int) $r['id'] ?>"><?= e($r['invoice_no']) ?></a> · <?= e($r['partner_name'] ?? '') ?></span><b><?= e(sx_money($r['amount'])) ?></b>
            <span class="sxw-due <?= $a > 0 ? 'p-bad' : ($a === 0 ? 'p-warn' : 'p-week') ?>"><?= $a > 0 ? $a . ' days late' : ($a === 0 ? 'due today' : 'in ' . -$a . ' days') ?></span></div>
        <?php endforeach; ?>
        <p class="rec-note">Accounts' invoices (not Sales values) — the first 15.</p>
      <?php endif; ?>
    </div>

    <div id="renew" data-sxw-panel>
      <div class="rec-ch" style="margin:4px 0 8px"><span class="ico"><?= rec_icon('swap') ?></span><h3>Renewals due</h3></div>
      <?php if (!$d['renewals']): ?><p class="sxw-empty">None in the next <?= (int) $warn ?> days.</p><?php else: ?>
        <?php foreach ($d['renewals'] as $r): $left = (int) round((strtotime($r['end_date']) - strtotime(date('Y-m-d'))) / 86400); ?>
          <div class="sxw-li" style="grid-template-columns:minmax(0,1fr) auto auto"><span><a href="/contract?id=<?= (int) $r['id'] ?>"><?= e($r['contract_number'] ?: $r['title']) ?></a> · <?= e($r['client_name'] ?? '') ?></span><b><?= (float) $r['value'] > 0 ? e(sx_money($r['value'])) : '' ?></b>
            <span class="sxw-due <?= $left <= 15 ? 'p-warn' : 'p-week' ?>"><?= $left <= 0 ? 'ends today' : ($left === 1 ? 'tomorrow' : 'in ' . $left . ' days') ?></span></div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php sxw_bottom('/sales-today', (int) $c['overdue'] + (int) $c['today']); sxw_script(); ?>
