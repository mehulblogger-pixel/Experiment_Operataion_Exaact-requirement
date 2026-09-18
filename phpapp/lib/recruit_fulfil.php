<?php
// ============================================================================
//  PHASE 4 — MULTI-SOURCE FULFILMENT
//
//  ONE approved demand, fulfilled from several sources, WITHOUT becoming several
//  demands. Twenty people from five sources is still twenty people of one
//  requirement — not five requirements that happen to look alike.
//
//  Nothing here re-decides anything Phase 3 owns:
//
//      M1/M2  approval and authority        → hreq_* / appr_*
//      M3     what "filled" means, counting → reqf_counts()
//      M4     the approved ceiling          → hreq_qty_guard() / the snapshot
//      M5     recruiter accountability      → rasg_assign()
//      M6     may recruitment execute?      → rexec_block_reason()
//
//  Phase 4 adds one question nobody owned: **of the authorised headcount, how
//  much has been promised to which source, and how much of that has arrived?**
//
//  ONE table is created (requisition_allocations). The source vocabulary is the
//  EXISTING configurable lookup `req_sourcing_model`; the source entity is an
//  EXISTING record — a business partner, a marketplace requirement, a
//  professional, an inspector or a user. No new master of anything.
// ============================================================================

//  The quantities, named once, because confusing two of them is the whole risk:
//
//    AUTHORISED  what the requisition may execute            (M4's ceiling)
//    ALLOCATED   how much of it is promised to sources       (this file)
//    FULFILLED   how much has actually arrived               (M3's counter)
//    UNALLOCATED authorised − allocated
//    REMAINING   authorised − fulfilled
//
//  FULFILLED ≤ ALLOCATED ≤ AUTHORISED, always.

//  Allocation lifecycle. Deliberately short: a state nobody acts on differently
//  is a state that only creates ways to be wrong.
const RFUL_STATES = [
    'PLANNED'   => 'Planned — seats set aside for this source',
    'ACTIVE'    => 'Active — this source is working on it',
    'FULFILLED' => 'Fulfilled — every allocated seat has arrived',
    'RELEASED'  => 'Released — unfilled seats given back to the requirement',
    'CANCELLED' => 'Cancelled — this source will not deliver',
];
//  States in which an allocation still holds seats against the requirement.
const RFUL_LIVE_STATES  = ['PLANNED', 'ACTIVE', 'FULFILLED'];
//  States in which an allocation may still take people.
const RFUL_OPEN_STATES  = ['PLANNED', 'ACTIVE'];
//  Ending an allocation keeps what it delivered and gives back only the rest.
const RFUL_CLOSED_STATES = ['RELEASED', 'CANCELLED'];

//  Refusals carry a code, so a test asserts WHICH control refused.
const RFUL_CODES = [
    'OK'                  => 'done',
    'NO_CHANGE'           => 'nothing to change',
    'NO_REQUISITION'      => 'that requirement does not exist',
    'NO_ALLOCATION'       => 'that allocation does not exist',
    'BAD_VALUE'           => 'that is not a usable value',
    'BAD_QUANTITY'        => 'that quantity is not a whole number of people',
    'BAD_SOURCE'          => 'that is not a configured fulfilment source',
    'SOURCE_ENTITY_UNKNOWN' => 'that supplier, marketplace requirement or person is not on file here',
    'NO_ENTITLEMENT'      => 'this workspace does not hold the module that source needs',
    'NO_PERMISSION'       => 'you may not change fulfilment sourcing',
    'OUT_OF_SCOPE'        => 'that requirement is outside your office / branch scope',
    'EXECUTION_BLOCKED'   => 'the requirement is not executable right now',
    'OVER_AUTHORISED'     => 'that would promise more people than the requirement is authorised for',
    'OVER_ALLOCATED'      => 'that source has no allocated seat left',
    'BAD_STATE'           => 'that allocation is closed',
    'BELOW_FULFILLED'     => 'an allocation cannot be cut below what it has already delivered',
    'STALE'               => 'somebody changed this allocation while the screen was open',
    'LOST_RACE'           => 'somebody else changed it at the same moment',
];

function rful_now() { return function_exists('now_iso') ? now_iso() : date('c'); }
function rful_who() {
    if (!function_exists('current_user')) return '';
    $u = current_user(); if (!$u) return '';
    return function_exists('user_name') ? (string) user_name($u) : (string) ($u['username'] ?? '');
}

// ---- values -----------------------------------------------------------------

//  An id is a number or it is nothing. The same rule M5 and M6 arrived at, for
//  the same reason: an identity must never be the product of a type conversion.
function rful_id($v, &$ok) {
    $ok = true;
    if ($v === null || $v === '' || $v === 0 || $v === '0') return null;
    if (is_int($v)) { if ($v > 0) return $v; $ok = false; return null; }
    if (is_string($v) && ctype_digit($v)) { $n = (int) $v; if ($n > 0) return $n; $ok = false; return null; }
    $ok = false; return null;
}

//  A COUNT is a whole number of people, zero included. Not 2.5, not "five", not
//  -3, not an array, not true. Nothing here is coerced: a value that is not
//  already a whole number is refused, never rounded or cast into one.
function rful_count($v, &$ok) {
    $ok = true;
    if (is_int($v)) { if ($v >= 0) return $v; $ok = false; return 0; }
    if (is_string($v) && $v !== '' && ctype_digit($v)) return (int) $v;
    $ok = false; return 0;
}

//  A QUANTITY is a count of at least one, because an allocation of nobody is not
//  an allocation.
function rful_qty($v, &$ok) {
    $n = rful_count($v, $ok);
    if (!$ok) return 0;
    if ($n < 1) { $ok = false; return 0; }
    return $n;
}

// ---- the source vocabulary --------------------------------------------------

//  The fulfilment sources, read from the EXISTING configurable lookup that the
//  cost model already uses (`req_sourcing_model`, registered in Masters). Phase 4
//  seeds the values the business named; a workspace may add its own without a
//  line of code, which is why no new master was built.
const RFUL_SEED_SOURCES = [
    'OWN_PAYROLL'       => 'Own payroll / internal',
    'DIRECT_RECRUITMENT'=> 'Direct recruitment',
    'INTERNAL_TRANSFER' => 'Internal transfer',
    'MANPOWER_AGENCY'   => 'Manpower supply agency',
    'SUBCON_AGENCY'     => 'Third-party (sub-contract) agency',
    'SUPPLIER'          => 'Supplier',
    'FREELANCER'        => 'Freelancer / consultant',
    'CONSULTANT'        => 'Consultant',
    'MARKETPLACE'       => 'Marketplace',
    'CLIENT_BENCH'      => 'Client bench',
];

