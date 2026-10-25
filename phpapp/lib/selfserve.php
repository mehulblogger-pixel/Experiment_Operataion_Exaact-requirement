<?php
// ============================================================================
//  SELF-SERVE COMPANIES — sign up, try, choose a plan, pay, keep going
//
//  The owner's decisions of 22 Oct 2026, in their words where it matters:
//
//    • A company can buy the modules on its own, pay directly, and start using
//      the system without waiting for anybody.
//    • A 30-day free trial of the FULL application, no card needed, with a
//      limit on storage.
//    • "After trial period lock out with notice to upgrade plan but they shall
//      be able to access the data with view only permission."
//    • "Our superadmin panel must show when last data was accessed so that if
//      any company with free trial does not access its login for 90 days the
//      data shall be deleted and email notification for every 15 days shall be
//      provided in email to the company."
//
//  HOW IT IS BUILT
//
//  Two places hold facts, and each fact has one home:
//
//    CONTROL database (the platform owner's install, table saas_tenants) —
//      who the company is, that it signed itself up (source = 'self'), when
//      its trial ends, when it was last used, how many inactivity warnings it
//      has had. The nightly run (cron.php) reads each company and keeps this
//      up to date; the Companies console shows it.
//
//    The company's OWN database (settings) — what decides, on every request,
//      whether the company may save: saas_billing_mode = 'self',
//      saas_trial_until, billing_paid_until, saas_storage_limit_mb. A request
//      inside a company never has to reach back to the control database.
//
//  Companies created by hand on the Companies console are NOT self-serve and
//  are unaffected by every rule here — their dates are managed by the platform
//  owner exactly as before. Nothing an existing customer has today changes.
//
//  Numbers the owner has not set are settings, never guesses: storage limit
//  and trial logins are blank (= no limit) until the platform owner fills
//  them in on the Companies console.
// ============================================================================

// The owner's numbers (22 Oct 2026). Kept as the defaults of settings so the
// platform owner can change them on the console without a code change.
const SELFSERVE_TRIAL_DAYS_DEFAULT    = 30;
const SELFSERVE_NOTICE_DAYS_DEFAULT   = 15;
const SELFSERVE_DELETE_DAYS_DEFAULT   = 90;

// Technical safety limits on the public form — not commercial policy.
const SELFSERVE_CODE_TTL_MIN      = 30;   // a sign-up code is good for 30 minutes
const SELFSERVE_CODE_TRIES        = 5;    // wrong guesses before the code is void
const SELFSERVE_STARTS_PER_HOUR   = 5;    // sign-ups started from one connection per hour
const SELFSERVE_SEEN_EVERY_SEC    = 3600; // write "last used" at most once an hour

// ---------------------------------------------------------------------------
//  Schema
// ---------------------------------------------------------------------------
function selfserve_migrate() {
    $pdo = function_exists('db') ? db() : null;
    if (!$pdo) return;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    // Control side: the commercial facts about each self-serve company.
    if (function_exists('ensure_column')) {
        foreach ([
            'source'           => "VARCHAR(20) DEFAULT ''",   // 'self' = signed itself up
            'trial_until'      => "VARCHAR(20) DEFAULT ''",
            'paid_until'       => "VARCHAR(20) DEFAULT ''",
            'last_active_at'   => "VARCHAR(30) DEFAULT ''",
            'inactive_notices' => "INT DEFAULT 0",
            'last_notice_at'   => "VARCHAR(30) DEFAULT ''",
            'owner_phone'      => "VARCHAR(40) DEFAULT ''",
            'storage_extra_gb' => "VARCHAR(20) DEFAULT ''",   // extra GB the platform owner granted
            'storage_used'     => "VARCHAR(20) DEFAULT ''",   // bytes, as last read by the nightly run
            'storage_limit'    => "VARCHAR(20) DEFAULT ''",   // bytes (0 = no limit)
            'storage_notice'   => "INT DEFAULT 0",            // highest warning level e-mailed (80/95/100)
            'converted_at'     => "VARCHAR(30) DEFAULT ''",   // the day its free trial became a paid plan
        ] as $col => $def) {
            try { ensure_column('saas_tenants', $col, $def); } catch (Throwable $e) {}
        }
    }
    try {
        // A sign-up waiting for its e-mail code. The password is stored only as
        // a hash and the code only as a hash.
        $pdo->exec("CREATE TABLE IF NOT EXISTS selfserve_signups (
            id $pk,
            company VARCHAR(150) DEFAULT '', owner_name VARCHAR(120) DEFAULT '',
            email VARCHAR(190) DEFAULT '', phone VARCHAR(40) DEFAULT '',
            pass_hash VARCHAR(255) DEFAULT '', code_hash VARCHAR(255) DEFAULT '',
            code_expires VARCHAR(30) DEFAULT '', tries INT DEFAULT 0,
            status VARCHAR(20) DEFAULT 'CODE_SENT', tenant_key VARCHAR(64) DEFAULT '',
            ip VARCHAR(60) DEFAULT '', created_at VARCHAR(30) DEFAULT '', done_at VARCHAR(30) DEFAULT '')");
        // Every company removed for inactivity, kept for the record.
        $pdo->exec("CREATE TABLE IF NOT EXISTS saas_deletions (
            id $pk,
            tenant_key VARCHAR(64) DEFAULT '', company VARCHAR(200) DEFAULT '',
            owner_email VARCHAR(190) DEFAULT '', reason VARCHAR(255) DEFAULT '',
            last_active_at VARCHAR(30) DEFAULT '', notices INT DEFAULT 0,
            deleted_at VARCHAR(30) DEFAULT '')");
        // A payment that has been started, so its verification can only ever
        // apply what was priced and ordered — never what the browser says.
        $pdo->exec("CREATE TABLE IF NOT EXISTS saas_orders (
            id $pk,
            order_id VARCHAR(80) DEFAULT '', kind VARCHAR(20) DEFAULT '',
            modules VARCHAR(255) DEFAULT '', seats INT DEFAULT 0, period VARCHAR(10) DEFAULT 'month',
            amount INT DEFAULT 0, status VARCHAR(20) DEFAULT 'CREATED', payment_id VARCHAR(80) DEFAULT '',
            quote_json TEXT,
            created_by INT DEFAULT 0, created_at VARCHAR(30) DEFAULT '', paid_at VARCHAR(30) DEFAULT '')");
    } catch (Throwable $e) { /* never block the boot chain */ }
    // GST on the order (lib/commercial.php) and the platform's tax-invoice number.
    if (function_exists('ensure_column')) foreach (['tax_json' => 'TEXT', 'invoice_no' => "VARCHAR(30) DEFAULT ''", 'invoiced_at' => "VARCHAR(30) DEFAULT ''"] as $col => $def) {
        try { ensure_column('saas_orders', $col, $def); } catch (Throwable $e) {}
    }
}

// ---------------------------------------------------------------------------
//  The platform owner's settings (control install)
// ---------------------------------------------------------------------------
function selfserve_enabled() {
    return function_exists('setting_get') && (string) setting_get('selfserve_signup_enabled', '0') === '1';
}
function selfserve_cfg() {
    $g = function ($k, $d) { $v = function_exists('setting_get') ? (string) setting_get($k, '') : ''; return $v === '' ? $d : $v; };
    return [
        'trial_days'   => max(1, (int) $g('saas_trial_days', SELFSERVE_TRIAL_DAYS_DEFAULT)),
        'storage_mb'   => max(0, (int) $g('saas_trial_storage_mb', 0)),   // 0 = not set yet → no limit
        'trial_seats'  => max(0, (int) $g('saas_trial_seats', 0)),        // 0 = not set yet → no limit
        'notice_days'  => max(1, (int) $g('saas_inactive_notice_days', SELFSERVE_NOTICE_DAYS_DEFAULT)),
        'delete_days'  => max(1, (int) $g('saas_inactive_delete_days', SELFSERVE_DELETE_DAYS_DEFAULT)),
        'auto_delete'  => (string) $g('saas_autodelete_enabled', '1') === '1',
    ];
}
// The deletion days must be larger than the warning interval, or an unused
// trial could be deleted before its first warning e-mail. '' when fine.
function selfserve_days_problem(?array $c = null) {
    $c = $c ?: selfserve_cfg();
    if ((int) $c['delete_days'] > (int) $c['notice_days']) return '';
    return 'deletion comes before the first warning with these numbers. Make the deletion days larger than the warning interval.';
}
// Self-serve needs the one-URL cloud set-up to exist (a base domain on the
// routing registry), because that is where a new company's database is routed.
function selfserve_ready() {
    if (function_exists('install_is_licence') && install_is_licence()) return false;
    if (function_exists('current_tenant') && current_tenant() !== '') return false;   // control install only
    $reg = function_exists('tenant_registry') ? tenant_registry() : [];
    return (string) ($reg['base_domain'] ?? '') !== '';
}

