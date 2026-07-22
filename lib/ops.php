<?php
// ============================================================================
//  Operations & Finance engine for the SGS Ahmedabad Inspection Management System
//  Phases 1-5: Calls, Jobs, Closure/Expenses, SubCon/Attendance/Holidays,
//  Credit logic, Dashboards + Reconciliation. Plus roles, email and reminders.
//  Kept in one file so a non-coder can find everything about "operations" here.
// ============================================================================

// ---- Choice lists (labels shown in dropdowns) ------------------------------
const OPS_REGIONS = ['WEST'=>'West','NORTH'=>'North','SOUTH'=>'South','EAST'=>'East','CENTRAL'=>'Central','OVERSEAS'=>'Overseas'];
const OPS_SBUS = ['IND'=>'Industrial','OGC'=>'Oil, Gas & Chemicals','MIN'=>'Minerals','GIS'=>'Governments & Institutions','AGRI'=>'Agriculture & Food','CRS'=>'Consumer & Retail','ENV'=>'Environment','OTHER'=>'Other'];
const PRODUCT_CATS = ['ELEC'=>'Electrical equipment','MECH'=>'Mechanical equipment','STRUCT'=>'Structural / Fabrication','PIPE'=>'Pipes & Fittings','VALVE'=>'Valves','PUMP'=>'Pumps & Rotating','TRANSFORMER'=>'Transformers','CABLE'=>'Cables','INSTRUMENT'=>'Instrumentation','CIVIL'=>'Civil / Construction','OTHER'=>'Others'];
const CREDIT_TYPES = ['MANDAY'=>'Man-day','MANMONTH'=>'Man-month','LUMP'=>'Lumpsum','LATER'=>'Decide later','OTHER'=>'Other'];
const CREDIT_DIRECTIONS = ['RECEIVED'=>'Received (IBO → Ahmedabad)','GIVEN'=>'Given (Ahmedabad → IBO)'];
const REPORT_FREQ = ['DAILY'=>'Daily','ALTERNATE'=>'Alternate day','WEEKLY'=>'Weekly','FORTNIGHTLY'=>'Fortnightly','MONTHLY'=>'Monthly','CUSTOM'=>'Custom (every N days)','NOREPORT'=>'No report'];
// Types of inspection service (third-party inspection industry).
const INSPECTION_TYPES = ['INSPECTION'=>'Inspection (third-party / TPI)','EXPEDITING'=>'Expediting','DEPUTATION'=>'Project deputation / Resident','VENDOR_ASSESS'=>'Vendor assessment','VENDOR_AUDIT'=>'Vendor audit','PRE_PROD'=>'Pre-production inspection','DURING_PROD'=>'During-production inspection','STAGE'=>'Stage / In-process inspection','FINAL'=>'Final inspection','FRI'=>'Final random inspection (FRI)','PSI'=>'Pre-shipment inspection (PSI)','WITNESS'=>'Witness / Test witnessing','FAT'=>'Factory Acceptance Test (FAT)','SAT'=>'Site Acceptance Test (SAT)','SOURCE'=>'Source inspection','SURVEILLANCE'=>'Surveillance','LOADING'=>'Loading / container supervision','SAMPLING'=>'Sampling','DIMENSIONAL'=>'Dimensional inspection','WELDING'=>'Welding inspection','NDT'=>'NDT witnessing','PMI'=>'Material verification (PMI)','COATING'=>'Painting / coating inspection','MECH_TEST'=>'Mechanical testing witness','CALIB'=>'Calibration verification','SAFETY_AUDIT'=>'Safety audit','SYSTEM_AUDIT'=>'Management-system audit','SECOND_PARTY'=>'Second-party audit','DESKTOP'=>'Desktop / Document review','TECH_AUDIT'=>'Technical audit'];
// Deliverables / report formats produced after a job.
const DELIVERABLES = ['IR'=>'Inspection Report (IR)','IRN'=>'Inspection Release Note (IRN)','NCR'=>'Non-Conformance Report (NCR)','COC'=>'Certificate of Conformity (CoC)','EXP_REP'=>'Expediting Report','VA_REP'=>'Vendor Assessment Report','AUDIT_REP'=>'Audit Report','TC_REVIEW'=>'Test Certificate Review','DPR'=>'Daily Progress Report','FINAL_REP'=>'Final Report','PUNCH'=>'Punch List','PHOTO'=>'Photographic Report','DIM_REP'=>'Dimensional Report','RN'=>'Release Note (RN)'];
const ATT_STATUS = ['PRESENT_NB'=>'Present (non-billable)','TRAINING'=>'Training','MEETING'=>'Meeting','LEAVE'=>'Leave','WFH'=>'Work from home','COMPOFF'=>'Comp-off taken','HOLIDAY'=>'Holiday'];
const JOB_TYPES = ['INSPECTION'=>'Inspection (day-based)','DEPUTATION'=>'Project deputation (site)'];
const EXPENSE_HEADINGS = ['TRAVEL'=>'Travel','LOCAL'=>'Local conveyance','FOOD'=>'Food','LODGING'=>'Lodging','MISC'=>'Misc'];
const EXP_LEVELS = ['JUNIOR'=>'Junior','MID'=>'Mid','SENIOR'=>'Senior','EXPERT'=>'Expert / Lead'];
const RATE_TYPES = ['MANDAY'=>'Per man-day','MANMONTH'=>'Per man-month'];
const BOSS_STATUS = ['ACTIVE'=>'Active','CLOSED'=>'Closed','HOLD'=>'On hold'];
const OPS_ROLES = ['MASTER_ADMIN'=>'Master Admin','ADMIN'=>'Admin','COORDINATOR'=>'Coordinator','INSPECTOR'=>'Inspector'];
const OVERHEAD_PCT = 8; // salary overhead %

// ---- Schema ----------------------------------------------------------------
function ops_ensure_schema() {
    $pdo = db(); $pk = pk_clause();
    $t = [
        "CREATE TABLE IF NOT EXISTS offices (
            id $pk, code VARCHAR(20), name VARCHAR(150), city VARCHAR(120) DEFAULT '',
            is_ahmedabad INT DEFAULT 0)",
        "CREATE TABLE IF NOT EXISTS inspectors (
            id $pk, name VARCHAR(150), emp_code VARCHAR(40) DEFAULT '', sbu VARCHAR(20) DEFAULT '',
            skills VARCHAR(255) DEFAULT '', email VARCHAR(200) DEFAULT '', mobile VARCHAR(40) DEFAULT '',
            salary_ctc DECIMAL(14,2) DEFAULT 0, status VARCHAR(20) DEFAULT 'ACTIVE',
            leave_balance DECIMAL(6,1) DEFAULT 0, compoff_balance DECIMAL(6,1) DEFAULT 0,
            created_at VARCHAR(30) DEFAULT '')",
        "CREATE TABLE IF NOT EXISTS subcons (
            id $pk, agency VARCHAR(150), inspector_name VARCHAR(150) DEFAULT '', skill VARCHAR(150) DEFAULT '',
            experience_level VARCHAR(20) DEFAULT '', regions VARCHAR(200) DEFAULT '', email VARCHAR(200) DEFAULT '',
            mobile VARCHAR(40) DEFAULT '', compliance VARCHAR(200) DEFAULT '', active INT DEFAULT 1,
            created_at VARCHAR(30) DEFAULT '')",
        "CREATE TABLE IF NOT EXISTS subcon_rates (
            id $pk, subcon_id INT NULL, agency VARCHAR(150) DEFAULT '', skill VARCHAR(150) DEFAULT '',
            experience_level VARCHAR(20) DEFAULT '', region VARCHAR(20) DEFAULT '',
            rate_type VARCHAR(20) DEFAULT 'MANDAY', rate DECIMAL(12,2) DEFAULT 0)",
        "CREATE TABLE IF NOT EXISTS boss_numbers (
            id $pk, client_id INT, boss_number VARCHAR(60), start_date VARCHAR(20) DEFAULT '',
            end_date VARCHAR(20) DEFAULT '', status VARCHAR(20) DEFAULT 'ACTIVE', comments VARCHAR(255) DEFAULT '')",
        "CREATE TABLE IF NOT EXISTS holidays (
            id $pk, hol_date VARCHAR(20), name VARCHAR(150), region VARCHAR(20) DEFAULT '')",
        "CREATE TABLE IF NOT EXISTS calls (
            id $pk, call_code VARCHAR(30), client_id INT NULL, vendor_id INT NULL, ibo_office_id INT NULL,
            region VARCHAR(20) DEFAULT '', sbu VARCHAR(20) DEFAULT '', product_category VARCHAR(30) DEFAULT '',
            product_other VARCHAR(150) DEFAULT '', call_received_date VARCHAR(20) DEFAULT '',
            inspection_required_date VARCHAR(20) DEFAULT '', notes TEXT, status VARCHAR(20) DEFAULT 'OPEN',
            created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')",
        "CREATE TABLE IF NOT EXISTS jobs (
            id $pk, job_code VARCHAR(30), call_id INT NULL, executing_office_id INT NULL,
            inspector_id INT NULL, subcon_id INT NULL, scheduled_date VARCHAR(20) DEFAULT '',
            inspection_start_date VARCHAR(20) DEFAULT '', inspection_end_date VARCHAR(20) DEFAULT '',
            random_date1 VARCHAR(20) DEFAULT '', random_date2 VARCHAR(20) DEFAULT '', random_date3 VARCHAR(20) DEFAULT '',
            folder_link VARCHAR(500) DEFAULT '', boss_id INT NULL, expected_credit DECIMAL(14,2) DEFAULT 0,
            credit_type VARCHAR(20) DEFAULT 'MANDAY', credit_direction VARCHAR(20) DEFAULT 'RECEIVED',
            reporting_frequency VARCHAR(20) DEFAULT 'NOREPORT', report_upload_date VARCHAR(20) DEFAULT '',
            report_link VARCHAR(500) DEFAULT '', closed_flag INT DEFAULT 0, closed_at VARCHAR(30) DEFAULT '',
            tat_days INT NULL, sbu VARCHAR(20) DEFAULT '', mandays DECIMAL(8,1) DEFAULT 0,
            subcon_cost DECIMAL(14,2) DEFAULT 0, last_reminder VARCHAR(20) DEFAULT '',
            created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')",
        "CREATE TABLE IF NOT EXISTS expenses (
            id $pk, job_id INT, inspector_id INT NULL, sbu VARCHAR(20) DEFAULT '',
            travel DECIMAL(12,2) DEFAULT 0, local DECIMAL(12,2) DEFAULT 0, food DECIMAL(12,2) DEFAULT 0,
            lodging DECIMAL(12,2) DEFAULT 0, misc DECIMAL(12,2) DEFAULT 0, exp_date VARCHAR(20) DEFAULT '',
            notes VARCHAR(255) DEFAULT '', created_at VARCHAR(30) DEFAULT '')",
        "CREATE TABLE IF NOT EXISTS attendance (
            id $pk, att_date VARCHAR(20), inspector_id INT, status VARCHAR(20) DEFAULT 'PRESENT_NB',
            remarks VARCHAR(255) DEFAULT '', compoff_earned INT DEFAULT 0, compoff_expiry VARCHAR(20) DEFAULT '',
            compoff_used INT DEFAULT 0, created_at VARCHAR(30) DEFAULT '')",
        "CREATE TABLE IF NOT EXISTS credit_recon (
            id $pk, month VARCHAR(10), ibo_office_id INT NULL, client_id INT NULL, boss_number VARCHAR(60) DEFAULT '',
            direction VARCHAR(20) DEFAULT 'RECEIVED', credit_actual DECIMAL(14,2) DEFAULT 0,
            notes VARCHAR(255) DEFAULT '', created_at VARCHAR(30) DEFAULT '')",
        "CREATE TABLE IF NOT EXISTS email_log (
            id $pk, to_addr VARCHAR(255), cc_addr VARCHAR(255) DEFAULT '', subject VARCHAR(255),
            body TEXT, kind VARCHAR(40) DEFAULT '', sent_ok INT DEFAULT 0, error VARCHAR(255) DEFAULT '',
            created_at VARCHAR(30) DEFAULT '')",
    ];
    foreach ($t as $sql) $pdo->exec($sql);
}

