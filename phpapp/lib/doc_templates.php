<?php
// ============================================================================
//  EXAACT Recruitment — Configurable Document Studio (Phase 5.1B)
//
//  Offer letters, appointment letters and any candidate document are DATA:
//  an administrator edits the template body (with {tokens}) and a configurable
//  letterhead + footer. When a letter is generated for a candidate, every
//  variable is auto-filled from the data we already hold (name, position,
//  phone, address, the salary structure, terms, company …). Anything we do NOT
//  have is HIGHLIGHTED and listed, so nothing is issued with a silent blank.
//  Additive and non-destructive.
// ============================================================================

const DOC_TPL_TYPES = ['OFFER' => 'Offer letter', 'APPOINTMENT' => 'Appointment letter', 'OTHER' => 'Other document'];

function doc_tpl_migrate() {
    static $done = false; if ($done) return; $done = true;
    if (!function_exists('ensure_column')) return;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $lt = (function_exists('db_driver') && db_driver() === 'sqlite') ? 'TEXT' : 'LONGTEXT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS doc_templates (
            id $pk,
            code VARCHAR(40) DEFAULT '',
            name VARCHAR(160) DEFAULT '',
            doc_type VARCHAR(20) DEFAULT 'OTHER',
            body $lt,
            active INT DEFAULT 1,
            created_at VARCHAR(30) DEFAULT ''
        )");
    } catch (Throwable $e) { return; }
    doc_tpl_seed();
    doc_tpl_seed_extra();   // backfill common extra letters into existing installs too
}

// Additional ready-made letters (relieving, confirmation, internship). Idempotent
// by code, so existing installs pick them up and none is ever duplicated. These
// are ordinary editable templates — a starting point, not a fixed format.
function doc_tpl_seed_extra() {
    $now = function_exists('now_iso') ? now_iso() : date('c');
    $have = function ($code) { try { return (int)ops_one("SELECT COUNT(*) c FROM doc_templates WHERE code=?", [$code])['c'] > 0; } catch (Throwable $e) { return true; } };
    $add = function ($code, $name, $body) use ($now, $have) {
        if ($have($code)) return;
        db()->prepare("INSERT INTO doc_templates (code,name,doc_type,body,active,created_at) VALUES (?,?, 'OTHER', ?,1,?)")->execute([$code, $name, $body, $now]);
    };
    $add('CONFIRMATION', 'Confirmation of employment',
        "Date: {date}\n\nDear {name},\n\nSub: Confirmation of employment — {position}\n\n"
        . "We are pleased to confirm your employment as {position} in the {department} department at {company} "
        . "with effect from {date}, following the successful completion of your probation.\n\n"
        . "All other terms and conditions of your appointment remain unchanged.\n\n{terms}\n\n"
        . "We congratulate you and look forward to your continued contribution.\n\nWarm regards,\nFor {company}\n\nAuthorised Signatory");
    $add('RELIEVING', 'Relieving & experience letter',
        "Date: {date}\n\nTO WHOMSOEVER IT MAY CONCERN\n\n"
        . "This is to certify that {name} was employed with {company} as {position} in the {department} department.\n\n"
        . "During the tenure of employment, their conduct and performance were found to be satisfactory. "
        . "They are hereby relieved of their duties with effect from {date}.\n\n"
        . "We wish {name} every success in their future endeavours.\n\nWarm regards,\nFor {company}\n\nAuthorised Signatory");
    $add('INTERNSHIP', 'Internship offer letter',
        "Date: {date}\n\nDear {name},\n\nSub: Internship offer — {position}\n\n"
        . "We are pleased to offer you an internship as {position} in the {department} department at {company}, "
        . "commencing {joining_date}.\n\nA stipend, where applicable, and the terms of the internship are set out below.\n\n"
        . "{salary_table}\n\n{terms}\n\nPlease confirm your acceptance by signing and returning a copy of this letter.\n\n"
        . "Warm regards,\nFor {company}\n\nAuthorised Signatory");
}

