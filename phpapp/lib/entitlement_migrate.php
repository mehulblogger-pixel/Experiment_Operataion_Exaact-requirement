<?php
// ============================================================================
//  PHASE 1 · MILESTONE 4 — EXISTING CUSTOMER ENTITLEMENT MIGRATION
//
//  Milestone 3 made a missing entitlement record mean DENY. That is correct, and
//  it leaves a question this milestone answers: what about the customers who
//  already exist?
//
//  The job is NOT to push every workspace into a new state. It is to give every
//  workspace a KNOWN, DEFENSIBLE outcome — migrated on evidence, left alone, or
//  set aside for a human.
//
//  THE ONE RULE: entitlement is never manufactured.
//
//  A plan name, a package, an old default, a blank record, the presence of code,
//  a route, a permission or a master user prove nothing about what was bought.
//  Only the commercial record does. Where there is no evidence, the workspace is
//  classified and left untouched — never granted, never quietly stripped.
//
//  This is a MIGRATION, not a second entitlement engine. It reads the M3 engine
//  and the M2 registry, and writes one setting. It decides nothing about access.
// ============================================================================

// Keep only real, sellable module keys. Core is deliberately dropped: it is
// always on and is not a ceiling entry, so including it would make two equal
// records compare as different.
function entmig_sellable($mods) {
    if (is_string($mods)) $mods = explode(',', $mods);
    $out = [];
    foreach ((array) $mods as $k) {
        $k = strtolower(trim((string) $k));
        if ($k !== '' && defined('PRODUCT_MODULES') && isset(PRODUCT_MODULES[$k]) && !licence_is_core($k)) $out[] = $k;
    }
    $out = array_values(array_unique($out));
    sort($out);
    return $out;
}

// ---- ASSESSMENT (pure, read-only, no database) -----------------------------
//
// $control — the commercial record, from the control database:
//     tenant, company, status, plan, enabled_modules[]
// $runtime — the workspace's own state:
//     reachable, provisioned, ceiling, modules_off, paid, package, licence_key
//
// Returns the classification, the evidence behind it, and the exact before/after
// so the impact can be read before anything is applied.
function entmig_assess(array $control, array $runtime) {
    $key      = strtolower(trim((string) ($control['tenant'] ?? '')));
    $ctrlMods = entmig_sellable($control['enabled_modules'] ?? []);
    $ceiling  = entmig_sellable($runtime['ceiling'] ?? '');
    $recorded = trim((string) ($runtime['ceiling'] ?? '')) !== '' && $ceiling !== [];

    $r = [
        'tenant'           => $key,
        'company'          => (string) ($control['company'] ?? ''),
        'status'           => (string) ($control['status'] ?? ''),
        'plan'             => (string) ($control['plan'] ?? ''),
        'control_modules'  => $ctrlMods,
        'runtime_ceiling'  => $ceiling,
        'ceiling_recorded' => $recorded,
        'provisioned'      => (string) ($runtime['provisioned'] ?? '') === '1',
        'tenant_disabled'  => entmig_sellable($runtime['modules_off'] ?? ''),
        // What the workspace is entitled to TODAY, under the M3 engine. A blank
        // record entitles nothing, which is why migrating one can only restore
        // access and can never remove it.
        'before'           => $recorded ? $ceiling : [],
        'after'            => [],
        'gained'           => [],
        'lost'             => [],
        'class'            => 'ERROR',
        'evidence'         => 'none',
        'confidence'       => 'NONE',
        'reason'           => '',
    ];

    // 1 · Cannot be read → cannot be assessed. Never guessed.
    if (empty($runtime['reachable'])) {
        $r['reason'] = 'The workspace database could not be read, so its entitlement cannot be assessed.';
        return $r;
    }

    // 2 · A signed licence is the contract and outranks any cloud record.
    //     Migration must not reach past it.
    if (trim((string) ($runtime['licence_key'] ?? '')) !== '') {
        $r['class']  = 'BLOCKED';
        $r['after']  = $r['before'];
        $r['reason'] = 'A signed licence governs this workspace. The licence is authoritative and migration must not override it.';
        return $r;
    }

    // 3 · Not an active customer. Changing what a suspended or closed account is
    //     entitled to is a commercial decision, not a migration.
    if ($r['status'] !== '' && $r['status'] !== 'active') {
        $r['class']  = 'BLOCKED';
        $r['after']  = $r['before'];
        $r['reason'] = 'The customer is ' . $r['status'] . '. Entitlement for an inactive account is a commercial decision, not a migration.';
        return $r;
    }

    // 4 · Never opened. Its ceiling is written when its owner first signs in, by
    //     the provisioning code — there is nothing here to migrate, and writing
    //     into a workspace that does not exist yet would create one.
    if (!$r['provisioned']) {
        $r['class']  = 'NO_CHANGE_REQUIRED';
        $r['after']  = $r['before'];
        $r['reason'] = 'The workspace has never been opened, so it has no runtime entitlement to migrate. Provisioning sets it on first sign-in.';
        return $r;
    }

    // 5 · A ceiling is already recorded.
    if ($recorded) {
        if ($ceiling === $ctrlMods) {
            $r['class']      = 'NO_CHANGE_REQUIRED';
            $r['after']      = $ceiling;
            $r['evidence']   = 'control saas_tenants.enabled_modules';
            $r['confidence'] = 'HIGH';
            $r['reason']     = 'The workspace ceiling already matches the commercial record exactly.';
            return $r;
        }
        // Two deliberate records disagree. Both were written by an operator
        // action; neither is self-evidently the mistake. Guessing could either
        // hand over a module nobody bought or take away one somebody paid for.
        $r['class']      = 'AMBIGUOUS';
        $r['after']      = $r['before'];
        $r['evidence']   = 'control record and workspace ceiling disagree';
        $r['reason']     = 'The commercial record says [' . (implode(',', $ctrlMods) ?: 'nothing')
                         . '] and the workspace ceiling says [' . (implode(',', $ceiling) ?: 'nothing')
                         . ']. Both were set deliberately, so this is resolved by a person, not by a migration.';
        return $r;
    }

    // 6 · No ceiling recorded. The commercial record is the only evidence there
    //     is, and it is evidence: it is written by provisioning, by the
    //     super-admin console and by billing — the acts of selling.
    if ($ctrlMods) {
        $r['class']      = 'SAFE_TO_MIGRATE';
        $r['after']      = $ctrlMods;
        $r['gained']     = array_values(array_diff($ctrlMods, $r['before']));
        $r['lost']       = array_values(array_diff($r['before'], $ctrlMods));
        $r['evidence']   = 'control saas_tenants.enabled_modules';
        $r['confidence'] = 'HIGH';
        $r['reason']     = 'The workspace has no entitlement record; the commercial record names what was sold. Migrating restores exactly that.';
        // Belt and braces. A blank ceiling entitles nothing under M3, so this
        // cannot normally lose access — but if it ever did, the change does not
        // get applied automatically.
        if ($r['lost']) {
            $r['class']  = 'AMBIGUOUS';
            $r['reason'] = 'Migrating would REMOVE access to [' . implode(',', $r['lost']) . ']. Not applied automatically.';
        }
        return $r;
    }

    // 7 · No ceiling and no commercial record. There is nothing to migrate FROM.
    //     The plan name is not evidence of purchase and is deliberately not used.
    $r['class']    = 'AMBIGUOUS';
    $r['after']    = $r['before'];
    $r['evidence'] = 'none';
    $r['reason']   = 'Neither the workspace nor the commercial record says what this customer bought. '
                   . 'The plan name is not proof of purchase, so nothing is granted. A person must record what was sold.';
    return $r;
}

