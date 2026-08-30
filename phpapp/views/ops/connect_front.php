<?php
// Connect — the ONE public front door. A professional, a company or an agency
// creates an account or signs in, all from a single page. Standalone (pre-login).
$appName = function_exists('app_name') ? app_name() : 'Connect';
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($appName) ?> Connect — find technical talent &amp; work</title>
<style>
  :root{--teal:#0f7d7d;--teal-d:#0a5c5c;--ink:#12201f;--muted:#5b6b6a;--line:#e3ebea;--bg:#f5f8f8;--card:#fff;--soft:#eef5f4}
  @media(prefers-color-scheme:dark){:root{--ink:#eaf2f1;--muted:#9fb2b1;--line:#22302f;--bg:#0c1413;--card:#111b1a;--soft:#132120}}
  *{box-sizing:border-box} body{margin:0;font:16px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:var(--ink)}
  a{color:inherit}
  .top{background:linear-gradient(135deg,var(--teal),var(--teal-d));color:#fff;padding:16px 22px;font-weight:700;font-size:18px}
  .hero{max-width:900px;margin:0 auto;padding:46px 20px 8px;text-align:center}
  .hero h1{font-size:clamp(28px,5vw,44px);letter-spacing:-.02em;margin:0 0 10px}
  .hero p{font-size:18px;color:var(--muted);max-width:60ch;margin:0 auto}
  .wrap{max-width:900px;margin:0 auto;padding:20px 20px 64px}
  .sec-label{font-size:12.5px;letter-spacing:.14em;text-transform:uppercase;color:var(--teal);font-weight:700;margin:34px 0 12px;text-align:center}
  .cards{display:grid;gap:16px;grid-template-columns:repeat(3,1fr)}
  @media(max-width:720px){.cards{grid-template-columns:1fr}}
  .card{display:flex;flex-direction:column;background:var(--card);border:1px solid var(--line);border-radius:16px;padding:22px;text-decoration:none;transition:transform .08s,box-shadow .12s}
  .card:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(15,125,125,.12)}
  .card .ic{font-size:30px}
  .card h3{margin:12px 0 4px;font-size:19px;letter-spacing:-.01em}
  .card p{margin:0 0 16px;color:var(--muted);font-size:14px;flex:1}
  .card .go{color:var(--teal);font-weight:600;font-size:14.5px}
  .signin{background:var(--soft);border:1px solid var(--line);border-radius:16px;padding:22px;margin-top:16px;text-align:center}
  .signin h3{margin:0 0 4px;font-size:18px}
  .signin p{margin:0 0 16px;color:var(--muted);font-size:14px}
  .btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
  .btn{display:inline-block;border-radius:11px;padding:13px 20px;font-size:15px;font-weight:600;text-decoration:none;border:1px solid var(--teal)}
  .btn.primary{background:var(--teal);color:#fff}
  .btn.ghost{background:transparent;color:var(--teal)}
  .foot{text-align:center;color:var(--muted);font-size:13px;margin-top:30px}
</style></head><body>
<div class="top"><?= e($appName) ?> Connect</div>

<div class="hero">
  <h1>Find technical talent. Find technical work.</h1>
  <p>One marketplace for inspectors, welders, NDT technicians and the companies and agencies who need them. Create your account in a minute.</p>
</div>

<div class="wrap">
  <div class="sec-label">New here — create your account</div>
  <div class="cards">
    <a class="card" href="/pro/register">
      <div class="ic">👷</div>
      <h3>I'm a professional</h3>
      <p>List your skills, get discovered, and accept jobs posted on the marketplace.</p>
      <span class="go">Create a professional profile →</span>
    </a>
    <a class="card" href="/join?type=COMPANY">
      <div class="ic">🏢</div>
      <h3>I'm hiring</h3>
      <p>Post what you need, review who applies, and award the right person — with vouchers and reports.</p>
      <span class="go">Create a company account →</span>
    </a>
    <a class="card" href="/join?type=MANPOWER_AGENCY">
      <div class="ic">🛠️</div>
      <h3>I'm an agency</h3>
      <p>Manage your own bench of people and put them forward to open jobs on the marketplace.</p>
      <span class="go">Create an agency account →</span>
    </a>
  </div>

  <div class="sec-label">Already have an account — sign in</div>
  <div class="signin">
    <h3>Welcome back</h3>
    <p>Choose how you use the marketplace.</p>
    <div class="btns">
      <a class="btn primary" href="/pro/login">Sign in as a professional</a>
      <a class="btn ghost" href="/portal/login">Sign in as a company or agency</a>
    </div>
  </div>

  <p class="foot">Powered by <?= e($appName) ?>. One account, your own private data.</p>
</div>
</body></html>
