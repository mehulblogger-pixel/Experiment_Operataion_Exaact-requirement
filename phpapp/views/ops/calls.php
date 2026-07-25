<?php
  $today = date('Y-m-d');
  $nSched = 0; $nOverdue = 0; $nClosed = 0;
  foreach ($rows as $c) {
    $needs = ($c['status'] ?? '') !== 'CLOSED' && (int)$c['job_count'] === 0;
    if (($c['status'] ?? '') === 'CLOSED') $nClosed++;
    elseif ($needs) { $nSched++; if (($c['inspection_required_date'] ?? '') && $c['inspection_required_date'] < $today) $nOverdue++; }
  }
?>
<div class="master-head">
  <div><h1><?= e(T_REG('call')) ?></h1>
  <p class="sub" style="margin:2px 0 0">Inspection calls received — open one to allocate a job, or edit the details.</p></div>
  <div style="display:flex;gap:8px">
    <a class="btn secondary" href="/calls?<?= e(http_build_query(array_merge($_GET, ['export'=>'csv']))) ?>">⬇ CSV</a>
    <?php if (is_coordinator_level()): ?><a class="btn" href="/call-new">➕ New Call</a><?php endif; ?>
  </div>
</div>

<div class="chip-row">
  <span class="ct">Showing <b><?= count($rows) ?></b></span>
  <span class="ct"><span class="dot dot-warn"></span><?= $nSched ?> to schedule</span>
  <?php if ($nOverdue): ?><span class="ct"><span class="dot dot-bad"></span><?= $nOverdue ?> overdue</span><?php endif; ?>
  <span class="ct"><span class="dot dot-ok"></span><?= $nClosed ?> closed</span>
</div>

<form method="get" action="/calls" class="filter-bar">
  <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="🔍 Search code, client or vendor…">
  <input class="form-control" type="number" name="mincost" value="<?= e($minCost ?? '') ?>" placeholder="Min cost <?= e(cur_sym()) ?>" style="max-width:140px">
  <button class="btn secondary" type="submit">Search</button>
  <?php if ($q || ($minCost ?? '') !== ''): ?><a class="btn secondary" href="/calls">Clear</a><?php endif; ?>
</form>

<div class="panel" style="padding:0;overflow:hidden">
  <?php if (!$rows): ?>
    <div style="text-align:center;padding:34px"><div style="font-size:32px">☎️</div>
      <p class="muted" style="margin:8px 0 0">No calls yet. <?php if (is_coordinator_level()): ?><a href="/call-new">Create the first call</a>.<?php endif; ?></p></div>
  <?php else: ?>
  <table class="dt">
    <thead><tr>
      <th>Call</th><th>Client</th><th>Vendor / Site</th><th>SBU</th><th>Required by</th><th class="num">Jobs</th><th class="num">Cost incurred</th><th>Status</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $c):
      $closed = ($c['status'] ?? '') === 'CLOSED';
      $needs = !$closed && (int)$c['job_count'] === 0;
      $req = $c['inspection_required_date'] ?? '';
      $reqOverdue = $needs && $req && $req < $today;
    ?>
      <tr>
        <td><a href="/call?id=<?= (int)$c['id'] ?>"><b><?= e($c['call_code']) ?></b></a></td>
        <td><?= e($c['client_disp'] ?: $c['client_name'] ?: '—') ?></td>
        <td><?= $c['vendor_name'] ? e($c['vendor_name']) : '<span class="muted">—</span>' ?></td>
        <td><?= e(OPS_SBUS[$c['sbu']] ?? '—') ?></td>
        <td><?= $req ? ($reqOverdue ? '<span class="down" style="font-weight:600">'.e($req).'</span>' : e($req)) : '<span class="muted">—</span>' ?></td>
        <td class="num"><?= (int)$c['job_count'] ?: '<span class="muted">0</span>' ?></td>
        <td class="num"><?= ((float)($c['cost_incurred'] ?? 0))>0 ? fmoney($c['cost_incurred']) : '<span class="muted">—</span>' ?></td>
        <td>
          <?php if ($closed): ?><span class="pill p-ok">Closed</span>
          <?php elseif ($needs): ?><span class="pill p-warn">To schedule</span>
          <?php else: ?><span class="pill p-info">In progress</span><?php endif; ?>
        </td>
        <td style="white-space:nowrap">
          <a class="btn small secondary" href="/call?id=<?= (int)$c['id'] ?>">Open</a>
          <?php if (is_coordinator_level()): ?><a class="btn small" href="/call-edit?id=<?= (int)$c['id'] ?>">Edit</a><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
