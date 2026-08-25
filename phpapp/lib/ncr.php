<?php
// ============================================================================
//  Nonconformity register — the thing that was wrong before it was fixed
//
//  The gap this closes. Until now corrective action was the ONLY container:
//  if you wanted to record that something had gone wrong you had to open a
//  CAPA, which is the *response*, not the event. That has three consequences,
//  all of which showed up the moment somebody tried to use it properly:
//
//   1. **Most nonconformities do not deserve a corrective action.** A report
//      returned by the client for a typo needs correcting and closing, not a
//      root-cause investigation. With only CAPA available, either you open one
//      and it is noise, or you record nothing and the event is invisible.
//   2. **There was nowhere for the client's rejection to land.** A client who
//      rejects an issued report was, in software terms, an e-mail.
//   3. **Nothing counted.** "How many nonconformities did Pune raise last
//      quarter, and how many needed corrective action?" had no answer,
//      because the population did not exist as an object.
//
//  So: a nonconformity is raised, contained, dispositioned and closed. It may
//  escalate into a corrective action, and a major one **must**. The CAPA
//  remains what it always was — cause, plan, verification of effectiveness —
//  and stops being asked to double as the incident log.
//
//  Where they come from, all wired rather than typed in again:
//    a deputation · an issued report · a client rejecting a report ·
//    a complaint · an internal audit finding · equipment found out of
//    calibration · a subcontractor · a data or system failure · somebody
//    noticing something.
//
//  The four rules that make it a register rather than a list of text boxes:
//
//   1. **Nothing closes without a disposition.** "What happened to the work
//      that was already wrong?" is the question an assessor asks, and rework /
//      reissue / accept-as-is / withdraw are different answers with different
//      consequences. An NCR closed without one records that nobody decided.
//   2. **A major nonconformity cannot close without a corrective action**, and
//      no nonconformity can close while its corrective action is still open.
//      Closing the symptom while the cause is open is the failure mode the
//      whole clause exists to prevent.
//   3. **Accepting a nonconformity as it stands needs a reason and a name.**
//      It is the disposition most likely to be chosen for convenience.
//   4. **It belongs to a branch.** Complaints and CAPA both stored office_id
//      and never read it, so a Pune manager saw the whole company. Here the
//      register is scoped from the first query, not as an afterthought.
// ============================================================================

const NCR_SOURCES = [
    'JOB'         => '',   // label built by ncr_source_label()
    'REPORT'      => 'An issued report',
    'CLIENT'      => 'Rejected by the client',
    'COMPLAINT'   => 'A complaint',
    'AUDIT'       => 'An internal audit finding',
    'EQUIPMENT'   => 'Equipment found unfit or out of calibration',
    'SUBCON'      => 'A subcontractor',
    'DATA'        => 'A data or system failure',
    'INTERNAL'    => 'Noticed by our own staff',
];

// NCR_SOURCES is a constant, so the one entry that names a business noun is
// left blank there and filled in here, where T() can be called.
function ncr_sources() {
    $m = NCR_SOURCES;
    $m['JOB'] = 'A ' . Tl('job');
    return $m;
}
function ncr_source_label($k) { return ncr_sources()[$k] ?? $k; }


// Major / minor is not a mood. The definitions are on the form, because two
// people grading the same event differently makes the register uncountable.
const NCR_SEVERITY = [
    'MAJOR'       => 'Major — the result is unreliable, or a requirement was not met at all',
    'MINOR'       => 'Minor — a requirement was partly met; the result still stands',
    'OBSERVATION' => 'Observation — nothing is broken yet',
];

// What happened to the work that was already wrong. This is the question an
// assessor asks and the reason the register exists at all.
const NCR_DISPOSITIONS = [
    ''           => '— not yet decided —',
    'REWORK'     => 'Re-do the work',
    'REISSUE'    => 'Correct and re-issue the document',
    'ACCEPT'     => 'Accept as it stands (needs a reason)',
    'WITHDRAW'   => 'Withdraw the document or certificate',
    'VOID'       => 'Void it — the work will not be delivered',
    'NONE'       => 'Nothing to put right',
];

const NCR_STATUS = [
    'OPEN'         => 'Open',
    'CONTAINED'    => 'Contained',
    'DISPOSITIONED'=> 'Disposition agreed',
    'CLOSED'       => 'Closed',
];

