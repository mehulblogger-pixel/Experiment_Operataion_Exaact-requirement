<?php
// Field-finding #9/#10 — add/edit "without losing my place". A reusable in-page popup: a [data-embed]
// trigger opens the EXISTING target screen in a modal iframe (rendered bare via embed=1); on save the
// screen's redirect posts {embedDone} to the host, which closes the popup and refreshes so the new record
// shows. No page you were working on is lost, and every screen works in the popup with no change of its own.
t_section('Field #9/#10 — in-page popup (embed) framework');

// --- The server side: is_embed, embed-aware redirect, form stamping, bare view ---
t_ok(function_exists('is_embed') && function_exists('embed_stamp_forms'), 'the embed helpers exist');

// is_embed reads embed=1 (GET) or _embed=1 (POST).
$G = $_GET; $P = $_POST;
$_GET['embed'] = '1'; $_POST = [];
t_ok(is_embed() === true, 'embed=1 in the query marks the request as embedded');
$_GET = []; $_POST['_embed'] = '1';
t_ok(is_embed() === true, '_embed=1 in a POST (stamped into forms) marks it embedded too');
$_GET = []; $_POST = [];
t_ok(is_embed() === false, 'a normal request is not embedded');
$_GET = $G; $_POST = $P;

// Forms get the hidden _embed flag on the way out, so their POST is recognised as embedded.
t_ok(strpos(embed_stamp_forms('<form action="/x"><input name="a"></form>'), 'name="_embed" value="1"') !== false,
     'every form is stamped with the _embed flag');

// redirect() is embed-aware (posts a message to the host instead of navigating the iframe).
$hs = file_get_contents(__DIR__ . '/../lib/helpers.php');
t_ok(strpos($hs, "is_embed()") !== false && strpos($hs, "parent.postMessage({embedDone:1") !== false,
     'a redirect inside the popup posts {embedDone} to the host instead of a Location header');

// view() renders bare (no chrome) and stamps forms when embedded.
$idx = file_get_contents(__DIR__ . '/../index.php');
t_ok(strpos($idx, "layout_embed_top.php") !== false && strpos($idx, 'embed_stamp_forms($out)') !== false,
     'view() uses the bare layout and stamps forms for an embedded request');
t_ok(is_file(__DIR__ . '/../views/layout_embed_top.php') && is_file(__DIR__ . '/../views/layout_embed_bottom.php'),
     'the bare (chromeless) layout files exist');
$et = file_get_contents(__DIR__ . '/../views/layout_embed_top.php');
t_ok(strpos($et, 'class="side"') === false && strpos($et, 'app.css') !== false,
     'the bare layout keeps the stylesheet but drops the sidebar/nav chrome');

// --- The client side: the modal opens [data-embed] and closes on {embedDone} ---
$js = file_get_contents(__DIR__ . '/../assets/js/app.js');
t_ok(strpos($js, 'initEmbedModals') !== false && strpos($js, "[data-embed]") !== false,
     'app.js opens a [data-embed] trigger in a modal');
t_ok(strpos($js, "ev.data.embedDone") !== false && strpos($js, 'location.reload()') !== false,
     'the host closes the popup and refreshes when the embedded screen signals done');
$css = file_get_contents(__DIR__ . '/../assets/css/app.css');
t_ok(strpos($css, '.embed-backdrop') !== false && strpos($css, '.embed-frame') !== false, 'the popup has styles');

// --- The two wired flows (reference adopters), with an href fallback for JS-off ---
$c3 = file_get_contents(__DIR__ . '/../views/ops/customer360.php');
t_ok(strpos($c3, 'data-embed="/call-new?client_id=') !== false, 'Raise inspection call opens in the popup from client-360');
t_ok(strpos($c3, 'data-embed="/partner-edit?id=') !== false, 'Edit / add address opens in the popup from client-360');
t_ok(strpos($c3, 'href="/call-new?client_id=') !== false, 'the trigger keeps a plain href fallback (works with JS off)');
