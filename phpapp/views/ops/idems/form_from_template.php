<?php
  // Preview + confirm the form fields generated from an uploaded client format.
  $new = array_values(array_filter($plan, fn($p)=>!$p['exists']));
  $have = array_values(array_filter($plan, fn($p)=>$p['exists']));
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/report-templates">Report templates</a> › Build form</div>
<div class="master-head">
  <div><h1>Build the form from this format</h1>
    <p class="sub" style="margin:2px 0 0">Reading <strong><?= e($tpl['file_name'] ?: $tpl['name']) ?></strong> for <strong><?= e($type['code']) ?> — <?= e($type['name']) ?></strong>. Every <code>{{token}}</code> you placed in the Word file becomes a field the inspector fills, so the report comes out matching the format exactly.</p></div>
  <a class="btn secondary" href="/report-templates">← Templates</a>
</div>

<?php if (empty($zipOk)): ?>
  <div class="panel" style="border:1px solid var(--bad)"><b style="color:var(--bad)">The PHP <code>zip</code> extension is not enabled</b> — the Word file cannot be read here. Enable it in cPanel, then try again.</div>
<?php elseif (!$plan): ?>
  <div class="panel">
    <p><b>No tokens found in this Word file.</b></p>
    <p class="muted">Open the format in Word and type the placeholders where the values should appear — for example <code>{{material_grade}}</code> after "Material grade:", or <code>{{checkpoints.parameter}}</code> inside a table row so it repeats. Save it, upload it again, then come back here.</p>
    <p class="muted">Standard details (IRN, client, project, PO, drawing, inspector, dates…) are already known by the system — you only need tokens for the parts the inspector types.</p>
    <a class="btn secondary" href="/report-templates?tokens=<?= (int)$type['id'] ?>">See the full token list</a>
  </div>
<?php else: ?>

<?php if ($have): ?>
<div class="panel" style="border:1px solid var(--ok)">
  <b style="color:var(--ok)">✓ Already in the form (<?= count($have) ?>)</b> —
  <?php foreach ($have as $p): ?><code style="margin-right:6px"><?= e($p['key']) ?></code><?php endforeach; ?>
  <div class="muted" style="font-size:12px;margin-top:4px">These will be left exactly as they are.</div>
</div>
<?php endif; ?>

<?php if (!$new): ?>
  <div class="panel"><p>Every token in this format already has a field. Nothing to create — open the <a href="/report-builder?type=<?= (int)$type['id'] ?>">form builder</a> to fine-tune.</p></div>
<?php else: ?>
<form method="post" action="/report-form-from-template?id=<?= (int)$tpl['id'] ?>" class="panel">
  <input type="hidden" name="_do" value="create">
  <input type="hidden" name="tpl_id" value="<?= (int)$tpl['id'] ?>">
  <input type="hidden" name="report_type_id" value="<?= (int)$type['id'] ?>">

  <h3 class="tab-sub" style="margin-top:0">Fields to create <span class="muted">(<?= count($new) ?>)</span></h3>
  <p class="muted" style="margin:0 0 10px">The label and type below are a suggestion taken from the wording next to each token — adjust anything before creating. Untick a row to skip it.</p>

  <div class="form-grid" style="grid-template-columns:1fr 1fr">
    <div class="ff"><label>Put them in</label>
      <select class="form-control" name="section_id"><option value="">— a new section —</option>
        <?php foreach ($sections as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['title']) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>New section name</label><input class="form-control" name="section_title" value="Report body — <?= e($tpl['name'] ?: $type['name']) ?>"></div>
  </div>

  <div class="tbl-scroll" style="overflow-x:auto;margin-top:8px">
  <table class="dt">
    <thead><tr><th style="width:38px"></th><th>Token</th><th>Label the inspector sees</th><th>Field type</th><th>Table columns</th></tr></thead>
    <tbody>
      <?php foreach ($new as $p): $k = e($p['key']); ?>
      <tr>
        <td><input type="checkbox" name="use[<?= $k ?>]" value="1" checked></td>
        <td><code><?= $p['kind']==='table' ? '{{'.$k.'.…}}' : '{{'.$k.'}}' ?></code></td>
        <td><input class="form-control" name="label[<?= $k ?>]" value="<?= e($p['label']) ?>"></td>
        <td>
          <select class="form-control" name="ftype[<?= $k ?>]">
            <?php foreach ($fieldTypes as $ft=>$fl): if (in_array($ft,['heading','note'],true)) continue; ?>
              <option value="<?= e($ft) ?>" <?= $p['type']===$ft?'selected':'' ?>><?= e($fl) ?></option>
            <?php endforeach; ?>
          </select>
        </td>
        <td><?php if ($p['kind']==='table'): ?>
              <input class="form-control" name="cols[<?= $k ?>]" value="<?= e(implode("\n", array_values($p['cols']))) ?>" title="one per line">
              <small class="muted"><?= count($p['cols']) ?> column(s) found in the table row</small>
            <?php else: ?><span class="muted">—</span><input type="hidden" name="cols[<?= $k ?>]" value=""><?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <div style="margin-top:14px">
    <button class="btn" type="submit">Create <?= count($new) ?> field(s)</button>
    <a class="btn secondary" href="/report-builder?type=<?= (int)$type['id'] ?>">Open builder instead</a>
  </div>
</form>
<?php endif; ?>
<?php endif; ?>
