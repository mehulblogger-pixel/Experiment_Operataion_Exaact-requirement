<?php $p = $partner; ?>
<h1><?= $p ? 'Edit ' . e(partner_name($p)) : 'New business partner' ?></h1>
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
    <div class="ff"><label>Client type</label><select class="form-control searchable" name="client_type"><option value="">—</option><?php foreach (lk_options_or('client_type', CLIENT_TYPES) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($p && $p['client_type']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?><option value="__new__">➕ Other (add new)…</option></select>
      <input class="form-control" name="client_type_new" placeholder="New client type" style="display:none;margin-top:6px" data-newfor="client_type"></div>
    <div class="ff"><label>Industry</label><select class="form-control searchable" name="industry"><option value="">—</option><?php foreach (lk_options_or('industry', INDUSTRIES) as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($p && $p['industry']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?><option value="__new__">➕ Other (add new)…</option></select>
      <input class="form-control" name="industry_new" placeholder="New industry" style="display:none;margin-top:6px" data-newfor="industry"></div>
    <div class="ff"><label>Ownership type</label><select class="form-control" name="ownership_type"><option value="">—</option><?php foreach (lk_options_or('ownership', OWNERSHIP) as $k=>$v): ?><option value="<?= $k ?>" <?= ($p && $p['ownership_type']===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Status</label><select class="form-control" name="status"><?php foreach (lk_options_or('partner_status', STATUSES) as $k=>$v): ?><option value="<?= $k ?>" <?= (($p['status'] ?? 'ACTIVE')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>GSTIN</label><input class="form-control" name="gstin" value="<?= e($p['gstin'] ?? '') ?>" placeholder="e.g. 24ADUPL3517E2ZJ"></div>
    <div class="ff"><label>PAN (auto from GSTIN)</label><input class="form-control readonly-field" id="pan_display" value="<?= e($p['pan'] ?? '') ?>" readonly></div>
    <div class="ff"><label>State (auto from GSTIN)</label><input class="form-control readonly-field" id="state_display" value="<?= e($p['state'] ?? '') ?>" readonly></div>
    <div class="ff"><label>CIN</label><input class="form-control" name="cin" value="<?= e($p['cin'] ?? '') ?>"></div>
    <div class="ff"><label>TAN</label><input class="form-control" name="tan" value="<?= e($p['tan'] ?? '') ?>"></div>
    <div class="ff"><label>MSME / Udyam</label><input class="form-control" name="msme_udyam" value="<?= e($p['msme_udyam'] ?? '') ?>"></div>
    <div class="ff"><label>Website</label><input class="form-control" name="website" value="<?= e($p['website'] ?? '') ?>"></div>
    <?php // Agreed once here, then carried onto every invoice for this customer.
          // Before this existed the invoice form asked for both on every single
          // bill, which is how one customer ends up on 30 days in March and 45
          // in April with nobody able to say which was right. ?>
    <?php // A dropdown from the Payment terms master (Masters → Payment term),
          // with the current value kept even if it is a custom one not in the list,
          // and a blank "— none —" so it is optional. Add new terms under Masters. ?>
    <div class="ff"><label>Payment terms <span class="muted">— carried onto their invoices</span></label>
      <?php $ptOpts = function_exists('lk_options_or') ? lk_options_or('payment_term', defined('PAYMENT_TERMS') ? PAYMENT_TERMS : []) : [];
            $ptCur = (string)($p['payment_terms'] ?? ''); ?>
      <select class="form-control searchable" name="payment_terms">
        <option value="">— none —</option>
        <?php $ptSeen = false; foreach ($ptOpts as $k => $lab): $val = (string)$lab; if ($val === $ptCur) $ptSeen = true; ?>
          <option value="<?= e($val) ?>" <?= $val === $ptCur ? 'selected' : '' ?>><?= e($val) ?></option>
        <?php endforeach; ?>
        <?php if ($ptCur !== '' && !$ptSeen): ?><option value="<?= e($ptCur) ?>" selected><?= e($ptCur) ?> (current)</option><?php endif; ?>
      </select>
      <small class="muted">Add or edit options under <a href="/lookup?key=payment_term">Masters → Payment terms</a>.</small></div>
    <div class="ff"><label>Credit days <span class="muted">— sets the due date on their invoices</span></label>
      <input class="form-control" type="number" min="0" name="credit_days" value="<?= e((string)($p['credit_days'] ?? '')) ?>"
             placeholder="e.g. 45"></div>
    <div class="ff ff-wide"><label>Description</label><input class="form-control" name="description" value="<?= e($p['description'] ?? '') ?>"></div>
    <?php $selInsp = ($p && !empty($p['inspection_types'])) ? explode(',', $p['inspection_types']) : []; ?>
    <div class="ff ff-wide"><label>Types of inspection this client needs <span class="muted">— carried into new calls</span></label>
      <div class="checkgrid">
        <?php foreach (lk_options_or('inspection_type', INSPECTION_TYPES) as $k=>$v): ?>
          <label class="chk"><input type="checkbox" name="inspection_types[]" value="<?= e($k) ?>" <?= in_array($k, $selInsp, true)?'checked':'' ?>> <?= e($v) ?></label>
        <?php endforeach; ?>
      </div>
      <small class="muted">Tick all that apply. In a new call for this client the Type-of-inspection list is narrowed to these. Manage the master under <a href="/lookup?key=inspection_type">Type of inspection</a>.</small></div>
    <?php // §man-month — what this client's contract means by a man-month. Left
          // alone it follows the company default in Settings. Set here it applies
          // to every monthly deputation for this client, and a single deputation
          // can still override it. ?>
    <div class="ff"><label>What a man-month means for this <?= e(Tl('client')) ?></label>
      <select class="form-control" name="manmonth_basis">
        <option value="">— the company default —</option>
        <?php $mmb = (string)($p['manmonth_basis'] ?? '');
              foreach (MANMONTH_BASES as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= $mmb === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
      <small class="muted">Only matters for monthly <?= e(Tlp('job')) ?>. The default is under <a href="/settings">Settings</a>.</small></div>
    <div class="ff"><label>Minimum working days in their man-month</label>
      <input class="form-control" type="number" min="1" max="31" name="manmonth_min_days"
             value="<?= e((($p['manmonth_min_days'] ?? 0) ?: '')) ?>" placeholder="e.g. 26">
      <small class="muted">Below this the month is claimable pro-rata; above it, it is still one man-month.</small></div>
    <?php if (function_exists('render_custom_fields')) render_custom_fields('partner', $pcfvals ?? []); ?>
  </div>
  <div style="margin-top:18px;">
    <button class="btn" type="submit">Save</button>
    <a class="btn secondary" href="<?= $p ? '/partner?id=' . (int)$p['id'] : '/clients' ?>">Cancel</a>
  </div>
</form>
