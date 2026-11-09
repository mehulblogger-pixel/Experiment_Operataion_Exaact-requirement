<?php
// ============================================================================
//  EXAACT Recruitment — Interviews (multi-round + scorecards) & Document DMS
//  (Phase 4; brief §22–§24). Additive and non-destructive.
//
//  Interviews become a first-class, multi-round entity (L1/L2/L3/HR/technical/
//  management/panel/client/practical/assessment) with a scorecard: competencies,
//  rating, recommendation, result and comments. Documents become a structured
//  set with configurable types and a real status lifecycle (required → requested
//  → uploaded → under review → verified / rejected / resubmit / expired).
//  Sensitive documents (salary, medical, identity) are download-restricted.
// ============================================================================

const IV_ROUNDS = ['L1', 'L2', 'L3', 'HR', 'Technical', 'Management', 'Panel', 'Client', 'Practical test', 'Assessment'];
const IV_MODES  = ['In person', 'Video', 'Phone'];
const IV_RESULTS = [
    'SCHEDULED'    => 'Scheduled',
    'PASS'         => 'Pass',
    'FAIL'         => 'Fail',
    'HOLD'         => 'Hold',
    'RE_INTERVIEW' => 'Re-interview',
    'NO_SHOW'      => 'No show',
    'CANCELLED'    => 'Cancelled',
];
const IV_RECOMMENDATIONS = ['', 'Strong hire', 'Hire', 'Hold', 'No hire'];

// Document status lifecycle (§24).
const DOC_STATUSES = [
    'NOT_REQUIRED' => 'Not required',
    'REQUIRED'     => 'Required',
    'REQUESTED'    => 'Requested',
    'UPLOADED'     => 'Uploaded',
    'UNDER_REVIEW' => 'Under review',
    'VERIFIED'     => 'Verified',
    'REJECTED'     => 'Rejected',
    'RESUBMIT'     => 'Resubmission required',
    'EXPIRED'      => 'Expired',
];
// Default document types (configurable on the Masters screen: candidate_doc_type).
const DOC_TYPES_SEED = [
    'Resume / CV', 'Educational certificate', 'Experience certificate', 'Identity (KYC)',
    'Address proof', 'PAN', 'Salary slip', 'Relieving letter', 'Reference', 'Photograph',
    'Medical report', 'Other',
];
// Types whose file is sensitive — download restricted to admin-level users (§39).
const DOC_SENSITIVE = ['Salary slip', 'Medical report', 'Identity (KYC)', 'PAN'];

function doc_types() {
    return function_exists('lk_options_or')
        ? array_values(lk_options_or('candidate_doc_type', array_combine(DOC_TYPES_SEED, DOC_TYPES_SEED)))
        : DOC_TYPES_SEED;
}
function doc_is_sensitive($type) { return in_array((string)$type, DOC_SENSITIVE, true); }

// ---- Schema (one migrate creates both tables; wired into boot) --------------
function recruit_iv_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    if (!function_exists('ensure_column')) return;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $lt = (function_exists('db_driver') && db_driver() === 'sqlite') ? 'TEXT' : 'LONGTEXT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS interviews (
            id $pk,
            candidate_id INT,
            round VARCHAR(40) DEFAULT 'L1',
            mode VARCHAR(30) DEFAULT 'In person',
            location VARCHAR(240) DEFAULT '',
            scheduled_at VARCHAR(30) DEFAULT '',
            panel VARCHAR(300) DEFAULT '',
            competencies VARCHAR(400) DEFAULT '',
            questions $lt,
            rating INT DEFAULT 0,
            recommendation VARCHAR(40) DEFAULT '',
            result VARCHAR(20) DEFAULT 'SCHEDULED',
            comments $lt,
            created_by VARCHAR(160) DEFAULT '',
            created_at VARCHAR(30) DEFAULT '',
            done_at VARCHAR(30) DEFAULT ''
        )");
        //  WHO IS ON THE PANEL, AS IDS.                        (UAT A9, 2026-10-18)
        //
        //  `panel` has always been a comma-joined string of NAMES, built at save
        //  time and thrown away as data. That made one of the owner's findings
        //  unanswerable by construction:
        //
        //    "the panel people are not reflected any details of the today
        //     scheduled interviews on there dashboard or daily task"
        //
        //  Nothing could tell which interviews a given person sits on, because
        //  "Mehul Rathod" is not a key: two people share a name, a person gets
        //  renamed, a middle name appears. The ids are now kept alongside the
        //  display string, so the text people read is unchanged and legacy rows
        //  still render, while "which interviews am I on" has a real answer.
        ensure_column('interviews', 'panel_user_ids', "VARCHAR(240) DEFAULT ''");
        //  Attempt number within a round, so a re-interview is visible as one
        //  rather than looking like a duplicate booking.
        ensure_column('interviews', 'attempt', 'INT DEFAULT 1');
        //  Owner, 9 Nov 2026 — why a re-interview, an out-of-order level or a
        //  changed result was allowed: written down, never left to memory.
        ensure_column('interviews', 'reason', "VARCHAR(300) DEFAULT ''");
        db()->exec("CREATE TABLE IF NOT EXISTS candidate_docs (
            id $pk,
            candidate_id INT,
            doc_type VARCHAR(120) DEFAULT '',
            `sensitive` INT DEFAULT 0,
            file_name VARCHAR(200) DEFAULT '',
            file_data $lt,
            issue_date VARCHAR(20) DEFAULT '',
            expiry_date VARCHAR(20) DEFAULT '',
            status VARCHAR(20) DEFAULT 'REQUIRED',
            uploaded_by VARCHAR(160) DEFAULT '',
            uploaded_at VARCHAR(30) DEFAULT '',
            verifier VARCHAR(160) DEFAULT '',
            verified_at VARCHAR(30) DEFAULT '',
            rejection_reason VARCHAR(300) DEFAULT '',
            created_at VARCHAR(30) DEFAULT ''
        )");
        if (function_exists('act_index')) {
            act_index('interviews', 'idx_iv_cand', '(candidate_id)');
            act_index('candidate_docs', 'idx_cd_cand', '(candidate_id)');
        }
        // A person's department — used to default an interview panel to the
        // candidate's own department. Additive; blank on existing users.
        ensure_column('users', 'department', "VARCHAR(120) DEFAULT ''");
        // A document can be captured against a specific pipeline stage (the
        // Pipeline tab), so each stage collects its own paperwork. Nullable —
        // existing documents stay candidate-wide.
        ensure_column('candidate_docs', 'pipeline_stage_id', 'INT NULL');
        // Per-interviewer scorecards: each panel member scores one interview
        // individually (rating, recommendation, comments). The interview's own
        // rating/recommendation stay as the overall/aggregate view.
        db()->exec("CREATE TABLE IF NOT EXISTS interview_scores (
            id $pk,
            interview_id INT,
            member VARCHAR(160) DEFAULT '',
            rating INT DEFAULT 0,
            recommendation VARCHAR(40) DEFAULT '',
            comments $lt,
            created_by VARCHAR(160) DEFAULT '',
            created_at VARCHAR(30) DEFAULT ''
        )");
        if (function_exists('act_index')) act_index('interview_scores', 'idx_ivs_iv', '(interview_id)');
    } catch (Throwable $e) { /* never break boot */ }
}

