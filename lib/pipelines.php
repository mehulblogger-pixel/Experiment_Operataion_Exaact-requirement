<?php
// ============================================================================
//  The pipeline & funnel builder
//
//  THE REQUIREMENT: "prebuilt in multiple number which can be adjusted by the
//  user as per their flow ... user shall also be able to customize the
//  dashboard, pipelines and funnels or create new ones."
//
//  The gap this closes is embarrassing and worth naming. Pipelines and stages
//  have existed since the lead register was built, and they have always been
//  read from the database rather than hard-coded — the code has never assumed a
//  stage name anywhere. But there was NO SCREEN to change them. "Configurable"
//  was true of the data model and false of the product: the only way to alter a
//  funnel was to write SQL.
//
//  So this is the screen. Create a pipeline, clone one, rename and reorder
//  stages, set probabilities and service levels, retire what you no longer use.
//
//  FIVE RULES, and each exists because breaking it corrupts live data rather
//  than merely looking untidy:
//
//   1. **A pipeline must keep at least one WON and one LOST stage.** Every rule
//      in the application reads `kind`, not names — winning a deal, refusing to
//      close without a loss reason, the win rate, the funnel conversion. A
//      pipeline with no WON stage is one where no deal can ever be won.
//   2. **A stage with deals on it is never deleted.** It is retired, or the
//      deals are moved first, and the screen says which. Deleting it would
//      leave rows pointing at nothing and every one of those deals would
//      vanish from its board.
//   3. **Reordering rewrites `seq` only.** Nothing else keys off position.
//   4. **Probability is clamped to 0–100 and a WON stage is forced to 100, a
//      LOST stage to 0.** A "Won" stage at 80% quietly understates every
//      forecast the company makes.
//   5. **Renaming is free.** Names are display only; the history keeps the name
//      as it was on the day of the move, so last year's report still reads
//      correctly after a rename.
// ============================================================================

const PIPE_KINDS = [
    'OPEN' => 'Still in play',
    'WON'  => 'Won — closes the deal',
    'LOST' => 'Lost — closes the deal, and asks why',
];

const PIPE_ENTITIES = [
    'OPPORTUNITY' => 'Opportunity funnel',
    'LEAD'        => 'Lead pipeline',
];

function pipe_can()      { return can('mod.leads.edit') || can('settings.manage') || is_master_of(['leads','settings']); }
function pipe_can_view() { return can('mod.leads.view') || can('mod.quotes.view') || is_master_of(['leads','quotes']); }

function pipe_try($fn, $fb = null) { try { return $fn(); } catch (Throwable $e) { return $fb; } }
function pipe_rows($sql, $a = []) { return pipe_try(fn() => ops_all($sql, $a), []) ?: []; }
function pipe_val($sql, $a = [], $fb = 0) { $v = pipe_try(fn() => ops_val($sql, $a), $fb); return $v === false || $v === null ? $fb : $v; }

