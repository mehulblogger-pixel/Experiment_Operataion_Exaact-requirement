<?php
// Connect B1 — public organisation onboarding. An organisation applies for
// itself and sees the modules it will get. Standalone page (pre-login).
$done = $GLOBALS['__join_done'] ?? false; $err = $GLOBALS['__join_err'] ?? '';
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
  .card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:20px;margin-bottom:14px}
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
</style></head><body>
<div class="top"><?= e($appName) ?></div>
<div class="wrap">
<?php if ($done): ?>
  <div class="card" style="text-align:center;padding:40px 22px">
    <div style="font-size:44px">🎉</div>
    <h1 style="margin:12px 0 6px">You're on the list</h1>
    <p class="muted">Thanks — your organisation has been registered and is awaiting approval. We'll be in touch at the e-mail you gave to finish setting you up.</p>
  </div>
<?php else: ?>
  <h1>Join as an organisation</h1>
  <p class="muted" style="margin:0 0 16px">One platform for the whole technical-manpower flow — post work, find people, run it to invoicing. Tell us who you are and we'll set you up with the right tools.</p>
  <?php if ($err): ?><div class="msg err"><?= e($err) ?></div><?php endif; ?>
  <form method="post" action="/join">
    <div class="card">
      <label>Organisation name</label><input name="name" required autofocus>
      <label>What kind of organisation?</label>
      <div class="types">
        <?php $first=true; foreach ($types as $k=>$t): if ($k==='FREELANCER') continue; /* freelancers use /pro */ ?>
          <label>
            <input type="radio" name="org_type" value="<?= e($k) ?>" <?= $first?'checked':'' ?>>
            <span><strong><?= e($t['label']) ?></strong>
              <?php $mods = function_exists('connect_org_type_modules') ? connect_org_type_modules($k) : []; ?>
              <div style="margin-top:4px"><?php foreach ($mods as $m) echo '<span class="chip">'.e($modLabel[$m]??$m).'</span>'; ?></div>
            </span>
          </label>
        <?php $first=false; endforeach; ?>
      </div>
    </div>
    <div class="card">
      <label>Your name</label><input name="contact_name">
      <label>Work e-mail</label><input type="email" name="contact_email" required>
      <label>Mobile</label><input name="contact_mobile">
    </div>
    <button class="btn" type="submit">Register my organisation</button>
    <p class="muted" style="text-align:center;margin-top:14px;font-size:13.5px">An individual professional? <a href="/pro/register">List yourself here →</a></p>
  </form>
<?php endif; ?>
</div>
</body></html>
