<?php
// Connect — Company Business Capabilities. Master-only. A company enables one,
// several or all business capabilities; the app adapts to the combination.
// Visibility only — this grants no permission (the permission matrix still rules).
$companies = $companies ?? []; $sel = (int)($sel ?? 0);
$catalog = $catalog ?? []; $groups = $groups ?? []; $enabled = $enabled ?? [];
$configured = !empty($configured); $pools = $pools ?? ['internal'=>0,'associated'=>0,'marketplace'=>0];
$is_supplier = !empty($is_supplier);
$owner = (int)($owner ?? 0); $is_owner = !empty($is_owner); $does_inspection = !empty($does_inspection);
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/settings">Settings</a> › Company capabilities</div>
<div class="master-head">
  <div><h1>Company business capabilities</h1>
    <p class="sub" style="margin:2px 0 0">A company is not one fixed type. Turn on the capabilities it actually delivers —
      TPIA, technical-manpower supply, freelance resource supply, recruitment, project services — and the app adapts to that mix.
      This controls <strong>what a company sees</strong>, never what a user may do (permissions still govern that).
      A company with nothing ticked keeps seeing everything, exactly as before.</p></div>
</div>

<form method="get" action="/connect-capabilities" style="margin:12px 0">
  <label style="font-weight:600;font-size:13px">Company&nbsp;</label>
  <select name="party" onchange="this.form.submit()" style="padding:10px;border:1px solid var(--line,#dde3e2);border-radius:10px;min-width:280px">
    <?php foreach ($companies as $c): ?>
      <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id']===$sel?'selected':'' ?>><?= e($c['name']) ?><?= !empty($c['code'])?' ('.e($c['code']).')':'' ?></option>
    <?php endforeach; ?>
  </select>
  <noscript><button class="btn sec" type="submit">Show</button></noscript>
</form>

<?php if (!$sel): ?>
  <div class="panel"><p class="muted" style="margin:0">No companies yet. Add a client, vendor or agency first, then set its capabilities here.</p></div>
<?php else: ?>
<div class="panel" style="margin-bottom:12px">
  <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:center">
    <span class="pill <?= $configured?'ok':'muted' ?>" style="padding:4px 11px;border-radius:999px;font-size:12px;font-weight:600;background:<?= $configured?'#e6f5ee':'#eef1f0' ?>"><?= $configured ? count($enabled).' capabilities enabled' : 'Not configured — sees everything' ?></span>
    <?php if ($is_supplier): ?>
      <span style="font-size:13px">Freelance Supplier pools —
        <strong><?= (int)$pools['internal'] ?></strong> internal ·
        <strong><?= (int)$pools['associated'] ?></strong> associated ·
        <strong><?= (int)$pools['marketplace'] ?></strong> marketplace</span>
    <?php endif; ?>
  </div>
  <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--line,#eef1f0);display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <?php if ($is_owner): ?>
      <span class="pill ok" style="padding:4px 11px;border-radius:999px;font-size:12px;font-weight:600;background:#e6f5ee">★ This is the operating company — the workspace follows its capabilities</span>
      <form method="post" action="/connect-capabilities" style="margin:0"><input type="hidden" name="action" value="set_owner"><input type="hidden" name="party_id" value="0"><button class="btn sec" type="submit" style="padding:6px 12px;font-size:12.5px">Clear</button></form>
    <?php else: ?>
      <form method="post" action="/connect-capabilities" style="margin:0"><input type="hidden" name="action" value="set_owner"><input type="hidden" name="party_id" value="<?= $sel ?>"><button class="btn" type="submit" style="padding:7px 13px">Make this the operating company</button></form>
      <span class="muted" style="font-size:12.5px">Tailors this workspace’s navigation to what this company actually does (e.g. hides ISO/inspection registers from a pure recruiter).</span>
    <?php endif; ?>
    <span class="muted" style="font-size:12px">Operating company currently <?= $owner ? 'set' : 'not set — workspace shows everything' ?>; inspection registers <?= $does_inspection ? 'visible' : 'hidden' ?>.</span>
  </div>
</div>

<form method="post" action="/connect-capabilities">
  <input type="hidden" name="party_id" value="<?= $sel ?>">
  <?php foreach ($groups as $g): ?>
    <div class="panel" style="margin-bottom:12px">
      <h3 style="margin:0 0 10px;font-size:15px"><?= e($g) ?></h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:8px">
        <?php foreach ($catalog as $code => $c): if ($c['group'] !== $g) continue; $on = in_array($code, $enabled, true); ?>
          <label style="display:flex;gap:9px;align-items:flex-start;border:1px solid var(--line,#e3ebea);border-radius:10px;padding:10px 12px;cursor:pointer;<?= $on?'background:#eef6f4':'' ?>">
            <input type="checkbox" name="caps[]" value="<?= e($code) ?>" <?= $on?'checked':'' ?> style="margin-top:2px;width:17px;height:17px">
            <span style="font-size:13.5px;font-weight:600"><?= e($c['label']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <div style="display:flex;gap:10px;align-items:center;margin-top:6px">
    <button class="btn" type="submit">Save capabilities</button>
    <span class="muted" style="font-size:12.5px">Saving with nothing ticked resets the company to “sees everything”.</span>
  </div>
</form>
<?php endif; ?>
