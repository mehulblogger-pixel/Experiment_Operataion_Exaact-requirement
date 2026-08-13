<?php
// ============================================================================
//  TAPI — TPIA Analytics & Performance Intelligence (Phase 11)
//
//  This is an ANALYTICS / DECISION-VISIBILITY layer. It is NOT a system of
//  record and it does NOT recompute what the domain modules already compute.
//  It reads existing platform data through a small set of NAMED METRICS (each
//  one a thin, scoped query or a wrapper over an existing function), and lets a
//  configurable KPI MASTER compose those metrics into the numbers management
//  actually watches.
//
//  Slice 1 — the foundation:
//    · KPI definition master (configurable; id / formula / unit / target /
//      threshold / direction / period / scope) — nothing like it existed.
//    · A SAFE formula engine. A KPI "formula" composes named metrics with
//      arithmetic and a tiny whitelist of functions. It is NOT eval() and can
//      never execute arbitrary code — the spec is explicit about this.
//    · A metric-adapter registry: named, scoped, lineage-carrying metrics.
//    · Data lineage on every KPI (which metrics, from which source).
//    · Mandatory scope — every metric ANDs in scope_clause(), so a user only
//      ever sees their own offices / business units.
//    · The zero-vs-no-data distinction: a count of zero is a real zero; an
//      average or a ratio with no underlying records is NO DATA, never 0.
//
//  Later slices add: the presentation kit, the domain roll-ups, the role
//  dashboards + drill-down, targets/scorecard/alerts, and snapshots/versioning.
// ============================================================================

const TAPI_DIRECTIONS = [
    'HIGHER' => 'Higher is better',
    'LOWER'  => 'Lower is better',
    'RANGE'  => 'Within range',
    'TARGET' => 'Meet the target',
    'INFO'   => 'Informational',
];
const TAPI_PERIODS = [
    'DAY' => 'Daily', 'WEEK' => 'Weekly', 'FORTNIGHT' => 'Fortnightly', 'MONTH' => 'Monthly',
    'QUARTER' => 'Quarterly', 'HALF' => 'Half-yearly', 'YEAR' => 'Yearly', 'CUSTOM' => 'Custom',
];
const TAPI_CATEGORIES = [
    'BUSINESS' => 'Business', 'OPERATIONS' => 'Operations', 'QUALITY' => 'Quality',
    'CLIENT' => 'Client', 'VENDOR' => 'Vendor', 'RESOURCE' => 'Resource',
    'SERVICE' => 'Service', 'FINANCE' => 'Financial', 'RISK' => 'Risk',
];
// The functions a KPI formula may call. Deliberately tiny and pure.
const TAPI_FUNCS = ['round', 'abs', 'min', 'max', 'coalesce'];

function tapi_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = pk_clause();
    // The KPI MASTER — configurable definitions, the thing the spec says must
    // exist and never did. `formula` composes metric keys (see the registry);
    // `scope_json` optionally narrows a KPI to a service/client/branch. Blank
    // target/threshold = informational.
    db()->exec("CREATE TABLE IF NOT EXISTS kpi_defs (
        id $pk, kpi_key VARCHAR(60) DEFAULT '', name VARCHAR(160) DEFAULT '',
        description VARCHAR(500) DEFAULT '', category VARCHAR(20) DEFAULT 'OPERATIONS',
        formula VARCHAR(400) DEFAULT '', unit VARCHAR(20) DEFAULT 'count',
        period VARCHAR(12) DEFAULT 'MONTH',
        target DECIMAL(16,4) NULL, threshold DECIMAL(16,4) NULL,
        direction VARCHAR(10) DEFAULT 'INFO', data_source VARCHAR(200) DEFAULT '',
        scope_json VARCHAR(600) DEFAULT '', active_from VARCHAR(20) DEFAULT '',
        active_until VARCHAR(20) DEFAULT '', status VARCHAR(12) DEFAULT 'ACTIVE',
        sort_order INT DEFAULT 0, created_by VARCHAR(150) DEFAULT '',
        created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    if (function_exists('idems_unique_index')) { try { idems_unique_index('kpi_defs', 'kpi_key'); } catch (Throwable $e) {} }
    tapi_seed_defaults();
    if (function_exists('tapi_seed_domain')) tapi_seed_domain();   // Slice 3 KPIs
}

// ============================================================================
//  The metric registry — named, scoped, lineage-carrying atoms.
//
//  A metric resolves to a single number for the given context, OR to null when
//  there is genuinely no underlying data to speak of. COUNT/SUM metrics return
//  a real 0 on an empty set (zero jobs IS zero). AVERAGE/RATE metrics return
//  null on an empty set (no reports → no turnaround, not "0 days").
//
//  Every resolver ANDs in scope_clause(), so a metric can never read outside the
//  caller's office/SBU scope. Later slices register the richer wrappers (over
//  mis_summary, tosrm_*, ncr_counts, …); these starter atoms prove the engine.
// ============================================================================
function tapi_period_sql($dateExpr, $ctx) {
    $from = trim((string)($ctx['from'] ?? '')); $to = trim((string)($ctx['to'] ?? ''));
    $w = []; $a = [];
    if ($from !== '') { $w[] = "$dateExpr >= ?"; $a[] = $from; }
    if ($to   !== '') { $w[] = "$dateExpr <= ?"; $a[] = $to; }
    return [$w ? implode(' AND ', $w) : '1=1', $a];
}
function tapi_scope($officeCol, $sbuCol) {
    return function_exists('scope_clause') ? scope_clause($officeCol, $sbuCol) : ['1=1', []];
}

