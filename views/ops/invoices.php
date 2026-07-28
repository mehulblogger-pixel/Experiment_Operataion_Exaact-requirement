<?php
  $c = $counts; $f = $f ?? 'open';
  $cards = [
    'open'    => ['n'=>$c['open'],    'l'=>'Open',            'ic'=>'₹', 'tone'=>'tone-info'],
    'overdue' => ['n'=>$c['overdue'], 'l'=>'Past its due date','ic'=>'◷', 'tone'=>'tone-bad'],
    'draft'   => ['n'=>$c['draft'],   'l'=>'Draft',           'ic'=>'✎', 'tone'=>'tone-warn'],
  ];
?>
<div class="crumbs"><a href="/">Home</a> › Invoices</div>
<div class="master-head">
  <div><h1>Invoices</h1>
  <p class="sub" style="margin:2px 0 0">Real invoices with lines and tax, not a number typed onto a deputation. Money received is recorded separately and matched to them, so half a payment reads as half.</p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn secondary" href="/to-bill">Work waiting to be billed</a>
    <a class="btn secondary" href="/receipts">Money in</a>
    <?php if ($canIssue): ?><a class="btn" href="/invoice-new">+ New invoice</a><?php endif; ?>
  </div>
</div>

<div class="qcards" style="margin-top:16px">
  <?php foreach ($cards as $key=>$q): ?>
    <a class="qcard <?= $q['tone'] ?><?= $f===$key?' on':'' ?>" href="/invoices?f=<?= e($key) ?>">
      <div class="qic"><?= e($q['ic']) ?></div><div class="qn"><?= (int)$q['n'] ?></div><div class="ql"><?= e($q['l']) ?></div>
    </a>
  <?php endforeach; ?>
  <div class="qcard tone-ok"><div class="qic">Σ</div><div class="qn" style="font-size:20px"><?= fmoney_short($c['open_value']) ?></div><div class="ql">Open invoice value</div></div>
</div>

<div class="panel" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
  <b style="font-size:13.5px"><?= e($cards[$f]['l'] ?? ucfirst($f)) ?></b>
  <span style="margin-left:auto">
    <?php foreach (['open'=>'Open','paid'=>'Paid','draft'=>'Draft','cancelled'=>'Cancelled','all'=>'All'] as $k=>$lbl): ?>
      <a href="/invoices?f=<?= e($k) ?>" style="margin-left:10px<?= $f===$k?';font-weight:700':'' ?>"><?= e($lbl) ?></a>
    <?php endforeach; ?>
  </span>
</div>

<?= dt_render($dt, $rows, $total, [
      'caption'     => 'Invoice register',
      'search'      => true,
      'search_hint' => 'Invoice number, customer, PO…',
      'export'      => true,
      'empty'       => 'Nothing here yet. Start from “Work waiting to be billed” — it lists every finished deputation nobody has invoiced.',
    ]) ?>
<style>.qcard.on{outline:2px solid var(--brand);outline-offset:1px}</style>
