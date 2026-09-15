<?php
// ============================================================================
//  PHASE 2 · M3 — CONTROLLED VOCABULARY & CANONICAL DEPARTMENT
//
//  The principle under test: EXAACT standardises IDENTITY, not terminology.
//  A customer may call a department whatever is right for them; once a term is
//  approved, EXAACT knows exactly which canonical department it means; and if it
//  does not know a term, it says so rather than guessing.
//
//  The three rules that must never bend:
//    1. An approved mapping always wins; a similarity score only ever suggests.
//    2. One term cannot mean two departments.
//    3. Nothing is merged, renamed or deleted by guesswork.
// ============================================================================

t_section('Milestone 3 — controlled vocabulary & canonical department');

$pdo = db();
vocab_migrate();
dept_vocab_seed();
$TID = vocab_type_id('department');
$made = [];                                    // every row this file creates
$mk = function ($label, $code, $extra = []) use (&$made) {
    [$ok, $msg, $id] = dept_save(0, array_merge(['label' => $label, 'code' => $code], $extra), true);
    if ($ok) $made[] = $id;
    return [$ok, $msg, $id];
};

// ---------------------------------------------------------------------------
//  1. Foundation — extended, not duplicated.
// ---------------------------------------------------------------------------
t_ok($TID > 0, 'the department vocabulary exists');
t_ok(t_table_exists('lookup_terms'), 'the approved-term layer exists');
t_ok(!t_table_exists('departments'), 'no competing departments table was created');
foreach (['display_name', 'description', 'attr_type', 'attr_owner_id', 'effective_from', 'effective_to', 'external_ref', 'source'] as $c)
    t_ok(in_array($c, t_columns('lookup_values'), true), "lookup_values carries $c (additive)");
t_ok(in_array('parent_value_id', t_columns('lookup_values'), true), 'hierarchy reuses the column that already existed');

// ---------------------------------------------------------------------------
//  2. Normalisation (§14) — case, spacing and punctuation never make a second
//     department, and the original input is never destroyed.
// ---------------------------------------------------------------------------
t_eq(vocab_norm('  Human   Resources  '), 'human resources', 'case and repeated spaces normalise');
t_eq(vocab_norm('H.R.'), 'h r', 'punctuation becomes a separator');
t_eq(vocab_compact('H.R.'), 'hr', '…and compacts to the form an abbreviation is written in');
t_eq(vocab_compact('H R'), vocab_compact('H.R.'), '"H R" and "H.R." compact identically');
t_eq(vocab_norm('R&D'), 'r and d', 'an ampersand is spelled out so "R&D" and "R and D" agree');
t_eq(vocab_norm('!!!'), '', 'a string with no letters or digits normalises to nothing');

// ---------------------------------------------------------------------------
//  3. Canonical department — create, read, update, deactivate, reactivate.
// ---------------------------------------------------------------------------
[$ok, $msg, $hrId] = $mk('M3 Human Resources', 'M3HR', ['attr_type' => 'SUPPORT', 'description' => 'People matters']);
t_ok($ok, 'a department can be created: ' . $msg);
$hr = vocab_value($hrId);
t_eq($hr['code'] ?? null, 'M3HR', 'its code is stored');
t_eq($hr['attr_type'] ?? null, 'SUPPORT', 'its type is stored');
t_eq((int) ($hr['active'] ?? 0), 1, 'it is in use when created');

dept_set_active($hrId, 0);
t_eq((int) vocab_value($hrId)['active'], 0, 'it can be switched off');
t_ok(vocab_value($hrId) !== null, 'switching off does not delete it — history keeps its identity');
dept_set_active($hrId, 1);
t_eq((int) vocab_value($hrId)['active'], 1, 'it can be switched back on');