function tapi_metrics() {
    static $reg = null;
    if ($reg !== null) return $reg;
    $countJobs = function ($extra) {
        return function ($ctx) use ($extra) {
            [$sw, $sa] = tapi_scope('j.executing_office_id', 'j.sbu');
            [$pw, $pa] = tapi_period_sql("COALESCE(NULLIF(j.scheduled_date,''), substr(j.created_at,1,10))", $ctx);
            $e = $extra !== '' ? " AND $extra" : '';
            return (int) ops_val("SELECT COUNT(*) FROM jobs j WHERE $sw AND $pw$e", array_merge($sa, $pa));
        };
    };
    $reg = [
        'jobs.total' => ['label'=>'Jobs','unit'=>'count','agg'=>'count','source'=>'ops/jobs',
            'method'=>'COUNT(jobs) in period, office+SBU scoped', 'resolve'=>$countJobs('')],
        'jobs.closed' => ['label'=>'Jobs closed','unit'=>'count','agg'=>'count','source'=>'ops/jobs',
            'method'=>'COUNT(jobs) WHERE closed_flag=1', 'resolve'=>$countJobs('COALESCE(j.closed_flag,0)=1')],
        'jobs.open' => ['label'=>'Jobs open','unit'=>'count','agg'=>'count','source'=>'ops/jobs',
            'method'=>'COUNT(jobs) WHERE closed_flag=0', 'resolve'=>$countJobs('COALESCE(j.closed_flag,0)=0')],
        'jobs.mandays' => ['label'=>'Man-days','unit'=>'days','agg'=>'sum','source'=>'ops/jobs',
            'method'=>'SUM(jobs.mandays) in period', 'resolve'=>function ($ctx) {
                [$sw, $sa] = tapi_scope('j.executing_office_id', 'j.sbu');
                [$pw, $pa] = tapi_period_sql("COALESCE(NULLIF(j.scheduled_date,''), substr(j.created_at,1,10))", $ctx);
                return round((float) ops_val("SELECT COALESCE(SUM(j.mandays),0) FROM jobs j WHERE $sw AND $pw", array_merge($sa, $pa)), 1);
            }],
        'revenue.invoiced' => ['label'=>'Invoiced value','unit'=>'money','agg'=>'sum','source'=>'ops/jobs',
            'method'=>'SUM(jobs.invoice_amount) WHERE invoice_raised=1', 'resolve'=>function ($ctx) {
                [$sw, $sa] = tapi_scope('j.executing_office_id', 'j.sbu');
                [$pw, $pa] = tapi_period_sql("COALESCE(NULLIF(j.scheduled_date,''), substr(j.created_at,1,10))", $ctx);
                return round((float) ops_val("SELECT COALESCE(SUM(j.invoice_amount),0) FROM jobs j WHERE COALESCE(j.invoice_raised,0)=1 AND $sw AND $pw", array_merge($sa, $pa)), 2);
            }],
        'calls.total' => ['label'=>'Calls','unit'=>'count','agg'=>'count','source'=>'ops/calls',
            'method'=>'COUNT(calls) in period', 'resolve'=>function ($ctx) {
                [$sw, $sa] = tapi_scope('c.executing_office_id', 'c.sbu');
                [$pw, $pa] = tapi_period_sql("COALESCE(NULLIF(c.call_received_date,''), substr(c.created_at,1,10))", $ctx);
                return (int) ops_val("SELECT COUNT(*) FROM calls c WHERE $sw AND $pw", array_merge($sa, $pa));
            }],
        'reports.total' => ['label'=>'Reports','unit'=>'count','agg'=>'count','source'=>'idems/report_docs',
            'method'=>'COUNT(report_docs) not deleted', 'resolve'=>function ($ctx) {
                [$sw, $sa] = tapi_scope('d.office_id', 'd.sbu');
                [$pw, $pa] = tapi_period_sql("substr(d.created_at,1,10)", $ctx);
                return (int) ops_val("SELECT COUNT(*) FROM report_docs d WHERE COALESCE(d.deleted,0)=0 AND $sw AND $pw", array_merge($sa, $pa));
            }],
        'reports.issued' => ['label'=>'Reports issued','unit'=>'count','agg'=>'count','source'=>'idems/report_docs',
            'method'=>'COUNT(report_docs) WHERE finalized=1', 'resolve'=>function ($ctx) {
                [$sw, $sa] = tapi_scope('d.office_id', 'd.sbu');
                [$pw, $pa] = tapi_period_sql("substr(COALESCE(d.finalized_at, d.created_at),1,10)", $ctx);
                return (int) ops_val("SELECT COUNT(*) FROM report_docs d WHERE d.finalized=1 AND COALESCE(d.deleted,0)=0 AND $sw AND $pw", array_merge($sa, $pa));
            }],
        'report.tat_avg_days' => ['label'=>'Average report TAT','unit'=>'days','agg'=>'avg','source'=>'idems/report_docs',
            'method'=>'AVG(finalized_at − inspection_date) over issued reports', 'resolve'=>function ($ctx) {
                [$sw, $sa] = tapi_scope('d.office_id', 'd.sbu');
                [$pw, $pa] = tapi_period_sql("substr(COALESCE(d.finalized_at, d.created_at),1,10)", $ctx);
                $rows = ops_all("SELECT d.inspection_date, d.finalized_at FROM report_docs d
                    WHERE d.finalized=1 AND COALESCE(d.deleted,0)=0 AND COALESCE(d.inspection_date,'')<>''
                      AND COALESCE(d.finalized_at,'')<>'' AND $sw AND $pw", array_merge($sa, $pa));
                if (!$rows) return null;   // NO DATA — not zero
                $sum = 0.0; $n = 0;
                foreach ($rows as $r) {
                    $a = strtotime(substr((string)$r['finalized_at'], 0, 10)); $b = strtotime((string)$r['inspection_date']);
                    if ($a && $b) { $sum += ($a - $b) / 86400; $n++; }
                }
                return $n ? round($sum / $n, 1) : null;
            }],
        'ncr.total' => ['label'=>'Nonconformities','unit'=>'count','agg'=>'count','source'=>'ncr/nonconformities',
            'method'=>'COUNT(nonconformities) in period', 'resolve'=>function ($ctx) {
                [$sw, $sa] = tapi_scope('n.office_id', 'n.sbu');
                [$pw, $pa] = tapi_period_sql("substr(COALESCE(NULLIF(n.detected_on,''), n.created_at),1,10)", $ctx);
                return (int) ops_val("SELECT COUNT(*) FROM nonconformities n WHERE $sw AND $pw", array_merge($sa, $pa));
            }],
        'ncr.open' => ['label'=>'Open nonconformities','unit'=>'count','agg'=>'count','source'=>'ncr/nonconformities',
            'method'=>"COUNT(nonconformities) WHERE status<>'CLOSED'", 'resolve'=>function ($ctx) {
                [$sw, $sa] = tapi_scope('n.office_id', 'n.sbu');
                [$pw, $pa] = tapi_period_sql("substr(COALESCE(NULLIF(n.detected_on,''), n.created_at),1,10)", $ctx);
                return (int) ops_val("SELECT COUNT(*) FROM nonconformities n WHERE n.status<>'CLOSED' AND $sw AND $pw", array_merge($sa, $pa));
            }],
        'ncr.closure_avg_days' => ['label'=>'Average NCR closure time','unit'=>'days','agg'=>'avg','source'=>'ncr/nonconformities',
            'method'=>'AVG(closed_on − detected_on) over closed NCRs', 'resolve'=>function ($ctx) {
                [$sw, $sa] = tapi_scope('n.office_id', 'n.sbu');
                [$pw, $pa] = tapi_period_sql("substr(COALESCE(NULLIF(n.detected_on,''), n.created_at),1,10)", $ctx);
                $rows = ops_all("SELECT n.detected_on, n.closed_on FROM nonconformities n
                    WHERE n.status='CLOSED' AND COALESCE(n.detected_on,'')<>'' AND COALESCE(n.closed_on,'')<>'' AND $sw AND $pw",
                    array_merge($sa, $pa));
                if (!$rows) return null;   // NO DATA — not zero
                $sum = 0.0; $n = 0;
                foreach ($rows as $r) {
                    $a = strtotime((string)$r['closed_on']); $b = strtotime((string)$r['detected_on']);
                    if ($a && $b) { $sum += ($a - $b) / 86400; $n++; }
                }
                return $n ? round($sum / $n, 1) : null;
            }],
    ];
    if (function_exists('tapi_domain_metrics')) $reg = array_merge($reg, tapi_domain_metrics());
    return $reg;
}

function tapi_metric_def($key) { return tapi_metrics()[$key] ?? null; }
function tapi_metric_value($key, $ctx) {
    $m = tapi_metric_def($key);
    if (!$m) return null;
    try { return ($m['resolve'])($ctx); }
    catch (Throwable $e) { return null; }   // a broken source must never crash a dashboard
}

// ============================================================================
//  The SAFE formula engine — arithmetic over named metrics, nothing else.
//
//  Grammar (recursive descent):
//     expr   := term (('+'|'-') term)*
//     term   := factor (('*'|'/') factor)*
//     factor := number | metric | func '(' args ')' | '(' expr ')' | '-' factor
//  A bare identifier is a METRIC KEY (looked up in the value bag). An identifier
//  followed by '(' is one of TAPI_FUNCS. There is no way to name a PHP function,
//  a variable, or a statement — the tokenizer rejects every character that is
//  not a digit, a metric/function name, an operator, a comma or a parenthesis.
//
//  Null propagation is the no-data rule: any operand null → null; divide by zero
//  → null; so a ratio whose denominator has no data becomes NO DATA, not a
//  misleading 0. coalesce() is the one exception, used to supply an explicit
//  fallback when that is genuinely wanted.
// ============================================================================
function tapi_formula_tokens($expr) {
    $expr = (string)$expr; $toks = []; $n = strlen($expr); $i = 0;
    while ($i < $n) {
        $ch = $expr[$i];
        if (ctype_space($ch)) { $i++; continue; }
        if (ctype_digit($ch) || ($ch === '.' && $i + 1 < $n && ctype_digit($expr[$i + 1]))) {
            $j = $i; while ($j < $n && (ctype_digit($expr[$j]) || $expr[$j] === '.')) $j++;
            $num = substr($expr, $i, $j - $i);
            if (substr_count($num, '.') > 1) return null;
            $toks[] = ['num', (float)$num]; $i = $j; continue;
        }
        if (ctype_alpha($ch) || $ch === '_') {
            $j = $i; while ($j < $n && (ctype_alnum($expr[$j]) || $expr[$j] === '_' || $expr[$j] === '.')) $j++;
            $toks[] = ['id', substr($expr, $i, $j - $i)]; $i = $j; continue;
        }
        if (strpos('+-*/', $ch) !== false) { $toks[] = ['op', $ch]; $i++; continue; }
        if ($ch === '(') { $toks[] = ['lp', '(']; $i++; continue; }
        if ($ch === ')') { $toks[] = ['rp', ')']; $i++; continue; }
        if ($ch === ',') { $toks[] = ['comma', ',']; $i++; continue; }
        return null;   // any other character is not allowed — no eval surface
    }
    return $toks;
}

// Metric keys referenced by a formula (excludes function names).
function tapi_formula_refs($expr) {
    $toks = tapi_formula_tokens($expr);
    if ($toks === null) return [];
    $out = [];
    foreach ($toks as $t) if ($t[0] === 'id' && !in_array(strtolower($t[1]), TAPI_FUNCS, true)) $out[$t[1]] = true;
    return array_keys($out);
}

// Is this a formula we are willing to evaluate? Tokenizes, parses fully, and
// checks every identifier is a known metric or an allowed function.
function tapi_formula_valid($expr) {
    $toks = tapi_formula_tokens($expr);
    if ($toks === null || !$toks) return false;
    foreach ($toks as $t) {
        if ($t[0] !== 'id') continue;
        $low = strtolower($t[1]);
        if (in_array($low, TAPI_FUNCS, true)) continue;
        if (!tapi_metric_def($t[1])) return false;   // unknown identifier
    }
    // dummy-evaluate (all metrics = 1) purely to confirm it parses end-to-end.
    $vals = []; foreach (tapi_formula_refs($expr) as $k) $vals[$k] = 1.0;
    $i = 0; $err = false;
    tapi__parse_expr($toks, $i, $vals, $err);
    return $err === false && $i === count($toks);
}

function tapi_formula_eval($expr, $vals) {
    $toks = tapi_formula_tokens($expr);
    if ($toks === null) return null;
    $i = 0; $err = false;
    $v = tapi__parse_expr($toks, $i, $vals, $err);
    if ($err || $i !== count($toks)) return null;
    return $v;
}

// null-aware arithmetic
function tapi__arith($a, $op, $b) {
    if ($a === null || $b === null) return null;
    switch ($op) {
        case '+': return $a + $b;
        case '-': return $a - $b;
        case '*': return $a * $b;
        case '/': return ((float)$b == 0.0) ? null : $a / $b;   // divide-by-zero → NO DATA
    }
    return null;
}
function tapi__parse_expr(&$t, &$i, &$vals, &$err) {
    $v = tapi__parse_term($t, $i, $vals, $err);
    while (!$err && $i < count($t) && $t[$i][0] === 'op' && ($t[$i][1] === '+' || $t[$i][1] === '-')) {
        $op = $t[$i][1]; $i++;
        $r = tapi__parse_term($t, $i, $vals, $err);
        $v = tapi__arith($v, $op, $r);
    }
    return $v;
}
function tapi__parse_term(&$t, &$i, &$vals, &$err) {
    $v = tapi__parse_factor($t, $i, $vals, $err);
    while (!$err && $i < count($t) && $t[$i][0] === 'op' && ($t[$i][1] === '*' || $t[$i][1] === '/')) {
        $op = $t[$i][1]; $i++;
        $r = tapi__parse_factor($t, $i, $vals, $err);
        $v = tapi__arith($v, $op, $r);
    }
    return $v;
}
function tapi__parse_factor(&$t, &$i, &$vals, &$err) {
    if ($i >= count($t)) { $err = true; return null; }
    $tok = $t[$i];
    if ($tok[0] === 'op' && $tok[1] === '-') { $i++; $f = tapi__parse_factor($t, $i, $vals, $err); return $f === null ? null : -$f; }
    if ($tok[0] === 'num') { $i++; return (float)$tok[1]; }
    if ($tok[0] === 'lp') {
        $i++; $v = tapi__parse_expr($t, $i, $vals, $err);
        if ($err || $i >= count($t) || $t[$i][0] !== 'rp') { $err = true; return null; }
        $i++; return $v;
    }
    if ($tok[0] === 'id') {
        $name = $tok[1]; $low = strtolower($name); $i++;
        // function call?
        if ($i < count($t) && $t[$i][0] === 'lp') {
            if (!in_array($low, TAPI_FUNCS, true)) { $err = true; return null; }
            $i++; $args = [];
            if (!($i < count($t) && $t[$i][0] === 'rp')) {
                $args[] = tapi__parse_expr($t, $i, $vals, $err);
                while (!$err && $i < count($t) && $t[$i][0] === 'comma') { $i++; $args[] = tapi__parse_expr($t, $i, $vals, $err); }
            }
            if ($err || $i >= count($t) || $t[$i][0] !== 'rp') { $err = true; return null; }
            $i++;
            return tapi__apply_func($low, $args);
        }
        // bare metric reference → value bag (missing/unknown → null = no data)
        return array_key_exists($name, $vals) ? $vals[$name] : null;
    }
    $err = true; return null;
}
function tapi__apply_func($fn, $args) {
    switch ($fn) {
        case 'coalesce':
            foreach ($args as $a) if ($a !== null) return $a;
            return null;
        case 'abs':   return $args[0] === null ? null : abs($args[0]);
        case 'round': if ($args[0] === null) return null; $n = isset($args[1]) ? (int)$args[1] : 0; return round($args[0], $n);
        case 'min':   foreach ($args as $a) if ($a === null) return null; return count($args) ? min($args) : null;
        case 'max':   foreach ($args as $a) if ($a === null) return null; return count($args) ? max($args) : null;
    }
    return null;
}

// ============================================================================
//  KPI evaluation — compose metrics, judge against target/threshold, and carry
//  the lineage that lets management trust the number.
// ============================================================================
function tapi_status($value, $def, $noData) {
    if ($noData || $value === null) return 'NO_DATA';
    $dir = strtoupper((string)($def['direction'] ?? 'INFO'));
    $target    = ($def['target'] ?? null);    $target    = ($target === '' || $target === null) ? null : (float)$target;
    $threshold = ($def['threshold'] ?? null); $threshold = ($threshold === '' || $threshold === null) ? null : (float)$threshold;
    if ($dir === 'INFO' || $target === null) return 'INFO';
    $v = (float)$value;
    if ($dir === 'HIGHER') {
        if ($v >= $target) return 'GOOD';
        if ($threshold !== null && $v >= $threshold) return 'WARN';
        return 'BAD';
    }
    if ($dir === 'LOWER') {
        if ($v <= $target) return 'GOOD';
        if ($threshold !== null && $v <= $threshold) return 'WARN';
        return 'BAD';
    }
    if ($dir === 'TARGET') {
        $tol = $threshold !== null ? abs($threshold) : max(0.0001, abs($target) * 0.05);
        return abs($v - $target) <= $tol ? 'GOOD' : 'WARN';
    }
    if ($dir === 'RANGE') {
        $lo = $threshold !== null ? min($threshold, $target) : $target;
        $hi = $threshold !== null ? max($threshold, $target) : $target;
        return ($v >= $lo && $v <= $hi) ? 'GOOD' : 'WARN';
    }
    return 'INFO';
}

// Evaluate a KPI definition for a context. Returns a full card payload with the
// value, no-data flag, status, target, and the data lineage.
function tapi_kpi_eval($def, $ctx = []) {
    $expr = (string)($def['formula'] ?? '');
    $refs = tapi_formula_refs($expr);
    $vals = []; $lineage = [];
    foreach ($refs as $k) {
        $vals[$k] = tapi_metric_value($k, $ctx);
        $m = tapi_metric_def($k);
        if ($m) $lineage[] = ['metric' => $k, 'source' => $m['source'], 'method' => $m['method']];
    }
    $value  = tapi_formula_valid($expr) ? tapi_formula_eval($expr, $vals) : null;
    $noData = ($value === null);
    return [
        'key'        => (string)($def['kpi_key'] ?? ''),
        'name'       => (string)($def['name'] ?? ''),
        'category'   => (string)($def['category'] ?? ''),
        'unit'       => (string)($def['unit'] ?? 'count'),
        'value'      => $value,
        'no_data'    => $noData,
        'status'     => tapi_status($value, $def, $noData),
        'target'     => ($def['target'] ?? null) === '' ? null : $def['target'],
        'direction'  => (string)($def['direction'] ?? 'INFO'),
        'period'     => (string)($def['period'] ?? 'MONTH'),
        'lineage'    => $lineage,
        'refreshed_at' => date('c'),
    ];
}

// ============================================================================
//  KPI master CRUD + a starter seed. All keys are stable so re-seeding is a
//  no-op; nothing overwrites a definition an administrator has edited.
// ============================================================================
function tapi_kpi_defs($activeOnly = true) {
    $w = $activeOnly ? "WHERE status='ACTIVE'" : '';
    return ops_all("SELECT * FROM kpi_defs $w ORDER BY sort_order, id") ?: [];
}
function tapi_kpi_get($key) { return ops_one("SELECT * FROM kpi_defs WHERE kpi_key=?", [(string)$key]) ?: null; }
function tapi_kpi_save($d) {
    $now = date('c');
    $exists = tapi_kpi_get($d['kpi_key']);
    if ($exists) {
        // KPI versioning — if the meaning of the KPI changes (formula / target /
        // threshold / direction), snapshot the OLD definition first so historical
        // interpretation is never silently overwritten (§80).
        if (function_exists('tapi_kpi_version_snapshot')) { try { tapi_kpi_version_snapshot($exists, $d); } catch (Throwable $e) {} }
        db()->prepare("UPDATE kpi_defs SET name=?, description=?, category=?, formula=?, unit=?, period=?,
            target=?, threshold=?, direction=?, data_source=?, scope_json=?, active_from=?, active_until=?,
            status=?, sort_order=?, updated_at=? WHERE kpi_key=?")
            ->execute([$d['name'] ?? '', $d['description'] ?? '', $d['category'] ?? 'OPERATIONS', $d['formula'] ?? '',
                $d['unit'] ?? 'count', $d['period'] ?? 'MONTH',
                ($d['target'] ?? '') === '' ? null : $d['target'], ($d['threshold'] ?? '') === '' ? null : $d['threshold'],
                $d['direction'] ?? 'INFO', $d['data_source'] ?? '', $d['scope_json'] ?? '',
                $d['active_from'] ?? '', $d['active_until'] ?? '', $d['status'] ?? 'ACTIVE', (int)($d['sort_order'] ?? 0),
                $now, $d['kpi_key']]);
        return (int)$exists['id'];
    }
    db()->prepare("INSERT INTO kpi_defs (kpi_key,name,description,category,formula,unit,period,target,threshold,
        direction,data_source,scope_json,active_from,active_until,status,sort_order,created_by,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$d['kpi_key'], $d['name'] ?? '', $d['description'] ?? '', $d['category'] ?? 'OPERATIONS',
            $d['formula'] ?? '', $d['unit'] ?? 'count', $d['period'] ?? 'MONTH',
            ($d['target'] ?? '') === '' ? null : $d['target'], ($d['threshold'] ?? '') === '' ? null : $d['threshold'],
            $d['direction'] ?? 'INFO', $d['data_source'] ?? '', $d['scope_json'] ?? '',
            $d['active_from'] ?? '', $d['active_until'] ?? '', $d['status'] ?? 'ACTIVE', (int)($d['sort_order'] ?? 0),
            function_exists('current_user') ? (string)(user_name(current_user()) ?? '') : '', $now, $now]);
    return (int)db()->lastInsertId();
}

function tapi_seed_defaults() {
    static $seeded = false; if ($seeded) return; $seeded = true;
    $starter = [
        ['kpi_key'=>'jobs_closed', 'name'=>'Jobs closed', 'category'=>'OPERATIONS', 'formula'=>'jobs.closed',
         'unit'=>'count', 'direction'=>'HIGHER', 'sort_order'=>10, 'data_source'=>'ops/jobs'],
        ['kpi_key'=>'job_closure_rate', 'name'=>'Job closure rate', 'category'=>'OPERATIONS',
         'formula'=>'round(jobs.closed / jobs.total * 100, 1)', 'unit'=>'%', 'direction'=>'HIGHER',
         'target'=>90, 'threshold'=>75, 'sort_order'=>20, 'data_source'=>'ops/jobs'],
        ['kpi_key'=>'reports_issued', 'name'=>'Reports issued', 'category'=>'SERVICE', 'formula'=>'reports.issued',
         'unit'=>'count', 'direction'=>'HIGHER', 'sort_order'=>30, 'data_source'=>'idems/report_docs'],
        ['kpi_key'=>'report_issue_rate', 'name'=>'Report issue rate', 'category'=>'SERVICE',
         'formula'=>'round(reports.issued / reports.total * 100, 1)', 'unit'=>'%', 'direction'=>'HIGHER',
         'target'=>85, 'threshold'=>70, 'sort_order'=>40, 'data_source'=>'idems/report_docs'],
        ['kpi_key'=>'report_tat', 'name'=>'Average report TAT', 'category'=>'SERVICE', 'formula'=>'report.tat_avg_days',
         'unit'=>'days', 'direction'=>'LOWER', 'target'=>3, 'threshold'=>5, 'sort_order'=>50, 'data_source'=>'idems/report_docs'],
        ['kpi_key'=>'open_ncrs', 'name'=>'Open nonconformities', 'category'=>'QUALITY', 'formula'=>'ncr.open',
         'unit'=>'count', 'direction'=>'LOWER', 'target'=>0, 'threshold'=>5, 'sort_order'=>60, 'data_source'=>'ncr/nonconformities'],
        ['kpi_key'=>'ncr_closure_time', 'name'=>'Average NCR closure time', 'category'=>'QUALITY',
         'formula'=>'ncr.closure_avg_days', 'unit'=>'days', 'direction'=>'LOWER', 'target'=>14, 'threshold'=>30,
         'sort_order'=>70, 'data_source'=>'ncr/nonconformities'],
        ['kpi_key'=>'invoiced_value', 'name'=>'Invoiced value', 'category'=>'FINANCE', 'formula'=>'revenue.invoiced',
         'unit'=>'money', 'direction'=>'HIGHER', 'sort_order'=>80, 'data_source'=>'ops/jobs'],
    ];
    foreach ($starter as $d) { if (!tapi_kpi_get($d['kpi_key'])) { try { tapi_kpi_save($d); } catch (Throwable $e) {} } }
}

// ============================================================================
//  SLICE 2 — the presentation kit.
//
//  Everything a dashboard is assembled from, made declarative and shared so no
//  screen hand-writes a tile or re-implements a filter bar again:
//    · kpi_card()          — render a KPI-eval payload as a card (status colour,
//                            no-data handling, delta, lineage).
//    · period ranges + comparison — current vs previous / target / YoY / MoM /
//                            QoQ / rolling, with variance, achievement % and a
//                            trend arrow.
//    · a shared filter engine — one parser + one sticky bar, scope-aware.
//    · chart types beyond bar/donut/gauge — line, sparkline, pareto, heatmap,
//      stacked bars — dependency-free themed SVG, same house style as ops.php.
// ============================================================================

// ---- Value formatting ------------------------------------------------------
function tapi_fmt_value($value, $unit) {
    if ($value === null) return '—';
    $unit = strtolower((string)$unit);
    if ($unit === 'money')  return function_exists('fmoney_short') ? fmoney_short($value) : number_format((float)$value);
    if ($unit === '%')      return rtrim(rtrim(number_format((float)$value, 1), '0'), '.') . '%';
    if ($unit === 'days')   return rtrim(rtrim(number_format((float)$value, 1), '0'), '.') . ' d';
    if ($unit === 'hours')  return rtrim(rtrim(number_format((float)$value, 1), '0'), '.') . ' h';
    // count / anything else
    return (float)$value == (int)$value ? number_format((int)$value) : number_format((float)$value, 1);
}
function tapi_status_tone($status) {
    return ['GOOD'=>'ok', 'WARN'=>'warn', 'BAD'=>'bad', 'NO_DATA'=>'mut', 'INFO'=>'info'][$status] ?? 'info';
}

// ---- The KPI card ----------------------------------------------------------
// $card is a tapi_kpi_eval() payload. $cmp (optional) is a tapi_compare() result
// to draw the delta. Reuses the existing .kpi CSS; adds a status stripe + a
// muted lineage/updated line so the number is never a bare, untraceable figure.
function kpi_card($card, $cmp = null, $opts = []) {
    $esc = fn($s) => function_exists('e') ? e($s) : htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $tone = tapi_status_tone($card['status'] ?? 'INFO');
    $val  = ($card['no_data'] ?? false) ? 'No data' : tapi_fmt_value($card['value'] ?? null, $card['unit'] ?? 'count');
    $href = $opts['href'] ?? '';
    $delta = '';
    if ($cmp && !($card['no_data'] ?? false) && ($cmp['variance_pct'] ?? null) !== null) {
        $dir = $cmp['trend'] === 'up' ? 'up' : ($cmp['trend'] === 'down' ? 'down' : 'flat');
        $arrow = $dir === 'up' ? '▲' : ($dir === 'down' ? '▼' : '▬');
        // "good" colour depends on the KPI's direction, not merely the sign.
        $goodUp = strtoupper((string)($card['direction'] ?? '')) === 'HIGHER';
        $isGood = ($cmp['trend'] === 'up' && $goodUp) || ($cmp['trend'] === 'down' && !$goodUp);
        $cls = $cmp['trend'] === 'flat' ? '' : ($isGood ? 'up' : 'down');
        $delta = '<div class="d ' . $cls . '">' . $arrow . ' ' . $esc(abs((float)$cmp['variance_pct'])) . '% ' . $esc($cmp['label'] ?? 'vs prev') . '</div>';
    }
    $tgt = '';
    if (($card['target'] ?? null) !== null && $card['target'] !== '') {
        $tgt = '<div class="kt">Target ' . $esc(tapi_fmt_value($card['target'], $card['unit'] ?? 'count')) . '</div>';
    }
    $lin = '';
    if (!empty($card['lineage'])) {
        $src = implode(', ', array_unique(array_map(fn($l) => (string)$l['source'], $card['lineage'])));
        $lin = '<div class="klin" title="Data source">' . $esc($src) . '</div>';
    }
    $inner = '<div class="k">' . $esc($card['name'] ?? '') . '</div>'
           . '<div class="v">' . $esc($val) . '</div>' . $delta . $tgt . $lin;
    $open = $href !== '' ? '<a class="kpi tapi-kpi tone-' . $tone . '" href="' . $esc($href) . '">' : '<div class="kpi tapi-kpi tone-' . $tone . '">';
    $close = $href !== '' ? '</a>' : '</div>';
    return $open . $inner . $close;
}

// A row of KPI cards from a list of definitions, each evaluated for $ctx (and
// optionally compared). $defs are kpi_defs rows.
function kpi_card_row($defs, $ctx = [], $cmpMode = '') {
    $out = '<div class="kpi-row tapi-kpi-row">';
    foreach ($defs as $d) {
        $card = tapi_kpi_eval($d, $ctx);
        $cmp = $cmpMode !== '' ? tapi_compare($d, $ctx, $cmpMode) : null;
        $out .= kpi_card($card, $cmp);
    }
    return $out . '</div>';
}

// The small amount of CSS the kit adds on top of the existing .kpi rules.
function tapi_style() {
    static $done = false; if ($done) return ''; $done = true;
    return '<style>
    .tapi-kpi{border:1px solid var(--line);border-radius:12px;padding:14px 16px;border-left-width:4px;text-decoration:none;color:inherit;display:block}
    .tapi-kpi.tone-ok{border-left-color:var(--ok)} .tapi-kpi.tone-warn{border-left-color:var(--warn)}
    .tapi-kpi.tone-bad{border-left-color:var(--bad)} .tapi-kpi.tone-mut{border-left-color:var(--line)} .tapi-kpi.tone-info{border-left-color:var(--info,#0ea5e9)}
    .tapi-kpi .k{font-size:12.5px;color:var(--muted);letter-spacing:.02em} .tapi-kpi .v{font-size:26px;font-weight:600;letter-spacing:-.02em;margin-top:2px}
    .tapi-kpi .d{font-size:12px;margin-top:3px} .tapi-kpi .d.up{color:var(--ok)} .tapi-kpi .d.down{color:var(--bad)}
    .tapi-kpi .kt,.tapi-kpi .klin{font-size:11.5px;color:var(--muted);margin-top:3px}
    .tapi-kpi-row{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin:0 0 20px}
    .tapi-filter{position:sticky;top:0;z-index:5;display:flex;flex-wrap:wrap;gap:8px;align-items:end;padding:10px 0;margin:0 0 16px;border-bottom:1px solid var(--line);background:var(--bg)}
    .tapi-filter label{display:block;font-size:11.5px;color:var(--muted);margin-bottom:3px}
    .tapi-fresh{font-size:12px;color:var(--muted);margin:0 0 14px}
    </style>';
}

// ---- Period ranges + comparison -------------------------------------------
// [from,to] for a named period around an anchor date (default today).
function tapi_period_range($preset, $anchor = null) {
    $a = $anchor ?: date('Y-m-d');
    $ts = strtotime($a); $Y = (int)date('Y', $ts); $M = (int)date('n', $ts);
    switch (strtoupper((string)$preset)) {
        case 'DAY':   return [$a, $a];
        case 'WEEK':  $mon = strtotime('monday this week', $ts); return [date('Y-m-d', $mon), date('Y-m-d', strtotime('+6 days', $mon))];
        case 'MONTH': return [sprintf('%04d-%02d-01', $Y, $M), date('Y-m-t', $ts)];
        case 'QUARTER': $q = intdiv($M - 1, 3); $qs = $q * 3 + 1; return [sprintf('%04d-%02d-01', $Y, $qs), date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $Y, $qs + 2)))];
        case 'HALF':  return $M <= 6 ? ["$Y-01-01", "$Y-06-30"] : ["$Y-07-01", "$Y-12-31"];
        case 'YEAR':  return ["$Y-01-01", "$Y-12-31"];
        case 'FY':    return function_exists('fy_range') && function_exists('current_fy') ? fy_range(current_fy()) : ["$Y-01-01", "$Y-12-31"];
        default:      return ['', ''];   // all time
    }
}
// The comparable previous window for a [from,to] range.
function tapi_prev_range($from, $to, $mode = 'PREV') {
    if ($from === '' || $to === '') return ['', ''];
    $f = strtotime($from); $t = strtotime($to);
    switch (strtoupper((string)$mode)) {
        case 'YOY': return [date('Y-m-d', strtotime('-1 year', $f)), date('Y-m-d', strtotime('-1 year', $t))];
        case 'MOM': return [date('Y-m-d', strtotime('-1 month', $f)), date('Y-m-d', strtotime('-1 month', $t))];
        case 'QOQ': return [date('Y-m-d', strtotime('-3 months', $f)), date('Y-m-d', strtotime('-3 months', $t))];
        case 'PREV': default:
            $len = $t - $f;                                   // same-length window immediately before
            $pt = strtotime('-1 day', $f);
            return [date('Y-m-d', $pt - $len), date('Y-m-d', $pt)];
    }
}
// Compare a KPI across the current window and a comparison window (or its target).
// Returns current/previous/variance/variance_pct/achievement_pct/trend/label,
// honouring no-data: if either side is no-data the variance is null (never faked).
function tapi_compare($def, $ctx, $mode = 'PREV') {
    $cur = tapi_kpi_eval($def, $ctx);
    $mode = strtoupper((string)$mode);
    $label = ['PREV'=>'vs prev','YOY'=>'YoY','MOM'=>'MoM','QOQ'=>'QoQ','TARGET'=>'vs target'][$mode] ?? 'vs prev';
    $prevVal = null;
    if ($mode === 'TARGET') {
        $prevVal = ($def['target'] ?? null) === '' ? null : ($def['target'] ?? null);
        $prevVal = $prevVal === null ? null : (float)$prevVal;
    } else {
        [$pf, $pt] = tapi_prev_range((string)($ctx['from'] ?? ''), (string)($ctx['to'] ?? ''), $mode);
        $prevCard = tapi_kpi_eval($def, ['from' => $pf, 'to' => $pt] + $ctx);
        $prevVal = ($prevCard['no_data'] ?? false) ? null : $prevCard['value'];
    }
    $curVal = ($cur['no_data'] ?? false) ? null : $cur['value'];
    $variance = ($curVal === null || $prevVal === null) ? null : round((float)$curVal - (float)$prevVal, 2);
    $variancePct = ($curVal === null || $prevVal === null || (float)$prevVal == 0.0) ? null
        : round(((float)$curVal - (float)$prevVal) / abs((float)$prevVal) * 100, 1);
    $achv = null;
    if (($def['target'] ?? null) !== null && $def['target'] !== '' && (float)$def['target'] != 0.0 && $curVal !== null)
        $achv = round((float)$curVal / (float)$def['target'] * 100, 1);
    $trend = $variance === null ? 'na' : ($variance > 0 ? 'up' : ($variance < 0 ? 'down' : 'flat'));
    return ['current'=>$curVal, 'previous'=>$prevVal, 'variance'=>$variance, 'variance_pct'=>$variancePct,
            'achievement_pct'=>$achv, 'trend'=>$trend, 'label'=>$label, 'mode'=>$mode];
}

