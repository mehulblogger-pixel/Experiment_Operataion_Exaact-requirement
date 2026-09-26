<?php
// ============================================================================
//  THE CERTIFICATE LIST TEACHES ITSELF — without un-teaching what it knows.
//
//  The picker replaced a free-text box because "CSWIP 3.1", "cswip3.1" and
//  "CSWIP Level 3.1" were three requirements to the system instead of one. That
//  fixed spelling and created a gap: a real certificate the seeded list had
//  never heard of could not be recorded at all, so it went into a notes field
//  where nothing matches on it.
//
//  Adding is therefore easy to get wrong in the direction that matters. These
//  tests are mostly about the DUPLICATE GUARD: the feature is only worth having
//  if typing a name the list already knows selects that certificate rather than
//  minting a rival spelling of it.
// ============================================================================

t_section('Certificates — the list learns, and does not duplicate');

$clCount = fn() => (int) ops_val("SELECT COUNT(*) FROM cx_prof_certifications");
$clFind  = fn($code) => ops_one("SELECT * FROM cx_prof_certifications WHERE code=?", [$code]);

t_nothrow('a certificate the list has never seen is added, and offered from then on', function () use ($clCount) {
    $before = $clCount();
    $r = req_cert_add('ZZ Weld Inspector Level 9', 'ZZTWI');
    t_ok(empty($r['err']), 'it was accepted' . (!empty($r['err']) ? ' (' . $r['err'] . ')' : ''));
    t_ok(!empty($r['created']), 'and recorded as newly created');
    t_eq($clCount(), $before + 1, 'exactly one row was added');

    // The picker must offer it immediately — a list that needs a reload has not
    // learned anything as far as the person filling the form is concerned.
    $opts = req_cert_options(true);
    t_ok(in_array('ZZ Weld Inspector Level 9 — ZZTWI', array_values($opts), true),
        'the picker offers it straight away, with its issuing body');
});

// ---------------------------------------------------------------------------
//  THE GUARD. Each of these is a spelling of the SAME certificate.
// ---------------------------------------------------------------------------
t_nothrow('the same certificate typed differently never creates a second row', function () use ($clCount) {
    $before = $clCount();
    foreach ([
        'zz weld inspector level 9',        // case
        'ZZ  Weld   Inspector  Level 9',    // spacing
        'ZZ-Weld-Inspector-Level-9',        // punctuation
        'Z.Z. Weld Inspector Level 9',      // dots
        '  ZZ Weld Inspector Level 9  ',    // padding
    ] as $variant) {
        $r = req_cert_add($variant);
        t_ok(empty($r['err']), 'accepted: ' . $variant);
        t_ok(empty($r['created']), 'matched the existing certificate rather than adding one: ' . $variant);
        t_eq($r['label'], 'ZZ Weld Inspector Level 9 — ZZTWI', 'and returns the canonical label: ' . $variant);
    }
    t_eq($clCount(), $before, 'not one extra row across all five spellings');
});

t_nothrow('a genuinely different certificate is still added', function () use ($clCount) {
    $before = $clCount();
    $r = req_cert_add('ZZ Weld Inspector Level 10');
    t_ok(!empty($r['created']), 'level 10 is not level 9');
    t_eq($clCount(), $before + 1, 'one row added');
});

// ---------------------------------------------------------------------------
//  Rubbish in.
// ---------------------------------------------------------------------------
t_nothrow('rubbish is refused with a sentence, not a crash', function () use ($clCount) {
    $before = $clCount();
    foreach ([['', 'empty'], ['   ', 'spaces only'], ['x', 'one character'], ['!!! ???', 'no letters or digits']] as [$bad, $what]) {
        $r = req_cert_add($bad);
        t_ok(!empty($r['err']), 'refused (' . $what . ')');
        t_ok(strlen((string) $r['err']) > 10, 'and says why (' . $what . ')');
    }
    $r = req_cert_add(str_repeat('A', 300));
    t_ok(!empty($r['err']), 'refused: absurdly long name');
    t_eq($clCount(), $before, 'nothing was written by any of them');
});

