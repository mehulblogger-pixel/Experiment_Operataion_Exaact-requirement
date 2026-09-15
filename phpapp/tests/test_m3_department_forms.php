<?php
// ============================================================================
//  PHASE 2 — CLOSING THE DEPARTMENT FORMS
//
//  The vocabulary milestone unified how a department is READ but left ENTRY on
//  the legacy hiring list, so a requisition raised today still wrote a value the
//  canonical master did not know. This closes that.
//
//  The shape of the fix is additive, because the alternative — rewriting every
//  stored department — is the destructive migration the milestone forbids:
//
//    department      keeps holding text, now the canonical CODE for new records
//    department_id   NEW. carries the identity, which survives a rename
//
//  So every existing reader is untouched, every old row still reads, and a
//  department renamed tomorrow does not orphan a requisition raised today.
// ============================================================================

t_section('Phase 2 — requisition & candidate forms on the canonical Department');

$pdo = db();
vocab_migrate(); dept_vocab_seed(); req_migrate();
$made = ['req' => [], 'cand' => [], 'dept' => []];

// ---------------------------------------------------------------------------
//  1. Structure — additive, on both objects.
// ---------------------------------------------------------------------------
t_ok(in_array('department_id', t_columns('requisitions'), true), 'requisitions carries the Department identity');
t_ok(in_array('department_id', t_columns('candidates'), true), 'candidates carries the Department identity');
t_ok(in_array('department', t_columns('requisitions'), true), 'the original free-text column is still there — nothing was dropped');
t_ok(in_array('department', t_columns('candidates'), true), '…on candidates too');

// ---------------------------------------------------------------------------
//  2. Fresh install (§29) — the requisition structure must not depend on
//     somebody happening to open a requisition page first.
// ---------------------------------------------------------------------------
$dbSrc = file_get_contents(__DIR__ . '/../lib/db.php');
t_ok(strpos($dbSrc, 'req_migrate()') !== false, 'requisition structure is created at boot, not only when a route is opened');
foreach (['quantity', 'start_date', 'department_id', 'position_id'] as $c)
    t_ok(in_array($c, t_columns('requisitions'), true), "requisitions.$c exists in a database that has only been booted");

// ---------------------------------------------------------------------------
//  3. The picker offers the canonical master — and nothing else.
// ---------------------------------------------------------------------------
t_ok(function_exists('dept_form_options'), 'there is one department picker for every form');
$opts = dept_form_options('');
t_ok(count($opts) > 0, 'it offers the canonical departments (' . count($opts) . ')');
$canon = array_keys(lk_options_or('department', DEPARTMENTS));
foreach (array_keys($opts) as $k) t_ok(in_array($k, $canon, true), "every option is a canonical department ($k)");
// QAQC is a WORD for Quality, not a department, so it is never offered as one.
// NDT, by decision, IS a department in its own right — so it is offered.
t_ok(!isset($opts['QAQC']),
     'a legacy word that means an existing department is not offered as a separate department');
t_ok(isset($opts['NDT']),
     'but NDT is offered, because the decision made it a department in its own right');
t_ok(isset($opts['HR']), '…as is HR, for the same reason');

// ---------------------------------------------------------------------------
//  4. …but a record that already holds a legacy value keeps it. Opening an old
//     requisition must never silently blank its department.
// ---------------------------------------------------------------------------
$withLegacy = dept_form_options('QAQC');
t_ok(isset($withLegacy['QAQC']), 'an existing legacy value stays selectable on the edit form');
t_ok(count($withLegacy) === count($opts) + 1, 'and it is added, not substituted for the canonical list');
$withUnknown = dept_form_options('Some Wording Nobody Knows');
t_ok(isset($withUnknown['Some Wording Nobody Knows']), 'free text typed long ago also stays selectable');

