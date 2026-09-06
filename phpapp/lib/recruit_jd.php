<?php
// ============================================================================
//  EXAACT Recruitment — Auto job-description & public posting generator.
//  Additive and non-destructive.
//
//  One click turns a requisition's STRUCTURED details (role, department, grade,
//  location, discipline, skills, qualification, experience, responsibilities)
//  into a polished job description / public careers posting.
//
//  • Works with NO AI key — a deterministic TEMPLATE assembles a solid posting
//    from the fields and the company's configurable boilerplate.
//  • When an AI provider is configured, it enriches the same facts into fluent
//    prose (never inventing salary or requirements). On any AI hiccup it falls
//    back to the template — the feature is always available.
//  • Highly configurable: an admin edits the intro, "what we offer", the
//    how-to-apply footer and the tone once; every posting inherits them.
//  The output is always shown for human review before it is published.
// ============================================================================

function jd_migrate() {
    static $done = false; if ($done) return; $done = true;
    if (function_exists('ensure_column')) {
        try { ensure_column('requisitions', 'responsibilities', 'TEXT'); } catch (Throwable $e) {}
    }
}

// ---- Configurable boilerplate (one settings row) ---------------------------
function jd_config() {
    $raw = function_exists('setting_get') ? (string)setting_get('jd_config', '') : '';
    $d = $raw !== '' ? json_decode($raw, true) : [];
    $d = is_array($d) ? $d : [];
    $company = function_exists('app_name') ? app_name() : 'our company';
    return array_merge([
        'about' => "At {company} we are growing our {department} team and looking for a {role} to join us.",
        'offer' => "A collaborative team, meaningful work, and room to grow.",
        'apply' => "Interested? Apply through this page and our team will be in touch.",
        'tone'  => 'professional',
        '_company' => $company,
    ], $d);
}
function jd_config_save($post) {
    $cfg = [
        'about' => substr(trim((string)($post['about'] ?? '')), 0, 1000),
        'offer' => substr(trim((string)($post['offer'] ?? '')), 0, 1000),
        'apply' => substr(trim((string)($post['apply'] ?? '')), 0, 1000),
        'tone'  => in_array($post['tone'] ?? '', ['professional','friendly','concise'], true) ? $post['tone'] : 'professional',
    ];
    if (function_exists('setting_set')) setting_set('jd_config', json_encode($cfg));
}

// ---- Field gathering -------------------------------------------------------
function jd_fields($req) {
    jd_migrate();
    $g = fn($k) => trim((string)($req[$k] ?? ''));
    $role = $g('designation') ?: ('Opening ' . $g('req_code'));
    $loc = '';
    foreach (['project_site','locations','deploy_location','sbu'] as $k) { if ($g($k) !== '') { $loc = $g($k); break; } }
    return [
        'role'        => $role,
        'department'  => $g('department') ?: $g('discipline'),
        'grade'       => $g('grade'),
        'location'    => $loc,
        'discipline'  => $g('discipline'),
        'skills'      => $g('skills'),
        'qualification' => $g('qualification'),
        'experience_min' => ($req['experience_min'] ?? '') !== '' ? (float)$req['experience_min'] : null,
        'work_model'  => $g('work_model'),
        'responsibilities' => $g('responsibilities'),
        'quantity'    => (int)($req['quantity'] ?? 0),
    ];
}

// Which useful fields are blank (so the UI can nudge the user to fill them).
function jd_missing($f) {
    $want = ['location'=>'location','skills'=>'skills','qualification'=>'qualification','responsibilities'=>'key responsibilities'];
    $miss = [];
    foreach ($want as $k => $label) { if (trim((string)($f[$k] ?? '')) === '') $miss[] = $label; }
    if ($f['experience_min'] === null) $miss[] = 'minimum experience';
    return $miss;
}

