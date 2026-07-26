<?php
// ============================================================================
//  Configurable master lists ("lookups") + hierarchical (dependent) lists
//  + custom fields. This is what makes the app adaptable to any field-ops
//  company without code: admins add their own lists and fields, and they
//  appear in the forms automatically.
//
//  lookup_types  : each master list. parent_type_id makes it depend on another
//                  list (e.g. Activity depends on SBU; Wax Type depends on
//                  Product; Tier depends on Wax Type — any depth).
//  lookup_values : the values. parent_value_id links a value to its parent
//                  value, so child lists filter by the chosen parent value.
//  custom_fields : extra fields an admin adds to Calls/Jobs (text/number/date
//                  or a dropdown backed by any lookup, cascading if hierarchical).
//  custom_values : the values entered for those custom fields, per record.
// ============================================================================

function lk_ensure_schema() {
    $pdo = db(); $pk = pk_clause();
    $t = [
        "CREATE TABLE IF NOT EXISTS lookup_types (
            id $pk, type_key VARCHAR(60), label VARCHAR(150), parent_type_id INT NULL,
            is_system INT DEFAULT 0, sort_order INT DEFAULT 0, module VARCHAR(30) DEFAULT '',
            created_at VARCHAR(30) DEFAULT '')",
        "CREATE TABLE IF NOT EXISTS lookup_values (
            id $pk, type_id INT, parent_value_id INT NULL, code VARCHAR(60) DEFAULT '',
            label VARCHAR(200), sort_order INT DEFAULT 0, active INT DEFAULT 1, created_at VARCHAR(30) DEFAULT '')",
        "CREATE TABLE IF NOT EXISTS custom_fields (
            id $pk, entity VARCHAR(20), field_key VARCHAR(60), label VARCHAR(150),
            field_type VARCHAR(20) DEFAULT 'text', lookup_type_id INT NULL, required INT DEFAULT 0,
            sort_order INT DEFAULT 0, active INT DEFAULT 1, created_at VARCHAR(30) DEFAULT '')",
        "CREATE TABLE IF NOT EXISTS custom_values (
            id $pk, entity VARCHAR(20), record_id INT, field_key VARCHAR(60),
            value_text VARCHAR(500) DEFAULT '', value_id INT NULL, created_at VARCHAR(30) DEFAULT '')",
    ];
    foreach ($t as $sql) $pdo->exec($sql);
}
function lk_migrate() {
    lk_ensure_schema();
    ensure_column('lookup_types', 'module', "VARCHAR(30) DEFAULT ''"); // groups the Masters screen
    // Idempotent: seed lists that were added after the first release.
    // The record is now called a Deputation, so the old "Deputation type" list —
    // which describes how the visit is structured — is renamed to avoid a clash.
    // Rename first, so the ensure below doesn't create a second, empty list.
    lk_rename_type('deputation_type', 'engagement_pattern', 'Engagement pattern');
    // Deliverables now come from the report-types register — drop the duplicate list.
    lk_drop_type('deliverable');
    lk_ensure_type('engagement_pattern', 'Engagement pattern', [
        'Daily (single day)', 'Multiple days', 'Continuous days', 'Monthly (resident posting)',
    ]);
    lk_ensure_type_map('inspection_type', 'Type of inspection', INSPECTION_TYPES);
    lk_ensure_type_map('expense_heading', 'Expense heading', EXPENSE_HEADINGS);
    lk_ensure_type_map('industry', 'Industry', INDUSTRIES);
    lk_ensure_type_map('department', 'Department', DEPARTMENTS);
    lk_ensure_type_map('client_type', 'Client type', CLIENT_TYPES);
    lk_ensure_type_map('designation', 'Designation', DESIGNATIONS);
    // voucher attendance codes: leave types + office/WFH/holiday day codes
    lk_ensure_type_map('leave_type', 'Leave type', LEAVE_TYPES);
    lk_ensure_type_map('day_code', 'Day / office code', DAY_CODES);
    lk_ensure_type_map('avail_status', 'Inspector availability status', AVAIL_STATUS);
    // back-fill any newly-added coded values into existing lists (idempotent)
    lk_ensure_values_from_map('inspection_type', INSPECTION_TYPES);
    // add CUSTOM to reporting frequency on existing installs
    lk_ensure_value('reporting_frequency', 'CUSTOM', 'Custom (every N days)');
    // trade + skills (skills depend on trade) — seed on upgrade if missing
    if ((int)ops_val("SELECT COUNT(*) FROM lookup_types") > 0 && !lk_type('trade')) lk_seed_trade_skill();
    // NB: lk_register_module_lists() runs from boot() *after* lk_seed(), because
    // on a fresh install there are no lookup types yet at this point.
}

