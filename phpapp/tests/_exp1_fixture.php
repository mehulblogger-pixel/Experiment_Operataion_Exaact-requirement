<?php
// Shared fixture for tests/test_expenses_phase1_*.php (Expenses Phase 1, owner
// 17 Nov 2026). Builds on the voucher chain fixture (_bbv_fixture.php: Amit the
// inspector reports to Neha, Neha to Sunil …; Meera is Mumbai Finance) and adds:
// a client and a vendor, two jobs allocated to Amit, a per-km travel mode, six
// expense types of every kind, and a band "Field" that may claim them — all
// with the fixture's own tag, so nothing seeded is touched. Amounts here are
// TEST values, not the owner's. exp1_cleanup() removes every row and restores
// the claim-rule settings and the session.
require_once __DIR__ . '/_bbv_fixture.php';
if (!function_exists('exp1_fixture')) {
    function exp1_fixture() {
        $pdo = db();
        if (function_exists('exp_migrate')) exp_migrate();
        $f = bbv_fixture();
        $t = $f['tag'];
        $f['settings'] = [];
        foreach (array_keys(exp_rule_defaults()) as $k) $f['settings'][$k] = setting_get($k, null);
        setting_set('exp_claim_period', 'MONTHLY');
        setting_set('exp_day_deadline_days', '8');
        setting_set('exp_claim_deadline_days', '8');
        setting_set('exp_km_tolerance_pct', '0');
        setting_set('exp_rules_from', '2020-01-01');
        $bp = function ($name, $client) use ($pdo) {
            $pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, is_vendor) VALUES (?,?,?,?)")->execute([$name, $name, $client ? 1 : 0, $client ? 0 : 1]);
            return (int) $pdo->lastInsertId();
        };
        $f['client'] = $bp('TB Refinery ' . $t, true);
        $f['vendor'] = $bp('TB Steel ' . $t, false);
        $pdo->prepare("INSERT INTO calls (call_code, client_id, vendor_id, ibo_office_id, executing_office_id, sbu, status, created_at) VALUES (?,?,?,?,?,?, 'OPEN', ?)")
            ->execute(['EX1-' . $t, $f['client'], $f['vendor'], $f['mum'], $f['mum'], 'NDT', date('c')]);
        $f['call'] = (int) $pdo->lastInsertId();
        $job = function ($code, $date) use ($pdo, $f) {
            $pdo->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, inspector_id, scheduled_date, inspection_start_date, inspection_end_date, sbu, reporting_frequency, closed_flag, created_at)
                           VALUES (?,?,?,?,?,?,?, 'NDT', 'NOREPORT', 0, ?)")
                ->execute([$code, $f['call'], $f['mum'], $f['amit_ins'], $date, $date, $date, date('c')]);
            return (int) $pdo->lastInsertId();
        };
        $f['today'] = date('Y-m-d');
        $f['job'] = $job('EX1J-' . $t, $f['today']);
        $f['old'] = date('Y-m-d', strtotime('-20 days'));
        $f['job_old'] = $job('EX1O-' . $t, $f['old']);
        // A per-km mode and one type of every kind.
        $f['mode'] = 'M' . $t;
        $pdo->prepare("INSERT INTO travel_modes (code,label,basis,default_rate,active) VALUES (?,?, 'PER_KM', 6, 1)")->execute([$f['mode'], 'Own bike ' . $t]);
        $f['mode2'] = 'C' . $t;
        $pdo->prepare("INSERT INTO travel_modes (code,label,basis,default_rate,active) VALUES (?,?, 'PER_KM', 12, 1)")->execute([$f['mode2'], 'Own car ' . $t]);
        $head = function ($sfx, $label, $type, $scope = 'JOB', $mode = '', $receipt = 0) use ($pdo, $t) {
            $code = 'X' . $t . $sfx;
            $pdo->prepare("INSERT INTO expense_heads (code,label,head_type,default_rate,needs_receipt,sort_order,active,scope,add_mode) VALUES (?,?,?,0,?,900,1,?,?)")
                ->execute([$code, $label . ' ' . $t, $type, $receipt, $scope, $mode]);
            return $code;
        };
        $f['DA']    = $head('DA', 'Daily allowance', 'PER_DAY', 'JOB', 'AUTO');
        $f['OUT']   = $head('OUT', 'Outstation', 'PER_DAY', 'JOB', 'TICK');
        $f['HOTEL'] = $head('HTL', 'Hotel', 'BILL', 'JOB', '', 1);
        $f['RICK']  = $head('RCK', 'Auto-rickshaw', 'NOBILL');
        $f['MOB']   = $head('MOB', 'Mobile', 'MONTHLY');
        $f['TEL']   = $head('TEL', 'Office courier', 'BILL', 'NONJOB');
        $pdo->prepare("INSERT INTO expense_bands (name, notes, sort_order, active, created_at) VALUES (?, '', 100, 1, ?)")->execute(['Field ' . $t, date('c')]);
        $f['band'] = (int) $pdo->lastInsertId();
        exp_save_rows('band', $f['band'], '2020-01-01', [
            ['MODE', $f['mode'], 1, null, null], ['MODE', $f['mode2'], 0, null, null],
            ['HEAD', $f['DA'], 1, 300, null], ['HEAD', $f['OUT'], 1, 500, null],
            ['HEAD', $f['HOTEL'], 1, null, 3000], ['HEAD', $f['RICK'], 1, null, 200],
            ['HEAD', $f['MOB'], 1, 400, null], ['HEAD', $f['TEL'], 0, null, null],
        ]);
        $pdo->prepare("UPDATE inspectors SET band_id=? WHERE id=?")->execute([$f['band'], $f['amit_ins']]);
        return $f;
    }
    function exp1_close_job($jobId) { db()->prepare("UPDATE jobs SET closed_flag=1, closed_at=? WHERE id=?")->execute([date('c'), (int) $jobId]); }
    function exp1_photo($bytes = null) {
        $p = tempnam(sys_get_temp_dir(), 'exp1');
        file_put_contents($p, $bytes ?? ("\x89PNG\r\n" . random_bytes(24)));
        return ['tmp_name' => $p, 'error' => 0, 'size' => filesize($p), 'type' => 'image/png', 'name' => 'bill.png'];
    }
    function exp1_lines($vid) { return ops_all("SELECT * FROM voucher_entries WHERE voucher_id=? ORDER BY entry_date, id", [(int) $vid]) ?: []; }
    function exp1_cleanup(array $f) {
        $pdo = db();
        $q = function ($sql, $a = []) use ($pdo) { try { $pdo->prepare($sql)->execute($a); } catch (Throwable $e) {} };
        $ins = [$f['amit_ins'], $f['rahul_ins'], $f['sr_ins'], $f['lone_ins']];
        $in = implode(',', array_map('intval', $ins));
        $q("DELETE FROM voucher_entries WHERE voucher_id IN (SELECT id FROM vouchers WHERE inspector_id IN ($in))");
        foreach ($f['vids'] ?? [] as $vid) $q("DELETE FROM voucher_entries WHERE voucher_id=?", [(int) $vid]);
        $q("DELETE FROM vendor_km_memory WHERE inspector_id IN ($in)");
        $q("DELETE FROM inspector_allowances WHERE inspector_id IN ($in) OR band_id=?", [$f['band']]);
        $q("DELETE FROM expense_bands WHERE id=?", [$f['band']]);
        $q("DELETE FROM expense_heads WHERE code LIKE ?", ['X' . $f['tag'] . '%']);
        $q("DELETE FROM travel_modes WHERE code IN (?,?)", [$f['mode'], $f['mode2']]);
        $q("DELETE FROM expenses WHERE job_id IN (SELECT id FROM jobs WHERE call_id=?)", [$f['call']]);
        $q("DELETE FROM job_visits WHERE job_id IN (SELECT id FROM jobs WHERE call_id=?)", [$f['call']]);
        $q("DELETE FROM jobs WHERE call_id=?", [$f['call']]);
        $q("DELETE FROM calls WHERE id=?", [$f['call']]);
        $q("DELETE FROM business_partners WHERE id IN (?,?)", [$f['client'], $f['vendor']]);
        foreach ($f['settings'] as $k => $v) {
            if ($v === null) $q("DELETE FROM settings WHERE skey=?", [$k]); else setting_set($k, (string) $v);
        }
        if (function_exists('settings_cache')) { $c = &settings_cache(); foreach ($f['settings'] as $k => $v) { if ($v === null) unset($c[$k]); else $c[$k] = $v; } }
        bbv_cleanup($f);
    }
}
