<?php
// ============================================================================
//  A CTC IN, A WHOLE SALARY STRUCTURE OUT.
//
//  The compensation engine only ever worked bottom-up: a person typed EVERY
//  fixed component — Basic, then Conveyance, then Special allowance — and it
//  derived the percentage ones from them. That is the wrong way round for the
//  conversation that actually happens. Nobody negotiates a Basic. They agree a
//  CTC, and the structure has to follow from it.
//
//  The owner asked for exactly this: "Once the CTC of the candidate is entered
//  it must create the salary structure as per the company configuration."
//
//  The one thing these tests care about above all others: THE TOTAL MUST LAND
//  EXACTLY ON THE CTC. A structure that comes to 11,99,880 against an agreed
//  12,00,000 is not a rounding curiosity — it is a number that cannot go in an
//  offer letter, and it is the failure mode a naive implementation produces.
// ============================================================================

t_section('Salary structure — solved from the CTC');

comp_migrate();

t_ok(function_exists('comp_solve_from_ctc'), 'ARMING · the solver exists');
t_ok(count(comp_defs(true)) > 3, 'ARMING · ' . count(comp_defs(true)) . ' components are configured to solve against');

$near = function ($a, $b, $tol = 0.011) { return abs((float) $a - (float) $b) < $tol; };

// ---------------------------------------------------------------------------
//  1 · IT CLOSES ON THE NUMBER
// ---------------------------------------------------------------------------
t_nothrow('the structure adds up to exactly the CTC that was asked for', function () use ($near) {
    foreach ([25000, 50000, 75000, 100000, 123456.78, 999999] as $ctc) {
        $r = comp_solve_from_ctc($ctc);
        t_ok(empty($r['err']), 'solved for ' . $ctc . (empty($r['err']) ? '' : ' — ' . $r['err']));
        if (!empty($r['err'])) continue;
        t_ok($near($r['ctc'], $ctc), 'and lands on it exactly: asked ' . $ctc . ', got ' . round((float) $r['ctc'], 2));
    }
});

t_nothrow('an annual CTC — what people actually negotiate — works too', function () use ($near) {
    $r = comp_solve_from_annual_ctc(1200000);
    t_ok(empty($r['err']), 'twelve lakh a year solves' . (empty($r['err']) ? '' : ' — ' . $r['err']));
    t_ok($near($r['annual_ctc'] ?? 0, 1200000, 0.2), 'and the annual total comes back to 12,00,000 (got '
        . round((float) ($r['annual_ctc'] ?? 0), 2) . ')');
    t_ok($near($r['monthly_ctc'] ?? 0, 100000), 'with a monthly CTC of 1,00,000');
});

t_nothrow('it still closes when a component is a percentage of GROSS', function () use ($near) {
    //  THE CASE THE CONVERGENCE LOOP EXISTS FOR, and the one the shipped
    //  configuration does not contain — so without this test the loop was
    //  untested code, and reducing it to a single pass broke nothing. A
    //  percentage-of-gross component makes the balancing figure feed back into
    //  the very total it is trying to complete, so one pass lands short.
    $id = comp_save(0, ['code' => 'ZZGROSSBONUS', 'name' => 'ZZ Gross-linked bonus',
                        'section' => 'EARNING', 'calc' => 'PCT_GROSS', 'rate' => 10, 'sort' => 900]);
    try {
        t_ok($id > 0, 'ARMING · a percentage-of-gross component was added');
        $hasIt = false;
        foreach (comp_defs(true) as $d) if ($d['calc'] === 'PCT_GROSS') $hasIt = true;
        t_ok($hasIt, 'ARMING · and the engine can see it');

        foreach ([50000, 100000, 250000] as $ctc) {
            $r = comp_solve_from_ctc($ctc);
            t_ok(empty($r['err']), "solves at $ctc with a gross-linked component"
                . (empty($r['err']) ? '' : ' — ' . $r['err']));
            t_ok($near($r['ctc'], $ctc),
                "and STILL lands exactly on $ctc (got " . round((float) ($r['ctc'] ?? 0), 2) . ')');
        }
        //  And the feedback component really was non-zero, or the case is fake.
        $r = comp_solve_from_ctc(100000);
        $bonus = 0.0;
        foreach ($r['lines'] as $l) if ($l['code'] === 'ZZGROSSBONUS') $bonus = (float) $l['amount'];
        t_ok($bonus > 0, 'ARMING · the gross-linked component carries a real amount (' . round($bonus, 2) . ')');
    } finally {
        db()->prepare("DELETE FROM salary_component_defs WHERE code=?")->execute(['ZZGROSSBONUS']);
    }
    t_eq((int) ops_val("SELECT COUNT(*) FROM salary_component_defs WHERE code='ZZGROSSBONUS'"), 0,
        'fixture removed');
});

