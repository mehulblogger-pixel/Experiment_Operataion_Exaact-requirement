<?php
// ============================================================================
//  ROLE DEFAULTS vs THE PERMISSION MATRIX            (UAT 1.5.4)
// ============================================================================
//  docs/02-permission-matrix.md is the sole authority on who-can-do-what, and
//  CLAUDE.md forbids granting a role anything the matrix does not list. Nothing
//  was checking that, so the two were free to drift apart silently — and had.
//
//  This file asks the engine what each role actually receives and compares it
//  with the matrix row. It covers the four roles the business UAT signs in as.
//
//  WHAT A FAILURE HERE MEANS: either the code granted something the business
//  never agreed to, or the matrix was changed without the code. Both are
//  defects. Neither is fixed by editing this file until the matrix says so.
// ============================================================================
t_section('Role office scope matches the matrix');

$scope = fn($r) => role_defaults($r)['offices'] ?? '';
//  Finance reconciles for the whole company, so company-wide scope is the
//  decision, not an oversight — recorded here so it is never "fixed" by mistake.
t_eq($scope('FINANCE'),        'ALL', '*** FINANCE is company-wide by design (matrix: reconciles across offices)');
t_eq($scope('COORDINATOR'),    'OWN', '*** COORDINATOR is confined to their own office');
t_eq($scope('INSPECTOR'),      'OWN', '*** INSPECTOR is confined to their own office');
t_eq($scope('BRANCH_MANAGER'), 'OWN', '*** BRANCH_MANAGER is confined to their own office');

t_section('Inspector holds no dashboard or money right');
$insp = role_defaults('INSPECTOR')['perms'];
foreach (['dash.operations','dash.financial','data.salary','data.revenue','finance.reconcile'] as $p)
    t_ok(!in_array($p, $insp, true), "*** INSPECTOR must not hold $p");
// R-20 — the old single "add / edit" tick became two verbs. An inspector who
// could add and change a report before must be able to add and change one now,
// so Add appears here. The set is still reports and nothing else, and the
// inspector gains NO Archive, Delete or Approve: those are new rights that have
// to be granted deliberately.
t_eq($insp, ['mod.idems.view','mod.idems.add','mod.idems.edit'],
     '*** INSPECTOR holds reports and nothing else');
foreach (['archive','delete'] as $v)
    t_ok(!in_array("mod.idems.$v", $insp, true),
         "*** INSPECTOR gained no mod.idems.$v from the verb split");
t_ok(!in_array('idems.finalize', $insp, true),
     '*** INSPECTOR still cannot sign off their own report (ISO 17020 separation)');

t_section('Finance holds the money rights the matrix grants');
$fin = role_defaults('FINANCE')['perms'];
foreach (['dash.financial','data.revenue','data.profitability','finance.reconcile',
          'crm.contract.register','mod.invoicing.view','mod.invoicing.edit'] as $p)
    t_ok(in_array($p, $fin, true), "FINANCE holds $p");

t_section('Finance is not handed operational WRITE rights');
foreach (['mod.calls.edit','mod.jobs.edit','mod.idems.edit','mod.vouchers.edit',
          'mod.hiring.view','mod.hiring.edit','ops.call.create','ops.job.allocate',
          'dash.operations','users.manage.branch','master.manage'] as $p)
    t_ok(!in_array($p, $fin, true), "*** FINANCE must not hold $p");

t_section('D-07 — what Finance reads, now that code and matrix agree');
//  Decided 2026-10-04 (docs/02-permission-matrix.md, note above the module table).
//  Finance bills from jobs and calls and pays vouchers, so all three are money and
//  the matrix now states View. Inspection reports carry no figure and were removed:
//  carrying them made a finance login open on an operations menu (UAT 1.5.4).
t_ok(in_array('mod.vouchers.view', $fin, true), '*** FINANCE reads vouchers — it pays them (matrix: View)');
t_ok(in_array('mod.jobs.view',     $fin, true), '*** FINANCE reads jobs — it bills from them (matrix: View)');
t_ok(in_array('mod.calls.view',    $fin, true), '*** FINANCE reads calls — it bills from them (matrix: View)');
t_ok(!in_array('mod.idems.view',   $fin, true), '*** FINANCE does NOT read inspection reports (D-07 removed them)');
t_ok(!in_array('mod.idems.edit',   $fin, true), '*** FINANCE cannot write inspection reports');
