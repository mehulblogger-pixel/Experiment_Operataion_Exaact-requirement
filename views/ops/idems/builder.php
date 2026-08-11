<?php
  // No-code report builder — design sections & fields for a report type.
  $bySection = [];
  foreach ($fields as $f) $bySection[(int)$f['section_id']][] = $f;
  $ef = $editField;
  $allFieldKeys = array_map(fn($f)=>$f['fkey'], $fields);
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/report-types">Report types</a> › Builder</div>
<div class="master-head"><div><h1>Form builder <?= e($type['code']) ?></h1>
  <p class="sub" style="margin:2px 0 0">Design the report form with no coding: sections, fields, conditional &amp; calculated fields, repeatable tables, photos, GPS and signatures.</p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if (!empty($fields)): ?><a class="btn" href="/document-new?type=<?= e($type['code']) ?>">Create a report with this form →</a>
    <button type="button" class="btn secondary" id="bld-preview-toggle" title="Show the finished report next to the designer — it refreshes as you arrange fields">🔍 Live preview</button>
    <a class="btn secondary" href="/report-type-preview?type=<?= (int)$type['id'] ?>" target="_blank" title="Open the finished report filled with dummy data in a new tab">👁 Open preview</a><?php endif; ?>
    <a class="btn<?= empty($fields) ? '' : ' secondary' ?>" href="/report-autoform?type=<?= (int)$type['id'] ?>">🪄 Build from my Word file (no codes)</a>
    <a class="btn secondary" href="/report-types">← Report types</a>
  </div>
</div>

<?php if (!empty($fields)): ?>
<div class="panel" id="bld-preview-panel" style="display:none;margin-bottom:14px">
  <div class="ctitle" style="margin-top:0"><h3>Live preview <span class="muted" style="font-weight:400">— the finished report with sample data, refreshed as you arrange fields</span></h3>
    <span><button type="button" class="btn small secondary" id="bld-preview-reload">↻ Refresh</button>
      <button type="button" class="btn small secondary" id="bld-preview-close">Hide</button></span></div>
  <div style="border:1px solid var(--line);border-radius:8px;overflow:hidden;background:#f5f5f5">
    <iframe id="bld-preview-frame" title="Report preview" style="width:100%;height:660px;border:0;display:block"></iframe>
  </div>
</div>
<?php endif; ?>

