<?php
// Targets (owner, 21 Nov 2026: Stage 5 of the Sales redesign). One row per person,
// one box per month of the financial year, what was won under each box. A changed
// figure is saved as a NEW row — the earlier one stays in the history. The old page
// is kept in tests/fixtures/pre_stage5/. New, by the owner's decision DEF-11 (A):
//   * any financial year can be picked (the year picker);
//   * an empty box means "no target" (stored as such, not as ₹0);
//   * the history is complete, a page at a time;
//   * who may set them: management as before, plus holders of lead set-up or
//     quotation approval, for the people of their own branches (lib: sxt_can_set()).
require __DIR__ . '/_sxw_ui.php';
$curYm = date('Y-m');
$fyq = '?fy=' . rawurlencode($fy);
$pick = '<form method="get" action="/sales-targets" class="sxw-ctl"><label class="lab" for="sxt-fy">Year</label>'
      . '<select class="form-control" id="sxt-fy" name="fy" onchange="this.form.submit()" style="width:auto">';
foreach ($fys as $f) $pick .= '<option value="' . e($f) . '"' . ($f === $fy ? ' selected' : '') . '>FY ' . e($f) . '</option>';
$pick .= '</select><noscript><button class="sxw-bt" type="submit">Show</button></noscript></form>';
$pages = max(1, (int) ceil($histN / $histPer));
?>
<div class="rec sxw has-bn">
  <?php sxw_nav('/sales-targets'); ?>
  <?php sxw_head('Targets', 'Monthly sales target per person for FY ' . $fy . ', with deals won against it. Every change is kept with who changed it, and the person is told. The dashboard\'s target and achievement read from here.', 'target', $pick); ?>

  <?php if (!$people): ?><?php rec_card_open('Targets', 'target'); ?><p class="sxw-empty">Nobody to set targets for yet — targets are for sales people and anyone who owns a lead or deal<?= $can ? ' in your branches' : '' ?>.</p><?php rec_card_close(); ?>
  <?php else: ?>
  <form method="post" action="/sales-targets<?= e($fyq) ?>">
    <input type="hidden" name="fy" value="<?= e($fy) ?>">
    <input type="hidden" name="unit" value="lakh">
    <?php rec_card_open($can ? 'Targets · amount in ₹ lakh' : 'Targets · ₹', 'target', $can ? '<button class="sxw-bt p" type="submit">Save targets</button>' : ''); ?>
      <p class="muted" style="margin:0 0 8px;font-size:12.5px"><?= $can ? '<b>Type each amount in ₹ lakh</b> — e.g. 12.5 = ₹12,50,000; 0.75 = ₹75,000. The rupee figure shows under the box as you type. <b>Leave a box empty for no target</b> — emptying a box records "no target"; the earlier figure stays in the history.' : 'Your manager sets these.' ?></p>
      <div class="rec-scroll"><table class="rec-tbl sx5-tg">
        <thead><tr><th>Person</th><?php foreach ($months as $m): ?><th class="n"<?= $m === $curYm ? ' style="color:var(--brand)"' : '' ?>><?= e(date('M y', strtotime($m . '-01'))) ?></th><?php endforeach; ?><th class="n">Year</th><th class="n">Won</th><th class="n">%</th></tr></thead>
        <tbody>
        <?php foreach ($people as $uid => $nm): $tY = array_sum($grid[$uid] ?? []); $wY = array_sum($won[$uid] ?? []); $pc = $tY > 0 ? (int) round($wY * 100 / $tY) : null; ?>
          <tr><th scope="row"><b><?= e($nm) ?></b><br><span class="muted" style="font-size:11.5px;font-weight:400">won ↓</span></th>
            <?php foreach ($months as $m): $tv = $grid[$uid][$m] ?? null; $wv = (float) ($won[$uid][$m] ?? 0); ?>
              <td class="n"><?php if ($can): ?><input class="form-control sxnum sxt-lakh" name="t[<?= (int) $uid ?>][<?= e($m) ?>]" inputmode="decimal" value="<?= e(sxt_lakh_text($tv)) ?>" aria-label="<?= e($nm . ' ' . date('M Y', strtotime($m . '-01'))) ?> target in rupees lakh" aria-describedby="sxtr-<?= (int) $uid ?>-<?= e($m) ?>"><input type="hidden" name="o[<?= (int) $uid ?>][<?= e($m) ?>]" value="<?= e(sxt_lakh_text($tv)) ?>"><div class="muted sxt-rs" id="sxtr-<?= (int) $uid ?>-<?= e($m) ?>" aria-live="polite" style="font-size:11px;margin-top:2px;white-space:nowrap"><?= $tv !== null ? e(sx_money($tv)) : '' ?></div><?php else: ?><b><?= $tv !== null ? e(sx_short($tv)) : '<span class="muted">—</span>' ?></b><?php endif; ?>
                <?php $ch = $changes[$uid][$m] ?? []; if (count($ch) > 1): $was = $ch[count($ch) - 2]; $last = $ch[count($ch) - 1]; ?>
                  <div style="font-size:11px;margin-top:3px"><span class="sxw-due p-week" title="Changed <?= e(fdate(substr((string) $last['set_at'], 0, 10))) ?> by <?= e($last['set_by']) ?>">↻ was <?= $was['amount'] === null ? 'no target' : e(sx_short($was['amount'])) ?></span></div>
                <?php endif; ?>
                <div class="muted" style="font-size:11.5px;margin-top:3px"><?= $wv > 0 ? '<span class="sxw-due ' . ($tv && $wv >= $tv ? 'p-ok' : 'p-mut') . '">' . e(sx_short($wv)) . '</span>' : '·' ?></div></td>
            <?php endforeach; ?>
            <td class="n"><b><?= $tY > 0 ? e(sx_short($tY)) : '—' ?></b></td><td class="n"><?= $wY > 0 ? e(sx_short($wY)) : '—' ?></td>
            <td class="n"><?= $pc === null ? '—' : '<span class="sxw-due ' . ($pc >= 100 ? 'p-ok' : ($pc >= 70 ? 'p-week' : 'p-bad')) . '">' . $pc . '%</span>' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <p class="muted" style="font-size:12px;margin:8px 0 0">Under each box: deals won that month. Year % compares won-to-date with the whole year's target.</p>
    <?php rec_card_close(); ?>
  </form>

  <?php rec_card_open('History — every change, before → after', 'hist'); ?>
    <?php if (!$hist): ?><p class="sxw-empty">No change yet.</p><?php else: ?>
    <div class="rec-scroll"><table class="rec-tbl">
      <thead><tr><th>When</th><th>Person</th><th>Month</th><th class="n">Before</th><th class="n">After</th><th>Changed by</th></tr></thead>
      <tbody>
      <?php foreach ($hist as $h): $pv = $h['prev_amount'] ?? null; $nv = $h['amount']; ?><tr><td class="nw"><?= e(fdate(substr((string) $h['set_at'], 0, 10))) ?> <span class="muted"><?= e(substr((string) $h['set_at'], 11, 5)) ?></span></td><td><?= e(trim(($h['first_name'] ?? '') . ' ' . ($h['last_name'] ?? ''))) ?></td><td><?= e(date('M Y', strtotime($h['ym'] . '-01'))) ?></td>
        <td class="n"><?= $pv === null ? '<span class="muted">first set</span>' : e(sx_money($pv)) ?></td>
        <td class="n"><?php if ($nv === null): ?><span class="muted">no target</span><?php else: ?><b><?= e(sx_money($nv)) ?></b><?php if ($pv !== null && (float) $pv > 0): $d = (float) $nv - (float) $pv; ?> <span class="sxw-due <?= $d >= 0 ? 'p-warn' : 'p-ok' ?>"><?= $d >= 0 ? '▲' : '▼' ?> <?= e(sx_short(abs($d))) ?></span><?php endif; endif; ?></td>
        <td><?= e($h['set_by']) ?></td></tr><?php endforeach; ?>
      </tbody>
    </table></div>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:8px;flex-wrap:wrap">
      <span class="muted" style="font-size:12px">Every change is listed, newest first — <?= (int) $histN ?> in all.</span>
      <?php if ($pages > 1): ?><nav class="sxw-seg" aria-label="History pages">
        <?php if ($histPage > 1): ?><a href="/sales-targets<?= e($fyq) ?>&amp;hp=<?= (int) $histPage - 1 ?>">← Previous</a><?php endif; ?>
        <a class="on" aria-current="true">Page <?= (int) $histPage ?> of <?= (int) $pages ?></a>
        <?php if ($histPage < $pages): ?><a href="/sales-targets<?= e($fyq) ?>&amp;hp=<?= (int) $histPage + 1 ?>">Next →</a><?php endif; ?>
      </nav><?php endif; ?>
    </div>
    <?php endif; ?>
  <?php rec_card_close(); ?>
  <?php endif; ?>
</div>
<?php if (!empty($can) && $people): ?>
<script>
// Live rupee figure under each ₹ lakh box (12.5 → ₹12,50,000). Display only —
// the server converts and stores rupees exactly as before.
(function () {
  function inr(n) { var s = String(Math.round(n)); var last = s.slice(-3), rest = s.slice(0, -3);
    return '₹' + (rest ? rest.replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' : '') + last; }
  document.querySelectorAll('.sxt-lakh').forEach(function (box) {
    var out = document.getElementById(box.getAttribute('aria-describedby'));
    function show() { var v = box.value.replace(/[,₹\s]/g, '');
      if (v === '') { out.textContent = 'no target'; return; }
      var n = Number(v); out.textContent = (isFinite(n) && n >= 0) ? inr(n * 100000) : 'type a number of lakh, 0 or more'; }
    box.addEventListener('input', show);
  });
})();
</script>
<?php endif; ?>
<?php sxw_bottom('/sales-targets'); ?>
