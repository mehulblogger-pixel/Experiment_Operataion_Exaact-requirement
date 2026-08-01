<?php
// ============================================================================
//  cPanel API — automatic workspace provisioning
//
//  With a cPanel API token, adding a workspace can create its MySQL database,
//  its database user, grant the privileges, and (optionally) create the
//  subdomain — instead of the operator doing those two cPanel screens by hand.
//  Everything is OPTIONAL: leave the token blank and provisioning stays manual,
//  exactly as before.
//
//  The token is created in cPanel → Manage API Tokens. It carries the same
//  power as the cPanel login, so it is stored on the control site only, shown
//  masked, and used only from this screen. Calls go to the account's own cPanel
//  (https://host:2083/execute/Module/function) over the UAPI, authenticated
//  with the "Authorization: cpanel user:TOKEN" header.
//
//  DELIBERATELY NARROW: this creates databases and subdomains and nothing else.
//  It never deletes anything — removing a workspace still leaves its database in
//  place, so a mistake is recoverable.
// ============================================================================

function cpanel_config() {
    $host  = (string) setting_get('cpanel_host', '');
    $user  = (string) setting_get('cpanel_user', '');
    $token = (string) setting_get('cpanel_token', '');
    return [
        'host'           => $host,
        'port'           => (int) (setting_get('cpanel_port', '') ?: 2083),
        'user'           => $user,
        'token'          => $token,
        'base_domain'    => (string) (setting_get('cpanel_rootdomain', '')
                              ?: (function_exists('tenant_base_domain') ? tenant_base_domain() : '')),
        'docroot'        => (string) setting_get('cpanel_docroot', ''),
        'make_subdomain' => setting_get('cpanel_make_subdomain', '') === '1',
        'verify_ssl'     => setting_get('cpanel_verify_ssl', '1') !== '0',
        'enabled'        => $host !== '' && $user !== '' && $token !== '',
    ];
}

function cpanel_configured() { return cpanel_config()['enabled']; }

// A strong password for the workspace database user. cPanel enforces a strength
// score, so this mixes cases, digits and symbols.
function cpanel_gen_password($len = 20) {
    $sets = ['abcdefghijkmnpqrstuvwxyz', 'ABCDEFGHJKLMNPQRSTUVWXYZ', '23456789', '!@#%^*-_=+'];
    $all = implode('', $sets);
    $out = '';
    foreach ($sets as $s) $out .= $s[random_int(0, strlen($s) - 1)];      // at least one of each
    for ($i = strlen($out); $i < $len; $i++) $out .= $all[random_int(0, strlen($all) - 1)];
    return str_shuffle($out);
}

// One UAPI call. Returns ['ok'=>bool, 'data'=>mixed, 'errors'=>string[]]. The
// HTTP transport can be overridden for testing via $GLOBALS['__cpanel_transport']
// — a callable ($url,$headers,$params) => [httpStatus, body].
function cpanel_api($module, $function, array $params = []) {
    $c = cpanel_config();
    if (!$c['enabled']) return ['ok' => false, 'data' => null, 'errors' => ['cPanel API is not configured.']];
    $url = 'https://' . $c['host'] . ':' . $c['port'] . '/execute/' . rawurlencode($module) . '/' . rawurlencode($function);
    if ($params) $url .= '?' . http_build_query($params);
    $headers = ['Authorization: cpanel ' . $c['user'] . ':' . $c['token']];

    if (!empty($GLOBALS['__cpanel_transport']) && is_callable($GLOBALS['__cpanel_transport'])) {
        [$status, $body] = ($GLOBALS['__cpanel_transport'])($url, $headers, $params);
    } elseif (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_SSL_VERIFYPEER => $c['verify_ssl'],
            CURLOPT_SSL_VERIFYHOST => $c['verify_ssl'] ? 2 : 0,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        if ($body === false) return ['ok' => false, 'data' => null, 'errors' => ['Could not reach cPanel: ' . $curlErr]];
    } else {
        return ['ok' => false, 'data' => null, 'errors' => ['This server has no cURL, so cPanel cannot be reached from PHP.']];
    }

    if ((int)$status === 403 || (int)$status === 401)
        return ['ok' => false, 'data' => null, 'errors' => ['cPanel refused the token (' . (int)$status . '). Check the username and API token.']];
    $j = json_decode((string) $body, true);
    if (!is_array($j)) return ['ok' => false, 'data' => null, 'errors' => ['cPanel returned an unreadable response.']];
    $ok = (int) ($j['status'] ?? 0) === 1;
    $errors = !empty($j['errors']) ? array_map('strval', (array) $j['errors']) : [];
    return ['ok' => $ok && !$errors, 'data' => $j['data'] ?? null, 'errors' => $errors ?: ($ok ? [] : ['cPanel reported a failure.'])];
}

