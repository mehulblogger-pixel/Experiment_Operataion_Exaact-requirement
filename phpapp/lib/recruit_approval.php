<?php
// ============================================================================
//  EXAACT Recruitment — Configurable Approval matrix + SLA + reminders +
//  escalations (Phase 6). Additive and non-destructive.
//
//  Everything is DATA an administrator configures — never hardcoded:
//   • Approval RULES matched by entity (requisition / offer / salary), by
//     department / business unit / grade / position and by a value band
//     (e.g. offers above ₹X need a Director). The narrowest match wins.
//   • Each rule has a multi-LEVEL chain: approver role (or a named user), an
//     SLA (days), a reminder cadence and an escalation target.
//   • A runtime REQUEST + STEPS drive the chain; approve advances, reject
//     closes. On completion a callback updates the entity (offer/requisition).
//   • A cron TICK sends reminders and escalations when an SLA is breached.
//   • Every notification goes out through the platform mailer (ops_mail).
// ============================================================================

//  Phase 3 · M1 — HIRING_REQUEST joins the list. The engine was already
//  entity-agnostic (recruit_approval_requests carries entity + entity_id), so
//  this constant is the whole of what it needed to know. No table, no column.
const APPR_ENTITIES = [
    'HIRING_REQUEST' => 'Hiring Request',
    'REQUISITION'    => 'Requisition (SRF)',
    'OFFER'          => 'Offer',
    'SALARY'         => 'Salary structure',
];

function appr_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    if (!function_exists('ensure_column')) return;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS recruit_approval_rules (
            id $pk, code VARCHAR(40) DEFAULT '', name VARCHAR(160) DEFAULT '',
            entity VARCHAR(20) DEFAULT 'OFFER',
            applies_department VARCHAR(160) DEFAULT '', applies_sbu VARCHAR(120) DEFAULT '',
            applies_grade VARCHAR(120) DEFAULT '', applies_position VARCHAR(160) DEFAULT '',
            min_amount DECIMAL(14,2) DEFAULT 0, max_amount DECIMAL(14,2) DEFAULT 0,
            active INT DEFAULT 1, sort INT DEFAULT 0, created_at VARCHAR(30) DEFAULT '')");
        db()->exec("CREATE TABLE IF NOT EXISTS recruit_approval_levels (
            id $pk, rule_id INT, seq INT DEFAULT 0, label VARCHAR(120) DEFAULT '',
            approver_role VARCHAR(40) DEFAULT '', approver_user_id INT NULL,
            sla_days INT DEFAULT 2, reminder_days INT DEFAULT 1,
            escalate_role VARCHAR(40) DEFAULT '', escalate_user_id INT NULL)");
        db()->exec("CREATE TABLE IF NOT EXISTS recruit_approval_requests (
            id $pk, entity VARCHAR(20) DEFAULT '', entity_id INT DEFAULT 0,
            rule_id INT DEFAULT 0, rule_name VARCHAR(160) DEFAULT '', subject VARCHAR(240) DEFAULT '',
            amount DECIMAL(14,2) DEFAULT 0, status VARCHAR(20) DEFAULT 'PENDING',
            current_seq INT DEFAULT 0, requester VARCHAR(160) DEFAULT '',
            created_at VARCHAR(30) DEFAULT '', closed_at VARCHAR(30) DEFAULT '')");
        db()->exec("CREATE TABLE IF NOT EXISTS recruit_approval_steps (
            id $pk, request_id INT, seq INT DEFAULT 0, label VARCHAR(120) DEFAULT '',
            approver_role VARCHAR(40) DEFAULT '', approver_user_id INT NULL,
            escalate_role VARCHAR(40) DEFAULT '', escalate_user_id INT NULL,
            sla_due VARCHAR(30) DEFAULT '', reminder_at VARCHAR(30) DEFAULT '',
            status VARCHAR(20) DEFAULT 'PENDING', acted_by VARCHAR(160) DEFAULT '',
            acted_at VARCHAR(30) DEFAULT '', remarks VARCHAR(400) DEFAULT '',
            reminded_at VARCHAR(30) DEFAULT '', escalated INT DEFAULT 0)");
        // Phase 3 · M2 — three additive, nullable columns on the EXISTING rules
        // table. No new rule table: a policy is still one row here.
        //   applies_office_id  the branch dimension the matrix could not express
        //   effective_from/to  so a policy can be scheduled and retired rather
        //                      than only switched off
        // Priority is NOT a new column: `sort` already exists and the screen
        // already calls it "Match order".
        try { ensure_column('recruit_approval_rules', 'applies_office_id', 'INT NULL'); } catch (Throwable $e) {}
        try { ensure_column('recruit_approval_rules', 'effective_from', "VARCHAR(30) DEFAULT ''"); } catch (Throwable $e) {}
        try { ensure_column('recruit_approval_rules', 'effective_to', "VARCHAR(30) DEFAULT ''"); } catch (Throwable $e) {}
        // Phase 3 · M3 — THREE additive columns on the EXISTING steps table, and
        // no new table anywhere. They exist for one reason the audit proved:
        // the SLA clock has to START when the step becomes active (§7), while the
        // POLICY it is measured against must stay frozen at chain creation (§31).
        // That means the step has to carry its own policy, so activation can
        // compute a due date without re-reading a matrix that may have changed.
        //   sla_days / reminder_days  the frozen policy
        //   activated_at              when this step became the one being waited on
        try { ensure_column('recruit_approval_steps', 'sla_days', 'INT DEFAULT 0'); } catch (Throwable $e) {}
        try { ensure_column('recruit_approval_steps', 'reminder_days', 'INT DEFAULT 0'); } catch (Throwable $e) {}
        try { ensure_column('recruit_approval_steps', 'activated_at', "VARCHAR(30) DEFAULT ''"); } catch (Throwable $e) {}
        // M3 CORRECTION #3 · D1 — ONE additive, nullable column: the canonical
        // identity of the person who raised this chain, captured from the
        // authenticated session at appr_start(). The audit's own question ("can
        // the approval request safely retain a canonical source-user reference?")
        // is answered yes, and it is answered once for EVERY entity rather than
        // four times, one per table. Historical rows are NULL and fail closed.
        try { ensure_column('recruit_approval_requests', 'requester_id', 'INT NULL'); } catch (Throwable $e) {}
        // Phase 3 · M2 — DELEGATION. The one new table, and the audit says why:
        // nothing in the application can represent a standing, dated, scoped
        // transfer of approval authority. IDEMS has `report_approvals.delegated_to`
        // — a single nullable integer on one step of a different module's engine,
        // with no delegator, no dates, no scope and no revocation — which is a
        // per-step hand-off, not "while I am away, B acts for me".
        db()->exec("CREATE TABLE IF NOT EXISTS approval_delegations (
            id $pk, delegator_user_id INT, delegate_user_id INT,
            entity VARCHAR(20) DEFAULT '',
            office_id INT NULL,
            effective_from VARCHAR(30) DEFAULT '', effective_to VARCHAR(30) DEFAULT '',
            reason VARCHAR(240) DEFAULT '', active INT DEFAULT 1,
            created_by VARCHAR(160) DEFAULT '', created_at VARCHAR(30) DEFAULT '',
            revoked_by VARCHAR(160) DEFAULT '', revoked_at VARCHAR(30) DEFAULT '')");
        if (function_exists('act_index')) {
            act_index('recruit_approval_levels', 'idx_al_rule', '(rule_id)');
            act_index('recruit_approval_steps', 'idx_as_req', '(request_id)');
            act_index('recruit_approval_requests', 'idx_ar_ent', '(entity,entity_id)');
        }
    } catch (Throwable $e) { /* never break boot */ }
}

function _appr_now() { return function_exists('now_iso') ? now_iso() : date('c'); }
function _appr_actor() { return function_exists('user_name') && function_exists('current_user') ? user_name(current_user()) : 'system'; }

//  Phase 3 · M3 §9 — a WORKING-day clock, not a calendar one.
//
//  "Two days to approve" has never meant "48 hours, Sunday included". A two-day
//  SLA issued on Friday used to expire over the weekend and escalate on Sunday
//  morning to a manager who was not at work. The branch-aware working day
//  already exists (lib/schedule.php: Sundays plus that branch's public holidays)
//  so M3 reuses it rather than inventing a second calendar.
//
//  Where no branch can be established — offer, salary and requisition carry no
//  office, exactly as M2 established — the company-wide holidays apply and
//  nothing else changes. The 400-iteration guard is the same bound
//  next_working_day() already uses.
function appr_due_at($days, $officeId = 0, $fromTs = null) {
    $days = max(0, (int) $days);
    $ts = $fromTs === null ? time() : (int) $fromTs;
    if ($days === 0) return date('c', $ts);
    if (!function_exists('is_working_day')) return date('c', strtotime('+' . $days . ' days', $ts));
    $added = 0; $guard = 0;
    while ($added < $days && $guard++ < 400) {
        $ts += 86400;
        if (is_working_day(date('Y-m-d', $ts), (int) $officeId)) $added++;
    }
    return date('c', $ts);
}

// ===========================================================================
//  M3 CORRECTION #6 · J1 — WHAT MAKES AN AUDIT REFERENCE VALID
//
//  Correction #3 (D2) guarded the audit trail with
//      if (!array_key_exists($entity, ACT_ENTITIES)) return;   // "never dangling"
//  which asks whether the entity TYPE is one the timeline can link. But the
//  reason that guard most often fires under is ENTITY_UNRESOLVED — which means,
//  by definition, that the RECORD is gone. A supported type was written; an
//  openable target was not. Every row it produced for that reason pointed at
//  nothing.
//
//      A SUPPORTED TYPE  ≠  AN OPENABLE RECORD
//
//  That is the same sentence as G1, one layer in. So the five clauses live in
//  ONE function that every writer in this module calls, rather than four-fifths
//  of the rule being re-implemented per call site:
//
//    1 · the entity TYPE is supported by the timeline (ACT_ENTITIES)
//    2 · the entity ID is valid (> 0)
//    3 · the target RECORD exists
//    4 · the target belongs to THIS tenant — structural: appr_entity_record()
//        and appr_rule() read the live connection, so another workspace's row
//        simply does not resolve here
//    5 · it resolves through the EXISTING entity mechanism, not a private one
// ===========================================================================

//  The subjects this module is allowed to point an audit row at. APPROVAL_POLICY
//  is already registered on the spine by M2 and /recruit-approvals?id=<rule> is
//  already a real screen — no new audit system, no new route.
const APPR_AUDIT_SUBJECTS = ['HIRING_REQUEST', 'OFFER', 'SALARY', 'REQUISITION', 'APPROVAL_POLICY'];

function appr_audit_ref_resolve($kind, $id) {
    $kind = strtoupper(trim((string) $kind)); $id = (int) $id;
    if ($id <= 0) return null;
    if ($kind === 'APPROVAL_POLICY') {
        //  A resolution that ERRORS is a resolution that FAILED (C5.6's rule).
        try { $r = function_exists('appr_rule') ? appr_rule($id) : null; }
        catch (Throwable $e) { return null; }
        return $r ?: null;
    }
    return appr_entity_record($kind, $id);          // G1's single resolution point
}

function appr_audit_ref_ok($kind, $id) {
    $kind = strtoupper(trim((string) $kind)); $id = (int) $id;
    if ($kind === '' || $id <= 0) return false;                                    // 2
    if (!defined('ACT_ENTITIES') || !array_key_exists($kind, ACT_ENTITIES)) return false;   // 1
    if (!in_array($kind, APPR_AUDIT_SUBJECTS, true)) return false;                 // this module's own subjects
    return appr_audit_ref_resolve($kind, $id) !== null;                            // 3 · 4 · 5
}

//  The smallest EXISTING subject the event can safely be filed under:
//    1 · the source record itself, when it is genuinely openable here;
//    2 · else the approval policy that governs this chain, when that is;
//    3 · else nothing. A row nobody can follow is worse than no row — and a
//        blank entity_kind with a live entity_id is the orphan D2 named.
//  Returns [kind, id, isSourceRecord].
function appr_audit_subject($req) {
    $entity = strtoupper(trim((string) ($req['entity'] ?? '')));
    $eid    = (int) ($req['entity_id'] ?? 0);
    if (appr_audit_ref_ok($entity, $eid)) return [$entity, $eid, true];
    $ruleId = (int) ($req['rule_id'] ?? 0);
    if (appr_audit_ref_ok('APPROVAL_POLICY', $ruleId)) return ['APPROVAL_POLICY', $ruleId, false];
    return ['', 0, false];
}

//  Whether the SOURCE record is genuinely gone, as opposed to merely not being
//  linkable on the timeline. Only the first may be described as unavailable —
//  saying so about a live offer would be the misleading event §2 forbids.
function appr_audit_source_gone($req) {
    $entity = strtoupper(trim((string) ($req['entity'] ?? '')));
    if ($entity === '' || !array_key_exists($entity, APPR_ENTITIES)) return true;
    return appr_entity_record($entity, (int) ($req['entity_id'] ?? 0)) === null;
}

// ===========================================================================
//  M3 CORRECTION #7 — A PERMANENT CONDITION IS A STATE, NOT AN EVENT
//
//  Correction #6 gave an unresolvable approval a subject that opens. It also
//  gave it the ability to repeat: ten identical refusals on a dead offer wrote
//  ten rows where they had previously written none (K1), and the scheduler has
//  written one row a day, for ever, over a chain whose record is gone (H2).
//
//  Those are the same rule missing on two paths, so this is ONE rule in ONE
//  place rather than two patches:
//
//      OBSERVATION -> CLASSIFY -> STABLE IDENTITY -> ALREADY RECORDED? -> RECORD
//
//  What it is NOT: a blanket "suppress repeats". A transient failure may
//  legitimately recur, a different reason is a different condition, and a later
//  step of the same chain is a new subject. Only the identical permanent
//  condition, on the identical subject, is silent the second time.
// ===========================================================================

//  §2 — PERMANENT means: re-asking cannot change the answer without the
//  underlying DATA changing, and when the data does change the REASON changes
//  with it, which produces a different key and therefore a new event.
//
//    ENTITY_UNRESOLVED    the source record does not exist here
//    TENANT_MISMATCH      the subject names a row that is not in this workspace
//    IDENTITY_UNRESOLVED  a legacy chain that carries no canonical raiser id
//
//  Everything else is TRANSIENT and is deliberately NOT suppressed:
//  RECIPIENT_INACTIVE (a person is reactivated), RECIPIENT_UNLICENSED (a module
//  is bought), RECIPIENT_OUT_OF_SCOPE / SEGREGATION_BLOCKED (configuration
//  changes), NO_EMAIL (an address is added), PROVIDER_FAILURE (explicitly
//  retryable). The brief's warning applies here: a lookup that failed once is
//  not permanent merely because it failed.
const APPR_PERMANENT_REASONS = ['ENTITY_UNRESOLVED', 'TENANT_MISMATCH', 'IDENTITY_UNRESOLVED'];

function appr_condition_kind($reason) {
    return in_array(strtoupper(trim((string) $reason)), APPR_PERMANENT_REASONS, true)
        ? 'PERMANENT' : 'TRANSIENT';
}

//  §3 — the stable identity of a permanent condition. Deliberately NOT rule_id:
//  two dead offers governed by one policy are two conditions, and a chain with
//  no rule at all still has an identity. Deliberately no timestamp: that would
//  make every observation unique, which is the defect. Tenant is structural —
//  one database per workspace — so it is the connection, not a column.
function appr_condition_key($event, $req, $step, $reason) {
    if (appr_condition_kind($reason) !== 'PERMANENT') return '';      // transient: no suppression
    $entity = strtoupper(trim((string) ($req['entity'] ?? '')));
    $eid    = (int) ($req['entity_id'] ?? 0);
    $rq     = (int) ($req['id'] ?? 0);
    $st     = is_array($step) ? (int) ($step['id'] ?? 0) : 0;
    return 'PC|' . strtoupper((string) $event) . '|' . $entity . '|' . $eid
         . '|R' . $rq . '|S' . $st . '|' . strtoupper(trim((string) $reason));
}

