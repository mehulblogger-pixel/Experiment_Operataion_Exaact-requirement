<?php
// ============================================================================
//  RECRUITMENT & WORKFORCE — Command Centre  (Phase 1, additive & read-only)
//
//  A single landing that answers, from data that ALREADY exists, the three
//  questions a recruiter / coordinator / manager opens the module to ask:
//     · Today   — what needs action right now
//     · Risks   — what is slipping
//     · Opportunities — what we can act on before recruiting externally
//
//  ZERO-BREAK: this file adds a new route (/recruitment) and reads existing
//  tables (requisitions, candidates, jobs, inspector_certs, inspector_day_status).
//  It creates no tables, changes no schema, and touches no existing handler.
//  Every query is guarded so a table/column missing on an older install makes a
//  card quietly read zero rather than taking the page down.
// ============================================================================

function recruit_home_can() {
    return function_exists('can') && (can('mod.hiring.view') || (function_exists('is_master') && is_master()));
}

// SBU scope for candidate rows (candidates have no office_id — they carry an sbu).
function recruit_sbu_clause($col = 'c.sbu') {
    if (!function_exists('scope_sbus')) return ['1=1', []];
    $s = scope_sbus();
    if ($s === 'ALL' || !is_array($s) || !$s) return ['1=1', []];
    $in = implode(',', array_fill(0, count($s), '?'));
    return ["($col IN ($in) OR COALESCE($col,'')='')", array_values($s)];
}