// ---- Deterministic template (always available) -----------------------------
function jd_template_text($f, $cfg) {
    $tok = fn($s) => strtr((string)$s, [
        '{company}' => $cfg['_company'], '{role}' => $f['role'] ?: 'this role',
        '{department}' => $f['department'] ?: 'our', '{location}' => $f['location'] ?: '',
    ]);
    $L = [];
    $L[] = $f['role'] . ($f['location'] ? ' — ' . $f['location'] : '');
    $L[] = '';
    $L[] = 'About the role';
    $L[] = trim($tok($cfg['about']));
    $L[] = '';
    $L[] = 'Key responsibilities';
    $resp = trim((string)$f['responsibilities']);
    if ($resp !== '') {
        foreach (preg_split('/\r\n|\r|\n|•|;/', $resp) as $r) { $r = trim($r, " \t-–•"); if ($r !== '') $L[] = '- ' . $r; }
    } else {
        $L[] = '- Own and deliver the day-to-day work of the ' . ($f['role'] ?: 'role') . ($f['department'] ? ' within ' . $f['department'] : '') . '.';
        $L[] = '- Work closely with the team to meet quality, timeline and safety expectations.';
        $L[] = '- Keep clear records and communicate progress to stakeholders.';
    }
    $L[] = '';
    $L[] = "What you'll bring";
    if ($f['experience_min'] !== null && $f['experience_min'] > 0) $L[] = '- ' . rtrim(rtrim(number_format($f['experience_min'], 1), '0'), '.') . '+ years of relevant experience.';
    if ($f['qualification'])  $L[] = '- ' . $f['qualification'] . '.';
    if ($f['skills'])         $L[] = '- Skills: ' . $f['skills'] . '.';
    if ($f['discipline'] && $f['discipline'] !== $f['department']) $L[] = '- Background in ' . $f['discipline'] . '.';
    if ($f['experience_min'] === null && !$f['qualification'] && !$f['skills']) $L[] = '- A genuine interest in the role and a willingness to learn.';
    $L[] = '';
    $L[] = 'What we offer';
    $L[] = trim($tok($cfg['offer']));
    $L[] = '';
    $L[] = 'How to apply';
    $L[] = trim($tok($cfg['apply']));
    return trim(implode("\n", $L));
}

// ---- AI enrichment (optional, graceful) ------------------------------------
function jd_ai_text($f, $cfg) {
    if (!function_exists('ai_enabled') || !ai_enabled() || !function_exists('ai_chat')) return null;
    $toneLine = ['professional' => 'professional and warm', 'friendly' => 'friendly and conversational', 'concise' => 'concise and direct'][$cfg['tone']] ?? 'professional and warm';
    $sys = "You are an expert recruitment copywriter. Write an inclusive, engaging job posting from the STRUCTURED facts provided. "
         . "Tone: {$toneLine}. Rules: use ONLY the facts given — never invent salary, benefits, requirements, company names or locations that are not provided. "
         . "If the company boilerplate is provided, weave it in. Output PLAIN TEXT with these headings on their own lines, each followed by content: "
         . "'About the role', 'Key responsibilities' (use '- ' bullets), \"What you'll bring\" (use '- ' bullets), 'What we offer', 'How to apply'. "
         . "Start with a single line: the role title and location. Keep it under 320 words.";
    $payload = ['facts' => $f, 'company' => $cfg['_company'],
                'boilerplate' => ['about' => $cfg['about'], 'offer' => $cfg['offer'], 'apply' => $cfg['apply']]];
    [$txt, $err] = ai_chat($sys, json_encode($payload), 900);
    $txt = trim((string)$txt);
    return $txt !== '' ? $txt : null;
}

// Main entry: returns ['text','source'=>'ai'|'template','missing'=>[]].
function recruit_jd_generate($req, $opts = []) {
    $cfg = jd_config();
    $f = jd_fields($req);
    $missing = jd_missing($f);
    $text = null; $source = 'template';
    if (empty($opts['template_only'])) {
        $ai = jd_ai_text($f, $cfg);
        if ($ai !== null) { $text = $ai; $source = 'ai'; }
    }
    if ($text === null) $text = jd_template_text($f, $cfg);
    return ['text' => $text, 'source' => $source, 'missing' => $missing];
}

// ---- AJAX endpoint used by the careers admin & requisition screen ----------
function ops_jd_generate($route, $method) {
    header('Content-Type: application/json');
    if (!(function_exists('is_coordinator_level') && is_coordinator_level())) { echo json_encode(['ok' => false, 'error' => 'Not allowed.']); return true; }
    if (function_exists('csrf_ok') && !csrf_ok($_POST['_csrf'] ?? '')) { echo json_encode(['ok' => false, 'error' => 'Session expired — reload the page.']); return true; }
    $id = (int)($_POST['req_id'] ?? 0);
    $req = $id ? ops_one("SELECT * FROM requisitions WHERE id=?", [$id]) : null;
    if (!$req) { echo json_encode(['ok' => false, 'error' => 'Requisition not found.']); return true; }
    $out = recruit_jd_generate($req, ['template_only' => !empty($_POST['template_only'])]);
    echo json_encode(['ok' => true] + $out);
    return true;
}
