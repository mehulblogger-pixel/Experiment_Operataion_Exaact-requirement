<?php
// ============================================================================
//  AN APPROVER MUST SEE WHAT THEY ARE APPROVING.
//
//  The hiring request is the record that goes through approval. It captured no
//  money at all: job title, headcount, department, dates — and nothing about
//  cost. The budget lived on the requisition, which is created AFTER the
//  decision. So a manager said yes to a headcount and found out the price
//  afterwards, on a record their approval had brought into existence.
//
//  For a manpower supplier or a project management company, cost is not one
//  input among twelve. It IS the decision.
//
//  These tests cover three things, in order of how much damage they prevent:
//    1. the arithmetic, including the shapes where it must NOT guess;
//    2. that raising the commitment after approval is material, and lowering
//       it is not — the same asymmetry the product already applies to headcount;
//    3. that the approved figure reaches the requisition as its budget baseline.
// ============================================================================

t_section('Hiring request — what it commits the company to');

hreq_migrate();

t_ok(function_exists('hreq_commitment'), 'ARMING · the helper exists');

// ---------------------------------------------------------------------------
//  1 · THE ARITHMETIC
// ---------------------------------------------------------------------------
t_nothrow('the headline number is quantity x rate x duration, plus any one-time cost', function () {
    $c = hreq_commitment(['quantity' => 12, 'est_cost_per_person' => 75000,
                          'est_cost_basis' => 'MANMONTH', 'est_duration_months' => 12]);
    t_eq($c['total'], 12 * 75000 * 12.0, 'twelve people, 75,000 each, twelve months');
    t_ok($c['has'], 'and it counts as estimated');

    $c2 = hreq_commitment(['quantity' => 2, 'est_cost_per_person' => 50000,
                           'est_cost_basis' => 'MONTHLY', 'est_duration_months' => 6,
                           'est_onetime_cost' => 25000]);
    t_eq($c2['total'], 2 * 50000 * 6.0 + 25000, 'a mobilisation cost is added on top');
});

t_nothrow('a fee-only request — the recruitment agency case — is a complete estimate', function () {
    //  An agency places somebody onto the CLIENT's payroll and bills a fee once.
    //  There is no rate and no duration, and this must not read as "no estimate".
    $c = hreq_commitment(['quantity' => 1, 'est_onetime_cost' => 150000]);
    t_eq($c['total'], 150000.0, 'the fee is the whole commitment');
    t_ok($c['has'], 'and it IS an estimate — not "not estimated"');
});

t_nothrow('a FIXED whole-order price is not multiplied by a duration it does not have', function () {
    $c = hreq_commitment(['quantity' => 1, 'est_cost_per_person' => 1200000, 'est_cost_basis' => 'FIXED']);
    t_eq($c['total'], 1200000.0, 'a fixed price stands alone');
    t_ok(empty($c['partial']), 'and is not flagged as missing a duration');
});

t_nothrow('a rate with no duration is NOT guessed at — it is flagged', function () {
    //  The dangerous case. Multiplying by a default duration would invent a
    //  commitment nobody stated; showing zero would hide a real cost. So it
    //  contributes exactly what is known — one period — and says so, and every
    //  screen prints that warning next to the number.
    $c = hreq_commitment(['quantity' => 4, 'est_cost_per_person' => 60000, 'est_cost_basis' => 'MONTHLY']);
    t_eq($c['total'], 4 * 60000.0, 'one period only');
    t_ok(!empty($c['partial']), 'and it is marked as incomplete, so the screen can warn');
});

t_nothrow('nothing entered reads as "not estimated", never as zero', function () {
    foreach ([[], ['quantity' => 5], ['quantity' => 5, 'est_cost_per_person' => 0, 'est_onetime_cost' => 0],
              ['quantity' => 5, 'est_cost_per_person' => '', 'est_duration_months' => '']] as $i => $row) {
        $c = hreq_commitment($row);
        t_ok(empty($c['has']), 'has=false for shape #' . ($i + 1));
        t_eq($c['total'], 0.0, 'and the total is zero, for the screen to suppress: #' . ($i + 1));
    }
});

t_nothrow('quantity is never treated as zero — one person is the floor', function () {
    $c = hreq_commitment(['quantity' => 0, 'est_cost_per_person' => 40000,
                          'est_cost_basis' => 'MONTHLY', 'est_duration_months' => 3]);
    t_eq($c['total'], 40000 * 3.0, 'a missing quantity costs one person, not nothing');
});

// ---------------------------------------------------------------------------
//  2 · MATERIALITY — the control that stops an approval being spent quietly
// ---------------------------------------------------------------------------
t_nothrow('raising the commitment after approval is material; lowering it is not', function () {
    $approved = ['quantity' => 5, 'est_cost_per_person' => 50000,
                 'est_cost_basis' => 'MONTHLY', 'est_duration_months' => 12];
    $snap = ['fields' => $approved, 'taken_at' => '2026-01-01'];
    $row  = $approved + ['approved_snapshot_json' => json_encode($snap), 'status' => 'APPROVED'];

    //  Up — the rate rises. Authority nobody granted.
    $up = hreq_material_diff($row, ['est_cost_per_person' => 90000] + $approved);
    t_ok(isset($up['commitment']), 'a higher rate is a material change');
    t_ok(stripos((string) ($up['commitment']['why'] ?? ''), 'larger') !== false,
         'and the reason says so in words an approver would use');

    //  Up — the duration stretches. Same money, same problem.
    $longer = hreq_material_diff($row, ['est_duration_months' => 24] + $approved);
    t_ok(isset($longer['commitment']), 'doubling the duration is material too');

    //  Down — cheaper than approved. Inside the authority already given.
    $down = hreq_material_diff($row, ['est_cost_per_person' => 30000] + $approved);
    t_ok(!isset($down['commitment']), 'a cheaper request needs no re-approval');

    //  Unchanged.
    t_ok(!isset(hreq_material_diff($row, $approved)['commitment']), 'no change, no difference');
});

