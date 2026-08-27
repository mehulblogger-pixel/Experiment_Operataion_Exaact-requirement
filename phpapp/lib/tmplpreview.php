<?php
// Phase 3 §8 — report-template persona preview.
//
// When someone designs a report type in the builder, they see a flat list of sections and fields. What
// they cannot see is what the FINISHED report looks like to each audience: which fields the recipient
// (the client/vendor who receives the report) actually sees, which are internal-only, which appear only
// under a condition, and which carry assessment scoring. A field the author believes clients see may in
// fact be marked hidden; scoring machinery may leak into a recipient's view. This shows both sides
// before the template is used, reusing the §72 visibility vocabulary. Read-only; changes no template.

// Classify one field by persona, from the columns the template already stores.
//   RECIPIENT  — on the rendered report the client/vendor receives (not hidden)
//   INTERNAL   — hidden fields (calc helpers, working notes) — staff only
// Plus flags: conditional (shown only when a rule holds) and scored (carries assessment weight/score).
function template_field_persona($f) {
    $f = (array)$f;
    $hidden = (int)($f['hidden'] ?? 0) === 1;
    $scored = ((float)($f['weight'] ?? 0) > 0) || ((float)($f['max_score'] ?? 0) > 0);
    return [
        'persona'     => $hidden ? 'INTERNAL' : 'RECIPIENT',
        'conditional' => trim((string)($f['cond_field'] ?? '')) !== '',
        'scored'      => $scored,
    ];
}

// The whole template, laid out by section with each field's persona — the data behind the preview.
function template_persona_preview($typeId) {
    $typeId = (int)$typeId;
    $type = ops_one("SELECT * FROM report_types WHERE id=?", [$typeId]);
    if (!$type) return null;
    $sections = ops_all("SELECT * FROM report_sections WHERE report_type_id=? ORDER BY sort_order, id", [$typeId]) ?: [];
    $fields   = ops_all("SELECT * FROM report_fields WHERE report_type_id=? ORDER BY section_id, sort_order, id", [$typeId]) ?: [];
    $bySec = [];
    foreach ($fields as $f) $bySec[(int)$f['section_id']][] = $f;

    $counts = ['recipient' => 0, 'internal' => 0, 'conditional' => 0, 'scored' => 0, 'fields' => 0];
    $out = [];
    foreach ($sections as $s) {
        $sid = (int)$s['id'];
        $fs = [];
        foreach ($bySec[$sid] ?? [] as $f) {
            $p = template_field_persona($f);
            $fs[] = ['label' => (string)($f['label'] ?: $f['fkey']), 'ftype' => (string)$f['ftype']] + $p;
            $counts['fields']++;
            $counts[$p['persona'] === 'INTERNAL' ? 'internal' : 'recipient']++;
            if ($p['conditional']) $counts['conditional']++;
            if ($p['scored'])      $counts['scored']++;
        }
        $out[] = [
            'id' => $sid, 'title' => (string)($s['title'] ?: 'Untitled section'),
            'conditional' => trim((string)($s['cond_field'] ?? '')) !== '',
            'recipient_fields' => count(array_filter($fs, fn($x) => $x['persona'] === 'RECIPIENT')),
            'fields' => $fs,
        ];
    }
    return ['type' => $type, 'sections' => $out, 'counts' => $counts];
}

// The /report-preview screen.
function ops_template_preview($method) {
    ops_require(is_master() || can('idems.type.manage') || can('master.manage'), 'You cannot preview report forms.');
    $typeId = (int)($_GET['type'] ?? 0);
    $pv = $typeId ? template_persona_preview($typeId) : null;
    if (!$pv) { flash('Choose a report type to preview.', 'error'); redirect('/report-types'); }
    view('ops/idems/template_preview', ['pv' => $pv]);
    return true;
}
