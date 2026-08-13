<?php
// ===========================================================================
//  ATTEND — self-service daily attendance (check-in / check-out) from the top
//  of the dashboard. ADDITIVE over the EXISTING attendance table.
//    • Two buttons: MARK IN (arrival) and MARK OUT (departure) — a pair, like a
//      site check-in/out; each stamps the SERVER time and a browser GPS fix.
//    • A status ("In office" / "On site" / WFH / On leave / Travel / Training /
//      Half-day). In office AND On site REQUIRE a location — enforced client-
//      AND server-side. WFH / leave / etc. need none.
//    • The mark flows into inspector_day_status, so the availability board and
//      dashboards show it (on leave / in office / …) automatically.
//    • It auto-links to that date's report by inspector + date (no new store).
//  Reuses: `attendance` table, `inspector_day_status`, the geolocation pattern.
// ===========================================================================

const ATTEND_SELF = [
    'OFFICE'   => 'In office',
    'SITE'     => 'On site',
    'WFH'      => 'Work from home',
    'LEAVE'    => 'On leave',
    'TRAVEL'   => 'On travel',
    'TRAINING' => 'Training',
    'HALF_DAY' => 'Half day',
];
const ATTEND_LOC_REQUIRED = ['OFFICE', 'SITE'];   // must carry a location

