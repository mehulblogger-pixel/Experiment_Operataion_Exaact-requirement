<?php
// ============================================================================
//  The licence key — what this customer bought, proved rather than asserted
//
//  WHAT WAS MISSING. lib/licence.php already knew how to switch a module off,
//  and enforced it in three places at once. But the switch was set by hand on
//  the server. That is fine for one customer and impossible for a hundred, and
//  it had no notion of WHEN the subscription ends or HOW MANY people may sign
//  in. So there was a product but no way to sell it.
//
//  SIGNED, NOT SHARED. The key is signed with a private key that only MGH
//  holds; every installation ships the matching PUBLIC key, which is safe to
//  publish. So a customer with root on their own server, their own database and
//  a copy of the source still cannot mint themselves a licence — the most they
//  can do is delete ours, which only takes things away from them.
//
//  This is why it is not an HMAC. A shared secret has to be present on the
//  customer's machine to verify, and anything that can verify can also forge.
//
//  OFFLINE. Verification is arithmetic, not a web request. An installation on a
//  factory network behind a corporate proxy works exactly like one on a VPS,
//  and a MGH outage cannot stop a customer invoicing. There is no phone-home,
//  and adding one later would be a downgrade.
//
//  FOUR RULES, and the first one is the important one:
//
//  1. A CUSTOMER IS NEVER LOCKED OUT OF THEIR OWN DATA. An expired licence
//     makes the system read-only: every screen, every export, every PDF still
//     works; only new records are refused. Locking a business out of its own
//     invoices earns a chargeback, a bad review and possibly a legal letter,
//     and it never once made anybody renew faster.
//
//  2. NO ENFORCEMENT UNLESS IT IS TURNED ON. With no LICENCE_ENFORCE the whole
//     file stands down and the system behaves precisely as it did before it
//     existed. Every installation running today keeps running.
//
//  3. A TRIAL, so a new customer is not stuck. Enforcement on but no key yet
//     means a full-featured trial from first boot. Without it a fresh install
//     would be read-only before anybody could paste the key that fixes it.
//
//  4. THE KEY CAN ALWAYS BE PASTED. Whatever state the licence is in, the
//     licence screen accepts a new one. A rule that locks the screen you need
//     in order to unlock the system is not a rule, it is a support call.
// ============================================================================

// The MGH signing key — the real one, made on the licence server at
// id.mghaiapps.com/licences/ and never off that machine in its private half.
// This is the PUBLIC half only: it can verify a licence and cannot create one,
// so publishing it costs nothing and every copy of this application ships with
// it. Replaced per deployment via LICENCE_PUBKEY if a customer is ever issued
// keys from a different signing pair.
//
// If this is ever changed, every licence already issued stops verifying. It is
// not a setting; it is the anchor the whole scheme hangs from.
const LICENCE_PUBKEY_DEFAULT = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEqBi7DWZhgXzmGUfhaeXyJUxM0bvr
VdEeTDu6pl3oAKMciG2CWcOAOzA2dwGz5EyryeqBnM15vBM0g4Bk/h3SHw==
-----END PUBLIC KEY-----
PEM;

const LICENCE_TRIAL_DAYS = 14;
const LICENCE_GRACE_DEFAULT = 14;

// The states, in the words the screen uses. Order matters: this is also the
// severity ranking used by the banner.
const LICENCE_STATES = [
    'OPEN'     => 'Not licensed — everything is switched on',
    'TRIAL'    => 'Trial',
    'VALID'    => 'Licensed',
    'GRACE'    => 'Expired — still working',
    'READONLY' => 'Expired — read only',
    'INVALID'  => 'Licence key not accepted',
    'MISSING'  => 'No licence key',
];

function lk_try($fn, $fb = null) { try { return $fn(); } catch (Throwable $e) { return $fb; } }

function lk_pubkey() {
    $env = getenv('LICENCE_PUBKEY');
    if ($env !== false && trim((string)$env) !== '') return (string)$env;
    // A public key set from the one-click "Set up signing" button (or pasted on a
    // customer install as the provider's key) lives in settings. It wins over the
    // built-in default so a vendor who generated their own pair — and the
    // customers verifying against it — all line up without touching any code.
    $s = function_exists('setting_get') ? trim((string)setting_get('licence_pubkey', '')) : '';
    if ($s !== '') return $s;
    return LICENCE_PUBKEY_DEFAULT;
}

