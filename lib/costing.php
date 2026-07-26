<?php
// ===========================================================================
//  Where the money actually goes
//
//  The old answer was one percentage. Every inspector's salary was multiplied
//  by it, and that multiplier stood in for the branch manager, the accountant,
//  the back office, the rent and the laptops — none of which were written down
//  anywhere. It gave a number, but the number was distributed on the wrong
//  basis: in proportion to whichever inspector happened to do the work, rather
//  than to what those costs are actually for.
//
//  This replaces it with real figures, allocated on rules the company chooses:
//
//    Production staff (inspection engineers)
//      · days they worked  → that job's SBU and activity code
//      · days they did not → split by the SBU mix of the work they did do that
//                            month; if there was none, by a method the admin
//                            picks
//      Every rupee of their salary lands on an SBU. That is the test.
//
//    Everybody else (branch manager, coordinator, accountant, back office)
//      · a percentage split across the branch's SBUs, set per month on their
//        own record. Not entered this month? Last month's carries forward.
//
//    Office costs that are not salary (rent, electricity, laptops, contingency)
//      · entered per office per month against heads the company defines, and
//        allocated on the basis chosen for each head.
//
//  Salary never appears as an expense head, and an expense head never contains
//  salary. That is what keeps the two from counting the same rupee twice.
//
//  Months are frozen once calculated: changing somebody's split in September
//  must not silently rewrite August.
// ===========================================================================

// --- how an expense head spreads across the SBUs of an office --------------
const ALLOC_BASES = [
    'EQUAL'     => 'Equally across the SBUs in this office',
    'MANDAYS'   => 'By man-days worked that month',
    'REVENUE'   => 'By revenue earned that month',
    'HEADCOUNT' => 'By the number of people in each SBU',
];
// --- what to do with an inspector who did no chargeable work at all --------
const IDLE_BASES = [
    'OFFICE_MIX' => 'By the office’s overall SBU mix that month',
    'EQUAL'      => 'Equally across the SBUs in this office',
    'OWN_SPLIT'  => 'By a fixed percentage set on their own record',
];
function office_idle_basis($officeId) {
    $v = $officeId ? ops_val("SELECT idle_basis FROM offices WHERE id=?", [$officeId]) : '';
    $v = trim((string)$v);
    if (isset(IDLE_BASES[$v])) return $v;
    $g = trim((string)setting_get('idle_basis', 'OFFICE_MIX'));
    return isset(IDLE_BASES[$g]) ? $g : 'OFFICE_MIX';
}

function costing_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = (db_driver() === 'sqlite') ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';

    // Which SBUs actually operate in this office. Without this there is no
    // answer to "show me ten boxes, one per SBU in the branch".
    ensure_column('offices', 'sbus',       "VARCHAR(300) DEFAULT ''");
    ensure_column('offices', 'idle_basis', "VARCHAR(20) DEFAULT ''");

    // What a person costs, and which side of the line they are on.
    ensure_column('users', 'monthly_ctc',   'DECIMAL(14,2) NULL');
    ensure_column('users', 'is_production', 'INT DEFAULT 0');

    // An inspection away from base: the travel day belongs to the job it is
    // travelling for, not to the pool of idle days.
    ensure_column('calls', 'is_outstation', 'INT DEFAULT 0');
    ensure_column('jobs',  'is_outstation', 'INT DEFAULT 0');

    try {
        // The percentage split for a non-production person, per month. Kept as
        // rows rather than a CSV so a month can be read back exactly as it was
        // entered, and so a changed split never reaches a month already closed.
        db()->exec("CREATE TABLE IF NOT EXISTS person_sbu_split (
            id $pk, user_id INT, yr INT, mon INT, sbu VARCHAR(40),
            pct DECIMAL(6,2) DEFAULT 0, set_by VARCHAR(120) DEFAULT '', set_at VARCHAR(30) DEFAULT '')");
        // The heads the company defines for its own office costs. Never salary.
        db()->exec("CREATE TABLE IF NOT EXISTS office_expense_heads (
            id $pk, code VARCHAR(40), label VARCHAR(150),
            alloc_basis VARCHAR(20) DEFAULT 'EQUAL', notes VARCHAR(255) DEFAULT '',
            sort_order INT DEFAULT 0, is_active INT DEFAULT 1)");
        // What each office actually spent under each head, month by month.
        db()->exec("CREATE TABLE IF NOT EXISTS office_expenses (
            id $pk, office_id INT, yr INT, mon INT, head_id INT,
            amount DECIMAL(14,2) DEFAULT 0, notes VARCHAR(255) DEFAULT '',
            set_by VARCHAR(120) DEFAULT '', set_at VARCHAR(30) DEFAULT '')");
        // The frozen answer. One row per month per office per SBU per source.
        db()->exec("CREATE TABLE IF NOT EXISTS cost_allocations (
            id $pk, yr INT, mon INT, office_id INT, sbu VARCHAR(40),
            activity VARCHAR(60) DEFAULT '', boss_id INT NULL, job_id INT NULL,
            source_kind VARCHAR(30), source_id INT NULL, source_label VARCHAR(200) DEFAULT '',
            basis VARCHAR(30) DEFAULT '', amount DECIMAL(14,2) DEFAULT 0,
            created_at VARCHAR(30) DEFAULT '')");
        db()->exec("CREATE TABLE IF NOT EXISTS cost_runs (
            id $pk, yr INT, mon INT, office_id INT, status VARCHAR(20) DEFAULT 'OPEN',
            total DECIMAL(14,2) DEFAULT 0, note VARCHAR(255) DEFAULT '',
            run_by VARCHAR(120) DEFAULT '', run_at VARCHAR(30) DEFAULT '')");
    } catch (Throwable $e) {}

    costing_seed_heads();
}

