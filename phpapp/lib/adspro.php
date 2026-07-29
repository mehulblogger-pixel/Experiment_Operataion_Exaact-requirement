<?php
// ============================================================================
//  Ads Pro — the link between what advertising COSTS and what it actually EARNS
//
//  WHAT ADS PRO IS. A sister application in the same portfolio
//  (adspro.mghaiapps.com): it plans and runs Meta/Google campaigns, captures
//  leads from WhatsApp click-to-chat and landing pages, and records daily spend
//  per campaign. PHP + SQLite, token-authenticated REST at /api.php?action=…,
//  workspace-scoped by an X-Workspace-Id header.
//
//  WHY THE TWO HALVES ARE WORTH JOINING, and this is the whole argument for the
//  file. Ads Pro can tell you cost per lead. Every ad platform can. What no ad
//  platform and no CRM can tell you is COST PER RUPEE ACTUALLY COLLECTED,
//  because the two facts live in different systems:
//
//      Ads Pro knows      spend, and which campaign produced which lead.
//      This system knows  whether that lead became a deal, whether the deal
//                         became an order, whether the order was invoiced, and
//                         — the only number that pays salaries — whether the
//                         invoice was PAID.
//
//  Every CRM stops at "closed won", which is an amount somebody typed into a
//  box. We hold receipts. So the join produces a number nobody else can quote:
//  ₹40,000 of Meta spend produced ₹6,20,000 invoiced of which ₹4,10,000 has
//  actually arrived, and ₹2,10,000 is 47 days overdue.
//
//  DIRECTION OF FLOW, deliberately asymmetric:
//
//    Ads Pro → here   Leads. They enter the ordinary lead register carrying
//                     their campaign and UTM, so they work with the pipelines,
//                     the funnels, the advisor and the stage gates already
//                     built. They are not a second kind of lead.
//
//    here → Ads Pro   Outcomes. When a deal is won, and again when money is
//                     RECEIVED, Ads Pro is told the true figure so its own
//                     optimisation stops steering on pixel-guessed conversion
//                     value and starts steering on cash.
//
//  NOTHING IS AUTOMATIC AND NOTHING IS SILENT. Import is a button a person
//  presses (or a cron calls), it is idempotent on the Ads Pro record id, and it
//  never overwrites a lead somebody has since edited here. A connector that
//  quietly rewrote local records would make every disagreement between the two
//  systems unexplainable.
//
//  IF ADS PRO IS NOT CONFIGURED, none of this exists: no nav item, no screen,
//  no checks. A customer who does not run advertising should never see it.
// ============================================================================

const ADSPRO_TIMEOUT   = 20;      // seconds; a slow sister app must not hang a page
const ADSPRO_MAX_PULL  = 500;     // leads per import run

function ads_try($fn, $fb = null) { try { return $fn(); } catch (Throwable $e) { return $fb; } }

