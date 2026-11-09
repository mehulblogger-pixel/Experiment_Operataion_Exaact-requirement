<?php
// ============================================================================
//  COSTING HEADS — the flexible cost build-up of one costing role.
//
//  Owner, 9 Nov 2026: "can we improve the costing with each heads that can be
//  selected either as % or Number, calculator inside it or remarks as attached
//  to it … adding any head or changing name … very flexible. Suppose statutory
//  requirement is of 12% of basic then it shall take the basic of the
//  candidate" — and: "costing shall be selected as Monthly or daily. Like
//  allowance can be selected as daily or monthly", "highly configurable as it
//  changes with time".
//
//  So a role is a LIST OF HEADS, each one of:
//    AMOUNT   a number (the box is a calculator: "1500*2+300" is fine)
//    PCT      a % of other heads — "12% of Basic + DA", with an optional cap on
//             the base ("on wages up to 15,000") and an optional eligibility
//             limit ("only while gross is up to 21,000")
//    QTYRATE  quantity × rate — "2 trips × 1,500"
//    FORMULA  a small formula over other heads — "MIN(BASIC,15000)*12%"
//  each entered per MONTH, per DAY, per YEAR or ONE TIME, with a group (salary
//  paid to the person / employer statutory / other cost), an optional minimum
//  and maximum, and a remark.
//
//  Everything is worked out in a canonical MONTHLY figure, then shown in the
//  sheet's own unit (per man-month, or per man-day for a daily sheet), using the
//  role's "working days in a month". Nothing here writes to the database except
//  the library and template tables at the bottom; the role itself is saved by
//  lib/projcosting.php, which owns project_costing_lines.
//
//  The LIBRARY holds the heads a company reuses, with the date each value took
//  effect, so when the law changes (a new PF ceiling) a new value is added from a
//  date and older costings keep the value they were priced with: a head is
//  COPIED into a role when it is added, never linked.
// ============================================================================

const CH_TYPES = [
    'AMOUNT'  => 'Amount',
    'PCT'     => '% of other heads',
    'QTYRATE' => 'Quantity × rate',
    'FORMULA' => 'Formula',
];
const CH_PER = [
    'MONTH' => 'per month',
    'DAY'   => 'per day',
    'YEAR'  => 'per year',
    'ONCE'  => 'one time',
];
const CH_GROUPS = [
    'PAY'  => 'Salary — paid to the person',
    'STAT' => 'Statutory — employer',
    'COST' => 'Other cost',
];
const CH_FUNCS = ['MIN', 'MAX', 'ROUND', 'CEIL', 'FLOOR', 'ABS', 'IF'];
const CH_MAX_ITEMS = 60;

// Working days in a month — the company's own figure, changeable on each role.
function ch_days_per_month() {
    $v = function_exists('setting_get') ? (float) setting_get('costing_days_per_month', 26) : 26;
    return ($v >= 1 && $v <= 31) ? $v : 26;
}

// ---- A tiny, safe formula language -----------------------------------------
// Numbers (12, 12.5, 12% = 0.12), head codes (BASIC), + - * / ( ), comparisons
// (< <= > >= == !=) and MIN MAX ROUND CEIL FLOOR ABS IF. No PHP is ever run.
function ch_tokens($s, &$err) {
    $err = ''; $s = (string) $s; $t = []; $i = 0; $n = strlen($s);
    if ($n > 300) { $err = 'The formula is too long (300 characters at most).'; return []; }
    while ($i < $n) {
        $c = $s[$i];
        if (ctype_space($c)) { $i++; continue; }
        if (ctype_digit($c) || ($c === '.' && $i + 1 < $n && ctype_digit($s[$i + 1]))) {
            $j = $i; while ($j < $n && (ctype_digit($s[$j]) || $s[$j] === '.')) $j++;
            $num = substr($s, $i, $j - $i);
            if (substr_count($num, '.') > 1) { $err = '"' . $num . '" is not a number.'; return []; }
            $v = (float) $num;
            if ($j < $n && $s[$j] === '%') { $v /= 100; $j++; }
            $t[] = ['n', $v]; $i = $j; continue;
        }
        if (ctype_alpha($c) || $c === '_') {
            $j = $i; while ($j < $n && (ctype_alnum($s[$j]) || $s[$j] === '_')) $j++;
            $t[] = ['id', strtoupper(substr($s, $i, $j - $i))]; $i = $j; continue;
        }
        $two = substr($s, $i, 2);
        if (in_array($two, ['<=', '>=', '==', '!='], true)) { $t[] = ['op', $two]; $i += 2; continue; }
        if (strpos('+-*/(),<>', $c) !== false) { $t[] = ['op', $c]; $i++; continue; }
        if ($c === '×' || $c === 'x') { $t[] = ['op', '*']; $i++; continue; }
        $err = 'The formula has a character it cannot use: "' . mb_substr(substr($s, $i), 0, 1) . '".';
        return [];
    }
    return $t;
}

// The head codes a formula refers to (functions are not references).
function ch_expr_refs($expr) {
    $out = [];
    $toks = ch_tokens($expr, $err);
    foreach ($toks as $k => $tk) {
        if ($tk[0] !== 'id') continue;
        $next = $toks[$k + 1] ?? null;
        if (in_array($tk[1], CH_FUNCS, true) && $next && $next[1] === '(') continue;
        $out[$tk[1]] = true;
    }
    return array_keys($out);
}

