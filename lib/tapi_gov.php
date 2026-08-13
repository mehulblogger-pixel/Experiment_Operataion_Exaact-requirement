<?php
// ============================================================================
//  TAPI Slice 6 — governance + the Phase-12 hand-off.
//
//  What makes analytics trustworthy over time and safe to build AI on:
//    · KPI versioning — a formula/target change never silently rewrites the
//      past; the old definition is kept with an effective date and a reason.
//    · Period snapshots + closure — a month can be snapshotted and LOCKED so a
//      finalised management figure stays reproducible even as live data moves.
//    · Exports (CSV + native XLSX) that carry the same filters as the screen.
//    · A management-review package assembled from the whole engine.
//    · A FACTUAL AI summary — KPI-derived statements only, no invented cause
//      (reuses ai_chat under a locked prompt; degrades when AI is off).
//    · An audit trail on every KPI edit / export / snapshot / period change
//      (reuses the platform's hash-chained idems_log).
//    · A clean, structured KPI-metadata interface — the input contract Phase 12
//      reads (§83): kpi, formula, source, period, value, target, status, trend,
//      and a data-quality/confidence signal.
// ============================================================================

const TAPI_PERIOD_STATES = ['OPEN' => 'Open', 'REVIEW' => 'Under review', 'FINAL' => 'Final', 'LOCKED' => 'Locked'];

function tapi_gov_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = pk_clause();
    // Old KPI definitions, kept so a historical report remains reproducible.
    db()->exec("CREATE TABLE IF NOT EXISTS kpi_versions (
        id $pk, kpi_key VARCHAR(60) DEFAULT '', version INT DEFAULT 1,
        formula VARCHAR(400) DEFAULT '', unit VARCHAR(20) DEFAULT '', target DECIMAL(16,4) NULL,
        threshold DECIMAL(16,4) NULL, direction VARCHAR(10) DEFAULT '',
        effective_from VARCHAR(30) DEFAULT '', effective_to VARCHAR(30) DEFAULT '',
        changed_by VARCHAR(150) DEFAULT '', reason VARCHAR(300) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // Immutable period results — one row per KPI per closed period + scope.
    db()->exec("CREATE TABLE IF NOT EXISTS kpi_snapshots (
        id $pk, period_key VARCHAR(16) DEFAULT '', kpi_key VARCHAR(60) DEFAULT '',
        scope_office INT DEFAULT 0, scope_sbu VARCHAR(20) DEFAULT '',
        value DECIMAL(18,4) NULL, no_data INT DEFAULT 0, target DECIMAL(16,4) NULL,
        status VARCHAR(12) DEFAULT '', kpi_version INT DEFAULT 0,
        taken_at VARCHAR(30) DEFAULT '', taken_by VARCHAR(150) DEFAULT '')");
    // Period lifecycle.
    db()->exec("CREATE TABLE IF NOT EXISTS analytics_periods (
        id $pk, period_key VARCHAR(16) DEFAULT '', state VARCHAR(12) DEFAULT 'OPEN',
        note VARCHAR(300) DEFAULT '', changed_by VARCHAR(150) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
}

// ---- Audit (reuse the hash-chained platform log) ---------------------------
function tapi_audit($action, $detail = '') {
    if (function_exists('idems_log')) { try { idems_log('tapi', 0, $action, ['field' => substr((string)$detail, 0, 240)]); } catch (Throwable $e) {} }
}

// ---- KPI versioning --------------------------------------------------------
function tapi_kpi_version_snapshot($old, $new) {
    // Only version when the MEANING changes.
    $changed = ((string)$old['formula'] !== (string)($new['formula'] ?? $old['formula']))
        || ((string)$old['direction'] !== (string)($new['direction'] ?? $old['direction']))
        || ((string)($old['target'] ?? '') !== (string)($new['target'] ?? ($old['target'] ?? '')))
        || ((string)($old['threshold'] ?? '') !== (string)($new['threshold'] ?? ($old['threshold'] ?? '')));
    if (!$changed) return false;
    $ver = (int) ops_val("SELECT COALESCE(MAX(version),0)+1 FROM kpi_versions WHERE kpi_key=?", [(string)$old['kpi_key']]);
    db()->prepare("INSERT INTO kpi_versions (kpi_key,version,formula,unit,target,threshold,direction,effective_from,effective_to,changed_by,reason,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
        (string)$old['kpi_key'], $ver, (string)$old['formula'], (string)$old['unit'],
        $old['target'] === '' ? null : $old['target'], $old['threshold'] === '' ? null : $old['threshold'], (string)$old['direction'],
        (string)($old['updated_at'] ?: $old['created_at']), date('c'),
        function_exists('current_user') ? (string)(user_name(current_user()) ?? '') : '', substr((string)($new['reason'] ?? ''), 0, 300), date('c')]);
    tapi_audit('KPI_VERSIONED', $old['kpi_key'] . ' v' . $ver);
    return true;
}
function tapi_kpi_history($key) { return ops_all("SELECT * FROM kpi_versions WHERE kpi_key=? ORDER BY version DESC", [(string)$key]) ?: []; }
function tapi_kpi_current_version($key) { return 1 + (int) ops_val("SELECT COALESCE(MAX(version),0) FROM kpi_versions WHERE kpi_key=?", [(string)$key]); }

