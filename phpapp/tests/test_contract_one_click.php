<?php
// ============================================================================
//  THE CONTRACT NUMBER IS ENTERED ONCE, NEVER TWICE, AND CAN BE PUT RIGHT.
//
//  The owner's three complaints, in his words:
//
//    "Contact number duplicate creation shall not be allowed. Also Contract
//     details shall be fetched automatically from Purchase order and other
//     details previously filled. Hence no duplicate entry is required. If
//     contact number is edited there must be an option to delete and edit it."
//
//    "I feel that contract number generation must be single click taking all
//     required entry automatically."
//
//    "When we have already entered the details in the Client under PO and
//     contract, again during inspection what inspection must be prefilled if
//     that information is already there. This shall be two way if it is not
//     there then it must be linked to appropriate PO and Contract accordingly."
//
//  Three complaints, one root cause: the contract number was treated as a
//  string somebody types, when it is in fact the join key that threads quote ->
//  order -> work order -> job -> invoice together. Because it was typed, it got
//  typed twice and typed wrongly; because it was the join key, a wrong one could
//  never be corrected; and because nothing read it forward, every screen after it
//  asked for the same facts again.
//
//  So: the data is inherited (nothing re-typed), a duplicate is named before it
//  is created, and a wrong number is corrected by moving EVERYTHING filed under
//  it at once — never half of it.
//
//  What this file does NOT touch: the two signatures. The owner chose to keep
//  the endorse-then-approve control and make the DATA one click, not the
//  approval. Any change to that is a change to the permission matrix.
// ============================================================================

t_section('Contract number — one click, no duplicates, correctable');

t_ok(function_exists('contract_prefill'),          'ARMING · the inheritance reader exists');
t_ok(function_exists('contract_duplicate_check'),  'ARMING · the duplicate check exists');
t_ok(function_exists('contract_renumber'),         'ARMING · the correction exists');
t_ok(function_exists('call_commercial_prefill'),   'ARMING · the work-order inheritance reader exists');
t_ok(function_exists('call_link_candidates'),      'ARMING · the reverse link reader exists');
t_ok(function_exists('call_link_commercial'),      'ARMING · the reverse link writer exists');

$pdo = db();
$uid = t_as_admin();

