<?php
// ============================================================================
//  ONE WORD FOR ONE THING, ON EVERY SCREEN.
//
//  The Recruitment Command Centre hard-coded "requirement" while every register
//  said "requisition". Same record, two names, and neither obeyed
//  Admin → Terminology — so a workspace that renamed the object saw its choice
//  ignored on the busiest recruitment screen, and an owner reading the two
//  screens side by side reasonably concluded they were different objects.
//
//  The rule these tests hold: a user-facing recruitment screen names the object
//  by ASKING the terminology engine, never by typing the word. That is what
//  makes Admin → Terminology mean something, and it is the only thing that stops
//  the two vocabularies drifting apart again.
// ============================================================================

t_section('Recruitment terminology — the screens ask, they do not assume');

// ---------------------------------------------------------------------------
//  1 — The shipped default, and the packs.
// ---------------------------------------------------------------------------
t_nothrow('the shipped word is Requisition', function () {
    t_eq(TERM_DEFAULTS['requisition'][0], 'Requisition', 'singular');
    t_eq(TERM_DEFAULTS['requisition'][1], 'Requisitions', 'plural');
});

t_nothrow('no industry pack calls two different objects the same thing', function () {
    if (!defined('TERM_PACKS') && !function_exists('term_packs')) { t_ok(true, 'no packs on this build — nothing to check'); return; }
    $packs = defined('TERM_PACKS') ? TERM_PACKS : term_packs();
    $checked = 0;
    foreach ($packs as $key => $pack) {
        $terms = $pack['terms'] ?? [];
        if (!$terms) continue;
        $seen = [];
        foreach ($terms as $obj => $pair) {
            $word = strtolower(trim((string) ($pair[0] ?? '')));
            if ($word === '') continue;
            if (isset($seen[$word])) {
                t_ok(false, "pack '$key' gives both '{$seen[$word]}' and '$obj' the word “{$pair[0]}”");
            }
            $seen[$word] = $obj;
        }
        $checked++;
    }
    t_ok($checked > 0, "every industry pack uses a distinct word per object ($checked packs checked)");
});

t_nothrow('the staffing packs say Job Order, not Requirement', function () {
    if (!defined('TERM_PACKS') && !function_exists('term_packs')) { t_ok(true, 'no packs on this build'); return; }
    $packs = defined('TERM_PACKS') ? TERM_PACKS : term_packs();
    foreach (['recruitment', 'manpower'] as $k) {
        if (!isset($packs[$k]['terms']['requisition'])) { t_ok(false, "pack '$k' does not name the requisition at all"); continue; }
        $w = $packs[$k]['terms']['requisition'][0];
        t_eq($w, 'Job Order', "pack '$k' names it Job Order");
    }
    //  "Requirement" is retired for this object specifically: it collides with
    //  the CRITERIA fields on the requisition itself (certificates required,
    //  minimum qualification), so the record and its own fields shared a word.
    foreach ($packs as $k => $pack) {
        $w = strtolower((string) ($pack['terms']['requisition'][0] ?? ''));
        t_ok($w !== 'requirement', "pack '$k' does not call the requisition a “Requirement”");
    }
});

