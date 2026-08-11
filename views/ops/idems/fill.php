<?php
  // Fill the report body from the designed form schema.
  $bySection = [];
  foreach ($fields as $f) $bySection[(int)$f['section_id']][] = $f;
  $filesByField = [];
  foreach ($files as $fl) $filesByField[$fl['field_key']][] = $fl;

  $condAttr = function($f) {
    if (!$f['cond_field'] || !$f['cond_op']) return '';
    return ' data-cond="'.e($f['cond_field']).'" data-cond-op="'.e($f['cond_op']).'" data-cond-val="'.e($f['cond_val']).'"';
  };

  $sugg = $sugg ?? [];
  $auto = $auto ?? [];
  $renderField = function($f) use ($data, $filesByField, $condAttr, $sugg, $auto, $doc) {
    $k = $f['fkey']; $val = $data[$k] ?? '';
    $span = (int)$f['col_span'] === 2 ? ' ff-wide' : '';
    $req = $f['required'] ? ' <span style="color:var(--bad)">*</span>' : '';
    $autoTag = isset($auto[$k]) ? ' <span class="pill p-ok" style="padding:0 5px;font-size:10px" title="Filled in from the inspection call — change it if needed">auto</span>' : '';
    $reqAttr = $f['required'] ? ' data-req="1"' : '';
    if ($f['ftype'] === 'heading') { echo '<h3 class="tab-sub" style="grid-column:1/-1"'.$condAttr($f).'>'.e($f['label']).'</h3>'; return; }
    if ($f['ftype'] === 'note')    { echo '<p class="muted" style="grid-column:1/-1"'.$condAttr($f).'>'.e($f['label'] ?: $f['help']).'</p>'; return; }
    if ($f['ftype'] === 'richtext') {
      // A fixed disclaimer / standing instruction — printed on the report, shown
      // read-only here so the inspector reads it in context (no field to fill).
      echo '<div class="rt-static" style="grid-column:1/-1;border-left:3px solid var(--line,#ccd);padding:4px 0 4px 10px;margin:2px 0"'.$condAttr($f).'>';
      if (trim((string)$f['label']) !== '') echo '<div style="font-weight:600;font-size:12.5px;margin-bottom:2px">'.e($f['label']).'</div>';
      foreach (preg_split('/\r?\n/', (string)($f['field_options'] ?? '')) as $ln) {
        if (trim($ln)==='') { echo '<div style="height:6px"></div>'; continue; }
        echo '<p class="muted" style="margin:0 0 3px;font-size:12px;line-height:1.4">'.e($ln).'</p>';
      }
      echo '</div>'; return;
    }
    echo '<div class="ff'.$span.'" data-fieldwrap="'.e($k).'"'.$condAttr($f).'>';
    echo '<label>'.e($f['label']).$req.$autoTag.'</label>';
    $opts = idems_field_options($f, $doc);
    switch ($f['ftype']) {
      case 'textarea':
        echo '<textarea class="form-control ta-improve" id="ta_'.e($k).'" name="f['.e($k).']" data-key="'.e($k).'"'.$reqAttr.' rows="3" placeholder="'.e($f['placeholder']).'">'.e(is_array($val)?'':$val).'</textarea>';
        echo '<div style="margin-top:4px"><button type="button" class="btn small secondary" onclick="idemsImprove(\''.e($k).'\')">✒️ Improve wording</button> <span class="muted" style="font-size:11px">type shorthand — e.g. “dimension ok”</span></div>';
        break;
      case 'number': case 'text': case 'date': case 'time': case 'qr':
        $t = $f['ftype']==='number'?'number':($f['ftype']==='date'?'date':($f['ftype']==='time'?'time':'text'));
        echo '<input class="form-control" type="'.$t.'" name="f['.e($k).']" data-key="'.e($k).'"'.$reqAttr.' value="'.e(is_array($val)?'':$val).'" placeholder="'.e($f['placeholder']).'">'; break;
      case 'instrument':
        // Master instruments as suggestions AND free typing (datalist): lab / NDT /
        // calibration pick a calibrated instrument; site inspectors type their own.
        $dl = 'instlist_'.preg_replace('/[^A-Za-z0-9_]/','',$k);
        echo '<input class="form-control" list="'.$dl.'" name="f['.e($k).']" data-key="'.e($k).'"'.$reqAttr.' value="'.e(is_array($val)?'':$val).'" placeholder="'.e($f['placeholder'] ?: 'Pick a calibrated instrument, or type one used on site').'">';
        echo '<datalist id="'.$dl.'">';
        if (function_exists('equipment_all')) foreach (equipment_all() as $eq) {
            $lbl = trim(($eq['name'] ?? '').($eq['serial_no'] ? ' — '.$eq['serial_no'] : '').($eq['code'] ? ' ['.$eq['code'].']' : ''));
            if ($lbl !== '') echo '<option value="'.e($lbl).'">';
        }
        echo '</datalist>';
        echo '<small class="muted">From the equipment master (calibrated), or type an instrument used at site.</small>'; break;
      case 'calc':
        echo '<input class="form-control" type="text" name="f['.e($k).']" data-key="'.e($k).'" data-calc="'.e($f['calc_expr']).'" value="'.e(is_array($val)?'':$val).'" readonly style="background:var(--soft)">'; break;
      case 'checkbox':
        echo '<label class="chk"><input type="checkbox" name="f['.e($k).']" data-key="'.e($k).'" value="1" '.(($val==='1'||$val===1||$val==='Yes')?'checked':'').'> Yes</label>'; break;
      case 'yesno':
        echo '<div class="chip-row" data-key="'.e($k).'">';
        foreach (['Yes','No','N/A'] as $yn) echo '<label class="chk" style="margin-right:12px"><input type="radio" name="f['.e($k).']" value="'.$yn.'" '.($val===$yn?'checked':'').'> '.$yn.'</label>';
        echo '</div>'; break;
      case 'select': case 'unit':
        echo '<select class="form-control searchable" name="f['.e($k).']" data-key="'.e($k).'"'.$reqAttr.'><option value="">—</option>';
        foreach ($opts as $ok=>$ol) echo '<option value="'.e($ok).'" '.($val===$ok?'selected':'').'>'.e($ol).'</option>';
        echo '</select>'; break;
      case 'radio':
        echo '<div class="chip-row" data-key="'.e($k).'">';
        foreach ($opts as $ok=>$ol) echo '<label class="chk" style="border:1px solid var(--line);padding:4px 8px;border-radius:8px"><input type="radio" name="f['.e($k).']" value="'.e($ok).'" '.($val===$ok?'checked':'').'> '.e($ol).'</label>';
        echo '</div>'; break;
      case 'multiselect':
        echo '<div class="checkgrid" data-key="'.e($k).'">';
        $arr = is_array($val)?$val:[];
        foreach ($opts as $ok=>$ol) echo '<label class="chk"><input type="checkbox" name="f['.e($k).'][]" value="'.e($ok).'" '.(in_array($ok,$arr,true)?'checked':'').'> '.e($ol).'</label>';
        echo '</div>'; break;
      case 'gps':
        echo '<div style="display:flex;gap:6px"><input class="form-control" name="f['.e($k).']" data-key="'.e($k).'" id="gps_'.e($k).'" value="'.e(is_array($val)?'':$val).'" placeholder="lat, long" readonly>';
        echo '<button type="button" class="btn small secondary" onclick="idemsGps(\''.e($k).'\')">📍 Capture</button></div>'; break;
      case 'photo': case 'file':
        if ($f['ftype']==='photo') {
          // Two ways in: take a photo (camera) OR upload from files — both are
          // auto-compressed (browser shrink + server re-encode) without visible
          // quality loss.
          echo '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">';
          echo '<label class="btn small secondary" style="cursor:pointer">📷 Take photo<input type="file" name="upl['.e($k).'][]" multiple accept="image/*" capture="environment" data-shrink="1" style="display:none"></label>';
          echo '<label class="btn small secondary" style="cursor:pointer">⬆ Upload photo(s)<input type="file" name="upl['.e($k).'][]" multiple accept="image/*" data-shrink="1" style="display:none"></label>';
          echo '</div>';
          echo '<small class="muted">Automatically compressed to a smaller size without losing clarity; tagged with time &amp; location.</small>';
        } else {
          echo '<input class="form-control" type="file" name="upl['.e($k).'][]" multiple>';
        }
        echo '<input type="hidden" name="gps['.e($k).']" id="gps_'.e($k).'">';
        if (!empty($filesByField[$k])) { echo '<div class="ev-thumbs" style="display:flex;flex-wrap:wrap;gap:10px;margin-top:8px">'; foreach ($filesByField[$k] as $fl) {
          if (strpos($fl['mime'],'image/')===0) {
            echo '<div style="width:120px"><a href="/report-file?id='.(int)$fl['id'].'" target="_blank"><img src="/report-file?id='.(int)$fl['id'].'" class="ev-th" style="width:120px;height:90px;object-fit:cover;border-radius:6px"></a>';
            echo '<input class="form-control" name="cap['.(int)$fl['id'].']" value="'.e($fl['caption'] ?? '').'" placeholder="Name / caption" style="font-size:12px;margin-top:3px;padding:3px 6px"></div>';
          } else echo '<a class="pill p-info" href="/report-file?id='.(int)$fl['id'].'" target="_blank">📎 '.e($fl['file_name']).'</a> ';
        } echo '</div><small class="muted">Type a name under each photo — it prints below the photo on the report. Saved when you save the body.</small>'; }
        if ($f['ftype']==='photo') {
          // Photography denied by the client / vendor → statement instead of photos.
          $den = ($data[$k.'__photo_denied'] ?? '')==='1';
          echo '<label class="chk" style="margin-top:8px;display:flex;align-items:center;gap:6px"><input type="checkbox" name="pdenied['.e($k).']" value="1" '.($den?'checked':'').'> Photography was <b>not permitted / denied</b> by the client or vendor</label>';
          echo '<input class="form-control" name="pdenied_note['.e($k).']" value="'.e($data[$k.'__photo_denied_note'] ?? '').'" placeholder="Optional note (who denied, reason)" style="font-size:12px;margin-top:4px">';
        }
        break;
      case 'signature':
        $sig = $filesByField[$k][0] ?? null;
        echo '<div class="sig-wrap"><canvas class="sig-pad" id="sig_'.e($k).'" width="380" height="120" data-key="'.e($k).'"></canvas>';
        echo '<input type="hidden" name="sig['.e($k).']" id="sigv_'.e($k).'">';
        echo '<div><button type="button" class="btn small secondary" onclick="idemsSigClear(\''.e($k).'\')">Clear</button> ';
        if ($sig) echo '<span class="muted">saved — sign again to replace</span>';
        echo '</div></div>'; break;
      case 'sigblock':
        // Per-role sign-off (Prepared / Reviewed / Approved). Name & designation
        // auto-fill from the workflow actors; the author can override, and set the
        // date. The on-file signature image is applied automatically at print.
        static $__sbSigs = null;
        if ($__sbSigs === null) $__sbSigs = function_exists('idems_report_signatures') ? idems_report_signatures($doc) : [];
        $rows = idems_sigblock_rows($f, $data, $__sbSigs);
        echo '<div class="sigblock" style="grid-column:1/-1;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">';
        foreach ($rows as $r) {
          $role = $r['role']; $b = 'sb['.e($k).']['.e($role).']';
          echo '<div style="border:1px solid var(--line,#ddd);border-radius:8px;padding:10px">';
          echo '<div style="font-weight:600;font-size:12px;margin-bottom:6px">'.e($role).($r['img']?' <span class="pill p-ok" style="font-size:9px;padding:0 4px" title="Signature on file — stamped automatically">signed</span>':'').'</div>';
          echo '<input class="form-control" style="margin-bottom:5px" name="'.$b.'[name]" placeholder="Name" value="'.e($r['name']).'">';
          echo '<input class="form-control" style="margin-bottom:5px" name="'.$b.'[desig]" placeholder="Designation" value="'.e($r['desig']).'">';
          echo '<input class="form-control" type="date" name="'.$b.'[date]" value="'.e($r['date']).'">';
          echo '</div>';
        }
        echo '</div>'; break;
      case 'table':
        // Each column carries its own data type — a dropdown, number, date or
        // free text — so a "Result" column can be a Accepted/Rejected/Hold pick.
        $cdefs = idems_table_col_defs($f); $rows = is_array($val)?$val:[];
        echo '<div class="rep-table" data-key="'.e($k).'"><table class="dt"><thead><tr>';
        foreach ($cdefs as $ck=>$d) echo '<th>'.e($d['label']).'</th>'; echo '<th></th></tr></thead><tbody>';
        $cellInput = function($ck,$d,$cur) use ($k,$doc){
            $name = 'tbl['.e($k).'][]['.e($ck).']';
            $lblAttr = ' data-collabel="'.e(strtolower((string)$d['label'])).'"';
            if ($d['type']==='select') {
                // A column's options can be a single token pointing at a live
                // source (call:po_items), an editable master (lookup:inspection_disposition),
                // or the equipment register (equip:instruments) — which also auto-fills
                // the calibration columns in the same row.
                $opts = $d['options']; $isEquip = false;
                if (count($opts)===1 && (strpos((string)$opts[0],'call:')===0 || strpos((string)$opts[0],'lookup:')===0 || strpos((string)$opts[0],'equip:')===0)) {
                    $isEquip = strpos((string)$opts[0],'equip:')===0;
                    $opts = idems_col_options($doc, (string)$opts[0]);
                }
                $h = '<select class="form-control'.($isEquip?' equip-pick':'').'" name="'.$name.'"'.$lblAttr.($isEquip?' data-equip="1"':'').'><option value=""></option>';
                foreach ($opts as $o) $h .= '<option value="'.e($o).'" '.((string)$cur===(string)$o?'selected':'').'>'.e($o).'</option>';
                return $h.'</select>';
            }
            if ($d['type']==='textarea') return '<textarea class="form-control" rows="1" name="'.$name.'"'.$lblAttr.'>'.e($cur).'</textarea>';
            $t = in_array($d['type'],['number','date'],true) ? $d['type'] : 'text';
            return '<input class="form-control" type="'.$t.'" name="'.$name.'"'.$lblAttr.' value="'.e($cur).'">';
        };
        $render_row = function($r) use ($cdefs,$cellInput){ echo '<tr>'; foreach ($cdefs as $ck=>$d) echo '<td>'.$cellInput($ck,$d,$r[$ck]??'').'</td>'; echo '<td><button type="button" class="btn small secondary" onclick="this.closest(\'tr\').remove()">✕</button></td></tr>'; };
        if ($rows) foreach ($rows as $r) $render_row((array)$r); else $render_row([]);
        echo '</tbody></table><button type="button" class="btn small secondary" onclick="idemsAddRow(this)">+ Add row</button>';
        echo '<template>'; $render_row([]); echo '</template></div>'; break;
      default:
        echo '<input class="form-control" name="f['.e($k).']" data-key="'.e($k).'" value="'.e(is_array($val)?'':$val).'">';
    }
    if ($f['help']) echo '<small class="muted">'.e($f['help']).'</small>';
    // ---- learned suggestions: what your team usually writes here (click to use) ----
    if (!empty($sugg[$k]) && in_array($f['ftype'], ['text','textarea'], true)) {
      echo '<div class="lrn-row"><span class="lrn-lab">Used before:</span>';
      foreach ($sugg[$k] as $s) {
        echo '<button type="button" class="lrn" data-target="'.e($k).'" data-text="'.e($s['text_value']).'" title="'.e($s['text_value']).'">'
           . e(mb_strimwidth($s['text_value'], 0, 46, '…')) . ' <span class="lrn-n">×'.(int)$s['uses'].'</span></button>';
      }
      echo '</div>';
    }
    echo '</div>';
  };
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/documents"><?= e(T_REG('report')) ?></a> › <a href="/document?id=<?= (int)$doc['id'] ?>"><?= e($doc['irn']) ?></a> › Fill</div>
<div class="master-head"><div><h1>Fill <?= e(Tl('report')) ?> <?= e($doc['irn']) ?></h1>
  <p class="sub" style="margin:2px 0 0"><?= e($doc['title'] ?: $doc['type_code']) ?></p></div>
  <a class="btn secondary" href="/document?id=<?= (int)$doc['id'] ?>">← Back</a>
