<?php
// The pre-submission completeness gate must never demand a detail the report
// type's form cannot capture. Inspection-only checks (Scope of inspection,
// QAP / ITP) are REQUIRED on inspection reports but N/A on vendor
// assessments/audits, which have no such fields — otherwise the report is
// trapped: the gate blocks submission with nothing to fill.
t_section('Completeness gate — inspection-only checks degrade to N/A');

if (function_exists('idems_completeness_check') && function_exists('idems_type_id_by_code')) {
    $status = function ($code, $key, $overrides = []) {
        $tid = idems_type_id_by_code($code);
        if (!$tid) return 'no-type';
        $cols = "report_type_id,type_code,status,data,created_at,deleted";
        $vals = [$tid, $code, 'DRAFT', '[]', date('c'), 0];
        $ph = "?,?,?,?,?,?";
        foreach ($overrides as $c => $v) { $cols .= ",$c"; $ph .= ",?"; $vals[] = $v; }
        db()->prepare("INSERT INTO report_docs ($cols) VALUES ($ph)")->execute($vals);
        $id = (int) db()->lastInsertId();
        $doc = ops_one("SELECT * FROM report_docs WHERE id=?", [$id]);
        $c = idems_completeness_check($doc);
        db()->prepare("DELETE FROM report_docs WHERE id=?")->execute([$id]);   // clean up
        foreach ($c['checks'] as $ch) if ($ch['key'] === $key) return $ch['status'];
        return 'absent';
    };

    // QAP / ITP never BLOCKS — it is attached in the document list, so with no QAP
    // evidence the check is N/A (never FAIL), on every report type.
    t_eq($status('UAUD_VENDOR', 'qap'), 'NA', 'a vendor audit never blocks on QAP / ITP (N/A)');
    t_eq($status('VASR', 'qap'),        'NA', 'a vendor assessment never blocks on QAP / ITP (N/A)');
    t_eq($status('MGHIR', 'qap'),       'NA', 'an inspection report with no QAP recorded is N/A, not a dead-end FAIL');
    // When a QAP revision IS recorded, the check shows PASS (green), never a block.
    t_eq($status('MGHIR', 'qap', ['qap_rev' => 'R3']), 'PASS', 'a recorded QAP revision shows the check as passed');

    // A vendor assessment has no inspection-scope field -> scope is N/A.
    t_eq($status('VASR', 'scope'), 'NA', 'a vendor assessment does not demand Scope of inspection (N/A)');
}
