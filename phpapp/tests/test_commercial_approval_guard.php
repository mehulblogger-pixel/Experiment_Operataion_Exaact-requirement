<?php
// ============================================================================
//  AN APPROVAL WITH NO NUMBERS IN IT IS NOT AN APPROVAL.
//
//  Approving placement commercials LOCKS what a client will be billed and what
//  we will carry as cost. It stamps the approver's name and the date on the
//  record, turns the badge green ("Approved — locked for this hire"), and
//  becomes the baseline every later variance is measured against.
//
//  A blank form used to sail through all of it. Three zeros, a name, a date, a
//  green tick — and "variance vs estimate" then computed against nothing. The
//  billing-readiness gate separately refuses a zero rate, so no money could
//  actually be invoiced; what was wrong was the RECORD and the ASSURANCE on the
//  screen, which is quite bad enough.
//
//  The rule has to fit every business this product serves, and those bill in
//  genuinely different shapes. That is what most of this file is about.
// ============================================================================

t_section('Placement commercials — an approval must carry a figure');

$ok  = fn($post) => assignment_commercial_refusal($post) === '';
$why = fn($post) => assignment_commercial_refusal($post);

t_ok(function_exists('assignment_commercial_refusal'), 'ARMING · the guard exists');

// ---------------------------------------------------------------------------
//  WHAT MUST BE REFUSED.
// ---------------------------------------------------------------------------
t_nothrow('a blank approval is refused, with a sentence that says what to do', function () use ($ok, $why) {
    foreach ([
        [[], 'nothing at all'],
        [['bill_rate' => '', 'cost_rate' => '', 'months' => '', 'onetime' => ''], 'every box left empty'],
        [['bill_rate' => '0', 'cost_rate' => '0', 'months' => '0', 'onetime' => '0'], 'zeros typed in'],
        [['cost_rate' => '50000', 'months' => '12'], 'our cost only — nothing to bill'],
    ] as [$post, $what]) {
        t_ok(!$ok($post), 'refused: ' . $what);
        $m = $why($post);
        t_ok(strlen($m) > 30, 'and explains why: ' . $what);
        t_ok(stripos($m, 'billing rate') !== false || stripos($m, 'duration') !== false,
            'naming the box to fill: ' . $what);
    }
});

t_nothrow('a rate with no duration is refused — it locks a value of zero', function () use ($ok, $why) {
    foreach (['MONTHLY', 'MANMONTH', 'MANDAY', 'DAILY'] as $b) {
        $post = ['bill_rate' => '75000', 'bill_basis' => $b, 'cost_rate' => '60000', 'months' => '0'];
        t_ok(!$ok($post), "refused: a $b rate with no duration");
        t_ok(stripos($why($post), 'duration') !== false, "and says so: $b");
    }
});

t_nothrow('negative money is refused', function () use ($ok, $why) {
    foreach ([['bill_rate' => '-1', 'months' => '6'], ['bill_rate' => '100', 'months' => '-6'],
              ['onetime' => '-500'], ['bill_rate' => '100', 'months' => '6', 'cost_rate' => '-1']] as $i => $post) {
        t_ok(!$ok($post), 'refused: negative figure #' . ($i + 1));
        t_ok(stripos($why($post), 'negative') !== false, 'and says it is negative #' . ($i + 1));
    }
});

// ---------------------------------------------------------------------------
//  WHAT MUST BE ACCEPTED. Each of these is a REAL deal shape, and refusing any
//  of them would break that business rather than protect it. This half of the
//  file matters more than the half above: a guard that is merely strict is easy
//  and useless.
// ---------------------------------------------------------------------------
t_nothrow('a MANPOWER SUPPLIER — a rate for a duration — is accepted', function () use ($ok, $why) {
    $post = ['bill_rate' => '95000', 'bill_basis' => 'MANMONTH', 'cost_rate' => '72000', 'months' => '12'];
    t_ok($ok($post), 'rate + cost + duration is a complete deal (' . $why($post) . ')');
});

t_nothrow('a RECRUITMENT AGENCY — a one-time placement fee and nothing else — is accepted', function () use ($ok, $why) {
    //  This is the case a naive "every box is mandatory" rule would have broken.
    //  An agency that places a candidate bills a fee once. It has no monthly
    //  rate, no duration, and no bench cost of its own: the person goes on the
    //  CLIENT's payroll. Demanding a rate here would make the product unusable
    //  for an entire category of customer.
    $post = ['onetime' => '150000', 'bill_rate' => '', 'cost_rate' => '', 'months' => ''];
    t_ok($ok($post), 'a placement fee alone is a complete deal (' . $why($post) . ')');
    t_ok($ok(['onetime' => '150000', 'cost_rate' => '0', 'months' => '0']),
        'and zero cost is legitimate — the agency carries none');
});

t_nothrow('a FIXED whole-order price, which has no duration, is accepted', function () use ($ok, $why) {
    $post = ['bill_rate' => '1200000', 'bill_basis' => 'FIXED', 'cost_rate' => '900000', 'months' => '0'];
    t_ok($ok($post), 'a fixed order needs no months (' . $why($post) . ')');
});

t_nothrow('a rate AND a fee together — supply plus a mobilisation charge — is accepted', function () use ($ok) {
    t_ok($ok(['bill_rate' => '80000', 'bill_basis' => 'MONTHLY', 'months' => '6',
              'cost_rate' => '65000', 'onetime' => '25000']), 'both may be present');
});

t_nothrow('a zero-margin or loss-making deal is ACCEPTED — that is a business call, not an error', function () use ($ok) {
    //  The guard exists to stop an EMPTY approval, never to second-guess the
    //  commercial judgement of the person approving. A strategic loss-leader on
    //  a first placement with a new client is a real decision, and the variance
    //  reporting is there precisely so it stays visible.
    t_ok($ok(['bill_rate' => '50000', 'bill_basis' => 'MONTHLY', 'months' => '6', 'cost_rate' => '50000']),
        'break-even is allowed');
    t_ok($ok(['bill_rate' => '40000', 'bill_basis' => 'MONTHLY', 'months' => '6', 'cost_rate' => '60000']),
        'and so is a deliberate loss');
});

// ---------------------------------------------------------------------------
//  THE HANDLER honours the guard, and hands back what was typed.
// ---------------------------------------------------------------------------
t_nothrow('the route refuses before it writes, and does not lose the typed figures', function () {
    $src = (string) @file_get_contents(dirname(__DIR__) . '/lib/ops.php');
    $from = strpos($src, "if (\$route === 'candidate-commercial')");
    t_ok($from !== false, 'ARMING · the route was located');
    if ($from === false) return;
    $body = substr($src, $from, 3000);

    $guard = strpos($body, 'assignment_commercial_refusal');
    $write = strpos($body, "asg_status='APPROVED'");
    t_ok($guard !== false, 'the route asks the guard');
    t_ok($write !== false, 'ARMING · the approving UPDATE was located');
    t_ok($guard !== false && $write !== false && $guard < $write,
        'and asks it BEFORE writing — a check after the UPDATE would guard nothing');
    t_ok(strpos($body, "\$_SESSION['asg_form']") !== false,
        'a refused approval keeps what was typed, so nobody re-keys the form');

    $view = (string) @file_get_contents(dirname(__DIR__) . '/views/ops/candidate_detail.php');
    t_ok(strpos($view, "\$_SESSION['asg_form']") !== false, 'and the screen reads it back');
    t_ok(strpos($view, "unset(\$_SESSION['asg_form'])") !== false,
        'then clears it, so an old refusal cannot haunt the next visit');
});
