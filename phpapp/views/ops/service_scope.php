<div class="crumbs"><a href="/">Home</a> › <a href="/settings">Settings</a> › Service scope</div>
<div class="master-head">
  <div><h1>Service scope</h1>
    <p class="sub" style="margin:2px 0 0">Every service runs on its own. Inspection, Expediting, Vendor Assessment,
      Vendor Audit, Release and Project Deputation are independent — none is a prerequisite for another. Here you choose
      which services this installation offers, and which are in scope for each client. The most specific active
      setting wins: <em>Client → Contract → Project → PO → Assignment</em>.</p></div>
</div>

<?php
  $modeOf = function($code) use ($overrides) {
      if (!isset($overrides[$code])) return 'default';
      return ((int)$overrides[$code]['active'] === 1) ? 'on' : 'off';
  };
?>

<!-- 1) Global catalog: which services are offered at all -->
<section class="card" style="margin-bottom:16px">
  <h2 style="margin:0 0 4px">1 · Services offered on this installation</h2>
  <p class="sub" style="margin:0 0 12px">Turning a service off here removes it from the menu and workflows everywhere.
    A service tied to a licence module that has been switched off is unavailable regardless of this toggle.</p>
  <form method="post" action="/service-scope">
    <input type="hidden" name="action" value="global">
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th>Service</th><th>What it covers</th><th style="text-align:center">Offered</th>
          <th style="text-align:center">In scope by default</th></tr></thead>
        <tbody>
        <?php foreach ($catalog as $c): ?>
          <tr>
            <td><span style="font-size:15px"><?= e($c['icon']) ?></span> <strong><?= e($c['name']) ?></strong>
              <div class="muted" style="font-size:11px"><?= e($c['code']) ?><?= $c['module_key'] ? ' · licence: ' . e($c['module_key']) : '' ?></div></td>
            <td class="muted" style="font-size:12px"><?= e($c['description']) ?></td>
            <td style="text-align:center"><label class="switch"><input type="checkbox" name="g_<?= e($c['code']) ?>" <?= (int)$c['globally_active'] === 1 ? 'checked' : '' ?>><span class="sl"></span></label></td>
            <td style="text-align:center"><label class="switch"><input type="checkbox" name="d_<?= e($c['code']) ?>" <?= (int)$c['default_scope_active'] === 1 ? 'checked' : '' ?>><span class="sl"></span></label></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="margin-top:12px"><button class="btn" type="submit">Save services offered</button></div>
  </form>
</section>

