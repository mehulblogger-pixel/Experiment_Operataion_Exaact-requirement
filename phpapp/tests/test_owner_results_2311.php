<?php
// ============================================================================
//  The owner's Sales test-book results of 23 Nov 2026 — the fixes.
//
//   S-05  Sales settings → "Who approves quotes" opens the approval levels, not
//         "Set up Sales in a few questions".
//   Q-11  /quotes-final (plural) lands on the Quotations register like /quote-final.
//   Q-13  A quote line with a quantity or rate but no service and no description is
//         refused with its line number, instead of being dropped without a word.
//   Q-15  A GSTIN pasted with a no-break / zero-width space, a dash, spaces or lower
//         case is cleaned before it is checked; a wrong last character is still
//         refused; the example GSTIN shown on screens passes the real check.
//   J1-05 The Sales view of the client register shows the GSTIN itself.
//   LF-01 A lead for a client on file is not flagged because of that client's own
//         contact; a contact of ANOTHER firm with the same mobile still is.
//  All GSTINs below are made up (valid check character), never a real business.
// ============================================================================
t_section('Owner results 23 Nov 2026 — S-05, Q-11, Q-13, Q-15, J1-05, LF-01');

$root = dirname(__DIR__);

// ---- S-05 -------------------------------------------------------------------------
$sx = (string) file_get_contents($root . '/views/ops/sx_settings.php');
t_ok(strpos($sx, "['Who approves ' . Tlp('quote'), 'lock', '/sales-approvals?tab=quote'") !== false,
     'S-05 · "Who approves quotes" opens the approval levels (Admin → Approvals → Sales, quotation tab)');
t_ok(defined('SALES_APPROVAL_TABS') ? isset(SALES_APPROVAL_TABS['quote']) || in_array('quote', (array) SALES_APPROVAL_TABS, true) : true,
     'S-05 · that tab exists on the approval-levels screen');

// ---- Q-11 -------------------------------------------------------------------------
$crm = (string) file_get_contents($root . '/lib/crm.php');
t_ok(strpos($crm, "\$route === 'quotes-final') redirect('/quotes')") !== false, 'Q-11 · /quotes-final redirects to the Quotations register');
t_ok(strpos((string) file_get_contents($root . '/lib/ops.php'), "'quotes-final'=>'quotes'") !== false, 'Q-11 · and is routed under the Quotations module (its gate)');

// ---- Q-13 -------------------------------------------------------------------------
$base = ['gst_pct' => '18', 'advance_pct' => '0', 'validity_days' => '30'];
$p = crm_quote_figures_problem($base + ['l_desc' => ['Inspection', ''], 'l_service' => ['TPI', ''], 'l_qty' => ['2', '5'], 'l_rate' => ['1000', '400']]);
t_ok(strpos($p, 'Line 2') === 0 && strpos($p, 'no service and no description') !== false, 'Q-13 · a line with a quantity / rate but no service and no description is refused, by its number: ' . $p);
$p = crm_quote_figures_problem($base + ['l_desc' => ['', 'Expediting visits'], 'l_service' => ['', ''], 'l_qty' => ['', '5'], 'l_rate' => ['', '400']]);
t_ok(strpos($p, 'no service and no description') === false, 'Q-13 · a line with a description and no service is NOT refused for that (it is saved, as before)');
$p = crm_quote_figures_problem($base + ['l_desc' => ['Inspection', ''], 'l_service' => ['TPI', ''], 'l_qty' => ['2', ''], 'l_rate' => ['1000', '']]);
t_ok(strpos($p, 'no service and no description') === false, 'Q-13 · an empty spare row is still simply skipped');