// ---------------------------------------------------------------------------
//  2 · IT FOLLOWS THE COMPANY'S CONFIGURATION, NOT A HARD-CODED TEMPLATE
// ---------------------------------------------------------------------------
t_nothrow('Basic follows the configured percentage of CTC', function () use ($near) {
    $was = (string) setting_get('comp_basic_pct', '');
    try {
        foreach ([40, 50, 35] as $pct) {
            setting_set('comp_basic_pct', (string) $pct);
            $c = &settings_cache(); $c['comp_basic_pct'] = (string) $pct;
            t_eq(comp_basic_pct(), (float) $pct, "the policy reads back as $pct%");
            $r = comp_solve_from_ctc(100000);
            t_ok($near($r['inputs']['BASIC'] ?? 0, 100000 * $pct / 100),
                "Basic is $pct% of CTC (got " . ($r['inputs']['BASIC'] ?? 'none') . ')');
            t_ok($near($r['ctc'], 100000), "and the total still lands on the CTC at $pct%");
        }
    } finally {
        setting_set('comp_basic_pct', $was);
        $c = &settings_cache(); $c['comp_basic_pct'] = $was;
    }
});

t_nothrow('an absurd Basic percentage is refused rather than obeyed', function () {
    $was = (string) setting_get('comp_basic_pct', '');
    try {
        foreach (['0', '-10', '250', 'abc'] as $bad) {
            setting_set('comp_basic_pct', $bad);
            $c = &settings_cache(); $c['comp_basic_pct'] = $bad;
            t_eq(comp_basic_pct(), 40.0, "\"$bad\" falls back to the 40% default rather than breaking the structure");
        }
    } finally {
        setting_set('comp_basic_pct', $was);
        $c = &settings_cache(); $c['comp_basic_pct'] = $was;
    }
});

t_nothrow('the derived components really are derived, not invented', function () use ($near) {
    $r = comp_solve_from_ctc(100000);
    $by = [];
    foreach ($r['lines'] as $l) $by[$l['code']] = (float) $l['amount'];
    $basic = (float) $r['inputs']['BASIC'];
    foreach (comp_defs(true) as $d) {
        if ($d['calc'] !== 'PCT_BASIC') continue;
        t_ok($near($by[$d['code']] ?? -1, round($basic * (float) $d['rate'] / 100, 2)),
            $d['code'] . ' is ' . $d['rate'] . '% of Basic, as configured');
    }
});

// ---------------------------------------------------------------------------
//  3 · A PERSON CAN OVERRIDE A LINE AND THE TOTAL STILL HOLDS
// ---------------------------------------------------------------------------
t_nothrow('pinning a component re-solves the balance around it', function () use ($near) {
    $r = comp_solve_from_ctc(100000, ['CONVEY' => 5000]);
    t_ok(empty($r['err']), 'it solves with a pinned conveyance' . (empty($r['err']) ? '' : ' — ' . $r['err']));
    t_eq((float) $r['inputs']['CONVEY'], 5000.0, 'the pinned amount is honoured exactly');
    t_ok($near($r['ctc'], 100000), 'and the total STILL lands on the CTC — the balance absorbed the difference');
});

t_nothrow('pinning Basic overrides the percentage policy', function () use ($near) {
    $r = comp_solve_from_ctc(100000, ['BASIC' => 55000]);
    t_eq((float) $r['inputs']['BASIC'], 55000.0, 'a typed Basic wins over the configured percentage');
    t_ok($near($r['ctc'], 100000), 'and the structure still closes on the CTC');
});

