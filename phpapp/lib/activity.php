<?php
// ============================================================================
//  The activity spine — one table, everything that ever happened
//
//  Blueprint 001 P1, and the highest-value table in the whole CRM plan, because
//  Customer 360 is impossible without it and every other CRM feature reads from
//  it. Nothing here is new *information* — the application already knows a quote
//  was sent, a report was issued, a complaint was raised. What it has never had
//  is one place to ask "what has happened with this customer", in date order,
//  without opening nine screens.
//
//  Four decisions, all of them consequences of the hosting answer (MilesWeb
//  shared, MySQL). They are written down because on a bigger box I would have
//  chosen differently:
//
//   1. **partner_id is denormalised onto every row.** The polymorphic link says
//      what the activity is *about* — a quotation, a deputation, a complaint.
//      But Customer 360 asks "everything for this customer", and resolving that
//      through five joins at read time is what makes a shared-hosting page take
//      four seconds. So the customer is resolved ONCE at write time and stored.
//      The cost is a denormalised column; the benefit is one indexed query.
//   2. **Indexes are created explicitly**, because nothing else in this codebase
//      does and MySQL will not invent them. A timeline without an index on
//      (partner_id, occurred_at) is a table scan on every customer page.
//   3. **`auto` separates what the system wrote from what a person typed.** A
//      timeline where "Quote sent" and "Rang him, he is thinking about it" look
//      identical teaches people to distrust both.
//   4. **Writing an activity must never break the thing that caused it.** Every
//      call is wrapped. A timeline is a record of work, not a precondition for
//      it — if the log fails, the quote still goes out.
//
//  What this file does NOT do: decide anything. It records. The gates live in
//  the modules that own the rule.
// ============================================================================

const ACT_KINDS = [
    'NOTE'      => 'Note',
    'CALL'      => 'Telephone call',
    'MEETING'   => 'Meeting',
    'VISIT'     => 'Site visit',
    'EMAIL'     => 'E-mail',
    'WHATSAPP'  => 'WhatsApp',
    'TASK'      => 'Task',
    'SYSTEM'    => 'Recorded by the system',
];

// What an activity can be about. Kept as a short code plus the route that opens
// it, so the timeline can link back without every caller passing a URL.
const ACT_ENTITIES = [
    'PARTNER'   => ['Customer',        '/client?id='],
    'LEAD'      => ['Lead',            '/lead?id='],
    'OPPORTUNITY' => ['Opportunity',   '/opportunity?id='],
    'INQUIRY'   => ['Inquiry',         '/inquiry-edit?id='],
    'QUOTE'     => ['Quotation',       '/quote?id='],
    'CALL'      => [null,               '/call?id='],   // labels for these two come from
    'JOB'       => [null,               '/job?id='],    // act_entities(), where T() can be called
    'REPORT'    => ['Report',          '/document?id='],
    'INVOICE'   => ['Invoice',         '/job?id='],
    'COMPLAINT' => ['Complaint',       '/complaint?id='],
    'NCR'       => ['Nonconformity',   '/ncr-item?id='],
    'CAPA'      => ['Corrective action','/capa-item?id='],
    // Phase 2 §17 — entities that already log to the spine (act_log) but were not
    // registered here, so the universal timeline could neither label nor link them.
    'CANDIDATE' => ['Candidate',       '/candidate?id='],
    'RECEIPT'   => ['Receipt',         '/receipt?id='],
    'CONTRACT'  => ['Contract',        '/contract?id='],
    'INSPECTOR' => ['Inspector',       '/inspector-profile?id='],   // Slice P1 — Credential Vault 360
    // Phase 3 · M1 — the hiring request and the recruitment requisition. Both
    // were calling a function that does not exist (activity_log), so nothing
    // they did was ever audited; registering them here is what lets act_log()
    // label and link them on the universal timeline.
    'HIRING_REQUEST' => ['Hiring Request',          '/hiring-request?id='],
    'REQUISITION'    => ['Recruitment Requisition', '/requisition?id='],
    // Phase 3 · M2 — approval configuration is sensitive, so policy and
    // delegation changes go on this same spine rather than a second one.
    'APPROVAL_POLICY'   => ['Approval policy',     '/recruit-approvals?id='],
    'APPROVAL_DELEGATE' => ['Approval delegation', '/approval-delegations'],
];

// A constant cannot call T(), so the two entries that name a business noun are
// left null above and filled in here.
function act_entities() {
    $m = ACT_ENTITIES;
    $m['CALL'][0] = TH('call');
    $m['JOB'][0]  = TH('job');
    return $m;
}


const ACT_DIRECTIONS = ['IN' => 'Incoming', 'OUT' => 'Outgoing', '' => ''];