// ---------------------------------------------------------------------------
//  Creating a company — ONE function, used by the console and by self-serve,
//  so the two can never build a company differently.
//
//  $o: company, key (optional), owner_name, owner_email, owner_pass (blank =
//  a temporary one), own_password (true = the owner chose it; no forced
//  change), plan, modules (optional explicit list), db (optional route),
//  pending_extra (extra first-boot settings), source, owner_phone.
//  Returns ['ok'=>bool, 'key'=>..., 'error'=>..., 'temp_pass'=>...].
// ---------------------------------------------------------------------------
function saas_company_create(array $o) {
    $company = trim((string) ($o['company'] ?? ''));
    $oemail  = strtolower(trim((string) ($o['owner_email'] ?? '')));
    $oname   = trim((string) ($o['owner_name'] ?? ''));
    $plan    = strtoupper((string) ($o['plan'] ?? 'RECRUITMENT'));
    if ($company === '' || $oemail === '' || !filter_var($oemail, FILTER_VALIDATE_EMAIL))
        return ['ok' => false, 'error' => 'A company name and a valid owner email are required.'];

    $reg = function_exists('tenant_registry') ? tenant_registry() : ['tenants' => []];
    $reserved = function_exists('platform_reserved_keys') ? platform_reserved_keys() : [];
    $taken = function ($k) use ($reg, $reserved) { return in_array($k, $reserved, true) || saas_tenant_get($k) || isset($reg['tenants'][$k]); };
    $nkey = saas_slug($o['key'] ?? '');
    if ($nkey !== '') {
        if (function_exists('tenant_valid_sub') && !tenant_valid_sub($nkey))
            return ['ok' => false, 'error' => 'The workspace key must be lowercase letters, digits or hyphens.'];
        if ($taken($nkey)) return ['ok' => false, 'error' => 'That workspace key is already taken — pick another.'];
    } else {
        // Made from the company name; a clash gets a number, never an error —
        // a person signing up should not have to invent a technical name.
        $base = substr(saas_slug($company) ?: 'company', 0, 36);
        $nkey = $base;
        for ($i = 2; $taken($nkey) && $i < 500; $i++) $nkey = $base . '-' . $i;
        if ($taken($nkey)) return ['ok' => false, 'error' => 'Could not find a free workspace name — please try again.'];
    }
    if (saas_login_lookup($oemail) !== '') return ['ok' => false, 'error' => 'That owner email already belongs to a company.'];

    $opass = (string) ($o['owner_pass'] ?? '');
    $temp  = '';
    if ($opass === '') { $opass = bin2hex(random_bytes(4)); $temp = $opass; }

    $db = $o['db'] ?? null;
    if (!is_array($db) || !$db) {
        [$db] = function_exists('tenant_auto_storage')
            ? tenant_auto_storage($nkey)
            : [['sqlite' => (function_exists('tenant_default_sqlite_path') ? tenant_default_sqlite_path($nkey)[0] : dirname(__DIR__) . '/tenant-' . $nkey . '.sqlite')]];
    }
    $err = function_exists('tenant_add') ? tenant_add($nkey, $company, $db) : 'Cloud mode is not enabled.';
    if ($err !== '') return ['ok' => false, 'error' => $err];

    saas_tenant_upsert($nkey, ['company' => $company, 'owner_name' => $oname, 'owner_email' => $oemail, 'plan' => $plan, 'status' => 'active']);
    saas_tenant_upsert($nkey, ['route_json' => json_encode($db)]);
    saas_tenant_set_plan($nkey, $plan);
    $mods = isset($o['modules']) && is_array($o['modules']) ? array_values($o['modules']) : null;
    if ($mods !== null) saas_tenant_set_modules($nkey, $mods);
    $extraCols = [];
    foreach (['source', 'trial_until', 'owner_phone'] as $c) if (isset($o[$c])) $extraCols[$c] = (string) $o[$c];
    if ($extraCols) saas_tenant_upsert($nkey, $extraCols);
    saas_login_index_set($oemail, $nkey);

    $pending = [
        'owner_email' => $oemail,
        'owner_name'  => $oname,
        'pass_hash'   => password_hash($opass, PASSWORD_DEFAULT),
        'own_password'=> !empty($o['own_password']),
        'plan'        => $plan,
        'seat_limit'  => (int) saas_tenant_seat_limit($nkey),
        'app_name'    => $company,
    ];
    if ($mods !== null) $pending['modules'] = $mods;
    // The platform owner's storage allowance, so the company has it from its
    // first minute rather than from the next nightly run. (Numbers only — no
    // secret ever goes into the first-boot record.)
    if (function_exists('selfserve_storage_settings')) $pending['storage'] = selfserve_storage_settings();
    foreach ((array) ($o['pending_extra'] ?? []) as $k => $v) $pending[$k] = $v;
    saas_tenant_upsert($nkey, ['pending_json' => json_encode($pending)]);
    // The owner's first sign-in lives on the routing entry; without it the
    // company can never be opened. Say so now rather than leave a company that
    // answers "still being set up" for ever.
    $perr = function_exists('tenant_pending_set') ? tenant_pending_set($nkey, $pending) : 'Cloud mode is not enabled.';
    if ($perr !== '' || !is_array(tenant_pending($nkey)))
        return ['ok' => false, 'key' => $nkey, 'error' => 'The company was registered but its owner could not be set up (' . ($perr ?: 'the routing file did not keep it')
            . '). Try again in a moment, or remove it on the Companies screen.', 'temp_pass' => ''];
    return ['ok' => true, 'key' => $nkey, 'error' => '', 'temp_pass' => $temp];
}

// Every non-core module — what a trial company gets.
function selfserve_all_modules() {
    $out = [];
    if (defined('PRODUCT_MODULES')) foreach (PRODUCT_MODULES as $k => $m) if (empty($m[3])) $out[] = $k;
    return $out;
}

