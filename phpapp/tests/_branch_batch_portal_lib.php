<?php
// Shared by tests/test_branch_batch_portal_*.php: runs one mode of
// tests/_branch_batch_portal_worker.php in a separate process attached to the
// same test database, and returns the JSON it printed.
if (!isset($GLOBALS['__bbp_worker'])) {
    $root   = dirname(__DIR__);
    $engine = $GLOBALS['__test_engine'] ?? 'sqlite';
    $env    = $engine === 'sqlite'
        ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
        : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST'))
          . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
          . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER'))
          . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
    $GLOBALS['__bbp_worker'] = function ($mode) use ($root, $env) {
        $raw = (string) shell_exec($env . ' php ' . escapeshellarg($root . '/tests/_branch_batch_portal_worker.php')
                                  . ' ' . escapeshellarg((string) $mode) . ' 2>&1');
        foreach (array_reverse(explode("\n", trim($raw))) as $line) {
            $d = json_decode(trim($line), true);
            if (is_array($d) && isset($d['mode'])) return $d;
        }
        return ['ok' => false, 'error' => 'no JSON from worker: ' . substr($raw, 0, 400)];
    };
}
