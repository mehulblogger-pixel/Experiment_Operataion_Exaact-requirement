<?php
// ============================================================================
//  An upload that is refused has to say WHY, and say something true.
//
//  upload_reject_reason() was already strong: a size cap, banned extensions
//  (including the report.php.jpg trick), <?php and shebang detection, a
//  declared picture that must really be one, SVG refused outright, markup
//  refused under any name. All three portal upload paths call it.
//
//  What was wrong was everything around it:
//
//   1. The /pro routes DISCARDED what the upload functions returned, so every
//      one of those reasons was swallowed. The professional saw a page with
//      their file missing and no explanation -- on the documents screen, on a
//      voucher attachment, and on the REPORT DELIVERABLE the client pays for.
//   2. A file over php.ini's own upload_max_filesize arrives as an EMPTY slot.
//      The code read that as "nothing chosen" and said "Choose a file to
//      upload." -- to somebody who had just chosen one.
//   3. A POST over post_max_size loses $_POST entirely, token included, so the
//      CSRF gate fired and blamed the FORM for an oversized file.
//   4. The app advertises its own limit (12 MB) even where php.ini stops
//      lower (2 MB here), so the number quoted could not be honoured.
//
//  (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('uploads — a refusal has to say why, and say something true');

// ---- 1. the limit quoted is the one that actually applies ----------------
$app = upload_max_mb();
$eff = upload_effective_max_mb();
t_ok($eff >= 1, 'upload_effective_max_mb() returns a usable number (' . $eff . ' MB)');
t_ok($eff <= $app, 'it never quotes MORE than the app allows (app ' . $app . ' MB)');
$raw = trim((string)ini_get('upload_max_filesize'));
if (preg_match('/^(\d+)\s*M$/i', $raw, $m) && (int)$m[1] < $app)
    t_eq($eff, (int)$m[1],
         'where php.ini is lower (' . $raw . '), THAT is the number quoted — not the app\'s');

// ---- 2. every browser upload error gets plain words ---------------------
t_eq(upload_error_reason(['error' => UPLOAD_ERR_OK, 'name' => 'a.pdf']), '',
     'a clean upload gives no reason');
t_eq(upload_error_reason(['error' => UPLOAD_ERR_NO_FILE, 'name' => '']), '',
     'genuinely choosing nothing gives no reason — the caller\'s own words fit');
foreach ([UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE] as $code) {
    $why = upload_error_reason(['error' => $code, 'name' => 'scan.pdf']);
    t_ok(stripos($why, 'too large') !== false, 'an over-limit file is called too large: ' . $why);
    t_ok(strpos($why, (string)$eff . ' MB') !== false, '...and names the limit that applies');
    t_ok(strpos($why, 'scan.pdf') !== false, '...and names the file');
}
t_ok(stripos(upload_error_reason(['error' => UPLOAD_ERR_PARTIAL, 'name' => 'x.pdf']), 'part') !== false,
     'a dropped connection says the file only arrived in part');
t_ok(upload_error_reason(['error' => UPLOAD_ERR_CANT_WRITE, 'name' => 'x.pdf']) !== '',
     'a server-side failure says something rather than nothing');
t_eq(upload_error_reason('not an array'), '', 'a non-array is handled without a warning');

// ---- 3. an oversized POST is recognised for what it is ------------------
$keepM = $_SERVER['REQUEST_METHOD'] ?? null; $keepL = $_SERVER['CONTENT_LENGTH'] ?? null;
$keepP = $_POST; $keepF = $_FILES;
$_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = []; $_FILES = [];
$_SERVER['CONTENT_LENGTH'] = '999999999';
t_eq(upload_post_too_large(), true,
     'a POST far over post_max_size, with an empty body, is recognised');
$_SERVER['CONTENT_LENGTH'] = '10';
t_eq(upload_post_too_large(), false, 'a small empty POST is NOT blamed on size');
$_POST = ['_csrf' => 'x'];
$_SERVER['CONTENT_LENGTH'] = '999999999';
t_eq(upload_post_too_large(), false, 'a POST that DID arrive is not blamed on size');
$_SERVER['REQUEST_METHOD'] = 'GET'; $_POST = [];
t_eq(upload_post_too_large(), false, 'a GET is never blamed on size');
$_SERVER['REQUEST_METHOD'] = $keepM; $_POST = $keepP; $_FILES = $keepF;
if ($keepL === null) unset($_SERVER['CONTENT_LENGTH']); else $_SERVER['CONTENT_LENGTH'] = $keepL;