// Discipline / trade → skills. Researched, exhaustive starting set; fully editable.
function trade_skill_data() {
    return [
        'Mechanical' => ['Tubes & Pipes','Piping & Pipelines','Fire Fighting','Valves','Pressure Vessels','Heat Exchangers','Storage Tanks','Rotating Equipment','Static Equipment','Structural Fabrication','Site QA/QC','Commissioning','Installation & Maintenance','Shutdown / Turnaround','HVAC','Bolting & Torquing'],
        'Electrical' => ['Cables','Panels & Switchboards','Transformers','Switchgear (HV/LV)','Motors','Earthing & Lightning','Lighting','Batteries & UPS','Relays & Protection'],
        'Civil' => ['Structure','Concrete & Cement','Reinforcement','Foundations','Roads & Pavements','Finishing','Waterproofing','Soil / Geotech'],
        'Instrumentation' => ['Field Instruments','Control Systems (DCS/PLC)','Calibration','Analysers','Cabling & Loop Check'],
        'Environmental' => ['Air / Emission Monitoring','Water & Effluent','Waste Management','Noise','EIA / Compliance'],
        'Safety (HSE)' => ['HSE Audit','Fire & Safety','Scaffolding','Work at Height','Confined Space','Lifting & Rigging'],
        'Painting & Coating' => ['Surface Preparation','Blasting','Coating Inspection (NACE)','Galvanising','FBE / 3LPE'],
        'Welding' => ['Welding Inspection (CSWIP/AWS)','WPS/PQR Review','Welder Qualification'],
        'NDT' => ['RT','UT','MT','PT','Visual','PAUT / TOFD'],
    ];
}
function lk_seed_trade_skill() {
    if (lk_type('trade')) return;
    $tradeType = lk_add_type('trade', 'Trade / discipline', null, 0, 60);
    $skillType = lk_add_type('skill', 'Skill', $tradeType, 0, 61);
    $ti = 0;
    foreach (trade_skill_data() as $trade => $skills) {
        $tvid = lk_add_value($tradeType, null, '', $trade, $ti++);
        $si = 0;
        foreach ($skills as $sk) lk_add_value($skillType, $tvid, '', $sk, $si++);
    }
}
// skills grouped by their parent trade value id — for the trade→skill picker.
function skills_by_trade() {
    $st = lk_type('skill');
    if (!$st) return [];
    $out = [];
    foreach (lk_all_values($st['id']) as $v) {
        if ($v['parent_value_id'] === null) continue;
        $out[(int)$v['parent_value_id']][] = ['id' => (int)$v['id'], 'label' => $v['label']];
    }
    return $out;
}
// Like lk_ensure_type but seeds coded values from a [code=>label] map.
// $module groups the list on the Masters screen so an admin can find it.
function lk_ensure_type_map($key, $label, $map, $module = '') {
    if (lk_type($key)) { if ($module !== '') lk_set_module($key, $module); return; }
    if ((int)ops_val("SELECT COUNT(*) FROM lookup_types") === 0) return;
    $tid = lk_add_type($key, $label, null, 0, 50);
    $so = 0;
    foreach ($map as $code => $lab) lk_add_value($tid, null, $code, $lab, $so++);
    if ($module !== '') lk_set_module($key, $module);
}
function lk_set_module($key, $module) {
    try { db()->prepare("UPDATE lookup_types SET module=? WHERE type_key=? AND (module IS NULL OR module='')")->execute([$module, $key]); }
    catch (Throwable $e) {}
}

