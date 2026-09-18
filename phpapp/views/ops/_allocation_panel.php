<?php
// ============================================================================
//  PHASE 4 — WHERE THIS REQUIREMENT'S PEOPLE ARE COMING FROM
//
//  Zero-Training gate: the panel answers one sentence a coordinator already says
//  out loud — "twenty people: ten from our own payroll, five from the agency,
//  five still to place". One bar, one row per source, one box to add another.
//
//  Nothing on this panel is a control. Every button posts to
//  /requisition-allocations, which re-decides entitlement, permission, scope,
//  executability and the ceiling from scratch.
// ============================================================================
if (!function_exists('rful_summary')) return;
$p4 = rful_summary((int) $req['id']);
if ($p4['authorised'] < 1 && !$p4['sources']) return;
$p4may   = function_exists('is_coordinator_level') && is_coordinator_level();
$p4live  = array_values(array_filter($p4['sources'], fn($s) => in_array($s['status'], RFUL_LIVE_STATES, true)));
$p4done  = array_values(array_filter($p4['sources'], fn($s) => in_array($s['status'], RFUL_CLOSED_STATES, true)));
$p4names = function_exists('rful_sources') ? rful_sources() : [];
$p4why   = function_exists('rful_exec_block') ? rful_exec_block((int) $req['id']) : '';
$p4pct   = fn($n) => $p4['authorised'] > 0 ? max(0, min(100, round($n * 100 / $p4['authorised']))) : 0;
?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0;display:flex;flex-wrap:wrap;gap:8px;align-items:baseline">
    Where these people come from
    <span class="muted" style="font-weight:400;font-size:12px">
      <?= (int) $p4['authorised'] ?> approved ·
      <?= (int) $p4['allocated'] ?> promised to <?= count($p4live) ?> source<?= count($p4live) === 1 ? '' : 's' ?> ·
      <?= (int) $p4['unallocated'] ?> not yet sourced
    </span>
  </h3>

  <?php // Somebody found directly fills an approved position too, so their seat is
        // spent. When that pushes the promises past the approved headcount, the
        // panel SAYS so — it never quietly cuts a source's promise for you. ?>
  <?php if ($p4['over_committed'] > 0): ?>
    <div class="panel" style="border-left:3px solid #d97706;background:#fffdf5;padding:9px 12px;margin:0 0 10px">
      <strong><?= (int) $p4['over_committed'] ?> too many.</strong>
      <?= (int) $p4['direct_fulfilled'] ?> <?= $p4['direct_fulfilled'] === 1 ? 'person has' : 'people have' ?>
      joined without a named source, so <?= (int) $p4['direct_fulfilled'] ?>
      of the promised seats <?= $p4['direct_fulfilled'] === 1 ? 'is' : 'are' ?> already filled.
      Reduce or give back one of the sources below by <?= (int) $p4['over_committed'] ?>.
      <span class="muted">Nobody has been removed and no promise has been changed for you.</span>
    </div>
  <?php endif; ?>

  <?php // One bar: arrived / promised-but-not-arrived / nothing planned yet. ?>
  <div style="display:flex;height:12px;border-radius:7px;overflow:hidden;background:#eef1f4;margin:2px 0 6px">
    <div title="Already joined" style="width:<?= $p4pct($p4['fulfilled']) ?>%;background:#1a7f37"></div>
    <div title="Promised, not yet arrived"
         style="width:<?= $p4pct(max(0, $p4['allocated'] - $p4['fulfilled'])) ?>%;background:#5a9bd5"></div>
  </div>
  <div class="muted" style="font-size:11.5px;margin-bottom:12px">
    <span style="color:#1a7f37">■</span> <?= (int) $p4['fulfilled'] ?> joined ·
    <span style="color:#5a9bd5">■</span> <?= max(0, (int) $p4['allocated'] - (int) $p4['fulfilled']) ?> promised, still to arrive ·
    <span style="color:#c9ced4">■</span> <?= (int) $p4['unallocated'] ?> still to be sourced
    <?php if ($p4['direct_fulfilled'] > 0): ?>
      · of those who joined, <?= (int) $p4['direct_fulfilled'] ?> came without a named source
    <?php endif; ?>
  </div>

  <?php if ($p4live || $p4done): ?>
  <table class="table" style="margin-bottom:10px">
    <thead><tr>
      <th>Source</th><th style="text-align:right">Promised</th><th style="text-align:right">Arrived</th>
      <th style="text-align:right">Still owed</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach (array_merge($p4live, $p4done) as $s):
      $closed = in_array($s['status'], RFUL_CLOSED_STATES, true); ?>
      <tr<?= $closed ? ' class="muted"' : '' ?>>
        <td>
          <strong><?= e($p4names[$s['source']] ?? $s['source']) ?></strong>
          <?php if ($s['label'] !== ''): ?><span class="muted"> — <?= e($s['label']) ?></span><?php endif; ?>
          <?php if ($closed): ?>
            <span class="badge p-mut"><?= $s['status'] === 'RELEASED' ? 'Seats returned' : 'Cancelled' ?></span>
          <?php elseif ($s['status'] === 'FULFILLED'): ?>
            <span class="badge p-ok">Complete</span>
          <?php endif; ?>
        </td>
        <td style="text-align:right"><?= (int) $s['allocated'] ?></td>
        <td style="text-align:right"><?= (int) $s['fulfilled'] ?></td>
        <td style="text-align:right"><?= $closed ? '—' : (int) $s['remaining'] ?></td>
        <td style="text-align:right;white-space:nowrap">
          <?php if ($p4may && !$closed && $p4why === ''): ?>
            <form method="post" action="/requisition-allocations?id=<?= (int) $req['id'] ?>"
                  style="display:inline-flex;gap:5px;align-items:center">
              <input type="hidden" name="do" value="reallocate">
              <input type="hidden" name="allocation_id" value="<?= (int) $s['id'] ?>">
              <input type="hidden" name="expect" value="<?= (int) $s['allocated'] ?>">
              <input class="form-control" type="number" name="qty" min="1" value="<?= (int) $s['allocated'] ?>"
                     style="width:68px" aria-label="People promised by this source">
              <button class="btn small secondary" type="submit">Change</button>
            </form>
            <form method="post" action="/requisition-allocations?id=<?= (int) $req['id'] ?>" style="display:inline"
                  onsubmit="return confirm('Give the unfilled seats back to this requirement?')">
              <input type="hidden" name="do" value="release">
              <input type="hidden" name="allocation_id" value="<?= (int) $s['id'] ?>">
              <button class="btn small secondary" type="submit">Give back</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <?php if (!$p4may): ?>
    <div class="muted" style="font-size:12px">Only coordinators and managers can change where the people come from.</div>
  <?php elseif ($p4why !== ''): ?>
    <div class="muted" style="font-size:12px"><?= e($p4why) ?></div>
  <?php elseif ($p4['unallocated'] < 1): ?>
    <div class="muted" style="font-size:12px">
      All <?= (int) $p4['authorised'] ?> approved position<?= $p4['authorised'] === 1 ? ' is' : 's are' ?>
      accounted for. To add another source, reduce or give back one of the sources above first.
    </div>
  <?php else: ?>
    <form method="post" action="/requisition-allocations?id=<?= (int) $req['id'] ?>"
          style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;padding-top:4px;border-top:1px solid #eef1f4">
      <input type="hidden" name="do" value="allocate">
      <?php // Sent back so a form left open while somebody else allocated is refused,
            // not silently added on top. ?>
      <input type="hidden" name="expect_allocated" value="<?= (int) $p4['allocated'] ?>">
      <div>
        <label class="muted" style="font-size:11.5px;display:block">Source</label>
        <select class="form-control" name="source" style="min-width:190px" required>
          <option value="">Choose…</option>
          <?php foreach ($p4names as $k => $v): ?>
            <option value="<?= e($k) ?>"><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="muted" style="font-size:11.5px;display:block">How many people</label>
        <input class="form-control" type="number" name="qty" min="1" max="<?= (int) $p4['unallocated'] ?>"
               value="<?= (int) $p4['unallocated'] ?>" style="width:96px" required>
      </div>
      <div>
        <label class="muted" style="font-size:11.5px;display:block">Who exactly <span class="muted">(optional)</span></label>
        <input class="form-control" name="label" placeholder="e.g. Sterling Manpower" style="width:200px">
      </div>
      <div>
        <label class="muted" style="font-size:11.5px;display:block">Needed by <span class="muted">(optional)</span></label>
        <input class="form-control" type="date" name="target_date" style="width:150px">
      </div>
      <button class="btn small" type="submit">Add source</button>
      <div class="muted" style="font-size:11.5px;flex-basis:100%">
        You can promise at most <?= (int) $p4['unallocated'] ?> more —
        that is what is left of the <?= (int) $p4['authorised'] ?> approved.
      </div>
    </form>
  <?php endif; ?>
</div>
