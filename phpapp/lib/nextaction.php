<?php
// ============================================================================
//  B3 — "WHAT SHOULD I DO NOW?"
//
//  F-A5-3 said "no shared next-action component". THAT WAS WRONG, and checking
//  it before building is what saved this phase from producing a second one.
//  `.nowband` -- a `.step` headline, a `.next` sentence and an optional `.cta`
//  -- already exists in app.css and is already rendered by NINE record screens:
//  call, job, lead, opportunity, quote, invoice, receipt, controlled document
//  and trace thread.
//
//  What was missing is not the component. It is the RECRUITMENT records:
//  candidate, requirement and hiring request had no such block at all. So this
//  file does not introduce a pattern; it feeds the existing one.
//
//  WHAT THIS IS
//  ------------
//  A PRESENTATION layer. Every answer below is read out of logic that already
//  exists: the module's own status constants, its own allowed-next helper, its
//  own counts, and its own permission gate. Each resolver records WHICH helper
//  answered it, in 'src', so the claim can be checked rather than believed.
//
//  WHAT THIS IS NOT
//  ----------------
//  Not a workflow engine. Not an SLA engine. Not a KPI engine. Not a rules
//  engine. It decides nothing, writes nothing and permits nothing.
//
//  Three rules hold it to that:
//
//   1. NO INVENTED STEP. A resolver may only name a transition its module
//      already allows. Where a module has no allowed-next helper, the resolver
//      mirrors the conditions that module's OWN SCREEN already uses, and says
//      so. Where neither exists, it returns null and nothing is rendered —
//      silence is correct, a guess is not.
//   2. NO GRANTED PERMISSION. 'can' is always the module's own gate, called
//      here and never re-implemented. When it is false the block shows words,
//      never a button. The server remains the boundary; this is a label.
//   3. NO MISLEADING ACTION. When nothing is due, or the wait is on somebody
//      else, the block says so plainly rather than offering something to click.
//
//  It is also deliberately NOT the queue. ops_pending_tasks(), action_centre()
//  and lib/advisor.php already answer "what is waiting for me across the
//  business". This answers "what happens next to the record I am looking at",
//  which no existing code answered.
// ============================================================================

//  A resolver's answer. Anything omitted is simply absent from the block.
//    state  current state, in the words the module itself uses
//    tone   an existing pill class: p-ok | p-warn | p-bad | p-info | p-mut
//    next   the next step, in words; '' when nothing is due
//    route  an EXISTING route the step lives at; '' when the step is elsewhere
//    can    whether THIS user may take it — the module's own gate
//    note   the sentence shown when there is no action to offer
//    src    the helper this answer was read from
function na_answer($a) {
    //  'next' is the SENTENCE; 'cta' is the short label on the door. The call
    //  screen's band already works this way ("Allocate a job →"), and a button
    //  carrying a whole sentence reads badly and wraps on a phone.
    return $a + ['state' => '', 'tone' => 'p-mut', 'next' => '', 'cta' => '', 'route' => '',
                 'can' => false, 'note' => '', 'src' => ''];
}

