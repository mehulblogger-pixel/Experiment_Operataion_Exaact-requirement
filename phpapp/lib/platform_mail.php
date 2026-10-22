<?php
// ============================================================================
//  PLATFORM SETUP — the platform owner's own e-mail, payment check and the
//  go-live checklist (owner, 22 Oct 2026: "Yes add the platform", "how to check
//  the email delivery, email setup and payment setup", "sent from our email id,
//  different from [the one] password [e-mails are] sent [from]").
//
//  PLATFORM E-MAIL — one mail account set by the platform owner that EVERY
//  hosted company uses when it has not set up its own. Two senders:
//
//    accounts  — sign-up codes, temporary passwords, password-reset links,
//                "your workspace is ready" (the PLATFORM_ACCOUNT_KINDS below);
//    general   — everything else (trial and storage warnings, reminders,
//                approvals, quotations sent by a company with no mail of its own).
//
//  The accounts sender may have its own mailbox login; left blank it uses the
//  general login (an alias / "send as" address on the same account).
//
//  WHERE IT IS KEPT — a file in the protected data folder above the web root
//  (tenant_safe_data_dir()), never in any company's database: a company's own
//  administrator therefore can never read the platform's mailbox password, yet
//  a company request can still send through it. The password is encrypted when
//  APP_ENCRYPTION_KEY is configured on the server.
//
//  ORDER — a server-wide OPS_SMTP_* setting (unchanged) → the company's OWN
//  e-mail settings (Settings → E-mail, unchanged) → the platform e-mail.
// ============================================================================

const PLATFORM_ACCOUNT_KINDS = ['password_reset', 'pro_reset', 'portal_reset', 'selfserve_code', 'selfserve_welcome',
                                'saas_new_owner', 'join', 'smtp_test_accounts'];

function platform_mail_file() {
    $dir = function_exists('tenant_safe_data_dir') ? (string) tenant_safe_data_dir()[0] : dirname(__DIR__);
    return rtrim($dir, '/') . '/platform-mail.php';
}

// The stored settings, passwords decrypted. Never throws.
function platform_mail_get() {
    $d = ['host' => '', 'port' => 587, 'general' => [], 'accounts' => [], 'payment_check' => []];
    $f = platform_mail_file();
    if (is_file($f)) {
        try { $r = @include $f; if (is_array($r)) $d = array_replace_recursive($d, $r); } catch (Throwable $e) {}
    }
    foreach (['general', 'accounts'] as $s) {
        $d[$s] += ['name' => '', 'from' => '', 'user' => '', 'pass' => ''];
        if (function_exists('app_decrypt')) $d[$s]['pass'] = (string) app_decrypt($d[$s]['pass']);
    }
    return $d;
}

// Save (platform owner only — the caller checks). A blank password keeps the
// one already stored. Returns '' or the reason it could not be written.
function platform_mail_save(array $in) {
    $cur = platform_mail_get();
    $out = $cur;
    $out['host'] = trim((string) ($in['host'] ?? ''));
    $out['port'] = (int) ($in['port'] ?? 587) ?: 587;
    foreach (['general', 'accounts'] as $s) {
        $src = (array) ($in[$s] ?? []);
        $out[$s]['name'] = substr(trim((string) ($src['name'] ?? '')), 0, 80);
        $from = strtolower(trim((string) ($src['from'] ?? '')));
        if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) return 'The ' . ($s === 'accounts' ? 'account e-mails' : 'general') . ' "from" address is not a valid e-mail address.';
        $out[$s]['from'] = $from;
        $out[$s]['user'] = trim((string) ($src['user'] ?? ''));
        $p = (string) ($src['pass'] ?? '');
        $out[$s]['pass'] = $p !== '' ? $p : (string) $cur[$s]['pass'];
    }
    return platform_mail_write($out);
}
function platform_mail_write(array $out) {
    foreach (['general', 'accounts'] as $s)
        if (function_exists('app_encrypt') && ($out[$s]['pass'] ?? '') !== '') $out[$s]['pass'] = app_encrypt($out[$s]['pass']);
    $f = platform_mail_file();
    $body = "<" . "?php\n// Platform e-mail and checks — written by the Platform setup screen. Keep private.\nreturn " . var_export($out, true) . ";\n";
    if (@file_put_contents($f, $body) === false) return 'Could not save — the protected data folder is not writable on this server.';
    @chmod($f, 0600);
    if (function_exists('opcache_invalidate')) @opcache_invalidate($f, true);   // read back at once (see tenant_registry_write)
    clearstatcache(true, $f);
    return '';
}
// Record the outcome of a check (test e-mail, payment) in the same file.
function platform_mail_note($key, array $v) {
    $d = platform_mail_get();
    $d['checks'][$key] = $v + ['at' => date('c')];
    return platform_mail_write($d);
}

