<?php
// ============================================================================
//  Impartiality — the clause a Type A inspection body exists to satisfy
//
//  ISO/IEC 17020 §4.1. Everything else in the standard is about doing the work
//  properly; this is about being ENTITLED to do it at all. An inspection body
//  that cannot show it identified and dealt with threats to its independence
//  is not an inspection body, whatever the quality of its reports.
//
//  The 2026 edition made this heavier, not lighter. Threats now explicitly
//  include those arising from ORGANISATIONAL RELATIONSHIPS, from OUTSOURCING,
//  and from FINANCIAL PRESSURE — the last of which is the one bodies find
//  hardest to write down honestly, because it means "this client is 40% of the
//  branch's revenue and everybody knows it".
//
//  Three things this file makes true:
//
//   1. A DECLARATION EXISTS, PER PERSON, WITH A DATE. Not a policy on a wall —
//      a dated statement by a named individual that renews.
//   2. A DECLARED THREAT IS A RECORD, NOT A CONVERSATION. Who it involves,
//      which kind of threat it is, what was decided, by whom, and when it is
//      reviewed again.
//   3. AN UNRESOLVED THREAT STOPS THE ALLOCATION. A register nobody acts on is
//      worse than no register — it proves the body knew.
//
//  Deliberately NOT done: scoring impartiality out of ten, or any automatic
//  judgement about whether a relationship is acceptable. That is a decision for
//  a person who can be held to it, and the record's job is to show they took it.
// ============================================================================

// The kinds of threat §4.1 asks a body to consider. The last three are what
// 2026 added weight to.
const IMP_THREATS = [
    'SELF_INTEREST' => 'Self-interest — we gain from a particular outcome',
    'SELF_REVIEW'   => 'Self-review — we would be checking our own work or advice',
    'ADVOCACY'      => 'Advocacy — we have promoted the client or the item',
    'FAMILIARITY'   => 'Familiarity — a personal or long relationship with those involved',
    'INTIMIDATION'  => 'Intimidation — pressure, real or felt, to reach a conclusion',
    'ORGANISATIONAL'=> 'Organisational — ownership, control or shared management',
    'OUTSOURCING'   => 'Outsourcing — a subcontractor’s own interests',
    'FINANCIAL'     => 'Financial pressure — dependence on this client’s income',
];

const IMP_STATUS = [
    'OPEN'         => 'Open — not yet decided',
    'MITIGATED'    => 'Mitigated — safeguards in place',
    'ACCEPTED'     => 'Accepted — residual risk judged acceptable',
    'UNACCEPTABLE' => 'Unacceptable — we must not do this work',
];

// Statuses in which the body has NOT cleared the threat. Work is refused.
const IMP_BLOCKING = ['OPEN', 'UNACCEPTABLE'];

// 2026 replaced Type A / B / C with Type A / Type non-A.
const IB_TYPES = [
    'A'     => 'Type A — independent third party',
    'NON_A' => 'Type non-A — supplier, user or otherwise not fully independent',
];