// ---------------------------------------------------------------------------
//  4 · WHEN IT CANNOT BE DONE, IT SAYS SO
// ---------------------------------------------------------------------------
t_nothrow('a CTC that genuinely cannot be split fails loudly, not silently', function () {
    //  My first draft of this test asserted that a CTC of 1 must fail. It does
    //  not, and should not: in a structure made entirely of percentages, one
    //  rupee splits as cleanly as one lakh. Asserting otherwise would have been
    //  demanding a bug.
    $tiny = comp_solve_from_ctc(1);
    t_ok(empty($tiny['err']), 'a tiny CTC still splits when every component is a percentage');

    //  It genuinely cannot be done when a FIXED amount somebody pinned already
    //  exceeds the whole CTC. There is nothing left for the balance to absorb,
    //  and driving it negative would produce a structure that does not add up.
    $r = comp_solve_from_ctc(20000, ['CONVEY' => 50000]);
    t_ok(!empty($r['err']), 'a pinned component larger than the CTC is refused');
    if (!empty($r['err'])) {
        t_ok(strlen($r['err']) > 30, 'and the sentence explains what to change');
        t_ok(stripos($r['err'], 'more than the CTC') !== false || stripos($r['err'], 'pinned') !== false,
            'naming the actual problem rather than "invalid input"');
    }
});

t_nothrow('nothing and nonsense are refused', function () {
    foreach ([0, -5000, ''] as $bad) {
        $r = comp_solve_from_ctc($bad);
        t_ok(!empty($r['err']), 'refused: ' . var_export($bad, true));
    }
    t_ok(!empty(comp_solve_from_annual_ctc(0)['err']), 'and an annual CTC of zero too');
});

t_nothrow('a balancing component that cannot balance is refused with a reason', function () {
    //  A percentage component cannot absorb a remainder: changing it changes
    //  what it is a percentage of. Pointing the setting at one must not produce
    //  a structure that silently misses.
    $was = (string) setting_get('comp_balance_code', '');
    try {
        foreach (['HRA', 'PF_ER', 'NOT_A_CODE'] as $bad) {
            setting_set('comp_balance_code', $bad);
            $c = &settings_cache(); $c['comp_balance_code'] = $bad;
            $r = comp_solve_from_ctc(100000);
            t_ok(!empty($r['err']), "'$bad' cannot be the balancing component, and is refused");
        }
    } finally {
        setting_set('comp_balance_code', $was);
        $c = &settings_cache(); $c['comp_balance_code'] = $was;
    }
});

// ---------------------------------------------------------------------------
//  5 · IT DOES NOT DISTURB THE ENGINE IT SITS ON
// ---------------------------------------------------------------------------
t_nothrow('the existing bottom-up path is untouched', function () use ($near) {
    //  The solver is a new ENTRY POINT to comp_compute(), not a replacement.
    //  Anything already typing components by hand must behave exactly as before.
    $r = comp_compute(['BASIC' => 40000, 'CONVEY' => 1600, 'SPECIAL' => 8000]);
    t_ok($r['gross'] > 0 && $r['ctc'] >= $r['gross'], 'comp_compute still computes bottom-up');
    t_ok($near($r['ctc'], $r['gross'] + $r['employer']), 'and CTC is still gross plus employer cost');
    //  …and the solver's output is the SAME shape, so every existing reader works.
    $s = comp_solve_from_ctc(100000);
    foreach (['lines', 'gross', 'deductions', 'employer', 'ctc', 'net'] as $k)
        t_ok(array_key_exists($k, $s), "the solver returns '$k', like comp_compute");
});

t_nothrow('the statutory numbers stay configuration, never constants', function () {
    //  PF at 12%, gratuity at 4.81% and the rest change with the national budget,
    //  and mean nothing outside India — and this product is sold to TPIAs and
    //  project management companies who work internationally. If any of these
    //  were hard-coded in the solver, every overseas customer would need a code
    //  change.
    $src = (string) @file_get_contents(dirname(__DIR__) . '/lib/comp_config.php');
    $from = strpos($src, 'function comp_solve_from_ctc');
    $to   = strpos($src, 'function comp_solve_from_annual_ctc');
    $body = ($from !== false && $to !== false) ? substr($src, $from, $to - $from) : '';
    t_ok($body !== '', 'ARMING · the solver body was located');
    //  Strip the two places a bare number legitimately appears and means nothing
    //  statutory: the convergence loop's bound, and comments. The first draft of
    //  this check flagged `$i < 12` as if it were the PF rate.
    $scan = preg_replace('~^\s*//.*$~m', '', $body);
    $scan = preg_replace('~for \(\$i = 0; \$i < \d+; \$i\+\+\)~', '', $scan);
    foreach (['12', '4.81', '15000', '1800'] as $magic)
        t_ok(!preg_match('~[^0-9.]' . preg_quote($magic, '~') . '[^0-9.]~', $scan),
            "the solver hard-codes no statutory figure ($magic)");
    t_ok(strpos($body, 'comp_basic_pct()') !== false, 'the Basic policy is read from configuration');
    t_ok(strpos($body, 'comp_balance_code()') !== false, 'and so is the balancing component');
});

