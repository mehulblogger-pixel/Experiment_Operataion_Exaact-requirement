<div class="crumbs"><a href="/">Home</a> › <a href="/analytics">Analytics</a> › <span>Scorecard</span></div>
<div class="master-head"><div>
  <h1>Management scorecard</h1>
  <p class="sub" style="margin:2px 0 0">A weighted view — but never an opaque number. Every category shows its own weight, score and contribution.</p>
</div></div>

<?php $tot = $eval['total']; ?>
<div class="panel" style="display:flex;align-items:center;gap:22px;flex-wrap:wrap">
  <div style="text-align:center;min-width:150px">
    <div style="font-size:52px;font-weight:700;letter-spacing:-.03em;line-height:1"><?= $tot===null ? '—' : e($tot) ?></div>
    <div class="muted" style="font-size:12.5px;margin-top:4px"><?= $tot===null ? 'Not enough data to score' : 'Overall (0–100)' ?></div>
  </div>
  <div class="muted" style="font-size:13.5px;max-width:52ch;line-height:1.6">
    The overall is the weighted average of the categories that have both a target and data. 
    <?php if ((int)$eval['excluded']>0): ?><b><?= (int)$eval['excluded'] ?></b> item(s) are excluded (no target or no data) and their weight is redistributed — the total is never faked from missing pieces.<?php endif; ?>
  </div>
</div>

<div class="panel" style="padding:0;overflow:hidden"><div class="tbl-scroll" style="overflow-x:auto">
<table class="dt">
  <thead><tr><th>Category</th><th>KPI</th><th>Weight</th><th>Value</th><th>Score</th><th>Contribution</th></tr></thead>
  <tbody>
  <?php foreach ($eval['rows'] as $r): ?>
    <tr>
      <td><b><?= e($r['category']) ?></b></td>
      <td><?= e($r['kpi']) ?></td>
      <td><?= e($r['weight']) ?></td>
      <td><?= e($r['no_data'] ? 'No data' : tapi_fmt_value($r['value'],$r['unit'])) ?></td>
      <td><?php if ($r['score']===null): ?><span class="muted">— not scored</span><?php else: ?><b><?= e($r['score']) ?></b><?php endif; ?></td>
      <td><?= $r['contribution']===null ? '<span class="muted">—</span>' : '<b>+'.e($r['contribution']).'</b>' ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$eval['rows']): ?><tr><td colspan="6" class="muted" style="text-align:center;padding:22px">No scorecard items.</td></tr><?php endif; ?>
  </tbody>
</table>
</div></div>
<p class="muted" style="font-size:12.5px"><?= e(tapi_fairness_note('resource')) ?></p>
