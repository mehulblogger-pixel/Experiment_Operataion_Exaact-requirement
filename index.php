<?php
session_start();

// --- Readable error page instead of a blank 500 (so problems are diagnosable) ---
function ops_fatal($title, $hint, $detail = '') {
    if (!headers_sent()) http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:660px;margin:50px auto;padding:26px;border:1px solid #e2ddd6;border-radius:12px">';
    echo '<h2 style="color:#b8480f;margin:0 0 10px">' . htmlspecialchars($title) . '</h2>';
    echo '<p style="color:#4d4d4d;font-size:15px">' . $hint . '</p>';
    if ($detail !== '') echo '<pre style="background:#f7f6f4;border:1px solid #e2ddd6;padding:12px;border-radius:8px;overflow:auto;font-size:13px;white-space:pre-wrap">' . htmlspecialchars($detail) . '</pre>';
    echo '</div>';
    exit;
}
// Catch fatals that try/catch can't (missing require, parse errors) via shutdown.
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR], true)) {
        $msg = $e['message'];
        if (stripos($msg, 'ops.php') !== false || stripos($msg, 'failed to open') !== false || stripos($msg, 'No such file') !== false) {
            ops_fatal('A program file is missing', 'It looks like not all files uploaded. Re-upload the whole app, making sure the <b>lib/</b> folder (with <code>ops.php</code>) and the <b>views/ops/</b> folder are present.', $msg . "\n" . $e['file'] . ':' . $e['line']);
        }
        ops_fatal('The app hit an error', 'Please screenshot this and send it over.', $msg . "\n" . $e['file'] . ':' . $e['line']);
    }
});
set_exception_handler(function ($ex) {
    ops_fatal('The app hit an error', 'Please screenshot this and send it over.', $ex->getMessage() . "\n" . $ex->getFile() . ':' . $ex->getLine());
});

try {
    require __DIR__ . '/lib/db.php';
    require __DIR__ . '/lib/helpers.php';
    require __DIR__ . '/lib/ops.php';
    require __DIR__ . '/lib/lookups.php';
    require __DIR__ . '/lib/access.php';
    require __DIR__ . '/lib/crm.php';
    require __DIR__ . '/lib/pdf.php';
    require __DIR__ . '/lib/ai.php';
    require __DIR__ . '/lib/workforce.php';
    require __DIR__ . '/lib/idems.php';
    require __DIR__ . '/lib/seed_demo.php';
} catch (Throwable $e) {
    ops_fatal('A program file is missing or has an error', 'Re-upload the app — make sure <b>lib/ops.php</b> and the <b>views/ops/</b> folder are present.', $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine());
}

