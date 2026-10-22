<?php
// ============================================================================
//  The platform's commercial numbers — ONE catalogue (owner, 22 Oct 2026:
//  "research this number and let me know or keep it open to configure in
//  admin panel, don't hardcode it").
//
//  Rules:
//   • Nothing that decides what a customer PAYS is fixed in code. A price that
//     has not been saved is "not set", and what it prices is simply not for
//     sale yet — never sold at an invented number.
//   • Every number is a setting the platform owner saves on Super Admin →
//     Plans, pricing & usage (or the Companies panels for storage and trial).
//   • Beside each one the screen shows a SUGGESTED value from market research
//     (22 Oct 2026, docs/pricing-research.md) and says whether it is evidence
//     or judgement. A suggestion is never used by itself: the owner fills the
//     form with it ("Fill in the suggested values") and presses Save.
//
//  Tax: GST is OFF until the owner switches it on with the platform's GSTIN.
//  When on, it is added to (or, if the owner chooses, included in) the amount
//  charged, split CGST+SGST or IGST by place of supply, rounded to the rupee
//  (s.170 CGST Act), and a tax invoice is issued for every payment.
// ============================================================================

// key => [group, label, unit, suggested value ('' = no suggestion), basis]
// unit: money (₹ per month unless the label says otherwise), pct, int, gb, mb, text, choice
function commercial_catalogue() {
    $c = [
        'billing_price_user_month'  => ['price', 'Each login (person) — per month', 'money', '499',
            'Judgement: a login is the "per user" part; Indian single-product tools charge ₹840–1,800 per user, so ₹499 per login plus modules lands a 5-module company near ₹2,500 per user — below Zoho One Flexible (₹3,000–3,500).'],
        'billing_price_user_year'   => ['price', 'Each login (person) — per year', 'money', '',
            'Leave blank to use the yearly discount below (₹499 × 12 − 20% = ₹4,790).'],
        'saas_yearly_discount_pct'  => ['price', 'Yearly discount when a yearly price is left blank', 'pct', '20',
            'Evidence: GoAudits and Zoho Inventory give 20% for yearly; Freshdesk, Salesforce and SafetyCulture about 17%.'],
        'saas_storage_pack_price_month' => ['price', 'Extra storage pack — per month', 'money', '399',
            'Judgement: 50 GB for ₹399 ≈ ₹8 per GB — between Zoho Meeting (≈ ₹6/GB) and Zoho CRM ($4 per 5 GB).'],
        'saas_storage_pack_price_year'  => ['price', 'Extra storage pack — per year', 'money', '', 'Leave blank to use the yearly discount.'],
        'ai_pack_price'             => ['price', 'AI top-up pack — price', 'money', '', 'No market figure found — your decision. Blank = top-ups are not sold.'],
        'billing_price_field_month' => ['price', 'Field seat (inspector) — per month, control-panel seat view', 'money', '', 'No market figure found — your decision.'],
        'billing_price_portal_month'=> ['price', 'Portal seat (client / vendor login) — per month, control-panel seat view', 'money', '', 'No market figure found — your decision.'],
        'billing_portal_free'       => ['price', 'Portal seats included free', 'int', '', 'Your decision.'],

        'saas_storage_gb_base'      => ['storage', 'Storage every company gets', 'gb', '10', 'Evidence: Zoho FSM Standard gives 10 GB per organisation.'],
        'saas_storage_gb_per_login' => ['storage', 'Extra storage per paid login', 'gb', '2',
            'Judgement: about a tenth of Google Workspace (30 GB per user), as EXAACT is not a file store. A 10-login company gets 30 GB.'],
        'saas_storage_pack_gb'      => ['storage', 'Size of one extra storage pack', 'gb', '50', 'Judgement (see the pack price).'],

        'saas_trial_days'           => ['trial', 'Free trial length', 'int', '30', 'Your decision (22 Oct 2026); Zoho One, SafetyCulture and FieldEZ also give 30 days.'],
        'saas_trial_seats'          => ['trial', 'Logins during the trial', 'int', '10', 'Evidence: Google Workspace and Zoho FSM limit trials to 10 users.'],
        'saas_trial_storage_mb'     => ['trial', 'Storage during the trial', 'mb', '2048',
            'Judgement: a busy company adds ≈ 3 GB a month; 2 GB covers a real trial and stops abuse.'],
        'saas_inactive_notice_days' => ['trial', 'Warning e-mail every … days to an unused trial', 'int', '15', 'Your decision (22 Oct 2026).'],
        'saas_inactive_delete_days' => ['trial', 'Delete a never-paid, unused trial after … days', 'int', '90',
            'Your decision (22 Oct 2026). For comparison: Freshdesk 14 days, Zoho 120–180 days.'],

        'plat_gst_enabled'          => ['tax', 'Charge GST', 'choice', '', 'Switch on only once the platform is GST-registered (needs your GSTIN below).'],
        'plat_gst_rate'             => ['tax', 'GST rate', 'pct', '18', 'Evidence: software / SaaS services are 18%; unchanged by the Sept 2025 GST reform.'],
        'plat_gst_mode'             => ['tax', 'Prices are', 'choice', 'exclusive', 'Evidence: Indian B2B SaaS shows prices "+ 18% GST" (Zoho, Freshdesk, Google partners).'],
        'plat_gst_sac'              => ['tax', 'SAC code', 'text', '997331', 'Most sources give 997331; some give 998315 — confirm with your chartered accountant.'],
        'plat_legal_name'           => ['tax', 'Your legal (registered) name', 'text', '', 'As on your GST certificate.'],
        'plat_gstin'                => ['tax', 'Your GSTIN', 'text', '', 'Its first two digits are your state.'],
        'plat_address'              => ['tax', 'Your registered address', 'text', '', 'Printed on every tax invoice.'],
    ];
    // One price per sellable module, monthly and yearly.
    if (defined('PRODUCT_MODULES')) {
        $mods = [];
        foreach (PRODUCT_MODULES as $k => $m) {
            if (!empty($m[3])) continue;   // core administration is included, never priced
            $mods['saas_price_mod_' . $k . '_month'] = ['price', ($m[0] ?? $k) . ' module — per company, per month', 'money', '999',
                'Evidence/judgement: Zoho Inventory, priced per organisation, is ₹999 (yearly) – ₹1,199 (monthly).'];
            $mods['saas_price_mod_' . $k . '_year'] = ['price', ($m[0] ?? $k) . ' module — per company, per year', 'money', '', 'Leave blank to use the yearly discount.'];
        }
        $c = array_slice($c, 0, 3, true) + $mods + array_slice($c, 3, null, true);
    }
    return $c;
}

