<?php
// ============================================================================
//  Client-portal job posting carries the full engagement / rate terms.
//
//  The client's own "Hire technical manpower" form (/portal/hire) posts through
//  the SAME cx_requirement_create path the ops desk uses, straight to OPEN. This
//  proves that every deputation shape + rate model + voucher cadence the client
//  can pick on that form is actually stored on the requirement, is visible only
//  to that poster, and is the value an engagement inherits at booking.
// ============================================================================

$partyA = 90101;   // the posting client
$partyB = 90202;   // a different client — must never see A's posting

// --- 1. Every deputation basis the form offers posts and stores correctly ----
foreach (array_keys(connect_engage_bases()) as $basis) {
    $id = cx_requirement_create([
        'title'            => 'Post via client portal — ' . $basis,
        'poster_party_id'  => $partyA,
        'poster_name'      => 'Acme Fabricators Ltd',
        'discipline_code'  => 'NDT',
        'location'         => 'Dahej',
        'positions'        => 2,
        'rate_max'         => 4500,
        'deputation_basis' => $basis,
        'rate_inclusive'   => 'EXCLUSIVE',
        'voucher_cadence'  => 'PER_DAY',
    ], true); // $post = true — the client form always posts straight to OPEN

    $row = cx_requirement_get($id);
    t_ok($row !== null, "client posting for $basis is created");
    t_eq(strtoupper((string)$row['status']), 'OPEN', "client posting for $basis is OPEN, not DRAFT");
    t_eq(strtoupper((string)$row['deputation_basis']), $basis, "$basis basis stored on the requirement");
    t_eq(strtoupper((string)$row['rate_inclusive']), 'EXCLUSIVE', "$basis keeps the EXCLUSIVE rate model");
    t_eq(strtoupper((string)$row['voucher_cadence']), 'PER_DAY', "$basis keeps the PER_DAY cadence");
}

// --- 2. The inclusive / per-deployment default combination also round-trips --
$incId = cx_requirement_create([
    'title'            => 'All-inclusive continuous deputation',
    'poster_party_id'  => $partyA,
    'poster_name'      => 'Acme Fabricators Ltd',
    'deputation_basis' => 'CONTINUOUS',
    'rate_inclusive'   => 'INCLUSIVE',
    'voucher_cadence'  => 'PER_DEPLOYMENT',
], true);
$inc = cx_requirement_get($incId);
t_eq(strtoupper((string)$inc['rate_inclusive']), 'INCLUSIVE', 'all-inclusive posting stores INCLUSIVE');
t_eq(strtoupper((string)$inc['voucher_cadence']), 'PER_DEPLOYMENT', 'inclusive posting stores PER_DEPLOYMENT');

// --- 3. A bad basis value is rejected, not stored blindly --------------------
$badId = cx_requirement_create([
    'title'            => 'Bad basis',
    'poster_party_id'  => $partyA,
    'poster_name'      => 'Acme Fabricators Ltd',
    'deputation_basis' => 'NONSENSE',
    'rate_inclusive'   => 'EXCLUSIVE',
    'voucher_cadence'  => 'PER_DAY',
], true);
$bad = cx_requirement_get($badId);
t_eq((string)$bad['deputation_basis'], '', 'an unknown deputation basis is normalised away, not stored');

// --- 4. Postings are scoped to their poster (a client sees only its own) ------
$mine = cx_requirements_for_party($partyA);
$theirs = cx_requirements_for_party($partyB);
t_ok(count($mine) >= 6, "poster A sees all of its own postings (" . count($mine) . ")");
t_eq(count($theirs), 0, 'poster B sees none of poster A postings');

// --- 5. The posted terms flow through to the engagement at booking -----------
if (function_exists('connect_engage_save_for_requirement') && function_exists('connect_engage_for_requirement')) {
    // Book against the man-months posting (needs a quantity) with all-in terms
    // inherited from the requirement.
    $mmId = cx_requirement_create([
        'title'            => 'Man-months booking source',
        'poster_party_id'  => $partyA,
        'poster_name'      => 'Acme Fabricators Ltd',
        'deputation_basis' => 'MAN_MONTHS',
        'rate_inclusive'   => 'EXCLUSIVE',
        'voucher_cadence'  => 'PER_DAY',
    ], true);
    // Award it to a professional first — a booking is only recorded post-award.
    $appId = cx_application_add($mmId, ['applicant_professional_id' => 5001, 'applicant_name' => 'Suresh K']);
    cx_requirement_transition($mmId, 'SHORTLISTING');
    cx_application_transition($appId, 'SHORTLISTED');
    cx_requirement_award($mmId, $appId);
    t_eq(strtoupper((string)cx_requirement_get($mmId)['status']), 'AWARDED', 'the client posting can be awarded');

    [$ok, $msg] = connect_engage_save_for_requirement($mmId, [
        'subject_kind' => 'professional',
        'subject_id'   => 5001,
        'basis'        => 'MAN_MONTHS',
        'quantity'     => 3,
        'rate'         => 90000,
    ]);
    t_ok($ok, 'an engagement books against the client posting (' . $msg . ')');
    $engs = connect_engage_for_requirement($mmId);
    $eng = $engs[0] ?? null;
    if ($eng) {
        t_eq(strtoupper((string)$eng['rate_inclusive']), 'EXCLUSIVE', 'engagement inherits the posting rate model');
        t_eq(strtoupper((string)$eng['voucher_cadence']), 'PER_DAY', 'engagement inherits the posting voucher cadence');
    }
}

// --- Teardown: leave the shared test DB exactly as we found it ----------------
// These tests seed marketplace requirements into the suite's single shared
// database; global-aggregate tests in sibling files (labour-market analytics)
// count every requirement, so we remove ours once the assertions are done.
try {
    $ids = array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM cx_requirements WHERE poster_party_id IN (90101,90202)") ?: []);
    if ($ids) {
        $in = implode(',', $ids);
        db()->exec("DELETE FROM cx_engagements  WHERE requirement_id IN ($in)");
        db()->exec("DELETE FROM cx_applications WHERE requirement_id IN ($in)");
        db()->exec("DELETE FROM cx_requirements WHERE id IN ($in)");
    }
} catch (Throwable $e) { /* best-effort cleanup */ }
