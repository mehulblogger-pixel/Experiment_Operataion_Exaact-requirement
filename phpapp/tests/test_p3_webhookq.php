<?php
// Phase 3 §50 — the generic integration queue. One reusable outbox any new integration can enqueue onto,
// with dedupe, bounded retries + exponential backoff, and a recorded outcome — so a new integration
// writes a delivery callback, not a table and a loop. Delivery is injectable and OFF by default (nothing
// is sent until an install wires a deliverer). The existing books/ads outboxes are untouched. Self-contained.
t_section('Phase 3 §50 — generic integration queue (enqueue / dedupe / retry / give-up)');

t_ok(function_exists('webhookq_enqueue') && function_exists('webhookq_dispatch') && function_exists('webhookq_counts'),
     'the queue helpers exist');
$idx = file_get_contents(__DIR__ . '/../index.php');
t_ok(strpos($idx, "require __DIR__ . '/lib/webhookq.php'") !== false, 'the queue lib is loaded by the front controller');
$dbsrc = file_get_contents(__DIR__ . '/../lib/db.php');
t_ok(strpos($dbsrc, 'webhookq_migrate()') !== false, 'the queue migration is wired into boot()');
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "'webhookq'") !== false && strpos($ops, 'Integration queue') !== false, 'the queue reports into integration_health()');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    webhookq_migrate();

    // Enqueue two events; an identical third is deduplicated.
    $a = webhookq_enqueue('crm', 'lead.created', ['id' => 1, 'name' => 'Acme']);
    $b = webhookq_enqueue('crm', 'lead.created', ['id' => 2, 'name' => 'Beta']);
    $dup = webhookq_enqueue('crm', 'lead.created', ['id' => 1, 'name' => 'Acme']);
    t_ok($a > 0 && $b > 0, 'two distinct events are queued');
    t_eq($dup, $a, 'an identical still-pending event is deduplicated to the same row');
    t_eq((int)webhookq_counts()['PENDING'], 2, 'exactly the two distinct events are pending');

    // With the default (no-op) deliverer, nothing is sent — items stay queued, not fabricated as sent.
    $r0 = webhookq_dispatch(50);
    t_ok($r0['done'] === 0 && $r0['failed'] === 2, 'the default deliverer sends nothing (items fail, not falsely DONE)');
    t_eq((int)webhookq_counts()['DONE'], 0, 'nothing is marked delivered without a real deliverer');

    // A success deliverer marks items DONE.
    $ok = fn($row) => ['ok' => true, 'code' => 200];
    // The two are now in FAILED with a future backoff — force them due, then deliver successfully.
    db()->exec("UPDATE integration_outbox SET next_attempt_at='' WHERE status='FAILED'");
    $r1 = webhookq_dispatch(50, $ok);
    t_eq($r1['done'], 2, 'a working deliverer marks the due items DONE');
    t_eq((int)webhookq_counts()['DONE'], 2, 'both events are delivered');

    // Retry → give-up: an always-failing deliverer with a small cap gives up after max attempts.
    $id = webhookq_enqueue('crm', 'lead.retry', ['id' => 9], ['max_attempts' => 2]);
    $fail = fn($row) => ['ok' => false, 'code' => 500, 'error' => 'boom'];
    webhookq_dispatch(50, $fail);                                   // attempt 1 → FAILED (backoff)
    $mid = ops_one("SELECT status, attempts FROM integration_outbox WHERE id=?", [$id]);
    t_ok($mid['status'] === 'FAILED' && (int)$mid['attempts'] === 1, 'first failure schedules a retry (backoff), not a give-up');
    db()->exec("UPDATE integration_outbox SET next_attempt_at='' WHERE id=" . (int)$id);  // force it due
    webhookq_dispatch(50, $fail);                                   // attempt 2 → GIVEN_UP (hit the cap)
    $end = ops_one("SELECT status, attempts FROM integration_outbox WHERE id=?", [$id]);
    t_ok($end['status'] === 'GIVEN_UP' && (int)$end['attempts'] === 2, 'it gives up once attempts hit max_attempts');
    t_eq((int)webhookq_counts()['stuck'], 1, 'a given-up item counts as stuck (what health flags)');

    // Backoff actually parks a fresh failure in the future (not retried in the same run).
    webhookq_enqueue('crm', 'lead.park', ['id' => 3], ['max_attempts' => 5]);
    webhookq_dispatch(50, $fail);
    $future = (int) ops_val("SELECT COUNT(*) FROM integration_outbox WHERE event='lead.park' AND status='FAILED' AND next_attempt_at > ?", [date('c')]);
    t_eq($future, 1, 'a failed item is parked with a future retry time (backoff)');
    // ...and a claim right now does not pick it up.
    $claimNow = array_filter(webhookq_claim(50), fn($r) => $r['event'] === 'lead.park');
    t_ok(empty($claimNow), 'the parked item is not re-claimed until its backoff elapses');

    // Channel isolation.
    webhookq_enqueue('books', 'inv.push', ['id' => 5]);
    t_eq((int)webhookq_counts('books')['PENDING'], 1, 'counts can be scoped to one channel');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
