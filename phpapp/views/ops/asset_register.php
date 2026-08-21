<?php
  $stPill = ['ISSUED'=>'p-warn','RETURNED'=>'p-ok','LOST'=>'p-bad','DAMAGED'=>'p-bad'];
  $selPerson = null;
  foreach ($people as $p) if ((int)$p['id'] === (int)$fPerson) $selPerson = $p;
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/masters">Masters</a> › Asset issuance</div>
<div class="master-head">
  <div><h1>Asset issuance register</h1>
    <p class="sub" style="margin:2px 0 0">What is given to each <?= e(Tl('engineer')) ?> — stamps, diaries, safety gear, ID, devices — acknowledged and tracked until it comes back.</p></div>
</div>

<div class="kpi-row">
  <div class="kpi"><span class="kic">📦</span><div class="k">Assets out</div><div class="v"><?= (int)$counts['out'] ?></div><div class="d">currently issued</div></div>
  <div class="kpi"><span class="kic">✍️</span><div class="k">Not acknowledged</div>
    <div class="v"><?= $counts['noack'] ? '<span class="down">'.(int)$counts['noack'].'</span>' : '0' ?></div><div class="d">awaiting the person’s sign-off</div></div>
  <div class="kpi"><span class="kic">👷</span><div class="k">People holding kit</div><div class="v"><?= (int)$counts['people'] ?></div></div>
</div>