// ---------------------------------------------------------------------------
//  NOT HERE: the work order / call.
//
//  views/ops/call_detail.php already renders its own .nowband, and it is better
//  than anything generic -- it distinguishes the contracting office from the
//  executing one and says "allocating is the executing office's responsibility"
//  rather than hiding the button. A resolver here would have been a second,
//  worse answer on a screen that already has a good one. Left alone.
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
//  Hiring request — lib/hiringreq.php has statuses and gates but NO
//  allowed-next helper, so inventing a transition table here would be
//  inventing a business rule. Instead this mirrors the conditions the hiring
//  request screen itself already renders (views/ops/hiring_request.php).
// ---------------------------------------------------------------------------
function na_hiring_request($row) {
    if (!defined('HREQ_STATUS')) return null;
    $cur = strtoupper(trim((string) ($row['status'] ?? '')));
    if ($cur === '') return null;
    $labels = HREQ_STATUS;
    $mayDecide = function_exists('hreq_can_decide') && hreq_can_decide();
    $mayEdit   = function_exists('hreq_can_create') && hreq_can_create();
    $id = (int) ($row['id'] ?? 0);

    if ($cur === 'DRAFT')
        return na_answer(['state' => $labels[$cur], 'tone' => 'p-mut',
            'next' => 'Submit it for approval', 'cta' => 'Submit for approval', 'route' => '/hiring-request?id=' . $id . '#na-submit',
            'can' => $mayEdit,
            'note' => $mayEdit ? '' : 'The person who raised this submits it.',
            'src' => 'HREQ_STATUS + hreq_can_create() — same condition as the screen\'s own Submit button']);

    if ($cur === 'SUBMITTED' || $cur === 'UNDER_REVIEW')
        return na_answer(['state' => $labels[$cur], 'tone' => 'p-warn',
            'next' => $mayDecide ? 'Approve or reject it' : '', 'cta' => 'Review it',
            'route' => $mayDecide ? '/hiring-request?id=' . $id . '#na-decide' : '',
            'can' => $mayDecide,
            'note' => $mayDecide ? '' : 'Waiting for an approver. Nothing is recruited until it is approved.',
            'src' => 'HREQ_STATUS + hreq_can_decide() — same condition as the screen\'s own Approve/Reject']);

    if ($cur === 'APPROVED') {
        //  "Remaining" is the screen's own number: quantity minus what is already
        //  being recruited. Read, not recomputed differently.
        $remaining = (int) ($row['__remaining'] ?? -1);
        if ($remaining === 0)
            return na_answer(['state' => $labels[$cur], 'tone' => 'p-ok',
                'note' => 'Approved, and everything asked for is already being recruited.',
                'src' => 'HREQ_STATUS + the screen\'s remaining count']);
        return na_answer(['state' => $labels[$cur], 'tone' => 'p-ok',
            'next' => 'Start recruiting against this request', 'cta' => 'Start recruiting', 'route' => '/hiring-request?id=' . $id . '#na-recruit',
            'can' => $mayEdit,
            'note' => $mayEdit ? '' : 'Approved. Somebody with recruitment rights starts this.',
            'src' => 'HREQ_STATUS + hreq_can_create() — same condition as "Start recruiting"']);
    }
    //  REJECTED / CANCELLED are terminal in HREQ_STATUS.
    return na_answer(['state' => $labels[$cur] ?? $cur, 'tone' => 'p-mut',
        'note' => 'Closed. Nothing further is required.',
        'src' => 'HREQ_STATUS terminal state']);
}

// ---------------------------------------------------------------------------
//  Requisition — lib/reqfulfil.php already counts requested / filled / joined /
//  remaining. Nothing is recounted here.
// ---------------------------------------------------------------------------
function na_requisition($row) {
    if (!function_exists('reqf_counts')) return null;
    $c = reqf_counts($row);
    if (($c['requested'] ?? 0) <= 0) return null;
    $can = function_exists('can') && can('mod.hiring.edit');
    $id  = (int) ($row['id'] ?? 0);

    if (($c['remaining'] ?? 0) > 0)
        return na_answer(['state' => $c['filled'] . ' of ' . $c['requested'] . ' filled',
            'tone' => $c['filled'] > 0 ? 'p-warn' : 'p-info',
            'next' => 'Put candidates forward — ' . (int) $c['remaining'] . ' still to fill', 'cta' => 'Add candidates',
            'route' => '/candidates?requisition_id=' . $id, 'can' => $can,
            'note' => $can ? '' : 'A recruiter puts candidates forward for this.',
            'src' => 'reqf_counts() remaining + can(mod.hiring.edit)']);

    //  Everything is filled. RB-2: filled is NOT joined, and the gap is the
    //  thing worth surfacing — it is exactly the Accepted-vs-Joined distinction
    //  the candidate screen already makes.
    if (($c['joined'] ?? 0) < ($c['filled'] ?? 0))
        return na_answer(['state' => 'All ' . $c['requested'] . ' filled',
            'tone' => 'p-warn',
            'next' => 'Confirm who has actually joined — ' . ((int) $c['filled'] - (int) $c['joined']) . ' not yet marked', 'cta' => 'Record joiners',
            'route' => '/candidates?requisition_id=' . $id, 'can' => $can,
            'note' => $can ? '' : 'A recruiter records the joining date.',
            'src' => 'reqf_counts() filled vs joined (RB-2)']);

    return na_answer(['state' => 'Filled and joined', 'tone' => 'p-ok',
        'note' => 'Nothing required right now.',
        'src' => 'reqf_counts() filled == joined']);
}

