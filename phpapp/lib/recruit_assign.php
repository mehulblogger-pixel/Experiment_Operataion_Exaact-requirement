<?php
// ============================================================================
//  PHASE 3 · M5 — RECRUITER ACCOUNTABILITY
//
//  ONE DOOR for every change of recruiter / manager ownership.
//
//  The M5 audit (docs/phase3/M5-ACTION-PATH-MATRIX.md) found five paths able to
//  write the ownership columns and NOT ONE of them validated what it wrote. The
//  screen offered a dropdown of active users; the save cast whatever arrived to
//  an integer and stored it. A hidden dropdown is not a control — so this file
//  exists, and the controls live at the WRITE rather than on the screen.
//
//  Everything a change of ownership must satisfy is asked here, in this order,
//  and every answer fails CLOSED:
//
//      entitlement  →  permission  →  the record  →  the actor's scope
//                   →  the state   →  the M4 execution boundary
//                   →  the recruiter exists, is active, and is in scope
//                   →  the screen was not stale   →  the compare-and-swap
//
//  Nothing here grants a permission. It asks exactly what the recruitment write
//  routes already ask (`is_coordinator_level()`, `docs/02-permission-matrix.md`
//  note 8) and adds the questions nobody was asking.
// ============================================================================

//  The three ownership columns, named once. A subject is a (table, column) pair
//  because "recruiter" means a different column on a different table, and a
//  control that guessed would eventually guess wrong.
const RASG_SUBJECTS = [
    'REQ_RECRUITER'  => ['table' => 'requisitions', 'col' => 'recruiter_id', 'what' => 'recruiter',
                         'entity' => 'REQUISITION', 'label' => 'Responsible 1 — recruiter'],
    'REQ_MANAGER'    => ['table' => 'requisitions', 'col' => 'manager_id',   'what' => 'manager',
                         'entity' => 'REQUISITION', 'label' => 'Responsible 2 — manager'],
    'CAND_RECRUITER' => ['table' => 'candidates',   'col' => 'recruiter_id', 'what' => 'recruiter',
                         'entity' => 'CANDIDATE',   'label' => 'Recruiter chasing this candidate'],
];

//  The requisition states in which accountability may still be MOVED. A finished
//  or abandoned requirement keeps the name of whoever carried it: reassigning it
//  would rewrite history, which is exactly what invariant I12 forbids.
const RASG_ASSIGNABLE_REQ = ['OPEN', 'PROPOSED', 'OFFERED', 'PARTIALLY_FILLED'];

//  The states in which a requisition is still LIVE DEMAND. One definition, used
//  by the workload counter AND by the command centre, so a number on a dashboard
//  and the same number counted from the records can never drift apart.
//  PARTIALLY_FILLED is here deliberately — see M5-RECONCILIATION-RESULTS.md.
const RASG_LIVE_REQ = ['OPEN', 'PROPOSED', 'OFFERED', 'PARTIALLY_FILLED', 'HIRED'];

//  A candidate whose outcome is settled is not live work either.
const RASG_CAND_TERMINAL = ['REJECTED', 'WITHDRAWN', 'OFFER_DECLINED'];

//  Every refusal has a code, so a test can assert WHICH control refused rather
//  than merely that something did. A test that accepts any refusal passes for the
//  wrong reason; that lesson cost this project two corrections.
const RASG_CODES = [
    'OK'                     => 'the assignment was made',
    'NO_CHANGE'              => 'the named person already holds it — nothing to do',
    'NO_SUBJECT'             => 'no such ownership field',
    'NO_ENTITLEMENT'         => 'this workspace does not hold the Recruitment module',
    'NO_PERMISSION'          => 'you may not change recruitment ownership',
    'NO_RECORD'              => 'that record does not exist',
    'OUT_OF_SCOPE'           => 'that record is outside your office / branch scope',
    'BAD_STATE'              => 'ownership cannot be moved in this state',
    'M4_BLOCKED'             => 'the hiring request behind this requisition is awaiting re-approval',
    'RECRUITER_UNKNOWN'      => 'that person does not exist in this workspace',
    'RECRUITER_INACTIVE'     => 'that person is deactivated and cannot be given new work',
    'RECRUITER_OUT_OF_SCOPE' => 'that person does not cover this office / branch',
    'BAD_VALUE'              => 'that is not a person',
    'STALE'                  => 'somebody changed the owner while this screen was open',
    'LOST_RACE'              => 'somebody else changed the owner at the same moment',
];

