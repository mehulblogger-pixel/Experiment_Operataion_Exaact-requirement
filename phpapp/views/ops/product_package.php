<?php
  // Revamp P6 — the product-package chooser. Master-only. Sets the industry pack
  // + product bundles (the existing switches); every module can still be tuned on
  // the Licence screen afterwards.
  $packages = $packages ?? []; $current = $current ?? ''; $modules = $modules ?? [];
  // Label lookup for a bundle key, from the licence summary.
  $blabel = function ($k) use ($modules) { return $modules[$k]['label'] ?? ucfirst($k); };
  // Every non-core bundle key, so we can show what a package keeps vs hides.
  $nonCore = array_keys(array_filter($modules, fn($m) => empty($m['core'])));
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/super-admin">Control panel</a> › Product package</div>
<div class="master-head">
  <div><h1>Product package</h1>
    <p class="sub" style="margin:2px 0 0">Pick which EXAACT this installation is. This sets the industry pack and which product
      bundles are switched on — the same switches as Licence, in one decision. Every choice is reversible, and you can still
      fine-tune any single module on <a href="/licence">Licence</a>.</p></div>
  <a class="btn secondary" href="/super-admin">← Control panel</a>
</div>

<?php if ($current === ''): ?>
  <div class="msg msg-info" style="margin-top:12px">This installation's switches don't match any preset — it's a <strong>custom</strong> configuration.
    Applying a package below will set it to that preset; you can then tune individual modules on Licence.</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px;margin-top:14px">
  <?php foreach ($packages as $key => $p):
    $isCur = ($current === $key);
    $off = $p['off'];
    $on  = array_values(array_diff($nonCore, $off));
  ?>
    <div class="panel" style="<?= $isCur ? 'border:2px solid var(--brand);' : '' ?>margin:0">
      <div class="ctitle" style="margin-top:0"><h3 style="margin:0"><?= e($p['label']) ?>
        <?php if ($isCur): ?><span class="pill p-ok" style="margin-left:6px">Current</span><?php endif; ?></h3></div>
      <p class="muted" style="margin:6px 0 10px;font-size:13px"><?= e($p['desc']) ?></p>

      <div style="font-size:12px;margin-bottom:4px"><strong>Industry pack:</strong>
        <?= $p['packs'] === '' ? '<span class="muted">none (no ISO inspection gates)</span>' : e($p['packs']) ?></div>
      <div style="font-size:12px;margin-bottom:2px"><strong>On:</strong>
        <?php foreach (['admin', ...$on] as $b): ?><span class="pill p-ok" style="font-size:10px;margin:1px"><?= e($blabel($b)) ?></span><?php endforeach; ?></div>
      <?php if ($off): ?>
        <div style="font-size:12px;margin-bottom:8px"><strong>Hidden:</strong>
          <?php foreach ($off as $b): ?><span class="pill p-mut" style="font-size:10px;margin:1px"><?= e($blabel($b)) ?></span><?php endforeach; ?></div>
      <?php else: ?>
        <div style="font-size:12px;margin-bottom:8px"><span class="muted">Nothing hidden — every bundle on.</span></div>
      <?php endif; ?>

      <?php if (!$isCur): ?>
        <form method="post" action="/product-package-apply" style="margin:0"
              onsubmit="return confirm('Set this installation to “<?= e($p['label']) ?>”? Modules outside this package will be hidden (reversible).');">
          <input type="hidden" name="package" value="<?= e($key) ?>">
          <button class="btn" type="submit">Apply <?= e($p['label']) ?></button>
        </form>
      <?php else: ?>
        <button class="btn secondary" type="button" disabled>In use</button>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<p class="muted" style="margin-top:14px;font-size:12px">Note: on an installation whose modules are pinned by a signed licence key, the
  signed licence wins over this chooser. Applying a package changes settings only; no data is affected.</p>
