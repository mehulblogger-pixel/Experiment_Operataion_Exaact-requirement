<?php
// ============================================================================
//  Geofenced attendance — an inspector may punch in/out only AT the site
//
//  Builds on the existing photo + GPS check-in (lib/trust.php). This adds:
//   - coordinates on the party (customer/vendor) and, per job, an adjustable
//     site point that defaults from the party,
//   - a distance check at punch-in/out: outside the fence, it is refused,
//   - photo made mandatory whenever the fence is enforced.
//
//  Coordinates may be typed as "lat, lon", pasted as a Google/OpenStreetMap
//  link (the app pulls the numbers out), or captured with the device's own
//  "use my location" button. Distance uses the existing haversine helper.
// ============================================================================

// Save a client/vendor's site location from the create/edit form. Blank
// coordinates leave the geofence off for this site (attendance is then not
// pinned to a spot). A radius with no coordinates is meaningless, so it is only
// kept when both coordinates are present.
function partner_save_geofence($pdo, $id, $b) {
    geofence_migrate();
    $lat = isset($b['site_lat']) && trim((string)$b['site_lat']) !== '' ? (float)$b['site_lat'] : null;
    $lon = isset($b['site_lon']) && trim((string)$b['site_lon']) !== '' ? (float)$b['site_lon'] : null;
    // Out-of-range coordinates are a typo, not a location — drop them rather than
    // pin attendance to the middle of the ocean.
    if ($lat === null || $lon === null || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
        $lat = null; $lon = null;
    }
    $rad = ($lat !== null) ? max(0, (int)($b['geofence_m'] ?? 0)) : 0;
    $pdo->prepare("UPDATE business_partners SET site_lat=?, site_lon=?, geofence_m=? WHERE id=?")
        ->execute([$lat, $lon, $rad, (int)$id]);
}

function geofence_migrate() {
    static $done = false; if ($done) return; $done = true;
    if (!function_exists('ensure_column')) return;
    try {
        // On the party — its default site location.
        ensure_column('business_partners', 'site_lat', "DECIMAL(10,7) NULL");
        ensure_column('business_partners', 'site_lon', "DECIMAL(10,7) NULL");
        ensure_column('business_partners', 'geofence_m', "INT DEFAULT 0");
        // On the job — the exact site for THIS inspection (defaults from the party).
        ensure_column('jobs', 'site_lat', "DECIMAL(10,7) NULL");
        ensure_column('jobs', 'site_lon', "DECIMAL(10,7) NULL");
        ensure_column('jobs', 'site_geofence_m', "INT DEFAULT 0");
        ensure_column('jobs', 'site_label', "VARCHAR(160) DEFAULT ''");
        // On each SITE ADDRESS — so a multi-site client's distinct sites each carry
        // their own coordinates. A call points at a site address; every inspection at
        // that site then inherits its location, set once instead of per job.
        ensure_column('partner_addresses', 'site_lat', "DECIMAL(10,7) NULL");
        ensure_column('partner_addresses', 'site_lon', "DECIMAL(10,7) NULL");
        ensure_column('partner_addresses', 'geofence_m', "INT DEFAULT 0");
    } catch (Throwable $e) { /* never let a column add break boot */ }
}

// Whether the fence is switched on for this installation, and the default radius.
function geofence_on() { return setting_get('geofence_on', '0') === '1'; }
function geofence_radius() { $r = (int)setting_get('geofence_radius_m', '250'); return $r > 0 ? $r : 250; }

// Pull [lat, lon] out of a typed pair OR a maps link. Returns [lat,lon] or null.
function geo_extract($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') return null;
    // A plain "lat, lon" (optionally with more after it).
    if (preg_match('/^\s*(-?\d{1,3}(?:\.\d+)?)\s*[, ]\s*(-?\d{1,3}(?:\.\d+)?)/', $raw, $m)) {
        return geo_valid((float)$m[1], (float)$m[2]);
    }
    // Google Maps: .../@LAT,LON,zoom  or  ?q=LAT,LON  or  !3dLAT!4dLON  or  ll=LAT,LON
    if (preg_match('/@(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/', $raw, $m)) return geo_valid((float)$m[1], (float)$m[2]);
    if (preg_match('/[?&](?:q|ll|query|destination)=(-?\d{1,3}\.\d+),\s*(-?\d{1,3}\.\d+)/', $raw, $m)) return geo_valid((float)$m[1], (float)$m[2]);
    if (preg_match('/!3d(-?\d{1,3}\.\d+)!4d(-?\d{1,3}\.\d+)/', $raw, $m)) return geo_valid((float)$m[1], (float)$m[2]);
    // OpenStreetMap: #map=17/LAT/LON  or  mlat=..&mlon=..
    if (preg_match('/mlat=(-?\d{1,3}\.\d+).*?mlon=(-?\d{1,3}\.\d+)/', $raw, $m)) return geo_valid((float)$m[1], (float)$m[2]);
    if (preg_match('#/(-?\d{1,3}\.\d+)/(-?\d{1,3}\.\d+)#', $raw, $m)) return geo_valid((float)$m[1], (float)$m[2]);
    return null;
}
function geo_valid($lat, $lon) {
    if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) return null;
    if ($lat === 0.0 && $lon === 0.0) return null;
    return [round($lat, 7), round($lon, 7)];
}

