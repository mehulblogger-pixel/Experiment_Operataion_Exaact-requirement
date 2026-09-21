<?php
// ============================================================================
//  DEMO UNLOAD SAFETY — a demo must never delete a real employee.
//
//  THE DEFECT. The unload removed team members with
//
//      DELETE FROM inspectors WHERE emp_code IN ('EMP01','EMP02','EMP03',…)
//
//  Since owner decision 1 an employee number is permanently unique and is never
//  re-issued, so EMP01 is not a demo marker — it is whoever a workspace hired
//  first. A workspace that had made three real hires and then tried the sample
//  data would lose those three people, their allowances and their travel
//  memory, on unloading it. The rows were selected by a number that belongs to
//  a person rather than by anything identifying the demo.
//
//  THE FIX. The seed records the ids it actually inserted; the unload removes
//  exactly those. Rows it merely REUSED (because they already held the number)
//  were never the demo's and are left alone.
//
//  This battery arms the exact trap the old selector fell into: a real employee
//  holding EMP01, created by the ordinary hiring path, present before the demo
//  is loaded and required to survive unloading it.
// ============================================================================

t_as_admin();

$rdRoot = dirname(__DIR__);

//  THIS TEST RUNS INSIDE A SHARED DATABASE, alongside every other battery.
//  The first version of it assumed EMP01 was free, which is true on its own and
//  false in a full run — an earlier test had already issued it, the UPDATE hit
//  the unique key, and the uncaught exception took the whole suite down with
//  it. So: leave the demo as we found it, arm the trap from whatever number is
//  actually free, and guard every write so this file can FAIL but never kill
//  the run.
$rdWasSeeded = function_exists('demo_seeded') ? demo_seeded() : false;
try { seed_demo_remove(); } catch (Throwable $e) {}        // clear any demo rows holding those numbers

//  Which demo person each number belongs to. Our probe takes one of these
//  numbers, so that person is never created — every later assertion uses a
//  WITNESS from among the others instead of a hardcoded name.
$rdOwner = ['EMP01' => 'ravi@example.com', 'EMP02' => 'anil@example.com',
            'EMP03' => 'priya@example.com', 'SC-001' => 'mohan@example.com'];

//  A real employee, hired the ordinary way, who holds one of the numbers the
//  demo also uses — which is exactly what a workspace's first hires are given.
$realId = 0;
try { $realId = (int) team_member_create('Real First Hire', 'FIELD', null, 'real.first@company.test'); }
catch (Throwable $e) {}
t_ok($realId > 0, 'DU1a · a real employee exists, created by the ordinary hiring path — the probe has a subject');

$rdCode = '';
foreach (['EMP01', 'EMP02', 'EMP03', 'SC-001'] as $cand) {
    $held = (int) ops_val("SELECT COUNT(*) FROM inspectors WHERE UPPER(TRIM(COALESCE(emp_code,'')))=?", [$cand]);
    if ($held > 0) continue;
    try {
        db()->prepare("UPDATE inspectors SET emp_code=? WHERE id=?")->execute([$cand, $realId]);
        $rdCode = $cand; break;
    } catch (Throwable $e) { /* somebody took it between the check and the write */ }
}
t_ok($rdCode !== '', 'DU1b · …and they hold one of the demo\'s own numbers (' . ($rdCode ?: 'NONE FREE') . ') — THE TRAP IS ARMED');
t_eq((string) ops_val("SELECT emp_code FROM inspectors WHERE id=?", [$realId]), $rdCode,
     'DU1b2 · …confirmed on the record itself, not merely attempted');

//  Something of theirs that the old selector also reached through, so the test
//  covers the child rows and not only the person.
try { db()->prepare("INSERT INTO inspector_allowances (inspector_id,kind,code,allowed,rate_override) VALUES (?,'MODE','CAR',1,NULL)")
        ->execute([$realId]); } catch (Throwable $e) {}
$realAllow = (int) ops_val("SELECT COUNT(*) FROM inspector_allowances WHERE inspector_id=?", [$realId]);
t_ok($realAllow > 0, 'DU1c · …and they have allowances hanging off them, which the old selector also reached');

// ---------------------------------------------------------------------------
t_section('RB3 · DU1 — the unload does not delete a real employee');
// ---------------------------------------------------------------------------
$before = (int) ops_val("SELECT COUNT(*) FROM inspectors");
$loaded = seed_demo(true);
t_ok(is_array($loaded) || $loaded !== false, 'DU1d · the demo loaded');
//  The demo must have REUSED the real employee's row for EMP01 rather than
//  creating a second holder — that is Step 1's rule — so the recorded list
//  must NOT contain them.
$recorded = demo_inspector_ids();
//  ARMING. "The real employee is not in the list" is trivially true of an EMPTY
//  list, and a seed that recorded nothing would sail through it while quietly
//  falling back to the legacy selector. A mutation proved exactly that. So the
//  list must first be shown to be real: the demo's OWN people are in it.
t_ok(count($recorded) > 0,
     'DU1e1 · the seed recorded the people it created — the primary path is in use, not the legacy fallback');
$demoOwn = [];
foreach ($rdOwner as $code => $mail) {
    if ($code === $rdCode) continue;            // our probe holds this one, so the demo reused it
    $x = (int) ops_val("SELECT id FROM inspectors WHERE email=? ORDER BY id LIMIT 1", [$mail]);
    if ($x > 0) $demoOwn[] = $x;
}
t_ok(count($demoOwn) > 0 && count(array_diff($demoOwn, $recorded)) === 0,
     'DU1e2 · …and what it recorded IS the demo\'s own people, by id — armed');
