<?php
// ============================================================================
//  EXAACT — HIRING REQUEST  (Phase 2 · M4)
//
//  The audit found one thing genuinely missing: a REQUEST LAYER.
//
//  A requisition is created directly, already OPEN — a status whose own label
//  reads "Open (approved, sourcing)" — and candidates can be attached to it at
//  once. Nothing distinguished "we would like to hire" from "this is approved,
//  start sourcing". That distinction is what this adds, and it is the only new
//  object M4 introduces.
//
//  WHAT THIS IS NOT. A hiring request is not a requisition, not a position, not
//  a candidate and not an approval. It is the business request to recruit:
//
//      Requestor -> Hiring Request -> [Phase 3 approval] -> Requisition -> execution
//
//  WHAT IS REUSED RATHER THAN REBUILT:
//    · Department      the canonical vocabulary from M2/M3 (identity, not text)
//    · Designation     the canonical vocabulary
//    · Position        the establishment, with its five-case manpower check
//    · Quantity        M3's model; see the note on authority below
//    · Branch          offices + the existing scope architecture
//    · Custom fields   the existing engine, entity 'hiring_request'
//    · Approval        recruit_approval_requests is entity-agnostic, so Phase 3
//                      plugs in without a new engine. M4 adds the hooks only.
//
//  QUANTITY AUTHORITY. Two numbers, because they are two facts:
//      hiring_requests.quantity   how many were ASKED for
//      requisitions.quantity      how many are being EXECUTED (M3's, unchanged)
//  Approval may cut ten to six; both numbers then matter, and neither is a copy
//  of the other. No third quantity exists.
// ============================================================================

// The request's own lifecycle — deliberately NOT the requisition's. A requisition
// says how execution is going; a request says whether execution may begin.
const HREQ_STATUS = [
    'DRAFT'        => 'Draft',
    'SUBMITTED'    => 'Submitted',
    'UNDER_REVIEW' => 'Under review',
    'APPROVED'     => 'Approved',
    'REJECTED'     => 'Rejected',
    'CANCELLED'    => 'Cancelled',
];
// The states from which recruitment may begin. Phase 3 will enforce the routing
// that reaches APPROVED; M4 only has to make the boundary expressible.
const HREQ_EXECUTABLE = ['APPROVED'];

// ===========================================================================
//  PHASE 3 · M4 — RE-APPROVAL & REQUISITION CONTROL
//
//  The lifecycle statuses above are UNCHANGED. docs/03-object-lifecycles.md is
//  the authority for them and M4 adds none: re-approval is an additive attribute
//  OF an approved request, so an approved request stays APPROVED throughout.
//
//      NONE         approved and unchanged                      executable
//      REQUIRED     approved, then materially changed           blocked
//      IN_PROGRESS  the re-approval chain is running            blocked
//      REAPPROVED   re-approved against the new snapshot        executable
//      REJECTED     the re-approval was refused                 blocked
//
//  Cancellation is not here — it is the lifecycle status CANCELLED.
const HREQ_REAPPROVAL = [
    'NONE'        => 'Approved',
    'REQUIRED'    => 'Re-approval required',
    'IN_PROGRESS' => 'Re-approval in progress',
    'REAPPROVED'  => 'Re-approved',
    'REJECTED'    => 'Re-approval rejected',
];
//  The states in which recruitment must NOT run.
const HREQ_REAPPROVAL_BLOCKS = ['REQUIRED', 'IN_PROGRESS', 'REJECTED'];

//  THE AUTHORITATIVE MATERIAL-CHANGE SET — docs/phase3/M4-MATERIAL-CHANGE-MATRIX.md.
//  One rule, one place. Nothing else in the product decides materiality.
//
//  'quantity' is deliberately absent: it is asymmetric and is judged by
//  hreq_material_diff() — an INCREASE spends authority nobody granted, a
//  DECREASE stays inside the approval. Treating them alike would force a
//  re-approval on a manager asking for fewer people, which teaches people to
//  route around the control.
const HREQ_MATERIAL_FIELDS = [
    'hiring_department_id'   => 'the department the person joins',
    'designation'            => 'the role',
    'grade'                  => 'the pay band',
    'position_id'            => 'the establishment seat',
    'new_position_requested' => 'whether a new seat is being asked for',
    'job_title'              => 'what was approved, in the approver\'s words',
    'employment_type'        => 'permanent, contract or temporary',
    'office_id'              => 'the branch the cost belongs to, and the approval chain',
    'work_location'          => 'where the person actually works',
    'client_id'              => 'the client contract it is billed against',
    'request_type'           => 'the basis on which it was authorised',
    'requested_by_id'        => 'the identity segregation of duties was judged against',
];

// Starter vocabularies. Each is created through the EXISTING lookup engine, so a
// customer can extend it on day one — none of these lists existed before.
const HREQ_PRIORITIES = [
    'LOW' => 'Low', 'NORMAL' => 'Normal', 'HIGH' => 'High',
    'URGENT' => 'Urgent', 'CRITICAL' => 'Critical',
];
const HREQ_EMPLOYMENT_TYPES = [
    'PERMANENT' => 'Permanent', 'CONTRACT' => 'Fixed-term contract',
    'TEMPORARY' => 'Temporary', 'PART_TIME' => 'Part time',
    'CONSULTANT' => 'Consultant / freelance', 'INTERN' => 'Intern / trainee',
];
// §8 asks for these request types. The application already has a configurable
// `requisition_type` list holding NEW and REPLACEMENT, so these EXTEND it rather
// than starting a second vocabulary for the same concept.
const HREQ_EXTRA_REQUEST_TYPES = [
    'ADDITIONAL_MANPOWER' => 'Additional manpower (same role, more people)',
    'TEMPORARY'           => 'Temporary / short-term need',
    'PROJECT'             => 'Project-specific requirement',
    'BACKFILL'            => 'Backfill (cover for leave / secondment)',
    'CONTRACT'            => 'Against a client contract',
    'OTHER'               => 'Other',
];

function hreq_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    $pk = (function_exists('db_driver') && db_driver() === 'mysql')
        ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS hiring_requests (
            id $pk,
            req_no VARCHAR(40) DEFAULT '',
            status VARCHAR(20) DEFAULT 'DRAFT',

            -- WHO is asking. A canonical identity, not a typed-in name (§6).
            requested_by_id INT NULL,
            requested_by_name VARCHAR(150) DEFAULT '',   -- display only; history keeps its own words
            requesting_department_id INT NULL,           -- the department ASKING

            -- WHAT is needed.
            hiring_department_id INT NULL,               -- the department the person will JOIN
            designation VARCHAR(60) DEFAULT '',
            grade VARCHAR(80) DEFAULT '',
            position_id INT NULL,                        -- an existing establishment seat…
            new_position_requested INT DEFAULT 0,        -- …or none, and one is being asked for (§15)
            job_title VARCHAR(200) DEFAULT '',
            job_description TEXT,                        -- specific to THIS request (§11)
            quantity INT DEFAULT 1,

            -- WHERE.
            office_id INT NULL,
            work_location VARCHAR(200) DEFAULT '',
            client_id INT NULL,
            project_ref VARCHAR(200) DEFAULT '',

            -- WHEN and HOW.
            required_by VARCHAR(20) DEFAULT '',
            employment_type VARCHAR(30) DEFAULT '',
            request_type VARCHAR(40) DEFAULT '',
            priority VARCHAR(20) DEFAULT 'NORMAL',

            -- WHY.
            reason VARCHAR(400) DEFAULT '',

            -- Approval boundary. M4 records the state; Phase 3 does the routing.
            approval_required INT DEFAULT 1,
            approval_ref VARCHAR(80) DEFAULT '',
            decided_by VARCHAR(150) DEFAULT '',
            decided_at VARCHAR(30) DEFAULT '',
            decision_note VARCHAR(400) DEFAULT '',

            -- What it meant when it was submitted, so a later master edit cannot
            -- silently change the business meaning of an approved request (§12).
            snapshot_json TEXT,
            submitted_at VARCHAR(30) DEFAULT '',

            created_by VARCHAR(150) DEFAULT '',
            created_at VARCHAR(30) DEFAULT '',
            updated_by VARCHAR(150) DEFAULT '',
            updated_at VARCHAR(30) DEFAULT '')");
        db()->exec("CREATE INDEX IF NOT EXISTS idx_hreq_status ON hiring_requests (status)");
        db()->exec("CREATE INDEX IF NOT EXISTS idx_hreq_office ON hiring_requests (office_id)");
        db()->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_hreq_no ON hiring_requests (req_no)");
    } catch (Throwable $e) {}

    // The execution record points back at the request it came from. Additive:
    // a requisition raised directly carries NULL, exactly as it always has (§19).
    if (function_exists('ensure_column')) {
        try { ensure_column('requisitions', 'hiring_request_id', 'INT NULL'); } catch (Throwable $e) {}
        //  M4 §4 — additive, idempotent, non-destructive, forward-only. Nothing is
        //  dropped or rewritten: an existing approved request keeps its status, its
        //  decision and its submitted snapshot, and simply gains 'NONE'.
        try { ensure_column('hiring_requests', 'reapproval_state',       "VARCHAR(20) DEFAULT 'NONE'"); } catch (Throwable $e) {}
        try { ensure_column('hiring_requests', 'approved_snapshot_json', 'TEXT'); } catch (Throwable $e) {}
        try { ensure_column('hiring_requests', 'approved_snapshot_at',   "VARCHAR(30) DEFAULT ''"); } catch (Throwable $e) {}
        try { ensure_column('hiring_requests', 'reapproval_started_at',  "VARCHAR(30) DEFAULT ''"); } catch (Throwable $e) {}
    }

    // Vocabularies, through the engine that already exists.
    if (function_exists('lk_ensure_type_map')) {
        try {
            lk_ensure_type_map('hr_priority', 'Hiring priority', HREQ_PRIORITIES, 'People');
            lk_ensure_type_map('employment_type', 'Employment type', HREQ_EMPLOYMENT_TYPES, 'People');
            lk_ensure_type_map('hiring_request_status', 'Hiring request status', HREQ_STATUS, 'People');
        } catch (Throwable $e) {}
    }
    // §8 — extend the request-type list the application already has.
    if (function_exists('lk_type') && function_exists('lk_ensure_value')) {
        try {
            if (lk_type('requisition_type'))
                foreach (HREQ_EXTRA_REQUEST_TYPES as $c => $l) lk_ensure_value('requisition_type', $c, $l);
        } catch (Throwable $e) {}
    }
}

