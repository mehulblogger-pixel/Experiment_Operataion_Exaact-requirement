<?php
// ===========================================================================
//  GUIDED ONBOARDING — the "getting started" next-steps a brand-new company
//  sees, so nobody is dumped into a deep module with no orientation and nobody
//  has to know a URL. Each step is computed from real data (done / not done) and
//  the wording adapts to the install mode (cloud vs licence). Read-only.
// ===========================================================================

// Count helper that never throws (a missing table just reads as 0).
function _onb_count($sql, $args = []) { try { return (int)ops_val($sql, $args); } catch (Throwable $e) { return 0; } }

/** Has the operating company added anyone beyond the first admin? */
function onboarding_has_team()        { return _onb_count("SELECT COUNT(*) FROM users WHERE COALESCE(is_active,1)=1") > 1; }
/** Has it posted any requirement / job yet? */
function onboarding_has_requirement() { return _onb_count("SELECT COUNT(*) FROM cx_requirements") > 0; }
/** Does it have any of its own people on record (bench or inspectors)? */
function onboarding_has_people()      { return _onb_count("SELECT COUNT(*) FROM inspectors") > 0 || _onb_count("SELECT COUNT(*) FROM cx_bench") > 0; }

/**
 * The getting-started steps, in order, each: key, label, why, url, icon, done.
 * They follow the modules THIS company has switched on, not the kind of install:
 * an operations company is asked to add its field team and raise a work order;
 * one using the marketplace is asked to post a requirement and build a bench.
 * (They used to follow the install mode, which sent every cloud company to the
 * marketplace and pointed "Add your people" at an address that does not exist.)
 */
function onboarding_steps() {
    $on = fn($k) => !function_exists('licence_enabled') || licence_enabled($k);
    $team = onboarding_has_team(); $req = onboarding_has_requirement(); $ppl = onboarding_has_people();
    $steps = [];
    $steps[] = ['key' => 'team', 'icon' => '👥', 'label' => 'Invite your team',
        'why' => 'Add colleagues so they can log in and help run the work.', 'url' => '/users', 'done' => $team];
    if ($on('operations')) {
        $steps[] = ['key' => 'people', 'icon' => '🧑‍🔧', 'label' => 'Add your people',
            'why' => 'Put the inspectors and technicians you work with on record.', 'url' => '/m/inspectors',
            'done' => _onb_count("SELECT COUNT(*) FROM inspectors") > 0];
        $steps[] = ['key' => 'call', 'icon' => '📋', 'label' => 'Raise your first work order',
            'why' => 'Record an inspection call to start the flow from enquiry to report and invoice.', 'url' => '/call-new',
            'done' => _onb_count("SELECT COUNT(*) FROM calls") > 0];
    }
    if ($on('connect')) {
        $steps[] = ['key' => 'req', 'icon' => '📢', 'label' => 'Post your first requirement',
            'why' => 'Tell the marketplace who you need — qualified people can apply.', 'url' => '/connect-requirements', 'done' => $req];
        $steps[] = ['key' => 'bench', 'icon' => '⭐', 'label' => 'Build your bench',
            'why' => 'Add people you already work with, or search the shared pool.', 'url' => '/connect-bench',
            'done' => _onb_count("SELECT COUNT(*) FROM cx_bench") > 0];
    }
    return $steps;
}

/** How many steps are still to do (0 = fully set up). */
function onboarding_remaining() { $n = 0; foreach (onboarding_steps() as $s) if (empty($s['done'])) $n++; return $n; }
/** Is the company still getting set up? (drives whether the welcome card shows). */
function onboarding_incomplete() { return onboarding_remaining() > 0; }

/** The guided welcome / getting-started screen. Gated to signed-in staff. */
function ops_welcome($method) {
    if (function_exists('current_user') && !current_user()) { redirect('/login'); return true; }
    $_SESSION['onb_seen'] = 1;   // seen this session → the home stops auto-redirecting here
    $steps = onboarding_steps();
    view('ops/welcome', [
        'steps'   => $steps,
        'remaining' => onboarding_remaining(),
        'appName' => (string)(function_exists('setting_get') ? setting_get('app_name', '') : ''),
        'licence' => function_exists('install_is_licence') && install_is_licence(),
    ]);
    return true;
}
