<?php $isClient = $roleField === 'is_client'; ?>
<div class="master-head">
  <div><h1><?= e($title) ?></h1>
    <p class="sub"><?= e($subtitle) ?> · <?= (int)$total ?> record<?= $total == 1 ? '' : 's' ?></p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <a class="btn" href="/partner-new?role=<?= e($roleField) ?>">+ Add <?= $isClient ? 'Client' : 'Vendor' ?></a>
    <?php if (is_admin_level() || can('mod.clients.edit') || can('mod.vendors.edit')): ?>
      <a class="btn ghost" href="/partner-import">⬆ Import from a spreadsheet</a>
      <a class="btn ghost" href="/partner-template?kind=<?= $isClient ? 'clients' : 'vendors' ?>&amp;fmt=xlsx">⬇ Export</a>
    <?php endif; ?>
  </div>
</div>

<form method="get" action="/<?= $isClient ? 'clients' : 'vendors' ?>" class="filter-bar">
  <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Search name / code / GSTIN / PAN…">
  <select class="form-control" name="status" onchange="this.form.submit()">
    <option value="">Any status</option>
    <?php foreach (lk_options_or('partner_status', STATUSES) as $k => $v): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
  </select>
  <button class="btn secondary" type="submit">Search</button>
  <?php if ($q || $status): ?><a class="btn secondary" href="/<?= $isClient ? 'clients' : 'vendors' ?>">Clear</a><?php endif; ?>
</form>

<div class="tbl-scroll" style="overflow-x:auto">
<table class="grid">
  <tr><th>Code</th><th>Name</th><th>Roles</th><th>Industry</th><th>GSTIN</th><th>Status</th><th>Actions</th></tr>
  <?php foreach ($rows as $p): ?>
  <tr>
    <td><a href="/partner?id=<?= (int)$p['id'] ?>"><?= e($p['code']) ?></a></td>
    <td><a href="/partner?id=<?= (int)$p['id'] ?>"><?= e(partner_name($p)) ?></a></td>
    <td><?= e(roles_label($p)) ?></td>
    <td><?= e(INDUSTRIES[$p['industry']] ?? '—') ?></td>
    <td><?= e($p['gstin'] ?: '—') ?></td>
    <td><span class="badge <?= $p['status']==='ACTIVE'?'GREEN':($p['status']==='BLACKLISTED'?'RED':'AMBER') ?>"><?= e(lk_options_or('partner_status', STATUSES)[$p['status']] ?? $p['status']) ?></span></td>
    <td class="row-actions">
      <a class="btn small secondary" href="/partner?id=<?= (int)$p['id'] ?>">Open</a>
      <a class="btn small" href="/partner-edit?id=<?= (int)$p['id'] ?>">Edit</a>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7">No records found. <a href="/partner-new?role=<?= e($roleField) ?>">Add one</a>.</td></tr><?php endif; ?>
</table>
</div>

<?php if ($pages > 1): ?>
<div class="pill-row" style="margin-top:14px;">
  <?php $base = "/" . ($isClient ? 'clients' : 'vendors') . "?q=" . urlencode($q) . "&status=" . urlencode($status); ?>
  <?php if ($page > 1): ?><a class="btn small secondary" href="<?= $base ?>&page=<?= $page-1 ?>">← Prev</a><?php endif; ?>
  <span class="muted">Page <?= $page ?> of <?= $pages ?></span>
  <?php if ($page < $pages): ?><a class="btn small secondary" href="<?= $base ?>&page=<?= $page+1 ?>">Next →</a><?php endif; ?>
</div>
<?php endif; ?>
