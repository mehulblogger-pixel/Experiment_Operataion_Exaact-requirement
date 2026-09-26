<?php
// ============================================================================
//  A FORM THAT LOOKS LIKE FOUR STEPS MUST BEHAVE LIKE FOUR STEPS.
//
//  Add candidate showed four tabs — Requirement & person, Role & where,
//  Sourcing & money, Paperwork & outcome — with no Back, no Next, no step
//  count, and a live green "Add candidate" button sitting directly under the
//  first one. So people filled the first panel, pressed the obvious button, and
//  created a record having never learned the other three existed.
//
//  The owner's words: "it is directly saved without further inputs or
//  information, so this is not well user friendly."
//
//  The fix is NOT a strict wizard. A recruiter loading forty CVs must not walk
//  four panels forty times, and most of what those panels ask is not knowable
//  when a CV arrives. Only the first panel is needed to create a usable record.
//  So: show the sequence, keep Save reachable, and say plainly what saving early
//  leaves for later. Both halves matter — hiding Save would trade one usability
//  failure for another.
// ============================================================================

t_section('Add candidate — the sequence is visible, and saving early is honest');

$cfSrc = (string) @file_get_contents(dirname(__DIR__) . '/views/ops/candidate_form.php');
$jsSrc = (string) @file_get_contents(dirname(__DIR__) . '/assets/js/app.js');

t_ok(strlen($cfSrc) > 4000, 'ARMING · the candidate form was read (' . strlen($cfSrc) . ' bytes)');
t_ok(strlen($jsSrc) > 20000, 'ARMING · the shared script was read');

t_nothrow('the four panels are a stepped form, not four loose panels', function () use ($cfSrc) {
    t_ok(preg_match('~class="form-tabs"[^>]*data-tabs~', $cfSrc) === 1
      || preg_match('~data-tabs[^>]*class="form-tabs"~', $cfSrc) === 1,
        'the wrap is marked form-tabs, which is what draws Back / Next / the step count');
    preg_match_all('~data-tab="([^"]+)"~', $cfSrc, $m);
    t_ok(count($m[1]) >= 4, 'ARMING · ' . count($m[1]) . ' panels found');
});

t_nothrow('Save stays reachable from any step — bulk CV entry is not sacrificed', function () use ($cfSrc, $jsSrc) {
    t_ok(strpos($cfSrc, 'data-tabs-save="always"') !== false,
        'the form opts into save-from-any-step');
    t_ok(strpos($jsSrc, "getAttribute('data-tabs-save') === 'always'") !== false,
        'and the shared engine implements it');
    //  Without this the engine hides Save on every panel but the last.
    t_ok(strpos($jsSrc, '(last || saveAlways)') !== false,
        'the engine shows Save on the last panel OR whenever save-always is set');
});

t_nothrow('saving early says what it leaves undone', function () use ($jsSrc) {
    t_ok(strpos($jsSrc, 'fs-savehint') !== false, 'there is a hint element');
    t_ok(strpos($jsSrc, 'can be filled in later') !== false,
        'and it tells the person the rest can be completed later, rather than leaving them guessing');
    //  On the LAST panel there is nothing left, so the hint must clear itself —
    //  a stale "3 sections remaining" on the final step would be a lie.
    t_ok(strpos($jsSrc, "last ? '' :") !== false,
        'and it clears on the last panel, where nothing remains');
});

t_nothrow('the Save row is where the engine can find it', function () use ($cfSrc) {
    //  The engine folds .fs-actions into its Back / Next row. Without the class
    //  the buttons stay adrift below the panels — which is precisely the layout
    //  that caused this complaint.
    t_ok(strpos($cfSrc, 'class="fs-actions"') !== false, 'the actions row carries fs-actions');
    $wrapEnd = strrpos($cfSrc, '</section>');
    $actions = strpos($cfSrc, 'class="fs-actions"');
    t_ok($wrapEnd !== false && $actions !== false && $actions > $wrapEnd,
        'and sits after the panels, as a sibling of the tab wrap');
});

t_nothrow('nothing typed on an earlier step is lost', function () use ($jsSrc) {
    //  Panels are hidden, never detached, so every field is still in the form
    //  when Save is pressed from any step. Verified in a real browser too.
    t_ok(strpos($jsSrc, 'p.hidden = j !== i') !== false,
        'panels are hidden rather than removed, so their fields still submit');
});

// ---------------------------------------------------------------------------
//  A DEFECT FOUND WHILE VERIFYING THE ABOVE IN A REAL BROWSER.
// ---------------------------------------------------------------------------
t_section('The header logo cannot break every page');

t_nothrow('a corrupt logo setting falls back to the text logo', function () {
    //  Found by watching the network while testing the form: EVERY page was
    //  requesting a URL made of 5,000 letter A's and getting a 404, because
    //  logo_html() put whatever was stored straight into src. One bad setting,
    //  a broken image in the header of every screen, and a 404 on every load.
    t_ok(function_exists('logo_html'), 'ARMING · the helper exists');
    $was = (string) setting_get('logo_data', '');
    try {
        foreach ([str_repeat('A', 200), 'not a url', '<script>x</script>', '   '] as $junk) {
            setting_set('logo_data', $junk);
            $c = &settings_cache(); $c['logo_data'] = $junk;
            t_eq(logo_html(), '', 'junk logo renders nothing: "' . substr($junk, 0, 18) . '"');
        }
        //  …and a real one still works, or the guard has simply broken the feature.
        foreach (['data:image/png;base64,iVBORw0KGgo=', 'https://example.com/logo.png', '/uploads/logo.png'] as $good) {
            setting_set('logo_data', $good);
            $c = &settings_cache(); $c['logo_data'] = $good;
            $h = logo_html();
            t_ok(strpos($h, '<img') === 0, 'a real logo still renders: ' . substr($good, 0, 24));
            t_ok(strpos($h, 'src="' . htmlspecialchars($good, ENT_QUOTES) . '"') !== false,
                'with the source intact: ' . substr($good, 0, 24));
        }
    } finally {
        setting_set('logo_data', $was);
        $c = &settings_cache(); $c['logo_data'] = $was;
    }
});