// ---------------------------------------------------------------------------
//  4. Identity survives a rename (§9, §10) — the point of the whole milestone.
// ---------------------------------------------------------------------------
vocab_term_add('department', $hrId, 'M3 Personnel', 'LEGACY');
dept_save($hrId, ['label' => 'M3 Human Resources', 'code' => 'M3HR', 'display_name' => 'M3 People & Culture'], true);
$after = vocab_resolve('department', 'M3 Personnel');
t_eq((int) ($after['id'] ?? 0), $hrId, 'after renaming the display wording, the identity is unchanged');
t_eq(vocab_display($after), 'M3 People & Culture', 'the customer’s own wording is what gets shown');
t_eq($after['label'], 'M3 Human Resources', 'the canonical name underneath is untouched');

// ---------------------------------------------------------------------------
//  5. Matching priority (§15). An approved mapping always wins; similarity only
//     ever suggests, and a suggestion never resolves.
// ---------------------------------------------------------------------------
t_eq(vocab_match('department', 'M3 People & Culture')['level'], 1, 'L1 — character-exact approved term');
t_eq(vocab_match('department', 'm3 people and culture')['level'], 2, 'L2 — same term ignoring case and punctuation');
t_eq(vocab_match('department', 'M3 Personnel')['level'], 1, 'L1 — an approved legacy term, exactly as written');
t_eq(vocab_match('department', 'm3 personnel')['level'], 3, 'L3 — the same legacy term, normalised');
$weak = vocab_match('department', 'M3 Personel');
t_ok($weak['level'] >= 4 && $weak['level'] <= 5, 'a misspelling only ever reaches suggestion level (got L' . $weak['level'] . ')');
t_eq($weak['value'], null, 'a suggestion does NOT resolve — a person must confirm it');
t_ok(!empty($weak['suggestions']), 'but the likely department is offered');
t_eq(vocab_match('department', 'Zzz Nothing Like This')['level'], 6, 'L6 — unknown, so a new department may be created');
t_eq(vocab_resolve('department', 'M3 Personel'), null, 'vocab_resolve() refuses anything below an approved match');

// ---------------------------------------------------------------------------
//  6. One term cannot mean two departments (§21).
// ---------------------------------------------------------------------------
[$ok2, $msg2, $finId] = $mk('M3 Finance', 'M3FIN');
t_ok($ok2, 'a second department is created to conflict against');
[$cok, $cmsg] = vocab_term_add('department', $finId, 'M3 Personnel', 'SYNONYM');
t_ok(!$cok, 'the same term cannot be pointed at a second department');
t_ok(str_contains($cmsg, 'already approved'), 'and the refusal says why: ' . $cmsg);
t_eq((int) vocab_resolve('department', 'M3 Personnel')['id'], $hrId, 'the original mapping is untouched by the attempt');
[$rok] = vocab_term_add('department', $hrId, 'M3 Personnel', 'SYNONYM');
t_ok($rok, 're-adding the SAME mapping is accepted quietly, not treated as a clash');

// ---------------------------------------------------------------------------
//  7. Hierarchy and cycles (§7).
// ---------------------------------------------------------------------------
[$ok3, $m3, $engId]  = $mk('M3 Engineering', 'M3ENG');
[$ok4, $m4, $mechId] = $mk('M3 Mechanical', 'M3MECH', ['parent_value_id' => $engId]);
t_eq((int) vocab_value($mechId)['parent_value_id'], $engId, 'a department can sit inside another');
t_ok(dept_would_loop($engId, $engId), 'a department is detected as its own parent');
t_ok(dept_would_loop($engId, $mechId), 'a department is detected inside its own child');
t_ok(!dept_would_loop($mechId, $engId), 'the legitimate direction is not blocked');
[$lok, $lmsg] = dept_save($engId, ['label' => 'M3 Engineering', 'code' => 'M3ENG', 'parent_value_id' => $mechId], true);
t_ok(!$lok, 'saving a circular hierarchy is refused: ' . $lmsg);
t_eq((int) (vocab_value($engId)['parent_value_id'] ?? 0), 0, 'and the existing hierarchy is left alone');

