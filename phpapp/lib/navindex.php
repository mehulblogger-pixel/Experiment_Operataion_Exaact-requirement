<?php
// ============================================================================
//  NAV INDEX — the source for the "Go to anything" command palette.
//
//  The left rail is deliberately shallow: seven area links, each opening a Home
//  full of tiles. That is calm, but a person who knows exactly where they want
//  to go still has to remember which area holds it and take two hops. This file
//  flattens EVERY destination the current user may open into one searchable
//  list, so a command palette (top-bar 🧭, or Ctrl/⌘-K) can jump straight there.
//
//  It reuses the same gated definitions the rail already trusts — ops_area_def()
//  for the seven areas, and the same permission checks the Operations Home uses
//  — so the palette can never offer a screen the rail would have hidden. Purely
//  additive: the menu, the tiles and the record search all stay exactly as they
//  were.
// ============================================================================

// One flat, de-duplicated, permission-filtered list of destinations for the
// signed-in user. Each entry: label, url, area, icon, desc, kind ('screen' |
// 'action'). Cached per request.
function ops_nav_index() {
    static $cache = null;
    if ($cache !== null) return $cache;

    $u = function_exists('current_user') ? current_user() : null;
    if (!$u) return $cache = [];

    $out = [];
    $seen = [];
    $add = function ($label, $url, $area, $icon = '•', $desc = '', $kind = 'screen') use (&$out, &$seen) {
        $label = trim((string)$label);
        $url   = trim((string)$url);
        if ($label === '' || $url === '' || $url === '#') return;
        $key = strtolower($url . '|' . $label);
        if (isset($seen[$key])) return;
        $seen[$key] = true;
        $out[] = ['label' => $label, 'url' => $url, 'area' => $area,
                  'icon' => $icon, 'desc' => (string)$desc, 'kind' => $kind];
    };
    $can = fn($p) => function_exists('can') && can($p);
    $fx  = fn($f) => function_exists($f);
    // Terminology helper, guarded — the rail uses the same words.
    $thp = fn($k) => $fx('THP') ? THP($k) : ucfirst($k);
    $tnew = fn($k) => $fx('T_NEW') ? ucfirst(T_NEW($k)) : ('New ' . $k);

    $isInsp = $fx('is_field_inspector') ? is_field_inspector() : ($fx('is_inspector') && is_inspector());

    // ---- Always-there, cross-module destinations ---------------------------
    $add('Dashboard', '/', 'Home', '🏠', 'Your landing dashboard.');
    $add('Search records', '/search', 'Home', '🔍', 'Search across every register.');
    if ($fx('adv_can') && adv_can())        $add('What to fix', '/advisor', 'Home', '🧭', 'The one action list, money attached.');
    if ($fx('chain_can') && chain_can())    $add('Where the flow is broken', '/flow-gaps', 'Home', '🔗', 'Hand-offs that were skipped.');
    $add('My signature', '/my-signature', 'Me', '✍️', 'The signature on your documents & quotations.');
    $add('Change password', '/change-password', 'Me', '🔑', 'Update your password.');
    $add('Two-step sign-in', '/two-factor', 'Me', '🛡️', 'A phone code as well as a password.');

    // ---- Inspector's own short world ---------------------------------------
    if ($isInsp) {
        $add('My ' . $thp('job'), '/my-jobs', 'My work', '🗂', 'Your assigned work.');
        if ($can('mod.idems.view')) {
            $add('My ' . $thp('report'), '/documents', 'My work', '📑', 'Reports you are writing.');
            if ($can('mod.idems.edit')) $add($tnew('report'), '/document-new', 'My work', '➕', 'Start a new report.');
            $add($thp('endorsement'), '/endorsements', 'My work', '✅', 'Document endorsements.');
        }
        $add('My ' . $thp('voucher'), '/vouchers', 'My work', '🧾', 'Your field expenses.');
    }

    // ---- Operations screens (mirror of the Operations Home tiles) ----------
    if (!$isInsp && ($can('mod.calls.view') || $can('mod.jobs.view') || $can('mod.vouchers.view') || $can('mod.hiring.view') || $can('mod.reconcile.view'))) {
        $A = 'Operations';
        $add('Operations', '/operations', $A, '🛠️', 'The operations landing.');
        if ($can('mod.calls.view')) $add($thp('call'), '/calls', $A, '📋', 'Work orders from the client.');
        if ($can('mod.calls.edit') || $can('mod.calls.view')) $add($tnew('call'), '/call-new', $A, '➕', 'Raise a new work order.', 'action');
        if ($can('mod.jobs.view'))  $add($thp('job'), '/jobs', $A, '🗂️', 'Allocation, execution & closure.');
        if ($fx('sched_board_can') && sched_board_can()) $add('Scheduling board', '/schedule', $A, '🗓️', 'Assign inspectors to dates — board, capacity and availability tabs.');
        if ($fx('tosrm_ops_desk_can') && tosrm_ops_desk_can()) {
            $add('Capacity outlook', '/capacity-outlook', $A, '📈', 'Who is free, who is stretched — a tab of the scheduling board.');
        }
        if ($can('mod.calls.view')) {
            $add('Recurring services', '/recurring', $A, '🔁', 'Contracts that raise work on a schedule.');
            $add('Contract exceptions', '/contract-overrides', $A, '🛑', 'Exhausted or expired contract overrides.');
        }
        if (($fx('can_endorse_contract_open') && can_endorse_contract_open()) || ($fx('can_approve_contract_open') && can_approve_contract_open()))
            $add('Contract openings', '/contract-openings', $A, '✍', 'Endorse / approve won orders so they open to operations.');
        if ($fx('can_manage_availability') && can_manage_availability()) $add(($fx('TH') ? TH('engineer') : 'Engineer') . ' availability', '/availability', $A, '🟢', 'Daily availability board — a tab of the scheduling board.');
        if ($can('mod.reconcile.view')) $add('Attendance reconciliation', '/attendance-recon', $A, '✅', 'Entry / exit vs billed.');
        if ($fx('timesheet_can') && timesheet_can()) $add('Timesheet', '/timesheet', $A, '⏱️', 'Hours logged against jobs.');
        if ($can('mod.vouchers.view')) $add($thp('voucher'), '/vouchers', $A, '🧾', 'Field expenses raised against a job.');
        if ($fx('rating_can') && rating_can()) $add('Inspector ratings', '/ratings', $A, '⭐', 'Post-job quality & conduct.');
        if ($can('mod.hiring.view')) {
            $add('Recruitment', '/recruitment-cc', $A, '🧭', 'Command centre — pipeline & requirements.');
            $add('Requirements', '/requisitions', $A, '📌', 'Manpower requirements / requisitions.');
            $add('Candidates', '/candidates', $A, '👤', 'Candidate pipeline.');
            $add('New requirement', '/requisition-new', $A, '➕', 'Raise a new requirement.', 'action');
            $add('Add candidate', '/candidate-new', $A, '➕', 'Add a candidate.', 'action');
        }
        if ($fx('pc_can') && pc_can())
            $add('Project costing', '/project-costings', $A, '🧮', 'Team cost build-ups → rates & margin.');
        // TPIA service lines from the Service Scope Engine — only those switched
        // on for this install, same rule the Operations Home uses.
        if ($fx('svc_catalog')) {
            try { foreach ((array)svc_catalog() as $s) {
                if ($fx('svc_globally_active') && !svc_globally_active($s['code'] ?? '')) continue;
                $add($s['name'] ?? '', $s['route'] ?? '', $A, ($s['icon'] ?? '') ?: '🧩', $s['description'] ?? '');
            } } catch (Throwable $e) {}
        }
    }

    // ---- The seven flat areas, flattened from their gated tile lists --------
    if ($fx('ops_area_def')) {
        foreach (['sales', 'quality', 'reporting', 'money', 'insights', 'directory', 'admin'] as $area) {
            $def = ops_area_def($area);
            if (!$def) continue;
            $add($def['title'], '/' . $area, $def['title'], $def['icon'] ?: '📂', $def['sub'] ?? '');
            foreach ($def['sections'] as $sec) {
                foreach ($sec['tiles'] as $tile) {
                    if (!empty($tile['ext'])) continue;   // external links belong in their own tab, not a jump list
                    $add($tile['label'], $tile['route'], $def['title'], $tile['icon'] ?: '•', $tile['desc'] ?? '');
                }
            }
        }
    }

    return $cache = $out;
}
