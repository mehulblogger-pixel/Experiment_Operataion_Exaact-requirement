<?php
// ============================================================================
//  Inspection items & samples — ISO/IEC 17020 §7.2 (and 17025 §7.4)
//
//  The one operational Clause-7 register the app was missing: when a body
//  RECEIVES a physical thing to inspect or test — a material sample, a failed
//  component, a weld coupon, a product unit — the standard asks for a record of
//  what came in, in what condition, where it is kept, and what finally happened
//  to it, with an unbroken chain of custody in between. An assessor asks to see
//  exactly this.
//
//  Configuration-first, per the standing rule:
//   - item type, condition-on-receipt and storage location are editable MASTER
//     lists (add/rename/remove under Masters), not fixed in code;
//   - anything else a particular body wants to capture goes on as a CUSTOM FIELD
//     (entity 'sample'), no code needed;
//   - the whole register is gated behind the accreditation pack, so a customer
//     who does not handle items never sees it.
//
//  It reuses the proven pieces (masters, custom fields, the audit log) and adds
//  only two thin tables: the item, and its custody trail.
// ============================================================================

// The lifecycle. RECEIVED is where everything starts; the two closed states are
// how a thing leaves — returned to its owner, or disposed of. HOLD parks an item
// (e.g. awaiting instruction) without closing it.
const SAMPLE_STATUSES = ['RECEIVED', 'IN_TESTING', 'HOLD', 'RETURNED', 'DISPOSED'];
const SAMPLE_OPEN      = ['RECEIVED', 'IN_TESTING', 'HOLD'];

function samples_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS sample_items (
            id $pk, item_code VARCHAR(30) DEFAULT '', partner_id INT NULL, call_id INT NULL, job_id INT NULL,
            report_doc_id INT NULL, description VARCHAR(300) DEFAULT '', item_type VARCHAR(40) DEFAULT '',
            maker_ref VARCHAR(120) DEFAULT '', quantity VARCHAR(40) DEFAULT '', unit VARCHAR(20) DEFAULT '',
            received_on VARCHAR(20) DEFAULT '', received_by VARCHAR(150) DEFAULT '',
            condition_code VARCHAR(40) DEFAULT '', condition_note VARCHAR(300) DEFAULT '',
            storage_code VARCHAR(40) DEFAULT '', status VARCHAR(20) DEFAULT 'RECEIVED',
            disposition VARCHAR(20) DEFAULT '', closed_on VARCHAR(20) DEFAULT '', closed_by VARCHAR(150) DEFAULT '',
            closed_note VARCHAR(300) DEFAULT '', office_id INT NULL, sbu VARCHAR(40) DEFAULT '',
            notes TEXT, created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '',
            updated_at VARCHAR(30) DEFAULT '')");
        db()->exec("CREATE TABLE IF NOT EXISTS sample_custody (
            id $pk, sample_id INT, at VARCHAR(30) DEFAULT '', action VARCHAR(40) DEFAULT '',
            actor VARCHAR(150) DEFAULT '', location VARCHAR(120) DEFAULT '', note VARCHAR(400) DEFAULT '')");
    } catch (Throwable $e) { /* never let a table add break boot */ }

    // Register the three editable master lists, filed under the Operations group
    // in the Masters screen. Shipped with sensible defaults; fully editable.
    if (function_exists('lk_ensure_type_map')) {
        try {
            lk_ensure_type_map('sample_type', 'Item / sample type', [
                'MATERIAL' => 'Material sample', 'COMPONENT' => 'Failed / returned component',
                'COUPON' => 'Weld / test coupon', 'PRODUCT' => 'Product unit', 'CONSUMABLE' => 'Consumable',
                'DOCUMENT' => 'Document / record', 'OTHER' => 'Other',
            ], 'Operations');
            lk_ensure_type_map('sample_condition', 'Condition on receipt', [
                'INTACT' => 'Good / intact', 'SEALED' => 'Sealed', 'DAMAGED' => 'Damaged',
                'PARTIAL' => 'Partial / incomplete', 'CONTAM' => 'Contaminated / degraded',
            ], 'Operations');
            lk_ensure_type_map('sample_storage', 'Storage location', [
                'RECEIVING' => 'Receiving store', 'LAB' => 'Laboratory cabinet', 'COLD' => 'Cold storage',
                'SECURE' => 'Secure cage', 'SITE' => 'Held at site', 'OWNER' => 'With the owner',
            ], 'Operations');
        } catch (Throwable $e) { /* masters are a convenience, not load-bearing */ }
    }
}

