<?php
// ===========================================================================
//  Start again
//
//  While a system is being set up, the fastest way to test something properly
//  is with nothing in the way: type ten calls, see how it behaves, throw them
//  out, type them again the right way. Without a button for that, the only
//  options are deleting records one at a time or living with test data mixed
//  into real data for ever — and the second one is how a register stops being
//  trusted.
//
//  So: clear whole groups of records on purpose, with the count shown before
//  anything happens and the exact words "DELETE" typed by hand.
//
//  Three things are never touched:
//    · the account of the person pressing the button — nobody locks themselves
//      out of their own system
//    · the settings (terminology, company name, colours, e-mail)
//    · the starter master lists, which are put back straight afterwards, so
//      the app is usable the moment the page reloads
// ===========================================================================

// The groups, in the order they are offered. Each is a plain list of tables;
// nothing clever, so what it says on the screen is exactly what happens.
function reset_groups() {
    return [
        'work' => [
            'label' => 'Day-to-day work',
            'note'  => 'Inquiries, quotations, ' . Tlp('call') . ', ' . Tlp('job') . ', expenses, '
                     . Tlp('voucher') . ', attendance, BOSS numbers, credit reconciliation.',
            'tables' => ['crm_inquiries', 'quotations', 'quote_lines', 'quote_locations', 'quote_revisions',
                'quote_approvals', 'quote_files', 'quote_followups', 'quote_edit_requests',
                'calls', 'jobs', 'expenses', 'vouchers', 'voucher_entries', 'attendance',
                'inspector_day_status', 'boss_numbers', 'credit_recon', 'contract_overrides',
                'candidates', 'candidate_events', 'requisitions'],
        ],
        'reports' => [
            'label' => 'Reports & endorsements',
            'note'  => 'Everything in the report register and the endorsement register, including uploaded files.',
            'tables' => ['report_docs', 'report_files', 'report_approvals', 'report_doc_review',
                'endorsements', 'endorsement_files', 'learned_suggestions'],
        ],
        'costing' => [
            'label' => 'Costing figures',
            'note'  => 'Monthly ' . Tl('office') . ' expenses, the percentage splits set on people, and every '
                     . 'month-end cost run. The expense heads themselves are kept.',
            'tables' => ['office_expenses', 'person_sbu_split', 'cost_allocations', 'cost_runs'],
        ],
        'partners' => [
            'label' => 'Clients & vendors',
            'note'  => 'Every client, vendor and sub-contractor with their contacts, addresses, registrations, '
                     . 'contracts and purchase orders.',
            'tables' => ['business_partners', 'partner_contacts', 'partner_addresses', 'partner_registrations',
                'partner_notes', 'partner_contracts', 'partner_purchase_orders', 'po_line_items',
                'partner_relationships', 'vendor_km_memory'],
        ],
        'people' => [
            'label' => 'People, ' . Tlp('office') . ' & agencies',
            'note'  => 'Inspection engineers, back-office staff, sub-contractors, agencies, ' . Tlp('office')
                     . ' and every login except your own.',
            'tables' => ['inspectors', 'inspector_certs', 'inspector_allowances', 'back_office_staff',
                'subcons', 'subcon_rates', 'agencies', 'work_norms', 'offices', 'users'],
        ],
        'masters' => [
            'label' => 'Master lists & custom fields',
            'note'  => 'Every dropdown list you have edited, plus custom fields and report form designs. '
                     . 'The starter lists are put back automatically.',
            'tables' => ['lookup_values', 'lookup_types', 'custom_values', 'custom_fields',
                'expense_heads', 'office_expense_heads', 'travel_modes', 'holidays',
                'report_types', 'report_fields', 'report_sections', 'report_templates',
                'idems_approval_rules', 'idems_approver_map', 'quote_approval_rules',
                'tech_phrases', 'crm_templates', 'idems_counters'],
        ],
        'audit' => [
            'label' => 'Audit trail & security records',
            'note'  => 'The audit log, sign-in attempts, incident register, consent and data requests, sent e-mail. '
                     . 'Keep these unless you are certain: the law expects logs to be retained, and this is the '
                     . 'evidence that the system was used properly.',
            'danger' => 1,
            'tables' => ['idems_audit', 'login_attempts', 'security_incidents', 'data_requests',
                'data_consents', 'email_log'],
        ],
    ];
}