// A mail server's error, in words the platform owner can act on; the original
// is kept after it for whoever helps them.
function platform_mail_explain($err) {
    $e = strtolower((string) $err);
    $plain = '';
    if (strpos($e, 'getaddrinfo') !== false || strpos($e, 'name or service not known') !== false || strpos($e, 'no such host') !== false)
        $plain = 'The mail server name was not found — check the mail server (SMTP host).';
    elseif (preg_match('/\b(535|534|530)\b/', $e) || strpos($e, 'authentication') !== false)
        $plain = 'The mail server refused the mailbox login or password. (Microsoft 365: "Authenticated SMTP" must be allowed for this mailbox.)';
    elseif (strpos($e, 'timed out') !== false || strpos($e, 'connection refused') !== false || strpos($e, 'connect failed') !== false)
        $plain = 'Could not reach the mail server on that port — check the port (usually 587) and that the hosting allows outgoing mail.';
    elseif (preg_match('/\b(550|553|554)\b/', $e) || strpos($e, 'sendas') !== false || strpos($e, 'send as') !== false)
        $plain = 'The mail server would not send from that "from" address — the mailbox needs permission to "send as" it.';
    elseif (strpos($e, 'tls') !== false)
        $plain = 'A secure connection could not be made — check the port (587 for STARTTLS, 465 for SSL).';
    elseif (strpos($e, 'test copy') !== false)
        $plain = 'This is a test copy, which never sends e-mail.';
    return $plain !== '' ? $plain . ' (Detail: ' . trim((string) $err) . ')' : trim((string) $err);
}

function platform_mail_ready() {
    $d = platform_mail_get();
    return $d['host'] !== '' && $d['general']['user'] !== '' && $d['general']['pass'] !== '';
}

// The SMTP settings to send a message of this kind through the platform, or
// null when the platform e-mail is not set up.
function platform_mail_sender($kind = '') {
    $d = platform_mail_get();
    if ($d['host'] === '' || $d['general']['user'] === '') return null;
    $g = $d['general'];
    $acct = in_array((string) $kind, PLATFORM_ACCOUNT_KINDS, true);
    $a = $d['accounts'];
    $useA = $acct && $a['from'] !== '';
    $user = $useA && $a['user'] !== '' ? $a['user'] : $g['user'];
    $pass = $useA && $a['user'] !== '' ? $a['pass'] : $g['pass'];
    $from = $useA ? $a['from'] : ($g['from'] !== '' ? $g['from'] : $g['user']);
    $name = $useA ? $a['name'] : $g['name'];
    // Inside a company, the reader sees the company's name and replies reach the company.
    $inCompany = function_exists('current_tenant') && current_tenant() !== '';
    if ($inCompany) {
        $co = function_exists('app_name') ? app_name() : '';
        $name = trim($co . ($name !== '' ? ' (via ' . $name . ')' : ''));
    }
    $reply = $inCompany && function_exists('setting_get') ? trim((string) setting_get('company_email', '')) : '';
    return ['host' => $d['host'], 'port' => (int) $d['port'], 'user' => $user, 'pass' => $pass, 'from' => $from,
            'from_name' => $name, 'reply_to' => filter_var($reply, FILTER_VALIDATE_EMAIL) ? $reply : '', 'source' => 'platform'];
}

