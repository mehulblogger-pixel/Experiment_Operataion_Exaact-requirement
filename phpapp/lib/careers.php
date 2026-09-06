<?php
// ============================================================================
//  EXAACT Recruitment — Phase 7: public Careers site + application intake.
//  Additive and non-destructive. A company can publish selected open
//  requisitions to a public /careers page; a candidate applies with their
//  details + résumé, and a real `candidates` row is created (source=CAREERS,
//  stage RECEIVED) against that requisition — landing on the recruiter's desk
//  exactly like a manually-added candidate.
//
//  REUSE, never rebuild:
//   • CV reading      — connect_cv_extract_text() + recruit_cv_autofill()
//   • duplicate guard — cand_find_duplicates()
//   • résumé on file  — doc_upload() (candidate_docs, base64)
//   • code + events   — recruit_cand_code()/ops_next_code() + candidate_events
//   • mail            — ops_mail()
//  Publishing is opt-in per requisition (careers_published flag) — OPEN is an
//  internal lifecycle state, never an automatic consent to advertise publicly.
// ============================================================================

// Additive columns/settings. No new table (candidates carries the applicant).
function careers_migrate() {
    static $done = false; if ($done) return; $done = true;
    if (!function_exists('ensure_column')) return;
    try {
        // A public application writes candidates.department / recruiter_id (added
        // by the command centre's migration). Guarantee they exist so intake works
        // even before anyone has opened the recruitment dashboard.
        if (function_exists('rcc_migrate')) rcc_migrate();
        ensure_column('requisitions', 'careers_published', 'INT DEFAULT 0');   // opt-in consent to advertise
        ensure_column('requisitions', 'careers_summary',   'TEXT');            // public-facing description
    } catch (Throwable $e) { /* never break boot */ }
}

function careers_enabled() { careers_migrate(); return function_exists('setting_get') && (string)setting_get('careers_enabled', '0') === '1'; }
function careers_intro()   { return function_exists('setting_get') ? (string)setting_get('careers_intro', '') : ''; }

