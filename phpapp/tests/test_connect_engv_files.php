<?php
// ============================================================================
//  Connect K21 — voucher supporting documents (receipts / bills).
//
//  A claimant attaches receipts to back a voucher; the approver sees them.
//  Uploads are allowed only while the voucher is still open for change
//  (DRAFT or SUBMITTED) and are frozen once APPROVED/PAID. File bytes come
//  through an HTTP multipart upload (is_uploaded_file), which cannot be
//  simulated under the CLI test runner — so the actual add is exercised at
//  its guard boundaries, and the stored-file read/list/scope/delete paths are
//  exercised against a row inserted exactly as a successful add would store it.
//  (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('connect voucher supporting documents (K21)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_engv_migrate();

    // A booked EXCLUSIVE engagement + a DRAFT voucher with one day line.
    $now = date('c');
    db()->prepare("INSERT INTO cx_engagements
        (requirement_id,subject_kind,subject_id,subject_name,poster_party_id,basis,rate,rate_unit,quantity,rate_inclusive,voucher_cadence,status,created_at,updated_at)
        VALUES (0,'professional',7001,'Rec Test',0,'MAN_DAYS',4000,'day',5,'EXCLUSIVE','PER_DAY','BOOKED',?,?)")
        ->execute([$now, $now]);
    $engId = (int)db()->lastInsertId();
    [$vok,, $vid] = connect_engv_open_for_engagement($engId, ['period_label' => 'REC-1']);
    t_ok($vok && $vid > 0, 'a voucher opens for the engagement');
    connect_engv_add_line($vid, ['work_date' => '2026-08-10', 'units' => 1, 'travel' => 1200]);
    $lineId = (int)ops_val("SELECT id FROM cx_engagement_voucher_lines WHERE voucher_id=?", [$vid]);

    // helper: store a file exactly as a successful upload would.
    $store = function ($voucherId, $lineId, $name, $bytes) {
        db()->prepare("INSERT INTO cx_engagement_voucher_files
            (voucher_id,line_id,file_name,mime,size,file_data,uploaded_kind,uploaded_id,uploaded_name,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([(int)$voucherId, (int)$lineId, $name, 'image/jpeg', strlen($bytes),
                       base64_encode($bytes), 'professional', 7001, 'Rec Test', date('c')]);
        return (int)db()->lastInsertId();
    };

    // ---- can_attach reflects the lifecycle ---------------------------------
    t_ok(connect_engv_can_attach(connect_engv_get($vid)) === true, 'a DRAFT voucher accepts documents');

    // ---- add guards (no real HTTP upload under CLI) ------------------------
    [$g1, $m1] = connect_engv_file_add(999999, 0, null);
    t_eq($m1, 'Voucher not found.', 'adding to a missing voucher is refused');
    [$g2, $m2] = connect_engv_file_add($vid, 0, null);
    t_eq($m2, 'Choose a file to upload.', 'a DRAFT voucher with no file asks for one (past the lifecycle gate)');

    // ---- stored file read / list / count / scope ---------------------------
    $f1 = $store($vid, 0, 'hotel-folio.jpg', 'BYTES-1');
    $f2 = $store($vid, $lineId, 'cab-bill.jpg', 'BYTES-2');
    t_eq(connect_engv_file_count($vid), 2, 'both stored documents are counted');
    $list = connect_engv_files($vid);
    t_eq(count($list), 2, 'both documents are listed');
    t_ok(!isset($list[0]['file_data']), 'the list view carries metadata only, not the bytes');

    $row = connect_engv_file_row($f1);
    t_ok($row && base64_decode((string)$row['file_data']) === 'BYTES-1', 'the file row serves the exact bytes');
    t_ok(connect_engv_file_row($f1, $vid) !== null, 'a file row scoped to its own voucher resolves');
    t_ok(connect_engv_file_row($f1, $vid + 999) === null, 'a file row scoped to the WRONG voucher is refused');
    t_eq((int)connect_engv_file_row($f2)['line_id'], $lineId, 'a document can be pinned to a specific day line');

    // ---- delete is allowed while open --------------------------------------
    t_ok(connect_engv_file_delete($f1, $vid) === true, 'a document can be removed while the voucher is a draft');
    t_eq(connect_engv_file_count($vid), 1, 'the removed document is gone');

    // ---- SUBMITTED still accepts / frees documents -------------------------
    connect_engv_set_status($vid, 'SUBMITTED');
    t_ok(connect_engv_can_attach(connect_engv_get($vid)) === true, 'a SUBMITTED voucher still accepts documents (under review)');
    [$g3, $m3] = connect_engv_file_add($vid, 0, null);
    t_eq($m3, 'Choose a file to upload.', 'SUBMITTED passes the lifecycle gate too');

    // ---- APPROVED freezes the attachment set -------------------------------
    connect_engv_set_status($vid, 'APPROVED');
    t_ok(connect_engv_can_attach(connect_engv_get($vid)) === false, 'an APPROVED voucher no longer accepts documents');
    [$g4, $m4] = connect_engv_file_add($vid, 0, null);
    t_eq($m4, 'Documents can be added only while the voucher is a draft or under review.', 'adding to an APPROVED voucher is refused by the lifecycle gate');
    t_ok(connect_engv_file_delete($f2, $vid) === false, 'a document cannot be removed from an APPROVED voucher');
    t_eq(connect_engv_file_count($vid), 1, 'the APPROVED voucher keeps its document');

} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
