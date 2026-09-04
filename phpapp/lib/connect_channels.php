<?php
// ============================================================================
//  CONNECT — WhatsApp / SMS / Email channel  (slice K16 / backlog #5, additive)
//
//  This audience lives on WhatsApp. This is the channel that reaches them without
//  them opening the app — job alerts, "you're shortlisted", "you're hired", a new
//  message nudge. It sits BEHIND the notification seam (the same posture as the
//  existing integration outbox): a provider registers behind a seam; until one is
//  wired the messages are RECORDED, not falsely marked sent.
//
//  Honesty & India compliance are first-class:
//   - Delivery MODE (setting connect_channels_mode): 'off' (default) records the
//     message as QUEUED (awaiting a channel being connected — nothing is sent and
//     nothing pretends to be); 'log' simulates delivery (LOGGED) for testing;
//     'live' hands the message to a registered provider (SENT / FAILED).
//   - Consent is required: a professional opts in per channel; WhatsApp/SMS need a
//     mobile. No opt-in → no message.
//   - Templates are runtime data (editable, not hard-coded) and, in 'live' mode,
//     must be APPROVED (WhatsApp/DLT template approval) before anything is sent.
//
//  ADDITIVE CONTRACT: new tables only (cx_channel_templates, cx_channel_messages),
//  additive consent columns on cx_professionals, no new named permission (admin /
//  coordinator, like the rest of the marketplace desk), no existing screen changed.
// ============================================================================

/** The reach-out channels this seam carries (in-app chat is handled by #4). */
function connect_channels_list() {
    return ['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'Email'];
}

/** Delivery mode: off (record only) | log (simulate) | live (provider). */
function connect_channels_mode() {
    $m = function_exists('setting_get') ? (string)setting_get('connect_channels_mode', 'off') : 'off';
    return in_array($m, ['off', 'log', 'live'], true) ? $m : 'off';
}

function connect_channels_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS cx_channel_templates (
        id $pk, tkey VARCHAR(40) DEFAULT '', channel VARCHAR(16) DEFAULT '',
        title VARCHAR(160) DEFAULT '', body TEXT,
        approval_status VARCHAR(16) DEFAULT 'DRAFT',   -- DRAFT | APPROVED (BSP/DLT)
        provider_ref VARCHAR(80) DEFAULT '',           -- WhatsApp template name / DLT template id
        enabled INT DEFAULT 1, is_system INT DEFAULT 0, sort_order INT DEFAULT 0,
        updated_at VARCHAR(30) DEFAULT '')");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_channel_messages (
        id $pk, pro_id INT DEFAULT 0, channel VARCHAR(16) DEFAULT '', tkey VARCHAR(40) DEFAULT '',
        to_masked VARCHAR(60) DEFAULT '', body TEXT,
        status VARCHAR(16) DEFAULT 'QUEUED',           -- QUEUED | LOGGED | SENT | FAILED | SKIPPED
        mode VARCHAR(8) DEFAULT 'off', provider VARCHAR(40) DEFAULT '', error VARCHAR(300) DEFAULT '',
        link VARCHAR(300) DEFAULT '', created_at VARCHAR(30) DEFAULT '', sent_at VARCHAR(30) DEFAULT '')");
    try { db()->exec("CREATE UNIQUE INDEX ux_cx_tmpl ON cx_channel_templates (tkey, channel)"); } catch (Throwable $e) {}
    try { db()->exec("CREATE INDEX ix_cx_chmsg ON cx_channel_messages (pro_id, id)"); } catch (Throwable $e) {}
    if (function_exists('ensure_column')) {
        try {
            ensure_column('cx_professionals', 'notify_whatsapp', 'INT DEFAULT 0');
            ensure_column('cx_professionals', 'notify_sms', 'INT DEFAULT 0');
            ensure_column('cx_professionals', 'notify_email', 'INT DEFAULT 1');
            ensure_column('cx_professionals', 'channel_consent_at', "VARCHAR(30) DEFAULT ''");
        } catch (Throwable $e) {}
    }
}