// ---------------------------------------------------------------------------
//  2 — THE SCREENS. This is the assertion that would have caught the drift.
// ---------------------------------------------------------------------------
t_nothrow('no recruitment screen hard-codes the object\'s name', function () {
    $root  = dirname(__DIR__);
    $files = [
        'views/ops/recruitment_cc.php',
        'views/ops/requisition_list.php',
        'views/ops/requisition_detail.php',
        'views/ops/hiring_request_list.php',
        'views/ops/hiring_request.php',
    ];
    //  ARMING. The first draft of this list named a file that does not exist
    //  ('hiring_requests.php' — the real one is hiring_request_list.php) and the
    //  loop skipped it in silence, so that screen was never checked and the test
    //  still went green. A missing file is now a FAILURE, not a skip: a test that
    //  quietly checks nothing is worse than no test.
    foreach ($files as $rel) {
        $f = $root . '/' . $rel;
        t_ok(is_file($f), 'ARMING · ' . $rel . ' exists and is being checked');
        if (!is_file($f)) continue;
        $src = (string) file_get_contents($f);

        //  Strip what is NOT user-facing: PHP comments, and the URLs/route names
        //  that legitimately contain the word (/requisition-new, $req['id'] …).
        $vis = preg_replace('~/\*.*?\*/~s', '', $src);
        $vis = preg_replace('~^\s*//.*$~m', '', $vis);
        $vis = preg_replace('~//[^\n\'"]*$~m', '', $vis);
        //  Wire identifiers, not prose: a route, a form field's name, a submit
        //  value the handler switches on ("raise-requisition"), a DOM id. Renaming
        //  the object must NOT rename these — they are contracts with the code.
        $vis = preg_replace('~\b(href|action|name|value|id|for|data-[\w-]+)="[^"]*"~i', '', $vis);
        $vis = preg_replace('~\$[A-Za-z_]\w*~', '', $vis);           // variables
        //  A defensive fallback — function_exists('Tl') ? Tl('x') : 'requisition'
        //  — is not the screen printing a literal; it is what it prints when the
        //  engine is absent. Strip those resolutions so the check stays about
        //  text a user can actually read.
        //  Line-scoped: on any line that resolves a term defensively, drop the
        //  ternary's else-branch string. Matching to a ';' missed the inline
        //  inline echo-tag form, which ends in a close-tag rather than a ';'.
        $vis = implode("\n", array_map(function ($ln) {
            return (strpos($ln, 'function_exists(') !== false && strpos($ln, '?') !== false)
                ? preg_replace("~:\s*'[^']*'~", '', $ln) : $ln;
        }, explode("\n", $vis)));
        $vis = preg_replace('~\b(requisition|hiring_request|candidate)\b(?=\s*[\'"]?\s*[\),\]])~i', '', $vis); // term keys

        foreach (['requirement' => 'Requirement', 'requisition' => 'Requisition'] as $needle => $label) {
            $n = preg_match_all('~\b' . $needle . 's?\b~i', $vis);
            t_ok($n === 0,
                basename($rel) . ' does not print “' . $label . '” as a literal'
                . ($n ? ' — found ' . $n . ' time(s); use TH()/THP()/Tl()/Tlp() instead' : ''));
        }
    }
});

t_nothrow('the Command Centre asks the terminology engine', function () {
    $src = (string) @file_get_contents(dirname(__DIR__) . '/views/ops/recruitment_cc.php');
    t_ok(strpos($src, "Tl('requisition')") !== false || strpos($src, "TH('requisition')") !== false,
        'it resolves the singular from the engine');
    t_ok(strpos($src, "Tlp('requisition')") !== false || strpos($src, "THP('requisition')") !== false,
        'and the plural');
    t_ok(strpos($src, "Tl('hiring_request')") !== false,
        'and names the hiring request from the engine too');
});

