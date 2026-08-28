<?php
// Connect K2b — the client manages one of its own requirements: who applied, and
// shortlist / offer / award / reject. Ownership is enforced in the route.
$req = $req ?? []; $apps = $apps ?? []; $req_next = $req_next ?? [];
$pill = function ($s) {
    $s = strtoupper((string)$s);
    $map = ['OPEN'=>'ok','SHORTLISTING'=>'ok','AWARDED'=>'ok','ACCEPTED'=>'ok','OFFERED'=>'ok','SHORTLISTED'=>'ok',
            'APPLIED'=>'muted','DRAFT'=>'muted','CLOSED'=>'muted','WITHDRAWN'=>'muted','CANCELLED'=>'err','REJECTED'=>'err','DECLINED'=>'err','EXPIRED'=>'warn'];
    return '<span class="ppill '.($map[$s] ?? 'muted').'">'.e(ucfirst(strtolower($s))).'</span>';
};
$awardedId = (int)($req['awarded_application_id'] ?? 0);
$reqLabel = ['OPEN'=>'Open for applications','SHORTLISTING'=>'Start shortlisting','CLOSED'=>'Close','CANCELLED'=>'Cancel','EXPIRED'=>'Mark expired'];
?>
<style>
  .ppill{display:inline-block;padding:3px 9px;border-radius:999px;font-size:12px;font-weight:600}
  .ppill.ok{background:#e7f5ef;color:#0f7d5a}.ppill.warn{background:#fbf3d8;color:#8a6d0b}
  .ppill.err{background:#f6e6e6;color:#9a2a2a}.ppill.muted{background:#eceff1;color:#5b6b6a}
  .inl{display:inline}
</style>
<p><a href="/portal/hire">← Your requirements</a></p>
<h2 class="ptitle"><?= e($req['title']) ?> <?= $pill($req['status']) ?></h2>
<p class="plead"><?= e($req['ref_code']) ?><?php if (!empty($req['location'])): ?> · <?= e($req['location']) ?><?php endif; ?> · <?= (int)$req['positions'] ?> position<?= (int)$req['positions']===1?'':'s' ?></p>

<?php if ($req_next): ?>
<div class="pcard" style="max-width:680px">
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php foreach ($req_next as $to): if ($to==='AWARDED') continue; ?>
      <form method="post" action="/portal/hire-req" class="inl">
        <input type="hidden" name="id" value="<?= (int)$req['id'] ?>"><input type="hidden" name="action" value="req_transition"><input type="hidden" name="to" value="<?= e($to) ?>">
        <button class="btn <?= $to==='CANCELLED'?'secondary':'' ?>" type="submit"><?= e($reqLabel[$to] ?? ucfirst(strtolower($to))) ?></button>
      </form>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<h3 class="ptitle" style="font-size:16px;margin-top:24px">Applications (<?= count($apps) ?>)</h3>
<?php if (!$apps): ?>
  <p class="pempty">No applications yet.</p>
<?php else: ?>
  <div class="pcard" style="max-width:680px">
    <?php foreach ($apps as $a): $st = strtoupper((string)$a['status']);
      $next = ['APPLIED'=>['SHORTLISTED'=>'Shortlist','REJECTED'=>'Reject'],
               'SHORTLISTED'=>['OFFERED'=>'Make offer','REJECTED'=>'Reject'],
               'OFFERED'=>['ACCEPTED'=>'Mark accepted']][$st] ?? []; ?>
      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding:11px 0;border-bottom:1px solid var(--line,#eee);flex-wrap:wrap">
        <div><strong><?= e($a['applicant_name'] !== '' ? $a['applicant_name'] : 'Applicant #'.$a['id']) ?></strong> <?= $pill($a['status']) ?>
          <?php if ($awardedId === (int)$a['id']): ?><span class="ppill ok">Awarded</span><?php endif; ?>
          <?php if (($a['proposed_rate'] ?? 0)): ?><div style="font-size:12.5px;color:var(--muted)">Proposed ₹<?= (int)$a['proposed_rate'] ?></div><?php endif; ?></div>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <?php foreach ($next as $to=>$lbl): ?>
            <form method="post" action="/portal/hire-req" class="inl">
              <input type="hidden" name="id" value="<?= (int)$req['id'] ?>"><input type="hidden" name="application_id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="action" value="app_transition"><input type="hidden" name="to" value="<?= e($to) ?>">
              <button class="btn secondary" type="submit"><?= e($lbl) ?></button>
            </form>
          <?php endforeach; ?>
          <?php if (in_array($st,['SHORTLISTED','OFFERED'],true) && in_array('AWARDED',$req_next,true)): ?>
            <form method="post" action="/portal/hire-req" class="inl" onsubmit="return confirm('Award to <?= e(addslashes($a['applicant_name'])) ?>?');">
              <input type="hidden" name="id" value="<?= (int)$req['id'] ?>"><input type="hidden" name="application_id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="action" value="award">
              <button class="btn" type="submit">🤝 Award</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
