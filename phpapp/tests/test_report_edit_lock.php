<?php
// Once an inspection report is submitted it is out of the inspector's hands: the
// body is frozen until it is sent back (REJECTED) or is a fresh draft. Guards the
// pure status rule that idems_can_edit_doc enforces.
t_section('report is locked from editing once submitted');

t_ok(idems_status_allows_edit('') === true, 'a brand-new (blank status) report is editable');
t_ok(idems_status_allows_edit('DRAFT') === true, 'a DRAFT is editable');
t_ok(idems_status_allows_edit('REJECTED') === true, 'a sent-back (REJECTED) report is editable again');
t_ok(idems_status_allows_edit('SUBMITTED') === false, 'a SUBMITTED report is not editable');
t_ok(idems_status_allows_edit('UNDER_REVIEW') === false, 'a report under review is not editable');
t_ok(idems_status_allows_edit('VETTING') === false, 'a report at vetting is not editable');
t_ok(idems_status_allows_edit('APPROVED') === false, 'an approved report is not editable');
t_ok(idems_status_allows_edit('ISSUED') === false, 'an issued report is not editable');

// A finalized report is never editable, whatever the permission.
t_ok(idems_can_edit_doc(['status'=>'DRAFT','finalized'=>1]) === false, 'a finalized report is never editable');
