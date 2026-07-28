<?php $c = $counts; $view = $view ?? 'board'; ?>
<div class="crumbs"><a href="/">Home</a> › Leads</div>
<div class="master-head">
  <div><h1>Leads</h1>
  <p class="sub" style="margin:2px 0 0">People we are chasing before they are customers. Winning one <b>converts</b> it — it becomes a customer and an inquiry, and nothing is typed twice.</p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if ($canEdit): ?><a class="btn" href="/lead-new">+ New lead</a><?php endif; ?>
    <a class="btn secondary" href="/leads?<?= e(http_build_query(array_merge($_GET,['export'=>'csv']))) ?>">⬇ CSV</a>
  </div>
</div>

<div class="qcards" style="margin-top:16px">
  <div class="qcard tone-info"><div class="qic">◎</div><div class="qn"><?= (int)$c['open'] ?></div><div class="ql">Open</div></div>
  <div class="qcard tone-ok"><div class="qic">₹</div><div class="qn" style="font-size:20px"><?= fmoney_short($c['value']) ?></div><div class="ql">Open pipeline value</div></div>
  <div class="qcard <?= $c['stalled'] ? 'tone-bad' : '' ?>"><div class="qic">◷</div><div class="qn"><?= (int)$c['stalled'] ?></div><div class="ql">Past their stage's service level</div></div>
  <div class="qcard tone-warn"><div class="qic">✓</div><div class="qn"><?= (int)$c['converted'] ?></div><div class="ql">Converted</div></div>
</div>

<div class="panel" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
  <a class="btn small<?= $view==='board'?'':' secondary' ?>" href="/leads?v=board">Board</a>
  <a class="btn small<?= $view==='list'?'':' secondary' ?>" href="/leads?v=list">List</a>
  <form method="get" action="/leads" style="display:flex;gap:8px;margin-left:auto">
    <input type="hidden" name="v" value="list">
    <input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Find a company or reference">
    <button class="btn small secondary">Find</button>
  </form>
</div>

<?php if ($view === 'board'): ?>
  <?php $cols = $board['columns']; ?>
  <div style="overflow-x:auto;margin-top:16px">
    <div style="display:flex;gap:12px;min-width:min-content;align-items:flex-start">
      <?php foreach ($board['stages'] as $s): $col = $cols[(int)$s['id']] ?? ['leads'=>[],'value'=>0]; ?>
        <div class="panel" style="min-width:250px;max-width:250px;padding:12px">
          <div style="display:flex;justify-content:space-between;align-items:baseline">
            <b style="font-size:13.5px"><?= e($s['name']) ?></b>
            <span class="muted" style="font-size:12px"><?= count($col['leads']) ?></span>
          </div>
          <div class="muted" style="font-size:12px;margin-bottom:8px">
            <?= $col['value'] ? fmoney_short($col['value']) : '—' ?>
            <?= (int)$s['probability'] ? ' · ' . (int)$s['probability'] . '%' : '' ?>
          </div>
          <?php foreach ($col['leads'] as $l): $sc = lead_score($l); $st = lead_stalled($l); ?>
            <a href="/lead?id=<?= (int)$l['id'] ?>" style="display:block;border:1px solid var(--line);border-radius:8px;padding:9px;margin-bottom:8px;text-decoration:none<?= $st ? ';border-left:3px solid var(--bad)' : '' ?>">
              <b style="font-size:13px"><?= e($l['company_name']) ?></b>
              <div class="muted" style="font-size:11.5px;margin-top:2px"><?= e($l['ref']) ?><?= $l['value'] ? ' · ' . fmoney_short($l['value']) : '' ?></div>
              <div style="font-size:11.5px;margin-top:4px">
                <span class="pill <?= $sc['score']>=60?'p-ok':($sc['score']>=35?'p-warn':'p-mut') ?>" style="font-size:10.5px"><?= (int)$sc['score'] ?></span>
                <?php if ($st): ?><span class="pill p-bad" style="font-size:10.5px"><?= lead_days_in_stage($l) ?>d</span><?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
          <?php if (!$col['leads']): ?><p class="muted" style="font-size:12px;margin:0">—</p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <p class="muted" style="margin-top:10px;font-size:12.5px">A red edge means it has sat in that stage longer than the stage allows. The number is a score with its reasoning on the lead — it is a rules engine, not a guess.</p>

<?php else: ?>
<div class="panel" style="padding:0;overflow:hidden;margin-top:16px">
  <div class="ctitle" style="padding:14px 18px 0"><h3>All leads <span class="muted">(<?= count($rows) ?>)</span></h3></div>
  <?php if (!$rows): ?><p class="muted" style="padding:18px">Nothing yet.</p><?php else: ?>
  <div style="overflow-x:auto">
  <table class="dt" style="margin-top:8px">
    <thead><tr><th>Ref</th><th>Company</th><th>Stage</th><th class="num">Value</th><th>Owner</th><th class="num">In stage</th><th class="num">Score</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $l): $sc = lead_score($l); $st = lead_stalled($l); ?>
      <tr>
        <td><a href="/lead?id=<?= (int)$l['id'] ?>"><b><?= e($l['ref']) ?></b></a></td>
        <td><?= e($l['company_name']) ?><?php if ($l['contact_name']): ?><br><span class="muted" style="font-size:12px"><?= e($l['contact_name']) ?></span><?php endif; ?></td>
        <td><?= e($l['stage_name'] ?: '—') ?></td>
        <td class="num"><?= $l['value'] ? fmoney($l['value']) : '—' ?></td>
        <td><?= e($l['owner_name'] ?: '—') ?></td>
        <td class="num"><?= lead_days_in_stage($l) ?> d<?php if ($st): ?><br><span class="pill p-bad">late</span><?php endif; ?></td>
        <td class="num"><span class="pill <?= $sc['score']>=60?'p-ok':($sc['score']>=35?'p-warn':'p-mut') ?>"><?= (int)$sc['score'] ?></span></td>
        <td><span class="pill <?= $l['status']==='CONVERTED'?'p-ok':($l['status']==='LOST'?'p-bad':'p-warn') ?>"><?= e(LEAD_STATUS[$l['status']] ?? $l['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