// ---- Period snapshots + closure -------------------------------------------
function tapi_period_state($periodKey) {
    $r = ops_one("SELECT state FROM analytics_periods WHERE period_key=?", [(string)$periodKey]);
    return $r ? (string)$r['state'] : 'OPEN';
}
function tapi_period_locked($periodKey) { return in_array(tapi_period_state($periodKey), ['FINAL', 'LOCKED'], true); }
function tapi_period_set_state($periodKey, $state, $note = '') {
    if (!isset(TAPI_PERIOD_STATES[$state])) return false;
    $by = function_exists('current_user') ? (string)(user_name(current_user()) ?? '') : '';
    if (ops_val("SELECT COUNT(*) FROM analytics_periods WHERE period_key=?", [(string)$periodKey]))
        db()->prepare("UPDATE analytics_periods SET state=?, note=?, changed_by=?, updated_at=? WHERE period_key=?")->execute([$state, $note, $by, date('c'), (string)$periodKey]);
    else
        db()->prepare("INSERT INTO analytics_periods (period_key,state,note,changed_by,updated_at) VALUES (?,?,?,?,?)")->execute([(string)$periodKey, $state, $note, $by, date('c')]);
    tapi_audit('PERIOD_STATE', $periodKey . '=' . $state);
    return true;
}
// Snapshot every active KPI for a period. Refuses to overwrite a locked period,
// so a finalised figure is immutable and reproducible.
function tapi_snapshot_period($periodKey, $ctx = null) {
    if (tapi_period_locked($periodKey)) return ['ok' => false, 'error' => 'That period is locked — its figures are final and cannot be re-snapshotted.'];
    if ($ctx === null) { [$from, $to] = tapi_period_range('MONTH', $periodKey . '-15'); $ctx = ['from' => $from, 'to' => $to]; }
    $by = function_exists('current_user') ? (string)(user_name(current_user()) ?? '') : '';
    $n = 0;
    db()->prepare("DELETE FROM kpi_snapshots WHERE period_key=? AND scope_office=? AND scope_sbu=?")
        ->execute([(string)$periodKey, (int)($ctx['office'] ?? 0), (string)($ctx['sbu'] ?? '')]);
    foreach (tapi_kpi_defs() as $d) {
        $card = tapi_kpi_eval($d, $ctx);
        [$target] = function_exists('tapi_target_effective') ? tapi_target_effective($d, $ctx) : [$d['target'] ?? null];
        db()->prepare("INSERT INTO kpi_snapshots (period_key,kpi_key,scope_office,scope_sbu,value,no_data,target,status,kpi_version,taken_at,taken_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute([
            (string)$periodKey, (string)$d['kpi_key'], (int)($ctx['office'] ?? 0), (string)($ctx['sbu'] ?? ''),
            ($card['no_data'] ?? false) ? null : $card['value'], ($card['no_data'] ?? false) ? 1 : 0,
            $target === null ? null : $target, (string)$card['status'], tapi_kpi_current_version($d['kpi_key']), date('c'), $by]);
        $n++;
    }
    tapi_audit('SNAPSHOT', $periodKey . ' (' . $n . ' KPIs)');
    return ['ok' => true, 'count' => $n];
}
function tapi_snapshot_get($periodKey, $office = 0, $sbu = '') {
    return ops_all("SELECT * FROM kpi_snapshots WHERE period_key=? AND scope_office=? AND scope_sbu=? ORDER BY id",
        [(string)$periodKey, (int)$office, (string)$sbu]) ?: [];
}

