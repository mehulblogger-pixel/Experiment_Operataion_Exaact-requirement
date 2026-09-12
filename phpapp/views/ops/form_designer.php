<?php
// Form Designer — the single cockpit for a form. Standard (built-in) fields are
// tuned by the overlay (rename / reorder / hide / require); the admin can also
// ADD their own fields, DELETE ones they added, and build a dropdown WITH its
// options — all here, no second screen.
$e   = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$csrf = fn() => function_exists('csrf_field') ? csrf_field() : '';
$types = $types ?? (function_exists('fd_field_types') ? fd_field_types() : []);
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/admin">Admin</a> › Form Designer</div>
<h1 style="margin:.2em 0">Form Designer</h1>
<p class="muted" style="margin-top:0">Build your forms end to end — rename a field, change its order, hide one you don’t use, make it required, <strong>add a new field</strong>, <strong>delete a field you added</strong>, or <strong>create a dropdown with its own options</strong>. No coding, and it never changes data already captured.</p>

<?php if ($sel === ''): ?>
  <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:14px">
    <?php foreach ($forms as $k => $f): ?>
      <a class="card" href="/form-designer?form=<?= $e($k) ?>" style="display:block;min-width:220px;flex:1 1 220px;padding:16px;border:1px solid #e5e7eb;border-radius:12px;text-decoration:none;color:#1f2937;background:#fff">
        <div style="font-size:22px"><?= $e($f['icon'] ?? '📝') ?></div>
        <div style="font-weight:700;margin-top:6px"><?= $e($f['label'] ?? $k) ?></div>
        <div class="muted" style="font-size:13px;margin-top:2px"><?= (int) count($f['fields'] ?? []) ?> standard fields · <?= $e($f['help'] ?? 'Design this form') ?></div>
      </a>
    <?php endforeach; ?>
    <?php if (!$forms): ?><p class="muted">No designable forms for this plan yet.</p><?php endif; ?>
  </div>
<?php else:
  $form   = $forms[$sel];
  $fields = $form['fields'] ?? [];
  $order  = function_exists('fd_order') ? fd_order($sel, array_keys($fields)) : array_keys($fields);
  $custom = $custom ?? [];
  $lists  = $lists ?? [];
  $editId = (int) ($editId ?? 0);
  // A friendly type word for a stored custom field.
  $typeWord = function ($t) use ($types) { return $types[$t] ?? ucfirst((string) $t); };
