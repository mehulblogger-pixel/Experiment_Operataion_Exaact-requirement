<?php
// ============================================================================
//  Operations & Finance engine for the Exaact Inspection & Operations Management System
//  Phases 1-5: Calls, Jobs, Closure/Expenses, SubCon/Attendance/Holidays,
//  Credit logic, Dashboards + Reconciliation. Plus roles, email and reminders.
//  Kept in one file so a non-coder can find everything about "operations" here.
// ============================================================================

// ---- Choice lists (labels shown in dropdowns) ------------------------------
const OPS_REGIONS = ['WEST'=>'West','NORTH'=>'North','SOUTH'=>'South','EAST'=>'East','CENTRAL'=>'Central','OVERSEAS'=>'Overseas'];
const OPS_SBUS = ['IND'=>'Industrial','OGC'=>'Oil, Gas & Chemicals','MIN'=>'Minerals','GIS'=>'Governments & Institutions','AGRI'=>'Agriculture & Food','CRS'=>'Consumer & Retail','ENV'=>'Environment','OTHER'=>'Other'];
const PRODUCT_CATS = ['ELEC'=>'Electrical equipment','MECH'=>'Mechanical equipment','STRUCT'=>'Structural / Fabrication','PIPE'=>'Pipes & Fittings','VALVE'=>'Valves','PUMP'=>'Pumps & Rotating','TRANSFORMER'=>'Transformers','CABLE'=>'Cables','INSTRUMENT'=>'Instrumentation','CIVIL'=>'Civil / Construction','OTHER'=>'Others'];
const CREDIT_TYPES = ['MANDAY'=>'Man-day','MANMONTH'=>'Man-month','LUMP'=>'Lump sum','LATER'=>'Decide later','OTHER'=>'Other'];
const CREDIT_DIRECTIONS = ['RECEIVED'=>'Received (IBO → Ahmedabad)','GIVEN'=>'Given (Ahmedabad → IBO)'];
const REPORT_FREQ = ['DAILY'=>'Daily','ALTERNATE'=>'Alternate day','WEEKLY'=>'Weekly','FORTNIGHTLY'=>'Fortnightly','MONTHLY'=>'Monthly','CUSTOM'=>'Custom (every N days)','NOREPORT'=>'No report'];
// Types of inspection service (third-party inspection industry).
const INSPECTION_TYPES = ['INSPECTION'=>'Inspection (third-party / TPI)','EXPEDITING'=>'Expediting','DEPUTATION'=>'Resident / site posting','VENDOR_ASSESS'=>'Vendor assessment','VENDOR_AUDIT'=>'Vendor audit','PRE_PROD'=>'Pre-production inspection','DURING_PROD'=>'During-production inspection','STAGE'=>'Stage / In-process inspection','FINAL'=>'Final inspection','FRI'=>'Final random inspection (FRI)','PSI'=>'Pre-shipment inspection (PSI)','WITNESS'=>'Witness / Test witnessing','FAT'=>'Factory Acceptance Test (FAT)','SAT'=>'Site Acceptance Test (SAT)','SOURCE'=>'Source inspection','SURVEILLANCE'=>'Surveillance','LOADING'=>'Loading / container supervision','SAMPLING'=>'Sampling','DIMENSIONAL'=>'Dimensional inspection','WELDING'=>'Welding inspection','NDT'=>'NDT witnessing','PMI'=>'Material verification (PMI)','COATING'=>'Painting / coating inspection','MECH_TEST'=>'Mechanical testing witness','CALIB'=>'Calibration verification','SAFETY_AUDIT'=>'Safety audit','SYSTEM_AUDIT'=>'Management-system audit','SECOND_PARTY'=>'Second-party audit','DESKTOP'=>'Desktop / Document review','TECH_AUDIT'=>'Technical audit','SUPPLY_CHAIN'=>'Supply-chain posting','SITE_SUP'=>'Site supervision','COMMISSIONING'=>'Commissioning & installation','SITE_QAQC'=>'Site QA / QC','TYPE_TEST'=>'Type test','TENDER_REVIEW'=>'Tender review','OTHER'=>'Other'];
// Deliverables / report formats produced after a job.
const DELIVERABLES = ['IR'=>'Inspection Report (IR)','IRN'=>'Inspection Release Note (IRN)','NCR'=>'Non-Conformance Report (NCR)','COC'=>'Certificate of Conformity (CoC)','EXP_REP'=>'Expediting Report','VA_REP'=>'Vendor Assessment Report','AUDIT_REP'=>'Audit Report','TC_REVIEW'=>'Test Certificate Review','DPR'=>'Daily Progress Report','FINAL_REP'=>'Final Report','PUNCH'=>'Punch List','PHOTO'=>'Photographic Report','DIM_REP'=>'Dimensional Report','RN'=>'Release Note (RN)'];
const ATT_STATUS = ['PRESENT_NB'=>'Present (non-billable)','TRAINING'=>'Training','MEETING'=>'Meeting','LEAVE'=>'Leave','WFH'=>'Work from home','COMPOFF'=>'Comp-off taken','HOLIDAY'=>'Holiday'];
// Daily availability of an inspector (the coordinator's board). ON_JOB is auto-derived
// from today's jobs; the others are set with one click. Configurable via the lookup master.
const AVAIL_STATUS = ['AVAILABLE'=>'Available (free)','ON_JOB'=>'On job / allocated','OFFICE'=>'In office','LEAVE'=>'On leave','TRAINING'=>'Training','WFH'=>'Work from home','HALF_DAY'=>'Half day','TRAVEL'=>'On travel'];
// Maximum working hours an inspector may log on a single working day (8 h 30 m).
const DAILY_HOURS_CAP = 8.5;
// The record itself is a Deputation (see Settings -> Terminology); these are the
// two ways one is worked — day by day, or as a continuous posting at a site.
const JOB_TYPES = ['INSPECTION'=>'Day-based inspection','DEPUTATION'=>'Resident / site posting'];
const EXPENSE_HEADINGS = ['TRAVEL'=>'Travel','LOCAL'=>'Local conveyance','FOOD'=>'Food','LODGING'=>'Lodging','MISC'=>'Misc'];
const DEPARTMENTS = ['QUALITY'=>'Quality','PROJECTS'=>'Projects','ENGINEERING'=>'Engineering','DESIGN'=>'Design','INSPECTION'=>'Inspection','PROCUREMENT'=>'Procurement / Purchase','PRODUCTION'=>'Production','MAINTENANCE'=>'Maintenance','SAFETY'=>'Safety / HSE','COMMERCIAL'=>'Commercial / Finance','STORES'=>'Stores','PLANNING'=>'Planning','OWNER'=>'Owner','PARTNER'=>'Partner','DIRECTOR'=>'Director','MANAGEMENT'=>'Management','OTHER'=>'Other'];
const DESIGNATIONS = ['INSPECTOR'=>'Inspector','SR_INSPECTOR'=>'Sr. Inspector','LEAD_INSPECTOR'=>'Lead Inspector','EXECUTIVE'=>'Executive','SR_EXECUTIVE'=>'Sr. Executive','ENGINEER'=>'Engineer','SR_ENGINEER'=>'Sr. Engineer','LEAD_ENGINEER'=>'Lead Engineer','COORDINATOR'=>'Coordinator','SR_COORDINATOR'=>'Sr. Coordinator','ASST_MANAGER'=>'Asst. Manager','DY_MANAGER'=>'Deputy Manager','MANAGER'=>'Manager','SR_MANAGER'=>'Sr. Manager','BRANCH_MANAGER'=>'Branch Manager','SBU_HEAD'=>'SBU Head','GM'=>'General Manager','DIRECTOR'=>'Director','OTHER'=>'Other'];
const JOB_STAGES = ['ALLOCATED'=>'Allocated','TRAVELLING'=>'Travelling','IN_PROGRESS'=>'Inspection in progress','REPORT_PENDING'=>'Report pending','SUBMITTED'=>'Report submitted','CLOSED'=>'Closed','ON_HOLD'=>'On hold','CANCELLED'=>'Cancelled'];
const EXP_LEVELS = ['JUNIOR'=>'Junior','MID'=>'Mid','SENIOR'=>'Senior','EXPERT'=>'Expert / Lead'];
// Sub-contractor rate basis — the same charge units, narrowed to the two that apply.
const RATE_TYPES = ['MANDAY'=>'Man-day','MANMONTH'=>'Man-month'];
// Agency types: recruitment = CVs only, one-time placement fee, person on our own roll;
// manpower = supplies people on the AGENCY's roll, bills us monthly (pass-through to client).
const AGENCY_TYPES = ['RECRUITMENT'=>'Recruitment agency (CVs only · one-time fee)', 'MANPOWER'=>'Manpower / supply agency (monthly bill)'];
const ROLL_TYPES = ['OWN'=>'On our roll (we pay salary)', 'AGENCY'=>'On agency roll (agency bills us monthly)'];
// Recruitment fee is conditional on the agency's free-replacement guarantee:
// provisional while inside the window, confirmed if the person stays past it,
// waived (₹0, free replacement) if they leave within it.
const FEE_STATUS = ['PROVISIONAL'=>'Provisional (within guarantee)', 'CONFIRMED'=>'Confirmed (payable)', 'WAIVED'=>'Waived (left within guarantee)'];
// Manpower requisition (management approval for a position — mandatory before hiring).
const REQ_TYPES  = ['NEW'=>'New position (new project / expansion)', 'REPLACEMENT'=>'Replacement (engineer who left)'];
const REQ_STATUS = ['OPEN'=>'Open (approved, sourcing)', 'PROPOSED'=>'Candidate proposed', 'OFFERED'=>'Offer released', 'HIRED'=>'Hired (filled)', 'CLOSED'=>'Closed', 'CANCELLED'=>'Cancelled'];
const BOSS_STATUS = ['ACTIVE'=>'Active','CLOSED'=>'Closed','HOLD'=>'On hold'];
const OVERHEAD_PCT = 8; // salary overhead %
// Built-in theme presets: primary, accent, page background, surface (cards), text.
const THEME_PRESETS = [
    'blue'     => ['label'=>'Corporate Blue',  'primary'=>'#1e40af','accent'=>'#0ea5e9','bg'=>'#f4f6f9','surface'=>'#ffffff','text'=>'#1f2937'],
    'orange'   => ['label'=>'Corporate Orange',      'primary'=>'#c2410c','accent'=>'#f59e0b','bg'=>'#fbf7f4','surface'=>'#ffffff','text'=>'#292524'],
    'forest'   => ['label'=>'Forest Green',    'primary'=>'#15803d','accent'=>'#10b981','bg'=>'#f1f7f3','surface'=>'#ffffff','text'=>'#14261c'],
    'purple'   => ['label'=>'Royal Purple',    'primary'=>'#6d28d9','accent'=>'#a78bfa','bg'=>'#f7f5fb','surface'=>'#ffffff','text'=>'#1f1b2e'],
    'teal'     => ['label'=>'Teal',            'primary'=>'#0f766e','accent'=>'#14b8a6','bg'=>'#eff7f6','surface'=>'#ffffff','text'=>'#14312e'],
    'crimson'  => ['label'=>'Crimson',         'primary'=>'#be123c','accent'=>'#fb7185','bg'=>'#fdf5f6','surface'=>'#ffffff','text'=>'#2a1216'],
    'midnight' => ['label'=>'Midnight (dark)', 'primary'=>'#1e293b','accent'=>'#38bdf8','bg'=>'#0f172a','surface'=>'#1e293b','text'=>'#e2e8f0'],
];
// Chart palette (used by dashboard SVG charts; theme accent leads).
const CHART_COLORS = ['#0ea5e9','#f59e0b','#10b981','#a78bfa','#fb7185','#14b8a6','#f97316','#6366f1','#84cc16','#ec4899'];
// CV / hiring (deputation resourcing) pipeline: a candidate's CV moves through
// these stages when a client needs a deputed / resident engineer.
const CAND_STAGES = [
    'RECEIVED'   => 'CV received',
    'SUBMITTED'  => 'Submitted to client',
    'SHORTLISTED'=> 'Shortlisted',
    'INTERVIEW'  => 'Interview scheduled',
    'OFFERED'    => 'Offer released',
    'OFFER_DECLINED' => 'Offer declined (backed out)',
    'HOLD'       => 'On hold',
    'REJECTED'   => 'Rejected',
    'ACCEPTED'   => 'Accepted (Hired)',
    'WITHDRAWN'  => 'Withdrawn',
];
// Where a candidate is sourced from (mirrors inspector engineer-type).
const CAND_SOURCES = ['ASSET'=>'Own employee','FREELANCER'=>'Freelancer','SUBCON'=>'Sub-contractor','HR_AGENCY'=>'HR / Manpower agency'];
// Sources that draw their Agency from the sub-contractor / agency master.
const CAND_AGENCY_SOURCES = ['SUBCON','HR_AGENCY'];

// ---- Expense / voucher module (P1: masters & codes) ------------------------
// How an expense head behaves. PER_KM = km × rate (rate from the travel mode /
// inspector); BILL = actual amount from a receipt; ALLOWANCE = fixed/daily amount.
const EXP_HEAD_TYPES = ['PER_KM'=>'Per-km (km × rate)','BILL'=>'Actual bill','ALLOWANCE'=>'Fixed allowance'];
const TRAVEL_BASIS = ['PER_KM'=>'Per km','ACTUAL'=>'Actual (ticket / bill)'];
// Seed rows for the monthly "Statement of Travelling Expenses" columns.
const EXPENSE_HEADS_SEED = [
    // code, label, head_type, needs_receipt, sort
    ['KMTRAVEL','Travel charges (KM × rate)','PER_KM',0,10],
    ['BUS','Bus ticket','BILL',1,20],
    ['TRAIN','Train ticket','BILL',1,30],
    ['AIR','Air ticket','BILL',1,40],
    ['HOTEL','Hotel / Boarding & Lodging (out-station)','BILL',1,50],
    ['FOOD','Food allowance (meals)','ALLOWANCE',0,60],
    ['FOODBILL','Food bills (actual)','BILL',1,65],
    ['CAB','Ola / Uber','BILL',1,70],
    ['AUTO','Auto / local conveyance','BILL',1,80],
    ['TEL','Telephone & communication','BILL',1,90],
    ['OUTSTN','Outstation allowance','ALLOWANCE',0,100],
    ['CASH','Cash purchase bills','BILL',1,110],
    ['OTHER','Others (specify)','BILL',0,120],
];
const TRAVEL_MODES_SEED = [
    // code, label, basis, default_rate
    ['BIKE','Bike / Two-wheeler','PER_KM',6],
    ['CAR','Car (four-wheeler)','PER_KM',12],
    ['OWNCAR','Own car','PER_KM',12],
    ['OLA','Ola / Uber','ACTUAL',0],
    ['AUTO','Auto / local','ACTUAL',0],
    ['BUS','Bus','ACTUAL',0],
    ['TRAIN','Train','ACTUAL',0],
    ['AIR','Air','ACTUAL',0],
];
// Leave-type and day (office/WFH/holiday) codes for the voucher's attendance column.
const LEAVE_TYPES = ['CL'=>'Casual Leave','SL'=>'Sick Leave','PL'=>'Privilege / Earned Leave','LWP'=>'Leave Without Pay','COMPOFF'=>'Comp-off','ML'=>'Maternity Leave','OTHER'=>'Other leave'];
const DAY_CODES   = ['OFFICE'=>'In office','WFH'=>'Work from home','TRAINING'=>'Training','HOLIDAY'=>'Holiday','WEEKOFF'=>'Week-off'];

// ---- Schema ----------------------------------------------------------------
function ops_ensure_schema() {
    $pdo = db(); $pk = pk_clause();
    $t = [
        "CREATE TABLE IF NOT EXISTS offices (
            id $pk, code VARCHAR(20), name VARCHAR(150), city VARCHAR(120) DEFAULT '',
            is_ahmedabad INT DEFAULT 0)",
        "CREATE TABLE IF NOT EXISTS back_office_staff (
            id $pk, name VARCHAR(150), emp_code VARCHAR(40) DEFAULT '', designation VARCHAR(40) DEFAULT '',
            department VARCHAR(40) DEFAULT '', office_id INT NULL, email VARCHAR(200) DEFAULT '',
            mobile VARCHAR(40) DEFAULT '', ctc DECIMAL(14,2) DEFAULT 0, allowances DECIMAL(14,2) DEFAULT 0,
            status VARCHAR(20) DEFAULT 'ACTIVE', created_at VARCHAR(30) DEFAULT '')",
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
        // CV / hiring pipeline (deputation resourcing) — one row per candidate CV.
        "CREATE TABLE IF NOT EXISTS candidates (
            id $pk, cand_code VARCHAR(30) DEFAULT '', first_name VARCHAR(80) DEFAULT '',
            middle_name VARCHAR(80) DEFAULT '', last_name VARCHAR(80) DEFAULT '',
            client_id INT NULL, call_id INT NULL, trade_id INT NULL, skill_id INT NULL,
            designation VARCHAR(40) DEFAULT '', source VARCHAR(20) DEFAULT 'FREELANCER',
            agency VARCHAR(150) DEFAULT '', proposed_site VARCHAR(200) DEFAULT '',
            sbu VARCHAR(20) DEFAULT '', experience_years DECIMAL(5,1) DEFAULT 0,
            email VARCHAR(200) DEFAULT '', mobile VARCHAR(40) DEFAULT '',
            cv_link VARCHAR(500) DEFAULT '', expected_rate DECIMAL(12,2) DEFAULT 0,
            rate_type VARCHAR(20) DEFAULT 'MANDAY', cv_received_date VARCHAR(20) DEFAULT '',
            stage VARCHAR(20) DEFAULT 'RECEIVED', decided_at VARCHAR(30) DEFAULT '',
            inspector_id INT NULL, remarks TEXT, created_by VARCHAR(150) DEFAULT '',
            created_at VARCHAR(30) DEFAULT '')",
        // stage-change log for a candidate (who moved it, when, note).
        "CREATE TABLE IF NOT EXISTS candidate_events (
            id $pk, candidate_id INT, from_stage VARCHAR(20) DEFAULT '', to_stage VARCHAR(20) DEFAULT '',
            remark VARCHAR(500) DEFAULT '', actor VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')",
        // Expense/voucher module — configurable expense heads (one column each on the voucher).
        "CREATE TABLE IF NOT EXISTS expense_heads (
            id $pk, code VARCHAR(30), label VARCHAR(150), head_type VARCHAR(20) DEFAULT 'BILL',
            default_rate DECIMAL(12,2) DEFAULT 0, needs_receipt INT DEFAULT 0, sort_order INT DEFAULT 100,
            active INT DEFAULT 1)",
        // Travel modes (bike/car/…): per-km vs actual, with a default rate.
        "CREATE TABLE IF NOT EXISTS travel_modes (
            id $pk, code VARCHAR(30), label VARCHAR(150), basis VARCHAR(20) DEFAULT 'PER_KM',
            default_rate DECIMAL(12,2) DEFAULT 0, active INT DEFAULT 1)",
        // Per-inspector entitlements — which heads/modes each inspector may claim,
        // plus a rate override. Managed ONLY by the Super Admin.
        "CREATE TABLE IF NOT EXISTS inspector_allowances (
            id $pk, inspector_id INT, kind VARCHAR(10), code VARCHAR(30),
            allowed INT DEFAULT 0, rate_override DECIMAL(12,2) NULL)",
        // Monthly inspector voucher ("Statement of Travelling Expenses").
        "CREATE TABLE IF NOT EXISTS vouchers (
            id $pk, inspector_id INT, office_id INT NULL, month VARCHAR(7),
            status VARCHAR(20) DEFAULT 'DRAFT', nature VARCHAR(30) DEFAULT '',
            advance DECIMAL(12,2) DEFAULT 0, office_incurred DECIMAL(12,2) DEFAULT 0,
            supporting_file MEDIUMTEXT, supporting_name VARCHAR(200) DEFAULT '',
            total DECIMAL(14,2) DEFAULT 0, submitted_at VARCHAR(30) DEFAULT '',
            checked_by VARCHAR(150) DEFAULT '', approved_by VARCHAR(150) DEFAULT '',
            authorized_by VARCHAR(150) DEFAULT '', approved_at VARCHAR(30) DEFAULT '',
            created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')",
        // Remembered km per inspector+vendor (so it auto-fills next visit).
        "CREATE TABLE IF NOT EXISTS vendor_km_memory (
            id $pk, inspector_id INT, vendor_id INT, mode_code VARCHAR(30) DEFAULT '', km DECIMAL(8,1) DEFAULT 0)",
        // One row per day-segment (a site visit, office, or leave). Multiple per date.
        "CREATE TABLE IF NOT EXISTS voucher_entries (
            id $pk, voucher_id INT, entry_date VARCHAR(20), day_type VARCHAR(15) DEFAULT 'WORK',
            job_id INT NULL, boss_id INT NULL, client_id INT NULL, vendor_id INT NULL,
            file_no VARCHAR(60) DEFAULT '', line_no VARCHAR(30) DEFAULT '', sbu VARCHAR(20) DEFAULT '',
            site_label VARCHAR(255) DEFAULT '', hours DECIMAL(5,2) DEFAULT 0,
            mode_code VARCHAR(30) DEFAULT '', km DECIMAL(8,1) DEFAULT 0, travel_amount DECIMAL(12,2) DEFAULT 0,
            amounts TEXT, row_total DECIMAL(12,2) DEFAULT 0,
            leave_code VARCHAR(20) DEFAULT '', office_code VARCHAR(20) DEFAULT '', notes VARCHAR(255) DEFAULT '',
            is_auto INT DEFAULT 0, sort_order INT DEFAULT 0)",
        // Manpower requisition / position approval — the front of the hiring chain
        "CREATE TABLE IF NOT EXISTS requisitions (
            id $pk, req_code VARCHAR(30) DEFAULT '', office_id INT NULL, sbu VARCHAR(20) DEFAULT '',
            designation VARCHAR(40) DEFAULT '', project_site VARCHAR(200) DEFAULT '',
            req_type VARCHAR(20) DEFAULT 'NEW', outgoing_inspector_id INT NULL,
            budgeted_cost DECIMAL(14,2) DEFAULT 0, approved_by VARCHAR(150) DEFAULT '',
            approval_ref VARCHAR(80) DEFAULT '', approval_date VARCHAR(20) DEFAULT '',
            status VARCHAR(20) DEFAULT 'OPEN', hired_inspector_id INT NULL,
            notes VARCHAR(255) DEFAULT '', created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')",
        // Recruitment / manpower agencies + their contracts (renewal reminders)
        "CREATE TABLE IF NOT EXISTS agencies (
            id $pk, name VARCHAR(150), agency_type VARCHAR(20) DEFAULT 'MANPOWER',
            contact_person VARCHAR(150) DEFAULT '', email VARCHAR(200) DEFAULT '', mobile VARCHAR(40) DEFAULT '',
            gstin VARCHAR(20) DEFAULT '', contract_number VARCHAR(60) DEFAULT '',
            contract_start VARCHAR(20) DEFAULT '', contract_end VARCHAR(20) DEFAULT '',
            one_time_fee DECIMAL(14,2) DEFAULT 0, monthly_rate DECIMAL(14,2) DEFAULT 0,
            guarantee_days INT DEFAULT 90,
            notes VARCHAR(255) DEFAULT '', active INT DEFAULT 1, created_at VARCHAR(30) DEFAULT '')",
    ];
    foreach ($t as $sql) $pdo->exec($sql);
}

