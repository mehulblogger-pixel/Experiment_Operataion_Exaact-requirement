<?php
  // Module 50 — one read-only "is the platform OK?" board, aggregating every health
  // verdict built across the programme. Each row comes from that subsystem's own helper.
  $tone = ['ok'=>'p-ok', 'warn'=>'p-warn', 'bad'=>'p-bad'];
  $word = ['ok'=>'OK', 'warn'=>'Watch', 'bad'=>'Attention'];
  $rank = ['ok'=>0, 'warn'=>1, 'bad'=>2]; $worst = 'ok';
  foreach ($rows as $r) if (($rank[$r['severity']] ?? 0) > $rank[$worst]) $worst = $r['severity'];
  $bad = 0; $warn = 0; foreach ($rows as $r) { if ($r['severity']==='bad') $bad++; elseif ($r['severity']==='warn') $warn++; }
?>
<div class="crumbs"><a href="/">Home</a> › System status</div>
<div class="master-head">
  <div><h1>System status</h1>
    <p class="sub" style="margin:2px 0 0">One place to see whether the platform itself is healthy — the audit trail, data integrity, compliance readiness, licence, integrations, email and the profit engine. Read-only; each line is that subsystem's own verdict.</p></div>
</div>

<?php $banner = $worst === 'bad' ? 'msg-error' : ($worst === 'warn' ? 'msg-warning' : 'msg-ok'); ?>
<div class="msg <?= $banner ?>" style="margin:12px 0">
  <?php if ($worst === 'ok'): ?><b>Everything checks out.</b> All <?= count($rows) ?> subsystems report healthy.
  <?php else: ?><b><?= $bad ?> attention · <?= $warn ?> to watch.</b> The lines below link to the screen that can fix each one.
  <?php endif; ?>
</div>

<div class="panel" style="padding:0;overflow:hidden">
  <table class="tbl">
    <thead><tr><th>Subsystem</th><th>Status</th><th>Summary</th><th>Detail</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><b><?= e($r['label']) ?></b></td>
        <td><span class="pill <?= $tone[$r['severity']] ?? 'p-mut' ?>"><?= e($word[$r['severity']] ?? $r['severity']) ?></span></td>
        <td><?= e($r['headline']) ?></td>
        <td class="muted"><?= e($r['detail']) ?></td>
        <td><?php if (!empty($r['url'])): ?><a href="<?= e($r['url']) ?>">Open →</a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="muted" style="padding:14px">No health signals available on this installation.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<p class="muted" style="font-size:12px;margin-top:8px">Read passively from each subsystem's stored verdict — opening this page runs the checks that are already computed, and calls no external service.</p>
