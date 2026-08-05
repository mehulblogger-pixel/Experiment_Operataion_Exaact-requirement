<?php
// ============================================================================
//  Secondary indexes — the registers stop table-scanning
//
//  The app had almost no secondary indexes: the big registers (parties, calls,
//  jobs, quotes, reports, custom-field values, the audit trail) were filtered
//  and joined on unindexed columns, so every list read the whole table. On a
//  demo that is invisible; on a real database it is the single largest source
//  of slowness. This one file adds the indexes, in one place, idempotently.
//
//  Why its own helper rather than act_index(): act_index() re-throws anything
//  that is not a duplicate, so a column that does not exist on a given install
//  would abort the boot. An index is never load-bearing — if a table or column
//  is not present here, we simply skip that index. So idx_add() swallows every
//  error. Plain CREATE INDEX (no "IF NOT EXISTS", which MySQL rejects) plus
//  swallowing the duplicate error is what makes this safe on MySQL AND SQLite.
//
//  boot() runs this once after each deploy (guarded by the schema fingerprint),
//  not on every request, so building the indexes costs nothing at steady state.
// ============================================================================

// Create one index, swallowing everything: duplicate name, missing table, or a
// column this install does not have. None of those is worth breaking a page for.
function idx_add($table, $name, $cols) {
    if (!function_exists('db')) return;
    try { db()->exec("CREATE INDEX $name ON $table $cols"); }
    catch (Throwable $e) { /* dup / missing table / missing column — never fatal */ }
}

