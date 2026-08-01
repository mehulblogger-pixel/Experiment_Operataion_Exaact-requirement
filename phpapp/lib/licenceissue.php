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

// One-click signing setup — no terminal, no OpenSSL commands. Generates the key
// pair in PHP, saves the private half as licence-private.pem (kept on this
// server, gitignored), and stores the public half in settings so this install
// signs and verifies with it straight away. Returns ['ok'=>true,'pub'=>...] or
// ['err'=>...]. Refuses to overwrite an existing key.
function lk_setup_signing() {
    if (lk_privkey() !== '') return ['err' => 'A signing key is already set up on this server.'];
    if (!function_exists('openssl_pkey_new')) return ['err' => 'This server does not have OpenSSL in PHP, so the key must be made by hand.'];
    $res = @openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    if (!$res) return ['err' => 'The server could not generate a key (its OpenSSL lacks the prime256v1 curve). Make the key by hand instead.'];
    $priv = '';
    if (!@openssl_pkey_export($res, $priv) || $priv === '') return ['err' => 'The private key could not be exported.'];
    $det = @openssl_pkey_get_details($res);
    $pub = (string)($det['key'] ?? '');
    if ($pub === '') return ['err' => 'The public key could not be read.'];
    $file = __DIR__ . '/../licence-private.pem';
    if (@file_put_contents($file, $priv) === false)
        return ['err' => 'Could not save the key file — the app folder is not writable by the web server. '
                       . 'In cPanel → File Manager, give this folder write permission and try again.'];
    @chmod($file, 0600);
    setting_set('licence_pubkey', $pub);
    if (function_exists('act_log')) try { act_log('SYSTEM', 0, 'SYSTEM', 'Licence signing key generated', ['auto' => 1]); } catch (Throwable $e) {}
    return ['ok' => true, 'pub' => $pub];
}

// The provider's public key — the one thing a customer's copy needs so it can
// verify the keys you sign for it. Shown on the console, pasted on a customer
// install's licence screen (or shipped via LICENCE_PUBKEY).
function lk_current_pubkey() { return function_exists('lk_pubkey') ? lk_pubkey() : ''; }

// The Super Admin hub — one place for everything the VENDOR runs: workspaces,
// licences, pricing/payment, and signing. Master Admin, control site only.
function ops_vendor($route, $method) {
    ops_require(lk_console_allowed(), 'The Super Admin panel is for the Master Admin, on the main site.');
    if ($route === 'signing-setup' && $method === 'POST') {
        $r = lk_setup_signing();
        flash(!empty($r['ok']) ? 'Signing is set up. You can now issue licences.' : ($r['err'] ?? 'Could not set up signing.'),
              !empty($r['ok']) ? 'success' : 'error');
        redirect('/vendor');
    }
    view('ops/vendor', [
        'can_sign'  => lk_can_sign(),
        'has_key'   => lk_privkey() !== '',
        'pubkey'    => lk_can_sign() ? lk_current_pubkey() : '',
        'saas'      => function_exists('saas_enabled') && saas_enabled(),
        'billing'   => function_exists('billing_configured') && billing_configured(),
        'n_tenants' => (function_exists('tenant_registry') ? count((array)(tenant_registry()['tenants'] ?? [])) : 0),
        'n_issued'  => (int)(function_exists('ops_val') ? ops_val("SELECT COUNT(*) FROM issued_licences") : 0),
    ]);
}

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

