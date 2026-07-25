<?php
  // No-code report builder — design sections & fields for a report type.
  $bySection = [];
  foreach ($fields as $f) $bySection[(int)$f['section_id']][] = $f;
  $ef = $editField;
  $allFieldKeys = array_map(fn($f)=>$f['fkey'], $fields);
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/report-types">Report types</a> › Builder</div>
<div class="master-head"><div><h1>Form builder — <?= e($type['code']) ?> <?= e($type['name']) ?></h1>
  <p class="sub" style="margin:2px 0 0">Design the report form with no coding: sections, fields, conditional &amp; calculated fields, repeatable tables, photos, GPS and signatures.</p></div>
  <a class="btn secondary" href="/report-types">← Report types</a>
</div>

<div class="dash-2col" style="align-items:start">
  <div>
    <!-- ============ existing sections & fields ============ -->
    <?php
      $renderFieldRow = function($f) use ($condOps) {
        echo '<div class="bld-field">';
        echo '<div><strong>'.e($f['label'] ?: $f['fkey']).'</strong> <span class="muted">'.e(IDEMS_FIELD_TYPES[$f['ftype']] ?? $f['ftype']).'</span>';
        if ($f['required']) echo ' <span class="pill p-bad" style="padding:0 5px">required</span>';
        if ($f['hidden']) echo ' <span class="pill p-mut" style="padding:0 5px">hidden</span>';
        if ($f['cond_field']) echo ' <span class="pill p-warn" style="padding:0 5px">if '.e($f['cond_field']).' '.e($condOps[$f['cond_op']] ?? $f['cond_op']).' '.e($f['cond_val']).'</span>';
        if ($f['ftype']==='calc' && $f['calc_expr']) echo ' <span class="pill p-info" style="padding:0 5px">= '.e($f['calc_expr']).'</span>';
        echo ' <span class="muted" style="font-size:11px">['.e($f['fkey']).']</span></div>';
        echo '<div class="bld-act">';
        echo '<form method="post" action="/report-builder?type='.(int)$f['report_type_id'].'" style="display:inline"><input type="hidden" name="_do" value="field_move"><input type="hidden" name="field_id" value="'.(int)$f['id'].'"><input type="hidden" name="dir" value="up"><button class="btn small secondary" title="Move up">↑</button></form> ';
        echo '<form method="post" action="/report-builder?type='.(int)$f['report_type_id'].'" style="display:inline"><input type="hidden" name="_do" value="field_move"><input type="hidden" name="field_id" value="'.(int)$f['id'].'"><input type="hidden" name="dir" value="down"><button class="btn small secondary" title="Move down">↓</button></form> ';
        echo '<a class="btn small secondary" href="/report-field-edit?type='.(int)$f['report_type_id'].'&id='.(int)$f['id'].'">Edit</a> ';
        echo '<form method="post" action="/report-builder?type='.(int)$f['report_type_id'].'" style="display:inline" onsubmit="return confirm(\'Remove this field?\')"><input type="hidden" name="_do" value="field_del"><input type="hidden" name="field_id" value="'.(int)$f['id'].'"><button class="btn small secondary">✕</button></form>';
        echo '</div></div>';
      };
    ?>
    <?php foreach ($sections as $s): ?>
      <div class="panel" style="margin-bottom:12px">
        <div class="ctitle" style="margin-top:0"><h3><?= e($s['title']) ?></h3>
          <span>
            <form method="post" action="/report-builder?type=<?= (int)$type['id'] ?>" style="display:inline"><input type="hidden" name="_do" value="section_move"><input type="hidden" name="section_id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="dir" value="up"><button class="btn small secondary">↑</button></form>
            <form method="post" action="/report-builder?type=<?= (int)$type['id'] ?>" style="display:inline"><input type="hidden" name="_do" value="section_move"><input type="hidden" name="section_id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="dir" value="down"><button class="btn small secondary">↓</button></form>
            <form method="post" action="/report-builder?type=<?= (int)$type['id'] ?>" style="display:inline" onsubmit="return confirm('Delete section? Its fields move to Unsectioned.')"><input type="hidden" name="_do" value="section_del"><input type="hidden" name="section_id" value="<?= (int)$s['id'] ?>"><button class="btn small secondary">Delete</button></form>
          </span>
        </div>
        <?php if ($s['help']): ?><p class="muted" style="margin:0 0 6px"><?= e($s['help']) ?></p><?php endif; ?>
        <?php foreach ($bySection[(int)$s['id']] ?? [] as $f) $renderFieldRow($f); ?>
        <?php if (empty($bySection[(int)$s['id']])): ?><p class="muted" style="margin:4px 0">No fields yet — add one on the right.</p><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!empty($bySection[0])): ?>
      <div class="panel" style="margin-bottom:12px">
        <div class="ctitle" style="margin-top:0"><h3>Unsectioned</h3></div>
        <?php foreach ($bySection[0] as $f) $renderFieldRow($f); ?>
      </div>
    <?php endif; ?>
    <?php if (!$sections && empty($bySection[0])): ?>
      <div class="panel"><p class="muted">This report type has no form yet. Add a section, then add fields — or let the system build it for you.</p>
        <?php $tplForType = ops_all("SELECT id, name, file_name FROM report_templates WHERE report_type_id=? AND file_data<>'' AND active=1 ORDER BY id DESC", [(int)$type['id']]); ?>
        <?php if ($tplForType): ?>
          <p><b>🪄 Build it from your uploaded format:</b></p>
          <?php foreach ($tplForType as $t): ?>
            <a class="btn small" href="/report-form-from-template?id=<?= (int)$t['id'] ?>"><?= e($t['name'] ?: $t['file_name']) ?></a>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="muted">Tip: upload this report type's Word format under <a href="/report-templates">Report templates</a> with <code>{{tokens}}</code> in it, and the form can be generated from it automatically.</p>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div>
    <!-- ============ add section ============ -->
    <form method="post" action="/report-builder?type=<?= (int)$type['id'] ?>" class="panel" style="margin-bottom:12px">
      <input type="hidden" name="_do" value="section_save">
      <h3 class="tab-sub" style="margin-top:0">Add a section</h3>
      <div class="ff"><label>Section title</label><input class="form-control" name="title" placeholder="e.g. Inspection details" required></div>
      <div class="ff"><label>Help note (optional)</label><input class="form-control" name="help"></div>
      <div style="margin-top:10px"><button class="btn" type="submit">Add section</button></div>
    </form>

    <!-- ============ add / edit field ============ -->
    <form method="post" action="/report-builder?type=<?= (int)$type['id'] ?>" class="panel">
      <input type="hidden" name="_do" value="field_save">
      <?php if ($ef): ?><input type="hidden" name="field_id" value="<?= (int)$ef['id'] ?>"><?php endif; ?>
      <h3 class="tab-sub" style="margin-top:0"><?= $ef ? 'Edit field' : 'Add a field' ?></h3>
      <div class="ff"><label>Label *</label><input class="form-control" name="label" value="<?= e($ef['label'] ?? '') ?>" required></div>
      <div class="ff"><label>Key <span class="muted">(auto from label if blank; used in formulas/conditions)</span></label><input class="form-control" name="fkey" value="<?= e($ef['fkey'] ?? '') ?>" placeholder="auto"></div>
      <div class="ff"><label>Section</label>
        <select class="form-control" name="section_id"><option value="">— unsectioned —</option>
          <?php foreach ($sections as $s): ?><option value="<?= (int)$s['id'] ?>" <?= ($ef && (int)$ef['section_id']===(int)$s['id'])?'selected':'' ?>><?= e($s['title']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff"><label>Field type</label>
        <select class="form-control" name="ftype" id="ftype"><?php foreach ($fieldTypes as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($ef['ftype'] ?? 'text')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
      <div class="ff" data-when="select,multiselect,radio"><label>Options <span class="muted">— one per line, or <code>lookup:sbu</code></span></label><textarea class="form-control" name="options" rows="3" placeholder="A&#10;B&#10;C"><?= e($ef['options'] ?? '') ?></textarea></div>
      <div class="ff" data-when="table"><label>Table columns <span class="muted">— one per line</span></label><textarea class="form-control" name="table_cols" rows="3" placeholder="Parameter&#10;Specified&#10;Actual&#10;Result"><?= e($ef['table_cols'] ?? '') ?></textarea></div>
      <div class="ff" data-when="calc"><label>Formula <span class="muted">— e.g. <code>qty * rate</code> (use field keys)</span></label><input class="form-control" name="calc_expr" value="<?= e($ef['calc_expr'] ?? '') ?>"></div>
      <div class="ff"><label>Placeholder / hint</label><input class="form-control" name="placeholder" value="<?= e($ef['placeholder'] ?? '') ?>"></div>
      <div class="ff"><label>Help text</label><input class="form-control" name="help" value="<?= e($ef['help'] ?? '') ?>"></div>
      <div class="form-grid" style="grid-template-columns:1fr 1fr">
        <div class="ff ff-check"><input type="checkbox" name="required" <?= ($ef && $ef['required'])?'checked':'' ?>><label>Mandatory</label></div>
        <div class="ff ff-check"><input type="checkbox" name="hidden" <?= ($ef && $ef['hidden'])?'checked':'' ?>><label>Hidden by default</label></div>
        <div class="ff"><label>Width</label><select class="form-control" name="col_span"><option value="1" <?= ($ef && (int)$ef['col_span']===1)?'selected':'' ?>>Half</option><option value="2" <?= ($ef && (int)$ef['col_span']===2)?'selected':'' ?>>Full</option></select></div>
      </div>
      <h4 style="margin:8px 0 4px">Show only when… <span class="muted" style="font-weight:400">(conditional field)</span></h4>
      <div class="form-grid" style="grid-template-columns:1fr 1fr">
        <div class="ff"><label>Field key</label>
          <select class="form-control" name="cond_field"><option value="">—</option>
            <?php foreach ($allFieldKeys as $fk): if ($ef && $fk===$ef['fkey']) continue; ?><option value="<?= e($fk) ?>" <?= ($ef && $ef['cond_field']===$fk)?'selected':'' ?>><?= e($fk) ?></option><?php endforeach; ?>
          </select></div>
        <div class="ff"><label>Operator</label>
          <select class="form-control" name="cond_op"><?php foreach ($condOps as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($ef && $ef['cond_op']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
        <div class="ff ff-wide"><label>Value</label><input class="form-control" name="cond_val" value="<?= e($ef['cond_val'] ?? '') ?>"></div>
      </div>
      <div style="margin-top:10px"><button class="btn" type="submit"><?= $ef ? 'Save field' : 'Add field' ?></button><?php if ($ef): ?> <a class="btn secondary" href="/report-builder?type=<?= (int)$type['id'] ?>">Cancel</a><?php endif; ?></div>
    </form>
  </div>
</div>

<style>
  .bld-field{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid var(--line)}
  .bld-field:last-child{border-bottom:0}
  .bld-act{white-space:nowrap;display:flex;gap:3px;align-items:center}
  .bld-act form{margin:0}
</style>
<script>
(function(){
  var sel=document.getElementById('ftype');
  function upd(){ var v=sel.value; document.querySelectorAll('[data-when]').forEach(function(el){ el.style.display = el.getAttribute('data-when').split(',').indexOf(v)>=0 ? '' : 'none'; }); }
  sel.addEventListener('change',upd); upd();
})();
</script>
