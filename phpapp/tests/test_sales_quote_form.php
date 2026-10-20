<?php
// ============================================================================
//  An honest quotation form (Sales audit Stage 2.2 / 2.3 — SA-24, SA-26, SA-43).
//
//  Before: an empty submit created a numbered ₹0 quotation with no customer; a
//  refused save threw away everything typed; a double click made two; the
//  second of two people editing at once silently won; a line could point at
//  another quotation's site, and posting that site's number deleted this
//  quotation's own site; a new quotation could not pair a line with a site
//  until it had been saved once. Each case is driven through the real route.
// ============================================================================
t_section('Sales — the quotation form keeps your work and refuses an offer of nothing');

$root   = dirname(__DIR__);
$engine = $GLOBALS['__test_engine'] ?? 'sqlite';
$env    = $engine === 'sqlite'
    ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
    : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
      . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
$drive = function ($uid, $route, $method = 'POST', array $get = [], array $post = [], array $needles = [], array $session = []) use ($root, $env) {
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_sales_route_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route)
         . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post))
         . ' ' . escapeshellarg(json_encode($needles)) . ' ' . escapeshellarg(json_encode((object) $session));
    $raw = (string) shell_exec($cmd . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300), 'has' => []];
};
$said = fn($r) => implode(' | ', array_map(fn($f) => (string)($f['text'] ?? ''), $r['flash'] ?? []));
$pdo = db();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES ('QF Office',1)")->execute();
$off = (int) $pdo->lastInsertId();
$pdo->prepare("DELETE FROM users WHERE username='qf_bdm'")->execute();
$pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id)
               VALUES ('qf_bdm','Qf','Probe','','BUSINESS_DEV_MANAGER',1,0,?)")->execute([$off]);
$BDM = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES ('QF Client','QF Client',1,'ACTIVE')")->execute();
$cli = (int) $pdo->lastInsertId();
$count = fn() => (int) ops_val("SELECT COUNT(*) FROM quotations");
$base = fn(array $x = []) => array_merge(['client_id' => $cli, 'subject' => 'Third-party inspection', 'office_id' => $off, 'validity_days' => 30,
    'gst_pct' => 18, 'l_desc' => ['Kept line text'], 'l_qty' => ['2'], 'l_unit' => ['MANDAY'], 'l_rate' => ['8000']], $x);

// ---- a draft needs a customer and a subject — and a refusal keeps the typing ---------------
$c0 = $count();
// A refused save draws the form again, so its message is ON that page (the
// layout shows and clears it there), not left waiting in the session.
$r = $drive($BDM, 'quote-new', 'POST', [], [], ['Say who the']);
t_eq($count(), $c0, 'an empty New quote no longer creates a numbered quotation');
t_ok(!empty($r['has']['Say who the']), '…the form says what is needed');
$r = $drive($BDM, 'quote-new', 'POST', [], $base(['client_id' => '', 'client_name' => 'Typed Prospect Co', 'subject' => '']),
            ['Typed Prospect Co', 'Kept line text', 'Give the']);
t_eq($count(), $c0, 'a quotation with no subject is not saved');
t_ok(!empty($r['has']['Typed Prospect Co']) && !empty($r['has']['Kept line text']),
     'the form comes back with the customer name and the line typed — nothing is lost');
$r = $drive($BDM, 'quote-new', 'POST', [], $base(['gst_pct' => '500']), ['Kept line text', 'Third-party inspection']);
t_eq($count(), $c0, 'a quotation with GST 500 % is not saved');
t_ok(!empty($r['has']['Kept line text']) && !empty($r['has']['Third-party inspection']), '…and that refusal keeps the typing too');
$drive($BDM, 'quote-new', 'POST', [], $base(['validity_days' => '-5']));
t_eq($count(), $c0, 'a negative validity (which would never expire) is refused');

// ---- a double click saves once ---------------------------------------------------------
$drive($BDM, 'quote-new', 'POST', [], $base(['form_once' => 'qf-key-1']));
t_eq($count(), $c0 + 1, 'the first save creates the quotation');
$made = (int) ops_val("SELECT MAX(id) FROM quotations");
$r = $drive($BDM, 'quote-new', 'POST', [], $base(['form_once' => 'qf-key-1']), [], ['quote_form_once' => ['qf-key-1' => $made]]);
t_eq($count(), $c0 + 1, 'the same form sent again in the same session does not make a second one');
t_ok(strpos($said($r), 'already saved') !== false, '…it opens the one already saved: ' . $said($r));

