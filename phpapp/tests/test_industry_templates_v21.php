<?php
// ============================================================================
//  Industry templates V2.1 — owner approved 21 Nov 2026 ("V2.1 approved, no
//  certification, Deployed Engineer ok").
//
//  TPIA refined; technical manpower and recruitment split apart; an audit
//  template added. Templates CREATE pipelines and never edit them; a template a
//  V2.1 one replaced stays working for the companies already on it but is not
//  offered for a new choice; every template carries a version, stamped when it
//  is applied. Stages, percentages and lists are examples the company changes.
// ============================================================================
t_section('Industry templates V2.1 — TPIA, technical manpower, recruitment, audit');

$V21 = ['inspection', 'manpower', 'recruitment', 'audit'];

// ---- 1. Shape: every funnel has exactly one Won and one Lost stage ----------
foreach (INDUSTRY_TEMPLATES as $key => $t) {
    $funnels = ['main' => $t['funnel'], 'leads' => $t['leads']] + array_map(fn($s) => $s, $t['alt'] ?? []);
    foreach ($funnels as $fn => $stages) {
        $kinds = array_count_values(array_column($stages, 1));
        t_ok(($kinds['WON'] ?? 0) === 1 && ($kinds['LOST'] ?? 0) === 1,
            "$key · $fn funnel has exactly one Won and one Lost stage");
    }
}

// ---- 2. The four V2.1 templates, as approved -----------------------------
t_eq(INDUSTRY_TEMPLATES['inspection']['label'], 'Third-party inspection (TPIA) & testing', 'TPIA template name');
t_ok(stripos(INDUSTRY_TEMPLATES['inspection']['label'], 'certification') === false, 'owner: no "certification" in the TPIA name');
t_eq(INDUSTRY_TEMPLATES['manpower']['label'], 'Technical manpower & deputation', 'technical manpower has its own template');
t_eq(INDUSTRY_TEMPLATES['recruitment']['label'], 'Recruitment & placement agency', 'recruitment has its own template');
t_eq(INDUSTRY_TEMPLATES['audit']['label'], 'Audit & assurance', 'audit has its own template');
foreach ($V21 as $k) {
    t_ok(industry_version($k) >= 1 && isset(INDUSTRY_TEMPLATES[$k]['version']), "$k carries a version (" . industry_version($k) . ')');
    t_eq(array_column(INDUSTRY_TEMPLATES[$k]['leads'], 0),
        ['New prospect', 'Contact attempted', 'Contact established', 'Qualified', 'Won — customer', 'Not pursued'],
        "$k · the common V2.1 lead stages, keeping the owner's \"Won — customer\" and \"Not pursued\"");
    $won = array_values(array_filter(INDUSTRY_TEMPLATES[$k]['funnel'], fn($s) => $s[1] === 'WON'))[0][0];
    t_ok(strpos($won, 'Won') === 0, "$k · the deal funnel ends at the commercial award (\"$won\")");
}
t_eq(INDUSTRY_TEMPLATES['inspection']['funnel'][0][0], 'Enquiry / RFQ received', 'TPIA funnel starts at Enquiry / RFQ received');
t_eq(count(INDUSTRY_TEMPLATES['recruitment']['funnel']), 9, 'recruitment funnel: 7 open stages, Won — candidate joined, Lost');
t_ok(in_array('Statutory Cost Not Accepted', INDUSTRY_TEMPLATES['manpower']['lost'], true), 'manpower lost reasons include statutory cost');
t_ok(in_array('Independence / Conflict of Interest', INDUSTRY_TEMPLATES['audit']['lost'], true), 'audit lost reasons include independence / conflict');
t_ok(in_array('Tender / GeM Portal', INDUSTRY_TEMPLATES['inspection']['sources'], true), 'TPIA sources include Tender / GeM Portal');

// ---- 3. Words — one short word per idea (V2.1 §2) -------------------------
$w = fn($pack, $k) => TERM_PACKS[$pack]['terms'][$k][0] ?? '(default ' . (TERM_DEFAULTS[$k][0] ?? '?') . ')';
t_eq([INDUSTRY_TERM_MAP['inspection'], INDUSTRY_TERM_MAP['manpower'], INDUSTRY_TERM_MAP['recruitment'], INDUSTRY_TERM_MAP['audit']],
     ['tpia', 'techmanpower', 'recruitplace', 'audit'], 'each V2.1 template sets its own wording pack');
t_eq([$w('tpia', 'inquiry'), $w('tpia', 'quote'), $w('tpia', 'order'), $w('tpia', 'call'), $w('tpia', 'job'), $w('tpia', 'engineer'), $w('tpia', 'report')],
     ['Enquiry', 'Quotation', 'Purchase Order', 'Inspection Call', 'Inspection Assignment', 'Inspector', 'Inspection Report'], 'TPIA words (V2.1 §4)');