// Requisitions currently advertised to the public.
function careers_open_jobs() {
    careers_migrate();
    try {
        return ops_all("SELECT * FROM requisitions
                        WHERE careers_published=1
                          AND COALESCE(status,'') NOT IN ('CLOSED','FILLED','CANCELLED','on_hold','REJECTED')
                        ORDER BY id DESC") ?: [];
    } catch (Throwable $e) { return []; }
}
function careers_job($id) {
    careers_migrate();
    $j = ops_one("SELECT * FROM requisitions WHERE id=? AND careers_published=1", [(int)$id]);
    return $j ?: null;
}
function careers_job_title($j) {
    return trim((string)($j['designation'] ?? '')) ?: ('Opening ' . (string)($j['req_code'] ?? ''));
}
function careers_job_location($j) {
    foreach (['project_site', 'locations', 'sbu'] as $k) { $v = trim((string)($j[$k] ?? '')); if ($v !== '') return $v; }
    return '';
}

// ---- Intake ----------------------------------------------------------------
// Create a candidate from a public application. Returns [ok, message, candId].
// Runs in a public (unauthenticated) context — no current_user().
function careers_apply($job, $post, $files) {
    careers_migrate();
    // Honeypot: a bot fills the hidden "website" field. Accept silently, create nothing.
    if (trim((string)($post['website'] ?? '')) !== '') return [true, 'thanks', 0];

    $first = trim((string)($post['first_name'] ?? ''));
    $last  = trim((string)($post['last_name'] ?? ''));
    $email = strtolower(trim((string)($post['email'] ?? '')));
    $mobile = trim((string)($post['mobile'] ?? ''));
    if ($first === '') return [false, 'Please enter your name.', 0];
    if ($email === '' && $mobile === '') return [false, 'Please give an email or a mobile number so we can reach you.', 0];
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) return [false, 'That email address does not look right.', 0];

    // Résumé (optional) — parse to supplement experience/skills; keep the file.
    $cvText = ''; $cvName = ''; $auto = [];
    if (!empty($files['cv_file']['tmp_name']) && is_uploaded_file($files['cv_file']['tmp_name'])) {
        $bytes = (string)@file_get_contents($files['cv_file']['tmp_name']);
        if (strlen($bytes) > 12 * 1024 * 1024) return [false, 'Your résumé file is too large (max 12 MB).', 0];
        $cvName = basename((string)$files['cv_file']['name']);
        if (function_exists('connect_cv_extract_text')) $cvText = (string)connect_cv_extract_text($bytes, (string)($files['cv_file']['type'] ?? ''), $cvName);
        if ($cvText !== '' && function_exists('recruit_cv_autofill')) $auto = recruit_cv_autofill($cvText);
    }

    $exp = (string)($post['experience_years'] ?? '');
    if ($exp === '' && !empty($auto['experience_years'])) $exp = (string)$auto['experience_years'];
    $remarks = trim((string)($post['message'] ?? ''));
    if ($remarks === '' && !empty($auto['remarks'])) $remarks = (string)$auto['remarks'];

    // Light de-dupe: an identical applicant already sitting on THIS requisition is
    // not created twice (a double-submit / re-apply). Different opening ⇒ new row.
    try {
        $exists = null;
        if ($email !== '')  $exists = ops_one("SELECT id FROM candidates WHERE requisition_id=? AND LOWER(email)=? LIMIT 1", [(int)$job['id'], $email]);
        if (!$exists && $mobile !== '') { $mn = preg_replace('/\D+/', '', $mobile);
            if ($mn !== '') $exists = ops_one("SELECT id FROM candidates WHERE requisition_id=? AND REPLACE(REPLACE(mobile,' ',''),'-','')=? LIMIT 1", [(int)$job['id'], $mn]); }
        if ($exists) return [true, 'thanks', (int)$exists['id']];
    } catch (Throwable $e) { /* de-dupe is best-effort */ }

    $pdo = db();
    $code = function_exists('recruit_cand_code') ? recruit_cand_code($job)
          : (function_exists('ops_next_code') ? ops_next_code('candidates', 'cand_code', 'CV') : ('CV-' . date('ymdHis')));
    $cols = ['cand_code','first_name','middle_name','last_name','designation','source','email','mobile',
             'experience_years','cv_received_date','remarks','requisition_id','recruiter_id','department','sbu',
             'stage','created_by','created_at'];
    $vals = [$code, $first, trim((string)($post['middle_name'] ?? '')), $last,
             (string)($job['designation'] ?? ''), 'CAREERS', $email, $mobile,
             ($exp === '' ? 0 : (float)$exp), date('Y-m-d'), $remarks, (int)$job['id'],
             ($job['recruiter_id'] ?? null) ?: null, (string)($job['department'] ?? ''), (string)($job['sbu'] ?? ''),
             'RECEIVED', 'Careers site', date('c')];
    $ph = implode(',', array_fill(0, count($cols), '?'));
    try {
        $pdo->prepare("INSERT INTO candidates (" . implode(',', $cols) . ") VALUES ($ph)")->execute($vals);
    } catch (Throwable $e) { return [false, 'We could not record your application just now. Please try again shortly.', 0]; }
    $id = (int)$pdo->lastInsertId();

    if ($cvText !== '') {
        $kw = function_exists('cv_extract_keywords') ? cv_extract_keywords($cvText) : '';
        try { $pdo->prepare("UPDATE candidates SET cv_text=?, cv_keywords=?, cv_file_name=?, cv_analyzed_at=? WHERE id=?")->execute([$cvText, $kw, $cvName, date('c'), $id]); } catch (Throwable $e) {}
    }
    try {
        $pdo->prepare("INSERT INTO candidate_events (candidate_id,from_stage,to_stage,remark,actor,created_at) VALUES (?,?,?,?,?,?)")
            ->execute([$id, '', 'RECEIVED', 'Applied via careers site', 'Careers site', date('c')]);
    } catch (Throwable $e) {}

    // Keep the résumé on file (base64 in candidate_docs), reusing the DMS helper.
    if ($cvName !== '' && function_exists('doc_upload')) {
        try { doc_upload($id, ['doc_type' => 'Resume/CV'], $files['cv_file']); } catch (Throwable $e) {}
    }
    careers_notify_recruiter($job, $first . ' ' . $last, $code);
    return [true, 'thanks', $id];
}

