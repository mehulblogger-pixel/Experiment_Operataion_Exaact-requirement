<?php
// ============================================================================
//  CONNECT — Labour-market Analytics  (slice K19 / backlog #8, additive & READ-ONLY)
//
//  The data a talent network monetises: supply vs demand, the fill funnel,
//  time-to-award, rate benchmarks, pool growth and the verification mix — computed
//  live over the marketplace's own cx_* tables (requirements, applications,
//  professionals, bench, verifications). No new table, no new permission, no new
//  status: every function is a pure aggregation, recomputed on read.
//
//  Reuses the existing analytics posture (TAPI/Advisor): honest counts, buckets
//  that drop out when empty, and a plain-language read for the desk. Later, the
//  same layer feeds an anonymised market-intelligence product (blueprint M17).
// ============================================================================

/** Small counter over a table with an optional WHERE. */
function cx_an_count($table, $where = '', $args = []) {
    try { return (int)ops_val("SELECT COUNT(*) FROM $table" . ($where ? " WHERE $where" : ''), $args); }
    catch (Throwable $e) { return 0; }
}

/** Headline KPIs for the marketplace. */
function connect_analytics_headline() {
    $posted = cx_an_count('cx_requirements', "status<>'DRAFT'");
    $awarded = cx_an_count('cx_requirements', "awarded_application_id>0");
    $pros = cx_an_count('cx_professionals', "is_active=1");
    $bench = cx_an_count('cx_bench', "COALESCE(is_active,1)=1");
    return [
        'requirements' => cx_an_count('cx_requirements'),
        'posted'       => $posted,
        'open'         => cx_an_count('cx_requirements', "status IN ('OPEN','SHORTLISTING')"),
        'awarded'      => $awarded,
        'applications' => cx_an_count('cx_applications'),
        'fill_rate'    => $posted > 0 ? (int)round($awarded / $posted * 100) : 0,
        'pool'         => $pros,
        'bench'        => $bench,
        'supply'       => $pros + $bench,
        'avg_days_to_award' => connect_analytics_avg_days_to_award(),
    ];
}

/** Average days from posting to award (over requirements that were awarded). */
function connect_analytics_avg_days_to_award() {
    try {
        $rows = ops_all("SELECT posted_at, closed_at, updated_at FROM cx_requirements WHERE awarded_application_id>0") ?: [];
    } catch (Throwable $e) { return 0; }
    $days = [];
    foreach ($rows as $r) {
        $start = strtotime((string)($r['posted_at'] ?? '')); if (!$start) continue;
        $end = strtotime((string)($r['closed_at'] ?? '')) ?: strtotime((string)($r['updated_at'] ?? ''));
        if (!$end || $end < $start) continue;
        $days[] = ($end - $start) / 86400;
    }
    return $days ? round(array_sum($days) / count($days), 1) : 0;
}

/**
 * Supply vs demand by discipline. Demand = open requirements in that discipline;
 * supply = available self-registered professionals + available agency-bench people
 * whose discipline/skills match. A negative gap means demand outstrips supply.
 */
function connect_analytics_demand_supply() {
    $disc = [];
    try { $disc = ops_all("SELECT code, name FROM cx_disciplines ORDER BY sort_order, id") ?: []; }
    catch (Throwable $e) {}
    $out = [];
    foreach ($disc as $d) {
        $code = (string)$d['code']; if ($code === '') continue;
        $demand = cx_an_count('cx_requirements', "status IN ('OPEN','SHORTLISTING') AND discipline_code=?", [$code]);
        $supPro = cx_an_count('cx_professionals', "is_active=1 AND availability='AVAILABLE' AND (disciplines LIKE ? OR skills LIKE ?)",
            ['%' . $code . '%', '%' . $code . '%']);
        $supBench = cx_an_count('cx_bench', "COALESCE(is_active,1)=1 AND availability='AVAILABLE' AND (discipline_code=? OR skills LIKE ?)",
            [$code, '%' . $code . '%']);
        $supply = $supPro + $supBench;
        if ($demand === 0 && $supply === 0) continue;   // drop empties — no noise
        $out[] = ['code' => $code, 'name' => (string)$d['name'], 'demand' => $demand,
                  'supply' => $supply, 'pro' => $supPro, 'bench' => $supBench, 'gap' => $supply - $demand];
    }
    // Tightest markets first (most demand relative to supply).
    usort($out, fn($a, $b) => ($a['gap'] <=> $b['gap']) ?: ($b['demand'] <=> $a['demand']));
    return $out;
}

/** The hiring funnel: posted → had applications → shortlisted → awarded → closed. */
function connect_analytics_funnel() {
    $posted = cx_an_count('cx_requirements', "status<>'DRAFT'");
    $withApps = 0;
    try { $withApps = (int)ops_val("SELECT COUNT(DISTINCT requirement_id) FROM cx_applications"); } catch (Throwable $e) {}
    $shortlisted = 0;
    try { $shortlisted = (int)ops_val("SELECT COUNT(DISTINCT requirement_id) FROM cx_applications WHERE status IN ('SHORTLISTED','OFFERED','ACCEPTED')"); } catch (Throwable $e) {}
    $awarded = cx_an_count('cx_requirements', "awarded_application_id>0");
    $closed = cx_an_count('cx_requirements', "status='CLOSED'");
    return [
        ['key' => 'posted',      'label' => 'Posted',            'value' => $posted],
        ['key' => 'applied',     'label' => 'Received applicants', 'value' => min($withApps, $posted)],
        ['key' => 'shortlisted', 'label' => 'Shortlisted',       'value' => $shortlisted],
        ['key' => 'awarded',     'label' => 'Awarded',           'value' => $awarded],
        ['key' => 'closed',      'label' => 'Closed',            'value' => $closed],
    ];
}

