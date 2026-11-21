<?php
// Approvals inbox (owner, 21 Nov 2026: Sales redesign Stage 4, approved design
// s4-approvals). Everything waiting on this person, in one place — the same queues,
// buttons and refusals as before (the old page is kept in tests/fixtures/pre_stage4/),
// in the record-page look, plus two approved backend items:
//   12 — a quotation can be decided here (Approve / Reject with a comment), through the
//        same /quote-approve handler and its rules; the person lands back here;
//   13 — contract openings waiting for this person's signature are listed here, signed
//        through the same /contract-open handler as the Contract openings screen.
// Two lists for deals, and the split is the point: what is waiting on YOU, and what is
// waiting on somebody else.
require_once __DIR__ . '/_sxw_ui.php';
$act = $q['act']; $watch = $q['watch'];
$total = count($act) + count($watch);
$sumOf = function ($rows) { $t = 0.0; foreach ($rows as $r) $t += (float)$r['amount']; return $t; };
// R-27 — this screen is the ONE answer to "what needs me?". The systems are still
// separate underneath — they have different records, rules and audiences — but they
// share a front door.
$sections = $sections ?? [];
$gatesOn  = $gatesOn  ?? true;
$waiting  = 0; $byKey = [];
foreach ($sections as $sec) { $waiting += count($sec['items']); $byKey[$sec['key']] = $sec; }
$quoteSum = 0.0; foreach (($byKey['quotes']['items'] ?? []) as $it) $quoteSum += crm_quote_before_gst($it);
$right = $can_manage ? '<div class="sxw-ctl"><a class="sxw-bt" href="/stage-gates">' . rec_icon('flow', 's') . ' Approval rules</a></div>' : '';
$tile = function ($key) use ($byKey) { return count($byKey[$key]['items'] ?? []); };
?>
<div class="rec sxw sxap">
  <?php sxw_head('Approvals', $waiting
      ? $waiting . ' ' . ($waiting === 1 ? 'thing is' : 'things are') . ' waiting on you. Decide here, or open the record.'
      : 'Everything waiting on your decision, in one place.', 'lock', $right); ?>

  <?php if ($waiting): ?>
  <div class="sxw-kp" style="--n:<?= min(5, 1 + count(array_filter(['quotes', 'costings', 'gates', 'lead_reassign'], fn($k) => isset($byKey[$k])))) ?>">
    <?php sxw_kpi('warn', 'lock', 'Waiting on you', 'Waiting on you', (string)$waiting, $quoteSum > 0 ? fmoney_short($quoteSum) . ' of ' . Tlp('quote') . ' before GST' : 'across every queue');
          if (isset($byKey['quotes'])) sxw_kpi('info', 'file', 'Quotations', THP('quote'), (string)$tile('quotes'), 'at your level', '#ap-quotes');
          if (isset($byKey['costings'])) sxw_kpi('info', 'calc', 'Costings', 'Costings', (string)$tile('costings'), 'waiting on you', '#ap-costings');
          if (isset($byKey['gates'])) sxw_kpi('teal', 'brief', 'Deal stage moves', 'Deal stage moves', (string)$tile('gates'), 'held for approval', '#ap-gates');
          if (isset($byKey['lead_reassign'])) sxw_kpi('grey', 'user', 'Lead hand-overs', 'Lead hand-overs', (string)$tile('lead_reassign'), 'reassignment requests', '#ap-lead_reassign'); ?>
  </div>
  <?php endif; ?>

<?php // ---- everything that is NOT a stage gate, newest concern first --------
foreach ($sections as $sec):
  if ($sec['key'] === 'gates') continue;          // rendered in full below
