<?php
// ============================================================================
//  CONNECT — Inspection request → unified manpower sourcing  (K0+, additive)
//
//  Closes integration-map seam 6 (master brief §21). When staff need to resource
//  an EXISTING Operations inspection job, this ranks candidates across every pool
//  — internal inspectors, marketplace professionals and the client's private
//  bench — REUSING the existing matcher (connect_match_for_requirement) over a
//  requirement-shaped view of the job. It then lets staff assign under CONTROLLED
//  rules: an internal inspector is assigned directly; a marketplace professional
//  can staff an internal job ONLY once linked to an inspector record (Connection
//  #1) — so ISO 17020 competence/authorization keeps running through the existing
//  inspector controls (§41). Sourcing is a recommendation + a controlled hand-off,
//  never a bypass.
//
//  STRICTLY ADDITIVE: read-only over jobs/calls + the matcher; the one write is a
//  guarded assignment of jobs.inspector_id (the same field job-edit already sets),
//  logged via act_log. No new table.
// ============================================================================

/** The job + its call/client context used for sourcing. */
function connect_source_job_context($jobId) {
    $jobId = (int)$jobId;
    $job = ops_one("SELECT * FROM jobs WHERE id=?", [$jobId]);
    if (!$job) return null;
    $clientId = 0; $clientName = '';
    if ((int)($job['call_id'] ?? 0) > 0) {
        $call = ops_one("SELECT client_id FROM calls WHERE id=?", [(int)$job['call_id']]);
        $clientId = (int)($call['client_id'] ?? 0);
    }
    if ($clientId > 0) $clientName = (string)ops_val("SELECT COALESCE(display_name, legal_name) FROM business_partners WHERE id=?", [$clientId]);
    $inspName = (int)($job['inspector_id'] ?? 0) > 0 ? (string)ops_val("SELECT name FROM inspectors WHERE id=?", [(int)$job['inspector_id']]) : '';
    return ['job' => $job, 'client_id' => $clientId, 'client_name' => $clientName, 'inspector_name' => $inspName];
}

/** Build a requirement-shaped array from a job so the existing matcher can rank. */
function connect_source_pseudo_req($job) {
    $trade = (int)($job['req_trade_id'] ?? 0) > 0 ? (string)ops_val("SELECT name FROM cx_iti_trades WHERE id=?", [(int)$job['req_trade_id']]) : '';
    $title = trim(implode(' ', array_filter([
        (string)($job['inspection_type'] ?? ''), (string)($job['service_code'] ?? ''), $trade,
    ]))) ?: 'Inspection';
    $site = trim((string)($job['dep_site'] ?? ''));
    return [
        'id'          => 0,   // no marketplace applications → no applied filtering
        'title'       => $title . ($site ? ' at ' . $site : ''),
        'description' => $site,
        'location'    => $site,
        'start_date'  => (string)($job['inspection_start_date'] ?? $job['scheduled_date'] ?? ''),
        'end_date'    => (string)($job['inspection_end_date'] ?? ''),
    ];
}

/**
 * Ranked, unified candidates for a job. Reuses connect_match_for_requirement
 * (internal inspectors + marketplace professionals, deduped by identity, with
 * reasons / eligibility / location), then annotates each with a source, whether
 * it can be assigned now, and whether it sits on THIS client's private bench.
 */
function connect_source_candidates($jobId, $limit = 12) {
    $ctx = connect_source_job_context($jobId);
    if (!$ctx) return [];
    $req = connect_source_pseudo_req($ctx['job']);
    $rows = function_exists('connect_match_for_requirement') ? connect_match_for_requirement($req, $limit) : [];

    // This client's private-bench professional ids (to prefer people they know).
    $benchPro = [];
    if ($ctx['client_id'] > 0) {
        try { foreach (ops_all("SELECT professional_id FROM cx_client_bench WHERE client_party_id=? AND professional_id>0", [(int)$ctx['client_id']]) ?: [] as $r) $benchPro[(int)$r['professional_id']] = true; }
        catch (Throwable $e) {}
    }

    foreach ($rows as &$r) {
        $kind = (string)($r['kind'] ?? 'inspector');
        $id   = (int)($r['id'] ?? 0);
        $r['source'] = $kind === 'inspector' ? 'internal' : 'marketplace';
        $r['assign_inspector_id'] = 0; $r['needs_link'] = false; $r['on_client_bench'] = false;
        if ($kind === 'inspector') {
            $r['assign_inspector_id'] = $id;
            // A person shown as their internal inspector may still be on the client's
            // bench via their linked marketplace profile — carry the preference.
            if (function_exists('connect_identity_of_inspector')) {
                $lk = connect_identity_of_inspector($id);
                if ($lk && !empty($benchPro[(int)$lk['professional_id']])) $r['on_client_bench'] = true;
            }
        } else {
            $r['on_client_bench'] = !empty($benchPro[$id]);
            $lk = function_exists('connect_identity_of_professional') ? connect_identity_of_professional($id) : null;
            if ($lk && (int)$lk['inspector_id'] > 0) { $r['assign_inspector_id'] = (int)$lk['inspector_id']; }
            else { $r['needs_link'] = true; }
        }
        $r['assignable'] = (int)$r['assign_inspector_id'] > 0;
        if ($r['on_client_bench']) $r['reasons'] = array_merge(['★ On this client’s bench'], $r['reasons'] ?? []);
    }
    unset($r);

    // Prefer people the client already knows, then keep the matcher's order.
    usort($rows, function ($a, $b) {
        $ab = !empty($a['on_client_bench']) ? 1 : 0; $bb = !empty($b['on_client_bench']) ? 1 : 0;
        if ($ab !== $bb) return $bb <=> $ab;
        return (int)($b['score'] ?? 0) <=> (int)($a['score'] ?? 0);
    });
    return $rows;
}

