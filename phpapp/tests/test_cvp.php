<?php
// ============================================================================
//  CVP Slice 1 — the confidentiality spine.
//  Proves: the visibility gate maps issue-visibility codes to the right external
//  audience (and never leaks INTERNAL/RESTRICTED/MGMT_ONLY); the SQL builder
//  filters a real register down to exactly what an audience may see; and report
//  / QAP files are now served through an object-level authorization helper that
//  carries the report's office+business-unit scope (no more fetch-any-by-id).
// ============================================================================
$__standalone = empty($GLOBALS['__test_db']);
if ($__standalone) { require __DIR__ . '/lib.php'; require __DIR__ . '/bootstrap.php'; }
if (!function_exists('ok'))   { function ok($cond, $msg) { return t_ok($cond, $msg); } }
if (!function_exists('head')) { function head($t) { t_section($t); } }

if (function_exists('cvp_migrate'))   cvp_migrate();
if (function_exists('ncr_migrate'))   ncr_migrate();
if (function_exists('ncdca_migrate')) ncdca_migrate();

head('1. Row gate — an external audience sees only what is marked for it');
ok(cvp_can_see('INTERNAL', 'INTERNAL') === true,       'staff (INTERNAL) see an internal-only row');
ok(cvp_can_see('INTERNAL', 'CLIENT') === false,        'a client never sees an INTERNAL row');
ok(cvp_can_see('INTERNAL', 'VENDOR') === false,        'a vendor never sees an INTERNAL row');
ok(cvp_can_see('RESTRICTED', 'CLIENT') === false,      'RESTRICTED is not external');
ok(cvp_can_see('MGMT_ONLY', 'CLIENT') === false,       'MGMT_ONLY is not external');
ok(cvp_can_see('CLIENT_VISIBLE', 'CLIENT') === true,   'a client sees a CLIENT_VISIBLE row');
ok(cvp_can_see('CLIENT_VISIBLE', 'VENDOR') === false,  'a vendor does NOT see a client-visible row');
ok(cvp_can_see('VENDOR_VISIBLE', 'VENDOR') === true,   'a vendor sees a VENDOR_VISIBLE row');
ok(cvp_can_see('VENDOR_VISIBLE', 'CLIENT') === false,  'a client does NOT see a vendor-visible row');
ok(cvp_can_see('', 'CLIENT') === false,                'a blank/unknown visibility defaults to NOT shown');
ok(cvp_can_see('SHARED', 'CLIENT') === true && cvp_can_see('SHARED', 'VENDOR') === true, 'a SHARED row is visible to both parties');

head('2. Staff always pass; the gate never hides a row from INTERNAL');
foreach (['INTERNAL','RESTRICTED','MGMT_ONLY','CLIENT_VISIBLE','VENDOR_VISIBLE',''] as $v) {
    if (!cvp_can_see($v, 'INTERNAL')) { ok(false, "staff see visibility=$v"); }
}
ok(true, 'staff see every visibility code (no internal screen is broken)');

head('3. The SQL builder filters a real register to the audience');
// Seed three nonconformities, one per visibility, and read them back through
// the audience filter — the security property, proven against the database.
$pid = 90210;
db()->exec("DELETE FROM nonconformities WHERE partner_id=$pid");
$ins = db()->prepare("INSERT INTO nonconformities (ref,partner_id,title,visibility,status,created_at) VALUES (?,?,?,?, 'OPEN', ?)");
$ins->execute(['CVP/INT', $pid, 'internal only',  'INTERNAL',       date('c')]);
$ins->execute(['CVP/CLI', $pid, 'client visible',  'CLIENT_VISIBLE', date('c')]);
$ins->execute(['CVP/VEN', $pid, 'vendor visible',  'VENDOR_VISIBLE', date('c')]);

[$vw, $va] = cvp_visibility_sql('visibility', 'CLIENT');
$cli = ops_all("SELECT ref FROM nonconformities WHERE partner_id=? AND $vw ORDER BY ref", array_merge([$pid], $va));
ok(count($cli) === 1 && $cli[0]['ref'] === 'CVP/CLI', 'the CLIENT filter returns only the client-visible NCR');

[$vw, $va] = cvp_visibility_sql('visibility', 'VENDOR');
$ven = ops_all("SELECT ref FROM nonconformities WHERE partner_id=? AND $vw ORDER BY ref", array_merge([$pid], $va));
ok(count($ven) === 1 && $ven[0]['ref'] === 'CVP/VEN', 'the VENDOR filter returns only the vendor-visible NCR');

