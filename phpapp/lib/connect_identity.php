<?php
// ============================================================================
//  CONNECT — Unified professional identity  (K0+, additive, NON-DESTRUCTIVE)
//
//  THE PROBLEM (from the integration audit): the same human can exist twice —
//  once as an internal `inspectors` row (the person the whole Operations / PDSO /
//  expense / voucher chain uses) and once as a marketplace `cx_professionals`
//  row (the self-registered pool). They were only ever bridged per-application
//  (cx_applications.inspector_id + applicant_professional_id). There is no stored
//  link on the person records, so the same person is two unlinked identities.
//
//  THE FIX — a RELATIONSHIP, never a merge (per the master brief §3, §48):
//  one small link ledger, cx_identity_link, records "this professional row and
//  this inspector row are the same person". Nothing is renamed, moved, merged or
//  deleted; both records keep working exactly as before. Everything else asks the
//  resolver "who is this, really?" and can then treat the two as ONE master
//  identity with many roles (marketplace pro · internal inspector · bench · …).
//
//  Reuses: act_log() for provenance (§42). Adds NO new permission — linking is a
//  talent-pool action gated by the existing coordinator/manager right.
// ============================================================================

function connect_identity_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS cx_identity_link (
        id $pk,
        professional_id INT DEFAULT 0,          -- cx_professionals.id
        inspector_id    INT DEFAULT 0,          -- inspectors.id
        party_id        INT DEFAULT 0,          -- optional business_partners.id (external)
        method   VARCHAR(20) DEFAULT 'manual',  -- manual | email_match | mobile_match | self_claim
        status   VARCHAR(12) DEFAULT 'LINKED',  -- LINKED | UNLINKED
        note     VARCHAR(200) DEFAULT '',
        linked_by   VARCHAR(120) DEFAULT '',
        linked_at   VARCHAR(30) DEFAULT '',
        unlinked_at VARCHAR(30) DEFAULT '')");
    foreach (["CREATE INDEX ix_cx_idlink_pro ON cx_identity_link (professional_id)",
              "CREATE INDEX ix_cx_idlink_insp ON cx_identity_link (inspector_id)"] as $ix) { try { db()->exec($ix); } catch (Throwable $e) {} }
    // P11 — the same person can also be a recruitment candidate. A candidate↔professional
    // link is the same "one person across identities" concept, so it reuses this ledger
    // (additive column; a candidate-axis row carries inspector_id=0).
    if (function_exists('ensure_column')) ensure_column('cx_identity_link', 'candidate_id', 'INT DEFAULT 0');
    try { db()->exec("CREATE INDEX ix_cx_idlink_cand ON cx_identity_link (candidate_id)"); } catch (Throwable $e) {}

    // ---- Phase 6 · Batch 1 — uniqueness the DATABASE enforces (R3 · I28) ----
    //
    // Three NULL-able "live key" columns, one per relationship that must be
    // unique, each carrying its value only while the row is LINKED and NULL
    // otherwise. A plain UNIQUE index over a column full of NULLs is the same
    // thing on SQLite and on MySQL — both allow unlimited NULLs — so one
    // statement gives identical semantics on both engines with no driver
    // branch, and unlinking releases the slot simply by nulling the key.
    // History is therefore never constrained: a pair may be linked and unlinked
    // as often as the business needs, and every one of those rows is kept.
    foreach (array_keys(CXID_UNIQUE) as $c)
        if (function_exists('ensure_column')) ensure_column('cx_identity_link', $c, 'INT NULL');
    connect_identity_backfill_keys();
    connect_identity_build_unique();
}

// Which relationships are unique, and the index that enforces each. There is
// deliberately NO key on the candidate axis's professional_id: one person may
// legitimately hold several candidate records (locked invariant I3), and each
// may point at the same professional. Constraining it would make I3
// unenforceable — the one place here where the obvious constraint is wrong.
const CXID_UNIQUE = [
    'uq_pro_insp'  => 'ux_cx_idlink_pro_insp',  // U1 · one live inspector-axis row per professional
    'uq_insp'      => 'ux_cx_idlink_insp',      // U2 · one live inspector-axis row per inspector
    'uq_cand'      => 'ux_cx_idlink_cand',      // U3 · one live candidate-axis row per candidate
    'uq_cand_insp' => 'ux_cx_idlink_cand_insp', // U4 · one live CONVERSION row per candidate (Batch 2)
];

/**
 * The live-key values for one relationship — the single definition of "this slot
 * is occupied". The INSERT, the UNLINK and the back-fill all ask this, so they
 * cannot drift apart; three copies of this rule is exactly how a subtle
 * uniqueness bug would get in.
 */
function connect_identity_keys($proId, $inspId, $candId, $live = true) {
    $none = ['uq_pro_insp' => null, 'uq_insp' => null, 'uq_cand' => null, 'uq_cand_insp' => null];
    if (!$live) return $none;
    if ((int)$candId > 0 && (int)$inspId > 0)                      // CONVERSION axis (Batch 2)
        return ['uq_pro_insp' => null, 'uq_insp' => null, 'uq_cand' => null, 'uq_cand_insp' => (int)$candId];
    if ((int)$candId > 0)                                          // candidate↔professional axis
        return ['uq_pro_insp' => null, 'uq_insp' => null, 'uq_cand' => (int)$candId, 'uq_cand_insp' => null];
    return ['uq_pro_insp' => ((int)$proId ?: null),                // professional↔inspector axis
            'uq_insp'     => ((int)$inspId ?: null), 'uq_cand' => null, 'uq_cand_insp' => null];
}

/** Stamp the live keys onto rows that pre-date them. Additive; no business field is touched. */
function connect_identity_backfill_keys() {
    $live = "status='LINKED'";
    $insp = "COALESCE(candidate_id,0)=0";
    //  Batch 2 — the candidate axis splits in two. A row carrying BOTH a
    //  candidate and an inspector is a CONVERSION, and must not take the
    //  candidate↔professional slot: one person may legitimately hold both
    //  (invariant I1), and sharing one key would forbid it.
    $candPro  = "COALESCE(candidate_id,0)>0 AND COALESCE(inspector_id,0)=0";
    $candInsp = "COALESCE(candidate_id,0)>0 AND COALESCE(inspector_id,0)>0";
    foreach ([
        'uq_pro_insp'  => "CASE WHEN $live AND $insp AND COALESCE(professional_id,0)>0 THEN professional_id ELSE NULL END",
        'uq_insp'      => "CASE WHEN $live AND $insp AND COALESCE(inspector_id,0)>0    THEN inspector_id    ELSE NULL END",
        'uq_cand'      => "CASE WHEN $live AND $candPro  THEN candidate_id ELSE NULL END",
        'uq_cand_insp' => "CASE WHEN $live AND $candInsp THEN candidate_id ELSE NULL END",
    ] as $col => $expr) {
        try { db()->exec("UPDATE cx_identity_link SET $col = $expr"); } catch (Throwable $e) {}
    }
}

