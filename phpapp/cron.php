<?php
// ============================================================================
//  Reminder runner — call this once or twice a day from the server's scheduler
//  (cPanel → Cron Jobs, a systemd timer, or Windows Task Scheduler).
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
// Contracts running out of time — one warning per contract per end date.
$expiring = function_exists('contracts_expiry_reminders') ? contracts_expiry_reminders() : 0;
echo "Reminders processed. Emails queued/sent: $sent\n";

// Flip recruitment placement fees from provisional to confirmed once the
// agency's free-replacement guarantee window has passed.
confirm_lapsed_placement_fees();
echo "Placement-fee guarantees checked.\n";

// Jobs that ran out of time: lock them, and tell the engineer, the coordinator,
// the branch manager and the administrators — once each, not every morning.
if (function_exists('joblock_sweep')) {
    $lk = joblock_sweep();
    echo "Jobs locked for late closure: {$lk['locked']} (alerts sent: {$lk['alerted']})\n";
}

// Send any due quotation follow-up e-mails (3/6/9-day, fortnight, month).
if (function_exists('crm_run_followups')) {
    $fu = crm_run_followups();
    echo "Quote follow-ups sent: $fu\n";
}

// IDEMS — escalate report approvals that have blown their SLA.
if (function_exists('idems_run_sla_escalations')) {
    $esc = idems_run_sla_escalations();
    echo "IDEMS approval SLA escalations: $esc\n";
}

// Automated MIS digest to leadership — weekly on Monday, monthly on the 1st.
// A per-day guard prevents duplicates if cron runs more than once a day.
if (function_exists('ops_run_mis_digest')) {
    $today = date('Y-m-d');
    if ((int)date('j') === 1 && setting_get('mis_last_monthly', '') !== $today) {
        ops_run_mis_digest('monthly'); setting_set('mis_last_monthly', $today);
        echo "Monthly MIS digest sent.\n";
    } elseif ((int)date('N') === 1 && setting_get('mis_last_weekly', '') !== $today) {
        ops_run_mis_digest('weekly'); setting_set('mis_last_weekly', $today);
        echo "Weekly MIS digest sent.\n";
    }
}
