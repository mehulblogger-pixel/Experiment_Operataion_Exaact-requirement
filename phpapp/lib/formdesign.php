<?php
// ============================================================================
//  FORM DESIGNER — per-company overrides for the built-in forms.
//
//  An additive OVERLAY: each company can rename a field, reorder fields, hide an
//  optional field, or make a field required/optional — WITHOUT the form's code
//  changing. Everything here is defensive: when a form has no overrides, every
//  helper returns the code's own default, so a form renders exactly as before.
//  It never changes how data is SAVED (only which built-in fields show, their
//  labels, order and required-ness) — so it can never corrupt a record. To add a
//  field of a new TYPE, use Custom fields (which already stores its own type).
// ============================================================================

function fd_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS form_field_layout (
            id $pk,
            form_key   VARCHAR(40)  DEFAULT '',
            field_key  VARCHAR(60)  DEFAULT '',
            label      VARCHAR(160) DEFAULT '',
            sort_order INT          DEFAULT 0,
            hidden     INT          DEFAULT 0,
            req        VARCHAR(8)   DEFAULT '',   -- '', 'yes' or 'no' (tri-state override)
            updated_at VARCHAR(30)  DEFAULT '')");
    } catch (Throwable $e) { /* never block the boot chain */ }
}

// Load every override for a form as [field_key => row]. Cached per request; pass
// $fresh=true (done after a save) to reload from the database.
function fd_overrides($form, $fresh = false) {
    static $cache = [];
    $form = (string) $form;
    if ($fresh) unset($cache[$form]);
    if (!array_key_exists($form, $cache)) {
        fd_migrate();
        $out = [];
        try {
            foreach (ops_all("SELECT * FROM form_field_layout WHERE form_key=?", [$form]) as $r) {
                $out[(string) $r['field_key']] = $r;
            }
        } catch (Throwable $e) {}
        $cache[$form] = $out;
    }
    return $cache[$form];
}

// The label to show for a field — the company's override, else the code default.
function fd_label($form, $key, $default) {
    $ov = fd_overrides($form);
    $lbl = (string) ($ov[$key]['label'] ?? '');
    return $lbl !== '' ? $lbl : $default;
}

// Is this (optional) field hidden by the company? Never hide a locked field.
function fd_hidden($form, $key) {
    $ov = fd_overrides($form);
    return !empty($ov[$key]['hidden']);
}

// Required? The override wins ('yes'/'no'); otherwise the code's own default.
function fd_required($form, $key, $default = false) {
    $ov = fd_overrides($form);
    $r = (string) ($ov[$key]['req'] ?? '');
    if ($r === 'yes') return true;
    if ($r === 'no')  return false;
    return (bool) $default;
}

// Reorder a list of field keys by the company's saved order. Keys with no saved
// order keep their code order, after the ordered ones. Stable and total.
function fd_order($form, array $keys) {
    $ov = fd_overrides($form);
    $withPos = [];
    $i = 0;
    foreach ($keys as $k) {
        $pos = isset($ov[$k]) ? (int) $ov[$k]['sort_order'] : 100000;   // unset → keep after ordered ones
        $withPos[] = [$k, $pos, $i++];
    }
    usort($withPos, fn($a, $b) => $a[1] === $b[1] ? $a[2] <=> $b[2] : $a[1] <=> $b[1]);
    return array_map(fn($p) => $p[0], $withPos);
}

// Reset the tiny per-request caches (used right after a save so the editor
// re-reads fresh values on the redirect target).
function fd_flush() { /* caches are request-scoped statics; nothing persistent to clear */ }

// ---------------------------------------------------------------------------
//  The registry of DESIGNABLE forms and their built-in fields. Populated for the
//  recruitment forms (see fd_forms()). Each field: key, default label, a plain
//  type word (for display only), whether it is 'locked' (cannot be hidden, e.g.
//  a field the save truly requires), and its section.
// ---------------------------------------------------------------------------
function fd_forms() {
    $forms = [];
    if (function_exists('fd_forms_recruitment')) $forms += fd_forms_recruitment();
    // Company-built custom forms are designed on their own screen already, so we
    // only surface the built-in forms here.
    return $forms;
}

