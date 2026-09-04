<?php
// ============================================================================
//  MARKETPLACE PLANS & LIMITS  (Slice 2 — configurable, Super-Admin owned)
//
//  The platform owner (Super Admin, separate from any operating company) defines
//  the marketplace subscription plans, their prices and usage limits, the annual
//  discount, and the freelancer launch-promo — all editable, nothing hard-coded.
//  This slice is the STORE + editor only; enforcement (subscriptions, gating,
//  credit packs) is layered on top in later slices and reads these numbers.
// ============================================================================

function mkt_plans_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS mkt_plans (
        id $pk,
        code VARCHAR(40) DEFAULT '', audience VARCHAR(12) DEFAULT 'CLIENT',
        name VARCHAR(120) DEFAULT '',
        price_month REAL DEFAULT 0, price_annual REAL DEFAULT 0,
        limits TEXT DEFAULT '', is_active INT DEFAULT 1, sort INT DEFAULT 0,
        created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    // Seed a sensible starter set ONCE (the Super Admin edits freely afterwards).
    try { if ((int)ops_val("SELECT COUNT(*) FROM mkt_plans") === 0) mkt_plans_seed_defaults(); } catch (Throwable $e) {}
}

/** The two sides that subscribe. */
function mkt_audiences() { return ['CLIENT' => 'Client (hiring companies)', 'PRO' => 'Professional (freelancers)']; }
function mkt_audience_norm($a) { $a = strtoupper((string)$a); return isset(mkt_audiences()[$a]) ? $a : 'CLIENT'; }

/** The limit keys we track (a plan may leave any at 0 = unlimited/not-applicable). */
function mkt_limit_keys() {
    return [
        'posts'        => 'Job posts / month',
        'unlocks'      => 'Contact unlocks / month',
        'reports'      => 'On-platform reports / month',
        'applications' => 'Applications / month (pro)',
        'featured'     => 'Featured / top-rank slots',
    ];
}

/** Global knobs (Super-Admin owned), stored as settings so they stay editable. */
function mkt_annual_months()  { return max(1, (int)setting_get('mkt_annual_months', 10)); }        // annual = pay N months, get 12
function mkt_pro_free_until()  { return trim((string)setting_get('mkt_pro_free_until', '')); }       // 'YYYY-MM-DD' launch promo, or '' = none
function mkt_currency()        { return (string)(setting_get('mkt_currency', '') ?: (function_exists('cur_sym') ? cur_sym() : '₹')); }
/** Are professionals still inside the free launch window? */
function mkt_pro_is_free($on = null) {
    $until = mkt_pro_free_until(); if ($until === '') return false;
    return substr((string)($on ?: date('Y-m-d')), 0, 10) <= $until;
}

/** Default limits for a fresh plan of an audience (used by the seed + the form). */
function mkt_default_limits($audience) {
    return mkt_audience_norm($audience) === 'PRO'
        ? ['applications' => 0, 'featured' => 0]
        : ['posts' => 0, 'unlocks' => 0, 'reports' => 0];
}

function mkt_plans_seed_defaults() {
    $months = mkt_annual_months();
    $rows = [
        // CLIENT
        ['CL_START', 'CLIENT', 'Client · Starter', 999,  ['posts' => 5,  'unlocks' => 20, 'reports' => 0],  1],
        ['CL_GROWTH','CLIENT', 'Client · Growth',  2499, ['posts' => 20, 'unlocks' => 0,  'reports' => 50], 2],
        ['CL_PRO',   'CLIENT', 'Client · Pro',     4999, ['posts' => 0,  'unlocks' => 0,  'reports' => 0],  3],
        // PROFESSIONAL
        ['PR_FREE',  'PRO', 'Freelancer · Free',    0,   ['applications' => 3],  1],
        ['PR_PLUS',  'PRO', 'Freelancer · Plus',    199, ['applications' => 0],  2],
        ['PR_TOP',   'PRO', 'Freelancer · Top-Rank',499, ['applications' => 0, 'featured' => 1], 3],
    ];
    $now = date('c');
    foreach ($rows as [$code, $aud, $name, $pm, $lim, $sort]) {
        db()->prepare("INSERT INTO mkt_plans (code,audience,name,price_month,price_annual,limits,is_active,sort,created_at,updated_at)
                       VALUES (?,?,?,?,?,?,1,?,?,?)")
            ->execute([$code, $aud, $name, $pm, round($pm * $months, 2), json_encode($lim), $sort, $now, $now]);
    }
}

/** All plans (optionally one audience), ordered for display. */
function mkt_plans_all($audience = null) {
    mkt_plans_migrate();
    if ($audience) return ops_all("SELECT * FROM mkt_plans WHERE audience=? ORDER BY sort, id", [mkt_audience_norm($audience)]) ?: [];
    return ops_all("SELECT * FROM mkt_plans ORDER BY audience, sort, id") ?: [];
}
function mkt_plan_get($id) { mkt_plans_migrate(); return ops_one("SELECT * FROM mkt_plans WHERE id=?", [(int)$id]) ?: null; }

/** A plan's limits as a clean array. */
function mkt_plan_limits($plan) {
    $raw = is_array($plan) ? (string)($plan['limits'] ?? '') : (string)$plan;
    $d = $raw !== '' ? json_decode($raw, true) : [];
    return is_array($d) ? $d : [];
}

/** Create or update a plan from posted fields. Returns [ok, message]. */
function mkt_plan_save(array $in) {
    mkt_plans_migrate();
    $id   = (int)($in['id'] ?? 0);
    $aud  = mkt_audience_norm($in['audience'] ?? 'CLIENT');
    $name = trim((string)($in['name'] ?? ''));
    if ($name === '') return [false, 'Give the plan a name.'];
    $code = trim((string)($in['code'] ?? '')) ?: strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 20));
    $pm   = max(0, (float)($in['price_month'] ?? 0));
    $pa   = ($in['price_annual'] ?? '') !== '' ? max(0, (float)$in['price_annual']) : round($pm * mkt_annual_months(), 2);
    $active = !empty($in['is_active']) ? 1 : 0;
    $sort = (int)($in['sort'] ?? 0);
    // Limits — read only the keys we know, ignore blanks/zeros gracefully.
    $lim = [];
    foreach (mkt_limit_keys() as $k => $lbl) if (isset($in['lim_' . $k]) && $in['lim_' . $k] !== '') $lim[$k] = max(0, (int)$in['lim_' . $k]);
    $now = date('c'); $limJson = json_encode($lim);
    if ($id > 0 && mkt_plan_get($id)) {
        db()->prepare("UPDATE mkt_plans SET code=?, audience=?, name=?, price_month=?, price_annual=?, limits=?, is_active=?, sort=?, updated_at=? WHERE id=?")
            ->execute([$code, $aud, $name, $pm, $pa, $limJson, $active, $sort, $now, $id]);
        return [true, 'Plan updated.'];
    }
    db()->prepare("INSERT INTO mkt_plans (code,audience,name,price_month,price_annual,limits,is_active,sort,created_at,updated_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?)")
        ->execute([$code, $aud, $name, $pm, $pa, $limJson, $active, $sort, $now, $now]);
    return [true, 'Plan added.'];
}

