<?php
  $res    = $res ?? [];
  $steps  = $res['steps'] ?? [];
  $checks = $res['checks'] ?? [];
  $ids    = $res['ids'] ?? [];
  $pass = 0; $fail = 0;
  foreach ($checks as $c) { if (!empty($c['ok'])) $pass++; else $fail++; }
  $allOk = $fail === 0 && $pass > 0 && empty($res['error']);
  $title = $title ?? 'Traceability thread';
  $intro = $intro ?? 'One record, followed the whole way through — and checked at every place.';
  $removeAction = $removeAction ?? '/trace-thread-remove';
  $bandText = $bandText ?? 'The record was built from a lead and followed all the way to money-in; each arrow below was then read back from the database to confirm it was saved.';
  $removeNote = $removeNote ?? 'Takes out the customer, lead, deal, quote, work-order, job, report, invoice and receipt it created — nothing else.';
  // Where each thread record can be opened live (route prefix, label). A caller
  // can pass its own map; this is the sales thread's default.
  $links = $links ?? [
    'client'  => ['/client?id=',      'Customer'],
    'lead'    => ['/lead?id=',        'Lead'],
    'opp'     => ['/opportunity?id=', 'Deal'],
    'quote'   => ['/quote?id=',       'Quotation'],
    'call'    => ['/call?id=',        'Work-order'],
    'job'     => ['/job?id=',         'Job'],
    'report'  => ['/document?id=',    'Report'],
    'invoice' => ['/invoice?id=',     'Invoice'],
    'receipt' => ['/receipt?id=',     'Money-in'],
  ];
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/settings">Settings</a> › <?= e($title) ?></div>
<div class="master-head"><div>
  <h1><?= e($title) ?></h1>
  <p class="sub" style="margin:2px 0 0"><?= e($intro) ?></p>
</div></div>

<?php if (!empty($res['error'])): ?>
<div class="nowband" style="border-left-color:var(--bad)">
  <div class="step">The thread stopped part-way.</div>
  <p class="next"><?= e($res['error']) ?></p>
</div>
<?php else: ?>
<div class="nowband" style="border-left-color:var(--<?= $allOk ? 'ok' : 'bad' ?>)">
  <div class="step"><?= $allOk ? '✔ Every link is recorded.' : '✘ ' . (int)$fail . ' link(s) are missing.' ?></div>
  <p class="next"><?= (int)$pass ?> of <?= (int)($pass + $fail) ?> checks passed. <?= e($bandText) ?></p>
</div>
<?php endif; ?>

<?php // Open the actual records this run created. ?>
<?php if ($ids): ?>
<div class="panel" style="margin-top:16px">
  <h3 class="tab-sub" style="margin-top:0">Open the records it created</h3>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php foreach ($links as $k => $lk): if (empty($ids[$k])) continue; ?>
      <a class="btn small secondary" href="<?= e($lk[0]) ?><?= (int)$ids[$k] ?>"><?= e($lk[1]) ?> →</a>
    <?php endforeach; ?>
  </div>
  <p class="muted" style="margin:10px 2px 0">These are real screens with real data — click through them the same way your team will.</p>
</div>
<?php endif; ?>

<?php // The steps, in order. ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">The thread, step by step</h3>
  <ol class="trace-steps">
    <?php foreach ($steps as $s): ?>
      <li>
        <div><b><?= e($s['title']) ?></b> <span class="pill p-mut"><?= e($s['ref']) ?></span></div>
        <div class="muted" style="font-size:13px"><?= e($s['detail']) ?></div>
      </li>
    <?php endforeach; ?>
  </ol>
</div>

<?php // The traceability table. ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Is every link recorded?</h3>
  <table class="dt">
    <thead><tr><th>Where</th><th>What it proves</th><th>Expected</th><th>Found</th><th></th></tr></thead>
    <tbody>
    <?php $area = ''; foreach ($checks as $c): ?>
      <?php if ($c['area'] !== $area): $area = $c['area']; ?>
        <tr><td colspan="5" style="background:var(--soft);font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.4px"><?= e($area) ?></td></tr>
      <?php endif; ?>
      <tr>
        <td></td>
        <td><?= e($c['proves']) ?></td>
        <td class="muted"><?= e($c['expected']) ?></td>
        <td class="muted"><?= e($c['actual']) ?></td>
        <td><?= !empty($c['ok']) ? '<span class="pill p-ok">✓ recorded</span>' : '<span class="pill p-bad">✗ missing</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<form method="post" action="<?= e($removeAction) ?>" style="margin-top:14px" onsubmit="return confirm('Remove the whole thread and all its records?')">
  <button class="btn danger" type="submit">Remove this thread</button>
  <span class="muted" style="margin-left:8px;font-size:13px"><?= e($removeNote) ?></span>
</form>

<style>
  .trace-steps{margin:0;padding-left:22px;display:flex;flex-direction:column;gap:10px}
  .trace-steps li{padding-left:4px}
</style>
