<?php
// Phase 2 §23/24 — canonical PARTY / PERSON mapping layer. One human lives across users, inspectors,
// candidates, partner_contacts, client_users and vendor_users. This layer does NOT merge them; it
// resolves ONE canonical identity key (ref -> last-10 mobile -> email, same precedence as recruitment)
// and lists every record for the same person across the stores. Read-only; non-destructive.
t_section('Phase 2 §23/24 — canonical person mapping layer');

t_ok(function_exists('party_key') && function_exists('party_records') && function_exists('party_key_of'),
    'party_key / party_records / party_key_of exist');

// The key precedence: explicit ref beats mobile beats email; normalises mobile to last 10 digits.
t_eq(party_key('', '', 'PR-9'), 'ref:PR-9', 'an explicit ref wins');
t_eq(party_key('+91 98765-43210', 'a@x.com', ''), 'mob:9876543210', 'a formatted mobile normalises to its last 10 digits');
t_eq(party_key('12345', 'Person@X.COM', ''), 'em:person@x.com', 'a too-short mobile falls back to the lowercased email');
t_eq(party_key('', '', ''), '', 'nothing to key on -> empty (never matches anyone)');
// The same human entered two ways resolves to the SAME key.
t_eq(party_key('098765 43210', '', ''), party_key('+91-9876543210', '', ''), 'the same mobile in two formats yields one key');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    $pdo = db();
    // The SAME person as an inspector AND a candidate (share email + mobile, as a hire copies them)
    // AND a client-portal user (shares only email — portal logins have no phone of their own).
    $email = 'rakesh.party@example.com'; $mob = '9876500001';
    $pdo->prepare("INSERT INTO inspectors (name, status, email, mobile) VALUES ('Rakesh P','ACTIVE',?,?)")->execute([$email, $mob]);
    $insId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO candidates (first_name, last_name, email, mobile, stage) VALUES ('Rakesh','P',?,?,'RECEIVED')")->execute([$email, $mob]);
    $candId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO client_users (name, email) VALUES ('Rakesh P', ?)")->execute([$email]);

    // A DIFFERENT person that must NOT be matched in.
    $pdo->prepare("INSERT INTO inspectors (name, status, email, mobile) VALUES ('Someone Else','ACTIVE','other.party@example.com','9999900000')")->execute();

    // The canonical single key (mobile wins over email for the inspector).
    $key = party_key_of('INSPECTOR', $insId);
    t_eq($key, 'mob:' . $mob, 'party_key_of resolves the inspector to its mobile key (mobile wins over email)');

    // The UNION linker finds every record sharing ANY identifier — so the email-only portal user
    // links to the mobile-keyed inspector because they share the email.
    $recs = party_records_for($mob, $email, '');
    $kinds = array_column($recs, 'kind');
    t_ok(in_array('INSPECTOR', $kinds, true), 'the inspector record is found');
    t_ok(in_array('CANDIDATE', $kinds, true), 'the candidate for the same person is found (shared mobile+email)');
    t_ok(in_array('CLIENT_USER', $kinds, true), 'the email-only portal user links via the shared email');
    foreach ($recs as $r) t_ok(strpos((string)$r['name'], 'Someone Else') === false, 'a different person is never matched in');

    // party_records_for from any of the person's records gives the same set.
    $idn = party_identity_of('CANDIDATE', $candId);
    t_eq($idn['email'], $email, 'party_identity_of reads the record contact points');
    t_ok(count(party_records_for($idn['mobile'], $idn['email'], $idn['ref'])) === count($recs), 'resolving from any of the person\'s records gives the same set');

    // Roles roll up the distinct stores.
    $roles = party_roles_of('INSPECTOR', $insId);
    t_ok(count($roles) >= 3, 'the person holds at least three roles (inspector + candidate + portal user)');

    // A mobile-only lookup still links inspector + candidate (both carry the mobile).
    $mrecs = party_records_for($mob, '', '');
    t_ok(count(array_filter($mrecs, fn($r) => in_array($r['kind'], ['INSPECTOR','CANDIDATE'], true))) >= 2, 'a mobile-only lookup links the inspector + candidate');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// wiring
$idx = file_get_contents(__DIR__ . '/../index.php');
$cand = file_get_contents(__DIR__ . '/../views/ops/candidate_detail.php');
t_ok(strpos($idx, "require __DIR__ . '/lib/party.php'") !== false, 'the party layer is loaded by the front controller');
t_ok(strpos($cand, "party_render_also('CANDIDATE'") !== false, 'the candidate detail shows the same-person panel');