function impartiality_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = pk_clause();
    // The periodic statement by a named person.
    $pdo->exec("CREATE TABLE IF NOT EXISTS impartiality_declarations (
        id $pk, person_kind VARCHAR(20) DEFAULT 'INSPECTOR', person_id INT,
        declared_on VARCHAR(20) DEFAULT '', valid_to VARCHAR(20) DEFAULT '',
        has_conflicts INT DEFAULT 0, statement VARCHAR(2000) DEFAULT '',
        signed_name VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // The register of threats and what was decided about each.
    $pdo->exec("CREATE TABLE IF NOT EXISTS impartiality_threats (
        id $pk, threat_kind VARCHAR(30) DEFAULT 'SELF_INTEREST',
        person_kind VARCHAR(20) DEFAULT 'INSPECTOR', person_id INT NULL,
        partner_id INT NULL, office_id INT NULL,
        description VARCHAR(2000) DEFAULT '', raised_by VARCHAR(150) DEFAULT '',
        raised_on VARCHAR(20) DEFAULT '',
        status VARCHAR(20) DEFAULT 'OPEN', safeguards VARCHAR(2000) DEFAULT '',
        decided_by VARCHAR(150) DEFAULT '', decided_on VARCHAR(20) DEFAULT '',
        review_on VARCHAR(20) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // The per-deputation declaration, kept on the job because that is the
    // record an assessor pulls when they pick one inspection at random.
    ensure_column('jobs', 'impartiality_ok', 'INT DEFAULT 0');
    ensure_column('jobs', 'impartiality_note', "VARCHAR(1000) DEFAULT ''");
    ensure_column('jobs', 'impartiality_by', "VARCHAR(150) DEFAULT ''");
    ensure_column('jobs', 'impartiality_at', "VARCHAR(30) DEFAULT ''");
}

function imp_missing_table(Throwable $e) {
    $m = $e->getMessage();
    return stripos($m, 'no such table') !== false || stripos($m, "doesn't exist") !== false;
}

// ---- Which kind of body is this? -------------------------------------------
function ib_type() {
    $t = (string)setting_get('inspection_body_type', 'A');
    return isset(IB_TYPES[$t]) ? $t : 'A';
}
function ib_type_label() { return IB_TYPES[ib_type()]; }

// ---- Declarations -----------------------------------------------------------
function imp_declarations($personId, $kind = 'INSPECTOR') {
    try {
        return ops_all("SELECT * FROM impartiality_declarations WHERE person_kind=? AND person_id=?
                        ORDER BY declared_on DESC, id DESC", [$kind, (int)$personId]);
    } catch (Throwable $e) { if (imp_missing_table($e)) return []; throw $e; }
}

// The one in force today, or null. A declaration with no end date is treated as
// running for a year — a statement made once and never renewed is not a
// current statement, and saying so out loud is cheaper than arguing with an
// assessor about it.
function imp_current_declaration($personId, $kind = 'INSPECTOR', $onDate = null) {
    $onDate = $onDate ?: date('Y-m-d');
    foreach (imp_declarations($personId, $kind) as $d) {
        if ($d['declared_on'] !== '' && $d['declared_on'] > $onDate) continue;
        $to = $d['valid_to'] !== '' ? $d['valid_to']
            : ($d['declared_on'] !== '' ? date('Y-m-d', strtotime($d['declared_on'] . ' +1 year')) : '');
        if ($to !== '' && $to < $onDate) continue;
        return $d;
    }
    return null;
}

function imp_declaration_due($personId, $kind = 'INSPECTOR') {
    return imp_current_declaration($personId, $kind) === null;
}

// ---- The threat register ----------------------------------------------------
function imp_threats($filter = []) {
    $w = ['1=1']; $a = [];
    if (!empty($filter['person_id'])) { $w[] = 'person_id = ?';  $a[] = (int)$filter['person_id']; }
    if (!empty($filter['partner_id'])) { $w[] = 'partner_id = ?'; $a[] = (int)$filter['partner_id']; }
    if (!empty($filter['status']))     { $w[] = 'status = ?';     $a[] = $filter['status']; }
    try {
        return ops_all("SELECT * FROM impartiality_threats WHERE " . implode(' AND ', $w)
                     . " ORDER BY (status='OPEN') DESC, raised_on DESC, id DESC", $a);
    } catch (Throwable $e) { if (imp_missing_table($e)) return []; throw $e; }
}

// Threats that stand in the way of this person doing work for this party.
// A threat with no partner named is about the person generally and counts
// against every client — which is the point of raising it that way.
function imp_blocking_for($inspectorId, $partnerIds = []) {
    $partnerIds = array_values(array_filter(array_map('intval', (array)$partnerIds)));
    $out = [];
    foreach (imp_threats(['person_id' => (int)$inspectorId]) as $t) {
        if (!in_array($t['status'], IMP_BLOCKING, true)) continue;
        $p = (int)$t['partner_id'];
        if ($p === 0 || in_array($p, $partnerIds, true)) $out[] = $t;
    }
    return $out;
}

// The sentence shown when somebody must not take this work. '' when they may.
function imp_block($inspectorId, $partnerIds = [], $onDate = null) {
    if (!$inspectorId) return '';
    $bad = imp_blocking_for($inspectorId, $partnerIds);
    if (!$bad) return '';
    $who = ops_val("SELECT name FROM inspectors WHERE id=?", [(int)$inspectorId]) ?: 'This ' . Tl('engineer');
    $bits = [];
    foreach ($bad as $t) {
        $kind = IMP_THREATS[$t['threat_kind']] ?? $t['threat_kind'];
        $bits[] = strtok($kind, '—') . '(' . strtolower(IMP_STATUS[$t['status']] ?? $t['status']) . ')';
    }
    return $who . ' has a declared threat to impartiality that has not been cleared for this work: '
         . implode('; ', $bits) . '. ISO/IEC 17020 §4.1 — the body must resolve it, and record how, '
         . 'before the work is done.';
}

// Is the per-deputation declaration outstanding? Only asked for once somebody
// is actually on the job — there is nothing to declare about an empty slot.
function imp_job_declaration_due($job) {
    if (empty($job['inspector_id'])) return false;
    return empty($job['impartiality_ok']);
}

// ---- How ready this body is on §4.1 ----------------------------------------
function imp_readiness() {
    $people = ops_all("SELECT id, name FROM inspectors WHERE status='ACTIVE'");
    $due = 0;
    foreach ($people as $p) if (imp_declaration_due($p['id'])) $due++;
    $open = count(imp_threats(['status' => 'OPEN']));
    $unacc = count(imp_threats(['status' => 'UNACCEPTABLE']));
    return [
        'people' => count($people), 'declaration_due' => $due,
        'open' => $open, 'unacceptable' => $unacc,
        'total' => count(imp_threats()),
        'type' => ib_type(), 'type_label' => ib_type_label(),
    ];
}

function imp_can_decide() { return is_admin_level() || is_master(); }

// ---- Screens ---------------------------------------------------------------
function ops_impartiality($route, $method) {
    ops_require(can('mod.competence.view') || is_master_of('competence') || (is_admin_level() && licence_enabled('operations')),
                'Only a manager can open the impartiality register.');

    if ($route === 'impartiality') {
        $people = ops_all("SELECT id, name, emp_code FROM inspectors WHERE status='ACTIVE' ORDER BY name");
        $rows = [];
        foreach ($people as $p)
            $rows[] = ['p' => $p, 'decl' => imp_current_declaration($p['id']),
                       'due' => imp_declaration_due($p['id']),
                       'threats' => count(imp_threats(['person_id' => $p['id']]))];
        view('ops/impartiality', [
            'ready' => imp_readiness(), 'people' => $rows,
            'threats' => imp_threats(), 'clients' => clients_list(),
            'inspectors' => $people, 'canDecide' => imp_can_decide(),
        ]);
        return true;
    }
    ops_require(imp_can_decide(), 'Only a manager can record or decide a threat to impartiality.');

    if ($route === 'imp-type' && $method === 'POST') {
        $t = (string)($_POST['ib_type'] ?? 'A');
        setting_set('inspection_body_type', isset(IB_TYPES[$t]) ? $t : 'A');
        flash('Recorded as ' . (IB_TYPES[$t] ?? IB_TYPES['A']) . '.');
        redirect('/impartiality');
    }
    if ($route === 'imp-declare' && $method === 'POST') {
        $pid = (int)($_POST['person_id'] ?? 0);
        $on  = (string)($_POST['declared_on'] ?? date('Y-m-d'));
        $has = !empty($_POST['has_conflicts']) ? 1 : 0;
        $stmt = trim((string)($_POST['statement'] ?? ''));
        if ($has && $stmt === '') {
            flash('A declaration that there ARE conflicts has to say what they are.', 'error');
            redirect('/impartiality?i=' . $pid);
        }
        db()->prepare("INSERT INTO impartiality_declarations (person_kind,person_id,declared_on,valid_to,has_conflicts,statement,signed_name,created_at)
                       VALUES ('INSPECTOR',?,?,?,?,?,?,?)")
            ->execute([$pid, $on, (string)($_POST['valid_to'] ?? date('Y-m-d', strtotime($on . ' +1 year'))),
                       $has, substr($stmt, 0, 2000), user_name(current_user()), date('c')]);
        flash($has ? 'Declaration recorded. Raise each conflict on the register below so it can be decided.'
                   : 'Declaration recorded.');
        redirect('/impartiality?i=' . $pid);
    }
    if ($route === 'imp-threat-add' && $method === 'POST') {
        $kind = (string)($_POST['threat_kind'] ?? 'SELF_INTEREST');
        if (!isset(IMP_THREATS[$kind])) $kind = 'SELF_INTEREST';
        $desc = trim((string)($_POST['description'] ?? ''));
        if ($desc === '') { flash('Describe the threat — a register entry with no description proves nothing.', 'error'); redirect('/impartiality'); }
        db()->prepare("INSERT INTO impartiality_threats (threat_kind,person_kind,person_id,partner_id,office_id,description,raised_by,raised_on,status,created_at)
                       VALUES (?,'INSPECTOR',?,?,?,?,?,?, 'OPEN',?)")
            ->execute([$kind, ($_POST['person_id'] ?? '') !== '' ? (int)$_POST['person_id'] : null,
                       ($_POST['partner_id'] ?? '') !== '' ? (int)$_POST['partner_id'] : null,
                       ($_POST['office_id'] ?? '') !== '' ? (int)$_POST['office_id'] : null,
                       substr($desc, 0, 2000), user_name(current_user()),
                       (string)($_POST['raised_on'] ?? date('Y-m-d')), date('c')]);
        flash('Threat recorded as OPEN. Until it is decided, work it touches is refused.');
        redirect('/impartiality');
    }
    if ($route === 'imp-threat-decide' && $method === 'POST') {
        $t = ops_one("SELECT * FROM impartiality_threats WHERE id=?", [(int)($_POST['id'] ?? 0)]);
        $to = (string)($_POST['status'] ?? '');
        $safe = trim((string)($_POST['safeguards'] ?? ''));
        if (!$t || !isset(IMP_STATUS[$to])) redirect('/impartiality');
        // Clearing a threat without saying how is exactly the nonconformity the
        // clause exists to prevent, so it is refused.
        if (in_array($to, ['MITIGATED', 'ACCEPTED'], true) && $safe === '') {
            flash('Say what safeguards were put in place, or on what grounds the residual risk is acceptable. '
                . 'Clearing a threat without recording how is the finding §4.1 exists to prevent.', 'error');
            redirect('/impartiality');
        }
        db()->prepare("UPDATE impartiality_threats SET status=?, safeguards=?, decided_by=?, decided_on=?, review_on=? WHERE id=?")
            ->execute([$to, substr($safe, 0, 2000), user_name(current_user()), date('Y-m-d'),
                       (string)($_POST['review_on'] ?? ''), $t['id']]);
        flash('Recorded: ' . IMP_STATUS[$to] . '.');
        redirect('/impartiality');
    }
    redirect('/impartiality');
    return true;
}