function doc_tpl_seed() {
    try { if ((int)ops_one("SELECT COUNT(*) c FROM doc_templates")['c'] > 0) return; }
    catch (Throwable $e) { return; }
    $now = function_exists('now_iso') ? now_iso() : date('c');
    if (function_exists('setting_get')) {
        foreach ([
            'doc_letterhead' => "<div style=\"text-align:center;border-bottom:2px solid #333;padding-bottom:10px;margin-bottom:18px\"><div style=\"font-size:20px;font-weight:700\">{company}</div><div style=\"font-size:12px;color:#555\">{company_address}</div></div>",
            'doc_footer'     => "<div style=\"border-top:1px solid #ccc;margin-top:26px;padding-top:8px;font-size:11px;color:#777;text-align:center\">{company} · This is a system-generated document.</div>",
            'company_address'=> '',
        ] as $k => $v) if (trim((string)setting_get($k, '')) === '') setting_set($k, $v);
    }
    $offer = "Date: {date}\n\nDear {name},\n\nSub: Offer of employment — {position}\n\n"
        . "We are pleased to offer you the position of {position} in the {department} department at {company}.\n\n"
        . "Your total annual compensation (CTC) will be {ctc}, with a net take-home of {net_pay}. "
        . "Your expected date of joining is {joining_date}. This offer is valid till {offer_valid_till}.\n\n"
        . "The detailed compensation structure is set out below:\n\n{salary_table}\n\n"
        . "{terms}\n\nPlease sign and return a copy of this letter as a token of your acceptance.\n\n"
        . "Warm regards,\nFor {company}\n\nAuthorised Signatory";
    $appt = "Date: {date}\n\nDear {name},\n\nSub: Letter of Appointment — {position}\n\n"
        . "With reference to your acceptance of our offer, we are pleased to appoint you as {position} "
        . "in the {department} department at {company}, effective {joining_date}.\n\n"
        . "Your compensation and terms are as per the annexure below and the company's policies.\n\n{salary_table}\n\n"
        . "{terms}\n\nWe welcome you to {company} and look forward to a long and mutually rewarding association.\n\n"
        . "Warm regards,\nFor {company}\n\nAuthorised Signatory";
    $ins = db()->prepare("INSERT INTO doc_templates (code,name,doc_type,body,active,created_at) VALUES (?,?,?,?,1,?)");
    $ins->execute(['OFFER', 'Standard offer letter', 'OFFER', $offer, $now]);
    $ins->execute(['APPOINTMENT', 'Standard appointment letter', 'APPOINTMENT', $appt, $now]);
}

function doc_tpl_all($activeOnly = true) {
    doc_tpl_migrate();
    $w = $activeOnly ? "WHERE active=1" : "";
    return ops_all("SELECT * FROM doc_templates $w ORDER BY doc_type, name, id");
}
function doc_tpl_get($id) { doc_tpl_migrate(); return ops_one("SELECT * FROM doc_templates WHERE id=?", [(int)$id]) ?: null; }
function doc_tpl_by_code($code) { doc_tpl_migrate(); return ops_one("SELECT * FROM doc_templates WHERE code=? AND active=1 ORDER BY id LIMIT 1", [(string)$code]) ?: null; }

function doc_tpl_save($id, $post) {
    doc_tpl_migrate();
    $type = array_key_exists($post['doc_type'] ?? '', DOC_TPL_TYPES) ? $post['doc_type'] : 'OTHER';
    $code = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', (string)($post['code'] ?? ''))) ?: ('DOC' . substr((string)time(), -5));
    if ((int)$id > 0) {
        db()->prepare("UPDATE doc_templates SET code=?,name=?,doc_type=?,body=? WHERE id=?")
            ->execute([$code, trim((string)($post['name'] ?? '')), $type, (string)($post['body'] ?? ''), (int)$id]);
        return (int)$id;
    }
    db()->prepare("INSERT INTO doc_templates (code,name,doc_type,body,active,created_at) VALUES (?,?,?,?,1,?)")
        ->execute([$code, trim((string)($post['name'] ?? 'New template')), $type, (string)($post['body'] ?? ''), function_exists('now_iso') ? now_iso() : date('c')]);
    return (int)db()->lastInsertId();
}
function doc_tpl_set_active($id, $on) { doc_tpl_migrate(); db()->prepare("UPDATE doc_templates SET active=? WHERE id=?")->execute([$on ? 1 : 0, (int)$id]); }

