#!/usr/bin/env python3
"""GATE 5 (§22) — the mutation battery.

Each entry breaks ONE part of the workforce-activation rule, and the Gate 5
battery must FAIL. A mutation that survives means the tests are decoration.

Nothing is left behind: each file is restored from its original bytes.
"""
import io, os, subprocess, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

MUTATIONS = [
 # ---- the boundary itself -------------------------------------------------
 ("M1 acceptance activates the person again", "lib/recruit.php",
  """,?,?,?,'" . WF_ST_JOINING . "',?)")""",
  """,?,?,?,'ACTIVE',?)")"""),

 ("M2 joining no longer activates anybody", "lib/ops.php",
  "            if (function_exists('wf_join_activate'))\n                $activated = wf_join_activate((int)$cand['inspector_id'], 'joined on ' . $when);",
  "            $activated = false;   // ACTIVATION REMOVED"),

 ("M3 clearing a joining leaves them on the roster", "lib/ops.php",
  "                    if ((int)($cand['inspector_id'] ?? 0) > 0 && function_exists('wf_join_stand_down'))\n                        $stoodDown = wf_join_stand_down((int)$cand['inspector_id'], 'joining record removed');",
  "                    $stoodDown = false;   // STAND-DOWN REMOVED"),

 # ---- the transition's preconditions -------------------------------------
 ("M4 activation ignores the status it expects to find", "lib/workforce.php",
  """    $st = db()->prepare("UPDATE inspectors SET status=? WHERE id=? AND UPPER(TRIM(COALESCE(status,'')))=?");
    $st->execute([$to, $inspectorId, $from]);""",
  """    $st = db()->prepare("UPDATE inspectors SET status=? WHERE id=?");
    $st->execute([$to, $inspectorId]);"""),

 ("M5 the transition claims success whether or not it moved a row", "lib/workforce.php",
  "    if ($st->rowCount() < 1) return false;",
  "    if (false) return false;   // ALWAYS CLAIMS A TRANSITION"),

 # ---- the vocabulary ------------------------------------------------------
 ("M6 a joiner counts as operationally active", "lib/workforce.php",
  "    return ($c === '' ? WF_ST_ACTIVE : $c) === WF_ST_ACTIVE;",
  "    return $c !== WF_ST_INACTIVE;   // JOINER TREATED AS ACTIVE"),

 ("M7 'has left' goes back to meaning 'not active'", "lib/workforce.php",
  "    return strtoupper(trim((string)$status)) === WF_ST_INACTIVE;",
  "    return strtoupper(trim((string)$status)) !== WF_ST_ACTIVE;   // THE OLD CONFLATION"),

 ("M8 the canonical active SQL stops filtering", "lib/workforce.php",
  """    return "COALESCE(NULLIF(" . $p . "status,''),'" . WF_ST_ACTIVE . "')='" . WF_ST_ACTIVE . "'";""",
  """    return "1=1";   // NO FILTER"""),

 # ---- the migration -------------------------------------------------------
 ("M9 the migration also demotes people who have worked", "lib/workforce.php",
  "        'ids_reclassify'   => wf_joining_ids($A, $noJoin, $W, false),",
  "        'ids_reclassify'   => array_merge(wf_joining_ids($A, $noJoin, $W, false), wf_joining_ids($A, $noJoin, $W, true)),"),

 ("M10 the migration applies on a dry run", "lib/workforce.php",
  "    if (!$apply || !$s['ids_reclassify']) return $out;",
  "    if (!$s['ids_reclassify']) return $out;   // IGNORES THE DRY RUN"),

 # ---- the recording of the fact ------------------------------------------
 ("M11 the joining date write is unconditional", "lib/ops.php",
  """            $st = db()->prepare("UPDATE candidates SET joined_at=? WHERE id=? AND (joined_at IS NULL OR joined_at='')");""",
  """            $st = db()->prepare("UPDATE candidates SET joined_at=? WHERE id=?");"""),

 ("M12 the joining audit kind is unregistered again", "lib/activity.php",
  "    'JOINED'            => 'Joined',",
  "    'JOINED_X'          => 'Joined',"),

 # ---- the screen ----------------------------------------------------------
 ("M13 the status dropdown omits the joiner again", "views/ops/inspector_form.php",
  """<select class="form-control" name="status"><?php foreach (wf_statuses() as $k=>$v): ?>""",
  """<select class="form-control" name="status"><?php foreach (['ACTIVE'=>'Active','INACTIVE'=>'Inactive'] as $k=>$v): ?>"""),
]


def run_battery(env):
    p = subprocess.run(['php', 'tests/run.php', 'gate5'], cwd=ROOT,
                       capture_output=True, text=True, env=env)
    out = p.stdout + p.stderr
    if 'Fatal error' in out or 'Parse error' in out:
        return -1, out
    for line in out.splitlines():
        if line.startswith('RESULT:'):
            return int(line.split(',')[1].strip().split()[0]), out
    return -1, out


def main():
    env = dict(os.environ)
    if len(sys.argv) > 1 and sys.argv[1] == 'mysql':
        env.update({'DB_DRIVER': 'mysql', 'DB_HOST': '127.0.0.1',
                    'DB_NAME': sys.argv[2], 'DB_USER': 'exa', 'DB_PASS': 'exa'})
    killed, survived = 0, []
    for name, rel, find, repl in MUTATIONS:
        path = os.path.join(ROOT, rel)
        original = io.open(path, encoding='utf-8').read()
        if original.count(find) != 1:
            print('  ?? %-56s ANCHOR MATCHED %d TIMES' % (name, original.count(find)))
            survived.append(name + ' (anchor)')
            continue
        io.open(path, 'w', encoding='utf-8').write(original.replace(find, repl, 1))
        try:
            fails, out = run_battery(env)
        finally:
            io.open(path, 'w', encoding='utf-8').write(original)
        if fails == 0:
            print('  SURVIVED  %-56s 0 failures' % name); survived.append(name)
        elif fails < 0:
            print('  killed    %-56s could not run' % name); killed += 1
        else:
            print('  killed    %-56s %d assertion(s) failed' % (name, fails)); killed += 1
    print('\n%d killed / %d total' % (killed, len(MUTATIONS)))
    if survived:
        print('SURVIVORS:'); [print('  - ' + x) for x in survived]; sys.exit(1)
    print('ALL MUTATIONS KILLED')


main()
