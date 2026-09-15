<?php
// Hiring requests. Data: $rows, $mayRaise.
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$rows = $rows ?? []; $mayRaise = $mayRaise ?? false;
$st = function_exists('hreq_statuses') ? hreq_statuses() : HREQ_STATUS;
$tone = ['DRAFT'=>'p-mut','SUBMITTED'=>'p-info','UNDER_REVIEW'=>'p-info','APPROVED'=>'p-ok','REJECTED'=>'p-mut','CANCELLED'=>'p-mut'];
?>
<div class="crumbs"><a href="/">Home</a> › Hiring requests</div>
<div class="master-head">
  <div><h1>Hiring requests</h1>
    <p class="sub" style="margin:2px 0 0">What the business has asked to recruit. A request becomes a requisition — and recruitment starts — only once it is approved.</p></div>
  <div class="row-actions">
    <?php if ($mayRaise): ?><a class="btn" href="/hiring-request">+ New request</a><?php endif; ?>
    <a class="btn secondary" href="/requisitions">Requisitions →</a>
  </div>
</div>
<div class="panel">
  <table class="grid">
    <tr><th>Request</th><th>What</th><th>Department</th><th>How many</th><th>Needed by</th><th>Priority</th><th>Status</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><a href="/hiring-request?id=<?= (int) $r['id'] ?>"><strong><?= $e($r['req_no']) ?></strong></a>
        <div class="muted" style="font-size:11.5px"><?= $e($r['requested_by_name'] ?: $r['created_by'] ?: '—') ?></div></td>
      <td><?= $e($r['job_title']) ?></td>
      <td><?= $e(function_exists('vocab_value') && $r['hiring_department_id'] && ($v = vocab_value((int) $r['hiring_department_id'])) ? vocab_display($v) : '—') ?></td>
      <td><?= (int) $r['quantity'] ?></td>
      <td><?= $e($r['required_by'] ?: '—') ?></td>
      <td><?= $e((function_exists('hreq_priorities') ? hreq_priorities() : [])[$r['priority']] ?? $r['priority']) ?></td>
      <td><span class="pill <?= $e($tone[$r['status']] ?? 'p-mut') ?>"><?= $e($st[$r['status']] ?? $r['status']) ?></span></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="muted">No hiring requests yet<?= $mayRaise ? ' — <a href="/hiring-request">raise the first one</a>.' : '.' ?></td></tr><?php endif; ?>
  </table>
</div>
