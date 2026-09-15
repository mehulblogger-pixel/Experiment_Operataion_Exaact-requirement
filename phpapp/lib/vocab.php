<?php
// ============================================================================
//  EXAACT — CONTROLLED VOCABULARY (canonical identity + approved terms)
//
//  The principle: EXAACT standardises IDENTITY, not terminology.
//
//  A customer may call a department whatever is correct for their organisation.
//  Once a term is approved, EXAACT knows exactly which canonical value it means.
//  If EXAACT does not know a term, the customer can approve it or create a new
//  value — the vocabulary is never a closed dictionary.
//
//  This EXTENDS the existing lookup engine rather than competing with it:
//    lookup_types   — the vocabulary  (department, designation, skill, …)
//    lookup_values  — the CANONICAL values. id is the stable identity.
//    lookup_terms   — every term that resolves to a canonical value  (NEW)
//
//  A canonical value's own code and label are themselves registered as terms,
//  so matching has exactly one index to consult.
//
//  Deliberately generic: Department is the first consumer, but nothing here is
//  department-specific. Designation, Skill, Discipline and the rest can adopt it
//  without change.
//
//  TENANCY: EXAACT gives each customer its own database, so a vocabulary is
//  already private to its tenant — there is no shared term table to protect.
//  The one real risk is a CACHE outliving the database it was read from, which
//  is why every cache here is keyed on db_epoch(). See M2.
// ============================================================================

const VOCAB_TERM_TYPES = [
    'CANONICAL'     => 'Canonical name',
    'SYNONYM'       => 'Synonym',
    'ABBREVIATION'  => 'Abbreviation',
    'ACRONYM'       => 'Acronym',
    'LEGACY'        => 'Legacy term',
    'CUSTOMER_TERM' => 'Customer term',
    'TRANSLATION'   => 'Translation',
    'ALIAS'         => 'Alias',
    'SEARCH_TERM'   => 'Search term',
];
const VOCAB_SOURCES = ['SYSTEM' => 'EXAACT standard', 'CUSTOMER' => 'Customer', 'IMPORT' => 'Imported', 'LEGACY' => 'Legacy data', 'SUGGESTED' => 'Suggested'];
const VOCAB_STATUSES = ['APPROVED' => 'Approved', 'PENDING' => 'Awaiting approval', 'REJECTED' => 'Rejected'];

function vocab_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    if (!function_exists('ensure_column')) return;
    $pk = (function_exists('db_driver') && db_driver() === 'mysql')
        ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS lookup_terms (
            id $pk,
            type_id INT NOT NULL,
            value_id INT NULL,
            term VARCHAR(200) DEFAULT '',
            term_norm VARCHAR(200) DEFAULT '',
            term_compact VARCHAR(200) DEFAULT '',
            term_type VARCHAR(20) DEFAULT 'SYNONYM',
            lang VARCHAR(10) DEFAULT 'en',
            source VARCHAR(20) DEFAULT 'CUSTOMER',
            status VARCHAR(20) DEFAULT 'APPROVED',
            suggested_value_id INT NULL,
            match_note VARCHAR(200) DEFAULT '',
            original_input VARCHAR(200) DEFAULT '',
            approved_by VARCHAR(150) DEFAULT '',
            approved_at VARCHAR(30) DEFAULT '',
            created_by VARCHAR(150) DEFAULT '',
            created_at VARCHAR(30) DEFAULT '')");
        db()->exec("CREATE INDEX IF NOT EXISTS idx_lkterm_lookup ON lookup_terms (type_id, term_norm)");
        db()->exec("CREATE INDEX IF NOT EXISTS idx_lkterm_value ON lookup_terms (value_id)");
        db()->exec("CREATE INDEX IF NOT EXISTS idx_lkterm_compact ON lookup_terms (type_id, term_compact)");
    } catch (Throwable $e) {}

    // Canonical attributes the lookup engine did not carry. All additive, all
    // generic — any vocabulary may use them, not only Department.
    foreach ([
        ['display_name',   "VARCHAR(160) DEFAULT ''"],  // customer terminology; canonical identity is unchanged
        ['description',    "VARCHAR(400) DEFAULT ''"],
        ['attr_type',      "VARCHAR(30)  DEFAULT ''"],  // e.g. department type
        ['attr_owner_id',  'INT NULL'],                 // e.g. department head
        ['effective_from', "VARCHAR(20)  DEFAULT ''"],
        ['effective_to',   "VARCHAR(20)  DEFAULT ''"],
        ['external_ref',   "VARCHAR(120) DEFAULT ''"],
        ['source',         "VARCHAR(20)  DEFAULT ''"],
        ['updated_by',     "VARCHAR(150) DEFAULT ''"],
        ['updated_at',     "VARCHAR(30)  DEFAULT ''"],
    ] as $c) { try { ensure_column('lookup_values', $c[0], $c[1]); } catch (Throwable $e) {} }
}