//  M3 CORRECTION #13 · Y2 — WHAT THE CALLER DOES WITH THE ANSWER.
//
//  #12 made act_set_cond_key() truthful. It did not make anyone listen: the sole
//  production caller discarded the status, so the whole four-valued contract was
//  observable only from tests. This is the missing sibling — not another channel
//  or another observer, but the caller itself.
//
//  Why it is a live defect and not a tidiness point: appr_condition_seen() asks
//  the DATABASE whether the marker is there. If the marker did not persist and
//  nobody is told, the next identical permanent condition finds no marker,
//  concludes it has never been seen, and records the event again — the repetition
//  #7 was raised to stop, silently resumed.
//
//  THE FAIL-SAFE, stated exactly rather than invented (§3):
//
//    marker STORED      → suppression is ARMED. Existing behaviour, unchanged:
//                         the identical condition will be suppressed next time.
//    marker NOT stored  → suppression is NOT ARMED, and the application says so.
//                         The event row still stands (S1 — an audit event is
//                         never sacrificed to its own metadata), but nothing
//                         anywhere claims the marker was written.
//
//  Fail-open is the only safe choice INSIDE THE EXISTING ARCHITECTURE, and the
//  reasoning is recorded so it is not mistaken for an oversight:
//
//    · Failing CLOSED — suppressing the repeat anyway — would mean suppressing on
//      the strength of a marker that does not exist. That is precisely "silently
//      behaving as though the condition was recorded", which §3 forbids.
//    · Suppressing from a second store — a cache, a session, an in-memory set —
//      would be a second deduplication mechanism, which §3 forbids.
//    · So the repeat is ALLOWED, and it is ATTRIBUTABLE: the caller returns
//      APPR_COND_UNARMED and the reason goes to the error log. A duplicate
//      timeline entry is a visible, correctable annoyance; a suppressed event
//      that was never recorded is lost evidence. The audit trail keeps the
//      louder failure.
//
//  No approval decision, authority or notification behaviour changes here.
//  M3 CORRECTION #14 · Z2 — EVERY STATUS MUST DESCRIBE WHAT ACTUALLY HAPPENED.
//
//  #13 defined UNARMED as "row written, marker not persisted" and then returned
//  it when the core INSERT itself had failed and NO row existed. Half the
//  definition was false, and the diagnostic built from it ended with the words
//  "the event itself was recorded" in the one case where it had not been. A
//  status is a claim about the world; it may not be a guess.
const APPR_COND_NONE          = 'NONE';           // E · no permanent condition — nothing to suppress
const APPR_COND_NO_SUBJECT    = 'NO_SUBJECT';     // E · nothing openable remains — no row written
const APPR_COND_SUPPRESSED    = 'SUPPRESSED';     // D · already recorded and armed — deliberately silent
const APPR_COND_RECORDED      = 'RECORDED';       // A · event written AND marker persisted — ARMED
const APPR_COND_UNARMED       = 'UNARMED';        // B · event written, marker NOT persisted — NOT armed
const APPR_COND_NOT_RECORDED  = 'NOT_RECORDED';   // C · the event itself was never written
const APPR_COND_PENDING_RETRY = 'PENDING_RETRY';  // B-4 · already unarmed; retried, still not armed, NO new row
const APPR_COND_RECOVERED     = 'RECOVERED';      // B-4 · the retry succeeded — the condition is armed at last
const APPR_COND_TERMINAL      = 'TERMINAL';       // #15 · PART C · resolved as unrecoverable — no longer an ACTIVE fault

//  M3 CORRECTION #14 · Z3 — THE BOUNDED FAILURE POLICY, stated once and in full.
//
//  #13 let a permanently unwritable marker write one audit row PER SCHEDULER TICK,
//  for ever. That is H2 under a different name. The bound cannot come from the
//  marker — the marker is the thing that does not work — so it comes from the one
//  fact that does persist: the event row itself.
//
//  Each condition event carries a FINGERPRINT of its condition in `body`, a
//  base-schema column written by the SAME INSERT that writes the event. It is not
//  a second suppression store and it is never treated as a marker: it is how the
//  application recognises the row it already wrote when the marker is missing.
//
//    FIRST failure     · the event is written once, the marker is attempted and
//                        fails, the row carries the fingerprint, the caller gets
//                        UNARMED, and the diagnostic is written ONCE.
//    LATER ticks       · the condition is recognised by its fingerprint. NO new
//                        event, NO new diagnostic. The marker is retried on the
//                        row that already exists. Caller gets PENDING_RETRY.
//    RETRY STOPS       · when it succeeds, or when the condition stops occurring.
//                        Retrying costs one UPDATE and writes nothing, so it is
//                        bounded in rows and in log lines, which is what ran away.
//    INTERVENTION      · while unarmed the condition is counted by
//                        appr_cond_unarmed_count() and surfaced on the approval
//                        summary the Recruitment Command Centre already renders.
//    NEVER PRETENDS    · suppression is never claimed. PENDING_RETRY suppresses
//                        the extra ROW on the strength of a row that genuinely
//                        exists, not on the strength of a marker that does not.
//    RECOVERY          · the moment the marker becomes writable the retry arms the
//                        existing row, the caller gets RECOVERED, the count falls
//                        to zero and ordinary suppression takes over unaided.
//
//  Nothing here changes an approval decision, an authority, a lifecycle state or
//  who is notified.
function appr_cond_fingerprint($key) {
    return (string) $key === '' ? '' : 'PCX|' . substr(sha1((string) $key), 0, 32);
}

// ===========================================================================
//  M3 CORRECTION #15 — THE CONDITION LEDGER
//
//  #14's adversarial audit found three faults that share one cause: the only
//  record of a failed condition was a row in `activities`, recognised by scanning
//  an unindexed column.
//
//    C-1  recovery lived inside the two condition writers, so it only ran if the
//         SAME condition happened again. Repair the fault, let the chain be
//         resolved, and the warning stayed on the dashboard for ever.
//    C-2  NOT_RECORDED writes no row, so there was nothing to recognise and the
//         diagnostic repeated on every tick.
//    C-4  recognising a row meant scanning `body`, and counting them for the
//         dashboard meant a LIKE over the whole spine on every page load.
//
//  All three need the same thing: a small, durable, per-workspace note of which
//  conditions are unresolved — one that does NOT live in `activities`, because
//  the case that breaks `activities` is exactly the case that has to be recorded.
//
//  REUSE, not BUILD. `settings` already is that store: one row per key in the
//  tenant's own database, PRIMARY KEY on skey, read through settings_cache() so
//  the dashboard pays one cached read and no scan at all. It is not a second
//  suppression store and never decides suppression — cond_key on the spine still
//  does that. It records only what is unresolved and what has already been said.
//
//  Entry shape, keyed by condition fingerprint:
//      k      the condition key, so the marker can actually be re-armed later
//      row    the activity row to arm, or 0 when none was ever written
//      st     UNARMED | NOT_RECORDED | TERMINAL
//      why    the reason last observed, so a CHANGED reason can speak again
//      maxid  MAX(activities.id) when a NOT_RECORDED was recorded — see reconcile
//      at     when it was first recorded
// ===========================================================================
const APPR_COND_LEDGER_KEY = 'appr_cond_ledger';
const APPR_COND_LEDGER_MAX = 200;       // svalue is bounded; the ledger cannot grow for ever
const APPR_COND_RECON_MAX  = 50;        // candidates examined per scheduler run

function appr_cond_ledger() {
    if (!function_exists('setting_get')) return [];
    $raw = (string) setting_get(APPR_COND_LEDGER_KEY, '');
    if ($raw === '') return [];
    $l = json_decode($raw, true);
    return is_array($l) ? $l : [];
}
function appr_cond_ledger_save($l) {
    if (!function_exists('setting_set')) return;
    if (count($l) > APPR_COND_LEDGER_MAX) $l = array_slice($l, -APPR_COND_LEDGER_MAX, null, true);
    try { setting_set(APPR_COND_LEDGER_KEY, json_encode($l)); } catch (Throwable $e) { /* never fatal */ }
}
function appr_cond_ledger_put($fp, array $rec) {
    if ($fp === '') return;
    $l = appr_cond_ledger(); $l[$fp] = $rec + ['at' => date('c')]; appr_cond_ledger_save($l);
}
function appr_cond_ledger_drop($fp) {
    if ($fp === '') return;
    $l = appr_cond_ledger();
    if (array_key_exists($fp, $l)) { unset($l[$fp]); appr_cond_ledger_save($l); }
}

//  Whether the spine can be asked about markers at all. On a host where the
//  optional column could never be created, asking would throw.
function appr_cond_col_ready() {
    return function_exists('act_has_cond_column') && act_has_cond_column();
}

//  The earliest event already written for this condition that carries NO marker.
//  C-4 — this used to scan `body`, an unindexed column on a table that only grows.
//  The ledger already knows the row, so the answer is a cached read and the retry
//  becomes a PRIMARY KEY lookup. The fingerprint is still written on the row (it
//  is that row's own durable record, and removing it is C-3, not this correction)
//  — it is simply no longer SEARCHED for.
function appr_cond_unarmed_row($key) {
    $fp = appr_cond_fingerprint($key);
    if ($fp === '') return null;
    $rec = appr_cond_ledger()[$fp] ?? null;
    if (!is_array($rec) || ($rec['st'] ?? '') !== APPR_COND_UNARMED) return null;
    $id = (int) ($rec['row'] ?? 0);
    return $id > 0 ? ['id' => $id] : null;
}

//  How many distinct conditions are currently recorded but NOT armed. This is the
//  business-visible number: each one is a suppression that cannot be trusted.
//  C-4 — the dashboard's number. This was a LIKE over the whole spine on every
//  page load; it is now a count over an already-cached settings value, and it
//  touches `activities` not at all. TERMINAL entries are excluded: a condition
//  that has been resolved as unrecoverable is not an active fault (PART C/H).
function appr_cond_unarmed_count() {
    $n = 0;
    foreach (appr_cond_ledger() as $rec) {
        $st = is_array($rec) ? (string) ($rec['st'] ?? '') : '';
        if ($st === APPR_COND_UNARMED || $st === APPR_COND_NOT_RECORDED) $n++;
    }
    return $n;
}

//  B-5 — a BOUNDED diagnostic. The unbounded stream came from writing the same
//  line on every tick; it is bounded structurally, because the only states that
//  speak are the ones that happen once per condition: first detection, the event
//  that could not be written, and recovery. PENDING_RETRY says nothing at all.
//  The text is built FROM the status, never from an assumption about it.
function appr_cond_note($condKey, $status, $detail = '') {
    $fp = appr_cond_fingerprint($condKey);
    //  M3 CORRECTION #15 · PART E/F — BOUNDED BY STATE, NOT BY LUCK.
    //
    //  #14 bounded the UNARMED diagnostic structurally: only one row is ever
    //  written, so the line could only be said once. NOT_RECORDED writes no row,
    //  so there was nothing to bound it and it repeated on every tick — 30 ticks,
    //  30 identical lines, measured.
    //
    //  The ledger now holds the fact "this condition has already been observed in
    //  this state", in `settings`, which does NOT depend on the activities INSERT
    //  that is failing. A line is written on FIRST detection, again if the failure
    //  REASON materially changes (a different fault is a different condition, not
    //  a repeat), and once on recovery. Never otherwise.
    if ($fp !== '' && $status !== APPR_COND_RECOVERED) {
        $seen = appr_cond_ledger()[$fp] ?? null;
        if (is_array($seen) && (string) ($seen['st'] ?? '') === (string) $status
            && (string) ($seen['why'] ?? '') === (string) $detail) return;   // already said
    }
    $msg = '';
    if ($status === APPR_COND_UNARMED) {
        $msg = 'the event WAS recorded but its suppression marker was not; '
             . 'this condition will not be suppressed until the marker can be written';
    } elseif ($status === APPR_COND_NOT_RECORDED) {
        $msg = 'the event itself was NOT recorded, so nothing about this condition '
             . 'has been written anywhere';
    } elseif ($status === APPR_COND_TERMINAL) {
        $msg = 'this condition is closed as unrecoverable; it is no longer reported as an '
             . 'active fault and nothing claims its marker was ever written';
    } elseif ($status === APPR_COND_RECOVERED) {
        $msg = 'the suppression marker was written on the existing event; '
             . 'ordinary suppression has resumed';
    } else {
        return;                                   // PENDING_RETRY and the rest are silent
    }
    @error_log('appr condition ' . $fp . ' [' . $status . ']: ' . $msg
             . ($detail !== '' ? ' — ' . $detail : ''));
}

//  PART B — RECOVERY THAT DOES NOT NEED THE CONDITION TO COME BACK.
//
//  #14 put the retry inside the two condition writers, so it could only run when
//  the same condition fired again. The conditions most likely to be left unarmed
//  — an orphaned chain, a request that has since been decided — are precisely the
//  ones that never fire again, so the warning became permanent.
//
//  Reconciliation is therefore a pass of its own, and its home is the mechanism
//  that already exists for exactly this: appr_tick(), the approval scheduler that
//  cron.php runs per workspace and that is already gated on the hiring module. No
//  new job, no new entry point, no new datastore.
//
//  Per candidate, in order, and each one independently (PART J):
//    1 the activity row still exists            → else TERMINAL, it cannot be armed
//    2 the workspace is this one                → structural: one database per tenant
//    3 the marker is genuinely still absent     → else it recovered already
//    4 marker storage is available now          → else leave it, say nothing
//    5 arm it, and READ IT BACK (#12's rule)    → only act_set_cond_key may decide
//    6 the ledger is updated truthfully
//    7 the warning clears only after 5 succeeded
//
//  Nothing here manufactures a marker, resurrects a decision, touches approval
//  lifecycle, or reaches outside the connected database.
function appr_cond_reconcile($limit = APPR_COND_RECON_MAX) {
    $out = ['examined' => 0, 'recovered' => 0, 'terminal' => 0, 'still' => 0];
    $l = appr_cond_ledger();
    if (!$l) return $out;
    $maxNow = 0;
    try { $maxNow = (int) ops_val("SELECT MAX(id) FROM activities"); } catch (Throwable $e) { $maxNow = 0; }
    $dirty = false;
    foreach ($l as $fp => $rec) {
        if ($out['examined'] >= (int) $limit) break;
        //  PART J case 3 — a malformed entry is dropped, and takes nobody with it.
        if (!is_array($rec)) { unset($l[$fp]); $dirty = true; continue; }
        $st = (string) ($rec['st'] ?? '');
        if ($st !== APPR_COND_UNARMED && $st !== APPR_COND_NOT_RECORDED) continue;
        $out['examined']++;
        try {
            if ($st === APPR_COND_NOT_RECORDED) {
                //  No row was ever written, and inventing one would be manufacturing
                //  history. The only honest question is whether the spine has become
                //  writable since — answered READ-ONLY, by whether anything at all has
                //  been written after the failure. MAX(id) is a primary-key read.
                if ($maxNow > (int) ($rec['maxid'] ?? 0)) {
                    $l[$fp] = ['st' => APPR_COND_TERMINAL, 'k' => (string) ($rec['k'] ?? ''), 'row' => 0,
                               'why' => 'the spine is writable again; the original event was never '
                                      . 'written and cannot be reconstructed', 'at' => date('c')];
                    $dirty = true; $out['terminal']++;
                    appr_cond_note($rec['k'] ?? '', APPR_COND_TERMINAL, (string) $l[$fp]['why']);
                } else { $out['still']++; }
                continue;
            }
            //  UNARMED
            $key = (string) ($rec['k'] ?? '');
            $id  = (int) ($rec['row'] ?? 0);
            if ($key === '' || $id <= 0) {
                $l[$fp] = ['st' => APPR_COND_TERMINAL, 'k' => $key, 'row' => 0,
                           'why' => 'the condition can no longer be identified', 'at' => date('c')];
                $dirty = true; $out['terminal']++; continue;
            }
            $row = ops_one("SELECT id, cond_key FROM activities WHERE id=?", [$id]);   // PRIMARY KEY
            if (!$row) {                                   // PART C — its source is gone
                $l[$fp] = ['st' => APPR_COND_TERMINAL, 'k' => $key, 'row' => 0,
                           'why' => 'the event it belonged to no longer exists, so its marker '
                                  . 'cannot be re-armed', 'at' => date('c')];
                $dirty = true; $out['terminal']++;
                appr_cond_note($key, APPR_COND_TERMINAL, (string) $l[$fp]['why']);
                continue;
            }
            if ((string) $row['cond_key'] === $key) {      // it recovered by other means
                unset($l[$fp]); $dirty = true; $out['recovered']++; continue;
            }
            if (!appr_cond_col_ready()) { $out['still']++; continue; }   // say nothing, change nothing
            //  Only act_set_cond_key() may decide this. It reads the value back
            //  (#12 · X1), so STORED here means the marker is genuinely on the row.
            if (act_set_cond_key($id, $key) === ACT_COND_STORED) {
                unset($l[$fp]); $dirty = true; $out['recovered']++;
                appr_cond_note($key, APPR_COND_RECOVERED, 'recovered by reconciliation');
            } else {
                $out['still']++;                            // truthfully unresolved, silently
            }
        } catch (Throwable $e) {
            //  PART J case 2 — one candidate's failure changes nothing for the rest.
            $out['still']++;
        }
    }
    if ($dirty) appr_cond_ledger_save($l);
    return $out;
}

