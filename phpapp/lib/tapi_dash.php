<?php
// ============================================================================
//  TAPI Slice 4 — role dashboards, drill-down, and the data-quality view.
//
//  Assembles Slices 1–3 into what management actually opens: role-tailored
//  dashboards (a KPI row + breakdown charts), each summary drilling down to the
//  SOURCE RECORDS behind it (§16/§18 — a number you can click through to the
//  rows that made it), a configurable KPI hub, and a data-quality dashboard that
//  reuses the platform's own integrity engine. Everything inherits the filter
//  bar and the mandatory scope from the engine — a dashboard can show nothing a
//  user could not already open.
// ============================================================================

// Role → dashboard layout. Each is KPI keys (a card row) + breakdown charts.
// Roles map to what a person needs, not to a fixed hierarchy; the hub lets any
// authorised user switch between them.
function tapi_dashboards() {
    return [
        'executive' => ['label' => 'Executive', 'kpis' => ['reports_issued','job_closure_rate','sla_compliance','capa_effectiveness','invoiced_value','open_ncrs'],
            'charts' => [['bd'=>'service_mix','type'=>'hbar','title'=>'Service mix (issued reports by type)'],
                         ['bd'=>'sla_status','type'=>'donut','title'=>'SLA status']]],
        'operations' => ['label' => 'Operations', 'kpis' => ['jobs_closed','job_closure_rate','sla_compliance','report_tat','open_ncrs'],
            'charts' => [['bd'=>'delay_categories','type'=>'pareto','title'=>'Delay causes (Pareto)'],
                         ['bd'=>'report_pipeline','type'=>'hbar','title'=>'Report pipeline'],
                         ['bd'=>'sla_status','type'=>'donut','title'=>'SLA status']]],
        'quality' => ['label' => 'Quality', 'kpis' => ['open_ncrs','ncr_closure_time','capa_effectiveness','report_revision_rate'],
            'charts' => [['bd'=>'rootcause','type'=>'pareto','title'=>'Root-cause methods'],
                         ['bd'=>'report_pipeline','type'=>'hbar','title'=>'Report pipeline']]],
        'service' => ['label' => 'Service', 'kpis' => ['reports_issued','report_issue_rate','report_tat','release_rate','report_revision_rate'],
            'charts' => [['bd'=>'service_mix','type'=>'hbar','title'=>'Service mix'],
                         ['bd'=>'release_status','type'=>'donut','title'=>'Release status']]],
        'vendor' => ['label' => 'Vendor', 'kpis' => ['vendor_reassess_due'],
            'charts' => [['bd'=>'vendor_scores','type'=>'hbar','title'=>'Vendor score distribution']]],
        'client' => ['label' => 'Client & portal', 'kpis' => ['portal_active_users'],
            'charts' => [['bd'=>'portal_activity','type'=>'hbar','title'=>'Portal activity']]],
    ];
}

// The chart for a breakdown, choosing the renderer named in the config.
function tapi_render_breakdown($bd, $type, $ctx) {
    $fn = 'tapi_bd_' . $bd;
    $data = function_exists($fn) ? $fn($ctx) : [];
    if (!$data) return tapi_chart_empty();
    switch ($type) {
        case 'pareto': return tapi_svg_pareto($data);
        case 'donut':  return function_exists('svg_donut') ? svg_donut($data, false) : tapi_svg_pareto($data);
        case 'hbar':   return function_exists('svg_hbars') ? svg_hbars($data, false) : tapi_svg_pareto($data);
        case 'line':   $pts = []; foreach ($data as $k => $v) $pts[] = ['x'=>$k,'y'=>$v]; return tapi_svg_line(['label'=>$bd,'points'=>$pts]);
        default:       return tapi_svg_pareto($data);
    }
}

