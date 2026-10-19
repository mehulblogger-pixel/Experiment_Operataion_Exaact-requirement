<?php
// php q.php "SQL" — run a read query against the probe DB (PDO only, no app boot)
$p = new PDO('sqlite:' . __DIR__ . '/probe.sqlite'); $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach (array_slice($argv, 1) as $sql) { echo "> $sql\n"; foreach ($p->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) echo '  ' . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n"; }