function act_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return;
    $pdo = db(); $pk = pk_clause();
    $pdo->exec("CREATE TABLE IF NOT EXISTS activities (
        id $pk,
        kind VARCHAR(20) DEFAULT 'NOTE',
        entity_kind VARCHAR(20) DEFAULT '', entity_id INT NULL,
        partner_id INT NULL,
        subject VARCHAR(255) DEFAULT '', body TEXT,
        direction VARCHAR(4) DEFAULT '',
        occurred_at VARCHAR(30) DEFAULT '',
        duration_mins INT DEFAULT 0,
        outcome VARCHAR(60) DEFAULT '',
        with_whom VARCHAR(200) DEFAULT '',
        owner VARCHAR(150) DEFAULT '',
        office_id INT NULL, sbu VARCHAR(20) DEFAULT '',
        auto INT DEFAULT 0,
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // Nothing else in this codebase creates an index, and MySQL will not invent
    // one. Without these, every Customer 360 page is a table scan — which is
    // survivable at 160 rows and is not at 160,000.
    act_index('activities', 'idx_act_partner', '(partner_id, occurred_at)');
    act_index('activities', 'idx_act_entity',  '(entity_kind, entity_id)');
    act_index('activities', 'idx_act_when',    '(occurred_at)');
    //  M3 CORRECTION #8 · S1 — THE GUARD IS SET HERE, AND ONLY HERE.
    //
    //  It used to be set on the FIRST LINE, before any of the work. So a failure
    //  anywhere below left the guard latched: the migration was never retried,
    //  and — because correction #7 had made act_log()'s INSERT depend on a column
    //  added further down — EVERY module's audit trail went silently dead for the
    //  life of the process. The core spine is complete at this point, which is
    //  what act_log() actually needs, so this is where "done" becomes true.
    $doneAt = db_epoch();
    //  Optional metadata is attempted AFTER the guard, so it can never un-do the
    //  core, and it reports rather than throws.
    act_migrate_optional();
}

