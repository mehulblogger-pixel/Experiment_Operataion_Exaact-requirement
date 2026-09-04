<?php
// Connect K5 — Trust Score 0-1000. Asserts the score is bounded, composed from
// available signals only (no-data buckets drop out), rises when verification
// improves, and reports honest confidence banding by job history.
t_section('connect trust score (K5)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    // A fresh professional with nothing on record.
    db()->prepare("INSERT INTO inspectors (name,status,created_at) VALUES ('Newbie Nita','ACTIVE',?)")->execute([date('c')]);
    $id = (int)db()->lastInsertId();
    $t0 = connect_trust_score($id);
    t_ok($t0['score'] >= 0 && $t0['score'] <= 1000, 'the score is within 0..1000');
    t_eq('New', $t0['band'], 'a professional with no history bands as New');
    t_ok($t0['limited'] === true, 'limited-history flag is set under 10 jobs');

    // Peer-endorsements has no engine yet → it must drop out (never counted).
    $endorse = null;
    foreach ($t0['subs'] as $s) if ($s['key'] === 'endorsements') $endorse = $s;
    t_ok($endorse && $endorse['counted'] === false, 'peer endorsements drops out (no data), never faked');

    // Verifying credentials raises the verification sub and the overall score.
    foreach (['CSWIP 3.1','ASNT UT II','NACE CIP'] as $nm)
        db()->prepare("INSERT INTO inspector_certs (inspector_id,name,valid_to,status,verify_status,created_at) VALUES (?,?,?,?,?,?)")
            ->execute([$id, $nm, date('Y-m-d', strtotime('+2 years')), 'VALID', 'VERIFIED', date('c')]);
    $t1 = connect_trust_score($id);
    t_ok($t1['verified'] === 3, 'three verified credentials are counted');
    t_ok($t1['score'] > $t0['score'], 'verifying credentials raises the Trust Score');
    $vsub = null; foreach ($t1['subs'] as $s) if ($s['key'] === 'verification') $vsub = $s;
    t_ok($vsub && (int)$vsub['value'] === 100, 'the verification sub-score reaches 100 with enough verified credentials');

    // Badge text is compact and sensible.
    t_ok(strpos(connect_trust_badge($id), (string)$t1['score']) !== false || connect_trust_badge($id) !== '', 'a compact trust badge is produced');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
