<?php
// Slice 3 — marketplace subscriptions, access & usage limits. Safe by default:
// enforcement OFF ⇒ the marketplace is open (nothing changes). ON ⇒ a plan is
// required, monthly limits bite, and the freelancer launch-promo is honoured.
t_section('marketplace subscriptions, access & limits');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    mkt_plans_migrate(); mkt_subs_migrate();
    $enf0 = (int)setting_get('mkt_enforce', 0);
    $free0 = (string)setting_get('mkt_pro_free_until', '');

    db()->prepare("INSERT INTO business_partners (legal_name,is_client,status) VALUES ('Sub Co',1,'ACTIVE')")->execute();
    $party = (int)db()->lastInsertId();

    // ---- enforcement OFF → the marketplace is open ----
    setting_set('mkt_enforce', 0);
    t_ok(mkt_has_access('CLIENT', $party) === true, 'enforcement OFF → everyone has access');
    t_ok(mkt_can_use('CLIENT', $party, 'posts') === true, 'enforcement OFF → posting is allowed (no change to today)');
    t_eq(mkt_limit('CLIENT', $party, 'posts'), -1, 'enforcement OFF → limits are unlimited');

    // Subscribe the client to the Starter plan (5 posts).
    $starter = null; foreach (mkt_plans_all('CLIENT') as $p) if ($p['code'] === 'CL_START') $starter = $p;
    t_ok($starter !== null, 'Starter plan exists');
    [$sok, $smsg, $sid] = mkt_subscribe('CLIENT', $party, (int)$starter['id'], 'MONTH');
    t_ok($sok && $sid > 0, 'client subscribes: ' . $smsg);
    $cp = mkt_current_plan('CLIENT', $party);
    t_eq((string)$cp['code'], 'CL_START', 'current plan is the one subscribed');

    // ---- enforcement ON → limits bite ----
    setting_set('mkt_enforce', 1);
    t_eq(mkt_limit('CLIENT', $party, 'posts'), 5, 'Starter allows 5 posts/month');
    t_ok(mkt_can_use('CLIENT', $party, 'posts') === true, 'with quota left, posting is allowed');
    for ($i = 0; $i < 5; $i++) mkt_usage_add('CLIENT', $party, 'posts');
    t_eq(mkt_usage_used('CLIENT', $party, 'posts'), 5, 'five posts used this month');
    t_ok(mkt_can_use('CLIENT', $party, 'posts') === false, 'the 6th post is blocked — limit reached');
    t_eq(mkt_usage_left('CLIENT', $party, 'posts'), 0, 'no posts left this month');

    // A client with NO subscription, enforcement ON → blocked.
    db()->prepare("INSERT INTO business_partners (legal_name,is_client,status) VALUES ('No Plan Co',1,'ACTIVE')")->execute();
    $party2 = (int)db()->lastInsertId();
    t_ok(mkt_has_access('CLIENT', $party2) === false, 'enforcement ON + no plan → no access');
    t_ok(mkt_can_use('CLIENT', $party2, 'posts') === false, 'no-plan client cannot post');

    // ---- freelancer launch-promo ----
    [$rr, , ] = [connect_pro_register(['name'=>'Sub Pro','email'=>'sub_'.substr(md5(uniqid('',true)),0,6).'@ex.com','password'=>'password1'])];
    $pid = (int)ops_val("SELECT id FROM cx_professionals WHERE email LIKE 'sub_%' ORDER BY id DESC LIMIT 1");
    setting_set('mkt_pro_free_until', date('Y-m-d', strtotime('+30 days')));   // promo running
    t_ok(mkt_has_access('PRO', $pid) === true, 'a professional has free access during the promo');
    t_eq(mkt_limit('PRO', $pid, 'applications'), -1, 'promo = unlimited applications');
    t_ok(mkt_can_use('PRO', $pid, 'applications') === true, 'promo professional can apply');

    setting_set('mkt_pro_free_until', date('Y-m-d', strtotime('-1 day')));     // promo ended
    t_ok(mkt_has_access('PRO', $pid) === false, 'after the promo, a professional with no plan has no access');

    // ---- expiry ----
    db()->prepare("UPDATE mkt_subscriptions SET expires_at=? WHERE id=?")->execute([date('Y-m-d', strtotime('-1 day')), $sid]);
    t_ok(mkt_active_sub('CLIENT', $party) === null, 'an expired subscription is not active');
} finally {
    setting_set('mkt_enforce', $enf0 ?? 0);
    setting_set('mkt_pro_free_until', $free0 ?? '');
    if ($own && db()->inTransaction()) db()->rollBack();
}
