<?php
// Owner, 31 Oct 2026 (test book MOD-SAL): (1) drawing a signature from the
// quotation "saves but does not return to the quote"; (2) the company signature
// "allows to save" with image, name and designation all blank. Checked in a
// real browser on a local copy (typed subject kept, "Signed by" set to you);
// this guards the wiring.
t_section('Signatures — from the quotation, and the company signature block');
$v = (string) file_get_contents(__DIR__ . '/../views/ops/crm/quote_form.php');
t_ok(strpos($v, 'data-embed="/my-signature"') !== false && strpos($v, 'data-embed-keep') !== false && strpos($v, '<a href="/my-signature" target="_blank"') === false,
     'the signature opens in a popup over the quotation, not a new tab');
t_ok(strpos($v, "document.addEventListener('embed:done'") !== false && strpos($v, 'sel.value = me') !== false, 'on save, "Signed by" picks the new signature without reloading');
$js = (string) file_get_contents(__DIR__ . '/../assets/js/app.js');
t_ok(strpos($js, "window.__embedKeep") !== false && strpos($js, "new CustomEvent('embed:done'") !== false, 'a popup marked "keep" never reloads the page behind it');
$h = (string) file_get_contents(__DIR__ . '/../lib/crm.php');
$i = strpos($h, "if (\$route === 'crm-signature'"); $blk = substr($h, $i, 2600);
t_ok(strpos($blk, "'the signature image'") !== false && strpos($blk, "\"the signatory's name\"") !== false && strpos($blk, "'the designation'") !== false
     && strpos($blk, 'Nothing was saved.') !== false, 'the company signature needs image, name and designation before anything is saved');
t_ok(strpos($blk, "\$_SESSION['crm_sig_draft']") !== false, 'what was typed is kept when it is refused');
