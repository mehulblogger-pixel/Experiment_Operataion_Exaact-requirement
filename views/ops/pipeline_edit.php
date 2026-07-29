<div class="crumbs"><a href="/">Home</a> › <a href="/pipelines">Pipelines &amp; funnels</a> › <?= e($p['name']) ?></div>
<div class="master-head">
  <div><h1><?= e($p['name']) ?></h1>
  <p class="sub" style="margin:2px 0 0"><?= e($p['entity_kind'] === 'LEAD' ? 'Lead pipeline' : 'Opportunity funnel') ?>
    <?php if ((int)$p['is_default']): ?><span class="pill p-ok">Default</span><?php endif; ?>
    <?php if (!(int)$p['active']): ?><span class="pill p-bad">Retired</span><?php endif; ?>
  </p></div>
</div>

<?php if ($problems): ?>
  <div class="msg msg-warning" style="margin-top:14px">
    <b>This pipeline is not usable as it stands.</b>
    <ul style="margin:6px 0 0 18px;padding:0"><?php foreach ($problems as $w): ?><li><?= e($w) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<form method="post" action="/pipeline-save" class="panel" style="margin-top:16px;padding:0;overflow:hidden">
  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
  <div style="padding:12px 16px;background:var(--soft);border-bottom:1px solid var(--line);display:flex;gap:14px;flex-wrap:wrap;align-items:end">
    <div class="ff" style="flex:1;min-width:220px"><label for="pname">Name</label>
      <input id="pname" name="pipeline_name" value="<?= e($p['name']) ?>" maxlength="120" <?= $canEdit?'':'disabled' ?>></div>
    <div class="ff-check"><input type="checkbox" name="active" id="pactive" value="1" <?= (int)$p['active']?'checked':'' ?> <?= $canEdit?'':'disabled' ?>>
      <label for="pactive">In use</label></div>
  </div>

  <div class="dt-scroll">
    <table class="dt">
      <caption class="sr-only">Stages, in order</caption>
      <thead><tr>
        <th scope="col" style="width:46px">Order</th>
        <th scope="col">Stage name</th>
        <th scope="col">What it means</th>
        <th scope="col" class="num">Probability</th>
        <th scope="col" class="num">Days allowed</th>
        <th scope="col">In use</th>
        <th scope="col" class="num">On it now</th>
        <?php if ($canEdit): ?><th scope="col"></th><?php endif; ?>
      </tr></thead>
      <tbody>
      <?php if (!$stages): ?>
        <tr><td colspan="8" class="muted" style="padding:18px;text-align:center">No stages yet — add the first one below.</td></tr>
      <?php else: foreach ($stages as $i => $s): $sid = (int)$s['id']; ?>
        <tr<?= (int)$s['active'] ? '' : ' style="opacity:.55"' ?>>
          <td><?= $i + 1 ?></td>
          <td><label class="sr-only" for="n-<?= $sid ?>">Stage name</label>
              <input id="n-<?= $sid ?>" name="name[<?= $sid ?>]" value="<?= e($s['name']) ?>" maxlength="120" style="min-width:170px" <?= $canEdit?'':'disabled' ?>></td>
          <td><label class="sr-only" for="k-<?= $sid ?>">What this stage means</label>
              <select id="k-<?= $sid ?>" name="kind[<?= $sid ?>]" <?= $canEdit?'':'disabled' ?>>
                <?php foreach ($kinds as $k => $lbl): ?>
                  <option value="<?= e($k) ?>" <?= $s['kind']===$k?'selected':'' ?>><?= e($lbl) ?></option>
                <?php endforeach; ?>
              </select></td>
          <td class="num"><label class="sr-only" for="p-<?= $sid ?>">Probability</label>
              <input id="p-<?= $sid ?>" name="probability[<?= $sid ?>]" type="number" min="0" max="100"
                     value="<?= (int)$s['probability'] ?>" style="width:78px;text-align:right" <?= $canEdit?'':'disabled' ?>>%</td>
          <td class="num"><label class="sr-only" for="s-<?= $sid ?>">Days allowed in this stage</label>
              <input id="s-<?= $sid ?>" name="sla_days[<?= $sid ?>]" type="number" min="0"
                     value="<?= (int)$s['sla_days'] ?>" style="width:70px;text-align:right" <?= $canEdit?'':'disabled' ?>></td>
          <td><label class="sr-only" for="a-<?= $sid ?>">In use</label>
              <input id="a-<?= $sid ?>" type="checkbox" name="active[<?= $sid ?>]" value="1" <?= (int)$s['active']?'checked':'' ?> <?= $canEdit?'':'disabled' ?>></td>
          <td class="num"><?= (int)$s['in_use'] ? '<b>' . (int)$s['in_use'] . '</b>' : '<span class="muted">—</span>' ?></td>
          <?php if ($canEdit): ?>
            <td><?php if (!(int)$s['in_use']): ?>
              <button class="btn small danger" form="del-<?= $sid ?>" onclick="return confirm('Remove this stage?')">Remove</button>
            <?php else: ?>
              <span class="muted" style="font-size:12px" title="Untick “in use” to retire it instead">has records</span>
            <?php endif; ?></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($canEdit): ?>
    <div style="padding:12px 16px;border-top:1px solid var(--line)">
      <button class="btn">Save the funnel</button>
      <span class="muted" style="font-size:12.5px;margin-left:10px">
        Order is taken from the rows top to bottom. A “won” stage is always 100% and a “lost” stage always 0% — a
        Won stage left at 80% quietly understates every forecast the company makes.
      </span>
    </div>
  <?php endif; ?>
</form>

<?php // The delete forms live outside the main form: a form inside a form is invalid HTML. ?>
<?php if ($canEdit): foreach ($stages as $s): if ((int)$s['in_use']) continue; ?>
  <form method="post" action="/pipeline-stage-delete" id="del-<?= (int)$s['id'] ?>" style="display:none">
    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
    <input type="hidden" name="stage_id" value="<?= (int)$s['id'] ?>">
  </form>
<?php endforeach; endif; ?>

<?php if ($canEdit): ?>
<form method="post" action="/pipeline-stage-add" class="panel" style="margin-top:14px;max-width:760px">
  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
  <h3 style="margin-top:0">Add a stage</h3>
  <div class="form-grid" style="gap:12px 16px">
    <div><label>Name *</label><input name="name" required maxlength="120" placeholder="e.g. Technical approval, Client site visit"></div>
    <div><label>What it means</label>
      <select name="kind"><?php foreach ($kinds as $k => $lbl): ?><option value="<?= e($k) ?>"><?= e($lbl) ?></option><?php endforeach; ?></select></div>
    <div><label>Probability</label><input name="probability" type="number" min="0" max="100" value="50"></div>
    <div><label>Days allowed</label><input name="sla_days" type="number" min="0" value="14">
      <span class="muted" style="font-size:12px">A deal past this shows as stopped moving. 0 means never chase it.</span></div>
  </div>
  <button class="btn" style="margin-top:12px">Add it</button>
  <span class="muted" style="font-size:12.5px;margin-left:8px">It goes on the end; drag order by changing the rows above and saving.</span>
</form>
<?php endif; ?>

<?php if ($others): ?>
  <p class="muted" style="margin-top:14px;font-size:12.5px">
    Other <?= e($p['entity_kind'] === 'LEAD' ? 'lead pipelines' : 'funnels') ?>:
    <?php foreach ($others as $o): ?><a href="/pipeline?id=<?= (int)$o['id'] ?>" style="margin-right:10px"><?= e($o['name']) ?></a><?php endforeach; ?>
  </p>
<?php endif; ?>