function ncr_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = pk_clause();
    $pdo->exec("CREATE TABLE IF NOT EXISTS nonconformities (
        id $pk, ref VARCHAR(40) DEFAULT '',
        source VARCHAR(20) DEFAULT 'INTERNAL', source_note VARCHAR(255) DEFAULT '',
        job_id INT NULL, report_doc_id INT NULL, complaint_id INT NULL,
        audit_finding_id INT NULL, equipment_id INT NULL, partner_id INT NULL,
        office_id INT NULL, sbu VARCHAR(20) DEFAULT '',
        title VARCHAR(255) DEFAULT '', description TEXT,
        clause VARCHAR(20) DEFAULT '', severity VARCHAR(20) DEFAULT 'MINOR',
        detected_on VARCHAR(20) DEFAULT '', detected_by VARCHAR(150) DEFAULT '',
        owner VARCHAR(150) DEFAULT '', due_on VARCHAR(20) DEFAULT '',
        containment TEXT, contained_on VARCHAR(20) DEFAULT '', contained_by VARCHAR(150) DEFAULT '',
        disposition VARCHAR(20) DEFAULT '', disposition_note TEXT,
        disposition_by VARCHAR(150) DEFAULT '', disposition_on VARCHAR(20) DEFAULT '',
        capa_id INT NULL, capa_ref VARCHAR(80) DEFAULT '',
        status VARCHAR(20) DEFAULT 'OPEN',
        closed_on VARCHAR(20) DEFAULT '', closed_by VARCHAR(150) DEFAULT '', close_note TEXT,
        raised_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ncr_events (
        id $pk, ncr_id INT, action VARCHAR(40) DEFAULT '',
        actor VARCHAR(150) DEFAULT '', note VARCHAR(1000) DEFAULT '', at VARCHAR(30) DEFAULT '')");
    // The corrective action points back, so the two are navigable both ways
    // without a join table for what is a one-to-one escalation.
    ensure_column('capa', 'ncr_id', 'INT NULL');
}

function ncr_missing_table(Throwable $e) {
    $m = $e->getMessage();
    return stripos($m, 'no such table') !== false || stripos($m, "doesn't exist") !== false;
}

// ---- The register's columns -------------------------------------------------
// Severity sorts by meaning, not alphabetically: MAJOR before MINOR before
// OBSERVATION. Sorting a severity column as text puts "Major" after
// "Concession" and quietly buries the ones that matter.
function ncr_dt_columns($today) {
    return [
        'ref' => ['label' => 'Ref', 'sort' => 'n.ref', 'render' => fn($r) =>
            '<a href="/ncr-item?id=' . (int)$r['id'] . '"><b>' . e($r['ref']) . '</b></a>'],
        'severity' => ['label' => 'Severity',
            'sort' => "CASE n.severity WHEN 'MAJOR' THEN 0 WHEN 'MINOR' THEN 1 ELSE 2 END",
            'render' => fn($r) => '<span class="pill '
                . ($r['severity'] === 'MAJOR' ? 'p-bad' : ($r['severity'] === 'MINOR' ? 'p-warn' : 'p-mut'))
                . '" style="font-size:11px">' . e(ucfirst(strtolower($r['severity']))) . '</span>'],
        'title' => ['label' => 'What was wrong', 'sort' => 'n.title', 'render' => fn($r) =>
            '<a href="/ncr-item?id=' . (int)$r['id'] . '">' . e($r['title']) . '</a>'
            . '<br><span class="muted" style="font-size:12px">raised ' . e(fdate($r['detected_on']))
            . ' by ' . e($r['detected_by'] ?: '—') . '</span>'],
        'source' => ['label' => 'Where it came from', 'sort' => 'n.source', 'render' => function ($r) {
            $h = e(ncr_source_label($r['source']));
            if ($r['job_code']) $h .= '<br><span class="muted" style="font-size:12px">' . e($r['job_code']) . '</span>';
            if ($r['display_name'] || $r['legal_name'])
                $h .= '<br><span class="muted" style="font-size:12px">' . e($r['display_name'] ?: $r['legal_name']) . '</span>';
            return $h;
        }],
        'branch' => ['label' => 'Branch', 'sort' => 'o.name', 'render' => fn($r) => e($r['office_name'] ?: '—')],
        'owner' => ['label' => 'Owner / due', 'sort' => 'n.due_on', 'render' => function ($r) use ($today) {
            $od = ($r['status'] !== 'CLOSED') && $r['due_on'] && $r['due_on'] < $today;
            return e($r['owner'] ?: '—') . '<br><span class="' . ($od ? 'pill p-bad' : 'muted') . '" style="font-size:12px">'
                 . ($r['due_on'] ? e(fdate($r['due_on'])) . ($od ? ' — late' : '') : 'no date') . '</span>';
        }],
        'clause' => ['label' => 'Clause', 'sort' => 'n.clause', 'optional' => true,
            'render' => fn($r) => e($r['clause'] ?: '—')],
        'disposition' => ['label' => 'Disposition', 'sort' => 'n.disposition', 'render' => fn($r) =>
            trim((string)$r['disposition']) === ''
                ? '<span class="pill p-warn">Not decided</span>'
                : e(NCR_DISPOSITIONS[$r['disposition']] ?? $r['disposition'])],
        'capa' => ['label' => 'Corrective action', 'sort' => 'n.capa_ref', 'render' => fn($r) =>
            $r['capa_ref'] ? '<a href="/capa-item?id=' . (int)$r['capa_id'] . '">' . e($r['capa_ref']) . '</a>'
                           : ($r['severity'] === 'MAJOR' ? '<span class="pill p-bad">Required</span>'
                                                         : '<span class="muted">—</span>')],
        'status' => ['label' => 'Status', 'sort' => 'n.status', 'render' => fn($r) =>
            '<span class="pill ' . ($r['status'] === 'CLOSED' ? 'p-ok' : ($r['status'] === 'OPEN' ? 'p-warn' : 'p-mut'))
            . '">' . e(NCR_STATUS[$r['status']] ?? $r['status']) . '</span>'],
        'closed' => ['label' => 'Closed', 'sort' => 'n.closed_on', 'optional' => true,
            'render' => fn($r) => $r['closed_on'] ? e(fdate($r['closed_on'])) : '—'],
    ];
}