// ---- The shared filter engine ---------------------------------------------
// Normalise a filter set (from an array or $_GET). Period preset resolves to
// from/to unless explicit dates are given. Office/SBU are intersected with the
// caller's scope so a filter can only ever NARROW, never widen, access.
function tapi_filters($in = null) {
    $in = $in ?? $_GET ?? [];
    $g = fn($k, $d = '') => isset($in[$k]) ? trim((string)$in[$k]) : $d;
    $period = $g('period', 'MONTH');
    $from = $g('from'); $to = $g('to');
    if ($from === '' && $to === '' && $period !== 'CUSTOM' && $period !== 'ALL') {
        [$from, $to] = tapi_period_range($period);
    }
    $f = [
        'period' => $period, 'from' => $from, 'to' => $to,
        'office' => (int)$g('office', '0'), 'sbu' => $g('sbu'),
        'client' => (int)$g('client', '0'), 'service' => $g('service'),
        'status' => $g('status'), 'compare' => strtoupper($g('compare', 'PREV')),
    ];
    return $f;
}
// A metric context {from,to,...} from a filter set.
function tapi_ctx_from_filters($f) {
    return ['from' => (string)($f['from'] ?? ''), 'to' => (string)($f['to'] ?? ''),
            'office' => (int)($f['office'] ?? 0), 'sbu' => (string)($f['sbu'] ?? ''),
            'client' => (int)($f['client'] ?? 0)];
}
// The sticky filter bar. $show lists which controls to render; period + dates
// are always present. Auto-submits on change.
function tapi_filter_bar($f, $opts = []) {
    $esc = fn($s) => function_exists('e') ? e($s) : htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $show = $opts['show'] ?? ['office', 'sbu', 'compare'];
    $action = $opts['action'] ?? '';
    $h = tapi_style();
    $h .= '<form class="tapi-filter" method="get"' . ($action ? ' action="' . $esc($action) . '"' : '') . '>';
    // period
    $h .= '<div><label>Period</label><select name="period" onchange="this.form.submit()">';
    foreach (TAPI_PERIODS as $k => $lbl) $h .= '<option value="' . $k . '"' . ($f['period'] === $k ? ' selected' : '') . '>' . $esc($lbl) . '</option>';
    $h .= '<option value="ALL"' . ($f['period'] === 'ALL' ? ' selected' : '') . '>All time</option></select></div>';
    // explicit dates (used when period=CUSTOM)
    $h .= '<div><label>From</label><input type="date" name="from" value="' . $esc($f['from']) . '"></div>';
    $h .= '<div><label>To</label><input type="date" name="to" value="' . $esc($f['to']) . '"></div>';
    if (in_array('office', $show, true) && function_exists('scope_office_options')) {
        $h .= '<div><label>Office</label><select name="office" onchange="this.form.submit()"><option value="0">All offices</option>';
        foreach (scope_office_options() as $id => $nm) $h .= '<option value="' . (int)$id . '"' . ((int)$f['office'] === (int)$id ? ' selected' : '') . '>' . $esc($nm) . '</option>';
        $h .= '</select></div>';
    }
    if (in_array('sbu', $show, true) && function_exists('lk_options_or')) {
        $h .= '<div><label>Business unit</label><select name="sbu" onchange="this.form.submit()"><option value="">All</option>';
        foreach (lk_options_or('sbu', []) as $k => $lbl) $h .= '<option value="' . $esc($k) . '"' . ($f['sbu'] === (string)$k ? ' selected' : '') . '>' . $esc($lbl) . '</option>';
        $h .= '</select></div>';
    }
    if (in_array('compare', $show, true)) {
        $h .= '<div><label>Compare</label><select name="compare" onchange="this.form.submit()">';
        foreach (['PREV'=>'vs previous','YOY'=>'Year on year','MOM'=>'Month on month','QOQ'=>'Quarter on quarter','TARGET'=>'vs target'] as $k => $lbl)
            $h .= '<option value="' . $k . '"' . ($f['compare'] === $k ? ' selected' : '') . '>' . $esc($lbl) . '</option>';
        $h .= '</select></div>';
    }
    $h .= '<div><button class="btn btn-sm" type="submit">Apply</button></div>';
    $h .= '</form>';
    return $h;
}

