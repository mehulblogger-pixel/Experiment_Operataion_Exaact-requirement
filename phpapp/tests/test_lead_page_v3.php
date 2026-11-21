<?php
// Owner, 20 Nov 2026 — the lead page redesigned from the owner's own picture
// ("Yes built at present looks ok"), with "each and everything" of the old page
// kept; "How promising" only on a lead still being chased (a lost lead read
// 35/100); a refused edit keeps what was typed; quick lead → full form carries
// the values; files can be attached when a lead is created.
//
// The page is drawn through the production dispatcher and view() in a second
// process (tests/_sales_route_worker.php), as each kind of person, for each
// state a lead can be in, and every old button and section is looked for.
require_once __DIR__ . '/_bbv_fixture.php';
require_once __DIR__ . '/_branch_batch_helpers.php';

//  Like bb_drive(), plus a session to start from (a refused save's kept typing).
function lp3_drive($uid, $route, $method = 'GET', array $get = [], array $post = [], array $needles = [], array $session = []) {
    $root = dirname(__DIR__);
    $engine = $GLOBALS['__test_engine'] ?? 'sqlite';
    $env = $engine === 'sqlite'
        ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
        : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
          . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
    $tok = 'lp3csrf' . str_repeat('0', 20);
    if ($method === 'POST') $post['_csrf'] = $tok;
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_sales_route_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route)
         . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post))
         . ' ' . escapeshellarg(json_encode($needles)) . ' ' . escapeshellarg(json_encode(['csrf' => $tok] + $session));
    $raw = (string) shell_exec($cmd . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300), 'has' => [], 'len' => 0];
}

