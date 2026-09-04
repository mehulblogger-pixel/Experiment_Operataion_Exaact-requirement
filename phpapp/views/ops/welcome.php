<?php
  // Guided welcome / getting-started home — the friendly orientation a new company lands on,
  // instead of being dropped into a deep module. Each step is a button; nothing needs a URL.
  $steps = $steps ?? []; $remaining = (int)($remaining ?? 0); $appName = $appName ?? ''; $licence = !empty($licence);
  $u = function_exists('current_user') ? current_user() : null;
  $who = trim((string)($u['name'] ?? $u['first_name'] ?? '')) ?: 'there';
  $done = count($steps) - $remaining;
?>
<div class="master-head" style="align-items:flex-start">
  <div>
    <h1 style="margin-bottom:2px"><?= $remaining === 0 ? '✅ You\'re all set up' : '👋 Welcome' ?><?= $who !== 'there' ? ', ' . e($who) : '' ?>!</h1>
    <p class="sub" style="margin:2px 0 0;max-width:60ch">
      <?php if ($remaining === 0): ?>
        Everything below is done — you're ready to run day to day. This card will step aside now.
      <?php else: ?>
        <?= $appName ? 'Welcome to <strong>' . e($appName) . '</strong>. ' : '' ?>Here's what to do next to get going. Each is one click — no links to remember.
        <?= $licence ? 'This is your private copy — it manages your own people and work.' : 'Post work and reach qualified people through the marketplace.' ?>
      <?php endif; ?>
    </p>
  </div>
  <a class="btn secondary" href="/">Go to home →</a>
</div>

<?php if ($steps): ?>
<div class="panel" style="margin-top:14px">
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
    <h3 class="tab-sub" style="margin:0">Getting started</h3>
    <span class="pill <?= $remaining === 0 ? 'p-ok' : 'p-info' ?>" style="font-size:11px"><?= (int)$done ?> / <?= count($steps) ?> done</span>
  </div>
  <div style="display:flex;flex-direction:column;gap:10px;margin-top:10px">
    <?php foreach ($steps as $i => $s): $isDone = !empty($s['done']); ?>
      <a href="<?= e($s['url']) ?>" class="panel" style="display:flex;align-items:center;gap:14px;text-decoration:none;margin:0;padding:14px 16px;border:1px solid var(--line);<?= $isDone ? 'opacity:.7' : '' ?>">
        <span style="width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:18px;flex:none;background:<?= $isDone ? 'var(--good-bg,#e4f0e8)' : 'var(--panel-2,#eef1f3)' ?>">
          <?= $isDone ? '✓' : e($s['icon']) ?></span>
        <span style="flex:1">
          <b style="display:block;font-size:15px;color:var(--ink);<?= $isDone ? 'text-decoration:line-through;color:var(--ink-3,#7c8894)' : '' ?>"><?= e($s['label']) ?></b>
          <span style="font-size:12.5px;color:var(--ink-2,#4a5560)"><?= e($s['why']) ?></span>
        </span>
        <span style="color:var(--ink-3,#7c8894);font-size:18px"><?= $isDone ? '' : '→' ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <p class="muted" style="font-size:12px;margin:12px 2px 0">Everything else is reachable from the menu at the top — you never need to type a web address.</p>
</div>
<?php endif; ?>
