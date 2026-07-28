<?php
// ============================================================================
//  The client accepts or rejects the report
//
//  Until now a report was issued and that was the end of it, as far as the
//  software was concerned. What the client thought of it happened entirely in
//  e-mail: "please revise section 4", "we cannot accept this", "approved,
//  release the material". None of it reached the system, so:
//
//   - nobody could answer "which reports are sitting unanswered at a client?"
//   - a rejection had nowhere to land, and so became somebody's inbox
//   - re-issues happened with no record of what prompted them
//   - the turnaround measured internally stopped at issue, which is not when
//     the job is actually finished from the client's side
//
//  So the client now decides, in the portal, against a specific revision.
//
//  Five things worth knowing about how this is built:
//
//   1. **The decision is against a REVISION, not the report.** A rejected
//      report gets corrected and re-issued as Rev 1, and Rev 1 needs its own
//      answer. Holding the decision on `report_docs` alone would mean the
//      correction silently inherits an acceptance, or a rejection follows a
//      report that has already been fixed. So the decisions are a table, and
//      the current state is derived from the newest row for the current rev.
//   2. **A rejection raises a nonconformity, automatically.** That is the whole
//      point of the previous commit. A client telling us the work is wrong is
//      exactly the population that used to be invisible.
//   3. **Nothing is auto-accepted by time.** A silent client is not an
//      approving client, and a rule that says otherwise is the kind of thing
//      that gets discovered during a dispute. Silence is reported as silence,
//      with its age.
//   4. **The client cannot change their mind by clicking twice.** A decision is
//      recorded once per revision; changing it needs somebody at our end to
//      re-issue, which creates a new revision to decide on. Otherwise the
//      audit trail says whatever the last click said.
//   5. **Acceptance is not release.** A client accepting the report does not
//      alter the release status, the payment hold or anything else. It records
//      their opinion, which is a different fact.
// ============================================================================

const RCR_DECISIONS = ['ACCEPTED' => 'Accepted', 'REJECTED' => 'Rejected'];

// Researched against what clients actually send back on inspection reports.
// A fixed list makes the rejections countable; "Other" keeps it honest.
const RCR_REASONS = [
    'FACTUAL'     => 'Something in it is factually wrong',
    'INCOMPLETE'  => 'Scope not fully covered — something we asked for is missing',
    'EVIDENCE'    => 'Photographs or evidence missing or unclear',
    'FORMAT'      => 'Wrong format, template or missing signature/stamp',
    'DETAILS'     => 'Wrong reference details (PO, drawing, heat/serial numbers)',
    'CONCLUSION'  => 'We disagree with the conclusion',
    'LATE'        => 'Too late to be of use',
    'OTHER'       => 'Other (please say)',
];

function rcr_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = pk_clause();
    // One row per decision per revision. Never overwritten — a re-issue that
    // followed a rejection has to be able to show what the rejection said.
    $pdo->exec("CREATE TABLE IF NOT EXISTS report_client_reviews (
        id $pk, report_doc_id INT, rev INT DEFAULT 0, partner_id INT,
        client_user_id INT NULL, decided_by_name VARCHAR(150) DEFAULT '',
        decided_by_email VARCHAR(200) DEFAULT '',
        decision VARCHAR(20) DEFAULT '', reason VARCHAR(30) DEFAULT '', note TEXT,
        ncr_id INT NULL, ncr_ref VARCHAR(40) DEFAULT '',
        ack_by VARCHAR(150) DEFAULT '', ack_at VARCHAR(30) DEFAULT '', ack_note VARCHAR(1000) DEFAULT '',
        ip VARCHAR(60) DEFAULT '', decided_at VARCHAR(30) DEFAULT '')");
    // Denormalised so a register can be listed and sorted without a subquery
    // per row. The table above stays the record; these two are a cache.
    ensure_column('report_docs', 'client_decision', "VARCHAR(20) DEFAULT ''");
    ensure_column('report_docs', 'client_decision_at', "VARCHAR(30) DEFAULT ''");
}

