<?php
// ============================================================================
//  Connect K18 — the agency self-service bench workspace (portal).
//
//  An agency's signed-in portal user reaches its OWN bench (org-scoped); a plain
//  client never does, and one agency can never see another's roster.
//  (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('connect agency bench workspace (portal K18)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_org_migrate(); connect_bench_migrate(); portal_migrate();
    $now = date('c');
    $mkParty = function ($name, $isClient, $isSub) use ($now) {
        db()->prepare("INSERT INTO business_partners (legal_name,is_client,is_subcontractor,status,created_at) VALUES (?,?,?, 'ACTIVE',?)")
            ->execute([$name, (int)$isClient, (int)$isSub, $now]); return (int)db()->lastInsertId();
    };
    $mkOrg = function ($name, $type, $party) use ($now) {
        db()->prepare("INSERT INTO cx_organisations (name,org_type,party_id,status,created_at) VALUES (?,?,?, 'ACTIVE',?)")
            ->execute([$name, $type, (int)$party, $now]); return (int)db()->lastInsertId();
    };
    $mkUser = function ($party, $email) use ($now) {
        db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,perms,created_at) VALUES (?,?,?, 'x',1,'',?)")
            ->execute([(int)$party, $email, $email, $now]); return (int)db()->lastInsertId();
    };

    // Agency A, agency B, and a plain client C — each with a portal user.
    $pa = $mkParty('Agency A', 0, 1); $oa = $mkOrg('Agency A', 'MANPOWER_AGENCY', $pa); $ua = $mkUser($pa, 'a@agency.test');
    $pb = $mkParty('Agency B', 0, 1); $ob = $mkOrg('Agency B', 'RECRUITMENT_AGENCY', $pb); $ub = $mkUser($pb, 'b@agency.test');
    $pc = $mkParty('Client C', 1, 0);                                                       $uc = $mkUser($pc, 'c@client.test');

    // --- the bench gate: an agency party resolves to its OWN agency org, a
    //     plain client party resolves to none (this is exactly what
    //     portal_agency_org() returns for the signed-in user's party). ---------
    $gate = fn($party) => ops_one("SELECT * FROM cx_organisations
        WHERE party_id=? AND org_type IN ('MANPOWER_AGENCY','RECRUITMENT_AGENCY') AND COALESCE(status,'ACTIVE')='ACTIVE'
        ORDER BY id LIMIT 1", [(int)$party]);
    $orgForA = $gate($pa);
    t_ok($orgForA && (int)$orgForA['id'] === $oa, 'an agency party resolves to its OWN agency org');
    t_ok(!$gate($pc), 'a plain client party has no agency org — the bench gate would 404');

    // --- roster is private + org-scoped -------------------------------------
    [$okA] = connect_bench_add($oa, ['name' => 'Ajay A', 'discipline_code' => 'WELD', 'day_rate' => 3800]);
    [$okB] = connect_bench_add($ob, ['name' => 'Bimal B', 'discipline_code' => 'NDT', 'day_rate' => 4200]);
    t_ok($okA && $okB, 'each agency adds to its own bench');
    $listA = connect_bench_list($oa, false); $listB = connect_bench_list($ob, false);
    t_eq(count($listA), 1, 'agency A sees exactly its own one person');
    t_eq((string)$listA[0]['name'], 'Ajay A', 'agency A sees Ajay, not Bimal');
    t_ok(!in_array('Ajay A', array_map(fn($b) => $b['name'], $listB), true), 'agency B cannot see agency A roster');

    // --- put forward → allocation, then release frees the person ------------
    $reqId = cx_requirement_create(['title' => 'Weld inspector', 'poster_party_id' => $pc, 'poster_name' => 'Client C', 'discipline_code' => 'WELD'], true);
    $benchId = (int)$listA[0]['id'];
    [$aok,, $allocId] = connect_bench_allocate($benchId, $oa, $reqId, 0, 'Available Monday');
    t_ok($aok, 'the agency puts its person forward to an open requirement');
    t_eq(strtoupper((string)connect_bench_get($benchId, $oa)['availability']), 'ALLOCATED', 'that person is now allocated');
    // Agency B cannot touch agency A's allocation (org scoping on alloc_set)
    [$xok] = connect_bench_alloc_set($allocId, $ob, 'CONFIRMED');
    t_ok($xok === false, 'another agency cannot change this allocation');
    // A cannot double-allocate the same person to the same requirement
    [$dupe] = connect_bench_allocate($benchId, $oa, $reqId);
    t_ok($dupe === false, 'no double allocation to the same requirement');
    // Release frees the person back to the bench
    connect_bench_alloc_set($allocId, $oa, 'RELEASED');
    t_eq(strtoupper((string)connect_bench_get($benchId, $oa)['availability']), 'AVAILABLE', 'releasing returns the person to the bench');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