// The two recruitment forms and their built-in fields. Each field:
//   'label' default label, 'type' (display only), 'section', 'locked' (a field
//   the form's logic depends on — renameable/reorderable but never hideable).
function fd_forms_recruitment() {
    // Only offer these where People & hiring is licensed.
    if (function_exists('licence_enabled') && !licence_enabled('hr')) return [];
    $F = fn($label, $type, $section, $locked = false) => ['label' => $label, 'type' => $type, 'section' => $section, 'locked' => $locked];
    return [
        'requisition' => [
            'label' => function_exists('TP') ? TP('requisition') : 'Requirement', 'icon' => '📋',
            'help'  => 'The new-requirement form',
            'fields' => [
                'req_type'        => $F('Type', 'dropdown', 'Client & position', true),
                'client_id'       => $F('Client', 'dropdown', 'Client & position', true),
                'contact_name'    => $F('Client contact', 'text', 'Client & position'),
                'contact_email'   => $F('Contact email', 'email', 'Client & position'),
                'contact_phone'   => $F('Contact phone', 'text', 'Client & position'),
                'contract_ref'    => $F('Contract number', 'text', 'Client & position'),
                'po_ref'          => $F('PO reference', 'text', 'Client & position'),
                'quotation_ref'   => $F('Quotation ref', 'text', 'Client & position'),
                'office_id'       => $F('Office', 'dropdown', 'Client & position', true),
                'recruiter_id'    => $F('Responsible 1 — Recruiter', 'dropdown', 'Client & position'),
                'manager_id'      => $F('Responsible 2 — Reporting manager', 'dropdown', 'Client & position'),
                'department'      => $F('Department', 'dropdown', 'Client & position'),
                'designation'     => $F('Designation / position', 'dropdown', 'Client & position'),
                'quantity'        => $F('How many?', 'number', 'Client & position', true),
                'project_site'    => $F('Project / site', 'text', 'Client & position'),
                'locations'       => $F('Locations required', 'textarea', 'Client & position'),
                'discipline'      => $F('Discipline', 'text', 'Client & position'),
                'category'        => $F('Category / sub-category', 'text', 'Client & position'),
                'qualification'   => $F('Qualification', 'text', 'Client & position'),
                'skills'          => $F('Skills / certifications', 'text', 'Client & position'),
                'experience_min'  => $F('Experience (min years)', 'number', 'Client & position'),
                'relevant_experience' => $F('Relevant experience', 'text', 'Client & position'),
                'responsibilities'=> $F('Key responsibilities', 'textarea', 'Client & position'),
                'work_model'      => $F('Work model', 'dropdown', 'Deployment'),
                'deploy_location' => $F('Deployment location', 'text', 'Deployment'),
                'start_date'      => $F('Start date', 'date', 'Deployment'),
                'end_date'        => $F('End date', 'date', 'Deployment'),
                'duration_months' => $F('Duration (months)', 'number', 'Deployment'),
                'duty_hours'      => $F('Duty hours', 'text', 'Deployment'),
                'shift'           => $F('Shift', 'dropdown', 'Deployment'),
                'other_allowances'=> $F('Other allowances', 'text', 'Deployment'),
                'sourcing_model'  => $F('Sourcing model', 'dropdown', 'Commercial', true),
                'cost_wage'       => $F('Base wage / salary', 'number', 'Commercial', true),
                'cost_statutory_pct' => $F('Statutory & benefits — %', 'number', 'Commercial', true),
                'cost_agency_pct' => $F('Agency service fee / markup — %', 'number', 'Commercial', true),
                'cost_reimburse'  => $F('Reimbursables', 'number', 'Commercial'),
                'cost_oneoff'     => $F('One-time / person', 'number', 'Commercial'),
                'billing_rate'    => $F('Billing rate', 'number', 'Commercial', true),
                'rate_basis'      => $F('Rate basis', 'dropdown', 'Commercial', true),
                'budgeted_cost'   => $F('Est. cost / person / month', 'number', 'Commercial'),
                'target_margin'   => $F('Target margin (%)', 'number', 'Commercial'),
                'negotiation_floor'=> $F('Negotiation floor', 'number', 'Commercial'),
                'approval_ref'    => $F('Approval reference', 'text', 'Approval & status'),
                'approved_by'     => $F('Approved by', 'text', 'Approval & status'),
                'approval_date'   => $F('Approval date', 'date', 'Approval & status'),
                'status'          => $F('Status', 'dropdown', 'Approval & status', true),
                'notes'           => $F('Notes', 'text', 'Approval & status'),
            ],
        ],
        'candidate' => [
            'label' => function_exists('TP') ? TP('candidate') : 'Candidate', 'icon' => '🧑‍💼',
            'help'  => 'The add-candidate form',
            'fields' => [
                'requisition_id'  => $F('Against requisition', 'dropdown', 'Candidate', true),
                'first_name'      => $F('First name', 'text', 'Candidate', true),
                'middle_name'     => $F('Middle name', 'text', 'Candidate'),
                'last_name'       => $F('Last name', 'text', 'Candidate'),
                'client_id'       => $F('Client', 'dropdown', 'Candidate'),
                'proposed_site'   => $F('Proposed site / location', 'text', 'Candidate'),
                'trade_id'        => $F('Trade / discipline', 'dropdown', 'Candidate'),
                'skill_id'        => $F('Sub-category (skill)', 'dropdown', 'Candidate'),
                'designation'     => $F('Designation offered', 'dropdown', 'Candidate'),
                'source'          => $F('Source', 'dropdown', 'Candidate'),
                'recruiter_id'    => $F('Recruiter (Responsible 1)', 'dropdown', 'Candidate'),
                'department'      => $F('Department', 'dropdown', 'Candidate'),
                'agency'          => $F('Agency (sub-con / HR agency)', 'dropdown', 'Candidate'),
                'experience_years'=> $F('Experience (years)', 'number', 'Candidate'),
                'email'           => $F('Email', 'text', 'Candidate'),
                'mobile'          => $F('Mobile', 'text', 'Candidate'),
                'expected_rate'   => $F('Expected rate', 'number', 'Candidate'),
                'rate_type'       => $F('Rate type', 'dropdown', 'Candidate'),
                'cv_received_date'=> $F('CV received date', 'date', 'Candidate'),
                'cv_link'         => $F('CV link', 'text', 'Candidate'),
                'remarks'         => $F('Remarks', 'text', 'Candidate'),
            ],
        ],
    ];
}