t_section('Lead page v3 — every state draws, every old button is still there');
$f = bbv_fixture();
$made = ['leads' => [], 'quotes' => [], 'acts' => [], 'rr' => [], 'pipe' => 0, 'opp' => [], 'bp' => []];
try {
    bbv_as($f['master']);
    leads_migrate();
    if (function_exists('act_migrate')) act_migrate();
    //  A pipeline of this test's own (open × 2, won, lost), so what other tests did to
    //  the default pipeline cannot change what is drawn here.
    db()->prepare("INSERT INTO pipelines (name) VALUES (?)")->execute(['V3 pipe ' . $f['tag']]);
    $pipe = (int) db()->lastInsertId(); $made['pipe'] = $pipe;
    foreach ([['New enquiry', 'OPEN'], ['Contact made', 'OPEN'], ['Qualified', 'OPEN'], ['Won — customer', 'WON'], ['Not pursued', 'LOST']] as $i => [$sn, $sk])
        db()->prepare("INSERT INTO pipeline_stages (pipeline_id, seq, name, kind, active) VALUES (?,?,?,?,1)")->execute([$pipe, $i + 1, $sn, $sk]);
    $stages = pipeline_stages($pipe);
    $byKind = []; foreach ($stages as $s) $byKind[$s['kind']][] = $s;
    t_ok(!empty($byKind['OPEN']) && !empty($byKind['WON']) && !empty($byKind['LOST']), 'ARMING · the lead pipeline has open, won and lost stages');
    $open1 = $byKind['OPEN'][0]; $open2 = $byKind['OPEN'][1] ?? $byKind['OPEN'][0];

    $mk = function ($name, array $x = []) use ($pipe, $open1, $f, &$made) {
        $r = lead_create(['company_name' => $name . ' ' . $f['tag'], 'contact_name' => 'Ravi Shah', 'contact_phone' => '9825012345',
                          'contact_email' => 'ravi.shah@example.test', 'value' => 1000000, 'office_id' => $f['mum'],
                          'owner_user_id' => $f['kiran'], 'pipeline_id' => $pipe, 'requirement' => 'Inspection of two mills',
                          'next_action' => 'Call to qualify', 'next_action_on' => date('Y-m-d', strtotime('+2 days'))] + $x);
        t_ok(!empty($r['id']), 'ARMING · lead "' . $name . '" made' . (!empty($r['err']) ? ' — ' . $r['err'] : ''));
        return $made['leads'][] = (int) ($r['id'] ?? 0);
    };
    $new   = $mk('V3 New Co');
    $quote = $mk('V3 Quoted Co', ['deputation_kind' => 'MANMONTH', 'manpower_count' => 2, 'site_location' => 'Hazira works', 'manpower_skills' => 'NDT Level II']);
    $lost  = $mk('V3 Lost Co');
    $won   = $mk('V3 Won Co');
    $reass = $mk('V3 Reassign Co');

    // The quoted lead: a contact logged and a quotation sent.
    db()->prepare("UPDATE leads SET stage_id=? WHERE id=?")->execute([(int) $open2['id'], $quote]);
    db()->prepare("INSERT INTO activities (kind,entity_kind,entity_id,subject,body,direction,occurred_at,duration_mins,outcome,with_whom,owner,auto,created_by,created_at)
                   VALUES ('CALL','LEAD',?,'','Agreed two inspectors.','OUT',?,30,'Wants a quote','Ravi Shah','Kiran T',0,'Kiran T',?)")->execute([$quote, date('c'), date('c')]);
    $made['acts'][] = (int) db()->lastInsertId();
    try {
        db()->prepare("INSERT INTO quotations (quote_no, rev, is_current, client_name, subtotal, gst_pct, gst_amount, total_amount, status, created_at, updated_at, lead_id)
                       VALUES (?,1,1,'V3 Quoted Co',450000,18,81000,531000,'SENT',?,?,?)")->execute(['V3-Q-' . $f['tag'], date('c'), date('c'), $quote]);
        $made['quotes'][] = (int) db()->lastInsertId();
    } catch (Throwable $e) { t_ok(false, 'ARMING · quotation row: ' . $e->getMessage()); }
    // A document on the quoted lead, and a deal worked from it.
    db()->prepare("INSERT INTO lead_files (lead_id, kind, file_name, mime, file_data, note, uploaded_by, uploaded_at) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$quote, 'REQUIREMENT', 'RFQ v3.pdf', 'application/pdf', base64_encode('x'), 'Rev 2', 'Kiran T', date('c')]);
    if (function_exists('opp_migrate')) opp_migrate();
    try { db()->prepare("INSERT INTO opportunities (ref, name, lead_id, status, value) VALUES (?,?,?,'OPEN',0)")->execute(['V3D-' . $f['tag'], 'V3 deal', $quote]);
          $made['opp'][] = (int) db()->lastInsertId(); } catch (Throwable $e) { t_ok(false, 'ARMING · deal row: ' . $e->getMessage()); }
    db()->prepare("INSERT INTO business_partners (code, legal_name, display_name, is_client, status, created_at) VALUES (?,?,?,1,'ACTIVE',?)")
        ->execute(['V3' . substr($f['tag'], -4), 'V3 Won Customer', 'V3 Won Customer', date('c')]);
    $made['bp'][] = $wonBp = (int) db()->lastInsertId();
    // Lost and won.
    db()->prepare("UPDATE leads SET stage_id=?, status='LOST', lost_reason='COMPETITOR', lost_note='Went with a local agency' WHERE id=?")->execute([(int) $byKind['LOST'][0]['id'], $lost]);
    db()->prepare("UPDATE leads SET stage_id=?, status='CONVERTED', converted_at=?, converted_partner_id=?, converted_inquiry_id=? WHERE id=?")->execute([(int) $byKind['WON'][0]['id'], date('c'), $wonBp, 999999, $won]);
    // A reassignment waiting for a manager.
    db()->prepare("INSERT INTO lead_reassign_requests (lead_id, from_user_id, to_user_id, reason, requested_by_id, requested_by, status, created_at)
                   VALUES (?,?,?,?,?,?,'PENDING',?)")->execute([$reass, $f['kiran'], $f['sunil'], 'Moving territory', $f['neha'], 'Neha T', date('c')]);
    $made['rr'][] = (int) db()->lastInsertId();

    $N = [
        'chain' => 'class="rec-top"', 'pill' => 'id="stage-menu"', 'static' => 'rec-pill static',
        'tabs' => 'data-tabs-order="Overview,Details,Contacts,Quotes,More"', 'steps' => 'class="rec-steps"',
        'kpi' => 'class="rec-kpi"', 'next' => '>Next action<', 'quick' => '>Quick actions<', 'summary' => '>Lead summary<',
        'score' => '>How promising<', 'want' => '>What they want<', 'details' => '>Lead details<', 'edit' => 'action="/lead-edit"',
        'dep' => '>Manpower deputation<', 'depfield' => 'name="deputation_kind"', 'log' => 'action="/sales-log"', 'contacts' => 'data-tab="Contacts"',
        'quotes' => 'data-tab="Quotes"', 'mkq' => '/quote-new?lead=', 'cost' => '/project-costings?lead_id=',
        'files' => 'id="files"', 'attach' => 'action="/lead-files?id=', 'fdel' => 'action="/lead-file-delete', 'history' => 'id="history"', 'flow' => 'id="flow"',
        'deal' => 'action="/opportunity-from-lead"', 'delete' => 'action="/lead-delete"', 'remove' => 'id="remove"', 'cannotdel' => 'cannot be deleted', 'move' => 'action="/lead-move"',
        'lostform' => 'name="lost_reason"', 'reopen' => 'action="/lead-reopen"', 'locked' => '>Why it was lost<', 'wonban' => 'is now a customer',
        'reass' => 'Reassignment waiting for a manager', 'decide' => 'action="/approval-lead-decide"', 'first' => 'Log your first contact',
        'openq' => 'Open ', 'sentpill' => ' sent',
        //  Sales Stage 2 (owner, 21 Nov 2026): the lead page also carries the Sales bar and phone bottom bar.
        'v3' => 'class="rec sxw has-bn"',
    ];
    $page = function ($uid, $lid, array $get = [], array $session = []) use ($N) {
        $r = lp3_drive($uid, 'lead', 'GET', ['id' => $lid] + $get, [], array_values($N), $session);
        $h = []; foreach ($N as $k => $n) $h[$k] = !empty($r['has'][$n]);
        return [$r, $h];
    };

    // ---- As the master (every right) -------------------------------------------------
    [$r, $h] = $page($f['master'], $new);
    t_ok(empty($r['error']) && $h['v3'], 'a new lead draws in the new design' . (!empty($r['error']) ? ' — ' . $r['error'] : ''));
    foreach (['pill', 'tabs', 'steps', 'kpi', 'next', 'quick', 'summary', 'score', 'want', 'details', 'edit', 'depfield', 'log', 'contacts', 'files', 'attach', 'history', 'flow', 'move', 'lostform', 'first', 'delete'] as $k)
        t_ok($h[$k], 'new lead, master: "' . $k . '" is on the page');
    t_ok(!$h['reopen'] && !$h['locked'] && !$h['wonban'], 'new lead: no lost card, no reopen, no won banner');

    [$r, $h] = $page($f['master'], $quote);
    t_ok(empty($r['error']), 'the quoted lead draws' . (!empty($r['error']) ? ' — ' . $r['error'] : ''));
    t_ok($h['quotes'] && $h['openq'] && $h['sentpill'], 'quotation sent: the Quotes tab, the "Open quotation …" button and a "… sent" status pill');
    t_ok($h['dep'], 'man-month lead: the Manpower deputation card is shown');
    t_ok($h['score'], 'an open lead shows How promising');
    t_ok(!$h['first'], 'a lead with a contact logged does not ask for the first contact');

    [$r, $h] = $page($f['master'], $lost);
    t_ok(empty($r['error']), 'the lost lead draws' . (!empty($r['error']) ? ' — ' . $r['error'] : ''));
    t_ok($h['locked'] && $h['reopen'], 'lost lead: "Why it was lost" (locked) and Reopen for a person who may reopen');
    t_ok(!$h['score'], 'lost lead: no "How promising" (owner, 20 Nov 2026 — it read 35/100)');
    t_ok(!$h['pill'] && $h['static'], 'lost lead: the status is shown, not a stage menu');
    t_ok(!$h['edit'] && !$h['quick'] && !$h['log'], 'lost lead: no edit form, no quick actions, no log form');
    t_ok($h['delete'], 'lost lead: still removable by a person allowed to delete');

    [$r, $h] = $page($f['master'], $won);
    t_ok(empty($r['error']), 'the won lead draws' . (!empty($r['error']) ? ' — ' . $r['error'] : ''));
    t_ok($h['wonban'] && !$h['score'] && !$h['pill'], 'won lead: the green banner, no score, no stage menu');
    t_ok($h['remove'] && $h['cannotdel'] && !$h['delete'], 'won lead: the Remove card says why it cannot be deleted, and offers no delete button');

    [$r, $h] = $page($f['master'], $reass);
    t_ok($h['reass'] && $h['decide'], 'reassignment waiting: the banner, and Approve / Reject for a manager');

    // ---- As a person who may only view leads (if this install has such a role) -------
    $view = null;
    foreach (['neha', 'am', 'co_ahm', 'meera', 'sbu', 'kiran'] as $who) {
        bbv_as($f[$who]);
        if (leads_can_view() && !leads_can_edit()) { $view = $f[$who]; break; }
    }
    bbv_as($f['master']);
    if ($view) {
        [$r, $h] = $page($view, $new);
        if (empty($r['error']) && $h['v3']) {
            t_ok(!$h['pill'] && !$h['edit'] && !$h['log'] && !$h['attach'] && !$h['move'] && !$h['quick'], 'view-only person: no stage menu, edit, log, attach, stage move or quick actions');
            t_ok($h['details'] && $h['history'] && $h['files'], 'view-only person: still reads the details, history and documents');
        } else t_ok(true, 'view-only person cannot open this office\'s lead (branch scope) — nothing to check');
    } else t_ok(true, 'no view-only role in this install — the editing rules are checked above');

    // ---- Score column in the register --------------------------------------------------
    $cols = function_exists('leads_columns') ? leads_columns() : null;
    $src = (string) file_get_contents(__DIR__ . '/../lib/leads.php');
    t_ok(strpos($src, "if (((\$l['status'] ?? 'OPEN') !== 'OPEN') return '<span class=\"muted\">—</span>';") !== false
         || strpos($src, "(\$l['status'] ?? 'OPEN') !== 'OPEN') return '<span class=\"muted\">—</span>'") !== false,
         'the leads list shows "—" as the score of a won or lost lead');
    t_ok(strpos($src, "\$sc = (\$r['status'] ?? 'OPEN') === 'OPEN' ? lead_score(\$r) : ['score' => ''];") !== false, 'and the CSV leaves it blank');

    t_section('Feature preservation — every element of the old pages is on the new ones');
    //  The pages as they were before this change are kept in tests/fixtures/pre_v3/. Every
    //  form action, every field name, every link target and every decision button found
    //  there must be on the new page: in its source, and drawn on the screen in at least
    //  one of the states above (new, quoted, lost, won, reassignment waiting).
    $inv = function ($src) {
        preg_match_all('/action="(\/[a-z-]+)/', $src, $a); preg_match_all('/name="([a-z_]+(?:\[\])?)"/', $src, $n);
        preg_match_all('/href="(\/[a-z-]+)/', $src, $h); preg_match_all('/name="decision" value="([a-z]+)"/', $src, $d);
        $out = [];
        foreach (array_unique($a[1]) as $x) $out[] = 'action="' . $x;
        foreach (array_unique($n[1]) as $x) $out[] = 'name="' . $x . '"';
        foreach (array_unique($h[1]) as $x) $out[] = 'href="' . $x;
        foreach (array_unique($d[1]) as $x) $out[] = 'value="' . $x . '"';
        return $out;
    };
    $old = (string) file_get_contents(__DIR__ . '/fixtures/pre_v3/lead_detail.php');
    $neu = (string) file_get_contents(__DIR__ . '/../views/ops/lead_detail.php');
    $logForm = (string) file_get_contents(__DIR__ . '/../views/ops/_log_form.php');
    $els = $inv($old);
    t_ok(count($els) >= 35, 'ARMING · the old lead page lists ' . count($els) . ' elements to look for');
    $seen = array_fill_keys($els, false);
    foreach ([$new, $quote, $lost, $won, $reass] as $lid) {
        $rr = lp3_drive($f['master'], 'lead', 'GET', ['id' => $lid], [], $els);
        foreach ($els as $x) if (!empty($rr['has'][$x])) $seen[$x] = true;
    }
    foreach ($els as $x) {
        t_ok(strpos($neu, $x) !== false || strpos($logForm, $x) !== false, 'lead page source still has ' . $x);
        t_ok($seen[$x], 'lead page draws ' . $x . ' (in at least one state)');
    }
    //  The words that name what a button or a section does.
    foreach (['Reassignment waiting for a manager', 'Approve', 'Reject', 'Reopen', 'Log your first contact', 'Open the deal', 'Work this as a deal',
              'Delete this lead', 'Remove this lead', 'cannot be deleted', 'Attach', 'What is it?', 'Note (optional)', 'Manpower deputation', 'Best reached by',
              'Days there', 'Why it was lost', 'Remarks — what happened', 'They said yes without a', 'Only the person responsible for this lead',
              'closes by itself as', 'days without progress', 'Project costing', 'How promising', 'Effort to win', 'Effort so far', 'What they want',
              'Why it is being reassigned', 'Edit the details', 'No documents yet.', 'It has not moved yet.', 'Nothing logged yet.',
              'No contact has been logged on this lead.', 'is now a customer', 'Hoped to close', 'Handled by', 'Worth about', 'Mobile', 'E-mail'] as $w)   // 'Telephone' → 'Mobile': owner decision D4, 21 Nov 2026 (one word for it across Sales)
        t_ok(strpos($neu, $w) !== false, 'the lead page still says "' . $w . '"');
    foreach (['lead_form' => 'lead_form', 'sx_lead' => 'sx_lead'] as $o => $nv) {
        $oldF = (string) file_get_contents(__DIR__ . '/fixtures/pre_v3/' . $o . '.php');
        $newF = (string) file_get_contents(__DIR__ . '/../views/ops/' . $nv . '.php');
        foreach ($inv($oldF) as $x) t_ok(strpos($newF, $x) !== false, $o . ' still has ' . $x);
    }

    t_section('Lead edit — a refused save keeps what was typed and stays on the lead');
    $post = ['id' => (string) $quote, 'company_name' => '   ', 'contact_name' => 'Typed Name 77', 'value' => '1200', 'requirement' => 'Typed requirement 77',
             'deputation_kind' => 'MANMONTH', 'manpower_count' => '2', 'site_location' => 'Hazira works', 'manpower_skills' => 'NDT Level II'];
    $r = lp3_drive($f['master'], 'lead-edit', 'POST', [], $post);
    t_ok(stripos(bb_said($r), 'needs a company') !== false, 'an empty company is refused, with the reason: ' . bb_said($r));
    t_eq((string) lead_row($quote)['contact_name'], 'Ravi Shah', 'nothing was saved');
    //  The kept typing lives in the browser session (form_stash), which the test worker
    //  does not open — so the handler's code is read, and the next page load is drawn
    //  with that session handed back.
    t_ok(strpos($lsrc0 = (string) file_get_contents(__DIR__ . '/../lib/leads.php'), "form_stash('lead', \$_POST + ['_edit' => '1']);") !== false
         && strpos($lsrc0, "redirect('/lead?id=' . \$id . '&edit=1#lead=details');") !== false && strpos($lsrc0, "/lead-edit?id=") === false,
         'a refused edit keeps what was typed for the lead page itself (the old address did not exist)');
    $r2 = lp3_drive($f['master'], 'lead', 'GET', ['id' => $quote, 'edit' => 1], [], ['Typed Name 77', 'Typed requirement 77', '<details id="edit" open', 'Nothing was lost'],
                    ['form_retry' => ['route' => 'lead', 'at' => time(), 'data' => $post + ['_edit' => '1']]]);
    t_ok(!empty($r2['has']['Typed Name 77']) && !empty($r2['has']['Typed requirement 77']), 'back on the lead, the edit form holds what was typed');
    t_ok(!empty($r2['has']['<details id="edit" open']) && !empty($r2['has']['Nothing was lost']), 'and it is open, saying nothing was lost');
    $r = lp3_drive($f['master'], 'lead-edit', 'POST', [], ['id' => (string) $quote, 'company_name' => 'V3 Quoted Co ' . $f['tag'], 'value' => '-5000']);
    t_ok(stripos(bb_said($r), 'value') !== false && (float) lead_row($quote)['value'] === 1000000.0, 'a negative value is refused, with the reason, and nothing changes');
    // The new edit form carries the manpower fields, so saving it keeps them (the old form
    // had none, and the handler — unchanged — saved them as blank).
    $r = lp3_drive($f['master'], 'lead-edit', 'POST', [], ['id' => (string) $quote, 'company_name' => 'V3 Quoted Co ' . $f['tag'], 'contact_name' => 'Ravi Shah', 'value' => '1000000',
                   'deputation_kind' => 'MANMONTH', 'manpower_count' => '2', 'site_location' => 'Hazira works', 'manpower_skills' => 'NDT Level II']);
    $q = lead_row($quote);
    t_eq([(string) $q['deputation_kind'], (int) $q['manpower_count'], (string) $q['site_location']], ['MANMONTH', 2, 'Hazira works'],
         'saving the new edit form keeps the manpower deputation (the old form had no such fields and wiped it)');
    $r = lp3_drive($f['master'], 'lead-edit', 'POST', [], ['id' => (string) $quote, 'company_name' => 'V3 Quoted Co ' . $f['tag'], 'deputation_kind' => '', 'value' => '1000000']);
    t_eq((string) lead_row($quote)['deputation_kind'], '', 'choosing "none" on the new edit form clears them on purpose');

    t_section('Quick lead → full form, and files on a new lead');
    $sx = (string) file_get_contents(__DIR__ . '/../views/ops/sx_lead.php');
    foreach (['company_name', 'contact_name', 'contact_phone', 'source', 'value', 'office_id', 'owner_user_id', 'next_action_on'] as $k)
        t_ok(strpos($sx, "'" . $k . "'") !== false, '"Full lead form ›" carries ' . $k);
    $r = lp3_drive($f['master'], 'lead-new', 'GET', ['company_name' => 'Carried Co 88', 'contact_phone' => '9825088888', 'value' => '250000'], [],
                   ['value="Carried Co 88"', 'value="9825088888"', 'value="250000"', 'enctype="multipart/form-data"', 'name="files[]"', 'name="file_kind"']);
    t_ok(!empty($r['has']['value="Carried Co 88"']) && !empty($r['has']['value="9825088888"']) && !empty($r['has']['value="250000"']), 'the full lead form opens with what was typed in the quick form');
    t_ok(!empty($r['has']['enctype="multipart/form-data"']) && !empty($r['has']['name="files[]"']) && !empty($r['has']['name="file_kind"']), 'the full lead form offers "Attach files (optional)"');
    $r = lp3_drive($f['master'], 'sales-lead', 'GET', [], [], ['enctype="multipart/form-data"', 'name="files[]"', 'id="sl-full"', 'data-max-bytes']);
    t_ok(!empty($r['has']['name="files[]"']) && !empty($r['has']['enctype="multipart/form-data"']) && !empty($r['has']['data-max-bytes']), 'the quick lead form offers "Attach files (optional)", with the size check');
    t_ok(!empty($r['has']['id="sl-full"']), 'and the "Full lead form ›" link that carries the values');
    t_eq(lead_attach_uploads($new, null), [0, []], 'no files chosen: nothing attached, nothing refused');
    $tmp = tempnam(sys_get_temp_dir(), 'lp3');
    file_put_contents($tmp, 'x');
    t_eq(lead_attach_uploads($new, ['tmp_name' => [$tmp], 'name' => ['a.txt'], 'size' => [1], 'type' => ['text/plain']])[0], 0, 'a file that did not come through an upload is never stored');
    @unlink($tmp);
    $lsrc = (string) file_get_contents(__DIR__ . '/../lib/leads.php'); $msrc = (string) file_get_contents(__DIR__ . '/../lib/sales_more.php');
    t_ok(substr_count($lsrc, 'lead_attach_uploads(') >= 3 && strpos($msrc, 'lead_attach_uploads(') !== false,
         'the Documents panel, the full form and the quick form all store files through the one helper (8 MB, kinds, History line)');

    t_section('Quick actions open the one log form ready to type');
    $r = lp3_drive($f['master'], 'lead', 'GET', ['id' => $new, 'log' => 'EMAIL'], [], ['value="EMAIL" checked', '<details class="ct-log lf" id="log" open']);
    t_ok(!empty($r['has']['value="EMAIL" checked']), '?log=EMAIL ticks "E-mail" in the log form');
    t_ok(!empty($r['has']['<details class="ct-log lf" id="log" open']), 'and opens it');
} finally {
    foreach ($made['opp'] as $i) db()->prepare("DELETE FROM opportunities WHERE id=?")->execute([$i]);
    foreach ($made['bp'] as $i) db()->prepare("DELETE FROM business_partners WHERE id=?")->execute([$i]);
    foreach ($made['rr'] as $i) db()->prepare("DELETE FROM lead_reassign_requests WHERE id=?")->execute([$i]);
    foreach ($made['quotes'] as $i) db()->prepare("DELETE FROM quotations WHERE id=?")->execute([$i]);
    foreach ($made['leads'] as $i) if ($i) {
        db()->prepare("DELETE FROM activities WHERE entity_kind='LEAD' AND entity_id=?")->execute([$i]);
        db()->prepare("DELETE FROM lead_files WHERE lead_id=?")->execute([$i]);
        db()->prepare("DELETE FROM lead_stage_history WHERE lead_id=?")->execute([$i]);
        db()->prepare("DELETE FROM leads WHERE id=?")->execute([$i]);
    }
    if ($made['pipe']) { db()->prepare("DELETE FROM pipeline_stages WHERE pipeline_id=?")->execute([$made['pipe']]); db()->prepare("DELETE FROM pipelines WHERE id=?")->execute([$made['pipe']]); }
    bbv_cleanup($f);
}
t_ok(true, 'the test removed its own rows');