function attend_migrate() {
    static $done = false; if ($done) return; $done = true;
    if (function_exists('lk_ensure_type_map')) lk_ensure_type_map('attendance_self', 'Self-marked attendance', ATTEND_SELF, 'attend');
    if (function_exists('ensure_column')) {
        // Arrival + departure are two facts; a location and time for each.
        ensure_column('attendance', 'check_in_at',  "VARCHAR(30) DEFAULT ''");
        ensure_column('attendance', 'in_lat',       'DECIMAL(10,7) NULL');
        ensure_column('attendance', 'in_lon',       'DECIMAL(10,7) NULL');
        ensure_column('attendance', 'in_acc',       'DECIMAL(10,2) NULL');
        ensure_column('attendance', 'check_out_at', "VARCHAR(30) DEFAULT ''");
        ensure_column('attendance', 'out_lat',      'DECIMAL(10,7) NULL');
        ensure_column('attendance', 'out_lon',      'DECIMAL(10,7) NULL');
        ensure_column('attendance', 'out_acc',      'DECIMAL(10,2) NULL');
        ensure_column('attendance', 'marked_at',    "VARCHAR(30) DEFAULT ''");
        ensure_column('attendance', 'source',       "VARCHAR(16) DEFAULT ''");
        ensure_column('attendance', 'marked_by',    "VARCHAR(120) DEFAULT ''");
    }
    db()->exec("CREATE TABLE IF NOT EXISTS inspector_day_status (
        id " . pk_clause() . ", inspector_id INT, day VARCHAR(20), status VARCHAR(20) DEFAULT '',
        note VARCHAR(255) DEFAULT '', set_by VARCHAR(120) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
}

function attend_status_options() { return function_exists('lk_options_or') ? lk_options_or('attendance_self', ATTEND_SELF) : ATTEND_SELF; }
function attend_loc_required($status) { return in_array(strtoupper((string)$status), ATTEND_LOC_REQUIRED, true); }
function attend_actor() { return (function_exists('user_name') && function_exists('current_user')) ? (user_name(current_user()) ?: 'system') : 'system'; }
function attend_avail_map($status) {
    $map = ['OFFICE'=>'OFFICE','SITE'=>'ON_JOB','WFH'=>'WFH','LEAVE'=>'LEAVE','TRAVEL'=>'TRAVEL','TRAINING'=>'TRAINING','HALF_DAY'=>'HALF_DAY'];
    return $map[strtoupper((string)$status)] ?? 'OFFICE';
}
function attend_today($inspectorId, $date = null) {
    attend_migrate();
    $date = $date ?: date('Y-m-d');
    return ops_one("SELECT * FROM attendance WHERE inspector_id=? AND att_date=? AND source='SELF' ORDER BY id DESC LIMIT 1", [(int)$inspectorId, $date]) ?: null;
}
function attend_for_report($inspectorId, $date) { return attend_today($inspectorId, substr((string)$date, 0, 10)); }
function attend_user_inspector($u = null) {
    $u = $u ?: (function_exists('current_user') ? current_user() : null);
    return (int)($u['inspector_id'] ?? 0);
}

// The one row for an inspector's day (self-marked), created on first punch.
function attend_row($inspectorId, $date) {
    $r = ops_one("SELECT id FROM attendance WHERE inspector_id=? AND att_date=? AND source='SELF' LIMIT 1", [(int)$inspectorId, $date]);
    if ($r) return (int)$r['id'];
    db()->prepare("INSERT INTO attendance (att_date, inspector_id, status, source, marked_by, created_at) VALUES (?,?,?,'SELF',?,?)")
        ->execute([$date, (int)$inspectorId, 'OFFICE', attend_actor(), date('c')]);
    return (int)db()->lastInsertId();
}

// A check-in (arrival) or check-out (departure). Location is mandatory for
// In-office / On-site; refused server-side without it.
function attend_punch($inspectorId, $date, $kind, $status, $data = []) {
    attend_migrate();
    $inspectorId = (int)$inspectorId; if ($inspectorId <= 0) return ['ok'=>false, 'error'=>'No inspector record for this user.'];
    $date = substr((string)$date, 0, 10) ?: date('Y-m-d');
    $kind = strtoupper(trim((string)$kind)); if (!in_array($kind, ['IN','OUT'], true)) return ['ok'=>false, 'error'=>'Choose Mark in or Mark out.'];
    $lat = trim((string)($data['lat'] ?? '')); $lon = trim((string)($data['lon'] ?? '')); $acc = ($data['accuracy'] ?? '') !== '' ? (float)$data['accuracy'] : null;
    $now = date('c'); $actor = attend_actor();

    if ($kind === 'IN') {
        $status = strtoupper(trim((string)$status));
        if (!array_key_exists($status, attend_status_options())) return ['ok'=>false, 'error'=>'Pick a valid attendance status.'];
        if (attend_loc_required($status) && ($lat === '' || $lon === ''))
            return ['ok'=>false, 'error'=>'A location is required to mark IN as "' . (attend_status_options()[$status] ?? $status) . '". Allow location access and try again.'];
        $id = attend_row($inspectorId, $date);
        db()->prepare("UPDATE attendance SET status=?, check_in_at=?, in_lat=?, in_lon=?, in_acc=?, marked_at=?, marked_by=? WHERE id=?")
            ->execute([$status, $now, $lat !== '' ? $lat : null, $lon !== '' ? $lon : null, $acc, $now, $actor, $id]);
        // Mirror onto the availability board.
        $avail = attend_avail_map($status);
        db()->prepare("DELETE FROM inspector_day_status WHERE inspector_id=? AND day=?")->execute([$inspectorId, $date]);
        db()->prepare("INSERT INTO inspector_day_status (inspector_id, day, status, note, set_by, created_at) VALUES (?,?,?,?,?,?)")
            ->execute([$inspectorId, $date, $avail, 'Self-marked attendance', $actor, $now]);
        if (function_exists('idems_log')) { try { idems_log('attendance', $id, 'CHECK_IN', ['new'=>$status]); } catch (Throwable $e) {} }
        return ['ok'=>true, 'id'=>$id, 'kind'=>'IN', 'status'=>$status];
    }
    // OUT — must have checked in; location required if the day was office/site.
    $row = attend_today($inspectorId, $date);
    if (!$row || trim((string)$row['check_in_at']) === '') return ['ok'=>false, 'error'=>'Mark IN first, then Mark OUT when you leave.'];
    if (attend_loc_required($row['status']) && ($lat === '' || $lon === ''))
        return ['ok'=>false, 'error'=>'A location is required to mark OUT for an on-location day. Allow location access and try again.'];
    db()->prepare("UPDATE attendance SET check_out_at=?, out_lat=?, out_lon=?, out_acc=?, marked_at=?, marked_by=? WHERE id=?")
        ->execute([$now, $lat !== '' ? $lat : null, $lon !== '' ? $lon : null, $acc, $now, $actor, (int)$row['id']]);
    if (function_exists('idems_log')) { try { idems_log('attendance', (int)$row['id'], 'CHECK_OUT', []); } catch (Throwable $e) {} }
    return ['ok'=>true, 'id'=>(int)$row['id'], 'kind'=>'OUT'];
}

// ---------------------------------------------------------------------------
//  Dashboard widget — top of the page: Mark IN / Mark OUT + status.
// ---------------------------------------------------------------------------
function attend_render_widget($u = null) {
    $u = $u ?: (function_exists('current_user') ? current_user() : null);
    $inspId = attend_user_inspector($u);
    if ($inspId <= 0) return;
    attend_migrate();
    $esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $today = date('Y-m-d');
    $row = attend_today($inspId, $today);
    $opts = attend_status_options();
    $csrf = function_exists('csrf_token') ? csrf_token() : '';
    $inDone  = $row && trim((string)$row['check_in_at']) !== '';
    $outDone = $row && trim((string)$row['check_out_at']) !== '';
    $curStatus = $row['status'] ?? 'OFFICE';
    $job = ops_one("SELECT id, job_code FROM jobs WHERE inspector_id=? AND scheduled_date=? ORDER BY id DESC LIMIT 1", [$inspId, $today]);
    $tm = fn($v) => $v ? date('H:i', strtotime($v)) : '—';
    ob_start(); ?>
    <section class="card attend-card" style="margin:0 0 16px">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
        <h2 style="margin:0">Attendance <span class="muted" style="font-weight:400;font-size:13px">· <?=$esc(date('l, d M Y'))?></span></h2>
        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
          <span class="badge <?= $inDone ? 'GREEN' : 'AMBER' ?>">IN <?=$esc($inDone ? $tm($row['check_in_at']) : '—')?></span>
          <span class="badge <?= $outDone ? 'GREEN' : 'AMBER' ?>">OUT <?=$esc($outDone ? $tm($row['check_out_at']) : '—')?></span>
          <?php if ($inDone): ?><span class="badge"><?=$esc($opts[$curStatus] ?? $curStatus)?></span><?php endif; ?>
        </div>
      </div>

      <form method="post" action="/attend-mark" id="attForm" style="margin-top:12px">
        <input type="hidden" name="_csrf" value="<?=$esc($csrf)?>">
        <input type="hidden" name="kind" id="attKind"><input type="hidden" name="status" id="attStatus" value="<?=$esc($curStatus)?>">
        <input type="hidden" name="lat" id="attLat"><input type="hidden" name="lon" id="attLon"><input type="hidden" name="accuracy" id="attAcc">
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
          <button type="button" class="btn" style="font-size:16px;padding:10px 22px" onclick="attPunch('IN')"<?= $inDone ? ' disabled' : '' ?>>📍 Mark IN</button>
          <button type="button" class="btn secondary" style="font-size:16px;padding:10px 22px" onclick="attPunch('OUT')"<?= (!$inDone || $outDone) ? ' disabled' : '' ?>>📍 Mark OUT</button>
          <label class="muted" style="font-size:13px">I am:
            <select id="attSel" onchange="document.getElementById('attStatus').value=this.value"<?= $inDone ? ' disabled' : '' ?> style="margin-left:4px">
              <?php foreach ($opts as $k=>$v): ?><option value="<?=$esc($k)?>" <?=$k===$curStatus?'selected':''?>><?=$esc($v)?><?= attend_loc_required($k) ? ' 📍' : '' ?></option><?php endforeach; ?>
            </select>
          </label>
        </div>
        <p class="muted" style="margin:8px 2px 0;font-size:12px">📍 In office / On site require your location. Time is stamped by the server; the mark shows on your availability<?= $job ? ' and links to today’s '.e(Tl('job')).' <a href="/job?id='.(int)$job['id'].'">'.$esc($job['job_code']).'</a>' : '' ?>.</p>
        <p id="attMsg" style="margin:4px 2px 0;font-size:12px;color:#b4232b"></p>
      </form>
    </section>
    <script>
    function attPunch(kind){
      var f=document.getElementById('attForm'), msg=document.getElementById('attMsg');
      document.getElementById('attKind').value=kind; msg.textContent='';
      var status=document.getElementById('attStatus').value||'OFFICE';
      var loc=(kind==='IN') ? (status==='OFFICE'||status==='SITE')
                            : <?= $inDone && attend_loc_required($curStatus) ? 'true' : 'false' ?>;
      if(!loc){ f.submit(); return; }
      if(!navigator.geolocation){ msg.style.color='#b4232b'; msg.textContent='This device cannot provide a location, which is required here.'; return; }
      msg.style.color='#6b7480'; msg.textContent='Getting your location…';
      navigator.geolocation.getCurrentPosition(function(p){
        document.getElementById('attLat').value=p.coords.latitude.toFixed(7);
        document.getElementById('attLon').value=p.coords.longitude.toFixed(7);
        document.getElementById('attAcc').value=Math.round(p.coords.accuracy||0);
        f.submit();
      }, function(){ msg.style.color='#b4232b'; msg.textContent='Could not get your location. Allow location access and try again — it is required.'; },
      {enableHighAccuracy:true, timeout:12000, maximumAge:0});
    }
    </script>
    <?php
    echo ob_get_clean();
}

// ---------------------------------------------------------------------------
//  Handler.
// ---------------------------------------------------------------------------
function ops_attend_action($route, $method) {
    $back = '/';
    if ($method !== 'POST') redirect($back);
    if (!csrf_ok($_POST['_csrf'] ?? '')) { flash('That form had expired — please try again.', 'error'); redirect($back); }
    $inspId = attend_user_inspector();
    if ($inspId <= 0) { flash('Your login is not linked to an inspector record, so attendance cannot be self-marked.', 'error'); redirect($back); }
    $res = attend_punch($inspId, date('Y-m-d'), (string)($_POST['kind'] ?? ''), (string)($_POST['status'] ?? 'OFFICE'), [
        'lat'=>$_POST['lat'] ?? '', 'lon'=>$_POST['lon'] ?? '', 'accuracy'=>$_POST['accuracy'] ?? '',
    ]);
    flash($res['ok'] ? (($res['kind'] ?? '') === 'OUT' ? 'Checked out. Have a good evening.' : 'Checked in. Attendance marked.') : $res['error'], $res['ok'] ? 'success' : 'error');
    redirect($back);
}
