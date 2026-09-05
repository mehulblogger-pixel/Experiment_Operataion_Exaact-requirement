<?php
// =========================================================================
//  MGH Hire — licence key issuer (VENDOR / internal tool).
//
//  Mint a signed licence key to give a customer. Run from the command line:
//
//     php tools/licence-issue.php "Acme Pvt Ltd" 25 2027-03-31 Pro
//
//  Arguments:  <customer>  <seats>  [expiry YYYY-MM-DD]  [plan name]
//
//  Use the SAME signing secret the customer's install uses. In production set
//  it via the environment so it never sits in a shipped file:
//     MGHHIRE_LICENCE_SECRET="your-long-random-secret" php tools/licence-issue.php ...
// =========================================================================

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
require __DIR__ . '/../lib/db.php';      // for setting_* not needed; we only sign
require __DIR__ . '/../lib/licence.php';

$cust  = $argv[1] ?? '';
$seats = (int)($argv[2] ?? 0);
$exp   = $argv[3] ?? '';
$plan  = $argv[4] ?? 'Pro';

if ($cust === '' || $seats < 1) {
    fwrite(STDERR, "Usage: php tools/licence-issue.php \"<customer>\" <seats> [YYYY-MM-DD] [plan]\n");
    exit(1);
}
if ($exp && !strtotime($exp)) { fwrite(STDERR, "Bad expiry date: $exp\n"); exit(1); }

$claims = ['cust' => $cust, 'seats' => $seats, 'exp' => $exp, 'plan' => $plan, 'iss' => date('Y-m-d')];
$key = licence_make($claims);

echo "\nLicence for : $cust\n";
echo "Plan        : $plan\n";
echo "Seats       : $seats\n";
echo "Expires     : " . ($exp ?: 'never') . "\n";
echo "\n--- give the customer this key (paste into Billing → Apply licence) ---\n";
echo $key . "\n\n";
