<?php
// ============================================================================
//  PRESSING A BUTTON MUST NOT THROW YOU OFF THE PANEL YOU WERE ON.
//
//  Reported by the owner: "Suppose I'm working in Offer, when I click on any
//  button inside it, it takes back to Overview. It is happening for each and
//  every button irrespective of tab."
//
//  Exactly right, and it was every tabbed screen in the product, not just this
//  one. A form posts, the handler redirects to a bare path, and the tab engine —
//  finding nothing in the address — opens panel one. So a person filling in an
//  offer, scheduling an interview or saving stage notes was thrown back to the
//  top of the record every single time.
//
//  THE PART WORTH REMEMBERING: three redirects had already tried to fix this and
//  wrote "#tab=Offer". That used the GENERIC key instead of the screen's own
//  ("ct"), and the LABEL instead of the slug, so it matched nothing — while the
//  address bar showed the right thing, which is why it looked solved and was
//  not. Two half-mechanisms doing one job is what produced the bug.
// ============================================================================

t_section('Tabbed screens — a submit returns you to the panel you were on');

t_ok(function_exists('redirect_tab_fragment'), 'ARMING · the helper exists');

$withPost = function (array $post, callable $fn) {
    $was = $_POST; $_POST = $post;
    try { return $fn(); } finally { $_POST = $was; }
};

// ---------------------------------------------------------------------------
//  1 · IT CARRIES THE PANEL BACK
// ---------------------------------------------------------------------------
t_nothrow('the panel a form was submitted from becomes the fragment', function () use ($withPost) {
    t_eq($withPost(['_tab' => 'offer', '_tabkey' => 'ct'], 'redirect_tab_fragment'), '#ct=offer',
        'the screen key and the panel slug, exactly as the engine writes them');
    t_eq($withPost(['_tab' => 'interviews', '_tabkey' => 'ct'], 'redirect_tab_fragment'), '#ct=interviews',
        'another panel on the same screen');
    t_eq($withPost(['_tab' => 'money', '_tabkey' => 'quote'], 'redirect_tab_fragment'), '#quote=money',
        'and a different screen entirely — this is not candidate-specific');
});

t_nothrow('a missing key falls back to the generic one the engine also accepts', function () use ($withPost) {
    t_eq($withPost(['_tab' => 'offer'], 'redirect_tab_fragment'), '#tab=offer', 'no key given');
    t_eq($withPost(['_tab' => 'offer', '_tabkey' => ''], 'redirect_tab_fragment'), '#tab=offer', 'empty key');
});

t_nothrow('no panel means no fragment — nothing is invented', function () use ($withPost) {
    foreach ([[], ['_tab' => ''], ['_tab' => '   '], ['_tabkey' => 'ct'], ['_tab' => '!!!']] as $i => $post)
        t_eq($withPost($post, 'redirect_tab_fragment'), '', 'nothing added for shape #' . ($i + 1));
});

// ---------------------------------------------------------------------------
//  2 · IT CANNOT BE USED TO FORGE A RESPONSE HEADER. The important half.
// ---------------------------------------------------------------------------
t_nothrow('a header cannot be split through the tab fields', function () use ($withPost) {
    //  This value is concatenated into a Location: header. A newline in it would
    //  let anybody who can make a person submit a form inject their own headers,
    //  which is a far worse bug than the one being fixed. Everything outside
    //  [a-z0-9-] is dropped rather than escaped, so there is nothing to get
    //  wrong later.
    $nasty = [
        "offer\r\nSet-Cookie: a=b",
        "offer\nLocation: https://evil.example",
        "offer\r\n\r\n<script>alert(1)</script>",
        "offer%0d%0aSet-Cookie:x",
        "../../etc/passwd",
        "offer\tx",
        'offer" onload="alert(1)',
        "https://evil.example/",
    ];
    foreach ($nasty as $bad) {
        $out = $withPost(['_tab' => $bad, '_tabkey' => 'ct'], 'redirect_tab_fragment');
        t_ok(strpos($out, "\r") === false && strpos($out, "\n") === false,
            'no newline survives: ' . str_replace(["\r", "\n", "\t"], ['\\r', '\\n', '\\t'], substr($bad, 0, 30)));
        t_ok(preg_match('~^(#[a-z0-9-]+=[a-z0-9-]*)?$~', $out) === 1,
            'and the result is still a plain fragment (got "' . $out . '")');
    }
    //  The KEY is sanitised too — it is the other half of the same header.
    foreach ($nasty as $bad) {
        $out = $withPost(['_tab' => 'offer', '_tabkey' => $bad], 'redirect_tab_fragment');
        t_ok(strpos($out, "\r") === false && strpos($out, "\n") === false, 'the key cannot split a header either');
    }
});

