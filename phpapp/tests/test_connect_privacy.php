<?php
// Professional privacy states & contact-reveal resolver (K0+).
t_section('connect professional privacy & contact reveal (K0+)');
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_pro_migrate(); connect_privacy_migrate();

    db()->prepare("INSERT INTO cx_professionals (email,name,mobile,is_active,created_at) VALUES ('priv@pro.test','Rajesh Kumar Singh','9876543210',1,?)")->execute([date('c')]);
    $pid = (int)db()->lastInsertId();
    $pro = ops_one("SELECT * FROM cx_professionals WHERE id=?", [$pid]);

    // defaults preserve prior behaviour: on_request contact, band rate, full name, listed
    $d = connect_privacy_get($pid);
    t_eq($d['contact'], 'on_request', 'default contact is on_request');
    t_eq($d['rate'], 'band', 'default rate is band');
    t_eq($d['identity'], 'full', 'default identity is full name');
    t_eq((int)$d['listed'], 1, 'default is listed in search');

    // whitelist: garbage falls back to a safe default
    connect_privacy_save($pid, ['contact'=>'nonsense','rate'=>'public','identity'=>'first_initial','listed'=>'1']);
    $s = connect_privacy_get($pid);
    t_eq($s['contact'], 'on_request', 'invalid contact value falls back to default');
    t_eq($s['rate'], 'public', 'rate saved as public');
    t_eq($s['identity'], 'first_initial', 'identity saved as first_initial');

    // first-initial masking
    t_eq(connect_privacy_first_initial('Rajesh Kumar Singh'), 'Rajesh K.S.', 'multi-word name masked to first + initials');
    t_eq(connect_privacy_first_initial('Madonna'), 'Madonna', 'single-word name is left whole');

    // --- Resolver: a brand-new client (no relationship) -----------------------
    $pro = ops_one("SELECT * FROM cx_professionals WHERE id=?", [$pid]);
    $anon = connect_privacy_resolve($pro, ['party_id'=>555]);
    t_eq($anon['display_name'], 'Rajesh K.S.', 'stranger sees masked identity when first_initial is set');
    t_ok(!$anon['contact_visible'], 'stranger cannot see contact under on_request');
    t_eq($anon['mobile'], '', 'masked-out mobile is empty to a stranger');
    t_eq($anon['rate_mode'], 'public', 'rate mode is passed through to the card');

    // owner always sees everything
    $owner = connect_privacy_resolve($pro, ['is_owner'=>true]);
    t_ok($owner['contact_visible'] && $owner['mobile']==='9876543210', 'owner sees full contact');
    t_eq($owner['display_name'], 'Rajesh Kumar Singh', 'owner sees full name');

    // staff (moderation) sees contact for review
    $staff = connect_privacy_resolve($pro, ['is_staff'=>true]);
    t_ok($staff['contact_visible'], 'staff sees contact for moderation');

    // --- Contact reveal grant makes a specific client eligible ---------------
    t_ok(!connect_privacy_contact_revealed($pid, 555), 'no reveal before a grant');
    connect_privacy_reveal_grant($pid, 555, 'reveal_request');
    t_ok(connect_privacy_contact_revealed($pid, 555), 'reveal grant recorded');
    connect_privacy_reveal_grant($pid, 555, 'reveal_request'); // idempotent
    t_eq((int)ops_val("SELECT COUNT(*) FROM cx_pro_contact_reveals WHERE pro_id=? AND client_party_id=555", [$pid]), 1, 'reveal grant is idempotent');
    $revd = connect_privacy_resolve($pro, ['party_id'=>555]);
    t_ok($revd['contact_visible'] && $revd['mobile']==='9876543210', 'granted client now sees contact');
    t_eq($revd['display_name'], 'Rajesh Kumar Singh', 'granted client also sees the full name');
    t_eq($revd['contact_reason'], 'revealed', 'reason names the reveal');
    // a different client is still blocked
    $other = connect_privacy_resolve($pro, ['party_id'=>999]);
    t_ok(!$other['contact_visible'], 'a different client without a grant is still blocked');

    // revoke closes it again
    connect_privacy_reveal_revoke($pid, 555);
    t_ok(!connect_privacy_contact_revealed($pid, 555), 'revoke removes the grant');

    // an existing engagement reveals without a stored grant
    $eng = connect_privacy_resolve($pro, ['party_id'=>555, 'engaged'=>true]);
    t_ok($eng['contact_visible'] && $eng['contact_reason']==='engaged', 'an engaged client sees contact');

    // --- 'hidden' contact hides even from a reveal-less on_request path -------
    connect_privacy_save($pid, ['contact'=>'hidden','rate'=>'hidden','identity'=>'full','listed'=>'0']);
    $pro = ops_one("SELECT * FROM cx_professionals WHERE id=?", [$pid]);
    $h = connect_privacy_resolve($pro, ['party_id'=>0]);
    t_ok(!$h['contact_visible'], 'hidden contact is never shown to clients');
    t_eq($h['rate_mode'], 'hidden', 'hidden rate mode passes through');
    t_eq((int)connect_privacy_get($pid)['listed'], 0, 'un-listing is saved');
    // but a real reveal still lets a specific engaged client through (they are working together)
    $he = connect_privacy_resolve($pro, ['party_id'=>555, 'engaged'=>true]);
    t_ok($he['contact_visible'], 'an engaged client still reaches a hidden-contact pro they are working with');
} finally { if ($own && db()->inTransaction()) db()->rollBack(); }
