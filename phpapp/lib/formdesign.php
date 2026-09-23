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
    //  WHERE an added field goes. Until now every field an admin added landed
    //  in a "More details" block at the very bottom of the form, in creation
    //  order, with nothing on screen to say so — the screen asked for a name,
    //  a type and "required", and never asked where it belonged. Additive and
    //  nullable: a field added before this keeps its old behaviour (empty =
    //  the end of the form), so nothing already captured moves.
    if (function_exists('ensure_column')) ensure_column('custom_fields', 'section', "VARCHAR(80) DEFAULT ''");
}

// The sections a given form offers, as [name => name], for the "where should it
// go?" picker. Read from the same registry that drives the standard-field list,
// so the choices are always the sections the form actually has.
function fd_sections($form) {
    $f = fd_forms()[$form] ?? null;
    if (!$f) return [];
    $out = [];
    foreach (($f['fields'] ?? []) as $fld) {
        $sec = trim((string)($fld['section'] ?? ''));
        if ($sec !== '') $out[$sec] = $sec;
    }
    return $out;
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
    if (function_exists('fd_forms_quality'))     $forms += fd_forms_quality();
    if (function_exists('fd_forms_operations'))  $forms += fd_forms_operations();
    if (function_exists('fd_forms_rest'))        $forms += fd_forms_rest();
    // Company-built custom forms are designed on their own screen already, so we
    // only surface the built-in forms here.
    return $forms;
}

