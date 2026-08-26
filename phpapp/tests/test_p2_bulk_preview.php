<?php
// Phase 2 §48 — a server-side PREVIEW (dry-run) for bulk actions. The bulk framework ran
// CONFIRM → EXECUTE with no way to see, before committing, which rows would be skipped and why.
// bulk_plan() partitions the ticked ids from the SAME eligibility rule the executor uses, so the
// preview and the result can never disagree. Read-only; changes nothing.
t_section('Phase 2 §48 — bulk action preview / dry-run');

t_ok(function_exists('bulk_plan') && function_exists('bulk_plan_summary'), 'the generic bulk planner exists');
$idx = file_get_contents(__DIR__ . '/../index.php');
t_ok(strpos($idx, "require __DIR__ . '/lib/bulk.php'") !== false, 'the bulk lib is loaded by the front controller');

// The planner partitions on the classifier's ok flag.
$plan = bulk_plan([1, 2, 3, 4], fn($id) => $id % 2 === 0 ? ['ok' => true] : ['ok' => false, 'reason' => 'odd']);
t_eq($plan['apply_count'], 2, 'the even ids are in the apply set');
t_eq($plan['skip_count'], 2, 'the odd ids are skipped');
t_ok($plan['apply'] === [2, 4], 'the apply set is exactly the eligible ids');
t_ok($plan['skip'][0]['reason'] === 'odd', 'each skip carries its reason');
$dupPlan = bulk_plan([5, 5, 5], fn($id) => ['ok' => true]);
t_eq($dupPlan['total'], 1, 'duplicate ids are collapsed');
$sum = bulk_plan_summary($plan, 'marked lost', 'lead');
t_ok(strpos($sum, '2 leads will be marked lost') !== false && strpos($sum, 'left alone') !== false, 'the summary states both the apply and the skip');

// The correctness property: for leads, the preview count must equal what the executor actually does.
t_ok(function_exists('leads_bulk_plan') && function_exists('leads_bulk_eligible'), 'leads adopt the shared eligibility + preview');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$savedUid = $_SESSION['uid'] ?? null;
try {
    if (function_exists('leads_migrate')) leads_migrate();
    $pdo = db();
    // A master session so branch scope allows every seeded lead.
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, is_active, is_superuser, role)
                   VALUES ('bulkmaster','Bulk','Master',1,1,'MASTER_ADMIN')")->execute();
    $uid = (int)$pdo->lastInsertId();
    $_SESSION['uid'] = $uid; current_user(true); ua(true);

    // Three OPEN leads (eligible for 'lost') and two already-closed (not eligible).
    $ids = [];
    foreach (['OPEN','OPEN','OPEN','WON','LOST'] as $i => $st) {
        $pdo->prepare("INSERT INTO leads (ref, company_name, status, created_at, updated_at) VALUES (?,?,?,?,?)")
            ->execute(['BLK-' . $i, 'Co ' . $i, $st, date('c'), date('c')]);
        $ids[] = (int)$pdo->lastInsertId();
    }

    $plan = leads_bulk_plan('lost', $ids);
    t_eq($plan['apply_count'], 3, 'the preview says 3 open leads would be marked lost');
    t_eq($plan['skip_count'], 2, 'the preview says 2 would be left alone (already closed)');
    t_ok($plan['skip'][0]['reason'] === 'already closed', 'the skip reason is surfaced');

    // Now actually run it — the count must match the preview exactly.
    $msg = leads_bulk('lost', $ids);
    t_ok(strpos($msg, '3 leads updated') !== false, 'the executor marks exactly the 3 the preview promised');
    $stillOpen = (int)ops_val("SELECT COUNT(*) FROM leads WHERE id IN (" . implode(',', $ids) . ") AND status='OPEN'");
    t_eq($stillOpen, 0, 'no open lead is left behind');
    $wonUntouched = (string)ops_val("SELECT status FROM leads WHERE id=?", [$ids[3]]);
    t_eq($wonUntouched, 'WON', 'the already-won lead is not overwritten with a loss');
} finally {
    if ($savedUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $savedUid;
    current_user(true); ua(true);
    if ($own && db()->inTransaction()) db()->rollBack();
}
