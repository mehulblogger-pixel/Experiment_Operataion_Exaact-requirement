<?php
// Connect K17 / backlog #6 — matching & trust on the UNIFIED pool + optional AI
// re-ranking. Asserts a professional now carries a real cross-pool Trust Score
// (driven by the #3 verification tier), and that the AI overlay only ever
// re-orders / annotates the rule set, never invents or loses a candidate, and
// falls straight back to the deterministic order on any failure.
t_section('connect matching + trust on the unified pool (#6)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    // --- cross-pool Trust Score for a professional --------------------------
    connect_pro_register(['name' => 'Trust Tina', 'email' => 'tina@example.com', 'password' => 'secret12']);
    $pid = connect_pro_id();
    $t0 = connect_trust_score_pro($pid);
    t_eq(0, $t0['score'], 'an unverified professional is an honest 0 (no inflated conduct prior)');
    t_eq('New', $t0['band'], 'and is honestly banded New (no history)');

    // Verify their ID → the verification bucket drives the cross-pool score.
    [$ok, , $vid] = connect_verify_submit('professional', $pid, 'PAN', 'AAPFU0939F');
    connect_verify_review($vid, 'VERIFIED', '', 'Coord');
    $t1 = connect_trust_score_pro($pid);
    t_eq(550, $t1['score'], 'an ID-verified professional scores on verification alone (550)');
    t_ok($t1['score'] > $t0['score'], 'verifying identity raises the professional Trust Score');
    t_eq(1, $t1['verified'], 'the professional shows one verified check');
    t_eq('New', $t1['band'], 'the band stays New until there is work history (honest)');
    // The dispatcher routes by kind.
    t_eq($t1['score'], connect_trust_score_for('professional', $pid)['score'], 'connect_trust_score_for dispatches to the professional scorer');

    // The matcher now carries that trust for the professional (not a flat zero).
    connect_pro_profile_save($pid, ['name' => 'Trust Tina', 'skills' => 'Welding inspection', 'disciplines' => ['WELD'], 'availability' => 'AVAILABLE']);
    $rid = cx_requirement_create(['title' => 'Welding inspector', 'discipline_code' => 'WELD', 'description' => 'Welding'], true);
    $req = cx_requirement_get($rid);
    $rows = connect_match_for_requirement($req, 20);
    $mine = null; foreach ($rows as $r) if (($r['kind'] ?? '') === 'professional' && (int)$r['id'] === $pid) $mine = $r;
    t_ok($mine && (int)$mine['trust'] === (int)$t1['score'], 'the recommendation carries the professional cross-pool Trust Score');

    // --- AI re-ranking overlay (injected stub — no network) -----------------
    // Seed a couple more candidates so there is something to reorder.
    db()->prepare("INSERT INTO inspectors (name,skills,status,created_at) VALUES ('Welder A','Welding inspection','ACTIVE',?)")->execute([date('c')]);
    db()->prepare("INSERT INTO inspectors (name,skills,status,created_at) VALUES ('Welder B','Welding NDT','ACTIVE',?)")->execute([date('c')]);
    $rows = connect_match_for_requirement($req, 20);
    t_ok(count($rows) >= 3, 'there are several candidates to rank');

    // A stub that reverses the rule order and adds a reason.
    $stub = function ($system, $user, $max = 800) use ($rows) {
        $ids = [];
        foreach (array_reverse($rows) as $r) if (($r['eligibility'] ?? '') !== 'BLOCKED')
            $ids[] = ['kind' => $r['kind'], 'id' => (int)$r['id'], 'reason' => 'stub fit'];
        return [json_encode($ids), null];
    };
    [$ranked, $used] = connect_match_ai_rerank($req, $rows, $stub);
    t_ok($used, 'the AI overlay applied');
    t_eq(count($rows), count($ranked), 'no candidate is added or lost by AI ranking');
    t_ok(!empty($ranked[0]['ai_reason']), 'the top AI-ranked candidate carries a reason');
    // The stub reversed order → the first rule candidate is now near the end.
    t_ok((string)$ranked[0]['name'] !== (string)$rows[0]['name'], 'AI actually re-ordered the shortlist');

    // Invented ids are ignored (AI can never introduce a candidate).
    $evil = fn($s, $u, $m = 800) => [json_encode([['kind' => 'professional', 'id' => 999999, 'reason' => 'ghost']]), null];
    [$r2, $used2] = connect_match_ai_rerank($req, $rows, $evil);
    t_ok(!$used2, 'a reply naming only unknown ids does not apply (never invents candidates)');
    t_eq(count($rows), count($r2), 'and the deterministic set is returned untouched');

    // Any AI error falls back to the deterministic order.
    $boom = fn($s, $u, $m = 800) => [null, 'provider down'];
    [$r3, $used3] = connect_match_ai_rerank($req, $rows, $boom);
    t_ok(!$used3 && count($r3) === count($rows), 'an AI failure falls back to the rule order');

    // Unparseable JSON also falls back.
    $junk = fn($s, $u, $m = 800) => ['not json at all', null];
    [$r4, $used4] = connect_match_ai_rerank($req, $rows, $junk);
    t_ok(!$used4, 'an unparseable AI reply falls back to the rule order');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
