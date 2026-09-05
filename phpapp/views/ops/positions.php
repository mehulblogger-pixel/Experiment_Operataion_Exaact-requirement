<?php
// Position master — list + add/edit form (desk-first). Data: $positions, $sel, $offices.
// Gated is_coordinator_level() in the handler. CSRF auto-stamped.
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$positions = $positions ?? []; $offices = $offices ?? []; $sel = $sel ?? null;
$posName = [];
foreach ($positions as $p) $posName[(int)$p['id']] = $p['name'];
?>
<div class="crumbs"><a href="/">Home</a> › Positions</div>
<div class="master-head">
  <div><h1>Position master</h1>
    <p class="sub" style="margin:2px 0 0">The roles your organisation sanctions — each with its department, grade, reporting line and headcount. Staff requisitions are validated against these.</p></div>
  <div class="row-actions">
    <a class="btn secondary" href="/positions-org">Org chart →</a>
    <form method="post" style="display:inline"><input type="hidden" name="do" value="save"><input type="hidden" name="name" value="New position"><button class="btn">＋ New position</button></form>
  </div>
</div>

<div style="display:grid;grid-template-columns:300px 1fr;gap:18px;align-items:start">
  <!-- List -->
  <div class="panel" style="padding:0">
    <div style="padding:12px 15px;border-bottom:1px solid var(--line,#e5e7eb);font-weight:700;font-size:14px">All positions (<?= count($positions) ?>)</div>
    <div>
      <?php if (!$positions): ?><div class="muted" style="padding:14px 15px">None yet — add one.</div><?php endif; ?>
      <?php foreach ($positions as $p): $on = $sel && (int)$p['id'] === (int)$sel['id']; ?>
        <a href="/positions?id=<?= (int)$p['id'] ?>" style="display:block;padding:11px 15px;border-bottom:1px solid var(--line,#eef1f5);color:inherit;text-decoration:none;<?= $on ? 'background:var(--brand,#1e40af);color:#fff' : '' ?>">
          <div style="font-weight:600;font-size:13.5px"><?= $e($p['name']) ?><?= (int)$p['active'] === 0 ? ' <span class="pill p-mut" style="font-size:10px">off</span>' : '' ?></div>
          <div style="font-size:11.5px;opacity:.8"><?= $e($p['department'] ?: '—') ?><?= $p['grade'] ? ' · ' . $e($p['grade']) : '' ?> · vac <?= max(0, (int)$p['sanctioned_headcount'] - (int)$p['occupied_headcount']) ?>/<?= (int)$p['sanctioned_headcount'] ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Form -->
  <div class="panel">
    <h3 class="tab-sub"><?= $sel ? 'Edit position' : 'Add a position' ?></h3>
    <form method="post">
      <input type="hidden" name="do" value="save">
      <input type="hidden" name="id" value="<?= (int)($sel['id'] ?? 0) ?>">
      <div class="ff-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
        <div class="ff"><label>Position name *</label><input class="form-control" name="name" value="<?= $e($sel['name'] ?? '') ?>" required></div>
        <div class="ff"><label>Position code</label><input class="form-control" name="code" value="<?= $e($sel['code'] ?? '') ?>"></div>
        <div class="ff"><label>Grade / band</label><input class="form-control" name="grade" value="<?= $e($sel['grade'] ?? '') ?>" placeholder="e.g. SENIOR"></div>
        <div class="ff"><label>Department</label><input class="form-control" name="department" value="<?= $e($sel['department'] ?? '') ?>"></div>
        <div class="ff"><label>Business unit</label><input class="form-control" name="sbu" value="<?= $e($sel['sbu'] ?? '') ?>"></div>
        <div class="ff"><label>Level</label><input class="form-control" name="level" value="<?= $e($sel['level'] ?? '') ?>" placeholder="e.g. L3 / Manager"></div>
        <div class="ff"><label>Reports to (position)</label>
          <select class="form-control" name="reports_to_id">
            <option value="0">— none (top of tree) —</option>
            <?php foreach ($positions as $p): if ($sel && (int)$p['id'] === (int)$sel['id']) continue; ?>
              <option value="<?= (int)$p['id'] ?>" <?= (int)($sel['reports_to_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= $e($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="ff"><label>Office</label>
          <select class="form-control" name="office_id">
            <option value="0">—</option>
            <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (int)($sel['office_id'] ?? 0) === (int)$o['id'] ? 'selected' : '' ?>><?= $e($o['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="ff"><label>HOD (head of department)</label><input class="form-control" name="hod_name" value="<?= $e($sel['hod_name'] ?? '') ?>"></div>
        <div class="ff"><label>Sanctioned headcount</label><input class="form-control" type="number" min="0" name="sanctioned_headcount" value="<?= (int)($sel['sanctioned_headcount'] ?? 0) ?>"></div>
        <div class="ff"><label>Occupied headcount</label><input class="form-control" type="number" min="0" name="occupied_headcount" value="<?= (int)($sel['occupied_headcount'] ?? 0) ?>"></div>
        <div class="ff"><label>Budgeted headcount</label><input class="form-control" type="number" min="0" name="budgeted_headcount" value="<?= (int)($sel['budgeted_headcount'] ?? 0) ?>"></div>
      </div>
      <?php if ($sel): ?>
        <p class="muted" style="margin-top:6px">Vacant now: <b><?= max(0, (int)$sel['sanctioned_headcount'] - (int)$sel['occupied_headcount']) ?></b> (sanctioned − occupied).</p>
      <?php endif; ?>
      <div style="margin-top:14px;display:flex;gap:8px">
        <button class="btn">Save position</button>
        <?php if ($sel): ?>
          <button class="btn secondary" name="do" value="toggle" formmethod="post"><?= (int)$sel['active'] === 1 ? 'Disable' : 'Enable' ?></button>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>
<style>.ff label{display:block;font-size:12px;font-weight:600;color:var(--ink,#28313f);margin-bottom:4px}
@media(max-width:820px){.ff-grid{grid-template-columns:1fr !important}}</style>
