<?php
// Connect K2a — one requirement: its details, its applications, and every legal
// lifecycle action. Staff desk (K2a); external self-service apply is K2b.
$req = $req ?? []; $apps = $apps ?? []; $inspectors = $inspectors ?? []; $req_next = $req_next ?? []; $matches = $matches ?? [];
$can_rate = $can_rate ?? false; $ratings = $ratings ?? []; $disputes = $disputes ?? [];
$terms = $terms ?? null; $terms_fields = $terms_fields ?? []; $readiness = $readiness ?? [];
$readiness_items = $readiness_items ?? []; $readiness_score = $readiness_score ?? null;
$ratedDir = [];
foreach ($ratings as $rr) $ratedDir[strtoupper((string)$rr['direction'])] = $rr;
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

<?php if ($matches): ?>
<div class="panel" style="margin-top:12px">
  <h3 style="margin:0 0 4px">Recommended professionals</h3>
  <p class="cxmeta" style="margin:0 0 10px">Ranked from your pool on skills fit, reputation, verified credentials and eligibility for this requirement. Add one to the shortlist in a tap.</p>
  <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fill,minmax(240px,1fr))">
    <?php foreach ($matches as $m):
      [$epLbl, $epCls] = function_exists('inspector_eligibility_pill') ? inspector_eligibility_pill($m['eligibility']) : ['—','p-mut'];
      $reasonCls = $m['reason'] === 'Best match' ? 'info' : ($m['eligibility']==='BLOCKED' ? 'bad' : 'ok');
    ?>
      <div style="border:1px solid var(--line,#e3ebea);border-radius:12px;padding:13px">
        <div style="display:flex;justify-content:space-between;align-items:start;gap:8px">
          <div><strong><?= e($m['name']) ?></strong>
            <?php if (!empty($m['designation'])): ?><div class="cxmeta"><?= e($m['designation']) ?></div><?php endif; ?></div>
          <span class="cxpill <?= $reasonCls ?>"><?= e($m['reason']) ?></span>
        </div>
        <div class="cxmeta" style="margin:8px 0">
          <?php if (isset($m['trust'])): ?><strong>Trust <?= (int)$m['trust'] ?></strong> · <?php endif; ?>
          <?php if ($m['stars'] !== null && (int)$m['jobs'] >= 3): ?>★ <?= e(number_format((float)$m['stars'],1)) ?> · <?php endif; ?>
          <?php if ((int)$m['verified'] > 0): ?><?= (int)$m['verified'] ?> verified · <?php endif; ?>
          <span class="cxpill <?= $epCls==='p-ok'?'ok':($epCls==='p-bad'?'bad':'warn') ?>"><?= e($epLbl) ?></span>
          · match <?= (int)$m['score'] ?>%
        </div>
        <?php if (!empty($m['skills'])): ?><div class="cxmeta" style="margin-bottom:8px"><?= e($m['skills']) ?></div><?php endif; ?>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <a class="btn secondary" href="/passport-share?id=<?= (int)$m['id'] ?>" style="font-size:13px">Passport</a>
          <?php if ($m['eligibility'] !== 'BLOCKED'): ?>
          <form method="post" action="/connect-requirement" class="inline">
            <input type="hidden" name="id" value="<?= (int)$req['id'] ?>">
            <input type="hidden" name="inspector_id" value="<?= (int)$m['id'] ?>">
            <input type="hidden" name="action" value="apply">
            <button class="btn" type="submit" style="font-size:13px">+ Add to shortlist</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

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

