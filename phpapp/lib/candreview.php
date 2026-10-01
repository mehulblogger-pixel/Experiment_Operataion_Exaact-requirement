<?php
// ===========================================================================
//  GATE 3 — REVIEW REQUIRED
//
//  WHAT THIS IS, in business terms.
//
//  A manager raised a requirement, it was approved, recruiters went out and found
//  people against it. Then the requirement was changed — properly, through Gate
//  2's change control — and the new version asks for MORE than the old one. Five
//  experienced candidates were sourced against a three-year minimum; the approved
//  minimum is now eight years.
//
//  Those five people are still sitting in the process. Nothing has told anybody
//  that the thing they were sourced against no longer exists. Without this gate
//  they continue to be shortlisted, interviewed and offered against a bar nobody
//  has checked them against — and the business finds out at the worst possible
//  moment, when somebody has already been promised a job.
//
//  So: when a stricter approved version becomes effective, EVERY candidate still
//  actively in that process is put into REVIEW REQUIRED, and a human with the
//  authority has to say, per person, Continue or Reject — with a reason.
//
//  THE FIVE RULES THAT MAKE THIS HONEST
//
//  A1 · ALL of them. Not the ones a score thinks are weak. Every active candidate.
//       There is deliberately no automatic exemption anywhere in this file: no
//       score clears a review, no score creates one, no score prevents one. A
//       scoring rule that quietly excused the strongest candidate would be a rule
//       nobody reviewed, applied to the people it matters most for.
//
//  A2 · Only until an offer is ISSUED. Once the business has put a promise in
//       front of somebody, that promise was made against the requirement as it
//       stood. A later version does not retroactively move the goalposts under a
//       person who is holding an offer letter.
//
//  §2 · PER RELATIONSHIP, never per person. In this product a candidate ROW is one
//       application: one human against one requirement. A person applying to three
//       requirements is three rows, threaded by person_ref. So a review that
//       belongs to a row belongs, by construction, to exactly one requirement —
//       and changing R1 cannot reach R2. There is deliberately NO
//       candidates.review_required column: that would be the global flag §2
//       forbids, and it would make one person's review on one vacancy stop their
//       interview on another.
//
//  §18 · A RELAXED requirement does nothing. Nobody is reopened, no queue is
//       built, no rejection is revisited. Somebody who was turned down stays
//       turned down until a human deliberately reconsiders them.
//
//  §13 · SEEING IS NOT DECIDING. Plenty of people can legitimately see that a
//       review is open. Resolving it needs hiring.review.clear plus recruitment
//       scope, enforced in this file — not by hiding a button.
//
//  WHAT THIS FILE DOES NOT CONTAIN, deliberately:
//    · no version engine          — Gate 2 owns versions (rver_*)
//    · no comparison algorithm    — rver_strictness() extends Gate 2's own diff
//    · no approval engine         — a review rejection needs no approval (§11)
//    · no pipeline engine         — Gate 1B owns stages and kinds (rpipe_*)
//    · no audit engine            — candidate_events, through rkpi_stage_log()
//    · no second rejection state  — the configured closed off-ramp (§16/§17)
//    · no action-policy engine    — REXEC_ACTIONS, through rexec_block_reason()
//    · no candidate office column — scope derives through the requirement (§23)
// ===========================================================================

//  The three statuses a review can hold, and nothing else. A review is OPEN until
//  a human decides; then it is closed one of two ways, for ever.
const CREV_STATUSES = [
    'OPEN'      => 'Review required',
    'CONTINUED' => 'Reviewed — continuing',
    'REJECTED'  => 'Reviewed — rejected',
];

//  WHICH DIRECTIONS OF CHANGE RAISE A REVIEW.
//
//  'stricter' is A1 and is NOT removable — it is the locked rule this gate exists
//  to enforce, and an organisation that could switch it off would be an
//  organisation where the control silently does not run.
//
//  'redefined' is removable. It covers a change that is not a raise but makes the
//  people already in the process the wrong people: the role became Electrician
//  where it said Welder, or the branch moved from Mumbai to Dubai. Five welders
//  attached to an electrician vacancy is the same class of silent wrongness as an
//  unapproved headcount, so it ships ON — but an organisation that genuinely
//  re-labels roles without changing what it wants can turn it off.
//
//  'relaxed' can never be added. §18 is absolute: lowering the bar reopens nobody.
//  The permission that decides a review (§12). Named as a constant so the code and
//  the catalogue cannot drift apart.
const CREV_PERM_CLEAR = 'hiring.review.clear';

const CREV_TRIGGER_ALWAYS = ['stricter'];
const CREV_TRIGGER_OPTIONAL = ['redefined'];

//  R1-UI (Gate 6B) — ONE PLACE THAT BUILDS THE SETTING KEY.
//
//  The key was previously assembled inline here and nowhere else, which was fine
//  while nothing else needed it. Now that the Approval Rules screen reads and
//  writes it, a second hand-typed 'crev_trigger_redefined' would be a second
//  definition of the same thing — the defect class this programme keeps removing.
//  The screen and the handler both go through this function.
function crev_trigger_key($t) { return 'crev_trigger_' . strtolower(trim((string) $t)); }

function crev_triggers() {
    $on = CREV_TRIGGER_ALWAYS;
    foreach (CREV_TRIGGER_OPTIONAL as $t) {
        $v = function_exists('setting_get') ? setting_get(crev_trigger_key($t), '1') : '1';
        if ((string) $v !== '0') $on[] = $t;
    }
    return $on;
}

//  Is this trigger currently on? Mandatory triggers always answer true, so a
//  caller cannot accidentally render 'stricter' as something switchable.
function crev_trigger_on($t) {
    $t = strtolower(trim((string) $t));
    if (in_array($t, CREV_TRIGGER_ALWAYS, true)) return true;
    return in_array($t, crev_triggers(), true);
}

//  What the configuration screen needs, so the view asks the engine rather than
//  the settings table. 'mandatory' is returned as well, so the screen can SHOW
//  that 'stricter' exists and is not negotiable instead of silently omitting it.
function crev_trigger_state() {
    $opt = [];
    foreach (CREV_TRIGGER_OPTIONAL as $t) $opt[$t] = crev_trigger_on($t);
    return ['mandatory' => CREV_TRIGGER_ALWAYS, 'optional' => $opt];
}

function crev_now() { return function_exists('now_iso') ? now_iso() : date('c'); }
function crev_who() { return function_exists('user_name') ? (string) user_name(current_user()) : 'system'; }
function crev_who_id() { return function_exists('current_user') && ($u = current_user()) ? (int) ($u['id'] ?? 0) : 0; }