// ---- 4. the error code is checked FIRST, before "choose a file" ---------
foreach ([
    ['lib/connect_pro.php',       'connect_pro_file_add'],
    ['lib/connect_engvoucher.php','connect_engv_file_add'],
    ['lib/connect_engvoucher.php','connect_engv_report_add'],
] as [$file, $fn]) {
    $src = file_get_contents(__DIR__ . '/../' . $file);
    if (preg_match('/function ' . preg_quote($fn, '/') . '\s*\([^)]*\)\s*\{(.*?)\n\}/s', $src, $m)) {
        $body = $m[1];
        // Match the STATEMENT, not the bare phrase: the explanatory comment above
        // the check quotes "Choose a file to upload." too, and an earlier version
        // of this assertion found the comment first and failed a correct function.
        $iErr = strpos($body, 'upload_error_reason($file)');
        $iChoose = strpos($body, "return [false, 'Choose a file to upload.'");
        t_ok($iErr !== false, $fn . '() asks why the browser\'s upload failed');
        t_ok($iErr !== false && $iChoose !== false && $iErr < $iChoose,
             '...BEFORE falling back to "Choose a file to upload."');
    } else {
        t_ok(false, 'could not locate ' . $fn . '()');
    }
}

// ---- 5. all three gates name an oversized upload, not the form ---------
foreach (['lib/portal.php' => 'portal_csrf_or_die',
          'lib/cvp.php' => 'cvp_vendor_csrf_or_die',
          'lib/connect_pro.php' => 'connect_pro_csrf_or_die'] as $file => $fn) {
    $src = file_get_contents(__DIR__ . '/../' . $file);
    if (preg_match('/function ' . preg_quote($fn, '/') . '\s*\([^)]*\)\s*\{(.*?)\n\}/s', $src, $m))
        t_ok(strpos($m[1], 'upload_post_too_large') !== false,
             $fn . '() blames the file, not the form, when the body was too large');
    else t_ok(false, 'could not locate ' . $fn . '()');
}

// ---- 6. the professional portal keeps every answer it is given ----------
// Nine calls used to throw [ok,msg] away. Each must now reach the person.
$pro = file_get_contents(__DIR__ . '/../lib/connect_pro.php');
foreach ([
    'connect_pro_file_add'     => 'the documents upload',
    'connect_pro_file_delete'  => 'removing a document',
    'connect_engv_file_add'    => 'a voucher attachment',
    'connect_engv_file_delete' => 'removing a voucher attachment',
    'connect_engv_report_add'  => 'the report deliverable',
    'connect_engv_add_line'    => 'adding a day',
    'connect_engv_delete_line' => 'removing a day',
    'connect_engv_set_status'  => 'submitting or reopening',
    'connect_cred_cert_save'   => 'saving a certificate',
    'connect_cred_project_save'=> 'saving project experience',
    'connect_pro_profile_save' => 'saving the passport',
] as $fn => $what) {
    // every call outside the function's own definition must have its result
    // captured into a variable or passed straight to the flash
    $calls = 0; $kept = 0;
    foreach (explode("\n", $pro) as $line) {
        if (strpos($line, $fn . '(') === false) continue;
        if (strpos($line, 'function ' . $fn) !== false) continue;
        $calls++;
        if (preg_match('/(\[\s*\$\w+|\$\w+\s*=|connect_pro_flash_set\s*\()/', $line)) $kept++;
    }
    if ($calls > 0)
        t_eq($kept, $calls, $what . ': all ' . $calls . ' call(s) keep the answer instead of discarding it');
}

// And no action may be invoked twice just to build its own message -- an
// earlier version of this fix called connect_engv_delete_line() THREE times,
// so a successful delete reported a failure.
foreach (['connect_pro_profile_save', 'connect_engv_delete_line', 'connect_engv_file_delete'] as $fn) {
    $n = 0;
    foreach (explode("\n", $pro) as $line)
        if (strpos($line, $fn . '(') !== false && strpos($line, 'function ' . $fn) === false) $n++;
    t_eq($n, 1, $fn . '() is invoked exactly once per request, not re-run to build a message');
}
