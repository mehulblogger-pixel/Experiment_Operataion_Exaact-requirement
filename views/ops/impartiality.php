<?php $sel = (int)($_GET['i'] ?? 0); ?>
<div class="crumbs"><a href="/">Home</a> › Impartiality</div>
<div class="master-head">
  <div><h1>Impartiality &amp; conflicts of interest</h1>
    <p class="sub" style="margin:2px 0 0"><?= e(accreditation_ref('impartiality')) ?> — the clause a third-party body exists to satisfy.
      Everything else is about doing the work properly; this is about being entitled to do it at all.</p></div>
  <a class="btn secondary" href="/">← Back</a>
</div>

<div class="kpi-row">
  <div class="kpi"><span class="kic">🏛</span><div class="k">This body is</div>
    <div class="v" style="font-size:20px"><?= e($ready['type']) ?></div><div class="d"><?= e($ready['type_label']) ?></div></div>
  <div class="kpi"><span class="kic">✍️</span><div class="k">Declaration outstanding</div>
    <div class="v"><?= $ready['declaration_due'] ? '<span class="down">' . (int)$ready['declaration_due'] . '</span>' : '0' ?></div>
    <div class="d">of <?= (int)$ready['people'] ?> active</div></div>
  <div class="kpi"><span class="kic">⚠️</span><div class="k">Threats not yet decided</div>
    <div class="v"><?= $ready['open'] ? '<span class="down">' . (int)$ready['open'] . '</span>' : '0' ?></div></div>
  <div class="kpi"><span class="kic">⛔</span><div class="k">Judged unacceptable</div>
    <div class="v"><?= (int)$ready['unacceptable'] ?></div><div class="d">work must be refused</div></div>
</div>

<?php if ($canDecide): ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">What kind of body is this?</h3>
  <p class="sub" style="margin-top:0">The 2026 edition replaced Type A / B / C with <strong>Type A</strong> and
    <strong>Type non-A</strong>. It changes what independence you must be able to demonstrate.</p>
  <form method="post" action="/imp-type" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
    <div class="ff" style="margin:0;min-width:340px"><label>Independence</label>
      <select class="form-control" name="ib_type">
        <?php foreach (IB_TYPES as $k=>$v): ?><option value="<?= e($k) ?>" <?= $ready['type']===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <button class="btn small" type="submit">Save</button>
  </form>
</div>
<?php endif; ?>

