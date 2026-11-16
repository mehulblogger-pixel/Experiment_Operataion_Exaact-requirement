<?php
// ============================================================================
//  EXAACT — THE COMPANY'S OWN CANDIDATE POOL, SHARED BY EVERY OFFICE
//  (branch batch B6, owner's final answers of 16 Nov 2026)
//
//  The candidate pool is the company's OWN internal pool — CVs received
//  directly, from consultants, from HR and so on. It is NOT the Marketplace,
//  which is a separate paid module and is not touched here.
//
//  The owner's rules, in the order this file answers them:
//
//   1 · No branch owns a candidate. Every office sees every candidate who has
//       not yet joined — in the candidate pool and in a requisition's "Best
//       matches from the pool" — unless the Master Admin switches that off.
//       The switch is "Offices can see each other's candidate pool", ON by
//       default. OFF = each office sees only its own candidates: the ones
//       worked against one of its requisitions, plus the unattached ones,
//       which belong to nobody and so stay visible to everybody (the same
//       "no office means everybody" rule rasg_cand_scope() already applies).
//
//   2 · The in-process badge. Once a candidate's process has started with an
//       office — linked to that office's requisition and not closed out —
//       every office sees "Recruitment in process — <office> (<stage>)"; once
//       the offer is accepted and joining is awaited, "Onboarding in process —
//       <office>". Nothing here is a new status: it is READ from the existing
//       classification (reqf_classify(): ACTIVE / FILLED / LOST) and the
//       existing joined_at column.
//
//   3 · The hard block. No other office may start ANYTHING with a candidate
//       whose recruitment or onboarding is in process with another office —
//       link them to a requisition, schedule an interview, draft an offer,
//       move a stage, add a document. The refusal names the office.
//       "Another office" = the person's office scope does not cover the office
//       running the process; company-wide roles (Master Admin, Admin, Business
//       Director …) cover every office and are never blocked.
//
//   4 · When the process ends (rejected / withdrawn / offer declined) the
//       candidate is back in the pool for everyone: another office may open
//       them, and starts by linking them to one of ITS OWN requisitions.
//
//  ONE guard, cand_other_office_block(), is asked by every write path. It adds
//  no permission: it only ever says no.
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
        foreach (['requisition_id', 'stage', 'pipeline_stage_id', 'joined_at', 'sbu', 'first_name'] as $k)
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

//  WHERE IS THIS CANDIDATE'S PROCESS, AND WITH WHOM?
//
//  null — no process is running: the candidate is unattached, or their process
//  ended (LOST). Otherwise:
//    kind       RECRUITMENT | ONBOARDING | JOINED
//    office_id  the office of the requisition they are worked against (0 = none set)
//    office     its name
//    stage      the stage they are at, in the screen's words
//
//  An unclassifiable candidate on a requisition is treated as in recruitment:
//  "we could not tell" must never open a candidate to a second office.
function cand_process($cand) {
    $c = rpool_cand($cand);
    if (!$c) return null;
    $req = rpool_req($c['requisition_id'] ?? 0);
    if (!$req) return null;
    $state = function_exists('rpipe_current_state') ? rpipe_current_state($c) : null;
    $class = $state ? (string) $state['class'] : (function_exists('reqf_classify') ? (string) reqf_classify($c) : '');
    if ($class === 'LOST') return null;
    $joined = trim((string) ($c['joined_at'] ?? '')) !== '';
    $kind = $class === 'FILLED' ? ($joined ? 'JOINED' : 'ONBOARDING') : 'RECRUITMENT';
    $stage = $state ? trim((string) ($state['stage_name'] ?? '')) : '';
    if ($stage === '') {
        $legacy = (string) ($c['stage'] ?? '');
        $labels = function_exists('lk_options_or') && defined('CAND_STAGES') ? lk_options_or('candidate_stage', CAND_STAGES) : [];
        $stage = (string) ($labels[$legacy] ?? $legacy);
    }
    return ['kind' => $kind, 'office_id' => (int) ($req['office_id'] ?? 0),
            'office' => trim((string) ($req['office_name'] ?? '')), 'stage' => $stage,
            'requisition_id' => (int) $req['id'], 'req_code' => (string) ($req['req_code'] ?? '')];
}

//  The badge every office sees. '' when no process is running (or the person has
//  joined — they are then an employee, no longer a pool candidate).
function cand_process_badge($cand) {
    $p = cand_process($cand);
    if (!$p || $p['kind'] === 'JOINED') return '';
    $at = $p['office'] !== '' ? ' — ' . $p['office'] : '';
    if ($p['kind'] === 'ONBOARDING') return 'Onboarding in process' . $at;
    return 'Recruitment in process' . $at . ($p['stage'] !== '' ? ' (' . $p['stage'] . ')' : '');
}