// ---------------------------------------------------------------------------
//  What the company added is distinguishable from what shipped.
// ---------------------------------------------------------------------------
t_nothrow('a learned certificate is marked as the company\'s, not as shipped', function () {
    $row = ops_one("SELECT * FROM cx_prof_certifications WHERE name=?", ['ZZ Weld Inspector Level 9']);
    t_ok(!empty($row), 'the row is there');
    t_eq((int) $row['is_system'], 0, 'is_system = 0, so an administrator can tell it apart from a seeded one');
    t_eq((int) $row['is_active'], 1, 'and it is active');
    t_ok(trim((string) $row['code']) !== '', 'it has a code');
});

t_nothrow('re-adding a DEACTIVATED certificate revives it instead of twinning it', function () use ($clCount) {
    db()->prepare("UPDATE cx_prof_certifications SET is_active=0 WHERE name=?")->execute(['ZZ Weld Inspector Level 10']);
    $before = $clCount();
    $r = req_cert_add('ZZ WELD INSPECTOR LEVEL 10');
    t_ok(empty($r['created']), 'it matched the deactivated row');
    t_eq($clCount(), $before, 'no second row');
    t_eq((int) ops_val("SELECT is_active FROM cx_prof_certifications WHERE name=?", ['ZZ Weld Inspector Level 10']), 1,
        'and it is active again — which is what typing it again means');
});

t_nothrow('two certificates whose names normalise alike still get distinct codes', function () {
    // "ZZ Alpha-Beta" and "ZZ Alpha Beta" are the SAME by the duplicate rule, so
    // this uses two names that differ in letters, to prove code allocation does
    // not collide when the normalised CODE would be the same length/shape.
    $a = req_cert_add('ZZ Code Clash One');
    $b = req_cert_add('ZZ Code Clash Two');
    t_ok(!empty($a['created']) && !empty($b['created']), 'both were added');
    t_ok($a['code'] !== $b['code'], 'their codes differ (' . $a['code'] . ' vs ' . $b['code'] . ')');
});

// ---------------------------------------------------------------------------
//  PERMISSION — writing to a company-wide master is a master-data act.
// ---------------------------------------------------------------------------
t_nothrow('adding is gated on the right to maintain the master, not on the requisition form', function () {
    $root = dirname(__DIR__);
    $jd   = (string) @file_get_contents($root . '/lib/recruit_jd.php');
    $view = (string) @file_get_contents($root . '/views/ops/requisition_form.php');

    //  Read ONLY this function's body. A loose /function ops_cert_add.*?X/s runs
    //  past the closing brace into the next function, which legitimately uses the
    //  coordinator band — and would have made the assertion below a false alarm.
    $from = strpos($jd, 'function ops_cert_add');
    $to   = strpos($jd, 'function ops_jd_generate');
    $body = ($from !== false && $to !== false && $to > $from) ? substr($jd, $from, $to - $from) : '';
    t_ok($body !== '', 'the route function was located');
    t_ok(strpos($body, 'connect_qualtax_manage_can()') !== false,
        'the route asks the taxonomy-maintenance gate');
    t_ok(strpos($body, 'csrf_ok') !== false,
        'and checks CSRF, like every other write');
    // The requisition form's own band is is_coordinator_level(); the add control
    // must NOT ride on it, or a coordinator would silently gain a master-data
    // right the permission matrix does not grant.
    t_ok(strpos($body, 'is_coordinator_level') === false,
        'it does NOT ride on the coordinator band the form around it uses');
    t_ok(strpos($view, 'connect_qualtax_manage_can') !== false,
        'and the control is only rendered for somebody who may use it');
});

// ---- remove every fixture --------------------------------------------------
t_nothrow('fixtures removed', function () {
    db()->exec("DELETE FROM cx_prof_certifications WHERE name LIKE 'ZZ %'");
    req_cert_options(true);
    t_eq((int) ops_val("SELECT COUNT(*) FROM cx_prof_certifications WHERE name LIKE 'ZZ %'"), 0,
        'no temporary certificates remain');
});
