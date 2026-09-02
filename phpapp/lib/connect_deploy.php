<?php
// ============================================================================
//  CONNECT — Award → Deployment bridge  (K0+, additive, NON-DESTRUCTIVE)
//
//  Closes integration-map seam 4/5: a marketplace AWARD becomes an actual
//  Operations DEPLOYMENT in the EXISTING deputation engine (PDSO) — so the pool
//  connects to real execution: hire → deploy → (attendance / report / voucher) →
//  invoice, with no re-entry. It COPIES the proven award→billing bridge pattern
//  (lib/connect_bridge.php): a deliberate desk action, idempotent by source key,
//  logged — we do NOT build a second scheduler.
//
//  A PDSO deployment IS a `jobs` row with job_type='DEPUTATION' + dep_status +
//  dep_site (see lib/pdso.php). We create exactly that, and resolve WHO is
//  deployed through the unified identity (Connection #1):
//    • awarded internal inspector      → jobs.inspector_id = that inspector
//    • awarded marketplace professional → the inspector it is LINKED to; if not
//      linked, the deployment is created UNASSIGNED and the desk is told to link
//      the person (one identity) — so ISO 17020 competence/authorization still
//      runs through the existing inspector controls (§41). Nothing is bypassed.
//    • agency-bench subject            → UNASSIGNED (the agency supplies the body)
//
//  STRICTLY ADDITIVE: two columns on `jobs` (source link + idempotency key). No
//  existing job, PDSO flow, attendance, report or invoice path changes.
// ============================================================================

function connect_deploy_migrate() {
    static $done = false; if ($done) return; $done = true;
    if (function_exists('ensure_column')) {
        // The bridge link + idempotency key (one deployment per requirement).
        try { ensure_column('jobs', 'source_module', "VARCHAR(24) DEFAULT ''"); } catch (Throwable $e) {}
        try { ensure_column('jobs', 'source_requirement_id', "INT DEFAULT 0"); }  catch (Throwable $e) {}
        // Defensive: these already exist via ops/pdso migrate, but a deployment
        // job needs them regardless of boot order.
        try { ensure_column('jobs', 'job_type', "VARCHAR(20) DEFAULT 'INSPECTION'"); } catch (Throwable $e) {}
        try { ensure_column('jobs', 'dep_status', "VARCHAR(24) DEFAULT ''"); }         catch (Throwable $e) {}
        try { ensure_column('jobs', 'dep_site', "VARCHAR(200) DEFAULT ''"); }          catch (Throwable $e) {}
    }
}

/** The existing deployment job created from this requirement, or null. */
function connect_deploy_row_for_requirement($requirementId) {
    connect_deploy_migrate();
    try { return ops_one("SELECT * FROM jobs WHERE source_module='connect' AND source_requirement_id=? ORDER BY id DESC", [(int)$requirementId]) ?: null; }
    catch (Throwable $e) { return null; }
}

/**
 * Resolve who to place on jobs.inspector_id for an awarded subject, via the
 * unified identity. Returns [inspector_id, unassigned_reason]. inspector_id 0
 * means "create the deployment but leave it unassigned" and the reason explains
 * why (so the desk can act — usually: link the professional to an inspector).
 */
function connect_deploy_resolve_inspector($subject) {
    $kind = (string)($subject['kind'] ?? '');
    $id   = (int)($subject['id'] ?? 0);
    if ($kind === 'inspector' && $id > 0) return [$id, ''];
    if ($kind === 'professional' && $id > 0) {
        if (function_exists('connect_identity_of_professional')) {
            $lk = connect_identity_of_professional($id);
            if ($lk && (int)$lk['inspector_id'] > 0) return [(int)$lk['inspector_id'], ''];
        }
        return [0, 'This marketplace professional is not yet linked to an internal inspector record — link them (Professional identity) to place them on the schedule and run competence checks.'];
    }
    if ($kind === 'bench') return [0, 'An agency-bench person supplies the body — assign the internal inspector who will carry it on the schedule.'];
    return [0, 'No resolvable person on the award.'];
}

/**
 * Create (or update) the Operations deployment for an AWARDED requirement.
 * Idempotent: one DEPUTATION job per requirement. Returns [ok, message, job_id].
 */
