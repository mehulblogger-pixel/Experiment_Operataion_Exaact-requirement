<?php
// Connect K2a — one requirement: its details, its applications, and every legal
// lifecycle action. Staff desk (K2a); external self-service apply is K2b.
$req = $req ?? []; $apps = $apps ?? []; $inspectors = $inspectors ?? []; $req_next = $req_next ?? [];
$pill = function ($s) {
    $s = strtoupper((string)$s);
    $map = ['OPEN'=>'ok','SHORTLISTING'=>'ok','AWARDED'=>'info','ACCEPTED'=>'ok','OFFERED'=>'info','SHORTLISTED'=>'info',
            'APPLIED'=>'muted','DRAFT'=>'muted','CLOSED'=>'muted','WITHDRAWN'=>'muted','CANCELLED'=>'bad','REJECTED'=>'bad','DECLINED'=>'bad','EXPIRED'=>'warn'];
    return '<span class="cxpill '.($map[$s] ?? 'muted').'">'.e(ucfirst(strtolower($s))).'</span>';
};
$label = ['OPEN'=>'Open for applications','SHORTLISTING'=>'Start shortlisting','AWARDED'=>'Award','CLOSED'=>'Close','CANCELLED'=>'Cancel','EXPIRED'=>'Mark expired','OPEN2'=>'Reopen'];
$awardedId = (int)($req['awarded_application_id'] ?? 0);
?>
<style>
  .cxpill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600}
  .cxpill.ok{background:#e7f5ef;color:#0f7d5a}.cxpill.info{background:#e6f0fb;color:#1858a8}
  .cxpill.warn{background:#fbf3d8;color:#8a6d0b}.cxpill.bad{background:#f6e6e6;color:#9a2a2a}.cxpill.muted{background:#eceff1;color:#5b6b6a}
  .approw{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--line,#eee);flex-wrap:wrap}
  .approw:last-child{border-bottom:0}
  .cxmeta{color:var(--muted,#5b6b6a);font-size:13px}
  .inline{display:inline}
</style>
<div class="crumbs"><a href="/">Home</a> › <a href="/connect-requirements">Marketplace</a> › <?= e($req['ref_code']) ?></div>
<div class="master-head">
  <div><h1><?= e($req['title']) ?> <?= $pill($req['status']) ?></h1>
    <p class="sub" style="margin:2px 0 0"><?= e($req['ref_code']) ?><?php if (!empty($req['location'])): ?> · <?= e($req['location']) ?><?php endif; ?>
      · <?= (int)$req['positions'] ?> position<?= (int)$req['positions']===1?'':'s' ?>
      <?php if (!empty($req['work_type'])): ?> · <?= e(str_replace('_',' ',$req['work_type'])) ?><?php endif; ?></p></div>
  <a class="btn secondary" href="/connect-requirements">← Marketplace</a>
</div>

<div class="panel" style="margin-top:12px">
  <div class="cxmeta" style="margin-bottom:8px">
    <?php if (!empty($req['poster_name'])): ?>Posted for <strong><?= e($req['poster_name']) ?></strong> · <?php endif; ?>
    <?php if (!empty($req['sector_code'])): ?>Sector <?= e($req['sector_code']) ?> · <?php endif; ?>
    <?php if (!empty($req['discipline_code'])): ?>Discipline <?= e($req['discipline_code']) ?> · <?php endif; ?>
    <?php if (($req['rate_min'] ?? 0) || ($req['rate_max'] ?? 0)): ?>Rate ₹<?= (int)$req['rate_min'] ?>–<?= (int)$req['rate_max'] ?> <?= e($req['rate_unit']) ?> · <?php endif; ?>
    <?php if (!empty($req['start_date'])): ?><?= e($req['start_date']) ?><?php if (!empty($req['end_date'])): ?> → <?= e($req['end_date']) ?><?php endif; endif; ?>
  </div>
  <?php if (!empty($req['description'])): ?><p style="margin:0;white-space:pre-line"><?= e($req['description']) ?></p><?php endif; ?>

  <?php if ($req_next): ?>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
    <?php foreach ($req_next as $to): if ($to==='AWARDED') continue; /* award happens per-application below */ ?>
      <form method="post" action="/connect-requirement" class="inline">
        <input type="hidden" name="id" value="<?= (int)$req['id'] ?>">
        <input type="hidden" name="action" value="req_transition">
        <input type="hidden" name="to" value="<?= e($to) ?>">
        <button class="btn <?= in_array($to,['CANCELLED'],true)?'secondary':'' ?>" type="submit"><?= e($label[$to] ?? ucfirst(strtolower($to))) ?></button>
      </form>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="panel" style="margin-top:12px">
  <h3 style="margin:0 0 6px">Applications (<?= count($apps) ?>)</h3>
  <?php if (!$apps): ?>
    <p class="muted" style="margin:0 0 10px">No applications yet.</p>
  <?php else: foreach ($apps as $a):
      $st = strtoupper((string)$a['status']);
      $next = ['APPLIED'=>['SHORTLISTED'=>'Shortlist','REJECTED'=>'Reject'],
               'SHORTLISTED'=>['OFFERED'=>'Make offer','REJECTED'=>'Reject'],
               'OFFERED'=>['ACCEPTED'=>'Mark accepted','DECLINED'=>'Declined']][$st] ?? [];
  ?>
    <div class="approw">
      <div>
        <strong><?= e($a['applicant_name'] !== '' ? $a['applicant_name'] : 'Applicant #'.$a['id']) ?></strong> <?= $pill($a['status']) ?>
        <?php if ($awardedId === (int)$a['id']): ?><span class="cxpill info">Awarded</span><?php endif; ?>
        <div class="cxmeta"><?php if (($a['proposed_rate'] ?? 0)): ?>Proposed ₹<?= (int)$a['proposed_rate'] ?> · <?php endif; ?><?= e($a['cover_note']) ?></div>
      </div>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <?php foreach ($next as $to=>$lbl): ?>
          <form method="post" action="/connect-requirement" class="inline">
            <input type="hidden" name="id" value="<?= (int)$req['id'] ?>">
            <input type="hidden" name="application_id" value="<?= (int)$a['id'] ?>">
            <input type="hidden" name="action" value="app_transition">
            <input type="hidden" name="to" value="<?= e($to) ?>">
            <button class="btn secondary" type="submit"><?= e($lbl) ?></button>
          </form>
        <?php endforeach; ?>
        <?php if (in_array($st,['SHORTLISTED','OFFERED'],true) && in_array('AWARDED', $req_next, true)): ?>
          <form method="post" action="/connect-requirement" class="inline" onsubmit="return confirm('Award this requirement to <?= e(addslashes($a['applicant_name'])) ?>?');">
            <input type="hidden" name="id" value="<?= (int)$req['id'] ?>">
            <input type="hidden" name="application_id" value="<?= (int)$a['id'] ?>">
            <input type="hidden" name="action" value="award">
            <button class="btn" type="submit">🤝 Award</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; endif; ?>

  <?php if (in_array(strtoupper((string)$req['status']), ['OPEN','SHORTLISTING'], true)): ?>
  <form method="post" action="/connect-requirement" style="margin-top:12px;border-top:1px solid var(--line,#eee);padding-top:12px">
    <input type="hidden" name="id" value="<?= (int)$req['id'] ?>">
    <input type="hidden" name="action" value="apply">
    <label style="font-size:13px;font-weight:600">Record an application (from the professional pool)</label>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">
      <select name="inspector_id" style="flex:2;min-width:200px;padding:11px;border:1px solid var(--line,#dde3e2);border-radius:10px">
        <option value="">— choose a professional —</option>
        <?php foreach ($inspectors as $i): ?><option value="<?= (int)$i['id'] ?>"><?= e($i['name']) ?></option><?php endforeach; ?>
      </select>
      <input type="number" name="proposed_rate" placeholder="Proposed ₹" style="flex:1;min-width:120px;padding:11px;border:1px solid var(--line,#dde3e2);border-radius:10px">
      <button class="btn" type="submit">Add application</button>
    </div>
  </form>
  <?php endif; ?>
</div>
