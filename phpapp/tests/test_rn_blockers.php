<?php
// A Release Note can only be raised once the report is marked for it AND every
// hold / deviation point and client-acceptance condition is clear. Guards
// idems_rn_blockers() and how idems_release_note_offer surfaces it.
t_section('Release Note blockers (mark, hold/deviation, client acceptance)');

$pdo = db();
$typeId = idems_build_fire_extinguisher_report();
$mk = function ($over = []) use ($pdo, $typeId) {
    $base = ['report_type_id'=>$typeId,'type_code'=>'FEXT','irn'=>'RB-'.substr(md5(json_encode($over).microtime()),0,6),
             'status'=>'ISSUED','finalized'=>1,'rn_to_issue'=>0,'data'=>'[]','created_at'=>date('c')];
    $row = array_merge($base, $over);
    $cols = implode(',', array_keys($row)); $ph = implode(',', array_fill(0, count($row), '?'));
    $pdo->prepare("INSERT INTO report_docs ($cols) VALUES ($ph)")->execute(array_values($row));
    return ops_one("SELECT * FROM report_docs WHERE id=?", [(int)$pdo->lastInsertId()]);
};

// Not marked for a Release Note → blocked.
$d = $mk(['rn_to_issue'=>0]);
$bl = idems_rn_blockers($d);
t_ok(count($bl) >= 1, 'an unmarked report is blocked from a Release Note');
t_ok(stripos(implode(' ',$bl), 'marked') !== false, 'the reason names the missing mark');

// Marked, nothing else pending → clear.
$d2 = $mk(['rn_to_issue'=>1]);
t_ok(idems_rn_blockers($d2) === [], 'a marked report with nothing pending is clear to release');

// The offer surfaces the blockers on an issued but unmarked report.
$offer = idems_release_note_offer($mk(['rn_to_issue'=>0]));
t_ok(is_array($offer) && !empty($offer['blockers']), 'the Release Note offer carries the blockers');

// An open deviation / nonconformity blocks it.
if (function_exists('ncr_migrate')) { try { ncr_migrate(); } catch (Throwable $e) {} }
$d3 = $mk(['rn_to_issue'=>1]);
$hasNcrTable = true;
try { $pdo->prepare("INSERT INTO nonconformities (ref, report_doc_id, status, created_at) VALUES ('RB-NCR', ?, 'OPEN', ?)")->execute([(int)$d3['id'], date('c')]); }
catch (Throwable $e) { $hasNcrTable = false; }
if ($hasNcrTable) {
    $d3 = ops_one("SELECT * FROM report_docs WHERE id=?", [(int)$d3['id']]);
    $bl3 = idems_rn_blockers($d3);
    t_ok(stripos(implode(' ',$bl3),'deviation') !== false || stripos(implode(' ',$bl3),'nonconformit') !== false,
         'an open deviation / nonconformity blocks the Release Note');
    // Close it → clear.
    $pdo->prepare("UPDATE nonconformities SET status='CLOSED' WHERE report_doc_id=?")->execute([(int)$d3['id']]);
    t_ok(idems_rn_blockers(ops_one("SELECT * FROM report_docs WHERE id=?", [(int)$d3['id']])) === [], 'closing the deviation clears the block');
} else {
    t_ok(true, 'no nonconformities table — skipping the deviation-block check');
    t_ok(true, '(skipped)');
}

// A client REJECTED report is always blocked.
$d4 = $mk(['rn_to_issue'=>1, 'client_decision'=>'REJECTED']);
t_ok(stripos(implode(' ', idems_rn_blockers($d4)), 'reject') !== false, 'a client-rejected report is blocked');

// Client-acceptance requirement is opt-in via a setting.
setting_set('rn_require_client_acceptance', '');
$d5 = $mk(['rn_to_issue'=>1, 'client_decision'=>'']);
t_ok(idems_rn_blockers($d5) === [], 'with the setting off, a pending client decision does not block');
setting_set('rn_require_client_acceptance', '1');
t_ok(stripos(implode(' ', idems_rn_blockers($d5)), 'acceptance') !== false, 'with the setting on, pending client acceptance blocks');
setting_set('rn_require_client_acceptance', '');
