<?php
  // Connect K13 / backlog #2 — the ITI→MBA qualification & role taxonomy,
  // read-only. Sits beside the K0 industry taxonomy and generalises the pool
  // beyond inspection. Zero-Training: counts up top, everything else revealed on
  // tap (progressive disclosure), large tap targets, plain words. Changes no
  // existing screen or number.
  $summary = $summary ?? []; $families = $families ?? []; $levels = $levels ?? [];
  $trades = $trades ?? []; $certs = $certs ?? [];
  $canManage = $canManage ?? false; $kinds = $kinds ?? []; $bands = $bands ?? [];
  $chip = fn($t) => '<span class="chip">' . e($t) . '</span>';

  // Qualification levels grouped by band, in a sensible ladder order.
  $bandOrder = ['SCHOOL','ITI','APPRENTICE','VOCATIONAL','DIPLOMA','DEGREE','PG','DOCTORATE','PROFESSIONAL'];
  $byBand = [];
  foreach ($levels as $l) { $byBand[strtoupper((string)($l['band'] ?? ''))][] = $l; }
?>
<div class="crumbs"><a href="/">Home</a> › Qualification &amp; role taxonomy</div>
<div class="master-head">
  <div><h1>Qualification &amp; role taxonomy</h1>
    <p class="sub" style="margin:2px 0 0">One ladder for everyone in technical services — from <strong>ITI</strong> and
      apprentices to <strong>diploma</strong>, <strong>engineers</strong>, <strong>MBA</strong> and doctorate — mapped to job
      families, roles and professional certifications. Inspection is one vertical here, not the whole world.
      Anchored on India's NSQF. Read-only reference.
      <?php if (!empty($summary['version'])): ?>Version <?= e($summary['version']) ?>.<?php endif; ?></p></div>
</div>

<div class="kpi-row" style="margin-top:12px">
  <div class="kpi"><span class="kic">🧭</span><div class="k">Job families</div><div class="v"><?= (int)($summary['families'] ?? 0) ?></div></div>
  <div class="kpi"><span class="kic">👷</span><div class="k">Roles</div><div class="v"><?= (int)($summary['roles'] ?? 0) ?></div></div>
  <div class="kpi"><span class="kic">🎓</span><div class="k">Qualification levels</div><div class="v"><?= (int)($summary['levels'] ?? 0) ?></div></div>
  <div class="kpi"><span class="kic">🔧</span><div class="k">ITI trades</div><div class="v"><?= (int)($summary['iti_trades'] ?? 0) ?></div></div>
  <div class="kpi"><span class="kic">🎖️</span><div class="k">Certifications</div><div class="v"><?= (int)($summary['certifications'] ?? 0) ?></div></div>
</div>