</div>

<?php // Where the report stands + the approval trail + Submit — all on this screen,
      // so the inspector no longer bounces to another page to see or move it (T13).
  $st = (string)($doc['status'] ?? 'DRAFT');
  $stLabel = lk_options_or('report_status', IDEMS_STATUS)[$st] ?? $st;
  $canSubmit = in_array($st, ['DRAFT', 'REJECTED'], true) && (function_exists('idems_can_edit_doc') ? idems_can_edit_doc($doc) : true);
  $approvals = $approvals ?? []; $curStep = $curStep ?? null;
  $stepPill = fn($a) => ($a['status'] ?? '') === 'APPROVED' ? 'p-ok'
      : (($a['status'] ?? '') === 'REJECTED' ? 'p-bad'
      : (($curStep && (int)$curStep['id'] === (int)$a['id']) ? 'p-info' : 'p-mut')); ?>
<div class="panel" style="margin-bottom:14px;display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between">
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <span class="pill <?= idems_status_pill($st) ?>"><?= e($stLabel) ?></span>
    <?php if (!empty($doc['submitted_at'])): ?><span class="muted" style="font-size:12px">Submitted <?= e(substr((string)$doc['submitted_at'], 0, 10)) ?></span><?php endif; ?>
    <?php if ($curStep): $cn = trim(((string)($curStep['first_name'] ?? '')).' '.((string)($curStep['last_name'] ?? ''))) ?: ($curStep['username'] ?? ''); ?>
      <span class="muted" style="font-size:12px">· with <b><?= e($cn ?: 'the approver') ?></b> now</span><?php endif; ?>
  </div>
  <?php if ($canSubmit): ?>
    <form method="post" action="/document-submit?id=<?= (int)$doc['id'] ?>" onsubmit="return confirm('Submit this <?= e(Tl('report')) ?> for approval? Any empty fields are recorded as NA.');" style="margin:0">
      <button class="btn" type="submit">Submit for approval →</button>
    </form>
  <?php endif; ?>
