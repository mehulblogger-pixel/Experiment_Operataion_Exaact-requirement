<?php
//  Owner, 28 Nov 2026: a deleted lead showed on Customer 360 → "What has happened" but not on the
//  Client register → client → Timeline tab. Both now read the same activity record.
t_section('One timeline — Client register tab shows what Customer 360 shows');
require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture(); $pdo = db(); $made = [];
try {
    bbv_as($f['master']);
    $pdo->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,is_vendor,status,created_at) VALUES (?,?,?,1,0,'ACTIVE',?)")
        ->execute(['TL-' . $f['tag'], 'Timeline Co ' . $f['tag'], 'Timeline Co ' . $f['tag'], date('c')]);
    $pid = (int) $pdo->lastInsertId(); $made[] = $pid;
    act_log('LEAD', 999001, 'DELETED', 'Lead LD-TL-' . $f['tag'] . ' deleted — Timeline Co', ['auto' => 1, 'partner_id' => $pid]);
    $c360 = array_column(c360_timeline($pid), 'subject');
    t_ok(in_array('Lead LD-TL-' . $f['tag'] . ' deleted — Timeline Co', $c360, true), 'Customer 360 shows the deleted lead');
    $p = ops_one("SELECT * FROM business_partners WHERE id=?", [$pid]);
    $vars = ['p' => $p, 'tab' => 'timeline', 'viewLevel' => 'FULL', 'parent' => null, 'subsidiaries' => [], 'addresses' => [],
             'contacts' => [], 'registrations' => [], 'notes' => [], 'contracts' => [], 'cityList' => [], 'awaiting' => [],
             'relationships' => [], 'pos' => [], 'projects' => [], 'timeline' => []];
    $h = (function ($file, $vars) { extract($vars); ob_start(); try { include $file; } catch (Throwable $e) { echo 'RENDER-ERROR ' . $e->getMessage(); } return (string) ob_get_clean(); })(dirname(__DIR__) . '/views/detail.php', $vars);
    t_ok(strpos($h, 'RENDER-ERROR') === false, 'the Timeline tab draws');
    t_ok(strpos($h, 'Lead LD-TL-' . $f['tag'] . ' deleted') !== false, 'the Client register Timeline tab shows the same deleted lead');
    t_ok(strpos($h, 'Partner record created') !== false, 'its own items (record created, notes, contracts, POs) are still there');
    t_ok(strpos($h, 'The same events as Customer 360') !== false, 'it says it is the same record as Customer 360');
} finally {
    if ($made) { $in = implode(',', array_map('intval', $made)); $pdo->exec("DELETE FROM activities WHERE partner_id IN ($in)"); $pdo->exec("DELETE FROM business_partners WHERE id IN ($in)"); }
    bbv_cleanup($f);
}