/** Default templates (runtime data, insert-if-empty, admin-editable afterwards). */
function connect_channels_default_templates() {
    return [
        // key, channel, title, body
        ['new_message', 'whatsapp', 'New message', 'Hi {name}, the hiring desk sent you a message about "{job}". Open ITSN to reply: {link}'],
        ['new_message', 'sms',      'New message', 'ITSN: new message about {job}. Reply in the app: {link}'],
        ['new_message', 'email',    'New message', "Hi {name},\n\nYou have a new message about \"{job}\".\nReply here: {link}\n\n— ITSN"],

        ['shortlisted', 'whatsapp', 'Shortlisted', 'Good news {name}! You have been shortlisted for "{job}" ({ref}). Details: {link}'],
        ['shortlisted', 'sms',      'Shortlisted', 'ITSN: you are shortlisted for {job} ({ref}). {link}'],
        ['shortlisted', 'email',    'Shortlisted', "Hi {name},\n\nYou have been shortlisted for \"{job}\" ({ref}).\n{link}\n\n— ITSN"],

        ['awarded',     'whatsapp', 'You are hired', 'Congratulations {name}! You have been awarded "{job}" ({ref}) by {poster}. Next steps: {link}'],
        ['awarded',     'sms',      'You are hired', 'ITSN: you are hired for {job} ({ref}). Open the app: {link}'],
        ['awarded',     'email',    'You are hired', "Hi {name},\n\nYou have been awarded \"{job}\" ({ref}) by {poster}.\nNext steps: {link}\n\n— ITSN"],

        ['job_match',   'whatsapp', 'New job for you', 'Hi {name}, a new job matches your skills: "{job}" in {location}. Apply: {link}'],
        ['job_match',   'sms',      'New job for you', 'ITSN: new job {job} in {location}. Apply: {link}'],
        ['job_match',   'email',    'New job for you', "Hi {name},\n\nA new job matches your skills: \"{job}\" in {location}.\nApply: {link}\n\n— ITSN"],
    ];
}

