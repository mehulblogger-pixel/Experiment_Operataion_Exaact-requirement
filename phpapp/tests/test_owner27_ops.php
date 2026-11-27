<?php
// Owner, 27 Nov 2026 — decisions on the 27 Nov test results ("My decisions … 3. Ok, 4. A ok").
//
//   O1 (decision 3, step one)  Closing a job with NO report on file warns and requires a
//                              reason; the reason goes to the job's History. A report that
//                              arrives after such a close is accepted, recorded, and the
//                              branch's Operations Manager is told. No new status / approval.
//   O2 (QP-16 / A6)            On a phone every tab bar is an even grid — no sideways
//                              scrolling, equal cells (CSS; checked here as the rule).
//   O3 (decision 4a)           The Branch Manager and the SBU Head READ enquiries (open,
//                              download) but no longer create, edit, assign, drop or delete them.
//   O4 (GD-03)                 A deleted lead / deal / enquiry typed by company name only is
//                              also filed on the client whose name matches exactly one on file.
t_section('Owner 27 Nov 2026 — closing without a report, phone tabs, read-only enquiries, deletions (O1–O4)');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$pdo = db();
$made = ['l' => [], 'o' => [], 'i' => [], 'p' => [], 'j' => [], 'r' => [], 'u' => []];
putenv('BBV_RAW=1');
$refused = fn($r) => !empty($r['restricted']) || (int) ($r['code'] ?? 0) === 403
                    || (bool) array_filter($r['flash'] ?? [], fn($x) => stripos($x, 'error') === 0);
