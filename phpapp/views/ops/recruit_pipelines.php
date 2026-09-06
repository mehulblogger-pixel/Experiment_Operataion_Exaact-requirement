<?php
// Hiring workflows — configure the recruitment pipeline & stages (admin, desk-first).
// Data: $pipes, $sel, $stages, $kinds, $ops.  Forms POST to /recruit-pipelines
// (CSRF auto-stamped). No new permission: gated is_admin_level() in the handler.
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$kinds = $kinds ?? []; $ops = $ops ?? []; $pipes = $pipes ?? []; $stages = $stages ?? [];
$sel = $sel ?? null;
$applySummary = function ($p) use ($e) {
    $bits = [];
    foreach (['applies_company'=>'Company','applies_sbu'=>'Unit','applies_department'=>'Dept',
              'applies_position'=>'Position','applies_employment'=>'Type','applies_grade'=>'Grade',
              'applies_location'=>'Location'] as $c=>$lbl)
        if (trim((string)($p[$c] ?? '')) !== '') $bits[] = $lbl.': '.$e($p[$c]);
    return $bits ? implode(' · ', $bits) : 'All requisitions (default match)';
};
?>
<style>
  .rp-wrap{display:grid;grid-template-columns:280px minmax(0,1fr);gap:18px;align-items:start}
  .rp-card{background:var(--card,#fff);border:1px solid var(--line,#e5e7eb);border-radius:14px;
    box-shadow:var(--shadow-sm,0 1px 2px rgba(18,32,60,.06));min-width:0}
  .rp-body{min-width:0}
  .rp-card h2{margin:0;padding:14px 16px;font-size:15px;border-bottom:1px solid var(--line,#e5e7eb)}
  .rp-list a{display:block;padding:12px 16px;border-bottom:1px solid var(--line,#eef1f5);color:inherit;text-decoration:none}
  .rp-list a:hover{background:var(--soft,#f6f8fb)}
  .rp-list a.on{background:var(--brand,#1e40af);color:#fff}
  .rp-list a.on .rp-sub{color:#dbe5ff}
  .rp-nm{font-weight:600;font-size:14px} .rp-sub{font-size:11.5px;color:var(--muted,#656e7a);margin-top:2px}
  .rp-body{padding:16px 18px}
  .rp-grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .rp-grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
  label{display:block;font-size:12px;font-weight:600;color:var(--ink,#28313f);margin:10px 0 4px}
  input,select{width:100%;padding:8px 10px;border:1px solid var(--line,#d7dde5);border-radius:8px;font:inherit;background:#fff}
  .rp-btn{display:inline-flex;align-items:center;gap:6px;background:var(--brand,#1e40af);color:#fff;border:none;
    padding:8px 14px;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px}
  .rp-btn.ghost{background:#fff;color:var(--ink,#28313f);border:1px solid var(--line,#d7dde5)}
  .rp-btn.sm{padding:5px 10px;font-size:12px}
  .rp-scroll{overflow-x:auto;margin-top:6px}
  table.rp{width:100%;border-collapse:collapse;min-width:780px}
  table.rp th,table.rp td{padding:8px 9px;border-bottom:1px solid var(--line,#eef1f5);text-align:left;vertical-align:middle;font-size:12.5px}
  table.rp th{font-size:10.5px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted,#656e7a);background:var(--soft,#f6f8fb)}
  table.rp td input[name=name]{min-width:190px} table.rp td select[name=kind]{min-width:120px}
  .pill{display:inline-block;padding:1px 8px;border-radius:20px;font-size:11px;font-weight:700}
  .pill.k{background:#eef2ff;color:#4338ca}.pill.c{background:#fffbeb;color:#b45309}.pill.d{background:#ecfdf5;color:#047857}
  .rp-note{font-size:12px;color:var(--muted,#656e7a);margin:2px 0 10px}
  .rp-cond{background:var(--soft,#f6f8fb);border:1px dashed var(--line,#d7dde5);border-radius:8px;padding:8px 10px;margin-top:6px}
  @media(max-width:820px){.rp-wrap{grid-template-columns:1fr}.rp-grid2,.rp-grid3{grid-template-columns:1fr}}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
  <div>
    <h1 style="margin:0;font-size:20px">Hiring workflows</h1>
    <div class="rp-note">Configure the recruitment pipeline every candidate moves through. Ships with your Corporate Recruitment Workflow as the default — rename, reorder, add stages, or make a stage <b>conditional</b> (e.g. an L2 only for senior grades). Different companies/units can have completely different workflows on the same engine.</div>
  </div>
  <form method="post"><input type="hidden" name="do" value="pipeline_new"><button class="rp-btn">＋ New workflow</button></form>
</div>

<div class="rp-wrap">
  <!-- Left: workflows -->
  <div class="rp-card">
    <h2>Workflows</h2>
    <div class="rp-list">
      <?php foreach ($pipes as $p): $on = $sel && (int)$p['id'] === (int)$sel['id']; ?>
        <a href="/recruit-pipelines?id=<?= (int)$p['id'] ?>" class="<?= $on?'on':'' ?>">
          <div class="rp-nm"><?= $e($p['name']) ?>
            <?= (int)$p['is_default']===1 ? '<span class="pill d" style="margin-left:4px">Default</span>' : '' ?>
            <?= (int)$p['active']===0 ? '<span class="pill" style="background:#fee2e2;color:#dc2626;margin-left:4px">Off</span>' : '' ?>
          </div>
          <div class="rp-sub"><?= $e($applySummary($p)) ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Right: selected workflow -->
  <div class="rp-card">
    <?php if (!$sel): ?>
      <div class="rp-body"><p class="rp-note">No workflow selected.</p></div>
    <?php else: ?>
    <h2><?= $e($sel['name']) ?></h2>
    <div class="rp-body">

      <!-- Meta / applicability -->
      <form method="post">
        <input type="hidden" name="do" value="pipeline_save">
        <input type="hidden" name="id" value="<?= (int)$sel['id'] ?>">
        <div class="rp-grid3">
          <div><label>Workflow name</label><input name="name" value="<?= $e($sel['name']) ?>"></div>
          <div><label>Code</label><input name="code" value="<?= $e($sel['code']) ?>"></div>
          <div><label>Description</label><input name="description" value="<?= $e($sel['description']) ?>"></div>
        </div>
        <div class="rp-note" style="margin-top:12px"><b>Applies to</b> — leave a box blank for “any”. A requisition is matched to the workflow whose filters fit best (comma-separate to allow several values).</div>
        <div class="rp-grid3">
          <div><label>Company</label><input name="applies_company" value="<?= $e($sel['applies_company']) ?>"></div>
          <div><label>Business unit</label><input name="applies_sbu" value="<?= $e($sel['applies_sbu']) ?>"></div>
          <div><label>Department</label><input name="applies_department" value="<?= $e($sel['applies_department']) ?>"></div>
        </div>
        <div class="rp-grid3">
          <div><label>Position</label><input name="applies_position" value="<?= $e($sel['applies_position']) ?>"></div>
          <div><label>Employment type</label><input name="applies_employment" value="<?= $e($sel['applies_employment']) ?>"></div>
          <div><label>Grade</label><input name="applies_grade" value="<?= $e($sel['applies_grade']) ?>"></div>
        </div>
        <div class="rp-grid3">
          <div><label>Location</label><input name="applies_location" value="<?= $e($sel['applies_location']) ?>"></div>
          <div></div><div></div>
        </div>
        <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap">
          <button class="rp-btn">Save workflow</button>
          <?php if ((int)$sel['is_default']!==1): ?>
            <button class="rp-btn ghost" name="do" value="pipeline_default" formmethod="post">Make default</button>
            <button class="rp-btn ghost" name="do" value="pipeline_delete" onclick="return confirm('Disable this workflow?')" style="color:#dc2626">Disable</button>
          <?php endif; ?>
        </div>
      </form>

      <!-- Stages -->
      <h3 style="margin:22px 0 2px;font-size:14px">Stages</h3>
      <div class="rp-note">Lower “order” = earlier. A <b>conditional</b> stage runs only when its rule matches the requisition — otherwise it is skipped automatically.</div>
      <div class="rp-scroll">
      <table class="rp">
        <tr><th style="width:60px">Order</th><th>Stage</th><th>Type</th><th>Responsible</th><th>Condition</th><th>SLA</th><th></th></tr>
        <?php foreach ($stages as $s): if ((int)$s['active']===0) continue; ?>
        <tr>
          <form method="post">
            <input type="hidden" name="do" value="stage_save">
            <input type="hidden" name="pipeline_id" value="<?= (int)$sel['id'] ?>">
            <input type="hidden" name="stage_id" value="<?= (int)$s['id'] ?>">
            <input type="hidden" name="stage_key" value="<?= $e($s['stage_key']) ?>">
            <td><input name="seq" type="number" value="<?= (int)$s['seq'] ?>" style="width:56px"></td>
            <td><input name="name" value="<?= $e($s['name']) ?>"></td>
            <td>
              <select name="kind"><?php foreach ($kinds as $k=>$lbl): ?><option value="<?= $k ?>" <?= $s['kind']===$k?'selected':'' ?>><?= $e($lbl) ?></option><?php endforeach; ?></select>
            </td>
            <td><input name="responsible_role" value="<?= $e($s['responsible_role']) ?>" style="min-width:110px"></td>
            <td>
              <div style="display:flex;gap:4px;align-items:center">
                <input name="condition_field" value="<?= $e($s['condition_field']) ?>" placeholder="field" style="width:78px" title="e.g. grade, medical_required">
                <select name="condition_op" style="width:96px"><?php foreach ($ops as $k=>$lbl): ?><option value="<?= $k ?>" <?= $s['condition_op']===$k?'selected':'' ?>><?= $e($lbl) ?></option><?php endforeach; ?></select>
                <input name="condition_value" value="<?= $e($s['condition_value']) ?>" placeholder="value(s)" style="width:96px">
              </div>
              <label style="display:inline-flex;align-items:center;gap:4px;margin-top:4px;font-weight:500;font-size:11px;color:var(--muted,#656e7a)">
                <input type="checkbox" name="mandatory" value="1" <?= (int)$s['mandatory']===1?'checked':'' ?> style="width:auto">mandatory</label>
            </td>
            <td><input name="sla_days" type="number" value="<?= (int)$s['sla_days'] ?>" style="width:52px" title="SLA in days (0 = none)"></td>
            <td style="white-space:nowrap">
              <button class="rp-btn sm">Save</button>
              <button class="rp-btn ghost sm" name="do" value="stage_delete" onclick="return confirm('Remove this stage?')">✕</button>
            </td>
          </form>
        </tr>
        <?php endforeach; ?>
      </table>
      </div>

      <!-- Add a stage -->
      <div class="rp-cond" style="margin-top:14px">
        <form method="post">
          <input type="hidden" name="do" value="stage_save">
          <input type="hidden" name="pipeline_id" value="<?= (int)$sel['id'] ?>">
          <b style="font-size:12.5px">Add a stage</b>
          <div class="rp-grid3" style="margin-top:6px">
            <div><label>Order</label><input name="seq" type="number" value="<?= (count($stages)+1)*10 ?>"></div>
            <div><label>Name</label><input name="name" placeholder="e.g. Background check"></div>
            <div><label>Type</label><select name="kind"><?php foreach ($kinds as $k=>$lbl): ?><option value="<?= $k ?>"><?= $e($lbl) ?></option><?php endforeach; ?></select></div>
          </div>
          <div class="rp-grid3">
            <div><label>Stage key</label><input name="stage_key" placeholder="BG_CHECK"></div>
            <div><label>Responsible role</label><input name="responsible_role" placeholder="RECRUITER"></div>
            <div><label>SLA days (0 = none)</label><input name="sla_days" type="number" value="0"></div>
          </div>
          <div style="margin-top:10px"><button class="rp-btn">Add stage</button></div>
        </form>
      </div>

    </div>
    <?php endif; ?>
  </div>
</div>