// ---- Per-interviewer scorecards -------------------------------------------
function iv_scores($ivId) {
    recruit_iv_migrate();
    try { return ops_all("SELECT * FROM interview_scores WHERE interview_id=? ORDER BY id", [(int)$ivId]); }
    catch (Throwable $e) { return []; }
}
// Count of member scores and the average of the non-zero ratings.
function iv_score_summary($ivId) {
    $rows = iv_scores($ivId);
    $rated = array_values(array_filter($rows, fn($r) => (int)$r['rating'] > 0));
    $avg = $rated ? round(array_sum(array_map(fn($r) => (int)$r['rating'], $rated)) / count($rated), 1) : 0;
    return ['n' => count($rows), 'rated' => count($rated), 'avg' => $avg];
}
function iv_score_save($post) {
    recruit_iv_migrate();
    $ivId = (int)($post['iv_id'] ?? 0);
    $member = trim((string)($post['member'] ?? ''));
    if (!$ivId || $member === '') return;
    $rating = max(0, min(5, (int)($post['srating'] ?? 0)));
    $rec = in_array($post['srecommendation'] ?? '', IV_RECOMMENDATIONS, true) ? $post['srecommendation'] : '';
    $comments = trim((string)($post['scomments'] ?? ''));
    $sid = (int)($post['score_id'] ?? 0);
    // Idempotent by member: a second save for the same interviewer updates their
    // row rather than adding a duplicate.
    if (!$sid) { try { $ex = ops_one("SELECT id FROM interview_scores WHERE interview_id=? AND member=?", [$ivId, $member]); if ($ex) $sid = (int)$ex['id']; } catch (Throwable $e) {} }
    if ($sid > 0) db()->prepare("UPDATE interview_scores SET member=?,rating=?,recommendation=?,comments=? WHERE id=?")
        ->execute([$member, $rating, $rec, $comments, $sid]);
    else db()->prepare("INSERT INTO interview_scores (interview_id,member,rating,recommendation,comments,created_by,created_at) VALUES (?,?,?,?,?,?,?)")
        ->execute([$ivId, $member, $rating, $rec, $comments, _iv_actor(), _iv_now()]);
}
function iv_score_delete($id) { recruit_iv_migrate(); db()->prepare("DELETE FROM interview_scores WHERE id=?")->execute([(int)$id]); }
// The interviewer names on an interview's panel (for the per-member picker).
function iv_panel_members($iv) {
    return array_values(array_filter(array_map('trim', explode(',', (string)($iv['panel'] ?? '')))));
}

function _iv_now() { return function_exists('now_iso') ? now_iso() : date('c'); }
function _iv_actor() { return function_exists('user_name') && function_exists('current_user') ? user_name(current_user()) : 'system'; }

// ============================================================================
//  Interviews
// ============================================================================
function iv_list($candidateId) {
    recruit_iv_migrate();
    return ops_all("SELECT * FROM interviews WHERE candidate_id=? ORDER BY id DESC", [(int)$candidateId]);
}
function iv_get($id) { recruit_iv_migrate(); return ops_one("SELECT * FROM interviews WHERE id=?", [(int)$id]) ?: null; }

//  PHASE 3 · M6 — scheduling an interview spends the requirement's time and the
//  panel's. It is asked the same integrated question as every other execution
//  step. Recording the OUTCOME of an interview that has already happened is not
//  gated: refusing to write down what took place would destroy information, and
//  it advances nothing.
//  WHY A ROUND CANNOT SIMPLY BE BOOKED TWICE.               (UAT A9, 2026-10-18)
//
//    "once L1 interview if completed and candidate has been rated it allows to
//     schedule L1 interview second, third and as many times it requires. Yes
//     there must be option to retry or reassess the candidate with reinterview
//     of rescheduling of the interveiw but to have scheduling multiple times
//     same level interview is a bug."
//
//  Exactly the right distinction, and the code made none: iv_schedule() wrote a
//  row every time it was called. Three L1s then sat in the list with no way to
//  tell a genuine re-assessment from a double booking somebody made by pressing
//  Schedule twice — and the owner's screenshot shows precisely that, two L1s
//  scheduled and a third already rated.
//
//  So the two cases are separated:
//
//    · the round is still SCHEDULED — there is nothing to re-assess yet, so a
//      second booking is a mistake. Refused, naming the one that exists.
//    · the round is finished (passed, failed, held, no-show) — a re-interview
//      is legitimate, and is now a deliberate act: it needs the re-interview
//      box ticked, and it is recorded as attempt 2, 3 … so the history reads
//      "L1 · re-interview 2" instead of three identical rows.
//
//  Returns 0 on refusal, with the reason in iv_last_error() for the caller to
//  flash — the route already flashes a reason when one is available.
function iv_last_error($set = null) {
    static $msg = '';
    if ($set !== null) $msg = (string) $set;
    return $msg;
}

function iv_round_state($candidateId, $round) {
    recruit_iv_migrate();
    try {
        $rows = ops_all("SELECT id, result, scheduled_at, attempt FROM interviews
                          WHERE candidate_id=? AND round=? ORDER BY id", [(int)$candidateId, (string)$round]);
    } catch (Throwable $e) { return ['open' => null, 'done' => 0, 'attempts' => 0]; }
    $open = null; $done = 0;
    foreach ($rows as $r) {
        if ((string)$r['result'] === 'SCHEDULED') $open = $r; else $done++;
    }
    return ['open' => $open, 'done' => $done, 'attempts' => count($rows)];
}

function iv_schedule($candidateId, $post) {
    recruit_iv_migrate();
    iv_last_error('');
    if (function_exists('rexec_cand_block_reason')
        && rexec_cand_block_reason((int) $candidateId, 'INTERVIEW') !== '') return 0;
    $round = in_array($post['round'] ?? '', IV_ROUNDS, true) ? $post['round'] : 'L1';
    $mode  = in_array($post['mode'] ?? '', IV_MODES, true) ? $post['mode'] : 'In person';

    $state = iv_round_state($candidateId, $round);
    if ($state['open']) {
        $when = trim((string)($state['open']['scheduled_at'] ?? ''));
        iv_last_error($round . ' is already scheduled' . ($when !== '' ? ' for ' . $when : '')
            . ' and has no outcome yet. Record its outcome first, or change the date on the one that exists —'
            . ' booking a second ' . $round . ' would leave two of them open.');
        return 0;
    }
    if ($state['done'] > 0 && empty($post['reinterview'])) {
        iv_last_error($round . ' has already been held and assessed. If you mean to re-assess this person,'
            . ' tick "This is a re-interview" and it will be recorded as ' . $round . ' attempt '
            . ($state['attempts'] + 1) . ' — that keeps the first result in the history instead of hiding it.');
        return 0;
    }
    $attempt = $state['attempts'] + 1;
    $reason = mb_substr(trim((string)($post['reason'] ?? '')), 0, 300);
    if ($state['done'] > 0 && $reason === '') {
        iv_last_error('Say why ' . $round . ' is being held again (e.g. "panel could not judge — technical test needed") — the first result stays in the history and this note explains the second.');
        return 0;
    }
    //  Owner, 9 Nov 2026 — "even when interview scheduling details like dates, who
    //  will take it etc. are blank it gets saved". An interview is a meeting: it
    //  needs a time, somebody to take it, and — unless it is a phone call — a place
    //  or a link.
    $when = str_replace('T', ' ', trim((string)($post['scheduled_at'] ?? '')));
    if ($when === '' || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $when) || strtotime($when) === false) {
        iv_last_error('Choose the date and time of the interview.'); return 0;
    }
    $when = substr($when, 0, 16);
    $whenStored = str_replace(' ', 'T', $when);          // kept in the form's own format, as before
    $panelIds = iv_panel_ids_from_post($post);
    $panelTxt = iv_panel_from_post($post);
    if ($panelIds === '' && trim($panelTxt) === '') {
        iv_last_error('Choose who will take the interview — tick one or more people, or type the name of an interviewer from outside.'); return 0;
    }
    $where = trim((string)($post['location'] ?? ''));
    if ($where === '' && $mode !== 'Phone') {
        iv_last_error($mode === 'Video' ? 'Add the video-call link.' : 'Add where the interview takes place.'); return 0;
    }
    //  Levels in order (owner: "let L1 complete the interview then only L2 can be
    //  scheduled"). L2 waits for L1's result, L3 for L2's. A level whose result
    //  was Fail / No show needs a reason to go further.
    $prevNeed = iv_level_gate((int)$candidateId, $round);
    if ($prevNeed !== '' && $reason === '') { iv_last_error($prevNeed); return 0; }
    if ($prevNeed !== '' && strpos($prevNeed, 'has no result yet') !== false) { iv_last_error($prevNeed); return 0; }
    //  Nobody — candidate or interviewer — is booked twice at the same time.
    foreach (ops_all("SELECT i.id, i.round, i.candidate_id, i.panel_user_ids FROM interviews i WHERE i.result='SCHEDULED' AND SUBSTR(REPLACE(i.scheduled_at,'T',' '),1,16)=?", [$when]) ?: [] as $o) {
        if ((int)$o['candidate_id'] === (int)$candidateId) { iv_last_error('This candidate already has ' . $o['round'] . ' at that time.'); return 0; }
        $clash = array_intersect(iv_panel_user_ids($o), array_map('intval', explode(',', $panelIds)));
        if ($panelIds !== '' && $clash) {
            $nm = iv_user_name((int)reset($clash));
            iv_last_error(($nm ?: 'One of the interviewers') . ' is already taking another interview at that time. Pick another time or another interviewer.'); return 0;
        }
    }

    db()->prepare("INSERT INTO interviews (candidate_id,round,attempt,mode,location,scheduled_at,panel,panel_user_ids,competencies,result,created_by,created_at,reason)
                   VALUES (?,?,?,?,?,?,?,?,?,'SCHEDULED',?,?,?)")
        ->execute([(int)$candidateId, $round, $attempt, $mode, $where,
            $whenStored, $panelTxt, $panelIds,
            trim((string)($post['competencies'] ?? '')), _iv_actor(), _iv_now(), $reason]);
    $newId = (int)db()->lastInsertId();
    if (function_exists('act_log')) try { act_log('CANDIDATE', (int)$candidateId, 'INTERVIEW',
        $round . ($attempt > 1 ? ' re-interview ' . $attempt : '') . ' scheduled for ' . $when . ' — panel: ' . $panelTxt . ($reason !== '' ? ' — reason: ' . $reason : '')); } catch (Throwable $e) {}
    //  The same person may sit on two levels (a small team often must), but the
    //  recruiter is told, because a second level is meant to be a second opinion.
    $same = [];
    foreach (ops_all("SELECT round, panel_user_ids FROM interviews WHERE candidate_id=? AND id<>? AND round<>?", [(int)$candidateId, $newId, $round]) ?: [] as $o)
        foreach (array_intersect(iv_panel_user_ids($o), array_map('intval', explode(',', $panelIds))) as $uid) $same[$uid][] = $o['round'];
    if ($same) {
        $names = [];
        foreach ($same as $uid => $rs) $names[] = (iv_user_name((int)$uid) ?: 'Someone') . ' (also on ' . implode(', ', array_unique($rs)) . ')';
        iv_last_notice('Note: ' . implode('; ', $names) . ' — a later level is meant to be a second opinion; use a different interviewer where you can.');
    }
    return $newId;
}

function iv_user_name($uid) {
    $u = ops_one("SELECT first_name, last_name FROM users WHERE id=?", [(int)$uid]);
    return $u ? trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) : '';
}
//  A second message channel for a scheduling that succeeded but deserves a word.
function iv_last_notice($set = null) {
    static $msg = '';
    if ($set !== null) $msg = (string) $set;
    return $msg;
}

