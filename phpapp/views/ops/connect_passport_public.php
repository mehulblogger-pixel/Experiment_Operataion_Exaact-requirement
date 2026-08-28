<?php
// Connect K1 — the PUBLIC professional passport. Reached without an account, via
// an unguessable share link, and from the QR printed on it. It shows only what is
// safe to show the world: who this professional is, their verified credentials
// with live status, and their platform reputation. Nothing confidential.
$d = $GLOBALS['__passport'] ?? null;
$url = $GLOBALS['__passport_url'] ?? '';
$appName = function_exists('app_name') ? app_name() : 'MGH Inspect Connect';
$pill = function ($status) {
    $s = strtoupper((string)$status);
    $live = in_array($s, ['VALID', 'VERIFIED', 'CURRENT', 'ACTIVE'], true);
    $warn = in_array($s, ['EXPIRING', 'DUE', 'SOON'], true);
    $cls = $live ? 'ok' : ($warn ? 'warn' : 'bad');
    return '<span class="pill ' . $cls . '">' . e(ucfirst(strtolower((string)$status))) . '</span>';
};
$qr = ($d && $url !== '' && function_exists('qr_svg')) ? qr_svg($url, 150, 'M', 3) : '';
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $d ? e($d['name']) . ' — ' : '' ?>Professional Passport · <?= e($appName) ?></title>
<style>
  :root{--teal:#0f7d7d;--teal-d:#0a5c5c;--ink:#12201f;--muted:#5b6b6a;--line:#e3ebea;--bg:#f5f8f8;--card:#fff;--gold:#c9a227;--ok:#0f7d5a;--okbg:#e7f5ef;--warn:#8a6d0b;--warnbg:#fbf3d8;--bad:#9a2a2a;--badbg:#f6e6e6}
  @media (prefers-color-scheme:dark){:root{--ink:#eaf2f1;--muted:#9fb2b1;--line:#22302f;--bg:#0c1413;--card:#111b1a;--okbg:#0f2a22;--warnbg:#2a2410;--badbg:#2a1414}}
  *{box-sizing:border-box}
  body{margin:0;font:16px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:var(--ink)}
  .wrap{max-width:560px;margin:0 auto;padding:24px 18px 60px}
  .card{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:22px;margin-bottom:16px}
  .hero{background:linear-gradient(135deg,var(--teal),var(--teal-d));color:#fff;border:0}
  .hero h1{margin:0 0 2px;font-size:26px;letter-spacing:-.02em}
  .hero .role{opacity:.92;font-size:15px}
  .hero .skills{margin-top:10px;font-size:14px;opacity:.9}
  .verified{display:inline-flex;align-items:center;gap:6px;margin-top:14px;background:rgba(255,255,255,.16);padding:7px 12px;border-radius:999px;font-size:13.5px;font-weight:600}
  .row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--line)}
  .row:last-child{border-bottom:0}
  .row .nm{font-weight:600}
  .row .vt{font-size:12.5px;color:var(--muted)}
  .pill{display:inline-block;padding:5px 11px;border-radius:999px;font-size:12.5px;font-weight:600;white-space:nowrap}
  .pill.ok{background:var(--okbg);color:var(--ok)}
  .pill.warn{background:var(--warnbg);color:var(--warn)}
  .pill.bad{background:var(--badbg);color:var(--bad)}
  h2{font-size:13px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:0 0 10px}
  .rep{display:flex;align-items:center;gap:14px}
  .stars{font-size:30px;color:var(--gold);line-height:1}
  .rep .n{font-size:14px;color:var(--muted)}
  .qr{text-align:center;padding-top:6px}
  .qr svg{width:150px;height:150px}
  .foot{text-align:center;color:var(--muted);font-size:12.5px;margin-top:10px}
  .badge-v{color:var(--gold)}
  .empty{color:var(--muted);font-size:14px}
</style>
</head><body>
<div class="wrap">
<?php if (!$d): ?>
  <div class="card" style="text-align:center;padding:48px 22px">
    <div style="font-size:40px">🔍</div>
    <h1 style="margin:12px 0 6px;font-size:22px">Passport not found</h1>
    <p class="empty">This link is not valid, or the professional is no longer active on <?= e($appName) ?>.</p>
  </div>
<?php else: ?>
  <div class="card hero">
    <h1><?= e($d['name']) ?></h1>
    <div class="role"><?= e($d['designation'] !== '' ? $d['designation'] : $d['kind_label']) ?></div>
    <?php if ($d['skills'] !== ''): ?><div class="skills"><?= e($d['skills']) ?></div><?php endif; ?>
    <?php if ($d['verified_count'] > 0): ?>
      <div class="verified">✓ <?= (int)$d['verified_count'] ?> verified credential<?= $d['verified_count'] === 1 ? '' : 's' ?></div>
    <?php endif; ?>
  </div>

  <?php $t = $d['trust'] ?? null; if ($t): ?>
  <div class="card">
    <h2>Trust Score</h2>
    <div class="rep" style="margin-bottom:12px">
      <div class="stars" style="font-size:34px;color:var(--teal)"><?= (int)$t['score'] ?><span style="font-size:15px;color:var(--muted)"> / 1000</span></div>
      <div class="n"><span class="pill <?= $t['band_class']==='ok'?'ok':($t['band_class']==='warn'?'warn':'bad') ?>" style="background:var(--okbg)"><?= e($t['band']) ?></span>
        <?php if ((int)$t['jobs'] > 0): ?><div style="margin-top:4px">from <?= (int)$t['jobs'] ?> completed job<?= (int)$t['jobs']===1?'':'s' ?></div><?php endif; ?></div>
    </div>
    <?php foreach ($t['subs'] as $s): if (!$s['counted']) continue; ?>
      <div style="display:flex;align-items:center;gap:10px;margin:6px 0">
        <div style="width:140px;font-size:13px;color:var(--muted)"><?= e($s['label']) ?></div>
        <div style="flex:1;height:8px;background:var(--line);border-radius:6px;overflow:hidden">
          <div style="height:100%;width:<?= (int)$s['value'] ?>%;background:var(--teal)"></div></div>
        <div style="width:34px;text-align:right;font-size:12.5px;color:var(--muted)"><?= (int)$s['value'] ?></div>
      </div>
    <?php endforeach; ?>
    <?php if ($t['limited']): ?><p class="empty" style="margin:10px 0 0">Limited history so far — the score sharpens as more jobs complete on the platform.</p><?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="card">
    <h2>Verified credentials</h2>
    <?php if (!$d['credentials']): ?>
      <p class="empty">No credentials on record yet.</p>
    <?php else: foreach ($d['credentials'] as $c): ?>
      <div class="row">
        <div>
          <div class="nm"><?php if (strtoupper((string)$c['verify_status']) === 'VERIFIED'): ?><span class="badge-v">✓ </span><?php endif; ?><?= e($c['name']) ?></div>
          <?php if ($c['valid_to'] !== ''): ?><div class="vt">Valid to <?= e($c['valid_to']) ?></div><?php endif; ?>
        </div>
        <?= $pill($c['status']) ?>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <?php if ($qr !== ''): ?>
  <div class="card qr">
    <h2>Verify this passport</h2>
    <?= $qr ?>
    <div class="foot">Scan to confirm this page on <?= e($appName) ?>.</div>
  </div>
  <?php endif; ?>

  <div class="foot">Verified by <?= e($appName) ?> — the platform confirms credentials and process, never the inspection outcome.</div>
<?php endif; ?>
</div>
</body></html>
