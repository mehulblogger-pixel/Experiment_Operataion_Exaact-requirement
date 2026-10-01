#!/usr/bin/env python3
"""GATE 4 (§18) — the mutation battery.

Each entry breaks ONE part of the self-approval rule and the battery must FAIL.
A mutation that survives means the tests are decoration.

Nothing is left behind: each file is restored from its original bytes.
"""
import io, os, subprocess, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

MUTATIONS = [
 ("M1 the self-approval condition is reversed", "lib/recruit_approval.php",
  "    if ($me <= 0 || (int) $requesterId !== $me) return '';   // a different person: nothing to say",
  "    if ($me <= 0 || (int) $requesterId === $me) return '';   // REVERSED"),

 ("M2 the requester/approver comparison is removed", "lib/recruit_approval.php",
  "    $me = function_exists('current_user') && ($u = current_user()) ? (int) ($u['id'] ?? 0) : 0;",
  "    $me = -1;   // never equal to anybody"),

 ("M3 the master exception is bypassed (always allowed)", "lib/recruit_approval.php",
  "    if ($isMaster && $pol['master']) {",
  "    if ($isMaster) {"),

 ("M4 the organisation configuration is ignored", "lib/recruit_approval.php",
  """function appr_self_allowed() {
    return function_exists('setting_get') ? ((string) setting_get(APPR_SELF_KEY, '0') === '1') : false;
}""",
  """function appr_self_allowed() {
    return true;   // IGNORES THE ORGANISATION
}"""),

 ("M5 material change routes on the OLD values", "lib/reqversion.php",
  "            [$started, $apprId] = appr_start((string) $route['entity'], $id,\n                rver_appr_ctx($entity, $proposed),",
  "            [$started, $apprId] = appr_start((string) $route['entity'], $id,\n                rver_appr_ctx($entity, $row),"),

 #  Placed BEFORE the `if (!$needs)` branch it is meant to flip. The first attempt
 #  at this mutation went in after that branch and therefore changed nothing — an
 #  ineffective mutation surviving says nothing about the code, only about the
 #  mutation, so it was moved rather than the test being weakened.
 ("M6 no matching rule means auto-approve", "lib/reqversion.php",
  "    if (!$needs) {\n        [$ok, $msg] = rver_apply($pid, ['decided_by' => rver_who(), 'note' => 'No approval required by configuration']);",
  "    if (!$needs || !$route['rule']) {\n        [$ok, $msg] = rver_apply($pid, ['decided_by' => rver_who(), 'note' => 'No approval required by configuration']);"),

 ("M7 a new configuration reaches a request already in flight", "lib/recruit_approval.php",
  """    $tok = is_array($req) ? trim((string) ($req['self_policy'] ?? '')) : '';
    if (preg_match('~^S([01])M([01])$~', $tok, $m))
        return ['self' => $m[1] === '1', 'master' => $m[2] === '1', 'frozen' => true];""",
  """    $tok = '';   // IGNORES THE FROZEN POLICY"""),

 ("M8 tenant scoping is removed from the policy read", "lib/recruit_approval.php",
  """function appr_self_master_exception() {
    return function_exists('setting_get') ? ((string) setting_get(APPR_SELF_MASTER_KEY, '0') === '1') : false;
}""",
  """function appr_self_master_exception() {
    //  Reads a value cached across the tenant switch instead of this organisation's own.
    static $once = null;
    if ($once === null) $once = function_exists('setting_get')
        ? ((string) setting_get(APPR_SELF_MASTER_KEY, '0') === '1') : false;
    return $once;
}"""),
]

def run_battery(env):
    p = subprocess.run(['php', 'tests/run.php', 'gate4'], cwd=ROOT,
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
            print('  ?? %-52s ANCHOR MATCHED %d TIMES' % (name, original.count(find)))
            survived.append(name + ' (anchor)')
            continue
        io.open(path, 'w', encoding='utf-8').write(original.replace(find, repl, 1))
        try:
            fails, out = run_battery(env)
        finally:
            io.open(path, 'w', encoding='utf-8').write(original)
        if fails == 0:
            print('  SURVIVED  %-52s 0 failures' % name); survived.append(name)
        elif fails < 0:
            print('  killed    %-52s could not run' % name); killed += 1
        else:
            print('  killed    %-52s %d assertion(s) failed' % (name, fails)); killed += 1
    print('\n%d killed / %d total' % (killed, len(MUTATIONS)))
    if survived:
        print('SURVIVORS:'); [print('  - ' + x) for x in survived]; sys.exit(1)
    print('ALL MUTATIONS KILLED')

main()
