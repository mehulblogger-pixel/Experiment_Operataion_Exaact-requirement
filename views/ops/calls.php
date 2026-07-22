<div class="master-head">
  <div><h1>Call Register</h1><p class="sub">Inspection calls received · <?= count($rows) ?> record(s)</p></div>
  <?php if (is_coordinator_level()): ?><a class="btn" href="/call-new">+ New Call</a><?php endif; ?>
</div>
<form method="get" action="/calls" class="filter-bar">
  <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Search code / client / vendor…">
  <button class="btn secondary" type="submit">Search</button>
  <?php if ($q): ?><a class="btn secondary" href="/calls">Clear</a><?php endif; ?>
</form>
<table class="grid">
  <tr><th>Call</th><th>Client</th><th>Vendor / Site</th><th>Region</th><th>SBU</th><th>Required by</th><th>Jobs</th><th>Status</th><th>Actions</th></tr>
  <?php foreach ($rows as $c): ?>
  <tr>
    <td><a href="/call?id=<?= (int)$c['id'] ?>"><?= e($c['call_code']) ?></a></td>
    <td><?= e($c['client_disp'] ?: $c['client_name'] ?: '—') ?></td>
    <td><?= e($c['vendor_name'] ?: '—') ?></td>
    <td><?= e(OPS_REGIONS[$c['region']] ?? '—') ?></td>
    <td><?= e(OPS_SBUS[$c['sbu']] ?? '—') ?></td>
    <td><?= e($c['inspection_required_date'] ?: '—') ?></td>
    <td><?= (int)$c['job_count'] ?></td>
    <td><span class="badge <?= $c['status']==='OPEN'?'AMBER':'GREEN' ?>"><?= e($c['status']) ?></span></td>
    <td class="row-actions">
      <a class="btn small secondary" href="/call?id=<?= (int)$c['id'] ?>">Open</a>
      <?php if (is_coordinator_level()): ?><a class="btn small" href="/call-edit?id=<?= (int)$c['id'] ?>">Edit</a><?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="9">No calls yet. <?php if (is_coordinator_level()): ?><a href="/call-new">Create the first call</a>.<?php endif; ?></td></tr><?php endif; ?>
</table>