// ---- The token catalogue (for the studio reference + resolution) -----------
function doc_tokens_help() {
    return [
        'name' => "Candidate's full name", 'first_name' => 'First name', 'last_name' => 'Last name',
        'position' => 'Position / designation', 'department' => 'Department', 'grade' => 'Grade',
        'phone' => 'Phone / mobile', 'email' => 'Email', 'address' => 'Address', 'location' => 'Location',
        'ctc' => 'Total CTC', 'net_pay' => 'Net (in-hand) pay', 'salary_table' => 'The full salary-structure table',
        'joining_date' => 'Date of joining', 'offer_valid_till' => 'Offer validity date', 'terms' => 'Offer terms',
        'company' => 'Company / workspace name', 'company_address' => 'Company address',
        'date' => "Today's date", 'cand_code' => 'Candidate code',
    ];
}

// Build the value map for a candidate — self-contained (own lookups).
function doc_token_map($candidate) {
    $cur = function_exists('cur_sym') ? cur_sym() : (function_exists('setting_get') ? (setting_get('currency_symbol', '₹') ?: '₹') : '₹');
    $money = fn($v) => $cur . number_format((float)$v, 0);
    $g = fn($k) => isset($candidate[$k]) ? trim((string)$candidate[$k]) : '';
    $company = function_exists('app_name') ? app_name() : (function_exists('setting_get') ? setting_get('app_name', '') : '');
    $desig = $g('designation');
    $position = (defined('DESIGNATIONS') && isset(DESIGNATIONS[$desig])) ? DESIGNATIONS[$desig] : $desig;

    $req = !empty($candidate['requisition_id']) ? ops_one("SELECT * FROM requisitions WHERE id=?", [(int)$candidate['requisition_id']]) : null;
    $dept = $req['department'] ?? ''; $grade = $req['grade'] ?? '';
    if ((!$dept || !$grade) && $req && !empty($req['position_id']) && function_exists('position_get')) {
        $pos = position_get((int)$req['position_id']);
        if ($pos) { $dept = $dept ?: ($pos['department'] ?? ''); $grade = $grade ?: ($pos['grade'] ?? ''); }
    }
    $sal = function_exists('sal_current') ? sal_current((int)$candidate['id']) : null;
    $offer = function_exists('offer_current') ? offer_current((int)$candidate['id']) : null;

    // The salary annexure table (HTML) — reuses the offer-letter renderer's style.
    $salTable = '';
    if ($sal && function_exists('sal_lines')) {
        $secLbl = ['EARNING' => 'Earnings', 'DEDUCTION' => 'Deductions', 'EMPLOYER' => 'Employer contributions'];
        $salTable = '<table style="width:100%;border-collapse:collapse;font-family:Arial,sans-serif;margin:6px 0">';
        foreach (['EARNING', 'DEDUCTION', 'EMPLOYER'] as $sec) {
            $sl = array_filter(sal_lines($sal), fn($l) => ($l['section'] ?? 'EARNING') === $sec);
            if (!$sl) continue;
            $salTable .= '<tr><td colspan="2" style="padding:7px 8px 2px;font-weight:700;color:#555;font-size:12px">' . htmlspecialchars($secLbl[$sec], ENT_QUOTES) . '</td></tr>';
            foreach ($sl as $l) $salTable .= '<tr><td style="padding:3px 8px;border-bottom:1px solid #eee">' . htmlspecialchars($l['name'], ENT_QUOTES) . '</td><td style="padding:3px 8px;border-bottom:1px solid #eee;text-align:right">' . htmlspecialchars($money($l['amount']), ENT_QUOTES) . '</td></tr>';
        }
        $salTable .= '<tr><td style="padding:6px 8px;font-weight:700;border-top:2px solid #333">Total CTC</td><td style="padding:6px 8px;text-align:right;font-weight:700;border-top:2px solid #333">' . htmlspecialchars($money($sal['gross_ctc']), ENT_QUOTES) . '</td></tr></table>';
    }

    $name = function_exists('candidate_name') ? candidate_name($candidate) : trim($g('first_name') . ' ' . $g('last_name'));
    return [
        'name' => $name, 'first_name' => $g('first_name'), 'last_name' => $g('last_name'),
        'position' => $position, 'department' => $dept, 'grade' => $grade,
        'phone' => $g('mobile'), 'email' => $g('email'), 'address' => $g('address'),
        'location' => $g('proposed_site') ?: $g('location'),
        'ctc' => $sal ? $money($sal['gross_ctc']) : '', 'net_pay' => $sal ? $money($sal['net_pay']) : '',
        'salary_table' => $salTable,
        'joining_date' => $offer ? ($offer['joining_date'] ?? '') : '', 'offer_valid_till' => $offer ? ($offer['expiry_date'] ?? '') : '',
        'terms' => $offer ? (string)($offer['offer_terms'] ?? '') : '',
        'company' => $company, 'company_address' => function_exists('setting_get') ? setting_get('company_address', '') : '',
        'date' => date('d M Y'), 'cand_code' => $g('cand_code'),
    ];
}