// ---------------------------------------------------------------------------
//  SCHEMA — additive, idempotent, non-destructive, tenant-local (§30)
// ---------------------------------------------------------------------------
function crev_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    if (!function_exists('ensure_column')) return;
    //  The same two helpers every other migration in this product uses, so the two
    //  engines are told apart in one place rather than in each file's own way.
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS candidate_reviews (
            id $pk,
            candidate_id INT DEFAULT 0,
            --  The process the review belongs to, captured when it was raised. A
            --  candidate can later be reallocated to a different requirement; the
            --  review must keep saying which process it was about rather than
            --  silently following them to a new one.
            requisition_id INT DEFAULT 0,
            --  WHAT changed: the requirement whose new version triggered this.
            entity VARCHAR(20) DEFAULT '',
            entity_id INT DEFAULT 0,
            from_version INT DEFAULT 0,
            to_version INT DEFAULT 0,
            trigger_kind VARCHAR(20) DEFAULT '',
            trigger_json TEXT,
            status VARCHAR(20) DEFAULT 'OPEN',
            --  One OPEN review per relationship, enforced by the database.
            open_key VARCHAR(80) DEFAULT NULL,
            raised_by VARCHAR(150) DEFAULT '',
            raised_at VARCHAR(30) DEFAULT '',
            resolved_by VARCHAR(150) DEFAULT '',
            resolved_by_id INT DEFAULT 0,
            resolved_at VARCHAR(30) DEFAULT '',
            resolve_reason VARCHAR(1000) DEFAULT '',
            resolved_stage_id INT DEFAULT 0,
            created_at VARCHAR(30) DEFAULT '')");
    } catch (Throwable $e) { /* never break boot */ }

    //  THE ONE-OPEN-REVIEW RULE, IN THE DATABASE.
    //
    //  The same trick Gate 2 proved portable for its one-pending proposal: a
    //  partial unique index would be the natural expression and MariaDB does not
    //  have them, so open_key carries the relationship's identity only while the
    //  review is OPEN and NULL once it is decided. NULLs are distinct in a unique
    //  index on BOTH engines, so any number of decided reviews coexist while a
    //  second OPEN one for the same relationship cannot be inserted.
    //
    //  This matters beyond tidiness: two open reviews for one relationship would
    //  be two questions where there is one decision to make, and a reviewer who
    //  answered one would leave the candidate blocked by the other.
    foreach (["CREATE UNIQUE INDEX idx_crev_open ON candidate_reviews (open_key)",
              "CREATE INDEX idx_crev_cand ON candidate_reviews (candidate_id, id)",
              "CREATE INDEX idx_crev_entity ON candidate_reviews (entity, entity_id)"] as $sql) {
        try { db()->exec($sql); } catch (Throwable $e) { /* already there */ }
    }
}

function crev_open_key($candidateId, $entity, $entityId) {
    return (int) $candidateId . ':' . strtoupper((string) $entity) . ':' . (int) $entityId;
}

// ---------------------------------------------------------------------------
//  WHO A NEW VERSION REACHES (A1 + A2 + §3)
// ---------------------------------------------------------------------------

//  Is this candidate STILL ACTIVELY IN the process?
//
//  Asked of Gate 1B's authority and of nothing else: pipeline -> stage -> kind ->
//  class. The legacy candidates.stage column is not consulted here; where a
//  candidate has no pipeline position, rpipe_current_state() already answers
//  through the documented one-way compatibility map, which is Gate 1B's business
//  and not a second opinion held here.
//
//  '' (the class could not be established) is NOT active. An unknown state is not
//  evidence of activity, and asserting a review against a candidate whose position
//  nobody can read would be guessing. Those rows are reported by the sweep instead
//  of being silently dropped.
function crev_is_active($cand) {
    if (!function_exists('rpipe_current_state')) return false;
    $st = rpipe_current_state($cand);
    return $st && (string) $st['class'] === 'ACTIVE';
}

//  Has this candidate crossed the A2 boundary on this requirement?
//
//  Answered by Gate 2's single answer to "which version applies to this person".
//  It returns the version STAMPED on their issued offer where there is one, with
//  the pre-Gate-2 timestamp fallback behind it. So a candidate whose applicable
//  version is older than the one that just became effective is holding a
//  commitment made against the requirement as it then stood — and a later version
//  does not reach them.
function crev_is_pinned($entity, $entityId, $candidateId, $newVersion) {
    if (!function_exists('rver_applicable_version')) return false;
    $v = (int) rver_applicable_version($entity, $entityId, (int) $candidateId);
    return $v > 0 && (int) $newVersion > 0 && $v < (int) $newVersion;
}

//  Every requisition a requirement change applies to.
//
//  A requisition change applies to itself. A HIRING REQUEST change applies to
//  every requisition raised from it (§22) — candidates attach to requisitions, so
//  that is the only way a hiring request's new version can reach a person. The
//  requisitions are NOT merged and their own version chains are untouched.
function crev_processes($entity, $entityId) {
    $e = strtoupper((string) $entity); $id = (int) $entityId;
    if ($e === 'REQUISITION') return $id > 0 ? [$id] : [];
    if ($e !== 'HIRING_REQUEST') return [];
    try { $rows = ops_all("SELECT id FROM requisitions WHERE hiring_request_id=? ORDER BY id", [$id]) ?: []; }
    catch (Throwable $e2) { return []; }
    return array_map(fn($r) => (int) $r['id'], $rows);
}

//  THE A1 POPULATION — who a stricter version must reach, and who it must not.
//
//  Returns ['reach' => [cand rows], 'pinned' => [...], 'inactive' => [...],
//           'unknown' => [...]] so the sweep can report what it did AND what it
//  deliberately left alone. Nothing here looks at a score, an experience year, a
//  qualification or a skill: suitability is not a condition of being reviewed.
function crev_audience($entity, $entityId, $newVersion) {
    crev_migrate();
    $out = ['reach' => [], 'pinned' => [], 'inactive' => [], 'unknown' => []];
    $procs = crev_processes($entity, $entityId);
    if (!$procs) return $out;
    $in = implode(',', array_map('intval', $procs));
    try { $rows = ops_all("SELECT * FROM candidates WHERE requisition_id IN ($in) ORDER BY id") ?: []; }
    catch (Throwable $e) { return $out; }
    foreach ($rows as $c) {
        $cid = (int) $c['id'];
        $st = function_exists('rpipe_current_state') ? rpipe_current_state($c) : null;
        $class = $st ? (string) $st['class'] : '';
        if ($class === '')       { $out['unknown'][]  = $c; continue; }
        if ($class !== 'ACTIVE') { $out['inactive'][] = $c; continue; }
        if (crev_is_pinned($entity, $entityId, $cid, $newVersion)) { $out['pinned'][] = $c; continue; }
        $out['reach'][] = $c;
    }
    return $out;
}

// ---------------------------------------------------------------------------
//  RAISING A REVIEW — the trigger (§5 / §6)
// ---------------------------------------------------------------------------

