<?php
// TPIA Phase 1 item 19 — a report must never show ANOTHER report's Release Note
// as "already raised". The old lookup (data LIKE '%"source_report_id":1%')
// matched report 1 against the Release Note of report 12, 13, 100 …
t_section('TPIA item 19 — the Release Note found for a report is its own');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $pdo = db();
    $mk = function ($code, array $f = []) use ($pdo) {
        $f += ['status' => 'ISSUED', 'finalized' => 1, 'data' => '[]', 'release_of_id' => null];
        $pdo->prepare("INSERT INTO report_docs (type_code, irn, status, finalized, data, release_of_id, deleted, created_at) VALUES (?,?,?,?,?,?,0,?)")
            ->execute([$code, $code . '-' . substr(md5((string)microtime(true) . mt_rand()), 0, 6), $f['status'], $f['finalized'], $f['data'], $f['release_of_id'], date('c')]);
        return (int)$pdo->lastInsertId();
    };
    // Pick ids so that A's id is a prefix of B's (e.g. 7 and 7x) — the old defect.
    $a = $mk('IR');
    do { $b = $mk('IR'); } while (strpos((string)$b, (string)$a) !== 0);
    $rnB = $mk('RN', ['data' => json_encode(['source_irn' => 'B', 'source_report_id' => $b]), 'release_of_id' => $b]);
    t_ok(idems_release_note_for($a) === null, "report $a does not pick up report $b's Release Note (the old false match)");
    t_eq((int)(idems_release_note_for($b)['id'] ?? 0), $rnB, "report $b finds its own Release Note");
    $offerA = idems_release_note_offer(ops_one("SELECT * FROM report_docs WHERE id=?", [$a]));
    t_ok(empty($offerA['existing']), 'the report page for A offers "raise", not "already raised"');
    // An older Release Note that has only the stored data (no release_of_id) is still found, exactly.
    $c = $mk('IR');
    $rnC = $mk('RN', ['data' => json_encode(['source_irn' => 'C', 'source_report_id' => $c])]);
    t_eq((int)(idems_release_note_for($c)['id'] ?? 0), $rnC, 'an older Release Note recorded only in its data is still found');
    // A release that covers several inspections (link table) is found for each of them.
    $d = $mk('IR');
    $rnD = $mk('RN', ['release_of_id' => $c]);
    $pdo->prepare("INSERT INTO release_inspections (release_doc_id, inspection_doc_id, is_primary, created_at) VALUES (?,?,0,?)")->execute([$rnD, $d, date('c')]);
    t_eq((int)(idems_release_note_for($d)['id'] ?? 0), $rnD, 'a report covered by a multi-inspection Release Note finds it');
    $pdo->prepare("UPDATE report_docs SET deleted=1 WHERE id=?")->execute([$rnB]);
    t_ok(idems_release_note_for($b) === null, 'a deleted Release Note is not "raised"');
    $src = file_get_contents(__DIR__ . '/../lib/idems.php');
    t_eq(substr_count($src, "'%\"source_report_id\":' . \$id . '%'") + substr_count($src, "'%\"source_report_id\":' . (int)\$src['id'] . '%'"), 0, 'the loose LIKE lookup is gone from both places');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
