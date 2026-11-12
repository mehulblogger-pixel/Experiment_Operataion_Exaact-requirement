<?php
// ============================================================================
//  PROJECT COSTING — the bid / deputation cost build-up.
//
//  A costing sheet is a whole-project estimate: several ROLE lines (Manager,
//  Shift Engineer, …), each built up from configurable cost heads (personnel,
//  transport, food, accommodation, site allowance, PPE, capex, misc), then
//  loaded with overhead / insurance / bad-debt / contingency, turned into a
//  man-month / man-day / lump sell rate with a negotiation margin, multiplied
//  by the BOQ quantity, and rolled up to a project revenue / cost / margin.
//
//  It is deliberately module-neutral: the SAME sheet is reachable from CRM (to
//  price a manpower quotation) and from Recruitment (to budget the roles it has
//  to fill). Everything a vendor might change — the cost heads, the loading
//  percentages, the bases — is configurable (Masters + Settings). Additive:
//  new tables only, nothing existing is touched.
// ============================================================================

// How the sell side is measured. LONG = per man-month × BOQ months (the Excel
// case); DAILY = per man-day × days; FIXED = a lump sum for the whole scope.
const PC_BASES = [
    'LONG'  => 'Long — per man-month × BOQ',
    'DAILY' => 'Daily deputation — per man-day',
    'FIXED' => 'Short / fixed — lump sum',
];

const PC_STATUS = [
    'DRAFT'     => 'Draft',
    'SUBMITTED' => 'Submitted for approval',
    'APPROVED'  => 'Approved',
    'REJECTED'  => 'Sent back',
];

// The default cost-head catalogue — mirrors the client's own sheet. Kept
// separate so the Masters registration can reference it without recursing.
function pc_head_defaults() {
    return [
        'PERSONNEL'     => 'Personnel / salary',
        'STATUTORY'     => 'Statutory (PF/ESIC/bonus/leave)',
        'TRANSPORT'     => 'Transport / travel',
        'ACCOMMODATION' => 'Accommodation / rentals',
        'FOOD'          => 'Food / meal allowance',
        'SITE_ALLOWANCE'=> 'Site allowance',
        'PPE'           => 'PPE / safety kit',
        'UNIFORM'       => 'Uniform / clean-room wearables',
        'CAPEX'         => 'Capital / equipment (amortised)',
        'TOOLS'         => 'Tools & testing equipment',
        'CALIBRATION'   => 'Calibration of instruments/tools',
        'CONSUMABLES'   => 'Consumables & spares',
        'COMMUNICATION' => 'Communication / SIM / data',
        'MEDICAL'       => 'Medical / PCC / fitness',
        'TRAINING'      => 'Training & induction',
        'HOUSEKEEPING'  => 'Housekeeping / general items',
        'INSURANCE_LINE'=> 'Insurance (per person)',
        'MISC'          => 'Miscellaneous / other',
    ];
}
// The live catalogue — a vendor can rename / add / remove heads under
// Masters → "Costing head"; the defaults are the fallback.
function pc_heads() {
    return function_exists('lk_options_or') ? lk_options_or('projcosting_head', pc_head_defaults()) : pc_head_defaults();
}

// The loading percentages and the default negotiation margin. Settings-backed,
// so the finance team sets them once and every new sheet inherits them.
function pc_defaults() {
    $g = fn($k, $d) => function_exists('setting_get') ? (float)setting_get($k, $d) : $d;
    return [
        'overhead_pct'    => $g('costing_overhead_pct', 8),
        'insurance_pct'   => $g('costing_insurance_pct', 0.5),
        'baddebt_pct'     => $g('costing_baddebt_pct', 0),
        'contingency_pct' => $g('costing_contingency_pct', 0.5),
        'negotiation_pct' => $g('costing_negotiation_pct', 5),
        'food_per_day'    => $g('costing_food_per_day', 250),
    ];
}

function pc_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS project_costings (
            id $pk,
            code VARCHAR(40) DEFAULT '', title VARCHAR(200) DEFAULT '',
            client_id INT NULL, office_id INT NULL, site VARCHAR(200) DEFAULT '',
            basis VARCHAR(16) DEFAULT 'LONG', load_on VARCHAR(8) DEFAULT 'COST',
            overhead_pct DECIMAL(6,3) DEFAULT 0, insurance_pct DECIMAL(6,3) DEFAULT 0,
            baddebt_pct DECIMAL(6,3) DEFAULT 0, contingency_pct DECIMAL(6,3) DEFAULT 0,
            negotiation_pct DECIMAL(6,3) DEFAULT 0,
            status VARCHAR(16) DEFAULT 'DRAFT',
            approval_ref VARCHAR(120) DEFAULT '', approved_by VARCHAR(150) DEFAULT '',
            approved_at VARCHAR(30) DEFAULT '', reject_note VARCHAR(400) DEFAULT '',
            quote_id INT NULL, opportunity_id INT NULL, requisition_id INT NULL,
            inclusions TEXT, exclusions TEXT, notes TEXT,
            exp_revenue DECIMAL(18,2) DEFAULT 0, exp_cost DECIMAL(18,2) DEFAULT 0, exp_profit DECIMAL(18,2) DEFAULT 0,
            created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
        // One row per approval level the costing must pass (owner, 20 Oct 2026:
        // "approval level as of quotation shall be applicable").
        db()->exec("CREATE TABLE IF NOT EXISTS project_costing_approvals (
            id $pk, costing_id INT NOT NULL, level INT DEFAULT 1,
            approver_role VARCHAR(40) DEFAULT '', approver_user_id INT NULL,
            status VARCHAR(12) DEFAULT 'PENDING', acted_by VARCHAR(150) DEFAULT '', acted_at VARCHAR(30) DEFAULT '',
            remarks VARCHAR(400) DEFAULT '')");
        db()->exec("CREATE TABLE IF NOT EXISTS project_costing_lines (
            id $pk,
            costing_id INT NOT NULL, seq INT DEFAULT 0,
            group_label VARCHAR(120) DEFAULT '', role_label VARCHAR(160) DEFAULT '',
            heads TEXT,                                  -- JSON {head_code: amount/month}
            boq_qty DECIMAL(12,2) DEFAULT 0,             -- man-months / man-days / 1
            target_margin DECIMAL(6,2) DEFAULT 0,        -- % used to derive the sell rate
            rate_manual DECIMAL(16,2) DEFAULT 0,         -- overrides the derived rate when > 0
            oneoff DECIMAL(14,2) DEFAULT 0,              -- one-time mobilisation cost / person
            created_at VARCHAR(30) DEFAULT '')");
    } catch (Throwable $e) {}
    // Additive columns for older installs.
    foreach ([['project_costings','requisition_id','INT NULL'],
              ['project_costings','load_on',"VARCHAR(8) DEFAULT 'COST'"],
              ['project_costing_lines','oneoff',"DECIMAL(14,2) DEFAULT 0"],
              // Who prepared it, by id — the owner's rule D-11 B: its author never approves it.
              ['project_costings','created_by_user_id','INT NULL'],
              // Owner, 20 Oct 2026: a costing is tagged with the enquiry or lead it
              // prices, and its business unit decides which SBU Head approves it.
              ['project_costings','inquiry_id','INT NULL'],
              ['project_costings','lead_id','INT NULL'],
              ['project_costings','sbu',"VARCHAR(20) DEFAULT ''"],
              // Owner, 9 Nov 2026 — how many people each role needs, so a hiring
              // request raised from it asks for the right number.
              ['project_costing_lines','headcount',"INT DEFAULT 1"],
              // Owner, 9 Nov 2026 — the role's flexible list of cost heads
              // (lib/costing_heads.php): amount / % / qty × rate / formula,
              // per month / day / year / one time, with remarks.
              ['project_costing_lines','spec',"TEXT"],
              // Owner, 12 Nov 2026 — which approval level a step came from, so a
              // change of approver reaches the costings already waiting.
              ['project_costing_approvals','rule_id',"INT DEFAULT 0"]] as $c) {
        if (function_exists('ensure_column')) ensure_column($c[0], $c[1], $c[2]);
    }
}

// Who may open / edit costings: anyone who works either side of it (sells or
// hires) plus management. Cross-module by design, like the advisor.
// MILESTONE 7. A costing sheet is a SALES or HIRING artefact — it prices a
// manpower quotation or budgets the roles a requisition has to fill. It is
// module-neutral between those two, which is why the guards below name both.
//
// What it is not is free. pc_can() fell back to is_admin_level() || is_master()
// with no module question, and the whole project-costing family is absent from
// the route map, so neither the screen nor the printable sheet
// (/project-costing-print) ever met the entitlement gate. A company with neither
// module could open and print client names, cost build-ups, margins and sell
// rates.
//
// Asked once here, so the screen and the printed export can never disagree:
// ops_projcosting() requires pc_can() before it dispatches any of its routes,
// the print route included.
function pc_modules_live() {
    if (!function_exists('licence_module_live')) return true;
    foreach (['quotes', 'inquiries', 'hiring'] as $m) if (licence_module_live($m)) return true;
    return false;   // neither Sales & CRM nor People & hiring — nothing to price
}
function pc_can() {
    if (!pc_modules_live()) return false;
    return function_exists('can') && (
        can('mod.quotes.view') || can('mod.hiring.view') || can('mod.inquiries.view')
        || (function_exists('is_admin_level') && is_admin_level()) || (function_exists('is_master') && is_master()));
}
function pc_can_edit() {
    if (!pc_modules_live()) return false;
    return function_exists('can') && (
        can('mod.quotes.edit') || can('mod.hiring.edit')
        || (function_exists('is_admin_level') && is_admin_level()) || (function_exists('is_master') && is_master()));
}
function pc_can_approve() {
    if (!pc_modules_live()) return false;
    return (function_exists('is_admin_level') && is_admin_level()) || (function_exists('is_master') && is_master())
        || (function_exists('can') && can('crm.quote.approve'));
}

// D-11 B (the owner, 20 Oct 2026: "No") — the person who prepared a costing never
// approves it, whatever their role. Older sheets carry only the author's name.
function pc_is_author($h) {
    $u = current_user(); $me = (int)($u['id'] ?? 0);
    if (!empty($h['created_by_user_id'])) return $me > 0 && (int)$h['created_by_user_id'] === $me;
    return $me > 0 && trim((string)($h['created_by'] ?? '')) !== '' && trim((string)$h['created_by']) === user_name($u);
}
// The enquiry or lead a costing is made for, and what it fills in. Branch scope
// applies: you tag only an enquiry or lead you can see.
function pc_link_source($inqId, $leadId) {
    $out = [];
    if ($inqId) {
        $i = ops_one("SELECT * FROM crm_inquiries WHERE id=?", [(int)$inqId]);
        if (!$i || (function_exists('crm_inquiry_in_scope') && !crm_inquiry_in_scope($i))) return ['err' => 'That enquiry is not one you can see.'];
        $out = ['client_id' => (int)($i['client_id'] ?? 0), 'office_id' => (int)($i['office_id'] ?? 0), 'sbu' => (string)($i['sbu'] ?? ''),
                'title' => trim((string)($i['subject'] ?? '')) ?: (string)($i['inquiry_no'] ?? ''), 'lead_id' => (int)($i['lead_id'] ?? 0)];
    }
    if ($leadId) {
        $l = function_exists('lead_row') ? lead_row((int)$leadId) : null;
        if (!$l || (function_exists('scope_office_allows') && !scope_office_allows($l['office_id'] ?? null))) return ['err' => 'That lead is not one you can see.'];
        $out += ['client_id' => (int)($l['partner_id'] ?? 0), 'office_id' => (int)($l['office_id'] ?? 0), 'sbu' => (string)($l['sbu'] ?? ''),
                 'title' => trim((string)$l['company_name'] . ((string)($l['requirement'] ?? '') !== '' ? ' — ' . mb_substr((string)$l['requirement'], 0, 80) : ''))];
        $out['lead_id'] = (int)$leadId;
    }
    return $out;
}

