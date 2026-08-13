<?php
// ============================================================================
//  TAPI Slice 5 — targets, the management scorecard, and threshold alerts.
//
//  Three things management asks of an analytics layer once the numbers exist:
//    · targets that can differ by branch / business unit / period, resolved
//      most-specific-first, with a live target-vs-actual read;
//    · a configurable weighted scorecard that is NEVER an opaque "82" — every
//      category shows its weight, its score and its contribution;
//    · rule-based alerts (SLA breach, backlog, utilisation, …) delivered
//      through the platform's existing cron + email_log, with a fired-today
//      debounce so nobody is spammed.
//
//  Plus the guardrails the spec is emphatic about: small-sample suppression,
//  a correlation-≠-causation caveat, and performance-fairness context — so
//  analytics informs judgement rather than replacing it or misleading it.
// ============================================================================

const TAPI_ALERT_OPS = ['LT' => 'is below', 'LTE' => 'is at or below', 'GT' => 'is above', 'GTE' => 'is at or above', 'EQ' => 'equals'];
const TAPI_SEVERITY  = ['INFO' => 'Info', 'WARN' => 'Warning', 'HIGH' => 'High', 'CRITICAL' => 'Critical'];

function tapi_score_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = pk_clause();
    // Per-scope / per-period target overrides. A blank office/sbu/period means
    // "applies to all"; resolution picks the most specific matching row, else
    // falls back to the KPI definition's own target.
    db()->exec("CREATE TABLE IF NOT EXISTS kpi_targets (
        id $pk, kpi_key VARCHAR(60) DEFAULT '', office_id INT DEFAULT 0, sbu VARCHAR(20) DEFAULT '',
        period_key VARCHAR(16) DEFAULT '', target DECIMAL(16,4) NULL, threshold DECIMAL(16,4) NULL,
        note VARCHAR(255) DEFAULT '', created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // The management scorecard: categories, each weighted, each fed by a KPI.
    db()->exec("CREATE TABLE IF NOT EXISTS scorecards (
        id $pk, code VARCHAR(40) DEFAULT '', name VARCHAR(160) DEFAULT '', description VARCHAR(500) DEFAULT '',
        active INT DEFAULT 1, created_at VARCHAR(30) DEFAULT '')");
    db()->exec("CREATE TABLE IF NOT EXISTS scorecard_items (
        id $pk, scorecard_id INT DEFAULT 0, category VARCHAR(40) DEFAULT '', kpi_key VARCHAR(60) DEFAULT '',
        weight DECIMAL(8,2) DEFAULT 1, sort_order INT DEFAULT 0)");
    // Threshold-alert rules.
    db()->exec("CREATE TABLE IF NOT EXISTS kpi_alerts (
        id $pk, name VARCHAR(160) DEFAULT '', kpi_key VARCHAR(60) DEFAULT '', op VARCHAR(4) DEFAULT 'LT',
        threshold DECIMAL(16,4) NULL, severity VARCHAR(12) DEFAULT 'WARN', recipients VARCHAR(500) DEFAULT '',
        scope_office INT DEFAULT 0, enabled INT DEFAULT 1, last_fired_at VARCHAR(30) DEFAULT '',
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    tapi_seed_scorecard();
}

// ---- Targets ---------------------------------------------------------------
// The effective [target, threshold] for a KPI in a context: the most specific
// override that matches, else the KPI definition's own target/threshold.
function tapi_target_effective($def, $ctx = []) {
    $office = (int)($ctx['office'] ?? 0); $sbu = (string)($ctx['sbu'] ?? '');
    $period = (string)($ctx['period_key'] ?? '');
    $rows = ops_all("SELECT * FROM kpi_targets WHERE kpi_key=?", [(string)$def['kpi_key']]) ?: [];
    $best = null; $bestScore = -1;
    foreach ($rows as $r) {
        // must not contradict the requested scope
        if ((int)$r['office_id'] !== 0 && (int)$r['office_id'] !== $office) continue;
        if ((string)$r['sbu'] !== '' && (string)$r['sbu'] !== $sbu) continue;
        if ((string)$r['period_key'] !== '' && (string)$r['period_key'] !== $period) continue;
        $spec = ((int)$r['office_id'] !== 0 ? 4 : 0) + ((string)$r['sbu'] !== '' ? 2 : 0) + ((string)$r['period_key'] !== '' ? 1 : 0);
        if ($spec > $bestScore) { $bestScore = $spec; $best = $r; }
    }
    if ($best) return [$best['target'] === null ? null : (float)$best['target'], $best['threshold'] === null ? null : (float)$best['threshold']];
    return [($def['target'] ?? null) === '' ? null : ($def['target'] === null ? null : (float)$def['target']),
            ($def['threshold'] ?? null) === '' ? null : ($def['threshold'] === null ? null : (float)$def['threshold'])];
}
function tapi_target_set($d) {
    db()->prepare("INSERT INTO kpi_targets (kpi_key,office_id,sbu,period_key,target,threshold,note,created_by,created_at)
        VALUES (?,?,?,?,?,?,?,?,?)")->execute([
        (string)$d['kpi_key'], (int)($d['office_id'] ?? 0), (string)($d['sbu'] ?? ''), (string)($d['period_key'] ?? ''),
        ($d['target'] ?? '') === '' ? null : $d['target'], ($d['threshold'] ?? '') === '' ? null : $d['threshold'],
        (string)($d['note'] ?? ''), function_exists('current_user') ? (string)(user_name(current_user()) ?? '') : '', date('c')]);
    return (int)db()->lastInsertId();
}
// Target vs actual for a KPI + context — the row §56 asks for on every target.
function tapi_target_vs_actual($def, $ctx = []) {
    $card = tapi_kpi_eval($def, $ctx);
    [$target, $threshold] = tapi_target_effective($def, $ctx);
    $actual = ($card['no_data'] ?? false) ? null : $card['value'];
    $variance = ($actual === null || $target === null) ? null : round((float)$actual - (float)$target, 2);
    $achv = ($actual === null || $target === null || (float)$target == 0.0) ? null : round((float)$actual / (float)$target * 100, 1);
    // status re-judged against the effective (possibly overridden) target
    $status = tapi_status($actual, ['direction' => $def['direction'] ?? 'INFO', 'target' => $target, 'threshold' => $threshold], $card['no_data'] ?? false);
    return ['name' => $def['name'], 'unit' => $def['unit'], 'actual' => $actual, 'target' => $target,
            'variance' => $variance, 'achievement_pct' => $achv, 'status' => $status, 'no_data' => $card['no_data'] ?? false];
}

