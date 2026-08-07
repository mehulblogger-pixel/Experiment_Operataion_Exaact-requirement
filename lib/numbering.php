<?php
// ============================================================================
//  Configurable document numbering
//
//  Every reference the operations side mints — enquiry, quotation, inspection
//  call, deputation/job, requisition, candidate — flows through ops_next_code(),
//  which now consults a per-document scheme an admin controls from Settings:
//  the prefix, the separator, how many digits, whether the financial year is in
//  the number and in what style, and the number to start from.
//
//  Design choices that keep it safe:
//   - The sequence number is always the LAST part of the code, so finding the
//     current maximum stays a simple, gap-safe scan whatever the prefix or year.
//   - The scheme is keyed by a stable document type (CALL, JOB…), not by the
//     prefix text — so renaming the prefix does not lose the setting or the count.
//   - Defaults reproduce the old PREFIX-00001 format exactly, so an installation
//     that changes nothing keeps the numbers it already has, gap-free.
//   - Only codes whose tail after the stem is pure digits are counted, so an
//     imported lettered code (CALL-E0149) can never poison the next number.
// ============================================================================

// The document types that flow through ops_next_code(), with their defaults.
function numbering_types() {
    return [
        'INQ'  => ['label' => 'Enquiries',        'default_prefix' => 'INQ'],
        'Q'    => ['label' => 'Quotations',       'default_prefix' => 'Q'],
        'CALL' => ['label' => 'Inspection calls', 'default_prefix' => 'CALL'],
        'JOB'  => ['label' => 'Deputations / jobs','default_prefix' => 'JOB'],
        'REQ'  => ['label' => 'Requisitions',     'default_prefix' => 'REQ'],
        'CV'   => ['label' => 'Candidates',       'default_prefix' => 'CV'],
        // Money documents. These reset each financial year (a GST requirement),
        // which happens automatically because the year is part of the number.
        'INV'  => ['label' => 'Invoices',       'default_prefix' => 'INV', 'money' => 1, 'fy_default' => 1, 'sep_default' => '/', 'pad_default' => 4, 'fy_style_default' => 'YYYYC', 'prefix_from' => 'the office code / invoice-series setting'],
        'RCP'  => ['label' => 'Receipts',       'default_prefix' => 'RCP', 'money' => 1, 'fy_default' => 1, 'sep_default' => '/', 'pad_default' => 4, 'fy_style_default' => 'YYYYC'],
        'CN'   => ['label' => 'Credit notes',   'default_prefix' => 'CN',  'money' => 1, 'fy_default' => 1, 'sep_default' => '/', 'pad_default' => 4, 'fy_style_default' => 'YYYYC'],
    ];
}
function numbering_has($key) { return isset(numbering_types()[$key]); }

function numbering_fy_styles() {
    return ['YY-YY' => '26-27', 'YYYY-YY' => '2026-27', 'YYYYC' => '2627', 'YYYY' => '2026', 'YY' => '26'];
}

// The effective scheme for a document type: defaults overlaid with the saved
// settings, then sanitised so a half-filled form can never mint a broken code.
function numbering_scheme($key) {
    $t = numbering_types()[$key] ?? ['label' => $key, 'default_prefix' => $key];
    $s = [
        'prefix'   => $t['default_prefix'],
        'sep'      => $t['sep_default'] ?? '-',
        'pad'      => $t['pad_default'] ?? 5,
        'fy'       => $t['fy_default'] ?? 0,
        'fy_style' => $t['fy_style_default'] ?? 'YY-YY',
        'start'    => 0,     // optional floor: the first number to hand out
        'label'    => $t['label'] ?? $key,
        'money'    => !empty($t['money']) ? 1 : 0,
        'prefix_from' => $t['prefix_from'] ?? '',
    ];
    $saved = json_decode((string)setting_get('numbering_' . $key, ''), true);
    if (is_array($saved)) $s = array_merge($s, $saved);
    $s['prefix']   = substr(trim((string)$s['prefix']), 0, 20);
    if ($s['prefix'] === '') $s['prefix'] = $t['default_prefix'];
    $s['sep']      = substr((string)$s['sep'], 0, 3);           // '' allowed (no separator)
    $s['pad']      = max(1, min(12, (int)$s['pad']));
    $s['fy']       = !empty($s['fy']) ? 1 : 0;
    if (!isset(numbering_fy_styles()[$s['fy_style']])) $s['fy_style'] = 'YY-YY';
    $s['start']    = max(0, (int)$s['start']);
    $s['label']    = $t['label'] ?? $key;
    return $s;
}