// The saved value, or null when the owner has not saved one. Never a default.
function commercial_val($key) {
    if (!function_exists('setting_get')) return null;
    $v = trim((string) setting_get($key, ''));
    return $v === '' ? null : $v;
}
// A price: a whole positive number, or null (= not set, not for sale).
function commercial_price($key) {
    $v = commercial_val($key);
    return $v !== null && (int) $v > 0 ? (int) $v : null;
}
// The yearly price for an item: its own yearly price if saved; otherwise the
// monthly price × 12 less the saved yearly discount; otherwise null (yearly not offered).
function commercial_yearly($monthKey, $yearKey) {
    $y = commercial_price($yearKey);
    if ($y !== null) return $y;
    $m = commercial_price($monthKey);
    $d = commercial_val('saas_yearly_discount_pct');
    if ($m === null || $d === null) return null;
    $d = max(0, min(90, (float) $d));
    return (int) round($m * 12 * (1 - $d / 100));
}

// ---- GST -------------------------------------------------------------------------
function commercial_gst_cfg() {
    $gstin = function_exists('clean_gstin') ? clean_gstin((string) commercial_val('plat_gstin')) : strtoupper(trim((string) commercial_val('plat_gstin')));
    $rate = commercial_val('plat_gst_rate');
    return [
        'on'    => commercial_val('plat_gst_enabled') === '1' && $gstin !== '' && $rate !== null && (float) $rate > 0,
        'rate'  => $rate !== null ? (float) $rate : 0.0,
        'mode'  => commercial_val('plat_gst_mode') === 'inclusive' ? 'inclusive' : 'exclusive',
        'sac'   => (string) commercial_val('plat_gst_sac'),
        'gstin' => $gstin,
        'state' => substr($gstin, 0, 2),
        'name'  => (string) commercial_val('plat_legal_name'),
        'address' => (string) commercial_val('plat_address'),
    ];
}
// Tax on an amount. Place of supply (s.12(2) IGST Act): the buyer's state —
// from its GSTIN when registered, else the state it gave for its address —
// and only when neither is known, the platform's own state.
// Returns net (taxable value), tax, gross (what is charged), and the split.
function commercial_tax($amount, $buyerGstin = '', $buyerState = '') {
    $amount = max(0, (int) $amount);
    $g = commercial_gst_cfg();
    $out = ['on' => $g['on'], 'rate' => $g['rate'], 'mode' => $g['mode'], 'net' => $amount, 'tax' => 0, 'gross' => $amount,
            'cgst' => 0, 'sgst' => 0, 'igst' => 0, 'pos' => '', 'buyer_gstin' => ''];
    if (!$g['on'] || $amount <= 0) return $out;
    $b = function_exists('clean_gstin') ? clean_gstin((string) $buyerGstin) : strtoupper(trim((string) $buyerGstin));
    $b = commercial_gstin_problem($b) === '' ? $b : '';
    $st = commercial_state_code($buyerState);
    $pos = $b !== '' ? substr($b, 0, 2) : ($st !== '' ? $st : $g['state']);
    if ($g['mode'] === 'inclusive') {
        $net = (int) round($amount / (1 + $g['rate'] / 100));
        $tax = $amount - $net; $gross = $amount;
    } else {
        $net = $amount; $tax = (int) round($amount * $g['rate'] / 100); $gross = $amount + $tax;
    }
    $out = array_merge($out, ['net' => $net, 'tax' => $tax, 'gross' => $gross, 'buyer_gstin' => $b]);
    return commercial_tax_split($out, $pos);
}
// Split a tax amount by place of supply: same state as the platform → CGST +
// SGST; any other → IGST. Used again when an invoice's buyer is corrected.
function commercial_tax_split(array $t, $pos) {
    $home = commercial_gst_cfg()['state'];
    $t['pos'] = (string) $pos;
    $t['cgst'] = $t['sgst'] = $t['igst'] = 0;
    if ($t['pos'] === $home) { $t['cgst'] = intdiv((int) $t['tax'], 2); $t['sgst'] = (int) $t['tax'] - $t['cgst']; }
    else $t['igst'] = (int) $t['tax'];
    return $t;
}

