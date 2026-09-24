<?php
// ============================================================================
//  B8 — RECRUITMENT IN GLOBAL SEARCH
//
//  The B8 audit confirmed four of its eight carried findings and retired the
//  other four. The four that stood: candidates, recruitment requisitions and
//  hiring requests could not be found from the one box — and the empty state
//  then said "Nothing matches Arjun Ghosh in any register you can open" about
//  a person the searcher could open from the candidate register. It did not
//  fail to find; it asserted absence.
//
//  This adds three sources to the ONE existing registry. No second engine, no
//  second registry, no new renderer, no schema change.
//
//  The rule that matters most here: EACH SOURCE TAKES ITS SCOPE FROM ITS OWN
//  MODULE. The three differ, and the differences are invisible to the eye:
//
//    requisitions    rcc_scope_req()      office AND sbu
//    hiring requests scope_office_clause() office ONLY — no sbu
//    candidates      rasg_cand_scope()    NO office of its own; reaches it
//                                         through requisitions.office_id via
//                                         c.requisition_id, plus the
//                                         recruitment sbu clause
//
//  Writing scope_clause('c.office_id','c.sbu') for candidates would look
//  exactly like its neighbours, parse, run, and scope NOTHING. That is the
//  defect this file exists to prevent.
// ============================================================================

t_section('B8 — recruitment in global search');

$b8src = (string) @file_get_contents(__DIR__ . '/../lib/search.php');
$b8vw  = (string) @file_get_contents(__DIR__ . '/../views/ops/search.php');

// ---- A · one engine, extended — not a second one --------------------------
t_eq(substr_count($b8src, 'function search_sources'), 1, 'A1 · one registry, defined once');
t_eq(substr_count($b8src, 'function search_run'), 1, 'A2 · one runner, defined once');
foreach (['recruit_search', 'search_recruitment', 'cand_search_run', 'search_sources_hr'] as $b8n)
    t_ok(!function_exists($b8n), "A3 · no separate recruitment search called $b8n");
t_eq(substr_count($b8src, '$add('), 19, 'A4 · 19 sources registered (16 before B8, plus three)');

// ---- B · the three sources exist and are gated ----------------------------
t_as_admin();
$b8keys = array_keys(search_sources());
foreach (['candidates', 'requisitions', 'hiring_requests'] as $b8k)
    t_ok(in_array($b8k, $b8keys, true), "B1 · \"$b8k\" is registered for an administrator");
t_eq(count($b8keys), 19, 'B2 · an administrator sees all 19');

// ---- C · THE SCOPE RULE — each from its own module ------------------------
//  Asserted on the source text because the three helpers are what make the
//  difference, and swapping one for another still parses and still runs.
t_ok(strpos($b8src, "rasg_cand_scope('c')") !== false,
     'C1 · candidates use the recruitment helper that reaches office via the requisition');
t_ok(strpos($b8src, "scope_clause('c.office_id'") === false,
     'C2 · candidates do NOT use a direct office/sbu clause — that would scope nothing');
t_ok(strpos($b8src, "rcc_scope_req('r')") !== false,
     'C3 · requisitions use their own office AND sbu helper');
//  Hiring requests are the one source whose QUERY does not live in search.php.
//  lib/hiringreq.php owns that table — test_m4_correction.php asserts no other
//  file reads or writes it, so that a record carrying an approval decision has
//  exactly one door. Search therefore asks the layer. The first version of this
//  stage put the SQL in search.php and broke that boundary; the regression
//  caught it, and the fix was to respect the layer rather than widen the rule.
$b8hr = (string) @file_get_contents(__DIR__ . '/../lib/hiringreq.php');
t_ok(strpos($b8src, 'hreq_search($l, $n)') !== false,
     'C4 · the hiring-request source asks the layer that owns the table');
t_ok(preg_match('/(FROM|INTO|UPDATE|JOIN)\s+hiring_requests\b/i', $b8src) !== 1,
     'C4b · …and search.php itself issues no SQL against that table');