[$vw, $va] = cvp_visibility_sql('visibility', 'INTERNAL');
$all = ops_all("SELECT ref FROM nonconformities WHERE partner_id=? AND $vw", array_merge([$pid], $va));
ok(count($all) === 3, 'the INTERNAL (staff) filter returns all three');

// An audience with no visible codes must match NOTHING, never everything.
[$vw, $va] = cvp_visibility_sql('visibility', 'STRANGER');
$none = ops_all("SELECT ref FROM nonconformities WHERE partner_id=? AND $vw", array_merge([$pid], $va));
ok(count($none) === 0, 'an unknown audience sees nothing (fails safe, not open)');
db()->exec("DELETE FROM nonconformities WHERE partner_id=$pid");

head('4. The client int-flag gate (dep_site_log.client_visible)');
[$fw, $fa] = cvp_client_flag_sql('client_visible', 'CLIENT');
ok(strpos($fw, 'client_visible') !== false, 'the CLIENT flag gate tests client_visible=1');
[$fw, $fa] = cvp_client_flag_sql('client_visible', 'VENDOR');
ok($fw === '1=0', 'a vendor sees nothing through the client-only flag');
[$fw, $fa] = cvp_client_flag_sql('client_visible', 'INTERNAL');
ok($fw === '1=1', 'staff pass the flag gate');

head('5. Report files are served through object-level authorization');
db()->prepare("INSERT INTO report_docs (irn,client_id,office_id,status,finalized,deleted,created_at,updated_at) VALUES ('CVP/RD/1',?, 7, 'ISSUED', 1, 0, ?, ?)")
    ->execute([$pid, date('c'), date('c')]);
$rd = (int)db()->lastInsertId();
db()->prepare("INSERT INTO report_files (report_doc_id,field_key,kind,file_name,mime,data,created_at) VALUES (?, 'photo1','photo','shot.jpg','image/jpeg','data:image/jpeg;base64,AAAA', ?)")
    ->execute([$rd, date('c')]);
$fid = (int)db()->lastInsertId();
$row = idems_file_authorized($fid);
ok($row && (int)$row['id'] === $fid, 'an in-scope file resolves through idems_file_authorized()');
ok(idems_file_authorized(0) === null, 'a missing file id resolves to null (not a fetch-any bypass)');
// A deleted report hides its files.
db()->prepare("UPDATE report_docs SET deleted=1 WHERE id=?")->execute([$rd]);
ok(idems_file_authorized($fid) === null, 'a file on a deleted report is not served');
db()->exec("DELETE FROM report_files WHERE id=$fid");
db()->exec("DELETE FROM report_docs WHERE id=$rd");

head('6. QAP files are served through object-level authorization');
db()->prepare("INSERT INTO jobs (job_code, executing_office_id, stage, created_at) VALUES ('CVP-J1', 7, 'OPEN', ?)")->execute([date('c')]);
$jid = (int)db()->lastInsertId();
db()->prepare("INSERT INTO job_qaps (job_id,po_line,file_name,mime,data,uploaded_at) VALUES (?, 'L1','qap.pdf','application/pdf','data:application/pdf;base64,AAAA', ?)")
    ->execute([$jid, date('c')]);
$qid = (int)db()->lastInsertId();
$q = job_qap_authorized($qid);
ok($q && (int)$q['id'] === $qid, 'an in-scope QAP resolves through job_qap_authorized()');
ok(job_qap_authorized(0) === null, 'a missing QAP id resolves to null');
db()->exec("DELETE FROM job_qaps WHERE id=$qid");
db()->exec("DELETE FROM jobs WHERE id=$jid");

head('7. The handlers route through the authorization helpers (regression guard)');
$src = file_get_contents(__DIR__ . '/../lib/idems.php');
ok(strpos($src, 'idems_file_authorized((int)($_GET[\'id\']') !== false, 'ops_idems_file() goes through idems_file_authorized()');
ok(strpos($src, 'job_qap_authorized((int)($_GET[\'id\']') !== false, 'ops_job_qap_download() goes through job_qap_authorized()');
ok(strpos($src, "scope_clause('d.office_id', 'd.sbu')") !== false, 'file auth carries the report office/SBU scope');
ok(strpos($src, "scope_clause('j.executing_office_id', 'j.sbu')") !== false, 'QAP auth carries the job office/SBU scope');

if ($__standalone) {
    $g = $GLOBALS['__t'];
    echo "\n==================== CVP: {$g['pass']} passed, {$g['fail']} failed ====================\n";
    exit($g['fail'] === 0 ? 0 : 1);
}