function ads_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = pk_clause();
    // One row per lead brought across. Holds the Ads Pro id so a second import
    // recognises it, and the campaign so revenue can be attributed later without
    // asking Ads Pro again — the campaign a lead came from does not change, and
    // an integration that needs the other system awake to answer a question
    // about last quarter is not an integration.
    $pdo->exec("CREATE TABLE IF NOT EXISTS ads_leads (
        id $pk, ext_id VARCHAR(64) DEFAULT '', ext_kind VARCHAR(20) DEFAULT 'WHATSAPP',
        workspace_id VARCHAR(64) DEFAULT '',
        lead_id INT NULL, partner_id INT NULL,
        campaign_id VARCHAR(64) DEFAULT '', campaign_name VARCHAR(200) DEFAULT '',
        platform VARCHAR(40) DEFAULT '',
        utm_source VARCHAR(120) DEFAULT '', utm_medium VARCHAR(120) DEFAULT '',
        utm_campaign VARCHAR(160) DEFAULT '', utm_content VARCHAR(160) DEFAULT '',
        contact_name VARCHAR(150) DEFAULT '', contact_email VARCHAR(200) DEFAULT '',
        contact_phone VARCHAR(60) DEFAULT '',
        occurred_at VARCHAR(30) DEFAULT '',
        pushed_won_at VARCHAR(30) DEFAULT '', pushed_paid_at VARCHAR(30) DEFAULT '',
        pushed_value DECIMAL(16,2) DEFAULT 0,
        imported_at VARCHAR(30) DEFAULT '')");
    // Daily spend, cached locally. Kept because the ROI screen must answer for a
    // closed financial year whether or not Ads Pro is reachable that morning.
    $pdo->exec("CREATE TABLE IF NOT EXISTS ads_spend (
        id $pk, workspace_id VARCHAR(64) DEFAULT '',
        campaign_id VARCHAR(64) DEFAULT '', campaign_name VARCHAR(200) DEFAULT '',
        platform VARCHAR(40) DEFAULT '', metric_date VARCHAR(20) DEFAULT '',
        impressions INT DEFAULT 0, clicks INT DEFAULT 0,
        spend DECIMAL(16,2) DEFAULT 0, conversions INT DEFAULT 0,
        pulled_at VARCHAR(30) DEFAULT '')");
    // Every call made and what came back. An integration without a log is an
    // integration nobody can debug at the moment it matters.
    $pdo->exec("CREATE TABLE IF NOT EXISTS ads_sync_log (
        id $pk, ran_at VARCHAR(30) DEFAULT '', action VARCHAR(60) DEFAULT '',
        ok INT DEFAULT 0, http_code INT DEFAULT 0,
        summary VARCHAR(400) DEFAULT '', detail TEXT,
        ran_by VARCHAR(150) DEFAULT '')");
    if (function_exists('act_index')) {
        act_index('ads_leads', 'idx_ads_ext', '(ext_kind, ext_id)');
        act_index('ads_leads', 'idx_ads_lead', '(lead_id)');
        act_index('ads_leads', 'idx_ads_camp', '(campaign_id)');
        act_index('ads_spend', 'idx_spend_day', '(campaign_id, metric_date)');
        act_index('ads_sync_log', 'idx_sync_when', '(ran_at)');
    }
}

// ---- Configuration ----------------------------------------------------------
// Held in settings so it survives an upgrade, and overridable by environment so
// an on-premise install can be pinned from outside the database. The TOKEN is
// only ever read from the environment or written to settings — it is never sent
// back to a browser, which is why ads_config() carries a redacted copy for the
// screen and ads_token() is separate.
function ads_config($reload = false) {
    static $c = null;
    if ($c !== null && !$reload) return $c;
    $env = fn($k, $d = '') => (getenv($k) !== false && getenv($k) !== '') ? getenv($k) : $d;
    return $c = [
        'base'      => rtrim((string)$env('ADSPRO_URL', (string)setting_get('adspro_url', '')), '/'),
        'workspace' => (string)$env('ADSPRO_WORKSPACE', (string)setting_get('adspro_workspace', '')),
        'has_token' => ads_token() !== '',
        'auto'      => (string)setting_get('adspro_autopush', '1') === '1',
    ];
}

function ads_token() {
    $e = getenv('ADSPRO_TOKEN');
    if ($e !== false && $e !== '') return (string)$e;
    return (string)setting_get('adspro_token', '');
}

function ads_on() {
    $c = ads_config();
    return $c['base'] !== '' && $c['workspace'] !== '' && $c['has_token'];
}

// https always; http only to this same machine.
function ads_url_safe($base) {
    $u = parse_url((string)$base);
    if (!$u || empty($u['scheme']) || empty($u['host'])) return false;
    if (strtolower($u['scheme']) === 'https') return true;
    if (strtolower($u['scheme']) !== 'http') return false;
    $h = strtolower((string)$u['host']);
    return $h === 'localhost' || $h === '127.0.0.1' || $h === '::1' || $h === '[::1]';
}

function ads_can_view()   { return can('mod.leads.view') || is_master_of('leads'); }
function ads_can_manage() { return can('settings.manage') || is_master(); }