// A starting set, so the screen is not an empty box on day one. Salary is
// deliberately absent — it comes from the people, and putting it here as well
// is exactly the double count this design exists to avoid.
function costing_seed_heads() {
    try {
        // Once. A company that deletes every head we shipped means it — putting
        // them back on the next page load would make the list ours, not theirs.
        if (setting_get('expense_heads_seeded', '') === '1') return;
        setting_set('expense_heads_seeded', '1');
        if ((int)ops_val("SELECT COUNT(*) FROM office_expense_heads") > 0) return;
        $seed = [
            ['RENT',        'Office rent',                    'EQUAL',     10],
            ['ELECTRICITY', 'Electricity & utilities',        'EQUAL',     20],
            ['INTERNET',    'Internet & telephone',           'HEADCOUNT', 30],
            ['LAPTOP',      'Laptops & IT equipment',         'HEADCOUNT', 40],
            ['SOFTWARE',    'Software & subscriptions',       'HEADCOUNT', 50],
            ['TRAVEL_ADMIN','Administrative travel',          'MANDAYS',   60],
            ['REPAIRS',     'Repairs & maintenance',          'EQUAL',     70],
            ['STATIONERY',  'Printing & stationery',          'HEADCOUNT', 80],
            ['PROF_FEES',   'Professional & audit fees',      'REVENUE',   90],
            ['OVERHEAD',    'Other overheads',                'EQUAL',    100],
            ['CONTINGENCY', 'Contingency',                    'REVENUE',  110],
        ];
        foreach ($seed as [$c, $l, $b, $o])
            db()->prepare("INSERT INTO office_expense_heads (code,label,alloc_basis,sort_order,is_active)
                           VALUES (?,?,?,?,1)")->execute([$c, $l, $b, $o]);
    } catch (Throwable $e) {}
}

// "2026-07" → [2026, 7]. Anything unreadable falls back to the current month,
// so a hand-typed URL can never land the screen on year zero.
function ym_parse($s) {
    $s = trim((string)$s);
    if (preg_match('/^(\d{4})-(\d{1,2})$/', $s, $m)) {
        $yr = (int)$m[1]; $mon = (int)$m[2];
        if ($yr >= 2000 && $yr <= 2100 && $mon >= 1 && $mon <= 12) return [$yr, $mon];
    }
    return [(int)date('Y'), (int)date('n')];
}

// ---------------------------------------------------------------------------
//  The expense heads themselves
//
//  Every one of them is a row the company owns: it can be renamed, its basis
//  changed, a new one added or an old one retired, all from Masters → Office
//  expense heads. The list above is only what a brand-new database starts with
//  so the entry screen is not blank on day one.
// ---------------------------------------------------------------------------
function expense_heads($activeOnly = true) {
    $w = $activeOnly ? "WHERE COALESCE(is_active,1) = 1" : '';
    return ops_all("SELECT * FROM office_expense_heads $w ORDER BY sort_order, label");
}

// The heads to show on one month's entry screen: the ones in use, plus any
// retired head that still holds money for that month. Retiring a head must not
// make the rupees already booked under it disappear from the screen while they
// are still in the total — that is how a month stops adding up.
function expense_heads_for_month($officeId, $yr, $mon) {
    $heads = expense_heads(true);
    $have  = array_column($heads, null, 'id');
    foreach (office_expense_map($officeId, $yr, $mon) as $hid => $r) {
        if (isset($have[$hid]) || (float)$r['amount'] <= 0) continue;
        $h = ops_one("SELECT * FROM office_expense_heads WHERE id=?", [$hid]);
        if ($h) { $h['retired'] = 1; $heads[] = $h; }
    }
    return $heads;
}

// What one office entered under each head for one month, keyed by head.
function office_expense_map($officeId, $yr, $mon) {
    $rows = ops_all("SELECT * FROM office_expenses WHERE office_id=? AND yr=? AND mon=?",
                    [(int)$officeId, (int)$yr, (int)$mon]);
    $out = []; foreach ($rows as $r) $out[(int)$r['head_id']] = $r;
    return $out;
}
function office_expense_total($officeId, $yr, $mon) {
    return (float)ops_val("SELECT COALESCE(SUM(amount),0) FROM office_expenses WHERE office_id=? AND yr=? AND mon=?",
                          [(int)$officeId, (int)$yr, (int)$mon]);
}

// Save a whole month in one go: [head_id => amount]. A blank or zero amount
// removes the row rather than storing a zero, so "nothing spent" and "not yet
// entered" do not look the same on the screen.
function office_expenses_save($officeId, $yr, $mon, array $amounts, array $notes = []) {
    $officeId = (int)$officeId; $yr = (int)$yr; $mon = (int)$mon;
    $have = office_expense_map($officeId, $yr, $mon);
    $who  = user_name(current_user()); $now = date('c'); $n = 0;
    foreach ($amounts as $headId => $raw) {
        $headId = (int)$headId;
        $amt = trim((string)$raw);
        $amt = ($amt === '') ? 0.0 : (float)str_replace([',', ' '], '', $amt);
        $note = trim((string)($notes[$headId] ?? ''));
        if ($amt <= 0 && $note === '') {
            if (isset($have[$headId])) {
                db()->prepare("DELETE FROM office_expenses WHERE id=?")->execute([$have[$headId]['id']]); $n++;
            }
            continue;
        }
        if (isset($have[$headId])) {
            db()->prepare("UPDATE office_expenses SET amount=?, notes=?, set_by=?, set_at=? WHERE id=?")
                ->execute([$amt, $note, $who, $now, $have[$headId]['id']]);
        } else {
            db()->prepare("INSERT INTO office_expenses (office_id,yr,mon,head_id,amount,notes,set_by,set_at)
                           VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$officeId, $yr, $mon, $headId, $amt, $note, $who, $now]);
        }
        $n++;
    }
    return $n;
}

