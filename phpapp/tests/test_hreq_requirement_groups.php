<?php
// ---------------------------------------------------------------------------
//  UAT A1 — "ALL OF" vs "ANY ONE OF".
//
//    "Still unable to add multiple skills, multiple qualification with and or
//     selection."
//
//  Both lines were one box, so "Welding inspection, CSWIP 3.1, offshore" meant
//  three things a candidate must ALL have, and there was no way to write what a
//  real requirement says:
//
//      CSWIP 3.1  AND  (B.E. Mechanical OR Diploma with 10 years)
//
//  A recruiter therefore over-specified and screened out people who could do
//  the job, or under-specified and let through people who could not.
// ---------------------------------------------------------------------------
t_section('A1 — requirements with and / or');

t_ok(function_exists('hreq_req_groups_text'), 'groups can be rendered as a sentence');
t_ok(function_exists('hreq_req_groups_from_post'), 'and read off the form');
t_ok(function_exists('hreq_req_groups_from_text'), 'and recovered from an old plain-text value');

$T = fn($g) => hreq_req_groups_text($g);

// ---- the sentence people actually write ----------------------------------
t_eq($T([['mode' => 'ALL', 'items' => ['CSWIP 3.1']],
         ['mode' => 'ANY', 'items' => ['B.E. Mechanical', 'Diploma with 10 years']]]),
    'CSWIP 3.1 AND (B.E. Mechanical OR Diploma with 10 years)',
    '*** the requirement the owner could not express is now expressible');
t_eq($T([['mode' => 'ANY', 'items' => ['B.E.', 'Diploma']]]), 'B.E. OR Diploma',
    'a single "any one of" needs no brackets');
t_eq($T([['mode' => 'ALL', 'items' => ['A', 'B']], ['mode' => 'ANY', 'items' => ['C', 'D']]]),
    '(A, B) AND (C OR D)', 'two groups are bracketed, because precedence would otherwise be guesswork');
t_eq($T([]), '', 'nothing in, nothing out');

// ---- THE ASSERTION THAT MATTERS MOST -------------------------------------
//  essential_skills and min_qualification are MATERIAL fields: a change to
//  either sends an approved request back for re-approval. Every request written
//  before the builder holds a COMMA LIST, because that is what the old box
//  asked for. If opening one and saving it rewrote the punctuation, the version
//  diff would demand re-approval for a change nobody made.
//
//  The first version of this code did exactly that — "A, B" came back as
//  "A AND B" — and it was caught by round-tripping real values rather than by
//  assuming. These are the values that must survive untouched.
foreach ([
    'NDT Level II',
    'Welding inspection, CSWIP 3.1, offshore experience',
    'Degree',
    'A, B',
    '',
] as $plain) {
    $g    = hreq_req_groups_from_text($plain);
    $back = $g ? $T($g) : $plain;
    t_eq($back, $plain, '*** an existing value round-trips EXACTLY: "' . $plain . '"');
}

//  And our own generated sentence is never re-parsed into something subtly
//  different, which would be the same bug one save later.
foreach (['CSWIP 3.1 AND (Offshore OR Refinery)', 'B.E. OR Diploma'] as $gen)
    t_eq(hreq_req_groups_from_text($gen), [],
        '*** already-structured text is left alone, not re-parsed: "' . $gen . '"');

// ---- reading the form ------------------------------------------------------
$post = ['skills_grp_mode'  => ['ALL', 'ANY'],
         'skills_grp_items' => ['CSWIP 3.1', 'Offshore experience, Refinery shutdown']];
$g = hreq_req_groups_from_post($post, 'skills_grp');
t_eq(count($g), 2, 'two filled rows make two groups');
t_eq($g[1]['mode'], 'ANY', 'and each row keeps its own mode');
t_eq($T($g), 'CSWIP 3.1 AND (Offshore experience OR Refinery shutdown)', 'rendering what was typed');

//  Empty rows are dropped rather than becoming empty groups.
$g2 = hreq_req_groups_from_post(['skills_grp_mode' => ['ALL', 'ANY', 'ALL'],
                                 'skills_grp_items' => ['A', '   ', '']], 'skills_grp');
t_eq(count($g2), 1, '*** blank rows are discarded, not stored as empty requirements');

//  Rubbish decodes to nothing rather than exploding.
foreach (['not json', '{"a":1}', '[{"mode":"ALL"}]', '[[]]', 'null'] as $junk)
    t_eq(hreq_req_groups_decode($junk), [], 'malformed stored value degrades to "no structure": ' . $junk);

// ---- the text stays canonical ---------------------------------------------
$src = (string) file_get_contents(__DIR__ . '/../lib/hiringreq.php');
t_ok(strpos($src, "'essential_skills'         => \$skillGroups ? \$skillText") !== false,
    '*** the builder GENERATES the text column rather than replacing it');
t_ok(strpos($src, 'a request saved WITHOUT the builder keeps whatever text it had') !== false,
    'and the reason is written down where the next person will look');

// ---- the preview must say what will be stored -----------------------------
$js = (string) file_get_contents(__DIR__ . '/../assets/js/app.js');
t_ok(strpos($js, "g.items.join(g.mode === 'ANY' ? ' OR ' : ', ')") !== false,
    '*** the live preview uses the same comma rule as the server');
t_ok(strpos($js, 'single ? joined') !== false, 'and the same bracket rule');
t_eq(substr_count($js, '{'), substr_count($js, '}'), 'app.js braces balance');