t_eq([$w('techmanpower', 'call'), $w('techmanpower', 'job'), $w('techmanpower', 'engineer'), $w('techmanpower', 'requisition'), $w('techmanpower', 'client')],
     ['Manpower Requirement', 'Deployment', 'Deployed Engineer', 'Job Order', '(default Client)'], 'technical manpower words — "Deployed Engineer"; the customer stays "Client" (V2.1 §5, gap G1)');
t_eq([$w('recruitplace', 'inquiry'), $w('recruitplace', 'quote'), $w('recruitplace', 'order'), $w('recruitplace', 'requisition')],
     ['Enquiry', 'Fee Proposal', 'Mandate', 'Job Requisition'], 'recruitment words — the naming clash solved (V2.1 §6)');
t_eq([$w('audit', 'vendor'), $w('audit', 'quote'), $w('audit', 'order'), $w('audit', 'call'), $w('audit', 'job'), $w('audit', 'engineer'), $w('audit', 'manday')],
     ['Auditee', 'Proposal', 'Engagement', 'Audit Assignment', 'Audit Visit', 'Auditor', 'Audit Day'], 'audit words (V2.1 §7)');
foreach (['tpia', 'techmanpower', 'recruitplace', 'audit'] as $pk)
    foreach (TERM_PACKS[$pk]['terms'] as $k => $pair)
        t_ok(isset(TERM_DEFAULTS[$k]) && strpos($pair[0], '/') === false || $k === 'boss',
            "$pk · '$k' is a known word and a single word, not a slash pair (\"{$pair[0]}\")");

// ---- 4. Replaced templates and packs: kept for existing companies, not offered anew
t_ok(!isset(industry_choices('inspection')['staffing']), 'the shared "Recruitment & staffing" template is not offered for a new choice');
t_ok(isset(industry_choices('staffing')['staffing']), '…but a company already on it still sees it');
t_ok(isset(INDUSTRY_TEMPLATES['staffing']) && INDUSTRY_TERM_MAP['staffing'] === 'manpower', '…and it keeps its own words');
foreach ($V21 as $k) t_ok(isset(industry_choices('')[$k]), "$k is offered for a new choice");
$offered = term_packs_offered('');
t_ok(!isset($offered['inspection']) && !isset($offered['manpower']) && !isset($offered['recruitment']), 'the three replaced wording packs are not offered anew');
t_ok(isset($offered['tpia'], $offered['techmanpower'], $offered['recruitplace'], $offered['audit'], $offered['labs']), 'the V2.1 packs and the others are');
t_ok(isset(term_packs_offered('manpower')['manpower']), 'a company using a replaced pack still sees it as its own');

