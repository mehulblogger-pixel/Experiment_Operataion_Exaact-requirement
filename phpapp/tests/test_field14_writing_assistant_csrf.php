<?php
// Field-finding #14 — "Improve wording" returned "could not reach the writing assistant", read as an
// AI failure "even though AI is activated". Root cause: it is NOT an AI feature at all — the ✒️ Improve
// wording button posts to the rule-based /writing-assistant, and its fetch() omitted the CSRF token.
// Every POST is CSRF-checked globally (index.php); a tokenless POST is rejected and REDIRECTED to the
// HTML page, so the JS `r.json()` threw and fell into `.catch` → the "could not reach" alert. The AI
// ✨ Polish button worked precisely because it already sent _csrf — which is why it looked AI-related.
t_section('Field #14 — the writing-assistant POST carries its CSRF token');

// The root cause, proven: the CSRF gate rejects a POST with no/blank token, accepts the real one.
$_SESSION['csrf'] = '';                                  // fresh session
$tok = csrf_token();                                     // mints and stores one
t_ok($tok !== '', 'a CSRF token is minted for the session');
t_ok(csrf_ok('') === false, 'a POST with NO csrf token is rejected (this is what broke Improve wording)');
t_ok(csrf_ok('wrong-token') === false, 'a POST with the wrong token is rejected');
t_ok(csrf_ok($tok) === true, 'a POST carrying the real token is accepted');

// The fix: the ✒️ Improve wording fetch now includes the token, exactly like the ✨ Polish fetch.
$fill = file_get_contents(__DIR__ . '/../views/ops/idems/fill.php');
$imp = strpos($fill, 'function idemsImprove');
t_ok($imp !== false, 'the Improve wording handler exists');
$impBlk = substr($fill, $imp, 800);
t_ok(strpos($impBlk, "ajax:'1'") !== false && strpos($impBlk, "_csrf:'<?= e(csrf_token()) ?>'") !== false,
     'the Improve wording POST now carries _csrf (the fix)');
// The AI Polish fetch already carried it — kept as the reference.
t_ok(strpos($fill, "field: k, doc: '<?= (int)\$doc['id'] ?>', _csrf: '<?= e(csrf_token()) ?>'") !== false,
     'the AI Polish POST still carries its token (unchanged reference)');

// Same defect, same fix: the availability quick-update POST also carries the token now.
$avail = file_get_contents(__DIR__ . '/../views/ops/availability.php');
t_ok(strpos($avail, "ajax:'1', day:day, _csrf:'<?= e(csrf_token()) ?>'") !== false,
     'the availability quick-update POST now carries _csrf too (same root cause)');
