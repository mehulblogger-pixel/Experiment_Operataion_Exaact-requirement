<?php
// Connect K21 — the client reviews a voucher raised against a job they posted.
// They see the fee, the actual expenses and the receipts, and either return it
// to the inspector for clarification or approve it. Read-only on the numbers —
// the client never edits the claim, only accepts or returns it.
$v = $v ?? null; $lines = $lines ?? []; $heads = $heads ?? []; $files = $files ?? [];
if (!$v) { echo '<p class="pempty">Voucher not found. <a href="/portal/hire">Back</a></p>'; return; }
$exclusive = strtoupper((string)$v['rate_inclusive']) === 'EXCLUSIVE';
$status    = strtoupper((string)$v['status']);
$inr = fn($n) => '₹' . number_format((int)round((float)$n));
$kb  = fn($n) => $n >= 1048576 ? round($n/1048576, 1) . ' MB' : max(1, (int)round($n/1024)) . ' KB';
$pill = function ($s) {
    $m = ['DRAFT'=>['Draft','muted'],'SUBMITTED'=>['Awaiting your review','warn'],'APPROVED'=>['Approved','ok'],
          'PAID'=>['Paid','ok'],'REJECTED'=>['Returned for clarification','err']];
    [$l,$c] = $m[strtoupper((string)$s)] ?? $m['DRAFT'];
    return '<span class="ppill '.$c.'">'.e($l).'</span>';
};
$canReview = $status === 'SUBMITTED';
?>
<style>
  .ppill{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600}
  .ppill.ok{background:#e7f5ef;color:#0f7d5a}.ppill.warn{background:#fbf3d8;color:#8a6d0b}
  .ppill.err{background:#f6e6e6;color:#9a2a2a}.ppill.muted{background:#eceff1;color:#5b6b6a}
  .vt{width:100%;border-collapse:collapse;font-size:13.5px}
  .vt th,.vt td{padding:7px 9px;text-align:left;border-bottom:1px solid var(--line,#eee)}
  .vt th{color:var(--muted);font-size:12px;font-weight:600}
  .kpis{display:flex;gap:22px;flex-wrap:wrap;margin:2px 0 4px}
  .kpis .k{font-size:12px;color:var(--muted)} .kpis .val{font-size:22px;font-weight:800}
</style>

<p style="margin:0 0 8px"><a href="/portal/hire-req?id=<?= (int)$v['requirement_id'] ?>" class="plead" style="font-size:14px">← Back to the job</a></p>
<div style="display:flex;justify-content:space-between;align-items:start;gap:12px">
  <h2 class="ptitle" style="margin:0">Voucher from <?= e($v['subject_name'] ?: 'the professional') ?></h2>
  <?= $pill($v['status']) ?>
</div>
<p class="plead" style="margin:8px 0 16px">
  <?= $exclusive
      ? 'A fee-only engagement — the professional claims their fee plus actual expenses, backed by the receipts below. Review them and approve, or return the voucher with a note if something needs clarifying.'
      : 'An all-inclusive engagement — the rate covers everything, so there are no separate expense claims. Review and approve, or return with a note.' ?>
</p>

<div class="pcard" style="max-width:720px">
  <div class="kpis">
    <div><div class="k">Fee</div><div class="val"><?= e($inr($v['fee_total'])) ?></div></div>
    <?php if ($exclusive): ?><div><div class="k">Expenses</div><div class="val"><?= e($inr($v['reimb_total'])) ?></div></div><?php endif; ?>
    <div><div class="k">Total</div><div class="val" style="color:#0f7d5a"><?= e($inr($v['grand_total'])) ?></div></div>
  </div>
</div>

<?php if ($status === 'REJECTED' && !empty($v['decided_note'])): ?>
<div class="pcard" style="max-width:720px;border-left:4px solid #9a2a2a">
  <strong>You returned this for clarification:</strong>
  <div style="color:var(--muted);margin-top:4px">“<?= e($v['decided_note']) ?>”</div>
</div>
<?php endif; ?>

<h3 class="ptitle" style="font-size:16px;margin-top:24px">Days claimed</h3>
<div class="pcard" style="max-width:720px;overflow-x:auto">
  <?php if (!$lines): ?><p class="pempty" style="margin:0">No days on this voucher yet.</p>
  <?php else: ?>
  <table class="vt">
    <thead><tr><th>Date</th><th><?= e(ucfirst((string)($v['rate_unit'] ?? 'day'))) ?>s</th><th>Fee</th>
      <?php if ($exclusive) foreach ($heads as $hk=>$hl): ?><th><?= e($hl) ?></th><?php endforeach; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($lines as $l): ?>
      <tr>
        <td><?= e(substr((string)$l['work_date'],0,10) ?: '—') ?></td>
        <td><?= e(rtrim(rtrim((string)$l['units'],'0'),'.')) ?></td>
        <td><?= e($inr($l['fee'])) ?></td>
        <?php if ($exclusive) foreach ($heads as $hk=>$hl): ?><td><?= (float)$l[$hk] > 0 ? e($inr($l[$hk])) : '—' ?></td><?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<h3 class="ptitle" style="font-size:16px;margin-top:24px">Supporting documents</h3>
<div class="pcard" style="max-width:720px">
  <?php if (!$files): ?><p class="pempty" style="margin:0">No receipts attached.</p>
  <?php else: ?>
    <?php foreach ($files as $f): ?>
      <div style="display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px solid var(--line,#eee)">
        <a href="/portal/voucher-file?id=<?= (int)$f['id'] ?>" target="_blank" rel="noopener">📎 <?= e($f['file_name']) ?></a>
        <span style="color:var(--muted);font-size:12.5px"><?= e($kb((int)$f['size'])) ?></span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php if ($canReview): ?>
<div class="pcard" style="max-width:720px;margin-top:20px">
  <h3 class="ptitle" style="font-size:16px;margin:0 0 6px">Your decision</h3>
  <p class="plead" style="margin:0 0 14px">Approve the voucher, or return it to <?= e($v['subject_name'] ?: 'the professional') ?> with a note if a day or an expense needs clarifying.</p>
  <form method="post" action="/portal/voucher" style="margin:0 0 16px">
    <input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><input type="hidden" name="action" value="return">
    <label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">What needs clarifying?</label>
    <textarea class="form-control" name="note" rows="2" placeholder="e.g. please attach the hotel folio for 26 Aug" required></textarea>
    <button class="btn" type="submit" style="margin-top:10px;background:#9a2a2a">↩ Return for clarification</button>
  </form>
  <form method="post" action="/portal/voucher" onsubmit="return confirm('Approve this voucher for <?= e($inr($v['grand_total'])) ?>?');" style="margin:0">
    <input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><input type="hidden" name="action" value="approve">
    <button class="btn" type="submit">✓ Approve voucher — <?= e($inr($v['grand_total'])) ?></button>
  </form>
</div>
<?php elseif ($status === 'APPROVED' || $status === 'PAID'): ?>
<div class="pcard" style="max-width:720px;margin-top:20px"><p style="margin:0">You approved this voucher<?php if (!empty($v['decided_at'])): ?> on <?= e(substr((string)$v['decided_at'],0,10)) ?><?php endif; ?>.</p></div>
<?php elseif ($status === 'REJECTED'): ?>
<div class="pcard" style="max-width:720px;margin-top:20px"><p style="margin:0">Returned to <?= e($v['subject_name'] ?: 'the professional') ?>. You will be able to review it again once it is resubmitted.</p></div>
<?php endif; ?>
