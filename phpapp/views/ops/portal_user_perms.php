<?php $held = trim((string)($u['perms'] ?? '')); $heldArr = $held === '' ? array_keys(PORTAL_PERMS) : array_filter(explode(',', $held));
      $mySites = array_filter(array_map('intval', explode(',', (string)($u['site_ids'] ?? '')))); ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/portal-users">Client portal</a> › <?= e($u['name'] ?: $u['email']) ?></div>
<div class="master-head"><div>
  <h1><?= e($u['name'] ?: $u['email']) ?></h1>
  <p class="sub" style="margin:2px 0 0"><?= e($u['partner_name'] ?: '') ?> · <?= e($u['email']) ?></p></div></div>

<?php if ($held === ''): ?>
<div class="panel" style="margin-top:16px;border-left:3px solid var(--warn)">
  <p style="margin:0">This person currently sees <b>everything</b> — they were invited before per-person access
    existed, so nothing was taken away from them on upgrade. Tick what they should actually have and save.</p>
</div>
<?php endif; ?>

<form method="post" action="/portal-user-perms" class="panel" style="margin-top:16px;max-width:720px">
  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">

  <h3 style="margin-top:0">Start from</h3>
  <p class="muted" style="margin:0 0 10px">A starting point, not a cage — tick and untick whatever you like afterwards.</p>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
    <?php foreach (PORTAL_PRESETS as $k => $p): ?>
      <button class="btn small secondary" type="button"
              onclick="applyPreset(<?= e(json_encode($p['perms'])) ?>, '<?= e($k) ?>')"><?= e($p['label']) ?></button>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="role_preset" id="rolePreset" value="<?= e($u['role_preset'] ?? '') ?>">

  <h3>What they can do</h3>
  <?php foreach (portal_perm_labels() as $k => $label): $id = 'p_' . str_replace('.', '_', $k); ?>
    <label style="display:flex;gap:9px;align-items:flex-start;margin:6px 0;font-size:14px">
      <input type="checkbox" name="<?= e($id) ?>" id="<?= e($id) ?>" data-perm="<?= e($k) ?>" value="1"
             <?= in_array($k, $heldArr, true) ? 'checked' : '' ?>>
      <span><?= e($label) ?></span></label>
  <?php endforeach; ?>
  <p class="muted" style="font-size:12.5px;margin-top:8px">Accepting a report binds the company, so it is deliberately
    separate from being able to read one. Somebody who chases invoices should not usually hold it.</p>

  <h3 style="margin-top:20px">Which sites</h3>
  <?php if (!$sites): ?>
    <p class="muted" style="margin:0">No site addresses are on this client's record, so this person sees all of their work.</p>
  <?php else: ?>
    <p class="muted" style="margin:0 0 8px">Leave every box clear for all sites — which is what a head-office contact needs.
      Tick some to limit a plant contact to their own works. Anything with no site recorded stays visible either way, so
      nothing disappears.</p>
    <?php foreach ($sites as $s): ?>
      <label style="display:flex;gap:9px;align-items:center;margin:4px 0;font-size:14px">
        <input type="checkbox" name="site_ids[]" value="<?= (int)$s['id'] ?>" <?= in_array((int)$s['id'], $mySites, true) ? 'checked' : '' ?>>
        <span><?= e(trim(($s['label'] ?: '') . ' ' . ($s['line1'] ?: '') . ' ' . ($s['city'] ?: '')) ?: 'Address #' . (int)$s['id']) ?></span></label>
    <?php endforeach; ?>
  <?php endif; ?>

  <h3 style="margin-top:20px">Administration &amp; access period</h3>
  <label style="display:flex;gap:9px;align-items:flex-start;margin:6px 0;font-size:14px">
    <input type="checkbox" name="is_org_admin" value="1" <?= !empty($u['is_org_admin']) ? 'checked' : '' ?>>
    <span>Let this person manage their own organisation's portal users (invite / withdraw colleagues, set access dates).
      Scoped to their own company only.</span></label>
  <div style="margin-top:12px">
    <label class="muted" style="display:block;font-size:12.5px;margin-bottom:4px">Access ends on <span style="opacity:.7">(optional — blank means never; access is revoked automatically after this date)</span></label>
    <input class="form-control" type="date" name="access_expires" value="<?= e($u['access_expires'] ?? '') ?>" style="max-width:200px">
  </div>

  <div style="margin-top:18px;display:flex;gap:10px">
    <button class="btn">Save</button>
    <a class="btn secondary" href="/portal-users">Cancel</a>
  </div>
</form>

<script>
function applyPreset(keys, name) {
  document.querySelectorAll('input[data-perm]').forEach(function (b) {
    b.checked = keys.indexOf(b.getAttribute('data-perm')) >= 0;
  });
  document.getElementById('rolePreset').value = name;
}
</script>
