<?php $p = $partner; ?>
<h1><?= $p ? 'Edit ' . e(partner_name($p)) : 'New Business Partner' ?></h1>
<p class="sub">Client Code is generated automatically. Enter GSTIN once — PAN &amp; State fill in automatically.</p>
<?php if (!empty($error)): ?><div class="msg msg-error"><?= e($error) ?></div><?php endif; ?>

<form method="post" action="<?= $p ? '/partner-edit?id=' . (int)$p['id'] : '/partner-new' ?>" class="panel">
  <div class="form-grid">
    <div class="ff"><label>Legal name *</label><input class="form-control" name="legal_name" required value="<?= e($p['legal_name'] ?? '') ?>"></div>
    <div class="ff"><label>Display name</label><input class="form-control" name="display_name" value="<?= e($p['display_name'] ?? '') ?>"></div>
    <div class="ff ff-check"><input type="checkbox" name="is_client" <?= ($p ? $p['is_client'] : ($defaultRole==='is_client')) ? 'checked' : '' ?>><label>Is a Client</label></div>
    <div class="ff ff-check"><input type="checkbox" name="is_vendor" <?= ($p ? $p['is_vendor'] : ($defaultRole==='is_vendor')) ? 'checked' : '' ?>><label>Is a Vendor</label></div>
    <div class="ff ff-check"><input type="checkbox" name="is_subcontractor" <?= ($p && $p['is_subcontractor']) ? 'checked' : '' ?>><label>Is a Sub-contractor</label></div>
    <div class="ff"></div>
    <div class="ff"><label>Client type</label><select class="form-control" name="client_type"><option value="">—</option><?php foreach (CLIENT_TYPES as $k=>$v): ?><option value="<?= $k ?>" <?= ($p && $p['client_type']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Industry</label><select class="form-control" name="industry"><option value="">—</option><?php foreach (INDUSTRIES as $k=>$v): ?><option value="<?= $k ?>" <?= ($p && $p['industry']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
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
  </div>
  <div style="margin-top:18px;">
    <button class="btn" type="submit">Save</button>
    <a class="btn secondary" href="<?= $p ? '/partner?id=' . (int)$p['id'] : '/clients' ?>">Cancel</a>
  </div>
</form>