// Idempotent migration: new tables + columns added over time.
function ops_migrate() {
    ops_ensure_schema();
    // users gets role plumbing for the 4-role model + inspector self-service link
    ensure_column('users', 'inspector_id', 'INT NULL');
    // offices carry their branch coordinator + manager (for forwarding & emails)
    ensure_column('offices', 'coordinator_name', "VARCHAR(150) DEFAULT ''");
    ensure_column('offices', 'coordinator_email', "VARCHAR(200) DEFAULT ''");
    ensure_column('offices', 'manager_name', "VARCHAR(150) DEFAULT ''");
    ensure_column('offices', 'manager_email', "VARCHAR(200) DEFAULT ''");
    // calls gain forwarding, credit, activity, deputation and timing fields
    ensure_column('calls', 'executing_office_id', 'INT NULL');
    ensure_column('calls', 'expected_credit', 'DECIMAL(14,2) DEFAULT 0');
    ensure_column('calls', 'credit_type', "VARCHAR(20) DEFAULT ''");
    ensure_column('calls', 'deputation_type', "VARCHAR(30) DEFAULT ''");
    ensure_column('calls', 'activity_id', 'INT NULL');
    ensure_column('calls', 'notify_manager', 'INT DEFAULT 0');
    ensure_column('calls', 'forwarded_at', "VARCHAR(30) DEFAULT ''");
    ensure_column('calls', 'inspection_type', "VARCHAR(40) DEFAULT ''");
    // jobs gain type of inspection (carried from call), custom report frequency,
    // activity and the required deliverables/report formats.
    ensure_column('jobs', 'inspection_type', "VARCHAR(40) DEFAULT ''");
    ensure_column('jobs', 'activity_id', 'INT NULL');
    ensure_column('jobs', 'report_custom_days', 'INT NULL');
    ensure_column('jobs', 'deliverables', "VARCHAR(500) DEFAULT ''");
    // a client can carry the inspection types it typically needs (carried into calls)
    ensure_column('business_partners', 'inspection_types', "VARCHAR(600) DEFAULT ''");
    // inspector master overhaul: names, trade, multi-SBU, multi-skill
    ensure_column('inspectors', 'first_name', "VARCHAR(80) DEFAULT ''");
    ensure_column('inspectors', 'middle_name', "VARCHAR(80) DEFAULT ''");
    ensure_column('inspectors', 'last_name', "VARCHAR(80) DEFAULT ''");
    ensure_column('inspectors', 'trade_id', 'INT NULL');
    ensure_column('inspectors', 'sbus', "VARCHAR(200) DEFAULT ''");
    ensure_column('inspectors', 'skill_ids', "VARCHAR(600) DEFAULT ''");
    // job type (inspection vs project deputation)
    ensure_column('jobs', 'job_type', "VARCHAR(20) DEFAULT 'INSPECTION'");
    // certifications per inspector, with validity + reminder tracking
    db()->exec("CREATE TABLE IF NOT EXISTS inspector_certs (
        id " . pk_clause() . ", inspector_id INT, name VARCHAR(200), number VARCHAR(80) DEFAULT '',
        issued_date VARCHAR(20) DEFAULT '', valid_to VARCHAR(20) DEFAULT '', status VARCHAR(20) DEFAULT 'VALID',
        last_reminder VARCHAR(20) DEFAULT '', updated_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
}

// Seed offices (Ahmedabad + affiliate IBOs) once.
function ops_seed() {
    $pdo = db();
    if ((int)$pdo->query("SELECT COUNT(*) FROM offices")->fetchColumn() > 0) return;
    $offices = [
        ['AHM','SGS Ahmedabad','Ahmedabad',1],
        ['MUM','SGS Mumbai','Mumbai',0], ['DEL','SGS Delhi','New Delhi',0],
        ['CHE','SGS Chennai','Chennai',0], ['KOL','SGS Kolkata','Kolkata',0],
        ['BLR','SGS Bengaluru','Bengaluru',0], ['HYD','SGS Hyderabad','Hyderabad',0],
        ['PUN','SGS Pune','Pune',0], ['BRD','SGS Vadodara','Vadodara',0],
        ['VIZ','SGS Visakhapatnam','Visakhapatnam',0], ['KOC','SGS Kochi','Kochi',0],
        ['JAI','SGS Jaipur','Jaipur',0], ['IND','SGS Indore','Indore',0],
        ['NAG','SGS Nagpur','Nagpur',0], ['BHU','SGS Bhubaneswar','Bhubaneswar',0],
        ['LKO','SGS Lucknow','Lucknow',0], ['CHD','SGS Chandigarh','Chandigarh',0],
    ];
    $ins = $pdo->prepare("INSERT INTO offices (code,name,city,is_ahmedabad) VALUES (?,?,?,?)");
    foreach ($offices as $o) $ins->execute($o);
}

// ---- Roles & permissions ---------------------------------------------------
function user_role($u = null) {
    if ($u === null) return ua()['role'];
    if (!empty($u['is_superuser'])) return 'MASTER_ADMIN';
    $r = strtoupper($u['role'] ?? 'ADMIN');
    return (defined('ORG_ROLES') && isset(ORG_ROLES[$r])) ? $r : 'ADMIN';
}
function role_label($u = null) { $roles = defined('ORG_ROLES') ? ORG_ROLES : []; return $roles[user_role($u)] ?? 'User'; }
function is_master() { return ua()['master']; }
function is_admin_level() { return in_array(user_role(), MGMT_ROLES, true); }
function is_coordinator_level() { return is_admin_level() || in_array(user_role(), ['ASST_MANAGER','COORDINATOR'], true); }
function is_inspector() { return user_role() === 'INSPECTOR'; }
function can_see_salary() { return can('data.salary'); } // salary/cost visibility
// A convenient guard for handlers.
function ops_require($ok, $msg = 'You do not have access to that screen.') {
    if (!$ok) { flash($msg, 'error'); redirect('/'); }
}

// ---- Small data helpers ----------------------------------------------------
function ops_all($sql, $args = []) { $s = db()->prepare($sql); $s->execute($args); return $s->fetchAll(); }
function ops_one($sql, $args = []) { $s = db()->prepare($sql); $s->execute($args); return $s->fetch(); }
function ops_val($sql, $args = []) { $s = db()->prepare($sql); $s->execute($args); return $s->fetchColumn(); }
function ops_next_code($table, $col, $prefix) {
    $last = ops_val("SELECT $col FROM $table WHERE $col LIKE ? ORDER BY $col DESC LIMIT 1", ["$prefix-%"]);
    $seq = $last ? ((int)substr($last, strrpos($last, '-') + 1)) + 1 : 1;
    return sprintf("%s-%05d", $prefix, $seq);
}
function clients_list() { return ops_all("SELECT id, legal_name, display_name FROM business_partners WHERE is_client=1 ORDER BY legal_name"); }
function vendors_list() { return ops_all("SELECT id, legal_name, display_name FROM business_partners WHERE is_vendor=1 ORDER BY legal_name"); }
function offices_list() { return ops_all("SELECT * FROM offices ORDER BY is_ahmedabad DESC, name"); }
function office($id) { return $id ? ops_one("SELECT * FROM offices WHERE id=?", [$id]) : null; }
function inspectors_list($activeOnly = true) { return ops_all("SELECT id, name, emp_code, sbu, salary_ctc FROM inspectors" . ($activeOnly ? " WHERE status='ACTIVE'" : "") . " ORDER BY name"); }
function subcons_list($activeOnly = true) { return ops_all("SELECT id, agency, inspector_name, skill FROM subcons" . ($activeOnly ? " WHERE active=1" : "") . " ORDER BY agency"); }
function boss_for_client($cid) { return $cid ? ops_all("SELECT id, boss_number, status FROM boss_numbers WHERE client_id=? ORDER BY boss_number", [$cid]) : []; }
function pname($p) { return $p ? ($p['display_name'] ?: $p['legal_name']) : '—'; }
function fmoney($v) { return $v === null || $v === '' ? '—' : '₹' . number_format((float)$v, 0); }

// ---- Calculations ----------------------------------------------------------
// Working days in a month = calendar days − Sundays − public holidays.
function working_days_in_month($year, $month) {
    $days = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
    $sundays = 0;
    for ($d = 1; $d <= $days; $d++) {
        if ((int)date('w', mktime(0, 0, 0, $month, $d, $year)) === 0) $sundays++;
    }
    $mm = sprintf('%04d-%02d', $year, $month);
    $hol = (int)ops_val("SELECT COUNT(*) FROM holidays WHERE hol_date LIKE ?", ["$mm-%"]);
    $wd = $days - $sundays - $hol;
    return $wd > 0 ? $wd : ($days - $sundays);
}
// Loaded daily cost for an inspector (salary + overhead) / working days this month.
function inspector_daily_cost($salary_ctc, $year = null, $month = null) {
    $year = $year ?: (int)date('Y'); $month = $month ?: (int)date('n');
    $loadedMonthly = ((float)$salary_ctc / 12) * (1 + OVERHEAD_PCT / 100);
    $wd = working_days_in_month($year, $month);
    return $wd > 0 ? $loadedMonthly / $wd : 0;
}
function days_between($a, $b) {
    if (!$a || !$b) return null;
    $ta = strtotime($a); $tb = strtotime($b);
    if ($ta === false || $tb === false) return null;
    return (int)round(($tb - $ta) / 86400);
}
function job_mandays($job) {
    if ((float)($job['mandays'] ?? 0) > 0) return (float)$job['mandays'];
    $d = days_between($job['inspection_start_date'] ?? '', $job['inspection_end_date'] ?? '');
    return $d !== null ? max(1, $d + 1) : 1;
}
function job_expenses_total($jobId) {
    $r = ops_one("SELECT COALESCE(SUM(travel+local+food+lodging+misc),0) t FROM expenses WHERE job_id=?", [$jobId]);
    return (float)($r['t'] ?? 0);
}
// Full profitability breakdown for a job (Master Admin sees the salary part).
function job_profit($job) {
    $mandays = job_mandays($job);
    $salary_ctc = $job['inspector_id'] ? (float)ops_val("SELECT salary_ctc FROM inspectors WHERE id=?", [$job['inspector_id']]) : 0;
    $daily = $salary_ctc ? inspector_daily_cost($salary_ctc) : 0;
    $labour = $daily * $mandays;
    $expenses = job_expenses_total($job['id']);
    $subcon = (float)($job['subcon_cost'] ?? 0);
    $credit = (float)($job['expected_credit'] ?? 0);
    return [
        'mandays' => $mandays, 'daily_cost' => $daily, 'labour' => $labour,
        'expenses' => $expenses, 'subcon' => $subcon, 'credit' => $credit,
        'profit' => $credit - $labour - $expenses - $subcon,
    ];
}

// ---- Email (real send when configured, always logged) ----------------------
function ops_mail($to, $subject, $body, $cc = '', $kind = '') {
    $enabled = getenv('OPS_MAIL_ENABLED');
    $from = getenv('OPS_MAIL_FROM') ?: 'no-reply@mghaiapps.com';
    $ok = 0; $err = '';
    if ($enabled && $to) {
        $headers = "From: SGS Ahmedabad Ops <$from>\r\n";
        if ($cc) $headers .= "Cc: $cc\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        try { $ok = @mail($to, $subject, $body, $headers) ? 1 : 0; if (!$ok) $err = 'mail() returned false'; }
        catch (Throwable $e) { $err = $e->getMessage(); }
    } else {
        $err = $enabled ? 'no recipient' : 'mail disabled (set OPS_MAIL_ENABLED=1)';
    }
    db()->prepare("INSERT INTO email_log (to_addr,cc_addr,subject,body,kind,sent_ok,error,created_at) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$to, $cc, $subject, $body, $kind, $ok, $err, date('c')]);
    return $ok;
}
function coordinator_emails() {
    $rows = ops_all("SELECT email FROM users WHERE role IN ('COORDINATOR','ADMIN') AND email <> '' AND is_active=1");
    return implode(',', array_filter(array_column($rows, 'email')));
}
function job_email_context($jobId) {
    return ops_one("SELECT j.*, c.call_code, c.region, c.sbu csbu, bp.legal_name client_name, bp.display_name client_disp,
        v.legal_name vendor_name, i.name inspector_name, i.email inspector_email, bn.boss_number, o.name office_name
        FROM jobs j
        LEFT JOIN calls c ON c.id=j.call_id
        LEFT JOIN business_partners bp ON bp.id=c.client_id
        LEFT JOIN business_partners v ON v.id=c.vendor_id
        LEFT JOIN inspectors i ON i.id=j.inspector_id
        LEFT JOIN boss_numbers bn ON bn.id=j.boss_id
        LEFT JOIN offices o ON o.id=j.executing_office_id
        WHERE j.id=?", [$jobId]);
}
function send_assignment_email($jobId) {
    $j = job_email_context($jobId);
    if (!$j || !$j['inspector_id']) return;
    $client = $j['client_disp'] ?: $j['client_name'];
    $body = "Dear {$j['inspector_name']},\n\nYou have been assigned the following inspection job.\n\n"
        . "Job: {$j['job_code']}\nClient: {$client}\nVendor/Site: {$j['vendor_name']}\n"
        . "Region: " . (OPS_REGIONS[$j['region']] ?? $j['region']) . "\nSBU: " . (OPS_SBUS[$j['sbu']] ?? $j['sbu']) . "\n"
        . "BOSS No.: {$j['boss_number']}\nScheduled: {$j['scheduled_date']}\n"
        . "Inspection dates: {$j['inspection_start_date']} to {$j['inspection_end_date']}\n"
        . "Reporting: " . (REPORT_FREQ[$j['reporting_frequency']] ?? '') . "\n"
        . "Report folder: {$j['folder_link']}\n\nRegards,\nSGS Ahmedabad Coordination";
    ops_mail($j['inspector_email'] ?? '', "Job Assignment: {$j['job_code']} — {$client}", $body, coordinator_emails(), 'assignment');
}
function send_closure_email($jobId) {
    $j = job_email_context($jobId);
    if (!$j) return;
    $client = $j['client_disp'] ?: $j['client_name'];
    $exp = job_expenses_total($jobId);
    $body = "Inspection job closed.\n\nJob: {$j['job_code']}\nClient: {$client}\nInspector: {$j['inspector_name']}\n"
        . "Inspection end: {$j['inspection_end_date']}\nReport uploaded: {$j['report_upload_date']}\n"
        . "TAT: {$j['tat_days']} day(s)\nExpenses: " . fmoney($exp) . "\nReport folder: {$j['folder_link']}\n\n"
        . "Regards,\nSGS Ahmedabad";
    $to = coordinator_emails() ?: ($j['inspector_email'] ?? '');
    ops_mail($to, "Job Closed: {$j['job_code']} — {$client} (TAT {$j['tat_days']}d)", $body, '', 'closure');
}

// ---- Reminders (called by cron.php) ----------------------------------------
function ops_run_reminders($today = null) {
    $today = $today ?: date('Y-m-d');
    $sent = 0;
    $open = ops_all("SELECT * FROM jobs WHERE closed_flag=0 AND inspector_id IS NOT NULL");
    foreach ($open as $j) {
        if ($j['reporting_frequency'] === 'NOREPORT') {
            // only overdue-closure reminder applies
        }
        $due = false; $why = '';
        $end = $j['inspection_end_date'] ?: $j['scheduled_date'];
        $last = $j['report_upload_date'] ?: $j['inspection_start_date'] ?: $j['scheduled_date'];
        $since = days_between($last, $today);
        switch ($j['reporting_frequency']) {
            case 'DAILY': if ($since !== null && $since >= 1) { $due = true; $why = 'daily report'; } break;
            case 'ALTERNATE': if ($since !== null && $since >= 2) { $due = true; $why = 'alternate-day report'; } break;
            case 'WEEKLY': if ($since !== null && $since >= 7) { $due = true; $why = 'weekly report'; } break;
            case 'FORTNIGHTLY': if ($since !== null && $since >= 14) { $due = true; $why = 'fortnightly report'; } break;
            case 'MONTHLY': if ($since !== null && $since >= 30) { $due = true; $why = 'monthly report'; } break;
            case 'CUSTOM': $n = (int)($j['report_custom_days'] ?? 0); if ($n > 0 && $since !== null && $since >= $n) { $due = true; $why = "report (every $n days)"; } break;
        }
        // Overdue closure: past inspection end and still open
        $pastEnd = $end && days_between($end, $today) !== null && days_between($end, $today) > 0;
        if ($pastEnd) { $due = true; $why = $why ?: 'overdue closure'; }
        // one reminder per job per day
        if ($due && $j['last_reminder'] !== $today) {
            $ctx = job_email_context($j['id']);
            $client = $ctx['client_disp'] ?: $ctx['client_name'];
            $body = "Reminder ({$why}) for job {$ctx['job_code']} — {$client}.\n"
                . "Inspection dates: {$ctx['inspection_start_date']} to {$ctx['inspection_end_date']}.\n"
                . "Please upload the report / close the job.\n\nSGS Ahmedabad";
            ops_mail($ctx['inspector_email'] ?? '', "Reminder: {$ctx['job_code']} ({$why})", $body, coordinator_emails(), 'reminder');
            db()->prepare("UPDATE jobs SET last_reminder=? WHERE id=?")->execute([$today, $j['id']]);
            $sent++;
        }
    }
    $sent += ops_run_cert_reminders($today);
    return $sent;
}

// E-mail the inspector + QA/QC nominee when a certificate is within 30 days of expiry.
function ops_run_cert_reminders($today = null) {
    $today = $today ?: date('Y-m-d');
    $qac = getenv('QAC_EMAIL') ?: coordinator_emails();
    $sent = 0;
    foreach (ops_all("SELECT c.*, i.name inspector_name, i.email inspector_email FROM inspector_certs c JOIN inspectors i ON i.id=c.inspector_id WHERE c.valid_to <> ''") as $c) {
        $days = days_between($today, $c['valid_to']);
        if ($days === null || $days > 30) continue;      // only within a month (or overdue)
        if ($c['last_reminder'] === $today) continue;      // once per day
        $when = $days < 0 ? "expired " . abs($days) . " day(s) ago" : "expires in $days day(s)";
        $body = "Certificate follow-up.\n\nInspector: {$c['inspector_name']}\nCertificate: {$c['name']} ({$c['number']})\n"
            . "Valid to: {$c['valid_to']} — {$when}.\n\nPlease renew and submit the hard copy so the QA/QC nominee can update the date in the system.\n\nSGS Ahmedabad";
        ops_mail($c['inspector_email'] ?? '', "Certificate expiry: {$c['name']} — {$c['inspector_name']} ($when)", $body, $qac, 'cert');
        db()->prepare("UPDATE inspector_certs SET last_reminder=? WHERE id=?")->execute([$today, $c['id']]);
        $sent++;
    }
    return $sent;
}

// ---- Generic master engine (simple CRUD screens) ---------------------------
// Each master is a config array driving one list view + one form view.
function ops_masters() {
    return [
        'offices' => [
            'label' => 'Offices / branches (IBO)', 'table' => 'offices', 'access' => 'admin', 'order' => 'is_ahmedabad DESC, name',
            'fields' => [
                ['code','Code','text',['req'=>1]],
                ['name','Office name','text',['req'=>1]],
                ['city','City','text',[]],
                ['coordinator_name','Coordinator name','text',[]],
                ['coordinator_email','Coordinator email','text',[]],
                ['manager_name','Manager name','text',[]],
                ['manager_email','Manager email','text',[]],
                ['is_ahmedabad','This is the Ahmedabad (managing) office','check',[]],
            ],
            'list' => ['code'=>'Code','name'=>'Office','city'=>'City','coordinator_name'=>'Coordinator','manager_name'=>'Manager'],
            'list_labels' => [],
        ],
        'inspectors' => [
            'label' => 'Inspectors', 'table' => 'inspectors', 'code' => null, 'access' => 'admin',
            'order' => 'name',
            'fields' => [
                ['name','Name','text',['req'=>1]],
                ['emp_code','Employee code','text',[]],
                ['sbu','SBU','select',['opts'=>OPS_SBUS]],
                ['skills','Skills','text',[]],
                ['email','Email','text',[]],
                ['mobile','Mobile','text',[]],
                ['salary_ctc','Annual CTC (₹)','money',['salary'=>1]],
                ['leave_balance','Leave balance (days)','number',[]],
                ['compoff_balance','Comp-off balance (days)','number',[]],
                ['status','Status','select',['opts'=>['ACTIVE'=>'Active','INACTIVE'=>'Inactive']]],
            ],
            'list' => ['name'=>'Name','emp_code'=>'Emp code','sbu'=>'SBU','skills'=>'Skills','status'=>'Status'],
            'list_labels' => ['sbu'=>OPS_SBUS],
        ],
        'subcons' => [
            'label' => 'Sub-contractors', 'table' => 'subcons', 'access' => 'coordinator', 'order' => 'agency',
            'fields' => [
                ['agency','Agency','text',['req'=>1]],
                ['inspector_name','Inspector name','text',[]],
                ['skill','Skill','text',[]],
                ['experience_level','Experience','select',['opts'=>EXP_LEVELS]],
                ['regions','Regions covered','text',[]],
                ['email','Email','text',[]],
                ['mobile','Mobile','text',[]],
                ['compliance','Compliance / docs','text',[]],
                ['active','Active','check',[]],
            ],
            'list' => ['agency'=>'Agency','inspector_name'=>'Inspector','skill'=>'Skill','experience_level'=>'Experience','regions'=>'Regions'],
            'list_labels' => ['experience_level'=>EXP_LEVELS],
        ],
        'subcon-rates' => [
            'label' => 'Sub-con rate matrix', 'table' => 'subcon_rates', 'access' => 'coordinator', 'order' => 'agency',
            'fields' => [
                ['subcon_id','Sub-contractor','ref',['ref'=>'subcons','optfn'=>'subcons_list','optlabel'=>'agency_inspector']],
                ['agency','Agency (if not linked)','text',[]],
                ['skill','Skill','text',[]],
                ['experience_level','Experience','select',['opts'=>EXP_LEVELS]],
                ['region','Region','select',['opts'=>OPS_REGIONS]],
                ['rate_type','Rate type','select',['opts'=>RATE_TYPES]],
                ['rate','Rate (₹)','money',[]],
            ],
            'list' => ['agency'=>'Agency','skill'=>'Skill','experience_level'=>'Experience','region'=>'Region','rate_type'=>'Type','rate'=>'Rate'],
            'list_labels' => ['experience_level'=>EXP_LEVELS,'region'=>OPS_REGIONS,'rate_type'=>RATE_TYPES],
            'money_cols' => ['rate'],
        ],
        'boss' => [
            'label' => 'BOSS numbers', 'table' => 'boss_numbers', 'access' => 'coordinator', 'order' => 'boss_number',
            'fields' => [
                ['client_id','Client','ref',['req'=>1,'ref'=>'clients','optfn'=>'clients_list','optlabel'=>'partner']],
                ['boss_number','BOSS number','text',['req'=>1]],
                ['start_date','Start date','date',[]],
                ['end_date','End date','date',[]],
                ['status','Status','select',['opts'=>BOSS_STATUS]],
                ['comments','Comments','text',[]],
            ],
            'list' => ['boss_number'=>'BOSS number','client_id'=>'Client','start_date'=>'Start','end_date'=>'End','status'=>'Status'],
            'list_labels' => ['status'=>BOSS_STATUS],
            'ref_cols' => ['client_id'=>['clients','partner']],
        ],
        'holidays' => [
            'label' => 'Holidays', 'table' => 'holidays', 'access' => 'admin', 'order' => 'hol_date',
            'fields' => [
                ['hol_date','Date','date',['req'=>1]],
                ['name','Holiday name','text',['req'=>1]],
                ['region','Region','select',['opts'=>OPS_REGIONS]],
            ],
            'list' => ['hol_date'=>'Date','name'=>'Holiday','region'=>'Region'],
            'list_labels' => ['region'=>OPS_REGIONS],
        ],
        'attendance' => [
            'label' => 'Attendance / non-billable', 'table' => 'attendance', 'access' => 'coordinator', 'order' => 'att_date DESC',
            'fields' => [
                ['att_date','Date','date',['req'=>1]],
                ['inspector_id','Inspector','ref',['req'=>1,'ref'=>'inspectors','optfn'=>'inspectors_list','optlabel'=>'name']],
                ['status','Status','select',['opts'=>ATT_STATUS]],
                ['remarks','Remarks','text',[]],
            ],
            'list' => ['att_date'=>'Date','inspector_id'=>'Inspector','status'=>'Status','remarks'=>'Remarks'],
            'list_labels' => ['status'=>ATT_STATUS],
            'ref_cols' => ['inspector_id'=>['inspectors','name']],
        ],
        'credit-recon' => [
            'label' => 'Credit reconciliation', 'table' => 'credit_recon', 'access' => 'admin', 'order' => 'month DESC',
            'fields' => [
                ['month','Month (YYYY-MM)','text',['req'=>1]],
                ['ibo_office_id','IBO / Office','ref',['ref'=>'offices','optfn'=>'offices_list','optlabel'=>'name']],
                ['client_id','Client','ref',['ref'=>'clients','optfn'=>'clients_list','optlabel'=>'partner']],
                ['boss_number','BOSS number','text',[]],
                ['direction','Direction','select',['opts'=>CREDIT_DIRECTIONS]],
                ['credit_actual','Actual credit (₹)','money',[]],
                ['notes','Notes','text',[]],
            ],
            'list' => ['month'=>'Month','client_id'=>'Client','boss_number'=>'BOSS','direction'=>'Direction','credit_actual'=>'Actual'],
            'list_labels' => ['direction'=>CREDIT_DIRECTIONS],
            'ref_cols' => ['client_id'=>['clients','partner'],'ibo_office_id'=>['offices','name']],
            'money_cols' => ['credit_actual'],
        ],
    ];
}
function master_access_ok($access) {
    if ($access === 'admin') return is_admin_level();
    if ($access === 'coordinator') return is_coordinator_level();
    if ($access === 'master') return is_master();
    return true;
}
function ref_label($col, $val, $cfg) {
    if ($val === null || $val === '') return '—';
    $rc = $cfg['ref_cols'][$col] ?? null;
    if (!$rc) return $val;
    [$kind] = $rc;
    if ($kind === 'clients' || $kind === 'vendors') {
        $r = ops_one("SELECT legal_name, display_name FROM business_partners WHERE id=?", [$val]);
        return $r ? pname($r) : $val;
    }
    if ($kind === 'inspectors') return ops_val("SELECT name FROM inspectors WHERE id=?", [$val]) ?: $val;
    if ($kind === 'offices') return ops_val("SELECT name FROM offices WHERE id=?", [$val]) ?: $val;
    if ($kind === 'subcons') return ops_val("SELECT agency FROM subcons WHERE id=?", [$val]) ?: $val;
    return $val;
}
function option_rows($fieldMeta) {
    $fn = $fieldMeta['optfn'] ?? null;
    if (!$fn || !function_exists($fn)) return [];
    $rows = $fn();
    $label = $fieldMeta['optlabel'] ?? '';
    $out = [];
    foreach ($rows as $r) {
        if ($label === 'partner') $txt = pname($r);
        elseif ($label === 'agency_inspector') $txt = trim(($r['agency'] ?? '') . (($r['inspector_name'] ?? '') ? ' — ' . $r['inspector_name'] : ''));
        elseif ($label === 'name') $txt = $r['name'] . (($r['emp_code'] ?? '') ? " ({$r['emp_code']})" : '');
        else $txt = $r[$label] ?? reset($r);
        $out[] = ['id' => $r['id'], 'text' => $txt];
    }
    return $out;
}

// ============================================================================
//  Dispatcher — returns true if it handled the route
// ============================================================================
function ops_dispatch($route, $method) {
    // ----- Generic masters: /m/<entity>, /m/<entity>/new, /m/<entity>/edit
    if (preg_match('#^m/([a-z-]+)(?:/(new|edit|delete))?$#', $route, $mm)) {
        $key = $mm[1]; $action = $mm[2] ?? 'list';
        $masters = ops_masters();
        if (!isset($masters[$key])) return false;
        $cfg = $masters[$key];
        ops_require(master_access_ok($cfg['access']), "Only " . ($cfg['access']) . "-level users can open {$cfg['label']}.");
        if ($key === 'inspectors') { ops_inspectors($action, $method); return true; } // dedicated screen
        ops_master_handle($key, $cfg, $action, $method);
        return true;
    }
    switch (true) {
        case $route === 'calls' || $route === 'call-new' || $route === 'call-edit' || $route === 'call':
            ops_calls($route, $method); return true;
        case $route === 'jobs' || $route === 'job-new' || $route === 'job-edit' || $route === 'job' || $route === 'job-close':
            ops_jobs($route, $method); return true;
        case $route === 'my-jobs':
            ops_my_jobs(); return true;
        case $route === 'reports':
            ops_reports(); return true;
        case $route === 'users' || $route === 'user-new' || $route === 'user-edit':
            ops_users($route, $method); return true;
        case $route === 'change-password':
            ops_change_password($method); return true;
        case $route === 'settings':
            ops_settings($method); return true;
        case $route === 'masters':
            ops_require(is_coordinator_level());
            view('ops/masters', ['masters' => ops_masters()]); return true;
        case $route === 'lookups' || $route === 'lookup' || $route === 'custom-fields':
            lk_admin($route, $method); return true;
        case $route === 'quick-add':
            ops_quick_add(); return true;
        case $route === 'partner-meta':
            header('Content-Type: application/json');
            $r = ops_one("SELECT inspection_types FROM business_partners WHERE id=?", [(int)($_GET['id'] ?? 0)]);
            echo json_encode(['inspection_types' => ($r && $r['inspection_types'] !== '') ? explode(',', $r['inspection_types']) : []]);
            return true;
    }
    return false;
}

// Inline "+ Add new" from the New Call form. Returns JSON {ok,id,label}.
function ops_quick_add() {
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_coordinator_level()) { echo json_encode(['ok' => false, 'error' => 'Not allowed']); return; }
    $pdo = db(); $kind = $_GET['kind'] ?? ''; $b = $_POST;
    $name = trim($b['name'] ?? '');
    if ($name === '') { echo json_encode(['ok' => false, 'error' => 'Enter a name.']); return; }
    try {
        if (in_array($kind, ['client', 'vendor', 'both'], true)) {
            $isClient = ($kind === 'client' || $kind === 'both') ? 1 : 0;
            $isVendor = ($kind === 'vendor' || $kind === 'both') ? 1 : 0;
            $gstin = clean_gstin($b['gstin'] ?? '');
            $pan = $gstin ? pan_from_gstin($gstin) : '';
            $state = $gstin ? state_from_gstin($gstin) : '';
            $token = short_token($name);
            $last = ops_val("SELECT code FROM business_partners WHERE code LIKE ? ORDER BY code DESC LIMIT 1", ["GEN-$token-%"]);
            $seq = $last ? ((int)substr($last, strrpos($last, '-') + 1)) + 1 : 1;
            $code = sprintf("GEN-%s-%04d", $token, $seq);
            $pdo->prepare("INSERT INTO business_partners (code,legal_name,is_client,is_vendor,status,gstin,pan,state,created_at) VALUES (?,?,?,?, 'ACTIVE', ?,?,?,?)")
                ->execute([$code, $name, $isClient, $isVendor, $gstin, $pan, $state, date('c')]);
            echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId(), 'label' => $name, 'roles' => ['client' => $isClient, 'vendor' => $isVendor]]);
            return;
        }
        if ($kind === 'office') {
            $code = strtoupper(trim($b['code'] ?? '')) ?: strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3));
            $pdo->prepare("INSERT INTO offices (code,name,city,coordinator_name,coordinator_email,manager_name,manager_email,is_ahmedabad) VALUES (?,?,?,?,?,?,?,0)")
                ->execute([$code, $name, $b['city'] ?? '', $b['coordinator_name'] ?? '', $b['coordinator_email'] ?? '', $b['manager_name'] ?? '', $b['manager_email'] ?? '']);
            echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId(), 'label' => $name]);
            return;
        }
        if ($kind === 'product') {
            $t = lk_type('product');
            if (!$t) { echo json_encode(['ok' => false, 'error' => 'No product list']); return; }
            $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 12));
            $id = lk_add_value($t['id'], null, $code, $name, 99);
            echo json_encode(['ok' => true, 'id' => $code ?: $id, 'label' => $name]);
            return;
        }
        if ($kind === 'activity') {
            $t = lk_type('activity');
            $sbu = lk_type('sbu');
            if (!$t || !$sbu) { echo json_encode(['ok' => false, 'error' => 'No activity list']); return; }
            $sbuCode = $b['sbu'] ?? '';
            $sbuVal = ops_one("SELECT * FROM lookup_values WHERE type_id=? AND (code=? OR id=?)", [$sbu['id'], $sbuCode, (int)$sbuCode]);
            if (!$sbuVal) { echo json_encode(['ok' => false, 'error' => 'Pick an SBU first.']); return; }
            $id = lk_add_value($t['id'], $sbuVal['id'], '', $name, 99);
            echo json_encode(['ok' => true, 'id' => $id, 'label' => $name, 'sbu' => $sbuCode]);
            return;
        }
        echo json_encode(['ok' => false, 'error' => 'Unknown type']);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

