<?php $list = $list ?? []; $cfg = $cfg ?? []; $canConfig = $canConfig ?? false;
  $stars = function($n){ $full=floor($n); $half=($n-$full)>=0.5; $s=str_repeat('★',(int)$full).($half?'⯪':'').str_repeat('☆',5-(int)$full-($half?1:0)); return $s; };
  $tone = fn($v)=> $v>=80?'p-ok':($v>=50?'p-warn':'p-bad');
?>
<div class="crumbs"><a href="/">Home</a> › Inspector ratings</div>
<div class="master-head"><div>
  <h1>Inspector ratings</h1>
  <p class="sub" style="margin:2px 0 0">An honest score over the last <?= (int)$cfg['months'] ?> months, from timely reporting, inspections done and complaints. Every number is shown.</p>
</div></div>

<div class="panel" style="padding:0;overflow:hidden;margin-top:14px">
  <div class="dt-scroll"><table class="dt">
    <thead><tr><th>Inspector</th><th>Rating</th><th>Timely reporting</th><th>Inspections done</th><th>Complaints</th></tr></thead>
    <tbody>
    <?php foreach ($list as $row): $i=$row['inspector']; $r=$row['r']; ?>
      <tr>
        <td><a href="/timesheet?ins=<?= (int)$i['id'] ?>"><strong><?= e($i['name']) ?></strong></a><?php if ($i['emp_code']): ?> <span class="muted" style="font-size:11px"><?= e($i['emp_code']) ?></span><?php endif; ?></td>
        <td><span style="color:#e6a700;font-size:15px" title="<?= (int)$r['overall'] ?>/100"><?= $stars($r['stars']) ?></span>
            <span class="pill <?= $tone($r['overall']) ?>"><?= (int)$r['overall'] ?></span></td>
        <td><?php if ($r['report_score']===null): ?><span class="muted">no reports yet</span><?php else: ?>
            <span class="pill <?= $tone($r['report_score']) ?>"><?= (int)$r['report_score'] ?></span>
            <span class="muted" style="font-size:12px"><?= (int)$r['on_time'] ?>/<?= (int)$r['reportable'] ?> on time</span><?php endif; ?></td>
        <td><span class="pill <?= $tone($r['volume_score']) ?>"><?= (int)$r['volume_score'] ?></span>
            <span class="muted" style="font-size:12px"><?= (int)$r['done'] ?> of <?= (int)$r['target'] ?> target</span></td>
        <td><?php if ((int)$r['complaints']===0): ?><span class="pill p-ok">none</span>
            <?php else: ?><span class="pill p-bad"><?= (int)$r['complaints'] ?></span> <span class="muted" style="font-size:12px">→ <?= (int)$r['comp_score'] ?>/100</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="5" class="muted" style="padding:16px">No active inspectors.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>

<p class="muted" style="font-size:12px;margin-top:10px">Rating = weighted average of the three scores
  (<?= (int)$cfg['w_report'] ?> : <?= (int)$cfg['w_volume'] ?> : <?= (int)$cfg['w_complaint'] ?>).
  A factor with no data yet is left out so a new inspector is not marked down for it.</p>

<?php if ($canConfig): ?>
<details class="panel" style="margin-top:14px">
  <summary style="cursor:pointer;font-weight:600">Adjust how the rating is calculated</summary>
  <form method="post" action="/ratings-config" class="settings-form" style="margin-top:12px">
    <div class="form-grid">
      <div class="ff"><label>Weight — timely reporting</label><input class="form-control" type="number" name="rating_w_report" value="<?= (int)$cfg['w_report'] ?>" min="0"></div>
      <div class="ff"><label>Weight — inspections done</label><input class="form-control" type="number" name="rating_w_volume" value="<?= (int)$cfg['w_volume'] ?>" min="0"></div>
      <div class="ff"><label>Weight — complaints</label><input class="form-control" type="number" name="rating_w_complaint" value="<?= (int)$cfg['w_complaint'] ?>" min="0"></div>
      <div class="ff"><label>Reporting grace (TAT days)</label><input class="form-control" type="number" name="rating_tat_days" value="<?= (int)$cfg['tat_days'] ?>" min="0"><small class="muted">Days to upload a report after the inspection and still count as on time (a job's own TAT wins if set).</small></div>
      <div class="ff"><label>Target inspections / month</label><input class="form-control" type="number" name="rating_target_month" value="<?= (int)$cfg['target_mo'] ?>" min="1"><small class="muted">The monthly count that scores 100 on volume.</small></div>
      <div class="ff"><label>Penalty per complaint</label><input class="form-control" type="number" name="rating_complaint_penalty" value="<?= (int)$cfg['penalty'] ?>" min="1"><small class="muted">Points off the complaints score for each complaint.</small></div>
      <div class="ff"><label>Look back (months)</label><input class="form-control" type="number" name="rating_window_months" value="<?= (int)$cfg['months'] ?>" min="1" max="60"></div>
    </div>
    <button class="btn" type="submit" style="margin-top:12px">Save</button>
  </form>
</details>
<?php endif; ?>
