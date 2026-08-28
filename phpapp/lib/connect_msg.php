<?php
// ============================================================================
//  CONNECT — In-app Messaging  (slice K15 / backlog #4, additive)
//
//  Two-way messaging per ENGAGEMENT — a thread keyed to one application (an
//  applicant against a requirement). This is the thing that stops people leaving
//  for WhatsApp: the staff desk and the professional talk inside the platform, and
//  the thread becomes part of the hiring record and the dispute trail (blueprint
//  M15). It reuses the marketplace's own engagement objects (cx_requirements /
//  cx_applications) and adds nothing to any existing screen's meaning.
//
//  ADDITIVE CONTRACT:
//   - Two new tables (cx_messages, cx_message_reads), cx_* namespaced.
//   - No new named permission: the staff desk uses coordinator level (same as the
//     rest of the marketplace desk); a professional messages only on their OWN
//     applications, through their own /pro portal session.
//   - Changes no existing route, view, permission or object status. The engine is
//     identity-agnostic (staff | professional | client | vendor | inspector) so
//     the client and vendor portals attach to the same threads later.
// ============================================================================

/** The message + per-reader read-cursor tables. */
function connect_msg_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS cx_messages (
        id $pk,
        application_id INT DEFAULT 0,
        requirement_id INT DEFAULT 0,
        sender_kind VARCHAR(20) DEFAULT '',   -- staff | professional | client | vendor | inspector
        sender_id   INT DEFAULT 0,
        sender_name VARCHAR(200) DEFAULT '',
        body TEXT,
        created_at VARCHAR(30) DEFAULT '')");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_message_reads (
        id $pk,
        application_id INT DEFAULT 0,
        reader_kind VARCHAR(20) DEFAULT '',
        reader_id   INT DEFAULT 0,
        last_read_id INT DEFAULT 0,
        updated_at VARCHAR(30) DEFAULT '')");
    try { db()->exec("CREATE INDEX ix_cx_msg_app ON cx_messages (application_id, id)"); } catch (Throwable $e) {}
    try { db()->exec("CREATE UNIQUE INDEX ux_cx_read ON cx_message_reads (application_id, reader_kind, reader_id)"); } catch (Throwable $e) {}
}

