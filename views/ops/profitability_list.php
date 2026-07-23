<div class="master-head">
  <div><h1>Profitability by BOSS / Contract</h1>
    <p class="sub">Revenue − labour − expenses − sub-con, per BOSS number · <?= count($rows) ?> shown</p></div>
</div>
<div class="tbl-scroll" style="overflow-x:auto">
<table class="grid">
  <tr><th>BOSS / Contract</th><th>Client</th><th>Jobs</th><th>Revenue</th><th>Expenses</th><th>Sub-con</th>
    <?php if ($seeSal): ?><th>Labour</th><th>Margin</th><th>Margin %</th><?php endif; ?></tr>
  <?php foreach ($rows as $r): $p = $r['p']; ?>
  <tr>
    <td><a href="/profitability?boss=<?= (int)$r['id'] ?>"><strong><?= e($r['boss_number']) ?></strong></a></td>
    <td><?= e($r['client_disp'] ?: $r['client_name'] ?: '—') ?></td>
    <td><?= (int)$p['jobs'] ?></td>
    <td><?= fmoney($p['revenue']) ?></td>
    <td><?= fmoney($p['expenses']) ?></td>
    <td><?= fmoney($p['subcon']) ?></td>
    <?php if ($seeSal): ?>
      <td><?= fmoney($p['labour']) ?></td>
      <td><strong style="color:<?= $p['margin']>=0?'#1f8a4c':'#c0392b' ?>"><?= fmoney($p['margin']) ?></strong></td>
      <td><?= $p['pct']===null?'—':e($p['pct']).'%' ?></td>
    <?php endif; ?>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="<?= $seeSal?9:6 ?>">No BOSS numbers yet. Add them under <a href="/m/boss">BOSS numbers</a>.</td></tr><?php endif; ?>
</table>
</div>
<p class="muted" style="margin-top:8px">Expenses include inspector voucher costs (travel + bills) and job-closure expenses attributed to each BOSS. Click a BOSS to see the line-by-line drill-down.</p>
