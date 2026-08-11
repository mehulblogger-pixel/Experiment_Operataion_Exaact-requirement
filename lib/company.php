<?php
// ============================================================================
//  "Your company" — one place to enter the operating business's identity, which
//  then feeds the quotation PDF letterhead, the invoice header and the records.
//
//  Before this, the name/address lived on the quote letterhead while the GST
//  name lived under a separate company_name setting, so the same details had to
//  be typed twice. This gathers them into one form and, on save, mirrors the
//  relevant fields back into the settings the PDF builder and the GST logic
//  already read — so nothing downstream had to change.
//
//  It ships BLANK. On a customer's own install they fill in THEIR company; the
//  provider fills in theirs. Nothing here is hard-coded to any one business.
// ============================================================================

function company_profile() {
    $g = fn($k, $d = '') => function_exists('setting_get') ? (string)setting_get($k, $d) : $d;
    return [
        'legal_name' => $g('company_legal_name'),
        'brand'      => $g('company_name'),
        'address'    => $g('company_address'),
        'gstin'      => $g('company_gstin'),
        'pan'        => $g('company_pan'),
        'state'      => $g('company_state'),
        'email'      => $g('company_email'),
        'phone'      => $g('company_phone'),
        'website'    => $g('company_website'),
        'footer'     => $g('quote_lh_footer'), // one footer line for every PDF
        'logo'       => $g('quote_lh_logo'),   // shared with the quote letterhead
        'report_brand_color' => $g('report_brand_color', '#1E40AF'), // header band colour
    ];
}

// A one-line contact string for the letterhead, from whichever of e-mail / phone
// / website are filled.
function company_contact_line() {
    $p = company_profile();
    $bits = array_filter([$p['email'], $p['phone'], $p['website']]);
    return implode('  ·  ', $bits);
}

function ops_company_profile($route, $method) {
    ops_require(is_master() || can('settings.manage'), 'Only an administrator can change the company profile.');

    if ($method === 'POST') {
        // Optional logo upload (shared with the quotation letterhead).
        if (!empty($_FILES['logo']['tmp_name']) && (int)$_FILES['logo']['error'] === 0 && function_exists('signature_to_jpeg')) {
            [$jpg, $err] = signature_to_jpeg(file_get_contents($_FILES['logo']['tmp_name']));
            if ($err) { flash('Logo: ' . $err, 'error'); redirect('/company-profile'); }
            setting_set('quote_lh_logo', base64_encode($jpg));
        }
        if (!empty($_POST['clear_logo'])) setting_set('quote_lh_logo', '');

        $legal = substr(trim((string)($_POST['legal_name'] ?? '')), 0, 200);
        $brand = substr(trim((string)($_POST['brand'] ?? '')), 0, 120);
        $addr  = substr(trim((string)($_POST['address'] ?? '')), 0, 500);
        $gstin = function_exists('clean_gstin') ? clean_gstin($_POST['gstin'] ?? '') : strtoupper(trim((string)($_POST['gstin'] ?? '')));
        $pan   = strtoupper(substr(trim((string)($_POST['pan'] ?? '')), 0, 15));
        $state = substr(trim((string)($_POST['state'] ?? '')), 0, 60);
        $email = substr(trim((string)($_POST['email'] ?? '')), 0, 150);
        $phone = substr(trim((string)($_POST['phone'] ?? '')), 0, 60);
        $web   = substr(trim((string)($_POST['website'] ?? '')), 0, 150);
        $foot  = substr(trim((string)($_POST['footer'] ?? '')), 0, 300);
        // Brand colour for the report/quotation header band (validated to #RRGGBB).
        $bcol  = trim((string)($_POST['report_brand_color'] ?? ''));
        if (preg_match('/^#?[0-9a-fA-F]{6}$/', $bcol)) setting_set('report_brand_color', (strpos($bcol,'#')===0?$bcol:'#'.$bcol));

        if ($gstin !== '' && function_exists('is_valid_gstin') && !is_valid_gstin($gstin)) {
            flash('That GSTIN does not look valid (it should be 15 characters). Saved the rest; please check it.', 'warning');
        }

        setting_set('company_legal_name', $legal);
        if ($brand !== '') setting_set('company_name', $brand);   // used by invoices, GST export, privacy notice
        setting_set('company_address', $addr);
        setting_set('company_gstin', $gstin);
        setting_set('company_pan', $pan !== '' ? $pan : ($gstin !== '' && function_exists('pan_from_gstin') ? pan_from_gstin($gstin) : ''));
        // State, like PAN, is derivable from the GSTIN (its first two digits), so
        // fill it from there when the field was left blank — the same map used for
        // tax, so the header and the GST split can never disagree.
        setting_set('company_state', $state !== '' ? $state : ($gstin !== '' && function_exists('state_from_gstin') ? state_from_gstin($gstin) : ''));
        setting_set('company_email', $email);
        setting_set('company_phone', $phone);
        setting_set('company_website', $web);

        // Mirror into the quotation letterhead the PDF builder already reads, so
        // the client PDF shows the same details without a second form.
        setting_set('quote_lh_name', $legal !== '' ? $legal : $brand);
        setting_set('quote_lh_address', $addr);
        setting_set('quote_lh_contact', company_contact_line());
        setting_set('quote_lh_footer', $foot);

        flash('Company profile saved — it now appears on your quotations, invoices and records.');
        redirect('/company-profile');
    }

    view('ops/company', ['p' => company_profile()]);
}
