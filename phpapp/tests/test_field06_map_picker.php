<?php
// Field-finding #6 — the site-location picker ("Find on maps" / "Pick on app") didn't work. Root
// cause: the thing people actually paste — the Google Maps app's Share SHORTLINK
// (maps.app.goo.gl / goo.gl/maps) — carries NO coordinates, so geo_extract() read nothing and the
// save was refused. Fix: geo_extract_deep() resolves an allowlisted map link server-side (SSRF-guarded)
// and reads the coordinates from where it lands; plus client polish (a "Find on maps" button, a
// robust "use my current location", and a Share-link hint).
t_section('Field #6 — the site-location picker reads a Google Maps link');

// Everything geo_extract already handled still resolves with NO network.
t_eq([19.076, 72.8777], geo_extract_deep('19.0760, 72.8777'), 'a typed "lat, lon" still works');
t_eq([18.92, 72.83], geo_extract_deep('https://www.google.com/maps/place/X/@18.92,72.83,17z'), 'a full /@lat,lon URL still works');
t_eq([18.9219, 72.8347], geo_extract_deep('https://www.google.com/maps?q=18.9219,72.8347'), 'a ?q=lat,lon URL still works');
// Plain junk (not a URL) is rejected fast — never a network attempt.
t_ok(geo_extract_deep('somewhere near the plant') === null, 'free text with no coordinates is rejected');

// The SSRF guard: only Google/OSM map hosts are ever fetched.
t_ok(geo_map_host_ok('maps.app.goo.gl') && geo_map_host_ok('goo.gl') && geo_map_host_ok('maps.google.co.in'),
     'the Google Maps share-link hosts are allowlisted');
t_ok(!geo_map_host_ok('evil.example.com') && !geo_map_host_ok('169.254.169.254') && !geo_map_host_ok('localhost'),
     'arbitrary and internal hosts are NOT fetched (no SSRF)');
// geo_resolve_url refuses a non-allowlisted host before making any request.
t_eq('', geo_resolve_url('http://evil.example.com/steal'), 'a non-allowlisted URL is never fetched');
t_eq('', geo_resolve_url('http://169.254.169.254/latest/meta-data/'), 'a cloud-metadata URL is never fetched');
// A shortlink IS a candidate for resolution (its host is allowlisted); resolution is best-effort
// and returns null gracefully when the network is unavailable — never an exception.
$sl = geo_extract_deep('https://maps.app.goo.gl/AbCdEf');
t_ok($sl === null || (is_array($sl) && count($sl) === 2), 'a share shortlink resolves to coords, or degrades to null — never throws');

// All four save handlers use the deep extractor (so a pasted link is resolved everywhere).
$src = file_get_contents(__DIR__ . '/../lib/geofence.php');
t_eq(4, substr_count($src, "geo_extract_deep((string)(\$_POST['coords']"), 'every save handler resolves the pasted link');

// Client polish: a "Find on maps" button, a robust use-my-location (takes the button, no global event),
// and a Share-link hint.
t_ok(strpos($src, '🔎 Find on maps') !== false, 'a "Find on maps" button opens Google Maps search');
t_ok(strpos($src, "gfLoc('<?= \$uid ?>c', this)") !== false && strpos($src, 'function gfLoc(id, btn)') !== false,
     'the current-location button passes itself (works without the global `event`)');
t_ok(strpos($src, 'Share') !== false && strpos($src, 'paste it here') !== false,
     'the picker tells the user how to copy a link from the Maps app');
