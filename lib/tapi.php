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
