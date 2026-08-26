<?php
  // Module 38 — the notification / outbox log over email_log. Read-only.
  $qs = function (array $over = []) { return '/notifications?' . http_build_query(array_merge($_GET, $over)); };
  $short = function ($s, $n = 60) { $s = (string)$s; return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s; };
?>
<div class="crumbs"><a href="/">Home</a> › Notification log</div>
<div class="master-head">
  <div><h1>Notification log</h1>
    <p class="sub" style="margin:2px 0 0">Every email the system sent or tried to send — recipient, subject, category and whether it actually went out. Read-only, from the send log.</p></div>
</div>

<div class="kpi-row" style="margin:14px 0">
  <div class="kpi"><span class="kic">✅</span><div class="k">Sent (30d)</div><div class="v"><?= number_format($stats['sent']) ?></div><div class="d">delivered to the mail server</div></div>
  <div class="kpi"><span class="kic">⚠️</span><div class="k">Failed (30d)</div><div class="v <?= $stats['failed'] ? 'down' : '' ?>"><a href="<?= e($qs(['failed'=>1,'kind'=>''])) ?>"><?= number_format($stats['failed']) ?></a></div><div class="d"><?= (int)$stats['norecip'] ?> had no recipient</div></div>
  <div class="kpi"><span class="kic">📨</span><div class="k">Total (30d)</div><div class="v"><?= number_format($stats['total']) ?></div><div class="d">send attempts</div></div>
</div>

<?php if ((int)$stats['failed'] > 0 && !$failed): ?>
<div class="msg msg-warning" style="margin-bottom:12px">
  <b><?= number_format($stats['failed']) ?> send(s) failed in the last 30 days</b>
  <?= (int)$stats['norecip'] ? '(' . (int)$stats['norecip'] . ' because no recipient could be resolved)' : '' ?>.
  <a href="<?= e($qs(['failed'=>1,'kind'=>''])) ?>">Show only failed →</a>
</div>
<?php endif; ?>

<form method="get" action="/notifications" class="filters" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px">
  <input type="text" name="q" value="<?= e($q) ?>" placeholder="recipient or subject…" style="min-width:220px">
  <select name="kind">
    <option value="">All categories</option>
    <?php foreach ($kinds as $k): ?>
      <option value="<?= e($k) ?>" <?= $kind === $k ? 'selected' : '' ?>><?= e($k) ?></option>
    <?php endforeach; ?>
  </select>
  <label class="chk" style="display:inline-flex;align-items:center;gap:4px">
    <input type="checkbox" name="failed" value="1" <?= $failed ? 'checked' : '' ?>> Failed only</label>
  <button class="btn" type="submit">Filter</button>
  <?php if ($q !== '' || $kind !== '' || $failed): ?><a class="btn ghost" href="/notifications">Clear</a><?php endif; ?>
</form>

<div class="panel" style="padding:0;overflow:hidden">
  <table class="tbl">
    <thead><tr><th>When</th><th>To</th><th>Category</th><th>Subject</th><th>Status</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="5" class="muted" style="padding:14px">No notifications match.</td></tr>
    <?php else: foreach ($rows as $r): ?>
      <tr>
        <td class="muted" style="white-space:nowrap"><?= e(substr((string)$r['created_at'], 0, 16)) ?></td>
        <td><?= e($short($r['to_addr'] ?: ($r['cc_addr'] ? 'cc: ' . $r['cc_addr'] : '—'), 40)) ?></td>
        <td><?php if ($r['kind']): ?><span class="pill p-mut"><?= e($r['kind']) ?></span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
        <td><?= e($short($r['subject'], 70)) ?></td>
        <td><?php if ((int)$r['sent_ok'] === 1): ?>
              <span class="pill p-ok">sent</span>
            <?php else: ?>
              <span class="pill p-bad">failed</span>
              <?php if ($r['error']): ?><br><span class="muted" style="font-size:11px"><?= e($short($r['error'], 50)) ?></span><?php endif; ?>
            <?php endif; ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<p class="muted" style="font-size:12px;margin-top:8px">Showing the most recent 400 matching entries. This is the send record — a “sent” row means the mail was handed to the mail server, not a delivery receipt.</p>