//  WHAT COUNTS AS A PERSON ID, asked before anything is cast.
//
//  An adversarial probe posted `recruiter_id[]` — an array — and PHP's cast
//  turned it into the integer 1, so the door dutifully validated "user #1" and
//  assigned the work to whoever that is. The same cast turns the string "abc"
//  into 0, which the door reads as *unassign*: a typo, or a crafted field, would
//  silently remove the owner.
//
//  So the value is examined before it is cast. Only three things are a legitimate
//  answer to "who?": nothing at all (unassign), a run of digits, or an integer.
//  Everything else is refused as BAD_VALUE rather than coerced into an identity.
//  A person is never inferred from a type conversion.
function rasg_person_id($v, &$ok) {
    $ok = true;
    if ($v === null || $v === '' || $v === 0 || $v === '0') return null;
    if (is_int($v)) return $v > 0 ? $v : null;
    if (is_string($v) && ctype_digit($v)) { $n = (int) $v; return $n > 0 ? $n : null; }
    $ok = false;                       // arrays, objects, floats, "abc", "7x"
    return null;
}

function rasg_now() { return function_exists('now_iso') ? now_iso() : date('c'); }
function rasg_who() {
    if (!function_exists('current_user')) return '';
    $u = current_user(); if (!$u) return '';
    return function_exists('user_name') ? (string) user_name($u) : (string) ($u['username'] ?? '');
}
function rasg_actor_id() {
    if (!function_exists('current_user')) return null;
    $u = current_user(); return $u ? (int) ($u['id'] ?? 0) ?: null : null;
}

//  The append-only ownership ledger. It answers "who used to be accountable?" —
//  a question the columns themselves cannot answer, because a column holds one
//  value and an overwrite destroys the previous one (invariants I4 and I12).
function rasg_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    $pk = (function_exists('db_driver') && db_driver() === 'mysql')
        ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS recruiter_assignments (
            id $pk,
            subject VARCHAR(20) DEFAULT '',        -- which ownership field
            entity_id INT NULL,                    -- the requisition / candidate
            from_user_id INT NULL,                 -- who held it (NULL = nobody)
            to_user_id INT NULL,                   -- who holds it now (NULL = unassigned)
            actor VARCHAR(150) DEFAULT '',         -- display name, never an identity
            actor_id INT NULL,                     -- the identity
            source VARCHAR(30) DEFAULT '',         -- which production path
            reason VARCHAR(255) DEFAULT '',
            created_at VARCHAR(30) DEFAULT ''
        )");
    } catch (Throwable $e) { return; }
    //  Both lookups this table serves: one record's history, and one person's.
    try { db()->exec("CREATE INDEX idx_rasg_entity ON recruiter_assignments (subject, entity_id, id)"); } catch (Throwable $e) {}
    try { db()->exec("CREATE INDEX idx_rasg_to ON recruiter_assignments (to_user_id)"); } catch (Throwable $e) {}
}

// ---- The people -------------------------------------------------------------

//  A user row read from THIS database. Multi-tenancy here is structural — one
//  database per tenant — so a user id from another workspace simply is not here,
//  and "not here" must mean refused rather than written (invariant I1).
function rasg_user($uid) {
    $uid = (int) $uid; if ($uid <= 0) return null;
    try { return db()->query("SELECT * FROM users WHERE id=" . $uid)->fetch() ?: null; }
    catch (Throwable $e) { return null; }
}

//  Is this person allowed to be given the work? Existence and activity only —
//  scope is a question about the RECORD, asked separately below.
function rasg_recruiter_state($uid) {
    $u = rasg_user($uid);
    if (!$u) return 'RECRUITER_UNKNOWN';
    //  Deactivation is recorded in two places in this codebase (`is_active` and
    //  `deactivated_at`); either one means no new work. Reading only one of them
    //  is how a deactivated person keeps receiving candidates.
    if (isset($u['is_active']) && !(int) $u['is_active']) return 'RECRUITER_INACTIVE';
    if (trim((string) ($u['deactivated_at'] ?? '')) !== '') return 'RECRUITER_INACTIVE';
    return 'OK';
}

//  Does THIS person's own office / Business-Unit scope cover the record?
//
//  The rule lives in ua() + scope_allows(); it is not restated here. It is
//  evaluated AS that person through the M3 helper appr_as_user(), which swaps the
//  session, refreshes the caches and restores both in a finally. Restating the
//  rule would let the two copies drift, and a scope rule that drifts is a leak.
function rasg_user_covers($uid, $officeId, $sbu) {
    $u = rasg_user($uid); if (!$u) return false;
    if (!function_exists('appr_as_user') || !function_exists('scope_allows')) return false;  // fail closed
    $v = appr_as_user($u, fn() => scope_allows($officeId, $sbu) === true);
    return $v === true;
}

// ---- The record -------------------------------------------------------------

function rasg_row($subject, $id) {
    $s = RASG_SUBJECTS[$subject] ?? null; if (!$s) return null;
    $id = (int) $id; if ($id <= 0) return null;
    try { return db()->query("SELECT * FROM {$s['table']} WHERE id=" . $id)->fetch() ?: null; }
    catch (Throwable $e) { return null; }
}

