<?php
// ============================================================================
//  Reminder runner — call this once or twice a day from MilesWeb cPanel → Cron.
//
//  Example cron lines (cPanel → Cron Jobs):
//     Report-due reminder,   daily 07:00:   php /home/USER/public_html/cron.php
//     Overdue-closure check,  daily 18:00:   php /home/USER/public_html/cron.php
//
//  Or via URL (if your host runs cron by URL), protect it with a token:
//     https://operations.mghaiapps.com/cron.php?key=YOUR_SECRET
//  and set the environment variable CRON_KEY=YOUR_SECRET.
// ============================================================================

require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/ops.php';

// When invoked over HTTP, require a matching key so strangers can't trigger it.
if (PHP_SAPI !== 'cli') {
    $need = getenv('CRON_KEY');
    if ($need && ($_GET['key'] ?? '') !== $need) {
        http_response_code(403);
        echo "Forbidden";
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
}

// Make sure the schema exists (safe if already there).
try { boot(); } catch (Throwable $e) { echo "Boot error: " . $e->getMessage() . "\n"; exit(1); }

$sent = ops_run_reminders();
echo "Reminders processed. Emails queued/sent: $sent\n";

// Flip recruitment placement fees from provisional to confirmed once the
// agency's free-replacement guarantee window has passed.
confirm_lapsed_placement_fees();
echo "Placement-fee guarantees checked.\n";