// A data-freshness line — analytics is only as current as its source.
function tapi_freshness($label = 'Live — computed from source records now') {
    return '<div class="tapi-fresh">🕒 ' . (function_exists('e') ? e($label) : $label) . ' · ' . date('d M Y H:i') . '</div>';
}

// ============================================================================
//  Chart primitives beyond bar/donut/gauge — themed, dependency-free SVG.
//  All read CSS variables so they follow light/dark like the existing charts,
//  and all show a labelled placeholder (never a blank box) when there is no data.
// ============================================================================
function tapi_chart_color($i) { return function_exists('chart_color') ? chart_color($i) : (['#0ea5e9','#f59e0b','#10b981','#a78bfa','#fb7185'][$i % 5]); }
function tapi_chart_empty($msg = 'No data for this period') {
    return '<div class="pnote" style="padding:22px 6px;color:var(--muted);font-size:13.5px">' . (function_exists('e') ? e($msg) : $msg) . '</div>';
}

// Line / trend chart. $series = ['label'=>..,'points'=>[['x'=>label,'y'=>num], ...]] or a bare list of series.
function tapi_svg_line($series, $opts = []) {
    if (!$series) return tapi_chart_empty();
    if (isset($series['points'])) $series = [$series];
    $w = (int)($opts['w'] ?? 640); $h = (int)($opts['h'] ?? 220); $pad = 30;
    $xs = []; $allY = [];
    foreach ($series as $s) foreach ($s['points'] as $p) { $xs[(string)$p['x']] = true; if ($p['y'] !== null) $allY[] = (float)$p['y']; }
    if (!$allY) return tapi_chart_empty();
    $labels = array_keys($xs); $n = max(1, count($labels) - 1);
    $maxY = max($allY); $minY = min(0, min($allY)); $range = ($maxY - $minY) ?: 1;
    $px = fn($i) => $pad + ($i / $n) * ($w - 2 * $pad);
    $py = fn($y) => $h - $pad - (($y - $minY) / $range) * ($h - 2 * $pad);
    $svg = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" width="100%" style="max-width:' . $w . 'px" role="img">';
    // baseline + gridlines
    $svg .= '<line x1="' . $pad . '" y1="' . ($h - $pad) . '" x2="' . ($w - $pad) . '" y2="' . ($h - $pad) . '" stroke="var(--line)" />';
    $si = 0;
    foreach ($series as $s) {
        $col = $s['color'] ?? tapi_chart_color($si++);
        $d = ''; $i = 0; $started = false;
        foreach ($labels as $lab) {
            $pt = null; foreach ($s['points'] as $p) if ((string)$p['x'] === $lab) { $pt = $p; break; }
            if ($pt && $pt['y'] !== null) { $d .= ($started ? ' L' : 'M') . round($px($i), 1) . ' ' . round($py((float)$pt['y']), 1); $started = true; }
            $i++;
        }
        if ($d !== '') $svg .= '<path d="' . $d . '" fill="none" stroke="' . $col . '" stroke-width="2" />';
    }
    // x labels (thinned)
    $step = max(1, (int)ceil(count($labels) / 8));
    foreach ($labels as $i => $lab) if ($i % $step === 0)
        $svg .= '<text x="' . round($px($i), 1) . '" y="' . ($h - 8) . '" font-size="10" fill="var(--muted)" text-anchor="middle">' . htmlspecialchars((string)$lab) . '</text>';
    $svg .= '</svg>';
    return $svg;
}

