<?php
// DEF-MKT-016 (QA audit V51.1, P0) — stored cross-site scripting on the staff
// Messages desk. The inbox list printed the first 60 characters of each thread's
// last message WITHOUT escaping (the e() call closed before the body was joined
// on), so markup sent by a self-registered professional ran in the coordinator's
// browser when they opened /connect-messages. This renders the real inbox view
// with a hostile message as the last one in a thread and proves it comes out as
// text — in the inbox snippet and in the open conversation — never as markup.
t_section('connect messages: message text is escaped on the staff desk (DEF-MKT-016)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_pro_register(['name' => 'Esc Pro', 'email' => 'esc.pro@example.com', 'password' => 'secret12']);
    $pid = connect_pro_id();
    $rid = cx_requirement_create(['title' => 'Escaping check', 'discipline_code' => 'PIPE',
        'description' => 'x', 'poster_name' => 'Acme'], true);
    $aid = cx_application_add($rid, ['applicant_professional_id' => $pid, 'applicant_name' => 'Esc Pro']);

    $hostile = '<img src=x onerror=alert(1)><script>alert(2)</script>';
    [$ok] = connect_msg_post($aid, 'professional', $pid, 'Esc Pro', $hostile);
    t_ok($ok, 'the professional\'s message is stored (stored verbatim; escaping is the screen\'s job)');

    $render = function ($openId) use ($aid) {
        $summaries = connect_msg_summaries([$aid], 'staff', 7);
        $open = $openId ? ['app' => connect_msg_app($openId), 'thread' => connect_msg_thread($openId)] : null;
        $meId = 7;
        ob_start(); include __DIR__ . '/../views/ops/connect_messages.php'; return ob_get_clean();
    };

    // Inbox only (no thread open) — this is where the snippet was printed raw.
    $html = $render(0);
    t_ok(strpos($html, '<img src=x') === false, 'the inbox snippet does not contain a live <img> tag');
    t_ok(strpos($html, '<script>alert(2)') === false, 'the inbox snippet does not contain a live <script> tag');
    t_ok(strpos($html, '&lt;img src=x onerror=alert(1)&gt;') !== false, 'the inbox snippet shows the message as escaped text');

    // Thread open — the conversation itself must stay escaped too.
    $html2 = $render($aid);
    t_ok(strpos($html2, '<img src=x') === false && strpos($html2, '<script>alert(2)') === false,
        'the open conversation does not contain live markup from the message');

    // A staff reply keeps its "You: " label and is escaped the same way.
    connect_msg_post($aid, 'staff', 7, 'Coordinator', 'Tom & Jerry <b>bold</b>');
    $html3 = $render(0);
    t_ok(strpos($html3, 'You: Tom &amp; Jerry &lt;b&gt;bold&lt;/b&gt;') !== false, 'a staff reply shows "You: " and its text escaped');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
