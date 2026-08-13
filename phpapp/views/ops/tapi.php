<div class="crumbs"><a href="/">Home</a> › <span>Analytics</span></div>
<div class="master-head"><div>
  <h1>Analytics &amp; performance</h1>
  <p class="sub" style="margin:2px 0 0">What is happening in the business — every figure clicks through to the records behind it.</p>
</div></div>

<div class="pnav" style="display:flex;flex-wrap:wrap;gap:4px;margin:10px 0 6px">
  <?php foreach ($dashes as $k => $cfg): ?>
    <a class="btn btn-sm <?= $role === $k ? '' : 'secondary' ?>" href="/analytics?view=<?= e($k) ?>"><?= e($cfg['label']) ?></a>
  <?php endforeach; ?>
  <span style="flex:1"></span>
  <a class="btn btn-sm secondary" href="/analytics-kpis">KPI library</a>
  <a class="btn btn-sm secondary" href="/analytics-quality">Data quality</a>
</div>

<?= $body ?>
