<?php
// Module 43 — Training / competence. There is no dedicated training register — competence
// (certs/authorisations/witness) is the spine, and the matrix shows only per-inspector COUNTS.
// Add competence_training_watch(): the actionable cross-inspector "who + which ticket" drill-down of
// lapsed / soon-to-refresh certificates. Read-only; changes nothing. First coverage of this surface.
t_section('Module 43 — training & certification watch');

$lib = file_get_contents(__DIR__ . '/../lib/competence.php');

t_ok(function_exists('competence_training_watch'), 'competence_training_watch() exists');
t_ok(function_exists('competence_training_watch_counts'), 'competence_training_watch_counts() exists');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    $pdo = db();

    $mkIns = function ($name, $status = 'ACTIVE') use ($pdo) { $pdo->prepare("INSERT INTO inspectors (name, status) VALUES (?, ?)")->execute([$name, $status]); return (int)$pdo->lastInsertId(); };
    $mkCert = function ($ins, $name, $validTo, $mand = 1) use ($pdo) {
        $pdo->prepare("INSERT INTO inspector_certs (inspector_id, name, number, valid_to, is_mandatory) VALUES (?,?,'C-1',?,?)")
            ->execute([$ins, $name, $validTo, $mand]);
    };
    $active   = $mkIns('Active Anne');
    $inactive = $mkIns('Retired Ray', 'INACTIVE');

    $mkCert($active, 'NDT Level II', date('Y-m-d', strtotime('-10 days')));       // lapsed
    $mkCert($active, 'Safety Induction', date('Y-m-d', strtotime('+20 days')));   // expiring soon
    $mkCert($active, 'First Aid', date('Y-m-d', strtotime('+400 days')));         // far future — not on watch
    $mkCert($inactive, 'Old Ticket', date('Y-m-d', strtotime('-5 days')));        // lapsed but inactive engineer

    $w = competence_training_watch(45);
    $certs = array_column($w, 'cert');
    t_ok(in_array('NDT Level II', $certs, true), 'a lapsed certificate is on the watch');
    t_ok(in_array('Safety Induction', $certs, true), 'a certificate expiring within the window is on the watch');
    t_ok(!in_array('First Aid', $certs, true), 'a certificate far from expiry is NOT on the watch');
    t_ok(!in_array('Old Ticket', $certs, true), 'an inactive engineer\'s lapsed cert is not chased');

    // Lapsed sorts before expiring.
    t_eq($w[0]['state'], 'lapsed', 'lapsed certificates are listed first (most urgent)');
    $ndt = null; foreach ($w as $x) if ($x['cert'] === 'NDT Level II') $ndt = $x;
    t_ok($ndt && $ndt['days'] < 0, 'a lapsed cert reports a negative days-to-expiry');
    t_ok($ndt && (int)$ndt['inspector_id'] === $active, 'the watch names the exact inspector');

    $c = competence_training_watch_counts(45);
    t_eq($c['lapsed'], 1, 'the counts split lapsed');
    t_eq($c['expiring'], 1, 'the counts split expiring');
    t_eq($c['total'], 2, 'the total excludes far-future and inactive');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- wiring / preservation ----
$view = file_get_contents(__DIR__ . '/../views/ops/competence.php');
t_ok(strpos($view, 'Training &amp; certification watch') !== false, 'the competence screen shows the training watch panel');
t_ok(strpos($lib, "'trainWatch' => competence_training_watch()") !== false, 'the watch is passed to the competence view');
t_ok(strpos($lib, 'function competence_matrix') !== false, 'the existing matrix is unchanged (additive drill-down)');
t_ok(strpos($lib, "i.status='ACTIVE'") !== false, 'the watch is scoped to active engineers');