// Field #6 — the picker "not working" is almost always this: the Google Maps app's
// Share button gives a SHORTLINK (maps.app.goo.gl / goo.gl/maps) that carries NO
// coordinates — it only redirects to the full URL that does — so geo_extract() reads
// nothing from it. geo_extract_deep() follows an allowlisted map link server-side and
// extracts the coordinates from where it lands. Everything geo_extract already handles
// (plain "lat, lon", full /@lat,lon URLs, ?q=…) still resolves with no network at all.

// Only these hosts are ever fetched — a hard allowlist so a pasted link cannot make
// the server reach into the local network (SSRF). Google's own shorteners only ever
// redirect to Google Maps, so resolving them is safe.
function geo_map_host_ok($host) {
    $host = strtolower((string)$host);
    static $ok = ['maps.app.goo.gl', 'goo.gl', 'g.co',
                  'google.com', 'www.google.com', 'google.co.in', 'www.google.co.in',
                  'maps.google.com', 'openstreetmap.org', 'www.openstreetmap.org', 'osm.org'];
    if (in_array($host, $ok, true)) return true;
    return (bool) preg_match('/^maps\.google\.[a-z.]{2,7}$/', $host);   // maps.google.<tld>
}

// Follow a map link's redirects (host-restricted, http/https only, short timeout) and
// return "<final-url> <first bytes of the page>" for geo_extract to mine. Best-effort:
// no cURL, a blocked network, or a non-allowlisted host all return '' rather than throw.
function geo_resolve_url($url) {
    if (!function_exists('curl_init')) return '';
    $host = parse_url(trim((string)$url), PHP_URL_HOST);
    if (!$host || !geo_map_host_ok($host)) return '';
    $ch = curl_init(trim((string)$url));
    if ($ch === false) return '';
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION  => true,
        CURLOPT_MAXREDIRS       => 6,
        CURLOPT_TIMEOUT         => 6,
        CURLOPT_CONNECTTIMEOUT  => 4,
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_PROTOCOLS       => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_USERAGENT       => 'Mozilla/5.0 (compatible; Exaact-geo/1.0)',
        CURLOPT_SSL_VERIFYPEER  => true,
    ]);
    $body = curl_exec($ch);
    $eff  = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return trim($eff . ' ' . (is_string($body) ? substr($body, 0, 200000) : ''));
}

// geo_extract, then — only if that read nothing AND the input is a map URL — resolve
// the link and try again. This is what the save handlers call.
function geo_extract_deep($raw) {
    $direct = geo_extract($raw);
    if ($direct !== null) return $direct;
    $raw = trim((string)$raw);
    if (stripos($raw, 'http://') !== 0 && stripos($raw, 'https://') !== 0) return null;
    $resolved = geo_resolve_url($raw);
    return $resolved === '' ? null : geo_extract($resolved);
}

// The party (customer/vendor) whose location a job's site defaults from: the
// vendor/manufacturer being inspected if there is one, else the client.
function geofence_job_party($job) {
    $callId = (int)($job['call_id'] ?? 0);
    if (!$callId) return null;
    $call = ops_one("SELECT client_id, vendor_id FROM calls WHERE id=?", [$callId]);
    if (!$call) return null;
    foreach (['vendor_id', 'client_id'] as $k) {
        if (!empty($call[$k])) {
            $p = ops_one("SELECT id, COALESCE(display_name,legal_name) nm, site_lat, site_lon, geofence_m FROM business_partners WHERE id=?", [(int)$call[$k]]);
            if ($p && $p['site_lat'] !== null && $p['site_lon'] !== null) return $p;
        }
    }
    return null;
}

