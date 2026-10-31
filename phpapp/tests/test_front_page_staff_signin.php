<?php
// Owner, 31 Oct 2026 (test book ONB-000): "Staff sign in at top when clicked
// takes to the same front page." The header's "Sign in" lands on the page's own
// "Welcome back" section, which offered only marketplace sign-ins. It now offers
// the company workspace (staff) sign-in too.
t_section('Front page — "Sign in" offers the staff sign-in');
$v = (string) file_get_contents(__DIR__ . '/../views/ops/connect_front.php');
$i = strpos($v, 'id="signin"'); $blk = substr($v, $i, 1400);
t_ok($i !== false && strpos($blk, 'href="/login">Sign in to your company workspace (staff)</a>') !== false,
     'the "Welcome back" section the header links to has a staff sign-in button');
