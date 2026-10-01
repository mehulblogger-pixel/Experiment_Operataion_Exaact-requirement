#!/usr/bin/env python3
""" GATE 6B — the mutation battery for the two locked decisions.

Each entry breaks ONE part of R1-UI or R2, and tests/test_gate6b_* must FAIL.
A mutation that SURVIVES means the assertion that was supposed to protect that
behaviour is decoration.

Rule this battery obeys: a mutation counts as killed only when a BEHAVIOURAL
assertion fails. Nothing here greps source for a marker.

Nothing is left behind: original bytes are restored, and a sidecar heals an
interrupted run on the next invocation.
"""
import io, os, subprocess, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

MUTATIONS = [
 # ======================================================================
 #  R1-UI — the configurable trigger
 # ======================================================================
 #  The whole point of the decision: the stored setting must actually govern
 #  whether a candidate is stopped. If the engine ignores it, C5/C6 must bite.
 ("G6B-M1 the engine ignores the stored setting", "lib/candreview.php",
  """        $v = function_exists('setting_get') ? setting_get(crev_trigger_key($t), '1') : '1';
        if ((string) $v !== '0') $on[] = $t;""",
  """        $on[] = $t;"""),

 #  The opposite failure: the optional trigger reads as OFF whatever is stored,
 #  so a review that should be raised never is.
 ("G6B-M2 the optional trigger is never on", "lib/candreview.php",
  """        if ((string) $v !== '0') $on[] = $t;""",
  """        if ((string) $v === '0') $on[] = $t;"""),

 #  THE LOCKED RULE. If 'stricter' becomes configurable, switching the optional
 #  trigger off would switch the mandatory one off with it — exactly the hole
 #  R1-UI must not open. C10/C11/C12 must bite, and so must A5.
 ("G6B-M3 the mandatory trigger becomes configurable", "lib/candreview.php",
  """    $on = CREV_TRIGGER_ALWAYS;""",
  """    $on = [];"""),

 #  DEFAULT ON. An organisation that has never opened the screen must keep the
 #  behaviour it had before the screen existed. Flipping the default to '0'
 #  silently changes every existing workspace.
 ("G6B-M4 a new organisation defaults to OFF", "lib/candreview.php",
  """setting_get(crev_trigger_key($t), '1') : '1';""",
  """setting_get(crev_trigger_key($t), '0') : '0';"""),

 #  ONE KEY. If the screen and the engine can disagree about the setting name,
 #  the screen saves into a key nothing reads — the classic "it does not stick".
 ("G6B-M5 the setting key is not the one Gate 3 used", "lib/candreview.php",
  """function crev_trigger_key($t) { return 'crev_trigger_' . strtolower(trim((string) $t)); }""",
  """function crev_trigger_key($t) { return 'crev_trig_' . strtolower(trim((string) $t)); }"""),

 #  WHAT THE SCREEN IS ALLOWED TO OFFER. If 'stricter' is offered as optional,
 #  an administrator is shown a switch for a rule that cannot be switched —
 #  which is worse than hiding it, because it lies.
 ("G6B-M6 the screen offers the mandatory trigger as optional", "lib/candreview.php",
  """    foreach (CREV_TRIGGER_OPTIONAL as $t) $opt[$t] = crev_trigger_on($t);""",
  """    foreach (array_merge(CREV_TRIGGER_OPTIONAL, CREV_TRIGGER_ALWAYS) as $t) $opt[$t] = crev_trigger_on($t);"""),

 #  The state the screen renders must reflect what is stored, not a constant.
 ("G6B-M7 the screen always shows ON", "lib/candreview.php",
  """    foreach (CREV_TRIGGER_OPTIONAL as $t) $opt[$t] = crev_trigger_on($t);""",
  """    foreach (CREV_TRIGGER_OPTIONAL as $t) $opt[$t] = true;"""),

 # ======================================================================
 #  R2 — the utilisation breakdown
 # ======================================================================
 #  The decision itself, removed. D6/D7/D10 must bite.
 ("G6B-M8 the breakdown stops excluding people who have not started", "lib/mis.php",
  """        if (isset($notStartedYet[(int) $ins['id']])) continue;""",
  """        if (false) continue;"""),

 #  The exclusion is still there but asks nobody: the helper returns nothing.
 ("G6B-M9 the not-yet-joined helper finds nobody", "lib/workforce.php",
  """    foreach ($rows as $r) $out[(int) $r['id']] = true;""",
  """    foreach ($rows as $r) { }"""),

 #  Keyed wrongly — a list instead of a lookup. isset() then tests positions, so
 #  the wrong people are excluded. This is the subtle version of M9.
 ("G6B-M10 the helper returns a list instead of a lookup", "lib/workforce.php",
  """    foreach ($rows as $r) $out[(int) $r['id']] = true;""",
  """    foreach ($rows as $r) $out[] = (int) $r['id'];"""),

 #  THE OVER-REACH. Excluding leavers as well would quietly understate the days
 #  the business actually delivered — somebody who worked three weeks and then
 #  resigned did the work. D8/D9 must bite.
 ("G6B-M11 the breakdown also drops leavers", "lib/mis.php",
  "        if (isset($notStartedYet[(int) $ins['id']])) continue;",
  "        if (isset($notStartedYet[(int) $ins['id']])) continue;\n"
  "        if (wf_has_left((string) ops_val(\"SELECT status FROM inspectors WHERE id=?\", [(int) $ins['id']]))) continue;"),

 #  THE SCOPE BREACH. Moving the exclusion into the shared reader would take the
 #  not-yet-joined person out of the requisition form, the voucher list and the
 #  person-linking picker too — the one thing the decision forbids. E4-E9 must bite.
 ("G6B-M12 the exclusion leaks into the shared team-member list", "lib/ops.php",
  """                     FROM inspectors" . ($activeOnly ? " WHERE COALESCE(NULLIF(status,''),'ACTIVE')='ACTIVE'" : "") . " ORDER BY name");""",
  """                     FROM inspectors WHERE COALESCE(NULLIF(status,''),'ACTIVE')='ACTIVE' ORDER BY name");"""),

 #  THE DENOMINATOR. The decision is only correct because capacity never counted
 #  these people. If the denominator started counting them, the report would be
 #  wrong in the other direction — and E1 must say so.
 ("G6B-M13 the capacity denominator starts counting them", "lib/mis.php",
  """    $w = ["status='ACTIVE'"]; $a = [];""",
  """    $w = ["COALESCE(status,'')<>'INACTIVE'"]; $a = [];"""),

 #  SUBCONTRACTORS. The helper must not claim a subcontractor is on our joining
 #  pipeline. E12 must bite.
 ("G6B-M14 the helper claims subcontractors are joining", "lib/workforce.php",
  """                            AND COALESCE(staff_kind,'ASSET')<>'SUBCON'") ?: [];
    } catch (Throwable $e) { return $out; }""",
  """                            ") ?: [];
    } catch (Throwable $e) { return $out; }"""),

 # ======================================================================
 #  GATE 5 / GATE 6 INVARIANTS this gate must not have loosened
 # ======================================================================
 ("G6B-M15 joining-pending counts as operationally active again", "lib/workforce.php",
  """function wf_is_active($status) {""",
  """function wf_is_active($status) { if (strtoupper(trim((string)$status)) === WF_ST_JOINING) return true;"""),

 ("G6B-M16 the availability board accepts somebody who has not joined", "lib/workforce.php",
  """    $where = "status='ACTIVE' AND COALESCE(staff_kind,'ASSET')<>'SUBCON'";""",
  """    $where = "COALESCE(status,'')<>'INACTIVE' AND COALESCE(staff_kind,'ASSET')<>'SUBCON'";"""),
]

