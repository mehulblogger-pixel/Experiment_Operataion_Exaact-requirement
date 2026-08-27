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
require __DIR__ . '/lib/capa.php';
require __DIR__ . '/lib/ncr.php';
require __DIR__ . '/lib/adspro.php';
require __DIR__ . '/lib/adssync.php';
require __DIR__ . '/lib/licencekey.php';
require __DIR__ . '/lib/licencesync.php';
// Retention lives across these two, and BOTH were missing from this list — so
// the nightly trim below silently did nothing at all: function_exists() returned
// false and the block skipped without a word. security.php holds the retention
// period, compliance.php holds the trim itself.
require __DIR__ . '/lib/security.php';
require __DIR__ . '/lib/compliance.php';
require __DIR__ . '/lib/books.php';
require __DIR__ . '/lib/billable.php';   // Revamp P4 — billable-event derive + reconcile
require __DIR__ . '/lib/booksbridge.php';

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
// A heads-up a couple of weeks before that close, so it never comes as a surprise.
$idleWarned = function_exists('contracts_idle_warn') ? contracts_idle_warn() : 0;
// A contract with no activity for two months is closed automatically; anything
// still pending at close is flagged to the owner, branch manager and accounts.
$idleClosed = function_exists('contracts_idle_autoclose') ? contracts_idle_autoclose() : 0;
echo "Reminders processed. Emails queued/sent: $sent. Contracts expiring: $expiring. Idle warnings: $idleWarned. Contracts auto-closed: $idleClosed\n";

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
// ISO/IEC 17020 §6.2 — an instrument that falls out of calibration silently is
// the whole problem. The same 30-day window as the personnel certificates.
if (function_exists('equipment_run_cal_reminders')) {
    $cal = equipment_run_cal_reminders();
    echo "Calibration reminders sent: $cal\n";
}

// §6.1 — an authorisation that has run out, or one resting on a certificate
// that has lapsed, must stop being live without anybody having to notice.
if (function_exists('auth_run_maintenance')) {
    $am = auth_run_maintenance();
    echo "Authorisations expired: {$am['expired']}, suspended: {$am['suspended']}\n";
}

if (function_exists('crm_run_followups')) {
    $fu = crm_run_followups();
    echo "Quote follow-ups sent: $fu\n";
}

// §14 — a quotation past its validity is no longer a live offer. Turn "past
// validity" into the EXPIRED status the lifecycle already knows, so a sent quote
// stops counting as live pipeline once its clock has run out.
if (function_exists('crm_expire_quotes')) {
    $exq = crm_expire_quotes();
    echo "Quotations expired: $exq\n";
}

// Module 09 — chase overdue invoices. Ageing was view-only; this follows up the
// money, marking each so a daily run does not re-nag the same invoice.
if (function_exists('ar_overdue_reminders')) {
    $od = ar_overdue_reminders();
    echo "Overdue invoices chased: $od\n";
}

// IDEMS — escalate report approvals that have blown their SLA.
if (function_exists('idems_run_sla_escalations')) {
    $esc = idems_run_sla_escalations();
    echo "IDEMS approval SLA escalations: $esc\n";
}

// TOSRM Phase 9 — generate any calls that recurring schedules have made due.
if (function_exists('tosrm_run_recurring')) {
    $gen = tosrm_run_recurring();
    if ($gen > 0) echo "TOSRM recurring: $gen call(s) generated.\n";
}

