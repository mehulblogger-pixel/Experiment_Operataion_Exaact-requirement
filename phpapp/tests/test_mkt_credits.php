<?php
// Slice 4 — marketplace credit packs (top-ups when a plan limit runs out). Safe by
// default: while enforcement is OFF nothing is drawn. When ON, credits are used only
// AFTER the plan's monthly quota is spent, and they carry over (they're a wallet).
t_section('marketplace credit packs (top-ups)');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    mkt_plans_migrate(); mkt_subs_migrate(); mkt_credits_migrate();
    $enf0 = (int)setting_get('mkt_enforce', 0);

    // Seeded catalogue exists.
    t_ok(count(mkt_credit_packs_all('CLIENT')) > 0, 'default client credit packs are seeded');
    t_ok(count(mkt_credit_packs_all('PRO')) > 0, 'default professional credit packs are seeded');

    // Add a bespoke pack: +3 posts for the client side.
    [$pok, $pmsg] = mkt_credit_pack_save(['audience'=>'CLIENT','name'=>'Test +3 posts','metric'=>'posts','credits'=>3,'price'=>150,'is_active'=>1]);
    t_ok($pok, 'a credit pack can be created: ' . $pmsg);
    $pack = null; foreach (mkt_credit_packs_all('CLIENT') as $p) if ($p['name']==='Test +3 posts') $pack = $p;
    t_ok($pack !== null, 'the new pack is listed');

    // A rejected save: unknown metric.
    [$bad,] = mkt_credit_pack_save(['audience'=>'CLIENT','name'=>'Bad','metric'=>'nonsense','credits'=>5]);
    t_ok($bad === false, 'a pack with an unknown metric is rejected');

    // A subscriber on the Starter plan (5 posts/month).
    db()->prepare("INSERT INTO business_partners (legal_name,is_client,status) VALUES ('Credit Co',1,'ACTIVE')")->execute();
    $party = (int)db()->lastInsertId();
    $starter = null; foreach (mkt_plans_all('CLIENT') as $p) if ($p['code']==='CL_START') $starter = $p;
    [$sok,,] = mkt_subscribe('CLIENT', $party, (int)$starter['id'], 'MONTH');
    t_ok($sok, 'client subscribes to Starter');

    setting_set('mkt_enforce', 1);

    // Buy the +3 posts pack → wallet balance = 3.
    t_eq(mkt_credits_balance('CLIENT', $party, 'posts'), 0, 'wallet starts empty');
    [$cok, $cmsg, $cid] = mkt_credit_buy('CLIENT', $party, (int)$pack['id']);
    t_ok($cok && $cid > 0, 'client buys the pack: ' . $cmsg);
    t_eq(mkt_credits_balance('CLIENT', $party, 'posts'), 3, 'wallet now holds 3 post credits');
    t_eq((int)ops_val("SELECT COUNT(*) FROM mkt_credit_purchases WHERE subscriber_id=? AND subscriber_kind='CLIENT'", [$party]), 1, 'the purchase is logged');

    // Consume the 5 plan posts — quota first, wallet untouched.
    for ($i=0;$i<5;$i++) mkt_consume('CLIENT', $party, 'posts');
    t_eq(mkt_usage_used('CLIENT', $party, 'posts'), 5, 'the 5 plan posts are used');
    t_eq(mkt_credits_balance('CLIENT', $party, 'posts'), 3, 'plan quota is drawn first — wallet still full');

    // Plan spent, but credits remain → still allowed.
    t_eq(mkt_usage_left('CLIENT', $party, 'posts'), 0, 'no plan quota left');
    t_ok(mkt_can_use('CLIENT', $party, 'posts') === true, 'a credit pack keeps posting allowed past the plan limit');

    // The 6th & 7th posts now draw from the wallet.
    mkt_consume('CLIENT', $party, 'posts');
    mkt_consume('CLIENT', $party, 'posts');
    t_eq(mkt_credits_balance('CLIENT', $party, 'posts'), 1, 'two posts past quota drew two credits');

    // Spend the last credit → then blocked.
    mkt_consume('CLIENT', $party, 'posts');
    t_eq(mkt_credits_balance('CLIENT', $party, 'posts'), 0, 'wallet emptied');
    t_ok(mkt_can_use('CLIENT', $party, 'posts') === false, 'with plan and wallet spent, posting is blocked');

    // A credit doesn't leak to a different metric.
    t_ok(mkt_can_use('CLIENT', $party, 'unlocks') === true, 'unlocks still have their own plan quota');

    // Audience mismatch — a client cannot buy a professional pack.
    $proPack = mkt_credit_packs_all('PRO')[0];
    [$mm,] = mkt_credit_buy('CLIENT', $party, (int)$proPack['id']);
    t_ok($mm === false, 'a client cannot buy a professional credit pack');

    // ---- enforcement OFF → credits are never drawn ----
    setting_set('mkt_enforce', 0);
    db()->prepare("INSERT INTO business_partners (legal_name,is_client,status) VALUES ('Open Co',1,'ACTIVE')")->execute();
    $party2 = (int)db()->lastInsertId();
    mkt_credits_add('CLIENT', $party2, 'posts', 4);
    for ($i=0;$i<10;$i++) mkt_consume('CLIENT', $party2, 'posts');
    t_eq(mkt_credits_balance('CLIENT', $party2, 'posts'), 4, 'enforcement OFF → the wallet is never touched');
} finally {
    setting_set('mkt_enforce', $enf0 ?? 0);
    if ($own && db()->inTransaction()) db()->rollBack();
}
