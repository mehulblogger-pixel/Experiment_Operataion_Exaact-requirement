<?php
// Department hub. Data: $hub = ['depts'=>[...], 'general'=>[...]], $deptNames.
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$hub = $hub ?? ['depts' => [], 'general' => []];
$deptNames = $deptNames ?? [];
// A small reusable "move this designation to department X" inline form.
$deptPicker = function ($valueId, $current) use ($e, $deptNames) {
    $h = '<form method="post" style="display:inline">'
       . (function_exists('csrf_field') ? csrf_field() : '')
       . '<input type="hidden" name="do" value="assign"><input type="hidden" name="value_id" value="' . (int) $valueId . '">'
       . '<select name="department" onchange="this.form.submit()" class="form-control" style="width:auto;display:inline-block;padding:2px 6px;font-size:11.5px">';
    $h .= '<option value="">— General —</option>';
    foreach ($deptNames as $d) $h .= '<option value="' . $e($d) . '"' . (strcasecmp($d, (string) $current) === 0 ? ' selected' : '') . '>' . $e($d) . '</option>';
    return $h . '</select></form>';
};
?>
<div class="crumbs"><a href="/">Home</a> › Departments</div>
<div class="master-head">
  <div><h1>Departments</h1>
    <p class="sub" style="margin:2px 0 0">Everything organised by department — its designations, positions, headcount and people. File each designation under a department for department-wise pickers, a clear org chart and approval routing.</p></div>
  <div class="row-actions">
    <a class="btn" href="/departments?tab=manage">🏛️ Department list</a>
    <?php if (!empty($pendingCount)): ?><a class="btn secondary" href="/departments?tab=review">❓ <?= (int) $pendingCount ?> word<?= (int) $pendingCount === 1 ? '' : 's' ?> to confirm</a><?php endif; ?>
    <a class="btn secondary" href="/positions-org">🗂️ Org chart</a> <a class="btn secondary" href="/positions-import">⬆ Import organogram</a></div>
</div>

<?php if (!$hub['depts'] && !$hub['general']): ?>
<div class="panel"><p class="muted">No departments yet. <a href="/departments?tab=manage">Add your first department</a>, or import an organogram.</p></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:14px;align-items:start">
  <?php foreach ($hub['depts'] as $d): ?>
  <div class="panel" style="margin:0">
    <div style="display:flex;justify-content:space-between;align-items:baseline;gap:10px;flex-wrap:wrap">
      <h3 class="tab-sub" style="margin:0">🏛️ <?= $e($d['department']) ?></h3>
      <span class="muted" style="font-size:12px"><?= (int) $d['people'] ?> <?= (int) $d['people'] === 1 ? 'person' : 'people' ?></span>
    </div>
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin:8px 0 10px">
      <span class="pill p-info"><?= count($d['positions']) ?> position<?= count($d['positions']) === 1 ? '' : 's' ?></span>
      <span class="pill p-ok"><?= (int) $d['occupied'] ?> filled</span>
      <span class="pill <?= $d['vacant'] > 0 ? 'p-warn' : 'p-mut' ?>"><?= (int) $d['vacant'] ?> vacant</span>
      <span class="pill p-mut"><?= (int) $d['sanctioned'] ?> sanctioned</span>
    </div>

    <div class="muted" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Designations (<?= count($d['designations']) ?>)</div>
    <?php if ($d['designations']): ?>
      <?php foreach ($d['designations'] as $g): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;font-size:13px;padding:3px 0;border-bottom:1px solid var(--line-2,#f1f5f9)">
          <span><?= $e($g['label']) ?></span><?= $deptPicker($g['id'], $d['department']) ?>
        </div>
      <?php endforeach; ?>
    <?php else: ?><p class="muted" style="font-size:12.5px;margin:0 0 6px">No designations filed here yet — assign some from “General” below.</p><?php endif; ?>

    <?php if ($d['positions']): ?>
    <details style="margin-top:8px"><summary style="cursor:pointer;font-size:12px;color:var(--brand,#1e40af)">Positions in this department</summary>
      <div style="margin-top:6px">
        <?php foreach ($d['positions'] as $p): $sanc = (int) ($p['sanctioned_headcount'] ?? 0); $occ = (int) ($p['occupied_headcount'] ?? 0); ?>
          <div style="display:flex;justify-content:space-between;gap:8px;font-size:12.5px;padding:2px 0">
            <span><?= $e($p['name']) ?><?= trim((string) ($p['code'] ?? '')) !== '' ? ' <span class="muted">· ' . $e($p['code']) . '</span>' : '' ?></span>
            <span class="muted"><?= $occ ?>/<?= $sanc ?: 1 ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </details>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<?php if ($hub['general']): ?>
<div class="panel" style="margin-top:16px">
  <h3 class="tab-sub" style="margin-top:0">General designations <span class="muted" style="font-weight:400;font-size:12px">— not filed under a department (they show for every department). Pick a department to file one.</span></h3>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:6px 16px">
    <?php foreach ($hub['general'] as $g): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;font-size:13px;padding:3px 0;border-bottom:1px solid var(--line-2,#f1f5f9)">
        <span><?= $e($g['label']) ?></span><?= $deptPicker($g['id'], '') ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
