<?php
// ============================================================================
//  COMPLIANCE RULE MASTER  (Phase 1 — the versioned, effective-dated tax spine)
//
//  Every tax/compliance value the marketplace uses — a GST rate, a SAC classification,
//  an RCM / 9(5) / TDS / TCS determination — is a RULE with a version and an effective
//  date range, never a hard-coded number. When the law changes you add a new version
//  with a new effective-from; the old version keeps its dates, so a transaction booked
//  under the old law is reproduced exactly, forever. A transaction stores a SNAPSHOT of
//  the rule versions it used (see mkt_snapshots) so an auditor can reproduce it.
//
//  This is CONFIG ONLY — no money moves here. It is the single place a CA's answers get
//  entered. Seeded values are CANDIDATES marked for CA confirmation, fully editable in
//  Super Admin. Master/Tax-admin only.
// ============================================================================

function mkt_rules_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS mkt_rules (
        id $pk,
        rule_type VARCHAR(12) DEFAULT 'GST', code VARCHAR(40) DEFAULT '', label VARCHAR(200) DEFAULT '',
        rate REAL DEFAULT 0, params TEXT DEFAULT '',
        effective_from VARCHAR(10) DEFAULT '', effective_until VARCHAR(10) DEFAULT '',
        source_ref VARCHAR(200) DEFAULT '', status VARCHAR(10) DEFAULT 'ACTIVE', version INT DEFAULT 1, priority INT DEFAULT 0,
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    // A reproducibility store: the exact rule/fee versions a transaction used.
    db()->exec("CREATE TABLE IF NOT EXISTS mkt_snapshots (
        id $pk, context VARCHAR(24) DEFAULT '', ref_id INT DEFAULT 0,
        snapshot TEXT DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    if (function_exists('act_index')) {
        act_index('mkt_rules', 'ix_rule_lookup', '(rule_type, code, status)');
        act_index('mkt_snapshots', 'ix_snap_ref', '(context, ref_id)');
    }
    try { if ((int)ops_val("SELECT COUNT(*) FROM mkt_rules") === 0) mkt_rules_seed_candidates(); } catch (Throwable $e) {}
}

/** The rule families the engine tracks. Extend freely — this is not a fixed list. */
function mkt_rule_types() {
    return [
        'GST'  => 'GST rate',
        'SAC'  => 'SAC classification',
        'RCM'  => 'Reverse charge (RCM)',
        'S9_5' => 'Section 9(5) notified service',
        'TDS'  => 'Income-tax TDS',
        'TCS'  => 'GST-TCS (s.52)',
    ];
}
function mkt_rule_type_norm($t) { $t = strtoupper((string)$t); return isset(mkt_rule_types()[$t]) ? $t : 'GST'; }

/**
 * Seed a small CANDIDATE set — clearly marked for CA confirmation, fully editable.
 * These are defaults so the engine has something to resolve; they are NOT legal advice.
 */
function mkt_rules_seed_candidates() {
    $CA = 'CANDIDATE — confirm with CA';
    $rows = [
        // type   code       label                              rate  from          source
        ['GST',  'STD',     'Standard GST rate',                18,  '2017-07-01', $CA],
        ['SAC',  '998346',  'Technical testing & inspection',   18,  '2017-07-01', $CA],
        ['SAC',  '998519',  'Manpower / labour supply',         18,  '2017-07-01', $CA],
        ['SAC',  '998599',  'Support / facilitation service',   18,  '2017-07-01', $CA],
        ['TDS',  '194J',    'Professional / technical fee TDS', 10,  '2025-04-01', $CA],
        ['TDS',  '194C',    'Contractor / work TDS',             2,  '2025-04-01', $CA],
        ['TCS',  'S52',     'GST-TCS (e-commerce operator)',     1,  '2025-04-01', $CA],
        ['RCM',  'DEFAULT', 'RCM not applicable by default',     0,  '2017-07-01', $CA],
        ['S9_5', 'DEFAULT', 'Not a 9(5) notified service',       0,  '2017-07-01', $CA],
    ];
    foreach ($rows as [$type, $code, $label, $rate, $from, $src]) {
        mkt_rule_set($type, $code, $rate, $from, ['label' => $label, 'source_ref' => $src, 'by' => 'seed']);
    }
}

/**
 * Add or supersede a rule with a new effective date. If a currently-open rule exists for
 * (type, code): if the new one starts later, the old one is closed the day before (kept
 * for history); if it starts on the same or earlier date, the old one is retired. Returns
 * the new rule id. This is how a law change is recorded — never by editing history.
 */
function mkt_rule_set($type, $code, $rate, $from, array $opts = []) {
    mkt_rules_migrate();
    $type = mkt_rule_type_norm($type); $code = trim((string)$code);
    $from = substr((string)$from, 0, 10) ?: date('Y-m-d');
    $prev = ops_one("SELECT * FROM mkt_rules WHERE rule_type=? AND code=? AND status='ACTIVE' ORDER BY version DESC, effective_from DESC LIMIT 1", [$type, $code]);
    $version = 1;
    if ($prev) {
        $version = (int)$prev['version'] + 1;
        if ((string)$prev['effective_from'] < $from) {
            $until = date('Y-m-d', strtotime($from . ' -1 day'));
            db()->prepare("UPDATE mkt_rules SET effective_until=?, updated_at=? WHERE id=?")->execute([$until, date('c'), (int)$prev['id']]);
        } else {
            db()->prepare("UPDATE mkt_rules SET status='RETIRED', updated_at=? WHERE id=?")->execute([date('c'), (int)$prev['id']]);
        }
    }
    $now = date('c');
    db()->prepare("INSERT INTO mkt_rules (rule_type,code,label,rate,params,effective_from,effective_until,source_ref,status,version,priority,created_by,created_at,updated_at)
                   VALUES (?,?,?,?,?,?,?,?, 'ACTIVE', ?, ?, ?, ?, ?)")
        ->execute([$type, $code, (string)($opts['label'] ?? ''), (float)$rate, (string)($opts['params'] ?? ''),
                   $from, (string)($opts['until'] ?? ''), (string)($opts['source_ref'] ?? ''), $version, (int)($opts['priority'] ?? 0),
                   (string)($opts['by'] ?? ''), $now, $now]);
    return (int)db()->lastInsertId();
}

/** The rule of a (type, code) in force on a date (default today), or null. */
function mkt_rule_resolve($type, $code, $onDate = null) {
    mkt_rules_migrate();
    $d = substr((string)($onDate ?: date('Y-m-d')), 0, 10);
    return ops_one("SELECT * FROM mkt_rules WHERE rule_type=? AND code=? AND status='ACTIVE' AND effective_from<=? AND (effective_until='' OR effective_until>=?)
                    ORDER BY effective_from DESC, version DESC LIMIT 1",
        [mkt_rule_type_norm($type), trim((string)$code), $d, $d]) ?: null;
}

/** The rate in force on a date, or a fallback if no rule matches (default 0). */
function mkt_rule_rate($type, $code, $onDate = null, $fallback = 0.0) {
    $r = mkt_rule_resolve($type, $code, $onDate);
    return $r ? (float)$r['rate'] : (float)$fallback;
}

/** A small immutable snapshot of the rule version used, to store on a transaction. */
function mkt_rule_snapshot($type, $code, $onDate = null) {
    $r = mkt_rule_resolve($type, $code, $onDate);
    if (!$r) return null;
    return ['rule_id' => (int)$r['id'], 'version' => (int)$r['version'], 'type' => mkt_rule_type_norm($type),
            'code' => trim((string)$code), 'rate' => (float)$r['rate'], 'source_ref' => (string)$r['source_ref'],
            'resolved_on' => substr((string)($onDate ?: date('Y-m-d')), 0, 10)];
}

/** All versions of a (type, code) newest first — the audit history for that rule. */
function mkt_rule_history($type, $code) {
    mkt_rules_migrate();
    return ops_all("SELECT * FROM mkt_rules WHERE rule_type=? AND code=? ORDER BY version DESC", [mkt_rule_type_norm($type), trim((string)$code)]) ?: [];
}
/** The current live rules (one active row per code), for the admin list. */
function mkt_rules_current($type = null) {
    mkt_rules_migrate();
    $today = date('Y-m-d');
    $args = [$today, $today]; $w = "status='ACTIVE' AND effective_from<=? AND (effective_until='' OR effective_until>=?)";
    if ($type) { $w .= " AND rule_type=?"; $args[] = mkt_rule_type_norm($type); }
    return ops_all("SELECT * FROM mkt_rules WHERE $w ORDER BY rule_type, code", $args) ?: [];
}

/** Persist a reproducibility snapshot for a transaction (context + ref). */
function mkt_snapshot_save($context, $refId, array $data) {
    mkt_rules_migrate();
    db()->prepare("INSERT INTO mkt_snapshots (context,ref_id,snapshot,created_at) VALUES (?,?,?,?)")
        ->execute([(string)$context, (int)$refId, json_encode($data), date('c')]);
    return (int)db()->lastInsertId();
}
function mkt_snapshot_get($context, $refId) {
    mkt_rules_migrate();
    $row = ops_one("SELECT snapshot FROM mkt_snapshots WHERE context=? AND ref_id=? ORDER BY id DESC LIMIT 1", [(string)$context, (int)$refId]);
    return $row ? (json_decode((string)$row['snapshot'], true) ?: null) : null;
}

/**
 * Tax-admin role (spec §42) — a dedicated set of people allowed to edit STATUTORY rules,
 * separate from commercial admins. The Master is always allowed; additional tax admins are
 * an allow-list of e-mails the Master maintains. Commercial admins never qualify.
 */
function mkt_tax_admins() {
    $raw = (string) setting_get('tax_admin_emails', '');
    return array_values(array_filter(array_map(fn($s) => strtolower(trim($s)), explode(',', $raw))));
}
function mkt_tax_admin_can() {
    if (function_exists('is_master') && is_master()) return true;
    if (!function_exists('current_user')) return false;
    $u = current_user(); $email = strtolower(trim((string)($u['email'] ?? '')));
    return $email !== '' && in_array($email, mkt_tax_admins(), true);
}

/** Route handler — the compliance rules & fees screen (Master or Tax-admin; statutory config). */
function ops_mkt_rules($method) {
    ops_require(mkt_tax_admin_can(), 'Only the Super Admin or a designated Tax admin can manage compliance rules.');
    mkt_rules_migrate(); if (function_exists('mkt_fees_migrate')) mkt_fees_migrate();
    if ($method === 'POST') {
        $act = (string)($_POST['action'] ?? '');
        $by  = function_exists('user_name') && function_exists('current_user') ? (string)user_name(current_user()) : '';
        if ($act === 'save_taxadmins') {
            ops_require(function_exists('is_master') && is_master(), 'Only the Master can change who the Tax admins are.');
            setting_set('tax_admin_emails', trim((string)($_POST['tax_admin_emails'] ?? '')));
            flash('Tax-admin list saved.'); redirect('/compliance-rules');
        }
        if ($act === 'save_rule') {
            $type = (string)($_POST['rule_type'] ?? 'GST'); $code = trim((string)($_POST['code'] ?? ''));
            if ($code === '') flash('Give the rule a code.', 'error');
            else { mkt_rule_set($type, $code, (float)($_POST['rate'] ?? 0), (string)($_POST['effective_from'] ?? date('Y-m-d')),
                        ['label' => (string)($_POST['label'] ?? ''), 'source_ref' => (string)($_POST['source_ref'] ?? ''), 'by' => $by]);
                   flash('Rule version saved — effective ' . e((string)($_POST['effective_from'] ?? '')) . '.'); }
        } elseif ($act === 'save_fee' && function_exists('mkt_fee_save')) {
            [$ok, $m] = mkt_fee_save($_POST, $by); flash($m, $ok ? 'success' : 'error');
        } elseif ($act === 'retire_fee' && function_exists('mkt_fee_retire')) {
            mkt_fee_retire((int)($_POST['id'] ?? 0)); flash('Fee rule retired.');
        }
        redirect('/compliance-rules');
    }
    view('ops/mkt_rules', [
        'ruleTypes' => mkt_rule_types(),
        'rules'     => mkt_rules_current(),
        'feeRules'  => function_exists('mkt_fee_rules_all') ? mkt_fee_rules_all() : [],
        'feePayers' => function_exists('mkt_fee_payers') ? mkt_fee_payers() : [],
        'feeBases'  => function_exists('mkt_fee_bases') ? mkt_fee_bases() : [],
        'currency'  => function_exists('mkt_currency') ? mkt_currency() : '₹',
        'taxAdmins' => implode(', ', mkt_tax_admins()),
        'isMaster'  => function_exists('is_master') && is_master(),
    ]);
    return true;
}