?>
  <p><a href="/form-designer">‹ All forms</a></p>
  <h2 style="margin:.2em 0"><?= $e($form['icon'] ?? '📝') ?> <?= $e($form['label'] ?? $sel) ?></h2>

  <!-- ============ CARD 1 — STANDARD (built-in) FIELDS ============ -->
  <div class="card" style="padding:16px;border:1px solid #e5e7eb;border-radius:12px;background:#fff;margin-top:12px">
    <h3 style="margin:.1em 0 .1em">Standard fields</h3>
    <p class="muted" style="margin-top:0;font-size:13px">These come built in. Rename them, drag their order with ▲ ▼, make them required, or hide the ones you don’t use. (Essential fields can’t be hidden — the form needs them to save.)</p>
    <form method="post" action="/form-designer-save" id="fdForm">
      <input type="hidden" name="form" value="<?= $e($sel) ?>">
      <?= $csrf() ?>
      <div style="overflow-x:auto">
      <table class="tbl" style="width:100%;border-collapse:collapse;min-width:560px">
        <thead><tr style="text-align:left;border-bottom:2px solid #e5e7eb">
          <th style="padding:8px 6px">Order</th>
          <th style="padding:8px 6px">Field label (what staff see)</th>
          <th style="padding:8px 6px">Type</th>
          <th style="padding:8px 6px">Required</th>
          <th style="padding:8px 6px">Hide</th>
        </tr></thead>
        <tbody id="fdRows">
          <?php foreach ($order as $key): $f = $fields[$key]; $o = $ov[$key] ?? [];
            $lbl = (string) ($o['label'] ?? '') !== '' ? $o['label'] : ($f['label'] ?? $key);
            $req = (string) ($o['req'] ?? '');
            $locked = !empty($f['locked']); ?>
          <tr class="fd-row" style="border-bottom:1px solid #f0f0f0">
            <td style="padding:6px;white-space:nowrap">
              <button type="button" class="btn xs ghost fd-up" title="Move up">▲</button>
              <button type="button" class="btn xs ghost fd-down" title="Move down">▼</button>
              <input type="hidden" name="field_key[]" value="<?= $e($key) ?>">
            </td>
            <td style="padding:6px">
              <input class="form-control" name="label[]" value="<?= $e($lbl) ?>" style="min-width:180px">
              <?php if (!empty($f['section'])): ?><div class="muted" style="font-size:12px">Section: <?= $e($f['section']) ?></div><?php endif; ?>
            </td>
            <td style="padding:6px;white-space:nowrap"><span class="pill"><?= $e($f['type'] ?? 'text') ?></span></td>
            <td style="padding:6px">
              <?php if ($locked): ?>
                <span class="muted" style="font-size:12px">Always required</span>
              <?php else: ?>
                <select name="req[<?= $e($key) ?>]" class="form-control" style="min-width:120px">
                  <option value=""    <?= $req===''    ?'selected':'' ?>>Default</option>
                  <option value="yes" <?= $req==='yes' ?'selected':'' ?>>Required</option>
                  <option value="no"  <?= $req==='no'  ?'selected':'' ?>>Optional</option>
                </select>
              <?php endif; ?>
            </td>
            <td style="padding:6px;text-align:center">
              <?php if ($locked): ?>
                <span class="muted" style="font-size:12px" title="This field is essential and cannot be hidden">—</span>
              <?php else: ?>
                <input type="checkbox" name="hidden[<?= $e($key) ?>]" value="1" <?= !empty($o['hidden'])?'checked':'' ?>>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <div style="margin-top:14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <button class="btn" type="submit">Save standard fields</button>
        <span class="muted" style="font-size:13px">Tip: use ▲ ▼ to reorder. Renaming only changes the label — the data stays the same.</span>
      </div>
    </form>
  </div>

  <!-- ============ CARD 2 — FIELDS YOU ADDED ============ -->
  <div class="card" style="padding:16px;border:1px solid #e5e7eb;border-radius:12px;background:#fff;margin-top:14px">
    <h3 style="margin:.1em 0 .1em">Fields you added</h3>
    <?php if (!$custom): ?>
      <p class="muted" style="margin:.2em 0 0;font-size:13px">None yet. Use “Add a new field” below to create your own — a text box, a number, a date, or a dropdown with its own options.</p>
    <?php else: ?>
      <p class="muted" style="margin-top:0;font-size:13px">Your own fields. Rename them, mark them required, add or remove dropdown options, or delete one you no longer need.</p>
      <div style="display:flex;flex-direction:column;gap:12px">
        <?php foreach ($custom as $cf):
          $cid = (int) $cf['id'];
          $isDrop = in_array($cf['field_type'], ['select', 'dependent'], true);
          $listId = (int) ($cf['lookup_type_id'] ?? 0);
          $vals   = ($isDrop && function_exists('fd_list_values')) ? fd_list_values($listId) : []; ?>
          <div style="border:1px solid #eef0f3;border-radius:10px;padding:12px;background:#fbfcfe">
            <div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap">
              <form method="post" action="/form-designer-field-edit" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;flex:1 1 320px;margin:0">
                <input type="hidden" name="form" value="<?= $e($sel) ?>">
                <input type="hidden" name="field_id" value="<?= $cid ?>">
                <?= $csrf() ?>
                <input class="form-control" name="ef_label" value="<?= $e($cf['label']) ?>" style="min-width:180px;flex:1 1 180px">
                <span class="pill" title="Field type"><?= $e($typeWord($cf['field_type'])) ?></span>
                <label style="font-size:13px;display:flex;align-items:center;gap:5px;white-space:nowrap"><input type="checkbox" name="ef_required" value="1" <?= !empty($cf['required'])?'checked':'' ?>> Required</label>
                <button class="btn xs" type="submit">Save</button>
              </form>
              <form method="post" action="/form-designer-field-del" onsubmit="return confirm('Delete the field “<?= $e(addslashes($cf['label'])) ?>”? Anything captured in it will be removed.');" style="margin:0">
                <input type="hidden" name="form" value="<?= $e($sel) ?>">
                <input type="hidden" name="field_id" value="<?= $cid ?>">
                <?= $csrf() ?>
                <button class="btn xs danger" type="submit" title="Delete this field">Delete</button>
              </form>
            </div>
            <?php if ($isDrop): ?>
              <div style="margin-top:10px">
                <div class="muted" style="font-size:12px;margin-bottom:4px">Dropdown options:</div>
                <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
                  <?php if (!$vals): ?><span class="muted" style="font-size:12px">No options yet — add one below.</span><?php endif; ?>
                  <?php foreach ($vals as $v): ?>
                    <span style="display:inline-flex;align-items:center;gap:6px;background:#eef2ff;border:1px solid #dfe4ff;border-radius:999px;padding:2px 6px 2px 10px;font-size:13px">
                      <?= $e($v['label']) ?>
                      <form method="post" action="/form-designer-option-del" style="margin:0" title="Remove this option">
                        <input type="hidden" name="form" value="<?= $e($sel) ?>">
                        <input type="hidden" name="list_id" value="<?= $listId ?>">
                        <input type="hidden" name="value_id" value="<?= (int) $v['id'] ?>">
                        <?= $csrf() ?>
                        <button type="submit" style="border:0;background:none;cursor:pointer;color:#6b7280;font-size:14px;line-height:1;padding:0">✕</button>
                      </form>
                    </span>
                  <?php endforeach; ?>
                </div>
                <form method="post" action="/form-designer-option-add" style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap">
                  <input type="hidden" name="form" value="<?= $e($sel) ?>">
                  <input type="hidden" name="list_id" value="<?= $listId ?>">
                  <?= $csrf() ?>
                  <input class="form-control" name="opt_label" placeholder="Add an option…" style="min-width:160px;flex:1 1 160px">
                  <button class="btn xs ghost" type="submit">+ Add option</button>
                </form>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- ============ CARD 3 — ADD A NEW FIELD ============ -->
  <div class="card" style="padding:16px;border:1px solid #dbeafe;border-radius:12px;background:#f8fbff;margin-top:14px">
    <h3 style="margin:.1em 0 .1em">➕ Add a new field</h3>
    <p class="muted" style="margin-top:0;font-size:13px">Give it a name, pick the type, and it appears on this form straight away.</p>
    <form method="post" action="/form-designer-field-add" id="fdAdd">
      <input type="hidden" name="form" value="<?= $e($sel) ?>">
      <?= $csrf() ?>
      <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <div style="flex:1 1 220px">
          <label style="font-size:13px;font-weight:600;display:block;margin-bottom:4px">Field name</label>
          <input class="form-control" name="nf_label" placeholder="e.g. Notice period" style="width:100%" required>
        </div>
        <div style="flex:1 1 200px">
          <label style="font-size:13px;font-weight:600;display:block;margin-bottom:4px">Type</label>
          <select class="form-control" name="nf_type" id="nf_type" style="width:100%">
            <?php foreach ($types as $tk => $tl): ?><option value="<?= $e($tk) ?>"><?= $e($tl) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div style="flex:0 0 auto">
          <label style="font-size:13px;display:flex;align-items:center;gap:6px;margin-bottom:8px"><input type="checkbox" name="nf_required" value="1"> Required</label>
        </div>
      </div>

      <!-- Dropdown builder: only shown when the type is a dropdown -->
      <div id="nf_dropdown" style="display:none;margin-top:12px;border-top:1px dashed #cfe0f5;padding-top:12px">
        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:8px">
          <label style="font-size:13px;display:flex;align-items:center;gap:6px"><input type="radio" name="nf_list_mode" value="new" checked class="nf-mode"> Create a new list of options</label>
          <label style="font-size:13px;display:flex;align-items:center;gap:6px"><input type="radio" name="nf_list_mode" value="existing" class="nf-mode"> Use a list I already made</label>
        </div>
        <div id="nf_new_list">
          <label style="font-size:13px;font-weight:600;display:block;margin-bottom:4px">Options — one per line</label>
          <textarea class="form-control" name="nf_options" rows="4" placeholder="Immediate&#10;15 days&#10;30 days&#10;60 days" style="width:100%"></textarea>
        </div>
        <div id="nf_existing_list" style="display:none">
          <label style="font-size:13px;font-weight:600;display:block;margin-bottom:4px">Pick a list</label>
          <select class="form-control" name="nf_list_id" style="min-width:220px">
            <option value="">— choose a list —</option>
            <?php foreach ($lists as $lt): ?><option value="<?= (int) $lt['id'] ?>"><?= $e($lt['label']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>

      <div style="margin-top:14px">
        <button class="btn" type="submit">Add field</button>
      </div>
    </form>
  </div>

  <script>
  (function(){
    // Reorder standard fields with ▲ ▼.
    var body = document.getElementById('fdRows');
    if (body) body.addEventListener('click', function(ev){
      var up = ev.target.closest('.fd-up'), dn = ev.target.closest('.fd-down');
      if (!up && !dn) return;
      var row = ev.target.closest('tr.fd-row'); if (!row) return;
      if (up && row.previousElementSibling) row.parentNode.insertBefore(row, row.previousElementSibling);
      if (dn && row.nextElementSibling) row.parentNode.insertBefore(row.nextElementSibling, row);
    });
    // Add-field: reveal the dropdown builder only for a dropdown, and toggle
    // between "new list" and "existing list".
    var typeSel = document.getElementById('nf_type');
    var drop = document.getElementById('nf_dropdown');
    var newL = document.getElementById('nf_new_list');
    var oldL = document.getElementById('nf_existing_list');
    function sync(){
      if (!typeSel || !drop) return;
      drop.style.display = (typeSel.value === 'select') ? '' : 'none';
      var mode = (document.querySelector('.nf-mode:checked') || {}).value || 'new';
      if (newL) newL.style.display = (mode === 'new') ? '' : 'none';
      if (oldL) oldL.style.display = (mode === 'existing') ? '' : 'none';
    }
    if (typeSel) typeSel.addEventListener('change', sync);
    Array.prototype.forEach.call(document.querySelectorAll('.nf-mode'), function(r){ r.addEventListener('change', sync); });
    sync();
  })();
  </script>
<?php endif; ?>