//  The numbered levels go in order: L2 after L1 has a result, L3 after L2.
//  '' when this round may be booked; otherwise what is in the way. A previous
//  level that failed returns a message that a written reason overrides; one
//  still waiting for its result cannot be overridden.
function iv_level_gate($candidateId, $round) {
    $order = ['L1' => 1, 'L2' => 2, 'L3' => 3];
    if (!isset($order[$round]) || $order[$round] === 1) return '';
    $prev = array_search($order[$round] - 1, $order, true);
    $last = ops_one("SELECT result, recommendation FROM interviews WHERE candidate_id=? AND round=? ORDER BY id DESC LIMIT 1", [(int)$candidateId, $prev]);
    if (!$last) return $prev . ' has not been held yet. Hold ' . $prev . ' first — or, if this person is meant to skip ' . $prev . ', write the reason in "Reason".';
    $r = (string)$last['result'];
    if (in_array($r, ['SCHEDULED', 'RE_INTERVIEW'], true)) return $prev . ' has no result yet. Record ' . $prev . "'s result first; " . $round . ' can be booked once ' . $prev . ' is complete.';
    if (in_array($r, ['FAIL', 'NO_SHOW', 'CANCELLED'], true) || (string)$last['recommendation'] === 'No hire')
        return $prev . ' ended "' . (IV_RESULTS[$r] ?? $r) . '". To take this person to ' . $round . ' anyway, write the reason in "Reason".';
    return '';
}

//  The panel as ids. Separate from iv_panel_from_post() because that one also
//  carries free-text names for people who have no account (a client-side
//  interviewer), and those can never have an id.
function iv_panel_ids_from_post($post) {
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($post['panel_users'] ?? [])))));
    return $ids ? implode(',', $ids) : '';
}

function iv_panel_user_ids($iv) {
    return array_values(array_filter(array_map('intval', explode(',', (string)($iv['panel_user_ids'] ?? '')))));
}

