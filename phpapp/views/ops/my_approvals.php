<?php
// My approvals — the current user's pending approval steps. Data: $inbox.
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$inbox = $inbox ?? [];
// M1 — the locked words (see docs/phase2/M4-TERMINOLOGY-LOCK.md): a Hiring
// Request and a Recruitment Requisition are different objects and are named
// differently here too.
$ent = function ($k) {
    return (defined('APPR_ENTITIES') && isset(APPR_ENTITIES[$k])) ? APPR_ENTITIES[$k] : $k;
};
// Where the approver goes to read the thing they are being asked to approve.
$entLink = function ($k, $id) {
    if ($k === 'HIRING_REQUEST') return '/hiring-request?id=' . (int) $id;
    if ($k === 'REQUISITION')    return '/requisition?id=' . (int) $id;
    return '';
};
$fdate = fn($s) => $s ? (function_exists('fdate') ? fdate($s) : date('d M Y', strtotime($s))) : '';
$cur = function_exists('cur_sym') ? cur_sym() : '';
$waiting = $waiting ?? [];
// Phase 3 · M3 §10 — ONE place decides what an SLA state is. The screen used to
// re-implement "overdue" as strtotime($sla_due) < time(), which is why no two
// surfaces agreed and "due soon" existed nowhere.
$slaState = fn($s) => function_exists('appr_sla_state') ? appr_sla_state($s) : '';
$slaText  = fn($s) => function_exists('appr_sla_sentence') ? appr_sla_sentence($s) : '';
// §28 — the approval status and the SLA status are two different things and the
// screen says so in those words, rather than inventing a third status that mixes
// them. Colour follows urgency, not decoration.
$slaPill = function ($st) {
    $map = [
        'OVERDUE'     => ['#fef2f2', '#dc2626'],
        'ESCALATED'   => ['#fef2f2', '#b91c1c'],
        'DUE'         => ['#fff7ed', '#c2410c'],
        'DUE_SOON'    => ['#fffbeb', '#a16207'],
        'ON_TRACK'    => ['#f0fdf4', '#15803d'],
        'NOT_STARTED' => ['#f1f5f9', '#475569'],
        'COMPLETED'   => ['#f1f5f9', '#475569'],
    ];
    return $map[$st] ?? ['#f1f5f9', '#475569'];
};
$urgent = fn($st) => in_array($st, ['OVERDUE', 'ESCALATED', 'DUE'], true);
?>
<div class="crumbs"><a href="/">Home</a> › My approvals</div>
<div class="master-head">
  <div><h1>My approvals</h1>
    <p class="sub" style="margin:2px 0 0">Items waiting on your decision. Approving passes the item to the next level (if any); rejecting sends it back to the requester.</p></div>
</div>

<?php if (!$inbox): ?>
  <div class="panel" style="text-align:center;padding:44px 20px">
    <div style="font-size:34px">✅</div>
    <h3 style="margin:8px 0 4px">You're all caught up</h3>
    <p class="muted">Nothing is waiting on your approval right now.</p>
  </div>