// Render a whole dashboard for a role and a filter set.
function tapi_render_dashboard($role, $filters) {
    $dashes = tapi_dashboards();
    $cfg = $dashes[$role] ?? reset($dashes);
    $ctx = tapi_ctx_from_filters($filters);
    $cmp = $filters['compare'] ?? 'PREV';
    $esc = fn($s) => function_exists('e') ? e($s) : htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

    // KPI row — only the KPIs configured for this role, in order.
    $defsByKey = []; foreach (tapi_kpi_defs() as $d) $defsByKey[$d['kpi_key']] = $d;
    $picked = [];
    foreach ($cfg['kpis'] as $k) if (isset($defsByKey[$k])) $picked[] = $defsByKey[$k];
    $h = tapi_style();
    $h .= '<div class="tapi-kpi-row">';
    foreach ($picked as $d) {
        $card = tapi_kpi_eval($d, $ctx);
        $c = tapi_compare($d, $ctx, $cmp);
        $h .= kpi_card($card, $c, ['href' => '/analytics-drill?kpi=' . urlencode($d['kpi_key']) . tapi_qs($filters)]);
    }
    $h .= '</div>';

    // Breakdown charts, each a panel with a drill link.
    if (!empty($cfg['charts'])) {
        $h .= '<div style="display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(320px,1fr))">';
        foreach ($cfg['charts'] as $ch) {
            $svg = tapi_render_breakdown($ch['bd'], $ch['type'], $ctx);
            $h .= '<div class="panel"><h3 style="margin:0 0 10px;font-size:15px">' . $esc($ch['title'])
                . ' <a style="font-size:12px;font-weight:400" href="/analytics-drill?bd=' . urlencode($ch['bd']) . tapi_qs($filters) . '">details →</a></h3>'
                . $svg . '</div>';
        }
        $h .= '</div>';
    }

    // Disruptions & changes — management/operations views only. Uses the same
    // date range and branch scope as the rest of the dashboard.
    if (in_array($role, ['executive', 'operations'], true) && function_exists('tosrm_disruptions')) {
        $dOff = null;
        if (!empty($filters['office'])) $dOff = [(int)$filters['office']];
        elseif (function_exists('tosrm_office_scope')) $dOff = tosrm_office_scope();
        $dd = tosrm_disruptions($dOff, (string)($ctx['from'] ?? ''), (string)($ctx['to'] ?? ''));
        $h .= '<h3 style="margin:22px 0 10px;font-size:15px">Disruptions &amp; changes '
            . '<span style="font-size:12px;font-weight:400;color:#9aa3af">— client &amp; office cancellations, engineer changes, no-shows for the selected period</span></h3>'
            . tosrm_disruptions_html($dd);
    }
    return $h;
}

// Preserve the current filters in a querystring fragment.
function tapi_qs($f) {
    $keep = ['period','from','to','office','sbu','compare'];
    $qs = '';
    foreach ($keep as $k) if (isset($f[$k]) && $f[$k] !== '' && $f[$k] !== 0) $qs .= '&' . $k . '=' . urlencode((string)$f[$k]);
    return $qs;
}