// ---- Payment keys check ---------------------------------------------------
// Asks Razorpay to list one order with the platform's keys: it moves no money
// and creates nothing. Returns ['ok'=>bool,'mode'=>'test'|'live'|'','msg'=>...].
function platform_payment_check() {
    $c = function_exists('billing_config') ? billing_config() : ['enabled' => false];
    if (empty($c['enabled'])) return ['ok' => false, 'mode' => '', 'msg' => 'No Razorpay keys are saved yet (Super Admin → Advanced settings → Billing).'];
    $mode = strncmp((string) $c['key_id'], 'rzp_live_', 9) === 0 ? 'live' : (strncmp((string) $c['key_id'], 'rzp_test_', 9) === 0 ? 'test' : '');
    $r = rzp_http('GET', 'https://api.razorpay.com/v1/orders?count=1', '', $c['key_id'], $c['key_secret']);
    if (!empty($r['ok'])) return ['ok' => true, 'mode' => $mode, 'msg' => 'Razorpay accepted the keys' . ($mode === 'test' ? ' — TEST keys: no real money moves.' : ($mode === 'live' ? ' — LIVE keys: real payments.' : '.'))];
    $why = ($r['status'] ?? 0) === 401 ? 'Razorpay refused the keys (wrong Key ID or Key Secret).' : ($r['error'] ?? ('Razorpay answered ' . ($r['status'] ?? '?') . '.'));
    return ['ok' => false, 'mode' => $mode, 'msg' => $why];
}

// ---- The go-live checklist ---------------------------------------------------
function platform_checklist() {
    $d = platform_mail_get();
    $ch = (array) ($d['checks'] ?? []);
    $set = fn($k) => function_exists('setting_get') && trim((string) setting_get($k, '')) !== '';
    $priceSet = $set('billing_price_user_month');
    if (defined('PRODUCT_MODULES')) foreach (PRODUCT_MODULES as $k => $m) if (empty($m[3]) && $set('saas_price_mod_' . $k . '_month')) $priceSet = true;
    $reg = function_exists('tenant_registry') ? tenant_registry() : [];
    $cron = function_exists('setting_get') ? (string) setting_get('cron_last_run', '') : '';
    $mailOk = platform_mail_ready() || (function_exists('smtp_config') && smtp_config());
    $items = [
        ['key' => 'cloud', 'label' => 'Cloud mode is on (companies can be hosted)', 'ok' => (string) ($reg['base_domain'] ?? '') !== '',
         'how' => 'Super Admin → Cloud workspaces → Base domain (e.g. operations.mghaiapps.com).', 'link' => '/tenants'],
        ['key' => 'mail', 'label' => 'E-mail can be sent', 'ok' => $mailOk,
         'how' => 'Fill in "Platform e-mail" below, then press "Send a test e-mail".', 'link' => '#mail'],
        ['key' => 'mail_test', 'label' => 'A test e-mail was delivered (general)', 'ok' => !empty($ch['mail_general']['ok']),
         'how' => !empty($ch['mail_general']) ? ('Last test ' . substr((string) $ch['mail_general']['at'], 0, 16) . ': ' . $ch['mail_general']['msg']) : 'Press "Send a test e-mail" below and check the inbox.', 'link' => '#mail'],
        ['key' => 'mail_acct', 'label' => 'Account & password e-mails have their own sender', 'ok' => $d['accounts']['from'] !== '',
         'how' => 'Fill in "Account & password e-mails" below (for example accounts@your-domain), then test it.', 'link' => '#mail'],
        ['key' => 'pay', 'label' => 'Razorpay keys work', 'ok' => !empty($ch['payment']['ok']),
         'how' => !empty($ch['payment']) ? ('Last check ' . substr((string) $ch['payment']['at'], 0, 16) . ': ' . $ch['payment']['msg']) : 'Save your keys under Billing, then press "Check payment keys" below.', 'link' => '#pay'],
        ['key' => 'pay_live', 'label' => 'Razorpay is on LIVE keys (real payments)', 'ok' => ($ch['payment']['mode'] ?? '') === 'live',
         'how' => 'Use test keys (rzp_test_…) while trying it out; switch to live keys (rzp_live_…) to take real money.', 'link' => '#pay'],
        ['key' => 'prices', 'label' => 'Your own prices are set (not the built-in placeholders)', 'ok' => $priceSet,
         'how' => 'Companies → 💳 Pricing — a price per module and per login, monthly and yearly.', 'link' => '/companies?panel=pricing'],
        ['key' => 'storage', 'label' => 'Storage allowance is set', 'ok' => $set('saas_storage_gb_base') || $set('saas_storage_gb_per_login'),
         'how' => 'Companies → 💾 Storage allowance.', 'link' => '/companies'],
        ['key' => 'trial', 'label' => 'Trial storage and trial logins are set', 'ok' => $set('saas_trial_storage_mb') && $set('saas_trial_seats'),
         'how' => 'Companies → 🚀 Self sign-up & free trial.', 'link' => '/companies'],
        ['key' => 'cron', 'label' => 'The nightly job is running', 'ok' => $cron !== '' && strtotime($cron) > time() - 2 * 86400,
         'how' => $cron !== '' ? 'Last run ' . substr($cron, 0, 16) . '.' : 'Not run yet. In your hosting panel (cPanel → Cron Jobs) add, once a day: php ' . dirname(__DIR__) . '/cron.php', 'link' => ''],
        ['key' => 'signup', 'label' => 'Self sign-up is switched on', 'ok' => function_exists('selfserve_enabled') && selfserve_enabled(),
         'how' => 'Companies → 🚀 Self sign-up & free trial → tick "Companies can sign themselves up". Do this last, when everything above is green.', 'link' => '/companies'],
    ];
    return $items;
}

