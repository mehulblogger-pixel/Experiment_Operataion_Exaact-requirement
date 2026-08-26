<?php
// Phase 2 §23/24 — canonical PARTY / PERSON mapping layer.
//
// One human is stored across several identity tables that were built at different
// times: users (staff login), inspectors (workforce), candidates (recruitment),
// partner_contacts (a person at a client/vendor org), client_users + vendor_users
// (portal logins). A candidate who is hired becomes an inspector who may also be a
// portal user — three-plus unlinked or one-way-linked rows.
//
// This layer does NOT merge or delete any of those tables (non-destructive rule).
// It RESOLVES one canonical identity key — explicit ref, else last-10 mobile, else
// email, the SAME precedence recruitment already uses (recruit.php person_key) —
// and lists every record that belongs to the same person across the stores, so new
// development can read "one person" without inventing another identity table.

// The stores, and how to read a name + contact from each. Guarded per store, so an
// install that lacks a store (or a column) simply skips it.
// Contact columns per store (email always present; mobile only where the table has
// one — users/client_users/vendor_users key on email alone, their phone lives on a
// linked contact). A missing column is guarded anyway.
function party_stores() {
    return [
        'USER'        => ['table'=>'users',            'label'=>'Staff login',
                          'name'=>"COALESCE(NULLIF(TRIM(COALESCE(first_name,'')||' '||COALESCE(last_name,'')),''), username)",
                          'email'=>'email', 'mobile'=>'', 'ref'=>'', 'url'=>'/users'],
        'INSPECTOR'   => ['table'=>'inspectors',       'label'=>'Inspector',
                          'name'=>'name', 'email'=>'email', 'mobile'=>'mobile', 'ref'=>'', 'url'=>'/competence'],
        'CANDIDATE'   => ['table'=>'candidates',       'label'=>'Candidate',
                          'name'=>"NULLIF(TRIM(COALESCE(first_name,'')||' '||COALESCE(last_name,'')),'')",
                          'email'=>'email', 'mobile'=>'mobile', 'ref'=>'person_ref', 'url'=>'/candidate?id='],
        'CONTACT'     => ['table'=>'partner_contacts', 'label'=>'Client / vendor contact',
                          'name'=>'name', 'email'=>'email', 'mobile'=>'mobile', 'ref'=>'', 'url'=>''],
        'CLIENT_USER' => ['table'=>'client_users',     'label'=>'Client portal user',
                          'name'=>'name', 'email'=>'email', 'mobile'=>'', 'ref'=>'', 'url'=>'/portal-users'],
        'VENDOR_USER' => ['table'=>'vendor_users',     'label'=>'Vendor portal user',
                          'name'=>'name', 'email'=>'email', 'mobile'=>'', 'ref'=>'', 'url'=>'/vendor-users'],
    ];
}

function party_norm_mobile($m) { $d = preg_replace('/\D+/', '', (string)$m); return strlen($d) >= 10 ? substr($d, -10) : ''; }
function party_norm_email($e) { return strtolower(trim((string)$e)); }

// The canonical key from raw contact points. Explicit ref wins, else last-10 mobile,
// else email. Returns '' when there is nothing to key on (never matches anyone).
function party_key($mobile, $email, $ref = '') {
    $ref = trim((string)$ref); if ($ref !== '') return 'ref:' . $ref;
    $m = party_norm_mobile($mobile); if ($m !== '') return 'mob:' . $m;
    $e = party_norm_email($email);   if ($e !== '') return 'em:' . $e;
    return '';
}

// The canonical key for a specific record in a specific store.
function party_key_of($kind, $id) {
    $s = party_stores()[$kind] ?? null; if (!$s || !$id) return '';
    try {
        $sel = "SELECT COALESCE(" . $s['email'] . ",'') e"
             . ($s['mobile'] ? ", COALESCE(" . $s['mobile'] . ",'') m" : ", '' m")
             . ($s['ref'] ? ", COALESCE(" . $s['ref'] . ",'') r" : ", '' r")
             . " FROM " . $s['table'] . " WHERE id=?";
        $row = ops_one($sel, [(int)$id]);
        return $row ? party_key($row['m'] ?? '', $row['e'] ?? '', $row['r'] ?? '') : '';
    } catch (Throwable $e) { return ''; }
}