function rful_sources() {
    //  lk_options_or() returns the workspace's configured list when one exists
    //  and the seed otherwise — the same helper every other recruitment list uses.
    $base = defined('REQ_SOURCING_MODELS') ? REQ_SOURCING_MODELS : [];
    $seed = $base + RFUL_SEED_SOURCES;
    if (!function_exists('lk_options_or')) return $seed;
    $cfg = lk_options_or('req_sourcing_model', $seed);
    return is_array($cfg) && $cfg ? ($cfg + $seed) : $seed;
}
//  A source is valid when it is in the workspace's configured list OR in the seed.
//  The seed always stays valid on purpose: a workspace that later narrows its
//  list must not make yesterday's allocations unreadable.
function rful_source_ok($code) {
    if (!is_scalar($code) || is_bool($code)) return false;
    $c = strtoupper(trim((string) $code));
    if ($c === '') return false;
    return array_key_exists($c, rful_sources());
}

//  Which EXISTING record a source points at, and which module it needs. Nothing
//  is invented: each row names a table this application already owns.
const RFUL_SOURCE_ENTITY = [
    'MANPOWER_AGENCY'   => ['table' => 'business_partners', 'module' => '',        'what' => 'supplier'],
    'SUBCON_AGENCY'     => ['table' => 'business_partners', 'module' => '',        'what' => 'sub-contractor'],
    'SUPPLIER'          => ['table' => 'business_partners', 'module' => '',        'what' => 'supplier'],
    'CLIENT_BENCH'      => ['table' => 'business_partners', 'module' => '',        'what' => 'client'],
    'MARKETPLACE'       => ['table' => 'cx_requirements',   'module' => 'connect', 'what' => 'marketplace requirement'],
    'FREELANCER'        => ['table' => 'cx_professionals',  'module' => 'connect', 'what' => 'professional'],
    'CONSULTANT'        => ['table' => 'cx_professionals',  'module' => 'connect', 'what' => 'professional'],
    'INTERNAL_TRANSFER' => ['table' => 'inspectors',        'module' => '',        'what' => 'person'],
    'OWN_PAYROLL'       => ['table' => '',                  'module' => '',        'what' => ''],
    'DIRECT_RECRUITMENT'=> ['table' => '',                  'module' => '',        'what' => ''],
];

//  Does the named source entity exist IN THIS WORKSPACE? Tenancy is structural —
//  one database per tenant — so a row from another workspace is simply not here,
//  and "not here" must mean refused rather than written.
function rful_source_entity_ok($source, $entityId, &$code) {
    $code = 'OK';
    $src = strtoupper((string) $source);
    $def = RFUL_SOURCE_ENTITY[$src] ?? null;
    $id  = rful_id($entityId, $vOk);
    if (!$vOk) { $code = 'BAD_VALUE'; return false; }
    if ($id === null) return true;                       // no entity named — allowed
    if (!$def || $def['table'] === '') { $code = 'SOURCE_ENTITY_UNKNOWN'; return false; }
    //  A source that needs a module the workspace has not bought is refused
    //  outright — hiding the option is not a control (M6 §42).
    if ($def['module'] === 'connect' && function_exists('connect_enabled') && !connect_enabled()) {
        $code = 'NO_ENTITLEMENT'; return false;
    }
    try { $n = (int) ops_val("SELECT COUNT(*) FROM {$def['table']} WHERE id=?", [$id]); }
    catch (Throwable $e) { $n = 0; }
    if ($n < 1) { $code = 'SOURCE_ENTITY_UNKNOWN'; return false; }
    return true;
}

// ---- schema -----------------------------------------------------------------

function rful_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return;
    //  Only remembered as done once it actually landed. A migration that ran
    //  before `candidates` existed would otherwise mark itself finished and leave
    //  the link column missing for the rest of the request.
    $pk = (function_exists('db_driver') && db_driver() === 'mysql')
        ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS requisition_allocations (
            id $pk,
            requisition_id INT NULL,          -- the ONE authorised demand this belongs to
            source VARCHAR(40) DEFAULT '',    -- a value of the configurable req_sourcing_model list
            source_entity_id INT NULL,        -- an EXISTING record: partner / cx_requirement / professional / inspector
            source_label VARCHAR(160) DEFAULT '',  -- display only, never an identity
            allocated_qty INT DEFAULT 0,
            status VARCHAR(20) DEFAULT 'PLANNED',
            target_date VARCHAR(20) DEFAULT '',
            note VARCHAR(255) DEFAULT '',
            created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '',
            updated_by VARCHAR(150) DEFAULT '', updated_at VARCHAR(30) DEFAULT '',
            closed_by VARCHAR(150) DEFAULT '', closed_at VARCHAR(30) DEFAULT '',
            close_reason VARCHAR(255) DEFAULT ''
        )");
    } catch (Throwable $e) { return; }
    try { db()->exec("CREATE INDEX idx_rful_req ON requisition_allocations (requisition_id, status)"); } catch (Throwable $e) {}
    try { db()->exec("CREATE INDEX idx_rful_src ON requisition_allocations (source, source_entity_id)"); } catch (Throwable $e) {}
    //  The candidate's allocation. Additive; `source`, `source_type` and `agency`
    //  already exist on the row and are left exactly as they are.
    $linked = false;
    if (function_exists('ensure_column')) {
        try { ensure_column('candidates', 'allocation_id', 'INT NULL'); $linked = true; } catch (Throwable $e) {}
    }
    if ($linked) { try { db()->exec("CREATE INDEX idx_cand_alloc ON candidates (allocation_id)"); } catch (Throwable $e) {} }
    //  An allocation ledger, append-only: what changed, who changed it, and why.
    //  History is never rewritten — a released or cancelled allocation keeps what
    //  it delivered (§28, §29).
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS requisition_allocation_events (
            id $pk,
            allocation_id INT NULL, requisition_id INT NULL,
            event VARCHAR(30) DEFAULT '',
            from_qty INT NULL, to_qty INT NULL,
            from_status VARCHAR(20) DEFAULT '', to_status VARCHAR(20) DEFAULT '',
            actor VARCHAR(150) DEFAULT '', reason VARCHAR(255) DEFAULT '',
            created_at VARCHAR(30) DEFAULT ''
        )");
    } catch (Throwable $e) {}
    try { db()->exec("CREATE INDEX idx_rfule_alloc ON requisition_allocation_events (allocation_id, id)"); } catch (Throwable $e) {}
    if ($linked) $doneAt = db_epoch();
}

// ---- reading ----------------------------------------------------------------