#  MUTATIONS WHOSE EFFECT IS ONLY VISIBLE ON SCREEN.
#
#  The save handler and the panel live in a route handler and a view, and view() is
#  defined in index.php — unreachable from the PHP harness. The only honest way to
#  prove these is to drive the real routes in a browser, so they are checked against
#  tools/g6b-browser-run.sh rather than the suite.
BROWSER_MUTATIONS = [
 #  THE SAVE. If the handler writes the opposite value, the screen appears to work
 #  and silently does the reverse of what the administrator asked.
 ("G6B-B1 the save writes the opposite value", "lib/recruit_approval.php",
  """                setting_set(crev_trigger_key($t), (($_POST['trigger_' . $t] ?? '') === '1') ? '1' : '0');""",
  """                setting_set(crev_trigger_key($t), (($_POST['trigger_' . $t] ?? '') === '1') ? '0' : '1');"""),

 #  …or does not write at all: the classic "I changed it and it did not stick".
 ("G6B-B2 the save does nothing", "lib/recruit_approval.php",
  """                setting_set(crev_trigger_key($t), (($_POST['trigger_' . $t] ?? '') === '1') ? '1' : '0');""",
  """                /* removed */;"""),

 #  THE LOCKED RULE, offered as a switch. The engine would ignore it, so the real
 #  damage is a screen that lies to an administrator about what they control.
 ("G6B-B3 the screen offers a switch for the mandatory trigger", "views/ops/approval_rules.php",
  """      <input type="checkbox" name="trigger_redefined" value="1"<?= !empty($rt['optional']['redefined']) ? ' checked' : '' ?>>""",
  """      <input type="checkbox" name="trigger_redefined" value="1"<?= !empty($rt['optional']['redefined']) ? ' checked' : '' ?>>
      <input type="checkbox" name="trigger_stricter" value="1" checked>"""),

 #  THE STATE IGNORED. A control hard-wired to "on" shows the wrong answer to
 #  every organisation that switched it off.
 ("G6B-B4 the control always renders as ON", "views/ops/approval_rules.php",
  """      <input type="checkbox" name="trigger_redefined" value="1"<?= !empty($rt['optional']['redefined']) ? ' checked' : '' ?>>""",
  """      <input type="checkbox" name="trigger_redefined" value="1" checked>"""),

 #  THE PHONE LAYOUT. Putting the fixed rail back must make 360px scroll sideways
 #  again — which is how the fix is proved to be the thing doing the work.
 ("G6B-B5 the rules grid stops stacking on a phone", "views/ops/approval_rules.php",
  """  @media(max-width:1080px){.ar-split{grid-template-columns:1fr}}""",
  """  /* removed */"""),

 #  DISCOVERABILITY. If the exclusion reached the screen's own person filter, the
 #  not-yet-joined person would vanish from the report entirely — which is the one
 #  thing R2 must not do. R2-UI-8 must bite.
 ("G6B-B6 the exclusion reaches the report's person filter too", "lib/ops.php",
  """        'inspOpts'=>inspectors_list(false), 'actType'=>lk_type('activity'),""",
  """        'inspOpts'=>inspectors_list(true), 'actType'=>lk_type('activity'),"""),
]

