<?php
// Simple Sales — Targets (the owner's Excel TARGETS sheet): one row per person,
// one box per month of the financial year; the row below shows what was won.
// A changed figure is saved as a NEW row — the earlier one stays in the history.
require __DIR__ . '/_sx_ui.php';
$curYm = date('Y-m');
?>
<div class="sx">
  <div class="sxhead"><h1>🎯 Targets</h1></div>
  <p class="sxwhy"><b>Why you're here:</b> each person's monthly target and what they have won against it. The dashboard's target and achievement read from here.
    <?= $can ? '<b>What to do:</b> type the figures (₹) and Save. Changing a figure never overwrites the old one — it is kept below.' : 'Your manager sets these.' ?></p>
  <?php if (!$people): ?><div class="sxcard"><p class="sxempty">Nobody to set targets for yet — targets are for sales people and anyone who owns a lead or deal.</p></div><?php else: ?>
  <form method="post" action="/sales-targets" class="sxcard" style="margin-top:0">
    <div class="tbl"><table class="sxt">
      <tr><th>Person</th><?php foreach ($months as $m): ?><th class="n"<?= $m === $curYm ? ' style="color:var(--brand)"' : '' ?>><?= e(date('M y', strtotime($m . '-01'))) ?></th><?php endforeach; ?><th class="n">Year</th><th class="n">Won</th><th class="n">%</th></tr>
      <?php foreach ($people as $uid => $nm): $tY = array_sum($grid[$uid] ?? []); $wY = array_sum($won[$uid] ?? []); $pc = $tY > 0 ? (int) round($wY * 100 / $tY) : null; ?>
        <tr><td><b><?= e($nm) ?></b><br><span class="muted" style="font-size:11.5px">won ↓</span></td>
          <?php foreach ($months as $m): $tv = $grid[$uid][$m] ?? null; $wv = (float) ($won[$uid][$m] ?? 0); ?>
            <td class="n" style="min-width:96px"><?php if ($can): ?><input class="form-control sxnum" name="t[<?= (int) $uid ?>][<?= e($m) ?>]" inputmode="decimal" value="<?= $tv !== null ? e((string) (0 + $tv)) : '' ?>" aria-label="<?= e($nm . ' ' . $m) ?> target"><?php else: ?><?= $tv !== null ? e(sx_short($tv)) : '—' ?><?php endif; ?>
              <?php $ch = $changes[$uid][$m] ?? []; if (count($ch) > 1): $was = $ch[count($ch) - 2]; $last = $ch[count($ch) - 1]; ?>
                <div style="font-size:11px;margin-top:3px"><span class="sxp p-week" title="Changed <?= e(fdate(substr((string) $last['set_at'], 0, 10))) ?> by <?= e($last['set_by']) ?> — was <?= e(sx_money($was['amount'])) ?>">↻ was <?= e(sx_short($was['amount'])) ?></span></div>
              <?php endif; ?>
              <div class="muted" style="font-size:11.5px;margin-top:3px"><?= $wv > 0 ? '<span class="sxp ' . ($tv && $wv >= $tv ? 'p-ok' : 'p-mut') . '">' . e(sx_short($wv)) . '</span>' : '·' ?></div></td>
          <?php endforeach; ?>
          <td class="n"><b><?= $tY > 0 ? e(sx_short($tY)) : '—' ?></b></td><td class="n"><?= $wY > 0 ? e(sx_short($wY)) : '—' ?></td>
          <td class="n"><?= $pc === null ? '—' : '<span class="sxp ' . ($pc >= 100 ? 'p-ok' : ($pc >= 70 ? 'p-week' : 'p-bad')) . '">' . $pc . '%</span>' ?></td></tr>
      <?php endforeach; ?>
    </table></div>
    <?php if ($can): ?><div class="sxactions"><button class="btn" type="submit">Save targets</button><span class="muted" style="font-size:12.5px">Leave a box empty for no target.</span></div><?php endif; ?>
  </form>
  <?php if ($hist): ?><div class="sxcard"><h2>History · every change, before → after</h2><div class="tbl"><table class="sxt">
    <tr><th>When</th><th>Person</th><th>Month</th><th class="n">Before</th><th class="n">After</th><th>Changed by</th></tr>
    <?php foreach ($hist as $h): $pv = $h['prev_amount'] ?? null; ?><tr><td class="nw"><?= e(fdate(substr((string) $h['set_at'], 0, 10))) ?></td><td><?= e(trim(($h['first_name'] ?? '') . ' ' . ($h['last_name'] ?? ''))) ?></td><td><?= e(date('M Y', strtotime($h['ym'] . '-01'))) ?></td>
      <td class="n"><?= $pv === null ? '<span class="muted">first set</span>' : e(sx_money($pv)) ?></td>
      <td class="n"><b><?= e(sx_money($h['amount'])) ?></b><?php if ($pv !== null && (float) $pv > 0): $d = (float) $h['amount'] - (float) $pv; ?> <span class="sxp <?= $d >= 0 ? 'p-warn' : 'p-ok' ?>"><?= $d >= 0 ? '▲' : '▼' ?> <?= e(sx_short(abs($d))) ?></span><?php endif; ?></td>
      <td><?= e($h['set_by']) ?></td></tr><?php endforeach; ?>
  </table></div></div><?php endif; ?>
  <?php endif; ?>
</div>
