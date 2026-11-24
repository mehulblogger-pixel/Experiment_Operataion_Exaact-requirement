<?php
// Stage 1 · B4 (owner, 24 Nov 2026 — "approve B0–B5, all as recommended"): one
// resource classification; external people are never "free"; a sub-contractor firm
// shows on the registers; declined / released work returns to "No engineer yet" and
// the person shows free. Existing statuses only.
t_section('Stage 1 B4 — one resource classification');

t_eq(res_class(['staff_kind' => 'ASSET', 'roll_type' => 'OWN', 'team_role' => 'FIELD'])['group'], 'INHOUSE', 'own-roll staff are in-house');
t_eq(res_class(['staff_kind' => 'ASSET', 'roll_type' => 'AGENCY'])['group'], 'AGENCY_PAYROLL', 'agency-payroll staff are their own group');
t_ok(res_class(['staff_kind' => 'ASSET', 'roll_type' => 'AGENCY'])['company'], '… and still company-controlled');
t_eq(res_class(['staff_kind' => 'SUBCON'])['group'], 'EXTERNAL', 'a sub-contractor is external');
t_eq(res_class(['staff_kind' => 'FREELANCER'])['group'], 'EXTERNAL', 'a freelancer is external');
t_ok(res_class(['team_role' => 'COORD'])['resource'] && !res_class(['team_role' => 'OFFICE'])['resource'], 'resources = field team + coordinators, not office staff');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $today = date('Y-m-d');
    db()->prepare("INSERT INTO offices (code, name) VALUES (?,?)")->execute(['S1B4', 'Stage1 B4 office']);
    $off = (int)db()->lastInsertId();
    $person = function ($name, $kind, $role, $roll = 'OWN') use ($off) {
        db()->prepare("INSERT INTO inspectors (name, staff_kind, team_role, roll_type, home_office_id, status) VALUES (?,?,?,?,?, 'ACTIVE')")
            ->execute([$name, $kind, $role, $roll, $off]);
        return (int)db()->lastInsertId();
    };
    $field = $person('B4 Field', 'ASSET', 'FIELD');
    $coord = $person('B4 Coord', 'ASSET', 'COORD');
    $office = $person('B4 Office', 'ASSET', 'OFFICE');
    $agencyPay = $person('B4 AgencyPay', 'ASSET', 'FIELD', 'AGENCY');
    $free = $person('B4 Freelancer', 'FREELANCER', 'FIELD');
    $declined = $person('B4 Declined', 'ASSET', 'FIELD');

    $ids = array_map(fn($r) => (int)$r['id'], inspector_availability([$off], $today));
    t_ok(in_array($field, $ids, true) && in_array($coord, $ids, true) && in_array($agencyPay, $ids, true), 'availability lists field, coordinator and agency-payroll people');
    t_ok(!in_array($office, $ids, true), 'availability leaves out office staff (not a field resource)');
    t_ok(!in_array($free, $ids, true), 'an external freelancer is never shown as free capacity');
    t_ok(in_array($free, array_map(fn($r) => (int)$r['id'], availability_external([$off])), true), '… they are listed in the "External — confirm with agency" group');

    // Declined work: the person shows free, the order goes back to "No engineer yet".
    db()->prepare("INSERT INTO calls (call_code, executing_office_id, ibo_office_id, status, created_at) VALUES (?,?,?,?,?)")->execute(['S4-C', $off, $off, 'OPEN', 'x']);
    $cid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, inspector_id, scheduled_date, closed_flag, accept_state, created_at) VALUES (?,?,?,?,?,0,'DECLINED',?)")
        ->execute(['S4-JD', $cid, $off, $declined, $today, 'x']);
    $jid = (int)db()->lastInsertId();
    $av = []; foreach (inspector_availability([$off], $today) as $r) $av[(int)$r['id']] = $r['eff_status'];
    t_eq($av[$declined] ?? '', 'AVAILABLE', 'a person whose assignment was declined shows free that day (was ON JOB)');
    t_eq(inspector_busy_on($declined, $today), '', 'the allocate check does not call them busy');
    t_eq((int) ops_val("SELECT COUNT(*) FROM calls c WHERE c.id=? AND " . opk_call_pred('no_engineer'), [$cid]), 1, 'the work order is back in "No engineer yet"');
    db()->prepare("UPDATE jobs SET accept_state='ACCEPTED', assign_state='RELEASED' WHERE id=?")->execute([$jid]);
    $av = []; foreach (inspector_availability([$off], $today) as $r) $av[(int)$r['id']] = $r['eff_status'];
    t_eq($av[$declined] ?? '', 'AVAILABLE', 'a released hold also frees the person');
    db()->prepare("UPDATE jobs SET assign_state='CONFIRMED' WHERE id=?")->execute([$jid]);
    $av = []; foreach (inspector_availability([$off], $today) as $r) $av[(int)$r['id']] = $r['eff_status'];
    t_eq($av[$declined] ?? '', 'ON_JOB', 'a confirmed, accepted job still makes them busy');

    // Sub-contractor firm on the registers.
    db()->prepare("INSERT INTO subcons (agency, active) VALUES (?,1)")->execute(['Acme Inspection Services']);
    $sc = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, subcon_id, scheduled_date, closed_flag, created_at) VALUES (?,?,?,?,?,0,?)")
        ->execute(['S4-JS', $cid, $off, $sc, $today, 'x']);
    $reg = array_values(array_filter(tosrm_assignment_register([$off], 50), fn($r) => $r['job_code'] === 'S4-JS'));
    t_ok($reg && tosrm_resource_label($reg[0]) === 'Acme Inspection Services · External', 'the assignment register names the sub-contractor firm, tagged External');
    $sch = array_values(array_filter(tosrm_schedule_register([$off], $today, $today), fn($r) => $r['job_code'] === 'S4-JS'));
    t_ok($sch && strpos(tosrm_resource_label($sch[0]), 'Acme') === 0, 'the schedule register names it too (was blank)');
    t_eq(tosrm_accept_label('DECLINED'), 'Declined', 'acceptance states read by their labels, not codes');
    t_eq(tosrm_assign_label('RELEASED'), 'Released', 'hold states read by their labels');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