//  Z3 — the decision taken BEFORE anything is written: write, retry, or stay quiet.
function appr_cond_gate($condKey) {
    if ((string) $condKey === '') return 'WRITE';            // no condition: ordinary event
    if (appr_condition_seen($condKey))  return 'SUPPRESS';   // armed — the existing behaviour
    return appr_cond_unarmed_row($condKey) ? 'RETRY' : 'WRITE';
}

//  Z3 — the retry. It writes NO new event; it arms the one that already exists.
function appr_cond_retry($condKey) {
    $row = appr_cond_unarmed_row($condKey);
    if (!$row) return APPR_COND_UNARMED;
    $st = function_exists('act_set_cond_key') ? act_set_cond_key((int) $row['id'], $condKey) : ACT_COND_FAILED;
    if ($st === ACT_COND_STORED) {
        appr_cond_ledger_drop(appr_cond_fingerprint($condKey));
        appr_cond_note($condKey, APPR_COND_RECOVERED); return APPR_COND_RECOVERED;
    }
    return APPR_COND_PENDING_RETRY;                          // bounded: no row, no log line
}

//  Reads the status act_log() left behind and turns it into the caller's own
//  outcome. It deliberately does NOT collapse to a boolean: a truthy 'FAILED'
//  was the trap #11 named, and "did it store" and "is suppression armed" are two
//  different questions that happen to share an answer today.
function appr_cond_outcome($condKey) {
    if ((string) $condKey === '') return APPR_COND_NONE;
    $row = function_exists('act_last_cond_row')    ? act_last_cond_row()    : 0;
    $st  = function_exists('act_last_cond_status') ? act_last_cond_status() : ACT_COND_NOT_ATTEMPTED;
    //  Z2 · case C — no row exists. Saying "UNARMED" here was #13's falsehood.
    $fp  = appr_cond_fingerprint($condKey);
    if ($row <= 0) {
        //  #15 · PART E — the fact is recorded where it does NOT depend on the
        //  INSERT that just failed. maxid is the read-only evidence reconciliation
        //  will use later to tell "still broken" from "writable again".
        $why = function_exists('act_last_error') ? act_last_error() : '';
        appr_cond_note($condKey, APPR_COND_NOT_RECORDED, $why);      // bounded by the ledger
        $max = 0; try { $max = (int) ops_val("SELECT MAX(id) FROM activities"); } catch (Throwable $e) {}
        appr_cond_ledger_put($fp, ['st' => APPR_COND_NOT_RECORDED, 'k' => (string) $condKey,
                                   'row' => 0, 'why' => $why, 'maxid' => $max]);
        return APPR_COND_NOT_RECORDED;
    }
    if ($st === ACT_COND_STORED) { appr_cond_ledger_drop($fp); return APPR_COND_RECORDED; }   // case A
    appr_cond_note($condKey, APPR_COND_UNARMED, $st);                // case B — once per condition
    appr_cond_ledger_put($fp, ['st' => APPR_COND_UNARMED, 'k' => (string) $condKey,
                               'row' => (int) $row, 'why' => (string) $st]);
    return APPR_COND_UNARMED;
}

//  Has this exact state already been recorded? One exact-match question against
//  the EXISTING spine — no second event engine, and no parsing of display prose.
function appr_condition_seen($key) {
    if ($key === '') return false;
    try { return (bool) ops_one("SELECT id FROM activities WHERE cond_key=?", [$key]); }
    catch (Throwable $e) { return false; }   // a check that cannot run must never
                                             // silence a first observation
}

//  §24 — every SLA event goes on the EXISTING activity spine. There is no second
//  audit system: act_log() is where M1 and M2 already put approval history. The
//  entity and its id are named in the SUBJECT as well as the entity column, so an
//  event stays readable for the entities that are not registered on the timeline
//  (offer and salary — see the known limitations).
//  Y2 — returns its own outcome so the branch taken is observable. Every existing
//  caller invokes this as a statement, so the added return changes nothing for them.
function appr_audit_sla($req, $step, $what, $detail = '', $event = 'SLA_EVENT') {
    if (!function_exists('act_log') || !$req) return APPR_COND_NO_SUBJECT;
    //  §5 — the permanent condition on this path is "the source record is gone".
    //  A chain whose record still exists is untouched: genuine reminders and
    //  escalations for genuinely pending approvals keep working exactly as before.
    $condKey = appr_audit_source_gone($req)
        ? appr_condition_key($event, $req, $step, 'ENTITY_UNRESOLVED') : '';
    //  Z3 — one gate for both writers: write / retry the existing row / stay quiet.
    $gate = appr_cond_gate($condKey);
    if ($gate === 'SUPPRESS') return APPR_COND_SUPPRESSED;
    if ($gate === 'RETRY')    return appr_cond_retry($condKey);     // bounded: no second row
    $entity = strtoupper((string) ($req['entity'] ?? ''));
    $label  = (defined('APPR_ENTITIES') && isset(APPR_ENTITIES[$entity])) ? APPR_ENTITIES[$entity] : $entity;
    //  J1 — this writer had NO check at all, not even D2's type check, so an
    //  offer or salary event reached act_log(), which blanks an unsupported kind
    //  and keeps the id: a row with entity_kind='' and a live entity_id. That is
    //  precisely the orphan D2 was raised about, produced by the audit path D2
    //  did not touch. One rule, both writers.
    [$kind, $id, $isSource] = appr_audit_subject($req);
    if ($kind === '') return APPR_COND_NO_SUBJECT;
    $subject = $what . ' — ' . $label . ' #' . (int) ($req['entity_id'] ?? 0)
        . ' · level ' . (int) ($step['seq'] ?? 0)
        . (trim((string) ($step['label'] ?? '')) !== '' ? ' (' . $step['label'] . ')' : '')
        . ($detail !== '' ? ' — ' . $detail : '')
        . ((!$isSource && appr_audit_source_gone($req)) ? ' — source record unavailable' : '');
    //  Z3 — the fingerprint rides in `body`, a base-schema column written by the
    //  SAME INSERT as the event, so the row stays recognisable when the marker
    //  cannot be written. It is never read as a marker.
    act_log($kind, $id, 'SYSTEM', $subject,
        ['auto' => 1, 'cond_key' => $condKey, 'body' => appr_cond_fingerprint($condKey)]);
    return appr_cond_outcome($condKey);          // Y2 — the status is consumed, not dropped
}

// ---- Rules & levels (config) -----------------------------------------------
function appr_rules($entity = null, $activeOnly = true) {
    appr_migrate();
    $w = []; $a = [];
    if ($activeOnly) $w[] = 'active=1';
    if ($entity) { $w[] = 'entity=?'; $a[] = $entity; }
    $wc = $w ? 'WHERE ' . implode(' AND ', $w) : '';
    return ops_all("SELECT * FROM recruit_approval_rules $wc ORDER BY entity, sort, id", $a);
}
function appr_rule($id) { appr_migrate(); return ops_one("SELECT * FROM recruit_approval_rules WHERE id=?", [(int)$id]) ?: null; }
function appr_levels($ruleId) { appr_migrate(); return ops_all("SELECT * FROM recruit_approval_levels WHERE rule_id=? ORDER BY seq, id", [(int)$ruleId]); }

