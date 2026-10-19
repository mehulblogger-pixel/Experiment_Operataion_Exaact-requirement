<?php
// ============================================================================
//  The external portals: a message must REACH the person, and every field must
//  carry a name a screen reader can read.
//
//  Two defects found by audit and frozen here:
//
//  1. lib/connect_pro.php wrote $_SESSION['pro_flash'] in TEN places -- every
//     one of them the outcome of a payment, a subscription, a credit pack or a
//     rating report -- and NO view ever read it. A professional whose payment
//     failed was told nothing at all, and the text accumulated in their
//     session. The client portal rendered its channel but hardcoded the SUCCESS
//     style, so a refusal arrived looking like a confirmation.
//
//  2. 113 label/control pairs across the three portals were written as
//     <label>Name</label><input name="name"> -- adjacent, never associated. No
//     for=, no wrapping, so the field is unlabelled to a screen reader and
//     tapping the label does not focus it.
//
//  (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('external portals — messages reach the person');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    // ---- 1. every portal's flash channel is RENDERED somewhere ------------
    $channels = [
        ['pro_flash',    'lib/connect_pro.php', 'views/pro/top.php'],
        ['portal_flash', 'lib/portal.php',      'views/portal/top.php'],
        ['vendor_flash', 'lib/cvp.php',         'views/vendor/top.php'],
    ];
    foreach ($channels as [$key, $writer, $renderer]) {
        $w = file_get_contents(__DIR__ . '/../' . $writer);
        $r = file_get_contents(__DIR__ . '/../' . $renderer);
        $writes = preg_match_all("/\\\$_SESSION\['$key'\]\s*=/", $w)
                + substr_count($w, str_replace('_flash', '_flash_set', ($key === 'pro_flash' ? 'connect_pro_flash' : ($key === 'portal_flash' ? 'portal_flash' : 'vendor_flash'))) . '(');
        t_ok($writes > 0, "$key is written by $writer");
        t_ok(strpos($r, $key) !== false,
             "$key is RENDERED by $renderer — a message written and never read tells nobody anything");
    }

    // ---- 2. the renderers carry a severity, not a hardcoded success ------
    foreach (['views/portal/top.php', 'views/vendor/top.php', 'views/pro/top.php'] as $v) {
        $t = file_get_contents(__DIR__ . '/../' . $v);
        t_eq(strpos($t, 'class="pmsg ok"'), false,
             basename($v) . ' does not hardcode the success style for the flash');
        t_eq(strpos($t, 'class="msg ok"'), false,
             basename($v) . ' does not hardcode the success style (pro markup)');
        t_ok(strpos($t, "=== 'err'") !== false,
             basename($v) . ' chooses the style from the message\'s own tag');
        t_ok(strpos($t, 'role="alert"') !== false,
             basename($v) . ' marks the flash role=alert, so it is announced and not merely seen');
    }

    // ---- 3. the two tag-carrying helpers behave, both shapes -------------
    portal_flash_set('a refusal', 'err');
    t_eq(portal_flash_take(), ['a refusal', 'err'], 'portal: a tagged refusal survives the round trip');
    portal_flash_set('a confirmation');
    t_eq(portal_flash_take(), ['a confirmation', 'ok'], 'portal: the default tag is a confirmation');
    $_SESSION['portal_flash'] = 'a legacy bare string';
    t_eq(portal_flash_take(), ['a legacy bare string', 'ok'],
         'portal: a bare string still works and reads as a confirmation — no call site had to change');
    t_eq($_SESSION['portal_flash'] ?? null, null, 'portal: taking it clears it (it does not accumulate)');

    connect_pro_flash_set('That payment is no longer available.', 'err');
    t_eq(connect_pro_flash_take(), ['That payment is no longer available.', 'err'],
         'pro: a failed payment keeps its failure tag');
    $_SESSION['pro_flash'] = 'legacy';
    t_eq(connect_pro_flash_take(), ['legacy', 'ok'], 'pro: a bare string still works');
    t_eq($_SESSION['pro_flash'] ?? null, null, 'pro: taking it clears it');

    // ---- 4. the money messages are tagged as failures --------------------
    $pro = file_get_contents(__DIR__ . '/../lib/connect_pro.php');
    t_ok(strpos($pro, "connect_pro_flash_set('That payment is no longer available.', 'err')") !== false,
         "pro: 'That payment is no longer available.' is tagged as a failure");
    t_ok(strpos($pro, "connect_pro_flash_set(\$start['msg'], 'err')") !== false,
         'pro: the payment-start error branch is tagged as a failure');
    // Every write to the key must live INSIDE the helper -- not "no writes at
    // all", which is what an earlier version of this assertion demanded and
    // which the helper itself necessarily fails.
    $helper = '';
    if (preg_match('/function connect_pro_flash_set.*?\n\}/s', $pro, $hm)) $helper = $hm[0];
    t_ok($helper !== '', 'pro: connect_pro_flash_set() exists');
    $allWrites    = preg_match_all("/\\\$_SESSION\['pro_flash'\]\s*=/", $pro);
    $helperWrites = preg_match_all("/\\\$_SESSION\['pro_flash'\]\s*=/", $helper);
    t_eq($allWrites - $helperWrites, 0,
         'pro: no route writes the session key directly — all ' . $allWrites
         . ' write(s) are inside the helper, so none can skip the tag');

    $por = file_get_contents(__DIR__ . '/../lib/portal.php');
    t_ok(strpos($por, "portal_flash_set('That deployment is not yours.', 'err')") !== false,
         "portal: 'That deployment is not yours.' is tagged as a refusal");
    t_ok(strpos($por, "'Ask your main contact at ' . portal_client_name() . ' to have it added.', 'err'") !== false,
         'portal: the permission refusal from portal_need() is tagged as a refusal, not a success');

    // ---- 5. every label is ASSOCIATED with its control -------------------
    t_section('external portals — every field carries a name');
    $inner = '(?:(?!</?label\b|<input\b|<select\b|<textarea\b).)*';
    $pat = '/<label(?![^>]*\bfor=)[^>]*>(' . $inner . ')<\/label>\s*<(input|select|textarea)([^>]*)>/s';
    $orphans = [];
    foreach (array_merge(glob(__DIR__ . '/../views/portal/*.php'),
                         glob(__DIR__ . '/../views/vendor/*.php'),
                         glob(__DIR__ . '/../views/pro/*.php')) as $v) {
        $t = file_get_contents($v);
        if (!preg_match_all($pat, $t, $m, PREG_SET_ORDER)) continue;
        foreach ($m as $hit) {
            // A control whose name is built in PHP cannot take a derived id;
            // those carry an explicit for=/aria-label instead and are listed
            // here only if they have neither.
            if (!preg_match('/\baria-label=/', $hit[3]))
                $orphans[] = basename($v) . ': ' . trim(preg_replace('/\s+/', ' ', strip_tags($hit[1])));
        }
    }
    t_eq(count($orphans), 0,
         'no <label> sits next to a control without being associated to it'
         . ($orphans ? ' — ' . count($orphans) . ': ' . implode(' | ', array_slice($orphans, 0, 4)) : ''));

    // A wrapping label needs no for= and must NOT be given one: an early version
    // of the fix matched across one and pointed a label reading "Rating" at the
    // name field, which is worse than no label at all.
    $roster = file_get_contents(__DIR__ . '/../views/portal/roster.php');
    t_ok(strpos($roster, '<label>Rating') !== false,
         'the wrapping label in roster.php is left alone — its control is already inside it');
    t_ok(strpos($roster, '<label for="f-manual_name"') !== false
         && strpos($roster, 'id="f-manual_name" class="form-control" name="manual_name"') !== false,
         'and f-manual_name points at the name field, not at the rating');
    // ---- 6. the words a tenant chooses are not hardcoded ------------------
    // lib/terms.php lets a tenant pick its own vocabulary, and the presets
    // genuinely differ: report is "Inspection Report" under 'inspection' but
    // plain "Report" under 'manufacturing' and 'trading', where job becomes
    // "Operation" and "Consignment". A hardcoded noun on a CUSTOMER-FACING
    // screen therefore shows a non-inspection tenant's own client the wrong
    // word -- exactly what this file exists to prevent.
    t_section('external portals — the tenant chooses the nouns');

    t_eq(term_apply_pack('trading'), true, 'the trading preset applies');
    t_eq(T('report'), 'Report', "under 'trading' a report is just a Report");
    t_eq(TP('job'), 'Consignments', "...and a job is a Consignment");
    t_eq(term_apply_pack('inspection'), true, 'the inspection preset applies');
    t_eq(T('report'), 'Inspection Report', "under 'inspection' it is an Inspection Report");
    t_eq(TP('job'), 'Deputations', '...and a job is a Deputation');

    // The customer-facing screens that used to hardcode those two nouns.
    foreach ([
        'views/portal/voucher.php'      => ['report'],
        'views/pro/voucher.php'         => ['report'],
        'views/portal/call.php'         => ['job'],
        'views/portal/complaint_new.php'=> ['job'],
    ] as $v => $keys) {
        $t = file_get_contents(__DIR__ . '/../' . $v);
        // strip php blocks so a code COMMENT mentioning the word is not a hit
        $prose = preg_replace('/<\?php.*?\?>/s', ' ', $t);
        $prose = preg_replace('/<\?=.*?\?>/s', ' ', $prose);
        if (in_array('report', $keys, true))
            t_eq(preg_match('/\binspection reports?\b/i', $prose), 0,
                 basename($v) . ' does not hardcode "inspection report" in prose');
        if (in_array('job', $keys, true))
            t_eq(preg_match('/\bvisits?\b/i', $prose), 0,
                 basename($v) . ' does not hardcode "visit" where the tenant picks the word');
        $used = false;
        foreach ($keys as $k) if (strpos($t, "('" . $k . "')") !== false) $used = true;
        t_ok($used, basename($v) . ' uses the term helper for ' . implode('/', $keys));
    }

    // The TRADE names in the marketplace are NOT this term and must be left
    // alone: "inspector, welder, NDT technician" are occupations a client is
    // hiring, not the tenant's word for its own people. Replacing them would
    // have been wrong, so this asserts they survived.
    $hire = file_get_contents(__DIR__ . '/../views/portal/hire.php');
    t_ok(strpos($hire, 'inspector, welder, NDT technician, site engineer') !== false,
         'the marketplace trade names are left as prose — they are occupations, not the term');

    term_apply_pack('general');
} finally {
    unset($_SESSION['portal_flash'], $_SESSION['pro_flash'], $_SESSION['vendor_flash']);
    if ($own && db()->inTransaction()) db()->rollBack();
}
