<?php
// ============================================================================
//  Connect scenario seed — coverage guard.
//
//  Runs the full marketplace seeder in a rolled-back transaction and asserts it
//  leaves NOTHING out: every user type, every requirement / application /
//  voucher status, every engagement basis, every subject kind and rate model,
//  all verification tiers, commission on every voucher, and at least one
//  settled voucher with a released report. (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('connect scenario seed coverage');

if (!function_exists('connect_seed_scenarios')) { t_ok(false, 'the Connect seeder is loaded'); return; }

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $r = connect_seed_scenarios(true);   // force, into the throwaway tx
    t_ok(empty($r['skipped']), 'the seeder runs');

    $vals = fn($s) => array_map(fn($x) => strtoupper((string)$x[0]), db()->query($s)->fetchAll(PDO::FETCH_NUM));
    $has  = fn($set, $x) => in_array(strtoupper($x), $set, true);

    // Users — every type present
    t_ok((int)ops_val("SELECT COUNT(*) FROM users WHERE username LIKE 'cx.%'") >= 4, 'staff desk users seeded (master/coord/finance/inspector)');
    t_ok((int)ops_val("SELECT COUNT(*) FROM client_users WHERE email LIKE '%@acme.test' OR email LIKE '%@reliance.test'") >= 3, 'client portal users seeded');
    t_ok((int)ops_val("SELECT COUNT(*) FROM cx_professionals WHERE email LIKE '%@pro.test'") >= 4, 'freelancers seeded');
    $tiers = $vals("SELECT DISTINCT verification_tier FROM cx_professionals WHERE email LIKE '%@pro.test'");
    foreach (['REGISTERED','ID_VERIFIED','CREDENTIAL_VERIFIED','PROVEN'] as $t) t_ok($has($tiers, $t), "freelancer tier present: $t");
    t_ok((int)ops_val("SELECT COUNT(*) FROM cx_bench") >= 1, 'agency bench people seeded');

    // Requirements — every status
    $rs = $vals("SELECT DISTINCT status FROM cx_requirements");
    foreach (['DRAFT','OPEN','SHORTLISTING','AWARDED','CLOSED','CANCELLED','EXPIRED'] as $s) t_ok($has($rs, $s), "requirement status present: $s");

    // Applications — every status
    $as = $vals("SELECT DISTINCT status FROM cx_applications");
    foreach (['APPLIED','SHORTLISTED','OFFERED','ACCEPTED','REJECTED','WITHDRAWN'] as $s) t_ok($has($as, $s), "application status present: $s");

    // Engagements — every basis, subject kind, rate model
    $bases = $vals("SELECT DISTINCT basis FROM cx_engagements");
    foreach (['MAN_DAYS','MAN_MONTHS','DEPUTATION','CONTINUOUS','FREQUENCY'] as $b) t_ok($has($bases, $b), "engagement basis present: $b");
    $kinds = $vals("SELECT DISTINCT subject_kind FROM cx_engagements");
    foreach (['PROFESSIONAL','INSPECTOR','BENCH'] as $k) t_ok($has($kinds, $k), "engagement subject present: $k");
    $models = $vals("SELECT DISTINCT rate_inclusive FROM cx_engagements");
    t_ok($has($models, 'INCLUSIVE') && $has($models, 'EXCLUSIVE'), 'both rate models present');
    $cads = $vals("SELECT DISTINCT voucher_cadence FROM cx_engagements");
    t_ok($has($cads, 'PER_DAY') && $has($cads, 'PER_DEPLOYMENT'), 'both voucher cadences present');

    // Vouchers — every status, commission on all, a settlement + a report
    $vs = $vals("SELECT DISTINCT status FROM cx_engagement_vouchers");
    foreach (['DRAFT','SUBMITTED','APPROVED','PAID','REJECTED'] as $s) t_ok($has($vs, $s), "voucher status present: $s");
    $total = (int)ops_val("SELECT COUNT(*) FROM cx_engagement_vouchers");
    $withComm = (int)ops_val("SELECT COUNT(*) FROM cx_engagement_vouchers WHERE commission_total > 0");
    t_eq($withComm, $total, 'every voucher carries a computed platform commission');
    t_ok((int)ops_val("SELECT COUNT(*) FROM cx_engagement_vouchers WHERE settled_at <> ''") >= 1, 'at least one fully settled voucher');
    t_ok((int)ops_val("SELECT COUNT(*) FROM cx_engagement_reports") >= 1, 'at least one inspection-report deliverable');
    t_ok((int)ops_val("SELECT COUNT(*) FROM cx_engagement_voucher_files") >= 1, 'receipts attached to vouchers');

    // A returned voucher carries the client's clarification note
    t_ok((int)ops_val("SELECT COUNT(*) FROM cx_engagement_vouchers WHERE status='REJECTED' AND decided_note <> ''") >= 1, 'a returned voucher carries a clarification note');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