// Whether this copy must honour a signed licence. Designed so that NOBODY — not
// the provider, not the customer — ever edits a server setting: the state is
// decided from things the app already knows.
//
//  1. An explicit LICENCE_ENFORCE in the environment always wins (managed hosts,
//     and a hard "0" to force a copy open).
//  2. The provider's own issuing server is never locked by its own licences: it
//     is the one machine that holds the private signing key, so it is exempt.
//  3. Otherwise a copy enforces the moment a licence key is FIRST applied to it.
//     Pasting that first key sets a sticky flag, so a customer who later removes
//     the key drops to read-only rather than silently becoming unlimited again.
//
// The upshot for selling: a customer installs the files and pastes the key you
// send — nothing else. Their copy was open (a 14-day trial) until the key
// arrived, and honours exactly the seats you signed from then on.
function lk_enforcing() {
    $v = getenv('LICENCE_ENFORCE');
    if ($v !== false && (string)$v !== '') return (string)$v !== '0';
    if (function_exists('lk_privkey') && lk_privkey() !== '') return false;
    // A key that is physically present is always honoured, so simply flipping the
    // sticky flag in the database does not free the copy — the seats still come
    // from the signed key, which cannot be forged. Enforcement only falls away if
    // BOTH the key is wiped AND the flag is cleared, which leaves no licence at
    // all and is plainly a breach rather than a setting.
    if (trim((string)setting_get('licence_key', '')) !== '') return true;
    return (string)setting_get('licence_enforce', '0') === '1';
}

// Called the first time a valid key is applied (by hand or by auto-pull). It
// makes enforcement stick, so this copy can never be freed by deleting the key.
function lk_mark_enforced() {
    if (function_exists('lk_privkey') && lk_privkey() !== '') return; // the issuer server stays open
    if ((string)setting_get('licence_enforce', '0') !== '1') setting_set('licence_enforce', '1');
}

function lk_raw_key() {
    $env = getenv('LICENCE_KEY');
    if ($env !== false && trim((string)$env) !== '') return trim((string)$env);
    return trim((string)setting_get('licence_key', ''));
}

function lk_b64d($s) { return base64_decode(strtr((string)$s, '-_', '+/')); }

// ---- Verification -----------------------------------------------------------
// Returns the claims on success, or ['err' => reason]. Every failure names
// itself: "not accepted" with no reason is the least useful message software
// can produce, and it is the one a customer will read out over the telephone.
function lk_verify($key) {
    $key = trim((string)$key);
    if ($key === '') return ['err' => 'No key given.'];
    $parts = explode('.', $key);
    if (count($parts) !== 2) return ['err' => 'That does not look like a licence key — it should be two parts separated by a dot.'];

    $payload = lk_b64d($parts[0]);
    $sig     = lk_b64d($parts[1]);
    if ($payload === false || $sig === false || $payload === '' || $sig === '')
        return ['err' => 'The key is damaged — it may have been broken across lines when it was copied.'];

    $ok = lk_try(fn() => openssl_verify($parts[0], $sig, lk_pubkey(), OPENSSL_ALGO_SHA256), -1);
    if ($ok !== 1)
        return ['err' => 'The signature does not match. This key was not issued by MGH, or it has been altered.'];

    $c = json_decode($payload, true);
    if (!is_array($c)) return ['err' => 'The key is signed correctly but its contents are unreadable.'];
    if (empty($c['exp'])) return ['err' => 'The key carries no expiry date.'];
    return $c;
}

