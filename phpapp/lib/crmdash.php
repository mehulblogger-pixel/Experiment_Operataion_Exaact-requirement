<?php
// ============================================================================
//  The CRM's own dashboard — the funnel, and what it says
//
//  Asked for as "its individual dashboard, pipelines, funnels". The pipelines
//  and funnels are lib/industry.php and lib/opportunities.php. This is the
//  screen that reads them.
//
//  It is SEPARATE from the main dashboard on purpose. The main dashboard
//  answers "what is happening in the business today" and is read by everyone.
//  This answers "how is selling going", and the questions are different enough
//  that mixing them makes both worse:
//
//      main dashboard          this
//      ─────────────────       ────────────────────────────
//      work due this week      what is in the funnel, by stage
//      people free tomorrow    where deals stop moving
//      invoices overdue        how many quotations become orders
//      reports outstanding     why we lose, and to whom
//
//  THE FUNNEL IS THE POINT, and it is worth being precise about what a funnel
//  chart honestly shows, because most of them lie.
//
//   * The bars are the deals SITTING in each stage right now. That is a
//     snapshot, not a flow, and it is the number people actually manage from.
//   * The CONVERSION between two stages is not "bar B ÷ bar A" — that compares
//     two snapshots and is meaningless when stages move at different speeds.
//     It is computed from the stage HISTORY: of the deals that ever reached
//     stage A, how many ever reached stage B. That is a real rate.
//   * A stage nobody has ever passed through shows "—", not 0%. Zero means
//     "everybody stopped here"; no data means nobody has been here yet, and
//     printing 0% for it invents a problem that does not exist.
//
//  Everything is scoped and licence-aware, like every other register.
// ============================================================================

function crmdash_can() {
    return can('mod.crm_reports.view') || can('mod.leads.view') || can('mod.quotes.view')
        || is_master_of(['crm_reports', 'leads', 'quotes']);
}

function crmdash_try($fn, $fb = null) { try { return $fn(); } catch (Throwable $e) { return $fb; } }
function crmdash_rows($sql, $a = []) { return crmdash_try(fn() => ops_all($sql, $a), []) ?: []; }
function crmdash_val($sql, $a = [], $fb = 0) { $v = crmdash_try(fn() => ops_val($sql, $a), $fb); return $v === false || $v === null ? $fb : $v; }

