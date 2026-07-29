<?php
// ============================================================================
//  Two-way sync with Ads Pro — leads and enquiries, in both directions
//
//  WHAT WAS THERE BEFORE. Leads came one way (Ads Pro → here) when somebody
//  pressed a button, and outcomes went back. That is a feed, not an integration:
//  a lead raised by a salesperson here never reached Ads Pro, so Ads Pro could
//  not market to them, could not build a lookalike audience from them, and could
//  not exclude them from a campaign aimed at winning them.
//
//  WHAT TWO-WAY ACTUALLY REQUIRES, and none of it is optional:
//
//  1. AN OUTBOX, NOT A LIVE CALL. Pushing on save means a coordinator saving a
//     lead waits for an HTTP round trip to another server. When Ads Pro is slow
//     they wait; when it is down they wait for the connect timeout and then see
//     an error about a system they have never heard of, on a form that saved
//     perfectly well. So a save writes a row to ads_outbox and returns. The
//     queue is drained by cron, or by a button. THE LOCAL SAVE NEVER FAILS
//     BECAUSE ADS PRO IS UNREACHABLE — that is the single most important
//     sentence in this file.
//
//  2. FIELD OWNERSHIP, NOT LAST-WRITE-WINS. Two systems editing the same record
//     with "newest wins" is a machine for losing work quietly. Each field has
//     exactly one owner:
//
//        Ads Pro owns   campaign, platform, UTM, and the fact of first contact.
//                       We never write these back. They are facts about an
//                       advertisement, and we do not run the advertisements.
//        We own         status, stage, value, owner, and the sales outcome. Ads
//                       Pro is told ours; we never take its status over ours,
//                       because a person here moved that lead deliberately.
//        Contact details are shared, and settled by a rule that cannot lose
//                       anything: a pull FILLS BLANKS ONLY. If a coordinator has
//                       typed a phone number, no import overwrites it. If the
//                       field is empty, Ads Pro's value is better than nothing.
//
//  3. A LOOP BREAKER. A push updates the record in Ads Pro; the next pull sees
//     it changed and would push it back for ever. Every row carries a hash of
//     what was last sent, and a push that would send the identical payload is
//     dropped before it leaves. Sync loops are the classic failure of every
//     two-way integration and they are silent until the API bill arrives.
//
//  4. AN ANSWER FOR "WHY IS THIS ONE NOT THERE". Every queued item ends as SENT,
//     FAILED with the reason, or SKIPPED with the reason. A record that did not
//     travel must be able to say why, on a screen, without anybody reading a log
//     file on a server they cannot reach.
//
//  ENQUIRIES. Ads Pro has no separate enquiry — everything is a contact with a
//  status. So an enquiry raised here becomes a contact there marked qualified,
//  carrying its number in the notes so the two can be told apart by a human
//  looking at either system. Coming the other way, an Ads Pro contact that has
//  been qualified arrives as an enquiry rather than a lead, because that is what
//  it has become.
// ============================================================================

const ADS_OUT_KINDS = ['LEAD' => 'Lead', 'INQUIRY' => 'Enquiry', 'OUTCOME' => 'Sale confirmed'];
const ADS_OUT_STATUS = ['PENDING' => 'Waiting to go', 'SENT' => 'Sent', 'FAILED' => 'Failed', 'SKIPPED' => 'Not sent'];
const ADS_FLUSH_MAX = 100;
const ADS_RETRY_MAX = 6;   // ~6 cron runs before a failure stops retrying and stands

// Their vocabulary is lead / qualified / customer / lost. Ours is richer on both
// entities, so the map is one-way lossy by design: we send the nearest true
// thing rather than inventing a fifth value in a system that only has four.
function ads_status_out($kind, $row) {
    if ($kind === 'INQUIRY') {
        $s = strtoupper((string)($row['status'] ?? ''));
        if ($s === 'CONVERTED' || $s === 'QUOTED') return 'qualified';
        if ($s === 'LOST' || $s === 'CLOSED' || $s === 'DROPPED') return 'lost';
        return 'qualified';           // an enquiry has already asked us for something
    }
    $s = strtoupper((string)($row['status'] ?? 'OPEN'));
    if ($s === 'WON' || $s === 'CONVERTED') return 'customer';
    if ($s === 'LOST') return 'lost';
    // An open lead that has reached a stage with real probability is qualified.
    return (int)($row['probability'] ?? 0) >= 40 ? 'qualified' : 'lead';
}