// ---- Exports (CSV + native XLSX), carrying the caller's filters ------------
// Build the KPI export matrix for a filter set.
function tapi_export_matrix($filters) {
    $ctx = tapi_ctx_from_filters($filters);
    $rows = [['KPI', 'Category', 'Value', 'Unit', 'Target', 'Status', 'Data source', 'Period from', 'Period to']];
    foreach (tapi_kpi_defs() as $d) {
        $c = tapi_kpi_eval($d, $ctx);
        $rows[] = [$d['name'], $d['category'], ($c['no_data'] ?? false) ? 'No data' : $c['value'], $d['unit'],
            ($d['target'] ?? '') === '' ? '' : $d['target'], $c['status'],
            implode('; ', array_unique(array_map(fn($l) => $l['source'], $c['lineage'] ?? []))),
            (string)($filters['from'] ?? ''), (string)($filters['to'] ?? '')];
    }
    return $rows;
}
// A minimal but VALID .xlsx (single sheet, inline strings) via ZipArchive — no
// external library. Numbers are written as numbers, everything else as text.
function tapi_xlsx($rows, $sheet = 'Analytics') {
    if (!class_exists('ZipArchive')) return null;
    $esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $cellRef = function ($col, $row) { $s = ''; $col++; while ($col > 0) { $m = ($col - 1) % 26; $s = chr(65 + $m) . $s; $col = intdiv($col - 1, 26); } return $s . $row; };
    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    foreach ($rows as $ri => $r) {
        $sheetXml .= '<row r="' . ($ri + 1) . '">';
        foreach (array_values($r) as $ci => $val) {
            $ref = $cellRef($ci, $ri + 1);
            if (is_int($val) || is_float($val) || (is_string($val) && $val !== '' && is_numeric($val)))
                $sheetXml .= '<c r="' . $ref . '"><v>' . $esc($val) . '</v></c>';
            else
                $sheetXml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $esc($val) . '</t></is></c>';
        }
        $sheetXml .= '</row>';
    }
    $sheetXml .= '</sheetData></worksheet>';
    $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>';
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="' . $esc(mb_substr($sheet, 0, 28)) . '" sheetId="1" r:id="rId1"/></sheets></workbook>';
    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>';
    $tmp = tempnam(sys_get_temp_dir(), 'tapi') . '.xlsx';
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return null;
    $zip->addFromString('[Content_Types].xml', $ct);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('xl/workbook.xml', $wb);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();
    $bytes = file_get_contents($tmp); @unlink($tmp);
    return $bytes;
}

