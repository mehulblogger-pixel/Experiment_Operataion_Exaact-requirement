<?php
// ============================================================================
//  Global search — one box, every record
//
//  Blueprint 002 U2. There are 101 routes and, until now, no way to find a
//  record without first knowing which register it lives in. The box in the
//  navigation filtered MENU ITEMS, which is a different and much smaller thing:
//  it answers "where is the quotation screen", never "where is quotation
//  Q-2026-0042" and never "we spoke to somebody at Kalpataru — what have we got".
//
//  HOW IT WORKS, and the honest limits of it.
//
//  This is LIKE '%term%' across a declared list of sources, each with its own
//  small LIMIT. Not a search index. That is a deliberate choice for this
//  deployment, and the reasoning matters more than the code:
//
//    * MySQL FULLTEXT needs InnoDB and per-table indexes, and SQLite needs FTS5.
//      The application runs on both. Two implementations of search that must
//      agree is two things to keep in step, and they would not stay in step.
//    * A leading-wildcard LIKE cannot use an index, so each source is a table
//      scan. At today's volumes that is microseconds. At a million rows per
//      table it is not, and THAT is the point at which this needs replacing —
//      not before, and the replacement is a search index, which needs the
//      hosting decision (B0) made first.
//    * So: correct now, honest about when it stops being enough, and structured
//      so the sources can be re-pointed at an index without touching a screen.
//
//  WHAT MAKES IT SAFE.
//
//    * Every source declares a permission. A source the signed-in person cannot
//      see is not queried at all — not queried and then filtered, which is how
//      row counts leak.
//    * Every source that is branch-scoped carries its scope clause. Search must
//      not become the hole in scoping that every register is careful about.
//    * A source that throws is skipped and named in the result, so one missing
//      table cannot take the whole page down and cannot silently pretend there
//      were no matches.
//    * The term is bound, never interpolated.
// ============================================================================

const SEARCH_MIN   = 2;    // one letter matches everything and helps nobody
const SEARCH_PER   = 6;    // rows per source on the mixed results page
const SEARCH_PER_1 = 60;   // rows when the person has narrowed to one source

// A source's SQL is written here, in this file, by us. The only thing that
// comes from the person searching is the bound term.
// Phase 2 §22/§51 — an SBU-only scope fragment for the two search sources whose tables carry `sbu`
// but no office_id (crm_inquiries, partner_contracts). Mirrors EXACTLY how those modules' own list
// views scope (e.g. crm.php inquiries: `sbu IN (...) OR sbu=''`) so global search can never surface a
// row the register itself would hide. Master / ALL scope is unrestricted; a blank sbu stays visible
// (an unassigned row is not hidden from anyone), matching the list. Returns [sqlFragment, args].
function search_sbu_clause($col) {
    $sb = function_exists('scope_sbus') ? scope_sbus() : 'ALL';
    if ($sb === 'ALL' || !is_array($sb) || !$sb) return ['1=1', []];
    $ph = implode(',', array_fill(0, count($sb), '?'));
    return ["($col IN ($ph) OR COALESCE($col,'')='')", array_values($sb)];
}