// ---------------------------------------------------------------------------
//  Every dropdown in the app, registered as an editable master list.
//
//  Each entry is [key, label, source constant, module]. The constant stays in
//  the code as the fallback (lk_options_or), so a list an admin has never
//  touched still shows the shipped values, and deleting a list cannot break a
//  screen. Adding to this table is all it takes to make a dropdown editable.
// ---------------------------------------------------------------------------
function lk_module_lists() {
    return [
        // --- Sales -----------------------------------------------------------
        ['inquiry_source',      'Inquiry source',            INQUIRY_SOURCES,        'Sales'],
        ['inquiry_status',      'Inquiry status',            INQUIRY_STATUS,         'Sales'],
        ['quote_status',        'Quote status',              QUOTE_STATUS,           'Sales'],
        ['quote_location_type', 'Work location type',        QUOTE_LOCATION_TYPES,   'Sales'],
        ['payment_term',        'Payment term',              PAYMENT_TERMS,          'Sales'],
        ['quote_origin',        'Quote origin',              QUOTE_ORIGINS,          'Sales'],
        ['site_location_type',  'Site location type',        SITE_LOCATION_TYPES,    'Sales'],
        ['order_type',          'Order type',                ORDER_TYPES,            'Sales'],
        ['followup_kind',       'Follow-up point',           FOLLOWUP_KINDS,         'Sales'],
        ['template_kind',       'Template kind',             CRM_TEMPLATE_KINDS,     'Sales'],
        ['approval_match',      'Approval rule match',       APPROVAL_MATCH,         'Sales'],
        // --- Operations ------------------------------------------------------
        ['job_type',            'Deputation type',           JOB_TYPES,              'Operations'],
        ['job_stage',           'Deputation stage',          JOB_STAGES,             'Operations'],
        ['attendance_status',   'Attendance status',         ATT_STATUS,             'Operations'],
        ['charge_unit',         'Charge unit',               CHARGE_UNITS,           'Operations'],
        ['rate_type',           'Rate basis',                RATE_TYPES,             'Operations'],
        ['experience_level',    'Experience level',          EXP_LEVELS,             'Operations'],
        ['expense_head_type',   'Expense head type',         EXP_HEAD_TYPES,         'Operations'],
        ['travel_basis',        'Travel basis',              TRAVEL_BASIS,           'Operations'],
        // --- People / hiring -------------------------------------------------
        ['candidate_stage',     'Candidate stage',           CAND_STAGES,            'People'],
        ['candidate_source',    'Candidate source',          CAND_SOURCES,           'People'],
        ['agency_type',         'Agency type',               AGENCY_TYPES,           'People'],
        ['roll_type',           'Whose roll',                ROLL_TYPES,             'People'],
        ['fee_status',          'Placement fee status',      FEE_STATUS,             'People'],
        ['requisition_type',    'Requisition type',          REQ_TYPES,              'People'],
        ['requisition_status',  'Requisition status',        REQ_STATUS,             'People'],
        // --- Scheduling ------------------------------------------------------
        // The wording is yours; the behaviour is keyed on the code behind it, so
        // renaming "Continuous days" does not stop the working-day arithmetic.
        ['engagement_type',     'Shape of engagement',       ENGAGEMENT_TYPES,       'Operations'],
        ['pattern_kind',        'How a pattern repeats',     PATTERN_KINDS,          'Operations'],
        // --- Money -----------------------------------------------------------
        ['boss_status',         'Contract number status',        BOSS_STATUS,            'Money'],
        // --- Directory -------------------------------------------------------
        ['partner_status',      'Partner status',            STATUSES,               'Directory'],
        ['ownership',           'Ownership type',            OWNERSHIP,              'Directory'],
        ['address_type',        'Address type',              ADDRESS_TYPES,          'Directory'],
        ['registration_type',   'Registration type',         REG_TYPES,              'Directory'],
        ['relationship_type',   'Relationship type',         REL_TYPES,              'Directory'],
        ['po_type',             'Purchase order type',       PO_TYPES,               'Directory'],
        ['gst_state',           'GST state',                 GST_STATES,             'Directory'],
        // --- Reporting -------------------------------------------------------
        ['report_category',     'Report category',           IDEMS_CATEGORIES,       'Reporting'],
        ['report_status',       'Report status',             IDEMS_STATUS,           'Reporting'],
        ['inspection_result',   'Inspection result',         IDEMS_RESULTS,          'Reporting'],
        ['release_status',      'Release status',            IDEMS_RELEASE,          'Reporting'],
        ['endorse_doc_type',    'Manufacturer record type',  ENDORSE_DOC_TYPES,      'Reporting'],
        ['endorse_status',      'Endorsement status',        ENDORSE_STATUS,         'Reporting'],
        ['endorse_decision',    'Endorsement decision',      ENDORSE_DECISION,       'Reporting'],
        ['phrase_category',     'Phrase category',           PHRASE_CATEGORIES,      'Reporting'],
        ['source_doc_type',     'Source document type',      SOURCE_DOC_TYPES,       'Reporting'],
    ];
}
function lk_register_module_lists() {
    foreach (lk_module_lists() as [$key, $label, $map, $module]) {
        lk_ensure_type_map($key, $label, $map, $module);
        // back-fill values added to the shipped list after this install was set up
        lk_ensure_values_from_map($key, $map);
    }
    // Lists that already existed get their module tag too, so nothing is unfiled.
    foreach ([
        'sbu'=>'Operations', 'region'=>'Operations', 'product'=>'Operations', 'product_category'=>'Operations',
        'inspection_type'=>'Operations', 'engagement_pattern'=>'Operations',
        'activity'=>'Operations', 'credit_type'=>'Money', 'credit_direction'=>'Money',
        'expense_heading'=>'Money', 'reporting_frequency'=>'Operations', 'avail_status'=>'Operations',
        'leave_type'=>'People', 'day_code'=>'People', 'department'=>'People', 'designation'=>'People',
        'trade'=>'People', 'skill'=>'People', 'client_type'=>'Directory', 'industry'=>'Directory',
        'quote_lost_reason'=>'Sales',
    ] as $k => $m) lk_set_module($k, $m);
}
// Back-fill all coded values from a [code=>label] map into an existing type.
function lk_ensure_values_from_map($typeKey, $map) {
    if (!lk_type($typeKey)) return;
    foreach ($map as $code => $label) lk_ensure_value($typeKey, $code, $label);
}
// Ensure a single coded value exists in an existing type (safe on upgrades).
function lk_ensure_value($typeKey, $code, $label) {
    $t = lk_type($typeKey);
    if (!$t) return;
    $exists = ops_val("SELECT COUNT(*) FROM lookup_values WHERE type_id=? AND code=?", [$t['id'], $code]);
    if (!$exists) lk_add_value($t['id'], null, $code, $label, 90);
}

