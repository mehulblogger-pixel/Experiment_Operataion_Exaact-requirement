<?php
// ============================================================================
//  CVP Slice 4 — the portal notification feed + the "awaiting you" action centre.
//  Proves: the feed is state-derived and self-healing (sync inserts once, never
//  duplicates); it inherits confidentiality (only records the party may already
//  see produce a notification); unread counts + mark-read work; and the action
//  centre reflects live "waiting for you" work for both audiences.
// ============================================================================
$__standalone = empty($GLOBALS['__test_db']);
if ($__standalone) { require __DIR__ . '/lib.php'; require __DIR__ . '/bootstrap.php'; }
if (!function_exists('ok'))   { function ok($cond, $msg) { return t_ok($cond, $msg); } }
if (!function_exists('head')) { function head($t) { t_section($t); } }

if (function_exists('cvp_migrate'))   cvp_migrate();
if (function_exists('ncr_migrate'))   ncr_migrate();
if (function_exists('ncdca_migrate')) ncdca_migrate();

db()->prepare("INSERT INTO business_partners (legal_name,is_client,status,created_at) VALUES ('Buyer Corp',1,'ACTIVE',?)")->execute([date('c')]);
$CLI = (int)db()->lastInsertId();
db()->prepare("INSERT INTO business_partners (legal_name,is_vendor,status,created_at) VALUES ('Acme Fasteners',1,'ACTIVE',?)")->execute([date('c')]);
$VEN = (int)db()->lastInsertId();
db()->exec("DELETE FROM portal_notifications WHERE partner_id IN ($CLI,$VEN)");

// A finalized report to the client; a shared report to the vendor; an open NCR each.
db()->prepare("INSERT INTO report_docs (irn,client_id,status,finalized,deleted,rev,created_at,updated_at) VALUES ('IR/26/001',?, 'ISSUED',1,0,0,?,?)")->execute([$CLI, date('c'), date('c')]);
$rCli = (int)db()->lastInsertId();
db()->prepare("INSERT INTO report_docs (irn,vendor_id,status,finalized,deleted,vendor_visible,rev,created_at,updated_at) VALUES ('VAR/26/001',?, 'ISSUED',1,0,1,0,?,?)")->execute([$VEN, date('c'), date('c')]);
$rVen = (int)db()->lastInsertId();
$mkNcr = function($ref,$pid,$vis) {
    db()->prepare("INSERT INTO nonconformities (ref,partner_id,title,visibility,status,detected_on,created_at) VALUES (?,?,?,?, 'OPEN', ?, ?)")
        ->execute([$ref,$pid,'Finding '.$ref,$vis,date('Y-m-d'),date('c')]);
    return (int)db()->lastInsertId();
};
$nCli = $mkNcr('NCR/C/1', $CLI, 'CLIENT_VISIBLE');
$nVen = $mkNcr('NCR/V/1', $VEN, 'VENDOR_VISIBLE');
$nInt = $mkNcr('NCR/C/9', $CLI, 'INTERNAL');

head('1. Sync builds the client feed from current state');
cvp_notify_sync('CLIENT', $CLI);
$rows = cvp_notifications('CLIENT', $CLI);
$events = array_map(fn($r) => $r['event'], $rows);
ok(in_array('REPORT_ISSUED', $events, true), 'the issued report produced a notification');
ok(in_array('NCR_RAISED', $events, true), 'the client-visible nonconformity produced a notification');

head('2. The feed inherits confidentiality — nothing internal leaks');
$refIds = array_map(fn($r) => (int)$r['ref_id'], array_filter($rows, fn($r) => $r['event'] === 'NCR_RAISED'));
ok(!in_array($nInt, $refIds, true), 'the INTERNAL nonconformity produced NO client notification');

head('3. Sync is idempotent — running it again inserts nothing');
$before = count(cvp_notifications('CLIENT', $CLI));
cvp_notify_sync('CLIENT', $CLI);
cvp_notify_sync('CLIENT', $CLI);
$after = count(cvp_notifications('CLIENT', $CLI));
ok($before === $after, 'repeated syncs do not duplicate (' . $before . ' == ' . $after . ')');

head('4. Unread count + mark read');
$unread = cvp_notify_count('CLIENT', $CLI);
ok($unread === $before && $unread > 0, 'all fresh notifications count as unread');
$one = cvp_notifications('CLIENT', $CLI)[0];
cvp_notify_read('CLIENT', $CLI, (int)$one['id']);
ok(cvp_notify_count('CLIENT', $CLI) === $unread - 1, 'marking one read drops the unread count by one');
cvp_notify_read_all('CLIENT', $CLI);
ok(cvp_notify_count('CLIENT', $CLI) === 0, 'mark-all clears the unread count');

head('5. The vendor feed is separate and its own');
cvp_notify_sync('VENDOR', $VEN);
$vrows = cvp_notifications('VENDOR', $VEN);
$vevents = array_map(fn($r) => $r['event'], $vrows);
ok(in_array('REPORT_SHARED', $vevents, true), 'the shared report notified the vendor');
ok(in_array('NCR_RAISED', $vevents, true), 'the vendor nonconformity notified the vendor');
ok(cvp_notify_count('VENDOR', $VEN) > 0 && cvp_notify_count('CLIENT', $CLI) === 0, 'the two feeds are independent');

head('6. The action centre reflects live "awaiting you" work');
$clientActs = cvp_action_centre('CLIENT', $CLI, fn($k) => true);
$labels = implode(' | ', array_map(fn($a) => $a['label'], $clientActs));
ok(strpos($labels, 'report') !== false, 'client action centre lists reports to decide: ' . $labels);
ok(strpos($labels, 'nonconformity') !== false, 'client action centre lists nonconformities to respond to');
$venActs = cvp_action_centre('VENDOR', $VEN, fn($k) => true);
ok(count($venActs) >= 1 && strpos(implode('|', array_map(fn($a)=>$a['label'],$venActs)), 'nonconformity') !== false,
   'vendor action centre lists nonconformities to respond to');
// A party with nothing pending gets an empty centre.
db()->prepare("INSERT INTO business_partners (legal_name,is_client,status,created_at) VALUES ('Quiet Co',1,'ACTIVE',?)")->execute([date('c')]);
$Q = (int)db()->lastInsertId();
ok(cvp_action_centre('CLIENT', $Q, fn($k) => true) === [], 'a party with nothing pending has an empty action centre');

head('7. Routes wired into both portals');
ok(strpos(file_get_contents(__DIR__ . '/../lib/portal.php'), "case 'portal/alerts':") !== false, 'the client portal has an alerts route');
ok(strpos(file_get_contents(__DIR__ . '/../lib/cvp.php'), "'vendor/alerts'") !== false, 'the vendor portal has an alerts route');

// cleanup
db()->exec("DELETE FROM portal_notifications WHERE partner_id IN ($CLI,$VEN,$Q)");
db()->exec("DELETE FROM nonconformities WHERE id IN ($nCli,$nVen,$nInt)");
db()->exec("DELETE FROM report_docs WHERE id IN ($rCli,$rVen)");
db()->exec("DELETE FROM business_partners WHERE id IN ($CLI,$VEN,$Q)");

if ($__standalone) {
    $g = $GLOBALS['__t'];
    echo "\n==================== CVP NOTIFY: {$g['pass']} passed, {$g['fail']} failed ====================\n";
    exit($g['fail'] === 0 ? 0 : 1);
}
