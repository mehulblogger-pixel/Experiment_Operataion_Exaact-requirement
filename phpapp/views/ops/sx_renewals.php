<?php
// Renewals (owner, 21 Nov 2026: Stage 5 of the Sales redesign). Contracts by days
// left, plus those ended in the last 30 days (still worth a call). The old page is
// kept in tests/fixtures/pre_stage5/. New, by the owner's decisions:
//   DEF-10 — only OPEN contracts (not pending, rejected or closed), and one look-ahead
//            window for Renewals, Today and Sales home, set in Sales settings (60 to start);
//   Q2     — a contract's value only for people the contract page shows it to;
//   Q3     — "Log a call": written on the client's own timeline, naming the contract.
require __DIR__ . '/_sxw_ui.php';
$t = strtotime(date('Y-m-d')); $c = ['late' => 0, 'm' => 0, 'later' => 0, 'v' => 0.0];
foreach ($rows as $r) { $left = (int) round((strtotime($r['end_date']) - $t) / 86400); $c[$left < 0 ? 'late' : ($left <= 30 ? 'm' : 'later')]++; $c['v'] += (float) $r['value']; }
$seg = '<span class="lab">Look ahead:</span><nav class="sxw-seg" aria-label="Look ahead">';
foreach ($opts as $d) $seg .= '<a href="/sales-renewals?d=' . (int) $d . '"' . ($days === $d ? ' class="on" aria-current="true"' : '') . '>' . (int) $d . ' days</a>';
$seg .= '</nav>';
?>
<div class="rec sxw has-bn">
  <?php sxw_nav('/sales-renewals'); ?>
  <?php sxw_head('Renewals', 'Active contracts ending soon, and those that ended in the last 30 days. Raise the renewal ' . Tl('quote') . ' in time, and log the call.', 'swap',
        '<div class="sxw-ctl">' . $seg . '</div>'); ?>
  <p class="muted" style="margin:6px 0 0;font-size:12.5px"><?= (int) $def ?> days is the starting window set in Sales settings<?= $example ? ' <span class="sxw-due p-warn">example value</span>' : '' ?> — Today and Sales home look the same distance ahead.</p>

  <div class="sxw-kp" style="--n:4">
    <?php sxw_kpi('bad', 'alert', 'Ended (last 30 days)', 'Ended · last 30 days', (string) $c['late'], 'call now');
          sxw_kpi('warn', 'hour', 'Ending in 30 days', 'Ending in 30 days', (string) $c['m'], 'renewal ' . Tl('quote'));
          sxw_kpi('info', 'cal', 'Later', 'Later', (string) $c['later'], 'within ' . $days . ' days');
          if ($money) sxw_kpi('ok', 'rupee', 'Their value', 'Their value', sx_short($c['v']), 'contract values'); ?>
  </div>

  <?php rec_card_open('Contracts', 'file'); ?>
    <?php if (!$rows): ?><p class="sxw-empty">Nothing ends in the next <?= (int) $days ?> days.</p><?php else: ?>
    <div class="rec-scroll"><table class="rec-tbl">
      <thead><tr><th>Contract</th><th><?= e(T('client')) ?></th><th>Ends</th><th>Days left</th><?php if ($money): ?><th class="n">Value</th><?php endif; ?><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $left = (int) round((strtotime($r['end_date']) - $t) / 86400); $no = $r['contract_number'] ?: $r['title']; ?>
        <tr><td><?= $canContract ? '<a href="/contract?id=' . (int) $r['id'] . '"><b>' . e($no) . '</b></a>' : '<b>' . e($no) . '</b>' ?><?= ($r['title'] ?? '') !== '' && $r['contract_number'] ? '<br><span class="muted" style="font-size:12px">' . e($r['title']) . '</span>' : '' ?></td>
          <td><?= $r['partner_id'] ? '<a href="/partner?id=' . (int) $r['partner_id'] . '">' . e($r['client_name'] ?? '') . '</a>' : '—' ?></td>
          <td class="nw"><?= e(fdate($r['end_date'])) ?></td>
          <td><span class="sxw-due <?= $left < 0 ? 'p-bad' : ($left <= 30 ? 'p-warn' : ($left <= 60 ? 'p-week' : 'p-mut')) ?>"><?= $left < 0 ? 'ended ' . -$left . ' d ago' : ($left === 0 ? 'ends today' : $left . ' days') ?></span></td>
          <?php if ($money): ?><td class="n"><?= (float) $r['value'] > 0 ? e(sx_money($r['value'])) : '—' ?></td><?php endif; ?>
          <td class="nw"><div class="sxw-acts">
            <?php if ($r['partner_id'] && $canQuote): ?><a class="sxw-bt" href="/quote-new?client=<?= (int) $r['partner_id'] ?>"><?= rec_icon('file', 's') ?>Renewal <?= e(Tl('quote')) ?></a><?php endif; ?>
            <?php if ($r['partner_id'] && $canLog): ?>
              <details class="sx5-log"><summary class="sxw-bt"><?= rec_icon('note', 's') ?>Log a call</summary>
                <form method="post" action="/sales-renewals" style="margin-top:8px;display:grid;gap:6px;min-width:240px">
                  <input type="hidden" name="do" value="log"><input type="hidden" name="contract_id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="d" value="<?= (int) $days ?>">
                  <label class="muted" style="font-size:12px">How
                    <select class="form-control" name="method"><option value="CALL">Telephone call</option><option value="MEETING">Meeting</option><option value="VISIT">Site visit</option><option value="EMAIL">E-mail</option><option value="WHATSAPP">WhatsApp</option></select></label>
                  <label class="muted" style="font-size:12px">What was said<textarea class="form-control" name="note" rows="2" required maxlength="2000" placeholder="e.g. wants the renewal quote by Friday; scope unchanged"></textarea></label>
                  <button class="sxw-bt p" type="submit">Save on the <?= e(Tl('client')) ?>'s timeline</button>
                  <span class="muted" style="font-size:11.5px">Saved on the <?= e(Tl('client')) ?>'s history with the contract number.</span>
                </form></details>
            <?php endif; ?></div></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
    <p class="muted" style="font-size:12px;margin:8px 0 0">Only contracts that are <b>open</b> (not pending, rejected or closed) · your branches (the contract's branch, else the <?= e(Tl('client')) ?>'s home branch)<?= $money ? '' : ' · contract values are shown to people who may see contract money' ?>.</p>
  <?php rec_card_close(); ?>
</div>
<?php sxw_bottom('/sales-renewals'); ?>