// Bootstrap / upgrade: this quick probe fails on a fresh install (no table) or
// when a new table/column is missing, which triggers the idempotent boot/migrate.
try {
    // Probe the NEWEST additions too — a miss here triggers boot(), whose
    // idempotent migrate() then adds every pending table/column in one pass.
    db()->query("SELECT address_id FROM partner_contacts LIMIT 1");
    db()->query("SELECT id FROM offices LIMIT 1");
    db()->query("SELECT id FROM lookup_types LIMIT 1");
    db()->query("SELECT deliverables FROM jobs LIMIT 1");
    db()->query("SELECT inspection_types FROM business_partners LIMIT 1");
    db()->query("SELECT trade_id FROM inspectors LIMIT 1");
    db()->query("SELECT id FROM inspector_certs LIMIT 1");
    db()->query("SELECT scope_offices FROM users LIMIT 1");
    db()->query("SELECT skey FROM settings LIMIT 1");
    db()->query("SELECT home_branch_id FROM business_partners LIMIT 1");
    db()->query("SELECT stage FROM candidates LIMIT 1");
    db()->query("SELECT extra FROM expenses LIMIT 1");
    db()->query("SELECT id FROM expense_heads LIMIT 1");
    db()->query("SELECT id FROM travel_modes LIMIT 1");
    db()->query("SELECT id FROM inspector_allowances LIMIT 1");
    db()->query("SELECT id FROM voucher_entries LIMIT 1");
    db()->query("SELECT id FROM vendor_km_memory LIMIT 1");
    db()->query("SELECT agency_cost FROM inspectors LIMIT 1");
    // Newest columns — a miss here triggers the idempotent upgrade so live
    // databases set up on an older version gain every pending column at once.
    db()->query("SELECT overhead_pct FROM offices LIMIT 1");
    db()->query("SELECT invoice_raised FROM jobs LIMIT 1");
    db()->query("SELECT supersedes FROM boss_numbers LIMIT 1");
    db()->query("SELECT billable_value FROM calls LIMIT 1");
    db()->query("SELECT id FROM agencies LIMIT 1");
    db()->query("SELECT agency_id FROM inspectors LIMIT 1");
    db()->query("SELECT id FROM requisitions LIMIT 1");
    db()->query("SELECT fee_status FROM inspectors LIMIT 1");
    // CRM module tables (Phase 0) — a miss creates the whole CRM data model.
    db()->query("SELECT quote_no FROM quotations LIMIT 1");
    db()->query("SELECT id FROM crm_inquiries LIMIT 1");
    db()->query("SELECT id FROM quote_approval_rules LIMIT 1");
    db()->query("SELECT document_number FROM crm_templates LIMIT 1");
    db()->query("SELECT quotation_id FROM jobs LIMIT 1");
    db()->query("SELECT cv_keywords FROM candidates LIMIT 1");
    // Workforce pack — availability board, weekly working days, reporting manager.
    db()->query("SELECT weekly_working_days FROM inspectors LIMIT 1");
    db()->query("SELECT id FROM inspector_day_status LIMIT 1");
    db()->query("SELECT reports_to_name FROM users LIMIT 1");
    db()->query("SELECT report_approval FROM jobs LIMIT 1");
    db()->query("SELECT id FROM work_norms LIMIT 1");
    // IDEMS report engine tables
    db()->query("SELECT irn FROM report_docs LIMIT 1");
    db()->query("SELECT id FROM report_types LIMIT 1");
    db()->query("SELECT id FROM idems_audit LIMIT 1");
    db()->query("SELECT id FROM report_sections LIMIT 1");
    db()->query("SELECT id FROM report_fields LIMIT 1");
    db()->query("SELECT id FROM report_files LIMIT 1");
    db()->query("SELECT id FROM report_approvals LIMIT 1");
    db()->query("SELECT id FROM idems_approval_rules LIMIT 1");
    db()->query("SELECT signature FROM users LIMIT 1");
    db()->query("SELECT id FROM report_templates LIMIT 1");
    db()->query("SELECT id FROM endorsements LIMIT 1");
    db()->query("SELECT id FROM tech_phrases LIMIT 1");
} catch (Throwable $ex) {
    try {
        boot();
    } catch (Throwable $e2) {
        $m = $e2->getMessage();
        $isConn = stripos($m, 'connect') !== false || stripos($m, 'access denied') !== false
               || stripos($m, 'unknown database') !== false || stripos($m, 'no such file') !== false;
        $hint = $isConn
            ? 'Open <code>config.php</code> and enter your MySQL database name, user and password (from MilesWeb → Databases).'
            : 'The database is reachable but a table could not be set up. Send this message over and it will be fixed quickly.';
        ops_fatal($isConn ? 'Database not connected' : 'Database setup error', $hint, $m . "\n" . $e2->getFile() . ':' . $e2->getLine());
    }
}

// --- Router (single-segment routes; ids/tabs via query string) ---
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$route = trim($path, '/');
if ($route === 'index.php' || $route === '') $route = '';
if (isset($_GET['r'])) $route = trim($_GET['r'], '/');
$method = $_SERVER['REQUEST_METHOD'];

$pdo = db();

function view($name, $vars = []) {
    extract($vars);
    require __DIR__ . '/views/layout_top.php';
    require __DIR__ . "/views/$name.php";
    require __DIR__ . '/views/layout_bottom.php';
}

function find_partner($id) {
    $q = db()->prepare("SELECT * FROM business_partners WHERE id = ?");
    $q->execute([$id]);
    return $q->fetch();
}
function children($table, $pid, $order = 'id') {
    $q = db()->prepare("SELECT * FROM $table WHERE partner_id = ? ORDER BY $order");
    $q->execute([$pid]);
    return $q->fetchAll();
}