//  The office / Business Unit the record belongs to. A candidate has no branch of
//  its own; it inherits the one from the requisition it is being worked against,
//  exactly as cand_scope_gate() already does for reading.
//  $opt['requisition_id'] — the requirement the candidate is being moved ONTO in
//  this same save, when there is one.
//
//  Without it the scope question is asked about where the candidate IS while the
//  save is about to move it somewhere else. An adversarial probe did exactly
//  that: one POST set a branch-A recruiter (checked against branch A, allowed)
//  and moved the candidate to a branch-B requirement, leaving a branch-A-only
//  recruiter accountable for branch-B work. The rule is the same one M4 learned —
//  ask the question about the record the save is actually producing.
function rasg_row_scope($subject, $row, array $opt = []) {
    $s = RASG_SUBJECTS[$subject] ?? null;
    if (!$s) return [null, null];
    if ($s['entity'] === 'REQUISITION') return [$row['office_id'] ?? null, $row['sbu'] ?? null];
    $rq = array_key_exists('requisition_id', $opt)
        ? (int) $opt['requisition_id']                     // where it is GOING
        : (int) ($row['requisition_id'] ?? 0);             // where it is now
    if ($rq > 0) {
        try { $r = db()->query("SELECT office_id, sbu FROM requisitions WHERE id=" . $rq)->fetch(); }
        catch (Throwable $e) { $r = null; }
        if ($r) return [$r['office_id'] ?? null, $r['sbu'] ?? null];
    }
    return [null, (string) ($row['sbu'] ?? '')];
}

//  May ownership move on this record at all, given what state it is in?
function rasg_state_ok($subject, $row, &$code) {
    $s = RASG_SUBJECTS[$subject];
    if ($s['entity'] === 'REQUISITION') {
        $st = strtoupper(trim((string) ($row['status'] ?? '')));
        if ($st === '') { $code = 'BAD_STATE'; return false; }          // unreadable state → closed
        if (!in_array($st, RASG_ASSIGNABLE_REQ, true)) { $code = 'BAD_STATE'; return false; }
        return true;
    }
    $stage = strtoupper(trim((string) ($row['stage'] ?? '')));
    if (in_array($stage, RASG_CAND_TERMINAL, true)) { $code = 'BAD_STATE'; return false; }
    return true;
}

//  The M4 boundary, asked about the requisition behind the record. Ownership is
//  accountability, not execution — but a requisition whose approval has been
//  invalidated is frozen, and M5 does not become the way around that (I8).
function rasg_m4_block($subject, $row, array $opt = []) {
    if (!function_exists('hreq_req_block_reason')) return '';
    $s = RASG_SUBJECTS[$subject];
    $rq = $s['entity'] === 'REQUISITION' ? (int) ($row['id'] ?? 0)
        : (array_key_exists('requisition_id', $opt) ? (int) $opt['requisition_id'] : (int) ($row['requisition_id'] ?? 0));
    if ($rq <= 0) return '';
    return (string) hreq_req_block_reason($rq);
}

// ---- The one door -----------------------------------------------------------

//  May the current actor move this ownership to this person? Returns a code from
//  RASG_CODES; 'OK' and 'NO_CHANGE' are the only permitting answers.
//
//  This is a pure question — it writes nothing — so a screen may ask it to decide
//  what to offer. The screen asking it is a convenience; rasg_assign() asks it
//  again at the write, which is what actually protects anything.
//  MAY THIS CALLER TOUCH THIS RECORD AT ALL? — asked about the person asking,
//  not about the change they want. It is separated from the change questions
//  below because ORDER LEAKS: while the stale-screen test ran first, a caller
//  with no permission could tell a wrong baseline (`STALE`) from a right one
//  (`NO_PERMISSION`) and so read off the current owner one guess at a time. A
//  refusal must never be a way of asking a question you are not allowed to ask.
function rasg_may_touch($subject, $id, array $opt = []) {
    $s = RASG_SUBJECTS[$subject] ?? null;
    if (!$s) return 'NO_SUBJECT';

    //  1 · ENTITLEMENT FIRST, and no master bypass. A workspace that does not
    //  hold Recruitment cannot have recruitment accountability moved inside it,
    //  whoever is asking. This mirrors appr_guard() and appr_config_can().
    if (function_exists('licence_blocks') && licence_blocks('mod.hiring.view')) return 'NO_ENTITLEMENT';

    //  2 · PERMISSION — exactly the band the recruitment write routes already
    //  require. Nothing new is granted here and nothing is taken away.
    if (empty($opt['skip_permission'])) {
        if (!function_exists('is_coordinator_level') || !is_coordinator_level()) return 'NO_PERMISSION';
    }

    //  3 · THE RECORD. An id is not proof that a record exists, and a missing
    //  record is refused rather than silently created.
    $row = is_array($opt['row'] ?? null) ? $opt['row'] : rasg_row($subject, $id);
    if (!$row) return 'NO_RECORD';

    //  4 · THE ACTOR'S OWN SCOPE over that record.
    [$off, $sbu] = rasg_row_scope($subject, $row, $opt);
    if (function_exists('scope_allows') && !scope_allows($off, $sbu)) return 'OUT_OF_SCOPE';
    return 'OK';
}