<?php if ($can_rate): ?>
<div class="panel" style="margin-top:12px">
  <h3 style="margin:0 0 4px">Ratings</h3>
  <p class="cxmeta" style="margin:0 0 10px">Both sides rate each other once the engagement is complete — competency, communication, punctuality, professionalism, and whether they'd work together again.</p>
  <?php
    $dirs = ['CLIENT_TO_PRO' => 'Client → professional', 'PRO_TO_CLIENT' => 'Professional → client'];
    foreach ($dirs as $dir => $label):
      $done = $ratedDir[$dir] ?? null;
  ?>
    <div style="border:1px solid var(--line,#eee);border-radius:12px;padding:12px;margin-bottom:10px">
      <div style="font-weight:600;margin-bottom:6px"><?= e($label) ?></div>
      <?php if ($done): ?>
        <div><?= str_repeat('★', (int)$done['stars']) . str_repeat('☆', 5 - (int)$done['stars']) ?>
          <?php if ((int)$done['would_rehire']): ?><span class="cxpill ok">Would work again</span><?php endif; ?></div>
        <?php if (!empty($done['comment'])): ?><div class="cxmeta" style="margin-top:4px"><?= e($done['comment']) ?></div><?php endif; ?>
      <?php else: ?>
        <form method="post" action="/connect-requirement" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
          <input type="hidden" name="id" value="<?= (int)$req['id'] ?>">
          <input type="hidden" name="action" value="rate">
          <input type="hidden" name="direction" value="<?= e($dir) ?>">
          <select name="stars" style="padding:9px;border:1px solid var(--line,#dde3e2);border-radius:9px">
            <?php for ($s = 5; $s >= 1; $s--): ?><option value="<?= $s ?>"><?= str_repeat('★', $s) ?> (<?= $s ?>)</option><?php endfor; ?>
          </select>
          <label style="font-size:13px;display:flex;align-items:center;gap:4px"><input type="checkbox" name="would_rehire" value="1"> Would work again</label>
          <input type="text" name="comment" placeholder="Optional note" style="flex:1;min-width:160px;padding:9px;border:1px solid var(--line,#dde3e2);border-radius:9px">
          <button class="btn secondary" type="submit">Save rating</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="panel" style="margin-top:12px">
  <h3 style="margin:0 0 6px">Concerns &amp; disputes<?= $disputes ? ' ('.count($disputes).')' : '' ?></h3>
  <?php $dpill = ['OPEN'=>'warn','UNDER_REVIEW'=>'info','RESOLVED'=>'ok','WITHDRAWN'=>'muted'];
    $dnext = ['OPEN'=>['UNDER_REVIEW'=>'Start review','RESOLVED'=>'Resolve','WITHDRAWN'=>'Withdraw'],
              'UNDER_REVIEW'=>['RESOLVED'=>'Resolve','WITHDRAWN'=>'Withdraw']];
  ?>
  <?php foreach ($disputes as $d): $st = strtoupper((string)$d['status']); ?>
    <div style="border:1px solid var(--line,#eee);border-radius:12px;padding:12px;margin-bottom:10px">
      <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
        <div><strong><?= e($d['subject']) ?></strong>
          <span class="cxpill <?= $dpill[$st] ?? 'muted' ?>"><?= e(ucfirst(strtolower(str_replace('_',' ',$st)))) ?></span>
          <span class="cxpill muted"><?= e(ucfirst(strtolower($d['category']))) ?></span>
          <?php if (!(int)$d['affects_fee']): ?><span class="cxpill ok">Fee protected</span><?php endif; ?>
          <div class="cxmeta"><?= e($d['ref_code']) ?> · raised by <?= e(strtolower($d['raised_by_side'])==='pro'?'the professional':'the client') ?><?php if (!empty($d['detail'])): ?> — <?= e($d['detail']) ?><?php endif; ?></div>
          <?php if (!empty($d['resolution'])): ?><div class="cxmeta" style="margin-top:4px"><strong>Resolution:</strong> <?= e($d['resolution']) ?></div><?php endif; ?>
        </div>
      </div>
      <?php $opts = $dnext[$st] ?? []; if ($opts): ?>
      <form method="post" action="/connect-requirement" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:8px">
        <input type="hidden" name="id" value="<?= (int)$req['id'] ?>">
        <input type="hidden" name="dispute_id" value="<?= (int)$d['id'] ?>">
        <input type="hidden" name="action" value="dispute_transition">
        <input type="text" name="resolution" placeholder="Resolution note (for Resolve)" style="flex:1;min-width:180px;padding:8px;border:1px solid var(--line,#dde3e2);border-radius:9px">
        <?php foreach ($opts as $to=>$lbl): ?>
          <button class="btn secondary" type="submit" name="to" value="<?= e($to) ?>"><?= e($lbl) ?></button>
        <?php endforeach; ?>
      </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <details style="margin-top:6px"><summary style="cursor:pointer;font-weight:600">Raise a concern</summary>
    <form method="post" action="/connect-requirement" style="margin-top:8px">
      <input type="hidden" name="id" value="<?= (int)$req['id'] ?>">
      <input type="hidden" name="action" value="dispute_raise">
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <select name="raised_by_side" style="padding:9px;border:1px solid var(--line,#dde3e2);border-radius:9px"><option value="CLIENT">From the client</option><option value="PRO">From the professional</option></select>
        <select name="category" style="padding:9px;border:1px solid var(--line,#dde3e2);border-radius:9px">
          <option value="PROCESS">Process</option><option value="COMMERCIAL">Commercial</option><option value="CONDUCT">Conduct</option><option value="FINDING">Finding (fee protected)</option>
        </select>
      </div>
      <input type="text" name="subject" placeholder="Subject" style="width:100%;padding:10px;border:1px solid var(--line,#dde3e2);border-radius:10px;margin-top:8px">
      <textarea name="detail" rows="2" placeholder="What happened?" style="width:100%;padding:10px;border:1px solid var(--line,#dde3e2);border-radius:10px;margin-top:8px"></textarea>
      <button class="btn secondary" type="submit" style="margin-top:8px">Raise concern</button>
    </form>
  </details>
