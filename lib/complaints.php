<?php
// ============================================================================
//  Complaints and appeals — ISO/IEC 17020 §7.5 and §7.6
//
//  Every inspection body says it takes complaints seriously. What an assessor
//  actually asks for is a list: how many, when each arrived, when each was
//  acknowledged, who decided it, whether that person had anything to do with
//  the inspection being complained about, and when the complainant was told the
//  outcome. A body that cannot produce that list has a nonconformity no matter
//  how well it handled the complaint in the room.
//
//  So this file is a register with dates and names on it, and three rules that
//  the software will not let you break:
//
//   1. THE DECIDER MUST NOT HAVE BEEN INVOLVED. §7.5.4 — the decision must be
//      made by, or reviewed and approved by, somebody not involved in the
//      original inspection. The engineer who did the work, whoever prepared the
//      report and whoever approved it are all refused. Not warned: refused.
//
//   2. AN APPEAL IS DECIDED BY SOMEBODY ELSE AGAIN. §7.6 — an appeal against a
//      decision cannot be decided by whoever made that decision. Otherwise it is
//      not an appeal, it is the same person being asked twice.
//
//   3. IT IS NOT CLOSED UNTIL THE COMPLAINANT HAS BEEN TOLD. The single most
//      common finding in this clause is a register full of resolved complaints
//      where nobody wrote to the person who complained. Closing refuses.
//
//  What is deliberately NOT here:
//
//   · No public complaint FORM. §7.5.1 wants the process publicly available, and
//     that is built — /complaints-policy is readable without signing in. An open
//     submission form on the internet needs rate limiting, spam handling and
//     abuse review to be worth having, and a half-built one is worse than a
//     published e-mail address. Said plainly rather than shipped badly.
//   · No automatic judgement of who is right. The outcome is a person's
//     decision, recorded with their name against it.
// ============================================================================

const CMP_KINDS = [
    'COMPLAINT' => 'Complaint — dissatisfaction with our work or conduct',
    'APPEAL'    => 'Appeal — a request to reconsider a decision we made',
];

// Where it came from. Kept broad on purpose: §7.5 covers complaints from any
// interested party, not only the paying client.
const CMP_SOURCES = [
    'CLIENT'     => 'Client',
    'VENDOR'     => 'Vendor / manufacturer inspected',
    'SUBCON'     => 'Subcontractor',
    'PUBLIC'     => 'A member of the public or other interested party',
    'STAFF'      => 'Our own staff',
    'REGULATOR'  => 'A regulator or accreditation body',
    'ANONYMOUS'  => 'Anonymous',
];

const CMP_CHANNELS = [
    'EMAIL' => 'E-mail', 'PHONE' => 'Telephone', 'LETTER' => 'Letter',
    'MEETING' => 'In person / meeting', 'PORTAL' => 'Website or portal', 'OTHER' => 'Other',
];

// Is it ours to answer at all? §7.5.3 — the body first confirms the complaint
// relates to inspection activities it is responsible for.
const CMP_VALIDITY = [
    'PENDING'      => 'Not yet decided',
    'VALID'        => 'Ours to answer — it concerns our inspection activities',
    'NOT_OURS'     => 'Not ours — it concerns somebody else’s work',
    'OUT_OF_SCOPE' => 'Ours, but outside inspection activities (e.g. commercial)',
];

const CMP_OUTCOMES = [
    'PENDING'    => 'Not yet decided',
    'UPHELD'     => 'Upheld — the complaint was justified',
    'PARTLY'     => 'Partly upheld',
    'NOT_UPHELD' => 'Not upheld — we found no fault',
    'WITHDRAWN'  => 'Withdrawn by the complainant',
];

// A decision that says we got something wrong should produce a corrective
// action. Recorded so 3.5 (CAPA) can pick it up rather than guess.
const CMP_NEEDS_CAPA = ['UPHELD', 'PARTLY'];

const CMP_ACK_DAYS_DEFAULT    = 3;
const CMP_DECIDE_DAYS_DEFAULT = 30;

