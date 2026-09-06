<?php
// Org-chart / organogram import — paste or CSV → positions with reporting links.
// Header-aware, re-runnable (upsert by code/name), two-pass parent linking. Additive.
t_section('org chart import');

position_migrate();
$pdo = db();
// Start from a clean slate for this test's codes.
$pdo->prepare("DELETE FROM positions WHERE code IN ('IP1','IP2','IP3','IP4')")->execute();

// --- Parsing: no header, comma-separated, fixed column order ---
$csv = "CEO, IP1, Executive, E1, , 1, 1\n"
     . "HR Director, IP2, Human Resources, M4, IP1, 1, 1\n"
     . "HR Manager, IP3, Human Resources, M2, IP2, 2, 1\n"
     . "Recruiter, IP4, Human Resources, M1, IP2, 3, 2";
$rows = positions_import_parse($csv);
t_eq(count($rows), 4, 'four rows are parsed from the pasted list');
t_eq($rows[1]['name'], 'HR Director', 'the name column is read');
t_eq($rows[1]['reports_to'], 'IP1', 'the reports-to column is read');
t_eq($rows[2]['sanctioned'], 2, 'the sanctioned headcount is read as a number');

// --- Header-aware parsing + tab separation ---
$tsv = "Name\tCode\tReports To\tGrade\nOps Head\tIP1\t\tM4\nAnalyst\tIP4\tOps Head\tM1";
$hrows = positions_import_parse($tsv);
t_eq(count($hrows), 2, 'a header row is detected and skipped');
t_eq($hrows[1]['reports_to'], 'Ops Head', 'columns are mapped by header name, not position');

// --- Apply: creates positions and links the tree ---
$res = positions_import_apply($rows);
t_eq($res['created'], 4, 'all four positions are created');
t_eq($res['linked'], 3, 'three reporting links are made (the CEO is the root)');
t_eq(count($res['unresolved']), 0, 'every reports-to reference resolved');
$hrm = ops_one("SELECT * FROM positions WHERE code='IP3'");
$hrd = ops_one("SELECT id FROM positions WHERE code='IP2'");
t_eq((int)$hrm['reports_to_id'], (int)$hrd['id'], 'HR Manager reports to HR Director');
$ceo = ops_one("SELECT * FROM positions WHERE code='IP1'");
t_ok((int)($ceo['reports_to_id'] ?? 0) === 0, 'the CEO sits at the top (no manager)');

// --- Re-running updates rather than duplicating ---
$csv2 = "CEO, IP1, Executive, E1, , 2, 1";   // sanctioned changed 1 → 2
$res2 = positions_import_apply(positions_import_parse($csv2));
t_eq($res2['created'], 0, 're-importing an existing code creates nothing new');
t_eq($res2['updated'], 1, 'the existing position is updated');
t_eq((int)ops_val("SELECT COUNT(*) FROM positions WHERE code='IP1'"), 1, 'no duplicate CEO position is created');
t_eq((int)ops_val("SELECT sanctioned_headcount FROM positions WHERE code='IP1'"), 2, 'the updated headcount is saved');

// --- An unresolved manager is reported, not silently dropped ---
$res3 = positions_import_apply(positions_import_parse("Lonely Role, IP4, HR, M1, NoSuchManager, 1, 0"));
t_ok(count($res3['unresolved']) >= 1, 'a reports-to that matches nothing is flagged as unresolved');

// Clean up.
$pdo->prepare("DELETE FROM positions WHERE code IN ('IP1','IP2','IP3','IP4') OR name IN ('Ops Head','Analyst','Lonely Role')")->execute();
