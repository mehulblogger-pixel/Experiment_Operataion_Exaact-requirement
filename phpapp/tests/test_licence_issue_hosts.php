<?php
// The Licence console can put a host lock on a key. This checks the host claim
// survives the exact encode/decode the console (lk_issue) and the client
// (lk_verify) use, and that the client then enforces it. Signature verification
// itself is unchanged and covered elsewhere.
t_section('licence console — host lock round-trip');

licissue_migrate();

// A claims payload as the console builds it, carrying a host lock.
$claims = ['cust' => 'Acme Manpower', 'exp' => '2027-03-31', 'seats' => 12, 'grace' => 15, 'hosts' => 'hr.acme.com'];
$payload = lk_b64e(json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));   // as lk_issue encodes
$decoded = json_decode(lk_b64d($payload), true);                                            // as lk_verify decodes
t_eq($decoded['hosts'], 'hr.acme.com', 'the host lock survives the key encoding');
t_eq((int)$decoded['seats'], 12, 'seats survive the encoding');
t_eq($decoded['exp'], '2027-03-31', 'the expiry survives the encoding');

// And the client enforces it.
t_ok(lk_host_ok($decoded, 'hr.acme.com')['ok'], 'a host-locked key runs on its licensed address');
t_ok(!lk_host_ok($decoded, 'pirate.example.com')['ok'], 'the same key is blocked on a foreign address');
t_ok(lk_host_ok($decoded, 'localhost')['ok'], 'localhost/laptop is never blocked');

// The issued-licences record can store the host lock (migration column present).
$pdo = db();
$pdo->prepare("INSERT INTO issued_licences (ref,customer,seats,exp,hosts,created_at) VALUES ('T-HOST','Acme',12,'2027-03-31','hr.acme.com', ?)")->execute([date('c')]);
t_eq(ops_val("SELECT hosts FROM issued_licences WHERE ref='T-HOST'"), 'hr.acme.com', 'the issued-licence record stores the host lock');
$pdo->prepare("DELETE FROM issued_licences WHERE ref='T-HOST'")->execute();