// Evaluate. $vars: CODE => number. Returns the number, or null with $err set.
function ch_expr_eval($expr, array $vars, &$err) {
    $err = '';
    $toks = ch_tokens($expr, $err);
    if ($err !== '') return null;
    if (!$toks) { $err = 'The formula is empty.'; return null; }
    $p = 0; $depth = 0;
    $peek = function () use (&$toks, &$p) { return $toks[$p] ?? null; };
    $isOp = function ($v) use (&$toks, &$p) { return isset($toks[$p]) && $toks[$p][0] === 'op' && $toks[$p][1] === $v; };
    $cmp = null; $add = null; $mul = null; $unary = null; $prim = null;
    $fail = function ($m) use (&$err) { if ($err === '') $err = $m; return 0.0; };
    $cmp = function () use (&$add, &$toks, &$p, &$err) {
        $l = $add(); if ($err !== '') return 0.0;
        while (isset($toks[$p]) && $toks[$p][0] === 'op' && in_array($toks[$p][1], ['<', '<=', '>', '>=', '==', '!='], true)) {
            $o = $toks[$p][1]; $p++; $r = $add(); if ($err !== '') return 0.0;
            $e = 1e-9;
            switch ($o) {
                case '<': $l = $l < $r - $e ? 1.0 : 0.0; break;
                case '<=': $l = $l <= $r + $e ? 1.0 : 0.0; break;
                case '>': $l = $l > $r + $e ? 1.0 : 0.0; break;
                case '>=': $l = $l >= $r - $e ? 1.0 : 0.0; break;
                case '==': $l = abs($l - $r) <= $e ? 1.0 : 0.0; break;
                default: $l = abs($l - $r) > $e ? 1.0 : 0.0;
            }
        }
        return $l;
    };
    $add = function () use (&$mul, &$toks, &$p, &$err) {
        $l = $mul(); if ($err !== '') return 0.0;
        while (isset($toks[$p]) && $toks[$p][0] === 'op' && ($toks[$p][1] === '+' || $toks[$p][1] === '-')) {
            $o = $toks[$p][1]; $p++; $r = $mul(); if ($err !== '') return 0.0;
            $l = $o === '+' ? $l + $r : $l - $r;
        }
        return $l;
    };
    $mul = function () use (&$unary, &$toks, &$p, &$err, $fail) {
        $l = $unary(); if ($err !== '') return 0.0;
        while (isset($toks[$p]) && $toks[$p][0] === 'op' && ($toks[$p][1] === '*' || $toks[$p][1] === '/')) {
            $o = $toks[$p][1]; $p++; $r = $unary(); if ($err !== '') return 0.0;
            if ($o === '/') { if (abs($r) < 1e-12) return $fail('The formula divides by zero.'); $l = $l / $r; }
            else $l = $l * $r;
        }
        return $l;
    };
    $unary = function () use (&$prim, &$unary, &$toks, &$p) {
        if (isset($toks[$p]) && $toks[$p][0] === 'op' && ($toks[$p][1] === '-' || $toks[$p][1] === '+')) {
            $o = $toks[$p][1]; $p++; $v = $unary(); return $o === '-' ? -$v : $v;
        }
        return $prim();
    };
    $prim = function () use (&$cmp, &$toks, &$p, &$depth, &$err, $vars, $fail) {
        if (++$depth > 40) return $fail('The formula is nested too deeply.');
        $tk = $toks[$p] ?? null;
        if (!$tk) { $depth--; return $fail('The formula ends too early.'); }
        if ($tk[0] === 'n') { $p++; $depth--; return (float) $tk[1]; }
        if ($tk[0] === 'op' && $tk[1] === '(') {
            $p++; $v = $cmp(); if ($err !== '') return 0.0;
            if (!isset($toks[$p]) || $toks[$p][1] !== ')') return $fail('A bracket is not closed.');
            $p++; $depth--; return $v;
        }
        if ($tk[0] === 'id') {
            $name = $tk[1]; $p++;
            if (in_array($name, CH_FUNCS, true) && isset($toks[$p]) && $toks[$p][1] === '(') {
                $p++; $args = [];
                if (!(isset($toks[$p]) && $toks[$p][1] === ')')) {
                    while (true) {
                        $args[] = $cmp(); if ($err !== '') return 0.0;
                        if (isset($toks[$p]) && $toks[$p][1] === ',') { $p++; continue; }
                        break;
                    }
                }
                if (!isset($toks[$p]) || $toks[$p][1] !== ')') return $fail($name . '( is not closed.');
                $p++; $depth--;
                $na = count($args);
                switch ($name) {
                    case 'MIN': return $na ? (float) min($args) : $fail('MIN needs at least one value.');
                    case 'MAX': return $na ? (float) max($args) : $fail('MAX needs at least one value.');
                    case 'ROUND': return $na === 1 ? round($args[0]) : ($na === 2 ? round($args[0], (int) $args[1]) : $fail('ROUND takes a value and, if you like, the decimals.'));
                    case 'CEIL': return $na === 1 ? ceil($args[0]) : $fail('CEIL takes one value.');
                    case 'FLOOR': return $na === 1 ? floor($args[0]) : $fail('FLOOR takes one value.');
                    case 'ABS': return $na === 1 ? abs($args[0]) : $fail('ABS takes one value.');
                    case 'IF': return $na === 3 ? (abs($args[0]) > 1e-12 ? $args[1] : $args[2]) : $fail('IF takes three parts: IF(test, then, otherwise).');
                }
            }
            $depth--;
            if (!array_key_exists($name, $vars)) return $fail('"' . $name . '" is not a head on this role.');
            return (float) $vars[$name];
        }
        return $fail('The formula has "' . $tk[1] . '" where a value should be.');
    };
    $v = $cmp();
    if ($err !== '') return null;
    if ($p < count($toks)) { $err = 'The formula has something extra after "' . (string) $toks[$p - 1][1] . '".'; return null; }
    if (!is_finite($v)) { $err = 'The formula does not give a number.'; return null; }
    return $v;
}

