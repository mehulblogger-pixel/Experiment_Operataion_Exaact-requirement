<?php
// Owner's answers of 22 Nov 2026 — Sales design requests 7(a)–(g) "as recommended" and
// 8a (the printed quotation title).
t_section('Owner answers 22 Nov 2026 — Sales 7(a)–(g) and the printed quotation title');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
putenv('BBV_RAW=1');
$prevQpt = setting_get('quote_print_title', null); $prevInd = setting_get('industry_template', null);
try {
    bbv_as($f['master']);

    // ---- 7a — one breadcrumb pattern, and a Sales home button ----------------------------
    $trail = fn($r, $leaf = null) => implode(' › ', array_map(fn($c) => $c['label'], nav_trail($r, $leaf)));
    t_eq($trail('lead', 'L-2026-0001'), 'Home › Sales › Leads › L-2026-0001', '7a · a lead: Home › Sales › Leads › its number');
    t_eq($trail('lead-new'), 'Home › Sales › Leads › New lead', '7a · new lead under Leads');
    t_eq($trail('sales-lead'), 'Home › Sales › Leads › Quick lead', '7a · the quick lead under Leads');
    t_eq($trail('opportunities'), 'Home › Sales › ' . TP('opportunity'), '7a · the deals register straight under Sales (no "Leads & pipeline")');
    t_eq($trail('opportunity-new'), 'Home › Sales › ' . TP('opportunity') . ' › New ' . Tl('opportunity'), '7a · new deal under Deals');
    t_eq($trail('quote-new'), 'Home › Sales › ' . TP('quote') . ' › New ' . Tl('quote'), '7a · new quotation under Quotes (no "CRM — Quotations")');
    t_eq($trail('inquiry-edit'), 'Home › Sales › ' . TP('inquiry') . ' › Edit ' . Tl('inquiry'), '7a · editing an inquiry under Inquiries');
    t_eq($trail('crm-reports'), 'Home › Sales › Quotation report', '7a · the quotation report under Sales, by its heading');
    t_eq(nav_back_info('quote-edit')['label'], TP('quote'), '7a · Back from a quotation form goes to the Quotes register');
    $lt = file_get_contents(__DIR__ . '/../views/layout_top.php');
    t_ok(strpos($lt, 'crumb-sales-home') !== false && strpos($lt, "sx_can()") !== false, '7a · every Sales screen draws a "Sales home" button beside Back, for people who may open Sales home');

    // ---- 7b — Print a quote -------------------------------------------------------------
    $sh = file_get_contents(__DIR__ . '/../views/ops/sx_home.php');
    t_ok(strpos($sh, 'href="/quotes?print=1"') !== false, '7b · the "Print a quote" tile opens the register in print mode');
    $r = bbv_route('quotes', 'GET', $f['master'], 0, [], ['print' => '1']);
    $h = (string) ($r['html'] ?? '');
    t_ok(strpos($h, 'press <b>PDF</b> on its row') !== false, '7b · print mode says how: find it, press PDF' . ($h === '' ? ' — ' . json_encode(array_intersect_key($r, ['code' => 1, 'flash' => 1, 'view_err' => 1])) : ''));
    $ql = file_get_contents(__DIR__ . '/../views/ops/crm/quote_list.php');
    t_ok(strpos($ql, "/quote-pdf?id=' . (int)\$r['id']") !== false, '7b · every row carries its PDF (the same /quote-pdf the quotation page prints)');

    // ---- 7c — clickable cards on Orders & payments ----------------------------------------
    $r = bbv_route('sales-orders', 'GET', $f['master'], 0, [], []);
    $h = (string) ($r['html'] ?? '');
    foreach (['st=tobill' => 'Still to bill', 'st=received' => 'Received', 'st=credit' => 'Reduced by credit note'] as $q => $lab)
        t_ok(strpos($h, $q . '#orders') !== false, "7c · \"$lab\" opens the order list narrowed to it ($q)");
    t_ok(substr_count($h, 'href="/receivables"') >= 2, '7c · Overdue, Received this month and Days to get paid open Receivables');
    $r = bbv_route('sales-orders', 'GET', $f['master'], 0, [], ['st' => 'tobill']);
    t_ok(strpos((string) ($r['html'] ?? ''), 'still to bill') !== false, '7c · the narrowed list says what it shows, with "Show all"');

    // ---- 7d — "— mine —" -------------------------------------------------------------------
    $r = bbv_route('lead-new', 'GET', $f['master'], 0, [], []);
    $h = (string) ($r['html'] ?? '');
    t_ok(strpos($h, '— mine —') === false && (strpos($h, 'My branch (') !== false || strpos($h, 'My home branch') !== false), '7d · the default branch reads "My branch (<name>)", not "— mine —"');

    // ---- 7e — Copy beside a number ------------------------------------------------------
    require_once __DIR__ . '/../views/ops/_sxw_ui.php';
    $c = sxw_contact('9825012345', 'Test Co');
    t_ok(strpos($c, 'data-copy="9825012345"') !== false && strpos($c, 'tel:') !== false, '7e · Call stays, and Copy carries the number');
    t_ok(strpos(sxw_contact('', 'X'), 'data-copy') === false, '7e · no number, no Copy');
    t_ok(strpos(file_get_contents(__DIR__ . '/../assets/js/app.js'), "closest('[data-copy]')") !== false, '7e · the page script copies it');

    // ---- 7f — "Payments to collect" only for receivables people ---------------------------
    $me = (int) ops_val("SELECT id FROM users WHERE role='MARKETING_EXECUTIVE' AND is_active=1 ORDER BY id LIMIT 1");
    if ($me) {
        $r = bbv_route('sales-today', 'GET', $me, 0, [], []);
        t_ok(strpos((string) ($r['html'] ?? ''), 'data-for="pay"') === false, '7f · a Marketing Executive (no receivables right) is not offered the tab');
    }
    $r = bbv_route('sales-today', 'GET', $f['master'], 0, [], []);
    t_ok(strpos((string) ($r['html'] ?? ''), 'data-for="pay"') !== false, '7f · someone who may see receivables still has it');

    // ---- 7g — one "Save anyway" ----------------------------------------------------------
    $lf = file_get_contents(__DIR__ . '/../views/ops/lead_form.php');
    t_ok(strpos($lf, 'name="confirm" value="1">Save anyway</button>') !== false && strpos($lf, 'type="checkbox" name="confirm"') === false,
         '7g · the full lead form has the same "Save anyway" button as the quick lead (same confirm=1)');

    // ---- 8a — printed quotation title ----------------------------------------------------
    setting_set('quote_print_title', '');
    setting_set('industry_template', 'inspection'); t_eq(quote_print_title(), 'Technical & Commercial Quotation', '8a · TPIA: "Technical & Commercial Quotation"');
    setting_set('industry_template', 'recruitment'); t_eq(quote_print_title(), 'Fee Proposal', '8a · recruitment: "Fee Proposal"');
    setting_set('industry_template', 'audit'); t_eq(quote_print_title(), 'Proposal', '8a · audit: "Proposal"');
    setting_set('industry_template', 'manpower'); t_eq(quote_print_title(), 'Quotation', '8a · technical manpower: "Quotation"');
    setting_set('industry_template', ''); t_eq(quote_print_title(), 'Quotation', '8a · no industry: "Quotation" — what every PDF printed before');
    setting_set('quote_print_title', 'Offer Letter'); t_eq(quote_print_title(), 'Offer Letter', '8a · the Sales setting wins');
    $pdfSrc = file_get_contents(__DIR__ . '/../lib/pdf.php');
    t_ok(strpos($pdfSrc, "\$p->line('QUOTATION'") === false && strpos($pdfSrc, 'mb_strtoupper(quote_print_title())') !== false, '8a · the PDF prints the title (in capitals) instead of a fixed "QUOTATION"');
} finally {
    putenv('BBV_RAW');
    setting_set('quote_print_title', $prevQpt === null ? '' : (string) $prevQpt);
    setting_set('industry_template', $prevInd === null ? '' : (string) $prevInd);
    bbv_cleanup($f);
}