t_nothrow('the recruitment libraries do not type the object\'s name into a sentence', function () {
    //  A library that COMPOSES a user-facing sentence is the same bug as a view
    //  that prints one — and the drift on the requisition detail screen was
    //  partly coming from here (lib/position.php's manpower notice said
    //  "This requisition…" and lib/recruitpipe.php said "Once a requirement is
    //  linked"). So these four are held to the same rule as the screens.
    $root = dirname(__DIR__);
    $libs = ['lib/position.php', 'lib/hiringreq.php', 'lib/recruit.php', 'lib/recruitpipe.php'];

    //  DELIBERATE EXCLUSIONS, listed so the line is explicit rather than implied:
    //
    //   * SQL and schema — 'requisitions' is a table name. Renaming the word on
    //     screen must never rename a table.
    //   * AUDIT AND REFUSAL TEXT (hreq_audit / 'Refused: …') — stored once and
    //     read years later. If it followed the current wording, entries written
    //     before and after a rename would disagree about what happened, which is
    //     worse than a stale word. Audit text stays canonical on purpose.
    //   * SEEDED DATA — a pipeline template's description is written into the
    //     database at seed time, so asking the engine there would not follow a
    //     later rename anyway.
    //   * HREQ_AMBIGUOUS — the constant that DETECTS the ambiguous word. It has
    //     to contain the word in order to look for it.
    //   * "approval requirement", "statutory requirement" — ordinary English for
    //     a rule, not the name of this record.
    $allow = '~(requisition_status|requisition_type|requisition_id|requisition_allocations'
           . '|raise-requisition|/requisition|FROM requisitions|INTO requisitions|requisitions SET'
           . '|JOIN requisitions|UPDATE requisitions|TABLE requisitions|hiring_request'
           . '|HREQ_AMBIGUOUS|Refused|approval requirement|statutory requirement'
           . '|Project-specific requirement|Recruitment Requisitions?|Marketplace Requirements?'
           . '|Staff Requisition|Paste the requirement text|\{req\}'
           . "|'requisition'|'requisitions'|requisition to joining)~i";

    $checked = 0;
    foreach ($libs as $rel) {
        $f = $root . '/' . $rel;
        t_ok(is_file($f), 'ARMING · ' . $rel . ' exists and is being checked');
        if (!is_file($f)) continue;
        $checked++;
        $bad = [];
        foreach (explode("\n", (string) file_get_contents($f)) as $i => $ln) {
            if (preg_match('~^\s*(//|\*|/\*)~', $ln)) continue;                 // comments
            //  A SEED-TABLE ROW: ['SRF', 'Requisition', 'gate', 'HR_MANAGER'].
            //  Pipeline stage names are written into the database once, at seed
            //  time, so asking the engine here could not follow a later rename —
            //  and renaming a seeded stage would not rename the rows already
            //  stored. Same reasoning as audit text.
            if (preg_match('~^\s*\[\s*\x27[A-Z0-9_]+\x27\s*,~', $ln)) continue;
            //  A DEFENSIVE FALLBACK — function_exists('TH') ? TH('x') : 'Requisition'
            //  — is not the code typing the name; it is what it prints if the
            //  engine is absent. Drop the else-branch, as the view scan does.
            if (strpos($ln, 'function_exists(') !== false && strpos($ln, '?') !== false)
                $ln = preg_replace('~:\s*\x27[^\x27]*\x27~', '', $ln);
            //  Only SINGLE-QUOTED PROSE: a sentence with a space in it. A bare
            //  'requisitions' identifier has no space and is excluded above.
            if (!preg_match_all('~\x27([^\x27]*\b(requisitions?|requirements?)\b[^\x27]*)\x27~i', $ln, $m)) continue;
            foreach ($m[1] as $lit) {
                //  PROSE vs IDENTIFIER. Prose has a space in it, or starts with a
                //  capital — this codebase writes SQL and array keys in lower case
                //  ('requisitions', 'requisition_status') and sentences with a
                //  capital ('Requisition R-12 raised…'). Requiring a space alone
                //  missed exactly that second shape: a one-word fragment
                //  concatenated onto a variable.
                $t = trim($lit);
                $isProse = strpos($t, ' ') !== false || preg_match('~^[A-Z]~', $t);
                if (!$isProse) continue;
                if (preg_match($allow, $lit)) continue;
                $bad[] = basename($rel) . ':' . ($i + 1) . '  "' . trim($lit) . '"';
            }
        }
        t_ok($bad === [], $bad === []
            ? basename($rel) . ' composes no sentence that types the name'
            : basename($rel) . ' types the object name into ' . count($bad) . ' sentence(s):'
              . "\n      " . implode("\n      ", $bad));
    }
    t_ok($checked === count($libs), 'ARMING · all ' . count($libs) . ' recruitment libraries were read');
});

