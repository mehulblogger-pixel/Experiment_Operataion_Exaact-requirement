<?php
// A multi-site client's distinct sites each carry their OWN coordinates, on the site
// address (partner_addresses). A call points at a site address; the geofence for its
// jobs resolves job-own → the call's site address → the party default. So a site's
// location is set once (or captured on site by the engineer) and every inspection
// there inherits it — the answer to "how do different sites store distinct lat/long".
t_section('per-site coordinates on the address, inherited by the call/job');

geofence_migrate();
$pdo = db();

// The columns are on the address now.
$cols = ops_all("PRAGMA table_info(partner_addresses)");
$names = array_map(fn($c) => $c['name'], $cols);
t_ok(in_array('site_lat', $names, true) && in_array('site_lon', $names, true), 'partner_addresses carries its own site coordinates');

// A client with a DEFAULT (party) location, and two site addresses with their own.
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status, site_lat, site_lon, geofence_m) VALUES ('Geo Client','Geo Client',1,'ACTIVE', 19.0000000, 72.0000000, 300)")->execute();
$pid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO partner_addresses (partner_id, address_type, label, line1, city, site_lat, site_lon, geofence_m) VALUES (?, 'SITE','Unit-A','Plot 1','Vapi', 20.3711000, 72.9043000, 150)")->execute([$pid]);
$addrA = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO partner_addresses (partner_id, address_type, label, line1, city) VALUES (?, 'SITE','Unit-B (no coords)','Plot 2','Silvassa')")->execute([$pid]);
$addrB = (int)$pdo->lastInsertId();

// A call at site A, and its job.
$pdo->prepare("INSERT INTO calls (call_code, client_id, site_address_id, status, created_at) VALUES ('GEO-1', ?, ?, 'OPEN', ?)")->execute([$pid, $addrA, date('c')]);
$callA = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO jobs (job_code, call_id, created_at) VALUES ('GEO-J1', ?, ?)")->execute([$callA, date('c')]);
$jobA = ops_one("SELECT * FROM jobs WHERE id=?", [(int)$pdo->lastInsertId()]);

// The job inherits SITE A's coordinates (not the party default).
$t = geofence_target($jobA);
t_ok($t && $t['source'] === 'address', 'the job fences to the call\'s site address, not the party');
t_ok(abs($t['lat'] - 20.3711) < 0.0001 && (int)$t['radius'] === 150, 'it uses the address\'s own coordinates and radius');

// A second site (B) with no coordinates falls back to the party default.
$pdo->prepare("INSERT INTO calls (call_code, client_id, site_address_id, status, created_at) VALUES ('GEO-2', ?, ?, 'OPEN', ?)")->execute([$pid, $addrB, date('c')]);
$callB = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO jobs (job_code, call_id, created_at) VALUES ('GEO-J2', ?, ?)")->execute([$callB, date('c')]);
$jobB = ops_one("SELECT * FROM jobs WHERE id=?", [(int)$pdo->lastInsertId()]);
$tb = geofence_target($jobB);
t_ok($tb && $tb['source'] === 'party', 'a site with no coordinates falls back to the party default');

// On-site capture writes the engineer's fix to the site ADDRESS, so it now resolves
// there (simulating what geofence_capture_site does for a call that has a site address).
$pdo->prepare("UPDATE partner_addresses SET site_lat=?, site_lon=? WHERE id=?")->execute([20.2700000, 73.0100000, $addrB]);
$tb2 = geofence_target($jobB);
t_ok($tb2 && $tb2['source'] === 'address' && abs($tb2['lat'] - 20.27) < 0.0001,
    'once captured on site, the address location is inherited by the job');

// A job's OWN override still wins over the address (a one-off correction).
$pdo->prepare("UPDATE jobs SET site_lat=?, site_lon=?, site_geofence_m=? WHERE id=?")->execute([15.0000000, 74.0000000, 80, (int)$jobA['id']]);
$jobA2 = ops_one("SELECT * FROM jobs WHERE id=?", [(int)$jobA['id']]);
$t2 = geofence_target($jobA2);
t_ok($t2 && $t2['source'] === 'job', 'a per-job override still takes precedence over the site address');

// Wiring: the two new routes and handlers exist.
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "route === 'address-geo'") !== false && strpos($ops, "route === 'site-geo-capture'") !== false,
    'the address-geo and on-site capture routes are dispatched');
t_ok(function_exists('geofence_save_address') && function_exists('geofence_capture_site'), 'the save + capture handlers exist');
$view = file_get_contents(__DIR__ . '/../views/ops/job_detail.php');
t_ok(strpos($view, '/site-geo-capture') !== false && strpos($view, 'save its exact location') !== false,
    'the job check-in offers on-site location capture');