// ---- Checking what is typed ------------------------------------------------------
// A GSTIN: 2-digit state code, PAN, entity number, 'Z', and a check character
// computed from the first 14 (the GSTN algorithm), so a mistyped character is
// caught before it reaches a tax invoice. '' = fine, else the reason.
function commercial_gstin_problem($g) {
    $g = function_exists('clean_gstin') ? clean_gstin((string) $g) : strtoupper(trim((string) $g));
    if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $g))
        return 'A GSTIN is 15 characters, like 24AAACE1234F1Z5 — please check it.';
    if (defined('GST_STATES') && !isset(GST_STATES[substr($g, 0, 2)]))
        return 'The GSTIN\'s first two digits are not an Indian state code.';
    if (commercial_gstin_check_char(substr($g, 0, 14)) !== $g[14])
        return 'That GSTIN has a typing mistake (its last character does not match the rest) — please copy it again from the GST certificate.';
    return '';
}
function commercial_gstin_check_char($first14) {
    $cs = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'; $sum = 0;
    for ($i = 0; $i < 14; $i++) {
        $p = strpos($cs, $first14[$i]) * (($i % 2) ? 2 : 1);
        $sum += intdiv($p, 36) + $p % 36;
    }
    return $cs[(36 - $sum % 36) % 36];
}
// SAC codes are six digits beginning 99 (e.g. 997331).
function commercial_sac_ok($sac) { return (bool) preg_match('/^99[0-9]{4}$/', trim((string) $sac)); }
// A state as its two-digit GST code, from a code or a state name ('' if unknown).
function commercial_state_code($s) {
    $s = trim((string) $s);
    if ($s === '' || !defined('GST_STATES')) return '';
    if (isset(GST_STATES[$s])) return $s;
    foreach (GST_STATES as $code => $name) if (strcasecmp($name, $s) === 0) return (string) $code;
    return '';
}

