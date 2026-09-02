<?php
// ===========================================================================
//  Candidate pool convergence — one person, two pools  (Revamp P11).
//
//  The same real human can sit in BOTH identity pools the platform keeps:
//    • the RECRUITMENT pool — `candidates` (people we place on a client's roll);
//    • the MARKETPLACE pool — `cx_professionals` (people on the bench / passport
//      for freelance deployment).
//  They were built separately and never linked, so a recruiter working a
//  requisition cannot see that a candidate is already a known marketplace
//  professional (verified, benched, rate on file), and the bench cannot see that
//  a professional has a live recruitment application.
//
//  This is a read-only DETECTOR, the identity-side twin of the engagement /
//  revenue / cost reconciliations. It matches a candidate to a professional by
//  the same keys the rest of the app already dedupes on — last-10-digit mobile,
//  lower-cased e-mail, or the dedupe name key — and SURFACES the overlap. It
//  never merges the two rows, deletes either, or moves any figure: each pool
//  keeps its own record. Mirrors recruit.php's person_applications(), which does
//  exactly this WITHIN the recruitment pool.
// ===========================================================================

// The two normalised keys, shared with connect_pro_phone_key() / person_key().
function candpool_mobile10($s) {
    $d = preg_replace('/\D+/', '', (string)$s);
    return strlen($d) >= 10 ? substr($d, -10) : '';
}
function candpool_email($s) { return strtolower(trim((string)$s)); }

// A keyed index of the marketplace pool, built once: mobile10 → ids, email → ids,
// name-dedupe-key → ids. Cached per request (pass $rebuild=true to refresh after
// the pool changes — used by tests). Read-only.
function candpool_pro_index($rebuild = false) {
    static $idx = null;
    if ($idx !== null && !$rebuild) return $idx;
    $idx = ['byMob' => [], 'byEmail' => [], 'byName' => [], 'rows' => []];
    try { $pros = ops_all("SELECT id, name, email, mobile, verification_tier, availability FROM cx_professionals WHERE COALESCE(is_active,1)=1") ?: []; }
    catch (Throwable $e) { $pros = []; }
    foreach ($pros as $p) {
        $pid = (int)$p['id'];
        $idx['rows'][$pid] = $p;
        $m = candpool_mobile10($p['mobile']); if ($m !== '') $idx['byMob'][$m][] = $pid;
        $em = candpool_email($p['email']);   if ($em !== '') $idx['byEmail'][$em][] = $pid;
        if (function_exists('dd_key')) { $k = dd_key((string)$p['name']); if ($k !== '') $idx['byName'][$k][] = $pid; }
    }
    return $idx;
}

// Marketplace professionals that are the same person as this candidate. Mobile
// and e-mail are strong matches; the name key is a soft one. Read-only.
//   → [ ['pro_id','name','verification_tier','availability','reason'] … ]
function candpool_pro_matches($cand, $limit = 20) {
    $idx = candpool_pro_index();
    $mob = candpool_mobile10($cand['mobile'] ?? '');
    $em  = candpool_email($cand['email'] ?? '');
    $nk  = function_exists('dd_key') ? dd_key((string)trim(($cand['first_name'] ?? '') . ' ' . ($cand['last_name'] ?? ''))) : '';
    $hits = [];  // pro_id => reason (strongest kept)
    $rank = ['mobile' => 3, 'email' => 2, 'name' => 1];
    $take = function ($ids, $reason) use (&$hits, $rank) {
        foreach ($ids as $pid) {
            if (!isset($hits[$pid]) || $rank[$reason] > $rank[$hits[$pid]]) $hits[$pid] = $reason;
        }
    };
    if ($mob !== '' && isset($idx['byMob'][$mob]))   $take($idx['byMob'][$mob], 'mobile');
    if ($em  !== '' && isset($idx['byEmail'][$em]))  $take($idx['byEmail'][$em], 'email');
    if ($nk  !== '' && isset($idx['byName'][$nk]))   $take($idx['byName'][$nk], 'name');
    $out = [];
    foreach ($hits as $pid => $reason) {
        $p = $idx['rows'][$pid] ?? null; if (!$p) continue;
        $out[] = ['pro_id' => $pid, 'name' => (string)$p['name'], 'verification_tier' => (string)($p['verification_tier'] ?? ''),
                  'availability' => (string)($p['availability'] ?? ''), 'reason' => $reason];
        if (count($out) >= $limit) break;
    }
    return $out;
}

