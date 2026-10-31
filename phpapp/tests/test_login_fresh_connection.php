<?php
// Owner, 31 Oct: "every login and password shows invalid". One cause found in
// the sign-in code: $pdo is opened at the top of the request; when the browser
// was last inside a company workspace it pointed at that company's database,
// and saas_leave_tenant() switched db() back WITHOUT the handler re-taking the
// connection, so the password was checked against the wrong users table.
t_section('sign-in checks the password against the main database after leaving a workspace');
$src = file_get_contents(__DIR__ . '/../index.php');
$i = strpos($src, "if (function_exists('saas_leave_tenant')) saas_leave_tenant();\n        // Re-take the connection");
t_ok($i !== false, 'the sign-in handler leaves any remembered workspace first');
$j = $i !== false ? strpos($src, '$pdo = db();', $i) : false;
$k = $i !== false ? strpos($src, 'SELECT * FROM users WHERE username', $i) : false;
t_ok($j !== false && $k !== false && $j < $k, 'and re-takes the database connection before it looks the user up');
