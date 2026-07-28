<?php $tab = $tab ?? 'people'; $r = $ready; ?>
<div class="crumbs"><a href="/">Home</a> › Confidentiality</div>
<div class="master-head"><div>
  <h1>Confidentiality</h1>
  <p class="sub" style="margin:2px 0 0">§4.2 asks for legally enforceable commitments over everything we learn on a job. Three questions: who has signed one, what a client has additionally imposed on us, and what has got out.</p></div></div>

<div class="qcards" style="margin-top:16px">
  <a class="qcard <?= $r['none'] || $r['lapsed'] ? 'tone-bad' : 'tone-ok' ?><?= $tab==='people'?' on':'' ?>" href="/confidentiality?t=people">
    <div class="qic">✍</div><div class="qn"><?= (int)$r['covered'] ?>/<?= (int)$r['people'] ?></div><div class="ql">People covered</div></a>
  <a class="qcard tone-info<?= $tab==='ndas'?' on':'' ?>" href="/confidentiality?t=ndas">
    <div class="qic">📜</div><div class="qn"><?= count($ndas) ?></div><div class="ql">Client agreements</div></a>
  <a class="qcard <?= count($breaches) ? 'tone-bad' : '' ?><?= $tab==='breaches'?' on':'' ?>" href="/confidentiality?t=breaches">
    <div class="qic">!</div><div class="qn"><?= count($breaches) ?></div><div class="ql">Breaches</div></a>
</div>

<?php if ($tab === 'people'): ?>
<div class="panel" style="padding:0;overflow:hidden">
  <div class="ctitle" style="padding:14px 18px 0"><h3>Who has signed one</h3></div>
  <p class="muted" style="padding:0 18px">The value of this register is telling you who is <b>not</b> covered. A lapsed undertaking is shown as lapsed, not quietly counted as signed.</p>
  <table class="dt" style="margin-top:8px">
    <thead><tr><th>Person</th><th>State</th><th>Signed</th><th>Valid to</th><th>Wording</th></tr></thead>
    <tbody>
    <?php foreach ($coverage as $c): ?>
      <tr<?= $c['state'] !== 'ok' ? ' style="background:rgba(220,60,60,.06)"' : '' ?>>
        <td><?= e($c['name']) ?></td>
        <td><?php
          if ($c['state'] === 'ok') echo '<span class="pill p-ok">Covered</span>';
          elseif ($c['state'] === 'lapsed') echo '<span class="pill p-bad">Lapsed</span>';
          else echo '<span class="pill p-bad">Nothing signed</span>';
        ?></td>
        <td><?= $c['live'] ? e(fdate($c['live']['signed_on'])) : ($c['lapsed'] ? e(fdate($c['lapsed']['signed_on'])) : '—') ?></td>
        <td><?= $c['live'] ? ($c['live']['valid_to'] ? e(fdate($c['live']['valid_to'])) : '<span class="muted">no end date</span>')
                : ($c['lapsed'] ? e(fdate($c['lapsed']['valid_to'])) : '—') ?></td>
        <td><?= e($c['live']['wording_ref'] ?? ($c['lapsed']['wording_ref'] ?? '—')) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$coverage): ?><tr><td colspan="5" class="muted" style="padding:18px">No engineers on the register.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php if ($canEdit): ?>
<form method="post" action="/conf-undertaking-add" enctype="multipart/form-data" class="panel" style="margin-top:16px;max-width:820px">
  <h3 style="margin-top:0">Record an undertaking</h3>
  <div class="form-grid" style="gap:12px 16px">
    <div><label>Person</label><select name="person_id" required><option value="">— choose —</option>
      <?php foreach ($inspectors as $i): ?><option value="<?= (int)$i['id'] ?>"><?= e($i['name']) ?></option><?php endforeach; ?></select></div>
    <div><label>Kind</label><select name="kind">
      <?php foreach (CONF_UNDERTAKING_KINDS as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div><label>Signed on</label><input type="date" name="signed_on" value="<?= e(date('Y-m-d')) ?>"></div>
    <div><label>Valid to <span class="muted">(blank = no end date)</span></label><input type="date" name="valid_to"></div>
    <div><label>Wording reference</label><input name="wording_ref" placeholder="e.g. CONF-UND Rev 3"></div>
    <div><label>The signed document</label><input type="file" name="file"></div>
    <div class="ff-wide"><label>Note</label><input name="note" maxlength="500"></div>
  </div>
  <button class="btn" style="margin-top:12px">Record it</button>
  <p class="muted" style="margin-top:8px;font-size:12.5px">A renewal is a new row, never an edit — "what was in force in March" has to stay answerable.</p>
</form>
<?php endif; ?>

<?php elseif ($tab === 'ndas'): ?>
<div class="panel" style="padding:0;overflow:hidden">
  <div class="ctitle" style="padding:14px 18px 0"><h3>What clients have imposed on us</h3></div>
  <p class="muted" style="padding:0 18px">Separate from our own undertaking, and it usually outlives the contract that carried it.</p>
  <table class="dt" style="margin-top:8px">
    <thead><tr><th>Client</th><th>Agreement</th><th>Signed</th><th>Runs to</th><th>Obligation actually ends</th></tr></thead>
    <tbody>
    <?php foreach ($ndas as $n): $ends = conf_nda_obligation_ends($n); ?>
      <tr>
        <td><?= e($n['display_name'] ?: $n['legal_name'] ?: '—') ?></td>
        <td><?= e($n['title'] ?: '—') ?></td>
        <td><?= $n['signed_on'] ? e(fdate($n['signed_on'])) : '—' ?></td>
        <td><?= $n['valid_to'] ? e(fdate($n['valid_to'])) : '<span class="muted">open</span>' ?></td>
        <td><?= $ends ? e(fdate($ends)) : '<span class="muted">no end</span>' ?>
            <?php if ((int)$n['survives_months'] > 0): ?><br><span class="muted" style="font-size:12px">survives <?= (int)$n['survives_months'] ?> months</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$ndas): ?><tr><td colspan="5" class="muted" style="padding:18px">Nothing recorded.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php if ($canEdit): ?>
