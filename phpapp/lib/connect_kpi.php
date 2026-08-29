<?php
// ============================================================================
//  CONNECT — Reusable KPI Board  (one engine, one renderer, no duplicate code)
//
//  The SAME board powers the ops "concern" dashboard and the client portal
//  dashboard — the only difference is the AUDIENCE + scope. Every metric REUSES
//  an existing EXAACT engine (no re-implementation):
//    - inspections → jobs (via calls.client_id)
//    - revenue     → financial_rollup(['partner_id'=>…]) / books_outstanding()
//    - concerns    → complaints (partner_id) + cx_disputes_open_count()
//    - reports     → report_docs (client_id, pending statuses)
//    - ratings     → cx_ratings / rating_all()
//
//  Every call is guarded (function_exists + try/catch) so the board can never
//  break a dashboard when an engine is absent (a lean tenant, a test DB, etc.).
//
//  ADDITIVE & READ-ONLY: no new table, no new permission, no new status.
// ============================================================================

/** Safe scalar count — 0 on any error/missing table. */
function connect_kpi_val($sql, $args = []) {
    try { return (int)ops_val($sql, $args); } catch (Throwable $e) { return 0; }
}

/** Short ₹ formatter (Cr / L / plain). */
function connect_kpi_inr($n) {
    $n = (float)$n;
    if ($n >= 10000000) return '₹' . rtrim(rtrim(number_format($n / 10000000, 2), '0'), '.') . ' Cr';
    if ($n >= 100000)   return '₹' . rtrim(rtrim(number_format($n / 100000, 1), '0'), '.') . ' L';
    return '₹' . number_format((int)round($n));
}

/**
 * Build the KPI board for an audience.
 *   $scope = ['audience' => 'client'|'staff'|'pro', 'party_id' => int]
 * For the 'pro' audience party_id is the cx_professionals.id (the freelancer).
 * Returns ['audience', 'tiles'[], 'actions'[], 'raw'{}] where each tile is
 * ['key','icon','label','value','sub','tone','url'] and each action is
 * ['label','n','url','tone','value'?].
 */
