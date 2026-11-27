<?php
// ============================================================================
//  A customer's tax identity: GSTIN, PAN, and the places it is billed at
//  (owner, 21 Nov 2026 — D1–D4 "as recommended").
//
//  The owner's rule: "when we win the customer it shall be mandatory to have
//  GSTIN and PAN, matched to the central customer database to avoid
//  duplication; a customer can have one PAN but multiple billing states with
//  respect to their branches."
//
//  So ONE customer is ONE PAN. Each state it is registered in is a BILLING
//  LOCATION on that same customer — state + GSTIN + billing address. The main
//  location is the customer record itself (its own GSTIN and primary address,
//  as it has always been); every further one is a partner_addresses row marked
//  is_billing=1 with its own GSTIN. A quotation, a contract and an invoice name
//  the location they are for (0 = the main one), and that decides CGST + SGST
//  against IGST. An invoice keeps a COPY of the name, GSTIN, state and address
//  it was billed to, so editing a customer later never rewrites a bill already
//  sent.
//
//  Reuse Gate: no table is added. Columns are added to tables that exist
//  (business_partners, partner_addresses, invoices, quotations,
//  partner_contracts). The GSTIN check digit is the one the platform's own
//  billing already uses (commercial_gstin_problem); PAN-inside-GSTIN is
//  pan_from_gstin(); the duplicate detector is find_duplicate_partner(); the
//  "move under the main customer" tool reuses dd_merge(). No permission is
//  added: who may add a billing location is who may edit the client record.
// ============================================================================

const CUSTID_STATUS = [
    'REGISTERED'   => 'GST-registered (GSTIN and PAN)',
    'UNREGISTERED' => 'Not GST-registered (PAN only)',
    'OVERSEAS'     => 'Overseas customer (no PAN or GSTIN)',
    'GOVT_NO_PAN'  => 'Government department without a PAN',
];

function custid_migrate() {
    static $doneAt = null;
    $ep = function_exists('db_epoch') ? db_epoch() : 0;
    if ($doneAt === $ep) return;
    $doneAt = $ep;
    if (!function_exists('ensure_column')) return;
    try {
        ensure_column('business_partners', 'tax_status', "VARCHAR(20) DEFAULT ''");
        ensure_column('business_partners', 'tax_reason', "VARCHAR(300) DEFAULT ''");
        ensure_column('business_partners', 'country', "VARCHAR(80) DEFAULT ''");
        ensure_column('business_partners', 'foreign_tax_id', "VARCHAR(60) DEFAULT ''");
        ensure_column('partner_addresses', 'gstin', "VARCHAR(20) DEFAULT ''");
        ensure_column('partner_addresses', 'state_code', "VARCHAR(4) DEFAULT ''");
        ensure_column('partner_addresses', 'is_billing', 'INT DEFAULT 0');
        ensure_column('quotations', 'billing_address_id', 'INT DEFAULT 0');
        ensure_column('partner_contracts', 'billing_address_id', 'INT DEFAULT 0');
        if (function_exists('db_tables') && in_array('invoices', db_tables(), true)) custid_migrate_invoices();
    } catch (Throwable $e) { /* a missing table is created by its own module; columns follow next time */ }
}
function custid_migrate_invoices() {
    static $doneAt = null;
    $ep = function_exists('db_epoch') ? db_epoch() : 0;
    if ($doneAt === $ep) return;
    $doneAt = $ep;
    ensure_column('invoices', 'billing_address_id', 'INT DEFAULT 0');
    // The copy of who the bill was made out to. bill_snap = 1 means the copy is
    // there and the print uses it; 0 (every invoice raised before this) means
    // the print reads the customer record exactly as it always did.
    ensure_column('invoices', 'bill_snap', 'INT DEFAULT 0');
    ensure_column('invoices', 'bill_name', "VARCHAR(200) DEFAULT ''");
    ensure_column('invoices', 'bill_gstin', "VARCHAR(20) DEFAULT ''");
    ensure_column('invoices', 'bill_state', "VARCHAR(4) DEFAULT ''");
    ensure_column('invoices', 'bill_address', "VARCHAR(600) DEFAULT ''");
}