// ---------------------------------------------------------------------------
//  5. What a save means: identity where it is known, the wording preserved
//     where it is not. Nothing is invented.
// ---------------------------------------------------------------------------
t_ok(function_exists('dept_form_save'), 'a form POST is resolved through one place');
$deptRow = vocab_resolve('department', 'Quality');
t_ok($deptRow !== null, 'the shipped Quality department resolves');
$sv = dept_form_save(['department' => 'Quality']);
t_eq($sv['department_id'], (int) $deptRow['id'], 'picking a department stores its identity');
t_eq($sv['department'], (string) $deptRow['code'], '…and the canonical code in the text column, so existing readers still work');

$svSyn = dept_form_save(['department' => strtoupper((string) $deptRow['code'])]);
t_eq($svSyn['department_id'], (int) $deptRow['id'], 'a code resolves to the same identity as its name');

$svUnknown = dept_form_save(['department' => 'Wholly Unknown Wording']);
t_eq($svUnknown['department_id'], null, 'unrecognised wording gets NO identity — nothing is guessed');
t_eq($svUnknown['department'], 'Wholly Unknown Wording', '…and is preserved exactly as typed');
$svBlank = dept_form_save(['department' => '']);
t_eq($svBlank['department'], '', 'blank stays blank');
t_eq($svBlank['department_id'], null, 'and gets no identity');

// ---------------------------------------------------------------------------
//  6. Reading a record: identity first, legacy text as the fallback.
// ---------------------------------------------------------------------------
$now = date('c');
$pdo->prepare("INSERT INTO requisitions (req_code,department,department_id,status,created_at) VALUES ('M3F-NEW',?,?,'OPEN',?)")
    ->execute([$sv['department'], $sv['department_id'], $now]);
$newId = (int) $pdo->lastInsertId(); $made['req'][] = $newId;
$pdo->prepare("INSERT INTO requisitions (req_code,department,status,created_at) VALUES ('M3F-OLD','QAQC','OPEN',?)")->execute([$now]);
$oldId = (int) $pdo->lastInsertId(); $made['req'][] = $oldId;

$new = ops_one("SELECT * FROM requisitions WHERE id=?", [$newId]);
$old = ops_one("SELECT * FROM requisitions WHERE id=?", [$oldId]);
t_eq((int) dept_of_row($new)['id'], (int) $deptRow['id'], 'a new record resolves through its identity');
t_eq(dept_row_label($new), vocab_display($deptRow), '…and displays the department name');
// Before the decisions this read "QA / QC" — the legacy list's own label. Now
// that QA/QC has been decided to BE Quality, it reads Quality. Either way the
// point is the same: a legacy record never shows a raw code.
t_eq(dept_row_label($old), 'Quality', 'a legacy record with no identity displays its department, not a raw code');
t_eq($old['department'], 'QAQC', '…and its stored value was NOT rewritten');

// ---------------------------------------------------------------------------
//  7. The point of carrying identity: a rename must not orphan a record.
// ---------------------------------------------------------------------------
$before = dept_row_label($new);
$pdo->prepare("UPDATE lookup_values SET display_name=? WHERE id=?")->execute(['M3F Renamed Department', (int) $deptRow['id']]);
$GLOBALS['__db_epoch'] = (int) ($GLOBALS['__db_epoch'] ?? 0) + 1;
t_eq(dept_row_label($new), 'M3F Renamed Department', 'renaming the department renames it on the requisition too');
$still = ops_one("SELECT department, department_id FROM requisitions WHERE id=?", [$newId]);
t_eq((int) $still['department_id'], (int) $deptRow['id'], '…because the link is the identity, which did not change');
$pdo->prepare("UPDATE lookup_values SET display_name='' WHERE id=?")->execute([(int) $deptRow['id']]);
$GLOBALS['__db_epoch'] = (int) ($GLOBALS['__db_epoch'] ?? 0) + 1;

// The rename above is not on its own proof that the IDENTITY is doing the work:
// the text column also holds the canonical code, so a fallback would have found
// the same department anyway. What separates them is a record whose WORDING
// cannot resolve but whose identity is sound — which is exactly a record filed
// under a word the master does not know, later linked to a real department.
$pdo->prepare("INSERT INTO requisitions (req_code,department,department_id,status,created_at) VALUES ('M3F-IDONLY',?,?,'OPEN',?)")
    ->execute(['Wording The Master Does Not Know', (int) $deptRow['id'], $now]);
