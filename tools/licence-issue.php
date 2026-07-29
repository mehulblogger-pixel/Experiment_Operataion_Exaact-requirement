<?php
// ---------------------------------------------------------------------------
//  Issue a licence key. MGH SIDE ONLY — this must never be deployed.
//
//  It needs the PRIVATE signing key, which is the one thing that must not exist
//  on a customer's server. Keep it off the web root, out of git, and backed up
//  somewhere you will still have in three years: lose it and every future
//  renewal has to be re-issued against a new public key, which means touching
//  every installation.
//
//  FIRST TIME — make the signing pair:
//      php tools/licence-issue.php --newkeys
//  It writes licence-private.pem and prints the public half. Put the public
//  half into LICENCE_PUBKEY_DEFAULT in lib/licencekey.php (or ship it as the
//  LICENCE_PUBKEY environment variable), and keep the private file safe.
//
//  ISSUING — one line per sale or renewal:
//      php tools/licence-issue.php \
//          --cust="Vertex Steel Pvt Ltd" \
//          --ref=MGH-2026-0042 \
//          --mods=sales,money \
//          --tier=sales:pro,money:starter \
//          --seats=10 \
//          --months=12
//
//  It prints the key. Send it to the customer; they paste it into
//  Settings → Licence. Nothing phones home, so this works for an on-premise
//  install behind a corporate firewall exactly as it does for a VPS.
//
//  RENEWAL is the same command with a new --months. The customer pastes the new
//  key over the old one. There is no revocation list: a key stops working when
//  it expires, which is the only revocation an offline system can honestly
//  offer. Keep the terms short enough that this matters — annual, not decadal.
// ---------------------------------------------------------------------------

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("This tool is command-line only.\n"); }

$args = [];
foreach (array_slice($argv, 1) as $a) {
    if (strncmp($a, '--', 2) !== 0) continue;
    $a = substr($a, 2);
    $eq = strpos($a, '=');
    if ($eq === false) $args[$a] = true;
    else $args[substr($a, 0, $eq)] = substr($a, $eq + 1);
}

$privPath = $args['priv'] ?? (__DIR__ . '/licence-private.pem');

// ---- Make a new signing pair ----------------------------------------------
if (!empty($args['newkeys'])) {
    if (is_file($privPath)) {
        fwrite(STDERR, "REFUSING: $privPath already exists.\n"
             . "Overwriting it would invalidate every licence ever issued with it.\n"
             . "Move it aside deliberately if you really mean to start again.\n");
        exit(1);
    }
    $k = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    if (!$k) { fwrite(STDERR, "Could not generate a key: " . openssl_error_string() . "\n"); exit(1); }
    openssl_pkey_export($k, $priv);
    file_put_contents($privPath, $priv);
    @chmod($privPath, 0600);
    echo "Private key written to $privPath — keep it safe and out of git.\n\n";
    echo "Put this public half into lib/licencekey.php (LICENCE_PUBKEY_DEFAULT):\n\n";
    echo openssl_pkey_get_details($k)['key'];
    exit(0);
}

// ---- Issue -----------------------------------------------------------------
if (!is_file($privPath)) {
    fwrite(STDERR, "No signing key at $privPath.\nRun with --newkeys first, or pass --priv=/path/to/licence-private.pem\n");
    exit(1);
}
$priv = file_get_contents($privPath);

$cust = trim((string)($args['cust'] ?? ''));
if ($cust === '') { fwrite(STDERR, "--cust is required: the customer's legal name.\n"); exit(1); }

$known = ['operations', 'admin', 'sales', 'reporting', 'money', 'hr'];
$mods = array_values(array_filter(array_map('trim', explode(',', (string)($args['mods'] ?? '')))));
foreach ($mods as $m) {
    if (!in_array($m, $known, true)) {
        fwrite(STDERR, "Unknown module '$m'. Known: " . implode(', ', $known) . "\n");
        exit(1);
    }
}
if (!$mods) { fwrite(STDERR, "--mods is required. Example: --mods=sales,money\n"); exit(1); }
// admin is core and always present; naming it changes nothing but confuses the
// invoice, so it is added silently rather than demanded.
if (!in_array('admin', $mods, true)) $mods[] = 'admin';

$tier = [];
foreach (array_filter(explode(',', (string)($args['tier'] ?? ''))) as $pair) {
    [$k, $v] = array_pad(explode(':', $pair, 2), 2, 'pro');
    $k = trim($k); $v = strtolower(trim($v));
    if ($k === '') continue;
    if (!in_array($v, ['starter', 'pro'], true)) { fwrite(STDERR, "Tier must be starter or pro, got '$v'.\n"); exit(1); }
    $tier[$k] = $v;
}

$months = (int)($args['months'] ?? 12);
$exp = !empty($args['exp']) ? (string)$args['exp'] : date('Y-m-d', strtotime("+$months months"));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp)) { fwrite(STDERR, "--exp must be YYYY-MM-DD.\n"); exit(1); }
if (strtotime($exp) < time()) { fwrite(STDERR, "That expiry is in the past — the customer would be read-only on day one.\n"); exit(1); }

$claims = [
    'cust'  => $cust,
    'ref'   => (string)($args['ref'] ?? ''),
    'mods'  => array_values(array_unique($mods)),
    'tier'  => $tier ?: null,
    'seats' => (int)($args['seats'] ?? 0),      // 0 = unlimited
    'exp'   => $exp,
    'grace' => (int)($args['grace'] ?? 14),
    'iss'   => date('Y-m-d'),
];
$claims = array_filter($claims, fn($v) => $v !== null);

$b64 = fn($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
$payload = $b64(json_encode($claims, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
if (!openssl_sign($payload, $sig, $priv, OPENSSL_ALGO_SHA256)) {
    fwrite(STDERR, "Signing failed: " . openssl_error_string() . "\n"); exit(1);
}
$key = $payload . '.' . $b64($sig);

echo "Licence for : {$claims['cust']}\n";
echo "Reference   : " . ($claims['ref'] ?: '—') . "\n";
echo "Modules     : " . implode(', ', $claims['mods']) . "\n";
if ($tier) { echo "Tiers       : "; foreach ($tier as $k => $v) echo "$k=$v "; echo "\n"; }
echo "Seats       : " . ($claims['seats'] > 0 ? $claims['seats'] : 'unlimited') . "\n";
echo "Expires     : {$claims['exp']}  (then {$claims['grace']} days grace, then read-only)\n";
echo "\n--- send this to the customer ---\n$key\n";