</div>
<?php if ($approvals): ?>
<div class="panel" style="margin-bottom:14px">
  <div class="ctitle" style="margin-top:0"><h3>Approval trail</h3></div>
  <div style="display:flex;flex-wrap:wrap;gap:8px">
    <?php foreach ($approvals as $a):
      $nm = trim(((string)($a['first_name'] ?? '')).' '.((string)($a['last_name'] ?? ''))) ?: ((string)($a['username'] ?? '') ?: ('Level '.(int)($a['level'] ?? 0)));
      $when = trim((string)($a['acted_at'] ?? '')); ?>
      <div style="border:1px solid var(--line);border-radius:9px;padding:8px 12px;min-width:150px">
        <div class="muted" style="font-size:10.5px;text-transform:uppercase;letter-spacing:.04em">Level <?= (int)($a['level'] ?? 0) ?><?= $a['approver_role'] ? ' · '.e($a['approver_role']) : '' ?></div>
        <div style="font-weight:650;font-size:13.5px"><?= e($nm) ?></div>
        <span class="pill <?= $stepPill($a) ?>" style="font-size:10.5px"><?= e(ucfirst(strtolower((string)($a['status'] ?? 'pending')))) ?></span>
        <?php if ($when): ?><span class="muted" style="font-size:11px"> · <?= e(substr($when, 0, 10)) ?></span><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($auto): ?>