// The description that has to be publicly available (§7.5.1). Shipped filled in
// rather than blank, because a blank one is the finding — a body can reword it,
// but it cannot end up with nothing.
const CMP_POLICY_DEFAULT = <<<'TXT'
How to complain about our work, and how we handle it

Anyone may complain about our inspection work or the conduct of our people —
you do not have to be the client who paid for it.

How to raise one
  Write to us with what happened, when, and which inspection or report it
  concerns. Include a way to reach you. If you would rather not give your name,
  say so; we will still investigate, but we will not be able to tell you the
  outcome.

What we do
  1. We acknowledge every complaint in writing.
  2. We confirm whether it concerns inspection work we are responsible for. If
     it does not, we say so and tell you who does, where we can.
  3. We investigate. The person who decides the outcome is never somebody who
     took part in the inspection being complained about.
  4. We write to you with the decision and the reasons for it.
  5. Where we find we were at fault, we record a corrective action and check
     later that it worked.

If you disagree with our decision
  You may appeal. An appeal is decided by somebody who did not make the original
  decision. Write to us saying what you are appealing against and why.

We do not treat anyone unfavourably because they complained.
TXT;

function complaints_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = pk_clause();
    $pdo->exec("CREATE TABLE IF NOT EXISTS complaints (
        id $pk, ref VARCHAR(40) DEFAULT '', kind VARCHAR(20) DEFAULT 'COMPLAINT',
        appeal_of_id INT NULL,
        source VARCHAR(20) DEFAULT 'CLIENT', channel VARCHAR(20) DEFAULT 'EMAIL',
        partner_id INT NULL, anonymous INT DEFAULT 0,
        complainant_name VARCHAR(150) DEFAULT '', complainant_email VARCHAR(200) DEFAULT '',
        complainant_phone VARCHAR(60) DEFAULT '',
        subject VARCHAR(255) DEFAULT '', description TEXT,
        received_on VARCHAR(20) DEFAULT '', received_by VARCHAR(150) DEFAULT '',
        job_id INT NULL, report_irn VARCHAR(120) DEFAULT '', inspector_id INT NULL, office_id INT NULL,
        validity VARCHAR(20) DEFAULT 'PENDING', validity_note VARCHAR(1000) DEFAULT '',
        validity_by VARCHAR(150) DEFAULT '', validity_on VARCHAR(20) DEFAULT '',
        acknowledged_on VARCHAR(20) DEFAULT '', acknowledged_by VARCHAR(150) DEFAULT '',
        assigned_to VARCHAR(150) DEFAULT '', investigation TEXT, root_cause VARCHAR(1000) DEFAULT '',
        outcome VARCHAR(20) DEFAULT 'PENDING', decision_note TEXT,
        decided_by VARCHAR(150) DEFAULT '', decided_on VARCHAR(20) DEFAULT '',
        notified_on VARCHAR(20) DEFAULT '', notified_by VARCHAR(150) DEFAULT '',
        notify_note VARCHAR(1000) DEFAULT '',
        capa_ref VARCHAR(80) DEFAULT '',
        status VARCHAR(20) DEFAULT 'OPEN', closed_on VARCHAR(20) DEFAULT '', closed_by VARCHAR(150) DEFAULT '',
        created_at VARCHAR(30) DEFAULT '')");
    // The trail. Separate from idems_audit because this one is meant to be read
    // by a person on the complaint's own screen, in order, as a story.
    $pdo->exec("CREATE TABLE IF NOT EXISTS complaint_events (
        id $pk, complaint_id INT, at VARCHAR(30) DEFAULT '',
        action VARCHAR(40) DEFAULT '', actor VARCHAR(150) DEFAULT '', note VARCHAR(1000) DEFAULT '')");
}

function cmp_missing_table(Throwable $e) {
    $m = $e->getMessage();
    return stripos($m, 'no such table') !== false || stripos($m, "doesn't exist") !== false;
}

