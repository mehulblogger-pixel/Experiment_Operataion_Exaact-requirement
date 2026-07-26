<div class="crumbs"><a href="/">Home</a> › <a href="/clients"><?= e(T_REG('client')) ?></a> › Possible duplicates</div>
<div class="master-head">
  <div><h1>Possible duplicates</h1>
    <p class="sub">The same company entered twice, months apart, spelt differently. Nothing is merged automatically — read the pair, then decide.</p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <a class="btn ghost" href="/clients">← <?= e(T_REG('client')) ?></a>
    <a class="btn ghost" href="/vendors"><?= e(T_REG('vendor')) ?></a>
  </div>
</div>

<?php if (!$pairs): ?>
  <div class="msg-success"><strong>Nothing looks doubled up.</strong>
    Every <?= e(Tl('client')) ?> and <?= e(Tl('vendor')) ?> on file has a name distinct enough from the rest,
    and no two share a GSTIN or a PAN.</div>
<?php else: ?>

<div class="kpi-row">
  <div class="kpi"><span class="k-lab">Pairs worth a look</span><span class="k-val"><?= count($pairs) ?></span></div>
  <div class="kpi"><span class="k-lab">Certain — same tax number</span>
    <span class="k-val"><?= count(array_filter($pairs, function ($p) { return $p['score'] >= 0.95; })) ?></span></div>
</div>

<div class="msg-warning">
  <strong>A merge cannot be undone from this screen.</strong>
  Everything pointing at the record you drop — its <?= e(Tlp('call')) ?>, <?= e(Tlp('quote')) ?>,
  <?= e(T('boss')) ?> numbers, contracts, orders, contacts and addresses — is repointed at the one you keep.
  The dropped record is marked <em>merged</em> rather than deleted, and says where it went, so anybody
  searching for it still finds it. But the pointers do not come back. Read both sides first.
</div>

<?php foreach ($pairs as $i => $p):
  $a = $p['a']; $b = $p['b'];
  // The one carrying more history is the sensible one to keep, so it is offered
  // first — but it is only ever an offer.
  $suggest = $p['aw'] >= $p['bw'] ? 'a' : 'b';
  $roles = function ($r) { $x = []; if ((int)$r['is_client']) $x[] = T('client'); if ((int)$r['is_vendor']) $x[] = T('vendor'); return $x ? implode(' + ', $x) : '—'; };
