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
        // The brand / trading name falls back to the workspace name (app_name) set
        // at signup, so the screen is never blank just because the legal form was
        // never opened. Saving here still writes company_name as before.
        'brand'      => $g('company_name') ?: $g('app_name'),
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

// ---- One company identity for EVERY document -------------------------------
// Owner, 9 Nov 2026: "Invoice does not carry forward the company name, logo etc
// on the invoice copy, appointment letter and every document except quotation."
// Each print page used to build its own header from a different setting (the
// invoice from the company profile without a logo, the letters from the
// workspace name, the costing sheet from the app name, the voucher from nothing).
// Now they all ask here. Whichever of the two places the company was filled in —
// Admin → Company profile, or Sales → Quote templates → Letterhead — is used, the
// profile first.
function doc_identity() {
    $g = fn($k) => function_exists('setting_get') ? trim((string)setting_get($k, '')) : '';
    $logo = '';
    $raw = preg_replace('~\s+~', '', $g('quote_lh_logo'));
    if ($raw !== '' && preg_match('~^[A-Za-z0-9+/=]+$~', $raw)) $logo = 'data:image/jpeg;base64,' . $raw;
    elseif (preg_match('~^data:image/(png|jpe?g|gif|webp);base64,~i', $g('logo_data'))) $logo = $g('logo_data');
    $contact = function_exists('company_contact_line') ? company_contact_line() : '';
    return [
        'name'    => $g('company_legal_name') ?: ($g('quote_lh_name') ?: ($g('company_name') ?: (function_exists('app_name') ? app_name() : ''))),
        'logo'    => $logo,
        'address' => $g('company_address') ?: $g('quote_lh_address'),
        'contact' => $contact !== '' ? $contact : $g('quote_lh_contact'),
        'gstin'   => $g('company_gstin'),
        'pan'     => $g('company_pan'),
        'footer'  => $g('quote_lh_footer'),
        'color'   => preg_match('/^#[0-9a-fA-F]{6}$/', $g('report_brand_color')) ? $g('report_brand_color') : '#1E40AF',
    ];
}

// The letterhead block for a printed HTML document: logo, name, address,
// contact, GSTIN / PAN, over a line in the company colour. Self-contained
// (inline styles), so it works on any print page and inside a stored letter.
function doc_letterhead_html(array $opt = []) {
    $d = doc_identity();
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $ids = array_filter([$d['gstin'] !== '' ? 'GSTIN: ' . $d['gstin'] : '', !empty($opt['pan']) && $d['pan'] !== '' ? 'PAN: ' . $d['pan'] : '']);
    $h = '<div class="doc-letterhead" style="display:flex;gap:14px;align-items:center;border-bottom:3px solid ' . $e($d['color']) . ';padding-bottom:10px;margin-bottom:14px;font-family:Arial,Helvetica,sans-serif;color:#111">';
    if ($d['logo'] !== '') $h .= '<img src="' . $e($d['logo']) . '" alt="" style="max-height:64px;max-width:180px;object-fit:contain">';
    $h .= '<div style="flex:1;min-width:0"><div style="font-size:19px;font-weight:700;line-height:1.2">' . $e($d['name']) . '</div>';
    if ($d['address'] !== '') $h .= '<div style="font-size:11.5px;color:#444;margin-top:2px">' . nl2br($e($d['address'])) . '</div>';
    if ($d['contact'] !== '') $h .= '<div style="font-size:11.5px;color:#444">' . $e($d['contact']) . '</div>';
    if ($ids) $h .= '<div style="font-size:11.5px;color:#444">' . $e(implode('  ·  ', $ids)) . '</div>';
    return $h . '</div></div>';
}
// The footer line (Admin → Company profile → footer), if one is set.
function doc_footer_html() {
    $d = doc_identity();
    if ($d['footer'] === '') return '';
    return '<div class="doc-footer" style="border-top:1px solid #ccc;margin-top:24px;padding-top:6px;font-size:10.5px;color:#666;text-align:center;font-family:Arial,Helvetica,sans-serif">'
         . htmlspecialchars($d['footer'], ENT_QUOTES) . '</div>';
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

        // What the company DOES — the multi-select business activities, now folded
        // into this one company screen (previously a separate "Business profile").
        // Reuses the cockpit's capability store, so there is a single source.
        if (function_exists('cockpit_capabilities_set') && array_key_exists('caps', $_POST)) {
            $capsBefore = function_exists('cockpit_capabilities') ? cockpit_capabilities() : [];
            $savedCaps = cockpit_capabilities_set(array_map('strval', (array) ($_POST['caps'] ?? [])));
            $a = $capsBefore; $b = (array) $savedCaps; sort($a); sort($b);
            // Activities already chosen: saving the profile changes modules only
            // when the activities change, and then only the difference (owner,
            // 8 Nov 2026 — every save used to switch off what the company had
            // turned on). The full set-up below runs once, the first time.
            if ($a && function_exists('cockpit_apply_capability_change')) {
                $ch = $a !== $b ? cockpit_apply_capability_change($capsBefore, (array) $savedCaps) : null;
                if ($ch) {
                    $lbl = function_exists('licence_summary') ? licence_summary() : [];
                    $nm = fn($ks) => implode(', ', array_map(fn($k) => $lbl[$k]['label'] ?? $k, $ks));
                    flash('Company profile saved.' . ($ch['added'] ? ' Switched on for your new activities: ' . $nm($ch['added']) . '.' : '')
                        . ($ch['removed'] ? ' Switched off (no activity of yours needs it now): ' . $nm($ch['removed']) . '.' : '')
                        . ' Everything else stays as you set it under Features.');
                    redirect('/company-profile');
                }
                flash('Company profile saved — it now appears on your quotations, invoices and records.');
                redirect('/company-profile');
            }
            // First time the activities are chosen: auto-configure the workspace to
            // match what the company does, so a recruitment-only company becomes a
            // recruitment-only workspace (nav, masters and wording follow).
            // Reversible under Features.
            if (function_exists('cockpit_apply_capability_modules')) {
                $left = cockpit_apply_capability_modules($savedCaps);
                if (is_array($left)) {
                    $lbl = function_exists('licence_summary') ? licence_summary() : [];
                    $onNames = array_values(array_filter(array_map(fn($k) => $lbl[$k]['label'] ?? $k, $left)));
                    flash('Company profile saved. Your workspace is now set up for: '
                        . (($onNames ? implode(', ', $onNames) . ' + ' : '') . 'Administration')
                        . '. Anything you don’t use is switched off — turn a feature back on anytime under Workspace setup → Features.');
                    redirect('/company-profile');
                }
            }
        }

        flash('Company profile saved — it now appears on your quotations, invoices and records.');
        redirect('/company-profile');
    }

    $chosen = function_exists('cockpit_capabilities') ? cockpit_capabilities() : [];
    view('ops/company', [
        'p'         => company_profile(),
        'capGroups' => function_exists('cockpit_capability_groups') ? cockpit_capability_groups($chosen) : [],
        'capChosen' => $chosen,
    ]);
}
