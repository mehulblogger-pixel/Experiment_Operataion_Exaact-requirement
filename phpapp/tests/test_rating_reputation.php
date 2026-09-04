<?php
// Review & Reputation — payer reputation (a professional rates how a client paid),
// genuine two-sided summaries, and the rating-integrity dispute (report a wrong rating
// → the moderation desk investigates → uphold / annotate / remove). Removed = hidden,
// never deleted.
t_section('review & reputation integrity');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    connect_ratings_migrate(); connect_rating_disputes_migrate();

    // Helper — insert a rating row directly (bypassing the AWARDED-requirement gate).
    $addRating = function ($direction, $rateeInspector, $rateePartyId, $stars, $rehire, $pay = '', $comment = '') {
        db()->prepare("INSERT INTO cx_ratings (requirement_id,direction,ratee_inspector_id,ratee_party_id,stars,would_rehire,payment_status,comment,created_at)
                       VALUES (0,?,?,?,?,?,?,?,?)")
            ->execute([$direction, (int)$rateeInspector, (int)$rateePartyId, (int)$stars, $rehire ? 1 : 0, (string)$pay, (string)$comment, date('c')]);
        return (int)db()->lastInsertId();
    };

    // ---- client (payer) reputation ----
    $CLIENT = 8801;
    $addRating('PRO_TO_CLIENT', 0, $CLIENT, 5, 1, 'ON_TIME', 'paid promptly');
    $addRating('PRO_TO_CLIENT', 0, $CLIENT, 4, 1, 'ON_TIME');
    $addRating('PRO_TO_CLIENT', 0, $CLIENT, 2, 0, 'DELAYED', 'paid two months late');
    $badId = $addRating('PRO_TO_CLIENT', 0, $CLIENT, 1, 0, 'UNPAID', 'never paid');

    $cs = cx_rating_summary_for_client($CLIENT);
    t_eq((int)$cs['count'], 4, 'the client has four payer ratings');
    t_eq((float)$cs['avg_stars'], 3.0, 'average stars = (5+4+2+1)/4 = 3.0');
    t_eq((int)$cs['pay']['ON_TIME'], 2, 'two on-time payments recorded');
    t_eq((int)$cs['pay']['UNPAID'], 1, 'one unpaid recorded');
    t_eq((int)$cs['paid_fair_pct'], 50, 'paid-on-time = 2 of 4 = 50%');

    // ---- professional reputation + hidden exclusion ----
    $PRO = 7701;
    $r1 = $addRating('CLIENT_TO_PRO', $PRO, 0, 5, 1, '', 'excellent');
    $unfair = $addRating('CLIENT_TO_PRO', $PRO, 0, 1, 0, '', 'unfair one-star');
    $ps = cx_rating_summary_for_inspector($PRO);
    t_eq((int)$ps['count'], 2, 'the professional has two ratings before moderation');
    t_eq((float)$ps['avg_stars'], 3.0, 'average is 3.0 with the unfair rating counted');

    // ---- rating-integrity dispute lifecycle ----
    $did = cx_rating_dispute_raise($unfair, ['category' => 'UNFAIR', 'detail' => 'client never engaged me', 'raised_by_kind' => 'PRO', 'raised_by_id' => $PRO]);
    t_ok($did > 0, 'a professional can report an unfair rating');
    $dup = cx_rating_dispute_raise($unfair, ['category' => 'UNFAIR', 'raised_by_kind' => 'PRO']);
    t_eq($dup, 0, 'a rating cannot have two open reports at once');
    t_eq(cx_rating_dispute_raise(999999, ['raised_by_kind' => 'PRO']), 0, 'a report on a non-existent rating is refused');

    // transitions
    t_ok(cx_rating_dispute_can_transition('OPEN', 'UNDER_REVIEW'), 'OPEN can go under review');
    t_ok(cx_rating_dispute_can_transition('RESOLVED', 'OPEN') === false, 'RESOLVED is terminal');
    [$iok] = cx_rating_dispute_investigate($did, 'moderator');
    t_ok($iok, 'the desk starts investigating');
    t_eq((string)cx_rating_dispute_get($did)['status'], 'UNDER_REVIEW', 'status is UNDER_REVIEW');

    // resolve → REMOVED hides the rating from scores (but keeps the row)
    [$rok, $rmsg] = cx_rating_dispute_resolve($did, 'REMOVED', 'fabricated — no engagement existed', 'moderator');
    t_ok($rok, 'the desk removes the rating: ' . $rmsg);
    t_eq((int)cx_rating_get($unfair)['hidden'], 1, 'the rating is hidden');
    t_ok(ops_val("SELECT id FROM cx_ratings WHERE id=?", [$unfair]) !== null, 'the rating row still exists (not deleted)');
    $ps2 = cx_rating_summary_for_inspector($PRO);
    t_eq((int)$ps2['count'], 1, 'the removed rating is excluded from the summary');
    t_eq((float)$ps2['avg_stars'], 5.0, 'the professional’s average is now 5.0');

    // a second dispute can be raised after the first resolved
    $did2 = cx_rating_dispute_raise($r1, ['category' => 'OTHER', 'raised_by_kind' => 'STAFF']);
    t_ok($did2 > 0, 'a new report can be raised after the previous one resolved');
    cx_rating_dispute_investigate($did2);
    // ANNOTATED attaches a public note without hiding
    cx_rating_dispute_resolve($did2, 'ANNOTATED', 'context added by moderator', 'moderator');
    t_eq((string)cx_rating_get($r1)['moderation_note'], 'context added by moderator', 'an annotation is stored on the rating');
    t_eq((int)cx_rating_get($r1)['hidden'], 0, 'an annotated rating stays visible');

    // withdraw path
    $did3 = cx_rating_dispute_raise($badId, ['category' => 'OTHER', 'raised_by_kind' => 'CLIENT', 'raised_by_id' => $CLIENT]);
    [$wok] = cx_rating_dispute_withdraw($did3);
    t_ok($wok, 'a report can be withdrawn');
    t_eq((string)cx_rating_dispute_get($did3)['status'], 'WITHDRAWN', 'the withdrawn report is closed');

    // reputation card unifies both sides
    $card = connect_reputation_card('CLIENT', $CLIENT);
    t_eq((string)$card['kind'], 'CLIENT', 'the reputation card resolves the client side');
    t_ok(isset($card['paid_fair_pct']), 'the client card carries payer metrics');

    // open-count for the desk badge
    t_ok(cx_rating_disputes_open_count() >= 0, 'the open-dispute count is available for the desk');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
