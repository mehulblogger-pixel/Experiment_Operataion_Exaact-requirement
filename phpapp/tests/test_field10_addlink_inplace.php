<?php
// Field-finding #10 — "if something is missing on the New Inspection Call (or anywhere) and we click to
// add it, we cannot come back to the call screen after adding." The "+ Add new" client/vendor links used
// to open the partner form in a SEPARATE browser window (and, when popups were blocked, navigated the tab
// away entirely — losing everything typed on the call). Now they open the SAME form as an in-page popup
// (iframe modal, reusing the #9 embed shell); on save the picker hands the new record back to the dropdown
// and the popup closes WITHOUT reloading — so the half-filled call form is never lost.
t_section('Field #10 — "+ Add new" opens in-page, keeps the form you were filling');

$js = file_get_contents(__DIR__ . '/../assets/js/app.js');

// The add-link handler opens the picker as an in-page modal (openEmbed), not a separate window.
$p = strpos($js, 'function initPartnerPicker');
t_ok($p !== false, 'the partner "+ Add new" handler exists');
$blk = substr($js, $p, 4200);
t_ok(strpos($blk, "typeof openEmbed === 'function'") !== false && strpos($blk, 'openEmbed(url') !== false,
     'the add link opens the partner form as an in-page popup (iframe modal)');
t_ok(strpos($blk, 'pending.embed = true') !== false,
     'the handler records that the popup was opened in-page (so it is closed in-page too)');
// window.open survives only as a fallback for an old cached script, not the primary path.
t_ok(strpos($blk, 'openEmbed(url') < strpos($blk, "window.open(url, 'exaactPartnerPicker'"),
     'a separate browser window is only the fallback, tried after the in-page popup');

// On return, the popup closes WITHOUT reloading — the in-progress call form must survive.
$mp = strpos($js, "d.type !== 'exaact:partner-added'");
t_ok($mp !== false, 'the page still listens for the saved partner and selects it into the dropdown');
$mblk = substr($js, $mp, 1800);
t_ok(strpos($mblk, 'closeEmbed(false)') !== false,
     'the popup closes without reloading, so nothing typed on the call form is lost');
t_ok(strpos($mblk, 'location.reload') === false,
     'the return handler never reloads the host (that would discard the half-filled call)');

// Server side: the picker hands the record back via postMessage to window.opener OR window.parent,
// so the very same return works whether the form was a separate window or an in-page iframe.
$idx = file_get_contents(__DIR__ . '/../index.php');
t_ok(strpos($idx, 'exaact:partner-added') !== false
     && strpos($idx, '(window.opener||window.parent).postMessage') !== false,
     'the saved-partner message reaches the host in both a window and an in-page iframe');
