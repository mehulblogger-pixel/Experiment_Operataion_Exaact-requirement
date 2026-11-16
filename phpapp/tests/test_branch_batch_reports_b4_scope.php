<?php
// ============================================================================
//  B4 — DEF-REP-015 (P1). Who may open and edit an inspection report.
//
//  Before: the report page refused another branch ("outside your office /
//  branch scope") but typing /document-edit or /document-fill opened the form,
//  and saving it stored the change — and wiped the report's client and vendor,
//  because that branch's pickers did not list them.
//
//  The owner's rule (16 Nov 2026, Q6 "only before issue"): the ISSUING office
//  and the EXECUTING office of its work order open and edit it; the CONTRACTING
//  office may also open and edit it before issue, and each such edit is written
//  to the History ("Edited by Priya Probe (BBR Mumbai — contracting office):
//  Title, Location changed"). Everyone else is refused on view, PDF, files,
//  edit and fill.
// ============================================================================
require_once __DIR__ . '/_bb_reports_fixture.php';
t_section('B4 — a report is opened and edited by its issuing, executing and contracting office only (DEF-REP-015)');

$fx = bbr_fixture();
$id = bbr_report(['title' => 'BBR B4 original']);
$irn = (string) bbr_doc($id)['irn'];

// ---- Pune (no part in the work order) is refused everywhere --------------------------------
$r = bbr_drive($fx['coP'], 'document', 'GET', ['id' => $id], [], [$irn]);
t_ok(bbr_scope_refused($r) && empty($r['has'][$irn]), 'Pune Coordinator: the report page refuses it: ' . bbr_said($r));
$r = bbr_drive($fx['coP'], 'document-edit', 'GET', ['id' => $id], [], ['Save']);
t_ok(bbr_scope_refused($r), 'Pune Coordinator: typing the edit address is refused too: ' . bbr_said($r));
$r = bbr_drive($fx['coP'], 'document-edit', 'POST', ['id' => $id], bbr_edit_post($id, ['title' => 'BBR changed from Pune']));
t_eq((string) bbr_doc($id)['title'], 'BBR B4 original', 'Pune Coordinator: saving the edit form changes nothing: ' . bbr_said($r));
$r = bbr_drive($fx['coP'], 'document-fill', 'GET', ['id' => $id], [], ['Save report body']);
t_ok(bbr_scope_refused($r) && empty($r['has']['Save report body']), 'Pune Coordinator: the fill address is refused: ' . bbr_said($r));
$r = bbr_drive($fx['coP'], 'document-pdf', 'GET', ['id' => $id]);
t_ok(bbr_scope_refused($r), 'Pune Coordinator: the PDF is refused: ' . bbr_said($r));
// A file on the report.
db()->prepare("INSERT INTO report_files (report_doc_id, field_key, kind, file_name, mime, data, created_by, created_at) VALUES (?,?,?,?,?,?,?,?)")
    ->execute([$id, 'photos', 'photo', 'bbr.txt', 'text/plain', 'data:text/plain;base64,' . base64_encode('BBR-FILE-BYTES'), 'Neha Probe', date('c')]);
$fileId = (int) db()->lastInsertId();
$r = bbr_drive($fx['coP'], 'report-file', 'GET', ['id' => $fileId], [], ['BBR-FILE-BYTES']);
t_ok(empty($r['has']['BBR-FILE-BYTES']), 'Pune Coordinator: a file on the report does not open');
$r = bbr_drive($fx['coP'], 'documents', 'GET', [], [], [$irn]);
t_ok(empty($r['has'][$irn]), 'Pune Coordinator: the register does not list it');

// ---- Ahmedabad (issuing + executing office) --------------------------------------------------
$r = bbr_drive($fx['coA'], 'document', 'GET', ['id' => $id], [], [$irn]);
t_ok(!empty($r['has'][$irn]), 'Ahmedabad Coordinator (issuing office): opens the report');
$r = bbr_drive($fx['coA'], 'report-file', 'GET', ['id' => $fileId], [], ['BBR-FILE-BYTES']);
t_ok(!empty($r['has']['BBR-FILE-BYTES']), 'Ahmedabad Coordinator: the file on the report opens');
$r = bbr_drive($fx['coA'], 'document-edit', 'POST', ['id' => $id], bbr_edit_post($id, ['title' => 'BBR edited by Ahmedabad']));
t_eq((string) bbr_doc($id)['title'], 'BBR edited by Ahmedabad', 'Ahmedabad Coordinator: edits it: ' . bbr_said($r));
t_eq(count(bbr_history($id, 'CONTRACTING_EDIT')), 0, 'an issuing-office edit is not marked as a contracting-office edit');