// Emit a small, SAFE overlay script for a built-in form: it renames labels,
// hides fields (kept in the DOM so their value is never blanked on save), and
// reorders fields WITHIN their own container — all display-only, because the
// save reads $_POST by field name, never by position. No-op when the company
// has set no overrides, so the form renders exactly as coded by default.
function fd_overlay_html($form) {
    if (!function_exists('fd_overrides')) return '';
    $ov = fd_overrides($form);
    if (!$ov) return '';
    $map = [];
    foreach ($ov as $key => $r) {
        $map[$key] = [
            'label' => (string) ($r['label'] ?? ''),
            'hidden' => !empty($r['hidden']) ? 1 : 0,
            'req' => (string) ($r['req'] ?? ''),
            'order' => (int) ($r['sort_order'] ?? 0),
        ];
    }
    $json = json_encode($map, JSON_UNESCAPED_UNICODE);
    // The applier is display-only and defensive: it only touches fields it finds,
    // renames labels, hides fields (kept in the DOM so their value still submits
    // and is never blanked), toggles required, and reorders the managed fields
    // within their own container while leaving every other field in place.
    $js = <<<JS
<script>
(function(){
  var O = $json;
  function fieldEl(name){
    return document.querySelector('[name="'+name+'"]') || document.querySelector('[name="'+name+'[]"]');
  }
  function ffOf(el){ return el.closest('.ff') || el.closest('.form-field') || el.parentElement; }
  var byParent = new Map();
  Object.keys(O).forEach(function(name){
    var el = fieldEl(name); if(!el) return;
    var ff = ffOf(el); var o = O[name];
    if(o.label){ var lab = ff && ff.querySelector('label'); if(lab){ lab.childNodes.length ? (lab.firstChild.nodeType===3 ? lab.firstChild.nodeValue=o.label : lab.textContent=o.label) : lab.textContent=o.label; } }
    if(o.hidden){ el.removeAttribute('required'); if(ff) ff.style.display='none'; }
    else if(o.req==='yes'){ el.setAttribute('required','required'); }
    else if(o.req==='no'){ el.removeAttribute('required'); }
    if(ff && ff.parentElement){
      var p = ff.parentElement;
      if(!byParent.has(p)) byParent.set(p, []);
      byParent.get(p).push({ff:ff, order:o.order});
    }
  });
  // Reorder the managed fields into their saved order, placed where the managed
  // block currently starts, leaving every non-managed field where it is.
  byParent.forEach(function(list, parent){
    var ordered = list.slice().sort(function(a,b){ return a.order - b.order; }).map(function(x){ return x.ff; });
    var firstIdx = Infinity, firstEl = null;
    ordered.forEach(function(ff){
      var idx = Array.prototype.indexOf.call(parent.children, ff);
      if(idx > -1 && idx < firstIdx){ firstIdx = idx; firstEl = ff; }
    });
    var anchor = firstEl ? firstEl.previousElementSibling : null;
    ordered.forEach(function(ff){
      if(anchor && anchor.parentNode === parent){ parent.insertBefore(ff, anchor.nextSibling); }
      else { parent.insertBefore(ff, parent.firstChild); }
      anchor = ff;
    });
  });
})();
</script>
JS;
    return $js;
}