<style>
  .cx-sec{margin-top:12px}
  .cx-sec > summary{cursor:pointer;font-weight:600;font-size:16px;padding:14px;background:var(--card,#fff);border:1px solid var(--line,#e5e7eb);border-radius:12px;list-style:none}
  .cx-sec > summary::-webkit-details-marker{display:none}
  .cx-sec[open] > summary{border-bottom-left-radius:0;border-bottom-right-radius:0}
  .cx-body{border:1px solid var(--line,#e5e7eb);border-top:0;border-radius:0 0 12px 12px;padding:12px}
  .chip{display:inline-block;margin:3px;padding:7px 12px;border-radius:999px;background:rgba(0,128,128,.08);border:1px solid rgba(0,128,128,.2);font-size:14px;line-height:1.3}
  .cx-row{padding:10px 4px;border-bottom:1px solid var(--line,#eee)}
  .cx-row:last-child{border-bottom:0}
  .cx-code{display:inline-block;min-width:80px;font-weight:600;color:var(--muted,#555)}
  .cx-detail{color:var(--muted,#666);font-size:13px}
  .cx-aka{color:var(--muted,#888);font-size:13px}
  .cx-band{display:inline-block;padding:2px 9px;border-radius:999px;background:rgba(201,162,39,.12);border:1px solid rgba(201,162,39,.35);font-size:12px;font-weight:600;color:#8a6d12}
  .cx-nsqf{display:inline-block;min-width:58px;font-size:12px;color:var(--muted,#777);font-weight:600}
  .cx-role{padding:8px 4px 8px 14px;border-bottom:1px dashed var(--line,#eee)}
  .cx-role:last-child{border-bottom:0}
  .cx-min{display:inline-block;padding:1px 8px;border-radius:6px;background:rgba(0,128,128,.08);font-size:12px;color:#0f7d7d;font-weight:600}
  .ladder-band{margin:8px 0;padding:10px 12px;border:1px solid var(--line,#e5e7eb);border-radius:10px}
  .ladder-band h4{margin:0 0 6px;font-size:14px}
</style>

<!-- The qualification ladder — the spine of the whole taxonomy -->
<details class="cx-sec" open>
  <summary>🎓 The qualification ladder — school → ITI → diploma → degree → MBA → doctorate (<?= count($levels) ?>)</summary>
  <div class="cx-body">
    <?php foreach ($bandOrder as $band): if (empty($byBand[$band])) continue; ?>
      <div class="ladder-band">
        <h4><span class="cx-band"><?= e(connect_qtx_band_label($band)) ?></span></h4>
        <?php foreach ($byBand[$band] as $l): ?>
          <div class="cx-row"><span class="cx-nsqf">NSQF <?= (int)$l['nsqf_level'] ?></span>
            <strong><?= e($l['name']) ?></strong>
            <?php if (!empty($l['detail'])): ?><div class="cx-detail"><?= e($l['detail']) ?></div><?php endif; ?></div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
</details>

<!-- Job families → roles -->
<details class="cx-sec">
  <summary>🧭 Job families &amp; roles (<?= count($families) ?> families)</summary>
  <div class="cx-body">
    <?php foreach ($families as $f): $roles = connect_qtx_roles_for_family($f['code'] ?? ''); ?>
      <div class="cx-row">
        <span class="cx-code"><?= e($f['code']) ?></span> <strong><?= e($f['name']) ?></strong>
        <?php if ((int)($f['nsqf_min'] ?? 0) > 0): ?><span class="cx-nsqf"> · NSQF <?= (int)$f['nsqf_min'] ?>–<?= (int)$f['nsqf_max'] ?></span><?php endif; ?>
        <?php if (!empty($f['detail'])): ?><div class="cx-detail"><?= e($f['detail']) ?></div><?php endif; ?>
        <?php foreach ($roles as $r): ?>
          <div class="cx-role">
            <strong><?= e($r['name']) ?></strong>
            <?php if (!empty($r['min_qual_band'])): ?><span class="cx-min"><?= e(connect_qtx_band_label($r['min_qual_band'])) ?>+</span><?php endif; ?>
            <?php if (!empty($r['aka'])): ?><div class="cx-aka"><?= e($r['aka']) ?></div><?php endif; ?>
            <?php if (!empty($r['typical_certs'])): ?><div class="cx-detail">Typical: <?= e($r['typical_certs']) ?></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
</details>

<!-- ITI trades — the blue-collar end, made concrete -->
<details class="cx-sec">
  <summary>🔧 ITI trades (<?= count($trades) ?>)</summary>
  <div class="cx-body">
    <?php
      $byCat = [];
      foreach ($trades as $t) { $byCat[(string)($t['category'] ?? 'Other')][] = $t; }
      foreach ($byCat as $cat => $rows): ?>
        <div class="cx-row"><strong><?= e($cat) ?></strong>
          <div><?php foreach ($rows as $t) echo $chip($t['name'] . (!empty($t['duration']) ? ' · ' . $t['duration'] : '')); ?></div>
        </div>
    <?php endforeach; ?>
  </div>
</details>

<!-- Professional certifications — the full spectrum, not inspection-only -->
<details class="cx-sec">
  <summary>🎖️ Professional certifications (<?= count($certs) ?>)</summary>
  <div class="cx-body">
    <?php
      $byDomain = [];
      foreach ($certs as $c) { $byDomain[(string)($c['domain'] ?? 'Other')][] = $c; }
      foreach ($byDomain as $domain => $rows): ?>
        <div class="cx-row"><strong><?= e($domain) ?></strong>
          <?php foreach ($rows as $c): ?>
            <div class="cx-role"><strong><?= e($c['name']) ?></strong>
              <?php if (!empty($c['body'])): ?><span class="cx-aka"> — <?= e($c['body']) ?></span><?php endif; ?></div>
          <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
  </div>
</details>

<?php if ($canManage): ?>
<!-- ====================================================================== -->
<!-- CONFIGURE — the taxonomy is runtime data, not hard-coded. Admins add,   -->
<!-- edit, switch off or delete every family, role, level, trade and cert.   -->
<!-- ====================================================================== -->
<style>
  .qtx-cfg{margin-top:22px}
  .qtx-cfg > summary{cursor:pointer;font-weight:700;font-size:17px;padding:15px;background:linear-gradient(0deg,rgba(15,125,125,.06),rgba(15,125,125,.06)),var(--card,#fff);border:1px solid rgba(15,125,125,.3);border-radius:12px;list-style:none}
  .qtx-cfg > summary::-webkit-details-marker{display:none}
  .qtx-cfg[open] > summary{border-bottom-left-radius:0;border-bottom-right-radius:0}
  .qtx-cfg-body{border:1px solid rgba(15,125,125,.3);border-top:0;border-radius:0 0 12px 12px;padding:14px}
  .qtx-tabs{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px}
  .qtx-tab{padding:8px 14px;border-radius:999px;border:1px solid var(--line,#e5e7eb);background:var(--card,#fff);cursor:pointer;font-weight:600;font-size:14px;color:var(--muted,#555)}
  .qtx-tab.active{background:#0f7d7d;border-color:#0f7d7d;color:#fff}
  .qtx-pane{display:none}
  .qtx-pane.active{display:block}
  .qtx-tbl{width:100%;border-collapse:collapse;font-size:14px}
  .qtx-tbl th,.qtx-tbl td{text-align:left;padding:8px 8px;border-bottom:1px solid var(--line,#eee);vertical-align:top}
  .qtx-tbl th{font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:var(--muted,#777)}
  .qtx-off{opacity:.5}
  .qtx-badge{display:inline-block;padding:1px 7px;border-radius:6px;font-size:11px;font-weight:700}
  .qtx-badge.sys{background:rgba(201,162,39,.14);color:#8a6d12}
  .qtx-badge.off{background:rgba(220,38,38,.12);color:#b91c1c}
  .qtx-inline{display:inline}
  .qtx-btn{padding:5px 10px;border-radius:8px;border:1px solid var(--line,#ddd);background:var(--card,#fff);cursor:pointer;font-size:13px;font-weight:600}
  .qtx-btn.danger{color:#b91c1c;border-color:rgba(185,28,28,.3)}
  .qtx-btn.primary{background:#0f7d7d;border-color:#0f7d7d;color:#fff}
  .qtx-form{margin:10px 0;padding:12px;border:1px dashed rgba(15,125,125,.4);border-radius:10px;background:rgba(15,125,125,.03)}
  .qtx-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px}
  .qtx-grid label{display:block;font-size:12px;font-weight:600;color:var(--muted,#666);margin-bottom:3px}
  .qtx-grid input,.qtx-grid select{width:100%;padding:8px;border:1px solid var(--line,#ddd);border-radius:8px;font-size:14px;box-sizing:border-box}
  .qtx-code{font-family:ui-monospace,Menlo,monospace;font-size:12px;color:var(--muted,#555)}
</style>
<?php
  // A single input for one editable field.
  $qtxInput = function ($col, $f, $val = '') use ($families, $bands) {
      $req = !empty($f['required']) ? 'required' : '';
      $lbl = e($f['label'] ?? $col);
      echo '<div><label>' . $lbl . (!empty($f['required']) ? ' *' : '') . '</label>';
      if (($f['type'] ?? '') === 'select' && ($f['select'] ?? '') === 'family') {
          echo '<select name="' . e($col) . '" ' . $req . '><option value="">—</option>';
          foreach ($families as $fam) echo '<option value="' . e($fam['code']) . '"' . (($fam['code'] ?? '') === $val ? ' selected' : '') . '>' . e($fam['name']) . '</option>';
          echo '</select>';
      } elseif (($f['type'] ?? '') === 'band') {
          echo '<select name="' . e($col) . '" ' . $req . '><option value="">—</option>';
          foreach ($bands as $b) echo '<option value="' . e($b) . '"' . ($b === $val ? ' selected' : '') . '>' . e(connect_qtx_band_label($b)) . '</option>';
          echo '</select>';
      } elseif (($f['type'] ?? '') === 'int') {
          echo '<input type="number" name="' . e($col) . '" value="' . e($val) . '" ' . $req . '>';
      } else {
          echo '<input type="text" name="' . e($col) . '" value="' . e($val) . '" ' . $req . '>';
      }
      echo '</div>';
  };
  // The add/edit form for a kind. $row = existing row (edit) or null (add).
  $qtxForm = function ($kind, $k, $row = null) use ($qtxInput) {
      $isEdit = is_array($row);
      echo '<form method="post" action="/connect-qualifications" class="qtx-form">';
      echo '<input type="hidden" name="action" value="save"><input type="hidden" name="kind" value="' . e($kind) . '">';
      if ($isEdit) echo '<input type="hidden" name="id" value="' . (int)$row['id'] . '">';
      echo '<div class="qtx-grid">';
      foreach ($k['fields'] as $col => $f) {
          $val = $isEdit ? (string)($row[$col] ?? '') : '';
          // Code is the stable key — shown read-only on edit to protect references.
          if (($f['type'] ?? '') === 'code' && $isEdit) {
              echo '<div><label>' . e($f['label']) . '</label><input type="text" value="' . e($val) . '" disabled>'
                 . '<input type="hidden" name="' . e($col) . '" value="' . e($val) . '"></div>';
              continue;
          }
          $qtxInput($col, $f, $val);
      }
      echo '</div><div style="margin-top:10px"><button class="qtx-btn primary" type="submit">'
         . ($isEdit ? 'Save changes' : 'Add ' . e(strtolower($k['label']))) . '</button></div>';
      echo '</form>';
  };
  // One row's on/off + delete controls.
  $qtxRowCtl = function ($kind, $row) {
      $active = (int)($row['is_active'] ?? 1) === 1;
      echo '<form method="post" action="/connect-qualifications" class="qtx-inline">'
         . '<input type="hidden" name="kind" value="' . e($kind) . '"><input type="hidden" name="id" value="' . (int)$row['id'] . '">'
         . '<button class="qtx-btn" name="action" value="toggle" type="submit">' . ($active ? 'Switch off' : 'Switch on') . '</button></form> ';
      echo '<form method="post" action="/connect-qualifications" class="qtx-inline" onsubmit="return confirm(\'Delete this item? Switching it off is usually safer.\')">'
         . '<input type="hidden" name="kind" value="' . e($kind) . '"><input type="hidden" name="id" value="' . (int)$row['id'] . '">'
         . '<button class="qtx-btn danger" name="action" value="delete" type="submit">Delete</button></form>';
  };
  // Every row of a kind, including switched-off ones, for the editor table.
  $kindOrder = ['family' => 'Job families', 'role' => 'Roles', 'level' => 'Qualification levels', 'trade' => 'ITI trades', 'cert' => 'Certifications'];
?>
<details class="qtx-cfg" id="configure">
  <summary>⚙️ Configure taxonomy — add, edit, switch off or delete (admin)</summary>
  <div class="qtx-cfg-body">
    <p class="cx-detail" style="margin:0 0 12px">Nothing here is hard-coded. Every family, role, qualification level, ITI trade
      and certification below is data you control. Built-in items <span class="qtx-badge sys">built-in</span> can be edited or switched off;
      only the Super Admin can permanently delete them. Switching an item off hides it from the marketplace without breaking anything that already uses it.</p>

    <div class="qtx-tabs">
      <?php $first = true; foreach ($kindOrder as $kk => $klabel): ?>
        <button type="button" class="qtx-tab<?= $first ? ' active' : '' ?>" data-pane="pane-<?= e($kk) ?>"><?= e($klabel) ?></button>
      <?php $first = false; endforeach; ?>
    </div>

    <?php $first = true; foreach ($kindOrder as $kk => $klabel):
      $k = $kinds[$kk] ?? null; if (!$k) continue;
      $rows = connect_qtx_rows($k['table'], 'sort_order, id', false); ?>
      <div class="qtx-pane<?= $first ? ' active' : '' ?>" id="pane-<?= e($kk) ?>">
        <details style="margin-bottom:8px"><summary class="qtx-btn primary" style="display:inline-block;list-style:none">＋ Add <?= e(strtolower($k['label'])) ?></summary>
          <?php $qtxForm($kk, $k); ?>
        </details>
        <div style="overflow-x:auto">
        <table class="qtx-tbl">
          <thead><tr>
            <th>Code</th>
            <?php foreach ($k['fields'] as $col => $f) if ($col !== 'code') echo '<th>' . e($f['label']) . '</th>'; ?>
            <th>Status</th><th>Actions</th>
          </tr></thead>
          <tbody>
            <?php foreach ($rows as $row): $active = (int)($row['is_active'] ?? 1) === 1; ?>
              <tr class="<?= $active ? '' : 'qtx-off' ?>">
                <td class="qtx-code"><?= e($row['code'] ?? '') ?></td>
                <?php foreach ($k['fields'] as $col => $f): if ($col === 'code') continue; ?>
                  <td><?php
                    $v = (string)($row[$col] ?? '');
                    if (($f['type'] ?? '') === 'band' && $v !== '') echo e(connect_qtx_band_label($v));
                    elseif (($f['type'] ?? '') === 'select' && ($f['select'] ?? '') === 'family' && $v !== '') echo e($v);
                    else echo e($v);
                  ?></td>
                <?php endforeach; ?>
                <td><?php if (!$active) echo '<span class="qtx-badge off">off</span> '; if ((int)($row['is_system'] ?? 0) === 1) echo '<span class="qtx-badge sys">built-in</span>'; ?></td>
                <td style="white-space:nowrap">
                  <details class="qtx-inline"><summary class="qtx-btn" style="display:inline-block;list-style:none">Edit</summary>
                    <?php $qtxForm($kk, $k, $row); ?>
                  </details>
                  <?php $qtxRowCtl($kk, $row); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>
    <?php $first = false; endforeach; ?>
  </div>
</details>
<script>
  (function () {
    var cfg = document.getElementById('configure'); if (!cfg) return;
    cfg.querySelectorAll('.qtx-tab').forEach(function (t) {
      t.addEventListener('click', function () {
        cfg.querySelectorAll('.qtx-tab').forEach(function (x) { x.classList.remove('active'); });
        cfg.querySelectorAll('.qtx-pane').forEach(function (x) { x.classList.remove('active'); });
        t.classList.add('active');
        var p = document.getElementById(t.getAttribute('data-pane')); if (p) p.classList.add('active');
      });
    });
  })();
</script>
<?php endif; ?>