// ---------------------------------------------------------------------------
//  8. Duplicates (§32) — warn, but never block a legitimate structure.
// ---------------------------------------------------------------------------
[$dok, $dmsg, $did] = dept_save(0, ['label' => 'M3 Financ', 'code' => 'M3FIN2']);
t_ok(!$dok, 'a near-duplicate name is held back for the user to look at');
t_ok($did < 0, 'and the existing department it resembles is identified');
[$dok2, $dmsg2, $did2] = dept_save(0, ['label' => 'M3 Financ', 'code' => 'M3FIN2'], true);
t_ok($dok2, 'the user can confirm it really is a separate department');
if ($dok2) $made[] = $did2;
[$cok2, $cmsg2] = dept_save(0, ['label' => 'M3 Something Else', 'code' => 'M3FIN'], true);
t_ok(!$cok2, 'a duplicate CODE is always refused: ' . $cmsg2);

// ---------------------------------------------------------------------------
//  9. Negative and boundary inputs (§39).
// ---------------------------------------------------------------------------
t_ok(!dept_save(0, ['label' => '', 'code' => 'X'], true)[0], 'an empty name is refused');
t_ok(!dept_save(0, ['label' => '   ', 'code' => 'X'], true)[0], 'a name of only spaces is refused');
t_ok(!vocab_term_add('department', $hrId, '', 'SYNONYM')[0], 'an empty term is refused');
t_ok(!vocab_term_add('department', $hrId, '   ', 'SYNONYM')[0], 'a term of only spaces is refused');
t_ok(!vocab_term_add('department', $hrId, '!!!', 'SYNONYM')[0], 'a term with no letters or digits is refused');
t_ok(!vocab_term_add('department', 999999, 'M3 Ghost Term', 'SYNONYM')[0], 'a term cannot point at a department that does not exist');
t_eq(vocab_value(999999), null, 'an unknown department id returns nothing rather than throwing');
t_eq(vocab_match('department', '')['level'], 6, 'an empty search is unknown, not a crash');
$long = str_repeat('Very Long Department ', 20);
[$lok2, , $lid2] = dept_save(0, ['label' => $long, 'code' => 'M3LONG'], true);
t_ok($lok2, 'a very long name is accepted rather than silently truncated into a clash');
if ($lok2) $made[] = $lid2;
// A designation must never be matchable as a department.
t_eq(vocab_resolve('department', 'Sr. Inspector'), null, 'a DESIGNATION does not resolve as a department (§3 keeps them apart)');

// ---------------------------------------------------------------------------
// 10. Legacy hiring-department values are recorded, never merged (§16, §26).
// ---------------------------------------------------------------------------
$pending = array_column(vocab_terms('department', 0, 'PENDING'), 'term');
$pendU = array_map('strtoupper', $pending);
foreach (['QAQC', 'NDT', 'HR', 'HSE', 'FINANCE'] as $legacy)
    t_ok(in_array($legacy, $pendU, true) || vocab_resolve('department', $legacy) !== null,
         "the legacy hiring value $legacy is either recorded for a decision or already resolves — never guessed");
$qaqc = vocab_resolve('department', 'QAQC');
t_eq($qaqc, null, 'QAQC is NOT silently merged into Quality — that is a business decision');

// ---------------------------------------------------------------------------
// 11. Approving a pending term is what makes it resolve (§17, §18).
// ---------------------------------------------------------------------------
[$sok, $smsg, $sid] = vocab_term_suggest('department', 'M3 Made Up Wording');
t_ok($sid > 0, 'an unrecognised word can be recorded for approval');
t_eq(vocab_resolve('department', 'M3 Made Up Wording'), null, 'while pending it resolves to nothing');
[$aok, $amsg] = vocab_term_approve($sid, $hrId);
t_ok($aok, 'an authorised person can approve it onto a department: ' . $amsg);
t_eq((int) vocab_resolve('department', 'M3 Made Up Wording')['id'], $hrId, 'after approval it resolves');
$row = ops_one("SELECT approved_by, approved_at FROM lookup_terms WHERE id=?", [$sid]);
t_ok(($row['approved_at'] ?? '') !== '', 'the approval is stamped with when it happened (§44)');
[$sok2, , $sid2] = vocab_term_suggest('department', 'M3 Rejected Wording');
vocab_term_reject($sid2);
t_eq(vocab_resolve('department', 'M3 Rejected Wording'), null, 'a rejected term never resolves');

