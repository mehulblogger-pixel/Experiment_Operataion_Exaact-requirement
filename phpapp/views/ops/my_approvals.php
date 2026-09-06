<?php
// My approvals — the current user's pending approval steps. Data: $inbox.
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$inbox = $inbox ?? [];
$ent = function ($k) { $m = ['REQUISITION'=>'Requisition','OFFER'=>'Offer','SALARY'=>'Salary structure']; return $m[$k] ?? $k; };
$fdate = fn($s) => $s ? (function_exists('fdate') ? fdate($s) : date('d M Y', strtotime($s))) : '';
$overdue = function ($s) { return $s && strtotime($s) < time(); };
$cur = function_exists('cur_sym') ? cur_sym() : '';
?>
<div class="crumbs"><a href="/">Home</a> › My approvals</div>
<div class="master-head">
  <div><h1>My approvals</h1>
    <p class="sub" style="margin:2px 0 0">Items waiting on your decision. Approving passes the item to the next level (if any); rejecting sends it back to the requester.</p></div>
</div>

<?php if (!$inbox): ?>
  <div class="panel" style="text-align:center;padding:44px 20px">
    <div style="font-size:34px">✅</div>
    <h3 style="margin:8px 0 4px">You're all caught up</h3>
    <p class="muted">Nothing is waiting on your approval right now.</p>
  </div>
<?php else: ?>
  <div style="display:grid;gap:14px">
  <?php foreach ($inbox as $s): $od = $overdue($s['sla_due']); ?>
    <div class="panel" style="padding:0;overflow:hidden;<?= $od?'border-left:4px solid #dc2626':'border-left:4px solid var(--brand,#1e40af)' ?>">
      <div style="padding:14px 16px;display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
        <div style="min-width:240px">
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <span class="pill" style="background:var(--soft,#eef2ff);color:var(--brand,#1e40af);font-size:11px"><?= $e($ent($s['entity'])) ?></span>
            <?php if ($s['label']): ?><span class="pill p-mut" style="font-size:11px"><?= $e($s['label']) ?></span><?php endif; ?>
            <?php if ($od): ?><span class="pill" style="background:#fef2f2;color:#dc2626;font-size:11px">Overdue</span><?php endif; ?>
          </div>
          <div style="font-weight:700;font-size:16px;margin-top:6px"><?= $e($s['subject'] ?: ($ent($s['entity']).' #'.$s['entity_id'])) ?></div>
          <div class="muted" style="font-size:12.5px;margin-top:3px">
            <?php if ((float)$s['amount']>0): ?>Value <b><?= $e($cur) ?><?= $e(number_format((float)$s['amount'],0)) ?></b> · <?php endif; ?>
            Requested by <?= $e($s['requester'] ?: '—') ?> · <?= $e($fdate($s['rcreated'] ?? '')) ?>
            <?php if ($s['sla_due']): ?> · Due <span style="<?= $od?'color:#dc2626;font-weight:600':'' ?>"><?= $e($fdate($s['sla_due'])) ?></span><?php endif; ?>
          </div>
          <div class="muted" style="font-size:11.5px;margin-top:2px">Rule: <?= $e($s['rule_name'] ?: '—') ?> · Level <?= (int)$s['seq'] ?></div>
        </div>
        <form method="post" style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;justify-content:flex-end">
          <input type="hidden" name="do" value="act">
          <input type="hidden" name="step_id" value="<?= (int)$s['id'] ?>">
          <input class="form-control" name="remarks" placeholder="Remark (optional)" style="min-width:180px;height:38px">
          <button class="btn" name="decision" value="approve">Approve</button>
          <button class="btn secondary" name="decision" value="reject" onclick="return confirm('Reject this request?')" style="color:#dc2626;border-color:#f0c2c2">Reject</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