// ---------------------------------------------------------------------------
//  Public sign-up (control install). Step 1: details → e-mail code.
//  Returns [ok, message, signupId].
// ---------------------------------------------------------------------------
function selfserve_start(array $in) {
    selfserve_migrate();
    // A field people never see. Anything typed into it came from a robot; the
    // reply is the same as success so it learns nothing.
    if (trim((string) ($in['website'] ?? '')) !== '') return [true, '', 0];

    $company = trim((string) ($in['company'] ?? ''));
    $name    = trim((string) ($in['owner_name'] ?? ''));
    $email   = strtolower(trim((string) ($in['email'] ?? '')));
    $phone   = trim((string) ($in['phone'] ?? ''));
    $pass    = (string) ($in['password'] ?? '');

    if ($company === '')                            return [false, 'Please enter your company name.', 0];
    if ($name === '')                               return [false, 'Please enter your name.', 0];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [false, 'Enter a valid work e-mail address.', 0];
    if (mb_strlen($company) > 150 || mb_strlen($name) > 120) return [false, 'That name is too long — please shorten it.', 0];
    if ($phone !== '' && !preg_match('/^[0-9+()\-\s]{6,20}$/', $phone)) return [false, 'Enter a valid phone number, or leave it empty.', 0];
    if (function_exists('password_problem_text')) {
        $why = password_problem_text($pass, $email, $name);
        if ($why !== '') return [false, $why, 0];
    } elseif (strlen($pass) < 8) return [false, 'Choose a password of at least 8 characters.', 0];
    if (empty($in['agree']))                        return [false, 'Please tick that you agree to the trial terms.', 0];
    if (saas_login_lookup($email) !== '')           return [false, 'That e-mail already has a workspace — sign in instead, or use "Forgot password?".', 0];

    $ip = function_exists('client_ip') ? client_ip() : (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $recent = (int) ops_val("SELECT COUNT(*) FROM selfserve_signups WHERE ip=? AND created_at>=?",
        [$ip, date('c', time() - 3600)]);
    if ($recent >= SELFSERVE_STARTS_PER_HOUR) return [false, 'Too many sign-ups from this connection. Please try again in an hour.', 0];

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    db()->prepare("INSERT INTO selfserve_signups (company,owner_name,email,phone,pass_hash,code_hash,code_expires,tries,status,ip,created_at)
                   VALUES (?,?,?,?,?,?,?,0,'CODE_SENT',?,?)")
        ->execute([$company, $name, $email, mb_substr($phone, 0, 40), password_hash($pass, PASSWORD_DEFAULT),
                   password_hash($code, PASSWORD_DEFAULT), date('c', time() + SELFSERVE_CODE_TTL_MIN * 60), $ip, date('c')]);
    $id = (int) db()->lastInsertId();
    selfserve_mail_code($email, $name, $code);
    return [true, 'We have sent a 6-digit code to ' . $email . '.', $id];
}

function selfserve_mail_code($email, $name, $code) {
    $app = function_exists('app_name') ? app_name() : 'Operations';
    $body = "Hello " . ($name ?: 'there') . ",\n\n"
          . "Your code to finish creating your " . $app . " workspace is:\n\n    " . $code . "\n\n"
          . "It works for " . SELFSERVE_CODE_TTL_MIN . " minutes. If you did not ask for this, ignore this e-mail — nothing has been created.\n";
    return function_exists('ops_mail') ? ops_mail($email, $app . ' — your sign-up code', $body, '', 'selfserve_code') : 0;
}

// A new code for the same sign-up (the first one did not arrive, or ran out).
function selfserve_resend($id) {
    $r = ops_one("SELECT * FROM selfserve_signups WHERE id=? AND status='CODE_SENT'", [(int) $id]);
    if (!$r) return [false, 'That sign-up has expired — please start again.'];
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    db()->prepare("UPDATE selfserve_signups SET code_hash=?, code_expires=?, tries=0 WHERE id=?")
        ->execute([password_hash($code, PASSWORD_DEFAULT), date('c', time() + SELFSERVE_CODE_TTL_MIN * 60), (int) $id]);
    selfserve_mail_code($r['email'], $r['owner_name'], $code);
    return [true, 'A new code has been sent to ' . $r['email'] . '.'];
}

// Step 2: the code → the company is created on the spot.
// Returns [ok, message, tenantKey].
function selfserve_verify($id, $code) {
    $r = ops_one("SELECT * FROM selfserve_signups WHERE id=?", [(int) $id]);
    if (!$r || $r['status'] !== 'CODE_SENT') return [false, 'That sign-up has expired — please start again.', ''];
    if ((int) $r['tries'] >= SELFSERVE_CODE_TRIES) return [false, 'Too many wrong codes. Press "Send a new code".', ''];
    if ((string) $r['code_expires'] < date('c')) return [false, 'That code has run out. Press "Send a new code".', ''];
    $code = preg_replace('/\D/', '', (string) $code);
    if (!password_verify($code, (string) $r['code_hash'])) {
        db()->prepare("UPDATE selfserve_signups SET tries=tries+1 WHERE id=?")->execute([(int) $id]);
        $left = SELFSERVE_CODE_TRIES - (int) $r['tries'] - 1;
        return [false, 'That code is not right.' . ($left > 0 ? ' ' . $left . ' tr' . ($left === 1 ? 'y' : 'ies') . ' left.' : ' Press "Send a new code".'), ''];
    }
    // Claim the row first, so a double-click can never build two companies.
    $st = db()->prepare("UPDATE selfserve_signups SET status='CREATING' WHERE id=? AND status='CODE_SENT'");
    $st->execute([(int) $id]);
    if ($st->rowCount() !== 1) return [false, 'That sign-up is already being created — sign in with your e-mail.', ''];
    if (saas_login_lookup($r['email']) !== '') {
        db()->prepare("UPDATE selfserve_signups SET status='DUPLICATE' WHERE id=?")->execute([(int) $id]);
        return [false, 'That e-mail already has a workspace — sign in instead.', ''];
    }

    $cfg   = selfserve_cfg();
    $until = date('Y-m-d', strtotime('+' . $cfg['trial_days'] . ' days'));
    $res = saas_company_create([
        'company' => $r['company'], 'owner_name' => $r['owner_name'], 'owner_email' => $r['email'],
        'owner_phone' => $r['phone'], 'own_password' => true, 'owner_pass' => '',
        'plan' => 'TRIAL', 'modules' => array_merge(['admin'], selfserve_all_modules()),
        'source' => 'self', 'trial_until' => $until,
        'pending_extra' => ['billing_mode' => 'self', 'trial_until' => $until, 'storage_mb' => $cfg['storage_mb'],
                            'seat_limit' => $cfg['trial_seats']],
    ]);
    if (!$res['ok']) {
        db()->prepare("UPDATE selfserve_signups SET status='CODE_SENT' WHERE id=?")->execute([(int) $id]);
        return [false, $res['error'], ''];
    }
    // The owner keeps the password they chose — swap the temporary hash for theirs.
    $key = $res['key'];
    $p = function_exists('tenant_pending') ? tenant_pending($key) : null;
    if (is_array($p)) {
        $p['pass_hash'] = (string) $r['pass_hash'];
        if (function_exists('tenant_pending_set')) tenant_pending_set($key, $p);
        saas_tenant_upsert($key, ['pending_json' => json_encode($p)]);
    }
    db()->prepare("UPDATE selfserve_signups SET status='DONE', tenant_key=?, done_at=?, pass_hash='', code_hash='' WHERE id=?")
        ->execute([$key, date('c'), (int) $id]);
    selfserve_mail_welcome($r['email'], $r['owner_name'], $r['company'], $until);
    return [true, '', $key];
}

function selfserve_mail_welcome($email, $name, $company, $until) {
    $app = function_exists('app_name') ? app_name() : 'Operations';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $url  = $host !== '' ? 'https://' . $host . '/login' : '/login';
    $cfg  = selfserve_cfg();
    $body = "Hello " . ($name ?: 'there') . ",\n\n"
          . "Your " . $app . " workspace for " . $company . " is ready.\n\n"
          . "Sign in at: " . $url . "\n"
          . "Your username: " . $email . " (with the password you chose)\n\n"
          . "Your free trial of every module runs until " . (function_exists('fdate') ? fdate($until) : $until) . ".\n"
          . "Before then, choose your plan under Admin → Your subscription. If the trial ends without a plan, "
          . "your workspace becomes view-only: you can still open and export everything, but not add anything new.\n\n"
          . "A trial workspace that nobody signs in to for " . $cfg['delete_days'] . " days is deleted with all its data. "
          . "We e-mail you every " . $cfg['notice_days'] . " days before that happens.\n";
    return function_exists('ops_mail') ? ops_mail($email, 'Your ' . $app . ' workspace is ready', $body, '', 'selfserve_welcome') : 0;
}

// A company created by the platform owner: tell its owner how to sign in.
// The password is a temporary one that must be changed at the first sign-in.
function saas_mail_new_owner($email, $name, $company, $tempPass) {
    $app  = function_exists('app_name') ? app_name() : 'Operations';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $url  = $host !== '' ? 'https://' . $host . '/login' : '/login';
    $body = "Hello " . ($name ?: 'there') . ",\n\n"
          . "A " . $app . " workspace has been created for " . $company . ".\n\n"
          . "Sign in at: " . $url . "\n"
          . "Your username: " . $email . "\n"
          . "Temporary password: " . $tempPass . "\n\n"
          . "You will be asked to choose your own password the first time you sign in.\n";
    return function_exists('ops_mail') ? ops_mail($email, 'Your ' . $app . ' workspace is ready', $body, '', 'saas_new_owner') : 0;
}

// Sign the new owner straight in (they have just proved the e-mail is theirs).
// Returns true when they are in.
function selfserve_sign_in_owner($key) {
    // Read on the control install, written into the new company below (the
    // nightly run repeats it, so a failure here is healed within a day). Kept
    // out of the first-boot record so the payment secret is stored nowhere new.
    $sell = selfserve_sell_settings();
    if (!function_exists('saas_enter_tenant') || !saas_enter_tenant($key)) return false;
    if (function_exists('saas_tenant_ensure_ready') && !saas_tenant_ensure_ready($key)) { saas_leave_tenant(); return false; }
    foreach ($sell as $sk => $sv) { try { setting_set($sk, $sv); } catch (Throwable $e) {} }
    $u = null;
    try { $u = ops_one("SELECT * FROM users WHERE is_superuser=1 AND is_active=1 ORDER BY id LIMIT 1"); } catch (Throwable $e) {}
    if (!$u) { saas_leave_tenant(); return false; }
    if (function_exists('complete_login')) complete_login($u);
    return true;
}

// The public page: /start. Always exits.
function selfserve_route($method) {
    if (!selfserve_ready() || !selfserve_enabled()) redirect('/get-started');
    selfserve_migrate();
    $step = 'form'; $err = ''; $msg = '';
    $sid  = (int) ($_SESSION['ss_signup'] ?? 0);
    $csrfOk = ($method !== 'POST') || (function_exists('csrf_ok') ? csrf_ok($_POST['_csrf'] ?? '') : true);
    if ($method === 'POST' && !$csrfOk) {
        $err = 'Your page had been open too long. Please press the button again.';
    } elseif ($method === 'POST') {
        $do = (string) ($_POST['do'] ?? 'start');
        if ($do === 'start') {
            [$ok, $m, $id] = selfserve_start($_POST);
            if ($ok && $id > 0) { $_SESSION['ss_signup'] = $id; $sid = $id; $step = 'code'; $msg = $m; }
            elseif ($ok) { $step = 'code'; $msg = 'We have sent a 6-digit code to your e-mail.'; }
            else $err = $m;
        } elseif ($do === 'verify' && $sid) {
            [$ok, $m, $key] = selfserve_verify($sid, $_POST['code'] ?? '');
            if ($ok) {
                unset($_SESSION['ss_signup']);
                if (selfserve_sign_in_owner($key)) {
                    flash('Welcome! Your workspace is ready and your free trial has started.', 'success');
                    redirect('/');
                }
                flash('Your workspace is ready. Sign in with your e-mail and the password you chose.', 'success');
                redirect('/login');
            }
            $step = 'code'; $err = $m;
        } elseif ($do === 'resend' && $sid) {
            [$ok, $m] = selfserve_resend($sid);
            $step = 'code'; if ($ok) $msg = $m; else { $err = $m; $step = 'form'; unset($_SESSION['ss_signup']); }
        } elseif ($do === 'restart') {
            unset($_SESSION['ss_signup']); $step = 'form';
        }
    } elseif ($sid && ops_one("SELECT id FROM selfserve_signups WHERE id=? AND status='CODE_SENT'", [$sid])) {
        $step = 'code';
    }
    $GLOBALS['__ss'] = ['step' => $step, 'err' => $err, 'msg' => $msg, 'post' => $_POST, 'cfg' => selfserve_cfg(),
                        'csrf' => function_exists('csrf_token') ? csrf_token() : ''];
    require __DIR__ . '/../views/public/start.php';
    exit;
}

// ===========================================================================
//  INSIDE A COMPANY — what it may do today
// ===========================================================================

// Is this workspace one that signed itself up (and so follows these rules)?
function saas_is_selfserve() {
    return function_exists('setting_get') && (string) setting_get('saas_billing_mode', '') === 'self';
}

// 'none' (not self-serve — unaffected), 'trial', 'paid' or 'locked'.
// $today is for tests.
function saas_access_state($today = null) {
    $today = $today ?: date('Y-m-d');
    $out = ['mode' => 'none', 'until' => '', 'days_left' => null];
    if (!saas_is_selfserve()) return $out;
    try { saas_apply_due_change($today); } catch (Throwable $e) {}
    $paid  = (string) setting_get('billing_paid_until', '');
    $trial = (string) setting_get('saas_trial_until', '');
    $days = function ($d) use ($today) { return (int) floor((strtotime($d) - strtotime($today)) / 86400); };
    if ($paid !== '' && $paid >= $today)  return ['mode' => 'paid',  'until' => $paid,  'days_left' => $days($paid)];
    if ($trial !== '' && $trial >= $today) return ['mode' => 'trial', 'until' => $trial, 'days_left' => $days($trial)];
    return ['mode' => 'locked', 'until' => $paid !== '' ? $paid : $trial, 'days_left' => 0,
            'was' => $paid !== '' ? 'paid' : 'trial'];
}

// The routes a view-only company can still post to: the ones that let it pay,
// sign out, or keep its own account safe. Everything else that saves is refused.
const SAAS_LOCKED_ALLOW = ['subscription', 'subscription-order', 'subscription-verify',
    'logout', 'login', 'change-password', 'two-factor', 'my-signature', 'verify', 'verify-pdf'];

function saas_blocks_write($route) {
    if (saas_access_state()['mode'] !== 'locked') return false;
    return !in_array($route, SAAS_LOCKED_ALLOW, true);
}

// Storage: every file is kept inside the company's own database, so the size
// of that database IS its storage. Measured at most every ten minutes.
function saas_storage_used_bytes($fresh = false) {
    if (!$fresh) {
        $c = (string) setting_get('saas_storage_cache', '');
        if ($c !== '') { [$at, $n] = array_pad(explode('|', $c, 2), 2, '0'); if ((int) $at > time() - 600) return (int) $n; }
    }
    $n = 0;
    try {
        $cfg = require dirname(__DIR__) . '/config.php';
        if (($cfg['db']['driver'] ?? '') === 'sqlite') {
            $p = (string) ($cfg['sqlite_path'] ?? '');
            $n = ($p !== '' && is_file($p)) ? (int) filesize($p) : 0;
        } else {
            $n = (int) ops_val("SELECT COALESCE(SUM(data_length+index_length),0) FROM information_schema.tables WHERE table_schema = DATABASE()");
        }
    } catch (Throwable $e) { $n = 0; }
    try { setting_set('saas_storage_cache', time() . '|' . $n); } catch (Throwable $e) {}
    return $n;
}
// ---------------------------------------------------------------------------
//  STORAGE ALLOWANCE — every hosted company (owner, 22 Oct 2026: "integrate
//  storage usage and limits on the paid users also")
//
//  Trial:  the trial limit (MB) the platform owner set.
//  Otherwise, an allowance in GB made of four parts, each the platform owner's
//  number (blank = 0):
//      base GB for any company on a plan
//    + GB per login the company has
//    + extra storage packs the company bought × the pack size
//    + extra GB the platform owner granted this company (a negotiated deal)
//  When all of it adds up to 0 the platform owner has not set storage at all,
//  and there is no limit — nothing is invented.
//
//  At the limit, NEW FILES are refused; every record still saves, every file
//  already stored stays, nothing is ever deleted for being over.
//  A self-hosted (licence) install is never limited by any of this.
// ---------------------------------------------------------------------------
const STORAGE_WARN_LEVELS = [80, 95, 100];   // % at which the owner is told (technical, not a price)

function saas_storage_cfg() {
    $n = fn($k) => max(0, (float) setting_get($k, '0'));
    return ['base_gb' => $n('saas_storage_gb_base'), 'per_login_gb' => $n('saas_storage_gb_per_login'),
            'pack_gb' => $n('saas_storage_pack_gb'), 'packs' => (int) $n('saas_storage_packs'),
            'extra_gb' => $n('saas_storage_extra_gb')];
}
function saas_storage_limit_bytes() {
    if (!function_exists('current_tenant') || current_tenant() === '') return 0;   // control / self-hosted: never limited
    $st = saas_access_state();
    if ($st['mode'] === 'trial') {
        $mb = (int) setting_get('saas_storage_limit_mb', 0);
        return $mb > 0 ? $mb * 1048576 : 0;
    }
    $c = saas_storage_cfg();
    $logins = max(0, (int) setting_get('saas_seat_limit', 0));
    $gb = $c['base_gb'] + $c['per_login_gb'] * $logins + $c['pack_gb'] * $c['packs'] + $c['extra_gb'];
    return $gb > 0 ? (int) round($gb * 1073741824) : 0;
}
// Percent used, or null when there is no limit.
function saas_storage_pct() {
    $lim = saas_storage_limit_bytes();
    return $lim > 0 ? (int) floor(100 * saas_storage_used_bytes() / $lim) : null;
}
// True when the company is at its storage allowance — new files are refused.
function saas_storage_full() {
    $lim = saas_storage_limit_bytes();
    return $lim > 0 && saas_storage_used_bytes() >= $lim;
}
// Where the space goes, largest first: [label => bytes]. Files are kept inside
// the company's own database, so this reads the size of the columns that hold
// them; "Records and everything else" is the rest of the database.
const STORAGE_AREAS = [    // every table that holds files, by area (read from the schema, 22 Oct 2026)
    'Inspection reports & photos' => [['report_files', 'data'], ['report_docs', 'data'], ['endorsement_files', 'data'],
                                      ['job_qaps', 'data'], ['report_templates', 'file_data']],
    'Field visits & vouchers'     => [['site_visits', 'photo_data'], ['vouchers', 'supporting_file'], ['voucher_entries', 'receipt_data'],
                                      ['job_bills', 'file_data']],
    'Sales documents'             => [['lead_files', 'file_data'], ['quote_files', 'file_data'], ['crm_templates', 'file_data'],
                                      ['partner_registrations', 'file_data'], ['client_ndas', 'file_data']],
    'Quality & equipment records' => [['equipment_calibrations', 'file_data'], ['controlled_docs', 'file_data'], ['methods', 'file_data'],
                                      ['confidentiality_undertakings', 'file_data']],
    'People documents'            => [['person_documents', 'file_data'], ['candidate_docs', 'file_data'], ['inspector_certs', 'file_data'],
                                      ['asset_issues', 'ack_file_data']],
    'Marketplace files'           => [['cx_pro_files', 'file_data'], ['cx_engagement_reports', 'file_data'], ['cx_engagement_voucher_files', 'file_data']],
];
function saas_storage_breakdown() {
    $total = saas_storage_used_bytes();
    $out = []; $files = 0;
    foreach (STORAGE_AREAS as $label => $cols) {
        $n = 0;
        foreach ($cols as [$t, $c]) {
            try { $n += (int) ops_val("SELECT COALESCE(SUM(LENGTH($c)),0) FROM $t"); } catch (Throwable $e) {}
        }
        if ($n > 0) { $out[$label] = $n; $files += $n; }
    }
    arsort($out);
    $out['Records and everything else'] = max(0, $total - $files);
    return $out;
}
function saas_bytes_label($n) {
    $n = (float) $n;
    if ($n >= 1073741824) return round($n / 1073741824, 2) . ' GB';
    if ($n >= 1048576)    return round($n / 1048576, 1) . ' MB';
    return max(0, (int) round($n / 1024)) . ' KB';
}

function saas_request_has_files() {
    foreach ((array) ($_FILES ?? []) as $f) {
        $sizes = (array) ($f['size'] ?? []);
        foreach ($sizes as $s) if ((int) $s > 0) return true;
    }
    return false;
}

// "Last used": written at most once an hour while a real person of this
// company is signed in (every hosted company, so the console can show it for
// all of them). The platform owner's "Log in as" never counts.
function saas_touch_activity() {
    if (!function_exists('current_tenant') || current_tenant() === '') return;
    if (!empty($_SESSION['saas_impersonating'])) return;
    if (!function_exists('current_user') || !current_user()) return;
    $last = (string) setting_get('saas_last_seen', '');
    if ($last !== '' && strtotime($last) > time() - SELFSERVE_SEEN_EVERY_SEC) return;
    try { setting_set('saas_last_seen', date('c')); } catch (Throwable $e) {}
}

// The line shown at the top of every screen of a self-serve company.
function saas_access_banner() {
    $st = saas_access_state();
    if ($st['mode'] === 'none') return null;
    $admin = function_exists('billing_can_manage') && billing_can_manage();
    $date = function_exists('fdate') ? fdate($st['until']) : $st['until'];
    if ($st['mode'] === 'locked') {
        return ['tone' => 'error', 'text' => ($st['was'] ?? '') === 'paid'
                ? 'Your plan ended on ' . $date . '. Your workspace is view-only — everything can be opened and exported, but nothing new can be saved.'
                : 'Your free trial ended on ' . $date . '. Your workspace is view-only — everything can be opened and exported, but nothing new can be saved.',
            'link' => $admin ? '/subscription' : '', 'cta' => $admin ? 'Choose your plan' : '',
            'note' => $admin ? '' : 'Ask your workspace owner to choose a plan.'];
    }
    if ($st['mode'] === 'trial') {
        $d = (int) $st['days_left'];
        $out = ['tone' => $d <= 7 ? 'warning' : 'info',
            'text' => 'Free trial — ' . ($d === 0 ? 'last day today' : $d . ' day' . ($d === 1 ? '' : 's') . ' left') . ' (until ' . $date . ').',
            'link' => $admin ? '/subscription' : '', 'cta' => $admin ? 'Choose your plan' : '', 'note' => ''];
        return $out;
    }
    // Paid: only nag in the last 7 days, and only the people who can renew.
    if ($st['mode'] === 'paid' && (int) $st['days_left'] <= 7 && $admin) {
        return ['tone' => 'warning', 'text' => 'Your plan runs until ' . $date . '. Renew to keep saving new work.',
                'link' => '/subscription', 'cta' => 'Renew', 'note' => ''];
    }
    return null;
}

// The storage line, for every hosted company with an allowance: from 80% for
// the people who can buy more, and at 100% for everyone (their uploads stop).
function saas_storage_banner() {
    if (saas_access_state()['mode'] === 'locked') return null;      // the view-only line already says it all
    $pct = saas_storage_pct();
    if ($pct === null || $pct < STORAGE_WARN_LEVELS[0]) return null;
    $admin = function_exists('billing_can_manage') && billing_can_manage();
    $self  = saas_is_selfserve();
    $used  = saas_bytes_label(saas_storage_used_bytes()) . ' of ' . saas_bytes_label(saas_storage_limit_bytes());
    if ($pct >= 100) {
        return ['tone' => 'error', 'text' => 'Storage is full (' . $used . '), so new files cannot be added. Everything already stored is safe, and records without files still save.',
            'link' => $admin ? '/subscription' : '', 'cta' => $admin ? ($self ? 'Add storage' : 'See storage') : '',
            'note' => $admin ? ($self ? '' : 'Ask your provider for more storage.') : 'Ask your workspace owner to add storage.'];
    }
    if (!$admin) return null;
    return ['tone' => $pct >= STORAGE_WARN_LEVELS[1] ? 'warning' : 'info',
        'text' => 'Storage ' . $pct . '% used (' . $used . ').', 'link' => '/subscription',
        'cta' => $self ? 'Add storage' : 'See storage', 'note' => $self ? '' : 'Ask your provider for more storage.'];
}

// ---------------------------------------------------------------------------
//  Choosing a plan (self-serve company, /subscription)
// ---------------------------------------------------------------------------

// Record an order before the payment window opens; the verification applies
// exactly this row, once.
// $amount is what is charged (GST included when GST is on); $tax is the
// breakdown from commercial_tax() plus the buyer's details for the invoice.
function saas_order_create($orderId, $kind, array $mods, $seats, $period, $amount, array $quote = [], array $tax = []) {
    selfserve_migrate();
    db()->prepare("INSERT INTO saas_orders (order_id,kind,modules,seats,period,amount,status,quote_json,tax_json,created_by,created_at)
                   VALUES (?,?,?,?,?,?,'CREATED',?,?,?,?)")
        ->execute([(string) $orderId, (string) $kind, implode(',', $mods), (int) $seats, $period === 'year' ? 'year' : 'month',
                   (int) $amount, $quote ? json_encode($quote) : '', $tax ? json_encode($tax) : '', (int) (current_user()['id'] ?? 0), date('c')]);
}
// Claim a CREATED order for a verified payment. Returns the row or null (unknown
// order, or already applied — a replayed callback changes nothing).
function saas_order_claim($orderId, $paymentId) {
    selfserve_migrate();
    $st = db()->prepare("UPDATE saas_orders SET status='PAID', payment_id=?, paid_at=? WHERE order_id=? AND status='CREATED'");
    $st->execute([(string) $paymentId, date('c'), (string) $orderId]);
    if ($st->rowCount() !== 1) return null;
    // A payment that carried GST gets the platform's next tax-invoice number.
    $tax = json_decode((string) (ops_val("SELECT tax_json FROM saas_orders WHERE order_id=?", [(string) $orderId]) ?: ''), true);
    if (is_array($tax) && !empty($tax['on']) && function_exists('commercial_next_invoice_no')) {
        $no = commercial_next_invoice_no();
        if ($no !== '') {
            db()->prepare("UPDATE saas_orders SET invoice_no=?, invoiced_at=? WHERE order_id=?")->execute([$no, date('Y-m-d'), (string) $orderId]);
            // …and into the platform owner's register, for the GST return.
            $o = ops_one("SELECT * FROM saas_orders WHERE order_id=?", [(string) $orderId]);
            if ($o && function_exists('commercial_register_add'))
                commercial_register_add(commercial_invoice_row($o, $tax, function_exists('current_tenant') ? (string) current_tenant() : ''));
        }
    }
    return ops_one("SELECT * FROM saas_orders WHERE order_id=?", [(string) $orderId]) ?: null;
}
// The buyer, as the invoice names them: this company's own profile.
function saas_invoice_buyer() {
    $g = fn($k) => function_exists('setting_get') ? trim((string) setting_get($k, '')) : '';
    $gstin = function_exists('clean_gstin') ? clean_gstin($g('company_gstin')) : $g('company_gstin');
    $state = function_exists('commercial_state_code') ? commercial_state_code($g('company_state')) : '';
    if ($gstin !== '' && preg_match('/^[0-9]{2}/', $gstin)) $state = substr($gstin, 0, 2);
    return ['name' => $g('company_legal_name') ?: ($g('company_name') ?: (function_exists('current_tenant') ? (string) current_tenant() : '')),
            'address' => $g('company_address'), 'gstin' => $gstin, 'state' => $state];
}
// The "Bill to" typed at checkout (views/ops/_billto.php), checked; saved back
// to the company profile when asked, so it is filled in next time. Returns the
// buyer, or redirects back with the reason (and what was typed kept).
function saas_billto_from_post() {
    if (!isset($_POST['billto_name'])) return saas_invoice_buyer();   // an older form: the profile, as before
    $in = ['name' => $_POST['billto_name'] ?? '', 'gstin' => $_POST['billto_gstin'] ?? '',
           'state' => $_POST['billto_state'] ?? '', 'address' => $_POST['billto_address'] ?? ''];
    $b = commercial_billto_clean($in);
    if ($b['error'] !== '') {
        $_SESSION['billto_draft'] = $in;
        flash($b['error'] . ' Nothing was charged.', 'error');
        redirect('/subscription');
    }
    unset($_SESSION['billto_draft']);
    $buyer = $b['buyer'];
    if (!empty($_POST['billto_save'])) {
        setting_set('company_legal_name', $buyer['name']);
        setting_set('company_address', $buyer['address']);
        if ($buyer['gstin'] !== '') {
            setting_set('company_gstin', $buyer['gstin']);
            if (function_exists('pan_from_gstin')) setting_set('company_pan', pan_from_gstin($buyer['gstin']));
        }
        if ($buyer['state'] !== '' && defined('GST_STATES')) setting_set('company_state', GST_STATES[$buyer['state']] ?? '');
    }
    return $buyer;
}

// ---------------------------------------------------------------------------
//  Changing plan — the owner's rule of 19 Oct 2026 (Q-L), the same one the
//  marketplace plans follow (lib/mkt_billing.php):
//
//    NEW       no paid period running (trial, or view-only) → starts today.
//    UPGRADE   worth more per month than the plan running now → starts today;
//              the unused value of the current plan is taken off the price, and
//              any excess becomes extra days, so nothing paid is lost.
//    RENEW     the same plan again (or the change already booked for the end
//              of this period) → paid now, starts when the current one ends.
//    DOWNGRADE worth the same or less → nothing is charged now; it is booked to
//              start when the current period ends and is paid for then.
// ---------------------------------------------------------------------------

// What the company is paying for right now.
function saas_plan_current() {
    return [
        'mods'   => array_values(array_intersect(selfserve_all_modules(), function_exists('saas_paid_modules') ? saas_paid_modules() : [])),
        'seats'  => (int) setting_get('saas_seat_limit', 0),
        'packs'  => (int) setting_get('saas_storage_packs', 0),
        'period' => (string) setting_get('saas_paid_period', '') ?: 'month',
        'from'   => (string) setting_get('saas_paid_from', ''),
        'until'  => (string) setting_get('billing_paid_until', ''),
    ];
}
function saas_plan_next() {
    $n = json_decode((string) setting_get('saas_next_plan', ''), true);
    return is_array($n) ? $n : null;
}
function saas_monthly_value(array $mods, $seats, $period, $packs = 0) {
    $q = saas_company_quote($mods, $seats, $period, $packs);
    return $period === 'year' ? $q['total'] / 12 : (float) $q['total'];
}
function saas_same_selection(array $a, $seatsA, array $b, $seatsB, $packsA = 0, $packsB = 0) {
    sort($a); sort($b);
    return $a === $b && (int) $seatsA === (int) $seatsB && (int) $packsA === (int) $packsB;
}

// What choosing this would do, before anything is charged or written.
function saas_plan_change_quote(array $mods, $seats, $period, $today = null, $packs = 0) {
    $today  = $today ?: date('Y-m-d');
    $period = $period === 'year' ? 'year' : 'month';
    $mods   = array_values(array_intersect(selfserve_all_modules(), array_map('strtolower', $mods)));
    sort($mods);
    $seats  = max(1, (int) $seats);
    $packs  = max(0, (int) $packs);
    $q      = saas_company_quote($mods, $seats, $period, $packs);
    $packs  = (int) $q['packs'];
    $list   = (int) $q['total'];
    $end    = fn($from) => date('Y-m-d', strtotime($from . ($period === 'year' ? ' +1 year' : ' +1 month')));
    $out = ['ok' => true, 'msg' => '', 'mods' => $mods, 'seats' => $seats, 'packs' => $packs, 'period' => $period, 'list' => $list,
            'price' => $list, 'credit' => 0, 'starts' => $today, 'expires' => $end($today), 'extra_days' => 0, 'type' => 'NEW'];
    if (!$mods) return ['ok' => false, 'msg' => 'Pick at least one module.'] + $out;
    if (!empty($q['missing'])) return ['ok' => false, 'msg' => saas_quote_missing_msg($q['missing'], $period)] + $out;
    if ($list <= 0) return ['ok' => false, 'msg' => 'That selection has no price set yet — please contact your provider.'] + $out;

    $cur = saas_plan_current();
    $running = $cur['until'] !== '' && $cur['until'] >= $today && $cur['mods'];
    if (!$running) return $out;   // NEW

    $next = saas_plan_next();
    $isCurrent = saas_same_selection($mods, $seats, $cur['mods'], $cur['seats'], $packs, $cur['packs']) && $period === $cur['period'];
    $isNext = $next && saas_same_selection($mods, $seats, (array) $next['mods'], (int) $next['seats'], $packs, (int) ($next['packs'] ?? 0)) && $period === ($next['period'] ?? '');
    $starts = date('Y-m-d', strtotime($cur['until'] . ' +1 day'));
    if ($isCurrent || $isNext) {
        return ['type' => 'RENEW', 'starts' => $starts, 'expires' => $end($starts)] + $out;
    }
    $newVal = saas_monthly_value($mods, $seats, $period, $packs);
    $curVal = saas_monthly_value($cur['mods'], $cur['seats'], $cur['period'], $cur['packs']);
    if ($newVal > $curVal + 0.005) {
        // Unused value of what is running now, at its list price.
        $worth = (int) saas_company_quote($cur['mods'], $cur['seats'], $cur['period'], $cur['packs'])['total'];
        $from  = $cur['from'] !== '' ? $cur['from'] : date('Y-m-d', strtotime($cur['until'] . ($cur['period'] === 'year' ? ' -1 year' : ' -1 month')));
        $total = max(1, (strtotime($cur['until']) - strtotime($from)) / 86400);
        $left  = max(0, (strtotime($cur['until']) - strtotime($today)) / 86400);
        $credit = (int) round($worth * min(1, $left / $total));
        $price  = max(0, $list - $credit);
        $extra  = 0;
        if ($credit > $list && $list > 0) {
            $days = max(1, (int) round((strtotime($end($today)) - strtotime($today)) / 86400));
            $extra = (int) floor(($credit - $list) / ($list / $days));
        }
        return ['type' => 'UPGRADE', 'price' => $price, 'credit' => $credit, 'extra_days' => $extra,
                'expires' => date('Y-m-d', strtotime($end($today) . ' +' . $extra . ' days'))] + $out;
    }
    return ['type' => 'DOWNGRADE', 'price' => 0, 'starts' => $starts, 'expires' => $end($starts),
            'msg' => 'Your smaller plan starts on ' . (function_exists('fdate') ? fdate($starts) : $starts)
                   . ', when the current one ends. Nothing is charged now.'] + $out;
}

// Switch exactly these modules and logins on now.
function saas_plan_switch_to(array $mods, $seats, $packs = 0) {
    setting_set('saas_storage_packs', (string) max(0, (int) $packs));
    setting_set('saas_paid_modules', implode(',', $mods));
    saas_apply_modules_list($mods);
    setting_set('saas_entitled_modules', implode(',', $mods));
    if (function_exists('licence_disabled')) licence_disabled(true);
    setting_set('saas_seat_limit', (string) (int) $seats);
    setting_set('saas_seat_floor', (string) (int) $seats);
}

// Carry out a quote. NEW and UPGRADE switch now; RENEW and DOWNGRADE are booked
// for the end of the running period (applied by saas_apply_due_change()).
function saas_plan_change_apply(array $q, $paymentId = '', $orderId = '') {
    $note = implode(',', $q['mods']);
    // The first plan ever paid for turns the free trial into a paid company —
    // recorded once, shown to the customer and on the platform owner's console.
    if ($q['type'] === 'NEW' && (string) setting_get('saas_converted_at', '') === '' && (string) setting_get('billing_paid_until', '') === '') {
        setting_set('saas_converted_at', date('c'));
        $q['converted'] = true;
    }
    if ($q['type'] === 'NEW' || $q['type'] === 'UPGRADE') {
        saas_plan_switch_to($q['mods'], $q['seats'], (int) ($q['packs'] ?? 0));
        setting_set('saas_paid_period', $q['period']);
        setting_set('saas_paid_from', $q['starts']);
        setting_set('billing_paid_until', $q['expires']);
        setting_set('saas_next_plan', '');
    } elseif ($q['type'] === 'RENEW') {
        // Paid now: the running period is extended, and the selection (if it
        // differs) takes over the day the old period ends.
        setting_set('billing_paid_until', $q['expires']);
        setting_set('saas_next_plan', json_encode(['mods' => $q['mods'], 'seats' => $q['seats'], 'packs' => (int) ($q['packs'] ?? 0),
            'period' => $q['period'], 'starts' => $q['starts'], 'paid' => true]));
    } else {   // DOWNGRADE — booked, unpaid
        setting_set('saas_next_plan', json_encode(['mods' => $q['mods'], 'seats' => $q['seats'], 'packs' => (int) ($q['packs'] ?? 0),
            'period' => $q['period'], 'starts' => $q['starts'], 'paid' => false]));
        return $q;
    }
    if (function_exists('billing_record_line'))
        billing_record_line($q['seats'], $q['period'], $note, $paymentId, $orderId, $q['expires']);
    return $q;
}

// A booked change whose day has come. Cheap: one setting read on most requests.
function saas_apply_due_change($today = null) {
    $today = $today ?: date('Y-m-d');
    $n = saas_plan_next();
    if (!$n || (string) ($n['starts'] ?? '9999') > $today) return false;
    setting_set('saas_next_plan', '');
    if (!empty($n['paid'])) {
        saas_plan_switch_to((array) $n['mods'], (int) $n['seats'], (int) ($n['packs'] ?? 0));
        setting_set('saas_paid_period', (string) $n['period']);
        setting_set('saas_paid_from', (string) $n['starts']);
    }
    // An unpaid downgrade simply lets the old plan end; the owner is offered
    // the booked selection on the subscription page when they come to pay.
    else setting_set('saas_offer_plan', json_encode($n));
    return true;
}

// ===========================================================================
//  CONTROL INSTALL — the nightly look at every self-serve company
// ===========================================================================

// What a self-serve company needs from the platform owner to be able to pay:
// prices and the platform's payment keys. Read on the CONTROL install and
// copied into each self-serve company (at creation and every night), so a
// company never sets its own price.
function selfserve_sell_settings() {
    $keys = ['billing_currency', 'billing_price_user_month', 'billing_price_user_year', 'rzp_key_id', 'rzp_key_secret',
             'saas_storage_pack_price_month', 'saas_storage_pack_price_year'];
    if (defined('PRODUCT_MODULES')) foreach (PRODUCT_MODULES as $k => $m) {
        if (!empty($m[3])) continue;
        $keys[] = 'saas_price_mod_' . $k . '_month'; $keys[] = 'saas_price_mod_' . $k . '_year';
    }
    if (function_exists('commercial_push_keys')) $keys = array_values(array_unique(array_merge($keys, commercial_push_keys())));
    $out = [];
    foreach ($keys as $k) $out[$k] = (string) setting_get($k, '');
    return $out;
}

// The platform owner's storage allowance numbers, copied into EVERY hosted
// company each night (and when changed on the console) so each company can
// work out its own allowance without reaching back to the control database.
function selfserve_storage_settings() {
    $out = [];
    foreach (['saas_storage_gb_base', 'saas_storage_gb_per_login', 'saas_storage_pack_gb'] as $k) $out[$k] = (string) setting_get($k, '');
    return $out;
}

// Read the facts that live inside one company. Never throws; ['ok'=>false]
// when the company could not be opened (it is then left completely alone).
function selfserve_read_tenant($key, array $sell = null, array $storage = null) {
    $row = ['ok' => false, 'last_seen' => '', 'paid_until' => '', 'trial_until' => '', 'modules' => [], 'seat_limit' => 0,
            'storage_used' => null, 'storage_limit' => null];
    if (!function_exists('saas_enter_tenant')) return $row;
    // A file-backed company whose file does not exist yet has never been opened:
    // nothing to read, and connecting would create an empty file.
    $reg = function_exists('tenant_registry') ? tenant_registry() : [];
    $path = (string) ($reg['tenants'][$key]['sqlite'] ?? '');
    if ($path !== '' && !is_file($path)) return $row;
    $prev = $_SESSION['saas_tenant'] ?? null;
    try {
        if (saas_enter_tenant($key)) {
            $rt = [];
            try { db(); $rt = $GLOBALS['__tenant'] ?? []; } catch (Throwable $e) {}
            if (strtolower((string) ($rt['key'] ?? '')) === $key && ($rt['error'] ?? '') === '') {
                $seen = (string) setting_get('saas_last_seen', '');
                $login = '';
                try { $login = (string) ops_val("SELECT MAX(last_login_at) FROM users WHERE COALESCE(last_login_at,'')<>''"); } catch (Throwable $e) {}
                $row['last_seen']   = max($seen, $login);
                $row['paid_until']  = (string) setting_get('billing_paid_until', '');
                $row['trial_until'] = (string) setting_get('saas_trial_until', '');
                $row['modules']     = function_exists('saas_current_modules') ? saas_current_modules() : [];
                $row['seat_limit']  = (int) setting_get('saas_seat_limit', 0);
                $row['converted_at'] = (string) setting_get('saas_converted_at', '');
                if ($sell) foreach ($sell as $sk => $sv) { try { setting_set($sk, $sv); } catch (Throwable $e) {} }
                if ($storage) foreach ($storage as $sk => $sv) { try { setting_set($sk, $sv); } catch (Throwable $e) {} }
                $row['storage_used']  = saas_storage_used_bytes(true);
                $row['storage_limit'] = saas_storage_limit_bytes();
                $row['ok'] = true;
            }
        }
    } catch (Throwable $e) { $row['ok'] = false; }
    if ($prev === null) { unset($_SESSION['saas_tenant']); } else { $_SESSION['saas_tenant'] = $prev; }
    if (function_exists('db_reset')) db_reset();
    if (function_exists('licence_disabled')) licence_disabled(true);
    return $row;
}

// Days a company has not been used, from the later of its last use and its
// creation (a company nobody ever opened counts from the day it was made).
function selfserve_idle_days(array $t, $today = null) {
    $today = $today ?: date('Y-m-d');
    $from = max((string) ($t['last_active_at'] ?? ''), (string) ($t['created_at'] ?? ''));
    if ($from === '') return 0;
    return max(0, (int) floor((strtotime($today) - strtotime(substr($from, 0, 10))) / 86400));
}

// Never paid = the trial companies the deletion rule is about. A company that
// has ever paid is never deleted automatically (it goes view-only instead).
function selfserve_never_paid(array $t) {
    return trim((string) ($t['paid_until'] ?? '')) === '';
}

// The whole nightly pass. $opts: today (tests), dry (sync only, no mail, no
// deletion), reader (tests: fn($key) → selfserve_read_tenant shape),
// mailer (tests: fn($to,$subject,$body) → bool).
// Returns counts and a line per action.
function selfserve_sweep(array $opts = []) {
    $today = $opts['today'] ?? date('Y-m-d');
    $dry   = !empty($opts['dry']);
    $read  = $opts['reader'] ?? 'selfserve_read_tenant';
    $mail  = $opts['mailer'] ?? function ($to, $s, $b) { return function_exists('ops_mail') ? (bool) ops_mail($to, $s, $b, '', 'selfserve_inactive') : false; };
    $out = ['checked' => 0, 'synced' => 0, 'notices' => 0, 'deleted' => 0, 'lines' => []];
    if (function_exists('current_tenant') && current_tenant() !== '') return $out;   // control install only
    selfserve_migrate();
    $cfg = selfserve_cfg();
    $sell = selfserve_sell_settings();
    $storeSet = selfserve_storage_settings();
    try { $rows = ops_all("SELECT * FROM saas_tenants ORDER BY tenant_key"); }
    catch (Throwable $e) { return $out; }
    foreach ((array) $rows as $t) {
        $key = (string) $t['tenant_key'];
        $isSelf = ($t['source'] ?? '') === 'self';
        if (strtolower((string) ($t['status'] ?? 'active')) === 'suspended') continue;
        $out['checked']++;
        $facts = $read($key, $isSelf ? $sell : null, $storeSet + ['saas_storage_extra_gb' => (string) ($t['storage_extra_gb'] ?? '')]);
        if (empty($facts['ok'])) { $out['lines'][] = $key . ': could not be opened — left alone'; continue; }
        // Storage, for every company: record it for the console, and tell the
        // owner once as each warning level is crossed.
        if (($facts['storage_used'] ?? null) !== null) {
            $used = (int) $facts['storage_used']; $lim = (int) $facts['storage_limit'];
            $sUpd = ['storage_used' => (string) $used, 'storage_limit' => (string) $lim];
            $pct = $lim > 0 ? (int) floor(100 * $used / $lim) : 0;
            $level = 0; foreach (STORAGE_WARN_LEVELS as $lv) if ($pct >= $lv) $level = $lv;
            $told = (int) ($t['storage_notice'] ?? 0);
            if ($level < STORAGE_WARN_LEVELS[0]) $sUpd['storage_notice'] = 0;         // back under: a later rise is told again
            elseif ($level > $told && !$dry) {
                $subj = $level >= 100 ? 'Storage is full for ' . $t['company'] : 'Storage ' . $pct . '% used for ' . $t['company'];
                if ($mail((string) $t['owner_email'], $subj, selfserve_storage_body($t, $used, $lim, $pct, $isSelf))) {
                    $sUpd['storage_notice'] = $level;
                    $out['lines'][] = $key . ': storage ' . $pct . '% — owner told';
                }
            }
            saas_tenant_upsert($key, $sUpd);
        }
        if (!$isSelf) {
            // A company the platform owner manages: only "last used" is recorded.
            if ((string) $facts['last_seen'] !== '' && (string) $facts['last_seen'] > (string) ($t['last_active_at'] ?? ''))
                saas_tenant_upsert($key, ['last_active_at' => (string) $facts['last_seen']]);
            $out['synced']++;
            continue;
        }
        // 1) Bring the control record up to date from the company's own data.
        $upd = ['paid_until' => (string) $facts['paid_until'], 'trial_until' => (string) $facts['trial_until']];
        if ((string) $facts['last_seen'] !== '' && (string) $facts['last_seen'] > (string) ($t['last_active_at'] ?? '')) {
            $upd['last_active_at'] = (string) $facts['last_seen'];
            // Used again since the last warning → the warning count starts over.
            if ((string) ($t['last_notice_at'] ?? '') !== '' && $upd['last_active_at'] > (string) $t['last_notice_at']) {
                $upd['inactive_notices'] = 0; $upd['last_notice_at'] = '';
            }
        }
        if ((string) ($facts['converted_at'] ?? '') !== '') $upd['converted_at'] = (string) $facts['converted_at'];
        if ($facts['paid_until'] !== '') {
            $upd['plan'] = 'CUSTOM';                                   // logins = exactly those bought
            $upd['extra_user_seats'] = (int) $facts['seat_limit'];
            if ($facts['modules']) $upd['enabled_modules'] = json_encode(array_values(array_merge(['admin'], $facts['modules'])));
        }
        saas_tenant_upsert($key, $upd);
        $t = array_merge($t, $upd);
        $out['synced']++;
        if ($dry || !selfserve_never_paid($t)) continue;

        // 2) A never-paid company nobody uses: a warning every N days, then deletion.
        $idle = selfserve_idle_days($t, $today);
        $sent = (int) ($t['inactive_notices'] ?? 0);
        $deleteOn = date('Y-m-d', strtotime($today . ' +' . max(0, $cfg['delete_days'] - $idle) . ' days'));
        if ($idle >= $cfg['delete_days']) {
            // Only after at least one warning that has had a full interval to be read.
            $warnedLongEnough = $sent > 0 && (string) ($t['last_notice_at'] ?? '') !== ''
                && strtotime($today) - strtotime(substr((string) $t['last_notice_at'], 0, 10)) >= $cfg['notice_days'] * 86400;
            if ($cfg['auto_delete'] && $warnedLongEnough) {
                $r = saas_company_purge($key, 'Free trial not used for ' . $idle . ' days (rule: ' . $cfg['delete_days'] . ')', $t);
                if ($r['ok']) {
                    $out['deleted']++;
                    $out['lines'][] = $key . ': deleted after ' . $idle . ' days unused';
                    $mail((string) $t['owner_email'], 'Your trial workspace ' . $t['company'] . ' has been deleted',
                        selfserve_notice_body($t, $idle, '', true));
                } else $out['lines'][] = $key . ': deletion failed — ' . $r['error'];
                continue;
            }
            if (!$warnedLongEnough && $sent === 0) {
                // Due, but never warned (mail was off, or the rule was just switched on):
                // warn now; deletion waits a full interval.
                $deleteOn = date('Y-m-d', strtotime($today . ' +' . $cfg['notice_days'] . ' days'));
            } else continue;
        } elseif ($idle < $cfg['notice_days'] * ($sent + 1)) {
            continue;   // not yet time for the next warning
        }
        if ($mail((string) $t['owner_email'], 'Your trial workspace ' . $t['company'] . ' will be deleted on ' . (function_exists('fdate') ? fdate($deleteOn) : $deleteOn),
                selfserve_notice_body($t, $idle, $deleteOn, false))) {
            saas_tenant_upsert($key, ['inactive_notices' => $sent + 1, 'last_notice_at' => $today]);
            $out['notices']++;
            $out['lines'][] = $key . ': warning ' . ($sent + 1) . ' sent (' . $idle . ' days unused)';
        } else {
            $out['lines'][] = $key . ': warning could not be e-mailed — will try again tomorrow';
        }
    }
    return $out;
}

function selfserve_storage_body(array $t, $used, $lim, $pct, $self) {
    $app = function_exists('app_name') ? app_name() : 'Operations';
    $u = saas_bytes_label($used) . ' of ' . saas_bytes_label($lim);
    return "Hello " . (($t['owner_name'] ?? '') ?: 'there') . ",\n\n"
         . ($pct >= 100
            ? "Your " . $app . " workspace \"" . $t['company'] . "\" has used all its storage (" . $u . "). New files (photos, reports, documents) cannot be added until more storage is added. Everything already stored is safe, and records without files still save.\n"
            : "Your " . $app . " workspace \"" . $t['company'] . "\" has used " . $pct . "% of its storage (" . $u . ").\n")
         . ($self ? "Add storage under Admin → Your subscription.\n" : "Contact your provider to add storage.\n");
}

function selfserve_notice_body(array $t, $idle, $deleteOn, $deleted) {
    $app = function_exists('app_name') ? app_name() : 'Operations';
    $hi = "Hello " . (($t['owner_name'] ?? '') ?: 'there') . ",\n\n";
    if ($deleted) {
        return $hi . "Your free-trial workspace \"" . $t['company'] . "\" on " . $app . " was not used for " . $idle
             . " days, so it has been deleted together with its data, as explained when it was created.\n\n"
             . "You are welcome to start a new workspace at any time.\n";
    }
    return $hi . "Nobody has signed in to your free-trial workspace \"" . $t['company'] . "\" on " . $app . " for " . $idle . " days.\n\n"
         . "Unless someone signs in, the workspace and all its data will be deleted on "
         . (function_exists('fdate') ? fdate($deleteOn) : $deleteOn) . ".\n"
         . "To keep it, simply sign in. To keep it for good, choose a plan under Admin → Your subscription.\n";
}

// Permanently remove a company: its routing, its directory row, its sign-in
// index and its data. Shared by the console's "Remove" and the inactivity rule.
// Returns ['ok'=>bool,'error'=>'','note'=>''].
function saas_company_purge($key, $reason, array $t = null) {
    $key = strtolower(trim((string) $key));
    if ($key === '') return ['ok' => false, 'error' => 'No company given.', 'note' => ''];
    if (function_exists('current_tenant') && current_tenant() === $key)
        return ['ok' => false, 'error' => 'You are signed in to that company.', 'note' => ''];
    $t = $t ?: (saas_tenant_get($key) ?: []);
    $reg = function_exists('tenant_registry') ? tenant_registry() : ['tenants' => []];
    $route = $reg['tenants'][$key] ?? null;
    if (!$route) { $rj = json_decode((string) ($t['route_json'] ?? ''), true); $route = is_array($rj) ? $rj : null; }
    $sqlite = is_array($route) ? (string) ($route['sqlite'] ?? '') : '';
    $isMysql = is_array($route) && (!empty($route['db']) || !empty($route['name']));
    $note = '';

    // Data first, while the routing still knows where it is.
    if ($sqlite !== '') {
        $real = realpath($sqlite);
        $allowed = array_filter([realpath(dirname(__DIR__)),
            function_exists('tenant_safe_data_dir') ? realpath((string) tenant_safe_data_dir()[0]) : false]);
        $inside = false;
        foreach ($allowed as $base) if ($real && strncmp($real, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) === 0) $inside = true;
        if ($real && $inside) { @unlink($real); @unlink($real . '-wal'); @unlink($real . '-shm'); $note = 'Its data file was deleted.'; }
        elseif ($real) $note = 'Its data file is outside the folders this app manages and was left in place.';
    } elseif ($isMysql) {
        // Empty the company's own MySQL database, table by table, through its own connection.
        $prev = $_SESSION['saas_tenant'] ?? null;
        try {
            if (saas_enter_tenant($key)) {
                $rt = []; try { db(); $rt = $GLOBALS['__tenant'] ?? []; } catch (Throwable $e) {}
                if (strtolower((string) ($rt['key'] ?? '')) === $key && ($rt['error'] ?? '') === '') {
                    $pdo = db();
                    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
                    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $tbl)
                        $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', $tbl) . '`');
                    $note = 'Its database tables were deleted.';
                }
            }
        } catch (Throwable $e) { $note = 'Its database could not be emptied (' . $e->getMessage() . ') — remove it from your hosting panel.'; }
        if ($prev === null) { unset($_SESSION['saas_tenant']); } else { $_SESSION['saas_tenant'] = $prev; }
        if (function_exists('db_reset')) db_reset();
    }

    if (function_exists('tenant_remove')) tenant_remove($key);
    saas_tenant_delete($key);
    saas_login_index_clear_tenant($key);
    try {
        selfserve_migrate();
        db()->prepare("INSERT INTO saas_deletions (tenant_key,company,owner_email,reason,last_active_at,notices,deleted_at) VALUES (?,?,?,?,?,?,?)")
            ->execute([$key, (string) ($t['company'] ?? ''), (string) ($t['owner_email'] ?? ''), mb_substr((string) $reason, 0, 255),
                       (string) ($t['last_active_at'] ?? ''), (int) ($t['inactive_notices'] ?? 0), date('c')]);
    } catch (Throwable $e) {}
    return ['ok' => true, 'error' => '', 'note' => $note];
}

function selfserve_deletions($limit = 50) {
    try { return ops_all("SELECT * FROM saas_deletions ORDER BY id DESC LIMIT " . (int) $limit); } catch (Throwable $e) { return []; }
}
