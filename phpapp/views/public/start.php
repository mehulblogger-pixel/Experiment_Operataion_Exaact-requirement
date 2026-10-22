<?php
// Public "Start your free trial" — a company signs itself up, proves its e-mail
// with a code, and lands in its own workspace (lib/selfserve.php). Standalone
// (pre-login), same look as /get-started: phone-first, one clear action per
// step, large touch targets, ≥16px text, light and dark.
$appName = function_exists('app_name') ? app_name() : 'Operations';
$S    = (array) ($GLOBALS['__ss'] ?? []);
$step = (string) ($S['step'] ?? 'form');
$err  = (string) ($S['err'] ?? '');
$msg  = (string) ($S['msg'] ?? '');
$p    = (array) ($S['post'] ?? []);
$cfg  = (array) ($S['cfg'] ?? []);
$csrf = (string) ($S['csrf'] ?? '');
$days = (int) ($cfg['trial_days'] ?? 30);
$v = function ($k) use ($p) { return e((string) ($p[$k] ?? '')); };
$minPw = function_exists('pwd_min_len') ? pwd_min_len() : 10;
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($appName) ?> — Start your free trial</title>
<style>
  :root{--teal:#0f7d7d;--teal-d:#0a5c5c;--gold:#c98a1e;--gold-soft:#f7edd8;--ink:#11201f;--muted:#566766;--line:#e3ebea;--bg:#f4f8f8;--card:#fff;--soft:#eaf4f3;--ok:#2f7a34;--err:#b3261e}
  @media(prefers-color-scheme:dark){:root{--teal:#3fb5ad;--teal-d:#2f9a92;--gold:#e0b45f;--gold-soft:#2a2413;--ink:#eaf2f1;--muted:#9fb2b1;--line:#22302f;--bg:#0b1312;--card:#111c1b;--soft:#132120;--ok:#8fd19a;--err:#f2b8b5}}
  *{box-sizing:border-box} html{-webkit-text-size-adjust:100%}
  body{margin:0;font:16px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:var(--ink)}
  a{color:var(--teal);text-decoration:none}
  .bar{position:sticky;top:0;z-index:20;display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--card);border-bottom:1px solid var(--line);padding:12px 18px}
  .brand{display:flex;align-items:center;gap:9px;font-weight:800;font-size:18px;color:var(--ink)}
  .brand .dot{width:26px;height:26px;border-radius:8px;background:linear-gradient(135deg,var(--teal),var(--teal-d));display:inline-block}
  .bar .si{font-size:14.5px;color:var(--teal);font-weight:600;padding:8px 12px;border-radius:9px}
  .hero{max-width:820px;margin:0 auto;padding:32px 20px 4px;text-align:center}
  .ribbon{display:inline-flex;align-items:center;gap:7px;background:var(--gold-soft);color:var(--gold);font-weight:700;font-size:13px;border-radius:999px;padding:6px 14px;margin-bottom:14px}
  .hero h1{font-size:clamp(26px,5vw,40px);line-height:1.08;letter-spacing:-.02em;margin:0 0 10px;text-wrap:balance}
  .hero p.lead{font-size:clamp(16px,2.3vw,18px);color:var(--muted);max-width:56ch;margin:0 auto}
  .points{display:flex;flex-wrap:wrap;justify-content:center;gap:8px 18px;margin:14px auto 0;color:var(--muted);font-size:14.5px}
  .points span::before{content:"✓ ";color:var(--ok);font-weight:800}
  .wrap{max-width:560px;margin:0 auto;padding:20px 20px 60px}
  .card{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:24px 22px;box-shadow:0 10px 30px rgba(15,125,125,.06)}
  .fld{margin-bottom:15px}
  .fld label{display:block;font-size:14px;font-weight:700;margin-bottom:6px}
  .fld .hint{font-weight:400;color:var(--muted)}
  .fld input{width:100%;min-height:52px;border:2px solid var(--line);border-radius:12px;padding:13px 15px;font-size:16.5px;background:var(--card);color:var(--ink)}
  .fld input:focus{border-color:var(--teal);outline:none}
  .code{letter-spacing:.4em;text-align:center;font-size:24px!important;font-weight:800}
  .agree{display:flex;gap:10px;align-items:flex-start;font-size:14px;color:var(--muted);margin:4px 0 18px}
  .agree input{width:22px;height:22px;margin-top:1px;flex:0 0 auto;accent-color:var(--teal)}
  .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;width:100%;min-height:54px;border-radius:13px;padding:15px 24px;font-size:17px;font-weight:800;border:2px solid var(--teal);background:var(--teal);color:#fff;cursor:pointer}
  .btn:hover{background:var(--teal-d)}
  .link{background:none;border:none;color:var(--teal);font-weight:700;font-size:15px;cursor:pointer;padding:10px;min-height:44px}
  .row{display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-top:10px}
  .banner{border-radius:12px;padding:13px 15px;font-size:15px;margin-bottom:18px}
  .banner.err{background:color-mix(in srgb,var(--err) 14%,var(--card));border:1px solid var(--err);color:var(--err)}
  .banner.ok{background:color-mix(in srgb,var(--ok) 14%,var(--card));border:1px solid var(--ok);color:var(--ok)}
  .hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
  .foot{text-align:center;color:var(--muted);font-size:13.5px;margin-top:16px}
  :focus-visible{outline:3px solid var(--gold);outline-offset:2px}
</style></head><body>

<header class="bar">
  <a class="brand" href="/"><span class="dot"></span><?= e($appName) ?></a>
  <a class="si" href="/login">Sign in</a>
</header>

<div class="hero">
  <div class="ribbon">◆ <?= $days ?> days free · no card needed</div>
  <h1>Start your free trial</h1>
  <p class="lead">Your own private workspace with every module — ready in a minute. Choose and pay for only the modules you want when you are ready.</p>
  <div class="points"><span>All modules for <?= $days ?> days</span><span>Your data stays yours</span><span>Pay online when you choose a plan</span></div>
</div>

<div class="wrap">
  <div class="card">
  <?php if ($err !== ''): ?><div class="banner err" role="alert"><?= e($err) ?></div><?php endif; ?>
  <?php if ($msg !== ''): ?><div class="banner ok" role="status"><?= e($msg) ?></div><?php endif; ?>

  <?php if ($step === 'code'): ?>
    <form method="post" action="/start" autocomplete="off">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="do" value="verify">
      <div class="fld">
        <label for="code">Enter the 6-digit code from your e-mail</label>
        <input id="code" class="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,8}" maxlength="8" required autofocus>
      </div>
      <button class="btn" type="submit">Create my workspace →</button>
    </form>
    <div class="row">
      <form method="post" action="/start"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="do" value="resend"><button class="link" type="submit">Send a new code</button></form>
      <form method="post" action="/start"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="do" value="restart"><button class="link" type="submit">Use a different e-mail</button></form>
    </div>
    <p class="foot">The code can take a minute to arrive. Check your spam folder too.</p>
  <?php else: ?>
    <form method="post" action="/start" autocomplete="on" novalidate>
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="do" value="start">
      <div class="hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="fld"><label for="company">Company name</label>
        <input id="company" name="company" value="<?= $v('company') ?>" maxlength="150" autocomplete="organization" required></div>
      <div class="fld"><label for="owner_name">Your name</label>
        <input id="owner_name" name="owner_name" value="<?= $v('owner_name') ?>" maxlength="120" autocomplete="name" required></div>
      <div class="fld"><label for="email">Work e-mail <span class="hint">— this is how you sign in</span></label>
        <input id="email" name="email" type="email" value="<?= $v('email') ?>" maxlength="190" autocomplete="email" inputmode="email" required></div>
      <div class="fld"><label for="phone">Mobile <span class="hint">— optional</span></label>
        <input id="phone" name="phone" type="tel" value="<?= $v('phone') ?>" maxlength="20" autocomplete="tel" inputmode="tel"></div>
      <div class="fld"><label for="password">Choose a password <span class="hint">— at least <?= (int) $minPw ?> characters</span></label>
        <input id="password" name="password" type="password" minlength="<?= (int) $minPw ?>" autocomplete="new-password" required></div>
      <label class="agree"><input type="checkbox" name="agree" value="1" <?= !empty($p['agree']) ? 'checked' : '' ?> required>
        <span>I agree: the trial is free for <?= $days ?> days. After that my workspace becomes view-only until a plan is chosen.
          A trial workspace nobody signs in to for <?= (int) ($cfg['delete_days'] ?? 90) ?> days is deleted with its data,
          after an e-mail warning every <?= (int) ($cfg['notice_days'] ?? 15) ?> days.</span></label>
      <button class="btn" type="submit">Start my free trial →</button>
    </form>
    <p class="foot">Already have a workspace? <a href="/login">Sign in</a></p>
  <?php endif; ?>
  </div>
</div>
</body></html>