/** Seed the default templates once (idempotent, insert-if-empty). */
function connect_channels_seed() {
    connect_channels_migrate();
    try { if ((int)db()->query("SELECT COUNT(*) FROM cx_channel_templates")->fetchColumn() > 0) return; }
    catch (Throwable $e) { return; }
    $st = db()->prepare("INSERT INTO cx_channel_templates (tkey,channel,title,body,approval_status,enabled,is_system,sort_order,updated_at)
                         VALUES (?,?,?,?, 'DRAFT', 1, 1, ?, ?)");
    $i = 0; foreach (connect_channels_default_templates() as $t) $st->execute([$t[0], $t[1], $t[2], $t[3], ++$i, date('c')]);
}

/** One template row, or null. */
function connect_channel_template($tkey, $channel) {
    connect_channels_migrate();
    try { return ops_one("SELECT * FROM cx_channel_templates WHERE tkey=? AND channel=?", [(string)$tkey, (string)$channel]) ?: null; }
    catch (Throwable $e) { return null; }
}

/** Render a template body with {placeholders} from $params. */
function connect_channel_render($body, array $params) {
    $out = (string)$body;
    foreach ($params as $k => $v) $out = str_replace('{' . $k . '}', (string)$v, $out);
    // Drop any placeholder left unfilled.
    $out = preg_replace('/\{[a-z0-9_]+\}/i', '', $out);
    return trim($out);
}

/** Mask a phone / email for the outbound log (never store the raw contact). */
function connect_channel_mask_contact($s) {
    $s = trim((string)$s);
    if ($s === '') return '';
    if (strpos($s, '@') !== false) {
        [$u, $d] = explode('@', $s, 2);
        return (mb_strlen($u) <= 2 ? $u : mb_substr($u, 0, 2)) . '…@' . $d;
    }
    return function_exists('connect_verify_mask') ? connect_verify_mask($s) : (strlen($s) > 4 ? str_repeat('•', strlen($s) - 4) . substr($s, -4) : $s);
}

// ---- Consent ----------------------------------------------------------------

/** A professional's channel opt-ins + contactability. */
function connect_channel_prefs($proId) {
    connect_channels_migrate();
    $u = ops_one("SELECT mobile, email, notify_whatsapp, notify_sms, notify_email FROM cx_professionals WHERE id=?", [(int)$proId]);
    if (!$u) return ['whatsapp' => false, 'sms' => false, 'email' => false, 'mobile' => '', 'email_addr' => ''];
    return [
        'whatsapp' => (int)($u['notify_whatsapp'] ?? 0) === 1,
        'sms'      => (int)($u['notify_sms'] ?? 0) === 1,
        'email'    => (int)($u['notify_email'] ?? 0) === 1,
        'mobile'   => (string)($u['mobile'] ?? ''),
        'email_addr' => (string)($u['email'] ?? ''),
    ];
}

/** Save a professional's channel opt-ins (called from the /pro profile save). */
function connect_channel_set_consent($proId, array $in) {
    connect_channels_migrate();
    $wa = !empty($in['notify_whatsapp']) ? 1 : 0;
    $sms = !empty($in['notify_sms']) ? 1 : 0;
    $em = !empty($in['notify_email']) ? 1 : 0;
    db()->prepare("UPDATE cx_professionals SET notify_whatsapp=?, notify_sms=?, notify_email=?, channel_consent_at=? WHERE id=?")
        ->execute([$wa, $sms, $em, date('c'), (int)$proId]);
    return true;
}

// ---- The provider seam ------------------------------------------------------

/**
 * Resolve a live sender for a channel. Empty by default — no BSP wired — so 'live'
 * mode records FAILED("no provider") rather than pretending. A real WhatsApp
 * Business / SMS-gateway integration registers a callable here (via the same
 * apply_filters seam the rest of the app uses) with no other change.
 */
function connect_channel_provider($channel) {
    $providers = function_exists('apply_filters') ? apply_filters('connect_channel_providers', []) : [];
    if (!is_array($providers)) $providers = [];
    return $providers[$channel] ?? null;
}

// ---- Send -------------------------------------------------------------------

/**
 * Notify a professional about an event on their opted-in channels. Records one
 * cx_channel_messages row per attempted channel. Honest by mode:
 *   off  → QUEUED  (recorded; nothing sent until a channel is connected)
 *   log  → LOGGED  (simulated delivery, for testing)
 *   live → SENT / FAILED via the registered provider (APPROVED template required)
 * Returns ['sent'=>n, 'queued'=>n, 'skipped'=>n, 'rows'=>[...]].
 */
function connect_channel_notify($proId, $tkey, array $params = [], array $opts = []) {
    connect_channels_migrate();
    $proId = (int)$proId;
    $prefs = connect_channel_prefs($proId);
    $mode = connect_channels_mode();
    $link = (string)($opts['link'] ?? ($params['link'] ?? ''));
    $params['name'] = $params['name'] ?? (string)ops_val("SELECT name FROM cx_professionals WHERE id=?", [$proId]);
    $out = ['sent' => 0, 'queued' => 0, 'logged' => 0, 'skipped' => 0, 'rows' => []];

    foreach (array_keys(connect_channels_list()) as $ch) {
        if (empty($prefs[$ch])) continue;   // not opted in — nothing recorded (no noise)
        $contact = ($ch === 'email') ? $prefs['email_addr'] : $prefs['mobile'];
        $tmpl = connect_channel_template($tkey, $ch);

        $status = 'QUEUED'; $err = ''; $provider = ''; $body = '';
        if (!$tmpl || (int)($tmpl['enabled'] ?? 0) !== 1) { $status = 'SKIPPED'; $err = 'no active template'; }
        elseif ($contact === '') { $status = 'SKIPPED'; $err = 'no ' . ($ch === 'email' ? 'email' : 'mobile') . ' on file'; }
        else {
            $body = connect_channel_render($tmpl['body'], $params);
            if ($mode === 'off') { $status = 'QUEUED'; }
            elseif ($mode === 'log') { $status = 'LOGGED'; }
            else { // live
                if (strtoupper((string)$tmpl['approval_status']) !== 'APPROVED') { $status = 'SKIPPED'; $err = 'template not approved for ' . $ch; }
                else {
                    $p = connect_channel_provider($ch);
                    if (is_callable($p['run'] ?? null)) {
                        try { $r = $p['run']($contact, $body, $tmpl); $status = !empty($r['ok']) ? 'SENT' : 'FAILED'; $err = (string)($r['error'] ?? ''); $provider = (string)($p['name'] ?? $ch); }
                        catch (Throwable $e) { $status = 'FAILED'; $err = substr($e->getMessage(), 0, 200); }
                    } else { $status = 'FAILED'; $err = 'no provider connected for ' . $ch; }
                }
            }
        }

        $sentAt = in_array($status, ['SENT', 'LOGGED'], true) ? date('c') : '';
        db()->prepare("INSERT INTO cx_channel_messages (pro_id,channel,tkey,to_masked,body,status,mode,provider,error,link,created_at,sent_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$proId, $ch, (string)$tkey, connect_channel_mask_contact($contact), $body, $status, $mode, $provider, $err, $link, date('c'), $sentAt]);
        $id = (int)db()->lastInsertId();
        $out['rows'][] = $id;
        if ($status === 'SENT') $out['sent']++;
        elseif ($status === 'LOGGED') $out['logged']++;
        elseif ($status === 'QUEUED') $out['queued']++;
        else $out['skipped']++;
    }
    return $out;
}

// ---- Reads + admin screen ---------------------------------------------------

function connect_channels_recent($limit = 60) {
    connect_channels_migrate();
    try {
        $st = db()->prepare("SELECT m.*, p.name AS pro_name FROM cx_channel_messages m
                             LEFT JOIN cx_professionals p ON p.id=m.pro_id ORDER BY m.id DESC LIMIT ?");
        $st->execute([(int)$limit]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
}

function connect_channels_counts() {
    connect_channels_migrate();
    $out = [];
    try {
        foreach (ops_all("SELECT status, COUNT(*) c FROM cx_channel_messages GROUP BY status") ?: [] as $r) $out[(string)$r['status']] = (int)$r['c'];
    } catch (Throwable $e) {}
    return $out;
}

/** Read/manage gate — the marketplace desk. No new permission. */
function connect_channels_can() {
    if (function_exists('connect_enabled') && !connect_enabled()) return false;
    if (function_exists('is_master') && is_master()) return true;
    if (function_exists('is_coordinator_level') && is_coordinator_level()) return true;
    return false;
}
/** Configuration (mode + templates) is admin-level, like Settings/Lookups. */
function connect_channels_manage_can() {
    if (function_exists('connect_enabled') && !connect_enabled()) return false;
    return function_exists('is_admin_level') && is_admin_level();
}

/** The ops channel screen: delivery mode, provider status, templates, outbound log. */
function ops_connect_channels($method) {
    ops_require(connect_channels_can(), 'Marketplace channels are available to the coordinator desk.');
    connect_channels_seed();

    if ($method === 'POST') {
        ops_require(connect_channels_manage_can(), 'Only admins can configure channels.');
        $act = (string)($_POST['action'] ?? '');
        if ($act === 'mode' && function_exists('setting_set')) {
            $m = (string)($_POST['mode'] ?? 'off');
            if (in_array($m, ['off', 'log', 'live'], true)) { setting_set('connect_channels_mode', $m); flash('Delivery mode set to ' . $m . '.'); }
        } elseif ($act === 'template') {
            $id = (int)($_POST['id'] ?? 0);
            $body = trim((string)($_POST['body'] ?? ''));
            $enabled = !empty($_POST['enabled']) ? 1 : 0;
            $appr = (strtoupper((string)($_POST['approval_status'] ?? 'DRAFT')) === 'APPROVED') ? 'APPROVED' : 'DRAFT';
            $ref = trim((string)($_POST['provider_ref'] ?? ''));
            if ($id > 0 && $body !== '') {
                db()->prepare("UPDATE cx_channel_templates SET body=?, enabled=?, approval_status=?, provider_ref=?, updated_at=? WHERE id=?")
                    ->execute([$body, $enabled, $appr, $ref, date('c'), $id]);
                flash('Template updated.');
            }
        }
        redirect('/connect-channels');
    }

    view('ops/connect_channels', [
        'mode'      => connect_channels_mode(),
        'channels'  => connect_channels_list(),
        'templates' => connect_qtx_rows_safe('cx_channel_templates', 'tkey, channel, id'),
        'recent'    => connect_channels_recent(),
        'counts'    => connect_channels_counts(),
        'canManage' => connect_channels_manage_can(),
        'providers' => array_map(fn($c) => connect_channel_provider($c) ? 'connected' : 'not connected', connect_channels_list()),
    ]);
    return true;
}

/** Tiny local read helper (avoids a hard dependency on the qualtax file's helper). */
function connect_qtx_rows_safe($table, $order) {
    try { return db()->query("SELECT * FROM $table ORDER BY $order")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
    catch (Throwable $e) { return []; }
}
