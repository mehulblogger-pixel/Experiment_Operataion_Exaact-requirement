<?php $p = $partner; ?>
<h1><?= $p ? 'Edit ' . e(partner_name($p)) : 'New Business Partner' ?></h1>
<p class="sub">Client Code is generated automatically. Enter GSTIN once — PAN &amp; State fill in automatically.</p>
<?php if (!empty($error)): ?><div class="msg msg-error"><?= e($error) ?></div><?php endif; ?>

<form method="post" action="<?= $p ? '/partner-edit?id=' . (int)$p['id'] : '/partner-new' ?>" class="panel">
  <div class="form-grid">
    <div class="ff"><label>Legal name *</label><input class="form-control" name="legal_name" required value="<?= e($p['legal_name'] ?? '') ?>"></div>
    <div class="ff"><label>Display name <span class="muted">(auto from legal, editable)</span></label><input class="form-control" name="display_name" value="<?= e($p['display_name'] ?? '') ?>"></div>
    <?php if (!$p): ?>
    <div class="ff"><label>Contracting branch <span class="muted">(drives the code)</span></label>
      <select class="form-control searchable" name="home_branch_id">
        <?php foreach (($offices ?? []) as $o): ?><option value="<?= (int)$o['id'] ?>" <?= $o['code']==='AHM'?'selected':'' ?>><?= e($o['name']) ?> (<?= e($o['code']) ?>)</option><?php endforeach; ?>
      </select><small class="muted">Code will look like <strong>AHM-<?= substr(date('Y'),2,2) ?>-NAME-00001</strong>.</small></div>
    <?php else: ?>
    <div class="ff"><label>Code</label><input class="form-control readonly-field" value="<?= e($p['code']) ?>" readonly></div>
    <?php endif; ?>
    <div class="ff ff-wide"><label>Roles</label>
      <div class="checkgrid" style="grid-template-columns:repeat(3,minmax(150px,1fr));">
        <label class="chk"><input type="checkbox" name="is_client" <?= ($p ? $p['is_client'] : ($defaultRole==='is_client')) ? 'checked' : '' ?>> Is a Client</label>
        <label class="chk"><input type="checkbox" id="is_vendor" name="is_vendor" <?= ($p ? $p['is_vendor'] : ($defaultRole==='is_vendor')) ? 'checked' : '' ?>> Is a Vendor</label>
        <label class="chk"><input type="checkbox" id="is_subcon" name="is_subcontractor" <?= ($p && $p['is_subcontractor']) ? 'checked' : '' ?>> Is a Sub-contractor</label>
      </div>
      <small class="muted">Tick both Client and Vendor for a company that is both. A Sub-contractor (manpower supplier) is automatically also a Vendor.</small></div>
    <div class="ff"><label>Client type</label><select class="form-control" name="client_type"><option value="">—</option><?php foreach (CLIENT_TYPES as $k=>$v): ?><option value="<?= $k ?>" <?= ($p && $p['client_type']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <?php $indOpts = lk_options_or('industry', INDUSTRIES); $curInd = $p['industry'] ?? ''; $indKnown = array_key_exists($curInd, $indOpts); ?>
    <div class="ff"><label>Industry</label>
      <select class="form-control searchable other-toggle" name="industry" data-other="industry_other_wrap"><option value="">—</option>
        <?php foreach ($indOpts as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($curInd===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
        <option value="__other__" <?= ($curInd!=='' && !$indKnown)?'selected':'' ?>>Other (type it)…</option>
      </select>
      <div id="industry_other_wrap" style="margin-top:6px;<?= ($curInd!=='' && !$indKnown)?'':'display:none;' ?>">
        <input class="form-control" name="industry_other" placeholder="New industry — will be added &amp; spelling-corrected" value="<?= e(!$indKnown ? $curInd : '') ?>"></div></div>
    <div class="ff"><label>Ownership type</label><select class="form-control" name="ownership_type"><option value="">—</option><?php foreach (OWNERSHIP as $k=>$v): ?><option value="<?= $k ?>" <?= ($p && $p['ownership_type']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Status</label><select class="form-control" name="status"><?php foreach (STATUSES as $k=>$v): ?><option value="<?= $k ?>" <?= (($p['status'] ?? 'ACTIVE')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>GSTIN</label><input class="form-control" name="gstin" value="<?= e($p['gstin'] ?? '') ?>" placeholder="e.g. 24ADUPL3517E2ZJ"></div>
    <div class="ff"><label>PAN (auto from GSTIN)</label><input class="form-control readonly-field" id="pan_display" value="<?= e($p['pan'] ?? '') ?>" readonly></div>
    <div class="ff"><label>State (auto from GSTIN)</label><input class="form-control readonly-field" id="state_display" value="<?= e($p['state'] ?? '') ?>" readonly></div>
    <div class="ff"><label>CIN</label><input class="form-control" name="cin" value="<?= e($p['cin'] ?? '') ?>"></div>
    <div class="ff"><label>TAN</label><input class="form-control" name="tan" value="<?= e($p['tan'] ?? '') ?>"></div>
    <div class="ff"><label>MSME / Udyam</label><input class="form-control" name="msme_udyam" value="<?= e($p['msme_udyam'] ?? '') ?>"></div>
    <div class="ff"><label>Website</label><input class="form-control" name="website" value="<?= e($p['website'] ?? '') ?>"></div>
    <div class="ff ff-wide"><label>Description</label><input class="form-control" name="description" value="<?= e($p['description'] ?? '') ?>"></div>
    <?php $selInsp = ($p && !empty($p['inspection_types'])) ? explode(',', $p['inspection_types']) : []; ?>
    <div class="ff ff-wide"><label>Types of inspection this client needs <span class="muted">— carried into new calls</span></label>
      <div class="checkgrid">
        <?php foreach (lk_options_or('inspection_type', INSPECTION_TYPES) as $k=>$v): ?>
          <label class="chk"><input type="checkbox" name="inspection_types[]" value="<?= e($k) ?>" <?= in_array($k, $selInsp, true)?'checked':'' ?>> <?= e($v) ?></label>
        <?php endforeach; ?>
      </div>
      <small class="muted">Tick all that apply. In a new call for this client the Type-of-inspection list is narrowed to these. Manage the master under <a href="/lookup?key=inspection_type">Type of inspection</a>.</small></div>
    <div class="ff ff-wide"><label>Other inspection type(s) <span class="muted">— free text, not added to the master; separate multiple with commas</span></label>
      <input class="form-control" name="inspection_types_other" value="<?= e($p['inspection_types_other'] ?? '') ?>" placeholder="e.g. Rope-access survey, Drone inspection"></div>
  </div>
  <?php if (!$p): ?><p class="muted" style="margin-top:6px;">Code preview: <strong><?= substr(date('Y'),2,2) ?></strong> · roles become letters <strong>C</strong>lient / <strong>V</strong>endor / <strong>M</strong>anufacturer / <strong>T</strong>rader / <strong>S</strong>ub-contractor, e.g. <code>AHM-<?= substr(date('Y'),2,2) ?>-CV-ADANI-00042</code>.</p><?php endif; ?>
  <?php // Admin-defined custom fields on the Client/Vendor form (Masters → Custom fields → Client / Vendor)
    if (function_exists('custom_fields_for') && custom_fields_for('partner')): ?>
    <div class="form-grid" style="margin-top:6px;"><?php render_custom_fields('partner', $p ? custom_values_map('partner', $p['id']) : []); ?></div>
  <?php endif; ?>
  <div style="margin-top:18px;">
    <button class="btn" type="submit">Save</button>
    <a class="btn secondary" href="<?= $p ? '/partner?id=' . (int)$p['id'] : '/clients' ?>">Cancel</a>
  </div>
</form>
