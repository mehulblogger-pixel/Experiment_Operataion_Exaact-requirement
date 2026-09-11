<?php
// Form Designer — rename / reorder / hide / require the fields of a built-in form.
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/admin">Admin</a> › Form Designer</div>
<h1 style="margin:.2em 0">Form Designer</h1>
<p class="muted" style="margin-top:0">Rename a field, change its order, hide one you don’t use, or make it required — without touching any code. To add a field of a new type, use <a href="/custom-fields">Custom fields</a>.</p>

<?php if ($sel === ''): ?>
  <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:14px">
    <?php foreach ($forms as $k => $f): ?>
      <a class="card" href="/form-designer?form=<?= $e($k) ?>" style="display:block;min-width:220px;flex:1 1 220px;padding:16px;border:1px solid #e5e7eb;border-radius:12px;text-decoration:none;color:#1f2937;background:#fff">
        <div style="font-size:22px"><?= $e($f['icon'] ?? '📝') ?></div>
        <div style="font-weight:700;margin-top:6px"><?= $e($f['label'] ?? $k) ?></div>
        <div class="muted" style="font-size:13px;margin-top:2px"><?= (int) count($f['fields'] ?? []) ?> fields · <?= $e($f['help'] ?? 'Design this form') ?></div>
      </a>
    <?php endforeach; ?>
    <?php if (!$forms): ?><p class="muted">No designable forms for this plan yet.</p><?php endif; ?>
  </div>
<?php else:
  $form = $forms[$sel];
  $fields = $form['fields'] ?? [];
  // Show fields in the company's saved order.
  $order = function_exists('fd_order') ? fd_order($sel, array_keys($fields)) : array_keys($fields);
?>
  <p><a href="/form-designer">‹ All forms</a></p>
  <h2 style="margin:.2em 0"><?= $e($form['icon'] ?? '📝') ?> <?= $e($form['label'] ?? $sel) ?></h2>
  <form method="post" action="/form-designer-save" id="fdForm">
    <input type="hidden" name="form" value="<?= $e($sel) ?>">
    <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
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
    <div style="margin-top:14px;display:flex;gap:10px;align-items:center">
      <button class="btn" type="submit">Save form</button>
      <a class="btn ghost" href="/form-designer?form=<?= $e($sel) ?>">Cancel</a>
      <span class="muted" style="font-size:13px">Tip: use ▲ ▼ to reorder. Renaming here only changes the label — the data stays the same.</span>
    </div>
  </form>
  <script>
  (function(){
    var body = document.getElementById('fdRows');
    if (!body) return;
    body.addEventListener('click', function(ev){
      var up = ev.target.closest('.fd-up'), dn = ev.target.closest('.fd-down');
      if (!up && !dn) return;
      var row = ev.target.closest('tr.fd-row'); if (!row) return;
      if (up && row.previousElementSibling) row.parentNode.insertBefore(row, row.previousElementSibling);
      if (dn && row.nextElementSibling) row.parentNode.insertBefore(row.nextElementSibling, row);
    });
  })();
  </script>
<?php endif; ?>
