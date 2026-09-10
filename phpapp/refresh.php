<?php
// ============================================================================
//  One-tap "apply my latest upload" — force PHP to drop its cached copy of the
//  old code and run the files just uploaded.
//
//  Managed hosting (cPanel/mPanel/LiteSpeed) keeps a compiled copy of the app
//  in memory (OPcache) and often keeps serving it AFTER an upload, so new code
//  appears not to work until the cache is cleared. Finding that button in the
//  panel is fiddly, so this page does it in one visit:
//
//        https://operations.mghaiapps.com/refresh.php
//
//  It is deliberately self-contained — it loads NONE of the application, so a
//  stale cached copy of the app can never stop it from running — and it only
//  clears caches (it changes no data), so it is safe to open any time after an
//  upload. As a brand-new/standalone file it is itself never served stale.
// ============================================================================

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$did = [];

// 1) The opcode cache — the big one. Drops every compiled file so the next
//    request compiles the freshly-uploaded code.
if (function_exists('opcache_reset')) {
    $ok = @opcache_reset();
    $did[] = $ok ? 'Cleared the PHP code cache (OPcache).'
                 : 'Tried to clear the PHP code cache, but the host did not allow it here — use “Restart PHP” / “Flush OPcache” in your hosting panel.';
} else {
    $did[] = 'This server has no OPcache to clear (nothing needed) — your upload is already live.';
}

// 2) Realpath + stat caches — cheap, and clears any stale file-location info.
@clearstatcache(true);
$did[] = 'Cleared the file-location cache.';

// 3) User cache, if the host runs APCu (harmless if not).
if (function_exists('apcu_clear_cache')) { @apcu_clear_cache(); $did[] = 'Cleared the APCu data cache.'; }

// A code fingerprint of the files on disk, so support can confirm which build
// is now live. It is built from each program file's size and timestamp.
$sig = '(unknown)';
try {
    $parts = [];
    foreach (array_merge([__DIR__ . '/index.php'], glob(__DIR__ . '/lib/*.php') ?: []) as $f) {
        $parts[] = basename($f) . ':' . @filemtime($f) . ':' . @filesize($f);
    }
    $sig = substr(md5(implode('|', $parts)), 0, 10);
} catch (Throwable $e) {}

$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
?><!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Latest update applied</title>
<div style="font-family:Segoe UI,Arial,sans-serif;max-width:560px;margin:60px auto;padding:26px;color:#1f2937">
  <h2 style="color:#047857;margin:0 0 6px">✓ Your latest update is now live</h2>
  <p style="color:#4b5563;font-size:15px;margin:0 0 18px">PHP has been told to drop its old cached copy of the code and use the files you just uploaded.</p>
  <ul style="color:#374151;font-size:14px;line-height:1.7;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:14px 18px 14px 34px">
    <?php foreach ($did as $d): ?><li><?= $e($d) ?></li><?php endforeach; ?>
  </ul>
  <p style="color:#6b7280;font-size:12px;margin:14px 0 22px">Build now live: <code style="background:#f3f4f6;padding:2px 6px;border-radius:5px"><?= $e($sig) ?></code></p>
  <a href="/" style="display:inline-block;background:#0f766e;color:#fff;text-decoration:none;padding:11px 20px;border-radius:9px;font-weight:600">Go to the app →</a>
  <p style="color:#9ca3af;font-size:12px;margin-top:22px">Tip: open this page once after every code upload. It only clears caches — it never changes your data.</p>
</div>