// ---- Who ------------------------------------------------------------------
function ncr_can_view()  { return can('mod.ncr.view') || can('mod.capa.view') || is_master_of(['ncr','capa']); }
function ncr_can_raise() { return can('mod.ncr.edit') || can('mod.capa.edit') || is_master_of(['ncr','capa']); }
// Closing is a separate act from raising, for the same reason approving is
// separate from creating everywhere else in this application.
function ncr_can_close() { return can('ncr.close') || can('capa.close') || is_master(); }

// ---- Numbering -------------------------------------------------------------
function ncr_ref_next() {
    $y = date('Y');
    $n = (int)ops_val("SELECT COUNT(*) FROM nonconformities WHERE ref LIKE ?", ["NCR-$y-%"]);
    do { $n++; $ref = sprintf('NCR-%s-%04d', $y, $n); }
    while ((int)ops_val("SELECT COUNT(*) FROM nonconformities WHERE ref=?", [$ref]) > 0);
    return $ref;
}

// ---- Reading ---------------------------------------------------------------
// Scoped from the first query. The office is taken from the deputation the
// nonconformity came off when there is one, so it lands with the branch that
// did the work rather than the branch of whoever happened to type it in.
const NCR_FROM = "FROM nonconformities n
             LEFT JOIN offices o ON o.id = n.office_id
             LEFT JOIN jobs j ON j.id = n.job_id
             LEFT JOIN business_partners bp ON bp.id = n.partner_id";

// One filter, used by both the count and the page of rows. They have to agree
// or the pager promises pages that are not there.
function ncr_where($filter = 'open', $q = '', $extraWhere = '', $extraArgs = []) {
    [$w, $a] = scope_clause('n.office_id', 'n.sbu');
    $f = '1=1';
    switch ($filter) {
        case 'open':      $f = "n.status <> 'CLOSED'"; break;
        case 'major':     $f = "n.status <> 'CLOSED' AND n.severity='MAJOR'"; break;
        case 'overdue':   $f = "n.status <> 'CLOSED' AND n.due_on <> '' AND n.due_on < ?"; $a[] = date('Y-m-d'); break;
        case 'nodisp':    $f = "n.status <> 'CLOSED' AND (n.disposition IS NULL OR n.disposition='')"; break;
        case 'closed':    $f = "n.status = 'CLOSED'"; break;
        case 'all':       $f = '1=1'; break;
    }
    if ($extraWhere !== '') { $f .= " AND ($extraWhere)"; $a = array_merge($a, $extraArgs); }
    if (trim((string)$q) !== '') {
        $f .= " AND (n.ref LIKE ? OR n.title LIKE ? OR n.description LIKE ? OR n.owner LIKE ?)";
        $like = '%' . trim((string)$q) . '%';
        array_push($a, $like, $like, $like, $like);
    }
    return ["$w AND ($f)", $a];
}

const NCR_ORDER = "(n.status='CLOSED') ASC,
                      CASE n.severity WHEN 'MAJOR' THEN 0 WHEN 'MINOR' THEN 1 ELSE 2 END,
                      n.detected_on DESC, n.id DESC";