function rasg_check($subject, $id, $toUserId, array $opt = []) {
    $gate = rasg_may_touch($subject, $id, $opt);
    if ($gate !== 'OK') return $gate;
    $s = RASG_SUBJECTS[$subject];
    $row = is_array($opt['row'] ?? null) ? $opt['row'] : rasg_row($subject, $id);
    [$off, $sbu] = rasg_row_scope($subject, $row, $opt);

    //  5 · THE STATE.
    $code = '';
    if (!rasg_state_ok($subject, $row, $code)) return $code;

    //  6 · THE M4 EXECUTION BOUNDARY.
    if (rasg_m4_block($subject, $row, $opt) !== '') return 'M4_BLOCKED';

    //  7 · THE PERSON. Unassignment (NULL) has no person to check — removing
    //  accountability is always permitted to someone who passed 1–6, because the
    //  alternative is work stuck to somebody who has left.
    $to = rasg_person_id($toUserId, $vOk);
    if (!$vOk) return 'BAD_VALUE';
    if ($to !== null) {
        $rs = rasg_recruiter_state($to);
        if ($rs !== 'OK') return $rs;
        if (!rasg_user_covers($to, $off, $sbu)) return 'RECRUITER_OUT_OF_SCOPE';
    }
    return 'OK';
}

//  THE ONLY WRITER. Every production path that changes ownership calls this.
//
//  $opt['expect']  the value the screen was showing (the TOCTOU baseline).
//                  Pass RASG_NO_BASELINE only where there cannot be one — a row
//                  being created, which has no previous owner by definition.
//  $opt['source']  which production path (recorded, for the matrix).
//  $opt['reason']  free text for the ledger.
function rasg_assign($subject, $id, $toUserId, array $opt = []) {
    rasg_migrate();
    $s = RASG_SUBJECTS[$subject] ?? null;
    $fail = fn($c) => ['ok' => false, 'code' => $c, 'reason' => RASG_CODES[$c] ?? $c, 'from' => null, 'to' => null];
    if (!$s) return $fail('NO_SUBJECT');

    $row = rasg_row($subject, $id);
    if (!$row) return $fail('NO_RECORD');
    $from = ($row[$s['col']] ?? null) === null ? null : (int) $row[$s['col']];
    $to   = rasg_person_id($toUserId, $vOk);
    if (!$vOk) return $fail('BAD_VALUE');

    //  AUTHORIZATION FIRST — before the no-change answer and before the stale
    //  answer, because both of those are answers ABOUT THE CURRENT OWNER, and a
    //  caller who may not touch this record must not be able to read it off by
    //  watching which refusal comes back.
    $gate = rasg_may_touch($subject, $id, ['row' => $row] + $opt);
    if ($gate !== 'OK') return $fail($gate);

    //  Then the stale-screen test: a screen that was showing a different owner is
    //  refused outright, never merged. "Last write wins" is how the earlier of two
    //  managers silently loses their decision.
    if (array_key_exists('expect', $opt)) {
        $exp = rasg_person_id($opt['expect'], $eOk);
        if (!$eOk) return $fail('BAD_VALUE');
        if ($exp !== $from) return $fail('STALE');
    }

    //  Asking for what is already true is not a change, and is not audited as
    //  one. It is also not refused by the state or the boundary — nothing moves.
    if ($from === $to) return ['ok' => true, 'code' => 'NO_CHANGE', 'reason' => RASG_CODES['NO_CHANGE'], 'from' => $from, 'to' => $to];

    $code = rasg_check($subject, $id, $to, ['row' => $row] + $opt);
    if ($code !== 'OK') return $fail($code);

    //  COMPARE AND SWAP. Two processes reassigning the same requisition at the
    //  same moment both pass the checks above — check-then-write is not atomic,
    //  which is exactly how M4's last-seat race allocated eleven people against
    //  an approved ten. Only the process whose expected value is still in the
    //  column may write, so one wins and the other is told it lost.
    $sql = "UPDATE {$s['table']} SET {$s['col']}=? WHERE id=? AND "
         . ($from === null ? "{$s['col']} IS NULL" : "{$s['col']}=" . (int) $from);
    try { $st = db()->prepare($sql); $st->execute([$to, (int) $id]); $touched = (int) $st->rowCount(); }
    catch (Throwable $e) { return $fail('LOST_RACE'); }

    //  DID *THIS* PROCESS WRITE IT? Both halves of that question are asked, and
    //  the first half was missing until a five-way race proved it:
    //
    //  Reading the column back and finding the value you wanted does NOT mean you
    //  wrote it. Five processes contending for one unowned requisition included
    //  two asking for the SAME person; the loser's compare-and-swap matched no
    //  row, it then read the column, saw the name it had asked for — put there by
    //  the winner — and reported a successful assignment. Two winners, two ledger
    //  rows, one actual move. A test that only asserted the final owner would have
    //  called that correct.
    //
    //  So the matched-row count is asked first. rowCount() is treated carefully
    //  because MariaDB reports 0 for an UPDATE that sets a column to the value it
    //  already holds — but that case is impossible here: $from === $to returned
    //  NO_CHANGE several lines above, so any row this statement matches is a row
    //  it genuinely changed. Zero matched rows therefore means one thing only:
    //  somebody else got there first.
    if ($touched < 1) return $fail('LOST_RACE');
    //  And the read-back stays, because a matched row is still not proof the value
    //  survived — that is the lesson M3 Correction #12 paid for.
    $now = rasg_row($subject, $id);
    $cur = ($now[$s['col']] ?? null) === null ? null : (int) $now[$s['col']];
    if ($cur !== $to) return $fail('LOST_RACE');

    //  The ledger, then the audit. History first: if the activity write fails the
    //  ownership trail still exists, which is the one that must never be lost.
    try {
        db()->prepare("INSERT INTO recruiter_assignments
            (subject, entity_id, from_user_id, to_user_id, actor, actor_id, source, reason, created_at)
            VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$subject, (int) $id, $from, $to, rasg_who(), rasg_actor_id(),
                       (string) ($opt['source'] ?? ''), substr((string) ($opt['reason'] ?? ''), 0, 255), rasg_now()]);
    } catch (Throwable $e) { /* the column is already authoritative; the ledger is repaired by rasg_backfill() */ }

    if (function_exists('act_log')) {
        $name = fn($u) => $u === null ? 'nobody' : (function_exists('rcc_user_name') ? (rcc_user_name($u) ?: ('user #' . $u)) : ('user #' . $u));
        act_log($s['entity'] === 'REQUISITION' ? 'REQUISITION' : 'CANDIDATE', (int) $id, 'NOTE',
            ucfirst($s['what']) . ' changed: ' . $name($from) . ' → ' . $name($to),
            ['body' => (string) ($opt['reason'] ?? '')]);
    }
    return ['ok' => true, 'code' => 'OK', 'reason' => RASG_CODES['OK'], 'from' => $from, 'to' => $to];
}

