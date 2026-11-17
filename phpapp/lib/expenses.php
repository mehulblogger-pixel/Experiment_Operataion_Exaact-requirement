<?php
// ============================================================================
//  EXPENSES PHASE 1 — owner-approved 17 Nov 2026
//  (QA-REPORTS/V51/designs/34-Expense-Screens-Design.html, design v3;
//   QA-REPORTS/V51/36-Expenses-Existing-vs-Needed.md, the reuse table)
// ============================================================================
//  "What the inspector works for, he is getting paid for. It is simple and
//   short. Suggest something wherein no wrong can be done by the inspector."
//
//  Built ON the existing records — nothing here is a second copy:
//   * the claim is the existing `vouchers` row, now with a period (from–to);
//     a month voucher is a period of one month (every old voucher);
//   * a day's expenses are the existing `voucher_entries` lines, written when
//     the claimant closes the day on HIS OWN allocated job (never `expenses`,
//     so profit counts the cost once);
//   * expense types are the existing `expense_heads`, with two more kinds
//     (no bill, fixed per month), a scope (job days / not for a job) and, for a
//     per-day allowance, AUTO (every closed day) or TICK ("Outstation today");
//   * per-person amounts are the existing `inspector_allowances`, now with an
//     effective-from date, a limit and BAND rows (band_id set, inspector_id
//     empty) — the band list (`expense_bands`) is the only new table;
//   * the usual distance is the existing `vendor_km_memory`, now with
//     "approved" — set when a claim with that distance is approved;
//   * the approval chain is lib/voucher_approval.php, unchanged.
//
//  No amount, rate, limit, deadline or period is written in this file: they
//  are company settings (exp_rules()) or rows the Master Admin keeps. The
//  owner's 8 + 8 days are the settings' initial values, seeded once.
//  No new permission and no new voucher status (docs/02 fn 3, docs/03).
// ============================================================================

const EXP_KINDS = [
    'PER_KM'  => 'Per km (own vehicle — km × rate)',
    'BILL'    => 'Bill (amount + photo of the bill)',
    'NOBILL'  => 'No bill (amount + a short note, within a daily limit)',
    'PER_DAY' => 'Per day (an allowance for each day worked)',
    'MONTHLY' => 'Fixed per month (added once a month)',
];
const EXP_SCOPES   = ['JOB' => 'On job days', 'NONJOB' => 'Not for a job'];
const EXP_ADDMODES = ['AUTO' => 'Added by itself on every closed job day', 'TICK' => 'Added when he ticks it (e.g. "Outstation today")'];
const EXP_PERIODS  = ['WEEKLY' => 'Weekly (Monday–Sunday)', 'FORTNIGHTLY' => 'Fortnightly (1–15 and 16–end)', 'MONTHLY' => 'Monthly'];

// ---- Migration (wired into lib/db.php run_schema) --------------------------
function exp_migrate() {
    static $doneAt = -1;
    $ep = function_exists('db_epoch') ? db_epoch() : 0;
    if ($doneAt === $ep) return; $doneAt = $ep;
    if (!function_exists('ensure_column')) return;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        // The ONLY new table (Reuse Gate, doc 36): a band / level list.
        db()->exec("CREATE TABLE IF NOT EXISTS expense_bands (
            id $pk, name VARCHAR(100) DEFAULT '', notes VARCHAR(255) DEFAULT '',
            sort_order INT DEFAULT 100, active INT DEFAULT 1, created_at VARCHAR(30) DEFAULT '')");
        // Expense types — two kinds more, a scope, and AUTO / TICK for per day.
        ensure_column('expense_heads', 'scope', "VARCHAR(10) DEFAULT 'JOB'");
        ensure_column('expense_heads', 'add_mode', "VARCHAR(10) DEFAULT ''");
        // Each person gets a band.
        ensure_column('inspectors', 'band_id', 'INT NULL');
        // Person and band amounts, with a limit and an effective-from date.
        ensure_column('inspector_allowances', 'band_id', 'INT NULL');
        ensure_column('inspector_allowances', 'limit_amount', 'DECIMAL(12,2) NULL');
        ensure_column('inspector_allowances', 'effective_from', "VARCHAR(10) DEFAULT ''");
        // The usual distance becomes "approved" once a claim with it is approved.
        ensure_column('vendor_km_memory', 'approved', 'INT DEFAULT 0');
        // The claim period (from–to) on the same voucher; deadline reopen by N+1.
        ensure_column('vouchers', 'period_start', "VARCHAR(10) DEFAULT ''");
        ensure_column('vouchers', 'period_end', "VARCHAR(10) DEFAULT ''");
        ensure_column('vouchers', 'deadline_open_until', "VARCHAR(10) DEFAULT ''");
        ensure_column('vouchers', 'reopen_asked_at', "VARCHAR(30) DEFAULT ''");
        // A line carries its type, where it came from and what the approver must see.
        ensure_column('voucher_entries', 'receipt_data', 'LONGTEXT');
        ensure_column('voucher_entries', 'receipt_mime', "VARCHAR(80) DEFAULT ''");
        ensure_column('voucher_entries', 'receipt_name', "VARCHAR(200) DEFAULT ''");
        ensure_column('voucher_entries', 'head_code', "VARCHAR(30) DEFAULT ''");
        ensure_column('voucher_entries', 'source', "VARCHAR(12) DEFAULT ''");
        ensure_column('voucher_entries', 'photo_hash', "VARCHAR(64) DEFAULT ''");
        ensure_column('voucher_entries', 'km_reason', "VARCHAR(255) DEFAULT ''");
        ensure_column('voucher_entries', 'usual_km', 'DECIMAL(8,1) NULL');
        ensure_column('voucher_entries', 'limit_amount', 'DECIMAL(12,2) NULL');
        ensure_column('voucher_entries', 'ticked', 'INT DEFAULT 0');
        if (function_exists('act_index')) {
            act_index('voucher_entries', 'idx_ve_photo', '(photo_hash)');
            act_index('inspector_allowances', 'idx_ia_band', '(band_id)');
        }
        // The rules' initial values — the owner's answers (E1, E4), as DATA.
        // Seeded once; the Master Admin changes them on Claim rules.
        foreach (exp_rule_defaults() as $k => $v)
            if (setting_get($k, null) === null) setting_set($k, (string) $v);
    } catch (Throwable $e) { /* never break boot */ }
}
//  The one place the initial values live. The deadlines are the owner's 8 and
//  8 days (E4); the period stays monthly so nothing changes until the owner
//  switches it (E1); the tolerance starts at 0 % (any different km is flagged).
//  "From" is the day the rules started: a day or a claim before it is never
//  locked by them, so no voucher made before this update changes.
function exp_rule_defaults() {
    return ['exp_claim_period' => 'MONTHLY', 'exp_day_deadline_days' => 8, 'exp_claim_deadline_days' => 8,
            'exp_km_tolerance_pct' => 0, 'exp_rules_from' => date('Y-m-d')];
}
function exp_rules() {
    $d = exp_rule_defaults();
    $g = fn($k) => function_exists('setting_get') ? setting_get($k, null) : null;
    $p = strtoupper((string) ($g('exp_claim_period') ?? $d['exp_claim_period']));
    $from = (string) ($g('exp_rules_from') ?? '');
    return [
        'period'     => isset(EXP_PERIODS[$p]) ? $p : 'MONTHLY',
        'day_days'   => max(0, (int) ($g('exp_day_deadline_days') ?? $d['exp_day_deadline_days'])),
        'claim_days' => max(0, (int) ($g('exp_claim_deadline_days') ?? $d['exp_claim_deadline_days'])),
        'km_tol'     => max(0.0, (float) ($g('exp_km_tolerance_pct') ?? $d['exp_km_tolerance_pct'])),
        'from'       => preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : '0000-00-00',
    ];
}
// Settings are the Master Admin's, like the voucher approval setup.
function exp_settings_can() { return function_exists('vappr_config_can') ? vappr_config_can() : (function_exists('is_master') && is_master()); }
function exp_today() { return date('Y-m-d'); }
function exp_add_days($date, $n) { return date('Y-m-d', strtotime($date . ' +' . (int) $n . ' days')); }
function exp_money($n) { return (function_exists('cur_sym') ? cur_sym() : '₹') . (function_exists('nf') ? nf((float) $n, 0) : number_format((float) $n)); }
function exp_valid_date($d) { return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false; }

