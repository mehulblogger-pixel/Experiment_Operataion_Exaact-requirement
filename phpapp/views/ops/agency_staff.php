<?php
  // Agency staff roster — freelancers & sub-contractors grouped by the agency
  // they are engaged through, with a document checklist for each. Presence-only.
  $totalPeople = 0; $totalGap = 0;
  foreach ($groups as $people) foreach ($people as $p) { $totalPeople++; if (!$p['sum']['complete']) $totalGap++; }
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/masters">Masters</a> › Agency staff</div>
<div class="master-head">
  <div><h1>Agency staff &amp; their documents</h1>
    <p class="sub" style="margin:2px 0 0">Freelancers and sub-contractors, grouped by agency, with what we hold on file for each.
      <?= (int)$totalPeople ?> people · <?= $totalGap ? '<strong class="down">'.(int)$totalGap.' with papers missing</strong>' : 'all complete' ?>.</p></div>
  <div style="display:flex;gap:8px">
    <a class="btn secondary" href="/m/inspectors/new">+ Add a person</a>
    <a class="btn secondary" href="/m/agencies">Agencies →</a>
  </div>
</div>

<form method="get" action="/agency-staff" class="filter-bar">
  <select class="form-control searchable" name="agency" onchange="this.form.submit()">
    <option value="">Any agency</option>
    <?php foreach ($agencies as $a): ?>
      <option value="<?= (int)$a['id'] ?>" <?= ((int)$fAgency===(int)$a['id'])?'selected':'' ?>><?= e($a['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <label class="chk" style="align-self:center"><input type="checkbox" name="gap" value="1" onchange="this.form.submit()" <?= $onlyGap?'checked':'' ?>> Only those with papers missing</label>
  <?php if ($fAgency || $onlyGap): ?><a class="btn secondary" href="/agency-staff">Clear</a><?php endif; ?>
</form>

<?php if (!empty($reqDocs)): ?>
  <p class="sub" style="margin:0 0 10px">Required set:
    <?php foreach ($reqDocs as $k): ?><span class="pill p-mut" style="margin-right:4px"><?= e($kinds[$k] ?? $k) ?></span><?php endforeach; ?>
    <a href="/identity" class="muted">change →</a></p>
<?php else: ?>
  <p class="sub" style="margin:0 0 10px">No documents are marked required yet. <a href="/identity">Set the checklist →</a></p>
<?php endif; ?>

<?php if (!$groups): ?>
  <div class="panel"><p style="margin:0">No freelancers or sub-contractors yet. <a href="/m/inspectors/new">Add one</a> and set their engineer type to Freelancer or Sub-contractor, with the agency they come through.</p></div>
<?php endif; ?>

<?php foreach ($groups as $agency => $people): ?>
<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3 style="margin:0"><?= e($agency) ?> <span class="muted">(<?= count($people) ?>)</span></h3></div>
  <table class="dt">
    <thead><tr><th>Name</th><th>Code</th><th>Type</th><th>Discipline</th><th>Documents</th><th>On file</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($people as $p): $s = $p['sum']; ?>
      <tr>
        <td><a href="/m/inspectors/edit?id=<?= (int)$p['id'] ?>"><strong><?= e($p['name']) ?></strong></a></td>
        <td class="muted"><?= e($p['emp_code']) ?></td>
        <td class="muted"><?= $p['staff_kind']==='SUBCON' ? 'Sub-contractor' : 'Freelancer' ?></td>
        <td class="muted"><?= ($p['trade']!=='' && $p['trade']!=='—') ? e($p['trade']) : '—' ?></td>
        <td>
          <?php if ((int)$s['total'] === 0): ?><span class="muted">no checklist set</span>
          <?php else: foreach ($s['rows'] as $r): ?>
            <span class="pill <?= $r['ok']?'p-ok':'p-warn' ?>" style="margin:0 4px 4px 0;display:inline-block"><?= $r['ok']?'✓':'✗' ?> <?= e($r['label']) ?></span>
          <?php endforeach; endif; ?>
        </td>
        <td><?= (int)$s['total']===0 ? '<span class="muted">—</span>'
              : ($s['complete'] ? '<span class="pill p-ok">complete</span>'
                                : '<span class="pill p-warn">'.(int)$s['need'].' missing</span>') ?></td>
        <td><?php if ($canFile): ?><a class="btn small secondary" href="/identity?i=<?= (int)$p['id'] ?>">Documents</a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endforeach; ?>