<div class="panel" style="border:1px solid var(--ok);background:color-mix(in srgb,var(--ok) 6%,transparent)">
  <b style="color:var(--ok)">✓ <?= count($auto) ?> field(s) filled in for you</b>
  <span class="muted">— taken from the <?= e(Tl('call')) ?>, the <?= e(Tl('job')) ?> and this report's details. They are marked <span class="pill p-ok" style="padding:0 5px;font-size:10px">auto</span>; change any of them if the site differs.</span>
</div>
<?php endif; ?>

<?php // §R1-D — the QAP(s) for this job, so the inspector can read them while
      // writing the report without leaving the screen. Read-only here. ?>
<?php $qaps = $qaps ?? []; if ($qaps): ?>
<div class="panel" style="border-left:3px solid var(--brand);background:var(--soft);margin-bottom:14px">
  <div class="ctitle" style="margin-top:0"><h3 style="margin:0">📋 QAP for this <?= e(Tl('job')) ?> <span class="muted">(<?= count($qaps) ?>)</span></h3></div>
  <p class="sub" style="margin:2px 0 8px">Open the Quality Assurance Plan while you write — the method, acceptance criteria and hold points to inspect against.</p>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php foreach ($qaps as $q): ?>
      <a class="btn small secondary" href="/job-qap?id=<?= (int)$q['id'] ?>" target="_blank" rel="noopener" title="<?= e($q['note'] ?: '') ?>">📄 <?= e($q['po_line'] ? $q['po_line'].' — ' : '') ?><?= e($q['file_name'] ?: 'QAP') ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!$fields): ?>
  <div class="panel"><p class="muted">No form has been designed for this report type yet. An administrator can design it under <a href="/report-builder?type=<?= (int)$doc['report_type_id'] ?>">Form builder</a>.</p></div>