// ---- The management scorecard ---------------------------------------------
function tapi_scorecards() { return ops_all("SELECT * FROM scorecards WHERE active=1 ORDER BY id") ?: []; }
function tapi_scorecard_items($id) { return ops_all("SELECT * FROM scorecard_items WHERE scorecard_id=? ORDER BY sort_order, id", [(int)$id]) ?: []; }

// Normalise a KPI to a 0–100 score for scoring: achievement vs its effective
// target, honouring direction. No target or no-data → not scored (excluded, and
// its weight is redistributed) so the total is never faked from missing pieces.
function tapi_kpi_score($def, $ctx) {
    $card = tapi_kpi_eval($def, $ctx);
    if ($card['no_data'] ?? false) return [null, $card];
    [$target] = tapi_target_effective($def, $ctx);
    if ($target === null || (float)$target == 0.0) return [null, $card];
    $v = (float)$card['value']; $dir = strtoupper((string)($def['direction'] ?? 'INFO'));
    if ($dir === 'HIGHER') $s = $v / $target * 100;
    elseif ($dir === 'LOWER') $s = $v <= 0 ? 100 : $target / $v * 100;
    else return [null, $card];   // RANGE/TARGET/INFO are not linearly scorable here
    return [max(0.0, min(100.0, round($s, 1))), $card];
}