//  INTERVIEWS THIS PERSON IS SITTING ON. The answer the dashboard could not
//  give. Scoped to the person asking — it reveals nothing about anybody else's
//  panels, and an empty list is the correct answer for most people.
function iv_my_upcoming($userId, $days = 7) {
    recruit_iv_migrate();
    $userId = (int) $userId;
    if ($userId <= 0) return [];
    $from = date('Y-m-d');
    $to   = date('Y-m-d', strtotime('+' . max(0, (int)$days) . ' day'));
    try {
        $rows = ops_all("SELECT i.*, c.first_name c_first, c.last_name c_last
                           FROM interviews i LEFT JOIN candidates c ON c.id = i.candidate_id
                          WHERE i.result='SCHEDULED' AND COALESCE(i.panel_user_ids,'') <> ''
                       ORDER BY i.scheduled_at");
    } catch (Throwable $e) { return []; }
    $out = [];
    foreach ($rows as $r) {
        if (!in_array($userId, iv_panel_user_ids($r), true)) continue;
        $d = substr(trim((string)$r['scheduled_at']), 0, 10);
        if ($d === '' || $d < $from || $d > $to) continue;
        $out[] = $r;
    }
    return $out;
}

function iv_my_upcoming_count($userId, $days = 7) { return count(iv_my_upcoming($userId, $days)); }
//  Returns [ok, message]. Owner, 9 Nov 2026 — "if the result has been recorded,
//  still it allows ... what will be the record kept". A recorded result is no
//  longer quietly overwritten: changing it needs a reason and is logged, it can
//  never be put back to "Scheduled" (book a re-interview instead), and a result
//  that contradicts its own recommendation is refused.
function iv_record($id, $post) {
    recruit_iv_migrate();
    $iv = ops_one("SELECT * FROM interviews WHERE id=?", [(int)$id]);
    if (!$iv) return [false, 'Interview not found.'];
    $result = array_key_exists($post['result'] ?? '', IV_RESULTS) ? $post['result'] : 'SCHEDULED';
    $rec = in_array($post['recommendation'] ?? '', IV_RECOMMENDATIONS, true) ? $post['recommendation'] : '';
    $rating = max(0, min(5, (int)($post['rating'] ?? 0)));
    $old = (string)$iv['result'];
    if ($old !== 'SCHEDULED' && $result === 'SCHEDULED')
        return [false, 'This interview already has a result. To assess the person again, schedule a re-interview — the first result stays on record.'];
    if ($result === 'PASS' && $rec === 'No hire') return [false, 'The result is Pass but the recommendation is "No hire". Correct one of them.'];
    if ($result === 'FAIL' && in_array($rec, ['Strong hire', 'Hire'], true)) return [false, 'The result is Fail but the recommendation is "' . $rec . '". Correct one of them.'];
    $why = mb_substr(trim((string)($post['change_reason'] ?? '')), 0, 300);
    $changing = $old !== 'SCHEDULED' && ($old !== $result || (string)$iv['recommendation'] !== $rec);
    if ($changing && $why === '') return [false, 'This interview\'s result was already recorded as ' . (IV_RESULTS[$old] ?? $old)
        . ($iv['recommendation'] !== '' ? ' (' . $iv['recommendation'] . ')' : '') . '. Write why it is being changed — the change is kept in the history.'];
    $done = in_array($result, ['PASS','FAIL','HOLD','NO_SHOW','CANCELLED'], true) ? ((string)$iv['done_at'] !== '' ? $iv['done_at'] : _iv_now()) : '';
    db()->prepare("UPDATE interviews SET result=?, rating=?, recommendation=?, competencies=?, questions=?, comments=?, done_at=? WHERE id=?")
        ->execute([$result, $rating, $rec, trim((string)($post['competencies'] ?? '')),
            trim((string)($post['questions'] ?? '')), trim((string)($post['comments'] ?? '')), $done, (int)$id]);
    if (function_exists('act_log')) try { act_log('CANDIDATE', (int)$iv['candidate_id'], 'INTERVIEW',
        $iv['round'] . ((int)$iv['attempt'] > 1 ? ' re-interview ' . (int)$iv['attempt'] : '') . ': '
        . ($changing ? 'result changed from ' . (IV_RESULTS[$old] ?? $old) . ($iv['recommendation'] !== '' ? ' / ' . $iv['recommendation'] : '') . ' to ' : 'result ')
        . (IV_RESULTS[$result] ?? $result) . ($rec !== '' ? ' / ' . $rec : '') . ($changing ? ' — reason: ' . $why : '')); } catch (Throwable $e) {}
    return [true, $changing ? 'Result changed — the earlier one and your reason are in the history.' : 'Interview outcome saved.'];
}
//  Only an interview with no result and no scores may be deleted (a booking made
//  by mistake); one that took place is part of the record. Returns [ok, message].
function iv_delete($id) {
    recruit_iv_migrate();
    $iv = ops_one("SELECT * FROM interviews WHERE id=?", [(int)$id]);
    if (!$iv) return [false, 'Interview not found.'];
    if (!in_array((string)$iv['result'], ['SCHEDULED', 'CANCELLED'], true) || iv_scores((int)$id))
        return [false, 'This interview has a result or scores, so it stays on record. If it should not count, record its result as Cancelled with a note.'];
    db()->prepare("DELETE FROM interviews WHERE id=?")->execute([(int)$id]);
    if (function_exists('act_log')) try { act_log('CANDIDATE', (int)$iv['candidate_id'], 'INTERVIEW', $iv['round'] . ' booking for ' . $iv['scheduled_at'] . ' removed by ' . _iv_actor()); } catch (Throwable $e) {}
    return [true, 'Interview removed.'];
}

//  ---- ONE DECISION ACROSS ALL LEVELS (owner, 9 Nov 2026) -------------------
//    "Suppose L1 gives good score and recommends strong hire or hire while L2
//     makes fail and no hire — what will happen ... What about such conflict."
//  The rule: each level's LATEST attempt counts; the HIGHEST level that has a
//  result decides (L3 over L2 over L1, then any other round in the order held);
//  a disagreement between levels is shown as a conflict, with the three ways
//  forward — reject, a tie-break interview at a higher level, or a re-interview
//  of the deciding level with a reason. Nothing moves the candidate by itself.
function iv_overall($candidateId) {
    $rows = ops_all("SELECT * FROM interviews WHERE candidate_id=? ORDER BY id", [(int)$candidateId]) ?: [];
    $latest = [];
    foreach ($rows as $r) $latest[$r['round']] = $r;           // last attempt of each round
    if (!$latest) return null;
    $rank = fn($round) => ['L1' => 1, 'L2' => 2, 'L3' => 3][$round] ?? 0;
    $pending = array_values(array_filter($latest, fn($r) => in_array((string)$r['result'], ['SCHEDULED', 'RE_INTERVIEW'], true)));
    $decided = array_values(array_filter($latest, fn($r) => in_array((string)$r['result'], ['PASS', 'FAIL', 'HOLD', 'NO_SHOW'], true)));
    $neg = fn($r) => in_array((string)$r['result'], ['FAIL', 'NO_SHOW'], true) || (string)$r['recommendation'] === 'No hire';
    $pos = fn($r) => (string)$r['result'] === 'PASS' || in_array((string)$r['recommendation'], ['Strong hire', 'Hire'], true);
    $deciding = null;
    foreach ($decided as $r) {
        if (!$deciding) { $deciding = $r; continue; }
        $a = $rank($r['round']); $b = $rank($deciding['round']);
        if ($a > $b || ($a === $b && (int)$r['id'] > (int)$deciding['id'])) $deciding = $r;
    }
    $conflict = $decided && array_filter($decided, $neg) && array_filter($decided, $pos);
    $out = ['pending' => $pending, 'decided' => $decided, 'deciding' => $deciding, 'conflict' => (bool)$conflict, 'suggest' => null, 'why' => ''];
    if ($pending) { $out['why'] = 'Waiting for ' . implode(', ', array_map(fn($r) => $r['round'], $pending)) . '.'; return $out; }
    if (!$deciding) return $out;
    $lbl = $deciding['round'] . ((int)$deciding['attempt'] > 1 ? ' (re-interview ' . (int)$deciding['attempt'] . ')' : '');
    if ($neg($deciding)) {
        $out['suggest'] = 'REJECTED';
        $out['why'] = $lbl . ' — the highest level held — did not recommend this person.';
    } elseif ((string)$deciding['result'] === 'HOLD') {
        $out['suggest'] = 'HOLD';
        $out['why'] = $lbl . ' put this person on hold.';
    } elseif ($pos($deciding)) {
        $out['suggest'] = 'OFFERED';
        $out['why'] = $lbl . ' — the highest level held — recommends this person.';
    }
    if ($conflict) $out['why'] .= ' The levels disagree: ' . implode(' · ', array_map(fn($r) => $r['round'] . ' ' . (IV_RESULTS[$r['result']] ?? $r['result']) . ($r['recommendation'] !== '' ? ' / ' . $r['recommendation'] : ''), $decided))
        . '. The higher level decides. If you are not sure, hold a tie-break interview at a higher level (e.g. L3 or Management), or re-interview ' . $deciding['round'] . ' with a reason.';
    return $out;
}

// The people who can sit on an interview panel — active users, with their
// department so the panel can default to the candidate's own department.
function iv_interviewers() {
    recruit_iv_migrate();
    try {
        return ops_all("SELECT id, first_name, last_name, COALESCE(department,'') department, COALESCE(role,'') role
                        FROM users WHERE is_active=1 ORDER BY first_name, last_name");
    } catch (Throwable $e) { return []; }
}
// The department a candidate is being hired into (candidate row, else its
// requisition, else the requisition's position) — used to pre-tick the panel.
function iv_candidate_department($cand) {
    $dept = trim((string)($cand['department'] ?? ''));
    if ($dept === '' && !empty($cand['requisition_id'])) {
        $req = ops_one("SELECT department, position_id FROM requisitions WHERE id=?", [(int)$cand['requisition_id']]);
        $dept = trim((string)($req['department'] ?? ''));
        if ($dept === '' && $req && !empty($req['position_id']) && function_exists('position_get')) {
            $pos = position_get((int)$req['position_id']); if ($pos) $dept = trim((string)($pos['department'] ?? ''));
        }
    }
    return $dept;
}
// Build the stored panel string from the multi-select (user ids) plus any free
// text for people not in the system (e.g. a client-side interviewer). Falls back
// to a legacy plain 'panel' text field so older forms still work.
function iv_panel_from_post($post) {
    $names = [];
    $ids = array_values(array_filter(array_map('intval', (array)($post['panel_users'] ?? []))));
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        try {
            foreach (ops_all("SELECT first_name, last_name FROM users WHERE id IN ($in)", $ids) as $u) {
                $nm = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                if ($nm !== '') $names[] = $nm;
            }
        } catch (Throwable $e) { /* ignore — fall through to extra/legacy */ }
    }
    $extra = trim((string)($post['panel_extra'] ?? ''));
    if ($extra !== '') $names[] = $extra;
    if (!$names && trim((string)($post['panel'] ?? '')) !== '') return trim((string)$post['panel']);
    return implode(', ', $names);
}

// ============================================================================
//  Documents
// ============================================================================
function docs_list($candidateId) {
    recruit_iv_migrate();
    return ops_all("SELECT * FROM candidate_docs WHERE candidate_id=? ORDER BY id", [(int)$candidateId]);
}
function doc_get($id) { recruit_iv_migrate(); return ops_one("SELECT * FROM candidate_docs WHERE id=?", [(int)$id]) ?: null; }
// Documents captured against one pipeline stage (the Pipeline tab).
function docs_for_stage($candidateId, $stageId) {
    recruit_iv_migrate();
    try { return ops_all("SELECT * FROM candidate_docs WHERE candidate_id=? AND pipeline_stage_id=? ORDER BY id", [(int)$candidateId, (int)$stageId]); }
    catch (Throwable $e) { return []; }
}

// The effective (display) status — a verified doc whose expiry has passed reads EXPIRED.
function doc_effective_status($d) {
    $s = (string)($d['status'] ?? '');
    if ($s === 'VERIFIED' && !empty($d['expiry_date']) && strtotime($d['expiry_date']) && strtotime($d['expiry_date']) < strtotime(date('Y-m-d')))
        return 'EXPIRED';
    return $s;
}

// Add a required/requested document row (a checklist item, no file yet).
function doc_add($candidateId, $post) {
    recruit_iv_migrate();
    $type = trim((string)($post['doc_type'] ?? 'Other')) ?: 'Other';
    $status = array_key_exists($post['status'] ?? '', DOC_STATUSES) ? $post['status'] : 'REQUIRED';
    db()->prepare("INSERT INTO candidate_docs (candidate_id,doc_type,`sensitive`,status,created_at) VALUES (?,?,?,?,?)")
        ->execute([(int)$candidateId, $type, doc_is_sensitive($type) ? 1 : 0, $status, _iv_now()]);
    return (int)db()->lastInsertId();
}
// Attach an uploaded file to a doc row (or create one on the fly), status → UPLOADED.
function doc_upload($candidateId, $post, $file) {
    recruit_iv_migrate();
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return [false, 'No file chosen.'];
    $bytes = file_get_contents($file['tmp_name']);
    if (strlen($bytes) > 12 * 1024 * 1024) return [false, 'File too large (max 12 MB).'];
    $data = 'data:' . ($file['type'] ?: 'application/octet-stream') . ';base64,' . base64_encode($bytes);
    $name = basename($file['name']);
    $id = (int)($post['doc_id'] ?? 0);
    $issue = trim((string)($post['issue_date'] ?? ''));
    $expiry = trim((string)($post['expiry_date'] ?? ''));
    if ($id > 0) {
        db()->prepare("UPDATE candidate_docs SET file_name=?, file_data=?, issue_date=?, expiry_date=?, status='UPLOADED', uploaded_by=?, uploaded_at=?, rejection_reason='' WHERE id=?")
            ->execute([$name, $data, $issue, $expiry, _iv_actor(), _iv_now(), $id]);
    } else {
        $type = trim((string)($post['doc_type'] ?? 'Other')) ?: 'Other';
        $stageId = (int)($post['stage_id'] ?? 0) ?: null;   // captured against a pipeline stage, when provided
        db()->prepare("INSERT INTO candidate_docs (candidate_id,doc_type,`sensitive`,file_name,file_data,issue_date,expiry_date,status,pipeline_stage_id,uploaded_by,uploaded_at,created_at)
                       VALUES (?,?,?,?,?,?,?, 'UPLOADED', ?,?,?,?)")
            ->execute([(int)$candidateId, $type, doc_is_sensitive($type) ? 1 : 0, $name, $data, $issue, $expiry, $stageId, _iv_actor(), _iv_now(), _iv_now()]);
    }
    return [true, 'Document uploaded.'];
}
function doc_verify($id) {
    recruit_iv_migrate();
    db()->prepare("UPDATE candidate_docs SET status='VERIFIED', verifier=?, verified_at=?, rejection_reason='' WHERE id=?")
        ->execute([_iv_actor(), _iv_now(), (int)$id]);
}
function doc_reject($id, $reason, $resubmit = false) {
    recruit_iv_migrate();
    db()->prepare("UPDATE candidate_docs SET status=?, verifier=?, verified_at=?, rejection_reason=? WHERE id=?")
        ->execute([$resubmit ? 'RESUBMIT' : 'REJECTED', _iv_actor(), _iv_now(), substr((string)$reason, 0, 300), (int)$id]);
}
function doc_status_set($id, $status) {
    recruit_iv_migrate();
    if (!array_key_exists($status, DOC_STATUSES)) return;
    db()->prepare("UPDATE candidate_docs SET status=? WHERE id=?")->execute([$status, (int)$id]);
}
function doc_delete($id) { recruit_iv_migrate(); db()->prepare("DELETE FROM candidate_docs WHERE id=?")->execute([(int)$id]); }

// A candidate may download a sensitive file only if admin-level.
function doc_can_download($d) {
    if ((int)($d['sensitive'] ?? 0) !== 1) return true;
    return function_exists('is_admin_level') && is_admin_level();
}

// ============================================================================
//  Routes
// ============================================================================
function ops_candidate_interview($route, $method) {
    recruit_iv_migrate();
    ops_require(is_coordinator_level(), 'Only coordinators / administrators can manage interviews.');
    $id = (int)($_GET['id'] ?? 0);
    $cand = ops_one("SELECT * FROM candidates WHERE id=?", [$id]);
    if (!$cand) { http_response_code(404); view('notfound'); return true; }
    if ($method === 'POST') {
        $do = (string)($_POST['do'] ?? '');
        //  PHASE 3 · M6 (adversarial audit) — SAY WHAT ACTUALLY HAPPENED.
        //
        //  This announced "Interview scheduled." whatever came back. Once the
        //  execution gate started refusing, a coordinator working on a blocked
        //  requirement was told the interview was booked while nothing had been
        //  written — a failed operation reporting success, which is the one thing
        //  invariant I18 forbids, and worse than the original gap because the
        //  person then acts on a meeting that does not exist.
        if ($do === 'schedule') {
            $ivNew = iv_schedule($id, $_POST);
            if ((int) $ivNew > 0) { flash('Interview scheduled.'); if (iv_last_notice() !== '') flash(iv_last_notice(), 'warning'); }
            else {
                //  iv_schedule()'s own reason first — it is the specific one
                //  ("L1 is already scheduled for ...") and the only one that
                //  tells the person what to do instead.
                $ivWhy = iv_last_error();
                if ($ivWhy === '' && function_exists('rexec_cand_block_reason'))
                    $ivWhy = rexec_cand_block_reason($id, 'INTERVIEW');
                flash($ivWhy !== '' ? $ivWhy : 'The interview could not be scheduled.', 'error');
            }
        }
        elseif ($do === 'record') { [$ok, $m] = iv_record((int)($_POST['iv_id'] ?? 0), $_POST); flash($m, $ok ? 'success' : 'error'); }
        elseif ($do === 'delete') { [$ok, $m] = iv_delete((int)($_POST['iv_id'] ?? 0)); flash($m, $ok ? 'success' : 'error'); }
        elseif ($do === 'score_save') { iv_score_save($_POST); flash('Panel member score saved.'); }
        elseif ($do === 'score_delete') { iv_score_delete((int)($_POST['score_id'] ?? 0)); flash('Score removed.'); }
    }
    //  See lib/recruit_offer.php: the open panel is carried by the form now.
    redirect('/candidate?id=' . $id);
    return true;
}

function ops_candidate_doc($route, $method) {
    recruit_iv_migrate();
    ops_require(is_coordinator_level(), 'Only coordinators / administrators can manage documents.');
    $id = (int)($_GET['id'] ?? 0);
    $cand = ops_one("SELECT * FROM candidates WHERE id=?", [$id]);
    if (!$cand) { http_response_code(404); view('notfound'); return true; }
    if ($method === 'POST') {
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'add') { doc_add($id, $_POST); flash('Document added to the checklist.'); }
        elseif ($do === 'upload') { [$ok, $msg] = doc_upload($id, $_POST, $_FILES['file'] ?? []); flash($msg, $ok ? 'success' : 'error'); }
        elseif ($do === 'verify') { doc_verify((int)($_POST['doc_id'] ?? 0)); flash('Document verified.'); }
        elseif ($do === 'reject') { doc_reject((int)($_POST['doc_id'] ?? 0), $_POST['reason'] ?? '', !empty($_POST['resubmit'])); flash('Document marked ' . (!empty($_POST['resubmit']) ? 'for resubmission' : 'rejected') . '.'); }
        elseif ($do === 'status') { doc_status_set((int)($_POST['doc_id'] ?? 0), (string)($_POST['status'] ?? '')); flash('Document status updated.'); }
        elseif ($do === 'delete') { doc_delete((int)($_POST['doc_id'] ?? 0)); flash('Document removed.'); }
    }
    //  See lib/recruit_offer.php: the open panel is carried by the form now.
    redirect('/candidate?id=' . $id);
    return true;
}

// ============================================================================
//  Panels (rendered as tabs on the candidate screen)
// ============================================================================
function recruit_iv_panel($cand) {
    if (!is_array($cand) || empty($cand['id'])) return;
    $ivs = iv_list((int)$cand['id']);
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $can = function_exists('is_coordinator_level') && is_coordinator_level();
    $ivUsers = $can ? iv_interviewers() : [];
    $ivDept  = strtolower(trim(iv_candidate_department($cand)));
    $pill = ['PASS'=>'p-ok','FAIL'=>'p-bad','HOLD'=>'p-warn','RE_INTERVIEW'=>'p-warn','NO_SHOW'=>'p-bad','CANCELLED'=>'p-mut','SCHEDULED'=>'p-info'];
    ?>
    <div class="panel">
      <h3 class="tab-sub">Interviews <span class="muted" style="font-weight:400">(<?= count($ivs) ?>)</span></h3>
      <?php if ($can): ?>
      <form method="post" action="/candidate-interview?id=<?= (int)$cand['id'] ?>" style="border:1px solid var(--line,#e5e7eb);border-radius:10px;padding:11px 13px;margin-bottom:12px">
        <input type="hidden" name="do" value="schedule">
        <b style="font-size:12.5px">Schedule an interview</b>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-top:6px">
          <div><label class="ff-l">Round</label><select class="form-control" name="round"><?php foreach (IV_ROUNDS as $r): ?><option><?= $e($r) ?></option><?php endforeach; ?></select></div>
          <div><label class="ff-l">When *</label><input class="form-control" type="datetime-local" name="scheduled_at" required></div>
          <div><label class="ff-l">Mode</label><select class="form-control" name="mode"><?php foreach (IV_MODES as $m): ?><option><?= $e($m) ?></option><?php endforeach; ?></select></div>
          <div><label class="ff-l">Location / link <span class="muted" style="font-weight:400">— not needed for a phone call</span></label><input class="form-control" name="location"></div>
          <div style="grid-column:1/-1"><label class="ff-l">Competencies to assess</label><input class="form-control" name="competencies"></div>
        </div>
        <?php // Interview panel — pick one or more interviewers from your people.
              //  Those in the candidate's own department are pre-ticked; change
              //  them freely, and add anyone not in the system in the box below. ?>
        <?php //  ---- THE PANEL PICKER (UAT A9) ---------------------------------
              //
              //    "No filter on the interviewer panel as all the people without
              //     recruitment module also shows in the interview panel list"
              //
              //  Correct: the list was every active user, alphabetically — so
              //  Finance and a site inspector sat beside the people who would
              //  actually interview a QA engineer.
              //
              //  It is a FILTER and not a gate, deliberately. The right
              //  technical interviewer for a role very often has no recruitment
              //  access at all — they are the senior person in that discipline.
              //  Hiding them would have replaced a cluttered list with a list
              //  missing the people you need, which is the worse failure.
              //
              //  So: the candidate's own department first and pre-ticked, a
              //  search box, a department filter that starts on that department,
              //  and everybody still reachable in one click of "All".
              $ivByDept = [];
              foreach ($ivUsers as $u) {
                  $nm = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                  if ($nm === '') continue;
                  $d = trim((string)($u['department'] ?? ''));
                  $ivByDept[$d === '' ? '—' : $d][] = $u + ['_nm' => $nm];
              }
              ksort($ivByDept);
              //  The candidate's department leads the list.
              $ivDeptName = iv_candidate_department($cand);
              if ($ivDeptName !== '' && isset($ivByDept[$ivDeptName]))
                  $ivByDept = [$ivDeptName => $ivByDept[$ivDeptName]] + $ivByDept;
        ?>
        <div style="margin-top:10px">
          <label class="ff-l">Interview panel — choose interviewers<?= $ivDept !== '' ? ' <span class="muted" style="font-weight:400">(' . $e($ivDeptName) . ' is pre-selected — this is the candidate\'s department)</span>' : '' ?></label>
          <?php if ($ivUsers): ?>
          <div style="display:flex;gap:8px;margin:6px 0 7px;flex-wrap:wrap">
            <input class="form-control ivp-find" type="search" placeholder="Find a person…" style="flex:1 1 180px;min-width:0" aria-label="Find a panel member">
            <select class="form-control ivp-dept" style="flex:0 1 200px" aria-label="Filter the panel list by department">
              <option value="">All departments</option>
              <?php foreach (array_keys($ivByDept) as $dn): ?>
                <option value="<?= $e($dn) ?>" <?= ($ivDeptName !== '' && $dn === $ivDeptName) ? 'selected' : '' ?>><?= $e($dn) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ivp-list" style="max-height:170px;overflow:auto;border:1px solid var(--line,#e5e7eb);border-radius:9px;padding:9px 11px;background:var(--surface,#fff)">
            <?php foreach ($ivByDept as $dn => $us): ?>
              <div class="ivp-grp" data-dept="<?= $e($dn) ?>">
                <div class="muted" style="font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;margin:4px 0 3px"><?= $e($dn) ?></div>
                <div style="display:flex;flex-wrap:wrap;gap:6px 14px">
                <?php foreach ($us as $u):
                      $pre = ($ivDept !== '' && strtolower(trim((string)($u['department'] ?? ''))) === $ivDept); ?>
                  <label class="ivp-row" data-nm="<?= $e(mb_strtolower($u['_nm'])) ?>"
                         style="display:inline-flex;align-items:center;gap:6px;font-size:13px;white-space:nowrap">
                    <input type="checkbox" name="panel_users[]" value="<?= (int)$u['id'] ?>" <?= $pre ? 'checked' : '' ?>>
                    <?= $e($u['_nm']) ?>
                    <?php if (($u['role'] ?? '') !== ''): ?><span class="muted" style="font-size:11px">· <?= $e((defined('ORG_ROLES') && isset(ORG_ROLES[$u['role']])) ? ORG_ROLES[$u['role']] : $u['role']) ?></span><?php endif; ?>
                  </label>
                <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
            <p class="muted ivp-none" style="display:none;font-size:12px;margin:6px 0">Nobody matches that. Clear the search, or choose All departments.</p>
          </div>
          <?php endif; ?>
          <input class="form-control" name="panel_extra" placeholder="<?= $ivUsers ? 'Other panelists not in the system (e.g. a client-side interviewer)' : 'Panel / interviewers (names)' ?>" style="margin-top:7px">
        </div>
        <?php //  A re-interview is a deliberate act, not an accidental second
              //  booking. iv_schedule() refuses a repeat of a finished round
              //  unless this is ticked, and records it as attempt 2, 3 … ?>
        <label style="display:inline-flex;align-items:center;gap:7px;margin-top:10px;font-size:13px">
          <input type="checkbox" name="reinterview" value="1">
          This is a <b>re-interview</b> — the same round has already been held and assessed
        </label>
        <div style="margin-top:8px"><label class="ff-l">Reason <span class="muted" style="font-weight:400">— needed for a re-interview, or to book a level before the one below it is passed</span></label>
          <input class="form-control" name="reason" maxlength="300" placeholder="e.g. L1 panel unsure on design skills — second technical opinion"></div>
        <p class="muted" style="font-size:12px;margin:6px 0 0">Levels go in order: L2 can be booked once L1 has a result, L3 once L2 has. Everyone on the panel sees it in My interviews.</p>
        <div style="margin-top:10px"><button class="btn">Schedule</button></div>
      </form>
      <?php endif; ?>
      <?php if (!$ivs): ?><p class="muted">No interviews yet.</p><?php endif; ?>
      <?php $ov = $ivs ? iv_overall((int)$cand['id']) : null;
            $flowCls = function_exists('rpipe_current_state') ? (rpipe_current_state($cand)['class'] ?? 'ACTIVE') : 'ACTIVE';
            $ivStNow = strtoupper((string)($cand['stage'] ?? ''));
            if ($ov && ($ov['why'] !== '')): $ivStages = function_exists('lk_options_or') ? lk_options_or('candidate_stage', CAND_STAGES) : CAND_STAGES; ?>
        <div style="border:1px solid <?= $ov['conflict'] ? '#fcd34d' : 'var(--line,#e5e7eb)' ?>;background:<?= $ov['conflict'] ? '#fffbeb' : 'var(--soft,#f8fafc)' ?>;border-radius:10px;padding:10px 12px;margin-bottom:10px;font-size:13px">
          <b><?= $ov['conflict'] ? '⚖️ The interview levels disagree' : 'Where the interviews stand' ?></b><br><?= $e($ov['why']) ?>
          <?php if ($can && $ov['suggest'] && $flowCls === 'ACTIVE' && $ivStNow !== $ov['suggest'] && !in_array($ivStNow, ['ACCEPTED', 'WITHDRAWN', 'REJECTED', 'OFFER_DECLINED'], true)): ?>
          <form method="post" action="/candidate-stage?id=<?= (int)$cand['id'] ?>" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <input type="hidden" name="to_stage" value="<?= $e($ov['suggest']) ?>">
            <input type="hidden" name="remark" value="<?= $e('Interviews: ' . implode(' · ', array_map(fn($r) => $r['round'] . ' ' . (IV_RESULTS[$r['result']] ?? $r['result']) . ($r['recommendation'] !== '' ? ' / ' . $r['recommendation'] : ''), $ov['decided']))) ?>">
            <?php if ($ov['suggest'] === 'REJECTED'): ?><input type="hidden" name="drop_point" value="IN_BETWEEN"><input type="hidden" name="drop_reason" value="PROFILE_MISMATCH"><?php endif; ?>
            <button class="btn small"><?= $e(['REJECTED' => 'Reject this candidate', 'HOLD' => 'Put on hold', 'OFFERED' => 'Move to offer'][$ov['suggest']] ?? ('Move to ' . ($ivStages[$ov['suggest']] ?? $ov['suggest']))) ?></button>
            <span class="muted" style="font-size:11.5px">or leave them where they are and decide later</span>
          </form>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php foreach ($ivs as $iv): $st = (string)$iv['result'];
            $ivScores = iv_scores((int)$iv['id']); $ivSum = iv_score_summary((int)$iv['id']); $ivMembers = iv_panel_members($iv); ?>
        <div style="border:1px solid var(--line,#e5e7eb);border-radius:10px;padding:11px 13px;margin-bottom:10px">
          <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:baseline">
            <b><?= $e($iv['round']) ?> · <?= $e($iv['mode']) ?><?= $iv['scheduled_at'] ? ' · ' . $e(function_exists('fdate') ? fdate(substr($iv['scheduled_at'],0,10), substr($iv['scheduled_at'],0,10)) . ' ' . substr($iv['scheduled_at'],11,5) : $iv['scheduled_at']) : '' ?></b>
            <span class="pill <?= $pill[$st] ?? 'p-mut' ?>"><?= $e(IV_RESULTS[$st] ?? $st) ?><?= (int)$iv['rating'] ? ' · ' . str_repeat('★', (int)$iv['rating']) : '' ?></span>
          </div>
          <?php if ($iv['panel']): ?><div class="muted" style="font-size:12px">Panel: <?= $e($iv['panel']) ?><?= $iv['location'] ? ' · ' . $e($iv['location']) : '' ?></div><?php endif; ?>
          <?php if ($iv['competencies']): ?><div class="muted" style="font-size:12px">Competencies: <?= $e($iv['competencies']) ?></div><?php endif; ?>
          <?php if (trim((string)($iv['reason'] ?? '')) !== ''): ?><div class="muted" style="font-size:12px">Why booked: <?= $e($iv['reason']) ?></div><?php endif; ?>
          <?php if ($iv['recommendation'] || $iv['comments']): ?><div style="font-size:12.5px;margin-top:4px"><?= $iv['recommendation'] ? '<b>' . $e($iv['recommendation']) . '</b> — ' : '' ?><?= $e($iv['comments']) ?></div><?php endif; ?>
          <?php // Individual panel-member scores, with the average across the panel. ?>
          <?php if ($ivScores): ?>
          <div style="margin-top:7px;border-top:1px dashed var(--line,#e5e7eb);padding-top:7px">
            <div class="muted" style="font-size:11.5px;font-weight:600;margin-bottom:3px">Panel scores<?= $ivSum['rated'] ? ' · average ' . $ivSum['avg'] . ' ★ (' . $ivSum['rated'] . ')' : '' ?></div>
            <?php foreach ($ivScores as $sc): ?>
              <div style="font-size:12.5px;padding:2px 0"><b><?= $e($sc['member']) ?></b><?= (int)$sc['rating'] ? ' · ' . str_repeat('★', (int)$sc['rating']) : '' ?><?= $sc['recommendation'] ? ' · ' . $e($sc['recommendation']) : '' ?><?= trim((string)$sc['comments']) !== '' ? ' — ' . $e($sc['comments']) : '' ?>
                <?php if ($can): ?><form method="post" action="/candidate-interview?id=<?= (int)$cand['id'] ?>" style="display:inline" onsubmit="return confirm('Remove this score?')"><input type="hidden" name="do" value="score_delete"><input type="hidden" name="score_id" value="<?= (int)$sc['id'] ?>"><button class="btn secondary" style="padding:1px 6px;font-size:10.5px;margin-left:4px">✕</button></form><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <?php if ($can): ?>
          <details style="margin-top:7px"><summary style="cursor:pointer;font-size:12px;color:var(--brand,#1e40af)">Add / edit a panel member's score</summary>
            <form method="post" action="/candidate-interview?id=<?= (int)$cand['id'] ?>" style="margin-top:8px;border:1px solid var(--line-2,#eef1f5);border-radius:8px;padding:9px 11px">
              <input type="hidden" name="do" value="score_save"><input type="hidden" name="iv_id" value="<?= (int)$iv['id'] ?>">
              <div style="display:grid;grid-template-columns:1.4fr .7fr 1fr;gap:9px">
                <div><label class="ff-l">Interviewer</label>
                  <input class="form-control" name="member" list="ivmem_<?= (int)$iv['id'] ?>" placeholder="panel member" autocomplete="off">
                  <?php if ($ivMembers): ?><datalist id="ivmem_<?= (int)$iv['id'] ?>"><?php foreach ($ivMembers as $mm): ?><option value="<?= $e($mm) ?>"></option><?php endforeach; ?></datalist><?php endif; ?>
                </div>
                <div><label class="ff-l">Rating (0–5)</label><input class="form-control" type="number" min="0" max="5" name="srating" value="0"></div>
                <div><label class="ff-l">Recommendation</label><select class="form-control" name="srecommendation"><?php foreach (IV_RECOMMENDATIONS as $r): ?><option value="<?= $e($r) ?>"><?= $e($r ?: '—') ?></option><?php endforeach; ?></select></div>
              </div>
              <label class="ff-l" style="margin-top:7px">This interviewer's comments</label><input class="form-control" name="scomments" placeholder="their notes on the candidate">
              <div style="margin-top:8px"><button class="btn secondary" style="padding:5px 12px;font-size:12.5px">Save this interviewer's score</button></div>
            </form>
          </details>
          <details style="margin-top:6px"><summary style="cursor:pointer;font-size:12px;color:var(--brand,#1e40af)">Record / edit overall outcome</summary>
            <form method="post" action="/candidate-interview?id=<?= (int)$cand['id'] ?>" style="margin-top:8px">
              <input type="hidden" name="do" value="record"><input type="hidden" name="iv_id" value="<?= (int)$iv['id'] ?>">
              <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
                <div><label class="ff-l">Result</label><select class="form-control" name="result"><?php foreach (IV_RESULTS as $k=>$v): ?><option value="<?= $k ?>" <?= $st===$k?'selected':'' ?>><?= $e($v) ?></option><?php endforeach; ?></select></div>
                <div><label class="ff-l">Rating (0–5)</label><input class="form-control" type="number" min="0" max="5" name="rating" value="<?= (int)$iv['rating'] ?>"></div>
                <div><label class="ff-l">Recommendation</label><select class="form-control" name="recommendation"><?php foreach (IV_RECOMMENDATIONS as $r): ?><option value="<?= $e($r) ?>" <?= $iv['recommendation']===$r?'selected':'' ?>><?= $e($r ?: '—') ?></option><?php endforeach; ?></select></div>
              </div>
              <label class="ff-l" style="margin-top:8px">Competencies assessed</label><input class="form-control" name="competencies" value="<?= $e($iv['competencies']) ?>">
              <label class="ff-l" style="margin-top:8px">Questions asked</label><textarea class="form-control" name="questions" rows="2"><?= $e($iv['questions']) ?></textarea>
              <label class="ff-l" style="margin-top:8px">Comments</label><textarea class="form-control" name="comments" rows="2"><?= $e($iv['comments']) ?></textarea>
              <?php if ($st !== 'SCHEDULED'): ?>
              <label class="ff-l" style="margin-top:8px">Reason for changing the recorded result <span class="muted" style="font-weight:400">— needed only if you change it; kept in the history</span></label>
              <input class="form-control" name="change_reason" maxlength="300">
              <?php endif; ?>
              <div style="margin-top:8px;display:flex;gap:8px"><button class="btn">Save scorecard</button>
                <?php if (in_array($st, ['SCHEDULED', 'CANCELLED'], true) && !$ivScores): ?><button class="btn secondary" name="do" value="delete" onclick="return confirm('Remove this booking? Use this only for a booking made by mistake.')">Delete booking</button><?php endif; ?></div>
            </form>
          </details>
          <?php endif; ?>

          <?php // The move a result suggests is offered once, above the cards (iv_overall()). ?>
        </div>
      <?php endforeach; ?>
    </div>
    <style>.ff-l{display:block;font-size:11.5px;font-weight:600;color:var(--muted,#656e7a);margin-bottom:3px}</style>
    <?php
}

function recruit_docs_panel($cand) {
    if (!is_array($cand) || empty($cand['id'])) return;
    $docs = docs_list((int)$cand['id']);
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $can = function_exists('is_coordinator_level') && is_coordinator_level();
    $pill = ['VERIFIED'=>'p-ok','REJECTED'=>'p-bad','EXPIRED'=>'p-bad','RESUBMIT'=>'p-warn','UNDER_REVIEW'=>'p-info','UPLOADED'=>'p-info','REQUESTED'=>'p-warn','REQUIRED'=>'p-mut','NOT_REQUIRED'=>'p-mut'];
    ?>
    <div class="panel">
      <h3 class="tab-sub">Documents <span class="muted" style="font-weight:400">(<?= count($docs) ?>)</span></h3>
      <?php if ($can): ?>
      <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:12px">
        <form method="post" action="/candidate-doc?id=<?= (int)$cand['id'] ?>" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
          <input type="hidden" name="do" value="add">
          <div><label class="ff-l">Add a required document</label>
            <select class="form-control" name="doc_type"><?php foreach (doc_types() as $t): ?><option><?= $e($t) ?></option><?php endforeach; ?></select></div>
          <button class="btn secondary">Add to checklist</button>
        </form>
        <form method="post" action="/candidate-doc?id=<?= (int)$cand['id'] ?>" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
          <input type="hidden" name="do" value="upload">
          <div><label class="ff-l">Or upload a document</label>
            <select class="form-control" name="doc_type"><?php foreach (doc_types() as $t): ?><option><?= $e($t) ?></option><?php endforeach; ?></select></div>
          <div><label class="ff-l">File (≤12 MB)</label><input class="form-control" type="file" name="file"></div>
          <div><label class="ff-l">Expiry (optional)</label><input class="form-control" type="date" name="expiry_date"></div>
          <button class="btn">Upload</button>
        </form>
      </div>
      <?php endif; ?>
      <?php if (!$docs): ?><p class="muted">No documents yet — add the ones you need to the checklist.</p><?php else: ?>
      <table style="width:100%;border-collapse:collapse">
        <tr><th style="text-align:left;font-size:10.5px;text-transform:uppercase;color:var(--muted,#656e7a);padding:6px 8px">Document</th><th style="text-align:left;font-size:10.5px;text-transform:uppercase;color:var(--muted,#656e7a);padding:6px 8px">Status</th><th style="text-align:left;font-size:10.5px;text-transform:uppercase;color:var(--muted,#656e7a);padding:6px 8px">Dates</th><th></th></tr>
        <?php foreach ($docs as $d): $st = doc_effective_status($d); ?>
        <tr style="border-top:1px solid var(--line,#eef1f5)">
          <td style="padding:8px"><b><?= $e($d['doc_type']) ?></b><?= (int)$d['sensitive'] ? ' <span class="pill p-mut" style="font-size:10px">sensitive</span>' : '' ?>
            <?php if ($d['file_name']): ?><br>
              <?php if (doc_can_download($d) && $d['file_data']): ?><a href="<?= $e($d['file_data']) ?>" download="<?= $e($d['file_name']) ?>" style="font-size:12px"><?= $e($d['file_name']) ?></a>
              <?php else: ?><span class="muted" style="font-size:12px"><?= $e($d['file_name']) ?> <em>(restricted)</em></span><?php endif; ?>
            <?php endif; ?>
            <?php if ($d['rejection_reason']): ?><br><span class="p-bad" style="font-size:11.5px">✕ <?= $e($d['rejection_reason']) ?></span><?php endif; ?>
          </td>
          <td style="padding:8px"><span class="pill <?= $pill[$st] ?? 'p-mut' ?>"><?= $e(DOC_STATUSES[$st] ?? $st) ?></span>
            <?php if ($d['verifier'] && $st==='VERIFIED'): ?><div class="muted" style="font-size:11px"><?= $e($d['verifier']) ?></div><?php endif; ?></td>
          <td style="padding:8px;font-size:12px;color:var(--muted,#656e7a)"><?= $d['issue_date'] ? 'Issued ' . $e($d['issue_date']) : '' ?><?= $d['expiry_date'] ? '<br>Expires ' . $e($d['expiry_date']) : '' ?></td>
          <td style="padding:8px;white-space:nowrap">
            <?php if ($can): ?>
              <?php if (in_array($st, ['UPLOADED','UNDER_REVIEW','RESUBMIT'], true)): ?>
                <form method="post" action="/candidate-doc?id=<?= (int)$cand['id'] ?>" style="display:inline"><input type="hidden" name="do" value="verify"><input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>"><button class="btn secondary" style="padding:4px 9px;font-size:12px">Verify</button></form>
                <form method="post" action="/candidate-doc?id=<?= (int)$cand['id'] ?>" style="display:inline" onsubmit="this.reason.value=prompt('Reason for rejection / resubmission?')||'';return this.reason.value!==''"><input type="hidden" name="do" value="reject"><input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="resubmit" value="1"><input type="hidden" name="reason"><button class="btn secondary" style="padding:4px 9px;font-size:12px">Return</button></form>
              <?php endif; ?>
              <form method="post" action="/candidate-doc?id=<?= (int)$cand['id'] ?>" style="display:inline" onsubmit="return confirm('Remove this document?')"><input type="hidden" name="do" value="delete"><input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>"><button class="btn secondary" style="padding:4px 9px;font-size:12px">✕</button></form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </div>
    <?php
    // The auto-generated letters (offer / appointment / one-pager / …) belong in
    // the Documents tab too — this is where anyone looks for a candidate's papers,
    // not only at the foot of the Offer tab. Merge tokens are filled from the
    // candidate's own data; missing fields are highlighted in the letter.
    if (function_exists('recruit_letters_block')) recruit_letters_block($cand);
}

// ============================================================================
//  MY INTERVIEWS — what an interviewer must attend.            (UAT A9)
//
//  The owner's finding was that a panel member had nowhere to see the
//  interviews they had been put on. My Work now counts them; this is the screen
//  behind that count, because a count that links nowhere is worse than no count.
//
//  Deliberately NOT gated on recruitment access. The people who sit on panels
//  are usually the senior person in the discipline, with no recruitment module
//  at all — gating this would hide an interviewer's own diary from them. It
//  shows one thing: interviews where THIS person is named on the panel. It
//  carries no candidate assessment, no salary, no other panel's work.
// ============================================================================
function ops_my_interviews($method) {
    recruit_iv_migrate();
    $u  = function_exists('current_user') ? current_user() : null;
    if (!$u) { redirect('/login'); return true; }
    $rows = iv_my_upcoming((int) ($u['id'] ?? 0), 30);
    view('ops/my_interviews', ['rows' => $rows]);
    return true;
}
