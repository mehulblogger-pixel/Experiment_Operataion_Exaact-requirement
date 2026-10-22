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
// Tax on an amount. $buyerGstin decides the place of supply: the buyer's state
// when registered (s.12(2) IGST Act), else the platform's own state.
// Returns net (taxable value), tax, gross (what is charged), and the split.
function commercial_tax($amount, $buyerGstin = '') {
    $amount = max(0, (int) $amount);
    $g = commercial_gst_cfg();
    $out = ['on' => $g['on'], 'rate' => $g['rate'], 'mode' => $g['mode'], 'net' => $amount, 'tax' => 0, 'gross' => $amount,
            'cgst' => 0, 'sgst' => 0, 'igst' => 0, 'pos' => '', 'buyer_gstin' => ''];
    if (!$g['on'] || $amount <= 0) return $out;
    $b = function_exists('clean_gstin') ? clean_gstin((string) $buyerGstin) : strtoupper(trim((string) $buyerGstin));
    $b = preg_match('/^[0-9]{2}[A-Z0-9]{13}$/', $b) ? $b : '';
    $pos = $b !== '' ? substr($b, 0, 2) : $g['state'];
    if ($g['mode'] === 'inclusive') {
        $net = (int) round($amount / (1 + $g['rate'] / 100));
        $tax = $amount - $net; $gross = $amount;
    } else {
        $net = $amount; $tax = (int) round($amount * $g['rate'] / 100); $gross = $amount + $tax;
    }
    $out = array_merge($out, ['net' => $net, 'tax' => $tax, 'gross' => $gross, 'pos' => $pos, 'buyer_gstin' => $b]);
    if ($pos === $g['state']) { $out['cgst'] = intdiv($tax, 2); $out['sgst'] = $tax - $out['cgst']; }
    else $out['igst'] = $tax;
    return $out;
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
