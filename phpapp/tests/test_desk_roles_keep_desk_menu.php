<?php
// Owner's screenshot, 20 Oct 2026: a Business Development Manager signed in to
// the inspector's screen — My jobs, My vouchers, attendance — and no Sales.
// Every login must belong to a team member (Add a person → Team member), and
// is_field_inspector() answered yes for any non-management login seated on a
// team record. The role decides; the team record does not.
t_section('desk roles keep their desk menu when linked to a team member');

$pdo = db();
$pdo->prepare("INSERT INTO inspectors (name, team_role) VALUES ('DRK field person', 'FIELD')")->execute();
$insp = (int)$pdo->lastInsertId();
$roles = ['BUSINESS_DEV_MANAGER' => false, 'KEY_ACCOUNTS_MANAGER' => false, 'MARKETING_EXECUTIVE' => false,
          'FINANCE' => false, 'QUALITY_HEAD' => false, 'TECHNICAL_HEAD' => false,
          'INSPECTOR' => true, 'SR_INSPECTOR' => true];
foreach ($roles as $role => $field) {
    $pdo->prepare("INSERT INTO users (username, first_name, role, inspector_id, permissions, is_active) VALUES (?,?,?,?,'',1)")
        ->execute(['drk_' . strtolower($role), 'DRK', $role, $insp]);
    $_SESSION['uid'] = (int)$pdo->lastInsertId(); current_user(true); ua(true);
    t_ok(is_field_inspector() === $field, $role . ($field ? ' gets the field screen' : ' keeps the desk screen though linked to a field team member'));
}
unset($_SESSION['uid']); current_user(true); ua(true);
$pdo->exec("DELETE FROM users WHERE username LIKE 'drk_%'");
$pdo->prepare("DELETE FROM inspectors WHERE id=?")->execute([$insp]);