// ---------------------------------------------------------------------------
// 12. The three defects M3 fixed, each pinned.
// ---------------------------------------------------------------------------
// (a) Approval routing must follow the department, not the spelling.
appr_migrate();
$pdo->prepare("INSERT INTO recruit_approval_rules (code,name,entity,applies_department,applies_sbu,applies_grade,applies_position,min_amount,max_amount,active,sort,created_at)
               VALUES ('M3R','M3 rule','M3OFFER',?,'','','',0,0,1,0,?)")->execute(['M3 Human Resources', date('c')]);
$ruleId = (int) $pdo->lastInsertId();
$ctx = fn($d) => ['department' => $d, 'sbu' => '', 'grade' => '', 'position' => '', 'amount' => 1000];
t_ok(appr_match('M3OFFER', $ctx('M3 Human Resources')) !== null, 'an approval rule matches its own wording');
t_ok(appr_match('M3OFFER', $ctx('M3 Personnel')) !== null,
     'and now matches a requisition that used an APPROVED different word for the same department');
t_ok(appr_match('M3OFFER', $ctx('M3 Finance')) === null, 'but never matches a genuinely different department');
t_ok(appr_match('M3OFFER', $ctx('QAQC')) === null, 'and never matches on an UNAPPROVED term — no guessing');

// (b) A stored value must render as a name, not a raw code (the public careers page).
t_eq(dept_label('M3HR'), 'M3 People & Culture', 'a stored CODE renders as the department name');
t_eq(dept_label('M3 Personnel'), 'M3 People & Culture', 'an approved legacy word renders as the department name');
t_eq(dept_label('Totally Unknown Dept'), 'Totally Unknown Dept', 'an unknown value is shown exactly as stored — nothing is lost');
t_eq(dept_label(''), '', 'blank stays blank');

// (c) The establishment belongs to the paid module, on every route.
t_ok(function_exists('dept_hub_shows_establishment'), 'the hub asks whether it may show the establishment');
$offWas = setting_get('modules_off', '');
setting_set('modules_off', 'hr'); licence_disabled(true);
t_ok(!dept_hub_shows_establishment(), 'with People & hiring switched off, the establishment is not shown');
$hubOff = dept_hub();
$sancOff = 0; foreach ($hubOff['depts'] as $r) $sancOff += (int) $r['sanctioned'];
t_eq($sancOff, 0, 'and no sanctioned headcount reaches the page through this core route');
setting_set('modules_off', $offWas); licence_disabled(true);
t_ok(dept_hub_shows_establishment(), 'with the module on, the establishment is shown again');

// ---------------------------------------------------------------------------
// 11b. A department added the OLD way must still work.
//      Settings → Masters and the organogram importer both call lk_ensure_value()
//      and know nothing about terms. Without a self-healing reconcile such a
//      department would appear in the list but never match, and would show its
//      raw code — the very defect this milestone removes. So the reconcile runs
//      on every entry, not only on the first.
// ---------------------------------------------------------------------------
lk_ensure_value('department', 'M3LOGI', 'M3 Logistics');
$GLOBALS['__db_epoch'] = (int) ($GLOBALS['__db_epoch'] ?? 0) + 1;      // the next request
$logi = dept_of('M3 Logistics');
t_ok($logi !== null, 'a department added through Settings → Masters resolves');
t_eq(dept_label('M3LOGI'), 'M3 Logistics', '…and its code renders as its name, not as a raw code');
if ($logi) $made[] = (int) $logi['id'];

// ---------------------------------------------------------------------------
// 12b. Permissions (§35) — viewing, and changing the shape of the organisation,
//      are different bars. Editing must be refused BEFORE any edit action runs,
//      so the guard is pinned structurally as well as behaviourally.
// ---------------------------------------------------------------------------
$srcDept = preg_replace('#^\s*//.*$#m', '', file_get_contents(__DIR__ . '/../lib/deptorg.php'));
$fp = strpos($srcDept, 'function ops_departments($route, $method) {');
t_ok($fp !== false, 'the department screen has one entry point');
$head = substr($srcDept, $fp, 1200);
t_ok(strpos($head, 'is_coordinator_level()') !== false, 'viewing needs coordinator level, checked on entry');
t_ok(strpos($head, "\$mayEdit") !== false && strpos($head, 'is_admin_level') !== false,
     'changing the department list needs admin level');
$guard = strpos($head, "ops_require(\$mayEdit");
$firstEdit = strpos($head, "'dept-save'");
t_ok($guard !== false && $firstEdit !== false && $guard < $firstEdit,
     'and that guard runs BEFORE the first edit action — not per action');
// Filing a designation is the one POST a coordinator may still do; it must sit
// ahead of the admin guard, or the hub would break for coordinators.
$assign = strpos($head, "'assign'");
t_ok($assign !== false && $assign < $guard, 'filing a designation stays available to a coordinator');

$origSess = $_SESSION;
$roleCan = function ($role, $super) use ($pdo) {
    $un = 'm3perm_' . strtolower($role) . ($super ? '_s' : '');
    try { $pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser) VALUES (?,?,?,1,?)")
              ->execute([$un, 'M3', $role, $super ? 1 : 0]); } catch (Throwable $e) {}
    $_SESSION['uid'] = (int) ops_val("SELECT id FROM users WHERE username=?", [$un]);
    current_user(true); ua(true);
    $r = ['view' => (bool) is_coordinator_level(), 'edit' => (bool) is_admin_level()];
    $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$un]);
    return $r;
};
$insp = $roleCan('INSPECTOR', false);
t_ok(!$insp['view'], 'an ordinary field user cannot open the department screen');
t_ok(!$insp['edit'], '…and certainly cannot change the department list');
$coord = $roleCan('COORDINATOR', false);
t_ok($coord['view'], 'a coordinator can open it');
t_ok(!$coord['edit'], 'but a coordinator cannot add or rename a department');
$adm = $roleCan('ADMIN', true);
t_ok($adm['view'] && $adm['edit'], 'an administrator can do both');
$_SESSION = $origSess; current_user(true); ua(true);