// The settings each hosted company needs a copy of to price and tax its own
// checkout (copied nightly and on "Save & push"): the prices and the GST details.
function commercial_push_keys() {
    $k = ['billing_currency', 'plat_invoice_prefix'];
    foreach (commercial_catalogue() as $key => $c) if ($c[0] === 'price' || $c[0] === 'tax') $k[] = $key;
    return array_values(array_unique($k));
}

// For a settings form: the suggestion as an attribute on the input, and the
// one-line "Suggested: … · why" under it. A "Fill in the suggested values"
// button (class cm-fill) copies them into the EMPTY boxes of its own form.
function commercial_suggest_attr($key) {
    $c = commercial_catalogue()[$key] ?? null;
    return $c && $c[3] !== '' ? ' data-suggest="' . htmlspecialchars($c[3], ENT_QUOTES) . '"' : '';
}
function commercial_hint($key) {
    $c = commercial_catalogue()[$key] ?? null;
    if (!$c) return '';
    return '<div class="cm-hint" style="font-size:11.5px;color:var(--muted,#656e7a);margin-top:3px;line-height:1.35" title="' . htmlspecialchars($c[4], ENT_QUOTES) . '">'
        . ($c[3] !== '' ? 'Suggested: <b>' . htmlspecialchars($c[3], ENT_QUOTES) . '</b> · ' : '') . htmlspecialchars($c[4], ENT_QUOTES) . '</div>';
}

// ---- What is still to decide (go-live checklist) -----------------------------------
// Prices needed before anything can be bought: a login price and at least one module.
function commercial_prices_ready() {
    if (commercial_price('billing_price_user_month') === null) return false;
    if (defined('PRODUCT_MODULES')) foreach (PRODUCT_MODULES as $k => $m)
        if (empty($m[3]) && commercial_price('saas_price_mod_' . $k . '_month') !== null) return true;
    return false;
}
// Labels of catalogue entries that have a suggestion but no saved value.
function commercial_unset() {
    $out = [];
    foreach (commercial_catalogue() as $k => $c) if ($c[3] !== '' && commercial_val($k) === null) $out[$k] = $c[1];
    return $out;
}

// ---- The tax-invoice number: one sequence across the whole platform ----------------
// Every company's payment is invoiced by the PLATFORM, so the numbers must run
// in one series (Rule 46: consecutive, unique per financial year) whichever
// company's database the payment lands in. Kept in the protected data folder
// under a file lock.
function commercial_fy($date = null) {
    $t = strtotime($date ?: date('Y-m-d'));
    $y = (int) date('Y', $t); if ((int) date('n', $t) < 4) $y--;
    return $y . '-' . substr((string) ($y + 1), 2);
}
function commercial_next_invoice_no($date = null) {
    $dir = function_exists('tenant_safe_data_dir') ? (string) tenant_safe_data_dir()[0] : sys_get_temp_dir();
    $f = rtrim($dir, '/') . '/invoice-series.json';
    $fy = commercial_fy($date);
    $h = @fopen($f, 'c+');
    if (!$h) return '';
    try {
        flock($h, LOCK_EX);
        $raw = stream_get_contents($h);
        $d = json_decode((string) $raw, true); if (!is_array($d)) $d = [];
        $n = (int) ($d[$fy] ?? 0) + 1; $d[$fy] = $n;
        ftruncate($h, 0); rewind($h); fwrite($h, json_encode($d)); fflush($h);
        flock($h, LOCK_UN);
    } finally { fclose($h); }
    @chmod($f, 0600);
    $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) (commercial_val('plat_invoice_prefix') ?? 'EX'))) ?: 'EX';
    return $prefix . '/' . $fy . '/' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);   // ≤ 16 characters, as Rule 46 requires
}