// Create a lookup type + flat values only if it doesn't exist yet (safe on upgrades).
// Rename a shipped list (and its key) in place, keeping every value and every
// record that points at it. No-op once done, and never clobbers an existing key.
function lk_rename_type($oldKey, $newKey, $newLabel) {
    if (!lk_type($oldKey) || lk_type($newKey)) return;
    db()->prepare("UPDATE lookup_types SET type_key=?, label=? WHERE type_key=?")->execute([$newKey, $newLabel, $oldKey]);
}
// Remove a list that has been superseded by another source of the same values.
function lk_drop_type($key) {
    $t = lk_type($key);
    if (!$t) return;
    db()->prepare("DELETE FROM lookup_values WHERE type_id=?")->execute([$t['id']]);
    db()->prepare("DELETE FROM lookup_types WHERE id=?")->execute([$t['id']]);
}
function lk_ensure_type($key, $label, $values) {
    if (lk_type($key)) return;
    if ((int)ops_val("SELECT COUNT(*) FROM lookup_types") === 0) return; // fresh install: lk_seed handles it
    $tid = lk_add_type($key, $label, null, 0, 50);
    $so = 0;
    foreach ($values as $v) lk_add_value($tid, null, '', $v, $so++);
}

// Activity values grouped by their parent SBU code — for the SBU→Activity picker.
function activity_options_by_sbu() {
    $act = lk_type('activity');
    if (!$act) return [];
    $out = [];
    foreach (lk_all_values($act['id']) as $v) {
        $parent = $v['parent_value_id'] ? lk_value($v['parent_value_id']) : null;
        if (!$parent) continue;
        $code = $parent['code'] !== '' ? $parent['code'] : (string)$parent['id'];
        $out[$code][] = ['id' => (int)$v['id'], 'label' => $v['label']];
    }
    return $out;
}

