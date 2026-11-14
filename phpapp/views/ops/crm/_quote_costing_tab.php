<?php
//  THE QUOTATION'S COSTING, ON THE QUOTATION'S OWN SCREEN (owner, 14 Nov 2026, SAL-186: "Revision is
//  really very very complex taking user here and there … if there is any change in costing due to
//  revision same shall be opened from the same screen no back and forth").
//  Drawn inside the quote's edit form (the "Costing" tab) and again by /quote-costing-card when the
//  costing popup closes, so the figures here are always the costing's latest. Needs $q.
$pcRow = function_exists('crm_quote_costing') ? crm_quote_costing($q) : null;
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
?>
<div id="qe-costing-box" data-quote="<?= (int)$q['id'] ?>">
<?php if (!$pcRow): ?>
  <p class="muted" style="margin:0">No project costing is linked to this <?= $e(Tl('quote')) ?>.
    <?php if (function_exists('crm_quote_costing_problem') && ($cp = crm_quote_costing_problem($q)) !== ''): ?><br><span style="color:var(--warn,#9a5b00)">⚠ <?= $e($cp) ?></span><?php endif; ?></p>
<?php else:
  $roll = pc_rollup((int)$pcRow['id']); $h = $roll['header']; $T = $roll['totals'];
  $locked = pc_locked($h); $canEditPc = function_exists('pc_can_edit') && pc_can_edit();
  $flags = function_exists('pc_margin_flags') ? pc_margin_flags($h, $roll['lines']) : [];
  $stTone = ['DRAFT' => 'p-mut', 'SUBMITTED' => 'p-warn', 'APPROVED' => 'p-ok', 'REJECTED' => 'p-bad'];
  $rows = pc_quote_line_rows($h, $roll['lines']);
  $gate = function_exists('crm_quote_costing_problem') ? crm_quote_costing_problem($q) : '';
  $revQ = function_exists('pc_revise_problem') && $locked && $canEditPc && pc_revise_problem((int)$q['id']) === '';
  $popup = '/project-costing?id=' . (int)$h['id'] . '&embed_stay=1'; ?>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:8px">
    <b style="font-size:15px"><?= $e($h['code']) ?></b>
    <span class="pill <?= $stTone[$h['status']] ?? 'p-mut' ?>"><?= $e(PC_STATUS[$h['status']] ?? $h['status']) ?></span>
    <span class="muted" style="font-size:13px">revenue <?= fmoney_short($T['revenue']) ?> · cost <?= fmoney_short($T['cost']) ?> · margin <?= number_format((float)$T['margin'], 1) ?>%</span>
  </div>
  <?php if ($flags): ?><div class="msg msg-warning" style="margin:0 0 8px;font-size:12.5px">⚠ <?= count($flags) ?> role<?= count($flags) === 1 ? '' : 's' ?> outside the margin set (± <?= (int)PC_MARGIN_BAND ?> points): <?= $e(implode(', ', array_column($flags, 'role'))) ?></div><?php endif; ?>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt" style="font-size:13px">
    <thead><tr><th>Role</th><th class="num">Qty</th><th>Unit</th><th class="num">Rate</th><th class="num">Amount</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?><tr><td><?= $e($r['desc']) ?></td><td class="num"><?= $e(rtrim(rtrim(number_format((float)$r['qty'], 2), '0'), '.')) ?></td><td><?= $e($r['unit']) ?></td><td class="num"><?= number_format((float)$r['rate']) ?></td><td class="num"><?= number_format((float)$r['amount']) ?></td></tr><?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="muted">No roles yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:10px">
    <?php if (!$locked && $canEditPc): ?>
      <a class="btn" href="/project-costing?id=<?= (int)$h['id'] ?>" data-embed="<?= $e($popup) ?>" data-embed-keep data-embed-title="Costing <?= $e($h['code']) ?> — change it here; close when done">✎ Change the costing</a>
      <span class="muted" style="font-size:12.5px">Opens over this screen — change roles, rates and margins, and submit it for approval there. Close it to come back here; nothing you typed on the <?= $e(Tl('quote')) ?> is lost.</span>
    <?php elseif ($revQ): ?>
      <span class="muted" style="font-size:12.5px">This costing is locked — it priced the earlier revision.</span>
      <button type="button" class="btn" data-qe-pc-copy="<?= (int)$q['id'] ?>">✎ Make this revision's costing</button>
    <?php else: ?>
      <a class="btn secondary" href="/project-costing?id=<?= (int)$h['id'] ?>" data-embed="<?= $e($popup) ?>" data-embed-keep data-embed-title="Costing <?= $e($h['code']) ?>">Open the costing</a>
      <?php if ($locked): ?><span class="muted" style="font-size:12.5px">🔒 <?= $e(pc_quote_lock_reason($h) ?: 'Read-only while ' . strtolower(PC_STATUS[$h['status']] ?? $h['status']) . '.') ?></span><?php endif; ?>
    <?php endif; ?>
    <?php if ($rows): ?><button type="button" class="btn secondary" id="qe-pc-take" title="Fill the Line items tab with the costing's roles, quantities and rates — saved when you save this <?= $e(Tl('quote')) ?>">⇣ Take the costing's prices into the line items</button><?php endif; ?>
  </div>
  <p class="muted" style="font-size:12.5px;margin:8px 0 0"><?= $gate !== '' ? '⚠ ' . $e($gate) : '✓ This costing is approved — the ' . $e(Tl('quote')) . ' can go forward at its prices.' ?></p>
<?php endif; ?>
</div>
