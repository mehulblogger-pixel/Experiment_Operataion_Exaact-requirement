<?php
// ============================================================================
//  DEF-SAL-037 (QA audit V51, 15 Nov 2026) — New quotation, browser Back, and
//  "Create quote" again made a SECOND quotation. The one-time key (SA-26) only
//  catches a double click: Back fetches the form afresh with a new key. The same
//  person, customer and subject within 15 minutes is now treated as a repeat.
// ============================================================================
t_section('DEF-SAL-037 — Back + Create quote saves one quotation');

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
$tag = 's37' . getmypid();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES (?,1)")->execute(['S37 Office ' . $tag]);
$off = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id) VALUES (?,?,?,?,?,1,0,?)")
    ->execute([$tag . '_mex', 'Qira', 'Dup', '', 'MARKETING_EXECUTIVE', $off]);
$MEX = (int) $pdo->lastInsertId();
$subject = 'DEF-SAL-037 back and create ' . $tag;
$post = fn(array $x = []) => $x + ['client_name' => 'S37 Client ' . $tag, 'contact_name' => 'Ravi', 'sbu' => 'IND', 'subject' => $subject,
    'office_id' => $off, 'currency' => 'INR', 'validity_days' => 30, 'gst_pct' => 18,
    'l_desc' => ['Stage inspection'], 'l_qty' => ['2'], 'l_rate' => ['7999.99'], 'l_unit' => ['MAN_DAY'], 'l_service' => [''], 'l_order' => [''], 'l_sbu' => ['IND']];
$count = fn($s = null) => (int) ops_val("SELECT COUNT(*) FROM quotations WHERE subject=?", [$s ?? $subject]);

try {
    $r1 = $drive($MEX, 'quote-new', 'POST', [], $post(['form_once' => 'q-key-1']));
    t_eq($count(), 1, 'the first Create makes the quotation: ' . $said($r1));
    $made = (int) ops_val("SELECT MAX(id) FROM quotations WHERE subject=?", [$subject]);
    $no = (string) ops_val("SELECT quote_no FROM quotations WHERE id=?", [$made]);

    // Double click (same key) — unchanged behaviour: opens the saved one.
    $r2 = $drive($MEX, 'quote-new', 'POST', [], $post(['form_once' => 'q-key-1']), [], ['quote_form_once' => ['q-key-1' => $made]]);
    t_eq($count(), 1, 'the same form sent twice (double click) saves once');
    t_ok(strpos($said($r2), 'already saved') !== false, '…and opens the one already saved: ' . $said($r2));

    // Back + Create: a fresh key, same details, minutes later.
    $r3 = $drive($MEX, 'quote-new', 'POST', [], $post(['form_once' => 'q-key-2']), ['Save it as a separate', '/quote?id=' . $made, 'not saved again', '(' . $no . ')']);
    t_eq($count(), 1, 'Back + Create quote again (fresh key, same customer and subject) does not make a second quotation');
    t_ok(!empty($r3['has']['not saved again']) && !empty($r3['has']['(' . $no . ')']), '…the page says ' . $no . ' was saved a few minutes ago and was not saved again');
    t_ok(!empty($r3['has']['Save it as a separate']) && !empty($r3['has']['/quote?id=' . $made]), '…the form links to it and offers "Save it as a separate quotation"');

    // A deliberate second quotation with a different scope (other lines) is never held.
    $drive($MEX, 'quote-new', 'POST', [], $post(['form_once' => 'q-key-6', 'l_desc' => ['Stage inspection — revised scope'], 'l_qty' => ['3'], 'l_rate' => ['12000']]));
    t_eq($count(), 2, '"Quote again" with a revised scope (different lines) is saved at once');
    $pdo->prepare("DELETE FROM quote_lines WHERE quote_id=(SELECT MAX(id) FROM quotations WHERE subject=?)")->execute([$subject]);
    $pdo->prepare("DELETE FROM quotations WHERE id=(SELECT MAX(id) FROM (SELECT id FROM quotations WHERE subject=?) x)")->execute([$subject]);
    // Once the first has moved on (lost, sent …), the same details again are a new quotation.
    $pdo->prepare("UPDATE quotations SET status='LOST' WHERE id=?")->execute([$made]);
    $drive($MEX, 'quote-new', 'POST', [], $post(['form_once' => 'q-key-7']));
    t_eq($count(), 2, 'after the first is lost, the same details make a new quotation (it is not a repeat of a draft)');
    $pdo->prepare("DELETE FROM quote_lines WHERE quote_id=(SELECT MAX(id) FROM quotations WHERE subject=?)")->execute([$subject]);
    $pdo->prepare("DELETE FROM quotations WHERE id=(SELECT MAX(id) FROM (SELECT id FROM quotations WHERE subject=?) x)")->execute([$subject]);
    $pdo->prepare("UPDATE quotations SET status='DRAFT' WHERE id=?")->execute([$made]);

    // Ticked: saved as a genuine second quotation.
    $drive($MEX, 'quote-new', 'POST', [], $post(['form_once' => 'q-key-2', 'dup_ok' => '1']));
    t_eq($count(), 2, 'with "Save it as a separate quotation" ticked, it is saved — nothing legitimate is blocked');
    // A different subject for the same customer is not held.
    $drive($MEX, 'quote-new', 'POST', [], $post(['form_once' => 'q-key-3', 'subject' => $subject . ' (other job)']));
    t_eq($count($subject . ' (other job)'), 1, 'a different subject for the same customer saves at once');
    // Another person with the same details is not held.
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id) VALUES (?,?,?,?,?,1,0,?)")
        ->execute([$tag . '_mex2', 'Omar', 'Dup', '', 'MARKETING_EXECUTIVE', $off]);
    $MEX2 = (int) $pdo->lastInsertId();
    $drive($MEX2, 'quote-new', 'POST', [], $post(['form_once' => 'q-key-4']));
    t_eq($count(), 3, 'the same details from a different person are saved');
    // Older than 15 minutes: a new quotation as before.
    $pdo->prepare("UPDATE quotations SET created_at=? WHERE subject=?")->execute([date('c', time() - 3600), $subject]);
    $drive($MEX, 'quote-new', 'POST', [], $post(['form_once' => 'q-key-5']));
    t_eq($count(), 4, 'an hour later the same details make a new quotation, as before');

    // DEF-SAL-018 / DEF-SAL-001 — paise and lakh grouping on the quotation page.
    $r6 = $drive($MEX, 'quote', 'GET', ['id' => $made], [], ['7,999.99', '15,999.98']);
    t_ok(!empty($r6['has']['7,999.99']) && !empty($r6['has']['15,999.98']), 'the quotation page shows the rate ₹7,999.99 and the line ₹15,999.98 with their paise (not ₹8,000 / ₹16,000)');
} finally {
    $ids = array_map('intval', array_column(ops_all("SELECT id FROM quotations WHERE subject LIKE ?", [$subject . '%']), 'id'));
    foreach ($ids as $qid) foreach (['quote_lines', 'quote_locations', 'quote_log', 'quote_approvals'] as $tb) { try { $pdo->prepare("DELETE FROM $tb WHERE quote_id=?")->execute([$qid]); } catch (Throwable $e) {} }
    $pdo->prepare("DELETE FROM quotations WHERE subject LIKE ?")->execute([$subject . '%']);
    $pdo->prepare("DELETE FROM users WHERE username IN (?,?)")->execute([$tag . '_mex', $tag . '_mex2']);
    $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$off]);
}