// ---------------------------------------------------------------------------
//  6 · IT IS REACHABLE. A solver nobody can use fixes nothing.
// ---------------------------------------------------------------------------
t_nothrow('the offer screen takes a CTC and builds the structure from it', function () {
    $src = (string) @file_get_contents(dirname(__DIR__) . '/lib/recruit_offer.php');
    t_ok(strpos($src, 'name="solve_ctc"') !== false, 'the salary form has a CTC box');
    t_ok(strpos($src, 'name="solve_period"') !== false, 'and says whether it is annual or monthly');
    t_ok(strpos($src, 'comp_solve_from_annual_ctc') !== false, 'annual is handled, because that is what people negotiate');

    $from = strpos($src, 'function sal_save');
    t_ok($from !== false, 'ARMING · sal_save was located');
    $body = substr($src, $from, 3000);
    t_ok(strpos($body, "\$post['solve_ctc']") !== false, 'sal_save reads the CTC');
    //  The solve must happen BEFORE the structure is computed and written.
    $solve = strpos($body, 'comp_solve_from');
    $write = strpos($body, 'INSERT INTO salary_structures');
    t_ok($solve !== false && $write !== false && $solve < $write,
        'and solves before writing, not after');
});

t_nothrow('typing a component alongside the CTC keeps that component', function () {
    $src  = (string) @file_get_contents(dirname(__DIR__) . '/lib/recruit_offer.php');
    $body = substr($src, strpos($src, 'function sal_save'), 3000);
    t_ok(strpos($body, '$pin') !== false, 'typed amounts are collected as pins');
    //  [^)]* cannot cross the "(float)" cast in the real call, so match the
    //  argument list loosely instead of pretending casts do not exist.
    t_ok(preg_match('~comp_solve_from_(annual_)?ctc\(.{0,40}\$pin\)~s', $body) === 1,
        'and passed to the solver, so an edited line survives the solve');
});

t_nothrow('a structure that cannot be solved is NOT written, and says why', function () {
    $src  = (string) @file_get_contents(dirname(__DIR__) . '/lib/recruit_offer.php');
    $body = substr($src, strpos($src, 'function sal_save'), 3000);
    t_ok(preg_match("~if \(!empty\(\\\$solved\['err'\]\)\) return~", $body) === 1,
        'sal_save returns the reason instead of writing a structure that does not add up');
    //  …and the route must not then report success over it.
    t_ok(strpos($src, "flash(\$salRes['err'], 'error')") !== false,
        'and the screen shows that reason rather than "Salary structure saved."');
});

t_nothrow('an administrator can change both policy rules, and rubbish is refused', function () {
    $v = (string) @file_get_contents(dirname(__DIR__) . '/views/ops/comp_setup.php');
    t_ok(strpos($v, 'name="comp_basic_pct"') !== false, 'the Basic percentage is editable on Compensation setup');
    t_ok(strpos($v, 'name="comp_balance_code"') !== false, 'and so is the balancing heading');
    //  The dropdown offers only what CAN balance — a percentage heading cannot.
    t_ok(strpos($v, "\$d['calc'] === 'FIXED' && \$d['section'] === 'EARNING'") !== false,
        'and it offers only fixed earnings, which are the only headings that can absorb a remainder');

    $h = (string) @file_get_contents(dirname(__DIR__) . '/lib/comp_config.php');
    $from = strpos($h, "\$do === 'save_policy'");
    t_ok($from !== false, 'ARMING · the handler exists');
    $body = substr($h, $from, 1400);
    t_ok(strpos($body, '$pct <= 0 || $pct > 100') !== false,
        'the handler validates the percentage rather than trusting the form');
    t_ok(strpos($body, "\$d['calc'] === 'FIXED'") !== false,
        'and re-checks the balancing heading server-side — a dropdown is not a security boundary');
});
