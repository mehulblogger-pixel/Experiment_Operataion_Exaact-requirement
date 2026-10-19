<?php
// Role x route GET matrix via the real dispatcher, one process per call (act.php).
$S = __DIR__; $ids = json_decode(file_get_contents("$S/ids.json"), true);
$roles = ['master','bd','bmA','coA','mm','bdmA','kamA','meA','fin','insA'];
$routes = [['leads',[]],['lead-new',[]],['opportunities',[]],['opportunity-new',[]],['pipelines',[]],['industry',[]],
  ['inquiries',[]],['inquiry-new',[]],['quotes',[]],['quotes',['mine'=>'approve']],['quote-new',[]],['quote-external',[]],
  ['crm-dashboard',[]],['crm-reports',[]],['crm-templates',[]],['templates',['kind'=>'quote']],['quote-approval-rules',[]],
  ['approval-rules',['module'=>'quote']],['preorder-checklist',[]],['project-costings',[]],['approvals',[]],['stage-gates',[]],['adspro',[]]];
$out = "route\t" . implode("\t", $roles) . "\n";
foreach ($routes as [$r, $g]) {
  $label = $r . ($g ? '?' . http_build_query($g) : '');
  $row = [$label];
  foreach ($roles as $role) {
    $uid = $ids['U'][$role];
    $res = shell_exec('cd /home/user/Experiment_Operataion_Exaact-requirement/phpapp && php ' . escapeshellarg("$S/act.php") . " $uid " . escapeshellarg($r) . ' GET ' . escapeshellarg(json_encode($g ?: new stdClass)) . " '{}' 2>&1");
    preg_match('/RESULT (.*)$/m', (string)$res, $m); $j = json_decode($m[1] ?? 'null', true);
    if (!$j) { $row[] = 'ERR'; continue; }
    if (($j['code'] ?? null) === 403) $c = '403';
    elseif ($j['view'] && $j['view'] !== 'ops/module_locked') $c = 'open';
    elseif (!empty($j['flash'])) { $f = $j['flash'][0]; $c = ($f['tag'] === 'error' ? 'refused' : $f['tag']) . ':' . substr($f['text'], 0, 40); }
    else $c = 'none';
    $row[] = $c;
  }
  $out .= implode("\t", $row) . "\n"; echo implode("\t", $row), "\n";
}
file_put_contents("$S/route_matrix.tsv", $out);