<?php else: ?>
  <div style="display:grid;gap:14px">
  <?php foreach ($inbox as $s): $st = $slaState($s); $od = $urgent($st); [$bg, $fg] = $slaPill($st); ?>
    <div class="panel" style="padding:0;overflow:hidden;<?= $od?'border-left:4px solid #dc2626':'border-left:4px solid var(--brand,#1e40af)' ?>">
      <div style="padding:14px 16px;display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
        <div style="min-width:240px">
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <span class="pill" style="background:var(--soft,#eef2ff);color:var(--brand,#1e40af);font-size:11px"><?= $e($ent($s['entity'])) ?></span>
            <?php if ($s['label']): ?><span class="pill p-mut" style="font-size:11px"><?= $e($s['label']) ?></span><?php endif; ?>
            <span class="pill" style="background:<?= $e($bg) ?>;color:<?= $e($fg) ?>;font-size:11px" title="SLA status">SLA: <?= $e($slaText($s)) ?></span>
            <?php if ($st === 'ESCALATED'): ?><span class="pill" style="background:#fef2f2;color:#b91c1c;font-size:11px" title="An escalation notice has gone out. It does not change who may approve.">Escalated</span><?php endif; ?>
          </div>
          <div style="font-weight:700;font-size:16px;margin-top:6px"><?php $lnk = $entLink($s['entity'], $s['entity_id']);
            if ($lnk): ?><a href="<?= $e($lnk) ?>"><?= $e($s['subject'] ?: ($ent($s['entity']).' #'.$s['entity_id'])) ?></a><?php
            else: ?><?= $e($s['subject'] ?: ($ent($s['entity']).' #'.$s['entity_id'])) ?><?php endif; ?></div>
          <div class="muted" style="font-size:12.5px;margin-top:3px">
            <?php if ((float)$s['amount']>0): ?>Value <b><?= $e($cur) ?><?= $e(number_format((float)$s['amount'],0)) ?></b> · <?php endif; ?>
            Approval: <b>Pending</b> · Requested by <?= $e($s['requester'] ?: '—') ?> · <?= $e($fdate($s['rcreated'] ?? '')) ?>
            <?php if (!empty($s['activated_at'])): ?> · With you since <?= $e($fdate($s['activated_at'])) ?><?php endif; ?>
            <?php if ($s['sla_due']): ?> · Due <span style="<?= $od?'color:#dc2626;font-weight:600':'' ?>"><?= $e($fdate($s['sla_due'])) ?></span><?php endif; ?>
          </div>
          <div class="muted" style="font-size:11.5px;margin-top:2px">Rule: <?= $e($s['rule_name'] ?: '—') ?> · Level <?= (int)$s['seq'] ?></div>
        </div>
        <form method="post" style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;justify-content:flex-end">
          <input type="hidden" name="do" value="act">
          <input type="hidden" name="step_id" value="<?= (int)$s['id'] ?>">
          <input class="form-control" name="remarks" placeholder="Remark (optional)" style="min-width:180px;height:38px">
          <button class="btn" name="decision" value="approve">Approve</button>
          <button class="btn secondary" name="decision" value="reject" onclick="return confirm('Reject this request?')" style="color:#dc2626;border-color:#f0c2c2">Reject</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($waiting): ?>
  <!-- §19 — the other half of the question. "My action" is above; this is what the
       person raised themselves and is waiting on somebody else for. It is read-only
       by construction: anything they could act on was removed before it got here. -->
  <h2 style="margin:26px 0 10px;font-size:16px">Waiting on someone else</h2>
  <p class="muted" style="margin:0 0 10px;font-size:12.5px">Requests you raised that are with an approver. Nothing here needs you.</p>
  <div class="panel" style="padding:0;overflow:hidden">
    <table class="table" style="margin:0">
      <thead><tr><th>Request</th><th>With</th><th>Due</th><th>SLA</th></tr></thead>
      <tbody>
      <?php foreach ($waiting as $w): $wst = $slaState($w); [$wbg, $wfg] = $slaPill($wst); ?>
        <tr>
          <td><?php $wl = $entLink($w['entity'], $w['entity_id']);
              if ($wl): ?><a href="<?= $e($wl) ?>"><?= $e($w['subject'] ?: ($ent($w['entity']).' #'.$w['entity_id'])) ?></a>
              <?php else: ?><?= $e($w['subject'] ?: ($ent($w['entity']).' #'.$w['entity_id'])) ?><?php endif; ?>
              <div class="muted" style="font-size:11.5px">Level <?= (int)$w['seq'] ?><?= $w['label'] ? ' · ' . $e($w['label']) : '' ?></div></td>
          <td class="muted"><?= $e($w['label'] ?: ($w['approver_role'] ?: 'the approver')) ?></td>
          <td class="muted"><?= $e($fdate($w['sla_due'] ?? '')) ?: '—' ?></td>
          <td><span class="pill" style="background:<?= $e($wbg) ?>;color:<?= $e($wfg) ?>;font-size:11px"><?= $e($slaText($w)) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