$html = fn($r) => (string) ($r['html'] ?? $r['out'] ?? '');
try {
    bbv_as($f['master']);
    leads_migrate(); opp_migrate();
    if (function_exists('crm_migrate')) crm_migrate();
    if (function_exists('act_migrate')) act_migrate();
    if (function_exists('idems_migrate')) idems_migrate();
    job_noreport_migrate();

    // ================================================================ O1 · close without a report
    $pdo->prepare("UPDATE users SET email=? WHERE id=?")->execute([$f['tag'] . '.om@example.test', $f['sunil']]);   // Mumbai Operation Manager
    $mkJob = function ($code, $freq = 'DAILY', $extra = []) use ($pdo, $f, &$made) {
        $pdo->prepare("INSERT INTO jobs (job_code, executing_office_id, reporting_frequency, deliverables, inspection_end_date, created_at) VALUES (?,?,?,?,?,?)")
            ->execute([$code, $f['mum'], $freq, '', date('Y-m-d'), date('c')]);
        $id = (int) $pdo->lastInsertId(); $made['j'][] = $id;
        foreach ($extra as $k => $v) $pdo->prepare("UPDATE jobs SET $k=? WHERE id=?")->execute([$v, $id]);
        return ops_one("SELECT * FROM jobs WHERE id=?", [$id]);
    };
    $j1 = $mkJob($f['tag'] . '-NR1');
    t_ok(!job_report_on_file($j1), 'O1 · a job with no report written, uploaded or linked has no report on file');
    t_ok(job_close_needs_report_reason($j1), 'O1 · …so closing it needs a reason');
    t_ok(job_report_on_file($j1, 'https://drive.example/report.pdf'), 'O1 · a report link typed on the close form counts as on file');
    t_ok(job_report_on_file($mkJob($f['tag'] . '-NR0', 'NOREPORT')), 'O1 · a job agreed with "No report" never needs one');
    t_ok(!job_close_needs_report_reason($mkJob($f['tag'] . '-NRT', 'DAILY', ['billing_stop_kind' => 'TERMINATED'])), 'O1 · a terminated job already says why on its record — not asked again');

    $r = bbv_route('job-close', 'GET', $f['neha'], (int) $j1['id']);
    $h = $html($r);
    t_ok(strpos($h, 'No report is on file for this') !== false && strpos($h, 'name="no_report_reason"') !== false,
         'O1 · the close form warns "No report is on file" and asks for the reason');
    $r = bbv_route('job-close', 'POST', $f['neha'], (int) $j1['id'], ['report_upload_date' => date('Y-m-d'), 'no_report_reason' => '']);
    t_eq((int) ops_val("SELECT closed_flag FROM jobs WHERE id=?", [$j1['id']]), 0, 'O1 · closing with no reason is refused — the job stays open');
    t_ok(strpos($html($r), 'say why') !== false, 'O1 · …and the page says to give a reason (or paste the report link)');
    $r = bbv_route('job-close', 'POST', $f['neha'], (int) $j1['id'], ['report_upload_date' => date('Y-m-d'), 'no_report_reason' => 'short']);
    t_eq((int) ops_val("SELECT closed_flag FROM jobs WHERE id=?", [$j1['id']]), 0, 'O1 · a reason shorter than ' . JOB_NOREPORT_REASON_MIN . ' characters is refused too');
    $why = 'Client cancelled the inspection on site — no report to issue';
    $r = bbv_route('job-close', 'POST', $f['neha'], (int) $j1['id'], ['report_upload_date' => date('Y-m-d'), 'no_report_reason' => $why]);
    $j1 = ops_one("SELECT * FROM jobs WHERE id=?", [$j1['id']]);
    t_eq((int) $j1['closed_flag'], 1, 'O1 · with a reason the job closes');
    t_eq((string) $j1['closed_no_report_reason'], $why, 'O1 · the reason is kept on the job');
    t_ok((string) $j1['closed_no_report_by'] !== '' && (string) $j1['closed_no_report_at'] !== '', 'O1 · …with who and when');
    t_eq((string) $j1['report_upload_date'], '', 'O1 · no report date is recorded for a report that does not exist');
    t_eq((string) $j1['report_approval'], '', 'O1 · and no report sign-off is asked for');
    $a = ops_one("SELECT * FROM activities WHERE entity_kind='JOB' AND entity_id=? AND subject LIKE 'Closed without a report%' ORDER BY id DESC LIMIT 1", [$j1['id']]);
    t_ok($a && strpos((string) $a['body'], $why) !== false, 'O1 · the job\'s History reads "Closed without a report — by …" with the reason');
    $opsSrc = (string) file_get_contents(dirname(__DIR__) . '/lib/ops.php');
    t_ok(strpos($opsSrc, "UPDATE jobs SET closed_no_report_reason=?, closed_no_report_by=?, closed_no_report_at=? WHERE id=?") !== false
         && strpos($opsSrc, "stage='CLOSED_NO_REPORT'") === false, 'O1 · no new status or stage — only the existing close, plus who / why / when');

    //  A job WITH a report closes as before — no reason asked, report date and sign-off kept.
    $j2 = $mkJob($f['tag'] . '-NR2');
    $pdo->prepare("INSERT INTO report_docs (irn, job_id, type_code, status, deleted, data, created_at) VALUES (?,?,?,?,0,'[]',?)")
        ->execute([$f['tag'] . '-IRN-2', $j2['id'], 'IR', 'DRAFT', date('c')]);
    $made['r'][] = (int) $pdo->lastInsertId();
    t_ok(!job_close_needs_report_reason($j2), 'O1 · a job with a report written on it needs no reason');
    $r = bbv_route('job-close', 'GET', $f['neha'], (int) $j2['id']);
    t_ok(strpos($html($r), 'name="no_report_reason"') === false, 'O1 · …and its close form shows no reason box');
    $r = bbv_route('job-close', 'POST', $f['neha'], (int) $j2['id'], ['report_upload_date' => date('Y-m-d')]);
    $j2 = ops_one("SELECT * FROM jobs WHERE id=?", [$j2['id']]);
    t_ok((int) $j2['closed_flag'] === 1 && (string) $j2['report_upload_date'] === date('Y-m-d') && (string) $j2['report_approval'] === 'PENDING',
         'O1 · it closes as before, with its report date and the report sign-off');
    t_eq((string) $j2['closed_no_report_reason'], '', 'O1 · nothing is recorded as "without a report"');

    //  A report added AFTER the no-report close: recorded and the Operations Manager told.
    $pdo->exec("DELETE FROM email_log WHERE kind='report_after_noreport_close'");
    $pdo->prepare("INSERT INTO report_docs (irn, job_id, type_code, status, deleted, data, created_at) VALUES (?,?,?,?,0,'[]',?)")
        ->execute([$f['tag'] . '-IRN-LATE', $j1['id'], 'IR', 'DRAFT', date('c')]);
    $late = (int) $pdo->lastInsertId(); $made['r'][] = $late;
    bbv_as($f['neha']);
    t_ok(job_report_added_after_noreport_close($late), 'O1 · a report written after a no-report close is accepted and noticed');
    $a = ops_one("SELECT * FROM activities WHERE entity_kind='JOB' AND entity_id=? AND subject LIKE 'Report %uploaded after closing without a report%' ORDER BY id DESC LIMIT 1", [$j1['id']]);
    t_ok($a && strpos((string) $a['subject'], $f['tag'] . '-IRN-LATE') !== false && strpos((string) $a['subject'], '— by ') !== false,
         'O1 · History: "Report … uploaded after closing without a report — by …"');
    $m = ops_one("SELECT * FROM email_log WHERE kind='report_after_noreport_close' ORDER BY id DESC LIMIT 1");
    t_ok($m && strpos((string) $m['to_addr'], $f['tag'] . '.om@example.test') !== false, 'O1 · the Mumbai Operations Manager is e-mailed');
    t_ok($m && strpos((string) $m['body'], $why) !== false, 'O1 · …with the reason given at the close');
    $pdo->prepare("INSERT INTO report_docs (irn, job_id, type_code, status, deleted, data, created_at) VALUES (?,?,?,?,0,'[]',?)")
        ->execute([$f['tag'] . '-IRN-3', $j2['id'], 'IR', 'DRAFT', date('c')]);
    $other = (int) $pdo->lastInsertId(); $made['r'][] = $other;
    t_ok(!job_report_added_after_noreport_close($other), 'O1 · a second report on a job closed normally raises nothing');
    bbv_as($f['master']);
    $src = (string) file_get_contents(dirname(__DIR__) . '/lib/idems.php');
    t_eq(preg_match_all('/^\s+job_report_added_after_noreport_close\(\$/m', $src), 4, 'O1 · every way a report document is written on a job (new, next stage, revision, Release Note) checks it');
    $r = bbv_route('job', 'GET', $f['neha'], (int) $j1['id']);
    t_ok(strpos($html($r), 'Closed without a report by') !== false, 'O1 · the closed job\'s page shows that it was closed without a report, and why');

    // ================================================================ O2 · phone tab grid
    $css = (string) file_get_contents(dirname(__DIR__) . '/assets/css/app.css');
    t_ok(preg_match('#@media \(max-width:599\.98px\)\{\s*\.tabbar:not\(\.rec-tabs\)\{display:grid;grid-template-columns:repeat\(3,minmax\(0,1fr\)\)#', $css) === 1,
         'O2 · below 600px a tab bar is an even three-column grid (six tabs = 3 × 2, five = 3 + 2)');
    t_ok(strpos($css, '.tabbar:not(.rec-tabs):has(> .tabbtn:nth-child(4):last-child){grid-template-columns:repeat(2,minmax(0,1fr))}') !== false
         || strpos($css, '.tabbar:not(.rec-tabs):has(> .tabbtn:nth-child(4):last-child)') !== false,
         'O2 · two or four tabs use two columns, so no row has one lonely cell');
    t_ok(preg_match('#\.tabbar:not\(\.rec-tabs\) \.tabbtn\{width:100%;[^}]*white-space:normal;[^}]*justify-content:center;[^}]*text-align:center#', $css) === 1,
         'O2 · every cell fills its column, centres its label and lets it take a second line (nothing cut off)');

    // ================================================================ O3 · read-only enquiries
    $mkInq = function ($office, $client = '') use ($pdo, $f, &$made) {
        $no = 'INQ-' . $f['tag'] . '-' . mt_rand(1000, 99999);
        $pdo->prepare("INSERT INTO crm_inquiries (inquiry_no, client_name, subject, status, office_id, sbu, received_date, created_at) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$no, $client ?: 'O3 Client', 'O3 enquiry', 'OPEN', $office, 'IND', date('Y-m-d'), date('c')]);
        $id = (int) $pdo->lastInsertId(); $made['i'][] = $id; return $id;
    };
    $pdo->prepare("UPDATE users SET scope_sbus='IND' WHERE id=?")->execute([$f['sbu']]);
    $bdm = (function () use ($pdo, $f, &$made) {
        $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id, scope_offices) VALUES (?,?,?,?,1,?,'')")
            ->execute([$f['tag'] . '_bdm', 'Bdm', 'T', 'BUSINESS_DEV_MANAGER', $f['mum']]);
        return (int) $pdo->lastInsertId();
    })();
    $i1 = $mkInq($f['mum']);
    foreach (['kiran' => 'Branch Manager', 'sbu' => 'SBU Head'] as $k => $who) {
        bbv_as($f[$k]);
        t_ok(can('mod.inquiries.view'), "O3 · $who still opens enquiries");
        t_ok(!crm_inquiry_can_write(), "O3 · $who no longer changes enquiries");
        $r = bbv_route('inquiries', 'GET', $f[$k], 0);
        t_ok(!$refused($r), "O3 · $who · the enquiry register opens");
        t_ok(strpos($html($r), 'href="/inquiry-new"') === false, "O3 · $who · no \"+ New enquiry\" button");
        t_ok(strpos($html($r), 'action="/inquiry-delete"') === false, "O3 · $who · no Delete on the register");
        $r = bbv_route('inquiries', 'GET', $f[$k], 0, [], ['csv' => '1']);
        t_ok(!$refused($r), "O3 · $who · the register still downloads");
        $r = bbv_route('inquiry', 'GET', $f[$k], $i1);
        t_ok(!$refused($r) && strpos($html($r), 'href="/inquiry-edit?id=' . $i1 . '"') === false && strpos($html($r), 'action="/inquiry-drop"') === false,
             "O3 · $who · the enquiry page opens with no Edit and no Drop");
        foreach (['inquiry-new' => [0, 'GET'], 'inquiry-edit' => [$i1, 'GET']] as $rt => [$id, $m])
            t_ok($refused(bbv_route($rt, $m, $f[$k], $id)), "O3 · $who · typing /$rt is refused");
        $r = bbv_route('inquiry-edit', 'POST', $f[$k], $i1, ['subject' => 'changed by ' . $k, 'client_name' => 'O3 Client']);
        t_ok($refused($r) && (string) ops_val("SELECT subject FROM crm_inquiries WHERE id=?", [$i1]) === 'O3 enquiry', "O3 · $who · a posted edit is refused and nothing changes");
        $r = bbv_route('inquiry-drop', 'POST', $f[$k], 0, ['id' => $i1, 'drop_reason' => array_key_first(INQUIRY_DROP_REASONS)]);
        t_ok($refused($r) && (string) ops_val("SELECT status FROM crm_inquiries WHERE id=?", [$i1]) === 'OPEN', "O3 · $who · Drop is refused");
        $r = bbv_route('inquiry-delete', 'POST', $f[$k], 0, ['id' => $i1]);
        t_eq((int) ops_val("SELECT COUNT(*) FROM crm_inquiries WHERE id=?", [$i1]), 1, "O3 · $who · Delete is refused (the enquiry is still there)");
    }
    bbv_as($bdm);
    t_ok(crm_inquiry_can_write(), 'O3 · a Business Development Manager still works enquiries');
    $r = bbv_route('inquiries', 'GET', $bdm, 0);
    t_ok(strpos($html($r), 'href="/inquiry-new"') !== false, 'O3 · …and sees "+ New enquiry"');
    t_ok(!$refused(bbv_route('inquiry-new', 'GET', $bdm, 0)), 'O3 · …and opens the new-enquiry form');
    bbv_as($f['bd']);
    t_ok(crm_inquiry_can_write() === can('mod.inquiries.edit'), 'O3 · the Business Director keeps exactly what they had');
    bbv_as($f['admin']);
    t_ok(crm_inquiry_can_write(), 'O3 · an administrator keeps it');
    t_ok(strpos((string) file_get_contents(dirname(__DIR__) . '/../docs/02-permission-matrix.md'), '19ac') !== false, 'O3 · docs/02 records the rule (19ac)');

    // ================================================================ O4 · deletions filed on the matching client
    bbv_as($f['master']);
    $nm = 'Sunrise Valves ' . $f['tag'];
    $pdo->prepare("INSERT INTO business_partners (code, legal_name, display_name, is_client, is_vendor, status, created_at) VALUES (?,?,?,1,0,'ACTIVE',?)")
        ->execute([$f['tag'] . 'SV', $nm . ' Pvt Ltd', $nm . ' Pvt Ltd', date('c')]);
    $pid = (int) $pdo->lastInsertId(); $made['p'][] = $pid;
    t_eq(act_partner_match_name($nm), $pid, 'O4 · "' . $nm . '" matches "' . $nm . ' Pvt Ltd" (Pvt Ltd left out, as for duplicates)');
    //  A lead typed by name only (no client chosen), deleted.
    $l = lead_create(['company_name' => strtoupper($nm) . ' PRIVATE LIMITED', 'contact_phone' => '9825077001', 'source' => 'REFERRAL']);
    $pdo->prepare("UPDATE leads SET office_id=?, partner_id=NULL WHERE id=?")->execute([$f['mum'], $l['id']]);
    bbv_route('lead-delete', 'POST', $f['master'], 0, ['id' => (int) $l['id']]);
    $a = ops_one("SELECT * FROM activities WHERE entity_kind='LEAD' AND entity_id=? ORDER BY id DESC LIMIT 1", [$l['id']]);
    t_ok($a && $a['kind'] === 'DELETED' && (int) $a['partner_id'] === $pid, 'O4 · a deleted lead with no client link is filed on the matching client');
    $tl = c360_timeline($pid, 50);
    t_ok((bool) array_filter($tl, fn($x) => $x['kind'] === 'DELETED' && (int) $x['entity_id'] === (int) $l['id']), 'O4 · …and shows on that client\'s 360 timeline');
    $r = bbv_route('customer360', 'GET', $f['master'], $pid);
    if (strpos($html($r), 'timeline') !== false || $html($r) !== '')
        t_ok(strpos($html($r), '>Deleted<') !== false, 'O4 · …with the "Deleted" label');
    //  A deal with only a typed client name, deleted.
    $pdo->prepare("INSERT INTO opportunities (ref, name, partner_name, office_id, sbu, status, created_at) VALUES (?,?,?,?,?,?,?)")
        ->execute(['DEAL-' . $f['tag'], 'O4 deal', $nm, $f['mum'], 'IND', 'OPEN', date('c')]);
    $oid = (int) $pdo->lastInsertId(); $made['o'][] = $oid;
    bbv_route('opportunity-delete', 'POST', $f['master'], 0, ['id' => $oid]);
    $a = ops_one("SELECT * FROM activities WHERE entity_kind='OPPORTUNITY' AND entity_id=? ORDER BY id DESC LIMIT 1", [$oid]);
    t_ok($a && $a['kind'] === 'DELETED' && (int) $a['partner_id'] === $pid, 'O4 · a deleted deal with only a typed client name is filed on the client');
    //  An enquiry with only a typed client name, deleted.
    $iq = $mkInq($f['mum'], $nm . ' Ltd');
    bbv_route('inquiry-delete', 'POST', $f['admin'], 0, ['id' => $iq]);
    $a = ops_one("SELECT * FROM activities WHERE entity_kind='INQUIRY' AND entity_id=? ORDER BY id DESC LIMIT 1", [$iq]);
    t_ok($a && $a['kind'] === 'DELETED' && (int) $a['partner_id'] === $pid, 'O4 · a deleted enquiry with only a typed client name is filed on the client');
    //  No match, or two clients of the same name: Directory → Activity only.
    $l = lead_create(['company_name' => 'Nobody On File ' . $f['tag'], 'contact_phone' => '9825077002', 'source' => 'REFERRAL']);
    $pdo->prepare("UPDATE leads SET office_id=? WHERE id=?")->execute([$f['mum'], $l['id']]);
    bbv_route('lead-delete', 'POST', $f['master'], 0, ['id' => (int) $l['id']]);
    $a = ops_one("SELECT * FROM activities WHERE entity_kind='LEAD' AND entity_id=? ORDER BY id DESC LIMIT 1", [$l['id']]);
    t_ok($a && $a['kind'] === 'DELETED' && $a['partner_id'] === null, 'O4 · no client of that name: kept in Directory → Activity only');
    $pdo->prepare("INSERT INTO business_partners (code, legal_name, display_name, is_client, is_vendor, status, created_at) VALUES (?,?,?,1,0,'ACTIVE',?)")
        ->execute([$f['tag'] . 'SV2', $nm . ' Limited', '', date('c')]);
    $made['p'][] = (int) $pdo->lastInsertId();
    t_eq(act_partner_match_name($nm), null, 'O4 · two clients carry that name: no guess is made');
    $l = lead_create(['company_name' => $nm, 'contact_phone' => '9825077003', 'source' => 'REFERRAL']);
    $pdo->prepare("UPDATE leads SET office_id=? WHERE id=?")->execute([$f['mum'], $l['id']]);
    bbv_route('lead-delete', 'POST', $f['master'], 0, ['id' => (int) $l['id']]);
    $a = ops_one("SELECT * FROM activities WHERE entity_kind='LEAD' AND entity_id=? ORDER BY id DESC LIMIT 1", [$l['id']]);
    t_ok($a && $a['partner_id'] === null, 'O4 · …so the deletion stays in Directory → Activity only');
    //  A lead linked to a client keeps its own link (unchanged).
    $l = lead_create(['company_name' => 'Linked ' . $f['tag'], 'contact_phone' => '9825077004', 'source' => 'REFERRAL']);
    $pdo->prepare("UPDATE leads SET office_id=?, partner_id=? WHERE id=?")->execute([$f['mum'], $pid, $l['id']]);
    bbv_route('lead-delete', 'POST', $f['master'], 0, ['id' => (int) $l['id']]);
    $a = ops_one("SELECT * FROM activities WHERE entity_kind='LEAD' AND entity_id=? ORDER BY id DESC LIMIT 1", [$l['id']]);
    t_ok($a && (int) $a['partner_id'] === $pid, 'O4 · a lead linked to a client is filed on it, as before');
    t_section('Reports to write — an open-ended daily job that starts on a day off (found 27 Nov)');
    $sun = ['id' => 0, 'reporting_frequency' => 'DAILY', 'scheduled_date' => '2026-11-22', 'inspection_end_date' => '', 'closed_flag' => 0, 'stage' => '', 'report_upload_date' => ''];
    if (is_working_day('2026-11-22', null)) t_ok(true, 'Sunday is a working day here — nothing to check');
    else {
        $o = ops_report_obligations($sun, '2026-11-27', ['reports' => [], 'visits' => []]);
        t_eq((int) $o['missing'], 1, 'a daily job starting on Sunday 22 Nov still owes one report (before: none)');
        t_eq((string) $o['oldest_missing'], '2026-11-23', 'the owed day is the first working day, Monday 23 Nov');
        $mon = ops_report_obligations(['scheduled_date' => '2026-11-23'] + $sun, '2026-11-27', ['reports' => [], 'visits' => []]);
        t_eq((string) $mon['oldest_missing'], '2026-11-23', 'a Monday start is unchanged');
        $early = ops_report_obligations($sun, '2026-11-22', ['reports' => [], 'visits' => []]);
        t_eq((int) $early['missing'], 0, 'on the Sunday itself nothing is owed yet');
    }
} finally {
    putenv('BBV_RAW');
    $pdo = db();
    if ($made['o']) $pdo->exec("DELETE FROM opportunities WHERE id IN (" . implode(',', array_map('intval', $made['o'])) . ")");
    if ($made['i']) $pdo->exec("DELETE FROM crm_inquiries WHERE id IN (" . implode(',', array_map('intval', $made['i'])) . ")");
    if ($made['r']) $pdo->exec("DELETE FROM report_docs WHERE id IN (" . implode(',', array_map('intval', $made['r'])) . ")");
    if ($made['j']) $pdo->exec("DELETE FROM jobs WHERE id IN (" . implode(',', array_map('intval', $made['j'])) . ")");
    if ($made['p']) $pdo->exec("DELETE FROM business_partners WHERE id IN (" . implode(',', array_map('intval', $made['p'])) . ")");
    $pdo->prepare("DELETE FROM leads WHERE company_name LIKE ?")->execute(['%' . $f['tag'] . '%']);
    bbv_cleanup($f);
}
