#!/usr/bin/env python3
"""GATE 6 — the mutation battery.

Each entry breaks ONE part of the workforce-activation rule, and the Gate 5
battery must FAIL. A mutation that survives means the tests are decoration.

Nothing is left behind: each file is restored from its original bytes.
"""
import io, os, subprocess, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

MUTATIONS = [
 # ---- A-F1: who may be given a job ---------------------------------------
 #  Gate 5's battery could never catch this: M6/M8 break wf_is_active() and
 #  wf_active_sql(), and this screen called neither — it carried its own copy of
 #  the rule. The mutation restores that copy, and the Gate 6 battery must fail
 #  BEHAVIOURALLY (AF1-3: a joining-pending person offered in the dropdown), not
 #  merely on a source grep.
 ("G6-M1 the assignment dropdown filters negatively again", "lib/tosrm.php",
  """    $insps = ops_all("SELECT id, name FROM inspectors WHERE " . wf_active_sql() . " ORDER BY name") ?: [];""",
  """    $insps = ops_all("SELECT id, name FROM inspectors WHERE COALESCE(status,'')<>'INACTIVE' ORDER BY name") ?: [];"""),

 #  And the weaker form: any filter at all removed. If the dropdown stops asking
 #  the question entirely, the same assertions must still bite.
 ("G6-M2 the assignment dropdown stops filtering at all", "lib/tosrm.php",
  """    $insps = ops_all("SELECT id, name FROM inspectors WHERE " . wf_active_sql() . " ORDER BY name") ?: [];""",
  """    $insps = ops_all("SELECT id, name FROM inspectors ORDER BY name") ?: [];"""),
]


#  CRASH SAFETY. A mutation battery deliberately holds a BROKEN copy of a source
#  file on disk while it runs. If the run is interrupted — Ctrl-C, a timeout, a
#  killed shell — the restore in the `finally` may never happen, and the next
#  person to commit ships sabotage with a green-looking test history behind it.
#  That is not hypothetical: it happened twice while this battery was being built,
#  once leaving the status dropdown reverted to the exact bug this gate fixes.
#
#  So the original bytes are also written to a sidecar before each mutation, and
#  any sidecar found at startup is restored first. An interrupted run now heals
#  itself on the next invocation instead of waiting to be noticed.
SIDECAR = '.g5orig'


def heal():
    healed = []
    for root, _dirs, files in os.walk(ROOT):
        if '/.git' in root:
            continue
        for f in files:
            if f.endswith(SIDECAR):
                side = os.path.join(root, f)
                real = side[:-len(SIDECAR)]
                try:
                    io.open(real, 'w', encoding='utf-8').write(io.open(side, encoding='utf-8').read())
                    os.remove(side)
                    healed.append(os.path.relpath(real, ROOT))
                except OSError as e:
                    print('  !! could not restore %s: %s' % (real, e))
    if healed:
        print('  healed an interrupted previous run: ' + ', '.join(healed))


def run_battery(env):
    p = subprocess.run(['php', 'tests/run.php', 'gate6'], cwd=ROOT,
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
    heal()
    engine = 'mysql' if (len(sys.argv) > 1 and sys.argv[1] == 'mysql') else 'sqlite'
    killed, survived, skipped = 0, [], []
    for entry in MUTATIONS:
        name, rel, find, repl = entry[0], entry[1], entry[2], entry[3]
        only = entry[4] if len(entry) > 4 else ''
        if only and only != engine:
            print('  skipped   %-56s %s-only' % (name, only)); skipped.append(name); continue
        path = os.path.join(ROOT, rel)
        original = io.open(path, encoding='utf-8').read()
        io.open(path + SIDECAR, 'w', encoding='utf-8').write(original)
        if original.count(find) != 1:
            os.remove(path + SIDECAR)
            print('  ?? %-56s ANCHOR MATCHED %d TIMES' % (name, original.count(find)))
            survived.append(name + ' (anchor)')
            continue
        io.open(path, 'w', encoding='utf-8').write(original.replace(find, repl, 1))
        try:
            fails, out = run_battery(env)
        finally:
            io.open(path, 'w', encoding='utf-8').write(original)
            if os.path.exists(path + SIDECAR):
                os.remove(path + SIDECAR)
        if fails == 0:
            print('  SURVIVED  %-56s 0 failures' % name); survived.append(name)
        elif fails < 0:
            print('  killed    %-56s could not run' % name); killed += 1
        else:
            print('  killed    %-56s %d assertion(s) failed' % (name, fails)); killed += 1
    print('\n%d killed / %d run (%d skipped as engine-specific)'
          % (killed, len(MUTATIONS) - len(skipped), len(skipped)))
    if skipped: print('SKIPPED (not applicable to this engine): ' + ', '.join(skipped))
    if survived:
        print('SURVIVORS:'); [print('  - ' + x) for x in survived]; sys.exit(1)
    print('ALL MUTATIONS KILLED')


main()