t_nothrow('an absurdly long value is truncated, not passed through', function () use ($withPost) {
    $out = $withPost(['_tab' => str_repeat('a', 5000), '_tabkey' => 'ct'], 'redirect_tab_fragment');
    t_ok(strlen($out) < 60, 'the fragment stays short (' . strlen($out) . ' chars)');
});

// ---------------------------------------------------------------------------
//  3 · IT NEVER OVERRIDES A DESTINATION THE CALLER CHOSE
// ---------------------------------------------------------------------------
t_nothrow('redirect() only ADDS a fragment, never replaces one', function () {
    $src = (string) @file_get_contents(dirname(__DIR__) . '/lib/helpers.php');
    $from = strpos($src, 'function redirect($path)');
    t_ok($from !== false, 'ARMING · redirect() was located');
    $body = substr($src, $from, 1600);   // must reach past the embed branch to the Location header
    t_ok(strpos($body, "strpos(\$path, '#') === false") !== false,
        'a path that already names a fragment is left exactly as the caller wrote it');
    t_ok(strpos($body, 'redirect_tab_fragment()') !== false, 'and otherwise the open panel is appended');
    //  It must happen BEFORE the header is sent, or it does nothing.
    $add = strpos($body, 'redirect_tab_fragment()');
    $hdr = strpos($body, 'header("Location:');
    t_ok($add !== false && $hdr !== false && $add < $hdr, 'and before the header is written');
});

// ---------------------------------------------------------------------------
//  4 · THE BROWSER HALF
// ---------------------------------------------------------------------------
t_nothrow('every form inside a tabbed screen is stamped with the open panel', function () {
    $js = (string) @file_get_contents(dirname(__DIR__) . '/assets/js/app.js');
    t_ok(strpos($js, 'function stampForms') !== false, 'the engine stamps forms');
    t_ok(strpos($js, "set('_tab',") !== false && strpos($js, "set('_tabkey',") !== false,
        'with both the panel and the screen key, which is what PHP reads back');
    //  Stamping must happen on EVERY panel change, not only at load, or the
    //  stamp goes stale the moment somebody switches tab.
    t_ok(preg_match('~stampForms\(slug\(groups\[i\]\.label\)\);~', $js) === 1,
        'and re-stamps whenever the open panel changes');
});

t_nothrow('the engine reads a fragment forgivingly, so an older link still works', function () {
    $js = (string) @file_get_contents(dirname(__DIR__) . '/assets/js/app.js');
    //  "#tab=Offer" was written by three redirects for a long time, and people
    //  have such links in their history and their bookmarks. Accepting the
    //  generic key and the label, case-insensitively, costs nothing and means a
    //  saved link opens the panel it names.
    t_ok(strpos($js, "'(?:^#|[#&])(?:' + key + '|tab)=([^&]+)'") !== false,
        'either this screen\'s key or the generic "tab" is accepted');
    t_ok(strpos($js, 'String(g.label).toLowerCase() === wanted') !== false,
        'and a panel matches by its label as well as its slug');
    t_ok(strpos($js, 'decodeURIComponent(m[1]).toLowerCase()') !== false, 'ignoring case');
});

t_nothrow('nothing hard-codes a fragment any more — one mechanism, not two', function () {
    //  The three redirects that wrote "#tab=Offer" are gone. Leaving them beside
    //  the generic mechanism would be two ways of doing one job, which is what
    //  produced this bug: one of them was wrong and nobody noticed, because the
    //  address bar looked right.
    foreach (['lib/recruit_offer.php', 'lib/recruit_iv.php'] as $rel) {
        $src = (string) @file_get_contents(dirname(__DIR__) . '/' . $rel);
        $code = preg_replace('~^\s*//.*$~m', '', $src);       // comments explain the history
        t_ok(!preg_match('~redirect\([^)]*#tab=~', $code),
            basename($rel) . ' no longer names a panel in its redirect');
    }
});
