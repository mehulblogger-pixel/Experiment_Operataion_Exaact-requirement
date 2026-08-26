<?php
// Phase 2 §47 — settings governance: for the settings that change how the system BEHAVES, one place
// that says what each is FOR, which modules it AFFECTS, and whether changing it re-touches existing
// records or only applies going forward. Module 14 already audits WHO changed WHICH setting; this adds
// the WHY/WHAT-IT-AFFECTS an approver needs to judge a change. Read-only reference over the settings
// that already exist — it defines no new setting and changes no behaviour.

// The curated registry. Deliberately NOT every key (branding, seed markers and cache flags are
// governance noise) — only the behavioural switches, financial norms and lifecycle timers where
// "what does this touch?" is a real question. `scope`: 'forward' = new records only; 'live' = changes
// what existing screens compute/show immediately.
function setting_meta_all() {
    return [
        // --- Report workflow gates ------------------------------------------------
        'issue_gate_strict'          => ['Strict issuance gate', 'Turns the report issuance readiness checks (vetting, completeness, competence, impartiality, open NCRs, client acceptance) from advisory into a hard block.', ['Report issue (Module 08)'], 'live', 'high'],
        'vetting_gate_required'      => ['Vetting required before approval', 'Whether technical vetting is a mandatory step before a report can be approved.', ['Report vetting (Module 07)'], 'forward', 'high'],
        'vetting_checklist_require'  => ['Vetting checklist mandatory', 'Whether the vetting checklist must be completed, not just present.', ['Report vetting (Module 07)'], 'forward', 'medium'],
        'rn_require_client_acceptance' => ['Release needs client acceptance', 'Whether a Release/IRN requires recorded client acceptance before issue.', ['Report issue (Module 08)'], 'forward', 'high'],
        'invoice_gate_strict'        => ['Strict invoice gate', 'Turns the invoice-readiness checks (reports issued, release accepted, contract value) from advisory into a hard block on raising the invoice.', ['Invoicing / billing (Module 33)'], 'live', 'high'],
        'report_escalate_days'       => ['Report escalation window (days)', 'How long a report may sit before it is escalated on the attention band.', ['My Work / attention (Module 39)'], 'live', 'medium'],
        // --- Financial norms (feed the ONE profit engine) -------------------------
        'fy_start_month'             => ['Financial-year start month', 'The month the financial year begins. Re-buckets every FY-filtered figure in the app.', ['All financial screens', 'MIS', 'Profitability'], 'live', 'high'],
        'fy_current'                 => ['Pinned current financial year', 'Pins the default FY the app opens on (blank = the real current year).', ['All registers (default filter)'], 'live', 'medium'],
        'manmonth_basis'             => ['Man-month basis', 'Days that make one man-month — the divisor in the labour-cost half of job profit.', ['Profit engine (job_profit)', 'Profitability', 'MIS'], 'live', 'high'],
        'daily_hours_cap'            => ['Daily hours cap', 'The working-hours ceiling used when converting effort to cost.', ['Profit engine', 'Timesheets'], 'live', 'medium'],
        'fy_revenue_target'         => ['FY revenue target', 'The revenue goal shown against actuals on the command band.', ['Dashboards / MIS'], 'live', 'low'],
        'tat_threshold_days'         => ['Turnaround threshold (days)', 'The turnaround a job is measured against before it counts as slow.', ['Job KPIs', 'Dashboards'], 'live', 'medium'],
        'revrecon_tolerance'         => ['Revenue reconciliation tolerance', 'How far a job\'s legacy invoice figure may sit from the books ledger before §29 flags it.', ['System status (revenue reconciliation)'], 'live', 'low'],
        // --- Lifecycle timers -----------------------------------------------------
        'contract_idle_close_days'   => ['Contract idle auto-close (days)', 'Days of no activity before a contract is auto-closed by the daily sweep.', ['Contracts (Module 03)'], 'forward', 'high'],
        'contract_idle_warn_days'    => ['Contract idle warning (days)', 'Days before the auto-close that a heads-up is sent and the on-screen warning appears.', ['Contracts (Module 03)'], 'forward', 'medium'],
        'vendor_requal_months'       => ['Vendor requalification (months)', 'How often an approved vendor must be requalified.', ['Vendors (Module 04)'], 'forward', 'medium'],
        // --- Field / check-in controls -------------------------------------------
        'geofence_on'                => ['Geofence check-in', 'Whether punch in/out is only allowed within range of the site.', ['Check-in / evidence (Module 21)'], 'forward', 'high'],
        'checkin_entry_exit_required'=> ['Arrival & departure required', 'Whether a job cannot be closed until arrival and departure are recorded.', ['Jobs (Module 05)', 'Check-in'], 'forward', 'high'],
        'checkin_photo_required'     => ['Check-in photo required', 'Whether a check-in must carry a photo.', ['Check-in / evidence (Module 21)'], 'forward', 'medium'],
        // --- Platform -------------------------------------------------------------
        'licence_enforce'            => ['Enforce licence', 'Whether module licensing is enforced (off = everything visible regardless of entitlement).', ['Every licensed module'], 'live', 'high'],
    ];
}

// The governance metadata for one setting, or null if it is not a governed behavioural setting.
function setting_meta($key) {
    $all = setting_meta_all();
    return $all[(string)$key] ?? null;
}

// A read-only governance reference panel: every governed setting, its purpose, what it affects, whether
// it applies live or only forward, and its current value. For the settings screen and the audit context.
function setting_meta_render($title = 'What these settings affect') {
    $all = setting_meta_all();
    if (!$all) return;
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $impactTone = ['high' => 'p-bad', 'medium' => 'p-warn', 'low' => 'p-mut'];
    echo '<div class="panel"><h3 class="tab-sub" style="margin-top:0">' . $e($title)
       . ' <span class="muted" style="font-weight:400;font-size:12px">(' . count($all) . ' governed settings)</span></h3>'
       . '<p class="muted" style="margin:0 0 8px;font-size:12px">The settings that change how the system behaves — what each is for, what it touches, and whether it applies to existing records or only going forward. Every change is on the audit trail.</p>'
       . '<div class="dt-scroll"><table class="dt"><thead><tr>'
       . '<th>Setting</th><th>What it is for</th><th>Affects</th><th>Applies</th><th>Now</th></tr></thead><tbody>';
    foreach ($all as $key => $m) {
        $val = function_exists('setting_get') ? (string)setting_get($key, '') : '';
        $valShow = $val === '' ? '—' : (strlen($val) > 24 ? substr($val, 0, 24) . '…' : $val);
        echo '<tr><td><strong>' . $e($m[0]) . '</strong><br><span class="muted" style="font-size:10.5px">' . $e($key) . '</span> '
           . '<span class="pill ' . ($impactTone[$m[4]] ?? 'p-mut') . '" style="font-size:9.5px">' . $e($m[4]) . '</span></td>'
           . '<td style="font-size:12.5px">' . $e($m[1]) . '</td>'
           . '<td style="font-size:11.5px">' . $e(implode(', ', $m[2])) . '</td>'
           . '<td style="font-size:11.5px">' . ($m[3] === 'live' ? 'immediately' : 'new records') . '</td>'
           . '<td class="muted" style="font-size:11.5px;white-space:nowrap">' . $e($valShow) . '</td></tr>';
    }
    echo '</tbody></table></div></div>';
}