?>
  <section class="rec-card sxap-sec" id="ap-<?= e($sec['key']) ?>">
    <div class="sxap-h">
      <h2><?= e($sec['title']) ?> <span class="muted">— <?= count($sec['items']) ?></span></h2>
      <?php if (($sec['act'] ?? '') !== ''): ?><a class="sxw-lnk" href="<?= e($sec['act']) ?>">Open <?= e(strtolower($sec['title'])) ?> →</a><?php endif; ?>
      <p class="muted"><?= e($sec['sub']) ?></p>
    </div>
    <div class="dt-scroll">
      <table class="dt">
        <caption class="sr-only"><?= e($sec['title']) ?> waiting on you</caption>
        <thead><tr>
          <th scope="col">What</th><th scope="col"><?= $sec['key'] === 'quotes' ? 'Before GST' : 'Kind' ?></th>
          <th scope="col">Raised by</th><th scope="col">Date</th><th scope="col"></th>
        </tr></thead>
        <tbody>
        <?php foreach ($sec['items'] as $it): $row = approvals_hub_row($sec['key'], $it); ?>
          <tr>
            <td><b><?= e($row['what']) ?></b><?= $row['rule'] !== '' ? ' <span class="muted" style="font-size:12px">· ' . e($row['rule']) . '</span>' : '' ?></td>
            <td><?= e($row['kind']) ?></td>
            <td><?= e($row['who']) ?></td>
            <td><?= e($row['when']) ?></td>
            <td class="sxap-act">
              <?php if ($sec['key'] === 'lead_reassign'): ?>
                <form method="post" action="/approval-lead-decide" class="sxap-f">
                  <input type="hidden" name="req" value="<?= (int)$row['req'] ?>">
                  <label class="sr-only" for="lrn-<?= (int)$row['req'] ?>">Note</label>
                  <input class="form-control" id="lrn-<?= (int)$row['req'] ?>" name="note" placeholder="Note (required to reject)" maxlength="500">
                  <button class="btn small" name="decision" value="approve">Approve</button>
                  <button class="btn small secondary" name="decision" value="reject">Reject</button>
                </form>
              <?php elseif ($sec['key'] === 'quotes'): $mySt = crm_quote_my_open_step((int)$it['id']); ?>
                <?php // Stage 4 (item 12): decided here, by the quotation's own handler and rules; back to this inbox. ?>
                <a class="btn small secondary" href="<?= e($row['url']) ?>">Open</a>
                <?php if ($mySt): ?>
                <form method="post" action="/quote-approve?id=<?= (int)$it['id'] ?>" class="sxap-f">
                  <input type="hidden" name="step" value="<?= (int)$mySt['id'] ?>">
                  <input type="hidden" name="back" value="approvals">
                  <label class="sr-only" for="qrm-<?= (int)$it['id'] ?>">Comment</label>
                  <input class="form-control" id="qrm-<?= (int)$it['id'] ?>" name="remarks" placeholder="Comment (required to reject)" maxlength="500">
                  <button class="btn small" name="decision" value="approve" type="submit">Approve</button>
                  <button class="btn small danger" name="decision" value="reject" type="submit">Reject</button>
                </form>
                <?php endif; ?>
              <?php elseif ($sec['key'] === 'contracts'): $sg = contract_open_stage($it); ?>
                <?php // Stage 4 (item 13): the same signature the Contract openings screen offers, back to this inbox. ?>
                <a class="btn small secondary" href="/contract-openings">Open</a>
                <form method="post" action="/contract-open" class="sxap-f">
                  <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                  <input type="hidden" name="from" value="inbox">
                  <input type="hidden" name="do" value="<?= e($sg['primary']) ?>">
                  <button class="btn small" type="submit"><?= $sg['primary'] === 'endorse' ? 'Give the first signature' : 'Approve & open the contract' ?></button>
                </form>
              <?php else: ?>
                <a class="btn small" href="<?= e($row['url']) ?>">Decide</a>
              <?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endforeach; ?>

<?php if ($gatesOn): ?>
<div class="sxl-ctl" id="ap-gates">
  <span class="lab muted" style="font-size:12.5px;font-weight:600">Deals held at a stage — show:</span>
  <nav class="sxw-seg" aria-label="Which requests">
  <?php foreach ($labels as $k => $l): ?>
    <a href="/approvals?f=<?= e($k) ?>"<?= $status === $k ? ' class="on" aria-current="true"' : '' ?>><?= e($l) ?></a>
  <?php endforeach; ?>
  </nav>
</div>
<?php endif; ?>

<?php if (!$total && $gatesOn && !$waiting): ?>
  <section class="rec-card" style="text-align:center;padding:32px 16px">
    <div style="font-size:28px;line-height:1">✓</div>
    <p style="margin:8px 0 0"><b>Nothing <?= e(strtolower($labels[$status] ?? $status)) ?>.</b></p>
    <p class="muted" style="margin:6px 0 0">
      <?php if ($status === 'PENDING'): ?>
        No deal is being held. If you expected one to be, check the <a href="/stage-gates">approval rules</a> —
        a gate only applies where a rule matches the deal's value, business unit and destination stage.
      <?php else: ?>
        Nothing has been <?= e(strtolower($labels[$status] ?? $status)) ?> yet.
      <?php endif; ?>
    </p>
  </section>