// ---- The state machine ------------------------------------------------------
// The public entry point: the licence state from the signed key (or trial),
// with the self-service billing grant laid over it. A paid seat plan bought
// through Razorpay on this install adds seats and keeps the system live to its
// paid-until date, on top of — or in place of — a signed key. This is what lets
// per-user self-service payment work without the vendor re-issuing a key.
function lk_state($reload = false) {
    static $s = null;
    if ($s !== null && !$reload) return $s;
    $out = lk_state_base($reload);
    if (function_exists('billing_grant')) {
        $g = billing_grant();
        if (!empty($g['active'])) {
            if ((int) $g['seats'] > (int) $out['seats']) $out['seats'] = (int) $g['seats'];
            $untilTs = strtotime((string) $g['until'] . ' 23:59:59');
            $curEndTs = ($out['days_left'] === null) ? 0 : (time() + (int) $out['days_left'] * 86400);
            // The paid plan governs whenever the system would otherwise be blocked
            // or on a bare trial, or when it simply runs longer than the key.
            if ($untilTs > time()
                && (!empty($out['read_only']) || in_array($out['state'], ['TRIAL', 'OPEN', 'MISSING', 'INVALID'], true) || $untilTs > $curEndTs)) {
                $out['state']     = 'VALID';
                $out['read_only'] = false;
                $out['err']       = '';
                $out['days_left'] = (int) floor(($untilTs - time()) / 86400);
                if ($out['customer'] === '') $out['customer'] = (string) setting_get('billing_customer', '');
            }
        }
    }
    return $out;
}

function lk_state_base($reload = false) {
    $out = ['state' => 'OPEN', 'claims' => [], 'err' => '', 'days_left' => null,
            'read_only' => false, 'customer' => '', 'seats' => 0];

    if (!lk_enforcing()) return $out;

    $key = lk_raw_key();
    if ($key === '') {
        // Trial, counted from the first time this installation ever booted.
        // Stored, not computed from a file date, because a file date changes on
        // every deployment and would hand out a fresh trial with each upgrade.
        $first = (string)setting_get('licence_first_seen', '');
        if ($first === '') { $first = date('Y-m-d'); lk_try(fn() => setting_set('licence_first_seen', $first)); }
        $used = (int)floor((strtotime(date('Y-m-d')) - strtotime($first)) / 86400);
        $left = LICENCE_TRIAL_DAYS - $used;
        $out['days_left'] = $left;
        if ($left > 0) { $out['state'] = 'TRIAL'; return $out; }
        $out['state'] = 'READONLY'; $out['read_only'] = true;
        $out['err'] = 'The ' . LICENCE_TRIAL_DAYS . '-day trial has ended.';
        return $out;
    }

    $c = lk_verify($key);
    if (!empty($c['err'])) {
        // A bad key is still read-only, never a lock-out. The customer's data is
        // theirs whatever went wrong with our paperwork.
        $out['state'] = 'INVALID'; $out['read_only'] = true; $out['err'] = $c['err'];
        return $out;
    }

    $out['claims']   = $c;
    $out['customer'] = (string)($c['cust'] ?? '');
    $out['seats']    = (int)($c['seats'] ?? 0);
    // Optional role-based cap: a key may split its seats into full + field. When
    // absent (the common case, and every key issued before this) field_seats is
    // 0 and every active user counts against the single `seats` cap as before.
    $out['field_seats'] = (int)($c['field_seats'] ?? 0);

    $exp   = strtotime((string)$c['exp'] . ' 23:59:59');
    $grace = (int)($c['grace'] ?? LICENCE_GRACE_DEFAULT);
    $now   = time();
    $out['days_left'] = (int)floor(($exp - $now) / 86400);

    if ($now <= $exp)                              $out['state'] = 'VALID';
    elseif ($now <= $exp + ($grace * 86400))       $out['state'] = 'GRACE';
    else { $out['state'] = 'READONLY'; $out['read_only'] = true;
           $out['err'] = 'The subscription ended on ' . fdate((string)$c['exp'])
                       . ' and the ' . $grace . '-day grace period is over.'; }
    return $out;
}

function lk_read_only() { return !empty(lk_state()['read_only']); }

// ---- What was bought --------------------------------------------------------
// The licensed module list, or null when nothing is being enforced — null means
// "this file has no opinion", which is what lets licence_disabled() carry on
// using the settings screen exactly as before.
function lk_modules() {
    $st = lk_state();
    if ($st['state'] === 'OPEN') return null;           // nothing is being enforced
    if ($st['state'] === 'TRIAL') return null;          // a trial is of everything

    // A key that failed verification grants NOTHING beyond the core. Returning
    // null here — "this file has no opinion" — was a bug caught by the forgery
    // test: it fell through to the settings screen, so tampering with a key
    // revealed every module in the product. Read-only made it harmless in
    // practice, but rewarding tampering with anything at all is the wrong shape.
    if ($st['state'] === 'INVALID' || $st['state'] === 'MISSING') return [];

    $m = $st['claims']['mods'] ?? null;
    // VALID, GRACE and READONLY all read from the key: an expired customer must
    // keep SEEING exactly what they bought. Taking their screens away as well as
    // their ability to save would be punishing them twice for one late payment.
    return is_array($m) ? array_map('strtolower', $m) : [];
}

