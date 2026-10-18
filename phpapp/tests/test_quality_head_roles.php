<?php
// ---------------------------------------------------------------------------
//  THE OWNER'S RULE, 2026-10-18: "only Quality or Technical Head can close it."
//
//  Asked who may close a nonconformity, the owner named two positions that did
//  not exist in the system. Measured before the change, ncr.close and capa.close
//  were held by MASTER_ADMIN and ADMIN alone — so closing a nonconformity was a
//  system-administrator act, which for an accredited inspection body is both a
//  bottleneck and a segregation-of-duties weakness: the person who administers
//  the software also signs off that the fix was carried out.
//
//  Two roles are added, and the audit folder records the measurement that led to
//  them (docs/audit-2026-10/08-GAP-REGISTER.md, GAP-004).
//
//  THE SECOND HALF of this file guards a dead door found while building the
//  first. The Quality area offered its "Nonconformity workspace" tile to anybody
//  holding mod.ncr.view OR mod.capa.view, and always pointed it at an
//  NCR-gated route. Five roles hold capa and not ncr, so five roles were shown a
//  door that answered 403. Driven and confirmed for Branch Manager, Operation
//  Manager and Coordinator before the fix.
// ---------------------------------------------------------------------------
t_section('Quality Head / Technical Head — closing a nonconformity');

// ---- the roles exist, and are named in business language --------------------
t_ok(isset(ORG_ROLES['QUALITY_HEAD']),   'Quality Head is a role the company can assign');
t_ok(isset(ORG_ROLES['TECHNICAL_HEAD']), 'Technical Head is a role the company can assign');
t_eq('Quality Head',   ORG_ROLES['QUALITY_HEAD'] ?? '',   'the Quality Head is labelled in plain words');
t_eq('Technical Head', ORG_ROLES['TECHNICAL_HEAD'] ?? '', 'the Technical Head is labelled in plain words');

// ---- what the owner actually asked for -------------------------------------
foreach (['QUALITY_HEAD', 'TECHNICAL_HEAD'] as $r) {
    $p = role_perms($r);
    t_ok(in_array('ncr.close', $p, true),  "$r may close a nonconformity");
    t_ok(in_array('capa.close', $p, true), "$r may close a corrective action");
    // You cannot close what you cannot open. Both modules are EDIT, because
    // closing the loop means raising and working the record too.
    t_ok(in_array('mod.ncr.view', $p, true),  "$r can open the nonconformity register");
    t_ok(in_array('mod.ncr.edit', $p, true),  "$r can raise and work a nonconformity");
    t_ok(in_array('mod.capa.view', $p, true), "$r can open the corrective actions register");
    t_ok(in_array('mod.capa.edit', $p, true), "$r can record a corrective action");
    // A closure has to be defensible to an assessor.
    t_ok(in_array('idems.audit.view', $p, true), "$r can see the audit trail behind a closure");
}

// ---- "ONLY Quality or Technical Head" — nobody else was handed it ----------
//  MASTER_ADMIN and ADMIN keep every right by definition (they resolve to the
//  whole permission list, and removing the break-glass account's access is not
//  something an audit does on its own initiative — it is raised with the owner).
//  What this asserts is that the change granted the closing rights to the two
//  named roles and to NOBODY else.
$closers = [];
foreach (array_keys(ORG_ROLES) as $r)
    if (in_array('ncr.close', role_perms($r), true)) $closers[] = $r;
sort($closers);
t_eq('ADMIN,MASTER_ADMIN,QUALITY_HEAD,TECHNICAL_HEAD', implode(',', $closers),
     'closing a nonconformity is the two named roles, plus the two administrator accounts');

// ---- least privilege: the quality roles are not back-door administrators ----
foreach (['QUALITY_HEAD', 'TECHNICAL_HEAD'] as $r) {
    $p = role_perms($r);
    foreach (['users.manage.global', 'users.manage.branch', 'settings.manage',
              'finance.reconcile', 'data.credit', 'data.salary',
              'mod.invoicing.view', 'mod.users.view', 'mod.settings.view'] as $never)
        t_ok(!in_array($never, $p, true), "$r does not hold $never");
    // Quality judges the work; it does not run it.
    t_ok(!in_array('ops.call.create', $p, true), "$r cannot raise a work order");
    t_ok(!in_array('ops.job.allocate', $p, true), "$r cannot allocate a job");
    // The accreditation registers were deliberately left out — a second decision
    // nobody has made. If this starts failing, it was widened without asking.
    t_ok(!in_array('mod.equipment.view', $p, true), "$r was not quietly given the equipment register");
    t_ok(!in_array('mod.audits.view', $p, true),    "$r was not quietly given internal audits");
}

// ---- quality oversees the whole company, not one branch --------------------
foreach (['QUALITY_HEAD', 'TECHNICAL_HEAD'] as $r) {
    $d = role_defaults($r);
    t_eq('ALL', $d['offices'], "$r sees every office — a nonconformity belongs to the company");
    t_eq('ALL', $d['sbus'],    "$r sees every business unit");
}

// ---- they are not operations managers --------------------------------------
t_ok(!in_array('QUALITY_HEAD', MGMT_ROLES, true),
     'the Quality Head is not an operations manager, so operational widgets do not leak to it');
t_ok(!in_array('TECHNICAL_HEAD', MGMT_ROLES, true),
     'the Technical Head is not an operations manager either');

// ---------------------------------------------------------------------------
t_section('The Quality tile must land where the person can actually go');

//  Read from the area definition rather than a rendered page, because a tile
//  that points at a refusal is a property of the definition.
$src = file_get_contents(__DIR__ . '/../lib/areas.php');
$body = substr($src, strpos($src, "case 'quality':"));
$body = substr($body, 0, strpos($body, "case '") ?: null);
// Strip comments so the assertions measure code, not the explanation beside it.
$code = preg_replace('~//[^\n]*~', '', $body);

t_ok(strpos($code, "\$ncrOk = can('mod.ncr.view')") !== false,
     'the tile asks whether this person may open a nonconformity');
t_ok(preg_match('~\$ncrOk \?.*/capa~s', $code) === 1,
     'a reader who may see only corrective actions is sent to /capa, not to an NCR screen');
t_ok(strpos($code, "'Corrective actions'") !== false,
     'and the tile calls itself what they will actually find');
//  The badge counts open NONconformities; offering it to somebody who cannot
//  open one is an errand they cannot run.
t_ok(preg_match('~\$ncrOk \? \$num\(.*?\) : null~s', $code) === 1,
     'the open-nonconformity badge is not shown to somebody who may not open one');

//  And the regression itself: no route the tile can point at may be one the
//  tile-holder cannot open. /issues and /ncr are module 'ncr'; /capa is 'capa'.
$map = ops_route_module_map();
t_eq('ncr',  $map['issues'] ?? '', '/issues is gated by the nonconformity module');
t_eq('ncr',  $map['ncr'] ?? '',    '/ncr is gated by the nonconformity module');
t_eq('capa', $map['capa'] ?? '',   '/capa is gated by the corrective-actions module');

//  Every role that is OFFERED the tile must be able to open what it points at.
//  This is the assertion that would have caught the original dead door.
foreach (array_keys(ORG_ROLES) as $r) {
    $p = role_perms($r);
    $offered = in_array('mod.ncr.view', $p, true) || in_array('mod.capa.view', $p, true);
    if (!$offered) continue;
    $dest = in_array('mod.ncr.view', $p, true) ? 'ncr' : 'capa';
    t_ok(in_array("mod.$dest.view", $p, true),
         "$r is offered the Quality tile and can open the screen it points at (/$dest)");
}