t_nothrow('no help sentence shows a raw token, and each names other objects the workspace\'s way', function () {
    t_ok(function_exists('T_HELP'), 'ARMING · T_HELP exists');
    if (!function_exists('T_HELP')) return;
    $n = 0; $withToken = 0;
    foreach (TERM_DEFAULTS as $k => $d) {
        $raw = (string) ($d[3] ?? '');
        if ($raw === '') continue;
        $n++;
        if (strpos($raw, '{') !== false) $withToken++;
        $h = T_HELP($k);
        t_ok(strpos($h, '{') === false, "the help for '$k' has no unresolved token");
    }
    t_ok($n > 3, "ARMING · $n help sentences checked");
    t_ok($withToken > 0, "ARMING · $withToken sentence(s) name another object by token, so there is something to resolve");

    //  And the named object follows a rename.
    $was = (string) setting_get('terms', '');
    try {
        $ov = ['requisition' => ['Vacancy', 'Vacancies']];
        setting_set('terms', json_encode($ov)); term_overrides($ov);
        $h = T_HELP('hiring_request');
        t_ok(stripos($h, 'vacancy') !== false,
            'the hiring-request help names the record the workspace\'s way (got: ' . $h . ')');
        t_ok(stripos($h, 'requisition') === false, 'and not ours');
    } finally {
        setting_set('terms', $was);
        term_overrides($was ? (json_decode($was, true) ?: []) : []);
    }
});

t_nothrow('every refusal message a user can be shown is fully resolved', function () {
    //  The static scan above cannot see this: if rcv_msg() stopped substituting,
    //  the code would still contain no typed word and the scan would stay green
    //  while users read a literal "{req}" on screen. This is the behavioural half
    //  of the same rule, and it caught exactly that mutation.
    t_ok(function_exists('rcv_msg'), 'ARMING · the accessor exists');
    if (!function_exists('rcv_msg')) return;
    t_ok(defined('RCV_CODES') && count(RCV_CODES) > 5, 'ARMING · ' . count(RCV_CODES) . ' refusal codes to check');

    $withToken = 0;
    foreach (RCV_CODES as $code => $canonical) {
        $msg = rcv_msg($code);
        if (strpos($canonical, '{') !== false) $withToken++;
        t_ok(strpos($msg, '{') === false && strpos($msg, '}') === false,
            $code . ' has no unresolved placeholder left in it');
        t_ok(trim($msg) !== '' && strlen($msg) > 8, $code . ' still reads as a sentence');
    }
    t_ok($withToken > 0, "ARMING · $withToken message(s) actually carry a placeholder, so the check has something to resolve");

    //  And it resolves to THIS workspace's word, not ours.
    $was = (string) setting_get('terms', '');
    try {
        $ov = ['requisition' => ['Vacancy', 'Vacancies']];
        setting_set('terms', json_encode($ov)); term_overrides($ov);
        $m = rcv_msg('BLOCKED');
        t_ok(stripos($m, 'vacancy') !== false, 'a renamed workspace reads its own word in the refusal (got: ' . $m . ')');
        t_ok(stripos($m, 'requisition') === false && stripos($m, 'requirement') === false,
            'and never ours');
    } finally {
        setting_set('terms', $was);
        term_overrides($was ? (json_decode($was, true) ?: []) : []);
    }
});

// ---------------------------------------------------------------------------
//  3 — Renaming the object really does change the screen.
//      The point of all of the above: Admin → Terminology must WORK.
//
//      These use the SAME key and the SAME cache-setter the save path uses
//      (`terms`, then term_overrides($ov) — see term_save_post() and
//      term_apply_pack()). An earlier draft of this test wrote a key nothing
//      reads (`terms_custom`), so it passed while proving nothing. Asserting
//      through the real key is the whole value of this block.
// ---------------------------------------------------------------------------
$termRestore = function () {
    $raw = function_exists('setting_get') ? (string) setting_get('terms', '') : '';
    setting_set('terms', $raw);
    term_overrides($raw ? (json_decode($raw, true) ?: []) : []);
};