// ---- Checking what is typed -------------------------------------------------
function custid_pan_ok($p) { return (bool) preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', strtoupper(trim((string) $p))); }

// '' = fine, else a sentence. The real check digit, not only the shape.
function custid_gstin_problem($g) {
    $g = clean_gstin($g);
    if ($g === '') return 'Enter the GSTIN.';
    if (function_exists('commercial_gstin_problem')) return commercial_gstin_problem($g);
    return is_valid_gstin($g) ? '' : 'A GSTIN is 15 characters, like ' . GSTIN_EXAMPLE . ' — please check it.';
}
// A government department's GSTIN can be issued on its TAN rather than a PAN,
// so only the length, the state code and the check digit are asked of it.
function custid_govt_gstin_problem($g) {
    $g = clean_gstin($g);
    if (!preg_match('/^[0-9]{2}[0-9A-Z]{13}$/', $g)) return 'A GSTIN is 15 characters, like 07DELM12345D1DL — please check it.';
    if (defined('GST_STATES') && !isset(GST_STATES[substr($g, 0, 2)])) return 'The GSTIN\'s first two digits are not an Indian state code.';
    if (function_exists('commercial_gstin_check_char') && commercial_gstin_check_char(substr($g, 0, 14)) !== $g[14])
        return 'That GSTIN has a typing mistake (its last character does not match the rest) — please copy it again from the GST certificate.';
    return '';
}
function custid_state_code_of($stateNameOrCode) {
    $s = trim((string) $stateNameOrCode);
    if ($s === '') return '';
    if (function_exists('tally_state_code')) return (string) tally_state_code($s);
    return preg_match('/^\d{2}$/', $s) ? $s : '';
}
function custid_state_name($code) {
    $code = trim((string) $code);
    return $code === '' ? '' : (string) (lk_options_or('gst_state', GST_STATES)[$code] ?? $code);
}

/**
 * Read the tax-identity boxes of any form and decide. $required = false (a
 * vendor-only record) checks only what was typed.
 * Returns ['err' => '' | sentence, 'tax_status', 'gstin', 'pan', 'state',
 *          'state_code', 'tax_reason', 'country', 'foreign_tax_id'].
 */
function custid_from_input(array $b, $required = true) {
    $st = strtoupper(trim((string) ($b['tax_status'] ?? '')));
    if (!isset(CUSTID_STATUS[$st])) $st = 'REGISTERED';
    $gstin = clean_gstin($b['gstin'] ?? '');
    $pan = strtoupper(preg_replace('/\s+/', '', (string) ($b['pan'] ?? '')));
    $reason = trim((string) ($b['tax_reason'] ?? ''));
    $country = trim((string) ($b['country'] ?? ''));
    $ftax = trim((string) ($b['foreign_tax_id'] ?? ''));
    $stateIn = trim((string) ($b['state'] ?? ''));
    $out = ['err' => '', 'tax_status' => $st, 'gstin' => '', 'pan' => '', 'state' => $stateIn, 'state_code' => custid_state_code_of($stateIn),
            'tax_reason' => '', 'country' => '', 'foreign_tax_id' => ''];
    $fail = function ($m) use (&$out) { $out['err'] = $m; return $out; };
    $other = ' — or choose "Not GST-registered", "Overseas" or "Government department without a PAN" and give the reason.';

    if (!$required) {
        // Optional: only what was typed is checked, the old way (a vendor).
        if ($gstin !== '') {
            if (($p = custid_gstin_problem($gstin)) !== '') return $fail($p);
            $out['gstin'] = $gstin; $out['pan'] = pan_from_gstin($gstin);
            $out['state'] = state_from_gstin($gstin); $out['state_code'] = substr($gstin, 0, 2);
        } elseif ($pan !== '') {
            if (!custid_pan_ok($pan)) return $fail('A PAN is 10 characters, like AAACT1234F — please check it.');
            $out['pan'] = $pan;
        }
        $out['tax_status'] = $out['gstin'] !== '' ? 'REGISTERED' : '';
        return $out;
    }

    switch ($st) {
        case 'REGISTERED':
            if ($gstin === '') return $fail('Enter the ' . Tl('client') . '\'s GSTIN' . $other);
            if (($p = custid_gstin_problem($gstin)) !== '') return $fail($p);
            $gpan = pan_from_gstin($gstin);
            if ($pan !== '' && $pan !== $gpan)
                return $fail('The PAN you typed (' . $pan . ') is not the PAN inside the GSTIN (' . $gpan . '). The GSTIN\'s 3rd to 12th characters are the PAN — check both.');
            $out['gstin'] = $gstin; $out['pan'] = $gpan;
            $out['state'] = state_from_gstin($gstin); $out['state_code'] = substr($gstin, 0, 2);
            return $out;
        case 'UNREGISTERED':
            if ($gstin !== '') return $fail('A GSTIN was entered — choose "GST-registered" instead.');
            if ($pan === '') return $fail('Enter the PAN. A customer that is not GST-registered still has a PAN.');
            if (!custid_pan_ok($pan)) return $fail('A PAN is 10 characters, like AAACT1234F — please check it.');
            if ($reason === '') return $fail('Say why there is no GSTIN (e.g. "below the GST threshold").');
            $out['pan'] = $pan; $out['tax_reason'] = substr($reason, 0, 300);
            return $out;
        case 'OVERSEAS':
            if ($country === '' || strtolower($country) === 'india') return $fail('Enter the overseas customer\'s country.');
            if ($ftax === '') return $fail('Enter the overseas customer\'s own tax number (VAT / EIN / TRN …).');
            $out['country'] = substr($country, 0, 80); $out['foreign_tax_id'] = substr($ftax, 0, 60);
            $out['tax_reason'] = substr($reason, 0, 300); $out['state'] = ''; $out['state_code'] = '';
            return $out;
        case 'GOVT_NO_PAN':
            if ($reason === '') return $fail('Say which department this is and why it has no PAN.');
            if ($gstin !== '') {
                if (($p = custid_govt_gstin_problem($gstin)) !== '') return $fail($p);
                $out['gstin'] = $gstin; $out['state'] = state_from_gstin($gstin); $out['state_code'] = substr($gstin, 0, 2);
            }
            if ($pan !== '') {
                if (!custid_pan_ok($pan)) return $fail('A PAN is 10 characters, like AAACT1234F — please check it.');
                $out['pan'] = $pan;
            }
            $out['tax_reason'] = substr($reason, 0, 300);
            return $out;
    }
    return $out;
}

// Is this customer's identity complete? (D3: those that are not are listed and
// asked for at the next edit and the next invoice.) A record saved before this
// rule with a GSTIN and a PAN counts as complete.
function custid_complete(array $p) {
    $st = strtoupper((string) ($p['tax_status'] ?? ''));
    $g = trim((string) ($p['gstin'] ?? '')); $pan = trim((string) ($p['pan'] ?? ''));
    $why = trim((string) ($p['tax_reason'] ?? ''));
    switch ($st) {
        case 'UNREGISTERED': return $pan !== '' && $why !== '';
        case 'OVERSEAS':     return trim((string) ($p['country'] ?? '')) !== '' && trim((string) ($p['foreign_tax_id'] ?? '')) !== '';
        case 'GOVT_NO_PAN':  return $why !== '';
        //  A record saved with a GSTIN but no PAN still carries its PAN inside the GSTIN.
        default:             return $g !== '' && ($pan !== '' || pan_from_gstin($g) !== '');
    }
}

// The words for what is missing, for a person.
function custid_missing_words(array $p) {
    return 'The ' . Tl('client') . ' ' . trim((string) ($p['code'] ?? '') . ' — ' . (string) ($p['legal_name'] ?? ''))
         . ' has no GSTIN and PAN on file (and no reason recorded for not having them). Add them on the ' . Tl('client') . '\'s record first.';
}

// Write the identity onto a customer record.
function custid_save($partnerId, array $id) {
    custid_migrate();
    db()->prepare("UPDATE business_partners SET gstin=?, pan=?, state=?, tax_status=?, tax_reason=?, country=?, foreign_tax_id=? WHERE id=?")
        ->execute([$id['gstin'], $id['pan'], $id['state'] !== '' ? $id['state'] : custid_state_name($id['state_code']),
                   $id['tax_status'], $id['tax_reason'], $id['country'], $id['foreign_tax_id'], (int) $partnerId]);
}

// ---- Who already has it -----------------------------------------------------
// The customer (live record) that holds this GSTIN — as its main GSTIN or as a
// billing location — or null. $excludeId leaves one record out (itself).
function custid_gstin_owner($gstin, $excludeId = 0) {
    $g = clean_gstin($gstin);
    if ($g === '') return null;
    custid_migrate();
    foreach (ops_all("SELECT id, code, legal_name, gstin, pan, tan, status FROM business_partners WHERE UPPER(gstin)=? AND id<>?", [$g, (int) $excludeId]) ?: [] as $r)
        return dup_hit($r, 'GSTIN', 'EXACT')['row'];
    $a = ops_one("SELECT pa.id addr_id, pa.label, bp.id, bp.code, bp.legal_name, bp.gstin, bp.pan, bp.tan, bp.status
                    FROM partner_addresses pa JOIN business_partners bp ON bp.id = pa.partner_id
                   WHERE pa.is_billing=1 AND UPPER(pa.gstin)=? AND pa.partner_id<>?", [$g, (int) $excludeId]);
    return $a ? dup_hit($a, 'GSTIN', 'EXACT')['row'] + ['addr_id' => (int) $a['addr_id']] : null;
}

/**
 * The decision against the central customer list, in the owner's words:
 *  - same GSTIN            → 'GSTIN': blocked; it IS that customer.
 *  - same PAN, new GSTIN   → 'PAN':   not a new customer; offer to add this
 *                                     GSTIN as a billing location of it.
 *  - same PAN, no GSTIN    → 'PAN':   it is that customer.
 *  - alike name            → 'NAME':  a warning only.
 * Names are compared without Pvt Ltd / Private Limited / Ltd / Limited / LLP /
 * Co / Company / & Co / Inc and punctuation (normalize_name, owner Q-02).
 */
function custid_match($name, $gstin, $pan, $excludeId = 0) {
    custid_migrate();
    $g = clean_gstin($gstin); $p = strtoupper(trim((string) $pan));
    if ($g !== '' && ($row = custid_gstin_owner($g, $excludeId))) return ['kind' => 'GSTIN', 'row' => $row];
    if ($p !== '') {
        $r = ops_one("SELECT id, code, legal_name, gstin, pan, tan, status FROM business_partners WHERE UPPER(pan)=? AND id<>? ORDER BY (status='MERGED'), id LIMIT 1", [$p, (int) $excludeId]);
        if ($r) return ['kind' => 'PAN', 'row' => dup_hit($r, 'PAN', 'EXACT')['row']];
    }
    $n = normalize_name($name);
    if ($n !== '') {
        foreach (ops_all("SELECT id, code, legal_name, display_name, gstin, pan, tan, status FROM business_partners WHERE id<>? AND COALESCE(status,'')<>'MERGED'", [(int) $excludeId]) ?: [] as $r)
            if (normalize_name($r['legal_name']) === $n || ($r['display_name'] ?? '') !== '' && normalize_name($r['display_name']) === $n)
                return ['kind' => 'NAME', 'row' => $r];
    }
    return ['kind' => 'NONE', 'row' => null];
}

function custid_match_message(array $m, $gstin = '') {
    $r = $m['row'] ?? null; if (!$r) return '';
    $who = trim((string) ($r['code'] ?? '') . ' — ' . (string) ($r['legal_name'] ?? ''));
    switch ($m['kind']) {
        case 'GSTIN': return 'This GSTIN already belongs to ' . $who . '. Open that ' . Tl('client') . ' instead of adding a second one — another office of it, even in the same state, is added there with "+ Add a billing address / GSTIN".';
        case 'PAN':   return $who . ' already has this PAN, so it is the same company. '
                           . (clean_gstin($gstin) !== '' ? 'Add ' . custid_state_name(substr(clean_gstin($gstin), 0, 2)) . ' (' . clean_gstin($gstin) . ') as a new billing location of it instead.'
                                                       : 'Open it instead of adding a second record.');
        case 'NAME':  return 'A company with a similar name is already on file: ' . $who . '. Check it is not the same company.';
    }
    return '';
}

/**
 * The one decision every "this customer is won" path makes (lead → customer,
 * won deal → customer, an accepted quotation for a new name). Returns:
 *   ['err' => …, 'offer' => row|null]           stop; with 'offer' the person may tick
 *                                                add_location_to = that id and try again
 *   ['attach' => id, 'cid' => …]                 it IS that customer (a billing location was added)
 *   ['cid' => …, 'near' => row|null]             a new customer may be created; 'near' = a
 *                                                similar name, recorded, not refused
 */
function custid_resolve_new_customer($name, array $b) {
    custid_migrate();
    $cid = custid_from_input($b, true);
    if ($cid['err'] !== '') return ['err' => $cid['err']];
    $m = custid_match($name, $cid['gstin'], $cid['pan'], 0);
    if ($m['kind'] === 'GSTIN') return ['err' => custid_match_message($m, $cid['gstin']) . ' Pick it from the list of ' . Tlp('client') . ' instead.'];
    if ($m['kind'] === 'PAN') {
        $row = $m['row'];
        if ($cid['gstin'] === '') return ['err' => trim($row['code'] . ' — ' . $row['legal_name']) . ' already has this PAN — it is the same company. Pick it from the list of ' . Tlp('client') . ' instead.'];
        if ((int) ($b['add_location_to'] ?? 0) === (int) $row['id']) {
            $tp = function_exists('find_partner') ? find_partner((int) $row['id']) : ops_one("SELECT * FROM business_partners WHERE id=?", [(int) $row['id']]);
            if (!$tp || !custid_can_edit($tp))
                return ['err' => 'Only someone who may edit ' . trim($row['code'] . ' — ' . $row['legal_name']) . ' can add a billing location to it. Ask them, then pick it from the list.'];
            $r = custid_add_location((int) $row['id'], ['gstin' => $cid['gstin'], 'label' => trim((string) $name) ?: state_from_gstin($cid['gstin'])]);
            if (!empty($r['err'])) return ['err' => $r['err']];
            return ['attach' => (int) $row['id'], 'cid' => $cid, 'location_id' => (int) $r['id']];
        }
        return ['err' => custid_match_message($m, $cid['gstin']), 'offer' => $row];
    }
    return ['cid' => $cid, 'near' => $m['kind'] === 'NAME' ? $m['row'] : null];
}

// ---- Billing locations ------------------------------------------------------
function custid_address_text($a) {
    if (!$a || !is_array($a)) return '';
    return trim(preg_replace('/\s+/', ' ', ($a['line1'] ?? '') . ' ' . ($a['line2'] ?? '') . ' ' . ($a['city'] ?? '') . ' ' . ($a['state'] ?? '') . ' ' . ($a['pincode'] ?? '')));
}

// The address exactly as the tax invoice prints it (line 1, city, state, PIN).
function custid_print_address($a) {
    if (!$a || !is_array($a)) return '';
    return trim(($a['line1'] ?? '') . ' ' . ($a['city'] ?? '') . ' ' . ($a['state'] ?? '') . ' ' . ($a['pincode'] ?? ''));
}

/** Every place this customer is billed at. id 0 = the main one (the record itself). */
function custid_locations($partnerId) {
    custid_migrate();
    $p = ops_one("SELECT * FROM business_partners WHERE id=?", [(int) $partnerId]);
    if (!$p) return [];
    //  The Main row is the record's own head-office address — never the address of one of
    //  its billing locations (owner test BL-03 / BL-05, 27 Nov 2026: a client converted from
    //  a lead had no address, so "Main" borrowed the Mumbai plant's). None on file = empty,
    //  and the box says so.
    $main = custid_head_office_address((int) $partnerId);
    $mg = clean_gstin($p['gstin'] ?? '');
    $msc = $mg !== '' ? substr($mg, 0, 2) : custid_state_code_of($p['state'] ?? '');
    $out = [[
        'id' => 0, 'label' => 'Main', 'gstin' => $mg, 'state_code' => $msc,
        'state' => $mg !== '' ? state_from_gstin($mg) : (string) ($p['state'] ?? ''),
        'address' => custid_address_text($main), 'addr' => $main, 'is_main' => true,
    ]];
    foreach (ops_all("SELECT * FROM partner_addresses WHERE partner_id=? AND is_billing=1 ORDER BY id", [(int) $partnerId]) ?: [] as $a) {
        $g = clean_gstin($a['gstin'] ?? '');
        $out[] = ['id' => (int) $a['id'], 'label' => (string) ($a['label'] ?: ($g !== '' ? state_from_gstin($g) : 'Billing location')),
                  'gstin' => $g, 'state_code' => (string) ($a['state_code'] ?: ($g !== '' ? substr($g, 0, 2) : custid_state_code_of($a['state'] ?? ''))),
                  'state' => $g !== '' ? state_from_gstin($g) : (string) ($a['state'] ?? ''),
                  'address' => custid_address_text($a), 'addr' => $a, 'is_main' => false];
    }
    return $out;
}
/**
 * The customer's head-office address: one of its OWN addresses, never a billing
 * location (is_billing=1) or a stopped one (it still carries its GSTIN). The one
 * marked "head office" wins; else a registered / corporate office; else any other
 * office address; an Addresses-tab "Billing Address" typed without a GSTIN only when
 * nothing else is on file (it was the main GSTIN's billing address). null = none.
 */
function custid_head_office_address($partnerId) {
    custid_migrate();
    try {
        return ops_one("SELECT * FROM partner_addresses
                         WHERE partner_id=? AND COALESCE(is_billing,0)=0 AND COALESCE(gstin,'')=''
                         ORDER BY is_primary DESC, CASE WHEN COALESCE(address_type,'')='BILLING' THEN 1 ELSE 0 END,
                                  CASE COALESCE(address_type,'') WHEN 'REGISTERED' THEN 0 WHEN 'CORPORATE' THEN 1 ELSE 2 END, id
                         LIMIT 1", [(int) $partnerId]) ?: null;
    } catch (Throwable $e) { return null; }
}
/** May this address be the head office? One of the customer's own, not a billing location. */
function custid_can_be_head_office(array $a) {
    return (int) ($a['is_billing'] ?? 0) === 0 && clean_gstin($a['gstin'] ?? '') === '';
}
/** Mark one address as the head office (one per customer). '' = done, else why not. */
function custid_mark_head_office($partnerId, $addrId) {
    custid_migrate();
    $a = ops_one("SELECT * FROM partner_addresses WHERE id=? AND partner_id=?", [(int) $addrId, (int) $partnerId]);
    if (!$a) return 'That address is not on this ' . Tl('client') . '.';
    if (!custid_can_be_head_office($a))
        return 'A billing address with its own GSTIN cannot be the head office. Mark one of the ' . Tl('client') . '\'s own addresses instead.';
    db()->prepare("UPDATE partner_addresses SET is_primary=0 WHERE partner_id=? AND id<>?")->execute([(int) $partnerId, (int) $addrId]);
    db()->prepare("UPDATE partner_addresses SET is_primary=1 WHERE id=?")->execute([(int) $addrId]);
    $txt = substr(trim(($a['label'] ?? '') . ' ' . custid_address_text($a)), 0, 200);
    if (function_exists('act_log')) act_log('PARTNER', (int) $partnerId, 'HEAD_OFFICE', 'Head-office address set: ' . $txt, ['partner_id' => (int) $partnerId]);
    if (function_exists('idems_log')) idems_log('partner', (int) $partnerId, 'HEAD_OFFICE_SET', ['field' => 'address', 'new' => $txt]);
    return '';
}

/**
 * Who may ask "who already has this GSTIN?" while typing (owner BL-07, 27 Nov 2026):
 * exactly the people who would be told on Save — those who may add or edit a client
 * or a billing address, and those who may turn a lead, a deal or a won quotation into
 * a customer. No new right.
 */
function custid_lookup_can() {
    if (!function_exists('current_user') || !current_user()) return false;
    if (is_master() || can('mod.clients.edit') || can('mod.vendors.edit')) return true;
    if (function_exists('is_coordinator_level') && is_coordinator_level()) return true;
    if (function_exists('leads_can_edit') && leads_can_edit()) return true;
    if (function_exists('opp_can_add_customer') && opp_can_add_customer()) return true;
    if (function_exists('crm_quote_can_create') && crm_quote_can_create()) return true;
    return false;
}
/**
 * The answer, as you type — no more than the Save refusal already names (the code and
 * the name). ['kind' => 'GSTIN' | 'PAN' | 'NONE' | 'BAD', 'text' => …]. $excludeId is
 * the record being edited (its own GSTIN and PAN are no clash).
 */
function custid_gstin_who($gstin, $excludeId = 0) {
    $g = clean_gstin($gstin);
    if (strlen($g) !== 15 || custid_gstin_problem($g) !== '') return ['kind' => 'BAD', 'text' => ''];
    $m = custid_match('', $g, pan_from_gstin($g), (int) $excludeId);
    $r = $m['row'] ?? null;
    if (!$r || !in_array($m['kind'], ['GSTIN', 'PAN'], true)) return ['kind' => 'NONE', 'text' => ''];
    $who = trim((string) ($r['code'] ?? '') . ' — ' . (string) ($r['legal_name'] ?? ''));
    $out = ['kind' => $m['kind'], 'code' => (string) ($r['code'] ?? ''), 'name' => (string) ($r['legal_name'] ?? '')];
    $out['text'] = $m['kind'] === 'GSTIN'
        ? 'Already belongs to ' . $who . '. Open that ' . Tl('client') . ' instead of adding it again.'
        : 'Its PAN ' . pan_from_gstin($g) . ' belongs to ' . $who . ' — the same company. Add this GSTIN there as a billing address instead.';
    return $out;
}

/** One location of this customer; an unknown id is the main one. */
function custid_location($partnerId, $locId = 0) {
    $all = custid_locations($partnerId);
    foreach ($all as $l) if ((int) $l['id'] === (int) $locId) return $l;
    return $all[0] ?? null;
}
/** A location id that really belongs to this customer, else 0 (the main one). */
function custid_location_id_for($partnerId, $locId) {
    $locId = (int) $locId;
    if ($locId <= 0) return 0;
    custid_migrate();
    return ops_val("SELECT id FROM partner_addresses WHERE id=? AND partner_id=? AND is_billing=1", [$locId, (int) $partnerId]) ? $locId : 0;
}

// Who may add or change a billing location (D4): exactly who may edit the
// client record — the partner-edit gate and its office boundary. No new right.
function custid_can_edit(array $p) {
    $role = is_master() || can('mod.clients.edit') || can('mod.vendors.edit')
         || (function_exists('is_coordinator_level') && is_coordinator_level());
    return $role && (!function_exists('partner_can_write') || partner_can_write($p));
}

/**
 * Add a billing location: a state + GSTIN + billing address of this same
 * customer. The GSTIN must be real, must carry the customer's PAN, and must not
 * be anybody's already. Returns ['id' => …] or ['err' => …].
 */
function custid_add_location($partnerId, array $b, $excludeOwnerId = 0) {
    custid_migrate();
    $p = ops_one("SELECT * FROM business_partners WHERE id=?", [(int) $partnerId]);
    if (!$p) return ['err' => 'That ' . Tl('client') . ' no longer exists.'];
    $g = clean_gstin($b['gstin'] ?? '');
    if (($why = custid_gstin_problem($g)) !== '') return ['err' => $why];
    $pan = pan_from_gstin($g);
    $mine = strtoupper(trim((string) ($p['pan'] ?? '')));
    if ($mine !== '' && $pan !== $mine)
        return ['err' => 'This GSTIN carries the PAN ' . $pan . ', but ' . $p['legal_name'] . '\'s PAN is ' . $mine . '. A billing location must belong to the same PAN — a different PAN is a different company.'];
    $own = custid_gstin_owner($g, (int) $partnerId);
    if ($own && (int) $own['id'] !== (int) $excludeOwnerId)
        return ['err' => 'That GSTIN already belongs to ' . trim(($own['code'] ?? '') . ' — ' . ($own['legal_name'] ?? '')) . '.'];
    // The same GSTIN again = another office of this customer in the same state
    // (GST "additional place of business", owner 25 Nov 2026: Q3). Allowed with
    // its own address; the address is what tells the two offices apart, so it
    // is required, and the very same address twice is refused.
    $sameGst = clean_gstin($p['gstin'] ?? '') === $g
            || (bool) ops_val("SELECT id FROM partner_addresses WHERE partner_id=? AND is_billing=1 AND UPPER(gstin)=?", [(int) $partnerId, $g]);
    if ($sameGst) {
        if (trim((string) ($b['line1'] ?? '')) === '' || trim((string) ($b['city'] ?? '')) === '')
            return ['err' => $g . ' is already one of ' . $p['legal_name'] . '\'s GSTINs. To add another office in the same state under it, enter that office\'s billing address and city — they are what tell the two offices apart on a bill.'];
        if (($dup = custid_same_address_as((int) $partnerId, $g, $b)) !== '')
            return ['err' => 'That address is already a billing address of ' . $p['legal_name'] . ' under GSTIN ' . $g . ' (' . $dup . ').'];
    }
    $state = state_from_gstin($g);
    $label = trim((string) ($b['label'] ?? '')) ?: ($sameGst ? (trim((string) ($b['city'] ?? '')) ?: $state) : $state);
    db()->prepare("INSERT INTO partner_addresses (partner_id,address_type,label,line1,line2,city,state,pincode,country,is_primary,gstin,state_code,is_billing)
                   VALUES (?,'BILLING',?,?,?,?,?,?,'India',0,?,?,1)")
        ->execute([(int) $partnerId, substr($label, 0, 150), substr(trim((string) ($b['line1'] ?? '')), 0, 255), substr(trim((string) ($b['line2'] ?? '')), 0, 255),
                   substr(trim((string) ($b['city'] ?? '')), 0, 120), $state, substr(trim((string) ($b['pincode'] ?? '')), 0, 15), $g, substr($g, 0, 2)]);
    $id = (int) db()->lastInsertId();
    // A customer saved before this rule may have had no PAN on its own record.
    if ($mine === '') db()->prepare("UPDATE business_partners SET pan=? WHERE id=? AND COALESCE(pan,'')=''")->execute([$pan, (int) $partnerId]);
    if (function_exists('act_log')) act_log('PARTNER', (int) $partnerId, 'BILLING_LOCATION', 'Billing location added: ' . $label . ' — GSTIN ' . $g . ($sameGst ? ' (same GSTIN, another address)' : ''), ['partner_id' => (int) $partnerId]);
    if (function_exists('idems_log')) idems_log('partner', (int) $partnerId, 'BILLING_LOCATION_ADDED', ['field' => $g, 'new' => $label]);
    return ['id' => $id, 'same_gstin' => $sameGst];
}

/**
 * Where Add / Change / Stop of a billing address lands: back on the page the box
 * was on — the Sales customer page or the master record (owner, 25 Nov 2026: Q3).
 * Only these two; anything else posted is ignored (never a free address).
 */
function custid_back_url(array $post, $partnerId) {
    $back = (string) ($post['back'] ?? '');
    //  The Addresses tab offers the same form (owner, 27 Nov 2026: "both shall have same
    //  form … so we don't have duplicate screens") — and goes back to that tab.
    if ($back === 'addresses') return '/partner?id=' . (int) $partnerId . '&tab=addresses';
    $base = $back === 'customer' ? '/customer?id=' : '/partner?id=';
    return $base . (int) $partnerId . '#billing-locations';
}

/** One address reduced to what makes it the same place: line 1, city, PIN — case, spaces and punctuation ignored. */
function custid_addr_key($a) {
    $n = fn($x) => preg_replace('/[^a-z0-9]+/', '', strtolower((string) $x));
    return $n($a['line1'] ?? '') . '|' . $n($a['city'] ?? '') . '|' . $n($a['pincode'] ?? '');
}

/**
 * The name of this customer's billing place that already has this GSTIN AND
 * this address ('' = none). $skipLocId leaves one location out (itself, on a change).
 */
function custid_same_address_as($partnerId, $gstin, array $b, $skipLocId = 0) {
    $g = clean_gstin($gstin);
    $key = custid_addr_key($b);
    if ($key === '||') return '';
    foreach (custid_locations((int) $partnerId) as $l) {
        if ($l['gstin'] !== $g || (!$l['is_main'] && (int) $l['id'] === (int) $skipLocId)) continue;
        if ($l['addr'] && custid_addr_key($l['addr']) === $key) return (string) $l['label'];
    }
    return '';
}

/**
 * How a location is named in a pick-list: name — state — GSTIN, plus the city
 * when another location shares its GSTIN (two offices in one state).
 */
function custid_location_words(array $l, array $all) {
    $n = 0;
    foreach ($all as $o) if ($o['gstin'] !== '' && $o['gstin'] === $l['gstin']) $n++;
    $city = trim((string) ($l['addr']['city'] ?? ''));
    return $l['label'] . ' — ' . ($l['state'] ?: 'state not set') . ($l['gstin'] !== '' ? ' — ' . $l['gstin'] : '')
         . ($n > 1 && $city !== '' && stripos($l['label'], $city) === false ? ' — ' . $city : '');
}

/** Change a location's name and address (the GSTIN is its identity and stays). */
function custid_update_location($partnerId, $locId, array $b) {
    custid_migrate();
    $a = ops_one("SELECT * FROM partner_addresses WHERE id=? AND partner_id=? AND is_billing=1", [(int) $locId, (int) $partnerId]);
    if (!$a) return 'That billing location is not on this ' . Tl('client') . '.';
    // A changed address may not become the very same place as another one under its GSTIN.
    if (($dup = custid_same_address_as((int) $partnerId, (string) $a['gstin'], $b, (int) $locId)) !== '')
        return 'That address is already a billing address under GSTIN ' . clean_gstin($a['gstin']) . ' (' . $dup . ').';
    db()->prepare("UPDATE partner_addresses SET label=?, line1=?, line2=?, city=?, pincode=? WHERE id=?")
        ->execute([substr(trim((string) ($b['label'] ?? '')) ?: (string) $a['label'], 0, 150), substr(trim((string) ($b['line1'] ?? '')), 0, 255),
                   substr(trim((string) ($b['line2'] ?? '')), 0, 255), substr(trim((string) ($b['city'] ?? '')), 0, 120),
                   substr(trim((string) ($b['pincode'] ?? '')), 0, 15), (int) $locId]);
    if (function_exists('act_log')) act_log('PARTNER', (int) $partnerId, 'BILLING_LOCATION', 'Billing location ' . $a['gstin'] . ' changed', ['partner_id' => (int) $partnerId]);
    return '';
}

/** Stop billing at a location. Bills already raised keep their own copy. */
function custid_stop_location($partnerId, $locId) {
    custid_migrate();
    $a = ops_one("SELECT * FROM partner_addresses WHERE id=? AND partner_id=? AND is_billing=1", [(int) $locId, (int) $partnerId]);
    if (!$a) return 'That billing location is not on this ' . Tl('client') . '.';
    $open = (int) ops_val("SELECT COUNT(*) FROM quotations WHERE billing_address_id=? AND client_id=? AND status NOT IN ('ACCEPTED','LOST','REJECTED','EXPIRED')", [(int) $locId, (int) $partnerId]);
    if ($open) return 'An open ' . Tl('quote') . ' is still billed to this location. Change it there first.';
    db()->prepare("UPDATE partner_addresses SET is_billing=0, address_type='BRANCH' WHERE id=?")->execute([(int) $locId]);
    if (function_exists('act_log')) act_log('PARTNER', (int) $partnerId, 'BILLING_LOCATION', 'Billing location ' . $a['gstin'] . ' no longer used for new bills', ['partner_id' => (int) $partnerId]);
    return '';
}

// ---- Tax side ---------------------------------------------------------------
/** The two-digit state the customer is billed in at this location ('' unknown). */
function custid_their_state($partnerId, $locId = 0) {
    $loc = custid_location($partnerId, $locId);
    return $loc ? (string) $loc['state_code'] : '';
}

/**
 * Write the copy of who an invoice is made out to. Called when it is raised,
 * when its location or branch changes as a draft, and once more as it is issued
 * — from then on the print reads only the copy.
 */
function custid_invoice_snapshot($invoiceId) {
    custid_migrate(); custid_migrate_invoices();
    $inv = ops_one("SELECT * FROM invoices WHERE id=?", [(int) $invoiceId]);
    if (!$inv || !(int) $inv['partner_id']) return;
    if ((int) ($inv['bill_snap'] ?? 0) === 1 && !in_array((string) $inv['status'], ['DRAFT'], true)) return;   // frozen once issued
    $p = ops_one("SELECT * FROM business_partners WHERE id=?", [(int) $inv['partner_id']]);
    if (!$p) return;
    $locId = custid_location_id_for((int) $inv['partner_id'], (int) ($inv['billing_address_id'] ?? 0));
    $loc = custid_location((int) $inv['partner_id'], $locId);
    db()->prepare("UPDATE invoices SET billing_address_id=?, bill_snap=1, bill_name=?, bill_gstin=?, bill_state=?, bill_address=? WHERE id=?")
        ->execute([$locId, (string) (($p['display_name'] ?? '') ?: $p['legal_name']), (string) ($loc['gstin'] ?? ''),
                   (string) ($loc['state_code'] ?? ''), substr(custid_print_address($loc['addr'] ?? null), 0, 600), (int) $invoiceId]);
}

/**
 * Choose where a DRAFT invoice is billed to. Re-reads the place of supply, the
 * buyer GSTIN and CGST+SGST / IGST from that location. '' = done.
 */
function custid_invoice_set_location($invoiceId, $locId) {
    custid_migrate(); custid_migrate_invoices();
    $inv = ops_one("SELECT * FROM invoices WHERE id=?", [(int) $invoiceId]);
    if (!$inv) return 'That invoice no longer exists.';
    if ((string) $inv['status'] !== 'DRAFT') return 'Where the invoice is billed to is fixed once it is issued.';
    $pid = (int) $inv['partner_id'];
    $locId = (int) $locId;
    if ($locId && !custid_location_id_for($pid, $locId)) return 'That billing location is not one of this ' . Tl('client') . '\'s.';
    $loc = custid_location($pid, $locId);
    $igst = function_exists('books_is_igst') ? books_is_igst($pid, $inv['office_id'] ? (int) $inv['office_id'] : null, $locId) : (int) $inv['is_igst'];
    //  Our own state is refreshed with it (owner, 27 Nov 2026), so a draft started before the Company
    //  profile state was filled reads the right state the next time "Billed to" is chosen.
    $ours = function_exists('books_our_state') ? books_our_state($inv['office_id'] ? (int) $inv['office_id'] : null) : (string) ($inv['supplier_state'] ?? '');
    db()->prepare("UPDATE invoices SET billing_address_id=?, place_of_supply=?, gstin=?, is_igst=?, supplier_state=?, updated_at=? WHERE id=?")
        ->execute([$locId, (string) ($loc['state_code'] ?? ''), (string) ($loc['gstin'] ?? ''), $igst, $ours, date('c'), (int) $invoiceId]);
    custid_invoice_snapshot((int) $invoiceId);
    if (function_exists('books_recalc')) books_recalc((int) $invoiceId);
    if (function_exists('act_log')) act_log('INVOICE', (int) $invoiceId, 'BILL_TO', 'Billed to ' . ($loc['label'] ?? 'Main') . ($loc['gstin'] ? ' (GSTIN ' . $loc['gstin'] . ')' : ''));
    return '';
}

/** The billing location an invoice for this quotation / contract should default to. */
function custid_default_location($partnerId, $quotationId = 0, $contractNo = '') {
    custid_migrate();
    $id = 0;
    if ((int) $quotationId) $id = (int) (ops_val("SELECT billing_address_id FROM quotations WHERE id=?", [(int) $quotationId]) ?: 0);
    if (!$id && trim((string) $contractNo) !== '') {
        $c = ops_one("SELECT billing_address_id, quotation_id FROM partner_contracts WHERE partner_id=? AND contract_number=? ORDER BY id DESC LIMIT 1", [(int) $partnerId, trim((string) $contractNo)]);
        $id = (int) ($c['billing_address_id'] ?? 0);
        //  A contract registered from a quotation is billed where that quotation was.
        if (!$id && !empty($c['quotation_id'])) $id = (int) (ops_val("SELECT billing_address_id FROM quotations WHERE id=?", [(int) $c['quotation_id']]) ?: 0);
        if (!$id) $id = (int) (ops_val("SELECT billing_address_id FROM quotations WHERE client_id=? AND contract_number=? AND COALESCE(is_current,1)=1 ORDER BY id DESC LIMIT 1", [(int) $partnerId, trim((string) $contractNo)]) ?: 0);
    }
    return custid_location_id_for($partnerId, $id);
}

/** Save which billing location a quotation is for (0 = the main one). */
function custid_quote_set_location($quoteId, array $b) {
    custid_migrate();
    if (!array_key_exists('billing_address_id', $b)) return;
    $q = ops_one("SELECT id, client_id, parent_id FROM quotations WHERE id=?", [(int) $quoteId]);
    if (!$q) return;
    $loc = (int) $q['client_id'] ? custid_location_id_for((int) $q['client_id'], (int) $b['billing_address_id']) : 0;
    db()->prepare("UPDATE quotations SET billing_address_id=? WHERE id=?")->execute([$loc, (int) $quoteId]);
}

/** {client id: [[location id, words], …]} for customers billed at more than one place. */
function custid_location_map() {
    custid_migrate();
    $out = [];
    foreach (ops_all("SELECT DISTINCT partner_id FROM partner_addresses WHERE is_billing=1") ?: [] as $r) {
        $pid = (int) $r['partner_id'];
        $all = custid_locations($pid);
        foreach ($all as $l)
            $out[$pid][] = [(int) $l['id'], custid_location_words($l, $all)];
    }
    return $out;
}

// ---- Data health (D3) -------------------------------------------------------
function custid_health() {
    custid_migrate();
    $missing = 0; $govt = 0; $badGst = 0;
    foreach (ops_all("SELECT id, gstin, pan, tax_status, tax_reason, country, foreign_tax_id FROM business_partners WHERE is_client=1 AND COALESCE(status,'') NOT IN ('MERGED','RETIRED')") ?: [] as $p) {
        if (!custid_complete($p)) $missing++;
        if (strtoupper((string) $p['tax_status']) === 'GOVT_NO_PAN') $govt++;
        $g = clean_gstin($p['gstin'] ?? '');
        if ($g !== '' && strtoupper((string) $p['tax_status']) !== 'GOVT_NO_PAN' && custid_gstin_problem($g) !== '') $badGst++;
    }
    return ['missing' => $missing, 'govt_no_pan' => $govt, 'bad_gstin' => $badGst];
}

// ---- D1: move an existing branch company under its main customer -------------
/**
 * What pressing "Move under the main customer" would do, without doing it.
 * Returns ['err' => …] or ['branch', 'main', 'gstin', 'state', 'moves' => [table.col => n],
 * 'invoices' => n frozen, 'creates_location' => bool].
 */
function custid_move_preview($branchId) {
    custid_migrate(); custid_migrate_invoices();
    $b = ops_one("SELECT * FROM business_partners WHERE id=?", [(int) $branchId]);
    if (!$b) return ['err' => 'That record no longer exists.'];
    if ((string) ($b['status'] ?? '') === 'MERGED') return ['err' => 'That record has already been moved or merged.'];
    $mainId = (int) ($b['parent_id'] ?? 0);
    if (!$mainId) return ['err' => 'This ' . Tl('client') . ' is not a branch company of another one.'];
    $m = ops_one("SELECT * FROM business_partners WHERE id=?", [$mainId]);
    if (!$m || (string) ($m['status'] ?? '') === 'MERGED') return ['err' => 'Its main ' . Tl('client') . ' is not on file any more.'];
    $bg = clean_gstin($b['gstin'] ?? ''); $bp = strtoupper(trim((string) ($b['pan'] ?? ''))) ?: pan_from_gstin($bg);
    $mp = strtoupper(trim((string) ($m['pan'] ?? ''))) ?: pan_from_gstin($m['gstin'] ?? '');
    if ($bp === '' || $mp === '') return ['err' => 'Both records need a PAN (or a GSTIN) before they can be joined — it is the PAN that proves they are one company.'];
    if ($bp !== $mp) return ['err' => 'The PANs differ (' . $bp . ' and ' . $mp . '), so these are two companies. A branch company with its own PAN stays a separate record.'];
    $moves = [];
    if (function_exists('dd_reference_columns'))
        foreach (dd_reference_columns() as [$t, $c]) {
            try { $n = (int) ops_val("SELECT COUNT(*) FROM $t WHERE $c=?", [(int) $branchId]); } catch (Throwable $e) { $n = 0; }
            if ($n) $moves[$t . '.' . $c] = $n;
        }
    $freeze = (int) (ops_val("SELECT COUNT(*) FROM invoices WHERE partner_id=? AND COALESCE(bill_snap,0)=0", [(int) $branchId]) ?: 0);
    return ['branch' => $b, 'main' => $m, 'gstin' => $bg, 'state' => $bg !== '' ? state_from_gstin($bg) : (string) ($b['state'] ?? ''),
            'moves' => $moves, 'invoices' => $freeze,
            'creates_location' => $bg !== '' && clean_gstin($m['gstin'] ?? '') !== '' && clean_gstin($m['gstin'] ?? '') !== $bg];
}

/**
 * Do it — only ever from a person pressing the button, with a reason.
 *  1. every invoice of the branch keeps a copy of exactly what it prints today;
 *  2. the branch's GSTIN, state and address become a billing location of the main customer
 *     (or the main one's own GSTIN, if it had none);
 *  3. its quotations, contracts and invoices point at that location;
 *  4. everything else that pointed at the branch points at the main customer (dd_merge),
 *     and the branch is retired with a note saying where it went.
 */
function custid_move_apply($branchId, $reason) {
    $pv = custid_move_preview($branchId);
    if (!empty($pv['err'])) return $pv;
    $reason = trim((string) $reason);
    if ($reason === '') return ['err' => 'Say why these are one company (e.g. "same PAN — Mumbai branch").'];
    $b = $pv['branch']; $m = $pv['main']; $bid = (int) $b['id']; $mid = (int) $m['id'];
    foreach (ops_all("SELECT id FROM invoices WHERE partner_id=? AND COALESCE(bill_snap,0)=0", [$bid]) ?: [] as $i) {
        // Exactly what the bill prints today: the branch's name, its GSTIN, its first address.
        //  The print read COALESCE(display_name, legal_name) and the bare GSTIN of the
        //  record; the copy is made the same way so the bill reads the same after the move.
        $addr = function_exists('partner_primary_address') ? partner_primary_address($bid) : null;
        db()->prepare("UPDATE invoices SET bill_snap=1, bill_name=?, bill_gstin=?, bill_state=?, bill_address=? WHERE id=?")
            ->execute([(string) ($b['display_name'] ?? $b['legal_name']), (string) ($b['gstin'] ?? ''),
                       (string) (ops_val("SELECT place_of_supply FROM invoices WHERE id=?", [(int) $i['id']]) ?: ''),
                       substr(custid_print_address($addr), 0, 600), (int) $i['id']]);
    }
    $locId = 0;
    if ($pv['creates_location']) {
        $addr = function_exists('partner_primary_address') ? partner_primary_address($bid) : null;
        $r = custid_add_location($mid, ['gstin' => $pv['gstin'], 'label' => (string) (($b['display_name'] ?? '') ?: $b['legal_name']),
                                        'line1' => $addr['line1'] ?? '', 'line2' => $addr['line2'] ?? '', 'city' => $addr['city'] ?? '', 'pincode' => $addr['pincode'] ?? ''], $bid);
        if (!empty($r['err'])) return $r;
        $locId = (int) $r['id'];
        foreach (['quotations' => 'client_id', 'partner_contracts' => 'partner_id', 'invoices' => 'partner_id'] as $t => $c)
            try { db()->prepare("UPDATE $t SET billing_address_id=? WHERE $c=? AND COALESCE(billing_address_id,0)=0")->execute([$locId, $bid]); } catch (Throwable $e) {}
    }
    if (!function_exists('dd_merge')) return ['err' => 'The merge tool is not available.'];
    $res = dd_merge($mid, $bid, 'Moved under the main ' . Tl('client') . ' as a billing location (same PAN): ' . $reason);
    if (empty($res['ok'])) return ['err' => $res['error'] ?? 'Could not move it.'];
    if (function_exists('idems_log')) idems_log('partner', $mid, 'BRANCH_MOVED_UNDER', ['field' => (string) $b['code'], 'reason' => $reason, 'new' => (string) $locId]);
    if (function_exists('act_log')) act_log('PARTNER', $mid, 'BRANCH_MOVED_UNDER', $b['code'] . ' — ' . $b['legal_name'] . ' moved under this ' . Tl('client') . ($locId ? ' as a billing location' : '') . '. ' . $reason, ['partner_id' => $mid]);
    return ['ok' => true, 'main_id' => $mid, 'location_id' => $locId, 'moved' => $res['moved'] ?? []];
}
