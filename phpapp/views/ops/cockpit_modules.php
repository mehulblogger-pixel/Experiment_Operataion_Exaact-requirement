<?php
// Cockpit → Features (modules). A customer-friendly view over the ONE module
// engine (licence.php). Toggling posts to /workspace/setup/module-toggle, which
// delegates to licence_save(). Turning a feature off asks for confirmation first
// and lists what depends on it — never a silent disable.
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$confirmOff = (string) ($confirmOff ?? '');
$featCat = $featCat ?? []; $featOff = $featOff ?? []; $presetAsk = (string) ($presetAsk ?? ''); $presetPlan = $presetPlan ?? [];
$featBase = $featBase ?? []; $featChanged = $featChanged ?? [];
//  Plan 42 step A — the switches for every screen of one card. One form per card.
$featCard = function ($card) use ($featCat, $featOff, $e) {
    $items = $featCat[$card] ?? [];
    $legacy = array_values(array_filter(defined('FEAT_LEGACY') ? FEAT_LEGACY : [], fn($l) => $l[2] === $card));
    if (!$items && !$legacy) return;
    $nOff = count(array_filter($items, fn($i) => isset($featOff[$i['key']])));
    ?>
    <details class="fx" <?= $nOff ? 'open' : '' ?>>
      <summary><span>Screens in this module</span> <span class="muted">· <?= count($items) - $nOff ?> on<?= $nOff ? ' · <b>' . $nOff . ' off</b>' : '' ?></span></summary>
      <form method="post" action="/workspace/setup/features-save">
        <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
        <input type="hidden" name="card" value="<?= $e($card) ?>">
        <?php foreach ($items as $i): $on = !isset($featOff[$i['key']]); ?>
          <label class="fx-row"><span class="fx-t"><b><?= $e($i['label']) ?></b><?php if ($i['desc'] !== ''): ?><span><?= $e($i['desc']) ?></span><?php endif; ?></span>
            <input type="checkbox" class="fx-sw" name="on[<?= $e($i['key']) ?>]" value="1" <?= $on ? 'checked' : '' ?> aria-label="<?= $e($i['label']) ?> on"></label>
        <?php endforeach; ?>
        <?php if ($legacy): ?><input type="hidden" name="legacy_seen" value="1">
          <?php foreach ($legacy as [$sk, $def, , $lab, $what]): ?>
          <label class="fx-row"><span class="fx-t"><b><?= $e($lab) ?></b><span><?= $e($what) ?> <i>(the switch you already had, now also here)</i></span></span>
            <input type="checkbox" class="fx-sw" name="legacy[<?= $e($sk) ?>]" value="1" <?= feat_legacy_on($sk, $def) ? 'checked' : '' ?> aria-label="<?= $e($lab) ?> on"></label>
          <?php endforeach; ?>
        <?php endif; ?>
        <div style="margin-top:10px"><button class="btn" type="submit">Save switches</button>
          <span class="muted" style="font-size:12.5px;margin-left:8px">Off = hidden from menus and links. Records are kept; switching on brings everything back.</span></div>
      </form>
    </details>
<?php };
?>
<style>
  .ck{max-width:760px}
  .ck .mod,.ck .hmod{border:1px solid var(--line,#e5e7eb);border-radius:14px;padding:14px 16px;margin-bottom:10px;background:var(--card,#fff)}
  .ck .hmod .hd,.ck .mod .hd{display:flex;gap:12px;align-items:flex-start;justify-content:space-between}
  .ck .hmod h3,.ck .mod h3{margin:0;font-size:16px}
  .ck .hmod .blurb,.ck .mod .blurb{color:#6b7280;font-size:13.5px;margin:3px 0 0}
  .ck .why{font-size:12px;color:#0b6b86;margin:8px 0 0}
  .ck .feats{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}
  .ck .feat{font-size:12px;background:#eef2ff;color:#3949ab;border:1px solid #dfe4ff;border-radius:999px;padding:3px 9px}
  .ck .on-pill{font-size:11.5px;font-weight:700;padding:3px 9px;border-radius:999px;white-space:nowrap}
  .ck .is-on{background:#e7f6ee;color:#137a4b;border:1px solid #bfe0cd}
  .ck .is-off{background:#eef1f5;color:#5c6b80;border:1px solid #dbe2ec}
  .ck .is-locked{background:#f4ecfb;color:#6b3fa0;border:1px solid #e0cff2}
  .ck .core{font-size:11.5px;color:#8494a8}
  .ck .confirm{border:1px solid #f0c2c0;background:#fdf3f3;border-radius:12px;padding:14px 16px;margin-bottom:14px}
  .ck .confirm h3{margin:0 0 6px;font-size:15px;color:#b42318}
  .ck .confirm ul{margin:6px 0 12px 18px;padding:0}
  .ck-actions{display:flex;gap:8px;flex-wrap:wrap}
  .ck .fx{margin-top:12px;border-top:1px solid var(--line,#e5e7eb);padding-top:8px}
  .ck .fx summary{cursor:pointer;font-weight:600;font-size:14px;padding:4px 0}
  .ck .fx-row{display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid var(--line,#eef0f2);cursor:pointer}
  .ck .fx-t{flex:1;min-width:0} .ck .fx-t b{display:block;font-size:14px} .ck .fx-t span{color:#6b7280;font-size:12.5px}
  .ck .fx-sw{appearance:none;-webkit-appearance:none;width:44px;height:26px;border-radius:999px;background:#c9d2d1;position:relative;flex:none;cursor:pointer;margin:0;transition:background .15s}
  .ck .fx-sw::after{content:"";position:absolute;left:3px;top:3px;width:20px;height:20px;border-radius:50%;background:#fff;transition:left .15s}
  .ck .fx-sw:checked{background:var(--brand,#145f5a)} .ck .fx-sw:checked::after{left:21px}
  .ck .fx-sw:focus-visible{outline:2px solid var(--focus,#2563eb);outline-offset:2px}
  .ck .qs{border:1px solid var(--line,#e5e7eb);border-radius:14px;padding:14px 16px;margin-bottom:12px;background:var(--card,#fff)}
  .ck .qs-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:8px}
  @media (max-width:640px){.ck .qs-grid{grid-template-columns:1fr}}
  .ck .qs-p{border:1px solid var(--line,#e5e7eb);border-radius:12px;padding:12px;background:var(--bg,#f6f8f8)}
  .ck .qs-p b{display:block} .ck .qs-p span{display:block;color:#6b7280;font-size:13px;margin:2px 0 10px}
</style>

<div class="ck">
  <?php //  Named as the menu names it. "Features" alone did not contain the
        //  word an owner would search for, which is half of why this screen
        //  could not be found at all (UAT 1.6.3). ?>
  <h1 style="margin:.2em 0">Modules &amp; features</h1>
  <p class="muted" style="margin-top:0">Turn on only the parts of the software your company needs. Everything here uses your plan — turning a feature off simply hides it and its screens.</p>

  <?php if (function_exists('feat_catalog')): ?>
  <div class="qs">
    <h3 style="margin:0;font-size:16px">Quick start</h3>
    <p class="muted" style="margin:3px 0 0;font-size:13.5px">One click for a whole layout. Each button first shows exactly what will change. Nothing is ever deleted.</p>
    <div class="qs-grid">
      <div class="qs-p"><b>↩️ Back to <?= $e(isset($featBase['at']) ? date('j M Y', strtotime($featBase['at'])) : 'today') ?></b><span>Every module and screen exactly as it was on <?= $e(isset($featBase['at']) ? date('j M Y', strtotime($featBase['at'])) : 'the day the switches arrived') ?>.</span>
        <form method="post" action="/workspace/setup/features-preset" style="margin:0"><?php if (function_exists('csrf_field')) echo csrf_field(); ?><input type="hidden" name="preset" value="baseline"><button class="btn secondary" type="submit">Use this</button></form></div>
      <div class="qs-p"><b>✨ Simple start (recommended)</b><span>The core that earns money: jobs, reports, expenses, invoices and simple sales. The rest is switched off.</span>
        <form method="post" action="/workspace/setup/features-preset" style="margin:0"><?php if (function_exists('csrf_field')) echo csrf_field(); ?><input type="hidden" name="preset" value="simple"><button class="btn" type="submit">Use this</button></form></div>
    </div>
    <?php if ($featChanged): ?><p class="muted" style="margin:8px 0 0;font-size:12.5px">Last change: <?= $e(date('j M Y, H:i', strtotime($featChanged['at'] ?? 'now'))) ?><?= ($featChanged['by'] ?? '') !== '' ? ' by ' . $e($featChanged['by']) : '' ?> · every change is in the audit log.</p><?php endif; ?>
  </div>

  <?php if ($presetAsk !== ''): ?>
    <div class="confirm" id="preset-confirm" style="border-color:#9ccfc8;background:#f2faf8">
      <?php if ($presetAsk === 'simple'): ?>
        <h3 style="color:inherit">Use Simple start?</h3>
        <p style="margin:.2em 0;font-size:13.5px">This switches OFF:</p>
        <ul>
          <?php if (!empty($presetPlan['modules'])): ?><li>Modules: <?= $e(implode(' · ', array_map(fn($k) => ($modules[$k]['label'] ?? $k), $presetPlan['modules']))) ?></li><?php endif; ?>
          <?php if (!empty($presetPlan['labels'])): ?><li>Screens: <?= $e(implode(' · ', $presetPlan['labels'])) ?></li><?php endif; ?>
          <?php if (!in_array('Pre-order checklist', $presetPlan['labels'] ?? [], true)): ?><li>Pre-order checklist</li><?php endif; ?>
        </ul>
      <?php else: ?>
        <h3 style="color:inherit">Go back to <?= $e(isset($featBase['at']) ? date('j M Y', strtotime($featBase['at'])) : 'how it was') ?>?</h3>
        <p style="margin:.2em 0 10px;font-size:13.5px">Every module, screen and switch is set back exactly as it was then.</p>
      <?php endif; ?>
      <p style="margin:0 0 10px;font-size:13.5px"><b>Nothing is deleted.</b> To undo, use “Back to <?= $e(isset($featBase['at']) ? date('j M Y', strtotime($featBase['at'])) : 'today') ?>”.</p>
      <div class="ck-actions">
        <form method="post" action="/workspace/setup/features-preset" style="margin:0"><?php if (function_exists('csrf_field')) echo csrf_field(); ?>
          <input type="hidden" name="preset" value="<?= $e($presetAsk) ?>"><input type="hidden" name="confirm" value="1">
          <button class="btn" type="submit"><?= $presetAsk === 'simple' ? 'Yes, use Simple start' : 'Yes, go back' ?></button></form>
        <a class="btn secondary" href="/workspace/setup/modules">Cancel</a>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($featCat['home'])): ?>
    <div class="hmod" id="card-home">
      <div class="hd"><div style="flex:1"><h3>Home &amp; insights</h3><p class="blurb">What people see first, and the cross-module summaries.</p></div><span class="on-pill is-on">On</span></div>
      <?php $featCard('home'); ?>
    </div>
  <?php endif; ?>
  <?php endif; ?>

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
    <div class="mod" id="card-<?= $e($key) ?>">
      <div class="hd">
        <div style="flex:1">
          <h3><?= $e($m['label']) ?></h3>
          <p class="blurb"><?= $e($m['blurb']) ?></p>
        </div>
        <div style="text-align:right">
          <?php if (!empty($m['locked'])): ?>
            <span class="on-pill is-locked">🔒 Not in plan</span>
          <?php else: ?>
            <span class="on-pill <?= $m['on'] ? 'is-on' : 'is-off' ?>"><?= $m['on'] ? 'On' : 'Off' ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="why"><?= $e($m['why']) ?></div>
      <?php // The marketplace has a second, platform-wide switch (connect_enabled).
            // With the module on but that switch off, nobody sees it — say so,
            // and let the platform owner switch it on from here (owner, 8 Nov 2026).
        if ($key === 'connect' && $m['on'] && function_exists('connect_enabled') && !connect_enabled()): ?>
        <div style="margin-top:8px;padding:10px 12px;border:1px solid #fcd34d;background:#fffbeb;border-radius:10px;font-size:13px">
          <b>Switched on here, but the Marketplace itself is switched off</b>, so it is not in the menu and the public marketplace page is closed.
          <?php if (function_exists('superadmin_can') && superadmin_can()): ?>
            <form method="post" action="/marketplace-toggle" style="display:inline-block;margin:6px 0 0">
              <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
              <input type="hidden" name="on" value="1"><input type="hidden" name="back" value="modules">
              <button class="btn" type="submit">Switch the Marketplace on</button></form>
          <?php elseif (function_exists('is_master') && is_master() && (!function_exists('current_tenant') || current_tenant() === '')):
                $saHost = function_exists('superadmin_host') ? superadmin_host() : ''; ?>
            Switch it on in Super Admin → <a href="<?= $e(($saHost !== '' ? 'https://' . $saHost : '') . '/owner') ?>">Owner home</a> → Marketplace.
          <?php else: ?>
            Your provider has switched the marketplace off for now.
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if ($m['features']): ?>
        <div class="feats"><?php foreach ($m['features'] as $f): ?><span class="feat"><?= $e($f) ?></span><?php endforeach; ?></div>
      <?php endif; ?>
      <div style="margin-top:12px">
        <?php if ($m['core']): ?>
          <span class="core">Always on — every workspace needs it.</span>
        <?php elseif (!empty($m['locked'])): ?>
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <span class="core">This feature isn’t part of your plan.</span>
            <?php if (function_exists('billing_can_manage') && billing_can_manage() && (int) setting_get('saas_seat_limit', 0) > 0): ?>
              <a class="btn" href="/subscription?add=<?= $e($key) ?>">Upgrade to add it</a>
            <?php else: ?>
              <span class="muted" style="font-size:12.5px">Contact us to add it to your plan.</span>
            <?php endif; ?>
          </div>
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
      <?php if (!empty($m['on']) && empty($m['locked'])) $featCard($key); ?>
    </div>
  <?php endforeach; ?>
</div>