function ads_sync_migrate() {
    static $done = false; if ($done) return; $done = true;
    ads_migrate();
    $pdo = db(); $pk = pk_clause();
    // The queue. Deliberately holds the local id rather than a built payload:
    // the payload is composed at flush time from the record as it stands, so a
    // lead edited three times between two cron runs sends once, current.
    $pdo->exec("CREATE TABLE IF NOT EXISTS ads_outbox (
        id $pk, kind VARCHAR(20) DEFAULT 'LEAD', local_id INT,
        reason VARCHAR(120) DEFAULT '',
        status VARCHAR(20) DEFAULT 'PENDING', attempts INT DEFAULT 0,
        last_error VARCHAR(400) DEFAULT '',
        queued_at VARCHAR(30) DEFAULT '', sent_at VARCHAR(30) DEFAULT '',
        queued_by VARCHAR(150) DEFAULT '')");
    // ads_leads grew from a one-way import table. These columns make it the link
    // table for both entities and both directions without losing the rows that
    // are already in it — lead_id keeps working, so the return report is untouched.
    ensure_column('ads_leads', 'local_kind', "VARCHAR(20) DEFAULT 'LEAD'");
    ensure_column('ads_leads', 'local_id', 'INT NULL');
    ensure_column('ads_leads', 'origin', "VARCHAR(20) DEFAULT 'ADSPRO'");
    ensure_column('ads_leads', 'push_hash', "VARCHAR(64) DEFAULT ''");
    ensure_column('ads_leads', 'remote_status', "VARCHAR(30) DEFAULT ''");
    ensure_column('ads_leads', 'last_push_at', "VARCHAR(30) DEFAULT ''");
    ensure_column('ads_leads', 'last_pull_at', "VARCHAR(30) DEFAULT ''");
    // Set when the company had to be invented from the person's name at import,
    // because Ads Pro had none. Without it, "never overwrite a non-empty field"
    // permanently refuses the real company name the moment Ads Pro learns it —
    // protecting a placeholder we wrote ourselves as if a person had typed it.
    ensure_column('ads_leads', 'company_fallback', 'INT DEFAULT 0');
    // Rows written before local_kind existed are all leads, and local_id must
    // mirror lead_id or the link is only half migrated.
    ads_try(fn() => db()->exec("UPDATE ads_leads SET local_kind='LEAD' WHERE local_kind IS NULL OR local_kind=''"));
    ads_try(fn() => db()->exec("UPDATE ads_leads SET local_id=lead_id WHERE (local_id IS NULL OR local_id=0) AND lead_id IS NOT NULL"));
    if (function_exists('act_index')) {
        act_index('ads_outbox', 'idx_outbox_go', '(status, id)');
        act_index('ads_leads', 'idx_ads_local', '(local_kind, local_id)');
    }
}

function ads_auto_push() { return ads_on() && (string)setting_get('adspro_autopush', '1') === '1'; }

