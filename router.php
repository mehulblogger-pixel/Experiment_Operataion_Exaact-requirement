<?php
$p = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$f = __DIR__ . $p;
if ($p !== "/" && file_exists($f) && !is_dir($f)) return false;
require __DIR__ . "/index.php";