SIDECAR = '.g6borig'


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


def run_browser(env):
    p = subprocess.run(['bash', 'tools/g6b-browser-run.sh'], cwd=ROOT,
                       capture_output=True, text=True, env=env)
    out = p.stdout + p.stderr
    #  A run that never reached the assertions is NOT a kill. The seed computes its
    #  own expected values precisely so that a mutation cannot abort it, but if the
    #  run dies anyway that is reported as crashed, not killed.
    if 'SEED FAILED' in out or 'aborting' in out:
        return -1, out
    for line in out.splitlines():
        if line.startswith('BROWSER RESULT:'):
            return int(line.split(',')[1].strip().split()[0]), out
    return -1, out


def run_battery(env):
    p = subprocess.run(['php', 'tests/run.php', 'gate6b'], cwd=ROOT,
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
    killed, survived, crashed = 0, [], []
    #  The browser runner picks its own port, so a stale server left holding the
    #  default one cannot silently turn every browser mutation into "crashed".
    env.setdefault('PORT', '8902')
    for name, rel, find, repl in (MUTATIONS if '--browser-only' not in sys.argv else []):
        path = os.path.join(ROOT, rel)
        original = io.open(path, encoding='utf-8').read()
        if original.count(find) != 1:
            print('  ?? %-62s ANCHOR MATCHED %d TIMES' % (name, original.count(find)))
            survived.append(name + ' (anchor)')
            continue
        io.open(path + SIDECAR, 'w', encoding='utf-8').write(original)
        io.open(path, 'w', encoding='utf-8').write(original.replace(find, repl, 1))
        try:
            fails, out = run_battery(env)
        finally:
            io.open(path, 'w', encoding='utf-8').write(original)
            if os.path.exists(path + SIDECAR):
                os.remove(path + SIDECAR)
        if fails == 0:
            print('  SURVIVED  %-62s 0 failures' % name); survived.append(name)
        elif fails < 0:
            #  NOT counted as a behavioural kill. A mutation that makes the suite
            #  crash proves the code is reachable, not that an assertion guards it.
            print('  crashed   %-62s suite could not run' % name); crashed.append(name)
        else:
            first = [l.strip() for l in out.splitlines() if l.strip().startswith('- ')][:3]
            print('  killed    %-62s %d failed%s' % (name, fails,
                  ('  [' + '; '.join(x[2:40] for x in first) + ']') if first else ''))
            killed += 1

    bkilled = 0
    if '--server-only' not in sys.argv:
        for name, rel, find, repl in BROWSER_MUTATIONS:
            path = os.path.join(ROOT, rel)
            original = io.open(path, encoding='utf-8').read()
            if original.count(find) != 1:
                print('  ?? %-62s ANCHOR MATCHED %d TIMES' % (name, original.count(find)))
                survived.append(name + ' (anchor)')
                continue
            io.open(path + SIDECAR, 'w', encoding='utf-8').write(original)
            io.open(path, 'w', encoding='utf-8').write(original.replace(find, repl, 1))
            try:
                fails, out = run_browser(env)
            finally:
                io.open(path, 'w', encoding='utf-8').write(original)
                if os.path.exists(path + SIDECAR):
                    os.remove(path + SIDECAR)
            if fails == 0:
                print('  SURVIVED  %-62s 0 browser failures' % name); survived.append(name)
            elif fails < 0:
                print('  crashed   %-62s browser run could not complete' % name); crashed.append(name)
            else:
                first = [l.strip() for l in out.splitlines() if l.strip().startswith('FAIL')][:2]
                print('  killed    %-62s %d browser assertion(s) failed%s' % (name, fails,
                      ('  [' + '; '.join(x[6:46] for x in first) + ']') if first else ''))
                bkilled += 1

    print()
    if '--browser-only' not in sys.argv:
        print('%d of %d mutations killed by a behavioural assertion' % (killed, len(MUTATIONS)))
    if '--server-only' not in sys.argv:
        print('%d of %d browser mutations killed by a browser assertion' % (bkilled, len(BROWSER_MUTATIONS)))
    if crashed:
        print('CRASHED (not counted as killed): ' + ', '.join(crashed))
    if survived:
        print('SURVIVORS:'); [print('  - ' + x) for x in survived]
    if survived or crashed:
        sys.exit(1)
    print('ALL MUTATIONS KILLED BEHAVIOURALLY')


main()