//  The history of one record's ownership (I4 / I12). Newest first.
function rasg_history($subject, $id) {
    rasg_migrate();
    try {
        $st = db()->prepare("SELECT * FROM recruiter_assignments WHERE subject=? AND entity_id=? ORDER BY id DESC");
        $st->execute([(string) $subject, (int) $id]);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) { return []; }
}

//  Did this person ever hold this record? Historical attribution survives every
//  later reassignment, which is the whole point of the ledger.
function rasg_ever_held($subject, $id, $uid) {
    foreach (rasg_history($subject, $id) as $h)
        if ((int) ($h['from_user_id'] ?? 0) === (int) $uid || (int) ($h['to_user_id'] ?? 0) === (int) $uid) return true;
    return false;
}

// ---- Ownership as it is applied by a save -----------------------------------

//  A form save posts every field at once. The ownership columns are taken OUT of
//  that blind list and come through here, so that:
//    · a value that did not change is never treated as an assignment;
//    · a value that DID change is refused as a whole rather than half-applied;
//    · and the refusal is a business sentence, not a silent no-op.
//
//  Returns '' when the save may proceed, or the refusal to show the person.
function rasg_guard_change($subject, $id, $posted, array $opt = []) {
    $s = RASG_SUBJECTS[$subject] ?? null; if (!$s) return '';
    $row = rasg_row($subject, $id); if (!$row) return '';       // creation: nothing to compare yet
    $from = ($row[$s['col']] ?? null) === null ? null : (int) $row[$s['col']];
    $to = rasg_person_id($posted, $vOk);
    if (!$vOk) return rasg_refusal($subject, 'BAD_VALUE');
    if ($from === $to) return '';                                // not a change
    $code = rasg_check($subject, $id, $to, ['row' => $row] + $opt);
    if ($code === 'OK') return '';
    return rasg_refusal($subject, $code);
}

//  What a FORM SAVE does with one ownership field. Both edit paths call this, so
//  "create" and "edit" cannot drift apart — the create-vs-edit gap is how the M4
//  audit found a candidate being attached to a blocked requisition one screen
//  later, and it is not repeated here.
//
//  Returns '' when the save may proceed, or the sentence to show the person.
function rasg_apply_posted($subject, $id, array $post, $col, $source = '', array $opt = []) {
    //  A form that does not carry the field changes nothing. Before M5 an absent
    //  field was written as NULL, so a POST that merely omitted the dropdown
    //  quietly UNASSIGNED the requirement. Silence is not an instruction.
    if (!array_key_exists($col, $post)) return '';
    $s = RASG_SUBJECTS[$subject] ?? null; if (!$s) return '';
    $row = rasg_row($subject, $id); if (!$row) return '';
    $from = ($row[$s['col']] ?? null) === null ? null : (int) $row[$s['col']];
    $to   = rasg_person_id($post[$col], $vOk);
    //  An array, an object or "abc" is not a person and is not silently cast into
    //  one — nor into 0, which would read as "unassign".
    if (!$vOk) return rasg_refusal($subject, 'BAD_VALUE');
    if ($from === $to && !array_key_exists('requisition_id', $opt)) return '';   // not a change

    //  THE STALE-SCREEN TEST. The form carries the owner it was showing. If the
    //  column has moved since, this save is acting on something it never saw, and
    //  is refused rather than merged. A crafted POST that omits the baseline is
    //  refused too: no baseline, no overwrite.
    $baseKey = 'own_base_' . $col;
    if (!array_key_exists($baseKey, $post)) return rasg_refusal($subject, 'STALE');

    $r = rasg_assign($subject, $id, $to, ['expect' => $post[$baseKey], 'source' => $source] + $opt);
    return $r['ok'] ? '' : rasg_refusal($subject, $r['code']);
}