// ---- Normalisation ---------------------------------------------------------
// Case, spacing and punctuation must not create a second department. The
// ORIGINAL input is never destroyed — it is stored alongside, for audit.
//   "H.R."  "h r"  " HR "  →  "hr"
function vocab_norm($s) {
    $s = strtolower(trim((string) $s));
    $s = str_replace(['&', '+'], ' and ', $s);
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}

// The same string with every separator removed, so an abbreviation written with
// full stops or spaces reaches the one without them: "H.R." and "H R" and "HR"
// all compact to "hr". Matching consults both forms; only the spaced form is
// shown to people.
function vocab_compact($s) { return str_replace(' ', '', vocab_norm($s)); }

function vocab_now() { return function_exists('now_iso') ? now_iso() : date('c'); }
function vocab_who() {
    if (!function_exists('current_user')) return '';
    $u = current_user(); if (!$u) return '';
    return function_exists('user_name') ? (string) user_name($u) : (string) ($u['username'] ?? '');
}

function vocab_type_id($typeKey) {
    $t = function_exists('lk_type') ? lk_type($typeKey) : null;
    return $t ? (int) $t['id'] : 0;
}

// ---- Canonical values ------------------------------------------------------
// What a value should DISPLAY as: the customer's own term where they set one,
// otherwise the canonical label. Identity never changes with the wording.
function vocab_display($row) {
    $d = trim((string) ($row['display_name'] ?? ''));
    return $d !== '' ? $d : trim((string) ($row['label'] ?? ''));
}

function vocab_values($typeKey, $activeOnly = true) {
    vocab_migrate();
    $tid = vocab_type_id($typeKey); if (!$tid) return [];
    try {
        $w = $activeOnly ? ' AND active=1' : '';
        return ops_all("SELECT * FROM lookup_values WHERE type_id=?$w ORDER BY sort_order, label", [$tid]);
    } catch (Throwable $e) { return []; }
}

// Always null for "no such value" — never PDO's false — so a caller may compare
// with === null without it quietly depending on which path failed.
function vocab_value($valueId) {
    vocab_migrate();
    try { $r = ops_one("SELECT * FROM lookup_values WHERE id=?", [(int) $valueId]); }
    catch (Throwable $e) { return null; }
    return $r ?: null;
}

// ---- Terms -----------------------------------------------------------------
function vocab_terms($typeKey, $valueId = 0, $status = 'APPROVED') {
    vocab_migrate();
    $tid = vocab_type_id($typeKey); if (!$tid) return [];
    $sql = "SELECT * FROM lookup_terms WHERE type_id=?"; $a = [$tid];
    if ($valueId > 0) { $sql .= " AND value_id=?"; $a[] = (int) $valueId; }
    if ($status !== '') { $sql .= " AND status=?"; $a[] = $status; }
    try { return ops_all($sql . " ORDER BY term_type, term", $a); } catch (Throwable $e) { return []; }
}

// The approved term, if any, that a normalised string already resolves to.
function vocab_term_find($typeKey, $input, $lang = 'en') {
    vocab_migrate();
    $tid = vocab_type_id($typeKey); if (!$tid) return null;
    $n = vocab_norm($input); if ($n === '') return null;
    try {
        return ops_one("SELECT * FROM lookup_terms WHERE type_id=? AND (term_norm=? OR term_compact=?) AND lang=? AND status='APPROVED'
                        ORDER BY CASE WHEN term_norm=? THEN 0 ELSE 1 END, CASE term_type WHEN 'CANONICAL' THEN 0 ELSE 1 END, id",
                       [$tid, $n, vocab_compact($input), $lang, $n]);
    } catch (Throwable $e) { return null; }
}