// The calculator in an amount box: "1500*2+300" → 3300. Plain numbers (with
// thousands commas) pass straight through. Returns [value|null, error].
function ch_calc_number($s) {
    $s = trim((string) $s);
    if ($s === '') return [0.0, ''];
    if (preg_match('/^-?[0-9][0-9,]*(\.[0-9]+)?$/', $s)) return [(float) str_replace(',', '', $s), ''];
    if (preg_match('/[A-Za-z_]/', str_replace(['x', '×'], '', $s))) return [null, 'Only numbers and + − × ÷ ( ) % can be used here — for a head that uses other heads, choose "Formula".'];
    $v = ch_expr_eval($s, [], $err);
    return [$v, $err];
}

// ---- One head, cleaned ------------------------------------------------------
function ch_code_from($label, array $taken = []) {
    $c = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', (string) $label));
    $c = trim($c, '_'); if ($c === '' || ctype_digit($c[0])) $c = 'H_' . $c;
    $c = substr($c, 0, 24); $base = $c; $k = 2;
    if (in_array($c, CH_FUNCS, true)) $c = $base = $c . '_H';
    while (in_array($c, $taken, true)) { $c = substr($base, 0, 21) . '_' . $k++; }
    return $c;
}

// $raw comes from the role editor or the library; returns [item, error].
function ch_item_clean(array $raw, array $taken = []) {
    $num = function ($k) use ($raw) { $v = $raw[$k] ?? ''; return is_numeric($v) ? (float) $v : (is_string($v) && trim($v) !== '' ? (float) str_replace(',', '', $v) : 0.0); };
    $label = trim(preg_replace('/\s+/', ' ', (string) ($raw['label'] ?? '')));
    if ($label === '') return [null, 'Every head needs a name.'];
    $label = mb_substr($label, 0, 80);
    $code = strtoupper(trim((string) ($raw['code'] ?? '')));
    if ($code === '' || !preg_match('/^[A-Z_][A-Z0-9_]{0,23}$/', $code) || in_array($code, CH_FUNCS, true)) $code = ch_code_from($label, $taken);
    if (in_array($code, $taken, true)) $code = ch_code_from($label, $taken);
    $type = strtoupper((string) ($raw['type'] ?? 'AMOUNT')); if (!isset(CH_TYPES[$type])) $type = 'AMOUNT';
    $per = strtoupper((string) ($raw['per'] ?? 'MONTH')); if (!isset(CH_PER[$per])) $per = 'MONTH';
    $grp = strtoupper((string) ($raw['grp'] ?? 'COST')); if (!isset(CH_GROUPS[$grp])) $grp = 'COST';
    // A % or a formula is worked out from monthly figures, so it is monthly — or
    // one time, when it is a one-time charge.
    if (($type === 'PCT' || $type === 'FORMULA') && $per !== 'ONCE') $per = 'MONTH';
    $it = ['code' => $code, 'label' => $label, 'type' => $type, 'per' => $per, 'grp' => $grp,
           'note' => mb_substr(trim((string) ($raw['note'] ?? '')), 0, 300)];
    if ($type === 'AMOUNT') {
        $calc = trim((string) ($raw['calc'] ?? $raw['amount'] ?? ''));
        [$v, $err] = ch_calc_number($calc);
        if ($v === null) return [null, '"' . $label . '": ' . $err];
        $it['amount'] = round($v, 4);
        if ($calc !== '' && !preg_match('/^-?[0-9][0-9,]*(\.[0-9]+)?$/', $calc)) $it['calc'] = mb_substr($calc, 0, 120);
    } elseif ($type === 'PCT') {
        $of = $raw['of'] ?? [];
        if (is_string($of)) $of = preg_split('/[\s,+]+/', $of);
        $of = array_values(array_unique(array_filter(array_map(fn($x) => strtoupper(trim((string) $x)), (array) $of), fn($x) => $x !== '')));
        if (!$of) return [null, '"' . $label . '": choose which heads the % is of.'];
        $it['pct'] = round($num('pct'), 4);
        $it['of'] = array_slice($of, 0, 20);
        $it['cap'] = max(0, round($num('cap'), 2));
        $it['upto'] = max(0, round($num('upto'), 2));
    } elseif ($type === 'QTYRATE') {
        $it['qty'] = round($num('qty'), 4);
        $it['rate'] = round($num('rate'), 4);
    } else {
        $expr = trim((string) ($raw['expr'] ?? ''));
        if ($expr === '') return [null, '"' . $label . '": write the formula.'];
        ch_tokens($expr, $err);
        if ($err !== '') return [null, '"' . $label . '": ' . $err];
        $it['expr'] = $expr;
    }
    $mn = trim((string) ($raw['min'] ?? '')); $mx = trim((string) ($raw['max'] ?? ''));
    if ($mn !== '' && is_numeric(str_replace(',', '', $mn))) $it['min'] = round((float) str_replace(',', '', $mn), 2);
    if ($mx !== '' && is_numeric(str_replace(',', '', $mx))) $it['max'] = round((float) str_replace(',', '', $mx), 2);
    if (isset($it['min'], $it['max']) && $it['min'] > $it['max']) return [null, '"' . $label . '": the minimum is above the maximum.'];
    if (!empty($raw['lib_id'])) { $it['lib_id'] = (int) $raw['lib_id']; $it['lib_from'] = substr((string) ($raw['lib_from'] ?? ''), 0, 10); }
    return [$it, ''];
}

// A whole role's list, cleaned. Returns [items, errors[]].
function ch_items_clean(array $rows) {
    $items = []; $errs = []; $taken = [];
    foreach (array_slice(array_values($rows), 0, CH_MAX_ITEMS) as $r) {
        if (!is_array($r)) continue;
        if (trim((string) ($r['label'] ?? '')) === '' && trim((string) ($r['calc'] ?? $r['amount'] ?? '')) === '' && trim((string) ($r['expr'] ?? '')) === '') continue;
        [$it, $err] = ch_item_clean($r, $taken);
        if ($err !== '') { $errs[] = $err; continue; }
        $taken[] = $it['code']; $items[] = $it;
    }
    if (count($rows) > CH_MAX_ITEMS) $errs[] = 'A role can have ' . CH_MAX_ITEMS . ' heads at most.';
    return [$items, $errs];
}

