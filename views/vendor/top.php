<?php
// The vendor's own shell. Deliberately NOT the staff layout and NOT the client
// portal shell either: a vendor is a third distinct audience. It can only link
// to /vendor addresses, and it names itself the VENDOR portal so nobody confuses
// it with the client one.
$nav = isset($nav) ? (bool)$nav : true;
$here = trim((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
$links = ['vendor' => 'Overview'];
if (vcan('reports')) $links['vendor/reports'] = 'Reports';
if (vcan('issues') && function_exists('cvp_vendor_issue_links')) $links['vendor/issues'] = 'Nonconformities';
if (function_exists('cvp_notify_count')) {
    $__vn = cvp_notify_count('VENDOR', cvp_vendor_id());
    $links['vendor/alerts'] = 'Alerts' . ($__vn ? ' (' . $__vn . ')' : '');
}
if (function_exists('cvp_ai_available') && cvp_ai_available()) $links['vendor/assistant'] = 'Assistant';
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(app_name()) ?><?= isset($pageTitle) ? ' — ' . e($pageTitle) : ' — Vendor portal' ?></title>
<link rel="stylesheet" href="/assets/css/app.css">
<?= theme_style_tag() ?>
<style>
  body{margin:0;background:var(--bg);color:var(--ink)}
  .pwrap{max-width:1000px;margin:0 auto;padding:0 20px 70px}
  .phead{display:flex;flex-wrap:wrap;gap:12px;align-items:baseline;justify-content:space-between;
    padding:22px 0 14px;border-bottom:1px solid var(--line)}
  .phead .who{font-size:13px;color:var(--muted)}
  .phead h1{margin:0;font-size:clamp(19px,2.4vw,25px);letter-spacing:-.02em}
  .pnav{display:flex;flex-wrap:wrap;gap:4px;margin:14px 0 26px}
  .pnav a{padding:7px 13px;border-radius:8px;text-decoration:none;color:var(--muted);font-size:14px}
  .pnav a.on{background:var(--panel,rgba(0,0,0,.05));color:var(--ink);font-weight:600}
  .ptitle{margin:0 0 6px;font-size:20px;letter-spacing:-.01em}
  .plead{margin:0 0 22px;color:var(--muted);font-size:14px;line-height:1.6;max-width:62ch}
  .ptable{width:100%;border-collapse:collapse;margin:0 0 24px;font-size:14px}
  .ptable th{text-align:left;padding:9px 10px 9px 0;border-bottom:1px solid var(--line);
    font-size:12.5px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)}
  .ptable td{padding:11px 10px 11px 0;border-bottom:1px solid var(--line);vertical-align:top}
  .ptable tr:last-child td{border-bottom:0}
  .pscroll{overflow-x:auto}
  .ptiles{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(165px,1fr));margin:0 0 28px}
  .ptile{border:1px solid var(--line);border-radius:12px;padding:15px 16px}
  .ptile .n{font-size:26px;font-weight:600;letter-spacing:-.02em}
  .ptile .l{font-size:12.5px;color:var(--muted);margin-top:3px}
  .pnote{font-size:13px;color:var(--muted);line-height:1.6}
  .pcard{border:1px solid var(--line);border-radius:12px;padding:20px 22px;margin:0 0 22px}
  .pform{max-width:430px;margin:34px auto 0}
  .pform label{display:block;font-size:13px;color:var(--muted);margin:14px 0 5px}
  .pfoot{margin-top:40px;padding-top:15px;border-top:1px solid var(--line);font-size:12.5px;color:var(--muted)}
  .pfoot a{color:inherit}
  .pmsg{border:1px solid var(--line);border-left-width:5px;border-radius:10px;padding:13px 16px;margin:0 0 20px;font-size:14px}
  .pmsg.err{border-left-color:var(--bad)}
  .pmsg.ok{border-left-color:var(--ok)}
  .pempty{padding:26px 0;color:var(--muted);font-size:14.5px}
</style>
</head><body>
<div class="pwrap">
  <div class="phead">
    <h1><?= e(app_name()) ?> · Vendor portal</h1>
    <?php if ($nav && $u): ?>
      <div class="who"><?= e($u['name'] ?: $u['email']) ?> · <?= e(cvp_vendor_name()) ?>
        · <a href="/vendor/logout">Sign out</a></div>
    <?php endif; ?>
  </div>
  <?php if ($nav && $u): ?>
    <nav class="pnav">
      <?php foreach ($links as $path => $label): ?>
        <a class="<?= $here === $path ? 'on' : '' ?>" href="/<?= e($path) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>
  <?php if (!empty($_SESSION['vendor_flash'])): ?>
    <div class="pmsg ok"><?= e($_SESSION['vendor_flash']) ?></div>
    <?php unset($_SESSION['vendor_flash']); ?>
  <?php endif; ?>