<?php else: ?>
<form method="post" action="/document-fill?id=<?= (int)$doc['id'] ?>" enctype="multipart/form-data" id="fillform" data-autosave="report-<?= (int)$doc['id'] ?>" class="field-form">
  <?php foreach ($sections as $s): if (empty($bySection[(int)$s['id']])) continue; ?>
    <div class="panel" style="margin-bottom:14px"<?= $condAttr($s) ?> data-sectionwrap>
      <div class="ctitle" style="margin-top:0"><h3><?= e($s['title']) ?></h3></div>
      <?php if ($s['help']): ?><p class="muted" style="margin:0 0 8px"><?= e($s['help']) ?></p><?php endif; ?>
      <div class="form-grid"><?php foreach ($bySection[(int)$s['id']] as $f) $renderField($f); ?></div>
    </div>
  <?php endforeach; ?>
  <?php if (!empty($bySection[0])): ?>
    <div class="panel" style="margin-bottom:14px"><div class="form-grid"><?php foreach ($bySection[0] as $f) $renderField($f); ?></div></div>
  <?php endif; ?>
  <div style="margin-top:8px"><button class="btn" type="submit">Save report body</button>
    <?php // §R1 — see the report in your uploaded format at any point while writing
          // (save first; it fills your layout with what you've entered so far). ?>
    <?php if (function_exists('idems_pick_template') && idems_pick_template($doc)): ?>
      <a class="btn secondary" href="/document-docx?id=<?= (int)$doc['id'] ?>" target="_blank" title="Save first, then this opens the report in your uploaded format">📄 See in your format</a>
    <?php endif; ?>
    <a class="btn secondary" href="/document?id=<?= (int)$doc['id'] ?>">Cancel</a></div>