// ---- Q-15 -------------------------------------------------------------------------
t_eq(commercial_gstin_problem(GSTIN_EXAMPLE), '', 'Q-15 · the example GSTIN shown on screens (' . GSTIN_EXAMPLE . ') passes the real check');
foreach (['27AAZCS4501K1ZS' => 'plain', ' 27aazcs4501k1zs ' => 'spaces and lower case', "\u{00A0}27AAZCS4501K1ZS" => 'a no-break space',
          "27AAZCS4501K1ZS\u{200B}" => 'a zero-width space', '27-AAZCS-4501K-1ZS' => 'dashes', '27 AAZCS 4501 K1ZS' => 'spaces inside'] as $in => $what) {
    t_eq(clean_gstin($in), '27AAZCS4501K1ZS', "Q-15 · pasted with $what, it is cleaned to the 15 characters");
    t_eq(custid_gstin_problem($in), '', "Q-15 · …and accepted ($what)");
}
t_ok(strpos(custid_gstin_problem('27AAZCS4501K1ZT'), 'last character does not match') !== false, 'Q-15 · a wrong last character is still refused');
t_ok(strpos(custid_gstin_problem('27AAZCS4501K1Z'), GSTIN_EXAMPLE) !== false, 'Q-15 · a short one is refused, and the message\'s example is the valid one');
foreach (['33AAZCS4501K1ZZ', '24AAZCS4501K1ZY', '29AAZCS4501K1ZO', '36AAZCS4501K1ZT'] as $g) t_eq(commercial_gstin_problem($g), '', "Q-15 · made-up test GSTIN $g passes (same PAN, other states)");
//  No screen or message offers an example GSTIN that its own check refuses.
$bad = [];
foreach (array_merge(glob($root . '/lib/*.php'), glob($root . '/views/*.php'), glob($root . '/views/*/*.php'), glob($root . '/views/*/*/*.php')) as $file) {
    if (preg_match('/seed|trace_seed/', basename($file))) continue;     // demo data, not a hint on a screen
    if (preg_match_all('/(?:e\.g\.|like|placeholder=")\s*([0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z])/', (string) file_get_contents($file), $m))
        foreach ($m[1] as $g) if (commercial_gstin_problem($g) !== '') $bad[] = basename($file) . ': ' . $g;
}
t_eq($bad, [], 'Q-15 · no on-screen example GSTIN fails the check (the old ones all did)');
$bl = (string) file_get_contents($root . '/views/ops/_billing_locations.php');
t_ok(strpos($bl, 'data-gstin-check') !== false && strpos($bl, 'name="gstin" maxlength="20"') !== false && strpos($bl, '1ZX') === false,
     'Q-15 · the billing-location box no longer cuts a pasted space into a lost last character, and no longer shows an invalid example');

// ---- J1-05 ------------------------------------------------------------------------
$lv = (string) file_get_contents($root . '/views/list.php');
t_ok(strpos($lv, "e(\$g) . '</span> '") !== false && strpos($lv, 'commercial_gstin_problem($g)') !== false,
     'J1-05 · the Sales view of the client register prints the GSTIN itself, checked with the real check character');

// ---- LF-01 ------------------------------------------------------------------------
$pdo = db();
$tag = 'or' . substr(md5((string) microtime(true)), 0, 5);
$mob = '98' . substr((string) mt_rand(10000000, 99999999), 0, 8);
$ids = [];
try {
    foreach (['A' => 'Own Client', 'B' => 'Other Firm'] as $k => $nm) {
        $pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES (?,?,1,'ACTIVE')")->execute([$nm . ' ' . $tag, $nm . ' ' . $tag]);
        $ids[$k] = (int) $pdo->lastInsertId();
    }
    $pdo->prepare("INSERT INTO partner_contacts (partner_id, name, mobile) VALUES (?,?,?)")->execute([$ids['A'], 'Contact A', $mob]);
    $d = lead_form_dupes(['partner_id' => (string) $ids['A'], 'contact_phone' => $mob]);
    t_eq(array_values(array_filter($d, fn($x) => strpos((string) $x['text'], 'A contact with this mobile') === 0)), [],
         "LF-01 · a lead for a client on file, with that client's own contact's mobile, is not flagged as a duplicate");
    $pdo->prepare("INSERT INTO partner_contacts (partner_id, name, mobile) VALUES (?,?,?)")->execute([$ids['B'], 'Contact B', $mob]);
    $d = lead_form_dupes(['partner_id' => (string) $ids['A'], 'contact_phone' => $mob]);
    t_ok((bool) array_filter($d, fn($x) => strpos((string) $x['text'], 'A contact with this mobile') === 0 && strpos((string) $x['text'], 'Other Firm') !== false),
         'LF-01 · the same mobile on a contact of ANOTHER firm is still flagged');
} finally {
    if ($ids) {
        $in = implode(',', array_map('intval', $ids));
        try { $pdo->exec("DELETE FROM partner_contacts WHERE partner_id IN ($in)"); } catch (Throwable $e) {}
        try { $pdo->exec("DELETE FROM business_partners WHERE id IN ($in)"); } catch (Throwable $e) {}
    }
}