// ---- 5. Applying: creates, never edits; stamps the version; units ---------
$pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
$savedTerms = (string) setting_get('terms', ''); $savedPack = (string) setting_get('terms_pack', '');
try {
    t_as_admin(); leads_migrate(); opp_migrate();
    // An existing company: its own pipeline with a deal on it, and its own words.
    $pdo->prepare("INSERT INTO pipelines (name,entity_kind,is_default,active,created_by,created_at) VALUES ('V21 own funnel','OPPORTUNITY',1,1,'t',?)")->execute([date('c')]);
    $pid = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO pipeline_stages (pipeline_id,seq,name,kind,probability,sla_days,active) VALUES (?,1,'Our own stage','OPEN',33,5,1)")->execute([$pid]);
    $sid = (int) $pdo->lastInsertId();
    $before = ops_all("SELECT id,name,kind,probability,sla_days FROM pipeline_stages WHERE pipeline_id=? ORDER BY id", [$pid]);
    $ov = ['engineer' => ['Our Expert', 'Our Experts']]; setting_set('terms', json_encode($ov)); setting_set('terms_pack', ''); term_overrides($ov);
    $nPipes = (int) ops_val("SELECT COUNT(*) FROM pipelines");

    $r = industry_apply_pipelines('manpower');
    t_ok(!empty($r['ok']), 'technical manpower pipelines added');
    t_eq((int) ops_val("SELECT COUNT(*) FROM pipelines"), $nPipes + 3, '…as three NEW pipelines (main, extensions, leads)');
    t_eq(ops_all("SELECT id,name,kind,probability,sla_days FROM pipeline_stages WHERE pipeline_id=? ORDER BY id", [$pid]), $before, 'the company\'s own funnel and its stage are untouched');
    t_eq((string) ops_val("SELECT name FROM pipelines WHERE id=?", [$pid]), 'V21 own funnel', '…and keeps its name');
    t_eq(T('engineer'), 'Our Expert', 'adding pipelines never changes the company\'s wording');
    t_eq((string) setting_get('industry_pipelines_version', ''), 'manpower@1', 'the pipelines carry the template version stamp');
    $stages = array_column(ops_all("SELECT s.name FROM pipeline_stages s JOIN pipelines p ON p.id=s.pipeline_id WHERE p.name=? ORDER BY s.seq", ['Technical manpower & deputation — new business']) ?: [], 'name');
    t_eq($stages, array_column(INDUSTRY_TEMPLATES['manpower']['funnel'], 0), 'the new funnel has exactly the template\'s stages');

    // Making it the company's industry: wording + stamp + units.
    $r = industry_set_company('manpower');
    t_ok(!empty($r['ok']) && $r['accreditation_pack'] === '', 'technical manpower set as the company\'s industry, no accreditation rules');
    t_eq([T('engineer'), T('job'), T('call'), T('client')], ['Deployed Engineer', 'Deployment', 'Manpower Requirement', 'Client'], 'every screen now reads the manpower words');
    t_eq((string) setting_get('industry_template_version', ''), 'manpower@1', 'the company\'s industry version is stamped');
    $codes = array_column(ops_all("SELECT v.code FROM lookup_values v JOIN lookup_types t ON t.id=v.type_id WHERE t.type_key='charge_unit' AND v.active=1") ?: [], 'code');
    t_ok(in_array('HOUR', $codes, true) && in_array('SHIFT', $codes, true), 'Per hour and Per shift are added to the charge units');
    t_ok(in_array('MANDAY', $codes, true) && in_array('MANMONTH', $codes, true) && in_array('LOT', $codes, true), 'the standard units stay (Man-day, Man-month, Lump sum)');
    t_eq(default_charge_unit(), 'MANDAY', 'a new manpower line starts in Man-day');
    t_eq((int) ops_val("SELECT COUNT(*) FROM pipelines"), $nPipes + 3, 'setting the industry creates no pipeline');

    industry_set_company('audit');
    t_eq([T('vendor'), T('manday'), T('quote')], ['Auditee', 'Audit Day', 'Proposal'], 'audit: Auditee, Audit Day, Proposal');
    t_eq(default_charge_unit(), 'AUDIT_DAY', 'a new audit line starts in Audit day');
    industry_set_company('recruitment');
    t_eq([T('inquiry'), T('quote'), T('order'), T('requisition')], ['Enquiry', 'Fee Proposal', 'Mandate', 'Job Requisition'], 'recruitment words on every screen');
    t_eq(default_charge_unit(), 'LOT', 'a new recruitment fee line starts as a lump sum');
    t_eq([chain_stages()['QUOTE'][0], chain_stages()['INQUIRY'][0]], ['Fee proposal', 'Enquiry'], 'the record chain strip reads the recruitment words too');
    industry_set_company('inspection');
    t_eq([T('job'), T('engineer'), T('order')], ['Inspection Assignment', 'Inspector', 'Purchase Order'], 'TPIA words on every screen');
    t_eq(chain_stages()['REPORT'][0], 'Inspection report', 'the chain strip names the TPIA report');
    t_eq((string) setting_get('packs_enabled', ''), 'inspection', 'TPIA keeps the ISO/IEC 17020 accreditation rules on');
    t_eq((string) setting_get('industry_template_version', ''), 'inspection@2', 'TPIA is version 2 of the inspection template');

    // The replaced template still works for a company on it.
    $n0 = (int) ops_val("SELECT COUNT(*) FROM pipelines");
    $r = industry_apply('staffing');
    t_ok(!empty($r['ok']), 'the shared "Recruitment & staffing" template still applies for a company already on it');
    t_eq((string) setting_get('terms_pack', ''), 'manpower', '…with its own wording, unchanged');
    t_eq(T('client'), 'Principal Employer', '…("Principal Employer", as before)');
    t_ok((int) ops_val("SELECT COUNT(*) FROM pipelines") > $n0, '…and builds its pipelines');
    t_eq((string) setting_get('industry_template_version', ''), 'staffing@1', 'an older template counts as version 1');
} finally {
    if ($own && $pdo->inTransaction()) $pdo->rollBack();
    setting_set('terms', $savedTerms); setting_set('terms_pack', $savedPack);
    term_overrides($savedTerms ? (json_decode($savedTerms, true) ?: []) : []);
}

// ---- 6. The screens offer what the rule says ------------------------------
$src = (string) file_get_contents(__DIR__ . '/../lib/industry.php');
t_ok(strpos($src, "'templates' => industry_choices()") !== false, 'the Your industry screen lists industry_choices(), not every template');
t_ok(strpos((string) file_get_contents(__DIR__ . '/../views/ops/setup.php'), 'industry_choices(') !== false, 'the first-time setup offers the same choices');
t_ok(strpos($src, '!industry_offered($key)') !== false, 'applying a replaced template by hand is refused unless it is the company\'s own');
t_ok(strpos((string) file_get_contents(__DIR__ . '/../lib/terms.php'), "'packs'  => term_packs_offered()") !== false, 'the Terminology screen offers term_packs_offered()');