// ---- Mumbai (contracting office) ---------------------------------------------------------------
$r = bbr_drive($fx['coM'], 'document', 'GET', ['id' => $id], [], [$irn]);
t_ok(!empty($r['has'][$irn]), 'Mumbai Coordinator (contracting office): may now open the report: ' . bbr_said($r));
$r = bbr_drive($fx['coM'], 'documents', 'GET', [], [], [$irn]);
t_ok(!empty($r['has'][$irn]), 'Mumbai Coordinator: the register lists it, so it can be found');
$r = bbr_drive($fx['coM'], 'document-pdf', 'GET', ['id' => $id]);
t_ok(!bbr_scope_refused($r), 'Mumbai Coordinator: the PDF opens: ' . bbr_said($r));
$r = bbr_drive($fx['coM'], 'report-file', 'GET', ['id' => $fileId], [], ['BBR-FILE-BYTES']);
t_ok(!empty($r['has']['BBR-FILE-BYTES']), 'Mumbai Coordinator: the file on the report opens');
$r = bbr_drive($fx['coM'], 'document-edit', 'GET', ['id' => $id], [], ['BBR Refinery Ltd', 'BBR Test Vendor']);
t_ok(!empty($r['has']['BBR Refinery Ltd']) && !empty($r['has']['BBR Test Vendor']),
     'Mumbai Coordinator: the edit form still offers the report\'s own (Ahmedabad) client and vendor');
// Saved exactly as the old form sent it: client and vendor missing from Mumbai's pickers, so blank.
$r = bbr_drive($fx['coM'], 'document-edit', 'POST', ['id' => $id],
     bbr_edit_post($id, ['title' => 'BBR edited by Mumbai', 'location' => 'Bay 7', 'client_id' => '', 'vendor_id' => '']));
$d = bbr_doc($id);
t_eq((string) $d['title'], 'BBR edited by Mumbai', 'Mumbai Coordinator: the contracting office may edit it before issue: ' . bbr_said($r));
t_eq([(int) $d['client_id'], (int) $d['vendor_id']], [$fx['client'], $fx['vendor']], 'saving the edit form no longer wipes the client and vendor');
$h = bbr_history($id, 'CONTRACTING_EDIT');
$line = $h ? (string) end($h)['new_value'] : '';
t_ok(strpos($line, 'Edited by Priya Probe (BBR Mumbai — contracting office): ') === 0, 'the History names the person and the contracting office: ' . $line);
t_ok(strpos($line, 'Title') !== false && strpos($line, 'Location') !== false && strpos($line, 'Client') === false,
     'the History lists exactly what changed (title, location — not the client that was kept)');

// Fill (the report body) by the contracting office is recorded the same way.
idems_ensure_default_form($fx['typeId']);
$tf = null; foreach (idems_fields($fx['typeId']) as $f) if (in_array($f['ftype'], ['text', 'textarea'], true)) { $tf = $f; break; }
if ($tf) {
    $r = bbr_drive($fx['coM'], 'document-fill', 'POST', ['id' => $id], ['f' => [$tf['fkey'] => 'BBR body from Mumbai']]);
    $data = json_decode((string) bbr_doc($id)['data'], true) ?: [];
    t_eq((string) ($data[$tf['fkey']] ?? ''), 'BBR body from Mumbai', 'Mumbai Coordinator: may fill the body: ' . bbr_said($r));
    $h = bbr_history($id, 'CONTRACTING_EDIT');
    t_ok(count($h) >= 2 && strpos((string) end($h)['new_value'], (string) $tf['label']) !== false,
         'the body edit is written to the History with the field changed');
}

// ---- Q6: only before issue -----------------------------------------------------------------------
db()->prepare("UPDATE report_docs SET status='ISSUED', finalized=1 WHERE id=?")->execute([$id]);
$r = bbr_drive($fx['coM'], 'document-edit', 'POST', ['id' => $id], bbr_edit_post($id, ['title' => 'BBR after issue']));
t_eq((string) bbr_doc($id)['title'], 'BBR edited by Mumbai', 'after issue the contracting office can no longer edit it: ' . bbr_said($r));
$r = bbr_drive($fx['coM'], 'document', 'GET', ['id' => $id], [], [$irn]);
t_ok(!empty($r['has'][$irn]), 'it can still open the issued report');
