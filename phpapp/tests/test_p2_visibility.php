<?php
// Phase 2 §72 — one visibility vocabulary and one single-record gate over the existing per-record
// flags (report_docs.vendor_visible, nonconformities.visibility, *_client_visible). The canonical
// layer DELEGATES to cvp_can_see() for every code cvp already knows, so it can never diverge from the
// portal-query filtering; it reads the flags and changes none of them.
t_section('Phase 2 §72 — canonical visibility gate (over existing flags)');

t_ok(function_exists('visibility_can_see') && function_exists('visibility_class_of') && function_exists('visibility_normalize'),
     'the visibility helpers exist');
$idx = file_get_contents(__DIR__ . '/../index.php');
t_ok(strpos($idx, "require __DIR__ . '/lib/visibility.php'") !== false, 'the visibility lib is loaded by the front controller');

// Legacy codes fold onto canonical classes; unknown/blank fails closed to CONFIDENTIAL.
t_eq(visibility_normalize('CLIENT_VISIBLE'), 'CLIENT', 'CLIENT_VISIBLE folds to CLIENT');
t_eq(visibility_normalize('VENDOR_VISIBLE'), 'VENDOR', 'VENDOR_VISIBLE folds to VENDOR');
t_eq(visibility_normalize('MGMT_ONLY'), 'CONFIDENTIAL', 'MGMT_ONLY folds to CONFIDENTIAL');
t_eq(visibility_normalize('RESTRICTED'), 'CONFIDENTIAL', 'RESTRICTED folds to CONFIDENTIAL');
t_eq(visibility_normalize(''), 'CONFIDENTIAL', 'blank fails closed to CONFIDENTIAL');
t_eq(visibility_normalize('nonsense'), 'CONFIDENTIAL', 'an unknown code fails closed to CONFIDENTIAL');

// The gate: staff see all; the safe default is never "shown".
t_ok(visibility_can_see('CONFIDENTIAL', 'INTERNAL') === true, 'staff (INTERNAL) see even confidential records');
t_ok(visibility_can_see('INTERNAL', 'CLIENT') === false, 'a client cannot see an internal record');
t_ok(visibility_can_see('INTERNAL', 'VENDOR') === false, 'a vendor cannot see an internal record');
t_ok(visibility_can_see('CLIENT', 'CLIENT') === true, 'a client sees a client record');
t_ok(visibility_can_see('CLIENT', 'VENDOR') === false, 'a vendor cannot see a client-only record');
t_ok(visibility_can_see('VENDOR', 'VENDOR') === true, 'a vendor sees a vendor record');
t_ok(visibility_can_see('SHARED', 'CLIENT') === true && visibility_can_see('SHARED', 'VENDOR') === true, 'both parties see a shared record');
t_ok(visibility_can_see('PUBLIC', 'PUBLIC') === true, 'a public record is visible to an unauthenticated reader');
t_ok(visibility_can_see('CLIENT', 'PUBLIC') === false, 'a client record is NOT public');

// The critical no-divergence property: for every code cvp knows and every audience, the canonical
// gate must give exactly the same answer as cvp_can_see().
if (function_exists('cvp_can_see') && defined('CVP_VISIBILITY_AUDIENCE')) {
    $legacyBack = ['CLIENT' => 'CLIENT_VISIBLE', 'VENDOR' => 'VENDOR_VISIBLE', 'SHARED' => 'SHARED',
                   'INTERNAL' => 'INTERNAL', 'CONFIDENTIAL' => 'RESTRICTED'];
    $agree = true;
    foreach (array_keys(CVP_VISIBILITY_AUDIENCE) as $code) {
        foreach (['CLIENT', 'VENDOR'] as $au) {
            $canon = visibility_can_see(visibility_normalize($code), $au);
            $cvp   = cvp_can_see($code, $au);
            if ($canon !== $cvp) { $agree = false; }
        }
    }
    t_ok($agree, 'the canonical gate agrees with cvp_can_see() for every existing code × audience (no divergence)');
}

// Classifying an actual record from its existing flag.
t_eq(visibility_class_of('REPORT', ['vendor_visible' => 1]), 'VENDOR', 'a vendor-shared report classifies as VENDOR');
t_eq(visibility_class_of('REPORT', ['vendor_visible' => 0]), 'INTERNAL', 'an unshared report is INTERNAL');
t_eq(visibility_class_of('NCR', ['visibility' => 'CLIENT_VISIBLE']), 'CLIENT', 'an NCR carries its visibility code through');
t_eq(visibility_class_of('SITELOG', ['client_visible' => 1]), 'CLIENT', 'a client-visible site-log line classifies as CLIENT');
t_eq(visibility_class_of('SITELOG', ['client_visible' => 0]), 'INTERNAL', 'an internal site-log line is INTERNAL');