// Visible only when an accreditation pack is on (an item-handling register makes
// no sense for a trading company); manageable by receiving/lab/coordinator staff.
function sample_pack_on() { return !function_exists('accredited_pack_on') || accredited_pack_on(); }
function sample_can_view() {
    if (!sample_pack_on()) return false;
    return is_master() || (function_exists('can') && (can('mod.equipment.view') || can('mod.competence.view')));
}
function sample_can_manage() {
    if (!sample_pack_on()) return false;
    return is_master() || (function_exists('can') && can('mod.equipment.view'))
        || (function_exists('is_coordinator_level') && is_coordinator_level());
}

// The editable values of one of the master lists, [code => label].
function sample_opts($key) {
    $t = function_exists('lk_type') ? lk_type($key) : null;
    if (!$t) return [];
    $out = [];
    foreach (ops_all("SELECT code, label FROM lookup_values WHERE type_id=? AND active=1 ORDER BY sort_order, label", [$t['id']]) ?: [] as $r)
        $out[$r['code']] = $r['label'];
    return $out;
}
function sample_label($key, $code) { $o = sample_opts($key); return $o[$code] ?? ($code ?: '—'); }

function samples_all($filter = []) {
    samples_migrate();
    $where = '1=1'; $args = [];
    if (!empty($filter['open']))   { $where .= " AND status IN ('" . implode("','", SAMPLE_OPEN) . "')"; }
    if (!empty($filter['status'])) { $where .= " AND status=?"; $args[] = $filter['status']; }
    if (!empty($filter['partner_id'])) { $where .= " AND partner_id=?"; $args[] = (int)$filter['partner_id']; }
    if (!empty($filter['q'])) { $where .= " AND (item_code LIKE ? OR description LIKE ? OR maker_ref LIKE ?)";
        $like = '%' . $filter['q'] . '%'; array_push($args, $like, $like, $like); }
    return ops_all("SELECT * FROM sample_items WHERE $where ORDER BY id DESC", $args) ?: [];
}
function sample_get($id) { samples_migrate(); return ops_one("SELECT * FROM sample_items WHERE id=?", [(int)$id]); }
function sample_custody_all($id) {
    return ops_all("SELECT * FROM sample_custody WHERE sample_id=? ORDER BY id", [(int)$id]) ?: [];
}
function sample_counts() {
    samples_migrate();
    $open = (int)ops_val("SELECT COUNT(*) FROM sample_items WHERE status IN ('" . implode("','", SAMPLE_OPEN) . "')");
    return ['open' => $open];
}

// A running per-year code, SMP-YY-#### — unique, readable off a label.
function sample_next_code() {
    $yy = date('y');
    $n = (int)ops_val("SELECT COUNT(*) FROM sample_items WHERE item_code LIKE ?", ["SMP-$yy-%"]) + 1;
    do { $code = sprintf('SMP-%s-%04d', $yy, $n); $n++; }
    while (ops_val("SELECT COUNT(*) FROM sample_items WHERE item_code=?", [$code]) > 0);
    return $code;
}