// ---- Queueing ---------------------------------------------------------------
// Called from the ordinary save paths. Cheap, local, and silent: it must never
// throw, never block, and never be the reason a lead failed to save.
function ads_queue($kind, $localId, $reason = '', $force = false) {
    // Backfill passes $force: a person pressing "send everything we already have"
    // has asked for it explicitly, and refusing because the automatic setting is
    // off would be the software second-guessing a direct instruction.
    if (!$force && !ads_auto_push()) return;
    if (!$force && !ads_on()) return;
    if (!isset(ADS_OUT_KINDS[$kind]) || (int)$localId <= 0) return;
    ads_try(function () use ($kind, $localId, $reason) {
        ads_sync_migrate();
        // One pending row per record. A lead edited five times before the next
        // flush should be sent once, carrying its final state.
        $open = ops_one("SELECT id FROM ads_outbox WHERE kind=? AND local_id=? AND status='PENDING' LIMIT 1",
                        [$kind, (int)$localId]);
        if ($open) {
            db()->prepare("UPDATE ads_outbox SET reason=?, queued_at=? WHERE id=?")
               ->execute([substr((string)$reason, 0, 120), date('c'), (int)$open['id']]);
            return;
        }
        $u = current_user();
        db()->prepare("INSERT INTO ads_outbox (kind, local_id, reason, status, queued_at, queued_by)
                       VALUES (?,?,?, 'PENDING', ?, ?)")
           ->execute([$kind, (int)$localId, substr((string)$reason, 0, 120), date('c'),
                      $u ? user_name($u) : 'system']);
    });
}

function ads_queue_lead($id, $why = '')    { ads_queue('LEAD', $id, $why); }
function ads_queue_inquiry($id, $why = '') { ads_queue('INQUIRY', $id, $why); }

// ---- Composing what goes out ------------------------------------------------
function ads_payload($kind, $localId) {
    if ($kind === 'INQUIRY') {
        $r = ads_try(fn() => ops_one("SELECT * FROM crm_inquiries WHERE id=?", [(int)$localId]), null);
        if (!$r) return null;
        return [
            'name'    => trim((string)($r['contact_name'] ?? '')) ?: trim((string)($r['client_name'] ?? '')) ?: 'Enquiry',
            'email'   => (string)($r['contact_email'] ?? ''),
            'phone'   => (string)($r['contact_mobile'] ?? ''),
            'company' => (string)($r['client_name'] ?? ''),
            'status'  => ads_status_out('INQUIRY', $r),
            'source'  => 'mgh_crm',
            // The reference travels in the notes because Ads Pro has nowhere else
            // to keep it, and a person looking at either system has to be able to
            // tell that these two records are the same thing.
            'notes'   => trim('MGH CRM enquiry ' . (string)($r['inquiry_no'] ?? '') . '. '
                            . (string)($r['subject'] ?? '') . ' ' . (string)($r['service_requirement'] ?? '')),
        ];
    }
    $r = ads_try(fn() => ops_one(
        "SELECT l.*, s.probability FROM leads l LEFT JOIN pipeline_stages s ON s.id = l.stage_id
         WHERE l.id=?", [(int)$localId]), null);
    if (!$r) return null;
    return [
        'name'    => trim((string)($r['contact_name'] ?? '')) ?: trim((string)($r['company_name'] ?? '')) ?: 'Lead',
        'email'   => (string)($r['contact_email'] ?? ''),
        'phone'   => (string)($r['contact_phone'] ?? ''),
        'company' => (string)($r['company_name'] ?? ''),
        'status'  => ads_status_out('LEAD', $r),
        'source'  => 'mgh_crm',
        'notes'   => trim('MGH CRM lead ' . (string)($r['ref'] ?? '') . '. ' . (string)($r['requirement'] ?? '')),
    ];
}

function ads_link($kind, $localId) {
    ads_sync_migrate();
    return ads_try(fn() => ops_one("SELECT * FROM ads_leads WHERE local_kind=? AND local_id=? LIMIT 1",
                                   [$kind, (int)$localId]), null) ?: null;
}

// ---- Draining the queue -----------------------------------------------------
// Returns counts. Called by cron and by the Sync now button. Never called during
// an ordinary page save.
function ads_flush($limit = ADS_FLUSH_MAX) {
    ads_sync_migrate();
    if (!ads_on()) return ['err' => 'Ads Pro is not connected.'];
    // FAILED rows are retried, up to a limit. Without this a single minute of
    // downtime silently loses that lead for ever: it was never sent, and nothing
    // would ever try again. With an unbounded retry, one permanently malformed
    // record is retried at every cron run until somebody notices the log — so the
    // count is capped and the row then stands as a visible, explained failure.
    $rows = ads_try(fn() => ops_all(
        "SELECT * FROM ads_outbox
         WHERE status='PENDING' OR (status='FAILED' AND attempts < " . ADS_RETRY_MAX . ")
         ORDER BY id LIMIT " . (int)$limit), []) ?: [];
    $sent = 0; $failed = 0; $skipped = 0; $errors = [];

    foreach ($rows as $q) {
        $kind = (string)$q['kind']; $lid = (int)$q['local_id'];
        $mark = function ($status, $err = '') use ($q) {
            ads_try(fn() => db()->prepare(
                "UPDATE ads_outbox SET status=?, attempts=attempts+1, last_error=?, sent_at=? WHERE id=?")
                ->execute([$status, $status === 'SENT' ? '' : substr((string)$err, 0, 400),
                           date('c'), (int)$q['id']]));
        };

        if ($kind === 'OUTCOME') { $r = ads_flush_outcome($lid); }
        else {
            $body = ads_payload($kind, $lid);
            if (!$body) { $mark('SKIPPED', 'The record no longer exists here.'); $skipped++; continue; }
            $link = ads_link($kind, $lid);
            // The loop breaker. If what we are about to send is byte-identical to
            // what we last sent, the change came from Ads Pro in the first place
            // and sending it back achieves nothing but another round trip.
            $hash = hash('sha256', json_encode($body));
            if ($link && (string)$link['push_hash'] === $hash) {
                $mark('SKIPPED', 'Nothing has changed since it was last sent.'); $skipped++; continue;
            }
            if ($link && trim((string)$link['ext_id']) !== '') $body['id'] = (string)$link['ext_id'];
            $r = ads_call('crm_save_contact', $body);
            if ($r['ok']) {
                $ext = (string)($r['data']['contact']['id'] ?? ($body['id'] ?? ''));
                ads_remember($kind, $lid, $ext, $hash, (string)$body['status'], $link);
            }
        }

        if (!empty($r['ok'])) { $mark('SENT'); $sent++; }
        else {
            $mark('FAILED', $r['error'] ?? 'unknown'); $failed++;
            if (count($errors) < 5) $errors[] = ADS_OUT_KINDS[$kind] . ' #' . $lid . ': ' . ($r['error'] ?? '');
        }
    }
    $msg = $sent . ' sent' . ($skipped ? ', ' . $skipped . ' not needed' : '') . ($failed ? ', ' . $failed . ' failed' : '');
    if ($rows) ads_log('flush_outbox', $failed === 0, 200, $msg, $errors ? implode("\n", $errors) : '');
    return ['ok' => true, 'sent' => $sent, 'failed' => $failed, 'skipped' => $skipped,
            'errors' => $errors, 'msg' => $msg, 'looked_at' => count($rows)];
}

function ads_remember($kind, $localId, $extId, $hash, $status, $existing = null) {
    ads_sync_migrate();
    $c = ads_config();
    ads_try(function () use ($kind, $localId, $extId, $hash, $status, $existing, $c) {
        if ($existing) {
            db()->prepare("UPDATE ads_leads SET ext_id=?, push_hash=?, remote_status=?, last_push_at=? WHERE id=?")
               ->execute([$extId, $hash, $status, date('c'), (int)$existing['id']]);
            return;
        }
        db()->prepare("INSERT INTO ads_leads
            (ext_id, ext_kind, workspace_id, lead_id, local_kind, local_id, origin,
             push_hash, remote_status, last_push_at, imported_at)
            VALUES (?, 'CONTACT', ?, ?, ?, ?, 'CRM', ?, ?, ?, ?)")
           ->execute([$extId, $c['workspace'], $kind === 'LEAD' ? (int)$localId : null,
                      $kind, (int)$localId, $hash, $status, date('c'), date('c')]);
    });
}

function ads_flush_outcome($linkId) {
    $row = ads_try(fn() => ops_one("SELECT * FROM ads_leads WHERE id=?", [(int)$linkId]), null);
    if (!$row) return ['ok' => false, 'error' => 'The link no longer exists.'];
    return ads_push_outcome($row, 'PAID', (float)$row['pushed_value'], 'Queued outcome');
}

// ---- Pulling ----------------------------------------------------------------
// The other half. Existing links are UPDATED now rather than skipped, but only
// where updating cannot destroy anything: blanks are filled, owned fields are
// left alone.
function ads_pull($limit = ADSPRO_MAX_PULL) {
    ads_sync_migrate();
    if (!ads_on()) return ['err' => 'Ads Pro is not connected.'];

    // WhatsApp click-to-chat leads live in their own table over there until this
    // is called. Asking first means an enquiry that arrived on WhatsApp this
    // morning is in our register this morning, not whenever somebody in Ads Pro
    // remembers to press its import button.
    ads_try(fn() => ads_call('crm_import_wa_leads', []));

    $r = ads_call('crm_get_contacts', null);
    if (!$r['ok']) return ['err' => $r['error']];
    $rows = $r['data']['contacts'] ?? ($r['data']['data'] ?? []);
    if (!is_array($rows)) $rows = [];

    $made = 0; $updated = 0; $skipped = 0; $failed = 0; $errors = []; $asInquiry = 0;
    foreach (array_slice($rows, 0, (int)$limit) as $x) {
        $ext = (string)($x['id'] ?? '');
        if ($ext === '') { $failed++; continue; }

        $link = ads_try(fn() => ops_one("SELECT * FROM ads_leads WHERE ext_id=? LIMIT 1", [$ext]), null);
        if ($link) {
            $n = ads_pull_update($link, $x);
            if ($n) $updated++; else $skipped++;
            continue;
        }
        // Anything we ourselves pushed comes back with source mgh_crm. Creating a
        // second local record from our own record is how a register doubles in
        // size overnight, so it is refused by name rather than by accident.
        if (strtolower((string)($x['source'] ?? '')) === 'mgh_crm') { $skipped++; continue; }

        $st = strtolower((string)($x['status'] ?? 'lead'));
        // A contact they have already qualified has asked us for something, so it
        // arrives as an enquiry. Filing it as a fresh lead would put a live
        // conversation back at the top of a funnel somebody has to work down again.
        $res = ($st === 'qualified' || $st === 'customer')
             ? ads_make_inquiry($x, $ext)
             : ads_make_lead($x, $ext);
        if (!empty($res['err'])) { $failed++; if (count($errors) < 5) $errors[] = $res['err']; continue; }
        if (!empty($res['inquiry'])) $asInquiry++;
        $made++;
    }
    $msg = $made . ' new' . ($asInquiry ? ' (' . $asInquiry . ' as enquiries)' : '')
         . ', ' . $updated . ' updated, ' . $skipped . ' unchanged' . ($failed ? ', ' . $failed . ' refused' : '');
    ads_log('pull', $failed === 0, 200, $msg, $errors ? implode("\n", $errors) : '');
    return ['ok' => true, 'made' => $made, 'updated' => $updated, 'skipped' => $skipped,
            'failed' => $failed, 'errors' => $errors, 'msg' => $msg];
}

// FILL BLANKS ONLY. This function may never overwrite something a person typed.
function ads_pull_update($link, $x) {
    $kind = (string)($link['local_kind'] ?: 'LEAD');
    $lid  = (int)($link['local_id'] ?: $link['lead_id']);
    if ($lid <= 0) return false;

    [$table, $map] = $kind === 'INQUIRY'
        ? ['crm_inquiries', ['contact_name' => 'name', 'contact_email' => 'email', 'contact_mobile' => 'phone', 'client_name' => 'company']]
        : ['leads',         ['contact_name' => 'name', 'contact_email' => 'email', 'contact_phone' => 'phone', 'company_name' => 'company']];

    $cur = ads_try(fn() => ops_one("SELECT * FROM $table WHERE id=?", [$lid]), null);
    if (!$cur) return false;

    // The one field allowed past "never overwrite", and only under conditions
    // that make loss impossible: we invented this company from the person's name
    // ourselves, nobody here has since changed it, and Ads Pro now has a real one.
    $companyCol = $kind === 'INQUIRY' ? 'client_name' : 'company_name';
    $mayFixCompany = (int)($link['company_fallback'] ?? 0) === 1
        && trim((string)($cur[$companyCol] ?? '')) === trim((string)($cur['contact_name'] ?? ''))
        && trim((string)($x['company'] ?? '')) !== '';

    $sets = []; $args = [];
    foreach ($map as $col => $key) {
        $incoming = trim((string)($x[$key] ?? ''));
        if ($incoming === '') continue;
        $mine = trim((string)($cur[$col] ?? ''));
        if ($mine !== '' && !($col === $companyCol && $mayFixCompany)) continue;   // ours stands
        $sets[] = "$col=?"; $args[] = substr($incoming, 0, 200);
    }
    // Once replaced it is a real value like any other, so the exemption is spent.
    if ($mayFixCompany)
        ads_try(fn() => db()->prepare("UPDATE ads_leads SET company_fallback=0 WHERE id=?")->execute([(int)$link['id']]));
    // Their status is recorded on the link so it is visible, but it is NEVER
    // written onto the record. Somebody here moved that lead deliberately.
    ads_try(fn() => db()->prepare("UPDATE ads_leads SET remote_status=?, last_pull_at=? WHERE id=?")
        ->execute([strtolower((string)($x['status'] ?? '')), date('c'), (int)$link['id']]));
    if (!$sets) return false;
    $args[] = $lid;
    ads_try(fn() => db()->prepare("UPDATE $table SET " . implode(',', $sets) . " WHERE id=?")->execute($args));
    return true;
}

function ads_make_lead($x, $ext) {
    $realCompany = trim((string)($x['company'] ?? ''));
    $company = $realCompany ?: trim((string)($x['name'] ?? '')) ?: 'Unnamed enquiry';
    $res = ads_try(fn() => lead_create([
        'company_name'  => $company,
        'contact_name'  => (string)($x['name'] ?? ''),
        'contact_email' => (string)($x['email'] ?? ''),
        'contact_phone' => (string)($x['phone'] ?? ''),
        'source'        => ads_map_source((string)($x['source'] ?? '')),
        'source_note'   => 'Ads Pro' . (trim((string)($x['source'] ?? '')) !== '' ? ' — ' . $x['source'] : ''),
        'requirement'   => (string)($x['notes'] ?? ''),
    ]), ['err' => 'lead_create failed']);
    if (!empty($res['err'])) return ['err' => $company . ': ' . $res['err']];
    ads_store_link('LEAD', (int)$res['id'], $ext, $x, $realCompany === '');
    return ['ok' => true];
}

function ads_make_inquiry($x, $ext) {
    if (!function_exists('crm_next_inquiry_no')) return ads_make_lead($x, $ext);
    $no = ads_try(fn() => crm_next_inquiry_no(), '');
    if ($no === '') return ads_make_lead($x, $ext);
    $company = trim((string)($x['company'] ?? '')) ?: trim((string)($x['name'] ?? '')) ?: 'Enquiry from advertising';
    $ok = ads_try(fn() => db()->prepare(
        "INSERT INTO crm_inquiries (inquiry_no, client_id, client_name, contact_name, contact_email, contact_mobile,
                                    subject, service_requirement, sbu, source, received_date, assigned_to, status,
                                    notes, created_by, created_at)
         VALUES (?, NULL, ?,?,?,?,?,?,'', ?, ?, NULL, 'OPEN', ?, 'Ads Pro', ?)")
        ->execute([$no, substr($company, 0, 200),
                   substr((string)($x['name'] ?? ''), 0, 150),
                   substr((string)($x['email'] ?? ''), 0, 200),
                   substr((string)($x['phone'] ?? ''), 0, 40),
                   substr('Enquiry from ' . ((string)($x['source'] ?? 'advertising')), 0, 255),
                   (string)($x['notes'] ?? ''),
                   ads_inquiry_source(), date('Y-m-d'),
                   'Brought across from Ads Pro, where it was already marked ' . (string)($x['status'] ?? 'qualified') . '.',
                   date('c')]), false);
    if ($ok === false) return ['err' => $company . ': the enquiry could not be written.'];
    $id = (int)db()->lastInsertId();
    ads_store_link('INQUIRY', $id, $ext, $x);
    return ['ok' => true, 'inquiry' => true];
}

// Same discipline as the lead source: only ever write a value the register offers.
function ads_inquiry_source() {
    $known = defined('INQUIRY_SOURCES') ? array_keys(INQUIRY_SOURCES) : [];
    foreach (['CAMPAIGN', 'ADS', 'WEBSITE', 'OTHER'] as $want)
        if (in_array($want, $known, true)) return $want;
    return (string)($known[0] ?? 'EMAIL');
}

function ads_store_link($kind, $localId, $ext, $x, $companyWasInvented = false) {
    $c = ads_config();
    ads_try(fn() => db()->prepare("INSERT INTO ads_leads
        (ext_id, ext_kind, workspace_id, lead_id, local_kind, local_id, origin,
         campaign_id, campaign_name, platform, utm_source, utm_medium, utm_campaign, utm_content,
         contact_name, contact_email, contact_phone, occurred_at, remote_status, last_pull_at, imported_at,
         company_fallback)
        VALUES (?, 'CONTACT', ?,?,?,?, 'ADSPRO', ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$ext, $c['workspace'], $kind === 'LEAD' ? (int)$localId : null, $kind, (int)$localId,
                   (string)($x['campaign_id'] ?? ''), (string)($x['campaign_name'] ?? ''),
                   (string)($x['platform'] ?? ($x['source'] ?? '')),
                   (string)($x['utm_source'] ?? ''), (string)($x['utm_medium'] ?? ''),
                   (string)($x['utm_campaign'] ?? ''), (string)($x['utm_content'] ?? ''),
                   substr((string)($x['name'] ?? ''), 0, 150), substr((string)($x['email'] ?? ''), 0, 200),
                   substr((string)($x['phone'] ?? ''), 0, 60), (string)($x['created_at'] ?? ''),
                   strtolower((string)($x['status'] ?? '')), date('c'), date('c'),
                   $companyWasInvented ? 1 : 0]));
}

// ---- Both directions, one act -----------------------------------------------
function ads_sync_now($limit = ADS_FLUSH_MAX) {
    // Push first. If a lead was created here and its outcome recorded in the same
    // window, Ads Pro should learn about the record before we ask it what it
    // knows — otherwise the pull sees a contact that does not exist yet and the
    // two systems disagree for one cycle for no reason.
    $out = ads_flush($limit);
    $in  = ads_pull();
    return ['push' => $out, 'pull' => $in];
}

// ---- Reading ----------------------------------------------------------------
function ads_outbox_rows($status = 'PENDING', $n = 60) {
    ads_sync_migrate();
    return ads_try(fn() => ops_all(
        "SELECT * FROM ads_outbox WHERE status=? ORDER BY id DESC LIMIT " . (int)$n, [$status]), []) ?: [];
}

function ads_outbox_counts() {
    ads_sync_migrate();
    $out = [];
    foreach (array_keys(ADS_OUT_STATUS) as $s)
        $out[$s] = (int)(ads_try(fn() => ops_val("SELECT COUNT(*) FROM ads_outbox WHERE status=?", [$s]), 0) ?: 0);
    // Failures split in two, because they need different reactions: one will fix
    // itself when the other end comes back, the other never will.
    $out['GIVEN_UP'] = (int)(ads_try(fn() => ops_val(
        "SELECT COUNT(*) FROM ads_outbox WHERE status='FAILED' AND attempts >= " . ADS_RETRY_MAX), 0) ?: 0);
    $out['WILL_RETRY'] = max(0, $out['FAILED'] - $out['GIVEN_UP']);
    return $out;
}

function ads_link_counts() {
    ads_sync_migrate();
    $v = fn($sql, $a = []) => (int)(ads_try(fn() => ops_val($sql, $a), 0) ?: 0);
    return [
        'leads_in'     => $v("SELECT COUNT(*) FROM ads_leads WHERE local_kind='LEAD' AND origin='ADSPRO'"),
        'leads_out'    => $v("SELECT COUNT(*) FROM ads_leads WHERE local_kind='LEAD' AND origin='CRM'"),
        'inq_in'       => $v("SELECT COUNT(*) FROM ads_leads WHERE local_kind='INQUIRY' AND origin='ADSPRO'"),
        'inq_out'      => $v("SELECT COUNT(*) FROM ads_leads WHERE local_kind='INQUIRY' AND origin='CRM'"),
        'disagreeing'  => $v("SELECT COUNT(*) FROM ads_leads WHERE remote_status<>'' AND last_push_at<>''
                              AND remote_status NOT IN ('customer','lost')
                              AND EXISTS (SELECT 1 FROM leads l WHERE l.id=ads_leads.local_id AND l.status<>'OPEN')"),
    ];
}

// ---- Backfill ---------------------------------------------------------------
// Everything already here that Ads Pro has never seen. Queued rather than sent,
// so pressing it on a register of nine thousand leads does not open nine thousand
// HTTP connections from inside a web request.
function ads_backfill($kind, $limit = 500) {
    ads_sync_migrate();
    $table = $kind === 'INQUIRY' ? 'crm_inquiries' : 'leads';
    $rows = ads_try(fn() => ops_all(
        "SELECT t.id FROM $table t
         WHERE NOT EXISTS (SELECT 1 FROM ads_leads a WHERE a.local_kind=? AND a.local_id=t.id)
         ORDER BY t.id DESC LIMIT " . (int)$limit, [$kind]), []) ?: [];
    $n = 0;
    foreach ($rows as $r) { ads_queue($kind, (int)$r['id'], 'Backfill', true); $n++; }
    return $n;
}
