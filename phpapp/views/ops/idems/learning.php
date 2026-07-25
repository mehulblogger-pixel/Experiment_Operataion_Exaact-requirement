<?php
  // Learning insights — what the system has picked up from approved & issued reports.
  $scopes = ['FIELD'=>'Field wording (by report type)', 'CLIENT'=>'Client-specific wording', 'REMARK'=>'Remarks &amp; conclusions', 'NCR'=>'Common NCR causes'];
  $scopePill = ['FIELD'=>'p-info','CLIENT'=>'p-ok','REMARK'=>'p-mut','NCR'=>'p-bad'];
  $canEdit = is_master() || can('idems.type.manage');
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/writing-assistant">Writing assistant</a> › Learning insights</div>
<div class="master-head">
  <div><h1>Learning insights</h1>
    <p class="sub" style="margin:2px 0 0">Wording harvested from your <strong>approved and issued</strong> reports, offered back as suggestions next time. Learning only enhances suggestions — it never changes a technical conclusion or an approval.</p></div>
  <a class="btn secondary" href="/phrase-library">Phrase library</a>
</div>

<div class="kpi-row">
  <div class="kpi"><span class="kic">🧠</span><div class="k">Learned entries</div><div class="v"><?= number_format($stats['total']) ?></div><div class="d">distinct wordings</div></div>
  <div class="kpi"><span class="kic">🔁</span><div class="k">Times seen</div><div class="v"><?= number_format($stats['uses']) ?></div><div class="d">across reports</div></div>
  <div class="kpi"><span class="kic">📚</span><div class="k">Reports learned from</div><div class="v"><?= number_format($stats['reports']) ?></div><div class="d">approved / issued</div></div>
  <div class="kpi"><span class="kic">⚠️</span><div class="k">NCR patterns</div><div class="v <?= $stats['ncr'] ? 'down' : '' ?>"><a href="/learning?scope=NCR"><?= number_format($stats['ncr']) ?></a></div><div class="d">recurring causes</div></div>
</div>

<?php if (!$stats['total']): ?>
  <div class="panel"><p class="muted">Nothing learned yet. As soon as reports are <strong>approved or issued</strong>, the wording your team used is picked up here and offered as suggestions on the next report of that type.</p></div>
<?php endif; ?>

<?php if ($topPhrases): ?>
<div class="panel">
  <div class="ctitle" style="margin-top:0"><h3>Most-used standard phrases</h3><a href="/phrase-library">Manage →</a></div>
  <?php $max = max(array_map(fn($p)=>(int)$p['usage_count'], $topPhrases)); ?>
  <div class="hbars">
    <?php foreach ($topPhrases as $p): $w = $max ? round((int)$p['usage_count']/$max*100) : 0; ?>
      <div class="hbar"><span title="<?= e($p['phrase']) ?>"><?= e($p['shorthand'] ?: mb_strimwidth($p['phrase'], 0, 40, '…')) ?></span>
        <span class="track"><span class="fill" style="width:<?= $w ?>%"></span></span><span class="val"><?= (int)$p['usage_count'] ?></span></div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="chip-row" style="margin:12px 0">
  <a class="ct" href="/learning" style="<?= $scope===''?'background:var(--brand);color:#fff;border-color:var(--brand)':'' ?>">All</a>
  <?php foreach ($scopes as $k=>$v): ?><a class="ct" href="/learning?scope=<?= e($k) ?>" style="<?= $scope===$k?'background:var(--brand);color:#fff;border-color:var(--brand)':'' ?>"><?= $v ?></a><?php endforeach; ?>
</div>

<div class="panel" style="padding:0;overflow:hidden">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Type</th><th>Report / client</th><th>Field</th><th>Learned wording</th><th class="num">Seen</th><th></th></tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="6" class="muted" style="padding:16px">Nothing in this category yet.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): ?>
      <tr<?= $r['muted'] ? ' style="opacity:.5"' : '' ?>>
        <td><span class="pill <?= $scopePill[$r['scope']] ?? 'p-mut' ?>"><?= e($r['scope']) ?></span></td>
        <td class="muted"><?= e($r['type_code'] ?: '—') ?><?= $r['client_name'] ? ' · '.e($r['client_name']) : '' ?></td>
        <td class="muted"><?= e($r['field_key'] ?: '—') ?></td>
        <td><?= e(mb_strimwidth($r['text_value'], 0, 150, '…')) ?><?= $r['muted'] ? ' <span class="pill p-mut" style="padding:0 5px;font-size:10px">muted</span>' : '' ?></td>
        <td class="num"><strong><?= (int)$r['uses'] ?></strong></td>
        <td style="white-space:nowrap">
          <?php if ($canEdit): ?>
            <?php if ($r['muted']): ?>
              <form method="post" action="/learning" style="display:inline"><input type="hidden" name="_do" value="unmute"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn small secondary">Restore</button></form>
            <?php else: ?>
              <form method="post" action="/learning" style="display:inline"><input type="hidden" name="_do" value="promote"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn small secondary" title="Add to the standard phrase library">★ Standardise</button></form>
              <form method="post" action="/learning" style="display:inline"><input type="hidden" name="_do" value="mute"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn small secondary" title="Stop offering this">Mute</button></form>
            <?php endif; ?>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<p class="muted" style="margin-top:8px;font-size:12px">Suggestions are offered to inspectors as "used before" chips while filling a report. Nothing is inserted automatically — the inspector chooses. Promote a frequent wording to the standard phrase library, or mute it so it is never offered.</p>
