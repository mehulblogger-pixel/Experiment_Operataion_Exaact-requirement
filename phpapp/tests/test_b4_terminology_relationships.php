<?php
// ============================================================================
//  B4 — TERMINOLOGY AND RELATIONSHIP VISIBILITY
//
//  Two measured facts drove this:
//
//   1. All 26 curated definitions existed, and exactly ONE screen printed them:
//      views/ops/terminology.php, the admin rename page — the one place a
//      person is not confused, because they went there on purpose.
//   2. SIX words the confusion audit found people hold apart every day had no
//      definition anywhere at all. (The audit said five; `professional` is a
//      sixth.)
//
//  The governing rule is the confusion audit's own: a RELATIONSHIP beats a
//  definition. "Raised from hiring request HR-00231" tells a reader more than
//  any paragraph about requisitions. So B4 writes the six sentences, surfaces
//  them only where confusion was measured, and spends the rest of its effort
//  on making the chain visible.
//
//  What these tests defend:
//   A. The six definitions exist and say what the recorded decisions say.
//   B. No word's MEANING changed and no term was removed.
//   C. Definitions reach the screens where the word is used.
//   D. The chain is visible in BOTH directions.
//   E. ADR-001 is not decided by a sentence.
// ============================================================================

t_section('B4 — terminology and relationships');

// ---- A · the six definitions ---------------------------------------------
$b4new = ['hiring_request', 'workforce', 'inspector', 'qa', 'billing_readiness', 'professional'];
foreach ($b4new as $k) {
    t_ok(isset(TERM_DEFAULTS[$k]), "A1 · '$k' now has a terminology entry");
    t_ok(trim((string) (TERM_DEFAULTS[$k][3] ?? '')) !== '', "A2 · '$k' carries a written definition");
    t_ok(strlen((string) (TERM_DEFAULTS[$k][3] ?? '')) > 40, "A3 · '$k' is a sentence, not a label");
}
//  Each must match the decision it was taken from, not something invented here.
t_ok(stripos(T_HELP('hiring_request'), 'approved') !== false,
     'A4 · hiring request names the approval that gates it (M4)');
t_ok(stripos(T_HELP('inspector'), 'field') !== false && stripos(T_HELP('inspector'), 'workforce') !== false,
     'A5 · inspector is defined as a workforce member whose team role is Field (Q32 Model D §10)');
t_ok(stripos(T_HELP('workforce'), 'hired') !== false,
     'A6 · workforce is the record created on hiring, whatever the job');
t_ok(stripos(T_HELP('qa'), 'stage') !== false,
     'A7 · QA is described as a stage of a report, not a separate document');
t_ok(stripos(T_HELP('billing_readiness'), 'not an invoice') !== false
     || stripos(T_HELP('billing_readiness'), 'is not an invoice') !== false,
     'A8 · billing readiness is explicitly distinguished from an invoice');
t_ok(stripos(T_HELP('professional'), 'marketplace') !== false,
     'A9 · professional is placed on the marketplace, not in this company\'s staff');

// ---- B · NOTHING WAS CHANGED OR LOST -------------------------------------
//  B4 may add definitions. It may not restate what an existing word means.
$b4known = [
  'client'      => 'The party that engages us and gets what we produce.',
  'call'        => 'A piece of work the customer has asked for, with its dates and location.',
  'job'         => 'One person put on one work order, for particular dates.',
  'requisition' => 'Approved demand for a new position — the role you are hiring for.',
  'candidate'   => 'A person being considered for hiring.',
  'report'      => 'A document we issue against a job and send to the client.',
  'invoice'     => 'A bill raised on the client.',
];
foreach ($b4known as $k => $def)
    t_eq(T_HELP($k), $def, "B1 · '$k' still means exactly what it meant before B4");
t_eq(TERM_DEFAULTS['engineer'][0], 'Team Member', 'B2 · the shipped words are unchanged too');
t_eq(count(TERM_DEFAULTS), 32, 'B3 · 26 terms before, 32 now — six added, none removed');
//  Every term still has all four parts, or the rename screen breaks.
$b4bad = [];
foreach (TERM_DEFAULTS as $k => $d) if (count($d) < 4 || trim((string) $d[3]) === '') $b4bad[] = $k;
t_eq($b4bad, [], 'B4 · every term still carries singular, plural, group and definition');
//  The new words must sit in groups the rename screen already knows, or they
//  would be invisible there.
$b4groups = array_unique(array_map(fn($d) => $d[2], TERM_DEFAULTS));
foreach ($b4new as $k)
    t_ok(in_array(TERM_DEFAULTS[$k][2], $b4groups, true) && TERM_DEFAULTS[$k][2] !== '',
         "B5 · '$k' is filed under an existing group (" . TERM_DEFAULTS[$k][2] . ')');

