<?php $byKind = []; foreach ($rows as $r) $byKind[$r['entity_kind']][] = $r; ?>
<div class="crumbs"><a href="/">Home</a> › Pipelines &amp; funnels</div>
<div class="master-head">
  <div><h1>Pipelines &amp; funnels</h1>
  <p class="sub" style="margin:2px 0 0">Have as many as your business needs — new business, renewals, repeat orders, each with its own stages. Adjust any of them to match how you actually work, or build one from scratch.</p></div>
  <?php if (!$industry): ?><a class="btn secondary" href="/industry">Start from an industry template</a><?php endif; ?>
</div>

<?php foreach ($entities as $kind => $label): $list = $byKind[$kind] ?? []; ?>
  <div class="panel" style="margin-top:16px;padding:0;overflow:hidden">
    <div style="padding:12px 16px;background:var(--soft);border-bottom:1px solid var(--line)">
      <b style="font-size:13.5px"><?= e($label) ?>s</b>
      <span class="muted" style="font-size:12.5px"> — <?= count($list) ?></span>
    </div>
    <?php if (!$list): ?>
      <p class="muted" style="padding:16px;margin:0">None yet. <?php if ($canEdit): ?>Create one below, or <a href="/industry">apply an industry template</a> and get a set built for you.<?php endif; ?></p>
    <?php else: ?>
      <div class="dt-scroll"><table class="dt">
        <caption class="sr-only"><?= e($label) ?>s</caption>
        <thead><tr><th scope="col">Name</th><th scope="col" class="num">Stages</th><th scope="col" class="num">In use</th><th scope="col">Default</th><th scope="col">State</th><th scope="col"></th></tr></thead>
        <tbody>
        <?php foreach ($list as $r): $used = $kind === 'LEAD' ? (int)$r['lead_n'] : (int)$r['opp_n']; ?>
          <tr>
            <td><a href="/pipeline?id=<?= (int)$r['id'] ?>"><b><?= e($r['name']) ?></b></a></td>
            <td class="num"><?= (int)$r['stage_n'] ?></td>
            <td class="num"><?= $used ?></td>
            <td><?php if ((int)$r['is_default']): ?><span class="pill p-ok">Default</span>
                <?php elseif ($canEdit && (int)$r['active']): ?>
                  <form method="post" action="/pipeline-default" style="display:inline">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn small secondary">Make default</button>
                  </form>
                <?php else: ?><span class="muted">—</span><?php endif; ?></td>
            <td><span class="pill <?= (int)$r['active'] ? 'p-mut' : 'p-bad' ?>"><?= (int)$r['active'] ? 'Active' : 'Retired' ?></span></td>
            <td><a class="btn small secondary" href="/pipeline?id=<?= (int)$r['id'] ?>">Edit stages</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<?php if ($canEdit): ?>
<form method="post" action="/pipeline-new" class="panel" style="margin-top:16px;max-width:760px">
  <h3 style="margin-top:0">Create one</h3>
  <p class="muted" style="font-size:13px;margin:0 0 12px">Cloning is usually quicker — "the same as the main funnel but with an extra approval step" is the common case, and retyping seven stages from a blank screen is how people give up.</p>
  <div class="form-grid" style="gap:12px 16px">
    <div><label>Name *</label><input class="form-control" name="name" required maxlength="120" placeholder="e.g. AMC renewals, Government tenders, Export orders"></div>
    <div><label>What is it for</label>
      <select class="form-control" name="entity_kind">
        <?php foreach ($entities as $k => $lbl): ?><option value="<?= e($k) ?>"><?= e($lbl) ?></option><?php endforeach; ?>
      </select></div>
    <div><label>Start from</label>
      <select class="form-control" name="clone_from">
        <option value="">— a plain four-stage starter —</option>
        <?php foreach ($rows as $r): ?>
          <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?> (<?= e($entities[$r['entity_kind']] ?? $r['entity_kind']) ?>)</option>
        <?php endforeach; ?>
      </select>
      <span class="muted" style="font-size:12px">Copies the stages; changes afterwards affect only the copy.</span></div>
    <div class="ff-check" style="align-self:end"><input type="checkbox" name="make_default" id="mkdef" value="1">
      <label for="mkdef">Make it the default for new work</label></div>
  </div>
  <button class="btn" style="margin-top:12px">Create it</button>
</form>
<?php endif; ?>