<!-- 2) Per-client scope override -->
<section class="card" style="margin-bottom:16px">
  <h2 style="margin:0 0 4px">2 · Client service scope</h2>
  <p class="sub" style="margin:0 0 12px">Pick a client, then set which services apply to them. “Default” follows the
    catalog default above; “Active” / “Inactive” override it for this client. Contract / Project / PO / Assignment
    overrides sit below this in specificity and are set from those records.</p>
  <form method="get" action="/service-scope" style="margin-bottom:14px;display:flex;gap:8px;align-items:end;flex-wrap:wrap">
    <label style="flex:1;min-width:240px">Client
      <select name="client_id" onchange="this.form.submit()">
        <option value="0">— choose a client —</option>
        <?php foreach ($clients as $cl): ?>
          <option value="<?= (int)$cl['id'] ?>" <?= $client && (int)$client['id'] === (int)$cl['id'] ? 'selected' : '' ?>><?= e($cl['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <noscript><button class="btn secondary" type="submit">Load</button></noscript>
  </form>

  <?php if ($client): ?>
    <form method="post" action="/service-scope">
      <input type="hidden" name="action" value="client">
      <input type="hidden" name="client_id" value="<?= (int)$client['id'] ?>">
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr><th>Service</th><th style="text-align:center">Scope for <?= e($client['name']) ?></th><th>Effective</th></tr></thead>
          <tbody>
          <?php foreach ($catalog as $c): $m = $modeOf($c['code']); ?>
            <tr>
              <td><span style="font-size:15px"><?= e($c['icon']) ?></span> <strong><?= e($c['name']) ?></strong></td>
              <td style="text-align:center">
                <label style="margin-right:10px"><input type="radio" name="s_<?= e($c['code']) ?>" value="default" <?= $m === 'default' ? 'checked' : '' ?>> Default</label>
                <label style="margin-right:10px"><input type="radio" name="s_<?= e($c['code']) ?>" value="on" <?= $m === 'on' ? 'checked' : '' ?>> Active</label>
                <label><input type="radio" name="s_<?= e($c['code']) ?>" value="off" <?= $m === 'off' ? 'checked' : '' ?>> Inactive</label>
              </td>
              <td class="muted" style="font-size:12px"><?= isset($overrides[$c['code']]) ? e($overrides[$c['code']]['set_at']) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="margin-top:12px"><button class="btn" type="submit">Save scope for <?= e($client['name']) ?></button></div>
    </form>
  <?php endif; ?>
</section>

<!-- 3) Optional configurable dependencies -->
<section class="card">
  <h2 style="margin:0 0 4px">3 · Optional dependencies</h2>
  <p class="sub" style="margin:0 0 12px">By default there is <strong>no forced workflow</strong> — a service never waits on
    another. Add a dependency here only where a client or contract genuinely requires it (e.g. “Release requires a completed
    Inspection”). It applies only where you scope it; everywhere else the services stay independent.</p>

  <?php if ($deps): ?>
    <div class="tbl-wrap" style="margin-bottom:14px">
      <table class="tbl">
        <thead><tr><th>Service</th><th>Requires first</th><th>Condition</th><th>Approval</th><th>Scope</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($deps as $d): ?>
          <tr>
            <td><strong><?= e(function_exists('svc_name') ? svc_name($d['service_code']) : $d['service_code']) ?></strong></td>
            <td><?= e(function_exists('svc_name') ? svc_name($d['requires_code']) : $d['requires_code']) ?></td>
            <td class="muted" style="font-size:12px"><?= e($d['condition_note']) ?: '—' ?></td>
            <td><?= (int)$d['requires_approval'] === 1 ? 'Yes' : 'No' ?></td>
            <td class="muted" style="font-size:12px"><?= $d['ref_id'] > 0 ? e($d['level'] . ' #' . $d['ref_id']) : 'All clients (global)' ?></td>
            <td><form method="post" action="/service-scope" style="display:inline" onsubmit="return confirm('Remove this dependency?')">
              <input type="hidden" name="action" value="dep-del"><input type="hidden" name="dep_id" value="<?= (int)$d['id'] ?>">
              <button class="btn danger sm" type="submit">Remove</button></form></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="muted" style="margin:0 0 14px">No dependencies configured — every service is fully independent.</p>
  <?php endif; ?>

  <form method="post" action="/service-scope" style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;max-width:720px">
    <input type="hidden" name="action" value="dep-add">
    <label>Service <select name="dep_service" required>
      <?php foreach ($catalog as $c): ?><option value="<?= e($c['code']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
    </select></label>
    <label>Requires first <select name="dep_requires" required>
      <?php foreach ($catalog as $c): ?><option value="<?= e($c['code']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
    </select></label>
    <label style="grid-column:1/3">Condition (optional) <input type="text" name="dep_condition" placeholder="e.g. only for critical items"></label>
    <label>Scope level <select name="dep_level">
      <option value="">All clients (global)</option>
      <?php foreach ($levels as $lv => $meta): ?><option value="<?= e($lv) ?>"><?= e($meta[0]) ?></option><?php endforeach; ?>
    </select></label>
    <label>Scope record ID <input type="number" name="dep_ref" value="0" min="0"></label>
    <label>Effective from <input type="date" name="dep_effective"></label>
    <label style="display:flex;align-items:center;gap:8px;margin-top:22px"><input type="checkbox" name="dep_approval"> Requires approval</label>
    <div style="grid-column:1/3"><button class="btn" type="submit">Add dependency</button></div>
  </form>
</section>
