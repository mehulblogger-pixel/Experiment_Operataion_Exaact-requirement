<?php
// Asset issuance register — issue kit to a person, acknowledge it, return it,
// and keep the counts honest.
t_section('asset issuance register');
assets_migrate();

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $p = team_member_create('Asset Holder One', 'FIELD');

    t_eq(asset_issue_add(['person_id' => $p, 'asset_type' => 'HARD_STAMP', 'asset_name' => 'Hard stamp A',
                          'identifier' => 'HS-042', 'issued_on' => '2026-08-01'], null), '',
        'an asset is issued to a person');
    t_ok(asset_issue_add(['person_id' => 0, 'asset_type' => 'HARD_STAMP'], null) !== '',
        'issuing to nobody is refused');
    t_ok(asset_issue_add(['person_id' => $p, 'asset_type' => 'NOPE'], null) !== '',
        'an unknown asset type is refused');

    $rows = person_assets($p);
    t_ok(count($rows) === 1 && $rows[0]['status'] === 'ISSUED', 'the issued asset is held by the person');
    $id = (int)$rows[0]['id'];

    $s0 = person_assets_summary($p);
    t_ok($s0['out'] === 1 && $s0['noack'] === 1, 'one asset out, and it is not acknowledged yet');

    asset_ack($id, ['ack_on' => '2026-08-02', 'ack_by' => 'Asset Holder One'], null);
    $s1 = person_assets_summary($p);
    t_ok($s1['out'] === 1 && $s1['noack'] === 0, 'after acknowledgement nothing is awaiting sign-off');

    asset_return($id, ['status' => 'RETURNED', 'returned_on' => '2026-09-01', 'condition_returned' => 'GOOD']);
    t_eq(asset_row($id)['status'], 'RETURNED', 'the asset is marked returned');
    t_ok(person_assets_summary($p)['out'] === 0, 'nothing is out after it is returned');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

t_ok(isset(asset_types()['HARD_STAMP'], asset_types()['SAFETY_HELMET'], asset_types()['DIARY']),
    'default asset types include hard stamp, safety helmet and diary');
$idx = (string)file_get_contents(__DIR__ . '/../index.php');
t_ok(strpos($idx, "require __DIR__ . '/lib/assets.php'") !== false, 'the asset module is loaded');

// The "kit not returned" flag when a holder leaves / is deactivated.
t_section('kit-not-returned flag when a person leaves');
$own2 = !db()->inTransaction();
if ($own2) db()->beginTransaction();
try {
    $before = asset_counts()['left'];
    $p2 = team_member_create('Leaver One', 'FIELD');
    asset_issue_add(['person_id' => $p2, 'asset_type' => 'HARD_STAMP', 'asset_name' => 'Hard stamp L'], null);
    t_ok(asset_counts()['left'] === $before, 'while the person is active, their kit is not on the "left" list');

    db()->prepare("UPDATE inspectors SET status='INACTIVE' WHERE id=?")->execute([$p2]);
    t_ok(asset_counts()['left'] === $before + 1, 'once deactivated, their unreturned asset is flagged');

    $held = person_assets($p2, true);
    asset_return((int)$held[0]['id'], ['status' => 'RETURNED']);
    t_ok(asset_counts()['left'] === $before, 'returning the asset clears the flag');
} finally {
    if ($own2 && db()->inTransaction()) db()->rollBack();
}
$ops = (string)file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, 'still not returned') !== false,
    'deactivating an engineer warns about their unreturned kit');
