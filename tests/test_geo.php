<?php
// Geolocation helpers used by geofenced attendance.
t_section('geolocation helpers');

$c = geo_extract('19.0760, 72.8777');
t_ok(is_array($c) && abs($c[0] - 19.0760) < 0.001 && abs($c[1] - 72.8777) < 0.001, "parses a plain 'lat, lon'");

$c2 = geo_extract('https://www.google.com/maps/@19.0760,72.8777,15z');
t_ok(is_array($c2) && abs($c2[0] - 19.0760) < 0.001, 'parses a Google Maps @lat,lon link');

$c3 = geo_extract('https://maps.google.com/?q=19.0760,72.8777');
t_ok(is_array($c3) && abs($c3[0] - 19.0760) < 0.001, 'parses a Maps ?q=lat,lon link');

t_ok(geo_extract('not a location at all') === null, 'rejects nonsense text');
t_ok(geo_valid(0.0, 0.0) === null, 'rejects null island (0,0)');
t_ok(geo_valid(200, 500) === null, 'rejects out-of-range coordinates');

if (function_exists('geo_distance_m')) {
    // ~0.1 degree of longitude at this latitude is roughly 10.5 km.
    $d = geo_distance_m(19.0760, 72.8777, 19.0760, 72.9777);
    t_ok($d > 9000 && $d < 12000, 'haversine distance is about right (' . round($d) . ' m)');
    t_ok(geo_distance_m(19.0760, 72.8777, 19.0760, 72.8777) < 1, 'zero distance for the same point');
}
