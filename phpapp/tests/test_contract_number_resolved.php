<?php
// ============================================================================
//  A WORK ORDER THAT HAS A CONTRACT NUMBER MUST NOT SAY "PENDING".
//
//  Reported: "It was allocated rightly to Mr. Ketan as per the flow. But even
//  when contract number is already there it shows contract number pending."
//
//  calls.contract_number is a COPY, filled when the work order is raised. A
//  contract number registered afterwards — which is the normal order, because
//  accounts opens the number while the coordinator is already scheduling —
//  never reached that copy. So the register went on showing "contract no.
//  pending" for an order that plainly had one, for ever.
//
//  contract_number_for() has always resolved this properly: the job first, then
//  the work order, then the quotation behind it. The register simply never
//  asked. This pins both halves — the resolver's order of preference, and the
//  register using it.
// ============================================================================

t_section('Work orders — the contract number is resolved, not remembered');

t_ok(function_exists('contract_number_for'), 'ARMING · the resolver exists');

// ---------------------------------------------------------------------------
//  1 · THE ORDER OF PREFERENCE
// ---------------------------------------------------------------------------
t_nothrow('the job wins, then the work order, then the quotation', function () {
    //  Most specific first: a number written on the job is the one that job was
    //  executed under, whatever the paperwork above it later said.
    t_eq(contract_number_for(['contract_number' => 'JOB-1'], ['contract_number' => 'CALL-1']), 'JOB-1',
        'the job’s own number wins');
    t_eq(contract_number_for(['contract_number' => ''], ['contract_number' => 'CALL-1']), 'CALL-1',
        'then the work order’s');
    t_eq(contract_number_for([], []), '', 'and nothing invented when there is nothing to find');
});

t_nothrow('blank and whitespace are not numbers', function () {
    t_eq(contract_number_for(['contract_number' => '   '], ['contract_number' => 'CALL-2']), 'CALL-2',
        'a whitespace copy does not mask the real one');
    t_eq(contract_number_for(['contract_number' => null], ['contract_number' => null]), '',
        'nulls are handled, not fatal');
});

// ---------------------------------------------------------------------------
//  2 · THE REGISTER ASKS
// ---------------------------------------------------------------------------
t_nothrow('the work-order register resolves the number in its own query', function () {
    $src = (string) @file_get_contents(dirname(__DIR__) . '/lib/ops.php');
    t_ok(strpos($src, '$resolvedContract') !== false, 'the register builds a resolved column');
    $from = strpos($src, '$resolvedContract =');
    t_ok($from !== false, 'ARMING · the expression was located');
    $expr = substr($src, $from, 800);
    //  The SAME order of preference as contract_number_for(), or the register and
    //  the record screen would disagree about the same work order.
    $job   = strpos($expr, 'j4.contract_number');
    $quote = strpos($expr, 'q4.contract_number');
    $own   = strpos($expr, "NULLIF(c.contract_number,'')");
    t_ok($own !== false && $job !== false && $quote !== false, 'all three sources are consulted');
    t_ok($own < $job && $job < $quote,
        'in the same order the resolver uses: the call’s own copy, then the job, then the quotation');
    //  One query for the page, not one per row: a register of 300 work orders
    //  must not become 900 round trips.
    t_ok(strpos($src, 'foreach ($rows') === false || strpos($expr, 'ops_val') === false,
        'and it is resolved in SQL, not by querying inside a loop');
});

t_nothrow('the register PRINTS the resolved number, with a safe fallback', function () {
    $v = (string) @file_get_contents(dirname(__DIR__) . '/views/ops/calls.php');
    t_ok(strpos($v, "resolved_contract_number") !== false, 'the view reads the resolved column');
    t_ok(strpos($v, "?? \$c['contract_number']") !== false,
        'and falls back to the row’s own copy for any caller not yet updated to select it');
    //  The badge must hang off the RESOLVED value, not the raw one, or the fix
    //  changes what is displayed without changing when the warning appears.
    $cnum  = strpos($v, '$cNum = trim(');
    $badge = strpos($v, 'contract no. pending');
    t_ok($cnum !== false && $badge !== false && $cnum < $badge,
        'the "pending" badge is decided by the resolved value');
});

// ---------------------------------------------------------------------------
//  3 · NAVIGATION — clients and vendors are reachable from Operations
// ---------------------------------------------------------------------------
t_section('Operations — who the work is for is one click away');

t_nothrow('the Operations screen offers the client and vendor registers', function () {
    //  Reported as missing from Operations. They were reachable, but only behind
    //  a tile called "Directory", which does not say what is inside it.
    $v = (string) @file_get_contents(dirname(__DIR__) . '/views/ops/operations_home.php');
    t_ok(strpos($v, 'href="/clients"') !== false, 'the client register has a door on Operations');
    t_ok(strpos($v, 'href="/vendors"') !== false, 'and so does the vendor register');
    t_ok(strpos($v, "can('mod.clients.view')") !== false, 'each behind the permission that owns it');
    t_ok(strpos($v, "can('mod.vendors.view')") !== false, 'both of them');
});

t_nothrow('and they are in the left rail, under Operations', function () {
    $n = (string) @file_get_contents(dirname(__DIR__) . '/lib/navindex.php');
    $from = strpos($n, "\$A = 'Operations';");
    t_ok($from !== false, 'ARMING · the Operations rail group was located');
    $body = substr($n, $from, 2200);
    t_ok(strpos($body, "'/clients', \$A") !== false, 'Clients is in the Operations rail group');
    t_ok(strpos($body, "'/vendors', \$A") !== false, 'Vendors is too');
    //  Doors, not a second copy: the registers are still maintained in Directory.
    $d = (string) @file_get_contents(dirname(__DIR__) . '/lib/areas.php');
    t_ok(strpos($d, "'/clients', 'The client register.'") !== false,
        'and the Directory area still owns them — these are doors, not a duplicate register');
});