// A cheap call that proves the token works, for the "Test connection" button.
function cpanel_test() {
    $r = cpanel_api('Mysql', 'list_databases');
    if ($r['ok']) return ['ok' => true, 'msg' => 'Connected. cPanel answered and the token works.'];
    return ['ok' => false, 'msg' => implode(' ', $r['errors']) ?: 'cPanel did not accept the request.'];
}

// Create the database + user + privileges (and optionally the subdomain) for a
// workspace. Returns ['ok'=>bool, 'db'=>[host,name,user,pass], 'subdomain'=>bool,
// 'errors'=>string[]]. Never deletes anything: if a later step fails, the earlier
// ones are left in place and reported, so the operator can finish by hand.
function cpanel_provision_workspace($sub) {
    $c = cpanel_config();
    $sub = strtolower(preg_replace('/[^a-z0-9]/', '', (string) $sub));
    if ($sub === '') return ['ok' => false, 'errors' => ['Workspace name is empty.']];
    // cPanel prefixes database and user names with the account name + underscore.
    $prefix = $c['user'] . '_';
    $dbname = $prefix . $sub;
    $dbuser = $prefix . $sub;
    $pass   = cpanel_gen_password();

    $r = cpanel_api('Mysql', 'create_database', ['name' => $dbname]);
    if (!$r['ok']) return ['ok' => false, 'errors' => array_merge(['The database could not be created.'], $r['errors'])];

    $r = cpanel_api('Mysql', 'create_user', ['name' => $dbuser, 'password' => $pass]);
    if (!$r['ok']) return ['ok' => false, 'errors' => array_merge(['The database was created, but its user could not be — remove the half-made database in cPanel and try again.'], $r['errors'])];

    $r = cpanel_api('Mysql', 'set_privileges_on_database',
        ['user' => $dbuser, 'database' => $dbname, 'privileges' => 'ALL PRIVILEGES']);
    if (!$r['ok']) return ['ok' => false, 'errors' => array_merge(['The database and user were created, but privileges could not be granted.'], $r['errors'])];

    $out = ['ok' => true, 'db' => ['host' => 'localhost', 'name' => $dbname, 'user' => $dbuser, 'pass' => $pass],
            'subdomain' => null, 'errors' => []];

    if ($c['make_subdomain']) {
        if ($c['base_domain'] === '' || $c['docroot'] === '') {
            $out['errors'][] = 'The database is ready, but the subdomain was skipped — set the root domain and document root in the cPanel settings, or create the subdomain by hand.';
        } else {
            $rs = cpanel_api('SubDomain', 'addsubdomain',
                ['domain' => $sub, 'rootdomain' => $c['base_domain'], 'dir' => $c['docroot']]);
            $out['subdomain'] = $rs['ok'];
            if (!$rs['ok'])
                $out['errors'][] = 'The database is ready, but the subdomain was not created (' . (implode(' ', $rs['errors']) ?: 'cPanel refused it')
                    . ') — create ' . $sub . '.' . $c['base_domain'] . ' by hand in cPanel → Subdomains.';
        }
    }
    return $out;
}
