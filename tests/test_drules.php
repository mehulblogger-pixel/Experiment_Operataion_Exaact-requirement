<?php
// Decision rules / acceptance criteria (ISO §7.4, Roadmap Step 25).
t_section('decision rules (ISO 7.4)');

t_ok(count(drule_opts('drule_basis')) > 0, 'decision-basis master seeded');

// A rule can link to a current method from the §7.1 library.
[$mid] = method_save(['title' => 'UT thickness', 'category' => 'NDT']);
method_set_status($mid, 'CURRENT');
$choices = drule_method_choices();
t_ok(isset($choices[$mid]), 'current methods are offered as the "belongs to" choice');

[$id, $err] = drule_save([
    'title' => 'Min wall thickness — pipework', 'method_id' => $mid, 'characteristic' => 'wall thickness (mm)',
    'accept_criteria' => 'x >= 6.0', 'reject_criteria' => 'x < 6.0', 'decision_basis' => 'GUARD_REJ',
    'uncertainty_rule' => 'guard band w = U; reject if x + U < 6.0',
]);
t_ok($id > 0 && $err === '', 'a decision rule is added');
$r = drule_get($id);
t_ok(strpos($r['rule_code'], 'DR-') === 0, "a code is assigned ({$r['rule_code']})");
t_eq($r['status'], 'DRAFT', 'new rule starts DRAFT');
t_eq((int)$r['method_id'], $mid, 'the rule is linked to its method');

[$bad, $e2] = drule_save(['title' => '']);
t_ok($bad === 0 && $e2 !== '', 'a rule with no title is refused');

t_eq(drule_set_status($id, 'CURRENT'), '', 'rule set Current');

// Versioned reissue.
[$newId, $e3] = drule_supersede($id);
t_ok($newId > 0 && $e3 === '', 'a new revision is created');
$old = drule_get($id); $new = drule_get($newId);
t_eq($old['status'], 'SUPERSEDED', 'old rule superseded');
t_eq((int)$new['supersedes_id'], $id, 'new revision links back');
t_eq($new['accept_criteria'], 'x >= 6.0', 'criteria carried into the new revision');

$currentIds = array_column(drules_all(['current' => 1]), 'id');
t_ok(!in_array($id, $currentIds, true) && !in_array($newId, $currentIds, true),
    'superseded and draft revisions are excluded from the current list');