t_nothrow('a trade-off that leaves the total alone does NOT force a re-approval', function () {
    //  This is why the comparison is ONE number rather than field by field.
    //  Halving the duration and doubling the rate is the same commitment, and a
    //  field-by-field rule would fire twice and send a manager round the approval
    //  loop for a change that costs nothing. That is how controls get routed
    //  around: make them fire on nothing and people stop believing them.
    $approved = ['quantity' => 4, 'est_cost_per_person' => 50000,
                 'est_cost_basis' => 'MONTHLY', 'est_duration_months' => 12];
    $row = $approved + ['approved_snapshot_json' => json_encode(['fields' => $approved]), 'status' => 'APPROVED'];
    $swap = hreq_material_diff($row, ['est_cost_per_person' => 100000, 'est_duration_months' => 6] + $approved);
    t_ok(!isset($swap['commitment']), 'same total, no material change');
    $c1 = hreq_commitment($approved);
    $c2 = hreq_commitment(['est_cost_per_person' => 100000, 'est_duration_months' => 6] + $approved);
    t_eq($c1['total'], $c2['total'], 'ARMING · the two really are the same commitment');
});

t_nothrow('a rounding tail is not a business change', function () {
    $approved = ['quantity' => 3, 'est_cost_per_person' => 33333.33,
                 'est_cost_basis' => 'MONTHLY', 'est_duration_months' => 7];
    $row = $approved + ['approved_snapshot_json' => json_encode(['fields' => $approved]), 'status' => 'APPROVED'];
    t_ok(!isset(hreq_material_diff($row, $approved)['commitment']), 'the same figures re-read are not a change');
});

t_nothrow('the snapshot carries the cost, or materiality has nothing to compare against', function () {
    $src = (string) @file_get_contents(dirname(__DIR__) . '/lib/hiringreq.php');
    $from = strpos($src, 'function hreq_snapshot');
    t_ok($from !== false, 'ARMING · the snapshot function was located');
    $body = substr($src, $from, 2000);
    foreach (['est_cost_per_person', 'est_cost_basis', 'est_duration_months', 'est_onetime_cost'] as $f)
        t_ok(strpos($body, $f) !== false, "the snapshot stores $f");
});

// ---------------------------------------------------------------------------
//  3 · IT REACHES THE PEOPLE AND THE RECORDS THAT NEED IT
// ---------------------------------------------------------------------------
t_nothrow('the approver is shown the figure where the decision is made', function () {
    $v = (string) @file_get_contents(dirname(__DIR__) . '/views/ops/hiring_request.php');
    t_ok(strpos($v, 'hreq_commitment') !== false, 'the request screen asks for the commitment');
    //  Above the buttons, not somewhere further up the page.
    $panel = strpos($v, 'You are approving a commitment');
    $btn   = strpos($v, 'name="decision" value="approve"');
    t_ok($panel !== false, 'it states the commitment in the approver\'s words');
    t_ok($btn !== false, 'ARMING · the Approve button was located');
    t_ok($panel !== false && $btn !== false && $panel < $btn,
         'and it sits ABOVE the Approve button — a figure below it is a figure nobody reads');
    t_ok(strpos($v, 'No cost estimate was given') !== false,
         'and an unestimated request says so plainly rather than showing nothing');
});

t_nothrow('the approval inbox is sent the value, not a zero', function () {
    $src = (string) @file_get_contents(dirname(__DIR__) . '/lib/hiringreq.php');
    t_ok(preg_match('~appr_start\(\s*\'HIRING_REQUEST\'.*?hreq_commitment~s', $src) === 1,
         'appr_start carries the commitment as the request value');
    //  /my-approvals already renders "Value" when the amount is non-zero. It was
    //  always sent 0, so the column never appeared for a hiring request.
    $inbox = (string) @file_get_contents(dirname(__DIR__) . '/views/ops/my_approvals.php');
    t_ok(strpos($inbox, "\$s['amount']") !== false, 'ARMING · the inbox does render an amount');
});

t_nothrow('the money does NOT silently redefine existing amount-band rules', function () {
    //  A hiring request's rule context has always used 'amount' to mean HEADCOUNT
    //  ("more than five people needs the director"). Making it money overnight
    //  would turn every configured band into nonsense. The commitment therefore
    //  arrives under its own key.
    $ctx = hreq_appr_ctx(['quantity' => 7, 'est_cost_per_person' => 80000,
                          'est_cost_basis' => 'MONTHLY', 'est_duration_months' => 10]);
    t_eq((int) $ctx['amount'], 7, "'amount' still means headcount, so configured rules keep their meaning");
    t_eq((float) $ctx['commitment'], 7 * 80000 * 10.0, "and the money arrives as 'commitment'");
});

t_nothrow('an approved estimate becomes the requisition\'s budget baseline', function () {
    $src = (string) @file_get_contents(dirname(__DIR__) . '/lib/hiringreq.php');
    $ins = strpos($src, 'INSERT INTO requisitions');
    t_ok($ins !== false, 'ARMING · the requisition insert was located');
    $body = substr($src, max(0, $ins - 1200), 2400);
    t_ok(strpos($body, 'budgeted_cost') !== false, 'the insert sets the requisition budget');
    t_ok(strpos($body, 'est_cost_per_person') !== false, 'from the approved estimate');
    //  Without this, the placement commercial approved later is measured against
    //  an estimate nobody ever approved.
    t_ok(strpos($body, 'rate_basis') !== false, 'and carries the basis with it, so the number keeps its units');
});