// ---- Seed system lists (from the old fixed choice lists) + demo hierarchies -
function lk_seed() {
    if ((int)ops_val("SELECT COUNT(*) FROM lookup_types") > 0) return;
    // system (flat) lists — become editable, keep the same codes so logic still works
    $system = [
        ['sbu','SBU', OPS_SBUS], ['region','Region', OPS_REGIONS], ['product','Product category', PRODUCT_CATS],
        ['credit_type','Credit type', CREDIT_TYPES], ['credit_direction','Credit direction', CREDIT_DIRECTIONS],
        ['reporting_frequency','Reporting frequency', REPORT_FREQ], ['attendance_status','Attendance status', ATT_STATUS],
        ['experience_level','Experience level', EXP_LEVELS], ['rate_type','Rate type', RATE_TYPES],
        ['boss_status','Contract status', BOSS_STATUS], ['client_type','Client type', CLIENT_TYPES],
        ['industry','Industry', INDUSTRIES], ['ownership','Ownership type', OWNERSHIP], ['partner_status','Partner status', STATUSES], ['department','Department', DEPARTMENTS], ['designation','Designation', DESIGNATIONS],
        ['leave_type','Leave type', LEAVE_TYPES], ['day_code','Day / office code', DAY_CODES],
    ];
    $so = 0;
    foreach ($system as [$key, $label, $map]) {
        $tid = lk_add_type($key, $label, null, 1, $so++);
        $vso = 0;
        foreach ($map as $code => $vlabel) lk_add_value($tid, null, $code, $vlabel, $vso++);
    }
    // demo hierarchy 1: Activity under SBU (the scenario you raised)
    $sbu = lk_type('sbu');
    $activity = lk_add_type('activity', 'Activity code', $sbu['id'], 0, $so++);
    $sbuVals = [];
    foreach (lk_root_values($sbu['id']) as $v) $sbuVals[$v['code']] = $v['id'];
    if (isset($sbuVals['IND'])) { lk_add_value($activity, $sbuVals['IND'], '', 'Vendor Inspection', 0); lk_add_value($activity, $sbuVals['IND'], '', 'Stage Inspection', 1); lk_add_value($activity, $sbuVals['IND'], '', 'Final Inspection', 2); }
    if (isset($sbuVals['OGC'])) { lk_add_value($activity, $sbuVals['OGC'], '', 'Welding Inspection', 0); lk_add_value($activity, $sbuVals['OGC'], '', 'NDT Witness', 1); }

    // deputation type (flat list)
    $dep = lk_add_type('engagement_pattern', 'Engagement pattern', null, 0, $so++);
    foreach (['Daily (single day)', 'Multiple days', 'Continuous days', 'Monthly (PM deputation)'] as $i => $d) lk_add_value($dep, null, '', $d, $i);
    // inspection types + deliverables (coded flat lists)
    $it = lk_add_type('inspection_type', 'Type of inspection', null, 0, $so++);
    $i = 0; foreach (INSPECTION_TYPES as $code => $lab) lk_add_value($it, null, $code, $lab, $i++);
    $eh = lk_add_type('expense_heading', 'Expense heading', null, 0, $so++);
    $i = 0; foreach (EXPENSE_HEADINGS as $code => $lab) lk_add_value($eh, null, $code, $lab, $i++);
    lk_seed_trade_skill();
}

// ---- Type helpers ----------------------------------------------------------
function lk_add_type($key, $label, $parentTypeId = null, $isSystem = 0, $sort = 0) {
    db()->prepare("INSERT INTO lookup_types (type_key,label,parent_type_id,is_system,sort_order,created_at) VALUES (?,?,?,?,?,?)")
        ->execute([$key, $label, $parentTypeId, $isSystem, $sort, date('c')]);
    return db()->lastInsertId();
}
function lk_add_value($typeId, $parentValueId, $code, $label, $sort = 0) {
    db()->prepare("INSERT INTO lookup_values (type_id,parent_value_id,code,label,sort_order,active,created_at) VALUES (?,?,?,?,?,1,?)")
        ->execute([$typeId, $parentValueId, $code, $label, $sort, date('c')]);
    return db()->lastInsertId();
}
function lk_types() { return ops_all("SELECT * FROM lookup_types ORDER BY sort_order, label"); }
function lk_type($key) { return ops_one("SELECT * FROM lookup_types WHERE type_key=?", [$key]); }
function lk_type_by_id($id) { return $id ? ops_one("SELECT * FROM lookup_types WHERE id=?", [$id]) : null; }
function lk_root_types() { return ops_all("SELECT * FROM lookup_types WHERE parent_type_id IS NULL ORDER BY sort_order, label"); }

// ---- Value helpers ---------------------------------------------------------
function lk_root_values($typeId, $active = true) {
    return ops_all("SELECT * FROM lookup_values WHERE type_id=? AND parent_value_id IS NULL" . ($active ? " AND active=1" : "") . " ORDER BY sort_order, label", [$typeId]);
}
function lk_child_values($typeId, $parentValueId, $active = true) {
    return ops_all("SELECT * FROM lookup_values WHERE type_id=? AND parent_value_id=?" . ($active ? " AND active=1" : "") . " ORDER BY sort_order, label", [$typeId, $parentValueId]);
}
function lk_all_values($typeId) { return ops_all("SELECT * FROM lookup_values WHERE type_id=? ORDER BY sort_order, label", [$typeId]); }
function lk_value($id) { return $id ? ops_one("SELECT * FROM lookup_values WHERE id=?", [$id]) : null; }

