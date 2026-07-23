<?php
  $tRev = 0; $tExp = 0; $tSub = 0; $tLab = 0; $tMar = 0;
  foreach ($rows as $r) { $p = $r['p'];
    $tRev += (float)$p['revenue']; $tExp += (float)$p['expenses']; $tSub += (float)$p['subcon'];
    $tLab += (float)$p['labour']; $tMar += (float)$p['margin'];
  }
?>
<div class="master-head">
  <div><h1>Profitability by BOSS / Contract</h1>
    <p class="sub" style="margin:2px 0 0">Revenue − labour − expenses − sub-con, per BOSS number. Click a BOSS for the line-by-line drill-down.</p></div>
</div>

<div class="chip-row">
  <span class="ct">📄 <b><?= count($rows) ?></b> BOSS</span>
  <span class="ct">Revenue <b><?= fmoney_short($tRev) ?></b></span>
  <?php if ($seeSal): ?>
    <span class="ct"><span class="dot <?= $tMar>=0?'dot-ok':'dot-bad' ?>"></span>Margin <b><?= fmoney_short($tMar) ?></b><?= $tRev? ' · '.round($tMar/$tRev*100,1).'%':'' ?></span>
  <?php endif; ?>
</div>

<div class="panel" style="padding:0;overflow:hidden">
  <?php if (!$rows): ?>
    <div style="text-align:center;padding:34px"><div style="font-size:32px">📄</div>
      <p class="muted" style="margin:8px 0 0">No BOSS numbers yet. Add them under <a href="/m/boss">BOSS numbers</a>.</p></div>
  <?php else: ?>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr>
      <th>BOSS / Contract</th><th>Client</th><th class="num">Jobs</th><th class="num">Revenue</th>
      <th class="num">Expenses</th><th class="num">Sub-con</th>
      <?php if ($seeSal): ?><th class="num">Labour</th><th class="num">Margin</th><th class="num">Margin %</th><?php endif; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $p = $r['p']; $pos = $p['margin'] >= 0; ?>
    <tr>
      <td><a href="/profitability?boss=<?= (int)$r['id'] ?>"><b><?= e($r['boss_number']) ?></b></a></td>
      <td><?= e($r['client_disp'] ?: $r['client_name'] ?: '—') ?></td>
      <td class="num"><?= (int)$p['jobs'] ?></td>
      <td class="num"><?= fmoney($p['revenue']) ?></td>
      <td class="num"><?= fmoney($p['expenses']) ?></td>
      <td class="num"><?= fmoney($p['subcon']) ?></td>
      <?php if ($seeSal): ?>
        <td class="num"><?= fmoney($p['labour']) ?></td>
        <td class="num"><strong class="<?= $pos?'up':'down' ?>"><?= fmoney($p['margin']) ?></strong></td>
        <td class="num"><?php if ($p['pct']===null): ?>—<?php else: ?><span class="pill <?= $p['pct']<0?'p-bad':($p['pct']<10?'p-warn':'p-ok') ?>"><?= e($p['pct']) ?>%</span><?php endif; ?></td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
<p class="muted" style="margin-top:10px">Expenses include inspector voucher costs (travel + bills) and job-closure expenses attributed to each BOSS.</p>