//  DEFENCE IN DEPTH — the compensating check after a form save.
//
//  Everything above assumes the save path asks the door. A mutation test proved
//  that assumption is not self-enforcing: putting the column back into a blind
//  field list ("$fields[] = 'recruiter_id';") restored the old behaviour and no
//  probe noticed, because the probes were reading the field list's LITERAL text
//  while the mutation appended to it at runtime.
//
//  So the form paths no longer trust themselves. After the rest of a save has
//  been written, this asks the column what it now holds, and anything other than
//  what the door authorised is reverted and audited. It is the same shape as M4's
//  headcount compensator, and it closes the class rather than the instance: a
//  save path invented next year that quietly carries an ownership column cannot
//  change ownership, whether or not anybody remembers this rule.
//
//  The ownership a table's record legitimately holds RIGHT NOW, snapshotted from
//  the service's own list of subjects rather than from anything the caller keeps.
//  A save path takes this after the door has run and before it writes the rest;
//  whatever it finds afterwards must match, or it was not an authorised change.
//
//  Reading the subject list here rather than accepting one from the caller is
//  deliberate: a caller that forgets a column, or is edited to forget one, would
//  otherwise silently exclude it from the protection.
function rasg_authorised_now($table, $id) {
    $out = [];
    foreach (RASG_SUBJECTS as $k => $s) {
        if ($s['table'] !== $table) continue;
        $row = rasg_row($k, $id);
        $out[$k] = $row && ($row[$s['col']] ?? null) !== null ? (int) $row[$s['col']] : null;
    }
    return $out;
}

//  "Nobody owns anything yet" for a freshly created record, expressed through the
//  same subject list, so a create path cannot miss a column either.
function rasg_unowned($table) {
    $out = [];
    foreach (RASG_SUBJECTS as $k => $s) if ($s['table'] === $table) $out[$k] = null;
    return $out;
}

//  Restore every ownership column of one record to the snapshot above.
function rasg_enforce_table($table, $id, array $authorised) {
    $said = [];
    foreach (RASG_SUBJECTS as $k => $s) {
        if ($s['table'] !== $table) continue;
        if (!array_key_exists($k, $authorised)) continue;
        $why = rasg_enforce_after_write($k, $id, $authorised[$k]);
        if ($why !== '') $said[] = $why;
    }
    return $said;
}

//  Returns '' when the column was already correct, or the sentence to show.
function rasg_enforce_after_write($subject, $id, $authorised) {
    $s = RASG_SUBJECTS[$subject] ?? null; if (!$s) return '';
    $row = rasg_row($subject, $id); if (!$row) return '';
    $cur = ($row[$s['col']] ?? null) === null ? null : (int) $row[$s['col']];
    $want = rasg_person_id($authorised, $wOk);
    if (!$wOk) $want = null;                    // an unreadable snapshot means nobody, never a guess
    if ($cur === $want) return '';

    try { db()->prepare("UPDATE {$s['table']} SET {$s['col']}=? WHERE id=?")->execute([$want, (int) $id]); }
    catch (Throwable $e) { return rasg_refusal($subject, 'LOST_RACE'); }
    if (function_exists('act_log')) {
        act_log($s['entity'] === 'REQUISITION' ? 'REQUISITION' : 'CANDIDATE', (int) $id, 'NOTE',
            'Unauthorised ' . $s['what'] . ' change reverted',
            ['body' => 'A save wrote this ownership column without passing the assignment control. '
                     . 'It has been put back to the authorised value.']);
    }
    return 'The ' . $s['what'] . ' was not changed — an ownership change must go through the assignment control.';
}

//  MOVING THE WORK, not the owner. A save can carry a candidate to a different
//  requirement without touching the recruiter's name. If the destination is in a
//  branch that recruiter does not cover, that is still a change of accountability
//  — the same question, arriving through the other field — and it is refused
//  rather than quietly creating cross-branch ownership.
//
//  Returns '' when the move is fine, or the sentence to show.
function rasg_move_blocks($subject, $id, array $opt = []) {
    if (!array_key_exists('requisition_id', $opt)) return '';
    $s = RASG_SUBJECTS[$subject] ?? null; if (!$s || $s['entity'] !== 'CANDIDATE') return '';
    $row = rasg_row($subject, $id); if (!$row) return '';
    $holder = ($row[$s['col']] ?? null) === null ? null : (int) $row[$s['col']];
    if ($holder === null) return '';                       // nobody to move
    $wasRq = (int) ($row['requisition_id'] ?? 0);
    $nowRq = (int) $opt['requisition_id'];
    if ($wasRq === $nowRq) return '';                      // not a move
    [$off, $sbu] = rasg_row_scope($subject, $row, $opt);   // the DESTINATION
    if (rasg_user_covers($holder, $off, $sbu)) return '';
    $who = function_exists('rcc_user_name') ? (rcc_user_name($holder) ?: ('user #' . $holder)) : ('user #' . $holder);
    return 'This candidate cannot be moved to that requirement while ' . $who
         . ' is the recruiter — they do not cover that office / branch. Change the recruiter in the same save.';
}

