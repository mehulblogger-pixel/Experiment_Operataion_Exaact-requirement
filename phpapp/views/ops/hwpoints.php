<?php
// Manager-wide list of every open hold / witness / review / clearance point.
$rows = $rows ?? []; $canEdit = $canEdit ?? false;
$typePill = function($t) {
    $m = ['HOLD' => 'p-bad', 'WITNESS' => 'p-info', 'REVIEW' => 'p-mut', 'CLEARANCE' => 'p-warn'];
    return '<span class="pill ' . ($m[$t] ?? 'p-mut') . '">' . e(hwp_type_label($t)) . '</span>';
};
?>
<div class="page-head">
  <div><h1>Hold &amp; witness points</h1>
    <p class="sub">Every open intervention point across your jobs — what is still stopping despatch.</p></div>
  <div><a class="btn secondary" href="/dashboard">← Dashboard</a></div>
</div>

<div class="panel">
  <?php if (!$rows): ?>
    <p class="muted" style="margin:0">Nothing is holding right now. Hold and witness points appear here automatically
      the moment an inspector issues a report with an activity marked <b>Hold</b>, <b>Witnessed</b>,
      <b>Reviewed</b> or <b>Client clearance required</b>.</p>
  <?php else: ?>
  <table class="dt">
    <thead><tr><th>Type</th><th>Job</th><th>QAP clause</th><th>Activity / description</th><th>Raised</th><th>From report</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= $typePill($r['point_type']) ?></td>
        <td><?php if (!empty($r['job_id'])): ?><a href="/job?id=<?= (int)$r['job_id'] ?>#holdpoints"><?= e($r['job_code'] ?: ('Job #' . (int)$r['job_id'])) ?></a><?php else: ?><span class="muted">—</span><?php endif; ?>
            <?php if (!empty($r['office_name'])): ?><div class="muted" style="font-size:.85em"><?= e($r['office_name']) ?></div><?php endif; ?></td>
        <td><?= e($r['qap_clause'] ?: '—') ?><?= $r['sub_clause'] ? ' <span class="muted">(' . e($r['sub_clause']) . ')</span>' : '' ?></td>
        <td><?= e($r['activity'] ?: '') ?><?php if ($r['description']): ?><div class="muted" style="font-size:.9em"><?= e(mb_strimwidth((string)$r['description'], 0, 140, '…')) ?></div><?php endif; ?></td>
        <td class="muted" style="font-size:.9em"><?= e($r['raised_by'] ?: '') ?><br><?= $r['raised_at'] ? e(date('d M Y', strtotime($r['raised_at']))) : '' ?></td>
        <td><?php if (!empty($r['report_doc_id'])): ?><a href="/document?id=<?= (int)$r['report_doc_id'] ?>"><?= e($r['irn'] ?: 'report') ?></a><?php else: ?><span class="pill p-mut">manual</span><?php endif; ?></td>
        <?php if ($canEdit): ?>
        <td class="num">
          <details><summary class="btn small">Clear / waive</summary>
            <form method="post" action="/hw-point-clear" style="display:flex;flex-direction:column;gap:5px;margin-top:6px;min-width:230px">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input class="form-control" name="clearance_ref" placeholder="Clearance ref (NCR/mail/doc no.)">
              <input class="form-control" name="remark" placeholder="Remark">
              <div style="display:flex;gap:5px">
                <button class="btn small" formaction="/hw-point-clear">Clear</button>
                <button class="btn small secondary" formaction="/hw-point-waive">Waive</button>
              </div>
            </form>
          </details>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
