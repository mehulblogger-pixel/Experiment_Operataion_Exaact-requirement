<?php $lg = logo_html(); ?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(app_name()) ?> — Sign in</title>
<link rel="stylesheet" href="/assets/css/app.css">
<?= theme_style_tag() ?>
<style>
  html,body{height:100%}
  body{margin:0;display:flex;min-height:100%;background:var(--bg)}
  .stage{display:grid;grid-template-columns:1.12fr .88fr;width:100%;min-height:100vh}
  .brand-side{position:relative;overflow:hidden;color:#fff;padding:52px 54px;
    display:flex;flex-direction:column;justify-content:space-between;
    background:radial-gradient(1100px 560px at 10% -10%, color-mix(in srgb,var(--accent) 45%, transparent) 0%, transparent 55%),
               linear-gradient(155deg, var(--brand) 0%, color-mix(in srgb,var(--brand) 55%, #05070f) 60%, color-mix(in srgb,var(--brand) 30%, #05070f) 100%)}
  .grid-motif{position:absolute;inset:0;opacity:.13;pointer-events:none;
    background-image:linear-gradient(#ffffff33 1px,transparent 1px),linear-gradient(90deg,#ffffff33 1px,transparent 1px);
    background-size:40px 40px;-webkit-mask-image:radial-gradient(circle at 30% 20%,#000 0%,transparent 70%);mask-image:radial-gradient(circle at 30% 20%,#000 0%,transparent 70%)}
  .glow{position:absolute;width:340px;height:340px;border-radius:50%;filter:blur(64px);opacity:.5;pointer-events:none;background:var(--accent)}
  .glow.a{right:-70px;top:120px}.glow.b{left:-90px;bottom:-50px;opacity:.4}
  .wm{display:flex;align-items:center;gap:12px;position:relative;z-index:2}
  .wm .mk{width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.25);
    display:grid;place-items:center;font-weight:900;font-size:19px}
  .wm b{font-size:17px;letter-spacing:.01em}.wm span{display:block;font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:rgba(255,255,255,.7);margin-top:2px}
  .pitch{position:relative;z-index:2}
  .pitch h1{font-size:clamp(28px,3.2vw,42px);line-height:1.09;letter-spacing:-.02em;margin:0 0 14px;color:#fff;text-wrap:balance;max-width:15ch}
  .pitch h1 em{font-style:normal;color:color-mix(in srgb,var(--accent) 70%, #fff)}
  .pitch p{font-size:16px;line-height:1.6;color:rgba(255,255,255,.82);margin:0 0 24px;max-width:40ch}
  .chips{display:flex;flex-wrap:wrap;gap:9px}
  .chip{font-size:12.5px;font-weight:600;padding:7px 13px;border-radius:999px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);color:#eef3ff}
  .foot{position:relative;z-index:2;font-size:12px;color:rgba(255,255,255,.62)}
  .foot b{color:rgba(255,255,255,.85);font-weight:600}

  .auth-side{display:flex;align-items:center;justify-content:center;padding:40px 30px}
  .card{width:100%;max-width:392px;background:var(--card);border:1px solid var(--line);border-radius:18px;box-shadow:var(--shadow);padding:34px 32px}
  .card .hi{font-size:13px;color:var(--brand);font-weight:700}
  .card h2{font-size:24px;letter-spacing:-.01em;margin:6px 0 4px;color:var(--ink)}
  .card .s{color:var(--muted);font-size:14px;margin:0 0 22px}
  .fld{margin-bottom:15px}
  .fld label{font-size:12.5px;font-weight:600;color:var(--muted);display:block;margin-bottom:6px}
  .fld .wrap{position:relative}
  .fld input{width:100%;font-size:15px;padding:12px 14px;border-radius:11px;border:1px solid var(--field-line);background:var(--field);color:var(--ink)}
  .fld input:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px color-mix(in srgb,var(--brand) 22%,transparent);background:var(--card)}
  .eye{position:absolute;right:10px;top:50%;transform:translateY(-50%);border:none;background:none;color:var(--muted);cursor:pointer;font-size:11px;font-weight:700;padding:6px;letter-spacing:.03em}
  .go{width:100%;margin-top:6px;background-image:linear-gradient(180deg,rgba(255,255,255,.18),transparent);background-color:var(--brand);color:#fff;border:none;padding:13px;border-radius:11px;font-size:15px;font-weight:700;cursor:pointer;box-shadow:0 8px 20px color-mix(in srgb,var(--brand) 34%,transparent)}
  .go:hover{filter:brightness(1.06)}.go:active{transform:translateY(1px)}
  .help{font-size:12.5px;color:var(--muted);text-align:center;line-height:1.6;margin-top:20px}
  .ver{margin-top:20px;text-align:center;font-size:11.5px;color:var(--muted)}
  .err{background:#fee2e2;color:#991b1b;border-radius:10px;padding:10px 13px;font-size:13.5px;margin-bottom:16px}
  @media (max-width:900px){.stage{grid-template-columns:1fr}.brand-side{padding:38px 28px 30px}.pitch h1{font-size:28px}.auth-side{padding:30px 20px 56px}}
  @media (prefers-reduced-motion:reduce){*{transition:none!important}}
</style>
</head><body>
<div class="stage">
  <aside class="brand-side">
    <div class="grid-motif"></div><div class="glow a"></div><div class="glow b"></div>
    <div class="wm"><?php if ($lg): ?><?= $lg ?><?php else: ?><div class="mk">◈</div><?php endif; ?>
      <div><b><?= e(app_name()) ?></b><span>Operations &amp; Finance</span></div></div>
    <div class="pitch">
      <h1>Run every office. <em>See every rupee.</em></h1>
      <p>Calls, jobs, inspector vouchers and profitability — one system for independent inspection offices, each with its own targets and P&amp;L.</p>
      <div class="chips"><span class="chip">Call &amp; Job register</span><span class="chip">Inspector vouchers</span><span class="chip">Contract profitability</span><span class="chip">Live dashboards</span></div>
    </div>
    <div class="foot"><b>Peer offices, one platform</b> · your role decides what you see</div>
  </aside>

  <main class="auth-side">
    <div class="card">
      <?php if (($stage ?? 'password') === 'code'): ?>
      <?php // The password was right. Nobody is signed in yet — the session only
            // knows who is half-way through, and drops that after five minutes. ?>
      <div class="hi">One more step</div>
      <h2>Enter your code</h2>
      <p class="s">Open your authenticator app and type the six digits showing for
        <strong><?= e(app_name()) ?></strong>. If your phone is not with you, use one of your recovery codes instead.</p>
      <?php if (!empty($error)): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="/login">
        <input type="hidden" name="stage" value="code">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div class="fld"><label for="c">Six-digit code</label>
          <div class="wrap"><input id="c" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
            autofocus required placeholder="000000" style="letter-spacing:.34em;font-size:20px;text-align:center"></div></div>
        <button class="go" type="submit">Verify →</button>
      </form>
      <p class="help">Lost the phone and the recovery codes? Your <strong>office administrator</strong> can turn the second step off for your account.</p>
      <?php else: ?>
      <div class="hi">Welcome back</div>
      <h2>Sign in to continue</h2>
      <p class="s">Enter your credentials to reach your office dashboard.</p>
      <?php if (!empty($error)): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="/login">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div class="fld"><label for="u">Username</label>
          <div class="wrap"><input id="u" name="username" type="text" autocomplete="username" autofocus required placeholder="e.g. m.prajapati"></div></div>
        <div class="fld"><label for="p">Password</label>
          <div class="wrap"><input id="p" name="password" type="password" autocomplete="current-password" required placeholder="••••••••">
            <button class="eye" id="eye" type="button" aria-label="Show password">SHOW</button></div></div>
        <button class="go" type="submit">Sign in →</button>
      </form>
      <p class="help">No account? Ask your <strong>office administrator</strong> to create one.</p>
      <?php endif; ?>
      <div class="ver"><?= e(app_name()) ?> · v<?= e(defined('APP_VERSION') ? APP_VERSION : '1.0') ?></div>
    </div>
  </main>
</div>
<script>
  var e=document.getElementById('eye'),p=document.getElementById('p');
  e&&e.addEventListener('click',function(){var s=p.type==='password';p.type=s?'text':'password';e.textContent=s?'HIDE':'SHOW';});
</script>
</body></html>