// A record's raw contact points (mobile, email, ref), for cross-store linking on ANY
// shared identifier — the same "ref OR mobile OR email" rule recruitment uses.
function party_identity_of($kind, $id) {
    $s = party_stores()[$kind] ?? null; if (!$s || !$id) return ['mobile'=>'', 'email'=>'', 'ref'=>''];
    $base = "SELECT COALESCE(" . $s['email'] . ",'') e" . ($s['mobile'] ? ", COALESCE(" . $s['mobile'] . ",'') m" : ", '' m");
    // The ref column (candidates.person_ref) is optional and not on every install; try it,
    // then fall back to email+mobile so a missing ref column never blanks the identity.
    if ($s['ref']) {
        try { $row = ops_one($base . ", COALESCE(" . $s['ref'] . ",'') r FROM " . $s['table'] . " WHERE id=?", [(int)$id]);
            if ($row !== null) return ['mobile'=>(string)($row['m'] ?? ''), 'email'=>(string)($row['e'] ?? ''), 'ref'=>(string)($row['r'] ?? '')]; }
        catch (Throwable $e) { /* ref column absent — fall through */ }
    }
    try { $row = ops_one($base . " FROM " . $s['table'] . " WHERE id=?", [(int)$id]);
        return $row ? ['mobile'=>(string)($row['m'] ?? ''), 'email'=>(string)($row['e'] ?? ''), 'ref'=>''] : ['mobile'=>'', 'email'=>'', 'ref'=>'']; }
    catch (Throwable $e) { return ['mobile'=>'', 'email'=>'', 'ref'=>'']; }
}

// Every record across the stores that shares ANY identifier with (mobile, email, ref)
// — matched by email (exact, lowercased), mobile (last 10 digits) or ref. The real
// linker: an email-keyed record still links to a mobile-keyed one if they share the
// email. Each match dimension selects ONLY the column it needs, in its own try/catch,
// so a store missing an optional column (e.g. person_ref) still matches on the others.
function party_records_for($mobile, $email, $ref = '', $limit = 40) {
    $m = party_norm_mobile($mobile); $e = party_norm_email($email); $rf = trim((string)$ref);
    if ($m === '' && $e === '' && $rf === '') return [];
    $out = []; $seen = [];
    $add = function ($skind, $s, $rows, $dim, $want) use (&$out, &$seen) {
        foreach ($rows as $r) {
            $ok = $dim === 'em'  ? party_norm_email($r['pemail'] ?? '') === $want
                : ($dim === 'mob' ? party_norm_mobile($r['pmobile'] ?? '') === $want
                :                    trim((string)($r['pref'] ?? '')) === $want);
            if (!$ok) continue;
            $dk = $skind . ':' . (int)$r['id']; if (isset($seen[$dk])) continue; $seen[$dk] = 1;
            $url = $s['url']; if ($url !== '' && substr($url, -1) === '=') $url .= (int)$r['id'];
            $out[] = ['kind'=>$skind, 'label'=>$s['label'], 'id'=>(int)$r['id'],
                      'name'=>trim((string)($r['pname'] ?? '')) ?: '—', 'url'=>$url];
        }
    };
    foreach (party_stores() as $skind => $s) {
        $nm = $s['name']; $t = $s['table']; $lim = max(1, (int)$limit);
        if ($e !== '') { try { $add($skind, $s, ops_all("SELECT id, $nm AS pname, COALESCE(" . $s['email'] . ",'') AS pemail FROM $t WHERE LOWER(COALESCE(" . $s['email'] . ",'')) = ? LIMIT $lim", [$e]) ?: [], 'em', $e); } catch (Throwable $ex) {} }
        if ($m !== '' && $s['mobile']) { try { $add($skind, $s, ops_all("SELECT id, $nm AS pname, COALESCE(" . $s['mobile'] . ",'') AS pmobile FROM $t WHERE COALESCE(" . $s['mobile'] . ",'') LIKE ? LIMIT $lim", ['%' . substr($m, -6) . '%']) ?: [], 'mob', $m); } catch (Throwable $ex) {} }
        if ($rf !== '' && $s['ref']) { try { $add($skind, $s, ops_all("SELECT id, $nm AS pname, COALESCE(" . $s['ref'] . ",'') AS pref FROM $t WHERE COALESCE(" . $s['ref'] . ",'') = ? LIMIT $lim", [$rf]) ?: [], 'ref', $rf); } catch (Throwable $ex) {} }
    }
    return $out;
}

