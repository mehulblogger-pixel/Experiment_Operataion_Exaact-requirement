<?php
// Connect B1 — public organisation onboarding. An organisation applies for
// itself and sees the modules it will get. Standalone page (pre-login).
$done = $GLOBALS['__join_done'] ?? false; $err = $GLOBALS['__join_err'] ?? '';
$acct = $GLOBALS['__join_acct'] ?? null;
$types = $GLOBALS['__join_types'] ?? [];
$appName = function_exists('app_name') ? app_name() : 'MGH Inspect Connect';
$modLabel = ['operations'=>'Operations','admin'=>'Admin','sales'=>'Sales/CRM','reporting'=>'Reporting','money'=>'Money','hr'=>'People/Hiring','connect'=>'Marketplace','pro'=>'Self-service'];
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Join as an organisation · <?= e($appName) ?></title>
<style>
  :root{--teal:#0f7d7d;--teal-d:#0a5c5c;--ink:#12201f;--muted:#5b6b6a;--line:#e3ebea;--bg:#f5f8f8;--card:#fff;--ok:#0f7d5a;--okbg:#e7f5ef;--bad:#9a2a2a}
  @media(prefers-color-scheme:dark){:root{--ink:#eaf2f1;--muted:#9fb2b1;--line:#22302f;--bg:#0c1413;--card:#111b1a;--okbg:#0f2a22}}
  *{box-sizing:border-box} body{margin:0;font:16px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:var(--ink)}
  .top{background:linear-gradient(135deg,var(--teal),var(--teal-d));color:#fff;padding:16px 20px;font-weight:700}
  .wrap{max-width:620px;margin:0 auto;padding:24px 18px 60px}
  .card{position:relative;background:var(--card);border:1px solid var(--line);border-radius:16px;padding:20px;margin-bottom:14px}
  .step{position:absolute;top:-11px;left:16px;width:24px;height:24px;border-radius:999px;background:var(--teal);color:#fff;font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 4px rgba(0,0,0,.15)}
  h1{font-size:25px;letter-spacing:-.02em;margin:0 0 4px}
  label{display:block;font-size:13px;color:var(--muted);margin:12px 0 4px}
  input,select{width:100%;padding:12px;border:1px solid var(--line);border-radius:11px;font-size:16px;background:var(--card);color:inherit}
  .btn{display:inline-block;background:var(--teal);color:#fff;border:0;border-radius:11px;padding:13px 18px;font-size:15px;font-weight:600;cursor:pointer;width:100%}
  .msg{padding:11px 14px;border-radius:11px;margin-bottom:12px;font-size:14px}
  .msg.err{background:#f6e6e6;color:var(--bad)} .msg.ok{background:var(--okbg);color:var(--ok)}
  .chip{display:inline-block;margin:3px;padding:5px 11px;border-radius:999px;background:rgba(15,125,125,.08);border:1px solid rgba(15,125,125,.2);font-size:12.5px}
  .muted{color:var(--muted)}
  .types{display:grid;gap:8px;margin-top:6px}
  .types label{display:flex;gap:8px;align-items:start;border:1px solid var(--line);border-radius:11px;padding:11px;cursor:pointer;margin:0}
  .types input{width:auto;margin-top:3px}
  .caps{display:grid;grid-template-columns:1fr 1fr;gap:6px}
  @media(max-width:520px){.caps{grid-template-columns:1fr}}
  .caps .cap{display:flex;gap:9px;align-items:center;border:1px solid var(--line);border-radius:10px;padding:11px 12px;cursor:pointer;margin:0;font-size:14px;transition:border-color .12s,background .12s}
  .caps .cap:hover{border-color:var(--teal)}
  .caps .cap input{width:18px;height:18px;margin:0;flex:none;accent-color:var(--teal)}
  .caps .cap:has(input:checked){border-color:var(--teal);background:rgba(15,125,125,.06)}
  .caps .cap span{color:var(--ink)}
</style></head><body>
<div class="top"><?= e($appName) ?></div>
<div class="wrap">
<?php if ($done): ?>
  <div class="card" style="text-align:center;padding:40px 22px">
    <div style="font-size:44px">✅</div>
    <h1 style="margin:12px 0 6px">Your account is ready</h1>
    <p class="muted" style="margin:0 0 4px">You can sign in now with</p>
    <p style="font-size:17px;font-weight:600;margin:0 0 18px"><?= e($acct['email'] ?? '') ?></p>
    <a class="btn" href="<?= e($acct['login_url'] ?? '/portal/login') ?>" style="max-width:280px;margin:0 auto">Sign in →</a>
    <p class="muted" style="margin:16px 0 0;font-size:13px">
      <?= !empty($acct['is_agency'])
          ? 'As an agency you can manage your own bench under “My bench”, put your people forward to open jobs, and review vouchers — all from your portal.'
          : 'Post work, shortlist and award people, and review their vouchers — all from your portal.' ?>
    </p>
  </div>
<?php else: ?>
  <h1>Set up your organisation</h1>
  <p class="muted" style="margin:0 0 16px">Three quick things and you're in — no jargon, no forms to hunt for. Just tell us what you do and we'll switch on the right tools for you.</p>
  <?php if ($err): ?><div class="msg err"><?= e($err) ?></div><?php endif; ?>
  <form method="post" action="/join">
    <div class="card">
      <div class="step">1</div>
      <label style="margin-top:0">Your organisation's name</label>
      <input name="name" required autofocus placeholder="e.g. Zephyr Inspection Services">
    </div>

    <?php $capCat = $GLOBALS['__join_cap_catalog'] ?? []; $capGroups = $GLOBALS['__join_cap_groups'] ?? []; $capsOn = $GLOBALS['__join_caps_posted'] ?? []; if ($capCat): ?>
    <div class="card">
      <div class="step">2</div>
      <label style="font-size:16px;color:var(--ink);font-weight:700;margin-top:0">What does your organisation do?</label>
      <p class="muted" style="margin:0 0 4px;font-size:13.5px"><strong>Tick everything that fits — most companies do more than one.</strong> This is all we need to set you up; you can change it any time.</p>
      <?php foreach ($capGroups as $g): ?>
        <div style="margin-top:12px"><div class="muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;font-weight:600;margin-bottom:6px"><?= e($g) ?></div>
          <div class="caps">
          <?php foreach ($capCat as $code=>$c): if ($c['group']!==$g) continue; $on=in_array($code,$capsOn,true); ?>
            <label class="cap"><input type="checkbox" name="caps[]" value="<?= e($code) ?>" <?= $on?'checked':'' ?>><span><?= e($c['label']) ?></span></label>
          <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <details style="margin-top:14px">
        <summary class="muted" style="cursor:pointer;font-size:13px">Prefer to pick a single main type instead? (optional)</summary>
        <div class="types" style="margin-top:8px">
          <?php
            $want = strtoupper((string)($_GET['type'] ?? ''));
            if (!isset($types[$want]) || $want === 'FREELANCER') $want = '';
          ?>
          <label>
            <input type="radio" name="org_type" value="" <?= $want===''?'checked':'' ?>>
            <span><strong>Set me up automatically</strong> <span class="muted">— based on what I ticked above (recommended)</span></span>
          </label>
          <?php foreach ($types as $k=>$t): if ($k==='FREELANCER') continue; ?>
          <label>
            <input type="radio" name="org_type" value="<?= e($k) ?>" <?= $k===$want?'checked':'' ?>>
            <span><strong><?= e($t['label']) ?></strong>
              <?php $mods = function_exists('connect_org_type_modules') ? connect_org_type_modules($k) : []; ?>
              <div style="margin-top:4px"><?php foreach ($mods as $m) echo '<span class="chip">'.e($modLabel[$m]??$m).'</span>'; ?></div>
            </span>
          </label>
          <?php endforeach; ?>
        </div>
      </details>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="step">3</div>
      <label style="margin-top:0">Your name</label><input name="contact_name">
      <label>Work e-mail</label><input type="email" name="contact_email" required placeholder="you@company.com">
      <label>Mobile</label><input name="contact_mobile">
      <label>Choose a password</label><input type="password" name="password" minlength="8" required placeholder="at least 8 characters">
    </div>
    <button class="btn" type="submit">Create my account →</button>
    <p class="muted" style="text-align:center;margin-top:14px;font-size:13.5px">An individual professional? <a href="/pro/register">List yourself here →</a> &nbsp;·&nbsp; <a href="/connect">All options</a></p>
  </form>
<?php endif; ?>
</div>
</body></html>