// --- Public routes ---
function render_login($error) {
    require __DIR__ . '/views/login_page.php';
    exit;
}
if ($route === 'login') {
    if ($method === 'POST') {
        $q = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $q->execute([$_POST['username'] ?? '']);
        $u = $q->fetch();
        if ($u && $u['is_active'] && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
            $_SESSION['uid'] = $u['id'];
            flash('Welcome, ' . user_name($u) . '.');
            redirect('/');
        }
        return render_login('Invalid username or password.');
    }
    if (current_user()) redirect('/');
    return render_login(null);
}
if ($route === 'logout') { session_destroy(); redirect('/login'); }

// --- Everything below requires login ---
require_login();

if ($route === '') {
    $clients = (int)$pdo->query("SELECT COUNT(*) FROM business_partners WHERE is_client=1")->fetchColumn();
    $vendors = (int)$pdo->query("SELECT COUNT(*) FROM business_partners WHERE is_vendor=1")->fetchColumn();
    return view('dashboard', ['clients' => $clients, 'vendors' => $vendors]);
}

if ($route === 'clients' || $route === 'vendors') {
    ops_require(can('mod.' . $route . '.view'), 'You don’t have access to the ' . ucfirst($route) . ' module. Ask your administrator.');
    $roleField = $route === 'clients' ? 'is_client' : 'is_vendor';
    $q = trim($_GET['q'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per = 40;
    $where = "$roleField = 1";
    $args = [];
    if ($status) { $where .= " AND status = ?"; $args[] = $status; }
    if ($q) {
        $where .= " AND (legal_name LIKE ? OR display_name LIKE ? OR code LIKE ? OR gstin LIKE ? OR pan LIKE ?)";
        for ($i = 0; $i < 5; $i++) $args[] = "%$q%";
    }
    $total = (int)(function() use ($pdo, $where, $args) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM business_partners WHERE $where"); $s->execute($args); return $s->fetchColumn();
    })();
    $s = $pdo->prepare("SELECT * FROM business_partners WHERE $where ORDER BY legal_name LIMIT $per OFFSET " . (($page - 1) * $per));
    $s->execute($args);
    return view('list', [
        'rows' => $s->fetchAll(), 'total' => $total, 'roleField' => $roleField,
        'title' => $route === 'clients' ? 'Client Master' : 'Vendor Master',
        'subtitle' => $route === 'clients' ? 'Customers, contacts, contracts and billing references' : 'Manufacturers, suppliers, plants and their contacts',
        'q' => $q, 'status' => $status, 'page' => $page, 'pages' => (int)ceil($total / $per),
    ]);
}

if ($route === 'partner-new') {
    if ($method === 'POST') {
        $b = $_POST;
        $formVars = ['partner' => null, 'defaultRole' => 'is_client', 'offices' => offices_list()];
        if (!empty($b['gstin']) && !is_valid_gstin($b['gstin'])) {
            return view('form', $formVars + ['error' => 'GSTIN should be 15 characters, e.g. 24ADUPL3517E2ZJ.']);
        }
        // sub-contractor is a manpower vendor → force the vendor role on
        if (!empty($b['is_subcontractor'])) $b['is_vendor'] = 1;
        if (empty($b['is_client']) && empty($b['is_vendor']) && empty($b['is_subcontractor'])) {
            return view('form', $formVars + ['error' => 'Select at least one role (Client / Vendor / Both).']);
        }
        $gstin = clean_gstin($b['gstin'] ?? '');
        $pan = $gstin ? pan_from_gstin($gstin) : '';
        $state = $gstin ? state_from_gstin($gstin) : '';
        // duplicate guard — by GSTIN / PAN / TAN / name
        $dup = find_duplicate_partner($b['legal_name'] ?? '', $gstin, $pan, $b['tan'] ?? '', 0);
        if ($dup) {
            return view('form', $formVars + ['error' => "This company already exists as {$dup['row']['code']} — {$dup['row']['legal_name']} (matched by {$dup['by']}). Open it from the Clients/Vendors list and tick the extra role instead of creating a duplicate."]);
        }
        $b['client_type'] = resolve_new_lookup('client_type', $b['client_type'] ?? '', $b['client_type_new'] ?? '');
        $b['industry'] = resolve_new_lookup('industry', $b['industry'] ?? '', $b['industry_new'] ?? '');
        $branchId = ($b['home_branch_id'] ?? '') !== '' ? (int)$b['home_branch_id'] : null;
        $code = gen_partner_code($branchId, ($b['display_name'] ?? '') ?: ($b['legal_name'] ?? ''));
        $ins = $pdo->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,is_vendor,is_subcontractor,client_type,industry,ownership_type,status,gstin,pan,cin,tan,msme_udyam,state,website,description,home_branch_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $ins->execute([$code, $b['legal_name'], $b['display_name'] ?? '', !empty($b['is_client'])?1:0, !empty($b['is_vendor'])?1:0, !empty($b['is_subcontractor'])?1:0, $b['client_type'] ?? '', $b['industry'] ?? '', $b['ownership_type'] ?? '', $b['status'] ?? 'ACTIVE', $gstin, $pan, $b['cin'] ?? '', $b['tan'] ?? '', $b['msme_udyam'] ?? '', $state, $b['website'] ?? '', $b['description'] ?? '', $branchId, date('c')]);
        $id = $pdo->lastInsertId();
        $pdo->prepare("UPDATE business_partners SET inspection_types=? WHERE id=?")
            ->execute([implode(',', array_filter((array)($b['inspection_types'] ?? []))), $id]);
        custom_save('partner', $id, $b);
        flash("$code created.");
        redirect("/partner?id=$id");
    }
    return view('form', ['partner' => null, 'defaultRole' => $_GET['role'] ?? 'is_client', 'error' => null, 'offices' => offices_list(), 'pcfvals' => []]);
}

if ($route === 'partner-edit') {
    $p = find_partner((int)($_GET['id'] ?? 0));
    if (!$p) { http_response_code(404); return view('notfound'); }
    if ($method === 'POST') {
        $b = $_POST;
        if (!empty($b['is_subcontractor'])) $b['is_vendor'] = 1; // sub-contractor ⇒ vendor
        $b['client_type'] = resolve_new_lookup('client_type', $b['client_type'] ?? '', $b['client_type_new'] ?? '');
        $b['industry'] = resolve_new_lookup('industry', $b['industry'] ?? '', $b['industry_new'] ?? '');
        $gstin = clean_gstin($b['gstin'] ?? '');
        $pan = $gstin ? pan_from_gstin($gstin) : $p['pan'];
        $state = $gstin ? state_from_gstin($gstin) : $p['state'];
        $dup = find_duplicate_partner($b['legal_name'] ?? '', $gstin, $pan ?: ($p['pan'] ?? ''), $b['tan'] ?? '', $p['id']);
        if ($dup) {
            return view('form', ['partner' => $p, 'defaultRole' => 'is_client', 'offices' => offices_list(),
                'error' => "Another company already uses these details: {$dup['row']['code']} — {$dup['row']['legal_name']} (matched by {$dup['by']})."]);
        }
        $pdo->prepare("UPDATE business_partners SET legal_name=?,display_name=?,is_client=?,is_vendor=?,is_subcontractor=?,client_type=?,industry=?,ownership_type=?,status=?,gstin=?,pan=?,cin=?,tan=?,msme_udyam=?,state=?,website=?,description=? WHERE id=?")
            ->execute([$b['legal_name'], $b['display_name'] ?? '', !empty($b['is_client'])?1:0, !empty($b['is_vendor'])?1:0, !empty($b['is_subcontractor'])?1:0, $b['client_type'] ?? '', $b['industry'] ?? '', $b['ownership_type'] ?? '', $b['status'] ?? 'ACTIVE', $gstin, $pan, $b['cin'] ?? '', $b['tan'] ?? '', $b['msme_udyam'] ?? '', $state, $b['website'] ?? '', $b['description'] ?? '', $p['id']]);
        $pdo->prepare("UPDATE business_partners SET inspection_types=? WHERE id=?")
            ->execute([implode(',', array_filter((array)($b['inspection_types'] ?? []))), $p['id']]);
        custom_save('partner', $p['id'], $b);
        flash('Updated.');
        redirect("/partner?id={$p['id']}");
    }
    return view('form', ['partner' => $p, 'defaultRole' => 'is_client', 'error' => null, 'offices' => offices_list(), 'pcfvals' => custom_values_map('partner', $p['id'])]);
}

if ($route === 'partner-add' && $method === 'POST') {
    $p = find_partner((int)($_GET['id'] ?? 0));
    if (!$p) { http_response_code(404); return view('notfound'); }
    $kind = $_GET['kind'] ?? '';
    $b = $_POST;
    $map = [
        'contact' => ['partner_contacts', ['name','designation','department','project','email','mobile','phone','address_id'], 'contacts'],
        'address' => ['partner_addresses', ['address_type','label','line1','line2','town_village','district','city','state','pincode','country'], 'addresses'],
        'registration' => ['partner_registrations', ['doc_type','number','valid_to','notes'], 'registration'],
        'contract' => ['partner_contracts', ['contract_number','title','sbu','value','start_date','end_date','notes'], 'contracts'],
        'relationship' => ['partner_relationships', ['relation_type','related_id','notes'], 'relationships'],
    ];
    if ($kind === 'note') {
        $pdo->prepare("INSERT INTO partner_notes (partner_id,note,author_name,created_at) VALUES (?,?,?,?)")
            ->execute([$p['id'], $b['note'] ?? '', user_name(current_user()), date('c')]);
        flash('Note added.');
        redirect("/partner?id={$p['id']}&tab=notes");
    }
    if ($kind === 'po') {
        $poSbu = isset($b['po_sbu']) ? implode(',', array_filter((array)$b['po_sbu'])) : ($b['sbu'] ?? '');
        $pdo->prepare("INSERT INTO partner_purchase_orders (partner_id,contract_id,sbu,po_number,po_type,title,value,start_date,end_date,notes) VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$p['id'], ($b['contract_id'] ?? '') !== '' ? $b['contract_id'] : null, $poSbu, $b['po_number'] ?? '', $b['po_type'] ?? 'REGULAR', $b['title'] ?? '', ($b['value'] ?? '') !== '' ? $b['value'] : null, $b['start_date'] ?? '', $b['end_date'] ?? '', $b['notes'] ?? '']);
        flash('Purchase order added.');
        redirect('/po?id=' . $pdo->lastInsertId());
    }
    if (isset($map[$kind])) {
        [$table, $fields, $tab] = $map[$kind];
        if ($kind === 'address' && !empty($b['city'])) $b['city'] = normalise_city($b['city']); // light spell-normalise
        $cols = array_merge(['partner_id'], $fields);
        $ph = implode(',', array_fill(0, count($cols), '?'));
        $vals = [$p['id']];
        foreach ($fields as $f) {
            $v = $b[$f] ?? '';
            if (($f === 'address_id' || $f === 'related_id' || $f === 'value') && $v === '') $v = null;
            $vals[] = $v;
        }
        $pdo->prepare("INSERT INTO $table (" . implode(',', $cols) . ") VALUES ($ph)")->execute($vals);
        flash('Added.');
        redirect("/partner?id={$p['id']}&tab=$tab");
    }
    redirect("/partner?id={$p['id']}");
}

if ($route === 'partner') {
    $p = find_partner((int)($_GET['id'] ?? 0));
    if (!$p) { http_response_code(404); return view('notfound'); }
    $subs = $pdo->prepare("SELECT * FROM business_partners WHERE parent_id = ?"); $subs->execute([$p['id']]);
    $parent = $p['parent_id'] ? find_partner($p['parent_id']) : null;
    return view('detail', [
        'p' => $p, 'tab' => $_GET['tab'] ?? 'overview', 'parent' => $parent,
        'subsidiaries' => $subs->fetchAll(),
        'addresses' => children('partner_addresses', $p['id']),
        'contacts' => children('partner_contacts', $p['id']),
        'registrations' => children('partner_registrations', $p['id']),
        'notes' => children('partner_notes', $p['id'], 'id DESC'),
        'contracts' => children('partner_contracts', $p['id']),
        'pos' => children('partner_purchase_orders', $p['id']),
        'rels' => (function() use ($pdo, $p) { $s = $pdo->prepare("SELECT r.*, b.legal_name rn, b.display_name rd, b.id rid FROM partner_relationships r LEFT JOIN business_partners b ON b.id=r.related_id WHERE r.partner_id=?"); $s->execute([$p['id']]); return $s->fetchAll(); })(),
        'all_partners' => (function() use ($pdo, $p) { $s = $pdo->prepare("SELECT id, legal_name, display_name FROM business_partners WHERE id <> ? ORDER BY legal_name"); $s->execute([$p['id']]); return $s->fetchAll(); })(),
        'cityList' => array_values(array_filter(array_column($pdo->query("SELECT DISTINCT city FROM partner_addresses WHERE city <> '' ORDER BY city")->fetchAll(), 'city'))),
        'linkedCalls' => (function() use ($pdo, $p) {
            try { $s = $pdo->prepare("SELECT id, call_code, inspection_type, status, call_received_date, inspection_required_date FROM calls WHERE client_id=? OR vendor_id=? ORDER BY id DESC"); $s->execute([$p['id'], $p['id']]); return $s->fetchAll(); }
            catch (Throwable $e) { return []; }
        })(),
    ]);
}

if ($route === 'po') {
    $s = $pdo->prepare("SELECT po.*, b.legal_name pn, b.display_name pdn FROM partner_purchase_orders po LEFT JOIN business_partners b ON b.id=po.partner_id WHERE po.id=?");
    $s->execute([(int)($_GET['id'] ?? 0)]);
    $po = $s->fetch();
    if (!$po) { http_response_code(404); return view('notfound'); }
    if ($method === 'POST') {
        $b = $_POST;
        $qty = (float)($b['quantity'] ?? 0); $rate = (float)($b['rate'] ?? 0); $gst = (float)($b['gst_pct'] ?? 0);
        $base = $qty * $rate; $tax = round($base * $gst / 100, 2); $total = $base + $tax;
        $pdo->prepare("INSERT INTO po_line_items (purchase_order_id,description,item_type,trade_id,skill_id,activity_id,site,manpower,quantity,rate,consumed,gst_pct,base_amount,tax_amount,total_amount)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$po['id'], $b['description'], $b['item_type'] ?? 'MANDAYS',
                ($b['trade_id'] ?? '') !== '' ? (int)$b['trade_id'] : null,
                ($b['skill_id'] ?? '') !== '' ? (int)$b['skill_id'] : null,
                ($b['activity_id'] ?? '') !== '' ? (int)$b['activity_id'] : null,
                $b['site'] ?? '', (int)(($b['manpower'] ?? 0) ?: 0), $qty, $rate ?: null, (float)(($b['consumed'] ?? 0) ?: 0), $gst, $base, $tax, $total]);
        // roll the PO value up to the sum of its line-item totals
        $sum = (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM po_line_items WHERE purchase_order_id=" . (int)$po['id'])->fetchColumn();
        $pdo->prepare("UPDATE partner_purchase_orders SET value=? WHERE id=?")->execute([$sum, $po['id']]);
        // if the PO is against a contract, roll the contract value up too
        if ($po['contract_id']) {
            $cSum = (float)$pdo->query("SELECT COALESCE(SUM(value),0) FROM partner_purchase_orders WHERE contract_id=" . (int)$po['contract_id'])->fetchColumn();
            $pdo->prepare("UPDATE partner_contracts SET value=? WHERE id=?")->execute([$cSum, $po['contract_id']]);
        }
        flash('Line item added. PO value updated to ₹' . number_format($sum, 0) . '.');
        redirect('/po?id=' . $po['id']);
    }
    $li = $pdo->prepare("SELECT l.*, t.label trade_label, s.label skill_label, a.label activity_label
        FROM po_line_items l LEFT JOIN lookup_values t ON t.id=l.trade_id LEFT JOIN lookup_values s ON s.id=l.skill_id
        LEFT JOIN lookup_values a ON a.id=l.activity_id WHERE l.purchase_order_id = ?");
    $li->execute([$po['id']]);
    // activities available for this PO's SBU(s)
    $poSbus = array_filter(explode(',', $po['sbu'] ?? ''));
    $actBySbu = activity_options_by_sbu();
    $poActivities = [];
    foreach ($poSbus as $sc) foreach (($actBySbu[$sc] ?? []) as $a) $poActivities[] = $a;
    if (!$poActivities) foreach ($actBySbu as $list) foreach ($list as $a) $poActivities[] = $a; // fallback: all
    return view('po_detail', ['po' => $po, 'items' => $li->fetchAll(), 'skillsByTrade' => skills_by_trade(),
        'trades' => lk_type('trade') ? lk_root_values(lk_type('trade')['id']) : [], 'poActivities' => $poActivities]);
}

// --- Operations & Finance modules (Calls, Jobs, masters, reports, users) ---
if (ops_dispatch($route, $method)) return;

http_response_code(404);
return view('notfound');
