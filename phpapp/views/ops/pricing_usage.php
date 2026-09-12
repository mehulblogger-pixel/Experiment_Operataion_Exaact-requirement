<?php
$p = $p ?? []; $usage = $usage ?? []; $sym = $sym ?? '₹';
$mods = $p['modules'] ?? [];
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/settings">Settings</a> › Plans, pricing &amp; usage</div>
<div class="master-head"><div>
  <h1>Plans, pricing &amp; usage</h1>
  <p class="sub" style="margin:2px 0 0">Set what each module and seat costs, the monthly AI allowance and the AI top-up pack — then push them to your workspaces. Below, see each workspace's usage.</p>
</div></div>

<form method="post" action="/pricing-usage" class="settings-form">
  <div class="panel" style="max-width:820px;margin-top:14px">
    <h3 class="tab-sub" style="margin-top:0">Module prices <span class="muted" style="font-weight:400;font-size:12.5px">— what a customer pays to add a module</span></h3>
    <div class="tbl-scroll" style="overflow-x:auto">
    <table class="grid" style="min-width:520px">
      <tr><th>Module</th><th>Per month (<?= e($sym) ?>)</th><th>Per year (<?= e($sym) ?>)</th></tr>
      <?php foreach ($mods as $k => $m): ?>
      <tr>
        <td><b><?= e($m['label']) ?></b></td>
        <td><input class="form-control" type="number" min="0" name="mod_<?= e($k) ?>_month" value="<?= (int) $m['month'] ?>" style="width:120px"></td>
        <td><input class="form-control" type="number" min="0" name="mod_<?= e($k) ?>_year"  value="<?= (int) $m['year'] ?>" style="width:120px"></td>
      </tr>
      <?php endforeach; ?>
      <tr>
        <td><b>Per seat (person)</b></td>
        <td><input class="form-control" type="number" min="0" name="seat_month" value="<?= (int) $p['seat_month'] ?>" style="width:120px"></td>
        <td><input class="form-control" type="number" min="0" name="seat_year"  value="<?= (int) $p['seat_year'] ?>" style="width:120px"></td>
      </tr>
    </table>
    </div>
    <div class="ff" style="margin-top:10px;max-width:200px"><label>Currency</label>
      <input class="form-control" name="currency" value="<?= e($p['currency']) ?>" maxlength="3" style="text-transform:uppercase"></div>
  </div>

  <div class="panel" style="max-width:820px;margin-top:14px">
    <h3 class="tab-sub" style="margin-top:0">AI allowance &amp; top-up</h3>
    <p class="muted" style="margin:0 0 10px;font-size:12.5px">
      Platform AI is <strong style="color:<?= $p['ai_platform'] ? 'var(--good,#15803d)' : 'var(--warn,#b45309)' ?>"><?= $p['ai_platform'] ? 'switched ON' : 'not set up' ?></strong>.
      <?= $p['ai_platform'] ? '' : 'Set PLATFORM_AI_KEY in config.local.php on the server to switch it on.' ?>
    </p>
    <div class="form-grid">
      <div class="ff"><label>Included AI actions / workspace / month</label>
        <input class="form-control" type="number" min="1" name="ai_cap" value="<?= (int) $p['ai_cap'] ?>"></div>
      <div class="ff"><label>Top-up pack size <span class="muted">— actions per pack</span></label>
        <input class="form-control" type="number" min="1" name="ai_pack_size" value="<?= (int) $p['ai_pack_size'] ?>"></div>
      <div class="ff"><label>Top-up pack price (<?= e($sym) ?>)</label>
        <input class="form-control" type="number" min="0" name="ai_pack_price" value="<?= (int) $p['ai_pack_price'] ?>"></div>
    </div>
  </div>

  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;max-width:820px">
    <button class="btn" type="submit" name="action" value="save">Save prices</button>
    <button class="btn" type="submit" name="action" value="apply_all" style="background:var(--brand)">Save &amp; push to all workspaces →</button>
  </div>
  <p class="muted" style="font-size:12px;margin-top:6px;max-width:820px">New workspaces use these automatically. "Push to all" also updates every existing workspace.</p>
</form>

<div class="panel" style="max-width:820px;margin-top:18px">
  <h3 class="tab-sub" style="margin-top:0">Workspace usage this month</h3>
  <?php if (!$usage): ?>
    <p class="muted">No hosted workspaces yet.</p>
  <?php else: ?>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="grid" style="min-width:520px">
    <tr><th>Workspace</th><th>Seats used</th><th>AI actions used</th><th></th></tr>
    <?php foreach ($usage as $u):
        $aiPct = ($u['ai_cap'] ?? 0) > 0 ? min(100, round(($u['ai_used'] ?? 0) / $u['ai_cap'] * 100)) : 0;
        $tone = $aiPct >= 100 ? 'var(--bad,#c0342b)' : ($aiPct >= 80 ? 'var(--warn,#b45309)' : 'var(--good,#15803d)'); ?>
    <tr>
      <td><b><?= e($u['name']) ?></b><?= empty($u['ok']) ? ' <span class="muted" style="font-size:11px">(unreachable)</span>' : '' ?></td>
      <td><?= (int) ($u['seats_used'] ?? 0) ?><?= ($u['seat_cap'] ?? 0) > 0 ? ' / ' . (int) $u['seat_cap'] : '' ?></td>
      <td><?= (int) ($u['ai_used'] ?? 0) ?> / <?= (int) ($u['ai_cap'] ?? 0) ?></td>
      <td style="min-width:120px"><div style="height:7px;border-radius:99px;background:var(--line,#eef1f4);overflow:hidden"><span style="display:block;height:100%;width:<?= (int) $aiPct ?>%;background:<?= $tone ?>"></span></div></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php endif; ?>
</div>