?>
<div class="panel">
  <div class="master-head" style="margin-bottom:8px">
    <div><h3 class="tab-sub" style="margin-top:0">
      <?= e($a['legal_name']) ?> <span class="muted">and</span> <?= e($b['legal_name']) ?></h3>
      <p class="sub">Flagged because <?= e($p['why']) ?><?= $p['score'] >= 0.95 ? '' : ' (' . round($p['score'] * 100) . '% alike)' ?>.</p></div>
    <span class="pill <?= $p['score'] >= 0.95 ? 'p-bad' : 'p-warn' ?>"><?= $p['score'] >= 0.95 ? 'Almost certainly the same' : 'Worth checking' ?></span>
  </div>

  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="grid">
    <tr><th></th><th>Record A</th><th>Record B</th></tr>
    <tr><td><strong>Code</strong></td>
        <td><a href="/partner?id=<?= (int)$a['id'] ?>"><?= e($a['code']) ?></a></td>
        <td><a href="/partner?id=<?= (int)$b['id'] ?>"><?= e($b['code']) ?></a></td></tr>
    <tr><td><strong>Name</strong></td><td><?= e($a['legal_name']) ?></td><td><?= e($b['legal_name']) ?></td></tr>
    <tr><td><strong>Roles</strong></td><td><?= e($roles($a)) ?></td><td><?= e($roles($b)) ?></td></tr>
    <tr><td><strong>GSTIN</strong></td>
        <td><?= $a['gstin'] !== '' ? e($a['gstin']) : '<span class="muted">—</span>' ?></td>
        <td><?= $b['gstin'] !== '' ? e($b['gstin']) : '<span class="muted">—</span>' ?></td></tr>
    <tr><td><strong>PAN</strong></td>
        <td><?= $a['pan'] !== '' ? e($a['pan']) : '<span class="muted">—</span>' ?></td>
        <td><?= $b['pan'] !== '' ? e($b['pan']) : '<span class="muted">—</span>' ?></td></tr>
    <tr><td><strong>On file since</strong></td>
        <td class="muted"><?= e(fdate(substr((string)$a['created_at'], 0, 10))) ?></td>
        <td class="muted"><?= e(fdate(substr((string)$b['created_at'], 0, 10))) ?></td></tr>
    <tr><td><strong>What hangs off it</strong></td>
        <td><?= $p['ah'] ? e(implode(', ', $p['ah'])) : '<span class="muted">nothing yet</span>' ?></td>
        <td><?= $p['bh'] ? e(implode(', ', $p['bh'])) : '<span class="muted">nothing yet</span>' ?></td></tr>
  </table>
  </div>

  <form method="post" action="/duplicates" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
    <div class="ff" style="margin:0;min-width:230px"><label>Keep which record?</label>
      <select class="form-control" name="keep" id="keep<?= $i ?>" onchange="ddSync(<?= $i ?>)">
        <option value="<?= (int)$a['id'] ?>"<?= $suggest === 'a' ? ' selected' : '' ?>>A — <?= e($a['code']) ?> · <?= e($a['legal_name']) ?><?= $suggest === 'a' ? ' (more history)' : '' ?></option>
        <option value="<?= (int)$b['id'] ?>"<?= $suggest === 'b' ? ' selected' : '' ?>>B — <?= e($b['code']) ?> · <?= e($b['legal_name']) ?><?= $suggest === 'b' ? ' (more history)' : '' ?></option>
      </select></div>
    <input type="hidden" name="drop" id="drop<?= $i ?>" value="<?= (int)($suggest === 'a' ? $b['id'] : $a['id']) ?>">
    <div class="ff" style="margin:0;flex:1;min-width:240px"><label>Why are these the same company?</label>
      <input class="form-control" name="reason" placeholder="e.g. same company, entered twice by two branches"></div>
    <button class="btn danger" type="submit"
      onclick="return confirm('Merge these two? Everything on the record being dropped moves to the one you keep. This cannot be undone here.')">Merge them</button>
    <span class="muted" id="ddnote<?= $i ?>"></span>
  </form>
</div>
<?php endforeach; ?>

<script>
// Keep the hidden "drop" in step with the visible "keep", and say plainly which
// record is about to disappear.
var DD = <?= json_encode(array_map(function ($p) {
  return ['a' => (int)$p['a']['id'], 'b' => (int)$p['b']['id'],
          'an' => $p['a']['code'] . ' · ' . $p['a']['legal_name'],
          'bn' => $p['b']['code'] . ' · ' . $p['b']['legal_name']];
}, $pairs)) ?>;
function ddSync(i) {
  var keep = document.getElementById('keep' + i);
  var drop = document.getElementById('drop' + i);
  var note = document.getElementById('ddnote' + i);
  if (!keep || !drop || !DD[i]) return;
  var isA = String(keep.value) === String(DD[i].a);
  drop.value = isA ? DD[i].b : DD[i].a;
  if (note) note.textContent = (isA ? DD[i].bn : DD[i].an) + ' will be merged away.';
}
for (var i = 0; i < DD.length; i++) ddSync(i);
</script>

<?php endif; ?>

<div class="panel">
  <h3 class="tab-sub">How a pair gets onto this list</h3>
  <ul class="muted" style="margin:6px 0 0 18px">
    <li>Two records sharing a <strong>GSTIN</strong> or a <strong>PAN</strong> are the same company — those are shown as almost certain.</li>
    <li>Otherwise the names are compared with the punctuation, the <em>Pvt Ltd</em>, the <em>M/s</em> and words like <em>India</em>, <em>Engineering</em> and <em>Services</em> taken out, and the remaining words sorted — so word order and spelling of the suffix do not hide a match.</li>
    <li>Two records with <strong>different</strong> tax numbers are never shown. They are different companies, whatever the names look like.</li>
    <li>Nothing on this screen changes anything until somebody presses Merge.</li>
  </ul>
</div>
