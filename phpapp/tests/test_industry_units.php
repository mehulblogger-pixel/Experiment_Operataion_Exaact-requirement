<?php
// Owner, 12 Nov 2026 (test book, Profile E) — "Unit remains the same for trading
// and distribution and all other Industry types as of Inspection and Testing."
t_section('Each industry brings the units its trade bills in');
$pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
try {
    t_as_admin();
    $codes = function () { $t = lk_type('charge_unit'); return $t ? array_column(ops_all("SELECT code FROM lookup_values WHERE type_id=? AND active=1", [(int)$t['id']]) ?: [], 'code') : []; };
    $n = industry_seed_units('trading');
    $c = $codes();
    t_ok(in_array('NOS', $c, true) && in_array('CARTON', $c, true) && in_array('KG', $c, true), 'a trader can quote in Nos, Carton, Kg … (' . $n . ' units added)');
    t_ok(in_array('MANDAY', $c, true) && in_array('LOT', $c, true), 'the service units stay, for installation or inspection lines');
    t_eq(default_charge_unit(), 'NOS', 'a new line on a trader\'s quotation starts in Nos');
    t_eq(industry_seed_units('trading'), 0, 'applying it again adds nothing twice');
    industry_seed_units('construction');
    t_ok(in_array('STAGE', $codes(), true) && in_array('SQM', $codes(), true), 'a construction company gets "Per stage / milestone" and square metres');
    industry_seed_units('labs');
    t_ok(in_array('SAMPLE', $codes(), true), 'a laboratory gets "Per sample"');
    industry_seed_units('inspection');
    t_eq(default_charge_unit(), 'MANDAY', 'back to inspection: new lines start in Man-day again');
    t_ok(!in_array('MONTH', array_merge(...array_map('array_keys', array_values(INDUSTRY_UNITS))), true), 'no unit uses the old code "MONTH", which an earlier migration turns into Man-month');
    $r = industry_set_company('trading');
    t_ok(!empty($r['ok']) && array_key_exists('units_added', $r), 'making Trading the company\'s industry adds its units too');
    t_eq(industry_seed_list('lead_source', 'Lead source', ['Probe exhibition ' . getmypid()]), 1, 'an industry\'s lead sources and lost reasons are really added now (they silently failed before)');
    t_ok(strpos((string) file_get_contents(__DIR__ . '/../views/ops/crm/quote_form.php'), 'default_charge_unit()') !== false, 'the quotation form starts new lines in the industry\'s unit');
} finally {
    if ($own && $pdo->inTransaction()) $pdo->rollBack();
}
