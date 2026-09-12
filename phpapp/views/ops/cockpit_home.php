<?php
// Company Setup Cockpit — the single front door to configuring the workspace.
// Reads everything from the orchestrator (lib/setup_cockpit.php); it links to the
// canonical engines and never stores a second copy of their data.
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
// status → [label, css class] — colour is NEVER the only signal (§52): the word is always shown.
$stat = function ($s) {
    static $m = [
        'complete'       => ['Complete',        'ck-ok'],
        'configured'     => ['Configured',      'ck-ok'],
        'in_progress'    => ['In progress',     'ck-warn'],
        'needs_attention'=> ['Needs attention', 'ck-bad'],
        'not_started'    => ['Not started',     'ck-idle'],
    ];
    return $m[$s] ?? [ucfirst(str_replace('_', ' ', $s)), 'ck-idle'];
};
$readiness = (int) ($readiness ?? 0);
$warns = array_values(array_filter($health ?? [], fn($h) => $h['level'] === 'warn'));
?>
<style>
  .ck{max-width:900px}
  .ck .crumbs{margin-bottom:6px}
  .ck-hero{display:flex;gap:18px;align-items:center;flex-wrap:wrap;background:var(--card,#fff);
    border:1px solid var(--line,#e5e7eb);border-radius:16px;padding:18px 20px;margin:10px 0 18px}
  .ck-ring{--v:0;width:84px;height:84px;border-radius:50%;flex:none;display:grid;place-items:center;
    background:conic-gradient(#16a34a calc(var(--v)*1%), #e5e7eb 0);}
  .ck-ring span{width:64px;height:64px;border-radius:50%;background:var(--card,#fff);display:grid;place-items:center;
    font-weight:800;font-size:18px}
  .ck-hero h1{margin:0;font-size:22px}
  .ck-hero p{margin:4px 0 0;color:#6b7280;font-size:14px}
  .ck-search{display:flex;gap:8px;margin:0 0 18px;flex-wrap:wrap}
  .ck-search input{flex:1 1 220px}
  .ck-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px}
  .ck-card{display:block;text-decoration:none;color:inherit;background:var(--card,#fff);
    border:1px solid var(--line,#e5e7eb);border-radius:14px;padding:14px 16px}
  .ck-card:hover{border-color:#94a3b8}
  .ck-card .top{display:flex;justify-content:space-between;align-items:flex-start;gap:8px}
  .ck-card .ic{font-size:20px}
  .ck-card h3{margin:8px 0 2px;font-size:15px}
  .ck-card .d{color:#6b7280;font-size:13px;margin:0}
  .ck-pill{font-size:11.5px;font-weight:700;padding:3px 9px;border-radius:999px;white-space:nowrap;
    border:1px solid transparent;display:inline-flex;align-items:center;gap:5px}
  .ck-pill::before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor;opacity:.9}
  .ck-ok{background:#e7f6ee;color:#137a4b;border-color:#bfe0cd}
  .ck-warn{background:#fdf0d9;color:#a25c00;border-color:#ecd6a8}
  .ck-bad{background:#fde7e7;color:#b42318;border-color:#f0c2c0}
  .ck-idle{background:#eef1f5;color:#5c6b80;border-color:#dbe2ec}
  .ck-sec{margin:22px 0 10px;font-size:13px;letter-spacing:.04em;text-transform:uppercase;color:#8494a8;font-weight:700}
  .ck-list{display:flex;flex-direction:column;gap:8px}
  .ck-item{display:flex;gap:10px;align-items:flex-start;background:var(--card,#fff);border:1px solid var(--line,#e5e7eb);
    border-radius:12px;padding:11px 14px}
  .ck-item .mk{flex:none;width:20px;text-align:center}
  .ck-item .bd{flex:1}
  .ck-item .bd a{font-size:13px}
  .ck-qa{display:flex;flex-wrap:wrap;gap:8px}
  @media (max-width:520px){ .ck-grid{grid-template-columns:1fr} .ck-hero{padding:16px} }
</style>

<div class="ck">
  <div class="crumbs"><a href="/">Home</a> › Workspace setup</div>

  <div class="ck-hero">
    <div class="ck-ring" style="--v:<?= $readiness ?>"><span><?= $readiness ?>%</span></div>
    <div style="flex:1;min-width:200px">
      <h1>Set up your workspace</h1>
      <p>Everything you need to configure your company, in one place. This is a guide, not a gate — you can use the app while you finish.</p>
    </div>
  </div>

  <form class="ck-search" method="get" action="/workspace/setup">
    <input class="form-control" type="search" name="q" value="<?= $e($q ?? '') ?>" placeholder="Search settings — e.g. candidate, dropdown, role…" aria-label="Search configuration">
    <button class="btn" type="submit">Search</button>
  </form>
  <?php if (($q ?? '') !== ''): ?>
    <div class="ck-list" style="margin-bottom:18px">
      <?php if (!$search): ?><p class="muted">No settings matched “<?= $e($q) ?>”.</p><?php endif; ?>
      <?php foreach ($search as $r): ?>
        <a class="ck-item" href="<?= $e($r['route']) ?>" style="text-decoration:none;color:inherit">
          <span class="mk">🔎</span><span class="bd"><strong><?= $e($r['label']) ?></strong></span><span class="ck-pill ck-idle">Open</span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($warns): ?>
    <div class="ck-sec">Needs your attention</div>
    <div class="ck-list">
      <?php foreach ($warns as $h): ?>
        <div class="ck-item">
          <span class="mk">⚠️</span>
          <span class="bd"><?= $e($h['msg']) ?></span>
          <?php if (!empty($h['fix'])): ?><a class="btn xs" href="<?= $e($h['fix']) ?>">Fix</a><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="ck-sec">Configuration areas</div>
  <div class="ck-grid">
    <?php foreach ($sections as $s): [$lbl, $cls] = $stat($s['status']); ?>
      <a class="ck-card" href="<?= $e($s['route']) ?>">
        <div class="top">
          <span class="ic"><?= $e($s['icon']) ?></span>
          <span class="ck-pill <?= $cls ?>"><?= $e($lbl) ?></span>
        </div>
        <h3><?= $e($s['label']) ?><?php if (!empty($s['note'])): ?> <span class="muted" style="font-weight:400;font-size:12px">· <?= $e($s['note']) ?></span><?php endif; ?></h3>
        <p class="d"><?= $e($s['desc']) ?></p>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="ck-sec">Get started</div>
  <div class="ck-list">
    <?php foreach ($checklist as $c): ?>
      <div class="ck-item">
        <span class="mk"><?= !empty($c['done']) ? '✅' : '⬜' ?></span>
        <span class="bd" style="<?= !empty($c['done']) ? 'color:#6b7280' : '' ?>"><?= $e($c['label']) ?></span>
        <?php if (empty($c['done']) && !empty($c['fix'])): ?><a class="btn xs ghost" href="<?= $e($c['fix']) ?>">Do this</a><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="ck-sec">Quick actions</div>
  <div class="ck-qa">
    <a class="btn ghost" href="/workspace/setup/forms">+ Add a form field</a>
    <a class="btn ghost" href="/ai-forms">✨ Build forms with AI</a>
    <a class="btn ghost" href="/masters">+ Add a dropdown value</a>
    <a class="btn ghost" href="/users">+ Add a user</a>
    <a class="btn ghost" href="/hierarchy?tab=offices">+ Add a branch</a>
    <a class="btn ghost" href="/workspace/setup/modules">Configure features</a>
    <a class="btn ghost" href="/terminology">Change wording</a>
  </div>
</div>
