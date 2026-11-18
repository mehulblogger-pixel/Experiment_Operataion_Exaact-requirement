<?php
// Expense setup (owner, 18 Nov 2026) — ONE screen, four tabs, Master Admin only.
//   1 Claim rules · 2 Expense types (added and changed HERE, vehicles included)
//   3 Bands = the company's policy, every amount on one table
//   4 People = a default band for each role, each person's band, and the way
//     to his personal allowances (a top-up ADDED ON TOP of his band).
// The types are the existing expense_heads / travel_modes rows; amounts are the
// existing inspector_allowances rows with a "from" date. Nothing is preset:
// every "₹ __" is the owner's to fill in.
$cs = cur_sym();
$tabs = ['rules' => '1 · Claim rules', 'types' => '2 · Expense types', 'bands' => '3 · Bands', 'people' => '4 · People'];
$bandName = []; foreach ($bands as $b) $bandName[(int)$b['id']] = $b['name'];
$amt = fn($v) => $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
?>
<style>
.es-wrap{max-width:900px}
.es-row{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--line);flex-wrap:wrap}
.es-row .es-what{min-width:0;flex:1 1 220px}
.es-edit{background:var(--bg,#f6f8f8);border:1.5px solid var(--brand,#0f5e5a);border-radius:12px;padding:12px;margin:8px 0 4px}
.es-chips{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
.es-chip{display:inline-flex;align-items:center;min-height:38px;padding:6px 14px;border:1.5px solid var(--line);border-radius:999px;font-weight:600;text-decoration:none;color:inherit}
.es-chip.on{border-color:var(--brand,#0f5e5a);background:var(--card);color:var(--brand-ink,#0f5e5a)}
.es-amt{width:120px}
.es-mean{color:var(--muted);font-size:13px;font-weight:400}
.es-type{white-space:normal;min-width:0}
@media (max-width:640px){.es-amt{width:96px}.es-wrap .grid td,.es-wrap .grid th{padding-left:6px;padding-right:6px}}
.es-note{color:var(--muted);font-size:13px;margin:8px 0 0}
</style>
<div class="es-wrap">
<div class="master-head">
  <div><h1>Expense setup</h1>
    <p class="sub" style="margin:2px 0 0">Everything about expense claims, in one place. Master Admin only.</p></div>
  <a class="btn secondary" href="/voucher-approvals">Who approves claims →</a>
</div>

<nav class="appr-tabs" aria-label="Expense setup">
  <?php foreach ($tabs as $k => $lab): ?><a class="appr-tab<?= $tab === $k ? ' on' : '' ?>" href="/voucher-rules?tab=<?= e($k) ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>><?= e($lab) ?></a><?php endforeach; ?>
</nav>

<?php if ($tab === 'rules'): ?>
<form method="post" action="/voucher-rules" class="panel">
  <input type="hidden" name="_do" value="rules">
  <label class="expd-lab">Claim period</label>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
    <?php foreach (EXP_PERIODS as $k => $lab): ?>
      <label class="pill <?= $rules['period'] === $k ? 'p-ok' : 'p-mut' ?>" style="min-height:40px;display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-size:14px">
        <input type="radio" name="period" value="<?= e($k) ?>" <?= $rules['period'] === $k ? 'checked' : '' ?>> <?= e($lab) ?></label>
    <?php endforeach; ?>
  </div>
  <div class="form-grid">
    <div class="ff"><label>A day's expenses can be added for … days after the day</label><input class="form-control" type="number" min="0" step="1" name="day_days" value="<?= (int)$rules['day_days'] ?>" required></div>
    <div class="ff"><label>A claim must be submitted within … days after its period ends</label><input class="form-control" type="number" min="0" step="1" name="claim_days" value="<?= (int)$rules['claim_days'] ?>" required></div>
    <div class="ff"><label>Usual distance — a difference above … % needs a reason (0 = any difference)</label><input class="form-control" type="number" min="0" step="0.5" name="km_tol" value="<?= e((string)$rules['km_tol']) ?>" required></div>
  </div>
  <p class="es-note">A period never crosses a month end. After a deadline the day or claim is locked; only the person's N+1 can reopen it, with a reason.
    Days and claims before <?= e(fdate($rules['from'])) ?> (when these rules started) are never locked by them.</p>
  <button class="btn" type="submit" style="margin-top:12px">Save</button>
</form>
<?php endif; ?>

<?php if ($tab === 'types'):
  $kindOpts = ['BILL' => 'Bill (amount + photo of the bill)', 'NOBILL' => 'No bill (amount + a short note)', 'PER_DAY' => 'Per day (an allowance for each site day)', 'MONTHLY' => 'Fixed per month'];
  $typeForm = function ($h) use ($kindOpts) {
      $isNew = $h === null; $k = $isNew ? 'BILL' : exp_kind($h); ?>
    <form method="post" action="/voucher-rules" class="es-edit" data-es-type>
      <input type="hidden" name="_do" value="<?= $isNew ? 'type_add' : 'type_save' ?>"><?php if (!$isNew): ?><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><?php endif; ?>
      <strong><?= $isNew ? 'Add an expense type' : 'Change: ' . e($h['label']) ?></strong>
      <div class="form-grid" style="margin-top:8px">
        <div class="ff"><label>Name</label><input class="form-control" name="label" maxlength="150" required value="<?= e($isNew ? '' : $h['label']) ?>" placeholder="e.g. Hotel, Site allowance, Own car"></div>
        <div class="ff"><label>Kind</label><select class="form-control" name="kind" data-es-kind>
          <?php if ($isNew): ?><option value="PER_KM">Per km (own vehicle — km × rate)</option><?php endif; ?>
          <?php foreach ($kindOpts as $kv => $kl): ?><option value="<?= e($kv) ?>" <?= $k === $kv ? 'selected' : '' ?>><?= e($kl) ?></option><?php endforeach; ?></select></div>
        <div class="ff" data-es-for><label>For</label><select class="form-control" name="scope">
          <option value="JOB" <?= !$isNew && exp_scope($h) === 'NONJOB' ? '' : 'selected' ?>>Job days</option>
          <option value="NONJOB" <?= !$isNew && exp_scope($h) === 'NONJOB' ? 'selected' : '' ?>>Not for a job (e.g. mobile bill)</option></select></div>
        <div class="ff" data-es-perday><label>Per day: how it is added</label><select class="form-control" name="add_mode">
          <option value="AUTO" <?= !$isNew && exp_add_mode($h) === 'AUTO' ? 'selected' : '' ?>>By itself on every closed site day</option>
          <option value="TICK" <?= $isNew || exp_add_mode($h) !== 'AUTO' ? 'selected' : '' ?>>When he ticks it (e.g. "Outstation today")</option></select></div>
      </div>
      <label class="ff-check" data-es-photo style="margin-top:6px"><input type="checkbox" name="needs_receipt" value="1" <?= $isNew || exp_needs_photo($h) ? 'checked' : '' ?>> Photo of the bill needed before he can submit</label>
      <label class="ff-check" data-es-personal style="margin-top:6px"><input type="checkbox" name="personal" value="1" data-es-pers <?= !$isNew && exp_is_personal($h) ? 'checked' : '' ?>> <strong>Personal allowance</strong> — not company policy: switched on for one person with his own amount, and always added on top of his band (e.g. a site allowance to adjust his pay)</label>
      <?php if (!$isNew): ?><label class="ff-check" style="margin-top:6px"><input type="checkbox" name="active" value="1" <?= (int)$h['active'] === 1 ? 'checked' : '' ?>> In use</label><?php endif; ?>
      <p class="es-note">No amount here. Policy amounts are set per band (tab 3); a personal allowance's amount is set on the person (tab 4).</p>
      <div style="display:flex;gap:8px;margin-top:10px"><button class="btn" type="submit"><?= $isNew ? 'Add' : 'Save' ?></button><a class="btn secondary" href="/voucher-rules?tab=types">Cancel</a></div>
    </form>
  <?php }; ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Vehicles (per km)</h3>
  <?php foreach ($modes as $m): ?>
    <div class="es-row"><span class="es-what"><strong><?= e($m['label']) ?></strong><br><span class="muted" style="font-size:13px">Per km · job days</span></span>
      <span class="pill <?= (int)$m['active'] === 1 ? 'p-ok' : 'p-mut' ?>"><?= (int)$m['active'] === 1 ? 'On' : 'Off' ?></span>
      <a class="btn small secondary" href="/voucher-rules?tab=types&amp;mode=<?= (int)$m['id'] ?>#m<?= (int)$m['id'] ?>">Change</a></div>
    <?php if ($editMode === (int)$m['id']): ?>
      <form method="post" action="/voucher-rules" class="es-edit" id="m<?= (int)$m['id'] ?>">
        <input type="hidden" name="_do" value="mode_save"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
        <strong>Change: <?= e($m['label']) ?></strong>
        <div class="form-grid" style="margin-top:8px"><div class="ff"><label>Name</label><input class="form-control" name="label" maxlength="150" required value="<?= e($m['label']) ?>"></div></div>
        <label class="ff-check" style="margin-top:6px"><input type="checkbox" name="active" value="1" <?= (int)$m['active'] === 1 ? 'checked' : '' ?>> In use</label>
        <p class="es-note">The rate per km is set per band (tab 3).</p>
        <div style="display:flex;gap:8px;margin-top:10px"><button class="btn" type="submit">Save</button><a class="btn secondary" href="/voucher-rules?tab=types">Cancel</a></div>
      </form>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php if (!$modes): ?><p class="muted">No vehicle yet. Add one below with kind "Per km".</p><?php endif; ?>

  <h3 class="tab-sub" style="margin-top:18px">Other expense types</h3>
  <?php foreach ($heads as $h): ?>
    <div class="es-row" id="h<?= (int)$h['id'] ?>"><span class="es-what"><strong><?= e($h['label']) ?></strong><?= exp_is_personal($h) ? ' <span class="pill p-warn">personal</span>' : '' ?><br><span class="muted" style="font-size:13px"><?= e(exp_kind_label($h)) ?></span></span>
      <span class="pill <?= (int)$h['active'] === 1 ? 'p-ok' : 'p-mut' ?>"><?= (int)$h['active'] === 1 ? 'On' : 'Off' ?></span>
      <a class="btn small secondary" href="/voucher-rules?tab=types&amp;edit=<?= (int)$h['id'] ?>#h<?= (int)$h['id'] ?>">Change</a></div>
    <?php if ($editId === (int)$h['id']) $typeForm($h); ?>
  <?php endforeach; ?>
  <details style="margin-top:14px" <?= isset($_GET['add']) ? 'open' : '' ?>><summary class="btn secondary" style="display:inline-flex">＋ Add an expense type</summary><?php $typeForm(null); ?></details>
</div>
<script>
(function(){document.querySelectorAll('[data-es-type]').forEach(function(f){
  var k=f.querySelector('[data-es-kind]'),p=f.querySelector('[data-es-pers]');
  function show(sel,on){var el=f.querySelector(sel);if(el)el.style.display=on?'':'none';}
  function sync(){var v=k.value,pers=p&&p.checked;
    if(pers&&v!=='PER_DAY'&&v!=='MONTHLY')k.value=v='PER_DAY';
    show('[data-es-for]',!pers&&v!=='MONTHLY'&&v!=='PER_KM');show('[data-es-perday]',!pers&&v==='PER_DAY');
    show('[data-es-photo]',v==='BILL');show('[data-es-personal]',v==='PER_DAY'||v==='MONTHLY');}
  k.addEventListener('change',sync);if(p)p.addEventListener('change',sync);sync();});})();
</script>
<?php endif; ?>

<?php if ($tab === 'bands'): ?>
<div class="panel">
  <p class="es-note" style="margin:0 0 10px">A band is the company's policy for a level — the same for everyone in it (e.g. hotel up to ₹ __ for a director, ₹ __ for an inspector). A person's own top-up is a personal allowance (tab 4), added on top.</p>
  <div class="es-chips">
    <?php foreach ($bands as $b): ?><a class="es-chip<?= $band && (int)$band['id'] === (int)$b['id'] ? ' on' : '' ?>" href="/voucher-rules?tab=bands&amp;band=<?= (int)$b['id'] ?>"><?= e($b['name']) ?><?= (int)$b['active'] ? '' : ' (off)' ?></a><?php endforeach; ?>
  </div>
  <details <?= $bands ? '' : 'open' ?>><summary class="btn secondary" style="display:inline-flex">＋ New band</summary>
    <form method="post" action="/voucher-rules" class="es-edit">
      <input type="hidden" name="_do" value="band_add">
      <div class="form-grid">
        <div class="ff"><label>Name</label><input class="form-control" name="name" maxlength="100" required placeholder="e.g. Field Inspector, Director, Office staff"></div>
        <?php if ($bands): ?><div class="ff"><label>Start as a copy of <span class="muted">— optional</span></label><select class="form-control" name="copy_from"><option value="">— start empty —</option>
          <?php foreach ($bands as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
      </div>
      <button class="btn" type="submit" style="margin-top:10px">Add band</button>
    </form>
  </details>
</div>
<?php if ($band): ?>
<form method="post" action="/voucher-rules" class="panel">
  <input type="hidden" name="_do" value="band_save"><input type="hidden" name="band_id" value="<?= (int)$band['id'] ?>">
  <h3 class="tab-sub" style="margin-top:0"><?= e($band['name']) ?> — what it may claim, and how much</h3>
  <p class="muted" style="font-size:13px;margin:0 0 10px"><?= (int)($bandCount[$band['id']] ?? 0) ?> people picked directly<?php $rc = array_keys(array_filter($roleBands, fn($x) => (int)$x === (int)$band['id'])); if ($rc): ?> · default band for: <?= e(implode(', ', array_map('role_name', $rc))) ?><?php endif; ?>.</p>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="grid">
    <tr><th>May claim</th><th>Type</th><th>Amount (<?= e($cs) ?>)</th></tr>
    <?php foreach ($modes as $m): $r = $bandRows['MODE'][$m['code']] ?? null; ?>
      <tr><td><input type="checkbox" name="allow_mode[<?= e($m['code']) ?>]" value="1" <?= $r && (int)$r['allowed'] === 1 ? 'checked' : '' ?> aria-label="<?= e($m['label']) ?> may be claimed" style="width:22px;height:22px"></td>
        <td class="es-type"><strong><?= e($m['label']) ?></strong><?= (int)$m['active'] ? '' : ' <span class="muted">· off</span>' ?><br><span class="es-mean">per km</span></td>
        <td><input class="form-control es-amt" type="number" step="0.01" min="0" name="amt_mode[<?= e($m['code']) ?>]" value="<?= e($r ? $amt($r['rate_override']) : '') ?>" placeholder="<?= (float)$m['default_rate'] > 0 ? e($amt($m['default_rate'])) : '__' ?>" aria-label="<?= e($m['label']) ?> rate per km"></td>
        </tr>
    <?php endforeach; ?>
    <?php foreach ($heads as $h): if (exp_is_personal($h)) continue; $r = $bandRows['HEAD'][$h['code']] ?? null; $k = exp_kind($h); $lim = in_array($k, ['BILL', 'NOBILL'], true); ?>
      <tr><td><input type="checkbox" name="allow_head[<?= e($h['code']) ?>]" value="1" <?= $r && (int)$r['allowed'] === 1 ? 'checked' : '' ?> aria-label="<?= e($h['label']) ?> may be claimed" style="width:22px;height:22px"></td>
        <td class="es-type"><strong><?= e($h['label']) ?></strong><?= (int)$h['active'] ? '' : ' <span class="muted">· off</span>' ?><br><span class="es-mean"><?= $k === 'BILL' ? 'up to, per bill' : ($k === 'NOBILL' ? 'up to, per day' : ($k === 'MONTHLY' ? 'per month' : 'per site day')) ?><?= exp_scope($h) === 'NONJOB' ? ' · not for a job' : '' ?></span></td>
        <td><input class="form-control es-amt" type="number" step="0.01" min="0" name="amt_head[<?= e($h['code']) ?>]" value="<?= e($r ? $amt($lim ? $r['limit_amount'] : $r['rate_override']) : '') ?>" placeholder="__" aria-label="<?= e($h['label']) ?> amount"></td>
        </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <p class="es-note">A bill above its "up to" amount is not refused — the approver sees it in amber. A blank bill amount means no limit. A per-day or monthly type with no amount adds nothing.</p>
  <div class="form-grid" style="margin-top:10px">
    <div class="ff"><label>These amounts apply from</label><input class="form-control" type="date" name="allow_from" value="<?= e(date('Y-m-d')) ?>" required></div>
    <div class="ff"><label>Band name</label><input class="form-control" name="name" maxlength="100" value="<?= e($band['name']) ?>"></div>
    <div class="ff"><label class="ff-check"><input type="checkbox" name="active" value="1" <?= (int)$band['active'] ? 'checked' : '' ?>> In use</label></div>
  </div>
  <p class="es-note">Showing the amounts on <?= e(fdate($asOf)) ?><?= $bandDates ? ' · saved from: ' . e(implode(', ', array_map(fn($d) => $d === '' ? 'the start' : fdate($d), $bandDates))) : '' ?>. A new date keeps the older amounts for older claims; lines already confirmed keep their amounts.</p>
  <button class="btn" type="submit" style="margin-top:12px">Save band</button>
</form>
<?php endif; ?>
<?php endif; ?>

<?php if ($tab === 'people'): ?>
<form method="post" action="/voucher-rules" class="panel">
  <input type="hidden" name="_do" value="people_save">
  <h3 class="tab-sub" style="margin-top:0">Default band for each role</h3>
  <?php if (!$bands): ?><p class="muted">Add a band first (tab 3).</p><?php endif; ?>
  <?php if (!$roles): ?><p class="muted">No team member has a login yet, so there is no role to set.</p><?php endif; ?>
  <div class="tbl-scroll" style="overflow-x:auto"><table class="grid">
    <?php if ($roles): ?><tr><th>Role</th><th>Everyone with this role follows</th></tr><?php endif; ?>
    <?php foreach ($roles as $r): $rk = strtoupper((string)$r); ?>
      <tr><td><strong><?= e(role_name($rk)) ?></strong></td>
        <td><select class="form-control" name="role_band[<?= e($rk) ?>]" style="min-width:200px" aria-label="Default band for <?= e(role_name($rk)) ?>"><option value="">— no band —</option>
          <?php foreach ($bands as $b): ?><option value="<?= (int)$b['id'] ?>" <?= (int)($roleBands[$rk] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></td></tr>
    <?php endforeach; ?>
  </table></div>

  <h3 class="tab-sub" style="margin-top:18px">Each person</h3>
  <div class="tbl-scroll" style="overflow-x:auto"><table class="grid">
    <tr><th>Person</th><th>Role</th><th>Band</th><th>Personal allowances</th></tr>
    <?php foreach ($people as $p): $rb = (int)($roleBands[$p['role']] ?? 0); $rbn = $rb ? ($bandName[$rb] ?? '') : ''; ?>
      <tr><td><strong><?= e($p['name']) ?></strong></td>
        <td class="muted"><?= $p['role'] !== '' ? e(role_name($p['role'])) : 'no login' ?></td>
        <td><select class="form-control" name="band[<?= (int)$p['id'] ?>]" style="min-width:210px" aria-label="Band for <?= e($p['name']) ?>">
          <option value=""><?= $rbn !== '' ? '— ' . e($rbn) . ' (from role) —' : '— no band —' ?></option>
          <?php foreach ($bands as $b): ?><option value="<?= (int)$b['id'] ?>" <?= (int)$p['band_id'] === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></td>
        <td><a class="btn small secondary" href="/m/inspectors/edit?id=<?= (int)$p['id'] ?>#tab=expenses"><?= $p['personal'] ? (int)$p['personal'] . ' · change' : 'Add' ?></a></td></tr>
    <?php endforeach; ?>
  </table></div>
  <p class="es-note">A person follows his role's band unless you pick another one for him. A personal allowance (e.g. a site allowance to adjust his pay) is added on top of his band — it never changes the band.</p>
  <button class="btn" type="submit" style="margin-top:12px">Save</button>
</form>
<?php endif; ?>
</div>
