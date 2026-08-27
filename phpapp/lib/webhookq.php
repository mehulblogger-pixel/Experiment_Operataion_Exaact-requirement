<?php
// Phase 3 §50 — the generic integration queue.
//
// Each existing integration (MGH Books, Ads Pro) hand-rolled its OWN outbox table and its own
// retry loop. They work and are kept exactly as they are. But every NEW integration had to repeat
// that plumbing. This is the one reusable queue: enqueue an outbound event on any channel, and a
// single dispatcher delivers it with dedupe, bounded retries and exponential backoff, and records the
// outcome — so a new integration writes a delivery callback, not a table and a loop.
//
// Delivery is injectable and OFF by default: with no deliverer wired, an item is queued but nothing is
// sent (webhookq_deliver returns "not configured"). Actual outbound HTTP is a per-install concern that
// carries its own endpoint config, signing and security review — this ships the durable, tested queue,
// not an unreviewed sender. The existing books/ads outboxes are untouched; this sits alongside them and
// reports into the same integration_health() surface (Module 46).

const WEBHOOKQ_MAX = 6;        // default attempts before giving up
const WEBHOOKQ_BACKOFF_CAP = 60;  // minutes

function webhookq_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = pk_clause();
    $pdo->exec("CREATE TABLE IF NOT EXISTS integration_outbox (
        id $pk,
        channel VARCHAR(40) DEFAULT '', event VARCHAR(60) DEFAULT '',
        target VARCHAR(500) DEFAULT '', payload TEXT,
        dedupe_hash VARCHAR(64) DEFAULT '',
        status VARCHAR(20) DEFAULT 'PENDING',
        attempts INT DEFAULT 0, max_attempts INT DEFAULT " . WEBHOOKQ_MAX . ",
        last_error VARCHAR(1000) DEFAULT '', response_code INT NULL,
        next_attempt_at VARCHAR(30) DEFAULT '',
        queued_at VARCHAR(30) DEFAULT '', queued_by INT NULL, sent_at VARCHAR(30) DEFAULT '')");
    if (function_exists('act_index')) { try { act_index('integration_outbox', 'idx_iob_disp', '(status, next_attempt_at, id)'); } catch (Throwable $e) {} }
}

