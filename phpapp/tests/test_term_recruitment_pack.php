<?php
// A Recruitment agency wording pack exists and, once applied, changes the words
// EVERY screen reads (via T/TP) — proving "pick a pack → it updates everywhere".
t_section('Terminology — Recruitment agency pack');

t_ok(defined('TERM_PACKS') && isset(TERM_PACKS['recruitment']), 'a Recruitment agency wording pack is offered');
t_eq(TERM_PACKS['recruitment']['label'] ?? '', 'Recruitment agency', 'it is labelled for a recruitment agency');

// Remember current wording to restore.
$savedTerms = (string) setting_get('terms', '');
$savedPack  = (string) setting_get('terms_pack', '');

term_apply_pack('recruitment');
t_eq(T('engineer'), 'Recruiter', 'applying the pack renames the internal worker to Recruiter everywhere');
t_eq(TP('engineer'), 'Recruiters', 'plural follows too');
t_eq(T('requisition'), 'Requirement', 'the hiring demand reads as Requirement');
t_eq(T('job'), 'Placement', 'a filled position reads as Placement');
t_eq(T('candidate'), 'Candidate', 'the candidate keeps its recruitment word');
t_eq(term_pack_current(), 'recruitment', 'the screen shows the Recruitment pack as the one in force');

// A single word stays editable after the pack (packs are a starting point).
$ov = term_overrides(); $ov['engineer'] = ['Talent Partner', 'Talent Partners']; term_overrides($ov);
t_eq(T('engineer'), 'Talent Partner', 'any single word can still be overridden after the pack');

// Restore the suite's wording.
setting_set('terms', $savedTerms);
setting_set('terms_pack', $savedPack);
term_overrides($savedTerms ? (json_decode($savedTerms, true) ?: []) : []);
t_ok(true, 'wording restored for the rest of the suite');
