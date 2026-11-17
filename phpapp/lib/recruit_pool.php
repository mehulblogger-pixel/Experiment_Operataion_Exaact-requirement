<?php
// ============================================================================
//  EXAACT — THE COMPANY'S OWN CANDIDATE POOL, SHARED BY EVERY OFFICE
//  (branch batch B6, owner's answers of 16 Nov 2026; LOCK RULE rewritten in
//  batch 2 to the owner's rule of 17 Nov 2026 — QA-REPORTS/V51/29 section C,
//  points 7, 8, 9 and Q2)
//
//  The candidate pool is the company's OWN internal pool — CVs received
//  directly, from consultants, from HR and so on. It is NOT the Marketplace,
//  which is a separate paid module and is not touched here.
//
//  The owner, 17 Nov 2026:
//    "When a candidate is in the pool and no recruitment process has been
//     started by any branch, everything of the candidate is shown to every
//     office. Once the process starts by any office (interview), a badge shall
//     be shown with the candidate as locked, with no access to any other office."
//    Q2: the lock starts at the FIRST INTERVIEW SCHEDULED, not when the
//    candidate is merely linked to a requisition.
//
//  The rules, in the order this file answers them:
//
//   1 · No branch owns a candidate. With the Master Admin switch "Offices can
//       see each other's candidate pool" ON (the default) every office sees
//       every candidate who has not joined — in the candidate pool, a
//       requisition's "Best matches from the pool", the register and search.
//       OFF = each office sees only its own candidates (worked against one of
//       its requisitions) plus the unattached ones, which belong to nobody.
//
//   2 · BEFORE THE LOCK — the candidate is linked to an office's requisition
//       (or to none) and no interview has been scheduled. Every office may open
//       the FULL candidate: page, CV, documents (salary still only for
//       `data.salary`, the Business Unit rule as for any viewer). Another office
//       may TAKE THEM ON: link them to one of ITS OWN requisitions (Edit →
//       Requisition) and schedule its own interview there. Nothing else is
//       written against the other office's requisition. The first office to
//       schedule an interview takes the lock.
//
//   3 · THE LOCK — from the first interview scheduled (an interview on the
//       Interviews panel that is not cancelled, scheduled against a requisition
//       of the office that now has them; or the candidate standing at an
//       interview, offer or later stage) through offer, onboarding and joining,
//       until the process ends. The office holding the lock = the office of the
//       requisition the candidate is worked against. Every office sees the badge
//       "Locked — in recruitment with <office>" / "Locked — onboarding with
//       <office>". Any other office sees ONLY the name and the badge in every
//       list, and cannot open the candidate page or use any candidate route —
//       refused, naming the office. Company-wide roles (Master Admin, Admin,
//       Business Director …) cover every office and are never blocked.
//       The lock is the PERSON's: a second record of the same person (same
//       mobile or e-mail) is locked with them, and a new second record cannot be
//       created by another office while they are locked (point 8).
//
//   4 · WHEN THE PROCESS ENDS (rejected / withdrawn / offer declined / did not
//       join / dropped) the lock is lifted and the candidate is back in every
//       office's pool and "Best matches" (point 9). A person who has JOINED is
//       an employee of that office, not a pool candidate.
//
//  Nothing here is a new status or a new permission: the lock is READ from the
//  existing classification (reqf_classify(): ACTIVE / FILLED / LOST), the stage
//  kind, the interviews table and the joined_at column. It only ever says no.
// ============================================================================

const RPOOL_SHARED_KEY = 'recruit_pool_shared';

//  Is the pool shared across offices? ON unless the Master Admin turned it off.
function rpool_shared() {
    $v = function_exists('setting_get') ? setting_get(RPOOL_SHARED_KEY, '1') : '1';
    return trim((string) $v) !== '0';
}

//  The Master Admin switch. Only a Master Admin may change it; anybody else is
//  refused and nothing is written. setting_set() puts the change on the audit
//  trail (who, when, from what to what).
function rpool_set_shared($on) {
    if (!function_exists('is_master') || !is_master()) return false;
    setting_set(RPOOL_SHARED_KEY, $on ? '1' : '0');
    if (function_exists('act_log')) {
        try { act_log('SETTINGS', 0, 'SETUP', "Offices can see each other's candidate pool → " . ($on ? 'On' : 'Off')); }
        catch (Throwable $e) {}
    }
    return true;
}

