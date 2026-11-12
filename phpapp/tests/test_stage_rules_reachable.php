<?php
// Owner, 12 Nov 2026 (SAL-033): "Sales → Pipelines & funnels → stage rules … I am
// unable to trace it." The deal-stage approval rules are reachable from Pipelines &
// funnels (as well as Approvals and Set up Sales), for the same people as before.
t_section('Deal-stage approval rules can be found from Pipelines & funnels');
$v = (string)file_get_contents(__DIR__ . '/../views/ops/pipelines.php');
t_ok(strpos($v, 'href="/stage-gates">Approval rules for deal stages</a>') !== false, 'Pipelines & funnels links to "Approval rules for deal stages"');
t_ok(strpos($v, 'gate_can_view() && gate_can_manage()') !== false, 'shown only to the people who may already manage those rules');