function connect_kpi_board($scope = []) {
    // The freelancer sees THEIR OWN cockpit — same engine, same renderer, but the
    // figures come from the professional-side engines (engagements, applications,
    // ratings, verification) rather than the org registers. Delegated so the
    // client/staff path below is untouched.
    if (($scope['audience'] ?? '') === 'pro') return connect_kpi_board_pro((int)($scope['party_id'] ?? 0));

    $isClient = (($scope['audience'] ?? 'staff') === 'client');
    $pid = (int)($scope['party_id'] ?? 0);

    // --- Inspections (jobs) — jobs link to a client only via their call. ------
    if ($isClient) {
        $inspDone = connect_kpi_val("SELECT COUNT(*) FROM jobs j JOIN calls c ON c.id=j.call_id WHERE c.client_id=? AND j.closed_flag=1", [$pid]);
        $inspOpen = connect_kpi_val("SELECT COUNT(*) FROM jobs j JOIN calls c ON c.id=j.call_id WHERE c.client_id=? AND COALESCE(j.closed_flag,0)=0", [$pid]);
    } else {
        $inspDone = connect_kpi_val("SELECT COUNT(*) FROM jobs WHERE closed_flag=1");
        $inspOpen = connect_kpi_val("SELECT COUNT(*) FROM jobs WHERE COALESCE(closed_flag,0)=0");
    }

    // --- Revenue — reuse the financial rollup (or books_outstanding). ---------
    $billed = 0.0; $received = 0.0; $outstanding = 0.0;
    if (function_exists('financial_rollup')) {
        try {
            $r = financial_rollup($isClient ? ['partner_id' => $pid] : []);
            $billed      = (float)($r['billed'] ?? $r['net_billed'] ?? 0);
            $received    = (float)($r['received'] ?? 0);
            $outstanding = (float)($r['outstanding'] ?? 0);
        } catch (Throwable $e) {}
    } elseif ($isClient && function_exists('books_outstanding')) {
        try { $b = books_outstanding($pid); $billed = (float)($b['billed'] ?? 0); $outstanding = (float)($b['outstanding'] ?? 0); } catch (Throwable $e) {}
    }

    // --- Open concerns — complaints (+ marketplace disputes for staff). -------
    if ($isClient) {
        $concerns = connect_kpi_val("SELECT COUNT(*) FROM complaints WHERE status='OPEN' AND partner_id=?", [$pid]);
    } else {
        $concerns = connect_kpi_val("SELECT COUNT(*) FROM complaints WHERE status='OPEN'");
        if (function_exists('cx_disputes_open_count')) { try { $concerns += (int)cx_disputes_open_count(); } catch (Throwable $e) {} }
    }

    // --- Reports pending — not yet issued. ------------------------------------
    $pend = "('DRAFT','SUBMITTED','UNDER_REVIEW','REJECTED')";
    if ($isClient) {
        $reportsPending = connect_kpi_val("SELECT COUNT(*) FROM report_docs WHERE COALESCE(deleted,0)=0 AND client_id=? AND status IN $pend", [$pid]);
    } else {
        $reportsPending = connect_kpi_val("SELECT COUNT(*) FROM report_docs WHERE COALESCE(deleted,0)=0 AND status IN $pend");
    }

    // --- Ratings — marketplace two-way ratings, best-effort. ------------------
    $ratingAvg = null; $ratingN = 0;
    try {
        $row = $isClient
            ? ops_one("SELECT COUNT(*) n, AVG(stars) a FROM cx_ratings WHERE stars>0 AND (rater_party_id=? OR ratee_party_id=?)", [$pid, $pid])
            : ops_one("SELECT COUNT(*) n, AVG(stars) a FROM cx_ratings WHERE stars>0");
        if ($row && (int)$row['n'] > 0) { $ratingN = (int)$row['n']; $ratingAvg = round((float)$row['a'], 1); }
    } catch (Throwable $e) {}
    if (!$isClient && $ratingAvg === null && function_exists('rating_all')) {   // fall back to internal inspector ratings
        try {
            $s = 0; $c = 0;
            foreach (rating_all() as $r) if (($r['stars'] ?? null) !== null) { $s += (float)$r['stars']; $c++; }
            if ($c > 0) { $ratingAvg = round($s / $c, 1); $ratingN = $c; }
        } catch (Throwable $e) {}
    }

    $inr = 'connect_kpi_inr';
    $tiles = [
        ['key' => 'inspections', 'icon' => '🔎', 'label' => 'Inspections', 'value' => (string)$inspDone,
         'sub' => $inspOpen . ' in progress', 'tone' => '', 'url' => $isClient ? '/portal/calls' : '/jobs'],
        ['key' => 'revenue', 'icon' => '💰', 'label' => $isClient ? 'Billed to you' : 'Revenue billed', 'value' => $inr($billed),
         'sub' => $outstanding > 0 ? $inr($outstanding) . ' outstanding' : ($received > 0 ? $inr($received) . ' received' : '—'),
         'tone' => $outstanding > 0 ? 'warn' : 'ok', 'url' => $isClient ? '/portal/invoices' : '/billable-events'],
        ['key' => 'concerns', 'icon' => '⚠️', 'label' => 'Open concerns', 'value' => (string)$concerns,
         'sub' => $concerns > 0 ? 'need attention' : 'all clear', 'tone' => $concerns > 0 ? 'bad' : 'ok',
         'url' => $isClient ? '/portal/requests' : '/complaints'],
        ['key' => 'ratings', 'icon' => '⭐', 'label' => 'Ratings', 'value' => $ratingAvg !== null ? number_format($ratingAvg, 1) : '—',
         'sub' => $ratingN > 0 ? ($ratingN . ' rated') : 'no ratings yet', 'tone' => ($ratingAvg !== null && $ratingAvg >= 4) ? 'ok' : '', 'url' => ''],
        ['key' => 'reports', 'icon' => '📄', 'label' => 'Reports pending', 'value' => (string)$reportsPending,
         'sub' => $reportsPending > 0 ? 'awaiting issue' : 'up to date', 'tone' => $reportsPending > 0 ? 'warn' : 'ok',
         'url' => $isClient ? '/portal/reports' : '/documents'],
    ];

    // --- Appropriate actions — "what needs attention". ------------------------
    $actions = [];
    if ($reportsPending > 0) $actions[] = ['label' => 'Reports pending', 'n' => $reportsPending, 'url' => $isClient ? '/portal/reports' : '/documents', 'tone' => 'warn'];
    if ($concerns > 0)       $actions[] = ['label' => 'Open concerns', 'n' => $concerns, 'url' => $isClient ? '/portal/requests' : '/complaints', 'tone' => 'bad'];
    if ($outstanding > 0)    $actions[] = ['label' => $isClient ? 'Invoices outstanding' : 'Receivables outstanding', 'n' => 0, 'value' => $inr($outstanding), 'url' => $isClient ? '/portal/invoices' : '/billable-events', 'tone' => 'warn'];
    if ($inspOpen > 0)       $actions[] = ['label' => 'Inspections in progress', 'n' => $inspOpen, 'url' => $isClient ? '/portal/calls' : '/jobs', 'tone' => ''];

    return [
        'audience' => $isClient ? 'client' : 'staff',
        'tiles'    => $tiles,
        'actions'  => $actions,
        'raw'      => [
            'inspections_done' => $inspDone, 'inspections_open' => $inspOpen,
            'billed' => $billed, 'received' => $received, 'outstanding' => $outstanding,
            'concerns' => $concerns, 'reports_pending' => $reportsPending,
            'rating_avg' => $ratingAvg, 'rating_n' => $ratingN,
        ],
    ];
}