function hreq_priorities()       { return lk_options_or('hr_priority', HREQ_PRIORITIES); }
function hreq_employment_types() { return lk_options_or('employment_type', HREQ_EMPLOYMENT_TYPES); }
function hreq_statuses()         { return lk_options_or('hiring_request_status', HREQ_STATUS); }
// The union of what the workspace's own list holds and the types M4 adds.
// hreq_migrate() persists the additions, but the lookup list is cached for the
// life of a request — so on the very request that first creates them, reading
// the list alone would still show the old two. The union makes it immediate and
// is a no-op from the next request onward.
function hreq_request_types() {
    $live = lk_options_or('requisition_type', defined('REQ_TYPES') ? REQ_TYPES : []);
    return $live + HREQ_EXTRA_REQUEST_TYPES;
}

function hreq_now() { return function_exists('now_iso') ? now_iso() : date('c'); }
function hreq_who() {
    if (!function_exists('current_user')) return '';
    $u = current_user(); if (!$u) return '';
    return function_exists('user_name') ? (string) user_name($u) : (string) ($u['username'] ?? '');
}

// ---- Terminology lock (M4 correction §1) -----------------------------------
//  THREE DIFFERENT BUSINESS OBJECTS, THREE DIFFERENT WORDS. The application
//  holds three things a person could loosely call a "requirement", and they are
//  not the same thing, not the same table and not the same lifecycle:
//
//    HIRING REQUEST           hiring_requests   the business ASK to recruit
//    RECRUITMENT REQUISITION  requisitions      the approved EXECUTION record
//    MARKETPLACE REQUIREMENT  cx_requirements   a CLIENT-POSTED demand (Connect)
//
//  None of them is being renamed, merged or replaced. What is fixed here is the
//  WORD ON THE SCREEN: a bare, unexplained "Requirement" must never appear on a
//  recruitment screen, because a workspace that also uses the marketplace would
//  have no way of telling which of the three it is looking at.
//
//  A workspace may still choose its own word for the execution record through
//  the EXISTING terminology engine (Settings → Terminology; the recruitment
//  industry pack does exactly that). If the word it chooses is the bare
//  "Requirement", it is QUALIFIED here — "Recruitment Requirement" — rather than
//  shown plain. Nothing else about its chosen vocabulary is touched.
const HREQ_TERMS = [
    'request'     => ['Hiring Request', 'Hiring Requests'],
    'requisition' => ['Recruitment Requisition', 'Recruitment Requisitions'],
    'marketplace' => ['Marketplace Requirement', 'Marketplace Requirements'],
];
// The words that are ambiguous on their own and must always carry a qualifier.
const HREQ_AMBIGUOUS = ['requirement', 'requirements'];

function hreq_label($what = 'request', $plural = false) {
    $i = $plural ? 1 : 0;
    if ($what === 'requisition') {
        // Honour the workspace's own word, then qualify it if it is ambiguous.
        $own = function_exists('T') ? trim((string) ($plural ? TP('requisition') : T('requisition'))) : '';
        if ($own === '') return HREQ_TERMS['requisition'][$i];
        if (in_array(mb_strtolower($own), HREQ_AMBIGUOUS, true)) return 'Recruitment ' . $own;
        return $own;
    }
    return HREQ_TERMS[$what][$i] ?? HREQ_TERMS['request'][$i];
}

// ---- Capability model (M4 correction §2) -----------------------------------
//  WHO MAY RAISE A HIRING REQUEST IS A CAPABILITY QUESTION, NOT A ROLE-NAME ONE.
//
//  M4 first shipped this gate as is_coordinator_level() — a role BAND. That was
//  wrong twice over. It named roles rather than the right, and the band it names
//  (the seven management roles + Asst. Manager + Coordinator) is WIDER than the
//  permission matrix: docs/02-permission-matrix.md gives Asst. Manager no hiring
//  right at all, yet the band let one raise a request.
//
//  The capability already exists and it is not a new permission. ACCESS_MODULES
//  generates mod.<module>.view and mod.<module>.edit for every module; they are
//  granted per role AND per user on Settings → Roles & permissions, and they are
//  enforced through the same can() choke point as every other right — licence
//  first, then master, then the granted set. 'hiring' is one of those modules:
//
//      VIEW    mod.hiring.view   already enforced for these routes by
//                                ops_module_gate() before dispatch
//      CREATE  mod.hiring.edit   the existing "may add / edit in recruitment"
//      EDIT    mod.hiring.edit   right — the very capability project costing
//      SUBMIT  mod.hiring.edit   already requires before it may create a
//      CANCEL  mod.hiring.edit   requisition (projcosting.php)
//      DECIDE  hreq_can_decide() — deliberately NOT the same right
//
//  No new permission is introduced, and no second permission system is created.
//  Asking can() rather than a role band also means the request layer now obeys
//  the module licence, which a role-name check never did.
function hreq_can_view()   { return function_exists('can') && can('mod.hiring.view'); }
function hreq_can_create() { return function_exists('can') && can('mod.hiring.edit'); }

//  Approval authority is held APART from creation authority on purpose — that
//  separation is the whole point of a request layer. Deciding therefore stays
//  where the application already puts recruitment approval: a management act
//  (an offer is approved by is_admin_level(), recruit_offer.php). Phase 3
//  replaces this caller with the configured approval matrix; until it does, the
//  right is not widened and it is not the create right.
//
//  The module question is asked FIRST, and that is not decoration. M10 keeps a
//  permanent guard that signs in as a master holding core administration alone
//  and calls every gate predicate in lib/; anything that still opens has escaped
//  the licence. Written as is_admin_level() alone this gate opened — a role band
//  knows nothing about what the workspace has bought. Asking can() first puts it
//  back behind licence -> module -> capability, like every other right.
function hreq_can_decide() {
    if (!hreq_can_view()) return false;
    return function_exists('is_admin_level') && is_admin_level();
}

//  Segregation of duties. The requestor must not also be the approver. The
//  canonical requestor relationship is hiring_requests.requested_by_id → users.id,
//  so that is what is compared — not a name, not a role.
//
//  There is ONE exception, and it is the one the application already states
//  elsewhere: a master. A single-administrator workspace has nobody else to
//  approve, and the same standing exception is written into report finalisation
//  ("the approver and the issuer cannot be the same", idems.php). It is an
//  exception to SEGREGATION only — the licence, the module capability and the
//  branch scope all still apply above it.
//  Branch scope, asked at the write as well as on the route. Found by mutation
//  testing: disabling hreq_scope_gate() entirely broke nothing, because the only
//  thing holding the gate in place was a source-level assertion, and the acts
//  that follow it — submit, decide, cancel, convert — trusted the route to have
//  asked. A route gate is the right place to REFUSE EARLY; it is not the right
//  place to be the only check.
function hreq_in_scope($r) {
    if (!is_array($r) || !function_exists('scope_allows')) return true;
    return (bool) scope_allows($r['office_id'] ?? null, null);
}

function hreq_is_own_request($r) {
    if (!is_array($r) || !function_exists('current_user')) return false;
    $u = current_user(); if (!$u) return false;
    $me = (int) ($u['id'] ?? 0);
    return $me > 0 && (int) ($r['requested_by_id'] ?? 0) === $me;
}
//  Phase 3 · M1 — the segregation rule alone, so the approval chain and the
//  direct decision ask exactly the same question. One rule, two readers. The
//  master exception is M4's, stated once, here, and neither broadened nor
//  narrowed by M1.
function hreq_segregation_blocks($r) {
    if (function_exists('is_master') && is_master()) return false;
    return hreq_is_own_request(is_array($r) ? $r : hreq_get($r));
}
function hreq_may_decide($r) {
    if (!hreq_can_decide()) return false;
    return !hreq_segregation_blocks($r);
}

