<?php
// Connect K15 / backlog #4 — in-app messaging. Asserts a two-way thread per
// engagement: staff and the professional exchange messages, unread counts track
// per reader (never counting your own), read-cursors clear on open, access is
// scoped to the professional's own engagements, and the thread is retained as a
// record.
t_section('connect in-app messaging (#4)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    // A professional applies to a requirement → an engagement (application).
    connect_pro_register(['name' => 'Msg Meera', 'email' => 'meera@example.com', 'password' => 'secret12']);
    $pid = connect_pro_id();
    $rid = cx_requirement_create(['title' => 'Piping inspector for shutdown', 'discipline_code' => 'PIPE',
        'description' => 'Shutdown piping', 'poster_name' => 'Acme Refinery'], true);
    $aid = cx_application_add($rid, ['applicant_professional_id' => $pid, 'applicant_name' => 'Msg Meera']);
    t_ok($aid > 0, 'an engagement (application) exists to hang a thread on');

    // Empty and oversize guards.
    [$bad] = connect_msg_post($aid, 'staff', 7, 'Coordinator', '   ');
    t_ok(!$bad, 'an empty message is rejected');
    [$noApp] = connect_msg_post(999999, 'staff', 7, 'Coordinator', 'hi');
    t_ok(!$noApp, 'posting to a non-existent engagement is rejected');

    // Staff opens the conversation.
    [$s1ok, , $m1] = connect_msg_post($aid, 'staff', 7, 'Coordinator Ravi', 'Hi Meera, can you start Monday?');
    t_ok($s1ok && $m1 > 0, 'staff can post the first message');

    // The professional has 1 unread (not counting anything of their own).
    t_eq(1, connect_msg_thread_unread($aid, 'professional', $pid), 'the professional sees 1 unread after the staff message');
    t_eq(1, connect_msg_pro_unread($pid), 'the pro-wide unread badge is 1');
    // Staff does NOT see their own message as unread.
    t_eq(0, connect_msg_thread_unread($aid, 'staff', 7), 'staff never counts their own message as unread');

    // Professional opens (mark read) and replies.
    connect_msg_mark_read($aid, 'professional', $pid);
    t_eq(0, connect_msg_thread_unread($aid, 'professional', $pid), 'opening the thread clears the professional\'s unread');
    [$p1ok, , $m2] = connect_msg_post($aid, 'professional', $pid, 'Msg Meera', 'Yes — Monday works. I have my own PPE.');
    t_ok($p1ok && $m2 > $m1, 'the professional can reply');

    // Now staff has 1 unread; the professional has 0 (their own reply).
    t_eq(1, connect_msg_thread_unread($aid, 'staff', 7), 'staff now has 1 unread (the professional\'s reply)');
    t_eq(0, connect_msg_thread_unread($aid, 'professional', $pid), 'the professional has no unread after their own reply');

    // The thread is the full two-way record, in order.
    $thread = connect_msg_thread($aid);
    t_eq(2, count($thread), 'the thread retains both messages');
    t_eq('staff', $thread[0]['sender_kind'], 'the first message is the staff opener');
    t_eq('professional', $thread[1]['sender_kind'], 'the second is the professional reply');
    t_ok(strpos((string)$thread[1]['body'], 'PPE') !== false, 'the reply body is stored verbatim');

    // Access scoping — the thread belongs to this professional, not another.
    t_ok(connect_msg_pro_owns($aid, $pid), 'the engagement belongs to its professional');
    connect_pro_register(['name' => 'Other Om', 'email' => 'om@example.com', 'password' => 'secret12']);
    $pid2 = connect_pro_id();
    t_ok(!connect_msg_pro_owns($aid, $pid2), 'another professional does NOT own this thread');
    t_ok(!in_array($aid, connect_msg_professional_apps($pid2), true), 'the thread is not in another professional\'s inbox');

    // Inbox summaries carry the last message + unread for the reader.
    $sum = connect_msg_summaries(connect_msg_professional_apps($pid), 'professional', $pid);
    t_ok(count($sum) >= 1, 'the professional\'s inbox lists the engagement');
    t_eq($aid, (int)$sum[0]['application_id'], 'the inbox row points at the right engagement');
    t_ok($sum[0]['last'] !== null, 'the inbox row carries the last message');

    // Staff inbox unread total reflects the open thread.
    t_ok(connect_msg_unread_total('staff', 7, [$aid]) >= 1, 'the staff unread total counts the pending reply');

    // --- anti-circumvention: contact details are redacted before award --------
    $leaky = 'Call me on +91 98765 43210 or email me at rakesh.welder@gmail.com to settle directly.';
    $masked = connect_msg_redact($leaky);
    t_ok(strpos($masked, '98765') === false, 'a phone number is masked by the redactor');
    t_ok(strpos($masked, '@gmail.com') === false, 'an email is masked by the redactor');

    // Before award: the professional sees the masked body; staff sees the raw text.
    connect_msg_post($aid, 'staff', 7, 'Coordinator Ravi', $leaky);
    t_ok(!connect_msg_contacts_revealed($aid), 'contacts are NOT revealed before award');
    $proSees = connect_msg_display_body($leaky, $aid, 'professional');
    t_ok(strpos($proSees, '98765') === false && strpos($proSees, '@gmail.com') === false, 'the applicant sees contact details masked pre-award');
    $staffSees = connect_msg_display_body($leaky, $aid, 'staff');
    t_ok(strpos($staffSees, '98765') !== false, 'staff (us) still see the raw text, for moderation & evidence');

    // After the engagement is awarded to this applicant, contacts unlock.
    db()->prepare("UPDATE cx_requirements SET status='AWARDED', awarded_application_id=? WHERE id=?")->execute([$aid, $rid]);
    t_ok(connect_msg_contacts_revealed($aid), 'contacts are revealed once the requirement is awarded to this applicant');
    $proSees2 = connect_msg_display_body($leaky, $aid, 'professional');
    t_ok(strpos($proSees2, '98765') !== false, 'the applicant sees full contact details after they are hired');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
