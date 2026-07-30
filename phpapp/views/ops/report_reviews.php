<?php
  $today = date('Y-m-d');
  $unack = count(array_filter($rejected, fn($r) => trim((string)$r['ack_at']) === ''));
?>
<div class="crumbs"><a href="/">Home</a> › Client acceptance</div>
<div class="master-head">
  <div><h1>Client acceptance of reports</h1>
  <p class="sub" style="margin:2px 0 0">What is sitting unanswered at a client, and what came back rejected. Nothing is ever accepted automatically by the passing of time — a silent client is not an approving one.</p></div>
  <a class="btn secondary" href="/report-reviews?<?= e(http_build_query(['f'=>$f,'export'=>'csv'])) ?>">⬇ CSV</a>
</div>

<div class="qcards" style="margin-top:16px">
  <a class="qcard tone-info<?= $f==='awaiting'?' on':'' ?>" href="/report-reviews?f=awaiting">
    <div class="qic">◷</div><div class="qn"><?= count($pending) ?></div><div class="ql">Awaiting the client</div></a>
  <a class="qcard <?= $unack ? 'tone-bad' : '' ?><?= $f==='rejected'?' on':'' ?>" href="/report-reviews?f=rejected">
    <div class="qic">!</div><div class="qn"><?= $unack ?></div><div class="ql">Rejected, not acknowledged</div></a>
  <div class="qcard tone-ok"><div class="qic">✓</div><div class="qn"><?= count($rejected) - $unack ?></div><div class="ql">Rejections dealt with</div></div>
</div>

<?php if ($f === 'rejected'): ?>
<div class="panel" style="padding:0;overflow:hidden">
  <div class="ctitle" style="padding:14px 18px 0"><h3>Rejected by the client <span class="muted">(<?= count($rejected) ?>)</span></h3></div>
  <?php if (!$rejected): ?>
    <p class="muted" style="padding:18px">Nothing has been rejected.</p>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table class="dt" style="margin-top:8px">
    <thead><tr><th>Report</th><th>Client</th><th>Why</th><th>What they said</th><th>Nonconformity</th><th>Acknowledged</th></tr></thead>
    <tbody>
    <?php foreach ($rejected as $r): ?>
      <tr<?= trim((string)$r['ack_at']) === '' ? ' style="background:rgba(220,60,60,.06)"' : '' ?>>
        <td><b><?= e($r['irn']) ?></b><?= (int)$r['rev'] ? ' <span class="muted">Rev ' . (int)$r['rev'] . '</span>' : '' ?>
            <br><span class="muted" style="font-size:12px"><?= e($r['office_name'] ?: '') ?></span></td>
        <td><?= e($r['display_name'] ?: $r['legal_name'] ?: '—') ?><br>
            <span class="muted" style="font-size:12px"><?= e($r['decided_by_name']) ?>, <?= e(fdate(substr((string)$r['decided_at'],0,10))) ?></span></td>
        <td><?= e(RCR_REASONS[$r['reason']] ?? $r['reason']) ?></td>
        <td style="max-width:340px;white-space:pre-wrap"><?= e($r['note']) ?></td>
        <td><?php if ($r['ncr_ref']): ?><a href="/ncr-item?id=<?= (int)$r['ncr_id'] ?>"><?= e($r['ncr_ref']) ?></a><?php else: ?><span class="muted">—</span><?php endif; ?></td>
        <td><?php if (trim((string)$r['ack_at']) !== ''): ?>
              <span class="pill p-ok"><?= e(fdate(substr((string)$r['ack_at'],0,10))) ?></span><br>
              <span class="muted" style="font-size:12px"><?= e($r['ack_by']) ?></span>
            <?php elseif ($canAck): ?>
              <form method="post" action="/report-ack">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input class="form-control" name="note" placeholder="optional note" style="width:150px">
                <button class="btn small">Seen it</button>
              </form>
            <?php else: ?><span class="pill p-warn">Not yet</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<?php else: ?>
<div class="panel" style="padding:0;overflow:hidden">
  <div class="ctitle" style="padding:14px 18px 0"><h3>Issued, no answer yet <span class="muted">(<?= count($pending) ?>)</span></h3></div>
  <?php if (!$pending): ?>
    <p class="muted" style="padding:18px">Every issued report has an answer.</p>
  <?php else: ?>
  <table class="dt" style="margin-top:8px">
    <thead><tr><th>Report</th><th>Title</th><th>Client</th><th>Branch</th><th>Issued</th><th class="num">Waiting</th></tr></thead>
    <tbody>
    <?php foreach ($pending as $r): $days = rcr_waiting_days($r, $today); ?>
      <tr>
        <td><b><?= e($r['irn']) ?></b><?= (int)$r['rev'] ? ' <span class="muted">Rev ' . (int)$r['rev'] . '</span>' : '' ?></td>
        <td><?= e($r['title'] ?: '—') ?></td>
        <td><?= e($r['display_name'] ?: $r['legal_name'] ?: '—') ?></td>
        <td><?= e($r['office_name'] ?: '—') ?></td>
        <td><?= e(fdate(substr((string)($r['finalized_at'] ?: $r['issue_date']), 0, 10))) ?></td>
        <td class="num"><?php if ($days === null): ?><span class="muted">—</span>
            <?php else: ?><span class="<?= $days > 30 ? 'pill p-bad' : ($days > 14 ? 'pill p-warn' : '') ?>"><?= (int)$days ?> d</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<p class="muted" style="margin-top:12px;font-size:13px">Long waits are shown, not acted on. If a client's silence
  needs to mean something commercially, that belongs in the contract, not in a timer here.</p>
<?php endif; ?>
<style>.qcard.on{outline:2px solid var(--brand);outline-offset:1px}</style>