// Starter or professional, per module. Unknown means the whole module.
function lk_tier($module) {
    $t = lk_state()['claims']['tier'] ?? [];
    return is_array($t) ? (string)($t[strtolower($module)] ?? 'pro') : 'pro';
}

// ---- Seats ------------------------------------------------------------------
function lk_seats_used() {
    return (int)(lk_try(fn() => ops_val("SELECT COUNT(*) FROM users WHERE is_active = 1"), 0) ?: 0);
}

// Which roles are "field" (light) seats. Defers to the seat-class helper when it
// is loaded (Super Admin panel), and otherwise falls back to just the inspector,
// so seat accounting is identical whether or not that module is present.
function lk_field_roles() {
    if (function_exists('superadmin_field_roles')) return superadmin_field_roles();
    return ['INSPECTOR'];
}

// Active seats used in the FIELD class, for a licence that caps field seats
// separately.
function lk_field_seats_used() {
    $roles = lk_field_roles();
    if (!$roles) return 0;
    $in = implode(',', array_fill(0, count($roles), '?'));
    return (int)(lk_try(fn() => ops_val(
        "SELECT COUNT(*) FROM users WHERE is_active = 1 AND UPPER(role) IN ($in)",
        array_map('strtoupper', $roles)), 0) ?: 0);
}

// Called before a user is created or re-activated. Returns '' when allowed, or
// the reason it is refused — in the words of the person who has to fix it.
// $role is the role of the person about to be created/reactivated; it lets a
// role-based key charge that person against the right pool. Callers that don't
// pass a role keep the old single-pool behaviour.
function lk_seat_block($role = '') {
    $st = lk_state();
    if ($st['state'] === 'OPEN' || $st['state'] === 'TRIAL') return '';
    $seats = (int)$st['seats'];
    $fieldCap = (int)($st['field_seats'] ?? 0);

    // Role-based cap: full and field seats are counted and limited separately.
    if ($fieldCap > 0 && $role !== '') {
        $isField = in_array(strtoupper((string)$role), array_map('strtoupper', lk_field_roles()), true);
        if ($isField) {
            $usedF = lk_field_seats_used();
            if ($usedF < $fieldCap) return '';
            return 'Your plan covers ' . $fieldCap . ' field ' . ($fieldCap === 1 ? 'seat' : 'seats')
                 . ' and ' . $usedF . ' are already active. Deactivate a field user who has left, or ask MGH to add field seats.';
        }
        // Full pool = total seats minus the field allocation.
        $fullCap = max(0, $seats - $fieldCap);
        if ($fullCap <= 0) return '';
        $usedFull = max(0, lk_seats_used() - lk_field_seats_used());
        if ($usedFull < $fullCap) return '';
        return 'Your plan covers ' . $fullCap . ' full ' . ($fullCap === 1 ? 'seat' : 'seats')
             . ' and ' . $usedFull . ' are already active. Deactivate somebody who has left, or ask MGH to add seats.';
    }

    if ($seats <= 0) return '';                            // unlimited
    $used = lk_seats_used();
    if ($used < $seats) return '';
    return 'Your subscription covers ' . $seats . ' ' . ($seats === 1 ? 'person' : 'people')
         . ' and ' . $used . ' are already active. Deactivate somebody who has left, or ask MGH to add seats.';
}

// ---- Read-only enforcement --------------------------------------------------
// Routes that must keep working even when the licence has run out. Everything
// here either takes nothing in, or is the thing that fixes the licence.
const LICENCE_ALWAYS_ALLOW = [
    'licence', 'licence-save', 'licence-check',   // the screen that fixes the licence
    'billing', 'billing-order', 'billing-verify', // …and paying is how you fix it
    'logout', 'login',
    'change-password', 'my-signature',
    'verify',                        // public report verification
];

