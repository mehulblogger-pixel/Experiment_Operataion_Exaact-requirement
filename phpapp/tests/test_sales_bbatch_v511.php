<?php
// ============================================================================
//  QA audit V51.1 — Sales B-batch (15 Nov 2026): the P2/P3 Sales defects the
//  owner's standing approval covers. One check per defect, named by its DEF id.
// ============================================================================
t_section('Sales B-batch — DEF-SAL-001/018 money as people in India write it');
t_eq(nf(500000), '5,00,000', 'DEF-SAL-001 · ₹500,000 is written 5,00,000');
t_eq(nf(2500000), '25,00,000', '…₹2,500,000 is written 25,00,000');
t_eq(nf(99999999999), '99,99,99,99,999', '…and ₹99,999,999,999 is written 99,99,99,99,999');
t_eq(nf(99999), '99,999', '…below a lakh nothing changes');
t_eq(nf(-123456.5, 2), '-1,23,456.50', '…a negative figure with paise keeps its sign and paise');
t_eq(fmoney(500000), cur_sym() . '5,00,000', 'fmoney() writes lakh grouping');
t_eq(fmoney(7999.99), cur_sym() . '7,999.99', 'DEF-SAL-018 · a rate with paise is written ₹7,999.99, not ₹8,000');
t_eq(fmoney(8000), cur_sym() . '8,000', '…a whole-rupee figure shows no ".00"');
t_eq(fmoney(-1500), '-' . cur_sym() . '1,500', '…a negative amount reads -₹1,500');
t_eq(fmoney(null), '—', '…an empty amount is still a dash');

$root   = dirname(__DIR__);
$engine = $GLOBALS['__test_engine'] ?? 'sqlite';
$env    = $engine === 'sqlite'
    ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
    : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
      . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
$drive = function ($uid, $route, $method = 'POST', array $get = [], array $post = [], array $needles = [], array $session = []) use ($root, $env) {
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_sales_route_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route)
         . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post))
         . ' ' . escapeshellarg(json_encode(array_values($needles))) . ' ' . escapeshellarg(json_encode((object) $session));
    $raw = (string) shell_exec($cmd . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300), 'has' => []];
};
$said = fn($r) => implode(' | ', array_map(fn($f) => (string)($f['text'] ?? ''), $r['flash'] ?? [])) . (!empty($r['error']) ? ' [' . $r['error'] . ']' : '');


$pdo = db(); crm_migrate();
if (function_exists('leads_migrate')) leads_migrate();
if (function_exists('opp_migrate')) opp_migrate();
$tag = 'bb' . getmypid();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES (?,1)")->execute(['BB Office ' . $tag]);
$off = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id) VALUES (?,?,?,?,?,1,0,?)")
    ->execute([$tag . '_bdm', 'Bina', 'Batch', '', 'BUSINESS_DEV_MANAGER', $off]);
$BDM = (int) $pdo->lastInsertId();
$made = ['leads' => [], 'pipes' => [], 'opps' => [], 'quotes' => [], 'inq' => []];

