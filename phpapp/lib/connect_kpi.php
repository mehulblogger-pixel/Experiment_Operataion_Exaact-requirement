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
 *   $scope = ['audience' => 'client'|'staff', 'party_id' => int]
 * Returns ['audience', 'tiles'[], 'actions'[], 'raw'{}] where each tile is
 * ['key','icon','label','value','sub','tone','url'] and each action is
 * ['label','n','url','tone','value'?].
 */
function connect_kpi_board($scope = []) {
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

/** Render the board (self-styled so it works in ops AND portal contexts). */
function connect_kpi_render($board) {
    if (!is_array($board) || empty($board['tiles'])) return;
    $tones = ['ok' => '#0f7d5a', 'warn' => '#8a6d12', 'bad' => '#b91c1c', '' => 'inherit'];
    static $styled = false;
    if (!$styled) {
        $styled = true;
        echo '<style>
        .kpiq-row{display:flex;flex-wrap:wrap;gap:10px;margin:10px 0}
        .kpiq{flex:1 1 150px;min-width:140px;background:var(--card,#fff);border:1px solid var(--line,#e5e7eb);border-radius:13px;padding:13px 15px}
        .kpiq .kic{font-size:16px}
        .kpiq .lab{font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:var(--muted,#777);margin-top:2px}
        .kpiq .val{font-size:24px;font-weight:800;letter-spacing:-.01em;line-height:1.1}
        .kpiq .val a{text-decoration:none;color:inherit}
        .kpiq .sub{font-size:12px;color:var(--muted,#888);margin-top:1px}
        .kpiq-actions{display:flex;flex-wrap:wrap;gap:8px;margin:4px 0 6px}
        .kpiq-act{display:inline-flex;align-items:center;gap:7px;padding:7px 12px;border-radius:999px;border:1px solid var(--line,#e5e7eb);background:var(--card,#fff);text-decoration:none;color:inherit;font-size:13px;font-weight:600}
        .kpiq-act .b{display:inline-block;min-width:18px;height:18px;line-height:18px;text-align:center;border-radius:999px;font-size:11px;font-weight:800;padding:0 5px}
        </style>';
    }
    echo '<div class="kpiq-row">';
    foreach ($board['tiles'] as $t) {
        $col = $tones[$t['tone'] ?? ''] ?? 'inherit';
        $val = e($t['value']);
        if (!empty($t['url'])) $val = '<a href="' . e($t['url']) . '">' . $val . '</a>';
        echo '<div class="kpiq"><span class="kic">' . e($t['icon']) . '</span>'
           . '<div class="lab">' . e($t['label']) . '</div>'
           . '<div class="val" style="color:' . $col . '">' . $val . '</div>'
           . '<div class="sub">' . e($t['sub']) . '</div></div>';
    }
    echo '</div>';
    if (!empty($board['actions'])) {
        echo '<div class="kpiq-actions">';
        foreach ($board['actions'] as $a) {
            $col = $tones[$a['tone'] ?? ''] ?? '#516260';
            $badge = !empty($a['value']) ? e($a['value']) : (int)($a['n'] ?? 0);
            echo '<a class="kpiq-act" href="' . e($a['url'] ?? '#') . '">'
               . '<span class="b" style="background:' . $col . '22;color:' . $col . '">' . $badge . '</span>'
               . e($a['label']) . '</a>';
        }
        echo '</div>';
    }
}