function rful_get($id) {
    rful_migrate();
    $i = rful_id($id, $ok); if (!$ok || $i === null) return null;
    try { return ops_one("SELECT * FROM requisition_allocations WHERE id=?", [$i]) ?: null; }
    catch (Throwable $e) { return null; }
}
function rful_list($requisitionId, $includeClosed = true) {
    rful_migrate();
    $r = rful_id($requisitionId, $ok); if (!$ok || $r === null) return [];
    $where = $includeClosed ? '' : " AND status IN ('" . implode("','", RFUL_OPEN_STATES) . "')";
    try { return ops_all("SELECT * FROM requisition_allocations WHERE requisition_id=?$where ORDER BY id", [$r]) ?: []; }
    catch (Throwable $e) { return []; }
}

//  FULFILLED, counted through M3's own definition of "filled" — candidates in
//  REQF_FILLED_STAGES. There is no second counter and no stored total to drift.
function rful_filled_stages() {
    return defined('REQF_FILLED_STAGES') ? REQF_FILLED_STAGES : ['ACCEPTED'];
}
function rful_fulfilled($allocationId) {
    $a = rful_id($allocationId, $ok); if (!$ok || $a === null) return 0;
    $ph = implode(',', array_fill(0, count(rful_filled_stages()), '?'));
    try {
        return (int) ops_val("SELECT COUNT(*) FROM candidates WHERE allocation_id=? AND stage IN ($ph)",
                             array_merge([$a], rful_filled_stages()));
    } catch (Throwable $e) { return 0; }
}

//  THE PICTURE OF ONE DEMAND. Every number the dashboard, the export and the
//  tests read comes from here, so they cannot disagree.
function rful_summary($requisitionId) {
    rful_migrate();
    $rq = rful_id($requisitionId, $ok);
    $out = ['requisition_id' => (int) ($rq ?: 0), 'authorised' => 0, 'allocated' => 0,
            'fulfilled' => 0, 'sourced_fulfilled' => 0, 'unallocated' => 0, 'remaining' => 0,
            'direct_fulfilled' => 0, 'committed' => 0, 'over_committed' => 0, 'sources' => []];
    if (!$ok || $rq === null) return $out;

    //  AUTHORISED is M3's own requested figure (quantity less cancelled
    //  vacancies), not a number of our own.
    if (function_exists('reqf_counts')) {
        $c = reqf_counts($rq);
        $out['authorised'] = max(0, (int) ($c['requested'] ?? 0) - (int) ($c['cancelled'] ?? 0));
        $out['fulfilled']  = (int) ($c['filled'] ?? 0);      // everybody in a seat, whatever their source
    } else {
        try { $out['authorised'] = max(0, (int) ops_val("SELECT quantity FROM requisitions WHERE id=?", [$rq])); }
        catch (Throwable $e) {}
    }
    foreach (rful_list($rq) as $a) {
        $live = in_array(strtoupper((string) $a['status']), RFUL_LIVE_STATES, true);
        $f = rful_fulfilled((int) $a['id']);
        //  EVERY allocation counts, closed ones included — because closing pins an
        //  allocation down to exactly what it DELIVERED, and those people have
        //  arrived: their seats are spent, not returned. Counting only live rows
        //  made a requirement report one person joined against zero allocated,
        //  which breaks FULFILLED ≤ ALLOCATED. Only the UNDELIVERED remainder of a
        //  closed allocation goes back, and the pinning is what returns it.
        //  A row contributes at LEAST nothing. The door refuses a negative
        //  quantity, but a manual database fix, a bad migration or a future writer
        //  could still produce one — and a negative here made ALLOCATED negative
        //  and pushed UNALLOCATED above the approved headcount, so the engine
        //  would promise more people than were ever approved. A derived figure
        //  must be bounded by its own definition, not by trust in the rows.
        $out['allocated'] += max(0, (int) $a['allocated_qty']);
        $out['sourced_fulfilled'] += $f;
        $out['sources'][] = [
            'id' => (int) $a['id'], 'source' => (string) $a['source'],
            'source_entity_id' => $a['source_entity_id'] === null ? null : (int) $a['source_entity_id'],
            'label' => (string) $a['source_label'], 'status' => (string) $a['status'],
            'allocated' => (int) $a['allocated_qty'], 'fulfilled' => $f,
            'remaining' => $live ? max(0, (int) $a['allocated_qty'] - $f) : 0,
        ];
    }
    //  People who arrived without an allocation — the direct path, which stays
    //  legitimate (ADR-001 and the pre-Phase-4 world both produce them).
    $ph = implode(',', array_fill(0, count(rful_filled_stages()), '?'));
    try {
        $out['direct_fulfilled'] = (int) ops_val(
            "SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND COALESCE(allocation_id,0)=0 AND stage IN ($ph)",
            array_merge([$rq], rful_filled_stages()));
    } catch (Throwable $e) {}
    //  COMMITTED is what the requirement has already spent: every seat promised to
    //  a source, PLUS everybody who arrived without one. A person found directly
    //  fills an approved position just as surely as an agency's placement does, so
    //  their seat is gone and must not be promised to anybody.
    $out['committed']     = $out['allocated'] + $out['direct_fulfilled'];
    //  Never more sourceable than was approved. That is guaranteed by the row-level
    //  clamp above (every contribution is >= 0, so COMMITTED is >= 0), not by a
    //  second bound here — a mutation proved an extra min() was unreachable, and
    //  defensive code that cannot be reached cannot be trusted or tested.
    $out['unallocated']   = max(0, $out['authorised'] - $out['committed']);
    $out['remaining']     = max(0, $out['authorised'] - $out['fulfilled']);
    //  Phase 4 never refuses a joining — M6 owns that — so a direct arrival can
    //  still push an existing promise past the ceiling. That is SHOWN, never
    //  silently corrected: trimming a source's promise is a person's decision.
    $out['over_committed'] = max(0, $out['committed'] - $out['authorised']);
    return $out;
}

// ---- the door ---------------------------------------------------------------

//  May this caller change sourcing on this requirement at all? Asked about the
//  person, before anything that would reveal the requirement's state — the
//  ordering lesson M5's audit paid for.
function rful_may_touch($requisitionId, array $opt = []) {
    if (function_exists('licence_blocks') && licence_blocks('mod.hiring.view')) return 'NO_ENTITLEMENT';
    if (empty($opt['skip_permission'])) {
        if (!function_exists('is_coordinator_level') || !is_coordinator_level()) return 'NO_PERMISSION';
    }
    $rq = rful_id($requisitionId, $ok);
    if (!$ok || $rq === null) return 'NO_REQUISITION';
    try { $r = ops_one("SELECT id, office_id, sbu FROM requisitions WHERE id=?", [$rq]); }
    catch (Throwable $e) { $r = null; }
    if (!$r) return 'NO_REQUISITION';
    if (function_exists('scope_allows') && !scope_allows($r['office_id'] ?? null, $r['sbu'] ?? null)) return 'OUT_OF_SCOPE';
    return 'OK';
}

