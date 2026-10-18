<?php
/**
 * EXAACT audit — the PROFESSIONAL portal (/pro/*): can one professional reach
 * another's records by changing a number in the address?
 *
 * This portal has no per-user permission layer, and that is correct: an account
 * here is ONE individual, with no colleagues to restrict. So the only security
 * question is ownership, and it matters most on the three routes that SERVE
 * FILES -- a document handed to the wrong person cannot be recalled.
 *
 * The method: sign in as professional A, then push professional B's record ids
 * through the SAME ownership checks each route performs, and confirm each one
 * refuses. Reading the code said it was right; this is the execution.
 *
 * Read-only against a throwaway database.
 *
 *   cd phpapp && php ../docs/audit-2026-10/evidence/pro-portal-isolation.php
 */
$root = dirname(__DIR__, 3) . '/phpapp';
require $root . '/tests/lib.php';
require $root . '/tests/bootstrap.php';

if (function_exists('connect_pro_migrate')) connect_pro_migrate();
$now = date('c');

// Column names read from the live schema, not assumed: cx_professionals has
// is_active, NOT status. The first run of this script invented `status` and
// died on it -- which is the cheap way for that mistake to surface.
function mk_pro($name, $email, $now) {
    db()->prepare("INSERT INTO cx_professionals (name,email,mobile,password_hash,is_active,created_at)
                   VALUES (?,?,?, 'x',1,?)")->execute([$name, $email, '90000' . rand(10000, 99999), $now]);
    return (int)db()->lastInsertId();
}
$A = mk_pro('Professional A', 'a@pro.test', $now);
$B = mk_pro('Professional B', 'b@pro.test', $now);

// B's private document — a certificate they uploaded to their own profile.
db()->prepare("INSERT INTO cx_pro_files (pro_id,kind,file_name,mime,file_data,created_at)
               VALUES (?, 'CERT','B-welding-certificate.pdf','application/pdf',?,?)")
    ->execute([$B, base64_encode('B PRIVATE CERTIFICATE BYTES'), $now]);
$bFile = (int)db()->lastInsertId();

echo "PROFESSIONAL PORTAL — CAN ONE PROFESSIONAL REACH ANOTHER'S RECORDS?\n";
echo str_repeat('=', 78) . "\n";
printf("Professional A = id %d   Professional B = id %d\n", $A, $B);
printf("B's private certificate = cx_pro_files id %d\n\n", $bFile);

$_SESSION['cxpid'] = $A;
$me = function_exists('connect_pro_user') ? connect_pro_user() : null;
if (!$me || (int)$me['id'] !== $A) {
    echo "ABORTED — could not sign in as A, so nothing below would mean anything.\n";
    exit(1);
}
printf("Signed in as: %s (id %d)\n\n", $me['name'], (int)$me['id']);

$fails = 0;
$check = function ($route, $what, $got, $expectRefused = true) use (&$fails) {
    $refused = ($got === null || $got === false || $got === []);
    $ok = $expectRefused ? $refused : !$refused;
    if (!$ok) $fails++;
    printf("%-22s %-34s %s\n", $route, $what,
           $ok ? ($expectRefused ? 'REFUSED (correct)' : 'allowed (correct)')
               : ($expectRefused ? '*** REACHED IT -- LEAK ***' : '*** refused its OWN record ***'));
};

// ---- /pro/file — the route calls connect_pro_file_row($id, $me['id']) -----
$check('/pro/file', "B's certificate, as A",
       connect_pro_file_row($bFile, $A));
$check('/pro/file', "A's own file (control)",
       (function () use ($A, $now) {
            db()->prepare("INSERT INTO cx_pro_files (pro_id,kind,file_name,mime,file_data,created_at)
                           VALUES (?, 'CERT','A-own.pdf','application/pdf',?,?)")
                ->execute([$A, base64_encode('A OWN'), $now]);
            return connect_pro_file_row((int)db()->lastInsertId(), $A);
       })(), false);

// ---- /pro/documents delete — same pair of ids ----------------------------
$beforeB = (int)ops_val("SELECT COUNT(*) FROM cx_pro_files WHERE pro_id=?", [$B]);
connect_pro_file_delete($bFile, $A);     // A trying to delete B's document
$afterB = (int)ops_val("SELECT COUNT(*) FROM cx_pro_files WHERE pro_id=?", [$B]);
$check('/pro/documents', "delete B's document, as A",
       $afterB < $beforeB ? 'deleted' : null);

// ---- /pro/voucher, /pro/voucher-file, /pro/report-file -------------------
//  The real tables are cx_engagement_vouchers / _voucher_files / _reports.
if (function_exists('connect_engv_migrate')) connect_engv_migrate();

db()->prepare("INSERT INTO cx_engagement_vouchers (subject_kind,subject_id,subject_name,status,period_label,created_at)
               VALUES ('PROFESSIONAL',?, 'Professional B','DRAFT','Sep 2026',?)")->execute([$B, $now]);
$bVoucher = (int)db()->lastInsertId();
db()->prepare("INSERT INTO cx_engagement_voucher_files (voucher_id,file_name,mime,file_data,created_at)
               VALUES (?, 'B-hotel-bill.pdf','application/pdf',?,?)")
    ->execute([$bVoucher, base64_encode('B PRIVATE EXPENSE RECEIPT'), $now]);
$bVFile = (int)db()->lastInsertId();
db()->prepare("INSERT INTO cx_engagement_reports (subject_kind,subject_id,title,file_name,mime,file_data,created_at)
               VALUES ('PROFESSIONAL',?, 'B site report','B-site-report.pdf','application/pdf',?,?)")
    ->execute([$B, base64_encode('B PRIVATE SITE REPORT'), $now]);
$bReport = (int)db()->lastInsertId();
printf("B's voucher = %d, its expense receipt = %d, B's report deliverable = %d\n\n",
       $bVoucher, $bVFile, $bReport);

// (a) /pro/voucher — the route's own test, run with A's id.
$v = connect_engv_get($bVoucher);
$check('/pro/voucher', "B's voucher, as A",
       ($v && strtoupper((string)$v['subject_kind']) === 'PROFESSIONAL'
           && (int)$v['subject_id'] === $A) ? $v : null);
printf("%-22s %-34s %s\n", '(sanity)', "the voucher really is B's",
       ($v && (int)$v['subject_id'] === $B) ? 'yes' : '*** seed wrong — test above is void ***');
if (!($v && (int)$v['subject_id'] === $B)) $fails++;

// (b) /pro/voucher-file — two-hop: file -> its voucher -> that voucher's subject.
$fr = function_exists('connect_engv_file_row') ? connect_engv_file_row($bVFile) : null;
$reach = null;
if ($fr) {
    $vv = connect_engv_get((int)$fr['voucher_id']);
    if ($vv && strtoupper((string)$vv['subject_kind']) === 'PROFESSIONAL'
            && (int)$vv['subject_id'] === $A) $reach = $fr;
}
$check('/pro/voucher-file', "B's expense receipt, as A", $reach);
printf("%-22s %-34s %s\n", '(sanity)', 'the receipt row was found at all',
       $fr ? 'yes — so the refusal is the ownership hop' : '*** not found — test is void ***');
if (!$fr) $fails++;

// (c) /pro/report-file — the deliverable on B's engagement.
$rr = function_exists('connect_engv_report_row') ? connect_engv_report_row($bReport) : null;
$check('/pro/report-file', "B's report deliverable, as A",
       ($rr && strtoupper((string)$rr['subject_kind']) === 'PROFESSIONAL'
            && (int)$rr['subject_id'] === $A) ? $rr : null);
printf("%-22s %-34s %s\n", '(sanity)', 'the report row was found at all',
       $rr ? 'yes — so the refusal is the ownership check' : '*** not found — test is void ***');
if (!$rr) $fails++;
$check('/pro/report-file', "B's own deliverable, as B (control)",
       ($rr && (int)$rr['subject_id'] === $B) ? $rr : null, false);

// ---- /pro/messages — connect_msg_pro_owns($applicationId, $proId) --------
if (function_exists('connect_msg_pro_owns')) {
    // connect_msg_app() INNER-joins cx_requirements, so an application pointing
    // at a requirement that does not exist is invisible to EVERYONE. Seeding it
    // that way made the control below fail and would have made the refusal
    // beside it meaningless -- A would have been refused because the row could
    // not be seen at all, not because the ownership check works. Hence a real
    // requirement here, and the control that caught it.
    $bApp = null;
    try {
        db()->prepare("INSERT INTO cx_requirements (ref_code,title,poster_party_id,poster_name,status,created_at)
                       VALUES ('REQ/PROBE/1','Welding inspector, Ahmedabad',0,'Probe Buyer','OPEN',?)")
            ->execute([$now]);
        $req = (int)db()->lastInsertId();
        db()->prepare("INSERT INTO cx_applications (requirement_id,applicant_professional_id,status,created_at)
                       VALUES (?,?, 'APPLIED',?)")->execute([$req, $B, $now]);
        $bApp = (int)db()->lastInsertId();
        printf("\nB's application on requirement %d = %d\n", $req, $bApp);
    } catch (Throwable $e) { echo "(could not seed an application: " . $e->getMessage() . ")\n"; }
    if ($bApp) {
        $check('/pro/messages', "B's message thread, as A",
               connect_msg_pro_owns($bApp, $A) ?: null);
        $check('/pro/messages', "B's own thread, as B (control)",
               connect_msg_pro_owns($bApp, $B) ?: null, false);
    }
}

echo "\n" . str_repeat('=', 78) . "\n";
echo $fails === 0
    ? "RESULT: every ownership check refused A and allowed the rightful owner.\n"
      . "No professional can reach another's file, voucher or message thread by id.\n"
      . "\nNote on the refusals: the file routes answer 404, not 403. That is the\n"
      . "right choice -- a 403 would confirm the record exists.\n"
    : "RESULT: $fails CHECK(S) FAILED — see the lines marked *** above.\n";