// How many records each group holds right now. A table the database has never
// created counts as nothing rather than crashing the screen.
function reset_counts() {
    $out = [];
    foreach (reset_groups() as $key => $g) {
        $n = 0; $per = [];
        foreach ($g['tables'] as $t) {
            $c = 0;
            try { $c = (int)db()->query("SELECT COUNT(*) FROM $t")->fetchColumn(); } catch (Throwable $e) { continue; }
            if ($t === 'users') $c = max(0, $c - 1);          // your own login is never counted
            $per[$t] = $c; $n += $c;
        }
        $out[$key] = ['total' => $n, 'per' => $per];
    }
    return $out;
}

// Do it. Returns [table => rows removed]. The signed-in account survives, and
// the standard lists are rebuilt before the next page is drawn.
function reset_run(array $keys) {
    $groups = reset_groups();
    $me = (int)(current_user()['id'] ?? 0);
    $done = []; $total = 0;
    foreach ($keys as $k) {
        if (!isset($groups[$k])) continue;
        foreach ($groups[$k]['tables'] as $t) {
            try {
                if ($t === 'users') {
                    // Never the person pressing the button, and never the last
                    // way back in: an empty users table is a locked door.
                    $st = db()->prepare("DELETE FROM users WHERE id <> ?");
                    $st->execute([$me]);
                } else {
                    $st = db()->prepare("DELETE FROM $t");
                    $st->execute();
                }
                $n = $st->rowCount();
                if ($n > 0) { $done[$t] = $n; $total += $n; }
            } catch (Throwable $e) { continue; }   // a table this install never made
        }
    }
    // The expense heads are seeded once and remembered, so that deleting them
    // deliberately keeps them deleted. Clearing the master lists is a different
    // intention — start again — so forget that we ever seeded.
    if (in_array('masters', $keys, true)) {
        try { db()->prepare("DELETE FROM settings WHERE skey = 'expense_heads_seeded'")->execute(); } catch (Throwable $e) {}
    }
    // Put the starter lists, the expense heads and the admin account back, so
    // the next screen is a working system rather than an empty one.
    boot();
    idems_log('system', 0, 'RESET', ['field' => implode(', ', $keys), 'new' => $total . ' record(s) removed']);
    return ['tables' => $done, 'total' => $total];
}

// ---------------------------------------------------------------------------
//  The screen
// ---------------------------------------------------------------------------
function ops_reset_data($method) {
    ops_require(is_master(), 'Only the Master Admin can clear records.');
    if ($method === 'POST') {
        $typed = trim((string)($_POST['confirm_word'] ?? ''));
        $keys  = array_values(array_intersect((array)($_POST['groups'] ?? []), array_keys(reset_groups())));
        if (!$keys) { flash('Nothing was ticked, so nothing was deleted.', 'warning'); redirect('/reset-data'); }
        if (strtoupper($typed) !== 'DELETE') {
            flash('Type DELETE in the box to confirm. Nothing was deleted.', 'error');
            redirect('/reset-data');
        }
        $res = reset_run($keys);
        flash($res['total']
            ? number_format($res['total']) . ' record(s) removed from ' . count($res['tables']) . ' table(s). '
              . 'The standard lists have been put back and your login is untouched.'
            : 'There was nothing to delete in what you ticked.');
        redirect('/reset-data');
    }
    view('ops/reset_data', ['groups' => reset_groups(), 'counts' => reset_counts()]);
}