//  Promising seats to a source is recruitment execution against an approved
//  demand, so it asks M6's gate — which asks M4. Phase 4 adds no second opinion.
function rful_exec_block($requisitionId) {
    if (!function_exists('rexec_block_reason')) return '';
    return (string) rexec_block_reason($requisitionId, 'ADVANCE');
}

//  CREATE an allocation. $opt['expect_allocated'] is the total-allocated figure
//  the screen was showing — the stale-save guard.
function rful_allocate($requisitionId, $source, $qty, array $opt = []) {
    rful_migrate();
    $fail = fn($c, $extra = '') => ['ok' => false, 'code' => $c,
        'reason' => (RFUL_CODES[$c] ?? $c) . ($extra !== '' ? ' — ' . $extra : ''), 'id' => 0];

    $gate = rful_may_touch($requisitionId, $opt);
    if ($gate !== 'OK') return $fail($gate);
    $rq = (int) rful_id($requisitionId, $ok);

    $why = rful_exec_block($rq);
    if ($why !== '') return $fail('EXECUTION_BLOCKED', $why);

    $src = strtoupper(trim((string) (is_scalar($source) ? $source : '')));
    if (!rful_source_ok($src)) return $fail('BAD_SOURCE');
    $n = rful_qty($qty, $qOk);
    if (!$qOk) return $fail('BAD_QUANTITY');
    if (!rful_source_entity_ok($src, $opt['source_entity_id'] ?? null, $eCode)) return $fail($eCode);
    $ent = rful_id($opt['source_entity_id'] ?? null, $x);

    $sum = rful_summary($rq);
    //  The stale-save guard. "Nothing allocated yet" is a legitimate expectation,
    //  so zero is a value here, not an absence — and a malformed expectation is
    //  STALE rather than ignored, so a bad value can never buy a seat.
    if (array_key_exists('expect_allocated', $opt)) {
        $exp = rful_count($opt['expect_allocated'], $xOk);
        if (!$xOk || $exp !== $sum['allocated']) return $fail('STALE');
    }
    //  THE CEILING. Allocated may never exceed authorised — the invariant this
    //  whole phase exists for. It is checked here and CONFIRMED after the write,
    //  because check-then-write is not atomic and two planners allocate at once.
    if ($sum['committed'] + $n > $sum['authorised'])
        return $fail('OVER_AUTHORISED', $sum['unallocated'] . ' of ' . $sum['authorised'] . ' still unallocated');

    $who = rful_who(); $now = rful_now();
    try {
        db()->prepare("INSERT INTO requisition_allocations
            (requisition_id, source, source_entity_id, source_label, allocated_qty, status, target_date, note,
             created_by, created_at, updated_by, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$rq, $src, $ent, substr((string) ($opt['label'] ?? ''), 0, 160), $n,
                       'PLANNED', substr((string) ($opt['target_date'] ?? ''), 0, 20),
                       substr((string) ($opt['note'] ?? ''), 0, 255), $who, $now, $who, $now]);
        $id = (int) db()->lastInsertId();
    } catch (Throwable $e) { return $fail('LOST_RACE'); }

    //  THE COMPENSATING CHECK. If several planners all passed the ceiling test and
    //  all wrote, the requirement is now over-allocated, and EVERY arrival that
    //  finds it so withdraws ITS OWN row.
    //
    //  This used to withdraw only the row with the highest id — an attempt to
    //  preserve exactly one winner. With two racers that looked right. With six it
    //  was measured leaving FIVE over-allocations standing, each reporting success,
    //  and the requirement over-promised by four: the ceiling this whole phase
    //  exists to defend, broken by the control meant to defend it. It was invisible
    //  until the concurrency harness was fixed to make the processes genuinely
    //  collide.
    //
    //  Withdrawing my own row can leave NO winner when several arrive together.
    //  That is the ratified capacity rule and the same choice M4 made at its own
    //  headcount ceiling: refusing a claim that could in principle have succeeded
    //  is the safe direction, and the seats stay available for the next valid
    //  transaction. Never over-allocated.
    $after = rful_summary($rq);
    if ($after['committed'] > $after['authorised']) {
        try { db()->prepare("DELETE FROM requisition_allocations WHERE id=?")->execute([$id]); } catch (Throwable $e) {}
        rful_event(0, $rq, 'ALLOCATE_REVERTED', null, $n, '', '', 'another allocation took the remaining seats first');
        return $fail('OVER_AUTHORISED', 'another allocation took those seats a moment earlier');
    }
    rful_event($id, $rq, 'ALLOCATED', null, $n, '', 'PLANNED', (string) ($opt['reason'] ?? ''));
    if (function_exists('act_log'))
        act_log('REQUISITION', $rq, 'NOTE', 'Fulfilment allocated: ' . $n . ' × ' . $src,
                ['body' => (string) ($opt['note'] ?? '')]);
    return ['ok' => true, 'code' => 'OK', 'reason' => 'allocated', 'id' => $id];
}

