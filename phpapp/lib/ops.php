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
const CREDIT_DIRECTIONS = ['RECEIVED'=>'Received (from the other office)','GIVEN'=>'Given (to the other office)'];
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
const DESIGNATIONS = ['INSPECTOR'=>'Inspector','SR_INSPECTOR'=>'Sr. Inspector','LEAD_INSPECTOR'=>'Lead Inspector','EXECUTIVE'=>'Executive','SR_EXECUTIVE'=>'Sr. Executive','ENGINEER'=>'Engineer','SR_ENGINEER'=>'Sr. Engineer','LEAD_ENGINEER'=>'Lead Engineer','COORDINATOR'=>'Coordinator','SR_COORDINATOR'=>'Sr. Coordinator','ASST_MANAGER'=>'Asst. Manager','DY_MANAGER'=>'Deputy Manager','MANAGER'=>'Manager','SR_MANAGER'=>'Sr. Manager','BRANCH_MANAGER'=>'Branch Manager','SBU_HEAD'=>'Business Unit Head','GM'=>'General Manager','DIRECTOR'=>'Director','OTHER'=>'Other'];
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
            supporting_file LONGTEXT, supporting_name VARCHAR(200) DEFAULT '',
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
    // §svc — the TPIA service line (Service Scope Engine code). Chosen on the
    // work order, carried to the job, and used to allocate the report format.
    ensure_column('calls', 'service_code', "VARCHAR(40) DEFAULT ''");
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
    // §WO-9 — a job closed without both site check-ins carries the manager's
    // override on the record, and the lapse feeds the inspector's rating.
    ensure_column('jobs', 'attendance_missing', 'INT DEFAULT 0');
    ensure_column('jobs', 'attendance_override_by', "VARCHAR(150) DEFAULT ''");
    ensure_column('jobs', 'attendance_override_reason', "VARCHAR(400) DEFAULT ''");
    ensure_column('jobs', 'attendance_override_at', "VARCHAR(30) DEFAULT ''");
    // What the client is charged, and which branch holds the order. Both were
    // only ever on the call, so every figure downstream had to go back and look
    // — and a same-office job, which has no inter-office credit, read as worth
    // nothing at all. They belong on the job because a job outlives edits to
    // the call it came from.
    ensure_column('jobs', 'invoice_value', 'DECIMAL(14,2) DEFAULT 0');
    ensure_column('jobs', 'contracting_office_id', 'INT NULL');
    jobs_backfill_money();
    // jobs gain type of inspection (carried from call), custom report frequency,
    // activity and the required deliverables/report formats.
    ensure_column('jobs', 'inspection_type', "VARCHAR(40) DEFAULT ''");
    ensure_column('jobs', 'service_code', "VARCHAR(40) DEFAULT ''");
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
    // contracts / purchase orders carry Business Unit for revenue attribution
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
    // inspector master overhaul: names, trade, multi-Business Unit, multi-skill
    ensure_column('inspectors', 'first_name', "VARCHAR(80) DEFAULT ''");
    ensure_column('inspectors', 'middle_name', "VARCHAR(80) DEFAULT ''");
    ensure_column('inspectors', 'last_name', "VARCHAR(80) DEFAULT ''");
    ensure_column('inspectors', 'trade_id', 'INT NULL');
    ensure_column('inspectors', 'sbus', "VARCHAR(200) DEFAULT ''");
    ensure_column('inspectors', 'skill_ids', "VARCHAR(600) DEFAULT ''");
    ensure_column('inspectors', 'designation', "VARCHAR(40) DEFAULT ''");
    ensure_column('inspectors', 'staff_kind', "VARCHAR(20) DEFAULT 'ASSET'"); // asset / freelancer / subcon
    // Where this team member sits for deputation: a FIELD inspector goes to site
    // and is ranked to the TOP of the allocate list; a COORDinator or OFFICE
    // person can still be deputed but sits below the field inspectors. Every
    // login is tied to one of these records (a login must belong to the team).
    ensure_column('inspectors', 'team_role', "VARCHAR(10) DEFAULT 'FIELD'");
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
    // Contract-number carry-forward chain (renewal / ARC → new number, old kept visible)
    ensure_column('boss_numbers', 'supersedes', 'INT NULL');       // this row continues an older contract
    ensure_column('boss_numbers', 'superseded_by', 'INT NULL');    // this row was renewed into a newer contract
    ensure_column('boss_numbers', 'carried_at', "VARCHAR(30) DEFAULT ''");
    // certifications per inspector, with validity + reminder tracking
    db()->exec("CREATE TABLE IF NOT EXISTS inspector_certs (
        id " . pk_clause() . ", inspector_id INT, name VARCHAR(200), number VARCHAR(80) DEFAULT '',
        issued_date VARCHAR(20) DEFAULT '', valid_to VARCHAR(20) DEFAULT '', status VARCHAR(20) DEFAULT 'VALID',
        last_reminder VARCHAR(20) DEFAULT '', updated_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // §WO-7 — a certificate carries its validity window and the scanned copy, so
    // the hard copy lives with the record and expiry can be watched from both dates.
    ensure_column('inspector_certs', 'is_mandatory', 'INT DEFAULT 0');
    ensure_column('inspector_certs', 'valid_from', "VARCHAR(20) DEFAULT ''");
    ensure_column('inspector_certs', 'file_name',  "VARCHAR(255) DEFAULT ''");
    ensure_column('inspector_certs', 'file_mime',  "VARCHAR(100) DEFAULT ''");
    ensure_column('inspector_certs', 'file_data',  'TEXT NULL');
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

// Seed offices (head office + branches) once.
function ops_seed() {
    $pdo = db();
    // Once the offices have been cleared ON PURPOSE (Settings → Clear records →
    // People, offices & agencies), they stay cleared instead of the 17 starter
    // branches flooding straight back on the next page load.
    $cleared = ''; try { $cleared = (string)setting_get('offices_seeded', ''); } catch (Throwable $e) {}
    if ($cleared === '1') return;
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

// Which office actually executes a call (does the field work). Falls back to the
// inter-branch order office; 0 means it was never set.
function call_exec_office($call) {
    $e = (int)($call['executing_office_id'] ?? 0);
    return $e > 0 ? $e : (int)($call['ibo_office_id'] ?? 0);
}
// Allocating a call — assigning the engineer / raising the job — is the EXECUTING
// office's responsibility. When the contracting and executing offices differ, the
// contracting office can SEE the call (it owns the client and will invoice it) but
// must not allocate it: the office doing the work decides who goes. Head office /
// all-office scope may always allocate, and a call with no executing office set is
// not blocked (nothing to enforce against).
// The pure decision, split out so it can be tested without a live sign-in:
// a coordinator may allocate when they have all-office scope, when the executing
// office is unset, or when their office scope includes the executing office.
function call_alloc_allowed($execOffice, $scopeOffices, $isCoord) {
    if (!$isCoord) return false;
    if ($scopeOffices === 'ALL' || !is_array($scopeOffices) || !$scopeOffices) return true;
    $exec = (int)$execOffice;
    if ($exec <= 0) return true;
    return in_array($exec, array_map('intval', $scopeOffices), true);
}
function call_can_allocate($call) {
    return call_alloc_allowed(call_exec_office($call), scope_offices(), is_coordinator_level());
}
function can_see_salary() { return can('data.salary'); } // salary/cost visibility
// What the branch keeps out of an invoice. Deliberately a separate permission
// from the credit itself: a coordinator has to see the credit to do the job,
// and has no business seeing what the branch earns on it.
function can_see_revenue() { return can('data.revenue') || is_admin_level(); }
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
    // Configurable numbering: when the prefix is a known document type, the
    // admin-controlled scheme (prefix, separator, digits, financial year, start)
    // drives the format. Defaults reproduce the old PREFIX-00001 exactly, so an
    // installation that changes nothing is unaffected.
    if (function_exists('numbering_next') && numbering_has($prefix)) {
        return numbering_next($prefix, $table, $col);
    }
    // FIXED, and the failure was live rather than theoretical. This used to take
    // the string-highest code and cast whatever followed the last '-' to an int.
    // With CALL-E0149 in the table — a real code from an import — that is
    // (int)'E0149' = 0, so the "next" code came back as CALL-00001, which
    // already existed. Every route that raises a call, job or inquiry shared it.
    //
    // Two changes. Only codes of the exact shape PREFIX-<digits> are considered,
    // so a lettered series cannot poison the count; and the result is checked
    // for existence and stepped past rather than assumed free, because two
    // people pressing the button at once is not a rare event.
    $rows = ops_all("SELECT $col FROM $table WHERE $col LIKE ?", ["$prefix-%"]);
    $max = 0;
    foreach ($rows as $r) {
        if (preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', (string)$r[$col], $m))
            $max = max($max, (int)$m[1]);
    }
    for ($try = 1; $try <= 50; $try++) {
        $code = sprintf("%s-%05d", $prefix, $max + $try);
        if (!ops_val("SELECT 1 FROM $table WHERE $col=? LIMIT 1", [$code])) return $code;
    }
    // Refusing beats handing back a duplicate reference somebody will quote back.
    throw new RuntimeException("Could not allocate a free $prefix number. Try again.");
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
function inspectors_list($activeOnly = true) {
    // Field inspectors first (they go to site), then coordinators, then office
    // staff — everyone is deputable, but the person most likely to be sent sits
    // at the top of every allocate list. Ordered in-code as a fallback too.
    //
    // Self-heal the column before reading it. COALESCE(team_role,…) does NOT
    // protect a database that never gained the column — COALESCE substitutes a
    // NULL value, but the column must still exist or the SELECT throws
    // "Unknown column 'team_role'". A live install whose boot() probe never
    // triggered the migration hits exactly that, so add the column here (cheap
    // and idempotent) before it is selected.
    if (function_exists('ensure_column')) ensure_column('inspectors', 'team_role', "VARCHAR(10) DEFAULT 'FIELD'");
    $rows = ops_all("SELECT id, name, emp_code, sbu, salary_ctc, staff_kind, home_office_id,
                            COALESCE(team_role,'FIELD') team_role
                     FROM inspectors" . ($activeOnly ? " WHERE status='ACTIVE'" : "") . " ORDER BY name");
    $rank = ['FIELD' => 0, 'COORD' => 1, 'OFFICE' => 2];
    usort($rows, fn($a, $b) => ($rank[$a['team_role']] ?? 0) <=> ($rank[$b['team_role']] ?? 0)
        ?: strcasecmp((string)$a['name'], (string)$b['name']));
    return $rows;
}

// Create a team-member (person) record and return its id. Every login must
// belong to one of these — the user form creates one inline when a new person
// is added rather than picked. FIELD / COORD / OFFICE drives where they sit in
// the allocate list; all three are deputable.
function team_member_create($name, $teamRole = 'FIELD', $officeId = null, $email = '') {
    $name = trim((string)$name);
    if ($name === '') return 0;
    if (function_exists('ensure_column')) ensure_column('inspectors', 'team_role', "VARCHAR(10) DEFAULT 'FIELD'");
    $tr = in_array($teamRole, ['FIELD', 'COORD', 'OFFICE'], true) ? $teamRole : 'FIELD';
    $data = ['name' => $name, 'staff_kind' => 'ASSET', 'status' => 'ACTIVE',
             'home_office_id' => $officeId ?: null, 'team_role' => $tr,
             'email' => $email, 'created_at' => date('c')];
    if (function_exists('next_emp_code')) $data['emp_code'] = next_emp_code('ASSET');
    $cols = function_exists('existing_columns_only') ? existing_columns_only('inspectors', array_keys($data)) : array_keys($data);
    if (!$cols) return 0;
    $ph = implode(',', array_fill(0, count($cols), '?'));
    db()->prepare("INSERT INTO inspectors (" . implode(',', $cols) . ") VALUES ($ph)")
        ->execute(array_map(fn($c) => $data[$c], $cols));
    return (int)db()->lastInsertId();
}

// ---- Smart inspector suggestion for allocation (#2) ------------------------
// The dates an inspector is already committed to (their open jobs), as a
// {YYYY-MM-DD => true} set, so a clash on the required date is visible.
function inspector_busy_dates($inspectorId, $exceptJobId = 0) {
    $out = [];
    foreach (ops_all("SELECT scheduled_date, inspection_dates FROM jobs
                      WHERE inspector_id=? AND id<>? AND COALESCE(closed_flag,0)=0",
                     [(int)$inspectorId, (int)$exceptJobId]) as $r) {
        if (!empty($r['scheduled_date'])) $out[substr((string)$r['scheduled_date'], 0, 10)] = true;
        foreach (explode(',', (string)($r['inspection_dates'] ?? '')) as $d) {
            $d = trim($d); if ($d !== '') $out[substr($d, 0, 10)] = true;
        }
    }
    // A per-visit booking (job_visits) can commit this engineer to a specific
    // date even when they are not the job's main inspector — count those too, or
    // two jobs could be booked on the same person for the same day.
    try {
        foreach (ops_all("SELECT v.visit_date FROM job_visits v JOIN jobs j ON j.id=v.job_id
                          WHERE v.inspector_id=? AND v.job_id<>? AND COALESCE(j.closed_flag,0)=0",
                         [(int)$inspectorId, (int)$exceptJobId]) as $r) {
            $d = substr(trim((string)$r['visit_date']), 0, 10); if ($d !== '') $out[$d] = true;
        }
    } catch (Throwable $e) { /* job_visits not present on a partial upload */ }
    return $out;
}

// Rank inspectors for a call by the priority ladder:
//   4  the inspector who did the LAST inspection for the same client + vendor
//      (+ contract, + executing office) — continuity is the top preference;
//   2  anyone who has worked for this client before;
//   0  every other active inspector (the full fallback list).
// Availability (a clash with the required dates) is attached but never removes
// anyone from the list — the coordinator always sees everybody and decides.
function inspector_suggestions($call, array $dates = [], $exceptJobId = 0) {
    $clientId = (int)($call['client_id'] ?? 0);
    $vendorId = (int)($call['vendor_id'] ?? 0);
    $contract = trim((string)($call['contract_number'] ?? ''));
    $names = []; foreach (inspectors_list(true) as $i) $names[(int)$i['id']] = $i;

    $score = []; $reason = [];
    $bump = function ($id, $s, $why) use (&$score, &$reason, $names) {
        $id = (int)$id; if ($id <= 0 || !isset($names[$id])) return;
        if (($score[$id] ?? -1) < $s) { $score[$id] = $s; $reason[$id] = $why; }
    };
    foreach ($names as $id => $i) $bump($id, 0, 'Available');
    if ($clientId)
        foreach (ops_all("SELECT DISTINCT j.inspector_id FROM jobs j JOIN calls c ON c.id=j.call_id
                          WHERE c.client_id=? AND j.inspector_id IS NOT NULL", [$clientId]) as $r)
            $bump($r['inspector_id'], 2, 'Has worked for this ' . Tl('client'));
    if ($clientId && $vendorId) {
        $args = [$clientId, $vendorId]; $cn = '';
        if ($contract !== '') { $cn = " AND (c.contract_number=? OR c.contract_number='')"; $args[] = $contract; }
        $last = ops_one("SELECT j.inspector_id FROM jobs j JOIN calls c ON c.id=j.call_id
                         WHERE c.client_id=? AND c.vendor_id=? AND j.inspector_id IS NOT NULL$cn
                         ORDER BY COALESCE(j.scheduled_date,'') DESC, j.id DESC LIMIT 1", $args);
        if ($last && $last['inspector_id']) $bump($last['inspector_id'], 4, 'Did the last inspection for this ' . Tl('client') . ' & ' . Tl('vendor'));
    }
    $dates = array_values(array_filter(array_map(fn($d) => substr(trim((string)$d), 0, 10), $dates)));
    $out = [];
    foreach ($score as $id => $s) {
        $busy = $dates ? inspector_busy_dates($id, $exceptJobId) : [];
        $clash = array_values(array_filter($dates, fn($d) => isset($busy[$d])));
        $out[] = ['id' => $id, 'name' => (string)$names[$id]['name'], 'emp_code' => (string)($names[$id]['emp_code'] ?? ''),
                  'score' => $s, 'reason' => $reason[$id], 'clash' => $clash, 'available' => empty($clash),
                  'team_role' => (string)($names[$id]['team_role'] ?? 'FIELD')];
    }
    // Rank: history score, then who is free, then a FIELD inspector over a
    // coordinator / office person (they go to site), then name.
    $trRank = ['FIELD' => 0, 'COORD' => 1, 'OFFICE' => 2];
    usort($out, fn($a, $b) => ($b['score'] <=> $a['score'])
        ?: (($b['available'] ? 1 : 0) <=> ($a['available'] ? 1 : 0))
        ?: (($trRank[$a['team_role']] ?? 0) <=> ($trRank[$b['team_role']] ?? 0))
        ?: strcasecmp($a['name'], $b['name']));
    return $out;
}

// Other calls for the same client + vendor (+ contract) still waiting to be
// allocated — surfaced so the coordinator can plan them together (#2).
function pending_allocation_siblings($call) {
    $clientId = (int)($call['client_id'] ?? 0);
    $vendorId = (int)($call['vendor_id'] ?? 0);
    $selfId   = (int)($call['id'] ?? 0);
    if (!$clientId) return [];
    return ops_all("SELECT c.id, c.call_code, c.inspection_required_date, c.contract_number
                    FROM calls c
                    WHERE c.client_id=? AND (? = 0 OR c.vendor_id=?) AND c.id<>?
                      AND UPPER(COALESCE(c.status,'')) <> 'CLOSED'
                      AND (SELECT COUNT(*) FROM jobs j WHERE j.call_id=c.id) = 0
                    ORDER BY c.inspection_required_date", [$clientId, $vendorId, $vendorId, $selfId]);
}
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

// ---------------------------------------------------------------------------
//  The contract number is not a thing you choose
//
//  It is agreed once, on the quotation, and from there it is simply carried:
//  quotation → inspection call → deputation. Asking the coordinator to pick it
//  again from a register meant two things went wrong. Either the register was
//  empty, in which case the allocation screen offered an empty dropdown and the
//  number the client actually quoted never reached the figures at all; or the
//  coordinator picked the wrong one, and a month's profit was booked against
//  somebody else's contract.
//
//  So the register fills itself. The number comes down the chain, and the row
//  profitability hangs off is created the first time it is needed, against the
//  right client, with the contract's own dates on it.
// ---------------------------------------------------------------------------
function contract_ref_ensure($clientId, $number, $quotationId = 0) {
    $clientId = (int)$clientId;
    $number = trim((string)$number);
    if (!$clientId || $number === '') return null;
    $ex = ops_val("SELECT id FROM boss_numbers WHERE client_id=? AND boss_number=?", [$clientId, $number]);
    if ($ex) return (int)$ex;
    // The dates come from the contract itself where one is on file, so expiry
    // checks and the renewal chain work on the real cover, not on guesses.
    $start = ''; $end = '';
    $ct = ops_one("SELECT start_date, end_date FROM partner_contracts WHERE partner_id=? AND contract_number=?",
                  [$clientId, $number]);
    if ($ct) { $start = (string)($ct['start_date'] ?? ''); $end = (string)($ct['end_date'] ?? ''); }
    $note = 'Created automatically from ' . Tl('quote') . '/' . Tl('call') . ' ' . date('Y-m-d');
    if ($quotationId) {
        $q = ops_one("SELECT quote_no, rev FROM quotations WHERE id=?", [(int)$quotationId]);
        if ($q) $note = 'Created automatically from ' . $q['quote_no'] . ((int)$q['rev'] ? ' R' . (int)$q['rev'] : '');
    }
    db()->prepare("INSERT INTO boss_numbers (client_id,boss_number,start_date,end_date,status,comments)
                   VALUES (?,?,?,?,?,?)")
        ->execute([$clientId, $number, $start, $end, 'ACTIVE', $note]);
    return (int)db()->lastInsertId();
}

// The contract number for a deputation, taken from the first place that has one:
// what is already on the job, what the call carried, or the quotation it is
// linked to. Nothing here is typed by hand.
function contract_number_for($job, $call, $quotationId = 0) {
    foreach ([($job['contract_number'] ?? ''), ($call['contract_number'] ?? '')] as $n)
        if (trim((string)$n) !== '') return trim((string)$n);
    $qid = (int)($quotationId ?: ($job['quotation_id'] ?? 0) ?: ($call['quotation_id'] ?? 0));
    if ($qid) {
        $n = ops_val("SELECT contract_number FROM quotations WHERE id=?", [$qid]);
        if (trim((string)$n) !== '') return trim((string)$n);
    }
    return '';
}
// ---- §WO-3: a schedule cannot exceed what was ordered, nor run past the
//              contract's validity date. Both are hard stops on save. ----------
// Days the order sold for the line this call draws against. Only day-based units
// (man-day / day) cap the number of visit dates; anything else returns null (no
// day cap). Uses the linked quote line's quantity.
function call_ordered_days($b) {
    $lineId = (int)($b['quote_line_id'] ?? 0);
    if (!$lineId) return null;
    $ln = ops_one("SELECT qty, unit FROM quote_lines WHERE id=?", [$lineId]);
    if (!$ln) return null;
    $unit = strtoupper(trim((string)($ln['unit'] ?? '')));
    if (!in_array($unit, ['MANDAY', 'DAY', 'MANDAYS'], true)) return null;   // not day-bound
    $qty = (float)($ln['qty'] ?? 0);
    return $qty > 0 ? (int)ceil($qty) : null;
}
// The validity (end) date of the contract this call is booked under, '' if none.
function call_contract_end($b) {
    $no  = trim((string)($b['contract_number'] ?? ''));
    $cid = (int)($b['client_id'] ?? 0);
    if ($no === '') return '';
    $end = $cid
        ? ops_val("SELECT end_date FROM partner_contracts WHERE partner_id=? AND contract_number=?", [$cid, $no])
        : ops_val("SELECT end_date FROM partner_contracts WHERE contract_number=? ORDER BY id DESC LIMIT 1", [$no]);
    return trim((string)$end);
}
// Returns an error message if the scheduled dates break either limit, else ''.
function call_schedule_limit_error($b, array $dates) {
    if (!$dates) return '';
    $ordered = call_ordered_days($b);
    if ($ordered !== null && count($dates) > $ordered) {
        return 'This ' . Tl('quote') . ' / order is for ' . $ordered . ' day(s), but ' . count($dates)
             . ' inspection date(s) are scheduled. Reduce the schedule to ' . $ordered . ' day(s) or fewer.';
    }
    $end = call_contract_end($b);
    if ($end !== '') {
        $over = array_values(array_filter($dates, fn($d) => $d > $end));
        if ($over) {
            $show = array_map('fdate', array_slice($over, 0, 5));
            return 'The contract is valid only up to ' . fdate($end) . '. ' . count($over)
                 . ' scheduled date(s) fall after it: ' . implode(', ', $show)
                 . (count($over) > 5 ? ' …' : '') . '. Remove those dates or extend the contract first.';
        }
    }
    return '';
}
function pname($p) { return $p ? ($p['display_name'] ?: $p['legal_name']) : '—'; }
// Currency symbol and date format are settings, not hard-coded (Settings → Display).
// The currency symbol, defended against its own storage.
//
// Reported from the live server: every money figure in the app read "?10,000".
// The symbol is kept in the settings table, and none of the CREATE TABLE
// statements name a charset — so on MySQL each table takes the DATABASE
// default. Where that default is latin1 (still common on cPanel hosts), the
// three bytes of ₹ cannot be represented, MySQL substitutes "?" on the way IN,
// and it reads back as "?" for ever afterwards. The connection being utf8mb4
// does not save you; by then the damage is in the column.
//
// So a stored symbol containing "?" or invalid UTF-8 is not a currency symbol,
// and is ignored in favour of the default. "?10,000" on every screen in the
// system is far worse than quietly falling back to ₹.
function cur_sym() {
    static $s = null;
    if ($s !== null) return $s;
    $v = trim((string)setting_get('currency_symbol', ''));
    if ($v === '' || strpos($v, '?') !== false || !mb_check_encoding($v, 'UTF-8')) $v = '₹';
    return $s = $v;
}
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

// ---- CSV export (dependency-free; works on the plainest shared hosting) -----
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
// Scoped to the user's offices/Business Units so a branch accountant sees only their own.
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
        // What is waiting to be billed is the invoice value, not the inter-office
        // credit — on a same-office job there is no credit and this read zero.
        'unbilled'   => (float)ops_val("SELECT COALESCE(SUM(COALESCE(NULLIF(j.invoice_value,0), j.expected_credit)),0) FROM jobs j WHERE $jw AND j.closed_flag=1 AND j.invoice_raised=0", $ja),
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
                j.payment_received, j.payment_amount, j.invoice_value, j.expected_credit, j.credit_direction, j.credit_received,
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
        $csv = [['Job', T('boss'), 'Client','Office','Amount','Invoice raised','Invoice no','Invoice date','Due date','Payment received','Payment amount','Credit direction','Credit received']];
        foreach ($rows as $r) {
            $csv[] = [$r['job_code'], $r['boss_number'], $r['display_name'] ?: $r['legal_name'], $r['office_name'],
                (float)($r['invoice_amount'] ?: ($r['invoice_value'] ?: $r['expected_credit'])), !empty($r['invoice_raised']) ? 'Yes' : 'No', $r['invoice_number'],
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
// ---------------------------------------------------------------------------
//  Invoice value and revenue are two different numbers
//
//  The owner's definition, and the only one used anywhere:
//
//    INVOICE VALUE  is what the client is charged, as agreed on the purchase
//                   order or the quotation. Once a bill is actually raised it
//                   is the bill; until then it is the agreed figure.
//
//    REVENUE        is what a branch keeps. Where the branch holding the order
//                   and the branch doing the work are the same, that is the
//                   whole invoice value. Where they differ, the holding branch
//                   passes a credit to the executing branch: the holder keeps
//                   invoice − credit, and the executor books the credit.
//
//  Two consequences worth stating, because both were wrong before:
//    · added across every branch, revenue equals the invoice value exactly —
//      no work is counted twice and none disappears;
//    · a same-office job is NOT worth nothing. It used to read zero because
//      only the inter-office credit was looked at, and a same-office job has
//      none.
// ---------------------------------------------------------------------------
// Jobs raised before the two columns existed take their figures from the call
// they came from, once. Anything already filled in is left alone, so this is
// safe to run on every boot.
function jobs_backfill_money() {
    try {
        db()->exec("UPDATE jobs SET invoice_value = COALESCE(
                        (SELECT c.billable_value FROM calls c WHERE c.id = jobs.call_id), 0)
                    WHERE COALESCE(invoice_value,0) = 0 AND call_id IS NOT NULL");
        db()->exec("UPDATE jobs SET contracting_office_id =
                        (SELECT COALESCE(c.contracting_office_id, c.ibo_office_id) FROM calls c WHERE c.id = jobs.call_id)
                    WHERE contracting_office_id IS NULL AND call_id IS NOT NULL");
    } catch (Throwable $e) { /* a fresh database has nothing to carry */ }
}

function job_money($job) {
    // Billed if it has been billed, agreed if it has not. An invoice for zero
    // is not an invoice, so it does not displace the agreed figure.
    $billed  = (float)($job['invoice_amount'] ?? 0);
    $agreed  = (float)($job['invoice_value'] ?? 0);
    if ($agreed <= 0 && !empty($job['call_id']))
        $agreed = (float)ops_val("SELECT billable_value FROM calls WHERE id=?", [(int)$job['call_id']]);
    $invoice = $billed > 0 ? $billed : $agreed;

    $exe = (int)($job['executing_office_id'] ?? 0);
    $hold = (int)($job['contracting_office_id'] ?? 0);
    if (!$hold && !empty($job['call_id']))
        $hold = (int)ops_val("SELECT COALESCE(contracting_office_id, ibo_office_id) FROM calls WHERE id=?", [(int)$job['call_id']]);
    $cross = $exe && $hold && $exe !== $hold;
    // The credit only means anything across offices. On a same-office job any
    // figure sitting in that column is a leftover, not money moving.
    $credit = $cross ? (float)($job['expected_credit'] ?? 0) : 0.0;
    // An older cross-office job may carry the credit and nothing else, because
    // until now only the credit was ever recorded. Subtracting it from an
    // invoice value of nothing would show the holding branch a loss it never
    // made. Where there is no invoice value on record, the credit is the whole
    // of it: the holding branch keeps nothing, which is the honest reading of a
    // job whose client charge was never written down.
    if ($invoice <= 0 && $credit > 0) $invoice = $credit;

    return [
        'invoice' => $invoice, 'billed' => $billed, 'agreed' => $agreed,
        'credit' => $credit, 'cross' => $cross,
        'contracting_office_id' => $hold ?: null, 'executing_office_id' => $exe ?: null,
        // What each side keeps. On a same-office job the one office keeps it
        // all, and the two shares must never be added together.
        'rev_holder'   => $cross ? $invoice - $credit : $invoice,
        'rev_executor' => $cross ? $credit : $invoice,
    ];
}

// What one branch books on this job — or, with no branch named, what the
// company books, which is the invoice value itself.
function job_revenue_for($job, $officeId = null) {
    $m = job_money($job);
    if (!$officeId) return $m['invoice'];
    $officeId = (int)$officeId;
    if (!$m['cross'])
        return ($officeId === (int)$m['executing_office_id'] || $officeId === (int)$m['contracting_office_id'])
             ? $m['invoice'] : 0.0;
    if ($officeId === (int)$m['contracting_office_id']) return $m['rev_holder'];
    if ($officeId === (int)$m['executing_office_id'])   return $m['rev_executor'];
    return 0.0;
}

// Money on one job. With no office named this is the company's view: the whole
// invoice against every cost the job caused. Named a branch, it is that
// branch's share of the revenue — and the costs, which sit with whoever did
// the work.
function job_profit($job, $officeId = null) {
    $mandays = job_mandays($job);
    $office = $job['executing_office_id'] ?? null;
    $m = job_money($job);
    $revenue = job_revenue_for($job, $officeId);
    // A branch only bears the cost of the work it actually did.
    $bearsCost = !$officeId || (int)$officeId === (int)$office;

    // --- the engineer's own time, unloaded ---------------------------------
    // The daily cost used elsewhere already has the overhead baked into it,
    // which is right for a rate but wrong for a statement: it hides the
    // overhead inside "salary" and there is then no line to point at. Here the
    // salary is the salary and the overhead is its own line, and the two add up
    // to exactly what the loaded rate would have given.
    $salary_ctc = $job['inspector_id']
        ? (float)ops_val("SELECT salary_ctc + COALESCE(agency_cost,0) FROM inspectors WHERE id=?", [$job['inspector_id']])
        : 0;
    $wd = working_days_in_month((int)date('Y'), (int)date('n'));
    $dailyBase = ($salary_ctc && $wd > 0) ? ($salary_ctc / 12) / $wd : 0;
    $ohPct = office_overhead_pct($office);
    $labour   = $bearsCost ? round($dailyBase * $mandays, 2) : 0;
    $overhead = $bearsCost ? round($labour * $ohPct / 100, 2) : 0;
    $daily    = $dailyBase * (1 + $ohPct / 100);          // the loaded rate, for display

    // --- everything else the job cost -------------------------------------
    $expenses = $bearsCost ? job_expenses_total($job['id']) : 0;   // booked at closure
    // What the engineer actually claimed on their monthly voucher against this
    // job — travel, lodging, food. Real money out of the branch, and it was
    // missing from this sum entirely.
    $voucher  = $bearsCost ? job_voucher_total($job['id']) : 0;
    $subcon   = $bearsCost ? (float)($job['subcon_cost'] ?? 0) : 0;
    $other    = $bearsCost ? (float)($job['other_cost'] ?? 0) : 0;

    // What the client agreed to pay back on top of the fee — the bills filed
    // against a ticked heading. It is money the branch laid out and then got
    // back, so leaving it in the cost would show a loss the branch never made.
    // Capped at what the job actually cost in expenses, so a mis-keyed bill can
    // never invent profit out of nothing.
    // Guarded the same way boot() guards its modules: if bills.php did not
    // upload, profitability still opens — it simply shows nothing recovered,
    // rather than taking the whole screen down.
    $recovered = ($bearsCost && function_exists('job_recovered_total'))
        ? min(job_recovered_total($job), $expenses + $voucher) : 0;

    $direct = $labour + $overhead + $expenses + $voucher + $subcon + $other - $recovered;
    $contingency = round($direct * office_contingency_pct($office) / 100, 2);
    $cost = round($direct + $contingency, 2);
    $profit = round($revenue - $cost, 2);

    return [
        'mandays' => $mandays, 'daily_cost' => $daily, 'daily_base' => $dailyBase,
        'labour' => $labour, 'overhead' => $overhead, 'overhead_pct' => $ohPct,
        'expenses' => $expenses, 'voucher' => $voucher, 'subcon' => $subcon, 'other' => $other,
        'recovered' => $recovered,
        'chargeable' => function_exists('chargeable_head_labels') ? chargeable_head_labels($job) : [],
        'contingency' => $contingency, 'contingency_pct' => office_contingency_pct($office),
        'cost' => $cost,
        'revenue' => $revenue,
        'invoice' => $m['invoice'], 'billed' => $m['billed'], 'own_credit' => $m['credit'],
        'cross' => $m['cross'],
        // Kept under its old name so nothing that reads it has to change; it is
        // the revenue for whoever this view is about.
        'credit' => $revenue,
        'profit' => $profit,
        'margin' => $revenue > 0 ? round($profit / $revenue * 100, 1) : null,
    ];
}

// What the engineer claimed on their monthly voucher against this job. Kept
// apart from the closure expenses because they are entered by different people
// at different times, and a manager asking "why is this job thin" needs to see
// which of the two it was.
function job_voucher_total($jobId) {
    try { return (float)ops_val("SELECT COALESCE(SUM(row_total),0) FROM voucher_entries WHERE job_id=?", [(int)$jobId]); }
    catch (Throwable $e) { return 0.0; }
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
    $b .= "Reporting: " . (REPORT_FREQ[$j['reporting_frequency']] ?? '') . "   " . T('boss') . ": {$j['boss_number']}\n\n";
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
// §WO-4 — when several engineers cover different days of one job, e-mail each of
// them only THEIR dates, so nobody is told to travel on a day that is not theirs.
// $onlyInspectorIds limits the mail to those just (re)assigned; null mails all.
function send_per_date_assignment_emails($jobId, $onlyInspectorIds = null) {
    $jobId = (int)$jobId;
    $j = job_email_context($jobId);
    if (!$j) return 0;
    $client = $j['client_disp'] ?: $j['client_name'];
    $rows = ops_all("SELECT v.inspector_id, v.visit_date, i.name, i.email
                     FROM job_visits v LEFT JOIN inspectors i ON i.id=v.inspector_id
                     WHERE v.job_id=? AND v.inspector_id IS NOT NULL AND COALESCE(v.status,'')<>'DONE'
                     ORDER BY v.inspector_id, v.visit_date", [$jobId]);
    $byInsp = [];
    foreach ($rows as $r) {
        $iid = (int)$r['inspector_id'];
        if ($onlyInspectorIds !== null && !in_array($iid, $onlyInspectorIds, true)) continue;
        if (!isset($byInsp[$iid])) $byInsp[$iid] = ['name' => $r['name'], 'email' => $r['email'], 'dates' => []];
        $byInsp[$iid]['dates'][] = substr((string)$r['visit_date'], 0, 10);
    }
    $sent = 0;
    foreach ($byInsp as $iid => $info) {
        if (trim((string)($info['email'] ?? '')) === '') continue;
        $dl = implode(', ', array_map(fn($d) => fdate($d), $info['dates']));
        $b  = "Dear {$info['name']},\n\nYou are assigned to this inspection on the following date(s):\n\n";
        $b .= "JOB: {$j['job_code']}" . ($j['call_code'] ? "   (Call {$j['call_code']})" : '') . "\n";
        $b .= "Client: {$client}\nSite / vendor: " . ($j['vendor_name'] ?: '—') . "\n";
        $b .= "Your date(s): {$dl}\n";
        if (!empty($j['folder_link'])) $b .= "Report folder: {$j['folder_link']}\n";
        $b .= "\nRegards,\n" . app_name() . " Coordination";
        ops_mail($info['email'], "Your inspection date(s): {$j['job_code']} — {$client}", $b, coordinator_emails(), 'assignment_dates');
        $sent++;
    }
    return $sent;
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
                        . "The report is still not uploaded. Please follow up with the " . Tl('engineer') . ".\n\n" . app_name();
                    ops_mail($mgrEmail, "OVERDUE report: {$ctx['job_code']} — {$client} ({$overdueDays}d)", $eb, manager_emails(), 'escalation');
                    db()->prepare("UPDATE jobs SET last_escalation=? WHERE id=?")->execute([$today, $j['id']]);
                    $sent++;
                }
            }
        }
    }
    $sent += ops_run_cert_reminders($today);
    $sent += ops_run_po_alerts($today);
    // Housekeeping that has to happen on a clock rather than when somebody
    // opens a screen. Neither sends mail, so neither adds to $sent — they are
    // here because this is the only thing that runs every night.
    if (function_exists('auth_run_maintenance')) auth_run_maintenance($today);
    if (function_exists('iddoc_run_retention')) iddoc_run_retention($today);
    if (function_exists('cmp_run_reminders')) $sent += cmp_run_reminders($today);
    if (function_exists('capa_run_reminders')) $sent += capa_run_reminders($today);
    // Cross-office: e-mail the executing manager when a forwarded call sits
    // unallocated past its target. Idempotent — mailed once per call.
    if (function_exists('tosrm_xo_escalate_scan')) $sent += tosrm_xo_escalate_scan('ALL', $today);
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
            'label' => 'Offices / branches', 'table' => 'offices', 'access' => 'admin', 'order' => 'is_ahmedabad DESC, name',
            // One table, one editor. The Organisation screen owns these: it is
            // the only one that also maintains the tree and the head of each
            // office, so a second form here would quietly write half a record.
            'goto' => '/hierarchy?tab=offices', 'goto_note' => 'in Organisation & people',
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
            // These are people, and people live in one register. Kept as a table
            // so nothing already typed here is lost, but the card sends you to
            // the People tab and anything still in here is offered for moving.
            'goto' => '/hierarchy?tab=people', 'goto_note' => 'people live in Organisation & people',
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
        // NOTE: not 'expense-heads' — that key is already taken by the voucher
        // expense columns (what an engineer claims on a job). These are the
        // office's own running costs. Two different lists, two different keys.
        'office-expense-heads' => [
            // The company's own list of what an office spends money on, and how
            // each one should be shared across its Business Units. Nothing here is fixed
            // in code: add a head, rename it, change how it spreads, retire it.
            // Salary is deliberately not one of these — it comes off the person's
            // record, and having it in both places would count the same rupee twice.
            'label' => 'Office expense heads', 'table' => 'office_expense_heads', 'access' => 'admin',
            'order' => 'is_active DESC, sort_order, label',
            'fields' => [
                ['code','Short code','text',['req'=>1]],
                ['label','Expense head','text',['req'=>1]],
                ['alloc_basis','How it spreads across ' . TP('sbu'),'select',['opts'=>ALLOC_BASES]],
                ['notes','Notes','text',[]],
                ['sort_order','Order on the entry screen','number',[]],
                ['is_active','In use','check',[]],
            ],
            'list' => ['code'=>'Code','label'=>'Expense head','alloc_basis'=>'Spreads by','sort_order'=>'Order','is_active'=>'In use'],
            'list_labels' => ['alloc_basis'=>ALLOC_BASES],
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
                ['sbu','Business Unit','select',['opts'=>OPS_SBUS]],
                ['skills','Skills','text',[]],
                ['email','Email','text',[]],
                ['mobile','Mobile','text',[]],
                ['salary_ctc','Annual CTC (₹)','money',['salary'=>1]],
                ['leave_balance','Leave balance (days)','number',[]],
                ['compoff_balance','Comp-off balance (days)','number',[]],
                ['status','Status','select',['opts'=>['ACTIVE'=>'Active','INACTIVE'=>'Inactive']]],
            ],
            'list' => ['name'=>'Name','emp_code'=>'Emp code','sbu'=>'Business Unit','skills'=>'Skills','status'=>'Status'],
            'list_labels' => ['sbu'=>OPS_SBUS],
        ],
        'subcons' => [
            'label' => 'Sub-contractors', 'table' => 'subcons', 'access' => 'coordinator', 'order' => 'agency',
            'fields' => [
                ['agency','Agency','text',['req'=>1]],
                ['inspector_name',TH('engineer') . ' name','text',[]],
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
            'label' => TP('boss'), 'table' => 'boss_numbers', 'access' => 'coordinator', 'order' => 'boss_number',
            'fields' => [
                ['client_id','Client','ref',['req'=>1,'ref'=>'clients','optfn'=>'clients_list','optlabel'=>'partner']],
                ['boss_number',T('boss'),'text',['req'=>1]],
                ['start_date','Start date','date',[]],
                ['end_date','End date','date',[]],
                ['status','Status','select',['opts'=>BOSS_STATUS]],
                ['comments','Comments','text',[]],
            ],
            'list' => ['boss_number'=>T('boss'),'client_id'=>'Client','start_date'=>'Start','end_date'=>'End','status'=>'Status'],
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
            // A holiday belongs to a branch. Bombay does not shut for a Gujarat
            // holiday, and an end date worked out as though it did is wrong by a
            // day for half the company. Leave the office blank for a national one.
            'label' => 'Public holidays', 'table' => 'holidays', 'access' => 'admin', 'order' => 'hol_date',
            'fields' => [
                ['hol_date','Date','date',['req'=>1]],
                ['name','Holiday name','text',['req'=>1]],
                ['office_id',T('office') . ' — leave blank for every ' . Tl('office'),'ref',['ref'=>'offices','optfn'=>'offices_list','optlabel'=>'name']],
                ['region','Region','select',['opts'=>OPS_REGIONS]],
            ],
            'list' => ['hol_date'=>'Date','name'=>'Holiday','office_id'=>T('office'),'region'=>'Region'],
            'list_labels' => ['region'=>OPS_REGIONS],
            'ref_cols' => ['office_id'=>['offices','name']],
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
                ['ibo_office_id','Contracting office','ref',['ref'=>'offices','optfn'=>'offices_list','optlabel'=>'name']],
                ['client_id','Client','ref',['ref'=>'clients','optfn'=>'clients_list','optlabel'=>'partner']],
                ['boss_number',T('boss'),'text',[]],
                ['direction','Direction','select',['opts'=>CREDIT_DIRECTIONS]],
                ['credit_actual','Actual credit (₹)','money',[]],
                ['notes','Notes','text',[]],
            ],
            'list' => ['month'=>'Month','client_id'=>'Client','boss_number'=>T('boss'),'direction'=>'Direction','credit_actual'=>'Actual'],
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
        'jobs'=>'jobs','job'=>'jobs','job-new'=>'jobs','job-edit'=>'jobs','job-close'=>'jobs','job-unlock'=>'jobs','job-invoice'=>'invoicing','job-bill'=>'invoicing','job-advance'=>'jobs','job-reassign'=>'jobs','job-visit-close'=>'jobs','job-qap-upload'=>'jobs','job-qap'=>'jobs','job-qap-del'=>'jobs','report-approve'=>'jobs','expense-delete'=>'jobs',
        'bill-add'=>'jobs','bill-delete'=>'jobs','bill-file'=>'jobs',
        'invoicing'=>'invoicing',
        'tally'=>'invoicing','tally-export'=>'invoicing','tally-settings'=>'invoicing','tally-undo'=>'invoicing',
        'receivables'=>'invoicing',
        'invoices'=>'invoicing','invoice'=>'invoicing','invoice-new'=>'invoicing','invoice-line-add'=>'invoicing',
        'invoice-line-delete'=>'invoicing','invoice-issue'=>'invoicing','invoice-cancel'=>'invoicing',
        'to-bill'=>'invoicing','receipts'=>'invoicing','receipt'=>'invoicing','receipt-new'=>'invoicing',
        'receipt-allocate'=>'invoicing','receipt-unallocate'=>'invoicing','credit-note-new'=>'invoicing',
        'ledger'=>'invoicing',
        'customer'=>'clients','customer-parent'=>'clients',
        // 'trace' and 'flow-gaps' are deliberately ungated: they show only records
        // the person's own scope already returns, and refusing them by module
        // would hide the very handovers this is meant to expose.
        // 'advisor' likewise: it spans selling, doing and billing, and each of its
        // checks already asks adv_on() whether that module is licensed and visible
        // to this person. One module gate here would either hide the whole screen
        // from someone who owns half of it, or show them findings they cannot act on.
        'profitability'=>'profitability','boss-renew'=>'profitability',
        'candidates'=>'hiring','candidate'=>'hiring','candidate-new'=>'hiring','candidate-edit'=>'hiring','candidate-stage'=>'hiring','candidate-cv'=>'hiring','candidate-client'=>'hiring','candidate-credential'=>'hiring','candidate-erase'=>'hiring','candidate-commercial'=>'hiring','candidate-link-person'=>'hiring',
        'requisitions'=>'hiring','requisition'=>'hiring','requisition-new'=>'hiring','requisition-edit'=>'hiring','recruitment'=>'hiring','recruitment-cc'=>'hiring','req-ai-extract'=>'hiring','recruit-config'=>'hiring',
        'leads'=>'leads','lead'=>'leads','lead-new'=>'leads','lead-edit'=>'leads','lead-move'=>'leads','lead-convert'=>'leads','leads-bulk'=>'leads','lead-delete'=>'leads','lead-contact'=>'leads','lead-files'=>'leads','lead-file'=>'leads','lead-file-delete'=>'leads',
        'opportunities'=>'leads','opportunity'=>'leads','opportunity-new'=>'leads','opportunity-edit'=>'leads',
        'opportunity-move'=>'leads','opportunity-quote'=>'leads','opportunity-from-lead'=>'leads',
        'opportunity-raise-order'=>'leads',
        'pipelines'=>'leads','pipeline'=>'leads','pipeline-new'=>'leads','pipeline-save'=>'leads',
        'pipeline-stage-add'=>'leads','pipeline-stage-delete'=>'leads','pipeline-default'=>'leads',
        'industry'=>'settings','industry-apply'=>'settings',
        'crm-dashboard'=>'crm_reports',
        // The approval queue and its rules ride with the pipeline they guard.
        'approvals'=>'leads','approval-act'=>'leads',
        // The Ads Pro link rides with leads: it exists to produce them.
        // 'licence' and 'sso' are deliberately ungated by module. The licence
        // screen in particular is what somebody reaches when the licence has
        // lapsed and the modules have gone — gating it behind one of them would
        // be a lock with the key inside.
        // 'sso' is deliberately ungated by module: it is an identity screen, and an
        // administrator on a Sales-only licence still has to see who signed in.
        'adspro'=>'leads','adspro-save'=>'leads','adspro-test'=>'leads',
        'adspro-import'=>'leads','adspro-spend'=>'leads','ads-roi'=>'leads',
        'adspro-sync'=>'leads','adspro-backfill'=>'leads',
        'stage-gates'=>'leads','stage-gate-save'=>'leads','stage-gate-delete'=>'leads',
        'inquiries'=>'inquiries','inquiry-new'=>'inquiries','inquiry-edit'=>'inquiries',
        'quotes'=>'quotes','quote'=>'quotes','quote-new'=>'quotes','quote-edit'=>'quotes','quote-revise'=>'quotes','quote-status'=>'quotes','quote-doc'=>'quotes','quote-pdf'=>'quotes','quote-approve'=>'quotes','quote-unapprove'=>'quotes','quote-approval-rules'=>'quotes','quote-contract'=>'quotes','quote-float'=>'quotes','client-quotes'=>'calls','quote-context'=>'calls','quote-client'=>'quotes','quote-files'=>'quotes','quote-file'=>'quotes','quote-file-delete'=>'quotes','quote-unlock'=>'quotes','quote-followup'=>'quotes','quote-external'=>'quotes','quotes-export'=>'quotes','quote-final'=>'quotes','quote-compose'=>'quotes','followup-compose'=>'quotes',
        'attendance-recon'=>'reconcile',
        'availability'=>'jobs','schedule'=>'jobs',
        'documents'=>'idems','document'=>'idems','document-new'=>'idems','document-edit'=>'idems','document-submit'=>'idems','document-finalize'=>'idems','document-delete'=>'idems','document-fill'=>'idems','release-notes'=>'idems','document-ai-review'=>'idems',
        'vendors'=>'idems','vendor-profile'=>'idems','vendor-profile-save'=>'idems',
        'expediting'=>'idems','expediting-projects'=>'idems',
        'report-types'=>'idems','report-type-edit'=>'idems','report-builder'=>'idems','report-field-edit'=>'idems','report-file'=>'idems','irn-rules'=>'idems','audit-log'=>'idems',
        'document-approve'=>'idems','document-vet'=>'idems','approver-map'=>'idems','idems-approval-rules'=>'idems','idems-approval-rule-edit'=>'idems',
        'document-pdf'=>'idems','document-timestamp'=>'idems','document-docx'=>'idems','report-type-preview'=>'idems','report-template-preview'=>'idems',
        'report-templates'=>'idems','report-template-edit'=>'idems','report-template-download'=>'idems','report-form-from-template'=>'idems','report-autoform'=>'idems',
        'endorsements'=>'idems','endorsement'=>'idems','endorsement-new'=>'idems','endorsement-edit'=>'idems','endorsement-submit'=>'idems','endorsement-approve'=>'idems','endorsement-delete'=>'idems','endorsement-file'=>'idems','endorsement-cert'=>'idems',
        'phrase-library'=>'idems','phrase-edit'=>'idems','learning'=>'idems',
        'document-smart'=>'idems','document-release-note'=>'idems','document-review'=>'idems','document-evidence'=>'idems',
        'portal-users'=>'portal','portal-user-toggle'=>'portal','portal-user-reinvite'=>'portal','portal-user-perms'=>'portal',
        'vendor-users'=>'portal','vendor-user-toggle'=>'portal','vendor-user-reinvite'=>'portal','vendor-settings'=>'portal','vendor-share'=>'portal',
        'portal-settings'=>'portal','portal-request'=>'portal',
        'masters'=>'masters','work-norms'=>'masters',
        'office-finance'=>'overheads','cost-run'=>'overheads',
        'sbu-pl'=>'profitability','call-profit'=>'profitability','mis'=>'reports',
        'reports'=>'reports',
        'analytics'=>'reports','analytics-kpis'=>'reports','analytics-kpi-edit'=>'reports','analytics-quality'=>'reports','analytics-drill'=>'reports',
        'analytics-scorecard'=>'reports','analytics-alerts'=>'reports',
        'analytics-export'=>'reports','analytics-review'=>'reports','analytics-snapshot'=>'reports',
        'users'=>'users','user-new'=>'users','user-edit'=>'users','hierarchy'=>'users','org-template'=>'users',
        'user-unlock'=>'users','user-2fa-reset'=>'users','user-retire'=>'users',
        'contract-overrides'=>'calls','contract-override'=>'calls','contract-open'=>'quotes',
        'settings'=>'settings','access'=>'settings','ai-settings'=>'settings','terminology'=>'settings',
        'service-scope'=>'settings','service-formats'=>'settings',
        'deputations'=>'jobs',
        'call-status'=>'calls','call-attrs'=>'calls','call-override'=>'calls',
        'call-clar-new'=>'calls','call-clar-respond'=>'calls','call-clar-status'=>'calls',
        'assign-hold'=>'jobs','assign-accept'=>'jobs','assign-reassign'=>'jobs',
        'assign-reschedule'=>'jobs','assign-cancel'=>'jobs','assign-noshow'=>'jobs',
        'job-ready-seed'=>'jobs','job-ready-set'=>'jobs','job-confirm'=>'jobs','job-confirm-req'=>'jobs',
        'delay-add'=>'jobs','sla-targets'=>'settings','recurring'=>'calls','capacity-outlook'=>'jobs',
        // 'operations' (and the other area homes) are deliberately ungated here —
        // an area aggregates several modules, so its handler enforces an OR of the
        // area's view permissions instead of one module gate that would lock out
        // someone who owns half the area.
        'ops-desk'=>'jobs','comm-add'=>'jobs','assign-issue'=>'jobs','xo-nudge'=>'calls',
        // 'attend-mark' is deliberately UNGATED here — any logged-in staff member
        // with an inspector record self-marks their own day; the handler checks it.
        'dep-status'=>'jobs','dep-check-seed'=>'jobs','dep-check-set'=>'jobs','dep-site-log'=>'jobs',
        'dep-site-log-close'=>'jobs','dep-timesheet'=>'jobs','dep-approval'=>'jobs','dep-approval-status'=>'jobs',
        'dep-manpower-add'=>'jobs','dep-manpower-update'=>'jobs','dep-manpower-del'=>'jobs',
        'issues'=>'ncr','departures'=>'ncr','issue-classify'=>'ncr','departure-new'=>'ncr','departure-status'=>'ncr',
        'dispute-new'=>'ncr','dispute-decide'=>'ncr','issue-extend'=>'ncr','extension-approve'=>'ncr',
        'preflight'=>'settings',
        'trace-thread'=>'settings','trace-thread-remove'=>'settings',
        'trace-audit'=>'settings','trace-audit-remove'=>'settings',
        'evidence-review'=>'idems','evidence-reviewed'=>'idems','checkin-photo'=>'jobs','checkin-settings'=>'idems',
        'data-control'=>'datacontrol','data-check-run'=>'datacontrol','sw-validation-add'=>'datacontrol',
        'failure-add'=>'datacontrol','failure-update'=>'datacontrol','failure-resolve'=>'datacontrol',
        'failure-capa'=>'datacontrol',
        'report-reviews'=>'idems','report-ack'=>'idems',
        'activities'=>'clients','activity-add'=>'clients',
        'packs-save'=>'settings',
        'confidentiality'=>'confidentiality','conf-undertaking-add'=>'confidentiality','conf-nda-add'=>'confidentiality',
        'conf-breach'=>'confidentiality','conf-breach-add'=>'confidentiality','conf-breach-close'=>'confidentiality',
        'site-docs'=>'identity','site-docs-add'=>'identity','site-docs-delete'=>'identity',
        'hold-points'=>'hold-points','hw-point-new'=>'hold-points','hw-point-clear'=>'hold-points',
        'hw-point-waive'=>'hold-points','hw-point-cancel'=>'hold-points','hw-point-reopen'=>'hold-points','hw-point-derive'=>'hold-points',
        'ncr'=>'ncr','ncr-item'=>'ncr','ncr-new'=>'ncr','ncr-contain'=>'ncr','ncr-disposition'=>'ncr',
        'ncr-capa'=>'ncr','ncr-assign'=>'ncr','ncr-close'=>'ncr','ncr-reopen'=>'ncr',
        'capa'=>'capa','capa-item'=>'capa','capa-new'=>'capa','capa-cause'=>'capa','capa-plan'=>'capa',
        'capa-done'=>'capa','capa-verify'=>'capa','capa-close'=>'capa','capa-escalate'=>'capa',
        'capa-settings'=>'capa','capa-from-complaint'=>'capa',
        'capa-action-add'=>'capa','capa-action-done'=>'capa','capa-action-cancel'=>'capa','capa-action-reopen'=>'capa',
        'internal-audits'=>'audits','internal-audit'=>'audits','internal-audit-new'=>'audits',
        'audit-record'=>'audits','audit-finding-add'=>'audits','audit-finding-delete'=>'audits',
        'audit-finding-capa'=>'audits','audit-close'=>'audits','audit-settings'=>'audits',
        'management-reviews'=>'audits','management-review'=>'audits','management-review-new'=>'audits',
        'review-refresh'=>'audits','review-header'=>'audits','review-input'=>'audits',
        'review-action-add'=>'audits','review-action-done'=>'audits','review-complete'=>'audits',
        'complaints'=>'complaints','complaint'=>'complaints','complaint-new'=>'complaints',
        'complaint-ack'=>'complaints','complaint-validity'=>'complaints','complaint-investigate'=>'complaints',
        'complaint-decide'=>'complaints','complaint-notify'=>'complaints','complaint-capa'=>'complaints',
        'complaint-close'=>'complaints','complaint-reopen'=>'complaints','complaint-settings'=>'complaints',
        'identity'=>'identity','iddoc-add'=>'identity','iddoc-file'=>'identity',
        'iddoc-reveal'=>'identity','iddoc-share'=>'identity','iddoc-redact'=>'identity',
        'iddoc-retention'=>'identity',
        'impartiality'=>'impartiality','imp-type'=>'impartiality','imp-declare'=>'impartiality',
        'imp-threat-add'=>'impartiality','imp-threat-decide'=>'impartiality',
        'competence'=>'competence','auth-add'=>'competence','auth-status'=>'competence',
        'auth-enforce'=>'competence','witness-add'=>'competence',
        'equipment'=>'equipment','equip-new'=>'equipment','equip-edit'=>'equipment',
        'equip-cal-add'=>'equipment','equip-cal-del'=>'equipment','equip-cert'=>'equipment',
        'report-equip-add'=>'equipment','report-equip-del'=>'equipment',
        'reset-data'=>'settings',
        'partner-import'=>'clients','partner-template'=>'clients','duplicates'=>'clients',
    ];
    $mod = $map[$base] ?? null;
    if ($mod && !can("mod.$mod.view")) {
        // Two different "no"s, and they need two different sentences. Telling
        // somebody to ask their administrator for a module the company has not
        // bought sends them on an errand that cannot succeed.
        $owner = function_exists('licence_owner') ? licence_owner($mod) : null;
        ops_require(false, ($owner && !licence_enabled($owner))
            ? 'The ' . PRODUCT_MODULES[$owner][0] . ' module is not switched on for this installation.'
            : 'You don’t have access to the ' . access_module_label($mod) . ' module. Ask your administrator.');
    }
    // Registers only an accredited body needs. With every accreditation pack
    // (inspection, laboratory) switched off they are hidden from the menu; refuse
    // them here too, so a bookmarked or typed URL cannot reach a screen the
    // installation has turned off. Universal registers (complaints, identity,
    // confidentiality, the client portal) are deliberately NOT in this list.
    if ($mod && function_exists('accredited_pack_on') && !accredited_pack_on()
        && in_array($mod, ['equipment','competence','impartiality','ncr','capa','audits','datacontrol'], true)) {
        ops_require(false, 'This register is part of an accreditation pack (Inspection or Laboratory), and none is '
            . 'switched on for this installation. An administrator can switch one on under Settings → Industry packs.');
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
        // Remember which modules existed at this moment, so a module added in a
        // later version is known to be new rather than deliberately untieked.
        stamp_modules_at_save();
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
    if (preg_match('#^m/([a-z-]+)(?:/(new|edit|delete|cert-file))?$#', $route, $mm)) {
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
        case $route === 'jobs' || $route === 'job-new' || $route === 'job-edit' || $route === 'job' || $route === 'job-close' || $route === 'job-invoice' || $route === 'job-bill' || $route === 'job-advance' || $route === 'job-reassign' || $route === 'job-visit-close' || $route === 'expense-delete':
            ops_jobs($route, $method); return true;
        // §R1-D — QAP documents on a job (attach only, never parsed).
        case $route === 'job-qap-upload':   return ops_job_qap_upload($method);
        case $route === 'job-qap':          return ops_job_qap_download();
        case $route === 'job-qap-del':      return ops_job_qap_del($method);
        // Bills backing the expenses the client is being charged for.
        case $route === 'bill-add' || $route === 'bill-delete' || $route === 'bill-file':
            return ops_job_bill($route, $method);
        case $route === 'candidates' || $route === 'candidate-new' || $route === 'candidate-edit' || $route === 'candidate' || $route === 'candidate-stage' || $route === 'candidate-cv' || $route === 'candidate-client' || $route === 'candidate-credential' || $route === 'candidate-commercial' || $route === 'candidate-link-person':
            ops_candidates($route, $method); return true;
        case $route === 'inquiries' || $route === 'inquiry-new' || $route === 'inquiry-edit':
            ops_crm_inquiries($route, $method); return true;
        case $route === 'quotes' || $route === 'quote' || $route === 'quote-new' || $route === 'quote-edit' || $route === 'quote-revise' || $route === 'quote-status' || $route === 'quote-doc' || $route === 'quote-pdf' || $route === 'quote-approve' || $route === 'quote-unapprove' || $route === 'quote-contract' || $route === 'quote-float' || $route === 'quote-client' || $route === 'partner-address' || $route === 'quote-files' || $route === 'quote-file' || $route === 'quote-file-delete' || $route === 'quote-unlock' || $route === 'quote-followup' || $route === 'quote-external' || $route === 'quotes-export' || $route === 'quote-final' || $route === 'quote-compose' || $route === 'followup-compose':
            ops_crm_quotes($route, $method); return true;
        case $route === 'crm-templates' || $route === 'crm-template-new' || $route === 'crm-template-edit' || $route === 'crm-template-delete' || $route === 'crm-template-download' || $route === 'crm-signature' || $route === 'crm-letterhead':
            ops_crm_templates($route, $method); return true;
        case $route === 'quote-approval-rules' || $route === 'quote-approval-rule-new' || $route === 'quote-approval-rule-edit' || $route === 'quote-approval-rule-delete':
            ops_crm_approval_rules($route, $method); return true;
        case $route === 'crm-reports':
            ops_crm_reports(); return true;
        case $route === 'requisitions' || $route === 'requisition-new' || $route === 'requisition-edit' || $route === 'requisition':
            ops_requisitions($route, $method); return true;
        case $route === 'recruitment':
            return ops_recruitment_home($method);
        case $route === 'recruitment-cc':
            return ops_recruitment_cc($method);
        case $route === 'recruit-config':
            ops_require(is_admin_level(), 'Only an administrator can change the engagement mode.');
            if ($method === 'POST' && function_exists('setting_set')) {
                $m = strtoupper((string)($_POST['engagement_mode'] ?? 'BOTH'));
                if (!isset(RECRUIT_ENGAGEMENT_MODES[$m])) $m = 'BOTH';
                setting_set('recruit_engagement_mode', $m);
                flash('Engagement mode set to ' . RECRUIT_ENGAGEMENT_MODES[$m] . '.');
            }
            redirect('/recruitment'); return true;
        case $route === 'req-ai-extract':
            ops_require(is_coordinator_level());
            if ($method === 'POST' && function_exists('recruit_ai_extract')) { recruit_ai_extract(); return true; }
            header('Content-Type: application/json'); echo json_encode(['ok' => false, 'error' => 'POST only']); return true;
        case $route === 'vouchers' || $route === 'voucher' || $route === 'voucher-generate' || $route === 'voucher-entry' || $route === 'voucher-save' || $route === 'voucher-header' || $route === 'voucher-status' || $route === 'voucher-print' || $route === 'voucher-file' || $route === 'voucher-csv':
            ops_vouchers($route, $method); return true;
        case $route === 'my-jobs':
            ops_my_jobs(); return true;
        case $route === 'reports':
            ops_reports(); return true;
        case strncmp($route, 'analytics', 9) === 0:
            return ops_tapi($route, $method);
        case $route === 'profitability':
            ops_profitability(); return true;
        case $route === 'invoicing':
            ops_invoicing(); return true;
        case $route === 'receivables':
            ops_receivables(); return true;
        case $route === 'tally' || $route === 'tally-export' || $route === 'tally-settings' || $route === 'tally-undo':
            return ops_tally($route, $method);
        case $route === 'seed-demo':
            ops_require(is_master(), 'Only the Master Admin can load demo data.');
            if ($method === 'POST') {
                // "Load anyway" is offered when the flag and the data disagree.
                $res = seed_demo(!empty($_POST['force']));
                if (!empty($res['skipped'])) flash('Demo data is already loaded. To refresh it with the latest sample records (e.g. agencies, requisitions), click "Remove demo data" first, then "Load demo data" again.', 'warning');
                elseif (!empty($res['error'])) flash('Could not load demo data: ' . $res['error'], 'error');
                else {
                    $x = $res['counts'];
                    flash("Demo data loaded — {$x['offices']} offices, {$x['users']} users, {$x['inspectors']} inspectors, {$x['partners']} clients/vendors, {$x['boss']} " . Tlp('boss') . ", {$x['calls']} calls, {$x['jobs']} jobs, {$x['vouchers']} vouchers, plus " . ($x['edge_cases'] ?? 0) . " generated edge-case records. Log in as any demo user (e.g. director, account, insp.ravi) with password demo12345.");
                    // The core loaded but a register did not. Say which, rather
                    // than leaving somebody to notice an empty screen later.
                    foreach ($res['failed'] ?? [] as $f)
                        flash('The operations demo loaded, but the extra registers did not: ' . $f, 'error');
                }
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
        case $route === 'trace-thread':
            ops_require(is_master(), 'Only the Master Admin can build the traceability thread.');
            if ($method === 'POST' && function_exists('trace_seed')) {
                $res = trace_seed(true);   // always rebuild fresh, so the run is repeatable
                if (empty($res['ok'])) flash('The traceability thread stopped: ' . ($res['error'] ?? 'unknown'), 'error');
                view('ops/trace_thread', ['res' => $res]); return true;
            }
            redirect('/settings'); return true;
        case $route === 'trace-thread-remove':
            ops_require(is_master(), 'Only the Master Admin can remove the traceability thread.');
            if ($method === 'POST' && function_exists('trace_seed_remove')) {
                $r = trace_seed_remove();
                flash('Traceability thread removed — ' . (int)($r['deleted'] ?? 0) . ' records deleted.');
            }
            redirect('/settings'); return true;
        case $route === 'trace-audit':
            ops_require(is_master(), 'Only the Master Admin can build the audit thread.');
            if ($method === 'POST' && function_exists('trace_audit_seed')) {
                $res = trace_audit_seed(true);
                if (empty($res['ok'])) flash('The audit thread stopped: ' . ($res['error'] ?? 'unknown'), 'error');
                view('ops/trace_thread', ['res' => $res,
                    'title' => 'Audit & compliance thread',
                    'intro' => 'One compliance chain, and the accreditation gates — checked link by link and gate by gate.',
                    'bandText' => 'A corrective action ran from an audit finding, a nonconformity and a complaint; and every accreditation gate below was fired on purpose and blocked as it should.',
                    'removeNote' => 'Takes out the audit, finding, nonconformity, complaint, corrective actions, review and the test engineer/instrument it created — nothing else.',
                    'removeAction' => '/trace-audit-remove',
                    'links' => [
                        'audit'      => ['/internal-audit?id=',    'Internal audit'],
                        'ncr'        => ['/nonconformity?id=',     'Nonconformity'],
                        'complaint'  => ['/complaint?id=',         'Complaint'],
                        'capa_audit' => ['/capa?id=',              'Corrective action'],
                        'review'     => ['/management-review?id=', 'Management review'],
                    ]]);
                return true;
            }
            redirect('/settings'); return true;
        case $route === 'trace-audit-remove':
            ops_require(is_master(), 'Only the Master Admin can remove the audit thread.');
            if ($method === 'POST' && function_exists('trace_audit_remove')) {
                $r = trace_audit_remove();
                flash('Audit thread removed — ' . (int)($r['deleted'] ?? 0) . ' records deleted.');
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
        case $route === 'two-factor':
            ops_two_factor($method); return true;
        case $route === 'compliance':
            ops_compliance($method); return true;
        case $route === 'incidents' || $route === 'incident' || $route === 'incident-new' || $route === 'incident-edit'
             || $route === 'incident-report':
            ops_incidents($route, $method); return true;
        case $route === 'data-requests' || $route === 'person-data' || $route === 'person-erase'
             || $route === 'candidate-erase':
            ops_data_requests($route, $method); return true;
        case $route === 'consents' || $route === 'consent-add' || $route === 'consent-withdraw':
            ops_consents($route, $method); return true;
        case $route === 'super-admin' || $route === 'control-panel':
            return ops_super_admin($method);
        case $route === 'tenants' || $route === 'tenant-enable' || $route === 'tenant-add'
             || $route === 'tenant-status' || $route === 'tenant-remove'
             || $route === 'cpanel-save' || $route === 'cpanel-test':
            ops_tenants($route, $method); return true;
        case $route === 'billing' || $route === 'billing-config' || $route === 'billing-order'
             || $route === 'billing-verify':
            ops_billing($route, $method); return true;
        case $route === 'issue-licence' || $route === 'issue-licence-new' || $route === 'issue-licence-reissue':
            ops_licence_issue($route, $method); return true;
        case $route === 'privacy':
            view('ops/privacy', ['notice'=>privacy_notice_text(), 'g'=>grievance_officer()]); return true;
        case $route === 'user-unlock':
            ops_user_unlock($method); return true;
        case $route === 'user-retire':
            ops_user_retire($method); return true;
        case $route === 'user-2fa-reset':
            ops_user_twofa_reset($method); return true;
        case $route === 'complaints' || $route === 'complaint' || strncmp($route, 'complaint-', 10) === 0:
            return ops_complaints($route, $method);
        case $route === 'evidence-review' || $route === 'evidence-reviewed'
             || $route === 'site-checkin' || $route === 'checkin-photo' || $route === 'checkin-settings'
             || $route === 'punch':
            return ops_trust($route, $method);
        case $route === 'portal-users' || $route === 'portal-user-toggle'
             || $route === 'portal-user-reinvite' || $route === 'portal-settings'
             || $route === 'portal-request' || $route === 'portal-user-perms':
            return ops_portal_admin($route, $method);
        case $route === 'vendor-users' || $route === 'vendor-user-toggle'
             || $route === 'vendor-user-reinvite' || $route === 'vendor-settings'
             || $route === 'vendor-share':
            return ops_cvp_vendor_admin($route, $method);
        case $route === 'data-control' || $route === 'data-check-run'
             || $route === 'sw-validation-add' || strncmp($route, 'failure-', 8) === 0:
            return ops_datacontrol($route, $method);
        case $route === 'report-reviews' || $route === 'report-ack':
            return ops_report_reviews($route, $method);
        case $route === 'packs-save':
            ops_require(is_master() || can('settings.manage'), 'Only an administrator can change industry packs.');
            if ($method === 'POST') {
                packs_save(implode(',', (array)($_POST['packs'] ?? [])));
                flash(packs_enabled(true)
                    ? 'Industry packs saved: ' . implode(', ', packs_enabled()) . '. Their rules are live from the next screen.'
                    : 'All industry packs are off. The registers stay; no specialist gate will fire.');
            }
            redirect('/settings');
        // Not gated by a module: choosing which columns you see is a preference
        // on whatever register you were already allowed to open.
        case $route === 'dt-columns':
            return ops_dt_columns($route, $method);
        // Not gated by a module either: it searches only the registers the
        // person can already open, and queries nothing else.
        case $route === 'search':
            return ops_search($route, $method);
        // Everything about one customer, on one screen.
        case $route === 'customer' || $route === 'customer-parent':
            return ops_customer360($route, $method);
        // The thread from enquiry to payment, and where it is cut.
        case $route === 'trace' || $route === 'flow-gaps':
            return ops_chain($route, $method);
        // What to fix: the problems, what they cost, and the steps that close them.
        case $route === 'advisor':
            return ops_advisor($route, $method);
        // The books: invoices, money in, credit notes, the customer ledger.
        case in_array($route, ['invoices','invoice','invoice-new','invoice-line-add','invoice-line-delete',
                               'invoice-issue','invoice-cancel','to-bill','receipts','receipt','receipt-new',
                               'receipt-allocate','receipt-unallocate','credit-note-new','ledger'], true):
            return ops_books($route, $method);
        case $route === 'pipelines' || strncmp($route, 'pipeline', 8) === 0:
            return ops_pipelines($route, $method);
        case $route === 'industry' || $route === 'industry-apply':
            return ops_industry($route, $method);
        // Advertising: what it cost, and what actually came back.
        case $route === 'adspro' || strncmp($route, 'adspro-', 7) === 0:
            return ops_adspro($route, $method);
        case $route === 'ads-roi':
            return ops_adsroi($route, $method);
        // Who arrived here from a sibling application, and who was turned away.
        case $route === 'licence' || $route === 'licence-save' || $route === 'licence-check' || $route === 'licence-pubkey':
            return ops_licence($route, $method);
        case $route === 'vendor' || $route === 'signing-setup':
            ops_vendor($route, $method); return true;
        case $route === 'agreement':
            ops_agreement($route, $method); return true;
        case $route === 'company-profile':
            ops_company_profile($route, $method); return true;
        case $route === 'books-bridge' || $route === 'books-bridge-save' || $route === 'books-bridge-drain':
            return ops_books_bridge($route, $method);
        case $route === 'cforms' || $route === 'cform-def-save' || $route === 'cform-def-del':
            return ops_customforms($route, $method);
        case $route === 'cform' || $route === 'cform-new' || $route === 'cform-edit' || $route === 'cform-view'
             || $route === 'cform-save' || $route === 'cform-del':
            return ops_cform_records($route, $method);
        case $route === 'partner-geo' && $method === 'POST':
            return geofence_save_party($route, $method);
        case $route === 'job-geo' && $method === 'POST':
            return geofence_save_job($route, $method);
        case $route === 'timesheet':
            return ops_timesheet($route, $method);
        case $route === 'ratings' || $route === 'ratings-config':
            return ops_ratings($route, $method);
        case $route === 'inspector-profile':
            return ops_inspector_profile($route, $method);
        case $route === 'sso':
            return ops_sso($route, $method);
        // Deals held at a stage until somebody with the authority agrees.
        case $route === 'approvals' || $route === 'approval-act':
            return ops_approvals($route, $method);
        case strncmp($route, 'stage-gate', 10) === 0:
            return ops_stage_gates($route, $method);
        case $route === 'crm-dashboard':
            return ops_crmdash($route, $method);
        case $route === 'opportunities' || strncmp($route, 'opportunity', 11) === 0:
            return ops_opportunities($route, $method);
        case $route === 'leads' || strncmp($route, 'lead', 4) === 0:
            return ops_leads($route, $method);
        case $route === 'activities' || $route === 'activity-add':
            return ops_activity($route, $method);
        case $route === 'confidentiality' || strncmp($route, 'conf-', 5) === 0:
            return ops_confidentiality($route, $method);
        case $route === 'site-docs' || $route === 'site-docs-add' || $route === 'site-docs-delete':
            return ops_sitedocs($route, $method);
        case $route === 'ncr' || strncmp($route, 'ncr-', 4) === 0:
            return ops_ncr($route, $method);
        case $route === 'hold-points' || strncmp($route, 'hw-point', 8) === 0:
            return ops_hwpoints($route, $method);
        case $route === 'capa' || strncmp($route, 'capa-', 5) === 0:
            return ops_capa($route, $method);
        case $route === 'management-reviews' || $route === 'management-review'
             || $route === 'management-review-new' || strncmp($route, 'review-', 7) === 0:
            return ops_reviews($route, $method);
        // 'audit-log' is IDEMS's compliance trail and predates this module. It
        // is handled further down, so it has to be let past here — a prefix
        // match that quietly swallows an existing screen is a nasty way to
        // break something that was working.
        case ($route === 'internal-audits' || $route === 'internal-audit'
              || $route === 'internal-audit-new'
              || (strncmp($route, 'audit-', 6) === 0 && $route !== 'audit-log')):
            return ops_audits($route, $method);
        case $route === 'identity' || strncmp($route, 'iddoc-', 6) === 0:
            return ops_identity($route, $method);
        case $route === 'impartiality' || strncmp($route, 'imp-', 4) === 0:
            return ops_impartiality($route, $method);
        case $route === 'competence' || strncmp($route, 'auth-', 5) === 0 || $route === 'witness-add':
            return ops_competence($route, $method);
        case strncmp($route, 'equip', 5) === 0 || $route === 'report-equip-add' || $route === 'report-equip-del':
            return ops_equipment($route, $method);
        case strncmp($route, 'sample', 6) === 0:
            return ops_samples($route, $method);
        case strncmp($route, 'method', 6) === 0:
            return ops_methods($route, $method);
        case strncmp($route, 'drule', 5) === 0:
            return ops_drules($route, $method);
        case strncmp($route, 'cdoc', 4) === 0:
            return ops_cdocs($route, $method);
        case strncmp($route, 'risk', 4) === 0:
            return ops_risks($route, $method);
        case strncmp($route, 'retention', 9) === 0:
            return ops_retention($route, $method);
        case strncmp($route, 'disclosure', 10) === 0:
            return ops_disclosure($route, $method);
        case strncmp($route, 'satisfaction', 12) === 0:
            return ops_satisfaction($route, $method);
        case $route === 'preflight':
            ops_preflight(); return true;
        case $route === 'settings':
            ops_settings($method); return true;
        case $route === 'access':
            ops_access($method); return true;
        case $route === 'ai-settings':
            ops_ai_settings($method); return true;
        case $route === 'terminology':
            ops_terminology($method); return true;
        case $route === 'service-scope':
            return ops_services($route, $method);
        case $route === 'service-formats':
            return svc_report_admin($method);
        case $route === 'deputations':
            return ops_pdso($route, $method);
        case in_array($route, ['call-status','call-attrs','call-override','call-clar-new','call-clar-respond','call-clar-status'], true):
            return ops_tosrm_action($route, $method);
        case in_array($route, ['assign-hold','assign-accept','assign-reassign','assign-reschedule','assign-cancel','assign-noshow'], true):
            return ops_tosrm_job_action($route, $method);
        case in_array($route, ['job-ready-seed','job-ready-set','job-confirm','job-confirm-req'], true):
            return ops_tosrm_ready_action($route, $method);
        case $route === 'delay-add':
            return ops_tosrm_delay_action($route, $method);
        case in_array($route, ['sla-targets','recurring','capacity-outlook'], true):
            return ops_tosrm_admin($route, $method);
        case $route === 'attend-mark':
            return ops_attend_action($route, $method);
        case $route === 'operations':
            return ops_operations_home($method);
        case in_array($route, ['sales','quality','reporting','money','insights','directory','admin'], true):
            return ops_area_home($route, $method);
        case $route === 'ops-desk':
            return ops_tosrm_desk($method);
        case in_array($route, ['comm-add','assign-issue','xo-nudge'], true):
            return ops_tosrm_comm_action($route, $method);
        case in_array($route, ['dep-status','dep-check-seed','dep-check-set','dep-site-log','dep-site-log-close','dep-timesheet','dep-approval','dep-approval-status'], true):
            return ops_pdso_action($route, $method);
        case in_array($route, ['dep-manpower-add','dep-manpower-update','dep-manpower-del'], true):
            return ops_pdso_manpower($route, $method);
        case in_array($route, ['issues','departures','issue-classify','departure-new','departure-status','dispute-new','dispute-decide','issue-extend','extension-approve'], true):
            return ops_ncdca($route, $method);
        // Merged screens — one heading, one tab per module underneath.
        case $route === 'approval-rules':
            return ops_approval_rules($method);
        case $route === 'templates':
            return ops_templates($method);
        case $route === 'availability':
            ops_inspector_availability($method); return true;
        case $route === 'schedule':
            return ops_schedule_board($method);
        case $route === 'hierarchy':
            return ops_hierarchy_screen($method);
        case $route === 'org-template':
            return ops_org_template();
        case $route === 'reset-data':
            ops_reset_data($method); return true;
        case $route === 'job-unlock':
            ops_job_unlock($method); return true;
        case $route === 'mis':
            ops_mis($method); return true;
        case $route === 'cost-run':
            ops_cost_run($method); return true;
        case $route === 'call-profit':
            // What each inspection made, for the branch manager and the managers
            // under them. Same figures as every other screen — one function.
            ops_call_profit(); return true;
        case $route === 'sbu-pl':
            ops_sbu_pl(); return true;
        case $route === 'duplicates':
            ops_dedupe($method); return true;
        case $route === 'partner-import':
            ops_partner_import($method); return true;
        case $route === 'partner-template':
            return ops_partner_template();
        case $route === 'contract-overrides' || $route === 'contract-override':
            return ops_contract_overrides($route, $method);
        case $route === 'contract-open':
            return ops_contract_open($route, $method);
        case $route === 'work-norms':
            ops_work_norms($method); return true;
        case in_array($route, ['documents','document','document-new','document-edit','document-submit','document-finalize','document-delete','release-notes','document-ai-review'], true):
            return ops_idems_documents($route, $method);
        case in_array($route, ['vendors','vendor-profile','vendor-profile-save'], true):
            return ops_idems_vendors($route, $method);
        case $route === 'expediting':
            return ops_idems_expediting($route, $method);
        case $route === 'expediting-projects':
            return ops_idems_expediting_projects($route, $method);
        case $route === 'report-types' || $route === 'report-type-edit':
            return ops_idems_report_types($route, $method);
        case $route === 'report-builder' || $route === 'report-field-edit':
            return ops_idems_builder($route, $method);
        case $route === 'document-fill':
            return ops_idems_fill($route, $method);
        case $route === 'document-approve':
            return ops_idems_approve($method);
        case $route === 'document-vet':
            return ops_idems_vet($method);
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
        case $route === 'report-type-preview':
            return ops_idems_type_preview();
        case $route === 'report-template-preview':
            return ops_idems_template_preview();
        case $route === 'report-templates' || $route === 'report-template-edit' || $route === 'report-template-download':
            return ops_idems_templates($route, $method);
        case $route === 'report-form-from-template':
            return ops_idems_form_from_template($method);
        case $route === 'report-autoform':
            return ops_idems_autoform($method);
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
                // How many lines an order carries decides whether the next
                // dropdown will have anything in it, so it is said here rather
                // than discovered by picking the order and finding it empty.
                $out[] = ['id' => (int)$o['id'],
                          'label' => ($o['po_number'] ?: 'Open order') . ' (' . (lk_options_or('po_type', PO_TYPES)[$o['po_type']] ?? $o['po_type']) . ')'
                                   . ' · ' . ($hasLines ? $hasLines . ' line(s)' : 'no line items'),
                          'lines' => $hasLines];
            }
            echo json_encode($out); return true;
        case $route === 'sched-preview':
            // The dates, the end date and who is free — worked out on the server
            // so the holiday rules exist in exactly one place.
            return ops_sched_preview();
        case $route === 'po-lines':
            header('Content-Type: application/json');
            $poId = (int)($_GET['id'] ?? 0);
            $out = [];
            foreach (ops_all("SELECT id, description, quantity, consumed, item_type, rate FROM po_line_items WHERE purchase_order_id=? ORDER BY id", [$poId]) as $l) {
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
            // An empty dropdown and a broken dropdown look identical, and the
            // person in front of it cannot tell which they are looking at. So
            // when there is nothing to offer, say why and say where the fix is —
            // usually the quotation this order answers, whose lines have simply
            // never been taken across.
            echo json_encode(['lines' => $out, 'hint' => $out ? null : po_empty_hint($poId)]);
            return true;
    }
    return false;
}

// ---------------------------------------------------------------------------
//  Why the line-item list is empty
//
//  Three different situations look identical from the call form — the order is
//  a lump-sum one that was never meant to have lines, the order came from a
//  quotation whose lines were never taken across, or nobody has typed them yet.
//  Only the second one is a mistake, and it is the common one. Naming which is
//  which, with the link to the screen that fixes it, turns a dead dropdown into
//  a task somebody can finish.
// ---------------------------------------------------------------------------
function po_empty_hint($poId) {
    $poId = (int)$poId;
    if (!$poId) return null;
    $po = ops_one("SELECT * FROM partner_purchase_orders WHERE id=?", [$poId]);
    if (!$po) return null;
    $url = '/po?id=' . $poId;

    // The quotation this order answers, if it named one and that quotation has
    // lines waiting. This is the case worth shouting about.
    $qid = (int)($po['quotation_id'] ?? 0);
    if ($qid) {
        $q = ops_one("SELECT quote_no, rev FROM quotations WHERE id=?", [$qid]);
        $n  = (int)ops_val("SELECT COUNT(*) FROM quote_lines WHERE quote_id=?", [$qid]);
        if ($q && $n) return [
            'text' => 'This order came from ' . $q['quote_no'] . ((int)$q['rev'] ? ' R' . (int)$q['rev'] : '')
                    . ', which has ' . $n . ' line item(s) that have never been taken across. '
                    . 'Open the order and pull them through — then they appear here.',
            'url' => $url, 'link' => 'Open the order'];
    }

    // No quotation named, but the client has one with lines on it — offer that,
    // because re-typing twelve lines that already exist is how the order and the
    // quotation end up disagreeing.
    $cand = ops_one("SELECT q.quote_no, q.rev, COUNT(l.id) n
                     FROM quotations q JOIN quote_lines l ON l.quote_id=q.id
                     WHERE q.client_id=? AND q.is_current=1 AND q.status NOT IN ('LOST','REJECTED')
                     GROUP BY q.id ORDER BY (q.status='ACCEPTED') DESC, q.id DESC",
                    [(int)$po['partner_id']]);
    if ($cand) return [
        'text' => 'This order has no line items yet. ' . $cand['quote_no']
                . ((int)$cand['rev'] ? ' R' . (int)$cand['rev'] : '') . ' for this ' . Tl('client')
                . ' has ' . (int)$cand['n'] . ' line(s) that can be copied straight onto it.',
        'url' => $url, 'link' => 'Open the order'];

    // A lump-sum order genuinely has nothing to track per line, and saying so
    // stops somebody hunting for a fault that is not there.
    if (($po['po_type'] ?? '') === 'REGULAR' && (float)($po['value'] ?? 0) > 0) return [
        'text' => 'This is a fixed-value order of ' . fmoney((float)$po['value'])
                . ' with no line items on it, so there is nothing to pick here. That is fine — '
                . 'the ' . Tl('call') . ' is priced on its own rate and quantity. Add line items to '
                . 'the order if you want the balance tracked.',
        'url' => $url, 'link' => 'Open the order'];

    return ['text' => 'This order has no line items on it yet. Add them on the order, or record the '
                    . 'order against its ' . Tl('quote') . ' and every quoted line is copied across.',
            'url' => $url, 'link' => 'Open the order'];
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
            // The same company twice is worse than a missing one: the calls split
            // between two records, the profitability splits with them, and nobody
            // notices until somebody adds up two lots of the same client by hand.
            // Matched on the name as well as on the tax numbers, because a second
            // record is usually typed by somebody who did not find the first.
            $dup = find_duplicate_partner($name, $gstin, $pan, trim($b['tan'] ?? ''), 0);
            if ($dup) {
                $r = $dup['row'];
                $already = ((int)($r['is_client'] ?? 0) && $isClient) || ((int)($r['is_vendor'] ?? 0) && $isVendor);
                if (!$already) {
                    // On file, but not yet in this list. Tick the extra role rather
                    // than making a second copy of the same company.
                    // Worked out here rather than in SQL: a bound "1" is a string,
                    // and letting the database decide what that means differs
                    // between MySQL and SQLite.
                    db()->prepare("UPDATE business_partners SET is_client=?, is_vendor=? WHERE id=?")
                        ->execute([((int)($r['is_client'] ?? 0) || $isClient) ? 1 : 0,
                                   ((int)($r['is_vendor'] ?? 0) || $isVendor) ? 1 : 0, $r['id']]);
                    echo json_encode(['ok' => true, 'id' => (int)$r['id'], 'label' => $r['legal_name'],
                        'note' => $r['legal_name'] . ' was already on file as ' . $r['code']
                                . ' (matched by ' . $dup['by'] . '). It has been added to this list rather than duplicated.',
                        'roles' => ['client' => $isClient, 'vendor' => $isVendor]]);
                    return;
                }
                echo json_encode(['ok' => false, 'error' => $r['legal_name'] . ' is already on file as '
                    . $r['code'] . ' (matched by ' . $dup['by'] . '). Pick it from the list instead of adding it twice.']);
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
            if (!$sbuVal) { echo json_encode(['ok' => false, 'error' => 'Pick a ' . Tl('sbu') . ' first.']); return; }
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

// ---- Inspector master (dedicated: names, trade, multi-Business Unit, multi-skill, certs) ----
// A single uploaded certificate scan, stored as a base64 data URI (same as the
// rest of the app, so it works on hosting with no writable uploads folder).
function cert_file_from_upload($key) {
    if (empty($_FILES[$key]) || ($_FILES[$key]['error'] ?? 4) !== 0 || !is_uploaded_file($_FILES[$key]['tmp_name'])) return null;
    $bytes = @file_get_contents($_FILES[$key]['tmp_name']);
    if ($bytes === false || strlen($bytes) === 0 || strlen($bytes) > 12 * 1024 * 1024) return null;
    $mime = $_FILES[$key]['type'] ?: 'application/octet-stream';
    return ['name' => substr((string)$_FILES[$key]['name'], 0, 255), 'mime' => $mime,
            'data' => 'data:' . $mime . ';base64,' . base64_encode($bytes)];
}
function inspector_cert_add($inspectorId, $b, $fileKey = 'cert_file') {
    if ($inspectorId <= 0 || trim((string)($b['cert_name'] ?? '')) === '') return;
    $f = cert_file_from_upload($fileKey);
    db()->prepare("INSERT INTO inspector_certs (inspector_id,name,number,issued_date,valid_from,valid_to,status,is_mandatory,file_name,file_mime,file_data,updated_by,created_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([(int)$inspectorId, $b['cert_name'], $b['cert_number'] ?? '', $b['cert_issued'] ?? '',
                   $b['cert_valid_from'] ?? '', $b['cert_valid_to'] ?? '', 'VALID', empty($b['cert_mandatory']) ? 0 : 1,
                   $f['name'] ?? '', $f['mime'] ?? '', $f['data'] ?? null, user_name(current_user()), date('c')]);
}

function ops_inspectors($action, $method) {
    $pdo = db();
    // Serve a stored certificate scan.
    if ($action === 'cert-file') {
        $c = ops_one("SELECT file_name, file_mime, file_data FROM inspector_certs WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$c || trim((string)$c['file_data']) === '') { http_response_code(404); echo 'No file.'; return; }
        $data = (string)$c['file_data'];
        $bytes = (strpos($data, 'base64,') !== false) ? base64_decode(substr($data, strpos($data, 'base64,') + 7)) : $data;
        send_uploaded_file($bytes, $c['file_name'] ?: 'certificate', $c['file_mime'] ?: 'application/octet-stream');
        return;
    }
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
                inspector_cert_add((int)$ins['id'], $b, 'cert_file');
                flash('Certification added.');
                redirect('/m/inspectors/edit?id=' . $ins['id'] . '#certs');
            }
            if (($b['_do'] ?? '') === 'cert_update' && $ins) {
                $f = cert_file_from_upload('cert_file');
                if ($f) {
                    $pdo->prepare("UPDATE inspector_certs SET valid_from=?, valid_to=?, number=?, is_mandatory=?, file_name=?, file_mime=?, file_data=?, updated_by=? WHERE id=? AND inspector_id=?")
                        ->execute([$b['cert_valid_from'] ?? '', $b['cert_valid_to'] ?? '', $b['cert_number'] ?? '', empty($b['cert_mandatory']) ? 0 : 1,
                                   $f['name'], $f['mime'], $f['data'], user_name(current_user()), (int)$b['cert_id'], $ins['id']]);
                } else {
                    $pdo->prepare("UPDATE inspector_certs SET valid_from=?, valid_to=?, number=?, is_mandatory=?, updated_by=? WHERE id=? AND inspector_id=?")
                        ->execute([$b['cert_valid_from'] ?? '', $b['cert_valid_to'] ?? '', $b['cert_number'] ?? '', empty($b['cert_mandatory']) ? 0 : 1,
                                   user_name(current_user()), (int)$b['cert_id'], $ins['id']]);
                }
                flash('Certification updated.');
                redirect('/m/inspectors/edit?id=' . $ins['id'] . '#certs');
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
            // Which team they are in for deputation: FIELD (goes to site, top of
            // the allocate list), COORD, or OFFICE. All three are deputable.
            $teamRole = in_array($b['team_role'] ?? '', ['FIELD','COORD','OFFICE'], true) ? $b['team_role'] : 'FIELD';
            if ($ins) {
                $sql = "UPDATE inspectors SET first_name=?,middle_name=?,last_name=?,name=?,emp_code=?,designation=?,staff_kind=?,trade_id=?,sbus=?,sbu=?,skill_ids=?,email=?,mobile=?,agency_name=?,home_office_id=?,weekly_working_days=?,reports_to_id=?,team_role=?,status=?";
                $args = [$b['first_name'] ?? '', $b['middle_name'] ?? '', $b['last_name'] ?? '', $full, $empCode, $desig, $kind, $trade, $sbus, explode(',', $sbus)[0] ?? '', $skills, $b['email'] ?? '', $b['mobile'] ?? '', $agencyName, $homeOff, $wwd, $reportTo, $teamRole, $b['status'] ?? 'ACTIVE'];
                if ($salary !== null) { $sql .= ",salary_ctc=?"; $args[] = $salary; }
                if ($agencyCost !== null) { $sql .= ",agency_cost=?"; $args[] = $agencyCost; }
                $sql .= " WHERE id=?"; $args[] = $ins['id'];
                $pdo->prepare($sql)->execute($args);
                flash('Inspector saved.');
                redirect('/m/inspectors/edit?id=' . $ins['id']);
            } else {
                $pdo->prepare("INSERT INTO inspectors (first_name,middle_name,last_name,name,emp_code,designation,staff_kind,trade_id,sbus,sbu,skill_ids,email,mobile,agency_name,home_office_id,weekly_working_days,reports_to_id,team_role,agency_cost,salary_ctc,status,created_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$b['first_name'] ?? '', $b['middle_name'] ?? '', $b['last_name'] ?? '', $full, $empCode, $desig, $kind, $trade, $sbus, explode(',', $sbus)[0] ?? '', $skills, $b['email'] ?? '', $b['mobile'] ?? '', $agencyName, $homeOff, $wwd, $reportTo, $teamRole, $agencyCost ?: 0, $salary ?: 0, $b['status'] ?? 'ACTIVE', date('c')]);
                $id = $pdo->lastInsertId();
                // §WO-7 — a first certificate (with its scan and validity) can be
                // attached right here while adding the team member.
                inspector_cert_add((int)$id, $b, 'cert_file');
                flash('Inspector added.' . (trim((string)($b['cert_name'] ?? '')) !== '' ? ' First certificate saved.' : ' You can now add certifications and upload the scans.'));
                redirect('/m/inspectors/edit?id=' . $id . '#certs');
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

// Human labels for an inspector's stored Business Unit codes / skill ids / trade.
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
            // Visible to BOTH the managing/contracting office and the executing office.
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
        // Raised straight off a won quotation: the client, the contract number,
        // the commercials and the business unit come across so nobody has to
        // remember them. Sales used to hand over by telling somebody.
        if ($route === 'call-new' && !empty($_GET['quote'])) {
            $fromQ = ops_one("SELECT * FROM quotations WHERE id=?", [(int)$_GET['quote']]);
            if ($fromQ) {
                $call = [
                    'quotation_id'    => (int)$fromQ['id'],
                    'client_id'       => $fromQ['client_id'] ?? null,
                    // The manufacturer / vendor site chosen on the quote comes across
                    // so the work-order's site dropdown is already set to it.
                    'vendor_id'       => $fromQ['vendor_id'] ?? null,
                    'contract_number' => $fromQ['contract_number'] ?? '',
                    'sbu'             => $fromQ['sbu'] ?? '',
                    'ibo_office_id'   => $fromQ['office_id'] ?? (current_user()['home_office_id'] ?? null),
                ];
            }
        }
        // T9 — carry the allocation "format" forward across a contract. Once a call
        // has been set up on a contract (its deliverables, chargeable heads,
        // reporting frequency and inspection type), a new call under the SAME
        // contract number inherits that format so it is not re-entered each time.
        // Only fills blanks — anything already carried from the quote wins, and
        // the coordinator can still change every field before saving.
        if ($route === 'call-new') {
            $cn = trim((string)((is_array($call) ? ($call['contract_number'] ?? '') : '') ?: ($_GET['contract'] ?? '')));
            if ($cn !== '') {
                $prev = ops_one("SELECT inspection_type, activity_id, deliverables, chargeable_heads,
                                        reporting_frequency, report_custom_days, executing_office_id
                                 FROM calls WHERE contract_number=? ORDER BY id DESC LIMIT 1", [$cn]);
                if ($prev) {
                    if (!is_array($call)) $call = [];
                    foreach ($prev as $k => $v)
                        if ($v !== null && $v !== '' && ($call[$k] ?? '') === '') $call[$k] = $v;
                }
            }
        }
        if ($method === 'POST') {
            $b = $_POST;
            $execOffice = ($b['executing_office_id'] ?? '') !== '' ? (int)$b['executing_office_id'] : null;
            // Default the managing / contracting office to the creator's home office if blank.
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
                    ['error' => $ex['text'] . ' Enter that amount before saving.',
                     'errorFields' => ['expected_credit']]));
                return;
            }
            // §when — the shape of the engagement decides the dates, and the
            // arithmetic lives in schedule.php so the form, the register and the
            // allocation all read the same answer. A continuous run of five days
            // is five WORKING days: Sundays and this branch's public holidays are
            // stepped over, not counted.
            $b['engagement_type'] = (string)($b['engagement_type'] ?? 'SINGLE');
            if (!isset(ENGAGEMENT_TYPES[$b['engagement_type']])) $b['engagement_type'] = 'SINGLE';
            $wd = array_values(array_filter(array_map('intval', (array)($b['schedule_weekdays'] ?? []))));
            $b['schedule_weekdays'] = implode(',', $wd);
            $b['inspection_dates']  = implode(',', call_dates_parse(implode(',', (array)($b['inspection_dates'] ?? []))));
            // Days the coordinator has decided will be worked despite falling on
            // a Sunday or a holiday. Recorded, because it is a decision.
            $b['force_dates'] = implode(',', call_dates_parse(implode(',', (array)($b['force_dates'] ?? []))));
            if (!isset(MANMONTH_BASES[(string)($b['manmonth_basis'] ?? '')])) $b['manmonth_basis'] = '';
            $sr = sched_resolve($b, $execOffice ?: ($b['ibo_office_id'] ?? null),
                                ($b['inspection_required_date'] ?? '') ?: null,
                                (int)($b['client_id'] ?? 0) ?: null);
            $dates = $sr['dates'];
            // §WO-3 — hard stops before anything is saved: the schedule cannot
            // book more days than the order sold, and no visit may fall after the
            // contract's validity date.
            $limitErr = call_schedule_limit_error($b, $dates);
            if ($limitErr !== '') {
                view('ops/call_form', array_merge(call_form_vars($call, $b),
                    ['error' => $limitErr, 'errorFields' => ['inspection_dates']]));
                return;
            }
            // The worked-out dates are stored, so nothing downstream has to
            // recompute them and nothing can disagree about what was booked.
            $b['inspection_dates']  = implode(',', $dates);
            $b['schedule_end_date'] = ($b['engagement_type'] === 'PATTERN')
                ? (string)($b['schedule_end_date'] ?? '')     // the "repeat until" the user typed
                : (string)$sr['end'];
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
            // A posting is not billed by the day. What is claimable is the
            // man-months the months actually came to — pro-rata for a month that
            // fell short of the agreed minimum, and capped at one for a month
            // that ran over it.
            if (($b['engagement_type'] ?? '') === 'MONTHLY' && ($sr['claimable'] ?? 0) > 0)
                $qty = (float)$sr['claimable'];
            $b['billable_qty'] = $qty;
            if ($rate > 0) $b['billable_value'] = round($rate * $qty, 2);
            // §credit — the credit is agreed per man-day and totalled off the
            // same quantity, so a six-day call cannot carry one day's credit.
            // Done here as well as in the browser: a figure that only exists if
            // JavaScript ran is a figure that will one day be wrong.
            $creditAuto = ($b['expected_credit_auto'] ?? '1') !== '0';
            $creditRate = (float)($b['credit_rate'] ?? 0);
            if ($creditAuto && $creditRate > 0) $b['expected_credit'] = round($creditRate * $qty, 2);
            // The client's expected date is the first visit, so the two never disagree.
            if ($dates && ($b['inspection_required_date'] ?? '') === '') $b['inspection_required_date'] = $dates[0];
            $fields = call_save_fields();
            // Drop any field whose column is missing (partial upload) so the whole
            // save cannot crash on one unknown column — it is added on next boot.
            if (function_exists('existing_columns_only')) $fields = existing_columns_only('calls', $fields);
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
                    // Name the boxes as well as the problem. A message at the top
                    // of a long form, with nothing on the form itself, is what
                    // made this read as "Save is not working".
                    $bad = [];
                    if ($cm) $bad[] = 'client_id';
                    if ($vm) $bad[] = 'vendor_id';
                    view('ops/call_form', array_merge(call_form_vars($call, $b), ['errorFields' => $bad, 'error' =>
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
            // §svc — the service line decides the report format. When a service
            // line is chosen and no report types were ticked by hand, allocate
            // the format(s) that service owes (client-specific if one is mapped,
            // else the house default). A smart default — the coordinator can
            // still tick different ones and those win.
            if (function_exists('svc_report_codes') && trim((string)($b['service_code'] ?? '')) !== '' && trim((string)$b['deliverables']) === '') {
                $svcCodes = svc_report_codes($b['service_code'], (int)($b['client_id'] ?? 0));
                if ($svcCodes) $b['deliverables'] = implode(',', $svcCodes);
            }
            // Which expense headings the client has agreed to pay on top of the
            // fee. A tick here is what later stops the deputation closing until
            // the bill for it is on file.
            $b['chargeable_heads'] = chargeable_heads_from_post($b['chargeable_heads'] ?? []);
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
                // Carry the edit forward onto any open deputation already raised
                // from this call — dates, days, reporting, formats, contract — so
                // a change made here does not silently leave the field out of date.
                $newCall = ops_one("SELECT * FROM calls WHERE id=?", [$call['id']]);
                [$cfJobs, $cfKept] = call_carry_forward_to_jobs($call, $newCall ?: []);
                $cfMsg = '';
                if ($cfJobs > 0) $cfMsg = " Carried forward to $cfJobs open " . ($cfJobs === 1 ? Tl('job') : Tlp('job')) . '.';
                if ($cfKept > 0) $cfMsg .= " $cfKept field(s) left as they were changed on the " . Tl('job') . '.';
                flash("Call {$call['call_code']} updated." . ($forwardNow ? ' Forwarded — email sent to the branch.' : '') . $cfMsg);
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
        'errorFields' => [],
        'isEdit' => !empty($call['call_code']),
        'clients' => clients_list(), 'vendors' => vendors_list(), 'offices' => offices_list(),
        'cfvals' => !empty($call['id']) ? custom_values_map('call', $call['id']) : [],
        // §a.i — quotes for the client already on the call, so an edit opens ready.
        'quotes' => $call ? call_quotes_for_client((int)($call['client_id'] ?? 0)) : [],
        'qlines' => ($call && !empty($call['quotation_id']))
            ? (call_quote_context((int)$call['quotation_id'])['lines'] ?? []) : [],
        // §a.iii — Region is a reporting roll-up for the Business Unit heads and the
        // Business Director. It is noise for everyone else, so only they see it.
        'showRegion' => in_array(user_role(), ['SBU_HEAD','BUSINESS_DIRECTOR','MASTER_ADMIN','ADMIN'], true) || is_master(),
        'error' => null,
    ];
}
// ---------------------------------------------------------------------------
//  What a save writes
//
//  These lists were written out inline where the INSERT is built. Adding a
//  column to a form and to the list, and forgetting to add it to the table, put
//  a name in the INSERT that the database had never heard of — and every
//  allocation died on it. Naming them here means one place to read, and
//  tools/check-columns.php checks both lists against the real schema, so the
//  same mistake cannot reach the site again.
// ---------------------------------------------------------------------------
function call_save_fields() {
    return ['client_id','vendor_id','ibo_office_id','executing_office_id','region','sbu','activity_id',
        'inspection_type','inspection_type_other','service_code','site_address_id','po_id','po_line_item_id',
        'product_category','product_other','deputation_type','expected_credit','credit_type',
        'billable_value','billable_basis','billable_rate','billable_qty','credit_rate','call_received_date',
        'inspection_required_date','notes',
        'quotation_id','quote_line_id','contract_number','folder_link',
        'inspection_dates','schedule_end_date','schedule_weekdays',
        'engagement_type','days_count','months_count','pattern_kind','pattern_n',
        'force_dates','manmonth_basis','manmonth_min_days',
        'reporting_frequency','report_custom_days','deliverables','is_outstation','chargeable_heads'];
}
function job_save_fields() {
    return ['executing_office_id','inspector_id','subcon_id','job_type','stage','scheduled_date',
        'inspection_start_date','inspection_end_date',
        'random_date1','random_date2','random_date3','folder_link','contract_number','inspection_dates','boss_id',
        'engagement_type','days_count','months_count','pattern_kind','pattern_n',
        'schedule_weekdays','schedule_end_date','force_dates','manmonth_basis','manmonth_min_days',
        'invoice_value','contracting_office_id','expected_credit','credit_rate','credit_type','credit_direction',
        'reporting_frequency','report_custom_days','inspection_type','service_code','activity_id','sbu','mandays',
        'subcon_cost','other_cost','other_cost_note','quotation_id','is_outstation','chargeable_heads',
        'cert_override_note','cert_override_by',
        'sitedoc_override_note','sitedoc_override_by',
        'impartiality_ok','impartiality_note','impartiality_by','impartiality_at'];
}

// ---- Carry an edited call forward onto its open jobs ----------------------
// Editing an inspection call AFTER it is allocated used to change nothing on
// the work already handed out — the dates, the reporting rhythm, the report
// formats, the contract number all moved on the call and the deputation kept
// the old ones. Now the change flows through: every OPEN (not closed) job of
// the call is brought back into line.
//
// The one rule that keeps this safe: a field is only overwritten on the job if
// the job still holds the call's OLD value — i.e. nobody has deliberately
// changed it on the deputation itself. A per-day reshuffle, a hand-picked date,
// a one-off reporting change stays put; everything the coordinator never
// touched follows the call. Returns [jobsChanged, fieldsKept] for the message.
function cf_eq($a, $b) {
    $a = trim((string)$a); $b = trim((string)$b);
    if ($a === $b) return true;
    if (is_numeric($a) && is_numeric($b)) return (float)$a === (float)$b;
    return false;
}
function call_carry_forward_to_jobs($oldCall, $newCall) {
    if (!is_array($oldCall) || !is_array($newCall)) return [0, 0];
    $callId = (int)($newCall['id'] ?? 0); if (!$callId) return [0, 0];
    // Plain fields that mean the same thing on a job as on the call.
    $plain = ['executing_office_id','sbu','activity_id','inspection_type',
              'contract_number','folder_link','engagement_type','days_count',
              'months_count','pattern_kind','pattern_n','schedule_weekdays',
              'schedule_end_date','force_dates','manmonth_basis','manmonth_min_days',
              'reporting_frequency','report_custom_days','deliverables',
              'is_outstation','chargeable_heads','quotation_id'];
    if (function_exists('existing_columns_only')) $plain = existing_columns_only('jobs', $plain);
    // Did anything that shapes the visit dates move on the call?
    $schedKeys = ['inspection_dates','engagement_type','days_count','months_count',
                  'pattern_kind','pattern_n','schedule_weekdays','schedule_end_date',
                  'force_dates','inspection_required_date','manmonth_basis','manmonth_min_days'];
    $schedMoved = false;
    foreach ($schedKeys as $k) if (!cf_eq($oldCall[$k] ?? '', $newCall[$k] ?? '')) { $schedMoved = true; break; }

    $jobs = ops_all("SELECT * FROM jobs WHERE call_id=? AND COALESCE(closed_flag,0)=0", [$callId]);
    $jobsChanged = 0; $fieldsKept = 0;
    foreach ($jobs as $job) {
        $set = [];
        foreach ($plain as $f) {
            if (!array_key_exists($f, $job)) continue;
            if (cf_eq($oldCall[$f] ?? '', $newCall[$f] ?? '')) continue;   // call value didn't move
            if (cf_eq($job[$f] ?? '', $oldCall[$f] ?? '')) $set[$f] = $newCall[$f] ?? '';  // job was in sync → follow
            else $fieldsKept++;                                            // job was overridden → leave it
        }
        // Dates: only re-plan the deputation when the call's schedule moved AND
        // the deputation is still on the call's old dates (not hand-scheduled).
        $replan = false;
        if ($schedMoved) {
            $oldDates = sched_resolve($oldCall, $oldCall['executing_office_id'] ?? null, ($oldCall['inspection_required_date'] ?? '') ?: null, (int)($oldCall['client_id'] ?? 0) ?: null)['dates'];
            $jobDates = job_call_required_dates($job, []);
            sort($oldDates); sort($jobDates);
            if ($jobDates === $oldDates || (!$jobDates && !$oldDates)) {
                // Build a row that carries the call's new shape, then resolve it.
                $shape = $job;
                foreach ($schedKeys as $k) if (array_key_exists($k, $newCall)) $shape[$k] = $newCall[$k];
                $start = ($newCall['inspection_required_date'] ?? '') ?: ($job['scheduled_date'] ?? '');
                $nd = sched_resolve($shape, $job['executing_office_id'] ?? ($newCall['executing_office_id'] ?? null), $start ?: null, (int)($newCall['client_id'] ?? 0) ?: null)['dates'];
                if ($nd) {
                    $set['inspection_dates'] = implode(',', $nd);
                    $set['scheduled_date'] = $nd[0];
                    $set['inspection_start_date'] = $nd[0];
                    $set['inspection_end_date'] = count($nd) > 1 ? end($nd) : $nd[0];
                    $replan = true;
                }
            } else {
                $fieldsKept++;   // deputation has its own dates — untouched
            }
        }
        if (!$set) continue;
        // Keep only columns that exist, so a partial upload cannot crash the save.
        if (function_exists('existing_columns_only'))
            $set = array_intersect_key($set, array_flip(existing_columns_only('jobs', array_keys($set))));
        if (!$set) continue;
        $cols = array_keys($set);
        $sql = 'UPDATE jobs SET ' . implode(',', array_map(fn($c) => "$c=?", $cols)) . ' WHERE id=?';
        $vals = array_map(fn($c) => nzc($c, $set[$c]), $cols); $vals[] = (int)$job['id'];
        db()->prepare($sql)->execute($vals);
        // If the dates were re-planned, redraw the per-visit rows to match.
        if ($replan && function_exists('job_visits_sync'))
            job_visits_sync((int)$job['id'], (int)($job['inspector_id'] ?? 0));
        $jobsChanged++;
    }
    return [$jobsChanged, $fieldsKept];
}

function nzc_call($f, $v) {
    // An unticked checkbox posts nothing at all, so '' has to mean 0 here or
    // "no longer outstation" could never be saved.
    if ($f === 'is_outstation') return empty($v) ? 0 : 1;
    if (in_array($f, ['client_id','vendor_id','ibo_office_id','executing_office_id','contracting_office_id','activity_id','site_address_id','po_id','po_line_item_id','quotation_id','quote_line_id'], true)) return $v === '' ? null : (int)$v;
    if (in_array($f, ['expected_credit','billable_value','credit_required','credit_rate'], true)) return $v === '' ? 0 : $v;
    // The counts only exist for the shape that uses them; a box that was never
    // shown posts nothing, and nothing means none.
    if (in_array($f, ['days_count','months_count','pattern_n','manmonth_min_days'], true)) return $v === '' ? 0 : (int)$v;
    return $v;
}
// True when the managing / contracting office also executes the call (or
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
    $body = "Dear {$c['coordinator_name']},\n\nA " . Tl('call') . " is forwarded to {$c['office_name']} for execution.\n\n"
        . "Call: {$c['call_code']}\nClient: {$client}\nVendor/Site: {$c['vendor_name']}\n"
        . "Region: " . (OPS_REGIONS[$c['region']] ?? $c['region']) . "\nSBU: " . (OPS_SBUS[$c['sbu']] ?? $c['sbu']) . "\n"
        . "Activity: " . ($c['activity_id'] ? lk_value_path($c['activity_id']) : '—') . "\n"
        . "Client required date: {$c['inspection_required_date']}\n"
        . "Credit to executing branch: " . fmoney($c['expected_credit']) . " (" . (CREDIT_TYPES[$c['credit_type']] ?? '') . ")\n"
        . "Please allocate a " . Tl('engineer') . ".\n\nRegards,\n" . app_name() . " (Managing office)";
    ops_mail($to, "Call forwarded: {$c['call_code']} — {$client}", $body, $cc, 'forward');
}

// ---- Manpower requisition / position approval (mandatory before hiring) ----
function requisitions_list($openOnly = false) {
    return ops_all("SELECT id, req_code, designation, req_type, status FROM requisitions" . ($openOnly ? " WHERE status IN ('OPEN','PROPOSED','OFFERED')" : "") . " ORDER BY id DESC");
}
function ops_requisitions($route, $method) {
    $pdo = db();
    if (function_exists('req_migrate')) req_migrate();   // additive Phase-2 columns
    if (function_exists('asg_migrate')) asg_migrate();   // additive Phase-5 assignment-commercial columns
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
            $base = ['office_id','sbu','designation','project_site','req_type','outgoing_inspector_id','budgeted_cost','approved_by','approval_ref','approval_date','status','notes'];
            $fields = array_merge($base, function_exists('req_extra_fields') ? req_extra_fields() : []);
            // Type coercion: ints (ids, counts, yes/no flags) and money/number fields.
            $intF = ['office_id','outgoing_inspector_id','client_id','quantity','prov_travel','prov_accommodation','prov_food',
                     'sel_client_interview','sel_tech_interview','sel_hr_interview','client_approval_req','training_req',
                     'cmp_medical','cmp_pcc','cmp_gate_pass','cmp_safety','cmp_certification'];
            $numF = ['budgeted_cost','billing_rate','target_margin','negotiation_floor','duration_months','experience_min'];
            $norm = function($f, $v) use ($intF, $numF) {
                if (in_array($f, ['office_id','outgoing_inspector_id','client_id','recruiter_id','manager_id'], true)) return $v === '' ? null : (int)$v;
                if (in_array($f, $intF, true)) return $v === '' ? 0 : (int)$v;
                if (in_array($f, $numF, true)) return $v === '' ? 0 : (float)$v;
                return $v;
            };
            // Derived commercials — recomputed on every save so they never drift.
            $com = function_exists('req_commercials') ? req_commercials($b) : ['revenue'=>0,'profit'=>0,'months'=>0];
            $extraCols = ['expected_revenue','expected_profit','duration_months'];
            $extraVals = [$com['revenue'], $com['profit'], $com['months']];
            if ($req) {
                $set = implode(',', array_map(fn($f)=>"$f=?", $fields)) . ',' . implode(',', array_map(fn($f)=>"$f=?", $extraCols));
                $vals = array_merge(array_map(fn($f)=>$norm($f, $b[$f] ?? ''), $fields), $extraVals, [$req['id']]);
                $pdo->prepare("UPDATE requisitions SET $set WHERE id=?")->execute($vals);
                if (function_exists('custom_save')) custom_save('requisition', (int)$req['id'], $b);
                flash("Requisition {$req['req_code']} updated."); redirect('/requisition?id=' . $req['id']);
            } else {
                $code = ops_next_code('requisitions', 'req_code', 'REQ');
                $cols = array_merge(['req_code'], $fields, $extraCols, ['created_by','created_at']);
                $vals = array_merge([$code], array_map(fn($f)=>$norm($f, $b[$f] ?? ''), $fields), $extraVals, [user_name(current_user()), date('c')]);
                $ph = implode(',', array_fill(0, count($cols), '?'));
                $pdo->prepare("INSERT INTO requisitions (" . implode(',', $cols) . ") VALUES ($ph)")->execute($vals);
                $id = $pdo->lastInsertId();
                if (function_exists('custom_save')) custom_save('requisition', (int)$id, $b);
                flash("$code created — now add candidates against it."); redirect('/requisition?id=' . $id);
            }
        }
        view('ops/requisition_form', ['req' => $req, 'offices' => offices_list(), 'inspectors' => inspectors_list(false),
            'clients' => clients_list(),
            'rccUsers' => function_exists('rcc_users') ? rcc_users() : [],
            'rccDepts' => function_exists('rcc_departments') ? rcc_departments() : [],
            'cfvals' => $req ? custom_values_map('requisition', $req['id']) : []]); return;
    }
    if ($route === 'requisition') {
        $req = ops_one("SELECT r.*, o.name office_name, bp.display_name client_name, bp.legal_name client_legal
                        FROM requisitions r LEFT JOIN offices o ON o.id=r.office_id
                        LEFT JOIN business_partners bp ON bp.id=r.client_id WHERE r.id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$req) { http_response_code(404); view('notfound'); return; }
        $outgoing = $req['outgoing_inspector_id'] ? ops_one("SELECT id,name,salary_ctc,agency_cost FROM inspectors WHERE id=?", [$req['outgoing_inspector_id']]) : null;
        $hired = $req['hired_inspector_id'] ? ops_one("SELECT id,name,salary_ctc,agency_cost,placement_fee,roll_type FROM inspectors WHERE id=?", [$req['hired_inspector_id']]) : null;
        $cands = ops_all("SELECT c.*, t.label trade_label, s.label skill_label FROM candidates c
                          LEFT JOIN lookup_values t ON t.id=c.trade_id LEFT JOIN lookup_values s ON s.id=c.skill_id
                          WHERE c.requisition_id=? ORDER BY c.id DESC", [$req['id']]) ?: [];
        // Phase 4 intelligence: requirement health + explainable fit + pool matches.
        $health = function_exists('recruit_req_health') ? recruit_req_health($req) : null;
        if (function_exists('recruit_fit_score')) foreach ($cands as &$cd) { $cd['fit'] = recruit_fit_score($cd, $req); } unset($cd);
        $pool = [];
        if (function_exists('recruit_fit_score')) {
            $others = ops_all("SELECT c.*, t.label trade_label, s.label skill_label FROM candidates c
                               LEFT JOIN lookup_values t ON t.id=c.trade_id LEFT JOIN lookup_values s ON s.id=c.skill_id
                               WHERE (c.requisition_id IS NULL OR c.requisition_id<>?)
                                 AND c.stage NOT IN ('ACCEPTED','REJECTED','WITHDRAWN','OFFER_DECLINED')
                               ORDER BY c.id DESC LIMIT 80", [$req['id']]) ?: [];
            foreach ($others as $o) { $f = recruit_fit_score($o, $req); if ($f['score'] >= 55) { $o['fit'] = $f; $pool[] = $o; } }
            usort($pool, fn($a, $b) => $b['fit']['score'] <=> $a['fit']['score']); $pool = array_slice($pool, 0, 5);
        }
        // Phase 5 — commercial rollup across hires (planned vs approved vs actual) + per-candidate commercials for the table.
        $rollup = function_exists('recruit_req_commercial_rollup') ? recruit_req_commercial_rollup($req) : null;
        $candComm = [];
        if (function_exists('assignment_commercials')) {
            foreach ($cands as $c) if (($c['stage'] ?? '') === 'ACCEPTED' || !empty($c['inspector_id'])) $candComm[$c['id']] = assignment_commercials($c, $req);
        }
        view('ops/requisition_detail', ['req' => $req, 'outgoing' => $outgoing, 'hired' => $hired, 'cands' => $cands, 'health' => $health, 'pool' => $pool, 'rollup' => $rollup, 'candComm' => $candComm]); return;
    }
}

// ---- CV / hiring pipeline (deputation resourcing) --------------------------
function candidate_name($c) {
    return trim(($c['first_name'] ?? '') . ' ' . ($c['middle_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: '(no name)';
}
function nzc_cand($f, $v) {
    if (in_array($f, ['client_id','call_id','trade_id','skill_id','requisition_id','recruiter_id'], true)) return $v === '' ? null : (int)$v;
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
        // §12 submission guard — first time we mark this person submitted to this
        // client, warn if the same person was already submitted (avoid a double
        // client submission). "Submit anyway" (sub_ack) proceeds.
        $newSub = trim((string)($b['submitted_client_date'] ?? '')) !== '' && trim((string)($cand['submitted_client_date'] ?? '')) === '';
        if ($newSub && empty($b['sub_ack']) && function_exists('cand_submission_dupes')) {
            $sd = cand_submission_dupes($cand);
            if ($sd) {
                $who = implode(', ', array_map(fn($x) => $x['cand_code'] . ' (' . trim(($x['first_name'] ?? '') . ' ' . ($x['last_name'] ?? '')) . ', ' . ($x['submitted_client_date'] ?: '—') . ')', $sd));
                flash('Already submitted to this client — ' . $who . '. Check the submission history, then tick “submit anyway” to record it.', 'warning');
                redirect('/candidate?id=' . $id);
            }
        }
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
            // Phase 7 — when a candidate is lost, record WHERE (drop point) and WHY
            // (drop reason). Moving to a live stage clears any stale drop info.
            $lost = in_array($to, ['REJECTED','WITHDRAWN','OFFER_DECLINED','HOLD'], true);
            $dropPoint  = $lost ? substr(trim((string)($_POST['drop_point'] ?? '')), 0, 30) : '';
            $dropReason = $lost ? substr(trim((string)($_POST['drop_reason'] ?? '')), 0, 60) : '';
            try { $pdo->prepare("UPDATE candidates SET stage=?, decided_at=?, drop_point=?, drop_reason=? WHERE id=?")->execute([$to, $decided, $dropPoint, $dropReason, $id]); }
            catch (Throwable $e) { $pdo->prepare("UPDATE candidates SET stage=?, decided_at=? WHERE id=?")->execute([$to, $decided, $id]); }
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
                $msg .= ' Added to ' . THP('engineer') . ' (' . ($roll === 'AGENCY' ? 'on agency roll' : 'on our roll') . ') — you can now allocate ' . Tlp('job') . ' to them.';
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
        $dupBlock = []; $prefill = null;
        if ($method === 'POST') {
            $b = $_POST;
            $fields = ['first_name','middle_name','last_name','client_id','call_id','trade_id','skill_id',
                'designation','source','agency','proposed_site','sbu','experience_years','email','mobile',
                'cv_link','expected_rate','rate_type','cv_received_date','remarks','requisition_id',
                'recruiter_id','department','drop_reason','drop_point'];   // Phase 7 — ownership + why/where lost
            // §11 duplicate guard — on a NEW candidate, stop and show look-alikes
            // (same mobile / email / name) before creating a second record for the
            // same person. "Save anyway" (dup_ack) proceeds.
            if (!$cand && empty($b['dup_ack']) && function_exists('cand_find_duplicates')) {
                $dupBlock = cand_find_duplicates($b);
                if ($dupBlock) $prefill = $b;
            }
            if (!$dupBlock && $cand) {
                $set = implode(',', array_map(fn($f) => "$f=?", $fields));
                $vals = array_map(fn($f) => nzc_cand($f, $b[$f] ?? ''), $fields); $vals[] = $cand['id'];
                $pdo->prepare("UPDATE candidates SET $set WHERE id=?")->execute($vals);
                if (function_exists('custom_save')) custom_save('candidate', (int)$cand['id'], $b);
                flash('Candidate updated.');
                redirect('/candidate?id=' . $cand['id']);
            } elseif (!$dupBlock) {
                $code = ops_next_code('candidates', 'cand_code', 'CV');
                $cols = array_merge(['cand_code'], $fields, ['stage','created_by','created_at']);
                $vals = array_merge([$code], array_map(fn($f) => nzc_cand($f, $b[$f] ?? ''), $fields),
                    ['RECEIVED', user_name(current_user()), date('c')]);
                $ph = implode(',', array_fill(0, count($cols), '?'));
                $pdo->prepare("INSERT INTO candidates (" . implode(',', $cols) . ") VALUES ($ph)")->execute($vals);
                $id = $pdo->lastInsertId();
                if (function_exists('custom_save')) custom_save('candidate', (int)$id, $b);
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
            'cfvals' => $cand ? custom_values_map('candidate', $cand['id']) : [],
            'dupes' => $dupBlock ?? [], 'prefill' => $prefill ?? null,
            'rccUsers' => function_exists('rcc_users') ? rcc_users() : [], 'rccDepts' => function_exists('rcc_departments') ? rcc_departments() : [],
            'rccDropReasons' => function_exists('rcc_drop_reasons') ? rcc_drop_reasons() : [], 'rccDropPoints' => function_exists('rcc_drop_points') ? rcc_drop_points() : [],
            'trades' => lk_type('trade') ? lk_root_values(lk_type('trade')['id']) : [], 'skillsByTrade' => skills_by_trade()]);
        return;
    }

    // detail
    // Phase 5 — approve / actualise this placement's commercials.
    if ($route === 'candidate-commercial') {
        ops_require(is_coordinator_level(), 'Only coordinators and admins can set assignment commercials.');
        if (function_exists('asg_migrate')) asg_migrate();
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        $cand = ops_one("SELECT * FROM candidates WHERE id=?", [$id]);
        if (!$cand) { http_response_code(404); view('notfound'); return; }
        if ($method === 'POST') {
            if (($_POST['mode'] ?? '') === 'actual') {
                $pdo->prepare("UPDATE candidates SET asg_act_rev=?, asg_act_cost=?, asg_status='ACTUAL', asg_note=? WHERE id=?")
                    ->execute([num($_POST['act_rev'] ?? 0), num($_POST['act_cost'] ?? 0),
                               substr(trim($_POST['note'] ?? ''), 0, 400), $id]);
                flash('Actual revenue and cost recorded for this placement.');
            } else {
                $pdo->prepare("UPDATE candidates SET asg_bill_rate=?, asg_bill_basis=?, asg_cost_rate=?, asg_months=?, asg_onetime=?, asg_ref=?, asg_note=?, asg_status='APPROVED', asg_approved_by=?, asg_approved_at=? WHERE id=?")
                    ->execute([num($_POST['bill_rate'] ?? 0), substr((string)($_POST['bill_basis'] ?? 'MONTHLY'), 0, 20),
                               num($_POST['cost_rate'] ?? 0), num($_POST['months'] ?? 0), num($_POST['onetime'] ?? 0),
                               substr(trim($_POST['ref'] ?? ''), 0, 60), substr(trim($_POST['note'] ?? ''), 0, 400),
                               user_name(current_user()), date('c'), $id]);
                flash('Placement commercials approved.');
            }
        }
        redirect('/candidate?id=' . $id);
    }

    // Phase 6 — thread two application rows together as the same person.
    if ($route === 'candidate-link-person') {
        ops_require(is_coordinator_level(), 'Only coordinators and admins can link applications.');
        $id    = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        $other = (int)($_POST['other_id'] ?? 0);
        if ($method === 'POST' && $id && $other && function_exists('person_link_rows')) {
            $why = person_link_rows([$id, $other]);
            flash($why !== '' ? $why : 'Applications linked — they are now recorded as the same person.', $why !== '' ? 'error' : 'success');
        }
        redirect('/candidate?id=' . $id);
    }

    if ($route === 'candidate') {
        ops_require(is_coordinator_level());
        $cand = ops_one("SELECT c.*, bp.legal_name client_name, bp.display_name client_disp,
            t.label trade_label, s.label skill_label, ca.call_code
            FROM candidates c LEFT JOIN business_partners bp ON bp.id=c.client_id
            LEFT JOIN lookup_values t ON t.id=c.trade_id LEFT JOIN lookup_values s ON s.id=c.skill_id
            LEFT JOIN calls ca ON ca.id=c.call_id WHERE c.id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$cand) { http_response_code(404); view('notfound'); return; }
        $events = ops_all("SELECT * FROM candidate_events WHERE candidate_id=? ORDER BY id DESC", [$cand['id']]);
        $dupes    = function_exists('cand_find_duplicates') ? cand_find_duplicates($cand, (int)$cand['id']) : [];
        $subDupes = function_exists('cand_submission_dupes') ? cand_submission_dupes($cand) : [];
        // Phase 4 intelligence: fit against the linked requirement + deployment readiness.
        $linkReq = !empty($cand['requisition_id']) ? ops_one("SELECT * FROM requisitions WHERE id=?", [(int)$cand['requisition_id']]) : null;
        $fit = ($linkReq && function_exists('recruit_fit_score')) ? recruit_fit_score($cand, $linkReq) : null;
        $readiness = function_exists('recruit_deploy_readiness') ? recruit_deploy_readiness($cand, $linkReq) : null;
        // Phase 5 — this placement's commercials (estimate→approved→actual) + billing readiness.
        $asgComm = $asgPacket = null;
        if ($linkReq && function_exists('assignment_commercials')) {
            if (function_exists('asg_migrate')) asg_migrate();
            $cand = ops_one("SELECT c.*, bp.legal_name client_name, bp.display_name client_disp,
                t.label trade_label, s.label skill_label, ca.call_code
                FROM candidates c LEFT JOIN business_partners bp ON bp.id=c.client_id
                LEFT JOIN lookup_values t ON t.id=c.trade_id LEFT JOIN lookup_values s ON s.id=c.skill_id
                LEFT JOIN calls ca ON ca.id=c.call_id WHERE c.id=?", [(int)$cand['id']]);
            $asgComm   = assignment_commercials($cand, $linkReq);
            $asgPacket = assignment_billing_packet($cand, $linkReq);
        }
        // Phase 6 — this person's other application rows (same person, kept distinct).
        $personApps = function_exists('person_applications') ? person_applications($cand) : [];
        view('ops/candidate_detail', ['cand' => $cand, 'events' => $events, 'dupes' => $dupes, 'subDupes' => $subDupes,
            'fit' => $fit, 'readiness' => $readiness, 'linkReq' => $linkReq, 'asgComm' => $asgComm, 'asgPacket' => $asgPacket,
            'personApps' => $personApps,
            'rccDropPoints' => function_exists('rcc_drop_points') ? rcc_drop_points() : [], 'rccDropReasons' => function_exists('rcc_drop_reasons') ? rcc_drop_reasons() : []]);
        return;
    }
}

// ---- Monthly voucher (P3: auto-fill skeleton) ------------------------------
function my_inspector_id() { $u = current_user(); return $u['inspector_id'] ?? null; }
// Friendly, actionable message when an Inspector login has no inspector profile linked.
function inspector_link_msg() {
    return 'Your login is on the ' . Tl('engineer') . ' role but is not linked to a ' . Tl('engineer')
         . ' record yet, so "My ' . Tlp('job') . '" and "My ' . Tlp('voucher') . '" — the personal screens for field '
         . Tlp('engineer') . ' — cannot open. An administrator fixes this in two steps: '
         . '(1) if the ' . Tl('engineer') . ' record does not exist yet, add it under Masters → ' . THP('engineer') . '; '
         . '(2) open Users → your name → set "Linked ' . Tl('engineer') . '" to that record → Save. '
         . 'If this login is actually an owner / office user rather than a field ' . Tl('engineer') . ', change its Role away from '
         . Tl('engineer') . ' instead — then use the full ' . THP('job') . ' and ' . THP('voucher') . ' registers under Operations.';
}
function voucher_owner_is_me($v) { return my_inspector_id() && (int)$v['inspector_id'] === (int)my_inspector_id(); }
function can_view_voucher($v) { return is_coordinator_level() || voucher_owner_is_me($v); }
// A voucher is the timesheet the whole cost run is built on. Once the month has
// been calculated and closed, changing a day behind it would make the stored
// allocation describe a month that no longer exists — the figures would still
// add up, and they would still be wrong. So a closed month closes its vouchers
// with it. Reopen the month if a day genuinely has to be corrected.
function voucher_month_frozen($v) {
    if (!function_exists('cost_month_frozen')) return false;
    $m = (string)($v['month'] ?? '');
    if (!preg_match('/^(\d{4})-(\d{2})$/', $m, $mm)) return false;
    $office = (int)($v['office_id'] ?? 0);
    if (!$office) $office = (int)ops_val("SELECT home_office_id FROM inspectors WHERE id=?", [(int)($v['inspector_id'] ?? 0)]);
    return $office ? cost_month_frozen((int)$mm[1], (int)$mm[2], $office) : false;
}
function voucher_lock_reason($v) {
    if (!voucher_month_frozen($v)) return '';
    return ucfirst(Tl('voucher')) . ' for ' . date('F Y', strtotime(($v['month'] ?? '') . '-01'))
         . ' cannot be changed: that month has been calculated and closed on the month-end cost run, '
         . 'and the costs already allocated were worked out from these days. '
         . 'Reopen the month first if a day really has to be corrected.';
}
function can_edit_voucher($v) {
    if (voucher_month_frozen($v)) return false;
    return ($v['status'] === 'DRAFT') && (is_coordinator_level() || voucher_owner_is_me($v));
}

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
            if (!$insId) { flash('Pick a ' . Tl('engineer') . ' first.', 'error'); redirect('/vouchers'); }
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
        ops_require(can_edit_voucher($v), voucher_lock_reason($v) ?: 'This voucher can no longer be edited.');
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
        $hdr = ['Date','Attendance / Site','File No (' . T('boss') . ')','Line No','Hours','Mode','KM','Travel'];
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
        ops_require(can_edit_voucher($v), voucher_lock_reason($v) ?: 'This voucher can no longer be edited.');
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
        ops_require(can_edit_voucher($v), voucher_lock_reason($v) ?: 'This voucher can no longer be edited.');
        $n = voucher_generate($v);
        flash($n ? "$n working day(s) pulled from jobs." : 'No new job days found for this month.');
        redirect('/voucher?id=' . $v['id']);
    }

    if ($route === 'voucher-entry' && $method === 'POST') {
        $v = ops_one("SELECT * FROM vouchers WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$v) { http_response_code(404); view('notfound'); return; }
        ops_require(can_edit_voucher($v), voucher_lock_reason($v) ?: 'This voucher can no longer be edited.');
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
            // A closed call is finished — it is not offered for a fresh allocation,
            // even by a stale link or a direct URL. (New work on the same customer
            // is a new call.)
            if (strtoupper((string)($call['status'] ?? '')) === 'CLOSED') {
                flash('This ' . Tl('call') . ' is closed — it cannot be allocated again. Raise a new ' . Tl('call') . ' for further work.', 'warning');
                redirect('/call?id=' . (int)$call['id']);
            }
        }
        // Only the executing office allocates. A contracting-office coordinator can
        // open and read the call but not raise work on it — that is the executing
        // office's responsibility.
        if ($call && !call_can_allocate($call)) {
            $eo = ops_val("SELECT name FROM offices WHERE id=?", [call_exec_office($call)]);
            $msg = 'This ' . Tl('call') . ' is carried out by ' . ($eo ?: 'another ' . Tl('office'))
                . '. Only that ' . Tl('office') . ' can allocate it — you can see it because your '
                . Tl('office') . ' contracts it and will invoice it.';
            flash($msg, 'error');
            redirect('/call?id=' . (int)$call['id']);
        }
        if ($method === 'POST') {
            $b = $_POST;
            // A job that ran out of time is fixed as it stands. Documents can
            // still be uploaded; figures cannot be rewritten weeks later.
            if ($job && ($why = job_lock_block($job)) !== '') { flash($why, 'error'); redirect('/job?id=' . $job['id']); }
            // Allocation must name WHO does the work — an internal inspector /
            // engineer (any staff kind) or a sub-contracting agency. Allowing a
            // job to be raised with nobody on it left calls "allocated" to no one.
            if (empty($b['inspector_id']) && empty($b['subcon_id'])) {
                view('ops/job_form', array_merge(call_job_form_vars($job, $call),
                    ['error' => 'Choose who will carry out this ' . Tl('job') . ' — an inspector / engineer, or a '
                              . 'sub-contracting agency — before it can be allocated.']));
                return;
            }
            $fields = job_save_fields();
            // As with calls: keep only columns that exist, so a partially-uploaded
            // jobs table degrades gracefully instead of crashing the allocation.
            if (function_exists('existing_columns_only')) $fields = existing_columns_only('jobs', $fields);
            // deliverables come as a checkbox array -> stored as CSV of codes
            $deliverables = implode(',', array_filter((array)($b['deliverables'] ?? [])));
            // §svc — the service line drives the report format on the job too.
            // Prefer what was posted; else carry the call/job's service line and
            // allocate the format(s) it owes when none were ticked by hand.
            $svcCode = trim((string)($b['service_code'] ?? ($job['service_code'] ?? ($call['service_code'] ?? ''))));
            if (function_exists('svc_report_codes') && $svcCode !== '' && trim($deliverables) === '') {
                $svcCodes = svc_report_codes($svcCode, (int)($call['client_id'] ?? 0));
                if ($svcCodes) $deliverables = implode(',', $svcCodes);
            }
            // Same shape for the chargeable headings. If the allocate form did
            // not post them at all, the call's agreement stands — never blank
            // them by accident, because blanking removes the bill requirement.
            $b['chargeable_heads'] = array_key_exists('chargeable_heads', $b)
                ? chargeable_heads_from_post($b['chargeable_heads'])
                : (string)($job['chargeable_heads'] ?? $call['chargeable_heads'] ?? '');
            // §when — the actual date is the only one chosen here; the end date
            // and the visit list follow from it and the shape of the engagement.
            // Move the actual date and the end date moves with it, which is the
            // whole reason the coordinator is not typing three dates by hand.
            $b['engagement_type'] = (string)($b['engagement_type'] ?? '')
                ?: (string)($call['engagement_type'] ?? 'SINGLE');
            if (!isset(ENGAGEMENT_TYPES[$b['engagement_type']])) $b['engagement_type'] = 'SINGLE';
            foreach (['days_count', 'months_count', 'pattern_kind', 'pattern_n', 'schedule_end_date'] as $k)
                if (($b[$k] ?? '') === '') $b[$k] = $call[$k] ?? '';
            $b['schedule_weekdays'] = implode(',', array_values(array_filter(array_map('intval',
                (array)($b['schedule_weekdays'] ?? [])))))
                ?: (string)($call['schedule_weekdays'] ?? '');
            $b['inspection_dates'] = implode(',', call_dates_parse(implode(',', (array)($b['inspection_dates'] ?? []))));
            if ($b['inspection_dates'] === '') $b['inspection_dates'] = (string)($call['inspection_dates'] ?? '');
            $b['force_dates'] = implode(',', call_dates_parse(implode(',', (array)($b['force_dates'] ?? []))));
            if (!isset(MANMONTH_BASES[(string)($b['manmonth_basis'] ?? '')]))
                $b['manmonth_basis'] = (string)($call['manmonth_basis'] ?? '');
            if (($b['manmonth_min_days'] ?? '') === '') $b['manmonth_min_days'] = (int)($call['manmonth_min_days'] ?? 0);
            $jExecForDates = ($b['executing_office_id'] ?? '') !== ''
                ? (int)$b['executing_office_id'] : (int)($call['executing_office_id'] ?? 0);
            $start = ($b['scheduled_date'] ?? '') ?: ($call['inspection_required_date'] ?? '');
            $jr = sched_resolve($b, $jExecForDates ?: null, $start ?: null, (int)($call['client_id'] ?? 0) ?: null);
            $jdates = $jr['dates'];
            $b['inspection_dates'] = implode(',', $jdates);
            if ($jdates) {
                $b['scheduled_date'] = $jdates[0];
                $b['inspection_start_date'] = $jdates[0];
                $b['inspection_end_date'] = count($jdates) > 1 ? end($jdates) : $jdates[0];
            }
            // §b.iv — the inter-office credit only exists when one office holds
            // the order and another does the work. On a job both ends of which
            // are the same office there is no credit to expect, and demanding a
            // figure greater than zero stopped the allocation dead: the button
            // did nothing, because the only thing wrong was a box that should
            // never have been asked for. Revenue for a same-office job is what
            // the client is billed, which the call already carries.
            $jMng = ($call['contracting_office_id'] ?? null) ?: ($call['ibo_office_id'] ?? null);
            $jExe = ($b['executing_office_id'] ?? '') !== '' ? (int)$b['executing_office_id'] : ($call['executing_office_id'] ?? null);
            $jCross = $jExe && $jMng && (int)$jMng !== (int)$jExe;
            // What the client is charged comes off the order or the quotation
            // via the call, and rides on the job from here on.
            $b['contracting_office_id'] = $jMng ?: null;
            // §price — the man-days are the quantity. A deputation of six days
            // against a per-day rate is worth six days, not the one the call was
            // priced at when it was still a single visit. Recomputed here as
            // well as in the browser, because a figure that only exists if
            // JavaScript ran is a figure that will one day be wrong.
            $invAuto = ($b['invoice_value_auto'] ?? '1') !== '0';
            $unitRate = (float)($call['billable_rate'] ?? 0);
            $mdQty = (float)($b['mandays'] ?? 0);
            if ($mdQty <= 0) $mdQty = max(1, count($jdates));
            if ($invAuto && $unitRate > 0) {
                $b['invoice_value'] = round($unitRate * $mdQty, 2);
            } elseif (($b['invoice_value'] ?? '') === '') {
                $b['invoice_value'] = (float)($call['billable_value'] ?? 0);
            }
            // The credit is priced per man-day off the same man-days.
            if (($b['credit_rate'] ?? '') === '') $b['credit_rate'] = (float)($call['credit_rate'] ?? 0);
            $jCreditAuto = ($b['expected_credit_auto'] ?? '1') !== '0';
            $jCreditRate = (float)($b['credit_rate'] ?? 0);
            if ($jCross && $jCreditAuto && $jCreditRate > 0)
                $b['expected_credit'] = round($jCreditRate * $mdQty, 2);
            if (!$jCross) $b['credit_rate'] = 0;
            if ($jCross && (($b['expected_credit'] ?? '') === '' || (float)$b['expected_credit'] <= 0)) {
                view('ops/job_form', array_merge(call_job_form_vars($job, $call),
                    ['error' => 'This ' . Tl('job') . ' is executed by a different ' . Tl('office')
                              . ' from the one holding the order, so the credit to the executing '
                              . Tl('office') . ' has to be stated before it is allocated.']));
                return;
            }
            if (!$jCross) {
                // One branch both holds the order and does the work. There is no
                // credit passing between offices, and a leftover figure in that
                // column would be counted as though there were.
                $b['expected_credit'] = 0;
                $b['credit_direction'] = '';
            }
            // ---- Specialist rules, if a pack is installed --------------------
            //
            // These used to be four hard calls into ISO/IEC 17020 modules. That
            // was right while this served one inspection body and wrong the
            // moment it became a product: a trading company allocating a
            // delivery must not meet a competence gate. The rules did not
            // change and did not soften — they moved behind a hook, and the
            // inspection pack registers them. No pack, no gate.
            //
            // Overrides still work the same way: a manager may proceed by
            // stating why, and the reason stays on the record, because refusing
            // outright teaches people to falsify the underlying document.
            $b['cert_override_note']    = trim((string)($b['cert_override_note'] ?? ''));
            $b['sitedoc_override_note'] = trim((string)($b['sitedoc_override_note'] ?? ''));
            if (!empty($b['inspector_id']) && function_exists('pack_fire')) {
                $onDate = function_exists('competence_job_date') ? competence_job_date($b, $call) : date('Y-m-d');
                $res = pack_fire('work.assign', [
                    'person_id'   => (int)$b['inspector_id'],
                    'partner_id'  => (int)($call['client_id'] ?? 0),
                    'vendor_id'   => (int)($call['vendor_id'] ?? 0),
                    'site_id'     => (int)($call['site_address_id'] ?? 0),
                    'work_type'   => (string)($b['inspection_type'] ?? $call['inspection_type'] ?? ''),
                    'activity_id' => (int)($b['activity_id'] ?? $call['activity_id'] ?? 0),
                    'on_date'     => $onDate,
                ]);
                $canOverride = function_exists('competence_can_override') && competence_can_override();
                foreach ($res['block'] as $blk) {
                    $why      = is_array($blk) ? (string)$blk['why'] : (string)$blk;
                    $field    = is_array($blk) ? (string)($blk['override'] ?? '') : '';
                    // The column recording WHO allowed it is cert_override_by,
                    // not cert_override_note_by — appending to the field name
                    // silently wrote a column that does not exist, so the
                    // override was kept with nobody's name against it. Which is
                    // the one thing an override must never be.
                    $byField  = preg_replace('/_note$/', '_by', $field);
                    $excused  = $field !== '' && trim((string)($b[$field] ?? '')) !== '' && $canOverride;
                    if ($excused) { $b[$byField] = user_name(current_user()); continue; }
                    $vars = array_merge(call_job_form_vars($job, $call), ['error' => $why
                        . ($field !== ''
                            ? ($canOverride
                                ? ' To go ahead anyway, state the reason in the box below.'
                                : ' A manager can allow it with a recorded reason.')
                            : ' Put somebody else on the work, or put the record right first.')]);
                    // Tell the form which override box to show, if any.
                    if ($field === 'cert_override_note')    $vars['certBlock']    = $why;
                    if ($field === 'sitedoc_override_note') $vars['siteDocBlock'] = $why;
                    view('ops/job_form', $vars);
                    return;
                }
                // Nothing to excuse — do not keep a reason for a gate that did
                // not fire, or the record claims a decision nobody made.
                foreach (['cert_override_note', 'sitedoc_override_note'] as $f) {
                    $fb = preg_replace('/_note$/', '_by', $f);
                    if (trim((string)($b[$f] ?? '')) !== '' && empty($b[$fb])) { $b[$f] = ''; $b[$fb] = ''; }
                }
                if ($res['warn'])
                    flash('Worth checking before they travel: ' . implode('; ', $res['warn']) . '.', 'warning');
            }
            // Who confirmed there was nothing to declare on THIS deputation.
            if (!empty($b['impartiality_ok'])) {
                $b['impartiality_ok'] = 1;
                $b['impartiality_by'] = user_name(current_user());
                $b['impartiality_at'] = date('c');
            } else {
                $b['impartiality_ok'] = 0;
                $b['impartiality_by'] = ''; $b['impartiality_at'] = '';
            }
            // The contract number comes down the chain and the register fills
            // itself, so nothing has to be chosen from a list that may be empty.
            $cn = contract_number_for($job ?: [], $call, (int)($b['quotation_id'] ?? 0));
            if ($cn !== '') {
                $b['contract_number'] = $cn;
                $ref = contract_ref_ensure($call['client_id'] ?? 0, $cn, (int)($b['quotation_id'] ?? 0));
                if ($ref) $b['boss_id'] = $ref;
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
            // One row per visit, so a multi-date deputation can carry a different
            // engineer on different days and the availability board is real.
            $perDate = [];
            foreach ((array)($_POST['visit_inspector'] ?? []) as $d => $iid)
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d) && (int)$iid) $perDate[$d] = (int)$iid;
            job_visits_sync($jobId, (int)($b['inspector_id'] ?? 0), $perDate);
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
            // §WO-4 — days split across engineers: e-mail each the dates that are
            // theirs (the main inspector already got the full assignment above).
            if (!empty($perDate) && function_exists('send_per_date_assignment_emails')) {
                $others = array_values(array_unique(array_filter(array_map('intval', $perDate),
                    fn($iid) => $iid && $iid !== (int)$jj['inspector_id'])));
                if ($others) send_per_date_assignment_emails($jobId, $others);
            }
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
        if (($why = job_lock_block($job)) !== '') { flash($why, 'error'); redirect('/job?id=' . $job['id']); }
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
    // One click from a closed job to a real GST invoice in the Money module — the
    // amount comes straight off the job's quote/call figures (books_line_add reads
    // billable_rate/qty/value), so nobody re-keys it (#4 / #5).
    if ($route === 'job-bill' && $method === 'POST') {
        ops_require(is_master() || can('finance.reconcile') || can('mod.invoicing.view'), 'You cannot raise invoices.');
        $job = ops_one("SELECT * FROM jobs WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$job) { http_response_code(404); view('notfound'); return; }
        if (!function_exists('books_invoice_create')) { flash('The Money module is not enabled on this installation.', 'error'); redirect('/job?id=' . $job['id']); }
        if (empty($job['closed_flag'])) { flash('Close the ' . Tl('job') . ' before raising its invoice.', 'error'); redirect('/job?id=' . $job['id']); }
        $call = ops_one("SELECT * FROM calls WHERE id=?", [$job['call_id']]);
        $inv = books_invoice_create([
            'partner_id'      => (int)($call['client_id'] ?? 0),
            'office_id'       => (int)($job['executing_office_id'] ?? ($call['executing_office_id'] ?? 0)) ?: null,
            'sbu'             => (string)($job['sbu'] ?? ($call['sbu'] ?? '')),
            'contract_number' => (string)($job['contract_number'] ?? ($call['contract_number'] ?? '')),
            'po_number'       => (string)($call['po_number'] ?? ''),
            'quotation_id'    => (int)($job['quotation_id'] ?? ($call['quotation_id'] ?? 0)),
        ]);
        if (!empty($inv['err'])) { flash($inv['err'], 'error'); redirect('/job?id=' . $job['id']); }
        $invId = (int)$inv['id'];
        $addErr = books_line_add($invId, ['job_id' => $job['id']]);   // amount auto-derived from the quote/call
        if ($addErr !== '') flash($addErr, 'warning');
        flash('Draft invoice raised from ' . TH('job') . ' ' . $job['job_code'] . ' — the amount is filled from the ' . Tl('quote') . '. Review the line and issue it.');
        redirect('/invoice?id=' . $invId);
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
            // Past the deadline the engineer can no longer close it themselves —
            // the whole point of the lock is that late figures are not simply
            // typed in later as if nothing happened.
            if (($why = job_lock_block($job)) !== '') { flash($why, 'error'); redirect('/job?id=' . $job['id']); }
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
            // Heads the coordinator declares were not incurred on this job (e.g.
            // travel on a job done locally). Recorded so the requirement is met
            // without a bill that will never exist — only heads actually agreed on
            // the job survive the clean.
            if (array_key_exists('nil_heads', $_POST)) {
                // Make sure the column exists before writing it — this handler must
                // not depend on another file's migration having been uploaded.
                if (function_exists('ensure_column')) ensure_column('jobs', 'nil_chargeable_heads', "VARCHAR(255) DEFAULT ''");
                $agreed  = chargeable_heads($job);
                $postedNil = chargeable_heads(['chargeable_heads' => chargeable_heads_from_post($_POST['nil_heads'])]);
                $nil = implode(',', array_values(array_intersect($agreed, $postedNil)));
                $pdo->prepare("UPDATE jobs SET nil_chargeable_heads=? WHERE id=?")->execute([$nil, $job['id']]);
                $job['nil_chargeable_heads'] = $nil;
            }
            // The client is being charged for these, so the bill has to exist
            // before the job is called finished. Checked on the server, not only
            // in the browser: this is a promise made to a customer. A head marked
            // "not incurred" above is satisfied without one.
            if (($why = job_bills_block($job)) !== '') {
                view('ops/job_close', ['job'=>$job,'error'=>$why]); return;
            }
            // Arrival and departure, where the company has asked for them. Off
            // by default — a body whose engineers hand their phones in at a
            // refinery gate cannot comply, and shipping it on would make the
            // software wrong for them from the first day.
            // §WO-9 — a job cannot be closed if either site check-in (arrival OR
            // departure) is missing. The inspector/coordinator is stopped; only a
            // manager may approve the close, must say why, and the lapse dents the
            // inspector's rating.
            $attMiss = function_exists('site_visit_close_missing') ? site_visit_close_missing($job) : [];
            if ($attMiss) {
                if (!can_close_attendance_missing($job)) {
                    view('ops/job_close', ['job'=>$job,
                        'error'=>site_visit_close_block($job) . ' Only a manager (operations, branch, or this ' . Tl('engineer') . '\'s reporting manager) can approve closing it without the check-in.']);
                    return;
                }
                $attReason = trim((string)($b['attendance_override_reason'] ?? ''));
                if ($attReason === '') {
                    view('ops/job_close', ['job'=>$job, 'needAttendanceReason'=>true, 'attMiss'=>$attMiss,
                        'error'=>'Missing ' . implode(' and ', $attMiss) . '. As a manager you may approve this close — enter the reason to proceed. It will be recorded and will affect the ' . Tl('engineer') . '\'s rating.']);
                    return;
                }
                $pdo->prepare("UPDATE jobs SET attendance_missing=1, attendance_override_by=?, attendance_override_reason=?, attendance_override_at=? WHERE id=?")
                    ->execute([user_name(current_user()), $attReason, date('c'), $job['id']]);
                $job['attendance_missing'] = 1;
            }
            // §WO-8 — a multi-day job cannot be closed while any of its visit days
            // is still open (each day is closed with its own report first).
            if (function_exists('job_visits_open_days')) {
                $openDays = job_visits_open_days($job);
                if ($openDays) {
                    view('ops/job_close', ['job'=>$job, 'error'=>'This ' . Tl('job') . ' still has ' . count($openDays)
                        . ' visit day(s) open: ' . implode(', ', array_map('fdate', array_slice($openDays, 0, 6)))
                        . (count($openDays) > 6 ? ' …' : '') . '. Close each day with its report first (below the day-by-day plan).']);
                    return;
                }
            }
            // §WO-6 — the last day of an inspection needs the Final Inspection
            // Report and the Release Note before the job is closed.
            if (function_exists('job_final_docs_missing')) {
                $fd = job_final_docs_missing($job);
                if ($fd) {
                    view('ops/job_close', ['job'=>$job, 'error'=>'The final day of an inspection needs '
                        . implode(' and ', $fd) . ' on file before this ' . Tl('job') . ' can be closed. '
                        . 'Create them with "New report" on the ' . Tl('job') . ' (Final Inspection Report and Release Note).']);
                    return;
                }
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
    // Reshuffle: change who goes on which day AFTER a multi-day job is allocated,
    // without re-opening the whole allocation form. A coordinator does this the
    // morning an engineer calls in sick. It rewrites only the visit rows, never
    // the job's main inspector, so everything else the job carries is untouched.
    if ($route === 'job-visit-close' && $method === 'POST') {
        // §WO-8 — close one day of a multi-day job, with its report.
        ops_require(is_coordinator_level() || is_master(), 'You cannot close a visit day.');
        $job = ops_one("SELECT * FROM jobs WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$job) { http_response_code(404); view('notfound'); return; }
        if (!empty($job['closed_flag'])) { flash('This ' . Tl('job') . ' is already closed.', 'warning'); redirect('/job?id=' . $job['id'] . '#visits'); }
        $err = function_exists('job_visit_close') ? job_visit_close($job['id'], $_POST['date'] ?? '', $_POST['report_link'] ?? '', 0) : 'Per-day close is unavailable.';
        flash($err !== '' ? $err : ('Visit of ' . fdate($_POST['date'] ?? '') . ' closed.'), $err !== '' ? 'error' : 'success');
        redirect('/job?id=' . $job['id'] . '#visits');
    }
    if ($route === 'job-reassign' && $method === 'POST') {
        ops_require(is_coordinator_level(), 'You cannot reassign ' . Tlp('job') . '.');
        $job = ops_one("SELECT * FROM jobs WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$job) { http_response_code(404); view('notfound'); return; }
        if (($why = job_lock_block($job)) !== '') { flash($why, 'error'); redirect('/job?id=' . $job['id']); }
        $perDate = [];
        foreach ((array)($_POST['visit_inspector'] ?? []) as $d => $iid)
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d)) $perDate[$d] = (int)$iid;
        if (function_exists('job_visits_sync')) job_visits_sync($job['id'], (int)($job['inspector_id'] ?? 0), $perDate);
        // §WO-4 — e-mail each engineer just (re)assigned, with only their dates.
        $changed = array_values(array_unique(array_filter(array_map('intval', $perDate))));
        $sent = $changed && function_exists('send_per_date_assignment_emails')
            ? send_per_date_assignment_emails($job['id'], $changed) : 0;
        flash('Day-by-day assignment updated for ' . $job['job_code'] . '.'
            . ($sent ? ' ' . $sent . ' ' . Tl('engineer') . '(s) e-mailed their dates.' : ''));
        redirect('/job?id=' . $job['id'] . '#visits');
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
        // The per-day plan for a multi-day deputation, with a busy flag on each
        // date, so a coordinator can reshuffle who goes when without re-opening
        // the whole allocation form.
        $visits = function_exists('job_visits') ? job_visits($job['id']) : [];
        // A job allocated before per-visit rows existed (or a fresh multi-day job)
        // has no job_visits yet — synthesise the plan from its own dates so the
        // panel still shows, every day on the main inspector until reshuffled.
        if (!$visits) {
            $mainInsp = (int)($job['inspector_id'] ?? 0);
            $mainName = (string)($job['inspector_name'] ?? '');
            foreach (job_call_required_dates($job, $jcall ?: []) as $d)
                $visits[] = ['visit_date' => $d, 'inspector_id' => $mainInsp ?: null, 'inspector_name' => $mainName];
        }
        $visitPlan = [];
        foreach ($visits as $v) {
            $d = substr((string)$v['visit_date'], 0, 10);
            $insp = (int)($v['inspector_id'] ?? 0);
            $visitPlan[] = ['date' => $d, 'pretty' => function_exists('fdate') ? fdate($d) : $d,
                'weekday' => $d ? date('D', strtotime($d)) : '',
                'inspector_id' => $insp, 'inspector_name' => (string)($v['inspector_name'] ?? ''),
                'busy' => $insp && function_exists('inspector_busy_on') ? inspector_busy_on($insp, $d, $job['id']) : '',
                'working' => function_exists('is_working_day') ? is_working_day($d, $job['executing_office_id'] ?? null) : true,
                // §WO-8 per-day completion
                'status' => (string)($v['status'] ?? 'PLANNED'),
                'done' => (($v['status'] ?? '') === 'DONE'),
                'report_link' => (string)($v['report_link'] ?? ''),
                'closed_by' => (string)($v['closed_by'] ?? '')];
        }
        // Phase-7 deputation & site-ops panel — only when this job is a deputation
        // and the service is active. Reuses the existing job; adds a site-ops layer.
        $depIsDep = function_exists('pdso_is_deputation') && function_exists('pdso_enabled')
            && pdso_enabled() && pdso_is_deputation($job, $jcall);
        view('ops/job_detail', ['job'=>$job,'expenses'=>$expenses,'profit'=>job_profit($job),
            'jcall'=>$jcall, 'siteAddr'=>$siteAddr,
            'clientInfo'=>$jcall ? partner_full($jcall['client_id']) : null,
            'vendorInfo'=>$jcall ? partner_full($jcall['vendor_id']) : null,
            'visitPlan'=>$visitPlan, 'inspectors'=>inspectors_list(),
            'booksInvoices'=>function_exists('books_invoices_for_job') ? books_invoices_for_job($job['id']) : [],
            'qaps'=>function_exists('job_qaps') ? job_qaps($job['id']) : [],
            'quoteDocs'=>function_exists('quote_docs_for_job') ? quote_docs_for_job($job['id']) : [],
            'dep'=> $depIsDep ? pdso_job_panel($job['id']) : null,
            'depStatuses'=> $depIsDep ? pdso_statuses() : [],
            'depMobStatuses'=> $depIsDep ? pdso_mob_statuses() : [],
            'depLogKinds'=> $depIsDep ? pdso_log_kinds() : [],
            'depApprovalStatuses'=> $depIsDep ? pdso_approval_statuses() : [],
            'depCanEdit'=> $depIsDep ? pdso_can_edit($job) : false]);
        return;
    }
}
// Everything the allocate-job screen needs. One place, because it is rendered
// again on each validation failure and a field missed here shows up as an
// undefined-variable warning on the re-render rather than at the happy path.
function call_job_form_vars($job, $call) {
    // The dates this deputation needs cover, so an inspector already booked on
    // one of them shows a clash. Take the job's own dates if it has them, else
    // fall back to what the call asked for.
    $jobDates = job_call_required_dates($job, $call);
    return [
        'job' => $job, 'call' => $call, 'error' => null, 'gate' => null,
        'offices' => offices_list(), 'inspectors' => inspectors_list(), 'subcons' => subcons_list(),
        'boss' => boss_for_client($call['client_id']),
        'clientInfo' => partner_full($call['client_id']),
        'vendorInfo' => partner_full($call['vendor_id']),
        'quotes' => job_linkable_quotes($call['client_id']),
        'cfvals' => $job ? custom_values_map('job', $job['id']) : [],
        'suggestions' => inspector_suggestions($call, $jobDates, (int)($job['id'] ?? 0)),
        'jobDates' => $jobDates,
        'pendingSiblings' => pending_allocation_siblings($call),
    ];
}

// The dates a deputation needs to cover, as a de-duplicated list of Y-m-d.
// Prefers the job's own schedule; if the job is new (or has none yet) it uses
// the dates the inspection call asked for so a clash is caught before saving.
function job_call_required_dates($job, $call) {
    $out = [];
    $add = function ($v) use (&$out) {
        foreach (explode(',', (string)$v) as $d) { $d = substr(trim($d), 0, 10); if ($d !== '') $out[$d] = true; }
    };
    if (is_array($job)) { $add($job['scheduled_date'] ?? ''); $add($job['inspection_dates'] ?? ''); }
    if (!$out && is_array($call)) {
        $add($call['inspection_required_date'] ?? '');
        $add($call['inspection_dates'] ?? '');
    }
    return array_keys($out);
}

function nzc($f, $v) {
    if ($f === 'is_outstation') return empty($v) ? 0 : 1;   // an unticked box posts nothing
    $nullable = ['executing_office_id','contracting_office_id','inspector_id','subcon_id','boss_id','activity_id','report_custom_days','quotation_id'];
    if (in_array($f, $nullable) && $v === '') return null;
    if (in_array($f, ['expected_credit','invoice_value','credit_rate','mandays','subcon_cost','other_cost']) && $v === '') return 0;
    if (in_array($f, ['days_count','months_count','pattern_n','manmonth_min_days'], true)) return $v === '' ? 0 : (int)$v;
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
// §WO-5 — an engineer's day-by-day schedule between two dates, drawn from the
// per-visit rows (a multi-day job may put them on some days and not others) and,
// for jobs with no per-visit rows, from the job's own dates. Keyed by date.
function inspector_date_schedule($insId, $from, $to) {
    $insId = (int)$insId; $out = []; $covered = [];
    $add = function ($d, $e) use (&$out) { $d = substr((string)$d, 0, 10); if ($d === '') return; $out[$d][] = $e; };
    try {
        foreach (ops_all("SELECT v.visit_date, v.status, j.id job_id, j.job_code, j.closed_flag,
                          bp.legal_name cl, bp.display_name cld, vp.legal_name site
                          FROM job_visits v JOIN jobs j ON j.id=v.job_id
                          LEFT JOIN calls c ON c.id=j.call_id
                          LEFT JOIN business_partners bp ON bp.id=c.client_id
                          LEFT JOIN business_partners vp ON vp.id=c.vendor_id
                          WHERE v.inspector_id=? AND substr(v.visit_date,1,10) BETWEEN ? AND ?
                          ORDER BY v.visit_date", [$insId, $from, $to]) as $r) {
            $covered[(int)$r['job_id']] = true;
            $add($r['visit_date'], ['job_id'=>(int)$r['job_id'], 'code'=>$r['job_code'], 'client'=>$r['cld'] ?: $r['cl'],
                'site'=>$r['site'], 'done'=>(($r['status'] ?? '') === 'DONE'), 'closed'=>(bool)$r['closed_flag']]);
        }
    } catch (Throwable $e) {}
    try {
        foreach (ops_all("SELECT j.id, j.job_code, j.scheduled_date, j.inspection_dates, j.closed_flag,
                          bp.legal_name cl, bp.display_name cld, vp.legal_name site
                          FROM jobs j LEFT JOIN calls c ON c.id=j.call_id
                          LEFT JOIN business_partners bp ON bp.id=c.client_id
                          LEFT JOIN business_partners vp ON vp.id=c.vendor_id
                          WHERE j.inspector_id=?", [$insId]) as $j) {
            if (isset($covered[(int)$j['id']])) continue;
            $dates = array_filter(array_map('trim', explode(',', (string)($j['inspection_dates'] ?? ''))));
            if (!$dates && trim((string)($j['scheduled_date'] ?? '')) !== '') $dates = [substr((string)$j['scheduled_date'], 0, 10)];
            foreach ($dates as $d) {
                $d = substr($d, 0, 10);
                if ($d < $from || $d > $to) continue;
                $add($d, ['job_id'=>(int)$j['id'], 'code'=>$j['job_code'], 'client'=>$j['cld'] ?: $j['cl'],
                    'site'=>$j['site'], 'done'=>false, 'closed'=>(bool)$j['closed_flag']]);
            }
        }
    } catch (Throwable $e) {}
    ksort($out);
    return $out;
}

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
    // §WO-5 — month schedule (navigable) for the week/month calendar views.
    $sched = null; $ym = '';
    if ($insId) {
        $ym = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['ym'] ?? '')) ? $_GET['ym'] : date('Y-m');
        $first = $ym . '-01'; $last = date('Y-m-t', strtotime($first));
        $sched = inspector_date_schedule($insId, $first, $last);
    }
    view('ops/my_jobs', ['rows' => $rows, 'f' => $_GET['f'] ?? '', 'sched' => $sched, 'ym' => $ym]);
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
// Map Business Unit-coded aggregates to their labels for charts.
function chart_relabel_sbu($data) {
    $map = lk_options_or('sbu', OPS_SBUS); $out = [];
    foreach ($data as $k => $v) $out[$map[$k] ?? $k] = $v;
    return $out;
}

// ---- Profitability by contract number / contract number (P7) --------------------------
// Revenue − labour − expenses − subcon, rolling voucher expenses + job closure
// expenses into each contract number number. Labour is only counted when salary is visible.
function boss_profit($bossId) {
    $seeSal = can_see_salary();
    $jobs = ops_all("SELECT * FROM jobs WHERE boss_id=?", [$bossId]);
    $revenue = 0; $labour = 0; $subcon = 0; $jobExp = 0; $invoiced = 0; $paid = 0; $contingency = 0;
    foreach ($jobs as $j) {
        $office = $j['executing_office_id'] ?? null;
        // The order is judged on what the client is charged for it, whichever
        // branch of ours did the work.
        $revenue += job_money($j)['invoice'];
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
// Office money. Two tabs, and the difference between them matters:
//   · Actual costs — what the office really spent this month, head by head.
//     Real figures, allocated to the Business Units by the rule set on each head.
//   · Overhead %   — the old single multiplier, still there for an office that
//     has not entered real figures yet, so no branch is left without a number.
// The office decides which it is on by whether it has entered anything.
function ops_office_finance() {
    ops_require(is_admin_level() || can('settings.manage'), 'You cannot edit office finance settings.');
    $pdo = db();
    $global = is_master() || can('users.manage.global') || can('settings.manage');
    $myOffice = current_user()['home_office_id'] ?? null;
    $offices = $global ? ops_all("SELECT * FROM offices WHERE COALESCE(is_active,1)=1 ORDER BY is_ahmedabad DESC, name")
                       : ($myOffice ? ops_all("SELECT * FROM offices WHERE id=?", [$myOffice]) : []);
    $mayEdit = function ($oid) use ($global, $myOffice) { return $global || (int)$oid === (int)$myOffice; };

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $do = $_POST['_do'] ?? '';
        if (isset($_POST['global_default'])) {
            ops_require(can('settings.manage') || is_master(), 'Only an admin can set the global default.');
            setting_set('overhead_pct', ($_POST['overhead_pct'] ?? '') === '' ? '' : (string)(float)$_POST['overhead_pct']);
            setting_set('contingency_pct', ($_POST['contingency_pct'] ?? '') === '' ? '' : (string)(float)$_POST['contingency_pct']);
            flash('Global default saved.');
            redirect('/office-finance?tab=pct');
        }
        $oid = (int)($_POST['office_id'] ?? 0);
        if (!$mayEdit($oid)) { flash('You can only edit your own ' . Tl('office') . '.', 'error'); redirect('/office-finance'); }

        if ($do === 'expenses' || $do === 'copy') {
            $m = trim((string)($_POST['m'] ?? ''));
            [$yr, $mon] = ym_parse($m);
            $back = '/office-finance?tab=actual&office=' . $oid . '&m=' . sprintf('%04d-%02d', $yr, $mon);
            if (cost_month_frozen($yr, $mon, $oid)) {
                flash('That month is frozen — reopen it on the month-end run before changing figures.', 'error');
                redirect($back);
            }
            if ($do === 'copy') {
                $p = ($mon > 1) ? [$yr, $mon - 1] : [$yr - 1, 12];
                $n = office_expenses_copy($oid, $p[0], $p[1], $yr, $mon);
                flash($n ? "Copied $n figure(s) from " . date('F Y', mktime(0,0,0,$p[1],1,$p[0])) . '. Edit anything that changed, then Save.'
                         : 'Nothing was entered for ' . date('F Y', mktime(0,0,0,$p[1],1,$p[0])) . ', so there was nothing to copy.',
                      $n ? 'success' : 'warning');
                redirect($back);
            }
            $n = office_expenses_save($oid, $yr, $mon, (array)($_POST['amt'] ?? []), (array)($_POST['note'] ?? []));
            flash('Saved ₹' . number_format(office_expense_total($oid, $yr, $mon), 2) . ' of '
                  . Tl('office') . ' costs for ' . date('F Y', mktime(0,0,0,$mon,1,$yr)) . '.');
            redirect($back);
        }
        if ($do === 'sbus') {
            $all = lk_options_or('sbu', OPS_SBUS);
            $sel = array_values(array_intersect((array)($_POST['sbus'] ?? []), array_keys($all)));
            $idle = trim((string)($_POST['idle_basis'] ?? ''));
            if (!isset(IDLE_BASES[$idle])) $idle = '';
            $pdo->prepare("UPDATE offices SET sbus=?, idle_basis=? WHERE id=?")->execute([implode(',', $sel), $idle, $oid]);
            flash($sel ? count($sel) . ' ' . Tlp('sbu') . ' set for this ' . Tl('office') . '.'
                       : 'No ' . Tlp('sbu') . ' named, so this ' . Tl('office') . ' is treated as running all of them.');
            redirect('/office-finance?tab=sbus&office=' . $oid);
        }
        // default: the old overhead / contingency percentages
        $oh = ($_POST['overhead_pct'] ?? '') === '' ? null : (float)$_POST['overhead_pct'];
        $cg = ($_POST['contingency_pct'] ?? '') === '' ? null : (float)$_POST['contingency_pct'];
        $pdo->prepare("UPDATE offices SET overhead_pct=?, contingency_pct=? WHERE id=?")->execute([$oh, $cg, $oid]);
        flash('Office finance settings saved.');
        redirect('/office-finance?tab=pct');
    }

    $tab = $_GET['tab'] ?? 'actual';
    if (!in_array($tab, ['actual', 'sbus', 'pct'], true)) $tab = 'actual';
    $sel = (int)($_GET['office'] ?? 0);
    if (!$sel || !array_filter($offices, function ($o) use ($sel) { return (int)$o['id'] === $sel; }))
        $sel = (int)($offices[0]['id'] ?? 0);
    [$yr, $mon] = ym_parse($_GET['m'] ?? '');

    view('ops/office_finance', ['offices' => $offices, 'global' => $global, 'tab' => $tab,
        'sel' => $sel, 'yr' => $yr, 'mon' => $mon,
        'heads' => $sel ? expense_heads_for_month($sel, $yr, $mon) : expense_heads(true),
        'entered' => $sel ? office_expense_map($sel, $yr, $mon) : [],
        'spent' => $sel ? office_expense_total($sel, $yr, $mon) : 0,
        'frozen' => $sel ? cost_month_frozen($yr, $mon, $sel) : false,
        'allSbus' => lk_options_or('sbu', OPS_SBUS),
        'offSbus' => $sel ? array_keys(office_sbus($sel)) : [],
        'offSbuRaw' => $sel ? trim((string)ops_val("SELECT sbus FROM offices WHERE id=?", [$sel])) : '',
        'idleBasis' => $sel ? (string)ops_val("SELECT idle_basis FROM offices WHERE id=?", [$sel]) : '',
        'idleUsed' => $sel ? office_idle_basis($sel) : 'OFFICE_MIX',
        'basisText' => $sel ? office_cost_basis_text($sel) : '',
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
    // list all contract number numbers with profitability + renewal hierarchy (prev / next contract number)
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
        $head = ['Sr No', T('boss'), 'Client','Status','Created on','Expires on','Renewed into','Jobs','Invoicing done','Expenses booked'];
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

// P8 — renew / carry a contract number/contract forward (ARC / renewal). Creates a new
// number linked to the old one, carries OPEN jobs forward, keeps the old visible.
function ops_boss_renew() {
    ops_require(can('data.profitability') || is_coordinator_level(), 'You cannot renew a contract.');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/profitability'); }
    $pdo = db();
    $old = ops_one("SELECT * FROM boss_numbers WHERE id=?", [(int)($_POST['old_id'] ?? 0)]);
    if (!$old) { flash(T('boss') . ' not found.', 'error'); redirect('/profitability'); }
    if (!empty($old['superseded_by'])) { flash('This contract has already been renewed.', 'error'); redirect('/profitability?boss=' . $old['id']); }
    $newNo = trim($_POST['new_number'] ?? '');
    if ($newNo === '') { flash('Enter the new ' . Tl('boss') . '.', 'error'); redirect('/profitability?boss=' . $old['id']); }
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
        // customer (top-10 chart) and by project/contract number (revenue-by-project chart).
        $rev=(float)($j['invoice_amount']??0); if ($rev<=0) $rev=$p['credit'];
        if ($rev!=0){ $ck=$j['client_disp']?:($j['client_name']?:'(no client)'); $fin['byClient'][$ck]=($fin['byClient'][$ck]??0)+$rev;
            $pk=$j['boss_number']?:('(no ' . Tl('boss') . ')'); $fin['byProject'][$pk]=($fin['byProject'][$pk]??0)+$rev; }
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

    // ---- Loaded cost distributed across each engineer's tagged Business Units (monthly snapshot) ----
    // An inspector tagged to multiple Business Units has their monthly loaded cost split
    // equally across those Business Units, so cost shows where the head-count sits — not
    // only where jobs happened to land. Respects Business Unit scope + the Business Unit/inspector filter.
    $fin['costBySbu']=[]; $fin['costBySbuTotal']=0;
    if ($seeSalary) {
        $scopeSbuSet = scope_sbus();
        foreach (ops_all("SELECT id, sbus, sbu, home_office_id, salary_ctc + COALESCE(agency_cost,0) salary_ctc FROM inspectors WHERE status='ACTIVE'") as $ins) {
            if ($F['insp']!=='' && (int)$ins['id']!==(int)$F['insp']) continue;
            $ctc=(float)($ins['salary_ctc'] ?? 0); if ($ctc<=0) continue;
            // The engineer's own office decides its overhead %, exactly as the
            // job-level cost does. Reading the constant here made this one panel
            // disagree with every other figure on the screen.
            $loadedMonthly=($ctc/12)*(1+office_overhead_pct($ins['home_office_id'] ?? null)/100);
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

    // ---- top-10 customers by revenue + revenue by project (contract number) ----
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
// ---------------------------------------------------------------------------
//  Turning what was typed back into a user row
//
//  When a save is refused the form has to be redrawn with what the person
//  typed, or they lose it. But the posted data and the stored row are different
//  shapes: the tick-lists (permissions, offices, Business Units) arrive as arrays while
//  the row keeps them comma-separated, and the sentinel values from the
//  "+ add one" dropdowns are not ids at all. Handing the raw post straight to
//  the form crashes it on the first field that expects a string.
//
//  So the conversion happens here, once, in the direction the form needs.
// ---------------------------------------------------------------------------
function user_row_from_post(array $b, $existing = null) {
    $csv = function ($v) {
        if (is_array($v)) return implode(',', array_filter(array_map('strval', $v), function ($x) { return $x !== ''; }));
        return trim((string)$v);
    };
    $notSentinel = function ($v) { return ($v === '__new__' || $v === '') ? '' : $v; };

    $row = is_array($existing) ? $existing : [];
    $row['id']            = $existing['id'] ?? null;
    $row['username']      = trim((string)($b['username'] ?? ''));
    $row['first_name']    = trim((string)($b['first_name'] ?? ''));
    $row['last_name']     = trim((string)($b['last_name'] ?? ''));
    $row['email']         = trim((string)($b['email'] ?? ''));
    $row['role']          = (string)($b['role'] ?? ($existing['role'] ?? 'COORDINATOR'));
    $row['is_superuser']  = ($row['role'] === 'MASTER_ADMIN') ? 1 : 0;
    $row['is_active']     = !empty($b['is_active']) ? 1 : 0;
    $row['inspector_id']  = $notSentinel((string)($b['inspector_id'] ?? '')) ?: null;
    $row['home_office_id'] = $notSentinel((string)($b['home_office_id'] ?? '')) ?: null;
    // "Every office / every Business Unit" is stored as ALL; otherwise the ticked ids.
    $row['scope_offices'] = !empty($b['scope_offices_all']) ? 'ALL' : $csv($b['scope_offices'] ?? '');
    $row['scope_sbus']    = !empty($b['scope_sbus_all'])    ? 'ALL' : $csv($b['scope_sbus'] ?? '');
    $row['permissions']   = $csv($b['permissions'] ?? '');
    $row['reports_to_id'] = $notSentinel((string)($b['reports_to_id'] ?? '')) ?: null;
    $row['reports_to_name']     = trim((string)($b['reports_to_name'] ?? ''));
    $row['reports_to_position'] = trim((string)($b['reports_to_position'] ?? ''));
    $row['reports_to_email']    = trim((string)($b['reports_to_email'] ?? ''));
    $pt = trim((string)($b['position_title'] ?? ''));
    $row['position_title'] = ($pt === '__new__') ? trim((string)($b['position_title_new'] ?? '')) : $pt;
    $row['weekly_working_days'] = (string)($b['weekly_working_days'] ?? '6');
    $row['daily_hours']    = trim((string)($b['daily_hours'] ?? ''));
    $row['half_day_hours'] = trim((string)($b['half_day_hours'] ?? ''));
    $row['must_change_pwd'] = !empty($b['must_change_pwd']) ? 1 : 0;
    $row['monthly_ctc']    = trim((string)($b['monthly_ctc'] ?? ''));
    $row['is_production']  = !empty($b['is_production']) ? 1 : 0;
    $row['sbu_pct']        = (array)($b['sbu_pct'] ?? []);
    return $row;
}

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
            // An existing team member picked from the list; '__new__' or blank is
            // resolved below, once the home office is known, into either a newly
            // created team member or a blocking error (a login must belong to
            // the team).
            $insRaw = (string)($b['inspector_id'] ?? '');
            $insId = ($insRaw !== '' && $insRaw !== '__new__') ? (int)$insRaw : null;
            // scope: global managers set anything; branch managers pin to their office
            // "+ Add an office" on this form writes into the one office list,
            // so it is there for everybody the moment this person is saved.
            $homeRaw = (string)($b['home_office_id'] ?? '');
            if ($globalMgr && $homeRaw === '__new__') {
                $homeOffice = office_quick_create($b['new_office_name'] ?? '', $b['new_office_code'] ?? '',
                                                  $b['new_office_city'] ?? '', $b['new_office_type'] ?? 'BRANCH') ?: null;
                if (!$homeOffice) flash('The ' . Tl('office') . ' needs a name, so none was added.', 'warning');
            } else {
                $homeOffice = $globalMgr ? ($homeRaw !== '' ? (int)$homeRaw : null) : $myOffice;
            }
            // ---- Every login must belong to a team member (person) -----------
            // The rule the owner asked for: you cannot create a login for
            // somebody who is not in the team. Pick an existing team member, or
            // add them inline (their name + which team they are in). The only
            // exception is the system-owner (Master Admin) account, which is the
            // installation's root login rather than a deputable person.
            $requireTeam = !$isSuper;
            if ($insId === null) {                          // nothing picked from the list
                if ($user && !empty($user['inspector_id'])) $insId = (int)$user['inspector_id']; // keep the edit's link
                if ($insRaw === '__new__' || ($requireTeam && !$insId)) {
                    $pname = trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? ''));
                    if ($pname === '') $pname = trim((string)($b['username'] ?? ''));
                    $teamRole = in_array($b['team_member_role'] ?? '', ['FIELD', 'COORD', 'OFFICE'], true)
                              ? $b['team_member_role'] : 'FIELD';
                    if ($pname !== '') $insId = team_member_create($pname, $teamRole, $homeOffice ?: null, $b['email'] ?? '') ?: null;
                }
            }
            if ($requireTeam && !$insId) {
                flash('A login must belong to a team member. Pick the person from the team, or enter their name (first / last) so they are added to the team.', 'error');
                $mgrsE = ops_all("SELECT id, first_name, last_name, username, role, position_title FROM users WHERE is_active=1" . ($user ? " AND id<>" . (int)$user['id'] : "") . " ORDER BY first_name, last_name");
                view('ops/user_form', ['user' => user_row_from_post($b, $user), 'inspectors' => inspectors_list(false), 'offices' => offices_list(),
                    'sbuOpts' => lk_options_or('sbu', OPS_SBUS), 'globalMgr' => $globalMgr, 'managers' => $mgrsE,
                    'defaults' => role_defaults($role)] + user_cost_vars(user_row_from_post($b, $user)));
                return;
            }
            // Both scopes arrive as tick-lists now. "Every…" wins over the
            // individual ticks, and is stored as ALL so an office added next
            // year is included without anybody revisiting this person.
            if ($globalMgr) {
                $scopeOffices = !empty($b['scope_offices_all']) ? 'ALL'
                    : implode(',', array_map('intval', array_filter((array)($b['scope_offices'] ?? []))));
            } else { $scopeOffices = ''; }                                       // '' = OWN(home)
            $sbuOpts = lk_options_or('sbu', OPS_SBUS);
            $scopeSbus = !empty($b['scope_sbus_all']) ? 'ALL'
                : implode(',', array_values(array_intersect(array_keys($sbuOpts), (array)($b['scope_sbus'] ?? []))));
            // permissions: branch mgr can only grant a safe subset (no salary/global/settings)
            $chosen = array_filter((array)($b['permissions'] ?? []));
            if (!$globalMgr) $chosen = array_intersect($chosen, ['dash.operations','dash.utilization','data.credit','ops.call.create','ops.job.allocate','ops.job.close','master.manage']);
            $perms = implode(',', $chosen);
            // Reporting manager (for the org hierarchy + approvals): a system user when
            // one exists, else a manual name/position/email for a manager without a login.
            // "+ add them" is not a person id — it means the manager has no login,
            // so the typed name/position/e-mail below are what we keep.
            $rtRaw = (string)($b['reports_to_id'] ?? '');
            $reportsTo = ($rtRaw !== '' && $rtRaw !== '__new__') ? (int)$rtRaw : null;
            if ($reportsTo && $user && $reportsTo === (int)$user['id']) $reportsTo = null; // no self-report
            $rtName = trim($b['reports_to_name'] ?? ''); $rtPos = trim($b['reports_to_position'] ?? ''); $rtEmail = trim($b['reports_to_email'] ?? '');
            // Designation comes from the master. "+ Add" writes it into the
            // master as well, so the next person picks it instead of typing a
            // second spelling of the same job.
            $posTitle = trim($b['position_title'] ?? '');
            if ($posTitle === '__new__') {
                $posTitle = trim($b['position_title_new'] ?? '');
                if ($posTitle !== '' && ($dt = lk_type('designation')))
                    if (!in_array($posTitle, lk_options_or('designation', DESIGNATIONS), true))
                        lk_add_value($dt['id'], null, strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $posTitle), 0, 20)), $posTitle);
            }
            // 5.5 days means five full days plus one half day — not "alternate
            // Saturday off", which is what it used to say and is a different
            // arrangement. How long a full day and a half day are is typed, not
            // assumed, because a half day here is four hours and not half of 8.5.
            $uwwd = in_array((string)($b['weekly_working_days'] ?? ''), ['5','5.5','6'], true) ? (float)$b['weekly_working_days'] : 6;
            $dHours = trim((string)($b['daily_hours'] ?? ''));
            $dHours = ($dHours !== '' && (float)$dHours > 0 && (float)$dHours <= 16) ? (float)$dHours : null;
            $hHours = trim((string)($b['half_day_hours'] ?? ''));
            $hHours = ($hHours !== '' && (float)$hHours > 0 && (float)$hHours <= 12) ? (float)$hHours : null;
            if ($uwwd != 5.5) $hHours = null;      // meaningless on any other pattern
            // A password set by an administrator has to clear the same bar as one
            // somebody chooses for themselves; otherwise the rule is decorative.
            $newPw = (string)($b['password'] ?? '');
            if ($newPw !== '') {
                $bad = password_problem_text($newPw, $b['username'] ?? '', trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? '')));
                if ($bad !== '') {
                    flash($bad, 'error');
                    $mgrs = ops_all("SELECT id, first_name, last_name, username, role, position_title FROM users WHERE is_active=1" . ($user ? " AND id<>" . (int)$user['id'] : "") . " ORDER BY first_name, last_name");
                    // Hand the form what it expects — a user ROW. Posted data is a
                    // different shape (permissions and the scope lists arrive as
                    // arrays, the row stores them comma-separated), so it has to be
                    // converted rather than passed straight through.
                    view('ops/user_form', ['user'=>user_row_from_post($b, $user), 'inspectors'=>inspectors_list(false), 'offices'=>offices_list(),
                        'sbuOpts'=>lk_options_or('sbu', OPS_SBUS), 'globalMgr'=>$globalMgr, 'managers'=>$mgrs,
                        'defaults'=>role_defaults($role)] + user_cost_vars(user_row_from_post($b, $user)));
                    return;
                }
            }
            // What this person costs, and which side of the line they are on.
            // Only somebody allowed to see salary may change it — otherwise a
            // form posted without the field would quietly wipe the figure.
            $canSalary = can_see_salary();
            $ctcRaw = trim((string)($b['monthly_ctc'] ?? ''));
            $ctc = ($ctcRaw === '') ? null : (float)str_replace([',', ' '], '', $ctcRaw);
            $isProd = !empty($b['is_production']) ? 1 : 0;

            // Whoever sets somebody else's password knows it. Ticking this means
            // they must replace it the moment they sign in, so it stops being shared.
            $mustChange = !empty($b['must_change_pwd']) ? 1 : 0;
            if ($user) {
                $pdo->prepare("UPDATE users SET username=?,first_name=?,last_name=?,email=?,role=?,is_superuser=?,is_active=?,inspector_id=?,home_office_id=?,scope_offices=?,scope_sbus=?,permissions=?,reports_to_id=?,reports_to_name=?,reports_to_position=?,reports_to_email=?,position_title=?,weekly_working_days=?,daily_hours=?,half_day_hours=? WHERE id=?")
                    ->execute([$b['username'], $b['first_name'] ?? '', $b['last_name'] ?? '', $b['email'] ?? '', $role, $isSuper, !empty($b['is_active'])?1:0, $insId, $homeOffice, $scopeOffices, $scopeSbus, $perms, $reportsTo, $rtName, $rtPos, $rtEmail, $posTitle, $uwwd, $dHours, $hHours, $user['id']]);
                if ($canSalary) {
                    $pdo->prepare("UPDATE users SET monthly_ctc=?, is_production=? WHERE id=?")
                        ->execute([$ctc, $isProd, $user['id']]);
                    if (!$isProd)
                        person_split_save($user['id'], (int)date('Y'), (int)date('n'),
                                          (array)($b['sbu_pct'] ?? []), (int)$homeOffice);
                }
                if ($newPw !== '') {
                    $pdo->prepare("UPDATE users SET password_hash=?, pwd_changed_at=? WHERE id=?")
                        ->execute([password_hash($newPw, PASSWORD_DEFAULT), date('c'), $user['id']]);
                    idems_log('user', $user['id'], 'PASSWORD_CHANGED', ['field'=>$user['username'], 'reason'=>'set by ' . user_name(current_user())]);
                }
                $pdo->prepare("UPDATE users SET must_change_pwd=? WHERE id=?")->execute([$mustChange, $user['id']]);
                flash('User saved.');
            } else {
                // Seats. Checked here, at the one place a NEW active account is
                // created, rather than in the licence file guessing where that
                // might be. Editing an existing person is never blocked — that
                // would strand a customer who is over their seat count with no
                // way to correct anybody's details.
                if (function_exists('lk_seat_block') && ($seatErr = lk_seat_block()) !== '') {
                    flash($seatErr, 'error');
                    redirect('/users');
                }
                // A brand-new account with no password typed gets one nobody knows
                // and is marked must-change, rather than a shared default.
                $hash = password_hash($newPw !== '' ? $newPw : bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (username,password_hash,first_name,last_name,email,role,is_superuser,is_active,inspector_id,home_office_id,scope_offices,scope_sbus,permissions,reports_to_id,reports_to_name,reports_to_position,reports_to_email,position_title,weekly_working_days,daily_hours,half_day_hours,pwd_changed_at,must_change_pwd)
                    VALUES (?,?,?,?,?,?,?,1,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([$b['username'], $hash, $b['first_name'] ?? '', $b['last_name'] ?? '', $b['email'] ?? '', $role, $isSuper, $insId, $homeOffice, $scopeOffices, $scopeSbus, $perms, $reportsTo, $rtName, $rtPos, $rtEmail, $posTitle, $uwwd, $dHours, $hHours, date('c'), $newPw === '' ? 1 : $mustChange]);
                if ($canSalary) {
                    $newId = (int)$pdo->lastInsertId();
                    $pdo->prepare("UPDATE users SET monthly_ctc=?, is_production=? WHERE id=?")
                        ->execute([$ctc, $isProd, $newId]);
                    if (!$isProd)
                        person_split_save($newId, (int)date('Y'), (int)date('n'),
                                          (array)($b['sbu_pct'] ?? []), (int)$homeOffice);
                }
                flash($newPw === ''
                    ? 'User created. No password was set, so use Edit to give them one — the account cannot be signed into until you do.'
                    : 'User created.');
            }
            redirect('/users');
        }
        $mgrs = ops_all("SELECT id, first_name, last_name, username, role, position_title FROM users WHERE is_active=1" . ($user ? " AND id<>" . (int)$user['id'] : "") . " ORDER BY first_name, last_name");
        view('ops/user_form', ['user'=>$user,'inspectors'=>inspectors_list(false),'offices'=>offices_list(),
            'sbuOpts'=>lk_options_or('sbu', OPS_SBUS),'globalMgr'=>$globalMgr,'managers'=>$mgrs,
            'defaults'=>role_defaults($user['role'] ?? 'COORDINATOR')] + user_cost_vars($user)); return;
    }
    $where = $globalMgr ? "1=1" : "home_office_id = " . (int)$myOffice;
    // Deactivated people drop to the bottom rather than sitting among the staff
    // who are actually here. They are never hidden: their work is still on file
    // and somebody will need to find them.
    $rows = ops_all("SELECT * FROM users WHERE $where ORDER BY is_active DESC, username");
    $seats = getenv('SEAT_LIMIT') ?: '';
    view('ops/users', ['rows'=>$rows,'seats'=>$seats,'active'=>(int)ops_val("SELECT COUNT(*) FROM users WHERE is_active=1"),
        'globalMgr'=>$globalMgr, 'defaults'=>accounts_on_default_password(), 'locked'=>accounts_locked_now()]);
}

// ---- System settings (financial year, etc.) --------------------------------
function ops_settings($method) {
    ops_require(can('settings.manage'), 'Only admins can change settings.');
    if ($method === 'POST') {
        // Which parts of the product this installation runs. Master Admin only —
        // switching a module off hides it from everybody including the person
        // doing the switching, so it is not an ordinary admin setting.
        if (isset($_POST['mod_on']) || ($_POST['modules_form'] ?? '') === '1') {
            if (!is_master()) { flash('Only the Master Admin can change which modules are licensed.', 'error'); redirect('/settings'); }
            if (getenv('MODULES_OFF')) { flash('Modules are pinned by this server\'s configuration (MODULES_OFF) and cannot be changed here.', 'warning'); redirect('/settings'); }
            $off = licence_save($_POST);
            flash($off ? 'Modules updated. Switched off: ' . implode(', ', array_map(fn($k) => PRODUCT_MODULES[$k][0], $off)) . '.'
                       : 'Modules updated — every module is switched on.');
            redirect('/settings');
        }
        $m = (int)($_POST['fy_start_month'] ?? 4);
        setting_set('fy_start_month', ($m >= 1 && $m <= 12) ? $m : 4);
        setting_set('tat_threshold_days', (int)($_POST['tat_threshold_days'] ?? 3));
        setting_set('fy_revenue_target', (float)($_POST['fy_revenue_target'] ?? 0));
        setting_set('report_escalate_days', max(1, (int)($_POST['report_escalate_days'] ?? 3)));
        setting_set('contract_warn_days', min(365, max(1, (int)($_POST['contract_warn_days'] ?? 30))));
        // What a man-month means, company-wide, unless a client or a single
        // deputation says otherwise.
        $mmb = (string)($_POST['manmonth_basis'] ?? 'CALENDAR');
        setting_set('manmonth_basis', isset(MANMONTH_BASES[$mmb]) ? $mmb : 'CALENDAR');
        setting_set('manmonth_min_days', min(31, max(1, (int)($_POST['manmonth_min_days'] ?? 26))));
        setting_set('app_name', trim($_POST['app_name'] ?? ''));
        // Working norms & limits (were hard-coded before)
        $cap = (float)($_POST['daily_hours_cap'] ?? 8.5);
        setting_set('daily_hours_cap', ($cap > 0 && $cap <= 24) ? $cap : 8.5);
        // A half day is its own length — four hours here — not half of a full
        // day. Typed once for the company; anybody whose half day differs has
        // their own figure on their person record.
        $hh = (float)($_POST['half_day_hours'] ?? 4);
        setting_set('half_day_hours', ($hh > 0 && $hh <= 12) ? $hh : 4);
        $wd = (float)($_POST['default_weekly_days'] ?? 6);
        setting_set('default_weekly_days', in_array($wd, [5.0, 5.5, 6.0], true) ? $wd : 6);
        setting_set('emp_code_prefix', strtoupper(trim($_POST['emp_code_prefix'] ?? '')));
        // How long an engineer has to close a job before it locks itself.
        $gd = trim((string)($_POST['job_close_grace_days'] ?? ''));
        if ($gd !== '') setting_set('job_close_grace_days', (string)max(0, min(365, (int)$gd)));
        setting_set('job_lock_enabled', !empty($_POST['job_lock_enabled']) ? '1' : '0');
        // P2 — e-mail the client when their report is issued (portal link + verify
        // code only, never the report itself). On by default; a customer can switch
        // it off here.
        setting_set('notify_client_on_issue', !empty($_POST['notify_client_on_issue']) ? '1' : '0');
        // Configurable document numbering (prefix, separator, digits, FY, start).
        if (function_exists('numbering_types') && ($_POST['numbering_form'] ?? '') === '1')
            foreach (array_keys(numbering_types()) as $nk) numbering_save($nk, $_POST);
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
        // --- Security. Each of these only chooses how strict a guard is; none
        //     of them can switch a guard off. ---
        setting_set('pwd_min_len',       min(64, max(8, (int)($_POST['pwd_min_len'] ?? 10))));
        $age = (int)($_POST['pwd_max_age_days'] ?? 0);
        setting_set('pwd_max_age_days',  ($age >= 30 && $age <= 730) ? $age : 0);
        setting_set('session_idle_min',  min(1440, max(5, (int)($_POST['session_idle_min'] ?? 60))));
        setting_set('session_max_hours', min(168, max(1, (int)($_POST['session_max_hours'] ?? 12))));
        $tr = array_values(array_intersect(array_keys(ORG_ROLES), (array)($_POST['twofa_roles'] ?? [])));
        setting_set('twofa_roles', implode(',', $tr));
        setting_set('audit_retain_days', min(3650, max(180, (int)($_POST['audit_retain_days'] ?? 400))));
        setting_set('upload_max_mb',     min(64, max(1, (int)($_POST['upload_max_mb'] ?? 12))));
        // --- Compliance. Nothing here is written by the software; leaving it
        //     blank is itself what the compliance screen reports. ---
        setting_set('grievance_name',  trim($_POST['grievance_name'] ?? ''));
        setting_set('grievance_email', trim($_POST['grievance_email'] ?? ''));
        setting_set('grievance_phone', trim($_POST['grievance_phone'] ?? ''));
        if (isset($_POST['privacy_notice'])) setting_set('privacy_notice', (string)$_POST['privacy_notice']);
        setting_set('last_cert_audit', trim($_POST['last_cert_audit'] ?? ''));
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
        // The rule is applied at the moment the password is chosen. Refusing it
        // a year later, after it has been used on every screen, helps nobody.
        $bad = password_problem_text($b['new'] ?? '', $u['username'], user_name($u));
        if ($bad !== '') { view('ops/change_password', ['error'=>$bad]); return; }
        if (($b['new'] ?? '') !== ($b['confirm'] ?? '')) { view('ops/change_password', ['error'=>'New password and confirmation do not match.']); return; }
        if (password_verify($b['new'], $u['password_hash'])) { view('ops/change_password', ['error'=>'That is the password you are already using. Please choose a different one.']); return; }
        $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($b['new'], PASSWORD_DEFAULT), $u['id']]);
        pwd_mark_changed($u['id']);
        idems_log('user', $u['id'], 'PASSWORD_CHANGED', ['field'=>$u['username']]);
        flash('Password changed.');
        redirect('/');
    }
    view('ops/change_password', ['error'=>null]);
}