function indexes_migrate() {
    static $done = false; if ($done) return; $done = true;

    // ---- Directory: parties are the most-joined table in the app ------------
    idx_add('business_partners', 'ix_bp_client',  '(is_client, status)');
    idx_add('business_partners', 'ix_bp_vendor',  '(is_vendor, status)');
    idx_add('business_partners', 'ix_bp_subcon',  '(is_subcontractor, status)');
    idx_add('business_partners', 'ix_bp_branch',  '(home_branch_id)');
    idx_add('business_partners', 'ix_bp_parent',  '(parent_id)');
    idx_add('business_partners', 'ix_bp_name',    '(display_name)');
    idx_add('business_partners', 'ix_bp_legal',   '(legal_name)');
    idx_add('business_partners', 'ix_bp_code',    '(code)');
    idx_add('partner_contacts',  'ix_pcont_partner',  '(partner_id)');
    idx_add('partner_addresses', 'ix_paddr_partner',  '(partner_id)');

    // ---- Operations: calls and jobs -----------------------------------------
    idx_add('calls', 'ix_calls_status',  '(status)');
    idx_add('calls', 'ix_calls_client',  '(client_id)');
    idx_add('calls', 'ix_calls_vendor',  '(vendor_id)');
    idx_add('calls', 'ix_calls_execoff', '(executing_office_id, status)');
    idx_add('calls', 'ix_calls_contoff', '(contracting_office_id)');
    idx_add('calls', 'ix_calls_quote',   '(quotation_id)');
    idx_add('calls', 'ix_calls_activity','(activity_id)');
    idx_add('calls', 'ix_calls_created', '(created_at)');
    idx_add('calls', 'ix_calls_code',    '(call_code)');

    idx_add('jobs', 'ix_jobs_call',      '(call_id)');
    idx_add('jobs', 'ix_jobs_inspector', '(inspector_id, closed_flag)');
    idx_add('jobs', 'ix_jobs_subcon',    '(subcon_id)');
    idx_add('jobs', 'ix_jobs_execoff',   '(executing_office_id, closed_flag)');
    idx_add('jobs', 'ix_jobs_closed',    '(closed_flag, stage)');
    idx_add('jobs', 'ix_jobs_start',     '(inspection_start_date)');
    idx_add('jobs', 'ix_jobs_end',       '(inspection_end_date)');
    idx_add('jobs', 'ix_jobs_boss',      '(boss_id)');
    idx_add('jobs', 'ix_jobs_quote',     '(quotation_id)');
    idx_add('jobs', 'ix_jobs_invraised', '(invoice_raised)');
    idx_add('jobs', 'ix_jobs_code',      '(job_code)');
    idx_add('job_visits', 'ix_jv_job',   '(job_id)');
    idx_add('job_visits', 'ix_jv_insp',  '(inspector_id, visit_date)');
    idx_add('job_bills',  'ix_jb_job',   '(job_id)');
    idx_add('boss_numbers','ix_boss_client', '(client_id, status)');

    // ---- Sales: quotations, lines, inquiries, opportunities -----------------
    idx_add('quotations', 'ix_quo_status',  '(status)');
    idx_add('quotations', 'ix_quo_client',  '(client_id)');
    idx_add('quotations', 'ix_quo_inquiry', '(inquiry_id)');
    idx_add('quotations', 'ix_quo_lead',    '(lead_id)');
    idx_add('quotations', 'ix_quo_current', '(is_current, status)');
    idx_add('quotations', 'ix_quo_parent',  '(parent_id)');
    idx_add('quotations', 'ix_quo_office',  '(office_id)');
    idx_add('quotations', 'ix_quo_owner',   '(owner_id)');
    idx_add('quotations', 'ix_quo_created', '(created_at)');
    idx_add('quotations', 'ix_quo_no',      '(quote_no)');
    idx_add('quote_lines',     'ix_ql_quote',   '(quote_id)');
    idx_add('quote_locations', 'ix_qloc_quote', '(quote_id)');
    idx_add('crm_inquiries', 'ix_inq_status',   '(status)');
    idx_add('crm_inquiries', 'ix_inq_client',   '(client_id)');
    idx_add('crm_inquiries', 'ix_inq_assigned', '(assigned_to)');
    idx_add('opportunities', 'ix_opp_stage',   '(pipeline_id, stage_id, status)');
    idx_add('opportunities', 'ix_opp_partner', '(partner_id)');
    idx_add('opportunities', 'ix_opp_lead',    '(lead_id)');
    idx_add('opportunities', 'ix_opp_status',  '(status)');
    idx_add('opportunities', 'ix_opp_owner',   '(owner_user_id)');
    idx_add('opportunities', 'ix_opp_call',    '(call_id)');
    idx_add('leads', 'ix_leads_partner', '(partner_id)');

    // ---- Reporting: the IDEMS document engine -------------------------------
    idx_add('report_docs', 'ix_rd_status',    '(status, deleted)');
    idx_add('report_docs', 'ix_rd_type',      '(report_type_id)');
    idx_add('report_docs', 'ix_rd_client',    '(client_id)');
    idx_add('report_docs', 'ix_rd_vendor',    '(vendor_id)');
    idx_add('report_docs', 'ix_rd_job',       '(job_id)');
    idx_add('report_docs', 'ix_rd_call',      '(call_id)');
    idx_add('report_docs', 'ix_rd_inspector', '(inspector_id)');
    idx_add('report_docs', 'ix_rd_approver',  '(approver_user_id)');
    idx_add('report_docs', 'ix_rd_verify',    '(verify_code)');
    idx_add('report_docs', 'ix_rd_office',    '(office_id)');
    idx_add('report_docs', 'ix_rd_created',   '(created_at)');
    idx_add('report_docs', 'ix_rd_irn',       '(irn)');
    idx_add('report_fields',   'ix_rf_type',    '(report_type_id)');
    idx_add('report_fields',   'ix_rf_section', '(section_id)');
    idx_add('report_sections', 'ix_rs_type',    '(report_type_id)');
    idx_add('report_files',    'ix_rfiles_doc', '(report_doc_id)');
    idx_add('report_approvals','ix_rap_doc',    '(report_doc_id, level)');
    idx_add('report_approvals','ix_rap_user',   '(resolved_user_id, status)');
    idx_add('report_approvals','ix_rap_sla',    '(status, sla_due)');
    idx_add('report_doc_review','ix_rdr_doc',   '(report_doc_id)');
    idx_add('report_client_reviews','ix_rcr_doc',     '(report_doc_id)');
    idx_add('report_client_reviews','ix_rcr_partner', '(partner_id)');
    idx_add('report_equipment','ix_req_doc',    '(report_doc_id)');
    idx_add('report_equipment','ix_req_equip',  '(equipment_id)');
    idx_add('report_types',    'ix_rt_code',    '(code)');
    idx_add('evidence_chain',  'ix_ec_doc',     '(report_doc_id, seq)');
    idx_add('evidence_chain',  'ix_ec_file',    '(file_id)');

    // ---- Field evidence: site visits ----------------------------------------
    idx_add('site_visits', 'ix_sv_job',  '(job_id)');
    idx_add('site_visits', 'ix_sv_insp', '(inspector_id, at)');
    idx_add('site_visits', 'ix_sv_kind', '(job_id, kind)');

    // ---- Custom fields: read on every entity by (entity, record) ------------
    idx_add('custom_values', 'ix_cv_lookup', '(entity, record_id)');
    idx_add('custom_values', 'ix_cv_field',  '(entity, field_key)');
    idx_add('custom_fields', 'ix_cf_entity', '(entity)');

    // ---- Money: vouchers, invoices, receipts, expenses ----------------------
    idx_add('vouchers', 'ix_vou_insp',   '(inspector_id, month)');
    idx_add('vouchers', 'ix_vou_status', '(status)');
    idx_add('vouchers', 'ix_vou_office', '(office_id)');
    idx_add('voucher_entries', 'ix_ve_voucher', '(voucher_id)');
    idx_add('voucher_entries', 'ix_ve_job',     '(job_id)');
    idx_add('voucher_entries', 'ix_ve_date',    '(entry_date)');
    idx_add('invoices',      'ix_inv_office', '(office_id)');
    idx_add('invoices',      'ix_inv_quote',  '(quotation_id)');
    idx_add('invoices',      'ix_inv_status', '(status, invoice_date)');
    idx_add('invoice_lines', 'ix_invl_call',  '(call_id)');
    idx_add('receipts',      'ix_rcp_date',   '(receipt_date)');
    idx_add('expenses',      'ix_exp_job',    '(job_id)');
    idx_add('expenses',      'ix_exp_insp',   '(inspector_id)');
    idx_add('tally_exports', 'ix_tex_job',    '(job_id)');
    idx_add('tally_exports', 'ix_tex_batch',  '(batch_ref)');

    // ---- People: users, attendance, allowances ------------------------------
    idx_add('users', 'ix_users_email',    '(email)');
    idx_add('users', 'ix_users_username', '(username)');
    idx_add('users', 'ix_users_inspector','(inspector_id)');
    idx_add('users', 'ix_users_reportsto','(reports_to_id)');
    idx_add('users', 'ix_users_office',   '(home_office_id)');
    idx_add('attendance', 'ix_att_insp', '(inspector_id, att_date)');
    idx_add('attendance', 'ix_att_date', '(att_date)');
    idx_add('inspector_allowances', 'ix_ialw_insp', '(inspector_id)');

    // ---- Masters: lookup lists and their cascade ----------------------------
    idx_add('lookup_values', 'ix_lv_type',   '(type_id, active)');
    idx_add('lookup_values', 'ix_lv_parent', '(parent_value_id)');
    idx_add('lookup_types',  'ix_lt_key',    '(type_key)');
    idx_add('lookup_types',  'ix_lt_parent', '(parent_type_id)');
    idx_add('lookup_types',  'ix_lt_module', '(module)');

    // ---- Contracts ----------------------------------------------------------
    idx_add('partner_contracts', 'ix_pc_partner', '(partner_id, is_active)');
    idx_add('partner_contracts', 'ix_pc_quote',   '(quotation_id)');
    idx_add('partner_contracts', 'ix_pc_num',     '(contract_number)');

    // ---- Quality & accreditation registers ----------------------------------
    idx_add('authorisations', 'ix_auth_insp',  '(inspector_id, status)');
    idx_add('authorisations', 'ix_auth_scope', '(scope_kind, scope_value)');
    idx_add('inspector_certs','ix_cert_insp',  '(inspector_id, status)');
    idx_add('inspector_certs','ix_cert_valid', '(valid_to)');
    idx_add('capa_events',      'ix_capae_capa', '(capa_id)');
    idx_add('ncr_events',       'ix_ncre_ncr',   '(ncr_id)');
    idx_add('complaint_events', 'ix_cmpe_cmp',   '(complaint_id)');
    idx_add('site_doc_requirements', 'ix_sdr_partner', '(partner_id)');
    idx_add('person_documents',        'ix_pd_person',  '(person_kind, person_id)');
    idx_add('person_documents',        'ix_pd_expires', '(expires_on)');
    idx_add('person_documents',        'ix_pd_retain',  '(retain_until)');
    idx_add('person_document_access',  'ix_pda_doc',    '(document_id)');

    // ---- The audit trail grows without bound; it is read by entity + date ----
    idx_add('idems_audit', 'ix_ia_entity',  '(entity, entity_id)');
    idx_add('idems_audit', 'ix_ia_irn',     '(irn)');
    idx_add('idems_audit', 'ix_ia_created', '(created_at)');

    // ---- Activity spine (its module indexes partner/entity/when already) -----
    idx_add('activities', 'ix_act_owner', '(owner, occurred_at)');

    // ---- Email log ----------------------------------------------------------
    idx_add('email_log', 'ix_email_when', '(created_at)');
}
