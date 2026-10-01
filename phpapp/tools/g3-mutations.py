#!/usr/bin/env python3
"""GATE 3 (§32) — the mutation battery.

Each entry deliberately breaks ONE part of the Review Required rule and the
battery must FAIL. A mutation that survives means the tests are decoration, so
the rule is only as real as this file's "killed" count.

Nothing here is ever left behind: every mutation is reverted before the next one,
and the file is restored from the original bytes rather than by re-patching.
"""
import io, os, subprocess, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

MUTATIONS = [
 ("M1  only score-failing candidates are flagged", "lib/candreview.php",
  "        if (crev_is_pinned($entity, $entityId, $cid, $newVersion)) { $out['pinned'][] = $c; continue; }",
  """        if (crev_is_pinned($entity, $entityId, $cid, $newVersion)) { $out['pinned'][] = $c; continue; }
        $__f = rver_approved_fields($entity, $entityId);
        if (is_array($__f) && (float) ($c['experience_years'] ?? 0) >= (float) ($__f['min_experience_years'] ?? 0)) continue;"""),

 ("M2  only the first candidate is flagged", "lib/candreview.php",
  "            $out['raised']++;\n            crev_log($cid, 'Review required",
  "            $out['raised']++;\n            if ($out['raised'] >= 1) { crev_log($cid, 'x'); break; }\n            crev_log($cid, 'Review required"),

 ("M3  a review becomes a global candidate state", "lib/candreview.php",
  '''        return ops_all("SELECT * FROM candidate_reviews WHERE candidate_id=? AND status='OPEN' ORDER BY id",
                       [(int) $candidateId]) ?: [];''',
  '''        return ops_all("SELECT cr.* FROM candidate_reviews cr JOIN candidates c ON c.id=cr.candidate_id
                        WHERE cr.status='OPEN' AND c.person_ref<>'' AND c.person_ref=(
                            SELECT person_ref FROM candidates WHERE id=?) ORDER BY cr.id",
                       [(int) $candidateId]) ?: [];'''),

 ("M4  an issued-offer candidate is flagged anyway", "lib/candreview.php",
  "        if (crev_is_pinned($entity, $entityId, $cid, $newVersion)) { $out['pinned'][] = $c; continue; }",
  "        if (false) { $out['pinned'][] = $c; continue; }"),

 ("M5  a draft-offer candidate is excluded", "lib/candreview.php",
  "function crev_is_pinned($entity, $entityId, $candidateId, $newVersion) {",
  """function crev_is_pinned($entity, $entityId, $candidateId, $newVersion) {
    try { if ((int) ops_val("SELECT COUNT(*) FROM job_offers WHERE candidate_id=?", [(int) $candidateId]) > 0) return true; }
    catch (Throwable $e) {}"""),

 ("M6  a review can be cleared without the permission", "lib/candreview.php",
  "    if (!function_exists('can') || !can(CREV_PERM_CLEAR)) return false;\n    return crev_scope_ok($cand);",
  "    return true;"),

 ("M7  continue accepts no reason", "lib/candreview.php",
  "    $why = crev_reason_ok($reason);\n    if ($why === '') return [false, 'Say why this candidate still meets the changed requirement. A reason is required.'];",
  "    $why = (string) $reason;"),

 ("M8  reject accepts no reason", "lib/candreview.php",
  "    $why = crev_reason_ok($reason);\n    if ($why === '') return [false, 'Say why this candidate no longer meets the requirement. A reason is required.'];",
  "    $why = (string) $reason;"),

 ("M9  the execution gate stops asking", "lib/recruit_exec.php",
  "    if ($aOk && (int) $actor > 0 && function_exists('crev_block_reason')) {",
  "    if (false && $aOk && (int) $actor > 0 && function_exists('crev_block_reason')) {"),

 ("M10 an offer may be made past an open review", "lib/candreview.php",
  "    if (crev_allows_screening() && !in_array($a, CREV_NEVER_ALLOWED, true)) return '';",
  "    if ($a === 'OFFER') return '';\n    if (crev_allows_screening() && !in_array($a, CREV_NEVER_ALLOWED, true)) return '';"),

 ("M11 a joining may be recorded past an open review", "lib/candreview.php",
  "    if (crev_allows_screening() && !in_array($a, CREV_NEVER_ALLOWED, true)) return '';",
  "    if ($a === 'JOIN') return '';\n    if (crev_allows_screening() && !in_array($a, CREV_NEVER_ALLOWED, true)) return '';"),

 ("M12 clearing one relationship clears the person's others", "lib/candreview.php",
  """                             WHERE id=? AND status='OPEN'");
        $st->execute([crev_who(), crev_who_id(), crev_now(), $why, (int) $reviewId]);
        if ($st->rowCount() < 1) return [false, 'That review was decided by somebody else a moment ago.'];
    } catch (Throwable $e) { return [false, 'The review could not be decided, so nothing was changed.']; }

    crev_log((int) $r['candidate_id'], 'Requirement review cleared — continuing', ['remark' => $why]);""",
  """                             WHERE status='OPEN' AND candidate_id IN (
                                 SELECT id FROM candidates WHERE person_ref<>'' AND person_ref=(
                                     SELECT person_ref FROM candidates WHERE id=?))");
        $st->execute([crev_who(), crev_who_id(), crev_now(), $why, (int) $r['candidate_id']]);
        if ($st->rowCount() < 1) return [false, 'That review was decided by somebody else a moment ago.'];
    } catch (Throwable $e) { return [false, 'The review could not be decided, so nothing was changed.']; }

    crev_log((int) $r['candidate_id'], 'Requirement review cleared — continuing', ['remark' => $why]);"""),

 ("M13 the wrong pair of versions is compared", "lib/reqversion.php",
  "        if ($ver > 1 && function_exists('crev_raise_for_version'))\n            $g3 = crev_raise_for_version($entity, $id, $ver - 1, $ver);",
  "        if ($ver > 1 && function_exists('crev_raise_for_version'))\n            $g3 = crev_raise_for_version($entity, $id, 1, $ver);"),

 ("M14 a relaxed requirement reopens rejected candidates", "lib/candreview.php",
  "    if ($kind === '') return $out;                  // §18 — relaxed, or nothing a candidate is judged by",
  """    if ($kind === '' && $s['is_relaxed']) {
        foreach (crev_processes($e, $id) as $__p)
            foreach (ops_all("SELECT id FROM candidates WHERE requisition_id=?", [$__p]) ?: [] as $__c)
                crev_reconsider((int) $__c['id'], 'Requirement relaxed — reopened automatically.');
        return $out;
    }
    if ($kind === '') return $out;                  // §18 — relaxed, or nothing a candidate is judged by"""),

 ("M15 reconsideration returns them to the first stage", "lib/candreview.php",
  "    $prev = crev_previous_stage($cid);\n    if (!$prev || empty($prev['stage']))",
  """    $prev = crev_previous_stage($cid);
    if (function_exists('recruitpipe_cand_state')) {
        [$__p, $__e, ] = recruitpipe_cand_state($cand);
        if ($__e) $prev = ['stage' => $__e[0], 'track' => 'PIPELINE',
                           'label' => (string) $__e[0]['name'], 'code' => (string) $__e[0]['stage_key']];
    }
    if (!$prev || empty($prev['stage']))"""),

 ("M16 reconsideration erases the rejection", "lib/candreview.php",
  "    //  THE RECONSIDERATION IS ITS OWN LEDGER ENTRY, carrying everything §19 asks",
  """    try { db()->prepare("DELETE FROM candidate_events WHERE candidate_id=?")->execute([$cid]); }
    catch (Throwable $e) {}
    //  THE RECONSIDERATION IS ITS OWN LEDGER ENTRY, carrying everything §19 asks"""),

 ("M17 a strong candidate is cleared automatically", "lib/candreview.php",
  "            $out['raised']++;\n            crev_log($cid, 'Review required",
  """            $out['raised']++;
            $__ev = crev_evidence($c, $e, $id);
            $__all = true; foreach ($__ev['rows'] as $__r) if ($__r['meets'] === false) $__all = false;
            if ($__all) { try { db()->prepare("UPDATE candidate_reviews SET status='CONTINUED', open_key=NULL
                                WHERE candidate_id=? AND status='OPEN'")->execute([$cid]); } catch (Throwable $e9) {} }
            crev_log($cid, 'Review required"""),

 ("M18 a weak candidate is rejected automatically", "lib/candreview.php",
  "            $out['raised']++;\n            crev_log($cid, 'Review required",
  """            $out['raised']++;
            $__ev = crev_evidence($c, $e, $id);
            foreach ($__ev['rows'] as $__r) if ($__r['meets'] === false) {
                try { db()->prepare("UPDATE candidate_reviews SET status='REJECTED', open_key=NULL
                      WHERE candidate_id=? AND status='OPEN'")->execute([$cid]); } catch (Throwable $e9) {}
                break;
            }
            crev_log($cid, 'Review required"""),

 ("M19 the audit entry is not written", "lib/candreview.php",
  "function crev_log($candidateId, $subject, array $o = []) {",
  "function crev_log($candidateId, $subject, array $o = []) {\n    if (true) return true;"),

 ("M20 a review is cached across tenants", "lib/candreview.php",
  '''function crev_review($id) {
    crev_migrate();
    try { return ops_one("SELECT * FROM candidate_reviews WHERE id=?", [(int) $id]) ?: null; }
    catch (Throwable $e) { return null; }''',
  '''function crev_review($id) {
    crev_migrate();
    static $cache = [];
    if (isset($cache[(int) $id])) return $cache[(int) $id];
    try { $r = ops_one("SELECT * FROM candidate_reviews WHERE id=?", [(int) $id]) ?: null;
          if ($r) $cache[(int) $id] = $r; return $r; }
    catch (Throwable $e) { return null; }'''),

 ("M21 the recruitment scope check is removed", "lib/candreview.php",
  "    if (!function_exists('scope_allows')) return true;\n    return (bool) scope_allows((int) ($r['office_id'] ?? 0), $r['sbu'] ?? null);",
  "    return true;"),

 ("M22 the version stamped at issue is ignored", "lib/candreview.php",
  "    $v = (int) rver_applicable_version($entity, $entityId, (int) $candidateId);\n    return $v > 0 && (int) $newVersion > 0 && $v < (int) $newVersion;",
  "    $cur = rver_current($entity, $entityId);\n    $v = $cur ? (int) $cur['version'] : 0;\n    return $v > 0 && (int) $newVersion > 0 && $v < (int) $newVersion;"),

 ("M23 two concurrent resolutions both succeed", "lib/candreview.php",
  """                                 resolved_at=?, resolve_reason=?
                             WHERE id=? AND status='OPEN'");
        $st->execute([crev_who(), crev_who_id(), crev_now(), $why, (int) $reviewId]);
        if ($st->rowCount() < 1) return [false, 'That review was decided by somebody else a moment ago.'];""",
  """                                 resolved_at=?, resolve_reason=?
                             WHERE id=?");
        $st->execute([crev_who(), crev_who_id(), crev_now(), $why, (int) $reviewId]);"""),

 ("M24 the configured closed stage is bypassed", "lib/candreview.php",
  "        if ($target) {\n            db()->prepare(\"UPDATE candidates SET pipeline_id=?, pipeline_stage_id=?, decided_at=?,",
  "        if (false) {\n            db()->prepare(\"UPDATE candidates SET pipeline_id=?, pipeline_stage_id=?, decided_at=?,"),
]