//  The candidate row, whichever way the caller holds it. A list row carries only
//  some columns, so anything without the columns this file reads is reloaded.
function rpool_cand($cand) {
    if (is_array($cand)) {
        if (empty($cand['id'])) return null;
        foreach (['requisition_id', 'stage', 'pipeline_stage_id', 'joined_at', 'sbu', 'first_name', 'mobile', 'email'] as $k)
            if (!array_key_exists($k, $cand)) { $cand = (int) $cand['id']; break; }
        if (is_array($cand)) return $cand;
    }
    $id = (int) $cand; if ($id <= 0) return null;
    try { return ops_one("SELECT * FROM candidates WHERE id=?", [$id]) ?: null; }
    catch (Throwable $e) { return null; }
}

function rpool_req($reqId) {
    $reqId = (int) $reqId; if ($reqId <= 0) return null;
    try {
        return ops_one("SELECT r.id, r.req_code, r.office_id, r.sbu, o.name office_name
                        FROM requisitions r LEFT JOIN offices o ON o.id=r.office_id WHERE r.id=?", [$reqId]) ?: null;
    } catch (Throwable $e) { return null; }
}

function rpool_name($c) {
    if (function_exists('candidate_name')) return candidate_name($c);
    $n = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
    return $n !== '' ? $n : 'This person';
}

//  The person's keys: the last ten digits of the mobile, and the e-mail in
//  lower case. The same normalisation person_applications() uses.
function rpool_person_keys($c) {
    $keys = [];
    $mob = preg_replace('/\D+/', '', (string) ($c['mobile'] ?? ''));
    if (strlen($mob) >= 10) $keys[] = 'm:' . substr($mob, -10);
    $em = strtolower(trim((string) ($c['email'] ?? '')));
    if ($em !== '') $keys[] = 'e:' . $em;
    return $keys;
}

//  ============================================================================
//  WHICH RECORDS ARE LOCKED — one SQL definition, used for one candidate, for a
//  person's other records and for a whole list.
//
//  A record is locked when it is worked against a requisition, its process has
//  not ended (classification ≠ LOST), and the process has STARTED:
//    · it stands at an interview, offer or final stage kind (legacy INTERVIEW /
//      OFFERED / ACCEPTED translate to those kinds), or — on a configured
//      pipeline — at or after its first interview / offer / final stage; or
//    · an interview that is not cancelled was scheduled against this
//      requisition, or against another requisition of the same office.
//  ============================================================================
function rpool_lock_rows($extraWhere = '1=1', array $args = []) {
    if (!function_exists('reqf_class_join') || !function_exists('reqf_class_expr') || !function_exists('reqf_kind_expr')) return [];
    if (function_exists('recruit_iv_migrate')) recruit_iv_migrate();
    $KJ = reqf_class_join('c'); $KE = reqf_class_expr('c'); $KK = reqf_kind_expr('c');
    $started = "($KK IN ('interview','offer','terminal')
        OR (rs_k.id IS NOT NULL AND rs_k.seq >= COALESCE((SELECT MIN(s2.seq) FROM recruit_stages s2
              WHERE s2.pipeline_id = rs_k.pipeline_id AND s2.active = 1 AND s2.kind IN ('interview','offer','terminal')), 999999))
        OR EXISTS (SELECT 1 FROM interviews iv LEFT JOIN requisitions ir ON ir.id = iv.requisition_id
              WHERE iv.candidate_id = c.id AND COALESCE(iv.result,'') <> 'CANCELLED'
                AND (iv.requisition_id = c.requisition_id OR (ir.office_id IS NOT NULL AND ir.office_id = r.office_id))))";
    try {
        return ops_all("SELECT c.*, ($KE) lk_class, r.office_id lk_office_id, r.req_code lk_req_code, o.name lk_office
                        FROM candidates c JOIN requisitions r ON r.id = c.requisition_id
                        LEFT JOIN offices o ON o.id = r.office_id $KJ
                        WHERE ($KE) <> 'LOST' AND $started AND ($extraWhere)
                        ORDER BY c.id", $args) ?: [];
    } catch (Throwable $e) { return []; }
}

//  One locked row → the lock, in the words the screens use.
//    kind       RECRUITMENT | ONBOARDING | JOINED
//    office_id  the office of the requisition they are worked against (0 = none set)
//    office     its name
//    stage      the stage they are at, in the screen's words
function rpool_lock_info(array $row) {
    $class = (string) ($row['lk_class'] ?? '');
    $joined = trim((string) ($row['joined_at'] ?? '')) !== '';
    $kind = $class === 'FILLED' ? ($joined ? 'JOINED' : 'ONBOARDING') : 'RECRUITMENT';
    $state = function_exists('rpipe_current_state') ? rpipe_current_state($row) : null;
    $stage = $state ? trim((string) ($state['stage_name'] ?? '')) : '';
    if ($stage === '') {
        $legacy = (string) ($row['stage'] ?? '');
        $labels = function_exists('lk_options_or') && defined('CAND_STAGES') ? lk_options_or('candidate_stage', CAND_STAGES) : [];
        $stage = (string) ($labels[$legacy] ?? $legacy);
    }
    return ['kind' => $kind, 'office_id' => (int) ($row['lk_office_id'] ?? 0),
            'office' => trim((string) ($row['lk_office'] ?? '')), 'stage' => $stage,
            'requisition_id' => (int) ($row['requisition_id'] ?? 0), 'req_code' => (string) ($row['lk_req_code'] ?? ''),
            'candidate_id' => (int) ($row['id'] ?? 0), 'name' => rpool_name($row)];
}

//  Every lock at once, for a list: one query, then looked up per row.
//    ['id'  => [candidate id => lock], 'key' => [person key => lock]]
//  A JOINED record is that office's employee, not a running process, so it does
//  not lock the person's OTHER records.
function rpool_lock_map() {
    $map = ['id' => [], 'key' => []];
    foreach (rpool_lock_rows() as $row) {
        $l = rpool_lock_info($row);
        $map['id'][(int) $row['id']] = $l;
        if ($l['kind'] === 'JOINED') continue;
        foreach (rpool_person_keys($row) as $k) if (!isset($map['key'][$k])) $map['key'][$k] = $l;
    }
    return $map;
}

//  WHERE IS THIS RECORD'S OWN PROCESS, AND WITH WHOM? null = not locked: the
//  record is unattached, no interview has been scheduled yet, or the process
//  ended. Otherwise the lock (see rpool_lock_info()).
function cand_process($cand) {
    $c = rpool_cand($cand);
    if (!$c) return null;
    if ((int) ($c['requisition_id'] ?? 0) <= 0) return null;
    $rows = rpool_lock_rows('c.id = ?', [(int) $c['id']]);
    return $rows ? rpool_lock_info($rows[0]) : null;
}

//  THE PERSON'S LOCK — this record's own, else that of another record of the
//  same person (same mobile or e-mail) whose process is running. $map, when a
//  list passes rpool_lock_map(), answers without a query per row.
function cand_lock($cand, $map = null) {
    $c = rpool_cand($cand);
    if (!$c) return null;
    $id = (int) $c['id'];
    if (is_array($map)) {
        if (isset($map['id'][$id])) return $map['id'][$id];
        foreach (rpool_person_keys($c) as $k)
            if (isset($map['key'][$k]) && (int) $map['key'][$k]['candidate_id'] !== $id) return $map['key'][$k];
        return null;
    }
    $own = cand_process($c);
    if ($own) return $own;
    return rpool_person_lock($c, $id);
}

//  The running process of another record that shares one of these keys, or null.
//  $exceptId = the record asking (0 = a record not yet created).
function rpool_person_lock($c, $exceptId = 0) {
    $keys = rpool_person_keys($c);
    if (!$keys) return null;
    $or = []; $args = [(int) $exceptId];
    foreach ($keys as $k) {
        if (strpos($k, 'm:') === 0) {
            //  A loose LIKE finds the candidates; the exact key is compared below.
            $or[] = "c.mobile LIKE ?"; $args[] = '%' . implode('%', str_split(substr($k, 2))) . '%';
        } else { $or[] = "LOWER(TRIM(COALESCE(c.email,''))) = ?"; $args[] = substr($k, 2); }
    }
    foreach (rpool_lock_rows('c.id <> ? AND (' . implode(' OR ', $or) . ')', $args) as $row) {
        if (!array_intersect($keys, rpool_person_keys($row))) continue;
        $l = rpool_lock_info($row);
        if ($l['kind'] !== 'JOINED') return $l;
    }
    return null;
}

//  The badge words for a lock. '' for a person who has joined — they are then an
//  employee, no longer a pool candidate.
function cand_lock_label($lock) {
    if (!$lock || ($lock['kind'] ?? '') === 'JOINED') return '';
    $at = ($lock['office'] ?? '') !== '' ? $lock['office'] : 'another office';
    return $lock['kind'] === 'ONBOARDING' ? 'Locked — onboarding with ' . $at : 'Locked — in recruitment with ' . $at;
}

//  The badge every office sees (the locking office too, so it knows the others
//  are kept out). '' when not locked.
function cand_process_badge($cand, $map = null) {
    return cand_lock_label(cand_lock($cand, $map));
}

//  The same badge as a pill, escaped, for the screens.
function cand_process_pill($cand, $map = null) {
    $b = is_string($cand) ? $cand : cand_process_badge($cand, $map);
    if ($b === '') return '';
    return '<span class="pill p-warn" style="font-size:11px;white-space:normal" title="'
         . htmlspecialchars('Only that office can open or work on this ' . (function_exists('Tl') ? Tl('candidate') : 'candidate') . ' until their process ends.', ENT_QUOTES)
         . '">🔒 ' . htmlspecialchars($b, ENT_QUOTES) . '</span>';
}

//  May the signed-in person's office scope work on a process run by this office?
function rpool_covers_office($officeId) {
    $officeId = (int) $officeId;
    if ($officeId <= 0) return true;               // no office named → nobody's to defend
    return !function_exists('scope_office_allows') || scope_office_allows($officeId);
}

//  The lock, ONLY when it keeps the signed-in person out (their scope does not
//  cover the office holding it). null = they may see this candidate in full.
function cand_locked_for_me($cand, $map = null) {
    $l = cand_lock($cand, $map);
    return ($l && $l['office_id'] > 0 && !rpool_covers_office($l['office_id'])) ? $l : null;
}

//  The refusal for a locked candidate, in the owner's words. '' = not locked for
//  this person.
function cand_lock_block($cand, $map = null) {
    $c = rpool_cand($cand);
    if (!$c) return '';
    $l = cand_locked_for_me($c, $map);
    if (!$l) return '';
    $T = function_exists('Tl') ? Tl('candidate') : 'candidate';
    $who = rpool_name($c); $at = $l['office'] !== '' ? $l['office'] : 'another';
    if ($l['kind'] === 'JOINED')
        return "$who has joined the $at office — only that office can open or work on this $T.";
    $what = $l['kind'] === 'ONBOARDING' ? 'onboarding' : 'in recruitment';
    return "$who is locked — $what with the $at office. Only that office can open or work on this $T until their process ends.";
}

//  What the first interview did for the other offices, for the "Interview
//  scheduled." message: ' Other offices now see …' or ''.
function cand_lock_note($cand) {
    $l = cand_process($cand);
    if (!$l || $l['office'] === '' || $l['kind'] === 'JOINED') return '';
    return ' Other offices now see this ' . (function_exists('Tl') ? Tl('candidate') : 'candidate')
         . ' as "' . cand_lock_label($l) . '" until their process ends.';
}

//  The route form: a locked candidate cannot be opened, read or worked on by
//  another office — by any method, on any candidate route.
function cand_lock_require($cand) {
    $why = cand_lock_block($cand);
    if ($why !== '' && function_exists('ops_require')) ops_require(false, $why);
    return $why;
}

//  ============================================================================
//  THE GUARD — asked by every write path on a candidate.
//
//  Returns '' when the signed-in person may work on this candidate now, or the
//  refusal in the words the person on the screen needs.
//
//  $opt['to_requisition']  the requisition this request links the candidate TO
//  $opt['form']            true = only opening the edit form (no write yet)
//  $opt['process_only']    true = ask only the lock question (for callers that
//                          already ask the branch-scope question themselves)
//  ============================================================================
function cand_other_office_block($cand, array $opt = []) {
    $c = rpool_cand($cand);
    if (!$c) return '';
    $T = function_exists('Tl') ? Tl('candidate') : 'candidate';

    //  1 · THE LOCK — the process is with an office this person does not cover.
    $why = cand_lock_block($c);
    if ($why !== '') return $why;
    if (!empty($opt['process_only'])) return '';

    //  2 · THE CANDIDATE'S OWN REQUISITION is outside this person's branch scope —
    //  the rule cand_scope_gate() has always applied. Under the shared pool a
    //  candidate who is NOT locked (no interview yet, or their process ended) is
    //  open to every office: they may be opened in full, and another office
    //  starts with them by linking them to one of ITS OWN requisitions. Nothing
    //  else is written against another office's requisition.
    $req = rpool_req($c['requisition_id'] ?? 0);
    if ($req && function_exists('scope_allows') && !scope_allows($req['office_id'] ?? null, $req['sbu'] ?? null)) {
        $sbuOk = !function_exists('scope_sbu_allows') || scope_sbu_allows((string) ($c['sbu'] ?? ''));
        if (rpool_shared() && $sbuOk) {
            if (!empty($opt['form'])) return '';
            $to = (int) ($opt['to_requisition'] ?? 0);
            $dest = ($to > 0 && $to !== (int) $req['id']) ? rpool_req($to) : null;
            if ($dest && scope_allows($dest['office_id'] ?? null, $dest['sbu'] ?? null)) return '';
            $R = function_exists('Tl') ? Tl('requisition') : 'requisition';
            $Rs = function_exists('Tlp') ? Tlp('requisition') : $R . 's';
            $at = trim((string) ($req['office_name'] ?? '')) !== '' ? 'the ' . $req['office_name'] . ' office' : 'another office';
            $ended = function_exists('reqf_classify') && reqf_classify($c) === 'LOST';
            $how = " To start with them, link them to one of your own office's $Rs first (Edit → " . ucfirst($R) . ").";
            return $ended
                ? rpool_name($c) . " was last worked by $at and is back in the pool." . $how
                : rpool_name($c) . " is on a $R of $at, and no interview has been scheduled yet, so they are open to every office." . $how
                  . " The first office to schedule an interview takes the lock.";
        }
        return 'This ' . $T . ' belongs to a ' . (function_exists('Tl') ? Tl('requisition') : 'requisition')
             . ' outside your office / branch scope.';
    }
    return '';
}

//  The route form of the guard: refuse, with the reason, and stop.
function cand_other_office_require($cand, array $opt = []) {
    $why = cand_other_office_block($cand, $opt);
    if ($why !== '' && function_exists('ops_require')) ops_require(false, $why);
    return $why;
}

//  MAY THIS PERSON OPEN (READ) THIS CANDIDATE?
//    · never while they are locked with an office this person does not cover;
//    · their own office's candidate, or an unattached one — as always;
//    · under the shared pool, also any other office's candidate who is not
//      locked (no interview yet, or back in the pool), within their own
//      Business Unit — in full.
function cand_pool_may_view($cand) {
    $c = rpool_cand($cand);
    if (!$c) return false;
    if (cand_locked_for_me($c)) return false;
    $req = rpool_req($c['requisition_id'] ?? 0);
    if (!$req || !function_exists('scope_allows') || scope_allows($req['office_id'] ?? null, $req['sbu'] ?? null)) return true;
    if (!rpool_shared()) return false;
    if (function_exists('scope_sbu_allows') && !scope_sbu_allows((string) ($c['sbu'] ?? ''))) return false;
    return true;
}

//  One search result for a candidate locked with an office the signed-in person
//  does not cover: the name and the badge, and no link. null = show it in full.
function rpool_search_locked_row(array $r, $map = null) {
    $lk = cand_locked_for_me($r, $map);
    if (!$lk) return null;
    $T = function_exists('Tl') ? Tl('candidate') : 'candidate';
    return ['title'    => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: '—',
            'subtitle' => '🔒 ' . (cand_lock_label($lk) ?: ('Joined — ' . ($lk['office'] ?: 'another office'))),
            'meta'     => "Only that office can open this $T until their process ends.",
            'url'      => '', 'dim' => true];
}

//  POINT 8 — a second record for the same person (same mobile or e-mail) while
//  they are LOCKED with an office the person creating it does not cover is
//  refused, naming the office. '' = may create (the soft duplicate warning still
//  applies to look-alikes that are not locked).
function cand_duplicate_lock_block(array $b) {
    $l = rpool_person_lock($b, 0);
    if (!$l || $l['office_id'] <= 0 || rpool_covers_office($l['office_id'])) return '';
    $T = function_exists('Tl') ? Tl('candidate') : 'candidate';
    $what = $l['kind'] === 'ONBOARDING' ? 'onboarding' : 'in recruitment';
    $at = $l['office'] !== '' ? $l['office'] : 'another';
    return 'This person is already ' . $what . ' with the ' . $at . ' office (' . $l['name'] . ', same mobile or e-mail) and is locked. '
         . 'A second ' . $T . ' record cannot be created until their process there ends — speak to the ' . $at . ' office.';
}

//  THE POOL AS A LIST (SQL) — for the candidate pool screen, "Best matches from
//  the pool", search and the re-approach list. The query must carry
//  reqf_class_join($alias). Locked candidates stay IN the list; the screen shows
//  another office only their name and the badge (cand_locked_for_me()).
//
//    OFF → the office's own candidates (rasg_cand_scope(): through the
//          requisition, unattached visible to all, plus the Business Unit rule)
//    ON  → those, plus every other candidate in the person's Business Unit who
//          has not joined.
function rpool_list_clause($alias = 'c') {
    [$own, $oa] = function_exists('rasg_cand_scope') ? rasg_cand_scope($alias) : ['1=1', []];
    if ($own === '1=1' || !rpool_shared()) return [$own, $oa];
    [$sw, $sa] = function_exists('recruit_sbu_clause') ? recruit_sbu_clause("$alias.sbu") : ['1=1', []];
    $class = function_exists('reqf_class_expr') ? reqf_class_expr($alias) : "''";
    $notJoined = "NOT ($class = 'FILLED' AND COALESCE($alias.joined_at,'') <> '')";
    return ["(($own) OR (($sw) AND $notJoined))", array_merge($oa, $sa)];
}

//  The Master Admin switch, drawn on the Hiring workflows setup screen. Shown
//  only to a Master Admin; the save is refused to anybody else.
function rpool_switch_panel() {
    if (!function_exists('is_master') || !is_master()) return;
    $on = rpool_shared();
    $T = function_exists('Tl') ? Tl('candidate') : 'candidate';
    ?>
    <div class="rp-card" style="margin-bottom:14px">
      <h2>Candidate pool across offices</h2>
      <div class="rp-body">
        <form method="post" action="/recruit-pipelines" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <input type="hidden" name="do" value="pool_shared">
          <label style="margin:0;display:flex;gap:8px;align-items:center;font-size:13px">
            Offices can see each other's <?= htmlspecialchars($T, ENT_QUOTES) ?> pool
            <select name="pool_shared" style="width:auto">
              <option value="1"<?= $on ? ' selected' : '' ?>>On — every office sees every <?= htmlspecialchars($T, ENT_QUOTES) ?> who has not joined</option>
              <option value="0"<?= $on ? '' : ' selected' ?>>Off — each office sees only its own</option>
            </select>
          </label>
          <button class="rp-btn sm" type="submit">Save</button>
        </form>
        <div class="rp-note" style="margin-top:8px">Either way, once an office schedules the first interview, the <?= htmlspecialchars($T, ENT_QUOTES) ?> is locked with that office until their process ends — every other office sees only the name and “Locked — in recruitment with office”.</div>
      </div>
    </div>
    <?php
}