// Replace {tokens} in $text using $map. Empty/unknown tokens are HIGHLIGHTED and
// collected into $missing. $salTable-style HTML tokens are inserted as-is.
function doc_fill($text, $map, &$missing) {
    $htmlTokens = ['salary_table'];
    return preg_replace_callback('/\{([a-z_]+)\}/', function ($m) use ($map, &$missing, $htmlTokens) {
        $k = $m[1];
        if (!array_key_exists($k, $map)) { $missing[$k] = 'unknown'; return '<span style="background:#fde68a;color:#92400e;padding:0 3px;border-radius:3px">«' . htmlspecialchars($k) . '?»</span>'; }
        $v = (string)$map[$k];
        if (in_array($k, $htmlTokens, true)) return $v !== '' ? $v : (function () use (&$missing, $k) { $missing[$k] = 'empty'; return '<span style="background:#fde68a;color:#92400e;padding:0 3px;border-radius:3px">«' . htmlspecialchars($k) . ' missing»</span>'; })();
        if (trim($v) === '') { $missing[$k] = 'empty'; return '<span style="background:#fde68a;color:#92400e;padding:0 3px;border-radius:3px">«' . htmlspecialchars($k) . ' missing»</span>'; }
        return in_array($k, $htmlTokens, true) ? $v : nl2br(htmlspecialchars($v, ENT_QUOTES));
    }, htmlspecialchars($text, ENT_QUOTES));
}

// Render a full document (letterhead + body + footer) for a candidate.
// Returns ['html'=>…, 'missing'=>[token=>reason,…]].
function doc_render_template($tpl, $candidate) {
    doc_tpl_migrate();
    $map = doc_token_map($candidate);
    $missing = [];
    $head = function_exists('setting_get') ? (string)setting_get('doc_letterhead', '') : '';
    $foot = function_exists('setting_get') ? (string)setting_get('doc_footer', '') : '';
    // Letterhead/footer are stored as HTML with {tokens} — fill tokens but keep HTML.
    $fillHtml = function ($raw) use ($map, &$missing) {
        return preg_replace_callback('/\{([a-z_]+)\}/', function ($m) use ($map, &$missing) {
            $k = $m[1]; $v = (string)($map[$k] ?? '');
            if (trim($v) === '') { if ($k !== 'company_address') $missing[$k] = $missing[$k] ?? 'empty'; return ''; }
            return htmlspecialchars($v, ENT_QUOTES);
        }, $raw);
    };
    $bodyHtml = doc_fill((string)$tpl['body'], $map, $missing);
    $html = '<div style="font-family:Georgia,serif;max-width:760px;margin:auto;color:#1a2230;line-height:1.6">'
        . $fillHtml($head)
        . '<div style="white-space:normal;font-size:15px">' . $bodyHtml . '</div>'
        . $fillHtml($foot) . '</div>';
    return ['html' => $html, 'missing' => $missing];
}

