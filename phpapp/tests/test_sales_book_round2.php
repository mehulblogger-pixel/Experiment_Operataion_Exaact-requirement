<?php
// Sales test book, round 2 (owner, 19 Nov 2026 — "Fix all smaller problems"; "Full year Target").
// One check per finding of parts SALA / SALB that was still open, each with the plain
// example the book gives, plus the full-year target.
t_section('Sales test book round 2 — the smaller problems, and the full-year target');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$made = ['l' => [], 'o' => [], 'q' => [], 'p' => [], 'pl' => [], 'g' => [], 'd' => []];
try {
    bbv_as($f['master']); leads_migrate(); opp_migrate();
    if (function_exists('crm_migrate')) crm_migrate();

    // ---- Leads ------------------------------------------------------------------------------
    t_eq([lead_real_date('2027-02-29'), lead_real_date('2026-13-45'), lead_real_date('2028-02-29'), lead_real_date('')], [false, false, true, true],
         'SALA-002 · 29 Feb 2027 and 2026-13-45 are not dates; 29 Feb 2028 is; empty is fine');
    t_ok(!empty(lead_create(['company_name' => 'Fx Date ' . uniqid(), 'expected_close' => '2026-13-45'])['err']), 'SALA-002 · a lead with an impossible expected-close date is refused');
    t_ok(!empty(lead_create(['company_name' => 'Fx Neg ' . uniqid(), 'value' => '-5000'])['err']), 'SALA-003 · a negative value is refused');
    $L = lead_create(['company_name' => 'Fx R2 Lead ' . uniqid(), 'office_id' => (string) $f['mum'], 'contact_name' => 'Fx Person', 'value' => '100000']);
    $made['l'][] = (int) $L['id'];
    $before = lead_row($L['id']);
    bbv_route('lead-edit', 'POST', $f['master'], 0, ['id' => (string) $L['id'], 'company_name' => '  ', 'value' => '-5000', 'expected_close' => '2026-13-45']);
    t_eq((string) lead_row($L['id'])['company_name'], (string) $before['company_name'], 'SALA-003 · editing a lead to an empty company name (or −₹5,000, or 2026-13-45) is refused; nothing changes');
    $dd = download_disposition('स्पेक EC.txt');
    t_ok(strpos($dd, "filename*=UTF-8''" . rawurlencode('स्पेक EC.txt')) !== false, 'SALA-007 / SALB-059 · a Hindi file name downloads with its own name (not "________EC.txt")');
    db()->prepare("INSERT INTO lead_files (lead_id,kind,file_name,mime,file_data,note,uploaded_by,uploaded_at) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([(int) $L['id'], 'ATTACHMENT', 'fx.txt', 'text/plain', base64_encode('x'), '', 'Fx', date('c')]);
    $fid = (int) db()->lastInsertId();
    bbv_route('lead-file-delete', 'POST', $f['master'], 0, [], ['id' => (string) $fid]);
    t_ok((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='LEAD' AND entity_id=? AND subject LIKE 'File removed%'", [$L['id']]) === 1,
         'SALA-008 · removing a lead document is written in the lead\'s history');
    $src = file_get_contents(dirname(__DIR__) . '/lib/leads.php');
    t_ok(strpos($src, "' (the file is empty)'") !== false, 'SALA-006 · an empty file is called empty, not "over 8 MB"');

    // Bulk "Mark lost" = the same as losing one lead.
    $L2 = lead_create(['company_name' => 'Fx Bulk ' . uniqid(), 'office_id' => (string) $f['mum']]); $made['l'][] = (int) $L2['id'];
    $reasons = function_exists('lk_options_or') ? lk_options_or('quote_lost_reason', QUOTE_LOST_REASONS) : QUOTE_LOST_REASONS;
    $_POST = ['lost_reason' => (string) array_key_first($reasons), 'lost_note' => 'Fx went elsewhere'];
    leads_bulk('lost', [(int) $L2['id']]); $_POST = [];
    $l2 = lead_row($L2['id']);
    t_ok($l2['status'] === 'LOST' && (stage_row((int) $l2['stage_id'])['kind'] ?? '') === 'LOST'
         && (int) ops_val("SELECT COUNT(*) FROM lead_stage_history WHERE lead_id=?", [$L2['id']]) >= 1,
         'SALA-013 · bulk "Mark lost" puts the lead on its Lost stage and writes the stage history');

    // "They said yes": branch, Won stage, business unit.
    $L3 = lead_create(['company_name' => 'Fx Yes Co ' . uniqid(), 'office_id' => (string) $f['mum'], 'contact_name' => 'Fx Buyer']); $made['l'][] = (int) $L3['id'];
    //  21 Nov 2026 (D1–D4): a NEW customer carries its tax identity — here a recorded exception.
    $r3 = lead_convert((int) $L3['id'], ['stage_id' => (string) lead_row($L3['id'])['stage_id'], 'company_name' => lead_row($L3['id'])['company_name'], 'sbu' => 'IND', 'tax_status' => 'UNREGISTERED', 'pan' => 'AABPB' . sprintf('%04d', random_int(0, 9999)) . 'B', 'tax_reason' => 'test — below the GST threshold']);
    $l3 = lead_row($L3['id']); $made['p'][] = (int) ($r3['partner_id'] ?? 0);
    t_eq((int) ops_val("SELECT home_branch_id FROM business_partners WHERE id=?", [(int) ($r3['partner_id'] ?? 0)]), (int) $f['mum'], 'SALA-011 · the new customer belongs to the lead\'s branch');
    t_eq((string) (stage_row((int) $l3['stage_id'])['kind'] ?? ''), 'WON', 'SALA-015 · a lead that became a customer sits on the Won stage (the CSV no longer says "New enquiry")');
    if (!empty($r3['inquiry_id'])) t_eq((string) ops_val("SELECT sbu FROM crm_inquiries WHERE id=?", [(int) $r3['inquiry_id']]), 'IND', 'SALA-011 · its enquiry gets the business unit chosen on the convert screen');

    // ---- Deals ------------------------------------------------------------------------------
    $o = opp_create(['name' => 'Fx R2 deal', 'partner_name' => 'Fx', 'value' => 100000, 'office_id' => $f['mum']]); $made['o'][] = (int) $o['id'];
    $other = (int) ops_val("SELECT s.id FROM pipeline_stages s JOIN pipelines p ON p.id=s.pipeline_id WHERE p.id<>? AND s.kind='OPEN' LIMIT 1", [(int) opp_row($o['id'])['pipeline_id']]);
    if ($other) t_ok(!empty(opp_move((int) $o['id'], $other)['err']), 'SALA-020 · a deal cannot be moved onto another funnel\'s stage');
    t_eq((string) (stage_row((int) opp_row($o['id'])['stage_id'])['kind'] ?? ''), 'OPEN', 'SALA-036 · a new deal opens on an open stage');
    $pid = (int) ops_val("SELECT id FROM business_partners WHERE is_client=1 LIMIT 1");
    $hs0 = ops_one("SELECT hold_status, hold_reason FROM business_partners WHERE id=?", [$pid]);
    db()->prepare("UPDATE business_partners SET hold_status='BLOCKED', hold_reason='Fx unpaid' WHERE id=?")->execute([$pid]);
    db()->prepare("UPDATE opportunities SET partner_id=? WHERE id=?")->execute([$pid, $o['id']]);
    $rq = opp_create_quote((int) $o['id']); $rg = opp_generate_quote((int) $o['id']);
    db()->prepare("UPDATE business_partners SET hold_status=?, hold_reason=? WHERE id=?")->execute([$hs0['hold_status'] ?? '', $hs0['hold_reason'] ?? '', $pid]);
    t_ok(!empty($rq['err']) && !empty($rg['err']), 'SALA-029 · a blocked customer gets no quotation from the deal (both buttons refuse)');
    if (!empty($rq['id'])) $made['q'][] = (int) $rq['id'];
    // Funnel with two Won stages / Won first.
    $pl = pipe_create('Fx two wins ' . uniqid(), 'OPPORTUNITY'); $plid = (int) ($pl['id'] ?? 0); $made['pl'][] = $plid;
    if ($plid) {
        db()->prepare("UPDATE pipeline_stages SET kind='WON' WHERE pipeline_id=? AND id=(SELECT MIN(id) FROM pipeline_stages WHERE pipeline_id=?)")->execute([$plid, $plid]);
        $why = implode(' ', pipe_problems($plid));
        t_ok(strpos($why, '“won” stages') !== false || strpos($why, 'first stage') !== false, 'SALA-036 · a funnel with two Won stages, or a Won stage first, is flagged (and cannot be the default): ' . $why);
    }
    // Stage-gate approvers are people who can open Sales leads, of the deal's branch.
    $ppl = gate_approvers(['approver_role' => 'BRANCH_MANAGER', 'entity_kind' => 'OPPORTUNITY', 'entity_id' => (int) $o['id']]);
    $ids = array_map(fn($u) => (int) $u['id'], $ppl);
    t_ok(!in_array(-1, $ids, true) && array_reduce($ids, fn($c, $id) => $c && sx_user_covers_office($id, $f['mum']), true),
         'SALA-022 · a Mumbai deal\'s approval goes only to Branch Managers who work for Mumbai (not every branch\'s)');
    $ops = file_get_contents(dirname(__DIR__) . '/lib/ops.php');
    t_ok(strpos($ops, "\$base === 'approval-act' && !can('mod.leads.view')") !== false, 'SALA-022 · …and the approver it waits on can act from the Approvals inbox without the Leads module');
    // ---- Costing ----------------------------------------------------------------------------
    //  SALA-069 is by design (owner, 13 Nov 2026): a FIXED client rate keeps the margin asked at that
    //  rate; "cost + my margin" adds the negotiation padding on top. test_costing_from_rate proves it.
    $rv = pc_ctc_from_rate(65625, 20, ['negotiation_pct' => 5, 'basis' => 'LONG']);
    t_eq((float) ($rv['ctc'] ?? 0), 52500.0, 'SALA-069 · a fixed client rate of ₹65,625 at 20% leaves CTC ₹52,500 — the margin is taken at the client\'s final rate (as approved 13 Nov)');
    // ---- Quotations -------------------------------------------------------------------------
    t_eq([crm_real_date('2026-02-29'), crm_real_date('31/02/2026'), crm_real_date('2028-02-29')], [false, false, true], 'SALB-045 · follow-up dates must be real');
    $qa = ['id' => 0, 'status' => 'APPROVED', 'client_id' => null, 'office_id' => null];
    t_ok(crm_quote_transition_problem($qa, 'LOST') !== '', 'SALB-052 · an approved (not sent) quotation is not marked lost by hand — docs/03 draws only Draft → Lost and Sent → Lost');
    t_ok(crm_quote_transition_problem(['id' => 0, 'status' => 'EXPIRED'] + $qa, 'LOST') !== '', 'SALB-052 · …nor an expired one');
    db()->prepare("INSERT INTO quotations (quote_no, rev, is_current, client_name, subtotal, total_amount, status, sent_at, created_at, updated_at) VALUES (?,0,1,'Fx',1,1,'ACCEPTED',?,?,?)")
        ->execute(['FX-R2-Q', date('c'), date('c'), date('c')]);
    $qid = (int) db()->lastInsertId(); $made['q'][] = $qid;
    $n0 = (int) ops_val("SELECT COUNT(*) FROM quotations WHERE quote_no='FX-R2-Q'");
    bbv_route('quote-revise', 'POST', $f['master'], 0, ['summary' => 'Fx'], ['id' => (string) $qid]);
    t_eq((int) ops_val("SELECT COUNT(*) FROM quotations WHERE quote_no='FX-R2-Q'"), $n0, 'SALB-053 · an accepted quotation is not revised (the won order stays with Accounts)');
    $f0 = (int) ops_val("SELECT COUNT(*) FROM quote_followups WHERE quote_id=?", [$qid]);
    bbv_route('quote-followup', 'POST', $f['master'], 0, ['do' => 'add', 'due_date' => '2026-02-30', 'note' => 'Fx'], ['id' => (string) $qid]);
    t_eq((int) ops_val("SELECT COUNT(*) FROM quote_followups WHERE quote_id=?", [$qid]), $f0, 'SALB-045 · a follow-up dated 30 Feb is not added');
    db()->prepare("INSERT INTO quote_files (quote_id,kind,file_name,mime,file_data,note,share_with_inspector,uploaded_by,uploaded_at) VALUES (?,?,?,?,?,?,0,?,?)")
        ->execute([$qid, 'ATTACHMENT', 'fx.pdf', 'application/pdf', base64_encode('x'), '', 'Fx', date('c')]);
    $qf = (int) db()->lastInsertId();
    bbv_route('quote-file-delete', 'POST', $f['master'], 0, [], ['id' => (string) $qf]);
    t_eq((int) ops_val("SELECT COUNT(*) FROM quote_files WHERE id=?", [$qf]), 1, 'SALB-060 · a sent quotation\'s documents cannot be removed');
    db()->prepare("DELETE FROM quote_files WHERE id=?")->execute([$qf]);
    t_ok(crm_quote_pdf_unprintable(['client_name' => 'O\'Brien & Shah Sons — શાહ'], []) && !crm_quote_pdf_unprintable(['client_name' => 'Café Ltd — ₹ terms'], []),
         'SALB-012 · Gujarati letters are spotted (the page then points to the Word document); ordinary accents are not');
    $crm = file_get_contents(dirname(__DIR__) . '/lib/crm.php');
    t_ok(strpos($crm, 'mb_strlen(trim((string)($b[\'subject\'] ?? \'\'))) > 255') !== false, 'SALB-011 · a subject over 255 letters is refused with its length, not cut silently');
    t_ok(strpos($crm, "already ' . strtolower((string)\$step['status'])") !== false, 'SALB-028 · pressing Approve twice says it was already approved');
    t_ok(strpos($crm, "(int)(\$q['rev'] ?? 0) > 0 ? '-R'") !== false, 'SALB-043 · the e-mailed PDF carries the revision (-R1) like the download');
    t_ok(strpos($crm, 'The contract cannot end') !== false, 'SALB-065 · a contract ending before it starts is refused');
    [$okD, $msgD] = appr_delegation_save(0, ['entity' => 'QUOTE', 'delegator_user_id' => $f['kiran'], 'delegate_user_id' => $f['sunil'], 'effective_from' => '2026-02-31']);
    t_ok(!$okD && stripos($msgD, 'real date') !== false, 'SALB-037 · a delegation dated 31 Feb is refused: ' . $msgD);
    $ms = file_get_contents(dirname(__DIR__) . '/views/ops/_milestones.php');
    t_ok(strpos($ms, "number_format((float)\$v, 3, '.', '')") !== false, 'SALB-069 · milestone shares show as typed (33.334%)');
    $ops = file_get_contents(dirname(__DIR__) . '/lib/ops.php');
    t_ok(strpos($ops, 'mb_substr($ln, 0, 300)') !== false, 'SALB-038 · checklist points are cut by letters, never inside a Gujarati letter');

    // ---- Owner, 19 Nov 2026: "Full year Target" ---------------------------------------------
    [$fa, $fb] = fy_range(active_fy());
    t_eq(sx_target_end('fy'), $fb, 'target · "This financial year" counts the whole year\'s target (to ' . $fb . '), not only the months so far');
    t_eq(sx_target_end('month'), date('Y-m-t', strtotime(sx_today())), 'target · "This month" counts the whole month');
} finally {
    $_GET = []; $_POST = [];
    foreach ($made['l'] as $id) if ($id) { db()->prepare("DELETE FROM leads WHERE id=?")->execute([$id]); db()->prepare("DELETE FROM activities WHERE entity_kind='LEAD' AND entity_id=?")->execute([$id]); db()->prepare("DELETE FROM lead_stage_history WHERE lead_id=?")->execute([$id]); }
    foreach ($made['o'] as $id) if ($id) { db()->prepare("DELETE FROM opportunities WHERE id=?")->execute([$id]); db()->prepare("DELETE FROM activities WHERE entity_kind='OPPORTUNITY' AND entity_id=?")->execute([$id]); }
    foreach ($made['q'] as $id) if ($id) { db()->prepare("DELETE FROM quotations WHERE id=?")->execute([$id]); db()->prepare("DELETE FROM quote_followups WHERE quote_id=?")->execute([$id]); }
    foreach ($made['p'] as $id) if ($id) { db()->prepare("DELETE FROM business_partners WHERE id=?")->execute([$id]); try { db()->prepare("DELETE FROM crm_inquiries WHERE client_id=?")->execute([$id]); } catch (Throwable $e) {} }
    foreach ($made['pl'] as $id) if ($id) { db()->prepare("DELETE FROM pipeline_stages WHERE pipeline_id=?")->execute([$id]); db()->prepare("DELETE FROM pipelines WHERE id=?")->execute([$id]); }
    db()->prepare("DELETE FROM quotations WHERE quote_no='FX-R2-Q'")->execute();
    bbv_cleanup($f);
}
t_ok(true, 'the test removed its own rows');