// The financial-year start year, from the fy_start_month setting.
function numbering_fy_year() {
    $m = (int)setting_get('fy_start_month', 4);
    if ($m < 1 || $m > 12) $m = 4;
    $Y = (int)date('Y'); $mo = (int)date('n');
    return $mo >= $m ? $Y : $Y - 1;
}
// Format a specific financial-year start year in a given style. The money
// documents pass their own year (an invoice may be back-dated into last year),
// so the number lands in the right GST series rather than today's.
function numbering_fy_string_for($startYear, $style) {
    $y = (int)$startYear; $y2 = $y + 1;
    switch ($style) {
        case 'YYYY-YY': return sprintf('%04d-%02d', $y, $y2 % 100);
        case 'YYYYC':   return sprintf('%02d%02d', $y % 100, $y2 % 100);   // 2627, the Books default
        case 'YYYY':    return sprintf('%04d', $y);
        case 'YY':      return sprintf('%02d', $y % 100);
        case 'YY-YY': default: return sprintf('%02d-%02d', $y % 100, $y2 % 100);
    }
}
function numbering_fy_string($style) { return numbering_fy_string_for(numbering_fy_year(), $style); }

// Everything before the sequence number. Pass an explicit FY string to override
// the current-year default (used by back-dated money documents).
function numbering_stem($scheme, $fyStr = null) {
    if ($fyStr === null) $fyStr = !empty($scheme['fy']) ? numbering_fy_string($scheme['fy_style']) : '';
    $s = $scheme['prefix'];
    if (!empty($scheme['fy']) && $fyStr !== '') $s .= $scheme['sep'] . $fyStr;
    return $s . $scheme['sep'];
}

// Format one specific sequence number in a type's scheme (used by callers that
// have their own collision handling, e.g. the quotation number).
function numbering_format_seq($key, $seq) {
    $sc = numbering_scheme($key);
    return numbering_stem($sc) . str_pad((string)max(0, (int)$seq), $sc['pad'], '0', STR_PAD_LEFT);
}

// A sample the Settings screen shows so an admin sees the shape before saving.
function numbering_preview($key) { return numbering_format_seq($key, 42); }

// Allocate the next free code for a document type against its table/column.
// Gap-safe (scans the real maximum), collision-checked (steps past a taken code),
// and honours the "start from" floor. $fyStartYear overrides the current FY (for
// back-dated money documents); $prefixOverride replaces the scheme prefix (so an
// invoice keeps its office-specific series while the rest of the format is
// configured centrally).
function numbering_next($key, $table, $col, $fyStartYear = null, $prefixOverride = null) {
    $sc = numbering_scheme($key);
    if ($prefixOverride !== null && $prefixOverride !== '') $sc['prefix'] = $prefixOverride;
    $fyStr = !empty($sc['fy'])
        ? ($fyStartYear !== null ? numbering_fy_string_for($fyStartYear, $sc['fy_style']) : numbering_fy_string($sc['fy_style']))
        : '';
    $stem = numbering_stem($sc, $fyStr);
    $max  = 0;
    foreach (ops_all("SELECT $col FROM $table WHERE $col LIKE ?", [$stem . '%']) ?: [] as $r) {
        if (preg_match('/^' . preg_quote($stem, '/') . '(\d+)$/', (string)$r[$col], $m))
            $max = max($max, (int)$m[1]);
    }
    if ($sc['start'] > 0) $max = max($max, $sc['start'] - 1);
    for ($try = 1; $try <= 50; $try++) {
        $code = $stem . str_pad((string)($max + $try), $sc['pad'], '0', STR_PAD_LEFT);
        if (!ops_val("SELECT 1 FROM $table WHERE $col=? LIMIT 1", [$code])) return $code;
    }
    throw new RuntimeException("Could not allocate a free $key number. Try again.");
}

// Save one scheme from a Settings POST (fields prefixed num_<key>_*).
function numbering_save($key, array $post) {
    if (!numbering_has($key)) return;
    $s = [
        'prefix'   => (string)($post["num_{$key}_prefix"] ?? ''),
        'sep'      => (string)($post["num_{$key}_sep"] ?? '-'),
        'pad'      => (int)($post["num_{$key}_pad"] ?? 5),
        'fy'       => !empty($post["num_{$key}_fy"]) ? 1 : 0,
        'fy_style' => (string)($post["num_{$key}_fy_style"] ?? 'YY-YY'),
        'start'    => (int)($post["num_{$key}_start"] ?? 0),
    ];
    setting_set('numbering_' . $key, json_encode($s));
}