try {
    t_section('Sales B-batch — leads');
    // DEF-SAL-017 — a man-month lead for nobody.
    $r0 = lead_create(['company_name' => 'BB MM ' . $tag, 'deputation_kind' => 'MANMONTH', 'manpower_count' => 0, 'site_location' => 'Plant 2, Dahej']);
    t_ok(!empty($r0['err']) && strpos($r0['err'], 'how many') !== false, 'DEF-SAL-017 · a man-month lead for 0 people is refused: ' . ($r0['err'] ?? 'saved'));
    $r1 = lead_create(['company_name' => 'BB MM ' . $tag, 'deputation_kind' => 'MANMONTH', 'manpower_count' => 2, 'site_location' => 'Plant 2, Dahej']);
    t_ok(empty($r1['err']) && !empty($r1['id']), '…with 2 people it is saved');
    if (!empty($r1['id'])) $made['leads'][] = (int)$r1['id'];

    // DEF-SAL-031 — an open lead whose expected close date has passed.
    t_ok(lead_close_overdue(['status' => 'OPEN', 'expected_close' => '2019-03-31']), 'DEF-SAL-031 · an open lead expected to close in 2019 is flagged');
    t_ok(!lead_close_overdue(['status' => 'OPEN', 'expected_close' => date('Y-m-d', strtotime('+30 day'))]), '…one due next month is not');
    t_ok(!lead_close_overdue(['status' => 'LOST', 'expected_close' => '2019-03-31']), '…a closed lead is not');
    t_ok(!lead_close_overdue(['status' => 'OPEN', 'expected_close' => '']), '…a lead with no date is not');
    if (!empty($r1['id'])) {
        $pdo->prepare("UPDATE leads SET expected_close='2019-03-31' WHERE id=?")->execute([(int)$r1['id']]);
        $due = array_filter(leads_due(), fn($l) => (int)$l['id'] === (int)$r1['id']);
        $why = $due ? implode(' ', reset($due)['due_reasons']) : '';
        t_ok(strpos($why, 'that date has passed') !== false, '…and it appears on the leads needing attention: ' . $why);
    }

    // DEF-SAL-026 — a year with only leads.
    $pdo->prepare("INSERT INTO leads (ref, company_name, status, created_at, updated_at) VALUES (?,?, 'OPEN', ?, ?)")
        ->execute(['BBY-' . $tag, 'BB Year ' . $tag, '1996-06-15T10:00:00', '1996-06-15T10:00:00']);
    $made['leads'][] = (int)$pdo->lastInsertId();
    // (A wide window: other tests may leave far-future dates that would narrow the default ten years.)
    t_ok(in_array(fy_label(1996), fy_options(200), true), 'DEF-SAL-026 · FY ' . fy_label(1996) . ', with only a lead in it, can be picked in the Year box');

    // DEF-SAL-025 — lead loss reasons are counted.
    $reasons = lk_options_or('quote_lost_reason', QUOTE_LOST_REASONS); $rk = array_key_first($reasons);
    $pdo->prepare("INSERT INTO leads (ref, company_name, status, lost_reason, lost_note, value, created_at, updated_at) VALUES (?,?, 'LOST', ?, 'went elsewhere', 50000, ?, ?)")
        ->execute(['BBL-' . $tag, 'BB Lost ' . $tag, $rk, date('c'), date('c')]);
    $made['leads'][] = (int)$pdo->lastInsertId();
    $rows = lead_loss_reasons(365); $hit = array_values(array_filter($rows, fn($r) => $r['reason'] === $reasons[$rk]));
    t_ok($hit && (int)$hit[0]['n'] >= 1, 'DEF-SAL-025 · "Why we lose" counts the lost lead under "' . $reasons[$rk] . '"');
    $g = $drive($BDM, 'crm-dashboard', 'GET', ['days' => 365], [], ['Leads we did not pursue, by reason', $reasons[$rk]]);
    t_ok(!empty($g['has']['Leads we did not pursue, by reason']), '…and the Sales dashboard shows it in "Why we lose"');

    t_section('Sales B-batch — pipelines and the funnel');
    $pc = pipe_create('BB Funnel ' . $tag, 'OPPORTUNITY');
    $pid = (int)($pc['id'] ?? 0); if ($pid) $made['pipes'][] = $pid;
    t_ok($pid > 0, 'a test funnel is made');
    $stages = pipeline_stages($pid);
    // DEF-SAL-032 — the pipeline's "In use" box.
    $stageBoxes = []; foreach ($stages as $s) $stageBoxes[(int)$s['id']] = '1';
    pipe_update($pid, ['pipeline_name' => 'BB Funnel ' . $tag, 'active' => $stageBoxes]);
    t_eq((int)ops_val("SELECT active FROM pipelines WHERE id=?", [$pid]), 0, 'DEF-SAL-032 · unticking "In use" (stage boxes still ticked) retires the pipeline');
    pipe_update($pid, ['pipeline_name' => 'BB Funnel ' . $tag, 'pipe_active' => '1', 'active' => $stageBoxes]);
    t_eq((int)ops_val("SELECT active FROM pipelines WHERE id=?", [$pid]), 1, '…ticking it puts it back in use');
    // DEF-SAL-023 — stages can be put in order.
    pipe_stage_add($pid, ['name' => 'BB Negotiation', 'kind' => 'OPEN', 'probability' => 60, 'sla_days' => 7]);
    $stages = pipeline_stages($pid);
    $neg = null; foreach ($stages as $s) if ($s['name'] === 'BB Negotiation') $neg = (int)$s['id'];
    t_ok($neg && end($stages)['name'] === 'BB Negotiation', 'a new stage lands at the end (after Won / Lost)');
    $post = ['name' => [], 'kind' => [], 'probability' => [], 'sla_days' => [], 'active' => [], 'order' => []];
    foreach ($stages as $i => $s) { $sid = (int)$s['id']; $post['name'][$sid] = $s['name']; $post['kind'][$sid] = $s['kind']; $post['probability'][$sid] = $s['probability']; $post['sla_days'][$sid] = $s['sla_days']; $post['active'][$sid] = '1'; $post['order'][$sid] = $i + 1; }
    $post['order'][$neg] = 2;
    pipe_stages_save($pid, $post);
    $after = array_map(fn($s) => $s['name'], pipeline_stages($pid));
    t_eq($after[1] ?? '', 'BB Negotiation', 'DEF-SAL-023 · Order 2 moves it to second place: ' . implode(' → ', $after));
    $lastKinds = array_map(fn($s) => $s['kind'], array_slice(pipeline_stages($pid), -2));
    t_ok(!in_array('OPEN', $lastKinds, true), '…so Won and Lost are the last stages again');

    // DEF-SAL-016 — no conversion above 100 %.
    $st = pipeline_stages($pid); $open = array_values(array_filter($st, fn($s) => $s['kind'] === 'OPEN'));
    $won = array_values(array_filter($st, fn($s) => $s['kind'] === 'WON'))[0] ?? null;
    $mk = function ($nm, $stage) use ($pid, $tag, $off, &$made) {
        $r = opp_create(['name' => $nm . ' ' . $tag, 'partner_name' => 'BB Cust ' . $tag, 'pipeline_id' => $pid, 'stage_id' => (int)$stage, 'value' => 100000, 'office_id' => $off]);
        if (!empty($r['id'])) $made['opps'][] = (int)$r['id'];
        return (int)($r['id'] ?? 0);
    };
    $a = $mk('BB A', $open[0]['id']); $b = $mk('BB B', $open[count($open) - 1]['id']); $c = $mk('BB C', $open[0]['id']);
    if ($won && $c) opp_move($c, (int)$won['id'], ['won_note' => 'signed'], true);
    $f = crmdash_funnel($pid, 365);
    $over = []; foreach ($f['stages'] ?? [] as $s) if ($s['conv_from_prev'] !== null && $s['conv_from_prev'] > 100) $over[] = $s['stage']['name'] . ' ' . $s['conv_from_prev'] . '%';
    t_ok($a && $b && $c && !$over, 'DEF-SAL-016 · no stage converts above 100% (a deal created in a later stage, and one won straight from the first)' . ($over ? ': ' . implode(', ', $over) : ''));
    $reachedFirst = (int)($f['stages'][0]['reached'] ?? 0);
    t_eq($reachedFirst, 3, '…every deal counts as having entered the first stage');

    t_section('Sales B-batch — screens');
    // DEF-SAL-030 — the History tab on a new deal.
    if ($a) {
        $g = $drive($BDM, 'opportunity', 'GET', ['id' => $a], [], ['data-tab="History"', 'no moves yet']);
        t_ok(!empty($g['has']['data-tab="History"']) && !empty($g['has']['no moves yet']), 'DEF-SAL-030 · a new deal has its History tab ("no moves yet")');
    }
    // DEF-SAL-015 — costing links stay inside the quotation's pop-up.
    $_GET['embed'] = '1'; $_GET['embed_stay'] = '1';
    $html = embed_stamp_forms('<a class="btn" href="/project-costing?id=3&amp;edit=5#add-role">✎ Edit</a> <a href="/quote?id=1">Q</a>');
    unset($_GET['embed'], $_GET['embed_stay']);
    t_ok(strpos($html, 'edit=5&amp;embed=1&amp;embed_stay=1#add-role') !== false && strpos($html, '<a href="/quote?id=1" target="_top">') !== false, 'DEF-SAL-015 · "✎ Edit" on a costing role stays in the pop-up; other links open the whole window');
    t_ok(strpos($html, '<a class="btn" href="/project-costing?id=3&amp;edit=5&amp;embed=1&amp;embed_stay=1#add-role">') !== false, '…the costing link keeps its role and its jump');

    // A quotation for the follow-up and enquiry checks.
    $qpost = ['client_name' => 'BB Client ' . $tag, 'contact_name' => 'Ravi', 'sbu' => 'IND', 'subject' => 'BB batch ' . $tag, 'office_id' => $off, 'form_once' => 'bb-1',
              'currency' => 'INR', 'validity_days' => 30, 'gst_pct' => 18, 'l_desc' => ['Stage inspection'], 'l_qty' => ['1'], 'l_rate' => ['10000'], 'l_unit' => ['MAN_DAY'], 'l_service' => [''], 'l_order' => [''], 'l_sbu' => ['IND']];
    $drive($BDM, 'quote-new', 'POST', [], $qpost);
    $qid = (int)ops_val("SELECT MAX(id) FROM quotations WHERE subject=?", ['BB batch ' . $tag]); if ($qid) $made['quotes'][] = $qid;
    // DEF-SAL-033 — an empty follow-up.
    $n0 = (int)ops_val("SELECT COUNT(*) FROM quote_followups WHERE quote_id=?", [$qid]);
    $drive($BDM, 'quote-followup', 'POST', ['id' => $qid], ['do' => 'add', 'due_date' => date('Y-m-d'), 'note' => '']);
    t_eq((int)ops_val("SELECT COUNT(*) FROM quote_followups WHERE quote_id=?", [$qid]), $n0, 'DEF-SAL-033 · "+ Add" with no note saves no follow-up');
    $drive($BDM, 'quote-followup', 'POST', ['id' => $qid], ['do' => 'add', 'due_date' => date('Y-m-d', strtotime('-2 day')), 'note' => 'Call Ravi about the PO']);
    t_eq((int)ops_val("SELECT COUNT(*) FROM quote_followups WHERE quote_id=?", [$qid]), $n0 + 1, '…with a note it is added');

    // DEF-SAL-021 — Your next actions carry the salesperson's own follow-ups.
    $pdo->prepare("UPDATE quotations SET owner_id=?, status='SENT' WHERE id=?")->execute([$BDM, $qid]);
    $l2 = lead_create(['company_name' => 'BB Chase ' . $tag, 'next_action' => 'send the brochure', 'next_action_on' => date('Y-m-d', strtotime('-1 day'))]);
    if (!empty($l2['id'])) { $made['leads'][] = (int)$l2['id']; $pdo->prepare("UPDATE leads SET owner_user_id=? WHERE id=?")->execute([$BDM, (int)$l2['id']]); }
    $pdo->prepare("INSERT INTO crm_inquiries (inquiry_no, subject, client_name, status, assigned_to, created_at, created_by) VALUES (?,?,?, 'OPEN', ?, ?, ?)")
        ->execute(['BBQ-' . $tag, 'BB enquiry ' . $tag, 'BB Client ' . $tag, $BDM, date('c'), 'test']);
    $made['inq'][] = (int)$pdo->lastInsertId();
    $prevUid = $_SESSION['uid'] ?? null; $_SESSION['uid'] = $BDM; current_user(true);
    $items = function_exists('action_centre_sales') ? action_centre_sales(date('Y-m-d'), date('Y-m-d', strtotime('+3 day'))) : [];
    $titles = implode(' | ', array_map(fn($i) => $i['title'], $items));
    t_ok(strpos($titles, 'Follow up BB Chase ' . $tag) !== false, 'DEF-SAL-021 · an overdue lead follow-up is in Your next actions');
    t_ok(strpos($titles, 'assigned to you, not quoted yet') !== false, '…an enquiry assigned to them with no quotation is');
    t_ok(strpos($titles, 'Chase BB Client ' . $tag) !== false, '…and an overdue chase on their sent quotation is: ' . $titles);

    // DEF-SAL-024 — the enquiry cards open lists that match their counts.
    $pdo->prepare("UPDATE crm_inquiries SET status='WON' WHERE id=?")->execute([end($made['inq'])]);
    $g = $drive($BDM, 'inquiries', 'GET', ['st' => 'QUOTED_ANY', 'fy' => 'all', 'q' => 'BBQ-' . $tag], [], ['BBQ-' . $tag, 'st=QUOTED_ANY', 'st=LOST_DROPPED']);
    t_ok(!empty($g['has']['BBQ-' . $tag]), 'DEF-SAL-024 · the "Quoted" card\'s list includes an enquiry that was quoted and won');
    t_ok(!empty($g['has']['st=QUOTED_ANY']) && !empty($g['has']['st=LOST_DROPPED']), '…the Quoted and Lost · Dropped cards link to their own groups');
} finally {
    foreach ($made['opps'] as $id) { try { $pdo->prepare("DELETE FROM opportunity_stage_history WHERE opportunity_id=?")->execute([$id]); $pdo->prepare("DELETE FROM opportunities WHERE id=?")->execute([$id]); } catch (Throwable $e) {} }
    foreach ($made['pipes'] as $id) { try { $pdo->prepare("DELETE FROM pipeline_stages WHERE pipeline_id=?")->execute([$id]); $pdo->prepare("DELETE FROM pipelines WHERE id=?")->execute([$id]); } catch (Throwable $e) {} }
    foreach ($made['quotes'] as $id) foreach (['quote_followups', 'quote_lines', 'quote_locations', 'quote_log'] as $tb) { try { $pdo->prepare("DELETE FROM $tb WHERE quote_id=?")->execute([$id]); } catch (Throwable $e) {} }
    foreach ($made['quotes'] as $id) { try { $pdo->prepare("DELETE FROM quotations WHERE id=?")->execute([$id]); } catch (Throwable $e) {} }
    foreach ($made['inq'] as $id) { try { $pdo->prepare("DELETE FROM crm_inquiries WHERE id=?")->execute([$id]); } catch (Throwable $e) {} }
    foreach ($made['leads'] as $id) { try { $pdo->prepare("DELETE FROM leads WHERE id=?")->execute([$id]); } catch (Throwable $e) {} }
    try { $pdo->prepare("DELETE FROM leads WHERE company_name LIKE ?")->execute(['BB % ' . $tag]); } catch (Throwable $e) {}
    if (isset($prevUid)) { if ($prevUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prevUid; current_user(true); }
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$BDM]);
    $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$off]);
}
