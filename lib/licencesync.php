<?php
// ============================================================================
//  Automatic renewal — the customer pays and the system renews itself
//
//  WHY THIS EXISTS. The signed key was the right foundation and the wrong
//  product. Pasting a key by hand is fine for an air-gapped factory and
//  unacceptable for everybody else: the customer pays on Tuesday morning and
//  waits for somebody at MGH to notice, open a tool, type their details and
//  send an e-mail. Zoho renews the instant the payment clears, and a customer
//  who has just paid and is still read-only is a customer writing a review.
//
//  HOW IT WORKS, and it changes nothing about the security model:
//
//      customer pays  →  the licence server extends their subscription
//                     →  this installation checks in and collects a fresh key
//
//  The key is still signed by MGH and still verified here by arithmetic. All
//  that changes is HOW the key arrives: fetched rather than pasted.
//
//  OFFLINE TOLERANCE IS KEPT, and that is the whole reason the offline design
//  came first. The fetched key is cached. If the licence server is down, slow,
//  or unreachable behind a corporate proxy, this installation carries on using
//  the key it already has until that key's own expiry. An outage at MGH must
//  never stop a customer invoicing — a check-in that fails is a non-event, not
//  an incident.
//
//  NEAR EXPIRY IT LOOKS HARDER. Once a day is enough for a subscription with
//  eleven months to run. It is nowhere near enough for one that lapsed
//  yesterday and was paid for an hour ago. So the interval tightens as the
//  expiry approaches: daily normally, every quarter of an hour inside the last
//  three days and throughout the grace period. And the licence screen has a
//  button, because somebody who has just paid will press it rather than wait.
//
//  THE INSTALL ID IS THE CREDENTIAL. One opaque value per installation,
//  issued when the customer is set up. It is not a password and grants nothing
//  except "tell me what this installation is entitled to" — the worst a stolen
//  one can do is read a licence that is already sitting on that customer's own
//  screen.
// ============================================================================

const LICSYNC_TIMEOUT   = 12;      // seconds; a slow hub must never hang a cron run
const LICSYNC_QUIET_H   = 20;      // hours between ordinary check-ins
const LICSYNC_URGENT_D  = 3;       // days before expiry when it starts looking hard
const LICSYNC_URGENT_M  = 15;      // minutes between check-ins once it is urgent

function licsync_try($fn, $fb = null) { try { return $fn(); } catch (Throwable $e) { return $fb; } }

function licsync_server() {
    $e = getenv('LICENCE_SERVER');
    $v = ($e !== false && trim((string)$e) !== '') ? trim((string)$e) : (string)setting_get('licence_server', '');
    return rtrim($v, '/');
}

function licsync_install() {
    $e = getenv('LICENCE_INSTALL');
    if ($e !== false && trim((string)$e) !== '') return trim((string)$e);
    return trim((string)setting_get('licence_install', ''));
}

// Configured means: we know where to ask, and who to say we are.
function licsync_on() { return licsync_server() !== '' && licsync_install() !== ''; }

// Same rule as the Ads Pro connector: https always, plain http only to this
// same machine. The install id travels on every request.
function licsync_url_safe($base) {
    $u = parse_url((string)$base);
    if (!$u || empty($u['scheme']) || empty($u['host'])) return false;
    if (strtolower($u['scheme']) === 'https') return true;
    if (strtolower($u['scheme']) !== 'http') return false;
    $h = strtolower((string)$u['host']);
    return $h === 'localhost' || $h === '127.0.0.1' || $h === '::1';
}

// ---- When to look -----------------------------------------------------------
function licsync_due() {
    if (!licsync_on()) return false;
    $last = (int)strtotime((string)setting_get('licence_checked_at', '')) ?: 0;
    if ($last <= 0) return true;                                  // never looked

    $st = function_exists('lk_state') ? lk_state() : ['state' => 'OPEN', 'days_left' => null];
    $urgent = in_array($st['state'], ['GRACE', 'READONLY', 'INVALID', 'MISSING'], true)
           || ($st['days_left'] !== null && $st['days_left'] <= LICSYNC_URGENT_D);

    $wait = $urgent ? LICSYNC_URGENT_M * 60 : LICSYNC_QUIET_H * 3600;
    return (time() - $last) >= $wait;
}