// One custody-trail entry. Every receipt, move, status change and closure writes
// one, so the chain of who-held-what-when is never assembled after the fact.
function sample_event($sampleId, $action, $note = '', $location = '') {
    samples_migrate();
    db()->prepare("INSERT INTO sample_custody (sample_id, at, action, actor, location, note) VALUES (?,?,?,?,?,?)")
        ->execute([(int)$sampleId, date('c'), substr($action, 0, 40),
                   function_exists('current_user') ? user_name(current_user()) : 'system',
                   substr($location, 0, 120), substr($note, 0, 400)]);
    if (function_exists('idems_log'))
        try { idems_log('sample', (int)$sampleId, 'CUSTODY_' . $action, ['reason' => $note]); }
        catch (Throwable $e) {}
}

// Move an item through its lifecycle, recording custody and the closing details.
function sample_set_status($id, $status, $note = '') {
    $s = sample_get($id);
    if (!$s || !in_array($status, SAMPLE_STATUSES, true)) return 'That status is not valid.';
    $closing = in_array($status, ['RETURNED', 'DISPOSED'], true);
    if ($closing) {
        db()->prepare("UPDATE sample_items SET status=?, disposition=?, closed_on=?, closed_by=?, closed_note=?, updated_at=? WHERE id=?")
            ->execute([$status, $status === 'RETURNED' ? 'RETURN' : 'DISPOSE', date('Y-m-d'),
                       user_name(current_user()), substr($note, 0, 300), date('c'), (int)$id]);
    } else {
        db()->prepare("UPDATE sample_items SET status=?, updated_at=? WHERE id=?")
            ->execute([$status, date('c'), (int)$id]);
    }
    sample_event($id, $status, $note);
    return '';
}

// Insert or update from the form. Returns [id, error].
function sample_save($b, $id = 0) {
    samples_migrate();
    $desc = trim((string)($b['description'] ?? ''));
    if ($desc === '') return [0, 'A short description of the item is required.'];
    $cols = [
        'partner_id'     => ($b['partner_id'] ?? '') !== '' ? (int)$b['partner_id'] : null,
        'call_id'        => ($b['call_id'] ?? '') !== '' ? (int)$b['call_id'] : null,
        'job_id'         => ($b['job_id'] ?? '') !== '' ? (int)$b['job_id'] : null,
        'description'    => substr($desc, 0, 300),
        'item_type'      => substr(trim((string)($b['item_type'] ?? '')), 0, 40),
        'maker_ref'      => substr(trim((string)($b['maker_ref'] ?? '')), 0, 120),
        'quantity'       => substr(trim((string)($b['quantity'] ?? '')), 0, 40),
        'unit'           => substr(trim((string)($b['unit'] ?? '')), 0, 20),
        'received_on'    => substr(trim((string)($b['received_on'] ?? '')), 0, 20) ?: date('Y-m-d'),
        'received_by'    => substr(trim((string)($b['received_by'] ?? '')), 0, 150),
        'condition_code' => substr(trim((string)($b['condition_code'] ?? '')), 0, 40),
        'condition_note' => substr(trim((string)($b['condition_note'] ?? '')), 0, 300),
        'storage_code'   => substr(trim((string)($b['storage_code'] ?? '')), 0, 40),
        'office_id'      => ($b['office_id'] ?? '') !== '' ? (int)$b['office_id'] : null,
        'sbu'            => substr(trim((string)($b['sbu'] ?? '')), 0, 40),
        'notes'          => trim((string)($b['notes'] ?? '')),
    ];
    if ($id) {
        $set = implode(', ', array_map(fn($k) => "$k=?", array_keys($cols)));
        db()->prepare("UPDATE sample_items SET $set, updated_at=? WHERE id=?")
            ->execute(array_merge(array_values($cols), [date('c'), (int)$id]));
        if (function_exists('custom_save')) custom_save('sample', (int)$id, $b);
        return [(int)$id, ''];
    }
    $cols['item_code']  = sample_next_code();
    $cols['status']     = 'RECEIVED';
    $cols['created_by'] = user_name(current_user());
    $cols['created_at'] = date('c');
    $cols['updated_at'] = date('c');
    $keys = array_keys($cols);
    db()->prepare("INSERT INTO sample_items (" . implode(',', $keys) . ") VALUES (" . implode(',', array_fill(0, count($keys), '?')) . ")")
        ->execute(array_values($cols));
    $newId = (int)db()->lastInsertId();
    if (function_exists('custom_save')) custom_save('sample', $newId, $b);
    sample_event($newId, 'RECEIVED', $cols['condition_note'], sample_label('sample_storage', $cols['storage_code']));
    return [$newId, ''];
}