// ---------------------------------------------------------------------------
//  Candidate — lib/recruitpipe.php owns the configured pipeline and the stage
//  a candidate sits on. The next stage is the pipeline's, not this file's.
// ---------------------------------------------------------------------------
function na_candidate($row) {
    if (!function_exists('recruitpipe_cand_state')) return null;
    [$pipe, $eff, $idx] = recruitpipe_cand_state($row);
    $stage = strtoupper(trim((string) ($row['stage'] ?? '')));
    $can = function_exists('is_coordinator_level') && is_coordinator_level();
    $id  = (int) ($row['id'] ?? 0);

    //  A terminal legacy stage wins over the pipeline — recruitpipe itself
    //  treats these as terminal (recruitpipe_legacy_terminal()).
    $terminal = function_exists('recruitpipe_legacy_terminal') ? recruitpipe_legacy_terminal() : [];
    if ($stage === 'ACCEPTED') {
        $joined = trim((string) ($row['joined_at'] ?? '')) !== '';
        if ($joined)
            return na_answer(['state' => 'Joined', 'tone' => 'p-ok',
                'note' => 'Nothing required right now.', 'src' => 'candidates.joined_at is set']);
        return na_answer(['state' => 'Accepted / hired', 'tone' => 'p-warn',
            'next' => 'Mark as joined once they actually arrive', 'cta' => 'Mark as joined',
            'route' => '/candidate?id=' . $id . '#na-joined', 'can' => $can,
            'note' => $can ? '' : 'A recruiter records the joining date.',
            'src' => 'candidates.stage=ACCEPTED with no joined_at (RB-2)']);
    }
    if (in_array($stage, $terminal, true))
        return na_answer(['state' => ucfirst(strtolower(str_replace('_', ' ', $stage))), 'tone' => 'p-mut',
            'note' => 'Closed. Nothing further is required.',
            'src' => 'recruitpipe_legacy_terminal()']);

    if (!$pipe || !$eff) return null;                       // no pipeline resolved — say nothing
    $here = $eff[$idx] ?? null;
    $nextStage = $eff[$idx + 1] ?? null;
    if (!$nextStage)
        return na_answer(['state' => (string) ($here['name'] ?? ''), 'tone' => 'p-info',
            'note' => 'Last stage of the pipeline. Nothing further is required here.',
            'src' => 'recruitpipe_cand_state() — no stage after this one']);

    return na_answer(['state' => (string) ($here['name'] ?? ''), 'tone' => 'p-info',
        'next' => 'Move to ' . (string) $nextStage['name'], 'cta' => 'Move to ' . (string) $nextStage['name'],
        'route' => '/candidate?id=' . $id, 'can' => $can,
        'note' => $can ? '' : 'A coordinator moves a candidate along.',
        'src' => 'recruitpipe_cand_state() next effective stage + is_coordinator_level()']);
}

// ---------------------------------------------------------------------------
//  The registry. An entity with no resolver simply has no block.
// ---------------------------------------------------------------------------
function na_resolvers() {
    return [
        'hiring_request'  => 'na_hiring_request',
        'requisition'     => 'na_requisition',
        'candidate'       => 'na_candidate',
    ];
}

//  The one entry point. Never throws: a next-action block is an aid, and a
//  failure in it must not take down the record screen behind it.
function na_state($entity, $row) {
    if (!is_array($row) || !$row) return null;
    $fn = na_resolvers()[$entity] ?? '';
    if (!$fn || !function_exists($fn)) return null;
    try { $a = $fn($row); } catch (Throwable $e) { return null; }
    if (!is_array($a)) return null;
    if (trim((string) $a['state']) === '' && trim((string) $a['next']) === ''
        && trim((string) $a['note']) === '') return null;
    return $a;
}

//  Render, as the `.nowband` that nine other record screens already use. The
//  only new thing B3 contributes is the ANSWER; the markup, the CSS and the
//  visual language were all already here.
function na_html($entity, $row) {
    $a = na_state($entity, $row);
    if (!$a) return '';
    $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
    //  .step = where this record stands. .next = the sentence. .cta = the door,
    //  and ONLY when the module's own gate already said yes.
    $step = $a['state'] !== '' ? $a['state'] : 'Where this stands';
    $h  = '<div class="nowband">';
    $h .= '<div class="step">' . $e($step) . '</div>';
    if ($a['next'] !== '') {
        $h .= '<p class="next"><b>Next:</b> ' . $e($a['next']) . '</p>';
        if ($a['can'] && $a['route'] !== '')
            $h .= '<div class="cta"><a class="btn small" href="' . $e($a['route']) . '">'
                . $e($a['cta'] !== '' ? $a['cta'] : $a['next']) . ' &rarr;</a></div>';
        elseif (!$a['can'] && $a['note'] !== '')
            $h .= '<p class="next muted">' . $e($a['note']) . '</p>';
    } else {
        $h .= '<p class="next">' . $e($a['note'] !== '' ? $a['note'] : 'Nothing required right now.') . '</p>';
    }
    $h .= '</div>';
    return $h;
}
