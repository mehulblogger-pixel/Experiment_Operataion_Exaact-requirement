<?php
// Module 47 — Mobile / PWA. The app already ships a full PWA (manifest, service worker, offline
// queue, camera capture, GPS). But the offline draft-autosave (offline.js, keyed on
// form[data-autosave]) was wired to exactly ONE form — report fill — so every other phone-first
// inspector form (site check-in, voucher entry, evidence) lost typed data on a flaky field
// connection. Extend the SAME existing mechanism to those forms. One attribute each; no route,
// behaviour or JS change; the offline infra already handles it.
t_section('Module 47 — offline draft-protect the remaining field-inspector forms');

$offline  = file_get_contents(__DIR__ . '/../assets/js/offline.js');
$checkin  = file_get_contents(__DIR__ . '/../views/ops/job_detail.php');
$evidence = file_get_contents(__DIR__ . '/../views/ops/idems/evidence.php');
$voucher  = file_get_contents(__DIR__ . '/../views/ops/voucher_detail.php');
$fill     = file_get_contents(__DIR__ . '/../views/ops/idems/fill.php');

// The infrastructure this rides is present and unchanged.
t_ok(strpos($offline, "form[data-autosave]") !== false, 'offline.js still wires every form[data-autosave]');
t_ok(strpos($offline, "el.type === 'hidden'") !== false && strpos($offline, "el.type === 'file'") !== false,
    'the autosave snapshot skips hidden and file fields (GPS/photos are never persisted)');
t_ok(strpos($fill, 'data-autosave=') !== false, 'the original report-fill autosave is preserved');

// Each field-inspector form now opts in, with a per-record key so drafts never collide.
t_ok(strpos($checkin,  'data-autosave="checkin-<?= (int)$job[\'id\'] ?>"') !== false,
    'the site check-in form is draft-protected, keyed per job');
t_ok(strpos($evidence, 'data-autosave="evidence-<?= (int)$doc[\'id\'] ?>"') !== false,
    'the evidence form is draft-protected, keyed per report');
t_ok(strpos($voucher,  'data-autosave="voucher-<?= (int)$v[\'id\'] ?>"') !== false,
    'the voucher edit form is draft-protected, keyed per voucher');

// The forms still carry their live-upload/GPS paths (draft-protection did not replace them).
t_ok(strpos($checkin, 'capture="environment"') !== false, 'the check-in camera capture is intact');
t_ok(strpos($checkin, 'id="ciForm"') !== false, 'the check-in form and its GPS submit flow are unchanged');
t_ok(strpos($voucher, 'id="vform"') !== false, 'the voucher save form id is unchanged');

// The evidence document input is deliberately NOT forced to camera-only (it must still accept a
// PDF mill/calibration certificate) — so no accept/capture was added there.
t_ok(strpos($evidence, 'name="doc[]" multiple required>') !== false,
    'the evidence document input still accepts any document (PDF certs), not camera-only');