// Add a term. Refuses a mapping that would make one term mean two things —
// §21: "HR is already approved as a term for Human Resources."
// Returns [ok(bool), message(string), id(int)].
function vocab_term_add($typeKey, $valueId, $term, $termType = 'SYNONYM', $status = 'APPROVED', $source = 'CUSTOMER', $lang = 'en', $originalInput = '') {
    vocab_migrate();
    $tid = vocab_type_id($typeKey);
    if (!$tid) return [false, 'That vocabulary does not exist.', 0];
    $term = trim((string) $term);
    if ($term === '') return [false, 'Enter a term.', 0];
    $n = vocab_norm($term);
    if ($n === '') return [false, 'That term has no letters or numbers in it.', 0];

    $valueId = (int) $valueId;
    if ($status === 'APPROVED' && $valueId <= 0) return [false, 'An approved term must point to a value.', 0];
    if ($valueId > 0) {
        $v = vocab_value($valueId);
        if (!$v || (int) $v['type_id'] !== $tid) return [false, 'That value does not belong to this vocabulary.', 0];
    }

    $clash = vocab_term_find($typeKey, $term, $lang);
    if ($clash) {
        if ((int) $clash['value_id'] === $valueId) return [true, 'Already approved.', (int) $clash['id']];
        $other = vocab_value((int) $clash['value_id']);
        return [false, '“' . $term . '” is already approved as a term for '
            . ($other ? vocab_display($other) : 'another value') . '.', 0];
    }
    $now = vocab_now(); $who = vocab_who();
    db()->prepare("INSERT INTO lookup_terms (type_id,value_id,term,term_norm,term_compact,term_type,lang,source,status,original_input,approved_by,approved_at,created_by,created_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$tid, $valueId ?: null, $term, $n, vocab_compact($term), $termType, $lang, $source, $status,
                   (string) $originalInput, $status === 'APPROVED' ? $who : '', $status === 'APPROVED' ? $now : '', $who, $now]);
    return [true, 'Term added.', (int) db()->lastInsertId()];
}

// A value's own code and label are terms too, so matching consults one index.
function vocab_register_canonical($typeKey, $valueId) {
    $v = vocab_value($valueId); if (!$v) return;
    foreach ([(string) $v['label'], (string) $v['code'], (string) ($v['display_name'] ?? '')] as $i => $t) {
        if (trim($t) === '') continue;
        vocab_term_add($typeKey, $valueId, $t, $i === 1 ? 'ABBREVIATION' : 'CANONICAL', 'APPROVED', 'SYSTEM');
    }
}

function vocab_term_delete($id) {
    vocab_migrate();
    try { db()->prepare("DELETE FROM lookup_terms WHERE id=?")->execute([(int) $id]); } catch (Throwable $e) {}
}

// Approve a pending term onto a canonical value (§18). Audited.
function vocab_term_approve($id, $valueId = 0) {
    vocab_migrate();
    $t = ops_one("SELECT * FROM lookup_terms WHERE id=?", [(int) $id]);
    if (!$t) return [false, 'That term no longer exists.'];
    $valueId = (int) ($valueId ?: $t['suggested_value_id'] ?: $t['value_id']);
    if ($valueId <= 0) return [false, 'Choose which value this term means.'];
    $tk = ops_val("SELECT type_key FROM lookup_types WHERE id=?", [(int) $t['type_id']]);
    $clash = vocab_term_find((string) $tk, (string) $t['term'], (string) $t['lang']);
    if ($clash && (int) $clash['value_id'] !== $valueId) {
        $other = vocab_value((int) $clash['value_id']);
        return [false, '“' . $t['term'] . '” is already approved as a term for '
            . ($other ? vocab_display($other) : 'another value') . '.'];
    }
    db()->prepare("UPDATE lookup_terms SET value_id=?, status='APPROVED', approved_by=?, approved_at=? WHERE id=?")
        ->execute([$valueId, vocab_who(), vocab_now(), (int) $id]);
    return [true, 'Term approved.'];
}