// ---- Approval levels: the quotation levels, applied to the costing -----------
//  The owner, 20 Oct 2026: "project costing is mandatory … approval level as of
//  quotation shall be applicable." Submitting builds the same steps a quotation
//  of the costing's sell value (before tax) and business unit would need — the
//  levels on Set up Sales, in order, each going to the people over the
//  costing's branch and business unit (crm_role_approvers_for). Its author never
//  signs (D-11 B). With no levels set up, one step: anyone who may approve.
function pc_value($h) {
    $r = pc_rollup((int)$h['id']);
    return $r ? (float)$r['totals']['revenue'] : (float)($h['exp_revenue'] ?? 0);
}
function pc_as_quote($h) {
    return ['subtotal' => pc_value($h), 'sbu' => (string)($h['sbu'] ?? ''), 'office_id' => $h['office_id'] ?? null];
}
function pc_build_approvals($h) {
    $pdo = db(); $id = (int)$h['id'];
    $pdo->prepare("DELETE FROM project_costing_approvals WHERE costing_id=?")->execute([$id]);
    $steps = function_exists('crm_quote_rule_steps') ? crm_quote_rule_steps(pc_as_quote($h)) : [];
    $ins = $pdo->prepare("INSERT INTO project_costing_approvals (costing_id, level, approver_role, approver_user_id, status, rule_id) VALUES (?,?,?,?,'PENDING',?)");
    if (!$steps) { $ins->execute([$id, 1, '', null, 0]); return 1; }
    foreach ($steps as $s) {
        $role = (string)($s['approver_role'] ?? ''); $uid = ($s['approver_user_id'] ?? null) ?: null;
        if ($role === 'REPORTS_TO') {   // the author's reporting manager, N levels up
            $uid = (!empty($h['created_by_user_id']) && function_exists('reporting_manager_at')) ? (reporting_manager_at((int)$h['created_by_user_id'], (int)$s['level']) ?: null) : null;
            $role = '';
        }
        $ins->execute([$id, (int)$s['level'], $role, $uid, (int)($s['id'] ?? 0)]);
    }
    return count($steps);
}
function pc_steps($id) {
    return ops_all("SELECT * FROM project_costing_approvals WHERE costing_id=? ORDER BY level, id", [(int)$id]) ?: [];
}
function pc_current_level($id) {
    return (int)ops_val("SELECT MIN(level) FROM project_costing_approvals WHERE costing_id=? AND status='PENDING'", [(int)$id]);
}
// The people a step waits for (named person, the role over the costing, or anyone who may approve).
function pc_step_people($step, $h) {
    if (!empty($step['approver_user_id'])) {
        $u = ops_one("SELECT id, username, first_name, last_name, email, role FROM users WHERE id=? AND is_active=1", [(int)$step['approver_user_id']]);
        return $u ? [$u] : [];
    }
    $roles = [];
    if (($step['approver_role'] ?? '') !== '') $roles = [(string)$step['approver_role']];
    else foreach (ORG_ROLES as $rk => $rl) if (in_array('crm.quote.approve', role_perms($rk) ?: [], true) || in_array($rk, defined('MGMT_ROLES') ? MGMT_ROLES : [], true)) $roles[] = $rk;
    $people = function_exists('crm_role_approvers_for') ? crm_role_approvers_for($roles, pc_as_quote($h)) : [];
    $author = (int)($h['created_by_user_id'] ?? 0);
    $out = array_values(array_filter($people, fn($u) => (int)$u['id'] !== $author));
    //  The author is the only person holding this level (a Marketing Manager who
    //  prepared it, at a "Marketing Manager" level): it may not wait forever on
    //  someone who can never sign it — anyone else who may approve signs instead.
    if (!$out && $roles && ($step['approver_role'] ?? '') !== '')
        return pc_step_people(['approver_role' => '', 'approver_user_id' => null] + $step, $h);
    return $out;
}
// The levels that may sign now: the lowest waiting one, or — when the company
// chose that all levels sign at the same time (Set up Sales) — every waiting one.
function pc_open_levels($id) {
    if (function_exists('crm_approval_mode') && crm_approval_mode() === 'together')
        return array_map('intval', array_column(ops_all("SELECT DISTINCT level FROM project_costing_approvals WHERE costing_id=? AND status='PENDING' ORDER BY level", [(int)$id]) ?: [], 'level'));
    $l = pc_current_level($id);
    return $l ? [$l] : [];
}
// The lowest open level the person signed in may sign (0 = none).
function pc_my_level($h) {
    if ((string)($h['status'] ?? '') !== 'SUBMITTED' || pc_is_author($h)) return 0;
    $open = pc_open_levels((int)$h['id']); if (!$open) return 0;
    if (function_exists('is_master') && is_master()) return $open[0];
    $me = (int)(current_user()['id'] ?? 0);
    foreach ($open as $lvl) foreach (pc_steps((int)$h['id']) as $s) {
        if ((int)$s['level'] !== $lvl || $s['status'] !== 'PENDING') continue;
        foreach (pc_step_people($s, $h) as $u) if ((int)$u['id'] === $me) return $lvl;
    }
    return 0;
}
// May the person signed in sign this costing now?
function pc_can_act($h) {
    if ((string)($h['status'] ?? '') !== 'SUBMITTED' || pc_is_author($h)) return false;
    if (!pc_steps((int)$h['id'])) return pc_can_approve();   // submitted before levels existed: the old rule
    return pc_my_level($h) > 0;
}
// Sign the level that is mine. Returns 'APPROVED' when nothing is left, else 'NEXT'.
function pc_sign_level($h, $ref = '') {
    $id = (int)$h['id']; $lvl = pc_my_level($h) ?: pc_current_level($id); $me = current_user();
    if ($lvl) db()->prepare("UPDATE project_costing_approvals SET status='APPROVED', acted_by=?, acted_at=?, remarks=? WHERE costing_id=? AND level=? AND status='PENDING'")
        ->execute([user_name($me), date('c'), substr(trim((string)$ref), 0, 400), $id, $lvl]);
    if (pc_current_level($id)) return 'NEXT';
    db()->prepare("UPDATE project_costings SET status='APPROVED', approved_by=?, approved_at=?, approval_ref=?, updated_at=? WHERE id=?")
        ->execute([user_name($me), date('c'), trim((string)$ref), date('c'), $id]);
    return 'APPROVED';
}
// Costings waiting at a level the person signed in may sign (for Approvals).
function pc_waiting_on_me() {
    if (!pc_modules_live()) return [];
    pc_migrate();
    $out = [];
    foreach (ops_all("SELECT * FROM project_costings WHERE status='SUBMITTED' ORDER BY updated_at DESC LIMIT 300") ?: [] as $h)
        if (pc_can_act($h)) $out[] = $h;
    return $out;
}

// How a costing may move — `docs/03-object-lifecycles.md` §Project costing.
// Returns '' when allowed, else the sentence that says why not.
function pc_transition_problem($h, $route) {
    $st = (string)($h['status'] ?? 'DRAFT');
    switch ($route) {
        case 'project-costing-submit':
            if (!in_array($st, ['DRAFT', 'REJECTED'], true)) return 'Only a draft or a sent-back costing can be submitted.';
            if (!pc_lines((int)$h['id'])) return 'Add at least one role before submitting.';
            return '';
        case 'project-costing-approve':
            if ($st !== 'SUBMITTED') return 'Only a submitted costing can be approved — this one is ' . strtolower(PC_STATUS[$st] ?? $st) . '.';
            if (pc_is_author($h)) return 'You prepared this costing, so someone else must approve it.';
            if (!pc_can_act($h)) return 'This costing is waiting for someone else at its current approval level.';
            return '';
        case 'project-costing-reject':
            if ($st !== 'SUBMITTED') return 'Only a submitted costing can be sent back.';
            if (!pc_can_act($h)) return 'Only an approver at its current level can send it back.';
            if (trim((string)($_POST['reject_note'] ?? '')) === '') return 'Say why it is being sent back, so its author knows what to change.';
            return '';
        case 'project-costing-reopen':
            if ($st === 'DRAFT') return 'This costing is already a draft.';
            return '';
    }
    return '';
}