t_ok(preg_match("/function hreq_search[\s\S]{0,1200}?scope_office_clause\('office_id'\)/", $b8hr) === 1,
     'C4c · the layer scopes it by office ONLY, exactly as hreq_list() does');
//  …and it must NOT have been given an SBU restriction its own register does
//  not apply, which would hide requests from people entitled to see them.
t_ok(preg_match("/function hreq_search[\s\S]{0,1200}?(rcc_scope_req|scope_clause\()/", $b8hr) !== 1,
     'C5 · …and was not given an SBU clause the register never applies');
//  A caller that forgets the gate must still be refused by the layer.
t_ok(preg_match('/function hreq_search[\s\S]{0,300}?hreq_can_view\(\)/', $b8hr) === 1,
     'C5b · …and refuses on its own when the reader may not see hiring requests');

// ---- D · permission gates come from each register -------------------------
t_ok(preg_match("/\\\$add\('candidates'[^\n]*is_coordinator_level/", $b8src) === 1,
     'D1 · candidates use the same gate as the candidate register');
t_ok(preg_match("/\\\$add\('requisitions'[\s\S]{0,300}?is_coordinator_level/", $b8src) === 1,
     'D2 · requisitions use the same gate as the requisition register');
t_ok(preg_match("/\\\$add\('hiring_requests'[\s\S]{0,300}?hreq_can_view/", $b8src) === 1,
     'D3 · hiring requests use the register\'s own hreq_can_view()');
//  The gate must stay INSIDE $add, which returns before storing the closure —
//  a source the person may not see is never queried, not queried then filtered.
t_ok(strpos($b8src, 'if (!$can) return;') !== false,
     'D4 · an ungranted source is never registered, so it can never be queried');

// ---- E · identity fields, not résumé content ------------------------------
//  The candidate REGISTER searches cv_text and cv_keywords, and should. In a
//  box that searches everything, one word of a résumé would bury everything
//  else, so global search takes identity only.
t_ok(preg_match("/\\\$add\('candidates'[\s\S]{0,2000}?c\.cand_code LIKE/", $b8src) === 1,
     'E1 · candidates are found by their code');
t_ok(preg_match("/\\\$add\('candidates'[\s\S]{0,2000}?\(c\.first_name \|\| ' ' \|\| c\.last_name\) LIKE/", $b8src) === 1,
     'E2 · …and by full name, which is what a person actually types');
t_ok(preg_match("/\\\$add\('candidates'[\s\S]{0,2000}?cv_text/", $b8src) !== 1,
     'E3 · …but NOT by résumé text, which belongs to the register');

// ---- F · "Requirement" is disambiguated without renaming anything ---------
t_ok(strpos($b8src, "TERM_DEFAULTS['requisition'][2]") !== false,
     'F1 · the requisition group names its module, read from the term\'s own group');
t_ok(strpos($b8src, "THP('candidate')") !== false && strpos($b8src, "THP('requisition')") !== false
     && strpos($b8src, "THP('hiring_request')") !== false,
     'F2 · all three labels come from the terminology engine, not hard-coded words');

// ---- G · the empty state describes what was SEARCHED ----------------------
t_ok(strpos($b8vw, 'in any register you can open') === false,
     'G1 · the universal claim about the whole database is gone');
t_ok(strpos($b8vw, "count(\$res['sources'] ?? [])") !== false,
     'G2 · …replaced by a count of the registers actually searched');
//  Safety: the count comes from the registry, which already excludes anything
//  this person may not see. No inaccessible record is read to build it.
t_ok(strpos($b8src, "'sources' => array_map(fn(\$s) => \$s['label'], \$sources)") !== false,
     'G3 · that list is the person\'s own permitted registry — nothing inaccessible is consulted');

// ---- H · destinations are the existing records ----------------------------
foreach ([['candidates', '/candidate?id='], ['requisitions', '/requisition?id='],
          ['hiring_requests', '/hiring-request?id=']] as [$b8k, $b8u])
    t_ok(preg_match("/\\\$add\('$b8k'[\s\S]{0,2200}?" . preg_quote($b8u, '/') . "/", $b8src) === 1,
         "H1 · $b8k results open the existing record ($b8u)");
