<?php
  $rows = $rows ?? [];
  $pill = function($s){ $s=strtoupper((string)$s);
    $c=['APPLIED'=>'#5b6b6a','SHORTLISTED'=>'#1858a8','OFFERED'=>'#1858a8','ACCEPTED'=>'#0f7d5a','REJECTED'=>'#9a2a2a','DECLINED'=>'#9a2a2a','WITHDRAWN'=>'#5b6b6a'][$s]??'#5b6b6a';
    return '<span style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;color:#fff;background:'.$c.'">'.e(ucfirst(strtolower($s))).'</span>'; };
?>
<h1>My applications</h1>
<p class="muted" style="margin:0 0 14px">Where you stand on the jobs you applied to.</p>
<?php if (!$rows): ?>
  <div class="card"><p class="muted" style="margin:0">You have not applied to anything yet. <a href="/pro/jobs">Browse open jobs →</a></p></div>
<?php else: foreach ($rows as $a): $st = strtoupper((string)$a['status']); $canWithdraw = in_array($st, ['APPLIED','SHORTLISTED','OFFERED'], true); ?>
  <div class="card">
    <div style="display:flex;justify-content:space-between;gap:10px;align-items:start">
      <div><strong><?= e($a['title']) ?></strong>
        <div class="muted" style="font-size:13px"><?= e($a['ref_code']) ?><?php if (!empty($a['location'])): ?> · <?= e($a['location']) ?><?php endif; ?></div>
      </div>
      <?= $pill($a['status']) ?>
    </div>
    <?php if (($a['proposed_rate'] ?? 0)): ?><div class="muted" style="font-size:13px;margin-top:6px">Your rate: ₹<?= (int)$a['proposed_rate'] ?></div><?php endif; ?>
    <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
      <a class="btn sec" href="/pro/messages?a=<?= (int)$a['id'] ?>" style="font-size:13px;padding:8px 14px">Message</a>
      <?php if ($canWithdraw): ?>
        <form method="post" action="/pro/applications" style="display:inline" onsubmit="return confirm('Withdraw your application for <?= e(addslashes($a['title'])) ?>?')">
          <input type="hidden" name="action" value="withdraw">
          <input type="hidden" name="application_id" value="<?= (int)$a['id'] ?>">
          <button class="btn sec" type="submit" style="font-size:13px;padding:8px 14px;color:var(--bad);border-color:var(--bad)">Withdraw</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; endif; ?>
