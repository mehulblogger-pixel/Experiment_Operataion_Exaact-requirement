<?php
// Shared helpers: escaping, auth, GST logic, choice lists, flash messages.

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function redirect($path) { header("Location: $path"); exit; }

// --- Flash messages ---
function flash($text, $tag = 'success') {
    $_SESSION['flash'][] = ['text' => $text, 'tag' => $tag];
}
function take_flash() {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// --- Auth ---
function current_user() {
    if (empty($_SESSION['uid'])) return null;
    static $u = null;
    if ($u === null) {
        $q = db()->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
        $q->execute([$_SESSION['uid']]);
        $u = $q->fetch() ?: null;
    }
    return $u;
}
function require_login() { if (!current_user()) redirect('login'); }
function user_name($u) {
    $n = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
    return $n !== '' ? $n : $u['username'];
}

// --- GST helpers ---
const GST_STATES = [
    '01'=>'Jammu & Kashmir','02'=>'Himachal Pradesh','03'=>'Punjab','04'=>'Chandigarh',
    '05'=>'Uttarakhand','06'=>'Haryana','07'=>'Delhi','08'=>'Rajasthan','09'=>'Uttar Pradesh',
    '10'=>'Bihar','11'=>'Sikkim','12'=>'Arunachal Pradesh','13'=>'Nagaland','14'=>'Manipur',
    '15'=>'Mizoram','16'=>'Tripura','17'=>'Meghalaya','18'=>'Assam','19'=>'West Bengal',
    '20'=>'Jharkhand','21'=>'Odisha','22'=>'Chhattisgarh','23'=>'Madhya Pradesh','24'=>'Gujarat',
    '25'=>'Daman & Diu','26'=>'Dadra & Nagar Haveli','27'=>'Maharashtra','28'=>'Andhra Pradesh (Old)',
    '29'=>'Karnataka','30'=>'Goa','31'=>'Lakshadweep','32'=>'Kerala','33'=>'Tamil Nadu',
    '34'=>'Puducherry','35'=>'Andaman & Nicobar','36'=>'Telangana','37'=>'Andhra Pradesh','38'=>'Ladakh',
];
function clean_gstin($g) { return strtoupper(preg_replace('/\s+/', '', trim((string)$g))); }
function pan_from_gstin($g) {
    $g = clean_gstin($g);
    if (strlen($g) >= 12) {
        $pan = substr($g, 2, 10);
        if (preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan)) return $pan;
    }
    return '';
}
function state_from_gstin($g) { $g = clean_gstin($g); return lk_options_or('gst_state', GST_STATES)[substr($g, 0, 2)] ?? ''; }
function is_valid_gstin($g) { return (bool)preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', clean_gstin($g)); }
function normalize_name($name) {
    $n = strtolower((string)$name);
    $n = preg_replace('/^m\/?s\.?\s+/', '', $n);
    $n = preg_replace('/[^a-z0-9 ]/', ' ', $n);
    $n = preg_replace('/\b(private|pvt|limited|ltd|llp|company|co)\b/', ' ', $n);
    return trim(preg_replace('/\s+/', ' ', $n));
}
function short_token($name) {
    $first = explode(' ', normalize_name($name))[0] ?? 'PARTNER';
    $t = preg_replace('/[^A-Z0-9]/', '', strtoupper($first));
    return substr($t, 0, 8) ?: 'PARTNER';
}

// --- Choice lists (labels) ---
const CLIENT_TYPES = ['OWNER'=>'Owner / End Client','EPC'=>'EPC Contractor','PMC'=>'PMC / Project Management','CONSULTANT'=>'Consultant','MANUFACTURER'=>'Manufacturer / Supplier','TRADER'=>'Trader / Distributor','SUBVENDOR'=>'Sub-vendor','OTHER'=>'Other'];
const INDUSTRIES = ['POWER'=>'Power / Transmission','RENEWABLE'=>'Renewable (Solar / Wind)','OIL_GAS'=>'Oil & Gas','WATER'=>'Water & Infrastructure','MINING'=>'Mining & Metals','STEEL'=>'Steel','CEMENT'=>'Cement','MANUFACTURING'=>'Manufacturing','INFRA'=>'Infrastructure / Construction','RAIL'=>'Railways','CHEMICAL'=>'Chemical / Fertiliser','OTHER'=>'Other'];
const OWNERSHIP = ['PVT_LTD'=>'Private Limited','PUB_LTD'=>'Public Limited','LLP'=>'LLP','PARTNERSHIP'=>'Partnership','PROPRIETOR'=>'Proprietorship','PSU'=>'Government / PSU','TRUST'=>'Trust / Society','MNC'=>'MNC / Foreign','OTHER'=>'Other'];
const STATUSES = ['ACTIVE'=>'Active','INACTIVE'=>'Inactive','ON_HOLD'=>'On hold','BLACKLISTED'=>'Blacklisted','PROSPECT'=>'Prospect'];
const ADDRESS_TYPES = ['REGISTERED'=>'Registered Office','CORPORATE'=>'Corporate Office','BRANCH'=>'Branch Office','PURCHASE'=>'Purchase Office','BILLING'=>'Billing Address','PLANT'=>'Plant','FACTORY'=>'Factory','WAREHOUSE'=>'Warehouse','PROJECT_SITE'=>'Project Site','SITE_OFFICE'=>'Site Office'];
const REG_TYPES = ['GSTIN'=>'GSTIN','PAN'=>'PAN','TAN'=>'TAN','CIN'=>'CIN','MSME'=>'MSME / Udyam','ISO'=>'ISO Certificate','PQ'=>'Pre-Qualification','OTHER'=>'Other'];
const PO_TYPES = ['REGULAR'=>'Regular (fixed value)','OPEN'=>'Open order (no PO / ARC)','ARC'=>'ARC / Rate contract'];
// A PO line is charged in the same units as everything else — see CHARGE_UNITS.
const PO_ITEM_TYPES = ['MANDAY'=>'Man-day','MANMONTH'=>'Man-month','AUDIT_DAY'=>'Audit day','VISIT'=>'Per visit','LOT'=>'Per lot / lump sum','DOC'=>'Per document','OTHER'=>'Other'];
const REL_TYPES = ['SUBSIDIARY'=>'Subsidiary of','JV'=>'Joint Venture with','CONSORTIUM'=>'Consortium with','EPC_FOR'=>'EPC Contractor for','CONSULTANT_FOR'=>'Consultant for','SUPPLIER_TO'=>'Supplier to','OTHER'=>'Other'];

function partner_name($p) { return $p['display_name'] !== '' ? $p['display_name'] : $p['legal_name']; }
function roles_label($p) {
    $r = [];
    if ($p['is_client']) $r[] = 'Client';
    if ($p['is_vendor']) $r[] = 'Vendor';
    if ($p['is_subcontractor']) $r[] = 'Sub-contractor';
    return $r ? implode(' / ', $r) : '—';
}

// ---------------------------------------------------------------------------
//  One submission, one record
//
//  A form submitted once was being recorded twice. The POST is replayed for
//  reasons that have nothing to do with the person: a double-click, a browser
//  retry when a response is slow, the back button and a refresh, and — the one
//  that caused this — the offline queue re-sending an entry whose reply never
//  arrived even though the server had already saved it. An inspector's expenses
//  appeared as two identical lines.
//
//  So each form carries a one-shot ticket. The first POST spends it; any replay
//  presents a ticket that is already spent and is turned away before the handler
//  runs. A form with no ticket at all — an older page, or a browser with no
//  JavaScript — is let through exactly as before, so nothing that worked stops
//  working.
// ---------------------------------------------------------------------------
function form_tokens_migrate() {
    static $done = false;
    if ($done) return; $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS form_tokens (
            token VARCHAR(64) PRIMARY KEY, used_at VARCHAR(30) DEFAULT '')");
    } catch (Throwable $e) {}
}
// True the first time a ticket is presented, false for every replay of it.
function form_token_spend($token) {
    $token = trim((string)$token);
    if ($token === '' || strlen($token) > 64) return true;   // no ticket: behave as before
    try {
        db()->prepare("INSERT INTO form_tokens (token, used_at) VALUES (?, ?)")
            ->execute([$token, date('c')]);
    } catch (Throwable $e) {
        return false;   // primary-key clash: this exact submission has been through already
    }
    // Keep the table from growing without bound; a ticket older than a day
    // cannot be replayed by anything we care about.
    try {
        if (random_int(1, 200) === 1)
            db()->prepare("DELETE FROM form_tokens WHERE used_at < ?")
                ->execute([date('c', time() - 86400)]);
    } catch (Throwable $e) {}
    return true;
}