// ---------------------------------------------------------------------------
//  Quality, reporting and operations forms.
//
//  The Form Designer offered exactly two forms, while the engine underneath
//  already supported eleven entities and fourteen form views already accepted
//  added fields. Nothing was missing architecturally — the registry simply
//  stopped at recruitment, so an admin could tailor a requirement but not a
//  sample, a method or a controlled document.
//
//  A form appears here only when it is genuinely designable: it must render
//  custom fields and carry fd_overlay_html(). Declaring one that does not would
//  give an admin a screen whose changes do nothing.
// ---------------------------------------------------------------------------
// ---------------------------------------------------------------------------
//  OPERATIONS — the Test request and the Job.
//
//  The two largest forms in the product (roughly 45 and 40 designable controls).
//  They could not be declared the way the quality forms were, because their
//  labels are not English constants: nearly every one is built from the
//  company's own terminology — T('client'), Tl('quote'), TP('office') — so a
//  workspace that calls a client a "Customer" and a call a "Service request"
//  reads those words throughout. A hardcoded registry would have shown an
//  admin one set of words on the Form Designer and a different set on the form
//  itself, and the label they typed would have replaced the wrong thing.
//
//  So every label and every section name below is built from the same helpers
//  the form uses. The section names matter twice over: the overlay finds a
//  section by matching the heading text on the page, so a name that stopped
//  tracking the terminology would quietly stop matching, and a field placed
//  there would never move.
// ---------------------------------------------------------------------------
function fd_forms_operations() {
    if (function_exists('licence_enabled') && !licence_enabled('operations')) return [];
    $F = fn($label, $type, $section, $locked = false) => ['label' => $label, 'type' => $type, 'section' => $section, 'locked' => $locked];
    // The company's own words, resolved once.
    $T  = fn($k) => function_exists('T')   ? T($k)   : ucfirst($k);
    $Tl = fn($k) => function_exists('Tl')  ? Tl($k)  : $k;
    $TH = fn($k) => function_exists('TH')  ? TH($k)  : ucfirst($k);
    $TP = fn($k) => function_exists('TP')  ? TP($k)  : ucfirst($k) . 's';
    $Tlp= fn($k) => function_exists('Tlp') ? Tlp($k) : $k . 's';
    $cur = function_exists('cur_sym') ? cur_sym() : '';

    //  Section names are the words the heading renders, WITHOUT the leading
    //  number — the overlay matches on a substring of the heading, so
    //  "Client & quotation" finds "1. Client & quotation".
    $s1 = $T('client') . ' & ' . $Tl('quote');
    $s2 = 'What is being inspected';
    $s3 = 'When';
    $s4 = 'Which ' . $TP('office') . ', and the money between them';
    $s5 = "Against the " . $Tl('client') . "'s purchase order";
    $s6 = 'Reporting owed to the ' . $Tl('client');

    $out = [];
    $out['call'] = [
        'label' => $TP('call'), 'icon' => '📞',
        'help'  => 'The new ' . $Tl('call') . ' form',
        'fields' => [
            // 1 — who it is for
            'client_id'       => $F($T('client'), 'dropdown', $s1, true),
            'quotation_id'    => $F($T('quote'), 'dropdown', $s1),
            'contract_number' => $F('Contract number', 'text', $s1),
            //  NB: this control appears TWICE on this form — here, and again
            //  under the purchase-order section. It is declared once; the
            //  overlay applies a rename or a hide to both copies.
            'quote_line_id'   => $F('Line item on the ' . $Tl('quote'), 'dropdown', $s1),
            'vendor_id'       => $F($T('vendor') . ' / ' . $Tl('manufacturer') . ' (site)', 'dropdown', $s1),
            'folder_link'     => $F('Shared folder / drive link', 'text', $s1),
            // 2 — what is being inspected
            'sbu'              => $F($T('sbu'), 'dropdown', $s2),
            'activity_id'      => $F('Activity code', 'dropdown', $s2),
            'service_code'     => $F('Service line', 'dropdown', $s2),
            'inspection_type'  => $F('Type of inspection', 'dropdown', $s2, true),
            'product_category' => $F('Product category', 'dropdown', $s2),
            'product_other'    => $F('Product (if "Others")', 'text', $s2),
            'site_address_id'  => $F('Site (' . $Tl('client') . "'s site)", 'dropdown', $s2),
            // 3 — when
            'call_received_date'       => $F($TH('call') . ' received', 'date', $s3),
            'inspection_required_date' => $F($TH('client') . "'s required date", 'date', $s3),
            'engagement_type'          => $F('Shape of the engagement', 'dropdown', $s3),
            'days_count'               => $F('How many days, continuously?', 'number', $s3),
            'months_count'             => $F('How many months on site?', 'number', $s3),
            'manmonth_basis'           => $F('Man-month basis', 'dropdown', $s3),
            'manmonth_min_days'        => $F('Minimum working days', 'number', $s3),
            'pattern_kind'             => $F('How does it repeat?', 'dropdown', $s3),
            'pattern_n'                => $F('How many', 'number', $s3),
            'schedule_end_date'        => $F('Repeat until', 'date', $s3),
            // 4 — offices and the money between them
            'ibo_office_id'       => $F('Contracting ' . $T('office'), 'dropdown', $s4, true),
            'executing_office_id' => $F('Executing ' . $T('office'), 'dropdown', $s4, true),
            'coordinator_id'      => $F('Forward to coordinator', 'dropdown', $s4),
            'region'              => $F('Region', 'dropdown', $s4),
            'billable_rate'       => $F('Unit rate — excluding GST (' . $cur . ')', 'number', $s4),
            'billable_basis'      => $F('Basis', 'dropdown', $s4),
            'billable_qty'        => $F('Quantity', 'number', $s4),
            'billable_value'      => $F('Value billable to the ' . $Tl('client') . ' (' . $cur . ', ex-GST)', 'number', $s4),
            'credit_rate'         => $F('Credit per man-day to the executing ' . $T('office') . ' (' . $cur . ')', 'number', $s4),
            'expected_credit'     => $F('Total credit (' . $cur . ')', 'number', $s4),
            'credit_type'         => $F('Credit basis', 'dropdown', $s4),
            'billable_value_x'    => $F('Total invoice value to the ' . $Tl('client') . ' (' . $cur . ', ex-GST)', 'number', $s4),
            // 5 — against the purchase order
            'po_id'           => $F('Purchase order', 'dropdown', $s5),
            'po_line_item_id' => $F('PO line item', 'dropdown', $s5),
            'notes'           => $F('Notes', 'text', $s5),
            // 6 — reporting owed
            'reporting_frequency' => $F('Reporting frequency', 'dropdown', $s6),
            'report_custom_days'  => $F('…every how many days?', 'number', $s6),
        ],
    ];

    $j1 = 'Assignment';
    $j2 = 'Who does it';
    $j3 = 'Order & dates';
    $j4 = 'Money';
    $j5 = 'Reporting & closure';
    $out['job'] = [
        'label' => $TP('job'), 'icon' => '🛠️',
        'help'  => 'The ' . $Tl('job') . ' form',
        'fields' => [
            'executing_office_id' => $F('Executing ' . $T('office'), 'dropdown', $j1, true),
            'stage'               => $F('Stage', 'dropdown', $j1, true),
            'job_type'            => $F('How it is worked', 'dropdown', $j1),
            'service_code'        => $F('Service line', 'dropdown', $j1),
            'inspection_type'     => $F('Type of inspection', 'dropdown', $j1),
            'sbu'                 => $F($T('sbu'), 'dropdown', $j1),
            'activity_id'         => $F('Activity code', 'dropdown', $j1),
            // who does it
            'req_trade_id'    => $F('Required trade / discipline', 'dropdown', $j2),
            'staff_kind_pick' => $F('Who does it', 'dropdown', $j2),
            'non_asset_kind'  => $F('…which kind', 'dropdown', $j2),
            'inspector_id'    => $F($T('engineer'), 'dropdown', $j2),
            'subcon_id'       => $F('Sub-contracting agency', 'dropdown', $j2),
            'subcon_cost'     => $F('Sub-con cost (' . $cur . ')', 'number', $j2),
            'other_cost'      => $F('Any other cost (' . $cur . ')', 'number', $j2),
            'other_cost_note' => $F('What was it for?', 'text', $j2),
            // order & dates
            'quotation_id'             => $F($T('quote'), 'dropdown', $j3),
            //  NOT declared here: the two dates carried from the Test request.
            //  They render read-only, with no name attribute at all, so the
            //  overlay could never find them — a design row for either would
            //  have looked like it worked and silently renamed nothing.
            'scheduled_date'           => $F('Actual scheduled date', 'date', $j3, true),
            'engagement_type'          => $F('Shape of the engagement', 'dropdown', $j3),
            'days_count'               => $F('How many days, continuously?', 'number', $j3),
            'months_count'             => $F('How many months on site?', 'number', $j3),
            'manmonth_basis'           => $F('Man-month basis', 'dropdown', $j3),
            'manmonth_min_days'        => $F('Minimum working days', 'number', $j3),
            'pattern_kind'             => $F('How does it repeat?', 'dropdown', $j3),
            'schedule_end_date'        => $F('Repeat until', 'date', $j3),
            'mandays'                  => $F('Man-days', 'number', $j3),
            // money
            //  On the Job these two are NOT the call's columns: the unit rate
            //  is a read-only mirror under its own display name, and the value
            //  the branch is judged on is invoice_value.
            'billable_rate_display' => $F('Unit rate', 'number', $j4),
            'invoice_value'         => $F('Invoice value to the ' . $Tl('client') . ' (' . $cur . ', ex-GST)', 'number', $j4),
            'credit_rate'     => $F('Credit per man-day to the executing ' . $Tl('office') . ' (' . $cur . ')', 'number', $j4),
            'expected_credit' => $F('Total credit (' . $cur . ')', 'number', $j4),
            'credit_type'     => $F('Credit type', 'dropdown', $j4),
            'credit_direction'=> $F('Credit direction', 'dropdown', $j4),
            // reporting & closure
            'reporting_frequency' => $F('Reporting frequency', 'dropdown', $j5),
            'report_custom_days'  => $F('…every how many days?', 'number', $j5),
            'folder_link'         => $F('Shared folder / drive link', 'text', $j5),
        ],
    ];
    return $out;
}