// ---- Settings ---------------------------------------------------------------
function cmp_ack_days() {
    $d = (int)setting_get('complaint_ack_days', (string)CMP_ACK_DAYS_DEFAULT);
    return $d > 0 ? $d : CMP_ACK_DAYS_DEFAULT;
}
function cmp_decide_days() {
    $d = (int)setting_get('complaint_decide_days', (string)CMP_DECIDE_DAYS_DEFAULT);
    return $d > 0 ? $d : CMP_DECIDE_DAYS_DEFAULT;
}
function cmp_policy_text() {
    $p = trim((string)setting_get('complaints_policy', ''));
    return $p !== '' ? $p : CMP_POLICY_DEFAULT;
}
function cmp_policy_is_default() { return trim((string)setting_get('complaints_policy', '')) === ''; }

// ---- Reading ----------------------------------------------------------------
function cmp_all($filter = []) {
    // office_id has been on this table since it was written and was never read,
    // so every branch manager saw every complaint in the company — the opposite
    // of what a multi-branch body needs. A complaint with no branch stays
    // visible to everyone, because an unassigned complaint must not vanish.
    [$sw, $sa] = scope_office_clause('office_id');
    $w = [$sw]; $a = $sa;
    if (!empty($filter['status']))  { $w[] = 'status = ?';  $a[] = $filter['status']; }
    if (!empty($filter['kind']))    { $w[] = 'kind = ?';    $a[] = $filter['kind']; }
    if (!empty($filter['outcome'])) { $w[] = 'outcome = ?'; $a[] = $filter['outcome']; }
    try {
        return ops_all("SELECT * FROM complaints WHERE " . implode(' AND ', $w)
                     . " ORDER BY (status='OPEN') DESC, received_on DESC, id DESC", $a);
    } catch (Throwable $e) { if (cmp_missing_table($e)) return []; throw $e; }
}

function cmp_row($id) {
    try { return ops_one("SELECT * FROM complaints WHERE id=?", [(int)$id]); }
    catch (Throwable $e) { if (cmp_missing_table($e)) return null; throw $e; }
}

function cmp_events($id) {
    try { return ops_all("SELECT * FROM complaint_events WHERE complaint_id=? ORDER BY id", [(int)$id]); }
    catch (Throwable $e) { if (cmp_missing_table($e)) return []; throw $e; }
}

function cmp_log($id, $action, $note = '') {
    try {
        db()->prepare("INSERT INTO complaint_events (complaint_id,at,action,actor,note) VALUES (?,?,?,?,?)")
            ->execute([(int)$id, date('c'), $action, user_name(current_user()), substr((string)$note, 0, 1000)]);
    } catch (Throwable $e) { /* the trail must not be what breaks the screen */ }
}

function cmp_ref_next($kind) {
    $prefix = $kind === 'APPEAL' ? 'APL' : 'CMP';
    $n = 0;
    try { $n = (int)ops_val("SELECT COUNT(*) FROM complaints WHERE kind=?", [$kind]); } catch (Throwable $e) {}
    return $prefix . '-' . date('Y') . '-' . str_pad((string)($n + 1), 3, '0', STR_PAD_LEFT);
}

// ---- The clocks -------------------------------------------------------------
// Days overdue, or 0 when it is not. Deliberately counted from the day it was
// received, not the day somebody got round to entering it.
function cmp_ack_overdue($c, $today = null) {
    if (($c['acknowledged_on'] ?? '') !== '') return 0;
    if (($c['received_on'] ?? '') === '') return 0;
    $due = date('Y-m-d', strtotime($c['received_on'] . ' +' . cmp_ack_days() . ' days'));
    $today = $today ?: date('Y-m-d');
    return $today > $due ? (int)floor((strtotime($today) - strtotime($due)) / 86400) : 0;
}
function cmp_decide_overdue($c, $today = null) {
    if (($c['status'] ?? '') === 'CLOSED') return 0;
    if (($c['outcome'] ?? 'PENDING') !== 'PENDING') return 0;
    if (($c['received_on'] ?? '') === '') return 0;
    $due = date('Y-m-d', strtotime($c['received_on'] . ' +' . cmp_decide_days() . ' days'));
    $today = $today ?: date('Y-m-d');
    return $today > $due ? (int)floor((strtotime($today) - strtotime($due)) / 86400) : 0;
}

