<?php
// ============================================================================
//  Chargeable expenses, and the bills that have to back them
//
//  Owner's rule, in their words: "There shall also be option to mark if
//  travelling, lodging boarding with multiple selection chargeable to client;
//  if tick or selected it is required for inspector to upload all related
//  bills."
//
//  So two things, and they are linked:
//
//   1. On the inspection call — and again on the deputation, because it can be
//      corrected at allocation — the coordinator ticks which expense headings
//      the client has agreed to pay on top of the fee. Any number of them:
//      travelling, lodging, boarding, local conveyance, or anything else the
//      company has added to its own expense-heading list.
//
//   2. A ticked heading is a promise to the client that a bill exists. So the
//      engineer cannot close that deputation until a bill is on file for every
//      heading that was ticked. Not "should" — the Close button refuses.
//
//  Why the bills are held here and not on the monthly voucher: the voucher is
//  the engineer's own reimbursement, raised weeks later, and the client's copy
//  is needed at invoicing. Tying the bill to the deputation means the person
//  raising the invoice can see it without going hunting through a month's
//  claims for the right day.
//
//  Money: a bill the client pays is not a cost the branch bears. See
//  job_recovered_total() and how job_profit() uses it.
// ============================================================================

function bills_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = pk_clause();
    // The ticked headings, as a CSV of expense-heading codes. On both tables:
    // the call is where it is agreed, the deputation is where it is worked to,
    // and a coordinator may correct it at allocation without touching the call.
    foreach (['calls', 'jobs'] as $t)
        ensure_column($t, 'chargeable_heads', "VARCHAR(255) DEFAULT ''");
    // Heads the client agreed to pay but which were NOT incurred on this job —
    // e.g. travel on a job the engineer did locally. Declared nil at closure so
    // the job can close without a bill that will never exist, while keeping the
    // fact on record instead of silently dropping the requirement.
    ensure_column('jobs', 'nil_chargeable_heads', "VARCHAR(255) DEFAULT ''");
    // One row per bill. The file is held in the row like every other upload in
    // this app, so a shared-hosting move never leaves the records pointing at
    // files that did not travel with them.
    $pdo->exec("CREATE TABLE IF NOT EXISTS job_bills (
        id $pk, job_id INT, head_code VARCHAR(30) DEFAULT '',
        bill_no VARCHAR(80) DEFAULT '', bill_date VARCHAR(20) DEFAULT '',
        amount DECIMAL(12,2) DEFAULT 0,
        file_name VARCHAR(255) DEFAULT '', mime VARCHAR(100) DEFAULT '', file_data LONGTEXT,
        note VARCHAR(400) DEFAULT '',
        uploaded_by VARCHAR(150) DEFAULT '', uploaded_at VARCHAR(30) DEFAULT '')");
}

// ---- The tickable headings -------------------------------------------------
// Exactly the company's own expense-heading master, so a heading added there
// becomes tickable here with nothing to change. Travelling, lodging and
// boarding are in the shipped list; so is local conveyance and misc.
function chargeable_head_options() {
    return lk_options_or('expense_heading', EXPENSE_HEADINGS);
}

// The ticked codes on a call or a deputation, as an array.
function chargeable_heads($row) {
    $csv = is_array($row) ? (string)($row['chargeable_heads'] ?? '') : (string)$row;
    return array_values(array_filter(array_map('trim', explode(',', $csv)), fn($c) => $c !== ''));
}

// What the tick-boxes posted, cleaned back to a CSV. Only codes that really
// exist in the master survive, so a stale or hand-made form cannot store a
// heading nobody can ever satisfy — which would lock the job closed for good.
function chargeable_heads_from_post($posted) {
    $valid = chargeable_head_options();
    $keep  = [];
    foreach ((array)$posted as $code) {
        $code = trim((string)$code);
        if ($code !== '' && isset($valid[$code]) && !in_array($code, $keep, true)) $keep[] = $code;
    }
    return implode(',', $keep);
}

// Human labels for a set of codes — for a detail screen or an e-mail.
function chargeable_head_labels($row) {
    $opt = chargeable_head_options();
    $out = [];
    foreach (chargeable_heads($row) as $c) $out[] = $opt[$c] ?? $c;
    return $out;
}

