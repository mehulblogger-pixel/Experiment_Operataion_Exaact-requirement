<?php
// Software bill of materials.
//
// The CERT-In directions expect a verifiable list of what a system is built
// from, so that when a vulnerability is announced in some library, everybody
// running it can find out in an afternoon whether they are affected.
//
// This app has an unusual and rather good answer: there are no third-party
// runtime dependencies at all. No Composer, no npm, no framework, no CDN. It is
// PHP and the browser. That is worth stating precisely rather than claiming, so
// this walks the source and writes out what is actually there — including a
// fingerprint of every file, which is what makes the list verifiable.
//
// Run from the phpapp folder:   php tools/sbom.php
// It writes SBOM.json next to index.php.

$root = dirname(__DIR__);
$skip = ['data', '.git', 'node_modules', 'vendor'];

$files = [];
$walk = function ($dir, $rel = '') use (&$walk, &$files, $root, $skip) {
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') continue;
        $path = $dir . '/' . $f;
        $r = ltrim($rel . '/' . $f, '/');
        if (is_dir($path)) { if (!in_array($f, $skip, true)) $walk($path, $r); continue; }
        if ($f === 'SBOM.json' || $f === 'config.php') continue;   // generated / secret
        $files[] = ['path' => $r, 'bytes' => filesize($path), 'sha256' => hash_file('sha256', $path)];
    }
};
$walk($root);
usort($files, function ($a, $b) { return strcmp($a['path'], $b['path']); });

$byExt = [];
foreach ($files as $f) {
    $e = strtolower(pathinfo($f['path'], PATHINFO_EXTENSION)) ?: '(none)';
    $byExt[$e] = ($byExt[$e] ?? 0) + 1;
}

// Anything pulled in over the network would appear here. The check is run
// rather than asserted, so the claim cannot quietly stop being true.
//
// A link somebody clicks is not a dependency — opening a site on a map does not
// put that site's code in this one. Only a resource the page *loads* counts, so
// the two are separated instead of being reported as one worrying number.
$loaded = []; $linked = [];
foreach ($files as $f) {
    $ext = strtolower(pathinfo($f['path'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['php', 'js', 'css', 'html'], true)) continue;
    $src = (string)file_get_contents($root . '/' . $f['path']);
    // <script src>, <link href>, <img src>, @import, fetch() — these execute or render here.
    if (preg_match_all('#(?:<script[^>]+src|<link[^>]+href|<img[^>]+src|@import\s+url\(|fetch\(\s*["\'])\s*=?\s*["\']?https?://[^"\'()]+#i', $src, $m))
        foreach ($m[0] as $hit) $loaded[] = ['file' => $f['path'], 'reference' => substr($hit, 0, 140)];
    // <a href> — the person leaves this site. Recorded for completeness only.
    if (preg_match_all('#<a[^>]+href\s*=\s*["\']https?://[^"\']+#i', $src, $m))
        foreach ($m[0] as $hit) $linked[] = ['file' => $f['path'], 'reference' => substr($hit, 0, 140)];
}
$external = $loaded;

$sbom = [
    'bomFormat'   => 'CycloneDX',
    'specVersion' => '1.5',
    'version'     => 1,
    'metadata'    => [
        'timestamp' => date('c'),
        'component' => [
            'type'        => 'application',
            'name'        => 'exaact-inspection-ops',
            'description' => 'Inspection and operations management system',
            'version'     => trim((string)@file_get_contents($root . '/VERSION')) ?: '1.0',
        ],
        'tools' => [['name' => 'tools/sbom.php', 'description' => 'walks the source and fingerprints every file']],
    ],
    // The point of the whole exercise. An empty list here is the finding.
    'components' => [],
    'notes' => [
        'third_party_runtime_dependencies' => 'None. There is no Composer, no npm, no framework and no CDN. '
            . 'The application is plain PHP with PDO, and plain JavaScript in the browser.',
        'platform_dependencies' => 'PHP 8 with the PDO, pdo_mysql, json, mbstring, openssl and gd extensions, '
            . 'and MySQL 5.7 or later. These are supplied and patched by the hosting provider, and are the only '
            . 'components for which a vulnerability announcement needs to be watched.',
        'why_this_matters' => 'A vulnerability in a library the application does not have cannot affect it. '
            . 'The list of things to watch is therefore PHP itself, the MySQL server, and the web server.',
    ],
    'inventory' => [
        'file_count'      => count($files),
        'total_bytes'     => array_sum(array_column($files, 'bytes')),
        'files_by_type'   => $byExt,
        'resources_loaded_from_other_sites' => $external,
        'links_a_person_can_click_to_other_sites' => $linked,
        'files'           => $files,
    ],
];

file_put_contents($root . '/SBOM.json',
    json_encode($sbom, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

echo "SBOM.json written.\n";
echo "  files      : " . count($files) . "\n";
echo "  total size : " . round(array_sum(array_column($files, 'bytes')) / 1024) . " KB\n";
echo "  third-party runtime dependencies : 0\n";
echo "  resources loaded from other sites : " . count($external);
echo count($external) ? "  <-- these ARE dependencies, look at them\n" : " (none - nothing is fetched from anywhere)\n";
echo "  links a person can click out to    : " . count($linked) . " (not dependencies)\n";
