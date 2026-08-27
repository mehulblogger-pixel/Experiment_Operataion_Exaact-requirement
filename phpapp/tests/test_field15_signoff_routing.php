<?php
// Field-finding #15 — a report's sign-off block should auto-fill "Prepared by" (the inspector),
// "Reviewed by" (the technical vetter) and "Approved by" (the approval chain's approver) from the
// workflow. Prepared-by worked, but "Reviewed by" fell through to the inspector — so it showed the
// SAME person as Prepared-by, which read as "there is no way to set/route reviewer & approver."
// The routing exists (idems_report_signatures resolves inspector + vetter + approver + issuer, and
// /approver-map + /idems-approval-rules configure it); the resolver just wasn't wiring the roles.
t_section('Field #15 — sign-off block routes Reviewed-by / Approved-by from the workflow');

$sigs = [
    'inspector' => ['name' => 'Ivy Inspector', 'desig' => 'Sr. Inspector',     'img' => ''],
    'vetter'    => ['name' => 'Ravi Reviewer', 'desig' => 'Technical vetting',  'img' => ''],
    'approver'  => ['name' => 'Amir Approver', 'desig' => 'Branch Manager',     'img' => ''],
    'issuer'    => ['name' => 'Ida Issuer',    'desig' => 'Authorised issuer',  'img' => ''],
];
$f = ['fkey' => 'signoff', 'options' => "Prepared by\nReviewed by\nApproved by"];

$byRole = [];
foreach (idems_sigblock_rows($f, [], $sigs) as $r) $byRole[$r['role']] = $r;

t_eq('Ivy Inspector', $byRole['Prepared by']['name'],  'Prepared by = the inspector (unchanged)');
t_eq('Ravi Reviewer', $byRole['Reviewed by']['name'],  'Reviewed by = the technical vetter (not the inspector)');
t_ok($byRole['Reviewed by']['name'] !== $byRole['Prepared by']['name'], 'Reviewed-by is a DIFFERENT person from Prepared-by');
t_eq('Amir Approver', $byRole['Approved by']['name'],  'Approved by = the approval chain approver');

// Other report types name the first signer differently — those still map to the inspector.
$f2 = ['fkey' => 'signoff', 'options' => "Assessed by\nReviewed by\nApproved by\nIssued by"];
$b2 = [];
foreach (idems_sigblock_rows($f2, [], $sigs) as $r) $b2[$r['role']] = $r;
t_eq('Ivy Inspector', $b2['Assessed by']['name'], '"Assessed by" (competence/audit types) still maps to the inspector');
t_eq('Ida Issuer',    $b2['Issued by']['name'],   '"Issued by" maps to the authorised issuer');

// A manually keyed name on the report overrides the workflow default (author intent wins).
$data = ['signoff' => ['Reviewed by' => ['name' => 'Manual Name', 'desig' => 'By hand', 'date' => '2026-08-01']]];
$b3 = [];
foreach (idems_sigblock_rows($f, $data, $sigs) as $r) $b3[$r['role']] = $r;
t_eq('Manual Name', $b3['Reviewed by']['name'], 'a manually entered reviewer name is kept over the workflow default');
t_eq('2026-08-01',  $b3['Reviewed by']['date'], 'the manually entered date is kept');

// REGRESSION GUARD — when the report has NOT been vetted, "Reviewed by" is BLANK,
// never silently the inspector (the old bug). No false attestation of a review.
$noVet = ['inspector' => ['name' => 'Ivy Inspector', 'desig' => 'Sr. Inspector', 'img' => ''],
          'vetter' => [], 'approver' => [], 'issuer' => []];
$b4 = [];
foreach (idems_sigblock_rows($f, [], $noVet) as $r) $b4[$r['role']] = $r;
t_eq('', $b4['Reviewed by']['name'], 'un-vetted report leaves Reviewed-by blank (not the inspector)');
t_eq('', $b4['Approved by']['name'], 'un-approved report leaves Approved-by blank');
t_eq('Ivy Inspector', $b4['Prepared by']['name'], 'Prepared-by still fills (the inspection happened)');

// idems_report_signatures resolves a vetter role, so the source the resolver reads exists.
$src = file_get_contents(__DIR__ . '/../lib/idems.php');
t_ok(strpos($src, "\$out['vetter']") !== false && strpos($src, "\$vet = \$sigs['vetter']") !== false,
     'the workflow resolves a vetter and the sign-off resolver reads it');
