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
    static $done = false; if ($done) return; $done = true;
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
              ['project_costing_lines','oneoff',"DECIMAL(14,2) DEFAULT 0"]] as $c) {
        if (function_exists('ensure_column')) ensure_column($c[0], $c[1], $c[2]);
    }
}

// Who may open / edit costings: anyone who works either side of it (sells or
// hires) plus management. Cross-module by design, like the advisor.
function pc_can() {
    return function_exists('can') && (
        can('mod.quotes.view') || can('mod.hiring.view') || can('mod.inquiries.view')
        || (function_exists('is_admin_level') && is_admin_level()) || (function_exists('is_master') && is_master()));
}
function pc_can_edit() {
    return function_exists('can') && (
        can('mod.quotes.edit') || can('mod.hiring.edit')
        || (function_exists('is_admin_level') && is_admin_level()) || (function_exists('is_master') && is_master()));
}
function pc_can_approve() {
    return (function_exists('is_admin_level') && is_admin_level()) || (function_exists('is_master') && is_master())
        || (function_exists('can') && can('crm.quote.approve'));
}

// ---- Reads -----------------------------------------------------------------
function pc_get($id) { pc_migrate(); return ops_one("SELECT * FROM project_costings WHERE id=?", [(int)$id]) ?: null; }
function pc_lines($id) {
    pc_migrate();
    return ops_all("SELECT * FROM project_costing_lines WHERE costing_id=? ORDER BY seq, id", [(int)$id]) ?: [];
}
function pc_all($limit = 200) {
    pc_migrate();
    return ops_all("SELECT c.*, COALESCE(p.display_name,p.legal_name) client_name
                    FROM project_costings c LEFT JOIN business_partners p ON p.id=c.client_id
                    ORDER BY c.id DESC LIMIT " . (int)$limit) ?: [];
}
// The costing linked to a given quotation / opportunity (or null).
function pc_for_quote($qid) { pc_migrate(); return $qid ? (ops_one("SELECT * FROM project_costings WHERE quote_id=? ORDER BY id DESC LIMIT 1", [(int)$qid]) ?: null) : null; }
function pc_for_opportunity($oid) { pc_migrate(); return $oid ? (ops_one("SELECT * FROM project_costings WHERE opportunity_id=? ORDER BY id DESC LIMIT 1", [(int)$oid]) ?: null) : null; }
// Costings not yet linked to any quote/opportunity — the "attach existing" list.
function pc_unlinked($limit = 100) {
    pc_migrate();
    return ops_all("SELECT id, code, title, exp_revenue FROM project_costings
                    WHERE COALESCE(quote_id,0)=0 AND COALESCE(opportunity_id,0)=0
                    ORDER BY id DESC LIMIT " . (int)$limit) ?: [];
}

// ---- The maths -------------------------------------------------------------
// One line's numbers, given the header (for the loading %s and basis).
function pc_line_calc($line, $header) {
    $heads = json_decode((string)($line['heads'] ?? ''), true);
    if (!is_array($heads)) $heads = [];
    $direct = 0.0; foreach ($heads as $v) $direct += (float)$v;

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

    $basis = (string)($header['basis'] ?? 'LONG');
    $boq = max(0, (float)($line['boq_qty'] ?? 0));
    $oneoff = (float)($line['oneoff'] ?? 0);
    if ($basis === 'FIXED') { $qty = 1; $revenue = $proposed; $cost = $loaded + $oneoff; }
    else                    { $qty = $boq; $revenue = $proposed * $qty; $cost = $loaded * $qty + $oneoff; }
    $profit = $revenue - $cost;
    $margin = $revenue > 0 ? $profit / $revenue * 100 : 0;

    return ['direct' => round($direct, 2), 'loaded' => round($loaded, 2),
            'rate' => round($rate, 2), 'proposed' => round($proposed, 2),
            'qty' => $qty, 'oneoff' => round($oneoff, 2),
            'revenue' => round($revenue, 2), 'cost' => round($cost, 2),
            'profit' => round($profit, 2), 'margin' => round($margin, 2), 'heads' => $heads];
}

// Project rollup: totals + per-head totals across every line.
function pc_rollup($id) {
    $h = pc_get($id); if (!$h) return null;
    $lines = pc_lines($id);
    $tot = ['revenue' => 0, 'cost' => 0, 'profit' => 0, 'direct' => 0, 'oneoff' => 0, 'lines' => 0];
    $byHead = [];
    foreach ($lines as $ln) {
        $c = pc_line_calc($ln, $h);
        $tot['revenue'] += $c['revenue']; $tot['cost'] += $c['cost']; $tot['profit'] += $c['profit'];
        $tot['direct'] += $c['direct']; $tot['oneoff'] += $c['oneoff']; $tot['lines']++;
        foreach ($c['heads'] as $k => $v) $byHead[$k] = ($byHead[$k] ?? 0) + (float)$v;
    }
    $tot['margin'] = $tot['revenue'] > 0 ? round($tot['profit'] / $tot['revenue'] * 100, 2) : 0;
    return ['header' => $h, 'lines' => $lines, 'totals' => $tot, 'by_head' => $byHead];
}

// Recompute and store the header's headline figures (for lists / links).
function pc_restamp($id) {
    $r = pc_rollup($id); if (!$r) return;
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
                        currency,validity_days,subtotal,gst_pct,gst_amount,total_amount,status,created_by,created_at)
                       VALUES (?,0,1,?,?,?,?,?, 'INR',30,?,?,?,?, 'DRAFT',?,?)")
            ->execute([$no, (int)$h['client_id'] ?: null,
                (string)ops_val("SELECT COALESCE(display_name,legal_name) FROM business_partners WHERE id=?", [(int)$h['client_id']]),
                (int)$h['office_id'] ?: null, (string)$h['title'], (string)$h['site'],
                $sub, $gstPct, $gst, $total, user_name(current_user()), date('c')]);
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
        return ['id' => $qid, 'no' => $no];
    } catch (Throwable $e) { return ['err' => $e->getMessage()]; }
}

