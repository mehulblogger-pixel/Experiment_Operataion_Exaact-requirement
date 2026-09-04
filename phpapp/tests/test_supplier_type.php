<?php
// Marketplace supplier type (§19). A professional reaches the market either
// directly (Individual Supplier) or through a supplier ORGANISATION whose kind —
// TPIA / Technical Manpower / Freelance Resource / Recruitment / Technical
// Services — the client should see. Read-only over the agency bench link.
t_section('marketplace supplier type');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_bench_migrate();
    if (function_exists('connect_org_migrate')) connect_org_migrate();
    if (function_exists('connect_cap_migrate')) connect_cap_migrate();

    // a professional with no agency link → Individual
    db()->prepare("INSERT INTO cx_professionals (email,name,is_active,created_at) VALUES ('sup.pro@demo.test','Sup Pro',1,?)")->execute([date('c')]);
    $pro = (int)db()->lastInsertId();
    $s = connect_supplier_type($pro);
    t_eq($s['channel'], 'INDIVIDUAL', 'a professional with no agency link is an Individual supplier');

    // a manpower agency puts them on its bench → Technical Manpower Supplier
    db()->prepare("INSERT INTO cx_organisations (name,org_type,party_id,status,created_at) VALUES ('Patel Manpower','MANPOWER_AGENCY',0,'ACTIVE',?)")->execute([date('c')]);
    $org = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO cx_bench (org_id,name,professional_id,is_active,created_at) VALUES (?,?,?,1,?)")->execute([$org, 'Sup Pro', $pro, date('c')]);
    $s2 = connect_supplier_type($pro);
    t_eq($s2['channel'], 'ORG', 'a benched professional is supplied through an organisation');
    t_eq($s2['type'], 'Technical Manpower Supplier', 'a MANPOWER_AGENCY reads as Technical Manpower Supplier');
    t_eq($s2['org_name'], 'Patel Manpower', 'the supplying organisation is named');

    // give that org a party + FREELANCE_SUPPLY capability → Freelance Resource Supplier
    db()->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_vendor,status,created_at) VALUES ('SUP-CO','Patel Manpower','Patel Manpower',1,'ACTIVE',?)")->execute([date('c')]);
    $party = (int)db()->lastInsertId();
    db()->prepare("UPDATE cx_organisations SET party_id=? WHERE id=?")->execute([$party, $org]);
    if (function_exists('connect_org_cap_bulk_set')) connect_org_cap_bulk_set($party, ['FREELANCE_SUPPLY'], 'test');
    t_eq(connect_supplier_type($pro)['type'], 'Freelance Resource Supplier', 'a FREELANCE_SUPPLY capability refines the label');

    // an inactive bench row is ignored → back to Individual
    db()->prepare("UPDATE cx_bench SET is_active=0 WHERE professional_id=?")->execute([$pro]);
    t_eq(connect_supplier_type($pro)['channel'], 'INDIVIDUAL', 'an inactive bench link no longer supplies them');

    // label vocabulary + unknown-pro safety
    t_ok(count(connect_supplier_type_labels()) >= 5, 'the supplier-type label vocabulary is present');
    t_eq(connect_supplier_type(0)['channel'], 'INDIVIDUAL', 'an unknown professional is Individual (no error)');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