def run_battery(env):
    p = subprocess.run([sys.executable and 'php', 'tests/run.php', 'gate3'],
                       cwd=ROOT, capture_output=True, text=True, env=env)
    out = p.stdout + p.stderr
    if 'Fatal error' in out or 'Parse error' in out:
        return -1, out            # a mutation that cannot even run is still caught
    for line in out.splitlines():
        if line.startswith('RESULT:'):
            return int(line.split('passed')[0].split(':')[1].strip().split()[0]) * 0 + \
                   int(line.split(',')[1].strip().split()[0]), out
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
            print('  ?? %-58s ANCHOR MATCHED %d TIMES — not applied' % (name, original.count(find)))
            survived.append(name + ' (anchor)')
            continue
        io.open(path, 'w', encoding='utf-8').write(original.replace(find, repl, 1))
        try:
            fails, out = run_battery(env)
        finally:
            io.open(path, 'w', encoding='utf-8').write(original)
        if fails == 0:
            print('  SURVIVED  %-58s 0 failures' % name)
            survived.append(name)
        elif fails < 0:
            print('  killed    %-58s could not run (broken by its own damage)' % name)
            killed += 1
        else:
            print('  killed    %-58s %d assertion(s) failed' % (name, fails))
            killed += 1
    print('\n%d killed / %d total' % (killed, len(MUTATIONS)))
    if survived:
        print('SURVIVORS:'); [print('  - ' + s) for s in survived]
        sys.exit(1)
    print('ALL MUTATIONS KILLED')

main()
