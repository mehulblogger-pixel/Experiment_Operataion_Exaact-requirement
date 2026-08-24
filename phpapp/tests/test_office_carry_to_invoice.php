<?php
// The office is set ONCE at contract registration and carries down to the invoice.
// invoice_office_for() proves the resolution: a same-office call (no executing
// branch) still bills from the contract's branch, so "No branch is set" cannot
// recur.
t_section('the contract branch carries down to the invoice');

$pdo = db();
// A registered contract carrying its branch (office 7).
$pdo->prepare("INSERT INTO partner_contracts (partner_id, contract_number, branch_id, open_status, is_active) VALUES (0,'OC/2026/1', 7, 'OPEN', 1)")->execute();

// Same-office call: no executing branch, no explicit contracting office on the row —
// only the contract number. The invoice must still resolve to the contract's branch.
$call = ['contract_number' => 'OC/2026/1', 'ibo_office_id' => null, 'contracting_office_id' => null, 'executing_office_id' => null];
$job  = ['contract_number' => 'OC/2026/1', 'contracting_office_id' => null, 'executing_office_id' => null];
t_ok(invoice_office_for($job, $call) === 7, 'a same-office call bills from the contract branch (no "No branch is set")');

// The call's own managing office wins when present (it was inherited from the contract).
$call2 = ['contract_number' => 'OC/2026/1', 'ibo_office_id' => 5];
t_ok(invoice_office_for([], $call2) === 5, 'the call managing/contracting office is used when set');

// The job's contracting office is the most specific and wins.
t_ok(invoice_office_for(['contracting_office_id' => 9], ['ibo_office_id' => 5]) === 9, 'the job contracting office wins when set');

// With nothing on the records and no branch on the contract, it falls to the
// executing office rather than nothing.
$callX = ['contract_number' => '', 'executing_office_id' => 3];
t_ok(invoice_office_for([], $callX) === 3, 'the executing office is the fallback when no contracting office exists');

// Registration captures the branch from the posted value, else the quote office.
$crm = file_get_contents(__DIR__ . '/../lib/crm.php');
t_ok(strpos($crm, "\$_POST['branch_id']") !== false, 'contract registration reads the chosen billing branch');
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "\$ct['branch_id']") !== false, 'a call raised from a contract inherits the contract branch');

// Recovering a stranded draft: a branch can be SET on a draft that has none, and
// a draft is never born without one when the user is acting in a branch.
if (function_exists('books_invoice_create')) {
    t_section('a draft with no branch can be given one (no dead end)');
    $own = !db()->inTransaction();
    if ($own) db()->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO offices (name, code, is_active) VALUES ('Branch Fix Office','BFO',1)")->execute();
        $ofid = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES ('Branchless Co','Branchless Co',1,'ACTIVE')")->execute();
        $cid = (int)$pdo->lastInsertId();

        // A draft created with no office at all — the stranded case.
        $r = books_invoice_create(['partner_id' => $cid, 'office_id' => 0]);
        t_ok(empty($r['err']) && !empty($r['id']), 'a draft can be started without a branch');
        $inv0 = books_invoice((int)$r['id']);
        t_ok(in_array('No branch is set, so it has no numbering series.', books_issue_missing($inv0), true),
            'and it is correctly flagged as not-ready until a branch is set');

        // Setting the branch clears the blocker and gives it a series.
        t_eq(books_set_office((int)$r['id'], $ofid), '', 'a branch can be set on the draft');
        $inv1 = books_invoice((int)$r['id']);
        t_ok((int)$inv1['office_id'] === $ofid && $inv1['series'] === 'BFO', 'the draft now bills from that branch, with its series');
        t_ok(!in_array('No branch is set, so it has no numbering series.', books_issue_missing($inv1), true),
            'the "No branch is set" blocker is gone');
        t_ok(books_set_office((int)$r['id'], 999999) !== '', 'an unknown / inactive branch is refused');
    } finally {
        if ($own && db()->inTransaction()) db()->rollBack();
    }

    // The create-time fallback: with no office posted, the invoice takes the
    // acting user's home branch rather than being born stranded.
    t_ok(strpos(file_get_contents(__DIR__ . '/../lib/books.php'), "current_user()['home_office_id']") !== false,
        'a draft with no branch falls back to the branch the user is acting in');
    // The draft screen offers the branch fixer.
    t_ok(strpos(file_get_contents(__DIR__ . '/../views/ops/invoice_detail.php'), '/invoice-set-branch') !== false,
        'the draft invoice offers a one-click "Set branch" fixer');
}