// ---- Admin studio ----------------------------------------------------------
function ops_doc_templates($route, $method) {
    ops_require(hiring_admin_can(), 'Only an administrator can edit document templates.');
    doc_tpl_migrate();
    if ($method === 'POST') {
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'save') { $id = doc_tpl_save((int)($_POST['id'] ?? 0), $_POST); flash('Template saved.'); redirect('/doc-templates?id=' . $id); return true; }
        if ($do === 'toggle') { $t = doc_tpl_get((int)($_POST['id'] ?? 0)); if ($t) doc_tpl_set_active($t['id'], (int)$t['active'] === 0); flash('Template updated.'); redirect('/doc-templates'); return true; }
        if ($do === 'letterhead' && function_exists('setting_set')) {
            setting_set('doc_letterhead', (string)($_POST['doc_letterhead'] ?? ''));
            setting_set('doc_footer', (string)($_POST['doc_footer'] ?? ''));
            setting_set('company_address', trim((string)($_POST['company_address'] ?? '')));
            flash('Letterhead saved.'); redirect('/doc-templates'); return true;
        }
    }
    $selId = (int)($_GET['id'] ?? 0);
    view('ops/doc_templates', [
        'tpls'   => doc_tpl_all(false),
        'sel'    => $selId ? doc_tpl_get($selId) : null,
        'tokens' => doc_tokens_help(),
    ]);
    return true;
}

// Render a printable letter for a candidate from a template.
function ops_candidate_letter($route, $method) {
    doc_tpl_migrate();
    ops_require(can('mod.hiring.view'), 'You cannot view candidate letters.');
    $id = (int)($_GET['id'] ?? 0);
    $cand = ops_one("SELECT * FROM candidates WHERE id=?", [$id]);
    if (!$cand) { http_response_code(404); view('notfound'); return true; }
    $tpl = null;
    if (!empty($_GET['template'])) $tpl = doc_tpl_by_code((string)$_GET['template']);
    if (!$tpl && !empty($_GET['tid'])) $tpl = doc_tpl_get((int)$_GET['tid']);
    if (!$tpl) { flash('Template not found.', 'error'); redirect('/candidate?id=' . $id); return true; }
    $r = doc_render_template($tpl, $cand);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><meta charset="utf-8"><title>' . htmlspecialchars($tpl['name']) . '</title><body style="padding:30px;background:#fff">';
    echo '<div style="max-width:800px;margin:auto">';
    echo '<div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">';
    if ($r['missing']) {
        echo '<div style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;padding:8px 12px;border-radius:8px;font-family:Arial;font-size:13px">⚠ Missing data (highlighted below): <b>' . htmlspecialchars(implode(', ', array_keys($r['missing']))) . '</b> — fill these before issuing.</div>';
    } else {
        echo '<div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;padding:8px 12px;border-radius:8px;font-family:Arial;font-size:13px">✓ All fields filled.</div>';
    }
    echo '<button onclick="window.print()" style="padding:8px 14px;border:1px solid #ccc;border-radius:6px;cursor:pointer;font-family:Arial">Print / Save PDF</button></div>';
    echo $r['html'];
    echo '</div><style>@media print{.no-print{display:none}}</style></body>';
    return true;
}

// A block for the Offer tab: generate the configured letters for this candidate.
function recruit_letters_block($cand) {
    if (!is_array($cand) || empty($cand['id'])) return;
    $tpls = doc_tpl_all(true);
    if (!$tpls) return;
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $canManage = function_exists('is_admin_level') && is_admin_level();
    ?>
    <div class="panel">
      <h3 class="tab-sub">Documents to issue</h3>
      <p class="muted" style="margin-top:-4px;font-size:12.5px">Auto-generated from the candidate's data. Any missing field is highlighted in the letter.</p>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php foreach ($tpls as $t): ?>
          <a class="btn secondary" href="/candidate-letter?id=<?= (int)$cand['id'] ?>&amp;tid=<?= (int)$t['id'] ?>" target="_blank"><?= $e($t['name']) ?> →</a>
        <?php endforeach; ?>
      </div>
      <?php if ($canManage): ?><p class="muted" style="margin-top:8px;font-size:12px"><a href="/doc-templates">Configure templates &amp; letterhead →</a></p><?php endif; ?>
    </div>
    <?php
}