/**
 * Assign a sourced candidate to the job — the controlled hand-off. An internal
 * inspector is placed directly; a marketplace professional is placed ONLY via the
 * inspector it is linked to (else refused with a prompt to link). Guarded + logged.
 * Returns [ok, message].
 */
function connect_source_assign($jobId, $kind, $candId, $by = '') {
    $jobId = (int)$jobId; $candId = (int)$candId; $kind = (string)$kind;
    $job = ops_one("SELECT id, job_code, closed_flag FROM jobs WHERE id=?", [$jobId]);
    if (!$job) return [false, 'Job not found.'];
    if ((int)($job['closed_flag'] ?? 0) === 1) return [false, 'This job is closed.'];

    $inspectorId = 0;
    if ($kind === 'inspector') {
        if ((int)ops_val("SELECT COUNT(*) FROM inspectors WHERE id=?", [$candId]) === 0) return [false, 'That inspector does not exist.'];
        $inspectorId = $candId;
    } elseif ($kind === 'professional') {
        $lk = function_exists('connect_identity_of_professional') ? connect_identity_of_professional($candId) : null;
        if (!$lk || (int)$lk['inspector_id'] <= 0)
            return [false, 'Link this marketplace professional to an internal inspector first (Professional identity) — required for competence checks before they can staff an inspection job.'];
        $inspectorId = (int)$lk['inspector_id'];
    } else {
        return [false, 'Unknown candidate type.'];
    }

    if ($by === '' && function_exists('user_name') && function_exists('current_user')) $by = user_name(current_user());
    db()->prepare("UPDATE jobs SET inspector_id=?, updated_at=? WHERE id=?")->execute([$inspectorId, date('c'), $jobId]);
    $nm = (string)ops_val("SELECT name FROM inspectors WHERE id=?", [$inspectorId]);
    if (function_exists('act_log')) { try { act_log('job', $jobId, 'CONNECT_SOURCED', 'Sourced ' . $nm . ' onto ' . $job['job_code'] . ($kind === 'professional' ? ' (via linked marketplace profile)' : ''), ['auto' => 0]); } catch (Throwable $e) {} }
    return [true, $nm . ' assigned to ' . $job['job_code'] . '.'];
}

/** Gate — the same coordinator/manager talent-pool right (no new permission). */
function connect_source_can() {
    if (function_exists('connect_enabled') && !connect_enabled()) return false;
    if (function_exists('connect_market_can')) return (bool)connect_market_can();
    return function_exists('is_master') && is_master();
}

/** The unified sourcing screen for one job. */
function ops_connect_source($method) {
    ops_require(connect_source_can(), 'Sourcing manpower is for coordinators, managers and admins.');
    $jobId = (int)($_GET['job'] ?? $_POST['job'] ?? 0);
    if ($method === 'POST' && ($_POST['action'] ?? '') === 'assign') {
        [$ok, $msg] = connect_source_assign($jobId, (string)($_POST['kind'] ?? ''), (int)($_POST['cand_id'] ?? 0));
        flash($msg, $ok ? 'success' : 'error');
        redirect('/connect-source?job=' . $jobId);
    }
    $ctx = connect_source_job_context($jobId);
    if (!$ctx) { flash('Job not found.', 'error'); redirect('/jobs'); }
    view('ops/connect_source', ['ctx' => $ctx, 'candidates' => connect_source_candidates($jobId)]);
    return true;
}