<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>Declarations <span class="muted">(<?= count($people) ?> people)</span></h3></div>
  <p class="sub" style="margin-top:0">A dated statement by a named person that renews — not a policy on a wall.
    One with no end date is treated as running a year.</p>
  <table class="dt">
    <thead><tr><th><?= e(TH('engineer')) ?></th><th>Declared</th><th>Valid to</th><th>Conflicts?</th><th>On the register</th></tr></thead>
    <tbody>
    <?php foreach ($people as $r): $p = $r['p']; $d = $r['decl']; ?>
      <tr<?= $sel === (int)$p['id'] ? ' style="background:var(--soft)"' : '' ?>>
        <td><a href="/impartiality?i=<?= (int)$p['id'] ?>"><strong><?= e($p['name']) ?></strong></a></td>
        <td><?= $d ? e(fdate($d['declared_on'])) : '<span class="pill p-bad">none</span>' ?></td>
        <td><?= $d ? e($d['valid_to'] ? fdate($d['valid_to']) : '—') : '—' ?></td>
        <td><?= $d ? (!empty($d['has_conflicts']) ? '<span class="pill p-warn">declared</span>' : '<span class="pill p-ok">none</span>') : '—' ?></td>
        <td><?= (int)$r['threats'] ?: '—' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$people): ?><tr><td colspan="5">No active <?= e(Tlp('engineer')) ?>.</td></tr><?php endif; ?>
    </tbody>
  </table>

  <?php if ($canDecide && $sel): ?>
  <h3 class="tab-sub">Record a declaration</h3>
  <form method="post" action="/imp-declare" class="inline-add">
    <input type="hidden" name="person_id" value="<?= (int)$sel ?>">
    <div class="ff"><label>Declared on</label><input class="form-control" type="date" name="declared_on" value="<?= e(date('Y-m-d')) ?>"></div>
    <div class="ff"><label>Valid to</label><input class="form-control" type="date" name="valid_to" value="<?= e(date('Y-m-d', strtotime('+1 year'))) ?>"></div>
    <div class="ff"><label>Anything to declare?</label>
      <label class="chk"><input type="checkbox" name="has_conflicts" value="1"> Yes — there are relationships to declare</label></div>
    <div class="ff ff-wide"><label>Statement</label>
      <input class="form-control" name="statement" placeholder="if there is anything to declare, say what — required when the box above is ticked"></div>
    <button class="btn small" type="submit">Record declaration</button>
  </form>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>Threat register <span class="muted">(<?= count($threats) ?>)</span></h3></div>
  <p class="sub" style="margin-top:0">An <strong>open</strong> or <strong>unacceptable</strong> threat stops the work it
    touches from being allocated. A register nobody acts on is worse than none — it proves the body knew.</p>
  <table class="dt">
    <thead><tr><th>Kind</th><th>Who</th><th>Which party</th><th>What</th><th>State</th><th>Decided</th><?php if ($canDecide): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($threats as $t):
      $who = $t['person_id'] ? ops_val("SELECT name FROM inspectors WHERE id=?", [(int)$t['person_id']]) : '';
      $party = $t['partner_id'] ? ops_val("SELECT COALESCE(display_name, legal_name) FROM business_partners WHERE id=?", [(int)$t['partner_id']]) : ''; ?>
      <tr>
        <td><strong><?= e(strtok(IMP_THREATS[$t['threat_kind']] ?? $t['threat_kind'], '—')) ?></strong></td>
        <td><?= e($who ?: 'the body') ?></td>
        <td><?= e($party ?: 'any client') ?></td>
        <td style="max-width:280px"><?= e($t['description']) ?>
          <?php if ($t['safeguards']): ?><div class="muted" style="font-size:12px">Safeguards: <?= e($t['safeguards']) ?></div><?php endif; ?></td>
        <td><?= in_array($t['status'], IMP_BLOCKING, true)
              ? '<span class="pill p-bad">' . e(strtok(IMP_STATUS[$t['status']], '—')) . '</span>'
              : '<span class="pill p-ok">' . e(strtok(IMP_STATUS[$t['status']], '—')) . '</span>' ?></td>
        <td class="muted"><?= e($t['decided_by'] ?: '—') ?><?= $t['decided_on'] ? '<br>' . e(fdate($t['decided_on'])) : '' ?></td>
        <?php if ($canDecide): ?>
        <td><form method="post" action="/imp-threat-decide" style="display:flex;gap:4px;flex-wrap:wrap;margin:0">
          <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
          <select class="form-control" style="width:150px" name="status">
            <?php foreach (IMP_STATUS as $k=>$v): ?><option value="<?= e($k) ?>" <?= $t['status']===$k?'selected':'' ?>><?= e(strtok($v,'—')) ?></option><?php endforeach; ?>
          </select>
          <input class="form-control" style="width:190px" name="safeguards" value="<?= e($t['safeguards']) ?>" placeholder="safeguards / grounds">
          <button class="btn small secondary" type="submit">Decide</button></form></td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$threats): ?><tr><td colspan="<?= $canDecide ? 7 : 6 ?>">Nothing on the register.</td></tr><?php endif; ?>
    </tbody>
  </table>

  <?php if ($canDecide): ?>
  <h3 class="tab-sub">Raise a threat</h3>
  <form method="post" action="/imp-threat-add" class="inline-add">
    <div class="ff"><label>Kind</label>
      <select class="form-control" name="threat_kind">
        <?php foreach (IMP_THREATS as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Who it concerns</label>
      <select class="form-control searchable" name="person_id"><option value="">— the body as a whole —</option>
        <?php foreach ($inspectors as $i): ?><option value="<?= (int)$i['id'] ?>" <?= $sel===(int)$i['id']?'selected':'' ?>><?= e($i['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Which party <a href="#" class="addlink" data-qa="client" data-target="select[name='partner_id']">+ Add new</a></label>
      <select class="form-control searchable" name="partner_id"><option value="">— any client —</option>
        <?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e(pname($c)) ?></option><?php endforeach; ?>
      </select>
      <small class="muted">Leave blank and it counts against every client.</small></div>
    <div class="ff"><label>Raised on</label><input class="form-control" type="date" name="raised_on" value="<?= e(date('Y-m-d')) ?>"></div>
    <div class="ff ff-wide"><label>What is the threat? *</label>
      <input class="form-control" name="description" required placeholder="plainly — an assessor reads this, not a code"></div>
    <button class="btn small" type="submit">Raise it</button>
  </form>
  <?php endif; ?>
</div>