// ---- §7.5.4 — who must not decide this -------------------------------------
// "Involved in the original inspection activities" is read as: the engineer who
// did the work, whoever prepared the report, and whoever approved or issued it.
// The coordinator who allocated the deputation is NOT counted — allocating work
// is not carrying out the inspection, and treating it as such would leave a
// small branch with nobody left who is allowed to decide anything.
function cmp_involved($c) {
    $names = [];
    $add = function ($n) use (&$names) {
        $n = trim((string)$n);
        if ($n !== '' && !in_array($n, $names, true)) $names[] = $n;
    };
    $insId = (int)($c['inspector_id'] ?? 0);
    if (!$insId && !empty($c['job_id']))
        $insId = (int)ops_val("SELECT inspector_id FROM jobs WHERE id=?", [(int)$c['job_id']]);
    if ($insId) {
        $add(ops_val("SELECT name FROM inspectors WHERE id=?", [$insId]));
        // The same person's sign-in name, which is what a decision is stamped with.
        try {
            foreach (ops_all("SELECT id, username, first_name, last_name FROM users WHERE inspector_id=?", [$insId]) as $u)
                $add(user_name($u));
        } catch (Throwable $e) { /* users may not carry inspector_id on an old install */ }
    }
    if (($c['report_irn'] ?? '') !== '') {
        try {
            $d = ops_one("SELECT created_by, finalized_by, approved_by, approver_user_id
                          FROM report_docs WHERE irn=?", [$c['report_irn']]);
            if ($d) {
                $add($d['created_by']); $add($d['finalized_by']); $add($d['approved_by']);
                if (!empty($d['approver_user_id'])) {
                    $u = ops_one("SELECT id, username, first_name, last_name FROM users WHERE id=?", [(int)$d['approver_user_id']]);
                    if ($u) $add(user_name($u));
                }
            }
        } catch (Throwable $e) { /* reporting module may be switched off */ }
    }
    return $names;
}

// The sentence shown when this person must not decide it. '' when they may.
function cmp_decide_block($c, $who = null) {
    $who = $who !== null ? trim((string)$who) : user_name(current_user());
    if ($who === '') return 'Sign in before deciding a complaint — a decision has to have a name on it.';

    // §7.6 — an appeal is not an appeal if the same person decides it again.
    if (($c['kind'] ?? '') === 'APPEAL' && !empty($c['appeal_of_id'])) {
        $parent = cmp_row((int)$c['appeal_of_id']);
        if ($parent && trim((string)$parent['decided_by']) !== ''
            && strcasecmp(trim((string)$parent['decided_by']), $who) === 0)
            return 'You decided ' . $parent['ref'] . '. ISO/IEC 17020 §7.6 — an appeal has to be decided by '
                 . 'somebody who did not make the decision being appealed against, or it is not an appeal.';
    }

    foreach (cmp_involved($c) as $n)
        if (strcasecmp($n, $who) === 0)
            return 'You took part in the inspection this concerns. ISO/IEC 17020 §7.5.4 — the decision must be '
                 . 'made by, or reviewed and approved by, somebody who was not involved. Ask a colleague who '
                 . 'was not on this work to decide it.';
    return '';
}

// ---- Closing ----------------------------------------------------------------
// The list of what is still missing, in the order somebody would do it.
function cmp_close_missing($c) {
    $miss = [];
    if (($c['acknowledged_on'] ?? '') === '') $miss[] = 'acknowledge it to the complainant';
    if (($c['validity'] ?? 'PENDING') === 'PENDING') $miss[] = 'decide whether it is ours to answer';
    $ours = in_array($c['validity'] ?? '', ['VALID', 'OUT_OF_SCOPE'], true);
    if ($ours && ($c['outcome'] ?? 'PENDING') === 'PENDING') $miss[] = 'record the outcome';
    if ($ours && ($c['outcome'] ?? '') !== 'PENDING' && trim((string)($c['decided_by'] ?? '')) === '')
        $miss[] = 'record who decided it';
    // Telling the complainant. Anonymous with no way to reach them is the one
    // honest exception, and it is stated rather than silently skipped.
    $reachable = !empty($c['complainant_email']) || !empty($c['complainant_phone']) || empty($c['anonymous']);
    if ($reachable && ($c['notified_on'] ?? '') === '') $miss[] = 'write to the complainant with the outcome';
    if (in_array($c['outcome'] ?? '', CMP_NEEDS_CAPA, true) && trim((string)($c['capa_ref'] ?? '')) === '')
        $miss[] = 'record the corrective action raised';
    return $miss;
}

function cmp_close_block($c) {
    $miss = cmp_close_missing($c);
    if (!$miss) return '';
    $word = ($c['kind'] ?? '') === 'APPEAL' ? 'appeal' : 'complaint';
    return 'This ' . $word . ' cannot be closed yet. Still to do: ' . implode('; ', $miss)
         . '. A register of resolved complaints where nobody wrote back to the complainant is the '
         . 'finding §7.5 exists to prevent.';
}

// ---- How this body is doing on §7.5 / §7.6 ---------------------------------
function cmp_readiness() {
    $all = cmp_all();
    $open = 0; $ackLate = 0; $decLate = 0; $unnotified = 0; $appeals = 0; $upheld = 0;
    foreach ($all as $c) {
        if ($c['status'] === 'OPEN') $open++;
        if (cmp_ack_overdue($c)) $ackLate++;
        if (cmp_decide_overdue($c)) $decLate++;
        if ($c['status'] === 'OPEN' && $c['outcome'] !== 'PENDING' && $c['notified_on'] === '') $unnotified++;
        if ($c['kind'] === 'APPEAL') $appeals++;
        if (in_array($c['outcome'], CMP_NEEDS_CAPA, true)) $upheld++;
    }
    return [
        'total' => count($all), 'open' => $open, 'appeals' => $appeals, 'upheld' => $upheld,
        'ack_late' => $ackLate, 'decide_late' => $decLate, 'unnotified' => $unnotified,
        'ack_days' => cmp_ack_days(), 'decide_days' => cmp_decide_days(),
        'policy_default' => cmp_policy_is_default(),
    ];
}

// ---- Nightly ----------------------------------------------------------------
// Nobody chases a complaint they have forgotten about, which is precisely how
// the acknowledgement deadline gets missed. One mail a day, to the person it
// is assigned to and the managers.
function cmp_run_reminders($today = null) {
    $today = $today ?: date('Y-m-d');
    $late = [];
    foreach (cmp_all(['status' => 'OPEN']) as $c) {
        $a = cmp_ack_overdue($c, $today); $d = cmp_decide_overdue($c, $today);
        if ($a || $d) $late[] = $c['ref'] . ' — ' . ($a ? "acknowledgement {$a} day(s) late" : '')
                              . ($a && $d ? '; ' : '') . ($d ? "decision {$d} day(s) late" : '');
    }
    if (!$late) return 0;
    $body = "Complaints and appeals past their own deadline:\n\n  " . implode("\n  ", $late)
          . "\n\nISO/IEC 17020 §7.5 — an assessor reads these dates.\n\n" . app_name();
    ops_mail('', 'Complaints overdue (' . count($late) . ')', $body, manager_emails(), 'complaint');
    return count($late);
}

// ---- Who may do what --------------------------------------------------------
function cmp_can_view()   { return can('mod.complaints.view'); }
function cmp_can_record() { return can('mod.complaints.edit'); }
function cmp_can_decide() { return can('complaints.decide'); }

// ---- Screens ----------------------------------------------------------------
function ops_complaints($route, $method) {
    // Note: /complaints-policy is NOT handled here. §7.5.1 wants the description
    // available to any interested party, so it is served from index.php before
    // the sign-in gate and never reaches this function.
    ops_require(cmp_can_view(), 'You don’t have access to the complaints register. Ask your administrator.');

    if ($route === 'complaints') {
        view('ops/complaints', [
            'ready' => cmp_readiness(), 'rows' => cmp_all(),
            'canRecord' => cmp_can_record(), 'canDecide' => cmp_can_decide(),
        ]);
        return true;
    }

    if ($route === 'complaint') {
        $c = cmp_row((int)($_GET['id'] ?? 0));
        if (!$c) { http_response_code(404); view('notfound'); return true; }
        view('ops/complaint_detail', [
            'c' => $c, 'events' => cmp_events($c['id']),
            'involved' => cmp_involved($c),
            'block' => cmp_decide_block($c),
            'missing' => cmp_close_missing($c),
            'parent' => !empty($c['appeal_of_id']) ? cmp_row((int)$c['appeal_of_id']) : null,
            'appeals' => cmp_all_appeals_of((int)$c['id']),
            'canRecord' => cmp_can_record(), 'canDecide' => cmp_can_decide(),
        ]);
        return true;
    }

    ops_require(cmp_can_record(), 'Only somebody with the complaints permission can record or update one.');

    if ($route === 'complaint-new') {
        if ($method === 'POST') {
            $id = cmp_create($_POST);
            if ($id === 0) { flash('A complaint needs a subject and a description — an entry that says nothing proves nothing.', 'error'); redirect('/complaint-new'); }
            redirect('/complaint?id=' . $id);
        }
        view('ops/complaint_form', [
            'c' => null,
            'clients' => clients_list(),
            'inspectors' => ops_all("SELECT id, name FROM inspectors WHERE status='ACTIVE' ORDER BY name"),
            'appealOf' => (int)($_GET['appeal_of'] ?? 0) ? cmp_row((int)$_GET['appeal_of']) : null,
        ]);
        return true;
    }

    $c = cmp_row((int)($_POST['id'] ?? $_GET['id'] ?? 0));
    if (!$c) redirect('/complaints');

    if ($route === 'complaint-ack' && $method === 'POST') {
        if ($c['acknowledged_on'] === '') {
            db()->prepare("UPDATE complaints SET acknowledged_on=?, acknowledged_by=? WHERE id=?")
                ->execute([(string)($_POST['on'] ?? date('Y-m-d')), user_name(current_user()), $c['id']]);
            cmp_log($c['id'], 'ACKNOWLEDGED', (string)($_POST['note'] ?? ''));
            flash('Acknowledged. That is the first date an assessor looks at.');
        }
        redirect('/complaint?id=' . $c['id']);
    }

    if ($route === 'complaint-validity' && $method === 'POST') {
        $v = (string)($_POST['validity'] ?? '');
        if (!isset(CMP_VALIDITY[$v]) || $v === 'PENDING') redirect('/complaint?id=' . $c['id']);
        $note = trim((string)($_POST['validity_note'] ?? ''));
        // Turning somebody away needs a reason on the record, and somewhere to
        // send them. Saying "not ours" with nothing written down is how a body
        // ends up unable to explain why it did nothing.
        if ($v !== 'VALID' && $note === '') {
            flash('Say why it is not ours to answer, and who should handle it if you know. '
                . 'A complaint turned away with nothing written down cannot be defended later.', 'error');
            redirect('/complaint?id=' . $c['id']);
        }
        db()->prepare("UPDATE complaints SET validity=?, validity_note=?, validity_by=?, validity_on=? WHERE id=?")
            ->execute([$v, substr($note, 0, 1000), user_name(current_user()), date('Y-m-d'), $c['id']]);
        cmp_log($c['id'], 'VALIDITY', CMP_VALIDITY[$v] . ($note !== '' ? ' — ' . $note : ''));
        flash('Recorded: ' . CMP_VALIDITY[$v] . '.');
        redirect('/complaint?id=' . $c['id']);
    }

    if ($route === 'complaint-investigate' && $method === 'POST') {
        db()->prepare("UPDATE complaints SET assigned_to=?, investigation=?, root_cause=? WHERE id=?")
            ->execute([substr(trim((string)($_POST['assigned_to'] ?? '')), 0, 150),
                       (string)($_POST['investigation'] ?? ''),
                       substr(trim((string)($_POST['root_cause'] ?? '')), 0, 1000), $c['id']]);
        cmp_log($c['id'], 'INVESTIGATION', 'Findings updated.');
        flash('Saved.');
        redirect('/complaint?id=' . $c['id']);
    }

    // The gate. Everything above can be done by whoever is handling it; this
    // one asks who you are.
    if ($route === 'complaint-decide' && $method === 'POST') {
        ops_require(cmp_can_decide(), 'Deciding a complaint needs the “Decide complaints and appeals” permission.');
        $why = cmp_decide_block($c);
        if ($why !== '') { flash($why, 'error'); redirect('/complaint?id=' . $c['id']); }
        $o = (string)($_POST['outcome'] ?? '');
        if (!isset(CMP_OUTCOMES[$o]) || $o === 'PENDING') redirect('/complaint?id=' . $c['id']);
        $note = trim((string)($_POST['decision_note'] ?? ''));
        if ($note === '') {
            flash('Write the decision and the reasons for it. That paragraph is what goes to the complainant '
                . 'and what an assessor reads.', 'error');
            redirect('/complaint?id=' . $c['id']);
        }
        db()->prepare("UPDATE complaints SET outcome=?, decision_note=?, decided_by=?, decided_on=? WHERE id=?")
            ->execute([$o, $note, user_name(current_user()), date('Y-m-d'), $c['id']]);
        cmp_log($c['id'], 'DECIDED', CMP_OUTCOMES[$o]);
        flash('Decision recorded' . (in_array($o, CMP_NEEDS_CAPA, true)
            ? '. We found fault, so a corrective action is now needed before this can be closed.' : '.'));
        redirect('/complaint?id=' . $c['id']);
    }

    if ($route === 'complaint-notify' && $method === 'POST') {
        if ($c['outcome'] === 'PENDING') {
            flash('Decide it before writing to the complainant.', 'error');
            redirect('/complaint?id=' . $c['id']);
        }
        db()->prepare("UPDATE complaints SET notified_on=?, notified_by=?, notify_note=? WHERE id=?")
            ->execute([(string)($_POST['on'] ?? date('Y-m-d')), user_name(current_user()),
                       substr(trim((string)($_POST['notify_note'] ?? '')), 0, 1000), $c['id']]);
        cmp_log($c['id'], 'NOTIFIED', (string)($_POST['notify_note'] ?? ''));
        flash('Recorded that the complainant was told, and when.');
        redirect('/complaint?id=' . $c['id']);
    }

    if ($route === 'complaint-capa' && $method === 'POST') {
        db()->prepare("UPDATE complaints SET capa_ref=? WHERE id=?")
            ->execute([substr(trim((string)($_POST['capa_ref'] ?? '')), 0, 80), $c['id']]);
        cmp_log($c['id'], 'CAPA', (string)($_POST['capa_ref'] ?? ''));
        flash('Corrective action reference saved.');
        redirect('/complaint?id=' . $c['id']);
    }

    if ($route === 'complaint-close' && $method === 'POST') {
        $why = cmp_close_block($c);
        if ($why !== '') { flash($why, 'error'); redirect('/complaint?id=' . $c['id']); }
        db()->prepare("UPDATE complaints SET status='CLOSED', closed_on=?, closed_by=? WHERE id=?")
            ->execute([date('Y-m-d'), user_name(current_user()), $c['id']]);
        cmp_log($c['id'], 'CLOSED', '');
        flash('Closed.');
        redirect('/complaint?id=' . $c['id']);
    }

    if ($route === 'complaint-reopen' && $method === 'POST') {
        db()->prepare("UPDATE complaints SET status='OPEN', closed_on='', closed_by='' WHERE id=?")->execute([$c['id']]);
        cmp_log($c['id'], 'REOPENED', trim((string)($_POST['reason'] ?? '')));
        flash('Reopened.');
        redirect('/complaint?id=' . $c['id']);
    }

    if ($route === 'complaint-settings' && $method === 'POST') {
        ops_require(is_admin_level() || is_master(), 'Only an administrator can change these.');
        $a = (int)($_POST['complaint_ack_days'] ?? 0);
        $d = (int)($_POST['complaint_decide_days'] ?? 0);
        if ($a > 0) setting_set('complaint_ack_days', (string)$a);
        if ($d > 0) setting_set('complaint_decide_days', (string)$d);
        setting_set('complaints_policy', (string)($_POST['complaints_policy'] ?? ''));
        flash('Saved. The published description is at /complaints-policy — it opens without signing in.');
        redirect('/complaints');
    }

    redirect('/complaints');
    return true;
}

function cmp_all_appeals_of($id) {
    try { return ops_all("SELECT * FROM complaints WHERE appeal_of_id=? ORDER BY id", [(int)$id]); }
    catch (Throwable $e) { if (cmp_missing_table($e)) return []; throw $e; }
}

// Returns the new id, or 0 when the form said nothing worth keeping.
function cmp_create($b) {
    $subject = trim((string)($b['subject'] ?? ''));
    $desc    = trim((string)($b['description'] ?? ''));
    if ($subject === '' || $desc === '') return 0;
    $kind = isset(CMP_KINDS[$b['kind'] ?? '']) ? $b['kind'] : 'COMPLAINT';
    $appealOf = (int)($b['appeal_of_id'] ?? 0);
    if ($appealOf && !cmp_row($appealOf)) $appealOf = 0;
    if ($appealOf) $kind = 'APPEAL';                    // an appeal against something is an appeal
    $anon = !empty($b['anonymous']) ? 1 : 0;
    $ref = cmp_ref_next($kind);
    // An appeal inherits what the original was about, so the "who must not
    // decide this" test still has something to work from.
    $parent = $appealOf ? cmp_row($appealOf) : null;
    db()->prepare("INSERT INTO complaints
        (ref,kind,appeal_of_id,source,channel,partner_id,anonymous,complainant_name,complainant_email,
         complainant_phone,subject,description,received_on,received_by,job_id,report_irn,inspector_id,office_id,
         validity,outcome,status,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'PENDING','PENDING','OPEN',?)")
        ->execute([$ref, $kind, $appealOf ?: null,
                   isset(CMP_SOURCES[$b['source'] ?? '']) ? $b['source'] : 'CLIENT',
                   isset(CMP_CHANNELS[$b['channel'] ?? '']) ? $b['channel'] : 'EMAIL',
                   ($b['partner_id'] ?? '') !== '' ? (int)$b['partner_id'] : ($parent['partner_id'] ?? null),
                   $anon,
                   $anon ? '' : substr(trim((string)($b['complainant_name'] ?? '')), 0, 150),
                   substr(trim((string)($b['complainant_email'] ?? '')), 0, 200),
                   substr(trim((string)($b['complainant_phone'] ?? '')), 0, 60),
                   substr($subject, 0, 255), $desc,
                   (string)($b['received_on'] ?? date('Y-m-d')),
                   // Who took it. A complaint raised in the client portal has no
                   // staff user behind it, so the caller says who — otherwise the
                   // register records the person who happened to load a page.
                   substr(trim((string)($b['received_by'] ?? user_name(current_user()))), 0, 150),
                   ($b['job_id'] ?? '') !== '' ? (int)$b['job_id'] : ($parent['job_id'] ?? null),
                   substr(trim((string)($b['report_irn'] ?? ($parent['report_irn'] ?? ''))), 0, 120),
                   ($b['inspector_id'] ?? '') !== '' ? (int)$b['inspector_id'] : ($parent['inspector_id'] ?? null),
                   ($b['office_id'] ?? '') !== '' ? (int)$b['office_id'] : ($parent['office_id'] ?? null),
                   date('c')]);
    $id = (int)db()->lastInsertId();
    cmp_log($id, 'RECEIVED', $ref . ' — ' . $subject);
    return $id;
}