// The specific SITE ADDRESS this job's call points at (calls.site_address_id), with
// its own coordinates — so different sites under one client/contract each fence to
// their own spot. Returns the address row (with coords) or null.
function geofence_call_site($job) {
    $callId = (int)($job['call_id'] ?? 0);
    if (!$callId) return null;
    $addrId = (int) ops_val("SELECT site_address_id FROM calls WHERE id=?", [$callId]);
    if (!$addrId) return null;
    $a = ops_one("SELECT id, label, line1, city, site_lat, site_lon, geofence_m FROM partner_addresses WHERE id=?", [$addrId]);
    if ($a && $a['site_lat'] !== null && $a['site_lon'] !== null) return $a;
    return null;
}

// The target the check-in is measured against for a job, in priority order:
//   1. the job's OWN site point (a manual override for this one inspection),
//   2. the call's SITE ADDRESS coordinates (set once per site, inherited by every
//      call/job at that site — the multi-site answer),
//   3. the party's (customer/vendor) default location.
// Returns [lat,lon,radius,label,source] or null.
function geofence_target($job) {
    geofence_migrate();
    if ($job['site_lat'] !== null && $job['site_lon'] !== null && $job['site_lat'] !== '') {
        $r = (int)($job['site_geofence_m'] ?? 0) ?: geofence_radius();
        return ['lat' => (float)$job['site_lat'], 'lon' => (float)$job['site_lon'], 'radius' => $r,
                'label' => (string)($job['site_label'] ?? '') ?: 'the job site', 'source' => 'job'];
    }
    $a = geofence_call_site($job);
    if ($a) {
        $r = (int)($a['geofence_m'] ?? 0) ?: geofence_radius();
        $lbl = trim((string)($a['label'] ?: trim(($a['line1'] ?? '') . ' ' . ($a['city'] ?? '')))) ?: 'the site';
        return ['lat' => (float)$a['site_lat'], 'lon' => (float)$a['site_lon'], 'radius' => $r,
                'label' => $lbl, 'source' => 'address'];
    }
    $p = geofence_job_party($job);
    if ($p) {
        $r = (int)($p['geofence_m'] ?? 0) ?: geofence_radius();
        return ['lat' => (float)$p['site_lat'], 'lon' => (float)$p['site_lon'], 'radius' => $r,
                'label' => (string)$p['nm'], 'source' => 'party'];
    }
    return null;
}

// The verdict for a punch at (lat,lon) on a job. Returns:
//   ['ok'=>true] when inside the fence or the fence does not apply,
//   ['ok'=>false,'msg'=>...] when outside and enforcement is on.
// $dist is filled with the metres from the site when a target exists.
function geofence_check($job, $lat, $lon, &$dist = null) {
    $dist = null;
    if (!geofence_on()) return ['ok' => true];
    $t = geofence_target($job);
    if (!$t) return ['ok' => true];                 // nothing to measure against — do not block
    $dist = geo_distance_m($t['lat'], $t['lon'], $lat, $lon);
    if ($dist === null) return ['ok' => true];
    if ($dist <= $t['radius']) return ['ok' => true];
    return ['ok' => false, 'dist' => $dist, 'radius' => $t['radius'],
            'msg' => 'You are about ' . $dist . ' m from ' . $t['label'] . '. Punching in or out only works within '
                   . $t['radius'] . ' m of the site. Move closer and try again — or ask a manager to adjust the site location.'];
}

// Photo is mandatory whenever the fence is enforced (a punch with no photo is
// not evidence of anyone being anywhere).
function geofence_photo_required() { return geofence_on() || checkin_photo_required(); }