// ---- The check-in -----------------------------------------------------------
// Returns ['ok'=>bool, 'changed'=>bool, 'msg'=>string]. It NEVER throws and
// never leaves the caller worse off: a failure writes a note and returns.
// The heartbeat this install adds to its key pull: what it believes it holds and
// how many people are signed in, so the provider can spot a copy that has gone
// quiet or drifted out of licence. Best-effort — never blocks the pull.
function licsync_report_query() {
    $q = '';
    try {
        $s = function_exists('lk_summary') ? lk_summary() : [];
        $parts = [
            'state' => (string)($s['state'] ?? ''),
            'used'  => (string)(function_exists('lk_seats_used') ? lk_seats_used() : ''),
            'lic'   => (string)($s['seats'] ?? ''),
            'cust'  => (string)($s['customer'] ?? ''),
            'host'  => (string)($_SERVER['HTTP_HOST'] ?? ''),
            'ver'   => defined('APP_VERSION') ? (string)APP_VERSION : '',
        ];
        foreach ($parts as $k => $v) if ($v !== '') $q .= '&' . $k . '=' . rawurlencode($v);
    } catch (Throwable $e) { return ''; }
    return $q;
}

function licsync_checkin($force = false) {
    if (!licsync_on())
        return ['ok' => false, 'changed' => false, 'msg' => 'No licence server is configured for this installation.'];
    if (!$force && !licsync_due())
        return ['ok' => true, 'changed' => false, 'msg' => 'Checked recently — nothing to do.'];

    $base = licsync_server();
    if (!licsync_url_safe($base))
        return ['ok' => false, 'changed' => false,
                'msg' => 'Refusing to send the installation id over plain http to ' . $base . '. Use https.'];

    $url = $base . '/api.php?action=licence&install=' . rawurlencode(licsync_install());
    // Attach a small self-report (the heartbeat) so the provider's console can
    // see this copy is alive and what state it believes it is in.
    $url .= licsync_report_query();
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => LICSYNC_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    // Whatever happens, record that we tried. Without this the urgent interval
    // would call out on every single cron run for as long as the hub is down.
    licsync_try(fn() => setting_set('licence_checked_at', date('c')));

    if ($raw === false) {
        $msg = 'Could not reach the licence server — ' . ($cerr ?: 'no response');
        licsync_try(fn() => setting_set('licence_checkin_error', $msg));
        return ['ok' => false, 'changed' => false, 'msg' => $msg];
    }
    $j = json_decode((string)$raw, true);
    if (!is_array($j)) {
        $msg = 'The licence server replied with something that was not JSON (HTTP ' . $code . ').';
        licsync_try(fn() => setting_set('licence_checkin_error', $msg));
        return ['ok' => false, 'changed' => false, 'msg' => $msg];
    }
    if ($code >= 400 || !empty($j['error'])) {
        $msg = (string)($j['error'] ?? ('The licence server refused the request (HTTP ' . $code . ').'));
        licsync_try(fn() => setting_set('licence_checkin_error', $msg));
        return ['ok' => false, 'changed' => false, 'msg' => $msg];
    }

    $key = trim((string)($j['key'] ?? ''));
    if ($key === '') {
        $msg = 'The licence server answered but sent no key.';
        licsync_try(fn() => setting_set('licence_checkin_error', $msg));
        return ['ok' => false, 'changed' => false, 'msg' => $msg];
    }

    // VERIFY BEFORE STORING. The server is ours, but a key that fails
    // verification must never replace a good one — that would turn a
    // misconfigured hub into a fleet-wide outage.
    $c = lk_verify($key);
    if (!empty($c['err'])) {
        $msg = 'The licence server sent a key this installation cannot verify: ' . $c['err'];
        licsync_try(fn() => setting_set('licence_checkin_error', $msg));
        return ['ok' => false, 'changed' => false, 'msg' => $msg];
    }

    licsync_try(fn() => setting_set('licence_checkin_error', ''));
    $before = trim((string)setting_get('licence_key', ''));
    if ($before === $key)
        return ['ok' => true, 'changed' => false, 'msg' => 'Already up to date — valid to ' . fdate((string)$c['exp']) . '.'];

    licsync_try(fn() => setting_set('licence_key', $key));
    if (function_exists('lk_mark_enforced')) licsync_try(fn() => lk_mark_enforced());
    if (function_exists('lk_state')) lk_state(true);
    if (function_exists('licence_disabled')) licence_disabled(true);
    if (function_exists('act_log'))
        licsync_try(fn() => act_log('SYSTEM', 0, 'SYSTEM',
            'Licence renewed automatically — now valid to ' . fdate((string)$c['exp']), ['auto' => 1]));
    return ['ok' => true, 'changed' => true,
            'msg' => 'Renewed automatically — now valid to ' . fdate((string)$c['exp']) . '.'];
}

// ---- For the screen ---------------------------------------------------------
function licsync_status() {
    return [
        'on'      => licsync_on(),
        'server'  => licsync_server(),
        'install' => licsync_install(),
        'last'    => (string)setting_get('licence_checked_at', ''),
        'error'   => (string)setting_get('licence_checkin_error', ''),
        'due'     => licsync_due(),
        'from_env'=> getenv('LICENCE_SERVER') !== false && trim((string)getenv('LICENCE_SERVER')) !== '',
    ];
}