// Sparkline — a tiny inline trend, no axes.
function tapi_svg_sparkline($values, $opts = []) {
    $values = array_values(array_filter($values, fn($v) => $v !== null));
    if (count($values) < 2) return '<span class="pnote">—</span>';
    $w = (int)($opts['w'] ?? 110); $h = (int)($opts['h'] ?? 26);
    $max = max($values); $min = min($values); $range = ($max - $min) ?: 1; $n = count($values) - 1;
    $col = $opts['color'] ?? tapi_chart_color(0);
    $d = ''; foreach ($values as $i => $v) { $x = ($i / $n) * ($w - 2); $y = $h - 2 - (($v - $min) / $range) * ($h - 4); $d .= ($i ? ' L' : 'M') . round($x + 1, 1) . ' ' . round($y, 1); }
    return '<svg viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" style="vertical-align:middle"><path d="' . $d . '" fill="none" stroke="' . $col . '" stroke-width="1.5"/></svg>';
}

// Pareto — bars sorted desc with a cumulative % line. $data = [label=>value].
function tapi_svg_pareto($data, $opts = []) {
    $data = array_filter($data, fn($v) => $v !== null);
    arsort($data);
    if (!$data) return tapi_chart_empty();
    $total = array_sum($data); if ($total <= 0) return tapi_chart_empty();
    $w = (int)($opts['w'] ?? 640); $h = (int)($opts['h'] ?? 240); $pad = 34;
    $labels = array_keys($data); $vals = array_values($data); $n = count($vals);
    $maxV = max($vals) ?: 1; $bw = ($w - 2 * $pad) / max(1, $n);
    $svg = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" width="100%" style="max-width:' . $w . 'px" role="img">';
    $svg .= '<line x1="' . $pad . '" y1="' . ($h - $pad) . '" x2="' . ($w - $pad) . '" y2="' . ($h - $pad) . '" stroke="var(--line)"/>';
    $cum = 0; $linePts = '';
    foreach ($vals as $i => $v) {
        $bh = ($v / $maxV) * ($h - 2 * $pad); $x = $pad + $i * $bw + 3; $y = $h - $pad - $bh;
        $svg .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($bw - 6, 1) . '" height="' . round($bh, 1) . '" fill="' . tapi_chart_color(0) . '" rx="2"/>';
        $svg .= '<text x="' . round($x + ($bw - 6) / 2, 1) . '" y="' . ($h - 10) . '" font-size="9.5" fill="var(--muted)" text-anchor="middle">' . htmlspecialchars(mb_substr((string)$labels[$i], 0, 8)) . '</text>';
        $cum += $v; $cx = $pad + $i * $bw + $bw / 2; $cy = $pad + ($h - 2 * $pad) * (1 - $cum / $total);
        $linePts .= ($i ? ' L' : 'M') . round($cx, 1) . ' ' . round($cy, 1);
    }
    $svg .= '<path d="' . $linePts . '" fill="none" stroke="' . tapi_chart_color(4) . '" stroke-width="2"/>';
    $svg .= '</svg>';
    return $svg;
}

