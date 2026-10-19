<?php
// Connect — freelancer portal chrome (self-contained, phone-first, Deep Teal).
$appName = function_exists('app_name') ? app_name() : 'MGH Inspect Connect';
$me = $me ?? (function_exists('connect_pro_user') ? connect_pro_user() : null);
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Professionals · <?= e($appName) ?></title>
<style>
  :root{--teal:#0f7d7d;--teal-d:#0a5c5c;--ink:#12201f;--muted:#5b6b6a;--line:#e3ebea;--bg:#f5f8f8;--card:#fff;--gold:#c9a227;--ok:#0f7d5a;--okbg:#e7f5ef;--bad:#9a2a2a}
  @media(prefers-color-scheme:dark){:root{--ink:#eaf2f1;--muted:#9fb2b1;--line:#22302f;--bg:#0c1413;--card:#111b1a;--okbg:#0f2a22}}
  *{box-sizing:border-box}
  body{margin:0;font:16px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:var(--ink)}
  .top{background:linear-gradient(135deg,var(--teal),var(--teal-d));color:#fff;padding:14px 18px;display:flex;justify-content:space-between;align-items:center}
  /* 24px minimum hit area (WCAG 2.5.8) on the controls that stand alone --
     a prose link inside a sentence is exempt and is left as it is. */
  /* No opacity here. The teal itself gives white text 4.94:1, which clears
     WCAG 1.4.3 -- but opacity:.9 pulled it down to 4.33:1 and failed. The brand
     colour needed no change; only the fade had to go. */
  .top a{color:#fff;text-decoration:none;font-size:14px;
         display:inline-flex;align-items:center;min-height:26px;padding:0 4px}
  .card p > a:only-child,.card > a:only-child,.wrap > p > a:only-child{
         display:inline-flex;align-items:center;min-height:26px}
  /* the small remove control on a chip: 14px of glyph is not a target */
  .chip button,.xchip button,button[title=Remove]{min-width:26px;min-height:26px;
         display:inline-flex;align-items:center;justify-content:center;
         background:none;border:0;color:var(--muted);cursor:pointer;font-size:14px}
  .top .brand{font-weight:700;letter-spacing:-.01em}
  .wrap{max-width:640px;margin:0 auto;padding:20px 16px 60px}
  .nav{display:flex;gap:4px;flex-wrap:wrap;max-width:640px;margin:10px auto 0;padding:0 16px}
  .nav a{padding:7px 12px;border-radius:8px;text-decoration:none;color:var(--muted);font-size:14px}
  .nav a.on{background:rgba(15,125,125,.12);color:var(--ink);font-weight:600}
  .card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:18px;margin-bottom:14px}
  h1{font-size:24px;letter-spacing:-.02em;margin:0 0 4px}
  h2{font-size:13px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:0 0 10px}
  label{display:block;font-size:13px;color:var(--muted);margin:12px 0 4px}
  input,select,textarea{width:100%;padding:12px;border:1px solid var(--line);border-radius:11px;font-size:16px;background:var(--card);color:inherit}
  .btn{display:inline-block;background:var(--teal);color:#fff;border:0;border-radius:11px;padding:12px 18px;font-size:15px;font-weight:600;cursor:pointer;text-decoration:none}
  .btn.sec{background:transparent;color:var(--teal);border:1px solid var(--teal)}
  .msg{padding:11px 14px;border-radius:11px;margin-bottom:12px;font-size:14px}
  .msg.err{background:#f6e6e6;color:var(--bad)} .msg.ok{background:var(--okbg);color:var(--ok)}
  .chip{display:inline-flex;align-items:center;gap:6px;margin:3px;padding:8px 12px;border-radius:999px;border:1px solid var(--line);font-size:14px}
  .chip input{width:auto} .grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  @media(max-width:520px){.grid2{grid-template-columns:1fr}}
  .muted{color:var(--muted)}
</style></head><body>
<div class="top">
  <span class="brand"><?= e($appName) ?> · Professionals</span>
  <?php if ($me): ?><a href="/pro/logout">Sign out</a><?php else: ?><a href="/pro/login">Sign in</a><?php endif; ?>
</div>
<?php if ($me): $here = trim((string)($_SERVER['REQUEST_URI'] ?? ''), '/'); $here = strtok($here, '?'); ?>
<div class="nav">
  <a class="<?= $here==='pro'?'on':'' ?>" href="/pro">Home</a>
  <a class="<?= $here==='pro/jobs'?'on':'' ?>" href="/pro/jobs">Open jobs</a>
  <a class="<?= $here==='pro/applications'?'on':'' ?>" href="/pro/applications">My applications</a>
  <a class="<?= $here==='pro/bookings'?'on':'' ?>" href="/pro/bookings">My bookings</a>
  <a class="<?= ($here==='pro/vouchers'||$here==='pro/voucher')?'on':'' ?>" href="/pro/vouchers">My vouchers</a>
  <a class="<?= $here==='pro/profile'?'on':'' ?>" href="/pro/profile">My profile</a>
  <a class="<?= $here==='pro/reputation'?'on':'' ?>" href="/pro/reputation">My reputation</a>
  <?php $mUn = (function_exists('connect_msg_pro_unread') && $me) ? connect_msg_pro_unread((int)$me['id']) : 0; ?>
  <a class="<?= $here==='pro/messages'?'on':'' ?>" href="/pro/messages">Messages<?= $mUn > 0 ? ' (' . (int)$mUn . ')' : '' ?></a>
  <a class="<?= $here==='pro/verify'?'on':'' ?>" href="/pro/verify">Get verified</a>
</div>
<?php endif; ?>
<div class="wrap">
<?php
// The one-shot message after a redirect. Ten places in lib/connect_pro.php
// write it -- payment, subscription, credit pack, rating report -- and until
// this block existed NONE of them reached the professional: the text went into
// the session and stayed there. role=alert so it is announced, not just seen,
// and the tag so a failed payment does not arrive wearing the success colour.
if (!empty($_SESSION['pro_flash']) && function_exists('connect_pro_flash_take')):
    [$pfMsg, $pfTag] = connect_pro_flash_take();
    if ($pfMsg !== ''): ?>
  <div class="msg <?= $pfTag === 'err' ? 'err' : 'ok' ?>" role="alert"><?= e($pfMsg) ?></div>
<?php endif; endif; ?>