// Create a recruitment requirement from one role line. Carries the per-person
// economics (loaded cost, proposed rate, target margin); headcount/duration are
// left for the recruiter to set. Returns ['id','code'].
function pc_make_requisition($cid, $lineId) {
    $h = pc_get($cid); if (!$h) return ['err' => 'Costing not found.'];
    $ln = ops_one("SELECT * FROM project_costing_lines WHERE id=? AND costing_id=?", [(int)$lineId, (int)$cid]);
    if (!$ln) return ['err' => 'Role not found.'];
    if (!function_exists('req_migrate')) return ['err' => 'Recruitment module is not available.'];
    req_migrate();
    $c = pc_line_calc($ln, $h);
    $heads = $c['heads'];
    $wage = (float)($heads['PERSONNEL'] ?? 0);
    $reimb = max(0, $c['direct'] - $wage);            // everything else monthly
    try {
        $code = ops_next_code('requisitions', 'req_code', 'REQ');
        $b = ['quantity' => 1, 'billing_rate' => $c['proposed'], 'budgeted_cost' => $c['loaded'],
              'rate_basis' => pc_req_basis((string)$h['basis'])];
        $com = function_exists('req_commercials') ? req_commercials($b) : ['revenue' => 0, 'profit' => 0, 'months' => 0];
        db()->prepare("INSERT INTO requisitions
            (req_code, office_id, client_id, designation, project_site, req_type, status, quantity,
             billing_rate, rate_basis, target_margin, budgeted_cost,
             sourcing_model, cost_wage, cost_reimburse, cost_oneoff,
             expected_revenue, expected_profit, notes, created_by, created_at)
            VALUES (?,?,?,?,?, 'NEW', 'OPEN', 1, ?,?,?,?, 'OWN_PAYROLL', ?,?,?, ?,?,?,?,?)")
            ->execute([$code, (int)$h['office_id'] ?: null, (int)$h['client_id'] ?: null,
                (string)$ln['role_label'], (string)$h['site'],
                $c['proposed'], pc_req_basis((string)$h['basis']), (float)$ln['target_margin'], $c['loaded'],
                $wage, $reimb, (float)$ln['oneoff'],
                $com['revenue'], $com['profit'],
                'From costing ' . $h['code'] . ' · ' . trim(((string)$ln['group_label'] !== '' ? $ln['group_label'] . ' / ' : '') . $ln['role_label']),
                user_name(current_user()), date('c')]);
        return ['id' => (int)db()->lastInsertId(), 'code' => $code];
    } catch (Throwable $e) { return ['err' => $e->getMessage()]; }
}

// ---- Routing ---------------------------------------------------------------
function ops_projcosting($route, $method) {
    ops_require(pc_can(), 'You do not have access to project costing.');
    pc_migrate();
    $pdo = db();

    // ---- Create ----
    if ($route === 'project-costing-new' && $method === 'POST') {
        ops_require(pc_can_edit(), 'You cannot create a costing.');
        $d = pc_defaults();
        $code = ops_next_code('project_costings', 'code', 'PC');
        $pdo->prepare("INSERT INTO project_costings
            (code,title,client_id,office_id,site,basis,overhead_pct,insurance_pct,baddebt_pct,contingency_pct,negotiation_pct,
             status,quote_id,opportunity_id,requisition_id,created_by,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?, 'DRAFT', ?,?,?,?,?)")
            ->execute([$code, trim((string)($_POST['title'] ?? 'Untitled costing')),
                (int)($_POST['client_id'] ?? 0) ?: null, (int)($_POST['office_id'] ?? 0) ?: null,
                trim((string)($_POST['site'] ?? '')), strtoupper((string)($_POST['basis'] ?? 'LONG')),
                $d['overhead_pct'], $d['insurance_pct'], $d['baddebt_pct'], $d['contingency_pct'], $d['negotiation_pct'],
                (int)($_POST['quote_id'] ?? 0) ?: null, (int)($_POST['opportunity_id'] ?? 0) ?: null,
                (int)($_POST['requisition_id'] ?? 0) ?: null, user_name(current_user()), date('c')]);
        $id = (int)$pdo->lastInsertId();
        flash("$code created — add the team roles and their cost heads.");
        redirect('/project-costing?id=' . $id);
    }

    // ---- Save header ----
    if ($route === 'project-costing-save' && $method === 'POST') {
        $id = (int)($_POST['id'] ?? 0); $h = pc_get($id);
        if (!$h) { flash('Costing not found.', 'error'); redirect('/project-costings'); }
        ops_require(pc_can_edit() && !pc_locked($h), 'This costing is locked.');
        $pdo->prepare("UPDATE project_costings SET title=?, client_id=?, office_id=?, site=?, basis=?, load_on=?,
            overhead_pct=?, insurance_pct=?, baddebt_pct=?, contingency_pct=?, negotiation_pct=?,
            inclusions=?, exclusions=?, notes=?, updated_at=? WHERE id=?")
            ->execute([trim((string)($_POST['title'] ?? $h['title'])), (int)($_POST['client_id'] ?? 0) ?: null,
                (int)($_POST['office_id'] ?? 0) ?: null, trim((string)($_POST['site'] ?? '')),
                strtoupper((string)($_POST['basis'] ?? $h['basis'])),
                strtoupper((string)($_POST['load_on'] ?? 'COST')) === 'RATE' ? 'RATE' : 'COST',
                (float)($_POST['overhead_pct'] ?? 0), (float)($_POST['insurance_pct'] ?? 0),
                (float)($_POST['baddebt_pct'] ?? 0), (float)($_POST['contingency_pct'] ?? 0),
                (float)($_POST['negotiation_pct'] ?? 0),
                trim((string)($_POST['inclusions'] ?? '')), trim((string)($_POST['exclusions'] ?? '')),
                trim((string)($_POST['notes'] ?? '')), date('c'), $id]);
        pc_restamp($id);
        flash('Costing saved.');
        redirect('/project-costing?id=' . $id);
    }

    // ---- Add / edit a role line ----
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
        if ($route === 'project-costing-submit') {
            ops_require(pc_can_edit(), 'You cannot submit this.');
            $pdo->prepare("UPDATE project_costings SET status='SUBMITTED', updated_at=? WHERE id=?")->execute([date('c'), $id]);
            flash('Costing submitted for approval.');
        } elseif ($route === 'project-costing-approve') {
            ops_require(pc_can_approve(), 'Only an approver can approve a costing.');
            $pdo->prepare("UPDATE project_costings SET status='APPROVED', approved_by=?, approved_at=?, approval_ref=?, updated_at=? WHERE id=?")
                ->execute([user_name(current_user()), date('c'), trim((string)($_POST['approval_ref'] ?? '')), date('c'), $id]);
            flash('Costing approved.');
        } elseif ($route === 'project-costing-reject') {
            ops_require(pc_can_approve(), 'Only an approver can send a costing back.');
            $pdo->prepare("UPDATE project_costings SET status='REJECTED', reject_note=?, updated_at=? WHERE id=?")
                ->execute([trim((string)($_POST['reject_note'] ?? '')), date('c'), $id]);
            flash('Sent back with comments.', 'warning');
        } else { // reopen to draft
            ops_require(pc_can_edit(), 'You cannot reopen this.');
            $pdo->prepare("UPDATE project_costings SET status='DRAFT', updated_at=? WHERE id=?")->execute([date('c'), $id]);
            flash('Reopened as draft.');
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
        if ($qid !== null) $pdo->prepare("UPDATE project_costings SET quote_id=?, updated_at=? WHERE id=?")->execute([$qid ?: null, date('c'), $id]);
        if ($oid !== null) $pdo->prepare("UPDATE project_costings SET opportunity_id=?, updated_at=? WHERE id=?")->execute([$oid ?: null, date('c'), $id]);
        flash(($qid || $oid) ? 'Costing linked.' : 'Costing unlinked.');
        // Return to wherever the request came from.
        $back = (string)($_POST['back'] ?? '');
        redirect($back !== '' ? $back : '/project-costing?id=' . $id);
    }

    // ---- Generate a quotation from the costing ----
    if ($route === 'project-costing-to-quote' && $method === 'POST') {
        $id = (int)($_POST['id'] ?? 0); $roll = pc_rollup($id);
        if (!$roll) { flash('Costing not found.', 'error'); redirect('/project-costings'); }
        ops_require(function_exists('can') && can('mod.quotes.edit'), 'You cannot create a quotation.');
        $r = pc_make_quote($roll);
        if (!empty($r['id'])) { flash('Quotation ' . $r['no'] . ' drafted from this costing — review and send.'); redirect('/quote?id=' . $r['id']); }
        flash($r['err'] ?? 'Could not create the quotation.', 'error'); redirect('/project-costing?id=' . $id);
    }

    // ---- Spawn a recruitment requirement from one role line ----
    if ($route === 'project-costing-to-req' && $method === 'POST') {
        $cid = (int)($_POST['costing_id'] ?? 0); $lineId = (int)($_POST['line_id'] ?? 0);
        $h = pc_get($cid); if (!$h) { flash('Costing not found.', 'error'); redirect('/project-costings'); }
        ops_require(function_exists('can') && can('mod.hiring.edit'), 'You cannot create a requirement.');
        $r = pc_make_requisition($cid, $lineId);
        if (!empty($r['id'])) { flash('Requirement ' . $r['code'] . ' created from this role — set the headcount & duration.'); redirect('/requisition?id=' . $r['id']); }
        flash($r['err'] ?? 'Could not create the requirement.', 'error'); redirect('/project-costing?id=' . $cid);
    }

    // ---- Printable sheet (PDF via the browser's Save-as-PDF) ----
    if ($route === 'project-costing-print') {
        $roll = pc_rollup((int)($_GET['id'] ?? 0));
        if (!$roll) { http_response_code(404); view('notfound'); return true; }
        $client = null;
        if (!empty($roll['header']['client_id'])) $client = ops_one("SELECT * FROM business_partners WHERE id=?", [(int)$roll['header']['client_id']]);
        $roll['client'] = $client; $roll['heads'] = pc_heads();
        require __DIR__ . '/../views/ops/project_costing_print.php';
        return true;
    }

    // ---- Views ----
    if ($route === 'project-costing') {
        $id = (int)($_GET['id'] ?? 0); $roll = pc_rollup($id);
        if (!$roll) { http_response_code(404); view('notfound'); return true; }
        view('ops/project_costing', ['roll' => $roll, 'heads' => pc_heads(),
            'clients' => function_exists('clients_list') ? clients_list() : [],
            'offices' => function_exists('offices_list') ? offices_list() : []]);
        return true;
    }

    // list
    view('ops/project_costings', ['rows' => pc_all(),
        'clients' => function_exists('clients_list') ? clients_list() : [],
        'offices' => function_exists('offices_list') ? offices_list() : []]);
    return true;
}
