<?php
// P7 — /quote-external: status, totals and approval on a quotation registered from a client portal.
putenv('OPS_MAIL_ENABLED');
require __DIR__ . '/harness.php';
$me = mkuser('me1', 'MARKETING_EXECUTIVE');
db()->exec("DELETE FROM quote_approval_rules"); rule('L1 MM all', 0, 0, 1, 'MARKETING_MANAGER');
function ext($uid, array $over) {
    $post = array_merge(['origin' => 'CLIENT_PORTAL', 'client_name' => 'Portal Client', 'subject' => 'Portal bid', 'office_id' => (string) probe_office(),
        'total_amount' => '100', 'status' => 'SENT', 'l_desc' => ['Inspection'], 'l_qty' => ['1'], 'l_rate' => ['1000000'], 'l_amount' => [''], 'l_unit' => ['MANDAY'], 'l_service' => ['']], $over);
    $before = (int) ops_val("SELECT COALESCE(MAX(id),0) FROM quotations");
    $r = route($uid, 'quote-external', 'POST', [], $post);
    $id = (int) ops_val("SELECT COALESCE(MAX(id),0) FROM quotations");
    return [$id > $before ? $id : 0, flashes($r)];
}
foreach (['ACCEPTED', 'APPROVED', 'NOT_A_STATUS'] as $s) {
    [$id, $f] = ext($me, ['status' => $s]);
    show("MARKETING_EXECUTIVE registers external quote with status=$s", $f . ' ' . json_encode($id ? ops_one("SELECT status,subtotal,gst_pct,gst_amount,total_amount FROM quotations WHERE id=?", [$id]) : null)
        . ' steps=' . ($id ? count(steps($id)) : '-'));
}
[$id, $f] = ext($me, ['total_amount' => '100']);
show('typed total 100 vs line 1 x 1,000,000', json_encode(ops_one("SELECT total_amount FROM quotations WHERE id=?", [$id])) . ' lines=' . json_encode(ops_all("SELECT qty,rate,amount FROM quote_lines WHERE quote_id=?", [$id])));
[$id, $f] = ext($me, ['total_amount' => '100', 'l_amount' => ['5']]);
show('line amount typed 5 for 1 x 1,000,000', json_encode(ops_all("SELECT qty,rate,amount FROM quote_lines WHERE quote_id=?", [$id])));