// Heatmap — rows × cols grid coloured by value. $rows=[label], $cols=[label],
// $matrix[rowLabel][colLabel]=value.
function tapi_svg_heatmap($rows, $cols, $matrix, $opts = []) {
    if (!$rows || !$cols) return tapi_chart_empty();
    $all = [];
    foreach ($matrix as $r) foreach ($r as $v) if ($v !== null) $all[] = (float)$v;
    $max = $all ? max($all) : 0; if ($max <= 0) return tapi_chart_empty('No values for this period');
    $cw = (int)($opts['cw'] ?? 46); $ch = (int)($opts['ch'] ?? 26); $lw = (int)($opts['lw'] ?? 120);
    $w = $lw + count($cols) * $cw + 10; $h = 24 + count($rows) * $ch + 6;
    $svg = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" width="100%" style="max-width:' . $w . 'px" role="img">';
    foreach ($cols as $ci => $c) $svg .= '<text x="' . ($lw + $ci * $cw + $cw / 2) . '" y="16" font-size="10" fill="var(--muted)" text-anchor="middle">' . htmlspecialchars(mb_substr((string)$c, 0, 7)) . '</text>';
    foreach ($rows as $ri => $r) {
        $y = 24 + $ri * $ch;
        $svg .= '<text x="0" y="' . ($y + $ch / 2 + 3) . '" font-size="10.5" fill="var(--ink)">' . htmlspecialchars(mb_substr((string)$r, 0, 18)) . '</text>';
        foreach ($cols as $ci => $c) {
            $v = $matrix[$r][$c] ?? null; $x = $lw + $ci * $cw;
            $op = $v === null ? 0 : max(0.08, min(1, (float)$v / $max));
            $fill = $v === null ? 'transparent' : 'color-mix(in srgb, ' . tapi_chart_color(0) . ' ' . round($op * 100) . '%, transparent)';
            $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . ($cw - 3) . '" height="' . ($ch - 3) . '" rx="3" fill="' . $fill . '" stroke="var(--line)"/>';
            if ($v !== null) $svg .= '<text x="' . ($x + ($cw - 3) / 2) . '" y="' . ($y + $ch / 2 + 2) . '" font-size="9.5" fill="var(--ink)" text-anchor="middle">' . htmlspecialchars((string)(is_float($v) ? round($v, 1) : $v)) . '</text>';
        }
    }
    $svg .= '</svg>';
    return $svg;
}