// ---- The approval chain (Phase 3 · M1) -------------------------------------
//  The chain currently open against this request, or null. The engine is
//  entity-agnostic, so this is a read of the existing tables — not a new store.
function hreq_approval($id) {
    if (!function_exists('appr_open')) return null;
    $open = appr_open('HIRING_REQUEST', (int) $id);
    if ($open) return $open;
    try { return ops_one("SELECT * FROM recruit_approval_requests WHERE entity='HIRING_REQUEST' AND entity_id=? ORDER BY id DESC LIMIT 1", [(int) $id]) ?: null; }
    catch (Throwable $e) { return null; }
}
// Its steps, for the history panel. Empty when no chain was ever started.
function hreq_approval_steps($id) {
    $a = hreq_approval($id);
    return ($a && function_exists('appr_steps')) ? appr_steps((int) $a['id']) : [];
}
// What the rule matcher is given. Department goes through the canonical label so
// a rule written in the customer's own words still matches (M3).
function hreq_appr_ctx(array $r) {
    $deptLbl = '';
    if (!empty($r['hiring_department_id']) && function_exists('vocab_value') && ($v = vocab_value((int) $r['hiring_department_id'])))
        $deptLbl = (string) ($v['code'] !== '' ? $v['code'] : $v['label']);
    return [
        'department'  => $deptLbl,
        'sbu'         => '',
        'grade'       => (string) ($r['grade'] ?? ''),
        'position'    => (string) ($r['designation'] ?? ''),
        'position_id' => (int) ($r['position_id'] ?? 0),
        // M2 — the branch dimension. A customer can now write "Ahmedabad hires
        // need the branch manager", and the quantity is what an amount band
        // reads for a hiring request: headcount, not money.
        'office_id'   => (int) ($r['office_id'] ?? 0),
        'amount'      => (int) ($r['quantity'] ?? 0),
    ];
}

function hreq_get($id) {
    hreq_migrate();
    try { $r = ops_one("SELECT * FROM hiring_requests WHERE id=?", [(int) $id]); }
    catch (Throwable $e) { return null; }
    return $r ?: null;
}

// Its own stable reference, distinct from the requisition's (§21, §22).
function hreq_next_no() {
    hreq_migrate();
    $yr = date('Y');
    try {
        $last = ops_val("SELECT req_no FROM hiring_requests WHERE req_no LIKE ? ORDER BY req_no DESC LIMIT 1", ["HRQ-$yr-%"]);
    } catch (Throwable $e) { $last = null; }
    $seq = ($last && preg_match('/-(\d{6})$/', (string) $last, $m)) ? (int) $m[1] + 1 : 1;
    return sprintf('HRQ-%s-%06d', $yr, $seq);
}

// May recruitment begin against this request? The whole point of the layer.
//  M4 §13 — THE AUTHORITATIVE RUNTIME BOUNDARY. There is no second one: every
//  execution path asks this, and it now weighs the re-approval attribute as well
//  as the lifecycle status.
function hreq_is_executable($req) {
    $r = is_array($req) ? $req : hreq_get($req);
    if (!$r) return false;
    if (empty($r['approval_required'])) return true;          // a workspace that does not require approval
    if (!in_array(strtoupper((string) $r['status']), HREQ_EXECUTABLE, true)) return false;
    return !in_array(hreq_reapproval_state($r), HREQ_REAPPROVAL_BLOCKS, true);
}

//  Why recruitment cannot run, in words a coordinator can act on. Returns '' when
//  it can — so a caller can use it directly as the refusal message.
function hreq_block_reason($req) {
    $r = is_array($req) ? $req : hreq_get($req);
    if (!$r) return 'That hiring request no longer exists.';
    if (hreq_is_executable($r)) return '';
    $st = hreq_reapproval_state($r);
    if (in_array($st, HREQ_REAPPROVAL_BLOCKS, true)) {
        if ($st === 'REJECTED')
            return 'The change to this approved request was not re-approved. Recruitment stays paused.';
        return 'This approved request has been changed in a way that needs re-approval. '
             . 'Recruitment is paused until it is re-approved.';
    }
    return 'This request is ' . strtolower((string) $r['status'])
         . '. Recruitment cannot start until it is approved.';
}

//  M4 §13/§17 — THE GATE EVERY EXECUTION PATH ASKS, given a requisition.
//  Returns '' when recruitment may proceed, or the reason it may not.
//
//  §16 / ADR-001 — a requisition raised directly carries hiring_request_id NULL.
//  It has no approved request to enforce against, and inventing an approved
//  headcount from nowhere would be a policy change, not a control. Such a
//  requisition proceeds, still subject to every existing RBAC, entitlement,
//  scope and business rule.
function hreq_req_block_reason($requisitionId) {
    $rid = (int) $requisitionId;
    if ($rid <= 0) return '';
    try { $row = ops_one("SELECT hiring_request_id FROM requisitions WHERE id=?", [$rid]); }
    catch (Throwable $e) { return ''; }
    $hid = (int) ($row['hiring_request_id'] ?? 0);
    if ($hid <= 0) return '';                       // ADR-001 — direct requisition
    return hreq_block_reason($hid);
}

// ============================================================================
//  ADR-001 — DECIDED. "Recruitment may only start from an approved request."
//
//  EXAACT has always had two ways into recruitment: the governed one (Hiring
//  Request → approval → Requisition) and the direct one (a Requisition raised
//  straight away, hiring_request_id NULL). ADR-001 left the choice to the owner
//  because it is a product policy, not a technical question: a manpower-services
//  business is authorised by its CLIENT'S order and needs the direct route,
//  while an employer hiring into its own establishment needs the approval to
//  BE the control. The owner has now chosen the second.
//
//  Implemented as ADR-001 recommended — option (c), a workspace policy — rather
//  than a hard-coded block, because the right answer differs per customer and
//  one database per tenant means each can hold its own. A workspace that needs
//  the direct route switches it off on Admin → System settings; nothing here is
//  irreversible.
//
//  THREE THINGS THIS DELIBERATELY DOES NOT DO:
//
//  1. It does not touch requisitions that ALREADY EXIST (ADR-001 consequence
//     §1, M4 §39). They stay valid, editable, recruitable and closeable. A
//     requirement raised before the policy was switched on is not retro-fitted
//     with an invented approval, and is not stranded either — inventing an
//     approved headcount from nowhere would be a fiction, and blocking work on
//     live requirements would be an outage.
//  2. It does not narrow any PERMISSION. The same people may create
//     requisitions; they must now start from an approved request. ADR-001's
//     option (d) — aligning the direct route's role band with the capability the
//     governed route uses — is a separate change needing its own regression
//     pass, and stays open.
//  3. It never blocks the GOVERNED route. hreq_to_requisition() is how an
//     approved request becomes a requisition, and gating that would close the
//     only door left.
//
//  Returns '' when creating is allowed, or the sentence to show the person.
// ============================================================================
function hreq_direct_path_allowed() {
    if (!function_exists('setting_get')) return true;
    // Default ENFORCED, per the owner's decision. A workspace that recruits on a
    // client's order rather than its own approval turns this off.
    return (string) setting_get('requisition_requires_request', '1') !== '1';
}

function hreq_direct_path_block_reason() {
    if (hreq_direct_path_allowed()) return '';
    $reqL = function_exists('hreq_label') ? mb_strtolower(hreq_label('requisition')) : 'requisition';
    $hrqL = function_exists('hreq_label') ? mb_strtolower(hreq_label('request'))     : 'hiring request';
    return 'This workspace starts recruitment from an approved ' . $hrqL . '. Raise a '
         . $hrqL . ', get it approved, then use “Start recruiting” on it — that creates the '
         . $reqL . ' for you, carrying the approved headcount across.';
}

//  M4 §14 — THE HEADCOUNT CEILING, for every write that can change executable
//  quantity. Returns '' when the quantity is allowed, or the refusal.
//
//  $exclude is the requisition being edited, so its own current seats are not
//  counted against it.
function hreq_qty_guard($hiringRequestId, $wantQty, $excludeRequisitionId = 0) {
    $hid = (int) $hiringRequestId;
    if ($hid <= 0) return '';                       // ADR-001 — nothing to enforce against
    $r = hreq_get($hid);
    if (!$r) return '';
    $approved = hreq_approved_qty($r);
    $others = 0;
    try {
        $others = (int) ops_val("SELECT COALESCE(SUM(quantity),0) FROM requisitions
                                 WHERE hiring_request_id=? AND id<>? AND UPPER(COALESCE(status,'')) <> 'CANCELLED'",
                                [$hid, (int) $excludeRequisitionId]);
    } catch (Throwable $e) { $others = 0; }
    $want = max(0, (int) $wantQty);
    if ($others + $want <= $approved) return '';
    $left = max(0, $approved - $others);
    return 'The approved headcount for this hiring request is ' . $approved
         . '. ' . ($others > 0 ? $others . ' already on other requisitions, so ' : '')
         . 'only ' . $left . ' can be recruited here.';
}