//  INHERITANCE, not a decision. A public careers application creates a candidate
//  against an advertised requisition and copies that requisition's recruiter, so
//  the CV lands on the right desk. There is no signed-in person to hold a
//  permission, so the permission question is skipped explicitly — and ONLY that
//  one. Entitlement, the record, the state, the M4 boundary and every question
//  about the person being given the work are all still asked, which is what stops
//  a public form from handing work to somebody who has left the company.
function rasg_inherit_from_requisition($candidateId, $requisitionId, $source = 'careers') {
    $rq = rasg_row('REQ_RECRUITER', $requisitionId);
    $to = $rq && ($rq['recruiter_id'] ?? null) !== null ? (int) $rq['recruiter_id'] : null;
    if ($to === null) return ['ok' => true, 'code' => 'NO_CHANGE', 'reason' => 'the requirement has no recruiter to inherit', 'from' => null, 'to' => null];
    return rasg_assign('CAND_RECRUITER', $candidateId, $to,
        ['expect' => null, 'skip_permission' => true, 'source' => $source,
         'reason' => 'inherited from requisition #' . (int) $requisitionId]);
}

//  The refusal in the words the person on the screen needs.
function rasg_refusal($subject, $code) {
    $s = RASG_SUBJECTS[$subject] ?? null;
    $what = $s ? $s['what'] : 'owner';
    $tail = RASG_CODES[$code] ?? 'this change is not allowed';
    return 'The ' . $what . ' could not be changed — ' . $tail . '.';
}

// ---- Workload, counted once -------------------------------------------------

//  THE CANDIDATE SCOPE RULE, DEFINED ONCE.
//
//  M5 shipped with two of them. The command centre scoped candidates through the
//  requirement they are worked against with scope_office_clause() — whose "no
//  office means everybody" rule keeps an unattached candidate visible — while the
//  workload counter used scope_clause(), under which no requirement means
//  Ahmedabad. A candidate with no requirement was therefore counted on the
//  dashboard and NOT in its own recruiter's workload: two numbers about the same
//  person, disagreeing, which is the exact defect this milestone exists to
//  remove. It survived the reconciliation suite only because every fixture
//  candidate happened to have a requirement.
//
//  So the rule lives here, once, and recruit_cc.php reads it. Two consumers, one
//  definition, no room to drift.
function rasg_cand_scope($alias = 'c') {
    $w = []; $a = [];
    if (function_exists('scope_office_clause')) {
        [$ow, $oa] = scope_office_clause("(SELECT r2.office_id FROM requisitions r2 WHERE r2.id=$alias.requisition_id)");
        if ($ow !== '1=1') { $w[] = $ow; $a = array_merge($a, $oa); }
    }
    if (function_exists('recruit_sbu_clause')) {
        [$sw, $sa] = recruit_sbu_clause("$alias.sbu");
        if ($sw !== '1=1') { $w[] = $sw; $a = array_merge($a, $sa); }
    }
    return [$w ? implode(' AND ', $w) : '1=1', $a];
}

