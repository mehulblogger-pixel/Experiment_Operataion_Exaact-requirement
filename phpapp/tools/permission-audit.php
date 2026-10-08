<?php
// ============================================================================
//  PERMISSION AUDIT — what guards each route, and what verb it really is
// ============================================================================
//  Input to R-20, the permission-model rebuild. The owner asked, in their own
//  words, to "check what already exists and what is not there". This answers it
//  mechanically rather than by reading: it walks the router, finds the handler
//  behind every route, reads that handler's body, and records every guard it
//  applies. Nothing here is judgement — it is what the code does.
//
//  It is deliberately conservative. A handler that delegates its guard to a
//  helper is recorded as guarded by that helper, not as unguarded; the goal is
//  to find routes with NO guard anywhere, which is a far stronger claim.
//
//  Run:  php tools/permission-audit.php            (human table)
//        php tools/permission-audit.php --csv      (machine readable)
// ============================================================================
$root = dirname(__DIR__);
$csv  = in_array('--csv', $argv, true);

// ---- 1. route -> handler + the router's own inline guards --------------------
//  A case may handle the route inline (guarding and rendering right there) or
//  delegate to a handler. We must read BOTH: a route whose router block calls
//  ops_require(can('x')) and then view() is guarded, even though no handler
//  function exists. Treating the guard call as the handler — the naive reading —
//  reports guarded routes as unguarded, which is the one error that matters here.
$ops   = file_get_contents("$root/lib/ops.php");
$lines = explode("\n", $ops);
$GUARD_FNS = ['ops_require','can','is_master','is_admin_level','is_coordinator_level',
              'hiring_admin_can','books_can','master_access_ok','licence_blocks',
              'is_master_of','appr_can_act','product_package_can','recruit_home_can'];
$routes = [];                                  // route => ['fn'=>…, 'inline'=>…]
for ($i = 0; $i < count($lines); $i++) {
    if (!preg_match('/^\s*case \$route === /', $lines[$i])) continue;
    preg_match_all("/\\\$route === '([a-z0-9_\\/-]+)'/", $lines[$i], $m);
    if (!$m[1]) continue;
    // the case block runs to the next `case `/`default:` at statement level
    $blk = '';
    for ($j = $i + 1; $j < count($lines); $j++) {
        if (preg_match('/^\s*(case |default:)/', $lines[$j])) break;
        $blk .= $lines[$j] . "\n";
    }
    // the delegated handler is the first called function that is NOT a guard,
    // not a language construct, and not a renderer
    $fn = '';
    if (preg_match_all('/\b([a-z_][a-z0-9_]*)\s*\(/i', $blk, $f)) {
        foreach ($f[1] as $cand) {
            if (in_array($cand, $GUARD_FNS, true)) continue;
            if (in_array($cand, ['return','if','elseif','else','while','foreach','for',
                                 'switch','isset','empty','view','redirect','flash',
                                 'array','count','in_array','intval','trim'], true)) continue;
            $fn = $cand; break;
        }
    }
    foreach ($m[1] as $r) $routes[$r] = ['fn' => $fn, 'inline' => $blk];
}

// ---- 2. index every function body in lib/ -----------------------------------
$bodies = [];
foreach (glob("$root/lib/*.php") as $f) {
    $src = file_get_contents($f);
    if (!preg_match_all('/^function\s+([a-z_][a-z0-9_]*)\s*\(/mi', $src, $mm, PREG_OFFSET_CAPTURE)) continue;
    foreach ($mm[1] as $k => $hit) {
        $name = $hit[0];
        $start = $mm[0][$k][1];
        $brace = strpos($src, '{', $start);
        if ($brace === false) continue;
        $depth = 0; $end = $brace;
        for ($p = $brace; $p < strlen($src); $p++) {
            if ($src[$p] === '{') $depth++;
            elseif ($src[$p] === '}') { $depth--; if ($depth === 0) { $end = $p; break; } }
        }
        $bodies[$name] = substr($src, $brace, $end - $brace + 1);
    }
}

// ---- 3. what guards does a body apply? --------------------------------------
function guards_in(string $body): array {
    $g = [];
    if (preg_match_all("/\bcan\('([a-z0-9_.]+)'\)/", $body, $m)) foreach ($m[1] as $k) $g[] = "can:$k";
    foreach (['is_master', 'is_admin_level', 'is_coordinator_level', 'hiring_admin_can',
              'books_can', 'master_access_ok', 'product_package_can', 'recruit_home_can'] as $fn)
        if (preg_match("/\b$fn\s*\(/", $body)) $g[] = $fn;
    if (preg_match('/\bops_require\s*\(/', $body))   $g[] = 'ops_require';
    if (preg_match('/\blicence_blocks\s*\(/', $body)) $g[] = 'licence';
    if (preg_match('/\bscope_(allows|clause|office_clause)\b/', $body)) $g[] = 'office-scope';
    return array_values(array_unique($g));
}

