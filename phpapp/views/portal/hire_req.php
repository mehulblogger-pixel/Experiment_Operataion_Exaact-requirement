<?php
// Connect K2b — the client manages one of its own requirements: who applied, and
// shortlist / offer / award / reject. Ownership is enforced in the route.
$req = $req ?? []; $apps = $apps ?? []; $req_next = $req_next ?? []; $vouchers = $vouchers ?? [];
$vinr = fn($n) => '₹' . number_format((int)round((float)$n));
$vlabel = ['DRAFT'=>['Draft','muted'],'SUBMITTED'=>['Awaiting your review','warn'],'APPROVED'=>['Approved','ok'],'PAID'=>['Paid','ok'],'REJECTED'=>['Returned','err']];
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
        <div><?php $apid = (int)($a['applicant_professional_id'] ?? 0); $anm = $a['applicant_name'] !== '' ? $a['applicant_name'] : 'Applicant #'.$a['id']; ?>
          <?php if ($apid > 0): ?><a href="/portal/talent?id=<?= $apid ?>&from=req&rid=<?= (int)$req['id'] ?>" style="text-decoration:none;color:inherit"><strong><?= e($anm) ?></strong> <span style="font-size:12px;color:#0f7d5a">View profile →</span></a><?php else: ?><strong><?= e($anm) ?></strong><?php endif; ?>
          <?= $pill($a['status']) ?>
          <?php if ($awardedId === (int)$a['id']): ?><span class="ppill ok">Awarded</span><?php endif; ?>
          <?php // Stage 7 — flag a scheduling clash or a lapsed credential before this
                //          person is shortlisted / offered / awarded (against the
                //          requirement's own dates). Only shown when not clear.
                if (function_exists('connect_conflict_check') && (int)($a['applicant_professional_id'] ?? 0) > 0):
                  $cv = connect_conflict_check((int)$a['applicant_professional_id'], (string)($req['start_date'] ?? ''), (string)($req['end_date'] ?? ''));
                  if (($cv['status'] ?? 'CLEAR') !== 'CLEAR'):
                    $cb = connect_conflict_badge($cv);
                    $rz = implode(' · ', array_map(fn($r) => $r['text'], array_slice($cv['reasons'], 0, 3))); ?>
              <span style="display:inline-block;font-size:11.5px;font-weight:600;padding:2px 9px;border-radius:999px;<?= $cb['tone']==='bad'?'background:#fbeceb;color:#9a2a2a':'background:#fbf3df;color:#a9720a' ?>" title="<?= e($rz) ?>"><?= $cb['tone']==='bad'?'⛔ ':'⚠ ' ?><?= e($cb['label']) ?></span>
          <?php endif; endif; ?>
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

<?php if ($vouchers): ?>
<h3 class="ptitle" style="font-size:16px;margin-top:24px">Vouchers</h3>
<p class="plead" style="margin:-4px 0 10px">Claims raised by the professional against this job — review the receipts, return for clarification, or approve.</p>
<div class="pcard" style="max-width:680px">
  <?php foreach ($vouchers as $vv): [$vl,$vc] = $vlabel[strtoupper((string)$vv['status'])] ?? $vlabel['DRAFT']; ?>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding:11px 0;border-bottom:1px solid var(--line,#eee)">
      <div>
        <a href="/portal/voucher?id=<?= (int)$vv['id'] ?>"><strong><?= e($vv['period_label'] ?: 'Voucher') ?></strong></a>
        <div style="font-size:12.5px;color:var(--muted)">Fee <?= e($vinr($vv['fee_total'])) ?><?php if (strtoupper((string)$vv['rate_inclusive'])==='EXCLUSIVE'): ?> · Expenses <?= e($vinr($vv['reimb_total'])) ?><?php endif; ?> · Total <?= e($vinr($vv['grand_total'])) ?></div>
      </div>
      <span class="ppill <?= $vc ?>"><?= e($vl) ?></span>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