// ---- The wire ---------------------------------------------------------------
// One place that talks to Ads Pro, so timeouts, headers, error shape and logging
// are decided once. Returns ['ok'=>bool, 'code'=>int, 'data'=>array, 'error'=>string].
function ads_call($action, array $body = null, $log = true) {
    ads_migrate();
    $c = ads_config();
    if (!ads_on()) return ['ok' => false, 'code' => 0, 'data' => [],
                           'error' => 'Ads Pro is not connected. Set its address, workspace and token first.'];

    // The token goes in a header on every call, so plain http would put a
    // long-lived credential on the wire in clear. Refused — except to the same
    // machine, where the two apps are commonly run side by side and there is no
    // wire to listen to.
    if (!ads_url_safe($c['base']))
        return ['ok' => false, 'code' => 0, 'data' => [],
                'error' => 'Refusing to send the API token over plain http to ' . $c['base']
                         . '. Use https, or run Ads Pro on this same machine.'];

    $url = $c['base'] . '/api.php?action=' . rawurlencode($action);
    $ch = curl_init($url);
    $headers = ['Authorization: Bearer ' . ads_token(),
                'X-Workspace-Id: ' . $c['workspace'],
                'Accept: application/json'];
    if ($body !== null) $headers[] = 'Content-Type: application/json';
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => ADSPRO_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER     => $headers,
        // Never turned off. A connector that trusts any certificate is a
        // connector that can be handed somebody else's leads.
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    $out = ['ok' => false, 'code' => $code, 'data' => [], 'error' => ''];
    if ($raw === false) {
        // Say which layer failed. "Could not connect" and "it said no" need
        // different people to fix them.
        $out['error'] = 'Could not reach Ads Pro at ' . $c['base'] . ' — ' . ($cerr ?: 'no response');
    } else {
        $j = json_decode((string)$raw, true);
        if (!is_array($j)) $out['error'] = 'Ads Pro replied with something that was not JSON (HTTP ' . $code . ').';
        elseif ($code >= 400) $out['error'] = (string)($j['error'] ?? ('Ads Pro refused the request (HTTP ' . $code . ').'));
        else { $out['ok'] = true; $out['data'] = $j; }
    }
    if ($log) ads_log($action, $out['ok'], $code, $out['ok'] ? 'ok' : $out['error'],
                      substr((string)$raw, 0, 4000));
    return $out;
}