function entmig_is_applicable(array $assessment) { return ($assessment['class'] ?? '') === 'SAFE_TO_MIGRATE'; }

// ---- The settings a migrated workspace carries, so it can be explained and
//      undone later without deleting anything. ------------------------------
const ENTMIG_PREV_KEY = 'saas_entitled_modules_prev';
const ENTMIG_AT_KEY   = 'saas_entitlement_migrated_at';
const ENTMIG_SRC_KEY  = 'saas_entitlement_migrated_from';

// Write the migrated ceiling into the workspace that is CURRENTLY entered.
// Separated from the switching so it can be tested directly, and so the write
// is one obvious place. Returns ['changed'=>bool, 'before'=>string, 'after'=>string].
function entmig_apply_here(array $assessment) {
    $after  = implode(',', entmig_sellable($assessment['after'] ?? []));
    $before = (string) setting_get('saas_entitled_modules', '');

    // IDEMPOTENT: an unchanged source writes nothing at all. Running the
    // migration twice must not keep rewriting customer data.
    if (trim($before) === trim($after)) return ['changed' => false, 'before' => $before, 'after' => $after];

    // Recovery first, and only for the FIRST migration — a second run must not
    // overwrite the original value with an already-migrated one.
    if ((string) setting_get(ENTMIG_PREV_KEY, '') === '' && (string) setting_get(ENTMIG_AT_KEY, '') === '') {
        setting_set(ENTMIG_PREV_KEY, $before);
    }
    setting_set('saas_entitled_modules', $after);
    setting_set(ENTMIG_AT_KEY, date('c'));
    setting_set(ENTMIG_SRC_KEY, (string) ($assessment['evidence'] ?? ''));
    if (function_exists('licence_disabled')) licence_disabled(true);

    // Reuse the existing sealed audit trail. Never a second audit system, and
    // never a secret: module names only.
    if (function_exists('idems_log')) {
        try {
            idems_log('tenant', null, 'ENTITLEMENT_MIGRATED', ['field' =>
                ($assessment['tenant'] ?? '') . ': [' . $before . '] -> [' . $after . '] via ' . ($assessment['evidence'] ?? '')]);
        } catch (Throwable $e) { /* the audit must never block the migration */ }
    }
    return ['changed' => true, 'before' => $before, 'after' => $after];
}