// A small, self-contained editor for a location: paste a Maps link OR type
// "lat, long" OR press "use my location", plus a radius. $action is the POST
// route; $id the record id; $cur = ['lat'=>,'lon'=>,'rad'=>,'label'=>].
function geofence_editor($action, $id, $cur, $withLabel = false) {
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $lat = $cur['lat'] ?? null; $lon = $cur['lon'] ?? null;
    $has = $lat !== null && $lat !== '' && $lon !== null && $lon !== '';
    $coords = $has ? ($lat . ', ' . $lon) : '';
    $csrf = function_exists('csrf_token') ? csrf_token() : '';
    $uid = 'gf' . $id . substr(md5($action . $id), 0, 4);
    ob_start(); ?>
    <form method="post" action="/<?= $e($action) ?>" style="margin:0">
      <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$id ?>">
      <label class="form-label">Site location</label>
      <input class="form-control" id="<?= $uid ?>c" name="coords" value="<?= $e($coords) ?>"
             placeholder="Paste a Google Maps link (incl. the app's Share link), or type: 19.0760, 72.8777">
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:6px">
        <button class="btn small secondary" type="button" onclick="gfLoc('<?= $uid ?>c', this)">📍 Use my current location</button>
        <a class="btn small secondary" href="https://www.google.com/maps/search/?api=1&amp;query=<?= $has ? $e($lat) . ',' . $e($lon) : '' ?>" target="_blank" rel="noopener">🔎 Find on maps ↗</a>
        <?php if ($has): ?><a class="btn small secondary" href="https://www.google.com/maps?q=<?= $e($lat) ?>,<?= $e($lon) ?>" target="_blank" rel="noopener">View on map ↗</a><?php endif; ?>
        <label style="font-size:12.5px" class="muted">Allowed distance
          <input class="form-control" type="number" name="geofence_m" value="<?= (int)($cur['rad'] ?? 0) ?>" min="0" max="20000" style="width:90px;display:inline-block" placeholder="default"> m</label>
      </div>
      <p class="muted" style="font-size:11.5px;margin:6px 0 0">On the Google Maps app: open the place → <b>Share</b> → copy the link, and paste it here. The app reads the coordinates out of it.</p>
      <?php if ($withLabel): ?><input class="form-control" name="site_label" value="<?= $e($cur['label'] ?? '') ?>" placeholder="Site name (optional)" style="margin-top:6px"><?php endif; ?>
      <button class="btn small" type="submit" style="margin-top:8px">Save location</button>
    </form>
    <script>function gfLoc(id, btn){var b=btn||(typeof event!=='undefined'&&event&&event.target);if(!navigator.geolocation){if(b)b.textContent='📍 Not supported';alert('This browser cannot provide a location. On a laptop, type the coordinates or paste a Google Maps link instead.');return;}if(b)b.textContent='Locating…';navigator.geolocation.getCurrentPosition(function(p){document.getElementById(id).value=p.coords.latitude.toFixed(7)+', '+p.coords.longitude.toFixed(7);if(b)b.textContent='📍 Got it';},function(){if(b)b.textContent='📍 Use my current location';alert('Could not get your location. Allow location access (needs HTTPS), or paste a Google Maps link instead.');},{enableHighAccuracy:true,timeout:10000});}</script>
    <?php return ob_get_clean();
}

// ---- Save handlers: coordinates on the party and on the job ------------------
function geofence_save_party($route, $method) {
    ops_require(is_master() || (function_exists('can') && can('master.manage')) || is_admin_level(), 'You cannot set a party location.');
    geofence_migrate();
    $pid = (int)($_POST['id'] ?? 0);
    $p = $pid ? ops_one("SELECT id FROM business_partners WHERE id=?", [$pid]) : null;
    if (!$p) { flash('Party not found.', 'error'); redirect('/'); }
    $c = geo_extract_deep((string)($_POST['coords'] ?? ''));
    if ($c === null && trim((string)($_POST['coords'] ?? '')) !== '') { flash('Could not read a location from that. Paste a Google Maps link (the app’s Share link works too), or type "lat, long".', 'error'); redirect('/customer?id=' . $pid); }
    $rad = max(0, (int)($_POST['geofence_m'] ?? 0));
    if ($c === null) { db()->prepare("UPDATE business_partners SET site_lat=NULL, site_lon=NULL, geofence_m=? WHERE id=?")->execute([$rad, $pid]); flash('Location cleared.'); }
    else { db()->prepare("UPDATE business_partners SET site_lat=?, site_lon=?, geofence_m=? WHERE id=?")->execute([$c[0], $c[1], $rad, $pid]); flash('Site location saved.'); }
    redirect('/customer?id=' . $pid);
}