function vocab_term_reject($id) {
    vocab_migrate();
    db()->prepare("UPDATE lookup_terms SET status='REJECTED', approved_by=?, approved_at=? WHERE id=?")
        ->execute([vocab_who(), vocab_now(), (int) $id]);
    return [true, 'Term rejected.'];
}

// ---- Matching (§15) --------------------------------------------------------
// Priority is fixed and deterministic. An approved mapping ALWAYS wins; a
// similarity score can only ever produce a SUGGESTION for a human to approve.
// Nothing here creates a value, and nothing here merges two values.
//
// Returns:
//   ['level'=>1..6, 'value'=>row|null, 'suggestions'=>[rows], 'via'=>term|'', 'reason'=>text]
//
//   1 EXACT       the input is, character for character, an approved term
//   2 NORMALISED  it matches an approved term once case/punctuation is ignored
//   3 SYNONYM     it matches an approved synonym, alias or legacy term
//   4 SUGGESTED   a strong deterministic candidate — needs a human decision
//   5 WEAK        a weaker candidate — needs a human decision
//   6 UNKNOWN     nothing matched; the customer may create a new value
function vocab_match($typeKey, $input, $lang = 'en') {
    vocab_migrate();
    $out = ['level' => 6, 'value' => null, 'suggestions' => [], 'via' => '', 'reason' => ''];
    $raw = trim((string) $input);
    if ($raw === '') { $out['reason'] = 'Nothing entered.'; return $out; }
    $tid = vocab_type_id($typeKey);
    if (!$tid) { $out['reason'] = 'That vocabulary does not exist.'; return $out; }
    $n = vocab_norm($raw);
    if ($n === '') { $out['reason'] = 'That value has no letters or numbers in it.'; return $out; }

    // Levels 1–3: one index, consulted once.
    try {
        $hits = ops_all("SELECT * FROM lookup_terms WHERE type_id=? AND (term_norm=? OR term_compact=?) AND lang=? AND status='APPROVED'
                         ORDER BY CASE WHEN term_norm=? THEN 0 ELSE 1 END, CASE term_type WHEN 'CANONICAL' THEN 0 ELSE 1 END, id",
                        [$tid, $n, vocab_compact($raw), $lang, $n]);
    } catch (Throwable $e) { $hits = []; }
    if ($hits) {
        $hit = $hits[0];
        foreach ($hits as $h) if ((string) $h['term'] === $raw) { $hit = $h; break; }   // prefer a character-exact term
        $out['value'] = vocab_value((int) $hit['value_id']);
        $out['via']   = (string) $hit['term'];
        if ((string) $hit['term'] === $raw) {
            $out['level'] = 1; $out['reason'] = 'Exact match on an approved term.';
        } elseif (in_array($hit['term_type'], ['SYNONYM', 'ALIAS', 'LEGACY', 'CUSTOMER_TERM', 'ACRONYM', 'TRANSLATION'], true)) {
            $out['level'] = 3; $out['reason'] = 'Approved ' . strtolower(VOCAB_TERM_TYPES[$hit['term_type']] ?? 'term') . ' for this value.';
        } else {
            $out['level'] = 2; $out['reason'] = 'Matches an approved term, ignoring case and punctuation.';
        }
        return $out;
    }

    // Levels 4–5: deterministic similarity only. §45 — no AI, and a suggestion
    // NEVER resolves on its own. Ambiguity is a question for a person.
    $cands = [];
    foreach (vocab_terms($typeKey, 0, 'APPROVED') as $t) {
        $tn = (string) $t['term_norm']; if ($tn === '') continue;
        $score = 0;
        if (str_starts_with($tn, $n) || str_starts_with($n, $tn))       $score = 90;
        elseif (str_contains($tn, $n) || str_contains($n, $tn))          $score = 80;
        else {
            $len = max(strlen($tn), strlen($n));
            if ($len > 0) {
                $d = levenshtein($n, $tn);
                $sim = (int) round((1 - $d / $len) * 100);
                if ($sim >= 70) $score = $sim;
            }
        }
        if ($score <= 0 || !$t['value_id']) continue;
        $vid = (int) $t['value_id'];
        if (!isset($cands[$vid]) || $cands[$vid]['score'] < $score)
            $cands[$vid] = ['score' => $score, 'term' => (string) $t['term'], 'value_id' => $vid];
    }
    if ($cands) {
        usort($cands, fn($a, $b) => $b['score'] <=> $a['score']);
        foreach (array_slice($cands, 0, 5) as $c) {
            $v = vocab_value($c['value_id']); if (!$v) continue;
            $out['suggestions'][] = ['value' => $v, 'score' => $c['score'], 'term' => $c['term']];
        }
    }
    if ($out['suggestions']) {
        $top = $out['suggestions'][0];
        $out['level']  = $top['score'] >= 85 ? 4 : 5;
        $out['reason'] = 'Did you mean ' . vocab_display($top['value']) . '? This needs to be confirmed before it is used.';
    } else {
        $out['reason'] = 'No matching value. You can create a new one.';
    }
    return $out;
}