function careers_notify_recruiter($job, $applicant, $code) {
    if (!function_exists('ops_mail')) return;
    $to = '';
    $rid = (int)($job['recruiter_id'] ?? 0);
    if ($rid > 0) { try { $to = (string)ops_val("SELECT email FROM users WHERE id=? AND is_active=1 AND email<>''", [$rid]); } catch (Throwable $e) {} }
    if ($to === '') return;
    $title = careers_job_title($job);
    try {
        ops_mail($to, 'New application — ' . $title,
            '<p>A new candidate applied through the careers site:</p>'
            . '<p><b>' . e($applicant) . '</b> (' . e($code) . ') for <b>' . e($title) . '</b>.</p>'
            . '<p>Open the candidate in the hiring pipeline to review.</p>', '', 'careers');
    } catch (Throwable $e) {}
}

// ============================================================================
//  Public site (pre-login). careers_route() always exits.
// ============================================================================
function careers_route($route, $method) {
    if (!careers_enabled()) {
        if (function_exists('current_user') && !current_user()) { redirect('/login'); }
        http_response_code(404); echo 'Careers page is not enabled.'; exit;
    }
    $jobId = (int)($_GET['job'] ?? 0);
    if ($jobId > 0) {
        $job = careers_job($jobId);
        if (!$job) { careers_shell('Opening not found', '<div class="cx-card"><p>This opening is no longer available.</p><p><a class="cx-btn" href="/careers">← See all openings</a></p></div>'); exit; }
        if ($method === 'POST') {
            [$ok, $msg, $cid] = careers_apply($job, $_POST, $_FILES);
            if ($ok) { careers_view_thanks($job); exit; }
            careers_view_job($job, $msg, $_POST); exit;
        }
        careers_view_job($job, '', []); exit;
    }
    careers_view_list(); exit;
}

// ---- Standalone shell (never the staff layout) -----------------------------
function careers_shell($title, $bodyHtml) {
    $app = function_exists('app_name') ? app_name() : 'Careers';
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>' . e($title) . ' · ' . e($app) . '</title><style>'
       . ':root{--brand:#1e40af;--brand-d:#152e7a;--ink:#0f172a;--muted:#64748b;--line:#e5e9f0;--bg:#f4f6fb;--card:#fff;--ok:#0f7d5a;--okbg:#e7f5ef;--bad:#b42318}'
       . '@media(prefers-color-scheme:dark){:root{--ink:#e8eef7;--muted:#93a2b8;--line:#233048;--bg:#0b1220;--card:#111a2c;--okbg:#0f2a22}}'
       . '*{box-sizing:border-box}body{margin:0;font:16px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:var(--bg);color:var(--ink)}'
       . '.cx-top{background:linear-gradient(135deg,var(--brand),var(--brand-d));color:#fff;padding:18px 20px}'
       . '.cx-top .in{max-width:820px;margin:0 auto;display:flex;align-items:center;gap:12px}'
       . '.cx-top b{font-size:19px;letter-spacing:-.01em}.cx-top span{opacity:.85;font-size:13px}'
       . '.cx-wrap{max-width:820px;margin:0 auto;padding:22px 18px 64px}'
       . '.cx-card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:20px;margin-bottom:14px;box-shadow:0 1px 2px rgba(16,24,40,.04)}'
       . 'h1{font-size:26px;letter-spacing:-.02em;margin:2px 0 6px}h2{font-size:18px;margin:0 0 4px}'
       . '.cx-muted{color:var(--muted)}.cx-meta{font-size:13.5px;color:var(--muted);margin-top:2px}'
       . '.cx-job{display:flex;justify-content:space-between;gap:14px;align-items:center;border:1px solid var(--line);border-radius:13px;padding:15px 16px;margin-bottom:10px;text-decoration:none;color:inherit;background:var(--card);transition:.12s}'
       . '.cx-job:hover{border-color:var(--brand);transform:translateY(-1px)}'
       . '.cx-pill{display:inline-block;font-size:11.5px;padding:2px 9px;border-radius:999px;background:rgba(30,64,175,.08);color:var(--brand);margin-right:6px}'
       . 'label{display:block;font-size:13px;font-weight:600;margin:12px 0 4px}'
       . 'input,textarea,select{width:100%;padding:12px;border:1px solid var(--line);border-radius:11px;font-size:16px;background:var(--card);color:inherit}'
       . 'textarea{min-height:92px;resize:vertical}'
       . '.cx-btn{display:inline-block;background:var(--brand);color:#fff;border:0;border-radius:11px;padding:13px 20px;font-size:15px;font-weight:600;cursor:pointer;text-decoration:none}'
       . '.cx-btn.block{width:100%;text-align:center}.cx-btn.ghost{background:transparent;color:var(--brand);border:1px solid var(--line)}'
       . '.cx-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}@media(max-width:560px){.cx-row{grid-template-columns:1fr}}'
       . '.cx-msg{padding:11px 14px;border-radius:11px;margin-bottom:12px;font-size:14px}.cx-msg.err{background:#fdeaea;color:var(--bad)}.cx-msg.ok{background:var(--okbg);color:var(--ok)}'
       . '.cx-hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}'
       . '.cx-foot{max-width:820px;margin:0 auto;padding:8px 18px 40px;color:var(--muted);font-size:12.5px;text-align:center}'
       . '</style></head><body>'
       . '<div class="cx-top"><div class="in"><b>' . e($app) . '</b><span>· Careers</span></div></div>'
       . '<div class="cx-wrap">' . $bodyHtml . '</div>'
       . '<div class="cx-foot">Powered by ' . e($app) . '</div></body></html>';
}

