<?php
// Owner, 31 Oct 2026 (test book MOD-SAL): Admin → Report configuration → Report
// templates showed the trail "Home › Sales › Quote templates". One address
// serves both template screens; the trail now follows the one shown.
t_section('Templates — the trail names the screen you opened');
$prevUid = $_SESSION['uid'] ?? null; $g = $_GET;
try {
    $_SESSION['uid'] = (int) ops_val("SELECT id FROM users WHERE username='admin'"); current_user(true); ua(true);
    $_GET['kind'] = 'report';
    t_eq(array_column(nav_trail('templates'), 'label'), ['Home', 'Admin', T('report') . ' templates'], 'Report templates: Home › Admin › Report templates');
    $_GET['kind'] = 'quote';
    t_eq(array_column(nav_trail('templates'), 'label'), ['Home', 'Sales', ucfirst(Tl('quote')) . ' templates'], 'Quote templates: Home › Sales › Quote templates');
} finally {
    $_GET = $g; if ($prevUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prevUid; current_user(true); ua(true);
}
