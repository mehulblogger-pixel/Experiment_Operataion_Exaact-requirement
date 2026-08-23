<?php
// ============================================================================
//  Who a panel is for — the role-first half of collapsing a screen
//
//  THE COMPLAINT THIS ANSWERS: "why do we have complicated screens".
//
//  Measured on the job screen, one closed job, nothing else going on:
//
//      Master Admin ......... 13 panels, 4 tabs, 31 kB
//      Operation Manager .... 12 panels, 4 tabs, 27 kB
//      Coordinator .......... 13 panels, 4 tabs, 29 kB
//      Inspector ............  9 panels, 4 tabs, 13 kB
//      Finance .............. 10 panels, 4 tabs, 14 kB
//
//  The gates already written do real work — an inspector is shown less than
//  half of what a coordinator is. What none of them do is remove a whole
//  SECTION. Every role gets all four tabs, so Finance, who came to raise one
//  invoice, is handed "who goes on each day", "site check-in" and "hold &
//  witness points" before reaching the panel they came for.
//
//  A screen that shows the union of every role's work is the biggest single
//  reason this app feels complicated, and it is presentation, not
//  architecture: the data and the flow behind it are sound. So each panel
//  names the WORK it belongs to, once, and this file — one table, in one
//  place, reviewable at a glance — says which roles do that work. Nothing is
//  rebuilt; sections that are not yours simply do not render, and the tab bar,
//  which is built from the panels that are actually present, loses the tab
//  with them.
//
//  Two rules it will not bend on:
//
//    1. UNREGISTERED WORK IS SHOWN. A panel whose key is not in the table
//       appears for everybody, and so does every panel for somebody who fits
//       no role bucket at all. Forgetting to register a panel must cost
//       somebody a tidier screen, never a capability they need.
//
//    2. THIS HIDES CLUTTER, NOT SECRETS. Every figure that must not be seen —
//       revenue, credit, profitability, salary — stays gated by can() at the
//       point it is rendered, exactly as before. Leaving a section off a
//       screen is a tidiness decision, and it must never be the only thing
//       standing between somebody and a number they are not cleared for.
// ============================================================================

// The work each panel key belongs to, and who does that work. Editing this
// table is the whole interface: it is meant to be argued with by the people
// who run the business, not buried in a template.
const SCREEN_AUDIENCE = [
    // The job screen.
    'job.schedule' => ['ops', 'inspector'],  // who goes which day, day-completion, site check-in
    'job.qa'       => ['ops', 'inspector'],  // QAPs, reports, hold & witness points
    'job.expenses' => ['ops', 'inspector', 'finance'],  // what it cost — the inspector's own claim
    'job.money'    => ['ops', 'finance'],    // invoice, payment, credit
];

// Which buckets the person signed in belongs to. Deliberately coarse: three
// kinds of work, not seventeen job titles. A Master Admin does every kind, by
// definition of the role rather than by listing them.
function screen_buckets() {
    if (is_master()) return ['ops', 'inspector', 'finance'];
    $b = [];
    if (is_coordinator_level()) $b[] = 'ops';
    if (is_inspector())         $b[] = 'inspector';
    // Finance is a job, not only a role name: anyone holding the money
    // permissions is here to do money work, whatever they are called.
    if (user_role() === 'FINANCE' || can('finance.reconcile') || can('data.credit')) $b[] = 'finance';
    return $b;
}

// Rule 1 lives here. Both fallbacks — an unregistered key, and a viewer in no
// bucket — return true, so the failure mode of this file is a screen that is
// merely untidy rather than one missing the panel somebody needed.
function screen_shows($key) {
    $want = SCREEN_AUDIENCE[$key] ?? null;
    if ($want === null) return true;
    $mine = screen_buckets();
    if (!$mine) return true;
    foreach ($mine as $b) if (in_array($b, $want, true)) return true;
    return false;
}