function ads_log($action, $ok, $code, $summary, $detail = '') {
    ads_migrate();
    $u = function_exists('current_user') ? current_user() : null;
    ads_try(fn() => db()->prepare(
        "INSERT INTO ads_sync_log (ran_at, action, ok, http_code, summary, detail, ran_by)
         VALUES (?,?,?,?,?,?,?)")
        ->execute([date('c'), substr((string)$action, 0, 60), $ok ? 1 : 0, (int)$code,
                   substr((string)$summary, 0, 400), (string)$detail,
                   $u ? user_name($u) : 'system']));
}

function ads_health() {
    if (!ads_on()) return ['ok' => false, 'error' => 'Not connected yet.'];
    $r = ads_call('me', null, false);
    if (!$r['ok']) { ads_log('me', false, $r['code'], $r['error']); return ['ok' => false, 'error' => $r['error']]; }
    $u  = $r['data']['user'] ?? [];
    $ws = $r['data']['workspaces'] ?? [];
    $c  = ads_config();
    $names = [];
    foreach ($ws as $w) if ((string)($w['id'] ?? '') === $c['workspace']) $names[] = (string)($w['name'] ?? '');
    ads_log('me', true, $r['code'], 'Connected as ' . ($u['email'] ?? '?'));
    return ['ok' => true, 'email' => (string)($u['email'] ?? ''), 'name' => (string)($u['name'] ?? ''),
            'workspaces' => count($ws),
            // A token that works but points at a workspace the user cannot see
            // is the failure that looks like success, so it is called out.
            'workspace_ok' => $names !== [], 'workspace_name' => $names[0] ?? ''];
}

// ---- Ads Pro → here: leads --------------------------------------------------
// Idempotent on (ext_kind, ext_id). A lead already imported is counted as a skip
// and left completely alone, including if somebody here has since renamed it,
// reassigned it or moved it down the pipeline.
function ads_import_leads($limit = ADSPRO_MAX_PULL) {
    ads_migrate();
    $r = ads_call('crm_get_contacts', null);
    if (!$r['ok']) return ['err' => $r['error']];

    $rows = $r['data']['contacts'] ?? ($r['data']['data'] ?? []);
    if (!is_array($rows)) $rows = [];
    $c = ads_config();

    $made = 0; $skipped = 0; $failed = 0; $errors = [];
    $seen = db()->prepare("SELECT id FROM ads_leads WHERE ext_kind='CONTACT' AND ext_id=? LIMIT 1");
    $ins  = db()->prepare("INSERT INTO ads_leads
        (ext_id, ext_kind, workspace_id, lead_id, campaign_id, campaign_name, platform,
         utm_source, utm_medium, utm_campaign, utm_content,
         contact_name, contact_email, contact_phone, occurred_at, imported_at)
        VALUES (?, 'CONTACT', ?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    foreach (array_slice($rows, 0, (int)$limit) as $x) {
        $ext = (string)($x['id'] ?? '');
        if ($ext === '') { $failed++; continue; }
        $seen->execute([$ext]);
        if ($seen->fetch()) { $skipped++; continue; }

        // The company is what a lead is chased by. Ads Pro contacts often carry
        // only a person, so fall back rather than refusing the row — a lead with
        // a name and a phone number is still a lead worth ringing.
        $company = trim((string)($x['company'] ?? '')) ?: trim((string)($x['name'] ?? '')) ?: 'Unnamed enquiry';
        $src = strtolower(trim((string)($x['source'] ?? '')));

        $res = ads_try(fn() => lead_create([
            'company_name'  => $company,
            'contact_name'  => (string)($x['name'] ?? ''),
            'contact_email' => (string)($x['email'] ?? ''),
            'contact_phone' => (string)($x['phone'] ?? ''),
            'source'        => ads_map_source($src),
            'source_note'   => 'Ads Pro' . ($src !== '' ? ' — ' . $src : ''),
            'requirement'   => (string)($x['notes'] ?? ''),
        ]), ['err' => 'lead_create failed']);

        if (!empty($res['err'])) { $failed++; if (count($errors) < 5) $errors[] = $company . ': ' . $res['err']; continue; }
        $leadId = (int)($res['id'] ?? 0);

        ads_try(fn() => $ins->execute([
            $ext, $c['workspace'], $leadId ?: null,
            (string)($x['campaign_id'] ?? ''), (string)($x['campaign_name'] ?? ''),
            (string)($x['platform'] ?? $src),
            (string)($x['utm_source'] ?? ''), (string)($x['utm_medium'] ?? ''),
            (string)($x['utm_campaign'] ?? ''), (string)($x['utm_content'] ?? ''),
            substr((string)($x['name'] ?? ''), 0, 150), substr((string)($x['email'] ?? ''), 0, 200),
            substr((string)($x['phone'] ?? ''), 0, 60),
            (string)($x['created_at'] ?? ''), date('c')]));
        $made++;
    }
    $msg = $made . ' new, ' . $skipped . ' already here' . ($failed ? ', ' . $failed . ' refused' : '');
    ads_log('import_leads', $failed === 0, 200, $msg, $errors ? implode("\n", $errors) : '');
    return ['ok' => true, 'made' => $made, 'skipped' => $skipped, 'failed' => $failed,
            'errors' => $errors, 'msg' => $msg];
}

// Ads Pro's vocabulary is not ours, and forcing either to change would break the
// other. One map, in one place, and anything unrecognised lands in OTHER rather
// than inventing a source value the rest of this system has never heard of.
function ads_map_source($s) {
    $map = ['whatsapp' => 'CAMPAIGN', 'meta_ads' => 'CAMPAIGN', 'meta' => 'CAMPAIGN',
            'google' => 'CAMPAIGN', 'google_ads' => 'CAMPAIGN', 'instagram' => 'CAMPAIGN',
            'website' => 'WEBSITE', 'landing' => 'WEBSITE',
            'referral' => 'REFERRAL', 'email' => 'EMAIL_IN',
            'import' => 'OTHER', 'manual' => 'OTHER'];
    $v = $map[strtolower(trim((string)$s))] ?? 'CAMPAIGN';
    // Only ever write a value the lead register actually offers. The list is a
    // configurable master, so a company that renamed or removed one must not end
    // up with leads carrying a source that no filter can select.
    $known = function_exists('lk_options_or') && defined('LEAD_SOURCES')
           ? array_keys(ads_try(fn() => lk_options_or('lead_source', LEAD_SOURCES), LEAD_SOURCES) ?: LEAD_SOURCES)
           : (defined('LEAD_SOURCES') ? array_keys(LEAD_SOURCES) : []);
    if ($known && !in_array($v, $known, true))
        $v = in_array('OTHER', $known, true) ? 'OTHER' : (string)($known[0] ?? '');
    return $v;
}

// ---- Ads Pro → here: spend --------------------------------------------------
function ads_import_spend($days = 90) {
    ads_migrate();
    $r = ads_call('cc_attribution', ['days' => (int)$days]);
    if (!$r['ok']) return ['err' => $r['error']];
    $rows = $r['data']['spend'] ?? ($r['data']['campaigns'] ?? ($r['data']['rows'] ?? []));
    if (!is_array($rows)) $rows = [];
    $c = ads_config();
    $n = 0;
    $del = db()->prepare("DELETE FROM ads_spend WHERE campaign_id=? AND metric_date=?");
    $ins = db()->prepare("INSERT INTO ads_spend
        (workspace_id, campaign_id, campaign_name, platform, metric_date,
         impressions, clicks, spend, conversions, pulled_at) VALUES (?,?,?,?,?,?,?,?,?,?)");
    foreach ($rows as $x) {
        $cid  = (string)($x['campaign_id'] ?? ($x['id'] ?? ''));
        $date = substr((string)($x['metric_date'] ?? ($x['date'] ?? '')), 0, 10);
        if ($cid === '' || $date === '') continue;
        // Replace the day rather than adding to it: a re-pull of the same date
        // must not double the spend, and ad platforms restate recent days.
        ads_try(fn() => $del->execute([$cid, $date]));
        ads_try(fn() => $ins->execute([$c['workspace'], $cid,
            (string)($x['campaign_name'] ?? ($x['name'] ?? '')), (string)($x['platform'] ?? ''), $date,
            (int)($x['impressions'] ?? 0), (int)($x['clicks'] ?? 0),
            (float)($x['spend'] ?? 0), (int)($x['conversions'] ?? 0), date('c')]));
        $n++;
    }
    ads_log('import_spend', true, 200, $n . ' campaign-days stored');
    return ['ok' => true, 'rows' => $n];
}

// ---- here → Ads Pro: outcomes ----------------------------------------------
// The point of the whole file. Ads Pro optimises on whatever conversion value it
// is given; left alone that is a tracking-pixel estimate. Sending the invoiced
// figure is better. Sending the RECEIVED figure is the thing no other CRM can
// do, because no other CRM is holding the receipt.
function ads_push_outcome($adsRow, $stage, $amount, $note = '') {
    if (!ads_on()) return ['err' => 'Ads Pro is not connected.'];
    if (!$adsRow) return ['err' => 'That lead did not come from Ads Pro.'];
    $r = ads_call('crm_save_order', [
        'contact_id'  => (string)$adsRow['ext_id'],
        'amount'      => round((float)$amount, 2),
        'currency'    => 'INR',
        'status'      => $stage === 'PAID' ? 'paid' : 'confirmed',
        'source'      => 'mgh_crm',
        'campaign_id' => (string)$adsRow['campaign_id'],
        'notes'       => trim('Confirmed by MGH CRM. ' . $note),
    ]);
    if (!$r['ok']) return ['err' => $r['error']];
    $col = $stage === 'PAID' ? 'pushed_paid_at' : 'pushed_won_at';
    ads_try(fn() => db()->prepare("UPDATE ads_leads SET $col=?, pushed_value=? WHERE id=?")
                       ->execute([date('c'), round((float)$amount, 2), (int)$adsRow['id']]));
    return ['ok' => true];
}

function ads_row_for_lead($leadId) {
    ads_migrate();
    return ads_try(fn() => ops_one("SELECT * FROM ads_leads WHERE lead_id=? LIMIT 1", [(int)$leadId]), null) ?: null;
}

// ---- Reading ----------------------------------------------------------------
function ads_recent_log($n = 25) {
    ads_migrate();
    return ads_try(fn() => ops_all("SELECT * FROM ads_sync_log ORDER BY id DESC LIMIT " . (int)$n), []) ?: [];
}

function ads_counts() {
    ads_migrate();
    $v = fn($sql, $a = []) => (float)(ads_try(fn() => ops_val($sql, $a), 0) ?: 0);
    return [
        'leads'    => (int)$v("SELECT COUNT(*) FROM ads_leads"),
        'linked'   => (int)$v("SELECT COUNT(*) FROM ads_leads WHERE lead_id IS NOT NULL"),
        'spend'    => $v("SELECT COALESCE(SUM(spend),0) FROM ads_spend"),
        'campaigns'=> (int)$v("SELECT COUNT(DISTINCT campaign_id) FROM ads_spend WHERE campaign_id <> ''"),
        'pushed'   => (int)$v("SELECT COUNT(*) FROM ads_leads WHERE pushed_paid_at <> ''"),
        'last'     => (string)(ads_try(fn() => ops_val("SELECT ran_at FROM ads_sync_log WHERE ok=1 ORDER BY id DESC LIMIT 1"), '') ?: ''),
    ];
}

// ---- Routes -----------------------------------------------------------------
function ops_adspro($route, $method) {
    ops_require(ads_can_view(), 'You cannot open the Ads Pro connection.');
    ads_migrate();

    if ($route === 'adspro-save' && $method === 'POST') {
        ops_require(ads_can_manage(), 'You cannot change the Ads Pro connection.');
        setting_set('adspro_url', rtrim(trim((string)($_POST['url'] ?? '')), '/'));
        setting_set('adspro_workspace', trim((string)($_POST['workspace'] ?? '')));
        setting_set('adspro_autopush', empty($_POST['autopush']) ? '0' : '1');
        // An empty token box leaves the stored one alone, so somebody editing the
        // address does not silently disconnect the link by not retyping a secret
        // the form never showed them.
        $tok = trim((string)($_POST['token'] ?? ''));
        if ($tok !== '') setting_set('adspro_token', $tok);
        if (!empty($_POST['forget_token'])) setting_set('adspro_token', '');
        ads_config(true);
        flash('Connection saved.', 'success');
        redirect('/adspro');
    }
    if ($route === 'adspro-test' && $method === 'POST') {
        ops_require(ads_can_manage(), 'You cannot test the Ads Pro connection.');
        $h = ads_health();
        if (!$h['ok']) flash('Not connected: ' . $h['error'], 'error');
        elseif (!$h['workspace_ok']) flash('The token works — signed in as ' . $h['email']
              . ' — but that account cannot see the workspace id you entered. Check the workspace.', 'warning');
        else flash('Connected to Ads Pro as ' . $h['email'] . ', workspace “' . $h['workspace_name'] . '”.', 'success');
        redirect('/adspro');
    }
    if ($route === 'adspro-import' && $method === 'POST') {
        ops_require(can('mod.leads.edit') || is_master_of('leads'), 'You cannot create leads.');
        $r = ads_import_leads();
        if (!empty($r['err'])) flash($r['err'], 'error');
        else flash('Leads brought across: ' . $r['msg'] . '.', $r['failed'] ? 'warning' : 'success');
        redirect('/adspro');
    }
    if ($route === 'adspro-spend' && $method === 'POST') {
        ops_require(ads_can_manage(), 'You cannot refresh the spend figures.');
        $r = ads_import_spend((int)($_POST['days'] ?? 90));
        if (!empty($r['err'])) flash($r['err'], 'error');
        else flash($r['rows'] . ' campaign-days of spend stored.', 'success');
        redirect('/adspro');
    }

    view('ops/adspro', [
        'cfg'       => ads_config(true),
        'on'        => ads_on(),
        'counts'    => ads_counts(),
        'log'       => ads_recent_log(20),
        'canManage' => ads_can_manage(),
        'envUrl'    => getenv('ADSPRO_URL') !== false && getenv('ADSPRO_URL') !== '',
        'envTok'    => getenv('ADSPRO_TOKEN') !== false && getenv('ADSPRO_TOKEN') !== '',
    ]);
    return true;
}