// ---- two people editing at once ------------------------------------------------------------
$ver = (string) ops_val("SELECT updated_at FROM quotations WHERE id=?", [$made]);
$drive($BDM, 'quote-edit', 'POST', ['id' => $made], $base(['row_version' => $ver, 'contact_name' => 'First editor']));
t_eq((string) ops_val("SELECT contact_name FROM quotations WHERE id=?", [$made]), 'First editor', 'the first of two editors saves');
$r = $drive($BDM, 'quote-edit', 'POST', ['id' => $made], $base(['row_version' => $ver, 'contact_name' => 'Second editor']), ['Second editor', 'Someone else saved']);
t_eq((string) ops_val("SELECT contact_name FROM quotations WHERE id=?", [$made]), 'First editor',
     'the second, working from the same old copy, does not silently overwrite the first');
t_ok(!empty($r['has']['Someone else saved']) && !empty($r['has']['Second editor']),
     '…they are told, and their own typing is still on the screen to re-apply');

// ---- an offer of nothing cannot be submitted or sent ------------------------------------------
$pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, status, client_id, client_name, subject, office_id, subtotal, total_amount, owner_id, created_by, created_at)
               VALUES ('QF-EMPTY',0,1,'DRAFT',?,'QF Client','Nothing',?,0,0,?,'qf_bdm',?)")->execute([$cli, $off, $BDM, date('c')]);
$empty = (int) $pdo->lastInsertId();
$r = $drive($BDM, 'quote-status', 'POST', ['id' => $empty], ['to' => 'PENDING_APPROVAL']);
t_eq((string) ops_val("SELECT status FROM quotations WHERE id=?", [$empty]), 'DRAFT', 'a quotation with no priced line cannot be submitted for approval');
t_ok(strpos($said($r), 'at least one line with a quantity and a rate') !== false, '…and it says what is missing: ' . $said($r));
setting_set('quote_unmatched_approval', 'none');
$drive($BDM, 'quote-status', 'POST', ['id' => $empty], ['to' => 'SENT']);
t_eq((string) ops_val("SELECT status FROM quotations WHERE id=?", [$empty]), 'DRAFT', '…nor sent, even where no approval is needed');
setting_set('quote_unmatched_approval', '');

// ---- sites belong to their own quotation ----------------------------------------------------
$site = function ($qid, $label) use ($pdo) {
    $pdo->prepare("INSERT INTO quote_locations (quote_id, label, location_type, line1, city, country, sort_order) VALUES (?,?,'SITE','Plot 1',?,'India',0)")
        ->execute([$qid, $label, $label]);
    return (int) $pdo->lastInsertId();
};
$other = $count(); $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, status, client_name, subject, office_id, created_at) VALUES ('QF-OTHER',0,1,'DRAFT','x','x',?,?)")->execute([$off, date('c')]);
$otherQ = (int) $pdo->lastInsertId();
$foreign = $site($otherQ, 'Other quote site');
$mine    = $site($made, 'My own site');
$ver = (string) ops_val("SELECT updated_at FROM quotations WHERE id=?", [$made]);
$drive($BDM, 'quote-edit', 'POST', ['id' => $made], $base(['row_version' => $ver,
    'loc_id' => [(string) $foreign], 'loc_label' => ['Posted site'], 'loc_line1' => ['Survey No 9'], 'loc_city' => ['Vapi'],
    'l_location' => [(string) $foreign]]));
t_eq((string) ops_val("SELECT label FROM quote_locations WHERE id=?", [$foreign]), 'Other quote site',
     'posting another quotation\'s site number does not touch that quotation\'s site');
t_eq((int) ops_val("SELECT COUNT(*) FROM quote_locations WHERE quote_id=? AND label='Posted site'", [$made]), 1,
     '…the posted address is saved as a new site of this quotation, not dropped');
t_ok(ops_val("SELECT location_id FROM quote_lines WHERE quote_id=?", [$made]) === null
     || (int) ops_val("SELECT location_id FROM quote_lines WHERE quote_id=?", [$made]) !== $foreign,
     'a line can never point at another quotation\'s site');

// A brand-new quotation pairs a line with its second site before it is ever saved.
$drive($BDM, 'quote-new', 'POST', [], $base([
    'loc_id' => ['0', '0'], 'loc_label' => ['Vapi works', 'Hazira yard'], 'loc_line1' => ['GIDC', 'Port road'], 'loc_city' => ['Vapi', 'Surat'],
    'l_desc' => ['At Hazira'], 'l_location' => ['new:1']]));
$nq = (int) ops_val("SELECT MAX(id) FROM quotations");
$hz = (int) ops_val("SELECT id FROM quote_locations WHERE quote_id=? AND label='Hazira yard'", [$nq]);
t_ok($hz > 0 && (int) ops_val("SELECT location_id FROM quote_lines WHERE quote_id=?", [$nq]) === $hz,
     'on a new quotation, a line chosen for the second site is saved against that site');
t_ok(strpos((string) file_get_contents($root . '/views/ops/crm/quote_form.php'), "'new:' + i") !== false,
     'the form offers a not-yet-saved site to the lines by its row');