// ---- Self-hosted self-service: the public /buy checkout on the licence server -
// An external customer (no login here — identified by their install id) pays for
// seats through Razorpay on THIS server, and on a verified payment we auto-issue
// a signed key for that install id. Their install then pulls it via api.php.
// Standalone pages, because the caller has no account on this site.
function ops_buy($route, $method) {
    licissue_migrate();
    $install = trim((string) ($_GET['install'] ?? $_POST['install'] ?? ''));
    $rec = $install !== '' ? ops_one("SELECT * FROM issued_licences WHERE install_id=? ORDER BY id DESC LIMIT 1", [$install]) : null;
    if (!$rec) { buy_page_shell('This licence link is not recognised', '<p>We could not find a workspace for this link. Check the address, or contact your provider.</p>', 404); }
    // Public flow: the caller is an external customer with no login here, so this
    // gates on the SERVER being able to sign (private key present) and payment
    // being configured — not on the caller being an admin.
    if (!function_exists('billing_configured') || !billing_configured() || lk_privkey() === '') {
        buy_page_shell('Online renewal is not available', '<p>This provider has not switched on online payment yet. Please pay them directly.</p>', 200);
    }

    if ($route === 'buy-verify' && $method === 'POST') {
        $orderId = (string) ($_POST['razorpay_order_id'] ?? '');
        $payId   = (string) ($_POST['razorpay_payment_id'] ?? '');
        $sig     = (string) ($_POST['razorpay_signature'] ?? '');
        $seats   = max(1, (int) ($_POST['seats'] ?? 0));
        $period  = ($_POST['period'] ?? 'month') === 'year' ? 'year' : 'month';
        if (!rzp_verify_signature($orderId, $payId, $sig))
            buy_page_shell('Payment not verified', '<p>That payment could not be verified. If money was taken it is refunded automatically, and nothing changed.</p>', 400);
        // Extend from the later of today and the current expiry, so paying early adds time.
        $from = ((string) $rec['exp'] >= date('Y-m-d')) ? (string) $rec['exp'] : date('Y-m-d');
        $exp  = date('Y-m-d', strtotime($from . ($period === 'year' ? ' +1 year' : ' +1 month')));
        $mods = array_filter(array_map('trim', explode(',', (string) $rec['mods']))) ?: array_keys(PRODUCT_MODULES);
        $ref  = 'LIC-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $claims = ['cust' => (string) $rec['customer'], 'exp' => $exp, 'seats' => $seats,
                   'grace' => (int) $rec['grace'] ?: LICENCE_GRACE_DEFAULT, 'ref' => $ref, 'mods' => $mods];
        $r = lk_issue($claims);
        if (empty($r['ok'])) buy_page_shell('Could not issue the licence', '<p>' . e($r['err'] ?? 'error') . ' Your payment succeeded — contact your provider and quote ' . e($payId) . '.</p>', 500);
        try { db()->prepare("INSERT INTO issued_licences (ref,customer,install_id,seats,exp,grace,mods,key_text,by_user,created_at)
                             VALUES (?,?,?,?,?,?,?,?,?,?)")
              ->execute([$ref, (string) $rec['customer'], $install, $seats, $exp, $claims['grace'], implode(',', $mods), $r['key'], 'self-service', date('c')]); } catch (Throwable $e) {}
        buy_page_shell('Payment received — you are all set',
            '<p>Your plan now covers <b>' . (int) $seats . '</b> ' . ((int)$seats === 1 ? 'person' : 'people') . ', paid to <b>' . e(fdate($exp)) . '</b>.</p>'
            . '<p>Go back to your own app and open <b>Licence → “Just paid? Check now”</b> — the new users switch on straight away.</p>', 200);
    }

    if ($route === 'buy' && $method === 'POST') {
        $a = billing_amount((int) ($_POST['seats'] ?? 1), (string) ($_POST['period'] ?? 'month'));
        if ($a['paise'] <= 0) buy_page_shell('No price set', '<p>This provider has not set a price yet.</p>', 200);
        $ord = rzp_create_order($a['paise'], 'buy-' . substr($install, 0, 20) . '-' . date('ymdHis'), ['install' => $install, 'seats' => $a['seats'], 'period' => $a['period']]);
        if (empty($ord['ok'])) buy_page_shell('Could not start payment', '<p>' . e($ord['error'] ?? '') . '</p>', 500);
        buy_pay_shell($a, $ord, billing_config(), $install);
    }

    // GET /buy — the choose-and-pay form.
    buy_form_shell($rec, $install, billing_config());
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

// A minimal standalone page for the public checkout (the caller has no account
// here, so the app's authed layout must not be used). Always exits.
function buy_shell_open($title) {
    if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
    $brand = function_exists('app_name') ? app_name() : 'Subscription';
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . htmlspecialchars($title) . '</title>';
    echo '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:560px;margin:44px auto;padding:26px;border:1px solid #e2ddd6;border-radius:12px;color:#333">';
    echo '<div style="font-weight:700;color:#1e40af;margin-bottom:6px">' . htmlspecialchars($brand) . '</div>';
    echo '<h2 style="margin:0 0 12px">' . htmlspecialchars($title) . '</h2>';
}
function buy_shell_close() { echo '</div>'; exit; }

function buy_page_shell($title, $bodyHtml, $status = 200) {
    if (!headers_sent()) http_response_code($status);
    buy_shell_open($title);
    echo '<div style="color:#444;font-size:15px;line-height:1.6">' . $bodyHtml . '</div>';
    buy_shell_close();
}

function buy_form_shell($rec, $install, $cfg) {
    $sym = ($cfg['currency'] ?? 'INR') === 'INR' ? '₹' : (($cfg['currency'] ?? '') . ' ');
    $pm = (int) ($cfg['price_month'] ?? 0); $py = (int) ($cfg['price_year'] ?? 0);
    $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
    $csrf = function_exists('csrf_token') ? csrf_token() : '';
    buy_shell_open('Add or renew users');
    echo '<p style="color:#555;margin:0 0 14px">For <b>' . $e($rec['customer']) . '</b>. Your licence currently runs to '
       . $e(function_exists('fdate') ? fdate((string) $rec['exp']) : $rec['exp']) . '.</p>';
    $inp = 'width:100%;padding:9px 10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;box-sizing:border-box';
    $lab = 'display:block;font-size:12px;color:#555;font-weight:600;margin:12px 0 4px';
    echo '<form method="post" action="/buy">';
    echo '<input type="hidden" name="_csrf" value="' . $e($csrf) . '"><input type="hidden" name="install" value="' . $e($install) . '">';
    echo '<label style="' . $lab . '">How many people</label><input style="' . $inp . '" type="number" id="seats" name="seats" min="1" value="' . (int) ($rec['seats'] ?: 1) . '" oninput="c()">';
    echo '<label style="' . $lab . '">Billing</label><select style="' . $inp . '" id="period" name="period" onchange="c()">';
    if ($pm > 0) echo '<option value="month">Monthly — ' . $e($sym) . number_format($pm) . ' / person / month</option>';
    if ($py > 0) echo '<option value="year">Annual — ' . $e($sym) . number_format($py) . ' / person / year</option>';
    echo '</select>';
    echo '<p style="margin:14px 0 4px">Total: <b id="t" style="font-size:18px"></b></p>';
    echo '<button type="submit" style="margin-top:10px;width:100%;padding:11px;background:#1e40af;color:#fff;border:0;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer">Pay with Razorpay →</button>';
    echo '</form>';
    echo '<script>var PM=' . $pm . ',PY=' . $py . ',S=' . json_encode($sym) . ';function c(){var s=Math.max(1,parseInt(document.getElementById("seats").value||"1",10));var p=document.getElementById("period").value;var t=s*(p==="year"?PY:PM);document.getElementById("t").textContent=S+t.toLocaleString();}c();</script>';
    buy_shell_close();
}

function buy_pay_shell($amt, $order, $cfg, $install) {
    $sym = ($amt['currency'] ?? 'INR') === 'INR' ? '₹' : (($amt['currency'] ?? '') . ' ');
    $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
    $csrf = function_exists('csrf_token') ? csrf_token() : '';
    buy_shell_open('Complete your payment');
    echo '<p style="color:#555">' . (int) $amt['seats'] . ' ' . ((int)$amt['seats'] === 1 ? 'person' : 'people') . ', ' . $e($amt['period'] === 'year' ? 'annual' : 'monthly')
       . ' — <b>' . $e($sym . number_format((int) $amt['major'])) . '</b>. The Razorpay window should open automatically.</p>';
    echo '<button id="paybtn" type="button" style="padding:10px 16px;background:#1e40af;color:#fff;border:0;border-radius:8px;font-weight:600;cursor:pointer">Pay ' . $e($sym . number_format((int) $amt['major'])) . '</button>';
    echo '<form method="post" action="/buy-verify" id="vf" style="display:none">';
    echo '<input type="hidden" name="_csrf" value="' . $e($csrf) . '"><input type="hidden" name="install" value="' . $e($install) . '">';
    echo '<input type="hidden" name="seats" value="' . (int) $amt['seats'] . '"><input type="hidden" name="period" value="' . $e($amt['period']) . '">';
    echo '<input type="hidden" name="razorpay_order_id" id="fo"><input type="hidden" name="razorpay_payment_id" id="fp"><input type="hidden" name="razorpay_signature" id="fs"></form>';
    echo '<script src="https://checkout.razorpay.com/v1/checkout.js"></script>';
    echo '<script>var o={key:' . json_encode($cfg['key_id'] ?? '') . ',amount:' . (int) $order['amount'] . ',currency:' . json_encode($amt['currency']) . ',name:' . json_encode(function_exists('app_name') ? app_name() : 'Subscription') . ',order_id:' . json_encode($order['id'] ?? '')
       . ',handler:function(r){document.getElementById("fo").value=r.razorpay_order_id;document.getElementById("fp").value=r.razorpay_payment_id;document.getElementById("fs").value=r.razorpay_signature;document.getElementById("vf").submit();},theme:{color:"#1e40af"}};'
       . 'function op(){try{(new Razorpay(o)).open()}catch(e){alert("Could not open payment. Reload.")}}document.getElementById("paybtn").addEventListener("click",op);window.addEventListener("load",function(){setTimeout(op,300)});</script>';
    buy_shell_close();
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