// ---- Management-review package (structured) --------------------------------
function tapi_management_review($filters) {
    $ctx = tapi_ctx_from_filters($filters);
    $kpis = [];
    foreach (tapi_kpi_defs() as $d) $kpis[] = ['def' => $d, 'card' => tapi_kpi_eval($d, $ctx), 'cmp' => tapi_compare($d, $ctx, $filters['compare'] ?? 'PREV')];
    $cards = function_exists('tapi_scorecards') ? tapi_scorecards() : [];
    return [
        'period'    => ['from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? ''],
        'kpis'      => $kpis,
        'scorecard' => ($cards && function_exists('tapi_scorecard_eval')) ? tapi_scorecard_eval((int)$cards[0]['id'], $ctx) : null,
        'alerts'    => function_exists('tapi_alerts_eval') ? tapi_alerts_eval($ctx) : [],
        'quality'   => function_exists('tapi_data_quality') ? tapi_data_quality()['summary'] : ['total'=>0,'failed'=>0],
        'caveat'    => function_exists('tapi_causality_caveat') ? tapi_causality_caveat() : '',
        'generated' => date('c'),
    ];
}

// ---- Factual AI summary (KPI-derived only) --------------------------------
function tapi_ai_summary($filters) {
    if (!function_exists('ai_active') || !ai_active())
        return ['', 'The assistant is not switched on. The figures are all on the dashboard.'];
    $ctx = tapi_ctx_from_filters($filters);
    $lines = ['KPIs for the period ' . ($filters['from'] ?? 'all') . ' to ' . ($filters['to'] ?? 'now') . ':'];
    foreach (tapi_kpi_defs() as $d) {
        $c = tapi_kpi_eval($d, $ctx); $cmp = tapi_compare($d, $ctx, $filters['compare'] ?? 'PREV');
        $val = ($c['no_data'] ?? false) ? 'no data' : tapi_fmt_value($c['value'], $d['unit']);
        $chg = $cmp['variance_pct'] === null ? '' : (' (' . ($cmp['variance_pct'] >= 0 ? '+' : '') . $cmp['variance_pct'] . '% ' . $cmp['label'] . ')');
        $lines[] = '- ' . $d['name'] . ': ' . $val . $chg . ' [status ' . $c['status'] . ']';
    }
    $facts = implode("\n", $lines);
    $system = 'You are writing a factual management brief for a third-party inspection agency. Use ONLY the KPI figures '
        . 'given. State what changed and by how much. Do NOT invent a reason or a cause — if a number moved, say it moved, '
        . 'not why. Never state that one thing caused another. Where a KPI says "no data", say the data is not available '
        . 'rather than treating it as zero. Keep it to a short paragraph plus a few bullet points.\n\nFIGURES:\n' . $facts;
    tapi_audit('AI_SUMMARY', 'period ' . ($filters['from'] ?? ''));
    return ai_chat($system, 'Write the brief.', 700);
}

// ---- Phase-12 hand-off: the structured KPI metadata contract (§83) ---------
// Everything the AI layer needs to reason, with a data-quality/confidence
// signal, and NO decision-making baked in.
function tapi_kpi_export_meta($filters) {
    $ctx = tapi_ctx_from_filters($filters);
    $dq = function_exists('tapi_data_quality') ? tapi_data_quality()['summary'] : ['total'=>0,'failed'=>0];
    $confidence = ($dq['total'] ?? 0) > 0 ? round(max(0, ($dq['total'] - $dq['failed']) / $dq['total']) * 100) : null;
    $out = [];
    foreach (tapi_kpi_defs() as $d) {
        $c = tapi_kpi_eval($d, $ctx); $cmp = tapi_compare($d, $ctx, $filters['compare'] ?? 'PREV');
        $out[] = [
            'kpi' => $d['kpi_key'], 'name' => $d['name'], 'category' => $d['category'],
            'formula' => $d['formula'], 'unit' => $d['unit'],
            'source' => array_values(array_unique(array_map(fn($l) => $l['source'], $c['lineage'] ?? []))),
            'period' => ['from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? ''],
            'dimensions' => ['office' => (int)($ctx['office'] ?? 0), 'sbu' => (string)($ctx['sbu'] ?? '')],
            'value' => ($c['no_data'] ?? false) ? null : $c['value'],
            'no_data' => $c['no_data'] ?? false,
            'target' => ($d['target'] ?? '') === '' ? null : (float)$d['target'],
            'status' => $c['status'], 'direction' => $d['direction'],
            'trend' => $cmp['trend'], 'variance_pct' => $cmp['variance_pct'],
            'data_quality_confidence' => $confidence,   // 0–100, or null when unknown
        ];
    }
    return ['generated' => date('c'), 'confidence' => $confidence, 'kpis' => $out];
}