// Evaluate a scorecard: per-category rows (weight, value, score, contribution)
// and a transparent total. NEVER just a number.
function tapi_scorecard_eval($id, $ctx = []) {
    $defsByKey = []; foreach (tapi_kpi_defs(false) as $d) $defsByKey[$d['kpi_key']] = $d;
    $items = tapi_scorecard_items($id);
    $rows = []; $sumW = 0.0; $wScore = 0.0;
    foreach ($items as $it) {
        $def = $defsByKey[$it['kpi_key']] ?? null;
        if (!$def) continue;
        [$score, $card] = tapi_kpi_score($def, $ctx);
        $rows[] = ['category' => $it['category'], 'kpi' => $def['name'], 'kpi_key' => $it['kpi_key'],
                   'weight' => (float)$it['weight'], 'value' => ($card['no_data'] ?? false) ? null : $card['value'],
                   'unit' => $def['unit'], 'score' => $score, 'no_data' => $card['no_data'] ?? false];
        if ($score !== null) { $sumW += (float)$it['weight']; $wScore += (float)$it['weight'] * $score; }
    }
    $total = $sumW > 0 ? round($wScore / $sumW, 1) : null;   // null = not enough data to score
    // contribution of each scored category to the total
    foreach ($rows as &$r) $r['contribution'] = ($r['score'] !== null && $sumW > 0) ? round($r['weight'] * $r['score'] / $sumW, 1) : null;
    unset($r);
    return ['rows' => $rows, 'total' => $total, 'scored_weight' => $sumW, 'excluded' => count(array_filter($rows, fn($r) => $r['score'] === null))];
}

function tapi_seed_scorecard() {
    static $seeded = false; if ($seeded) return; $seeded = true;
    if (ops_val("SELECT COUNT(*) FROM scorecards")) return;
    db()->prepare("INSERT INTO scorecards (code,name,description,active,created_at) VALUES ('MGMT','Management scorecard','A weighted view of operations, service and quality — every category shows its own contribution.',1,?)")->execute([date('c')]);
    $sid = (int)db()->lastInsertId();
    $items = [
        ['Operations', 'job_closure_rate', 30], ['Operations', 'sla_compliance', 20],
        ['Service', 'report_issue_rate', 15], ['Service', 'report_tat', 10],
        ['Quality', 'capa_effectiveness', 15], ['Quality', 'open_ncrs', 10],
    ];
    $i = 0;
    foreach ($items as [$cat, $kpi, $w])
        db()->prepare("INSERT INTO scorecard_items (scorecard_id,category,kpi_key,weight,sort_order) VALUES (?,?,?,?,?)")->execute([$sid, $cat, $kpi, $w, $i++]);
}