$idOnlyId = (int) $pdo->lastInsertId(); $made['req'][] = $idOnlyId;
$idOnly = ops_one("SELECT * FROM requisitions WHERE id=?", [$idOnlyId]);

t_eq(vocab_resolve('department', 'Wording The Master Does Not Know'), null,
     'the wording on that record resolves to nothing on its own');
t_eq((int) (dept_of_row($idOnly)['id'] ?? 0), (int) $deptRow['id'],
     'but the record still finds its department — through the identity, not the wording');
t_eq(dept_row_label($idOnly), vocab_display(vocab_value((int) $deptRow['id'])),
     '…and displays that department, not the raw wording');

// ---------------------------------------------------------------------------
//  8. Both save handlers actually run the resolution — pinned structurally, so
//     a future edit cannot quietly drop it from one of them.
// ---------------------------------------------------------------------------
$opsSrc = file_get_contents(__DIR__ . '/../lib/ops.php');
t_eq(substr_count($opsSrc, 'dept_form_save($b)'), 2, 'the requisition AND candidate handlers both resolve the department');

// ---------------------------------------------------------------------------
//  9. Nothing renders or exports a raw code any more.
// ---------------------------------------------------------------------------
foreach ([['lib/recruit_export.php', 'exports'], ['lib/recruit_cc.php', 'the Command Centre'], ['lib/doc_templates.php', 'offer letters']] as $f)
    t_ok(strpos(file_get_contents(__DIR__ . '/../' . $f[0]), 'dept_row_label') !== false,
         $f[1] . ' render the department name, not the stored code');
t_ok(strpos(file_get_contents(__DIR__ . '/../lib/careers.php'), 'department_id') !== false,
     'a public application inherits the department identity from the job');

// ---------------------------------------------------------------------------
// 10. Negative and boundary.
// ---------------------------------------------------------------------------
t_eq(dept_form_save([])['department'], '', 'a POST with no department at all is not an error');
t_eq(dept_form_save(['department' => '   '])['department'], '', 'whitespace only is treated as blank');
// A dangling identity must not lose the department: it falls through to the
// wording. Since QA/QC was decided to BE Quality, that wording now resolves to a
// real department — which is a stronger outcome than before the decision, when
// it could only fall back to the legacy list's label.
t_eq((int) (dept_of_row(['department_id' => 999999, 'department' => 'QAQC'])['id'] ?? 0),
     (int) vocab_resolve('department', 'Quality')['id'],
     'an identity that no longer exists falls through to the wording, which resolves');
t_eq(dept_row_label(['department_id' => 999999, 'department' => 'QAQC']), 'Quality',
     '…so the record still shows its department — a dangling id never loses it');
// And a wording nobody has decided still resolves to nothing, dangling id or not.
t_eq(dept_of_row(['department_id' => 999999, 'department' => 'M3 Never Decided']), null,
     'a dangling id with undecided wording resolves to no department — no guessing');
$q = vocab_resolve('department', 'Quality');
t_eq((int) dept_of_row(['department_id' => 999999, 'department' => 'Quality'])['id'], (int) $q['id'],
     'and where the wording IS known, a dangling id falls through to it');
t_eq(dept_of_row(['department' => '']), null, 'an empty record has no department');
t_eq(dept_row_label(['department' => '']), '', '…and renders as nothing, not as "—" or a stray code');

// ---------------------------------------------------------------------------
//  Clean up.
// ---------------------------------------------------------------------------
foreach ($made['req'] as $id)  $pdo->prepare("DELETE FROM requisitions WHERE id=?")->execute([$id]);
foreach ($made['cand'] as $id) $pdo->prepare("DELETE FROM candidates WHERE id=?")->execute([$id]);