function search_sources() {
    $sources = [];

    $add = function ($key, $label, $icon, $can, callable $run) use (&$sources) {
        if (!$can) return;                      // not asked, so not queried
        $sources[$key] = ['label' => $label, 'icon' => $icon, 'run' => $run];
    };

    // A term is matched literally. Without this, typing "%" matches every row in
    // every register — not a security hole, but it makes the box look broken,
    // and "_" would silently match any single character. Both engines take
    // backslash as the default LIKE escape, so no ESCAPE clause is needed.
    $like = fn($q) => '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], (string)$q) . '%';

    // ---- Customers and vendors ---------------------------------------------
    $add('partners', 'Customers & vendors', '🏢', can('mod.clients.view') || is_master_of('clients'),
        function ($q, $n) use ($like) {
            $l = $like($q);
            return array_map(fn($r) => [
                'title'    => $r['display_name'] ?: $r['legal_name'],
                'subtitle' => trim(($r['code'] ? $r['code'] . ' · ' : '')
                              . ($r['is_client'] ? 'Customer' : '') . ($r['is_client'] && $r['is_vendor'] ? ' & ' : '')
                              . ($r['is_vendor'] ? 'Vendor' : '')) ?: 'Business partner',
                'meta'     => trim((string)$r['gstin']) !== '' ? 'GSTIN ' . $r['gstin'] : (string)$r['state'],
                'url'      => '/partner?id=' . (int)$r['id'],
                'dim'      => $r['status'] !== 'ACTIVE',
            ], ops_all(
                "SELECT id, code, legal_name, display_name, gstin, pan, state, status, is_client, is_vendor
                 FROM business_partners
                 WHERE legal_name LIKE ? OR display_name LIKE ? OR code LIKE ? OR gstin LIKE ? OR pan LIKE ?
                 ORDER BY (status='ACTIVE') DESC, COALESCE(display_name, legal_name) LIMIT $n",
                [$l, $l, $l, $l, $l]));
        });

    // ---- People at those customers -----------------------------------------
    // Searched separately because "who is Rakesh" is a real question and the
    // answer is a person, not the company they happen to work for.
    $add('contacts', 'Contacts', '👤', can('mod.clients.view') || is_master_of('clients'),
        function ($q, $n) use ($like) {
            $l = $like($q);
            return array_map(fn($r) => [
                'title'    => $r['name'],
                'subtitle' => trim(($r['designation'] ?: '') . ' at ' . ($r['company'] ?: 'a customer'), ' at '),
                'meta'     => trim(($r['email'] ?: '') . ' ' . ($r['mobile'] ?: '')),
                'url'      => '/partner?id=' . (int)$r['partner_id'],
            ], ops_all(
                "SELECT pc.id, pc.partner_id, pc.name, pc.designation, pc.email, pc.mobile,
                        COALESCE(bp.display_name, bp.legal_name) company
                 FROM partner_contacts pc
                 LEFT JOIN business_partners bp ON bp.id = pc.partner_id
                 WHERE pc.name LIKE ? OR pc.email LIKE ? OR pc.mobile LIKE ?
                 ORDER BY pc.is_primary DESC, pc.name LIMIT $n",
                [$l, $l, $l]));
        });

    // ---- Leads --------------------------------------------------------------
    $add('leads', 'Leads', '🎯', function_exists('leads_can_view') && leads_can_view(),
        function ($q, $n) use ($like) {
            $l = $like($q);
            [$sw, $sa] = scope_office_clause('l.office_id');
            return array_map(fn($r) => [
                'title'    => $r['company_name'],
                'subtitle' => $r['ref'] . ($r['stage_name'] ? ' · ' . $r['stage_name'] : ''),
                'meta'     => trim(($r['contact_name'] ?: '') . ' ' . ($r['owner_name'] ? '· ' . $r['owner_name'] : '')),
                'url'      => '/lead?id=' . (int)$r['id'],
                'dim'      => $r['status'] !== 'OPEN',
            ], ops_all(
                "SELECT l.id, l.ref, l.company_name, l.contact_name, l.owner_name, l.status, s.name stage_name
                 FROM leads l LEFT JOIN pipeline_stages s ON s.id = l.stage_id
                 WHERE $sw AND (l.ref LIKE ? OR l.company_name LIKE ? OR l.contact_name LIKE ? OR l.contact_email LIKE ?)
                 ORDER BY (l.status='OPEN') DESC, l.id DESC LIMIT $n",
                array_merge($sa, [$l, $l, $l, $l])));
        });

    // ---- Inquiries ----------------------------------------------------------
    $add('inquiries', THP('inquiry'), '📨', can('mod.inquiries.view') || is_master_of('inquiries'),
        function ($q, $n) use ($like) {
            $l = $like($q);
            [$sw, $sa] = search_sbu_clause('sbu');
            return array_map(fn($r) => [
                'title'    => $r['inquiry_no'] . ' — ' . ($r['subject'] ?: 'no subject'),
                'subtitle' => $r['client_name'] ?: '',
                'meta'     => trim(($r['contact_name'] ?: '') . ' · ' . ($r['status'] ?: ''), ' ·'),
                'url'      => '/inquiry-edit?id=' . (int)$r['id'],
            ], ops_all(
                "SELECT id, inquiry_no, subject, client_name, contact_name, status
                 FROM crm_inquiries
                 WHERE $sw AND (inquiry_no LIKE ? OR subject LIKE ? OR client_name LIKE ?
                       OR contact_name LIKE ? OR service_requirement LIKE ?)
                 ORDER BY id DESC LIMIT $n",
                array_merge($sa, [$l, $l, $l, $l, $l])));
        });

    // ---- Quotations ---------------------------------------------------------
    $add('quotes', THP('quote'), '📝', can('mod.quotes.view') || is_master_of('quotes'),
        function ($q, $n) use ($like) {
            $l = $like($q);
            [$sw, $sa] = scope_clause('q.office_id', 'q.sbu');
            return array_map(fn($r) => [
                'title'    => $r['quote_no'] . ((int)$r['rev'] ? ' rev ' . (int)$r['rev'] : ''),
                'subtitle' => $r['subject'] ?: ($r['client_name'] ?: ''),
                'meta'     => trim(($r['client_name'] ?: '') . ' · ' . ($r['status'] ?: '')
                              . ((float)$r['total_amount'] ? ' · ' . fmoney($r['total_amount']) : ''), ' ·'),
                'url'      => '/quote?id=' . (int)$r['id'],
                'dim'      => !(int)$r['is_current'],
            ], ops_all(
                "SELECT q.id, q.quote_no, q.rev, q.is_current, q.subject, q.client_name, q.status,
                        q.total_amount, q.po_number, q.contract_number
                 FROM quotations q
                 WHERE $sw AND (q.quote_no LIKE ? OR q.subject LIKE ? OR q.client_name LIKE ?
                                OR q.contact_name LIKE ? OR q.po_number LIKE ? OR q.contract_number LIKE ?)
                 ORDER BY q.is_current DESC, q.id DESC LIMIT $n",
                array_merge($sa, [$l, $l, $l, $l, $l, $l])));
        });

    // ---- Opportunities (Module 37) -----------------------------------------
    // A CRM spine record with its own OPP- reference and register, previously
    // reachable but not findable by its own number. Permission-gated AND office+SBU
    // scoped from day one — a correctly-scoped new path, nothing loosened.
    $add('opportunities', 'Opportunities', '📈', function_exists('opp_can_view') && opp_can_view(),
        function ($q, $n) use ($like) {
            $l = $like($q);
            [$sw, $sa] = scope_clause('o.office_id', 'o.sbu');
            return array_map(fn($r) => [
                'title'    => ($r['ref'] ?: ('#' . $r['id'])) . ' — ' . ($r['name'] ?: ($r['partner_name'] ?: 'opportunity')),
                'subtitle' => $r['partner_name'] ?: '',
                'meta'     => trim(($r['status'] ?: '') . ((float)$r['value'] ? ' · ' . fmoney($r['value']) : '')
                              . ($r['owner_name'] ? ' · ' . $r['owner_name'] : ''), ' ·'),
                'url'      => '/opportunity?id=' . (int)$r['id'],
                'dim'      => ($r['status'] ?? '') !== 'OPEN',
            ], ops_all(
                "SELECT o.id, o.ref, o.name, o.partner_name, o.status, o.value, o.owner_name
                 FROM opportunities o
                 WHERE $sw AND (o.ref LIKE ? OR o.name LIKE ? OR o.partner_name LIKE ? OR o.contact_name LIKE ?)
                 ORDER BY (o.status='OPEN') DESC, o.id DESC LIMIT $n",
                array_merge($sa, [$l, $l, $l, $l])));
        });

    // ---- Invoices (Module 37) ----------------------------------------------
    // The finance spine record findable before only via a job's invoice_number.
    // Gated by books_can() and office+SBU scoped, exactly like the register.
    $add('invoices', 'Invoices', '🧾', function_exists('books_can') && books_can(),
        function ($q, $n) use ($like) {
            $l = $like($q);
            [$sw, $sa] = scope_clause('i.office_id', 'i.sbu');
            return array_map(fn($r) => [
                'title'    => $r['invoice_no'] ?: ('draft #' . $r['id']),
                'subtitle' => $r['partner_name'] ?: '',
                'meta'     => trim(($r['status'] ?: '') . ((float)$r['total'] ? ' · ' . fmoney($r['total']) : '')
                              . ($r['contract_number'] ? ' · ' . $r['contract_number'] : ''), ' ·'),
                'url'      => '/invoice?id=' . (int)$r['id'],
                'dim'      => ($r['status'] ?? '') === 'CANCELLED',
            ], ops_all(
                "SELECT i.id, i.invoice_no, i.partner_name, i.status, i.total, i.po_number, i.contract_number
                 FROM invoices i
                 WHERE $sw AND (i.invoice_no LIKE ? OR i.partner_name LIKE ? OR i.po_number LIKE ? OR i.contract_number LIKE ?)
                 ORDER BY i.id DESC LIMIT $n",
                array_merge($sa, [$l, $l, $l, $l])));
        });

    // ---- Contracts ----------------------------------------------------------
    // Search a contract by number, title or client and open its 360 — the whole
    // thread (quote, POs, calls, jobs, reports, money) on one screen.
    $add('contracts', (function_exists('THP') ? THP('contract') : 'Contracts'), '📄',
        // M10 — a bare master flag on an ungated route. A contract is what a won
        // quotation becomes (Sales), and it is also partner master data (core
        // administration), so master authority is scoped to those two rather than
        // withdrawn: is_master_of() grants through whichever the workspace has.
        can('mod.clients.view') || can('crm.contract.register') || can('data.credit')
            || (function_exists('is_master_of') && is_master_of(['clients', 'quotes']))
            || (function_exists('is_coordinator_level') && is_coordinator_level()),
        function ($q, $n) use ($like) {
            $l = $like($q);
            [$sw, $sa] = search_sbu_clause('pc.sbu');
            return array_map(fn($r) => [
                'title'    => $r['contract_number'] ?: ('#' . (int)$r['id']),
                'subtitle' => $r['client_name'] ?: '',
                'meta'     => trim(($r['title'] ?: '') . ' · ' . ($r['open_status'] ?: ''), ' ·'),
                'url'      => '/contract?id=' . (int)$r['id'],
            ], ops_all(
                "SELECT pc.id, pc.contract_number, pc.title, pc.open_status,
                        COALESCE(bp.display_name, bp.legal_name) client_name
                 FROM partner_contracts pc LEFT JOIN business_partners bp ON bp.id=pc.partner_id
                 WHERE $sw AND (pc.contract_number LIKE ? OR pc.title LIKE ? OR bp.legal_name LIKE ? OR bp.display_name LIKE ?)
                 ORDER BY pc.id DESC LIMIT $n",
                array_merge($sa, [$l, $l, $l, $l])));
        });

    // ---- Inspection calls ---------------------------------------------------
    $add('calls', THP('call'), '📞', can('mod.calls.view') || is_master_of('calls'),
        function ($q, $n) use ($like) {
            $l = $like($q);
            [$sw, $sa] = scope_clause('c.executing_office_id', 'c.sbu');
            return array_map(fn($r) => [
                'title'    => $r['call_code'],
                'subtitle' => $r['client_name'] ?: '',
                'meta'     => trim(($r['status'] ?: '') . ' · ' . ($r['call_received_date'] ? fdate($r['call_received_date']) : ''), ' ·'),
                'url'      => '/call?id=' . (int)$r['id'],
            ], ops_all(
                "SELECT c.id, c.call_code, c.status, c.call_received_date, c.contract_number,
                        COALESCE(bp.display_name, bp.legal_name) client_name
                 FROM calls c LEFT JOIN business_partners bp ON bp.id = c.client_id
                 WHERE $sw AND (c.call_code LIKE ? OR c.notes LIKE ? OR c.contract_number LIKE ?
                                OR bp.display_name LIKE ? OR bp.legal_name LIKE ?)
                 ORDER BY c.id DESC LIMIT $n",
                array_merge($sa, [$l, $l, $l, $l, $l])));
        });

    // ---- Deputations ---------------------------------------------------------
    // Invoice number is searched here because that is where it lives, and
    // "which job was invoice INV/26/0112" is the most-asked accounts question.
    $add('jobs', THP('job'), '🗂', can('mod.jobs.view') || is_master_of('jobs'),
        function ($q, $n) use ($like) {
            $l = $like($q);
            [$sw, $sa] = scope_clause('j.executing_office_id', 'j.sbu');
            return array_map(fn($r) => [
                'title'    => $r['job_code'],
                'subtitle' => trim(($r['client_name'] ?: '') . ($r['inspector_name'] ? ' · ' . $r['inspector_name'] : '')),
                'meta'     => trim(($r['invoice_number'] ? 'Invoice ' . $r['invoice_number'] . ' · ' : '')
                              . ($r['closed_flag'] ? 'Closed' : 'Open')),
                'url'      => '/job?id=' . (int)$r['id'],
                'dim'      => (int)$r['closed_flag'] === 1,
            ], ops_all(
                "SELECT j.id, j.job_code, j.invoice_number, j.closed_flag, j.contract_number,
                        i.name inspector_name, COALESCE(bp.display_name, bp.legal_name) client_name
                 FROM jobs j
                 LEFT JOIN calls c ON c.id = j.call_id
                 LEFT JOIN business_partners bp ON bp.id = c.client_id
                 LEFT JOIN inspectors i ON i.id = j.inspector_id
                 WHERE $sw AND (j.job_code LIKE ? OR j.invoice_number LIKE ? OR j.contract_number LIKE ?
                                OR i.name LIKE ? OR bp.display_name LIKE ? OR bp.legal_name LIKE ?)
                 ORDER BY j.id DESC LIMIT $n",
                array_merge($sa, [$l, $l, $l, $l, $l, $l])));
        });

    // ---- Reports -------------------------------------------------------------
    $add('reports', THP('report'), '📑', can('mod.idems.view') || is_master_of('idems'),
        function ($q, $n) use ($like) {
            $l = $like($q);
            [$sw, $sa] = scope_clause('d.office_id', 'd.sbu');
            return array_map(fn($r) => [
                'title'    => ($r['irn'] ?: 'no reference') . ((int)$r['rev'] ? ' rev ' . (int)$r['rev'] : ''),
                'subtitle' => $r['title'] ?: ($r['project_name'] ?: ''),
                'meta'     => trim(($r['client_name'] ?: '') . ' · ' . ($r['status'] ?: ''), ' ·'),
                'url'      => '/document?id=' . (int)$r['id'],
                'dim'      => (int)$r['deleted'] === 1,
            ], ops_all(
                "SELECT d.id, d.irn, d.rev, d.title, d.project_name, d.status, d.deleted, d.po_ref, d.drawing_no,
                        COALESCE(bp.display_name, bp.legal_name) client_name
                 FROM report_docs d LEFT JOIN business_partners bp ON bp.id = d.client_id
                 WHERE $sw AND d.deleted = 0
                   AND (d.irn LIKE ? OR d.title LIKE ? OR d.project_name LIKE ?
                        OR d.po_ref LIKE ? OR d.drawing_no LIKE ? OR d.verify_code LIKE ?)
                 ORDER BY d.id DESC LIMIT $n",
                array_merge($sa, [$l, $l, $l, $l, $l, $l])));
        });

    // ---- The compliance registers -------------------------------------------
    $add('complaints', 'Complaints & appeals', '📣', can('mod.complaints.view') || is_master_of('complaints'),
        function ($q, $n) use ($like) {
            $l = $like($q);
            [$sw, $sa] = scope_office_clause('office_id');
            return array_map(fn($r) => [
                'title'    => $r['ref'] . ' — ' . ($r['subject'] ?: 'no subject'),
                'subtitle' => $r['kind'] === 'APPEAL' ? 'Appeal' : 'Complaint',
                'meta'     => trim(($r['complainant_name'] ?: '') . ' · ' . ($r['status'] ?: ''), ' ·'),
                'url'      => '/complaint?id=' . (int)$r['id'],
                'dim'      => $r['status'] === 'CLOSED',
            ], ops_all(
                "SELECT id, ref, kind, subject, complainant_name, status FROM complaints
                 WHERE $sw AND (ref LIKE ? OR subject LIKE ? OR description LIKE ?
                                OR complainant_name LIKE ? OR report_irn LIKE ?)
                 ORDER BY (status='CLOSED') ASC, id DESC LIMIT $n",
                array_merge($sa, [$l, $l, $l, $l, $l])));
        });

    $add('ncr', 'Nonconformities', '⚠', function_exists('ncr_can_view') && ncr_can_view(),
        function ($q, $n) use ($like) {
            $l = $like($q);
            [$sw, $sa] = scope_clause('n.office_id', 'n.sbu');
            return array_map(fn($r) => [
                'title'    => $r['ref'] . ' — ' . ($r['title'] ?: 'no title'),
                'subtitle' => ucfirst(strtolower((string)$r['severity'])),
                'meta'     => trim(($r['owner'] ?: '') . ' · ' . ($r['status'] ?: ''), ' ·'),
                'url'      => '/ncr-item?id=' . (int)$r['id'],
                'dim'      => $r['status'] === 'CLOSED',
            ], ops_all(
                "SELECT n.id, n.ref, n.title, n.severity, n.owner, n.status FROM nonconformities n
                 WHERE $sw AND (n.ref LIKE ? OR n.title LIKE ? OR n.description LIKE ? OR n.owner LIKE ?)
                 ORDER BY (n.status='CLOSED') ASC, n.id DESC LIMIT $n",
                array_merge($sa, [$l, $l, $l, $l])));
        });

    $add('capa', 'Corrective actions', '🛠', can('mod.capa.view') || is_master_of('capa'),
        function ($q, $n) use ($like) {
            $l = $like($q);
            [$sw, $sa] = scope_office_clause('office_id');
            return array_map(fn($r) => [
                'title'    => $r['ref'] . ' — ' . ($r['title'] ?: 'no title'),
                'subtitle' => $r['clause'] ? 'Clause ' . $r['clause'] : '',
                'meta'     => trim(($r['owner'] ?: '') . ' · ' . ($r['status'] ?: ''), ' ·'),
                'url'      => '/capa-item?id=' . (int)$r['id'],
                'dim'      => $r['status'] === 'CLOSED',
            ], ops_all(
                "SELECT id, ref, title, clause, owner, status FROM capa
                 WHERE $sw AND (ref LIKE ? OR title LIKE ? OR description LIKE ?
                                OR owner LIKE ? OR root_cause LIKE ?)
                 ORDER BY (status='CLOSED') ASC, id DESC LIMIT $n",
                array_merge($sa, [$l, $l, $l, $l, $l])));
        });

    // ---- People and instruments ---------------------------------------------
    $add('people', 'People', '👷', can('mod.masters.view') || is_master_of('inspectors'),
        function ($q, $n) use ($like) {
            $l = $like($q);
            return array_map(fn($r) => [
                'title'    => $r['name'],
                'subtitle' => trim(($r['designation'] ?: '') . ($r['emp_code'] ? ' · ' . $r['emp_code'] : '')),
                'meta'     => trim(($r['email'] ?: '') . ' ' . ($r['mobile'] ?: '')),
                'url'      => '/m/inspectors/edit?id=' . (int)$r['id'],
                'dim'      => $r['status'] !== 'ACTIVE',
            ], ops_all(
                "SELECT id, name, emp_code, designation, email, mobile, status FROM inspectors
                 WHERE name LIKE ? OR emp_code LIKE ? OR email LIKE ? OR mobile LIKE ?
                 ORDER BY (status='ACTIVE') DESC, name LIMIT $n",
                [$l, $l, $l, $l]));
        });

    // ---- Recruitment ---------------------------------------------------------
    //  B8. Until now the one box could find a customer, an invoice, a report and
    //  a team member, but not the candidate you are hiring, the requisition you
    //  are hiring against, or the hiring request that authorised it. Worse than
    //  a gap: the empty state then said "Nothing matches Ravi Patel in any
    //  register you can open", about a person the searcher could open from the
    //  candidate register. It did not fail to find — it asserted absence.
    //
    //  These three follow the same shape as every source above: a permission the
    //  register itself uses, that register's OWN scope clause, and title /
    //  subtitle / meta / url. What differs is the scoping, and it differs three
    //  ways — which is why each one is taken from the module rather than copied
    //  from the source above it.

    //  Candidates. The scope here is the one thing in B8 that cannot be written
    //  by pattern-matching the other sources. A candidate has NO office of its
    //  own: rasg_cand_scope() reaches the office THROUGH the requisition
    //  (requisitions.office_id via c.requisition_id) and adds the recruitment
    //  SBU clause. Handing the generic office/SBU helper a candidate alias
    //  instead — the way every neighbouring source is written — would look
    //  right, parse, run, and scope NOTHING: an office could read another
    //  office's candidates. Use the module's helper.
    //
    //  Identity fields only — the names, the code, the e-mail, the mobile, the
    //  same set the People source uses for a person. The candidate REGISTER also
    //  searches cv_keywords and cv_text, and that is right there: reading CVs is
    //  what that screen is for. It is deliberately NOT repeated here, because in
    //  a box that searches everything, one word of a résumé would drag in every
    //  candidate who ever mentioned it and bury the invoice you were after.
    //
    //  The full name is matched as well as its parts, because "Arjun Ghosh" is
    //  what a recruiter types and neither column contains it. `||` is safe on
    //  both engines here: db.php sets PIPES_AS_CONCAT on every MySQL connection
    //  precisely so this dialect means the same thing on MariaDB as on SQLite.
    //  Do not "fix" it to CONCAT() — that would then break SQLite.
    $add('candidates', THP('candidate'), '🧑‍💼', function_exists('is_coordinator_level') && is_coordinator_level(),
        function ($q, $n) use ($like) {
            $l = $like($q);
            [$sw, $sa] = function_exists('rasg_cand_scope') ? rasg_cand_scope('c') : ['1=1', []];
            return array_map(fn($r) => [
                'title'    => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: ($r['cand_code'] ?: '—'),
                'subtitle' => trim(($r['designation'] ? (DESIGNATIONS[$r['designation']] ?? $r['designation']) : '')
                              . ($r['cand_code'] ? ' · ' . $r['cand_code'] : '')),
                'meta'     => trim(($r['stage'] ?: '') . ($r['req_code'] ? ' · ' . $r['req_code'] : '')
                              . ($r['proposed_site'] ? ' · ' . $r['proposed_site'] : ''), ' ·'),
                'url'      => '/candidate?id=' . (int)$r['id'],
            ], ops_all(
                "SELECT c.id, c.cand_code, c.first_name, c.last_name, c.designation, c.stage, c.proposed_site,
                        r.req_code
                 FROM candidates c LEFT JOIN requisitions r ON r.id = c.requisition_id
                 WHERE $sw AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.cand_code LIKE ?
                                OR c.email LIKE ? OR c.mobile LIKE ?
                                OR (c.first_name || ' ' || c.last_name) LIKE ?)
                 ORDER BY c.id DESC LIMIT $n",
                array_merge($sa, [$l, $l, $l, $l, $l, $l])));
        });

    //  Recruitment requisitions. Scoped by the module's own helper, which is
    //  office AND SBU. The label carries its module because "Requirement" is a
    //  word this product uses twice — a recruitment requisition here, a
    //  marketplace requirement in Connect — and a tenant may rename either
    //  through the terminology engine. The entity is NOT renamed; the group
    //  simply says which kind it is, so the distinction survives whatever the
    //  tenant calls them. The module word is read from the term's own group,
    //  not hard-coded.
    $add('requisitions', (TERM_DEFAULTS['requisition'][2] ?? 'Recruitment') . ' · ' . THP('requisition'), '📋',
        function_exists('is_coordinator_level') && is_coordinator_level(),
        function ($q, $n) use ($like) {
            $l = $like($q);
            [$sw, $sa] = function_exists('rcc_scope_req') ? rcc_scope_req('r') : ['1=1', []];
            return array_map(fn($r) => [
                'title'    => $r['req_code'],
                'subtitle' => trim(($r['designation'] ? (DESIGNATIONS[$r['designation']] ?? $r['designation']) : '')
                              . ((int)$r['quantity'] > 1 ? ' · ' . (int)$r['quantity'] . ' positions' : '')),
                'meta'     => trim(($r['status'] ?: '') . ($r['project_site'] ? ' · ' . $r['project_site'] : ''), ' ·'),
                'url'      => '/requisition?id=' . (int)$r['id'],
                'dim'      => in_array((string)$r['status'], ['CLOSED', 'CANCELLED'], true),
            ], ops_all(
                "SELECT r.id, r.req_code, r.designation, r.status, r.project_site, r.quantity
                 FROM requisitions r
                 WHERE $sw AND (r.req_code LIKE ? OR r.designation LIKE ? OR r.project_site LIKE ?)
                 ORDER BY r.id DESC LIMIT $n",
                array_merge($sa, [$l, $l, $l])));
        });

    //  Hiring requests. A THIRD scoping rule, and the reason each source is
    //  taken from its own module: hreq_list() scopes by OFFICE ONLY —
    //  scope_office_clause('office_id'), with no SBU clause. Reusing the
    //  requisition helper here would have added an SBU restriction the register
    //  itself does not apply, and quietly hidden requests from people entitled
    //  to see them. The permission is the register's own hreq_can_view().
    //  The query itself lives in lib/hiringreq.php, not here. That layer owns the
    //  table — test_m4_correction.php asserts no other file reads or writes it —
    //  and the boundary is deliberate: one door onto a record that carries an
    //  approval decision. So search asks the layer, exactly as it asks
    //  recruitment for the candidate and requisition scope helpers above.
    $add('hiring_requests', THP('hiring_request'), '📨',
        function_exists('hreq_can_view') && hreq_can_view(),
        function ($q, $n) use ($like) {
            $l = $like($q);
            return array_map(fn($r) => [
                'title'    => $r['req_no'],
                'subtitle' => trim((string)($r['job_title'] ?: ''))
                              ?: ($r['designation'] ? (DESIGNATIONS[$r['designation']] ?? $r['designation']) : ''),
                'meta'     => trim(($r['status'] ?: '') . ($r['requested_by_name'] ? ' · ' . $r['requested_by_name'] : ''), ' ·'),
                'url'      => '/hiring-request?id=' . (int)$r['id'],
                'dim'      => in_array((string)$r['status'], ['CANCELLED', 'REJECTED'], true),
            ], function_exists('hreq_search') ? hreq_search($l, $n) : []);
        });

    $add('equipment', 'Equipment', '📐', can('mod.equipment.view') || is_master_of('equipment'),
        function ($q, $n) use ($like) {
            $l = $like($q);
            return array_map(fn($r) => [
                'title'    => trim(($r['code'] ? $r['code'] . ' — ' : '') . $r['name']),
                'subtitle' => trim(($r['make'] ?: '') . ' ' . ($r['model'] ?: '')),
                'meta'     => $r['serial_no'] ? 'Serial ' . $r['serial_no'] : '',
                'url'      => '/equip-edit?id=' . (int)$r['id'],
                'dim'      => $r['status'] !== 'ACTIVE',
            ], ops_all(
                "SELECT id, code, name, make, model, serial_no, status FROM equipment
                 WHERE code LIKE ? OR name LIKE ? OR serial_no LIKE ? OR make LIKE ? OR model LIKE ?
                 ORDER BY (status='ACTIVE') DESC, name LIMIT $n",
                [$l, $l, $l, $l, $l]));
        });

    return $sources;
}

