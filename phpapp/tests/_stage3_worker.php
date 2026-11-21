<?php
// ============================================================================
//  Sales redesign Stage 3 — Enquiries and Deals (owner, 21 Nov 2026) — draws ONE screen as
//  ONE user through the production dispatcher and view(), with either the NEW view
//  (views/ops/) or the OLD one kept in tests/fixtures/pre_stage3/ (env STAGE3_OLD=1),
//  and prints what a person would find on it as one JSON line: the figures (old
//  .qcard cards and new data-k tiles), every link and form (with its fields), every
//  amount, the navigation and the visible text.
//
//    php tests/_stage3_worker.php <uid> <route> '<get json>'
// ============================================================================
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'stage3-worker'; $_SERVER['HTTP_HOST'] = 'localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;
$i = strpos($idx, 'function view($name, $vars = []) {');
$fn = substr($idx, $i, strpos($idx, "\n}\n", $i) - $i + 3);
//  The old page: the same view(), reading the four Sales screens from the kept copies.
if (getenv('STAGE3_OLD')) {
    $fn = str_replace('$__viewFile = __DIR__ . "/views/$__view.php";',
        '$__viewFile = in_array($__view, ["ops/crm/inquiry_list","ops/crm/inquiry_form","ops/opportunities","ops/opportunity_detail","ops/opportunity_form"], true) ? __DIR__ . "/tests/fixtures/pre_stage3/" . basename($__view) . ".php" : __DIR__ . "/views/$__view.php";', $fn);
}
$tmp = $root . '/.viewprobe_s2_' . getmypid() . '.php';
file_put_contents($tmp, "<?php\n" . $fn);
require $tmp; @unlink($tmp);

[$uid, $route] = [(int) ($argv[1] ?? 0), (string) ($argv[2] ?? '')];
$_GET = json_decode((string) ($argv[3] ?? '{}'), true) ?: [];
$_REQUEST = $_GET; $_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/' . $route . ($_GET ? '?' . http_build_query($_GET) : '');
$_SESSION['uid'] = $uid; current_user(true); if (function_exists('ua')) ua(true);

register_shutdown_function(function () {
    $html = ''; while (ob_get_level()) $html = (string) ob_get_clean() . $html;
    $out = ['len' => strlen($html), 'figs' => [], 'hrefs' => [], 'forms' => [], 'money' => [], 'rail' => null, 'nav' => [], 'bn' => [], 'refused' => false, 'text' => '', 'buttons' => [], 'flash' => array_values($_SESSION['flash'] ?? [])];
    if ($html !== '') {
        $dom = new DOMDocument(); libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $html); $x = new DOMXPath($dom);
        $clean = fn($s) => trim(preg_replace('/\s+/u', ' ', preg_replace('/[^\p{L}\p{N}₹%.,\/×÷+()\- ]/u', '', (string) $s)));
        $main = $x->query("//*[contains(concat(' ',normalize-space(@class),' '),' container ')]")->item(0) ?: $dom->documentElement;
        foreach ($x->query(".//*[contains(concat(' ',normalize-space(@class),' '),' sxk ')]", $main) as $k) {
            $t = $x->query(".//*[contains(@class,'sxk-t')]", $k)->item(0); $v = $x->query(".//*[contains(@class,'sxk-v')]", $k)->item(0);
            $out['figs'][$clean($t ? $t->textContent : '')] = $clean($v ? $v->textContent : '');
        }
        //  The old inquiries register's cards: label (.k) → figure (.v).
        foreach ($x->query(".//a[contains(concat(' ',normalize-space(@class),' '),' kpi ')]", $main) as $k) {
            $t = $x->query(".//*[contains(concat(' ',normalize-space(@class),' '),' k ')]", $k)->item(0); $v = $x->query(".//*[contains(concat(' ',normalize-space(@class),' '),' v ')]", $k)->item(0);
            $out['figs'][$clean($t ? $t->textContent : '')] = $clean($v ? $v->textContent : '');
        }
        //  The old leads register's cards: label (.ql) → figure (.qn).
        foreach ($x->query(".//*[contains(concat(' ',normalize-space(@class),' '),' qcard ')]", $main) as $k) {
            $t = $x->query(".//*[contains(@class,'ql')]", $k)->item(0); $v = $x->query(".//*[contains(@class,'qn')]", $k)->item(0);
            $out['figs'][$clean($t ? $t->textContent : '')] = $clean($v ? $v->textContent : '');
        }
        foreach ($x->query('.//*[@data-k]', $main) as $k) { $v = $x->query('.//*[@data-v]', $k)->item(0); $out['figs'][$clean($k->getAttribute('data-k'))] = $clean($v ? $v->textContent : ''); }
        foreach ($x->query('.//a[@href]', $main) as $a) $out['hrefs'][] = $a->getAttribute('href');
        foreach ($x->query('.//form', $main) as $f) {
            $names = []; foreach ($x->query('.//*[@name]', $f) as $in) if ($in->getAttribute('name') !== '_csrf') $names[] = $in->getAttribute('name');
            sort($names); $out['forms'][] = strtolower($f->getAttribute('method') ?: 'get') . ' ' . $f->getAttribute('action') . ' [' . implode(',', $names) . ']';
        }
        $parts = []; foreach ($x->query('.//text()[not(ancestor::script) and not(ancestor::style)]', $main) as $tn) $parts[] = $tn->nodeValue;
        $txt = implode(' ', $parts); $out['text'] = preg_replace('/\s+/u', ' ', $txt);
        preg_match_all('/₹\s?[\d,]+(?:\.\d+)?\s?(?:Cr|L|K)?/u', $txt, $mm); $out['money'] = array_values(array_unique(array_map(fn($s) => preg_replace('/\s/u', '', $s), $mm[0])));
        foreach ($x->query("//nav//a[contains(@class,'s-item')]") as $a) if (trim($a->textContent) === 'Sales' || str_ends_with(trim($a->textContent), 'Sales')) $out['rail'] = $a->getAttribute('href');
        foreach ($x->query("//nav[contains(@class,'sxw-nav')]//a") as $a) $out['nav'][] = $a->getAttribute('href');
        foreach ($x->query("//nav[contains(@class,'sxw-bn')]//a") as $a) $out['bn'][] = $a->getAttribute('href');
        $out['refused'] = (bool) preg_match('/not open to you|needs "Sales reports"|You cannot|needs the right/i', $out['text']);
        //  Buttons a person can press, by their words (submit buttons and links styled as buttons).
        foreach ($x->query('.//button', $main) as $b) $out['buttons'][] = $clean($b->textContent);
    }
    $out['done'] = $GLOBALS['__s3_done'] ?? 'redirect';
    echo json_encode($out) . "\n";
});
ob_start();
try { ops_dispatch($route, 'GET'); $GLOBALS['__s3_done'] = 'return'; }
catch (Throwable $e) { $GLOBALS['__s3_done'] = 'threw: ' . $e->getMessage(); }