function connect_deploy_from_engagement($requirementId) {
    connect_deploy_migrate();
    $requirementId = (int)$requirementId;
    $req = function_exists('cx_requirement_get') ? cx_requirement_get($requirementId) : null;
    if (!$req) return [false, 'Requirement not found.', 0];
    if (strtoupper((string)$req['status']) !== 'AWARDED') return [false, 'Deploy only after the requirement is awarded.', 0];
    $subject = function_exists('connect_engage_subject_for_award') ? connect_engage_subject_for_award($req) : null;
    if (!$subject || (int)($subject['id'] ?? 0) <= 0 && ($subject['kind'] ?? '') !== 'bench') return [false, 'No awarded person to deploy.', 0];

    // Dates: prefer the booked engagement, else the requirement.
    $eng = function_exists('connect_engage_for_requirement') ? connect_engage_for_requirement($requirementId) : null;
    $start = trim((string)($eng['start_date'] ?? $req['start_date'] ?? ''));
    $end   = trim((string)($eng['end_date'] ?? $req['end_date'] ?? ''));
    $site  = trim((string)($req['location'] ?? ''));
    $sbu   = trim((string)($req['sbu'] ?? ''));
    $mandays = (float)($eng['quantity'] ?? 0);

    [$inspectorId, $unassignedReason] = connect_deploy_resolve_inspector($subject);

    $existing = connect_deploy_row_for_requirement($requirementId);
    $firstStatus = function_exists('pdso_statuses') ? array_key_first(pdso_statuses()) : 'PLANNED';
    $by = function_exists('user_name') && function_exists('current_user') ? user_name(current_user()) : 'connect';

    // Gap-1 (CONNECT) — thread this marketplace deployment into the engagement/finance spine.
    // The requirement's marketplace ref becomes the engagement key (contract_number), and a
    // first-class engagement row is ensured, so the deployment reaches the contract_number-keyed
    // readers, the engagement entity and the reconciliation gates instead of dangling unlinked.
    $contractNo = trim((string)($req['ref_code'] ?? '')) !== '' ? (string)$req['ref_code'] : ('CXR-' . $requirementId);
    $engId = function_exists('engagement_ensure')
        ? (int)engagement_ensure($contractNo, (int)($req['poster_party_id'] ?? 0), (string)($req['title'] ?? ''))
        : 0;

    if ($existing) {
        // Keep the deployment; refresh the person + dates + site (e.g. after a link) and, additively,
        // the engagement thread for deployments created before this connection existed.
        db()->prepare("UPDATE jobs SET inspector_id=?, dep_site=?, scheduled_date=?, inspection_start_date=?, inspection_end_date=?, contract_number=?, engagement_id=?, updated_at=? WHERE id=?")
            ->execute([$inspectorId ?: null, $site, $start, $start, $end, $contractNo, $engId ?: null, date('c'), (int)$existing['id']]);
        $jobId = (int)$existing['id'];
        $msg = 'Deployment updated (' . $existing['job_code'] . ').';
    } else {
        $code = function_exists('ops_next_code') ? ops_next_code('jobs', 'job_code', 'JOB') : ('JOB-' . $requirementId);
        db()->prepare("INSERT INTO jobs (job_code, call_id, inspector_id, job_type, dep_status, dep_site, scheduled_date, inspection_start_date, inspection_end_date, reporting_frequency, sbu, mandays, source_module, source_requirement_id, contract_number, engagement_id, created_by, created_at)
                       VALUES (?, NULL, ?, 'DEPUTATION', ?, ?, ?, ?, ?, 'NOREPORT', ?, ?, 'connect', ?, ?, ?, ?, ?)")
            ->execute([$code, $inspectorId ?: null, $firstStatus, $site, $start, $start, $end, $sbu, $mandays, $requirementId, $contractNo, $engId ?: null, $by, date('c')]);
        $jobId = (int)db()->lastInsertId();
        $msg = 'Deployment ' . $code . ' created in Operations (PDSO).';
        if (function_exists('act_log')) { try { act_log('job', $jobId, 'CONNECT_DEPLOYED', 'Deployment ' . $code . ' from marketplace requirement ' . ($req['ref_code'] ?? ('#'.$requirementId)), ['auto' => 0]); } catch (Throwable $e) {} }
    }
    if ($unassignedReason) $msg .= ' ' . $unassignedReason;
    return [true, $msg, $jobId];
}