/** The live values that appear on more than one row — the ones a UNIQUE index would reject. */
function cxid_dup_rows($col) {
    try {
        return ops_all("SELECT $col AS v, COUNT(*) n FROM cx_identity_link
                        WHERE $col IS NOT NULL GROUP BY $col HAVING COUNT(*) > 1 ORDER BY $col") ?: [];
    } catch (Throwable $e) { return []; }
}

/**
 * Build each unique index — but only over data that is already clean.
 *
 * A database that already carries two live rows for one pair cannot have the
 * index built over it, and failing the boot for data we found there would be
 * worse than the gap. So the affected index is SKIPPED and the duplicates are
 * surfaced for a person to resolve (connect_identity_duplicates()), after which
 * a later boot builds it. Nothing is merged, unlinked or deleted to make the
 * constraint fit: choosing which of two live links survives is a judgement
 * about who a person is, and that is never made silently.
 *
 * Each index is decided on its own, so duplicates on one axis do not leave the
 * other axis unprotected.
 */
function connect_identity_build_unique() {
    foreach (CXID_UNIQUE as $col => $name) {
        if (cxid_dup_rows($col)) continue;
        try { db()->exec("CREATE UNIQUE INDEX $name ON cx_identity_link ($col)"); } catch (Throwable $e) {}
    }
}

/** Live duplicate relationships this database still carries, for the health report. */
function connect_identity_duplicates() {
    connect_identity_migrate();
    $out = [];
    foreach (CXID_UNIQUE as $col => $name)
        foreach (cxid_dup_rows($col) as $r)
            $out[] = ['key' => $col, 'value' => (int)$r['v'], 'rows' => (int)$r['n'], 'index' => $name];
    return $out;
}

// ---- Axis, scope and authority (Phase 6 · Batch 1) --------------------------

/** The refusal shown whenever the actor was not allowed to get this far. */
const CXID_DENY = 'You cannot change identity relationships here.';

/**
 * ONE gate for every writer of this ledger (R25 · I26 · I27).
 *
 * It is asked by the ledger functions themselves, not by the routes, so a
 * caller cannot inherit a weaker gate than the one this ledger requires — which
 * is exactly what happened before: the marketplace console asked for Connect
 * while the recruitment route wrote the same rows asking only for Recruitment.
 * No new permission and no second entitlement engine: connect_identity_admin_can()
 * already composes the licence and the staff right.
 */
function connect_identity_guard() {
    return function_exists('connect_identity_admin_can') ? (bool)connect_identity_admin_can() : false;
}

/**
 * PER-END VISIBILITY (R15 · I16): you may not build or break a relationship out
 * of a record you are not allowed to open. This is not a new rule — it is the
 * one scope_allows() already applies to every detail page — applied to a
 * relationship endpoint.
 *
 * It deliberately says NOTHING about the relationship's own scope. Whether a
 * link between a branch-scoped inspector and a tenant-global professional
 * itself belongs to a branch is Q5/Q11, and is NOT decided here. I16 therefore
 * remains PARTIAL after this batch, by design.
 */
function connect_identity_scope_ok($kind, $id) {
    $id = (int)$id;
    if ($kind === 'inspector') {
        $r = ops_one("SELECT home_office_id, sbu FROM inspectors WHERE id=?", [$id]);
        if (!$r) return false;
        return !function_exists('scope_allows') || scope_allows($r['home_office_id'] ?? null, (string)($r['sbu'] ?? ''));
    }
    if ($kind === 'candidate') {
        //  A candidate carries a business unit and NO branch, so it is guarded by
        //  the SBU-only twin. scope_allows(null, ...) would read the missing
        //  office as Ahmedabad and refuse every branch-scoped user a record that
        //  has no branch at all.
        $r = ops_one("SELECT sbu FROM candidates WHERE id=?", [$id]);
        if (!$r) return false;
        return !function_exists('scope_sbu_allows') || scope_sbu_allows((string)($r['sbu'] ?? ''));
    }
    // A marketplace professional is TENANT-GLOBAL: it carries no branch and no
    // business unit. Filtering it by the actor's branch would be inventing the
    // decision Q5/Q11 has not made, so it is checked for existence only.
    return (int)ops_val("SELECT COUNT(*) FROM cx_professionals WHERE id=?", [$id]) > 0;
}

/** Was this exception the database refusing a duplicate, or something else entirely? */
function connect_identity_is_duplicate(Throwable $e) {
    $code = (string)$e->getCode();
    if ($code === '23000' || $code === '23505') return true;
    $m = strtolower($e->getMessage());
    return strpos($m, 'unique') !== false || strpos($m, 'duplicate') !== false;
}

/**
 * The database refused the row. That is a CORRECT outcome, not a failure: two
 * people confirmed the same person in the same instant and exactly one had to
 * win. Re-read the winner and tell the loser the truth — the same answer the
 * PHP pre-check would have given had it run a moment later.
 *
 * Anything that is not a uniqueness conflict is re-thrown. An exception is never
 * swallowed, and the INSERT is never blindly retried.
 */
function connect_identity_conflict($axis, $proId, $inspId, $candId, Throwable $e) {
    if (!connect_identity_is_duplicate($e)) throw $e;
    if ($axis === 'CANDIDATE') {
        $w = connect_identity_of_candidate($candId);
        if ($w && (int)$w['professional_id'] === (int)$proId) return [true, 'Already confirmed as the same person.', (int)$w['id']];
        return [false, 'This candidate is already linked to another professional — unlink it first.', 0];
    }
    $w = connect_identity_of_professional($proId);
    if ($w && (int)$w['inspector_id'] === (int)$inspId) return [true, 'Already linked.', (int)$w['id']];
    if ($w) return [false, 'That professional is already linked to another inspector — unlink it first.', 0];
    if (connect_identity_of_inspector($inspId)) return [false, 'That inspector is already linked to another professional — unlink it first.', 0];
    return [false, 'That identity relationship could not be recorded — please try again.', 0];
}

/**
 * An audited refusal (owner decision 3C). Only refusals that have actually
 * identified a real record are recorded: a refusal that never got as far as
 * naming one has no entity to attach to, and inventing an entity id would
 * recreate the dangling-reference defect this batch exists to remove.
 */
function connect_identity_log($entityKind, $entityId, $kind, $subject) {
    if (!function_exists('act_log') || (int)$entityId <= 0) return;
    try { act_log($entityKind, (int)$entityId, $kind, $subject, ['auto' => 0]); } catch (Throwable $e) {}
}

// ---- Resolvers — "who is this, really?" ------------------------------------

// THE TWO AXES ARE INDEPENDENT (owner decision 2).
//
// One ledger carries two different relationships: professional↔inspector (rows
// with candidate_id = 0) and candidate↔professional (rows with inspector_id = 0).
// Until Batch 1 none of these resolvers said which one it was asking about, so a
// candidate-axis row was returned as "the professional's active link" and the
// writer refused an unrelated inspector link with a message naming an inspector
// that did not exist — the people most likely to need it, recruited AND
// deployed, were exactly the ones who could not be linked.
//
// Each resolver now names its axis. Nothing is merged and no third ledger is
// introduced; the rows were always distinguishable, they were simply not being
// distinguished.
//  Batch 2 adds a THIRD axis — candidate↔inspector, the recruitment conversion.
//  The candidate predicate is narrowed accordingly: without that, a conversion
//  row would answer as a candidate↔professional link and the two would collide,
//  which is the same class of defect Batch 1 fixed between the first two axes.
const CXID_AXIS_INSPECTOR  = "COALESCE(candidate_id,0)=0";
const CXID_AXIS_CANDIDATE  = "COALESCE(candidate_id,0)>0 AND COALESCE(inspector_id,0)=0";
const CXID_AXIS_CONVERSION = "COALESCE(candidate_id,0)>0 AND COALESCE(inspector_id,0)>0";

/** The active INSPECTOR-AXIS link for a professional (→ inspector_id), or null. */
function connect_identity_of_professional($proId) {
    connect_identity_migrate();
    return ops_one("SELECT * FROM cx_identity_link
                     WHERE professional_id=? AND status='LINKED' AND " . CXID_AXIS_INSPECTOR . "
                     ORDER BY id DESC LIMIT 1", [(int)$proId]) ?: null;
}
/** The active link row for an inspector (→ professional_id), or null. */
function connect_identity_of_inspector($inspId) {
    connect_identity_migrate();
    // An inspector id is only ever present on the inspector axis, but the
    // predicate is stated anyway: this resolver must not start answering about a
    // different axis if the ledger ever grows a third one.
    return ops_one("SELECT * FROM cx_identity_link
                     WHERE inspector_id=? AND status='LINKED' AND " . CXID_AXIS_INSPECTOR . "
                     ORDER BY id DESC LIMIT 1", [(int)$inspId]) ?: null;
}

/**
 * The roles one person plays across the whole system — the payoff of the link.
 * $ref is ['professional_id'=>..] or ['inspector_id'=>..]. Returns a compact card:
 * whether they are a marketplace professional, an internal inspector, on any agency
 * bench, plus the resolved counterpart id. Read-only; safe to call widely.
 */
function connect_identity_roles(array $ref) {
    connect_identity_migrate();
    $proId = (int)($ref['professional_id'] ?? 0);
    $inspId = (int)($ref['inspector_id'] ?? 0);
    if ($proId && !$inspId) { $lk = connect_identity_of_professional($proId); if ($lk) $inspId = (int)$lk['inspector_id']; }
    if ($inspId && !$proId) { $lk = connect_identity_of_inspector($inspId); if ($lk) $proId = (int)$lk['professional_id']; }
    $name = '';
    $isPro = $proId ? (int)ops_val("SELECT COUNT(*) FROM cx_professionals WHERE id=?", [$proId]) > 0 : false;
    $isInsp = $inspId ? (int)ops_val("SELECT COUNT(*) FROM inspectors WHERE id=?", [$inspId]) > 0 : false;
    if ($isPro)  $name = (string)ops_val("SELECT name FROM cx_professionals WHERE id=?", [$proId]);
    if (!$name && $isInsp) $name = (string)ops_val("SELECT name FROM inspectors WHERE id=?", [$inspId]);
    $onBench = 0;
    try { $onBench = (int)ops_val("SELECT COUNT(*) FROM cx_bench WHERE professional_id=?", [$proId]); } catch (Throwable $e) {}
    return [
        'name'            => $name,
        'professional_id' => $proId,
        'inspector_id'    => $inspId,
        'is_professional' => $isPro,
        'is_inspector'    => $isInsp,
        'linked'          => ($proId && $inspId),
        'bench_count'     => $onBench,
    ];
}

// ---- Create / remove a link ------------------------------------------------

/**
 * Link a professional row and an inspector row as the same person. Guards:
 *  - both rows must exist;
 *  - neither may already be actively linked to a DIFFERENT counterpart
 *    (re-linking the same pair is a harmless success).
 * Records provenance in the activity spine. Returns [ok, message, id].
 */
function connect_identity_link_create($proId, $inspId, $method = 'manual', $by = '', $note = '') {
    connect_identity_migrate();
    $proId = (int)$proId; $inspId = (int)$inspId;
    //  1 ENTITLEMENT · 2 PERMISSION — asked first, and answered with one generic
    //  refusal, so that being turned away never tells the caller whether the
    //  records they named exist.
    if (!connect_identity_guard()) return [false, CXID_DENY, 0];
    if ($proId <= 0 || $inspId <= 0) return [false, 'A professional and an inspector are both required.', 0];
    //  3 TENANT — structural: db() is this tenant's own database and no identity
    //  path can reach another. Proved by tests/_p6_tenant_worker.php.
    //  4 SCOPE — the actor must be able to open both ends. Existence is folded in
    //  here deliberately: a record you cannot see and a record that is not there
    //  must be indistinguishable from the outside.
    if (!connect_identity_scope_ok('professional', $proId)) return [false, CXID_DENY, 0];
    if (!connect_identity_scope_ok('inspector', $inspId))   return [false, CXID_DENY, 0];
    //  5 STATE — axis-aware, so a candidate link cannot masquerade as this one.
    $ep = connect_identity_of_professional($proId);
    if ($ep && (int)$ep['inspector_id'] === $inspId) return [true, 'Already linked.', (int)$ep['id']];
    if ($ep) return [false, 'That professional is already linked to another inspector — unlink it first.', 0];
    if (connect_identity_of_inspector($inspId)) return [false, 'That inspector is already linked to another professional — unlink it first.', 0];
    if ($by === '' && function_exists('current_user')) { $u = current_user(); $by = (string)($u['name'] ?? $u['username'] ?? ''); }
    //  6 DUPLICATE · 7 WRITE — the checks above are the courtesy that gives a good
    //  message; U1/U2 are the protection. A second process that got past the same
    //  checks a microsecond ago is stopped here, by the database, and told the truth.
    $k = connect_identity_keys($proId, $inspId, 0, true);
    try {
        db()->prepare("INSERT INTO cx_identity_link (professional_id,inspector_id,candidate_id,method,status,note,linked_by,linked_at,uq_pro_insp,uq_insp,uq_cand)
                       VALUES (?,?,0,?,'LINKED',?,?,?,?,?,?)")
            ->execute([$proId, $inspId, substr((string)$method,0,20), substr((string)$note,0,200),
                       substr((string)$by,0,120), date('c'), $k['uq_pro_insp'], $k['uq_insp'], $k['uq_cand']]);
    } catch (Throwable $e) {
        return connect_identity_conflict('INSPECTOR', $proId, $inspId, 0, $e);
    }
    $id = (int)db()->lastInsertId();
    //  8 AUDIT — attributable: a registered entity kind and a registered activity
    //  kind, pointing at the row it describes. Before Batch 1 this passed the TABLE
    //  name, which act_log() blanked, so every identity event was stored as an
    //  untyped note with a dangling id that no timeline could show.
    $roles = connect_identity_roles(['professional_id' => $proId]);
    connect_identity_log('IDENTITY_LINK', $id, 'IDENTITY_LINKED',
        'Linked ' . ($roles['name'] ?: 'a professional') . ' (pro #' . $proId . ' ↔ inspector #' . $inspId . ')');
    return [true, 'Linked — this is now one person across the marketplace and Operations.', $id];
}

/**
 * Remove an active link. Records provenance.
 *
 * $expect states WHICH record the caller is acting on behalf of — e.g.
 * ['candidate_id' => 71] from the candidate screen. A record id posted by a
 * browser is not a passport (R24 · I25): before this, any link id in the
 * workspace could be removed from the candidate screen, including a
 * professional↔inspector link that had nothing to do with candidates.
 *
 * A link that exists but is not this record's is refused with the SAME words as
 * one that does not exist, so the refusal cannot be used to enumerate other
 * people's relationships.
 */
function connect_identity_unlink($linkId, $by = '', array $expect = []) {
    connect_identity_migrate();
    $linkId = (int)$linkId;
    //  1 ENTITLEMENT · 2 PERMISSION
    if (!connect_identity_guard()) return [false, CXID_DENY];
    $row = ops_one("SELECT * FROM cx_identity_link WHERE id=? AND status='LINKED'", [$linkId]);
    $miss = 'No active identity link for this record.';
    if (!$row) return [false, $miss];
    //  6 RELATIONSHIP OWNERSHIP — established from the business relationship, not
    //  from the fact that a row with that id happens to exist.
    foreach (['candidate_id', 'professional_id', 'inspector_id'] as $f) {
        if (!isset($expect[$f])) continue;
        if ((int)$expect[$f] !== (int)($row[$f] ?? 0)) {
            connect_identity_log('IDENTITY_LINK', $linkId, 'IDENTITY_REFUSED',
                'Unlink refused — link #' . $linkId . ' does not belong to ' . $f . ' #' . (int)$expect[$f]);
            return [false, $miss];
        }
    }
    //  4 SCOPE — both ends, per-end visibility only (Q5/Q11 stay open).
    if ((int)$row['inspector_id'] > 0 && !connect_identity_scope_ok('inspector', (int)$row['inspector_id'])) {
        connect_identity_log('IDENTITY_LINK', $linkId, 'IDENTITY_REFUSED', 'Unlink refused — inspector out of scope');
        return [false, CXID_DENY];
    }
    if ((int)($row['candidate_id'] ?? 0) > 0 && !connect_identity_scope_ok('candidate', (int)$row['candidate_id'])) {
        connect_identity_log('IDENTITY_LINK', $linkId, 'IDENTITY_REFUSED', 'Unlink refused — candidate out of scope');
        return [false, CXID_DENY];
    }
    if ($by === '' && function_exists('current_user')) { $u = current_user(); $by = (string)($u['name'] ?? $u['username'] ?? ''); }
    //  7 WRITE — one statement. Nulling the live keys is what RELEASES the slot,
    //  so the pair can be linked again later while every historical row is kept.
    db()->prepare("UPDATE cx_identity_link SET status='UNLINKED', unlinked_at=?,
                        uq_pro_insp=NULL, uq_insp=NULL, uq_cand=NULL WHERE id=?")
        ->execute([date('c'), $linkId]);
    //  8 AUDIT
    $other = (int)($row['candidate_id'] ?? 0) > 0 ? 'candidate #' . (int)$row['candidate_id'] : 'inspector #' . (int)$row['inspector_id'];
    connect_identity_log('IDENTITY_LINK', $linkId, 'IDENTITY_UNLINKED',
        'Unlinked pro #' . (int)$row['professional_id'] . ' ↔ ' . $other);
    return [true, 'Unlinked.'];
}

// ---- Candidate ↔ professional (P11) — the same person across the two pools -----

/** The active candidate↔professional link row for a candidate (→ professional_id), or null. */
function connect_identity_of_candidate($candId) {
    connect_identity_migrate();
    return ops_one("SELECT * FROM cx_identity_link
                     WHERE candidate_id=? AND status='LINKED' AND " . CXID_AXIS_CANDIDATE . "
                     ORDER BY id DESC LIMIT 1", [(int)$candId]) ?: null;
}

/**
 * Confirm that a recruitment candidate and a marketplace professional are the same
 * person, recording it as an additive, reversible link. It NEVER merges or deletes
 * either record — each pool keeps its own row; this only stamps the fact that they
 * are one person, so it can be unlinked with no data loss (P11's safe first step).
 */
function connect_identity_candidate_link_create($candId, $proId, $method = 'manual', $by = '', $note = '') {
    connect_identity_migrate();
    $candId = (int)$candId; $proId = (int)$proId;
    //  1 ENTITLEMENT · 2 PERMISSION — the SAME gate the marketplace console asks.
    //  One ledger, one rule: before Batch 1 this route wrote these rows while
    //  asking only whether Recruitment was bought.
    if (!connect_identity_guard()) return [false, CXID_DENY, 0];
    if ($candId <= 0 || $proId <= 0) return [false, 'A candidate and a professional are both required.', 0];
    //  3 TENANT (structural) · 4 SCOPE — both ends, existence folded in.
    if (!connect_identity_scope_ok('candidate', $candId))       return [false, CXID_DENY, 0];
    if (!connect_identity_scope_ok('professional', $proId))     return [false, CXID_DENY, 0];
    //  5 STATE — candidate axis only. Note there is deliberately NO check that the
    //  professional is free: one person may hold several candidate records
    //  (invariant I3), and each may point at the same professional.
    $ex = connect_identity_of_candidate($candId);
    if ($ex && (int)$ex['professional_id'] === $proId) return [true, 'Already confirmed as the same person.', (int)$ex['id']];
    if ($ex) return [false, 'This candidate is already linked to another professional — unlink it first.', 0];
    if ($by === '' && function_exists('current_user')) { $u = current_user(); $by = (string)($u['name'] ?? $u['username'] ?? ''); }
    //  6 DUPLICATE · 7 WRITE — U3 is the protection.
    $k = connect_identity_keys($proId, 0, $candId, true);
    try {
        db()->prepare("INSERT INTO cx_identity_link (professional_id,inspector_id,candidate_id,method,status,note,linked_by,linked_at,uq_pro_insp,uq_insp,uq_cand)
                       VALUES (?,0,?,?,'LINKED',?,?,?,?,?,?)")
            ->execute([$proId, $candId, substr((string)$method,0,20), substr((string)$note,0,200),
                       substr((string)$by,0,120), date('c'), $k['uq_pro_insp'], $k['uq_insp'], $k['uq_cand']]);
    } catch (Throwable $e) {
        return connect_identity_conflict('CANDIDATE', $proId, 0, $candId, $e);
    }
    $id = (int)db()->lastInsertId();
    //  8 AUDIT
    connect_identity_log('IDENTITY_LINK', $id, 'IDENTITY_LINKED',
        'Linked candidate #' . $candId . ' ↔ professional #' . $proId . ' (same person)');
    return [true, 'Confirmed — this candidate and marketplace professional are recorded as one person (nothing merged; you can unlink any time).', $id];
}

// ---- The CONVERSION axis (Phase 6 · Batch 2) -------------------------------
//
//  "This application became this team member." It is the same "one person across
//  identities" concept as the other two axes and reuses the same ledger — a
//  relationship, never a merge. `candidates.inspector_id` keeps working exactly
//  as before for every existing reader; this row is ADDITIVE, and it is what
//  brings the conversion under U4, the resolver, the audit and the unlink that
//  Batch 1 built.

/** The active CONVERSION link for a candidate (→ inspector_id), or null. */
function connect_identity_of_candidate_inspector($candId) {
    connect_identity_migrate();
    return ops_one("SELECT * FROM cx_identity_link
                     WHERE candidate_id=? AND status='LINKED' AND " . CXID_AXIS_CONVERSION . "
                     ORDER BY id DESC LIMIT 1", [(int)$candId]) ?: null;
}

/** Every candidate whose conversion points at this team member (a re-hire may give several). */
function connect_identity_candidates_of_inspector($inspId) {
    connect_identity_migrate();
    return ops_all("SELECT * FROM cx_identity_link
                     WHERE inspector_id=? AND status='LINKED' AND " . CXID_AXIS_CONVERSION . "
                     ORDER BY id DESC", [(int)$inspId]) ?: [];
}

/**
 * Record that a candidate became a team member.
 *
 * Called from INSIDE the recruitment conversion's transaction, which is why it
 * does not open one of its own. It deliberately does NOT ask
 * connect_identity_admin_can(): owner decision BD2 — recruitment must remain
 * usable without the marketplace, so the CALLER decides whether the ledger row
 * is written at all, and says which state it produced. What this function will
 * not do is write a row it is not entitled to: the caller asks
 * connect_identity_conversion_allowed() first.
 *
 * U4 is the protection. The PHP pre-check is the courtesy that gives the good
 * message.
 */
function connect_identity_conversion_link_create($candId, $inspId, $method = 'conversion', $by = '', $note = '') {
    connect_identity_migrate();
    $candId = (int)$candId; $inspId = (int)$inspId;
    if ($candId <= 0 || $inspId <= 0) return [false, 'A candidate and a team member are both required.', 0];
    $ex = connect_identity_of_candidate_inspector($candId);
    if ($ex && (int)$ex['inspector_id'] === $inspId) return [true, 'Already recorded.', (int)$ex['id']];
    if ($ex) return [false, 'This application is already recorded against another team member.', 0];
    if ($by === '' && function_exists('current_user')) { $u = current_user(); $by = (string)($u['name'] ?? $u['username'] ?? ''); }
    $k = connect_identity_keys(0, $inspId, $candId, true);
    try {
        db()->prepare("INSERT INTO cx_identity_link (professional_id,inspector_id,candidate_id,method,status,note,linked_by,linked_at,uq_pro_insp,uq_insp,uq_cand,uq_cand_insp)
                       VALUES (0,?,?,?,'LINKED',?,?,?,?,?,?,?)")
            ->execute([$inspId, $candId, substr((string)$method, 0, 20), substr((string)$note, 0, 200),
                       substr((string)$by, 0, 120), date('c'), $k['uq_pro_insp'], $k['uq_insp'], $k['uq_cand'], $k['uq_cand_insp']]);
    } catch (Throwable $e) {
        if (!connect_identity_is_duplicate($e)) throw $e;
        $w = connect_identity_of_candidate_inspector($candId);
        if ($w && (int)$w['inspector_id'] === $inspId) return [true, 'Already recorded.', (int)$w['id']];
        return [false, 'This application is already recorded against another team member.', 0];
    }
    $id = (int)db()->lastInsertId();
    connect_identity_log('IDENTITY_LINK', $id, 'IDENTITY_LINKED',
        'Recorded candidate #' . $candId . ' ↔ team member #' . $inspId . ' (recruitment conversion)');
    return [true, 'Recorded.', $id];
}

/** May this workspace record identity relationships right now? (BD2 — never a hiring blocker.) */
function connect_identity_conversion_allowed() {
    return function_exists('connect_identity_admin_can') ? (bool)connect_identity_admin_can() : false;
}

// ---- Suggestions — the same person, not yet linked -------------------------

/**
 * Propose links from a STRONG deterministic signal: a professional and an
 * inspector that share an e-mail (primary) or a mobile number (secondary), and
 * are not already linked to anyone. Never links automatically — a person confirms.
 */
function connect_identity_suggestions($limit = 100) {
    connect_identity_migrate();
    $out = []; $seen = [];
    $push = function ($pro, $insp, $basis) use (&$out, &$seen) {
        $key = (int)$pro['id'] . ':' . (int)$insp['id'];
        if (isset($seen[$key])) return; $seen[$key] = true;
        if (connect_identity_of_professional((int)$pro['id']) || connect_identity_of_inspector((int)$insp['id'])) return;
        $out[] = ['professional_id' => (int)$pro['id'], 'pro_name' => (string)$pro['name'], 'pro_email' => (string)($pro['email'] ?? ''),
                  'inspector_id' => (int)$insp['id'], 'insp_name' => (string)$insp['name'], 'insp_email' => (string)($insp['email'] ?? ''),
                  'emp_code' => (string)($insp['emp_code'] ?? ''), 'basis' => $basis];
    };
    // Primary: exact e-mail match.
    try {
        $rows = ops_all(
            "SELECT p.id p_id, p.name p_name, p.email p_email, i.id i_id, i.name i_name, i.email i_email, i.emp_code
               FROM cx_professionals p JOIN inspectors i ON LOWER(p.email)=LOWER(i.email)
              WHERE p.is_active=1 AND COALESCE(i.status,'ACTIVE')='ACTIVE' AND COALESCE(p.email,'')<>''") ?: [];
        foreach ($rows as $r) $push(['id'=>$r['p_id'],'name'=>$r['p_name'],'email'=>$r['p_email']],
                                     ['id'=>$r['i_id'],'name'=>$r['i_name'],'email'=>$r['i_email'],'emp_code'=>$r['emp_code']], 'email');
    } catch (Throwable $e) {}
    // Secondary: exact mobile match (digits only).
    try {
        $rows = ops_all(
            "SELECT p.id p_id, p.name p_name, p.email p_email, i.id i_id, i.name i_name, i.email i_email, i.emp_code
               FROM cx_professionals p JOIN inspectors i
                 ON REPLACE(REPLACE(p.mobile,' ',''),'-','') = REPLACE(REPLACE(i.mobile,' ',''),'-','')
              WHERE p.is_active=1 AND COALESCE(i.status,'ACTIVE')='ACTIVE'
                AND LENGTH(REPLACE(REPLACE(COALESCE(p.mobile,''),' ',''),'-','')) >= 8") ?: [];
        foreach ($rows as $r) $push(['id'=>$r['p_id'],'name'=>$r['p_name'],'email'=>$r['p_email']],
                                     ['id'=>$r['i_id'],'name'=>$r['i_name'],'email'=>$r['i_email'],'emp_code'=>$r['emp_code']], 'mobile');
    } catch (Throwable $e) {}
    return array_slice($out, 0, max(1, (int)$limit));
}

/** All active links, joined to names, for the admin console. */
function connect_identity_links($limit = 300) {
    connect_identity_migrate();
    try {
        return ops_all(
            "SELECT l.*, p.name pro_name, p.email pro_email, i.name insp_name, i.emp_code
               FROM cx_identity_link l
               LEFT JOIN cx_professionals p ON p.id=l.professional_id
               LEFT JOIN inspectors i ON i.id=l.inspector_id
              WHERE l.status='LINKED' ORDER BY l.id DESC LIMIT " . max(1,(int)$limit)) ?: [];
    } catch (Throwable $e) { return []; }
}

// ---- Matcher dedupe — one person, once -------------------------------------

/**
 * Collapse matcher rows so a person linked as BOTH an internal inspector and a
 * self-registered professional appears once — keeping the higher-scoring row and
 * annotating it so the desk still knows the person wears both hats. Given rows
 * each carrying 'kind' ('inspector'|'professional') and 'id'. Order preserved
 * (the kept row stays where the stronger of the two was).
 */
function connect_identity_dedupe_rows(array $rows) {
    connect_identity_migrate();
    // Build inspector→professional map for the ids present.
    $byPair = [];
    foreach (connect_identity_links(1000) as $l) $byPair[(int)$l['inspector_id']] = (int)$l['professional_id'];
    if (!$byPair) return $rows;
    // Index rows for quick score lookup.
    $out = []; $dropId = [];   // "professional:{id}" or "inspector:{id}" keys to drop
    // First pass: decide, for each linked pair present twice, which to drop.
    $proRow = []; $inspRow = [];
    foreach ($rows as $i => $r) {
        if (($r['kind'] ?? '') === 'professional') $proRow[(int)$r['id']] = $i;
        elseif (($r['kind'] ?? '') === 'inspector') $inspRow[(int)$r['id']] = $i;
    }
    foreach ($byPair as $inspId => $proId) {
        if (!isset($inspRow[$inspId]) || !isset($proRow[$proId])) continue;   // not both present
        $ri = $rows[$inspRow[$inspId]]; $rp = $rows[$proRow[$proId]];
        // Keep the higher score; annotate it; drop the other.
        if ((int)($ri['score'] ?? 0) >= (int)($rp['score'] ?? 0)) { $dropId['professional:' . $proId] = true; $keep = 'inspector:' . $inspId; }
        else { $dropId['inspector:' . $inspId] = true; $keep = 'professional:' . $proId; }
        $GLOBALS['__cx_id_also'][$keep] = true;
    }
    foreach ($rows as $r) {
        $key = ($r['kind'] ?? '') . ':' . (int)($r['id'] ?? 0);
        if (!empty($dropId[$key])) continue;
        if (!empty($GLOBALS['__cx_id_also'][$key])) {
            $r['also_identity'] = ($r['kind'] === 'inspector') ? 'Also a marketplace professional' : 'Also on internal staff';
            $r['reasons'] = array_merge($r['reasons'] ?? [], ['✓ One verified person — staff & marketplace']);
        }
        $out[] = $r;
    }
    unset($GLOBALS['__cx_id_also']);
    return $out;
}

// ---- Access gate (reuses the talent-pool right; no new permission) ---------

function connect_identity_admin_can() {
    if (function_exists('connect_enabled') && !connect_enabled()) return false;
    if (function_exists('connect_market_can')) return (bool)connect_market_can();
    if (function_exists('is_master') && is_master()) return true;
    return false;
}

/** The staff identity console — confirm suggested links, link/unlink by hand. */
function ops_connect_identity($method) {
    ops_require(connect_identity_admin_can(), 'Managing professional identity is for coordinators, managers and admins.');
    connect_identity_migrate();
    if ($method === 'POST') {
        $act = (string)($_POST['action'] ?? '');
        if ($act === 'link') {
            [$ok, $msg] = connect_identity_link_create((int)($_POST['professional_id'] ?? 0), (int)($_POST['inspector_id'] ?? 0), (string)($_POST['method'] ?? 'manual'));
            flash($msg, $ok ? 'success' : 'error');
        } elseif ($act === 'unlink') {
            //  The console lists professional↔inspector links, so that is the axis
            //  it may act on. Stating the expectation means a posted id belonging
            //  to a candidate link cannot be removed from here.
            $lid = (int)($_POST['id'] ?? 0);
            $row = $lid > 0 ? ops_one("SELECT professional_id FROM cx_identity_link WHERE id=? AND status='LINKED' AND " . CXID_AXIS_INSPECTOR, [$lid]) : null;
            [$ok, $msg] = $row
                ? connect_identity_unlink($lid, '', ['professional_id' => (int)$row['professional_id']])
                : [false, 'No active identity link for this record.'];
            flash($msg, $ok ? 'success' : 'error');
        }
        redirect('/connect-identity');
    }
    view('ops/connect_identity', [
        'links'       => connect_identity_links(),
        'suggestions' => connect_identity_suggestions(),
    ]);
    return true;
}

// ============================================================================
//  PHASE 6 · BATCH 2 — CONTRADICTORY IDENTITY STATES: DETECTION ONLY.
//
//  The Batch 2 audit found sixteen states an identity relationship can be in and
//  eleven of them undetectable. This reports them. It REPAIRS NOTHING — deciding
//  which of two records is the human is exactly what this programme never does
//  silently, and the one approved repair (a person group the old linker split)
//  lives in the recruitment layer that owns person groups.
//
//  Every finding states what is wrong, which records are involved, why it was
//  detected, whether a repair is safe, and whether a person must look at it.
//  Read-only, and cheap enough for the system-status page.
// ============================================================================
function identity_state_findings($limit = 200) {
    connect_identity_migrate();
    $out = [];
    $add = function ($kind, $what, $records, $why, $safe, $human) use (&$out) {
        $out[] = ['kind' => $kind, 'what' => $what, 'records' => $records,
                  'why' => $why, 'safe_to_repair' => $safe, 'needs_human' => $human];
    };
    $all = function ($sql, $a = []) { try { return ops_all($sql, $a) ?: []; } catch (Throwable $e) { return []; } };
    $lim = max(1, (int)$limit);

    // 1 — a candidate points at a team member that is not there.
    foreach ($all("SELECT c.id, c.inspector_id FROM candidates c
                    WHERE COALESCE(c.inspector_id,0)>0
                      AND NOT EXISTS (SELECT 1 FROM inspectors i WHERE i.id=c.inspector_id) LIMIT $lim") as $r)
        $add('CANDIDATE_INSPECTOR_MISSING',
             'An application is recorded against a team member that no longer exists.',
             ['candidate' => (int)$r['id'], 'inspector' => (int)$r['inspector_id']],
             'candidates.inspector_id names a row that is not in inspectors.',
             false, true);

    // 2 — a login points at a team member that is not there.
    foreach ($all("SELECT u.id, u.inspector_id FROM users u
                    WHERE COALESCE(u.inspector_id,0)>0
                      AND NOT EXISTS (SELECT 1 FROM inspectors i WHERE i.id=u.inspector_id) LIMIT $lim") as $r)
        $add('USER_INSPECTOR_MISSING',
             'A login is linked to a team member that no longer exists.',
             ['user' => (int)$r['id'], 'inspector' => (int)$r['inspector_id']],
             'users.inspector_id names a row that is not in inspectors.',
             false, true);

    // 3 — converted, but the identity relationship was never recorded. This is
    //     owner decision BD2's STATE B, and it is a QUESTION, not a fault: a
    //     workspace without the marketplace add-on produces it legitimately.
    foreach ($all("SELECT c.id, c.inspector_id FROM candidates c
                    WHERE COALESCE(c.inspector_id,0)>0
                      AND EXISTS (SELECT 1 FROM inspectors i WHERE i.id=c.inspector_id)
                      AND NOT EXISTS (SELECT 1 FROM cx_identity_link l
                                       WHERE l.candidate_id=c.id AND l.status='LINKED'
                                         AND COALESCE(l.inspector_id,0)>0) LIMIT $lim") as $r)
        $add('CONVERTED_NO_LEDGER',
             'An application was converted to a team member without a cross-system identity record.',
             ['candidate' => (int)$r['id'], 'inspector' => (int)$r['inspector_id']],
             'Expected while the marketplace add-on is inactive. It can be recorded later through the identity screen.',
             true, false);

    // 4 — the ledger and the column disagree about which team member it is.
    foreach ($all("SELECT c.id, c.inspector_id, l.inspector_id led FROM candidates c
                    JOIN cx_identity_link l ON l.candidate_id=c.id AND l.status='LINKED' AND COALESCE(l.inspector_id,0)>0
                    WHERE COALESCE(c.inspector_id,0)>0 AND c.inspector_id <> l.inspector_id LIMIT $lim") as $r)
        $add('CONVERSION_DISAGREES',
             'An application names one team member and the identity record names another.',
             ['candidate' => (int)$r['id'], 'inspector' => (int)$r['inspector_id'], 'ledger_inspector' => (int)$r['led']],
             'Two recorded relationships contradict each other; which one is the person is a business question.',
             false, true);

    // 5 — one team member claimed by two active logins.
    foreach ($all("SELECT inspector_id, COUNT(*) n FROM users
                    WHERE COALESCE(inspector_id,0)>0 AND is_active=1
                    GROUP BY inspector_id HAVING COUNT(*)>1 LIMIT $lim") as $r)
        $add('INSPECTOR_TWO_LOGINS',
             'One team member is linked to more than one active login.',
             ['inspector' => (int)$r['inspector_id'], 'logins' => (int)$r['n']],
             'Each login would see that person\'s jobs and schedule.',
             false, true);

    // 6 — a person reachable as two different team members: one through the
    //     conversion, another through the marketplace professional.
    foreach ($all("SELECT cv.candidate_id, cv.inspector_id conv_insp, pi.inspector_id pro_insp
                     FROM cx_identity_link cv
                     JOIN cx_identity_link cp ON cp.candidate_id=cv.candidate_id AND cp.status='LINKED'
                                             AND COALESCE(cp.inspector_id,0)=0 AND COALESCE(cp.professional_id,0)>0
                     JOIN cx_identity_link pi ON pi.professional_id=cp.professional_id AND pi.status='LINKED'
                                             AND COALESCE(pi.candidate_id,0)=0 AND COALESCE(pi.inspector_id,0)>0
                    WHERE cv.status='LINKED' AND COALESCE(cv.candidate_id,0)>0 AND COALESCE(cv.inspector_id,0)>0
                      AND cv.inspector_id <> pi.inspector_id LIMIT $lim") as $r)
        $add('TWO_INSPECTORS_ONE_PERSON',
             'One person resolves to two different team members.',
             ['candidate' => (int)$r['candidate_id'], 'inspector_a' => (int)$r['conv_insp'], 'inspector_b' => (int)$r['pro_insp']],
             'Legitimate for a re-hire on different terms; a duplicate otherwise. Only a person can tell.',
             false, true);

    // ---- RB-3 — the EMPLOYEE NUMBER (owner decision 1, 2026-09-20) ---------
    //
    //  "Permanently unique and never re-issued to another person, even after the
    //   employee leaves." The database now enforces that, so nothing NEW can
    //   collide — but an install that already carried a collision cannot have the
    //   index built over it, and that is exactly the case a person must see.
    //
    //  Nothing here repairs anything. Renumbering somebody is precisely the
    //  historical ambiguity decision 1 exists to prevent: the number is already
    //  in their inspection reports, attendance, vouchers and audit records.
    if (function_exists('emp_code_collisions')) {
        foreach (emp_code_collisions($lim) as $r)
            $add('EMP_CODE_SHARED',
                 'More than one team member carries the same employee number.',
                 ['employee_number' => (string)$r['v'], 'team_members' => (int)$r['n']],
                 'An employee number identifies one person for life — it appears in reports, attendance, expenses and audit records. '
                 . 'Until this is resolved the protection cannot be switched on for this workspace. '
                 . 'Give the newer record a fresh number; never re-issue the old one.',
                 false, true);
    }
    //  And the protection's own state, so "it is installed" is never assumed.
    if (function_exists('schema_guards_not_ok')) {
        foreach (schema_guards_not_ok() as $g) {
            if ((string)($g['table_name'] ?? '') !== 'inspectors') continue;
            $add('EMP_CODE_UNPROTECTED',
                 'The employee-number rule is not yet switched on for this workspace.',
                 ['state' => (string)($g['state'] ?? ''), 'detail' => (string)($g['detail'] ?? '')],
                 'Resolve the shared employee numbers above; the rule installs itself on the next start-up.',
                 false, true);
        }
    }

    // ---- RB-3 Step 3 — audit entries that could not be written -------------
    //
    //  Invariant I41 keeps the hire: a failed note never undoes completed work.
    //  Owner decision 2 removes the other half of the old behaviour — that the
    //  loss was SILENT. If a note could not be written, say so here.
    if (function_exists('setting_get')) {
        $lostN = (int)setting_get('audit_writes_lost', 0);
        if ($lostN > 0)
            $add('AUDIT_WRITES_LOST',
                 'Some acceptance entries could not be written to the activity trail.',
                 ['entries' => $lostN],
                 'The hires themselves stand — a failed note never undoes completed work. '
                 . 'But this many entries are missing from the trail, so the record is incomplete. '
                 . 'Check the server error log for the affected applications.',
                 false, true);
    }

    // ---- Phase 6 · Batch 3 — ORGANISATION states (detection only) ----------
    //
    //  Extends the report Batch 2 built rather than adding a second one. Every
    //  finding below is REPORTED and nothing is repaired: deciding that two
    //  organisations are one company is a business judgement, and a shared tax
    //  identifier is strong evidence of it but not a licence to merge.

    //  R1 — a record RETIRED BY A MERGE is left out of these two findings, and
    //  only these two. Before that, merging two duplicates — the exact remedy
    //  this dashboard asks for — did not clear the warning it raised: the pair
    //  was reported for ever, because the merge leaves the retired record's
    //  identifiers in place as evidence. A warning no action can clear teaches
    //  people to ignore the warnings next to it.
    //
    //  ONLY 'MERGED' is excluded. INACTIVE, ON_HOLD, BLACKLISTED, PROSPECT, a
    //  code a tenant invented, a blank or a NULL all still count (R2 · R5): an
    //  unrecognised status must never quietly switch a protection off, and a
    //  dormant company with two records is still two records.
    $live = PARTNER_LIVE_SQL;

    // 8 — two organisations carrying the same authoritative tax identifier.
    foreach (['gstin', 'pan', 'tan'] as $idf) {
        foreach ($all("SELECT $idf AS v, COUNT(*) n FROM business_partners
                        WHERE COALESCE($idf,'')<>'' AND $live GROUP BY $idf HAVING COUNT(*)>1 LIMIT $lim") as $r) {
            $ids = array_column($all("SELECT id FROM business_partners WHERE $idf=? AND $live LIMIT 20", [$r['v']]) ?: [], 'id');
            $add('PARTNER_DUPLICATE_TAXID',
                 'Two or more organisations carry the same ' . strtoupper($idf) . '.',
                 ['identifier' => strtoupper($idf), 'organisations' => array_map('intval', $ids)],
                 'A tax identifier names one legal entity, so these are very likely one organisation with two records.',
                 false, true);
        }
    }

    // 9 — organisations sharing only a name. Evidence, not proof: two real
    //     companies share a name often enough that this is a question.
    foreach ($all("SELECT LOWER(TRIM(legal_name)) AS v, COUNT(*) n FROM business_partners
                    WHERE COALESCE(legal_name,'')<>'' AND $live GROUP BY LOWER(TRIM(legal_name)) HAVING COUNT(*)>1 LIMIT $lim") as $r) {
        $ids = array_column($all("SELECT id FROM business_partners WHERE LOWER(TRIM(legal_name))=? AND $live LIMIT 20", [$r['v']]) ?: [], 'id');
        $add('PARTNER_POSSIBLE_DUPLICATE_NAME',
             'Two or more organisations share a name.',
             ['organisations' => array_map('intval', $ids)],
             'A name is not an identifier. These may be separate legal entities in one group, or one organisation entered twice.',
             false, true);
    }

    // 10 — several marketplace organisations pointing at one party.
    foreach ($all("SELECT party_id AS v, COUNT(*) n FROM cx_organisations
                    WHERE COALESCE(party_id,0)>0 GROUP BY party_id HAVING COUNT(*)>1 LIMIT $lim") as $r) {
        $add('MARKETPLACE_MULTIPLE_FOR_PARTY',
             'One organisation has more than one marketplace representation.',
             ['organisation' => (int)$r['v'], 'representations' => (int)$r['n']],
             'May be legitimate where the audiences genuinely differ; more often it is the same company registered twice.',
             false, true);
    }

    // 11 — a marketplace organisation with no link to the spine at all.
    foreach ($all("SELECT id, name FROM cx_organisations WHERE COALESCE(party_id,0)=0 LIMIT $lim") as $r)
        $add('MARKETPLACE_UNMAPPED',
             'A marketplace organisation is not connected to the organisation register.',
             ['marketplace_organisation' => (int)$r['id'], 'name' => (string)$r['name']],
             'Expected for an application that has not been approved yet.',
             true, false);

    // 12 — a marketplace organisation naming a party that is not there.
    foreach ($all("SELECT o.id, o.party_id FROM cx_organisations o
                    WHERE COALESCE(o.party_id,0)>0
                      AND NOT EXISTS (SELECT 1 FROM business_partners b WHERE b.id=o.party_id) LIMIT $lim") as $r)
        $add('MARKETPLACE_PARTY_MISSING',
             'A marketplace organisation names an organisation that no longer exists.',
             ['marketplace_organisation' => (int)$r['id'], 'organisation' => (int)$r['party_id']],
             'A dangling reference.', false, true);

    // 13 — an agency that MIGHT be an organisation already on the register.
    //      A question for a person; the mapping is never inferred (Q19).
    foreach ($all("SELECT a.id, a.name, b.id AS pid FROM agencies a
                    JOIN business_partners b ON COALESCE(a.gstin,'')<>'' AND UPPER(b.gstin)=UPPER(a.gstin)
                   WHERE COALESCE(a.party_id,0)=0 LIMIT $lim") as $r)
        $add('AGENCY_POSSIBLE_ORGANISATION',
             'An agency contract carries the same GSTIN as an organisation on the register.',
             ['agency' => (int)$r['id'], 'organisation' => (int)$r['pid']],
             'An agency is a CONTRACT and an organisation is a LEGAL IDENTITY, so this is a suggestion to connect them, never a duplicate.',
             false, true);

    // 14 — duplicate contacts, and the ambiguous primary.
    //  Q25 — the same rule the sign-in door and the database use, so a contact
    //  saved as " Ann@x.com " is found as the duplicate of "ann@x.com" that it is.
    foreach ($all("SELECT partner_id, LOWER(TRIM(email)) AS v, COUNT(*) n FROM partner_contacts
                    WHERE TRIM(COALESCE(email,''))<>'' GROUP BY partner_id, LOWER(TRIM(email)) HAVING COUNT(*)>1 LIMIT $lim") as $r)
        $add('CONTACT_DUPLICATE',
             'One organisation holds the same contact address more than once.',
             ['organisation' => (int)$r['partner_id'], 'email' => (string)$r['v'], 'rows' => (int)$r['n']],
             'The same person entered twice on one organisation.', false, true);
    foreach ($all("SELECT partner_id, COUNT(*) n FROM partner_contacts
                    WHERE COALESCE(is_primary,0)=1 GROUP BY partner_id HAVING COUNT(*)>1 LIMIT $lim") as $r)
        $add('CONTACT_MULTIPLE_PRIMARY',
             'One organisation has more than one primary contact.',
             ['organisation' => (int)$r['partner_id'], 'primaries' => (int)$r['n']],
             'Historical data from before the one-primary rule. "The primary contact" is ambiguous until it is resolved.',
             false, true);

    // 15 — portal accounts: duplicates within the boundary, and orphans.
    if (function_exists('portal_acct_duplicates'))
        foreach (portal_acct_duplicates() as $d)
            $add('ACCOUNT_DUPLICATE_ACTIVE',
                 'Two active portal accounts share one address in the same account list.',
                 ['table' => $d['table'], 'email' => $d['email'], 'rows' => $d['rows']],
                 'Sign-in resolves one of them, so the person may land in the wrong organisation. The database protection for this list is held back until it is resolved.',
                 false, true);
    foreach ($all("SELECT u.id, u.partner_id FROM client_users u
                    WHERE COALESCE(u.partner_id,0)>0
                      AND NOT EXISTS (SELECT 1 FROM business_partners b WHERE b.id=u.partner_id) LIMIT $lim") as $r)
        $add('ACCOUNT_ORGANISATION_MISSING',
             'A portal account belongs to an organisation that no longer exists.',
             ['account' => (int)$r['id'], 'organisation' => (int)$r['partner_id']],
             'A dangling reference.', false, true);

    // 7 — live duplicate relationships the uniqueness protection could not build over.
    foreach (connect_identity_duplicates() as $d)
        $add('DUPLICATE_RELATIONSHIP',
             'Two live identity relationships describe the same thing.',
             ['key' => $d['key'], 'value' => $d['value'], 'rows' => $d['rows']],
             'The database protection for this relationship is held back until it is resolved.',
             false, true);

    return $out;
}
