<?php
// ============================================================================
//  The professional portal had NO cross-site-request-forgery protection.
//
//  All three external portals are dispatched BEFORE require_login(), so nothing
//  downstream applies the application's CSRF gate. portal.php and cvp.php each
//  carry their own -- they stamp a token into every POST form and refuse a POST
//  without it. connect_pro.php carried NEITHER.
//
//  Proven by execution before the fix (evidence/pro-csrf-probe.sh): a POST with
//  no token and a Referer from another site rewrote a professional's record.
//  Every POST route there was forgeable -- rates and contact details, document
//  deletion, withdrawing applications, revealing contact details to a client,
//  and confirming a payment was received, which is the handshake that releases
//  the deliverable and settles the engagement.
//
//  (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('professional portal — CSRF protection');

// ---- 1. all three portals have a gate, and it is wired into the router ----
foreach ([
    ['lib/portal.php',      'portal_csrf_or_die',      'portal_route'],
    ['lib/cvp.php',         'cvp_vendor_csrf_or_die',  'cvp_vendor_route'],
    ['lib/connect_pro.php', 'connect_pro_csrf_or_die', 'connect_pro_route'],
] as [$file, $gate, $router]) {
    $src = file_get_contents(__DIR__ . '/../' . $file);
    t_ok(strpos($src, 'function ' . $gate) !== false,
         basename($file) . " defines $gate()");
    // the gate must be CALLED inside the router, not merely defined
    if (preg_match('/function ' . preg_quote($router, '/') . '\s*\([^)]*\)\s*\{(.{0,900})/s', $src, $m))
        t_ok(strpos($m[1], $gate . '(') !== false,
             "...and $router() calls it before dispatching anything");
    else
        t_ok(false, "could not locate $router() to check the gate is called");
}

// ---- 2. all three view helpers stamp a token into every POST form --------
foreach ([
    ['lib/portal.php',      'portal_view'],
    ['lib/cvp.php',         'cvp_vendor_view'],
    ['lib/connect_pro.php', 'connect_pro_view'],
] as [$file, $view]) {
    $src = file_get_contents(__DIR__ . '/../' . $file);
    if (preg_match('/function ' . preg_quote($view, '/') . '\s*\([^)]*\)\s*\{(.*?)\n\}/s', $src, $m)) {
        t_ok(strpos($m[1], 'csrf_stamp_forms') !== false,
             "$view() stamps a CSRF token into every POST form it renders");
        t_ok(strpos($m[1], 'ob_start') !== false,
             "...which needs the output buffered, so $view() buffers it");
    } else {
        t_ok(false, "could not locate $view()");
    }
}

// ---- 3. the gate's own logic ---------------------------------------------
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $_SESSION['csrf'] = 'a-known-token';
    t_eq(csrf_ok('a-known-token'), true,  'the right token is accepted');
    t_eq(csrf_ok('wrong'), false,         'a wrong token is refused');
    t_eq(csrf_ok(''), false,              'an absent token is refused');
    $_SESSION['csrf'] = '';
    t_eq(csrf_ok(''), false,              'and an empty session secret never matches an empty token');

    // The gate must be POST-only: stamping is for forms, and a GET that is
    // refused would break every link in the portal.
    $src = file_get_contents(__DIR__ . '/../lib/connect_pro.php');
    if (preg_match('/function connect_pro_csrf_or_die\s*\([^)]*\)\s*\{(.*?)\n\}/s', $src, $m)) {
        t_ok(strpos($m[1], "!== 'POST'") !== false,
             'the professional gate returns early for anything that is not a POST');
        t_ok(strpos($m[1], '400') !== false, '...and answers 400 on a refusal');
        t_ok(strpos($m[1], 'nothing was changed') !== false,
             '...and tells the person plainly that nothing was changed');
    }

    // The gate renders a view, so that view has to exist.
    t_ok(is_file(__DIR__ . '/../views/pro/message.php'),
         'views/pro/message.php exists for the gate to render');

    // The four /pro lookup endpoints the screens fetch are GET, so a POST-only
    // gate cannot break them. If one is ever changed to POST it must carry a
    // token, and this assertion is the reminder.
    foreach (['pro/tax-roots', 'pro/tax-children', 'pro/tax-suggest', 'pro/tax-resolve'] as $r) {
        if (preg_match("/case '" . preg_quote($r, '/') . "':(.{0,400})/s", $src, $m))
            t_ok(strpos($m[1], '$_POST') === false,
                 "/$r reads only \$_GET, so the POST-only gate does not touch it");
    }

    // ---- 4. the marketplace posting form answers an empty title ----------
    t_section('client portal — an empty requirement title is answered, not ignored');
    $por = file_get_contents(__DIR__ . '/../lib/portal.php');
    t_ok(strpos($por, 'Give the requirement a one-line title') !== false,
         'an empty title on /portal/hire gets a plain-words refusal');
    t_ok(strpos($por, "Nothing was posted.', 'err')") !== false,
         '...tagged as a refusal, and saying plainly that nothing was posted');
    // Its siblings already answered; this one had fallen through every branch
    // and re-rendered in silence, so the client believed it had worked. Each
    // message lives in the module that owns the record, NOT in portal.php --
    // an earlier version of this test looked only in portal.php and reported
    // two of them missing when the live probe had just seen them on screen.
    $where = [
        ['/portal/request',       'lib/portal.php',              'Tell us what you need and where'],
        ['/portal/complaint-new', 'lib/reportreview.php',        'Please give it a one-line subject'],
        ['/portal/roster',        'lib/connect_client_bench.php','Add a professional from the pool'],
        ['/portal/bench',         'lib/connect_bench.php',       'A name is required'],
    ];
    foreach ($where as [$route, $file, $phrase]) {
        $t = @file_get_contents(__DIR__ . '/../' . $file);
        t_ok($t !== false && strpos($t, $phrase) !== false,
             $route . ' still answers an empty submission, from ' . basename($file)
             . ': "' . $phrase . '..."');
    }
} finally {
    unset($_SESSION['csrf']);
    if ($own && db()->inTransaction()) db()->rollBack();
}