// ---- fixtures ---------------------------------------------------------------
$office = function ($name) use ($pdo) {
    $ex = ops_one("SELECT id FROM offices WHERE name=?", [$name]);
    if ($ex) return (int) $ex['id'];
    $pdo->prepare("INSERT INTO offices (name, code, is_active) VALUES (?,?,1)")->execute([$name, 'ZZC']);
    return (int) $pdo->lastInsertId();
};
$client = function ($name, $branch = null) use ($pdo) {
    $pdo->prepare("INSERT INTO business_partners (code, legal_name, display_name, home_branch_id, is_client, status, created_at)
                   VALUES (?,?,?,?,1,'ACTIVE',?)")
        ->execute(['ZC' . mt_rand(100000, 999999), $name, $name, $branch, date('c')]);
    return (int) $pdo->lastInsertId();
};
$contract = function ($partnerId, $no, $status = 'OPEN', $start = '', $end = '', $branch = null) use ($pdo) {
    $pdo->prepare("INSERT INTO partner_contracts (partner_id, contract_number, title, value, start_date, end_date, open_status, is_active, branch_id)
                   VALUES (?,?,?,?,?,?,?,1,?)")
        ->execute([$partnerId, $no, 'ZZ contract ' . $no, 100000, $start, $end, $status, $branch]);
    return (int) $pdo->lastInsertId();
};
$po = function ($partnerId, $number, $value, $start = '', $end = '', $contractId = null, $quoteId = null) use ($pdo) {
    $pdo->prepare("INSERT INTO partner_purchase_orders (partner_id, contract_id, po_number, po_type, title, value, start_date, end_date, is_active, quotation_id)
                   VALUES (?,?,?, 'REGULAR', ?,?,?,?,1,?)")
        ->execute([$partnerId, $contractId, $number, 'ZZ order ' . $number, $value, $start, $end, $quoteId]);
    return (int) $pdo->lastInsertId();
};
$poLine = function ($poId, $desc, $qty, $rate, $unit = 'MANDAY', $consumed = 0) use ($pdo) {
    $pdo->prepare("INSERT INTO po_line_items (purchase_order_id, description, item_type, quantity, rate, consumed) VALUES (?,?,?,?,?,?)")
        ->execute([$poId, $desc, $unit, $qty, $rate, $consumed]);
    return (int) $pdo->lastInsertId();
};

$off  = $office('ZZC Office');
$acme = $client('ZZC Acme Steel', $off);
$rival = $client('ZZC Rival Pipes', $off);

t_ok($acme && $rival, 'ARMING · two unrelated clients exist');

// ============================================================================
//  1 — THE SCHEMA INVARIANT BEHIND THE WHOLE THING
//
//  Every table that files something under a contract number must be in the
//  rename list, or a correction moves half the record and silently orphans the
//  rest. And the length limit must be the NARROWEST of those columns, or the
//  same number is stored whole in one place and cut short in another — at which
//  point the joins stop matching and nobody can tell why.
// ============================================================================
t_section('The rename list is complete, and the length limit is the real one');

// Engine-independent column width.
$width = function ($table, $col) {
    if (t_driver() === 'sqlite') {
        foreach (ops_all("PRAGMA table_info(" . $table . ")") ?: [] as $r) {
            if ((string) $r['name'] !== $col) continue;
            if (preg_match('/\((\d+)\)/', (string) $r['type'], $m)) return (int) $m[1];
            return 0;
        }
        return 0;
    }
    return (int) ops_val("SELECT character_maximum_length FROM information_schema.columns
                          WHERE table_schema=DATABASE() AND table_name=? AND column_name=?", [$table, $col]);
};

$narrowest = null;
foreach (contract_no_tables() as $table => $col) {
    if (!t_table_exists($table)) continue;
    t_ok(in_array($col, t_columns($table), true), "$table.$col exists — the rename can reach it");
    $w = $width($table, $col);
    if ($w > 0 && ($narrowest === null || $w < $narrowest)) $narrowest = $w;
}
t_ok($narrowest !== null, 'the contract-number columns were measured');
t_ok(CONTRACT_NO_MAX <= $narrowest,
    'CONTRACT_NO_MAX (' . CONTRACT_NO_MAX . ') fits the narrowest column (' . $narrowest . ') — no silent truncation');

// The other direction: a table that files under a contract number and is NOT in
// the list is the one way a correction can still orphan history. Found by asking
// the live schema, so a table added next year is caught without editing a test.
$listed = array_keys(contract_no_tables());
$strays = [];
foreach (t_tables() as $t) {
    if (in_array($t, $listed, true)) continue;
    // Agency contracts are a different domain entirely — an agency's own
    // engagement letter, not a client contract — so they are deliberately out.
    if ($t === 'agencies') continue;
    // partner_contracts OWNS the number — it is renamed explicitly by
    // contract_renumber() and is not a filer, so it is not in the cascade list.
    if ($t === 'partner_contracts') continue;
    if (in_array('contract_number', t_columns($t), true)) $strays[] = $t;
}
t_eq($strays, [], 'no table files under a contract number without being in the rename list');

// ============================================================================
//  2 — THE DUPLICATE IS NAMED BEFORE IT IS CREATED
// ============================================================================
t_section('Duplicates');

$cAcme = $contract($acme, 'ZZC/C/25-26/00001', 'OPEN', '2026-01-01', '2026-12-31', $off);
t_ok($cAcme > 0, 'ARMING · Acme has one open contract');

$d = contract_duplicate_check($rival, 'ZZC/C/25-26/00001');
t_ok($d['block'] !== '', 'the same number against a DIFFERENT client is refused, not warned about');
t_ok(strpos($d['block'], 'ZZC Acme Steel') !== false, 'and the refusal names who already holds it');

$d = contract_duplicate_check($acme, 'ZZC/C/25-26/00001');
t_eq($d['block'], '', 'the same number against the SAME client is NOT refused — that is a rate contract drawn down again');
t_ok(!empty($d['reuse']), 'and the existing row is handed back to be reused rather than duplicated');
t_ok(count($d['warn']) >= 1, 'but it is said out loud, so nobody thinks they created something new');

// The case the system used to accept in silence.
$d = contract_duplicate_check($acme, 'zzc-c-2526-00001');
t_eq($d['block'], '', 'a punctuation variant is not refused — it is occasionally deliberate');
t_ok(count($d['warn']) >= 1, 'but it IS warned about: same number, written differently');
$joined = implode(' ', $d['warn']);
t_ok(strpos($joined, 'ZZC/C/25-26/00001') !== false, 'and the warning names the contract it would duplicate');

$d = contract_duplicate_check($acme, 'ZZC/C/25-26/00002');
t_eq($d['block'], '', 'a genuinely new number is accepted');
t_eq($d['warn'], [],  'and with no dates given there is nothing to warn about');

// The overlap warning, which is how one order gets registered twice under two numbers.
$d = contract_duplicate_check($acme, 'ZZC/C/25-26/00002', null, ['start_date' => '2026-03-01', 'end_date' => '2026-04-01']);
t_ok(count($d['warn']) >= 1, 'a different number covering the same period is flagged as a possible double registration');

// Length. This is not pedantry: 41 characters would be stored whole on
// partner_contracts and cut to 40 on invoice_lines, and the two would stop matching.
$d = contract_duplicate_check($acme, str_repeat('X', CONTRACT_NO_MAX + 1));
t_ok($d['block'] !== '', 'a number longer than the narrowest column is refused');

t_eq(contract_no_key('AHM/C/25-26/42'), contract_no_key('ahm-c-2526-42'),
    'the sameness test ignores punctuation and case, which is how humans read a number');
t_ok(contract_no_key('A/1') !== contract_no_key('A/2'), 'but it does not ignore the digits');

// ============================================================================
//  3 — NOTHING IS RE-TYPED
// ============================================================================
t_section('Inheritance — the PO and the quotation already said it');

$pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, client_id, client_name, subject, total_amount, status, office_id, accepted_date, created_at)
               VALUES (?,0,1,?,?,?,?, 'ACCEPTED', ?,?,?)")
    ->execute(['ZZC-Q-1', $acme, 'ZZC Acme Steel', 'ZZC inspection of pipe', 500000, $off, '2026-02-01', date('c')]);
$qAcme = (int) $pdo->lastInsertId();

$pf = contract_prefill($qAcme);
t_eq((float) $pf['value'], 500000.0, 'with no purchase order, the value comes from the quotation');
t_eq($pf['start_date'], '2026-02-01', 'and the start date is the day the client accepted, not today');
t_eq($pf['end_date'], '', 'no end date is invented — a quotation\'s validity is how long the PRICE stood');
t_eq((int) $pf['branch_id'], $off, 'the billing branch comes across too');

// Now the purchase order arrives. It is the document that commits the money, so
// it must win over the quotation.
$poAcme = $po($acme, 'ZZC-PO-77', 450000, '2026-02-15', '2026-08-14', null, $qAcme);
$pf = contract_prefill($qAcme);
t_eq((float) $pf['value'], 450000.0, 'once there is a purchase order, ITS value wins — that is the money actually committed');
t_eq($pf['start_date'], '2026-02-15', 'and its dates win');
t_eq($pf['end_date'], '2026-08-14', 'including the end date, which the quotation could not supply');
t_eq((int) $pf['po_id'], $poAcme, 'the order it read from is named, so it can be linked to the contract');
t_ok(strpos(contract_prefill_note($pf), 'ZZC-PO-77') !== false,
    'and the screen can say where each figure came from — a value that appears by itself is a value nobody checks');

// Two unattached orders and nothing is assumed: guessing here would put the
// wrong money on a real contract.
$rq = $client('ZZC Two Orders Ltd', $off);
$po($rq, 'ZZC-PO-A', 111, '', '');
$po($rq, 'ZZC-PO-B', 222, '', '');
$pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, client_id, client_name, subject, total_amount, status, office_id, created_at)
               VALUES (?,0,1,?,?,?,?, 'ACCEPTED', ?,?)")
    ->execute(['ZZC-Q-2', $rq, 'ZZC Two Orders Ltd', 'ZZC two orders', 999, $off, date('c')]);
$q2 = (int) $pdo->lastInsertId();
$pf2 = contract_prefill($q2);
t_eq((int) $pf2['po_id'], 0, 'with two unattached orders, none is assumed');
t_eq(count($pf2['choices']), 2, 'both are offered for the person to pick instead');
t_eq((float) $pf2['value'], 999.0, 'and the value falls back to the quotation rather than to a guess');

// Inheritance must not make a field impossible to clear. The one-click form sends
// no date inputs at all, so the inherited dates are used; the manual form always
// sends them, so an empty one means "there is no end date" and must be respected.
// Quietly putting the inherited value back would be the worst of both worlds.
t_section('An inherited value can still be cleared');
$reg = (string) @file_get_contents(dirname(__DIR__) . '/lib/crm.php');
t_ok(strpos($reg, "isset(\$_POST['end_date'])   ? trim((string)\$_POST['end_date'])") !== false,
    'the register reads a sent-but-empty date as a deliberate blank, not as "use the inherited one"');
t_ok(strpos($reg, "\$_POST['end_date'] ?? '')) ?: (string)(\$pf['end_date']") === false,
    'and the earlier ?: form, which silently restored a cleared date, is gone');
$qdForm = (string) @file_get_contents(dirname(__DIR__) . '/views/ops/crm/quote_detail.php');
$oneClick = strpos($qdForm, 'Register the contract &amp; request opening');
$block = $oneClick !== false ? substr($qdForm, $oneClick - 1800, 1800) : '';
t_ok(strpos($block, 'name="start_date"') === false && strpos($block, 'name="end_date"') === false,
    'and the one-click form sends no date inputs, so the inherited dates DO apply there');

// ============================================================================
//  4 — A WRONG NUMBER CAN BE PUT RIGHT, AND EVERYTHING MOVES WITH IT
//
//  This was refused outright before, on the grounds that the number is what
//  everything is filed under. That is true, and it is the reason it must work:
//  a wrong number left wrong is carried by every invoice for the life of the
//  contract. The test that matters is that NOTHING is left behind.
// ============================================================================
t_section('Correcting the number');

$typo = 'ZZC/C/25-26/00142';      // what was typed
$right = 'ZZC/C/25-26/00124';     // what the client's PO actually says
$cTypo = $contract($acme, $typo, 'OPEN', '2026-01-01', '2026-12-31', $off);

// Put one of everything under the wrong number.
$pdo->prepare("INSERT INTO calls (call_code, client_id, contract_number, status, created_at) VALUES (?,?,?,'OPEN',?)")
    ->execute(['ZZC-CALL-1', $acme, $typo, date('c')]);
$callTypo = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO jobs (job_code, call_id, contract_number, created_at) VALUES (?,?,?,?)")
    ->execute(['ZZC-JOB-1', $callTypo, $typo, date('c')]);
$pdo->prepare("UPDATE quotations SET contract_number=?, contract_id=? WHERE id=?")->execute([$typo, $cTypo, $qAcme]);
if (t_table_exists('engagements')) {
    $pdo->prepare("INSERT INTO engagements (engagement_key, partner_id, title, created_at) VALUES (?,?,?,?)")
        ->execute([$typo, $acme, 'ZZC engagement', date('c')]);
}

$impact = contract_renumber_impact(ops_one("SELECT * FROM partner_contracts WHERE id=?", [$cTypo]));
t_ok(array_sum($impact) >= 3, 'the impact is counted BEFORE the button exists — ' . array_sum($impact) . ' record(s) would move');

// Refusals first. A correction must never become a silent merge.
$r = contract_renumber($cTypo, 'ZZC/C/25-26/00001');
t_ok(!empty($r['err']), 'correcting to a number this client already uses is refused');
t_ok(strpos((string) $r['err'], 'merge') !== false, 'and the refusal says why: that would be a merge, not a correction');
$r = contract_renumber($cTypo, $typo);
t_ok(!empty($r['err']), 'correcting a number to itself is refused rather than logged as a change');
$r = contract_renumber($cTypo, '');
t_ok(!empty($r['err']), 'a blank number is refused');
$r = contract_renumber($cTypo, str_repeat('Y', CONTRACT_NO_MAX + 1));
t_ok(!empty($r['err']), 'an over-long number is refused here too, not only at registration');

$rivalNo = 'ZZC/C/25-26/09999';
$contract($rival, $rivalNo, 'OPEN', '', '', $off);
$r = contract_renumber($cTypo, $rivalNo);
t_ok(!empty($r['err']), 'and a number another client already holds is refused');

// Nothing above should have changed anything.
t_eq((string) ops_val("SELECT contract_number FROM partner_contracts WHERE id=?", [$cTypo]), $typo,
    'after four refusals the contract still carries its original number — a refusal changes nothing');

// Now the real correction.
$r = contract_renumber($cTypo, $right, 'the PO says 0124');
t_ok(empty($r['err']), 'the correction is applied' . (!empty($r['err']) ? ' — ' . $r['err'] : ''));
t_eq((string) ops_val("SELECT contract_number FROM partner_contracts WHERE id=?", [$cTypo]), $right, 'the contract carries the right number');
t_eq((string) ops_val("SELECT contract_number FROM calls WHERE id=?", [$callTypo]), $right, 'the work order moved with it');
t_eq((int) ops_val("SELECT COUNT(*) FROM jobs WHERE contract_number=?", [$right]), 1, 'the job moved with it');
t_eq((string) ops_val("SELECT contract_number FROM quotations WHERE id=?", [$qAcme]), $right, 'the quotation moved with it');
if (t_table_exists('engagements')) {
    t_eq((int) ops_val("SELECT COUNT(*) FROM engagements WHERE engagement_key=?", [$right]), 1,
        'and the engagement key moved — miss this one and the nightly reconciliation reports every job broken');
}
// The point of the whole exercise: nothing at all is left under the old number.
$left = 0;
foreach (contract_no_tables() as $table => $col) {
    if (!t_table_exists($table)) continue;
    $left += (int) ops_val("SELECT COUNT(*) FROM $table WHERE $col=?", [$typo]);
}
t_eq($left, 0, 'NOTHING remains under the old number anywhere — no orphans');

// An engagement already standing on the target key would make the move fail
// half-way through, so it is caught before anything is written.
if (t_table_exists('engagements')) {
    $blocked = 'ZZC/C/25-26/07777';
    $pdo->prepare("INSERT INTO engagements (engagement_key, partner_id, title, created_at) VALUES (?,?,?,?)")
        ->execute([$blocked, $acme, 'ZZC squatter', date('c')]);
    $r = contract_renumber($cTypo, $blocked);
    t_ok(!empty($r['err']), 'a number whose engagement record already exists is refused up front');
    // "Up front" is the point. Without the check the database's unique index
    // would stop it anyway — but half-way through, and the person would be shown
    // a raw SQL error instead of being told what to do about it. A refusal that
    // reads like a crash is a refusal nobody can act on.
    t_ok(stripos((string) $r['err'], 'SQLSTATE') === false && stripos((string) $r['err'], 'Integrity constraint') === false,
        'and refused in English, not as a database error leaking onto the screen');
    t_ok(strpos((string) $r['err'], $blocked) !== false, 'naming the number that is in the way');
    t_eq((string) ops_val("SELECT contract_number FROM partner_contracts WHERE id=?", [$cTypo]), $right,
        'and nothing was half-written before the refusal');
}

// ============================================================================
//  5 — THE WORK ORDER ALREADY KNOWS WHAT THE ORDER SAYS
// ============================================================================
t_section('The work order inherits the commercials');

$cWork = $contract($acme, 'ZZC/C/25-26/00500', 'OPEN', '2026-01-01', '2026-12-31', $off);
$poWork = $po($acme, 'ZZC-PO-500', 300000, '2026-01-01', '2026-12-31', $cWork);
$lineWork = $poLine($poWork, 'ZZC third-party inspection', 20, 4500, 'MANDAY');

$cp = call_commercial_prefill($cWork);
t_eq((int) $cp['po_id'], $poWork, 'one order on the contract → it is the order');
t_eq((int) $cp['po_line_item_id'], $lineWork, 'one line with balance left → it is the line');
t_eq((float) $cp['billable_rate'], 4500.0, 'and the rate is the rate the client agreed, not one somebody remembers');
t_eq($cp['billable_basis'], 'MANDAY', 'with the unit carried across as-is');

// Two open lines: the balances are the whole point of tracking them, so the
// coordinator picks. Filling one in would quietly draw down the wrong one.
$poLine($poWork, 'ZZC witness testing', 10, 6000, 'VISIT');
$cp = call_commercial_prefill($cWork);
t_eq((int) $cp['po_id'], $poWork, 'the order is still unambiguous');
t_eq((int) $cp['po_line_item_id'], 0, 'but with two open lines neither is assumed');
t_eq($cp['billable_rate'], null, 'and no rate is invented from a line nobody chose');

// A fully consumed line is not a candidate at all.
$cSpent = $contract($acme, 'ZZC/C/25-26/00600', 'OPEN', '', '', $off);
$poSpent = $po($acme, 'ZZC-PO-600', 1000, '', '', $cSpent);
$poLine($poSpent, 'ZZC spent line', 5, 1000, 'MANDAY', 5);
$lineLeft = $poLine($poSpent, 'ZZC line with balance', 5, 2000, 'MANDAY', 1);
$cp = call_commercial_prefill($cSpent);
t_eq((int) $cp['po_line_item_id'], $lineLeft, 'a fully consumed line is not offered — only the one with balance left');
t_eq((float) $cp['billable_rate'], 2000.0, 'and the rate comes from that line');

// Two orders on one contract and nothing is assumed.
$po($acme, 'ZZC-PO-501', 1, '', '', $cWork);
$cp = call_commercial_prefill($cWork);
t_eq((int) $cp['po_id'], 0, 'two orders on the contract → none is assumed');

// ============================================================================
//  6 — AND THE OTHER DIRECTION
// ============================================================================
t_section('Linking a work order back to its contract and order');

$cLink = $contract($acme, 'ZZC/C/25-26/00700', 'OPEN', '2026-01-01', '2026-12-31', $off);
$poLink = $po($acme, 'ZZC-PO-700', 50000, '', '');       // deliberately NOT on the contract
$cPending = $contract($acme, 'ZZC/C/25-26/00701', 'PENDING', '', '', $off);
$cRival = $contract($rival, 'ZZC/C/25-26/00702', 'OPEN', '', '', $off);

$pdo->prepare("INSERT INTO calls (call_code, client_id, contract_number, status, created_at) VALUES (?,?,'','OPEN',?)")
    ->execute(['ZZC-CALL-LOOSE', $acme, date('c')]);
$loose = (int) $pdo->lastInsertId();
$looseRow = ops_one("SELECT * FROM calls WHERE id=?", [$loose]);

$cands = call_link_candidates($looseRow);
t_ok(!empty($cands['need_contract']), 'a work order with no contract number is recognised as needing one');
t_ok(!empty($cands['need_po']), 'and as needing an order');
// A contract that is OPEN but has run past its end date cannot take new work —
// the scheduling gate would refuse it — so offering it here would be offering a
// dead end. Same for one whose agreed quantity is fully used up.
$cExpired = $contract($acme, 'ZZC/C/25-26/00703', 'OPEN', '2020-01-01', '2020-12-31', $off);
$cExhausted = $contract($acme, 'ZZC/C/25-26/00704', 'OPEN', '', '', $off);
$pdo->prepare("UPDATE partner_contracts SET qty_total=0 WHERE id=?")->execute([$cExhausted]);

$cands = call_link_candidates($looseRow);
$ids = array_map(fn($r) => (int) $r['id'], $cands['contracts']);
t_ok(in_array($cLink, $ids, true), 'the client\'s open contract is offered');
t_ok(!in_array($cPending, $ids, true), 'a contract still awaiting its two signatures is NOT offered — it is not open yet');
t_ok(!in_array($cRival, $ids, true), 'and another client\'s contract is never offered');
t_ok(!in_array($cExpired, $ids, true),
    'a contract past its end date is NOT offered — the scheduling gate would refuse it, so offering it is a dead end');
t_ok(!in_array($cExhausted, $ids, true), 'nor is one whose agreed quantity is used up');

// The guards hold on the write too, not only in the list. A list is a
// convenience; the write is the control.
//
// This block runs as a REAL COORDINATOR, not as the master. The not-open guard
// deliberately lets a Master Admin through (a one-person branch has nobody else
// to ask), exactly as the raise-a-work-order path does — so asserting it as a
// master would assert nothing at all. That mistake has been made in this suite
// before; the fixture below is the fix for it.
$zzUid = null;
$asCoordinator = function () use (&$zzUid, $off) {
    if ($zzUid === null) {
        db()->prepare("INSERT INTO users (username,password_hash,first_name,last_name,role,is_superuser,is_active,home_office_id,scope_offices)
                       VALUES ('zzc_coord',?,'ZZC','Coordinator','COORDINATOR',0,1,?,?)")
            ->execute([password_hash('x' . bin2hex(random_bytes(6)), PASSWORD_BCRYPT), $off, (string) $off]);
        $zzUid = (int) db()->lastInsertId();
    }
    $_SESSION['uid'] = $zzUid;
    if (function_exists('current_user')) current_user(true);
    if (function_exists('ua')) ua(true);
};

$asCoordinator();
t_ok(!is_master(), 'ARMING · these two refusals are being tested as a real coordinator, NOT as the master');
$r = call_link_commercial($loose, $cRival, 0);
t_ok(!empty($r['err']), 'linking to another client\'s contract is refused at the write');
$r = call_link_commercial($loose, $cPending, 0);
t_ok(!empty($r['err']), 'and linking to a contract that is not open is refused at the write');
t_eq((string) ops_val("SELECT contract_number FROM calls WHERE id=?", [$loose]), '',
    'after both refusals the work order is still unlinked');
t_as_admin();

$r = call_link_commercial($loose, $cLink, $poLink);
t_ok(empty($r['err']), 'the real link is applied' . (!empty($r['err']) ? ' — ' . $r['err'] : ''));
t_eq((string) ops_val("SELECT contract_number FROM calls WHERE id=?", [$loose]), 'ZZC/C/25-26/00700', 'the contract number is on the work order');
t_eq((int) ops_val("SELECT po_id FROM calls WHERE id=?", [$loose]), $poLink, 'and so is the order');
t_eq((int) ops_val("SELECT contract_id FROM partner_purchase_orders WHERE id=?", [$poLink]), $cLink,
    'and the order is joined to the contract from the other end, so the NEXT work order fills itself in');

$r = call_link_commercial($loose, 0, 0);
t_ok(!empty($r['err']), 'linking to nothing is refused rather than treated as a save');

// Once an invoice has gone out, the number is ON that invoice. Re-linking the work
// order now would leave the two disagreeing — which is the exact class of mistake
// this whole change exists to prevent. The remedy for a wrongly numbered invoice is
// a credit note, not a dropdown.
$pdo->prepare("INSERT INTO calls (call_code, client_id, contract_number, status, created_at) VALUES (?,?,'','OPEN',?)")
    ->execute(['ZZC-CALL-BILLED', $acme, date('c')]);
$billedCall = (int) $pdo->lastInsertId();
$billedRow = ops_one("SELECT * FROM calls WHERE id=?", [$billedCall]);
t_ok(!empty(call_link_candidates($billedRow)['need_contract']),
    'ARMING · before it is invoiced, the link IS offered — otherwise the next assertion proves nothing');
$pdo->prepare("INSERT INTO jobs (job_code, call_id, contract_number, invoice_raised, invoice_amount, created_at) VALUES (?,?,'',1,5000,?)")
    ->execute(['ZZC-JOB-BILLED', $billedCall, date('c')]);
$billedCands = call_link_candidates(ops_one("SELECT * FROM calls WHERE id=?", [$billedCall]));
t_ok(empty($billedCands['need_contract']) && empty($billedCands['need_po']),
    'once invoiced, the link is no longer offered on the screen');
$asCoordinator();
$r = call_link_commercial($billedCall, $cLink, 0);
t_ok(!empty($r['err']), 'and it is refused at the write too, not only hidden from the screen');
t_ok(strpos((string) $r['err'], 'credit note') !== false, 'with the real remedy named: a credit note, not a re-link');
t_eq((string) ops_val("SELECT contract_number FROM calls WHERE id=?", [$billedCall]), '', 'and nothing was changed');
t_as_admin();

// ============================================================================
//  7 — THE DOORS EXIST, AND THE SIGNATURES ARE UNTOUCHED
// ============================================================================
t_section('The screens, and what was deliberately NOT changed');

$ops = (string) @file_get_contents(dirname(__DIR__) . '/lib/ops.php');
t_ok(strpos($ops, "'contract-renumber'") !== false, 'the correction has a route');
t_ok(strpos($ops, "'contract-no-check'") !== false, 'the live duplicate check has a route');
t_ok(strpos($ops, "'call-link-commercial'") !== false, 'the reverse link has a route');
t_ok(strpos($ops, 'call_commercial_prefill') !== false, 'and the work-order form reads the order forward');

$cd = (string) @file_get_contents(dirname(__DIR__) . '/views/ops/contract_detail.php');
t_ok(strpos($cd, '/contract-renumber') !== false, 'the contract screen offers the correction');
t_ok(strpos($cd, '/contract-delete') !== false, 'and still offers the delete, which the owner asked for beside it');
t_ok(strpos($cd, 'renumberImpact') !== false, 'and says what the correction would move before it is pressed');
// Both confirm dialogs put the number into the JS string through json_encode. A
// client-typed number with an apostrophe would otherwise close the string, the
// confirm would never fire, and the form would submit unconfirmed — on a DELETE.
t_eq(substr_count($cd, 'onsubmit="return confirm(<?= e(json_encode('), 2,
    'and neither confirm dialog can be broken by an apostrophe in the number');
t_ok(strpos($cd, "confirm('Delete contract <?=") === false,
    'the old interpolated-into-JS form of the delete confirm is gone');

$qd = (string) @file_get_contents(dirname(__DIR__) . '/views/ops/crm/quote_detail.php');
t_ok(strpos($qd, 'Register the contract &amp; request opening') !== false, 'the quotation screen has the one-click register');
t_ok(strpos($qd, 'auto_contract" value="1"') !== false, 'which generates the number rather than asking for it');
t_ok(strpos($qd, '/contract-no-check') !== false, 'and warns about a duplicate while it is being typed');
// The live warning names the party that already holds the number, and that name
// comes from the client master. It reaches innerHTML, so it must be escaped on the
// way in: a company called <img src=x onerror=...> must read as a company name.
t_ok(strpos($qd, 'function esc(s)') !== false && strpos($qd, "'&lt;'") !== false,
    'and the warning text is escaped before it reaches the page, not injected raw');
t_ok(strpos($qd, "box.innerHTML=parts.map(function(p){return '\\u26a0 '+esc(p);})") !== false,
    'every part of it, including the party name');
t_ok(strpos($qd, 'already has') !== false, 'the client\'s existing contracts are shown before a new one is created');
// Zero-Training UI gate item 7 — a filled-in figure that does not say where it came
// from is a figure nobody checks and everybody re-enters.
t_ok(strpos($qd, 'contract_prefill_note') !== false, 'and every inherited figure says which document it came from');
t_ok(strpos($qd, "from ' . e(\$pf['from']['value'])") !== false || strpos($qd, "\$pf['from']['value']") !== false,
    'including the value, named against its source on the row itself');

$cf = (string) @file_get_contents(dirname(__DIR__) . '/views/ops/call_form.php');
t_ok(strpos($cf, '_prefill_note') !== false, 'the work-order form says where its order and rate were read from');
$cdt = (string) @file_get_contents(dirname(__DIR__) . '/views/ops/call_detail.php');
t_ok(strpos($cdt, '/call-link-commercial') !== false, 'and the work-order screen offers the reverse link');

// The control the owner chose to KEEP. One click is about the data, never the
// approval — if this ever changes it is a change to the permission matrix and
// has to be asked for.
$ct = (string) @file_get_contents(dirname(__DIR__) . '/lib/contracts.php');
t_ok(strpos($ct, 'function can_endorse_contract_open') !== false, 'the manager endorsement still exists');
t_ok(strpos($ct, 'function can_approve_contract_open') !== false, 'the branch-manager approval still exists');
$crm = (string) @file_get_contents(dirname(__DIR__) . '/lib/crm.php');
t_ok(strpos($crm, "'PENDING'") !== false, 'and a newly registered contract is still held PENDING, not opened by the click');

// ---- clean up ---------------------------------------------------------------
t_nothrow('fixtures removed', function () use ($pdo) {
    $pdo->exec("DELETE FROM po_line_items WHERE purchase_order_id IN (SELECT id FROM partner_purchase_orders WHERE po_number LIKE 'ZZC-PO-%')");
    $pdo->exec("DELETE FROM partner_purchase_orders WHERE po_number LIKE 'ZZC-PO-%'");
    $pdo->exec("DELETE FROM jobs WHERE job_code LIKE 'ZZC-JOB-%'");
    $pdo->exec("DELETE FROM calls WHERE call_code LIKE 'ZZC-CALL-%'");
    $pdo->exec("DELETE FROM quotations WHERE quote_no LIKE 'ZZC-Q-%'");
    $pdo->exec("DELETE FROM partner_contracts WHERE contract_number LIKE 'ZZC/C/%'");
    if (t_table_exists('engagements')) $pdo->exec("DELETE FROM engagements WHERE engagement_key LIKE 'ZZC/C/%'");
    $pdo->exec("DELETE FROM business_partners WHERE code LIKE 'ZC%' AND legal_name LIKE 'ZZC %'");
    $pdo->exec("DELETE FROM offices WHERE name LIKE 'ZZC %'");
    $pdo->exec("DELETE FROM users WHERE username='zzc_coord'");
    // The audit rows this file created, so the next test's counts are its own.
    if (t_table_exists('activity_log')) $pdo->exec("DELETE FROM activity_log WHERE note LIKE '%ZZC/C/%' OR note LIKE '%ZZC-CALL-%'");
    t_eq((int) ops_val("SELECT COUNT(*) FROM partner_contracts WHERE contract_number LIKE 'ZZC/C/%'"), 0, 'no test contracts remain');
    t_as_nobody();
    t_ok(true, 'session restored for the rest of the suite');
});
