<div class="crumbs"><a href="/">Home</a> › Activity</div>
<div class="master-head">
  <div><h1>Activity</h1>
  <p class="sub" style="margin:2px 0 0">Everything that has happened, in date order. Most of it writes itself — a quote sent, a report issued, a complaint raised. What a person typed is marked apart from what the system recorded, because a timeline where the two look identical teaches you to distrust both.</p></div>
</div>

<div class="panel" style="margin-top:16px;display:flex;gap:14px;flex-wrap:wrap;align-items:center">
  <a href="/activities" class="btn small<?= $kind===''&&!($_GET['manual']??'')?'':' secondary' ?>">Everything</a>
  <?php foreach (ACT_KINDS as $k=>$v): ?>
    <a href="/activities?kind=<?= e($k) ?>" class="btn small<?= $kind===$k?'':' secondary' ?>"><?= e($v) ?></a>
  <?php endforeach; ?>
  <a href="/activities?manual=1" class="btn small<?= ($_GET['manual']??'')==='1'?'':' secondary' ?>">Typed by a person</a>
</div>

<?php if ($silent): ?>
<div class="panel" style="margin-top:16px;border-left:3px solid var(--warn)">
  <h3 style="margin:0 0 8px">Nothing has happened with these for 30 days <span class="muted">(<?= count($silent) ?>)</span></h3>
  <p class="muted" style="margin:0 0 10px;font-size:13px">The most-asked question in any CRM, and it only became answerable when the timeline existed.</p>
  <table class="dt">
    <thead><tr><th>Customer</th><th>Last anything</th></tr></thead>
    <tbody>
    <?php foreach ($silent as $s): ?>
      <tr><td><a href="/activities?partner=<?= (int)$s['id'] ?>"><?= e($s['display_name'] ?: $s['legal_name']) ?></a></td>
          <td><?= $s['last_touch'] ? e(fdate(substr((string)$s['last_touch'],0,10))) : '<span class="pill p-bad">never</span>' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div style="margin-top:16px">
  <?= dt_render($dt, $rows, $total, [
        'caption'     => 'Activity timeline',
        'search'      => true,
        'search_hint' => 'Search what happened…',
        'export'      => true,
        'empty'       => 'Nothing recorded yet. It fills itself as work happens.',
      ]) ?>
</div>

<?php if ($canWrite): ?>
<form method="post" action="/activity-add" class="panel" style="margin-top:16px;max-width:820px">
  <h3 style="margin-top:0">Record something that happened</h3>
  <div class="form-grid" style="gap:12px 16px">
    <div><label>What kind</label><select class="form-control" name="kind">
      <?php foreach (ACT_KINDS as $k=>$v): if ($k==='SYSTEM') continue; ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
    </select></div>
    <div><label>Which way</label><select class="form-control" name="direction">
      <option value="">—</option><option value="OUT">We contacted them</option><option value="IN">They contacted us</option>
    </select></div>
    <div><label>Customer</label><input class="form-control" name="partner_id" value="<?= (int)$pid ?: '' ?>" placeholder="customer id"></div>
    <div><label>When</label><input class="form-control" type="datetime-local" name="occurred_at" value="<?= e(date('Y-m-d\TH:i')) ?>"></div>
    <div class="ff-wide"><label>What happened *</label>
      <input class="form-control" name="subject" required maxlength="255" placeholder="e.g. Rang Rakesh about the vessel inspection — he wants it moved to the 14th"></div>
    <div class="ff-wide"><label>Anything more</label><textarea class="form-control" name="body" rows="2"></textarea></div>
    <div><label>Who with</label><input class="form-control" name="with_whom" maxlength="200"></div>
    <div><label>Outcome</label><input class="form-control" name="outcome" maxlength="60" placeholder="e.g. agreed, no answer, call back"></div>
  </div>
  <button class="btn" style="margin-top:12px">Record it</button>
</form>
<?php endif; ?>
