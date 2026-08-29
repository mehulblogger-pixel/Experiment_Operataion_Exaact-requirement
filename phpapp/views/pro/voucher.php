<?php
  // Connect K21 — one engagement voucher. Add days; when the rate is EXCLUSIVE,
  // also add travel/hotel/conveyance/allowances against receipts; then submit.
  $me = $me ?? []; $v = $v ?? null; $lines = $lines ?? []; $heads = $heads ?? []; $files = $files ?? [];
  if (!$v) { echo '<div class="card"><p class="muted">Voucher not found. <a href="/pro/vouchers">Back to my vouchers</a></p></div>'; return; }
  $exclusive = strtoupper((string)$v['rate_inclusive']) === 'EXCLUSIVE';
  $isDraft   = strtoupper((string)$v['status']) === 'DRAFT';
  $canAttach = in_array(strtoupper((string)$v['status']), ['DRAFT', 'SUBMITTED'], true);
  $kb = fn($n) => $n >= 1048576 ? round($n/1048576, 1) . ' MB' : max(1, (int)round($n/1024)) . ' KB';
  $unit      = (string)($v['rate_unit'] ?? 'day');
  $rate      = (float)($v['rate'] ?? 0);
  $inr = fn($n) => '₹' . number_format((int)round((float)$n));
  $statusPill = function ($s) {
      $s = strtoupper((string)$s);
      $m = ['DRAFT' => ['Draft', '#5b6b6a', 'rgba(0,0,0,.06)'], 'SUBMITTED' => ['Submitted', '#1858a8', 'rgba(24,88,168,.12)'],
            'APPROVED' => ['Approved', '#0f7d5a', 'var(--okbg)'], 'PAID' => ['Paid', '#0f7d5a', 'var(--okbg)'],
            'REJECTED' => ['Sent back', '#9a2a2a', '#f6e7e6']];
      [$l, $c, $bg] = $m[$s] ?? $m['DRAFT'];
      return '<span style="display:inline-block;padding:3px 11px;border-radius:999px;font-size:12px;font-weight:700;color:' . $c . ';background:' . $bg . '">' . e($l) . '</span>';
  };
?>
<p style="margin:0 0 8px"><a href="/pro/vouchers" class="muted" style="font-size:14px">← My vouchers</a></p>
<div style="display:flex;justify-content:space-between;gap:10px;align-items:start">
  <h1 style="margin:0"><?= e($v['req_title'] ?? '') ?: 'Voucher' ?></h1>
  <?= $statusPill($v['status']) ?>
</div>
<div style="display:flex;gap:6px;flex-wrap:wrap;margin:8px 0 4px">
  <span class="chip" style="<?= $exclusive ? 'border-color:var(--gold);color:var(--gold)' : 'border-color:var(--teal);color:var(--teal)' ?>"><?= $exclusive ? 'Fee + expenses (exclusive)' : 'All-inclusive' ?></span>
  <span class="chip">Period <?= e($v['period_label'] ?: '—') ?></span>
  <?php if ($rate > 0): ?><span class="chip"><?= e($inr($rate)) ?>/<?= e($unit) ?></span><?php endif; ?>
</div>
<p class="muted" style="margin:2px 0 14px;font-size:13px">
  <?= $exclusive
      ? 'Your rate is the professional fee only — add each ' . e($unit) . ' worked, plus travel, hotel, conveyance and allowances against receipts.'
      : 'Your rate is all-inclusive — add each ' . e($unit) . ' worked. Travel, hotel and allowances are already covered, so no expense claim is needed.' ?>
</p>

<?php // Returned for clarification — show the client's note and a way to revise ?>
<?php if (strtoupper((string)$v['status']) === 'REJECTED'): ?>
<div class="card" style="border-left:4px solid #9a2a2a">
  <h2 style="margin:0 0 6px">Returned for clarification</h2>
  <?php if (!empty($v['decided_note'])): ?>
    <p style="margin:0 0 10px">The client asked: <em>“<?= e($v['decided_note']) ?>”</em></p>
  <?php else: ?>
    <p class="muted" style="margin:0 0 10px">The client returned this voucher. Revise it and resubmit.</p>
  <?php endif; ?>
  <form method="post" action="/pro/voucher" style="margin:0">
    <input type="hidden" name="voucher_id" value="<?= (int)$v['id'] ?>"><input type="hidden" name="action" value="reopen">
    <button class="btn" type="submit">Revise this voucher</button>
  </form>
