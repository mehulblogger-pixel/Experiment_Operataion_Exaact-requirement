<?php
// Connect K0+ — the hiring home for a marketplace client. Search & post at the
// top, then the live state of their hiring. Read-only over the marketplace +
// privacy engines; every number links to the screen that acts on it.
$home = $home ?? []; $client = $client ?? '';
$c = $home['counts'] ?? ['open_reqs'=>0,'awaiting'=>0,'awarded'=>0,'total_reqs'=>0];
$open = $home['open_reqs'] ?? []; $saved = $home['saved_searches'] ?? [];
$reqs = $home['contact_requests'] ?? []; $pool = (int)($home['pool_size'] ?? 0);
?>
<style>
  .hh-hero{background:linear-gradient(135deg,#0f7d7d,#0a5c5c);color:#fff;border-radius:16px;padding:24px 26px;margin:6px 0 20px}
  .hh-hero h2{margin:0 0 6px;font-size:23px;letter-spacing:-.01em;color:#fff}
  .hh-hero p{margin:0 0 16px;color:#dcefee;font-size:14.5px;max-width:60ch}
  .hh-btns{display:flex;gap:10px;flex-wrap:wrap}
  .hh-btn{display:inline-block;border-radius:11px;padding:12px 18px;font-size:15px;font-weight:700;text-decoration:none}
  .hh-btn.p{background:#fff;color:#0a5c5c}.hh-btn.g{background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.5)}
  .hh-stats{display:grid;gap:12px;grid-template-columns:repeat(3,1fr);margin-bottom:20px}
  @media(max-width:620px){.hh-stats{grid-template-columns:1fr}}
  .hh-stat{background:var(--card,#fff);border:1px solid var(--line,#e3ebea);border-radius:13px;padding:14px 16px;text-decoration:none;color:inherit;display:block}
  .hh-stat .n{font-size:26px;font-weight:800;letter-spacing:-.02em;font-variant-numeric:tabular-nums}
  .hh-stat .l{color:var(--muted,#5b6b6a);font-size:13px}
  .hh-stat.warn .n{color:#8a6d0b}
  .hh-sec{background:var(--card,#fff);border:1px solid var(--line,#e3ebea);border-radius:14px;padding:16px 18px;margin-bottom:16px}
  .hh-sec h3{margin:0 0 3px;font-size:16px}
  .hh-sec .sub{color:var(--muted,#5b6b6a);font-size:12.5px;margin:0 0 12px}
  .hh-row{display:flex;justify-content:space-between;gap:12px;align-items:center;border:1px solid var(--line,#eef1f0);border-radius:11px;padding:11px 13px;margin-bottom:8px}
  .hh-chip{display:inline-flex;align-items:center;gap:6px;background:#eef5f4;border:1px solid var(--line,#e3ebea);border-radius:999px;padding:6px 12px;font-size:13px;text-decoration:none;color:inherit;margin:0 6px 6px 0}
  .hh-chip:hover{background:#e2efee}
  .hh-pill{display:inline-block;padding:3px 9px;border-radius:999px;font-size:12px;font-weight:600}
  .hh-pill.ok{background:#e7f5ef;color:#0f7d5a}.hh-pill.warn{background:#fbf3d8;color:#8a6d0b}.hh-pill.muted{background:#eceff1;color:#5b6b6a}
</style>

<?php if (function_exists('connect_client_dash_render') && function_exists('portal_user') && portal_user()) connect_client_dash_render(portal_partner_id(), portal_user()); ?>
<div class="hh-hero">
  <h2>Find &amp; hire technical manpower</h2>
  <p>Search <?= number_format($pool) ?> verified professionals across the pool — by role, skill, certificate or equipment — or post exactly what you need and let qualified people apply.</p>
  <div class="hh-btns">
    <a class="hh-btn p" href="/portal/find">🔍 Search the pool</a>
    <a class="hh-btn g" href="/portal/hire">➕ Post a requirement</a>
    <a class="hh-btn g" href="/portal/roster">★ My bench<?= (int)($home['bench_count'] ?? 0) > 0 ? ' ('.(int)$home['bench_count'].')' : '' ?></a>
  </div>
</div>

<div class="hh-stats">
  <a class="hh-stat" href="/portal/hire"><div class="n"><?= (int)$c['open_reqs'] ?></div><div class="l">Open requirements</div></a>
  <a class="hh-stat <?= (int)$c['awaiting']>0?'warn':'' ?>" href="<?= $open ? '/portal/hire-req?id='.(int)$open[0]['id'] : '/portal/hire' ?>"><div class="n"><?= (int)$c['awaiting'] ?></div><div class="l">Applicants awaiting your decision</div></a>
  <a class="hh-stat" href="/portal/hire"><div class="n"><?= (int)$c['awarded'] ?></div><div class="l">Awarded to date</div></a>
</div>

<?php // ---- Saved searches ---- ?>
<div class="hh-sec">
  <h3>Saved searches</h3>
  <p class="sub">Re-run a search you use often in one tap. Save one from the Find-manpower screen.</p>
  <?php if (!$saved): ?>
    <p class="muted" style="margin:0;font-size:13.5px">No saved searches yet. <a href="/portal/find">Search the pool →</a> and tap “Save this search”.</p>
  <?php else: foreach ($saved as $s): ?>
    <span style="display:inline-flex;align-items:center;margin:0 6px 6px 0">
      <a class="hh-chip" style="margin:0" href="/portal/find?<?= e($s['qs']) ?>">🔎 <?= e($s['label']) ?></a>
      <form method="post" action="/portal/hiring" style="margin:0 0 0 -2px"><input type="hidden" name="action" value="del_search"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button type="submit" title="Remove" style="border:0;background:none;color:var(--muted);cursor:pointer;font-size:15px;padding:2px 6px">×</button></form>
    </span>
  <?php endforeach; endif; ?>
</div>

<?php // ---- Open requirements + who is waiting ---- ?>
<div class="hh-sec">
  <h3>Your open requirements</h3>
  <p class="sub">Jobs you have posted that are still taking applications.</p>
  <?php if (!$open): ?>
    <p class="muted" style="margin:0;font-size:13.5px">Nothing open right now. <a href="/portal/hire">Post a requirement →</a></p>
  <?php else: foreach ($open as $r): ?>
    <div class="hh-row">
      <div>
        <strong><?= e($r['ref_code']) ?></strong> — <?= e($r['title']) ?>
        <div class="muted" style="font-size:12px"><?= e($r['location'] ?? '') ?> <?= !empty($r['location'])?'·':'' ?> <?= (int)($r['_apps'] ?? 0) ?> applicant<?= (int)($r['_apps'] ?? 0)===1?'':'s' ?></div>
      </div>
      <div style="display:flex;gap:10px;align-items:center">
        <?php if ((int)($r['_pending'] ?? 0) > 0): ?><span class="hh-pill warn"><?= (int)$r['_pending'] ?> to review</span><?php else: ?><span class="hh-pill muted">No new</span><?php endif; ?>
        <a class="btn sec" href="/portal/hire-req?id=<?= (int)$r['id'] ?>" style="padding:6px 12px;font-size:12.5px">Open</a>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<?php // ---- Contact requests the client has sent ---- ?>
<?php if ($reqs): ?>
<div class="hh-sec">
  <h3>Contact requests</h3>
  <p class="sub">Professionals unlock their phone and e-mail when they approve you (or once you engage them).</p>
  <?php foreach ($reqs as $rq): $granted = strtoupper((string)$rq['status'])==='GRANTED'; ?>
    <div class="hh-row">
      <div><strong><?= e($rq['pro_name'] ?: 'Professional') ?></strong>
        <?php if ($rq['headline']): ?><div class="muted" style="font-size:12px"><?= e($rq['headline']) ?></div><?php endif; ?>
      </div>
      <?php if ($granted): ?><span class="hh-pill ok">✓ Contact shared — <a href="/portal/find" style="color:inherit;text-decoration:underline">view</a></span>
      <?php else: ?><span class="hh-pill warn">⏳ Awaiting approval</span><?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
