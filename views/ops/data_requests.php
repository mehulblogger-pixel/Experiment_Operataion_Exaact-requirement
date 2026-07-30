<div class="crumbs"><a href="/">Home</a> › <a href="/compliance">Where we stand</a> › <span>Requests about personal data</span></div>

<div class="master-head">
  <div><h1>Requests about personal data</h1>
    <p class="sub" style="margin:2px 0 0">Somebody asking for a copy of what is held about them, for a correction, or for it to go.</p></div>
</div>

<div class="panel" style="border:1px solid var(--info);background:color-mix(in srgb,var(--info) 7%,transparent)">
  <b>What the DPDP Act gives people</b>
  <div class="muted" style="margin-top:4px;font-size:13.5px;line-height:1.65">
    A person whose data you hold — a client's contact person, one of your own <?= e(Tlp('engineer')) ?> — can ask what
    you have about them, ask you to correct it, ask you to delete it, or withdraw their consent. Log the request
    here on the day it arrives. A copy of everything held about somebody can be produced as a file from their own
    record, and the same screen shows what deleting them would and would not remove.
  </div>
</div>

<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>Log a request</h3></div>
  <form method="post" action="/data-requests" class="form-grid">
    <div class="ff"><label>Received on</label>
      <input class="form-control" type="datetime-local" name="received_at" value="<?= e(date('Y-m-d\TH:i')) ?>"></div>
    <div class="ff"><label>Who asked</label><input class="form-control" name="requester" required placeholder="Their name"></div>
    <div class="ff"><label>How to reach them</label><input class="form-control" name="contact" placeholder="e-mail or phone"></div>
    <div class="ff"><label>What they want</label>
      <select class="form-control" name="kind">
        <?php foreach (DATA_REQUEST_KINDS as $k => $lbl): ?><option value="<?= e($k) ?>"><?= e($lbl) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff ff-wide"><label>In their words</label><textarea class="form-control" name="detail" rows="2"></textarea></div>
    <div class="ff ff-check" style="align-items:end"><button class="btn" type="submit">Log it</button></div>
  </form>
</div>

<div class="panel" style="padding:0;overflow:hidden">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Received</th><th>Waiting</th><th>Who</th><th>What they want</th><th>Detail</th><th>Status</th><th>Answer</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e(fdate($r['received_at'])) ?></td>
        <?php $ag = dsr_age($r); ?>
        <td class="nowrap">
          <?php if ($ag['days'] === null): ?>
            <span class="muted">—</span>
          <?php elseif ($ag['state'] === 'overdue'): ?>
            <span class="pill p-bad"><?= (int)$ag['days'] ?> days</span>
            <div class="t-xs t-mut">past the <?= (int)$ag['target'] ?>-day target</div>
          <?php elseif ($ag['state'] === 'due-soon'): ?>
            <span class="pill p-warn"><?= (int)$ag['days'] ?> days</span>
            <div class="t-xs t-mut">of <?= (int)$ag['target'] ?></div>
          <?php elseif ($ag['state'] === 'late-closed'): ?>
            <span class="pill p-mut"><?= (int)$ag['days'] ?> days</span>
            <div class="t-xs t-mut">answered late</div>
          <?php elseif ($ag['state'] === 'answered'): ?>
            <span class="pill p-ok"><?= (int)$ag['days'] ?> days</span>
            <div class="t-xs t-mut">to answer</div>
          <?php else: ?>
            <span class="pill p-mut"><?= (int)$ag['days'] ?> days</span>
            <div class="t-xs t-mut">of <?= (int)$ag['target'] ?></div>
          <?php endif; ?>
        </td>
        <td><b><?= e($r['requester']) ?></b><?= $r['contact'] ? '<br><span class="muted" style="font-size:12px">' . e($r['contact']) . '</span>' : '' ?></td>
        <td><?= e(DATA_REQUEST_KINDS[$r['kind']] ?? $r['kind']) ?></td>
        <td style="font-size:13px"><?= e($r['detail'] ?: '—') ?></td>
        <td><span class="pill <?= $r['status'] === 'CLOSED' ? 'p-ok' : 'p-warn' ?>"><?= $r['status'] === 'CLOSED' ? 'Answered' : 'Open' ?></span></td>
        <td>
          <?php if ($r['status'] === 'CLOSED'): ?>
            <span class="muted" style="font-size:12.5px"><?= e(fdate($r['answered_at'])) ?> — <?= e($r['answer'] ?: '') ?></span>
          <?php else: ?>
            <form method="post" action="/data-requests" style="display:flex;gap:6px">
              <input type="hidden" name="_do" value="answer"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input class="form-control" name="answer" placeholder="What you did" style="min-width:180px">
              <button class="btn small" type="submit">Close</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" style="text-align:center;padding:24px" class="muted">Nothing logged.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