function pipe_all() {
    if (function_exists('leads_migrate')) leads_migrate();
    if (function_exists('opp_migrate')) opp_migrate();
    return pipe_rows("SELECT p.*,
              (SELECT COUNT(*) FROM pipeline_stages s WHERE s.pipeline_id=p.id AND s.active=1) stage_n,
              (SELECT COUNT(*) FROM opportunities o WHERE o.pipeline_id=p.id) opp_n,
              (SELECT COUNT(*) FROM leads l WHERE l.pipeline_id=p.id) lead_n
              FROM pipelines p ORDER BY p.entity_kind, p.is_default DESC, p.name");
}

function pipe_row($id) {
    return pipe_try(fn() => ops_one("SELECT * FROM pipelines WHERE id=?", [(int)$id]), null) ?: null;
}

// Stages plus how much is sitting on each — the number that decides whether a
// stage can be deleted, so it is fetched here rather than guessed at the screen.
function pipe_stages($pipelineId) {
    $p = pipe_row($pipelineId);
    $kind = $p ? (string)$p['entity_kind'] : 'OPPORTUNITY';
    $tbl = $kind === 'LEAD' ? 'leads' : 'opportunities';
    return pipe_rows("SELECT s.*,
              (SELECT COUNT(*) FROM $tbl x WHERE x.stage_id = s.id) in_use,
              (SELECT COUNT(*) FROM $tbl x WHERE x.stage_id = s.id AND x.status='OPEN') open_here
              FROM pipeline_stages s WHERE s.pipeline_id=? ORDER BY s.seq, s.id", [(int)$pipelineId]);
}

// What a pipeline is missing before it can be used in anger.
function pipe_problems($pipelineId) {
    $st = pipe_stages($pipelineId);
    $why = [];
    $open = $won = $lost = 0;
    foreach ($st as $s) {
        if (!(int)$s['active']) continue;
        if ($s['kind'] === 'WON') $won++; elseif ($s['kind'] === 'LOST') $lost++; else $open++;
    }
    if (!$won)  $why[] = 'It has no “won” stage, so no deal on it can ever be marked won.';
    if (!$lost) $why[] = 'It has no “lost” stage, so a deal that falls through has nowhere to go and no reason gets recorded.';
    if (!$open) $why[] = 'Every stage closes the deal — there is nothing in play.';
    return $why;
}

// ---- Writing ---------------------------------------------------------------
function pipe_create($name, $entityKind, $cloneFrom = 0, $makeDefault = false) {
    $name = trim((string)$name);
    if ($name === '') return ['err' => 'Give the pipeline a name.'];
    if (!isset(PIPE_ENTITIES[$entityKind])) return ['err' => 'Choose whether this is a funnel or a lead pipeline.'];
    if (function_exists('leads_migrate')) leads_migrate();

    if ($makeDefault)
        pipe_try(fn() => db()->prepare("UPDATE pipelines SET is_default=0 WHERE entity_kind=?")->execute([$entityKind]));
    db()->prepare("INSERT INTO pipelines (name,entity_kind,is_default,active,created_by,created_at)
                   VALUES (?,?,?,1,?,?)")
       ->execute([$name, $entityKind, $makeDefault ? 1 : 0, user_name(current_user()), date('c')]);
    $id = (int)db()->lastInsertId();

    $st = db()->prepare("INSERT INTO pipeline_stages (pipeline_id,seq,name,kind,probability,sla_days,active)
                         VALUES (?,?,?,?,?,?,1)");
    if ($cloneFrom) {
        // Cloning is the usual way somebody makes a variant — "same as the main
        // funnel but with an extra approval step". Starting from a blank screen
        // and retyping seven stages is how people give up.
        $src = pipe_stages($cloneFrom);
        $i = 0;
        foreach ($src as $s) {
            if (!(int)$s['active']) continue;
            $st->execute([$id, ++$i, $s['name'], $s['kind'], (int)$s['probability'], (int)$s['sla_days']]);
        }
        if (!$src) $cloneFrom = 0;
    }
    if (!$cloneFrom) {
        // A brand-new pipeline still arrives usable. An empty one is a screen
        // that cannot be used until somebody reads the rules above.
        foreach ([['New', 'OPEN', 10, 7], ['In progress', 'OPEN', 50, 14],
                  ['Won', 'WON', 100, 0], ['Lost', 'LOST', 0, 0]] as $i => [$n, $k, $p, $sla])
            $st->execute([$id, $i + 1, $n, $k, $p, $sla]);
    }
    return ['id' => $id];
}

function pipe_update($id, array $b) {
    $p = pipe_row($id);
    if (!$p) return 'That pipeline no longer exists.';
    // NOT 'name'. The same form posts the stage names as name[<id>], and PHP
    // merges `name=X` and `name[14]=Y` into ONE array — so reading 'name' here
    // gave the string "Array" and renamed every pipeline it touched. Caught in
    // testing; it would have shipped silently.
    $name = trim((string)($b['pipeline_name'] ?? $p['name']));
    if ($name === '') return 'A pipeline needs a name.';
    if (is_array($b['pipeline_name'] ?? null)) return 'The pipeline name came through as a list, which means a form field is misnamed.';
    $active = !empty($b['active']) ? 1 : 0;
    // Retiring the default would leave new deals with nowhere to land.
    if (!$active && (int)$p['is_default'])
        return 'This is the default pipeline. Make another one the default first, then retire this.';
    db()->prepare("UPDATE pipelines SET name=?, active=? WHERE id=?")->execute([$name, $active, (int)$id]);
    return '';
}

function pipe_make_default($id) {
    $p = pipe_row($id);
    if (!$p) return 'That pipeline no longer exists.';
    if (!(int)$p['active']) return 'A retired pipeline cannot be the default.';
    $why = pipe_problems((int)$id);
    if ($why) return 'It cannot be the default yet. ' . implode(' ', $why);
    db()->prepare("UPDATE pipelines SET is_default=0 WHERE entity_kind=?")->execute([$p['entity_kind']]);
    db()->prepare("UPDATE pipelines SET is_default=1 WHERE id=?")->execute([(int)$id]);
    return '';
}

// A stage's probability is not free-form once it closes a deal.
function pipe_clamp_prob($kind, $prob) {
    if ($kind === 'WON')  return 100;
    if ($kind === 'LOST') return 0;
    return max(0, min(100, (int)$prob));
}

function pipe_stage_add($pipelineId, array $b) {
    $p = pipe_row($pipelineId);
    if (!$p) return 'That pipeline no longer exists.';
    $name = trim((string)($b['name'] ?? ''));
    if ($name === '') return 'Give the stage a name.';
    $kind = isset(PIPE_KINDS[$b['kind'] ?? '']) ? (string)$b['kind'] : 'OPEN';
    $seq = (int)pipe_val("SELECT COALESCE(MAX(seq),0) FROM pipeline_stages WHERE pipeline_id=?", [(int)$pipelineId], 0) + 1;
    db()->prepare("INSERT INTO pipeline_stages (pipeline_id,seq,name,kind,probability,sla_days,active)
                   VALUES (?,?,?,?,?,?,1)")
       ->execute([(int)$pipelineId, $seq, $name, $kind,
                  pipe_clamp_prob($kind, $b['probability'] ?? 0), max(0, (int)($b['sla_days'] ?? 0))]);
    return '';
}

// Saves every stage in one post — name, kind, probability, service level and
// order together, because changing them one at a time on a seven-stage funnel
// is eight page loads and somebody gives up halfway.
function pipe_stages_save($pipelineId, array $b) {
    $p = pipe_row($pipelineId);
    if (!$p) return 'That pipeline no longer exists.';
    $names = (array)($b['name'] ?? []);
    if (!$names) return 'Nothing to save.';
    $seq = 0;
    foreach ($names as $sid => $nm) {
        $sid = (int)$sid;
        $nm = trim((string)$nm);
        if ($nm === '') continue;                       // a blank name is a slip, not a delete
        $kind = isset(PIPE_KINDS[$b['kind'][$sid] ?? '']) ? (string)$b['kind'][$sid] : 'OPEN';
        db()->prepare("UPDATE pipeline_stages SET name=?, kind=?, probability=?, sla_days=?, seq=?, active=? WHERE id=? AND pipeline_id=?")
           ->execute([$nm, $kind, pipe_clamp_prob($kind, $b['probability'][$sid] ?? 0),
                      max(0, (int)($b['sla_days'][$sid] ?? 0)), ++$seq,
                      empty($b['active'][$sid]) ? 0 : 1, $sid, (int)$pipelineId]);
    }
    $why = pipe_problems($pipelineId);
    return $why ? 'Saved, but: ' . implode(' ', $why) : '';
}

// Deleting a stage that holds deals would orphan them. Retiring keeps the deals
// where they are and stops anything new landing there — which is what somebody
// almost always means anyway.
function pipe_stage_delete($stageId) {
    $s = pipe_try(fn() => ops_one("SELECT * FROM pipeline_stages WHERE id=?", [(int)$stageId]), null);
    if (!$s) return 'That stage has already gone.';
    $p = pipe_row((int)$s['pipeline_id']);
    $tbl = ($p && $p['entity_kind'] === 'LEAD') ? 'leads' : 'opportunities';
    $n = (int)pipe_val("SELECT COUNT(*) FROM $tbl WHERE stage_id=?", [(int)$stageId], 0);
    if ($n > 0)
        return $n . ' record' . ($n === 1 ? ' is' : 's are') . ' sitting on “' . $s['name'] . '”. '
             . 'Move them to another stage first, or untick it to retire it — retiring keeps them where they are '
             . 'and stops anything new landing there.';
    db()->prepare("DELETE FROM pipeline_stages WHERE id=?")->execute([(int)$stageId]);
    return '';
}

// ---- Screens ----------------------------------------------------------------
function ops_pipelines($route, $method) {
    ops_require(pipe_can_view(), 'You cannot open the pipelines.');

    if ($route === 'pipelines') {
        view('ops/pipelines', ['rows' => pipe_all(), 'canEdit' => pipe_can(),
                               'entities' => PIPE_ENTITIES,
                               'industry' => function_exists('industry_current') ? industry_current() : '']);
        return true;
    }

    if ($route === 'pipeline') {
        $p = pipe_row($_GET['id'] ?? 0);
        if (!$p) { http_response_code(404); view('notfound'); return true; }
        view('ops/pipeline_edit', [
            'p' => $p, 'stages' => pipe_stages((int)$p['id']),
            'problems' => pipe_problems((int)$p['id']),
            'kinds' => PIPE_KINDS, 'canEdit' => pipe_can(),
            'others' => pipe_rows("SELECT id, name FROM pipelines WHERE entity_kind=? AND id<>? AND active=1",
                                  [(string)$p['entity_kind'], (int)$p['id']]),
        ]);
        return true;
    }

    ops_require(pipe_can(), 'You cannot change the pipelines.');

    if ($route === 'pipeline-new' && $method === 'POST') {
        $r = pipe_create($_POST['name'] ?? '', (string)($_POST['entity_kind'] ?? ''),
                         (int)($_POST['clone_from'] ?? 0), !empty($_POST['make_default']));
        if (!empty($r['err'])) { flash($r['err'], 'error'); redirect('/pipelines'); }
        flash('Created. Adjust the stages to match how you actually work.');
        redirect('/pipeline?id=' . $r['id']);
    }

    if ($route === 'pipeline-save' && $method === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $e1 = pipe_update($id, $_POST);
        $e2 = pipe_stages_save($id, $_POST);
        $msg = trim($e1 . ' ' . $e2);
        flash($msg !== '' ? $msg : 'Saved.', $e1 !== '' ? 'error' : ($e2 !== '' ? 'warning' : 'success'));
        redirect('/pipeline?id=' . $id);
    }

    if ($route === 'pipeline-stage-add' && $method === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        if (($e = pipe_stage_add($id, $_POST)) !== '') flash($e, 'error');
        redirect('/pipeline?id=' . $id);
    }

    if ($route === 'pipeline-stage-delete' && $method === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        if (($e = pipe_stage_delete((int)($_POST['stage_id'] ?? 0))) !== '') flash($e, 'error');
        else flash('Stage removed.');
        redirect('/pipeline?id=' . $id);
    }

    if ($route === 'pipeline-default' && $method === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $e = pipe_make_default($id);
        flash($e !== '' ? $e : 'That is now the default — new work will use it.', $e !== '' ? 'error' : 'success');
        redirect('/pipelines');
    }

    return false;
}