//  The same ceiling, applied as a COMPENSATING check after a write that has
//  already happened. Two processes may both write and both then see the total
//  broken; both revert and both refuse, so the headcount is never over-allocated
//  — the worst case is a refusal that could in principle have succeeded, which is
//  the safe direction for a control of this kind (§25).
function hreq_qty_enforce_after_write($requisitionId, $previousQty) {
    $rid = (int) $requisitionId;
    try { $row = ops_one("SELECT hiring_request_id, quantity FROM requisitions WHERE id=?", [$rid]); }
    catch (Throwable $e) { return ''; }
    if (!$row) return '';
    $hid = (int) ($row['hiring_request_id'] ?? 0);
    if ($hid <= 0) return '';
    $why = hreq_qty_guard($hid, (int) $row['quantity'], $rid);
    if ($why === '') return '';
    try { db()->prepare("UPDATE requisitions SET quantity=? WHERE id=?")->execute([(int) $previousQty, $rid]); }
    catch (Throwable $e) {}
    if (function_exists('act_log'))
        act_log('HIRING_REQUEST', $hid, 'SYSTEM',
                'Refused: a requisition quantity change that would exceed the approved headcount',
                ['auto' => 1, 'outcome' => 'OVER_ALLOCATION_REFUSED']);
    return $why;
}

function hreq_reapproval_state($r) {
    $v = strtoupper(trim((string) (is_array($r) ? ($r['reapproval_state'] ?? '') : '')));
    return array_key_exists($v, HREQ_REAPPROVAL) ? $v : 'NONE';
}

//  The immutable approved snapshot — what the approver actually approved.
function hreq_approved_snapshot($r) {
    $raw = is_array($r) ? (string) ($r['approved_snapshot_json'] ?? '') : '';
    if ($raw === '') return null;
    $d = json_decode($raw, true);
    return is_array($d) ? $d : null;
}

//  M4 §6 — MATERIALITY IS JUDGED AGAINST THE APPROVED SNAPSHOT, never against
//  the previous edit. Ten harmless edits followed by one material one must still
//  be compared with what the approver saw.
//
//  Returns the list of material differences; empty means nothing material moved.
function hreq_material_diff($r, array $incoming = null) {
    $snap = hreq_approved_snapshot($r);
    if (!$snap || !is_array($snap['fields'] ?? null)) return [];   // never approved: nothing to invalidate
    $was  = $snap['fields'];
    $now  = is_array($incoming) ? ($incoming + (array) $r) : (array) $r;
    $out  = [];
    foreach (HREQ_MATERIAL_FIELDS as $f => $why) {
        $a = $was[$f] ?? null; $b = $now[$f] ?? null;
        //  Ids and flags compare as integers, text as trimmed strings, so a
        //  NULL/0/'' shuffle is not reported as a business change.
        $norm = fn($v) => is_numeric($v) ? (string) (int) $v : strtolower(trim((string) $v));
        if ($norm($a) !== $norm($b)) $out[$f] = ['was' => $a, 'now' => $b, 'why' => $why];
    }
    //  §7 — quantity, asymmetrically. Up is material; down is not, and both are
    //  audited by the caller either way.
    $qWas = (int) ($was['quantity'] ?? 0); $qNow = (int) ($now['quantity'] ?? $qWas);
    if ($qNow > $qWas)
        $out['quantity'] = ['was' => $qWas, 'now' => $qNow, 'why' => 'more headcount than was authorised'];
    return $out;
}

