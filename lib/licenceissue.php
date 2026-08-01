<?php
// ============================================================================
//  Vendor licence console — where MGH issues the signed keys
//
//  This is the other half of licencekey.php. That file VERIFIES keys with the
//  public key baked into every install; this one SIGNS them with the private
//  key, which exists only here, on the licence server. A customer's install
//  never has the private key, so it can verify a key but never mint one — which
//  is exactly what stops a self-hosted client from giving themselves seats.
//
//  The private key is read from the LICENCE_PRIVKEY environment variable, or a
//  file "licence-private.pem" in the app folder (gitignored). If neither is
//  present the console explains how to set one up and refuses to sign, so a
//  customer's copy — which has neither — simply cannot become an issuer.
// ============================================================================

function lk_b64e($s) { return rtrim(strtr(base64_encode((string) $s), '+/', '-_'), '='); }

// The private signing key, or '' when this is not the licence server.
function lk_privkey() {
    $e = getenv('LICENCE_PRIVKEY');
    if ($e !== false && trim((string) $e) !== '') return (string) $e;
    $f = __DIR__ . '/../licence-private.pem';
    if (is_file($f)) return (string) @file_get_contents($f);
    return '';
}

// Who may open the console: the Master Admin, on the control site (never inside
// a tenant workspace). The screen itself explains setup when no key is present;
// signing needs the key.
function lk_console_allowed() {
    if (!function_exists('is_master') || !is_master()) return false;
    if (function_exists('current_tenant') && current_tenant() !== '') return false;
    return true;
}
function lk_can_sign() { return lk_console_allowed() && lk_privkey() !== ''; }

// Sign a claims array into a licence key. Returns ['ok'=>true,'key'=>...] or ['err'=>...].
function lk_issue(array $claims) {
    $pem = lk_privkey();
    if ($pem === '') return ['err' => 'There is no signing key on this server.'];
    $pk = @openssl_pkey_get_private($pem);
    if (!$pk) return ['err' => 'The signing key could not be read — check licence-private.pem.'];
    $p64 = lk_b64e(json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $sig = '';
    if (!openssl_sign($p64, $sig, $pk, OPENSSL_ALGO_SHA256)) return ['err' => 'Signing failed.'];
    return ['ok' => true, 'key' => $p64 . '.' . lk_b64e($sig)];
}

function licissue_migrate() {
    static $done = false; if ($done) return; $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS issued_licences (
            id " . (function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT') . ",
            ref VARCHAR(40) DEFAULT '', customer VARCHAR(200) DEFAULT '', install_id VARCHAR(80) DEFAULT '',
            seats INT DEFAULT 0, exp VARCHAR(20) DEFAULT '', grace INT DEFAULT 0, mods TEXT,
            key_text TEXT, by_user VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    } catch (Throwable $e) {}
}

// The most recent key issued for a given install id — used by the licence-server
// endpoint an install pulls from (self-hosted self-service, part two).
function lk_latest_key_for($installId) {
    $installId = trim((string) $installId);
    if ($installId === '') return '';
    try { licissue_migrate();
        return (string) ops_val("SELECT key_text FROM issued_licences WHERE install_id=? ORDER BY id DESC LIMIT 1", [$installId]);
    } catch (Throwable $e) { return ''; }
}

function ops_licence_issue($route, $method) {
    ops_require(lk_console_allowed(),
        'The licence console runs only on the licence server, for the Master Admin.');
    licissue_migrate();

    if ($route === 'issue-licence-new' && $method === 'POST') {
        ops_require(lk_can_sign(), 'No signing key is set up on this server yet.');
        $cust  = substr(trim((string) ($_POST['customer'] ?? '')), 0, 200);
        if ($cust === '') { flash('Name the customer this key is for.', 'error'); redirect('/issue-licence'); }
        $seats = max(0, (int) ($_POST['seats'] ?? 0));                 // 0 = unlimited
        $grace = max(0, min(120, (int) ($_POST['grace'] ?? LICENCE_GRACE_DEFAULT)));
        $install = substr(trim((string) ($_POST['install_id'] ?? '')), 0, 80);
        // Expiry: an explicit date wins; otherwise a number of months from today.
        $exp = trim((string) ($_POST['exp_date'] ?? ''));
        if ($exp === '') {
            $months = max(1, (int) ($_POST['months'] ?? 12));
            $exp = date('Y-m-d', strtotime('+' . $months . ' months'));
        }
        $mods = [];
        foreach (array_keys(PRODUCT_MODULES) as $k) if (!empty($_POST['mods'][$k])) $mods[] = $k;
        if (!$mods) $mods = array_keys(PRODUCT_MODULES);               // default: the whole product
        $ref = 'LIC-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $claims = ['cust' => $cust, 'exp' => $exp, 'seats' => $seats, 'grace' => $grace, 'ref' => $ref, 'mods' => $mods];
        $r = lk_issue($claims);
        if (!empty($r['err'])) { flash($r['err'], 'error'); redirect('/issue-licence'); }
        try {
            db()->prepare("INSERT INTO issued_licences (ref,customer,install_id,seats,exp,grace,mods,key_text,by_user,created_at)
                           VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$ref, $cust, $install, $seats, $exp, $grace, implode(',', $mods), $r['key'],
                           user_name(current_user()), date('c')]);
        } catch (Throwable $e) {}
        view('ops/licence_issued', ['key' => $r['key'], 'claims' => $claims, 'ref' => $ref, 'install' => $install]);
        return;
    }

    view('ops/licence_issue', [
        'can_sign' => lk_can_sign(),
        'modules'  => PRODUCT_MODULES,
        'history'  => ops_all("SELECT * FROM issued_licences ORDER BY id DESC LIMIT 50"),
    ]);
}