// Save posted overrides for one form. $rows is a list of
// [field_key, label, hidden(0/1), req('','yes','no'), sort_order].
function fd_save($form, array $rows) {
    fd_migrate();
    $pdo = db();
    try {
        $pdo->prepare("DELETE FROM form_field_layout WHERE form_key=?")->execute([(string) $form]);
        $ins = $pdo->prepare("INSERT INTO form_field_layout (form_key,field_key,label,sort_order,hidden,req,updated_at)
                              VALUES (?,?,?,?,?,?,?)");
        foreach ($rows as $r) {
            $ins->execute([
                (string) $form,
                (string) ($r['field_key'] ?? ''),
                substr((string) ($r['label'] ?? ''), 0, 160),
                (int) ($r['sort_order'] ?? 0),
                !empty($r['hidden']) ? 1 : 0,
                in_array($r['req'] ?? '', ['yes', 'no'], true) ? $r['req'] : '',
                date('c'),
            ]);
        }
        fd_overrides($form, true);   // refresh the per-request cache so later reads see the new values
        return true;
    } catch (Throwable $e) { return false; }
}

// Who may design forms — an admin of this company (settings.manage) or master.
function fd_can() {
    return (function_exists('can') && can('settings.manage')) || (function_exists('is_master') && is_master());
}

// ---------------------------------------------------------------------------
//  Admin screen — Form Designer (/form-designer, /form-designer-save)
// ---------------------------------------------------------------------------
function ops_form_designer($route, $method) {
    ops_require(fd_can(), 'Only an administrator can design forms.');
    $forms = fd_forms();

    if ($route === 'form-designer-save' && $method === 'POST') {
        $form = (string) ($_POST['form'] ?? '');
        if (!isset($forms[$form])) { flash('Unknown form.', 'error'); redirect('/form-designer'); }
        $fields = $forms[$form]['fields'] ?? [];
        $rows = [];
        $order = 0;
        // The posted order is the sequence the fields arrived in (the up/down
        // buttons reorder the hidden inputs), so we number them as they come.
        foreach ((array) ($_POST['field_key'] ?? []) as $idx => $key) {
            $key = (string) $key;
            if (!isset($fields[$key])) continue;
            $locked = !empty($fields[$key]['locked']);
            $rows[] = [
                'field_key'  => $key,
                'label'      => trim((string) ($_POST['label'][$idx] ?? '')),
                'sort_order' => $order++,
                'hidden'     => (!$locked && !empty($_POST['hidden'][$key])) ? 1 : 0,
                'req'        => (string) ($_POST['req'][$key] ?? ''),
            ];
        }
        fd_save($form, $rows);
        flash('Form saved. Your changes show on the ' . ($forms[$form]['label'] ?? $form) . ' form now.');
        redirect('/form-designer?form=' . urlencode($form));
    }

    $sel = (string) ($_GET['form'] ?? '');
    if ($sel !== '' && !isset($forms[$sel])) $sel = '';
    $ov  = $sel !== '' ? fd_overrides($sel) : [];
    view('ops/form_designer', ['forms' => $forms, 'sel' => $sel, 'ov' => $ov]);
    return true;
}