// Module 12 — the nonconformities on one job (open first, then closed), for the
// Job-360 Quality panel. Office-scoped like the register, so it never widens
// visibility. Safe (empty) on an un-migrated install.
function ncr_for_job($jobId) {
    $jobId = (int)$jobId; if (!$jobId) return [];
    try { return ncr_all('all', 'n.job_id = ?', [$jobId], '', 'ORDER BY ' . NCR_ORDER); }
    catch (Throwable $e) { return []; }
}

// May the current viewer reach the NCR module at all (permission + accreditation pack)?
function ncr_reachable() {
    $perm = (function_exists('can') && (can('mod.ncr.view') || can('mod.capa.view'))) || (function_exists('is_master') && is_master());
    $pack = !function_exists('accredited_pack_on') || accredited_pack_on();
    return $perm && $pack;
}

// $tail is built by the register component from its own column whitelist.
function ncr_all($filter = 'open', $extraWhere = '', $extraArgs = [], $q = '', $tail = '') {
    ncr_migrate();
    [$w, $a] = ncr_where($filter, $q, $extraWhere, $extraArgs);
    try {
        return ops_all("SELECT n.*, o.name office_name, j.job_code, bp.display_name, bp.legal_name "
                       . NCR_FROM . " WHERE $w " . ($tail ?: 'ORDER BY ' . NCR_ORDER), $a);
    } catch (Throwable $e) { if (!ncr_missing_table($e)) throw $e; return []; }
}

function ncr_count($filter = 'open', $extraWhere = '', $extraArgs = [], $q = '') {
    ncr_migrate();
    [$w, $a] = ncr_where($filter, $q, $extraWhere, $extraArgs);
    try { return (int)ops_val("SELECT COUNT(*) " . NCR_FROM . " WHERE $w", $a); }
    catch (Throwable $e) { if (!ncr_missing_table($e)) throw $e; return 0; }
}

