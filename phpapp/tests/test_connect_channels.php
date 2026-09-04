<?php
// Connect K16 / backlog #5 — WhatsApp / SMS / email channel. Asserts consent-gated
// sending, template rendering, the honest delivery modes (off → recorded not sent;
// log → simulated; live → approved-template + provider), contact masking, and the
// new-message nudge that fires when the desk writes to an opted-in professional.
t_section('connect channels — WhatsApp/SMS/email (#5)');

connect_channels_seed();

// Templates seeded + render.
$t = connect_channel_template('new_message', 'whatsapp');
t_ok($t && stripos((string)$t['body'], '{job}') !== false, 'a WhatsApp template is seeded with placeholders');
$rendered = connect_channel_render($t['body'], ['name' => 'Asha', 'job' => 'Welding FAT', 'link' => '/x']);
t_ok(strpos($rendered, 'Asha') !== false && strpos($rendered, '{job}') === false, 'a template renders placeholders and drops unfilled ones');
t_eq('••••••3210', connect_channel_mask_contact('98765 43210'), 'a mobile is masked to its last 4');
t_ok(strpos(connect_channel_mask_contact('asha.k@gmail.com'), '@gmail.com') !== false
     && strpos(connect_channel_mask_contact('asha.k@gmail.com'), 'asha.k') === false, 'an email local part is masked');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_pro_register(['name' => 'Channel Chandni', 'email' => 'chandni@example.com', 'password' => 'secret12']);
    $pid = connect_pro_id();
    db()->prepare("UPDATE cx_professionals SET mobile='9876543210' WHERE id=?")->execute([$pid]);

    // Email is on by default; WhatsApp/SMS are consent-first (off).
    $def = connect_channel_prefs($pid);
    t_ok($def['email'] && !$def['whatsapp'] && !$def['sms'], 'by default email is on, WhatsApp/SMS are off (consent-first)');

    // With every channel opted OUT → nothing is recorded (the consent gate holds).
    connect_channel_set_consent($pid, ['notify_whatsapp' => 0, 'notify_sms' => 0, 'notify_email' => 0]);
    $r0 = connect_channel_notify($pid, 'new_message', ['job' => 'Piping']);
    t_eq(0, count($r0['rows']), 'with every channel opted out, no channel message is created');

    // Opt into WhatsApp + SMS (not email).
    connect_channel_set_consent($pid, ['notify_whatsapp' => 1, 'notify_sms' => 1, 'notify_email' => 0]);
    $prefs = connect_channel_prefs($pid);
    t_ok($prefs['whatsapp'] && $prefs['sms'] && !$prefs['email'], 'consent is stored per channel');

    // Mode OFF (default) → messages are QUEUED (recorded), never faked as sent.
    setting_set('connect_channels_mode', 'off');
    $r1 = connect_channel_notify($pid, 'new_message', ['job' => 'Piping inspection', 'ref' => 'CX-1']);
    t_eq(2, count($r1['rows']), 'both opted-in channels record a message (WhatsApp + SMS)');
    t_eq(2, $r1['queued'], 'in OFF mode both are QUEUED, not sent');
    t_eq(0, $r1['sent'], 'nothing is marked sent without a provider');
    $wa = ops_one("SELECT * FROM cx_channel_messages WHERE pro_id=? AND channel='whatsapp' ORDER BY id DESC", [$pid]);
    t_ok(strpos((string)$wa['body'], 'Piping inspection') !== false, 'the queued WhatsApp body is rendered from the template');
    t_ok(strpos((string)$wa['to_masked'], '3210') !== false && strpos((string)$wa['to_masked'], '98765') === false, 'only a masked mobile is stored');

    // Mode LOG → simulated delivery (LOGGED, honestly labelled).
    setting_set('connect_channels_mode', 'log');
    $r2 = connect_channel_notify($pid, 'shortlisted', ['job' => 'NDT job', 'ref' => 'CX-2']);
    t_eq(2, $r2['logged'], 'in LOG mode messages are marked logged (simulated)');

    // Mode LIVE with no provider + unapproved template → SKIPPED/FAILED, never SENT.
    setting_set('connect_channels_mode', 'live');
    $r3 = connect_channel_notify($pid, 'awarded', ['job' => 'FAT', 'ref' => 'CX-3']);
    t_eq(0, $r3['sent'], 'LIVE mode sends nothing while templates are unapproved / no provider is connected');
    t_ok($r3['skipped'] >= 1, 'an unapproved template is skipped in LIVE mode (compliance gate)');

    setting_set('connect_channels_mode', 'off');

    // The new-message nudge fires when the DESK messages an opted-in professional.
    $rid = cx_requirement_create(['title' => 'Shutdown welder', 'discipline_code' => 'WELD', 'poster_name' => 'Acme'], true);
    $aid = cx_application_add($rid, ['applicant_professional_id' => $pid, 'applicant_name' => 'Channel Chandni']);
    $before = (int)ops_val("SELECT COUNT(*) FROM cx_channel_messages WHERE pro_id=?", [$pid]);
    connect_msg_post($aid, 'staff', 5, 'Coordinator', 'Are you available next week?');
    $after = (int)ops_val("SELECT COUNT(*) FROM cx_channel_messages WHERE pro_id=?", [$pid]);
    t_ok($after > $before, 'a desk message nudges the professional on their opted-in channels');

    // A professional's OWN message does NOT nudge themselves.
    $before2 = (int)ops_val("SELECT COUNT(*) FROM cx_channel_messages WHERE pro_id=?", [$pid]);
    connect_msg_post($aid, 'professional', $pid, 'Channel Chandni', 'Yes, I am available.');
    $after2 = (int)ops_val("SELECT COUNT(*) FROM cx_channel_messages WHERE pro_id=?", [$pid]);
    t_eq($before2, $after2, 'a professional replying does not trigger a channel nudge to themselves');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
    if (function_exists('setting_set')) setting_set('connect_channels_mode', 'off');
}