// ---------------------------------------------------------------------------
//  THE REMAINING TWELVE.
//
//  These could not be offered before for a reason that had nothing to do with
//  the registry: their views could not render an added field at all, and their
//  save paths did not store one. Declaring them then would have given an admin
//  a design screen where renaming worked and "add a field" went nowhere — half
//  working, which is worse than not offered. Each now renders the extras
//  through fd_extra_fields() and stores them through custom_save().
//
//  Labels were read from each form rather than scraped: an extractor pairs the
//  wrong <label> with a control whenever help text or a nested block sits
//  between them, and several here do (the NCR severity radios, the invoice
//  payment terms). Where a form builds its wording from the company's
//  terminology, so does this.
//
//  Deliberately NOT declared: repeating sub-forms (an engineer's certificate
//  rows, an invoice's line items) and the inline "create one while you are
//  here" helpers on the user form. They are not header fields of the record,
//  and a design row for one would rename a box that appears many times.
// ---------------------------------------------------------------------------
function fd_forms_rest() {
    $F = fn($label, $type, $section, $locked = false) => ['label' => $label, 'type' => $type, 'section' => $section, 'locked' => $locked];
    $T  = fn($k) => function_exists('T')  ? T($k)  : ucfirst($k);
    $Tl = fn($k) => function_exists('Tl') ? Tl($k) : $k;
    $TH = fn($k) => function_exists('TH') ? TH($k) : ucfirst($k);
    $TP = fn($k) => function_exists('TP') ? TP($k) : ucfirst($k) . 's';
    $on = fn($m) => !function_exists('licence_enabled') || licence_enabled($m);
    $cur = function_exists('cur_sym') ? cur_sym() : '';
    $out = [];

    if ($on('operations')) {
        $out['inspector'] = [
            'label' => $TP('engineer'), 'icon' => '👷',
            'help'  => 'The ' . $Tl('engineer') . ' record',
            'fields' => [
                'first_name'          => $F('First name', 'text', 'Who they are', true),
                'middle_name'         => $F('Middle name', 'text', 'Who they are'),
                'last_name'           => $F('Last name', 'text', 'Who they are'),
                'emp_code'            => $F('Employee code', 'text', 'Who they are', true),
                'email'               => $F('Email', 'text', 'Who they are'),
                'mobile'              => $F('Mobile', 'text', 'Who they are'),
                'designation'         => $F('Designation', 'dropdown', 'Their role'),
                'staff_kind'          => $F($TH('engineer') . ' type', 'dropdown', 'Their role'),
                'agency_id'           => $F('Engaged via agency', 'dropdown', 'Their role'),
                'team_role'           => $F('Team', 'dropdown', 'Their role', true),
                'trade_id'            => $F('Trade / discipline', 'dropdown', 'Their role'),
                'status'              => $F('Status', 'dropdown', 'Their role', true),
                'home_office_id'      => $F('Posted ' . $Tl('office'), 'dropdown', 'Where they sit'),
                'weekly_working_days' => $F('Weekly working days', 'dropdown', 'Where they sit'),
                'reports_to_id'       => $F('Reporting manager', 'dropdown', 'Where they sit'),
                'salary_ctc'          => $F('Annual CTC (' . $cur . ')', 'number', 'Allowances & rates'),
                'agency_cost'         => $F('Agency hiring cost (' . $cur . '/yr)', 'number', 'Allowances & rates'),
            ],
        ];
    }

    if ($on('reporting')) {
        $out['equipment'] = [
            'label' => 'Instruments', 'icon' => '📐',
            'help'  => 'The instrument register',
            'fields' => [
                'code'                => $F('Identification code', 'text', 'The instrument', true),
                'name'                => $F('Instrument', 'text', 'The instrument', true),
                'kind'                => $F('Type / method', 'text', 'The instrument'),
                'make'                => $F('Make', 'text', 'The instrument'),
                'model'               => $F('Model', 'text', 'The instrument'),
                'serial_no'           => $F('Serial number', 'text', 'The instrument'),
                'range_spec'          => $F('Range', 'text', 'The instrument'),
                'accuracy'            => $F('Accuracy / uncertainty', 'text', 'The instrument'),
                'office_id'           => $F($TH('office') . ' that owns it', 'dropdown', 'Custody'),
                'inspector_id'        => $F('Held by', 'dropdown', 'Custody'),
                'status'              => $F('State', 'dropdown', 'Custody'),
                'cal_interval_months' => $F('Calibration interval (months)', 'number', 'Custody'),
                'owned_by'            => $F('Ownership', 'dropdown', 'Custody'),
                'notes'               => $F('Notes', 'text', 'Custody'),
            ],
        ];
        $out['complaint'] = [
            'label' => 'Complaints', 'icon' => '📣',
            'help'  => 'The complaint / appeal form',
            'fields' => [
                'kind'              => $F('What is it?', 'dropdown', 'How it reached us', true),
                'received_on'       => $F('Received on', 'date', 'How it reached us'),
                'channel'           => $F('How it reached us', 'dropdown', 'How it reached us'),
                'source'            => $F('Who from', 'dropdown', 'How it reached us'),
                'complainant_name'  => $F('Their name', 'text', 'Who complained'),
                'complainant_email' => $F('E-mail', 'email', 'Who complained'),
                'complainant_phone' => $F('Telephone', 'text', 'Who complained'),
                'partner_id'        => $F('Which ' . $Tl('client') . ' or party', 'dropdown', 'Who complained'),
                'inspector_id'      => $F('Which ' . $Tl('engineer'), 'dropdown', 'What it is about'),
                'job_id'            => $F($TH('job') . ' number', 'text', 'What it is about'),
                'report_irn'        => $F($TH('report') . ' number (IRN)', 'text', 'What it is about'),
                'subject'           => $F('Subject', 'text', 'What it is about', true),
                'description'       => $F('What they told us', 'text', 'What it is about', true),
            ],
        ];
        $out['incident'] = [
            'label' => 'Security incidents', 'icon' => '🚨',
            'help'  => 'The incident record',
            'fields' => [
                'detected_at'      => $F('When it was noticed', 'text', 'What happened', true),
                'kind'             => $F('What kind', 'dropdown', 'What happened', true),
                'severity'         => $F('How bad', 'dropdown', 'What happened'),
                'summary'          => $F('In your own words, what happened', 'text', 'What happened', true),
                'systems'          => $F('What was affected', 'text', 'What happened'),
                'people_affected'  => $F("Roughly how many people's data", 'number', 'What happened'),
                'data_kinds'       => $F('What sort of data', 'text', 'What happened'),
                'immediate_action' => $F('What you did straight away', 'text', 'What happened'),
                'root_cause'       => $F('Why it happened', 'text', 'What happened'),
                'certin_reported_at' => $F('Reported to CERT-In at', 'text', 'Who has been told'),
                'certin_ref'         => $F('Their reference', 'text', 'Who has been told'),
                'dpb_reported_at'    => $F('Reported to the Data Protection Board at', 'text', 'Who has been told'),
                'people_told_at'     => $F('The people affected were told at', 'text', 'Who has been told'),
                'status'             => $F('Status', 'dropdown', 'Who has been told', true),
            ],
        ];
        $out['ncr'] = [
            'label' => 'Non-conformities', 'icon' => '❗',
            'help'  => 'The NCR form',
            'fields' => [
                'source'      => $F('Where it came from', 'dropdown', 'The finding', true),
                'source_note' => $F('Reference at the source', 'text', 'The finding'),
                //  The extractor paired this with the severity heading above it —
                //  it is the description box, and "How serious" is the radio group.
                'description' => $F('What was found', 'text', 'The finding', true),
                'severity'    => $F('How serious', 'dropdown', 'The finding'),
                'job_id'      => $F('Against a ' . $Tl('job'), 'dropdown', 'Where and who'),
                'office_id'   => $F($TH('office'), 'dropdown', 'Where and who'),
                'detected_on' => $F('Detected on', 'date', 'Where and who'),
                'clause'      => $F('Clause', 'text', 'Where and who'),
                'owner'       => $F('Owner', 'text', 'Where and who'),
                'due_on'      => $F('Due by', 'date', 'Where and who'),
                'containment' => $F('What was done immediately', 'text', 'Where and who'),
            ],
        ];
        $out['capa'] = [
            'label' => 'Corrective actions', 'icon' => '🛠️',
            'help'  => 'The CAPA form',
            'fields' => [
                'title'            => $F('In one line', 'text', 'What went wrong', true),
                'description'      => $F('What went wrong', 'text', 'What went wrong', true),
                'source_ref'       => $F('Their reference', 'text', 'What went wrong'),
                'severity'         => $F('How serious', 'dropdown', 'What went wrong'),
                'clause'           => $F('Clause it touches', 'dropdown', 'Ownership'),
                'raised_on'        => $F('Raised on', 'date', 'Ownership'),
                'owner'            => $F('Whose job it is', 'text', 'Ownership'),
                'due_on'           => $F('Action due by', 'date', 'Ownership'),
                'immediate_action' => $F('What we did straight away', 'text', 'Ownership'),
            ],
        ];
        $out['audit'] = [
            'label' => 'Internal audits', 'icon' => '🔎',
            'help'  => 'The audit plan form',
            'fields' => [
                'planned_on' => $F('Planned for', 'date', 'The plan', true),
                'auditor'    => $F('Auditor', 'text', 'The plan', true),
                'area_owner' => $F('Who runs this area', 'text', 'The plan'),
                'scope'      => $F('Scope — what is being looked at', 'text', 'The plan'),
                'method'     => $F('How', 'text', 'The plan'),
            ],
        ];
    }

    if ($on('sales')) {
        $out['lead'] = [
            'label' => 'Leads', 'icon' => '🎯',
            'help'  => 'The new-lead form',
            'fields' => [
                'partner_id'      => $F('Company or person', 'dropdown', 'Who they are', true),
                'contact_name'    => $F('Contact name', 'text', 'Who they are'),
                'contact_email'   => $F('E-mail', 'email', 'Who they are'),
                'contact_phone'   => $F('Telephone', 'text', 'Who they are'),
                'source'          => $F('Where they came from', 'dropdown', 'Who they are'),
                'requirement'     => $F('The requirement', 'text', 'What they want'),
                'value'           => $F('Value they are worth', 'number', 'What they want'),
                'expected_close'  => $F('Expected to close', 'date', 'What they want'),
                'deputation_kind' => $F('Type of deputation', 'dropdown', 'Manpower deputation'),
                'manpower_count'  => $F('How many people', 'number', 'Manpower deputation'),
                'manpower_skills' => $F('Skills / qualifications needed', 'text', 'Manpower deputation'),
                'site_location'   => $F('Site details', 'text', 'Manpower deputation'),
                'owner_user_id'   => $F('Allocated to', 'dropdown', 'Who chases it'),
                'office_id'       => $F($TH('office'), 'dropdown', 'Who chases it'),
                'pipeline_id'     => $F('Pipeline', 'dropdown', 'Who chases it'),
                'next_action'     => $F('Next thing to do', 'text', 'Who chases it'),
                'next_action_on'  => $F('By when', 'date', 'Who chases it'),
            ],
        ];
        $out['opportunity'] = [
            'label' => 'Opportunities', 'icon' => '💡',
            'help'  => 'The new-opportunity form',
            'fields' => [
                'name'           => $F('What is the opportunity?', 'text', 'The opportunity', true),
                'partner_id'     => $F($T('client'), 'dropdown', 'The opportunity'),
                'partner_name'   => $F('…or who it is for', 'text', 'The opportunity'),
                'pipeline_id'    => $F('Pipeline', 'dropdown', 'The opportunity'),
                'value'          => $F('Estimated value', 'number', 'The opportunity'),
                'expected_close' => $F('Expected close', 'date', 'The opportunity'),
                'office_id'      => $F($TH('office'), 'dropdown', 'The opportunity'),
                'source'         => $F('Where it came from', 'dropdown', 'The opportunity'),
                'competitor'     => $F('Competing against', 'text', 'The opportunity'),
                'contact_name'   => $F('Contact', 'text', 'Contact & next step'),
                'contact_email'  => $F('Contact e-mail', 'email', 'Contact & next step'),
                'contact_phone'  => $F('Contact phone', 'text', 'Contact & next step'),
                'next_action'    => $F('Next action', 'text', 'Contact & next step'),
                'next_action_on' => $F('By when', 'date', 'Contact & next step'),
                'requirement'    => $F('What they need', 'text', 'Contact & next step'),
            ],
        ];
    }

    if ($on('money')) {
        $out['invoice'] = [
            'label' => 'Invoices', 'icon' => '🧾',
            'help'  => 'The invoice header',
            'fields' => [
                'partner_id'      => $F($T('client'), 'dropdown', 'Who it is for', true),
                'office_id'       => $F($TH('office'), 'dropdown', 'Who it is for', true),
                'invoice_date'    => $F('Invoice date', 'date', 'Who it is for', true),
                //  Paired with the "Payment terms" heading by the extractor; it
                //  is the PO number box further down the Terms block.
                'po_number'       => $F('PO number', 'text', 'Terms'),
                'contract_number' => $F('Contract number', 'text', 'Terms'),
                'notes'           => $F('Notes on the invoice', 'text', 'Terms'),
            ],
        ];
        $out['receipt'] = [
            'label' => 'Receipts', 'icon' => '💵',
            'help'  => 'The money-received form',
            'fields' => [
                'office_id'     => $F($TH('office'), 'dropdown', 'The receipt', true),
                'receipt_date'  => $F('Date received', 'date', 'The receipt', true),
                'mode'          => $F('How it came', 'dropdown', 'The receipt'),
                'amount'        => $F('Amount in the bank', 'number', 'The receipt', true),
                'tds_amount'    => $F('TDS the customer withheld', 'number', 'Deductions'),
                'bank_charges'  => $F('Bank charges', 'number', 'Deductions'),
                'bank'          => $F('Bank', 'text', 'Deductions'),
                'reference'     => $F('Reference', 'text', 'Deductions'),
            ],
        ];
    }

    $out['user'] = [
        'label' => 'Users', 'icon' => '🔑',
        'help'  => 'The login record',
        'fields' => [
            'username'            => $F('Username', 'text', 'Who they are', true),
            'first_name'          => $F('First name', 'text', 'Who they are'),
            'last_name'           => $F('Last name', 'text', 'Who they are'),
            'email'               => $F('Email', 'text', 'Who they are'),
            'role'                => $F('Role', 'dropdown', 'Who they are', true),
            'home_office_id'      => $F('Home ' . $Tl('office'), 'dropdown', 'Who they are'),
            'inspector_id'        => $F('Team member', 'dropdown', 'Who they are'),
            'team_member_role'    => $F('Which team', 'dropdown', 'Who they are'),
            'position_title'      => $F('Position title', 'dropdown', 'Who they are'),
            'weekly_working_days' => $F('Working days a week', 'dropdown', 'Working pattern'),
            'daily_hours'         => $F('Working hours a full day', 'number', 'Working pattern'),
            'half_day_hours'      => $F('Hours on the half day', 'number', 'Working pattern'),
            'monthly_ctc'         => $F('Cost to the company, per month (' . $cur . ')', 'number', 'Cost & where it belongs'),
            'reports_to_id'       => $F('Reports to', 'dropdown', 'Cost & where it belongs'),
            'reports_to_name'     => $F('Manager name', 'text', 'Cost & where it belongs'),
            'reports_to_position' => $F('Manager position', 'text', 'Cost & where it belongs'),
            'reports_to_email'    => $F('Manager e-mail', 'email', 'Cost & where it belongs'),
        ],
    ];
    return $out;
}

