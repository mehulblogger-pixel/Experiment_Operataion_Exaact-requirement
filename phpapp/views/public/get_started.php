<?php
// Public "Start your workspace" — a new inspection company applies for its own
// operations workspace. Standalone (pre-login). Built to the UI/UX blueprint:
// mobile-first, one clear primary action, large touch targets, teal/white/gold,
// ≥16px. The request lands as PENDING for the Super-Admin to approve.
$appName = function_exists('app_name') ? app_name() : 'Operations';
$done = !empty($GLOBALS['__ws_done']);
$err  = (string)($GLOBALS['__ws_err'] ?? '');
$open = !empty($GLOBALS['__ws_open']);
$p    = (array)($GLOBALS['__ws_post'] ?? []);
$v = function($k) use ($p) { return e((string)($p[$k] ?? '')); };
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($appName) ?> — Start your workspace</title>
<style>
  :root{--teal:#0f7d7d;--teal-d:#0a5c5c;--gold:#c98a1e;--gold-soft:#f7edd8;--ink:#11201f;--muted:#566766;--line:#e3ebea;--bg:#f4f8f8;--card:#fff;--soft:#eaf4f3;--ok:#2f7a34;--err:#b3261e}
  @media(prefers-color-scheme:dark){:root{--teal:#3fb5ad;--teal-d:#2f9a92;--gold:#e0b45f;--gold-soft:#2a2413;--ink:#eaf2f1;--muted:#9fb2b1;--line:#22302f;--bg:#0b1312;--card:#111c1b;--soft:#132120;--err:#f2b8b5}}
  *{box-sizing:border-box} html{-webkit-text-size-adjust:100%}
  body{margin:0;font:16px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:var(--ink)}
  a{color:var(--teal);text-decoration:none}
  .bar{position:sticky;top:0;z-index:20;display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--card);border-bottom:1px solid var(--line);padding:12px 18px}
  .brand{display:flex;align-items:center;gap:9px;font-weight:800;font-size:18px;letter-spacing:-.01em;color:var(--ink)}
  .brand .dot{width:26px;height:26px;border-radius:8px;background:linear-gradient(135deg,var(--teal),var(--teal-d));display:inline-block}
  .bar .si{font-size:14.5px;color:var(--teal);font-weight:600;padding:8px 12px;border-radius:9px}
  .bar .si:hover{background:var(--soft)}
  .hero{max-width:820px;margin:0 auto;padding:36px 20px 4px;text-align:center}
  .ribbon{display:inline-flex;align-items:center;gap:7px;background:var(--gold-soft);color:var(--gold);font-weight:700;font-size:13px;border-radius:999px;padding:6px 14px;margin-bottom:16px}
  .hero h1{font-size:clamp(27px,5vw,42px);line-height:1.07;letter-spacing:-.02em;margin:0 0 12px;text-wrap:balance}
  .hero h1 .hl{color:var(--teal)}
  .hero p.lead{font-size:clamp(16px,2.3vw,18px);color:var(--muted);max-width:56ch;margin:0 auto 6px}
  .wrap{max-width:640px;margin:0 auto;padding:20px 20px 60px}
  .card{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:26px 24px;box-shadow:0 10px 30px rgba(15,125,125,.06)}
  .fld{margin-bottom:16px}
  .fld label{display:block;font-size:14px;font-weight:700;margin-bottom:6px}
  .fld .hint{font-weight:400;color:var(--muted)}
  .fld input,.fld textarea{width:100%;min-height:52px;border:2px solid var(--line);border-radius:12px;padding:13px 15px;font-size:16.5px;background:var(--card);color:var(--ink)}
  .fld textarea{min-height:88px;resize:vertical}
  .fld input:focus,.fld textarea:focus{border-color:var(--teal);outline:none}
  .sub-row{display:flex;align-items:center;border:2px solid var(--line);border-radius:12px;overflow:hidden}
  .sub-row input{border:none;min-height:48px}
  .sub-row .suf{padding:0 14px;color:var(--muted);font-size:15px;white-space:nowrap;background:var(--soft);align-self:stretch;display:flex;align-items:center}
  .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;width:100%;min-height:54px;border-radius:13px;padding:15px 24px;font-size:17px;font-weight:800;border:2px solid var(--teal);background:var(--teal);color:#fff;cursor:pointer}
  .btn:hover{background:var(--teal-d)}
  .note{color:var(--muted);font-size:13.5px;margin:14px 2px 0;text-align:center}
  .banner{border-radius:12px;padding:13px 15px;font-size:15px;margin-bottom:18px}
  .banner.err{background:color-mix(in srgb,var(--err) 14%,var(--card));border:1px solid var(--err);color:var(--err)}
  .banner.ok{background:color-mix(in srgb,var(--ok) 14%,var(--card));border:1px solid var(--ok);color:var(--ok)}
  .done{text-align:center;padding:12px 4px}
  .done .big{font-size:52px;line-height:1}
  .done h2{margin:12px 0 6px;font-size:24px}
  .done p{color:var(--muted);margin:0 auto 18px;max-width:44ch}
  .steps{list-style:none;padding:0;margin:22px 0 0;display:grid;gap:12px}
  .steps li{display:flex;gap:12px;align-items:flex-start;background:var(--soft);border-radius:12px;padding:13px 15px;font-size:14.5px}
  .steps .n{flex:0 0 auto;width:26px;height:26px;border-radius:8px;background:var(--teal);color:#fff;font-weight:800;display:flex;align-items:center;justify-content:center;font-size:14px}
  .foot{text-align:center;color:var(--muted);font-size:13px;margin-top:30px}
  :focus-visible{outline:3px solid var(--gold);outline-offset:2px}
</style></head><body>

<header class="bar">
  <a class="brand" href="/"><span class="dot"></span><?= e($appName) ?></a>
  <a class="si" href="/login">Staff sign in</a>
</header>

<div class="hero">
  <div class="ribbon">◆ Free during launch</div>
  <h1>Run your inspection company on <span class="hl"><?= e($appName) ?></span></h1>
  <p class="lead">Your own private workspace — calls, jobs, inspectors, reports and finances, one system. Tell us about your company and we'll set it up for you.</p>
</div>

<div class="wrap">
  <div class="card">
  <?php if ($done): ?>
    <div class="done">
      <div class="big">✅</div>
      <h2>Request received</h2>
      <p>Thanks. Our team will review your details and set up your workspace. You'll hear from us at the e-mail you gave, usually within one working day.</p>
      <ol class="steps">
        <li><span class="n">1</span><div><b>We review</b> your company details.</div></li>
        <li><span class="n">2</span><div><b>We create your workspace</b> — your own private web address and admin login.</div></li>
        <li><span class="n">3</span><div><b>You sign in</b> and add your team. That's it.</div></li>
      </ol>
      <p class="note"><a href="/">← Back to home</a></p>
    </div>
  <?php elseif (!$open): ?>
    <div class="done">
      <div class="big">✉️</div>
      <h2>We set up workspaces personally</h2>
      <p>Online self-registration isn't open just yet. To get your company started on <?= e($appName) ?>, please reach out and we'll set your workspace up for you.</p>
      <p class="note"><a href="/login">Already have a workspace? Staff sign in →</a></p>
    </div>
  <?php else: ?>
    <?php if ($err !== ''): ?><div class="banner err"><?= e($err) ?></div><?php endif; ?>
    <form method="post" action="/get-started" autocomplete="on">
      <div class="fld">
        <label for="company">Company name</label>
        <input id="company" name="company" required value="<?= $v('company') ?>" placeholder="e.g. Acme Inspection Services">
      </div>
      <div class="fld">
        <label for="contact_name">Your name</label>
        <input id="contact_name" name="contact_name" value="<?= $v('contact_name') ?>" placeholder="e.g. Ramesh Patel">
      </div>
      <div class="fld">
        <label for="email">Work e-mail <span class="hint">— we'll send your workspace details here</span></label>
        <input id="email" name="email" type="email" required value="<?= $v('email') ?>" placeholder="you@yourcompany.com">
      </div>
      <div class="fld">
        <label for="phone">Phone <span class="hint">— optional</span></label>
        <input id="phone" name="phone" type="tel" value="<?= $v('phone') ?>" placeholder="+91 ...">
      </div>
      <div class="fld">
        <label for="sub">Preferred workspace name <span class="hint">— optional, letters &amp; numbers</span></label>
        <div class="sub-row">
          <input id="sub" name="sub" value="<?= $v('sub') ?>" placeholder="acme" pattern="[a-zA-Z0-9-]*">
          <span class="suf">.<?= e(function_exists('tenant_base_domain') && tenant_base_domain() ? tenant_base_domain() : 'operations.mghaiapps.com') ?></span>
        </div>
      </div>
      <div class="fld">
        <label for="note">Anything we should know? <span class="hint">— optional</span></label>
        <textarea id="note" name="note" placeholder="Your city, team size, what you inspect…"><?= $v('note') ?></textarea>
      </div>
      <button class="btn" type="submit">Request my workspace →</button>
      <p class="note">No payment now — the platform is free during launch. We'll review and set you up personally.</p>
    </form>
  <?php endif; ?>
  </div>
  <div class="foot">Already using <?= e($appName) ?>? <a href="/login">Staff sign in</a></div>
</div>
</body></html>