// ---- Threshold alerts ------------------------------------------------------
function tapi_alerts($enabledOnly = false) {
    $w = $enabledOnly ? "WHERE enabled=1" : '';
    return ops_all("SELECT * FROM kpi_alerts $w ORDER BY id") ?: [];
}
function tapi_alert_save($d) {
    if (!empty($d['id'])) {
        db()->prepare("UPDATE kpi_alerts SET name=?, kpi_key=?, op=?, threshold=?, severity=?, recipients=?, scope_office=?, enabled=? WHERE id=?")
            ->execute([$d['name'], $d['kpi_key'], $d['op'], ($d['threshold'] ?? '') === '' ? null : $d['threshold'], $d['severity'] ?? 'WARN',
                $d['recipients'] ?? '', (int)($d['scope_office'] ?? 0), !empty($d['enabled']) ? 1 : 0, (int)$d['id']]);
        return (int)$d['id'];
    }
    db()->prepare("INSERT INTO kpi_alerts (name,kpi_key,op,threshold,severity,recipients,scope_office,enabled,created_by,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?)")->execute([$d['name'], $d['kpi_key'], $d['op'], ($d['threshold'] ?? '') === '' ? null : $d['threshold'],
        $d['severity'] ?? 'WARN', $d['recipients'] ?? '', (int)($d['scope_office'] ?? 0), !empty($d['enabled']) ? 1 : 0,
        function_exists('current_user') ? (string)(user_name(current_user()) ?? '') : '', date('c')]);
    return (int)db()->lastInsertId();
}
function tapi_alert_test($op, $value, $threshold) {
    if ($value === null || $threshold === null) return false;   // no-data never fires (§92)
    $v = (float)$value; $t = (float)$threshold;
    switch ($op) { case 'LT': return $v < $t; case 'LTE': return $v <= $t; case 'GT': return $v > $t; case 'GTE': return $v >= $t; case 'EQ': return $v == $t; }
    return false;
}
// Evaluate every enabled alert against the current period; returns the firing set.
function tapi_alerts_eval($ctx = null) {
    $ctx = $ctx ?? tapi_ctx_from_filters(tapi_filters(['period' => 'MONTH']));
    $out = [];
    foreach (tapi_alerts(true) as $a) {
        $def = tapi_kpi_get($a['kpi_key']); if (!$def) continue;
        $c = $ctx; if ((int)$a['scope_office'] > 0) $c['office'] = (int)$a['scope_office'];
        $card = tapi_kpi_eval($def, $c);
        $val = ($card['no_data'] ?? false) ? null : $card['value'];
        if (tapi_alert_test($a['op'], $val, $a['threshold'])) {
            $out[] = ['alert' => $a, 'value' => $val, 'unit' => $def['unit'], 'name' => $def['name'],
                'message' => $def['name'] . ' ' . (TAPI_ALERT_OPS[$a['op']] ?? $a['op']) . ' ' . tapi_fmt_value($a['threshold'], $def['unit'])
                    . ' (now ' . tapi_fmt_value($val, $def['unit']) . ')'];
        }
    }
    return $out;
}
// Cron entry — fire firing alerts by email, once per rule per day (debounce).
function tapi_alerts_run() {
    $today = date('Y-m-d'); $sent = 0;
    foreach (tapi_alerts_eval() as $f) {
        $a = $f['alert'];
        if (substr((string)$a['last_fired_at'], 0, 10) === $today) continue;   // already fired today
        $to = trim((string)$a['recipients']);
        if ($to !== '' && function_exists('ops_mail')) {
            ops_mail($to, '[' . ($a['severity'] ?? 'WARN') . '] ' . $a['name'], $f['message'], '', 'tapi_alert');
            $sent++;
        }
        db()->prepare("UPDATE kpi_alerts SET last_fired_at=? WHERE id=?")->execute([date('c'), (int)$a['id']]);
    }
    return $sent;
}

// ---- Guardrails ------------------------------------------------------------
// Small-sample suppression: below the minimum, a figure must not be exposed as
// if it were representative (protects confidentiality and avoids over-reading
// noise). Returns true when the value should be suppressed.
function tapi_small_sample($count, $min = 5) { return (int)$count < (int)$min; }
// The caveat TAPI attaches wherever two series move together — analytics shows
// association; it must never assert cause (that is Phase 12 / human analysis).
function tapi_causality_caveat() {
    return 'This shows association only. A change appearing after another does not prove it was caused by it — treat any explanation as a hypothesis to check, not a conclusion.';
}
// Fairness context: personnel/vendor figures must carry the context that makes
// them fair to compare (assignment mix, volume, complexity), never a bare rank.
function tapi_fairness_note($kind = 'resource') {
    return $kind === 'vendor'
        ? 'Vendor figures depend on how many transactions, what scope and which client requirements applied — a single finding is not a score.'
        : 'Workload, assignment type, location and complexity differ between people — read these as context, not a league table.';
}
