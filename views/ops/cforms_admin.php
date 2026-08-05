<?php $forms = $forms ?? []; $groups = $groups ?? []; $counts = $counts ?? []; $fieldCounts = $fieldCounts ?? []; ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/settings">Settings</a> › Custom forms</div>
<div class="master-head"><div>
  <h1>Custom forms</h1>
  <p class="sub" style="margin:2px 0 0">Build a whole new register — no coding. Name it, choose where it sits in the menu, then add its
    fields (text, dates, and dropdowns from any master list). It appears in the menu on its own.</p>
</div></div>

<div class="panel settings-form" style="max-width:640px;margin-top:14px">
  <h3 class="tab-sub" style="margin-top:0">Add a form</h3>
  <form method="post" action="/cform-def-save">
    <div class="form-grid">
      <div class="ff ff-wide"><label>Form name *</label><input class="form-control" name="name" required placeholder="e.g. Site visit log, Asset register, Gate pass"></div>
      <div class="ff"><label>Show it under</label>
        <select class="form-control" name="nav_group"><?php foreach ($groups as $g): ?><option value="<?= e($g) ?>"><?= e($g) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Menu icon</label><input class="form-control" name="icon" value="📋" maxlength="4" style="max-width:90px"></div>
      <div class="ff ff-wide"><label>Short description <span class="muted">— optional</span></label><input class="form-control" name="help" placeholder="What this form is for"></div>
    </div>
    <button class="btn" type="submit" style="margin-top:12px">Create form &amp; add fields →</button>
  </form>
  <p class="muted" style="font-size:12px;margin-top:8px">After it's created you'll add fields. A dropdown field uses a <a href="/lookups">master list</a> —
    pick an existing one or create a new list first.</p>
</div>

<?php if ($forms): ?>
<div class="panel" style="margin-top:16px">
  <h3 class="tab-sub" style="margin-top:0">Your forms</h3>
  <table class="dt">
    <thead><tr><th>Form</th><th>Menu group</th><th class="num">Fields</th><th class="num">Records</th><th>State</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($forms as $f): ?>
      <tr<?= empty($f['active']) ? ' style="opacity:.55"' : '' ?>>
        <td><b><?= e($f['icon']) ?> <?= e($f['name']) ?></b><?php if ($f['help']): ?><br><span class="muted" style="font-size:12px"><?= e($f['help']) ?></span><?php endif; ?></td>
        <td><?= e($f['nav_group']) ?></td>
        <td class="num"><?= (int)($fieldCounts[$f['slug']] ?? 0) ?></td>
        <td class="num"><?= (int)($counts[(int)$f['id']] ?? 0) ?></td>
        <td><?= empty($f['active']) ? '<span class="pill p-mut">retired</span>' : '<span class="pill p-ok">live</span>' ?></td>
        <td class="num" style="white-space:nowrap">
          <a class="btn small" href="/custom-fields?entity=<?= e($f['slug']) ?>">Fields</a>
          <a class="btn small secondary" href="/cform?f=<?= e($f['slug']) ?>">Open</a>
          <details style="display:inline-block"><summary class="btn small secondary" style="list-style:none">Edit</summary>
            <form method="post" action="/cform-def-save" class="panel" style="position:absolute;z-index:5;min-width:280px;margin-top:4px">
              <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
              <label class="form-label">Name</label><input class="form-control" name="name" value="<?= e($f['name']) ?>">
              <label class="form-label" style="margin-top:6px">Menu group</label>
              <select class="form-control" name="nav_group"><?php foreach ($groups as $g): ?><option value="<?= e($g) ?>" <?= $g===$f['nav_group']?'selected':'' ?>><?= e($g) ?></option><?php endforeach; ?></select>
              <label class="form-label" style="margin-top:6px">Icon</label><input class="form-control" name="icon" value="<?= e($f['icon']) ?>" maxlength="4" style="max-width:90px">
              <label class="chk" style="margin-top:8px"><input type="checkbox" name="active" value="1" <?= empty($f['active'])?'':'checked' ?>> Show in the menu</label>
              <button class="btn small" type="submit" style="margin-top:8px">Save</button>
            </form>
          </details>
          <form method="post" action="/cform-def-del" style="display:inline" onsubmit="return confirm('Retire this form? It leaves the menu; its records are kept.')">
            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="btn small danger" type="submit">Retire</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
