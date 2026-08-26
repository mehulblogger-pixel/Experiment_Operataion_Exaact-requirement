<?php
  // Module 46 — read-only integration health. Aggregates each integration's own
  // already-tracked last-sync / error / stuck-outbox signal into one place.
  $tone = ['ok'=>'p-ok', 'warn'=>'p-warn', 'bad'=>'p-bad'];
  $word = ['ok'=>'Healthy', 'warn'=>'Attention', 'bad'=>'Failing'];
  $attention = 0; foreach ($rows as $r) if ($r['severity'] !== 'ok') $attention++;
?>
<div class="crumbs"><a href="/">Home</a> › Integration health</div>
<div class="master-head">
  <div><h1>Integration health</h1>
    <p class="sub" style="margin:2px 0 0">Every external connection — Ads Pro, Books, licence renewal, email and more — with its last sync and whether it is working. Read-only, from each integration's own status.</p></div>
</div>

<?php if ($attention > 0): ?>
<div class="msg msg-warning" style="margin:12px 0">
  <b><?= (int)$attention ?> integration<?= $attention === 1 ? '' : 's' ?> need attention.</b>
  A sync that fails quietly is invisible until someone asks why data is stale — this is where to look.
</div>
<?php elseif ($rows): ?>
<div class="msg msg-ok" style="margin:12px 0"><b>All connected integrations are healthy.</b></div>
<?php endif; ?>

<?php if (!$rows): ?>
  <div class="panel"><p class="muted" style="margin:0">No external integrations are connected on this installation.</p></div>
<?php else: ?>
<div class="panel" style="padding:0;overflow:hidden">
  <table class="tbl">
    <thead><tr><th>Integration</th><th>Status</th><th>Detail</th><th>Last sync</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><b><?= e($r['label']) ?></b></td>
        <td><span class="pill <?= $tone[$r['severity']] ?? 'p-mut' ?>"><?= e($word[$r['severity']] ?? $r['severity']) ?></span></td>
        <td><?= e($r['detail']) ?></td>
        <td class="muted"><?= $r['last'] !== '' ? e(substr($r['last'], 0, 16)) : '—' ?></td>
        <td><a href="<?= e($r['url']) ?>">Open →</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<p class="muted" style="font-size:12px;margin-top:8px">Status is read passively from each integration's stored sync record — opening this page does not call any external service. Use each integration's own screen to test the live connection.</p>
<?php endif; ?>