// ---- Routing -------------------------------------------------------------
function ops_samples($route, $method) {
    samples_migrate();
    ops_require(sample_can_view(), 'The item / sample register is not available to you.');
    $canEdit = sample_can_manage();

    if ($route === 'samples') {
        $filter = ['q' => trim((string)($_GET['q'] ?? ''))];
        if (($_GET['show'] ?? 'open') !== 'all') $filter['open'] = 1;
        view('ops/samples_list', ['rows' => samples_all($filter), 'canEdit' => $canEdit,
            'showAll' => ($_GET['show'] ?? 'open') === 'all', 'q' => $filter['q'], 'counts' => sample_counts()]);
        return true;
    }

    if ($route === 'sample') {
        $s = sample_get($_GET['id'] ?? 0);
        if (!$s) { http_response_code(404); view('notfound'); return true; }
        view('ops/sample_detail', ['s' => $s, 'custody' => sample_custody_all($s['id']), 'canEdit' => $canEdit,
            'cvals' => function_exists('custom_display') ? custom_display('sample', $s['id']) : []]);
        return true;
    }

    // Everything past here changes something.
    ops_require($canEdit, 'You cannot change the item / sample register.');

    if ($route === 'sample-new' || $route === 'sample-edit') {
        $s = $route === 'sample-edit' ? sample_get($_GET['id'] ?? 0) : null;
        if ($route === 'sample-edit' && !$s) { http_response_code(404); view('notfound'); return true; }
        if ($method === 'POST') {
            [$id, $err] = sample_save($_POST, $s['id'] ?? 0);
            if ($err) { flash($err, 'error'); redirect($s ? '/sample-edit?id=' . $s['id'] : '/sample-new'); }
            flash($s ? 'Item updated.' : 'Item received and logged.');
            redirect('/sample?id=' . $id);
        }
        view('ops/sample_form', ['s' => $s,
            'types' => sample_opts('sample_type'), 'conditions' => sample_opts('sample_condition'),
            'storages' => sample_opts('sample_storage'),
            'cfields' => function_exists('custom_fields_for') ? custom_fields_for('sample') : [],
            'cvals' => $s && function_exists('custom_values_map') ? custom_values_map('sample', $s['id']) : []]);
        return true;
    }

    if ($route === 'sample-event' && $method === 'POST') {
        $s = sample_get($_POST['id'] ?? 0);
        if ($s) {
            $loc = trim((string)($_POST['location'] ?? ''));
            if ($loc !== '') db()->prepare("UPDATE sample_items SET storage_code=?, updated_at=? WHERE id=?")
                ->execute([substr($loc, 0, 40), date('c'), (int)$s['id']]);
            sample_event($s['id'], 'MOVED', (string)($_POST['note'] ?? ''), sample_label('sample_storage', $loc));
            flash('Custody note added.');
        }
        redirect('/sample?id=' . (int)($_POST['id'] ?? 0));
    }

    if ($route === 'sample-status' && $method === 'POST') {
        $err = sample_set_status((int)($_POST['id'] ?? 0), (string)($_POST['status'] ?? ''), (string)($_POST['note'] ?? ''));
        flash($err ?: 'Status updated.', $err ? 'error' : 'success');
        redirect('/sample?id=' . (int)($_POST['id'] ?? 0));
    }

    http_response_code(404); view('notfound'); return true;
}
