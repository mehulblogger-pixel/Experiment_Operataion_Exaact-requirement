<?php
// ============================================================================
//  CONNECT — Operations Advisor  (slice K12, additive & READ-ONLY)
//
//  The blueprint's F13: at/after booking, the Concierge stops being only a
//  matcher and becomes a PREVENTION engine — it assembles the requirement's
//  readiness picture and says, in one line, the delay risk and exactly what to
//  do about it. MVP is deterministic rules over data we already have (K10 site
//  readiness + commercial terms, and the awarded professional's eligibility).
//  No new table, permission or status — it reads and advises.
// ============================================================================

/**
 * The advisor verdict for a requirement:
 *   [readiness_pct, risk (LOW|MEDIUM|HIGH), headline, actions[], factors[]].
 * Each action is a plain "do this" sentence, in the house advisor style.
 */
function connect_advisor_for_requirement($req) {
    if (!is_array($req)) return null;
    $reqId = (int)($req['id'] ?? 0);
    $actions = []; $factors = [];
    $riskRank = 0;  // 0 LOW, 1 MEDIUM, 2 HIGH
    $bump = function ($r) use (&$riskRank) { $rank = ['LOW' => 0, 'MEDIUM' => 1, 'HIGH' => 2][$r] ?? 0; if ($rank > $riskRank) $riskRank = $rank; };

    // 1. Site readiness (K10 / F3) — missing mandatory items are hard delay risks.
    $readinessPct = 100;
    if (function_exists('cx_readiness_score')) {
        $rs = cx_readiness_score($reqId);
        $readinessPct = (int)$rs['score'];
        if (!empty($rs['missing_mandatory'])) {
            $bump('HIGH');
            foreach ($rs['missing_mandatory'] as $m) $actions[] = 'Confirm before mobilization: ' . $m;
            $factors[] = ['label' => 'Site readiness', 'level' => 'bad', 'text' => count($rs['missing_mandatory']) . ' mandatory item(s) not ready'];
        } else {
            $factors[] = ['label' => 'Site readiness', 'level' => 'ok', 'text' => 'All mandatory items ready'];
        }
    }

    // 2. Commercial terms (K10 / F1) — incomplete terms invite disputes.
    if (function_exists('cx_terms_complete')) {
        if (!cx_terms_complete($reqId)) {
            $bump('MEDIUM');
            $actions[] = 'Agree the commercial term-sheet (waiting charges, travel, revisit) before work starts';
            $factors[] = ['label' => 'Commercial terms', 'level' => 'warn', 'text' => 'Term-sheet incomplete'];
        } else {
            $factors[] = ['label' => 'Commercial terms', 'level' => 'ok', 'text' => 'Agreed'];
        }
    }

    // 3. Awarded professional eligibility — a lapsed mandatory cert stops the visit.
    $awardedApp = (int)($req['awarded_application_id'] ?? 0);
    if ($awardedApp && function_exists('cx_application_get') && function_exists('inspector_eligibility')) {
        $ap = cx_application_get($awardedApp);
        $insp = (int)($ap['inspector_id'] ?? 0);
        if ($insp) {
            $elig = inspector_eligibility($insp, ['on_date' => substr((string)($req['start_date'] ?? ''), 0, 10) ?: date('Y-m-d')]);
            if ($elig['status'] === 'BLOCKED') {
                $bump('HIGH');
                $actions[] = 'The awarded professional is blocked (a required certificate has lapsed) — verify or replace before mobilization';
                $factors[] = ['label' => 'Professional eligibility', 'level' => 'bad', 'text' => 'Blocked'];
            } elseif (in_array($elig['status'], ['CHECK', 'EXPIRING'], true)) {
                $bump('MEDIUM');
                $actions[] = 'Check the awarded professional\'s authorisation/expiry for this work';
                $factors[] = ['label' => 'Professional eligibility', 'level' => 'warn', 'text' => ucfirst(strtolower($elig['status']))];
            } else {
                $factors[] = ['label' => 'Professional eligibility', 'level' => 'ok', 'text' => 'Eligible'];
            }
        }
    }

    $risk = ['LOW', 'MEDIUM', 'HIGH'][$riskRank];
    $headline = [
        'LOW'    => 'Low delay risk — this engagement looks ready.',
        'MEDIUM' => 'Some delay risk — a few things to tidy before mobilization.',
        'HIGH'   => 'High delay risk — resolve the items below before anyone travels.',
    ][$risk];

    return [
        'readiness_pct' => $readinessPct,
        'risk'          => $risk,
        'headline'      => $headline,
        'actions'       => $actions,
        'factors'       => $factors,
    ];
}