</div>

<?php if ($readiness_items): ?>
<div class="panel" style="margin-top:12px">
  <h3 style="margin:0 0 4px">Site readiness</h3>
  <?php if ($readiness_score): $rv = strtoupper((string)$readiness_score['verdict']); ?>
    <p class="cxmeta" style="margin:0 0 10px">
      <span class="cxpill <?= $rv==='READY'?'ok':'warn' ?>"><?= $rv==='READY'?'✓ Ready to mobilize':'⚠ Hold — not ready' ?></span>
      · <?= (int)$readiness_score['done'] ?>/<?= (int)$readiness_score['total'] ?> checks · <?= (int)$readiness_score['score'] ?>%
      <?php if (!empty($readiness_score['missing_mandatory'])): ?><br>Missing: <?= e(implode(', ', $readiness_score['missing_mandatory'])) ?><?php endif; ?>
    </p>
  <?php endif; ?>
  <?php foreach ($readiness_items as $key => [$lbl, $mand]): $on = !empty($readiness[$key]); ?>
    <form method="post" action="/connect-requirement" style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid var(--line,#eee)">
      <input type="hidden" name="id" value="<?= (int)$req['id'] ?>">
      <input type="hidden" name="action" value="readiness_toggle">
      <input type="hidden" name="item_key" value="<?= e($key) ?>">
      <input type="hidden" name="checked" value="<?= $on ? '0' : '1' ?>">
      <button type="submit" style="width:26px;height:26px;border-radius:7px;border:1px solid var(--line,#ccc);background:<?= $on?'var(--teal,#0f7d7d)':'transparent' ?>;color:#fff;cursor:pointer;font-size:15px"><?= $on?'✓':'' ?></button>
      <span><?= e($lbl) ?><?php if ($mand): ?> <span class="cxpill bad" style="font-size:10px">required</span><?php endif; ?></span>
    </form>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($terms_fields): ?>
<div class="panel" style="margin-top:12px">
  <h3 style="margin:0 0 4px">Commercial terms</h3>
  <p class="cxmeta" style="margin:0 0 10px">Agree these before mobilization — waiting charges, travel, PPE, revisit and cancellation. <?= function_exists('cx_terms_complete') && cx_terms_complete((int)$req['id']) ? '<span class="cxpill ok">Complete</span>' : '<span class="cxpill warn">Incomplete</span>' ?></p>
  <form method="post" action="/connect-requirement">
    <input type="hidden" name="id" value="<?= (int)$req['id'] ?>">
    <input type="hidden" name="action" value="terms_save">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <?php foreach ($terms_fields as $f => $lbl): ?>
        <div><label style="font-size:12.5px;color:var(--muted);display:block;margin-bottom:3px"><?= e($lbl) ?></label>
          <input type="text" name="<?= e($f) ?>" value="<?= e($terms[$f] ?? '') ?>" style="width:100%;padding:9px;border:1px solid var(--line,#dde3e2);border-radius:9px"></div>
      <?php endforeach; ?>
    </div>
    <label style="display:flex;align-items:center;gap:6px;margin-top:10px;font-size:13px"><input type="checkbox" name="accepted" value="1" <?= !empty($terms['accepted'])?'checked':'' ?>> Both sides have accepted these terms</label>
    <button class="btn secondary" type="submit" style="margin-top:8px">Save terms</button>
  </form>
</div>
<?php endif; ?>