// ---- Generic master handler ------------------------------------------------
function ops_master_handle($key, $cfg, $action, $method) {
    $pdo = db(); $table = $cfg['table'];
    if ($action === 'delete' && $method === 'POST') {
        $pdo->prepare("DELETE FROM $table WHERE id=?")->execute([(int)($_GET['id'] ?? 0)]);
        flash("{$cfg['label']}: record deleted.");
        redirect("/m/$key");
    }
    if (($action === 'new' || $action === 'edit') && $method === 'POST') {
        $b = $_POST; $cols = []; $vals = [];
        foreach ($cfg['fields'] as $f) {
            [$name, , $type] = $f;
            $v = $b[$name] ?? '';
            if ($type === 'check') $v = !empty($b[$name]) ? 1 : 0;
            elseif (($type === 'ref' || $type === 'money' || $type === 'number') && $v === '') $v = null;
            $cols[] = $name; $vals[] = $v;
        }
        if ($action === 'edit') {
            $id = (int)($_GET['id'] ?? 0);
            $set = implode(',', array_map(fn($c) => "$c=?", $cols));
            $vals[] = $id;
            $pdo->prepare("UPDATE $table SET $set WHERE id=?")->execute($vals);
            flash("{$cfg['label']}: saved.");
        } else {
            $ph = implode(',', array_fill(0, count($cols), '?'));
            if (in_array('created_at', array_column($cfg['fields'], 0)) === false && column_exists($table, 'created_at')) {
                $cols[] = 'created_at'; $vals[] = date('c'); $ph .= ',?';
            }
            $pdo->prepare("INSERT INTO $table (" . implode(',', $cols) . ") VALUES ($ph)")->execute($vals);
            flash("{$cfg['label']}: added.");
        }
        redirect("/m/$key");
    }
    if ($action === 'new') { view('ops/master_form', ['cfg' => $cfg, 'key' => $key, 'row' => null]); return; }
    if ($action === 'edit') {
        $row = ops_one("SELECT * FROM $table WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$row) { http_response_code(404); view('notfound'); return; }
        view('ops/master_form', ['cfg' => $cfg, 'key' => $key, 'row' => $row]); return;
    }
    // list
    $q = trim($_GET['q'] ?? '');
    $rows = ops_all("SELECT * FROM $table ORDER BY {$cfg['order']}");
    if ($q !== '') {
        $rows = array_filter($rows, function($r) use ($q) {
            foreach ($r as $v) if (stripos((string)$v, $q) !== false) return true; return false;
        });
    }
    view('ops/master_list', ['cfg' => $cfg, 'key' => $key, 'rows' => $rows, 'q' => $q]);
}
function column_exists($table, $col) {
    try {
        if (db_driver() === 'sqlite') {
            foreach (db()->query("PRAGMA table_info($table)")->fetchAll() as $c) if ($c['name'] === $col) return true;
            return false;
        }
        $s = db()->prepare("SHOW COLUMNS FROM `$table` LIKE ?"); $s->execute([$col]); return (bool)$s->fetch();
    } catch (Throwable $e) { return false; }
}

// ---- Inspector master (dedicated: names, trade, multi-SBU, multi-skill, certs) ----
function ops_inspectors($action, $method) {
    $pdo = db();
    if ($action === 'delete' && $method === 'POST') {
        $id = (int)($_GET['id'] ?? 0);
        $pdo->prepare("DELETE FROM inspector_certs WHERE inspector_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM inspectors WHERE id=?")->execute([$id]);
        flash('Inspector deleted.');
        redirect('/m/inspectors');
    }
    if ($action === 'new' || $action === 'edit') {
        $ins = null;
        if ($action === 'edit') {
            $ins = ops_one("SELECT * FROM inspectors WHERE id=?", [(int)($_GET['id'] ?? 0)]);
            if (!$ins) { http_response_code(404); view('notfound'); return; }
        }
        if ($method === 'POST') {
            $b = $_POST;
            // certification sub-actions on the edit page
            if (($b['_do'] ?? '') === 'cert_add' && $ins) {
                $pdo->prepare("INSERT INTO inspector_certs (inspector_id,name,number,issued_date,valid_to,status,updated_by,created_at) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$ins['id'], $b['cert_name'] ?? '', $b['cert_number'] ?? '', $b['cert_issued'] ?? '', $b['cert_valid_to'] ?? '', 'VALID', user_name(current_user()), date('c')]);
                flash('Certification added.');
                redirect('/m/inspectors/edit?id=' . $ins['id']);
            }
            if (($b['_do'] ?? '') === 'cert_update' && $ins) {
                $pdo->prepare("UPDATE inspector_certs SET valid_to=?, number=?, updated_by=? WHERE id=? AND inspector_id=?")
                    ->execute([$b['cert_valid_to'] ?? '', $b['cert_number'] ?? '', user_name(current_user()), (int)$b['cert_id'], $ins['id']]);
                flash('Certification validity updated.');
                redirect('/m/inspectors/edit?id=' . $ins['id']);
            }
            if (($b['_do'] ?? '') === 'cert_del' && $ins) {
                $pdo->prepare("DELETE FROM inspector_certs WHERE id=? AND inspector_id=?")->execute([(int)$b['cert_id'], $ins['id']]);
                flash('Certification removed.');
                redirect('/m/inspectors/edit?id=' . $ins['id']);
            }
            // main save
            $full = trim(trim(($b['first_name'] ?? '') . ' ' . ($b['middle_name'] ?? '')) . ' ' . ($b['last_name'] ?? ''));
            $sbus = implode(',', array_filter((array)($b['sbus'] ?? [])));
            $skills = implode(',', array_filter((array)($b['skill_ids'] ?? [])));
            $trade = ($b['trade_id'] ?? '') !== '' ? (int)$b['trade_id'] : null;
            $salary = can_see_salary() ? (($b['salary_ctc'] ?? '') !== '' ? $b['salary_ctc'] : 0) : null;
            if ($ins) {
                $sql = "UPDATE inspectors SET first_name=?,middle_name=?,last_name=?,name=?,emp_code=?,trade_id=?,sbus=?,sbu=?,skill_ids=?,email=?,mobile=?,status=?";
                $args = [$b['first_name'] ?? '', $b['middle_name'] ?? '', $b['last_name'] ?? '', $full, $b['emp_code'] ?? '', $trade, $sbus, explode(',', $sbus)[0] ?? '', $skills, $b['email'] ?? '', $b['mobile'] ?? '', $b['status'] ?? 'ACTIVE'];
                if ($salary !== null) { $sql .= ",salary_ctc=?"; $args[] = $salary; }
                $sql .= " WHERE id=?"; $args[] = $ins['id'];
                $pdo->prepare($sql)->execute($args);
                flash('Inspector saved.');
                redirect('/m/inspectors/edit?id=' . $ins['id']);
            } else {
                $pdo->prepare("INSERT INTO inspectors (first_name,middle_name,last_name,name,emp_code,trade_id,sbus,sbu,skill_ids,email,mobile,salary_ctc,status,created_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$b['first_name'] ?? '', $b['middle_name'] ?? '', $b['last_name'] ?? '', $full, $b['emp_code'] ?? '', $trade, $sbus, explode(',', $sbus)[0] ?? '', $skills, $b['email'] ?? '', $b['mobile'] ?? '', $salary ?: 0, $b['status'] ?? 'ACTIVE', date('c')]);
                $id = $pdo->lastInsertId();
                flash('Inspector added. You can now add certifications.');
                redirect('/m/inspectors/edit?id=' . $id);
            }
        }
        $certs = $ins ? ops_all("SELECT * FROM inspector_certs WHERE inspector_id=? ORDER BY valid_to", [$ins['id']]) : [];
        view('ops/inspector_form', ['ins' => $ins, 'certs' => $certs, 'skillsByTrade' => skills_by_trade()]);
        return;
    }
    // list
    $q = trim($_GET['q'] ?? '');
    $where = "1=1"; $args = [];
    if ($q) { $where = "(name LIKE ? OR emp_code LIKE ? OR skills LIKE ?)"; $args = ["%$q%", "%$q%", "%$q%"]; }
    $rows = ops_all("SELECT * FROM inspectors WHERE $where ORDER BY name", $args);
    view('ops/inspector_list', ['rows' => $rows, 'q' => $q]);
}
// Human labels for an inspector's stored SBU codes / skill ids / trade.
function sbu_labels($csv) {
    if (!$csv) return '—';
    $map = lk_options_or('sbu', OPS_SBUS);
    return implode(', ', array_map(fn($c) => $map[$c] ?? $c, array_filter(explode(',', $csv)))) ?: '—';
}
function skill_labels($csv) {
    if (!$csv) return '—';
    $out = [];
    foreach (array_filter(explode(',', $csv)) as $id) { $v = lk_value((int)$id); if ($v) $out[] = $v['label']; }
    return $out ? implode(', ', $out) : '—';
}
function trade_label($id) { $v = $id ? lk_value($id) : null; return $v ? $v['label'] : '—'; }

// ---- Calls -----------------------------------------------------------------
function ops_calls($route, $method) {
    $pdo = db();
    if ($route === 'calls') {
        $q = trim($_GET['q'] ?? '');
        [$scopeW, $args] = scope_clause('c.executing_office_id', 'c.sbu');
        $where = $scopeW;
        if ($q) { $where .= " AND (c.call_code LIKE ? OR bp.legal_name LIKE ? OR v.legal_name LIKE ?)"; array_push($args, "%$q%","%$q%","%$q%"); }
        $rows = ops_all("SELECT c.*, bp.legal_name client_name, bp.display_name client_disp, v.legal_name vendor_name,
            (SELECT COUNT(*) FROM jobs j WHERE j.call_id=c.id) job_count
            FROM calls c LEFT JOIN business_partners bp ON bp.id=c.client_id
            LEFT JOIN business_partners v ON v.id=c.vendor_id WHERE $where ORDER BY c.id DESC", $args);
        view('ops/calls', ['rows' => $rows, 'q' => $q]); return;
    }
    if ($route === 'call-new' || $route === 'call-edit') {
        ops_require(is_coordinator_level(), 'Only coordinators and admins can create calls.');
        $call = null;
        if ($route === 'call-edit') {
            $call = ops_one("SELECT * FROM calls WHERE id=?", [(int)($_GET['id'] ?? 0)]);
            if (!$call) { http_response_code(404); view('notfound'); return; }
        }
        if ($method === 'POST') {
            $b = $_POST;
            $execOffice = ($b['executing_office_id'] ?? '') !== '' ? (int)$b['executing_office_id'] : null;
            // Forwarding to an executing branch → credit is mandatory.
            if ($execOffice && (($b['expected_credit'] ?? '') === '' || (float)$b['expected_credit'] <= 0)) {
                view('ops/call_form', ['call' => $call, 'clients' => clients_list(), 'vendors' => vendors_list(),
                    'offices' => offices_list(), 'error' => 'Enter the credit amount to give the executing branch (mandatory when forwarding).',
                    'cfvals' => $call ? custom_values_map('call', $call['id']) : []]);
                return;
            }
            $fields = ['client_id','vendor_id','ibo_office_id','executing_office_id','region','sbu','activity_id',
                'inspection_type','product_category','product_other','deputation_type','expected_credit','credit_type',
                'call_received_date','inspection_required_date','notes'];
            $wasForwarded = $call ? ($call['executing_office_id'] ?? null) : null;
            $forwardNow = $execOffice && !$wasForwarded; // first time it gets an executing branch
            $notifyMgr = !empty($b['notify_manager']) ? 1 : 0;
            if ($call) {
                $set = implode(',', array_map(fn($f)=>"$f=?", $fields));
                $vals = array_map(fn($f)=> nzc_call($f, $b[$f] ?? ''), $fields); $vals[] = $call['id'];
                $pdo->prepare("UPDATE calls SET $set WHERE id=?")->execute($vals);
                $pdo->prepare("UPDATE calls SET notify_manager=? WHERE id=?")->execute([$notifyMgr, $call['id']]);
                if ($forwardNow) $pdo->prepare("UPDATE calls SET forwarded_at=?, status='FORWARDED' WHERE id=?")->execute([date('c'), $call['id']]);
                custom_save('call', $call['id'], $b);
                if ($forwardNow) send_forward_email($call['id']);
                flash("Call {$call['call_code']} updated." . ($forwardNow ? ' Forwarded — email sent to the branch.' : ''));
                redirect('/call?id=' . $call['id']);
            } else {
                $code = ops_next_code('calls', 'call_code', 'CALL');
                $cols = array_merge(['call_code'], $fields, ['notify_manager','forwarded_at','status','created_by','created_at']);
                $vals = array_merge([$code], array_map(fn($f)=> nzc_call($f, $b[$f] ?? ''), $fields),
                    [$notifyMgr, $execOffice ? date('c') : '', $execOffice ? 'FORWARDED' : 'OPEN', user_name(current_user()), date('c')]);
                $ph = implode(',', array_fill(0, count($cols), '?'));
                $pdo->prepare("INSERT INTO calls (" . implode(',', $cols) . ") VALUES ($ph)")->execute($vals);
                $id = $pdo->lastInsertId();
                custom_save('call', $id, $b);
                if ($execOffice) send_forward_email($id);
                flash("$code created." . ($execOffice ? ' Forwarded to the branch — email sent.' : ' Now allocate a job.'));
                redirect('/call?id=' . $id);
            }
        }
        view('ops/call_form', ['call' => $call, 'clients' => clients_list(), 'vendors' => vendors_list(),
            'offices' => offices_list(), 'error' => null, 'cfvals' => $call ? custom_values_map('call', $call['id']) : []]);
        return;
    }
    if ($route === 'call') {
        $call = ops_one("SELECT c.*, bp.legal_name client_name, bp.display_name client_disp, v.legal_name vendor_name,
            o.name ibo_name, x.name exec_name, x.coordinator_name, x.coordinator_email, x.manager_name, x.manager_email
            FROM calls c LEFT JOIN business_partners bp ON bp.id=c.client_id
            LEFT JOIN business_partners v ON v.id=c.vendor_id LEFT JOIN offices o ON o.id=c.ibo_office_id
            LEFT JOIN offices x ON x.id=c.executing_office_id WHERE c.id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$call) { http_response_code(404); view('notfound'); return; }
        $jobs = ops_all("SELECT j.*, i.name inspector_name FROM jobs j LEFT JOIN inspectors i ON i.id=j.inspector_id WHERE j.call_id=? ORDER BY j.id DESC", [$call['id']]);
        // lead-time metrics
        $firstJob = $jobs ? end($jobs) : null; // earliest allocated
        $allocDate = $firstJob ? ($firstJob['scheduled_date'] ?: substr($firstJob['created_at'], 0, 10)) : '';
        $fwdDate = $call['forwarded_at'] ? substr($call['forwarded_at'], 0, 10) : '';
        $lead = [
            'client_to_sgs' => days_between($call['call_received_date'], $call['inspection_required_date']),
            'to_executing'  => ($fwdDate && $call['inspection_required_date']) ? days_between($fwdDate, $call['inspection_required_date']) : null,
            'sched_delay'   => ($fwdDate && $allocDate) ? days_between($fwdDate, $allocDate) : null,
            'alloc_date'    => $allocDate,
        ];
        view('ops/call_detail', ['call' => $call, 'jobs' => $jobs, 'lead' => $lead]);
        return;
    }
}
function nz($v) { return $v === '' ? null : $v; }
function nzc_call($f, $v) {
    if (in_array($f, ['client_id','vendor_id','ibo_office_id','executing_office_id','activity_id'], true)) return $v === '' ? null : (int)$v;
    if ($f === 'expected_credit') return $v === '' ? 0 : $v;
    return $v;
}
// Email the executing branch's coordinator (+ manager if ticked) when a call is forwarded.
function send_forward_email($callId) {
    $c = ops_one("SELECT c.*, bp.legal_name client_name, bp.display_name client_disp, v.legal_name vendor_name, o.name office_name,
        o.coordinator_email, o.coordinator_name, o.manager_email, o.manager_name
        FROM calls c LEFT JOIN business_partners bp ON bp.id=c.client_id LEFT JOIN business_partners v ON v.id=c.vendor_id
        LEFT JOIN offices o ON o.id=c.executing_office_id WHERE c.id=?", [$callId]);
    if (!$c || !$c['coordinator_email']) return;
    $client = $c['client_disp'] ?: $c['client_name'];
    $to = $c['coordinator_email'];
    $cc = (!empty($c['notify_manager']) && $c['manager_email']) ? $c['manager_email'] : '';
    $body = "Dear {$c['coordinator_name']},\n\nAn inspection call is forwarded to {$c['office_name']} for execution.\n\n"
        . "Call: {$c['call_code']}\nClient: {$client}\nVendor/Site: {$c['vendor_name']}\n"
        . "Region: " . (OPS_REGIONS[$c['region']] ?? $c['region']) . "\nSBU: " . (OPS_SBUS[$c['sbu']] ?? $c['sbu']) . "\n"
        . "Activity: " . ($c['activity_id'] ? lk_value_path($c['activity_id']) : '—') . "\n"
        . "Client required date: {$c['inspection_required_date']}\n"
        . "Credit to executing branch: " . fmoney($c['expected_credit']) . " (" . (CREDIT_TYPES[$c['credit_type']] ?? '') . ")\n"
        . "Please allocate an inspector.\n\nRegards,\nSGS Ahmedabad (Managing office)";
    ops_mail($to, "Call forwarded: {$c['call_code']} — {$client}", $body, $cc, 'forward');
}

// ---- Jobs ------------------------------------------------------------------
function ops_jobs($route, $method) {
    $pdo = db();
    if ($route === 'jobs') {
        $q = trim($_GET['q'] ?? ''); $filter = $_GET['status'] ?? '';
        [$where, $args] = scope_clause('j.executing_office_id', 'j.sbu');
        if ($filter === 'open') $where .= " AND j.closed_flag=0";
        if ($filter === 'closed') $where .= " AND j.closed_flag=1";
        if ($q) { $where .= " AND (j.job_code LIKE ? OR bp.legal_name LIKE ?)"; array_push($args, "%$q%","%$q%"); }
        $rows = ops_all("SELECT j.*, c.call_code, bp.legal_name client_name, bp.display_name client_disp,
            i.name inspector_name FROM jobs j LEFT JOIN calls c ON c.id=j.call_id
            LEFT JOIN business_partners bp ON bp.id=c.client_id LEFT JOIN inspectors i ON i.id=j.inspector_id
            WHERE $where ORDER BY j.id DESC", $args);
        view('ops/jobs', ['rows' => $rows, 'q' => $q, 'filter' => $filter]); return;
    }
    if ($route === 'job-new' || $route === 'job-edit') {
        ops_require(is_coordinator_level(), 'Only coordinators and admins can allocate jobs.');
        $job = null; $call = null;
        if ($route === 'job-edit') {
            $job = ops_one("SELECT * FROM jobs WHERE id=?", [(int)($_GET['id'] ?? 0)]);
            if (!$job) { http_response_code(404); view('notfound'); return; }
            $call = ops_one("SELECT * FROM calls WHERE id=?", [$job['call_id']]);
        } else {
            $call = ops_one("SELECT * FROM calls WHERE id=?", [(int)($_GET['call'] ?? 0)]);
            if (!$call) { flash('Pick a call to allocate first.', 'error'); redirect('/calls'); }
        }
        if ($method === 'POST') {
            $b = $_POST;
            $fields = ['executing_office_id','inspector_id','subcon_id','job_type','scheduled_date','inspection_start_date','inspection_end_date',
                'random_date1','random_date2','random_date3','folder_link','boss_id','expected_credit','credit_type','credit_direction',
                'reporting_frequency','report_custom_days','inspection_type','activity_id','sbu','mandays','subcon_cost'];
            // deliverables come as a checkbox array -> stored as CSV of codes
            $deliverables = implode(',', array_filter((array)($b['deliverables'] ?? [])));
            // validation: expected credit mandatory at allocation
            if (($b['expected_credit'] ?? '') === '' || (float)$b['expected_credit'] <= 0) {
                view('ops/job_form', ['job'=>$job,'call'=>$call,'error'=>'Expected credit is mandatory at allocation.',
                    'offices'=>offices_list(),'inspectors'=>inspectors_list(),'subcons'=>subcons_list(),
                    'boss'=>boss_for_client($call['client_id']),'clientInfo'=>partner_full($call['client_id']),
                    'vendorInfo'=>partner_full($call['vendor_id']),'cfvals'=>$job?custom_values_map('job',$job['id']):[]]);
                return;
            }
            if ($job) {
                $set = implode(',', array_map(fn($f)=>"$f=?", $fields));
                $vals = array_map(fn($f)=> nzc($f, $b[$f] ?? ''), $fields); $vals[] = $job['id'];
                $pdo->prepare("UPDATE jobs SET $set WHERE id=?")->execute($vals);
                $pdo->prepare("UPDATE jobs SET deliverables=? WHERE id=?")->execute([$deliverables, $job['id']]);
                $jobId = $job['id'];
                flash("Job {$job['job_code']} updated.");
            } else {
                $code = ops_next_code('jobs', 'job_code', 'JOB');
                $cols = array_merge(['job_code','call_id'], $fields, ['deliverables','created_by','created_at']);
                $vals = array_merge([$code, $call['id']], array_map(fn($f)=> nzc($f, $b[$f] ?? ''), $fields), [$deliverables, user_name(current_user()), date('c')]);
                $ph = implode(',', array_fill(0, count($cols), '?'));
                $pdo->prepare("INSERT INTO jobs (" . implode(',', $cols) . ") VALUES ($ph)")->execute($vals);
                $jobId = $pdo->lastInsertId();
                $pdo->prepare("UPDATE calls SET status='ALLOCATED' WHERE id=?")->execute([$call['id']]);
                flash("$code allocated. Assignment email sent to inspector.");
            }
            custom_save('job', $jobId, $b);
            // comp-off if any inspection date is a Sunday
            ops_check_compoff($jobId);
            // assignment email when an inspector + schedule exist
            $jj = ops_one("SELECT * FROM jobs WHERE id=?", [$jobId]);
            if ($jj['inspector_id'] && $jj['scheduled_date']) send_assignment_email($jobId);
            redirect('/job?id=' . $jobId);
        }
        view('ops/job_form', ['job'=>$job,'call'=>$call,'error'=>null,'offices'=>offices_list(),
            'inspectors'=>inspectors_list(),'subcons'=>subcons_list(),'boss'=>boss_for_client($call['client_id']),
            'clientInfo'=>partner_full($call['client_id']),'vendorInfo'=>partner_full($call['vendor_id']),
            'cfvals'=>$job ? custom_values_map('job', $job['id']) : []]);
        return;
    }
    if ($route === 'job-close') {
        // Inspector (or coordinator) closes: report + expenses required
        $job = ops_one("SELECT * FROM jobs WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$job) { http_response_code(404); view('notfound'); return; }
        if ($method === 'POST') {
            $b = $_POST;
            $reportDate = $b['report_upload_date'] ?? '';
            if ($job['reporting_frequency'] !== 'NOREPORT' && $reportDate === '') {
                view('ops/job_close', ['job'=>$job,'error'=>'A report upload date is required before closing this job.']); return;
            }
            // save expenses row (same-day at closure)
            $pdo->prepare("INSERT INTO expenses (job_id,inspector_id,sbu,travel,local,food,lodging,misc,exp_date,notes,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute([
                $job['id'], $job['inspector_id'], $b['sbu'] ?: $job['sbu'],
                num($b['travel']), num($b['local']), num($b['food']), num($b['lodging']), num($b['misc']),
                $reportDate ?: date('Y-m-d'), $b['exp_notes'] ?? '', date('c')]);
            $tat = days_between($job['inspection_end_date'], $reportDate);
            $pdo->prepare("UPDATE jobs SET closed_flag=1, closed_at=?, report_upload_date=?, report_link=?, tat_days=? WHERE id=?")
                ->execute([date('c'), $reportDate, $b['report_link'] ?? '', $tat, $job['id']]);
            send_closure_email($job['id']);
            flash("Job {$job['job_code']} closed. TAT " . ($tat === null ? '—' : $tat) . " day(s). Closure email sent.");
            redirect('/job?id=' . $job['id']);
        }
        view('ops/job_close', ['job'=>$job,'error'=>null]); return;
    }
    if ($route === 'job') {
        $job = ops_one("SELECT j.*, c.call_code, c.region, c.product_category, bp.legal_name client_name, bp.display_name client_disp,
            v.legal_name vendor_name, i.name inspector_name, i.salary_ctc, s.agency subcon_agency, bn.boss_number, o.name office_name
            FROM jobs j LEFT JOIN calls c ON c.id=j.call_id
            LEFT JOIN business_partners bp ON bp.id=c.client_id LEFT JOIN business_partners v ON v.id=c.vendor_id
            LEFT JOIN inspectors i ON i.id=j.inspector_id LEFT JOIN subcons s ON s.id=j.subcon_id
            LEFT JOIN boss_numbers bn ON bn.id=j.boss_id LEFT JOIN offices o ON o.id=j.executing_office_id
            WHERE j.id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$job) { http_response_code(404); view('notfound'); return; }
        $expenses = ops_all("SELECT * FROM expenses WHERE job_id=? ORDER BY id", [$job['id']]);
        view('ops/job_detail', ['job'=>$job,'expenses'=>$expenses,'profit'=>job_profit($job)]);
        return;
    }
}
function nzc($f, $v) {
    $nullable = ['executing_office_id','inspector_id','subcon_id','boss_id','activity_id','report_custom_days'];
    if (in_array($f, $nullable) && $v === '') return null;
    if (in_array($f, ['expected_credit','mandays','subcon_cost']) && $v === '') return 0;
    return $v;
}
// Full partner detail (record + contacts + addresses) for the allocate-job info panel.
function partner_full($id) {
    if (!$id) return null;
    $p = ops_one("SELECT * FROM business_partners WHERE id=?", [$id]);
    if (!$p) return null;
    return [
        'p' => $p,
        'contacts' => ops_all("SELECT * FROM partner_contacts WHERE partner_id=? ORDER BY is_primary DESC, id", [$id]),
        'addresses' => ops_all("SELECT * FROM partner_addresses WHERE partner_id=? ORDER BY is_primary DESC, id", [$id]),
    ];
}
function num($v) { return $v === '' || $v === null ? 0 : (float)$v; }

// Grant comp-off if any of the job's inspection dates falls on a Sunday.
function ops_check_compoff($jobId) {
    $j = ops_one("SELECT * FROM jobs WHERE id=?", [$jobId]);
    if (!$j || !$j['inspector_id']) return;
    $dates = array_filter([$j['scheduled_date'], $j['inspection_start_date'], $j['inspection_end_date'], $j['random_date1'], $j['random_date2'], $j['random_date3']]);
    foreach ($dates as $d) {
        $ts = strtotime($d); if ($ts === false) continue;
        if ((int)date('w', $ts) === 0) { // Sunday
            $exists = ops_val("SELECT COUNT(*) FROM attendance WHERE inspector_id=? AND att_date=? AND compoff_earned=1", [$j['inspector_id'], $d]);
            if (!$exists) {
                $expiry = date('Y-m-d', strtotime($d . ' +30 days'));
                db()->prepare("INSERT INTO attendance (att_date,inspector_id,status,remarks,compoff_earned,compoff_expiry,created_at)
                    VALUES (?,?,?,?,?,?,?)")->execute([$d, $j['inspector_id'], 'PRESENT_NB', "Sunday work on {$j['job_code']}", 1, $expiry, date('c')]);
                db()->prepare("UPDATE inspectors SET compoff_balance = compoff_balance + 1 WHERE id=?")->execute([$j['inspector_id']]);
            }
        }
    }
}

// ---- Inspector self-service: my jobs ---------------------------------------
function ops_my_jobs() {
    $u = current_user();
    $insId = $u['inspector_id'] ?? null;
    if (!$insId && !is_coordinator_level()) { flash('Your login is not linked to an inspector yet. Ask an admin.', 'error'); redirect('/'); }
    if ($insId) {
        $rows = ops_all("SELECT j.*, c.call_code, bp.legal_name client_name, bp.display_name client_disp
            FROM jobs j LEFT JOIN calls c ON c.id=j.call_id LEFT JOIN business_partners bp ON bp.id=c.client_id
            WHERE j.inspector_id=? ORDER BY j.closed_flag, j.scheduled_date DESC", [$insId]);
    } else {
        $rows = ops_all("SELECT j.*, c.call_code, bp.legal_name client_name, bp.display_name client_disp
            FROM jobs j LEFT JOIN calls c ON c.id=j.call_id LEFT JOIN business_partners bp ON bp.id=c.client_id
            WHERE j.closed_flag=0 ORDER BY j.scheduled_date DESC");
    }
    view('ops/my_jobs', ['rows' => $rows]);
}

// ---- Dashboards / reports (scoped + filtered) ------------------------------
function job_eff_date($j) { return ($j['scheduled_date'] ?? '') !== '' ? $j['scheduled_date'] : substr($j['created_at'] ?? '', 0, 10); }

function ops_reports() {
    ops_require(can('dash.operations') || can('dash.financial') || can('dash.utilization') || can('dash.people'), 'You do not have dashboard access.');
    $seeFin = can('dash.financial'); $seeSalary = can('data.salary');
    // ---- filters ----
    $F = [
        'fy'    => $_GET['fy'] ?? current_fy(),
        'month' => $_GET['month'] ?? '',
        'office'=> $_GET['office'] ?? '',
        'sbu'   => $_GET['sbu'] ?? '',
        'insp'  => $_GET['insp'] ?? '',
        'act'   => $_GET['act'] ?? '',
        'itype' => $_GET['itype'] ?? '',
    ];
    [$fyFrom, $fyTo] = fy_range($F['fy']);
    // ---- scoped data ----
    [$scopeW, $scopeArgs] = scope_clause('j.executing_office_id', 'j.sbu');
    $jobs = ops_all("SELECT j.*, i.salary_ctc, i.name inspector_name, i.id ins_id, i.trade_id, bp.legal_name client_name, bp.display_name client_disp,
        c.region, o.name office_name FROM jobs j LEFT JOIN inspectors i ON i.id=j.inspector_id
        LEFT JOIN calls c ON c.id=j.call_id LEFT JOIN business_partners bp ON bp.id=c.client_id
        LEFT JOIN offices o ON o.id=j.executing_office_id WHERE $scopeW", $scopeArgs);
    [$cScopeW, $cScopeArgs] = scope_clause('c.executing_office_id', 'c.sbu');
    $calls = ops_all("SELECT c.*, (SELECT COUNT(*) FROM jobs j WHERE j.call_id=c.id AND j.scheduled_date<>'') sched_jobs
        FROM calls c WHERE $cScopeW", $cScopeArgs);
    // ---- apply filters in PHP (FY/month/office/sbu/inspector/activity/type) ----
    $filt = function($rows) use ($F, $fyFrom, $fyTo) {
        return array_values(array_filter($rows, function($r) use ($F, $fyFrom, $fyTo) {
            $d = isset($r['job_code']) ? job_eff_date($r) : (($r['call_received_date'] ?? '') ?: substr($r['created_at'] ?? '', 0, 10));
            if ($d) { if ($d < $fyFrom || $d > $fyTo) return false; if ($F['month'] !== '' && (int)substr($d,5,2) !== (int)$F['month']) return false; }
            if ($F['office'] !== '' && (int)($r['executing_office_id'] ?? 0) !== (int)$F['office']) return false;
            if ($F['sbu'] !== '' && ($r['sbu'] ?? '') !== $F['sbu']) return false;
            if ($F['insp'] !== '' && (int)($r['inspector_id'] ?? 0) !== (int)$F['insp']) return false;
            if ($F['act'] !== '' && (int)($r['activity_id'] ?? 0) !== (int)$F['act']) return false;
            if ($F['itype'] !== '' && ($r['inspection_type'] ?? '') !== $F['itype']) return false;
            return true;
        }));
    };
    $jobs = $filt($jobs); $calls = $filt($calls);
    $tatThresh = (int)setting_get('tat_threshold_days', 3);

    // ---- OPERATIONS ----
    $op = ['calls'=>count($calls), 'pending'=>0, 'open'=>0, 'closed'=>0, 'overdue'=>0, 'tatOn'=>0, 'tatLate'=>0, 'tatTotal'=>0, 'tatSum'=>0];
    foreach ($calls as $c) if ((int)$c['sched_jobs'] === 0 && ($c['status'] ?? '') !== 'CLOSED') $op['pending']++;
    $today = date('Y-m-d');
    foreach ($jobs as $j) {
        if ($j['closed_flag']) { $op['closed']++; if ($j['tat_days']!==null){ $op['tatTotal']++; $op['tatSum']+=(int)$j['tat_days']; if ((int)$j['tat_days']<=$tatThresh) $op['tatOn']++; else $op['tatLate']++; } }
        else { $op['open']++; $end=$j['inspection_end_date']?:$j['scheduled_date']; if ($end && $end<$today) $op['overdue']++; }
    }

    // ---- FINANCIAL ----
    $fin = ['credit'=>0,'recv'=>0,'given'=>0,'labour'=>0,'exp'=>0,'subcon'=>0,'profit'=>0,'bySbu'=>[],'byOffice'=>[],'expHead'=>['travel'=>0,'local'=>0,'food'=>0,'lodging'=>0,'misc'=>0]];
    $byInspector=[];
    foreach ($jobs as $j) {
        $p = job_profit($j);
        $fin['credit']+=$p['credit']; $fin['labour']+=$p['labour']; $fin['exp']+=$p['expenses']; $fin['subcon']+=$p['subcon']; $fin['profit']+=$p['profit'];
        if (($j['credit_direction']??'')==='GIVEN') $fin['given']+=$p['credit']; else $fin['recv']+=$p['credit'];
        $sk=$j['sbu']?:'—'; $fin['bySbu'][$sk]=($fin['bySbu'][$sk]??0)+$p['credit'];
        $ok=$j['office_name']?:'Ahmedabad'; $fin['byOffice'][$ok]=($fin['byOffice'][$ok]??0)+$p['credit'];
        foreach (ops_all("SELECT * FROM expenses WHERE job_id=?", [$j['id']]) as $x)
            foreach (['travel','local','food','lodging','misc'] as $h) $fin['expHead'][$h]+=(float)$x[$h];
        $key=$j['ins_id']?:0;
        if (!isset($byInspector[$key])) $byInspector[$key]=['name'=>$j['inspector_name']?:'(unassigned)','credit'=>0,'cost'=>0,'exp'=>0,'profit'=>0,'jobs'=>0,'mandays'=>0];
        $byInspector[$key]['credit']+=$p['credit'];$byInspector[$key]['cost']+=$p['labour'];$byInspector[$key]['exp']+=$p['expenses'];$byInspector[$key]['profit']+=$p['profit'];$byInspector[$key]['jobs']++;$byInspector[$key]['mandays']+=$p['mandays'];
    }
    $recF=$F['fy']; // reconciliation is by month string; keep simple totals across scope for now
    $fin['reconRecv']=(float)ops_val("SELECT COALESCE(SUM(credit_actual),0) FROM credit_recon WHERE direction='RECEIVED'");
    $fin['reconGiven']=(float)ops_val("SELECT COALESCE(SUM(credit_actual),0) FROM credit_recon WHERE direction='GIVEN'");

    // ---- UTILIZATION ----
    $wd = working_days_in_month((int)date('Y'), (int)date('n'));
    $util=[]; $mdBySbu=[]; $depMd=0;$inspMd=0;$subMd=0;
    foreach ($jobs as $j) { $md=job_mandays($j); $sk=$j['sbu']?:'—'; $mdBySbu[$sk]=($mdBySbu[$sk]??0)+$md;
        if (($j['job_type']??'')==='DEPUTATION') $depMd+=$md; else $inspMd+=$md; if ($j['subcon_id']) $subMd+=$md; }
    foreach (inspectors_list(false) as $ins) { $md=0; foreach ($jobs as $j) if ($j['ins_id']==$ins['id']) $md+=job_mandays($j);
        if ($md>0 || $F['insp']==='' ) $util[]=['name'=>$ins['name'],'mandays'=>$md,'working'=>$wd,'pct'=>$wd?round($md/$wd*100):0]; }

    // ---- PEOPLE & COMPLIANCE ----
    $certExp = ops_all("SELECT c.*, i.name inspector_name FROM inspector_certs c JOIN inspectors i ON i.id=c.inspector_id WHERE c.valid_to<>'' ORDER BY c.valid_to");
    $certSoon=[]; foreach ($certExp as $c){ $dleft=days_between($today,$c['valid_to']); if ($dleft!==null && $dleft<=90){ $c['days']=$dleft; $certSoon[]=$c; } }
    $byTrade=[]; foreach (ops_all("SELECT trade_id, COUNT(*) n FROM inspectors WHERE status='ACTIVE' GROUP BY trade_id") as $r){ $byTrade[trade_label($r['trade_id'])]=$r['n']; }

    // ---- filter option lists (scope-limited) ----
    $offOpts = scope_offices()==='ALL' ? offices_list() : array_filter(offices_list(), fn($o)=>in_array((int)$o['id'], scope_offices()));
    $sbuAll = lk_options_or('sbu', OPS_SBUS);
    $sbuOpts = scope_sbus()==='ALL' ? $sbuAll : array_intersect_key($sbuAll, array_flip(scope_sbus()));

    view('ops/reports', [
        'F'=>$F, 'seeFin'=>$seeFin, 'seeSalary'=>$seeSalary, 'tatThresh'=>$tatThresh,
        'op'=>$op, 'fin'=>$fin, 'byInspector'=>$byInspector, 'util'=>$util, 'mdBySbu'=>$mdBySbu,
        'depMd'=>$depMd, 'inspMd'=>$inspMd, 'subMd'=>$subMd, 'certSoon'=>$certSoon, 'byTrade'=>$byTrade,
        'fyOpts'=>fy_options(6), 'offOpts'=>$offOpts, 'sbuOpts'=>$sbuOpts,
        'inspOpts'=>inspectors_list(false), 'actType'=>lk_type('activity'),
        'itypeOpts'=>lk_options_or('inspection_type', INSPECTION_TYPES),
        'canOps'=>can('dash.operations'),'canUtil'=>can('dash.utilization'),'canPeople'=>can('dash.people'),
    ]);
}

// ---- Users, roles, access --------------------------------------------------
function ops_users($route, $method) {
    ops_require(can('users.manage.branch') || can('users.manage.global'), 'You cannot manage users.');
    $pdo = db();
    $globalMgr = can('users.manage.global');          // Master Admin / Branch Manager (global)
    $myOffice = current_user()['home_office_id'] ?? null;
    if ($route === 'user-new' || $route === 'user-edit') {
        $user = null;
        if ($route === 'user-edit') {
            $user = ops_one("SELECT * FROM users WHERE id=?", [(int)($_GET['id'] ?? 0)]);
            if (!$user) { http_response_code(404); view('notfound'); return; }
            // branch app manager may only touch users in their own office
            if (!$globalMgr && (int)($user['home_office_id'] ?? 0) !== (int)$myOffice) { flash('You can only manage users in your own office.', 'error'); redirect('/users'); }
        }
        if ($method === 'POST') {
            $b = $_POST;
            $allowedRoles = $globalMgr ? array_keys(ORG_ROLES) : ['OPERATION_MANAGER','ASST_MANAGER','COORDINATOR','INSPECTOR'];
            $role = in_array($b['role'] ?? '', $allowedRoles, true) ? $b['role'] : 'COORDINATOR';
            $isSuper = $role === 'MASTER_ADMIN' ? 1 : 0;
            $insId = ($b['inspector_id'] ?? '') !== '' ? (int)$b['inspector_id'] : null;
            // scope: global managers set anything; branch managers pin to their office
            $homeOffice = $globalMgr ? (($b['home_office_id'] ?? '') !== '' ? (int)$b['home_office_id'] : null) : $myOffice;
            $scopeOffices = $globalMgr ? trim($b['scope_offices'] ?? '') : '';   // '' = OWN(home)
            $scopeSbus = trim($b['scope_sbus'] ?? '');
            // permissions: branch mgr can only grant a safe subset (no salary/global/settings)
            $chosen = array_filter((array)($b['permissions'] ?? []));
            if (!$globalMgr) $chosen = array_intersect($chosen, ['dash.operations','dash.utilization','data.credit','ops.call.create','ops.job.allocate','ops.job.close','master.manage']);
            $perms = implode(',', $chosen);
            if ($user) {
                $pdo->prepare("UPDATE users SET username=?,first_name=?,last_name=?,email=?,role=?,is_superuser=?,is_active=?,inspector_id=?,home_office_id=?,scope_offices=?,scope_sbus=?,permissions=? WHERE id=?")
                    ->execute([$b['username'], $b['first_name'] ?? '', $b['last_name'] ?? '', $b['email'] ?? '', $role, $isSuper, !empty($b['is_active'])?1:0, $insId, $homeOffice, $scopeOffices, $scopeSbus, $perms, $user['id']]);
                if (!empty($b['password'])) $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($b['password'], PASSWORD_DEFAULT), $user['id']]);
                flash('User saved.');
            } else {
                $hash = password_hash($b['password'] ?: 'changeme123', PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (username,password_hash,first_name,last_name,email,role,is_superuser,is_active,inspector_id,home_office_id,scope_offices,scope_sbus,permissions)
                    VALUES (?,?,?,?,?,?,?,1,?,?,?,?,?)")->execute([$b['username'], $hash, $b['first_name'] ?? '', $b['last_name'] ?? '', $b['email'] ?? '', $role, $isSuper, $insId, $homeOffice, $scopeOffices, $scopeSbus, $perms]);
                flash('User created.');
            }
            redirect('/users');
        }
        view('ops/user_form', ['user'=>$user,'inspectors'=>inspectors_list(false),'offices'=>offices_list(),
            'sbuOpts'=>lk_options_or('sbu', OPS_SBUS),'globalMgr'=>$globalMgr,'defaults'=>role_defaults($user['role'] ?? 'COORDINATOR')]); return;
    }
    $where = $globalMgr ? "1=1" : "home_office_id = " . (int)$myOffice;
    $rows = ops_all("SELECT * FROM users WHERE $where ORDER BY username");
    $seats = getenv('SEAT_LIMIT') ?: '';
    view('ops/users', ['rows'=>$rows,'seats'=>$seats,'active'=>(int)ops_val("SELECT COUNT(*) FROM users WHERE is_active=1"),'globalMgr'=>$globalMgr]);
}

// ---- System settings (financial year, etc.) --------------------------------
function ops_settings($method) {
    ops_require(can('settings.manage'), 'Only admins can change settings.');
    if ($method === 'POST') {
        $m = (int)($_POST['fy_start_month'] ?? 4);
        setting_set('fy_start_month', ($m >= 1 && $m <= 12) ? $m : 4);
        setting_set('tat_threshold_days', (int)($_POST['tat_threshold_days'] ?? 3));
        flash('Settings saved.');
        redirect('/settings');
    }
    view('ops/settings', []);
}

function ops_change_password($method) {
    $pdo = db(); $u = current_user();
    if ($method === 'POST') {
        $b = $_POST;
        if (!password_verify($b['current'] ?? '', $u['password_hash'])) {
            view('ops/change_password', ['error'=>'Current password is incorrect.']); return;
        }
        if (strlen($b['new'] ?? '') < 8) { view('ops/change_password', ['error'=>'New password must be at least 8 characters.']); return; }
        if (($b['new'] ?? '') !== ($b['confirm'] ?? '')) { view('ops/change_password', ['error'=>'New password and confirmation do not match.']); return; }
        $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($b['new'], PASSWORD_DEFAULT), $u['id']]);
        flash('Password changed.');
        redirect('/');
    }
    view('ops/change_password', ['error'=>null]);
}