// Resolve to a canonical value ONLY where an approved mapping exists.
// A suggestion is never treated as a resolution. Returns the value row or null.
function vocab_resolve($typeKey, $input, $lang = 'en') {
    $m = vocab_match($typeKey, $input, $lang);
    return $m['level'] <= 3 ? $m['value'] : null;
}

// Record an unrecognised input for a human to decide on later (§17), carrying
// the best suggestion. Never creates a value and never resolves anything.
function vocab_term_suggest($typeKey, $input, $lang = 'en') {
    $m = vocab_match($typeKey, $input, $lang);
    if ($m['level'] <= 3) return [false, 'Already an approved term.', 0];
    $tid = vocab_type_id($typeKey); if (!$tid) return [false, 'That vocabulary does not exist.', 0];
    $n = vocab_norm($input);
    $dup = ops_one("SELECT id FROM lookup_terms WHERE type_id=? AND term_norm=? AND lang=? AND status='PENDING'", [$tid, $n, $lang]);
    if ($dup) return [true, 'Already awaiting approval.', (int) $dup['id']];
    $sugg = $m['suggestions'][0] ?? null;
    $now = vocab_now(); $who = vocab_who();
    db()->prepare("INSERT INTO lookup_terms (type_id,value_id,term,term_norm,term_compact,term_type,lang,source,status,suggested_value_id,match_note,original_input,created_by,created_at)
                   VALUES (?,NULL,?,?,?,'SYNONYM',?,'SUGGESTED','PENDING',?,?,?,?,?)")
        ->execute([$tid, trim((string) $input), $n, vocab_compact($input), $lang,
                   $sugg ? (int) $sugg['value']['id'] : null,
                   $sugg ? ('Suggested: ' . vocab_display($sugg['value']) . ' (' . $sugg['score'] . '%)') : 'No suggestion',
                   trim((string) $input), $who, $now]);
    return [true, 'Recorded for approval.', (int) db()->lastInsertId()];
}

// Before creating a new value (§32), show what already looks like it.
function vocab_duplicate_check($typeKey, $name, $code = '') {
    $found = [];
    $m = vocab_match($typeKey, $name);
    if ($m['value']) $found[] = ['value' => $m['value'], 'why' => $m['reason'], 'exact' => true];
    foreach ($m['suggestions'] as $s)
        $found[] = ['value' => $s['value'], 'why' => 'Similar to the approved term “' . $s['term'] . '”', 'exact' => false];
    $code = trim((string) $code);
    if ($code !== '') {
        $tid = vocab_type_id($typeKey);
        $c = $tid ? ops_one("SELECT * FROM lookup_values WHERE type_id=? AND UPPER(code)=?", [$tid, strtoupper($code)]) : null;
        if ($c) array_unshift($found, ['value' => $c, 'why' => 'That code is already in use.', 'exact' => true]);
    }
    return $found;
}