function careers_view_list() {
    $jobs = careers_open_jobs();
    $intro = careers_intro();
    $b = '<h1>Open positions</h1>';
    $b .= '<p class="cx-muted">' . ($intro !== '' ? nl2br(e($intro)) : 'Explore current openings and apply in a couple of minutes.') . '</p>';
    if (!$jobs) {
        $b .= '<div class="cx-card"><h2>No openings right now</h2><p class="cx-muted">Please check back soon — new roles are posted here as they open.</p></div>';
    } else {
        $b .= '<div style="margin-top:14px">';
        foreach ($jobs as $j) {
            $loc = careers_job_location($j);
            $b .= '<a class="cx-job" href="/careers?job=' . (int)$j['id'] . '">'
                . '<div><h2>' . e(careers_job_title($j)) . '</h2>'
                . '<div class="cx-meta">'
                . ($j['department'] ? '<span class="cx-pill">' . e($j['department']) . '</span>' : '')
                . ($j['grade'] ? '<span class="cx-pill">' . e($j['grade']) . '</span>' : '')
                . ($loc ? '📍 ' . e($loc) : '') . '</div></div>'
                . '<div class="cx-btn ghost">View &amp; apply →</div></a>';
        }
        $b .= '</div>';
    }
    careers_shell('Careers', $b);
}

