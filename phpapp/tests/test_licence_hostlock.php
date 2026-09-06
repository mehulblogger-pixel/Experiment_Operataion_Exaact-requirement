<?php
// Licence hardening — optional host/domain binding. A key may name the address(es)
// it is licensed for; a copy on the wrong address goes read-only. Backward
// compatible: a key with no host claim is unrestricted. Loopback always allowed.
t_section('licence host/domain binding');

// --- No host claim → unrestricted (existing keys unaffected) ---
$r = lk_host_ok(['seats' => 10], 'anything.example.com');
t_ok($r['ok'], 'a key with no host claim runs anywhere (backward compatible)');
t_eq(count($r['allow']), 0, 'no host restriction is reported when none is set');

// --- Exact host match ---
t_ok(lk_host_ok(['hosts' => 'hr.acme.com'], 'hr.acme.com')['ok'], 'the licensed host is allowed');
t_ok(!lk_host_ok(['hosts' => 'hr.acme.com'], 'hr.evil.com')['ok'], 'a different host is blocked');

// --- Host normalisation: scheme, port and path are ignored ---
t_ok(lk_host_ok(['hosts' => 'hr.acme.com'], 'https://hr.acme.com:8080/login')['ok'], 'scheme, port and path are ignored when matching');
t_ok(lk_host_ok(['host' => 'HR.ACME.COM'], 'hr.acme.com')['ok'], 'matching is case-insensitive and honours the singular "host" claim');

// --- Multiple allowed hosts (array and comma string) ---
t_ok(lk_host_ok(['hosts' => ['a.acme.com', 'b.acme.com']], 'b.acme.com')['ok'], 'any host in the allow list is accepted (array form)');
t_ok(lk_host_ok(['hosts' => 'a.acme.com, b.acme.com'], 'a.acme.com')['ok'], 'the comma-separated form is accepted too');
t_ok(!lk_host_ok(['hosts' => 'a.acme.com, b.acme.com'], 'c.acme.com')['ok'], 'a host outside the list is blocked');

// --- Wildcard subdomain ---
t_ok(lk_host_ok(['hosts' => '*.acme.com'], 'hr.acme.com')['ok'], 'a wildcard matches a subdomain');
t_ok(lk_host_ok(['hosts' => '*.acme.com'], 'acme.com')['ok'], 'a wildcard also matches the bare domain');
t_ok(!lk_host_ok(['hosts' => '*.acme.com'], 'acme.co')['ok'], 'a wildcard does not match a different domain');

// --- Loopback / internal / CLI is always allowed (never lock out a laptop or cron) ---
foreach (['localhost', '127.0.0.1', '::1', 'box.localhost', ''] as $h)
    t_ok(lk_host_ok(['hosts' => 'hr.acme.com'], $h)['ok'], "loopback/CLI host '" . ($h ?: 'cli') . "' is never blocked");

// --- The allow list is reported (for the Licence screen) ---
$rep = lk_host_ok(['hosts' => 'HR.Acme.com'], 'hr.acme.com');
t_eq($rep['allow'][0], 'hr.acme.com', 'the licensed host is normalised for display');

// Sanity: normaliser strips scheme/port/path.
t_eq(lk_host_norm('HTTP://Sub.Acme.com:9000/x'), 'sub.acme.com', 'host normalisation lowercases and strips scheme/port/path');