// ---- Reads -----------------------------------------------------------------
function pc_get($id) { pc_migrate(); return ops_one("SELECT * FROM project_costings WHERE id=?", [(int)$id]) ?: null; }
function pc_lines($id) {
    pc_migrate();
    return ops_all("SELECT * FROM project_costing_lines WHERE costing_id=? ORDER BY seq, id", [(int)$id]) ?: [];
}
function pc_all($limit = 200, array $f = []) {
    pc_migrate();
    // M16 (product decision, Option B) — costings follow BRANCH SCOPE.
    //
    // A costing carries day rates, overheads, contingency, negotiation margin and
    // expected revenue: the commercially sensitive numbers. Until now this list
    // applied no scope at all, so every holder of the costing permission saw every
    // branch's margins. The business decision is that they should not, so the
    // register is scoped the same way leads, complaints and receipts are — by
    // office, with an UNASSIGNED costing still visible to everyone so that work
    // nobody has filed to a branch does not disappear.
    [$sw, $sa] = function_exists('scope_office_clause') ? scope_office_clause('c.office_id') : ['1=1', []];
    // MOD-REP (owner, 9 Nov 2026) — "if we unlink the costing from the quotation
    // it is lost". It never was deleted; the register simply gave no way to find
    // it. It can now be searched, and narrowed to the costings on no quotation.
    $q = trim((string)($f['q'] ?? ''));
    if ($q !== '') {
        $sw .= " AND (c.code LIKE ? OR c.title LIKE ? OR COALESCE(p.display_name,p.legal_name,'') LIKE ? OR COALESCE(qq.quote_no,'') LIKE ?)";
        $like = '%' . $q . '%'; array_push($sa, $like, $like, $like, $like);
    }
    if (($f['link'] ?? '') === 'none') $sw .= " AND COALESCE(c.quote_id,0)=0";
    return ops_all("SELECT c.*, COALESCE(p.display_name,p.legal_name) client_name, qq.quote_no, qq.rev quote_rev
                    FROM project_costings c LEFT JOIN business_partners p ON p.id=c.client_id
                    LEFT JOIN quotations qq ON qq.id=c.quote_id
                    WHERE $sw
                    ORDER BY c.id DESC LIMIT " . (int)$limit, $sa) ?: [];
}
// The costing linked to a given quotation / opportunity (or null).
// A quotation's costing is the one linked to ANY of its revisions: a revision is
// the same quotation re-priced, so revising it must not make its costing vanish
// from the panel (the approval check already looked across revisions).
function pc_for_quote($qid) {
    pc_migrate(); if (!$qid) return null;
    $base = (int)(ops_val("SELECT COALESCE(NULLIF(parent_id,0), id) FROM quotations WHERE id=?", [(int)$qid]) ?: (int)$qid);
    return ops_one("SELECT * FROM project_costings WHERE quote_id IN (SELECT id FROM quotations WHERE id=? OR parent_id=?) OR quote_id=? ORDER BY id DESC LIMIT 1", [$base, $base, (int)$qid]) ?: null;
}
function pc_for_opportunity($oid) { pc_migrate(); return $oid ? (ops_one("SELECT * FROM project_costings WHERE opportunity_id=? ORDER BY id DESC LIMIT 1", [(int)$oid]) ?: null) : null; }

// Module 20 — the bid estimate behind a CONTRACT (a boss number). A boss number
// IS the contract number (created from the winning quotation), so the estimate is
// found by matching the boss number to the contract number carried on the quote
// the costing is linked to. Derived on read — no stored column, works for existing
// data — and prefers the APPROVED costing, then the latest. Null when a contract
// was won without a pre-award costing (a direct win, or legacy).
function pc_for_boss($bossId) {
    pc_migrate();
    $bossId = (int)$bossId; if (!$bossId) return null;
    $boss = ops_one("SELECT boss_number, client_id FROM boss_numbers WHERE id=?", [$bossId]);
    $num = trim((string)($boss['boss_number'] ?? ''));
    if ($num === '') return null;
    return ops_one(
        "SELECT pc.* FROM project_costings pc
         JOIN quotations q ON q.id = pc.quote_id
         WHERE q.contract_number = ?
         ORDER BY (pc.status='APPROVED') DESC, pc.id DESC LIMIT 1", [$num]) ?: null;
}

// Module 20 — Estimated (the bid) vs Actual (the delivered contract), side by
// side. Reuses the ONE canonical actuals engine (boss_profit) and the estimate's
// own rollup (exp_*); it invents no new margin number — variance is subtraction.
// Null when no bid estimate is linked to the contract.
function pc_estimate_vs_actual($bossId) {
    $pc = pc_for_boss($bossId);
    if (!$pc || !function_exists('boss_profit')) return null;
    $act = boss_profit((int)$bossId);
    $estRev = (float)($pc['exp_revenue'] ?? 0);
    $estCost = (float)($pc['exp_cost'] ?? 0);
    $estProfit = (float)($pc['exp_profit'] ?? ($estRev - $estCost));
    $estPct = $estRev > 0 ? round($estProfit / $estRev * 100, 1) : null;
    $actRev = (float)($act['revenue'] ?? 0);
    $actProfit = (float)($act['margin'] ?? 0);
    $actPct = $act['pct'] ?? null;
    return [
        'costing' => $pc,
        'est' => ['revenue' => $estRev, 'cost' => $estCost, 'profit' => $estProfit, 'pct' => $estPct],
        'act' => ['revenue' => $actRev, 'cost' => round($actRev - $actProfit, 2), 'profit' => $actProfit, 'pct' => $actPct],
        'var' => ['profit' => round($actProfit - $estProfit, 2),
                  'pct' => ($actPct !== null && $estPct !== null) ? round($actPct - $estPct, 1) : null],
    ];
}
// Costings not yet linked to any quote/opportunity — the "attach existing" list.
// $for 'quote': every costing on no quotation (one still tagged to a deal may be
// attached to that deal's quotation); 'opportunity': every costing on no deal.
// The same client's costings come first; branch scope as the register.
function pc_unlinked($limit = 100, $for = 'quote', $clientId = 0) {
    pc_migrate();
    [$sw, $sa] = function_exists('scope_office_clause') ? scope_office_clause('c.office_id') : ['1=1', []];
    $col = $for === 'opportunity' ? 'opportunity_id' : 'quote_id';
    array_unshift($sa, (int)$clientId);
    return ops_all("SELECT c.id, c.code, c.title, c.exp_revenue, c.status, COALESCE(p.display_name,p.legal_name) client_name
                    FROM project_costings c LEFT JOIN business_partners p ON p.id=c.client_id
                    WHERE COALESCE(c.$col,0)=0 AND $sw
                    ORDER BY (COALESCE(c.client_id,0)=?) DESC, c.id DESC LIMIT " . (int)$limit, $sa) ?: [];
}

// ---- The maths -------------------------------------------------------------
// One line's numbers, given the header (for the loading %s and basis).
function pc_line_calc($line, $header) {
    $basis = (string)($header['basis'] ?? 'LONG');
    $ev = null;
    if (trim((string)($line['spec'] ?? '')) !== '' && function_exists('ch_evaluate')) {
        // A role built from flexible heads: worked out by the heads engine, in
        // the sheet's own unit (per man-month, or per man-day on a daily sheet).
        $sp = ch_spec_of($line, $basis);
        $ev = ch_evaluate($sp['items'], ch_unit_for_basis($basis), $sp['dpm']);
        $heads = $ev['heads']; $direct = (float)$ev['direct']; $labels = $ev['labels'];
        $pay = (float)$ev['pay']; $oneoffPer = (float)$ev['once'];
    } else {
        $heads = json_decode((string)($line['heads'] ?? ''), true);
        if (!is_array($heads)) $heads = [];
        $direct = 0.0; foreach ($heads as $v) $direct += (float)$v;
        $names = pc_heads(); $labels = [];
        foreach ($heads as $k => $v) $labels[$k] = $names[$k] ?? $k;
        $pay = (float)($heads['PERSONNEL'] ?? 0); $oneoffPer = (float)($line['oneoff'] ?? 0);
    }

    $load = (float)($header['overhead_pct'] ?? 0) + (float)($header['insurance_pct'] ?? 0)
          + (float)($header['baddebt_pct'] ?? 0) + (float)($header['contingency_pct'] ?? 0);
    // Loadings can be recovered either as a % of the direct COST (the standard
    // build-up) or as a % of the SELL RATE (the client's own sheet: "overhead @
    // 8%" of the man-month rate). The base is set on the header.
    $onRate = strtoupper((string)($header['load_on'] ?? 'COST')) === 'RATE';
    $tm = (float)($line['target_margin'] ?? 0);
    $rate = (float)($line['rate_manual'] ?? 0);
    if ($onRate) {
        if ($rate <= 0) {
            $denom = 1 - $tm / 100 - $load / 100;
            $rate = ($tm > 0 && $denom > 0) ? $direct / $denom : $direct;
        }
        $loaded = $direct + $rate * $load / 100;         // cost = direct + loadings on the rate
    } else {
        $loaded = $direct * (1 + $load / 100);
        if ($rate <= 0) $rate = ($tm > 0 && $tm < 100) ? $loaded / (1 - $tm / 100) : $loaded;
    }
    $neg = (float)($header['negotiation_pct'] ?? 0);
    $proposed = $rate * (1 + $neg / 100);

    $boq = max(0, (float)($line['boq_qty'] ?? 0));
    // The one-time cost is per person (the form always said so): it counts once
    // for each of the role's people.
    $oneoff = $oneoffPer * max(1, (int)($line['headcount'] ?? 1));
    if ($basis === 'FIXED') { $qty = 1; $revenue = $proposed; $cost = $loaded + $oneoff; }
    else                    { $qty = $boq; $revenue = $proposed * $qty; $cost = $loaded * $qty + $oneoff; }
    $profit = $revenue - $cost;
    $margin = $revenue > 0 ? $profit / $revenue * 100 : 0;

    return ['direct' => round($direct, 2), 'loaded' => round($loaded, 2),
            'rate' => round($rate, 2), 'proposed' => round($proposed, 2),
            'qty' => $qty, 'oneoff' => round($oneoffPer, 2), 'oneoff_total' => round($oneoff, 2),
            'revenue' => round($revenue, 2), 'cost' => round($cost, 2),
            'profit' => round($profit, 2), 'margin' => round($margin, 2), 'heads' => $heads,
            // What the person is paid (the salary heads) — the requisition's wage
            // and, later, what a hired candidate's actual pay is compared with.
            'pay' => round($pay, 2), 'labels' => $labels, 'eval' => $ev];
}

// Project rollup: totals + per-head totals across every line.
function pc_rollup($id) {
    $h = pc_get($id); if (!$h) return null;
    $lines = pc_lines($id);
    $tot = ['revenue' => 0, 'cost' => 0, 'profit' => 0, 'direct' => 0, 'oneoff' => 0, 'lines' => 0];
    $byHead = []; $labels = [];
    foreach ($lines as $ln) {
        $c = pc_line_calc($ln, $h);
        $tot['revenue'] += $c['revenue']; $tot['cost'] += $c['cost']; $tot['profit'] += $c['profit'];
        $tot['direct'] += $c['direct']; $tot['oneoff'] += $c['oneoff_total']; $tot['lines']++;
        foreach ($c['heads'] as $k => $v) $byHead[$k] = ($byHead[$k] ?? 0) + (float)$v;
        foreach ($c['labels'] as $k => $l) if (!isset($labels[$k])) $labels[$k] = $l;
    }
    $tot['margin'] = $tot['revenue'] > 0 ? round($tot['profit'] / $tot['revenue'] * 100, 2) : 0;
    // A head's column name: the role's own name for it, else the catalogue's.
    $labels += pc_heads();
    return ['header' => $h, 'lines' => $lines, 'totals' => $tot, 'by_head' => $byHead, 'labels' => $labels];
}

// Recompute and store the header's headline figures (for lists / links).
function pc_restamp($id) {
    $r = pc_rollup($id); if (!$r) return;
    // Keep each flexible role's stored amounts in step with its heads (a change
    // of basis turns per-month figures into per-day ones), for older readers.
    foreach ($r['lines'] as $ln) {
        if (trim((string)($ln['spec'] ?? '')) === '') continue;
        $c = pc_line_calc($ln, $r['header']);
        try { db()->prepare("UPDATE project_costing_lines SET heads=?, oneoff=? WHERE id=?")
                  ->execute([json_encode($c['heads'], JSON_UNESCAPED_UNICODE), $c['oneoff'], (int)$ln['id']]); } catch (Throwable $e) {}
    }
    try {
        db()->prepare("UPDATE project_costings SET exp_revenue=?, exp_cost=?, exp_profit=?, updated_at=? WHERE id=?")
            ->execute([$r['totals']['revenue'], $r['totals']['cost'], $r['totals']['profit'], date('c'), (int)$id]);
    } catch (Throwable $e) {}
}

// A locked (submitted/approved) sheet is read-only until sent back to draft.
function pc_locked($h) { return in_array((string)($h['status'] ?? 'DRAFT'), ['SUBMITTED', 'APPROVED'], true); }

// Basis → the unit word a quote line / requirement uses.
function pc_unit($basis) { return $basis === 'DAILY' ? 'MANDAY' : ($basis === 'FIXED' ? 'LOT' : 'MANMONTH'); }
function pc_req_basis($basis) { return $basis === 'DAILY' ? 'MANDAY' : ($basis === 'FIXED' ? 'FIXED' : 'MONTHLY'); }

// A costing started FROM a quotation begins with that quotation's man-month (or
// man-day) lines: the same description, quantity and — as the fixed sell rate —
// the price already quoted, with no negotiation buffer on top. The person then
// adds each role's cost heads and sees the real margin AT THAT PRICE (owner, test
// book MOD-SAL: "I have provided 1,20,000 per man-month in the quotation … I got
// rate as 1,23,418 which is not as per the quotation"). An empty costing used to
// work out its own rate from cost + target margin, and its own quantity.
// Returns the number of lines copied.
function pc_seed_from_quote($costingId, $quoteId) {
    $costingId = (int)$costingId; $quoteId = (int)$quoteId;
    if (!$costingId || !$quoteId) return 0;
    if ((int)ops_val("SELECT COUNT(*) FROM project_costing_lines WHERE costing_id=?", [$costingId])) return 0;   // never over a costing already filled
    $ql = ops_all("SELECT description, qty, unit, rate FROM quote_lines WHERE quote_id=? ORDER BY line_no, id", [$quoteId]) ?: [];
    $units = array_map(fn($l) => strtoupper((string)$l['unit']), $ql);
    $unit = in_array('MANMONTH', $units, true) ? 'MANMONTH' : (in_array('MANDAY', $units, true) ? 'MANDAY' : '');
    if ($unit === '') return 0;
    db()->prepare("UPDATE project_costings SET basis=?, negotiation_pct=0, updated_at=? WHERE id=?")
        ->execute([$unit === 'MANDAY' ? 'DAILY' : 'LONG', date('c'), $costingId]);
    $ins = db()->prepare("INSERT INTO project_costing_lines (costing_id,seq,group_label,role_label,heads,boq_qty,target_margin,rate_manual,oneoff,created_at) VALUES (?,?,'',?,'{}',?,0,?,0,?)");
    $n = 0;
    foreach ($ql as $l) {
        if (strtoupper((string)$l['unit']) !== $unit) continue;
        $ins->execute([$costingId, ++$n, mb_substr(trim((string)$l['description']) ?: 'Role', 0, 160), (float)$l['qty'], (float)$l['rate'], date('c')]);
    }
    if ($n) pc_restamp($costingId);
    return $n;
}

// Draft a quotation from a costing: one quote line per role, priced at the
// proposed rate × BOQ. Links the quote back to the costing. Returns ['id','no'].
function pc_make_quote($roll) {
    $h = $roll['header']; $lines = $roll['lines'];
    if (empty($lines)) return ['err' => 'Add at least one role first.'];
    try {
        $no = ops_next_code('quotations', 'quote_no', 'Q');
        $sub = 0; $unit = pc_unit((string)$h['basis']);
        foreach ($lines as $ln) { $c = pc_line_calc($ln, $h); $sub += $c['revenue']; }
        $gstPct = 18; $gst = round($sub * $gstPct / 100, 2); $total = $sub + $gst;
        db()->prepare("INSERT INTO quotations (quote_no,rev,is_current,client_id,client_name,office_id,subject,site_location,
                        currency,validity_days,subtotal,gst_pct,gst_amount,total_amount,status,owner_id,created_by,created_at)
                       VALUES (?,0,1,?,?,?,?,?, 'INR',30,?,?,?,?, 'DRAFT',?,?,?)")
            ->execute([$no, (int)$h['client_id'] ?: null,
                (string)ops_val("SELECT COALESCE(display_name,legal_name) FROM business_partners WHERE id=?", [(int)$h['client_id']]),
                (int)$h['office_id'] ?: null, (string)$h['title'], (string)$h['site'],
                $sub, $gstPct, $gst, $total, (int)(current_user()['id'] ?? 0) ?: null, user_name(current_user()), date('c')]);
        $qid = (int)db()->lastInsertId();
        $n = 0;
        foreach ($lines as $ln) {
            $c = pc_line_calc($ln, $h); $n++;
            $desc = trim(((string)$ln['group_label'] !== '' ? $ln['group_label'] . ' — ' : '') . (string)$ln['role_label']);
            db()->prepare("INSERT INTO quote_lines (quote_id,line_no,description,order_type,qty,unit,rate,amount)
                           VALUES (?,?,?, 'LINE', ?,?,?,?)")
                ->execute([$qid, $n, $desc, ($h['basis'] === 'FIXED' ? 1 : (float)$ln['boq_qty']), $unit, $c['proposed'], $c['revenue']]);
        }
        db()->prepare("UPDATE project_costings SET quote_id=? WHERE id=?")->execute([$qid, (int)$h['id']]);
        // The quotation belongs to the same enquiry, lead and business unit as
        // the costing it was made from — and is worked by its preparer.
        db()->prepare("UPDATE quotations SET inquiry_id=?, lead_id=?, sbu=? WHERE id=?")
            ->execute([(int)($h['inquiry_id'] ?? 0) ?: null, (int)($h['lead_id'] ?? 0) ?: null, (string)($h['sbu'] ?? ''), $qid]);
        return ['id' => $qid, 'no' => $no];
    } catch (Throwable $e) { return ['err' => $e->getMessage()]; }
}

// Create a recruitment requirement from one role line. Carries the per-person
// economics (loaded cost, proposed rate, target margin); headcount/duration are
// left for the recruiter to set. Returns ['id','code'].
// ---------------------------------------------------------------------------
//  FROM A WON COSTING TO RECRUITMENT (owner, 9 Nov 2026)
//
//  "if quote is won or accepted by client then only the hiring request or
//  requisition button shall be shown" — and the request then goes through the
//  existing hiring-request approvals. So recruitment can start from a costing
//  only when (1) the costing is APPROVED and (2) a revision of its quotation is
//  ACCEPTED by the client. Each role asks for its own number of people, never
//  more than were costed, and carries the costing's budget into recruitment.
// ---------------------------------------------------------------------------

// The accepted revision of the costing's quotation, or null.
function pc_accepted_quote($h) {
    $qid = (int)($h['quote_id'] ?? 0); if (!$qid) return null;
    $q = ops_one("SELECT id, parent_id FROM quotations WHERE id=?", [$qid]); if (!$q) return null;
    $base = (int)($q['parent_id'] ?? 0) ?: (int)$q['id'];
    return ops_one("SELECT * FROM quotations WHERE (id=? OR parent_id=?) AND status='ACCEPTED' ORDER BY id DESC LIMIT 1", [$base, $base]) ?: null;
}
// '' when recruitment may start from this costing, or the reason it may not yet.
function pc_hire_gate($h) {
    if (strtoupper((string)($h['status'] ?? '')) !== 'APPROVED')
        return 'The costing must be approved first.';
    if (!(int)($h['quote_id'] ?? 0))
        return 'Create the quotation from this costing first — recruitment starts once the client accepts it.';
    if (!pc_accepted_quote($h)) {
        $q = ops_one("SELECT quote_no, status FROM quotations WHERE id=?", [(int)$h['quote_id']]);
        return 'Recruitment starts once the client accepts the quotation' . ($q ? ' (' . $q['quote_no'] . ' is ' . strtolower(str_replace('_', ' ', (string)$q['status'])) . ')' : '') . '.';
    }
    return '';
}
// How many of a role's people are already asked for: live hiring requests plus
// requisitions raised directly from the costing.
function pc_line_raised($lineId) {
    $n = 0;
    if (function_exists('hreq_costing_line_qty')) $n += hreq_costing_line_qty((int)$lineId);
    try { $n += (int)ops_val("SELECT COALESCE(SUM(quantity),0) FROM requisitions WHERE costing_line_id=? AND COALESCE(hiring_request_id,0)=0 AND UPPER(COALESCE(status,'')) <> 'CANCELLED'", [(int)$lineId]); } catch (Throwable $e) {}
    return $n;
}
function pc_line_people($ln) { return max(1, (int)($ln['headcount'] ?? 1)); }
// What has been raised from a role, for the costing page.
function pc_line_links($lineId) {
    $out = ['hreq' => [], 'req' => []];
    if (function_exists('hreq_for_costing_line')) $out['hreq'] = hreq_for_costing_line((int)$lineId);
    try { $out['req'] = ops_all("SELECT id, req_code, status, quantity FROM requisitions WHERE costing_line_id=? ORDER BY id", [(int)$lineId]) ?: []; } catch (Throwable $e) {}
    return $out;
}
function pc_link_migrate() {
    if (function_exists('hreq_costing_migrate')) hreq_costing_migrate();
    if (function_exists('req_migrate')) req_migrate();
    if (function_exists('ensure_column')) try { ensure_column('requisitions', 'costing_line_id', 'INT NULL'); } catch (Throwable $e) {}
}

// Raise a hiring request for one role of a won costing. It is created as a
// DRAFT, filled from the costing, for the people not yet asked for; the user
// checks it and submits it into the existing approvals. Returns [ok, msg, id].
function pc_raise_hiring_request($cid, $lineId) {
    $h = pc_get($cid); if (!$h) return [false, 'Costing not found.', 0];
    if (($why = pc_hire_gate($h)) !== '') return [false, $why, 0];
    $ln = ops_one("SELECT * FROM project_costing_lines WHERE id=? AND costing_id=?", [(int)$lineId, (int)$cid]);
    if (!$ln) return [false, 'Role not found.', 0];
    if (!function_exists('hreq_save')) return [false, 'Hiring requests are not available here.', 0];
    pc_link_migrate();
    $left = pc_line_people($ln) - pc_line_raised((int)$ln['id']);
    if ($left <= 0) return [false, 'All ' . pc_line_people($ln) . ' people for "' . $ln['role_label'] . '" are already asked for.', 0];
    $c = pc_line_calc($ln, $h);
    $q = pc_accepted_quote($h);
    $basis = (string)$h['basis'];
    $months = $basis === 'LONG' && (float)$ln['boq_qty'] > 0 ? round((float)$ln['boq_qty'] / pc_line_people($ln), 2) : null;
    $headsTxt = [];
    foreach ((array)$c['heads'] as $k => $v) $headsTxt[] = ($c['labels'][$k] ?? $k) . ': ' . number_format((float)$v);
    [$ok, $msg, $id] = hreq_save(0, [
        'job_title' => trim(((string)$ln['group_label'] !== '' ? $ln['group_label'] . ' / ' : '') . $ln['role_label']),
        'quantity' => $left,
        'office_id' => (int)($h['office_id'] ?? 0) ?: '',
        'client_id' => (int)($h['client_id'] ?? 0) ?: '',
        'work_location' => (string)($h['site'] ?? ''),
        'project_ref' => trim(($q ? $q['quote_no'] . ' · ' : '') . $h['code']),
        'reason' => 'Quotation ' . ($q['quote_no'] ?? '') . ' accepted by the client — costing ' . $h['code'] . ', role "' . $ln['role_label'] . '".',
        'job_description' => 'Costed per person per ' . ($basis === 'DAILY' ? 'day' : 'month') . ': ' . implode(' · ', $headsTxt)
                           . '. Loaded cost ' . number_format($c['loaded']) . ', billed at ' . number_format($c['proposed'])
                           . ' (' . number_format($c['margin'], 1) . '% margin).',
        'est_cost_per_person' => $c['loaded'],
        'est_cost_basis' => $basis === 'DAILY' ? 'MANDAY' : ($basis === 'FIXED' ? 'FIXED' : 'MONTHLY'),
        'est_duration_months' => $months,
        'est_onetime_cost' => (float)$c['oneoff'] > 0 ? (float)$c['oneoff'] : null,
    ]);
    if (!$ok) return [false, $msg, 0];
    hreq_link_costing((int)$id, (int)$cid, (int)$ln['id']);
    if (function_exists('act_log')) try { act_log('HIRING_REQUEST', (int)$id, 'SYSTEM', 'Raised from costing ' . $h['code'] . ' (role "' . $ln['role_label'] . '"), quotation ' . ($q['quote_no'] ?? '') . ' accepted'); } catch (Throwable $e) {}
    return [true, 'Hiring request created for ' . $left . ' × ' . $ln['role_label'] . '.', (int)$id];
}

// A requisition raised from a hiring request that came from a costing takes the
// costing's figures as its budget: the billing rate, the target margin and the
// cost build-up — so every hire is measured against what was priced to the client.
function pc_requisition_from_hreq($rid, array $hr) {
    $lineId = (int)($hr['costing_line_id'] ?? 0); if (!$lineId) return;
    $ln = ops_one("SELECT * FROM project_costing_lines WHERE id=?", [$lineId]); if (!$ln) return;
    $h = pc_get((int)$ln['costing_id']); if (!$h) return;
    pc_link_migrate();
    $c = pc_line_calc($ln, $h);
    $wage = (float)$c['pay'];          // the salary heads — what the person is paid
    $qty = (int)ops_val("SELECT quantity FROM requisitions WHERE id=?", [(int)$rid]) ?: 1;
    $b = ['quantity' => $qty, 'billing_rate' => $c['proposed'], 'budgeted_cost' => $c['loaded'], 'rate_basis' => pc_req_basis((string)$h['basis'])];
    $com = function_exists('req_commercials') ? req_commercials($b) : ['revenue' => 0, 'profit' => 0];
    try {
        db()->prepare("UPDATE requisitions SET costing_line_id=?, billing_rate=?, target_margin=?, budgeted_cost=?, rate_basis=?,
                       sourcing_model=?, cost_wage=?, cost_reimburse=?, cost_oneoff=?, expected_revenue=?, expected_profit=? WHERE id=?")
            ->execute([$lineId, $c['proposed'], (float)$ln['target_margin'], $c['loaded'], pc_req_basis((string)$h['basis']),
                       'OWN_PAYROLL', $wage, max(0, $c['direct'] - $wage), (float)$c['oneoff'],
                       (float)($com['revenue'] ?? 0), (float)($com['profit'] ?? 0), (int)$rid]);
    } catch (Throwable $e) {}
}

function pc_make_requisition($cid, $lineId) {
    //  ADR-001 (decided) — a costing spawns a requisition on the DIRECT path, as
    //  the route's own comment says, so the workspace policy applies here too.
    //  Asked in the function rather than on the route, because this is the write.
    //  Leaving it out would have left the policy with a hole: anyone refused on
    //  the requisition form could raise the same requirement from a costing.
    //  ADR-001 consequence §3 asked whether costing counts as separately
    //  authorised; a costing is a COMMERCIAL estimate and carries no headcount
    //  approval, so it does not.
    if (function_exists('hreq_direct_path_block_reason')
        && ($pcDirect = hreq_direct_path_block_reason()) !== '') {
        return ['err' => $pcDirect];
    }
    $h = pc_get($cid); if (!$h) return ['err' => 'Costing not found.'];
    if (($why = pc_hire_gate($h)) !== '') return ['err' => $why];
    $ln = ops_one("SELECT * FROM project_costing_lines WHERE id=? AND costing_id=?", [(int)$lineId, (int)$cid]);
    if (!$ln) return ['err' => 'Role not found.'];
    if (!function_exists('req_migrate')) return ['err' => 'Recruitment module is not available.'];
    req_migrate();
    pc_link_migrate();
    $people = pc_line_people($ln) - pc_line_raised((int)$ln['id']);
    if ($people <= 0) return ['err' => 'All ' . pc_line_people($ln) . ' people for "' . $ln['role_label'] . '" are already asked for.'];
    $c = pc_line_calc($ln, $h);
    $wage = (float)$c['pay'];                         // the salary heads
    $reimb = max(0, $c['direct'] - $wage);            // everything else, recurring
    try {
        $code = ops_next_code('requisitions', 'req_code', 'REQ');
        $b = ['quantity' => $people, 'billing_rate' => $c['proposed'], 'budgeted_cost' => $c['loaded'],
              'rate_basis' => pc_req_basis((string)$h['basis'])];
        $com = function_exists('req_commercials') ? req_commercials($b) : ['revenue' => 0, 'profit' => 0, 'months' => 0];
        db()->prepare("INSERT INTO requisitions
            (req_code, office_id, client_id, designation, project_site, req_type, status, quantity,
             billing_rate, rate_basis, target_margin, budgeted_cost,
             sourcing_model, cost_wage, cost_reimburse, cost_oneoff,
             expected_revenue, expected_profit, notes, created_by, created_at)
            VALUES (?,?,?,?,?, 'NEW', 'OPEN', " . (int)$people . ", ?,?,?,?, 'OWN_PAYROLL', ?,?,?, ?,?,?,?,?)")
            ->execute([$code, (int)$h['office_id'] ?: null, (int)$h['client_id'] ?: null,
                (string)$ln['role_label'], (string)$h['site'],
                $c['proposed'], pc_req_basis((string)$h['basis']), (float)$ln['target_margin'], $c['loaded'],
                $wage, $reimb, (float)$c['oneoff'],
                $com['revenue'], $com['profit'],
                'From costing ' . $h['code'] . ' · ' . trim(((string)$ln['group_label'] !== '' ? $ln['group_label'] . ' / ' : '') . $ln['role_label']),
                user_name(current_user()), date('c')]);
        $rid = (int)db()->lastInsertId();
        db()->prepare("UPDATE requisitions SET costing_line_id=? WHERE id=?")->execute([(int)$ln['id'], $rid]);
        return ['id' => $rid, 'code' => $code];
    } catch (Throwable $e) { return ['err' => $e->getMessage()]; }
}

// ---- Part 3 · the hired person's actual cost against the costing -------------
// Owner, 9 Nov 2026: "the rates of the candidates who are hired shall meet the
// margin as well as costing" — "Last candidate actual comparison. One at a time."
//
// A candidate recruited for a costed role (candidate → requisition → costing
// role) is measured one at a time: their actual monthly CTC (the offer, else the
// salary structure) takes the place of the costed salary + employer statutory
// heads; the role's other costs and its loadings stay; and the margin is
// worked out at the rate quoted to the client. The answer says whether the
// target margin still holds, and the highest CTC that keeps it.
function pc_cand_line(array $cand) {
    $rq = (int)($cand['requisition_id'] ?? 0); if (!$rq) return null;
    try { $lid = (int)ops_val("SELECT costing_line_id FROM requisitions WHERE id=?", [$rq]); } catch (Throwable $e) { return null; }
    if (!$lid) return null;
    $ln = ops_one("SELECT * FROM project_costing_lines WHERE id=?", [$lid]); if (!$ln) return null;
    $h = pc_get((int)$ln['costing_id']); if (!$h) return null;
    return [$ln, $h];
}
// The candidate's actual monthly CTC: the live offer's, else the salary
// structure's. [amount, source] or [null, ''].
function pc_cand_actual_ctc(array $cand) {
    $cid = (int)($cand['id'] ?? 0);
    if (function_exists('offer_current')) {
        $o = offer_current($cid);
        if ($o && !in_array((string)$o['status'], ['WITHDRAWN', 'DECLINED', 'EXPIRED'], true) && (float)$o['ctc'] > 0) return [(float)$o['ctc'], 'offer'];
    }
    if (function_exists('sal_current')) {
        $s = sal_current($cid);
        if ($s && (float)$s['gross_ctc'] > 0) return [(float)$s['gross_ctc'], 'salary structure'];
    }
    return [null, ''];
}
function pc_cand_check(array $cand, $ctcMonthly = null) {
    $pair = pc_cand_line($cand); if (!$pair) return null;
    [$ln, $h] = $pair;
    $src = 'given';
    if ($ctcMonthly === null) [$ctcMonthly, $src] = pc_cand_actual_ctc($cand);
    $c = pc_line_calc($ln, $h);
    $basis = (string)$h['basis'];
    $ev = $c['eval'] ?? null;
    $costedPS = $ev ? (float)$ev['by_grp']['PAY'] + (float)$ev['by_grp']['STAT']
                    : (float)($c['heads']['PERSONNEL'] ?? 0) + (float)($c['heads']['STATUTORY'] ?? 0);
    $dpm = $ev ? (float)$ev['dpm'] : (function_exists('ch_days_per_month') ? ch_days_per_month() : 26);
    $perDay = function_exists('ch_unit_for_basis') && ch_unit_for_basis($basis) === 'DAY';
    $L = (float)$h['overhead_pct'] + (float)$h['insurance_pct'] + (float)$h['baddebt_pct'] + (float)$h['contingency_pct'];
    $onRate = strtoupper((string)($h['load_on'] ?? 'COST')) === 'RATE';
    $R = (float)$c['proposed'];                       // what the client pays per unit
    $loadOf = fn($direct) => $onRate ? $direct + (float)$c['rate'] * $L / 100 : $direct * (1 + $L / 100);
    $marginOf = fn($loaded) => $R > 0 ? round(($R - $loaded) / $R * 100, 2) : 0.0;
    $costedMargin = $marginOf((float)$c['loaded']);
    $target = (float)$ln['target_margin'] > 0 ? (float)$ln['target_margin'] : $costedMargin;
    $other = (float)$c['direct'] - $costedPS;         // the role's costs that are not pay
    // The highest monthly CTC that keeps the target margin at the quoted rate.
    $maxLoaded = $R * (1 - $target / 100);
    $maxDirect = $onRate ? $maxLoaded - (float)$c['rate'] * $L / 100 : $maxLoaded / (1 + $L / 100);
    $maxUnit = $maxDirect - $other;
    $out = ['line' => $ln, 'costing' => $h, 'unit' => $perDay ? 'day' : 'month', 'dpm' => $dpm,
            'costed_pay' => round($perDay ? $costedPS * $dpm : $costedPS, 2), 'rate' => round($R, 2),
            'costed_margin' => $costedMargin, 'target' => round($target, 2),
            'max_ctc' => round(max(0, $perDay ? $maxUnit * $dpm : $maxUnit), 2),
            'ctc' => $ctcMonthly === null ? null : round((float)$ctcMonthly, 2), 'source' => $src,
            'margin' => null, 'verdict' => 'NONE'];
    if ($ctcMonthly === null) return $out;
    $actualUnit = $perDay ? (float)$ctcMonthly / max(1, $dpm) : (float)$ctcMonthly;
    $out['margin'] = $marginOf($loadOf($other + $actualUnit));
    $out['verdict'] = $out['margin'] <= 0 ? 'LOSS' : ($out['margin'] + 0.005 < $out['target'] ? 'LOW' : 'OK');
    return $out;
}
// The last candidate (most recent) recruited for a costing role who has an offer
// or a salary structure — "one at a time".
function pc_line_last_candidate($lineId) {
    try {
        $rows = ops_all("SELECT c.* FROM candidates c JOIN requisitions r ON r.id=c.requisition_id
                         WHERE r.costing_line_id=? ORDER BY c.id DESC LIMIT 20", [(int)$lineId]) ?: [];
    } catch (Throwable $e) { return null; }
    foreach ($rows as $cand) { [$ctc] = pc_cand_actual_ctc($cand); if ($ctc !== null) return $cand; }
    return null;
}
// One sentence for the verdict.
function pc_cand_verdict_text(array $k, $showPay = true) {
    $money = fn($v) => (function_exists('cur_sym') ? cur_sym() : '₹') . number_format((float)$v, 0);
    $pct = fn($v) => rtrim(rtrim(number_format((float)$v, 1), '0'), '.') . '%';
    if ($k['verdict'] === 'NONE') return 'No offer or salary structure yet' . ($showPay ? ' — pay up to ' . $money($k['max_ctc']) . ' a month keeps the ' . $pct($k['target']) . ' target margin.' : '.');
    $head = $k['verdict'] === 'OK' ? 'Within the costing' : ($k['verdict'] === 'LOW' ? 'Below the target margin' : 'Loss at the quoted rate');
    return $head . ': margin ' . $pct($k['margin']) . ' against the target ' . $pct($k['target'])
         . ($showPay ? ' (CTC ' . $money($k['ctc']) . ' a month; costed ' . $money($k['costed_pay']) . '; up to ' . $money($k['max_ctc']) . ' keeps the target).' : '.');
}

// ---- A role from the heads editor -------------------------------------------
// The role editor posts its heads as JSON (items_json) plus the role's own
// fields. Cleaned here for both the live preview and the save, so the figure
// the user watches while typing is the figure that is saved.
// Returns [line (a project_costing_lines-shaped row), errors[], evaluation].
function pc_line_from_post(array $p, array $h) {
    $raw = json_decode((string)($p['items_json'] ?? '[]'), true);
    if (!is_array($raw)) $raw = [];
    [$items, $errs] = ch_items_clean($raw);
    $dpm = (float)($p['dpm'] ?? 0); if ($dpm < 1 || $dpm > 31) $dpm = ch_days_per_month();
    $num = fn($k) => is_numeric($p[$k] ?? null) ? (float)$p[$k] : 0.0;
    $line = [
        'group_label' => mb_substr(trim((string)($p['group_label'] ?? '')), 0, 120),
        'role_label' => mb_substr(trim((string)($p['role_label'] ?? '')), 0, 160),
        'boq_qty' => max(0, $num('boq_qty')), 'target_margin' => $num('target_margin'),
        'rate_manual' => max(0, $num('rate_manual')), 'headcount' => max(1, (int)($p['headcount'] ?? 1)),
        'heads' => '{}', 'oneoff' => 0,
        'spec' => json_encode(['v' => 1, 'dpm' => $dpm, 'items' => $items], JSON_UNESCAPED_UNICODE),
    ];
    if ($line['role_label'] === '') $errs[] = 'Give the role a name.';
    if ($line['target_margin'] < 0 || $line['target_margin'] >= 100) $errs[] = 'The target margin must be from 0 up to (not including) 100%.';
    $ev = ch_evaluate($items, ch_unit_for_basis((string)$h['basis']), $dpm);
    foreach ($ev['errors'] as $m) $errs[] = $m;
    return [$line, array_values(array_unique($errs)), $ev];
}

// ---- Routing ---------------------------------------------------------------
// M16 — ONE door for the module, the same shape as lead_scope_gate() in leads.php.
// The routes name their costing as either `id` or `costing_id`; both are resolved
// here so no route can be reached with the other spelling. A line id is never the
// object being guarded — the costing it belongs to is.
function pc_scope_gate() {
    $id = (int)($_POST['costing_id'] ?? $_GET['costing_id'] ?? $_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) return;                       // a list, a new form: no costing named
    try { $office = ops_val("SELECT office_id FROM project_costings WHERE id=?", [$id]); }
    catch (Throwable $e) { return; }            // table not built yet — nothing to guard
    if ($office === false) return;              // no such costing — the route's own answer
    ops_require(scope_office_allows($office), 'This costing is outside your office / branch scope.');
}

function ops_projcosting($route, $method) {
    ops_require(pc_can(), 'You do not have access to project costing.');
    pc_scope_gate();   // M16 — object-level branch scope, before anything reads an id
    pc_migrate();
    $pdo = db();

    // ---- Create ----
    if ($route === 'project-costing-new' && $method === 'POST') {
        ops_require(pc_can_edit(), 'You cannot create a costing.');
        $d = pc_defaults();
        //  What the costing prices (owner, 20 Oct 2026: "tagged with that inquiry
        //  or lead"). An enquiry or lead fills in the customer, branch and
        //  business unit; a costing made from a deal, a quotation or a
        //  recruitment requisition is tagged by that instead. One is required —
        //  a costing tagged to nothing is a sheet nobody can find again.
        $inq = (int)($_POST['inquiry_id'] ?? 0); $lead = (int)($_POST['lead_id'] ?? 0);
        $src = pc_link_source($inq, $lead);
        if (!$inq && !$lead && !(int)($_POST['opportunity_id'] ?? 0) && !(int)($_POST['quote_id'] ?? 0) && !(int)($_POST['requisition_id'] ?? 0)) {
            flash('Say which enquiry or lead this costing is for — pick one under "For".', 'error'); redirect('/project-costings');
        }
        if (!empty($src['err'])) { flash($src['err'], 'error'); redirect('/project-costings'); }
        foreach (['client_id', 'office_id'] as $k) if (!(int)($_POST[$k] ?? 0) && !empty($src[$k])) $_POST[$k] = $src[$k];
        if (trim((string)($_POST['title'] ?? '')) === '' && !empty($src['title'])) $_POST['title'] = $src['title'];
        $code = ops_next_code('project_costings', 'code', 'PC');
        $pdo->prepare("INSERT INTO project_costings
            (code,title,client_id,office_id,site,basis,overhead_pct,insurance_pct,baddebt_pct,contingency_pct,negotiation_pct,
             status,quote_id,opportunity_id,requisition_id,created_by,created_by_user_id,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?, 'DRAFT', ?,?,?,?,?,?)")
            ->execute([$code, trim((string)($_POST['title'] ?? '')) ?: 'Untitled costing',
                (int)($_POST['client_id'] ?? 0) ?: null, (int)($_POST['office_id'] ?? 0) ?: null,
                trim((string)($_POST['site'] ?? '')), strtoupper((string)($_POST['basis'] ?? 'LONG')),
                $d['overhead_pct'], $d['insurance_pct'], $d['baddebt_pct'], $d['contingency_pct'], $d['negotiation_pct'],
                (int)($_POST['quote_id'] ?? 0) ?: null, (int)($_POST['opportunity_id'] ?? 0) ?: null,
                (int)($_POST['requisition_id'] ?? 0) ?: null, user_name(current_user()), (int)(current_user()['id'] ?? 0) ?: null, date('c')]);
        $id = (int)$pdo->lastInsertId();
        $sbu = trim((string)($_POST['sbu'] ?? '')) ?: (string)($src['sbu'] ?? '');
        $pdo->prepare("UPDATE project_costings SET inquiry_id=?, lead_id=?, sbu=? WHERE id=?")
            ->execute([$inq ?: null, ($lead ?: (int)($src['lead_id'] ?? 0)) ?: null, substr($sbu, 0, 20), $id]);
        $seeded = (int)($_POST['quote_id'] ?? 0) ? pc_seed_from_quote($id, (int)$_POST['quote_id']) : 0;
        flash($seeded ? "$code created from the quotation — its $seeded line" . ($seeded === 1 ? '' : 's') . " are in, at the quoted rate and quantity. Add each role's cost heads to see the margin at that price."
                      : "$code created — add the team roles and their cost heads.");
        redirect('/project-costing?id=' . $id);
    }

    // ---- Save header ----
    if ($route === 'project-costing-save' && $method === 'POST') {
        $id = (int)($_POST['id'] ?? 0); $h = pc_get($id);
        if (!$h) { flash('Costing not found.', 'error'); redirect('/project-costings'); }
        ops_require(pc_can_edit() && !pc_locked($h), 'This costing is locked.');
        $pdo->prepare("UPDATE project_costings SET title=?, client_id=?, office_id=?, site=?, basis=?, load_on=?,
            overhead_pct=?, insurance_pct=?, baddebt_pct=?, contingency_pct=?, negotiation_pct=?,
            inclusions=?, exclusions=?, notes=?, sbu=?, updated_at=? WHERE id=?")
            ->execute([trim((string)($_POST['title'] ?? $h['title'])), (int)($_POST['client_id'] ?? 0) ?: null,
                (int)($_POST['office_id'] ?? 0) ?: null, trim((string)($_POST['site'] ?? '')),
                strtoupper((string)($_POST['basis'] ?? $h['basis'])),
                strtoupper((string)($_POST['load_on'] ?? 'COST')) === 'RATE' ? 'RATE' : 'COST',
                (float)($_POST['overhead_pct'] ?? 0), (float)($_POST['insurance_pct'] ?? 0),
                (float)($_POST['baddebt_pct'] ?? 0), (float)($_POST['contingency_pct'] ?? 0),
                (float)($_POST['negotiation_pct'] ?? 0),
                trim((string)($_POST['inclusions'] ?? '')), trim((string)($_POST['exclusions'] ?? '')),
                trim((string)($_POST['notes'] ?? '')), substr(trim((string)($_POST['sbu'] ?? ($h['sbu'] ?? ''))), 0, 20), date('c'), $id]);
        pc_restamp($id);
        flash('Costing saved.');
        redirect('/project-costing?id=' . $id);
    }

    // ---- Costing heads: the library and the templates (Masters-level setup) ----
    // Owner, 9 Nov 2026 — "highly configurable as it changes with time". Who
    // changes them: the people who change the Masters lists (docs/02 row 12).
    // Template ids travel as tpl_id: pc_scope_gate() reads `id` as a costing.
    if ($route === 'project-costing-heads') {
        ch_migrate();
        $me = user_name(current_user());
        if ($method === 'POST') {
            ops_require(ch_can_manage(), 'Only an administrator can change the costing heads.');
            $do = (string)($_POST['do'] ?? '');
            $back = '/project-costing-heads';
            if ($do === 'head_save') {
                $raw = $_POST; $raw['of'] = (array)($_POST['of'] ?? []);
                $raw['calc'] = (string)($_POST['amount'] ?? '');
                [$ok, $res] = ch_library_save($raw, (string)($_POST['from'] ?? ''), $me);
                flash($ok ? 'Saved — new roles get this value from ' . fdate((string)$_POST['from']) . '. Costings already made keep the value they were priced with.' : $res, $ok ? 'success' : 'error');
                redirect($back . ($ok ? '#h-' . $res : '#edit'));
            }
            if ($do === 'head_active') {
                ch_library_set_active((string)($_POST['code'] ?? ''), !empty($_POST['on']), $me);
                flash(!empty($_POST['on']) ? 'Brought back.' : 'Retired — it is no longer offered for new roles; costings that use it keep it.');
                redirect($back);
            }
            if ($do === 'dpm') {
                $v = (float)($_POST['dpm'] ?? 0);
                if ($v < 1 || $v > 31) { flash('Working days in a month must be from 1 to 31.', 'error'); redirect($back); }
                setting_set('costing_days_per_month', (string)$v);
                if (function_exists('act_log')) try { act_log('COSTING_HEADS', 0, 'UPDATE', 'Working days in a month for costings set to ' . $v . ' by ' . $me, []); } catch (Throwable $e) {}
                flash('Saved — new roles use ' . $v . ' working days in a month (each role can still change it).');
                redirect($back);
            }
            if ($do === 'template_save' || $do === 'template_from_role') {
                $items = json_decode((string)($_POST['items_json'] ?? '[]'), true);
                [$ok, $res] = ch_template_save((int)($_POST['tpl_id'] ?? 0), (string)($_POST['name'] ?? ''), (string)($_POST['note'] ?? ''), is_array($items) ? $items : [], $me);
                flash($ok ? 'Template saved — pick it under "Start from a template" on any role.' : $res, $ok ? 'success' : 'error');
                $rb = (string)($_POST['back'] ?? '');
                redirect($do === 'template_from_role' && strpos($rb, '/project-costing?') === 0 ? $rb : $back . '#templates');
            }
            if ($do === 'template_active') {
                ch_template_set_active((int)($_POST['tpl_id'] ?? 0), !empty($_POST['on']), $me);
                flash(!empty($_POST['on']) ? 'Template brought back.' : 'Template removed.');
                redirect($back . '#templates');
            }
            redirect($back);
        }
        view('ops/costing_heads', ['lib' => ch_library('9999-12-31', true), 'today' => ch_library(null, true),
            'templates' => ch_templates(true), 'dpm' => ch_days_per_month(), 'canManage' => ch_can_manage(),
            'editCode' => strtoupper((string)($_GET['edit'] ?? ''))]);
        return true;
    }

    // ---- Live figures while a role is being edited (nothing is saved) ----
    if ($route === 'project-costing-line-preview' && $method === 'POST') {
        $cid = (int)($_POST['costing_id'] ?? 0); $h = pc_get($cid);
        header('Content-Type: application/json');
        if (!$h) { echo json_encode(['ok' => false, 'errors' => ['Costing not found.']]); return true; }
        [$line, $errs, $ev] = pc_line_from_post($_POST, $h);
        $c = pc_line_calc($line, $h);
        echo json_encode(['ok' => !$errs, 'errors' => $errs, 'rows' => $ev['rows'], 'by_grp' => $ev['by_grp'],
            'direct' => $c['direct'], 'pay' => $c['pay'], 'once' => $c['oneoff'], 'loaded' => $c['loaded'], 'rate' => $c['proposed'],
            'revenue' => $c['revenue'], 'cost' => $c['cost'], 'profit' => $c['profit'], 'margin' => $c['margin']], JSON_UNESCAPED_UNICODE);
        return true;
    }

    // ---- Add / edit a role line ----
    if ($route === 'project-costing-line-save' && $method === 'POST' && isset($_POST['items_json'])) {
        // The heads editor (owner, 9 Nov 2026).
        $cid = (int)($_POST['costing_id'] ?? 0); $h = pc_get($cid);
        if (!$h) { flash('Costing not found.', 'error'); redirect('/project-costings'); }
        ops_require(pc_can_edit() && !pc_locked($h), 'This costing is locked.');
        $lineId = (int)($_POST['line_id'] ?? 0);
        if ($lineId && !ops_one("SELECT id FROM project_costing_lines WHERE id=? AND costing_id=?", [$lineId, $cid])) { flash('Role not found.', 'error'); redirect('/project-costing?id=' . $cid); }
        [$line, $errs] = pc_line_from_post($_POST, $h);
        if ($errs) {
            // Keep what was typed, so nothing has to be entered twice.
            $_SESSION['pc_line_draft'][$cid] = ['line_id' => $lineId, 'post' => array_intersect_key($_POST,
                array_flip(['group_label', 'role_label', 'headcount', 'boq_qty', 'target_margin', 'rate_manual', 'dpm', 'items_json']))];
            flash('Not saved yet — ' . implode(' ', array_slice($errs, 0, 4)), 'error');
            redirect('/project-costing?id=' . $cid . ($lineId ? '&edit=' . $lineId : '') . '#add-role');
        }
        $c = pc_line_calc($line, $h);
        $args = [$line['group_label'], $line['role_label'], json_encode($c['heads'], JSON_UNESCAPED_UNICODE), $line['boq_qty'],
                 $line['target_margin'], $line['rate_manual'], $c['oneoff'], $line['headcount'], $line['spec']];
        if ($lineId) {
            $pdo->prepare("UPDATE project_costing_lines SET group_label=?, role_label=?, heads=?, boq_qty=?, target_margin=?, rate_manual=?, oneoff=?, headcount=?, spec=? WHERE id=? AND costing_id=?")
                ->execute(array_merge($args, [$lineId, $cid]));
        } else {
            $seq = (int)ops_val("SELECT COALESCE(MAX(seq),0)+1 FROM project_costing_lines WHERE costing_id=?", [$cid]);
            $pdo->prepare("INSERT INTO project_costing_lines (costing_id,seq,group_label,role_label,heads,boq_qty,target_margin,rate_manual,oneoff,headcount,spec,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute(array_merge([$cid, $seq], $args, [date('c')]));
        }
        unset($_SESSION['pc_line_draft'][$cid]);
        pc_restamp($cid);
        flash($lineId ? 'Role updated.' : 'Role added.');
        redirect('/project-costing?id=' . $cid);
    }
    if ($route === 'project-costing-line-save' && $method === 'POST') {
        $cid = (int)($_POST['costing_id'] ?? 0); $h = pc_get($cid);
        if (!$h) { flash('Costing not found.', 'error'); redirect('/project-costings'); }
        ops_require(pc_can_edit() && !pc_locked($h), 'This costing is locked.');
        // Collect the per-head amounts (only heads that exist in the catalogue).
        $heads = [];
        foreach (array_keys(pc_heads()) as $hk) {
            $val = (float)($_POST['head'][$hk] ?? 0);
            if ($val != 0) $heads[$hk] = $val;
        }
        $lineId = (int)($_POST['line_id'] ?? 0);
        $args = [trim((string)($_POST['group_label'] ?? '')), trim((string)($_POST['role_label'] ?? 'Role')),
                 json_encode($heads, JSON_UNESCAPED_UNICODE), (float)($_POST['boq_qty'] ?? 0),
                 (float)($_POST['target_margin'] ?? 0), (float)($_POST['rate_manual'] ?? 0), (float)($_POST['oneoff'] ?? 0)];
        if ($lineId) {
            $pdo->prepare("UPDATE project_costing_lines SET group_label=?, role_label=?, heads=?, boq_qty=?, target_margin=?, rate_manual=?, oneoff=? WHERE id=? AND costing_id=?")
                ->execute(array_merge($args, [$lineId, $cid]));
        } else {
            $seq = (int)ops_val("SELECT COALESCE(MAX(seq),0)+1 FROM project_costing_lines WHERE costing_id=?", [$cid]);
            $pdo->prepare("INSERT INTO project_costing_lines (costing_id,seq,group_label,role_label,heads,boq_qty,target_margin,rate_manual,oneoff,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute(array_merge([$cid, $seq], $args, [date('c')]));
        }
        $lid = $lineId ?: (int)ops_val("SELECT MAX(id) FROM project_costing_lines WHERE costing_id=?", [$cid]);
        $pdo->prepare("UPDATE project_costing_lines SET headcount=? WHERE id=? AND costing_id=?")->execute([max(1, (int)($_POST['headcount'] ?? 1)), $lid, $cid]);
        pc_restamp($cid);
        flash('Role saved.');
        redirect('/project-costing?id=' . $cid);
    }

    // ---- Delete a line ----
    if ($route === 'project-costing-line-delete' && $method === 'POST') {
        $cid = (int)($_POST['costing_id'] ?? 0); $h = pc_get($cid);
        if ($h && pc_can_edit() && !pc_locked($h)) {
            $pdo->prepare("DELETE FROM project_costing_lines WHERE id=? AND costing_id=?")->execute([(int)($_POST['line_id'] ?? 0), $cid]);
            pc_restamp($cid);
            flash('Role removed.');
        }
        redirect('/project-costing?id=' . $cid);
    }

    // ---- Status transitions ----
    if (in_array($route, ['project-costing-submit', 'project-costing-approve', 'project-costing-reject', 'project-costing-reopen'], true) && $method === 'POST') {
        $id = (int)($_POST['id'] ?? 0); $h = pc_get($id);
        if (!$h) { flash('Costing not found.', 'error'); redirect('/project-costings'); }
        if (($why = pc_transition_problem($h, $route)) !== '') { flash($why, 'error'); redirect('/project-costing?id=' . $id); }
        if ($route === 'project-costing-submit') {
            ops_require(pc_can_edit(), 'You cannot submit this.');
            pc_restamp($id);
            $pdo->prepare("UPDATE project_costings SET status='SUBMITTED', updated_at=? WHERE id=?")->execute([date('c'), $id]);
            $n = pc_build_approvals(pc_get($id));
            // Tell the people the first level waits for.
            $h2 = pc_get($id); $open = pc_open_levels($id);
            foreach (pc_steps($id) as $s) {
                if (!in_array((int)$s['level'], $open, true)) continue;
                foreach (pc_step_people($s, $h2) as $ap) if (trim((string)($ap['email'] ?? '')) !== '' && function_exists('ops_mail'))
                    ops_mail($ap['email'], 'Approval needed: costing ' . $h2['code'], "Hello,\n\nProject costing {$h2['code']} — {$h2['title']} is waiting for your approval (level {$s['level']}).\nOpen it in " . (function_exists('app_name') ? app_name() : 'the application') . " to approve it or send it back with a reason.\n", '', 'COSTING_APPROVAL');
            }
            $together = function_exists('crm_approval_mode') && crm_approval_mode() === 'together';
            flash('Costing submitted for approval — ' . $n . ' level' . ($n === 1 ? '' : 's') . ' to pass' . ($n > 1 ? ($together ? ', all at the same time.' : ', in order.') : '.'));
        } elseif ($route === 'project-costing-approve') {
            $r = pc_sign_level($h, (string)($_POST['approval_ref'] ?? ''));
            if ($r === 'APPROVED') flash('Costing approved — a quotation can now be made from it.');
            elseif (function_exists('crm_approval_mode') && crm_approval_mode() === 'together') {
                flash('Your level is signed — still waiting for level ' . implode(', ', pc_open_levels($id)) . '.');
            } else {
                $h2 = pc_get($id); $lvl = pc_current_level($id);
                foreach (pc_steps($id) as $s) if ((int)$s['level'] === $lvl)
                    foreach (pc_step_people($s, $h2) as $ap) if (trim((string)($ap['email'] ?? '')) !== '' && function_exists('ops_mail'))
                        ops_mail($ap['email'], 'Approval needed: costing ' . $h2['code'], "Hello,\n\nProject costing {$h2['code']} — {$h2['title']} has passed the level below and is waiting for your approval (level $lvl).\n", '', 'COSTING_APPROVAL');
                flash('Your level is signed — it now waits for level ' . $lvl . '.');
            }
        } elseif ($route === 'project-costing-reject') {
            $lvl = pc_my_level($h) ?: pc_current_level($id);
            if ($lvl) $pdo->prepare("UPDATE project_costing_approvals SET status='REJECTED', acted_by=?, acted_at=?, remarks=? WHERE costing_id=? AND level=? AND status='PENDING'")
                ->execute([user_name(current_user()), date('c'), substr(trim((string)($_POST['reject_note'] ?? '')), 0, 400), $id, $lvl]);
            $pdo->prepare("UPDATE project_costings SET status='REJECTED', reject_note=?, updated_at=? WHERE id=?")
                ->execute([trim((string)($_POST['reject_note'] ?? '')), date('c'), $id]);
            flash('Sent back with comments.', 'warning');
        } else { // reopen to draft — an approval is for the figures approved, so it goes
            ops_require(pc_can_edit(), 'You cannot reopen this.');
            $pdo->prepare("DELETE FROM project_costing_approvals WHERE costing_id=?")->execute([$id]);
            $pdo->prepare("UPDATE project_costings SET status='DRAFT', approved_by='', approved_at='', approval_ref='', updated_at=? WHERE id=?")->execute([date('c'), $id]);
            if ($h['status'] === 'APPROVED' && !empty($h['quote_id']) && function_exists('crm_log_change'))
                crm_log_change((int)$h['quote_id'], 'Costing ' . $h['code'] . ' was reopened by ' . user_name(current_user()) . ' — its approval was withdrawn.');
            flash($h['status'] === 'APPROVED' ? 'Reopened as draft — its approval was withdrawn; submit it again when it is ready.' : 'Reopened as draft.');
        }
        redirect('/project-costing?id=' . $id);
    }

    // ---- Attach / detach a costing to an existing quotation or opportunity ----
    if ($route === 'project-costing-attach' && $method === 'POST') {
        $id = (int)($_POST['costing_id'] ?? 0); $h = pc_get($id);
        if (!$h) { flash('Costing not found.', 'error'); redirect('/project-costings'); }
        ops_require(pc_can_edit() || (function_exists('can') && (can('mod.quotes.edit') || can('mod.hiring.edit'))), 'You cannot link this costing.');
        $qid = isset($_POST['quote_id']) ? (int)$_POST['quote_id'] : null;
        $oid = isset($_POST['opportunity_id']) ? (int)$_POST['opportunity_id'] : null;
        $was = (int)($h['quote_id'] ?? 0); $wasOpp = (int)($h['opportunity_id'] ?? 0);
        if ($qid !== null) $pdo->prepare("UPDATE project_costings SET quote_id=?, updated_at=? WHERE id=?")->execute([$qid ?: null, date('c'), $id]);
        if ($oid !== null) $pdo->prepare("UPDATE project_costings SET opportunity_id=?, updated_at=? WHERE id=?")->execute([$oid ?: null, date('c'), $id]);
        // Every link and unlink is written down — on the costing, and in the
        // quotation's own history — so a costing unlinked by mistake can be traced
        // and attached again (MOD-REP, owner, 9 Nov 2026).
        $me = user_name(current_user());
        $what = $qid !== null ? ($qid ? 'linked to quotation #' . $qid : 'unlinked from quotation #' . $was) : ($oid ? 'linked to deal #' . $oid : 'unlinked from deal #' . $wasOpp);
        if (function_exists('act_log')) try { act_log('PROJECT_COSTING', $id, 'UPDATE', 'Costing ' . $h['code'] . ' ' . $what . ' by ' . $me, []); } catch (Throwable $e) {}
        if ($qid !== null && function_exists('crm_log_change')) {
            if ($was && !$qid) try { crm_log_change($was, 'Costing ' . $h['code'] . ' was unlinked by ' . $me . '. It is kept in Project costing (filter "Not on a quotation") and can be attached again from this quotation.'); } catch (Throwable $e) {}
            if ($qid) try { crm_log_change($qid, 'Costing ' . $h['code'] . ' was linked by ' . $me . '.'); } catch (Throwable $e) {}
        }
        flash(($qid || $oid) ? 'Costing ' . $h['code'] . ' linked.'
            : 'Costing ' . $h['code'] . ' unlinked — it is not deleted. Find it under Sales → Project costing ("Not on a quotation"), or attach it again with "attach existing…".');
        // Return to wherever the request came from.
        $back = (string)($_POST['back'] ?? '');
        redirect($back !== '' ? $back : '/project-costing?id=' . $id);
    }

    // ---- Generate a quotation from the costing ----
    if ($route === 'project-costing-to-quote' && $method === 'POST') {
        $id = (int)($_POST['id'] ?? 0); $roll = pc_rollup($id);
        if (!$roll) { flash('Costing not found.', 'error'); redirect('/project-costings'); }
        ops_require(function_exists('can') && can('mod.quotes.edit'), 'You cannot create a quotation.');
        // D-11 (the owner, 20 Oct 2026) — a quotation is made only from an approved
        // costing: approval is what makes its prices the company's prices.
        if ((string)$roll['header']['status'] !== 'APPROVED') {
            flash('Make the quotation once this costing is approved — submit it for approval first.', 'error');
            redirect('/project-costing?id=' . $id);
        }
        $r = pc_make_quote($roll);
        if (!empty($r['id'])) { flash('Quotation ' . $r['no'] . ' drafted from this costing — review and send.'); redirect('/quote?id=' . $r['id']); }
        flash($r['err'] ?? 'Could not create the quotation.', 'error'); redirect('/project-costing?id=' . $id);
    }

    // ---- Raise a hiring request from a won costing (one role, or all) ----
    if ($route === 'project-costing-to-hreq' && $method === 'POST') {
        $cid = (int)($_POST['costing_id'] ?? 0);
        $h = pc_get($cid); if (!$h) { flash('Costing not found.', 'error'); redirect('/project-costings'); }
        ops_require(function_exists('hreq_can_create') ? hreq_can_create() : (function_exists('can') && can('mod.hiring.edit')), 'You cannot raise a hiring request.');
        $ids = !empty($_POST['all']) ? array_map(fn($r) => (int)$r['id'], pc_lines($cid)) : [(int)($_POST['line_id'] ?? 0)];
        $made = []; $msgs = [];
        foreach ($ids as $lid) {
            [$ok, $msg, $hid] = pc_raise_hiring_request($cid, $lid);
            if ($ok) $made[] = $hid; elseif (count($ids) === 1 || strpos($msg, 'already asked for') === false) $msgs[] = $msg;
        }
        if (count($made) === 1 && count($ids) === 1) { flash($msgs ? implode(' ', $msgs) : 'Hiring request created from this costing — check it and press Submit to send it for approval.'); redirect('/hiring-request?id=' . $made[0]); }
        if ($made) { flash(count($made) . ' hiring request(s) created — open each from this page, check it and press Submit to send it for approval.' . ($msgs ? ' ' . implode(' ', array_unique($msgs)) : '')); redirect('/project-costing?id=' . $cid . '#use'); }
        flash($msgs ? implode(' ', array_unique($msgs)) : 'Every role is already asked for.', 'error');
        redirect('/project-costing?id=' . $cid . '#use');
    }

    // ---- Spawn a recruitment requirement from one role line ----
    if ($route === 'project-costing-to-req' && $method === 'POST') {
        $cid = (int)($_POST['costing_id'] ?? 0); $lineId = (int)($_POST['line_id'] ?? 0);
        $h = pc_get($cid); if (!$h) { flash('Costing not found.', 'error'); redirect('/project-costings'); }
        // M4 correction §1 — name the object. A costing spawns a RECRUITMENT
        // REQUISITION (the direct path); it does not post a marketplace requirement.
        $pcLbl = function_exists('hreq_label') ? hreq_label('requisition') : 'Recruitment Requisition';
        ops_require(function_exists('can') && can('mod.hiring.edit'), 'You cannot create a ' . mb_strtolower($pcLbl) . '.');
        $r = pc_make_requisition($cid, $lineId);
        if (!empty($r['id'])) { flash($pcLbl . ' ' . $r['code'] . ' created from this role, for the people not yet asked for — check the duration.'); redirect('/requisition?id=' . $r['id']); }
        flash($r['err'] ?? ('Could not create the ' . mb_strtolower($pcLbl) . '.'), 'error'); redirect('/project-costing?id=' . $cid);
    }

    // ---- Printable sheet (PDF via the browser's Save-as-PDF) ----
    if ($route === 'project-costing-print') {
        $roll = pc_rollup((int)($_GET['id'] ?? 0));
        if (!$roll) { http_response_code(404); view('notfound'); return true; }
        $client = null;
        if (!empty($roll['header']['client_id'])) $client = ops_one("SELECT * FROM business_partners WHERE id=?", [(int)$roll['header']['client_id']]);
        $roll['client'] = $client; $roll['heads'] = $roll['labels'];
        require __DIR__ . '/../views/ops/project_costing_print.php';
        return true;
    }

    // ---- Views ----
    if ($route === 'project-costing') {
        $id = (int)($_GET['id'] ?? 0); $roll = pc_rollup($id);
        if (!$roll) { http_response_code(404); view('notfound'); return true; }
        pc_link_migrate();
        $chOn = function_exists('ch_library');
        view('ops/project_costing', ['roll' => $roll, 'heads' => pc_heads(),
            'chLib' => $chOn ? ch_library() : [], 'chTpls' => $chOn ? ch_templates() : [], 'chManage' => $chOn && ch_can_manage(),
            'clients' => function_exists('clients_list') ? clients_list() : [],
            'offices' => function_exists('offices_list') ? offices_list() : []]);
        return true;
    }

    // list
    // The enquiries and leads a costing can be tagged with — open ones, in scope.
    [$ow, $oa] = function_exists('scope_office_clause') ? scope_office_clause('i.office_id') : ['1=1', []];
    $inqs = ops_all("SELECT i.id, i.inquiry_no, i.client_name, i.subject FROM crm_inquiries i
                     WHERE COALESCE(i.status,'OPEN') <> 'DROPPED' AND $ow ORDER BY i.id DESC LIMIT 300", $oa) ?: [];
    $leads = function_exists('leads_all') ? (leads_try(fn() => leads_all(['status' => 'OPEN'], ' LIMIT 300'), []) ?: []) : [];
    view('ops/project_costings', ['rows' => pc_all(200, ['q' => (string)($_GET['q'] ?? ''), 'link' => (string)($_GET['link'] ?? '')]),
        'filter' => ['q' => (string)($_GET['q'] ?? ''), 'link' => (string)($_GET['link'] ?? '')],
        'clients' => function_exists('clients_list') ? clients_list() : [],
        'offices' => function_exists('offices_list') ? offices_list() : [],
        'inquiries' => $inqs, 'leads' => $leads,
        'sbuOpts' => function_exists('lk_options_or') ? lk_options_or('sbu', OPS_SBUS) : [],
        'pre' => ['inquiry_id' => (int)($_GET['inquiry_id'] ?? 0), 'lead_id' => (int)($_GET['lead_id'] ?? 0)]]);
    return true;
}