function fd_forms_quality() {
    $rep = !function_exists('licence_enabled') || licence_enabled('reporting');
    $ops = !function_exists('licence_enabled') || licence_enabled('operations');
    $F = fn($label, $type, $section, $locked = false) => ['label' => $label, 'type' => $type, 'section' => $section, 'locked' => $locked];
    $out = [];

    if ($ops) {
        $out['sample'] = [
            //  TP() returns the company's own word, which is stored lower-case
            //  ("sample"). Every other card here is a heading, so it is headed
            //  the same way rather than sitting in the row in lower case.
            'label' => function_exists('THP') ? THP('sample') : (function_exists('TP') ? ucfirst(TP('sample')) : 'Items received'), 'icon' => '📦',
            'help'  => 'The item-received form',
            'fields' => [
                'description'    => $F('Description of the item', 'text', 'The item', true),
                'item_type'      => $F('Type', 'dropdown', 'The item'),
                'maker_ref'      => $F('Maker / heat / batch ref', 'text', 'The item'),
                'quantity'       => $F('Quantity', 'text', 'The item'),
                'unit'           => $F('Unit', 'text', 'The item'),
                'received_on'    => $F('Received on', 'date', 'Receipt'),
                'received_by'    => $F('Received by', 'text', 'Receipt'),
                'condition_code' => $F('Condition on receipt', 'dropdown', 'Receipt'),
                'condition_note' => $F('Condition note', 'text', 'Receipt'),
                'storage_code'   => $F('Storage location', 'dropdown', 'Receipt'),
                'partner_id'     => $F('Client / owner', 'dropdown', 'Receipt'),
                'notes'          => $F('Notes', 'text', 'Receipt'),
            ],
        ];
        $out['satisfaction'] = [
            'label' => 'Client satisfaction', 'icon' => '⭐',
            'help'  => 'The feedback form',
            'fields' => [
                'client_id' => $F('Client', 'dropdown', 'Feedback', true),
                'about'     => $F('What this is about', 'text', 'Feedback'),
            ],
        ];
    }

    if ($rep) {
        $out['method'] = [
            'label' => 'Test method', 'icon' => '🧪',
            'help'  => 'The method register form',
            'fields' => [
                'title'          => $F('Title', 'text', 'The method', true),
                'standard_ref'   => $F('Standard reference', 'text', 'The method'),
                'revision'       => $F('Revision', 'text', 'The method'),
                'category'       => $F('Category', 'dropdown', 'The method'),
                'discipline'     => $F('Discipline / business unit', 'text', 'The method'),
                'effective_date' => $F('Effective date', 'date', 'Control'),
                'review_due'     => $F('Review due', 'date', 'Control'),
                'owner'          => $F('Owner / custodian', 'text', 'Control'),
                'method_file'    => $F('Method document', 'text', 'Control'),
                'description'    => $F('Description / scope', 'text', 'Control'),
            ],
        ];
        $out['risk'] = [
            'label' => 'Risk / opportunity', 'icon' => '⚠️',
            'help'  => 'The risk register form',
            'fields' => [
                'kind'        => $F('This is a…', 'dropdown', 'What it is', true),
                'category'    => $F('Category', 'dropdown', 'What it is'),
                'title'       => $F('Title', 'text', 'What it is', true),
                'context'     => $F('Area / context', 'text', 'What it is'),
                'description' => $F('Description', 'text', 'What it is'),
                'likelihood'  => $F('Likelihood', 'dropdown', 'Assessment'),
                'impact'      => $F('Impact', 'dropdown', 'Assessment'),
                'treatment'   => $F('Treatment — how it is addressed', 'text', 'Assessment'),
                'owner'       => $F('Owner', 'text', 'Assessment'),
                'review_due'  => $F('Review due', 'date', 'Assessment'),
            ],
        ];
        $out['decision_rule'] = [
            'label' => 'Decision rule', 'icon' => '⚖️',
            'help'  => 'The pass / fail rule form',
            'fields' => [
                'title'            => $F('Title', 'text', 'The rule', true),
                'method_id'        => $F('Belongs to method', 'dropdown', 'The rule'),
                'characteristic'   => $F('Characteristic judged', 'text', 'The rule'),
                'accept_criteria'  => $F('Acceptance criteria', 'text', 'Criteria'),
                'reject_criteria'  => $F('Rejection criteria', 'text', 'Criteria'),
                'decision_basis'   => $F('Decision basis', 'dropdown', 'Criteria'),
                'uncertainty_rule' => $F('How uncertainty is applied', 'text', 'Criteria'),
                'owner'            => $F('Owner / custodian', 'text', 'Criteria'),
                'notes'            => $F('Notes', 'text', 'Criteria'),
            ],
        ];
        $out['controlled_doc'] = [
            'label' => 'Controlled document', 'icon' => '📄',
            'help'  => 'The document register form',
            'fields' => [
                'title'          => $F('Title', 'text', 'The document', true),
                'doc_type'       => $F('Type', 'dropdown', 'The document'),
                'revision'       => $F('Revision', 'text', 'The document'),
                'owner'          => $F('Owner / custodian', 'text', 'The document'),
                'approved_by'    => $F('Approved by', 'text', 'Approval'),
                'approved_on'    => $F('Approved on', 'date', 'Approval'),
                'effective_date' => $F('Effective date', 'date', 'Approval'),
                'review_due'     => $F('Review due', 'date', 'Approval'),
                'cdoc_file'      => $F('Document file', 'text', 'Distribution'),
                'distribution'   => $F('Distribution (who holds a copy)', 'text', 'Distribution'),
                'description'    => $F('Description / purpose', 'text', 'Distribution'),
            ],
        ];
    }
    return $out;
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

/**
 * ONE LINE makes a form designable.
 *
 * A form needs three things to be tailorable: it must render the fields an
 * admin added, read back what was captured in them, and apply the company's
 * label / order / hide / required overrides. Doing that as three separate
 * edits — view, route and save — is how the first twelve forms ended up
 * half-wired: several rendered added fields but applied no overrides, so a
 * rename in the Form Designer silently did nothing.
 *
 * This does the first and the third together, and reads its own values, so a
 * view needs no new variables from its route:
 *
 *     <?= fd_extra_fields('inspector', $ins['id'] ?? 0) ?>
 *
 * Saving is deliberately NOT hidden in here. custom_save() must be called by
 * the code that owns the record, inside whatever transaction it uses, and a
 * helper that appeared to save from the view would be a lie the day one of
 * them rolls back.
 *
 * The heading is only emitted when there is something under it, and the
 * overlay hides it again if every field beneath was moved into a section.
 */
function fd_extra_fields($entity, $recordId = 0, $heading = 'More details') {
    $out = '';
    if (function_exists('custom_fields_for')) {
        try {
            if (custom_fields_for($entity)) {
                $vals = ($recordId && function_exists('custom_values_map'))
                    ? custom_values_map($entity, (int) $recordId) : [];
                ob_start();
                echo '<h3>' . e($heading) . '</h3><div class="form-grid">';
                render_custom_fields($entity, $vals);
                echo '</div>';
                $out = ob_get_clean();
            }
        } catch (Throwable $e) { /* a form is never taken down by its extras */ }
    }
    // The overrides apply whether or not anything was added — renaming and
    // hiding a BUILT-IN field is the commoner case by far.
    return $out . (function_exists('fd_overlay_html') ? fd_overlay_html($entity) : '');
}

// Emit a small, SAFE overlay script for a built-in form: it renames labels,
// hides fields (kept in the DOM so their value is never blanked on save), and
// reorders fields WITHIN their own container — all display-only, because the
// save reads $_POST by field name, never by position. No-op when the company
// has set no overrides, so the form renders exactly as coded by default.
function fd_overlay_html($form) {
    if (!function_exists('fd_overrides')) return '';
    $ov = fd_overrides($form);
    //  An admin who only ADDS a field and places it in a section sets no label,
    //  hide or order override at all. Bailing out on an empty $ov therefore
    //  emitted nothing, and the placement they had just chosen was silently
    //  ignored — the field stayed at the bottom under "More details".
    $placed = [];
    if (function_exists('custom_fields_for')) {
        try {
            foreach (custom_fields_for($form) as $cf) {
                $sec = trim((string) ($cf['section'] ?? ''));
                if ($sec !== '') $placed['cf_' . $cf['field_key']] = $sec;
            }
        } catch (Throwable $e) { /* older install without the column — nothing to move */ }
    }
    if (!$ov && !$placed) return '';
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
    $placedJson = json_encode($placed, JSON_UNESCAPED_UNICODE);
    // The applier is display-only and defensive: it only touches fields it finds,
    // renames labels, hides fields (kept in the DOM so their value still submits
    // and is never blanked), toggles required, and reorders the managed fields
    // within their own container while leaving every other field in place.
    $js = <<<JS
<script>
(function(){
  var O = $json, PLACED = $placedJson;
  //  ALL matches, not the first. A field name can legitimately appear more than
  //  once on one form — the Test request form asks for the quotation line in two
  //  places — and hiding only the first left the field half-hidden: gone from one
  //  section, still sitting in the other. Radio groups share a name too, so the
  //  containers are de-duplicated below rather than the elements.
  function fieldEls(name){
    var a = document.querySelectorAll('[name="'+name+'"]');
    if (a.length) return Array.prototype.slice.call(a);
    return Array.prototype.slice.call(document.querySelectorAll('[name="'+name+'[]"]'));
  }
  function fieldEl(name){ return fieldEls(name)[0] || null; }
  function ffOf(el){ return el.closest('.ff') || el.closest('.form-field') || el.parentElement; }
  var byParent = new Map();
  Object.keys(O).forEach(function(name){
    var els = fieldEls(name); if(!els.length) return;
    var o = O[name], done = [];
    els.forEach(function(el){
      var ff = ffOf(el);
      if(ff){ if(done.indexOf(ff) >= 0) return; done.push(ff); }
      if(o.label && ff){ var lab = ff.querySelector('label'); if(lab){ lab.childNodes.length ? (lab.firstChild.nodeType===3 ? lab.firstChild.nodeValue=o.label : lab.textContent=o.label) : lab.textContent=o.label; } }
      if(o.hidden){ el.removeAttribute('required'); if(ff) ff.style.display='none'; }
      else if(o.req==='yes'){ el.setAttribute('required','required'); }
      else if(o.req==='no'){ el.removeAttribute('required'); }
      // Ordering is per container, and only the FIRST placement of a repeated
      // field takes part — reordering a field that appears twice would otherwise
      // drag the second copy across the form.
      if(ff && ff.parentElement && done.length === 1){
        var p = ff.parentElement;
        if(!byParent.has(p)) byParent.set(p, []);
        byParent.get(p).push({ff:ff, order:o.order});
      }
    });
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

  // ---- Move added fields into the section they were placed in -------------
  //  A field the admin added renders once, in the form's "More details" block,
  //  and is moved here. Moving rather than rendering in place means a field
  //  whose section has since been renamed simply stays where it was rendered —
  //  still on the form, still saving — instead of vanishing along with the data
  //  it already holds.
  function norm(t){ return (t||'').replace(/\s+/g,' ').replace(/[^a-z0-9 &\/-]/gi,'').trim().toLowerCase(); }
  function sectionBody(name){
    var want = norm(name);
    if(!want) return null;
    var heads = document.querySelectorAll('h2, h3, h4, legend');
    for(var i=0;i<heads.length;i++){
      var h = heads[i];
      // The numbered badge ("1", "4") is part of the heading text — compare on
      // the words, so "1 Client & position" matches the section "Client & position".
      var t = norm(h.textContent);
      if(t !== want && t.indexOf(want) < 0) continue;
      // Prefer the grid that follows the heading; fall back to the heading's own
      // container, which is where a form without a .form-grid keeps its fields.
      var sib = h.nextElementSibling;
      while(sib){
        if(sib.classList && (sib.classList.contains('form-grid') || sib.classList.contains('rq-chk'))) return sib;
        if(/^H[234]$/.test(sib.tagName) || sib.tagName === 'LEGEND') break;
        sib = sib.nextElementSibling;
      }
      var g = h.parentElement && h.parentElement.querySelector('.form-grid');
      if(g) return g;
      return h.parentElement || null;
    }
    return null;
  }
  Object.keys(PLACED).forEach(function(name){
    var el = fieldEl(name); if(!el) return;
    var ff = ffOf(el); if(!ff) return;
    var target = sectionBody(PLACED[name]);
    if(!target || target === ff.parentElement) return;
    if(target.contains(ff)) return;
    target.appendChild(ff);
  });
  // A "More details" heading left with nothing under it is noise — hide it, but
  // only when its block is genuinely empty.
  document.querySelectorAll('h3, h4').forEach(function(h){
    var ht = norm(h.textContent);
    if(ht !== 'more details' && ht !== 'additional details') return;
    var g = h.nextElementSibling;
    if(g && g.classList && g.classList.contains('form-grid') && !g.querySelector('.ff')){
      h.style.display='none'; g.style.display='none';
    }
  });
})();
</script>
JS;
    return $js;
}

// Save posted overrides for one form. $rows is a list of ASSOCIATIVE rows —
//   ['field_key' => …, 'label' => …, 'hidden' => 0|1, 'req' => ''|'yes'|'no',
//    'sort_order' => int]
// — not positional ones. The old wording here read like a positional list, which
// is a silent failure when believed: every row saves with an empty field_key and
// the overlay then carries nothing at all.
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

// ===========================================================================
//  ADD / EDIT / DELETE FIELDS + DROPDOWNS — all from the one Form Designer.
//
//  The overlay above tunes the BUILT-IN fields (rename / reorder / hide /
//  require). These helpers let an admin also ADD brand-new fields to the same
//  form, DELETE ones they added, and build a dropdown WITH its options right
//  here — folding what used to be three separate screens (Form Designer,
//  Custom fields, Masters) into one. They reuse the proven engines: a new field
//  is a custom_fields row (already rendered & saved by the Requirement /
//  Candidate forms), and a new dropdown is a lookup_types + lookup_values list
//  (the same master-list engine). So nothing here is a parallel system — it is
//  the existing capability, surfaced in one place.
// ===========================================================================

// The field types an admin can add. Display word => stored custom_fields type.
// (All are already understood by render_custom_fields()/custom_save().)
function fd_field_types() {
    return [
        'text'     => 'Text (single line)',
        'textarea' => 'Paragraph (multi-line)',
        'number'   => 'Number',
        'date'     => 'Date',
        'select'   => 'Dropdown (pick from a list)',
    ];
}

// The custom (admin-added) fields on a form, newest sort last. Built-in fields
// are NOT here — they live in fd_forms().
function fd_custom_fields($form) {
    if (!function_exists('custom_fields_for')) return [];
    try { return custom_fields_for((string) $form, false); } catch (Throwable $e) { return []; }
}

// Every existing dropdown list, so an admin can point a new dropdown at one they
// already built instead of retyping the options.
function fd_lists() {
    if (!function_exists('lk_types')) return [];
    try { return lk_types(); } catch (Throwable $e) { return []; }
}

// The options of one dropdown list, for the inline options editor.
function fd_list_values($typeId) {
    if (!$typeId || !function_exists('lk_all_values')) return [];
    try { return lk_all_values((int) $typeId); } catch (Throwable $e) { return []; }
}

// Split a pasted block of options into a clean, de-duplicated list. Accepts one
// per line OR comma-separated, trims blanks, keeps the admin's order.
function fd_parse_options($raw) {
    $raw = str_replace(["\r\n", "\r"], "\n", (string) $raw);
    $out = [];
    foreach (preg_split('/[\n,]+/', $raw) as $p) {
        $p = trim($p);
        if ($p !== '' && !in_array($p, $out, true)) $out[] = $p;
    }
    return $out;
}

// Create a brand-new dropdown list (lookup_type + its values) on the fly and
// return its id. Tagged to the People module so it groups sensibly on Masters.
function fd_make_list($entity, $label, $optionsRaw) {
    $base = 'cf_' . trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($entity . '_' . $label)), '_');
    if ($base === 'cf_' || $base === 'cf') $base = 'cf_list';
    $key = $base; $n = 1;
    while (function_exists('lk_type') && lk_type($key)) { $key = $base . '_' . (++$n); }
    $tid = lk_add_type($key, $label !== '' ? $label : 'List', null, 0, 50);
    try { db()->prepare("UPDATE lookup_types SET module=? WHERE id=?")->execute(['People', $tid]); } catch (Throwable $e) {}
    $so = 0;
    foreach (fd_parse_options($optionsRaw) as $o) lk_add_value($tid, null, '', $o, $so++);
    return (int) $tid;
}

// Add a new field to a form. For a dropdown the admin either reuses an existing
// list or types the options here and we build the list first. Flashes the result.
function fd_field_add($form) {
    $label = trim((string) ($_POST['nf_label'] ?? ''));
    $type  = (string) ($_POST['nf_type'] ?? 'text');
    $req   = !empty($_POST['nf_required']) ? 1 : 0;
    if (!array_key_exists($type, fd_field_types())) $type = 'text';
    if ($label === '') { flash('Type a name for the field.', 'error'); return; }

    $lt = null;
    if ($type === 'select') {
        $mode = (string) ($_POST['nf_list_mode'] ?? 'new');
        if ($mode === 'existing') {
            $lt = (int) ($_POST['nf_list_id'] ?? 0);
            if (!$lt || !lk_type_by_id($lt)) { flash('Pick which list this dropdown uses, or create a new one.', 'error'); return; }
        } else {
            if (!fd_parse_options($_POST['nf_options'] ?? '')) { flash('Type at least one option for the dropdown — one per line.', 'error'); return; }
            $lt = fd_make_list($form, $label, $_POST['nf_options'] ?? '');
        }
    }

    // A stable, unique key within this form (so saved values are never orphaned).
    $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]+/', '_', $label), '_'));
    if ($base === '') $base = 'field';
    $fkey = $base; $n = 1;
    while ((int) ops_val("SELECT COUNT(*) FROM custom_fields WHERE entity=? AND field_key=?", [(string) $form, $fkey]) > 0) $fkey = $base . '_' . (++$n);
    $sort = (int) ops_val("SELECT COALESCE(MAX(sort_order),0)+1 FROM custom_fields WHERE entity=?", [(string) $form]);
    //  Where it goes. Only a section this form really has is accepted — a typed
    //  or stale name would send the field to a container that does not exist,
    //  and it would silently vanish from the form.
    $sec = trim((string) ($_POST['nf_section'] ?? ''));
    if ($sec !== '' && !isset(fd_sections($form)[$sec])) $sec = '';
    db()->prepare("INSERT INTO custom_fields (entity,field_key,label,field_type,lookup_type_id,required,sort_order,section,active,created_at)
                   VALUES (?,?,?,?,?,?,?,?,1,?)")
        ->execute([(string) $form, $fkey, $label, $type, $lt, $req, $sort, $sec, date('c')]);
    flash('Added “' . $label . '” to your form' . ($sec !== '' ? ', under “' . $sec . '”.' : ', at the end under “More details”.'));
}

// Rename a custom field / toggle required. The type and its list are kept as-is
// so data already captured under the field is never orphaned (same rule the
// Custom fields editor uses).
function fd_field_edit($form) {
    $id  = (int) ($_POST['field_id'] ?? 0);
    $cur = ops_one("SELECT * FROM custom_fields WHERE id=? AND entity=?", [$id, (string) $form]);
    if (!$cur) { flash('That field no longer exists.', 'error'); return; }
    $label = trim((string) ($_POST['ef_label'] ?? ''));
    if ($label === '') { flash('The field needs a name.', 'error'); return; }
    $req = !empty($_POST['ef_required']) ? 1 : 0;
    //  A field can be moved to another section later — the same validation as on
    //  add, so it can never be sent to a container the form does not have.
    //  Absent from the post (an older form, or a form with no sections) means
    //  "leave it where it is" rather than "move it to the end".
    $sec = array_key_exists('ef_section', $_POST) ? trim((string) $_POST['ef_section']) : (string) ($cur['section'] ?? '');
    if ($sec !== '' && !isset(fd_sections($form)[$sec])) $sec = '';
    db()->prepare("UPDATE custom_fields SET label=?, required=?, section=? WHERE id=? AND entity=?")
        ->execute([$label, $req, $sec, $id, (string) $form]);
    flash('Updated “' . $label . '”' . ($sec !== '' ? ' — it now sits under “' . $sec . '”.' : '.'));
}

// Delete a custom field the admin added, and its captured values (no orphans).
// Built-in fields are never here, so this can only ever remove an added field.
function fd_field_delete($form) {
    $id  = (int) ($_POST['field_id'] ?? $_GET['del_field'] ?? 0);
    $cur = ops_one("SELECT * FROM custom_fields WHERE id=? AND entity=?", [$id, (string) $form]);
    if (!$cur) return;
    db()->prepare("DELETE FROM custom_fields WHERE id=? AND entity=?")->execute([$id, (string) $form]);
    try { db()->prepare("DELETE FROM custom_values WHERE entity=? AND field_key=?")->execute([(string) $form, $cur['field_key']]); } catch (Throwable $e) {}
    flash('Removed the field “' . $cur['label'] . '”.');
}

// Add one option to a dropdown's list, inline.
function fd_option_add() {
    $tid   = (int) ($_POST['list_id'] ?? 0);
    $label = trim((string) ($_POST['opt_label'] ?? ''));
    if (!$tid || !lk_type_by_id($tid) || $label === '') { flash('Type the option text first.', 'error'); return; }
    if ((int) ops_val("SELECT COUNT(*) FROM lookup_values WHERE type_id=? AND label=?", [$tid, $label]) > 0) {
        flash('“' . $label . '” is already on this list.', 'warning'); return;
    }
    lk_add_value($tid, null, '', $label, 99);
    flash('Option “' . $label . '” added.');
}

// Remove one option from a dropdown's list, inline.
function fd_option_delete() {
    $tid = (int) ($_POST['list_id'] ?? 0);
    $vid = (int) ($_POST['value_id'] ?? 0);
    if ($tid && $vid) {
        db()->prepare("DELETE FROM lookup_values WHERE id=? AND type_id=?")->execute([$vid, $tid]);
        flash('Option removed.');
    }
}

// ---------------------------------------------------------------------------
//  Admin screen — Form Designer (/form-designer, /form-designer-save)
// ---------------------------------------------------------------------------
function ops_form_designer($route, $method) {
    ops_require(fd_can(), 'Only an administrator can design forms.');
    $forms = fd_forms();

    // Add / edit / delete a field, and inline dropdown-option edits — all POST,
    // all scoped to a real designable form, all returning to the same screen.
    $fieldActions = ['form-designer-field-add', 'form-designer-field-edit', 'form-designer-field-del',
                     'form-designer-option-add', 'form-designer-option-del'];
    if (in_array($route, $fieldActions, true) && $method === 'POST') {
        $form = (string) ($_POST['form'] ?? '');
        if (!isset($forms[$form])) { flash('Unknown form.', 'error'); redirect('/form-designer'); }
        switch ($route) {
            case 'form-designer-field-add':   fd_field_add($form);    break;
            case 'form-designer-field-edit':  fd_field_edit($form);   break;
            case 'form-designer-field-del':   fd_field_delete($form); break;
            case 'form-designer-option-add':  fd_option_add();        break;
            case 'form-designer-option-del':  fd_option_delete();     break;
        }
        redirect('/form-designer?form=' . urlencode($form));
    }

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
    view('ops/form_designer', [
        'forms'   => $forms,
        'sel'     => $sel,
        'ov'      => $ov,
        'custom'  => $sel !== '' ? fd_custom_fields($sel) : [],   // admin-added fields on this form
        'lists'   => fd_lists(),                                  // existing dropdown lists to reuse
        'types'   => fd_field_types(),                            // the field types offered
        'editId'  => (int) ($_GET['edit_field'] ?? 0),           // a custom field being edited inline
    ]);
    return true;
}
