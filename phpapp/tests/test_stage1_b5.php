<?php
// Stage 1 · B5 (owner, 24 Nov 2026 — "approve B0–B5, all as recommended"): the same
// competence warning everywhere, including the board's suggestions and the allocate
// form's alternatives; the 45-day "expiring soon" window becomes a setting (default
// 45). No new block — the existing pack block is unchanged.
t_section('Stage 1 B5 — one competence warning; the window is a setting');

$prev = setting_get('credential_window_days', '');
try {
    if ($prev !== '') setting_set('credential_window_days', '');
    t_eq(credential_window_days(), 45, 'the default window is 45 days — what every company had');
    setting_set('credential_window_days', '30');
    t_eq(credential_window_days(), 30, 'a company can set its own window (30)');
    setting_set('credential_window_days', '0');
    t_eq(credential_window_days(), 45, 'a nonsense value falls back to 45');
    setting_set('credential_window_days', '900');
    t_eq(credential_window_days(), 45, 'more than a year falls back to 45');

    $own = !db()->inTransaction();
    if ($own) db()->beginTransaction();
    try {
        db()->prepare("INSERT INTO inspectors (name, status) VALUES (?, 'ACTIVE')")->execute(['B5 Cert Holder']);
        $ins = (int)db()->lastInsertId();
        $in40 = date('Y-m-d', strtotime('+40 days'));
        db()->prepare("INSERT INTO inspector_certs (inspector_id, name, valid_to) VALUES (?,?,?)")->execute([$ins, 'B5 cert', $in40]);
        setting_set('credential_window_days', '45');
        t_eq(inspector_eligibility($ins)['status'], 'EXPIRING', 'with 45 days, a certificate lapsing in 40 days is "expiring" on the pill');
        t_eq(credential_status(['valid_to' => $in40]), 'EXPIRING', '… and on the credential itself');
        t_ok(in_array($ins, array_map('intval', array_column(competence_training_watch(), 'inspector_id')), true), '… and on the training watch');
        setting_set('credential_window_days', '30');
        t_eq(inspector_eligibility($ins)['status'], 'ELIGIBLE', 'with 30 days, the same certificate is not yet "expiring" — the pill follows the setting');
        t_eq(credential_status(['valid_to' => $in40]), 'VALID', '… the credential agrees');
        t_ok(!in_array($ins, array_map('intval', array_column(competence_training_watch(), 'inspector_id')), true), '… and so does the training watch');
    } finally {
        if ($own && db()->inTransaction()) db()->rollBack();
    }
} finally {
    setting_set('credential_window_days', $prev);
}

// The suggestions and alternatives carry the same verdict (a warning; nothing new blocks).
$sb = (string) file_get_contents(__DIR__ . '/../lib/schedboard.php');
t_ok(strpos($sb, "'elig' => \$elig['status']") !== false, 'board suggestions carry the eligibility verdict');
$sv = (string) file_get_contents(__DIR__ . '/../views/ops/schedule_board.php');
t_ok(strpos($sv, 'inspector_eligibility_pill') !== false, 'the board shows the same eligibility pill as the allocate form');
$sc = (string) file_get_contents(__DIR__ . '/../lib/schedule.php');
t_ok(strpos($sc, "'elig_label'") !== false, 'the allocate form\'s "who else could take this date" carries the verdict');
$pk = (string) file_get_contents(__DIR__ . '/../lib/packs.php');
t_ok(strpos($pk, 'Stage 1 B5') === false, 'the existing pack block is untouched');