<form method="get" action="/asset-register" class="filter-bar">
  <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Asset / serial / person…" style="min-width:200px">
  <select class="form-control searchable" name="person" onchange="this.form.submit()">
    <option value="">Any <?= e(Tl('engineer')) ?></option>
    <?php foreach ($people as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (int)$fPerson===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?><?= $p['emp_code']?' ('.e($p['emp_code']).')':'' ?></option><?php endforeach; ?>
  </select>
  <select class="form-control searchable" name="type" onchange="this.form.submit()">
    <option value="">Any type</option>
    <?php foreach ($types as $k=>$v): ?><option value="<?= e($k) ?>" <?= $fType===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
  </select>
  <select class="form-control" name="status" onchange="this.form.submit()">
    <option value="">Any status</option>
    <?php foreach ($statuses as $k=>$v): ?><option value="<?= e($k) ?>" <?= $fStatus===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
  </select>
  <button class="btn secondary" type="submit">Search</button>
  <?php if ($q!==''||$fPerson||$fType!==''||$fStatus!==''): ?><a class="btn secondary" href="/asset-register">Clear</a><?php endif; ?>
</form>

<?php if ($canManage): ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Issue an asset</h3>
  <form method="post" action="/asset-issue-new" enctype="multipart/form-data" class="inline-add">
    <div class="ff"><label>Issue to *</label>
      <select class="form-control searchable" name="person_id" required>
        <option value="">— choose —</option>
        <?php foreach ($people as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (int)$fPerson===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?><?= $p['emp_code']?' ('.e($p['emp_code']).')':'' ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Type *</label>
      <select class="form-control searchable" name="asset_type" required>
        <?php foreach ($types as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Description</label><input class="form-control" name="asset_name" placeholder="e.g. Hard stamp — welder A"></div>
    <div class="ff"><label>Serial / stamp no.</label><input class="form-control" name="identifier" placeholder="e.g. HS-042"></div>
    <div class="ff"><label>Qty</label><input class="form-control" type="number" min="1" name="quantity" value="1"></div>
    <div class="ff"><label>Issued on</label><input class="form-control" type="date" name="issued_on" value="<?= e(date('Y-m-d')) ?>"></div>
    <div class="ff"><label>Condition</label>
      <select class="form-control" name="condition_issued"><?php foreach ($conditions as $k=>$v): ?><option value="<?= e($k) ?>" <?= $k==='GOOD'?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Acknowledged by <span class="muted">— now, if signed on the spot</span></label><input class="form-control" name="ack_by" placeholder="person’s name (optional)"></div>
    <div class="ff"><label>Acknowledged on</label><input class="form-control" type="date" name="ack_on"></div>
    <div class="ff"><label>Signed slip <span class="muted">— optional scan</span></label><input class="form-control" type="file" name="ack_file" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
    <div class="ff ff-wide"><label>Notes</label><input class="form-control" name="notes"></div>
    <button class="btn small" type="submit">Issue it</button>
  </form>
</div>
<?php endif; ?>

<div class="panel" style="padding:0;overflow:hidden">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Asset</th><th>Type</th><th>Held by</th><th>Issued</th><th>Acknowledged</th><th>Status</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $issued = $r['status']==='ISSUED'; ?>
      <tr>
        <td><strong><?= e($r['asset_name']) ?></strong><?php if ($r['identifier']): ?><div class="muted" style="font-size:12px"><?= e($r['identifier']) ?><?= (int)$r['quantity']>1 ? ' · ×'.(int)$r['quantity'] : '' ?></div><?php endif; ?></td>
        <td class="muted"><?= e($types[$r['asset_type']] ?? $r['asset_type']) ?></td>
        <td><?= e($r['person_name'] ?: '—') ?><?php if ($r['emp_code']): ?> <span class="muted">(<?= e($r['emp_code']) ?>)</span><?php endif; ?></td>
        <td class="muted"><?= e($r['issued_on'] ?: '—') ?></td>
        <td><?php if (trim((string)$r['ack_on'])!==''): ?>
              <span class="pill p-ok">✓</span> <span class="muted" style="font-size:12px"><?= e($r['ack_by'] ?: '') ?> · <?= e($r['ack_on']) ?></span>
              <?php if ($r['ack_file_name']): ?> <a href="/asset-file?id=<?= (int)$r['id'] ?>">slip</a><?php endif; ?>
            <?php elseif ($issued): ?><span class="pill p-warn">not yet</span>
            <?php else: ?><span class="muted">—</span><?php endif; ?></td>
        <td><span class="pill <?= $stPill[$r['status']] ?? 'p-mut' ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span>
          <?php if (!$issued && $r['returned_on']): ?><div class="muted" style="font-size:12px"><?= e($r['returned_on']) ?></div><?php endif; ?></td>
        <?php if ($canManage): ?>
        <td style="min-width:230px">
          <?php if ($issued): ?>
            <?php if (trim((string)$r['ack_on'])===''): ?>
            <details style="margin-bottom:4px"><summary class="btn small secondary">Acknowledge</summary>
              <form method="post" action="/asset-ack" enctype="multipart/form-data" style="display:flex;gap:4px;flex-wrap:wrap;margin-top:6px">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="person" value="<?= (int)$fPerson ?>">
                <input class="form-control" style="width:130px" name="ack_by" value="<?= e($r['person_name']) ?>" placeholder="signed by">
                <input class="form-control" style="width:130px" type="date" name="ack_on" value="<?= e(date('Y-m-d')) ?>">
                <input class="form-control" type="file" name="ack_file" accept=".pdf,.jpg,.jpeg,.png,.webp" style="width:150px">
                <button class="btn small" type="submit">Save</button></form></details>
            <?php endif; ?>
            <details><summary class="btn small secondary">Return / write off</summary>
              <form method="post" action="/asset-return" style="display:flex;gap:4px;flex-wrap:wrap;margin-top:6px">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="person" value="<?= (int)$fPerson ?>">
                <select class="form-control" style="width:130px" name="status"><option value="RETURNED">Returned</option><option value="LOST">Lost</option><option value="DAMAGED">Damaged</option></select>
                <input class="form-control" style="width:130px" type="date" name="returned_on" value="<?= e(date('Y-m-d')) ?>">
                <select class="form-control" style="width:110px" name="condition_returned"><option value="">condition…</option><?php foreach ($conditions as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select>
                <input class="form-control" style="width:150px" name="return_note" placeholder="note">
                <button class="btn small" type="submit">Save</button></form></details>
          <?php else: ?><span class="muted">closed</span><?php endif; ?>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="<?= $canManage ? 7 : 6 ?>" style="text-align:center;padding:24px" class="muted">No assets issued yet<?= $canManage ? ' — use the form above to issue the first one' : '' ?>.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