function rcr_missing_table(Throwable $e) {
    $m = $e->getMessage();
    return stripos($m, 'no such table') !== false || stripos($m, "doesn't exist") !== false;
}
function rcr_try($fn, $fallback = []) {
    try { return $fn(); } catch (Throwable $e) { if (!rcr_missing_table($e)) throw $e; return $fallback; }
}

// ---- Reading ---------------------------------------------------------------
// Every decision ever taken on this report, newest first.
function rcr_history($docId) {
    rcr_migrate();
    return rcr_try(fn() => ops_all(
        "SELECT * FROM report_client_reviews WHERE report_doc_id=? ORDER BY id DESC", [(int)$docId]));
}

// The decision that applies to the revision currently issued — which is the
// only one that means anything. An older rev's acceptance is history.
function rcr_current($doc) {
    rcr_migrate();
    $rev = (int)($doc['rev'] ?? 0);
    return rcr_try(fn() => ops_one(
        "SELECT * FROM report_client_reviews WHERE report_doc_id=? AND rev=? ORDER BY id DESC LIMIT 1",
        [(int)$doc['id'], $rev]), null) ?: null;
}

// Can this revision still be decided on? Issued, and not already answered.
function rcr_awaiting($doc) {
    if (empty($doc['finalized'])) return false;
    return rcr_current($doc) === null;
}

// How long the client has been sitting on it. Reported, never acted on: a
// silent client is not an approving one.
function rcr_waiting_days($doc, $today = null) {
    $issued = substr((string)($doc['finalized_at'] ?: $doc['issue_date']), 0, 10);
    if ($issued === '') return null;
    $today = $today ?: date('Y-m-d');
    return (int)floor((strtotime($today) - strtotime($issued)) / 86400);
}