//  Called when a NEW APPROVED VERSION BECOMES EFFECTIVE. The direction of the
//  change is asked of Gate 2's extended comparison; this function never compares
//  anything itself.
//
//  Returns ['raised' => n, 'refreshed' => n, 'skipped' => ..., 'direction' => ...].
function crev_raise_for_version($entity, $entityId, $fromVersion, $toVersion) {
    crev_migrate();
    $e = strtoupper((string) $entity); $id = (int) $entityId;
    $to = (int) $toVersion; $from = (int) $fromVersion;
    $out = ['raised' => 0, 'refreshed' => 0, 'direction' => '', 'summary' => '',
            'pinned' => 0, 'inactive' => 0, 'unknown' => 0, 'triggered' => false];
    if ($to <= 0 || $from <= 0 || $from >= $to) return $out;      // nothing to compare
    if (!function_exists('rver_strictness_between')) return $out;

    $s = rver_strictness_between($e, $id, $from, $to);
    if (!$s) return $out;
    $out['summary'] = (string) $s['summary'];

    //  WHICH DIRECTION DECIDES. A change can be several things at once; the
    //  trigger is the strongest one the organisation has switched on, and
    //  'stricter' always wins because it is the locked rule.
    $trig = crev_triggers();
    $kind = '';
    if ($s['is_stricter'] && in_array('stricter', $trig, true)) $kind = 'stricter';
    elseif ($s['is_redefined'] && in_array('redefined', $trig, true)) $kind = 'redefined';
    $out['direction'] = $s['is_stricter'] ? 'stricter' : ($s['is_redefined'] ? 'redefined' : ($s['is_relaxed'] ? 'relaxed' : 'neither'));
    if ($kind === '') return $out;                  // §18 — relaxed, or nothing a candidate is judged by
    $out['triggered'] = true;

    $aud = crev_audience($e, $id, $to);
    $out['pinned']   = count($aud['pinned']);
    $out['inactive'] = count($aud['inactive']);
    $out['unknown']  = count($aud['unknown']);

    $detail = json_encode(['summary' => (string) $s['summary'], 'kind' => $kind,
        'stricter'  => array_keys($s['stricter']),
        'redefined' => array_keys($s['redefined']),
        'relaxed'   => array_keys($s['relaxed'])]);

    foreach ($aud['reach'] as $c) {
        $cid = (int) $c['id'];
        $existing = crev_open_for($cid, $e, $id);
        if ($existing) {
            //  SUCCESSIVE VERSIONS — one question, kept current.
            //
            //  A second stricter version while a review is still open does not
            //  create a second review: there is one decision to make and it must be
            //  made against the bar as it now stands. The open review is moved onto
            //  the newer version and the reviewer sees the latest requirement.
            //  Raising another row would leave the candidate blocked by a review
            //  whose answer nobody needed.
            try {
                db()->prepare("UPDATE candidate_reviews SET to_version=?, trigger_kind=?, trigger_json=?
                               WHERE id=? AND status='OPEN'")
                    ->execute([$to, $kind, $detail, (int) $existing['id']]);
                $out['refreshed']++;
                crev_log($cid, 'Review still required — requirement changed again to version ' . $to,
                         ['remark' => (string) $s['summary']]);
            } catch (Throwable $e2) {}
            continue;
        }
        try {
            db()->prepare("INSERT INTO candidate_reviews
                (candidate_id,requisition_id,entity,entity_id,from_version,to_version,
                 trigger_kind,trigger_json,status,open_key,raised_by,raised_at,created_at)
                VALUES (?,?,?,?,?,?,?,?,'OPEN',?,?,?,?)")
                ->execute([$cid, (int) ($c['requisition_id'] ?? 0), $e, $id, $from, $to,
                           $kind, $detail, crev_open_key($cid, $e, $id),
                           crev_who(), crev_now(), crev_now()]);
            $out['raised']++;
            crev_log($cid, 'Review required — the approved requirement changed to version ' . $to,
                     ['remark' => (string) $s['summary']]);
        } catch (Throwable $e2) {
            //  The unique index refused it, which means another process raised the
            //  same review in the same instant. That is the correct outcome, not an
            //  error: the relationship has its one open review.
            $out['refreshed']++;
        }
    }
    return $out;
}

// ---------------------------------------------------------------------------
//  READING REVIEWS
// ---------------------------------------------------------------------------

//  The OPEN review for one relationship, or null.
function crev_open_for($candidateId, $entity, $entityId) {
    crev_migrate();
    try {
        return ops_one("SELECT * FROM candidate_reviews
                        WHERE candidate_id=? AND entity=? AND entity_id=? AND status='OPEN'
                        ORDER BY id DESC LIMIT 1",
                       [(int) $candidateId, strtoupper((string) $entity), (int) $entityId]) ?: null;
    } catch (Throwable $e) { return null; }
}

//  EVERY open review on this candidate row.
//
//  A row is one application against one requirement, so in practice this is at
//  most two: the requisition's own change and a change to the hiring request it
//  was raised from. Both are about THIS relationship and both have to be answered
//  before this candidate moves — which is why they are read together rather than
//  one being allowed to mask the other.
function crev_open_all($candidateId) {
    crev_migrate();
    try {
        return ops_all("SELECT * FROM candidate_reviews WHERE candidate_id=? AND status='OPEN' ORDER BY id",
                       [(int) $candidateId]) ?: [];
    } catch (Throwable $e) { return []; }
}

function crev_review($id) {
    crev_migrate();
    try { return ops_one("SELECT * FROM candidate_reviews WHERE id=?", [(int) $id]) ?: null; }
    catch (Throwable $e) { return null; }
}

//  THE WHOLE HISTORY for a candidate row, open and decided, newest first. Never
//  filtered and never pruned: a refused review and the reason it was refused are
//  part of the permanent record (§25).
function crev_history($candidateId) {
    crev_migrate();
    try {
        return ops_all("SELECT * FROM candidate_reviews WHERE candidate_id=? ORDER BY id DESC",
                       [(int) $candidateId]) ?: [];
    } catch (Throwable $e) { return []; }
}

//  Open reviews across a requirement — what a coordinator has to work through.
function crev_open_on_process($requisitionId) {
    crev_migrate();
    try {
        return ops_all("SELECT cr.*, c.first_name, c.last_name, c.cand_code
                        FROM candidate_reviews cr
                        JOIN candidates c ON c.id = cr.candidate_id
                        WHERE cr.requisition_id=? AND cr.status='OPEN' ORDER BY cr.id",
                       [(int) $requisitionId]) ?: [];
    } catch (Throwable $e) { return []; }
}

// ---------------------------------------------------------------------------
//  AUTHORITY (§12 / §13 / §28) — seeing is not deciding
// ---------------------------------------------------------------------------

//  May the current user RESOLVE a review on this candidate?
//
//  Three things, all required:
//    1. the hiring.review.clear permission, configured per ROLE (never per user);
//    2. recruitment scope — the office / business unit this requirement belongs to;
//    3. a review that is actually open.
//
//  This is deliberately NOT the general recruitment edit permission. A coordinator
//  who may move candidates all day is not thereby entitled to decide that a person
//  still meets a requirement the business just raised — that is the judgement the
//  permission exists to place with somebody.
function crev_can_clear($cand) {
    if (!is_array($cand)) {
        $cand = ops_one("SELECT * FROM candidates WHERE id=?", [(int) $cand]) ?: null;
        if (!$cand) return false;
    }
    //  can() is this product's permission reader — the same one Gate 2 asks for its
    //  own permission. Asking for a function that does not exist would have made
    //  function_exists() false and quietly denied EVERYBODY, which is a failure that
    //  looks like security and is actually a dead control.
    if (!function_exists('can') || !can(CREV_PERM_CLEAR)) return false;
    return crev_scope_ok($cand);
}

//  SCOPE, derived through the requirement — there is no candidate office column
//  and Gate 3 does not add one (§23). A candidate with no requirement has no
//  requirement-derived scope and no review to resolve either.
function crev_scope_ok($cand) {
    $rq = (int) ($cand['requisition_id'] ?? 0);
    if ($rq <= 0) return false;
    try { $r = ops_one("SELECT office_id, sbu FROM requisitions WHERE id=?", [$rq]); }
    catch (Throwable $e) { return false; }
    if (!$r) return false;
    if (!function_exists('scope_allows')) return true;
    return (bool) scope_allows((int) ($r['office_id'] ?? 0), $r['sbu'] ?? null);
}

//  May the current user SEE that a review is open? Anyone who can legitimately
//  see the candidate. Visibility is not authority, so this is deliberately a
//  weaker test than crev_can_clear().
function crev_can_see($cand) {
    return function_exists('is_coordinator_level') ? (bool) is_coordinator_level() : true;
}

// ---------------------------------------------------------------------------
//  RESOLVING A REVIEW (§11 / §15 / §16)
// ---------------------------------------------------------------------------

//  A reason that is actually a reason. Blank, whitespace and a stray punctuation
//  mark are all refused: an audit trail of empty strings is not an audit trail.
function crev_reason_ok($reason) {
    $r = trim(preg_replace('~\s+~u', ' ', (string) $reason));
    if ($r === '') return '';
    if (preg_match('~^[\p{P}\p{S}\s]+$~u', $r)) return '';
    return substr($r, 0, 1000);
}

//  CONTINUE — this person stays in the process.
//
//  The historical interviews, assessments and scorecards are untouched (§9): they
//  are evidence of what happened and remain valid. Continuing does not move the
//  candidate, does not re-start anything and does not re-score anything. It
//  records that somebody with the authority looked at this person against the new
//  requirement and said yes, and why.
function crev_continue($reviewId, $reason) {
    crev_migrate();
    $r = crev_review($reviewId);
    if (!$r) return [false, 'That review no longer exists.'];
    if ((string) $r['status'] !== 'OPEN') return [false, 'That review has already been decided.'];
    $cand = ops_one("SELECT * FROM candidates WHERE id=?", [(int) $r['candidate_id']]);
    if (!$cand) return [false, 'That candidate no longer exists.'];
    if (!crev_can_clear($cand))
        return [false, 'You do not have the authority to decide a requirement review on this candidate.'];
    $why = crev_reason_ok($reason);
    if ($why === '') return [false, 'Say why this candidate still meets the changed requirement. A reason is required.'];

    //  Closed conditionally on it still being OPEN, so two people deciding in the
    //  same instant cannot both win and the loser changes nothing.
    try {
        $st = db()->prepare("UPDATE candidate_reviews
                             SET status='CONTINUED', open_key=NULL, resolved_by=?, resolved_by_id=?,
                                 resolved_at=?, resolve_reason=?
                             WHERE id=? AND status='OPEN'");
        $st->execute([crev_who(), crev_who_id(), crev_now(), $why, (int) $reviewId]);
        if ($st->rowCount() < 1) return [false, 'That review was decided by somebody else a moment ago.'];
    } catch (Throwable $e) { return [false, 'The review could not be decided, so nothing was changed.']; }

    crev_log((int) $r['candidate_id'], 'Requirement review cleared — continuing', ['remark' => $why]);
    return [true, 'Review cleared. This candidate continues in the process.'];
}

//  REJECT — this person leaves the process now.
//
//  §11: no further approval is required. The person reviewing IS the authority for
//  this decision, and routing it to somebody else would leave the candidate
//  blocked while a second queue formed.
//
//  §16/§17: the rejection lands on the organisation's OWN configured closed /
//  not-proceeding off-ramp, through Gate 1B's resolver. There is no REVIEW_REJECTED
//  state: a rejection is a rejection, and inventing a parallel one would give the
//  product two answers to "is this candidate still live".
function crev_reject($reviewId, $reason) {
    crev_migrate();
    $r = crev_review($reviewId);
    if (!$r) return [false, 'That review no longer exists.'];
    if ((string) $r['status'] !== 'OPEN') return [false, 'That review has already been decided.'];
    $cid = (int) $r['candidate_id'];
    $cand = ops_one("SELECT * FROM candidates WHERE id=?", [$cid]);
    if (!$cand) return [false, 'That candidate no longer exists.'];
    if (!crev_can_clear($cand))
        return [false, 'You do not have the authority to decide a requirement review on this candidate.'];
    $why = crev_reason_ok($reason);
    if ($why === '') return [false, 'Say why this candidate no longer meets the requirement. A reason is required.'];

    //  WHERE THE REJECTION LANDS, asked of Gate 1B's own closed-stage reader.
    //
    //  "Not suitable" is the outcome that actually describes what happened here —
    //  the requirement changed and this person no longer meets it — so an off-ramp
    //  configured with that outcome is preferred. It is looked up among the
    //  CONFIGURED CLOSED STAGES rather than through the legacy-target resolver,
    //  because NOT_SUITABLE is a closed OUTCOME and not a legacy stage code: asking
    //  the resolver for it would have returned nothing, every time, and the
    //  preference would have been dead code reading like a feature.
    //
    //  A workspace that has not configured such an off-ramp falls back to its
    //  ordinary rejection off-ramp, which every workspace has.
    $target = null;
    if (function_exists('recruitpipe_closed_stages') && function_exists('recruitpipe_cand_state')) {
        [$cpipe, , ] = recruitpipe_cand_state($cand);
        if ($cpipe) {
            $creq = !empty($cand['requisition_id'])
                ? (ops_one("SELECT * FROM requisitions WHERE id=?", [(int) $cand['requisition_id']]) ?: []) : [];
            foreach (recruitpipe_closed_stages((int) $cpipe['id'], is_array($creq) ? $creq : []) as $cs)
                if (strtoupper((string) ($cs['closed_outcome'] ?? '')) === 'NOT_SUITABLE') { $target = $cs; break; }
        }
    }
    if (!$target && function_exists('rpipe_stage_for_legacy_target'))
        $target = rpipe_stage_for_legacy_target($cand, 'REJECTED');

    $priorStageId = (int) ($cand['pipeline_stage_id'] ?? 0);
    $priorState = function_exists('rpipe_current_state') ? rpipe_current_state($cand) : null;
    $priorName = $priorState ? (string) ($priorState['stage_name'] ?: $priorState['legacy_stage']) : (string) ($cand['stage'] ?? '');
    $priorCode = $priorState ? (string) ($priorState['stage_key'] ?: $priorState['legacy_stage']) : (string) ($cand['stage'] ?? '');

    try {
        $st = db()->prepare("UPDATE candidate_reviews
                             SET status='REJECTED', open_key=NULL, resolved_by=?, resolved_by_id=?,
                                 resolved_at=?, resolve_reason=?, resolved_stage_id=?
                             WHERE id=? AND status='OPEN'");
        $st->execute([crev_who(), crev_who_id(), crev_now(), $why,
                      $target ? (int) $target['id'] : 0, (int) $reviewId]);
        if ($st->rowCount() < 1) return [false, 'That review was decided by somebody else a moment ago.'];
    } catch (Throwable $e) { return [false, 'The review could not be decided, so nothing was changed.']; }

    //  THE CANDIDATE MOVES, the same way every other closure moves them: the
    //  pipeline position where the pipeline can answer, the legacy column only
    //  where it cannot (a candidate outside any process — D3). Never both, because
    //  Gate 1B removed the second authority and nothing here puts it back.
    $drop = substr('Requirement changed — ' . $why, 0, 60);
    try {
        if ($target) {
            db()->prepare("UPDATE candidates SET pipeline_id=?, pipeline_stage_id=?, decided_at=?,
                                                 drop_point=?, drop_reason=? WHERE id=?")
                ->execute([(int) $target['pipeline_id'], (int) $target['id'], crev_now(),
                           substr($priorName, 0, 30), $drop, $cid]);
        } else {
            db()->prepare("UPDATE candidates SET stage='REJECTED', decided_at=?, drop_point=?, drop_reason=? WHERE id=?")
                ->execute([crev_now(), substr($priorName, 0, 30), $drop, $cid]);
        }
    } catch (Throwable $e) { /* the review is decided; the move is reported below */ }

    //  ONE LEDGER, the existing one. The origin stage is recorded because that is
    //  what a deliberate reconsideration later has to put the candidate back to
    //  (§19) — it is evidence, not a convenience.
    crev_log($cid, 'Requirement review — rejected', [
        'remark' => $why, 'from_label' => $priorName, 'from_code' => $priorCode,
        'to_label' => $target ? (string) $target['name'] : 'Rejected',
        'to_code' => $target ? (string) ($target['stage_key'] ?? '') : 'REJECTED',
        'track' => $target ? 'PIPELINE' : 'LEGACY']);

    //  Any OTHER open review on this same relationship is moot: the candidate has
    //  left the process, and leaving it open would block a candidate who is gone.
    try {
        db()->prepare("UPDATE candidate_reviews SET status='REJECTED', open_key=NULL, resolved_by=?,
                           resolved_by_id=?, resolved_at=?, resolve_reason=?
                       WHERE candidate_id=? AND status='OPEN'")
            ->execute([crev_who(), crev_who_id(), crev_now(),
                       'Closed with the candidate: ' . $why, $cid]);
    } catch (Throwable $e) {}

    return [true, 'Candidate rejected. The reason has been recorded against this requirement review.'];
}

// ---------------------------------------------------------------------------
//  DELIBERATE RECONSIDERATION (§18 / §19)
// ---------------------------------------------------------------------------

//  WHERE A REJECTED CANDIDATE GOES BACK TO.
//
//  §19 is specific and it is not the first stage: the candidate returns to the
//  stage they were on IMMEDIATELY BEFORE the rejection. Somebody rejected out of
//  an interview goes back to the interview, not to Received — being reconsidered
//  is not being re-applied, and sending them to the start would throw away the
//  screening and interviewing that genuinely happened.
//
//  The answer comes from the event ledger, which recorded the origin at the moment
//  of closure. Nothing is inferred from the pipeline's shape.
function crev_previous_stage($candidateId) {
    $cid = (int) $candidateId;
    $cand = ops_one("SELECT * FROM candidates WHERE id=?", [$cid]);
    if (!$cand) return null;
    try {
        //  The most recent CLOSING move: the ledger row whose origin is where they
        //  stood before they were closed. Read newest-first so a candidate closed,
        //  reconsidered and closed again goes back to the right place.
        $rows = ops_all("SELECT * FROM candidate_events WHERE candidate_id=? ORDER BY id DESC LIMIT 60", [$cid]) ?: [];
    } catch (Throwable $e) { return null; }
    foreach ($rows as $ev) {
        if (strtoupper((string) ($ev['event_kind'] ?? '')) === 'REVERT') continue;
        $fromCode = trim((string) ($ev['from_code'] ?? ''));
        $track = strtoupper((string) ($ev['track'] ?? ''));
        //  Was the DESTINATION of this move a closed one? That is the move to undo.
        $toCode = trim((string) ($ev['to_code'] ?? ''));
        $closedMove = false;
        if ($track === 'PIPELINE' && $toCode !== '') {
            $s = crev_stage_by_key($cand, $toCode);
            $closedMove = $s && function_exists('rpipe_kind_is_closed') && rpipe_kind_is_closed((string) ($s['kind'] ?? ''));
        } elseif ($toCode !== '') {
            $closedMove = function_exists('rpipe_legacy_kind') && rpipe_legacy_kind(strtoupper($toCode)) === 'closed';
        }
        if (!$closedMove || $fromCode === '') continue;
        if ($track === 'PIPELINE') {
            $s = crev_stage_by_key($cand, $fromCode);
            if ($s) return ['stage' => $s, 'track' => 'PIPELINE', 'label' => (string) $s['name'], 'code' => $fromCode];
        } else {
            $s = function_exists('rpipe_stage_for_legacy_target')
                ? rpipe_stage_for_legacy_target($cand, $fromCode) : null;
            return ['stage' => $s, 'track' => 'LEGACY', 'label' => (string) ($ev['from_stage'] ?? $fromCode), 'code' => $fromCode];
        }
    }
    return null;
}

//  One stage of this candidate's own pipeline, by its stable key — progression and
//  off-ramps both, because a closed stage is a real position.
function crev_stage_by_key($cand, $key) {
    if (!function_exists('recruitpipe_cand_state')) return null;
    [$pipe, , ] = recruitpipe_cand_state($cand);
    if (!$pipe) return null;
    $req = !empty($cand['requisition_id'])
        ? (ops_one("SELECT * FROM requisitions WHERE id=?", [(int) $cand['requisition_id']]) ?: []) : [];
    foreach (recruitpipe_resolvable_stages((int) $pipe['id'], is_array($req) ? $req : []) as $s)
        if (strcasecmp((string) ($s['stage_key'] ?? ''), (string) $key) === 0) return $s;
    return null;
}

//  BRING A REJECTED CANDIDATE BACK — deliberately, by a person, with a reason.
//
//  Nothing automatic ever calls this. A relaxed requirement does not call it (§18),
//  no queue calls it, no scan calls it. The original rejection stays exactly where
//  it is in the record: this APPENDS a reconsideration, it does not edit history.
function crev_reconsider($candidateId, $reason) {
    crev_migrate();
    $cid = (int) $candidateId;
    $cand = ops_one("SELECT * FROM candidates WHERE id=?", [$cid]);
    if (!$cand) return [false, 'That candidate no longer exists.'];
    if (!crev_can_clear($cand))
        return [false, 'You do not have the authority to reconsider a candidate on this requirement.'];
    $why = crev_reason_ok($reason);
    if ($why === '') return [false, 'Say why this candidate is being reconsidered. A reason is required.'];

    $st = function_exists('rpipe_current_state') ? rpipe_current_state($cand) : null;
    if (!$st || (string) $st['class'] !== 'LOST')
        return [false, 'This candidate has not been closed, so there is nothing to reconsider.'];
    $prev = crev_previous_stage($cid);
    if (!$prev || empty($prev['stage']))
        return [false, 'The stage this candidate was on before being closed is not recorded, so they cannot be '
                     . 'put back automatically. Move them to the right stage deliberately instead.'];

    $closedName = (string) ($st['stage_name'] ?: $st['legacy_stage']);
    $closedOutcome = (string) ($st['closed_outcome'] ?? '');
    $target = $prev['stage'];
    try {
        db()->prepare("UPDATE candidates SET pipeline_id=?, pipeline_stage_id=?, decided_at='',
                                             drop_point='', drop_reason='' WHERE id=?")
            ->execute([(int) $target['pipeline_id'], (int) $target['id'], $cid]);
    } catch (Throwable $e) { return [false, 'The candidate could not be put back, so nothing was changed.']; }

    //  THE RECONSIDERATION IS ITS OWN LEDGER ENTRY, carrying everything §19 asks
    //  for: what they were closed as, where they came back to, who did it, when,
    //  and why. The rejection row above it is untouched.
    crev_log($cid, 'Reconsidered — returned to ' . (string) $target['name'], [
        'remark' => 'Previously ' . ($closedOutcome !== '' ? strtolower(str_replace('_', ' ', $closedOutcome)) : 'closed')
                  . ' at ' . $closedName . '. ' . $why,
        'from_label' => $closedName, 'from_code' => (string) ($st['stage_key'] ?: $st['legacy_stage']),
        'to_label' => (string) $target['name'], 'to_code' => (string) ($target['stage_key'] ?? ''),
        'track' => 'PIPELINE']);
    return [true, 'Candidate reconsidered and returned to ' . (string) $target['name'] . '.'];
}

// ---------------------------------------------------------------------------
//  WHAT AN OPEN REVIEW STOPS (§10) — in the EXISTING action vocabulary
// ---------------------------------------------------------------------------

//  Which REXEC_ACTIONS an open review refuses.
//
//  The shipped answer is ALL of them: the bar moved and nobody has confirmed this
//  person clears it, so nothing about them should advance. An organisation may let
//  screening and interviewing continue — gathering more evidence is exactly what a
//  reviewer may want first — but an OFFER and a JOINING can never be unblocked.
//  Those are commitments to a human being, and making one against a requirement
//  nobody has re-checked them against is the failure this gate exists to prevent.
const CREV_NEVER_ALLOWED = ['OFFER', 'JOIN'];

function crev_allows_screening() {
    $v = function_exists('setting_get') ? setting_get('crev_allow_screening', '0') : '0';
    return (string) $v === '1';
}

//  Why this action is refused while a review is open on this candidate, or ''.
//
//  Answered PER CANDIDATE, because a review belongs to one candidate's one
//  relationship. Asked with no candidate, there is nothing to say — a requirement
//  does not hold a review, people do.
function crev_block_reason($candidateId, $action = 'ADVANCE') {
    $cid = (int) $candidateId; if ($cid <= 0) return '';
    $open = crev_open_all($cid);
    if (!$open) return '';
    //  ONLY A REVIEW ABOUT THE PROCESS THIS CANDIDATE IS ACTUALLY IN.
    //
    //  A review records the requirement it was raised against. If the candidate is
    //  later reallocated to a different requirement, that review is about a process
    //  they are no longer in — and leaving it to block work on an unrelated
    //  requirement would be the global candidate state §2 forbids, arriving by the
    //  back door. It stays OPEN in the record, because nobody answered it; it simply
    //  stops speaking for a requirement it was never about. Move them back and it
    //  blocks again.
    try { $here = (int) ops_val("SELECT COALESCE(requisition_id,0) FROM candidates WHERE id=?", [$cid]); }
    catch (Throwable $e) { $here = 0; }
    $open = array_values(array_filter($open, fn($r) => (int) $r['requisition_id'] === $here));
    if (!$open) return '';
    $a = strtoupper((string) $action);
    if (crev_allows_screening() && !in_array($a, CREV_NEVER_ALLOWED, true)) return '';
    $r = $open[0];
    $label = (string) $r['entity'] === 'HIRING_REQUEST' ? 'hiring request' : 'requirement';
    //  Phrased with the EXISTING action vocabulary, so a review refusal reads like
    //  every other refusal in the product rather than announcing a new subsystem.
    $verb = defined('REXEC_ACTIONS') ? (REXEC_ACTIONS[$a] ?? 'move this candidate forward')
                                     : 'move this candidate forward';
    return 'The approved ' . $label . ' changed after this candidate was added, so somebody has to confirm '
         . 'they still meet it before you can ' . $verb . '.';
}

// ---------------------------------------------------------------------------
//  EVIDENCE, NEVER A DECISION (§1 / §20)
// ---------------------------------------------------------------------------

//  How this candidate stands against the requirement AS IT NOW IS.
//
//  Read this carefully, because it is the one thing in this gate most likely to be
//  misused later: THIS IS DISPLAY ONLY. It is computed so a reviewer opening a
//  review can see at a glance why they are being asked, instead of holding two
//  records side by side in their head. It is returned to the screen and to nobody
//  else.
//
//  It is never consulted by crev_raise_for_version(), never by crev_audience(),
//  never by crev_block_reason(), and never by crev_continue() or crev_reject().
//  A candidate who plainly fails every line of it still gets a review and still
//  needs a human to reject them; a candidate who exceeds every line still gets a
//  review and still needs a human to clear them. The mutation battery exists
//  largely to prove that this function cannot acquire a vote.
function crev_evidence($cand, $entity, $entityId) {
    if (!is_array($cand)) $cand = ops_one("SELECT * FROM candidates WHERE id=?", [(int) $cand]) ?: [];
    $out = ['rows' => [], 'note' => 'Evidence only — this does not decide the review.'];
    if (!$cand || !function_exists('rver_approved_fields')) return $out;
    $f = rver_approved_fields(strtoupper((string) $entity), (int) $entityId);
    if (!is_array($f)) return $out;

    $exp = (float) ($f['min_experience_years'] ?? 0);
    if ($exp > 0) {
        $has = (float) ($cand['experience_years'] ?? 0);
        $out['rows'][] = ['label' => 'Minimum experience', 'wanted' => $exp . ' years',
                          'candidate' => $has . ' years', 'meets' => $has >= $exp];
    }
    $qual = trim((string) ($f['min_qualification'] ?? ''));
    if ($qual !== '') {
        //  The candidate's own qualification is not a column on this record, so the
        //  honest answer is "a person has to look", not a guess dressed as a tick.
        $out['rows'][] = ['label' => 'Minimum qualification', 'wanted' => $qual,
                          'candidate' => '—', 'meets' => null];
    }
    $skills = trim((string) ($f['essential_skills'] ?? ''));
    if ($skills !== '') {
        $out['rows'][] = ['label' => 'Essential skills', 'wanted' => $skills,
                          'candidate' => '—', 'meets' => null];
    }
    return $out;
}

// ---------------------------------------------------------------------------
//  THE LEDGER — the existing one (§25)
// ---------------------------------------------------------------------------

//  Every review event goes into candidate_events, through the one stage-ledger
//  writer, so a review sits in the same history as every other thing that happened
//  to this candidate and in the same order. There is no separate review log to
//  reconcile with it.
function crev_log($candidateId, $subject, array $o = []) {
    $from = (string) ($o['from_label'] ?? '');
    $to   = (string) ($o['to_label'] ?? $subject);
    if (function_exists('rkpi_stage_log'))
        return (bool) rkpi_stage_log((int) $candidateId, $from, $to, [
            'from_code' => (string) ($o['from_code'] ?? ''),
            'to_code'   => (string) ($o['to_code'] ?? ''),
            'track'     => (string) ($o['track'] ?? 'REVIEW'),
            'kind'      => (string) ($o['kind'] ?? 'REVIEW'),
            'remark'    => substr($subject . (trim((string) ($o['remark'] ?? '')) !== ''
                              ? ' — ' . (string) $o['remark'] : ''), 0, 500),
            'actor'     => crev_who()]);
    try {
        db()->prepare("INSERT INTO candidate_events (candidate_id,from_stage,to_stage,remark,actor,created_at)
                       VALUES (?,?,?,?,?,?)")
            ->execute([(int) $candidateId, $from, $to,
                       substr($subject, 0, 500), crev_who(), crev_now()]);
        return true;
    } catch (Throwable $e) { return false; }
}

// ---------------------------------------------------------------------------
//  WHAT THE SCREEN NEEDS (§24)
// ---------------------------------------------------------------------------

//  One answer for one candidate row: is a review open, what changed, may I decide
//  it, and what has been decided before. Everything the UI shows comes from here,
//  so a screen cannot form its own opinion about any of it.
function crev_state($cand) {
    if (!is_array($cand)) $cand = ops_one("SELECT * FROM candidates WHERE id=?", [(int) $cand]) ?: [];
    $out = ['open' => [], 'has_open' => false, 'can_clear' => false, 'can_see' => false,
            'history' => [], 'reconsider' => null, 'blocked' => ''];
    if (!$cand || empty($cand['id'])) return $out;
    $cid = (int) $cand['id'];
    $out['can_see'] = crev_can_see($cand);
    $out['can_clear'] = crev_can_clear($cand);
    $out['history'] = crev_history($cid);

    foreach (crev_open_all($cid) as $r) {
        $e = (string) $r['entity']; $eid = (int) $r['entity_id'];
        $trig = json_decode((string) ($r['trigger_json'] ?? ''), true);
        $out['open'][] = [
            'review'   => $r,
            'label'    => $e === 'HIRING_REQUEST' ? 'hiring request' : 'requirement',
            'no'       => crev_entity_no($e, $eid),
            'summary'  => (string) ($trig['summary'] ?? ''),
            'kind'     => (string) $r['trigger_kind'],
            'from'     => (int) $r['from_version'],
            'to'       => (int) $r['to_version'],
            'evidence' => crev_evidence($cand, $e, $eid),
        ];
    }
    $out['has_open'] = !empty($out['open']);
    $out['blocked'] = crev_block_reason($cid, 'ADVANCE');

    //  Reconsideration is offered only where it is genuinely available: the
    //  candidate is closed AND the ledger records where they came from.
    $st = function_exists('rpipe_current_state') ? rpipe_current_state($cand) : null;
    if ($st && (string) $st['class'] === 'LOST' && $out['can_clear']) {
        $prev = crev_previous_stage($cid);
        if ($prev && !empty($prev['stage'])) $out['reconsider'] = $prev;
    }
    return $out;
}

//  The human-readable number of the requirement that changed.
function crev_entity_no($entity, $entityId) {
    $e = strtoupper((string) $entity);
    $tbl = $e === 'HIRING_REQUEST' ? 'hiring_requests' : 'requisitions';
    $col = $e === 'HIRING_REQUEST' ? 'req_no' : 'req_code';
    try { return (string) ops_val("SELECT COALESCE($col,'') FROM $tbl WHERE id=?", [(int) $entityId]); }
    catch (Throwable $e2) { return ''; }
}

// ---------------------------------------------------------------------------
//  THE ONE DOOR (§13 / §27)
//
//  Continue, Reject and Reconsider all arrive here, and the authority question is
//  asked by crev_continue() / crev_reject() / crev_reconsider() — inside the
//  functions, not in this handler. That is deliberate: a route that checked
//  permission itself would be a second place for the rule to live, and a POST
//  arriving by any other path would miss it. The functions refuse on their own, so
//  a direct POST, a crafted URL or a future caller all meet the same refusal.
// ---------------------------------------------------------------------------
function ops_candidate_review($route, $method) {
    $cid = (int) ($_GET['id'] ?? $_POST['candidate_id'] ?? 0);
    $cand = $cid > 0 ? ops_one("SELECT * FROM candidates WHERE id=?", [$cid]) : null;
    if (!$cand) { http_response_code(404); view('notfound'); return true; }

    //  Seeing the screen needs only legitimate access to the candidate. Deciding
    //  anything on it does not happen here.
    ops_require(crev_can_see($cand), 'You cannot open this candidate.');

    if ($method === 'POST') {
        $action = strtolower(trim((string) ($_POST['action'] ?? '')));
        $reason = (string) ($_POST['reason'] ?? '');
        if ($action === 'continue') {
            [$ok, $msg] = crev_continue((int) ($_POST['review_id'] ?? 0), $reason);
        } elseif ($action === 'reject') {
            [$ok, $msg] = crev_reject((int) ($_POST['review_id'] ?? 0), $reason);
        } elseif ($action === 'reconsider') {
            [$ok, $msg] = crev_reconsider($cid, $reason);
        } else {
            [$ok, $msg] = [false, 'Unknown review action.'];
        }
        flash($msg, $ok ? 'success' : 'error');
        redirect('/candidate?id=' . $cid);
    }
    redirect('/candidate?id=' . $cid);
    return true;
}

// ---------------------------------------------------------------------------
//  THE PANEL (§24)
//
//  Built from the EXISTING panel / pill / table classes, in the same shape as Gate
//  2's change-control panel, because a reviewer who already understands that panel
//  should not have to learn a second one. No new UI framework, no new vocabulary.
//
//  It exists to make three mistakes impossible:
//    · not realising the requirement moved under this candidate;
//    · thinking the review has been dealt with when it has not;
//    · thinking the figures on screen mean this person has been checked.
//
//  So the requirement that changed is named with its number and its two version
//  numbers, what got stricter is stated in words, and the evidence table is
//  labelled as evidence — never as a verdict. Salary and commercial terms are NOT
//  shown here: a review is about whether somebody meets a requirement, and a panel
//  that is visible to more people than the commercials are must not leak them.
// ---------------------------------------------------------------------------
function crev_panel($cand) {
    if (!is_array($cand) || empty($cand['id'])) return;
    $st = crev_state($cand);
    if (!$st['can_see']) return;
    if (!$st['has_open'] && !$st['history'] && !$st['reconsider']) return;
    $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
    $cid = (int) $cand['id'];
    ?>
    <?php if ($st['has_open']): ?>
    <div class="panel" style="margin-top:14px;border-left:4px solid var(--amber,#d97706);background:#fffdf5">
      <h3 class="tab-sub" style="margin-top:0">Review required
        <span class="pill p-warn" style="font-size:12px">Not yet reviewed</span>
      </h3>
      <p class="muted" style="margin:0 0 10px">
        The approved requirement this candidate was added against has changed. Somebody has to
        confirm, for this person, that they still meet it — or reject them — before recruitment
        continues.
      </p>
      <?php foreach ($st['open'] as $o): $r = $o['review']; ?>
        <div style="border:1px solid var(--line,#e5e7eb);border-radius:8px;padding:10px 12px;margin-bottom:10px;background:#fff">
          <div style="margin-bottom:6px">
            <strong>The <?= $e($o['label']) ?><?php if ($o['no'] !== ''): ?> <?= $e($o['no']) ?><?php endif; ?></strong>
            changed from <span class="pill" style="font-size:11px">version <?= (int) $o['from'] ?></span>
            to <span class="pill p-ok" style="font-size:11px">version <?= (int) $o['to'] ?> — in force</span>
            <?php if ($o['kind'] === 'stricter'): ?>
              <span class="pill p-bad" style="font-size:11px">Asks for more than before</span>
            <?php else: ?>
              <span class="pill p-warn" style="font-size:11px">Asks for something different</span>
            <?php endif; ?>
          </div>
          <?php if ($o['summary'] !== ''): ?>
            <div style="margin-bottom:8px"><?= $e($o['summary']) ?>.</div>
          <?php endif; ?>

          <?php if (!empty($o['evidence']['rows'])): ?>
            <table class="tbl" style="margin:0 0 8px">
              <thead><tr><th>What the requirement now asks</th><th>Asked</th><th>On this record</th></tr></thead>
              <tbody>
              <?php foreach ($o['evidence']['rows'] as $row): ?>
                <tr>
                  <td><?= $e($row['label']) ?></td>
                  <td><?= $e($row['wanted']) ?></td>
                  <td><?= $e($row['candidate']) ?><?php
                    if ($row['meets'] === true): ?> <span class="pill p-ok" style="font-size:10.5px">meets</span><?php
                    elseif ($row['meets'] === false): ?> <span class="pill p-bad" style="font-size:10.5px">below</span><?php
                    endif; ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
            <p class="muted" style="margin:0 0 8px;font-size:12px">
              This table is <strong>evidence for you to weigh, not a decision</strong>. Nothing here
              clears or rejects anybody, and a candidate who looks short on paper may still be the
              right person — that judgement is yours.
            </p>
          <?php endif; ?>

          <?php if ($st['can_clear']): ?>
            <form method="post" action="/candidate-review?id=<?= $cid ?>" style="margin:0">
              <input type="hidden" name="review_id" value="<?= (int) $r['id'] ?>">
              <input type="hidden" name="candidate_id" value="<?= $cid ?>">
              <label style="display:block;margin-bottom:6px">
                <span class="muted" style="font-size:12px">Your reason (required, and kept in the record)</span>
                <input type="text" name="reason" maxlength="1000" required
                       placeholder="e.g. 12 years on similar plant — comfortably above the new 8-year minimum"
                       style="width:100%;box-sizing:border-box">
              </label>
              <div style="display:flex;flex-wrap:wrap;gap:8px">
                <button class="btn" name="action" value="continue">Continue with this candidate</button>
                <button class="btn btn-danger" name="action" value="reject"
                        onclick="return confirm('Reject this candidate against the changed requirement? This closes them out of the process.')">
                  Reject</button>
              </div>
            </form>
          <?php else: ?>
            <p class="muted" style="margin:0;font-size:12.5px">
              You can see this review but you are not the one who decides it. Somebody with
              authority to decide requirement reviews on this requirement has to continue or
              reject this candidate.
            </p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($st['reconsider']): ?>
    <div class="panel" style="margin-top:14px">
      <h3 class="tab-sub" style="margin-top:0">Reconsider this candidate</h3>
      <p class="muted" style="margin:0 0 10px">
        This candidate was closed out of the process. Bringing them back is a deliberate decision —
        it never happens on its own, not even when the requirement is lowered again. They would
        return to <strong><?= $e($st['reconsider']['label']) ?></strong>, the stage they were on
        before they were closed, and the original decision stays in the record.
      </p>
      <form method="post" action="/candidate-review?id=<?= $cid ?>" style="margin:0">
        <input type="hidden" name="candidate_id" value="<?= $cid ?>">
        <label style="display:block;margin-bottom:6px">
          <span class="muted" style="font-size:12px">Why are you reconsidering them? (required)</span>
          <input type="text" name="reason" maxlength="1000" required
                 placeholder="e.g. requirement reduced to 5 years and no other candidate is available"
                 style="width:100%;box-sizing:border-box">
        </label>
        <button class="btn" name="action" value="reconsider">
          Reconsider — return to <?= $e($st['reconsider']['label']) ?></button>
      </form>
    </div>
    <?php endif; ?>

    <?php
    //  THE HISTORY — open and decided, never pruned. A refused review and the reason
    //  it was refused are part of the permanent record, and showing only the open
    //  ones would hide exactly the decisions somebody later needs to understand.
    if ($st['history']): ?>
    <div class="panel" style="margin-top:14px">
      <h3 class="tab-sub" style="margin-top:0">Requirement review history
        <span class="muted" style="font-weight:400">— every review raised on this application</span></h3>
      <table class="tbl">
        <thead><tr><th>Requirement</th><th>Change</th><th>Outcome</th><th>Who &amp; when</th><th>Reason</th></tr></thead>
        <tbody>
        <?php foreach ($st['history'] as $h): $tj = json_decode((string) ($h['trigger_json'] ?? ''), true); ?>
          <tr>
            <td><?= $e(crev_entity_no((string) $h['entity'], (int) $h['entity_id'])) ?>
                <span class="muted" style="font-size:11px"><?= $e((string) $h['entity'] === 'HIRING_REQUEST' ? 'hiring request' : 'requirement') ?></span></td>
            <td>v<?= (int) $h['from_version'] ?> → v<?= (int) $h['to_version'] ?>
                <div class="muted" style="font-size:11px"><?= $e((string) ($tj['summary'] ?? '')) ?></div></td>
            <td><?php $s = (string) $h['status']; ?>
                <span class="pill <?= $s === 'OPEN' ? 'p-warn' : ($s === 'REJECTED' ? 'p-bad' : 'p-ok') ?>" style="font-size:11px">
                  <?= $e(CREV_STATUSES[$s] ?? $s) ?></span></td>
            <td><?= $e($s === 'OPEN' ? (string) $h['raised_by'] : (string) $h['resolved_by']) ?>
                <div class="muted" style="font-size:11px"><?= $e(substr((string) ($s === 'OPEN' ? $h['raised_at'] : $h['resolved_at']), 0, 16)) ?></div></td>
            <td><?= $e((string) $h['resolve_reason']) ?: '<span class="muted">—</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif;
}
