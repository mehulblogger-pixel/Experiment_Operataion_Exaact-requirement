<?php
// Cockpit → Features (modules). A customer-friendly view over the ONE module
// engine (licence.php). Toggling posts to /workspace/setup/module-toggle, which
// delegates to licence_save(). Turning a feature off asks for confirmation first
// and lists what depends on it — never a silent disable.
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$confirmOff = (string) ($confirmOff ?? '');
?>
<style>
  .ck{max-width:760px}
  .ck .mod{border:1px solid var(--line,#e5e7eb);border-radius:14px;padding:14px 16px;margin-bottom:10px;background:var(--card,#fff)}
  .ck .mod .hd{display:flex;gap:12px;align-items:flex-start;justify-content:space-between}
  .ck .mod h3{margin:0;font-size:16px}
  .ck .mod .blurb{color:#6b7280;font-size:13.5px;margin:3px 0 0}
  .ck .why{font-size:12px;color:#0b6b86;margin:8px 0 0}
  .ck .feats{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}
  .ck .feat{font-size:12px;background:#eef2ff;color:#3949ab;border:1px solid #dfe4ff;border-radius:999px;padding:3px 9px}
  .ck .on-pill{font-size:11.5px;font-weight:700;padding:3px 9px;border-radius:999px;white-space:nowrap}
  .ck .is-on{background:#e7f6ee;color:#137a4b;border:1px solid #bfe0cd}
  .ck .is-off{background:#eef1f5;color:#5c6b80;border:1px solid #dbe2ec}
  .ck .core{font-size:11.5px;color:#8494a8}
  .ck .confirm{border:1px solid #f0c2c0;background:#fdf3f3;border-radius:12px;padding:14px 16px;margin-bottom:14px}
  .ck .confirm h3{margin:0 0 6px;font-size:15px;color:#b42318}
  .ck .confirm ul{margin:6px 0 12px 18px;padding:0}
  .ck-actions{display:flex;gap:8px;flex-wrap:wrap}
</style>

<div class="ck">
  <div class="crumbs"><a href="/">Home</a> › <a href="/workspace/setup">Workspace setup</a> › Features</div>
  <h1 style="margin:.2em 0">Features</h1>
  <p class="muted" style="margin-top:0">Turn on only the parts of the software your company needs. Everything here uses your plan — turning a feature off simply hides it and its screens.</p>

  <?php
  // Confirmation panel when the admin asked to turn a feature OFF (§17).
  if ($confirmOff !== '' && isset($modules[$confirmOff]) && $modules[$confirmOff]['on'] && !$modules[$confirmOff]['core']):
    $m = $modules[$confirmOff]; ?>
    <div class="confirm">
      <h3>Turn off “<?= $e($m['label']) ?>”?</h3>
      <p style="margin:.2em 0;font-size:13.5px">These parts of your workspace will be hidden while it is off:</p>
      <ul>
        <?php foreach ($m['features'] as $f): ?><li><?= $e($f) ?></li><?php endforeach; ?>
      </ul>
      <div class="ck-actions">
        <form method="post" action="/workspace/setup/module-toggle" style="margin:0">
          <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
          <input type="hidden" name="module" value="<?= $e($confirmOff) ?>">
          <input type="hidden" name="on" value="">
          <input type="hidden" name="confirm" value="1">
          <button class="btn danger" type="submit">Turn it off &amp; hide these</button>
        </form>
        <a class="btn ghost" href="/workspace/setup/modules">Keep it on</a>
      </div>
    </div>
  <?php endif; ?>

  <?php foreach ($modules as $key => $m): ?>
    <div class="mod">
      <div class="hd">
        <div style="flex:1">
          <h3><?= $e($m['label']) ?></h3>
          <p class="blurb"><?= $e($m['blurb']) ?></p>
        </div>
        <div style="text-align:right">
          <span class="on-pill <?= $m['on'] ? 'is-on' : 'is-off' ?>"><?= $m['on'] ? 'On' : 'Off' ?></span>
        </div>
      </div>
      <div class="why"><?= $e($m['why']) ?></div>
      <?php if ($m['features']): ?>
        <div class="feats"><?php foreach ($m['features'] as $f): ?><span class="feat"><?= $e($f) ?></span><?php endforeach; ?></div>
      <?php endif; ?>
      <div style="margin-top:12px">
        <?php if ($m['core']): ?>
          <span class="core">Always on — every workspace needs it.</span>
        <?php else: ?>
          <form method="post" action="/workspace/setup/module-toggle" style="margin:0">
            <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
            <input type="hidden" name="module" value="<?= $e($key) ?>">
            <input type="hidden" name="on" value="<?= $m['on'] ? '' : '1' ?>">
            <?php if ($m['on']): ?>
              <button class="btn ghost" type="submit">Turn off this feature</button>
            <?php else: ?>
              <button class="btn" type="submit">Add this to my workspace</button>
            <?php endif; ?>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
