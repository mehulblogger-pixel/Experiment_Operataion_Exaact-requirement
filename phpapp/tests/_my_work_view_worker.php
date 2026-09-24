<?php
// ============================================================================
//  A REAL separate process that renders My Work through the PRODUCTION view().
//
//  Why a worker rather than an in-process call: three other test files install a
//  capturing STUB view() (module38 / module46 / module49). Whichever runs first
//  wins the global function name, so in a full-suite run an in-process render
//  would silently go through a stub — which is the very mistake that let the
//  /my-work defect survive. A separate process cannot be polluted that way.
//
//  It attaches to the SAME database as the parent (env), and never uses
//  tests/bootstrap.php, which drops every table on the MySQL path.
//
//    php tests/_my_work_view_worker.php handler <first> <last> <role> <super>
//    php tests/_my_work_view_worker.php vars    <json-encoded view vars>
//
//  Prints one JSON line. See docs/phase7/MY-WORK-DEFECT-FIX.md.
// ============================================================================
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'my-work-view-worker';
$_SERVER['REQUEST_URI'] = '/my-work'; $_SERVER['HTTP_HOST'] = 'localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;

// The production view(), verbatim. Written to a file in the application root so
// that __DIR__ inside it resolves exactly as it does when index.php defines it.
$i = strpos($idx, 'function view($name, $vars = []) {');
$j = strpos($idx, "\n}\n", $i);
$fn = substr($idx, $i, $j - $i + 3);
foreach ((array) glob($root . '/.viewprobe_*.php') as $stale) @unlink($stale);
$tmp = $root . '/.viewprobe_' . getmypid() . '.php';
file_put_contents($tmp, "<?php\n" . $fn);
register_shutdown_function(function () use ($tmp) { @unlink($tmp); });
require $tmp;

// Prove, by reflection, that the view() now in scope really is the production
// one: read the function back out of whatever file actually defines it and
// fingerprint it. If somebody ever replaces this loader with a hand-written
// imitation, the fingerprint stops matching index.php and the tests fail — which
// is the whole point, because an imitation is what hid this defect originally.
$__rf   = new ReflectionFunction('view');
$__src  = (array) file($__rf->getFileName());
$__body = implode('', array_slice($__src, $__rf->getStartLine() - 1,
                                 $__rf->getEndLine() - $__rf->getStartLine() + 1));
$viewSha = hash('sha256', rtrim($__body));
@unlink($tmp);            // only now — reflection had to read it first

$mode = (string) ($argv[1] ?? '');
$out  = ['mode' => $mode, 'ok' => false, 'viewSha' => $viewSha];
try {
    if ($mode === 'handler') {
        $first = (string) ($argv[2] ?? ''); $last = (string) ($argv[3] ?? '');
        $role  = (string) ($argv[4] ?? 'COORDINATOR'); $super = (int) ($argv[5] ?? 0);
        $un = 'vnc_' . substr(md5($first . '|' . $last . '|' . $role), 0, 12);
        db()->prepare("DELETE FROM users WHERE username=?")->execute([$un]);
        db()->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, is_superuser)
                       VALUES (?,?,?,?,1,?)")->execute([$un, $first, $last, $role, $super]);
        $_SESSION['uid'] = (int) db()->lastInsertId();
        current_user(true); if (function_exists('ua')) ua(true);
        $out['displayName'] = function_exists('user_name') ? user_name(current_user()) : '';
        ob_start(); ops_my_work(); $html = (string) ob_get_clean();
        db()->prepare("DELETE FROM users WHERE username=?")->execute([$un]);
    } elseif ($mode === 'vars') {
        $vars = json_decode((string) ($argv[2] ?? '{}'), true) ?: [];
        $uid = (int) ops_val("SELECT id FROM users WHERE is_active=1 ORDER BY id LIMIT 1");
        if ($uid) { $_SESSION['uid'] = $uid; current_user(true); if (function_exists('ua')) ua(true); }
        ob_start(); view('ops/my_work', $vars); $html = (string) ob_get_clean();
    } else {
        throw new RuntimeException('unknown mode');
    }
    $out['ok']          = true;
    $out['len']         = strlen($html);
    $out['isMyWork']    = (strpos($html, '<h1>My Work</h1>') !== false)
                       && (strpos($html, '› My Work</div>') !== false)
                       && (strpos($html, 'could not be loaded') === false);
    $out['isAdminArea'] = (strpos($html, '<h1>Admin</h1>') !== false)
                       || (strpos($html, 'Nothing is removed.') !== false);
    $out['failPanel']   = strpos($html, 'could not be loaded') !== false;
    $out['html']        = $html;   // the caller decides what else to look for
} catch (Throwable $e) {
    $out['error'] = get_class($e) . ': ' . $e->getMessage();
}
echo json_encode($out), "\n";
