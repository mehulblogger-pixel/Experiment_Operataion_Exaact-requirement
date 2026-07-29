<div class="crumbs"><a href="/">Home</a> › <a href="/settings">Settings</a> › Industry</div>
<div class="master-head">
  <div><h1>Your industry</h1>
  <p class="sub" style="margin:2px 0 0">Choose the trade you are in and the whole sales apparatus is built for you — a lead pipeline, an opportunity funnel with realistic probabilities and service levels, the sources deals come from in that trade, and the reasons they are lost. A CRM that has to be configured before it is useful is one that gets abandoned in week two.</p></div>
</div>

<?php if ($current): ?>
  <div class="msg msg-info" style="margin-top:14px">
    Currently set to <b><?= e($templates[$current]['label'] ?? $current) ?></b><?= $appliedAt ? ', applied ' . e(fdate(substr($appliedAt,0,10))) : '' ?>.
    Applying another one <b>creates</b> new pipelines and makes them the default — nothing already in flight is moved or lost.
  </div>
<?php endif; ?>

<div class="panel" style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap">
  <?php foreach ($templates as $k => $t): ?>
    <a class="btn small<?= $showKey === $k ? '' : ' secondary' ?>" href="/industry?show=<?= e($k) ?>"><?= e($t['label']) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($preview): ?>
<div class="panel" style="margin-top:16px">
  <h3 style="margin-top:0"><?= e($preview['label']) ?></h3>
  <p class="muted" style="margin:0 0 14px"><?= e($preview['blurb']) ?></p>

  <div class="panel-split">
    <div>
      <p class="muted" style="font-size:12.5px;margin:0 0 6px"><b>The opportunity funnel</b> — what gets created</p>
      <table class="dt">
        <caption class="sr-only">Funnel stages</caption>
        <thead><tr><th scope="col">#</th><th scope="col">Stage</th><th scope="col" class="num">Probability</th><th scope="col" class="num">Days allowed</th></tr></thead>
        <tbody>
        <?php foreach ($preview['funnel'] as $i => [$n, $kind, $p, $sla]): ?>
          <tr><td><?= $i+1 ?></td>
              <td><?= e($n) ?> <?php if ($kind !== 'OPEN'): ?><span class="pill <?= $kind==='WON'?'p-ok':'p-bad' ?>" style="font-size:10.5px"><?= e($kind) ?></span><?php endif; ?></td>
              <td class="num"><?= (int)$p ?>%</td>
              <td class="num"><?= $sla ? (int)$sla : '—' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="muted" style="font-size:12px;margin:8px 0 0">The probabilities are this template's opinion of how your trade actually buys — a starting point, not a claim about your numbers. Change any of them afterwards.</p>
    </div>
    <div>
      <p class="muted" style="font-size:12.5px;margin:0 0 6px"><b>The lead pipeline</b></p>
      <table class="dt">
        <caption class="sr-only">Lead stages</caption>
        <thead><tr><th scope="col">Stage</th><th scope="col" class="num">Days allowed</th></tr></thead>
        <tbody>
        <?php foreach ($preview['leads'] as [$n, $kind, $p, $sla]): ?>
          <tr><td><?= e($n) ?> <?php if ($kind !== 'OPEN'): ?><span class="pill <?= $kind==='WON'?'p-ok':'p-bad' ?>" style="font-size:10.5px"><?= e($kind) ?></span><?php endif; ?></td>
              <td class="num"><?= $sla ? (int)$sla : '—' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="muted" style="font-size:12.5px;margin:12px 0 4px"><b>Where deals come from</b></p>
      <div style="display:flex;gap:5px;flex-wrap:wrap">
        <?php foreach ($preview['sources'] as $s): ?><span class="pill p-mut" style="font-size:11px"><?= e($s) ?></span><?php endforeach; ?>
      </div>
      <p class="muted" style="font-size:12.5px;margin:12px 0 4px"><b>Why they are lost</b></p>
      <div style="display:flex;gap:5px;flex-wrap:wrap">
        <?php foreach ($preview['lost'] as $s): ?><span class="pill p-mut" style="font-size:11px"><?= e($s) ?></span><?php endforeach; ?>
      </div>
    </div>
  </div>

  <form method="post" action="/industry-apply" style="margin-top:16px;border-top:1px solid var(--line);padding-top:14px">
    <input type="hidden" name="key" value="<?= e($showKey) ?>">
    <?php if ($preview['existing_open']): ?>
      <p class="muted" style="font-size:13px;margin:0 0 10px">
        You have <b><?= (int)$preview['existing_open'] ?></b> open deal<?= $preview['existing_open']==1?'':'s' ?>.
        They stay exactly where they are, on the pipeline they are on. Only new work uses the new funnel.
      </p>
    <?php endif; ?>
    <button class="btn" onclick="return confirm('Create the <?= e($preview['label']) ?> pipelines and make them the default?')">
      Use <?= e($preview['label']) ?>
    </button>
    <span class="muted" style="font-size:12.5px;margin-left:10px">Creates; never edits or deletes.</span>
  </form>
</div>
<?php endif; ?>