<form method="post" action="/conf-nda-add" enctype="multipart/form-data" class="panel" style="margin-top:16px;max-width:820px">
  <h3 style="margin-top:0">Record a client agreement</h3>
  <div class="form-grid" style="gap:12px 16px">
    <div><label>Client</label><select name="partner_id" required><option value="">— choose —</option>
      <?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['display_name'] ?: $c['legal_name']) ?></option><?php endforeach; ?></select></div>
    <div><label>What it is called</label><input name="title" maxlength="200" placeholder="e.g. Mutual NDA, Sept 2026"></div>
    <div><label>Signed on</label><input type="date" name="signed_on"></div>
    <div><label>Runs to</label><input type="date" name="valid_to"></div>
    <div><label>Obligation survives (months past the end)</label><input type="number" min="0" name="survives_months" value="0"></div>
    <div><label>The signed document</label><input type="file" name="file"></div>
    <div class="ff-wide"><label>Anything stricter than our standard terms</label><textarea name="terms" rows="3"></textarea></div>
  </div>
  <button class="btn" style="margin-top:12px">Record it</button>
</form>
<?php endif; ?>

<?php else: ?>
<div class="panel" style="padding:0;overflow:hidden">
  <div class="ctitle" style="padding:14px 18px 0"><h3>Breaches</h3>
    <span><?php foreach (['open'=>'Open','closed'=>'Closed','all'=>'All'] as $k=>$l): ?>
      <a href="/confidentiality?t=breaches&f=<?= e($k) ?>" style="margin-left:10px<?= ($f??'open')===$k?';font-weight:700':'' ?>"><?= e($l) ?></a>
    <?php endforeach; ?></span></div>
  <p class="muted" style="padding:0 18px">The register nobody builds and every assessor asks for. Recording one raises a nonconformity automatically — because that is what it is.</p>
  <table class="dt" style="margin-top:8px">
    <thead><tr><th>Ref</th><th>What happened</th><th>Client</th><th>Discovered</th><th>Party told</th><th>Nonconformity</th><th>State</th></tr></thead>
    <tbody>
    <?php foreach ($breaches as $b): ?>
      <tr>
        <td><a href="/conf-breach?id=<?= (int)$b['id'] ?>"><b><?= e($b['ref']) ?></b></a></td>
        <td><?= e(CONF_BREACH_KINDS[$b['kind']] ?? $b['kind']) ?><br>
            <span class="muted" style="font-size:12px"><?= e(mb_substr((string)$b['what_got_out'], 0, 70)) ?></span></td>
        <td><?= e($b['display_name'] ?: $b['legal_name'] ?: '—') ?></td>
        <td><?= e(fdate($b['discovered_on'])) ?></td>
        <td><?= $b['party_told'] === 'YES' ? '<span class="pill p-ok">Yes</span>'
              : ($b['party_told'] === 'NO' ? '<span class="pill p-mut">No, with a reason</span>' : '<span class="pill p-bad">Not decided</span>') ?></td>
        <td><?= $b['ncr_ref'] ? '<a href="/ncr-item?id=' . (int)$b['ncr_id'] . '">' . e($b['ncr_ref']) . '</a>' : '—' ?></td>
        <td><span class="pill <?= $b['status']==='CLOSED'?'p-ok':'p-warn' ?>"><?= e(CONF_BREACH_STATUS[$b['status']] ?? $b['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$breaches): ?><tr><td colspan="7" class="muted" style="padding:18px">Nothing recorded.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php if ($canEdit): ?>
<form method="post" action="/conf-breach-add" class="panel" style="margin-top:16px;max-width:820px">
  <h3 style="margin-top:0">Record a breach</h3>
  <div class="form-grid" style="gap:12px 16px">
    <div><label>What happened</label><select name="kind">
      <?php foreach (CONF_BREACH_KINDS as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div><label>Whose information</label><select name="partner_id"><option value="">— not a specific client —</option>
      <?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['display_name'] ?: $c['legal_name']) ?></option><?php endforeach; ?></select></div>
    <div><label>Happened on</label><input type="date" name="happened_on"></div>
    <div><label>Discovered on</label><input type="date" name="discovered_on" value="<?= e(date('Y-m-d')) ?>"></div>
    <div class="ff-wide"><label>What got out *</label><textarea name="what_got_out" rows="3" required
      placeholder="Be specific — which report, whose data, how much of it."></textarea></div>
    <div class="ff-wide"><label>Who saw it</label><input name="who_saw_it" maxlength="400"></div>
    <div class="ff-wide"><label>What was done immediately</label><textarea name="containment" rows="2"></textarea></div>
  </div>
  <button class="btn" style="margin-top:12px">Record it</button>
  <p class="muted" style="margin-top:8px;font-size:12.5px">This raises a <b>major</b> nonconformity automatically. Losing control of a client's information is not a minor lapse.</p>
</form>
<?php endif; ?>
<?php endif; ?>
<style>.qcard.on{outline:2px solid var(--brand);outline-offset:1px}</style>