<?php endif; ?>

<?php
$block = function ($rows, $title, $note, $showActions) use ($status) {
    if (!$rows) return;
    ?>
    <section class="rec-card sxap-sec">
      <div class="sxap-h">
        <h2><?= e($title) ?> <span class="muted">— <?= count($rows) ?></span></h2>
        <p class="muted"><?= e($note) ?></p>
      </div>
      <div class="dt-scroll">
        <table class="dt">
          <caption class="sr-only"><?= e($title) ?></caption>
          <thead><tr>
            <th scope="col">Deal</th><th scope="col">Move</th>
            <th scope="col" style="text-align:right">Value</th>
            <th scope="col">Raised by</th><th scope="col">Waiting on</th><th scope="col"></th>
          </tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td>
                <a href="/opportunity?id=<?= (int)$r['entity_id'] ?>"><b><?= e((string)($r['deal_name'] ?: 'Deal #' . (int)$r['entity_id'])) ?></b></a>
                <?php if (!empty($r['partner_name'])): ?><div class="muted" style="font-size:12px"><?= e((string)$r['partner_name']) ?></div><?php endif; ?>
              </td>
              <td>
                <span class="muted"><?= e((string)($r['from_name'] ?: '—')) ?></span> →
                <b><?= e((string)($r['to_name'] ?: '—')) ?></b>
                <?php if (($r['to_kind'] ?? '') === 'WON'): ?><span class="pill">Win</span><?php endif; ?>
                <?php if (($r['to_kind'] ?? '') === 'LOST'): ?><span class="pill warn">Loss</span><?php endif; ?>
                <?php if (trim((string)$r['note']) !== ''): ?>
                  <div class="muted" style="font-size:12px;margin-top:2px"><?= e((string)$r['note']) ?></div>
                <?php endif; ?>
              </td>
              <td style="text-align:right"><?= e(fmoney((float)$r['amount'])) ?></td>
              <td><?= e((string)$r['requested_by'] ?: '—') ?>
                  <div class="muted" style="font-size:12px"><?= trim((string)$r['requested_at']) !== '' ? e(fdate(substr((string)$r['requested_at'], 0, 10))) : '' ?></div></td>
              <td><?= e((string)$r['waiting_on']) ?></td>
              <td class="sxap-act">
                <?php if ($status === 'PENDING' && $showActions): ?>
                  <form method="post" action="/approval-act" class="sxap-f">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="back" value="/approvals?f=<?= e($status) ?>">
                    <input class="form-control" name="remarks" placeholder="Remark (required to send back)" maxlength="500">
                    <button class="btn small" name="decision" value="APPROVED">Approve</button>
                    <button class="btn small secondary" name="decision" value="REJECTED">Send back</button>
                  </form>
                <?php elseif ($status === 'PENDING' && !empty($r['is_mine'])): ?>
                  <form method="post" action="/approval-act" onsubmit="return confirm('Withdraw this request? The deal stays where it is.')">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="back" value="/approvals?f=<?= e($status) ?>">
                    <button class="btn small secondary" name="decision" value="CANCELLED">Withdraw</button>
                  </form>
                <?php elseif ($status !== 'PENDING'): ?>
                  <span class="muted" style="font-size:12.5px">
                    <?= e((string)$r['acted_by'] ?: '—') ?><?= trim((string)$r['remarks']) !== '' ? ' — ' . e((string)$r['remarks']) : '' ?>
                  </span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php
};

if ($status === 'PENDING') {
    $block($act, 'Waiting on you', 'Approving moves the deal immediately. Sending it back needs a reason — the person who raised it has to know what to change.', true);
    $block($watch, 'Waiting on somebody else', 'Shown so a deal that has stopped moving is never a mystery. You cannot approve your own request, which is what the rule exists for.', false);
} else {
    $block(array_merge($act, $watch), $labels[$status] ?? $status, 'What was decided, by whom, and why.', false);
}
?>

<?php if ($status === 'PENDING' && $act): ?>
  <p class="sxl-note" style="margin-top:12px">
    <?= count($act) ?> waiting on you, worth <?= e(fmoney($sumOf($act))) ?> in total.
  </p>
<?php endif; ?>
</div>