//  CHANGE the allocated quantity — the rebalancing case (§13). Never below what
//  the source has already delivered, never past the requirement's ceiling.
function rful_reallocate($allocationId, $newQty, array $opt = []) {
    rful_migrate();
    $fail = fn($c, $extra = '') => ['ok' => false, 'code' => $c,
        'reason' => (RFUL_CODES[$c] ?? $c) . ($extra !== '' ? ' — ' . $extra : ''), 'id' => 0];
    $a = rful_get($allocationId); if (!$a) return $fail('NO_ALLOCATION');
    $rq = (int) $a['requisition_id'];
    $gate = rful_may_touch($rq, $opt); if ($gate !== 'OK') return $fail($gate);
    $why = rful_exec_block($rq); if ($why !== '') return $fail('EXECUTION_BLOCKED', $why);
    //  CLOSED means released or cancelled — the same answer the seat check gives.
    //
    //  This asked whether the allocation was OPEN, which excludes FULFILLED, while
    //  rful_seat_block() asks whether it is LIVE, which includes it. Two answers to
    //  one question again, and the lifecycle document has said all along that
    //  FULFILLED is "not a closed state: a live source that happens to be full".
    //
    //  The consequence was not theoretical. FULFILLED is DERIVED, and under
    //  concurrency it can be left stale — one process syncs it to FULFILLED while
    //  another has just reverted a joining. A coordinator trying to trim a promise
    //  the source had not actually delivered was then refused with "that allocation
    //  is closed", which is both false and the exact thing the negative matrix
    //  forbids: the correction must never be the thing that is refused. Cutting
    //  below what was genuinely delivered is still refused, by BELOW_FULFILLED.
    //
    //  Stated as "allow only LIVE", character for character the same test the seat
    //  check makes — not as "refuse only CLOSED", which would let a status in no
    //  lifecycle at all through. A probe written in an earlier pass caught exactly
    //  that mistake here within a minute of it being made.
    if (!in_array(strtoupper((string) $a['status']), RFUL_LIVE_STATES, true)) return $fail('BAD_STATE');

    $n = rful_qty($newQty, $qOk); if (!$qOk) return $fail('BAD_QUANTITY');
    $was = (int) $a['allocated_qty'];
    if ($n === $was) return ['ok' => true, 'code' => 'NO_CHANGE', 'reason' => 'unchanged', 'id' => (int) $a['id']];

    if (array_key_exists('expect', $opt)) {
        $exp = rful_count($opt['expect'], $eOk);
        if (!$eOk || $exp !== $was) return $fail('STALE');
    }
    //  Cutting an allocation below what it has already delivered would erase
    //  history and invent a negative remainder.
    $done = rful_fulfilled((int) $a['id']);
    if ($n < $done) return $fail('BELOW_FULFILLED', $done . ' already delivered');

    //  ONE READ, ONE MOMENT. $was came from the top of this function and the
    //  requirement's total comes from here, so under a real race they can describe
    //  two different instants — and "total minus mine plus new" then subtracts a
    //  quantity that is no longer mine. The compare-and-swap below always
    //  protected the WRITE, but the sum above it could refuse with a false reason
    //  ("you would exceed the approval") when the truth was simply that somebody
    //  resized this a moment ago. A disagreement between the two IS the race.
    $sum = rful_summary($rq);
    $now = null;
    foreach ($sum['sources'] as $srcRow) if ((int) $srcRow['id'] === (int) $a['id']) $now = (int) $srcRow['allocated'];
    if ($now === null || $now !== $was) return $fail('LOST_RACE');

    //  A change is refused when it leaves the requirement over-committed AND makes
    //  it worse. Trimming is never refused: when a direct arrival has already
    //  pushed a requirement past its ceiling, cutting a source's promise back is
    //  precisely the correction the coordinator needs to be able to make.
    $will = $sum['committed'] - $was + $n;
    if ($will > $sum['authorised'] && $will > $sum['committed'])
        return $fail('OVER_AUTHORISED', $sum['unallocated'] . ' still unallocated');

    //  COMPARE AND SWAP on the quantity the check was made against.
    $wasCommitted = $sum['committed'];
    try {
        $st = db()->prepare("UPDATE requisition_allocations SET allocated_qty=?, updated_by=?, updated_at=?
                             WHERE id=? AND allocated_qty=?");
        $st->execute([$n, rful_who(), rful_now(), (int) $a['id'], $was]);
        if ((int) $st->rowCount() < 1) return $fail('LOST_RACE');
    } catch (Throwable $e) { return $fail('LOST_RACE'); }

    $after = rful_summary($rq);
    if ($after['committed'] > $after['authorised'] && $after['committed'] > $wasCommitted) {
        try { db()->prepare("UPDATE requisition_allocations SET allocated_qty=? WHERE id=?")->execute([$was, (int) $a['id']]); }
        catch (Throwable $e) {}
        rful_event((int) $a['id'], $rq, 'REALLOCATE_REVERTED', $was, $n, '', '', 'the requirement would have been over-allocated');
        return $fail('OVER_AUTHORISED', 'another change took those seats a moment earlier');
    }
    rful_event((int) $a['id'], $rq, 'REALLOCATED', $was, $n, '', '', (string) ($opt['reason'] ?? ''));
    if (function_exists('act_log'))
        act_log('REQUISITION', $rq, 'NOTE', 'Fulfilment reallocated: ' . $was . ' → ' . $n . ' × ' . $a['source'],
                ['body' => (string) ($opt['reason'] ?? '')]);
    return ['ok' => true, 'code' => 'OK', 'reason' => 'reallocated', 'id' => (int) $a['id']];
}

//  END an allocation — RELEASED (the source gives back what it did not deliver)
//  or CANCELLED (it will not deliver at all). In both cases what it HAS
//  delivered stays delivered: the people are in seats, and no history is erased.
function rful_close($allocationId, $to, array $opt = []) {
    rful_migrate();
    $fail = fn($c, $extra = '') => ['ok' => false, 'code' => $c,
        'reason' => (RFUL_CODES[$c] ?? $c) . ($extra !== '' ? ' — ' . $extra : ''), 'id' => 0];
    $a = rful_get($allocationId); if (!$a) return $fail('NO_ALLOCATION');
    $rq = (int) $a['requisition_id'];
    $gate = rful_may_touch($rq, $opt); if ($gate !== 'OK') return $fail($gate);
    $st = strtoupper((string) $to);
    if (!in_array($st, RFUL_CLOSED_STATES, true)) return $fail('BAD_STATE');
    $was = strtoupper((string) $a['status']);
    if (in_array($was, RFUL_CLOSED_STATES, true)) return $fail('BAD_STATE', 'already ' . strtolower($was));

    $done = rful_fulfilled((int) $a['id']);
    //  The delivered seats are kept by pinning the allocation down to them; the
    //  rest returns to the requirement as unallocated capacity. A cancelled
    //  allocation that delivered nobody simply frees all of its seats.
    try {
        $stm = db()->prepare("UPDATE requisition_allocations
                              SET status=?, allocated_qty=?, closed_by=?, closed_at=?, close_reason=?, updated_by=?, updated_at=?
                              WHERE id=? AND status=?");
        $stm->execute([$st, $done, rful_who(), rful_now(), substr((string) ($opt['reason'] ?? ''), 0, 255),
                       rful_who(), rful_now(), (int) $a['id'], $a['status']]);
        if ((int) $stm->rowCount() < 1) return $fail('LOST_RACE');
    } catch (Throwable $e) { return $fail('LOST_RACE'); }

    rful_event((int) $a['id'], $rq, $st === 'RELEASED' ? 'RELEASED' : 'CANCELLED',
               (int) $a['allocated_qty'], $done, $was, $st, (string) ($opt['reason'] ?? ''));
    if (function_exists('act_log'))
        act_log('REQUISITION', $rq, 'NOTE',
                ($st === 'RELEASED' ? 'Fulfilment released: ' : 'Fulfilment cancelled: ')
                . (max(0, (int) $a['allocated_qty'] - $done)) . ' seat(s) returned from ' . $a['source'],
                ['body' => (string) ($opt['reason'] ?? '')]);
    return ['ok' => true, 'code' => 'OK', 'reason' => 'closed', 'id' => (int) $a['id'], 'kept' => $done];
}

//  Move an allocation between the two open states (PLANNED ⇄ ACTIVE), and mark
//  it FULFILLED when it is full. Derived rather than typed in, so the state and
//  the numbers cannot disagree.
function rful_sync_state($allocationId) {
    $a = rful_get($allocationId); if (!$a) return '';
    $was = strtoupper((string) $a['status']);
    if (in_array($was, RFUL_CLOSED_STATES, true)) return $was;      // closed stays closed
    $done = rful_fulfilled((int) $a['id']);
    $want = $done >= (int) $a['allocated_qty'] ? 'FULFILLED' : ($done > 0 ? 'ACTIVE' : $was);
    if ($want === 'FULFILLED' && (int) $a['allocated_qty'] === 0) $want = $was;
    if ($want === $was) return $was;
    try { db()->prepare("UPDATE requisition_allocations SET status=?, updated_at=? WHERE id=? AND status=?")
              ->execute([$want, rful_now(), (int) $a['id'], $a['status']]); }
    catch (Throwable $e) { return $was; }
    rful_event((int) $a['id'], (int) $a['requisition_id'], 'STATE', null, null, $was, $want, 'derived from fulfilment');
    return $want;
}

//  The ledger. Append-only; it records what happened, never what was attempted
//  and refused — a refused operation writes nothing here (M6 invariant I18).
function rful_event($allocationId, $requisitionId, $event, $fromQty, $toQty, $fromStatus, $toStatus, $reason = '') {
    try {
        db()->prepare("INSERT INTO requisition_allocation_events
            (allocation_id, requisition_id, event, from_qty, to_qty, from_status, to_status, actor, reason, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([(int) $allocationId ?: null, (int) $requisitionId ?: null, (string) $event,
                       $fromQty === null ? null : (int) $fromQty, $toQty === null ? null : (int) $toQty,
                       (string) $fromStatus, (string) $toStatus, rful_who(),
                       substr((string) $reason, 0, 255), rful_now()]);
    } catch (Throwable $e) {}
}
function rful_events($allocationId) {
    rful_migrate();
    $a = rful_id($allocationId, $ok); if (!$ok || $a === null) return [];
    try { return ops_all("SELECT * FROM requisition_allocation_events WHERE allocation_id=? ORDER BY id", [$a]) ?: []; }
    catch (Throwable $e) { return []; }
}

// ============================================================================
//  THE CANDIDATE ↔ ALLOCATION LINK
//
//  Which source a person arrived through. It is an accountability and capacity
//  link, not a form field, so — exactly as M5 did with ownership — it leaves the
//  blind field list and travels one door with one set of controls.
//
//  Phase 4 NEVER decides whether somebody holds a seat on the requirement. That
//  is M6's, and it stays M6's. Phase 4 decides only whether a source may be
//  credited with them. So the worst this can ever do is drop a credit back to
//  the direct path — it can never push a person out of a seat they hold.
// ============================================================================

//  Is there room under this allocation for one more person?
function rful_seat_block($allocationId, $exceptCandidateId = 0) {
    $a = rful_get($allocationId);
    if (!$a) return 'NO_ALLOCATION';
    //  CLOSED means released or cancelled. FULFILLED is not closed — it is a live
    //  source that happens to be full, and telling somebody their source was
    //  "closed" when it is simply full is a false answer, even though both refuse.
    //
    //  Anything OUTSIDE the lifecycle fails closed. This asked only whether the
    //  status was closed, so a row carrying a state in no lifecycle at all could
    //  still be credited with people — while rful_reallocate(), asking the open
    //  states, refused to resize that very same row. Two answers to one question,
    //  and the permissive one was the security-relevant one.
    if (!in_array(strtoupper((string) $a['status']), RFUL_LIVE_STATES, true)) return 'BAD_STATE';
    $done = rful_fulfilled((int) $a['id']);
    $self = rful_id($exceptCandidateId, $sOk);
    if ($sOk && $self !== null) {
        //  Somebody already counted against this allocation does not consume a
        //  second seat by being saved again.
        $ph = implode(',', array_fill(0, count(rful_filled_stages()), '?'));
        try {
            $mine = (int) ops_val("SELECT COUNT(*) FROM candidates WHERE id=? AND allocation_id=? AND stage IN ($ph)",
                                  array_merge([$self, (int) $a['id']], rful_filled_stages()));
        } catch (Throwable $e) { $mine = 0; }
        $done = max(0, $done - $mine);
    }
    if ($done >= (int) $a['allocated_qty']) return 'OVER_ALLOCATED';
    return '';
}

//  Set / clear / change a candidate's allocation. $destinationRequisitionId is
//  the requirement the SAVE IS PRODUCING — the M5 origin-vs-destination lesson,
//  paid for once and not paid for again.
function rful_attach($candidateId, $allocationId, array $opt = []) {
    rful_migrate();
    $fail = fn($c, $extra = '') => ['ok' => false, 'code' => $c,
        'reason' => (RFUL_CODES[$c] ?? $c) . ($extra !== '' ? ' — ' . $extra : ''), 'id' => 0];

    $cid = rful_id($candidateId, $cOk);
    if (!$cOk || $cid === null) return $fail('BAD_VALUE');
    $wanted = rful_id($allocationId, $aOk);
    if (!$aOk) return $fail('BAD_VALUE');           // "abc", -1, 1e3, an array — never a link

    try { $cand = ops_one("SELECT id, requisition_id, allocation_id, stage FROM candidates WHERE id=?", [$cid]); }
    catch (Throwable $e) { $cand = null; }
    if (!$cand) return $fail('BAD_VALUE');

    $dest = array_key_exists('requisition_id', $opt)
        ? (int) rful_id($opt['requisition_id'], $dOk) : (int) ($cand['requisition_id'] ?? 0);

    //  Entitlement → permission → record → scope, asked about the person before
    //  anything that would describe the requirement (M5's refusal ordering).
    $gate = rful_may_touch($dest ?: ($cand['requisition_id'] ?? 0), $opt);
    if ($gate !== 'OK') return $fail($gate);

    $was = rful_id($cand['allocation_id'] ?? null, $wOk);
    if (!$wOk) $was = null;
    if (($wanted ?? 0) === ($was ?? 0)) return ['ok' => true, 'code' => 'NO_CHANGE', 'reason' => 'unchanged', 'id' => (int) ($was ?? 0)];

    if ($wanted !== null) {
        $a = rful_get($wanted);
        if (!$a) return $fail('NO_ALLOCATION');
        //  AN ALLOCATION BELONGS TO ONE REQUIREMENT. Crediting a source on
        //  requirement A with a person who is filling requirement B would let one
        //  approved demand quietly pay for another's headcount.
        if ((int) $a['requisition_id'] !== $dest || $dest <= 0) return $fail('NO_ALLOCATION', 'that allocation belongs to a different requirement');
        $why = rful_seat_block($wanted, $cid);
        if ($why !== '') return $fail($why);
    }

    //  COMPARE AND SWAP on the link the decision was made against.
    try {
        $sql = $was === null
            ? "UPDATE candidates SET allocation_id=? WHERE id=? AND COALESCE(allocation_id,0)=0"
            : "UPDATE candidates SET allocation_id=? WHERE id=? AND allocation_id=?";
        $args = $was === null ? [$wanted, $cid] : [$wanted, $cid, $was];
        $st = db()->prepare($sql); $st->execute($args);
        if ((int) $st->rowCount() < 1) return $fail('LOST_RACE');
    } catch (Throwable $e) { return $fail('LOST_RACE'); }

    //  THE COMPENSATING CHECK. Two people can pass the seat test and both write.
    //  Nobody is displaced: the arriving link is the one withdrawn, and the person
    //  keeps whatever seat M6 gave them on the requirement itself.
    if ($wanted !== null && rful_over_allocated($wanted)) {
        try {
            $st = db()->prepare("UPDATE candidates SET allocation_id=? WHERE id=? AND allocation_id=?");
            $st->execute([$was, $cid, $wanted]);
        } catch (Throwable $e) {}
        rful_event($wanted, $dest, 'ATTACH_REVERTED', null, null, '', '', 'that source had no seat left');
        return $fail('OVER_ALLOCATED', 'another arrival took that source\'s last seat a moment earlier');
    }

    rful_event((int) ($wanted ?? 0), $dest, $wanted === null ? 'DETACHED' : 'ATTACHED',
               null, null, '', '', 'candidate #' . $cid . ((string) ($opt['reason'] ?? '') !== '' ? ' — ' . $opt['reason'] : ''));
    if ($was !== null && $wanted !== null) rful_event($was, $dest, 'DETACHED', null, null, '', '', 'candidate #' . $cid . ' moved to another source');
    foreach (array_unique(array_filter([(int) ($was ?? 0), (int) ($wanted ?? 0)])) as $syncId) rful_sync_state($syncId);
    if (function_exists('act_log'))
        act_log('CANDIDATE', $cid, 'NOTE',
                $wanted === null ? 'Fulfilment source cleared' : 'Fulfilment source set to allocation #' . $wanted, []);
    return ['ok' => true, 'code' => 'OK', 'reason' => 'attached', 'id' => (int) ($wanted ?? 0)];
}

//  Has this allocation been credited with more people than it was promised?
function rful_over_allocated($allocationId) {
    $a = rful_get($allocationId); if (!$a) return false;
    return rful_fulfilled((int) $a['id']) > (int) $a['allocated_qty'];
}

//  The form's own door — mirrors rasg_apply_posted(), so the caller does not
//  have to remember the rules. Returns '' when there is nothing to refuse.
function rful_apply_posted($candidateId, array $post, $destinationRequisitionId, $source = '') {
    if (!array_key_exists('allocation_id', $post)) return '';
    $v = $post['allocation_id'];
    $r = rful_attach($candidateId, ($v === '' || $v === null) ? null : $v,
                     ['requisition_id' => $destinationRequisitionId, 'reason' => (string) $source]);
    if ($r['ok']) return '';
    return rful_refusal($r['code']);
}

//  What the person is told. A refusal never describes a record they may not see.
function rful_refusal($code) {
    switch ($code) {
        case 'NO_ENTITLEMENT':        return 'Hiring is not part of this workspace’s plan, so fulfilment sourcing cannot be changed.';
        case 'NO_PERMISSION':         return 'You do not have permission to change fulfilment sourcing.';
        case 'OUT_OF_SCOPE':          return 'That requirement is outside your office or branch, so its sourcing cannot be changed here.';
        case 'NO_REQUISITION':        return 'That requirement could not be found.';
        case 'NO_ALLOCATION':         return 'That fulfilment source could not be used for this requirement.';
        case 'OVER_ALLOCATED':        return 'That source has no allocated seat left. Increase its allocation first, or choose another source.';
        case 'OVER_AUTHORISED':       return 'That would promise more people than the requirement is authorised for.';
        case 'BELOW_FULFILLED':       return 'An allocation cannot be cut below the people that source has already delivered.';
        case 'BAD_STATE':             return 'That allocation is closed.';
        case 'BAD_QUANTITY':          return 'Enter the number of people as a whole number.';
        case 'BAD_SOURCE':            return 'Choose a fulfilment source from the list.';
        case 'SOURCE_ENTITY_UNKNOWN': return 'That supplier, marketplace requirement or person is not on file in this workspace.';
        case 'EXECUTION_BLOCKED':     return 'That requirement is not executable right now, so its sourcing cannot be changed.';
        case 'STALE':                 return 'Somebody changed this while your screen was open. It has been reloaded — please check and try again.';
        case 'LOST_RACE':             return 'Somebody else changed this at the same moment. Please check and try again.';
        case 'BAD_VALUE':             return 'That fulfilment source is not a usable value.';
    }
    return 'That change could not be made.';
}

//  DEFENCE IN DEPTH. Whatever wrote to `candidates` — this door, a blind field
//  list somebody adds in two years' time, or a raw statement — the row is read
//  back and any link that should not exist is removed. The same shape M5 and M6
//  use, for the same reason: a control that only one caller remembers is not a
//  control.
function rful_enforce_candidate($candidateId, $priorAllocationId = 0) {
    rful_migrate();
    $cid = rful_id($candidateId, $ok); if (!$ok || $cid === null) return '';
    try { $c = ops_one("SELECT id, requisition_id, allocation_id, stage FROM candidates WHERE id=?", [$cid]); }
    catch (Throwable $e) { return ''; }
    if (!$c) return '';
    $link = rful_id($c['allocation_id'] ?? null, $lOk);
    if (!$lOk) { rful_clear_link($cid); return rful_refusal('BAD_VALUE'); }
    if ($link === null) return '';

    $a = rful_get($link);
    $bad = '';
    if (!$a)                                                     $bad = 'NO_ALLOCATION';
    elseif ((int) $a['requisition_id'] !== (int) ($c['requisition_id'] ?? 0)) $bad = 'NO_ALLOCATION';
    elseif (rful_over_allocated($link))                          $bad = 'OVER_ALLOCATED';
    if ($bad === '') { rful_sync_state($link); return ''; }

    //  Over-allocated: the ESTABLISHED credits stay, the arriving one goes. Never
    //  displace somebody already counted — the ratified Phase 3 capacity rule.
    if ($bad === 'OVER_ALLOCATED') {
        $ph = implode(',', array_fill(0, count(rful_filled_stages()), '?'));
        try {
            $ids = ops_all("SELECT id FROM candidates WHERE allocation_id=? AND stage IN ($ph) ORDER BY id",
                           array_merge([$link], rful_filled_stages()));
        } catch (Throwable $e) { $ids = []; }
        $keep = array_slice(array_map(fn($r) => (int) $r['id'], $ids), 0, (int) $a['allocated_qty']);
        if (in_array($cid, $keep, true)) return '';              // established — untouched
    }
    rful_clear_link($cid, (int) $link);
    rful_event((int) $link, (int) ($c['requisition_id'] ?? 0), 'LINK_REVOKED', null, null, '', '',
               'candidate #' . $cid . ' — ' . (RFUL_CODES[$bad] ?? $bad));
    if ($link) rful_sync_state($link);
    return rful_refusal($bad);
}
function rful_clear_link($candidateId, $expect = 0) {
    try {
        if ($expect > 0) db()->prepare("UPDATE candidates SET allocation_id=NULL WHERE id=? AND allocation_id=?")->execute([(int) $candidateId, (int) $expect]);
        else             db()->prepare("UPDATE candidates SET allocation_id=NULL WHERE id=?")->execute([(int) $candidateId]);
    } catch (Throwable $e) {}
}

//  Every candidate whose link is wrong, across the whole workspace — the
//  reconciliation sweep §48 asks for, and what the create path uses to make sure
//  an INSERT never smuggles a link past the door.
function rful_bad_links($requisitionId = 0) {
    rful_migrate();
    $out = [];
    $where = ''; $args = [];
    $rq = rful_id($requisitionId, $ok);
    if ($ok && $rq !== null) { $where = " AND c.requisition_id=?"; $args[] = $rq; }
    try {
        $rows = ops_all("SELECT c.id FROM candidates c WHERE COALESCE(c.allocation_id,0)<>0$where ORDER BY c.id", $args);
    } catch (Throwable $e) { return []; }
    foreach ($rows as $r) $out[] = (int) $r['id'];
    return $out;
}

// ============================================================================
//  THE SCREEN
//
//  One panel on the requirement: where the twenty people are coming from, how
//  many each source still owes, and what is still unallocated. The user never
//  has to hold two numbers in their head — the panel shows the difference.
//
//  NOTHING here is a control. Every button is re-decided in rful_allocate() /
//  rful_reallocate() / rful_close(), which a crafted POST reaches exactly as the
//  form does. The panel only decides what is worth showing.
// ============================================================================

function ops_requisition_allocations($route, $method) {
    if (function_exists('req_scope_gate')) req_scope_gate();      // branch scope, before anything reads an id
    $id = (int) ($_GET['id'] ?? 0);
    $back = '/requisition?id=' . $id;
    //  Every POST in this application is CSRF-checked centrally before a route is
    //  reached (index.php), and every POST form is stamped with the token, so
    //  there is nothing to repeat here.
    if ($method !== 'POST') redirect($back);

    $do = strtolower(trim((string) ($_POST['do'] ?? '')));
    if ($do === 'allocate') {
        $opt = ['source_entity_id' => $_POST['source_entity_id'] ?? null,
                'label'       => $_POST['label'] ?? '',
                'target_date' => $_POST['target_date'] ?? '',
                'note'        => $_POST['note'] ?? ''];
        //  The screen sends back the total it was showing, so a form left open
        //  while somebody else allocated is refused rather than silently added on.
        if (array_key_exists('expect_allocated', $_POST)) $opt['expect_allocated'] = $_POST['expect_allocated'];
        $r = rful_allocate($id, $_POST['source'] ?? '', $_POST['qty'] ?? '', $opt);
        flash($r['ok'] ? 'Fulfilment source added.' : rful_refusal($r['code']), $r['ok'] ? 'success' : 'error');
    } elseif ($do === 'reallocate') {
        $r = rful_reallocate($_POST['allocation_id'] ?? 0, $_POST['qty'] ?? '',
                             ['expect' => $_POST['expect'] ?? null, 'reason' => $_POST['reason'] ?? '']);
        if ($r['ok'] && $r['code'] === 'NO_CHANGE') flash('Nothing to change there.');
        else flash($r['ok'] ? 'Allocation updated.' : rful_refusal($r['code']), $r['ok'] ? 'success' : 'error');
    } elseif ($do === 'release' || $do === 'cancel') {
        $r = rful_close($_POST['allocation_id'] ?? 0, $do === 'release' ? 'RELEASED' : 'CANCELLED',
                        ['reason' => $_POST['reason'] ?? '']);
        flash($r['ok']
            ? ($do === 'release' ? 'Seats returned to the requirement.' : 'That source was cancelled; its seats are back.')
            : rful_refusal($r['code']), $r['ok'] ? 'success' : 'error');
    } else {
        flash('That action is not available here.', 'error');
    }
    redirect($back);
    return true;
}

//  What the candidate screen offers as "arrived through". Only sources that can
//  still take somebody, plus whichever one this person is already credited to,
//  so an existing link never silently disappears from its own form.
function rful_picker($requisitionId, $keepAllocationId = 0) {
    $out = [];
    $keep = (int) rful_id($keepAllocationId, $kOk);
    $names = rful_sources();
    foreach (rful_list($requisitionId, true) as $a) {
        $id = (int) $a['id'];
        $open = in_array(strtoupper((string) $a['status']), RFUL_OPEN_STATES, true);
        $room = $open && rful_seat_block($id, 0) === '';
        if (!$room && $id !== $keep) continue;
        $left = max(0, (int) $a['allocated_qty'] - rful_fulfilled($id));
        $out[$id] = ($names[$a['source']] ?? $a['source'])
                  . ((string) $a['source_label'] !== '' ? ' — ' . $a['source_label'] : '')
                  . ' (' . $left . ' of ' . (int) $a['allocated_qty'] . ' still to arrive)';
    }
    return $out;
}
