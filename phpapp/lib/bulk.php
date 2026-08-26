<?php
// Phase 2 §48 — a server-side PREVIEW (dry-run) for bulk actions, over the EXISTING bulk framework.
//
// The bulk framework (datatable.php) and its one adopter (leads_bulk) run CONFIRM → EXECUTE → AUDIT:
// the confirm step states a raw count, then the action runs and reports how many it actually touched
// and how many it quietly skipped. Nobody could see BEFORE committing which rows would be skipped and
// why. This adds that pre-flight — a pure partition of the ticked ids into "will apply" and
// "will skip (reason)" — computed from the SAME eligibility rule the executor uses, so the preview and
// the result can never disagree. It reads; it changes nothing.

// Partition ids using a classifier that returns ['ok'=>bool, 'reason'=>string] for each id.
// Returns apply[] (ids), skip[] ([id,reason]), and the three counts.
function bulk_plan(array $ids, callable $classify) {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    $apply = []; $skip = [];
    foreach ($ids as $id) {
        $v = $classify($id);
        if (!empty($v['ok'])) $apply[] = $id;
        else $skip[] = ['id' => $id, 'reason' => (string)($v['reason'] ?? 'not eligible')];
    }
    return ['apply' => $apply, 'skip' => $skip,
            'apply_count' => count($apply), 'skip_count' => count($skip), 'total' => count($ids)];
}

// A human sentence for the confirm step. e.g. "12 leads will be marked lost. 3 will be left alone
// (already closed)." Groups the skips by reason so the message stays short.
function bulk_plan_summary($plan, $verb = 'updated', $unit = 'item') {
    $n = (int)($plan['apply_count'] ?? 0);
    $msg = $n . ' ' . $unit . ($n === 1 ? '' : 's') . ' will be ' . $verb . '.';
    if (!empty($plan['skip'])) {
        $byReason = [];
        foreach ($plan['skip'] as $s) { $r = (string)$s['reason']; $byReason[$r] = ($byReason[$r] ?? 0) + 1; }
        $parts = [];
        foreach ($byReason as $reason => $c) $parts[] = $c . ' (' . $reason . ')';
        $sk = (int)$plan['skip_count'];
        $msg .= ' ' . $sk . ' will be left alone: ' . implode(', ', $parts) . '.';
    }
    return $msg;
}