// Idempotent migration: new tables + columns added over time.
function ops_migrate() {
    ops_ensure_schema();
    form_tokens_migrate();
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
    ensure_column('calls', 'inspection_type_other', "VARCHAR(150) DEFAULT ''");
    ensure_column('calls', 'site_address_id', 'INT NULL');
    // §i — the reporting rhythm and the reports the client wants are agreed on
    // the call, not invented at allocation. Both flow onto the job.
    ensure_column('calls', 'reporting_frequency', "VARCHAR(20) DEFAULT ''");
    ensure_column('calls', 'report_custom_days', 'INT NULL');
    ensure_column('calls', 'deliverables', "VARCHAR(255) DEFAULT ''");
    ensure_column('calls', 'po_id', 'INT NULL');
    ensure_column('calls', 'po_line_item_id', 'INT NULL');
    // Contracting-vs-executing credit model
    ensure_column('calls', 'contracting_office_id', 'INT NULL');       // office that owns the client/PO
    ensure_column('calls', 'billable_value', 'DECIMAL(14,2) DEFAULT 0'); // ex-GST value when same office
    // §13 — the value billable is not typed, it is worked out: the rate the order
    // was priced at, times the quantity actually being asked for. Both are kept,
    // because "6,000" tells you nothing later about whether that was one day at
    // 6,000 or three days at 2,000.
    ensure_column('calls', 'billable_rate', 'DECIMAL(14,2) DEFAULT 0');
    ensure_column('calls', 'billable_qty', 'DECIMAL(10,2) DEFAULT 0');
    ensure_column('calls', 'billable_basis', "VARCHAR(20) DEFAULT ''"); // MANDAY / MANMONTH / …
    ensure_column('calls', 'credit_required', 'DECIMAL(14,2) DEFAULT 0'); // executing office's counter value
    ensure_column('calls', 'credit_status', "VARCHAR(20) DEFAULT ''");  // PROPOSED / COUNTERED / AGREED
    // A call is raised against a quotation, so the commercial terms are inherited
    // rather than re-typed: contract number, types of inspection, product, value
    // and basis all come across (§a.i, §a.iv).
    ensure_column('calls', 'quotation_id', 'INT NULL');
    ensure_column('calls', 'quote_line_id', 'INT NULL');
    ensure_column('calls', 'contract_number', "VARCHAR(80) DEFAULT ''");
    // Where the client's papers and our working files live — a clickable link.
    ensure_column('calls', 'folder_link', "VARCHAR(500) DEFAULT ''");
    // Several visit dates on one call (§a.vi), and a repeating pattern for a call
    // that runs to an end date on set weekdays (§a.vii).
    ensure_column('calls', 'inspection_dates', "VARCHAR(400) DEFAULT ''");   // CSV of Y-m-d
    ensure_column('calls', 'schedule_end_date', "VARCHAR(20) DEFAULT ''");
    ensure_column('calls', 'schedule_weekdays', "VARCHAR(40) DEFAULT ''");   // CSV of 1..7 (Mon..Sun)
    // When the inspection engineer was actually put on it — the third leg of the
    // lead-time picture on the register (§a.ix).
    ensure_column('calls', 'allocated_at', "VARCHAR(30) DEFAULT ''");
    // Up to 20 visit dates once it reaches a deputation (§b.vi).
    ensure_column('jobs', 'inspection_dates', "VARCHAR(600) DEFAULT ''");
    ensure_column('jobs', 'folder_link', "VARCHAR(500) DEFAULT ''");
    ensure_column('jobs', 'contract_number', "VARCHAR(80) DEFAULT ''");
    // jobs gain type of inspection (carried from call), custom report frequency,
    // activity and the required deliverables/report formats.
    ensure_column('jobs', 'inspection_type', "VARCHAR(40) DEFAULT ''");
    ensure_column('jobs', 'activity_id', 'INT NULL');
    ensure_column('jobs', 'report_custom_days', 'INT NULL');
    ensure_column('jobs', 'deliverables', "VARCHAR(500) DEFAULT ''");
    // a client can carry the inspection types it typically needs (carried into calls)
    ensure_column('business_partners', 'inspection_types', "VARCHAR(600) DEFAULT ''");
    // contracting branch that registered the company (drives the code)
    ensure_column('business_partners', 'home_branch_id', 'INT NULL');
    // richer address (town/village + district) and contact (department + project)
    ensure_column('partner_addresses', 'town_village', "VARCHAR(150) DEFAULT ''");
    ensure_column('partner_addresses', 'district', "VARCHAR(150) DEFAULT ''");
    ensure_column('partner_contacts', 'project', "VARCHAR(200) DEFAULT ''");
    // contracts / purchase orders carry SBU for revenue attribution
    ensure_column('partner_contracts', 'sbu', "VARCHAR(20) DEFAULT ''");
    ensure_column('partner_purchase_orders', 'sbu', "VARCHAR(20) DEFAULT ''");
    // PO line items: manpower / site / trade→subcategory + GST/Tax/Total
    ensure_column('po_line_items', 'trade_id', 'INT NULL');
    ensure_column('po_line_items', 'skill_id', 'INT NULL');
    ensure_column('po_line_items', 'site', "VARCHAR(200) DEFAULT ''");
    ensure_column('po_line_items', 'manpower', 'INT DEFAULT 0');
    ensure_column('po_line_items', 'activity_id', 'INT NULL');
    ensure_column('po_line_items', 'gst_pct', 'DECIMAL(6,2) DEFAULT 0');
    ensure_column('po_line_items', 'base_amount', 'DECIMAL(14,2) DEFAULT 0');
    ensure_column('po_line_items', 'tax_amount', 'DECIMAL(14,2) DEFAULT 0');
    ensure_column('po_line_items', 'total_amount', 'DECIMAL(14,2) DEFAULT 0');
    ensure_column('po_line_items', 'last_alert', "VARCHAR(20) DEFAULT ''");
    // inspector master overhaul: names, trade, multi-SBU, multi-skill
    ensure_column('inspectors', 'first_name', "VARCHAR(80) DEFAULT ''");
    ensure_column('inspectors', 'middle_name', "VARCHAR(80) DEFAULT ''");
    ensure_column('inspectors', 'last_name', "VARCHAR(80) DEFAULT ''");
    ensure_column('inspectors', 'trade_id', 'INT NULL');
    ensure_column('inspectors', 'sbus', "VARCHAR(200) DEFAULT ''");
    ensure_column('inspectors', 'skill_ids', "VARCHAR(600) DEFAULT ''");
    ensure_column('inspectors', 'designation', "VARCHAR(40) DEFAULT ''");
    ensure_column('inspectors', 'staff_kind', "VARCHAR(20) DEFAULT 'ASSET'"); // asset / freelancer / subcon
    // extra annual cost paid to an external agency when this engineer is hired via one
    ensure_column('inspectors', 'agency_name', "VARCHAR(150) DEFAULT ''");
    ensure_column('inspectors', 'agency_cost', 'DECIMAL(14,2) DEFAULT 0');
    // Agency linkage + roll: which agency supplied them, on whose roll, one-time fee
    ensure_column('inspectors', 'agency_id', 'INT NULL');
    ensure_column('inspectors', 'roll_type', "VARCHAR(20) DEFAULT 'OWN'"); // OWN or AGENCY
    // Older installs stored the agency's own name as the roll code — normalise it.
    try { db()->exec("UPDATE inspectors SET roll_type='OWN' WHERE roll_type NOT IN ('OWN','AGENCY')"); } catch (Throwable $e) {}
    ensure_column('inspectors', 'placement_fee', 'DECIMAL(14,2) DEFAULT 0'); // one-time recruitment fee
    ensure_column('inspectors', 'fee_status', "VARCHAR(20) DEFAULT ''");     // PROVISIONAL | CONFIRMED | WAIVED
    ensure_column('inspectors', 'guarantee_upto', "VARCHAR(20) DEFAULT ''"); // fee is provisional until this date
    ensure_column('agencies', 'guarantee_days', 'INT DEFAULT 90');           // free-replacement window
    ensure_column('candidates', 'requisition_id', 'INT NULL');               // hire is against an approved requisition
    // CV analysis (keyword extraction for search) + client-submission / interview tracking (§20)
    ensure_column('candidates', 'cv_text', 'MEDIUMTEXT');
    ensure_column('candidates', 'cv_keywords', 'TEXT');
    ensure_column('candidates', 'cv_file_name', "VARCHAR(200) DEFAULT ''");
    ensure_column('candidates', 'cv_analyzed_at', "VARCHAR(30) DEFAULT ''");
    ensure_column('candidates', 'submitted_client_date', "VARCHAR(20) DEFAULT ''");
    ensure_column('candidates', 'client_feedback', "VARCHAR(20) DEFAULT ''");        // SHORTLISTED / REJECTED / PENDING
    ensure_column('candidates', 'client_feedback_date', "VARCHAR(20) DEFAULT ''");
    ensure_column('candidates', 'client_feedback_note', "VARCHAR(400) DEFAULT ''");
    ensure_column('candidates', 'interview_required', 'INT DEFAULT 0');
    ensure_column('candidates', 'interview_date', "VARCHAR(20) DEFAULT ''");         // planned
    ensure_column('candidates', 'interview_done_date', "VARCHAR(20) DEFAULT ''");
    ensure_column('candidates', 'interview_outcome', "VARCHAR(20) DEFAULT ''");      // SELECTED / REJECTED / HOLD
    ensure_column('candidates', 'credential_requested', 'INT DEFAULT 0');
    // job type (inspection vs project deputation) + lifecycle stage
    ensure_column('jobs', 'job_type', "VARCHAR(20) DEFAULT 'INSPECTION'");
    ensure_column('jobs', 'stage', "VARCHAR(20) DEFAULT 'ALLOCATED'");
    // Invoicing & payment / inter-office credit per job
    ensure_column('jobs', 'invoice_raised', 'INT DEFAULT 0');
    ensure_column('jobs', 'invoice_number', "VARCHAR(60) DEFAULT ''");
    ensure_column('jobs', 'invoice_date', "VARCHAR(20) DEFAULT ''");
    ensure_column('jobs', 'invoice_due_date', "VARCHAR(20) DEFAULT ''");
    ensure_column('jobs', 'invoice_amount', 'DECIMAL(14,2) DEFAULT 0');
    ensure_column('jobs', 'payment_received', 'INT DEFAULT 0');
    ensure_column('jobs', 'payment_date', "VARCHAR(20) DEFAULT ''");
    ensure_column('jobs', 'payment_amount', 'DECIMAL(14,2) DEFAULT 0');
    ensure_column('jobs', 'credit_received', 'INT DEFAULT 0'); // inter-office: credit received flag
    // CRM link: a job may be booked against an accepted quotation. Advance/report
    // conditions inherit from the quote so the inspector sees a HOLD when unpaid.
    ensure_column('jobs', 'quotation_id', 'INT NULL');
    ensure_column('jobs', 'adv_required', 'INT DEFAULT 0');
    ensure_column('jobs', 'adv_pct', 'DECIMAL(6,2) DEFAULT 0');
    ensure_column('jobs', 'adv_received', 'INT DEFAULT 0');
    ensure_column('jobs', 'report_hold', 'INT DEFAULT 0');   // deliverable held until payment
    // Inspection report approval — routes to the inspector's reporting manager (N+1).
    ensure_column('jobs', 'report_approval', "VARCHAR(20) DEFAULT ''");   // ''=n/a, PENDING, APPROVED, REJECTED
    ensure_column('jobs', 'report_approved_by', "VARCHAR(150) DEFAULT ''");
    ensure_column('jobs', 'report_approved_at', "VARCHAR(30) DEFAULT ''");
    ensure_column('jobs', 'report_approval_note', "VARCHAR(400) DEFAULT ''");
    ensure_column('jobs', 'last_escalation', "VARCHAR(20) DEFAULT ''");   // report-overdue escalation to manager
    // extra (configurable) expense headings beyond the fixed 5, stored as JSON {code:amount}
    ensure_column('expenses', 'extra', 'TEXT');
    // voucher supporting-file mime (voucher table itself is created in ensure_schema)
    ensure_column('vouchers', 'supporting_mime', "VARCHAR(100) DEFAULT ''");
    // per-office finance params (overhead % + contingency %) for accurate profitability
    ensure_column('offices', 'overhead_pct', 'DECIMAL(6,2) NULL');
    ensure_column('offices', 'contingency_pct', 'DECIMAL(6,2) NULL');
    // BOSS / contract carry-forward chain (renewal / ARC → new number, old kept visible)
    ensure_column('boss_numbers', 'supersedes', 'INT NULL');       // this row continues an older BOSS
    ensure_column('boss_numbers', 'superseded_by', 'INT NULL');    // this row was renewed into a newer BOSS
    ensure_column('boss_numbers', 'carried_at', "VARCHAR(30) DEFAULT ''");
    // certifications per inspector, with validity + reminder tracking
    db()->exec("CREATE TABLE IF NOT EXISTS inspector_certs (
        id " . pk_clause() . ", inspector_id INT, name VARCHAR(200), number VARCHAR(80) DEFAULT '',
        issued_date VARCHAR(20) DEFAULT '', valid_to VARCHAR(20) DEFAULT '', status VARCHAR(20) DEFAULT 'VALID',
        last_reminder VARCHAR(20) DEFAULT '', updated_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // ---- Workforce pack: office posting, weekly working days, reporting manager ----
    ensure_column('inspectors', 'home_office_id', 'INT NULL');                     // which branch this engineer is posted to
    ensure_column('inspectors', 'weekly_working_days', "DECIMAL(3,1) DEFAULT 6");  // 5 | 5.5 | 6 (Sat pattern)
    ensure_column('inspectors', 'reports_to_id', 'INT NULL');                      // reporting manager (a system user)
    // Reporting manager on users: reports_to_id already added in access.php; add the
    // manual name/position/email so a manager who has no login can still be recorded.
    ensure_column('users', 'reports_to_name', "VARCHAR(150) DEFAULT ''");
    ensure_column('users', 'reports_to_position', "VARCHAR(120) DEFAULT ''");
    ensure_column('users', 'reports_to_email', "VARCHAR(200) DEFAULT ''");
    ensure_column('users', 'position_title', "VARCHAR(120) DEFAULT ''");           // free-text designation for the hierarchy
    ensure_column('users', 'weekly_working_days', "DECIMAL(3,1) DEFAULT 6");
    // Per-day availability override for an inspector (leave / training / office …).
    // "On job" is auto-derived from jobs; a row here is a manual status for that date.
    db()->exec("CREATE TABLE IF NOT EXISTS inspector_day_status (
        id " . pk_clause() . ", inspector_id INT, day VARCHAR(20), status VARCHAR(20) DEFAULT 'AVAILABLE',
        note VARCHAR(255) DEFAULT '', set_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // Standard working norm (weekly days + hours) by designation and office. office_id
    // NULL = all offices; designation '' = office-wide default. Most-specific wins.
    db()->exec("CREATE TABLE IF NOT EXISTS work_norms (
        id " . pk_clause() . ", designation VARCHAR(40) DEFAULT '', office_id INT NULL,
        weekly_days DECIMAL(3,1) DEFAULT 6, weekly_hours DECIMAL(5,1) DEFAULT 48,
        updated_by VARCHAR(150) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    ops_seed_expense_masters();
}
// Seed the expense-head and travel-mode masters once (idempotent — count-guarded,
// so it runs on a fresh install and on an upgrade of an existing database).
function ops_seed_expense_masters() {
    $pdo = db();
    // Ensure each standard head exists BY CODE (not just when the table is empty),
    // so heads added in a later release — e.g. "Food bills (actual)" — appear on the
    // next boot of an already-populated database without disturbing custom heads.
    $have = [];
    foreach ($pdo->query("SELECT code FROM expense_heads")->fetchAll(PDO::FETCH_COLUMN) as $c) $have[strtoupper($c)] = 1;
    $ins = $pdo->prepare("INSERT INTO expense_heads (code,label,head_type,needs_receipt,sort_order,active) VALUES (?,?,?,?,?,1)");
    foreach (EXPENSE_HEADS_SEED as $h) { if (!isset($have[strtoupper($h[0])])) $ins->execute($h); }
    if ((int)$pdo->query("SELECT COUNT(*) FROM travel_modes")->fetchColumn() === 0) {
        $ins = $pdo->prepare("INSERT INTO travel_modes (code,label,basis,default_rate,active) VALUES (?,?,?,?,1)");
        foreach (TRAVEL_MODES_SEED as $m) $ins->execute($m);
    }
}

// Seed offices (Ahmedabad + affiliate IBOs) once.
function ops_seed() {
    $pdo = db();
    if ((int)$pdo->query("SELECT COUNT(*) FROM offices")->fetchColumn() > 0) return;
    $offices = [
        ['AHM','Ahmedabad','Ahmedabad',1],
        ['MUM','Mumbai','Mumbai',0], ['DEL','Delhi','New Delhi',0],
        ['CHE','Chennai','Chennai',0], ['KOL','Kolkata','Kolkata',0],
        ['BLR','Bengaluru','Bengaluru',0], ['HYD','Hyderabad','Hyderabad',0],
        ['PUN','Pune','Pune',0], ['BRD','Vadodara','Vadodara',0],
        ['VIZ','Visakhapatnam','Visakhapatnam',0], ['KOC','Kochi','Kochi',0],
        ['JAI','Jaipur','Jaipur',0], ['IND','Indore','Indore',0],
        ['NAG','Nagpur','Nagpur',0], ['BHU','Bhubaneswar','Bhubaneswar',0],
        ['LKO','Lucknow','Lucknow',0], ['CHD','Chandigarh','Chandigarh',0],
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
// NOTE: a partial fetch (fetch()/fetchColumn without draining all rows) leaves the
// SQLite cursor OPEN on the shared connection. A later multi-parameter INSERT can
// then bind only partially (silent, engine-specific). closeCursor() finalises the
// statement so every write that follows binds all its parameters correctly.
function ops_one($sql, $args = []) { $s = db()->prepare($sql); $s->execute($args); $r = $s->fetch(); $s->closeCursor(); return $r; }
function ops_val($sql, $args = []) { $s = db()->prepare($sql); $s->execute($args); $v = $s->fetchColumn(); $s->closeCursor(); return $v; }
function ops_next_code($table, $col, $prefix) {
    $last = ops_val("SELECT $col FROM $table WHERE $col LIKE ? ORDER BY $col DESC LIMIT 1", ["$prefix-%"]);
    $seq = $last ? ((int)substr($last, strrpos($last, '-') + 1)) + 1 : 1;
    return sprintf("%s-%05d", $prefix, $seq);
}
// ---------------------------------------------------------------------------
//  §b — is this party's master record fit to work against?
//
//  A client added in a hurry from a call has a name and nothing else: no address
//  to travel to, nobody to ring, no tax identity to invoice. The gap is not felt
//  on the day it is created — it is felt weeks later by the engineer standing at
//  a gate, or by the accountant who cannot raise the bill. So the details are
//  asked for while the coordinator still has the client on the phone.
//
//  Returns what is missing, in plain words. An empty array means the record is
//  complete. Sites and manufacturers need less than clients — nobody invoices a
//  site — so the list depends on the role the party is being used in.
// ---------------------------------------------------------------------------
function partner_missing($id, $role = 'client') {
    $id = (int)$id;
    if (!$id) return [];
    $p = ops_one("SELECT * FROM business_partners WHERE id=?", [$id]);
    if (!$p) return [];
    $miss = [];
    if ($role === 'client' && trim((string)($p['gstin'] ?? '')) === '' && trim((string)($p['pan'] ?? '')) === '')
        $miss[] = 'GSTIN or PAN';
    $addr = ops_one("SELECT * FROM partner_addresses WHERE partner_id=? ORDER BY is_primary DESC, id", [$id]);
    if (!$addr) $miss[] = 'an address';
    elseif (trim((string)$addr['line1']) === '' && trim((string)$addr['city']) === '') $miss[] = 'a usable address';
    $contacts = ops_all("SELECT * FROM partner_contacts WHERE partner_id=?", [$id]);
    if (!$contacts) $miss[] = 'a contact person';
    else {
        $hasPhone = false; $hasMail = false;
        foreach ($contacts as $c) {
            if (trim((string)($c['mobile'] ?: $c['phone'])) !== '') $hasPhone = true;
            if (trim((string)$c['email']) !== '') $hasMail = true;
        }
        if (!$hasPhone) $miss[] = 'a contact mobile number';
        // A site's paperwork travels by hand; only a client is e-mailed.
        if (!$hasMail && $role === 'client') $miss[] = 'a contact e-mail address';
    }
    return $miss;
}
// The same question, phrased for a sentence: "no address, no contact person".
function partner_missing_text($id, $role = 'client') {
    $m = partner_missing($id, $role);
    if (!$m) return '';
    if (count($m) === 1) return $m[0];
    $last = array_pop($m);
    return implode(', ', $m) . ' and ' . $last;
}

// ---------------------------------------------------------------------------
//  A call is raised against a quotation (§a.i, §a.ii, §a.iv)
//
//  Everything commercial was already agreed when the quote was won, so the call
//  inherits it instead of the coordinator re-typing it: contract number, the
//  types of inspection sold, product category, value and the basis it is
//  charged on. Re-typing is how a call ends up billed differently from what was
//  quoted.
// ---------------------------------------------------------------------------
function call_quotes_for_client($clientId) {
    $clientId = (int)$clientId;
    if (!$clientId) return [];
    return ops_all("SELECT id, quote_no, rev, contract_number, total_amount, status, subject, payment_terms
                    FROM quotations
                    WHERE client_id=? AND is_current=1 AND status IN ('ACCEPTED','APPROVED','SENT')
                    ORDER BY (status='ACCEPTED') DESC, id DESC", [$clientId]);
}
// The header + line items of a quote, shaped for the call form's pickers.
function call_quote_context($quoteId) {
    $quoteId = (int)$quoteId;
    if (!$quoteId) return null;
    $q = ops_one("SELECT * FROM quotations WHERE id=?", [$quoteId]);
    if (!$q) return null;
    $lines = ops_all("SELECT id, line_no, sbu, service_type, description, qty, unit, rate, amount, activity_id, office_id, location_id
                      FROM quote_lines WHERE quote_id=? ORDER BY line_no, id", [$quoteId]);
    return ['quote' => $q, 'lines' => $lines];
}

// ---------------------------------------------------------------------------
//  Visit dates (§a.vi, §a.vii, §b.vi)
//
//  Three ways a client asks for the work, all stored as one list of dates:
//    - a single day
//    - a handful of named days
//    - "every Monday and Thursday until the 30th" — expanded here, and still
//      fully editable afterwards, because site plans change.
// ---------------------------------------------------------------------------
function call_dates_parse($csv) {
    $out = [];
    foreach (explode(',', (string)$csv) as $d) {
        $d = trim($d);
        if ($d !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) $out[] = $d;
    }
    $out = array_values(array_unique($out));
    sort($out);
    return $out;
}
function call_dates_csv(array $dates) { return implode(',', call_dates_parse(implode(',', $dates))); }
// Expand "these weekdays, from this start, until this end" into actual dates.
// $weekdays are ISO numbers 1..7 (Monday..Sunday). Capped so a careless end date
// cannot generate thousands of rows.
function call_expand_pattern($startDate, $endDate, array $weekdays, $cap = 60) {
    $start = strtotime((string)$startDate); $end = strtotime((string)$endDate);
    if (!$start || !$end || $end < $start || !$weekdays) return [];
    $want = array_flip(array_map('intval', $weekdays));
    $out = []; $t = $start;
    while ($t <= $end && count($out) < $cap) {
        if (isset($want[(int)date('N', $t)])) $out[] = date('Y-m-d', $t);
        $t = strtotime('+1 day', $t);
    }
    return $out;
}
const WEEKDAY_NAMES = [1=>'Monday', 2=>'Tuesday', 3=>'Wednesday', 4=>'Thursday', 5=>'Friday', 6=>'Saturday', 7=>'Sunday'];
function weekdays_label($csv) {
    $out = [];
    foreach (explode(',', (string)$csv) as $d) { $d = (int)trim($d); if (isset(WEEKDAY_NAMES[$d])) $out[] = substr(WEEKDAY_NAMES[$d], 0, 3); }
    return implode(', ', $out);
}

// ---------------------------------------------------------------------------
//  Inter-office credit, stated in plain words (§a.viii)
//
//  Every office both contracts and executes, depending on whose client it is.
//  When the office that holds the contract is not the office doing the work,
//  the contracting office pays the executing office a credit in rupees. This
//  builds the sentence the screens show, so nobody has to work it out.
// ---------------------------------------------------------------------------
// ---------------------------------------------------------------------------
//  Where did the time go? (§a.ix)
//
//  Four moments matter on a call: the client asked, we forwarded it to the
//  office that will do it, we put an engineer on it, and the engineer is
//  scheduled. The gaps between them are where delay hides, so they are computed
//  once here and shown on the register and in its export.
// ---------------------------------------------------------------------------
function call_lead_times($c) {
    $d = function ($v) { $v = substr((string)$v, 0, 10); return ($v !== '' && strtotime($v)) ? strtotime($v) : null; };
    $recv  = $d($c['call_received_date'] ?? '');
    $fwd   = $d($c['forwarded_at'] ?? '');
    $alloc = $d($c['allocated_at'] ?? '');
    $sched = $d($c['sched_date'] ?? '');
    $need  = $d($c['inspection_required_date'] ?? '');
    $days = fn($a, $b) => ($a && $b) ? (int)round(($b - $a) / 86400) : null;
    $out = [
        'to_forward'  => $days($recv, $fwd),
        'to_allocate' => $days($fwd, $alloc),
        'to_schedule' => $days($recv, $sched),
        // Positive = the engineer is scheduled AFTER the date the client wanted.
        'delay'       => $days($need, $sched),
        'unallocated_days' => (!$alloc && $recv) ? (int)round((time() - $recv) / 86400) : null,
    ];
    $out['late'] = ($out['delay'] !== null && $out['delay'] > 0)
        || ($need && !$sched && $need < time());   // wanted by now, still nothing scheduled
    return $out;
}

function credit_explainer($contractingOfficeId, $executingOfficeId) {
    $c = $contractingOfficeId ? office((int)$contractingOfficeId) : null;
    $e = $executingOfficeId ? office((int)$executingOfficeId) : null;
    if (!$e || !$c || (int)$c['id'] === (int)$e['id']) {
        return ['cross' => false, 'text' => 'One office both holds the contract and does the work, so there is no inter-office credit — only the value billable to the client.'];
    }
    return ['cross' => true, 'from' => $c['name'], 'to' => $e['name'],
        'text' => $c['name'] . ' holds this contract and ' . $e['name'] . ' will do the work, so '
                . $c['name'] . ' gives ' . $e['name'] . ' a credit in ' . cur_sym()
                . '. Enter what ' . $e['name'] . ' is to receive; they can revert with the figure they need.'];
}

function clients_list() { return ops_all("SELECT id, legal_name, display_name FROM business_partners WHERE is_client=1 ORDER BY legal_name"); }
function vendors_list() { return ops_all("SELECT id, legal_name, display_name FROM business_partners WHERE is_vendor=1 ORDER BY legal_name"); }
function offices_list() { return ops_all("SELECT * FROM offices ORDER BY is_ahmedabad DESC, name"); }
function office($id) { return $id ? ops_one("SELECT * FROM offices WHERE id=?", [$id]) : null; }

// ---- Client/Vendor coding: BRANCH-YY-SHORTNAME-SEQ (e.g. AHM-26-KAVER-00042) ----
function branch_abbr($branchId) {
    $o = $branchId ? office($branchId) : null;
    if (!$o) $o = ops_one("SELECT * FROM offices WHERE is_ahmedabad=1 LIMIT 1");
    $code = $o ? ($o['code'] ?: substr($o['name'], 4, 3)) : 'AHM';
    return strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $code), 0, 4)) ?: 'AHM';
}
function partner_short_name($name) {
    $n = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', normalize_name($name)));
    return substr($n, 0, 5) ?: 'XXXXX';
}
function gen_partner_code($branchId, $name) {
    $bc = branch_abbr($branchId);
    $yy = substr((string)date('Y'), 2, 2);
    $prefix = "$bc-$yy-";
    $last = ops_val("SELECT code FROM business_partners WHERE code LIKE ? ORDER BY code DESC LIMIT 1", ["$prefix%"]);
    $seq = ($last && preg_match('/-(\d{5})$/', $last, $m)) ? (int)$m[1] + 1 : 1;
    return sprintf("%s-%s-%s-%05d", $bc, $yy, partner_short_name($name), $seq);
}
// "Other (add new)…" on a lookup dropdown → create the value and return its code.
function resolve_new_lookup($typeKey, $val, $newText) {
    if ($val !== '__new__') return $val;
    $newText = trim($newText);
    if ($newText === '') return '';
    $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $newText), 0, 20)) ?: strtoupper(substr(md5($newText), 0, 8));
    lk_ensure_value($typeKey, $code, $newText);
    return $code;
}
// Find an existing company by GSTIN / PAN / TAN / normalized name (avoids duplicates).
function find_duplicate_partner($name, $gstin, $pan, $tan, $excludeId = 0) {
    $g = strtoupper(clean_gstin($gstin)); $p = strtoupper(trim($pan)); $t = strtoupper(trim($tan)); $norm = normalize_name($name);
    foreach (ops_all("SELECT id, code, legal_name, gstin, pan, tan FROM business_partners WHERE id <> ?", [$excludeId]) as $r) {
        if ($g && $r['gstin'] && strtoupper($r['gstin']) === $g) return ['row' => $r, 'by' => 'GSTIN'];
        if ($p && $r['pan'] && strtoupper($r['pan']) === $p) return ['row' => $r, 'by' => 'PAN'];
        if ($t && ($r['tan'] ?? '') && strtoupper($r['tan']) === $t) return ['row' => $r, 'by' => 'TAN'];
        if ($norm !== '' && normalize_name($r['legal_name']) === $norm) return ['row' => $r, 'by' => 'name'];
    }
    return null;
}
function inspectors_list($activeOnly = true) { return ops_all("SELECT id, name, emp_code, sbu, salary_ctc, staff_kind, home_office_id FROM inspectors" . ($activeOnly ? " WHERE status='ACTIVE'" : "") . " ORDER BY name"); }
// Employee-code prefix per engagement kind. Sub-contractors and freelancers get a
// visibly DIFFERENT code series from our own staff so payroll/accounts can tell
// them apart at a glance: SC-#### for sub-cons, FL-#### for freelancers, EMP## for staff.
// The staff prefix is a setting (Settings → Working norms & limits); the two
// contractor series stay fixed so old codes never change meaning.
const EMP_CODE_PREFIX = ['ASSET' => 'EMP', 'SUBCON' => 'SC-', 'FREELANCER' => 'FL-'];
function emp_code_prefix($kind) {
    if ($kind === 'ASSET') { $p = setting_get('emp_code_prefix', ''); if ($p !== '' && $p !== null) return $p; }
    return EMP_CODE_PREFIX[$kind] ?? 'EMP';
}
// Next free auto code for a kind. Scans existing codes that share the prefix and
// bumps the highest trailing number. Regular staff pad to 2 digits (EMP07), the
// contractor series to 3 (SC-014) — either way the running number never collides.
function next_emp_code($kind) {
    $prefix = emp_code_prefix($kind);
    $pad = ($prefix === 'EMP') ? 2 : 3;
    $max = 0;
    foreach (ops_all("SELECT emp_code FROM inspectors WHERE emp_code LIKE ?", [$prefix . '%']) as $r) {
        if (preg_match('/(\d+)\s*$/', (string)$r['emp_code'], $m)) $max = max($max, (int)$m[1]);
    }
    return $prefix . str_pad((string)($max + 1), $pad, '0', STR_PAD_LEFT);
}
function subcons_list($activeOnly = true) { return ops_all("SELECT id, agency, inspector_name, skill FROM subcons" . ($activeOnly ? " WHERE active=1" : "") . " ORDER BY agency"); }
function agencies_list($activeOnly = true) { return ops_all("SELECT id, name, agency_type, one_time_fee, monthly_rate FROM agencies" . ($activeOnly ? " WHERE active=1" : "") . " ORDER BY name"); }
function agency_get($id) { return $id ? ops_one("SELECT * FROM agencies WHERE id=?", [(int)$id]) : null; }
// Agency contracts whose renewal is due within $days (default 30) — for reminders + a dashboard card.
// Days-left is computed in PHP so it works the same on MySQL and SQLite.
function agencies_renewing($days = 30) {
    $today = date('Y-m-d'); $limit = date('Y-m-d', strtotime("+$days days"));
    $rows = ops_all("SELECT id, name, agency_type, contract_number, contract_end
        FROM agencies WHERE active=1 AND contract_end<>'' AND contract_end<=? ORDER BY contract_end", [$limit]);
    foreach ($rows as &$r) $r['days_left'] = (int)round((strtotime($r['contract_end']) - strtotime($today)) / 86400);
    return $rows;
}
// Flip provisional placement fees to CONFIRMED once the guarantee window has
// passed for a still-active inspector. Safe to run any time (idempotent).
function confirm_lapsed_placement_fees() {
    $today = date('Y-m-d');
    db()->prepare("UPDATE inspectors SET fee_status='CONFIRMED'
        WHERE fee_status='PROVISIONAL' AND status='ACTIVE' AND guarantee_upto<>'' AND guarantee_upto < ?")
        ->execute([$today]);
}
// Placement-fee summary for the dashboard: provisional (₹ at risk / not yet due),
// confirmed (real cost), and how many guarantees lapse within `$soon` days.
function placement_fee_summary($soon = 30) {
    $today = date('Y-m-d'); $limit = date('Y-m-d', strtotime("+$soon days"));
    $prov = ops_one("SELECT COUNT(*) n, COALESCE(SUM(placement_fee),0) amt FROM inspectors WHERE fee_status='PROVISIONAL' AND placement_fee>0");
    $conf = ops_one("SELECT COUNT(*) n, COALESCE(SUM(placement_fee),0) amt FROM inspectors WHERE fee_status='CONFIRMED' AND placement_fee>0");
    $lapsing = (int)ops_val("SELECT COUNT(*) FROM inspectors WHERE fee_status='PROVISIONAL' AND placement_fee>0 AND guarantee_upto<>'' AND guarantee_upto<=?", [$limit]);
    return ['prov_n'=>(int)$prov['n'], 'prov_amt'=>(float)$prov['amt'], 'conf_n'=>(int)$conf['n'], 'conf_amt'=>(float)$conf['amt'], 'lapsing'=>$lapsing];
}
function boss_for_client($cid) { return $cid ? ops_all("SELECT id, boss_number, status FROM boss_numbers WHERE client_id=? ORDER BY boss_number", [$cid]) : []; }
function pname($p) { return $p ? ($p['display_name'] ?: $p['legal_name']) : '—'; }
// Currency symbol and date format are settings, not hard-coded (Settings → Display).
function cur_sym() { static $s = null; if ($s === null) $s = setting_get('currency_symbol', '') ?: '₹'; return $s; }
const DATE_FORMATS = ['d M Y'=>'25 Jul 2026', 'd/m/Y'=>'25/07/2026', 'd-m-Y'=>'25-07-2026', 'Y-m-d'=>'2026-07-25', 'M d, Y'=>'Jul 25, 2026'];
function date_fmt() { static $f = null; if ($f === null) { $f = setting_get('date_format', '') ?: 'd M Y'; if (!isset(DATE_FORMATS[$f])) $f = 'd M Y'; } return $f; }
// Format a stored Y-m-d (or any parseable) date for display.
function fdate($d, $fallback = '—') {
    if (!$d) return $fallback;
    $ts = strtotime($d); return $ts === false ? $d : date(date_fmt(), $ts);
}
function fmoney($v) { return $v === null || $v === '' ? '—' : cur_sym() . number_format((float)$v, 0); }
// Compact Indian money for KPI tiles: ₹1.84 L / ₹4.82 Cr / ₹6,200.
function fmoney_short($v) {
    $n = (float)$v; $s = $n < 0 ? '-' : ''; $n = abs($n); $c = cur_sym();
    if ($n >= 1e7)  return $s . $c . rtrim(rtrim(number_format($n / 1e7, 2, '.', ''), '0'), '.') . ' Cr';
    if ($n >= 1e5)  return $s . $c . rtrim(rtrim(number_format($n / 1e5, 2, '.', ''), '0'), '.') . ' L';
    return $s . $c . number_format($n, 0);
}
// ---- Working norms & limits (Settings, with the shipped defaults as fallback)
function hours_cap()           { $v = (float)setting_get('daily_hours_cap', 0); return $v > 0 ? $v : DAILY_HOURS_CAP; }
function hours_cap_disp()      { return rtrim(rtrim(number_format(hours_cap(), 2, '.', ''), '0'), '.'); }
function default_weekly_days() { $v = (float)setting_get('default_weekly_days', 0); return in_array($v, [5.0,5.5,6.0], true) ? $v : 6.0; }

// ---- CSV export (dependency-free; works on MilesWeb shared hosting) ---------
// $rows = array of rows (each an array of cells); stream as a downloadable file.
function csv_download($filename, array $rows) {
    if (headers_sent()) return;
    $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $safe . '"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows ₹ / accents correctly
    foreach ($rows as $r) fputcsv($out, $r);
    fclose($out);
    exit;
}
// True when the current request asked for a CSV download (?export=csv).
function wants_csv() { return ($_GET['export'] ?? '') === 'csv'; }

// ---- Accountant "money desk": counts + worklist of jobs needing action ------
// Uses the invoice / payment / inter-office-credit fields already on `jobs`.
// Scoped to the user's offices/SBUs so a branch accountant sees only their own.
function ops_invoicing_counts() {
    [$jw, $ja] = scope_clause('j.executing_office_id', 'j.sbu');
    $today = date('Y-m-d');
    $q = function($extra, $args = []) use ($jw, $ja) {
        return (int)ops_val("SELECT COUNT(*) FROM jobs j WHERE $jw AND $extra", array_merge($ja, $args));
    };
    return [
        'pending'    => $q("j.closed_flag=1 AND j.invoice_raised=0 AND j.credit_direction<>'GIVEN'"),
        'awaiting'   => $q("j.invoice_raised=1 AND j.payment_received=0"),
        'overdue'    => $q("j.invoice_raised=1 AND j.payment_received=0 AND j.invoice_due_date<>'' AND j.invoice_due_date<?", [$today]),
        'credit'     => $q("j.closed_flag=1 AND j.credit_direction<>'GIVEN' AND j.executing_office_id IS NOT NULL AND j.credit_received=0"),
        'unbilled'   => (float)ops_val("SELECT COALESCE(SUM(j.expected_credit),0) FROM jobs j WHERE $jw AND j.closed_flag=1 AND j.invoice_raised=0", $ja),
        'outstanding'=> (float)ops_val("SELECT COALESCE(SUM(j.invoice_amount),0) FROM jobs j WHERE $jw AND j.invoice_raised=1 AND j.payment_received=0", $ja),
    ];
}

// SQL fragment for a money-desk filter bucket.
function invoicing_filter_sql($f, $today) {
    switch ($f) {
        case 'awaiting': return ["j.invoice_raised=1 AND j.payment_received=0", []];
        case 'overdue':  return ["j.invoice_raised=1 AND j.payment_received=0 AND j.invoice_due_date<>'' AND j.invoice_due_date<?", [$today]];
        case 'credit':   return ["j.closed_flag=1 AND j.credit_direction<>'GIVEN' AND j.executing_office_id IS NOT NULL AND j.credit_received=0", []];
        case 'pending':  return ["j.closed_flag=1 AND j.invoice_raised=0 AND j.credit_direction<>'GIVEN'", []];
        default:         return ["j.closed_flag=1 AND (j.invoice_raised=0 OR j.payment_received=0)", []]; // 'all' open money items
    }
}

function ops_invoicing() {
    ops_require(can('data.credit') || can('finance.reconcile'), 'You cannot open Invoicing.');
    $f = $_GET['f'] ?? 'all';
    $today = date('Y-m-d');
    [$jw, $ja] = scope_clause('j.executing_office_id', 'j.sbu');
    [$fw, $fa] = invoicing_filter_sql($f, $today);
    $rows = ops_all(
        "SELECT j.id, j.job_code, j.invoice_raised, j.invoice_number, j.invoice_amount, j.invoice_date, j.invoice_due_date,
                j.payment_received, j.payment_amount, j.expected_credit, j.credit_direction, j.credit_received,
                bp.display_name, bp.legal_name, bn.boss_number, o.name office_name
         FROM jobs j
         LEFT JOIN calls c ON c.id = j.call_id
         LEFT JOIN business_partners bp ON bp.id = c.client_id
         LEFT JOIN boss_numbers bn ON bn.id = j.boss_id
         LEFT JOIN offices o ON o.id = j.executing_office_id
         WHERE $jw AND ($fw)
         ORDER BY (j.invoice_due_date <> '') DESC, j.invoice_due_date ASC, j.id DESC",
        array_merge($ja, $fa));
    if (wants_csv()) {
        $csv = [['Job','BOSS','Client','Office','Amount','Invoice raised','Invoice no','Invoice date','Due date','Payment received','Payment amount','Credit direction','Credit received']];
        foreach ($rows as $r) {
            $csv[] = [$r['job_code'], $r['boss_number'], $r['display_name'] ?: $r['legal_name'], $r['office_name'],
                (float)($r['invoice_amount'] ?: $r['expected_credit']), !empty($r['invoice_raised']) ? 'Yes' : 'No', $r['invoice_number'],
                $r['invoice_date'], $r['invoice_due_date'], !empty($r['payment_received']) ? 'Yes' : 'No', (float)$r['payment_amount'],
                $r['credit_direction'], !empty($r['credit_received']) ? 'Yes' : 'No'];
        }
        csv_download('invoicing-' . ($f ?: 'all') . '-' . date('Y-m-d') . '.csv', $csv);
    }
    view('ops/invoicing', ['counts' => ops_invoicing_counts(), 'rows' => $rows, 'f' => $f, 'today' => $today]);
}

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
// Overhead / contingency %: per-office if set, else a global default, else the
// hard-coded fallback. Lets each independent office tune its own profitability.
function global_overhead_pct() { $v = setting_get('overhead_pct', ''); return $v !== '' ? (float)$v : OVERHEAD_PCT; }
function global_contingency_pct() { $v = setting_get('contingency_pct', ''); return $v !== '' ? (float)$v : 0; }
function office_overhead_pct($officeId) {
    if ($officeId) { $v = ops_val("SELECT overhead_pct FROM offices WHERE id=?", [$officeId]); if ($v !== null && $v !== '') return (float)$v; }
    return global_overhead_pct();
}
function office_contingency_pct($officeId) {
    if ($officeId) { $v = ops_val("SELECT contingency_pct FROM offices WHERE id=?", [$officeId]); if ($v !== null && $v !== '') return (float)$v; }
    return global_contingency_pct();
}
// Loaded daily cost for an inspector (salary + overhead) / working days this month.
function inspector_daily_cost($salary_ctc, $year = null, $month = null, $officeId = null) {
    $year = $year ?: (int)date('Y'); $month = $month ?: (int)date('n');
    $oh = $officeId !== null ? office_overhead_pct($officeId) : global_overhead_pct();
    $loadedMonthly = ((float)$salary_ctc / 12) * (1 + $oh / 100);
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
    $base = (float)(ops_one("SELECT COALESCE(SUM(travel+local+food+lodging+misc),0) t FROM expenses WHERE job_id=?", [$jobId])['t'] ?? 0);
    $extra = 0;
    foreach (ops_all("SELECT extra FROM expenses WHERE job_id=? AND extra IS NOT NULL AND extra<>''", [$jobId]) as $r)
        foreach (expense_extra_decode($r['extra']) as $v) $extra += (float)$v;
    return $base + $extra;
}
// Codes handled by the fixed columns; everything else in the expense_heading list is an "extra".
const EXPENSE_BASE_CODES = ['TRAVEL','LOCAL','FOOD','LODGING','MISC'];
// Configurable headings beyond the fixed 5 (added by the user under expense_heading).
function expense_extra_headings() {
    $out = [];
    foreach (lk_options_or('expense_heading', EXPENSE_HEADINGS) as $code=>$label)
        if (!in_array($code, EXPENSE_BASE_CODES, true)) $out[$code] = $label;
    return $out;
}
function expense_extra_decode($json) {
    if (!$json) return [];
    $a = json_decode($json, true);
    return is_array($a) ? $a : [];
}
// Merge legacy base labels (renamable via expense_heading list) with extras.
function expense_heading_labels() {
    $lk = lk_options_or('expense_heading', EXPENSE_HEADINGS);
    $out = [];
    foreach (['TRAVEL'=>'travel','LOCAL'=>'local','FOOD'=>'food','LODGING'=>'lodging','MISC'=>'misc'] as $code=>$col)
        $out[$col] = $lk[$code] ?? EXPENSE_HEADINGS[$code];
    return $out; // col => label for the 5 base columns
}
// Full profitability breakdown for a job (Master Admin sees the salary part).
function job_profit($job) {
    $mandays = job_mandays($job);
    $office = $job['executing_office_id'] ?? null;
    $salary_ctc = $job['inspector_id'] ? (float)ops_val("SELECT salary_ctc + COALESCE(agency_cost,0) FROM inspectors WHERE id=?", [$job['inspector_id']]) : 0;
    $daily = $salary_ctc ? inspector_daily_cost($salary_ctc, null, null, $office) : 0;
    $labour = $daily * $mandays;
    $expenses = job_expenses_total($job['id']);
    $subcon = (float)($job['subcon_cost'] ?? 0);
    $credit = (float)($job['expected_credit'] ?? 0);
    $contingency = round(($labour + $expenses + $subcon) * office_contingency_pct($office) / 100, 2);
    return [
        'mandays' => $mandays, 'daily_cost' => $daily, 'labour' => $labour,
        'expenses' => $expenses, 'subcon' => $subcon, 'credit' => $credit, 'contingency' => $contingency,
        'profit' => $credit - $labour - $expenses - $subcon - $contingency,
    ];
}

// ---- Email (real send when configured, always logged) ----------------------
// SMTP settings (e.g. Office 365) come from Settings, or env overrides. Null = not configured.
function smtp_config() {
    $host = getenv('OPS_SMTP_HOST') ?: setting_get('smtp_host', '');
    if (!$host) return null;
    return [
        'host' => $host,
        'port' => (int)(getenv('OPS_SMTP_PORT') ?: (setting_get('smtp_port', '') ?: 587)),
        'user' => getenv('OPS_SMTP_USER') ?: setting_get('smtp_user', ''),
        'pass' => getenv('OPS_SMTP_PASS') ?: setting_get('smtp_pass', ''),
        'from' => getenv('OPS_SMTP_FROM') ?: (setting_get('smtp_from', '') ?: (setting_get('smtp_user', '') ?: 'no-reply@mghaiapps.com')),
    ];
}
// Minimal SMTP client (STARTTLS + AUTH LOGIN) — enough for Office 365 / Gmail relay.
// Throws on any protocol error; the caller logs it and the app keeps working.
function smtp_send($cfg, $to, $subject, $body, $cc = '', $attachments = []) {
    $port = (int)$cfg['port'];
    $fp = @fsockopen(($port === 465 ? 'ssl://' : '') . $cfg['host'], $port, $errno, $errstr, 15);
    if (!$fp) throw new Exception("connect failed: $errstr ($errno)");
    stream_set_timeout($fp, 15);
    $read = function() use ($fp) { $d = ''; while (($ln = fgets($fp, 515)) !== false) { $d .= $ln; if (isset($ln[3]) && $ln[3] === ' ') break; } return $d; };
    $say  = function($c) use ($fp, $read) { if ($c !== null) fwrite($fp, $c . "\r\n"); return $read(); };
    $need = function($resp, $code) { if (strncmp($resp, $code, 3) !== 0) throw new Exception('SMTP expected ' . $code . ', got ' . trim($resp)); };
    $ehlo = 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $need($read(), '220');
    $need($say($ehlo), '250');
    if ($port === 587) {
        $need($say('STARTTLS'), '220');
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new Exception('TLS negotiation failed');
        $need($say($ehlo), '250');
    }
    $need($say('AUTH LOGIN'), '334');
    $need($say(base64_encode($cfg['user'])), '334');
    $need($say(base64_encode($cfg['pass'])), '235');
    $need($say('MAIL FROM:<' . $cfg['from'] . '>'), '250');
    foreach (array_filter(array_map('trim', array_merge(explode(',', $to), explode(',', $cc)))) as $rcpt)
        $need($say('RCPT TO:<' . $rcpt . '>'), '250');
    $need($say('DATA'), '354');
    $hdrTop = 'From: ' . app_name() . ' <' . $cfg['from'] . ">\r\nTo: $to\r\n" . ($cc ? "Cc: $cc\r\n" : '') . 'Subject: ' . $subject . "\r\nMIME-Version: 1.0\r\n";
    if ($attachments) {
        $bnd = 'b_' . bin2hex(random_bytes(10));
        $headers = $hdrTop . "Content-Type: multipart/mixed; boundary=\"$bnd\"\r\n\r\n";
        $mime = "--$bnd\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n" . str_replace("\r\n", "\n", $body) . "\r\n";
        foreach ($attachments as $a) {
            $mime .= "--$bnd\r\nContent-Type: " . ($a['type'] ?? 'application/octet-stream') . "; name=\"" . $a['name'] . "\"\r\n"
                . "Content-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"" . $a['name'] . "\"\r\n\r\n"
                . chunk_split(base64_encode($a['data'])) . "\r\n";
        }
        $mime .= "--$bnd--";
        $data = $headers . preg_replace('/^\./m', '..', $mime) . "\r\n.";
    } else {
        $headers = $hdrTop . "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $data = $headers . preg_replace('/^\./m', '..', str_replace("\r\n", "\n", $body)) . "\r\n.";
    }
    $need($say($data), '250');
    $say('QUIT');
    fclose($fp);
    return true;
}
function ops_mail($to, $subject, $body, $cc = '', $kind = '', $attachments = []) {
    $ok = 0; $err = '';
    if ($to) {
        $smtp = smtp_config();
        if ($smtp) {
            try { smtp_send($smtp, $to, $subject, $body, $cc, $attachments); $ok = 1; }
            catch (Throwable $e) { $err = 'SMTP: ' . $e->getMessage(); }
        } elseif (getenv('OPS_MAIL_ENABLED')) {
            $from = getenv('OPS_MAIL_FROM') ?: 'no-reply@mghaiapps.com';
            $headers = 'From: ' . app_name() . " <$from>\r\n" . ($cc ? "Cc: $cc\r\n" : '') . "Content-Type: text/plain; charset=UTF-8\r\n";
            try { $ok = @mail($to, $subject, $body, $headers) ? 1 : 0; if (!$ok) $err = 'mail() returned false'; }
            catch (Throwable $e) { $err = $e->getMessage(); }
        } else {
            $err = 'mail disabled (configure Office 365 SMTP in Settings, or set OPS_MAIL_ENABLED=1)';
        }
    } else { $err = 'no recipient'; }
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
    // pull client & vendor primary contact + address for a complete brief
    $call = ops_one("SELECT * FROM calls WHERE id=?", [$j['call_id']]);
    $cc = $call ? partner_primary_contact($call['client_id']) : null;
    $vc = $call ? partner_primary_contact($call['vendor_id']) : null;
    $va = $call ? partner_primary_address($call['vendor_id']) : null;
    $notes = $call['notes'] ?? '';
    $product = $call ? ((lk_options_or('product', PRODUCT_CATS)[$call['product_category']] ?? '') ?: ($call['product_other'] ?? '')) : '';
    $b  = "Dear {$j['inspector_name']},\n\nYou have been assigned the following inspection. Full details below.\n\n";
    $b .= "JOB: {$j['job_code']}   (Call {$j['call_code']})\n";
    $b .= "Type: " . (INSPECTION_TYPES[$call['inspection_type'] ?? ''] ?? '') . "\n";
    $b .= "Scheduled: {$j['scheduled_date']}   Inspection: {$j['inspection_start_date']} to {$j['inspection_end_date']}\n";
    $b .= "Client required date: " . ($call['inspection_required_date'] ?? '') . "\n";
    $b .= "Reporting: " . (REPORT_FREQ[$j['reporting_frequency']] ?? '') . "   BOSS: {$j['boss_number']}\n\n";
    $b .= "-- CLIENT --\n{$client}\n";
    if ($cc) $b .= "Contact: {$cc['name']} " . ($cc['designation'] ? "({$cc['designation']})" : '') . "  M: " . ($cc['mobile'] ?: $cc['phone']) . "  E: {$cc['email']}\n";
    $b .= "\n-- VENDOR / SITE --\n{$j['vendor_name']}\n";
    if ($va) $b .= "Address: " . trim(($va['line1'] ?? '') . ' ' . ($va['town_village'] ?? '') . ' ' . ($va['district'] ?? '') . ' ' . ($va['city'] ?? '') . ' ' . ($va['state'] ?? '') . ' ' . ($va['pincode'] ?? '')) . "\n";
    if ($vc) $b .= "Contact: {$vc['name']} " . ($vc['designation'] ? "({$vc['designation']})" : '') . "  M: " . ($vc['mobile'] ?: $vc['phone']) . "  E: {$vc['email']}\n";
    if ($product) $b .= "\nProduct: {$product}\n";
    if ($notes) $b .= "\nNotes: {$notes}\n";
    $b .= "\nReport folder: {$j['folder_link']}\n\nRegards,\n" . app_name() . " Coordination";
    ops_mail($j['inspector_email'] ?? '', "Job Assignment: {$j['job_code']} — {$client}", $b, coordinator_emails(), 'assignment');
}
function partner_primary_contact($pid) {
    return $pid ? ops_one("SELECT * FROM partner_contacts WHERE partner_id=? ORDER BY is_primary DESC, id LIMIT 1", [$pid]) : null;
}
function partner_primary_address($pid) {
    return $pid ? ops_one("SELECT * FROM partner_addresses WHERE partner_id=? ORDER BY is_primary DESC, id LIMIT 1", [$pid]) : null;
}
function send_closure_email($jobId) {
    $j = job_email_context($jobId);
    if (!$j) return;
    $client = $j['client_disp'] ?: $j['client_name'];
    $exp = job_expenses_total($jobId);
    $body = "Inspection job closed.\n\nJob: {$j['job_code']}\nClient: {$client}\nInspector: {$j['inspector_name']}\n"
        . "Inspection end: {$j['inspection_end_date']}\nReport uploaded: {$j['report_upload_date']}\n"
        . "TAT: {$j['tat_days']} day(s)\nExpenses: " . fmoney($exp) . "\nReport folder: {$j['folder_link']}\n\n"
        . "Regards,\n" . app_name();
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
                . "Please upload the report / close the job.\n\n" . app_name();
            ops_mail($ctx['inspector_email'] ?? '', "Reminder: {$ctx['job_code']} ({$why})", $body, coordinator_emails(), 'reminder');
            db()->prepare("UPDATE jobs SET last_reminder=? WHERE id=?")->execute([$today, $j['id']]);
            $sent++;
        }
        // Escalation: a report/closure overdue by >= threshold days and still open is
        // escalated to the inspector's reporting manager. Throttled to once a week.
        $overdueDays = $pastEnd ? days_between($end, $today) : ($due && $since !== null ? $since : 0);
        $threshold = (int)(setting_get('report_escalate_days', '') ?: 3);
        if ($due && $overdueDays >= $threshold && function_exists('inspector_manager_email')) {
            $lastEsc = $j['last_escalation'] ?? '';
            $escOk = $lastEsc === '' || (days_between($lastEsc, $today) !== null && days_between($lastEsc, $today) >= 7);
            if ($escOk) {
                $mgrEmail = inspector_manager_email($j['inspector_id']);
                if ($mgrEmail) {
                    $ctx = $ctx ?? job_email_context($j['id']);
                    $client = $ctx['client_disp'] ?: $ctx['client_name'];
                    $eb = "ESCALATION — report overdue by {$overdueDays} day(s).\n\nJob: {$ctx['job_code']}\nClient: {$client}\n"
                        . "Inspector: {$ctx['inspector_name']}\nInspection ended: {$ctx['inspection_end_date']}\n{$why}.\n\n"
                        . "The report is still not uploaded. Please follow up with the inspector.\n\n" . app_name();
                    ops_mail($mgrEmail, "OVERDUE report: {$ctx['job_code']} — {$client} ({$overdueDays}d)", $eb, manager_emails(), 'escalation');
                    db()->prepare("UPDATE jobs SET last_escalation=? WHERE id=?")->execute([$today, $j['id']]);
                    $sent++;
                }
            }
        }
    }
    $sent += ops_run_cert_reminders($today);
    $sent += ops_run_po_alerts($today);
    return $sent;
}

// Alert managers when a PO line item's quantity is ~85%+ consumed (before validity).
function ops_run_po_alerts($today = null) {
    $today = $today ?: date('Y-m-d');
    $sent = 0;
    foreach (ops_all("SELECT l.*, po.po_number, po.end_date, po.partner_id FROM po_line_items l JOIN partner_purchase_orders po ON po.id=l.purchase_order_id WHERE l.quantity > 0") as $l) {
        $ratio = (float)$l['consumed'] / (float)$l['quantity'];
        if ($ratio < 0.85) continue;
        if ($l['last_alert'] === $today) continue;
        $bp = ops_one("SELECT legal_name, display_name FROM business_partners WHERE id=?", [$l['partner_id']]);
        $client = $bp ? ($bp['display_name'] ?: $bp['legal_name']) : '';
        $bal = (float)$l['quantity'] - (float)$l['consumed'];
        $body = "PO quantity nearing completion.\n\nClient: {$client}\nPO: {$l['po_number']}\nLine: {$l['description']}\n"
            . "Consumed: {$l['consumed']} of {$l['quantity']} (balance {$bal}).\nPO valid till: {$l['end_date']}.\n\nPlease arrange a fresh PO / extension.\n\n" . app_name();
        ops_mail(manager_emails(), "PO nearing completion: {$l['po_number']} — {$client}", $body, '', 'po_alert');
        db()->prepare("UPDATE po_line_items SET last_alert=? WHERE id=?")->execute([$today, $l['id']]);
        $sent++;
    }
    return $sent;
}
// Emails of managers + branch managers (for PO alerts).
function manager_emails() {
    $rows = ops_all("SELECT email FROM users WHERE role IN ('BRANCH_MANAGER','OPERATION_MANAGER','ADMIN','MASTER_ADMIN','SBU_HEAD') AND email <> '' AND is_active=1");
    return implode(',', array_filter(array_column($rows, 'email'))) ?: coordinator_emails();
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
            . "Valid to: {$c['valid_to']} — {$when}.\n\nPlease renew and submit the hard copy so the QA/QC nominee can update the date in the system.\n\n" . app_name();
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
            // Same columns the Organisation screen writes — the two editors are
            // views on one table, so an office never has two versions of itself.
            'fields' => [
                ['code','Code','text',['req'=>1]],
                ['name','Office name','text',['req'=>1]],
                ['office_type','Type','select',['opts'=>OFFICE_TYPES]],
                ['parent_office_id','Sits under','ref',['ref'=>'offices','optfn'=>'offices_list','optlabel'=>'name']],
                ['region','Region / zone','text',[]],
                ['city','City','text',[]],
                ['address','Address','text',[]],
                ['phone','Phone','text',[]],
                ['coordinator_name','Coordinator name','text',[]],
                ['coordinator_email','Coordinator email','text',[]],
                ['manager_name','Manager name','text',[]],
                ['manager_email','Manager email','text',[]],
                ['is_ahmedabad','This is the Ahmedabad (managing) office','check',[]],
            ],
            'list' => ['code'=>'Code','name'=>'Office','office_type'=>'Type','parent_office_id'=>'Sits under','city'=>'City','manager_name'=>'Manager'],
            'list_labels' => ['office_type'=>OFFICE_TYPES],
            'ref_cols' => ['parent_office_id'=>['offices','name']],
        ],
        'back-office' => [
            'label' => 'Back-office staff', 'table' => 'back_office_staff', 'access' => 'admin', 'order' => 'name',
            'fields' => [
                ['name','Name','text',['req'=>1]],
                ['emp_code','Employee code','text',[]],
                ['designation','Designation','select',['opts'=>DESIGNATIONS]],
                ['department','Department','select',['opts'=>DEPARTMENTS]],
                ['office_id','Office','ref',['ref'=>'offices','optfn'=>'offices_list','optlabel'=>'name']],
                ['email','Email','text',[]],
                ['mobile','Mobile','text',[]],
                ['ctc','Annual CTC (₹)','money',['salary'=>1]],
                ['allowances','Allowances (₹/yr)','money',['salary'=>1]],
                ['status','Status','select',['opts'=>['ACTIVE'=>'Active','INACTIVE'=>'Inactive']]],
            ],
            'list' => ['name'=>'Name','designation'=>'Designation','department'=>'Department','office_id'=>'Office','status'=>'Status'],
            'list_labels' => ['designation'=>DESIGNATIONS,'department'=>DEPARTMENTS],
            'ref_cols' => ['office_id'=>['offices','name']],
        ],
        'agencies' => [
            'label' => 'Recruitment / manpower agencies', 'table' => 'agencies', 'access' => 'admin', 'order' => 'name',
            'fields' => [
                ['name','Agency name','text',['req'=>1]],
                ['agency_type','Type','select',['opts'=>AGENCY_TYPES]],
                ['contact_person','Contact person','text',[]],
                ['email','Email','text',[]],
                ['mobile','Mobile','text',[]],
                ['gstin','GSTIN','text',[]],
                ['contract_number','Contract / agreement no.','text',[]],
                ['contract_start','Contract start','date',[]],
                ['contract_end','Contract end / renewal due','date',[]],
                ['one_time_fee','One-time placement fee (recruitment) ₹','money',[]],
                ['monthly_rate','Monthly charge (manpower) ₹','money',[]],
                ['guarantee_days','Free-replacement guarantee (days)','text',[]],
                ['notes','Notes','text',[]],
                ['active','Active','check',[]],
            ],
            'list' => ['name'=>'Agency','agency_type'=>'Type','contract_number'=>'Contract','contract_end'=>'Renewal due','active'=>'Active'],
            'list_labels' => ['agency_type'=>AGENCY_TYPES],
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
        'expense-heads' => [
            'label' => 'Expense heads (voucher columns)', 'table' => 'expense_heads', 'access' => 'admin', 'order' => 'sort_order, id',
            'fields' => [
                ['code','Code','text',['req'=>1]],
                ['label','Heading (column title)','text',['req'=>1]],
                ['head_type','Type','select',['opts'=>EXP_HEAD_TYPES]],
                ['default_rate','Default rate / amount (₹)','money',[]],
                ['needs_receipt','Needs a receipt / bill','check',[]],
                ['sort_order','Column order','number',[]],
                ['active','Active','check',[]],
            ],
            'list' => ['code'=>'Code','label'=>'Heading','head_type'=>'Type','default_rate'=>'Rate','needs_receipt'=>'Receipt?','sort_order'=>'Order'],
            'list_labels' => ['head_type'=>EXP_HEAD_TYPES],
            'money_cols' => ['default_rate'],
        ],
        'travel-modes' => [
            'label' => 'Travel modes & per-km rates', 'table' => 'travel_modes', 'access' => 'admin', 'order' => 'id',
            'fields' => [
                ['code','Code','text',['req'=>1]],
                ['label','Mode','text',['req'=>1]],
                ['basis','Basis','select',['opts'=>TRAVEL_BASIS]],
                ['default_rate','Default rate (₹/km)','money',[]],
                ['active','Active','check',[]],
            ],
            'list' => ['code'=>'Code','label'=>'Mode','basis'=>'Basis','default_rate'=>'₹/km'],
            'list_labels' => ['basis'=>TRAVEL_BASIS],
            'money_cols' => ['default_rate'],
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
// Per-module view-access gate. Maps a route to its module and blocks it if the
// user lacks mod.<module>.view. Inspector-owned paths (my-jobs, vouchers) and
// utility routes are intentionally NOT mapped, so they stay reachable.
function ops_module_gate($route) {
    $base = (strncmp($route, 'm/', 2) === 0) ? 'masters' : $route;
    static $map = [
        'calls'=>'calls','call'=>'calls','call-new'=>'calls','call-edit'=>'calls','call-delete'=>'calls',
        'jobs'=>'jobs','job'=>'jobs','job-new'=>'jobs','job-edit'=>'jobs','job-close'=>'jobs','job-invoice'=>'invoicing','job-advance'=>'jobs','report-approve'=>'jobs','expense-delete'=>'jobs',
        'invoicing'=>'invoicing',
        'profitability'=>'profitability','boss-renew'=>'profitability',
        'candidates'=>'hiring','candidate'=>'hiring','candidate-new'=>'hiring','candidate-edit'=>'hiring','candidate-stage'=>'hiring','candidate-cv'=>'hiring','candidate-client'=>'hiring','candidate-credential'=>'hiring',
        'requisitions'=>'hiring','requisition'=>'hiring','requisition-new'=>'hiring','requisition-edit'=>'hiring',
        'inquiries'=>'inquiries','inquiry-new'=>'inquiries','inquiry-edit'=>'inquiries',
        'quotes'=>'quotes','quote'=>'quotes','quote-new'=>'quotes','quote-edit'=>'quotes','quote-revise'=>'quotes','quote-status'=>'quotes','quote-doc'=>'quotes','quote-pdf'=>'quotes','quote-approve'=>'quotes','quote-approval-rules'=>'quotes','quote-contract'=>'quotes','quote-float'=>'quotes','client-quotes'=>'calls','quote-context'=>'calls','quote-client'=>'quotes','quote-files'=>'quotes','quote-file'=>'quotes','quote-file-delete'=>'quotes','quote-unlock'=>'quotes','quote-followup'=>'quotes','quote-external'=>'quotes','quotes-export'=>'quotes','quote-final'=>'quotes','quote-compose'=>'quotes','followup-compose'=>'quotes',
        'attendance-recon'=>'reconcile',
        'availability'=>'jobs',
        'documents'=>'idems','document'=>'idems','document-new'=>'idems','document-edit'=>'idems','document-submit'=>'idems','document-finalize'=>'idems','document-delete'=>'idems','document-fill'=>'idems',
        'report-types'=>'idems','report-type-edit'=>'idems','report-builder'=>'idems','report-field-edit'=>'idems','report-file'=>'idems','irn-rules'=>'idems','audit-log'=>'idems',
        'document-approve'=>'idems','approver-map'=>'idems','idems-approval-rules'=>'idems','idems-approval-rule-edit'=>'idems',
        'document-pdf'=>'idems','document-timestamp'=>'idems','document-docx'=>'idems',
        'report-templates'=>'idems','report-template-edit'=>'idems','report-template-download'=>'idems','report-form-from-template'=>'idems',
        'endorsements'=>'idems','endorsement'=>'idems','endorsement-new'=>'idems','endorsement-edit'=>'idems','endorsement-submit'=>'idems','endorsement-approve'=>'idems','endorsement-delete'=>'idems','endorsement-file'=>'idems','endorsement-cert'=>'idems',
        'phrase-library'=>'idems','phrase-edit'=>'idems','learning'=>'idems',
        'document-smart'=>'idems','document-release-note'=>'idems','document-review'=>'idems','document-evidence'=>'idems',
        'masters'=>'masters','work-norms'=>'masters',
        'office-finance'=>'overheads',
        'reports'=>'reports',
        'users'=>'users','user-new'=>'users','user-edit'=>'users','hierarchy'=>'users','org-template'=>'users',
        'contract-overrides'=>'calls','contract-override'=>'calls',
        'settings'=>'settings','access'=>'settings','ai-settings'=>'settings','terminology'=>'settings',
    ];
    $mod = $map[$base] ?? null;
    if ($mod && !can("mod.$mod.view")) {
        ops_require(false, 'You don’t have access to the ' . (ACCESS_MODULES[$mod] ?? $mod) . ' module. Ask your administrator.');
    }
}

// Settings → Roles & access: edit each role's default permission set.
function ops_access($method) {
    ops_require(is_master(), 'Only the Master Admin can edit role access.');
    $roles = ORG_ROLES; unset($roles['MASTER_ADMIN']); // Master Admin always has everything
    $sel = $_GET['role'] ?? 'COORDINATOR';
    if (!isset($roles[$sel])) $sel = array_key_first($roles);
    if ($method === 'POST') {
        $sel = $_POST['role'] ?? $sel;
        if (!isset($roles[$sel])) $sel = array_key_first($roles);
        $store = json_decode(setting_get('role_access', ''), true); if (!is_array($store)) $store = [];
        // One-click preset: apply the built-in recommended set for this role.
        if (($_POST['_do'] ?? '') === 'preset') {
            $store[$sel] = role_recommended_perms($sel);
            setting_set('role_access', json_encode($store));
            flash('Recommended permissions applied for ' . ORG_ROLES[$sel] . '. Review and Save, or adjust as needed.');
            redirect('/access?role=' . $sel);
        }
        // Restore to built-in default (drop the override).
        if (($_POST['_do'] ?? '') === 'reset') {
            unset($store[$sel]);
            setting_set('role_access', json_encode($store));
            flash('Access for ' . ORG_ROLES[$sel] . ' reset to the built-in default.');
            redirect('/access?role=' . $sel);
        }
        $valid = array_keys(all_permissions());
        $checked = array_values(array_intersect(array_keys($_POST['perms'] ?? []), $valid));
        // edit implies view for every module
        foreach ($checked as $p) if (preg_match('/^mod\.(\w+)\.edit$/', $p, $mm)) $checked[] = "mod.{$mm[1]}.view";
        $checked = array_values(array_unique($checked));
        $store[$sel] = $checked;
        setting_set('role_access', json_encode($store));
        flash('Access for ' . ORG_ROLES[$sel] . ' saved.');
        redirect('/access?role=' . $sel);
    }
    view('ops/access', ['roles' => $roles, 'sel' => $sel, 'current' => role_perms($sel),
        'recommended' => role_recommended_perms($sel), 'permGroups' => permission_groups(),
        'moduleGroups' => module_groups(), 'scope' => role_defaults_base($sel)]);
}

function ops_dispatch($route, $method) {
    ops_module_gate($route);
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
        case $route === 'calls' || $route === 'call-new' || $route === 'call-edit' || $route === 'call' || $route === 'call-delete' || $route === 'call-credit':
            ops_calls($route, $method); return true;
        case $route === 'jobs' || $route === 'job-new' || $route === 'job-edit' || $route === 'job' || $route === 'job-close' || $route === 'job-invoice' || $route === 'job-advance' || $route === 'expense-delete':
            ops_jobs($route, $method); return true;
        case $route === 'candidates' || $route === 'candidate-new' || $route === 'candidate-edit' || $route === 'candidate' || $route === 'candidate-stage' || $route === 'candidate-cv' || $route === 'candidate-client' || $route === 'candidate-credential':
            ops_candidates($route, $method); return true;
        case $route === 'inquiries' || $route === 'inquiry-new' || $route === 'inquiry-edit':
            ops_crm_inquiries($route, $method); return true;
        case $route === 'quotes' || $route === 'quote' || $route === 'quote-new' || $route === 'quote-edit' || $route === 'quote-revise' || $route === 'quote-status' || $route === 'quote-doc' || $route === 'quote-pdf' || $route === 'quote-approve' || $route === 'quote-contract' || $route === 'quote-float' || $route === 'quote-client' || $route === 'quote-files' || $route === 'quote-file' || $route === 'quote-file-delete' || $route === 'quote-unlock' || $route === 'quote-followup' || $route === 'quote-external' || $route === 'quotes-export' || $route === 'quote-final' || $route === 'quote-compose' || $route === 'followup-compose':
            ops_crm_quotes($route, $method); return true;
        case $route === 'crm-templates' || $route === 'crm-template-new' || $route === 'crm-template-edit' || $route === 'crm-template-delete' || $route === 'crm-template-download' || $route === 'crm-signature' || $route === 'crm-letterhead':
            ops_crm_templates($route, $method); return true;
        case $route === 'quote-approval-rules' || $route === 'quote-approval-rule-new' || $route === 'quote-approval-rule-edit' || $route === 'quote-approval-rule-delete':
            ops_crm_approval_rules($route, $method); return true;
        case $route === 'crm-reports':
            ops_crm_reports(); return true;
        case $route === 'requisitions' || $route === 'requisition-new' || $route === 'requisition-edit' || $route === 'requisition':
            ops_requisitions($route, $method); return true;
        case $route === 'vouchers' || $route === 'voucher' || $route === 'voucher-generate' || $route === 'voucher-entry' || $route === 'voucher-save' || $route === 'voucher-header' || $route === 'voucher-status' || $route === 'voucher-print' || $route === 'voucher-file' || $route === 'voucher-csv':
            ops_vouchers($route, $method); return true;
        case $route === 'my-jobs':
            ops_my_jobs(); return true;
        case $route === 'reports':
            ops_reports(); return true;
        case $route === 'profitability':
            ops_profitability(); return true;
        case $route === 'invoicing':
            ops_invoicing(); return true;
        case $route === 'seed-demo':
            ops_require(is_master(), 'Only the Master Admin can load demo data.');
            if ($method === 'POST') {
                $res = seed_demo();
                if (!empty($res['skipped'])) flash('Demo data is already loaded. To refresh it with the latest sample records (e.g. agencies, requisitions), click "Remove demo data" first, then "Load demo data" again.', 'warning');
                elseif (!empty($res['error'])) flash('Could not load demo data: ' . $res['error'], 'error');
                else { $x = $res['counts']; flash("Demo data loaded — {$x['offices']} offices, {$x['users']} users, {$x['inspectors']} inspectors, {$x['partners']} clients/vendors, {$x['boss']} BOSS, {$x['calls']} calls, {$x['jobs']} jobs, {$x['vouchers']} vouchers, plus " . ($x['edge_cases'] ?? 0) . " generated edge-case records. Log in as any demo user (e.g. director, account, insp.ravi) with password demo12345."); }
            }
            redirect('/settings'); return true;
        case $route === 'seed-demo-remove':
            ops_require(is_master(), 'Only the Master Admin can remove demo data.');
            if ($method === 'POST') {
                $res = seed_demo_remove();
                if (!empty($res['error'])) flash('Could not remove demo data: ' . $res['error'], 'error');
                else flash('Demo data removed — ' . (int)($res['deleted'] ?? 0) . ' records deleted. You can load it again anytime.');
            }
            redirect('/settings'); return true;
        case $route === 'boss-renew':
            ops_boss_renew(); return true;
        case $route === 'attendance-recon':
            ops_attendance_recon(); return true;
        case $route === 'users' || $route === 'user-new' || $route === 'user-edit':
            ops_users($route, $method); return true;
        case $route === 'change-password':
            ops_change_password($method); return true;
        case $route === 'settings':
            ops_settings($method); return true;
        case $route === 'access':
            ops_access($method); return true;
        case $route === 'ai-settings':
            ops_ai_settings($method); return true;
        case $route === 'terminology':
            ops_terminology($method); return true;
        // Merged screens — one heading, one tab per module underneath.
        case $route === 'approval-rules':
            return ops_approval_rules($method);
        case $route === 'templates':
            return ops_templates($method);
        case $route === 'availability':
            ops_inspector_availability($method); return true;
        case $route === 'hierarchy':
            return ops_hierarchy_screen($method);
        case $route === 'org-template':
            return ops_org_template();
        case $route === 'contract-overrides' || $route === 'contract-override':
            return ops_contract_overrides($route, $method);
        case $route === 'work-norms':
            ops_work_norms($method); return true;
        case in_array($route, ['documents','document','document-new','document-edit','document-submit','document-finalize','document-delete'], true):
            return ops_idems_documents($route, $method);
        case $route === 'report-types' || $route === 'report-type-edit':
            return ops_idems_report_types($route, $method);
        case $route === 'report-builder' || $route === 'report-field-edit':
            return ops_idems_builder($route, $method);
        case $route === 'document-fill':
            return ops_idems_fill($route, $method);
        case $route === 'document-approve':
            return ops_idems_approve($method);
        case $route === 'approver-map':
            return ops_idems_approver_map($method);
        case $route === 'idems-approval-rules' || $route === 'idems-approval-rule-edit':
            return ops_idems_approval_rules($route, $method);
        case $route === 'report-file':
            return ops_idems_file($method);
        case $route === 'document-pdf':
            return ops_idems_pdf($method);
        case $route === 'document-timestamp':
            return ops_idems_timestamp($method);
        case $route === 'my-signature':
            return ops_idems_my_signature($method);
        case $route === 'document-docx':
            return ops_idems_docx($method);
        case $route === 'report-templates' || $route === 'report-template-edit' || $route === 'report-template-download':
            return ops_idems_templates($route, $method);
        case $route === 'report-form-from-template':
            return ops_idems_form_from_template($method);
        case in_array($route, ['endorsements','endorsement','endorsement-new','endorsement-edit','endorsement-submit','endorsement-approve','endorsement-delete','endorsement-file'], true):
            return ops_idems_endorsements($route, $method);
        case $route === 'endorsement-cert':
            return ops_idems_endorse_cert($method);
        case $route === 'writing-assistant':
            return ops_idems_writing($method);
        case $route === 'phrase-library' || $route === 'phrase-edit':
            return ops_idems_phrases($route, $method);
        case $route === 'learning':
            return ops_idems_learning($method);
        case $route === 'document-smart':
            return ops_idems_smart($method);
        case $route === 'document-release-note':
            return ops_idems_release_note($method);
        case $route === 'document-review':
            return ops_idems_review($route, $method);
        case $route === 'document-evidence':
            return ops_idems_evidence($method);
        case $route === 'irn-rules':
            return ops_idems_numbering($method);
        case $route === 'audit-log':
            return ops_idems_audit($method);
        case $route === 'report-approve':
            ops_report_approve($method); return true;
        case $route === 'office-finance':
            ops_office_finance(); return true;
        case $route === 'masters':
            ops_require(is_coordinator_level());
            view('ops/masters', ['masters' => ops_masters()]); return true;
        case $route === 'lookups' || $route === 'lookup' || $route === 'custom-fields':
            lk_admin($route, $method); return true;
        case $route === 'quick-add':
            ops_quick_add(); return true;
        // §b — what is still missing from a party's master record, so the call
        // form can say so while the client is still on the phone.
        case $route === 'partner-gaps':
            header('Content-Type: application/json');
            $pid = (int)($_GET['id'] ?? 0);
            $role = ($_GET['role'] ?? 'client') === 'site' ? 'site' : 'client';
            $p = $pid ? ops_one("SELECT COALESCE(NULLIF(display_name,''), legal_name) nm FROM business_partners WHERE id=?", [$pid]) : null;
            echo json_encode([
                'name'    => $p ? (string)$p['nm'] : '',
                'missing' => partner_missing($pid, $role),
                'url'     => $pid ? '/partner-edit?id=' . $pid : '',
            ]);
            return true;
        case $route === 'partner-meta':
            header('Content-Type: application/json');
            $r = ops_one("SELECT inspection_types FROM business_partners WHERE id=?", [(int)($_GET['id'] ?? 0)]);
            echo json_encode(['inspection_types' => ($r && $r['inspection_types'] !== '') ? explode(',', $r['inspection_types']) : []]);
            return true;
        // §a.i — the quotations this client has that a call may be raised against.
        case $route === 'client-quotes':
            header('Content-Type: application/json');
            $out = [];
            foreach (call_quotes_for_client((int)($_GET['id'] ?? 0)) as $q) {
                $out[] = [
                    'id' => (int)$q['id'],
                    'label' => $q['quote_no'] . ((int)$q['rev'] > 0 ? ' R' . (int)$q['rev'] : '')
                             . ' · ' . fmoney($q['total_amount'])
                             . ' · ' . (lk_options_or('quote_status', QUOTE_STATUS)[$q['status']] ?? $q['status'])
                             . ($q['subject'] ? ' — ' . mb_substr($q['subject'], 0, 40) : ''),
                    'contract_number' => (string)$q['contract_number'],
                ];
            }
            echo json_encode($out); return true;
        // §a.ii, §a.iv — the quote's own terms and its line items, so the call
        // inherits what was sold instead of the coordinator re-typing it.
        case $route === 'quote-context':
            header('Content-Type: application/json');
            $ctx = call_quote_context((int)($_GET['id'] ?? 0));
            if (!$ctx) { echo json_encode(null); return true; }
            $q = $ctx['quote'];
            $lines = [];
            foreach ($ctx['lines'] as $l) {
                $lines[] = [
                    'id' => (int)$l['id'],
                    'label' => '#' . ((int)$l['line_no'] + 1) . ' · '
                        . (lk_options_or('inspection_type', INSPECTION_TYPES)[$l['service_type']] ?? $l['service_type'])
                        . ($l['description'] ? ' — ' . mb_substr($l['description'], 0, 40) : '')
                        . ' · ' . rtrim(rtrim(number_format((float)$l['qty'], 2), '0'), '.') . ' '
                        . (lk_options_or('charge_unit', CHARGE_UNITS)[$l['unit']] ?? $l['unit'])
                        . ' @ ' . fmoney($l['rate']),
                    'sbu' => (string)$l['sbu'], 'service_type' => (string)$l['service_type'],
                    'activity_id' => (int)($l['activity_id'] ?? 0), 'office_id' => (int)($l['office_id'] ?? 0),
                    'amount' => (float)$l['amount'], 'unit' => (string)$l['unit'],
                    // §13 / §g — the call prices off the line's RATE and its own
                    // quantity, not off the line total, which covers the whole order.
                    'rate' => (float)$l['rate'], 'qty' => (float)$l['qty'],
                ];
            }
            echo json_encode([
                'contract_number'  => (string)$q['contract_number'],
                'sbu'              => (string)$q['sbu'],
                'office_id'        => (int)($q['office_id'] ?? 0),
                'product_category' => (string)($q['product_category'] ?? ''),
                'inspection_types' => array_values(array_filter(explode(',', (string)($q['inspection_types'] ?? '')))),
                'total_amount'     => (float)$q['subtotal'],   // ex-GST: what is billable
                'payment_terms'    => (string)($q['payment_terms'] ?? ''),
                'lines'            => $lines,
            ]);
            return true;
        case $route === 'partner-sites':
            header('Content-Type: application/json');
            $out = [];
            foreach (ops_all("SELECT id, address_type, label, town_village, district, city, state FROM partner_addresses WHERE partner_id=? ORDER BY is_primary DESC, id", [(int)($_GET['id'] ?? 0)]) as $a) {
                $lbl = trim(($a['label'] ?: (lk_options_or('address_type', ADDRESS_TYPES)[$a['address_type']] ?? $a['address_type'])) . ' — ' . implode(', ', array_filter([$a['town_village'], $a['district'], $a['city'], $a['state']])), ' —');
                $out[] = ['id' => (int)$a['id'], 'label' => $lbl];
            }
            echo json_encode($out); return true;
        case $route === 'partner-pos':
            header('Content-Type: application/json');
            $out = [];
            foreach (ops_all("SELECT id, po_number, po_type, value FROM partner_purchase_orders WHERE partner_id=? ORDER BY id DESC", [(int)($_GET['id'] ?? 0)]) as $o) {
                $hasLines = (int)ops_val("SELECT COUNT(*) FROM po_line_items WHERE purchase_order_id=?", [$o['id']]);
                $out[] = ['id' => (int)$o['id'], 'label' => ($o['po_number'] ?: 'Open order') . ' (' . (lk_options_or('po_type', PO_TYPES)[$o['po_type']] ?? $o['po_type']) . ')', 'lines' => $hasLines];
            }
            echo json_encode($out); return true;
        case $route === 'po-lines':
            header('Content-Type: application/json');
            $out = [];
            foreach (ops_all("SELECT id, description, quantity, consumed, item_type, rate FROM po_line_items WHERE purchase_order_id=? ORDER BY id", [(int)($_GET['id'] ?? 0)]) as $l) {
                $bal = (float)$l['quantity'] - (float)$l['consumed'];
                // §l — the rate and the unit come back too, so the call can price
                // itself off the order rather than off somebody's memory, and the
                // balance is there to be checked against what is being asked for.
                $out[] = [
                    'id'    => (int)$l['id'],
                    'label' => $l['description'] . ' — bal ' . $bal . ' ' . (lk_options_or('charge_unit', PO_ITEM_TYPES)[$l['item_type']] ?? ''),
                    'rate'  => $l['rate'] === null ? null : (float)$l['rate'],
                    'unit'  => (string)$l['item_type'],
                    'balance' => $bal,
                ];
            }
            echo json_encode($out); return true;
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
            $pan = $gstin ? pan_from_gstin($gstin) : strtoupper(trim($b['pan'] ?? ''));
            $state = $gstin ? state_from_gstin($gstin) : trim($b['state'] ?? '');
            // §b — the record must be usable the moment it exists: an address to
            // travel to, somebody to ring, and a tax identity to invoice against.
            $line1 = trim($b['line1'] ?? ''); $city = trim($b['qcity'] ?? '');
            $pName = trim($b['pname'] ?? ''); $pMob = trim($b['pmob'] ?? ''); $pMail = trim($b['pmail'] ?? '');
            $need = [];
            if ($isClient && $gstin === '' && $pan === '') $need[] = 'a GSTIN or a PAN';
            if ($line1 === '' && $city === '') $need[] = 'an address';
            if ($pName === '') $need[] = 'a contact person';
            if ($pMob === '') $need[] = 'a mobile number';
            if ($isClient && $pMail === '') $need[] = 'an e-mail address';
            if ($need) {
                $last = array_pop($need);
                echo json_encode(['ok' => false, 'error' => 'Enter '
                    . ($need ? implode(', ', $need) . ' and ' . $last : $last)
                    . '. These are needed before the work can be sent out or billed.']);
                return;
            }
            $token = short_token($name);
            $last = ops_val("SELECT code FROM business_partners WHERE code LIKE ? ORDER BY code DESC LIMIT 1", ["GEN-$token-%"]);
            $seq = $last ? ((int)substr($last, strrpos($last, '-') + 1)) + 1 : 1;
            $code = sprintf("GEN-%s-%04d", $token, $seq);
            $pdo->prepare("INSERT INTO business_partners (code,legal_name,is_client,is_vendor,status,gstin,pan,state,created_at) VALUES (?,?,?,?, 'ACTIVE', ?,?,?,?)")
                ->execute([$code, $name, $isClient, $isVendor, $gstin, $pan, $state, date('c')]);
            $pid = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO partner_addresses (partner_id,address_type,label,line1,city,state,is_primary) VALUES (?,?,?,?,?,?,1)")
                ->execute([$pid, $isVendor && !$isClient ? 'FACTORY' : 'REGISTERED', $isVendor && !$isClient ? 'Works' : 'Registered office', $line1, $city, $state]);
            $pdo->prepare("INSERT INTO partner_contacts (partner_id,name,mobile,email,is_primary) VALUES (?,?,?,?,1)")
                ->execute([$pid, $pName, $pMob, $pMail]);
            echo json_encode(['ok' => true, 'id' => $pid, 'label' => $name, 'roles' => ['client' => $isClient, 'vendor' => $isVendor]]);
            return;
        }
        if ($kind === 'office') {
            $code = strtoupper(trim($b['code'] ?? '')) ?: strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3));
            $pdo->prepare("INSERT INTO offices (code,name,city,coordinator_name,coordinator_email,manager_name,manager_email,is_ahmedabad) VALUES (?,?,?,?,?,?,?,0)")
                ->execute([$code, $name, $b['city'] ?? '', $b['coordinator_name'] ?? '', $b['coordinator_email'] ?? '', $b['manager_name'] ?? '', $b['manager_email'] ?? '']);
            echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId(), 'label' => $name]);
            return;
        }
        if ($kind === 'agency') {
            // agency for candidates (sub-con / HR). Stored on subcons so it's reusable.
            $exists = ops_val("SELECT agency FROM subcons WHERE agency=? LIMIT 1", [$name]);
            if (!$exists) $pdo->prepare("INSERT INTO subcons (agency,active,created_at) VALUES (?,1,?)")->execute([$name, date('c')]);
            echo json_encode(['ok' => true, 'id' => $name, 'label' => $name]);
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
            custom_save($key, $id, $_POST);
            flash("{$cfg['label']}: saved.");
        } else {
            $ph = implode(',', array_fill(0, count($cols), '?'));
            if (in_array('created_at', array_column($cfg['fields'], 0)) === false && column_exists($table, 'created_at')) {
                $cols[] = 'created_at'; $vals[] = date('c'); $ph .= ',?';
            }
            $pdo->prepare("INSERT INTO $table (" . implode(',', $cols) . ") VALUES ($ph)")->execute($vals);
            custom_save($key, $pdo->lastInsertId(), $_POST);
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
            // Digital signature (drawn or uploaded) — added automatically to this inspector's reports.
            if (($b['_do'] ?? '') === 'signature' && $ins) {
                $sig = $b['signature'] ?? '';
                if (!empty($_FILES['sigfile']['tmp_name']) && is_uploaded_file($_FILES['sigfile']['tmp_name'])) {
                    $bytes = file_get_contents($_FILES['sigfile']['tmp_name']);
                    if ($bytes !== false && strlen($bytes) < 2*1024*1024) $sig = 'data:' . ($_FILES['sigfile']['type'] ?: 'image/png') . ';base64,' . base64_encode($bytes);
                }
                if ($sig && strpos($sig, 'data:image') === 0) { $pdo->prepare("UPDATE inspectors SET signature=? WHERE id=?")->execute([$sig, $ins['id']]); flash('Signature saved.'); }
                elseif (($b['_clear'] ?? '') === '1') { $pdo->prepare("UPDATE inspectors SET signature='' WHERE id=?")->execute([$ins['id']]); flash('Signature cleared.'); }
                else flash('No signature captured.', 'error');
                redirect('/m/inspectors/edit?id=' . $ins['id']);
            }
            // Allowances & rates grid (Super Admin only)
            if (($b['_do'] ?? '') === 'allow_save' && $ins) {
                ops_require(is_master(), 'Only the Super Admin can set allowances.');
                save_inspector_allowances($ins['id'], $b);
                flash('Allowances & rates updated.');
                redirect('/m/inspectors/edit?id=' . $ins['id']);
            }
            // main save
            $full = trim(trim(($b['first_name'] ?? '') . ' ' . ($b['middle_name'] ?? '')) . ' ' . ($b['last_name'] ?? ''));
            $sbus = implode(',', array_filter((array)($b['sbus'] ?? [])));
            $skills = implode(',', array_filter((array)($b['skill_ids'] ?? [])));
            $trade = ($b['trade_id'] ?? '') !== '' ? (int)$b['trade_id'] : null;
            $salary = can_see_salary() ? (($b['salary_ctc'] ?? '') !== '' ? $b['salary_ctc'] : 0) : null;
            $agencyName = $b['agency_name'] ?? '';
            $agencyCost = can_see_salary() ? (($b['agency_cost'] ?? '') !== '' ? (float)$b['agency_cost'] : 0) : null;
            $desig = $b['designation'] ?? ''; $kind = in_array($b['staff_kind'] ?? '', ['ASSET','FREELANCER','SUBCON'], true) ? $b['staff_kind'] : 'ASSET';
            $empCode = trim($b['emp_code'] ?? '');
            if ($empCode === '' && !$ins) $empCode = next_emp_code($kind); // auto: SC-/FL- for contractors, EMP for staff
            // Workforce fields: posted office, weekly working days (5/5.5/6), reporting manager.
            $homeOff  = ($b['home_office_id'] ?? '') !== '' ? (int)$b['home_office_id'] : null;
            $wwd      = in_array((string)($b['weekly_working_days'] ?? ''), ['5','5.5','6'], true) ? (float)$b['weekly_working_days'] : 6;
            $reportTo = ($b['reports_to_id'] ?? '') !== '' ? (int)$b['reports_to_id'] : null;
            if ($ins) {
                $sql = "UPDATE inspectors SET first_name=?,middle_name=?,last_name=?,name=?,emp_code=?,designation=?,staff_kind=?,trade_id=?,sbus=?,sbu=?,skill_ids=?,email=?,mobile=?,agency_name=?,home_office_id=?,weekly_working_days=?,reports_to_id=?,status=?";
                $args = [$b['first_name'] ?? '', $b['middle_name'] ?? '', $b['last_name'] ?? '', $full, $empCode, $desig, $kind, $trade, $sbus, explode(',', $sbus)[0] ?? '', $skills, $b['email'] ?? '', $b['mobile'] ?? '', $agencyName, $homeOff, $wwd, $reportTo, $b['status'] ?? 'ACTIVE'];
                if ($salary !== null) { $sql .= ",salary_ctc=?"; $args[] = $salary; }
                if ($agencyCost !== null) { $sql .= ",agency_cost=?"; $args[] = $agencyCost; }
                $sql .= " WHERE id=?"; $args[] = $ins['id'];
                $pdo->prepare($sql)->execute($args);
                flash('Inspector saved.');
                redirect('/m/inspectors/edit?id=' . $ins['id']);
            } else {
                $pdo->prepare("INSERT INTO inspectors (first_name,middle_name,last_name,name,emp_code,designation,staff_kind,trade_id,sbus,sbu,skill_ids,email,mobile,agency_name,home_office_id,weekly_working_days,reports_to_id,agency_cost,salary_ctc,status,created_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$b['first_name'] ?? '', $b['middle_name'] ?? '', $b['last_name'] ?? '', $full, $empCode, $desig, $kind, $trade, $sbus, explode(',', $sbus)[0] ?? '', $skills, $b['email'] ?? '', $b['mobile'] ?? '', $agencyName, $homeOff, $wwd, $reportTo, $agencyCost ?: 0, $salary ?: 0, $b['status'] ?? 'ACTIVE', date('c')]);
                $id = $pdo->lastInsertId();
                flash('Inspector added. You can now add certifications.');
                redirect('/m/inspectors/edit?id=' . $id);
            }
        }
        $certs = $ins ? ops_all("SELECT * FROM inspector_certs WHERE inspector_id=? ORDER BY valid_to", [$ins['id']]) : [];
        view('ops/inspector_form', ['ins' => $ins, 'certs' => $certs, 'skillsByTrade' => skills_by_trade(),
            'offices' => ops_all("SELECT id, name FROM offices ORDER BY is_ahmedabad DESC, name"),
            'managers' => ops_all("SELECT id, first_name, last_name, username, role FROM users WHERE is_active=1 ORDER BY first_name, last_name"),
            'expHeads' => ops_all("SELECT * FROM expense_heads WHERE active=1 ORDER BY sort_order, id"),
            'travelModes' => ops_all("SELECT * FROM travel_modes WHERE active=1 ORDER BY id"),
            'allowMap' => $ins ? inspector_allow_map($ins['id']) : ['HEAD'=>[], 'MODE'=>[]]]);
        return;
    }
    // list
    $q = trim($_GET['q'] ?? '');
    $where = "1=1"; $args = [];
    if ($q) { $where = "(name LIKE ? OR emp_code LIKE ? OR skills LIKE ?)"; $args = ["%$q%", "%$q%", "%$q%"]; }
    $rows = ops_all("SELECT * FROM inspectors WHERE $where ORDER BY name", $args);
    view('ops/inspector_list', ['rows' => $rows, 'q' => $q]);
}
// ---- Inspector entitlements (P2) — Super-Admin only ------------------------
// Effective map of what an inspector may claim: ['HEAD'=>[code=>['allowed','rate']], 'MODE'=>[...]]
function inspector_allow_map($insId) {
    $out = ['HEAD' => [], 'MODE' => []];
    if (!$insId) return $out;
    foreach (ops_all("SELECT kind, code, allowed, rate_override FROM inspector_allowances WHERE inspector_id=?", [$insId]) as $r) {
        $out[$r['kind']][$r['code']] = ['allowed' => (int)$r['allowed'], 'rate' => $r['rate_override']];
    }
    return $out;
}
// Effective per-km rate for a mode: the inspector's override, else the mode default.
function inspector_mode_rate($insId, $modeCode) {
    $ov = ops_val("SELECT rate_override FROM inspector_allowances WHERE inspector_id=? AND kind='MODE' AND code=? AND rate_override IS NOT NULL", [$insId, $modeCode]);
    if ($ov !== null && $ov !== false && $ov !== '') return (float)$ov;
    return (float)(ops_val("SELECT default_rate FROM travel_modes WHERE code=?", [$modeCode]) ?: 0);
}
function inspector_head_allowed($insId, $code) {
    return (int)ops_val("SELECT allowed FROM inspector_allowances WHERE inspector_id=? AND kind='HEAD' AND code=?", [$insId, $code]) === 1;
}
function inspector_mode_allowed($insId, $code) {
    return (int)ops_val("SELECT allowed FROM inspector_allowances WHERE inspector_id=? AND kind='MODE' AND code=?", [$insId, $code]) === 1;
}
// Save the entitlement grid (Super Admin only). Stores a row when allowed OR a
// rate override is given; clears the inspector's set first so unticks are honoured.
function save_inspector_allowances($insId, $b) {
    if (!is_master() || !$insId) return;
    $pdo = db();
    $pdo->prepare("DELETE FROM inspector_allowances WHERE inspector_id=?")->execute([$insId]);
    $ins = $pdo->prepare("INSERT INTO inspector_allowances (inspector_id,kind,code,allowed,rate_override) VALUES (?,?,?,?,?)");
    foreach (ops_all("SELECT code FROM expense_heads") as $h) {
        $code = $h['code'];
        $allowed = !empty($b['allow_head'][$code]) ? 1 : 0;
        $rate = (($b['head_rate'][$code] ?? '') !== '') ? (float)$b['head_rate'][$code] : null;
        if ($allowed || $rate !== null) $ins->execute([$insId, 'HEAD', $code, $allowed, $rate]);
    }
    foreach (ops_all("SELECT code FROM travel_modes") as $m) {
        $code = $m['code'];
        $allowed = !empty($b['allow_mode'][$code]) ? 1 : 0;
        $rate = (($b['mode_rate'][$code] ?? '') !== '') ? (float)$b['mode_rate'][$code] : null;
        if ($allowed || $rate !== null) $ins->execute([$insId, 'MODE', $code, $allowed, $rate]);
    }
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
    if ($route === 'call-delete') {
        ops_require(is_master() || can('ops.call.delete'), 'Only the Super Admin or a Branch Application Manager can delete calls.');
        if ($method === 'POST') {
            $cid = (int)($_GET['id'] ?? 0);
            $pdo->prepare("DELETE FROM jobs WHERE call_id=?")->execute([$cid]);
            $pdo->prepare("DELETE FROM calls WHERE id=?")->execute([$cid]);
            flash('Call deleted.');
        }
        redirect('/calls');
    }
    if ($route === 'calls') {
        $q = trim($_GET['q'] ?? '');
        $minCost = ($_GET['mincost'] ?? '') !== '' ? (float)$_GET['mincost'] : null;
        // Visible to BOTH the contracting and the executing office (peer offices).
        $off = scope_offices(); $sbu = scope_sbus();
        $conds = []; $args = [];
        if ($off !== 'ALL' && is_array($off) && $off) {
            $ahm = (int)(ops_val("SELECT id FROM offices WHERE is_ahmedabad=1 LIMIT 1") ?: 0);
            $ids = implode(',', array_map('intval', $off)); // inline ints (COALESCE drops bind affinity)
            // Visible to BOTH the managing/contracting office (IBO) and the executing office.
            $conds[] = "(COALESCE(c.executing_office_id,$ahm) IN ($ids) OR c.ibo_office_id IN ($ids))";
        }
        if ($sbu !== 'ALL' && is_array($sbu) && $sbu) {
            $ph = implode(',', array_fill(0, count($sbu), '?'));
            $conds[] = "c.sbu IN ($ph)"; foreach ($sbu as $s) $args[] = $s;
        }
        $where = $conds ? implode(' AND ', $conds) : '1=1';
        if ($q) { $where .= " AND (c.call_code LIKE ? OR bp.legal_name LIKE ? OR v.legal_name LIKE ?)"; array_push($args, "%$q%","%$q%","%$q%"); }
        $costExpr = "((SELECT COALESCE(SUM(ve.row_total),0) FROM voucher_entries ve JOIN jobs jv ON jv.id=ve.job_id WHERE jv.call_id=c.id)"
                  . " + (SELECT COALESCE(SUM(ex.travel+ex.local+ex.food+ex.lodging+ex.misc),0) FROM expenses ex JOIN jobs je ON je.id=ex.job_id WHERE je.call_id=c.id))";
        // §a.ix — the register has to answer "where is this call stuck?" without
        // opening it: which office is executing, what activity, what credit is
        // owed, whose desk it is on, who is doing it, when, and how late.
        $rows = ops_all("SELECT c.*, bp.legal_name client_name, bp.display_name client_disp, v.legal_name vendor_name,
            eo.name exec_office_name, eo.coordinator_name exec_coordinator,
            io2.name ibo_office_name,
            (SELECT COUNT(*) FROM jobs j WHERE j.call_id=c.id) job_count, $costExpr AS cost_incurred,
            (SELECT MIN(j2.scheduled_date) FROM jobs j2 WHERE j2.call_id=c.id AND j2.scheduled_date<>'') sched_date,
            (SELECT i.name FROM jobs j3 LEFT JOIN inspectors i ON i.id=j3.inspector_id
               WHERE j3.call_id=c.id AND j3.inspector_id IS NOT NULL ORDER BY j3.id LIMIT 1) inspector_name
            FROM calls c LEFT JOIN business_partners bp ON bp.id=c.client_id
            LEFT JOIN business_partners v ON v.id=c.vendor_id
            LEFT JOIN offices eo ON eo.id=c.executing_office_id
            LEFT JOIN offices io2 ON io2.id=c.ibo_office_id
            WHERE $where ORDER BY c.id DESC", $args);
        foreach ($rows as &$r) $r['lead'] = call_lead_times($r);
        unset($r);
        if ($minCost !== null) $rows = array_values(array_filter($rows, fn($r) => (float)$r['cost_incurred'] >= $minCost));
        if (wants_csv()) {
            $csv = [[TH('call'), T('client'), T('vendor') . ' / site', T('sbu'), 'Activity',
                'Contracting ' . T('office'), 'Executing ' . T('office'), 'Credit to give', 'Coordinator',
                T('engineer'), 'Received', 'Forwarded', 'Allocated', 'Required by', 'Scheduled',
                'Received to forwarded (days)', 'Forwarded to allocated (days)', 'Received to scheduled (days)',
                'Delay vs required (days)', THP('job') . ' count', 'Cost incurred', 'Status']];
            foreach ($rows as $c) {
                $st = ($c['status'] ?? '') === 'CLOSED' ? 'Closed' : ((int)$c['job_count'] === 0 ? 'To schedule' : 'In progress');
                $L = $c['lead'];
                $csv[] = [$c['call_code'], $c['client_disp'] ?: $c['client_name'], $c['vendor_name'],
                    lk_options_or('sbu', OPS_SBUS)[$c['sbu']] ?? $c['sbu'],
                    $c['activity_id'] ? lk_value_path($c['activity_id']) : '',
                    $c['ibo_office_name'] ?? '', $c['exec_office_name'] ?? '',
                    (float)($c['expected_credit'] ?? 0), $c['exec_coordinator'] ?? '', $c['inspector_name'] ?? '',
                    fdate($c['call_received_date'], ''), fdate(substr((string)($c['forwarded_at'] ?? ''), 0, 10), ''),
                    fdate(substr((string)($c['allocated_at'] ?? ''), 0, 10), ''),
                    fdate($c['inspection_required_date'], ''), fdate($c['sched_date'] ?? '', ''),
                    $L['to_forward'] ?? '', $L['to_allocate'] ?? '', $L['to_schedule'] ?? '', $L['delay'] ?? '',
                    (int)$c['job_count'], (float)($c['cost_incurred'] ?? 0), $st];
            }
            csv_download('calls-' . date('Y-m-d') . '.csv', $csv);
        }
        view('ops/calls', ['rows' => $rows, 'q' => $q, 'minCost' => $_GET['mincost'] ?? '']); return;
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
            // Default the managing / contracting office (IBO) to the creator's home office if blank.
            if (($b['ibo_office_id'] ?? '') === '') {
                $b['ibo_office_id'] = ($call['ibo_office_id'] ?? '') ?: (current_user()['home_office_id'] ?? '');
            }
            $mngOffice = ($b['ibo_office_id'] ?? '') !== '' ? (int)$b['ibo_office_id'] : null;
            $crossOffice = $execOffice && (!$mngOffice || $mngOffice !== $execOffice);
            // §a.viii — when the contracting office is not the executing office, the
            // contracting office owes the executing office a credit in rupees. Say so
            // in those words rather than reporting a bare validation failure.
            if ($crossOffice && (($b['expected_credit'] ?? '') === '' || (float)$b['expected_credit'] <= 0)) {
                $ex = credit_explainer($mngOffice, $execOffice);
                // array_merge, not "+": the union operator keeps the left-hand
                // value, which would silently drop the message.
                view('ops/call_form', array_merge(call_form_vars($call, $b),
                    ['error' => $ex['text'] . ' Enter that amount before saving.']));
                return;
            }
            // §a.vi / §a.vii — one date, several dates, or a weekly pattern to an end
            // date. All three end up as the same editable list of dates.
            $dates = call_dates_parse(implode(',', (array)($b['inspection_dates'] ?? [])));
            $wd = array_values(array_filter(array_map('intval', (array)($b['schedule_weekdays'] ?? []))));
            if ($wd && ($b['schedule_end_date'] ?? '') !== '') {
                $from = ($b['inspection_required_date'] ?? '') ?: ($dates[0] ?? date('Y-m-d'));
                $dates = call_dates_parse(implode(',', array_merge($dates, call_expand_pattern($from, $b['schedule_end_date'], $wd))));
            }
            $b['inspection_dates']  = implode(',', $dates);
            $b['schedule_weekdays'] = implode(',', $wd);
            // §13 — rate x quantity, computed here and not read from the form, so a
            // stale or hand-edited total can never reach the register. The quantity
            // defaults to the number of visit dates: one for a single day, the
            // count for several, and the expanded count for a repeating pattern.
            $rate = (float)($b['billable_rate'] ?? 0);
            $qty  = (float)($b['billable_qty'] ?? 0);
            // A repeating pattern is expanded into real dates HERE, so the browser
            // could not have known the count when it posted. Unless somebody typed
            // a quantity, it is the number of visit dates after expansion.
            $qtyAuto = ($b['billable_qty_auto'] ?? '1') !== '0';
            if ($qty <= 0 || $qtyAuto) $qty = max(1, count($dates));
            $b['billable_qty'] = $qty;
            if ($rate > 0) $b['billable_value'] = round($rate * $qty, 2);
            // The client's expected date is the first visit, so the two never disagree.
            if ($dates && ($b['inspection_required_date'] ?? '') === '') $b['inspection_required_date'] = $dates[0];
            $fields = ['client_id','vendor_id','ibo_office_id','executing_office_id','region','sbu','activity_id',
                'inspection_type','inspection_type_other','site_address_id','po_id','po_line_item_id',
                'product_category','product_other','deputation_type','expected_credit','credit_type',
                'billable_value','billable_basis','billable_rate','billable_qty','call_received_date','inspection_required_date','notes',
                'quotation_id','quote_line_id','contract_number','folder_link',
                'inspection_dates','schedule_end_date','schedule_weekdays',
                'reporting_frequency','report_custom_days','deliverables'];
            $wasForwarded = $call ? ($call['executing_office_id'] ?? null) : null;
            $forwardNow = $execOffice && !$wasForwarded; // first time it gets an executing branch
            // §b — forwarding is the moment the work leaves this desk: somebody
            // will travel to the site, and somebody will be invoiced for it. Neither
            // is possible against a master record that is only a name, so the gaps
            // are named and the call is handed back rather than sent on. Only the
            // first forward is gated — a call already out in the world stays
            // editable, or a typo would become impossible to correct.
            if ($forwardNow) {
                $gaps = [];
                $cm = partner_missing_text((int)($b['client_id'] ?? 0), 'client');
                if ($cm) $gaps[] = 'The ' . Tl('client') . ' record is missing ' . $cm . '.';
                $vm = partner_missing_text((int)($b['vendor_id'] ?? 0), 'site');
                if ($vm) $gaps[] = 'The site record is missing ' . $vm . '.';
                if ($gaps) {
                    view('ops/call_form', array_merge(call_form_vars($call, $b), ['error' =>
                        implode(' ', $gaps) . ' Complete the master under '
                        . T('client') . 's & ' . T('vendor') . 's before forwarding this '
                        . Tl('call') . ' to an executing ' . Tl('office')
                        . ' — the ' . Tl('engineer') . ' needs somewhere to go and somebody to ask for,'
                        . ' and the invoice needs a tax identity. Leave the executing '
                        . Tl('office') . ' blank to save what you have so far.']));
                    return;
                }
            }
            $notifyMgr = !empty($b['notify_manager']) ? 1 : 0;
            // §i — the report types are a multi-select, so they arrive as an array
            // and are stored as a CSV of report-type codes: the same shape the job
            // uses, which is what lets them be handed over untouched at allocation.
            $b['deliverables'] = implode(',', array_filter((array)($b['deliverables'] ?? [])));
            // §11 — the inspection is at the client's own premises: the same
            // partner is both the customer and the site. Flag them as a site
            // partner so they appear in the site list from now on, instead of
            // making somebody go and edit the partner record first.
            $vid = (int)($b['vendor_id'] ?? 0);
            if ($vid && $vid === (int)($b['client_id'] ?? 0))
                $pdo->prepare("UPDATE business_partners SET is_vendor=1 WHERE id=? AND is_vendor=0")->execute([$vid]);
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
        view('ops/call_form', call_form_vars($call));
        return;
    }
    // Executing office reverts with the credit it requires for a cross-office call.
    if ($route === 'call-credit') {
        $call = ops_one("SELECT * FROM calls WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$call) { http_response_code(404); view('notfound'); return; }
        ops_require(can('mod.calls.edit') || is_coordinator_level(), 'You cannot set the required credit.');
        if ($method === 'POST') {
            $req = ($_POST['credit_required'] ?? '') === '' ? 0 : (float)$_POST['credit_required'];
            $st  = $_POST['credit_status'] ?? 'COUNTERED';
            $pdo->prepare("UPDATE calls SET credit_required=?, credit_status=? WHERE id=?")->execute([$req, $st, $call['id']]);
            flash($st === 'AGREED' ? 'Credit agreed.' : 'Required credit sent back to the contracting office.');
        }
        redirect('/call?id=' . $call['id']);
    }
    if ($route === 'call') {
        $call = ops_one("SELECT c.*, bp.legal_name client_name, bp.display_name client_disp, v.legal_name vendor_name,
            o.name ibo_name, x.name exec_name, x.coordinator_name, x.coordinator_email, x.manager_name, x.manager_email
            FROM calls c LEFT JOIN business_partners bp ON bp.id=c.client_id
            LEFT JOIN business_partners v ON v.id=c.vendor_id LEFT JOIN offices o ON o.id=c.ibo_office_id
            LEFT JOIN offices x ON x.id=c.executing_office_id WHERE c.id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$call) { http_response_code(404); view('notfound'); return; }
        $jobs = ops_all("SELECT j.*, i.name inspector_name, i.staff_kind, s.agency subcon_agency FROM jobs j LEFT JOIN inspectors i ON i.id=j.inspector_id LEFT JOIN subcons s ON s.id=j.subcon_id WHERE j.call_id=? ORDER BY j.id DESC", [$call['id']]);
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
        view('ops/call_detail', ['call' => $call, 'jobs' => $jobs, 'lead' => $lead,
            'sameOffice' => call_same_office($call), 'costIncurred' => call_cost_incurred($call['id'])]);
        return;
    }
}
function nz($v) { return $v === '' ? null : $v; }
// Normalise a city name and reuse an existing near-match spelling (light auto-correct).
function normalise_city($input) {
    $c = ucwords(strtolower(trim(preg_replace('/\s+/', ' ', $input))));
    if ($c === '') return '';
    $best = null; $bestD = 999;
    foreach (ops_all("SELECT DISTINCT city FROM partner_addresses WHERE city <> ''") as $r) {
        $existing = $r['city'];
        if (strcasecmp($existing, $c) === 0) return $existing;              // exact (case-insensitive)
        $d = levenshtein(strtolower($existing), strtolower($c));
        if ($d < $bestD) { $bestD = $d; $best = $existing; }
    }
    // reuse existing spelling only for a very close match (1-2 edits on a name of decent length)
    if ($best !== null && $bestD <= 2 && strlen($c) >= 5) return $best;
    return $c;
}
// Everything the call form needs, in one place — the error path and the normal
// path used to build this list separately and drifted apart.
// $posted: when a save is refused, re-render with what was typed rather than
// with what is in the database. Losing twenty fields because one was wrong is
// the kind of thing that makes people stop using a system.
function call_form_vars($call, $posted = null) {
    $u = current_user();
    if ($posted !== null) {
        $sticky = [];
        foreach ($posted as $k => $v) $sticky[$k] = is_array($v) ? implode(',', array_filter($v, 'strlen')) : $v;
        $call = array_merge($call ?: [], $sticky);
        // A call that does not exist yet must not acquire an id from the post.
        if (!isset($call['call_code'])) unset($call['id']);
    }
    return [
        'call' => $call,
        'isEdit' => !empty($call['call_code']),
        'clients' => clients_list(), 'vendors' => vendors_list(), 'offices' => offices_list(),
        'cfvals' => !empty($call['id']) ? custom_values_map('call', $call['id']) : [],
        // §a.i — quotes for the client already on the call, so an edit opens ready.
        'quotes' => $call ? call_quotes_for_client((int)($call['client_id'] ?? 0)) : [],
        'qlines' => ($call && !empty($call['quotation_id']))
            ? (call_quote_context((int)$call['quotation_id'])['lines'] ?? []) : [],
        // §a.iii — Region is a reporting roll-up for the SBU heads and the
        // Business Director. It is noise for everyone else, so only they see it.
        'showRegion' => in_array(user_role(), ['SBU_HEAD','BUSINESS_DIRECTOR','MASTER_ADMIN','ADMIN'], true) || is_master(),
        'error' => null,
    ];
}
function nzc_call($f, $v) {
    if (in_array($f, ['client_id','vendor_id','ibo_office_id','executing_office_id','contracting_office_id','activity_id','site_address_id','po_id','po_line_item_id','quotation_id','quote_line_id'], true)) return $v === '' ? null : (int)$v;
    if (in_array($f, ['expected_credit','billable_value','credit_required'], true)) return $v === '' ? 0 : $v;
    return $v;
}
// True when the managing / contracting office (IBO) also executes the call (or
// there is no separate executing branch) — then there is no inter-office credit,
// only a billable value to the client (ex-GST). The managing office = ibo_office_id.
function call_same_office($call) {
    $exe = (int)($call['executing_office_id'] ?? 0);
    if (!$exe) return true;                    // no executing branch → same office
    $mng = (int)($call['ibo_office_id'] ?? 0);  // managing / contracting office
    return $mng !== 0 && $mng === $exe;         // managing office executes it itself
}
// Total cost incurred on a call = voucher lines (travel + bills) + job-closure
// expenses across every job under the call. Shown to both offices.
function call_cost_incurred($callId) {
    $jobIds = array_column(ops_all("SELECT id FROM jobs WHERE call_id=?", [$callId]), 'id');
    if (!$jobIds) return 0.0;
    $ph = implode(',', array_fill(0, count($jobIds), '?'));
    $vouch = (float)ops_val("SELECT COALESCE(SUM(row_total),0) FROM voucher_entries WHERE job_id IN ($ph)", $jobIds);
    $exp   = (float)ops_val("SELECT COALESCE(SUM(travel+local+food+lodging+misc),0) FROM expenses WHERE job_id IN ($ph)", $jobIds);
    return $vouch + $exp;
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
        . "Please allocate an inspector.\n\nRegards,\n" . app_name() . " (Managing office)";
    ops_mail($to, "Call forwarded: {$c['call_code']} — {$client}", $body, $cc, 'forward');
}

// ---- Manpower requisition / position approval (mandatory before hiring) ----
function requisitions_list($openOnly = false) {
    return ops_all("SELECT id, req_code, designation, req_type, status FROM requisitions" . ($openOnly ? " WHERE status IN ('OPEN','PROPOSED','OFFERED')" : "") . " ORDER BY id DESC");
}
function ops_requisitions($route, $method) {
    $pdo = db();
    if ($route === 'requisitions') {
        [$scopeW, $args] = scope_clause('r.office_id', 'r.sbu');
        $q = trim($_GET['q'] ?? ''); $where = $scopeW;
        if ($q) { $where .= " AND (r.req_code LIKE ? OR r.designation LIKE ? OR r.project_site LIKE ?)"; array_push($args, "%$q%","%$q%","%$q%"); }
        $rows = ops_all("SELECT r.*, o.name office_name, oi.name outgoing_name, hi.name hired_name
            FROM requisitions r LEFT JOIN offices o ON o.id=r.office_id
            LEFT JOIN inspectors oi ON oi.id=r.outgoing_inspector_id
            LEFT JOIN inspectors hi ON hi.id=r.hired_inspector_id
            WHERE $where ORDER BY r.id DESC", $args);
        view('ops/requisition_list', ['rows' => $rows, 'q' => $q]); return;
    }
    if ($route === 'requisition-new' || $route === 'requisition-edit') {
        ops_require(is_coordinator_level(), 'Only coordinators / managers can raise requisitions.');
        $req = null;
        if ($route === 'requisition-edit') { $req = ops_one("SELECT * FROM requisitions WHERE id=?", [(int)($_GET['id'] ?? 0)]); if (!$req) { http_response_code(404); view('notfound'); return; } }
        if ($method === 'POST') {
            $b = $_POST;
            $fields = ['office_id','sbu','designation','project_site','req_type','outgoing_inspector_id','budgeted_cost','approved_by','approval_ref','approval_date','status','notes'];
            $norm = function($f, $v) { if (in_array($f, ['office_id','outgoing_inspector_id'], true)) return $v === '' ? null : (int)$v; if ($f === 'budgeted_cost') return $v === '' ? 0 : (float)$v; return $v; };
            if ($req) {
                $set = implode(',', array_map(fn($f)=>"$f=?", $fields)); $vals = array_map(fn($f)=>$norm($f, $b[$f] ?? ''), $fields); $vals[] = $req['id'];
                $pdo->prepare("UPDATE requisitions SET $set WHERE id=?")->execute($vals);
                flash("Requisition {$req['req_code']} updated."); redirect('/requisition?id=' . $req['id']);
            } else {
                $code = ops_next_code('requisitions', 'req_code', 'REQ');
                $cols = array_merge(['req_code'], $fields, ['created_by','created_at']);
                $vals = array_merge([$code], array_map(fn($f)=>$norm($f, $b[$f] ?? ''), $fields), [user_name(current_user()), date('c')]);
                $ph = implode(',', array_fill(0, count($cols), '?'));
                $pdo->prepare("INSERT INTO requisitions (" . implode(',', $cols) . ") VALUES ($ph)")->execute($vals);
                $id = $pdo->lastInsertId(); flash("$code created — now add candidates against it."); redirect('/requisition?id=' . $id);
            }
        }
        view('ops/requisition_form', ['req' => $req, 'offices' => offices_list(), 'inspectors' => inspectors_list(false)]); return;
    }
    if ($route === 'requisition') {
        $req = ops_one("SELECT r.*, o.name office_name FROM requisitions r LEFT JOIN offices o ON o.id=r.office_id WHERE r.id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$req) { http_response_code(404); view('notfound'); return; }
        $outgoing = $req['outgoing_inspector_id'] ? ops_one("SELECT id,name,salary_ctc,agency_cost FROM inspectors WHERE id=?", [$req['outgoing_inspector_id']]) : null;
        $hired = $req['hired_inspector_id'] ? ops_one("SELECT id,name,salary_ctc,agency_cost,placement_fee,roll_type FROM inspectors WHERE id=?", [$req['hired_inspector_id']]) : null;
        $cands = ops_all("SELECT * FROM candidates WHERE requisition_id=? ORDER BY id DESC", [$req['id']]);
        view('ops/requisition_detail', ['req' => $req, 'outgoing' => $outgoing, 'hired' => $hired, 'cands' => $cands]); return;
    }
}

// ---- CV / hiring pipeline (deputation resourcing) --------------------------
function candidate_name($c) {
    return trim(($c['first_name'] ?? '') . ' ' . ($c['middle_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: '(no name)';
}
function nzc_cand($f, $v) {
    if (in_array($f, ['client_id','call_id','trade_id','skill_id','requisition_id'], true)) return $v === '' ? null : (int)$v;
    if (in_array($f, ['experience_years','expected_rate'], true)) return $v === '' ? 0 : $v;
    return $v;
}
function ops_candidates($route, $method) {
    $pdo = db();

    // stage transition (+ optional hire = create inspector)
    if ($route === 'candidate-cv' && $method === 'POST') {
        ops_require(is_coordinator_level(), 'You cannot update CVs.');
        $id = (int)($_GET['id'] ?? 0); $cand = ops_one("SELECT * FROM candidates WHERE id=?", [$id]);
        if (!$cand) { http_response_code(404); view('notfound'); return; }
        $text = trim($_POST['cv_text'] ?? ''); $fileName = $cand['cv_file_name'] ?? ''; $note = '';
        if (!empty($_FILES['cv_file']['tmp_name']) && (int)$_FILES['cv_file']['error'] === 0) {
            [$ext, $n2] = cv_text_from_upload($_FILES['cv_file']['tmp_name'], $_FILES['cv_file']['name']);
            if ($ext !== '') $text = $ext; $fileName = $_FILES['cv_file']['name']; if ($n2) $note = $n2;
        }
        $kw = cv_extract_keywords($text);
        $pdo->prepare("UPDATE candidates SET cv_text=?, cv_keywords=?, cv_file_name=?, cv_analyzed_at=? WHERE id=?")->execute([$text, $kw, $fileName, date('c'), $id]);
        flash(($kw !== '' ? (substr_count($kw, ',') + 1) . ' keyword(s) extracted for search.' : 'CV saved (no keywords found — paste more text).') . ($note ? ' Note: ' . $note : ''));
        redirect('/candidate?id=' . $id);
    }
    if ($route === 'candidate-client' && $method === 'POST') {
        ops_require(is_coordinator_level(), 'You cannot update client tracking.');
        $id = (int)($_GET['id'] ?? 0); $cand = ops_one("SELECT * FROM candidates WHERE id=?", [$id]);
        if (!$cand) { http_response_code(404); view('notfound'); return; }
        $b = $_POST;
        $pdo->prepare("UPDATE candidates SET submitted_client_date=?, client_feedback=?, client_feedback_date=?, client_feedback_note=?, interview_required=?, interview_date=?, interview_done_date=?, interview_outcome=? WHERE id=?")
            ->execute([$b['submitted_client_date'] ?? '', $b['client_feedback'] ?? '', $b['client_feedback_date'] ?? '', trim($b['client_feedback_note'] ?? ''),
                !empty($b['interview_required']) ? 1 : 0, $b['interview_date'] ?? '', $b['interview_done_date'] ?? '', $b['interview_outcome'] ?? '', $id]);
        flash('Client submission / interview tracking updated.');
        redirect('/candidate?id=' . $id);
    }
    if ($route === 'candidate-credential' && $method === 'POST') {
        ops_require(is_coordinator_level(), 'You cannot send credential requests.');
        $id = (int)($_GET['id'] ?? 0); $cand = ops_one("SELECT * FROM candidates WHERE id=?", [$id]);
        if (!$cand) { http_response_code(404); view('notfound'); return; }
        [$ok, $msg] = cv_send_credential_request($cand);
        $pdo->prepare("UPDATE candidates SET credential_requested=1 WHERE id=?")->execute([$id]);
        flash($ok ? 'Credential-request e-mail sent to the candidate.' : ('Logged; ' . $msg), $ok ? 'success' : 'warning');
        redirect('/candidate?id=' . $id);
    }
    if ($route === 'candidate-stage') {
        ops_require(is_coordinator_level(), 'Only coordinators and admins can move a candidate.');
        $id = (int)($_GET['id'] ?? 0);
        $cand = ops_one("SELECT * FROM candidates WHERE id=?", [$id]);
        if (!$cand) { http_response_code(404); view('notfound'); return; }
        if ($method === 'POST') {
            $to = $_POST['to_stage'] ?? '';
            if (!isset(lk_options_or('candidate_stage', CAND_STAGES)[$to])) { flash('Unknown stage.', 'error'); redirect('/candidate?id=' . $id); }
            $remark = trim($_POST['remark'] ?? '');
            $decided = in_array($to, ['ACCEPTED','REJECTED','WITHDRAWN','OFFER_DECLINED'], true) ? date('c') : ($cand['decided_at'] ?: '');
            $pdo->prepare("UPDATE candidates SET stage=?, decided_at=? WHERE id=?")->execute([$to, $decided, $id]);
            $pdo->prepare("INSERT INTO candidate_events (candidate_id,from_stage,to_stage,remark,actor,created_at) VALUES (?,?,?,?,?,?)")
                ->execute([$id, $cand['stage'], $to, $remark, user_name(current_user()), date('c')]);
            $msg = 'Candidate moved to ' . lk_options_or('candidate_stage', CAND_STAGES)[$to] . '.';
            // Hired: create an inspector/resource record from the accepted candidate.
            if ($to === 'ACCEPTED' && !empty($_POST['make_inspector']) && empty($cand['inspector_id'])) {
                $name = candidate_name($cand);
                $agId = ($_POST['agency_id'] ?? '') !== '' ? (int)$_POST['agency_id'] : null;
                $ag = $agId ? agency_get($agId) : null;
                $roll = ($_POST['roll_type'] ?? '') === 'AGENCY' ? 'AGENCY' : 'OWN';
                $agName = $ag['name'] ?? '';
                $placement = ($_POST['placement_fee'] ?? '') !== '' ? (float)$_POST['placement_fee'] : 0;
                $agCost = ($_POST['agency_cost'] ?? '') !== '' ? (float)$_POST['agency_cost'] : 0;
                // On agency roll → costed as a sub-con (monthly agency charge); on our own roll → asset (salary).
                $kind = ($roll === 'AGENCY') ? 'SUBCON' : 'ASSET';
                // Recruitment placement fee is provisional until the agency's guarantee window passes.
                $gd = (int)($ag['guarantee_days'] ?? 90) ?: 90;
                $feeStatus = $placement > 0 ? 'PROVISIONAL' : '';
                $guarUpto  = $placement > 0 ? date('Y-m-d', strtotime("+$gd days")) : '';
                $pdo->prepare("INSERT INTO inspectors (name,first_name,middle_name,last_name,email,mobile,trade_id,skill_ids,sbus,sbu,designation,staff_kind,agency_id,roll_type,agency_name,agency_cost,placement_fee,fee_status,guarantee_upto,status,created_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'ACTIVE',?)")
                    ->execute([$name, $cand['first_name'], $cand['middle_name'], $cand['last_name'], $cand['email'], $cand['mobile'],
                        $cand['trade_id'], (string)($cand['skill_id'] ?: ''), $cand['sbu'], $cand['sbu'], $cand['designation'], $kind,
                        $agId, $roll, $agName, $agCost, $placement, $feeStatus, $guarUpto, date('c')]);
                $insId = $pdo->lastInsertId();
                $pdo->prepare("UPDATE candidates SET inspector_id=? WHERE id=?")->execute([$insId, $id]);
                // Fill the requisition this candidate was raised against.
                if (!empty($cand['requisition_id'])) {
                    $pdo->prepare("UPDATE requisitions SET hired_inspector_id=?, status='HIRED' WHERE id=?")->execute([$insId, (int)$cand['requisition_id']]);
                    $msg .= ' Requisition filled.';
                }
                $msg .= ' Added to Inspectors (' . ($roll === 'AGENCY' ? 'on agency roll' : 'on our roll') . ') — you can now allocate deputation jobs to them.';
            }
            flash($msg);
        }
        redirect('/candidate?id=' . $id);
    }

    // list + stage filter
    if ($route === 'candidates') {
        ops_require(is_coordinator_level(), 'Only coordinators and admins can view the hiring pipeline.');
        $q = trim($_GET['q'] ?? ''); $stage = $_GET['stage'] ?? '';
        $where = '1=1'; $args = [];
        if ($stage !== '' && isset(lk_options_or('candidate_stage', CAND_STAGES)[$stage])) { $where .= ' AND c.stage=?'; $args[] = $stage; }
        if ($q) { $where .= " AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.cand_code LIKE ? OR c.proposed_site LIKE ? OR c.cv_keywords LIKE ? OR c.cv_text LIKE ?)"; array_push($args, "%$q%","%$q%","%$q%","%$q%","%$q%","%$q%"); }
        $rows = ops_all("SELECT c.*, bp.legal_name client_name, bp.display_name client_disp, t.label trade_label
            FROM candidates c LEFT JOIN business_partners bp ON bp.id=c.client_id
            LEFT JOIN lookup_values t ON t.id=c.trade_id WHERE $where ORDER BY c.id DESC", $args);
        $counts = [];
        foreach (ops_all("SELECT stage, COUNT(*) n FROM candidates GROUP BY stage") as $r) $counts[$r['stage']] = (int)$r['n'];
        view('ops/candidate_list', ['rows' => $rows, 'q' => $q, 'stage' => $stage, 'counts' => $counts]);
        return;
    }

    // new / edit
    if ($route === 'candidate-new' || $route === 'candidate-edit') {
        ops_require(is_coordinator_level(), 'Only coordinators and admins can add candidates.');
        $cand = null;
        if ($route === 'candidate-edit') {
            $cand = ops_one("SELECT * FROM candidates WHERE id=?", [(int)($_GET['id'] ?? 0)]);
            if (!$cand) { http_response_code(404); view('notfound'); return; }
        }
        if ($method === 'POST') {
            $b = $_POST;
            $fields = ['first_name','middle_name','last_name','client_id','call_id','trade_id','skill_id',
                'designation','source','agency','proposed_site','sbu','experience_years','email','mobile',
                'cv_link','expected_rate','rate_type','cv_received_date','remarks','requisition_id'];
            if ($cand) {
                $set = implode(',', array_map(fn($f) => "$f=?", $fields));
                $vals = array_map(fn($f) => nzc_cand($f, $b[$f] ?? ''), $fields); $vals[] = $cand['id'];
                $pdo->prepare("UPDATE candidates SET $set WHERE id=?")->execute($vals);
                flash('Candidate updated.');
                redirect('/candidate?id=' . $cand['id']);
            } else {
                $code = ops_next_code('candidates', 'cand_code', 'CV');
                $cols = array_merge(['cand_code'], $fields, ['stage','created_by','created_at']);
                $vals = array_merge([$code], array_map(fn($f) => nzc_cand($f, $b[$f] ?? ''), $fields),
                    ['RECEIVED', user_name(current_user()), date('c')]);
                $ph = implode(',', array_fill(0, count($cols), '?'));
                $pdo->prepare("INSERT INTO candidates (" . implode(',', $cols) . ") VALUES ($ph)")->execute($vals);
                $id = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO candidate_events (candidate_id,from_stage,to_stage,remark,actor,created_at) VALUES (?,?,?,?,?,?)")
                    ->execute([$id, '', 'RECEIVED', 'CV received', user_name(current_user()), date('c')]);
                flash("$code added to the hiring pipeline.");
                redirect('/candidate?id=' . $id);
            }
        }
        // client's deputation calls (to link the candidate to a specific requirement)
        $depCalls = ops_all("SELECT id, call_code, inspection_type FROM calls ORDER BY id DESC");
        $agencies = array_values(array_filter(array_column(ops_all("SELECT DISTINCT agency FROM subcons WHERE agency<>'' ORDER BY agency"), 'agency')));
        $preReq = $cand ? ($cand['requisition_id'] ?? null) : (($_GET['req'] ?? '') !== '' ? (int)$_GET['req'] : null);
        view('ops/candidate_form', ['cand' => $cand, 'clients' => clients_list(), 'depCalls' => $depCalls, 'agencies' => $agencies,
            'requisitions' => requisitions_list(true), 'preReq' => $preReq,
            'trades' => lk_type('trade') ? lk_root_values(lk_type('trade')['id']) : [], 'skillsByTrade' => skills_by_trade()]);
        return;
    }

    // detail
    if ($route === 'candidate') {
        ops_require(is_coordinator_level());
        $cand = ops_one("SELECT c.*, bp.legal_name client_name, bp.display_name client_disp,
            t.label trade_label, s.label skill_label, ca.call_code
            FROM candidates c LEFT JOIN business_partners bp ON bp.id=c.client_id
            LEFT JOIN lookup_values t ON t.id=c.trade_id LEFT JOIN lookup_values s ON s.id=c.skill_id
            LEFT JOIN calls ca ON ca.id=c.call_id WHERE c.id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$cand) { http_response_code(404); view('notfound'); return; }
        $events = ops_all("SELECT * FROM candidate_events WHERE candidate_id=? ORDER BY id DESC", [$cand['id']]);
        view('ops/candidate_detail', ['cand' => $cand, 'events' => $events]);
        return;
    }
}

// ---- Monthly voucher (P3: auto-fill skeleton) ------------------------------
function my_inspector_id() { $u = current_user(); return $u['inspector_id'] ?? null; }
// Friendly, actionable message when an Inspector login has no inspector profile linked.
function inspector_link_msg() {
    return 'Your login isn’t linked to an inspector profile yet, so "My Jobs" and "My Voucher" can’t open. '
         . 'An administrator can fix this in Users → open your name → set "Linked inspector" '
         . '(if your inspector record doesn’t exist yet, add it under Inspectors first).';
}
function voucher_owner_is_me($v) { return my_inspector_id() && (int)$v['inspector_id'] === (int)my_inspector_id(); }
function can_view_voucher($v) { return is_coordinator_level() || voucher_owner_is_me($v); }
function can_edit_voucher($v) { return ($v['status'] === 'DRAFT') && (is_coordinator_level() || voucher_owner_is_me($v)); }

// Build the month's day rows from the inspector's jobs (idempotent — skips days
// already pulled for the same job). Returns how many rows were added.
function voucher_generate($v) {
    $pdo = db();
    [$y, $m] = array_map('intval', explode('-', $v['month']));
    $from = sprintf('%04d-%02d-01', $y, $m);
    $to = date('Y-m-t', strtotime($from));
    $jobs = ops_all("SELECT j.id, j.boss_id, j.sbu, j.scheduled_date, j.inspection_start_date, j.inspection_end_date,
        c.client_id, c.vendor_id, bn.boss_number,
        vn.display_name vdisp, vn.legal_name vleg, cl.display_name cdisp, cl.legal_name cleg
        FROM jobs j LEFT JOIN calls c ON c.id=j.call_id LEFT JOIN boss_numbers bn ON bn.id=j.boss_id
        LEFT JOIN business_partners vn ON vn.id=c.vendor_id LEFT JOIN business_partners cl ON cl.id=c.client_id
        WHERE j.inspector_id=?", [$v['inspector_id']]);
    $added = 0;
    $ins = $pdo->prepare("INSERT INTO voucher_entries (voucher_id,entry_date,day_type,job_id,boss_id,client_id,vendor_id,file_no,line_no,sbu,site_label,hours,is_auto)
        VALUES (?,?, 'WORK', ?,?,?,?,?,'',?,?, ?, 1)");
    foreach ($jobs as $j) {
        $s = $j['inspection_start_date'] ?: ($j['scheduled_date'] ?: '');
        $e = $j['inspection_end_date'] ?: $s;
        if (!$s) continue;
        $start = max(strtotime($from), strtotime($s));
        $stop  = min(strtotime($to), strtotime($e ?: $s));
        for ($d = $start; $d !== false && $d <= $stop; $d = strtotime('+1 day', $d)) {
            $date = date('Y-m-d', $d);
            // Skip the date if ANYTHING is already on it — the same job pulled
            // before, another job, or a day entered by hand. Two rows on one date
            // double-count it whichever way they got there.
            if ((int)ops_val("SELECT COUNT(*) FROM voucher_entries WHERE voucher_id=? AND entry_date=?", [$v['id'], $date])) continue;
            $site = $j['vdisp'] ?: ($j['vleg'] ?: ($j['cdisp'] ?: ($j['cleg'] ?: '')));
            $ins->execute([$v['id'], $date, $j['id'], $j['boss_id'], $j['client_id'], $j['vendor_id'], $j['boss_number'] ?: '', $j['sbu'], $site, 8]);
            $added++;
        }
    }
    return $added;
}

// Travel modes / expense heads this inspector may use (entitlement-limited; if
// the Super Admin hasn't configured any, default to all active — usable out of the box).
function voucher_modes_for($insId) {
    if ((int)ops_val("SELECT COUNT(*) FROM inspector_allowances WHERE inspector_id=? AND kind='MODE'", [$insId]))
        return ops_all("SELECT tm.* FROM travel_modes tm JOIN inspector_allowances a ON a.code=tm.code AND a.kind='MODE' AND a.allowed=1 WHERE a.inspector_id=? AND tm.active=1 ORDER BY tm.id", [$insId]);
    return ops_all("SELECT * FROM travel_modes WHERE active=1 ORDER BY id");
}
function voucher_heads_for($insId) {
    if ((int)ops_val("SELECT COUNT(*) FROM inspector_allowances WHERE inspector_id=? AND kind='HEAD'", [$insId]))
        $rows = ops_all("SELECT eh.* FROM expense_heads eh JOIN inspector_allowances a ON a.code=eh.code AND a.kind='HEAD' AND a.allowed=1 WHERE a.inspector_id=? AND eh.active=1 ORDER BY eh.sort_order, eh.id", [$insId]);
    else $rows = ops_all("SELECT * FROM expense_heads WHERE active=1 ORDER BY sort_order, id");
    return array_values(array_filter($rows, fn($h) => $h['code'] !== 'KMTRAVEL')); // travel is computed from mode × km
}
function voucher_mode_rates($insId) {
    $out = [];
    foreach (voucher_modes_for($insId) as $m) if ($m['basis'] === 'PER_KM') $out[$m['code']] = inspector_mode_rate($insId, $m['code']);
    return $out;
}
function vendor_km_lookup($insId, $vendorId) {
    if (!$insId || !$vendorId) return null;
    return ops_one("SELECT mode_code, km FROM vendor_km_memory WHERE inspector_id=? AND vendor_id=?", [$insId, $vendorId]);
}
function vendor_km_remember($insId, $vendorId, $mode, $km) {
    if (!$insId || !$vendorId || $km <= 0) return;
    $pdo = db();
    $ex = ops_val("SELECT id FROM vendor_km_memory WHERE inspector_id=? AND vendor_id=?", [$insId, $vendorId]);
    if ($ex) $pdo->prepare("UPDATE vendor_km_memory SET mode_code=?, km=? WHERE id=?")->execute([$mode, $km, $ex]);
    else $pdo->prepare("INSERT INTO vendor_km_memory (inspector_id,vendor_id,mode_code,km) VALUES (?,?,?,?)")->execute([$insId, $vendorId, $mode, $km]);
}
// Roll the voucher's entries into the summary "particulars": travel + per head.
function voucher_summary($voucherId) {
    $travel = 0; $heads = [];
    foreach (ops_all("SELECT travel_amount, amounts FROM voucher_entries WHERE voucher_id=?", [$voucherId]) as $r) {
        $travel += (float)$r['travel_amount'];
        foreach (expense_extra_decode($r['amounts'] ?? '') as $c => $a) $heads[$c] = ($heads[$c] ?? 0) + (float)$a;
    }
    return ['travel' => $travel, 'heads' => $heads, 'grand' => $travel + array_sum($heads)];
}
function expense_head_label_map() {
    $out = ['KMTRAVEL' => 'Travel charges (KM × rate)'];
    foreach (ops_all("SELECT code, label FROM expense_heads") as $h) $out[$h['code']] = $h['label'];
    return $out;
}

function ops_vouchers($route, $method) {
    $pdo = db();

    if ($route === 'vouchers') {
        if (is_inspector()) {
            if (!my_inspector_id()) { flash(inspector_link_msg(), 'error'); redirect('/'); }
            $rows = ops_all("SELECT * FROM vouchers WHERE inspector_id=? ORDER BY month DESC", [my_inspector_id()]);
            view('ops/voucher_list', ['rows' => $rows, 'mine' => true, 'inspectors' => []]);
            return;
        }
        ops_require(is_coordinator_level(), 'You cannot view vouchers.');
        $rows = ops_all("SELECT v.*, i.name inspector_name FROM vouchers v LEFT JOIN inspectors i ON i.id=v.inspector_id ORDER BY v.month DESC, i.name");
        view('ops/voucher_list', ['rows' => $rows, 'mine' => false, 'inspectors' => inspectors_list(false)]);
        return;
    }

    if ($route === 'voucher') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { // find-or-create for inspector + month
            $insId = is_coordinator_level() ? (int)($_GET['ins'] ?? 0) : (int)my_inspector_id();
            $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
            if (!$insId) { flash('Pick an inspector first.', 'error'); redirect('/vouchers'); }
            $v = ops_one("SELECT * FROM vouchers WHERE inspector_id=? AND month=?", [$insId, $month]);
            if (!$v) {
                $pdo->prepare("INSERT INTO vouchers (inspector_id,office_id,month,status,created_by,created_at) VALUES (?,?,?, 'DRAFT', ?,?)")
                    ->execute([$insId, current_user()['home_office_id'] ?? null, $month, user_name(current_user()), date('c')]);
                $id = $pdo->lastInsertId();
            } else { $id = $v['id']; }
            redirect('/voucher?id=' . $id);
        }
        $v = ops_one("SELECT v.*, i.name inspector_name, i.emp_code, i.sbu FROM vouchers v LEFT JOIN inspectors i ON i.id=v.inspector_id WHERE v.id=?", [$id]);
        if (!$v) { http_response_code(404); view('notfound'); return; }
        ops_require(can_view_voucher($v), 'You cannot view this voucher.');
        $entries = ops_all("SELECT * FROM voucher_entries WHERE voucher_id=? ORDER BY entry_date, id", [$id]);
        view('ops/voucher_detail', ['v' => $v, 'entries' => $entries, 'canEdit' => can_edit_voucher($v),
            'leaveOpts' => lk_options_or('leave_type', LEAVE_TYPES), 'dayOpts' => lk_options_or('day_code', DAY_CODES),
            'heads' => voucher_heads_for($v['inspector_id']), 'modes' => voucher_modes_for($v['inspector_id']),
            'rates' => voucher_mode_rates($v['inspector_id']), 'sum' => voucher_summary($v['id']),
            'headLabels' => expense_head_label_map(), 'canApprove' => is_coordinator_level(),
            'natureOpts' => ['REVENUE'=>'Revenue','NONREV'=>'Non-Revenue','SUPPORT'=>'Support function','MD'=>'MD']]);
        return;
    }

    if ($route === 'voucher-header' && $method === 'POST') {
        $v = ops_one("SELECT * FROM vouchers WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$v) { http_response_code(404); view('notfound'); return; }
        ops_require(can_edit_voucher($v), 'This voucher can no longer be edited.');
        $pdo->prepare("UPDATE vouchers SET nature=?, advance=?, office_incurred=? WHERE id=?")
            ->execute([$_POST['nature'] ?? '', (float)($_POST['advance'] ?? 0), (float)($_POST['office_incurred'] ?? 0), $v['id']]);
        if (!empty($_FILES['support']['tmp_name']) && (int)$_FILES['support']['error'] === 0) {
            if ((int)$_FILES['support']['size'] <= 6 * 1024 * 1024) {
                $data = base64_encode(file_get_contents($_FILES['support']['tmp_name']));
                $pdo->prepare("UPDATE vouchers SET supporting_file=?, supporting_name=?, supporting_mime=? WHERE id=?")
                    ->execute([$data, substr($_FILES['support']['name'], 0, 200), $_FILES['support']['type'] ?: 'application/octet-stream', $v['id']]);
            } else { flash('Supporting file is larger than 6 MB — please compress it.', 'error'); redirect('/voucher?id=' . $v['id']); }
        }
        flash('Voucher details saved.');
        redirect('/voucher?id=' . $v['id']);
    }

    if ($route === 'voucher-csv') {
        $v = ops_one("SELECT v.*, i.name inspector_name, i.emp_code FROM vouchers v LEFT JOIN inspectors i ON i.id=v.inspector_id WHERE v.id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$v) { http_response_code(404); echo 'Not found.'; return; }
        ops_require(can_view_voucher($v), 'You cannot download this voucher.');
        $heads = voucher_heads_for($v['inspector_id']);
        $entries = ops_all("SELECT * FROM voucher_entries WHERE voucher_id=? ORDER BY entry_date, id", [$v['id']]);
        $rows = [];
        $rows[] = ['Statement of Travelling Expenses'];
        $rows[] = ['Inspector', $v['inspector_name'], 'Emp code', $v['emp_code'], 'Month', $v['month'], 'Status', $v['status']];
        $rows[] = [];
        $hdr = ['Date','Attendance / Site','File No (BOSS)','Line No','Hours','Mode','KM','Travel'];
        foreach ($heads as $h) $hdr[] = $h['label'];
        $hdr[] = 'Row total';
        $rows[] = $hdr;
        $tTravel = 0; $grand = 0; $tHours = 0; $tHead = []; foreach ($heads as $h) $tHead[$h['code']] = 0;
        foreach ($entries as $e) {
            $amt = expense_extra_decode($e['amounts'] ?? '');
            $att = $e['day_type'] === 'WORK' ? ($e['site_label'] ?: '') : trim($e['day_type'] . ' ' . ($e['leave_code'] ?: $e['office_code'] ?: ''));
            $line = [$e['entry_date'], $att, $e['file_no'], $e['line_no'], (float)$e['hours'], $e['mode_code'], (float)$e['km'], (float)$e['travel_amount']];
            foreach ($heads as $h) { $val = (float)($amt[$h['code']] ?? 0); $line[] = $val; $tHead[$h['code']] += $val; }
            $line[] = (float)$e['row_total'];
            $rows[] = $line;
            $tTravel += (float)$e['travel_amount']; $grand += (float)$e['row_total']; $tHours += (float)$e['hours'];
        }
        $tot = ['TOTAL', '', '', '', $tHours, '', '', $tTravel];
        foreach ($heads as $h) $tot[] = $tHead[$h['code']];
        $tot[] = $grand;
        $rows[] = $tot;
        $rows[] = [];
        $rows[] = ['Less: Advance', (float)$v['advance']];
        $rows[] = ['Less: Office-incurred', (float)$v['office_incurred']];
        $rows[] = ['Balance to be paid / (recovered)', $grand - (float)$v['advance'] - (float)$v['office_incurred']];
        csv_download('Voucher-' . preg_replace('/[^A-Za-z0-9]/', '', (string)$v['inspector_name']) . '-' . $v['month'] . '.csv', $rows);
    }

    if ($route === 'voucher-file') {
        $v = ops_one("SELECT * FROM vouchers WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$v || !$v['supporting_file']) { http_response_code(404); echo 'No supporting file.'; return; }
        ops_require(can_view_voucher($v), 'You cannot view this file.');
        send_uploaded_file(base64_decode($v['supporting_file']),
                           $v['supporting_name'] ?: 'support', $v['supporting_mime'] ?? '');
        return;
    }

    if ($route === 'voucher-status' && $method === 'POST') {
        $v = ops_one("SELECT * FROM vouchers WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$v) { http_response_code(404); view('notfound'); return; }
        $act = $_POST['action'] ?? '';
        if ($act === 'submit') {
            ops_require(($v['status'] === 'DRAFT') && (is_coordinator_level() || voucher_owner_is_me($v)), 'Cannot submit this voucher.');
            $pdo->prepare("UPDATE vouchers SET status='SUBMITTED', submitted_at=? WHERE id=?")->execute([date('c'), $v['id']]);
            flash('Voucher submitted for approval.');
        } elseif ($act === 'approve') {
            ops_require(is_coordinator_level() && $v['status'] === 'SUBMITTED', 'Only a manager can approve a submitted voucher.');
            $pdo->prepare("UPDATE vouchers SET status='APPROVED', checked_by=?, approved_by=?, authorized_by=?, approved_at=? WHERE id=?")
                ->execute([$_POST['checked_by'] ?? '', ($_POST['approved_by'] ?? '') ?: user_name(current_user()), $_POST['authorized_by'] ?? '', date('c'), $v['id']]);
            flash('Voucher approved.');
        } elseif ($act === 'paid') {
            ops_require(is_coordinator_level() && $v['status'] === 'APPROVED', 'Only an approved voucher can be marked paid.');
            $pdo->prepare("UPDATE vouchers SET status='PAID' WHERE id=?")->execute([$v['id']]);
            flash('Voucher marked as paid.');
        } elseif ($act === 'reopen') {
            ops_require(is_coordinator_level(), 'Only a manager can reopen a voucher.');
            $pdo->prepare("UPDATE vouchers SET status='DRAFT' WHERE id=?")->execute([$v['id']]);
            flash('Voucher reopened for editing.');
        }
        redirect('/voucher?id=' . $v['id']);
    }

    if ($route === 'voucher-print') {
        $v = ops_one("SELECT v.*, i.name inspector_name, i.emp_code, i.sbu FROM vouchers v LEFT JOIN inspectors i ON i.id=v.inspector_id WHERE v.id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$v) { http_response_code(404); view('notfound'); return; }
        ops_require(can_view_voucher($v), 'You cannot view this voucher.');
        $entries = ops_all("SELECT * FROM voucher_entries WHERE voucher_id=? ORDER BY entry_date, id", [$v['id']]);
        $sum = voucher_summary($v['id']);
        $headLabels = expense_head_label_map();
        require __DIR__ . '/../views/ops/voucher_print.php';
        return;
    }

    if ($route === 'voucher-save' && $method === 'POST') {
        $v = ops_one("SELECT * FROM vouchers WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$v) { http_response_code(404); view('notfound'); return; }
        ops_require(can_edit_voucher($v), 'This voucher can no longer be edited.');
        $heads = voucher_heads_for($v['inspector_id']);
        $rates = voucher_mode_rates($v['inspector_id']);
        // 8.5-hour daily cap: no inspector may log more than DAILY_HOURS_CAP working
        // hours on any single date. Validate the whole submission before saving.
        $hoursByDate = [];
        foreach (($_POST['rows'] ?? []) as $eid => $r) {
            $e = ops_one("SELECT entry_date FROM voucher_entries WHERE id=? AND voucher_id=?", [(int)$eid, $v['id']]);
            if (!$e || !$e['entry_date']) continue;
            $hoursByDate[$e['entry_date']] = ($hoursByDate[$e['entry_date']] ?? 0) + (float)($r['hours'] ?? 0);
        }
        foreach ($hoursByDate as $d => $hrs) {
            if ($hrs > hours_cap() + 0.001) {
                flash('Hours on ' . fdate($d) . ' total ' . rtrim(rtrim(number_format($hrs, 2), '0'), '.') . ' h — over the ' . hours_cap_disp() . ' h daily limit. Please reduce so no day exceeds ' . hours_cap_disp() . ' hours.', 'error');
                redirect('/voucher?id=' . $v['id']);
            }
        }
        $grand = 0;
        foreach (($_POST['rows'] ?? []) as $eid => $r) {
            $e = ops_one("SELECT * FROM voucher_entries WHERE id=? AND voucher_id=?", [(int)$eid, $v['id']]);
            if (!$e) continue;
            $mode = $r['mode'] ?? '';
            $km = (float)($r['km'] ?? 0);
            $travel = round($km * (float)($rates[$mode] ?? 0), 2);
            $amounts = [];
            foreach ($heads as $h) { $a = (float)($r['amt'][$h['code']] ?? 0); if ($a != 0) $amounts[$h['code']] = $a; }
            $rowTotal = $travel + array_sum($amounts);
            $pdo->prepare("UPDATE voucher_entries SET hours=?, line_no=?, file_no=?, mode_code=?, km=?, travel_amount=?, amounts=?, row_total=? WHERE id=? AND voucher_id=?")
                ->execute([(float)($r['hours'] ?? 0), $r['line_no'] ?? '', $r['file_no'] ?? '', $mode, $km, $travel,
                    $amounts ? json_encode($amounts) : '', $rowTotal, $e['id'], $v['id']]);
            if ($e['day_type'] === 'WORK' && $e['vendor_id'] && $km > 0) vendor_km_remember($v['inspector_id'], $e['vendor_id'], $mode, $km);
            $grand += $rowTotal;
        }
        $pdo->prepare("UPDATE vouchers SET total=? WHERE id=?")->execute([$grand, $v['id']]);
        flash('Voucher saved. Total ₹' . number_format($grand, 0) . '.');
        redirect('/voucher?id=' . $v['id']);
    }

    if ($route === 'voucher-generate' && $method === 'POST') {
        $v = ops_one("SELECT * FROM vouchers WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$v) { http_response_code(404); view('notfound'); return; }
        ops_require(can_edit_voucher($v), 'This voucher can no longer be edited.');
        $n = voucher_generate($v);
        flash($n ? "$n working day(s) pulled from jobs." : 'No new job days found for this month.');
        redirect('/voucher?id=' . $v['id']);
    }

    if ($route === 'voucher-entry' && $method === 'POST') {
        $v = ops_one("SELECT * FROM vouchers WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$v) { http_response_code(404); view('notfound'); return; }
        ops_require(can_edit_voucher($v), 'This voucher can no longer be edited.');
        $b = $_POST; $do = $b['_do'] ?? '';
        if ($do === 'update') {
            $ent = ops_one("SELECT entry_date FROM voucher_entries WHERE id=? AND voucher_id=?", [(int)$b['entry_id'], $v['id']]);
            if ($ent && $ent['entry_date']) {
                [$ok, , $cap, $tot] = hours_within_cap($v['inspector_id'], $ent['entry_date'], (float)($b['hours'] ?? 0), (int)$b['entry_id']);
                if (!$ok) { flash('Hours on ' . $ent['entry_date'] . ' would total ' . rtrim(rtrim(number_format($tot, 2), '0'), '.') . ' h — over the ' . $cap . ' h daily limit.', 'error'); redirect('/voucher?id=' . $v['id']); }
            }
            $pdo->prepare("UPDATE voucher_entries SET hours=?, line_no=?, file_no=?, notes=? WHERE id=? AND voucher_id=?")
                ->execute([(float)($b['hours'] ?? 0), $b['line_no'] ?? '', $b['file_no'] ?? '', $b['notes'] ?? '', (int)$b['entry_id'], $v['id']]);
            flash('Row updated.');
        } elseif ($do === 'add') {
            $ed = $b['entry_date'] ?? '';
            if ($ed) {
                [$ok, , $cap, $tot] = hours_within_cap($v['inspector_id'], $ed, (float)($b['hours'] ?? 0));
                if (!$ok) { flash('Hours on ' . $ed . ' would total ' . rtrim(rtrim(number_format($tot, 2), '0'), '.') . ' h — over the ' . $cap . ' h daily limit.', 'error'); redirect('/voucher?id=' . $v['id']); }
            }
            $dt = in_array($b['day_type'] ?? '', ['OFFICE','LEAVE','HOLIDAY','WEEKOFF','WORK'], true) ? $b['day_type'] : 'OFFICE';
            // A day happens once. The same date added twice — a second click, a
            // resend, or simply not noticing it was already on the sheet — put two
            // rows against one date, and a month sheet that double-counts a day is
            // a payroll problem, not a display one. The daily-hours cap caught it
            // only when the hours were big enough to breach the cap.
            $clash = ops_one("SELECT id, day_type, hours FROM voucher_entries WHERE voucher_id=? AND entry_date=?",
                             [$v['id'], $b['entry_date'] ?? '']);
            if ($clash) {
                $dayLbl = lk_options_or('day_code', DAY_CODES)[$clash['day_type']] ?? $clash['day_type'];
                flash(fdate($b['entry_date'] ?? '') . ' is already on this ' . Tl('voucher')
                    . ' as ' . $dayLbl . ' (' . rtrim(rtrim(number_format((float)$clash['hours'], 2), '0'), '.') . ' h). '
                    . 'Edit that row rather than adding the day again.', 'warning');
                redirect('/voucher?id=' . $v['id']);
            }
            $pdo->prepare("INSERT INTO voucher_entries (voucher_id,entry_date,day_type,sbu,site_label,hours,leave_code,office_code,is_auto)
                VALUES (?,?,?,?,?,?,?,?,0)")
                ->execute([$v['id'], $b['entry_date'] ?? '', $dt, $b['sbu'] ?? '',
                    $dt === 'LEAVE' ? 'On leave' : ($dt === 'OFFICE' ? 'In office' : ucfirst(strtolower($dt))),
                    (float)($b['hours'] ?? 0), $dt === 'LEAVE' ? ($b['leave_code'] ?? '') : '', $dt === 'OFFICE' ? ($b['office_code'] ?? '') : '']);
            flash('Day added.');
        } elseif ($do === 'del') {
            $pdo->prepare("DELETE FROM voucher_entries WHERE id=? AND voucher_id=?")->execute([(int)$b['entry_id'], $v['id']]);
            flash('Row removed.');
        }
        redirect('/voucher?id=' . $v['id']);
    }
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
        // §b.ix — narrow the allocation board by engineer, by month, or by a date
        // range, which is how a coordinator actually looks for a free slot.
        $fInsp = (int)($_GET['inspector'] ?? 0);
        if ($fInsp) { $where .= " AND j.inspector_id=?"; $args[] = $fInsp; }
        $fMonth = trim($_GET['month'] ?? '');           // YYYY-MM
        if ($fMonth !== '' && preg_match('/^\d{4}-\d{2}$/', $fMonth)) {
            $where .= " AND j.scheduled_date LIKE ?"; $args[] = $fMonth . '%';
        }
        $fFrom = trim($_GET['from'] ?? ''); $fTo = trim($_GET['to'] ?? '');
        if ($fFrom !== '') { $where .= " AND j.scheduled_date >= ?"; $args[] = $fFrom; }
        if ($fTo   !== '') { $where .= " AND j.scheduled_date <= ?"; $args[] = $fTo; }
        $fOffice = (int)($_GET['office'] ?? 0);
        if ($fOffice) { $where .= " AND j.executing_office_id=?"; $args[] = $fOffice; }
        $fUnalloc = ($_GET['unallocated'] ?? '') === '1';
        if ($fUnalloc) $where .= " AND (j.inspector_id IS NULL AND (j.subcon_id IS NULL))";
        $rows = ops_all("SELECT j.*, c.call_code, bp.legal_name client_name, bp.display_name client_disp,
            i.name inspector_name FROM jobs j LEFT JOIN calls c ON c.id=j.call_id
            LEFT JOIN business_partners bp ON bp.id=c.client_id LEFT JOIN inspectors i ON i.id=j.inspector_id
            WHERE $where ORDER BY j.id DESC", $args);
        if (wants_csv()) {
            $seeCredit = can('data.credit'); $today = date('Y-m-d');
            $csv = [array_merge(['Job','Client','Inspector','Scheduled','Reporting'], $seeCredit ? ['Expected credit'] : [], ['TAT days','Status','Money'])];
            foreach ($rows as $j) {
                $money = $j['closed_flag'] ? (empty($j['invoice_raised']) ? 'Unbilled' : (empty($j['payment_received']) ? 'Awaiting payment' : 'Paid')) : '';
                $csv[] = array_merge([$j['job_code'], $j['client_disp'] ?: $j['client_name'], $j['inspector_name'], $j['scheduled_date'], REPORT_FREQ[$j['reporting_frequency']] ?? ''],
                    $seeCredit ? [(float)$j['expected_credit']] : [], [$j['tat_days'], $j['closed_flag'] ? 'Closed' : 'Open', $money]);
            }
            csv_download('jobs-' . date('Y-m-d') . '.csv', $csv);
        }
        view('ops/jobs', ['rows' => $rows, 'q' => $q, 'filter' => $filter,
            'inspectors' => inspectors_list(), 'offices' => offices_list(),
            'fInsp' => $fInsp, 'fMonth' => $fMonth, 'fFrom' => $fFrom, 'fTo' => $fTo,
            'fOffice' => $fOffice, 'fUnalloc' => $fUnalloc]); return;
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
            $fields = ['executing_office_id','inspector_id','subcon_id','job_type','stage','scheduled_date','inspection_start_date','inspection_end_date',
                'random_date1','random_date2','random_date3','folder_link','contract_number','inspection_dates','boss_id','expected_credit','credit_type','credit_direction',
                'reporting_frequency','report_custom_days','inspection_type','activity_id','sbu','mandays','subcon_cost','quotation_id'];
            // deliverables come as a checkbox array -> stored as CSV of codes
            $deliverables = implode(',', array_filter((array)($b['deliverables'] ?? [])));
            // §b.vi — the visit dates arrive as a list of date boxes. Keep them as one
            // ordered list, and keep scheduled_date as the first day so every existing
            // report, reminder and TAT calculation keeps working unchanged.
            $jdates = call_dates_parse(implode(',', (array)($b['inspection_dates'] ?? [])));
            $b['inspection_dates'] = implode(',', $jdates);
            if ($jdates) {
                if (($b['scheduled_date'] ?? '') === '') $b['scheduled_date'] = $jdates[0];
                if (($b['inspection_start_date'] ?? '') === '') $b['inspection_start_date'] = $jdates[0];
                if (($b['inspection_end_date'] ?? '') === '' && count($jdates) > 1) $b['inspection_end_date'] = end($jdates);
            }
            // validation: expected credit mandatory at allocation
            if (($b['expected_credit'] ?? '') === '' || (float)$b['expected_credit'] <= 0) {
                view('ops/job_form', array_merge(call_job_form_vars($job, $call),
                    ['error' => 'Expected credit is mandatory at allocation.']));
                return;
            }
            // Contract cover: putting an engineer on site against an expired
            // contract, or one whose quantity is used up, is refused here. This is
            // the last point before it costs money, so it is the right gate — and
            // an existing job can still be corrected, only new work is stopped.
            $gateQid = (int)($b['quotation_id'] ?? 0) ?: (int)($call['quotation_id'] ?? 0);
            $gate = (!$job && $gateQid && function_exists('contract_gate')) ? contract_gate($gateQid) : null;
            if ($gate && !$gate['allowed']) {
                view('ops/job_form', array_merge(call_job_form_vars($job, $call), [
                    'error' => $gate['reason'] . ' ' . ($gate['pending']
                        ? 'An exception has been requested and is ' . strtolower(OVERRIDE_STATUS[$gate['pending']['status']] ?? 'pending') . '.'
                        : 'Ask for an exception from the ' . Tl('call') . ' before allocating.'),
                    'gate' => $gate]));
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
                // §a.ix — stamp when the engineer was put on it, so the register can
                // show received -> forwarded -> allocated as three real lead times.
                $pdo->prepare("UPDATE calls SET status='ALLOCATED', allocated_at=? WHERE id=?")->execute([date('c'), $call['id']]);
                // An exception is spent when work is actually booked against it,
                // not when it is granted — that is what makes "allowed for 2
                // allocations" mean anything.
                if ($gate && !empty($gate['override'])) override_consume($gate['override']);
                flash("$code allocated. Assignment email sent to the " . Tl('engineer') . "."
                    . ($gate && !empty($gate['override']) ? ' One granted contract exception was used.' : ''));
            }
            custom_save('job', $jobId, $b);
            // inherit advance / report-vs-payment conditions from a linked quotation
            if (function_exists('crm_apply_quote_to_job')) crm_apply_quote_to_job($jobId);
            // consume the linked PO line item by this job's man-days (new jobs only)
            if (!$job && $call && ($call['po_line_item_id'] ?? null)) {
                $jj0 = ops_one("SELECT * FROM jobs WHERE id=?", [$jobId]);
                $md = job_mandays($jj0);
                $pdo->prepare("UPDATE po_line_items SET consumed = consumed + ? WHERE id=?")->execute([$md, $call['po_line_item_id']]);
            }
            // comp-off if any inspection date is a Sunday
            ops_check_compoff($jobId);
            // assignment email when an inspector + schedule exist
            $jj = ops_one("SELECT * FROM jobs WHERE id=?", [$jobId]);
            if ($jj['inspector_id'] && $jj['scheduled_date']) send_assignment_email($jobId);
            redirect('/job?id=' . $jobId);
        }
        // Show the contract position before anything is typed, so a coordinator
        // is not filling a form that will be refused on submit.
        $gq = (int)($call['quotation_id'] ?? 0);
        view('ops/job_form', array_merge(call_job_form_vars($job, $call),
            ['gate' => (!$job && $gq && function_exists('contract_gate')) ? contract_gate($gq) : null]));
        return;
    }
    if ($route === 'job-advance' && $method === 'POST') {
        ops_require(is_coordinator_level() || can('data.credit') || can('finance.reconcile'), 'You cannot update advance status.');
        $job = ops_one("SELECT * FROM jobs WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$job) { http_response_code(404); view('notfound'); return; }
        $pdo->prepare("UPDATE jobs SET adv_received=? WHERE id=?")->execute([!empty($_POST['adv_received']) ? 1 : 0, $job['id']]);
        flash(!empty($_POST['adv_received']) ? 'Advance marked received — scheduling can proceed.' : 'Advance marked NOT received.');
        redirect('/job?id=' . $job['id']);
    }
    if ($route === 'job-invoice') {
        ops_require(can('data.credit') || can('finance.reconcile'), 'You cannot record invoices.');
        $job = ops_one("SELECT * FROM jobs WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$job) { http_response_code(404); view('notfound'); return; }
        if ($method === 'POST') {
            $b = $_POST;
            $pdo->prepare("UPDATE jobs SET invoice_raised=?, invoice_number=?, invoice_date=?, invoice_due_date=?, invoice_amount=?,
                payment_received=?, payment_date=?, payment_amount=?, credit_received=? WHERE id=?")
                ->execute([
                    !empty($b['invoice_raised']) ? 1 : 0, $b['invoice_number'] ?? '', $b['invoice_date'] ?? '', $b['invoice_due_date'] ?? '',
                    num($b['invoice_amount'] ?? ''), !empty($b['payment_received']) ? 1 : 0, $b['payment_date'] ?? '', num($b['payment_amount'] ?? ''),
                    !empty($b['credit_received']) ? 1 : 0, $job['id']]);
            flash('Invoice / payment updated.');
            redirect('/job?id=' . $job['id']);
        }
        redirect('/job?id=' . $job['id']);
    }
    // A duplicate expense line, removed. New ones cannot be created any more, but
    // the copies already on file have to be clearable — a claim that reads double
    // is worse than one with a line missing.
    if ($route === 'expense-delete') {
        ops_require(is_coordinator_level() || can('finance.reconcile') || is_master(),
            'Only a coordinator, accounts or an administrator can remove an expense line.');
        $row = ops_one("SELECT * FROM expenses WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$row) { flash('That expense line no longer exists.', 'warning'); redirect('/jobs'); }
        $pdo->prepare("DELETE FROM expenses WHERE id=?")->execute([(int)$row['id']]);
        flash('Expense line removed.');
        redirect('/job?id=' . (int)$row['job_id']);
    }
    if ($route === 'job-close') {
        // Inspector (or coordinator) closes: report + expenses required
        $job = ops_one("SELECT * FROM jobs WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$job) { http_response_code(404); view('notfound'); return; }
        if ($method === 'POST') {
            $b = $_POST;
            // A job closes once. Without this, every re-post of the closure form —
            // a refresh, a back-and-resend, the offline queue re-sending an entry
            // the server had already taken — filed another set of expenses against
            // the same day's work, and the engineer's claim read double.
            if (!empty($job['closed_flag'])) {
                flash(ucfirst(Tl('job')) . ' ' . $job['job_code'] . ' was already closed'
                    . (!empty($job['closed_at']) ? ' on ' . fdate(substr((string)$job['closed_at'], 0, 10)) : '')
                    . '. Nothing was recorded twice. To correct the expenses, edit them on the '
                    . Tl('job') . ' below.', 'warning');
                redirect('/job?id=' . $job['id']);
            }
            $reportDate = $b['report_upload_date'] ?? '';
            if ($job['reporting_frequency'] !== 'NOREPORT' && $reportDate === '') {
                view('ops/job_close', ['job'=>$job,'error'=>'A report upload date is required before closing this job.']); return;
            }
            // collect any configurable (extra) headings into JSON {code:amount}
            $extra = [];
            foreach (expense_extra_headings() as $code=>$label) {
                $amt = num($_POST['extra'][$code] ?? 0);
                if ($amt != 0) $extra[$code] = $amt;
            }
            // save expenses row (same-day at closure)
            $pdo->prepare("INSERT INTO expenses (job_id,inspector_id,sbu,travel,local,food,lodging,misc,extra,exp_date,notes,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
                $job['id'], $job['inspector_id'], $b['sbu'] ?: $job['sbu'],
                num($b['travel']), num($b['local']), num($b['food']), num($b['lodging']), num($b['misc']),
                $extra ? json_encode($extra) : '', $reportDate ?: date('Y-m-d'), $b['exp_notes'] ?? '', date('c')]);
            $tat = days_between($job['inspection_end_date'], $reportDate);
            // If a report was produced, route it to the inspector's reporting manager for sign-off.
            $needsApproval = ($job['reporting_frequency'] !== 'NOREPORT' && $reportDate !== '') ? 'PENDING' : '';
            $pdo->prepare("UPDATE jobs SET closed_flag=1, closed_at=?, report_upload_date=?, report_link=?, tat_days=?, report_approval=? WHERE id=?")
                ->execute([date('c'), $reportDate, $b['report_link'] ?? '', $tat, $needsApproval, $job['id']]);
            send_closure_email($job['id']);
            if ($needsApproval === 'PENDING') report_approval_notify($job['id']);
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
        // The engineer standing at the gate needs to know who to ask for and where
        // to go. None of it was on this screen, so it lived in the assignment
        // e-mail or in a phone call to the coordinator.
        $jcall = $job['call_id'] ? ops_one("SELECT * FROM calls WHERE id=?", [(int)$job['call_id']]) : null;
        $siteAddr = ($jcall && !empty($jcall['site_address_id']))
            ? ops_one("SELECT * FROM partner_addresses WHERE id=?", [(int)$jcall['site_address_id']]) : null;
        // §xxiv — whatever the client sent with the order reaches the engineer here.
        view('ops/job_detail', ['job'=>$job,'expenses'=>$expenses,'profit'=>job_profit($job),
            'jcall'=>$jcall, 'siteAddr'=>$siteAddr,
            'clientInfo'=>$jcall ? partner_full($jcall['client_id']) : null,
            'vendorInfo'=>$jcall ? partner_full($jcall['vendor_id']) : null,
            'quoteDocs'=>function_exists('quote_docs_for_job') ? quote_docs_for_job($job['id']) : []]);
        return;
    }
}
// Everything the allocate-job screen needs. One place, because it is rendered
// again on each validation failure and a field missed here shows up as an
// undefined-variable warning on the re-render rather than at the happy path.
function call_job_form_vars($job, $call) {
    return [
        'job' => $job, 'call' => $call, 'error' => null, 'gate' => null,
        'offices' => offices_list(), 'inspectors' => inspectors_list(), 'subcons' => subcons_list(),
        'boss' => boss_for_client($call['client_id']),
        'clientInfo' => partner_full($call['client_id']),
        'vendorInfo' => partner_full($call['vendor_id']),
        'quotes' => job_linkable_quotes($call['client_id']),
        'cfvals' => $job ? custom_values_map('job', $job['id']) : [],
    ];
}

function nzc($f, $v) {
    $nullable = ['executing_office_id','inspector_id','subcon_id','boss_id','activity_id','report_custom_days','quotation_id'];
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
    if (!$insId && !is_coordinator_level()) { flash(inspector_link_msg(), 'error'); redirect('/'); }
    if ($insId) {
        $rows = ops_all("SELECT j.*, c.call_code, bp.legal_name client_name, bp.display_name client_disp
            FROM jobs j LEFT JOIN calls c ON c.id=j.call_id LEFT JOIN business_partners bp ON bp.id=c.client_id
            WHERE j.inspector_id=? ORDER BY j.closed_flag, j.scheduled_date DESC", [$insId]);
    } else {
        $rows = ops_all("SELECT j.*, c.call_code, bp.legal_name client_name, bp.display_name client_disp
            FROM jobs j LEFT JOIN calls c ON c.id=j.call_id LEFT JOIN business_partners bp ON bp.id=c.client_id
            WHERE j.closed_flag=0 ORDER BY j.scheduled_date DESC");
    }
    view('ops/my_jobs', ['rows' => $rows, 'f' => $_GET['f'] ?? '']);
}

// ---- Dashboards / reports (scoped + filtered) ------------------------------
function job_eff_date($j) { return ($j['scheduled_date'] ?? '') !== '' ? $j['scheduled_date'] : substr($j['created_at'] ?? '', 0, 10); }

// ---- Dashboard charts (dependency-free inline SVG, theme-reactive) ---------
function chart_color($i) { $c = CHART_COLORS; return $c[$i % count($c)]; }
function chart_no_data() { return '<p class="muted" style="padding:8px 0">No data for this filter.</p>'; }
// Horizontal colourful bars. $data = [label => value].
function svg_hbars($data, $money = false, $unit = '') {
    $data = array_filter($data, fn($v) => (float)$v != 0);
    if (!$data) return chart_no_data();
    arsort($data);
    $max = max(array_map('abs', array_map('floatval', $data)));
    $rowH = 24; $gap = 7; $labelW = 130; $barW = 240; $w = $labelW + $barW + 90;
    $h = count($data) * ($rowH + $gap) + 4; $y = 2; $i = 0;
    $svg = "<svg viewBox='0 0 $w $h' width='100%' style='max-width:560px' role='img'>";
    foreach ($data as $label => $val) {
        $bw = $max > 0 ? max(1, round(abs($val) / $max * $barW)) : 1; $col = chart_color($i); $vy = $y + $rowH * 0.68;
        $lab = htmlspecialchars(mb_strimwidth((string)$label, 0, 20, '…'), ENT_QUOTES);
        $vv = $money ? '₹' . number_format((float)$val) : (rtrim(rtrim(number_format((float)$val, 1, '.', ''), '0'), '.') . $unit);
        $svg .= "<text x='0' y='$vy' font-size='12' fill='var(--muted)'>$lab</text>";
        $svg .= "<rect x='$labelW' y='$y' width='$bw' height='" . ($rowH - 5) . "' rx='4' fill='$col'></rect>";
        $svg .= "<text x='" . ($labelW + $bw + 6) . "' y='$vy' font-size='12' fill='var(--ink)'>$vv</text>";
        $y += $rowH + $gap; $i++;
    }
    return $svg . "</svg>";
}
// Donut with legend. $data = [label => value].
function svg_donut($data, $money = false) {
    $data = array_filter($data, fn($v) => (float)$v > 0);
    if (!$data) return chart_no_data();
    arsort($data);
    $total = array_sum(array_map('floatval', $data));
    $cx = 70; $cy = 75; $r = 52; $sw = 26; $C = 2 * M_PI * $r; $off = 0; $i = 0;
    $legH = max(150, count($data) * 18 + 20);
    $svg = "<svg viewBox='0 0 340 $legH' width='100%' style='max-width:440px' role='img'>";
    foreach ($data as $label => $val) {
        $len = $val / $total * $C; $col = chart_color($i);
        $svg .= "<circle cx='$cx' cy='$cy' r='$r' fill='none' stroke='$col' stroke-width='$sw' stroke-dasharray='$len " . ($C - $len) . "' stroke-dashoffset='" . (-$off) . "' transform='rotate(-90 $cx $cy)'></circle>";
        $off += $len; $i++;
    }
    $svg .= "<text x='$cx' y='" . ($cy + 4) . "' text-anchor='middle' font-size='13' font-weight='700' fill='var(--ink)'>" . ($money ? '₹' . number_format($total) : rtrim(rtrim(number_format($total, 1, '.', ''), '0'), '.')) . "</text>";
    $ly = 22; $i = 0;
    foreach ($data as $label => $val) {
        $col = chart_color($i); $pct = round($val / $total * 100);
        $lab = htmlspecialchars(mb_strimwidth((string)$label, 0, 22, '…'), ENT_QUOTES);
        $svg .= "<rect x='158' y='" . ($ly - 9) . "' width='11' height='11' rx='2' fill='$col'></rect>";
        $svg .= "<text x='174' y='$ly' font-size='11.5' fill='var(--ink)'>$lab · $pct%</text>";
        $ly += 18; $i++;
    }
    return $svg . "</svg>";
}
// Big % ring (utilization / on-time). Accent-coloured.
function svg_gauge($pct, $label) {
    $pct = max(0, min(100, (float)$pct)); $r = 42; $C = 2 * M_PI * $r; $len = $pct / 100 * $C;
    $svg = "<svg viewBox='0 0 120 120' width='118' height='118' role='img'>";
    $svg .= "<circle cx='60' cy='60' r='$r' fill='none' stroke='var(--soft)' stroke-width='13'></circle>";
    $svg .= "<circle cx='60' cy='60' r='$r' fill='none' stroke='var(--accent)' stroke-width='13' stroke-linecap='round' stroke-dasharray='$len " . ($C - $len) . "' transform='rotate(-90 60 60)'></circle>";
    $svg .= "<text x='60' y='58' text-anchor='middle' font-size='21' font-weight='800' fill='var(--ink)'>" . round($pct) . "%</text>";
    $svg .= "<text x='60' y='76' text-anchor='middle' font-size='10' fill='var(--muted)'>" . htmlspecialchars($label, ENT_QUOTES) . "</text>";
    return $svg . "</svg>";
}
// Map SBU-coded aggregates to their labels for charts.
function chart_relabel_sbu($data) {
    $map = lk_options_or('sbu', OPS_SBUS); $out = [];
    foreach ($data as $k => $v) $out[$map[$k] ?? $k] = $v;
    return $out;
}

// ---- Profitability by BOSS / contract number (P7) --------------------------
// Revenue − labour − expenses − subcon, rolling voucher expenses + job closure
// expenses into each BOSS number. Labour is only counted when salary is visible.
function boss_profit($bossId) {
    $seeSal = can_see_salary();
    $jobs = ops_all("SELECT * FROM jobs WHERE boss_id=?", [$bossId]);
    $revenue = 0; $labour = 0; $subcon = 0; $jobExp = 0; $invoiced = 0; $paid = 0; $contingency = 0;
    foreach ($jobs as $j) {
        $office = $j['executing_office_id'] ?? null;
        $revenue += (float)(($j['invoice_amount'] ?? 0) ?: ($j['expected_credit'] ?? 0));
        $invoiced += (float)($j['invoice_amount'] ?? 0);
        $paid += !empty($j['payment_received']) ? (float)($j['payment_amount'] ?? 0) : 0;
        $jSub = (float)($j['subcon_cost'] ?? 0); $subcon += $jSub;
        $jLab = 0;
        if ($seeSal) {
            $sal = $j['inspector_id'] ? (float)ops_val("SELECT salary_ctc + COALESCE(agency_cost,0) FROM inspectors WHERE id=?", [$j['inspector_id']]) : 0;
            $jLab = ($sal ? inspector_daily_cost($sal, null, null, $office) : 0) * job_mandays($j);
            $labour += $jLab;
        }
        $jExp = job_expenses_total($j['id']); $jobExp += $jExp;
        $contingency += round(($jLab + $jExp + $jSub) * office_contingency_pct($office) / 100, 2);
    }
    $vExp = (float)ops_val("SELECT COALESCE(SUM(row_total),0) FROM voucher_entries WHERE boss_id=?", [$bossId]);
    $expenses = $jobExp + $vExp;
    $margin = $revenue - $labour - $expenses - $subcon - $contingency;
    return ['jobs' => count($jobs), 'revenue' => $revenue, 'labour' => $labour, 'expenses' => $expenses,
        'vExp' => $vExp, 'jobExp' => $jobExp, 'subcon' => $subcon, 'contingency' => $contingency, 'margin' => $margin,
        'pct' => $revenue ? round($margin / $revenue * 100, 1) : null, 'invoiced' => $invoiced, 'paid' => $paid];
}
// Per-office overhead % + contingency % (Branch Application Manager edits their
// own office; global managers edit any + the global default).
function ops_office_finance() {
    ops_require(is_admin_level() || can('settings.manage'), 'You cannot edit office finance settings.');
    $pdo = db();
    $global = is_master() || can('users.manage.global') || can('settings.manage');
    $myOffice = current_user()['home_office_id'] ?? null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['global_default'])) {
            ops_require(can('settings.manage') || is_master(), 'Only an admin can set the global default.');
            setting_set('overhead_pct', ($_POST['overhead_pct'] ?? '') === '' ? '' : (string)(float)$_POST['overhead_pct']);
            setting_set('contingency_pct', ($_POST['contingency_pct'] ?? '') === '' ? '' : (string)(float)$_POST['contingency_pct']);
            flash('Global default saved.');
            redirect('/office-finance');
        }
        $oid = (int)($_POST['office_id'] ?? 0);
        if (!$global && $oid !== (int)$myOffice) { flash('You can only edit your own office.', 'error'); redirect('/office-finance'); }
        $oh = ($_POST['overhead_pct'] ?? '') === '' ? null : (float)$_POST['overhead_pct'];
        $cg = ($_POST['contingency_pct'] ?? '') === '' ? null : (float)$_POST['contingency_pct'];
        $pdo->prepare("UPDATE offices SET overhead_pct=?, contingency_pct=? WHERE id=?")->execute([$oh, $cg, $oid]);
        flash('Office finance settings saved.');
        redirect('/office-finance');
    }
    $offices = $global ? ops_all("SELECT * FROM offices ORDER BY is_ahmedabad DESC, name")
                       : ($myOffice ? ops_all("SELECT * FROM offices WHERE id=?", [$myOffice]) : []);
    view('ops/office_finance', ['offices' => $offices, 'global' => $global,
        'defOh' => global_overhead_pct(), 'defCg' => global_contingency_pct(),
        'canGlobal' => can('settings.manage') || is_master()]);
}

function ops_profitability() {
    ops_require(can('data.profitability'), 'You do not have access to profitability figures.');
    $bossId = (int)($_GET['boss'] ?? 0);
    if ($bossId) {
        $boss = ops_one("SELECT bn.*, bp.legal_name client_name, bp.display_name client_disp,
            old.id prev_id, old.boss_number prev_no, nw.id next_id, nw.boss_number next_no
            FROM boss_numbers bn LEFT JOIN business_partners bp ON bp.id=bn.client_id
            LEFT JOIN boss_numbers old ON old.id=bn.supersedes LEFT JOIN boss_numbers nw ON nw.id=bn.superseded_by
            WHERE bn.id=?", [$bossId]);
        if (!$boss) { http_response_code(404); view('notfound'); return; }
        $p = boss_profit($bossId);
        // expense drill-down (voucher lines) — which inspector visited which vendor, cost
        $lines = ops_all("SELECT e.entry_date, e.hours, e.km, e.travel_amount, e.amounts, e.row_total, e.line_no,
            i.name inspector_name, vn.display_name vendor_disp, vn.legal_name vendor_leg
            FROM voucher_entries e LEFT JOIN vouchers v ON v.id=e.voucher_id LEFT JOIN inspectors i ON i.id=v.inspector_id
            LEFT JOIN business_partners vn ON vn.id=e.vendor_id WHERE e.boss_id=? AND e.row_total>0 ORDER BY e.entry_date", [$bossId]);
        // invoice drill-down (jobs)
        $invLines = ops_all("SELECT j.job_code, j.invoice_number, j.invoice_amount, j.invoice_date, j.payment_received, j.payment_amount,
            i.name inspector_name FROM jobs j LEFT JOIN inspectors i ON i.id=j.inspector_id WHERE j.boss_id=? ORDER BY j.id", [$bossId]);
        view('ops/profitability_detail', ['boss' => $boss, 'p' => $p, 'lines' => $lines, 'invLines' => $invLines,
            'headLabels' => expense_head_label_map(), 'seeSal' => can_see_salary()]);
        return;
    }
    // list all BOSS numbers with profitability + renewal hierarchy (prev / next BOSS)
    $rows = [];
    foreach (ops_all("SELECT bn.*, bp.display_name client_disp, bp.legal_name client_name,
                nw.boss_number next_no, old.boss_number prev_no
            FROM boss_numbers bn
            LEFT JOIN business_partners bp ON bp.id=bn.client_id
            LEFT JOIN boss_numbers nw ON nw.id=bn.superseded_by
            LEFT JOIN boss_numbers old ON old.id=bn.supersedes
            ORDER BY bn.boss_number") as $b) {
        $rows[] = $b + ['p' => boss_profit($b['id'])];
    }
    $seeSal = can_see_salary();
    if (wants_csv()) {
        $head = ['Sr No','BOSS number','Client','Status','Created on','Expires on','Renewed into','Jobs','Invoicing done','Expenses booked'];
        if ($seeSal) $head = array_merge($head, ['Salary costing','Profit INR','Profit %']);
        $csv = [$head];
        $sr = 0;
        foreach ($rows as $r) { $p = $r['p']; $sr++;
            $line = [$sr, $r['boss_number'], $r['client_disp'] ?: $r['client_name'], lk_options_or('boss_status', BOSS_STATUS)[$r['status']] ?? $r['status'],
                $r['start_date'], $r['end_date'], $r['next_no'] ?: '', (int)$p['jobs'], (float)$p['invoiced'], (float)$p['expenses']];
            if ($seeSal) $line = array_merge($line, [(float)$p['labour'], (float)$p['margin'], $p['pct'] === null ? '' : $p['pct']]);
            $csv[] = $line;
        }
        csv_download('boss-numbers-' . date('Y-m-d') . '.csv', $csv);
    }
    view('ops/profitability_list', ['rows' => $rows, 'seeSal' => $seeSal]);
}

// P6 — Attendance reconciliation. Upload the HR payroll export (CSV); it is
// parsed IN MEMORY ONLY and NEVER stored — we keep only the comparison result.
function ops_attendance_recon() {
    ops_require(is_coordinator_level(), 'You cannot run attendance reconciliation.');
    $month = preg_match('/^\d{4}-\d{2}$/', $_POST['month'] ?? '') ? $_POST['month'] : (preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m'));
    $result = null; $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_FILES['hr']['tmp_name']) || (int)$_FILES['hr']['error'] !== 0) {
            $error = 'Choose the HR export saved as a .csv file.';
        } else {
            [$hr, $error] = attendance_parse_csv($_FILES['hr']['tmp_name']);
            if (!$error) {
                // our side: distinct present/leave days per inspector for the month (from vouchers)
                $ours = [];
                foreach (ops_all("SELECT i.emp_code, i.name,
                    COUNT(DISTINCT CASE WHEN e.day_type IN ('WORK','OFFICE','WFH','TRAINING') THEN e.entry_date END) present,
                    COUNT(DISTINCT CASE WHEN e.day_type='LEAVE' THEN e.entry_date END) leaves
                    FROM voucher_entries e JOIN vouchers v ON v.id=e.voucher_id JOIN inspectors i ON i.id=v.inspector_id
                    WHERE v.month=? GROUP BY i.emp_code, i.name", [$month]) as $r) {
                    $ours[strtoupper(trim($r['emp_code']))] = $r;
                }
                $rows = []; $keys = array_unique(array_merge(array_keys($hr), array_keys($ours)));
                foreach ($keys as $code) {
                    if ($code === '') continue;
                    $h = $hr[$code] ?? null; $o = $ours[$code] ?? null;
                    $hp = $h ? (float)$h['present'] : null; $hl = $h ? (float)$h['leave'] : null;
                    $op = $o ? (int)$o['present'] : null; $ol = $o ? (int)$o['leaves'] : null;
                    $flag = 'OK';
                    if (!$h) $flag = 'In app only';
                    elseif (!$o) $flag = 'In HR only';
                    elseif ($hp !== (float)$op || $hl !== (float)$ol) $flag = 'MISMATCH';
                    $rows[] = ['code' => $code, 'name' => $h['name'] ?? ($o['name'] ?? ''),
                        'hrP' => $hp, 'hrL' => $hl, 'ourP' => $op, 'ourL' => $ol, 'flag' => $flag];
                }
                // mismatches / one-sided first
                usort($rows, fn($a, $b) => [$a['flag'] === 'OK' ? 1 : 0, $a['name']] <=> [$b['flag'] === 'OK' ? 1 : 0, $b['name']]);
                $result = ['rows' => $rows, 'hrCount' => count($hr), 'oursCount' => count($ours)];
            }
        }
    }
    view('ops/attendance_recon', ['month' => $month, 'result' => $result, 'error' => $error]);
}
// Parse the HR CSV in memory. Returns [ ['CODE'=>['name','present','leave'], ...], errorString ].
function attendance_parse_csv($tmp) {
    $all = [];
    if (($fh = fopen($tmp, 'r')) === false) return [[], 'Could not read the file.'];
    while (($r = fgetcsv($fh)) !== false) $all[] = $r;
    fclose($fh);
    if (!$all) return [[], 'The file is empty.'];
    // find the header row (first row containing an "employee code"/"code" cell)
    $hIdx = -1;
    foreach ($all as $i => $r) {
        foreach ($r as $c) { $cl = strtolower(trim((string)$c));
            if ($cl === 'employee code' || $cl === 'emp code' || $cl === 'employee id' || $cl === 'emp id' || $cl === 'code') { $hIdx = $i; break 2; } }
    }
    if ($hIdx < 0) return [[], 'Could not find an "Employee Code" column — please make sure the HR export has that header, saved as CSV.'];
    $header = array_map(fn($h) => strtolower(trim((string)$h)), $all[$hIdx]);
    $find = function($needles) use ($header) {
        foreach ($header as $i => $h) foreach ($needles as $n) if (strpos($h, $n) !== false) return $i;
        return -1;
    };
    $ci = $find(['employee code','emp code','employee id','emp id','code']);
    $ni = $find(['name']);
    $pi = $find(['present','payable','worked']);
    $li = $find(['leave','lop','absent']);
    if ($pi < 0 && $li < 0) return [[], 'Could not find a "Present days" or "Leave days" column in the HR export.'];
    $out = [];
    for ($i = $hIdx + 1; $i < count($all); $i++) {
        $r = $all[$i];
        $code = strtoupper(trim((string)($r[$ci] ?? '')));
        if ($code === '' || !ctype_alnum(str_replace(['-', '_', ' '], '', $code))) continue;
        $out[$code] = ['name' => trim((string)($r[$ni] ?? '')),
            'present' => $pi >= 0 ? (float)($r[$pi] ?? 0) : 0, 'leave' => $li >= 0 ? (float)($r[$li] ?? 0) : 0];
    }
    if (!$out) return [[], 'No employee rows found under the header.'];
    return [$out, ''];
}

// P8 — renew / carry a BOSS/contract forward (ARC / renewal). Creates a new
// number linked to the old one, carries OPEN jobs forward, keeps the old visible.
function ops_boss_renew() {
    ops_require(can('data.profitability') || is_coordinator_level(), 'You cannot renew a contract.');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/profitability'); }
    $pdo = db();
    $old = ops_one("SELECT * FROM boss_numbers WHERE id=?", [(int)($_POST['old_id'] ?? 0)]);
    if (!$old) { flash('BOSS/contract not found.', 'error'); redirect('/profitability'); }
    if (!empty($old['superseded_by'])) { flash('This contract has already been renewed.', 'error'); redirect('/profitability?boss=' . $old['id']); }
    $newNo = trim($_POST['new_number'] ?? '');
    if ($newNo === '') { flash('Enter the new BOSS / contract number.', 'error'); redirect('/profitability?boss=' . $old['id']); }
    $pdo->prepare("INSERT INTO boss_numbers (client_id,boss_number,start_date,end_date,status,comments,supersedes,carried_at) VALUES (?,?,?,?, 'ACTIVE', ?, ?, ?)")
        ->execute([$old['client_id'], $newNo, $_POST['start_date'] ?? '', $_POST['end_date'] ?? '', 'Carried forward from ' . $old['boss_number'], $old['id'], date('c')]);
    $newId = $pdo->lastInsertId();
    $pdo->prepare("UPDATE boss_numbers SET superseded_by=?, status='CLOSED' WHERE id=?")->execute([$newId, $old['id']]);
    // carry OPEN jobs (and their voucher lines) to the new number
    $jobs = ops_all("SELECT id FROM jobs WHERE boss_id=? AND (closed_flag=0 OR closed_flag IS NULL)", [$old['id']]);
    foreach ($jobs as $j) {
        $pdo->prepare("UPDATE jobs SET boss_id=? WHERE id=?")->execute([$newId, $j['id']]);
        $pdo->prepare("UPDATE voucher_entries SET boss_id=?, file_no=? WHERE job_id=? AND boss_id=?")->execute([$newId, $newNo, $j['id'], $old['id']]);
    }
    flash("Renewed to {$newNo}. " . count($jobs) . " open job(s) carried forward; the old number {$old['boss_number']} stays visible.");
    redirect('/profitability?boss=' . $newId);
}

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
        'client'=> $_GET['client'] ?? '',
    ];
    [$fyFrom, $fyTo] = fy_range($F['fy']);
    // ---- scoped data ----
    [$scopeW, $scopeArgs] = scope_clause('j.executing_office_id', 'j.sbu');
    $jobs = ops_all("SELECT j.*, i.salary_ctc, i.name inspector_name, i.id ins_id, i.trade_id, bp.legal_name client_name, bp.display_name client_disp,
        c.client_id, c.region, o.name office_name, bn.boss_number FROM jobs j LEFT JOIN inspectors i ON i.id=j.inspector_id
        LEFT JOIN calls c ON c.id=j.call_id LEFT JOIN business_partners bp ON bp.id=c.client_id
        LEFT JOIN boss_numbers bn ON bn.id=j.boss_id
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
            if ($F['client'] !== '' && (int)($r['client_id'] ?? 0) !== (int)$F['client']) return false;
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
    $fin = ['credit'=>0,'recv'=>0,'given'=>0,'labour'=>0,'exp'=>0,'subcon'=>0,'profit'=>0,'bySbu'=>[],'byOffice'=>[],'byClient'=>[],'byProject'=>[],'expHead'=>['travel'=>0,'local'=>0,'food'=>0,'lodging'=>0,'misc'=>0],'expHeadExtra'=>[],
        'invoiced'=>0,'paid'=>0,'outstanding'=>0,'overdue'=>0,'creditRecvCnt'=>0,'creditPendCnt'=>0];
    $byInspector=[]; $todayD=date('Y-m-d');
    foreach ($jobs as $j) {
        $p = job_profit($j);
        $fin['credit']+=$p['credit']; $fin['labour']+=$p['labour']; $fin['exp']+=$p['expenses']; $fin['subcon']+=$p['subcon']; $fin['profit']+=$p['profit'];
        if (($j['credit_direction']??'')==='GIVEN') $fin['given']+=$p['credit']; else $fin['recv']+=$p['credit'];
        // invoicing / payment / inter-office credit
        if (!empty($j['invoice_raised'])) { $fin['invoiced']+=(float)$j['invoice_amount']; if (empty($j['payment_received']) && ($j['invoice_due_date']??'') && $j['invoice_due_date']<$todayD) $fin['overdue']++; }
        if (!empty($j['payment_received'])) $fin['paid']+=(float)$j['payment_amount'];
        if (($j['credit_direction']??'')!=='GIVEN' && (($j['executing_office_id']??null))) { if (!empty($j['credit_received'])) $fin['creditRecvCnt']++; else $fin['creditPendCnt']++; }
        $sk=$j['sbu']?:'—'; $fin['bySbu'][$sk]=($fin['bySbu'][$sk]??0)+$p['credit'];
        $ok=$j['office_name']?:'Ahmedabad'; $fin['byOffice'][$ok]=($fin['byOffice'][$ok]??0)+$p['credit'];
        // Revenue = invoiced value where raised, else expected credit. Grouped by
        // customer (top-10 chart) and by project/BOSS (revenue-by-project chart).
        $rev=(float)($j['invoice_amount']??0); if ($rev<=0) $rev=$p['credit'];
        if ($rev!=0){ $ck=$j['client_disp']?:($j['client_name']?:'(no client)'); $fin['byClient'][$ck]=($fin['byClient'][$ck]??0)+$rev;
            $pk=$j['boss_number']?:'(no BOSS)'; $fin['byProject'][$pk]=($fin['byProject'][$pk]??0)+$rev; }
        foreach (ops_all("SELECT * FROM expenses WHERE job_id=?", [$j['id']]) as $x) {
            foreach (['travel','local','food','lodging','misc'] as $h) $fin['expHead'][$h]+=(float)$x[$h];
            foreach (expense_extra_decode($x['extra'] ?? '') as $code=>$amt) $fin['expHeadExtra'][$code]=($fin['expHeadExtra'][$code]??0)+(float)$amt;
        }
        $key=$j['ins_id']?:0;
        if (!isset($byInspector[$key])) $byInspector[$key]=['name'=>$j['inspector_name']?:'(unassigned)','credit'=>0,'cost'=>0,'exp'=>0,'profit'=>0,'jobs'=>0,'mandays'=>0];
        $byInspector[$key]['credit']+=$p['credit'];$byInspector[$key]['cost']+=$p['labour'];$byInspector[$key]['exp']+=$p['expenses'];$byInspector[$key]['profit']+=$p['profit'];$byInspector[$key]['jobs']++;$byInspector[$key]['mandays']+=$p['mandays'];
    }
    $recF=$F['fy']; // reconciliation is by month string; keep simple totals across scope for now
    $fin['reconRecv']=(float)ops_val("SELECT COALESCE(SUM(credit_actual),0) FROM credit_recon WHERE direction='RECEIVED'");
    $fin['reconGiven']=(float)ops_val("SELECT COALESCE(SUM(credit_actual),0) FROM credit_recon WHERE direction='GIVEN'");

    // ---- Loaded cost distributed across each engineer's tagged SBUs (monthly snapshot) ----
    // An inspector tagged to multiple SBUs has their monthly loaded cost split
    // equally across those SBUs, so cost shows where the head-count sits — not
    // only where jobs happened to land. Respects SBU scope + the SBU/inspector filter.
    $fin['costBySbu']=[]; $fin['costBySbuTotal']=0;
    if ($seeSalary) {
        $scopeSbuSet = scope_sbus();
        foreach (ops_all("SELECT id, sbus, sbu, salary_ctc + COALESCE(agency_cost,0) salary_ctc FROM inspectors WHERE status='ACTIVE'") as $ins) {
            if ($F['insp']!=='' && (int)$ins['id']!==(int)$F['insp']) continue;
            $ctc=(float)($ins['salary_ctc'] ?? 0); if ($ctc<=0) continue;
            $loadedMonthly=($ctc/12)*(1+OVERHEAD_PCT/100);
            $sbus=array_values(array_filter(array_map('trim', explode(',', ($ins['sbus'] ?: ($ins['sbu'] ?? ''))))));
            if (!$sbus) $sbus=['—'];
            if ($scopeSbuSet!=='ALL') $sbus=array_values(array_intersect($sbus,$scopeSbuSet));
            if ($F['sbu']!=='') $sbus=in_array($F['sbu'],$sbus,true)?[$F['sbu']]:[];
            if (!$sbus) continue;
            $share=$loadedMonthly/count($sbus);
            foreach ($sbus as $s){ $fin['costBySbu'][$s]=($fin['costBySbu'][$s]??0)+$share; $fin['costBySbuTotal']+=$share; }
        }
    }

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

    // ---- top-10 customers by revenue + revenue by project (BOSS) ----
    arsort($fin['byClient']); $fin['byClientTop'] = array_slice($fin['byClient'], 0, 10, true);
    arsort($fin['byProject']); $fin['byProjectTop'] = array_slice($fin['byProject'], 0, 10, true);

    // ---- filter option lists (scope-limited) ----
    $clientOpts = ops_all("SELECT id, COALESCE(NULLIF(display_name,''), legal_name) name FROM business_partners WHERE is_client=1 ORDER BY name");
    $offOpts = scope_offices()==='ALL' ? offices_list() : array_filter(offices_list(), fn($o)=>in_array((int)$o['id'], scope_offices()));
    $sbuAll = lk_options_or('sbu', OPS_SBUS);
    $sbuOpts = scope_sbus()==='ALL' ? $sbuAll : array_intersect_key($sbuAll, array_flip(scope_sbus()));

    view('ops/reports', [
        'F'=>$F, 'seeFin'=>$seeFin, 'seeSalary'=>$seeSalary, 'tatThresh'=>$tatThresh,
        'op'=>$op, 'fin'=>$fin, 'byInspector'=>$byInspector, 'util'=>$util, 'mdBySbu'=>$mdBySbu,
        'depMd'=>$depMd, 'inspMd'=>$inspMd, 'subMd'=>$subMd, 'certSoon'=>$certSoon, 'byTrade'=>$byTrade,
        'fyOpts'=>fy_options(6), 'offOpts'=>$offOpts, 'sbuOpts'=>$sbuOpts, 'clientOpts'=>$clientOpts,
        'inspOpts'=>inspectors_list(false), 'actType'=>lk_type('activity'),
        'itypeOpts'=>lk_options_or('inspection_type', INSPECTION_TYPES),
        'canOps'=>can('dash.operations'),'canUtil'=>can('dash.utilization'),'canPeople'=>can('dash.people'),
        'hideCerts'=>((current_user()['role'] ?? '')==='BUSINESS_DIRECTOR'),
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
            // Reporting manager (for the org hierarchy + approvals): a system user when
            // one exists, else a manual name/position/email for a manager without a login.
            $reportsTo = ($b['reports_to_id'] ?? '') !== '' ? (int)$b['reports_to_id'] : null;
            if ($reportsTo && $user && $reportsTo === (int)$user['id']) $reportsTo = null; // no self-report
            $rtName = trim($b['reports_to_name'] ?? ''); $rtPos = trim($b['reports_to_position'] ?? ''); $rtEmail = trim($b['reports_to_email'] ?? '');
            $posTitle = trim($b['position_title'] ?? '');
            $uwwd = in_array((string)($b['weekly_working_days'] ?? ''), ['5','5.5','6'], true) ? (float)$b['weekly_working_days'] : 6;
            if ($user) {
                $pdo->prepare("UPDATE users SET username=?,first_name=?,last_name=?,email=?,role=?,is_superuser=?,is_active=?,inspector_id=?,home_office_id=?,scope_offices=?,scope_sbus=?,permissions=?,reports_to_id=?,reports_to_name=?,reports_to_position=?,reports_to_email=?,position_title=?,weekly_working_days=? WHERE id=?")
                    ->execute([$b['username'], $b['first_name'] ?? '', $b['last_name'] ?? '', $b['email'] ?? '', $role, $isSuper, !empty($b['is_active'])?1:0, $insId, $homeOffice, $scopeOffices, $scopeSbus, $perms, $reportsTo, $rtName, $rtPos, $rtEmail, $posTitle, $uwwd, $user['id']]);
                if (!empty($b['password'])) $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($b['password'], PASSWORD_DEFAULT), $user['id']]);
                flash('User saved.');
            } else {
                $hash = password_hash($b['password'] ?: 'changeme123', PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (username,password_hash,first_name,last_name,email,role,is_superuser,is_active,inspector_id,home_office_id,scope_offices,scope_sbus,permissions,reports_to_id,reports_to_name,reports_to_position,reports_to_email,position_title,weekly_working_days)
                    VALUES (?,?,?,?,?,?,?,1,?,?,?,?,?,?,?,?,?,?,?)")->execute([$b['username'], $hash, $b['first_name'] ?? '', $b['last_name'] ?? '', $b['email'] ?? '', $role, $isSuper, $insId, $homeOffice, $scopeOffices, $scopeSbus, $perms, $reportsTo, $rtName, $rtPos, $rtEmail, $posTitle, $uwwd]);
                flash('User created.');
            }
            redirect('/users');
        }
        $mgrs = ops_all("SELECT id, first_name, last_name, username, role, position_title FROM users WHERE is_active=1" . ($user ? " AND id<>" . (int)$user['id'] : "") . " ORDER BY first_name, last_name");
        view('ops/user_form', ['user'=>$user,'inspectors'=>inspectors_list(false),'offices'=>offices_list(),
            'sbuOpts'=>lk_options_or('sbu', OPS_SBUS),'globalMgr'=>$globalMgr,'managers'=>$mgrs,'defaults'=>role_defaults($user['role'] ?? 'COORDINATOR')]); return;
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
        setting_set('fy_revenue_target', (float)($_POST['fy_revenue_target'] ?? 0));
        setting_set('report_escalate_days', max(1, (int)($_POST['report_escalate_days'] ?? 3)));
        setting_set('contract_warn_days', min(365, max(1, (int)($_POST['contract_warn_days'] ?? 30))));
        setting_set('app_name', trim($_POST['app_name'] ?? ''));
        // Working norms & limits (were hard-coded before)
        $cap = (float)($_POST['daily_hours_cap'] ?? 8.5);
        setting_set('daily_hours_cap', ($cap > 0 && $cap <= 24) ? $cap : 8.5);
        $wd = (float)($_POST['default_weekly_days'] ?? 6);
        setting_set('default_weekly_days', in_array($wd, [5.0, 5.5, 6.0], true) ? $wd : 6);
        setting_set('emp_code_prefix', strtoupper(trim($_POST['emp_code_prefix'] ?? '')));
        // Default terms & conditions carried onto every new quote.
        if (isset($_POST['quote_terms'])) setting_set('quote_terms', (string)$_POST['quote_terms']);
        // Display
        setting_set('currency_symbol', trim($_POST['currency_symbol'] ?? '') ?: '₹');
        $df = trim($_POST['date_format'] ?? ''); setting_set('date_format', in_array($df, array_keys(DATE_FORMATS), true) ? $df : 'd M Y');
        // Reporting controls
        $esd = array_values(array_intersect(array_keys(SOURCE_DOC_TYPES), (array)($_POST['expected_source_docs'] ?? [])));
        setting_set('expected_source_docs', implode(',', $esd));
        $hr = array_values(array_intersect(AUDIT_ACTIONS_ALL, (array)($_POST['audit_high_risk'] ?? [])));
        setting_set('audit_high_risk', implode(',', $hr));
        // Theme builder: preset + 4 colours + text colour + font size
        foreach (['c_primary','c_accent','c_bg','c_surface','c_text'] as $k) {
            $v = trim($_POST[$k] ?? '');
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $v)) setting_set($k, $v);
        }
        $fs = (int)($_POST['font_size'] ?? 14); setting_set('font_size', ($fs >= 12 && $fs <= 20) ? $fs : 14);
        setting_set('theme_preset', trim($_POST['theme_preset'] ?? ''));
        // keep brand_color in sync (used as fallback + logo backdrop)
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['c_primary'] ?? '')) setting_set('brand_color', $_POST['c_primary']);
        // Office 365 (SMTP) auto-send settings
        setting_set('smtp_host', trim($_POST['smtp_host'] ?? ''));
        setting_set('smtp_port', (int)($_POST['smtp_port'] ?? 587) ?: 587);
        setting_set('smtp_user', trim($_POST['smtp_user'] ?? ''));
        if (($_POST['smtp_pass'] ?? '') !== '') setting_set('smtp_pass', $_POST['smtp_pass']); // keep existing if left blank
        setting_set('smtp_from', trim($_POST['smtp_from'] ?? ''));
        if (($_POST['clear_logo'] ?? '') === '1') setting_set('logo_data', '');
        // logo upload → stored as a data URI (works without file permissions)
        if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
            $sz = (int)($_FILES['logo']['size'] ?? 0);
            $info = @getimagesize($_FILES['logo']['tmp_name']);
            if ($info && $sz > 0 && $sz <= 600000 && in_array($info['mime'], ['image/png','image/jpeg','image/gif','image/webp','image/svg+xml'], true)) {
                $data = file_get_contents($_FILES['logo']['tmp_name']);
                setting_set('logo_data', 'data:' . $info['mime'] . ';base64,' . base64_encode($data));
            } else {
                flash('Logo must be a PNG/JPG/GIF/WEBP/SVG under 600 KB.', 'error');
            }
        }
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