// Editable map for the old fixed dropdowns: use lookup values if present, else the constant.
// Cached per request: this is called inside table loops (one label lookup per
// row), so without the cache a 200-row list would run 400 queries.
function lk_options_or($key, $const) {
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key] ?: $const;
    $t = lk_type($key);
    if (!$t) { $cache[$key] = null; return $const; }
    $out = [];
    foreach (lk_root_values($t['id']) as $r) $out[$r['code'] !== '' ? $r['code'] : $r['id']] = $r['label'];
    $cache[$key] = $out ?: null;
    return $out ?: $const;
}

// The chain of types from root to this leaf type (for cascading render).
function lk_chain($typeId) {
    $chain = []; $t = lk_type_by_id($typeId); $guard = 0;
    while ($t && $guard++ < 10) { array_unshift($chain, $t); $t = $t['parent_type_id'] ? lk_type_by_id($t['parent_type_id']) : null; }
    return $chain;
}
// "Candles › Soy wax › Premium" for a stored leaf value id.
function lk_value_path($valueId) {
    $labels = []; $v = lk_value($valueId); $guard = 0;
    while ($v && $guard++ < 10) { array_unshift($labels, $v['label']); $v = $v['parent_value_id'] ? lk_value($v['parent_value_id']) : null; }
    return implode(' › ', $labels);
}

// ---- Custom fields ---------------------------------------------------------
function custom_fields_for($entity, $activeOnly = true) {
    return ops_all("SELECT * FROM custom_fields WHERE entity=?" . ($activeOnly ? " AND active=1" : "") . " ORDER BY sort_order, id", [$entity]);
}
function custom_values_map($entity, $recordId) {
    $out = [];
    foreach (ops_all("SELECT * FROM custom_values WHERE entity=? AND record_id=?", [$entity, $recordId]) as $r) {
        $out[$r['field_key']] = $r;
    }
    return $out;
}
function custom_save($entity, $recordId, $post) {
    $pdo = db();
    foreach (custom_fields_for($entity) as $f) {
        $key = 'cf_' . $f['field_key'];
        $val = trim((string)($post[$key] ?? ''));
        $pdo->prepare("DELETE FROM custom_values WHERE entity=? AND record_id=? AND field_key=?")->execute([$entity, $recordId, $f['field_key']]);
        if ($val === '') continue;
        if (in_array($f['field_type'], ['select', 'dependent'], true)) {
            $pdo->prepare("INSERT INTO custom_values (entity,record_id,field_key,value_id,created_at) VALUES (?,?,?,?,?)")
                ->execute([$entity, $recordId, $f['field_key'], (int)$val, date('c')]);
        } else {
            $pdo->prepare("INSERT INTO custom_values (entity,record_id,field_key,value_text,created_at) VALUES (?,?,?,?,?)")
                ->execute([$entity, $recordId, $f['field_key'], $val, date('c')]);
        }
    }
}
function custom_display($entity, $recordId) {
    $vals = custom_values_map($entity, $recordId); $out = [];
    foreach (custom_fields_for($entity) as $f) {
        $v = $vals[$f['field_key']] ?? null;
        if (!$v) continue;
        if (in_array($f['field_type'], ['select', 'dependent'], true)) $disp = lk_value_path($v['value_id']);
        else $disp = $v['value_text'];
        if ($disp !== '' && $disp !== null) $out[] = ['label' => $f['label'], 'value' => $disp];
    }
    return $out;
}

