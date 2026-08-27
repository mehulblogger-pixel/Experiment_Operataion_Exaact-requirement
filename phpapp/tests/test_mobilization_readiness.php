<?php
// Slice P2 — Mobilization readiness: the person-centric "what is blocking this
// person from mobilizing?" composition over the deputation checklist, competence
// gate, site documents, credentials and assets. Read-only CONNECT: no table, no
// status, no permission. The allocation gate itself is unchanged (test_module24).
t_section('mobilization readiness (Slice P2)');

pdso_migrate();
competence_migrate();
if (function_exists('sitedoc_migrate')) sitedoc_migrate();
if (function_exists('assets_migrate'))  assets_migrate();

t_ok(mobilization_readiness(0) === null, 'a missing job returns null');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    db()->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES ('Mob Client','Mob Client',1,'ACTIVE')")->execute();
    $cid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO inspectors (name, status, created_at) VALUES ('Mob Ready','ACTIVE',?)")->execute([date('c')]);
    $iid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO calls (call_code, client_id, inspection_type, created_at) VALUES ('MOB-1',?,?,?)")->execute([$cid, 'DEPUTATION', date('c')]);
    $callId = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO jobs (job_code, call_id, inspector_id, job_type, dep_status, inspection_start_date) VALUES ('J-MOB-1',?,?,?,?,?)")
        ->execute([$callId, $iid, 'DEPUTATION', 'MOB_PENDING', '2026-09-15']);
    $jid = (int)db()->lastInsertId();

    // No checklist and nothing lapsed → ready, with a note that no checklist exists.
    $r0 = mobilization_readiness($jid);
    t_ok(is_array($r0) && array_key_exists('ready', $r0), 'readiness returns a verdict');
    t_eq($r0['ready'], true, 'with nothing blocking, the posting is ready');
    t_ok((bool)array_filter($r0['warnings'], fn($w) => $w['source'] === 'Checklist'), 'a missing checklist is surfaced as a note, not a block');

    // Seed the checklist → required items are open → blocked.
    pdso_checklist_seed($jid, 'MOB');
    $r1 = mobilization_readiness($jid);
    t_eq($r1['ready'], false, 'required checklist items open → blocked');
    t_ok((bool)array_filter($r1['blockers'], fn($b) => $b['source'] === 'Checklist'), 'the checklist blocker is named');

    // Complete every required checklist item → the checklist no longer blocks.
    foreach (pdso_checklist($jid, 'MOB') as $it) if ((int)$it['required'] === 1) pdso_checklist_set((int)$it['id'], 'COMPLETED');
    $r2 = mobilization_readiness($jid);
    t_ok(!array_filter($r2['blockers'], fn($b) => $b['source'] === 'Checklist'), 'completing required items clears the checklist blocker');

    // A lapsed MANDATORY certificate on the work date → competence blocker
    // (mirrors the real allocation gate, which this reads, not re-implements).
    db()->prepare("INSERT INTO inspector_certs (inspector_id,name,number,valid_to,is_mandatory,status) VALUES (?,?,?,?,1,'VALID')")
        ->execute([$iid, 'Safety Passport', 'SP-1', '2026-01-01']);
    $r3 = mobilization_readiness($jid);
    t_eq($r3['ready'], false, 'a lapsed required certificate blocks mobilization');
    t_ok((bool)array_filter($r3['blockers'], fn($b) => $b['source'] === 'Competence'), 'the competence blocker is named');

    // A rejected credential (Slice P1) is a blocker; an expiring one is a note.
    db()->prepare("INSERT INTO inspector_certs (inspector_id,name,number,valid_to,is_mandatory,status,verify_status) VALUES (?,?,?,?,0,'VALID','REJECTED')")
        ->execute([$iid, 'CSWIP', 'C-9', '2027-01-01']);
    $r3b = mobilization_readiness($jid);
    t_ok((bool)array_filter($r3b['blockers'], fn($b) => $b['source'] === 'Credential'), 'a rejected credential blocks mobilization');

    // Board badge + detail render.
    t_ok(is_string(mobilization_readiness_badge($jid)) && mobilization_readiness_badge($jid) !== '', 'the board badge renders');
    t_nothrow('the readiness panel renders without error', function () use ($jid) { ob_start(); mobilization_readiness_render($jid); ob_get_clean(); });
    ob_start(); mobilization_readiness_render($jid); $html = ob_get_clean();
    t_ok(strpos($html, 'Mobilization readiness') !== false, 'the panel shows its heading');

    // An unallocated posting still returns, noting the missing allocation.
    db()->prepare("INSERT INTO jobs (job_code, call_id, job_type, dep_status, inspection_start_date) VALUES ('J-MOB-2',?,?,?,?)")
        ->execute([$callId, 'DEPUTATION', 'PLANNED', '2026-09-15']);
    $jid2 = (int)db()->lastInsertId();
    $r4 = mobilization_readiness($jid2);
    t_ok(is_array($r4) && (bool)array_filter($r4['warnings'], fn($w) => $w['source'] === 'Allocation'),
        'an unallocated posting notes the missing allocation');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