// Run the sources. $only narrows to one; '' searches everything the person can
// see. Returns the groups, the total, and anything that failed — a source that
// throws is NAMED rather than swallowed, because "no results" and "that
// register is broken" must never look the same.
function search_run($q, $only = '') {
    $q = trim((string)$q);
    if (mb_strlen($q) < SEARCH_MIN) return ['groups' => [], 'total' => 0, 'failed' => [], 'short' => true];

    $sources = search_sources();
    if ($only !== '' && !isset($sources[$only])) $only = '';
    $run = $only !== '' ? [$only => $sources[$only]] : $sources;
    $per = $only !== '' ? SEARCH_PER_1 : SEARCH_PER;

    $groups = []; $total = 0; $failed = [];
    foreach ($run as $key => $src) {
        try { $rows = ($src['run'])($q, $per); }
        catch (Throwable $e) { $failed[] = $src['label']; continue; }
        if (!$rows) continue;
        $groups[$key] = ['label' => $src['label'], 'icon' => $src['icon'], 'rows' => $rows,
                         'more' => count($rows) >= $per];
        $total += count($rows);
    }
    return ['groups' => $groups, 'total' => $total, 'failed' => $failed, 'short' => false,
            'sources' => array_map(fn($s) => $s['label'], $sources), 'only' => $only];
}

// A reference typed whole should go straight to the record rather than to a
// page of one result. Only when there is exactly one match and the term looks
// like a reference — otherwise a person searching for a company by name would
// be thrown into a record they were only browsing towards.
function search_only_hit(array $res, $q) {
    if ($res['total'] !== 1 || !preg_match('/[0-9]/', (string)$q) || mb_strlen(trim((string)$q)) < 4) return '';
    $g = reset($res['groups']);
    return (string)($g['rows'][0]['url'] ?? '');
}

function ops_search($route, $method) {
    $q    = (string)($_GET['q'] ?? '');
    $only = preg_replace('/[^a-z]/', '', (string)($_GET['in'] ?? ''));
    $res  = search_run($q, $only);

    if ($only === '' && ($hit = search_only_hit($res, $q)) !== '') redirect($hit);

    view('ops/search', ['q' => trim($q), 'res' => $res, 'only' => $res['only'] ?? '']);
    return true;
}