//  THE counter behind every recruiter number. The command centre and the tests
//  read this same function, so a dashboard figure and the records underneath it
//  cannot disagree (invariant I5). Every count is scope-filtered exactly as the
//  register the person can open is.
function rasg_workload($uid, array $opt = []) {
    $uid  = (int) $uid;
    $live = "'" . implode("','", RASG_LIVE_REQ) . "'";
    $term = "'" . implode("','", RASG_CAND_TERMINAL) . "'";
    //  The SAME scope rule the registers use. A candidate has no branch of its
    //  own, so it is scoped through the requisition it is worked against —
    //  exactly as cand_scope_gate() scopes the screen that shows it.
    [$sc, $sa] = (function_exists('scope_clause') && empty($opt['no_scope']))
        ? scope_clause('r.office_id', 'r.sbu') : ['1=1', []];
    $n = function ($sql, $args) { try { return (int) ops_val($sql, $args); } catch (Throwable $e) { return 0; } };

    //  Requisitions: this person is Responsible 1.
    $req  = "FROM requisitions r WHERE $sc AND r.recruiter_id=$uid";
    //  Candidates use the ONE candidate scope rule, the same one the command
    //  centre reads — not the requisition rule, which would lose every candidate
    //  that belongs to no requirement.
    [$cc, $ca] = empty($opt['no_scope']) ? rasg_cand_scope('c') : ['1=1', []];
    $cand = "FROM candidates c LEFT JOIN requisitions r ON r.id=c.requisition_id WHERE $cc AND c.recruiter_id=$uid";

    $out = [];
    $out['assigned_requisitions'] = $n("SELECT COUNT(*) $req", $sa);
    $out['active_requisitions']   = $n("SELECT COUNT(*) $req AND r.status IN ($live)", $sa);
    $out['vacancies']             = $n("SELECT COALESCE(SUM(CASE WHEN r.quantity>0 THEN r.quantity ELSE 1 END),0) $req AND r.status IN ($live)", $sa);
    $out['filled']                = $n("SELECT COUNT(*) FROM candidates c2 JOIN requisitions r ON r.id=c2.requisition_id
                                        WHERE $sc AND r.recruiter_id=$uid AND r.status IN ($live) AND c2.stage='ACCEPTED'", $sa);
    $out['open_seats']            = max(0, $out['vacancies'] - $out['filled']);
    $out['candidates']            = $n("SELECT COUNT(*) $cand", $ca);
    $out['active_candidates']     = $n("SELECT COUNT(*) $cand AND c.stage NOT IN ($term)", $ca);
    $out['offers']                = $n("SELECT COUNT(*) $cand AND c.stage='OFFERED'", $ca);
    $out['joins']                 = $n("SELECT COUNT(*) $cand AND c.stage='ACCEPTED'", $ca);
    //  Interviews arranged for the people this person is chasing. Counted from
    //  the interviews table itself, never from a stage label — a candidate can sit
    //  several rounds, and "has reached the interview stage" is a different number
    //  from "interviews arranged", which is exactly the kind of quiet mismatch a
    //  reconciliation is for.
    $out['interviews'] = $n("SELECT COUNT(*) FROM interviews i
                             JOIN candidates c ON c.id=i.candidate_id
                             LEFT JOIN requisitions r ON r.id=c.requisition_id
                             WHERE $cc AND c.recruiter_id=$uid", $ca);
    //  Work that has been sitting still. "Overdue" is not a new status — it is the
    //  ageing rule the command centre already shows, asked per recruiter.
    $cut = date('Y-m-d', strtotime('-' . (int) ($opt['overdue_days'] ?? 30) . ' days'));
    $out['overdue']               = $n("SELECT COUNT(*) $cand AND c.stage NOT IN ($term) AND c.stage<>'ACCEPTED'
                                        AND substr(COALESCE(NULLIF(c.cv_received_date,''),c.created_at),1,10) < " . db()->quote($cut), $ca);
    return $out;
}

//  Requisitions and candidates nobody is accountable for, in the caller's scope.
//  Unassigned is a legitimate state — it is never invented into an owner — but it
//  must be VISIBLE, or work quietly belongs to nobody (invariant I7).
function rasg_unassigned(array $opt = []) {
    $live = "'" . implode("','", RASG_LIVE_REQ) . "'";
    $term = "'" . implode("','", RASG_CAND_TERMINAL) . "'";
    [$rw, $ra] = (function_exists('scope_clause') && empty($opt['no_scope']))
        ? scope_clause('r.office_id', 'r.sbu') : ['1=1', []];
    [$cw, $ca] = empty($opt['no_scope']) ? rasg_cand_scope('c') : ['1=1', []];
    $n = function ($sql, $args) { try { return (int) ops_val($sql, $args); } catch (Throwable $e) { return 0; } };
    return [
        'requisitions' => $n("SELECT COUNT(*) FROM requisitions r
                              WHERE $rw AND r.status IN ($live) AND COALESCE(r.recruiter_id,0)=0", $ra),
        'candidates'   => $n("SELECT COUNT(*) FROM candidates c LEFT JOIN requisitions r ON r.id=c.requisition_id
                              WHERE $cw AND c.stage NOT IN ($term) AND COALESCE(c.recruiter_id,0)=0", $ca),
    ];
}

//  Ownership pointing at somebody who is not a usable person any more: deleted,
//  or deactivated. These are the rows that show on a dashboard as "User #7".
//  Reported, never silently cleared — clearing it would destroy the evidence of
//  who was accountable.
function rasg_phantoms(array $opt = []) {
    $out = ['requisitions' => [], 'candidates' => []];
    $scan = function ($table, $col) {
        $bad = [];
        try {
            foreach (db()->query("SELECT id, $col uid FROM $table WHERE COALESCE($col,0)<>0") as $r) {
                $st = rasg_recruiter_state((int) $r['uid']);
                if ($st !== 'OK') $bad[] = ['id' => (int) $r['id'], 'user_id' => (int) $r['uid'], 'why' => $st];
            }
        } catch (Throwable $e) {}
        return $bad;
    };
    $out['requisitions'] = $scan('requisitions', 'recruiter_id');
    $out['candidates']   = $scan('candidates', 'recruiter_id');
    return $out;
}