// ---- The platform's GST invoice register ------------------------------------------
// One register across every company, for the platform owner's GST return: each
// tax invoice is written here when it is issued, in the protected data folder
// (a company's payment lands in the company's own database, which the owner's
// screens should not have to open one by one). Append-only: a correction to an
// invoice's buyer is a new line for the same number, with what changed, when,
// by whom and why — the last line for a number is the invoice as it stands.
function commercial_register_file() {
    $dir = function_exists('tenant_safe_data_dir') ? (string) tenant_safe_data_dir()[0] : sys_get_temp_dir();
    return rtrim($dir, '/') . '/gst-invoice-register.jsonl';
}
function commercial_register_add(array $row) {
    $f = commercial_register_file();
    $h = @fopen($f, 'a');
    if (!$h) return false;
    try { flock($h, LOCK_EX); fwrite($h, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"); fflush($h); flock($h, LOCK_UN); }
    finally { fclose($h); }
    @chmod($f, 0600);
    return true;
}
// Every invoice as it stands now, newest first.
function commercial_register_all() {
    $f = commercial_register_file();
    if (!is_file($f)) return [];
    $by = [];
    foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $r = json_decode($line, true);
        if (is_array($r) && !empty($r['invoice_no'])) $by[$r['invoice_no']] = $r;
    }
    uasort($by, fn($a, $b) => strcmp((string) $b['date'] . $b['invoice_no'], (string) $a['date'] . $a['invoice_no']));
    return $by;
}
function commercial_register_get($no) { return commercial_register_all()[(string) $no] ?? null; }

// The invoice row for a paid order (built from what the order recorded).
function commercial_invoice_row(array $o, array $t, $companyKey = '') {
    $buyer = (array) ($t['buyer'] ?? []);
    return [
        'invoice_no' => (string) $o['invoice_no'], 'date' => (string) ($o['invoiced_at'] ?: substr((string) $o['paid_at'], 0, 10)),
        'company_key' => (string) $companyKey, 'order_id' => (string) $o['order_id'], 'payment_id' => (string) $o['payment_id'],
        'period' => (string) $o['period'], 'lines' => array_values((array) ($t['lines'] ?? [])), 'credit' => (int) ($t['credit'] ?? 0),
        'rate' => (float) ($t['rate'] ?? 0), 'mode' => (string) ($t['mode'] ?? 'exclusive'), 'net' => (int) ($t['net'] ?? 0), 'tax' => (int) ($t['tax'] ?? 0),
        'cgst' => (int) ($t['cgst'] ?? 0), 'sgst' => (int) ($t['sgst'] ?? 0), 'igst' => (int) ($t['igst'] ?? 0),
        'total' => (int) $o['amount'], 'pos' => (string) ($t['pos'] ?? ''),
        'buyer' => ['name' => (string) ($buyer['name'] ?? ''), 'gstin' => (string) ($buyer['gstin'] ?? ''),
                    'address' => (string) ($buyer['address'] ?? ''), 'state' => (string) ($buyer['state'] ?? '')],
        'supplier' => (array) ($t['supplier'] ?? []), 'sac' => (string) (($t['supplier'] ?? [])['sac'] ?? ''),
        'amendments' => [],
    ];
}

// Correct an issued invoice's buyer (name, GSTIN, state, address) — e.g. a
// customer who paid without its GSTIN and now needs it on the bill. The amount
// and tax stay exactly as charged; only the split follows the new place of
// supply. Returns '' or the reason it was refused.
function commercial_register_amend($no, array $buyer, $reason, $by) {
    $r = commercial_register_get($no);
    if (!$r) return 'That invoice is not in the register.';
    $reason = trim((string) $reason);
    if ($reason === '') return 'Say why the invoice is being corrected (it is kept with the invoice).';
    $b = commercial_billto_clean($buyer);
    if ($b['error'] !== '') return $b['error'];
    $old = $r['buyer'];
    $new = $b['buyer'];
    if ($old == $new) return 'Nothing was changed.';
    $pos = $new['gstin'] !== '' ? substr($new['gstin'], 0, 2) : ($new['state'] !== '' ? $new['state'] : (string) ($r['supplier']['state'] ?? ''));
    $split = commercial_tax_split(['tax' => (int) $r['tax']], $pos);
    $r['amendments'][] = ['at' => date('c'), 'by' => (string) $by, 'reason' => mb_substr($reason, 0, 300), 'was' => $old, 'pos_was' => $r['pos']];
    $r['buyer'] = $new;
    foreach (['pos', 'cgst', 'sgst', 'igst'] as $k) $r[$k] = $split[$k];
    commercial_register_add($r);
    return '';
}

// The "Bill to" a company types at checkout, checked. GSTIN optional; when
// given it must be a real one, and its state wins over the state chosen.
// Returns ['buyer' => [name, gstin, address, state(code)], 'error' => ''].
function commercial_billto_clean(array $in) {
    $name  = mb_substr(trim((string) ($in['name'] ?? '')), 0, 200);
    $gstin = function_exists('clean_gstin') ? clean_gstin((string) ($in['gstin'] ?? '')) : strtoupper(trim((string) ($in['gstin'] ?? '')));
    $addr  = mb_substr(trim((string) ($in['address'] ?? '')), 0, 500);
    $state = commercial_state_code((string) ($in['state'] ?? ''));
    $out = ['buyer' => ['name' => $name, 'gstin' => $gstin, 'address' => $addr, 'state' => $state], 'error' => ''];
    if ($name === '') { $out['error'] = 'Enter the name to bill (your registered business name).'; return $out; }
    if ($gstin !== '') {
        $why = commercial_gstin_problem($gstin);
        if ($why !== '') { $out['error'] = 'GSTIN: ' . $why; return $out; }
        $out['buyer']['state'] = substr($gstin, 0, 2);
    }
    return $out;
}

// ---- The screen: /gst-invoices (platform owner) -------------------------------------
// Every tax invoice the platform has issued, by month, with totals and a CSV for
// the accountant (GSTR-1: B2B lines carry the buyer's GSTIN; B2C lines do not),
// one invoice opened for printing, and a buyer correction with its reason.
function ops_gst_invoices($method) {
    ops_require(function_exists('superadmin_can') && superadmin_can(), 'Only the platform owner (Super Admin) can see the GST invoice register.');
    $no = trim((string) ($_GET['no'] ?? $_POST['no'] ?? ''));
    if ($method === 'POST' && ($_POST['do'] ?? '') === 'amend') {
        $u = function_exists('current_user') ? current_user() : [];
        $err = commercial_register_amend($no, ['name' => $_POST['name'] ?? '', 'gstin' => $_POST['gstin'] ?? '',
            'state' => $_POST['state'] ?? '', 'address' => $_POST['address'] ?? ''], $_POST['reason'] ?? '',
            function_exists('user_name') ? user_name($u) : 'platform owner');
        if ($err === '' && function_exists('idems_log')) idems_log('user', $u['id'] ?? null, 'GST_INVOICE_CORRECTED', ['field' => $no]);
        flash($err === '' ? 'Invoice ' . $no . ' corrected. The customer sees the corrected invoice from their Payment history; include it as an amendment in your next GST return.' : $err,
              $err === '' ? 'success' : 'error');
        redirect('/gst-invoices?no=' . rawurlencode($no));
    }
    if ($no !== '' && isset($_GET['print'])) {
        $inv = commercial_register_get($no);
        ops_require((bool) $inv, 'That invoice is not in the register.');
        require __DIR__ . '/../views/ops/subscription_invoice.php';
        exit;
    }
    $month = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['m'] ?? '')) ? (string) $_GET['m'] : date('Y-m');
    $all = commercial_register_all();
    $rows = array_values(array_filter($all, fn($r) => substr((string) $r['date'], 0, 7) === $month));
    if (isset($_GET['csv'])) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="gst-invoices-' . $month . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Invoice no', 'Date', 'Type', 'Buyer GSTIN', 'Buyer name', 'Place of supply', 'SAC', 'Taxable value', 'Rate %',
                       'IGST', 'CGST', 'SGST', 'Invoice total', 'Workspace', 'Payment ref', 'Corrected']);
        foreach ($rows as $r) {
            $pos = (string) $r['pos'];
            fputcsv($out, [$r['invoice_no'], $r['date'], $r['buyer']['gstin'] !== '' ? 'B2B' : 'B2C', $r['buyer']['gstin'], $r['buyer']['name'],
                $pos . (defined('GST_STATES') && isset(GST_STATES[$pos]) ? '-' . GST_STATES[$pos] : ''), $r['sac'], $r['net'], $r['rate'],
                $r['igst'], $r['cgst'], $r['sgst'], $r['total'], $r['company_key'], $r['payment_id'], $r['amendments'] ? 'yes' : '']);
        }
        fclose($out);
        exit;
    }
    $months = array_values(array_unique(array_merge([date('Y-m')], array_map(fn($r) => substr((string) $r['date'], 0, 7), $all))));
    rsort($months);
    view('ops/gst_invoices', ['rows' => $rows, 'month' => $month, 'months' => $months, 'sel' => $no !== '' ? commercial_register_get($no) : null]);
    return true;
}
