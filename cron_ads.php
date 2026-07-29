<?php
// ---------------------------------------------------------------------------
//  Ads Pro sync — safe to run every few minutes
//
//  WHY THIS IS A SEPARATE FILE FROM cron.php. cron.php is a DAILY job: it sends
//  report reminders, expiry notices and the MIS digest. Most of those have no
//  per-day guard, so running cron.php every fifteen minutes would post the same
//  reminder e-mails ninety-six times a day. The sync, by contrast, is worth
//  running often — a lead that arrives at 09:05 should be workable at 09:20, not
//  tomorrow.
//
//  So: cron.php stays daily, this runs frequently, and the two do not overlap in
//  anything that sends an e-mail.
//
//  It is safe to run twice at once and safe to run when nothing has changed:
//  every push is guarded by a payload hash and every pull is matched on the Ads
//  Pro record id. Running it when the link is not configured does nothing at all.
//
//  Cron (cPanel → Cron Jobs), every 15 minutes:
//      */15 * * * *  php /home/USER/public_html/cron_ads.php
//
//  Or by URL, protected by the same key cron.php uses:
//      https://your-host/cron_ads.php?key=YOUR_SECRET
// ---------------------------------------------------------------------------

// boot() runs every module's migration, so every module has to be loaded — a
// list trimmed to "what the sync needs" dies on the first constant boot()
// touches, which is exactly how this file failed the first time it was run.
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/ops.php';
require __DIR__ . '/lib/lookups.php';
require __DIR__ . '/lib/access.php';
require __DIR__ . '/lib/terms.php';
require __DIR__ . '/lib/compose.php';
require __DIR__ . '/lib/crm.php';
require __DIR__ . '/lib/pdf.php';
require __DIR__ . '/lib/ai.php';
require __DIR__ . '/lib/workforce.php';
require __DIR__ . '/lib/orgadmin.php';
require __DIR__ . '/lib/contracts.php';
require __DIR__ . '/lib/idems.php';
require __DIR__ . '/lib/costing.php';
require __DIR__ . '/lib/joblock.php';
require __DIR__ . '/lib/capa.php';
require __DIR__ . '/lib/ncr.php';
require __DIR__ . '/lib/activity.php';
require __DIR__ . '/lib/licence.php';
require __DIR__ . '/lib/leads.php';
require __DIR__ . '/lib/books.php';
require __DIR__ . '/lib/adspro.php';
require __DIR__ . '/lib/adssync.php';
require __DIR__ . '/lib/licencekey.php';
require __DIR__ . '/lib/licencesync.php';

// Same protection as cron.php: over the web it needs the key; from the command
// line there is no request to protect.
if (PHP_SAPI !== 'cli') {
    $need = getenv('CRON_KEY');
    if ($need && ($_GET['key'] ?? '') !== $need) {
        http_response_code(403);
        echo "Forbidden";
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
}

try { boot(); } catch (Throwable $e) { echo "Boot error: " . $e->getMessage() . "\n"; exit(1); }

// ---------------------------------------------------------------------------
//  Licence check-in FIRST, and before the Ads Pro test below, because a
//  customer who has never heard of Ads Pro still has to renew automatically.
//  licsync_checkin() decides for itself whether it is due — daily normally,
//  every quarter of an hour once an expiry is close — so calling it on every
//  run costs one comparison and no network traffic.
//
//  A failure here is a non-event. The installation carries on with the licence
//  it already holds; an outage at MGH must never stop a customer invoicing.
// ---------------------------------------------------------------------------
if (function_exists('licsync_checkin')) {
    $lr = licsync_checkin(false);
    if (!empty($lr['changed'])) echo "licence: " . $lr['msg'] . "\n";
    elseif (empty($lr['ok']))   echo "licence: " . $lr['msg'] . "\n";
}

if (!function_exists('ads_on') || !ads_on()) {
    echo "Ads Pro is not connected — nothing more to do.\n";
    exit;
}

$r   = ads_sync_now();
$out = $r['push'] ?? [];
$in  = $r['pull'] ?? [];

echo "out: " . (!empty($out['err']) ? 'FAILED — ' . $out['err'] : ($out['msg'] ?? 'nothing waiting')) . "\n";
echo "in:  " . (!empty($in['err'])  ? 'FAILED — ' . $in['err']  : ($in['msg']  ?? 'nothing')) . "\n";

// Spend once a day is enough — ad platforms restate the last few days anyway, and
// the return report re-reads what is stored rather than fetching on demand.
$today = date('Y-m-d');
if ((string)setting_get('adspro_spend_day', '') !== $today) {
    $sp = ads_import_spend(90);
    if (empty($sp['err'])) {
        setting_set('adspro_spend_day', $today);
        echo "spend: " . $sp['msg'] . "\n";
    } else {
        echo "spend: FAILED — " . $sp['err'] . "\n";
    }
} else {
    echo "spend: already refreshed today\n";
}
