<?php // Report-type registry — admin adds unlimited types ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/documents"><?= e(T_REG('report')) ?></a> › Report types</div>
<div class="master-head"><div><h1><?= e(TH('report')) ?> types</h1>
  <p class="sub" style="margin:2px 0 0">The catalogue of report types. Built-in TPIA types are seeded; add your own with no coding.</p></div></div>

<form method="post" action="/report-types" class="panel">
  <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
  <h3 class="tab-sub" style="margin-top:0"><?= $edit ? 'Edit type' : 'Add a report type' ?></h3>
  <div class="form-grid">
    <div class="ff"><label>Code *</label><input class="form-control" name="code" value="<?= e($edit['code'] ?? '') ?>" placeholder="e.g. IR" maxlength="16" required></div>
    <div class="ff"><label>Name *</label><input class="form-control" name="name" value="<?= e($edit['name'] ?? '') ?>" placeholder="e.g. Inspection Report" required></div>
    <div class="ff"><label>Category</label>
      <select class="form-control" name="category"><?php foreach (lk_options_or('report_category', IDEMS_CATEGORIES) as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($edit['category'] ?? 'TPIA_REPORT')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff ff-check"><input type="checkbox" name="active" <?= (!$edit || $edit['active'])?'checked':'' ?>><label>Active</label></div>
  </div>
  <div style="margin-top:12px"><button class="btn" type="submit"><?= $edit ? 'Save' : 'Add type' ?></button><?php if ($edit): ?> <a class="btn secondary" href="/report-types">Cancel</a><?php endif; ?></div>
</form>

<div class="panel" style="padding:0;overflow:hidden;margin-top:14px">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Code</th><th>Name</th><th>Category</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><strong><?= e($r['code']) ?></strong></td>
        <td><?= e($r['name']) ?><?= $r['is_system'] ? ' <span class="muted" style="font-size:11px">(built-in)</span>' : '' ?></td>
        <td class="muted"><?= e(lk_options_or('report_category', IDEMS_CATEGORIES)[$r['category']] ?? $r['category']) ?></td>
        <td><?= $r['active'] ? '<span class="pill p-ok">Active</span>' : '<span class="pill p-mut">Inactive</span>' ?></td>
        <td style="white-space:nowrap">
          <a class="btn small" href="/report-builder?type=<?= (int)$r['id'] ?>">Design form</a>
          <a class="btn small secondary" href="/report-type-edit?id=<?= (int)$r['id'] ?>">Edit</a>
          <form method="post" action="/report-types" style="display:inline" onsubmit="return confirm('<?= $r['is_system']?'Deactivate this built-in type?':'Remove this type?' ?>')"><input type="hidden" name="_do" value="del"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn small secondary" type="submit"><?= $r['is_system']?'Deactivate':'Remove' ?></button></form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