// ---- Periods (E1) — never across a month end --------------------------------
function exp_period_for($date, $kind = null) {
    $kind = $kind ?: exp_rules()['period'];
    $t = strtotime($date); $ms = date('Y-m-01', $t); $me = date('Y-m-t', $t);
    if ($kind === 'FORTNIGHTLY')
        return (int) date('j', $t) <= 15 ? [$ms, date('Y-m-15', $t)] : [date('Y-m-16', $t), $me];
    if ($kind === 'WEEKLY') {
        $dow = (int) date('N', $t);
        $mon = date('Y-m-d', strtotime($date . ' -' . ($dow - 1) . ' days'));
        $sun = date('Y-m-d', strtotime($mon . ' +6 days'));
        return [max($mon, $ms), min($sun, $me)];
    }
    return [$ms, $me];
}
//  A voucher's from–to. Every voucher made before periods existed is its month.
function voucher_period($v) {
    $ps = (string) ($v['period_start'] ?? ''); $pe = (string) ($v['period_end'] ?? '');
    if (exp_valid_date($ps) && exp_valid_date($pe)) return [$ps, $pe];
    $m = (string) ($v['month'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}$/', $m)) $m = date('Y-m');
    return [$m . '-01', date('Y-m-t', strtotime($m . '-01'))];
}
function exp_is_legacy($v) { return !exp_valid_date((string) ($v['period_start'] ?? '')); }
function exp_period_label($v) {
    [$a, $b] = voucher_period($v);
    if (substr($a, 8) === '01' && $b === date('Y-m-t', strtotime($a))) return date('F Y', strtotime($a));
    return date('j', strtotime($a)) . '–' . date('j M Y', strtotime($b));
}
function exp_period_kind_label($v) {
    [$a, $b] = voucher_period($v);
    $days = (int) round((strtotime($b) - strtotime($a)) / 86400) + 1;
    if (substr($a, 8) === '01' && $b === date('Y-m-t', strtotime($a))) return 'monthly claim';
    return $days <= 7 ? 'weekly claim' : 'fortnightly claim';
}

//  The claim (voucher) holding this person's expenses for a date: the voucher
//  whose period contains it (an old month voucher counts), else a new DRAFT
//  for the period the company setting gives.
function exp_claim_for($insId, $date, $create = true) {
    $insId = (int) $insId; if (!$insId || !exp_valid_date($date)) return null;
    exp_migrate();
    foreach (ops_all("SELECT * FROM vouchers WHERE inspector_id=? AND month=? ORDER BY id", [$insId, substr($date, 0, 7)]) ?: [] as $v) {
        [$a, $b] = voucher_period($v);
        if ($date >= $a && $date <= $b) return $v;
    }
    if (!$create) return null;
    [$ps, $pe] = exp_period_for($date);
    $office = ops_val("SELECT home_office_id FROM inspectors WHERE id=?", [$insId]) ?: (current_user()['home_office_id'] ?? null);
    db()->prepare("INSERT INTO vouchers (inspector_id,office_id,month,period_start,period_end,status,created_by,created_at) VALUES (?,?,?,?,?, 'DRAFT', ?,?)")
        ->execute([$insId, $office ?: null, substr($date, 0, 7), $ps, $pe, function_exists('user_name') ? user_name(current_user()) : '', date('c')]);
    return ops_one("SELECT * FROM vouchers WHERE id=?", [(int) db()->lastInsertId()]);
}

// ---- Expense types ----------------------------------------------------------
function exp_kind($h) {
    $t = strtoupper((string) ($h['head_type'] ?? 'BILL'));
    if ($t === 'ALLOWANCE') return 'PER_DAY';                // the existing "fixed allowance" kind
    return isset(EXP_KINDS[$t]) ? $t : 'BILL';
}
function exp_scope($h) { return strtoupper((string) ($h['scope'] ?? 'JOB')) === 'NONJOB' ? 'NONJOB' : 'JOB'; }
//  A per-day type the owner has not yet marked AUTO is a tick box: no money is
//  added to anybody's day by itself until the owner says so.
function exp_add_mode($h) { return strtoupper((string) ($h['add_mode'] ?? '')) === 'AUTO' ? 'AUTO' : 'TICK'; }
function exp_needs_photo($h) { return exp_kind($h) === 'BILL' && (int) ($h['needs_receipt'] ?? 0) === 1; }
function exp_head($code) {
    static $c = [];
    $code = (string) $code; if ($code === '') return null;
    if (!array_key_exists($code, $c)) $c[$code] = ops_one("SELECT * FROM expense_heads WHERE code=?", [$code]) ?: null;
    return $c[$code];
}
function exp_kind_label($h) {
    $k = exp_kind($h);
    $map = ['PER_KM' => 'Per km', 'BILL' => exp_needs_photo($h) ? 'Bill · photo required' : 'Bill', 'NOBILL' => 'No bill · amount + note',
            'PER_DAY' => 'Per day · ' . (exp_add_mode($h) === 'AUTO' ? 'added by itself on every job day' : 'added when he ticks it'),
            'MONTHLY' => 'Fixed per month · added by itself'];
    return $map[$k] . ($k === 'MONTHLY' ? '' : ' · ' . (exp_scope($h) === 'NONJOB' ? 'not for a job' : 'job days'));
}

// ---- Bands ------------------------------------------------------------------
function exp_bands($activeOnly = false) {
    exp_migrate();
    try { return ops_all("SELECT * FROM expense_bands" . ($activeOnly ? " WHERE active=1" : "") . " ORDER BY sort_order, name") ?: []; }
    catch (Throwable $e) { return []; }
}
function exp_person_band($insId) {
    try { return (int) (ops_val("SELECT band_id FROM inspectors WHERE id=?", [(int) $insId]) ?: 0); } catch (Throwable $e) { return 0; }
}

// ---- Entitlement: person row → band row → default (no amount in code) -----
//  The latest row for that date (effective-from on or before it). A row with
//  allowed = -1 means "follows the band" for whether it may be claimed; its
//  own rate / limit still apply when given.
function exp_ent_latest($who, $id, $kind, $code, $date) {
    $col = $who === 'band' ? 'band_id' : 'inspector_id';
    $other = $who === 'band' ? "(inspector_id IS NULL OR inspector_id=0)" : "(band_id IS NULL OR band_id=0)";
    return ops_one("SELECT * FROM inspector_allowances WHERE $col=? AND $other AND kind=? AND code=? AND COALESCE(effective_from,'')<=?
                    ORDER BY COALESCE(effective_from,'') DESC, id DESC LIMIT 1", [(int) $id, $kind, $code, $date]) ?: null;
}
//  A person with no band keeps today's rule: once any of their own rows of a
//  kind says yes or no, only the ticked ones may be claimed.
function exp_person_restrictive($insId, $kind, $date) {
    $codes = ops_all("SELECT DISTINCT code FROM inspector_allowances WHERE inspector_id=? AND (band_id IS NULL OR band_id=0) AND kind=? AND COALESCE(effective_from,'')<=?",
                     [(int) $insId, $kind, $date]) ?: [];
    foreach ($codes as $c) {
        $r = exp_ent_latest('person', $insId, $kind, $c['code'], $date);
        if ($r && in_array((int) $r['allowed'], [0, 1], true)) return true;
    }
    return false;
}
function exp_entitlement($insId, $kind, $code, $date = null) {
    $date = exp_valid_date((string) $date) ? $date : exp_today();
    $kind = $kind === 'MODE' ? 'MODE' : 'HEAD';
    $p = exp_ent_latest('person', $insId, $kind, $code, $date);
    $band = exp_person_band($insId);
    $b = $band ? exp_ent_latest('band', $band, $kind, $code, $date) : null;
    $def = $kind === 'MODE' ? ops_one("SELECT * FROM travel_modes WHERE code=?", [$code]) : exp_head($code);
    $active = $def && (int) ($def['active'] ?? 1) === 1;
    if ($p && (int) $p['allowed'] !== -1)      { $allowed = (int) $p['allowed'] === 1; $src = 'person'; }
    elseif ($b && (int) $b['allowed'] !== -1)  { $allowed = (int) $b['allowed'] === 1; $src = 'band'; }
    elseif ($band)                             { $allowed = false; $src = 'band'; }
    else {
        $src = 'default';
        $allowed = !exp_person_restrictive($insId, $kind, $date)
            && ($kind === 'MODE' || exp_scope($def ?: []) === 'JOB');      // a not-for-a-job type is never on by default (E5)
    }
    $rate = null; $rateSrc = 'default';
    foreach (['person' => $p, 'band' => $b] as $s => $r)
        if ($r && $r['rate_override'] !== null && $r['rate_override'] !== '') { $rate = (float) $r['rate_override']; $rateSrc = $s; break; }
    if ($rate === null) $rate = (float) ($def['default_rate'] ?? 0);
    $limit = null;
    foreach ([$p, $b] as $r) if ($r && $r['limit_amount'] !== null && $r['limit_amount'] !== '') { $limit = (float) $r['limit_amount']; break; }
    return ['allowed' => $allowed && $active, 'rate' => $rate, 'limit' => $limit, 'source' => $src, 'rate_source' => $rateSrc];
}
//  The types this person may claim on a date, in screen order, with rate / limit.
function exp_allowed_heads($insId, $date = null, array $kinds = null, $scope = null) {
    $out = [];
    foreach (ops_all("SELECT * FROM expense_heads WHERE active=1 ORDER BY sort_order, id") ?: [] as $h) {
        if ($h['code'] === 'KMTRAVEL') continue;
        if ($kinds !== null && !in_array(exp_kind($h), $kinds, true)) continue;
        if ($scope !== null && exp_scope($h) !== $scope) continue;
        $en = exp_entitlement($insId, 'HEAD', $h['code'], $date);
        if (!$en['allowed']) continue;
        $out[] = $h + ['_rate' => $en['rate'], '_limit' => $en['limit'], '_src' => $en['rate_source']];
    }
    return $out;
}
function exp_allowed_modes($insId, $date = null, $perKmOnly = false) {
    $out = [];
    foreach (ops_all("SELECT * FROM travel_modes WHERE active=1 ORDER BY id") ?: [] as $m) {
        if ($perKmOnly && $m['basis'] !== 'PER_KM') continue;
        $en = exp_entitlement($insId, 'MODE', $m['code'], $date);
        if ($en['allowed']) $out[] = $m + ['_rate' => $en['rate'], '_src' => $en['rate_source']];
    }
    return $out;
}

//  Save a set of person or band rows from Applies-from $ef. Rows already
//  saved for an earlier date stay as they are, so an older claim keeps the
//  rate that applied then; a second save on the same date corrects that date.
//  $rows: [[kind, code, allowed(-1|0|1), rate|null, limit|null], …]
function exp_save_rows($who, $id, $ef, array $rows) {
    $id = (int) $id; if (!$id) return;
    $ef = exp_valid_date((string) $ef) ? $ef : exp_today();
    $pdo = db();
    if ($who === 'band') {
        $pdo->prepare("DELETE FROM inspector_allowances WHERE band_id=? AND (inspector_id IS NULL OR inspector_id=0) AND COALESCE(effective_from,'')=?")->execute([$id, $ef]);
        $ins = $pdo->prepare("INSERT INTO inspector_allowances (inspector_id,band_id,kind,code,allowed,rate_override,limit_amount,effective_from) VALUES (NULL,?,?,?,?,?,?,?)");
    } else {
        $pdo->prepare("DELETE FROM inspector_allowances WHERE inspector_id=? AND (band_id IS NULL OR band_id=0) AND COALESCE(effective_from,'')=?")->execute([$id, $ef]);
        $ins = $pdo->prepare("INSERT INTO inspector_allowances (inspector_id,band_id,kind,code,allowed,rate_override,limit_amount,effective_from) VALUES (?,NULL,?,?,?,?,?,?)");
    }
    foreach ($rows as [$kind, $code, $allowed, $rate, $limit]) $ins->execute([$id, $kind, $code, (int) $allowed, $rate, $limit, $ef]);
    if (function_exists('act_log')) try { act_log($who === 'band' ? 'EXPENSE_BAND' : 'INSPECTOR', $id, 'EXPENSE_RATES', 'Expense amounts saved, applying from ' . $ef . '.'); } catch (Throwable $e) {}
}
//  Read a person / band grid post into rows. Field names are the existing
//  allowances screen's (allow_head / head_rate / allow_mode / mode_rate) plus
//  head_limit. allow_*: "1" yes, "0" no, "" or missing = follows the band.
function exp_rows_from_post(array $b, $isBand) {
    $num = function ($v) { $v = trim((string) $v); return $v === '' || !is_numeric($v) || (float) $v < 0 ? null : round((float) $v, 2); };
    $rows = [];
    foreach (['HEAD' => ['allow_head', 'head_rate', 'head_limit', "SELECT code FROM expense_heads"],
              'MODE' => ['allow_mode', 'mode_rate', 'mode_limit', "SELECT code FROM travel_modes"]] as $kind => [$fa, $fr, $fl, $sql]) {
        foreach (ops_all($sql) ?: [] as $r) {
            $code = $r['code'];
            $a = (string) ($b[$fa][$code] ?? '');
            $allowed = $a === '1' ? 1 : ($a === '0' ? 0 : ($isBand ? 0 : -1));
            $rate = $num($b[$fr][$code] ?? ''); $limit = $num($b[$fl][$code] ?? '');
            if (!$isBand && $allowed === -1 && $rate === null && $limit === null) {
                // Nothing said for this code from this date — record "follows" only
                // when it changes what an earlier row said.
                $rows[] = [$kind, $code, -1, null, null];
                continue;
            }
            $rows[] = [$kind, $code, $allowed, $rate, $limit];
        }
    }
    return $rows;
}

// ---- Usual distance (vendor_km_memory + approved) --------------------------
function exp_usual_km($insId, $vendorId) {
    if (!$insId || !$vendorId) return null;
    $r = ops_one("SELECT mode_code, km, approved FROM vendor_km_memory WHERE inspector_id=? AND vendor_id=?", [(int) $insId, (int) $vendorId]);
    return $r ? ['mode' => (string) $r['mode_code'], 'km' => (float) $r['km'], 'approved' => (int) ($r['approved'] ?? 0) === 1] : null;
}
//  Does this km need a reason? Only against an APPROVED usual distance, and
//  only beyond the company's tolerance (0 % = any difference).
function exp_km_differs($km, $usual) {
    if ($usual === null || $usual <= 0) return false;
    $tol = exp_rules()['km_tol'];
    return abs((float) $km - (float) $usual) > ($usual * $tol / 100) + 0.0001;
}
//  A claim with that distance is approved: it becomes the usual distance.
function exp_on_approved($v) {
    try {
        $rows = ops_all("SELECT vendor_id, mode_code, km FROM voucher_entries WHERE voucher_id=? AND vendor_id IS NOT NULL AND km>0 AND mode_code<>'' ORDER BY entry_date, id", [(int) $v['id']]) ?: [];
        $last = []; foreach ($rows as $r) $last[(int) $r['vendor_id']] = $r;
        foreach ($last as $vid => $r) {
            $ex = ops_val("SELECT id FROM vendor_km_memory WHERE inspector_id=? AND vendor_id=?", [(int) $v['inspector_id'], $vid]);
            if ($ex) db()->prepare("UPDATE vendor_km_memory SET mode_code=?, km=?, approved=1 WHERE id=?")->execute([$r['mode_code'], (float) $r['km'], $ex]);
            else db()->prepare("INSERT INTO vendor_km_memory (inspector_id,vendor_id,mode_code,km,approved) VALUES (?,?,?,?,1)")->execute([(int) $v['inspector_id'], $vid, $r['mode_code'], (float) $r['km']]);
        }
    } catch (Throwable $e) {}
}

// ---- Own allocated job days --------------------------------------------------
function exp_job($jobId) {
    return ops_one("SELECT j.*, c.client_id, c.vendor_id, bn.boss_number, cl.display_name cdisp, cl.legal_name cleg,
                           vn.display_name vdisp, vn.legal_name vleg
                    FROM jobs j LEFT JOIN calls c ON c.id=j.call_id LEFT JOIN boss_numbers bn ON bn.id=j.boss_id
                    LEFT JOIN business_partners cl ON cl.id=c.client_id LEFT JOIN business_partners vn ON vn.id=c.vendor_id
                    WHERE j.id=?", [(int) $jobId]) ?: null;
}
function exp_job_client($j) { return (string) (($j['cdisp'] ?? '') ?: ($j['cleg'] ?? '')); }
function exp_job_site($j)   { return (string) (($j['vdisp'] ?? '') ?: (($j['vleg'] ?? '') ?: exp_job_client($j))); }
//  The days this person works the job: his visit rows, else the job's booked
//  dates (the same reading as voucher_generate and the My-Jobs schedule).
function exp_job_days($insId, $job) {
    $insId = (int) $insId; $jid = (int) ($job['id'] ?? 0); $out = [];
    try { foreach (ops_all("SELECT visit_date FROM job_visits WHERE job_id=? AND inspector_id=? ORDER BY visit_date", [$jid, $insId]) ?: [] as $r) $out[] = substr((string) $r['visit_date'], 0, 10); }
    catch (Throwable $e) {}
    if (!$out && (int) ($job['inspector_id'] ?? 0) === $insId) {
        $d = array_filter(array_map(fn($x) => substr(trim($x), 0, 10), explode(',', (string) ($job['inspection_dates'] ?? ''))));
        if (!$d && trim((string) ($job['scheduled_date'] ?? '')) !== '') $d = [substr((string) $job['scheduled_date'], 0, 10)];
        if (!$d && trim((string) ($job['inspection_start_date'] ?? '')) !== '') $d = [substr((string) $job['inspection_start_date'], 0, 10)];
        $out = array_values($d);
    }
    $out = array_values(array_unique(array_filter($out, 'exp_valid_date'))); sort($out);
    return $out;
}
function exp_job_is_mine($insId, $job) {
    if (!$insId || !$job) return false;
    if ((int) ($job['inspector_id'] ?? 0) === (int) $insId) return true;
    try { return (int) ops_val("SELECT COUNT(*) FROM job_visits WHERE job_id=? AND inspector_id=?", [(int) $job['id'], (int) $insId]) > 0; }
    catch (Throwable $e) { return false; }
}
//  V1 — an expense exists only on a day the claimant closed on HIS OWN
//  allocated job: the visit day is closed, or the whole job is.
function exp_day_problem($insId, $job, $date, $closingNow = false) {
    if (!$job) return 'That ' . Tl('job') . ' no longer exists.';
    if (!exp_job_is_mine($insId, $job)) return 'Expenses go only on your own ' . Tlp('job') . ' — this one is not allocated to you.';
    $days = exp_job_days($insId, $job);
    if ($days && !in_array($date, $days, true)) return fdate($date) . ' is not one of your days on ' . $job['job_code'] . '.';
    if ($closingNow) return '';
    if (!empty($job['closed_flag'])) return '';
    try {
        $st = ops_val("SELECT status FROM job_visits WHERE job_id=? AND substr(visit_date,1,10)=? AND (inspector_id=? OR inspector_id IS NULL)", [(int) $job['id'], $date, (int) $insId]);
        if ($st === 'DONE') return '';
    } catch (Throwable $e) {}
    return 'Close the day on ' . $job['job_code'] . ' first — expenses are confirmed when the day is closed.';
}

// ---- Locks: month freeze (existing) + the two deadlines (E4) ---------------
//  The window a reopen (by N+1) or a send-back gives: the claimant may change
//  the claim's days and submit it until then.
function exp_window_until($v) {
    $w = exp_valid_date((string) ($v['deadline_open_until'] ?? '')) ? $v['deadline_open_until'] : '';
    $sb = substr((string) ($v['sent_back_at'] ?? ''), 0, 10);
    if (($v['status'] ?? '') === 'DRAFT' && trim((string) ($v['sent_back_reason'] ?? '')) !== '' && exp_valid_date($sb)) {
        $r = exp_rules(); $s = exp_add_days($sb, max($r['day_days'], $r['claim_days']));
        if ($s > $w) $w = $s;
    }
    return $w;
}
function exp_day_lock($v, $date) {
    $r = exp_rules();
    if (!exp_valid_date((string) $date) || $date < $r['from']) return '';
    $last = exp_add_days($date, $r['day_days']);
    $today = exp_today();
    if ($today <= $last) return '';
    $w = exp_window_until($v); if ($w !== '' && $today <= $w) return '';
    return 'The expenses of ' . fdate($date) . ' could be added or changed until ' . fdate($last) . ' (' . $r['day_days']
         . ' days after the day). That day is now locked — only your N+1 can reopen it, with a reason.';
}
function exp_claim_lock($v) {
    $r = exp_rules();
    [, $pe] = voucher_period($v);
    if ($pe < $r['from']) return '';
    $last = exp_add_days($pe, $r['claim_days']);
    $today = exp_today();
    if ($today <= $last) return '';
    $w = exp_window_until($v); if ($w !== '' && $today <= $w) return '';
    return 'This claim had to be submitted by ' . fdate($last) . ' (' . $r['claim_days'] . ' days after its period ended). '
         . 'It is now locked — only your N+1 can reopen it, with a reason.';
}
//  Some day of this claim is past the day deadline (so a reopen can help).
function exp_some_day_locked($v) {
    [$ps, $pe] = voucher_period($v);
    $first = max($ps, exp_rules()['from']);
    return $first <= $pe && exp_day_lock($v, $first) !== '';
}

// ---- N+1 reopen after a deadline --------------------------------------------
function exp_n1_uid($v) {
    $line = function_exists('vappr_line') ? vappr_line($v, 1) : [];
    return $line ? (int) $line[0]['uid'] : 0;
}
function exp_can_ask_reopen($v) {
    return ($v['status'] ?? '') === 'DRAFT' && voucher_owner_is_me($v) && !voucher_month_frozen($v)
        && (exp_claim_lock($v) !== '' || exp_some_day_locked($v)) && trim((string) ($v['reopen_asked_at'] ?? '')) === '';
}
function exp_reopen_waits_for_me($v) {
    if (($v['status'] ?? '') !== 'DRAFT' || trim((string) ($v['reopen_asked_at'] ?? '')) === '') return false;
    $me = (int) (current_user()['id'] ?? 0);
    return $me && $me === exp_n1_uid($v) && !voucher_owner_is_me($v);
}
function exp_can_deadline_reopen($v) {
    if (($v['status'] ?? '') !== 'DRAFT' || voucher_owner_is_me($v)) return false;
    $me = (int) (current_user()['id'] ?? 0);
    return $me && $me === exp_n1_uid($v) && (exp_claim_lock($v) !== '' || exp_some_day_locked($v));
}

// ---- Who changes lines: the claimant only (V2) -----------------------------
function exp_line_change_problem($v, $date = null) {
    if (!voucher_owner_is_me($v)) return 'Only the person claiming changes the lines of a claim. You may approve it or send it back with a reason.';
    if (($v['status'] ?? '') !== 'DRAFT') return 'This claim has been submitted — it can be changed only after it is sent back.';
    if (voucher_month_frozen($v)) return voucher_lock_reason($v);
    if (($w = exp_claim_lock($v)) !== '') return $w;
    if ($date !== null && ($w = exp_day_lock($v, $date)) !== '') return $w;
    return '';
}

// ---- Lines -------------------------------------------------------------------
function exp_recalc_total($voucherId) {
    $g = (float) ops_val("SELECT COALESCE(SUM(row_total),0) FROM voucher_entries WHERE voucher_id=?", [(int) $voucherId]);
    db()->prepare("UPDATE vouchers SET total=? WHERE id=?")->execute([$g, (int) $voucherId]);
    return $g;
}
//  Same person, same date, same type, same amount — refused as a repeat
//  (owner's point 6). Looks at every claim of the person, old lines included.
function exp_is_duplicate($insId, $date, $code, $amount, $exceptId = 0) {
    $rows = ops_all("SELECT e.id, e.head_code, e.amounts FROM voucher_entries e JOIN vouchers v ON v.id=e.voucher_id
                     WHERE v.inspector_id=? AND e.entry_date=? AND e.id<>?", [(int) $insId, $date, (int) $exceptId]) ?: [];
    foreach ($rows as $r) {
        $amt = expense_extra_decode($r['amounts'] ?? '');
        if (isset($amt[$code]) && abs((float) $amt[$code] - (float) $amount) < 0.005) return true;
    }
    return false;
}
function exp_photo_used($hash, $exceptId = 0) {
    if ($hash === '') return false;
    return (int) ops_val("SELECT COUNT(*) FROM voucher_entries WHERE photo_hash=? AND id<>?", [$hash, (int) $exceptId]) > 0;
}
//  Read an uploaded bill photo: [data|null, mime, name, hash, problem].
function exp_read_photo($f) {
    if (empty($f['tmp_name']) || (int) ($f['error'] ?? 1) !== 0) return [null, '', '', '', ''];
    if ((int) ($f['size'] ?? 0) > 6 * 1024 * 1024) return [null, '', '', '', 'The photo is larger than 6 MB — please take it again at a lower size.'];
    $mime = (string) (($f['type'] ?? '') ?: 'application/octet-stream');
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) return [null, '', '', '', 'Attach the bill as a photo (JPG, PNG, WebP) or a PDF.'];
    $bytes = (string) @file_get_contents($f['tmp_name']);
    if ($bytes === '') return [null, '', '', '', 'The photo could not be read — please take it again.'];
    return [base64_encode($bytes), $mime, substr((string) ($f['name'] ?? 'bill'), 0, 200), hash('sha256', $bytes), ''];
}
//  Insert one line. $l: date, job (row|null), code, amount, note, mode, km,
//  travel, source, ticked, limit, usual, reason, hours, photo [data,mime,name,hash].
function exp_insert_line($v, array $l) {
    $j = $l['job'] ?? null;
    $amt = (float) ($l['amount'] ?? 0); $travel = (float) ($l['travel'] ?? 0);
    $code = (string) ($l['code'] ?? '');
    $amounts = ($code !== '' && $code !== 'KMTRAVEL' && $amt != 0) ? json_encode([$code => $amt]) : '';
    $ph = $l['photo'] ?? [null, '', '', ''];
    db()->prepare("INSERT INTO voucher_entries (voucher_id,entry_date,day_type,job_id,boss_id,client_id,vendor_id,file_no,line_no,sbu,site_label,hours,
                   mode_code,km,travel_amount,amounts,row_total,notes,is_auto,head_code,source,ticked,limit_amount,usual_km,km_reason,receipt_data,receipt_mime,receipt_name,photo_hash)
                   VALUES (?,?, 'WORK', ?,?,?,?,?, '', ?,?,?, ?,?,?,?,?,?,?, ?,?,?,?,?,?, ?,?,?,?)")
        ->execute([(int) $v['id'], $l['date'], $j ? (int) $j['id'] : null, $j['boss_id'] ?? null, $j['client_id'] ?? null, $j['vendor_id'] ?? null,
                   (string) ($j['boss_number'] ?? ''), (string) ($j['sbu'] ?? ''), $j ? exp_job_site($j) : '', (float) ($l['hours'] ?? 0),
                   (string) ($l['mode'] ?? ''), (float) ($l['km'] ?? 0), $travel, $amounts, round($travel + $amt, 2),
                   substr(trim((string) ($l['note'] ?? '')), 0, 255), in_array($l['source'] ?? '', ['DAY', 'MONTHLY'], true) ? 1 : 0,
                   $code, (string) ($l['source'] ?? ''), (int) ($l['ticked'] ?? 0), $l['limit'] ?? null, $l['usual'] ?? null,
                   substr(trim((string) ($l['reason'] ?? '')), 0, 255), $ph[0], (string) $ph[1], (string) $ph[2], (string) ($ph[3] ?? '')]);
    return (int) db()->lastInsertId();
}
//  The day's 8 hours (voucher_generate's reading) are carried once per date.
function exp_day_hours() { return min(8.0, function_exists('hours_cap') ? (float) hours_cap() : 8.0); }
function exp_fix_day_hours($voucherId, $date) {
    $rows = ops_all("SELECT id, hours FROM voucher_entries WHERE voucher_id=? AND entry_date=? AND day_type='WORK' ORDER BY id", [(int) $voucherId, $date]) ?: [];
    if (!$rows) return;
    $sum = array_sum(array_map(fn($r) => (float) $r['hours'], $rows));
    if ($sum <= 0) db()->prepare("UPDATE voucher_entries SET hours=? WHERE id=?")->execute([exp_day_hours(), (int) $rows[0]['id']]);
}

//  Screen 1 — what the day close offers, filled in from his band / profile.
function exp_day_plan($insId, $job, $date) {
    $modes = exp_allowed_modes($insId, $date, true);
    $usual = exp_usual_km($insId, (int) ($job['vendor_id'] ?? 0));
    $mode = null;
    foreach ($modes as $m) if ($usual && $m['code'] === $usual['mode']) $mode = $m;
    if (!$mode && $modes) $mode = $modes[0];
    $per = exp_allowed_heads($insId, $date, ['PER_DAY'], 'JOB');
    $v = exp_claim_for($insId, $date, false);
    $existing = $v ? (ops_all("SELECT * FROM voucher_entries WHERE voucher_id=? AND job_id=? AND entry_date=? ORDER BY id", [(int) $v['id'], (int) $job['id'], $date]) ?: []) : [];
    $prevTravel = null; $prevTicks = [];
    foreach ($existing as $e) {
        if (($e['source'] ?? '') !== 'DAY') continue;
        if ((float) $e['km'] > 0 || $e['head_code'] === 'KMTRAVEL') $prevTravel = $e;
        if ((int) $e['ticked'] === 1) $prevTicks[$e['head_code']] = true;
    }
    return [
        'modes' => $modes, 'mode' => $mode, 'usual' => $usual,
        'km' => $prevTravel ? (float) $prevTravel['km'] : ($usual['km'] ?? 0),
        'mode_code' => $prevTravel ? (string) $prevTravel['mode_code'] : ($mode['code'] ?? ''),
        'reason' => $prevTravel ? (string) $prevTravel['km_reason'] : '',
        'auto' => array_values(array_filter($per, fn($h) => exp_add_mode($h) === 'AUTO' && $h['_rate'] > 0)),
        'tick' => array_values(array_filter($per, fn($h) => exp_add_mode($h) === 'TICK' && $h['_rate'] > 0)),
        'ticked' => $prevTicks,
        'bills' => exp_allowed_heads($insId, $date, ['BILL', 'NOBILL'], 'JOB'),
        'claim' => $v, 'existing' => $existing, 'confirmed' => (bool) $existing,
    ];
}
//  Check what the day close posted, before anything is written. Returns
//  [problem, travel-line|null, allowance-lines].
function exp_day_check($insId, $job, $date, array $post) {
    $plan = exp_day_plan($insId, $job, $date);
    $km = trim((string) ($post['km'] ?? ''));
    if ($km !== '' && (!is_numeric($km) || (float) $km < 0)) return ['The distance must be a number of km (0 or more).', null, []];
    $km = round((float) $km, 1);
    $travel = null;
    if ($km > 0) {
        $mode = null; $code = (string) ($post['mode'] ?? $plan['mode_code']);
        foreach ($plan['modes'] as $m) if ($m['code'] === $code) $mode = $m;
        if (!$mode) return ['Choose how you travelled — only the vehicles your band or profile allows are offered.', null, []];
        $usual = ($plan['usual'] && $plan['usual']['approved']) ? (float) $plan['usual']['km'] : null;
        $reason = trim((string) ($post['km_reason'] ?? ''));
        if (exp_km_differs($km, $usual) && $reason === '')
            return ['The usual distance to ' . exp_job_site($job) . ' is ' . rtrim(rtrim(number_format($usual, 1), '0'), '.') . ' km. Say why it was '
                    . rtrim(rtrim(number_format($km, 1), '0'), '.') . ' km today — your approver will see it.', null, []];
        $travel = ['code' => 'KMTRAVEL', 'mode' => $mode['code'], 'km' => $km, 'travel' => round($km * (float) $mode['_rate'], 2),
                   'usual' => $usual, 'reason' => exp_km_differs($km, $usual) ? $reason : '', 'source' => 'DAY', 'note' => $mode['label']];
    }
    $allow = [];
    foreach ($plan['auto'] as $h) $allow[] = ['code' => $h['code'], 'amount' => (float) $h['_rate'], 'source' => 'DAY', 'limit' => $h['_limit']];
    foreach ($plan['tick'] as $h)
        if (!empty($post['tick'][$h['code']])) $allow[] = ['code' => $h['code'], 'amount' => (float) $h['_rate'], 'source' => 'DAY', 'ticked' => 1, 'limit' => $h['_limit']];
    foreach ($allow as $a)
        if (exp_is_duplicate_day_allowance($insId, $date, $a['code'], (int) $job['id']))
            return [exp_head($a['code'])['label'] . ' is already claimed for ' . fdate($date) . ' on another ' . Tl('job') . ' — one allowance per day.', null, []];
    return ['', $travel, $allow];
}
//  One allowance per day: a per-day allowance already on another job that day.
function exp_is_duplicate_day_allowance($insId, $date, $code, $jobId) {
    return (int) ops_val("SELECT COUNT(*) FROM voucher_entries e JOIN vouchers v ON v.id=e.voucher_id
                          WHERE v.inspector_id=? AND e.entry_date=? AND e.head_code=? AND COALESCE(e.job_id,0)<>?", [(int) $insId, $date, $code, (int) $jobId]) > 0;
}
//  Confirm (or change) the day's expenses: travel + allowances, written to the
//  claim's lines — never to `expenses`. Bills stay as they are. [ok, message, voucher].
function exp_day_confirm($insId, $jobId, $date, array $post, $closingNow = false) {
    $job = exp_job($jobId);
    if (($p = exp_day_problem($insId, $job, $date, $closingNow)) !== '') return [false, $p, null];
    $v = exp_claim_for($insId, $date, true);
    if (!$v) return [false, 'Your claim for ' . fdate($date) . ' could not be opened.', null];
    if (($p = exp_line_change_problem($v, $date)) !== '') return [false, $p, $v];
    [$p, $travel, $allow] = exp_day_check($insId, $job, $date, $post);
    if ($p !== '') return [false, $p, $v];
    $pdo = db();
    $pdo->prepare("DELETE FROM voucher_entries WHERE voucher_id=? AND job_id=? AND entry_date=? AND source='DAY'")->execute([(int) $v['id'], (int) $job['id'], $date]);
    $n = 0;
    if ($travel) { exp_insert_line($v, $travel + ['date' => $date, 'job' => $job]); $n++; }
    foreach ($allow as $a) { exp_insert_line($v, $a + ['date' => $date, 'job' => $job]); $n++; }
    $any = (int) ops_val("SELECT COUNT(*) FROM voucher_entries WHERE voucher_id=? AND job_id=? AND entry_date=?", [(int) $v['id'], (int) $job['id'], $date]);
    if (!$any) exp_insert_line($v, ['date' => $date, 'job' => $job, 'source' => 'DAY', 'note' => 'No expenses this day']);   // the day worked, for the timesheet
    exp_fix_day_hours((int) $v['id'], $date);
    exp_claim_refresh($v);
    $tot = (float) ops_val("SELECT COALESCE(SUM(row_total),0) FROM voucher_entries WHERE voucher_id=? AND job_id=? AND entry_date=?", [(int) $v['id'], (int) $job['id'], $date]);
    return [true, 'Expenses for ' . fdate($date) . ' on ' . $job['job_code'] . ' confirmed — ' . exp_money($tot) . ', in your claim for ' . exp_period_label($v) . '.', $v];
}
//  Add a bill / no-bill line (on a closed job day), or a "not for a job" line.
function exp_add_line($insId, $code, $amount, $date, $jobId, $note, $file = null) {
    $h = exp_head($code);
    if (!$h || (int) $h['active'] !== 1) return [false, 'Choose what kind of expense it is.', null];
    if (!exp_valid_date((string) $date)) return [false, 'Choose the date of the expense.', null];
    $kind = exp_kind($h);
    $job = null;
    if ($jobId) {
        $job = exp_job($jobId);
        if (($p = exp_day_problem($insId, $job, $date)) !== '') return [false, $p, null];
        if (!in_array($kind, ['BILL', 'NOBILL'], true) || exp_scope($h) !== 'JOB') return [false, $h['label'] . ' is not added this way.', null];
    } else {
        if (exp_scope($h) !== 'NONJOB' || !in_array($kind, ['BILL', 'NOBILL'], true))
            return [false, $h['label'] . ' goes on a ' . Tl('job') . ' day — add it when you close the day.', null];
    }
    $en = exp_entitlement($insId, 'HEAD', $code, $date);
    if (!$en['allowed']) return [false, $h['label'] . ' is not one of the expense types your band or profile allows.', null];
    $amount = round((float) $amount, 2);
    if (!is_numeric((string) $amount) || $amount <= 0) return [false, 'Enter the amount.', null];
    $note = trim((string) $note);
    if ($kind === 'NOBILL' && $note === '') return [false, 'A "no bill" expense needs a short note — what was it for?', null];
    if (exp_is_duplicate($insId, $date, $code, $amount))
        return [false, $h['label'] . ' of ' . exp_money($amount) . ' on ' . fdate($date) . ' is already in your claim — the same expense twice is refused.', null];
    $photo = [null, '', '', ''];
    if ($file) {
        [$d, $mime, $name, $hash, $prob] = exp_read_photo($file);
        if ($prob !== '') return [false, $prob, null];
        if ($d !== null) {
            if (exp_photo_used($hash)) return [false, 'This photo has already been used for another bill — each bill is claimed once.', null];
            $photo = [$d, $mime, $name, $hash];
        }
    }
    $v = exp_claim_for($insId, $date, true);
    if (($p = exp_line_change_problem($v, $date)) !== '') return [false, $p, $v];
    exp_insert_line($v, ['date' => $date, 'job' => $job, 'code' => $code, 'amount' => $amount, 'note' => $note,
                         'source' => $job ? 'BILL' : 'NONJOB', 'limit' => $en['limit'], 'photo' => $photo]);
    if ($job) exp_fix_day_hours((int) $v['id'], $date);
    exp_recalc_total((int) $v['id']);
    $warn = $en['limit'] !== null && $amount > $en['limit'] ? ' It is above your limit of ' . exp_money($en['limit']) . ' — your approver will see that.' : '';
    $need = exp_needs_photo($h) && $photo[0] === null ? ' Add the photo of the bill before you submit.' : '';
    return [true, $h['label'] . ' of ' . exp_money($amount) . ' added for ' . fdate($date) . '.' . $warn . $need, $v];
}
//  Fixed-per-month types are added once a month by themselves, at the amount
//  for that month, on the person's own draft claim (never on an old voucher).
function exp_claim_refresh($v) {
    if (!$v || exp_is_legacy($v) || ($v['status'] ?? '') !== 'DRAFT') return;
    [$ps] = voucher_period($v);
    $month = substr($ps, 0, 7);
    foreach (exp_allowed_heads((int) $v['inspector_id'], $ps, ['MONTHLY']) as $h) {
        if ($h['_rate'] <= 0) continue;
        $have = (int) ops_val("SELECT COUNT(*) FROM voucher_entries e JOIN vouchers v ON v.id=e.voucher_id
                               WHERE v.inspector_id=? AND v.month=? AND e.head_code=?", [(int) $v['inspector_id'], $month, $h['code']]);
        if (!$have) exp_insert_line($v, ['date' => $ps, 'code' => $h['code'], 'amount' => (float) $h['_rate'], 'source' => 'MONTHLY', 'note' => 'Fixed per month']);
    }
    exp_recalc_total((int) $v['id']);
}

// ---- Before submit, and what the approver sees in amber ---------------------
function exp_line_parts($e, array $labels) {
    $parts = [];
    if ((float) $e['km'] > 0 || (float) $e['travel_amount'] != 0) {
        $m = (string) $e['mode_code'];
        $ml = $m !== '' ? (string) (ops_val("SELECT label FROM travel_modes WHERE code=?", [$m]) ?: $m) : 'Travel';
        $parts[] = ['label' => $ml . ((float) $e['km'] > 0 ? ' · ' . rtrim(rtrim(number_format((float) $e['km'], 1), '0'), '.') . ' km' : ''), 'amount' => (float) $e['travel_amount'], 'code' => 'KMTRAVEL'];
    }
    foreach (expense_extra_decode($e['amounts'] ?? '') as $c => $a)
        $parts[] = ['label' => $labels[$c] ?? $c, 'amount' => (float) $a, 'code' => $c];
    return $parts;
}
function exp_submit_problems($v) {
    $out = [];
    foreach (ops_all("SELECT * FROM voucher_entries WHERE voucher_id=? ORDER BY entry_date, id", [(int) $v['id']]) ?: [] as $e) {
        $h = exp_head((string) $e['head_code']); if (!$h) continue;
        if (exp_needs_photo($h) && empty($e['receipt_data'])) $out[] = fdate($e['entry_date']) . ': ' . $h['label'] . ' needs the photo of the bill.';
        if (exp_kind($h) === 'NOBILL' && trim((string) $e['notes']) === '') $out[] = fdate($e['entry_date']) . ': ' . $h['label'] . ' needs a short note.';
    }
    return $out;
}
function exp_warnings($v) {
    $out = []; $labels = expense_head_label_map(); $daySum = [];
    $rows = ops_all("SELECT * FROM voucher_entries WHERE voucher_id=? ORDER BY entry_date, id", [(int) $v['id']]) ?: [];
    foreach ($rows as $e) {
        $d = fdate($e['entry_date']);
        if ((float) $e['km'] > 0 && $e['usual_km'] !== null && exp_km_differs((float) $e['km'], (float) $e['usual_km'])) {
            $site = trim((string) $e['site_label']);
            $out[] = ['eid' => (int) $e['id'], 'text' => $d . ': ' . rtrim(rtrim(number_format((float) $e['km'], 1), '0'), '.') . ' km. The usual distance'
                . ($site !== '' ? ' to ' . $site : '') . ' is ' . rtrim(rtrim(number_format((float) $e['usual_km'], 1), '0'), '.') . ' km.'
                . (trim((string) $e['km_reason']) !== '' ? ' Reason given: "' . $e['km_reason'] . '".' : '')];
        }
        $h = exp_head((string) $e['head_code']);
        if ($h && $e['limit_amount'] !== null) {
            $amt = (float) (expense_extra_decode($e['amounts'] ?? '')[$h['code']] ?? 0);
            if (exp_kind($h) === 'NOBILL') { $k = $e['entry_date'] . '|' . $h['code']; $daySum[$k] = ['sum' => ($daySum[$k]['sum'] ?? 0) + $amt, 'limit' => (float) $e['limit_amount'], 'e' => $e, 'h' => $h]; }
            elseif ($amt > (float) $e['limit_amount'] + 0.005)
                $out[] = ['eid' => (int) $e['id'], 'text' => $d . ': ' . $h['label'] . ' ' . exp_money($amt) . ' is above the limit of ' . exp_money($e['limit_amount']) . '.'];
        }
        if ($h && exp_needs_photo($h) && empty($e['receipt_data']))
            $out[] = ['eid' => (int) $e['id'], 'text' => $d . ': ' . $h['label'] . ' has no photo of the bill.'];
    }
    foreach ($daySum as $s)
        if ($s['sum'] > $s['limit'] + 0.005)
            $out[] = ['eid' => (int) $s['e']['id'], 'text' => fdate($s['e']['entry_date']) . ': ' . $s['h']['label'] . ' (no bill) ' . exp_money($s['sum']) . ' is above the daily limit of ' . exp_money($s['limit']) . '.'];
    return $out;
}
//  The claim's lines grouped as screen 3 shows them: one card per date + job,
//  "Other expenses" for lines not on a job, and days not worked.
function exp_claim_groups($entries) {
    $g = [];
    foreach ($entries as $e) {
        $isWork = ($e['day_type'] ?? 'WORK') === 'WORK';
        $k = $e['entry_date'] . '|' . ($isWork ? ((int) $e['job_id'] ? 'J' . (int) $e['job_id'] : 'O') : 'D' . $e['day_type']);
        if (!isset($g[$k])) $g[$k] = ['date' => $e['entry_date'], 'job_id' => (int) $e['job_id'], 'work' => $isWork, 'lines' => [], 'total' => 0.0, 'day_type' => $e['day_type']];
        $g[$k]['lines'][] = $e; $g[$k]['total'] += (float) $e['row_total'];
    }
    ksort($g);
    return array_values($g);
}
//  The day a job close confirms: today when it is one of his days on the job,
//  else his last day on it up to today, else his last day, else today.
function exp_close_default_date($insId, $job) {
    $days = $insId ? exp_job_days($insId, $job) : [];
    $t = exp_today();
    if (in_array($t, $days, true)) return $t;
    $past = array_values(array_filter($days, fn($d) => $d <= $t));
    if ($past) return end($past);
    return $days ? end($days) : $t;
}
//  Is this visit day of the job allocated to this person?
function exp_visit_is_mine($insId, $job, $date) {
    if (!$insId || !$job || !exp_valid_date((string) $date)) return false;
    try {
        $r = ops_one("SELECT inspector_id FROM job_visits WHERE job_id=? AND substr(visit_date,1,10)=?", [(int) $job['id'], $date]);
        if ($r) return (int) ($r['inspector_id'] ?: $job['inspector_id']) === (int) $insId;
    } catch (Throwable $e) {}
    return (int) ($job['inspector_id'] ?? 0) === (int) $insId;
}