function lk_blocks_write($route) {
    if (!lk_read_only()) return false;
    if (in_array($route, LICENCE_ALWAYS_ALLOW, true)) return false;
    // Anything that only reads is unaffected — this is called on POST alone.
    return true;
}

// ---- The screen -------------------------------------------------------------
function lk_can_manage() { return can('settings.manage') || is_master(); }

function lk_summary() {
    $st = lk_state();
    $out = [
        'state'     => $st['state'],
        'label'     => LICENCE_STATES[$st['state']] ?? $st['state'],
        'customer'  => $st['customer'],
        'ref'       => (string)($st['claims']['ref'] ?? ''),
        'expires'   => (string)($st['claims']['exp'] ?? ''),
        'days_left' => $st['days_left'],
        'seats'     => (int)$st['seats'],
        'field_seats' => (int)($st['field_seats'] ?? 0),
        'field_used'  => lk_field_seats_used(),
        'used'      => lk_seats_used(),
        'err'       => $st['err'],
        'enforcing' => lk_enforcing(),
        'from_env'  => getenv('LICENCE_KEY') !== false && trim((string)getenv('LICENCE_KEY')) !== '',
        'modules'   => [],
    ];
    $mods = lk_modules();
    foreach (PRODUCT_MODULES as $key => [$lbl, $desc, $covers, $core]) {
        $on = $core || $mods === null || in_array($key, $mods, true);
        $out['modules'][$key] = ['label' => $lbl, 'desc' => $desc, 'on' => $on, 'core' => $core,
                                 'tier' => $on && $mods !== null ? lk_tier($key) : ''];
    }
    return $out;
}

// Module 36 — one normalized "does the licence/subscription need attention" verdict,
// derived from lk_summary() (state, days_left, seats) + the auto-renew status. It
// exists so a lapse can be surfaced BEFORE it forces read-only — an ambient admin
// banner and a cron nudge both read this, instead of each re-deriving the rules.
// Advisory only; it blocks nothing.
function licence_health() {
    $s    = lk_summary();
    $sync = function_exists('licsync_status') ? licsync_status() : [];
    $state = (string)$s['state'];
    $days  = $s['days_left'];
    $seats = (int)$s['seats']; $used = (int)$s['used'];
    $overSeat = $seats > 0 && $used > $seats;
    $atSeat   = $seats > 0 && $used >= $seats && !$overSeat;
    $syncErr  = trim((string)($sync['error'] ?? ''));

    $sev = 'ok'; $head = ''; $detail = ''; $url = '/licence';
    if ($state === 'READONLY' || $state === 'INVALID') {
        $sev = 'bad'; $url = '/billing';
        $head = $state === 'INVALID' ? 'Licence key not accepted' : 'Subscription expired — the app is read-only';
        $detail = (string)$s['err'];
    } elseif ($state === 'GRACE') {
        $sev = 'bad'; $url = '/billing';
        $head = 'Subscription expired — still working during a short grace period';
        $detail = 'Renew now to avoid the app going read-only.' . ($s['expires'] ? ' Ended ' . fdate((string)$s['expires']) . '.' : '');
    } elseif ($state === 'TRIAL') {
        if ($days !== null && (int)$days <= 5) { $sev = 'warn'; $url = '/billing';
            $head = 'Trial ending in ' . max(0, (int)$days) . ' day(s)';
            $detail = 'Add a licence or subscription to keep working.'; }
    } elseif ($state === 'VALID') {
        if ($days !== null && (int)$days <= 30) { $sev = 'warn'; $url = '/billing';
            $head = 'Subscription renews in ' . max(0, (int)$days) . ' day(s)';
            $detail = 'Renew before it lapses to avoid any interruption.'; }
    }
    // Seat pressure — independent of the expiry state.
    if ($overSeat) {
        if ($sev !== 'bad') { $sev = 'bad'; $url = '/billing'; }
        $head = ($head ? $head . ' · ' : '') . 'Over the licensed seat count';
        $detail = trim($detail . ' ' . $used . ' active users against ' . $seats . ' seats.');
    } elseif ($atSeat && $sev === 'ok') {
        $sev = 'warn'; $url = '/billing';
        $head = 'At the licensed seat limit';
        $detail = $used . ' of ' . $seats . ' seats used — the next new user will be refused.';
    }
    // Auto-renew failing (never downgrade a bad verdict, but add the note).
    if ($syncErr !== '') {
        if ($sev === 'ok') { $sev = 'warn'; $head = 'Automatic licence renewal is failing'; $url = '/licence'; }
        $detail = trim($detail . ' Auto-renew error: ' . $syncErr);
    }

    return ['needs_attention' => $sev !== 'ok', 'severity' => $sev, 'state' => $state,
            'headline' => $head, 'detail' => $detail, 'url' => $url,
            'days_left' => $days, 'used' => $used, 'seats' => $seats,
            'over_seat' => $overSeat, 'at_seat' => $atSeat, 'sync_error' => $syncErr];
}