function geofence_save_job($route, $method) {
    ops_require(is_master() || (function_exists('can') && can('mod.jobs.edit')) || is_coordinator_level(), 'You cannot set a job site.');
    geofence_migrate();
    $jid = (int)($_POST['id'] ?? 0);
    $job = $jid ? ops_one("SELECT id FROM jobs WHERE id=?", [$jid]) : null;
    if (!$job) { flash('Job not found.', 'error'); redirect('/'); }
    $c = geo_extract_deep((string)($_POST['coords'] ?? ''));
    if ($c === null && trim((string)($_POST['coords'] ?? '')) !== '') { flash('Could not read a location. Paste a Google Maps link (the app’s Share link works too), or type "lat, long".', 'error'); redirect('/job?id=' . $jid); }
    $rad = max(0, (int)($_POST['geofence_m'] ?? 0));
    $label = substr(trim((string)($_POST['site_label'] ?? '')), 0, 160);
    if ($c === null) { db()->prepare("UPDATE jobs SET site_lat=NULL, site_lon=NULL, site_geofence_m=?, site_label=? WHERE id=?")->execute([$rad, $label, $jid]); flash('Job site cleared — it will use the site / party location.'); }
    else { db()->prepare("UPDATE jobs SET site_lat=?, site_lon=?, site_geofence_m=?, site_label=? WHERE id=?")->execute([$c[0], $c[1], $rad, $label, $jid]); flash('Job site location saved.'); }
    redirect('/job?id=' . $jid);
}

// Save coordinates on a SITE ADDRESS (the partner detail → Addresses tab), so every
// call/job that points at that address fences to it. Blank clears it (fence off).
function geofence_save_address($route, $method) {
    ops_require(is_master() || (function_exists('can') && (can('mod.clients.edit') || can('mod.vendors.edit'))) || is_coordinator_level(),
        'You cannot set a site location.');
    geofence_migrate();
    $aid = (int)($_POST['id'] ?? 0);
    $a = $aid ? ops_one("SELECT id, partner_id FROM partner_addresses WHERE id=?", [$aid]) : null;
    if (!$a) { flash('Address not found.', 'error'); redirect('/'); }
    $back = '/partner?id=' . (int)$a['partner_id'] . '&tab=addresses';
    $c = geo_extract_deep((string)($_POST['coords'] ?? ''));
    if ($c === null && trim((string)($_POST['coords'] ?? '')) !== '') { flash('Could not read a location. Paste a Google Maps link (the app’s Share link works too), or type "lat, long".', 'error'); redirect($back); }
    $rad = max(0, (int)($_POST['geofence_m'] ?? 0));
    if ($c === null) { db()->prepare("UPDATE partner_addresses SET site_lat=NULL, site_lon=NULL, geofence_m=? WHERE id=?")->execute([$rad, $aid]); flash('Site location cleared.'); }
    else { db()->prepare("UPDATE partner_addresses SET site_lat=?, site_lon=?, geofence_m=? WHERE id=?")->execute([$c[0], $c[1], $rad, $aid]); flash('Site location saved.'); }
    redirect($back);
}

// On-site capture: the engineer standing at the site saves their device GPS fix as
// the SITE'S location — the reliable way to pin a site whose exact coordinates were
// never known. Written to the call's site address (so future inspections there inherit
// it) when the call has one, else pinned on the job. The owning inspector may do it.
function geofence_capture_site($route, $method) {
    geofence_migrate();
    $jid = (int)($_POST['id'] ?? 0);
    $job = $jid ? ops_one("SELECT id, call_id FROM jobs WHERE id=?", [$jid]) : null;
    if (!$job) { flash('Job not found.', 'error'); redirect('/'); }
    ops_require(is_master() || is_coordinator_level() || (function_exists('job_owned_by_me') && job_owned_by_me($jid)),
        'You cannot set this site location.');
    $c = geo_extract_deep((string)($_POST['coords'] ?? ''));
    if ($c === null) { flash('No location was captured — allow location access and try again.', 'error'); redirect('/job?id=' . $jid); }
    $addrId = (int) ops_val("SELECT site_address_id FROM calls WHERE id=?", [(int)$job['call_id']]);
    if ($addrId) {
        db()->prepare("UPDATE partner_addresses SET site_lat=?, site_lon=? WHERE id=?")->execute([$c[0], $c[1], $addrId]);
        flash('Saved as this site’s location — future inspections at this site inherit it.');
    } else {
        db()->prepare("UPDATE jobs SET site_lat=?, site_lon=? WHERE id=?")->execute([$c[0], $c[1], $jid]);
        flash('Saved as this ' . (function_exists('Tl') ? Tl('job') : 'job') . '’s site location.');
    }
    redirect('/job?id=' . $jid);
}