// ---- The funnel -------------------------------------------------------------
// Stage by stage: what is sitting there now, and — from the history — what
// share of everything that ever reached this stage went on to the next.
function crmdash_funnel($pipelineId = 0, $days = 365) {
    if (!function_exists('opp_migrate')) return null;
    opp_migrate();
    $pipe = $pipelineId ?: (int)(crmdash_try(fn() => pipeline_default('OPPORTUNITY'), null)['id'] ?? 0);
    if (!$pipe) return null;
    $stages = crmdash_try(fn() => pipeline_stages($pipe), []) ?: [];
    if (!$stages) return null;

    [$sw, $sa] = scope_office_clause('o.office_id');
    $cut = date('Y-m-d', strtotime("-$days days"));

    // Reached-ever, per stage, from the history plus wherever a deal sits now.
    // A deal created straight into stage 2 never generated a history row for it,
    // so the current stage has to be counted as "reached" as well.
    $reached = [];
    foreach ($stages as $s) {
        $sid = (int)$s['id'];
        $reached[$sid] = (int)crmdash_val(
            "SELECT COUNT(DISTINCT o.id) FROM opportunities o
             WHERE $sw AND o.pipeline_id=? AND o.created_at >= ?
               AND (o.stage_id=? OR EXISTS (SELECT 1 FROM opportunity_stage_history h
                                            WHERE h.opportunity_id=o.id AND h.to_stage_id=?))",
            array_merge($sa, [$pipe, $cut, $sid, $sid]), 0);
    }

    $out = []; $prevSid = null;
    foreach ($stages as $s) {
        $sid = (int)$s['id'];
        $here = crmdash_rows(
            "SELECT o.id, o.ref, o.name, o.value, o.partner_name, o.stage_since, o.owner_name
             FROM opportunities o WHERE $sw AND o.pipeline_id=? AND o.stage_id=? AND o.status='OPEN'
             ORDER BY o.value DESC LIMIT 200", array_merge($sa, [$pipe, $sid]));
        $val = 0.0; foreach ($here as $h) $val += (float)$h['value'];

        // Conversion from the PREVIOUS stage, using reached-ever on both.
        $conv = null;
        if ($prevSid !== null && ($reached[$prevSid] ?? 0) > 0)
            $conv = round(($reached[$sid] ?? 0) * 100 / $reached[$prevSid]);

        $out[] = [
            'stage' => $s, 'now' => count($here), 'value' => $val,
            'weighted' => round($val * (int)$s['probability'] / 100, 2),
            'reached' => $reached[$sid] ?? 0,
            'conv_from_prev' => $conv,
            'rows' => array_slice($here, 0, 5),
        ];
        $prevSid = $sid;
    }
    // Overall: of everything that entered the funnel, what share was won.
    $first = $stages[0]['id'] ?? null;
    $wonStage = null;
    foreach ($stages as $s) if ($s['kind'] === 'WON') { $wonStage = (int)$s['id']; break; }
    $overall = ($first && $wonStage && ($reached[(int)$first] ?? 0) > 0)
        ? round(($reached[$wonStage] ?? 0) * 100 / $reached[(int)$first]) : null;

    return ['pipeline' => $pipe, 'stages' => $out, 'overall' => $overall, 'days' => $days,
            'entered' => $first ? ($reached[(int)$first] ?? 0) : 0];
}

// ---- The numbers a sales manager is asked for -------------------------------
function crmdash_headline($days = 365) {
    $cut = date('Y-m-d', strtotime("-$days days"));
    [$sw, $sa] = scope_office_clause('o.office_id');
    $open = function_exists('opp_all') ? (crmdash_try(fn() => opp_all(['open' => 1]), []) ?: []) : [];
    $value = $weighted = 0.0; $stalled = 0; $closingSoon = 0;
    $soon = date('Y-m-d', strtotime('+30 days'));
    foreach ($open as $o) {
        $value += (float)$o['value'];
        $weighted += opp_weighted($o);
        if (opp_stalled($o)) $stalled++;
        if ($o['expected_close'] !== '' && $o['expected_close'] <= $soon) $closingSoon++;
    }
    $win = function_exists('opp_win_rate') ? opp_win_rate($days) : ['won' => 0, 'lost' => 0, 'rate' => null, 'won_value' => 0];

    // Average deal size and sales cycle, from what actually closed.
    $avg = (float)crmdash_val("SELECT COALESCE(AVG(o.value),0) FROM opportunities o
                               WHERE $sw AND o.status='WON' AND o.won_at >= ?", array_merge($sa, [$cut]), 0);
    $cycleRows = crmdash_rows("SELECT o.created_at, o.won_at FROM opportunities o
                               WHERE $sw AND o.status='WON' AND o.won_at <> '' AND o.won_at >= ?",
                              array_merge($sa, [$cut]));
    $cycle = null;
    if ($cycleRows) {
        $t = 0; $n = 0;
        foreach ($cycleRows as $r) {
            $a = strtotime((string)$r['created_at']); $b = strtotime((string)$r['won_at']);
            if ($a && $b && $b >= $a) { $t += ($b - $a) / 86400; $n++; }
        }
        if ($n) $cycle = (int)round($t / $n);
    }
    return ['open' => count($open), 'value' => $value, 'weighted' => $weighted,
            'stalled' => $stalled, 'closing_soon' => $closingSoon,
            'won' => $win['won'], 'lost' => $win['lost'], 'rate' => $win['rate'],
            'won_value' => $win['won_value'], 'avg_deal' => $avg, 'cycle_days' => $cycle];
}

// Where the whole thing starts: leads in, and how many became deals.
function crmdash_topfunnel($days = 365) {
    $cut = date('Y-m-d', strtotime("-$days days"));
    [$lw, $la] = scope_office_clause('l.office_id');
    $leads = (int)crmdash_val("SELECT COUNT(*) FROM leads l WHERE $lw AND l.created_at >= ?", array_merge($la, [$cut]), 0);
    $toOpp = (int)crmdash_val("SELECT COUNT(*) FROM opportunities o WHERE o.lead_id IS NOT NULL AND o.created_at >= ?", [$cut], 0);
    [$qw, $qa] = scope_clause('q.office_id', 'q.sbu');
    $quotes = (int)crmdash_val("SELECT COUNT(*) FROM quotations q WHERE $qw AND q.is_current=1 AND q.created_at >= ?", array_merge($qa, [$cut]), 0);
    [$ow, $oa] = scope_office_clause('o.office_id');
    $won = (int)crmdash_val("SELECT COUNT(*) FROM opportunities o WHERE $ow AND o.status='WON' AND o.won_at >= ?", array_merge($oa, [$cut]), 0);
    return ['leads' => $leads, 'to_opp' => $toOpp, 'quotes' => $quotes, 'won' => $won,
            'lead_to_opp' => $leads ? round($toOpp * 100 / $leads) : null];
}

// Who is selling. Not a scoreboard for its own sake — an owner with a big
// pipeline and no wins is a conversation, and it is invisible without this.
function crmdash_owners($days = 365) {
    $cut = date('Y-m-d', strtotime("-$days days"));
    [$sw, $sa] = scope_office_clause('o.office_id');
    return crmdash_rows(
        "SELECT COALESCE(NULLIF(o.owner_name,''),'(nobody)') owner,
                SUM(CASE WHEN o.status='OPEN' THEN 1 ELSE 0 END) open_n,
                COALESCE(SUM(CASE WHEN o.status='OPEN' THEN o.value ELSE 0 END),0) open_value,
                SUM(CASE WHEN o.status='WON'  THEN 1 ELSE 0 END) won_n,
                COALESCE(SUM(CASE WHEN o.status='WON' THEN o.value ELSE 0 END),0) won_value,
                SUM(CASE WHEN o.status='LOST' THEN 1 ELSE 0 END) lost_n
         FROM opportunities o
         WHERE $sw AND (o.status='OPEN' OR o.created_at >= ?)
         GROUP BY COALESCE(NULLIF(o.owner_name,''),'(nobody)')
         ORDER BY open_value DESC, won_value DESC LIMIT 15", array_merge($sa, [$cut]));
}

// Deals that have stopped moving. The most useful list on the screen.
function crmdash_stuck($limit = 10) {
    if (!function_exists('opp_all')) return [];
    $rows = crmdash_try(fn() => opp_all(['open' => 1]), []) ?: [];
    $out = [];
    foreach ($rows as $o) if (opp_stalled($o)) $out[] = $o;
    usort($out, fn($a, $b) => opp_days_in_stage($b) <=> opp_days_in_stage($a));
    return array_slice($out, 0, $limit);
}

// Activity, because a funnel with no calls behind it is a wish list.
function crmdash_activity($days = 30) {
    if (!function_exists('act_migrate')) return null;
    act_migrate();
    $cut = date('c', strtotime("-$days days"));
    $rows = crmdash_rows("SELECT kind, COUNT(*) n FROM activities
                          WHERE occurred_at >= ? AND auto=0 GROUP BY kind ORDER BY n DESC", [$cut]);
    $total = 0; foreach ($rows as $r) $total += (int)$r['n'];
    return ['rows' => $rows, 'total' => $total, 'days' => $days,
            'silent' => function_exists('act_silent_customers')
                        ? count(crmdash_try(fn() => act_silent_customers(30, 200), []) ?: []) : 0];
}

function ops_crmdash($route, $method) {
    ops_require(crmdash_can(), 'You cannot open the sales dashboard.');
    $days = (int)($_GET['days'] ?? 365);
    if (!in_array($days, [30, 90, 180, 365, 730], true)) $days = 365;
    $pipe = (int)($_GET['pipeline'] ?? 0);

    $funnel = crmdash_funnel($pipe, $days);
    if (wants_csv() && $funnel) {
        $csv = [['Stage', 'Deals here now', 'Value here', 'Weighted', 'Ever reached', 'Conversion from previous']];
        foreach ($funnel['stages'] as $s)
            $csv[] = [$s['stage']['name'], $s['now'], $s['value'], $s['weighted'], $s['reached'],
                      $s['conv_from_prev'] === null ? '' : $s['conv_from_prev'] . '%'];
        csv_download('funnel-' . date('Y-m-d') . '.csv', $csv);
    }

    view('ops/crm_dashboard', [
        'funnel'   => $funnel,
        'head'     => crmdash_headline($days),
        'top'      => crmdash_topfunnel($days),
        'owners'   => crmdash_owners($days),
        'stuck'    => crmdash_stuck(10),
        'activity' => crmdash_activity(30),
        'losses'   => function_exists('opp_loss_reasons') ? opp_loss_reasons($days) : [],
        'pipelines'=> function_exists('pipelines_all') ? (crmdash_try(fn() => pipelines_all('OPPORTUNITY'), []) ?: []) : [],
        'days'     => $days, 'pipe' => $pipe,
        'industry' => function_exists('industry_current') ? industry_current() : '',
        'industryLabel' => function_exists('industry_label') && function_exists('industry_current')
                           ? industry_label(industry_current()) : '',
        'canSetIndustry' => function_exists('industry_can') && industry_can(),
    ]);
    return true;
}