</form>
<?php endif; ?>

<style>
  .ev-thumbs{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
  .ev-th{width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid var(--line)}
  .sig-pad{border:1px solid var(--line);border-radius:8px;touch-action:none;background:#fff;max-width:100%}
  .rep-table table{width:100%}
  /* ---- learned suggestions ---- */
  .lrn-row{display:flex;flex-wrap:wrap;gap:4px;align-items:center;margin-top:5px}
  .lrn-lab{font-size:11px;color:var(--muted)}
  .lrn{font-size:11px;padding:3px 8px;border:1px solid var(--line);border-radius:12px;background:var(--soft);cursor:pointer;max-width:100%;text-align:left}
  .lrn:hover{border-color:var(--brand);color:var(--brand)}
  .lrn-n{color:var(--muted);font-size:10px}
  /* ---- field mode: large, touch-friendly controls on phones & tablets ---- */
  @media (max-width: 820px) {
    .field-form .form-grid{grid-template-columns:1fr}
    .field-form .form-control{font-size:16px;padding:11px 12px;min-height:46px}   /* 16px stops iOS zooming on focus */
    .field-form textarea.form-control{min-height:104px}
    .field-form label{font-size:14px}
    .field-form .chk{display:flex;align-items:center;gap:8px;padding:9px 0;font-size:15px}
    .field-form .chk input[type=checkbox],.field-form .chk input[type=radio]{width:22px;height:22px}
    .field-form .btn{min-height:44px;padding:10px 16px}
    .field-form .btn.small{min-height:38px}
    .field-form .rep-table{overflow-x:auto}
    .field-form .rep-table .form-control{min-width:120px}
    .field-form .ev-th{width:76px;height:76px}
    body{padding-bottom:44px}                                                     /* room for the connection bar */
  }
</style>
<script>
// Equipment register (active instruments + their latest calibration) — picking an
// instrument in a master-linked table auto-fills the serial / calibration columns.
window.__equipReg = <?= json_encode(function_exists('idems_equipment_active') ? idems_equipment_active() : (object)[], JSON_UNESCAPED_UNICODE) ?>;
document.addEventListener('change', function(ev){
  var sel = ev.target; if(!sel.matches || !sel.matches('select[data-equip]')) return;
  var reg = window.__equipReg || {}; var rec = reg[sel.value]; if(!rec) return;
  var tr = sel.closest('tr'); if(!tr) return;
  tr.querySelectorAll('[data-collabel]').forEach(function(cell){
    if(cell===sel) return;
    var L = cell.getAttribute('data-collabel')||''; var v = null;
    if(/traceab/.test(L)) v = rec.traceable;
    else if(/due/.test(L)) v = rec.cal_due;
    else if(/calibrat/.test(L) && /(on|date)/.test(L)) v = rec.cal_on;
    else if(/(serial|sr\.?\s*no|identif)/.test(L)) v = rec.serial;
    else if(/cert/.test(L)) v = rec.cert;
    else if(/(lab|agency|body)/.test(L)) v = rec.body;
    if(v!=null && v!=='' && (cell.value===''||cell.dataset.autofilled==='1')){ cell.value=v; cell.dataset.autofilled='1'; }
  });
});
function idemsGps(k){ if(!navigator.geolocation){alert('GPS not available');return;} navigator.geolocation.getCurrentPosition(function(p){ var v=p.coords.latitude.toFixed(6)+', '+p.coords.longitude.toFixed(6); document.getElementById('gps_'+k).value=v; },function(){alert('Could not get location');}); }
// Technical writing assistant: convert shorthand in this box to engineering language.
function idemsImprove(k){
  var ta=document.getElementById('ta_'+k); if(!ta||!ta.value.trim())return;
  var body=new URLSearchParams({text:ta.value, ajax:'1'});
  fetch('/writing-assistant',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()})
    .then(function(r){return r.json();}).then(function(d){ if(d&&d.text) ta.value=d.text; })
    .catch(function(){ alert('Could not reach the writing assistant.'); });
}
function idemsAddRow(btn){ var wrap=btn.closest('.rep-table'); var tpl=wrap.querySelector('template'); var tb=wrap.querySelector('tbody'); tb.insertAdjacentHTML('beforeend', tpl.innerHTML); }
// Learned suggestions: click to insert wording your team has used before.
document.querySelectorAll('.lrn').forEach(function(b){
  b.addEventListener('click', function(){
    var k=b.getAttribute('data-target'), t=b.getAttribute('data-text');
    var el=document.querySelector('[data-key="'+k+'"]'); if(!el) return;
    el.value = el.value && el.value.trim() ? (el.value.replace(/\s*$/,'') + ' ' + t) : t;
    el.dispatchEvent(new Event('input',{bubbles:true}));
    el.focus();
  });
});
// ---- Smart evidence: shrink big camera photos in the browser before upload, and
// auto-capture the location once a photo is chosen (saves data on site connections).
(function(){
  var MAXD = 1600, Q = 0.82;
  function shrink(file){
    return new Promise(function(res){
      if (!file.type || file.type.indexOf('image/') !== 0 || file.size < 400*1024) return res(file);
      var img = new Image(), url = URL.createObjectURL(file);
      img.onload = function(){
        var w = img.width, h = img.height, s = (w > MAXD || h > MAXD) ? MAXD/Math.max(w,h) : 1;
        if (s === 1 && file.size < 1200*1024) { URL.revokeObjectURL(url); return res(file); }
        var c = document.createElement('canvas'); c.width = Math.round(w*s); c.height = Math.round(h*s);
        var x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0,0,c.width,c.height); x.drawImage(img,0,0,c.width,c.height);
        c.toBlob(function(b){ URL.revokeObjectURL(url);
          if (!b || b.size >= file.size) return res(file);
          res(new File([b], file.name.replace(/\.(png|heic|heif|webp)$/i,'.jpg'), {type:'image/jpeg', lastModified: file.lastModified}));
        }, 'image/jpeg', Q);
      };
      img.onerror = function(){ URL.revokeObjectURL(url); res(file); };
      img.src = url;
    });
  }
  document.querySelectorAll('input[type=file][data-shrink]').forEach(function(inp){
    inp.addEventListener('change', function(){
      if (!inp.files || !inp.files.length) return;
      // auto-capture location alongside the photo
      var key = (inp.name.match(/upl\[(.+?)\]/)||[])[1];
      if (key && navigator.geolocation) {
        var g = document.getElementById('gps_'+key);
        if (g && !g.value) navigator.geolocation.getCurrentPosition(function(p){ g.value = p.coords.latitude.toFixed(6)+', '+p.coords.longitude.toFixed(6); }, function(){});
      }
      Promise.all(Array.prototype.map.call(inp.files, shrink)).then(function(list){
        var dt = new DataTransfer(); list.forEach(function(f){ dt.items.add(f); });
        try { inp.files = dt.files; } catch(e) {}
      });
    });
  });
})();
// signature pads
document.querySelectorAll('.sig-pad').forEach(function(c){
  var ctx=c.getContext('2d'), drawing=false, dirty=false; ctx.lineWidth=2; ctx.lineCap='round'; ctx.strokeStyle='#111';
  function pos(e){ var r=c.getBoundingClientRect(); var t=e.touches?e.touches[0]:e; return {x:(t.clientX-r.left)*(c.width/r.width), y:(t.clientY-r.top)*(c.height/r.height)}; }
  function start(e){ drawing=true; var p=pos(e); ctx.beginPath(); ctx.moveTo(p.x,p.y); e.preventDefault(); }
  function move(e){ if(!drawing)return; var p=pos(e); ctx.lineTo(p.x,p.y); ctx.stroke(); dirty=true; e.preventDefault(); }
  function end(){ drawing=false; if(dirty) document.getElementById('sigv_'+c.dataset.key).value=c.toDataURL('image/png'); }
  c.addEventListener('mousedown',start); c.addEventListener('mousemove',move); window.addEventListener('mouseup',end);
  c.addEventListener('touchstart',start); c.addEventListener('touchmove',move); c.addEventListener('touchend',end);
});
function idemsSigClear(k){ var c=document.getElementById('sig_'+k); c.getContext('2d').clearRect(0,0,c.width,c.height); document.getElementById('sigv_'+k).value=''; }
// conditional visibility + calculated fields
(function(){
  var form=document.getElementById('fillform'); if(!form) return;
  function valOf(key){ var el=form.querySelector('[data-key="'+key+'"]'); if(!el) { var chk=form.querySelector('[name="f['+key+']"]:checked'); return chk?chk.value:''; }
    if(el.type==='checkbox') return el.checked?'1':''; return el.value||''; }
  function evalConds(){
    form.querySelectorAll('[data-cond]').forEach(function(el){
      var f=el.getAttribute('data-cond'), op=el.getAttribute('data-cond-op'), cv=el.getAttribute('data-cond-val'), v=valOf(f), show=true;
      if(op==='eq') show=(v==cv); else if(op==='ne') show=(v!=cv); else if(op==='in') show=cv.split(',').map(function(s){return s.trim();}).indexOf(v)>=0; else if(op==='nonempty') show=!!v; else if(op==='empty') show=!v;
      el.style.display=show?'':'none';
    });
  }
  function evalCalcs(){
    form.querySelectorAll('[data-calc]').forEach(function(el){
      var expr=el.getAttribute('data-calc');
      var safe=expr.replace(/[a-zA-Z_][a-zA-Z0-9_]*/g,function(m){ var n=parseFloat(valOf(m)); return isNaN(n)?'0':n; });
      if(/^[0-9+\-*/().\s]*$/.test(safe)){ try{ var r=Function('return ('+safe+')')(); el.value=(isFinite(r)?Math.round(r*100)/100:''); }catch(e){} }
    });
  }
  form.addEventListener('input',function(){ evalConds(); evalCalcs(); });
  form.addEventListener('change',function(){ evalConds(); evalCalcs(); });
  evalConds(); evalCalcs();
})();
</script>