// Enqueue an outbound event. Deduplicates against an identical still-pending item (same channel/event
// and payload, or an explicit dedupe key) so a double save does not double-send. Returns the row id.
function webhookq_enqueue($channel, $event, $payload, array $opts = []) {
    webhookq_migrate();
    $channel = trim((string)$channel); $event = trim((string)$event);
    if ($channel === '') return 0;
    $body = is_string($payload) ? $payload : json_encode($payload);
    $hash = trim((string)($opts['dedupe_hash'] ?? '')) ?: sha1($channel . '|' . $event . '|' . $body);
    // An identical item already waiting → reuse it (refresh the payload, requeue).
    $existing = (int) (ops_val("SELECT id FROM integration_outbox WHERE dedupe_hash=? AND status IN ('PENDING','FAILED') ORDER BY id DESC LIMIT 1", [$hash]) ?: 0);
    if ($existing) {
        db()->prepare("UPDATE integration_outbox SET payload=?, status='PENDING', next_attempt_at='', last_error='' WHERE id=?")
            ->execute([$body, $existing]);
        return $existing;
    }
    $now = date('c');
    db()->prepare("INSERT INTO integration_outbox (channel, event, target, payload, dedupe_hash, status, attempts, max_attempts, next_attempt_at, queued_at, queued_by)
        VALUES (?,?,?,?,?, 'PENDING', 0, ?, '', ?, ?)")
        ->execute([$channel, $event, trim((string)($opts['target'] ?? '')), $body, $hash,
                   (int)($opts['max_attempts'] ?? WEBHOOKQ_MAX), $now, (int)(current_user()['id'] ?? 0) ?: null]);
    return (int) db()->lastInsertId();
}

// The items due for a delivery attempt now: never-tried (PENDING) or failed-but-retryable and past their
// backoff window. Optionally limited to one channel.
function webhookq_claim($limit = 50, $channel = '') {
    webhookq_migrate();
    $now = date('c');
    $w = "(status='PENDING' OR (status='FAILED' AND attempts < max_attempts AND (COALESCE(next_attempt_at,'')='' OR next_attempt_at <= ?)))";
    $a = [$now];
    if ($channel !== '') { $w .= " AND channel=?"; $a[] = $channel; }
    try {
        return ops_all("SELECT * FROM integration_outbox WHERE $w ORDER BY id ASC LIMIT " . max(1, (int)$limit), $a) ?: [];
    } catch (Throwable $e) { return []; }
}

// The default deliverer: no outbound HTTP is wired, so an item stays queued rather than being sent to a
// fabricated endpoint. A per-install integration replaces this by passing its own callback to dispatch.
function webhookq_deliver($row) {
    return ['ok' => false, 'code' => 0, 'error' => 'no deliverer configured'];
}

// Run the due items through a deliverer. $deliver($row) must return ['ok'=>bool, 'code'=>int, 'error'=>string].
// On success → DONE. On failure → FAILED with exponential backoff, or GIVEN_UP once attempts hit the cap.
function webhookq_dispatch($limit = 50, $deliver = null, $channel = '') {
    webhookq_migrate();
    $deliver = is_callable($deliver) ? $deliver : 'webhookq_deliver';
    $out = ['tried' => 0, 'done' => 0, 'failed' => 0, 'gave_up' => 0];
    foreach (webhookq_claim($limit, $channel) as $r) {
        $out['tried']++;
        $res = ['ok' => false, 'code' => 0, 'error' => 'deliver threw'];
        try { $res = (array) call_user_func($deliver, $r); } catch (Throwable $e) { $res = ['ok' => false, 'code' => 0, 'error' => substr($e->getMessage(), 0, 300)]; }
        $attempts = (int)$r['attempts'] + 1;
        if (!empty($res['ok'])) {
            db()->prepare("UPDATE integration_outbox SET status='DONE', attempts=?, response_code=?, last_error='', sent_at=? WHERE id=?")
                ->execute([$attempts, (int)($res['code'] ?? 0), date('c'), (int)$r['id']]);
            $out['done']++;
        } elseif ($attempts >= (int)$r['max_attempts']) {
            db()->prepare("UPDATE integration_outbox SET status='GIVEN_UP', attempts=?, response_code=?, last_error=? WHERE id=?")
                ->execute([$attempts, (int)($res['code'] ?? 0), substr((string)($res['error'] ?? ''), 0, 1000), (int)$r['id']]);
            $out['gave_up']++;
        } else {
            $mins = min(WEBHOOKQ_BACKOFF_CAP, (int) pow(2, $attempts));   // 2, 4, 8, … minutes
            $next = date('c', time() + $mins * 60);
            db()->prepare("UPDATE integration_outbox SET status='FAILED', attempts=?, response_code=?, last_error=?, next_attempt_at=? WHERE id=?")
                ->execute([$attempts, (int)($res['code'] ?? 0), substr((string)($res['error'] ?? ''), 0, 1000), $next, (int)$r['id']]);
            $out['failed']++;
        }
    }
    return $out;
}

// Status counts for the queue (optionally one channel), including the "stuck" (given-up) figure that the
// health surface cares about.
function webhookq_counts($channel = '') {
    webhookq_migrate();
    $out = ['PENDING' => 0, 'FAILED' => 0, 'DONE' => 0, 'GIVEN_UP' => 0, 'stuck' => 0];
    $cw = $channel !== '' ? " AND channel=?" : ''; $ca = $channel !== '' ? [$channel] : [];
    foreach (['PENDING', 'FAILED', 'DONE', 'GIVEN_UP'] as $s) {
        try { $out[$s] = (int) ops_val("SELECT COUNT(*) FROM integration_outbox WHERE status=?$cw", array_merge([$s], $ca)); } catch (Throwable $e) {}
    }
    $out['stuck'] = $out['GIVEN_UP'];
    return $out;
}

// The distinct channels the queue has carried — so the health surface can name them.
function webhookq_channels() {
    webhookq_migrate();
    try { return array_column(ops_all("SELECT DISTINCT channel FROM integration_outbox WHERE COALESCE(channel,'')<>''") ?: [], 'channel'); }
    catch (Throwable $e) { return []; }
}

// A cron entry that drains the queue (using whatever deliverers each install has wired via a filter).
function webhookq_cron() {
    if (function_exists('setting_get') && (string)setting_get('webhookq_paused', '') === '1') return ['tried' => 0];
    return webhookq_dispatch(100);
}