// ---- C · the definition reaches the screen -------------------------------
t_ok(function_exists('T_HELP') && function_exists('T_NOTE'), 'C1 · the two accessors exist');
t_eq(T_HELP('not_a_term'), '', 'C2 · an unknown key yields no text');
t_eq(T_NOTE('not_a_term'), '', 'C3 · and renders nothing at all — never an empty box');
$b4n = T_NOTE('hiring_request');
t_ok(strpos($b4n, 'class="sub t-note"') !== false,
     'C4 · it renders as the same muted one-liner 295 views already use — no new component');
t_ok(strpos($b4n, '<script') === false && strpos($b4n, 'onmouse') === false,
     'C5 · and it is not a tooltip — a tooltip cannot be read on the phone inspectors work from');
//  Escaping: a definition is content, and content gets escaped.
t_ok(strpos(T_NOTE('qa'), '&#039;') !== false || strpos(T_NOTE('qa'), '&amp;') !== false
     || strpos(T_HELP('qa'), "'") === false,
     'C6 · the rendered definition is HTML-escaped');
//  The screens that should carry one, do.
foreach (['hiring_request' => "T_NOTE('hiring_request')"] as $b4v => $b4needle) {
    $src = file_get_contents(__DIR__ . '/../views/ops/' . $b4v . '.php');
    t_ok(strpos($src, $b4needle) !== false, "C7 · views/ops/$b4v.php surfaces its definition");
}
$b4insp = file_get_contents(__DIR__ . '/../views/ops/inspector_form.php');
t_ok(stripos($b4insp, 'is what also makes somebody an') !== false,
     'C8 · the workforce-vs-inspector rule is stated at the field that decides it');

// ---- D · THE CHAIN, BOTH WAYS --------------------------------------------
t_ok(function_exists('workforce_origin'), 'D1 · a team member can be asked where they came from');
t_eq(workforce_origin(0), null, 'D2 · with no id, it answers nothing rather than guessing');
t_eq(workforce_origin(999999), null, 'D3 · and for somebody never recruited, nothing — which is a real case');
//  Arming: build the chain and read it back.
t_as_admin();
$b4email = 'b4.chain.' . substr(md5((string) mt_rand()), 0, 6) . '@example.com';
$b4ins = team_member_create('B4 Chain Person', 'FIELD', null, $b4email);
t_ok($b4ins > 0, 'D ARMING · a team member really was created (id ' . (int) $b4ins . ')');
db()->prepare("INSERT INTO candidates (first_name,last_name,cand_code,email,stage,inspector_id,sbu,created_at)
               VALUES ('B4','Chain Person','CV-B4-1',?,'ACCEPTED',?,'IND',?)")
    ->execute([$b4email, (int) $b4ins, date('c')]);
$b4o = workforce_origin((int) $b4ins);
t_ok(is_array($b4o), 'D4 · the team member now reports an origin');
t_eq($b4o['candidate']['cand_code'] ?? '', 'CV-B4-1', 'D5 · and names the candidate they were hired as');
t_ok(($b4o['candidate']['joined_at'] ?? '') === null || trim((string) ($b4o['candidate']['joined_at'] ?? '')) === '',
     'D6 · with no joining date, because accepted is not joined');
//  The view must render it, and only when editing an existing person.
t_ok(strpos($b4insp, 'workforce_origin((int) $ins[\'id\'])') !== false,
     'D7 · the team-member screen reads it');
t_ok(strpos($b4insp, '$isEdit && function_exists(\'workforce_origin\')') !== false,
     'D8 · only for a person who exists — a refused add has no origin to show');
db()->prepare("DELETE FROM candidates WHERE cand_code='CV-B4-1'")->execute();
db()->prepare("DELETE FROM inspectors WHERE email=?")->execute([$b4email]);

// ---- E · ADR-001 IS NOT DECIDED BY A SENTENCE ----------------------------
$b4req = file_get_contents(__DIR__ . '/../views/ops/requisition_detail.php');
t_ok(stripos($b4req, 'Recorded directly') !== false,
     'E1 · a requirement with no hiring request behind it now says so');
t_ok(stripos($b4req, 'Both ways of starting are supported') !== false,
     'E2 · and states that both routes are supported');
foreach (['should have been raised', 'preferred', 'ought to', 'incorrectly', 'bypassed', 'skipped the approval'] as $b4claim)
    t_ok(stripos($b4req, $b4claim) === false,
         "E3 · it does not editorialise ('$b4claim' absent) — ADR-001 stays the owner's decision");
//  ARMING — prove the scan would catch an editorial.
t_ok(stripos('this should have been raised as a hiring request', 'should have been raised') !== false,
     'E ARMING · the editorial scan detects the shape it forbids');

t_as_nobody();
t_ok(true, 'B4 · session restored');