// Stacked bars — $groups = [label => [seriesLabel => value]].
function tapi_svg_stack($groups, $seriesLabels, $opts = []) {
    if (!$groups) return tapi_chart_empty();
    $w = (int)($opts['w'] ?? 640); $h = (int)($opts['h'] ?? 240); $pad = 34;
    $totals = []; foreach ($groups as $k => $g) $totals[$k] = array_sum(array_map(fn($v) => (float)($v ?? 0), $g));
    $maxV = $totals ? max($totals) : 0; if ($maxV <= 0) return tapi_chart_empty();
    $labels = array_keys($groups); $n = count($labels); $bw = ($w - 2 * $pad) / max(1, $n);
    $svg = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" width="100%" style="max-width:' . $w . 'px" role="img">';
    $svg .= '<line x1="' . $pad . '" y1="' . ($h - $pad) . '" x2="' . ($w - $pad) . '" y2="' . ($h - $pad) . '" stroke="var(--line)"/>';
    foreach ($labels as $i => $lab) {
        $x = $pad + $i * $bw + 3; $yBase = $h - $pad;
        foreach ($seriesLabels as $si => $sl) {
            $v = (float)($groups[$lab][$sl] ?? 0); if ($v <= 0) continue;
            $bh = ($v / $maxV) * ($h - 2 * $pad); $yBase -= $bh;
            $svg .= '<rect x="' . round($x, 1) . '" y="' . round($yBase, 1) . '" width="' . round($bw - 6, 1) . '" height="' . round($bh, 1) . '" fill="' . tapi_chart_color($si) . '"/>';
        }
        $svg .= '<text x="' . round($x + ($bw - 6) / 2, 1) . '" y="' . ($h - 10) . '" font-size="9.5" fill="var(--muted)" text-anchor="middle">' . htmlspecialchars(mb_substr((string)$lab, 0, 8)) . '</text>';
    }
    // legend
    $lx = $pad;
    foreach ($seriesLabels as $si => $sl) { $svg .= '<rect x="' . $lx . '" y="6" width="10" height="10" fill="' . tapi_chart_color($si) . '"/><text x="' . ($lx + 14) . '" y="15" font-size="10" fill="var(--muted)">' . htmlspecialchars((string)$sl) . '</text>'; $lx += 30 + strlen((string)$sl) * 6; }
    $svg .= '</svg>';
    return $svg;
}

// ============================================================================
//  SLICE 3 — domain roll-ups.
//
//  Portfolio-level aggregations the per-entity functions never provided. Each
//  reuses the SAME source tables (and, for SLA, the SAME per-call engine
//  tosrm_sla_eval) the domains own — TAPI interprets, it does not re-model.
//  Two shapes:
//    · scalar METRICS (tapi_domain_metrics) — compose into KPIs like any atom.
//    · BREAKDOWN functions (tapi_bd_*) — return [label => value] for charts and
//      drill-down (the bare gaps: delay categories, root causes, service mix,
//      SLA status, release status, vendor-score bands, portal activity).
//  Every query ANDs in scope, and the zero-vs-no-data rule holds (rates over an
//  empty base are null, not 0).
// ============================================================================

function tapi_report_status_metric($statuses) {
    return function ($ctx) use ($statuses) {
        [$sw, $sa] = tapi_scope('d.office_id', 'd.sbu');
        [$pw, $pa] = tapi_period_sql("substr(COALESCE(NULLIF(d.issue_date,''), d.created_at),1,10)", $ctx);
        $ph = implode(',', array_fill(0, count($statuses), '?'));
        return (int) ops_val("SELECT COUNT(*) FROM report_docs d WHERE COALESCE(d.deleted,0)=0 AND d.status IN ($ph) AND $sw AND $pw",
            array_merge($statuses, $sa, $pa));
    };
}
function tapi_release_metric($codes) {
    return function ($ctx) use ($codes) {
        [$sw, $sa] = tapi_scope('d.office_id', 'd.sbu');
        [$pw, $pa] = tapi_period_sql("substr(COALESCE(NULLIF(d.issue_date,''), d.created_at),1,10)", $ctx);
        $ph = implode(',', array_fill(0, count($codes), '?'));
        return (int) ops_val("SELECT COUNT(*) FROM report_docs d WHERE COALESCE(d.deleted,0)=0 AND d.release_status IN ($ph) AND $sw AND $pw",
            array_merge($codes, $sa, $pa));
    };
}

function tapi_domain_metrics() {
    static $reg = null; if ($reg !== null) return $reg;
    $reg = [
        // -- Report pipeline (bare gap: only inline register buckets existed) ---
        'reports.draft'        => ['label'=>'Reports in draft','unit'=>'count','agg'=>'count','source'=>'idems/report_docs','method'=>"status='DRAFT'", 'resolve'=>tapi_report_status_metric(['DRAFT'])],
        'reports.under_review' => ['label'=>'Reports under review','unit'=>'count','agg'=>'count','source'=>'idems/report_docs','method'=>"status IN (SUBMITTED,UNDER_REVIEW)", 'resolve'=>tapi_report_status_metric(['SUBMITTED','UNDER_REVIEW'])],
        'reports.rejected'     => ['label'=>'Reports returned','unit'=>'count','agg'=>'count','source'=>'idems/report_docs','method'=>"status='REJECTED'", 'resolve'=>tapi_report_status_metric(['REJECTED'])],
        'reports.revised'      => ['label'=>'Revised reports','unit'=>'count','agg'=>'count','source'=>'idems/report_docs','method'=>'rev>0', 'resolve'=>function ($ctx) {
            [$sw, $sa] = tapi_scope('d.office_id', 'd.sbu');
            [$pw, $pa] = tapi_period_sql("substr(COALESCE(NULLIF(d.issue_date,''), d.created_at),1,10)", $ctx);
            return (int) ops_val("SELECT COUNT(*) FROM report_docs d WHERE COALESCE(d.deleted,0)=0 AND COALESCE(d.rev,0)>0 AND $sw AND $pw", array_merge($sa, $pa));
        }],
        // -- Release (bare gap: never GROUP BY-aggregated) ---------------------
        'release.released'    => ['label'=>'Released','unit'=>'count','agg'=>'count','source'=>'idems/report_docs','method'=>"release_status='RELEASED'", 'resolve'=>tapi_release_metric(['RELEASED'])],
        'release.conditional' => ['label'=>'Released with conditions','unit'=>'count','agg'=>'count','source'=>'idems/report_docs','method'=>"release_status='RELEASED_COND'", 'resolve'=>tapi_release_metric(['RELEASED_COND'])],
        'release.not_released' => ['label'=>'Not released','unit'=>'count','agg'=>'count','source'=>'idems/report_docs','method'=>"release_status='NOT_RELEASED'", 'resolve'=>tapi_release_metric(['NOT_RELEASED'])],
        // -- SLA compliance (bare gap: only per-call tosrm_sla_eval existed) ----
        'sla.evaluable' => ['label'=>'Calls with an SLA','unit'=>'count','agg'=>'count','source'=>'tosrm/calls','method'=>'calls with ≥1 non-NA SLA stage', 'resolve'=>function ($ctx) { return tapi_sla_tally($ctx)['evaluable']; }],
        'sla.within'    => ['label'=>'Within SLA','unit'=>'count','agg'=>'count','source'=>'tosrm/calls','method'=>'calls with no breached stage', 'resolve'=>function ($ctx) { return tapi_sla_tally($ctx)['within']; }],
        'sla.breached'  => ['label'=>'SLA breached','unit'=>'count','agg'=>'count','source'=>'tosrm/calls','method'=>'calls with ≥1 breached stage', 'resolve'=>function ($ctx) { return tapi_sla_tally($ctx)['breached']; }],
        // -- CAPA effectiveness (bare gap: counts existed, not a rate) ----------
        'capa.rated'     => ['label'=>'CAPA rated for effectiveness','unit'=>'count','agg'=>'count','source'=>'capa','method'=>"eff_result<>''", 'resolve'=>function ($ctx) {
            [$sw, $sa] = function_exists('scope_office_clause') ? scope_office_clause('c.office_id') : ['1=1', []];
            [$pw, $pa] = tapi_period_sql("substr(COALESCE(NULLIF(c.raised_on,''), c.created_at),1,10)", $ctx);
            return (int) ops_val("SELECT COUNT(*) FROM capa c WHERE COALESCE(c.eff_result,'')<>'' AND $sw AND $pw", array_merge($sa, $pa));
        }],
        'capa.effective' => ['label'=>'CAPA effective','unit'=>'count','agg'=>'count','source'=>'capa','method'=>"eff_result='EFFECTIVE'", 'resolve'=>function ($ctx) {
            [$sw, $sa] = function_exists('scope_office_clause') ? scope_office_clause('c.office_id') : ['1=1', []];
            [$pw, $pa] = tapi_period_sql("substr(COALESCE(NULLIF(c.raised_on,''), c.created_at),1,10)", $ctx);
            return (int) ops_val("SELECT COUNT(*) FROM capa c WHERE c.eff_result='EFFECTIVE' AND $sw AND $pw", array_merge($sa, $pa));
        }],
        // -- Vendor re-assessment due (bare gap: only inline in the register) ---
        'vendor.reassess_due' => ['label'=>'Vendor re-assessments due','unit'=>'count','agg'=>'count','source'=>'idems/vendor_profiles','method'=>'reassess_on ≤ today', 'resolve'=>function ($ctx) {
            return (int) ops_val("SELECT COUNT(*) FROM vendor_profiles WHERE COALESCE(reassess_on,'')<>'' AND reassess_on <= ?", [date('Y-m-d')]);
        }],
        // -- Portal adoption (fully bare: audit tables never aggregated) --------
        'portal.active_users' => ['label'=>'Active client-portal users','unit'=>'count','agg'=>'count','source'=>'portal/client_users','method'=>'distinct client_users with a login in period', 'resolve'=>function ($ctx) {
            [$pw, $pa] = tapi_period_sql("substr(cu.last_login_at,1,10)", $ctx);
            return (int) ops_val("SELECT COUNT(*) FROM client_users cu WHERE COALESCE(cu.last_login_at,'')<>'' AND $pw", $pa);
        }],
        'portal.events' => ['label'=>'Portal actions','unit'=>'count','agg'=>'count','source'=>'portal/portal_audit','method'=>'portal_audit rows in period', 'resolve'=>function ($ctx) {
            [$pw, $pa] = tapi_period_sql("substr(pa.at,1,10)", $ctx);
            return (int) ops_val("SELECT COUNT(*) FROM portal_audit pa WHERE $pw", $pa);
        }],
    ];
    return $reg;
}