//  The same badge as a pill, escaped, for the screens.
function cand_process_pill($cand) {
    $b = cand_process_badge($cand);
    if ($b === '') return '';
    return '<span class="pill p-warn" style="font-size:11px;white-space:normal" title="'
         . htmlspecialchars('Only that office can work on this ' . (function_exists('Tl') ? Tl('candidate') : 'candidate') . ' until their process ends.', ENT_QUOTES)
         . '">🔒 ' . htmlspecialchars($b, ENT_QUOTES) . '</span>';
}

//  May the signed-in person's office scope work on a process run by this office?
function rpool_covers_office($officeId) {
    $officeId = (int) $officeId;
    if ($officeId <= 0) return true;               // no office named → nobody's to defend
    return !function_exists('scope_office_allows') || scope_office_allows($officeId);
}

//  ============================================================================
//  THE GUARD — asked by every write path on a candidate.
//
//  Returns '' when the signed-in person may work on this candidate now, or the
//  refusal in the words the person on the screen needs.
//
//  $opt['to_requisition']  the requisition this request links the candidate TO
//  $opt['form']            true = only opening the edit form (no write yet)
//  $opt['process_only']    true = ask only the in-process question (for callers
//                          that already ask the branch-scope question themselves)
//  ============================================================================
function cand_other_office_block($cand, array $opt = []) {
    $c = rpool_cand($cand);
    if (!$c) return '';
    $T = function_exists('Tl') ? Tl('candidate') : 'candidate';

    //  1 · THE HARD BLOCK — the process is with an office this person does not cover.
    $p = cand_process($c);
    if ($p && $p['office_id'] > 0 && !rpool_covers_office($p['office_id'])) {
        $who = rpool_name($c); $at = $p['office'] !== '' ? $p['office'] : 'another';
        if ($p['kind'] === 'JOINED')
            return "$who has joined the $at office — only that office can work on this $T.";
        $what = $p['kind'] === 'ONBOARDING' ? 'onboarding' : 'recruitment';
        return "$who is in $what with the $at office — only that office can work on this $T until their process ends.";
    }
    if (!empty($opt['process_only'])) return '';

    //  2 · THE CANDIDATE'S OWN REQUISITION is outside this person's branch scope —
    //  the rule cand_scope_gate() has always applied. Under the shared pool a
    //  candidate whose process has ENDED is back in the pool for everyone: they
    //  may be opened, and the way to start with them is to link them to one of
    //  your own office's requisitions. Nothing else is written against another
    //  office's requisition.
    $req = rpool_req($c['requisition_id'] ?? 0);
    if ($req && function_exists('scope_allows') && !scope_allows($req['office_id'] ?? null, $req['sbu'] ?? null)) {
        $sbuOk = !function_exists('scope_sbu_allows') || scope_sbu_allows((string) ($c['sbu'] ?? ''));
        if (rpool_shared() && $p === null && $sbuOk) {
            if (!empty($opt['form'])) return '';
            $to = (int) ($opt['to_requisition'] ?? 0);
            $dest = ($to > 0 && $to !== (int) $req['id']) ? rpool_req($to) : null;
            if ($dest && scope_allows($dest['office_id'] ?? null, $dest['sbu'] ?? null)) return '';
            $R = function_exists('Tl') ? Tl('requisition') : 'requisition';
            $at = trim((string) ($req['office_name'] ?? '')) !== '' ? 'the ' . $req['office_name'] . ' office' : 'another office';
            return rpool_name($c) . " was last worked by $at and is back in the pool. To start with them, "
                 . "link them to one of your own office's " . (function_exists('Tlp') ? Tlp('requisition') : $R . 's')
                 . " first (Edit → " . ucfirst($R) . ").";
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
//    · their own office's candidate, or an unattached one — as always;
//    · under the shared pool, also any other office's candidate who has not
//      joined (in process — the page shows the badge and every action is
//      refused — or back in the pool), within their own Business Unit.
function cand_pool_may_view($cand) {
    $c = rpool_cand($cand);
    if (!$c) return false;
    $req = rpool_req($c['requisition_id'] ?? 0);
    if (!$req || !function_exists('scope_allows') || scope_allows($req['office_id'] ?? null, $req['sbu'] ?? null)) return true;
    if (!rpool_shared()) return false;
    if (function_exists('scope_sbu_allows') && !scope_sbu_allows((string) ($c['sbu'] ?? ''))) return false;
    $p = cand_process($c);
    return $p === null || $p['kind'] !== 'JOINED';
}

//  THE POOL AS A LIST (SQL) — for the candidate pool screen, "Best matches from
//  the pool" and the re-approach list. The query must carry reqf_class_join($alias).
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
        <div class="rp-note" style="margin-top:8px">Either way, once an office has started with someone, only that office can work on them until their process ends — every other office sees “Recruitment in process — office”.</div>
      </div>
    </div>
    <?php
}