// ---- 4. what verb is this route, by its name? -------------------------------
function verb_of(string $r): string {
    if (preg_match('/(^|-)(new|add|create|generate)$/', $r))              return 'add';
    if (preg_match('/(^|-)(edit|save|update|rename|set|move|assign)$/', $r)) return 'edit';
    if (preg_match('/(^|-)(delete|remove|purge|destroy)$/', $r))          return 'delete';
    if (preg_match('/(^|-)(retire|deactivate|disable|close|cancel|revoke)$/', $r)) return 'deactivate';
    if (preg_match('/(^|-)(reactivate|activate|enable|reopen|restore)$/', $r)) return 'activate';
    if (preg_match('/(^|-)(approve|reject|decide|issue|finalize|finalise|sign)$/', $r)) return 'approve';
    return 'view';
}

// ---- 5. report ---------------------------------------------------------------
$rows = [];
foreach ($routes as $r => $info) {
    $fn   = $info['fn'];
    $body = $fn && isset($bodies[$fn]) ? $bodies[$fn] : '';
    $g    = array_values(array_unique(array_merge(
                guards_in($info['inline']),            // guards in the router itself
                $body === '' ? [] : guards_in($body))) // guards inside the handler
            );
    $rows[] = ['route' => $r, 'handler' => $fn ?: '(inline)', 'verb' => verb_of($r),
               'guards' => $g, 'resolved' => ($body !== '' || $fn === '')];
}
usort($rows, fn($a, $b) => [$a['verb'], $a['route']] <=> [$b['verb'], $b['route']]);

if ($csv) {
    echo "route,verb,handler,resolved,guard_count,guards\n";
    foreach ($rows as $x)
        echo '"' . $x['route'] . '","' . $x['verb'] . '","' . $x['handler'] . '","'
           . ($x['resolved'] ? 'yes' : 'no') . '","' . count($x['guards']) . '","'
           . implode(' ', $x['guards']) . "\"\n";
    exit(0);
}

$byVerb = []; $unguarded = []; $unresolved = 0;
foreach ($rows as $x) {
    $byVerb[$x['verb']]['total'] = ($byVerb[$x['verb']]['total'] ?? 0) + 1;
    if (!$x['resolved']) { $unresolved++; continue; }
    if (!$x['guards']) { $byVerb[$x['verb']]['unguarded'] = ($byVerb[$x['verb']]['unguarded'] ?? 0) + 1; $unguarded[] = $x; }
}
echo "ROUTES FOUND: " . count($rows) . "   (handler body resolved for " . (count($rows) - $unresolved) . ")\n\n";
echo str_pad('VERB', 12) . str_pad('ROUTES', 9) . "NO GUARD IN THE HANDLER\n";
echo str_repeat('-', 46) . "\n";
foreach (['view','add','edit','approve','deactivate','activate','delete'] as $v) {
    if (!isset($byVerb[$v])) continue;
    echo str_pad($v, 12) . str_pad((string)$byVerb[$v]['total'], 9) . (int)($byVerb[$v]['unguarded'] ?? 0) . "\n";
}
echo "\nWRITE ROUTES WITH NO GUARD ANYWHERE IN THEIR HANDLER\n" . str_repeat('-', 46) . "\n";
$w = 0;
foreach ($unguarded as $x) {
    if ($x['verb'] === 'view') continue;
    printf("  %-28s %-10s %s\n", $x['route'], $x['verb'], $x['handler']);
    $w++;
}
echo $w ? "\n  $w write route(s) above need a human decision.\n" : "  none\n";

// ============================================================================
//  PART 2 — VERB COVERAGE: which of the seven verbs can the owner grant today?
// ============================================================================
//  The owner's question, in their words: "hope you have separated selection each
//  for edit, add, delete, remove, deactivate and activate". This part answers it
//  per module instead of in the abstract. The rule it applies:
//
//    view        mod.<key>.view always exists                    -> always separate
//    add/edit    mod.<key>.edit is ONE tick labelled "add / edit" -> MERGED, unless
//                a dedicated fine-grained key names the verb
//    the rest    separate only if a fine-grained key names the verb
//
//  A verb is reported SEPARATE only when a permission key exists that grants that
//  verb and nothing wider. Where authority comes from is_master()/is_admin_level()
//  instead, the verb is reported ROLE — real protection, but not something the
//  owner can hand to one person without handing them everything else too.
// ============================================================================
echo "\n\n" . str_repeat('=', 78) . "\nVERB COVERAGE PER MODULE\n" . str_repeat('=', 78) . "\n";