// ---- The bills themselves --------------------------------------------------
function job_bills($jobId) {
    try {
        return ops_all("SELECT id, job_id, head_code, bill_no, bill_date, amount, file_name, mime, note,
                               uploaded_by, uploaded_at
                        FROM job_bills WHERE job_id=? ORDER BY head_code, id", [(int)$jobId]);
    } catch (Throwable $e) { return []; }   // pre-migration
}

// Grouped by heading, which is how both screens want it.
function job_bills_by_head($jobId) {
    $out = [];
    foreach (job_bills($jobId) as $b) $out[$b['head_code']][] = $b;
    return $out;
}

// The headings that were ticked and still have no bill. Empty means the
// deputation may close. This is the whole gate, in one function.
function job_bills_missing($job) {
    $ticked = chargeable_heads($job);
    if (!$ticked) return [];
    $have = job_bills_by_head((int)($job['id'] ?? 0));
    // A head declared "not incurred" at closure counts as satisfied — nothing to
    // bill, but the decision is recorded.
    $nil  = array_flip(chargeable_heads([
        'chargeable_heads' => (string)($job['nil_chargeable_heads'] ?? '')]));
    $opt  = chargeable_head_options();
    $miss = [];
    foreach ($ticked as $c) if (empty($have[$c]) && !isset($nil[$c])) $miss[$c] = $opt[$c] ?? $c;
    return $miss;
}

// The sentence shown to whoever is being stopped, or '' when nothing is.
function job_bills_block($job) {
    $miss = job_bills_missing($job);
    if (!$miss) return '';
    $n = count($miss);
    return 'The client is being charged for ' . ($n === 1 ? 'this expense' : 'these expenses')
         . ', so ' . ($n === 1 ? 'its bill has' : 'their bills have') . ' to be on file before this '
         . Tl('job') . ' can be closed: ' . implode(', ', $miss) . '.';
}

// What the client is being billed back for on this deputation — the bills that
// sit under a ticked heading. A bill filed under a heading that was later
// un-ticked is kept but no longer counted, because it is no longer chargeable.
function job_recovered_total($job) {
    $ticked = chargeable_heads($job);
    if (!$ticked) return 0.0;
    $sum = 0.0;
    foreach (job_bills((int)($job['id'] ?? 0)) as $b)
        if (in_array($b['head_code'], $ticked, true)) $sum += (float)$b['amount'];
    return round($sum, 2);
}

// ---- Storing one --------------------------------------------------------------
function job_bill_add($jobId, $headCode, $post, $file) {
    $bytes = ($file && ($file['tmp_name'] ?? '') !== '' && is_uploaded_file($file['tmp_name']))
        ? (string)file_get_contents($file['tmp_name']) : '';
    if ($bytes === '') return 'Choose the bill file to upload.';
    if (($why = upload_reject_reason($bytes, $file['name'] ?? '', $file['type'] ?? '')) !== '') return $why;
    $valid = chargeable_head_options();
    if (!isset($valid[$headCode])) return 'That expense heading is not on the list any more.';
    db()->prepare("INSERT INTO job_bills (job_id,head_code,bill_no,bill_date,amount,file_name,mime,file_data,note,uploaded_by,uploaded_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([(int)$jobId, $headCode, substr(trim((string)($post['bill_no'] ?? '')), 0, 80),
                   (string)($post['bill_date'] ?? ''), num($post['amount'] ?? 0),
                   substr((string)($file['name'] ?? 'bill'), 0, 255), (string)($file['type'] ?? ''),
                   base64_encode($bytes), substr(trim((string)($post['note'] ?? '')), 0, 400),
                   user_name(current_user()), date('c')]);
    return '';
}

function job_bill_row($id) {
    try { return ops_one("SELECT * FROM job_bills WHERE id=?", [(int)$id]); }
    catch (Throwable $e) { return null; }
}

// ---- Screens ---------------------------------------------------------------
// Three small routes, all hung off one deputation.
function ops_job_bill($route, $method) {
    if ($route === 'bill-file') {
        $b = job_bill_row($_GET['id'] ?? 0);
        if (!$b) { http_response_code(404); view('notfound'); return true; }
        ops_require(job_bill_can_see($b), 'You cannot open this bill.');
        send_uploaded_file(base64_decode((string)$b['file_data']), $b['file_name'] ?: 'bill', $b['mime'] ?: 'application/octet-stream');
        return true;
    }
    $jobId = (int)($_GET['job'] ?? $_POST['job_id'] ?? 0);
    $job   = $jobId ? ops_one("SELECT * FROM jobs WHERE id=?", [$jobId]) : null;
    if (!$job) { http_response_code(404); view('notfound'); return true; }
    ops_require(job_bill_can_upload($job), 'Only the engineer on this ' . Tl('job') . ', the coordinator or a manager can file its bills.');

    if ($route === 'bill-add' && $method === 'POST') {
        $why = job_bill_add($job['id'], (string)($_POST['head_code'] ?? ''), $_POST, $_FILES['bill_file'] ?? null);
        if ($why !== '') flash($why, 'error');
        else flash('Bill filed. ' . (($m = job_bills_missing(ops_one("SELECT * FROM jobs WHERE id=?", [$job['id']])))
              ? 'Still outstanding: ' . implode(', ', $m) . '.' : 'Every chargeable expense on this ' . Tl('job') . ' is now backed by a bill.'));
        redirect('/job?id=' . $job['id']);
    }
    if ($route === 'bill-delete' && $method === 'POST') {
        $b = job_bill_row($_POST['id'] ?? 0);
        // Once the job is closed the bills are part of what was invoiced, so
        // only a manager may pull one — an engineer cannot quietly remove the
        // evidence for something the client has already been charged for.
        if ($b && (int)$b['job_id'] === (int)$job['id']) {
            if (!empty($job['closed_flag']) && !is_admin_level())
                flash('This ' . Tl('job') . ' is closed. Ask a manager to remove a bill from it.', 'error');
            else {
                db()->prepare("DELETE FROM job_bills WHERE id=?")->execute([(int)$b['id']]);
                flash('Bill removed.');
            }
        }
        redirect('/job?id=' . $job['id']);
    }
    redirect('/job?id=' . $job['id']);
    return true;
}

// The engineer who did the work, the desk that runs it, or a manager.
function job_bill_can_upload($job) {
    if (is_admin_level() || can('ops.job.close') || can('mod.jobs.edit')) return true;
    if (function_exists('is_field_inspector') ? is_field_inspector() : is_inspector()) {
        $me = (int)(current_user()['inspector_id'] ?? 0);
        return $me && $me === (int)($job['inspector_id'] ?? 0);
    }
    return false;
}
function job_bill_can_see($bill) {
    $job = ops_one("SELECT * FROM jobs WHERE id=?", [(int)$bill['job_id']]);
    return $job ? (job_bill_can_upload($job) || can('mod.jobs.view')) : false;
}
