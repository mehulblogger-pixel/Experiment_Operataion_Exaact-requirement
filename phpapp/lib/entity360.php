<?php
// Phase 3 §49 — the uniform Entity-360 shell.
//
// Client-360 and vendor-360 are rich, bespoke screens. Most other entities (a job, an NCR, a corrective
// action, a candidate) have a detail page but no consistent "whole story" view. Rather than rewrite the
// bespoke 360s (that would be destructive) or hand-roll a timeline+tasks section onto every detail page,
// this is ONE shell that assembles a consistent panel set for any registered entity, composing the
// cross-cutting engines already built: tasks (§26), the activity spine (§17), quality case (§39), the
// person matcher (§23/24) and the money stream (§27). Read-only; it reads through those engines and
// adds no data of its own.

// kind → [table, title expr, panels]. Only kinds the activity spine already knows are listed, so their
// history renders. Panels: tasks · history · quality (NCR/CAPA/complaint) · party (person) · money.
function entity_360_registry() {
    return [
        'JOB'       => ['jobs',            'job_code',                     ['tasks', 'history']],
        'NCR'       => ['nonconformities', 'ref',                          ['quality', 'tasks', 'history']],
        'CAPA'      => ['capa',            'ref',                          ['quality', 'tasks', 'history']],
        'COMPLAINT' => ['complaints',      'ref',                          ['quality', 'tasks', 'history']],
        'CANDIDATE' => ['candidates',      'first_name',                   ['party', 'tasks', 'history']],  // single column — portable across SQLite/MySQL
        'INSPECTOR' => ['inspectors',      'name',                         ['credential', 'party', 'tasks', 'history']],  // Slice P1 — Credential Vault
    ];
}

// Who may open an Entity-360. Conservative and permission-safe: management level (master / coordinator),
// which is the audience for a cross-cutting summary. No new permission is introduced.
function entity_360_can($kind = '') {
    return (function_exists('is_master') && is_master())
        || (function_exists('is_coordinator_level') && is_coordinator_level());
}

// Resolve one entity: its kind, id, display title, back-link and the panels that apply. Null if the kind
// is unregistered or the record does not exist (fail-closed).
function entity_360_load($kind, $id) {
    $kind = strtoupper(trim((string)$kind)); $id = (int)$id;
    $reg = entity_360_registry();
    if (!isset($reg[$kind]) || !$id) return null;
    [$table, $titleExpr, $panels] = $reg[$kind];
    $title = '';
    try { $title = (string) ops_val("SELECT $titleExpr FROM $table WHERE id=?", [$id]); } catch (Throwable $e) { return null; }
    if ($title === '' && !ops_val("SELECT id FROM $table WHERE id=?", [$id])) return null;   // no such record
    $routes = function_exists('act_entities') ? act_entities() : (defined('ACT_ENTITIES') ? ACT_ENTITIES : []);
    $back = ($routes[$kind][1] ?? '') . $id;
    $label = $routes[$kind][0] ?? ucfirst(strtolower($kind));
    return ['kind' => $kind, 'id' => $id, 'title' => trim($title) ?: ('#' . $id), 'label' => $label,
            'back' => $back, 'panels' => $panels];
}

// Render the applicable panels, in a consistent order, reusing the existing render helpers. Each is
// guarded so a missing engine simply omits its panel.
function entity_360_render_panels($kind, $id, array $panels) {
    foreach ($panels as $p) {
        if ($p === 'credential' && function_exists('credential_vault_render')) credential_vault_render($kind, $id, ['editable' => function_exists('competence_can_authorise') && competence_can_authorise()]);
        elseif ($p === 'quality' && function_exists('quality_case_render'))   quality_case_render($kind, $id);
        elseif ($p === 'party' && function_exists('party_render_also'))      party_render_also($kind, $id);
        elseif ($p === 'money' && function_exists('financial_events_render')) financial_events_render(['partner_id' => (int)$id], 'Money timeline');
        elseif ($p === 'tasks' && function_exists('task_render_for_entity')) task_render_for_entity($kind, $id, 'Tasks');
        elseif ($p === 'history' && function_exists('act_render_timeline'))  act_render_timeline($kind, $id, 'History');
    }
}

function ops_entity_360($method) {
    ops_require(entity_360_can(), 'You cannot open the 360 view.');
    $e = entity_360_load($_GET['kind'] ?? '', (int)($_GET['id'] ?? 0));
    if (!$e) { flash('That record could not be opened in the 360 view.', 'error'); redirect('/'); }
    view('ops/entity360', ['e' => $e]);
    return true;
}

// A small "360 view" link a detail page can drop in to reach the uniform shell for its record.
function entity_360_link($kind, $id, $label = '360 view') {
    if (!entity_360_can($kind)) return '';
    return '<a class="btn small secondary" href="/entity-360?kind=' . urlencode((string)$kind) . '&id=' . (int)$id . '">🧭 ' . htmlspecialchars($label, ENT_QUOTES) . '</a>';
}
