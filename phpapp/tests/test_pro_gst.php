<?php
// Freelancer tax identity (GST status + GSTIN + PAN). The professional's invoice is
// their OWN document; the platform is a facilitator and never invents a tax number.
t_section('freelancer GST / tax identity');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    connect_pro_migrate();

    // Format validators.
    t_ok(connect_pro_gstin_valid('24ABCDE1234F1Z5') === true, 'a well-formed GSTIN passes');
    t_ok(connect_pro_gstin_valid('24ABCDE1234F1Z') === false, 'a 14-char GSTIN fails');
    t_ok(connect_pro_gstin_valid('abcd') === false, 'junk GSTIN fails');
    t_ok(connect_pro_gstin_valid('') === false, 'empty GSTIN is not valid');
    t_ok(connect_pro_pan_valid('ABCDE1234F') === true, 'a well-formed PAN passes');
    t_ok(connect_pro_pan_valid('ABCDE12345') === false, 'a malformed PAN fails');

    // A fresh professional defaults to UNREGISTERED.
    $email = 'gst_' . substr(md5(uniqid('', true)), 0, 6) . '@ex.com';
    connect_pro_register(['name' => 'GST Pro', 'email' => $email, 'password' => 'password1']);
    $pid = (int)ops_val("SELECT id FROM cx_professionals WHERE email=?", [$email]);
    t_ok($pid > 0, 'the professional is registered');
    t_eq((string)ops_val("SELECT gst_status FROM cx_professionals WHERE id=?", [$pid]), 'UNREGISTERED', 'a new professional defaults to UNREGISTERED');

    // Registered but no/invalid GSTIN → rejected, nothing saved.
    [$bad, $bmsg] = connect_pro_tax_save($pid, ['gst_status' => 'REGISTERED', 'gstin' => 'nope']);
    t_ok($bad === false, 'registered without a valid GSTIN is rejected: ' . $bmsg);
    t_eq((string)ops_val("SELECT gstin FROM cx_professionals WHERE id=?", [$pid]), '', 'no GSTIN was written on the rejected save');

    // Registered with a valid GSTIN → saved, PAN derived from the GSTIN.
    [$ok, $msg] = connect_pro_tax_save($pid, ['gst_status' => 'REGISTERED', 'gstin' => '24ABCDE1234F1Z5']);
    t_ok($ok === true, 'a valid GSTIN is accepted: ' . $msg);
    t_eq((string)ops_val("SELECT gstin FROM cx_professionals WHERE id=?", [$pid]), '24ABCDE1234F1Z5', 'the GSTIN is stored');
    t_eq((string)ops_val("SELECT pan FROM cx_professionals WHERE id=?", [$pid]), 'ABCDE1234F', 'the PAN is derived from the GSTIN when not given');

    // Switching to UNREGISTERED clears the GSTIN.
    [$ok2] = connect_pro_tax_save($pid, ['gst_status' => 'UNREGISTERED']);
    t_ok($ok2 === true, 'switching to unregistered saves');
    t_eq((string)ops_val("SELECT gstin FROM cx_professionals WHERE id=?", [$pid]), '', 'the GSTIN is cleared when unregistered');
    t_eq((string)ops_val("SELECT gst_status FROM cx_professionals WHERE id=?", [$pid]), 'UNREGISTERED', 'status is back to UNREGISTERED');

    // A malformed PAN is rejected even for an unregistered professional.
    [$badpan] = connect_pro_tax_save($pid, ['gst_status' => 'UNREGISTERED', 'pan' => '123']);
    t_ok($badpan === false, 'a malformed PAN is rejected');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