// Revamp P4 — keep the billable-event ledger fresh: backstop-derive any closed
// work not yet queued, and reconcile events whose job has since been invoiced.
if (function_exists('billable_events_sync')) {
    $be = billable_events_sync();
    if (($be['created'] ?? 0) + ($be['billed'] ?? 0) > 0)
        echo "Billable events: {$be['created']} derived, {$be['billed']} reconciled.\n";
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

// Nonconformities past their date — chase the owner. Runs alongside the
// corrective-action and complaint chases so the registers actually reach the
// person who has to act, rather than waiting to be visited.
if (function_exists('ncr_run_reminders')) {
    $n = ncr_run_reminders();
    echo "Nonconformity chases sent: $n\n";
}
// Individual corrective-action tasks past their date. Separate from the
// corrective action's own chase: the task has its own owner, and that is the
// person who can actually move it.
if (function_exists('capa_actions_overdue') && function_exists('ops_mail')) {
    $n = 0;
    foreach (capa_actions_overdue() as $a) {
        $to = trim((string)$a['owner']);
        if ($to === '') continue;
        $late = (int)floor((strtotime(date('Y-m-d')) - strtotime($a['due_on'])) / 86400);
        ops_mail($to, "Corrective action {$a['ref']}: a task is $late day(s) late",
            "{$a['ref']} — {$a['title']}\n\nYour task: {$a['description']}\n"
            . "Due: {$a['due_on']} ($late day(s) ago)\n\n"
            . "Open it in the application to record it as done, or say why it is being dropped.");
        $n++;
    }
    echo "Corrective-action task chases sent: $n\n";
}
if (function_exists('capa_run_reminders')) {
    $n = capa_run_reminders();
    echo "Corrective-action chases sent: $n\n";
}
if (function_exists('cmp_run_reminders')) {
    $n = cmp_run_reminders();
    echo "Complaint chases sent: $n\n";
}
// Vendor qualification lifecycle — expire lapsed approvals and remind on
// re-assessments falling due.
if (function_exists('idems_vendor_run_reminders')) {
    $vr = idems_vendor_run_reminders();
    echo "Vendor approvals expired: {$vr['expired']}; re-assessment reminders sent: {$vr['reminded']}\n";
}
// TAPI threshold alerts — fire any KPI alert whose rule is currently breached
// (once per rule per day).
if (function_exists('tapi_alerts_run')) {
    $ta = tapi_alerts_run();
    echo "Analytics alerts sent: $ta\n";
}

// Passports, visas, medicals and gate passes running out. Looked at 45 days
// ahead rather than 30, because a visa takes weeks to renew and a document that
// expires the week of the inspection is a wasted trip.
if (function_exists('sitedoc_expiring') && function_exists('ops_mail')) {
    $n = 0;
    foreach (sitedoc_expiring(45) as $d) {
        $to = trim((string)($d['email'] ?? ''));
        if ($to === '') continue;
        $kinds = function_exists('iddoc_kind_options') ? iddoc_kind_options() : [];
        ops_mail($to, 'Your ' . ($kinds[$d['doc_kind']] ?? $d['doc_kind']) . ' expires on ' . $d['expires_on'],
            "Your " . ($kinds[$d['doc_kind']] ?? $d['doc_kind']) . " expires on {$d['expires_on']}.\n\n"
            . "Some client sites will not admit you without it, and an allocation to one of those sites "
            . "will be refused once it has lapsed. Please get it renewed and send the new one to the office.");
        $n++;
    }
    echo "Site-entry document expiry notices sent: $n\n";
}

// Competence that has fallen out of date: authorisations expired, reviews come
// round, witnessing due. Sent to the reporting manager rather than the person
// themselves, because none of these are theirs to fix.
if (function_exists('competence_due') && function_exists('ops_mail')) {
    $d = competence_due();
    $n = count($d['expired']) + count($d['review']) + count($d['witness']);
    if ($n) {
        $body = "The competence register has " . $n . " item(s) out of date.\n\n";
        foreach (['expired' => 'Expired authorisations', 'review' => 'Due for review', 'witness' => 'Witnessing due'] as $k => $lbl) {
            if (!$d[$k]) continue;
            $body .= "$lbl:\n";
            foreach ($d[$k] as $a) $body .= '  - ' . ($a['inspector_name'] ?? '?') . ' — ' . ($a['scope_value'] ?: $a['scope_kind']) . "\n";
            $body .= "\n";
        }
        $body .= "Open Competence & authorisation in the application to put them right.";
        foreach (ops_all("SELECT email FROM users WHERE is_active=1 AND email <> '' AND role IN ('MASTER_ADMIN','BRANCH_MANAGER','OPERATION_MANAGER','BUSINESS_DIRECTOR')") as $u) {
            try { ops_mail($u['email'], "Competence register: $n item(s) out of date", $body); } catch (Throwable $e) {}
        }
    }
    echo "Competence items out of date: $n\n";
}

// ---------------------------------------------------------------------------
//  Ads Pro: drain the outbox, then bring in what is new.
//
//  This is where the two-way sync actually happens. Saving a lead only ever
//  queues it — the web request returns immediately whether Ads Pro is up, slow
//  or gone. Nothing here can break a page, because nothing here runs on one.
//
//  Silent when the link is not configured, which is most installs.
// ---------------------------------------------------------------------------
// Licence check-in, as a safety net for an installation where the frequent
// cron (cron_ads.php) was never set up. Renewal is then within a day rather
// than within fifteen minutes, which is slower than it should be but is never
// nothing.
if (function_exists('licsync_checkin')) {
    $lr = licsync_checkin(false);
    if (!empty($lr['changed']) || empty($lr['ok'])) echo "Licence: " . $lr['msg'] . "\n";
}

//  A DAILY CATCH-UP ONLY. The real schedule for this is cron_ads.php, run every
//  fifteen minutes — a lead that arrives at 09:05 should be workable at 09:20,
//  not tomorrow. It is a separate file because most jobs in THIS file send
//  e-mail and have no per-day guard, so running this one every quarter of an
//  hour would post the same reminders ninety-six times a day.
//
//  Harmless if cron_ads.php is already running: every push is guarded by a
//  payload hash and every pull is matched on the Ads Pro record id, so a second
//  run finds nothing to do.
if (function_exists('ads_on') && ads_on() && function_exists('ads_sync_now')) {
    $r = ads_sync_now();
    $out = $r['push'] ?? []; $in = $r['pull'] ?? [];
    echo "Ads Pro out: " . (!empty($out['err']) ? 'FAILED — ' . $out['err'] : ($out['msg'] ?? 'nothing waiting')) . "\n";
    echo "Ads Pro in:  " . (!empty($in['err'])  ? 'FAILED — ' . $in['err']  : ($in['msg']  ?? 'nothing')) . "\n";
    if (function_exists('ads_import_spend') && (string)setting_get('adspro_spend_day', '') !== date('Y-m-d')) {
        $sp = ads_import_spend(90);
        if (empty($sp['err'])) setting_set('adspro_spend_day', date('Y-m-d'));
        echo "Ads Pro spend: " . (!empty($sp['err']) ? 'FAILED — ' . $sp['err'] : ($sp['msg'] ?? '')) . "\n";
    }
}

// ---------------------------------------------------------------------------
//  MGH Books: drain the outbox (ERP -> Books). A no-op unless connected.
if (function_exists('books_bridge_drain') && function_exists('books_connected') && books_connected()) {
    $bx = books_bridge_drain();
    echo "MGH Books queue: {$bx['sent']} sent, {$bx['failed']} failed\n";
    if (function_exists('books_bridge_pull_status')) {
        $pulled = books_bridge_pull_status();
        echo "MGH Books status pulled: $pulled invoice(s)\n";
    }
}

// ---------------------------------------------------------------------------
//  Retention, once a day
//
//  audit_trim_old() existed but was only ever reached by somebody pressing a
//  button on the compliance screen. Nobody presses it, so the trail grew for
//  ever — which is the opposite of a retention policy: the screen states a
//  retention period the system was not keeping to, and holding personal data
//  longer than you said you would is the finding, not the fix.
//
//  Guarded per day so running cron.php more often costs one comparison.
// ---------------------------------------------------------------------------
if (function_exists('audit_trim_old') && (string)setting_get('audit_trim_day', '') !== date('Y-m-d')) {
    try {
        $n = audit_trim_old();
        setting_set('audit_trim_day', date('Y-m-d'));
        echo "Audit trail: " . ($n ? $n . " entries past the " . audit_retain_days() . "-day retention period removed"
                                   : "nothing older than " . audit_retain_days() . " days") . "\n";
    } catch (Throwable $e) {
        // A failed trim must never stop the rest of the nightly run.
        echo "Audit trail: trim failed — " . $e->getMessage() . "\n";
    }
}

// Module 28 — chase an overdue internal audit / management review, at most weekly
// (a year-long cycle does not need a daily nudge). The only accreditation registers
// that had a readiness signal but no reminder.
if ((function_exists('audits_run_reminders') || function_exists('reviews_run_reminders'))
    && (string)setting_get('audit_reminder_week', '') !== date('o-W')) {
    try {
        $a = function_exists('audits_run_reminders') ? audits_run_reminders() : 0;
        $r = function_exists('reviews_run_reminders') ? reviews_run_reminders() : 0;
        setting_set('audit_reminder_week', date('o-W'));
        echo "Audit / review reminders sent: " . ($a + $r) . "\n";
    } catch (Throwable $e) {
        echo "Audit / review reminders: failed — " . $e->getMessage() . "\n";
    }
}

// Module 41 — chase a controlled document past its review date, at most weekly
// (a year-scale review cycle needs no daily nudge). §8.3 was the one accreditation
// register whose review-due signal was computed but never dispatched.
if (function_exists('cdoc_run_reminders') && (string)setting_get('cdoc_reminder_week', '') !== date('o-W')) {
    try {
        $cd = cdoc_run_reminders();
        setting_set('cdoc_reminder_week', date('o-W'));
        echo "Controlled-document reminders sent: $cd\n";
    } catch (Throwable $e) {
        echo "Controlled-document reminders: failed — " . $e->getMessage() . "\n";
    }
}

// Module 36 — nudge an admin before a licence/subscription lapse forces read-only
// or the next new user is refused, at most weekly. lk_state() already knows the
// days-left and seat pressure; nothing dispatched it, so a self-service install got
// no pre-lapse notice. Advisory email only — it never changes enforcement.
if (function_exists('licence_run_reminders') && (string)setting_get('licence_reminder_week', '') !== date('o-W')) {
    try {
        $lc = licence_run_reminders();
        setting_set('licence_reminder_week', date('o-W'));
        if ($lc) echo "Licence/subscription reminder sent.\n";
    } catch (Throwable $e) {
        echo "Licence reminder: failed — " . $e->getMessage() . "\n";
    }
}

// Module 29 — the §7.11 data-integrity self-test, once a day. integrity_run()
// runs the referential + consistency checks and writes ONE dated pass/fail row to
// data_check_runs — the evidence the Data Control console reads. Exactly like
// audit_trim_old() above, it was only ever reached by somebody pressing "Run them
// now", so the history was starved and "run_stale" always red. Guarded per day.
if (function_exists('integrity_run') && (string)setting_get('integrity_run_day', '') !== date('Y-m-d')) {
    try {
        $ir = integrity_run();
        setting_set('integrity_run_day', date('Y-m-d'));
        echo "Data integrity: {$ir['total']} checks, {$ir['failed']} failed, {$ir['skipped']} not in use\n";
    } catch (Throwable $e) {
        // A failed self-test must never stop the rest of the nightly run.
        echo "Data integrity: run failed — " . $e->getMessage() . "\n";
    }
}

// Phase 2 §11 — re-seal any issued report whose content seal failed to write at
// issue (transient DB hiccup). Issued content is immutable, so the re-seal hash
// equals what it would have been at issue. Self-healing; no-op when none pending.
if (function_exists('idems_reseal_failed')) {
    try { $rs = idems_reseal_failed(); if ($rs) echo "Report seals repaired: $rs\n"; }
    catch (Throwable $e) { echo "Seal repair: failed — " . $e->getMessage() . "\n"; }
}

// Phase 2 §30 — freeze the cost basis of any closed job that has none yet (a close
// path that did not snapshot, or a pre-feature job). Uses the CURRENT live value, so
// today's displayed profit is unchanged and future drift stops. Bounded per run.
if (function_exists('jobs_backfill_cost_basis')) {
    try { $bf = jobs_backfill_cost_basis(); if ($bf) echo "Job cost bases frozen: $bf\n"; }
    catch (Throwable $e) { echo "Cost-basis backfill: failed — " . $e->getMessage() . "\n"; }
}

// Phase 2 §53 — encrypt any identity documents still at rest in plaintext, once
// APP_ENCRYPTION_KEY is configured. No-op when the key is not set. Bounded per run.
if (function_exists('iddoc_encrypt_backfill')) {
    try { $ie = iddoc_encrypt_backfill(); if ($ie) echo "Identity documents encrypted: $ie\n"; }
    catch (Throwable $e) { echo "Identity encryption: failed — " . $e->getMessage() . "\n"; }
}