function ncr_row($id) {
    ncr_migrate();
    try {
        return ops_one("SELECT n.*, o.name office_name, j.job_code, bp.display_name, bp.legal_name
                        FROM nonconformities n
                        LEFT JOIN offices o ON o.id = n.office_id
                        LEFT JOIN jobs j ON j.id = n.job_id
                        LEFT JOIN business_partners bp ON bp.id = n.partner_id
                        WHERE n.id=?", [(int)$id]);
    } catch (Throwable $e) { if (!ncr_missing_table($e)) throw $e; return null; }
}

function ncr_events($id) {
    try { return ops_all("SELECT * FROM ncr_events WHERE ncr_id=? ORDER BY id DESC", [(int)$id]); }
    catch (Throwable $e) { if (!ncr_missing_table($e)) throw $e; return []; }
}

function ncr_log($id, $action, $note = '') {
    try {
        db()->prepare("INSERT INTO ncr_events (ncr_id,action,actor,note,at) VALUES (?,?,?,?,?)")
            ->execute([(int)$id, $action, user_name(current_user()), substr((string)$note, 0, 1000), date('c')]);
    } catch (Throwable $e) { if (!ncr_missing_table($e)) throw $e; }
}

// ---- The gates -------------------------------------------------------------
// Everything standing between this nonconformity and being closed, as a list
// of sentences. Returning the reasons rather than a boolean means the screen
// can show all of them at once instead of revealing them one refusal at a time.
function ncr_close_missing($n) {
    $missing = [];
    if (trim((string)($n['disposition'] ?? '')) === '')
        $missing[] = 'the disposition — what happened to the work that was already wrong';
    if (($n['disposition'] ?? '') === 'ACCEPT' && trim((string)($n['disposition_note'] ?? '')) === '')
        $missing[] = 'a reason for accepting it as it stands';
    if (trim((string)($n['containment'] ?? '')) === '')
        $missing[] = 'what was done immediately to contain it';
    // §8.7: a major nonconformity is precisely the case where finding the cause
    // is not optional. Closing one with no corrective action records that
    // nobody asked why.
    if (($n['severity'] ?? '') === 'MAJOR' && empty($n['capa_id']))
        $missing[] = 'a corrective action — a major nonconformity cannot be closed without one';
    if (!empty($n['capa_id']) && function_exists('capa_row')) {
        $c = capa_row((int)$n['capa_id']);
        if ($c && ($c['status'] ?? '') !== 'CLOSED')
            $missing[] = 'its corrective action ' . ($c['ref'] ?? '') . ' to be closed first';
    }
    return $missing;
}

function ncr_close_block($n) {
    $m = ncr_close_missing($n);
    return $m ? 'This nonconformity cannot be closed yet. Still needed: ' . implode('; ', $m) . '.' : '';
}

function ncr_is_overdue($n, $today = null) {
    $today = $today ?: date('Y-m-d');
    return ($n['status'] ?? '') !== 'CLOSED' && ($n['due_on'] ?? '') !== '' && $n['due_on'] < $today;
}

// ---- Writing ---------------------------------------------------------------
// One way in, whoever is calling: the client portal, a complaint, an audit
// finding, the equipment register or somebody typing it. Every caller gets the
// same numbering, the same branch derivation and the same opening event.
function ncr_create(array $b) {
    ncr_migrate();
    $ref = ncr_ref_next();
    $jobId = (int)($b['job_id'] ?? 0) ?: null;

    // Land it with the branch that did the work. Only fall back to the
    // raiser's own office when there is no deputation to read it off.
    $office = ($b['office_id'] ?? '') !== '' ? (int)$b['office_id'] : null;
    $sbu    = (string)($b['sbu'] ?? '');
    if ($jobId) {
        $j = ops_one("SELECT executing_office_id, sbu FROM jobs WHERE id=?", [$jobId]);
        if ($j) {
            if (!$office) $office = $j['executing_office_id'] !== null ? (int)$j['executing_office_id'] : null;
            if ($sbu === '') $sbu = (string)$j['sbu'];
        }
    }
    if (!$office) { $u = current_user(); $office = !empty($u['office_id']) ? (int)$u['office_id'] : null; }

    $sev = isset(NCR_SEVERITY[$b['severity'] ?? '']) ? $b['severity'] : 'MINOR';
    $src = isset(NCR_SOURCES[$b['source'] ?? '']) ? $b['source'] : 'INTERNAL';
    $detected = trim((string)($b['detected_on'] ?? '')) ?: date('Y-m-d');
    // A major nonconformity gets a shorter rope than a minor one.
    $due = trim((string)($b['due_on'] ?? ''));
    if ($due === '') $due = date('Y-m-d', strtotime($detected . ' +' . ($sev === 'MAJOR' ? 14 : 30) . ' days'));

    db()->prepare("INSERT INTO nonconformities
        (ref,source,source_note,job_id,report_doc_id,complaint_id,audit_finding_id,equipment_id,partner_id,
         office_id,sbu,title,description,clause,severity,detected_on,detected_by,owner,due_on,
         containment,status,raised_by,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'OPEN', ?,?)")
        ->execute([$ref, $src, substr(trim((string)($b['source_note'] ?? '')), 0, 255),
            $jobId,
            (int)($b['report_doc_id'] ?? 0) ?: null,
            (int)($b['complaint_id'] ?? 0) ?: null,
            (int)($b['audit_finding_id'] ?? 0) ?: null,
            (int)($b['equipment_id'] ?? 0) ?: null,
            (int)($b['partner_id'] ?? 0) ?: null,
            $office, $sbu,
            substr(trim((string)($b['title'] ?? 'Nonconformity')), 0, 255),
            (string)($b['description'] ?? ''),
            substr(trim((string)($b['clause'] ?? '')), 0, 20), $sev,
            $detected, substr(trim((string)($b['detected_by'] ?? user_name(current_user()))), 0, 150),
            substr(trim((string)($b['owner'] ?? '')), 0, 150), $due,
            (string)($b['containment'] ?? ''),
            user_name(current_user()), date('c')]);
    $id = (int)db()->lastInsertId();
    // Phase 8 (NCDCA): let the universal issue layer stamp the issue type /
    // classification / responsibility / visibility if provided. Additive and
    // guarded — absent keys leave the record exactly as before (a plain NCR).
    if (function_exists('ncdca_apply_classification')) { try { ncdca_apply_classification($id, $b); } catch (Throwable $e) {} }
    if (function_exists('act_log'))
        act_log('NCR', $id, 'SYSTEM', 'Nonconformity ' . $ref . ' raised',
                ['auto' => 1, 'body' => (string)($b['title'] ?? ''),
                 'partner_id' => ($b['partner_id'] ?? '') !== '' ? (int)$b['partner_id'] : null,
                 'office_id' => $office]);
    ncr_log($id, 'RAISED', $ref . ' — ' . ncr_source_label($src)
        . ($b['source_note'] ?? '' ? ': ' . $b['source_note'] : ''));
    return $id;
}

// Escalate into a corrective action, carrying everything already known so
// nobody retypes it. Returns the CAPA id.
function ncr_to_capa($ncrId) {
    $n = ncr_row($ncrId);
    if (!$n || !function_exists('capa_create')) return 0;
    if (!empty($n['capa_id'])) return (int)$n['capa_id'];
    $capaId = capa_create([
        'source'      => 'NCR',
        'source_ref'  => $n['ref'],
        'ncr_id'      => (int)$n['id'],
        'complaint_id'=> $n['complaint_id'],
        'title'       => $n['title'],
        'description' => "Raised from nonconformity {$n['ref']}.\n\n" . (string)$n['description'],
        'clause'      => $n['clause'],
        'severity'    => $n['severity'] === 'MAJOR' ? 'MAJOR' : 'MINOR',
        'office_id'   => $n['office_id'],
        'immediate_action' => (string)$n['containment'],
        'owner'       => $n['owner'],
    ]);
    if (!$capaId) return 0;
    $ref = (string)ops_val("SELECT ref FROM capa WHERE id=?", [(int)$capaId]);
    try { db()->prepare("UPDATE capa SET ncr_id=? WHERE id=?")->execute([(int)$n['id'], (int)$capaId]); }
    catch (Throwable $e) { /* column added by ncr_migrate; older row shape */ }
    db()->prepare("UPDATE nonconformities SET capa_id=?, capa_ref=? WHERE id=?")
        ->execute([(int)$capaId, $ref, (int)$n['id']]);
    ncr_log((int)$n['id'], 'CAPA', 'Escalated to corrective action ' . $ref);
    return (int)$capaId;
}

// ---- Counts for the cards --------------------------------------------------
function ncr_counts() {
    ncr_migrate();
    [$w, $a] = scope_clause('n.office_id', 'n.sbu');
    $today = date('Y-m-d');
    $q = function ($extra, $args = []) use ($w, $a) {
        try { return (int)ops_val("SELECT COUNT(*) FROM nonconformities n WHERE $w AND ($extra)", array_merge($a, $args)); }
        catch (Throwable $e) { if (!ncr_missing_table($e)) throw $e; return 0; }
    };
    return [
        'open'    => $q("n.status <> 'CLOSED'"),
        'major'   => $q("n.status <> 'CLOSED' AND n.severity='MAJOR'"),
        'overdue' => $q("n.status <> 'CLOSED' AND n.due_on <> '' AND n.due_on < ?", [$today]),
        'nodisp'  => $q("n.status <> 'CLOSED' AND (n.disposition IS NULL OR n.disposition='')"),
    ];
}

// What the compliance screen reads, in the same shape as the other registers.
function ncr_readiness() {
    $c = ncr_counts();
    return ['open' => $c['open'], 'major' => $c['major'], 'overdue' => $c['overdue'],
            'without_disposition' => $c['nodisp']];
}

// Nightly: chase the owner of anything overdue, once per day per record.
function ncr_run_reminders($today = null) {
    ncr_migrate();
    $today = $today ?: date('Y-m-d');
    $sent = 0;
    try {
        $rows = ops_all("SELECT * FROM nonconformities
                         WHERE status <> 'CLOSED' AND due_on <> '' AND due_on < ?", [$today]);
    } catch (Throwable $e) { if (!ncr_missing_table($e)) throw $e; return 0; }
    foreach ($rows as $n) {
        $to = trim((string)$n['owner']);
        if ($to === '') continue;
        $days = (int)floor((strtotime($today) - strtotime($n['due_on'])) / 86400);
        if (function_exists('ops_mail')) {
            ops_mail($to, "Nonconformity {$n['ref']} is $days day(s) past its date",
                "{$n['ref']} — {$n['title']}\n\nSeverity: " . (NCR_SEVERITY[$n['severity']] ?? $n['severity'])
                . "\nDue: {$n['due_on']} ($days day(s) ago)\n\n"
                . "Open it in the application to record the disposition and close it.");
            $sent++;
        }
    }
    return $sent;
}

// ---- Screens ---------------------------------------------------------------
function ops_ncr($route, $method) {
    ops_require(ncr_can_view(), 'You cannot open the nonconformity register.');
    ncr_migrate();

    if ($route === 'ncr') {
        // Default to ALL when scoping to one job/report so the chip shows that entity's
        // whole history (open + closed), not just its open ones. The register chip on a
        // job used to link ?job= but this handler ignored it — landing on the full
        // register. Now it scopes to that job (or report), respecting office scope.
        $scopeJob    = (int)($_GET['job'] ?? 0);
        $scopeReport = (int)($_GET['report'] ?? 0);
        $scoped = $scopeJob || $scopeReport;
        $f = $_GET['f'] ?? ($scoped ? 'all' : 'open');
        $extraW = ''; $extraA = [];
        if ($scopeJob)    { $extraW = 'n.job_id = ?';        $extraA = [$scopeJob]; }
        elseif ($scopeReport) { $extraW = 'n.report_doc_id = ?'; $extraA = [$scopeReport]; }
        $cols = ncr_dt_columns(date('Y-m-d'));
        // Severity first by default: a major nonconformity that scrolled off the
        // bottom of a date-sorted list is the exact failure this register exists
        // to prevent.
        $dt = dt_state('ncr', $cols, ['default_sort' => 'severity', 'default_dir' => 'asc', 'per' => 50]);

        if (wants_csv()) {
            $csv = [['Ref','Raised','Severity','Source','Title','Branch',TH('job'),'Owner','Due','Disposition','Corrective action','Status','Closed']];
            foreach (ncr_all($f, $extraW, $extraA, $dt['q']) as $r)
                $csv[] = [$r['ref'], $r['detected_on'], $r['severity'], ncr_source_label($r['source']),
                          $r['title'], $r['office_name'], $r['job_code'], $r['owner'], $r['due_on'],
                          NCR_DISPOSITIONS[$r['disposition']] ?? $r['disposition'], $r['capa_ref'],
                          $r['status'], $r['closed_on']];
            csv_download('nonconformities-' . $f . '-' . date('Y-m-d') . '.csv', $csv);
        }
        $total = ncr_count($f, $extraW, $extraA, $dt['q']);
        $rows  = ncr_all($f, $extraW, $extraA, $dt['q'], dt_sql_tail($dt, $cols, NCR_ORDER));
        view('ops/ncr_list', ['rows' => $rows, 'total' => $total, 'dt' => $dt, 'cols' => $cols,
                              'f' => $f, 'counts' => ncr_counts(),
                              'canRaise' => ncr_can_raise(), 'today' => date('Y-m-d'),
                              'scopeJob' => $scopeJob, 'scopeReport' => $scopeReport]);
        return true;
    }

    if ($route === 'ncr-item') {
        $n = ncr_row($_GET['id'] ?? 0);
        if (!$n) { http_response_code(404); view('notfound'); return true; }
        // Phase-8 (NCDCA) issue panel — classification, departures, disputes,
        // extensions & possible repeats. Only when the universal issue engine is
        // present and the service is active.
        $issueOn = function_exists('ncdca_enabled') && ncdca_enabled() && function_exists('ncdca_issue_panel');
        view('ops/ncr_detail', [
            'n' => $n, 'events' => ncr_events((int)$n['id']),
            'missing' => ncr_close_missing($n),
            'canEdit' => ncr_can_raise(), 'canClose' => ncr_can_close(),
            'capa' => (!empty($n['capa_id']) && function_exists('capa_row')) ? capa_row((int)$n['capa_id']) : null,
            'issue'         => $issueOn ? ncdca_issue_panel((int)$n['id']) : null,
            'issueTypes'    => $issueOn ? ncdca_issue_types() : [],
            'issueClasses'  => $issueOn ? ncdca_classes() : [],
            'issueResp'     => $issueOn ? ncdca_responsibilities() : [],
            'issueVis'      => $issueOn ? ncdca_visibilities() : [],
            'depKinds'      => defined('NCDCA_DEPARTURE_KINDS') ? NCDCA_DEPARTURE_KINDS : [],
            'depStatuses'   => $issueOn ? ncdca_departure_statuses() : [],
            'disputeStatus' => defined('NCDCA_DISPUTE_STATUS') ? NCDCA_DISPUTE_STATUS : [],
            'issueCanEdit'  => $issueOn ? ncdca_can_edit() : false,
        ]);
        return true;
    }

    // Everything below writes.
    ops_require(ncr_can_raise(), 'You cannot raise or change a nonconformity.');

    if ($route === 'ncr-new') {
        if ($method === 'POST') {
            $title = trim((string)($_POST['title'] ?? ''));
            if ($title === '') { flash('A nonconformity needs a title — one line saying what was wrong.', 'error'); redirect('/ncr-new'); }
            $id = ncr_create($_POST);
            flash('Nonconformity raised. Record what was done immediately to contain it, then agree the disposition.');
            redirect('/ncr-item?id=' . $id);
        }
        view('ops/ncr_form', [
            'prefill' => $_GET,
            'offices' => ops_all("SELECT id, name FROM offices ORDER BY name"),
            'jobs'    => ops_all("SELECT id, job_code FROM jobs ORDER BY id DESC LIMIT 300"),
        ]);
        return true;
    }

    $n = ncr_row($_POST['id'] ?? $_GET['id'] ?? 0);
    if (!$n) { http_response_code(404); view('notfound'); return true; }
    $back = '/ncr-item?id=' . (int)$n['id'];
    if ($method !== 'POST') redirect($back);

    if ($route === 'ncr-contain') {
        $t = trim((string)($_POST['containment'] ?? ''));
        if ($t === '') { flash('Say what was actually done to contain it.', 'error'); redirect($back); }
        db()->prepare("UPDATE nonconformities SET containment=?, contained_on=?, contained_by=?,
                       status=CASE WHEN status='OPEN' THEN 'CONTAINED' ELSE status END WHERE id=?")
            ->execute([$t, date('Y-m-d'), user_name(current_user()), (int)$n['id']]);
        ncr_log((int)$n['id'], 'CONTAINED', $t);
        flash('Containment recorded.');
        redirect($back);
    }

    if ($route === 'ncr-disposition') {
        $d = (string)($_POST['disposition'] ?? '');
        if (!isset(NCR_DISPOSITIONS[$d]) || $d === '') { flash('Choose what happened to the work.', 'error'); redirect($back); }
        $note = trim((string)($_POST['disposition_note'] ?? ''));
        // Accepting a nonconformity as it stands is the disposition most likely
        // to be picked for convenience, so it is the one that needs a name
        // against a reason.
        if ($d === 'ACCEPT' && $note === '') {
            flash('Accepting it as it stands needs a reason — that is the whole point of recording this one.', 'error');
            redirect($back);
        }
        db()->prepare("UPDATE nonconformities SET disposition=?, disposition_note=?, disposition_by=?,
                       disposition_on=?, status=CASE WHEN status IN ('OPEN','CONTAINED') THEN 'DISPOSITIONED' ELSE status END
                       WHERE id=?")
            ->execute([$d, $note, user_name(current_user()), date('Y-m-d'), (int)$n['id']]);
        ncr_log((int)$n['id'], 'DISPOSITION', (NCR_DISPOSITIONS[$d] ?? $d) . ($note ? ' — ' . $note : ''));
        flash('Disposition recorded.');
        redirect($back);
    }

    if ($route === 'ncr-capa') {
        $capaId = ncr_to_capa((int)$n['id']);
        if (!$capaId) { flash('Could not open a corrective action from this nonconformity.', 'error'); redirect($back); }
        flash('Corrective action opened, carrying everything already recorded here.');
        redirect('/capa-item?id=' . $capaId);
    }

    if ($route === 'ncr-assign') {
        db()->prepare("UPDATE nonconformities SET owner=?, due_on=? WHERE id=?")
            ->execute([substr(trim((string)($_POST['owner'] ?? '')), 0, 150),
                       trim((string)($_POST['due_on'] ?? '')), (int)$n['id']]);
        ncr_log((int)$n['id'], 'ASSIGNED', trim((string)($_POST['owner'] ?? '')) . ' by ' . trim((string)($_POST['due_on'] ?? '')));
        flash('Owner and date set.');
        redirect($back);
    }

    if ($route === 'ncr-close') {
        ops_require(ncr_can_close(), 'You cannot close a nonconformity.');
        // Re-read so the gate judges what is in the database now, not what the
        // screen showed whoever opened it ten minutes ago.
        $fresh = ncr_row((int)$n['id']);
        if (($why = ncr_close_block($fresh)) !== '') { flash($why, 'error'); redirect($back); }
        db()->prepare("UPDATE nonconformities SET status='CLOSED', closed_on=?, closed_by=?, close_note=? WHERE id=?")
            ->execute([date('Y-m-d'), user_name(current_user()),
                       trim((string)($_POST['close_note'] ?? '')), (int)$n['id']]);
        ncr_log((int)$n['id'], 'CLOSED', trim((string)($_POST['close_note'] ?? '')));
        flash('Nonconformity ' . $fresh['ref'] . ' closed.');
        redirect($back);
    }

    if ($route === 'ncr-reopen') {
        db()->prepare("UPDATE nonconformities SET status='DISPOSITIONED', closed_on='', closed_by='' WHERE id=?")
            ->execute([(int)$n['id']]);
        ncr_log((int)$n['id'], 'REOPENED', trim((string)($_POST['why'] ?? '')));
        flash('Reopened.');
        redirect($back);
    }
    redirect($back);
}