//  M3 CORRECTION #8 · S1 — OPTIONAL AUDIT METADATA, KEPT OPTIONAL.
//
//  cond_key carries correction #7's permanent-condition identity. Two of this
//  application's SEVENTY-SIX act_log() call sites use it. It must therefore never
//  be a precondition for the other seventy-four — or for these two.
//
//  Bounded: at most three attempts per workspace per process, so a host that
//  refuses DDL does not run an ALTER on every audit write. Not latched on
//  failure, so the next request (or the next workspace) tries again. Never
//  reported as successful when it was not.
//  M3 CORRECTION #9 · T2 — THE COLUMN IS CORRECTNESS. THE INDEX IS PERFORMANCE.
//
//  Correction #8 ensured both inside one function and returned one boolean. So a
//  failed CREATE INDEX — a pure performance structure — reported the whole feature
//  unavailable, and cond_key was not written into a column that existed and worked
//  perfectly. That is the conflation this whole sequence began with, one more time:
//
//      A USABLE COLUMN IS NOT AN INDEXED COLUMN.
//
//  They are therefore two states, two bounded retries and two error strings.
//  Neither can mark the other as successful, and the index can never roll back
//  the column.
//  Cheap METADATA READS, never DDL. They are what lets a repaired schema be
//  noticed again without hammering ALTER: if the structure is already there the
//  answer is yes, however many earlier attempts failed. Without them the retry
//  budget is spent by internal CHECKS rather than by real attempts, and a schema
//  that has just been repaired can never be seen — which is how correction #9's
//  first draft broke C8.5's retry case.
function act_has_cond_column() {
    try {
        if (db_driver() === 'sqlite') {
            foreach (db()->query("PRAGMA table_info(activities)")->fetchAll() as $c)
                if (($c['name'] ?? '') === 'cond_key') return true;
            return false;
        }
        $q = db()->prepare("SELECT 1 FROM information_schema.columns
                            WHERE table_schema=DATABASE() AND table_name='activities' AND column_name='cond_key'");
        $q->execute(); return (bool) $q->fetchColumn();
    } catch (Throwable $e) { return false; }
}
function act_has_cond_index() {
    try {
        if (db_driver() === 'sqlite') {
            $q = db()->prepare("SELECT 1 FROM sqlite_master WHERE type='index' AND name='idx_act_cond'");
            $q->execute(); return (bool) $q->fetchColumn();
        }
        $q = db()->prepare("SELECT 1 FROM information_schema.statistics
                            WHERE table_schema=DATABASE() AND table_name='activities' AND index_name='idx_act_cond'");
        $q->execute(); return (bool) $q->fetchColumn();
    } catch (Throwable $e) { return false; }
}

// ===========================================================================
//  M3 CORRECTION #11 · W1 — AN ERROR BELONGS TO THE WORKSPACE THAT PRODUCED IT
//
//  Correction #10 made the migration ATTEMPT LEDGER per workspace epoch and left
//  every ERROR channel a plain process global. This application switches
//  workspace IN PROCESS — "Log in as", provisioning, a cron sweeping tenants — so
//  a failure recorded against company A was still returned after the switch to
//  company B: a false diagnosis in B, and A's database error text handed to B.
//
//  The audit found FOUR such channels, not the one W1 named:
//      core   act_log()'s core INSERT failure
//      col    the cond_key COLUMN migration
//      idx    the cond_key INDEX migration
//      write  the cond_key UPDATE
//  Fixing only the reported one would repeat the pattern every audit in this
//  sequence has found, so all four go through the same keyed channel.
//
//  Keyed, NOT cleared: entering a workspace wipes nothing, it simply cannot see
//  what belongs to another. Switching back to A still shows A's error — there is
//  no global historical store, only per-workspace state.
function act_err_set($ch, $msg) {
    $GLOBALS['__act_err_' . $ch] = ['epoch' => db_epoch(), 'msg' => (string) $msg];
}
function act_err_get($ch) {
    $r = $GLOBALS['__act_err_' . $ch] ?? null;
    if (!is_array($r) || ($r['epoch'] ?? -1) !== db_epoch()) return '';
    return (string) $r['msg'];
}

// ===========================================================================
//  M3 CORRECTION #10 · V1 — ABSENT IS NOT FAILED
//
//  Correction #9 made observation read-only, which was right, and then answered a
//  THREE-valued question with a boolean. A structure that is merely absent —
//  nothing has tried to create it yet — was reported exactly like one that was
//  attempted and could not be made. A support screen could not tell "broken" from
//  "nobody has asked yet", which is the only question it exists to answer.
//
//      NOT_ATTEMPTED · FAILED · READY
//
//  FAILED is never inferred from absence. It requires RECORDED EVIDENCE of a real
//  attempt, so the attempt ledger — which used to be a static inside the retry
//  logic — is now observable state. An observer may READ it and may never write it.
// ===========================================================================
const ACT_OPT_NOT_ATTEMPTED = 'NOT_ATTEMPTED';
const ACT_OPT_FAILED        = 'FAILED';
const ACT_OPT_READY         = 'READY';

//  The ledger is per WORKSPACE EPOCH: a different company's database has not
//  attempted anything merely because this one failed.
function act_opt_rec($which) {
    $r = $GLOBALS['__act_opt_' . $which] ?? null;
    if (!is_array($r) || ($r['epoch'] ?? -1) !== db_epoch()) return ['epoch' => db_epoch(), 'tries' => 0, 'error' => ''];
    return $r;
}
//  Written ONLY from inside a real migration attempt. Never from an observer.
function act_opt_note($which, $tries, $error) {
    $GLOBALS['__act_opt_' . $which] = ['epoch' => db_epoch(), 'tries' => (int) $tries, 'error' => (string) $error];
}

function act_cond_column_status() {
    if (act_has_cond_column()) return ACT_OPT_READY;
    return act_opt_rec('col')['tries'] > 0 ? ACT_OPT_FAILED : ACT_OPT_NOT_ATTEMPTED;
}
//  The index's state is its OWN. A missing column means the index has not been
//  attempted — it does not mean the index failed, and it is not the index's place
//  to report on the column.
function act_cond_index_status() {
    if (act_has_cond_column() && act_has_cond_index()) return ACT_OPT_READY;
    return act_opt_rec('idx')['tries'] > 0 ? ACT_OPT_FAILED : ACT_OPT_NOT_ATTEMPTED;
}
//  §4 — a message describes an observed or recorded FACT, never a supposition.
function act_opt_message($which, $status) {
    if ($status === ACT_OPT_READY)         return 'Ready';
    if ($status === ACT_OPT_NOT_ATTEMPTED) return 'Not attempted';
    $e = act_opt_rec($which)['error'];
    return 'Failed: ' . ($e !== '' ? $e : 'no reason recorded');
}

function act_cond_column_ready() {
    static $okAt = -1;
    $e = db_epoch();
    if ($okAt === $e) return true;
    if (act_has_cond_column()) {                     // already there: no DDL, no budget
        $okAt = $e;
        act_err_set('col', ''); act_err_set('write', '');
        return true;
    }
    $rec = act_opt_rec('col');
    if ($rec['tries'] >= 3) return false;            // bounded, never an infinite loop
    try {
        act_opt_note('col', $rec['tries'] + 1, $rec['error']);       // the ATTEMPT is recorded
        ensure_column('activities', 'cond_key', "VARCHAR(160) DEFAULT ''");
    } catch (Throwable $ex) {
        act_opt_note('col', $rec['tries'] + 1, $ex->getMessage());
        act_err_set('col', 'cond_key column: ' . $ex->getMessage());
        @error_log('act_cond_column_ready: cond_key column unavailable — ' . $ex->getMessage());
        return false;
    }
    $okAt = $e;
    act_opt_note('col', 0, '');
    act_err_set('col', ''); act_err_set('write', '');
    return true;
}

function act_cond_index_ready() {
    static $okAt = -1;
    $e = db_epoch();
    if ($okAt === $e) return true;
    //  Asked CHEAPLY — the index has no business spending the COLUMN's budget.
    if (!act_has_cond_column()) return false;
    if (act_has_cond_index()) { $okAt = $e; act_err_set('idx', ''); return true; }
    $rec = act_opt_rec('idx');
    if ($rec['tries'] >= 3) return false;
    try {
        act_opt_note('idx', $rec['tries'] + 1, $rec['error']);
        act_index('activities', 'idx_act_cond', '(cond_key)');
    } catch (Throwable $ex) {
        act_opt_note('idx', $rec['tries'] + 1, $ex->getMessage());
        act_err_set('idx', 'cond_key index: ' . $ex->getMessage());
        @error_log('act_cond_index_ready: cond_key index unavailable — ' . $ex->getMessage());
        return false;                                 // the COLUMN is untouched by this
    }
    $okAt = $e; act_opt_note('idx', 0, '');
    act_err_set('idx', '');
    return true;
}

function act_migrate_optional() {
    $col = act_cond_column_ready();
    act_cond_index_ready();          //  attempted, and NEVER allowed to affect $col
    return $col;
}

function act_optional_ready()       { return act_cond_column_ready(); }
function act_optional_index_ready() { return act_cond_index_ready(); }
//  M3 CORRECTION #12 · X2 — each channel must be observable ON ITS OWN.
//  act_optional_error() folds two channels together, so a caller (or a test)
//  reading it cannot tell which one spoke. The four readers below are one
//  channel each; act_optional_error() keeps its combined meaning for the
//  screens that already call it.
function act_cond_column_error()    { return act_err_get('col'); }
function act_cond_write_error()     { return act_err_get('write'); }
function act_optional_error()       { return act_cond_column_error() ?: act_cond_write_error(); }
function act_optional_index_error() { return act_err_get('idx'); }

//  READ-ONLY (U1). It attempts nothing, spends no budget and increments no
//  counter — it reports the structures it can see and the attempts already on
//  the ledger.
function act_optional_state() {
    $c = act_cond_column_status(); $i = act_cond_index_status();
    return [
        'column'        => $c === ACT_OPT_READY,
        'index'         => $i === ACT_OPT_READY,
        'column_status' => $c,
        'index_status'  => $i,
        'column_error'  => $c === ACT_OPT_READY ? '' : act_opt_message('col', $c),
        'index_error'   => $i === ACT_OPT_READY ? '' : act_opt_message('idx', $i),
        'column_tries'  => act_opt_rec('col')['tries'],
        'index_tries'   => act_opt_rec('idx')['tries'],
    ];
}

//  The last CORE audit failure, so "the row was not written" can never be
//  indistinguishable from "nothing happened".
function act_last_error() { return act_err_get('core'); }

// CREATE INDEX IF NOT EXISTS is SQLite-only; MySQL throws on a duplicate, so
// the error is swallowed and only that one.
function act_index($table, $name, $cols) {
    try { db()->exec("CREATE INDEX $name ON $table $cols"); }
    catch (Throwable $e) {
        $m = strtolower($e->getMessage());
        if (strpos($m, 'exist') === false && strpos($m, 'duplicate') === false) throw $e;
    }
}

function act_missing_table(Throwable $e) {
    $m = $e->getMessage();
    return stripos($m, 'no such table') !== false || stripos($m, "doesn't exist") !== false;
}
function act_try($fn, $fallback = []) {
    try { return $fn(); } catch (Throwable $e) { if (!act_missing_table($e)) throw $e; return $fallback; }
}

// ---- Resolving the customer ------------------------------------------------
// Done ONCE, at write time. Given what an activity is about, work out which
// customer it belongs to so Customer 360 never has to join its way there.
function act_partner_for($entityKind, $entityId) {
    $id = (int)$entityId;
    if (!$id) return null;
    $q = function ($sql) use ($id) {
        try { $v = ops_val($sql, [$id]); return $v ? (int)$v : null; }
        catch (Throwable $e) { return null; }
    };
    switch ($entityKind) {
        case 'PARTNER':   return $id;
        case 'LEAD':      return $q("SELECT partner_id FROM leads WHERE id=?");
        case 'OPPORTUNITY': return $q("SELECT partner_id FROM opportunities WHERE id=?");
        case 'INQUIRY':   return $q("SELECT client_id FROM crm_inquiries WHERE id=?");
        case 'QUOTE':     return $q("SELECT client_id FROM quotations WHERE id=?");
        case 'CALL':      return $q("SELECT client_id FROM calls WHERE id=?");
        case 'JOB':
        case 'INVOICE':   return $q("SELECT c.client_id FROM jobs j LEFT JOIN calls c ON c.id=j.call_id WHERE j.id=?");
        case 'REPORT':    return $q("SELECT client_id FROM report_docs WHERE id=?");
        case 'COMPLAINT': return $q("SELECT partner_id FROM complaints WHERE id=?");
        case 'NCR':       return $q("SELECT partner_id FROM nonconformities WHERE id=?");
        case 'CAPA':      return null;
    }
    return null;
}

// ---- Writing ---------------------------------------------------------------
// The one function everything calls. Wrapped, because a timeline is a record of
// work and not a precondition for it: if this fails, the quote still goes out.
//
//   act_log('QUOTE', 42, 'EMAIL', 'Quotation Q-00042 sent to the customer',
//           ['auto' => 1, 'direction' => 'OUT']);
function act_log($entityKind, $entityId, $kind, $subject, array $opt = []) {
    try {
        act_migrate();
        if (!isset(ACT_ENTITIES[$entityKind])) $entityKind = '';
        if (!isset(ACT_KINDS[$kind])) $kind = 'NOTE';
        $partner = array_key_exists('partner_id', $opt)
            ? ($opt['partner_id'] ? (int)$opt['partner_id'] : null)
            : act_partner_for($entityKind, $entityId);
        $u = function_exists('current_user') ? current_user() : null;
        db()->prepare("INSERT INTO activities
            (kind,entity_kind,entity_id,partner_id,subject,body,direction,occurred_at,
             duration_mins,outcome,with_whom,owner,office_id,sbu,auto,created_by,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$kind, $entityKind, (int)$entityId ?: null, $partner,
                substr(trim((string)$subject), 0, 255),
                (string)($opt['body'] ?? ''),
                isset(ACT_DIRECTIONS[$opt['direction'] ?? '']) ? (string)($opt['direction'] ?? '') : '',
                (string)($opt['occurred_at'] ?? date('c')),
                (int)($opt['duration_mins'] ?? 0),
                substr(trim((string)($opt['outcome'] ?? '')), 0, 60),
                substr(trim((string)($opt['with_whom'] ?? '')), 0, 200),
                substr(trim((string)($opt['owner'] ?? ($u ? user_name($u) : ''))), 0, 150),
                ($opt['office_id'] ?? '') !== '' ? (int)$opt['office_id'] : (($u['home_office_id'] ?? null) ?: null),
                (string)($opt['sbu'] ?? ''),
                !empty($opt['auto']) ? 1 : 0,
                $u ? user_name($u) : (string)($opt['created_by'] ?? 'system'),
                date('c')]);
        $id = (int) db()->lastInsertId();
        //  S1 — THE CORE ROW IS ALREADY SAFE. Optional metadata is applied
        //  afterwards, against a column that may not exist, and its failure
        //  cannot reach back and undo the event that has just been recorded.
        $ck = substr(trim((string)($opt['cond_key'] ?? '')), 0, 160);
        if ($id > 0 && $ck !== '') act_set_cond_key($id, $ck);   // status ignored: the CORE row is what matters
        act_err_set('core', '');
        return $id;
    } catch (Throwable $e) {
        //  Still non-fatal — a timeline is a record of work, not a precondition
        //  for it — but no longer INVISIBLE. A core failure must never be
        //  indistinguishable from nothing having happened.
        act_err_set('core', $e->getMessage());
        @error_log('act_log: core audit row NOT written — ' . $e->getMessage());
        return 0;
    }
}

//  M3 CORRECTION #11 · W2 — THE WRITER ANSWERS THE SAME QUESTION AS THE OBSERVER
//
//  This returned a plain true/false, so "nothing has tried to create the column
//  yet" and "the write was attempted and failed" were the same answer. That is
//  V1's sentence — ABSENT IS NOT FAILED — on the writer side, which correction #10
//  left standing because it only fixed the instance that had been reported.
//
//  §8 lists five separate questions, so the answer distinguishes four outcomes
//  rather than collapsing them:
//
//      STORED         the UPDATE ran and succeeded
//      FAILED         the UPDATE ran and failed          (the write's own failure)
//      UNAVAILABLE    the column was attempted and cannot be made
//      NOT_ATTEMPTED  nothing was attempted at all
//
//  The return is a STATUS CONSTANT, not a boolean, and callers compare against
//  ACT_COND_STORED. A truthy 'FAILED' would be a trap for any future `if (...)`,
//  so the one production caller and every test are explicit.
const ACT_COND_STORED        = 'STORED';
const ACT_COND_FAILED        = 'FAILED';
const ACT_COND_UNAVAILABLE   = 'UNAVAILABLE';
const ACT_COND_NOT_ATTEMPTED = 'NOT_ATTEMPTED';

function act_set_cond_key($id, $key) {
    if ((int) $id <= 0 || (string) $key === '') return ACT_COND_NOT_ATTEMPTED;
    if (!act_cond_column_ready()) {                   // T2 — the COLUMN, not the index
        //  §8 — the MIGRATION's outcome, kept distinct from the WRITE's. The column
        //  having been attempted and refused is not the same as the update failing.
        return act_cond_column_status() === ACT_OPT_FAILED ? ACT_COND_UNAVAILABLE : ACT_COND_NOT_ATTEMPTED;
    }
    try {
        db()->prepare("UPDATE activities SET cond_key=? WHERE id=?")->execute([(string) $key, (int) $id]);
        //  M3 CORRECTION #12 · X1 — STORED MUST MEAN ACTUALLY PERSISTED.
        //
        //  An UPDATE that matches no row does not throw, so "PDO did not throw"
        //  reported success for a nonexistent id and for a row belonging to another
        //  workspace — nothing written, anywhere, reported as STORED.
        //
        //  rowCount() cannot answer this either, and would swap one lie for
        //  another. Measured on both engines:
        //
        //      MariaDB  same value → 0     different value → 1   no such row → 0
        //      SQLite   same value → 1                           no such row → 0
        //
        //  So on MariaDB a row that already holds the wanted value is
        //  indistinguishable from a row that does not exist, and an idempotent
        //  re-write would be reported as a failure. Reading the value back is the
        //  only engine-independent answer, and it is the requirement word for word:
        //  the value must be present on the intended record in the intended
        //  workspace. One extra SELECT, on the two cond_key call sites only —
        //  never on the seventy-four ordinary act_log() callers.
        $row = ops_one("SELECT cond_key FROM activities WHERE id=?", [(int) $id]);
        if (!$row) {
            act_err_set('write', 'cond_key: no such activity row in this workspace');
            return ACT_COND_FAILED;                   // nonexistent, or another workspace's
        }
        if ((string) $row['cond_key'] !== (string) $key) {
            act_err_set('write', 'cond_key: the value was not persisted as given');
            return ACT_COND_FAILED;                   // e.g. silently truncated by the column
        }
        act_err_set('write', '');
        return ACT_COND_STORED;
    } catch (Throwable $e) {
        act_err_set('write', 'cond_key: ' . $e->getMessage());
        return ACT_COND_FAILED;                       // the WRITE failed — the column is fine
    }
}

// ---- Effort ----------------------------------------------------------------
// How much working time went into something, summed from the minutes logged on
// its activities. A man-day is a standard eight-hour working day — the yardstick
// a manager reads "how much did it cost us to win this" in.
function act_effort(array $refs) {
    $mins = 0; $n = 0;
    foreach ($refs as $ref) {
        $k = $ref[0] ?? ''; $id = (int)($ref[1] ?? 0);
        if ($k === '' || !$id) continue;
        try {
            $r = ops_one("SELECT COALESCE(SUM(duration_mins),0) m, COUNT(*) n
                          FROM activities WHERE entity_kind=? AND entity_id=? AND duration_mins>0", [$k, $id]);
            $mins += (int)($r['m'] ?? 0); $n += (int)($r['n'] ?? 0);
        } catch (Throwable $e) { /* table not built yet */ }
    }
    return ['mins' => $mins, 'touches' => $n, 'mandays' => $mins / 480.0];
}
// "1.5 man-days (12h over 6 touches)" — empty when nothing has been timed, so a
// screen shows the line only once there is something honest to put in it.
function act_effort_label(array $eff) {
    $mins = (int)($eff['mins'] ?? 0);
    if ($mins <= 0) return '';
    $h = intdiv($mins, 60); $m = $mins % 60;
    $hm = $h ? ($h . 'h' . ($m ? ' ' . $m . 'm' : '')) : ($m . 'm');
    $n  = (int)($eff['touches'] ?? 0);
    return number_format($mins / 480.0, 1) . ' man-days (' . $hm . ' over ' . $n . ' touch' . ($n === 1 ? '' : 'es') . ')';
}

// ---- Reading ---------------------------------------------------------------
// Everything for one customer — the Customer 360 query. One index, no joins to
// find the customer, because that was resolved on the way in.
function act_for_partner($partnerId, $limit = 200, $kinds = []) {
    act_migrate();
    $w = 'partner_id = ?'; $a = [(int)$partnerId];
    if ($kinds) {
        $w .= ' AND kind IN (' . implode(',', array_fill(0, count($kinds), '?')) . ')';
        foreach ($kinds as $k) $a[] = $k;
    }
    return act_try(fn() => ops_all(
        "SELECT * FROM activities WHERE $w ORDER BY occurred_at DESC, id DESC LIMIT " . max(1, (int)$limit), $a));
}

// Everything about one record — the panel that goes on a quote, a deputation,
// a complaint.
function act_for_entity($entityKind, $entityId, $limit = 100) {
    act_migrate();
    return act_try(fn() => ops_all(
        "SELECT * FROM activities WHERE entity_kind=? AND entity_id=?
         ORDER BY occurred_at DESC, id DESC LIMIT " . max(1, (int)$limit),
        [$entityKind, (int)$entityId]));
}

// When did anything last happen with this customer? The question behind
// "customers not contacted in 30 days", which is the most-asked CRM report.
function act_last_touch($partnerId) {
    act_migrate();
    return (string)act_try(fn() => ops_val(
        "SELECT MAX(occurred_at) FROM activities WHERE partner_id=?", [(int)$partnerId]), '');
}

function act_silent_customers($days = 30, $limit = 100) {
    act_migrate();
    $cut = date('c', strtotime("-$days days"));
    return act_try(fn() => ops_all(
        "SELECT bp.id, bp.display_name, bp.legal_name, MAX(a.occurred_at) last_touch
         FROM business_partners bp
         LEFT JOIN activities a ON a.partner_id = bp.id
         WHERE bp.is_client = 1 AND bp.status = 'ACTIVE'
         GROUP BY bp.id, bp.display_name, bp.legal_name
         HAVING MAX(a.occurred_at) IS NULL OR MAX(a.occurred_at) < ?
         ORDER BY last_touch ASC LIMIT " . max(1, (int)$limit), [$cut]));
}

function act_counts($partnerId) {
    act_migrate();
    $out = [];
    foreach (act_try(fn() => ops_all(
        "SELECT kind, COUNT(*) n FROM activities WHERE partner_id=? GROUP BY kind", [(int)$partnerId])) as $r)
        $out[$r['kind']] = (int)$r['n'];
    return $out;
}

// Where an activity points, for the timeline's "open it" link.
function act_link($a) {
    $k = (string)$a['entity_kind'];
    if ($k === '' || empty($a['entity_id']) || !isset(ACT_ENTITIES[$k])) return '';
    return ACT_ENTITIES[$k][1] . (int)$a['entity_id'];
}
function act_entity_label($a) {
    $k = (string)$a['entity_kind'];
    $m = act_entities();
    return isset($m[$k]) ? $m[$k][0] : '';
}

// Module 40 — a read-only activity-timeline panel for any entity that already logs
// to the spine but had no timeline of its own (a complaint, an NCR, a report). It
// only reads act_for_entity() and echoes a panel — no write form, no new data, and
// only this entity's own history (nothing cross-entity or cross-office). Marks
// system vs person-typed, and names the actor.
function act_render_timeline($entityKind, $entityId, $title = 'History', $limit = 100) {
    $rows = act_for_entity($entityKind, (int)$entityId, (int)$limit);
    echo '<div class="panel"><h3 class="tab-sub" style="margin-top:0">' . e($title)
       . ' <span class="muted" style="font-weight:400;font-size:12px">(' . count($rows) . ')</span></h3>';
    if (!$rows) { echo '<p class="muted" style="margin:0">Nothing recorded yet.</p></div>'; return; }
    echo '<div class="dt-scroll"><table class="dt"><tbody>';
    foreach ($rows as $r) {
        echo '<tr><td class="muted" style="white-space:nowrap;width:104px">'
           . e(fdate(substr((string)$r['occurred_at'], 0, 10)))
           . '<br><span style="font-size:11px">' . e(substr((string)$r['occurred_at'], 11, 5)) . '</span></td>'
           . '<td><span class="pill ' . ($r['auto'] ? 'p-mut' : 'p-ok') . '" style="font-size:10px">'
           . e(ACT_KINDS[$r['kind']] ?? $r['kind']) . '</span> <strong>' . e($r['subject']) . '</strong>'
           . (trim((string)$r['body']) !== '' ? '<div class="muted" style="font-size:12.5px;white-space:pre-wrap">' . e($r['body']) . '</div>' : '')
           . (trim((string)($r['with_whom'] ?? '')) !== '' ? '<div class="muted" style="font-size:11.5px">with ' . e($r['with_whom']) . '</div>' : '')
           . '</td><td class="muted" style="font-size:11.5px;white-space:nowrap">'
           . e($r['created_by'] ?: ($r['owner'] ?? '')) . '</td></tr>';
    }
    echo '</tbody></table></div></div>';
}

// ---- The screen ------------------------------------------------------------
function act_can_view()  { return can('mod.clients.view') || can('mod.calls.view') || is_master_of(['clients','calls']); }
function act_can_write() { return can('mod.clients.edit') || can('mod.calls.edit') || is_master_of(['clients','calls']); }

// The register's columns, declared once. The 'sort' value is a SQL expression
// the register owns — it is the ONLY thing that can reach ORDER BY, which is
// what makes ?sort= in the address bar safe. 'optional' means hidden until
// somebody asks for it, so the default view stays readable.
function act_dt_columns() {
    return [
        'when' => ['label' => 'When', 'sort' => 'a.occurred_at', 'render' => fn($r) =>
            '<span style="white-space:nowrap">' . e(fdate(substr((string)$r['occurred_at'], 0, 10))) . '</span>'
            . '<br><span class="muted" style="font-size:12px">' . e(substr((string)$r['occurred_at'], 11, 5)) . '</span>'],
        'what' => ['label' => 'What happened', 'sort' => 'a.subject', 'render' => function ($r) {
            $h = '<span class="pill ' . ($r['auto'] ? 'p-mut' : 'p-ok') . '" style="font-size:11px">'
               . e(ACT_KINDS[$r['kind']] ?? $r['kind']) . '</span>';
            if ($r['direction']) $h .= ' <span class="muted" style="font-size:11px">'
                                     . e(ACT_DIRECTIONS[$r['direction']] ?? '') . '</span>';
            $h .= '<br>' . e($r['subject']);
            if (trim((string)$r['body']) !== '')
                $h .= '<br><span class="muted" style="font-size:12px;white-space:pre-wrap">'
                    . e(mb_substr((string)$r['body'], 0, 140)) . '</span>';
            return $h;
        }],
        'customer' => ['label' => 'Customer', 'sort' => 'COALESCE(bp.display_name, bp.legal_name)',
            'render' => fn($r) => $r['partner_id']
                ? '<a href="/activities?partner=' . (int)$r['partner_id'] . '">'
                  . e($r['display_name'] ?: $r['legal_name']) . '</a>'
                : '<span class="muted">—</span>'],
        'about' => ['label' => 'About', 'sort' => 'a.entity_kind',
            'render' => fn($r) => e(act_entity_label($r) ?: '—')],
        'who' => ['label' => 'Who', 'sort' => 'a.owner', 'render' => function ($r) {
            $h = e($r['owner'] ?: '—');
            if ($r['with_whom']) $h .= '<br><span class="muted" style="font-size:12px">with ' . e($r['with_whom']) . '</span>';
            return $h;
        }],
        'outcome' => ['label' => 'Outcome', 'sort' => 'a.outcome', 'optional' => true,
            'render' => fn($r) => e($r['outcome'] ?: '—')],
        'mins' => ['label' => 'Minutes', 'sort' => 'a.duration_mins', 'num' => true, 'optional' => true,
            'render' => fn($r) => (int)$r['duration_mins'] ?: '<span class="muted">—</span>'],
        'open' => ['label' => '', 'render' => fn($r) =>
            ($l = act_link($r)) ? '<a class="btn small" href="' . e($l) . '">Open →</a>' : ''],
    ];
}

function ops_activity($route, $method) {
    ops_require(act_can_view(), 'You cannot open the activity timeline.');
    act_migrate();

    if ($route === 'activity-add' && $method === 'POST') {
        ops_require(act_can_write(), 'You cannot record an activity.');
        $subject = trim((string)($_POST['subject'] ?? ''));
        if ($subject === '') { flash('Say what happened, in a line.', 'error'); redirect_back('/activities'); }
        // A person typing this is never an automatic entry, whatever is posted.
        act_log((string)($_POST['entity_kind'] ?? ''), (int)($_POST['entity_id'] ?? 0),
                (string)($_POST['kind'] ?? 'NOTE'), $subject, [
                    'body' => (string)($_POST['body'] ?? ''),
                    'direction' => (string)($_POST['direction'] ?? ''),
                    'occurred_at' => (string)($_POST['occurred_at'] ?? '') ?: date('c'),
                    'duration_mins' => (int)($_POST['duration_mins'] ?? 0),
                    'outcome' => (string)($_POST['outcome'] ?? ''),
                    'with_whom' => (string)($_POST['with_whom'] ?? ''),
                    'partner_id' => (int)($_POST['partner_id'] ?? 0) ?: null,
                    'auto' => 0,
                ]);
        flash('Recorded.');
        redirect_back('/activities');
    }

    // The register. It used to be "newest 300 and no more", which on a busy
    // installation quietly hid everything older without saying so. It pages now,
    // so the whole timeline is reachable and the count at the top is the truth.
    $kind = (string)($_GET['kind'] ?? '');
    $pid  = (int)($_GET['partner'] ?? 0);
    $cols = act_dt_columns();
    $dt   = dt_state('activities', $cols, ['default_sort' => 'when', 'default_dir' => 'desc', 'per' => 50]);

    $w = '1=1'; $a = [];
    if ($kind !== '' && isset(ACT_KINDS[$kind])) { $w .= ' AND a.kind=?'; $a[] = $kind; }
    if ($pid) { $w .= ' AND a.partner_id=?'; $a[] = $pid; }
    if (($_GET['manual'] ?? '') === '1') $w .= ' AND a.auto=0';
    if ($dt['q'] !== '') {
        $w .= ' AND (a.subject LIKE ? OR a.body LIKE ? OR a.with_whom LIKE ? OR a.outcome LIKE ?)';
        $like = '%' . $dt['q'] . '%';
        array_push($a, $like, $like, $like, $like);
    }
    $from = "FROM activities a LEFT JOIN business_partners bp ON bp.id = a.partner_id WHERE $w";

    if (wants_csv()) {
        // An export that only carried the page you were looking at would be a
        // trap. It carries every row the filters match — the same filters, not
        // the same page.
        $all = act_try(fn() => ops_all("SELECT a.*, bp.display_name, bp.legal_name $from
                                        ORDER BY a.occurred_at DESC, a.id DESC LIMIT 20000", $a));
        $csv = [['When','Kind','Subject','Customer','About','Direction','With','Outcome','Minutes','Owner','Recorded by system']];
        foreach ($all as $r)
            $csv[] = [substr((string)$r['occurred_at'],0,16), ACT_KINDS[$r['kind']] ?? $r['kind'], $r['subject'],
                      $r['display_name'] ?: $r['legal_name'], act_entity_label($r),
                      ACT_DIRECTIONS[$r['direction']] ?? '', $r['with_whom'], $r['outcome'],
                      (int)$r['duration_mins'], $r['owner'], $r['auto'] ? 'Yes' : 'No'];
        csv_download('activities-' . date('Y-m-d') . '.csv', $csv);
    }

    $total = (int)act_try(fn() => ops_val("SELECT COUNT(*) $from", $a), 0);
    $rows  = act_try(fn() => ops_all("SELECT a.*, bp.display_name, bp.legal_name $from"
                                     . dt_sql_tail($dt, $cols, 'a.occurred_at DESC, a.id DESC'), $a));

    view('ops/activities', ['rows' => $rows, 'total' => $total, 'dt' => $dt, 'cols' => $cols,
                            'kind' => $kind, 'pid' => $pid,
                            'silent' => act_silent_customers(30, 20),
                            'canWrite' => act_can_write()]);
    return true;
}
