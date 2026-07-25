<?php
// ============================================================================
//  Terminology — one place that decides what every business noun is called.
//
//  Why this file exists: the same idea used to be written three different ways
//  across the app ("Customer" in CRM, "Client" in Operations; "Quotation" on
//  one screen, "Quote" on the next; "Job" here, "Deputation" there). Screens
//  no longer hard-code those words. They call T()/TP() and get whatever the
//  company has decided the word is, from Settings → Terminology.
//
//  Vocabulary follows ISO/IEC 17020 and normal third-party-inspection usage.
//
//      T('call')   → "Inspection Call"   (singular, as a heading / label)
//      TP('call')  → "Inspection Calls"  (plural)
//      Tl('call')  → "inspection call"   (lower-case, mid-sentence)
//      Tlp('call') → "inspection calls"
//      T_REG('call') → "Inspection call register"   (the standard list heading)
//
//  Acronyms (IBO, SBU, IRN, BOSS…) are never lower-cased by Tl() — see
//  TERM_ACRONYMS. Overrides live in the single `terms` setting as JSON.
// ============================================================================

// key => [singular, plural, group, help]
const TERM_DEFAULTS = [
    // -- parties -------------------------------------------------------------
    'client'       => ['Client', 'Clients', 'Parties', 'The party that engages us and receives the report.'],
    'vendor'       => ['Vendor', 'Vendors', 'Parties', 'The party whose goods or works we inspect.'],
    'manufacturer' => ['Manufacturer', 'Manufacturers', 'Parties', 'The works that actually fabricates the item.'],
    'supplier'     => ['Supplier', 'Suppliers', 'Parties', 'The party that supplies the item to the client.'],
    'subvendor'    => ['Sub-vendor', 'Sub-vendors', 'Parties', "The manufacturer's own subcontractor."],
    // -- sales ---------------------------------------------------------------
    'inquiry'      => ['Inquiry', 'Inquiries', 'Sales', 'An enquiry received from a client.'],
    'quote'        => ['Quote', 'Quotes', 'Sales', 'Our commercial offer against an inquiry.'],
    'order'        => ['Order', 'Orders', 'Sales', 'A won quote registered as an order.'],
    // -- operations ----------------------------------------------------------
    'call'         => ['Inspection Call', 'Inspection Calls', 'Operations', 'The client’s request to inspect, with dates and location.'],
    'job'          => ['Deputation', 'Deputations', 'Operations', 'An inspection engineer put on a call.'],
    'engineer'     => ['Inspection Engineer', 'Inspection Engineers', 'Operations', 'The person who performs the inspection.'],
    'office'       => ['IBO', 'IBOs', 'Operations', 'Inspection branch office.'],
    'sbu'          => ['SBU', 'SBUs', 'Operations', 'Strategic business unit.'],
    'manday'       => ['Man-day', 'Man-days', 'Operations', 'One engineer for one working day.'],
    // -- reporting -----------------------------------------------------------
    'report'       => ['Report', 'Reports', 'Reporting', 'An inspection document we issue (IR, NCR, IRN, CoC…).'],
    'endorsement'  => ['Endorsement', 'Endorsements', 'Reporting', 'Our review and sign-off of a manufacturer’s record.'],
    'mfr_record'   => ['Manufacturer Record', 'Manufacturer Records', 'Reporting', 'MTC, NDT report, hydro test report and similar.'],
    // -- money ---------------------------------------------------------------
    'boss'         => ['BOSS Number', 'BOSS Numbers', 'Money', 'The contract / order number profitability is tracked against.'],
    'invoice'      => ['Invoice', 'Invoices', 'Money', 'A bill raised on the client.'],
    'voucher'      => ['Voucher', 'Vouchers', 'Money', 'The monthly statement of travelling expenses.'],
    // -- people --------------------------------------------------------------
    'user'         => ['User', 'Users', 'People', 'A login account.'],
    'candidate'    => ['Candidate', 'Candidates', 'People', 'A person being considered for hiring.'],
    'requisition'  => ['Requisition', 'Requisitions', 'People', 'Approved demand for a new position.'],
];

// Words that must keep their capitals when used mid-sentence.
const TERM_ACRONYMS = ['IBO','IBOs','SBU','SBUs','IRN','NCR','BOSS','MTC','NDT','FAT','SAT','PWHT','CoC','CV','CVs','AI','GST','TPI','QAP','PO','HSE','ARC'];

