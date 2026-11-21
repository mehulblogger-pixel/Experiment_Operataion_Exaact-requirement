<?php
// ============================================================================
//  DEF-0001 (QA audit V51, 15 Nov 2026) — the same New-inquiry form sent twice
//  (a double click, or browser Back and "Create inquiry" again) saves ONCE.
//
//  Found by the audit's demonstration test DEMO-01: Back + submit made a second,
//  identical inquiry (INQ-00002). The quotation form already carried a one-time
//  key (SA-26); the inquiry form now carries the same.
// ============================================================================
t_section('DEF-0001 — a New-inquiry form sent twice saves once');

$root   = dirname(__DIR__);
$engine = $GLOBALS['__test_engine'] ?? 'sqlite';
$env    = $engine === 'sqlite'
    ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
    : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
      . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
$drive = function ($uid, $route, $method = 'POST', array $get = [], array $post = [], array $needles = [], array $session = []) use ($root, $env) {
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_sales_route_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route)
         . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post))
         . ' ' . escapeshellarg(json_encode(array_values($needles))) . ' ' . escapeshellarg(json_encode((object) $session));
    $raw = (string) shell_exec($cmd . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300), 'has' => []];
};
$said = fn($r) => implode(' | ', array_map(fn($f) => (string)($f['text'] ?? ''), $r['flash'] ?? [])) . (!empty($r['error']) ? ' [' . $r['error'] . ']' : '');

$pdo = db(); crm_migrate();
$tag = 'd1' . getmypid();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES (?,1)")->execute(['DEF1 Office ' . $tag]);
$off = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id) VALUES (?,?,?,?,?,1,0,?)")
    ->execute([$tag . '_mex', 'Dina', 'Def', '', 'MARKETING_EXECUTIVE', $off]);
$MEX = (int) $pdo->lastInsertId();
$subject = 'DEF-0001 double submit ' . $tag;
$post = fn(array $x = []) => $x + ['client_name' => 'DEF1 Client ' . $tag, 'contact_name' => 'Ravi', 'contact_email' => 'ravi@def1.test',
    'sbu' => 'IND', 'source' => 'EMAIL', 'received_date' => date('Y-m-d'), 'subject' => $subject];
$count = fn() => (int) ops_val("SELECT COUNT(*) FROM crm_inquiries WHERE subject=?", [$subject]);

try {
    // The form offers the key on a new inquiry only.
    $r = $drive($MEX, 'inquiry-new', 'GET', [], [], ['name="form_once"']);
    t_ok(!empty($r['has']['name="form_once"']), 'the New-inquiry form carries a one-time key');

    $r1 = $drive($MEX, 'inquiry-new', 'POST', [], $post(['form_once' => 'inq-key-1']));
    t_eq($count(), 1, 'the first send creates the inquiry: ' . $said($r1));
    $made = (int) ops_val("SELECT MAX(id) FROM crm_inquiries WHERE subject=?", [$subject]);

    // The same form again, in the same browser session (the key is now used).
    $r2 = $drive($MEX, 'inquiry-new', 'POST', [], $post(['form_once' => 'inq-key-1']), [], ['inquiry_form_once' => ['inq-key-1' => $made]]);
    t_eq($count(), 1, 'the same form sent again does not make a second inquiry');
    t_ok(strpos($said($r2), 'already saved') !== false, '…it opens the one already saved: ' . $said($r2));

    // Browser Back + send again: the page is fetched afresh, so the form carries a NEW key with the
    // same details. Within 15 minutes, by the same person, it is not saved — the form says so.
    $r3 = $drive($MEX, 'inquiry-new', 'POST', [], $post(['form_once' => 'inq-key-2']), ['Save it as a separate', '/inquiry?id=' . $made]);
    t_eq($count(), 1, 'Back + send again (a fresh key, same client and subject, minutes later) does not make a second inquiry');
    t_ok(!empty($r3['has']['Save it as a separate']) && !empty($r3['has']['/inquiry?id=' . $made])  /* Stage 3: the link opens the inquiry page */, '…the form says it was already saved, links to it, and offers "Save it as a separate inquiry"');
    // Ticked: it really is a separate inquiry — saved.
    $drive($MEX, 'inquiry-new', 'POST', [], $post(['form_once' => 'inq-key-2', 'dup_ok' => '1']));
    t_eq($count(), 2, 'with "Save it as a separate inquiry" ticked, it is saved — nothing legitimate is blocked');
    // Another subject for the same client is not held at all.
    $drive($MEX, 'inquiry-new', 'POST', [], $post(['form_once' => 'inq-key-5', 'subject' => $subject . ' (different job)']));
    t_eq((int) ops_val("SELECT COUNT(*) FROM crm_inquiries WHERE subject=?", [$subject . ' (different job)']), 1, 'a different subject for the same client saves at once');
    // Another person sending the same details is not held either.
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id) VALUES (?,?,?,?,?,1,0,?)")
        ->execute([$tag . '_mex2', 'Omar', 'Def', '', 'MARKETING_EXECUTIVE', $off]);
    $MEX2 = (int) $pdo->lastInsertId();
    $drive($MEX2, 'inquiry-new', 'POST', [], $post(['form_once' => 'inq-key-6']));
    t_eq($count(), 3, 'the same details from a different person are saved (only one\'s own repeat is held)');
    // Older than 15 minutes: no longer treated as a repeat.
    $pdo->prepare("UPDATE crm_inquiries SET created_at=? WHERE subject=?")->execute([date('c', time() - 3600), $subject]);
    $drive($MEX, 'inquiry-new', 'POST', [], $post(['form_once' => 'inq-key-7']));
    t_eq($count(), 4, 'an hour later the same details are a new inquiry, as before');

    // A refused send (missing contact) keeps its key, so correcting it and sending again saves once.
    $before = (int) ops_val("SELECT COUNT(*) FROM crm_inquiries");
    $r4 = $drive($MEX, 'inquiry-new', 'POST', [], $post(['form_once' => 'inq-key-3', 'contact_name' => '']), ['inq-key-3']);
    t_eq((int) ops_val("SELECT COUNT(*) FROM crm_inquiries"), $before, 'a refused send saves nothing');
    t_ok(!empty($r4['has']['inq-key-3']), '…and the form drawn again keeps the same key');

    // Edits are not affected (no key on an existing inquiry).
    $r5 = $drive($MEX, 'inquiry-edit', 'GET', ['id' => $made], [], ['name="form_once"']);
    t_ok(empty($r5['has']['name="form_once"']), 'an existing inquiry\'s form carries no key — editing works as before');
} finally {
    $pdo->prepare("DELETE FROM crm_inquiries WHERE subject LIKE ?")->execute([$subject . '%']);
    $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$tag . '_mex2']);
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$MEX]);
    $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$off]);
}
