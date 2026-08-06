<?php
// Controlled-document register (ISO §8.3, Roadmap Step 26).
t_section('controlled documents (ISO 8.3)');

t_ok(count(cdoc_opts('cdoc_type')) > 0, 'document-type master seeded');

[$id, $err] = cdoc_save([
    'title' => 'Inspection Reporting Procedure', 'doc_type' => 'PROCEDURE', 'revision' => 'Rev 2',
    'owner' => 'QM', 'approved_by' => 'Director', 'approved_on' => '2026-01-10',
    'review_due' => '2020-01-01', 'distribution' => 'All offices',
]);
t_ok($id > 0 && $err === '', 'a controlled document is added');
$d = cdoc_get($id);
t_ok(strpos($d['doc_code'], 'DOC-') === 0, "a code is assigned ({$d['doc_code']})");
t_eq($d['status'], 'DRAFT', 'new document starts DRAFT');

[$bad, $e2] = cdoc_save(['title' => '']);
t_ok($bad === 0 && $e2 !== '', 'a document with no title is refused');

t_eq(cdoc_set_status($id, 'CURRENT'), '', 'document set Current');

// A current document past its review date is counted as overdue for review.
$c = cdoc_counts();
t_ok($c['review_due'] >= 1, 'an overdue review is counted');

// Versioned reissue.
[$newId, $e3] = cdoc_supersede($id, 'Rev 3');
t_ok($newId > 0 && $e3 === '', 'a new revision is created');
$old = cdoc_get($id); $new = cdoc_get($newId);
t_eq($old['status'], 'SUPERSEDED', 'old revision superseded');
t_eq((int)$new['supersedes_id'], $id, 'new revision links back');
t_eq($new['revision'], 'Rev 3', 'the new revision label is set');

$currentIds = array_column(cdocs_all(['current' => 1]), 'id');
t_ok(!in_array($id, $currentIds, true) && !in_array($newId, $currentIds, true),
    'superseded and draft revisions are excluded from the current list');
