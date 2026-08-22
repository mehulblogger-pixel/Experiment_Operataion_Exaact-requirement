<?php
  $c = $counts; $f = $f ?? 'open';
  $cards = [
    'open'    => ['n'=>$c['open'],    'l'=>'Open',                 'ic'=>'▦', 'tone'=>'tone-info'],
    'major'   => ['n'=>$c['major'],   'l'=>'Major, still open',    'ic'=>'!', 'tone'=>'tone-bad'],
    'overdue' => ['n'=>$c['overdue'], 'l'=>'Past its date',        'ic'=>'◷', 'tone'=>'tone-warn'],
    'nodisp'  => ['n'=>$c['nodisp'],  'l'=>'No disposition yet',   'ic'=>'?', 'tone'=>'tone-warn'],
  ];
?>
<div class="crumbs"><a href="/">Home</a> › Nonconformities</div>
<?php if (function_exists('nc_tabs')) nc_tabs('ncr'); ?>
<div class="master-head">
  <div><h1>Nonconformities</h1>
  <p class="sub" style="margin:2px 0 0">What was wrong, what was done about the work that was already affected, and whether it needed a corrective action. A major one cannot be closed without one.</p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php // The register carries its own export, so it can promise what it is
          // exporting: every row the filter and search match, not this page. ?>
    <?php if ($canRaise): ?><a class="btn" href="/ncr-new">+ Raise a nonconformity</a><?php endif; ?>
  </div>
</div>

<div class="qcards" style="margin-top:16px">
  <?php foreach ($cards as $key=>$q): ?>
    <a class="qcard <?= $q['tone'] ?><?= $f===$key?' on':'' ?>" href="/ncr?f=<?= e($key) ?>">
      <div class="qic"><?= e($q['ic']) ?></div>
      <div class="qn"><?= (int)$q['n'] ?></div>
      <div class="ql"><?= e($q['l']) ?></div>
    </a>
  <?php endforeach; ?>
</div>

<div class="panel" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
  <b style="font-size:13.5px"><?= e($cards[$f]['l'] ?? ($f === 'closed' ? 'Closed' : 'All')) ?></b>
  <span style="margin-left:auto">
    <?php foreach (['open'=>'Open','closed'=>'Closed','all'=>'All'] as $k=>$lbl): ?>
      <a href="/ncr?f=<?= e($k) ?>" style="margin-left:10px<?= $f===$k?';font-weight:700':'' ?>"><?= e($lbl) ?></a>
    <?php endforeach; ?>
  </span>
</div>

<?= dt_render($dt, $rows, $total, [
      'caption'     => 'Nonconformity register',
      'search'      => true,
      'search_hint' => 'Reference, title or owner…',
      'export'      => true,
      'empty'       => 'Nothing here.',
    ]) ?>
<style>.qcard.on{outline:2px solid var(--brand);outline-offset:1px}</style>