// Pass $set to replace the cached overrides (used right after saving).
function term_overrides($set = null) {
    static $ov = null;
    if ($set !== null) return $ov = $set;
    if ($ov === null) {
        $raw = setting_get('terms', '');
        $ov  = $raw ? (json_decode($raw, true) ?: []) : [];
    }
    return $ov;
}

// Raw lookup: $form is 0 = singular, 1 = plural.
function term_raw($key, $form = 0) {
    $ov = term_overrides();
    if (isset($ov[$key][$form]) && $ov[$key][$form] !== '') return $ov[$key][$form];
    return TERM_DEFAULTS[$key][$form] ?? ($key);
}
function T($key)   { return term_raw($key, 0); }
function TP($key)  { return term_raw($key, 1); }

// Lower-cased for mid-sentence use, but acronyms are left alone. A multi-word
// term only lower-cases the words that are not acronyms ("Inspection Call" →
// "inspection call"; "IBO" stays "IBO"; "BOSS Number" → "BOSS number").
function term_lower($s) {
    $out = [];
    foreach (explode(' ', $s) as $w) {
        $bare = trim($w, '(),.:');
        $out[] = in_array($bare, TERM_ACRONYMS, true) ? $w : mb_strtolower($w);
    }
    return implode(' ', $out);
}
function Tl($key)  { return term_lower(T($key)); }
function Tlp($key) { return term_lower(TP($key)); }

// Sentence-case a term for the START of a heading: first character upper,
// the rest as-is unless the whole word is an acronym.
function term_head($s) {
    $l = term_lower($s);
    $first = mb_substr($l, 0, 1); $rest = mb_substr($l, 1);
    $w0 = explode(' ', $l)[0];
    if (in_array(trim($w0, '(),.:'), TERM_ACRONYMS, true)) return $l;
    return mb_strtoupper($first) . $rest;
}

// ---- The standard heading builders ----------------------------------------
// Every list screen in the app is "<Thing> register" (ISO/IEC 17020 §7.3/§8.4
// is written around records and registers, and it is how inspection desks
// already speak). Every detail screen is "<Thing> <code>". Every form is
// "New <thing>" / "Edit <thing>". Nothing invents its own pattern.
function T_REG($key)          { return term_head(T($key)) . ' register'; }
function T_DETAIL($key, $code){ return term_head(T($key)) . ($code !== '' && $code !== null ? ' ' . $code : ''); }
function T_NEW($key)          { return 'New ' . Tl($key); }
function T_EDIT($key)         { return 'Edit ' . Tl($key); }

// Groups, for the Settings → Terminology screen.
function term_groups() {
    $g = [];
    foreach (TERM_DEFAULTS as $k => $d) $g[$d[2]][$k] = $d;
    return $g;
}
function term_save($post) {
    $ov = [];
    foreach (TERM_DEFAULTS as $k => $d) {
        $s = trim((string)($post['t_' . $k . '_s'] ?? ''));
        $p = trim((string)($post['t_' . $k . '_p'] ?? ''));
        // only store what actually differs from the shipped default
        if ($s !== '' && $s !== $d[0]) $ov[$k][0] = $s;
        if ($p !== '' && $p !== $d[1]) $ov[$k][1] = $p;
        if (isset($ov[$k])) { $ov[$k][0] = $ov[$k][0] ?? $d[0]; $ov[$k][1] = $ov[$k][1] ?? $d[1]; ksort($ov[$k]); }
    }
    setting_set('terms', $ov ? json_encode($ov) : '');
    term_overrides($ov);
}

// ---- Screen ---------------------------------------------------------------
function ops_terminology($method) {
    ops_require(can('settings.manage'), 'Only admins can change terminology.');
    if ($method === 'POST') {
        if (($_POST['reset'] ?? '') === '1') { setting_set('terms', ''); term_overrides([]); flash('Terminology reset to the standard wording.'); }
        else { term_save($_POST); flash('Terminology saved. Every screen now uses your wording.'); }
        redirect('/terminology');
    }
    view('ops/terminology', ['groups' => term_groups(), 'ov' => term_overrides()]);
}