// ---- Working it out ---------------------------------------------------------
// $unit: 'MONTH' (a man-month sheet, or a lump sum) or 'DAY' (a man-day sheet).
// Returns ['ok', 'errors' => [code => msg], 'rows' => [code => [...]],
//          'direct' (recurring, in the unit), 'once', 'pay' (in the unit),
//          'by_grp' => [grp => unit], 'heads' => [code => unit value], 'labels'].
function ch_evaluate(array $items, $unit = 'MONTH', $dpm = null) {
    $dpm = (float) ($dpm ?: ch_days_per_month()); if ($dpm < 1 || $dpm > 31) $dpm = 26;
    $unit = $unit === 'DAY' ? 'DAY' : 'MONTH';
    $by = []; foreach ($items as $it) $by[$it['code']] = $it;
    $deps = [];
    foreach ($by as $code => $it) {
        $deps[$code] = $it['type'] === 'PCT' ? (array) $it['of'] : ($it['type'] === 'FORMULA' ? ch_expr_refs($it['expr']) : []);
    }
    $errors = [];
    foreach ($deps as $code => $d) foreach ($d as $ref) if (!isset($by[$ref]))
        $errors[$code] = $by[$code]['label'] . ' uses "' . $ref . '", which is not a head on this role.';
    // Order the heads so each comes after what it uses; a loop is an error.
    $state = []; $order = []; $cycle = [];
    $visit = function ($c, $path) use (&$visit, &$state, &$order, &$cycle, $deps, $by) {
        if (($state[$c] ?? 0) === 2) return;
        if (($state[$c] ?? 0) === 1) { $cycle = array_merge(array_slice($path, (int) array_search($c, $path, true)), [$c]); return; }
        $state[$c] = 1; $path[] = $c;
        foreach ($deps[$c] ?? [] as $d) { if (isset($by[$d])) $visit($d, $path); if ($cycle) return; }
        $state[$c] = 2; $order[] = $c;
    };
    foreach (array_keys($by) as $c) { if (!$cycle) $visit($c, []); }
    if ($cycle) {
        $names = array_map(fn($c) => $by[$c]['label'] ?? $c, $cycle);
        $errors[$cycle[0]] = 'These heads use each other in a loop: ' . implode(' → ', $names) . '. Break the loop so one of them stands on its own.';
        $order = array_keys($by);
    }
    $money = fn($v) => (function_exists('cur_sym') ? cur_sym() : '₹') . number_format((float) $v, abs($v - round($v)) > 0.004 ? 2 : 0);
    $pctTxt = fn($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.') . '%';
    $monthly = []; $once = []; $rows = [];
    foreach ($order as $code) {
        $it = $by[$code]; $why = ''; $m = 0.0; $o = 0.0; $isOnce = $it['per'] === 'ONCE';
        if (isset($errors[$code])) { $rows[$code] = ['monthly' => 0, 'once' => 0, 'unit' => 0, 'why' => $errors[$code], 'error' => true]; $monthly[$code] = 0; $once[$code] = 0; continue; }
        $toMonthly = function ($v, $per) use ($dpm) { return $per === 'DAY' ? $v * $dpm : ($per === 'YEAR' ? $v / 12 : $v); };
        $perTxt = fn($v, $per) => $per === 'DAY' ? $money($v) . ' a day × ' . rtrim(rtrim(number_format($dpm, 1), '0'), '.') . ' days'
                                 : ($per === 'YEAR' ? $money($v) . ' a year ÷ 12' : ($per === 'ONCE' ? $money($v) . ' one time' : ''));
        if ($it['type'] === 'AMOUNT' || $it['type'] === 'QTYRATE') {
            $v = $it['type'] === 'AMOUNT' ? (float) $it['amount'] : (float) $it['qty'] * (float) $it['rate'];
            $why = $it['type'] === 'QTYRATE' ? rtrim(rtrim(number_format((float) $it['qty'], 2), '0'), '.') . ' × ' . $money($it['rate']) . ' = ' . $money($v)
                 : (!empty($it['calc']) ? $it['calc'] . ' = ' . $money($v) : '');
            [$v, $clampTxt] = ch_clamp($v, $it, $money);
            if ($isOnce) $o = $v; else { $m = $toMonthly($v, $it['per']); $pt = $perTxt($v, $it['per']); if ($pt !== '') $why = trim($why . ($why !== '' ? '; ' : '') . $pt); }
            if ($clampTxt !== '') $why = trim($why . ($why !== '' ? '; ' : '') . $clampTxt);
        } elseif ($it['type'] === 'PCT') {
            $base = 0.0; $bad = '';
            foreach ($it['of'] as $ref) {
                if (!$isOnce && ($by[$ref]['per'] ?? '') === 'ONCE') { $bad = $ref; break; }
                $base += ($by[$ref]['per'] ?? '') === 'ONCE' ? (float) ($once[$ref] ?? 0) : (float) ($monthly[$ref] ?? 0);
            }
            if ($bad !== '') {
                $errors[$code] = $it['label'] . ' is monthly but is a % of "' . ($by[$bad]['label'] ?? $bad) . '", which is one time. Make it one time too, or choose monthly heads.';
                $rows[$code] = ['monthly' => 0, 'once' => 0, 'unit' => 0, 'why' => $errors[$code], 'error' => true]; $monthly[$code] = 0; $once[$code] = 0; continue;
            }
            $ofTxt = implode(' + ', array_map(fn($r) => $by[$r]['label'] ?? $r, $it['of']));
            if ((float) $it['upto'] > 0 && $base > (float) $it['upto'] + 0.004) {
                $v = 0.0; $why = 'Not applied — ' . $ofTxt . ' is ' . $money($base) . ', above the limit of ' . $money($it['upto']);
            } else {
                $used = ((float) $it['cap'] > 0 && $base > (float) $it['cap']) ? (float) $it['cap'] : $base;
                $v = $used * (float) $it['pct'] / 100;
                $why = $pctTxt($it['pct']) . ' of ' . $money($used) . ' (' . $ofTxt . ($used < $base ? ' ' . $money($base) . ', counted up to ' . $money($it['cap']) : '') . ')';
            }
            [$v, $clampTxt] = ch_clamp($v, $it, $money);
            if ($clampTxt !== '') $why .= '; ' . $clampTxt;
            if ($isOnce) $o = $v; else $m = $v;
        } else {
            $vars = [];
            foreach ($deps[$code] as $ref) {
                if (!$isOnce && ($by[$ref]['per'] ?? '') === 'ONCE') { $errors[$code] = $it['label'] . ' is monthly but its formula uses "' . ($by[$ref]['label'] ?? $ref) . '", which is one time.'; break; }
                $vars[$ref] = ($by[$ref]['per'] ?? '') === 'ONCE' ? (float) ($once[$ref] ?? 0) : (float) ($monthly[$ref] ?? 0);
            }
            if (isset($errors[$code])) { $rows[$code] = ['monthly' => 0, 'once' => 0, 'unit' => 0, 'why' => $errors[$code], 'error' => true]; $monthly[$code] = 0; $once[$code] = 0; continue; }
            $v = ch_expr_eval($it['expr'], $vars, $err);
            if ($v === null) {
                $errors[$code] = $it['label'] . ': ' . $err;
                $rows[$code] = ['monthly' => 0, 'once' => 0, 'unit' => 0, 'why' => $errors[$code], 'error' => true]; $monthly[$code] = 0; $once[$code] = 0; continue;
            }
            $why = $it['expr'] . ' = ' . $money($v);
            [$v, $clampTxt] = ch_clamp($v, $it, $money);
            if ($clampTxt !== '') $why .= '; ' . $clampTxt;
            if ($isOnce) $o = $v; else $m = $v;
        }
        $monthly[$code] = $isOnce ? 0.0 : $m; $once[$code] = $isOnce ? $o : 0.0;
        $u = $isOnce ? 0.0 : ($unit === 'DAY' ? $m / $dpm : $m);
        $rows[$code] = ['monthly' => round($m, 2), 'once' => round($o, 2), 'unit' => round($u, 2), 'why' => $why, 'error' => false];
    }
    $out = ['ok' => !$errors, 'errors' => $errors, 'rows' => [], 'direct' => 0.0, 'once' => 0.0, 'pay' => 0.0,
            'by_grp' => ['PAY' => 0.0, 'STAT' => 0.0, 'COST' => 0.0], 'heads' => [], 'labels' => [], 'unit' => $unit, 'dpm' => $dpm];
    foreach ($items as $it) {           // back in the order the user put them
        $r = $rows[$it['code']] ?? ['monthly' => 0, 'once' => 0, 'unit' => 0, 'why' => '', 'error' => false];
        $out['rows'][$it['code']] = $r;
        $out['labels'][$it['code']] = $it['label'];
        $out['direct'] += $r['unit']; $out['once'] += $r['once'];
        $out['by_grp'][$it['grp']] += $r['unit'];
        if ($it['grp'] === 'PAY') $out['pay'] += $r['unit'];
        if (abs($r['unit']) > 0.004) $out['heads'][$it['code']] = $r['unit'];
    }
    foreach (['direct', 'once', 'pay'] as $k) $out[$k] = round($out[$k], 2);
    foreach ($out['by_grp'] as $k => $v) $out['by_grp'][$k] = round($v, 2);
    return $out;
}

function ch_clamp($v, array $it, $money) {
    if (isset($it['min']) && $v < (float) $it['min']) return [(float) $it['min'], 'raised to the minimum ' . $money($it['min'])];
    if (isset($it['max']) && $v > (float) $it['max']) return [(float) $it['max'], 'kept to the maximum ' . $money($it['max'])];
    return [$v, ''];
}

// ---- A role's stored list ---------------------------------------------------
// project_costing_lines.spec: {"v":1,"dpm":26,"items":[…]}. A role saved before
// the heads became flexible has none — it is read from its old amounts, so every
// existing costing keeps its figures to the rupee.
function ch_spec_of(array $line, $basis = 'LONG') {
    $s = json_decode((string) ($line['spec'] ?? ''), true);
    if (is_array($s) && isset($s['items']) && is_array($s['items'])) {
        $s['dpm'] = (float) ($s['dpm'] ?? 0) ?: ch_days_per_month();
        return $s;
    }
    return ch_spec_from_legacy($line, $basis);
}

function ch_spec_from_legacy(array $line, $basis = 'LONG') {
    $heads = json_decode((string) ($line['heads'] ?? ''), true); if (!is_array($heads)) $heads = [];
    $names = function_exists('pc_heads') ? pc_heads() : [];
    $per = $basis === 'DAILY' ? 'DAY' : 'MONTH';
    $items = []; $taken = [];
    foreach ($heads as $k => $v) {
        $code = preg_match('/^[A-Z_][A-Z0-9_]{0,23}$/', (string) $k) && !in_array((string) $k, CH_FUNCS, true) ? (string) $k : ch_code_from((string) $k, $taken);
        $taken[] = $code;
        $items[] = ['code' => $code, 'label' => (string) ($names[$k] ?? $k), 'type' => 'AMOUNT', 'per' => $per,
                    'grp' => $k === 'PERSONNEL' ? 'PAY' : ($k === 'STATUTORY' ? 'STAT' : 'COST'), 'note' => '', 'amount' => (float) $v];
    }
    if ((float) ($line['oneoff'] ?? 0) != 0) {
        $items[] = ['code' => ch_code_from('ONE_TIME', $taken), 'label' => 'One-time / mobilisation', 'type' => 'AMOUNT', 'per' => 'ONCE',
                    'grp' => 'COST', 'note' => '', 'amount' => (float) $line['oneoff']];
    }
    return ['v' => 1, 'dpm' => ch_days_per_month(), 'items' => $items, 'legacy' => true];
}

// The unit a costing's heads are shown in.
function ch_unit_for_basis($basis) { return strtoupper((string) $basis) === 'DAILY' ? 'DAY' : 'MONTH'; }

// ---- The library (Masters-level setup) --------------------------------------
// Who changes the library and the templates: the people who change the Masters
// lists, of which the "Costing head" list is one (docs/02 row 12, is_admin_level()).
function ch_can_manage() {
    return (function_exists('is_admin_level') && is_admin_level()) || (function_exists('is_master') && is_master());
}

function ch_migrate() {
    static $doneAt = -1; $ep = function_exists('db_epoch') ? db_epoch() : 0; if ($doneAt === $ep) return; $doneAt = $ep;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS costing_head_library (
            id $pk, code VARCHAR(24) NOT NULL, label VARCHAR(80) DEFAULT '',
            spec TEXT, statutory INT DEFAULT 0, effective_from VARCHAR(10) DEFAULT '',
            active INT DEFAULT 1, sort INT DEFAULT 0,
            updated_by VARCHAR(150) DEFAULT '', updated_at VARCHAR(30) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
        db()->exec("CREATE TABLE IF NOT EXISTS costing_head_templates (
            id $pk, name VARCHAR(120) NOT NULL, note VARCHAR(300) DEFAULT '', items TEXT, dpm DECIMAL(5,2) DEFAULT 0,
            active INT DEFAULT 1, created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    } catch (Throwable $e) {}
    ch_library_seed();
}

// Starting values for India — offered, never imposed: every one is a normal
// library row the company changes (or adds a new value from a date) on the
// Costing heads screen. The owner asked for exactly this (9 Nov 2026: "Yes sure
// … but highly configurable as it changes with time").
function ch_seed_rows() {
    $chk = 'A starting value — confirm it with your accountant, and add a new value from the date the rule changes.';
    $pf = ['BASIC', 'DA'];
    $gross = ['BASIC', 'DA', 'HRA', 'SPECIAL'];
    $rows = [
        ['BASIC',   'Basic salary',              ['type' => 'AMOUNT', 'per' => 'MONTH', 'grp' => 'PAY', 'amount' => 0], 0, ''],
        ['DA',      'Dearness allowance (DA)',   ['type' => 'AMOUNT', 'per' => 'MONTH', 'grp' => 'PAY', 'amount' => 0], 0, ''],
        ['HRA',     'House rent allowance (HRA)',['type' => 'AMOUNT', 'per' => 'MONTH', 'grp' => 'PAY', 'amount' => 0], 0, ''],
        ['SPECIAL', 'Special / other allowance', ['type' => 'AMOUNT', 'per' => 'MONTH', 'grp' => 'PAY', 'amount' => 0], 0, ''],
        ['PF_ER',   'Provident Fund — employer', ['type' => 'PCT', 'per' => 'MONTH', 'grp' => 'STAT', 'pct' => 12, 'of' => $pf, 'cap' => 15000, 'upto' => 0], 1,
            '12% of Basic + DA, counted on wages up to ₹15,000 a month (the PF wage ceiling). If you pay PF on the full basic, clear the "counted up to" figure. ' . $chk],
        ['PF_ADMIN','PF admin charges',          ['type' => 'PCT', 'per' => 'MONTH', 'grp' => 'STAT', 'pct' => 0.5, 'of' => $pf, 'cap' => 15000, 'upto' => 0], 1, $chk],
        ['EDLI',    'EDLI insurance',            ['type' => 'PCT', 'per' => 'MONTH', 'grp' => 'STAT', 'pct' => 0.5, 'of' => $pf, 'cap' => 15000, 'upto' => 0], 1, $chk],
        ['ESIC_ER', 'ESIC — employer',           ['type' => 'PCT', 'per' => 'MONTH', 'grp' => 'STAT', 'pct' => 3.25, 'of' => $gross, 'cap' => 0, 'upto' => 21000], 1,
            '3.25% of gross wages, only while gross is up to ₹21,000 a month. ' . $chk],
        ['BONUS',   'Statutory bonus',           ['type' => 'PCT', 'per' => 'MONTH', 'grp' => 'STAT', 'pct' => 8.33, 'of' => $pf, 'cap' => 7000, 'upto' => 21000], 1,
            'The minimum 8.33%, on Basic + DA counted up to ₹7,000 (or the minimum wage, if higher), for people earning up to ₹21,000. ' . $chk],
        ['GRATUITY','Gratuity provision',        ['type' => 'PCT', 'per' => 'MONTH', 'grp' => 'STAT', 'pct' => 4.81, 'of' => $pf, 'cap' => 0, 'upto' => 0], 1,
            '4.81% of Basic + DA — 15 days\' wages for each year, set aside monthly. ' . $chk],
    ];
    // The cost heads the company already uses (Masters → "Costing head"), as
    // monthly amounts — except food, which is usually costed per day.
    $food = function_exists('pc_defaults') ? (float) (pc_defaults()['food_per_day'] ?? 0) : 0;
    foreach ((function_exists('pc_heads') ? pc_heads() : []) as $k => $lbl) {
        if (in_array($k, ['PERSONNEL', 'STATUTORY'], true)) continue;
        $code = preg_match('/^[A-Z_][A-Z0-9_]{0,23}$/', (string) $k) ? (string) $k : ch_code_from((string) $lbl);
        $per = $k === 'FOOD' ? 'DAY' : 'MONTH';
        $grp = $k === 'SITE_ALLOWANCE' ? 'PAY' : 'COST';
        $rows[] = [$code, (string) $lbl, ['type' => 'AMOUNT', 'per' => $per, 'grp' => $grp, 'amount' => $k === 'FOOD' ? $food : 0], 0, ''];
    }
    return $rows;
}

function ch_library_seed() {
    try {
        if ((int) ops_val("SELECT COUNT(*) FROM costing_head_library") > 0) return;
        $now = date('c'); $sort = 0;
        $ins = db()->prepare("INSERT INTO costing_head_library (code,label,spec,statutory,effective_from,active,sort,updated_by,updated_at,created_at) VALUES (?,?,?,?,?,1,?,?,?,?)");
        foreach (ch_seed_rows() as [$code, $label, $spec, $stat, $note]) {
            $spec['note'] = $note;
            $ins->execute([$code, $label, json_encode($spec, JSON_UNESCAPED_UNICODE), (int) $stat, '2000-01-01', $sort += 10, 'Starting values', $now, $now]);
        }
        $items = [];
        foreach (['BASIC', 'DA', 'HRA', 'SPECIAL', 'PF_ER', 'PF_ADMIN', 'EDLI', 'ESIC_ER', 'BONUS', 'GRATUITY'] as $c) {
            foreach (ch_seed_rows() as [$code, $label, $spec, $stat, $note]) if ($code === $c) $items[] = ch_item_clean(array_merge($spec, ['code' => $code, 'label' => $label, 'note' => $note]))[0];
        }
        db()->prepare("INSERT INTO costing_head_templates (name, note, items, dpm, active, created_by, created_at, updated_at) VALUES (?,?,?,0,1,?,?,?)")
            ->execute(['Payroll role — salary + statutory', 'Fill in Basic, DA, HRA and the allowance; PF, ESIC, bonus and gratuity work themselves out.',
                       json_encode(array_values(array_filter($items)), JSON_UNESCAPED_UNICODE), 'Starting values', $now, $now]);
        if (function_exists('act_log')) try { act_log('COSTING_HEADS', 0, 'SYSTEM', 'Costing head library started with the starting values', ['auto' => 1]); } catch (Throwable $e) {}
    } catch (Throwable $e) {}
}

// The library as it stands on a date: for each head, its latest value that had
// taken effect by then. [code => ['id','code','label','statutory','effective_from', …spec]]
function ch_library($onDate = null, $withInactive = false) {
    ch_migrate();
    $d = $onDate ?: date('Y-m-d');
    $rows = ops_all("SELECT * FROM costing_head_library WHERE effective_from <= ? ORDER BY sort, code, effective_from", [$d]) ?: [];
    $out = [];
    foreach ($rows as $r) {
        $spec = json_decode((string) $r['spec'], true) ?: [];
        $out[$r['code']] = array_merge($spec, ['id' => (int) $r['id'], 'code' => $r['code'], 'label' => $r['label'],
            'statutory' => (int) $r['statutory'], 'effective_from' => $r['effective_from'], 'active' => (int) $r['active'], 'sort' => (int) $r['sort']]);
    }
    if (!$withInactive) $out = array_filter($out, fn($r) => $r['active'] === 1);
    return $out;
}
// Every value a head has had, newest first — and any due from a later date.
function ch_library_versions($code) {
    ch_migrate();
    return ops_all("SELECT * FROM costing_head_library WHERE code=? ORDER BY effective_from DESC, id DESC", [(string) $code]) ?: [];
}
// The head as it should be copied into a role.
function ch_library_item($code, $onDate = null) {
    $lib = ch_library($onDate); $r = $lib[$code] ?? null; if (!$r) return null;
    $raw = $r; $raw['lib_id'] = $r['id']; $raw['lib_from'] = $r['effective_from'];
    return ch_item_clean($raw)[0];
}

// Save a head's value from a date (a new row; the older values stay, so a
// costing priced with them can still say where its figure came from).
function ch_library_save(array $raw, $from, $by) {
    ch_migrate();
    $from = (string) $from;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !strtotime($from)) return [false, 'Give the date this value takes effect from.'];
    [$it, $err] = ch_item_clean($raw);
    if ($err !== '') return [false, $err];
    $code = $it['code'];
    $spec = $it; unset($spec['code'], $spec['label'], $spec['lib_id'], $spec['lib_from']);
    $now = date('c');
    $prev = ops_one("SELECT * FROM costing_head_library WHERE code=? ORDER BY effective_from DESC, id DESC LIMIT 1", [$code]);
    $sort = $prev ? (int) $prev['sort'] : (int) ops_val("SELECT COALESCE(MAX(sort),0)+10 FROM costing_head_library");
    $stat = !empty($raw['statutory']) ? 1 : ($prev ? (int) $prev['statutory'] : 0);
    $same = ops_one("SELECT id FROM costing_head_library WHERE code=? AND effective_from=?", [$code, $from]);
    if ($same) {
        db()->prepare("UPDATE costing_head_library SET label=?, spec=?, statutory=?, active=1, updated_by=?, updated_at=? WHERE id=?")
            ->execute([$it['label'], json_encode($spec, JSON_UNESCAPED_UNICODE), $stat, (string) $by, $now, (int) $same['id']]);
        $id = (int) $same['id'];
    } else {
        db()->prepare("INSERT INTO costing_head_library (code,label,spec,statutory,effective_from,active,sort,updated_by,updated_at,created_at) VALUES (?,?,?,?,?,1,?,?,?,?)")
            ->execute([$code, $it['label'], json_encode($spec, JSON_UNESCAPED_UNICODE), $stat, $from, $sort, (string) $by, $now, $now]);
        $id = (int) db()->lastInsertId();
    }
    if (function_exists('act_log')) try { act_log('COSTING_HEADS', $id, 'UPDATE', 'Costing head "' . $it['label'] . '" (' . $code . ') set from ' . $from . ' by ' . $by, ['code' => $code, 'from' => $from]); } catch (Throwable $e) {}
    return [true, $code];
}

// Retire a head from the library (roles that already use it keep their copy).
function ch_library_set_active($code, $on, $by) {
    ch_migrate();
    db()->prepare("UPDATE costing_head_library SET active=?, updated_by=?, updated_at=? WHERE code=?")->execute([$on ? 1 : 0, (string) $by, date('c'), (string) $code]);
    if (function_exists('act_log')) try { act_log('COSTING_HEADS', 0, 'UPDATE', 'Costing head ' . $code . ($on ? ' brought back' : ' retired') . ' by ' . $by, ['code' => $code]); } catch (Throwable $e) {}
}

// ---- Templates: a ready list of heads to start a role from ------------------
function ch_templates($all = false) {
    ch_migrate();
    return ops_all("SELECT * FROM costing_head_templates" . ($all ? '' : " WHERE active=1") . " ORDER BY name") ?: [];
}
function ch_template($id) { ch_migrate(); return ops_one("SELECT * FROM costing_head_templates WHERE id=?", [(int) $id]) ?: null; }
function ch_template_items($id) {
    $t = ch_template($id); if (!$t) return [];
    $items = json_decode((string) $t['items'], true);
    return is_array($items) ? ch_items_clean($items)[0] : [];
}
function ch_template_save($id, $name, $note, array $items, $by) {
    ch_migrate();
    $name = mb_substr(trim((string) $name), 0, 120);
    if ($name === '') return [false, 'Give the template a name.'];
    [$items, $errs] = ch_items_clean($items);
    if ($errs) return [false, implode(' ', $errs)];
    if (!$items) return [false, 'A template needs at least one head.'];
    $now = date('c'); $json = json_encode($items, JSON_UNESCAPED_UNICODE);
    $dupe = ops_one("SELECT id FROM costing_head_templates WHERE LOWER(name)=LOWER(?) AND id<>? AND active=1", [$name, (int) $id]);
    if ($dupe) return [false, 'There is already a template called "' . $name . '".'];
    if ($id) {
        db()->prepare("UPDATE costing_head_templates SET name=?, note=?, items=?, updated_at=? WHERE id=?")
            ->execute([$name, mb_substr(trim((string) $note), 0, 300), $json, $now, (int) $id]);
    } else {
        db()->prepare("INSERT INTO costing_head_templates (name, note, items, dpm, active, created_by, created_at, updated_at) VALUES (?,?,?,0,1,?,?,?)")
            ->execute([$name, mb_substr(trim((string) $note), 0, 300), $json, (string) $by, $now, $now]);
        $id = (int) db()->lastInsertId();
    }
    if (function_exists('act_log')) try { act_log('COSTING_HEADS', (int) $id, 'UPDATE', 'Costing template "' . $name . '" saved by ' . $by . ' (' . count($items) . ' heads)', []); } catch (Throwable $e) {}
    return [true, (int) $id];
}
function ch_template_set_active($id, $on, $by) {
    ch_migrate();
    db()->prepare("UPDATE costing_head_templates SET active=?, updated_at=? WHERE id=?")->execute([$on ? 1 : 0, date('c'), (int) $id]);
    if (function_exists('act_log')) try { act_log('COSTING_HEADS', (int) $id, 'UPDATE', 'Costing template #' . (int) $id . ($on ? ' brought back' : ' removed') . ' by ' . $by, []); } catch (Throwable $e) {}
}

// One line in words — "12% of Basic + DA, counted up to ₹15,000" — for the
// library list and the printed sheet.
function ch_item_summary(array $it, array $labels = []) {
    $sym = function_exists('cur_sym') ? cur_sym() : '₹';
    $m = fn($v) => $sym . number_format((float) $v, abs((float) $v - round((float) $v)) > 0.004 ? 2 : 0);
    $per = $it['per'] ?? 'MONTH';
    $perW = $per === 'ONCE' ? ' one time' : ($per === 'DAY' ? ' a day' : ($per === 'YEAR' ? ' a year' : ' a month'));
    switch ($it['type'] ?? 'AMOUNT') {
        case 'PCT':
            $of = implode(' + ', array_map(fn($c) => $labels[$c] ?? $c, (array) ($it['of'] ?? [])));
            $s = rtrim(rtrim(number_format((float) ($it['pct'] ?? 0), 3), '0'), '.') . '% of ' . $of;
            if ((float) ($it['cap'] ?? 0) > 0) $s .= ', counted up to ' . $m($it['cap']);
            if ((float) ($it['upto'] ?? 0) > 0) $s .= ', only while it is up to ' . $m($it['upto']);
            if ($per === 'ONCE') $s .= ' (one time)';
            break;
        case 'QTYRATE': $s = rtrim(rtrim(number_format((float) ($it['qty'] ?? 0), 2), '0'), '.') . ' × ' . $m($it['rate'] ?? 0) . $perW; break;
        case 'FORMULA': $s = 'Formula: ' . ($it['expr'] ?? '') . ($per === 'ONCE' ? ' (one time)' : ''); break;
        default: $s = ((float) ($it['amount'] ?? 0) != 0 ? $m($it['amount']) : 'Amount') . $perW;
    }
    if (isset($it['min'])) $s .= ', at least ' . $m($it['min']);
    if (isset($it['max'])) $s .= ', at most ' . $m($it['max']);
    return $s;
}
