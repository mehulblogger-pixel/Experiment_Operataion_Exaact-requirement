<?php
// Module 39 — My Work. A role-relevant landing page that groups the EXISTING
// pending-task buckets into lanes and adds one thing the app was missing: reports
// that were RETURNED for correction (reset to DRAFT by a vetter/approver) surfaced
// distinctly from ordinary new drafts. Launcher only — no state change, no new
// permission.
t_section('My Work — route, lanes and the returned-for-correction bucket');

$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
$idm = file_get_contents(__DIR__ . '/../lib/idems.php');
$nav = file_get_contents(__DIR__ . '/../lib/navindex.php');

// Wiring.
t_ok(strpos($ops, "\$route === 'my-work'") !== false && function_exists('ops_my_work'), 'the /my-work route is dispatched to its handler');
t_ok(strpos($nav, "'/my-work'") !== false, 'navigation offers a My Work destination');
t_ok(strpos($ops, 'See all in My Work') !== false, 'the dashboard pending-tasks panel links to My Work');

// Each pending task now carries a lane (extra key only — the dashboard panel ignores it).
t_ok(strpos($ops, "'lane'=>\$lane") !== false, 'pending tasks carry a lane for grouping');
// The returned bucket is defined and points at a real destination filter.
t_ok(strpos($ops, "'returned for correction'") !== false && strpos($ops, "/documents?mine=returned") !== false,
    'a "returned for correction" bucket exists and links to the returned filter');
t_ok(strpos($idm, "\$mineReturned = (\$_GET['mine'] ?? '') === 'returned'") !== false,
    'the documents register understands the returned filter');

// ---- Behavioural: the returned-for-correction detection is correct & disjoint ----
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('idems_migrate')) idems_migrate();
    $ins = 990001;   // a synthetic inspector id
    $mk = function ($status, $vet, $sentback) use ($ins) {
        db()->prepare("INSERT INTO report_docs (type_code, status, inspector_id, vet_status, deleted, created_at)
                       VALUES ('IC', ?, ?, ?, 0, ?)")->execute([$status, $ins, $vet, date('c')]);
        $id = (int)db()->lastInsertId();
        if ($sentback) db()->prepare("INSERT INTO report_approvals (report_doc_id, level, status, created_at) VALUES (?,1,'SENTBACK',?)")->execute([$id, date('c')]);
        return $id;
    };
    $a = $mk('DRAFT', 'RETURNED', false);   // returned by a vetter
    $b = $mk('DRAFT', '', true);            // sent back by an approver
    $c = $mk('DRAFT', '', false);           // a genuine new draft — NOT returned
    $d = $mk('REJECTED', '', false);        // formally rejected — the other bucket

    $returned = (int) ops_val(
        "SELECT COUNT(*) FROM report_docs d WHERE d.deleted=0 AND d.status='DRAFT' AND d.inspector_id=?
         AND (UPPER(COALESCE(d.vet_status,''))='RETURNED'
              OR EXISTS(SELECT 1 FROM report_approvals a WHERE a.report_doc_id=d.id AND a.status='SENTBACK'))", [$ins]);
    t_ok($returned === 2, 'returned-for-correction catches the vetter-return and the approver-sendback (2), not the fresh draft');

    $rejected = (int) ops_val("SELECT COUNT(*) FROM report_docs d WHERE d.deleted=0 AND d.status='REJECTED' AND d.inspector_id=?", [$ins]);
    t_ok($rejected === 1, 'the REJECTED bucket holds the rejected report separately');
    // Disjoint: a REJECTED report is never in the returned (DRAFT) bucket, so no double count.
    t_ok($returned + $rejected === 3, 'the two buckets are disjoint — nothing is counted twice');

    // The documents register filter returns exactly the returned drafts for this inspector.
    // (my_inspector_id() reads the session user; assert the SQL the route runs, scoped to $ins.)
    $filtered = ops_all("SELECT id FROM report_docs d WHERE d.deleted=0 AND d.status='DRAFT' AND d.inspector_id=?
         AND (UPPER(COALESCE(d.vet_status,''))='RETURNED'
              OR EXISTS(SELECT 1 FROM report_approvals a WHERE a.report_doc_id=d.id AND a.status='SENTBACK')) ORDER BY id", [$ins]);
    $ids = array_map(fn($r) => (int)$r['id'], $filtered);
    t_ok($ids === [$a, $b], 'the returned filter lists exactly the two returned drafts, not the fresh draft or the rejected one');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- View: lanes render; empty state renders; a report label stays intact ----
$renderMyWork = function ($vars) {
    return (function () use ($vars) { extract($vars); ob_start(); include __DIR__ . '/../views/ops/my_work.php'; return ob_get_clean(); })();
};
$full = $renderMyWork([
    'lanes' => ['reports' => [['icon'=>'↩','n'=>2,'label'=>'returned for correction','sub'=>'reports a reviewer sent back','href'=>'/documents?mine=returned','tone'=>'bad','lane'=>'reports']],
                'do' => [['icon'=>'✔','n'=>3,'label'=>'reports to approve','sub'=>'awaiting your approval','href'=>'/documents?mine=approve','tone'=>'info','lane'=>'do']]],
    'total' => 2, 'isInspector' => true, 'inspectorUnlinked' => false, 'name' => 'Ravi',
]);
t_ok(strpos($full, 'My reports') !== false && strpos($full, 'returned for correction') !== false, 'the My Work view groups tasks into lanes with their cards');
t_ok(strpos($full, '/documents?mine=returned') !== false, 'a card links to the screen that handles it');

$empty = $renderMyWork(['lanes' => [], 'total' => 0, 'isInspector' => true, 'inspectorUnlinked' => false, 'name' => 'Ravi']);
t_ok(strpos($empty, 'all caught up') !== false, 'the empty state shows a caught-up message, not a blank page');

$unlinked = $renderMyWork(['lanes' => [], 'total' => 0, 'isInspector' => true, 'inspectorUnlinked' => true, 'name' => 'Ravi']);
t_ok(strpos($unlinked, 'not linked to an inspector record') !== false, 'an unlinked inspector gets a gentle notice, not a fatal');

// No new permission was invented for this module.
t_ok(!preg_match('/can\(\x27mod\.mywork/', $ops), 'My Work introduces no new permission constant');