// ---- Writing ---------------------------------------------------------------
// Called from the portal. $doc must already have been fetched through
// portal_report(), which is what proves it belongs to the signed-in client —
// this function does not re-check ownership and must never be given a report
// that was not fetched that way.
function rcr_decide($doc, array $b, $clientUser) {
    rcr_migrate();
    $decision = (string)($b['decision'] ?? '');
    if (!isset(RCR_DECISIONS[$decision])) return 'Choose whether you are accepting or rejecting the report.';
    if (empty($doc['finalized'])) return 'That report has not been issued.';
    if (rcr_current($doc) !== null)
        return 'A decision has already been recorded for this revision. If it needs to change, ask us to re-issue it.';

    $reason = (string)($b['reason'] ?? '');
    $note   = trim((string)($b['note'] ?? ''));
    if ($decision === 'REJECTED') {
        if (!isset(RCR_REASONS[$reason])) return 'Please say why the report is being rejected.';
        // Without this a rejection is "REJECTED / OTHER" and nobody at our end
        // knows what to fix — which is the e-mail problem all over again.
        if ($note === '') return 'Please tell us what needs to change — a reason on its own is not enough to act on.';
    } else {
        $reason = '';
    }

    $rev = (int)($doc['rev'] ?? 0);
    db()->prepare("INSERT INTO report_client_reviews
        (report_doc_id,rev,partner_id,client_user_id,decided_by_name,decided_by_email,
         decision,reason,note,ip,decided_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([(int)$doc['id'], $rev, (int)($clientUser['partner_id'] ?? 0),
                   (int)($clientUser['id'] ?? 0),
                   substr((string)($clientUser['name'] ?? ''), 0, 150),
                   substr((string)($clientUser['email'] ?? ''), 0, 200),
                   $decision, $reason, $note,
                   function_exists('client_ip') ? client_ip() : '', date('c')]);
    $reviewId = (int)db()->lastInsertId();
    try {
        db()->prepare("UPDATE report_docs SET client_decision=?, client_decision_at=? WHERE id=?")
            ->execute([$decision, date('c'), (int)$doc['id']]);
    } catch (Throwable $e) { if (!rcr_missing_table($e)) throw $e; }

    if ($decision === 'REJECTED') rcr_raise_ncr($doc, $reviewId, $reason, $note, $clientUser);
    rcr_notify($doc, $decision, $reason, $note, $clientUser);
    if (function_exists('portal_log'))
        portal_log('REPORT_' . $decision, ($doc['irn'] ?? '') . ($reason ? ' — ' . $reason : ''));
    return '';
}

// A client saying the work is wrong is a nonconformity. It is raised
// automatically because the alternative — hoping somebody reads the e-mail and
// remembers to open one — is exactly what was happening before.
function rcr_raise_ncr($doc, $reviewId, $reason, $note, $clientUser) {
    if (!function_exists('ncr_create')) return 0;
    $client = trim((string)($clientUser['display_name'] ?: $clientUser['legal_name'] ?? ''));
    $id = ncr_create([
        'source'        => 'CLIENT',
        'source_note'   => 'Report ' . ($doc['irn'] ?? '') . ' rejected by ' . ($clientUser['name'] ?? 'the client'),
        'report_doc_id' => (int)$doc['id'],
        'job_id'        => (int)($doc['job_id'] ?? 0),
        'partner_id'    => (int)($doc['client_id'] ?? 0),
        'office_id'     => (int)($doc['office_id'] ?? 0),
        'sbu'           => (string)($doc['sbu'] ?? ''),
        'title'         => 'Report ' . ($doc['irn'] ?? '') . ' rejected by ' . ($client ?: 'the client'),
        // Severity is deliberately NOT derived from the reason. A client
        // rejecting a report is not automatically a major nonconformity —
        // "wrong PO number" and "we disagree with the conclusion" are not the
        // same event, and grading them by rule would be guessing. Somebody
        // reads it and decides.
        'severity'      => 'MINOR',
        'description'   => "The client rejected this report through the portal.\n\n"
                         . 'Reason given: ' . (RCR_REASONS[$reason] ?? $reason) . "\n\n"
                         . "What they said:\n" . $note,
        'detected_by'   => ($clientUser['name'] ?? '') . ' (client)',
    ]);
    if ($id) {
        $ref = (string)ops_val("SELECT ref FROM nonconformities WHERE id=?", [(int)$id]);
        db()->prepare("UPDATE report_client_reviews SET ncr_id=?, ncr_ref=? WHERE id=?")
            ->execute([(int)$id, $ref, (int)$reviewId]);
    }
    return (int)$id;
}

// Tell the people who can act. The report's author and approver first, then
// the branch — a rejection that only reaches a shared inbox is a rejection
// nobody owns.
function rcr_notify($doc, $decision, $reason, $note, $clientUser) {
    if (!function_exists('ops_mail')) return;
    $to = [];
    foreach ([$doc['created_by'] ?? '', $doc['finalized_by'] ?? '', $doc['approved_by'] ?? ''] as $n)
        if (trim((string)$n) !== '') $to[] = $n;
    $to = array_values(array_unique($to));
    if (!$to) return;
    $verb = $decision === 'ACCEPTED' ? 'accepted' : 'REJECTED';
    $body = "Report {$doc['irn']} has been $verb by the client.\n\n"
          . 'By: ' . ($clientUser['name'] ?? '') . ' <' . ($clientUser['email'] ?? '') . ">\n";
    if ($decision === 'REJECTED')
        $body .= 'Reason: ' . (RCR_REASONS[$reason] ?? $reason) . "\n\nWhat they said:\n" . $note
               . "\n\nA nonconformity has been raised automatically. Open it to record what happens to the report.";
    foreach ($to as $t) {
        try { ops_mail($t, "Report {$doc['irn']} $verb by the client", $body); }
        catch (Throwable $e) { /* the decision is recorded whether or not the mail goes */ }
    }
}

// Somebody at our end has seen it. Separate from the nonconformity, because
// acknowledging a rejection and fixing it are different acts.
function rcr_acknowledge($reviewId, $note = '') {
    rcr_migrate();
    db()->prepare("UPDATE report_client_reviews SET ack_by=?, ack_at=?, ack_note=? WHERE id=?")
        ->execute([user_name(current_user()), date('c'), substr(trim((string)$note), 0, 1000), (int)$reviewId]);
}

// ---- The staff-side register ----------------------------------------------
// What is sitting at a client unanswered, and what came back rejected. Scoped
// like every other register.
function rcr_pending($limit = 200) {
    rcr_migrate();
    [$w, $a] = scope_clause('d.office_id', 'd.sbu');
    return rcr_try(fn() => ops_all(
        "SELECT d.id, d.irn, d.title, d.rev, d.finalized_at, d.issue_date, d.office_id,
                bp.display_name, bp.legal_name, o.name office_name
         FROM report_docs d
         LEFT JOIN business_partners bp ON bp.id = d.client_id
         LEFT JOIN offices o ON o.id = d.office_id
         WHERE $w AND d.finalized=1 AND COALESCE(d.deleted,0)=0
           AND NOT EXISTS (SELECT 1 FROM report_client_reviews r
                           WHERE r.report_doc_id = d.id AND r.rev = d.rev)
         ORDER BY COALESCE(NULLIF(d.finalized_at,''), d.issue_date) ASC
         LIMIT " . max(1, (int)$limit), $a));
}

function rcr_rejected($limit = 200) {
    rcr_migrate();
    [$w, $a] = scope_clause('d.office_id', 'd.sbu');
    return rcr_try(fn() => ops_all(
        "SELECT r.*, d.irn, d.title, d.office_id, o.name office_name,
                bp.display_name, bp.legal_name
         FROM report_client_reviews r
         JOIN report_docs d ON d.id = r.report_doc_id
         LEFT JOIN business_partners bp ON bp.id = d.client_id
         LEFT JOIN offices o ON o.id = d.office_id
         WHERE $w AND r.decision='REJECTED'
         ORDER BY r.id DESC LIMIT " . max(1, (int)$limit), $a));
}

function rcr_counts() {
    rcr_migrate();
    return [
        'awaiting' => count(rcr_pending(1000)),
        'rejected' => count(array_filter(rcr_rejected(1000), fn($r) => trim((string)$r['ack_at']) === '')),
    ];
}

// ---- The staff screen ------------------------------------------------------
function rcr_can_view() { return can('mod.idems.view') || can('mod.ncr.view') || is_master(); }

function ops_report_reviews($route, $method) {
    ops_require(rcr_can_view(), 'You cannot open the client-acceptance register.');
    rcr_migrate();

    if ($route === 'report-ack' && $method === 'POST') {
        ops_require(can('mod.idems.edit') || can('mod.ncr.edit') || is_master(),
                    'You cannot acknowledge a client decision.');
        rcr_acknowledge((int)($_POST['id'] ?? 0), (string)($_POST['note'] ?? ''));
        flash('Acknowledged. The nonconformity, if one was raised, still has to be worked through on its own.');
        redirect('/report-reviews?f=rejected');
    }

    $f = $_GET['f'] ?? 'awaiting';
    $pending  = rcr_pending();
    $rejected = rcr_rejected();
    if (wants_csv()) {
        if ($f === 'rejected') {
            $csv = [['Report','Rev','Client','Branch','Reason','What they said','By','When','Nonconformity','Acknowledged']];
            foreach ($rejected as $r)
                $csv[] = [$r['irn'], $r['rev'], $r['display_name'] ?: $r['legal_name'], $r['office_name'],
                          RCR_REASONS[$r['reason']] ?? $r['reason'], $r['note'],
                          $r['decided_by_name'], substr((string)$r['decided_at'], 0, 10),
                          $r['ncr_ref'], substr((string)$r['ack_at'], 0, 10)];
        } else {
            $csv = [['Report','Rev','Title','Client','Branch','Issued','Days waiting']];
            foreach ($pending as $r)
                $csv[] = [$r['irn'], $r['rev'], $r['title'], $r['display_name'] ?: $r['legal_name'],
                          $r['office_name'], substr((string)($r['finalized_at'] ?: $r['issue_date']), 0, 10),
                          rcr_waiting_days($r)];
        }
        csv_download('client-acceptance-' . $f . '-' . date('Y-m-d') . '.csv', $csv);
    }
    view('ops/report_reviews', ['f' => $f, 'pending' => $pending, 'rejected' => $rejected,
                                'canAck' => can('mod.idems.edit') || can('mod.ncr.edit') || is_master()]);
    return true;
}
