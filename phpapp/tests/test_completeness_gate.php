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

    // A standalone vendor audit has no QAP/ITP field and no linked job -> N/A.
    t_eq($status('UAUD_VENDOR', 'qap'), 'NA', 'a vendor audit does not demand QAP / ITP (N/A, not a dead-end)');
    // A vendor assessment has neither an inspection-scope field nor QAP -> both N/A.
    t_eq($status('VASR', 'scope'), 'NA', 'a vendor assessment does not demand Scope of inspection (N/A)');
    t_eq($status('VASR', 'qap'),   'NA', 'a vendor assessment does not demand QAP / ITP (N/A)');

    // But when the report IS linked to an inspection job, QAP / ITP becomes a real
    // requirement again (proving we did not simply switch the check off).
    $q = $status('UAUD_VENDOR', 'qap', ['job_id' => 999999]);
    t_ok($q === 'FAIL' || $q === 'PASS', 'with a linked inspection job, QAP / ITP is required again (not N/A)');
}
