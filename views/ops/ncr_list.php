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
<div class="master-head">
  <div><h1>Nonconformities</h1>
  <p class="sub" style="margin:2px 0 0">What was wrong, what was done about the work that was already affected, and whether it needed a corrective action. A major one cannot be closed without one.</p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if ($canRaise): ?><a class="btn" href="/ncr-new">+ Raise a nonconformity</a><?php endif; ?>
    <a class="btn secondary" href="/ncr?<?= e(http_build_query(['f'=>$f,'export'=>'csv'])) ?>">⬇ CSV</a>
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

<div class="panel" style="padding:0;overflow:hidden">
  <div class="ctitle" style="padding:14px 18px 0">
    <h3><?= e($cards[$f]['l'] ?? ($f === 'closed' ? 'Closed' : 'All')) ?> <span class="muted">(<?= count($rows) ?>)</span></h3>
    <span>
      <?php foreach (['open'=>'Open','closed'=>'Closed','all'=>'All'] as $k=>$lbl): ?>
        <a href="/ncr?f=<?= e($k) ?>" style="margin-left:10px<?= $f===$k?';font-weight:700':'' ?>"><?= e($lbl) ?></a>
      <?php endforeach; ?>
    </span>
  </div>
  <?php if (!$rows): ?>
    <p class="muted" style="padding:18px">Nothing here.</p>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table class="dt" style="margin-top:8px">
    <thead><tr><th>Ref</th><th>What was wrong</th><th>Where it came from</th><th>Branch</th><th>Owner / due</th><th>Disposition</th><th>Corrective action</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
      $od = ($r['status'] !== 'CLOSED') && $r['due_on'] && $r['due_on'] < $today;
    ?>
      <tr>
        <td><a href="/ncr-item?id=<?= (int)$r['id'] ?>"><b><?= e($r['ref']) ?></b></a><br>
            <span class="pill <?= $r['severity']==='MAJOR'?'p-bad':($r['severity']==='MINOR'?'p-warn':'p-mut') ?>" style="font-size:11px"><?= e(ucfirst(strtolower($r['severity']))) ?></span></td>
        <td><a href="/ncr-item?id=<?= (int)$r['id'] ?>"><?= e($r['title']) ?></a><br>
            <span class="muted" style="font-size:12px">raised <?= e(fdate($r['detected_on'])) ?> by <?= e($r['detected_by'] ?: '—') ?></span></td>
        <td><?= e(NCR_SOURCES[$r['source']] ?? $r['source']) ?>
            <?php if ($r['job_code']): ?><br><span class="muted" style="font-size:12px"><?= e($r['job_code']) ?></span><?php endif; ?>
            <?php if ($r['display_name'] || $r['legal_name']): ?><br><span class="muted" style="font-size:12px"><?= e($r['display_name'] ?: $r['legal_name']) ?></span><?php endif; ?></td>
        <td><?= e($r['office_name'] ?: '—') ?></td>
        <td><?= e($r['owner'] ?: '—') ?><br>
            <span class="<?= $od ? 'pill p-bad' : 'muted' ?>" style="font-size:12px"><?= $r['due_on'] ? e(fdate($r['due_on'])) . ($od ? ' — late' : '') : 'no date' ?></span></td>
        <td><?php if (trim((string)$r['disposition']) === ''): ?><span class="pill p-warn">Not decided</span>
            <?php else: ?><?= e(NCR_DISPOSITIONS[$r['disposition']] ?? $r['disposition']) ?><?php endif; ?></td>
        <td><?php if ($r['capa_ref']): ?><a href="/capa-item?id=<?= (int)$r['capa_id'] ?>"><?= e($r['capa_ref']) ?></a>
            <?php elseif ($r['severity']==='MAJOR'): ?><span class="pill p-bad">Required</span>
            <?php else: ?><span class="muted">—</span><?php endif; ?></td>
        <td><span class="pill <?= $r['status']==='CLOSED'?'p-ok':($r['status']==='OPEN'?'p-warn':'p-mut') ?>"><?= e(NCR_STATUS[$r['status']] ?? $r['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
<style>.qcard.on{outline:2px solid var(--brand);outline-offset:1px}</style>
