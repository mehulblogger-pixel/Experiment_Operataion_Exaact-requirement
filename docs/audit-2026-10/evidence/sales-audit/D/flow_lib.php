<?php
// Driver helpers: run one handler call in its own process; read the DB directly.
$GLOBALS['S'] = __DIR__;
$GLOBALS['IDS'] = json_decode(file_get_contents(__DIR__ . '/ids.json'), true);
$GLOBALS['PDO'] = new PDO('sqlite:' . __DIR__ . '/probe.sqlite');
$GLOBALS['PDO']->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$GLOBALS['PDO']->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
function U($k) { return $GLOBALS['IDS']['U'][$k]; }
function act($who, $route, $method = 'GET', array $get = [], array $post = []) {
    $cmd = 'cd /home/user/Experiment_Operataion_Exaact-requirement/phpapp && php ' . escapeshellarg($GLOBALS['S'] . '/act.php') . ' '
         . (int)U($who) . ' ' . escapeshellarg($route) . ' ' . $method . ' ' . escapeshellarg(json_encode($get ?: new stdClass)) . ' '
         . escapeshellarg(json_encode($post ?: new stdClass)) . ' 2>&1';
    $raw = (string)shell_exec($cmd);
    preg_match('/RESULT (.*)$/m', $raw, $m);
    $j = json_decode($m[1] ?? 'null', true) ?: ['raw' => substr($raw, -400)];
    $fl = array_map(fn($f) => '[' . $f['tag'] . '] ' . $f['text'], (array)($j['flash'] ?? []));
    $j['say'] = ($j['code'] ?? null) === 403 ? '403 ' . ($j['view'] ?? '') : (implode(' | ', $fl) ?: ('view=' . ($j['view'] ?? 'none') . (isset($j['form_error']) ? ' form_error="' . $j['form_error'] . '"' : '')));
    return $j;
}
function q($sql, $a = []) { $s = $GLOBALS['PDO']->prepare($sql); $s->execute($a); return $s->fetchAll(); }
function q1($sql, $a = []) { return q($sql, $a)[0] ?? null; }
function qv($sql, $a = []) { $r = q1($sql, $a); return $r ? array_values($r)[0] : null; }
$GLOBALS['LOG'] = [];
function step($id, $scenario, $expected, $actual, $evidence = '') {
    $line = sprintf("%-5s %s\n      expected: %s\n      actual:   %s%s\n", $id, $scenario, $expected, $actual, $evidence !== '' ? "\n      evidence: $evidence" : '');
    echo $line; $GLOBALS['LOG'][] = $line;
}
function lastq() { return q1("SELECT * FROM quotations ORDER BY id DESC LIMIT 1"); }
function quote_post(array $o = []) {
    return array_merge(['client_id' => (string)$GLOBALS['IDS']['C'], 'sbu' => 'IND', 'office_id' => (string)$GLOBALS['IDS']['A'],
        'subject' => 'Probe quote', 'contact_name' => 'Ravi', 'contact_email' => 'ravi@example.invalid', 'gst_pct' => '18', 'validity_days' => '30',
        'l_desc' => ['Inspection man-days'], 'l_service' => ['INSPECTION'], 'l_qty' => ['10'], 'l_rate' => ['10000'], 'l_unit' => ['MANDAY'], 'l_sbu' => ['IND']], $o);
}