</div>
<?php endif; ?>

<?php // Totals ?>
<div class="card" style="display:flex;gap:18px;flex-wrap:wrap">
  <div><div class="muted" style="font-size:12px">Fee</div><div style="font-size:22px;font-weight:800"><?= e($inr($v['fee_total'])) ?></div></div>
  <?php if ($exclusive): ?><div><div class="muted" style="font-size:12px">Expenses</div><div style="font-size:22px;font-weight:800"><?= e($inr($v['reimb_total'])) ?></div></div><?php endif; ?>
  <div><div class="muted" style="font-size:12px">Total</div><div style="font-size:22px;font-weight:800;color:var(--teal)"><?= e($inr($v['grand_total'])) ?></div></div>
</div>

<?php // Lines ?>
<div class="card">
  <h2>Days claimed</h2>
  <?php if (!$lines): ?>
    <p class="muted" style="margin:0">Nothing added yet<?= $isDraft ? ' — add your first ' . e($unit) . ' below.' : '.' ?></p>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead><tr style="text-align:left;color:var(--muted);font-size:12px">
      <th style="padding:6px 8px">Date</th><th style="padding:6px 8px"><?= e(ucfirst($unit)) ?>s</th><th style="padding:6px 8px">Fee</th>
      <?php if ($exclusive): foreach ($heads as $hk => $hl): ?><th style="padding:6px 8px"><?= e($hl) ?></th><?php endforeach; endif; ?>
      <?php if ($isDraft): ?><th></th><?php endif; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($lines as $l): ?>
      <tr style="border-top:1px solid var(--line)">
        <td style="padding:6px 8px"><?= e(substr((string)$l['work_date'], 0, 10) ?: '—') ?></td>
        <td style="padding:6px 8px"><?= e(rtrim(rtrim((string)$l['units'], '0'), '.')) ?></td>
        <td style="padding:6px 8px"><?= e($inr($l['fee'])) ?></td>
        <?php if ($exclusive): foreach ($heads as $hk => $hl): ?><td style="padding:6px 8px"><?= (float)$l[$hk] > 0 ? e($inr($l[$hk])) : '—' ?></td><?php endforeach; endif; ?>
        <?php if ($isDraft): ?>
        <td style="padding:6px 8px;text-align:right">
          <form method="post" action="/pro/voucher" style="margin:0;display:inline">
            <input type="hidden" name="voucher_id" value="<?= (int)$v['id'] ?>">
            <input type="hidden" name="action" value="del_line"><input type="hidden" name="line_id" value="<?= (int)$l['id'] ?>">
            <button class="btn sec" type="submit" style="padding:4px 10px;font-size:12px">✕</button>
          </form>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<?php // Supporting documents — receipts / bills backing the claim ?>
