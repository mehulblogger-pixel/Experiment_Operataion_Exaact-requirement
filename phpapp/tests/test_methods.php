<?php
// Controlled method / standard library (ISO §7.1, Roadmap Step 24).
t_section('method library (ISO 7.1)');

t_ok(count(method_opts('method_category')) > 0, 'method-category master seeded');

// Add a method — starts as a draft, unvalidated.
[$id, $err] = method_save([
    'title' => 'UT thickness of pipework', 'standard_ref' => 'ASTM E797', 'revision' => 'Rev 3',
    'category' => 'NDT', 'owner' => 'QA',
]);
t_ok($id > 0 && $err === '', 'a method is added');
$m = method_get($id);
t_ok(strpos($m['method_code'], 'MTH-') === 0, "a code is assigned ({$m['method_code']})");
t_eq($m['status'], 'DRAFT', 'new method starts DRAFT');
t_eq((int)$m['is_validated'], 0, 'new method is not yet validated');

// A blank title is refused.
[$bad, $err2] = method_save(['title' => '  ']);
t_ok($bad === 0 && $err2 !== '', 'a method with no title is refused');

// Validate it, then set it Current.
t_eq(method_validate($id, 'Validated against ASTM E797 by comparison block'), '', 'validation is recorded');
$m = method_get($id);
t_ok((int)$m['is_validated'] === 1 && $m['validated_on'] !== '', 'method now shows validated with a date');
t_eq(method_set_status($id, 'CURRENT'), '', 'method set Current');

// Issue a new revision — old becomes SUPERSEDED, new is a linked DRAFT.
[$newId, $err3] = method_supersede($id, 'Rev 4');
t_ok($newId > 0 && $err3 === '', 'a new revision is created');
$old = method_get($id); $new = method_get($newId);
t_eq($old['status'], 'SUPERSEDED', 'old revision is marked SUPERSEDED');
t_eq((int)$old['superseded_by_id'], $newId, 'old revision links forward to the new one');
t_eq((int)$new['supersedes_id'], $id, 'new revision links back to the old one');
t_eq($new['status'], 'DRAFT', 'new revision starts as a DRAFT');

// The "current only" list shows neither the superseded old nor the draft new.
$currentIds = array_column(methods_all(['current' => 1]), 'id');
t_ok(!in_array($id, $currentIds, true) && !in_array($newId, $currentIds, true),
    'superseded and draft revisions are excluded from the current list');
