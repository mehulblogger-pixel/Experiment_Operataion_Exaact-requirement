<?php
  $c = $counts; $f = $f ?? 'all'; $today = $today ?? date('Y-m-d');
  $cards = [
    'pending'  => ['n'=>$c['pending'],  'l'=>'Invoice pending',   'ic'=>'▦', 'tone'=>'tone-info'],
    'awaiting' => ['n'=>$c['awaiting'], 'l'=>'Awaiting payment',  'ic'=>'◷', 'tone'=>'tone-warn'],
    'overdue'  => ['n'=>$c['overdue'],  'l'=>'Overdue',            'ic'=>'!', 'tone'=>'tone-bad'],
    'credit'   => ['n'=>$c['credit'],   'l'=>'Credit not received','ic'=>'⇄', 'tone'=>'tone-ok'],
  ];
?>
<div class="crumbs"><a href="/">Home</a> › <?= e(T_REG('invoice')) ?></div>
<div class="master-head">
  <div><h1><?= e(T_REG('invoice')) ?></h1>
  <p class="sub" style="margin:2px 0 0">Confirm invoicing, payment received and inter-office credit. Tick each job — it feeds profitability.</p></div>
  <a class="btn secondary" href="/invoicing?<?= e(http_build_query(array_merge($_GET, ['export'=>'csv']))) ?>">⬇ Download CSV</a>
</div>

<div class="qcards" style="margin-top:16px">
  <?php foreach ($cards as $key=>$q): ?>
    <a class="qcard <?= $q['tone'] ?><?= $f===$key?' on':'' ?>" href="/invoicing?f=<?= e($key) ?>">
      <div class="qic"><?= e($q['ic']) ?></div>
      <div class="qn"><?= (int)$q['n'] ?></div>
      <div class="ql"><?= e($q['l']) ?></div>
    </a>
  <?php endforeach; ?>
</div>

<div class="panel" style="padding:0;overflow:hidden">
  <div class="ctitle" style="padding:14px 18px 0">
    <h3><?= $f==='all'?'All open money items':e($cards[$f]['l'] ?? 'Worklist') ?> <span class="muted">(<?= count($rows) ?>)</span></h3>
    <?php if ($f!=='all'): ?><a href="/invoicing?f=all">Show all →</a><?php endif; ?>
  </div>
  <?php if (!$rows): ?>
    <p class="muted" style="padding:18px">Nothing here — all clear. 🎉</p>
  <?php else: ?>
  <table class="dt" style="margin-top:8px">
    <thead><tr><th>Job / <?= e(T("boss")) ?></th><th>Client</th><th class="num">Amount</th><th>Invoice</th><th>Payment</th><th>Credit</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
      $amount = (float)($r['invoice_amount'] ?: $r['expected_credit']);
      $raised = !empty($r['invoice_raised']);
      $paid   = !empty($r['payment_received']);
      $overdue = $raised && !$paid && ($r['invoice_due_date'] ?? '') && $r['invoice_due_date'] < $today;
      $odDays = $overdue ? (int)round((strtotime($today)-strtotime($r['invoice_due_date']))/86400) : 0;
      $isGiven = ($r['credit_direction'] ?? '') === 'GIVEN';
    ?>
      <tr>
        <td><b><?= e($r['job_code']) ?></b><?php if ($r['boss_number']): ?><br><span class="muted"><?= e(T('boss')) ?> <?= e($r['boss_number']) ?></span><?php endif; ?></td>
        <td><?= e($r['display_name'] ?: $r['legal_name'] ?: '—') ?><?php if ($r['office_name']): ?><br><span class="muted" style="font-size:12px"><?= e($r['office_name']) ?></span><?php endif; ?></td>
        <td class="num"><?= $amount ? fmoney($amount) : '—' ?></td>
        <td><?php if ($raised): ?><span class="pill p-ok"><?= e($r['invoice_number'] ?: 'Raised') ?></span><?php else: ?><span class="pill p-warn">Not raised</span><?php endif; ?></td>
        <td><?php
          if (!$raised) echo '<span class="pill p-mut">—</span>';
          elseif ($paid) echo '<span class="pill p-ok">Received</span>';
          elseif ($overdue) echo '<span class="pill p-bad">Overdue '.$odDays.'d</span>';
          else echo '<span class="pill p-warn">Awaiting</span>';
        ?></td>
        <td><?php
          if ($isGiven) echo '<span class="pill p-mut">Given</span>';
          elseif (!empty($r['credit_received'])) echo '<span class="pill p-ok">Received</span>';
          else echo '<span class="pill p-warn">Pending</span>';
        ?></td>
        <td><a class="btn small" href="/job?id=<?= (int)$r['id'] ?>#invoice">Confirm →</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<style>.qcard.on{outline:2px solid var(--brand);outline-offset:1px}</style>