// ---- Admin handlers (master-list & custom-field management) -----------------
function lk_admin($route, $method) {
    ops_require(is_admin_level(), 'Only admins can manage master lists.');
    $pdo = db();

    if ($route === 'lookups') {
        if ($method === 'POST') {
            $label = trim($_POST['label'] ?? '');
            $rawKey = trim($_POST['type_key'] ?? '');
            if ($rawKey === '') $rawKey = $label; // auto-derive the key from the name
            $key = trim(strtolower(preg_replace('/[^a-zA-Z0-9_]+/', '_', $rawKey)), '_');
            $parent = ($_POST['parent_type_id'] ?? '') !== '' ? (int)$_POST['parent_type_id'] : null;
            if ($key === '' || $label === '') { flash('Give the list a name.', 'error'); redirect('/lookups'); }
            if (lk_type($key)) { flash('A list with that key already exists.', 'error'); redirect('/lookups'); }
            lk_add_type($key, $label, $parent, 0, 99);
            flash("Master list \"$label\" created. Now add its values.");
            redirect('/lookup?key=' . $key);
        }
        if (($_GET['del'] ?? '') !== '') {
            $t = lk_type_by_id((int)$_GET['del']);
            // Super Admin may delete any list; others only custom lists.
            if ($t && (is_master() || !$t['is_system'])) {
                $pdo->prepare("DELETE FROM lookup_values WHERE type_id=?")->execute([$t['id']]);
                $pdo->prepare("DELETE FROM lookup_types WHERE id=?")->execute([$t['id']]);
                flash('Master list deleted.' . ($t['is_system'] ? ' (built-in — dropdowns fall back to defaults.)' : ''));
            } else flash('Only the Super Admin can delete built-in lists.', 'error');
            redirect('/lookups');
        }
        view('ops/lookups', ['types' => lk_types()]); return;
    }

    if ($route === 'lookup') {
        $t = lk_type($_GET['key'] ?? '');
        if (!$t) { http_response_code(404); view('notfound'); return; }
        $parentType = lk_type_by_id($t['parent_type_id']);
        if ($method === 'POST') {
            $label = trim($_POST['label'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $parentVal = ($_POST['parent_value_id'] ?? '') !== '' ? (int)$_POST['parent_value_id'] : null;
            $editId = (int)($_POST['edit_id'] ?? 0);
            if ($label === '') { flash('Enter a value label.', 'error'); redirect('/lookup?key=' . $t['type_key']); }
            if ($parentType && !$parentVal) { flash('Pick which ' . $parentType['label'] . ' this belongs under.', 'error'); redirect('/lookup?key=' . $t['type_key']); }
            if ($editId) {
                $pdo->prepare("UPDATE lookup_values SET label=?, code=?, parent_value_id=? WHERE id=? AND type_id=?")
                    ->execute([$label, $code, $parentVal, $editId, $t['id']]);
                flash('Value updated.');
            } else {
                lk_add_value($t['id'], $parentVal, $code, $label, 99);
                flash('Value added.');
            }
            redirect('/lookup?key=' . $t['type_key']);
        }
        if (($_GET['del'] ?? '') !== '') {
            $pdo->prepare("DELETE FROM lookup_values WHERE id=? AND type_id=?")->execute([(int)$_GET['del'], $t['id']]);
            flash('Value removed.');
            redirect('/lookup?key=' . $t['type_key']);
        }
        $parentValues = $parentType ? lk_all_values($parentType['id']) : [];
        $editRow = ($_GET['edit'] ?? '') !== '' ? lk_value((int)$_GET['edit']) : null;
        view('ops/lookup_values', ['t' => $t, 'parentType' => $parentType, 'values' => lk_all_values($t['id']), 'parentValues' => $parentValues, 'editRow' => $editRow]);
        return;
    }

    if ($route === 'custom-fields') {
        $allowedEntities = array_merge(['call', 'job', 'partner'], array_keys(ops_masters()));
        $entity = in_array($_GET['entity'] ?? 'call', $allowedEntities, true) ? $_GET['entity'] : 'call';
        if ($method === 'POST') {
            $label = trim($_POST['label'] ?? '');
            $type = in_array($_POST['field_type'] ?? '', ['text', 'number', 'date', 'select', 'dependent'], true) ? $_POST['field_type'] : 'text';
            $lt = ($_POST['lookup_type_id'] ?? '') !== '' ? (int)$_POST['lookup_type_id'] : null;
            $req = !empty($_POST['required']) ? 1 : 0;
            if ($label === '') { flash('Enter a field label.', 'error'); redirect('/custom-fields?entity=' . $entity); }
            if (in_array($type, ['select', 'dependent'], true) && !$lt) { flash('Pick which master list this dropdown uses.', 'error'); redirect('/custom-fields?entity=' . $entity); }
            $fkey = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]+/', '_', $label)));
            $pdo->prepare("INSERT INTO custom_fields (entity,field_key,label,field_type,lookup_type_id,required,sort_order,active,created_at)
                VALUES (?,?,?,?,?,?,?,1,?)")->execute([$entity, $fkey, $label, $type, $lt, $req, 99, date('c')]);
            flash("Field \"$label\" added to the " . ($entity === 'call' ? 'Call' : 'Job') . " form.");
            redirect('/custom-fields?entity=' . $entity);
        }
        if (($_GET['del'] ?? '') !== '') {
            $pdo->prepare("DELETE FROM custom_fields WHERE id=? AND entity=?")->execute([(int)$_GET['del'], $entity]);
            flash('Field removed.');
            redirect('/custom-fields?entity=' . $entity);
        }
        view('ops/custom_fields', ['entity' => $entity, 'fields' => custom_fields_for($entity, false), 'types' => lk_types(), 'entities' => $allowedEntities]);
        return;
    }
}

// ---- Render helpers (used inside views) ------------------------------------
// Render all custom fields for an entity as .ff blocks; $vals = custom_values_map().
function render_custom_fields($entity, $vals = []) {
    foreach (custom_fields_for($entity) as $f) {
        $key = 'cf_' . $f['field_key'];
        $cur = $vals[$f['field_key']] ?? null;
        echo '<div class="ff">';
        echo '<label>' . e($f['label']) . ($f['required'] ? ' *' : '') . '</label>';
        if ($f['field_type'] === 'text' || $f['field_type'] === 'number' || $f['field_type'] === 'date') {
            $type = $f['field_type'] === 'text' ? 'text' : $f['field_type'];
            echo '<input class="form-control" type="' . $type . '" name="' . e($key) . '" value="' . e($cur['value_text'] ?? '') . '"' . ($f['required'] ? ' required' : '') . '>';
        } elseif ($f['field_type'] === 'select') {
            $t = lk_type_by_id($f['lookup_type_id']);
            echo '<select class="form-control searchable" name="' . e($key) . '"><option value="">—</option>';
            if ($t) foreach (lk_root_values($t['id']) as $v) {
                $sel = $cur && (int)$cur['value_id'] === (int)$v['id'] ? ' selected' : '';
                echo '<option value="' . (int)$v['id'] . '"' . $sel . '>' . e($v['label']) . '</option>';
            }
            echo '</select>';
        } elseif ($f['field_type'] === 'dependent') {
            render_cascade_field($f, $cur ? (int)$cur['value_id'] : 0);
        }
        echo '</div>';
    }
}
// Cascading selects for a hierarchical lookup (root → … → leaf). Stores leaf id.
function render_cascade_field($f, $selectedLeafId = 0) {
    $chain = lk_chain($f['lookup_type_id']);
    if (!$chain) return;
    // Determine the selected value at each level from the stored leaf id.
    $selPath = [];
    if ($selectedLeafId) {
        $v = lk_value($selectedLeafId); $g = 0;
        while ($v && $g++ < 10) { array_unshift($selPath, (int)$v['id']); $v = $v['parent_value_id'] ? lk_value($v['parent_value_id']) : null; }
    }
    $fieldName = 'cf_' . $f['field_key'];
    $data = ['levels' => count($chain), 'byType' => []];
    foreach ($chain as $lvl => $t) {
        $rows = $lvl === 0 ? lk_root_values($t['id']) : lk_all_values($t['id']);
        $data['byType'][$lvl] = array_map(fn($r) => ['id' => (int)$r['id'], 'label' => $r['label'], 'parent' => $r['parent_value_id'] !== null ? (int)$r['parent_value_id'] : null], $rows);
    }
    echo '<div class="cascade" data-field="' . e($f['field_key']) . '">';
    foreach ($chain as $lvl => $t) {
        $isLeaf = $lvl === count($chain) - 1;
        $name = $isLeaf ? $fieldName : '';
        $sel = $selPath[$lvl] ?? 0;
        echo '<select class="form-control cascade-sel"' . ($name ? ' name="' . e($name) . '"' : '') . ' data-level="' . $lvl . '" data-field="' . e($f['field_key']) . '">';
        echo '<option value="">' . e($t['label']) . '…</option>';
        $rows = $lvl === 0 ? lk_root_values($t['id']) : lk_all_values($t['id']);
        foreach ($rows as $r) {
            // On first render only show the correct options per selected parent; JS keeps it in sync after.
            if ($lvl > 0) {
                $parentSel = $selPath[$lvl - 1] ?? 0;
                if (!$parentSel || (int)$r['parent_value_id'] !== (int)$parentSel) continue;
            }
            $s = ($sel && (int)$r['id'] === (int)$sel) ? ' selected' : '';
            echo '<option value="' . (int)$r['id'] . '"' . $s . '>' . e($r['label']) . '</option>';
        }
        echo '</select>';
    }
    echo '<script>window.LKDATA=window.LKDATA||{};window.LKDATA[' . json_encode($f['field_key']) . ']=' . json_encode($data) . ';</script>';
    echo '</div>';
}