// Copy a month forward. Rent and the like repeat; retyping twelve numbers a
// month is how figures stop being entered at all.
function office_expenses_copy($officeId, $fromYr, $fromMon, $toYr, $toMon) {
    $src = office_expense_map($officeId, $fromYr, $fromMon);
    if (!$src) return 0;
    $amounts = []; $notes = [];
    foreach ($src as $hid => $r) { $amounts[$hid] = $r['amount']; $notes[$hid] = $r['notes'] ?? ''; }
    return office_expenses_save($officeId, $toYr, $toMon, $amounts, $notes);
}

// ---------------------------------------------------------------------------
//  The SBUs of an office
// ---------------------------------------------------------------------------
function office_sbus($officeId) {
    $all = lk_options_or('sbu', OPS_SBUS);
    $csv = $officeId ? trim((string)ops_val("SELECT sbus FROM offices WHERE id=?", [$officeId])) : '';
    $codes = array_values(array_filter(array_map('trim', explode(',', $csv))));
    $codes = array_values(array_intersect($codes, array_keys($all)));
    // An office that has not said which SBUs it runs is treated as running all
    // of them — otherwise its costs would have nowhere to land.
    if (!$codes) $codes = array_keys($all);
    $out = [];
    foreach ($codes as $c) $out[$c] = $all[$c];
    return $out;
}