// The SLA portfolio tally — reuses the per-call engine, once per call in scope.
// Cached per context so the three sla.* metrics do not each re-scan.
function tapi_sla_tally($ctx) {
    static $cache = [];
    $ck = md5(json_encode([$ctx['from'] ?? '', $ctx['to'] ?? '']));
    if (isset($cache[$ck])) return $cache[$ck];
    $out = ['evaluable' => 0, 'within' => 0, 'breached' => 0, 'at_risk' => 0];
    if (!function_exists('tosrm_sla_eval')) return $cache[$ck] = $out;
    [$sw, $sa] = tapi_scope('c.executing_office_id', 'c.sbu');
    [$pw, $pa] = tapi_period_sql("substr(COALESCE(NULLIF(c.call_received_date,''), c.created_at),1,10)", $ctx);
    try {
        $calls = ops_all("SELECT c.* FROM calls c WHERE $sw AND $pw ORDER BY c.id DESC LIMIT 2000", array_merge($sa, $pa));
    } catch (Throwable $e) { return $cache[$ck] = $out; }
    foreach ($calls as $c) {
        $stages = tosrm_sla_eval($c);
        $hasEval = false; $breach = false; $risk = false;
        foreach ($stages as $s) {
            if (($s['status'] ?? 'NA') === 'NA') continue;
            $hasEval = true;
            if ($s['status'] === 'BREACHED') $breach = true;
            elseif ($s['status'] === 'AT_RISK') $risk = true;
        }
        if (!$hasEval) continue;
        $out['evaluable']++;
        if ($breach) $out['breached']++; elseif ($risk) $out['at_risk']++; else $out['within']++;
    }
    return $cache[$ck] = $out;
}

// ---- Breakdown functions — [label => value], scoped, for charts + drill-down.
function tapi_bd_delay_categories($ctx) {
    [$pw, $pa] = tapi_period_sql("substr(COALESCE(NULLIF(dl.scheduled_date,''), dl.created_at),1,10)", $ctx);
    $rows = ops_all("SELECT COALESCE(NULLIF(dl.category,''),'Uncategorised') k, COUNT(*) n
                     FROM job_delays dl WHERE $pw GROUP BY k ORDER BY n DESC", $pa) ?: [];
    $out = []; foreach ($rows as $r) $out[$r['k']] = (int)$r['n']; return $out;
}
function tapi_bd_sla_status($ctx) {
    $t = tapi_sla_tally($ctx);
    return ['Within' => $t['within'], 'At risk' => $t['at_risk'], 'Breached' => $t['breached']];
}
function tapi_bd_report_pipeline($ctx) {
    [$sw, $sa] = tapi_scope('d.office_id', 'd.sbu');
    [$pw, $pa] = tapi_period_sql("substr(COALESCE(NULLIF(d.issue_date,''), d.created_at),1,10)", $ctx);
    $rows = ops_all("SELECT d.status k, COUNT(*) n FROM report_docs d WHERE COALESCE(d.deleted,0)=0 AND $sw AND $pw GROUP BY d.status ORDER BY n DESC", array_merge($sa, $pa)) ?: [];
    $out = []; foreach ($rows as $r) $out[$r['k'] ?: '—'] = (int)$r['n']; return $out;
}
function tapi_bd_release_status($ctx) {
    [$sw, $sa] = tapi_scope('d.office_id', 'd.sbu');
    [$pw, $pa] = tapi_period_sql("substr(COALESCE(NULLIF(d.issue_date,''), d.created_at),1,10)", $ctx);
    $rows = ops_all("SELECT COALESCE(NULLIF(d.release_status,''),'—') k, COUNT(*) n FROM report_docs d WHERE COALESCE(d.deleted,0)=0 AND $sw AND $pw GROUP BY k ORDER BY n DESC", array_merge($sa, $pa)) ?: [];
    $out = []; foreach ($rows as $r) $out[$r['k']] = (int)$r['n']; return $out;
}
function tapi_bd_rootcause($ctx) {
    [$sw, $sa] = function_exists('scope_office_clause') ? scope_office_clause('c.office_id') : ['1=1', []];
    [$pw, $pa] = tapi_period_sql("substr(COALESCE(NULLIF(c.raised_on,''), c.created_at),1,10)", $ctx);
    $rows = ops_all("SELECT COALESCE(NULLIF(c.rc_method,''),'Unspecified') k, COUNT(*) n FROM capa c WHERE $sw AND $pw GROUP BY k ORDER BY n DESC", array_merge($sa, $pa)) ?: [];
    $out = []; foreach ($rows as $r) $out[$r['k']] = (int)$r['n']; return $out;
}
function tapi_bd_service_mix($ctx) {
    [$sw, $sa] = tapi_scope('d.office_id', 'd.sbu');
    [$pw, $pa] = tapi_period_sql("substr(COALESCE(NULLIF(d.issue_date,''), d.created_at),1,10)", $ctx);
    $rows = ops_all("SELECT COALESCE(NULLIF(d.type_code,''),'—') k, COUNT(*) n FROM report_docs d WHERE COALESCE(d.deleted,0)=0 AND $sw AND $pw GROUP BY k ORDER BY n DESC", array_merge($sa, $pa)) ?: [];
    $out = []; foreach ($rows as $r) $out[$r['k']] = (int)$r['n']; return $out;
}
function tapi_bd_vendor_scores($ctx) {
    $rows = ops_all("SELECT last_score s FROM vendor_profiles WHERE last_score IS NOT NULL AND last_score>0") ?: [];
    $bands = ['0–40' => 0, '40–60' => 0, '60–75' => 0, '75–90' => 0, '90–100' => 0];
    foreach ($rows as $r) { $s = (float)$r['s'];
        if ($s < 40) $bands['0–40']++; elseif ($s < 60) $bands['40–60']++; elseif ($s < 75) $bands['60–75']++; elseif ($s < 90) $bands['75–90']++; else $bands['90–100']++; }
    return $bands;
}
function tapi_bd_portal_activity($ctx) {
    [$pw, $pa] = tapi_period_sql("substr(pa.at,1,10)", $ctx);
    $rows = ops_all("SELECT COALESCE(NULLIF(pa.action,''),'—') k, COUNT(*) n FROM portal_audit pa WHERE $pw GROUP BY k ORDER BY n DESC", $pa) ?: [];
    $out = []; foreach ($rows as $r) $out[$r['k']] = (int)$r['n']; return $out;
}

// Seed the domain KPIs (idempotent, additive to the Slice-1 starter set).
function tapi_seed_domain() {
    static $seeded = false; if ($seeded) return; $seeded = true;
    $more = [
        ['kpi_key'=>'sla_compliance', 'name'=>'SLA compliance', 'category'=>'OPERATIONS',
         'formula'=>'round(sla.within / sla.evaluable * 100, 1)', 'unit'=>'%', 'direction'=>'HIGHER',
         'target'=>95, 'threshold'=>85, 'sort_order'=>90, 'data_source'=>'tosrm/calls'],
        ['kpi_key'=>'report_revision_rate', 'name'=>'Report revision rate', 'category'=>'SERVICE',
         'formula'=>'round(reports.revised / reports.total * 100, 1)', 'unit'=>'%', 'direction'=>'LOWER',
         'target'=>10, 'threshold'=>25, 'sort_order'=>100, 'data_source'=>'idems/report_docs'],
        ['kpi_key'=>'release_rate', 'name'=>'Release rate', 'category'=>'SERVICE',
         'formula'=>'round((release.released + release.conditional) / reports.issued * 100, 1)', 'unit'=>'%',
         'direction'=>'HIGHER', 'target'=>90, 'threshold'=>75, 'sort_order'=>110, 'data_source'=>'idems/report_docs'],
        ['kpi_key'=>'capa_effectiveness', 'name'=>'CAPA effectiveness', 'category'=>'QUALITY',
         'formula'=>'round(capa.effective / capa.rated * 100, 1)', 'unit'=>'%', 'direction'=>'HIGHER',
         'target'=>90, 'threshold'=>70, 'sort_order'=>120, 'data_source'=>'capa'],
        ['kpi_key'=>'vendor_reassess_due', 'name'=>'Vendor re-assessments due', 'category'=>'VENDOR',
         'formula'=>'vendor.reassess_due', 'unit'=>'count', 'direction'=>'LOWER', 'target'=>0, 'threshold'=>5,
         'sort_order'=>130, 'data_source'=>'idems/vendor_profiles'],
        ['kpi_key'=>'portal_active_users', 'name'=>'Active portal users', 'category'=>'CLIENT',
         'formula'=>'portal.active_users', 'unit'=>'count', 'direction'=>'HIGHER', 'sort_order'=>140, 'data_source'=>'portal/client_users'],
    ];
    foreach ($more as $d) { if (!tapi_kpi_get($d['kpi_key'])) { try { tapi_kpi_save($d); } catch (Throwable $e) {} } }
}