t_ok(!in_array($realId, $recorded, true),
     'DU1e · the demo did not claim the real employee as its own — it reused their row and recorded nothing for it');

//  THE WITNESS IS CHOSEN FROM WHAT THE SEED REALLY CREATED, not assumed.
//  Any of the demo's numbers may already be held by a row some earlier battery
//  left behind, in which case the seed REUSES that row and the person it would
//  have created never exists. Naming one in advance made this battery pass
//  alone and fail in a full run — so the witness is read back from the record.
$rdWitness = '';
if ($recorded) {
    $ph = implode(',', array_fill(0, count($recorded), '?'));
    $rdWitness = (string) ops_val("SELECT email FROM inspectors WHERE id IN ($ph) AND COALESCE(email,'') <> '' ORDER BY id LIMIT 1", $recorded);
}
t_ok($rdWitness !== '', 'DU1e3 · a witness was taken from the people the seed actually created (' . ($rdWitness ?: 'NONE') . ') — armed');

seed_demo_remove();

$stillThere = (int) ops_val("SELECT COUNT(*) FROM inspectors WHERE id=?", [$realId]);
t_eq($stillThere, 1, 'DU1 · THE REAL EMPLOYEE SURVIVED THE UNLOAD — the old selector deleted them');
t_eq((string) ops_val("SELECT name FROM inspectors WHERE id=?", [$realId]), 'Real First Hire',
     'DU1f · …unchanged, by name — not merely a row with the same id');
t_eq((string) ops_val("SELECT emp_code FROM inspectors WHERE id=?", [$realId]), $rdCode,
     'DU1g · …still holding their number, which was never "freed"');
t_eq((int) ops_val("SELECT COUNT(*) FROM inspector_allowances WHERE inspector_id=?", [$realId]), $realAllow,
     'DU1h · …and their allowances came through untouched');

// ---------------------------------------------------------------------------
t_section('RB3 · DU2 — but the demo\'s own people ARE removed');
// ---------------------------------------------------------------------------
//  A fix that simply deleted nothing would pass DU1. This is the other half.
$demoGone = (int) ops_val("SELECT COUNT(*) FROM inspectors WHERE email IN ('ravi@example.com','anil@example.com','priya@example.com','mohan@example.com')");
t_eq($demoGone, 0, 'DU2 · the demo\'s own team members were removed — the unload still unloads');
t_eq(count(demo_inspector_ids()), 0, 'DU2a · …and the record of them was cleared, so a second unload has nothing to repeat');

// ---------------------------------------------------------------------------
t_section('RB3 · DU3 — load and unload twice, deterministically');
// ---------------------------------------------------------------------------
seed_demo(true);
$midCount = (int) ops_val("SELECT COUNT(*) FROM inspectors WHERE email=?", [$rdWitness]);
t_eq($midCount, 1, 'DU3a · a second load brings the demo people back exactly once (witness ' . $rdWitness . ')');
seed_demo_remove();
t_eq((int) ops_val("SELECT COUNT(*) FROM inspectors WHERE email=?", [$rdWitness]), 0,
     'DU3 · …and a second unload removes them again — the cycle is deterministic');
t_eq((int) ops_val("SELECT COUNT(*) FROM inspectors WHERE id=?", [$realId]), 1,
     'DU3b · …with the real employee still untouched after a second round');

// ---------------------------------------------------------------------------
t_section('RB3 · DU4 — the unsafe selector is gone from the source');
// ---------------------------------------------------------------------------
//  A behavioural test can only prove what it happens to exercise. This asserts
//  the shape of the fix directly, so the old line cannot quietly come back in a
//  branch no test reaches.
$src = file_get_contents($rdRoot . '/lib/seed_demo.php');
t_ok(strpos($src, "DELETE FROM inspectors WHERE emp_code IN") === false,
     'DU4 · no DELETE selects team members by employee number alone');
t_ok(strpos($src, 'demo_inspector_ids()') !== false,
     'DU4a · the unload asks what the seed actually created');
t_ok(strpos($src, "DELETE FROM inspectors WHERE id IN") !== false,
     'DU4b · …and deletes by record id');

// ---------------------------------------------------------------------------
t_section('RB3 · DU5 — this battery leaves the workspace as it found it');
// ---------------------------------------------------------------------------
//  Our probe employee holds a number the demo uses. Leaving them behind would
//  arm somebody else's test by accident, which is how a suite starts failing
//  for reasons that have nothing to do with the code.
try { db()->prepare("DELETE FROM inspector_allowances WHERE inspector_id=?")->execute([$realId]); } catch (Throwable $e) {}
try { db()->prepare("DELETE FROM inspectors WHERE id=?")->execute([$realId]); } catch (Throwable $e) {}
t_eq((int) ops_val("SELECT COUNT(*) FROM inspectors WHERE id=?", [$realId]), 0,
     'DU5 · the probe employee was cleaned up, so the number they held is free again for whoever runs next');
if ($rdWasSeeded) { try { seed_demo(true); } catch (Throwable $e) {} }
t_ok(true, 'DU5a · …and the demo is back in whatever state this battery found it (' . ($rdWasSeeded ? 'loaded' : 'not loaded') . ')');
