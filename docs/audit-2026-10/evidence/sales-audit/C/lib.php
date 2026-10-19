<?php
// Driver helpers. The driver itself is booted on the same DB via boot.php.
function req($uid, $route, $method = 'GET', array $get = [], array $post = []) {
    $payload = json_encode(['uid' => $uid, 'route' => $route, 'method' => $method, 'get' => $get, 'post' => $post]);
    $cmd = 'cd /home/user/Experiment_Operataion_Exaact-requirement/phpapp && PROBE_DB=' . escapeshellarg(getenv('PROBE_DB'))
         . ' php -d sendmail_path=/bin/true ' . escapeshellarg(__DIR__ . '/w.php') . ' ' . escapeshellarg($payload) . ' 2>&1';
    $raw = shell_exec($cmd);
    $p = strrpos((string)$raw, '@@OUT@@');
    $o = $p === false ? ['raw' => substr((string)$raw, -800)] : (json_decode(trim(substr($raw, $p + 7)), true) ?: ['raw' => substr($raw, -800)]);
    if (function_exists('current_user')) { current_user(true); }
    return $o;
}
function show($label, $o) {
    echo "  > $label\n";
    foreach (($o['flash'] ?? []) as $f) echo "      flash  " . mb_substr(preg_replace('/\s+/', ' ', $f), 0, 260) . "\n";
    if (!empty($o['view'])) echo "      view   " . $o['view'] . (!empty($o['view_error']) ? '  ERROR: ' . mb_substr(preg_replace('/\s+/', ' ', $o['view_error']), 0, 300) : '') . "\n";
    if (empty($o['flash']) && empty($o['view']) && !empty($o['body'])) echo "      body   " . $o['body'] . "\n";
    if (!empty($o['page_error'])) echo "      page   " . mb_substr(preg_replace('/\s+/', ' ', $o['page_error']), 0, 260) . "\n";
    if (!empty($o['exception'])) echo "      EXC    " . $o['exception'] . "\n";
    if (!empty($o['fatal'])) echo "      FATAL  " . $o['fatal'] . "\n";
    if (!empty($o['raw'])) echo "      RAW    " . $o['raw'] . "\n";
}
function row($sql, $a = []) { return ops_one($sql, $a); }
function val($sql, $a = []) { return ops_val($sql, $a); }
function rows($sql, $a = []) { return ops_all($sql, $a); }
function mkuser($u, $role, $office, $super = 0) {
    db()->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id) VALUES (?,?,?,?,?,1,?,?)")
        ->execute([$u, ucfirst(strtolower($u)), 'Probe', $u . '@probe.invalid', $role, $super, $office]);
    return (int)db()->lastInsertId();
}
function mkpartner($name, $gstin, $extra = []) {
    db()->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,status,state,gstin,created_at) VALUES (?,?,?,1,?,?,?,?)")
        ->execute(['P-' . substr(md5($name), 0, 6), $name, $name, $extra['status'] ?? 'ACTIVE', 'Gujarat', $gstin, date('c')]);
    $id = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO partner_addresses (partner_id,address_type,line1,city,state,is_primary) VALUES (?,?,?,?,?,1)")->execute([$id, 'REGISTERED', '1 Probe Rd', 'Vadodara', 'Gujarat']);
    db()->prepare("INSERT INTO partner_contacts (partner_id,name,email,mobile,is_primary) VALUES (?,?,?,?,1)")->execute([$id, 'Buyer ' . $name, 'buyer@' . substr(md5($name), 0, 5) . '.invalid', '9800000000']);
    if (!empty($extra['hold'])) db()->prepare("UPDATE business_partners SET hold_status=?, hold_reason=? WHERE id=?")->execute([$extra['hold'], 'probe reason', $id]);
    return $id;
}
// Post a quotation via the real quote-new route; returns the new quote id.
function mkquote($uid, array $hdr, array $lines) {
    $post = array_merge(['gst_pct' => 18, 'sbu' => 'TPI', 'subject' => 'Probe quote', 'validity_days' => 30, 'currency' => 'INR'], $hdr);
    foreach ($lines as $l) {
        $post['l_desc'][] = $l[0]; $post['l_service'][] = $l[1] ?? 'TPI'; $post['l_qty'][] = $l[2]; $post['l_unit'][] = $l[3]; $post['l_rate'][] = $l[4];
        $post['l_order'][] = 'LINE'; $post['l_sbu'][] = ''; $post['l_office'][] = ''; $post['l_location'][] = ''; $post['l_activity'][] = '';
    }
    $before = (int)val("SELECT COALESCE(MAX(id),0) FROM quotations");
    $o = req($uid, 'quote-new', 'POST', [], $post);
    $id = (int)val("SELECT COALESCE(MAX(id),0) FROM quotations");
    return [$id > $before ? $id : 0, $o];
}
function qstatus($id) { return (string)val("SELECT status FROM quotations WHERE id=?", [$id]); }