/**
 * The freelancer's own KPI board (audience 'pro'). Every figure REUSES a
 * professional-side engine that already exists — no new query surface, no new
 * table, and nothing here can claim a payout (earnings/escrow is a separate,
 * gated slice). "Booked value" is the contracted value of the person's own
 * bookings (man-days/months × agreed rate), not money paid.
 */
function connect_kpi_board_pro($proId) {
    $proId = (int)$proId;

    // --- Assignments (engagements) — reuse the booking summary. ---------------
    $eng = ['booked' => 0, 'active' => 0, 'completed' => 0, 'total' => 0];
    if (function_exists('connect_engage_summary_pro')) {
        try { $eng = array_merge($eng, (array)connect_engage_summary_pro($proId)); } catch (Throwable $e) {}
    }

    // --- Booked value — sum of the deterministic per-engagement totals. --------
    // Only man-days / man-months yield a defensible quantity×rate figure; the
    // describer returns null for open-ended bases, so those add nothing.
    $bookedValue = 0.0;
    if (function_exists('connect_engage_for_professional') && function_exists('connect_engage_describe')) {
        try {
            foreach (connect_engage_for_professional($proId) as $e) {
                if (strtoupper((string)($e['status'] ?? '')) === 'CANCELLED') continue;
                $d = connect_engage_describe($e);
                if (($d['total'] ?? null) !== null) $bookedValue += (float)$d['total'];
            }
        } catch (Throwable $e) {}
    }

    // --- Applications — the person's own pipeline (reuse cx_applications). -----
    $appsLive = 0; $appsShort = 0; $appsOffered = 0;
    try {
        $rows = ops_all("SELECT status, COUNT(*) n FROM cx_applications WHERE applicant_professional_id=? GROUP BY status", [$proId]) ?: [];
        foreach ($rows as $r) {
            $s = strtoupper((string)$r['status']); $n = (int)$r['n'];
            if ($s === 'SHORTLISTED') $appsShort += $n;
            if ($s === 'OFFERED')     $appsOffered += $n;
            if (in_array($s, ['APPLIED', 'SHORTLISTED', 'OFFERED'], true)) $appsLive += $n;
        }
    } catch (Throwable $e) {}

    // --- Ratings — client-to-pro ratings on this freelancer's own awards. ------
    $ratingAvg = null; $ratingN = 0;
    try {
        $row = ops_one("SELECT COUNT(*) n, AVG(r.stars) a FROM cx_ratings r
                        JOIN cx_applications a ON a.id=r.application_id
                        WHERE r.direction='CLIENT_TO_PRO' AND r.stars>0 AND a.applicant_professional_id=?", [$proId]);
        if ($row && (int)$row['n'] > 0) { $ratingN = (int)$row['n']; $ratingAvg = round((float)$row['a'], 1); }
    } catch (Throwable $e) {}

    // --- Verification / trust — reuse the tier + trust engines. ----------------
    $tier = function_exists('connect_verify_tier_for_professional') ? connect_verify_tier_for_professional($proId) : 'registered';
    $tierLbl = function_exists('connect_verify_tier_label') ? connect_verify_tier_label($tier) : ucfirst((string)$tier);
    $tierRank = function_exists('connect_verify_tier_rank') ? (int)connect_verify_tier_rank($tier) : 0;
    $trustScore = null;
    if (function_exists('connect_trust_score_pro')) {
        try { $t = connect_trust_score_pro($proId); if (is_array($t) && isset($t['score'])) $trustScore = (int)$t['score']; } catch (Throwable $e) {}
    }

    // --- Unread from the hiring desk — surfaces as an action, not a tile. ------
    $unread = 0;
    if (function_exists('connect_msg_pro_unread')) { try { $unread = (int)connect_msg_pro_unread($proId); } catch (Throwable $e) {} }

    $inr = 'connect_kpi_inr';
    $tiles = [
        ['key' => 'assignments', 'icon' => '📋', 'label' => 'Assignments', 'value' => (string)(int)$eng['completed'],
         'sub' => ((int)$eng['active'] + (int)$eng['booked']) . ' upcoming/active', 'tone' => '', 'url' => '/pro/bookings'],
        ['key' => 'value', 'icon' => '💼', 'label' => 'Booked value', 'value' => $inr($bookedValue),
         'sub' => (int)$eng['total'] > 0 ? 'across ' . (int)$eng['total'] . ' booking' . ((int)$eng['total'] === 1 ? '' : 's') : 'no bookings yet',
         'tone' => $bookedValue > 0 ? 'ok' : '', 'url' => '/pro/bookings'],
        ['key' => 'applications', 'icon' => '📨', 'label' => 'Applications', 'value' => (string)$appsLive,
         'sub' => $appsShort . ' shortlisted · ' . $appsOffered . ' offered', 'tone' => $appsOffered > 0 ? 'warn' : '', 'url' => '/pro/applications'],
        ['key' => 'ratings', 'icon' => '⭐', 'label' => 'Ratings', 'value' => $ratingAvg !== null ? number_format($ratingAvg, 1) : '—',
         'sub' => $ratingN > 0 ? ($ratingN . ' rated') : 'no ratings yet', 'tone' => ($ratingAvg !== null && $ratingAvg >= 4) ? 'ok' : '', 'url' => ''],
        ['key' => 'verification', 'icon' => '🛡️', 'label' => 'Verification', 'value' => $tierRank >= 3 ? '✓✓' : ($tierRank >= 1 ? '✓' : '—'),
         'sub' => $tierLbl . ($trustScore !== null ? ' · trust ' . $trustScore : ''), 'tone' => $tierRank >= 1 ? 'ok' : 'warn', 'url' => '/pro/verify'],
    ];

    // --- What needs the freelancer's attention. -------------------------------
    $actions = [];
    if ($appsOffered > 0) $actions[] = ['label' => 'Offers to respond', 'n' => $appsOffered, 'url' => '/pro/applications', 'tone' => 'warn'];
    if ($unread > 0)      $actions[] = ['label' => 'Unread messages', 'n' => $unread, 'url' => '/pro/messages', 'tone' => 'bad'];
    if ((int)$eng['active'] > 0) $actions[] = ['label' => 'Active assignments', 'n' => (int)$eng['active'], 'url' => '/pro/bookings', 'tone' => ''];
    if ($tierRank < 1)    $actions[] = ['label' => 'Get verified', 'n' => 0, 'url' => '/pro/verify', 'tone' => 'warn'];

    return [
        'audience' => 'pro',
        'tiles'    => $tiles,
        'actions'  => $actions,
        'raw'      => [
            'assignments_done' => (int)$eng['completed'], 'assignments_active' => (int)$eng['active'],
            'assignments_booked' => (int)$eng['booked'], 'assignments_total' => (int)$eng['total'],
            'booked_value' => $bookedValue,
            'apps_live' => $appsLive, 'apps_shortlisted' => $appsShort, 'apps_offered' => $appsOffered,
            'rating_avg' => $ratingAvg, 'rating_n' => $ratingN,
            'tier' => $tier, 'tier_rank' => $tierRank, 'trust_score' => $trustScore, 'unread' => $unread,
        ],
    ];
}

/**
 * Render the board using the app's ONE universal KPI-card design.
 *
 * The markup carries the design-system class names — `.kpi-row`, `.kpi`
 * (`.kic`/`.k`/`.v`/`.d`), the `.tone-*` semantic rail, and `.pill` badges —
 * so on any screen that loads `assets/css/app.css` (the ops dashboards) the
 * board IS the universal component: no second definition, and any future
 * tweak to `.kpi` in the design system flows straight through.
 *
 * The client and freelancer portals are self-contained (their own tokens, no
 * app.css), so a single zero-specificity `:where()` fallback reproduces the
 * SAME card spec from each portal's own variables (with design-system hex
 * fallbacks). Because `:where()` has zero specificity, the real component
 * always wins wherever app.css is present, and the fallback only fills in
 * where it is absent — one look everywhere, no override, no drift.
 */
function connect_kpi_render($board) {
    if (!is_array($board) || empty($board['tiles'])) return;
    // board tone → design-system tone/pill suffix.
    $tone = ['ok' => 'ok', 'warn' => 'warn', 'bad' => 'bad', 'info' => 'info', '' => ''];

    static $styled = false;
    if (!$styled) {
        $styled = true;
        // Fallback ONLY — every selector is wrapped in :where() (specificity 0)
        // so app.css's real .kpi/.pill win on the ops dashboards, while the
        // self-contained portals still get the identical universal card.
        echo '<style>'
           . ':where(.kpi-row){display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin:12px 0}'
           . ':where(.kpi){position:relative;background:var(--card,#fff);border:1px solid var(--line,#e5e7eb);border-radius:var(--radius,14px);padding:16px 18px;box-shadow:var(--shadow-sm,0 1px 2px rgba(18,32,60,.06));overflow:hidden}'
           . ':where(a.kpi){text-decoration:none;color:var(--ink,#12201f);transition:transform .12s,box-shadow .16s}'
           . ':where(a.kpi:hover){transform:translateY(-2px);box-shadow:var(--shadow,0 10px 30px rgba(18,32,60,.12))}'
           . ':where(.kpi .kic){position:absolute;right:16px;top:14px;font-size:17px;opacity:.55;line-height:1}'
           . ':where(.kpi .k){display:block;font-size:11.5px;font-weight:700;color:var(--muted,#5b6b6a);text-transform:uppercase;letter-spacing:.4px;line-height:1.35;padding-right:26px}'
           . ':where(.kpi .v){display:block;font-size:26px;font-weight:800;letter-spacing:-.5px;line-height:1.1;margin:6px 0 3px;color:var(--ink,#12201f);font-variant-numeric:tabular-nums}'
           . ':where(.kpi .d){display:block;font-size:12.5px;font-weight:600;color:var(--muted,#5b6b6a);line-height:1.35}'
           . ':where(.kpi.tone-ok,.kpi.tone-warn,.kpi.tone-bad,.kpi.tone-info){padding-left:21px}'
           . ':where(.kpi.tone-ok,.kpi.tone-warn,.kpi.tone-bad,.kpi.tone-info)::before{content:"";position:absolute;left:0;top:0;bottom:0;width:3px}'
           . ':where(.kpi.tone-ok)::before{background:var(--ok,#15803d)}:where(.kpi.tone-ok .v){color:var(--ok,#15803d)}'
           . ':where(.kpi.tone-warn)::before{background:var(--warn,#b45309)}:where(.kpi.tone-warn .v){color:var(--warn,#b45309)}'
           . ':where(.kpi.tone-bad)::before{background:var(--bad,#b91c1c)}:where(.kpi.tone-bad .v){color:var(--bad,#b91c1c)}'
           . ':where(.kpi.tone-info)::before{background:var(--info,#0369a1)}:where(.kpi.tone-info .v){color:var(--info,#0369a1)}'
           . ':where(.pill){display:inline-block;padding:3px 9px;border-radius:20px;font-size:11.5px;font-weight:700;white-space:nowrap}'
           . ':where(.pill.p-ok){background:color-mix(in srgb,var(--ok,#15803d) 14%,transparent);color:var(--ok,#15803d)}'
           . ':where(.pill.p-warn){background:color-mix(in srgb,var(--warn,#b45309) 14%,transparent);color:var(--warn,#b45309)}'
           . ':where(.pill.p-bad){background:color-mix(in srgb,var(--bad,#b91c1c) 14%,transparent);color:var(--bad,#b91c1c)}'
           . ':where(.pill.p-mut){background:var(--soft,#f1f5f9);color:var(--muted,#5b6b6a)}'
           . ':where(.kpi-acts){display:flex;flex-wrap:wrap;gap:8px;margin:0 0 12px}'
           . ':where(.kpi-act){display:inline-flex;align-items:center;gap:7px;padding:6px 12px;border-radius:999px;border:1px solid var(--line,#e5e7eb);background:var(--card,#fff);text-decoration:none;color:var(--ink,#12201f);font-size:12.5px;font-weight:600}'
           . ':where(a.kpi-act:hover){border-color:var(--muted,#5b6b6a)}'
           . '</style>';
    }

    echo '<div class="kpi-row">';
    foreach ($board['tiles'] as $t) {
        $tc = $tone[$t['tone'] ?? ''] ?? '';
        $cls = 'kpi' . ($tc ? ' tone-' . $tc : '');
        $url = (string)($t['url'] ?? '');
        $open  = $url !== '' ? '<a class="' . $cls . '" href="' . e($url) . '">' : '<div class="' . $cls . '">';
        $close = $url !== '' ? '</a>' : '</div>';
        echo $open
           . '<span class="kic">' . e($t['icon']) . '</span>'
           . '<span class="k">' . e($t['label']) . '</span>'
           . '<span class="v">' . e($t['value']) . '</span>'
           . '<span class="d">' . e($t['sub']) . '</span>'
           . $close;
    }
    echo '</div>';

    if (!empty($board['actions'])) {
        echo '<div class="kpi-acts">';
        foreach ($board['actions'] as $a) {
            $tc = $tone[$a['tone'] ?? ''] ?? '';
            $pill = 'pill p-' . ($tc ?: 'mut');
            $badge = !empty($a['value']) ? e($a['value']) : (string)(int)($a['n'] ?? 0);
            echo '<a class="kpi-act" href="' . e($a['url'] ?? '#') . '">'
               . '<span class="' . $pill . '">' . $badge . '</span>'
               . e($a['label']) . '</a>';
        }
        echo '</div>';
    }
}