/** The engagement (application joined to its requirement), or null. */
function connect_msg_app($applicationId) {
    $applicationId = (int)$applicationId; if ($applicationId <= 0) return null;
    try {
        return ops_one("SELECT a.*, r.ref_code, r.title AS req_title, r.status AS req_status,
                               r.poster_party_id, r.poster_name
                        FROM cx_applications a JOIN cx_requirements r ON r.id = a.requirement_id
                        WHERE a.id=?", [$applicationId]) ?: null;
    } catch (Throwable $e) { return null; }
}

/** Who the applicant is on an engagement: [kind, id, name]. */
function connect_msg_applicant($app) {
    if (!is_array($app)) return ['kind' => '', 'id' => 0, 'name' => ''];
    if ((int)($app['applicant_professional_id'] ?? 0) > 0)
        return ['kind' => 'professional', 'id' => (int)$app['applicant_professional_id'], 'name' => (string)($app['applicant_name'] ?? '')];
    if ((int)($app['applicant_party_id'] ?? 0) > 0)
        return ['kind' => 'vendor', 'id' => (int)$app['applicant_party_id'], 'name' => (string)($app['applicant_name'] ?? '')];
    if ((int)($app['inspector_id'] ?? 0) > 0)
        return ['kind' => 'inspector', 'id' => (int)$app['inspector_id'], 'name' => (string)($app['applicant_name'] ?? '')];
    return ['kind' => '', 'id' => 0, 'name' => (string)($app['applicant_name'] ?? '')];
}

/** A short label for the other party on a thread, from a given viewer's side. */
function connect_msg_counterparty_label($app, $viewerKind) {
    $ap = connect_msg_applicant($app);
    if ($viewerKind === 'staff') return $ap['name'] !== '' ? $ap['name'] : 'Applicant';
    // applicant-side viewers see "the hiring desk"
    return (string)($app['poster_name'] ?? '') !== '' ? (string)$app['poster_name'] : 'Hiring desk';
}

/**
 * Post a message to an engagement's thread. Access is enforced by the caller
 * (which knows the identity); this validates the engagement exists and the body.
 * Returns [ok, message, id]. The sender's own read-cursor advances to their post.
 */
function connect_msg_post($applicationId, $senderKind, $senderId, $senderName, $body) {
    connect_msg_migrate();
    $app = connect_msg_app($applicationId);
    if (!$app) return [false, 'That conversation no longer exists.', 0];
    $body = trim((string)$body);
    if ($body === '') return [false, 'Write a message first.', 0];
    if (mb_strlen($body) > 4000) $body = mb_substr($body, 0, 4000);
    db()->prepare("INSERT INTO cx_messages (application_id,requirement_id,sender_kind,sender_id,sender_name,body,created_at)
                   VALUES (?,?,?,?,?,?,?)")
        ->execute([(int)$applicationId, (int)$app['requirement_id'], (string)$senderKind, (int)$senderId, (string)$senderName, $body, date('c')]);
    $id = (int)db()->lastInsertId();
    connect_msg_mark_read($applicationId, $senderKind, $senderId); // I've "read" my own message
    return [true, 'Message sent.', $id];
}

/** Every message in a thread, oldest first. */
function connect_msg_thread($applicationId) {
    connect_msg_migrate();
    try {
        $st = db()->prepare("SELECT * FROM cx_messages WHERE application_id=? ORDER BY id ASC");
        $st->execute([(int)$applicationId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
}

/** Advance a reader's read-cursor to the latest message in a thread. */
function connect_msg_mark_read($applicationId, $readerKind, $readerId) {
    connect_msg_migrate();
    $applicationId = (int)$applicationId;
    $last = (int)ops_val("SELECT COALESCE(MAX(id),0) FROM cx_messages WHERE application_id=?", [$applicationId]);
    $exists = (int)ops_val("SELECT COUNT(*) FROM cx_message_reads WHERE application_id=? AND reader_kind=? AND reader_id=?",
        [$applicationId, (string)$readerKind, (int)$readerId]);
    if ($exists) {
        db()->prepare("UPDATE cx_message_reads SET last_read_id=?, updated_at=? WHERE application_id=? AND reader_kind=? AND reader_id=?")
            ->execute([$last, date('c'), $applicationId, (string)$readerKind, (int)$readerId]);
    } else {
        db()->prepare("INSERT INTO cx_message_reads (application_id,reader_kind,reader_id,last_read_id,updated_at) VALUES (?,?,?,?,?)")
            ->execute([$applicationId, (string)$readerKind, (int)$readerId, $last, date('c')]);
    }
}

/** Unread count for a reader on ONE thread (messages after their cursor, not their own). */
function connect_msg_thread_unread($applicationId, $readerKind, $readerId) {
    $applicationId = (int)$applicationId;
    $cursor = (int)ops_val("SELECT COALESCE(last_read_id,0) FROM cx_message_reads WHERE application_id=? AND reader_kind=? AND reader_id=?",
        [$applicationId, (string)$readerKind, (int)$readerId]);
    return (int)ops_val("SELECT COUNT(*) FROM cx_messages WHERE application_id=? AND id>? AND NOT (sender_kind=? AND sender_id=?)",
        [$applicationId, $cursor, (string)$readerKind, (int)$readerId]);
}

/**
 * A reader's total unread across a set of application ids (null = all threads the
 * reader could see — used for the staff nav badge). Cheap enough at launch scale.
 */
function connect_msg_unread_total($readerKind, $readerId, $appIds = null) {
    connect_msg_migrate();
    try {
        if (is_array($appIds)) {
            $appIds = array_values(array_unique(array_map('intval', $appIds)));
            if (!$appIds) return 0;
            $in = implode(',', array_fill(0, count($appIds), '?'));
            $rows = ops_all("SELECT DISTINCT application_id FROM cx_messages WHERE application_id IN ($in)", $appIds) ?: [];
        } else {
            $rows = ops_all("SELECT DISTINCT application_id FROM cx_messages") ?: [];
        }
        $n = 0;
        foreach ($rows as $r) $n += connect_msg_thread_unread((int)$r['application_id'], $readerKind, $readerId);
        return $n;
    } catch (Throwable $e) { return 0; }
}

/** Thread summaries (last message + unread) for a set of engagements, latest activity first. */
function connect_msg_summaries($appIds, $readerKind, $readerId) {
    connect_msg_migrate();
    $appIds = array_values(array_unique(array_map('intval', (array)$appIds)));
    $out = [];
    foreach ($appIds as $aid) {
        $app = connect_msg_app($aid); if (!$app) continue;
        $last = ops_one("SELECT * FROM cx_messages WHERE application_id=? ORDER BY id DESC LIMIT 1", [$aid]);
        $out[] = [
            'application_id' => $aid,
            'requirement_id' => (int)$app['requirement_id'],
            'ref_code'   => (string)($app['ref_code'] ?? ''),
            'req_title'  => (string)($app['req_title'] ?? ''),
            'applicant'  => connect_msg_applicant($app),
            'poster_name'=> (string)($app['poster_name'] ?? ''),
            'last'       => $last ?: null,
            'last_at'    => $last ? (string)$last['created_at'] : '',
            'unread'     => connect_msg_thread_unread($aid, $readerKind, $readerId),
            'app_status' => (string)($app['status'] ?? ''),
        ];
    }
    usort($out, fn($a, $b) => strcmp((string)$b['last_at'], (string)$a['last_at']));
    return $out;
}

// ---- Staff desk -------------------------------------------------------------

/** Staff messaging gate — the marketplace desk (coordinator level). No new perm. */
function connect_msg_staff_can() {
    if (function_exists('connect_enabled') && !connect_enabled()) return false;
    if (function_exists('is_master') && is_master()) return true;
    if (function_exists('is_coordinator_level') && is_coordinator_level()) return true;
    return false;
}

/** The application ids that currently have a thread (any message) — staff scope. */
function connect_msg_all_thread_apps() {
    connect_msg_migrate();
    try { return array_map(fn($r) => (int)$r['application_id'], ops_all("SELECT DISTINCT application_id FROM cx_messages") ?: []); }
    catch (Throwable $e) { return []; }
}

/** Staff nav badge: unread across all marketplace threads for this staff user. */
function connect_msg_staff_unread() {
    if (!connect_msg_staff_can()) return 0;
    $me = function_exists('current_user') ? current_user() : null;
    return connect_msg_unread_total('staff', (int)($me['id'] ?? 0));
}

// ---- Professional portal ----------------------------------------------------

/** The application ids that belong to one self-registered professional. */
function connect_msg_professional_apps($proId) {
    connect_msg_migrate();
    try {
        return array_map(fn($r) => (int)$r['id'],
            ops_all("SELECT id FROM cx_applications WHERE applicant_professional_id=? ORDER BY id DESC", [(int)$proId]) ?: []);
    } catch (Throwable $e) { return []; }
}

/** True when this engagement belongs to this professional (their own thread). */
function connect_msg_pro_owns($applicationId, $proId) {
    $app = connect_msg_app($applicationId);
    return $app && (int)($app['applicant_professional_id'] ?? 0) === (int)$proId;
}

/** Pro nav badge: unread across all of a professional's own engagement threads. */
function connect_msg_pro_unread($proId) {
    return connect_msg_unread_total('professional', (int)$proId, connect_msg_professional_apps($proId));
}

/** The staff messaging screen: an inbox of threads + one open thread with reply. */
function ops_connect_messages($method) {
    ops_require(connect_msg_staff_can(), 'Marketplace messaging is available to the coordinator desk.');
    connect_msg_migrate();
    $me = function_exists('current_user') ? current_user() : [];
    $meName = (string)($me['name'] ?? $me['username'] ?? 'Desk');
    $meId = (int)($me['id'] ?? 0);

    if ($method === 'POST') {
        $aid = (int)($_POST['application_id'] ?? 0);
        [$ok, $msg] = connect_msg_post($aid, 'staff', $meId, $meName, (string)($_POST['body'] ?? ''));
        flash($msg, $ok ? 'success' : 'error');
        redirect('/connect-messages?a=' . $aid);
    }

    $openId = (int)($_GET['a'] ?? 0);
    $open = null;
    if ($openId > 0) {
        $app = connect_msg_app($openId);
        if ($app) {
            connect_msg_mark_read($openId, 'staff', $meId);
            $open = [
                'app' => $app,
                'applicant' => connect_msg_applicant($app),
                'thread' => connect_msg_thread($openId),
            ];
        }
    }

    // Inbox — every thread that has a message, plus (so the desk can START a chat)
    // the recent applications on live requirements.
    $ids = connect_msg_all_thread_apps();
    try {
        foreach (ops_all("SELECT a.id FROM cx_applications a JOIN cx_requirements r ON r.id=a.requirement_id
                          ORDER BY a.id DESC LIMIT 40") ?: [] as $r) $ids[] = (int)$r['id'];
    } catch (Throwable $e) {}
    $summaries = connect_msg_summaries($ids, 'staff', $meId);

    view('ops/connect_messages', [
        'summaries' => $summaries,
        'open'      => $open,
        'openId'    => $openId,
        'meId'      => $meId,
    ]);
    return true;
}