function careers_view_job($job, $err, $post) {
    $loc = careers_job_location($job);
    $desc = trim((string)($job['careers_summary'] ?? ''));
    $v = fn($k) => e((string)($post[$k] ?? ''));
    $b = '<p><a class="cx-muted" href="/careers" style="text-decoration:none">← All openings</a></p>';
    $b .= '<div class="cx-card"><h1>' . e(careers_job_title($job)) . '</h1><div class="cx-meta">'
        . ($job['department'] ? '<span class="cx-pill">' . e($job['department']) . '</span>' : '')
        . ($job['grade'] ? '<span class="cx-pill">' . e($job['grade']) . '</span>' : '')
        . ($loc ? '📍 ' . e($loc) : '') . '</div>';
    if ($desc !== '') $b .= '<div style="margin-top:12px">' . nl2br(e($desc)) . '</div>';
    $b .= '</div>';

    $b .= '<div class="cx-card"><h2>Apply for this role</h2><p class="cx-muted">Tell us a little about you — it takes about two minutes.</p>';
    if ($err !== '') $b .= '<div class="cx-msg err">' . e($err) . '</div>';
    $b .= '<form method="post" action="/careers?job=' . (int)$job['id'] . '" enctype="multipart/form-data">'
        . '<div class="cx-hp"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>'
        . '<div class="cx-row"><div><label>First name *</label><input name="first_name" value="' . $v('first_name') . '" required></div>'
        . '<div><label>Last name</label><input name="last_name" value="' . $v('last_name') . '"></div></div>'
        . '<div class="cx-row"><div><label>Email</label><input type="email" name="email" value="' . $v('email') . '"></div>'
        . '<div><label>Mobile</label><input name="mobile" value="' . $v('mobile') . '"></div></div>'
        . '<label>Total experience (years)</label><input type="number" step="0.5" min="0" name="experience_years" value="' . $v('experience_years') . '">'
        . '<label>Résumé / CV (PDF or Word)</label><input type="file" name="cv_file" accept=".pdf,.doc,.docx,.txt">'
        . '<label>Anything you would like to add</label><textarea name="message" placeholder="A short note (optional)">' . $v('message') . '</textarea>'
        . '<p class="cx-muted" style="font-size:12.5px;margin:12px 0 4px">Provide an email or a mobile number so we can contact you.</p>'
        . '<button class="cx-btn block" type="submit">Submit application</button>'
        . '</form></div>';
    careers_shell('Apply — ' . careers_job_title($job), $b);
}

function careers_view_thanks($job) {
    $b = '<div class="cx-card" style="text-align:center;padding:40px 22px">'
       . '<div style="font-size:44px">✅</div>'
       . '<h1>Application received</h1>'
       . '<p class="cx-muted">Thank you for applying for <b>' . e(careers_job_title($job)) . '</b>. '
       . 'Our team will review your details and get in touch if there is a fit.</p>'
       . '<p style="margin-top:16px"><a class="cx-btn ghost" href="/careers">← See other openings</a></p></div>';
    careers_shell('Thank you', $b);
}

// ============================================================================
//  Admin — choose which openings are advertised + page settings.
// ============================================================================
function ops_careers_admin($route, $method) {
    ops_require(is_admin_level(), 'Only an administrator can manage the careers page.');
    careers_migrate();
    if ($method === 'POST') {
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'settings' && function_exists('setting_set')) {
            setting_set('careers_enabled', ($_POST['careers_enabled'] ?? '') ? '1' : '0');
            setting_set('careers_intro', substr(trim((string)($_POST['careers_intro'] ?? '')), 0, 2000));
            flash('Careers page settings saved.');
            redirect('/careers-admin'); return true;
        }
        if ($do === 'publish') {
            $id = (int)($_POST['req_id'] ?? 0);
            $on = ($_POST['publish'] ?? '') ? 1 : 0;
            db()->prepare("UPDATE requisitions SET careers_published=?, careers_summary=? WHERE id=?")
                ->execute([$on, substr(trim((string)($_POST['careers_summary'] ?? '')), 0, 4000), $id]);
            flash($on ? 'Opening published to the careers page.' : 'Opening removed from the careers page.');
            redirect('/careers-admin'); return true;
        }
        if ($do === 'jd_settings' && function_exists('jd_config_save')) {
            jd_config_save($_POST);
            flash('Posting defaults saved.');
            redirect('/careers-admin'); return true;
        }
    }
    $reqs = [];
    try {
        $reqs = ops_all("SELECT * FROM requisitions
                         WHERE COALESCE(status,'') NOT IN ('CLOSED','FILLED','CANCELLED','on_hold','REJECTED')
                         ORDER BY careers_published DESC, id DESC") ?: [];
    } catch (Throwable $e) { $reqs = []; }
    view('ops/careers_admin', [
        'enabled' => careers_enabled(),
        'intro'   => careers_intro(),
        'reqs'    => $reqs,
        'live'    => count(careers_open_jobs()),
        'jdcfg'   => function_exists('jd_config') ? jd_config() : null,
        'ai_on'   => function_exists('ai_enabled') && ai_enabled(),
    ]);
    return true;
}
