<?php
// The contract-openings hand-off screen: endorsers and approvers reach the
// Endorse / Approve buttons here without needing to open the quotation (which an
// Operation Manager may not have rights to view). Each viewer sees only the
// action they can take.
t_section('contract-openings screen shows the right action per role');

$render = function ($rows, $canEndorse, $canApprove, $canSeeQuote = false, $myCoordinators = [], $myOfficeName = '') {
    ob_start();
    require __DIR__ . '/../views/ops/contract_openings.php';
    return (string) ob_get_clean();
};
$base = ['id' => 1, 'contract_number' => 'CON/TEST/1', 'quote_id' => 0, 'quote_no' => '',
    'quote_subject' => 'Coating inspection', 'client_name' => 'ACME Ltd', 'requested_by' => 'Sana',
    'requested_at' => '2026-08-20', 'mgr_endorsed_at' => '', 'mgr_endorsed_by' => '', 'bm_approved_at' => ''];

// An endorser, before endorsement — sees Endorse, not Approve.
$h = $render([$base], true, false);
t_ok(strpos($h, 'CON/TEST/1') !== false, 'the pending contract is listed');
t_ok(strpos($h, 'Endorse') !== false, 'the endorser sees an Endorse button');
t_ok(strpos($h, 'Approve &amp; open') === false, 'a pure endorser does not see Approve');
t_ok(strpos($h, 'Awaiting manager endorsement') !== false, 'the step reads awaiting endorsement');

// An approver, after endorsement — sees Approve & open, enabled.
$endorsed = array_merge($base, ['mgr_endorsed_at' => '2026-08-21', 'mgr_endorsed_by' => 'Sunil']);
$h2 = $render([$endorsed], false, true);
t_ok(strpos($h2, 'Approve &amp; open') !== false, 'the approver sees Approve & open');
t_ok(strpos($h2, 'disabled') === false, 'approve is enabled once the contract is endorsed');
t_ok(strpos($h2, 'Awaiting branch-manager approval') !== false, 'the step reads awaiting branch-manager approval');

// Nothing pending.
$h3 = $render([], true, true);
t_ok(strpos($h3, 'Nothing is waiting') !== false, 'an empty state shows when nothing is pending');

// The endorser can forward to a coordinator in his office — an office has several,
// so the Endorse form offers a coordinator picker when coordinators are on file.
$coords = [['id' => 7, 'name' => 'Anita', 'role' => 'COORDINATOR'], ['id' => 8, 'name' => 'Arjun', 'role' => 'ASST_MANAGER']];
$h4 = $render([$base], true, false, false, $coords, 'Head Office');
t_ok(strpos($h4, 'name="coordinator_id"') !== false, 'the Endorse form offers a coordinator picker');
t_ok(strpos($h4, 'Anita') !== false && strpos($h4, 'Arjun') !== false, 'the office coordinators are listed to forward to');

// With no coordinators on file, the picker is simply absent (still endorsable).
$h5 = $render([$base], true, false, false, [], '');
t_ok(strpos($h5, 'name="coordinator_id"') === false, 'no picker when the office has no coordinators');
t_ok(strpos($h5, 'Endorse') !== false, 'endorsement is still offered without a coordinator');

// Once a coordinator is nominated, the row says who will coordinate.
$pdo = db();
$pdo->prepare("INSERT INTO users (username, first_name, role, is_active) VALUES ('co_anita','Anita','COORDINATOR',1)")->execute();
$anitaId = (int)$pdo->lastInsertId();
$withCoord = array_merge($base, ['mgr_endorsed_at' => '2026-08-21', 'mgr_endorsed_by' => 'Sunil', 'coordinator_id' => $anitaId]);
$h6 = $render([$withCoord], false, true);
t_ok(strpos($h6, 'Anita to coordinate') !== false, 'the row shows the nominated coordinator once set');
