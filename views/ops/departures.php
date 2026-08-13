<?php
  // Controlled departures register — deviation / concession / waiver. Each is a
  // controlled acceptance of a departure from requirement, with its own approval
  // workflow and validity. NOT an NCR closure.
  $fmt = fn($s) => $s ? date('d M Y', strtotime($s)) : '—';
  $today = date('Y-m-d');
  $stTone = fn($s) => in_array($s,['APPROVED'],true)?'p-ok':(in_array($s,['REJECTED','EXPIRED'],true)?'p-bad':'p-warn');
  $kindLabel = $kind !== '' ? ($kinds[$kind] ?? $kind) : 'Deviations, concessions &amp; waivers';
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/issues">Issues</a> › Departures</div>
<div class="master-head">
  <div><h1>Deviations, concessions &amp; waivers</h1>
    <p class="sub" style="margin:2px 0 0">A controlled, approved departure from a requirement — with technical justification,
      risk, compensating controls and validity. This is <em>not</em> automatic acceptance of poor quality, and it is not
      the same as closing a nonconformity.</p></div>
</div>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin:16px 0 8px">
  <a class="pill <?= $kind===''?'p-info':'p-mut' ?>" href="/departures" style="text-decoration:none">All</a>
  <?php foreach ($kinds as $kc=>$kl): ?>
    <a class="pill <?= $kind===$kc?'p-info':'p-mut' ?>" href="/departures?kind=<?= e($kc) ?>" style="text-decoration:none"><?= e($kl) ?></a>
  <?php endforeach; ?>
</div>

<section class="card" style="margin-top:8px">
  <h2 style="margin:0 0 10px"><?= $kindLabel ?></h2>
  <?php if (!$rows): ?>
    <p class="muted" style="margin:0 0 10px">None recorded<?= $kind!==''?' of this kind':'' ?> yet.</p>
  <?php else: ?>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th>Reference</th><th>Kind</th><th>Title / requirement</th><th>Risk</th><th>Validity</th><th>Status</th>
          <?php if ($canEdit): ?><th>Move</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($rows as $d): $exp = $d['valid_to'] && $d['valid_to']<$today && $d['status']==='APPROVED'; ?>
          <tr>
            <td><b><?= e($d['ref']) ?></b></td>
            <td><span class="pill p-mut"><?= e($kinds[$d['kind']] ?? $d['kind']) ?></span></td>
            <td><?= e($d['title']) ?><?php if ($d['requirement']): ?><br><span class="muted" style="font-size:12px"><?= e($d['requirement']) ?></span><?php endif; ?></td>
            <td><?= e($d['risk']) ?: '—' ?></td>
            <td style="white-space:nowrap"><?= e($fmt($d['valid_to'])) ?><?= $exp?' <span class="down">(lapsed)</span>':'' ?></td>
            <td><span class="pill <?= $stTone($d['status']) ?>"><?= e($statuses[$d['status']] ?? $d['status']) ?></span>
              <?php if ((int)$d['client_approval']===1): ?><br><span class="muted" style="font-size:11px">client approved</span><?php endif; ?></td>
            <?php if ($canEdit): ?>
              <td>
                <?php if (!in_array($d['status'],['CLOSED','REJECTED','EXPIRED'],true)): ?>
                  <form method="post" action="/departure-status" style="display:flex;gap:4px;flex-wrap:wrap;align-items:center">
                    <input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="back" value="/departures<?= $kind!==''?'?kind='.e($kind):'' ?>">
                    <input type="text" name="approver" placeholder="Authority" style="width:100px">
                    <label style="font-size:12px"><input type="checkbox" name="client_approval"> client</label>
                    <select name="status">
                      <?php foreach ($statuses as $sc=>$sl): ?><option value="<?= e($sc) ?>" <?= $d['status']===$sc?'selected':'' ?>><?= e($sl) ?></option><?php endforeach; ?>
                    </select>
                    <button class="btn sm" type="submit">Save</button>
                  </form>
                <?php else: ?><span class="muted">—</span><?php endif; ?>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($canEdit): ?>
    <details style="margin-top:14px"><summary>➕ Raise a deviation / concession / waiver</summary>
      <form method="post" action="/departure-new" style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-top:10px;max-width:820px">
        <label>Kind <select name="kind"><?php foreach ($kinds as $kc=>$kl): ?><option value="<?= e($kc) ?>" <?= $kind===$kc?'selected':'' ?>><?= e($kl) ?></option><?php endforeach; ?></select></label>
        <label>Client <select name="client_id"><option value="0">— none —</option><?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
        <label style="grid-column:1/3">Title <input type="text" name="title" required></label>
        <label style="grid-column:1/3">Requirement <input type="text" name="requirement"></label>
        <label>Planned condition <input type="text" name="planned_condition"></label>
        <label>Actual / proposed condition <input type="text" name="actual_condition"></label>
        <label style="grid-column:1/3">Reason <textarea name="reason" rows="2"></textarea></label>
        <label style="grid-column:1/3">Technical justification <textarea name="justification" rows="2"></textarea></label>
        <label>Risk <input type="text" name="risk" placeholder="Low / Medium / High"></label>
        <label>Compensating controls <input type="text" name="compensating_controls"></label>
        <label>Valid from <input type="date" name="valid_from"></label>
        <label>Valid to <input type="date" name="valid_to"></label>
        <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="temporary"> Temporary</label>
        <div style="grid-column:1/3"><button class="btn" type="submit">Create — starts as Draft</button>
          <span class="muted" style="margin-left:6px">It is not effective until reviewed and approved by an authorised person.</span></div>
      </form>
    </details>
  <?php endif; ?>
</section>
