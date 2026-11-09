<?php
// Owner, 9 Nov 2026 (test book MOD-SAL): an empty save was not visibly refused
// (the browser's own bubble stood in for the app's message), the letterhead had
// no check at all (an empty save wiped it), and "there is no list to verify"
// what was saved. Now both forms show the app's message, the letterhead needs a
// name and address, and each panel shows what prints, with who saved it when.
t_section('Quote letterhead and signature: refused when incomplete, shown when saved');
$c = (string) file_get_contents(__DIR__ . '/../lib/crm.php');
$i = strpos($c, "if (\$route === 'crm-letterhead'");
$blk = substr($c, $i, 1800);
t_ok(strpos($blk, "'the company name'") !== false && strpos($blk, "'the address'") !== false && strpos($blk, 'Nothing was saved.') !== false,
     'a letterhead without its name or address is refused, nothing saved');
t_ok(strpos($blk, "\$_SESSION['crm_lh_draft'] = \$lh") !== false, 'and what was typed stays in the boxes');
t_ok(strpos($blk, "setting_set('quote_lh_saved'") !== false && strpos($c, "setting_set('quote_sig_saved'") !== false, 'each save records when and by whom');

$v = (string) file_get_contents(__DIR__ . '/../views/ops/crm/template_list.php');
t_eq(substr_count($v, 'enctype="multipart/form-data" novalidate'), 2, 'both forms leave the refusal to the app\'s own message');
// Render with something saved.
$jpg = base64_encode((string) base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='));
$rows = []; $sigSet = true; $sigName = 'R. Sharma'; $sigDesig = 'Marketing Manager'; $lhLogo = true; $lhName = 'Probe Co Pvt Ltd';
$lhAddress = "1 Main St\nMumbai"; $lhContact = '+91 99'; $lhFooter = 'Computer generated'; $lhLogoData = $jpg; $sigImgData = $jpg;
$lhSaved = date('c') . '|admin'; $sigSaved = date('c') . '|admin';
ob_start(); try { include __DIR__ . '/../views/ops/crm/template_list.php'; } catch (Throwable $ex) { echo 'THREW ' . $ex->getMessage(); } $h = ob_get_clean();
t_ok(strpos($h, 'THREW') === false, 'the page draws');
t_eq(substr_count($h, 'Saved — this is what prints'), 2, 'both panels show what is saved');
$after = explode('Saved — this is what prints', $h);
t_ok(strpos($after[1], 'Probe Co Pvt Ltd') !== false && strpos($after[1], 'Mumbai') !== false, 'the letterhead as it prints: name and address');
t_ok(strpos($after[2], 'R. Sharma') !== false && strpos($after[2], 'Marketing Manager') !== false && strpos($after[2], 'data:image/jpeg;base64,') !== false,
     'the signature as it prints: image, name and designation');
t_ok(strpos($h, 'by admin') !== false, 'with who saved it');