// Module 36 — a nudge before a licence/subscription lapse forces read-only or the
// next new user is refused. Reads the already-computed licence_health() verdict and
// pushes the same message; it emails, it never changes enforcement. The at-most-
// weekly guard lives in cron.php, like the audit and controlled-doc reminders.
function licence_run_reminders($today = null) {
    if (!function_exists('licence_health')) return 0;
    $h = licence_health();
    $approaching = in_array($h['state'], ['VALID', 'TRIAL'], true)
                 && $h['days_left'] !== null && (int)$h['days_left'] <= 14;
    if (empty($h['needs_attention']) && !$approaching) return 0;
    $to = getenv('QAC_EMAIL') ?: (function_exists('coordinator_emails') ? coordinator_emails() : '');
    if (trim((string)$to) === '') return 0;
    $body = ($h['headline'] ?: 'Licence / subscription attention') . "\n\n" . ($h['detail'] ?: '')
          . "\n\nOpen Licence & billing in " . (function_exists('app_name') ? app_name() : 'the app')
          . " to renew or adjust seats.\n";
    lk_try(fn() => ops_mail($to, 'Licence & subscription: ' . ($h['headline'] ?: 'attention needed'), $body, '', 'licence'), null);
    return 1;
}

function ops_licence($route, $method) {
    ops_require(lk_can_manage(), 'You cannot see the licence.');

    // The provider's public key — pasted once on a customer install so it can
    // verify the keys the provider signs for it. (Not needed where the provider
    // ships it as LICENCE_PUBKEY, or on the provider's own licence server.)
    if ($route === 'licence-pubkey' && $method === 'POST') {
        $pub = trim((string)($_POST['pubkey'] ?? ''));
        setting_set('licence_pubkey', $pub);
        lk_state(true);
        flash($pub === '' ? 'Provider key cleared.' : 'Provider key saved.');
        redirect('/licence');
    }

    // Somebody who has just paid will press a button rather than wait for the
    // next scheduled check. Making them wait is the whole complaint this
    // machinery exists to answer.
    if ($route === 'licence-check' && $method === 'POST') {
        if (!function_exists('licsync_checkin')) { flash('Automatic renewal is not available on this installation.', 'error'); redirect('/licence'); }
        $r = licsync_checkin(true);
        flash($r['msg'], !empty($r['ok']) ? (!empty($r['changed']) ? 'success' : 'info') : 'error');
        redirect('/licence');
    }

    if ($route === 'licence-save' && $method === 'POST') {
        $key = trim((string)($_POST['key'] ?? ''));
        // Whitespace is how a pasted key arrives from an e-mail client.
        $key = preg_replace('/\s+/', '', $key);
        if ($key === '') {
            setting_set('licence_key', '');
            lk_state(true);
            flash('Licence key removed.', 'warning');
            redirect('/licence');
        }
        $c = lk_verify($key);
        if (!empty($c['err'])) { flash($c['err'], 'error'); redirect('/licence'); }
        setting_set('licence_key', $key);
        lk_mark_enforced();   // first key makes this a licensed copy, for good
        lk_state(true);
        licence_disabled(true);
        flash('Licence accepted — ' . ($c['cust'] ?? 'this installation') . ', to ' . fdate((string)$c['exp']) . '.', 'success');
        redirect('/licence');
    }

    view('ops/licence', ['s' => lk_summary(), 'canManage' => lk_can_manage(),
                         'states' => LICENCE_STATES,
                         'sync' => function_exists('licsync_status') ? licsync_status() : ['on' => false]]);
    return true;
}