function mkt_plan_delete($id) { mkt_plans_migrate(); db()->prepare("DELETE FROM mkt_plans WHERE id=?")->execute([(int)$id]); return true; }

/** Save the global knobs (annual months, pro free-until, currency). */
function mkt_settings_save(array $in) {
    if (array_key_exists('mkt_annual_months', $in)) setting_set('mkt_annual_months', max(1, (int)$in['mkt_annual_months']));
    if (array_key_exists('mkt_pro_free_until', $in)) {
        $d = trim((string)$in['mkt_pro_free_until']);
        // A friendly "+6 months from today" shortcut.
        if ($d === '+6') $d = date('Y-m-d', strtotime('+6 months'));
        setting_set('mkt_pro_free_until', $d);
    }
    if (array_key_exists('mkt_currency', $in)) setting_set('mkt_currency', trim((string)$in['mkt_currency']));
    // Master enforcement switch — only saved when the settings form was submitted
    // (the checkbox is absent when unticked, so a dedicated marker carries intent).
    if (array_key_exists('mkt_settings_form', $in)) setting_set('mkt_enforce', !empty($in['mkt_enforce']) ? 1 : 0);
    return true;
}

/** Route handler — the Super-Admin marketplace-plans screen (master only). */
function ops_mkt_plans($method) {
    ops_require(function_exists('is_master') && is_master(), 'Only the Super Admin can manage marketplace plans.');
    mkt_plans_migrate();
    if ($method === 'POST') {
        $act = (string)($_POST['action'] ?? '');
        if ($act === 'save_plan')       { [$ok, $msg] = mkt_plan_save($_POST); flash($msg, $ok ? 'success' : 'error'); }
        elseif ($act === 'delete_plan') { mkt_plan_delete((int)($_POST['id'] ?? 0)); flash('Plan removed.'); }
        elseif ($act === 'save_settings') { mkt_settings_save($_POST); flash('Marketplace settings saved.'); }
        elseif ($act === 'save_pack' && function_exists('mkt_credit_pack_save'))   { [$ok, $msg] = mkt_credit_pack_save($_POST); flash($msg, $ok ? 'success' : 'error'); }
        elseif ($act === 'delete_pack' && function_exists('mkt_credit_pack_delete')) { mkt_credit_pack_delete((int)($_POST['id'] ?? 0)); flash('Credit pack removed.'); }
        redirect('/marketplace-plans');
    }
    $edit = ($_GET['edit'] ?? '') !== '' ? mkt_plan_get((int)$_GET['edit']) : null;
    $editPack = ($_GET['edit_pack'] ?? '') !== '' && function_exists('mkt_credit_pack_get') ? mkt_credit_pack_get((int)$_GET['edit_pack']) : null;
    view('ops/mkt_plans', [
        'clientPlans' => mkt_plans_all('CLIENT'),
        'proPlans'    => mkt_plans_all('PRO'),
        'creditPacks' => function_exists('mkt_credit_packs_all') ? mkt_credit_packs_all() : [],
        'editPack'    => $editPack,
        'edit'        => $edit,
        'limitKeys'   => mkt_limit_keys(),
        'audiences'   => mkt_audiences(),
        'annualMonths'=> mkt_annual_months(),
        'proFreeUntil'=> mkt_pro_free_until(),
        'currency'    => mkt_currency(),
        'enforce'     => function_exists('mkt_enforce_on') ? mkt_enforce_on() : false,
    ]);
    return true;
}