/** Rate benchmarks by discipline — the requirement ask band + the pool day-rate. */
function connect_analytics_rates() {
    $disc = [];
    try { $disc = ops_all("SELECT code, name FROM cx_disciplines ORDER BY sort_order, id") ?: []; }
    catch (Throwable $e) {}
    $out = [];
    foreach ($disc as $d) {
        $code = (string)$d['code']; if ($code === '') continue;
        try {
            $req = ops_one("SELECT AVG(NULLIF(rate_min,0)) mn, AVG(NULLIF(rate_max,0)) mx, COUNT(*) n
                            FROM cx_requirements WHERE discipline_code=? AND (rate_min>0 OR rate_max>0)", [$code]);
            $pro = ops_one("SELECT AVG(NULLIF(day_rate_min,0)) avg, MIN(NULLIF(day_rate_min,0)) mn, MAX(day_rate_min) mx, COUNT(*) n
                            FROM cx_professionals WHERE is_active=1 AND day_rate_min>0 AND (disciplines LIKE ? OR skills LIKE ?)",
                            ['%' . $code . '%', '%' . $code . '%']);
        } catch (Throwable $e) { $req = null; $pro = null; }
        $reqN = (int)($req['n'] ?? 0); $proN = (int)($pro['n'] ?? 0);
        if ($reqN === 0 && $proN === 0) continue;
        $out[] = [
            'code' => $code, 'name' => (string)$d['name'],
            'ask_min' => (int)round((float)($req['mn'] ?? 0)), 'ask_max' => (int)round((float)($req['mx'] ?? 0)), 'ask_n' => $reqN,
            'pool_avg' => (int)round((float)($pro['avg'] ?? 0)), 'pool_min' => (int)round((float)($pro['mn'] ?? 0)),
            'pool_max' => (int)round((float)($pro['mx'] ?? 0)), 'pool_n' => $proN,
        ];
    }
    return $out;
}

/** Pool growth — new self-registered professionals per month (last 6 months). */
function connect_analytics_growth($months = 6) {
    $out = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $ym = date('Y-m', strtotime("first day of -$i month"));
        $n = cx_an_count('cx_professionals', "substr(created_at,1,7)=?", [$ym]);
        $out[] = ['month' => $ym, 'label' => date('M', strtotime($ym . '-01')), 'value' => $n];
    }
    return $out;
}

/** Verification mix across the active pool (ties in #3). */
function connect_analytics_verif_mix() {
    $tiers = function_exists('connect_verify_tiers') ? connect_verify_tiers()
        : [['key' => 'registered', 'label' => 'Registered']];
    $out = [];
    foreach ($tiers as $t) {
        $out[] = ['key' => $t['key'], 'label' => $t['label'],
                  'value' => cx_an_count('cx_professionals', "is_active=1 AND COALESCE(verification_tier,'registered')=?", [$t['key']])];
    }
    return $out;
}

/** Demand by location — where the work is (top N). */
function connect_analytics_locations($limit = 8) {
    try {
        $rows = ops_all("SELECT location, COUNT(*) n FROM cx_requirements
                         WHERE status<>'DRAFT' AND COALESCE(location,'')<>''
                         GROUP BY location ORDER BY n DESC LIMIT ?", [(int)$limit]) ?: [];
    } catch (Throwable $e) { return []; }
    return array_map(fn($r) => ['location' => (string)$r['location'], 'value' => (int)$r['n']], $rows);
}

/** One plain-language insight for the desk — the tightest market, if any. */
function connect_analytics_insight() {
    $ds = connect_analytics_demand_supply();
    foreach ($ds as $row) if ($row['demand'] > 0 && $row['gap'] < 0)
        return "Demand outstrips supply in " . $row['name'] . " — " . $row['demand']
             . " open vs " . $row['supply'] . " available. Recruit or verify more " . strtolower($row['name']) . ".";
    foreach ($ds as $row) if ($row['demand'] > 0)
        return $row['name'] . " has the most live demand (" . $row['demand'] . " open).";
    return "No open demand yet — post requirements to start the market.";
}

/** Read gate — a desk/manager view; reuses existing levels, no new permission. */
function connect_analytics_can() {
    if (function_exists('connect_enabled') && !connect_enabled()) return false;
    if (function_exists('is_master') && is_master()) return true;
    if (function_exists('is_coordinator_level') && is_coordinator_level()) return true;
    return false;
}

/** Read-only screen: the labour-market analytics dashboard. */
function ops_connect_analytics($method) {
    ops_require(connect_analytics_can(),
        'Marketplace analytics are available to coordinators, managers and admins.');
    view('ops/connect_analytics', [
        'head'    => connect_analytics_headline(),
        'demand'  => connect_analytics_demand_supply(),
        'funnel'  => connect_analytics_funnel(),
        'rates'   => connect_analytics_rates(),
        'growth'  => connect_analytics_growth(),
        'verif'   => connect_analytics_verif_mix(),
        'locs'    => connect_analytics_locations(),
        'insight' => connect_analytics_insight(),
    ]);
    return true;
}
