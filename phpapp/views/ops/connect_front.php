<?php
// Connect — the ONE public front door / common landing page. A professional, a company
// or an agency creates an account, signs in, or starts posting a requirement, all from
// one page. Standalone (pre-login). Built to the UI/UX blueprint: mobile-first, one
// clear primary action, large touch targets, teal/white/gold, ≥16px, WCAG contrast.
$appName = function_exists('app_name') ? app_name() : 'Connect';
$pool = function_exists('connect_pro_pool_count') ? (int) connect_pro_pool_count() : 0;
$openJobs = 0; try { if (function_exists('cx_open_requirements')) $openJobs = count(cx_open_requirements(500)); } catch (Throwable $e) {}
$freeLaunch = true; // platform is free during launch
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($appName) ?> Connect — find technical talent &amp; work</title>
<style>
  :root{--teal:#0f7d7d;--teal-d:#0a5c5c;--gold:#c98a1e;--gold-soft:#f7edd8;--ink:#11201f;--muted:#566766;--line:#e3ebea;--bg:#f4f8f8;--card:#fff;--soft:#eaf4f3;--ok:#2f7a34}
  @media(prefers-color-scheme:dark){:root{--teal:#3fb5ad;--teal-d:#2f9a92;--gold:#e0b45f;--gold-soft:#2a2413;--ink:#eaf2f1;--muted:#9fb2b1;--line:#22302f;--bg:#0b1312;--card:#111c1b;--soft:#132120}}
  *{box-sizing:border-box} html{-webkit-text-size-adjust:100%}
  body{margin:0;font:16px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:var(--ink)}
  a{color:inherit;text-decoration:none}
  .bar{position:sticky;top:0;z-index:20;display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--card);border-bottom:1px solid var(--line);padding:12px 18px}
  .brand{display:flex;align-items:center;gap:9px;font-weight:800;font-size:18px;letter-spacing:-.01em}
  .brand .dot{width:26px;height:26px;border-radius:8px;background:linear-gradient(135deg,var(--teal),var(--teal-d));display:inline-block}
  .bar .si{font-size:14.5px;color:var(--teal);font-weight:600;padding:8px 12px;border-radius:9px}
  .bar .si:hover{background:var(--soft)}
  .hero{max-width:960px;margin:0 auto;padding:40px 20px 6px;text-align:center}
  .ribbon{display:inline-flex;align-items:center;gap:7px;background:var(--gold-soft);color:var(--gold);font-weight:700;font-size:13px;border-radius:999px;padding:6px 14px;margin-bottom:16px}
  .hero h1{font-size:clamp(29px,5.4vw,46px);line-height:1.06;letter-spacing:-.02em;margin:0 0 12px;text-wrap:balance}
  .hero h1 .hl{color:var(--teal)}
  .hero p.lead{font-size:clamp(16px,2.4vw,19px);color:var(--muted);max-width:58ch;margin:0 auto 22px}
  .ask{display:flex;gap:10px;max-width:620px;margin:0 auto;flex-wrap:wrap}
  .ask input{flex:1;min-width:240px;min-height:54px;border:2px solid var(--line);border-radius:13px;padding:14px 18px;font-size:16.5px;background:var(--card);color:var(--ink)}
  .ask input:focus{border-color:var(--teal);outline:none}
  .ask .btn{flex:0 0 auto}
  @media(max-width:560px){.ask{flex-direction:column}.ask .btn{width:100%}}
  .cta{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
  .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:52px;border-radius:13px;padding:14px 24px;font-size:16.5px;font-weight:700;border:2px solid var(--teal);cursor:pointer}
  .btn.primary{background:var(--teal);color:#fff}
  .btn.primary:hover{background:var(--teal-d)}
  .btn.ghost{background:var(--card);color:var(--teal)}
  .btn.ghost:hover{background:var(--soft)}
  .under{color:var(--muted);font-size:14px;margin:14px 0 0}
  .under a{color:var(--teal);font-weight:600}
  .trust{max-width:960px;margin:26px auto 0;padding:0 20px;display:flex;gap:10px 22px;justify-content:center;flex-wrap:wrap;color:var(--muted);font-size:14px}
  .trust b{color:var(--ink)} .trust .v{color:var(--teal);font-weight:800}
  .wrap{max-width:960px;margin:0 auto;padding:16px 20px 60px}
  .sec-label{font-size:12.5px;letter-spacing:.14em;text-transform:uppercase;color:var(--teal);font-weight:800;margin:40px 0 14px;text-align:center}
  .cards{display:grid;gap:16px;grid-template-columns:repeat(3,1fr)}
  @media(max-width:760px){.cards{grid-template-columns:1fr}}
  .card{display:flex;flex-direction:column;background:var(--card);border:1px solid var(--line);border-radius:18px;padding:24px;transition:transform .08s,box-shadow .12s}
  .card:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(15,125,125,.14)}
  .card .ic{font-size:32px} .card h3{margin:12px 0 5px;font-size:20px;letter-spacing:-.01em}
  .card p{margin:0 0 18px;color:var(--muted);font-size:14.5px;flex:1}
  .card .go{color:#fff;background:var(--teal);border-radius:11px;padding:12px 16px;font-weight:700;font-size:15px;text-align:center}
  .card.alt .go{background:transparent;color:var(--teal);border:2px solid var(--teal)}
  .steps{display:grid;gap:16px;grid-template-columns:repeat(3,1fr)}
  @media(max-width:760px){.steps{grid-template-columns:1fr}}
  .step{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:20px}
  .step .n{width:34px;height:34px;border-radius:10px;background:var(--soft);color:var(--teal);font-weight:800;display:flex;align-items:center;justify-content:center;font-size:16px}
  .step h4{margin:12px 0 4px;font-size:16.5px} .step p{margin:0;color:var(--muted);font-size:14px}
  .signin{background:var(--soft);border:1px solid var(--line);border-radius:18px;padding:24px;text-align:center}
  .signin h3{margin:0 0 4px;font-size:19px} .signin p{margin:0 0 16px;color:var(--muted);font-size:14.5px}
  .signin .btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
  .foot{text-align:center;color:var(--muted);font-size:13px;margin-top:34px}
  .foot a{color:var(--muted);text-decoration:underline}
  :focus-visible{outline:3px solid var(--gold);outline-offset:2px}
</style></head><body>

<header class="bar">
  <a class="brand" href="/connect"><span class="dot"></span><?= e($appName) ?> Connect</a>
  <a class="si" href="#signin">Sign in</a>
</header>

<section class="hero">
  <?php if ($freeLaunch): ?><span class="ribbon">🎉 Free for everyone during launch</span><?php endif; ?>
  <h1>Post a job. Find the <span class="hl">right inspector</span>. Get it done.</h1>
  <p class="lead">One marketplace for inspectors, welders, NDT technicians and the companies who need them — with verified profiles, on-platform reports and genuine reviews.</p>
  <form class="ask" method="get" action="/connect/start">
    <input name="need" maxlength="200" placeholder="What do you need inspected? e.g. CSWIP welding inspector at Dahej" aria-label="What do you need inspected?">
    <button class="btn primary" type="submit">Post a requirement →</button>
  </form>
  <p class="under">Or <a href="/pro/register">join as a professional</a> · Already have an account? <a href="#signin">Sign in →</a></p>
</section>

<div class="trust">
  <?php if ($pool > 0): ?><span><span class="v"><?= number_format($pool) ?></span> professionals listed</span><?php endif; ?>
  <?php if ($openJobs > 0): ?><span><span class="v"><?= number_format($openJobs) ?></span> open jobs</span><?php endif; ?>
  <span>✅ <b>Verified</b> profiles</span>
  <span>📄 Reports <b>on-platform</b></span>
  <span>⭐ Genuine <b>two-way reviews</b></span>
</div>

<main class="wrap">
  <div class="sec-label">Choose how you’ll use Connect</div>
  <div class="cards">
    <div class="card">
      <div class="ic">🏢</div>
      <h3>I need to hire</h3>
      <p>Post what you need, see who applies, and award the right person — with vouchers, reports and reviews, all in one place.</p>
      <a class="go" href="/join?type=COMPANY">Create a company account →</a>
    </div>
    <div class="card">
      <div class="ic">👷</div>
      <h3>I’m a professional</h3>
      <p>Build your passport once, get matched to work that fits your discipline &amp; certificates, and get hired.</p>
      <a class="go" href="/pro/register">Create a professional profile →</a>
    </div>
    <div class="card alt">
      <div class="ic">🛠️</div>
      <h3>I’m an agency</h3>
      <p>Manage your own bench of people and put them forward to open jobs across the marketplace.</p>
      <a class="go" href="/join?type=MANPOWER_AGENCY">Create an agency account →</a>
    </div>
  </div>

  <div class="sec-label">How it works</div>
  <div class="steps">
    <div class="step"><div class="n">1</div><h4>Post or list</h4><p>A company posts a requirement; a professional builds a profile. Takes a minute.</p></div>
    <div class="step"><div class="n">2</div><h4>Match &amp; agree</h4><p>The right people are matched by discipline and certificate. Chat, agree the terms.</p></div>
    <div class="step"><div class="n">3</div><h4>Work &amp; report</h4><p>The work is done, the report is delivered on-platform, and both sides leave a review.</p></div>
  </div>

  <div class="sec-label" id="signin">Already have an account</div>
  <div class="signin">
    <h3>Welcome back</h3>
    <p>Choose how you use the marketplace.</p>
    <div class="btns">
      <a class="btn primary" href="/pro/login">Sign in as a professional</a>
      <a class="btn ghost" href="/portal/login">Sign in as a company or agency</a>
    </div>
  </div>

  <p class="foot">Powered by <?= e($appName) ?>. One account, your own private data.<br>
    <a href="/login">Staff sign-in</a></p>
</main>
</body></html>
