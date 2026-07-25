<?php // Compliance audit log (basic viewer) ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/documents">Documents</a> › Audit log</div>
<div class="master-head"><div><h1>Compliance audit log</h1>
  <p class="sub" style="margin:2px 0 0">Every critical action is recorded here and is never hard-deleted. Showing the latest <?= count($rows) ?>.</p></div></div>

<form method="get" action="/audit-log" class="chip-row" style="margin:10px 0;gap:6px">
  <select class="form-control" name="action" onchange="this.form.submit()" style="max-width:200px"><option value="">All actions</option>
    <?php foreach ($actions as $a): ?><option value="<?= e($a) ?>" <?= $act===$a?'selected':'' ?>><?= e($a) ?></option><?php endforeach; ?>
  </select>
  <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search IRN / user" style="min-width:200px">
  <button class="btn small" type="submit">Search</button>
</form>

<div class="panel" style="padding:0;overflow:hidden">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>When</th><th>IRN</th><th>Action</th><th>By</th><th>Role</th><th>Detail</th></tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="6" class="muted" style="padding:16px">No audit entries yet.</td></tr><?php endif; ?>
      <?php foreach ($rows as $a): ?>
      <tr>
        <td class="muted" style="white-space:nowrap"><?= e($a['created_at'] ? date('d M Y H:i', strtotime($a['created_at'])) : '—') ?></td>
        <td><?= e($a['irn'] ?: '—') ?></td>
        <td><span class="pill p-mut"><?= e($a['action']) ?></span></td>
        <td><?= e($a['username']) ?></td>
        <td class="muted"><?= e(ORG_ROLES[$a['role']] ?? $a['role']) ?></td>
        <td class="muted"><?= e(trim(($a['field']?$a['field'].': ':'').($a['old_value']?$a['old_value'].' → ':'').($a['new_value'] ?? '')) ?: ($a['reason'] ?? '')) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