<div class="dash-2col" style="align-items:start">
  <div>
    <!-- ============ existing sections & fields ============ -->
    <?php
      $renderFieldRow = function($f) use ($condOps) {
        $full = (int)$f['col_span'] === 2;
        echo '<div class="bld-field" draggable="true" data-fid="'.(int)$f['id'].'">';
        echo '<span class="bld-grip" title="Drag to reorder or move between sections">⠿</span>';
        echo '<div class="bld-fmain"><strong>'.e($f['label'] ?: $f['fkey']).'</strong> <span class="muted">'.e(IDEMS_FIELD_TYPES[$f['ftype']] ?? $f['ftype']).'</span>';
        if ($f['required']) echo ' <span class="pill p-bad" style="padding:0 5px">required</span>';
        if ($f['hidden']) echo ' <span class="pill p-mut" style="padding:0 5px">hidden</span>';
        if ($f['cond_field']) echo ' <span class="pill p-warn" style="padding:0 5px">if '.e($f['cond_field']).' '.e($condOps[$f['cond_op']] ?? $f['cond_op']).' '.e($f['cond_val']).'</span>';
        if ($f['ftype']==='calc' && $f['calc_expr']) echo ' <span class="pill p-info" style="padding:0 5px">= '.e($f['calc_expr']).'</span>';
        echo ' <span class="muted" style="font-size:11px">['.e($f['fkey']).']</span></div>';
        echo '<div class="bld-act">';
        echo '<button type="button" class="btn small secondary bld-width" data-fid="'.(int)$f['id'].'" data-span="'.($full?2:1).'" title="Toggle field width">'.($full?'▭ Full':'▯ Half').'</button> ';
        echo '<a class="btn small secondary" href="/report-field-edit?type='.(int)$f['report_type_id'].'&id='.(int)$f['id'].'">Edit</a> ';
        echo '<form method="post" action="/report-builder?type='.(int)$f['report_type_id'].'" style="display:inline" onsubmit="return confirm(\'Remove this field?\')"><input type="hidden" name="_do" value="field_del"><input type="hidden" name="field_id" value="'.(int)$f['id'].'"><button class="btn small secondary">✕</button></form>';
        echo '</div></div>';
      };
    ?>
    <?php if ($sections || !empty($bySection[0])): ?>
      <p class="muted" style="margin:0 0 8px;font-size:12.5px">💡 Drag the <span style="font-family:monospace">⠿</span> handle to reorder fields or move them between sections; drag a section header to reorder sections. Changes save as you drop.</p>
    <?php endif; ?>
    <?php if ($sections || !empty($bySection[0])): ?>
    <div id="bld-canvas">
    <?php foreach ($sections as $s): ?>
      <div class="panel bld-section" style="margin-bottom:12px" data-sid="<?= (int)$s['id'] ?>">
        <div class="ctitle bld-shead" draggable="true" style="margin-top:0;cursor:grab"><h3><span class="bld-grip">⠿</span> <?= e($s['title']) ?></h3>
          <span>
            <a class="btn small secondary" href="/report-field-edit?type=<?= (int)$type['id'] ?>&section=<?= (int)$s['id'] ?>" style="display:none"></a>
            <form method="post" action="/report-builder?type=<?= (int)$type['id'] ?>" style="display:inline" onsubmit="return confirm('Delete section? Its fields move to Unsectioned.')"><input type="hidden" name="_do" value="section_del"><input type="hidden" name="section_id" value="<?= (int)$s['id'] ?>"><button class="btn small secondary">Delete</button></form>
          </span>
        </div>
        <?php if ($s['help']): ?><p class="muted" style="margin:0 0 6px"><?= e($s['help']) ?></p><?php endif; ?>
        <details class="sec-layout" style="margin:0 0 6px">
          <summary style="cursor:pointer;font-size:11.5px;color:var(--muted)">⚙ Layout<?php if (!empty($s['page_break_before']) || !empty($s['keep_together'])): ?> <span class="pill p-ok" style="font-size:9px;padding:0 4px"><?= (!empty($s['page_break_before'])?'new page':'') . ((!empty($s['page_break_before'])&&!empty($s['keep_together']))?' · ':'') . (!empty($s['keep_together'])?'keep together':'') ?></span><?php endif; ?></summary>
          <form method="post" action="/report-builder?type=<?= (int)$type['id'] ?>" style="margin:6px 0 0;display:flex;gap:14px;align-items:center;flex-wrap:wrap">
            <input type="hidden" name="_do" value="section_save">
            <input type="hidden" name="section_id" value="<?= (int)$s['id'] ?>">
            <input type="hidden" name="title" value="<?= e($s['title']) ?>">
            <input type="hidden" name="help" value="<?= e($s['help']) ?>">
            <label style="font-size:12px"><input type="checkbox" name="page_break_before" value="1" <?= !empty($s['page_break_before'])?'checked':'' ?>> Start on a new page</label>
            <label style="font-size:12px"><input type="checkbox" name="keep_together" value="1" <?= !empty($s['keep_together'])?'checked':'' ?>> Keep together (don’t split)</label>
            <button class="btn small secondary" type="submit">Save layout</button>
          </form>
        </details>
        <div class="bld-drop" data-sec="<?= (int)$s['id'] ?>">
          <?php foreach ($bySection[(int)$s['id']] ?? [] as $f) $renderFieldRow($f); ?>
        </div>
        <?php if (empty($bySection[(int)$s['id']])): ?><p class="muted bld-empty" style="margin:4px 0"><?= 'Drop fields here — or add one on the right.' ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
      <div class="panel bld-section" style="margin-bottom:12px" data-sid="0">
        <div class="ctitle" style="margin-top:0"><h3>Unsectioned</h3></div>
        <div class="bld-drop" data-sec="0">
          <?php foreach ($bySection[0] ?? [] as $f) $renderFieldRow($f); ?>
        </div>
        <?php if (empty($bySection[0])): ?><p class="muted bld-empty" style="margin:4px 0">Drop fields here to leave them unsectioned.</p><?php endif; ?>
      </div>
    </div><!-- /bld-canvas -->
    <?php endif; ?>
    <?php if (!$sections && empty($bySection[0])): ?>
      <div class="panel"><p class="muted">This report type has no form yet. Add a section and fields by hand — or let the system build it for you from your own report format.</p>
        <p style="margin:10px 0 4px"><b>🪄 Build it from your Word file — no codes to insert:</b></p>
        <a class="btn" href="/report-autoform?type=<?= (int)$type['id'] ?>">Upload my report format &amp; build the form</a>
        <?php $tplForType = ops_all("SELECT id, name, file_name FROM report_templates WHERE report_type_id=? AND file_data<>'' AND active=1 ORDER BY id DESC", [(int)$type['id']]); ?>
        <?php if ($tplForType): ?>
          <p class="muted" style="margin:14px 0 4px;font-size:12.5px">Or build from a format you already uploaded (with <code>{{tokens}}</code>):</p>
          <?php foreach ($tplForType as $t): ?>
            <a class="btn small secondary" href="/report-form-from-template?id=<?= (int)$t['id'] ?>"><?= e($t['name'] ?: $t['file_name']) ?></a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div>
    <!-- ============ ADD A SECTION — pick a type ============ -->
    <div class="panel" style="margin-bottom:12px">
      <h3 class="tab-sub" style="margin-top:0">➕ Add a section — pick a type</h3>
      <p class="muted" style="margin:0 0 10px;font-size:12.5px">Choose a ready-made section (tables and fields already set up), build a custom table, or start blank. You can rename, reorder and fine-tune everything afterwards.</p>
      <?php
        $rt = (int)$type['id'];
        $ready = [
          ['add_scope','📋 Inspection scope (ITP)','ITP/clause, activity, quantum of check, inspection type, observation, disposition'],
          ['add_po_items','📦 PO items &amp; quantities','Sr.No, description, size, unit, PO/offered/passed/rejected/hold/balance qty'],
          ['add_refdocs','📑 Reference documents','Document name, number, revision, approval code, date of approval'],
          ['add_instruments','🔧 Instruments &amp; calibration','Pick from the equipment register — serial &amp; calibration dates auto-fill'],
          ['add_iso17020','🛡️ ISO 17020 identification','Item, method, acceptance criteria, statement of conformity, limitations'],
          ['add_holdstatus','⏸️ Order &amp; hold-point status','P.O. status (Completed/Balance/Hold) + previous &amp; current hold points'],
          ['add_photos','📷 Photographs','Take/upload photos (auto-compressed), caption each, or mark denied'],
          ['add_conclusion','📝 Conclusion &amp; remarks','Observations, conclusion and general remarks'],
          ['add_disclaimer','📄 Static text / disclaimer','A fixed paragraph printed on the report — disclaimer, standing instruction'],
          ['add_sigblock','🖋️ Sign-off block (per role)','Prepared / Reviewed / Approved — name, designation &amp; date, auto-filled'],
          ['add_signatures','✍️ Signatures (sign pads)','Manufacturer/vendor &amp; client representative signature pads'],
        ];
      ?>
      <div style="display:grid;grid-template-columns:1fr;gap:6px">
        <?php foreach ($ready as $r): ?>
          <form method="post" action="/report-builder?type=<?= $rt ?>" style="margin:0">
            <input type="hidden" name="_do" value="<?= e($r[0]) ?>">
            <button class="bld-sec-card" type="submit" title="<?= $r[2] ?>">
              <span class="t"><?= $r[1] ?></span><span class="d"><?= $r[2] ?></span>
            </button>
          </form>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ============ CUSTOM TABLE — define columns ============ -->
    <details class="panel" style="margin-bottom:12px">
      <summary style="cursor:pointer;font-weight:700">🧱 Custom table — define columns &amp; types</summary>
      <form method="post" action="/report-builder?type=<?= $rt ?>" style="margin-top:10px" onsubmit="ctSerialize()">
        <input type="hidden" name="_do" value="add_custom_table">
        <div class="ff"><label>Table / section title</label><input class="form-control" name="sec_title" placeholder="e.g. Material details" required></div>
        <label style="font-size:12.5px;color:var(--muted)">Columns — add up to ~10, each with its own type</label>
        <div id="ctcols" style="margin-top:4px"></div>
        <button type="button" class="btn small secondary" onclick="ctAdd()">+ Add column</button>
        <div id="ctfields"></div>
        <div style="margin-top:10px"><button class="btn" type="submit">Create table</button></div>
      </form>
    </details>

    <!-- ============ blank section ============ -->
    <details class="panel" style="margin-bottom:12px">
      <summary style="cursor:pointer;font-weight:700">➕ Blank section (add fields by hand)</summary>
      <form method="post" action="/report-builder?type=<?= $rt ?>" style="margin-top:10px">
        <input type="hidden" name="_do" value="section_save">
        <div class="ff"><label>Section title</label><input class="form-control" name="title" placeholder="e.g. Inspection details" required></div>
        <div class="ff"><label>Help note (optional)</label><input class="form-control" name="help"></div>
        <div class="form-grid" style="grid-template-columns:1fr 1fr">
          <div class="ff ff-check"><input type="checkbox" name="page_break_before" value="1"><label>Start on a new page</label></div>
          <div class="ff ff-check"><input type="checkbox" name="keep_together" value="1"><label>Keep together (don’t split)</label></div>
        </div>
        <div style="margin-top:10px"><button class="btn" type="submit">Add section</button></div>
      </form>
    </details>

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
      <div class="ff" data-when="select,multiselect,radio,richtext,sigblock"><label><span data-optlabel>Options</span> <span class="muted"><span data-opthint>— one per line, a master with <code>lookup:sbu</code>, or the call's order items with <code>call:po_items</code></span></span></label><textarea class="form-control" name="options" rows="4" placeholder="A&#10;B&#10;C"><?= e($ef['options'] ?? '') ?></textarea></div>
      <div class="ff" data-when="table"><label>Table columns <span class="muted">— each column can be text, a number, a date or a dropdown</span></label>
        <div id="colb"></div>
        <button type="button" class="btn small secondary" onclick="colbAdd()">+ Add column</button>
        <textarea name="table_cols" id="table_cols_raw" style="display:none"><?= e($ef['table_cols'] ?? '') ?></textarea>
        <small class="muted">Pick <b>Dropdown</b> to make a column a pick-list (e.g. a Result column of Accepted / Rejected / Hold); separate the choices with a semicolon.</small></div>
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
  .bld-field{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:7px 6px;border-bottom:1px solid var(--line);background:var(--card,transparent);border-radius:6px}
  .bld-field:last-child{border-bottom:0}
  .bld-fmain{flex:1;min-width:0}
  .bld-act{white-space:nowrap;display:flex;gap:3px;align-items:center}
  .bld-act form{margin:0}
  .bld-grip{cursor:grab;color:var(--muted,#999);font-size:15px;user-select:none;padding:0 2px}
  .bld-field.dragging{opacity:.45}
  .bld-drop{min-height:14px;border-radius:8px;transition:background .12s,box-shadow .12s}
  .bld-drop.drop-hot{background:color-mix(in srgb,var(--accent,#2b6cff) 10%,transparent);box-shadow:inset 0 0 0 2px color-mix(in srgb,var(--accent,#2b6cff) 45%,transparent)}
  .bld-section.sec-dragging{opacity:.5}
  .bld-shead h3{display:flex;align-items:center;gap:6px}
  .bld-sec-card{width:100%;text-align:left;display:flex;flex-direction:column;gap:2px;padding:9px 12px;border:1px solid var(--line);border-radius:9px;background:var(--card,var(--panel2));cursor:pointer;transition:border-color .12s,background .12s}
  .bld-sec-card:hover{border-color:var(--accent,#2b6cff);background:color-mix(in srgb,var(--accent,#2b6cff) 7%,transparent)}
  .bld-sec-card .t{font-weight:700;font-size:13.5px;color:var(--ink)}
  .bld-sec-card .d{font-size:11.5px;color:var(--muted);line-height:1.35}
  .ctrow{display:flex;gap:6px;margin:0 0 5px;align-items:center}
</style>
<script>
(function(){
  var sel=document.getElementById('ftype');
  var oL=document.querySelector('[data-optlabel]'), oH=document.querySelector('[data-opthint]');
  function upd(){ var v=sel.value; document.querySelectorAll('[data-when]').forEach(function(el){ el.style.display = el.getAttribute('data-when').split(',').indexOf(v)>=0 ? '' : 'none'; });
    if(oL&&oH){ if(v==='richtext'){ oL.textContent='Content'; oH.innerHTML='— the fixed text / disclaimer to print. Blank line = new paragraph.'; }
      else if(v==='sigblock'){ oL.textContent='Roles'; oH.innerHTML='— one sign-off role per line (e.g. Prepared by / Reviewed by / Approved by). Name &amp; designation auto-fill.'; }
      else { oL.textContent='Options'; oH.innerHTML='— one per line, a master with <code>lookup:sbu</code>, or the call’s order items with <code>call:po_items</code>'; } } }
  sel.addEventListener('change',upd); upd();
})();

// ---- visual table-column editor (name + type + dropdown options) ----------
var COLB_TYPES = ['text','number','date','select','textarea','unit'];
function colbSerialize(){
  var lines=[];
  document.querySelectorAll('#colb .colrow').forEach(function(row){
    var name=row.querySelector('.c-name').value.trim(); if(!name) return;
    var type=row.querySelector('.c-type').value;
    var line;
    if(type==='text') line=name;
    else if(type==='select'){ var o=row.querySelector('.c-opts').value.trim(); line=name+'|select'+(o?('|'+o):''); }
    else line=name+'|'+type;
    var mg=row.querySelector('.c-merge'); if(mg&&mg.checked) line+='|merge';
    lines.push(line);
  });
  var raw=document.getElementById('table_cols_raw'); if(raw) raw.value=lines.join('\n');
}
function colbParse(raw){
  raw=(raw||'').trim(); if(!raw) return [];
  var looksNew=false;
  raw.split(/\r?\n/).forEach(function(ln){ var p=ln.split('|'); if(p.length>=2){ var t=p[1].trim().toLowerCase(); if(t==='dropdown')t='select'; if(COLB_TYPES.indexOf(t)>=0) looksNew=true; } });
  var rows=[];
  if(looksNew){
    raw.split(/\r?\n/).forEach(function(ln){ ln=ln.trim(); if(!ln)return;
      var segs=ln.split('|').map(function(s){return s.trim();});
      var name=segs.shift()||'';
      var merge=false; segs=segs.filter(function(s){ if(s.toLowerCase()==='merge'){merge=true;return false;} return true; });
      var t=(segs[0]||'text').toLowerCase(); if(t==='dropdown')t='select'; if(COLB_TYPES.indexOf(t)<0)t='text';
      rows.push({name:name,type:t,opts:(t==='select'&&segs[1])?segs[1]:'',merge:merge}); });
  } else {
    raw.split(/\r?\n|\|/).forEach(function(c){ c=c.trim(); if(!c)return; rows.push({name:c,type:'text',opts:'',merge:false}); });
  }
  return rows;
}
function colbRow(col){
  col=col||{name:'',type:'text',opts:'',merge:false};
  var div=document.createElement('div'); div.className='colrow';
  div.style.cssText='display:flex;gap:6px;margin:0 0 5px;align-items:center';
  div.innerHTML='<input class="form-control c-name" placeholder="Column name" style="flex:2">'+
    '<select class="form-control c-type" style="flex:1"><option value="text">Text</option><option value="number">Number</option><option value="date">Date</option><option value="select">Dropdown</option><option value="unit">Unit picker</option><option value="textarea">Long text</option></select>'+
    '<input class="form-control c-opts" placeholder="Accepted; Rejected; Hold" style="flex:2">'+
    '<label class="c-merge-lbl" title="Merge identical values down this column (rowspan) in the PDF/Word output" style="display:flex;align-items:center;gap:3px;font-size:11px;white-space:nowrap;color:var(--muted)"><input type="checkbox" class="c-merge">merge</label>'+
    '<button type="button" class="btn small secondary c-del" title="Remove column">✕</button>';
  div.querySelector('.c-name').value=col.name;
  div.querySelector('.c-type').value=col.type;
  div.querySelector('.c-opts').value=col.opts;
  div.querySelector('.c-merge').checked=!!col.merge;
  function tog(){ div.querySelector('.c-opts').style.display = div.querySelector('.c-type').value==='select'?'':'none'; }
  tog();
  div.querySelector('.c-type').addEventListener('change',function(){tog();colbSerialize();});
  div.querySelector('.c-name').addEventListener('input',colbSerialize);
  div.querySelector('.c-opts').addEventListener('input',colbSerialize);
  div.querySelector('.c-merge').addEventListener('change',colbSerialize);
  div.querySelector('.c-del').addEventListener('click',function(){div.remove();colbSerialize();});
  return div;
}
function colbAdd(){ var h=document.getElementById('colb'); if(h){ h.appendChild(colbRow()); colbSerialize(); } }

// ---- custom-table column editor (Add a section → Custom table) -------------
function ctSerialize(){ return true; }   // rows carry named inputs directly
function ctRow(){
  var div=document.createElement('div'); div.className='ctrow';
  div.innerHTML='<input class="form-control" name="col_name[]" placeholder="Column name" style="flex:2">'+
    '<select class="form-control ct-type" name="col_type[]" style="flex:1">'+
    '<option value="text">Text</option><option value="number">Number</option><option value="date">Date</option>'+
    '<option value="select">Dropdown</option><option value="unit">Unit</option><option value="textarea">Long text</option></select>'+
    '<input class="form-control ct-opts" name="col_opts[]" placeholder="A; B; C" style="flex:2;display:none">'+
    '<button type="button" class="btn small secondary" title="Remove">✕</button>';
  function tog(){ div.querySelector('.ct-opts').style.display = div.querySelector('.ct-type').value==='select'?'':'none'; }
  div.querySelector('.ct-type').addEventListener('change',tog); tog();
  div.querySelector('button').addEventListener('click',function(){ div.remove(); });
  return div;
}
function ctAdd(){ var h=document.getElementById('ctcols'); if(h) h.appendChild(ctRow()); }
(function ctInit(){ var h=document.getElementById('ctcols'); if(h && !h.children.length){ h.appendChild(ctRow()); h.appendChild(ctRow()); h.appendChild(ctRow()); } })();
(function colbInit(){
  var host=document.getElementById('colb'), raw=document.getElementById('table_cols_raw');
  if(!host||!raw) return;
  var cols=colbParse(raw.value); if(!cols.length) cols=[{name:'',type:'text',opts:''}];
  host.innerHTML=''; cols.forEach(function(c){ host.appendChild(colbRow(c)); });
  colbSerialize();
})();

// ---- drag-and-drop layout designer ----------------------------------------
(function(){
  var canvas=document.getElementById('bld-canvas'); if(!canvas) return;
  var TYPE=<?= json_encode((int)$type['id']) ?>;
  function post(doName, extra){
    var body=new URLSearchParams(); body.set('_do',doName); body.set('report_type_id',TYPE); body.set('_ajax','1');
    body.set('_csrf', (document.querySelector('input[name=_csrf]')||{}).value || '');
    Object.keys(extra||{}).forEach(function(k){ body.set(k, extra[k]); });
    return fetch('/report-builder?type='+TYPE, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:body.toString(), credentials:'same-origin'})
      .then(function(r){ if(window.bldPreviewRefresh) window.bldPreviewRefresh(); return r; });
  }
  // --- field drag ---
  var dragEl=null;
  function afterEl(container, y){
    var els=[].slice.call(container.querySelectorAll('.bld-field:not(.dragging)'));
    var closest={offset:-Infinity, el:null};
    els.forEach(function(c){ var b=c.getBoundingClientRect(); var off=y-b.top-b.height/2; if(off<0&&off>closest.offset){ closest={offset:off, el:c}; } });
    return closest.el;
  }
  canvas.querySelectorAll('.bld-field').forEach(function(f){
    f.addEventListener('dragstart',function(e){ if(f.closest('.bld-shead')) return; dragEl=f; f.classList.add('dragging'); e.dataTransfer.effectAllowed='move'; });
    f.addEventListener('dragend',function(){ if(!dragEl)return; dragEl.classList.remove('dragging'); dragEl=null; canvas.querySelectorAll('.drop-hot').forEach(function(d){d.classList.remove('drop-hot');}); saveFieldOrder(); });
  });
  canvas.querySelectorAll('.bld-drop').forEach(function(drop){
    drop.addEventListener('dragover',function(e){ if(!dragEl)return; e.preventDefault(); drop.classList.add('drop-hot');
      var after=afterEl(drop, e.clientY); if(after==null) drop.appendChild(dragEl); else drop.insertBefore(dragEl, after); });
    drop.addEventListener('dragleave',function(e){ if(e.target===drop) drop.classList.remove('drop-hot'); });
    drop.addEventListener('drop',function(e){ e.preventDefault(); drop.classList.remove('drop-hot'); });
  });
  function saveFieldOrder(){
    var items=[];
    canvas.querySelectorAll('.bld-drop').forEach(function(drop){
      var sec=drop.getAttribute('data-sec');
      drop.querySelectorAll('.bld-field').forEach(function(f){ items.push({id:parseInt(f.getAttribute('data-fid'),10), section:parseInt(sec,10)}); });
    });
    post('field_reorder', {items:JSON.stringify(items)});
  }
  // --- section drag ---
  var secDrag=null;
  canvas.querySelectorAll('.bld-shead').forEach(function(head){
    var sec=head.closest('.bld-section');
    head.addEventListener('dragstart',function(e){ secDrag=sec; sec.classList.add('sec-dragging'); e.dataTransfer.effectAllowed='move'; });
    head.addEventListener('dragend',function(){ if(!secDrag)return; secDrag.classList.remove('sec-dragging'); secDrag=null; saveSectionOrder(); });
  });
  canvas.addEventListener('dragover',function(e){
    if(!secDrag)return; e.preventDefault();
    var secs=[].slice.call(canvas.querySelectorAll('.bld-section:not(.sec-dragging)'));
    var after=null;
    for(var i=0;i<secs.length;i++){ var b=secs[i].getBoundingClientRect(); if(e.clientY < b.top+b.height/2){ after=secs[i]; break; } }
    // keep Unsectioned (data-sid=0) pinned last
    if(after && after.getAttribute('data-sid')==='0') { canvas.insertBefore(secDrag, after); }
    else if(after) canvas.insertBefore(secDrag, after);
  });
  function saveSectionOrder(){
    var ids=[];
    canvas.querySelectorAll('.bld-section').forEach(function(s){ var id=s.getAttribute('data-sid'); if(id&&id!=='0') ids.push(parseInt(id,10)); });
    post('section_reorder', {ids:JSON.stringify(ids)});
  }
  // --- inline width toggle ---
  canvas.querySelectorAll('.bld-width').forEach(function(btn){
    btn.addEventListener('click',function(){
      var cur=parseInt(btn.getAttribute('data-span'),10)||1; var next=cur===2?1:2;
      btn.setAttribute('data-span',next); btn.innerHTML = next===2?'▭ Full':'▯ Half';
      post('field_width', {field_id:btn.getAttribute('data-fid'), col_span:next});
    });
  });
})();

// ---- live preview pane ----------------------------------------------------
(function(){
  var TYPE=<?= json_encode((int)$type['id']) ?>;
  var toggle=document.getElementById('bld-preview-toggle');
  var panel=document.getElementById('bld-preview-panel');
  var frame=document.getElementById('bld-preview-frame');
  if(!toggle||!panel||!frame) return;
  var pending=null;
  function load(){ frame.src='/report-type-preview?type='+TYPE+'&_t='+(new Date().getTime()%100000); }
  // debounced refresh, exposed to the drag-drop code via window.bldPreviewRefresh
  window.bldPreviewRefresh=function(){
    if(panel.style.display==='none') return;    // only refresh when visible
    if(pending) clearTimeout(pending);
    pending=setTimeout(load, 500);
  };
  function open(){ panel.style.display=''; toggle.classList.add('active'); load(); panel.scrollIntoView({behavior:'smooth',block:'nearest'}); }
  function close(){ panel.style.display='none'; toggle.classList.remove('active'); }
  toggle.addEventListener('click',function(){ panel.style.display==='none'?open():close(); });
  var rl=document.getElementById('bld-preview-reload'); if(rl) rl.addEventListener('click',load);
  var cl=document.getElementById('bld-preview-close'); if(cl) cl.addEventListener('click',close);
  // also refresh after any add/edit/delete form submits leave & reload the page
  // (those are full navigations, so the panel simply reopens on demand).
})();
</script>
