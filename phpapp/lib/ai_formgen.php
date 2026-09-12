<?php
// ============================================================================
//  AI Form & Dropdown Builder  (pilot)
//
//  A company pastes or describes its own recruitment process, and the app
//  proposes the EXTRA form fields and dropdown lists that process needs. The
//  admin reviews every suggestion and ticks what to keep; only then is it
//  applied. Nothing goes live automatically.
//
//  Reuse-first: it calls the existing AI client (lib/ai.php → ai_chat) and the
//  existing Form Designer engine (lib/formdesign.php → fd_make_list + the
//  custom_fields store). It adds NO new form/dropdown storage of its own, so
//  everything it creates is editable afterwards under Forms and Masters exactly
//  like anything an admin adds by hand.
// ============================================================================

// The forms the builder may add fields to (key => friendly label), taken from
// the Form Designer's own list — so it can never target a form that isn't real.
function aifg_forms() {
    $out = [];
    if (function_exists('fd_forms')) foreach (fd_forms() as $key => $f) $out[(string) $key] = (string) ($f['label'] ?? $key);
    return $out;
}

// The strict instruction we give the model. We ask for machine-readable JSON in
// one exact shape so the reply can be validated rather than trusted.
function aifg_system_prompt() {
    $forms = aifg_forms();
    $formList = [];
    foreach ($forms as $k => $l) $formList[] = "\"$k\" ($l)";
    $formList = $formList ? implode(', ', $formList) : '"candidate", "requisition"';
    return "You are a recruitment-operations analyst configuring an applicant tracking system. "
        . "From the hiring process the user describes, propose the EXTRA form fields and dropdown lists they will need to run it. "
        . "Return ONLY valid JSON — no prose, no explanation, no markdown code fences — in EXACTLY this shape:\n"
        . '{"dropdowns":[{"name":"Notice Period","values":["Immediate","15 days","30 days","60 days"]}],'
        . '"fields":[{"form":"candidate","label":"Current CTC","type":"number","required":false},'
        . '{"form":"candidate","label":"Notice Period","type":"select","dropdown":"Notice Period","required":false}]}'
        . "\nRules:\n"
        . "- \"form\" MUST be one of: $formList.\n"
        . "- \"type\" MUST be one of: text, textarea, number, date, select.\n"
        . "- For type \"select\", set \"dropdown\" to the EXACT name of one list you listed in \"dropdowns\".\n"
        . "- Do NOT propose fields that obviously already exist (candidate name, email, phone, client, position/role, status, resume).\n"
        . "- Prefer dropdowns over free text wherever the answer is one of a known set.\n"
        . "- Keep it practical: at most 12 fields and 8 dropdowns. Labels in Title Case; option values short and clear.";
}

// Ask the AI for a suggestion. Returns [plan, error]; plan is the validated
// structure from aifg_parse().
function aifg_suggest($flowText) {
    $flowText = trim((string) $flowText);
    if ($flowText === '') return [null, 'Paste or describe your recruitment process first.'];
    if (!function_exists('ai_chat')) return [null, 'The AI helper is not available in this build.'];
    [$text, $err] = ai_chat(aifg_system_prompt(), "Our recruitment / hiring process:\n\n" . $flowText, 1800);
    if ($err) return [null, $err];
    return aifg_parse($text);
}

// Pull the JSON object out of whatever the model returned (fenced or bare).
function aifg_extract_json($text) {
    $text = trim((string) $text);
    if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $text, $m)) return $m[1];
    $s = strpos($text, '{'); $e = strrpos($text, '}');
    return ($s !== false && $e !== false && $e > $s) ? substr($text, $s, $e - $s + 1) : '';
}

// Validate and normalise the model's reply into a clean plan. PURE — no database,
// no network — so it is fully unit-testable. Returns [plan, error] where
// plan = ['dropdowns' => [['name','values'[]]], 'fields' => [['form','label','type','dropdown','required']]].
function aifg_parse($text) {
    $json = aifg_extract_json((string) $text);
    if ($json === '') return [null, 'The AI did not return a usable suggestion. Please try again.'];
    $d = json_decode($json, true);
    if (!is_array($d)) return [null, 'The AI reply could not be read. Please try again.'];

    $validForms = array_keys(aifg_forms());
    if (!$validForms) $validForms = ['candidate', 'requisition'];
    $validTypes = ['text', 'textarea', 'number', 'date', 'select'];

    // Dropdowns first — de-duplicated by name, so a select can reference them.
    $dropdowns = []; $names = [];
    foreach ((array) ($d['dropdowns'] ?? []) as $dd) {
        if (!is_array($dd)) continue;
        $name = trim((string) ($dd['name'] ?? ''));
        if ($name === '' || isset($names[strtolower($name)])) continue;
        $vals = [];
        foreach ((array) ($dd['values'] ?? []) as $v) {
            $v = trim((string) $v);
            if ($v !== '' && !in_array($v, $vals, true)) $vals[] = $v;
        }
        if (!$vals) continue;
        $names[strtolower($name)] = true;
        $dropdowns[] = ['name' => $name, 'values' => array_slice($vals, 0, 40)];
    }

    // Fields — each validated against the real forms and the allowed types; a
    // select must point at one of the dropdowns above or it is dropped.
    $fields = [];
    foreach ((array) ($d['fields'] ?? []) as $f) {
        if (!is_array($f)) continue;
        $label = trim((string) ($f['label'] ?? ''));
        $form  = (string) ($f['form'] ?? '');
        if ($label === '' || !in_array($form, $validForms, true)) continue;
        $type = (string) ($f['type'] ?? 'text');
        if (!in_array($type, $validTypes, true)) $type = 'text';
        $dropdown = trim((string) ($f['dropdown'] ?? ''));
        if ($type === 'select' && ($dropdown === '' || !isset($names[strtolower($dropdown)]))) continue;
        $fields[] = ['form' => $form, 'label' => $label, 'type' => $type, 'dropdown' => $dropdown, 'required' => !empty($f['required'])];
    }

    if (!$dropdowns && !$fields)
        return [null, 'The AI could not turn that into fields or dropdowns. Try describing the steps and the details you collect at each one.'];
    return [['dropdowns' => array_slice($dropdowns, 0, 8), 'fields' => array_slice($fields, 0, 12)], null];
}

