<div class="crumbs"><a href="/">Home</a> › Activity</div>
<div class="master-head">
  <div><h1>Activity</h1>
  <p class="sub" style="margin:2px 0 0">Everything that has happened, in date order. Most of it writes itself — a quote sent, a report issued, a complaint raised. What a person typed is marked apart from what the system recorded, because a timeline where the two look identical teaches you to distrust both.</p></div>
  <a class="btn secondary" href="/activities?<?= e(http_build_query(array_merge($_GET, ['export'=>'csv']))) ?>">⬇ CSV</a>
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

<div class="panel" style="padding:0;overflow:hidden;margin-top:16px">
  <div class="ctitle" style="padding:14px 18px 0"><h3>Timeline <span class="muted">(<?= count($rows) ?>)</span></h3></div>
  <?php if (!$rows): ?>
    <p class="muted" style="padding:18px">Nothing recorded yet. It fills itself as work happens.</p>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table class="dt" style="margin-top:8px">
    <thead><tr><th>When</th><th>What</th><th>Customer</th><th>About</th><th>Who</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $link = act_link($r); ?>
      <tr>
        <td style="white-space:nowrap"><?= e(fdate(substr((string)$r['occurred_at'],0,10))) ?>
          <br><span class="muted" style="font-size:12px"><?= e(substr((string)$r['occurred_at'],11,5)) ?></span></td>
        <td>
          <span class="pill <?= $r['auto'] ? 'p-mut' : 'p-ok' ?>" style="font-size:11px"><?= e(ACT_KINDS[$r['kind']] ?? $r['kind']) ?></span>
          <?= $r['direction'] ? ' <span class="muted" style="font-size:11px">' . e(ACT_DIRECTIONS[$r['direction']]) . '</span>' : '' ?>
          <br><?= e($r['subject']) ?>
          <?php if (trim((string)$r['body']) !== ''): ?><br><span class="muted" style="font-size:12px;white-space:pre-wrap"><?= e(mb_substr((string)$r['body'],0,140)) ?></span><?php endif; ?>
        </td>
        <td><?= $r['partner_id'] ? e($r['display_name'] ?: $r['legal_name']) : '<span class="muted">—</span>' ?></td>
        <td><?= e(act_entity_label($r) ?: '—') ?></td>
        <td><?= e($r['owner'] ?: '—') ?><?php if ($r['with_whom']): ?><br><span class="muted" style="font-size:12px">with <?= e($r['with_whom']) ?></span><?php endif; ?></td>
        <td><?php if ($link): ?><a class="btn small" href="<?= e($link) ?>">Open →</a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<?php if ($canWrite): ?>
<form method="post" action="/activity-add" class="panel" style="margin-top:16px;max-width:820px">
  <h3 style="margin-top:0">Record something that happened</h3>
  <div class="form-grid" style="gap:12px 16px">
    <div><label>What kind</label><select name="kind">
      <?php foreach (ACT_KINDS as $k=>$v): if ($k==='SYSTEM') continue; ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
    </select></div>
    <div><label>Which way</label><select name="direction">
      <option value="">—</option><option value="OUT">We contacted them</option><option value="IN">They contacted us</option>
    </select></div>
    <div><label>Customer</label><input name="partner_id" value="<?= (int)$pid ?: '' ?>" placeholder="customer id"></div>
    <div><label>When</label><input type="datetime-local" name="occurred_at" value="<?= e(date('Y-m-d\TH:i')) ?>"></div>
    <div class="ff-wide"><label>What happened *</label>
      <input name="subject" required maxlength="255" placeholder="e.g. Rang Rakesh about the vessel inspection — he wants it moved to the 14th"></div>
    <div class="ff-wide"><label>Anything more</label><textarea name="body" rows="2"></textarea></div>
    <div><label>Who with</label><input name="with_whom" maxlength="200"></div>
    <div><label>Outcome</label><input name="outcome" maxlength="60" placeholder="e.g. agreed, no answer, call back"></div>
  </div>
  <button class="btn" style="margin-top:12px">Record it</button>
</form>
<?php endif; ?>