// Every record across the stores that shares this single canonical key. (party_records_for
// is the stronger union matcher; this stays for callers that hold only a key string.)
function party_records($key, $limit = 40) {
    $key = (string)$key;
    [$kk, $val] = array_pad(explode(':', $key, 2), 2, '');
    if ($val === '') return [];
    $out = []; $seen = [];
    foreach (party_stores() as $skind => $s) {
        $sel = "SELECT id, " . $s['name'] . " AS pname, COALESCE(" . $s['email'] . ",'') AS pemail"
             . ($s['mobile'] ? ", COALESCE(" . $s['mobile'] . ",'') AS pmobile" : ", '' AS pmobile")
             . ($s['ref'] ? ", COALESCE(" . $s['ref'] . ",'') AS pref" : ", '' AS pref")
             . " FROM " . $s['table'];
        $rows = [];
        try {
            if ($kk === 'em') {
                $rows = ops_all($sel . " WHERE LOWER(COALESCE(" . $s['email'] . ",'')) = ? LIMIT " . max(1, (int)$limit), [$val]) ?: [];
            } elseif ($kk === 'mob' && $s['mobile']) {
                $rows = ops_all($sel . " WHERE COALESCE(" . $s['mobile'] . ",'') LIKE ? LIMIT " . max(1, (int)$limit), ['%' . substr($val, -6) . '%']) ?: [];
            } elseif ($kk === 'ref' && $s['ref']) {
                $rows = ops_all($sel . " WHERE COALESCE(" . $s['ref'] . ",'') = ? LIMIT " . max(1, (int)$limit), [$val]) ?: [];
            }
        } catch (Throwable $e) { $rows = []; }
        foreach ($rows as $r) {
            // Confirm the match in PHP — especially a mobile compared on its last 10 digits.
            $ok = ($kk === 'em'  && party_norm_email($r['pemail'] ?? '') === $val)
               || ($kk === 'mob' && party_norm_mobile($r['pmobile'] ?? '') === $val)
               || ($kk === 'ref' && trim((string)($r['pref'] ?? '')) === $val);
            if (!$ok) continue;
            $dk = $skind . ':' . (int)$r['id']; if (isset($seen[$dk])) continue; $seen[$dk] = 1;
            $url = $s['url']; if ($url !== '' && substr($url, -1) === '=') $url .= (int)$r['id'];
            $out[] = ['kind'=>$skind, 'label'=>$s['label'], 'id'=>(int)$r['id'],
                      'name'=>trim((string)($r['pname'] ?? '')) ?: '—', 'url'=>$url];
        }
    }
    return $out;
}

// The distinct roles one person (given by their record) holds across the stores.
function party_roles_of($kind, $id) {
    $idn = party_identity_of($kind, $id);
    $roles = [];
    foreach (party_records_for($idn['mobile'], $idn['email'], $idn['ref']) as $r)
        $roles[$r['kind']] = party_stores()[$r['kind']]['label'] ?? $r['kind'];
    return $roles;
}

// "This person also appears elsewhere in the system" — resolve one record's contact
// points and list the OTHER records for the same person (any shared identifier). For a
// detail-view panel; drop-in echo. Read-only; a link, never a merge.
function party_render_also($kind, $id, $title = 'Same person across the system') {
    if (!function_exists('party_records_for')) return;
    $idn = party_identity_of($kind, $id);
    $others = array_values(array_filter(party_records_for($idn['mobile'], $idn['email'], $idn['ref']),
        fn($r) => !($r['kind'] === $kind && (int)$r['id'] === (int)$id)));
    if (!$others) return;
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    echo '<div class="panel"><h3 class="tab-sub" style="margin-top:0">' . $e($title)
       . ' <span class="muted" style="font-weight:400;font-size:12px">(' . count($others) . ')</span></h3>'
       . '<p class="muted" style="margin:0 0 8px;font-size:12px">Matched by shared mobile or email. Records are kept separate; this is a link, not a merge.</p>'
       . '<div style="display:flex;flex-wrap:wrap;gap:6px">';
    foreach ($others as $o) {
        $label = $e($o['label']) . ' · ' . $e($o['name']);
        echo $o['url'] !== ''
            ? '<a class="pill p-mut" href="' . $e($o['url']) . '">' . $label . '</a>'
            : '<span class="pill p-mut">' . $label . '</span>';
    }
    echo '</div></div>';
}