//  M2 §34 — A PARTIAL UPDATE MUST NOT ERASE WHAT IT DOES NOT CARRY.
//  This previously read every column as trim($post[$c] ?? '') on an UPDATE, so a
//  post that simply did not include a field blanked it — a condition, a branch
//  or an effective date could be silently cleared by a form that never showed it.
//  Now only the keys actually present in the post are written; clearing a value
//  means posting it empty, which is a deliberate act.
//  Phase 3 · M3 §25 — SLA AND ESCALATION POLICY IS ASKED FOR AT THE WRITE.
//
//  sla_days, reminder_days, escalate_role and escalate_user_id all live on an
//  approval level, and every one of these functions used to trust the route for
//  authorization. That is the exact shape M2's correction found in delegation: a
//  capability asked on the screen and not at the write, so a direct POST or AJAX
//  call reached the policy without it. A hidden button is not security.
//
//  One question, asked once, by everything that writes approval policy.
//  ENTITLEMENT FIRST, then capability — the ordering is the security property,
//  and it is why this gate stays shut for a master on a workspace that has not
//  bought recruitment. Every rule this policy governs (hiring request, offer,
//  salary, requisition) belongs to the People & hiring module, so a workspace
//  without it has no approval matrix for anybody to edit. There is no master
//  bypass of entitlement anywhere in this application and there is not one here.
function appr_config_can() {
    if (function_exists('licence_blocks') && licence_blocks('mod.hiring.view')) return false;
    return !function_exists('hiring_admin_can') || hiring_admin_can();
}
function appr_rule_save($id, $post) {
    appr_migrate();
    if (!appr_config_can()) return 0;
    $id = (int) $id;
    $existing = $id > 0 ? appr_rule($id) : null;
    $text = ['code','name','applies_department','applies_sbu','applies_grade','applies_position','effective_from','effective_to'];
    $num  = ['min_amount','max_amount'];
    $int  = ['sort','applies_office_id'];

    $val = function ($k, $default = '') use ($post, $existing) {
        if (array_key_exists($k, $post)) return trim((string) $post[$k]);
        if ($existing && array_key_exists($k, $existing)) return (string) $existing[$k];
        return $default;
    };
    $entity = array_key_exists($post['entity'] ?? '', APPR_ENTITIES)
        ? $post['entity'] : ($existing['entity'] ?? 'OFFER');

    $cols = ['entity' => $entity];
    foreach ($text as $c) $cols[$c] = $val($c);
    foreach ($num  as $c) $cols[$c] = (float) $val($c, '0');
    foreach ($int  as $c) $cols[$c] = ($c === 'applies_office_id') ? ((int) $val($c, '0') ?: null) : (int) $val($c, '0');
    foreach (['effective_from','effective_to'] as $d)            // dates, or nothing
        if ($cols[$d] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', substr($cols[$d], 0, 10))) $cols[$d] = '';
        else $cols[$d] = substr($cols[$d], 0, 10);

    if ($existing) {
        $set = implode(',', array_map(fn($c) => "$c=?", array_keys($cols)));
        db()->prepare("UPDATE recruit_approval_rules SET $set WHERE id=?")
            ->execute([...array_values($cols), $id]);
        appr_audit_policy($id, 'Approval policy updated: ' . ($cols['name'] ?: '#' . $id));
        return $id;
    }
    if (trim((string) $cols['name']) === '') $cols['name'] = 'New rule';
    $keys = array_keys($cols);
    db()->prepare("INSERT INTO recruit_approval_rules (" . implode(',', $keys) . ",active,created_at) VALUES ("
        . implode(',', array_fill(0, count($keys), '?')) . ",1,?)")
        ->execute([...array_values($cols), _appr_now()]);
    $new = (int) db()->lastInsertId();
    appr_audit_policy($new, 'Approval policy created: ' . $cols['name']);
    return $new;
}

//  M2 §35 — approval configuration is sensitive, so every change goes on the
//  EXISTING audit spine. No second audit system.
function appr_audit_policy($ruleId, $what) {
    if (function_exists('act_log')) act_log('APPROVAL_POLICY', (int) $ruleId, 'SYSTEM', $what, ['auto' => 1]);
}
function appr_rule_set_active($id, $on) {
    appr_migrate();
    if (!appr_config_can()) return false;
    db()->prepare("UPDATE recruit_approval_rules SET active=? WHERE id=?")->execute([$on?1:0,(int)$id]);
    appr_audit_policy($id, $on ? 'Approval policy activated' : 'Approval policy deactivated');
}
function appr_level_save($post) {
    appr_migrate();
    if (!appr_config_can()) return false;
    $rid = (int)($post['rule_id'] ?? 0); if (!$rid) return false;
    // Validate the posted roles against the roles THIS workspace's plan uses, so a
    // switched-off module's role (Inspector, Marketing, Finance…) can never be saved.
    $roleSet = array_keys(function_exists('roles_for_licence') ? roles_for_licence() : ORG_ROLES);
    $okApprover = fn($v) => in_array($v, $roleSet, true) || (function_exists('appr_is_org_approver') && appr_is_org_approver($v));
    $role = $okApprover($post['approver_role'] ?? '') ? $post['approver_role'] : '';
    $esc  = $okApprover($post['escalate_role'] ?? '') ? $post['escalate_role'] : '';
    $data = [(int)($post['seq']??0), trim((string)($post['label']??'')), $role, (int)($post['approver_user_id']??0)?:null,
             max(0,(int)($post['sla_days']??2)), max(0,(int)($post['reminder_days']??1)), $esc, (int)($post['escalate_user_id']??0)?:null];
    $lid = (int)($post['level_id'] ?? 0);
    if ($lid > 0) { $data[] = $lid;
        db()->prepare("UPDATE recruit_approval_levels SET seq=?,label=?,approver_role=?,approver_user_id=?,sla_days=?,reminder_days=?,escalate_role=?,escalate_user_id=? WHERE id=?")->execute($data);
    } else { array_unshift($data, $rid);
        db()->prepare("INSERT INTO recruit_approval_levels (rule_id,seq,label,approver_role,approver_user_id,sla_days,reminder_days,escalate_role,escalate_user_id) VALUES (?,?,?,?,?,?,?,?,?)")->execute($data);
    }
    appr_audit_policy($rid, 'Approval level saved: ' . (trim((string)($post['label'] ?? '')) ?: 'level ' . (int)($post['seq'] ?? 0))
        . ($role !== '' ? ' → ' . $role : ''));
}
function appr_level_delete($id) {
    appr_migrate();
    if (!appr_config_can()) return false;
    $lv = ops_one("SELECT rule_id, label FROM recruit_approval_levels WHERE id=?", [(int)$id]);
    db()->prepare("DELETE FROM recruit_approval_levels WHERE id=?")->execute([(int)$id]);
    if ($lv) appr_audit_policy((int) $lv['rule_id'], 'Approval level removed: ' . ($lv['label'] ?: '#' . (int) $id));
}

// ---- Matching (narrowest wins) ---------------------------------------------
//  PRECEDENCE — deterministic, and now written down (M2 §7).
//
//    1. SPECIFICITY   how many conditions the rule actually pinned down:
//                     department, business unit, grade, position, branch, and
//                     one point for a matched amount band. A rule that names
//                     more wins.
//    2. MATCH ORDER   `sort` on the rule — what the screen calls "Match order".
//    3. ID            creation order.
//
//  The loop keeps a rule only on a STRICTLY greater score while iterating in
//  ORDER BY sort, id — so an equal-specificity tie falls to `sort`, then to the
//  older rule. Never random, never a silent coin-toss. appr_match_all() exposes
//  the whole ranked list so an administrator can be shown the runners-up and any
//  ambiguity, rather than just the winner.
function appr_match($entity, $ctx) {
    $ranked = appr_match_all($entity, $ctx);
    return $ranked ? $ranked[0]['rule'] : null;
}

//  Every rule that matches, best first, each with the score that ranked it and
//  whether it ties with the one above. This is what the preview and the conflict
//  warnings read.
function appr_match_all($entity, $ctx) {
    $out = [];
    foreach (appr_rules($entity, true) as $r) {
        $score = appr_rule_score($r, $ctx);
        if ($score === null) continue;                      // a condition failed
        $out[] = ['rule' => $r, 'score' => $score];
    }
    // Stable: score desc, then the order appr_rules() already returns them in
    // (sort, id). usort is not stable across engines, so rank is carried.
    foreach ($out as $i => $row) $out[$i]['seen'] = $i;
    usort($out, function ($a, $b) {
        if ($a['score'] !== $b['score']) return $b['score'] <=> $a['score'];
        return $a['seen'] <=> $b['seen'];
    });
    foreach ($out as $i => $row)
        $out[$i]['ties_with_previous'] = $i > 0 && $out[$i - 1]['score'] === $row['score'];
    return $out;
}

//  How well one rule fits, or null when it does not apply at all.
function appr_rule_score($r, $ctx) {
    $map = ['applies_department'=>'department','applies_sbu'=>'sbu','applies_grade'=>'grade','applies_position'=>'position'];
    {
        $ok = true; $score = 0;
        foreach ($map as $col => $k) {
            $f = trim((string)($r[$col] ?? '')); if ($f === '') continue;
            $vals = array_map('strtolower', array_map('trim', explode(',', $f)));
            $have = strtolower(trim((string)($ctx[$k] ?? '')));
            $hit  = in_array($have, $vals, true);
            // M3 — a rule keyed on Department must match the requisition even when
            // the two were written in different words. The rule box is free text
            // and the requisition stores a coded value, so a rule for "Quality"
            // never fired on a requisition filed as "QAQC". Compare through the
            // canonical department instead. This can only ever ADD a match that
            // the customer has APPROVED: an unrecognised term canonicalises to
            // itself, so nothing matches by guesswork.
            if (!$hit && $k === 'department' && $have !== '' && function_exists('dept_canon')) {
                try {
                    $mine = strtolower(dept_canon($have));
                    foreach ($vals as $v) if ($v !== '' && strtolower(dept_canon($v)) === $mine) { $hit = true; break; }
                } catch (Throwable $e) {}
            }
            if ($hit) $score++;
            else { $ok = false; break; }
        }
        if (!$ok) return null;
        // M2 — BRANCH. A rule may be pinned to one office; an unpinned rule is
        // the customer's global policy and still applies. A rule for another
        // branch does not apply at all.
        $ruleOffice = (int) ($r['applies_office_id'] ?? 0);
        if ($ruleOffice > 0) {
            if ((int) ($ctx['office_id'] ?? 0) !== $ruleOffice) return null;
            $score++;
        }
        // M2 — EFFECTIVE DATES. Before it starts or after it ends, a rule is not
        // in force. Blank means "no limit", which is what every existing rule has.
        $today = date('Y-m-d');
        $from = substr(trim((string) ($r['effective_from'] ?? '')), 0, 10);
        $to   = substr(trim((string) ($r['effective_to']   ?? '')), 0, 10);
        if ($from !== '' && $today < $from) return null;
        if ($to   !== '' && $today > $to)   return null;

        $amt = (float)($ctx['amount'] ?? 0);
        if ((float)$r['min_amount'] > 0 && $amt < (float)$r['min_amount']) return null;
        if ((float)$r['max_amount'] > 0 && $amt > (float)$r['max_amount']) return null;
        if ((float)$r['min_amount'] > 0 || (float)$r['max_amount'] > 0) $score++;   // a band that matched adds specificity
        return $score;
    }
}

// ---- Delegation administration (Phase 3 · M2) ------------------------------
//  Configuring a delegation is a privileged act: it moves approval authority
//  from one person to another. It is gated exactly like the rest of approval
//  configuration, and every change is audited.
function appr_delegations($activeOnly = false) {
    appr_migrate();
    $w = $activeOnly ? 'WHERE d.active=1' : '';
    try {
        return ops_all("SELECT d.*, du.first_name dfn, du.last_name dln, du.username dun,
                               eu.first_name efn, eu.last_name eln, eu.username eun
                        FROM approval_delegations d
                        LEFT JOIN users du ON du.id=d.delegator_user_id
                        LEFT JOIN users eu ON eu.id=d.delegate_user_id
                        $w ORDER BY d.id DESC");
    } catch (Throwable $e) { return []; }
}
function appr_delegation($id) {
    appr_migrate();
    try { return ops_one("SELECT * FROM approval_delegations WHERE id=?", [(int) $id]) ?: null; }
    catch (Throwable $e) { return null; }
}

//  Returns [ok, message, id]. Validates against the real register rather than
//  trusting the form: a dropdown is not a security boundary.
function appr_delegation_save($id, array $post) {
    // The right is asked HERE, at the write, and not only on the route — the
    // discipline M1 established after branch scope was found living on the route
    // alone. Moving approval authority is privileged; a helper called directly
    // must not reach the table around the gate.
    //
    // M3 CORRECTION · F2 — and it is the SAME gate the approval matrix uses.
    // M3 put entitlement in front of capability for policy writes and left this
    // one asking capability alone, so a workspace that had stopped paying for
    // recruitment could not edit its approval matrix but could still MOVE APPROVAL
    // AUTHORITY from one person to another — the more privileged of the two. One
    // gate, both siblings; not a second entitlement rule.
    if (!appr_config_can())
        return [false, 'Only an administrator on a workspace with recruitment can configure approval delegation.', 0];
    appr_migrate();
    $id = (int) $id;
    $existing = $id > 0 ? appr_delegation($id) : null;
    if ($id > 0 && !$existing) return [false, 'That delegation no longer exists.', 0];

    $val = function ($k, $d = '') use ($post, $existing) {
        if (array_key_exists($k, $post)) return trim((string) $post[$k]);
        if ($existing && array_key_exists($k, $existing)) return (string) $existing[$k];
        return $d;
    };
    $from_u = (int) $val('delegator_user_id', '0');
    $to_u   = (int) $val('delegate_user_id', '0');
    if ($from_u <= 0 || $to_u <= 0) return [false, 'Choose who is delegating and who is acting for them.', 0];
    if ($from_u === $to_u) return [false, 'A person cannot delegate their approval authority to themselves.', 0];
    foreach ([[$from_u, 'delegating'], [$to_u, 'acting']] as $u) {
        try { $row = ops_one("SELECT id, is_active FROM users WHERE id=?", [$u[0]]); } catch (Throwable $e) { $row = null; }
        if (!$row) return [false, 'That is not someone in this workspace.', 0];
        if ((int) ($row['is_active'] ?? 0) !== 1) return [false, 'That user is switched off, so they cannot be ' . $u[1] . '.', 0];
    }
    $entity = strtoupper($val('entity'));
    if ($entity !== '' && !isset(APPR_ENTITIES[$entity])) return [false, 'That is not something this workspace approves.', 0];
    $office = (int) $val('office_id', '0') ?: null;
    if ($office !== null && function_exists('scope_allows') && !scope_allows($office, null))
        return [false, 'That branch is outside your office / branch scope.', 0];
    $dates = [];
    foreach (['effective_from', 'effective_to'] as $d) {
        $v = substr($val($d), 0, 10);
        if ($v !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return [false, 'That is not a date.', 0];
        $dates[$d] = $v;
    }
    if ($dates['effective_from'] !== '' && $dates['effective_to'] !== '' && $dates['effective_to'] < $dates['effective_from'])
        return [false, 'The delegation cannot end before it starts.', 0];

    $cols = [
        'delegator_user_id' => $from_u, 'delegate_user_id' => $to_u,
        'entity' => $entity, 'office_id' => $office,
        'effective_from' => $dates['effective_from'], 'effective_to' => $dates['effective_to'],
        'reason' => substr($val('reason'), 0, 240),
        'active' => array_key_exists('active', $post) ? (int) !!$post['active'] : (int) ($existing['active'] ?? 1),
    ];
    if ($existing) {
        $set = implode(',', array_map(fn($c) => "$c=?", array_keys($cols)));
        db()->prepare("UPDATE approval_delegations SET $set WHERE id=?")->execute([...array_values($cols), $id]);
    } else {
        $keys = array_keys($cols);
        db()->prepare("INSERT INTO approval_delegations (" . implode(',', $keys) . ",created_by,created_at) VALUES ("
            . implode(',', array_fill(0, count($keys), '?')) . ",?,?)")
            ->execute([...array_values($cols), _appr_actor(), _appr_now()]);
        $id = (int) db()->lastInsertId();
    }
    if (function_exists('act_log'))
        act_log('APPROVAL_DELEGATE', $id, 'SYSTEM',
                ($existing ? 'Delegation updated' : 'Delegation created') . ': user #' . $from_u . ' → user #' . $to_u
                . ($cols['effective_from'] !== '' || $cols['effective_to'] !== '' ? ' (' . ($cols['effective_from'] ?: '…') . ' to ' . ($cols['effective_to'] ?: '…') . ')' : ''),
                ['auto' => 1]);
    return [true, $existing ? 'Delegation saved.' : 'Delegation created.', $id];
}

function appr_delegation_revoke($id) {
    // M3 CORRECTION · F2 — the same gate as create/edit and as the approval matrix.
    if (!appr_config_can())
        return [false, 'Only an administrator on a workspace with recruitment can revoke an approval delegation.'];
    appr_migrate();
    $d = appr_delegation($id); if (!$d) return [false, 'That delegation no longer exists.'];
    db()->prepare("UPDATE approval_delegations SET active=0, revoked_by=?, revoked_at=? WHERE id=?")
        ->execute([_appr_actor(), _appr_now(), (int) $id]);
    if (function_exists('act_log'))
        act_log('APPROVAL_DELEGATE', (int) $id, 'SYSTEM', 'Delegation revoked', ['auto' => 1, 'outcome' => 'REVOKED']);
    return [true, 'Delegation revoked.'];
}

// ---- Who could actually approve a level (Phase 3 · M2) ---------------------
//  M1's adversarial audit found a configuration trap: a level may name a role
//  that no active user holds, and nothing said so. The administrator believed
//  the workflow was configured; the request simply parked.
//
//  Returns the active users eligible for a level, so the screen can warn and the
//  preview can show real names. An org-chart token resolves per request, not per
//  rule, so it is reported as such rather than counted as zero.
function appr_level_eligible($level, $ctx = []) {
    appr_migrate();
    $uid = (int) ($level['approver_user_id'] ?? 0);
    if ($uid > 0) {
        try { $u = ops_one("SELECT id, first_name, last_name, username, is_active, role FROM users WHERE id=?", [$uid]); }
        catch (Throwable $e) { $u = null; }
        return ['kind' => 'USER', 'users' => ($u && (int) $u['is_active'] === 1) ? [$u] : [], 'role' => ''];
    }
    $role = trim((string) ($level['approver_role'] ?? ''));
    if ($role === '') return ['kind' => 'NONE', 'users' => [], 'role' => ''];
    if (function_exists('appr_is_org_approver') && appr_is_org_approver($role)) {
        // Resolved against the position tree when a request exists; a hiring
        // admin can always act, so this can never strand a step.
        $who = [];
        if (!empty($ctx['position_id']) && function_exists('appr_resolve_org_approver')) {
            $rid = (int) appr_resolve_org_approver($role, (int) $ctx['position_id'], (string) ($ctx['department'] ?? ''));
            if ($rid > 0) { try { $u = ops_one("SELECT id, first_name, last_name, username, is_active, role FROM users WHERE id=?", [$rid]); if ($u) $who[] = $u; } catch (Throwable $e) {} }
        }
        return ['kind' => 'ORG', 'users' => $who, 'role' => $role];
    }
    try { $rows = ops_all("SELECT id, first_name, last_name, username, is_active, role FROM users WHERE role=? AND is_active=1 ORDER BY first_name", [$role]); }
    catch (Throwable $e) { $rows = []; }
    return ['kind' => 'ROLE', 'users' => $rows, 'role' => $role];
}

//  Levels of a rule that nobody can currently action. ORG tokens are excluded:
//  they resolve per request and fall back to a hiring admin by design.
function appr_orphan_levels($ruleId, $ctx = []) {
    $out = [];
    foreach (appr_levels($ruleId) as $lv) {
        $e = appr_level_eligible($lv, $ctx);
        if ($e['kind'] === 'ORG') continue;
        if (!$e['users']) $out[] = ['level' => $lv, 'why' => $e['kind'] === 'NONE' ? 'no approver configured' : 'no active user holds this role'];
    }
    return $out;
}

// ---- "Why this approval?" — the deterministic resolver (M2 §14) ------------
//  Given the facts of a request, show the administrator exactly what the policy
//  would do: which rule wins, which runners-up matched, whether anything ties,
//  the levels, and who could actually act at each one.
function appr_preview($entity, $ctx) {
    $ranked = appr_match_all($entity, $ctx);
    $win = $ranked ? $ranked[0]['rule'] : null;
    $levels = [];
    if ($win) {
        foreach (appr_levels($win['id']) as $lv)
            $levels[] = ['level' => $lv, 'eligible' => appr_level_eligible($lv, $ctx)];
    }
    return [
        'matched'   => $win,
        'ranked'    => $ranked,
        'ambiguous' => count($ranked) > 1 && !empty($ranked[1]['ties_with_previous']),
        'levels'    => $levels,
        'orphans'   => $win ? appr_orphan_levels($win['id'], $ctx) : [],
        'no_match'  => $win === null,
    ];
}

// ---- Requests & steps ------------------------------------------------------
function appr_open($entity, $entityId) {
    appr_migrate();
    return ops_one("SELECT * FROM recruit_approval_requests WHERE entity=? AND entity_id=? AND status='PENDING' ORDER BY id DESC LIMIT 1", [$entity, (int)$entityId]) ?: null;
}
function appr_request($id) { appr_migrate(); return ops_one("SELECT * FROM recruit_approval_requests WHERE id=?", [(int)$id]) ?: null; }

//  M1 CORRECTION — close any chain still open against an entity, because the
//  entity itself has gone away (cancelled). This is not a new cancellation
//  mechanism: every query in this engine that decides whether a chain is live
//  already asks for status='PENDING' — appr_open(), appr_inbox(), appr_tick()
//  and appr_act() itself — so moving the request row off PENDING closes it
//  everywhere at once, with nothing else to change.
//
//  Returns the number of chains closed.
function appr_cancel_open($entity, $entityId, $reason = '') {
    appr_migrate();
    $n = 0;
    try {
        foreach (ops_all("SELECT id FROM recruit_approval_requests WHERE entity=? AND entity_id=? AND status='PENDING'",
                         [(string) $entity, (int) $entityId]) as $r) {
            db()->prepare("UPDATE recruit_approval_steps SET status='CANCELLED', acted_by=?, acted_at=?, remarks=? WHERE request_id=? AND status='PENDING'")
                ->execute([_appr_actor(), _appr_now(), substr(trim((string) $reason), 0, 400), (int) $r['id']]);
            db()->prepare("UPDATE recruit_approval_requests SET status='CANCELLED', closed_at=? WHERE id=?")
                ->execute([_appr_now(), (int) $r['id']]);
            $n++;
        }
    } catch (Throwable $e) { /* never break the cancellation itself */ }
    return $n;
}
function appr_steps($requestId) { appr_migrate(); return ops_all("SELECT * FROM recruit_approval_steps WHERE request_id=? ORDER BY seq, id", [(int)$requestId]); }
function appr_current_step($request) {
    $s = ops_one("SELECT * FROM recruit_approval_steps WHERE request_id=? AND seq=? AND status='PENDING' ORDER BY id LIMIT 1", [(int)$request['id'], (int)$request['current_seq']]);
    return $s ?: null;
}

// Start a chain if a rule matches. Returns [started(bool), requestId|0].
function appr_start($entity, $entityId, $ctx, $subject = '', $amount = 0) {
    appr_migrate();
    if (appr_open($entity, $entityId)) return [true, (int)appr_open($entity, $entityId)['id']];
    $rule = appr_match($entity, $ctx);
    if (!$rule) return [false, 0];
    $levels = appr_levels($rule['id']);
    if (!$levels) return [false, 0];
    // D1 — the display name is still written, for screens; the IDENTITY is written
    // beside it, from the session, and it is the identity that decides anything.
    $reqUid = function_exists('current_user') ? (int) ((current_user()['id'] ?? 0)) : 0;
    db()->prepare("INSERT INTO recruit_approval_requests (entity,entity_id,rule_id,rule_name,subject,amount,status,current_seq,requester,requester_id,created_at) VALUES (?,?,?,?,?,?, 'PENDING', ?,?,?,?)")
        ->execute([$entity,(int)$entityId,(int)$rule['id'],(string)$rule['name'],(string)$subject,(float)$amount, (int)$levels[0]['seq'], _appr_actor(), $reqUid ?: null, _appr_now()]);
    $reqId = (int)db()->lastInsertId();
    // An org-chart approver token ("reporting manager", "HOD", …) is resolved to a
    // real person here, from the requisition's position walked up the reporting
    // line. If it resolves, the step is pinned to that user; if not, the token is
    // kept and appr_can_act lets a hiring admin act so a step is never stranded.
    $orgResolve = function ($role) use ($ctx) {
        if ($role === '' || !function_exists('appr_is_org_approver') || !appr_is_org_approver($role)) return 0;
        $posId = (int) ($ctx['position_id'] ?? 0);
        if (!$posId && !empty($ctx['position'])) {
            try { $pp = ops_one("SELECT id FROM positions WHERE LOWER(name)=LOWER(?) OR LOWER(code)=LOWER(?) LIMIT 1", [$ctx['position'], $ctx['position']]); if ($pp) $posId = (int) $pp['id']; }
            catch (Throwable $e) {}
        }
        return function_exists('appr_resolve_org_approver') ? (int) appr_resolve_org_approver($role, $posId, (string) ($ctx['department'] ?? '')) : 0;
    };
    foreach ($levels as $lv) {
        $role = (string) $lv['approver_role']; $uid = $lv['approver_user_id'] ?: null;
        $esc = (string) $lv['escalate_role']; $euid = $lv['escalate_user_id'] ?: null;
        if (!$uid && ($rid = $orgResolve($role)) > 0) { $uid = $rid; $role = ''; }   // pinned to the resolved manager
        if (!$euid && ($eid = $orgResolve($esc)) > 0) { $euid = $eid; $esc = ''; }
        // M3 §7 — the POLICY is frozen onto the step here; the CLOCK is not
        // started here. A step that nobody is waiting on yet has no due date.
        db()->prepare("INSERT INTO recruit_approval_steps (request_id,seq,label,approver_role,approver_user_id,escalate_role,escalate_user_id,sla_days,reminder_days,sla_due,reminder_at,activated_at,status) VALUES (?,?,?,?,?,?,?,?,?,'','','', 'PENDING')")
            ->execute([$reqId,(int)$lv['seq'],(string)$lv['label'],$role,$uid,$esc,$euid,max(0,(int)$lv['sla_days']),max(0,(int)$lv['reminder_days'])]);
    }
    // Only the first level is being waited on, so only its clock starts.
    $reqRow = appr_request($reqId);
    $first = appr_current_step($reqRow);
    if ($first) {
        $first = appr_activate_step($first, $reqRow);
        appr_email_approver($first, $reqRow, 'requested');
    }
    return [true, $reqId];
}

//  Phase 3 · M3 §7 — THE SLA CLOCK STARTS HERE, AND ONLY HERE.
//
//  Before this, appr_start() stamped a due date on EVERY level at once, from the
//  moment the request was raised. A three-level chain with two days per level
//  whose first approver took three days handed level 2 to its approver ALREADY
//  OVERDUE, and the next tick escalated it before that person had a minute to
//  look at it; level 3 was two days overdue before anyone had seen it.
//
//  So the clock starts when the step becomes the one being waited on. What it is
//  measured against was frozen onto the step at chain creation, so an
//  administrator editing the matrix today cannot rewrite an approval that is
//  already running (§31).
//
//  Idempotent by the activated_at stamp: activating twice cannot extend an
//  approver's deadline, which would otherwise be a way to defeat the SLA.
function appr_activate_step($step, $req = null) {
    if (!$step) return $step;
    $id = (int) ($step['id'] ?? 0);
    if ($id <= 0) return $step;
    if (trim((string) ($step['activated_at'] ?? '')) !== '') return $step;
    $req = $req ?: appr_request((int) ($step['request_id'] ?? 0));
    $office = (int) (appr_step_context($step, $req)['_office_id'] ?? 0);
    $now = _appr_now();
    $due = appr_due_at((int) ($step['sla_days'] ?? 0), $office);
    $rem = appr_due_at((int) ($step['reminder_days'] ?? 0), $office);
    try {
        db()->prepare("UPDATE recruit_approval_steps SET activated_at=?, sla_due=?, reminder_at=? WHERE id=?")
            ->execute([$now, $due, $rem, $id]);
    } catch (Throwable $e) { return $step; }
    $step['activated_at'] = $now; $step['sla_due'] = $due; $step['reminder_at'] = $rem;
    appr_audit_sla($req, $step, 'SLA started', 'due ' . substr($due, 0, 10), 'SLA_START');
    return $step;
}

// ---- Delegation (Phase 3 · M2) ---------------------------------------------
//  Who is currently acting FOR whom. A delegation transfers the authority its
//  delegator actually holds — never more — for a stated period and, optionally,
//  one entity and one branch.
//
//  Returns the delegator user ids this person may currently act for.
//  Phase 3 · M3 — THE ONE PLACE DELEGATION VALIDITY IS DECIDED.
//
//  M3 needs to read the delegation table the other way round: not "who may this
//  person act for" but "who is currently acting for this approver", so a reminder
//  reaches the person the system actually expects to act. That is a second
//  READING of the rule — it must never become a second RULE. M2's correction was
//  precisely about two copies of a delegation rule drifting apart, and adding a
//  reader is not a licence to repeat that.
//
//  So both directions come through here. $col is allow-listed, never
//  interpolated from anything a caller could shape.
function appr_valid_delegations($col, $userId, $entity = '', $officeId = null) {
    appr_migrate();
    if (!in_array($col, ['delegate_user_id', 'delegator_user_id'], true)) return [];
    $userId = (int) $userId; if ($userId <= 0) return [];
    $today = date('Y-m-d');
    try {
        $rows = ops_all("SELECT * FROM approval_delegations WHERE $col=? AND active=1", [$userId]);
    } catch (Throwable $e) { return []; }
    $out = [];
    foreach ($rows as $d) {
        $from = substr(trim((string) $d['effective_from']), 0, 10);
        $to   = substr(trim((string) $d['effective_to']), 0, 10);
        if ($from !== '' && $today < $from) continue;         // not started
        if ($to   !== '' && $today > $to)   continue;         // expired
        $de = trim((string) ($d['entity'] ?? ''));
        if ($de !== '' && strtoupper($de) !== strtoupper((string) $entity)) continue;   // wrong entity

        // M2 CORRECTION · FINDING A — a delegation that NAMES a branch must not
        // apply where a branch cannot be established. This read
        // "$do > 0 && $officeId !== null && ..." which treated a missing branch
        // context as "do not filter", so a delegation scoped to one office was
        // UNSCOPED for every entity that carries no office (offer, salary,
        // requisition). The administrator said "my Ahmedabad work while I'm
        // away"; they also got company-wide offer approvals.
        $do = (int) ($d['office_id'] ?? 0);
        if ($do > 0) {
            if ($officeId === null) continue;                // no branch to check against → does not apply
            if ((int) $officeId !== $do) continue;            // wrong branch
        }

        // M2 CORRECTION · FINDING B — the DELEGATOR must still be active. This
        // was asked on the role path only, so the two paths disagreed: with the
        // delegator switched off a role step refused and a named-user step
        // still granted, and somebody who had left the company kept lending
        // their approval authority. Asked ONCE here, so both paths inherit it.
        $dl = (int) $d['delegator_user_id'];
        try { $du = ops_one("SELECT is_active FROM users WHERE id=?", [$dl]); } catch (Throwable $e) { $du = null; }
        if (!$du || (int) ($du['is_active'] ?? 0) !== 1) continue;

        $out[] = $d;
    }
    return $out;
}

//  Delegate → the delegators they may currently act for. The M2 signature and
//  semantics are unchanged; only where the rule lives has moved.
function appr_delegators_for($userId, $entity = '', $officeId = null) {
    $out = [];
    foreach (appr_valid_delegations('delegate_user_id', $userId, $entity, $officeId) as $d)
        $out[] = (int) $d['delegator_user_id'];
    return array_values(array_unique($out));
}

//  Delegator → the people currently acting for them. M3 uses this to decide who
//  to NOTIFY. It decides nothing about authority: appr_can_act() remains the only
//  thing that says who may approve, and being e-mailed is not being authorized.
function appr_delegates_of($userId, $entity = '', $officeId = null) {
    $out = [];
    foreach (appr_valid_delegations('delegator_user_id', $userId, $entity, $officeId) as $d)
        $out[] = (int) $d['delegate_user_id'];
    return array_values(array_unique($out));
}

//  NO CHAINING. A delegate may act for their delegator, and that is where it
//  stops: A→B→C is refused, because B cannot pass on an authority that was only
//  lent to them. Nothing walks the table twice, and this test says so out loud.
function appr_delegation_chains($userId, $entity = '', $officeId = null) {
    foreach (appr_delegators_for($userId, $entity, $officeId) as $d)
        if (appr_delegators_for($d, $entity, $officeId)) return true;
    return false;
}

//  A step on its own does not know which entity or branch it belongs to, and
//  delegation scope needs both. This decorates it from its request — one place,
//  so every caller asks the same question.
function appr_step_context($step, $req = null) {
    $req = $req ?: appr_request((int) ($step['request_id'] ?? 0));
    if (!$req) return $step;
    $step['_entity'] = (string) ($req['entity'] ?? '');
    $step['_office_id'] = null;
    if (strtoupper((string) $req['entity']) === 'HIRING_REQUEST' && function_exists('hreq_get')) {
        $r = hreq_get((int) $req['entity_id']);
        if ($r) $step['_office_id'] = $r['office_id'] ?? null;
    }
    return $step;
}

//  ---- Approval queue visibility (M1 Finding 2, closed in M2) ---------------
//  What a person may SEE in the queue is now the same question as what they may
//  ACT on. It is asked per entity, so the fix could not empty an Offer or Salary
//  queue whose approvers legitimately have no branch relationship to the record.
//
//  Returns true when this row belongs in this person's queue.
function appr_visible($step, $req, $user = null) {
    if (!appr_can_act($step, $user)) return false;                     // never widens
    $entity = strtoupper(trim((string) ($req['entity'] ?? '')));
    //  M3 CORRECTION #2 · C2 — AN ENTITY WE CANNOT NAME IS NOT ONE WE MAY
    //  DISCLOSE. When the request row could not be fetched, $req arrived null,
    //  $entity came out '', this test read "not a hiring request" and the function
    //  fell through to `return true` — so a missing record SKIPPED entitlement,
    //  branch scope and segregation and notified everyone who held the role.
    //  A missing security subject must make the answer stricter, never looser.
    //
    //  Known-and-supported is now required, and it is checked against the same
    //  APPR_ENTITIES map the rest of the engine uses. An unknown entity is not a
    //  synonym for "some other supported entity".
    if ($entity === '' || !array_key_exists($entity, APPR_ENTITIES)) return false;
    //  G1 — and a SUPPORTED TYPE is not a RESOLVED RECORD. Asked for all four, so
    //  a chain whose source has been deleted can no longer fall through to
    //  appr_can_act() as a generic rescue.
    if (!appr_entity_record($entity, $req['entity_id'] ?? 0)) return false;
    if ($entity !== 'HIRING_REQUEST') return true;                     // scope semantics unchanged for every other entity
    // For a hiring request, seeing it and deciding it are the same question, so
    // the queue asks the guard that governs the decision.
    return appr_guard($req) === '';
}

function appr_can_act($step, $user = null) {
    $user = $user ?: (function_exists('current_user') ? current_user() : null);
    if (!$user) return false;
    // M1 FINDING B. This was a bare is_master(), which walks straight past the
    // module licence: a master on a workspace that had NOT bought recruitment
    // could act on recruitment approval steps (proved with a probe). is_master_of()
    // is the existing licence-aware master helper — master, but only for a module
    // this installation actually has. Same rule the rest of the app already uses.
    if (function_exists('is_master_of') ? is_master_of('hiring') : (function_exists('is_master') && is_master())) return true;
    if ((int)($step['approver_user_id'] ?? 0) > 0) {
        if ((int)$step['approver_user_id'] === (int)$user['id']) return true;
        // M2 — or this person is currently acting for that named approver.
        return in_array((int) $step['approver_user_id'],
                        appr_delegators_for((int) $user['id'], (string) ($step['_entity'] ?? ''), $step['_office_id'] ?? null), true);
    }
    $role = (string)($step['approver_role'] ?? '');
    // An org-chart approver that could not be pinned to a person: a hiring admin
    // acts so the chain is never stranded.
    if ($role !== '' && function_exists('appr_is_org_approver') && appr_is_org_approver($role))
        return function_exists('hiring_admin_can') ? hiring_admin_can() : (function_exists('is_admin_level') && is_admin_level());
    if ($role !== '' && (string)$user['role'] === $role) return true;
    // M2 — acting for somebody who holds the configured role. The delegator must
    // genuinely hold it: a delegation cannot manufacture an authority its
    // delegator never had.
    if ($role !== '') {
        foreach (appr_delegators_for((int) $user['id'], (string) ($step['_entity'] ?? ''), $step['_office_id'] ?? null) as $dl) {
            // Active status is settled in appr_delegators_for() — one rule, two
            // readers. This asks only the question that is specific to a role
            // step: does the delegator genuinely hold the role being delegated?
            try { $du = ops_one("SELECT role FROM users WHERE id=?", [$dl]); } catch (Throwable $e) { $du = null; }
            if ($du && (string) $du['role'] === $role) return true;
        }
    }
    return false;
}

// ---- The decision guard (Phase 3 · M1) -------------------------------------
//  Asked at the mutation choke point, before anything is written, and returns a
//  reason or an empty string so it can be tested without a redirect.
//
//  ENTITY-SCOPED ON PURPOSE. M1 was asked to secure the Hiring Request path, not
//  to change how offers, salary structures and requisitions have behaved since
//  Phase 6. Applying segregation of duties to every entity is a customer-visible
//  policy change and is recorded as a Phase-3 question, not slipped in here.
function appr_guard($req) {
    $entity = strtoupper((string) ($req['entity'] ?? ''));
    if ($entity !== 'HIRING_REQUEST' || !function_exists('hreq_get')) return '';
    // 1. ENTITLEMENT first — the workspace must have bought recruitment. This is
    //    asked before anything about the person, and a master does not escape it.
    if (function_exists('licence_blocks') && licence_blocks('mod.hiring.view'))
        return 'The recruitment module is not switched on for this installation.';
    $r = hreq_get((int) ($req['entity_id'] ?? 0));
    if (!$r) return 'That hiring request no longer exists.';
    // 2. BRANCH SCOPE — asked here, at the decision, not only on a route.
    if (function_exists('hreq_in_scope') && !hreq_in_scope($r))
        return 'This hiring request is outside your office / branch scope.';
    // 3. SEGREGATION OF DUTIES — the requestor may not approve their own request.
    //    One rule, two readers: the same helper the direct decision path uses,
    //    including its single stated master exception, neither broadened nor
    //    narrowed here.
    if (function_exists('hreq_segregation_blocks') && hreq_segregation_blocks($r))
        return 'You raised this request, so somebody else has to decide it.';
    return '';
}

// Approve or reject the given step. Returns [ok, message].
function appr_act($stepId, $decision, $remarks = '') {
    appr_migrate();
    $step = ops_one("SELECT * FROM recruit_approval_steps WHERE id=?", [(int)$stepId]);
    if (!$step || $step['status'] !== 'PENDING') return [false, 'This step is not pending.'];
    $req = appr_request((int)$step['request_id']);
    if (!$req || $req['status'] !== 'PENDING') return [false, 'This request is closed.'];
    if ((int)$step['seq'] !== (int)$req['current_seq']) return [false, 'An earlier level is still pending.'];
    $step = appr_step_context($step, $req);
    if (!appr_can_act($step)) return [false, 'You are not the approver for this step.'];
    $why = appr_guard($req);
    if ($why !== '') return [false, $why];

    $now = _appr_now(); $actor = _appr_actor();
    if ($decision === 'reject') {
        db()->prepare("UPDATE recruit_approval_steps SET status='REJECTED', acted_by=?, acted_at=?, remarks=? WHERE id=?")->execute([$actor,$now,substr((string)$remarks,0,400),(int)$stepId]);
        db()->prepare("UPDATE recruit_approval_requests SET status='REJECTED', closed_at=? WHERE id=?")->execute([$now,(int)$req['id']]);
        $cb = appr_callback($req['entity'], (int)$req['entity_id'], 'REJECTED', $req);
        if ($cb !== true) return appr_undo_step($stepId, $req, $cb);
        appr_email_requester($req, 'rejected', $remarks);
        return [true, 'Rejected.'];
    }
    db()->prepare("UPDATE recruit_approval_steps SET status='APPROVED', acted_by=?, acted_at=?, remarks=? WHERE id=?")->execute([$actor,$now,substr((string)$remarks,0,400),(int)$stepId]);
    // Next pending step?
    $next = ops_one("SELECT * FROM recruit_approval_steps WHERE request_id=? AND status='PENDING' AND seq>? ORDER BY seq LIMIT 1", [(int)$req['id'], (int)$step['seq']]);
    if ($next) {
        db()->prepare("UPDATE recruit_approval_requests SET current_seq=? WHERE id=?")->execute([(int)$next['seq'],(int)$req['id']]);
        // M3 §7 — the next approver's clock starts NOW, not when the request was
        // raised. This is the line that stops a later level being born overdue.
        $reqRow = appr_request((int)$req['id']);
        $next = appr_activate_step($next, $reqRow);
        appr_email_approver($next, $reqRow, 'requested');
        return [true, 'Approved — sent to the next approver.'];
    }
    db()->prepare("UPDATE recruit_approval_requests SET status='APPROVED', closed_at=? WHERE id=?")->execute([$now,(int)$req['id']]);
    $cb = appr_callback($req['entity'], (int)$req['entity_id'], 'APPROVED', $req);
    if ($cb !== true) return appr_undo_step($stepId, $req, $cb);
    appr_email_requester($req, 'approved', $remarks);
    return [true, 'Approved — fully cleared.'];
}

//  M1 CORRECTION — put the step and its chain back the way they were, and tell
//  the approver what actually happened.
//
//  Before this, appr_act() wrote the step, closed the chain, called the callback
//  and DISCARDED its result — so when the underlying record refused the decision
//  (a cancelled hiring request, say) the approver was told "Approved — fully
//  cleared" and the history kept an APPROVED step against a record that had been
//  approved of nothing. A decision that could not be applied is not a decision,
//  so nothing about it is left behind.
function appr_undo_step($stepId, $req, $why) {
    try {
        db()->prepare("UPDATE recruit_approval_steps SET status='PENDING', acted_by='', acted_at='', remarks='' WHERE id=?")
            ->execute([(int) $stepId]);
        db()->prepare("UPDATE recruit_approval_requests SET status='PENDING', closed_at='' WHERE id=?")
            ->execute([(int) $req['id']]);
    } catch (Throwable $e) { /* the refusal below is what matters */ }
    return [false, is_string($why) && $why !== '' ? $why : 'That decision could not be applied.'];
}

// Update the underlying entity when a chain completes.
//
//  M1 CORRECTION — returns TRUE when the decision was applied, or a STRING
//  giving the reason it could not be. Only the HIRING_REQUEST branch can return
//  a reason: the other three keep their original best-effort semantics exactly,
//  because their SQL legitimately affects no rows in ordinary cases (an offer
//  already approved, say) and treating that as a failure would change behaviour
//  this correction was told not to touch.
function appr_callback($entity, $entityId, $result, $req = null) {
    try {
        if ($entity === 'HIRING_REQUEST') {
            // The hiring-request layer owns its own state machine and refuses a
            // decision on a request that is no longer open for one. That refusal
            // must reach the approver instead of being discarded.
            if (!function_exists('hreq_apply_decision')) return true;
            [$ok, $msg] = hreq_apply_decision((int) $entityId, $result === 'APPROVED' ? 'APPROVED' : 'REJECTED',
                                              _appr_actor(), (string) ($req['rule_name'] ?? ''), 'CHAIN');
            return $ok ? true : (string) $msg;
        }
        if ($entity === 'OFFER') {
            if ($result === 'APPROVED') db()->prepare("UPDATE job_offers SET status='APPROVED', approved_by=?, approved_at=? WHERE id=? AND status IN ('PENDING_APPROVAL','DRAFT')")->execute(['Approval chain', _appr_now(), (int)$entityId]);
            else db()->prepare("UPDATE job_offers SET status='DRAFT' WHERE id=? AND status='PENDING_APPROVAL'")->execute([(int)$entityId]);
        } elseif ($entity === 'REQUISITION') {
            if ($result === 'APPROVED') db()->prepare("UPDATE requisitions SET status='approved', approved_by=? WHERE id=?")->execute(['Approval chain', (int)$entityId]);
            else db()->prepare("UPDATE requisitions SET status='on_hold' WHERE id=?")->execute([(int)$entityId]);
        }
    } catch (Throwable $e) { /* callback is best-effort */ }
    return true;
}

// ---- SLA state (Phase 3 · M3 §10) -----------------------------------------
//
//  DERIVED, never stored. The step already knows its status, its due date and
//  whether it has been escalated; the only other input is the clock. Storing a
//  seventh workflow state would mean a nightly job had to keep it true, and a
//  stored status that nobody refreshed is worse than no status at all.
//
//  Before this, "overdue" was re-implemented inline in the view as
//  strtotime($sla_due) < time(), so no two screens could agree and "due soon"
//  did not exist anywhere.
const APPR_SLA_STATES = [
    'COMPLETED'   => 'Completed',
    'NOT_STARTED' => 'Not started',
    'ESCALATED'   => 'Escalated',
    'OVERDUE'     => 'Overdue',
    'DUE'         => 'Due today',
    'DUE_SOON'    => 'Due soon',
    'ON_TRACK'    => 'On track',
];
function appr_sla_state($step, $now = null) {
    $now = $now === null ? time() : (int) $now;
    $status = strtoupper(trim((string) ($step['status'] ?? '')));
    if ($status !== '' && $status !== 'PENDING') return 'COMPLETED';
    $due = trim((string) ($step['sla_due'] ?? ''));
    if ($due === '') return 'NOT_STARTED';          // nobody is waiting on this step yet
    if ((int) ($step['escalated'] ?? 0) === 1) return 'ESCALATED';
    $dueTs = strtotime($due);
    if ($dueTs === false) return 'NOT_STARTED';
    if ($now > $dueTs) return 'OVERDUE';
    if (date('Y-m-d', $now) === date('Y-m-d', $dueTs)) return 'DUE';
    $rem = trim((string) ($step['reminder_at'] ?? ''));
    if ($rem !== '' && ($remTs = strtotime($rem)) !== false && $now >= $remTs) return 'DUE_SOON';
    return 'ON_TRACK';
}
function appr_sla_label($step, $now = null) {
    $st = appr_sla_state($step, $now);
    return APPR_SLA_STATES[$st] ?? $st;
}
//  Whole days late — 0 when it is not late. Used for the one sentence an
//  approver actually reads: "Approval: Pending · SLA: Overdue by 2 days".
function appr_sla_days_late($step, $now = null) {
    $now = $now === null ? time() : (int) $now;
    $due = trim((string) ($step['sla_due'] ?? ''));
    if ($due === '') return 0;
    $dueTs = strtotime($due);
    if ($dueTs === false || $now <= $dueTs) return 0;
    return (int) floor(($now - $dueTs) / 86400);
}
//  The one sentence, built once so every screen says the same thing.
function appr_sla_sentence($step, $now = null) {
    $st = appr_sla_state($step, $now);
    if ($st === 'OVERDUE' || $st === 'ESCALATED') {
        $d = appr_sla_days_late($step, $now);
        $suffix = $d > 0 ? ' by ' . $d . ' day' . ($d === 1 ? '' : 's') : '';
        return ($st === 'ESCALATED' ? 'Escalated — overdue' : 'Overdue') . $suffix;
    }
    return APPR_SLA_STATES[$st] ?? $st;
}

//  Phase 3 · M3 §27 — the approval backlog as five numbers, for the EXISTING
//  Recruitment Command Centre. No second dashboard, no stored counters: it reads
//  the same live steps the inbox reads and derives each state the same way.
//
//  Entitlement is asked here as well as by the screen. A KPI is a read of paid
//  data, and "it is only a number" has never been a reason to answer it for a
//  workspace that has not bought the module.
function appr_sla_summary() {
    appr_migrate();
    //  M3 CORRECTION #14 · B-2 — a condition whose suppression marker could not be
    //  written is a business fact, not a developer's. It is counted HERE, on the
    //  approval summary the Recruitment Command Centre already renders, so no
    //  second dashboard exists and none is redesigned. It is tenant-scoped by
    //  construction: one database per tenant, and this reads the connected one.
    $out = ['pending' => 0, 'due_today' => 0, 'overdue' => 0, 'escalated' => 0, 'due_soon' => 0,
            'suppression_unarmed' => 0];
    if (function_exists('licence_blocks') && licence_blocks('mod.hiring.view')) return $out;
    $out['suppression_unarmed'] = appr_cond_unarmed_count();
    try {
        $rows = ops_all("SELECT s.* FROM recruit_approval_steps s JOIN recruit_approval_requests r ON r.id=s.request_id
                         WHERE r.status='PENDING' AND s.status='PENDING' AND s.seq=r.current_seq");
    } catch (Throwable $e) { return $out; }
    foreach ($rows as $s) {
        $out['pending']++;
        switch (appr_sla_state($s)) {
            case 'ESCALATED': $out['escalated']++; $out['overdue']++; break;
            case 'OVERDUE':   $out['overdue']++;   break;
            case 'DUE':       $out['due_today']++; break;
            case 'DUE_SOON':  $out['due_soon']++;  break;
        }
    }
    return $out;
}

// ---- Inbox (My approvals) --------------------------------------------------
function appr_inbox($user = null) {
    appr_migrate();
    $user = $user ?: (function_exists('current_user') ? current_user() : null);
    if (!$user) return [];
    $rows = ops_all("SELECT s.*, r.entity, r.entity_id, r.subject, r.amount, r.rule_name, r.requester, r.created_at rcreated
                     FROM recruit_approval_steps s JOIN recruit_approval_requests r ON r.id=s.request_id
                     WHERE r.status='PENDING' AND s.status='PENDING' AND s.seq=r.current_seq ORDER BY s.sla_due");
    // M2 — visibility now matches actionability. appr_visible() calls
    // appr_can_act() first, so this can only ever narrow the queue, never widen
    // it, and it is entity-aware so Offer / Salary / Requisition queues are
    // untouched.
    return array_values(array_filter($rows, function ($s) use ($user) {
        $req = ['entity' => $s['entity'], 'entity_id' => $s['entity_id'], 'status' => 'PENDING'];
        return appr_visible(appr_step_context($s, $req), $req, $user);
    }));
}
function appr_inbox_count($user = null) { return count(appr_inbox($user)); }

//  Phase 3 · M3 §19 — "WAITING": raised by me, sitting with somebody else.
//
//  appr_inbox() answers "what must I do", and that is all it has ever answered,
//  so a requester had nowhere to see that their own request was parked with an
//  approver. This is the other half of the same screen, and it is deliberately
//  narrow:
//
//    • HIRING_REQUEST only, matched on requested_by_id — the EXACT owner. The
//      request table's `requester` is a display name, and two people can share
//      one, so it is not used to decide what somebody may see.
//    • entitlement first, as everywhere else.
//    • anything the person could act on is removed — that belongs under
//      "My action" and must not be listed twice.
//
//  It widens nothing: these are the person's own requests.
function appr_waiting_on_others($user = null) {
    appr_migrate();
    $user = $user ?: (function_exists('current_user') ? current_user() : null);
    if (!$user) return [];
    if (function_exists('licence_blocks') && licence_blocks('mod.hiring.view')) return [];
    $me = (int) ($user['id'] ?? 0);
    if ($me <= 0) return [];
    try {
        $rows = ops_all("SELECT s.*, r.entity, r.entity_id, r.subject, r.rule_name, r.requester, r.created_at rcreated
                         FROM recruit_approval_steps s JOIN recruit_approval_requests r ON r.id=s.request_id
                         WHERE r.status='PENDING' AND s.status='PENDING' AND s.seq=r.current_seq
                           AND r.entity='HIRING_REQUEST' ORDER BY s.sla_due");
    } catch (Throwable $e) { return []; }
    $out = [];
    foreach ($rows as $s) {
        if (!function_exists('hreq_get')) break;
        $hr = hreq_get((int) $s['entity_id']);
        if (!$hr || (int) ($hr['requested_by_id'] ?? 0) !== $me) continue;
        $step = appr_step_context($s, ['id' => (int) $s['request_id'], 'entity' => $s['entity'], 'entity_id' => (int) $s['entity_id']]);
        if (appr_can_act($step, $user)) continue;          // that is "My action", not "Waiting"
        $out[] = $s;
    }
    return $out;
}

// ---- Reminders + escalations (cron tick) -----------------------------------
//  What the scheduler may and may not do (§23) is worth saying in the file
//  itself, because the temptation is always to let it "just clear the backlog":
//
//    IT MAY     notice a step is overdue, remind, escalate, notify, audit
//    IT MAY NOT approve, reject, reassign, or manufacture any authority at all
//
//  Nothing below writes to a step's STATUS, to a request's STATUS, or to any
//  approver field. It writes reminder bookkeeping and it sends e-mail. The only
//  authority in this application is appr_can_act(), and the scheduler never
//  calls it, because the scheduler never acts.
function appr_tick() {
    appr_migrate();
    // §29 — cron.php already gates this on the People & hiring module, and that
    // gate stays. Asking here too is the lesson M1 and M2 both taught: a question
    // asked only by the caller is a question one new caller can skip.
    if (function_exists('licence_blocks') && licence_blocks('mod.hiring.view')) return 0;
    $now = time(); $acted = 0;
    //  M3 CORRECTION #14 · B-1 — the scheduler is the real decision maker, and it
    //  no longer throws the condition outcome away. What it decides with it: a tick
    //  that leaves conditions unarmed says so in its own result, so cron.php prints
    //  it and the operator learns from the run itself, not from a log nobody reads.
    $unarmed = 0;
    $steps = ops_all("SELECT s.*, r.subject, r.entity, r.entity_id, r.rule_name, r.rule_id FROM recruit_approval_steps s
                      JOIN recruit_approval_requests r ON r.id=s.request_id
                      WHERE r.status='PENDING' AND s.status='PENDING' AND s.seq=r.current_seq");
    foreach ($steps as $s) {
        //  M3 correction #7 — the scheduler used to build this row WITHOUT rule_id,
        //  so after correction #6 an orphaned chain had no openable subject and its
        //  SLA events were dropped entirely. That silenced H2 by accident, through
        //  K3's lost-history branch, rather than by recording the condition once.
        //  Carrying the rule through gives the event a subject that opens.
        $req = ['id' => (int) $s['request_id'], 'entity' => $s['entity'],
                'entity_id' => (int) ($s['entity_id'] ?? 0), 'subject' => $s['subject'],
                'rule_id' => (int) ($s['rule_id'] ?? 0), 'status' => 'PENDING'];
        // A step whose clock never started is not late — it is waiting to be
        // handed over. Start it here rather than skipping it forever, so a chain
        // cannot be stranded by a write that failed at activation time.
        if (trim((string) $s['sla_due']) === '') {
            $s = appr_activate_step($s, $req);
            if (trim((string) ($s['sla_due'] ?? '')) === '') continue;
        }
        $sla = $s['sla_due'] ? strtotime($s['sla_due']) : 0;
        if ($sla && $now >= $sla && (int)$s['escalated'] === 0) {
            // §24 + §20 — this used to set escalated=1 whether or not a single
            // person had been told, so a step could be permanently marked
            // escalated with nobody notified and no trace. The outcome is now
            // recorded as it actually happened.
            [$n, $ok] = appr_email_escalate($s, $req);
            db()->prepare("UPDATE recruit_approval_steps SET escalated=1 WHERE id=?")->execute([(int)$s['id']]);
            $co = appr_audit_sla($req, $s, 'Approval overdue — escalated', appr_delivery_note($n, $ok), 'ESCALATION');
            if ($co === APPR_COND_UNARMED || $co === APPR_COND_PENDING_RETRY
                || $co === APPR_COND_NOT_RECORDED) $unarmed++;   // B-1
            $acted++;
            continue;
        }
        //  M3 CORRECTION · F3 — ONCE ESCALATED, THE ROUTINE REMINDER STOPS.
        //
        //  Before this the two signals ran the wrong way round: the escalation
        //  contact was told once and never again, while the approver was reminded
        //  every day for ever, each with an e-mail to every recipient and a row on
        //  the activity timeline. An abandoned request nagged without limit and its
        //  audit trail grew without bound.
        //
        //  The step does not go quiet — it stays PENDING and reads ESCALATED on
        //  every screen and in the dashboard count, which is the durable signal.
        //  What stops is the daily repetition, not the visibility.
        $rem = $s['reminder_at'] ? strtotime($s['reminder_at']) : 0;
        if ($rem && $now >= $rem && (int) $s['escalated'] === 0) {
            [$n, $ok] = appr_email_approver($s, $req, 'reminder');
            // §21 — the identity of a reminder is (step, threshold). Moving the
            // threshold forward is what makes a second run of the scheduler a
            // no-op, however many times it runs in a day.
            db()->prepare("UPDATE recruit_approval_steps SET reminded_at=?, reminder_at=? WHERE id=?")->execute([_appr_now(), date('c', $now + 86400), (int)$s['id']]);
            $co = appr_audit_sla($req, $s, 'Approval reminder sent', appr_delivery_note($n, $ok), 'REMINDER');
            if ($co === APPR_COND_UNARMED || $co === APPR_COND_PENDING_RETRY
                || $co === APPR_COND_NOT_RECORDED) $unarmed++;   // B-1
            $acted++;
        }
    }
    //  B-1 — the outcome reaches the run's own result. appr_tick_unarmed() is what
    //  cron.php reports; it is per-workspace and per-run, like everything else here.
    //  #15 · PART A/B — reconciliation runs here, in the approval scheduler that
    //  already exists, so a repaired workspace recovers WITHOUT the original
    //  condition ever firing again. It is bounded per run and never throws.
    try { $rc = appr_cond_reconcile(); } catch (Throwable $e) { $rc = ['recovered'=>0,'terminal'=>0,'still'=>0]; }
    $GLOBALS['__appr_tick_unarmed'] = ['epoch' => function_exists('db_epoch') ? db_epoch() : 0,
                                       'n' => $unarmed, 'rc' => $rc];
    return $acted;
}

//  How many conditions this run could not arm. Workspace-keyed, like every other
//  cross-call state in this module.
function appr_tick_unarmed() {
    $r = $GLOBALS['__appr_tick_unarmed'] ?? null;
    if (!is_array($r) || ($r['epoch'] ?? -1) !== (function_exists('db_epoch') ? db_epoch() : 0)) return 0;
    return (int) ($r['n'] ?? 0);
}
//  What the last reconciliation pass did, for the run that produced it.
function appr_tick_reconciled() {
    $r = $GLOBALS['__appr_tick_unarmed'] ?? null;
    if (!is_array($r) || ($r['epoch'] ?? -1) !== (function_exists('db_epoch') ? db_epoch() : 0))
        return ['recovered' => 0, 'terminal' => 0, 'still' => 0];
    return is_array($r['rc'] ?? null) ? $r['rc'] : ['recovered' => 0, 'terminal' => 0, 'still' => 0];
}

//  §20 — never report a send that did not happen. ops_mail() already records
//  every attempt in email_log with its error; this is the same truth in words,
//  on the activity timeline.
function appr_delivery_note($recipients, $delivered) {
    $recipients = (int) $recipients; $delivered = (int) $delivered;
    if ($recipients === 0) return 'NO RECIPIENT — nobody was notified';
    $who = $recipients . ' recipient' . ($recipients === 1 ? '' : 's');
    if ($delivered === 0) return $who . ' — not delivered (mail is not configured, or the send failed)';
    if ($delivered < $recipients) return $who . ' — ' . $delivered . ' delivered';
    return 'notified ' . $who;
}

// ---- Email helpers ---------------------------------------------------------
//  M3 CORRECTION · F1 — appr_role_emails() IS GONE.
//
//  It answered "every active holder of this role, anywhere", and treating that
//  answer as notification eligibility is precisely what leaked one branch's hiring
//  request to another's. Nothing calls it now, and leaving it in place would be
//  leaving the defect within arm's reach of the next person who needs a recipient
//  list. Recipients come from appr_step_recipients() and appr_email_escalate(),
//  both of which end at the existing approval model.
//  Returns how many of the attempted sends were actually delivered. It still
//  never throws: a notification failure must not break an approval (§39).
function appr_mail($to, $subject, $body) {
    $ok = 0;
    if (!$to || !function_exists('ops_mail')) return 0;
    foreach ((array)$to as $addr) if ($addr) {
        try { $ok += ops_mail($addr, $subject, $body, '', 'recruit_approval') ? 1 : 0; } catch (Throwable $e) {}
    }
    return $ok;
}

//  Phase 3 · M3 CORRECTION · F1 — WHO MAY BE TOLD.
//
//  The adversarial audit proved the notification layer contradicting the approval
//  layer. A role-based step wrote to EVERY holder of that role in EVERY branch —
//  including the person M1 and M2 deliberately hide the request from and refuse at
//  the engine, and including the requestor whom segregation will never let
//  approve. E-mail leaves the application, so that is a disclosure, and M3's
//  reminders made it repeat on a schedule.
//
//  The rule is one sentence:
//
//      NOTIFICATION ELIGIBILITY IS NEVER BROADER THAN APPROVAL VISIBILITY.
//
//  It is enforced by ASKING THE EXISTING MODEL, never by a second one:
//  appr_visible() → appr_can_act() + appr_guard(), the same chain the queue and
//  the decision already use. Nothing here re-implements entitlement, scope,
//  segregation or delegation; it only asks them about somebody else.
//
//  And that is the difficulty: appr_guard() reads the CURRENT user — entitlement,
//  branch scope and segregation all do. Answering "may this OTHER person be told"
//  means asking the question AS them. appr_as_user() does exactly that and puts
//  the session back in a `finally`, so no caller can leave the session somewhere
//  it should not be, not even on an exception.
function appr_as_user($user, callable $fn) {
    $uid = (int) ($user['id'] ?? 0);
    if ($uid <= 0) return null;
    $prev = array_key_exists('uid', $_SESSION ?? []) ? $_SESSION['uid'] : null;
    $_SESSION['uid'] = $uid;
    if (function_exists('current_user')) current_user(true);
    if (function_exists('ua')) ua(true);
    try { return $fn(); }
    finally {
        if ($prev === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prev;
        if (function_exists('current_user')) current_user(true);
        if (function_exists('ua')) ua(true);
    }
}

//  ACTIONABLE — "this is waiting for your decision". The strictest test there is,
//  and it is exactly the queue's: appr_visible() = appr_can_act() AND appr_guard().
//  If the person would not see it in /my-approvals, they are not written to.
function appr_may_be_asked($step, $req, $user) {
    //  E1 — the SAME common gate the informational level uses, so an offer, a
    //  salary structure and a requisition cannot reach "eligible to be asked"
    //  without their module either. E2 — and the result is accepted only when it
    //  is literally true, never by casting whatever came back.
    if (appr_notify_gate($req, $user) !== '') return false;
    return appr_as_user($user, fn() => appr_visible($step, $req, $user)) === true;
}

//  M3 CORRECTION #3 · D3 — WHY a notification did not go out, said accurately.
//
//  The previous correction logged every failure as "no canonical requester
//  identity", including the cases where the identity resolved perfectly and the
//  person was simply not eligible. Two different things — "we do not know who" and
//  "we know who, and may not tell them" — recorded as one. These are the distinct
//  reasons, and they are the vocabulary the notifier reports and the audit stores.
const APPR_NOTIFY_REASONS = [
    'SENT'                 => 'Notification sent',
    'ENTITY_UNRESOLVED'    => 'the record could not be resolved',
    'IDENTITY_UNRESOLVED'  => 'the record carries no canonical raiser identity',
    'TENANT_MISMATCH'      => 'the identity does not exist in this workspace',
    'RECIPIENT_INACTIVE'   => 'the recipient is no longer an active user',
    'RECIPIENT_UNLICENSED' => 'the module is not licensed for this workspace',
    'RECIPIENT_OUT_OF_SCOPE' => 'the recipient is outside the branch scope of the record',
    'RECIPIENT_NOT_VISIBLE'  => 'the recipient may not see this record',
    'SEGREGATION_BLOCKED'    => 'segregation of duties forbids it',
    'NO_EMAIL'             => 'the recipient has no e-mail address',
    'PROVIDER_FAILURE'     => 'the mail provider could not deliver it',
];

//  M3 CORRECTION #5 · G1 — A SUPPORTED TYPE IS NOT A RESOLVED RECORD.
//
//  appr_visible() asked whether the entity TYPE was known and then resolved the
//  RECORD for the hiring request alone, so a step whose offer, salary structure
//  or requisition had been deleted still produced recipients. C2's rule —
//  "unknown, missing or invalid entity must DENY" — had its unknown-type half
//  implemented for every entity and its missing-record half for one.
//
//  This is the one place the source record is resolved for notification, for all
//  four. It REUSES the existing resolvers where they exist (hreq_get, offer_get)
//  and reads the source table directly where they do not, rather than inventing a
//  second entity framework.
//
//  Tenant safety needs no clause: this application keeps ONE DATABASE PER TENANT
//  and no tenant_id column, so a record belonging to another workspace is not in
//  this database and an id cannot resolve to it. That is asserted behaviourally
//  rather than assumed.
//
//  A resolution that ERRORS is a resolution that FAILED — the catch denies.
const APPR_ENTITY_SOURCE = [
    'HIRING_REQUEST' => 'hiring_requests',
    'OFFER'          => 'job_offers',
    'SALARY'         => 'salary_structures',
    'REQUISITION'    => 'requisitions',
];
function appr_entity_record($entity, $id) {
    $entity = strtoupper(trim((string) $entity));
    $id = (int) $id;
    if ($id <= 0 || !array_key_exists($entity, APPR_ENTITY_SOURCE)) return null;
    try {
        if ($entity === 'HIRING_REQUEST')
            return function_exists('hreq_get') ? (hreq_get($id) ?: null) : null;
        if (function_exists('recruit_offer_migrate') && ($entity === 'OFFER' || $entity === 'SALARY'))
            recruit_offer_migrate();
        if ($entity === 'OFFER')
            return function_exists('offer_get') ? (offer_get($id) ?: null) : null;
        if ($entity === 'SALARY')
            return ops_one("SELECT * FROM salary_structures WHERE id=?", [$id]) ?: null;
        return ops_one("SELECT * FROM requisitions WHERE id=?", [$id]) ?: null;
    } catch (Throwable $e) { return null; }
}

//  M3 CORRECTION #4 · E1 — WHICH MODULE EACH APPROVAL ENTITY BELONGS TO.
//
//  Written down rather than assumed, so the entitlement question can be asked for
//  every entity in one place and a future entity cannot quietly arrive without
//  one. All four approval entities are recruitment: an offer, a salary structure,
//  a requisition and a hiring request are all People & hiring records.
const APPR_ENTITY_MODULE = [
    'HIRING_REQUEST' => 'mod.hiring.view',
    'REQUISITION'    => 'mod.hiring.view',
    'OFFER'          => 'mod.hiring.view',
    'SALARY'         => 'mod.hiring.view',
];

//  M3 CORRECTION #4 · E1 + E2 — THE COMMON GATE.
//
//  Every notification, actionable or informational, passes this BEFORE any
//  entity-specific question is asked. The audit found the entitlement check
//  living inside the hiring-request branch, so an offer, a salary structure and a
//  requisition reached "eligible" having never been asked — and correction #3,
//  by restoring their notifications, made that live. A notification leaves the
//  application, so it is the last boundary where "entitlement first" can still be
//  true.
//
//      1 · a VALID SECURITY SUBJECT        (E2)
//      2 · a KNOWN, SUPPORTED ENTITY
//      3 · the APPLICABLE MODULE ENTITLEMENT
//
//  Entity-specific visibility and scope come after, and can only narrow.
//
//  E2 — an unusable subject is denied by an EXPLICIT test, never by a cast.
//  appr_as_user() returns null for an id it cannot use, `(string) null` is '',
//  and '' is this function's word for "eligible": a type conversion was deciding
//  a security question. It is decided here instead, and the helper's result is
//  accepted only when it is genuinely a string.
function appr_notify_gate($req, $user) {
    if (!is_array($user)) return 'IDENTITY_UNRESOLVED';
    if ((int) ($user['id'] ?? 0) <= 0) return 'IDENTITY_UNRESOLVED';
    if ((int) ($user['is_active'] ?? 0) !== 1) return 'RECIPIENT_INACTIVE';
    $entity = strtoupper(trim((string) ($req['entity'] ?? '')));
    if ($entity === '' || !array_key_exists($entity, APPR_ENTITIES)) return 'ENTITY_UNRESOLVED';
    $module = APPR_ENTITY_MODULE[$entity] ?? 'mod.hiring.view';
    $r = appr_as_user($user, fn() => (function_exists('licence_blocks') && licence_blocks($module))
        ? 'RECIPIENT_UNLICENSED' : '');
    return is_string($r) ? $r : 'IDENTITY_UNRESOLVED';
}

//  The reason a person may NOT be told, or '' when they may. appr_may_be_told()
//  is this same rule read as a yes/no — one rule, two readers, never two rules.
function appr_told_reason($req, $user) {
    $why = appr_notify_gate($req, $user);          // subject · entity · entitlement
    if ($why !== '') return $why;
    $entity = strtoupper(trim((string) ($req['entity'] ?? '')));
    //  G1 — informational is not a bypass. The source record must resolve here
    //  too, for every entity, before anything else is considered.
    $rec = appr_entity_record($entity, $req['entity_id'] ?? 0);
    if (!$rec) return 'ENTITY_UNRESOLVED';
    if ($entity !== 'HIRING_REQUEST') return '';
    $r = appr_as_user($user, function () use ($rec) {
        if (function_exists('hreq_in_scope') && !hreq_in_scope($rec)) return 'RECIPIENT_OUT_OF_SCOPE';
        return '';
    });
    return is_string($r) ? $r : 'IDENTITY_UNRESOLVED';
}

//  INFORMATIONAL — "this approval is late". Not a request to act, so the single
//  clause that does not apply is SEGREGATION: telling the person who raised a
//  request that it has gone overdue is the point of the message, not a leak.
//  Everything else is the same guard asked the same way — entitlement, the record
//  existing, branch scope — through the same helpers appr_guard() itself calls.
//  It is a second disclosure LEVEL, not a second rule set.
function appr_may_be_told($req, $user) { return appr_told_reason($req, $user) === ''; }

//  Phase 3 · M3 §16 — WHO IS BEING ASKED TO ACT.
//
//  The inbox has honoured delegation since M2, but the e-mail did not: it went
//  to the named approver or the role and stopped there. So the queue said one
//  thing and the notification said another, and the person the system was
//  actually waiting for was never told. This adds the current delegates of
//  whoever the step names.
//
//  It grants nothing. appr_can_act() is still the only thing that decides who
//  may approve; a delegate appears here because M2 already gave them the
//  authority, and an address on an e-mail has never been an authority anywhere
//  in this application.
function appr_step_recipients($step, $req = null) {
    if (!array_key_exists('_entity', $step) && $req) $step = appr_step_context($step, $req);
    $req = $req ?: appr_request((int) ($step['request_id'] ?? 0));
    $entity = (string) ($step['_entity'] ?? ($req['entity'] ?? ''));
    $office = array_key_exists('_office_id', $step) ? $step['_office_id'] : null;

    //  1 · WHO THE STEP NAMES — one person, or the holders of a role. This is a
    //      CANDIDATE list, nothing more. Holding a role is not eligibility; it was
    //      treating it as eligibility that caused the leak.
    $cand = [];
    $add = function ($u) use (&$cand) {
        if (is_array($u) && (int) ($u['is_active'] ?? 0) === 1) $cand[(int) $u['id']] = $u;
    };
    $uid = (int) ($step['approver_user_id'] ?? 0);
    if ($uid > 0) {
        try { $add(ops_one("SELECT * FROM users WHERE id=?", [$uid])); } catch (Throwable $e) {}
    } else {
        $role = (string) ($step['approver_role'] ?? '');
        if ($role !== '') {
            try { foreach (ops_all("SELECT * FROM users WHERE role=? AND is_active=1", [$role]) as $u) $add($u); }
            catch (Throwable $e) {}
        }
    }
    //  2 · PLUS whoever is currently acting for them. M2's delegation rules decide
    //      that, unchanged — dates, entity, branch and delegator active status.
    foreach (array_keys($cand) as $pid) {
        foreach (appr_delegates_of($pid, $entity, $office) as $dg) {
            try { $add(ops_one("SELECT * FROM users WHERE id=?", [(int) $dg])); } catch (Throwable $e) {}
        }
    }
    //  3 · AND THEN THE EXISTING MODEL DECIDES. Every candidate — named, role
    //      holder or delegate alike — is asked the same question /my-approvals
    //      asks. No path skips it, because the audit showed a path that did.
    $out = [];
    foreach ($cand as $u) {
        $e = trim((string) ($u['email'] ?? ''));
        if ($e === '') continue;
        if (!appr_may_be_asked($step, $req, $u)) continue;
        $out[] = $e;
    }
    return array_values(array_unique($out));
}

//  Returns [recipients, delivered] so the caller can audit what really happened.
function appr_email_approver($step, $req, $kind) {
    $to = appr_step_recipients($step, $req);
    $sub = ($kind === 'reminder' ? 'Reminder: ' : '') . 'Approval needed — ' . ($req['subject'] ?? $req['entity'] ?? 'item');
    $body = '<p>An approval is awaiting your action' . ($kind === 'reminder' ? ' (reminder)' : '') . ':</p>'
        . '<p><b>' . e((string)($req['subject'] ?? '')) . '</b>' . (isset($step['label']) && $step['label'] ? ' — ' . e($step['label']) : '') . '</p>'
        . '<p>Open <b>My approvals</b> in the app to approve or reject.</p>';
    return [count($to), appr_mail($to, $sub, $body)];
}

//  §13 + §15 — ESCALATION IS VISIBILITY, NOT AUTHORITY.
//
//  Being told an approval is late does not make the recipient an approver. This
//  function sends an e-mail and returns; it writes to no approver field, and the
//  authority engine has never read the escalation columns. A manager who is told
//  can approve only if appr_can_act() independently says so.
function appr_email_escalate($step, $req = null) {
    $req = $req ?: appr_request((int) ($step['request_id'] ?? 0));
    //  F1 — an escalation contact is a CANDIDATE too. Being named on a policy, or
    //  holding an escalation role, or being an administrator is not on its own a
    //  licence to be told what another branch is hiring for.
    $cand = [];
    $add = function ($u) use (&$cand) {
        if (is_array($u) && (int) ($u['is_active'] ?? 0) === 1 && trim((string) ($u['email'] ?? '')) !== '')
            $cand[(int) $u['id']] = $u;
    };
    $euid = (int) ($step['escalate_user_id'] ?? 0);
    $erole = (string) ($step['escalate_role'] ?? '');
    try {
        if ($euid > 0) $add(ops_one("SELECT * FROM users WHERE id=?", [$euid]));
        elseif ($erole !== '') foreach (ops_all("SELECT * FROM users WHERE role=? AND is_active=1", [$erole]) as $u) $add($u);
        else foreach (ops_all("SELECT * FROM users WHERE role IN ('MASTER_ADMIN','ADMIN','BRANCH_MANAGER','SBU_HEAD') AND is_active=1") as $u) $add($u);
    } catch (Throwable $e) {}
    //  The INFORMATIONAL disclosure level: entitlement and branch scope, without
    //  segregation — being told a request is late is not being asked to approve it,
    //  so the person who raised it may legitimately hear about it.
    $to = [];
    foreach ($cand as $u) if (appr_may_be_told($req, $u)) $to[] = trim((string) $u['email']);
    $to = array_values(array_unique($to));
    $late = appr_sla_days_late($step);
    $body = '<p>An approval step has passed its SLA and needs attention:</p><p><b>' . e((string)($step['subject'] ?? '')) . '</b> — level ' . (int)$step['seq']
        . ($late > 0 ? ' — overdue by ' . $late . ' day' . ($late === 1 ? '' : 's') : '') . '</p>'
        . '<p>This is a notification. It does not give you authority to approve — the approver named on the step still owns the decision.</p>';
    return [count($to), appr_mail($to, 'ESCALATION: approval overdue — ' . ($step['subject'] ?? ''), $body)];
}
//  Phase 3 · M3 CORRECTION #3 · D1 — IDENTITY, THEN ELIGIBILITY, THEN NOTIFICATION.
//
//  Three questions, asked in that order and never conflated. Correction #2 fixed
//  the identity question for the hiring request and, by failing closed everywhere
//  else, silently stopped a working notification for the other entities. Both
//  halves are now answered properly.
//
//  RESOLUTION ORDER — strictest first, and a NAME IS NEVER CONSULTED:
//    1. the BUSINESS OBJECT's own canonical raiser id — hiring_requests.
//       requested_by_id (M4), job_offers.created_by_id, salary_structures.
//       created_by_id. "Who created the thing", which is what identity means, and
//       it survives the chain being restarted.
//    2. the CHAIN's requester_id — the authenticated user at appr_start(). One
//       column covering every entity, including requisitions, whose business
//       object carries no raiser id and which no code path in this application
//       ever starts a chain for.
//    3. nothing at all → FAIL CLOSED, with a reason.
function appr_requester_id($req) {
    $entity = strtoupper(trim((string) ($req['entity'] ?? '')));
    $eid = (int) ($req['entity_id'] ?? 0);
    //  G1 — one resolver, the same one the notification predicates use, instead of
    //  an inline query per entity. A record that does not resolve is
    //  ENTITY_UNRESOLVED and never falls through to the chain.
    $rec = appr_entity_record($entity, $eid);
    if (!$rec) return [0, 'ENTITY_UNRESOLVED'];
    $fromEntity = 0;
    if ($entity === 'HIRING_REQUEST')             $fromEntity = (int) ($rec['requested_by_id'] ?? 0);
    elseif ($entity === 'OFFER' || $entity === 'SALARY') $fromEntity = (int) ($rec['created_by_id'] ?? 0);
    if ($fromEntity > 0) return [$fromEntity, ''];
    $fromChain = (int) ($req['requester_id'] ?? 0);
    if ($fromChain > 0) return [$fromChain, ''];
    return [0, 'IDENTITY_UNRESOLVED'];          // a legacy row. Do not guess.
}

//  [user|null, reason]. TENANT_MISMATCH is what an id from somewhere else looks
//  like here: one database per tenant, so the row simply is not there.
function appr_resolve_requester($req) {
    [$uid, $why] = appr_requester_id($req);
    if ($uid <= 0) return [null, $why !== '' ? $why : 'IDENTITY_UNRESOLVED'];
    try { $u = ops_one("SELECT * FROM users WHERE id=?", [$uid]); } catch (Throwable $e) { $u = null; }
    if (!$u) return [null, 'TENANT_MISMATCH'];
    return [$u, ''];
}
function appr_requester_user($req) { [$u] = appr_resolve_requester($req); return $u; }

//  M3 CORRECTION #3 · D2 — AN AUDIT ROW ONLY WHEN IT MEANS SOMETHING AND POINTS
//  AT SOMETHING.
//
//  Correction #2 wrote "could not identify the requester" on EVERY offer decision,
//  with a blank entity_kind and a dangling entity_id — a permanent structural
//  condition logged as if it were an event, in rows nobody could follow.
//
//    · a SUCCESSFUL send is already in email_log — nothing is added here
//    · a DELIVERY failure is already in email_log, with its error
//    · a BLOCKED or UNRESOLVED notification is recorded here, with its REAL
//      reason (D3), and only for an entity the timeline can actually link
//    · OFFER and SALARY are not ACT_ENTITIES, so nothing is written for them
//      rather than something invalid. That limitation is documented, which is
//      what the brief asks for in place of fabricated audit data.
//  Which reasons earn a row, and which do not. Anything routine — it was sent,
//  the mailer could not deliver it (already in email_log with its error), or the
//  person simply has no e-mail address — is NOT an event. Writing one per decision
//  for a benign configuration state is the same noise D2 was raised about, and an
//  existing M1 assertion caught me doing exactly that with NO_EMAIL.
const APPR_NOTIFY_AUDITED = ['ENTITY_UNRESOLVED', 'IDENTITY_UNRESOLVED', 'TENANT_MISMATCH',
    'RECIPIENT_INACTIVE', 'RECIPIENT_UNLICENSED', 'RECIPIENT_OUT_OF_SCOPE',
    'RECIPIENT_NOT_VISIBLE', 'SEGREGATION_BLOCKED'];
//  Y2 — returns its own outcome, for the same reason as appr_audit_sla().
function appr_audit_notify($req, $result, $reason) {
    if (!in_array((string) $reason, APPR_NOTIFY_AUDITED, true)) return APPR_COND_NONE;
    if (!function_exists('act_log') || !defined('ACT_ENTITIES')) return APPR_COND_NO_SUBJECT;
    $entity = strtoupper(trim((string) ($req['entity'] ?? '')));
    $label = (defined('APPR_ENTITIES') && isset(APPR_ENTITIES[$entity])) ? APPR_ENTITIES[$entity] : $entity;
    //  J1 — the type check that used to stand here called itself "never a
    //  dangling reference" while the commonest reason for writing was that the
    //  record is gone. The five clauses now live in appr_audit_subject().
    //  §3/§6 — K1: correction #6 gave offer and salary a subject that opens, and
    //  with it the ability to repeat. The identical permanent condition on the
    //  identical chain is a STATE that is already on the record.
    $condKey = appr_condition_key('DECISION', $req, null, $reason);
    $gate = appr_cond_gate($condKey);                                // Z3 — same gate
    if ($gate === 'SUPPRESS') return APPR_COND_SUPPRESSED;
    if ($gate === 'RETRY')    return appr_cond_retry($condKey);
    [$kind, $id, $isSource] = appr_audit_subject($req);
    if ($kind === '') return APPR_COND_NO_SUBJECT;  // nothing openable remains: no row
    act_log($kind, $id, 'SYSTEM',
        'Decision not notified (' . $reason . ') — ' . (APPR_NOTIFY_REASONS[$reason] ?? $reason)
        . ' — ' . $label . ' #' . (int) ($req['entity_id'] ?? 0) . ' ' . strtoupper((string) $result)
        . ((!$isSource && appr_audit_source_gone($req)) ? ' — source record unavailable' : ''),
        ['auto' => 1, 'outcome' => substr($reason, 0, 60), 'cond_key' => $condKey,
         'body' => appr_cond_fingerprint($condKey)]);                // Z3 — see appr_audit_sla
    return appr_cond_outcome($condKey);          // Y2 — the status is consumed, not dropped
}

//  B-1 — the decision notifier's own consumption of the condition outcome. It is
//  workspace-keyed and readable, so the outcome ends somewhere that can be seen
//  rather than in an unused return value.
function appr_email_cond_note($outcome) {
    $GLOBALS['__appr_last_cond'] = ['epoch' => function_exists('db_epoch') ? db_epoch() : 0,
                                    'o' => (string) $outcome];
    return (string) $outcome;
}
function appr_last_cond_outcome() {
    $r = $GLOBALS['__appr_last_cond'] ?? null;
    if (!is_array($r) || ($r['epoch'] ?? -1) !== (function_exists('db_epoch') ? db_epoch() : 0)) return '';
    return (string) $r['o'];
}

//  Returns the reason code, so a caller — and a test — can see exactly which of
//  the three questions failed rather than inferring it from an empty inbox.
function appr_email_requester($req, $result, $remarks = '') {
    [$u, $why] = appr_resolve_requester($req);          // 1 · IDENTITY
    if (!$u) { appr_email_cond_note(appr_audit_notify($req, $result, $why)); return $why; }
    $why = appr_told_reason($req, $u);                  // 2 · ELIGIBILITY
    if ($why !== '') { appr_email_cond_note(appr_audit_notify($req, $result, $why)); return $why; }
    $email = trim((string) ($u['email'] ?? ''));
    if ($email === '') { appr_email_cond_note(appr_audit_notify($req, $result, 'NO_EMAIL')); return 'NO_EMAIL'; }
    $sent = appr_mail([$email], 'Your approval was ' . strtoupper($result) . ' — ' . ($req['subject'] ?? ''),
        '<p>Your request <b>' . e((string)($req['subject'] ?? '')) . '</b> was <b>' . e(strtoupper($result)) . '</b>.' . ($remarks ? ' Remark: ' . e($remarks) : '') . '</p>');
    // A provider failure is already recorded in email_log with its error, so it is
    // reported here and not duplicated onto the timeline.
    return $sent >= 1 ? 'SENT' : 'PROVIDER_FAILURE';    // 3 · NOTIFICATION
}


// ============================================================================
//  Admin screen — configure rules + levels
// ============================================================================
function ops_recruit_approvals($route, $method) {
    ops_require(hiring_admin_can(), 'Only an administrator can configure approval rules.');
    appr_migrate();
    if ($method === 'POST') {
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'rule_save') { $id = appr_rule_save((int)($_POST['id'] ?? 0), $_POST); flash('Rule saved.'); redirect('/recruit-approvals?id=' . $id); return true; }
        if ($do === 'rule_toggle') { $r = appr_rule((int)($_POST['id'] ?? 0)); if ($r) appr_rule_set_active($r['id'], (int)$r['active'] === 0); flash('Rule updated.'); redirect('/recruit-approvals'); return true; }
        if ($do === 'level_save') { appr_level_save($_POST); flash('Level saved.'); redirect('/recruit-approvals?id=' . (int)($_POST['rule_id'] ?? 0)); return true; }
        if ($do === 'level_delete') { appr_level_delete((int)($_POST['level_id'] ?? 0)); flash('Level removed.'); redirect('/recruit-approvals?id=' . (int)($_POST['rule_id'] ?? 0)); return true; }
    }
    $selId = (int)($_GET['id'] ?? 0);
    $sel = $selId ? appr_rule($selId) : null;
    // M2 — what the administrator needs to see to trust the configuration: which
    // levels nobody can action, and whether this rule ties with another.
    $orphans = $sel ? appr_orphan_levels($sel['id']) : [];
    $ties = [];
    if ($sel) {
        foreach (appr_rules($sel['entity'], true) as $other) {
            if ((int) $other['id'] === (int) $sel['id']) continue;
            if ((int) $other['sort'] === (int) $sel['sort']
                && (string) $other['applies_department'] === (string) $sel['applies_department']
                && (string) $other['applies_sbu'] === (string) $sel['applies_sbu']
                && (string) $other['applies_grade'] === (string) $sel['applies_grade']
                && (string) $other['applies_position'] === (string) $sel['applies_position']
                && (int) ($other['applies_office_id'] ?? 0) === (int) ($sel['applies_office_id'] ?? 0)) $ties[] = $other;
        }
    }
    view('ops/approval_rules', [
        'rules'  => appr_rules(null, false),
        'sel'    => $sel,
        'levels' => $sel ? appr_levels($sel['id']) : [],
        'roles'  => function_exists('roles_for_licence') ? roles_for_licence() : ORG_ROLES,
        'entities' => APPR_ENTITIES,
        'orphans'  => $orphans,
        'ties'     => $ties,
        'offices'  => function_exists('offices_list') ? offices_list() : [],
        'preview'  => (isset($_GET['pv']) && $sel) ? appr_preview($sel['entity'], [
            'department'  => (string) ($_GET['pv_department'] ?? ''),
            'sbu'         => (string) ($_GET['pv_sbu'] ?? ''),
            'grade'       => (string) ($_GET['pv_grade'] ?? ''),
            'position'    => (string) ($_GET['pv_position'] ?? ''),
            'office_id'   => (int) ($_GET['pv_office_id'] ?? 0),
            'amount'      => (float) ($_GET['pv_amount'] ?? 0),
        ]) : null,
    ]);
    return true;
}

//  Delegation administration. Same gate as the rest of approval configuration —
//  moving approval authority is at least as privileged as writing the rule that
//  demands it.
function ops_approval_delegations($route, $method) {
    ops_require(hiring_admin_can(), 'Only an administrator can configure approval delegation.');
    appr_migrate();
    if ($method === 'POST') {
        $do = (string) ($_POST['do'] ?? '');
        if ($do === 'save') {
            [$ok, $msg] = appr_delegation_save((int) ($_POST['id'] ?? 0), $_POST);
            flash($msg, $ok ? 'success' : 'error');
        } elseif ($do === 'revoke') {
            [$ok, $msg] = appr_delegation_revoke((int) ($_POST['id'] ?? 0));
            flash($msg, $ok ? 'success' : 'error');
        }
        redirect('/approval-delegations'); return true;
    }
    view('ops/approval_delegations', [
        'rows'     => appr_delegations(false),
        'people'   => function_exists('rcc_users') ? rcc_users() : ops_all("SELECT id, first_name, last_name, username FROM users WHERE is_active=1 ORDER BY first_name"),
        'offices'  => function_exists('offices_list') ? offices_list() : [],
        'entities' => APPR_ENTITIES,
    ]);
    return true;
}

// My approvals inbox + act.
function ops_my_approvals($route, $method) {
    appr_migrate();
    ops_require(function_exists('current_user') && current_user(), 'Sign in.');
    // M1 FINDING A. This route is in neither ops_module_gate()'s route map nor
    // ops_module_family()'s prefix table, so NO module question was ever asked —
    // the recruitment approval inbox opened on a workspace that had not bought
    // recruitment (proved with a probe).
    //
    // What is asked here is the LICENCE, not mod.hiring.view. Entitlement is the
    // tenant's contract; capability is the person's role. Requiring the hiring
    // permission would lock out a configured approver who legitimately holds no
    // recruitment module — a Finance approver on an offer chain, say — and
    // narrowing the approver population is a policy change M1 was not asked for.
    if (function_exists('licence_blocks') && licence_blocks('mod.hiring.view')) {
        if (function_exists('access_deny')) access_deny('hiring');
        ops_require(false, 'The recruitment module is not switched on for this installation.');
    }
    if ($method === 'POST') {
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'act') { [$ok, $m] = appr_act((int)($_POST['step_id'] ?? 0), (string)($_POST['decision'] ?? 'approve'), $_POST['remarks'] ?? ''); flash($m, $ok ? 'success' : 'error'); }
        redirect('/my-approvals'); return true;
    }
    view('ops/my_approvals', ['inbox' => appr_inbox(), 'waiting' => appr_waiting_on_others()]);
    return true;
}