// ---- Save -----------------------------------------------------------------
// Returns [ok, message, id]. Validates against the real masters rather than
// trusting what the browser sent — a dropdown is not a security boundary (§37).
function hreq_save($id, array $post) {
    // The right is asked HERE, where the write happens, and not only on the
    // route — so a helper called directly, an AJAX handler added later or a
    // Phase-3 caller cannot reach the table around the capability (§5).
    if (!hreq_can_create())
        return [false, 'You do not have the right to raise or change a hiring request.', 0];
    hreq_migrate();
    $id = (int) $id;
    $existing = $id > 0 ? hreq_get($id) : null;
    if ($id > 0 && !$existing) return [false, 'That hiring request no longer exists.', 0];

    //  PHASE 3 · M4 — THE RE-APPROVAL PATH. An approved request used to be simply
    //  immutable ("…which is not built yet"). It can now be changed by anyone with
    //  the edit right, and what happens next depends entirely on WHAT changed,
    //  judged against the snapshot the approver actually approved.
    $wasApproved = $existing && strtoupper((string) $existing['status']) === 'APPROVED';
    if ($wasApproved) {
        //  §8 — approval_required is not a field, it is the CONTROL. Turning it off
        //  after approval would retire the approval entirely, so it is refused
        //  outright and no re-approval route is offered for it. A crafted POST
        //  reaches this line exactly as the form does.
        if (array_key_exists('approval_required', $post)
            && (int) !empty($post['approval_required']) !== (int) !empty($existing['approval_required'])) {
            if (function_exists('act_log'))
                act_log('HIRING_REQUEST', (int) $id, 'SYSTEM',
                        'Refused: an attempt to change the approval requirement on an approved request',
                        ['auto' => 1, 'outcome' => 'DENIED']);
            return [false, 'The approval requirement of an approved request cannot be changed.', 0];
        }
    }
    if ($existing && !$wasApproved && strtoupper((string) $existing['status']) === 'APPROVED')
        return [false, 'This request is approved.', 0];
    if ($existing && in_array(strtoupper((string) $existing['status']), ['REJECTED', 'CANCELLED'], true))
        return [false, 'A ' . strtolower($existing['status']) . ' request cannot be edited.', 0];
    if ($existing && !hreq_in_scope($existing))
        return [false, 'This hiring request is outside your office / branch scope.', 0];

    $qty = (int) ($post['quantity'] ?? 1);
    if ($qty <= 0) return [false, 'How many people are needed? Enter at least one.', 0];

    $title = trim((string) ($post['job_title'] ?? ''));
    if ($title === '') return [false, 'Say what is being requested.', 0];

    // Requestor — a canonical identity, checked against the register (§6, §43).
    //  requested_by_id -> users.id IS the requestor relationship. So it is filled
    //  from whoever is signed in when nothing was chosen, and a partial update
    //  that simply does not carry the field cannot silently erase it.
    $byId = (int) ($post['requested_by_id'] ?? 0) ?: null;
    if (!$byId && !array_key_exists('requested_by_id', $post) && $existing)
        $byId = (int) ($existing['requested_by_id'] ?? 0) ?: null;
    if (!$byId && !$existing && function_exists('current_user') && ($cu = current_user()))
        $byId = (int) ($cu['id'] ?? 0) ?: null;
    if ($byId) {
        try { $u = ops_one("SELECT id, is_active FROM users WHERE id=?", [$byId]); } catch (Throwable $e) { $u = null; }
        if (!$u) return [false, 'That requestor is not someone in this workspace.', 0];
    }
    // Departments — resolved through the canonical vocabulary, never free text.
    $deptId = function ($v) {
        $v = trim((string) $v); if ($v === '') return null;
        if (ctype_digit($v)) { $row = function_exists('vocab_value') ? vocab_value((int) $v) : null; }
        else { $row = function_exists('dept_of') ? dept_of($v) : null; }
        return $row ? (int) $row['id'] : null;
    };
    $reqDept = $deptId($post['requesting_department_id'] ?? '');
    $hirDept = $deptId($post['hiring_department_id'] ?? '');
    foreach ([['requesting_department_id', $reqDept], ['hiring_department_id', $hirDept]] as $d) {
        if (trim((string) ($post[$d[0]] ?? '')) !== '' && $d[1] === null)
            return [false, 'That department is not one this workspace knows.', 0];
        if ($d[1] !== null && function_exists('vocab_value')) {
            $row = vocab_value($d[1]);
            if (!$row || (int) $row['type_id'] !== (int) vocab_type_id('department'))
                return [false, 'That department is not a department.', 0];
            if ((int) ($row['active'] ?? 1) !== 1)
                return [false, 'That department is switched off and cannot be requested against.', 0];
        }
    }
    // Position — must exist, be active, and be one this user may act on.
    $posId = (int) ($post['position_id'] ?? 0) ?: null;
    if ($posId) {
        try { $p = ops_one("SELECT id, active, office_id FROM positions WHERE id=?", [$posId]); } catch (Throwable $e) { $p = null; }
        if (!$p) return [false, 'That position does not exist.', 0];
        if ((int) ($p['active'] ?? 1) !== 1) return [false, 'That position is switched off.', 0];
        if (function_exists('scope_office_allows') && !scope_office_allows($p['office_id'] ?? null))
            return [false, 'That position is outside your office / branch scope.', 0];
    }
    // A partial update that does not carry office_id must neither move the
    // request to "no branch" nor slip past the scope check by omission.
    $office = (int) ($post['office_id'] ?? 0) ?: null;
    if ($office === null && !array_key_exists('office_id', $post) && $existing)
        $office = (int) ($existing['office_id'] ?? 0) ?: null;
    if ($office !== null && function_exists('scope_allows') && !scope_allows($office, null))
        return [false, 'That branch is outside your office / branch scope.', 0];

    $reqBy = trim((string) ($post['required_by'] ?? ''));
    if ($reqBy !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', substr($reqBy, 0, 10)))
        return [false, 'The required-by date is not a date.', 0];

    $prio = strtoupper(trim((string) ($post['priority'] ?? 'NORMAL'))) ?: 'NORMAL';
    if (!isset(hreq_priorities()[$prio])) return [false, 'That is not a priority this workspace uses.', 0];
    $emp = strtoupper(trim((string) ($post['employment_type'] ?? '')));
    if ($emp !== '' && !isset(hreq_employment_types()[$emp])) return [false, 'That is not an employment type this workspace uses.', 0];
    $rtype = strtoupper(trim((string) ($post['request_type'] ?? '')));
    if ($rtype !== '' && !isset(hreq_request_types()[$rtype])) return [false, 'That is not a request type this workspace uses.', 0];

    $cols = [
        'status'                   => $existing ? (string) $existing['status'] : 'DRAFT',
        'requested_by_id'          => $byId,
        'requested_by_name'        => trim((string) ($post['requested_by_name'] ?? '')),
        'requesting_department_id' => $reqDept,
        'hiring_department_id'     => $hirDept,
        'designation'              => trim((string) ($post['designation'] ?? '')),
        'grade'                    => trim((string) ($post['grade'] ?? '')),
        'position_id'              => $posId,
        'new_position_requested'   => !empty($post['new_position_requested']) ? 1 : 0,
        'job_title'                => $title,
        'job_description'          => (string) ($post['job_description'] ?? ''),
        'quantity'                 => $qty,
        'office_id'                => $office,
        'work_location'            => trim((string) ($post['work_location'] ?? '')),
        'client_id'                => (int) ($post['client_id'] ?? 0) ?: null,
        'project_ref'              => trim((string) ($post['project_ref'] ?? '')),
        'required_by'              => substr($reqBy, 0, 10),
        'employment_type'          => $emp,
        'request_type'             => $rtype,
        'priority'                 => $prio,
        'reason'                   => trim((string) ($post['reason'] ?? '')),
        'approval_required'        => isset($post['approval_required']) ? (int) !!$post['approval_required'] : 1,
    ];
    $now = hreq_now(); $who = hreq_who();
    if ($existing) {
        $set = implode(',', array_map(fn($c) => "$c=?", array_keys($cols)));
        db()->prepare("UPDATE hiring_requests SET $set, updated_by=?, updated_at=? WHERE id=?")
            ->execute([...array_values($cols), $who, $now, $id]);
    } else {
        $keys = array_keys($cols);
        $ph = implode(',', array_fill(0, count($keys) + 4, '?'));
        db()->prepare("INSERT INTO hiring_requests (req_no," . implode(',', $keys) . ",created_by,created_at,updated_at) VALUES ($ph)")
            ->execute([hreq_next_no(), ...array_values($cols), $who, $now, $now]);
        $id = (int) db()->lastInsertId();
    }
    if (function_exists('custom_save')) custom_save('hiring_request', $id, $post);
    // AUDIT. This called activity_log() — a function that does not exist anywhere
    // in the application — so it was a silent no-op behind function_exists() and
    // nothing here was ever audited. act_log() is the real spine (M1 finding G).
    if (function_exists('act_log'))
        act_log('HIRING_REQUEST', $id, 'SYSTEM', ($existing ? 'Hiring request updated: ' : 'Hiring request raised: ') . $title, ['auto' => 1]);
    //  M4 §9/§10 — CLASSIFY THE CHANGE, against the APPROVED snapshot.
    if ($wasApproved) {
        $after = hreq_get($id);
        $diff  = hreq_material_diff($after);
        if (!$diff) {
            //  Non-material: allowed, still executable, and still audited. "Not
            //  material" means no re-approval, never no record.
            if (function_exists('act_log'))
                act_log('HIRING_REQUEST', (int) $id, 'SYSTEM',
                        'Approved request changed — nothing material; the approval still stands',
                        ['auto' => 1, 'outcome' => 'NON_MATERIAL']);
            return [true, 'Hiring request saved. The approval still stands.', $id];
        }
        [$ok, $msg] = hreq_require_reapproval($id, $diff);
        return [true, $msg, $id];
    }
    return [true, $existing ? 'Hiring request saved.' : 'Hiring request created.', $id];
}

//  M4 §10 — a material change invalidates the standing approval and asks the
//  EXISTING approval engine for a new decision. It creates no approval mechanism
//  of its own: appr_start() is the same call hreq_submit() makes, so the matrix,
//  authority, delegation, scope, segregation, inbox, SLA and notifications are
//  the ones M1/M2/M3 already built and proved.
//
//  §25 — the move to REQUIRED is an atomic compare-and-swap, so two users saving
//  a material change at the same moment cannot both start a chain. Only the
//  winner proceeds; the loser finds the request already in re-approval, which is
//  the correct answer for it.
function hreq_require_reapproval($id, array $diff) {
    $id = (int) $id;
    $st = db()->prepare("UPDATE hiring_requests SET reapproval_state='REQUIRED', reapproval_started_at=?,
                         updated_at=? WHERE id=? AND COALESCE(reapproval_state,'NONE') IN ('NONE','REAPPROVED','REJECTED')");
    $st->execute([hreq_now(), hreq_now(), $id]);
    //  The new value always differs from the matched ones, so 0 rows can only mean
    //  another process got there first — never "the value was already identical".
    $won = $st->rowCount() > 0;

    $names = implode(', ', array_keys($diff));
    if (function_exists('act_log')) {
        if ($won) {
            act_log('HIRING_REQUEST', $id, 'SYSTEM',
                    'Material change after approval — re-approval required (' . $names . ')',
                    ['auto' => 1, 'outcome' => 'MATERIAL', 'body' => json_encode($diff)]);
            act_log('HIRING_REQUEST', $id, 'SYSTEM', 'Recruitment execution blocked pending re-approval',
                    ['auto' => 1, 'outcome' => 'BLOCKED']);
        } else {
            act_log('HIRING_REQUEST', $id, 'SYSTEM',
                    'Further material change while re-approval was already open (' . $names . ')',
                    ['auto' => 1, 'outcome' => 'MATERIAL', 'body' => json_encode($diff)]);
        }
    }
    if (!$won) return [true, 'Saved. This request is already awaiting re-approval.'];

    //  Hand it to the engine that already exists. If no rule matches, the request
    //  stays REQUIRED and is decided directly through hreq_apply_decision() —
    //  exactly the behaviour hreq_submit() has for a first approval.
    $started = false;
    if (function_exists('appr_start')) {
        try {
            //  The SAME call hreq_submit() makes, through the SAME context helper —
            //  so the matrix, authority, delegation, scope and segregation cannot
            //  drift apart between a first approval and a re-approval.
            $r = hreq_get($id);
            [$started, $apprId] = appr_start('HIRING_REQUEST', $id, hreq_appr_ctx($r),
                'Re-approval — hiring request ' . (string) ($r['req_no'] ?? '') . ' — ' . (string) ($r['job_title'] ?? ''), 0);
            if ($started && $apprId > 0)
                db()->prepare("UPDATE hiring_requests SET approval_ref=? WHERE id=?")->execute([(string) $apprId, $id]);
            $started = $started && $apprId > 0;
        } catch (Throwable $e) { $started = false; }
    }
    if ($started) {
        db()->prepare("UPDATE hiring_requests SET reapproval_state='IN_PROGRESS', updated_at=?
                       WHERE id=? AND reapproval_state='REQUIRED'")->execute([hreq_now(), $id]);
        if (function_exists('act_log'))
            act_log('HIRING_REQUEST', $id, 'SYSTEM', 'Re-approval submitted to the approval chain', ['auto' => 1]);
        return [true, 'Saved. This change needs re-approval, which has been sent to the approvers. '
                    . 'Recruitment is paused until it is approved.'];
    }
    return [true, 'Saved. This change needs re-approval before recruitment can continue.'];
}

// ---- Submit — takes the snapshot (§12) ------------------------------------
// What the request MEANT when it was sent. A later edit to the department or
// designation master cannot silently change the meaning of what was approved.
function hreq_snapshot(array $r) {
    $dept = fn($id) => ($id && function_exists('vocab_value') && ($v = vocab_value((int) $id)))
        ? (function_exists('vocab_display') ? vocab_display($v) : $v['label']) : '';
    $desig = function_exists('lk_options_or') ? lk_options_or('designation', defined('DESIGNATIONS') ? DESIGNATIONS : []) : [];
    $pos = null;
    if (!empty($r['position_id'])) { try { $pos = ops_one("SELECT code, name FROM positions WHERE id=?", [(int) $r['position_id']]); } catch (Throwable $e) {} }
    //  M4 §5 — the snapshot must answer "what exactly was approved?", so it keeps
    //  BOTH the words the approver saw (resolved labels, below) AND the raw
    //  comparable values materiality is judged on. It stores every user-editable
    //  field, not only the material ones: deciding materiality is a comparison
    //  OVER the snapshot, so the snapshot cannot be selective.
    $fields = [];
    foreach (['job_title','job_description','designation','grade','position_id','new_position_requested',
              'quantity','employment_type','work_location','required_by','priority','reason',
              'request_type','project_ref','office_id','client_id','hiring_department_id',
              'requesting_department_id','requested_by_id','requested_by_name','approval_required'] as $f)
        $fields[$f] = $r[$f] ?? null;

    return [
        'taken_at'             => hreq_now(),
        'fields'               => $fields,
        'job_title'            => (string) ($r['job_title'] ?? ''),
        'job_description'      => (string) ($r['job_description'] ?? ''),
        'designation'          => (string) ($r['designation'] ?? ''),
        'designation_display'  => (string) ($desig[$r['designation'] ?? ''] ?? ($r['designation'] ?? '')),
        'requesting_department'=> $dept($r['requesting_department_id'] ?? 0),
        'hiring_department'    => $dept($r['hiring_department_id'] ?? 0),
        'position'             => $pos ? trim(($pos['code'] ?? '') . ' ' . ($pos['name'] ?? '')) : '',
        'quantity'             => (int) ($r['quantity'] ?? 0),
        'grade'                => (string) ($r['grade'] ?? ''),
        'employment_type'      => (string) ($r['employment_type'] ?? ''),
        'work_location'        => (string) ($r['work_location'] ?? ''),
        'required_by'          => (string) ($r['required_by'] ?? ''),
        'priority'             => (string) ($r['priority'] ?? ''),
    ];
}

function hreq_submit($id) {
    if (!hreq_can_create()) return [false, 'You do not have the right to submit a hiring request.'];
    hreq_migrate();
    $r = hreq_get($id); if (!$r) return [false, 'That hiring request no longer exists.'];
    if (!hreq_in_scope($r)) return [false, 'This hiring request is outside your office / branch scope.'];
    $st = strtoupper((string) $r['status']);
    if ($st !== 'DRAFT') return [false, 'Only a draft can be submitted (this one is ' . strtolower($st) . ').'];
    if ((int) $r['quantity'] <= 0) return [false, 'Say how many people are needed before submitting.'];
    if (trim((string) $r['job_title']) === '') return [false, 'Say what is being requested before submitting.'];
    $next = empty($r['approval_required']) ? 'APPROVED' : 'SUBMITTED';
    // The snapshot is taken HERE, before the chain is started, so what the
    // approvers are shown and what the record says was approved are the same
    // thing — and a later edit to a master cannot change it (M4 §12).
    db()->prepare("UPDATE hiring_requests SET status=?, snapshot_json=?, submitted_at=?, updated_by=?, updated_at=? WHERE id=?")
        ->execute([$next, json_encode(hreq_snapshot($r)), hreq_now(), hreq_who(), hreq_now(), (int) $id]);
    if (function_exists('act_log'))
        act_log('HIRING_REQUEST', (int) $id, 'SYSTEM', 'Submitted for approval', ['auto' => 1]);

    // Phase 3 · M1 — hand the request to the EXISTING approval engine. A chain
    // starts only where an administrator has configured a rule that matches; if
    // none does, the request stays SUBMITTED and is decided directly, exactly as
    // M4 behaved. Nothing is forced on a workspace that has configured nothing.
    if ($next === 'SUBMITTED' && function_exists('appr_start')) {
        $fresh = hreq_get($id) ?: $r;
        [$started, $apprId] = appr_start('HIRING_REQUEST', (int) $id, hreq_appr_ctx($fresh),
                                         'Hiring request ' . (string) $r['req_no'] . ' — ' . (string) $r['job_title'], 0);
        if ($started && $apprId > 0) {
            db()->prepare("UPDATE hiring_requests SET status='UNDER_REVIEW', approval_ref=?, updated_at=? WHERE id=?")
                ->execute([(string) $apprId, hreq_now(), (int) $id]);
            if (function_exists('act_log'))
                act_log('HIRING_REQUEST', (int) $id, 'SYSTEM', 'Sent to its approvers (chain #' . (int) $apprId . ')', ['auto' => 1]);
            return [true, 'Request submitted — it is now with its approvers.'];
        }
    }
    return [true, $next === 'APPROVED' ? 'Request submitted — this workspace does not require approval, so it is ready.' : 'Request submitted for approval.'];
}

// ---- The ONE writer of a decision (Phase 3 · M1) ---------------------------
//  Both ways of deciding a hiring request come through here — the direct
//  decision (hreq_decide) and the approval chain (appr_callback) — so there is
//  one place that changes the approved/rejected state, one place that stamps who
//  decided and when, and one audit entry, whichever route was taken.
//
//  It deliberately does NOT ask the authority question. Its two callers each ask
//  it in the way that is right for them: hreq_decide() asks the capability, the
//  scope and the segregation rule; the chain asks appr_can_act() and appr_guard(),
//  which ask entitlement, scope and the same segregation rule. Both are audited
//  by the write-path test, and this function is not reachable from any route.
//
//  The STATE rule lives here, once, so neither route can approve something that
//  is no longer open for decision — a cancelled or already-decided request is
//  refused whichever way the decision arrives.
function hreq_apply_decision($id, $result, $by, $note = '', $source = 'DIRECT') {
    hreq_migrate();
    $r = hreq_get($id); if (!$r) return [false, 'That hiring request no longer exists.'];
    $st = strtoupper((string) $r['status']);
    //  M4 §11 — RE-APPROVAL USES THIS WRITER. There is no second decision writer:
    //  an approved request whose re-approval is running is decided here too, so
    //  authority, segregation, delegation and audit are the same code for both.
    $reSt = hreq_reapproval_state($r);
    $isReapproval = ($st === 'APPROVED' && in_array($reSt, ['REQUIRED', 'IN_PROGRESS'], true));
    //  ONE state gate for BOTH routes, before anything is written. A first
    //  approval is decided from SUBMITTED or UNDER_REVIEW; a re-approval is
    //  decided from APPROVED while its re-approval is open. Nothing else is
    //  decidable, and no write happens above this line.
    if (!$isReapproval && !in_array($st, ['SUBMITTED', 'UNDER_REVIEW'], true))
        return [false, 'Only a submitted request can be decided (this one is ' . strtolower($st) . ').'];
    if ($isReapproval) {
        $to  = strtoupper((string) $result) === 'APPROVED' ? 'APPROVED' : 'REJECTED';
        $who = trim((string) $by) !== '' ? (string) $by : hreq_who();
        //  PHASE 3 · M6 (adversarial pass 3) — THE DECISION IS A COMPARE-AND-SWAP.
        //
        //  The state gate above is a CHECK, and the write below used to be
        //  "WHERE id=?" — check-then-write, which is not atomic. Two approvers
        //  deciding the same re-approval at the same moment both passed the gate,
        //  both wrote, and BOTH WERE TOLD THEIR DECISION WAS RECORDED. Measured on
        //  MariaDB: two successes out of two processes, in two runs out of five,
        //  leaving one request carrying two contradictory decisions in its audit
        //  trail. SQLite hid it by serialising writers.
        //
        //  The swap adds the state the gate just checked to the WHERE, so only the
        //  process that still finds it there may write. The loser is told plainly
        //  and writes nothing — no state, no audit, no claim.
        $cas = " AND UPPER(COALESCE(status,''))='APPROVED'"
             . " AND UPPER(COALESCE(reapproval_state,'')) IN ('REQUIRED','IN_PROGRESS')";
        if ($to === 'APPROVED') {
            //  The NEW approved snapshot is captured at the decision — never when
            //  the change was merely submitted for re-approval.
            $stw = db()->prepare("UPDATE hiring_requests SET reapproval_state='REAPPROVED', decided_by=?, decided_at=?,
                           decision_note=?, approved_snapshot_json=?, approved_snapshot_at=?, updated_by=?, updated_at=? WHERE id=?" . $cas);
            $stw->execute([$who, hreq_now(), substr(trim((string) $note), 0, 400),
                           json_encode(hreq_snapshot($r)), hreq_now(), $who, hreq_now(), (int) $id]);
        } else {
            $stw = db()->prepare("UPDATE hiring_requests SET reapproval_state='REJECTED', decided_by=?, decided_at=?,
                           decision_note=?, updated_by=?, updated_at=? WHERE id=?" . $cas);
            $stw->execute([$who, hreq_now(), substr(trim((string) $note), 0, 400), $who, hreq_now(), (int) $id]);
        }
        //  A matched row is always a changed row here — the gate above excludes
        //  every state this write sets — so no rows matched means one thing only.
        if ((int) $stw->rowCount() < 1)
            return [false, 'Somebody else decided this re-approval a moment ago. Open it again to see the decision.'];
        if (function_exists('act_log'))
            act_log('HIRING_REQUEST', (int) $id, 'SYSTEM',
                    ($to === 'APPROVED' ? 'Re-approved' : 'Re-approval rejected') . ' via ' . $source
                    . ($who !== '' ? ' by ' . $who : ''),
                    ['auto' => 1, 'outcome' => $to === 'APPROVED' ? 'REAPPROVED' : 'REAPPROVAL_REJECTED',
                     'body' => trim((string) $note)]);
        if (function_exists('act_log'))
            act_log('HIRING_REQUEST', (int) $id, 'SYSTEM',
                    $to === 'APPROVED' ? 'Recruitment execution restored' : 'Recruitment execution remains blocked',
                    ['auto' => 1]);
        return [true, $to === 'APPROVED' ? 'Change re-approved. Recruitment can continue.'
                                         : 'Change not re-approved. Recruitment stays paused.'];
    }
    $to = strtoupper((string) $result) === 'APPROVED' ? 'APPROVED' : 'REJECTED';
    $who = trim((string) $by) !== '' ? (string) $by : hreq_who();
    //  M4 §5/§11 — THE APPROVED SNAPSHOT IS TAKEN AT THE DECISION, not at submit.
    //  The submitted snapshot answers "what was sent"; only this answers "what did
    //  the approver approve?", and the two differ whenever anything moved in
    //  between. It is written once per approval and never overwritten by a later
    //  edit — a subsequent material change compares against it, it does not
    //  replace it.
    //  M6 — the same compare-and-swap on the first decision. Two approvers acting
    //  on one submitted request must produce one decision, not two.
    $cas1 = " AND UPPER(COALESCE(status,'')) IN ('SUBMITTED','UNDER_REVIEW')";
    if ($to === 'APPROVED') {
        $stw1 = db()->prepare("UPDATE hiring_requests SET status=?, decided_by=?, decided_at=?, decision_note=?,
                       approved_snapshot_json=?, approved_snapshot_at=?, reapproval_state=?, updated_by=?, updated_at=? WHERE id=?" . $cas1);
        $stw1->execute([$to, $who, hreq_now(), substr(trim((string) $note), 0, 400),
                       json_encode(hreq_snapshot($r)), hreq_now(),
                       hreq_reapproval_state($r) === 'NONE' ? 'NONE' : 'REAPPROVED',
                       $who, hreq_now(), (int) $id]);
    } else {
        $stw1 = db()->prepare("UPDATE hiring_requests SET status=?, decided_by=?, decided_at=?, decision_note=?, updated_by=?, updated_at=? WHERE id=?" . $cas1);
        $stw1->execute([$to, $who, hreq_now(), substr(trim((string) $note), 0, 400), $who, hreq_now(), (int) $id]);
    }
    if ((int) $stw1->rowCount() < 1)
        return [false, 'Somebody else decided this request a moment ago. Open it again to see the decision.'];
    if (function_exists('act_log'))
        act_log('HIRING_REQUEST', (int) $id, 'SYSTEM',
                $to . ' via ' . $source . ($who !== '' ? ' by ' . $who : ''),
                ['auto' => 1, 'outcome' => $to, 'body' => trim((string) $note)]);
    return [true, $to === 'APPROVED' ? 'Request approved.' : 'Request rejected.'];
}

// The direct decision. Where an approval chain is running, the chain is
// authoritative and this refuses — otherwise the two could disagree about the
// same request.
function hreq_decide($id, $approve, $note = '') {
    hreq_migrate();
    $r = hreq_get($id); if (!$r) return [false, 'That hiring request no longer exists.'];
    if (!hreq_in_scope($r)) return [false, 'This hiring request is outside your office / branch scope.'];
    if (!hreq_can_decide()) return [false, 'You are not permitted to approve or reject a hiring request.'];
    // Segregation of duties — asked here, so no caller can decide around it.
    if (!hreq_may_decide($r))
        return [false, 'You raised this request, so somebody else has to decide it.'];
    // An open chain owns this decision. Deciding around it would leave the chain
    // pending against a request that had already moved.
    $open = function_exists('appr_open') ? appr_open('HIRING_REQUEST', (int) $id) : null;
    if ($open) return [false, 'This request is with its approvers. Decide it from My approvals.'];
    return hreq_apply_decision($id, $approve ? 'APPROVED' : 'REJECTED', hreq_who(), $note, 'DIRECT');
}

function hreq_cancel($id, $note = '') {
    if (!hreq_can_create()) return [false, 'You do not have the right to cancel a hiring request.'];
    hreq_migrate();
    $r = hreq_get($id); if (!$r) return [false, 'That hiring request no longer exists.'];
    if (!hreq_in_scope($r)) return [false, 'This hiring request is outside your office / branch scope.'];
    if (strtoupper((string) $r['status']) === 'CANCELLED') return [true, 'Already cancelled.'];
    db()->prepare("UPDATE hiring_requests SET status='CANCELLED', decision_note=?, updated_by=?, updated_at=? WHERE id=?")
        ->execute([substr(trim((string) $note), 0, 400), hreq_who(), hreq_now(), (int) $id]);
    // M1 CORRECTION — a cancelled request must not leave live approval work
    // behind it. M4 wrote this function before approval chains existed and M1
    // connected chains without revisiting it, so the step stayed in the
    // approver's inbox and acting on it reported a false success. Closing the
    // chain through the engine's own helper takes it out of the inbox, out of
    // the SLA reminders and out of reach of a decision, all at once.
    $closed = function_exists('appr_cancel_open')
        ? appr_cancel_open('HIRING_REQUEST', (int) $id, 'The hiring request was cancelled.') : 0;
    if (function_exists('act_log'))
        act_log('HIRING_REQUEST', (int) $id, 'SYSTEM',
                'Cancelled' . ($closed > 0 ? ' — approval withdrawn from its approvers' : ''),
                ['auto' => 1, 'outcome' => 'CANCELLED', 'body' => trim((string) $note)]);
    return [true, 'Request cancelled.'];
}

// ---- Request -> Requisition (§18, §20) ------------------------------------
//  THE boundary this milestone exists to create: recruitment execution cannot
//  begin from a request that is not ready. M4 makes that refusable; Phase 3 adds
//  the routing that reaches APPROVED.
//
//  Cardinality is one request to MANY requisitions (§20). A request for ten
//  people may legitimately be executed as two requisitions of five in different
//  branches, so nothing here forces one-to-one. What is NOT allowed is
//  converting more people than were approved.
function hreq_requisitions($id) {
    hreq_migrate();
    try { return ops_all("SELECT * FROM requisitions WHERE hiring_request_id=? ORDER BY id", [(int) $id]); }
    catch (Throwable $e) { return []; }
}

// How many of the approved headcount are already being executed.
function hreq_converted_qty($id) {
    $n = 0;
    foreach (hreq_requisitions($id) as $r) {
        if (in_array(strtoupper((string) $r['status']), ['CANCELLED'], true)) continue;
        $n += max(1, (int) ($r['quantity'] ?? 1));
    }
    return $n;
}

//  M4 §14 — THE CEILING IS THE APPROVED FIGURE, not the figure on the row.
//
//  A material quantity increase is applied to the row immediately and invalidates
//  the approval (§10); the row therefore says 99 while the approver authorised 10.
//  Reading the row here would let an unapproved increase raise the ceiling the
//  moment it was typed, which is precisely the bypass M4 exists to stop. The
//  approved snapshot is the authority, and the row is used only where no approval
//  has happened yet (a workspace that requires none).
function hreq_approved_qty($r) {
    $r = is_array($r) ? $r : hreq_get($r);
    if (!$r) return 0;
    $snap = hreq_approved_snapshot($r);
    if ($snap && isset($snap['fields']['quantity'])) return max(0, (int) $snap['fields']['quantity']);
    return max(0, (int) ($r['quantity'] ?? 0));
}

function hreq_remaining_qty($id) {
    $r = hreq_get($id); if (!$r) return 0;
    return max(0, hreq_approved_qty($r) - hreq_converted_qty($id));
}

// Create the execution record. Returns [ok, message, requisitionId].
function hreq_to_requisition($id, $qty = 0) {
    if (!hreq_can_create())
        return [false, 'You do not have the right to raise a requisition from this request.', 0];
    hreq_migrate();
    if (function_exists('req_migrate')) req_migrate();
    $r = hreq_get($id); if (!$r) return [false, 'That hiring request no longer exists.', 0];
    if (!hreq_in_scope($r)) return [false, 'This hiring request is outside your office / branch scope.', 0];

    // The boundary. Nothing below runs for a request that is not ready.
    if (!hreq_is_executable($r))
        return [false, 'This request is ' . strtolower((string) $r['status'])
            . '. Recruitment cannot start until it is approved.', 0];

    $left = hreq_remaining_qty($id);
    if ($left <= 0) return [false, 'Every approved vacancy on this request is already being recruited.', 0];
    $qty = (int) $qty ?: $left;
    if ($qty > $left) return [false, 'Only ' . $left . ' of the approved headcount is left to recruit.', 0];

    // The department goes across as the canonical identity AND the text column,
    // exactly as a directly raised requisition now does.
    $deptCode = ''; $deptId = (int) ($r['hiring_department_id'] ?: $r['requesting_department_id'] ?: 0) ?: null;
    if ($deptId && function_exists('vocab_value')) { $v = vocab_value($deptId); if ($v) $deptCode = (string) $v['code']; }

    $code = function_exists('recruit_req_code')
        ? recruit_req_code((int) ($r['office_id'] ?? 0), (int) ($r['client_id'] ?? 0), date('Y-m-d'))
        : (function_exists('ops_next_code') ? ops_next_code('requisitions', 'req_code', 'REQ') : 'REQ-' . $id);

    db()->prepare("INSERT INTO requisitions
        (req_code, hiring_request_id, office_id, client_id, department, department_id, designation, grade,
         position_id, quantity, req_type, project_site, deploy_location, start_date, responsibilities,
         status, approved_by, approval_date, created_by, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'OPEN', ?, ?, ?, ?)")
        ->execute([$code, (int) $id, $r['office_id'], $r['client_id'], $deptCode, $deptId,
                   $r['designation'], $r['grade'], $r['position_id'], $qty, $r['request_type'],
                   $r['project_ref'], $r['work_location'], $r['required_by'], $r['job_description'],
                   (string) $r['decided_by'], (string) substr((string) $r['decided_at'], 0, 10),
                   hreq_who(), hreq_now()]);
    $rid = (int) db()->lastInsertId();

    //  M4 §14/§25 — THE CEILING, RE-CHECKED AFTER THE INSERT.
    //
    //  The check above is check-then-insert, which is not atomic. Under real
    //  concurrency on MariaDB two processes both passed the check and both took
    //  the last seat: measured, 11 allocated against an approved 10. SQLite hid it
    //  because it serialises writers with a database-level lock — which is exactly
    //  why MariaDB is the authoritative engine.
    //
    //  The edit path already compensates after its write; creation now does the
    //  same. The row is inserted, the total is re-read, and a requisition that
    //  broke the ceiling is removed again. Two racing processes may both revert,
    //  which refuses an allocation that could in principle have succeeded — the
    //  safe direction for a headcount control, and never an over-allocation.
    $approvedNow = hreq_approved_qty($r);
    $totalNow = (int) ops_val("SELECT COALESCE(SUM(quantity),0) FROM requisitions
                               WHERE hiring_request_id=? AND UPPER(COALESCE(status,'')) <> 'CANCELLED'", [(int) $id]);
    if ($totalNow > $approvedNow) {
        try { db()->prepare("DELETE FROM requisitions WHERE id=?")->execute([$rid]); } catch (Throwable $e) {}
        if (function_exists('act_log'))
            act_log('HIRING_REQUEST', (int) $id, 'SYSTEM',
                    'Refused: a requisition that would have exceeded the approved headcount',
                    ['auto' => 1, 'outcome' => 'OVER_ALLOCATION_REFUSED']);
        return [false, 'Another user took the last of the approved headcount while this was being raised.', 0];
    }

    if (function_exists('reqf_sync')) reqf_sync($rid);
    if (function_exists('act_log'))
        act_log('HIRING_REQUEST', (int) $id, 'SYSTEM', 'Recruitment requisition ' . $code . ' raised for ' . $qty, ['auto' => 1]);
    return [true, 'Requisition ' . $code . ' raised for ' . $qty . ' of the approved headcount.', $rid];
}

// ---- Scope (§35) ----------------------------------------------------------
// One door, the pattern M14 established and M3 extended to candidates. A hiring
// request belongs to a branch; a user may only act within their own.
//  The DECISION, kept separate from the REFUSAL. ops_require() redirects and
//  exits, which makes a gate impossible to ask a question of — so the question
//  is asked here, and answered with a reason or an empty string. That is what
//  the tests exercise; hreq_scope_gate() is the one-line refusal around it.
function hreq_scope_reason() {
    if (!function_exists('scope_allows')) return '';
    $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id > 0) {
        $r = hreq_get($id);
        if ($r && !scope_allows($r['office_id'] ?? null, null))
            return 'This hiring request is outside your office / branch scope.';
    }
    // A branch named on the way in must also be one they may use.
    $to = (int) ($_POST['office_id'] ?? 0);
    if ($to > 0 && !scope_allows($to, null)) return 'That branch is outside your office / branch scope.';
    return '';
}
function hreq_scope_gate() {
    $why = hreq_scope_reason();
    ops_require($why === '', $why);
}

// Only requests this user may see.
function hreq_list($status = '') {
    hreq_migrate();
    $w = []; $a = [];
    if ($status !== '') { $w[] = 'status=?'; $a[] = strtoupper($status); }
    if (function_exists('scope_office_clause')) {
        [$sw, $sa] = scope_office_clause('office_id');
        if ($sw && $sw !== '1=1') { $w[] = $sw; $a = array_merge($a, $sa); }
    }
    $sql = "SELECT * FROM hiring_requests" . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY id DESC";
    try { return ops_all($sql, $a); } catch (Throwable $e) { return []; }
}

//  B8 — the one query global search needs, kept HERE because this layer owns
//  the table. test_m4_correction.php asserts that no file but this one reads or
//  writes hiring_requests, and that boundary is deliberate: it is what stops a
//  second door opening onto a record that carries an approval decision. Search
//  therefore asks the layer instead of reaching past it — the same way it asks
//  recruitment for rasg_cand_scope() and rcc_scope_req().
//
//  Scope and permission are the register's own. hreq_list() scopes by OFFICE
//  ONLY — no SBU clause — and this must match it exactly; adding an SBU
//  restriction here would hide requests from people the register shows them to.
//  The caller checks hreq_can_view(); this refuses anyway, so the function is
//  safe wherever it is called from.
function hreq_search($like, $limit = 6) {
    if (!hreq_can_view()) return [];
    hreq_migrate();
    $w = []; $a = [];
    if (function_exists('scope_office_clause')) {
        [$sw, $sa] = scope_office_clause('office_id');
        if ($sw && $sw !== '1=1') { $w[] = $sw; $a = array_merge($a, $sa); }
    }
    $w[] = '(req_no LIKE ? OR designation LIKE ? OR job_title LIKE ?)';
    array_push($a, $like, $like, $like);
    $n = max(1, (int) $limit);
    try {
        return ops_all("SELECT id, req_no, designation, job_title, status, requested_by_name
                        FROM hiring_requests WHERE " . implode(' AND ', $w) . "
                        ORDER BY id DESC LIMIT $n", $a);
    } catch (Throwable $e) { return []; }
}

// ---- Routes ---------------------------------------------------------------
//  One dispatcher, one door. Scope first, then permission, exactly as every
//  other register in the application does — no second access-control mechanism.
function ops_hiring_requests($route, $method) {
    // The house order, and the order the enforcement has to be read in:
    //   licence + module   ops_module_gate() — already ran, before dispatch
    //   capability         here, so a direct invocation cannot skip it
    //   object scope       here, before anything reads an id
    ops_require(hreq_can_view(), 'You do not have access to recruitment.');
    hreq_scope_gate();                       // M4 — branch scope, before anything reads an id
    hreq_migrate();
    $mayRaise  = hreq_can_create();          // CAPABILITY, not a role name (§2)
    $mayDecide = hreq_can_decide();

    if ($method === 'POST') {
        ops_require($mayRaise, 'You do not have the right to raise or change a hiring request.');
        $do = (string) ($_POST['do'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        if ($do === 'save') {
            [$ok, $msg, $newId] = hreq_save($id, $_POST);
            flash($msg, $ok ? 'success' : 'error');
            redirect($ok ? '/hiring-request?id=' . $newId : '/hiring-requests'); return true;
        }
        if ($do === 'submit')  { [$ok, $msg] = hreq_submit($id);  flash($msg, $ok ? 'success' : 'error'); redirect('/hiring-request?id=' . $id); return true; }
        if ($do === 'cancel')  { [$ok, $msg] = hreq_cancel($id, (string) ($_POST['note'] ?? '')); flash($msg, $ok ? 'success' : 'error'); redirect('/hiring-request?id=' . $id); return true; }
        if ($do === 'decide') {
            // Recording a decision is an administrator's act. Phase 3 replaces
            // this with the approval engine; the state change stays here so both
            // go through one place.
            ops_require($mayDecide, 'You are not permitted to approve or reject a hiring request.');
            // Segregation of duties — the requestor is not the approver.
            ops_require(hreq_may_decide($id), 'You raised this request, so somebody else has to decide it.');
            [$ok, $msg] = hreq_decide($id, ($_POST['decision'] ?? '') === 'approve', (string) ($_POST['note'] ?? ''));
            flash($msg, $ok ? 'success' : 'error'); redirect('/hiring-request?id=' . $id); return true;
        }
        if ($do === 'raise-requisition') {
            [$ok, $msg, $rid] = hreq_to_requisition($id, (int) ($_POST['qty'] ?? 0));
            flash($msg, $ok ? 'success' : 'error');
            redirect($ok ? '/requisition?id=' . $rid : '/hiring-request?id=' . $id); return true;
        }
    }

    if ($route === 'hiring-request') {
        $id = (int) ($_GET['id'] ?? 0);
        $r = $id > 0 ? hreq_get($id) : null;
        if ($id > 0 && !$r) { http_response_code(404); view('notfound'); return true; }
        // Asked again where the record is actually read. The gate above refuses
        // earlier and more kindly, but it must not be the only thing standing
        // between a bookmarked URL and another branch's request.
        ops_require(hreq_in_scope($r), 'This hiring request is outside your office / branch scope.');
        view('ops/hiring_request', [
            'req'        => $r,
            'mayRaise'   => $mayRaise,
            // What the screen offers is what the server would actually allow —
            // the segregation rule included. The button is not the boundary; it
            // simply stops offering an act that would be refused.
            'mayDecide'  => $mayDecide && ($r ? hreq_may_decide($r) : true),
            'requisitions' => $r ? hreq_requisitions($id) : [],
            'remaining'  => $r ? hreq_remaining_qty($id) : 0,
            'people'     => function_exists('rcc_users') ? rcc_users() : [],
            'offices'    => function_exists('offices_list') ? offices_list() : [],
            'positions'  => function_exists('positions_all') ? positions_all(true) : [],
        ]);
        return true;
    }
    view('ops/hiring_request_list', ['rows' => hreq_list((string) ($_GET['status'] ?? '')), 'mayRaise' => $mayRaise]);
    return true;
}
