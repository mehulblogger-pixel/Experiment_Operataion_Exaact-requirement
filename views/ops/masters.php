<h1>Master data</h1>
<p class="sub">The reference lists the Calls and Jobs screens draw from. Add or edit these first.</p>
<div class="card-grid">
  <?php foreach ($masters as $key => $cfg): if (!master_access_ok($cfg['access'])) continue; ?>
    <a class="master-card" href="/m/<?= e($key) ?>">
      <strong><?= e($cfg['label']) ?></strong>
      <span class="muted"><?= (int)ops_val("SELECT COUNT(*) FROM {$cfg['table']}") ?> record(s)</span>
    </a>
  <?php endforeach; ?>
  <a class="master-card" href="/clients"><strong>Clients</strong><span class="muted">Customer master</span></a>
  <a class="master-card" href="/vendors"><strong>Vendors</strong><span class="muted">Manufacturer / supplier master</span></a>
</div>
