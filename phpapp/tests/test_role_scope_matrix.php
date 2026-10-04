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
t_eq($insp, ['mod.idems.view','mod.idems.edit'], '*** INSPECTOR holds reports and nothing else');

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

t_section('RECORDED DEVIATION — Finance sees operational registers (open decision D-07)');
//  The matrix gives FINANCE "—" on Vouchers, yet the engine grants
//  mod.vouchers.view (access.php role_defaults_base, FINANCE $view list). It
//  also grants jobs/idems view; the matrix marks Jobs "⚠ implicit".
//  This is what a Finance user reported seeing as "an inspector's workspace".
//  Asserted as the CURRENT state, not as the intent, so the deviation is
//  visible in the suite instead of hiding. When D-07 is decided, this section
//  and the matrix move together — never one without the other.
t_ok(in_array('mod.vouchers.view', $fin, true),
     'DEVIATION: FINANCE holds mod.vouchers.view while the matrix says "—"');
t_ok(in_array('mod.jobs.view', $fin, true),
     'DEVIATION: FINANCE holds mod.jobs.view (matrix marks this implicit)');
t_ok(in_array('mod.idems.view', $fin, true),
     'DEVIATION: FINANCE holds mod.idems.view (inspection reports)');