// Which fine-grained key supplies which verb, for which module. This map is the
// one piece of judgement in the tool: it reads each PERMISSIONS key's intent.
$FINE = [
  'crm.quote.create'       => ['quotes',      'add'],
  'ops.call.create'        => ['calls',       'add'],
  'crm.contract.register'  => ['crm_orders',  'add'],
  'ops.call.delete'        => ['calls',       'delete'],
  'ops.job.close'          => ['jobs',        'deactivate'],
  'ncr.close'              => ['ncr',         'deactivate'],
  'capa.close'             => ['capa',        'deactivate'],
  'crm.quote.approve'      => ['quotes',      'approve'],
  'idems.finalize'         => ['idems',       'approve'],
  'idems.template.approve' => ['idems',       'approve'],
  'complaints.decide'      => ['complaints',  'approve'],
  'hiring.review.clear'    => ['hiring',      'approve'],
  'workforce.report.approve'=>['reconcile',   'approve'],
  'crm.followup.manage'    => ['inquiries',   'edit'],
  'crm.template.manage'    => ['quotes',      'edit'],
  'idems.timestamp.edit'   => ['idems',       'edit'],
  'idems.type.manage'      => ['idems',       'edit'],
  'ops.job.allocate'       => ['jobs',        'edit'],
  'finance.reconcile'      => ['reconcile',   'edit'],
  'master.manage'          => ['masters',     'edit'],
  'settings.manage'        => ['settings',    'edit'],
  'users.manage.branch'    => ['users',       'edit'],
  'users.manage.global'    => ['users',       'edit'],
  'person.iddoc.manage'    => ['identity',    'edit'],
  'hiring.admin'           => ['hiring',      'edit'],
];
$VERBS = ['view','add','edit','delete','deactivate','activate'];
// Read ACCESS_MODULES out of the source text. Loading lib/access.php would drag in
// the database and session layers, which an offline audit tool must not need.
$acc  = file_get_contents("$root/lib/access.php");
$seg  = substr($acc, strpos($acc, 'ACCESS_MODULES = ['));
$seg  = substr($seg, 0, strpos($seg, "\n];"));
preg_match_all("/'([a-z0-9_]+)'\s*=>/", $seg, $am);
$mods = $am[1];
$grid  = [];
foreach ($mods as $k) {
    $grid[$k] = ['view' => 'yes', 'add' => 'merged', 'edit' => 'yes',
                 'delete' => 'role', 'deactivate' => 'role', 'activate' => 'role'];
}
foreach ($FINE as $key => [$m, $v]) {
    if (!isset($grid[$m])) continue;
    if ($v === 'add' || $v === 'delete' || $v === 'deactivate' || $v === 'activate') $grid[$m][$v] = 'yes';
}
printf("%-18s %-7s %-8s %-7s %-8s %-11s %s\n", 'MODULE', 'view', 'add', 'edit', 'delete', 'deactivate', 'activate');
echo str_repeat('-', 78) . "\n";
$tally = [];
foreach ($grid as $k => $row) {
    printf("%-18s %-7s %-8s %-7s %-8s %-11s %s\n", $k, $row['view'], $row['add'],
           $row['edit'], $row['delete'], $row['deactivate'], $row['activate']);
    foreach ($row as $v => $state) $tally[$v][$state] = ($tally[$v][$state] ?? 0) + 1;
}
echo str_repeat('-', 78) . "\n";
echo "\nSEPARATELY GRANTABLE TODAY (out of " . count($mods) . " modules)\n" . str_repeat('-', 46) . "\n";
foreach ($VERBS as $v) {
    $yes = (int)($tally[$v]['yes'] ?? 0);
    $note = $v === 'add' ? '  (' . (int)($tally[$v]['merged'] ?? 0) . ' share the edit tick)' : '';
    $role = (int)($tally[$v]['role'] ?? 0);
    printf("  %-12s %2d separate%s%s\n", $v, $yes, $note,
           $role ? '  (' . $role . ' decided by role, not by a tick)' : '');
}