// ---- Data ------------------------------------------------------------------
function recruit_data() {
    $today = date('Y-m-d');
    $in30  = date('Y-m-d', strtotime('+30 days'));
    $in7   = date('Y-m-d', strtotime('+7 days'));
    $in14  = date('Y-m-d', strtotime('+14 days'));
    $ago7  = date('Y-m-d', strtotime('-7 days'));
    $ago14 = date('Y-m-d', strtotime('-14 days'));

    // Guarded readers — a missing table/column yields 0 / [] not a fatal.
    $one  = function ($sql, $a = []) { try { return (int)(ops_one($sql, $a)['n'] ?? 0); } catch (Throwable $e) { return 0; } };
    $rows = function ($sql, $a = []) { try { return ops_all($sql, $a) ?: []; } catch (Throwable $e) { return []; } };

    [$rw, $ra]   = function_exists('scope_clause') ? scope_clause('r.office_id', 'r.sbu') : ['1=1', []];
    [$jw, $ja]   = function_exists('scope_clause') ? scope_clause('j.executing_office_id', "''") : ['1=1', []];
    [$cw, $ca]   = recruit_sbu_clause('c.sbu');
    $activeStages = "('RECEIVED','SUBMITTED','SHORTLISTED','INTERVIEW','OFFERED','HOLD')";

    $d = [];

    // ---------- Headline counts (KPI row) ----------
    $d['open_reqs']   = $one("SELECT COUNT(*) n FROM requisitions r WHERE r.status='OPEN' AND $rw", $ra);
    $d['pipeline']    = $one("SELECT COUNT(*) n FROM candidates c WHERE c.stage IN $activeStages AND $cw", $ca);
    $d['interviews']  = $one("SELECT COUNT(*) n FROM candidates c WHERE COALESCE(c.interview_required,0)=1
                              AND COALESCE(c.interview_date,'')<>'' AND COALESCE(c.interview_done_date,'')=''
                              AND c.interview_date>=? AND c.interview_date<=? AND $cw", array_merge([$today,$in7], $ca));
    $d['offers']      = $one("SELECT COUNT(*) n FROM candidates c WHERE c.stage='OFFERED' AND $cw", $ca);
    $d['expiring']    = $one("SELECT COUNT(*) n FROM jobs j WHERE COALESCE(j.closed_flag,0)=0 AND j.inspector_id IS NOT NULL
                              AND COALESCE(NULLIF(j.schedule_end_date,''),j.inspection_end_date,j.scheduled_date) BETWEEN ? AND ? AND $jw", array_merge([$today,$in30], $ja));
    $d['available']   = $one("SELECT COUNT(*) n FROM inspector_day_status s WHERE s.day=? AND s.status='AVAILABLE'", [$today]);

    // ---------- TODAY — needs action ----------
    $d['t_reqs'] = $rows("SELECT r.id, r.req_code, r.designation, r.project_site, o.name office, r.status,
                                 (SELECT COUNT(*) FROM candidates c WHERE c.requisition_id=r.id AND c.stage IN ('OFFERED','ACCEPTED')) filled
                          FROM requisitions r LEFT JOIN offices o ON o.id=r.office_id
                          WHERE r.status IN ('OPEN','PROPOSED') AND $rw ORDER BY r.id DESC LIMIT 6", $ra);
    $d['t_followups'] = $rows("SELECT c.id, c.cand_code, (c.first_name||' '||c.last_name) nm, c.designation, c.stage,
                                      COALESCE(NULLIF(c.decided_at,''),c.cv_received_date) since
                               FROM candidates c WHERE c.stage IN $activeStages
                                 AND COALESCE(NULLIF(c.decided_at,''),c.cv_received_date,'') <> ''
                                 AND COALESCE(NULLIF(c.decided_at,''),c.cv_received_date) < ? AND $cw
                               ORDER BY since LIMIT 6", array_merge([$ago7], $ca));
    $d['t_interviews'] = $rows("SELECT c.id, c.cand_code, (c.first_name||' '||c.last_name) nm, c.designation, c.interview_date
                                FROM candidates c WHERE COALESCE(c.interview_required,0)=1
                                  AND COALESCE(c.interview_date,'')<>'' AND COALESCE(c.interview_done_date,'')=''
                                  AND c.interview_date>=? AND $cw ORDER BY c.interview_date LIMIT 6", array_merge([$today], $ca));
    $d['t_offers'] = $rows("SELECT c.id, c.cand_code, (c.first_name||' '||c.last_name) nm, c.designation, c.expected_rate, c.rate_type
                            FROM candidates c WHERE c.stage='OFFERED' AND $cw ORDER BY c.decided_at DESC LIMIT 6", $ca);
    $d['t_joinings'] = $rows("SELECT c.id, c.cand_code, (c.first_name||' '||c.last_name) nm, c.designation, c.proposed_site
                              FROM candidates c WHERE c.stage='ACCEPTED' AND c.inspector_id IS NULL AND $cw
                              ORDER BY c.decided_at DESC LIMIT 6", $ca);
    $d['t_expiring'] = $rows("SELECT j.id, j.job_code, i.name inspector, bp.display_name client,
                                     COALESCE(NULLIF(j.schedule_end_date,''),j.inspection_end_date,j.scheduled_date) endd
                              FROM jobs j LEFT JOIN inspectors i ON i.id=j.inspector_id
                              LEFT JOIN calls cl ON cl.id=j.call_id LEFT JOIN business_partners bp ON bp.id=cl.client_id
                              WHERE COALESCE(j.closed_flag,0)=0 AND j.inspector_id IS NOT NULL
                                AND COALESCE(NULLIF(j.schedule_end_date,''),j.inspection_end_date,j.scheduled_date) BETWEEN ? AND ? AND $jw
                              ORDER BY endd LIMIT 6", array_merge([$today,$in30], $ja));

    // ---------- RISKS ----------
    $d['r_reqs'] = $rows("SELECT r.id, r.req_code, r.designation, o.name office, r.created_at,
                                 (SELECT COUNT(*) FROM candidates c WHERE c.requisition_id=r.id) cands
                          FROM requisitions r LEFT JOIN offices o ON o.id=r.office_id
                          WHERE r.status='OPEN' AND COALESCE(r.created_at,'') < ? AND $rw
                            AND NOT EXISTS (SELECT 1 FROM candidates c WHERE c.requisition_id=r.id AND c.stage IN ('OFFERED','ACCEPTED'))
                          ORDER BY r.created_at LIMIT 6", array_merge([$ago14], $ra));
    $d['r_dormant'] = $rows("SELECT c.id, c.cand_code, (c.first_name||' '||c.last_name) nm, c.stage,
                                    COALESCE(NULLIF(c.decided_at,''),c.cv_received_date) since
                             FROM candidates c WHERE c.stage IN $activeStages
                               AND COALESCE(NULLIF(c.decided_at,''),c.cv_received_date,'')<>''
                               AND COALESCE(NULLIF(c.decided_at,''),c.cv_received_date) < ? AND $cw
                             ORDER BY since LIMIT 6", array_merge([$ago14], $ca));
    $d['r_urgent'] = $rows("SELECT j.id, j.job_code, i.name inspector,
                                   COALESCE(NULLIF(j.schedule_end_date,''),j.inspection_end_date,j.scheduled_date) endd
                            FROM jobs j LEFT JOIN inspectors i ON i.id=j.inspector_id
                            WHERE COALESCE(j.closed_flag,0)=0 AND j.inspector_id IS NOT NULL
                              AND COALESCE(NULLIF(j.schedule_end_date,''),j.inspection_end_date,j.scheduled_date) BETWEEN ? AND ? AND $jw
                            ORDER BY endd LIMIT 6", array_merge([$today,$in7], $ja));
    $d['r_certs'] = $rows("SELECT ic.id, i.name inspector, ic.name cert, ic.valid_to
                           FROM inspector_certs ic JOIN inspectors i ON i.id=ic.inspector_id
                           WHERE COALESCE(ic.valid_to,'')<>'' AND ic.valid_to BETWEEN ? AND ?
                           ORDER BY ic.valid_to LIMIT 6", [$today, $in30]);

    // ---------- OPPORTUNITIES ----------
    $d['o_available'] = $rows("SELECT s.inspector_id, i.name, i.designation
                               FROM inspector_day_status s JOIN inspectors i ON i.id=s.inspector_id
                               WHERE s.day=? AND s.status='AVAILABLE' ORDER BY i.name LIMIT 6", [$today]);
    $d['o_freeing'] = $rows("SELECT j.id, j.job_code, i.name inspector,
                                    COALESCE(NULLIF(j.schedule_end_date,''),j.inspection_end_date,j.scheduled_date) endd
                             FROM jobs j JOIN inspectors i ON i.id=j.inspector_id
                             WHERE COALESCE(j.closed_flag,0)=0
                               AND COALESCE(NULLIF(j.schedule_end_date,''),j.inspection_end_date,j.scheduled_date) BETWEEN ? AND ? AND $jw
                             ORDER BY endd LIMIT 6", array_merge([$today,$in14], $ja));
    // Dormant candidates whose designation matches a live open requirement.
    $d['o_match'] = $rows("SELECT c.id, c.cand_code, (c.first_name||' '||c.last_name) nm, c.designation
                           FROM candidates c
                           WHERE c.stage IN ('HOLD','REJECTED','WITHDRAWN') AND COALESCE(c.designation,'')<>''
                             AND EXISTS (SELECT 1 FROM requisitions r WHERE r.status='OPEN' AND r.designation=c.designation)
                             AND $cw ORDER BY c.id DESC LIMIT 6", $ca);
    $d['o_extensions'] = $d['t_expiring'];   // same set, framed as an extension opportunity

    $d['counts'] = [
        'today' => count($d['t_reqs']) + count($d['t_followups']) + count($d['t_interviews']) + count($d['t_offers']) + count($d['t_joinings']) + count($d['t_expiring']),
        'risks' => count($d['r_reqs']) + count($d['r_dormant']) + count($d['r_urgent']) + count($d['r_certs']),
        'opps'  => count($d['o_available']) + count($d['o_freeing']) + count($d['o_match']),
    ];
    return $d;
}

function ops_recruitment_home($method) {
    ops_require(recruit_home_can(), 'You do not have access to Recruitment.');
    $d = recruit_data();
    view('ops/recruitment_home', ['d' => $d]);
    return true;
}