<div class="card">
  <h2>Supporting documents</h2>
  <p class="muted" style="margin:0 0 10px;font-size:13px">
    <?= $exclusive
        ? 'Attach receipts and bills for the expenses you claimed — travel tickets, hotel folio, cab bills. The approver sees them with your voucher.'
        : 'Attach any supporting paperwork for this claim — the approver sees it with your voucher.' ?>
  </p>
  <?php if (!$files): ?>
    <p class="muted" style="margin:0 0 <?= $canAttach ? '12px' : '0' ?>">No documents attached yet.</p>
  <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:<?= $canAttach ? '14px' : '0' ?>">
      <?php foreach ($files as $f): ?>
        <?php $forDate = ''; if ((int)$f['line_id'] > 0) { foreach ($lines as $ln) if ((int)$ln['id'] === (int)$f['line_id']) $forDate = substr((string)$ln['work_date'], 0, 10); } ?>
        <div style="display:flex;align-items:center;gap:10px;justify-content:space-between;border:1px solid var(--line);border-radius:10px;padding:9px 12px">
          <div style="min-width:0">
            <a href="/pro/voucher-file?id=<?= (int)$f['id'] ?>" target="_blank" rel="noopener" style="font-weight:600;word-break:break-word">📎 <?= e($f['file_name']) ?></a>
            <div class="muted" style="font-size:12px"><?= e($kb((int)$f['size'])) ?><?php if ($forDate): ?> · for <?= e($forDate) ?><?php endif; ?></div>
          </div>
          <?php if ($canAttach): ?>
          <form method="post" action="/pro/voucher" style="margin:0">
            <input type="hidden" name="voucher_id" value="<?= (int)$v['id'] ?>">
            <input type="hidden" name="action" value="del_file"><input type="hidden" name="file_id" value="<?= (int)$f['id'] ?>">
            <button class="btn sec" type="submit" style="padding:4px 10px;font-size:12px">Remove</button>
          </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($canAttach): ?>
  <form method="post" action="/pro/voucher" enctype="multipart/form-data">
    <input type="hidden" name="voucher_id" value="<?= (int)$v['id'] ?>">
    <input type="hidden" name="action" value="add_file">
    <div class="grid2">
      <div><label>Choose a file</label><input type="file" name="file" required></div>
      <?php if ($lines): ?>
      <div><label>For which day? (optional)</label>
        <select name="line_id">
          <option value="0">— whole voucher —</option>
          <?php foreach ($lines as $ln): ?>
            <option value="<?= (int)$ln['id'] ?>"><?= e(substr((string)$ln['work_date'], 0, 10) ?: ('Day #' . (int)$ln['id'])) ?><?php if (!empty($ln['receipt_ref'])): ?> · <?= e($ln['receipt_ref']) ?><?php endif; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
    </div>
    <button class="btn" type="submit" style="margin-top:12px">Upload document</button>
  </form>
  <?php endif; ?>
</div>

<?php // Add a day (draft only) ?>
<?php if ($isDraft): ?>
<div class="card">
  <h2>Add a <?= e($unit) ?></h2>
  <form method="post" action="/pro/voucher">
    <input type="hidden" name="voucher_id" value="<?= (int)$v['id'] ?>">
    <input type="hidden" name="action" value="add_line">
    <div class="grid2">
      <div><label>Date</label><input type="date" name="work_date"></div>
      <div><label><?= e(ucfirst($unit)) ?>s</label><input type="number" name="units" value="1" min="0.5" step="0.5"></div>
    </div>
    <?php if ($exclusive): ?>
      <p class="muted" style="margin:12px 0 4px;font-size:12px">Reimbursable expenses (against receipts)</p>
      <div class="grid2">
        <?php foreach ($heads as $hk => $hl): ?>
          <div><label><?= e($hl) ?></label><input type="number" name="<?= e($hk) ?>" value="0" min="0" step="1"></div>
        <?php endforeach; ?>
      </div>
      <label>Receipt reference</label><input type="text" name="receipt_ref" placeholder="e.g. cab bill #, hotel folio">
    <?php endif; ?>
    <label>Note</label><input type="text" name="note" placeholder="optional">
    <button class="btn" type="submit" style="margin-top:14px">Add <?= e($unit) ?></button>
  </form>
</div>

<?php if ($lines): ?>
<form method="post" action="/pro/voucher" onsubmit="return confirm('Submit this voucher for approval? You will not be able to change it after.');">
  <input type="hidden" name="voucher_id" value="<?= (int)$v['id'] ?>">
  <input type="hidden" name="action" value="submit">
  <button class="btn" type="submit" style="width:100%">Submit for approval →</button>
</form>
<?php endif; ?>
<?php else: ?>
<div class="card"><p class="muted" style="margin:0">This voucher is <strong><?= e(function_exists('connect_engv_status_label') ? connect_engv_status_label($v['status']) : $v['status']) ?></strong><?php if (!empty($v['submitted_at'])): ?> · submitted <?= e(substr((string)$v['submitted_at'], 0, 10)) ?><?php endif; ?>. It can no longer be edited.</p></div>
<?php endif; ?>
