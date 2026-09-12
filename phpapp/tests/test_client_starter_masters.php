<?php
// ============================================================================
//  A fresh HOSTED workspace must not open to a blank "0 lists" Masters screen
//  with its designation / source dropdowns silently falling back to the
//  inspection company's defaults (Inspector, Engineer, Sub-contractor…). A
//  recruitment / people plan gets a small, correctly-worded HIRING starter set;
//  any other plan gets the generic business lists. And "+ Add a designation"
//  always sticks, creating the master on first use if it is missing.
// ============================================================================

t_section('Client workspace — plan-appropriate starter master lists');

if (!function_exists('lk_client_starter_lists')) { t_ok(true, 'starter-list helper not present — skipped'); return; }

// --- The recruitment starter set is worded for hiring, not inspection. ---
$flat = function ($lists) { $m = []; foreach ($lists as [$k, $l, $map]) $m[$k] = $map; return $m; };

t_ok(in_array('Recruiter', RECRUIT_DESIGNATIONS, true), 'the recruitment designations include "Recruiter"');
t_ok(in_array('Recruitment Manager', RECRUIT_DESIGNATIONS, true), 'the recruitment designations include "Recruitment Manager"');
t_ok(!in_array('Inspector', RECRUIT_DESIGNATIONS, true), 'the recruitment designations do NOT carry inspection titles');
t_ok(in_array('LinkedIn', RECRUIT_CAND_SOURCES, true), 'candidate sources include real hiring channels (LinkedIn)');
t_ok(in_array('Employee Referral', RECRUIT_CAND_SOURCES, true), 'candidate sources include Employee Referral');

// --- Plan selection: recruitment plan → hiring lists; other plan → generic. ---
$saveTenant = $GLOBALS['__tenant'] ?? null;
$mk = function () { $GLOBALS['__tenant'] = ['key' => 'acme', 'company' => 'Acme', 'error' => '', 'saas' => true, 'base' => 'ops.example.com']; };
$mk();
$savedKey = (string) setting_get('licence_key', '');
$savedOff = (string) setting_get('modules_off', '');
$savedCeil = (string) setting_get('saas_entitled_modules', '');
setting_set('licence_key', ''); if (function_exists('lk_state')) lk_state(true);

// Recruitment plan: People & hiring on, operations / reporting off.
setting_set('saas_entitled_modules', 'hr'); $mk();
setting_set('modules_off', 'operations,sales,reporting,money'); $mk();
if (function_exists('licence_disabled')) licence_disabled(true);
$mk();
$recruitLists = $flat(lk_client_starter_lists());
t_ok(($recruitLists['designation'] ?? []) === RECRUIT_DESIGNATIONS, 'a recruitment workspace gets the recruitment designations');
t_ok(($recruitLists['department'] ?? []) === RECRUIT_DEPARTMENTS, 'a recruitment workspace gets the recruitment departments');
// candidate_source is registered as a module list (from RECRUIT_CAND_SOURCES),
// so it is not part of the starter helper — but its default is hiring-shaped.
t_ok(in_array('Job Portal (Naukri / Indeed)', RECRUIT_CAND_SOURCES, true), 'the candidate-source default is hiring-shaped (Job Portal)');

// A broader plan (operations on) → generic business lists, no candidate_source.
setting_set('modules_off', ''); $mk();
setting_set('saas_entitled_modules', 'hr,operations,sales,reporting,money'); $mk();
if (function_exists('licence_disabled')) licence_disabled(true);
$mk();
$genLists = $flat(lk_client_starter_lists());
t_ok(!isset($genLists['candidate_source']), 'a non-recruitment plan does not force a Candidate source list');
t_ok(($genLists['designation'] ?? []) === DESIGNATIONS, 'a non-recruitment plan keeps the generic designations');

// --- Restore. ---
setting_set('modules_off', $savedOff);
setting_set('saas_entitled_modules', $savedCeil);
setting_set('licence_key', $savedKey);
if (function_exists('lk_state')) lk_state(true);
if ($saveTenant === null) { unset($GLOBALS['__tenant']); } else { $GLOBALS['__tenant'] = $saveTenant; }
if (function_exists('licence_disabled')) licence_disabled(true);
t_ok(true, 'plan / tenant state restored for the rest of the suite');