// ---- Drill-down: a breakdown slice (or KPI) → the source records -----------
// Each entry returns [columns, rows] scoped + period-filtered, so the rows a
// user sees are exactly the rows the KPI counted, and never outside their scope.
function tapi_drill($bd, $key, $ctx) {
    [$sw, $sa] = tapi_scope('d.office_id', 'd.sbu');
    [$pw, $pa] = tapi_period_sql("substr(COALESCE(NULLIF(d.issue_date,''), d.created_at),1,10)", $ctx);
    switch ($bd) {
        case 'report_pipeline':
            $rows = ops_all("SELECT d.irn, d.type_code, d.status, d.issue_date FROM report_docs d
                WHERE COALESCE(d.deleted,0)=0 AND d.status=? AND $sw AND $pw ORDER BY d.id DESC LIMIT 500", array_merge([$key], $sa, $pa));
            return [['IRN','Type','Status','Issued'], array_map(fn($r) => [$r['irn'],$r['type_code'],$r['status'],$r['issue_date']], $rows)];
        case 'release_status':
            $rows = ops_all("SELECT d.irn, d.type_code, d.release_status, d.issue_date FROM report_docs d
                WHERE COALESCE(d.deleted,0)=0 AND d.release_status=? AND $sw AND $pw ORDER BY d.id DESC LIMIT 500", array_merge([$key], $sa, $pa));
            return [['IRN','Type','Release','Issued'], array_map(fn($r) => [$r['irn'],$r['type_code'],$r['release_status'],$r['issue_date']], $rows)];
        case 'service_mix':
            $rows = ops_all("SELECT d.irn, d.type_code, d.status, d.issue_date FROM report_docs d
                WHERE COALESCE(d.deleted,0)=0 AND d.type_code=? AND $sw AND $pw ORDER BY d.id DESC LIMIT 500", array_merge([$key], $sa, $pa));
            return [['IRN','Type','Status','Issued'], array_map(fn($r) => [$r['irn'],$r['type_code'],$r['status'],$r['issue_date']], $rows)];
        case 'delay_categories':
            [$dpw, $dpa] = tapi_period_sql("substr(COALESCE(NULLIF(dl.scheduled_date,''), dl.created_at),1,10)", $ctx);
            $rows = ops_all("SELECT dl.call_id, dl.category, dl.reason, dl.delay_days, dl.scheduled_date FROM job_delays dl
                WHERE dl.category=? AND $dpw ORDER BY dl.id DESC LIMIT 500", array_merge([$key], $dpa));
            return [['Call','Category','Reason','Delay (d)','Scheduled'], array_map(fn($r) => [$r['call_id'],$r['category'],$r['reason'],$r['delay_days'],$r['scheduled_date']], $rows)];
        case 'rootcause':
            [$cw, $ca] = function_exists('scope_office_clause') ? scope_office_clause('c.office_id') : ['1=1', []];
            [$cpw, $cpa] = tapi_period_sql("substr(COALESCE(NULLIF(c.raised_on,''), c.created_at),1,10)", $ctx);
            $rows = ops_all("SELECT c.ref, c.title, c.rc_method, c.status, c.raised_on FROM capa c
                WHERE c.rc_method=? AND $cw AND $cpw ORDER BY c.id DESC LIMIT 500", array_merge([$key], $ca, $cpa));
            return [['Ref','Title','Method','Status','Raised'], array_map(fn($r) => [$r['ref'],$r['title'],$r['rc_method'],$r['status'],$r['raised_on']], $rows)];
        case 'portal_activity':
            [$ppw, $ppa] = tapi_period_sql("substr(pa.at,1,10)", $ctx);
            $rows = ops_all("SELECT pa.action, pa.detail, pa.at FROM portal_audit pa WHERE pa.action=? AND $ppw ORDER BY pa.id DESC LIMIT 500", array_merge([$key], $ppa));
            return [['Action','Detail','When'], array_map(fn($r) => [$r['action'],$r['detail'],$r['at']], $rows)];
        case 'vendor_scores':
            [$lo, $hi] = tapi_score_band($key);
            $rows = ops_all("SELECT vp.partner_id, vp.last_score, vp.last_band, vp.reassess_on FROM vendor_profiles vp
                WHERE vp.last_score IS NOT NULL AND vp.last_score>=? AND vp.last_score<? ORDER BY vp.last_score DESC LIMIT 500", [$lo, $hi]);
            return [['Vendor id','Score','Band','Re-assess on'], array_map(fn($r) => [$r['partner_id'],$r['last_score'],$r['last_band'],$r['reassess_on']], $rows)];
    }
    return [[], []];
}
function tapi_score_band($label) {
    return ['0–40'=>[0,40], '40–60'=>[40,60], '60–75'=>[60,75], '75–90'=>[75,90], '90–100'=>[90,100.001]][$label] ?? [0, 100.001];
}

// ---- Data-quality dashboard — reuse the platform's integrity engine --------
function tapi_data_quality() {
    if (!function_exists('integrity_checks')) return ['summary' => ['total'=>0,'failed'=>0,'skipped'=>0], 'checks' => []];
    $checks = integrity_checks();
    return ['summary' => integrity_summary($checks), 'checks' => $checks,
            'last' => function_exists('integrity_last_run') ? integrity_last_run() : null];
}

// ============================================================================
//  Route handler — gated so a user only reaches analytics they may see.
// ============================================================================
function tapi_can() {
    return (function_exists('is_master') && is_master())
        || (function_exists('can') && (can('dash.operations') || can('dash.financial') || can('dash.utilization') || can('dash.people')));
}

function ops_tapi($route, $method) {
    ops_require(tapi_can(), 'You do not have access to the analytics dashboards.');
    $filters = tapi_filters();

    if ($route === 'analytics') {
        $role = preg_replace('/[^a-z]/', '', strtolower((string)($_GET['view'] ?? 'executive')));
        if (!isset(tapi_dashboards()[$role])) $role = 'executive';
        $body = tapi_filter_bar($filters, ['show' => ['office','sbu','compare']])
              . tapi_freshness()
              . tapi_render_dashboard($role, $filters);
        view('ops/tapi', ['tab' => 'dash', 'role' => $role, 'dashes' => tapi_dashboards(), 'body' => $body, 'filters' => $filters]);
        return true;
    }

    if ($route === 'analytics-kpis') {
        $rows = [];
        foreach (tapi_kpi_defs(false) as $d) $rows[] = ['def' => $d, 'card' => tapi_kpi_eval($d, tapi_ctx_from_filters($filters))];
        view('ops/tapi_kpis', ['rows' => $rows, 'filters' => $filters, 'metrics' => array_keys(tapi_metrics())]);
        return true;
    }

    if ($route === 'analytics-kpi-edit') {
        $key = (string)($_GET['key'] ?? $_POST['kpi_key'] ?? '');
        if ($method === 'POST') {
            $d = [
                'kpi_key' => trim((string)($_POST['kpi_key'] ?? '')), 'name' => trim((string)($_POST['name'] ?? '')),
                'category' => (string)($_POST['category'] ?? 'OPERATIONS'), 'formula' => trim((string)($_POST['formula'] ?? '')),
                'unit' => (string)($_POST['unit'] ?? 'count'), 'period' => (string)($_POST['period'] ?? 'MONTH'),
                'direction' => (string)($_POST['direction'] ?? 'INFO'),
                'target' => $_POST['target'] ?? '', 'threshold' => $_POST['threshold'] ?? '',
                'status' => (string)($_POST['status'] ?? 'ACTIVE'), 'description' => (string)($_POST['description'] ?? ''),
            ];
            if ($d['kpi_key'] === '' || $d['name'] === '') { flash('A key and a name are required.', 'error'); redirect('/analytics-kpis'); }
            if (!tapi_formula_valid($d['formula'])) {
                flash('That formula is not valid — it may reference an unknown metric or contain a disallowed character. Nothing was saved.', 'error');
                redirect('/analytics-kpi-edit?key=' . urlencode($d['kpi_key']));
            }
            tapi_kpi_save($d);
            flash('KPI "' . $d['name'] . '" saved.');
            redirect('/analytics-kpis');
        }
        $def = $key !== '' ? tapi_kpi_get($key) : null;
        view('ops/tapi_kpi_edit', ['def' => $def, 'metrics' => tapi_metrics()]);
        return true;
    }

    if ($route === 'analytics-export') {
        if (function_exists('tapi_audit')) tapi_audit('EXPORT', ($_GET['fmt'] ?? 'csv') . ' ' . ($filters['from'] ?? ''));
        $rows = tapi_export_matrix($filters);
        $fmt = strtolower((string)($_GET['fmt'] ?? 'csv'));
        if ($fmt === 'xlsx' && function_exists('tapi_xlsx') && ($bytes = tapi_xlsx($rows)) !== null) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="analytics.xlsx"');
            echo $bytes; return true;
        }
        if (function_exists('csv_download')) { csv_download('analytics', $rows); return true; }
        header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="analytics.csv"');
        foreach ($rows as $r) echo implode(',', array_map(fn($c) => '"' . str_replace('"', '""', (string)$c) . '"', $r)) . "\n";
        return true;
    }

    if ($route === 'analytics-review') {
        if (function_exists('tapi_audit')) tapi_audit('REVIEW', (string)($filters['from'] ?? ''));
        $ai = ['', ''];
        if (($_GET['ai'] ?? '') === '1' && function_exists('tapi_ai_summary')) $ai = tapi_ai_summary($filters);
        view('ops/tapi_review', ['review' => tapi_management_review($filters), 'filters' => $filters,
            'ai' => $ai[0], 'aierr' => $ai[1], 'ai_on' => function_exists('ai_active') && ai_active()]);
        return true;
    }

    if ($route === 'analytics-snapshot') {
        if ($method === 'POST') {
            $pk = preg_replace('/[^0-9-]/', '', (string)($_POST['period_key'] ?? ''));
            $act = (string)($_POST['act'] ?? '');
            if ($act === 'snapshot' && $pk !== '') { $r = tapi_snapshot_period($pk); flash($r['ok'] ? ('Snapshot taken — ' . $r['count'] . ' KPIs frozen for ' . $pk . '.') : $r['error'], $r['ok'] ? 'success' : 'error'); }
            elseif ($act === 'state' && $pk !== '') { tapi_period_set_state($pk, (string)($_POST['state'] ?? 'OPEN')); flash('Period ' . $pk . ' set to ' . ($_POST['state'] ?? '') . '.'); }
            redirect('/analytics-snapshot');
        }
        $periods = ops_all("SELECT * FROM analytics_periods ORDER BY period_key DESC") ?: [];
        $recent = ops_all("SELECT period_key, COUNT(*) n, MAX(taken_at) taken_at FROM kpi_snapshots GROUP BY period_key ORDER BY period_key DESC LIMIT 24") ?: [];
        view('ops/tapi_snapshot', ['periods' => $periods, 'recent' => $recent, 'states' => TAPI_PERIOD_STATES,
            'thisMonth' => date('Y-m')]);
        return true;
    }

    if ($route === 'analytics-scorecard') {
        $cards = tapi_scorecards();
        $sid = (int)($_GET['id'] ?? ($cards ? $cards[0]['id'] : 0));
        $eval = $sid ? tapi_scorecard_eval($sid, tapi_ctx_from_filters($filters)) : ['rows'=>[], 'total'=>null, 'excluded'=>0];
        view('ops/tapi_scorecard', ['cards' => $cards, 'sid' => $sid, 'eval' => $eval, 'filters' => $filters]);
        return true;
    }

    if ($route === 'analytics-alerts') {
        if ($method === 'POST') {
            if (($_POST['act'] ?? '') === 'save') {
                tapi_alert_save([
                    'id' => (int)($_POST['id'] ?? 0), 'name' => trim((string)($_POST['name'] ?? '')),
                    'kpi_key' => (string)($_POST['kpi_key'] ?? ''), 'op' => (string)($_POST['op'] ?? 'LT'),
                    'threshold' => $_POST['threshold'] ?? '', 'severity' => (string)($_POST['severity'] ?? 'WARN'),
                    'recipients' => (string)($_POST['recipients'] ?? ''), 'scope_office' => (int)($_POST['scope_office'] ?? 0),
                    'enabled' => !empty($_POST['enabled']),
                ]);
                flash('Alert saved.');
            }
            redirect('/analytics-alerts');
        }
        view('ops/tapi_alerts', ['alerts' => tapi_alerts(), 'firing' => tapi_alerts_eval(tapi_ctx_from_filters($filters)),
            'kpis' => tapi_kpi_defs(false), 'ops' => TAPI_ALERT_OPS, 'sev' => TAPI_SEVERITY]);
        return true;
    }

    if ($route === 'analytics-quality') {
        if ($method === 'POST' && function_exists('integrity_run')) { integrity_run(); flash('Data-quality checks re-run.'); redirect('/analytics-quality'); }
        view('ops/tapi_quality', tapi_data_quality());
        return true;
    }

    if ($route === 'analytics-drill') {
        $ctx = tapi_ctx_from_filters($filters);
        $bd = preg_replace('/[^a-z_]/', '', (string)($_GET['bd'] ?? ''));
        $kpi = (string)($_GET['kpi'] ?? '');
        if ($kpi !== '') {
            // A KPI drill shows its lineage + value; the record-level rows come via
            // the breakdown a chart links to.
            $def = tapi_kpi_get($kpi);
            $card = $def ? tapi_kpi_eval($def, $ctx) : null;
            view('ops/tapi_drill', ['mode' => 'kpi', 'card' => $card, 'def' => $def, 'filters' => $filters, 'cols' => [], 'rows' => []]);
            return true;
        }
        $key = (string)($_GET['key'] ?? '');
        if ($key === '') {
            // no slice chosen yet — show the breakdown itself so the user can pick.
            $fn = 'tapi_bd_' . $bd; $data = function_exists($fn) ? $fn($ctx) : [];
            view('ops/tapi_drill', ['mode' => 'pick', 'bd' => $bd, 'data' => $data, 'filters' => $filters, 'cols' => [], 'rows' => []]);
            return true;
        }
        [$cols, $rows] = tapi_drill($bd, $key, $ctx);
        view('ops/tapi_drill', ['mode' => 'records', 'bd' => $bd, 'key' => $key, 'cols' => $cols, 'rows' => $rows, 'filters' => $filters]);
        return true;
    }

    redirect('/analytics');
    return true;
}