// The reverse: recruitment candidates that are the same person as a marketplace
// professional. Read-only.
function candpool_cand_matches($pro, $limit = 20) {
    $mob = candpool_mobile10($pro['mobile'] ?? '');
    $em  = candpool_email($pro['email'] ?? '');
    $nk  = function_exists('dd_key') ? dd_key((string)($pro['name'] ?? '')) : '';
    if ($mob === '' && $em === '' && $nk === '') return [];
    try {
        $rows = ops_all("SELECT c.id, c.cand_code, c.first_name, c.last_name, c.mobile, c.email, c.stage,
                            r.req_code FROM candidates c LEFT JOIN requisitions r ON r.id=c.requisition_id
                         ORDER BY c.id DESC LIMIT 400") ?: [];
    } catch (Throwable $e) { return []; }
    $out = [];
    foreach ($rows as $c) {
        $cm = candpool_mobile10($c['mobile']); $ce = candpool_email($c['email']);
        $ck = function_exists('dd_key') ? dd_key((string)trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))) : '';
        $reason = ($mob !== '' && $cm === $mob) ? 'mobile'
                : (($em !== '' && $ce === $em) ? 'email'
                : (($nk !== '' && $ck === $nk) ? 'name' : ''));
        if ($reason === '') continue;
        $c['reason'] = $reason; $out[] = $c;
        if (count($out) >= $limit) break;
    }
    return $out;
}

// The full overlap worklist: candidates that also exist as a marketplace
// professional, one row per candidate with its best (strongest) match. Read-only.
function candpool_scan($limit = 200) {
    try { $cands = ops_all("SELECT c.id, c.cand_code, c.first_name, c.last_name, c.mobile, c.email, c.stage, c.client_id,
                               r.req_code, bp.legal_name client_name
                            FROM candidates c LEFT JOIN requisitions r ON r.id=c.requisition_id
                            LEFT JOIN business_partners bp ON bp.id=c.client_id
                            ORDER BY c.id DESC LIMIT 2000") ?: []; }
    catch (Throwable $e) { $cands = []; }
    $rank = ['mobile' => 3, 'email' => 2, 'name' => 1];
    $out = [];
    foreach ($cands as $c) {
        $m = candpool_pro_matches($c, 20);
        if (!$m) continue;
        // best (strongest) match for the headline row
        usort($m, fn($a, $b) => $rank[$b['reason']] <=> $rank[$a['reason']]);
        $best = $m[0];
        $out[] = [
            'cand_id' => (int)$c['id'], 'cand_code' => (string)($c['cand_code'] ?: ('#' . $c['id'])),
            'cand_name' => trim(((string)($c['first_name'] ?? '')) . ' ' . ((string)($c['last_name'] ?? ''))),
            'stage' => (string)($c['stage'] ?? ''), 'req_code' => (string)($c['req_code'] ?? ''),
            'client_name' => (string)($c['client_name'] ?? ''),
            'pro_id' => (int)$best['pro_id'], 'pro_name' => (string)$best['name'],
            'verification_tier' => (string)$best['verification_tier'], 'availability' => (string)$best['availability'],
            'reason' => (string)$best['reason'], 'match_count' => count($m),
        ];
        if (count($out) >= $limit) break;
    }
    return $out;
}

// How many candidates also exist as a marketplace professional (advisory count).
function candpool_count() { return count(candpool_scan(100000)); }

// Pool health summary: the size of each pool and how many people overlap, split
// by match strength. Read-only.
function candpool_summary() {
    $s = ['candidates' => 0, 'professionals' => 0, 'overlap' => 0,
          'by_mobile' => 0, 'by_email' => 0, 'by_name' => 0];
    try { $s['candidates']    = (int)ops_val("SELECT COUNT(*) FROM candidates"); } catch (Throwable $e) {}
    try { $s['professionals'] = (int)ops_val("SELECT COUNT(*) FROM cx_professionals WHERE COALESCE(is_active,1)=1"); } catch (Throwable $e) {}
    foreach (candpool_scan(100000) as $r) {
        $s['overlap']++;
        if ($r['reason'] === 'mobile') $s['by_mobile']++;
        elseif ($r['reason'] === 'email') $s['by_email']++;
        else $s['by_name']++;
    }
    return $s;
}

// The read-only pool overview screen. Gated to recruitment/HR viewers.
function ops_candpool($method) {
    ops_require((function_exists('recruit_home_can') && recruit_home_can()) || is_master(),
        'You cannot open the candidate pool overview.');
    view('ops/candidate_pool', [
        'summary' => candpool_summary(),
        'rows'    => candpool_scan(300),
    ]);
    return true;
}