t_nothrow('renaming the object by hand changes what every screen is told to print', function () use ($termRestore) {
    $was = (string) setting_get('terms', '');
    try {
        $ov = ['requisition' => ['Vacancy', 'Vacancies']];
        setting_set('terms', json_encode($ov));
        term_overrides($ov);                                  // exactly what the save path does

        t_eq(T('requisition'),   'Vacancy',   'T() — the stored singular');
        t_eq(TP('requisition'),  'Vacancies', 'TP() — the stored plural');
        t_eq(TH('requisition'),  'Vacancy',   'TH() — the heading form the screens use');
        t_eq(THP('requisition'), 'Vacancies', 'THP() — the heading plural');
        t_eq(Tl('requisition'),  'vacancy',   'Tl() — mid-sentence singular');
        t_eq(Tlp('requisition'), 'vacancies', 'Tlp() — mid-sentence plural');
        t_eq(T_NEW('requisition'), 'New vacancy', 'the New… button label follows too');

        //  Nothing still leaks the old word through a sibling helper.
        if (function_exists('T_REG'))
            t_ok(stripos(T_REG('requisition'), 'requisition') === false,
                'T_REG() — the register heading no longer says “requisition”');
        if (function_exists('T_DETAIL'))
            t_ok(stripos(T_DETAIL('requisition', 'RQ-1'), 'requisition') === false,
                'T_DETAIL() — the record heading no longer says “requisition”');
    } finally {
        setting_set('terms', $was);
        term_overrides($was ? (json_decode($was, true) ?: []) : []);
    }
});

t_nothrow('choosing the staffing pack renames it to Job Order on every screen', function () use ($termRestore) {
    if (!function_exists('term_apply_pack')) { t_ok(true, 'no packs on this build'); return; }
    $was = (string) setting_get('terms', '');
    $wasPack = (string) setting_get('terms_pack', '');
    try {
        t_ok(term_apply_pack('recruitment'), 'the recruitment pack applied');
        t_eq(T('requisition'),  'Job Order',  'the singular the screens print');
        t_eq(TP('requisition'), 'Job Orders', 'and the plural');
        t_eq(Tl('requisition'), 'job order',  'mid-sentence');
        t_eq(term_pack_current(), 'recruitment', 'and the screen can show which pack is in force');
    } finally {
        setting_set('terms', $was);
        setting_set('terms_pack', $wasPack);
        term_overrides($was ? (json_decode($was, true) ?: []) : []);
    }
});

t_nothrow('with nothing overridden the shipped word comes back', function () use ($termRestore) {
    $termRestore();
    $ov = term_overrides();
    if (!empty($ov['requisition'])) { t_ok(true, 'this workspace has renamed it — default check not applicable'); return; }
    t_eq(T('requisition'),  'Requisition',  'default singular');
    t_eq(TP('requisition'), 'Requisitions', 'default plural');
});

// ---------------------------------------------------------------------------
//  4 — ADR-001: the Command Centre must not point at a door the policy shut.
// ---------------------------------------------------------------------------
t_nothrow('the Command Centre follows the direct-path policy', function () {
    $src = (string) @file_get_contents(dirname(__DIR__) . '/views/ops/recruitment_cc.php');
    t_ok(strpos($src, 'hreq_direct_path_allowed') !== false,
        'it asks whether the direct route is open before offering it');
    //  When the policy is on, the primary button must lead to the hiring request.
    t_ok(preg_match('~ccDirect.*?/hiring-request~s', $src) === 1,
        'and offers the hiring request instead when it is not');
});