// Apply the ADMIN-APPROVED parts of a plan through the existing engines.
//   $picks = ['dropdowns' => [approved names], 'fields' => [approved indexes]]
// Returns ['dropdowns' => N created, 'fields' => N created].
function aifg_apply($plan, $picks) {
    $madeLists = []; $nDD = 0; $nF = 0;
    $wantDD = array_flip(array_map('strval', (array) ($picks['dropdowns'] ?? [])));
    foreach ((array) ($plan['dropdowns'] ?? []) as $dd) {
        $name = (string) ($dd['name'] ?? '');
        if ($name === '' || !isset($wantDD[$name]) || !function_exists('fd_make_list')) continue;
        $tid = fd_make_list('candidate', $name, implode("\n", (array) ($dd['values'] ?? [])));
        if ($tid) { $madeLists[strtolower($name)] = (int) $tid; $nDD++; }
    }
    $wantF = array_flip(array_map('intval', (array) ($picks['fields'] ?? [])));
    foreach ((array) ($plan['fields'] ?? []) as $i => $f) {
        if (!isset($wantF[$i])) continue;
        $type = (string) ($f['type'] ?? 'text');
        $lt = null;
        if ($type === 'select') {
            $lt = $madeLists[strtolower((string) ($f['dropdown'] ?? ''))] ?? null;
            if (!$lt) continue;   // its list was not approved / created → skip the field, don't orphan it
        }
        if (aifg_add_field((string) ($f['form'] ?? ''), (string) ($f['label'] ?? ''), $type, !empty($f['required']), $lt)) $nF++;
    }
    return ['dropdowns' => $nDD, 'fields' => $nF];
}

// Add one custom field to a form programmatically, mirroring fd_field_add()'s
// key-uniqueness rules exactly, so an AI-added field is indistinguishable from a
// hand-added one and never collides with an existing key.
function aifg_add_field($form, $label, $type, $required, $lookupTypeId = null) {
    $form = (string) $form; $label = trim((string) $label);
    if ($form === '' || $label === '') return false;
    $valid = function_exists('fd_field_types') ? fd_field_types() : ['text' => 1, 'textarea' => 1, 'number' => 1, 'date' => 1, 'select' => 1];
    if (!array_key_exists($type, $valid)) $type = 'text';
    if ($type === 'select' && !$lookupTypeId) $type = 'text';   // a dropdown with no list is just text
    $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]+/', '_', $label), '_'));
    if ($base === '') $base = 'field';
    $fkey = $base; $n = 1;
    while ((int) ops_val("SELECT COUNT(*) FROM custom_fields WHERE entity=? AND field_key=?", [$form, $fkey]) > 0) $fkey = $base . '_' . (++$n);
    $sort = (int) ops_val("SELECT COALESCE(MAX(sort_order),0)+1 FROM custom_fields WHERE entity=?", [$form]);
    db()->prepare("INSERT INTO custom_fields (entity,field_key,label,field_type,lookup_type_id,required,sort_order,active,created_at)
                   VALUES (?,?,?,?,?,?,?,1,?)")
        ->execute([$form, $fkey, $label, $type, $lookupTypeId, $required ? 1 : 0, $sort, date('c')]);
    return true;
}

// ---- Screen ---------------------------------------------------------------
function ops_ai_forms($method) {
    ops_require(is_master() || can('settings.manage') || can('mod.masters.view'),
        'Only an administrator can build forms.');
    if (function_exists('licence_enabled') && !licence_enabled('hr')) {
        ops_require(false, 'The form builder is part of People & hiring, which is not enabled on this workspace.');
    }
    $plan = null; $flow = '';
    if ($method === 'POST') {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'suggest') {
            $flow = (string) ($_POST['flow'] ?? '');
            [$plan, $err] = aifg_suggest($flow);
            if ($err) flash($err, 'error');
        } elseif ($action === 'apply') {
            $planIn = json_decode((string) ($_POST['plan'] ?? ''), true);
            if (is_array($planIn)) {
                $picks = [
                    'dropdowns' => (array) ($_POST['dd'] ?? []),
                    'fields'    => array_map('intval', (array) ($_POST['fld'] ?? [])),
                ];
                $res = aifg_apply($planIn, $picks);
                if ($res['fields'] || $res['dropdowns']) {
                    if (function_exists('idems_log')) idems_log('setting', null, 'AI_FORMS_APPLIED',
                        ['field' => 'ai_form_builder', 'new' => $res]);
                    flash('Added ' . $res['fields'] . ' field(s) and ' . $res['dropdowns'] . ' dropdown list(s). '
                        . 'Fine-tune them any time under Forms and Masters.');
                } else {
                    flash('Nothing was selected to add.', 'warning');
                }
                redirect('/ai-forms');
            } else {
                flash('That suggestion could not be applied — please generate it again.', 'error');
            }
        }
    }
    view('ops/ai_forms', [
        'plan'  => $plan,
        'flow'  => $flow,
        'aiOn'  => function_exists('ai_enabled') && ai_enabled(),
        'forms' => aifg_forms(),
    ]);
}
