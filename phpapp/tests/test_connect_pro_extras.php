<?php
// Connect — freelancer small-things: forgot-password token flow + own-files store.
// (t_eq is t_eq($got, $want).)
t_section('connect freelancer extras (forgot-password + files)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_pro_register(['name' => 'Reset Riya', 'email' => 'riya@example.com', 'password' => 'oldpass12']);
    $pid = connect_pro_id();

    // --- forgot-password ----------------------------------------------------
    // Unknown e-mail → no token issued, no leak.
    t_eq(connect_pro_reset_request('nobody@example.com'), '', 'an unknown e-mail issues no reset link (no enumeration)');
    // Known e-mail → a link with a token.
    $link = connect_pro_reset_request('riya@example.com');
    t_ok(strpos($link, '/pro/reset?t=') !== false, 'a known e-mail gets a reset link');
    preg_match('/t=([a-f0-9]+)/', $link, $mm); $tok = $mm[1] ?? '';
    t_ok($tok !== '', 'the link carries a token');
    t_eq(connect_pro_reset_problem($tok), '', 'the fresh token is usable');
    t_ok(connect_pro_reset_problem('deadbeef') !== '', 'a bogus token is rejected');

    // Complete the reset — mismatched, too short, then good.
    t_ok(connect_pro_reset_complete($tok, 'newpass12', 'different') !== '', 'mismatched passwords are rejected');
    t_ok(connect_pro_reset_complete($tok, 'short', 'short') !== '', 'a short password is rejected');
    t_eq(connect_pro_reset_complete($tok, 'newpass12', 'newpass12'), '', 'a valid reset succeeds');
    // The token is single-use, and the new password works.
    t_ok(connect_pro_reset_problem($tok) !== '', 'the token is burned after use');
    unset($_SESSION['cxpid']);
    t_eq(connect_pro_login('riya@example.com', 'newpass12'), '', 'the new password signs in');
    t_ok(connect_pro_login('riya@example.com', 'oldpass12') !== '', 'the old password no longer works');

    // --- files (photo / CV) : ownership-scoped ------------------------------
    // Insert directly (is_uploaded_file() blocks a real upload path in CLI).
    db()->prepare("INSERT INTO cx_pro_files (pro_id,kind,file_name,mime,size,file_data,created_at) VALUES (?, 'CV','cv.pdf','application/pdf',1234,?,?)")
        ->execute([$pid, base64_encode('PDFDATA'), date('c')]);
    db()->prepare("INSERT INTO cx_pro_files (pro_id,kind,file_name,mime,size,file_data,created_at) VALUES (?, 'AVATAR','me.jpg','image/jpeg',900,?,?)")
        ->execute([$pid, base64_encode('JPGDATA'), date('c')]);
    $files = connect_pro_files($pid);
    t_eq(count($files), 2, 'the professional has two files');
    t_ok(connect_pro_avatar_id($pid) > 0, 'the avatar id resolves');
    // Ownership: another professional cannot read this one's file.
    connect_pro_register(['name' => 'Other', 'email' => 'o3@example.com', 'password' => 'secret12']);
    $pid2 = connect_pro_id();
    $fid = (int)$files[0]['id'];
    t_ok(connect_pro_file_row($fid, $pid) !== null, 'the owner can read their own file');
    t_ok(connect_pro_file_row($fid, $pid2) === null, 'another professional cannot read it (ownership-scoped)');
    // Delete is scoped too.
    connect_pro_file_delete($fid, $pid2);
    t_ok(connect_pro_file_row($fid, $pid) !== null, 'a non-owner delete does nothing');
    connect_pro_file_delete($fid, $pid);
    t_eq(count(connect_pro_files($pid)), 1, 'the owner can delete their own file');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
