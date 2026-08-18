<?php
// A Release Note is raised from an inspection report in one click. The action
// already existed; the complaint was that it was buried in a Tools menu ("no
// button"). These guard the pure status/type decision (idems_release_note_offer)
// that drives the now-prominent playbook step — independent of who is logged in.
t_section('Release Note offer (one-click from an inspection report)');

$typeId = idems_build_fire_extinguisher_report();
$rnTypeId = idems_build_release_note();
$pdo = db();
$n = 0;
$mk = function ($typeCode, $typeRef, $status, $finalized = 0, $data = []) use ($pdo, &$n) {
    $n++;
    $pdo->prepare("INSERT INTO report_docs (report_type_id,type_code,irn,status,finalized,data,created_at)
                   VALUES (?,?,?,?,?,?,?)")
        ->execute([$typeRef, $typeCode, 'RNO-' . $n, $status, $finalized, json_encode($data), date('c')]);
    return ops_one("SELECT * FROM report_docs WHERE id=?", [(int)$pdo->lastInsertId()]);
};

// A DRAFT inspection report offers nothing to release yet.
t_ok(idems_release_note_offer($mk('FEXT', $typeId, 'DRAFT')) === null,
     'a draft report offers no Release Note');

// An APPROVED-but-not-issued report does NOT offer it — a Release Note is raised
// only once the inspection report is accepted (issued).
$appr = $mk('FEXT', $typeId, 'APPROVED');
t_ok(idems_release_note_offer($appr) === null,
     'an approved but not-yet-issued report offers no Release Note (must be issued first)');

// An ISSUED (finalized/accepted) report offers it, with no existing RN yet.
$iss = $mk('FEXT', $typeId, 'ISSUED', 1);
$offer = idems_release_note_offer($iss);
t_ok(is_array($offer), 'an issued (accepted) report offers a Release Note');
t_ok($offer['existing'] === null, 'no Release Note exists yet for it');
t_eq((int)($offer['doc_id'] ?? 0), (int)$iss['id'], 'the offer carries this report id');

// Once an RN is raised from it, the offer points to the existing RN (no dup).
$pdo->prepare("INSERT INTO report_docs (report_type_id,type_code,irn,status,data,created_at) VALUES (?,?,?,?,?,?)")
    ->execute([$rnTypeId, 'RN', 'RN-FROM-ISS', 'DRAFT',
               json_encode(['source_report_id' => (int)$iss['id'], 'source_irn' => $iss['irn']]), date('c')]);
$rnId = (int)$pdo->lastInsertId();
$offer2 = idems_release_note_offer($iss);
t_ok(!empty($offer2['existing']) && (int)$offer2['existing']['id'] === $rnId,
     'the offer now points to the Release Note already raised');

// A Release Note itself never offers to spawn another Release Note.
$rnDoc = ops_one("SELECT * FROM report_docs WHERE id=?", [$rnId]);
$rnDoc['status'] = 'APPROVED';
t_ok(idems_release_note_offer($rnDoc) === null,
     'a Release Note does not offer to create another Release Note');

// The playbook wires the offer into a step (state now / done) when a user may
// create one; as an unauthenticated test run it is correctly withheld. Either
// way, the step must never be the "now" action for a Release Note document.
$pbRn = idems_report_playbook($rnDoc, [], true);
$rnStep = null; foreach ($pbRn['steps'] as $s) if (($s['key'] ?? '') === 'release') $rnStep = $s;
t_ok($rnStep === null, 'the playbook never shows a Release Note step on a Release Note itself');