// ---------------------------------------------------------------------------
//  A person's percentage split for one month
//
//  Not entered this month? The most recent earlier month carries forward. That
//  is a read-time fallback, not a write — so a month nobody touched still shows
//  the right answer without filling the table with copies.
// ---------------------------------------------------------------------------
function person_split($userId, $yr, $mon) {
    $userId = (int)$userId;
    $rows = ops_all("SELECT sbu, pct FROM person_sbu_split WHERE user_id=? AND yr=? AND mon=? AND pct>0",
                    [$userId, (int)$yr, (int)$mon]);
    if ($rows) return array_column($rows, 'pct', 'sbu');
    // carry forward: the newest month before this one
    $prev = ops_one("SELECT yr, mon FROM person_sbu_split WHERE user_id=? AND (yr < ? OR (yr = ? AND mon < ?))
                     ORDER BY yr DESC, mon DESC", [$userId, (int)$yr, (int)$yr, (int)$mon]);
    if (!$prev) return [];
    $rows = ops_all("SELECT sbu, pct FROM person_sbu_split WHERE user_id=? AND yr=? AND mon=? AND pct>0",
                    [$userId, (int)$prev['yr'], (int)$prev['mon']]);
    return array_column($rows, 'pct', 'sbu');
}
function person_split_is_carried($userId, $yr, $mon) {
    return !ops_all("SELECT id FROM person_sbu_split WHERE user_id=? AND yr=? AND mon=?",
                    [(int)$userId, (int)$yr, (int)$mon]);
}
// Splits are only meaningful when they add to 100.
function split_total(array $split) { return round(array_sum(array_map('floatval', $split)), 2); }

// ---------------------------------------------------------------------------
//  Is this month already closed?
// ---------------------------------------------------------------------------
function cost_run_status($yr, $mon, $officeId) {
    $r = ops_one("SELECT * FROM cost_runs WHERE yr=? AND mon=? AND office_id=?", [(int)$yr, (int)$mon, (int)$officeId]);
    return $r ?: null;
}
function cost_month_frozen($yr, $mon, $officeId) {
    $r = cost_run_status($yr, $mon, $officeId);
    return $r && ($r['status'] ?? '') === 'FROZEN';
}

// ===========================================================================
//  The engine
//
//  Everything below turns one month for one office into rows in
//  cost_allocations. It is written so that the total it produces equals the
//  total the office actually spends — no rupee invented, none lost. The tests
//  assert exactly that.
// ===========================================================================

// A day the inspector worked, resolved to the job it belongs to. Travel days on
// an outstation job carry no job of their own on the voucher; by the owner's
// rule they belong to the inspection they are travelling for, so a dayless
// travel row takes the job of the next dated row that has one.
function costing_inspector_days($inspectorId, $yr, $mon) {
    $from = sprintf('%04d-%02d-01', $yr, $mon);
    $to   = date('Y-m-t', strtotime($from));
    $rows = ops_all("SELECT e.*, j.sbu job_sbu, j.activity_id, j.boss_id job_boss, j.is_outstation
                     FROM voucher_entries e
                     LEFT JOIN vouchers v ON v.id = e.voucher_id
                     LEFT JOIN jobs j ON j.id = e.job_id
                     WHERE v.inspector_id = ? AND e.entry_date >= ? AND e.entry_date <= ?
                     ORDER BY e.entry_date, e.id", [(int)$inspectorId, $from, $to]);
    // forward-fill the job for travel rows that name none
    for ($i = 0; $i < count($rows); $i++) {
        if ((int)($rows[$i]['job_id'] ?? 0) > 0) continue;
        $isTravel = (float)($rows[$i]['km'] ?? 0) > 0 || (float)($rows[$i]['travel_amount'] ?? 0) > 0;
        if (!$isTravel) continue;
        for ($k = $i + 1; $k < count($rows); $k++) {
            if ((int)($rows[$k]['job_id'] ?? 0) > 0) {
                $rows[$i]['job_id']     = $rows[$k]['job_id'];
                $rows[$i]['job_sbu']    = $rows[$k]['job_sbu'];
                $rows[$i]['activity_id']= $rows[$k]['activity_id'];
                $rows[$i]['job_boss']   = $rows[$k]['job_boss'];
                $rows[$i]['carried']    = 1;      // for the explain view
                break;
            }
        }
    }
    return $rows;
}

// One inspector, one month → [['sbu'=>..,'activity'=>..,'boss_id'=>..,'job_id'=>..,'days'=>..], …]
// plus the count of days that were not chargeable to anything.
function costing_inspector_month($inspectorId, $yr, $mon) {
    $worked = []; $productive = 0;
    foreach (costing_inspector_days($inspectorId, $yr, $mon) as $e) {
        if (($e['day_type'] ?? '') !== 'WORK') continue;
        $jid = (int)($e['job_id'] ?? 0);
        if (!$jid) continue;                                  // work with no job names nothing
        $sbu = trim((string)($e['job_sbu'] ?: $e['sbu'] ?: ''));
        $key = $jid . '|' . $sbu;
        if (!isset($worked[$key])) $worked[$key] = ['sbu'=>$sbu, 'activity'=>(string)($e['activity_id'] ?? ''),
            'boss_id'=>(int)($e['job_boss'] ?? 0) ?: null, 'job_id'=>$jid, 'days'=>0];
        $worked[$key]['days'] += 1;
        $productive += 1;
    }
    $available = working_days_in_month($yr, $mon);
    return ['worked' => array_values($worked), 'productive' => $productive,
            'available' => $available, 'idle' => max(0, $available - $productive)];
}

// The SBU mix of an office for a month — used when an inspector did nothing
// chargeable at all, so their salary still lands somewhere rather than vanishing.
function costing_office_mix($officeId, $yr, $mon) {
    $from = sprintf('%04d-%02d-01', $yr, $mon);
    $to   = date('Y-m-t', strtotime($from));
    $rows = ops_all("SELECT j.sbu, COUNT(*) n FROM voucher_entries e
                     LEFT JOIN jobs j ON j.id = e.job_id
                     WHERE e.day_type='WORK' AND e.job_id IS NOT NULL AND j.sbu <> ''
                       AND j.executing_office_id = ? AND e.entry_date >= ? AND e.entry_date <= ?
                     GROUP BY j.sbu", [(int)$officeId, $from, $to]);
    $mix = []; foreach ($rows as $r) $mix[$r['sbu']] = (float)$r['n'];
    return $mix;
}

// Turn any set of weights into shares of $amount that add back to $amount
// exactly — the rounding remainder goes on the largest share, so the total is
// never a rupee out.
function costing_spread($amount, array $weights) {
    $amount = round((float)$amount, 2);
    $sum = array_sum(array_map('floatval', $weights));
    if ($sum <= 0 || !$weights) return [];
    $out = []; $running = 0; $biggest = null; $biggestW = -1;
    foreach ($weights as $k => $w) {
        $w = (float)$w; if ($w <= 0) continue;
        $share = round($amount * $w / $sum, 2);
        $out[$k] = $share; $running += $share;
        if ($w > $biggestW) { $biggestW = $w; $biggest = $k; }
    }
    if ($biggest !== null && abs($running - $amount) >= 0.01)
        $out[$biggest] = round($out[$biggest] + ($amount - $running), 2);
    return $out;
}

// ---------------------------------------------------------------------------
//  Run one month for one office
//
//  Returns the rows it would write, so the screen can show a preview before
//  anything is committed. $commit writes them and marks the run.
// ---------------------------------------------------------------------------
function costing_run($yr, $mon, $officeId, $commit = false) {
    $yr = (int)$yr; $mon = (int)$mon; $officeId = (int)$officeId;
    $rows = []; $warn = [];
    $sbus = office_sbus($officeId);
    $add = function ($sbu, $amount, $kind, $id, $label, $basis, $activity = '', $boss = null, $job = null) use (&$rows) {
        if (round((float)$amount, 2) == 0) return;
        $rows[] = ['sbu'=>$sbu, 'activity'=>$activity, 'boss_id'=>$boss, 'job_id'=>$job,
                   'source_kind'=>$kind, 'source_id'=>$id, 'source_label'=>$label,
                   'basis'=>$basis, 'amount'=>round((float)$amount, 2)];
    };

    // ---- 1. production staff: the work they did, then what they did not ----
    $insp = ops_all("SELECT id, name, COALESCE(salary_ctc,0) salary_ctc, COALESCE(agency_cost,0) agency_cost
                     FROM inspectors WHERE home_office_id = ? AND status='ACTIVE'", [$officeId]);
    foreach ($insp as $i) {
        $monthly = ((float)$i['salary_ctc'] / 12) + (float)$i['agency_cost'];
        if ($monthly <= 0) { $warn[] = $i['name'] . ' has no salary on file, so their time is not costed.'; continue; }
        $m = costing_inspector_month($i['id'], $yr, $mon);
        if ($m['available'] <= 0) continue;
        $daily = $monthly / $m['available'];

        $mix = [];
        foreach ($m['worked'] as $w) {
            if ($w['sbu'] === '') { $warn[] = $i['name'] . ' worked days on a job with no ' . Tl('sbu') . '.'; continue; }
            $add($w['sbu'], $daily * $w['days'], 'INSPECTOR_WORKED', (int)$i['id'],
                 $i['name'] . ' — ' . $w['days'] . ' day(s) worked', 'DAYS_WORKED',
                 (string)$w['activity'], $w['boss_id'], $w['job_id']);
            $mix[$w['sbu']] = ($mix[$w['sbu']] ?? 0) + $w['days'];
        }
        if ($m['idle'] > 0) {
            $idleAmt = $daily * $m['idle'];
            // by their own month's mix; with no mix at all, by the office rule
            $weights = $mix;
            $basis = 'OWN_MIX';
            if (!$weights) {
                $basis = office_idle_basis($officeId);
                if ($basis === 'OFFICE_MIX') $weights = costing_office_mix($officeId, $yr, $mon);
                if ($basis === 'OWN_SPLIT')  $weights = person_split_for_inspector($i['id'], $yr, $mon);
                if (!$weights) { $basis = 'EQUAL'; $weights = array_fill_keys(array_keys($sbus), 1); }
            }
            foreach (costing_spread($idleAmt, $weights) as $sbu => $amt)
                $add($sbu, $amt, 'INSPECTOR_IDLE', (int)$i['id'],
                     $i['name'] . ' — ' . $m['idle'] . ' non-chargeable day(s)', $basis);
        }
    }

    // ---- 2. everybody else: their own percentage split ---------------------
    $people = ops_all("SELECT id, first_name, last_name, username, COALESCE(monthly_ctc,0) monthly_ctc
                       FROM users WHERE home_office_id = ? AND is_active = 1
                         AND COALESCE(is_production,0) = 0 AND COALESCE(monthly_ctc,0) > 0", [$officeId]);
    foreach ($people as $p) {
        $nm = trim($p['first_name'] . ' ' . $p['last_name']) ?: $p['username'];
        $split = person_split($p['id'], $yr, $mon);
        $tot = split_total($split);
        if (!$split) {
            $warn[] = $nm . ' has a salary but no ' . Tl('sbu') . ' split, so it was spread equally.';
            $split = array_fill_keys(array_keys($sbus), 100 / max(1, count($sbus)));
            $tot = 100;
        } elseif (abs($tot - 100) > 0.01) {
            $warn[] = $nm . '’s split adds to ' . $tot . '%, not 100 — it was scaled to fit.';
        }
        foreach (costing_spread((float)$p['monthly_ctc'], $split) as $sbu => $amt)
            $add($sbu, $amt, 'STAFF_SALARY', (int)$p['id'], $nm, 'PERSON_SPLIT');
    }

    // ---- 3. office costs that are not salary -------------------------------
    $heads = ops_all("SELECT oe.*, h.code, h.label, h.alloc_basis
                      FROM office_expenses oe JOIN office_expense_heads h ON h.id = oe.head_id
                      WHERE oe.office_id=? AND oe.yr=? AND oe.mon=? AND oe.amount > 0", [$officeId, $yr, $mon]);
    $mixMandays = costing_office_mix($officeId, $yr, $mon);
    foreach ($heads as $h) {
        $basis = isset(ALLOC_BASES[$h['alloc_basis']]) ? $h['alloc_basis'] : 'EQUAL';
        $weights = [];
        if ($basis === 'MANDAYS')        $weights = $mixMandays;
        elseif ($basis === 'REVENUE')    $weights = costing_office_revenue($officeId, $yr, $mon);
        elseif ($basis === 'HEADCOUNT')  $weights = costing_office_headcount($officeId, $yr, $mon);
        if (!$weights) { $basis = 'EQUAL'; $weights = array_fill_keys(array_keys($sbus), 1); }
        foreach (costing_spread((float)$h['amount'], $weights) as $sbu => $amt)
            $add($sbu, $amt, 'OFFICE_EXPENSE', (int)$h['head_id'], $h['label'], $basis);
    }

    $total = 0; foreach ($rows as $r) $total += $r['amount'];
    if ($commit) {
        db()->prepare("DELETE FROM cost_allocations WHERE yr=? AND mon=? AND office_id=?")->execute([$yr, $mon, $officeId]);
        $ins = db()->prepare("INSERT INTO cost_allocations
            (yr,mon,office_id,sbu,activity,boss_id,job_id,source_kind,source_id,source_label,basis,amount,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        foreach ($rows as $r)
            $ins->execute([$yr, $mon, $officeId, $r['sbu'], $r['activity'], $r['boss_id'], $r['job_id'],
                           $r['source_kind'], $r['source_id'], $r['source_label'], $r['basis'], $r['amount'], date('c')]);
        db()->prepare("DELETE FROM cost_runs WHERE yr=? AND mon=? AND office_id=?")->execute([$yr, $mon, $officeId]);
        db()->prepare("INSERT INTO cost_runs (yr,mon,office_id,status,total,note,run_by,run_at)
                       VALUES (?,?,?,'OPEN',?,?,?,?)")
            ->execute([$yr, $mon, $officeId, round($total, 2), implode(' ', array_slice($warn, 0, 5)),
                       user_name(current_user()), date('c')]);
        idems_log('office', $officeId, 'COST_RUN', ['field'=>sprintf('%04d-%02d', $yr, $mon),
                  'new'=>'total ' . round($total, 2)]);
    }
    return ['rows' => $rows, 'total' => round($total, 2), 'warnings' => array_values(array_unique($warn))];
}

// An inspector's own fixed split, for the rare "did nothing chargeable" case
// where the office rule says to use it. Reads the same table as everybody else,
// through the linked login if there is one.
function person_split_for_inspector($inspectorId, $yr, $mon) {
    $uid = (int)ops_val("SELECT id FROM users WHERE inspector_id=?", [(int)$inspectorId]);
    return $uid ? person_split($uid, $yr, $mon) : [];
}

// Revenue earned per SBU in the month — for heads allocated by revenue.
function costing_office_revenue($officeId, $yr, $mon) {
    $from = sprintf('%04d-%02d-01', $yr, $mon);
    $to   = date('Y-m-t', strtotime($from));
    $rows = ops_all("SELECT sbu, COALESCE(SUM(COALESCE(invoice_amount, expected_credit, 0)),0) v
                     FROM jobs WHERE executing_office_id=? AND sbu <> ''
                       AND COALESCE(inspection_end_date, inspection_start_date) >= ?
                       AND COALESCE(inspection_end_date, inspection_start_date) <= ?
                     GROUP BY sbu", [(int)$officeId, $from, $to]);
    $out = []; foreach ($rows as $r) if ((float)$r['v'] > 0) $out[$r['sbu']] = (float)$r['v'];
    return $out;
}
// People per SBU — for heads allocated by headcount. An inspection engineer
// counts towards the SBUs they actually worked in; everybody else towards the
// SBUs their split names.
function costing_office_headcount($officeId, $yr, $mon) {
    $out = costing_office_mix($officeId, $yr, $mon);   // engineers, by where they worked
    foreach ($out as $k => $v) $out[$k] = 0;           // want heads, not days
    foreach (array_keys(costing_office_mix($officeId, $yr, $mon)) as $s) $out[$s] = ($out[$s] ?? 0) + 1;
    $people = ops_all("SELECT id FROM users WHERE home_office_id=? AND is_active=1
                       AND COALESCE(is_production,0)=0", [(int)$officeId]);
    foreach ($people as $p)
        foreach (person_split($p['id'], $yr, $mon) as $sbu => $pct)
            if ((float)$pct > 0) $out[$sbu] = ($out[$sbu] ?? 0) + ((float)$pct / 100);
    return array_filter($out, function ($v) { return $v > 0; });
}

// ---------------------------------------------------------------------------
//  Does this office have real figures, or is it still on the old percentage?
//
//  Said out loud on every screen that shows a cost, because a number computed
//  two different ways for two different branches, with nothing saying which,
//  is worse than either.
// ---------------------------------------------------------------------------
function office_uses_real_costs($officeId) {
    if (!$officeId) return false;
    $sal = (int)ops_val("SELECT COUNT(*) FROM users WHERE home_office_id=? AND is_active=1
                         AND COALESCE(monthly_ctc,0) > 0", [(int)$officeId]);
    $exp = (int)ops_val("SELECT COUNT(*) FROM office_expenses WHERE office_id=? AND amount>0", [(int)$officeId]);
    return $sal > 0 || $exp > 0;
}
function office_cost_basis_text($officeId) {
    return office_uses_real_costs($officeId)
        ? 'Real salaries and office costs, allocated to SBUs.'
        : 'Still on the old overhead percentage — no salaries or office costs have been entered for this '
          . Tl('office') . ' yet.';
}

// ---------------------------------------------------------------------------
//  What the person form needs to draw the cost panel
//
//  Kept here rather than in the screen so the normal render and the one after
//  a rejected password show exactly the same thing — the second is where a
//  half-built form goes wrong, and did once already.
// ---------------------------------------------------------------------------
function user_cost_vars($user) {
    $canSalary = function_exists('can_see_salary') ? can_see_salary() : false;
    $officeId  = (int)($user['home_office_id'] ?? 0);
    $uid       = (int)($user['id'] ?? 0);
    $yr = (int)date('Y'); $mon = (int)date('n');
    // A person with no office yet still needs boxes, so fall back to every SBU.
    $sbus = $officeId ? office_sbus($officeId) : lk_options_or('sbu', OPS_SBUS);
    // A form handed back after a rejected save must show what was typed, not
    // what is in the database.
    $typed = (array)($user['sbu_pct'] ?? []);
    $split = $typed ? array_filter(array_map('trim', array_map('strval', $typed)), function ($v) { return $v !== ''; })
                    : ($uid ? person_split($uid, $yr, $mon) : []);
    return [
        'canSalary'    => $canSalary,
        'splitSbus'    => $sbus,
        'splitNow'     => $split,
        'splitCarried' => (!$typed && $uid) ? person_split_is_carried($uid, $yr, $mon) : false,
    ];
}

// Save one person's split for one month. Blank or zero removes the row, so
// "nothing entered" and "entered as zero" never look the same. A month that has
// already been calculated and frozen is left exactly as it was.
function person_split_save($userId, $yr, $mon, array $pcts, $officeId = 0) {
    $userId = (int)$userId; $yr = (int)$yr; $mon = (int)$mon;
    if (!$userId) return 0;
    if ($officeId && cost_month_frozen($yr, $mon, $officeId)) return 0;
    $valid = array_keys($officeId ? office_sbus($officeId) : lk_options_or('sbu', OPS_SBUS));
    db()->prepare("DELETE FROM person_sbu_split WHERE user_id=? AND yr=? AND mon=?")->execute([$userId, $yr, $mon]);
    $who = user_name(current_user()); $now = date('c'); $n = 0;
    $ins = db()->prepare("INSERT INTO person_sbu_split (user_id,yr,mon,sbu,pct,set_by,set_at) VALUES (?,?,?,?,?,?,?)");
    foreach ($pcts as $sbu => $raw) {
        if (!in_array((string)$sbu, $valid, true)) continue;
        $v = (float)str_replace([',', ' '], '', trim((string)$raw));
        if ($v <= 0) continue;
        $ins->execute([$userId, $yr, $mon, (string)$sbu, $v, $who, $now]);
        $n++;
    }
    return $n;
}

// ===========================================================================
//  Month-end run, and the profit & loss it feeds
// ===========================================================================

// Revenue billed per SBU for a month. A job counts in the month its inspection
// finished — the month the work was done, not the month somebody got round to
// raising the invoice.
function costing_sbu_revenue($officeId, $yr, $mon) {
    $from = sprintf('%04d-%02d-01', $yr, $mon);
    $to   = date('Y-m-t', strtotime($from));
    $rows = ops_all("SELECT sbu, COALESCE(SUM(COALESCE(invoice_amount, expected_credit, 0)),0) v
                     FROM jobs WHERE executing_office_id=?
                       AND COALESCE(inspection_end_date, inspection_start_date, scheduled_date) >= ?
                       AND COALESCE(inspection_end_date, inspection_start_date, scheduled_date) <= ?
                     GROUP BY sbu", [(int)$officeId, $from, $to]);
    $out = []; foreach ($rows as $r) $out[(string)$r['sbu']] = (float)$r['v'];
    return $out;
}

// What was actually stored by the last run, per SBU and per kind.
function costing_stored_by_sbu($officeId, $yr, $mon) {
    $rows = ops_all("SELECT sbu, source_kind, COALESCE(SUM(amount),0) v FROM cost_allocations
                     WHERE office_id=? AND yr=? AND mon=? GROUP BY sbu, source_kind",
                    [(int)$officeId, (int)$yr, (int)$mon]);
    $out = [];
    foreach ($rows as $r) {
        $s = (string)$r['sbu'];
        if (!isset($out[$s])) $out[$s] = ['engineers'=>0, 'staff'=>0, 'office'=>0, 'cost'=>0];
        $v = (float)$r['v'];
        if (strncmp($r['source_kind'], 'INSPECTOR', 9) === 0) $out[$s]['engineers'] += $v;
        elseif ($r['source_kind'] === 'STAFF_SALARY')        $out[$s]['staff'] += $v;
        else                                                 $out[$s]['office'] += $v;
        $out[$s]['cost'] += $v;
    }
    return $out;
}

// Cost that names an activity code — an engineer's days on that work. Shared
// costs stop at the SBU line by design, so they are absent here on purpose.
function costing_stored_by_activity($officeId, $yr, $mon) {
    $rows = ops_all("SELECT activity, sbu, COALESCE(SUM(amount),0) v FROM cost_allocations
                     WHERE office_id=? AND yr=? AND mon=? AND activity <> '' GROUP BY activity, sbu",
                    [(int)$officeId, (int)$yr, (int)$mon]);
    $out = [];
    foreach ($rows as $r) {
        $k = $r['activity'] . '|' . $r['sbu'];
        $out[$k] = ['activity'=>$r['activity'], 'sbu'=>(string)$r['sbu'], 'cost'=>(float)$r['v'], 'revenue'=>0,
                    'label'=>lk_value_path($r['activity']) ?: (string)$r['activity']];
    }
    // and the revenue the same activity codes earned
    $from = sprintf('%04d-%02d-01', $yr, $mon);
    $to   = date('Y-m-t', strtotime($from));
    $rev = ops_all("SELECT activity_id, sbu, COALESCE(SUM(COALESCE(invoice_amount, expected_credit, 0)),0) v
                    FROM jobs WHERE executing_office_id=? AND activity_id IS NOT NULL
                      AND COALESCE(inspection_end_date, inspection_start_date, scheduled_date) >= ?
                      AND COALESCE(inspection_end_date, inspection_start_date, scheduled_date) <= ?
                    GROUP BY activity_id, sbu", [(int)$officeId, $from, $to]);
    foreach ($rev as $r) {
        $k = $r['activity_id'] . '|' . $r['sbu'];
        if (!isset($out[$k]))
            $out[$k] = ['activity'=>(string)$r['activity_id'], 'sbu'=>(string)$r['sbu'], 'cost'=>0, 'revenue'=>0,
                        'label'=>lk_value_path($r['activity_id']) ?: (string)$r['activity_id']];
        $out[$k]['revenue'] += (float)$r['v'];
    }
    uasort($out, function ($a, $b) { return ($b['revenue'] - $b['cost']) <=> ($a['revenue'] - $a['cost']); });
    return $out;
}

// One line per BOSS number worked in the month: what it billed and what it
// directly caused. Deliberately NOT loaded with a share of the branch — an
// order is judged on its own costs, and the branch is judged separately.
function costing_boss_lines($officeId, $yr, $mon) {
    $from = sprintf('%04d-%02d-01', $yr, $mon);
    $to   = date('Y-m-t', strtotime($from));
    $jobs = ops_all("SELECT j.*, b.boss_number, p.legal_name, p.display_name
                     FROM jobs j
                     LEFT JOIN boss_numbers b ON b.id = j.boss_id
                     LEFT JOIN business_partners p ON p.id = b.client_id
                     WHERE j.executing_office_id = ?
                       AND COALESCE(j.inspection_end_date, j.inspection_start_date, j.scheduled_date) >= ?
                       AND COALESCE(j.inspection_end_date, j.inspection_start_date, j.scheduled_date) <= ?",
                    [(int)$officeId, $from, $to]);
    $out = [];
    foreach ($jobs as $j) {
        $key = (int)($j['boss_id'] ?? 0) ?: 'none';
        if (!isset($out[$key])) $out[$key] = [
            'boss_number' => $j['boss_number'] ?: 'Not against a ' . T('boss') . ' number',
            'client' => trim((string)($j['display_name'] ?: $j['legal_name'])) ?: '—',
            'sbu' => (string)($j['sbu'] ?? ''), 'revenue'=>0, 'labour'=>0, 'expenses'=>0, 'subcon'=>0];
        $p = job_profit($j);
        $out[$key]['revenue']  += (float)$p['credit'];
        $out[$key]['labour']   += (float)$p['labour'];
        $out[$key]['expenses'] += (float)$p['expenses'];
        $out[$key]['subcon']   += (float)$p['subcon'];
    }
    uasort($out, function ($a, $b) {
        return (($b['revenue'] - $b['labour'] - $b['expenses'] - $b['subcon'])
             <=> ($a['revenue'] - $a['labour'] - $a['expenses'] - $a['subcon']));
    });
    return array_values($out);
}

// Offices this person may look at, newest schema first.
function costing_offices_for_user() {
    $global = is_master() || can('users.manage.global') || can('settings.manage') || can('data.profitability');
    $mine = current_user()['home_office_id'] ?? null;
    return $global ? ops_all("SELECT * FROM offices WHERE COALESCE(is_active,1)=1 ORDER BY is_ahmedabad DESC, name")
                   : ($mine ? ops_all("SELECT * FROM offices WHERE id=?", [$mine]) : []);
}
function costing_pick_office(array $offices) {
    $sel = (int)($_GET['office'] ?? 0);
    foreach ($offices as $o) if ((int)$o['id'] === $sel) return $sel;
    return (int)($offices[0]['id'] ?? 0);
}

// ---------------------------------------------------------------------------
//  Screen: the month-end run
// ---------------------------------------------------------------------------
function ops_cost_run($method) {
    ops_require(can_see_salary() || is_admin_level(), 'You cannot see the cost run — it contains salaries.');
    $canRun = is_master() || can('settings.manage') || can('users.manage.global');
    $offices = costing_offices_for_user();

    if ($method === 'POST') {
        ops_require($canRun, 'Only an administrator can store or close a month.');
        $oid = (int)($_POST['office_id'] ?? 0);
        [$yr, $mon] = ym_parse($_POST['m'] ?? '');
        $back = '/cost-run?office=' . $oid . '&m=' . sprintf('%04d-%02d', $yr, $mon);
        $mName = date('F Y', mktime(0, 0, 0, $mon, 1, $yr));
        $do = $_POST['do'] ?? '';
        if ($do === 'reopen') {
            db()->prepare("UPDATE cost_runs SET status='OPEN' WHERE yr=? AND mon=? AND office_id=?")
                ->execute([$yr, $mon, $oid]);
            idems_log('office', $oid, 'COST_REOPEN', ['field' => sprintf('%04d-%02d', $yr, $mon)]);
            flash($mName . ' reopened. Correct the figures, then calculate and close it again.');
            redirect($back);
        }
        if (cost_month_frozen($yr, $mon, $oid)) {
            flash($mName . ' is closed. Reopen it first.', 'error');
            redirect($back);
        }
        if ($do === 'freeze') {
            if (!cost_run_status($yr, $mon, $oid)) { flash('Calculate the month before closing it.', 'error'); redirect($back); }
            db()->prepare("UPDATE cost_runs SET status='FROZEN' WHERE yr=? AND mon=? AND office_id=?")
                ->execute([$yr, $mon, $oid]);
            idems_log('office', $oid, 'COST_FREEZE', ['field' => sprintf('%04d-%02d', $yr, $mon)]);
            flash($mName . ' closed. The figures are fixed now — reopen the month if anything has to change.');
            redirect($back);
        }
        $res = costing_run($yr, $mon, $oid, true);
        flash('₹' . number_format($res['total'], 2) . ' allocated for ' . $mName . '.'
            . ($res['warnings'] ? ' ' . count($res['warnings']) . ' thing(s) worth a look.' : ''));
        redirect($back);
    }

    $sel = costing_pick_office($offices);
    [$yr, $mon] = ym_parse($_GET['m'] ?? '');
    $preview = $sel ? costing_run($yr, $mon, $sel, false) : ['rows'=>[], 'total'=>0, 'warnings'=>[]];
    $status  = $sel ? cost_run_status($yr, $mon, $sel) : null;

    $kinds = ['INSPECTOR_WORKED'=>0, 'INSPECTOR_IDLE'=>0, 'STAFF_SALARY'=>0, 'OFFICE_EXPENSE'=>0];
    $bySbu = [];
    foreach ($preview['rows'] as $r) {
        $kinds[$r['source_kind']] = ($kinds[$r['source_kind']] ?? 0) + $r['amount'];
        $s = (string)$r['sbu'];
        if (!isset($bySbu[$s])) $bySbu[$s] = ['INSPECTOR_WORKED'=>0, 'INSPECTOR_IDLE'=>0,
                                               'STAFF_SALARY'=>0, 'OFFICE_EXPENSE'=>0, 'total'=>0];
        $bySbu[$s][$r['source_kind']] += $r['amount'];
        $bySbu[$s]['total'] += $r['amount'];
    }
    uasort($bySbu, function ($a, $b) { return $b['total'] <=> $a['total']; });
    $bossCodes = [];
    foreach (ops_all("SELECT id, boss_number FROM boss_numbers") as $b) $bossCodes[(int)$b['id']] = $b['boss_number'];

    view('ops/cost_run', [
        'offices'=>$offices, 'sel'=>$sel, 'yr'=>$yr, 'mon'=>$mon,
        'preview'=>$preview, 'byKind'=>$kinds, 'bySbu'=>$bySbu,
        'run'=>['status'=>$status['status'] ?? '', 'run_by'=>$status['run_by'] ?? '', 'run_at'=>$status['run_at'] ?? ''],
        'sbuLabels'=>lk_options_or('sbu', OPS_SBUS), 'bossCodes'=>$bossCodes, 'canRun'=>$canRun,
    ]);
}

// ---------------------------------------------------------------------------
//  Screen: SBU / activity / BOSS profit & loss
// ---------------------------------------------------------------------------
function ops_sbu_pl() {
    ops_require(can('data.profitability') || can_see_salary() || is_admin_level(),
                'You cannot see profitability figures.');
    $offices = costing_offices_for_user();
    $sel = costing_pick_office($offices);
    [$yr, $mon] = ym_parse($_GET['m'] ?? '');

    $revenue = $sel ? costing_sbu_revenue($sel, $yr, $mon) : [];
    $cost    = $sel ? costing_stored_by_sbu($sel, $yr, $mon) : [];
    $rows = [];
    foreach (array_unique(array_merge(array_keys($revenue), array_keys($cost))) as $s) {
        if ($s === '' && !($revenue[$s] ?? 0) && !($cost[$s]['cost'] ?? 0)) continue;
        $rows[$s] = ['revenue'=>(float)($revenue[$s] ?? 0)] + ($cost[$s] ?? ['engineers'=>0,'staff'=>0,'office'=>0,'cost'=>0]);
    }
    uasort($rows, function ($a, $b) { return ($b['revenue'] - $b['cost']) <=> ($a['revenue'] - $a['cost']); });
    $tot = ['revenue'=>0, 'cost'=>0];
    foreach ($rows as $r) { $tot['revenue'] += $r['revenue']; $tot['cost'] += $r['cost']; }

    view('ops/sbu_pl', [
        'offices'=>$offices, 'sel'=>$sel, 'yr'=>$yr, 'mon'=>$mon,
        'rows'=>$rows, 'tot'=>$tot,
        'byActivity'=>$sel ? costing_stored_by_activity($sel, $yr, $mon) : [],
        'byBoss'=>$sel ? costing_boss_lines($sel, $yr, $mon) : [],
        'stored'=>(bool)($sel ? cost_run_status($yr, $mon, $sel) : false),
        'sbuLabels'=>lk_options_or('sbu', OPS_SBUS),
        'basisText'=>$sel ? office_cost_basis_text($sel) : '',
    ]);
}
