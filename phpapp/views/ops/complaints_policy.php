<?php
// The one page in this application that anybody can read without signing in.
//
// ISO/IEC 17020 §7.5.1 requires the description of the complaints-handling
// process to be available to any interested party. A page behind a password is
// not available to an interested party, so this one sits in front of the gate.
// It is read-only by design: it shows what the company published and how to
// reach them, and it accepts nothing back. See the note at the top of
// lib/complaints.php for why there is no public submission form yet.
$text = cmp_policy_text();
$g    = grievance_officer();
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(app_name()) ?> — Complaints and appeals</title>
<link rel="stylesheet" href="/assets/css/app.css">
<?= theme_style_tag() ?>
<style>
  body{margin:0;background:var(--bg);color:var(--ink)}
  .wrap{max-width:760px;margin:0 auto;padding:48px 22px 72px}
  .hd{border-bottom:1px solid var(--line);padding-bottom:18px;margin-bottom:26px}
  .hd h1{margin:0 0 6px;font-size:clamp(24px,3vw,32px);letter-spacing:-.02em}
  .hd p{margin:0;color:var(--muted);font-size:14px}
  .body{white-space:pre-wrap;line-height:1.62;font-size:15px}
  .contact{margin-top:34px;padding:18px 20px;border:1px solid var(--line);border-radius:12px;background:var(--soft)}
  .contact h2{margin:0 0 8px;font-size:15px;letter-spacing:.02em;text-transform:uppercase;color:var(--muted)}
  .contact p{margin:0 0 4px;font-size:15px}
  .foot{margin-top:40px;padding-top:16px;border-top:1px solid var(--line);font-size:12.5px;color:var(--muted)}
  .foot a{color:inherit}
</style>
</head><body>
<div class="wrap">
  <div class="hd">
    <h1>Complaints and appeals</h1>
    <p><?= e(app_name()) ?><?php if (function_exists('accredited_pack_on') && accredited_pack_on()): ?> · published under <?= e(accreditation_ref('complaints')) ?><?php endif; ?></p>
  </div>

  <div class="body"><?= e($text) ?></div>

  <?php if ($g['name'] || $g['email'] || $g['phone']): ?>
  <div class="contact">
    <h2>Where to send it</h2>
    <?php if ($g['name']): ?><p><strong><?= e($g['name']) ?></strong></p><?php endif; ?>
    <?php if ($g['email']): ?><p><a href="mailto:<?= e($g['email']) ?>"><?= e($g['email']) ?></a></p><?php endif; ?>
    <?php if ($g['phone']): ?><p><?= e($g['phone']) ?></p><?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="foot">
    <a href="/privacy">How we handle personal data</a> · <a href="/login">Staff sign-in</a>
  </div>
</div>
</body></html>