// Last e-mails sent from this install (the main site), newest first.
function platform_mail_log($limit = 40) {
    try { return ops_all("SELECT created_at, to_addr, subject, kind, sent_ok, error FROM email_log ORDER BY id DESC LIMIT " . (int) $limit); }
    catch (Throwable $e) { return []; }
}

// ---- The screen: /platform-setup (platform owner, main site only) ------------------
function ops_platform_setup($method) {
    ops_require(function_exists('superadmin_can') && superadmin_can(),
        'Only the platform owner (Super Admin), on the main site, can open Platform setup.');
    if ($method === 'POST') {
        $do = (string) ($_POST['do'] ?? '');
        if ($do === 'mail_save') {
            $err = platform_mail_save([
                'host' => $_POST['host'] ?? '', 'port' => $_POST['port'] ?? 587,
                'general'  => ['name' => $_POST['g_name'] ?? '', 'from' => $_POST['g_from'] ?? '', 'user' => $_POST['g_user'] ?? '', 'pass' => $_POST['g_pass'] ?? ''],
                'accounts' => ['name' => $_POST['a_name'] ?? '', 'from' => $_POST['a_from'] ?? '', 'user' => $_POST['a_user'] ?? '', 'pass' => $_POST['a_pass'] ?? ''],
            ]);
            flash($err === '' ? 'Platform e-mail saved. Now send a test e-mail from each sender.' : $err, $err === '' ? 'success' : 'error');
            redirect('/platform-setup#mail');
        }
        if ($do === 'mail_test') {
            $which = ($_POST['which'] ?? '') === 'accounts' ? 'accounts' : 'general';
            $to = strtolower(trim((string) ($_POST['to'] ?? '')));
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { flash('Enter the address to send the test to.', 'error'); redirect('/platform-setup#mail'); }
            $kind = $which === 'accounts' ? 'smtp_test_accounts' : 'smtp_test';
            $app = function_exists('app_name') ? app_name() : 'Platform';
            $ok = ops_mail($to, $app . ' — test e-mail (' . ($which === 'accounts' ? 'account & password sender' : 'general sender') . ')',
                "This is a test from " . $app . ".\n\nIf you are reading it, the " . ($which === 'accounts' ? 'account & password' : 'general')
                . " sender works. Check which address it came from.\n", '', $kind);
            $last = ops_one("SELECT error FROM email_log WHERE kind=? ORDER BY id DESC LIMIT 1", [$kind]);
            $msg = $ok ? 'sent to ' . $to . ' — check the inbox (and spam) to confirm it arrived' : ('failed: ' . platform_mail_explain((string) ($last['error'] ?? 'unknown reason')));
            platform_mail_note('mail_' . $which, ['ok' => (bool) $ok, 'msg' => $msg]);
            flash('Test e-mail ' . $msg . '.', $ok ? 'success' : 'error');
            redirect('/platform-setup#mail');
        }
        if ($do === 'pay_check') {
            $r = platform_payment_check();
            platform_mail_note('payment', $r);
            flash($r['msg'], $r['ok'] ? 'success' : 'error');
            redirect('/platform-setup#pay');
        }
        redirect('/platform-setup');
    }
    $u = function_exists('current_user') ? current_user() : [];
    view('ops/platform_setup', [
        'mail' => platform_mail_get(), 'items' => platform_checklist(), 'log' => platform_mail_log(20),
        'pay' => function_exists('billing_config') ? billing_config() : [], 'me' => (string) ($u['email'] ?? ''),
        'enc' => function_exists('app_enc_available') && app_enc_available(),
    ]);
    return true;
}