// ---------------------------------------------------------------------------
// 13. Tenant safety (§34) — a vocabulary cache must not outlive its database.
// ---------------------------------------------------------------------------
$before = dept_label('M3HR');
$pdo->prepare("UPDATE lookup_values SET display_name=? WHERE id=?")->execute(['M3 Renamed By Another Workspace', $hrId]);
t_eq(dept_label('M3HR'), $before, 'within ONE request the resolved name stays cached');
$GLOBALS['__db_epoch'] = (int) ($GLOBALS['__db_epoch'] ?? 0) + 1;
t_eq(dept_label('M3HR'), 'M3 Renamed By Another Workspace',
     'when the database changes underneath it, the cache is rebuilt — no other workspace’s wording');

// ---------------------------------------------------------------------------
//  Clean up — leave the shared database as it was found.
// ---------------------------------------------------------------------------
$pdo->prepare("DELETE FROM recruit_approval_rules WHERE id=?")->execute([$ruleId]);
foreach (array_unique(array_filter($made)) as $id) {
    $pdo->prepare("DELETE FROM lookup_terms WHERE value_id=?")->execute([(int) $id]);
    $pdo->prepare("DELETE FROM lookup_values WHERE id=?")->execute([(int) $id]);
}
$pdo->prepare("DELETE FROM lookup_terms WHERE term LIKE 'M3 %'")->execute();
