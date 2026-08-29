<?php
// Connect K2a — one requirement: its details, its applications, and every legal
// lifecycle action. Staff desk (K2a); external self-service apply is K2b.
$req = $req ?? []; $apps = $apps ?? []; $inspectors = $inspectors ?? []; $req_next = $req_next ?? []; $matches = $matches ?? [];
$can_rate = $can_rate ?? false; $ratings = $ratings ?? []; $disputes = $disputes ?? [];
$terms = $terms ?? null; $terms_fields = $terms_fields ?? []; $readiness = $readiness ?? [];
$readiness_items = $readiness_items ?? []; $readiness_score = $readiness_score ?? null; $advisor = $advisor ?? null;
$billable = $billable ?? null; $positions = $positions ?? []; $crew = $crew ?? null; $disciplines = $disciplines ?? [];
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

<?php if ($advisor && ($advisor['actions'] || strtoupper((string)$advisor['risk']) !== 'LOW')):
  $rk = strtoupper((string)$advisor['risk']);
  $bg = $rk==='HIGH' ? '#f6e6e6' : ($rk==='MEDIUM' ? '#fbf3d8' : '#e7f5ef');
  $bd = $rk==='HIGH' ? '#9a2a2a' : ($rk==='MEDIUM' ? '#8a6d0b' : '#0f7d5a');
?>
<div class="panel" style="margin-top:12px;background:<?= $bg ?>;border-left:4px solid <?= $bd ?>">
  <div style="font-weight:600;color:<?= $bd ?>">🧭 Operations Advisor — Delay risk: <?= e($rk) ?> · Readiness <?= (int)$advisor['readiness_pct'] ?>%</div>
  <div class="cxmeta" style="margin:2px 0 0;color:<?= $bd ?>"><?= e($advisor['headline']) ?></div>
  <?php if ($advisor['actions']): ?>
    <ul style="margin:8px 0 0;padding-left:20px">
      <?php foreach ($advisor['actions'] as $a): ?><li style="margin:2px 0"><?= e($a) ?></li><?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
<?php endif; ?>

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
  <h3 style="margin:0 0 4px">Crew manifest<?php if ($crew && (int)$crew['positions']>0): ?> — <?= (int)$crew['positions'] ?> position<?= (int)$crew['positions']===1?'':'s' ?>, <?= (int)$crew['headcount'] ?> people<?php endif; ?></h3>
  <p class="cxmeta" style="margin:0 0 10px">For a shutdown / turnaround, build the crew as positions (role × quantity × rate). A single-role job needs none of this.</p>
  <?php if ($positions): ?>
    <div class="tbl-wrap"><table class="tbl">
      <thead><tr><th>#</th><th>Role</th><th>Discipline</th><th>Qty</th><th>Rate</th><th>Shift</th><th>Line ₹</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($positions as $p): ?>
        <tr>
          <td><?= (int)$p['seq'] ?></td><td><?= e($p['role']) ?></td><td><?= e($p['discipline_code']) ?></td>
          <td><?= (int)$p['quantity'] ?></td><td>₹<?= (int)$p['rate'] ?>/<?= e($p['unit']) ?></td><td><?= e($p['shift_pattern']) ?></td>
          <td>₹<?= number_format((int)$p['quantity']*(float)$p['rate'],0) ?></td>
          <td><form method="post" action="/connect-requirement" onsubmit="return confirm('Remove this position?');">
            <input type="hidden" name="id" value="<?= (int)$req['id'] ?>"><input type="hidden" name="position_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="action" value="position_delete">
            <button class="btn secondary" type="submit" style="font-size:12px">Remove</button></form></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <?php if ($crew): ?><tfoot><tr><th colspan="6" style="text-align:right">Crew total</th><th>₹<?= number_format((float)$crew['value'],0) ?></th><th></th></tr></tfoot><?php endif; ?>
    </table></div>
  <?php endif; ?>
  <details style="margin-top:8px"><summary style="cursor:pointer;font-weight:600">➕ Add a position</summary>
    <form method="post" action="/connect-requirement" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr;gap:8px;margin-top:8px">
      <input type="hidden" name="id" value="<?= (int)$req['id'] ?>"><input type="hidden" name="action" value="position_add">
      <input type="text" name="role" placeholder="Role (e.g. UT technician)" style="padding:9px;border:1px solid var(--line,#dde3e2);border-radius:9px">
      <select name="discipline_code" style="padding:9px;border:1px solid var(--line,#dde3e2);border-radius:9px"><option value="">Discipline</option><?php foreach ($disciplines as $d): ?><option value="<?= e($d['code']) ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select>
      <input type="number" name="quantity" value="1" min="1" placeholder="Qty" style="padding:9px;border:1px solid var(--line,#dde3e2);border-radius:9px">
      <input type="number" name="rate" placeholder="Rate ₹/day" style="padding:9px;border:1px solid var(--line,#dde3e2);border-radius:9px">
      <button class="btn" type="submit">Add</button>
      <input type="text" name="shift_pattern" placeholder="Shift (e.g. 12h day)" style="grid-column:1/3;padding:9px;border:1px solid var(--line,#dde3e2);border-radius:9px">
    </form>
  </details>
</div>

<?php if (strtoupper((string)$req['status']) === 'AWARDED'): ?>
<div class="panel" style="margin-top:12px">
  <h3 style="margin:0 0 6px">Billing</h3>
  <?php if ($billable): $bs = strtoupper((string)$billable['status']); ?>
    <p class="cxmeta" style="margin:0">
      <span class="cxpill <?= $bs==='BILLED'?'ok':($bs==='CANCELLED'?'bad':'info') ?>"><?= e(ucfirst(strtolower($bs))) ?></span>
      This engagement is in the billing ledger — <?= (int)$billable['qty'] ?> × ₹<?= (int)$billable['rate'] ?> = <strong>₹<?= number_format((float)$billable['amount'], 0) ?></strong>.
      <a href="/billable-events">Open the billable events board →</a>
    </p>
    <p class="cxmeta" style="margin:6px 0 0">Finance approves and attests the real invoice from there — the books ledger stays the single money truth.</p>
  <?php else: ?>
    <p class="cxmeta" style="margin:0 0 10px">Turn this awarded engagement into a billable event — it flows into your existing invoicing chain (billable events → finance → invoice).</p>
    <form method="post" action="/connect-requirement" class="inline">
      <input type="hidden" name="id" value="<?= (int)$req['id'] ?>">
      <input type="hidden" name="action" value="send_to_billing">
      <button class="btn" type="submit">💷 Send to billing</button>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($matches): ?>
<div class="panel" style="margin-top:12px">
  <?php $ai_available = $ai_available ?? false; $ai_used = $ai_used ?? false; ?>
  <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap">
    <h3 style="margin:0 0 4px">Recommended professionals
      <?php if ($ai_used): ?><span class="cxpill info" style="font-size:10px">✨ AI-ranked</span><?php endif; ?></h3>
    <?php if ($ai_available): ?>
      <?php if ($ai_used): ?>
        <a class="btn secondary" href="/connect-requirement?id=<?= (int)$req['id'] ?>" style="font-size:13px">↺ Back to rule order</a>
      <?php else: ?>
        <a class="btn secondary" href="/connect-requirement?id=<?= (int)$req['id'] ?>&amp;ai=1" style="font-size:13px">✨ Rank with AI</a>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <p class="cxmeta" style="margin:0 0 10px">Ranked from your pool on skills fit, reputation, verified credentials and eligibility for this requirement<?php if ($ai_used): ?>, then re-ordered by AI for real-world fit (the rule score and eligibility are unchanged)<?php endif; ?>. Add one to the shortlist in a tap.</p>
  <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fill,minmax(240px,1fr))">
    <?php foreach ($matches as $m):
      $isPro = ($m['kind'] ?? 'inspector') === 'professional';
      if ($m['eligibility'] === 'UNVERIFIED') { $epLbl = 'Unverified'; $epCls = 'p-warn'; }
      else [$epLbl, $epCls] = function_exists('inspector_eligibility_pill') ? inspector_eligibility_pill($m['eligibility']) : ['—','p-mut'];
      $reasonCls = $m['reason'] === 'Best match' ? 'info' : ($m['eligibility']==='BLOCKED' ? 'bad' : 'ok');
    ?>
      <div style="border:1px solid var(--line,#e3ebea);border-radius:12px;padding:13px">
        <div style="display:flex;justify-content:space-between;align-items:start;gap:8px">
          <div><strong><?= e($m['name']) ?></strong>
            <?php if ($isPro): ?><span class="cxpill info" style="font-size:10px">Freelancer</span><?php endif; ?>
            <?php if (!empty($m['designation'])): ?><div class="cxmeta"><?= e($m['designation']) ?></div><?php endif; ?></div>
          <span class="cxpill <?= $reasonCls ?>"><?= e($m['reason']) ?></span>
        </div>
        <div class="cxmeta" style="margin:8px 0">
          <?php if (isset($m['trust']) && (int)$m['trust'] > 0): ?><strong>Trust <?= (int)$m['trust'] ?></strong><?php if (!empty($m['trust_band'])): ?> · <?= e($m['trust_band']) ?><?php endif; ?> · <?php elseif ($isPro): ?><strong><?= e($m['trust_band'] ?? 'New') ?></strong> · <?php endif; ?>
          <?php if ($m['stars'] !== null && (int)$m['jobs'] >= 3): ?>★ <?= e(number_format((float)$m['stars'],1)) ?> · <?php endif; ?>
          <?php if ((int)$m['verified'] > 0): ?><?= (int)$m['verified'] ?> verified · <?php endif; ?>
          <span class="cxpill <?= $epCls==='p-ok'?'ok':($epCls==='p-bad'?'bad':'warn') ?>"><?= e($epLbl) ?></span>
          · match <?= (int)$m['score'] ?>%
        </div>
        <?php if (!empty($m['ai_reason'])): ?><div class="cxmeta" style="margin:2px 0 6px"><span class="cxpill info" style="font-size:10px">✨ AI</span> <?= e($m['ai_reason']) ?></div><?php endif; ?>
        <?php if (!empty($m['skills'])): ?><div class="cxmeta" style="margin-bottom:8px"><?= e($m['skills']) ?></div><?php endif; ?>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <?php if ($isPro): ?>
            <?php if (!empty($m['passport_token'])): ?><a class="btn secondary" href="/p/<?= e($m['passport_token']) ?>" target="_blank" rel="noopener" style="font-size:13px">Passport ↗</a><?php endif; ?>
          <?php else: ?>
            <a class="btn secondary" href="/passport-share?id=<?= (int)$m['id'] ?>" style="font-size:13px">Passport</a>
          <?php endif; ?>
          <?php if ($m['eligibility'] !== 'BLOCKED'): ?>
          <form method="post" action="/connect-requirement" class="inline">
            <input type="hidden" name="id" value="<?= (int)$req['id'] ?>">
            <input type="hidden" name="<?= $isPro ? 'applicant_professional_id' : 'inspector_id' ?>" value="<?= (int)$m['id'] ?>">
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

<?php
  // K20 — Booking / engagement basis (man-days / months / deputation / continuous /
  // frequency). Shown once the requirement is awarded — the day is booked, on what basis?
  $engagement = $engagement ?? null; $engage_bases = $engage_bases ?? [];
  $isAwarded = strtoupper((string)($req['status'] ?? '')) === 'AWARDED';
  if ($isAwarded && $engage_bases):
    $eg = $engagement ?: [];
    $curBasis = strtoupper((string)($eg['basis'] ?? 'MAN_DAYS'));
?>
<div class="panel" style="margin-top:12px">
  <h3 style="margin:0 0 4px">📅 Booking / engagement</h3>
  <p class="cxmeta" style="margin:0 0 10px">On what basis is this person booked? This is what they see under "My bookings" and what finance bills.</p>
  <?php if ($engagement): $d = function_exists('connect_engage_describe') ? connect_engage_describe($engagement) : ['commitment'=>'','rate'=>'','total'=>null]; ?>
    <div class="approw"><div>
      <strong><?= e(function_exists('connect_engage_basis_label') ? connect_engage_basis_label($engagement['basis']) : $engagement['basis']) ?></strong>
      · <?= e($d['commitment']) ?><?php if ($d['rate']): ?> · <?= e($d['rate']) ?><?php endif; ?>
      <?php $exM = strtoupper((string)($engagement['rate_inclusive'] ?? 'INCLUSIVE')) === 'EXCLUSIVE'; ?>
      · <?= $exM ? 'Fee + expenses' : 'All-inclusive' ?>
      <?php if ($d['total'] !== null): ?> · est. ₹<?= number_format((int)$d['total']) ?><?php endif; ?>
      <div class="cxmeta">Status: <?= e(ucfirst(strtolower((string)$engagement['status']))) ?><?php if (!empty($engagement['subject_name'])): ?> · <?= e($engagement['subject_name']) ?><?php endif; ?><?php if (function_exists('connect_engv_summary_for_subject') && !empty($engagement['subject_kind'])): $vs = connect_engv_summary_for_subject($engagement['subject_kind'], (int)$engagement['subject_id']); if ($vs['total'] > 0): ?> · <?= (int)$vs['total'] ?> voucher<?= $vs['total']===1?'':'s' ?> (<?= (int)$vs['submitted']+(int)$vs['approved'] ?> pending)<?php endif; endif; ?></div>
    </div>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <?php foreach (['ACTIVE'=>'Mark active','COMPLETED'=>'Mark completed','CANCELLED'=>'Cancel'] as $s=>$lbl): if (strtoupper((string)$engagement['status'])===$s) continue; ?>
          <form method="post" action="/connect-requirement" class="inline">
            <input type="hidden" name="id" value="<?= (int)$req['id'] ?>"><input type="hidden" name="action" value="engagement_status">
            <input type="hidden" name="engagement_id" value="<?= (int)$engagement['id'] ?>"><input type="hidden" name="status" value="<?= e($s) ?>">
            <button class="btn secondary" type="submit"><?= e($lbl) ?></button>
          </form>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
  <details<?= $engagement ? '' : ' open' ?> style="margin-top:8px">
    <summary style="cursor:pointer;font-weight:600;font-size:14px"><?= $engagement ? 'Edit booking' : 'Record the booking' ?></summary>
    <form method="post" action="/connect-requirement" style="margin-top:10px">
      <input type="hidden" name="id" value="<?= (int)$req['id'] ?>"><input type="hidden" name="action" value="book_engagement">
      <label style="font-size:13px;font-weight:600">Basis</label>
      <select name="basis" id="egBasis" onchange="egSync()" style="width:100%;padding:9px;border:1px solid var(--line,#ddd);border-radius:9px;margin:4px 0 10px">
        <?php foreach ($engage_bases as $code=>$b): ?><option value="<?= e($code) ?>" <?= $curBasis===$code?'selected':'' ?>><?= e($b['label']) ?></option><?php endforeach; ?>
      </select>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div><label style="font-size:13px;font-weight:600">Rate ₹</label><input type="number" name="rate" value="<?= (int)($eg['rate'] ?? 0) ?: '' ?>" style="width:100%;padding:9px;border:1px solid var(--line,#ddd);border-radius:9px"></div>
        <div><label style="font-size:13px;font-weight:600">Per</label>
          <select name="rate_unit" style="width:100%;padding:9px;border:1px solid var(--line,#ddd);border-radius:9px">
            <?php foreach (['day'=>'day','month'=>'month','visit'=>'visit'] as $u=>$ul): ?><option value="<?= $u ?>" <?= ($eg['rate_unit'] ?? '')===$u?'selected':'' ?>><?= e($ul) ?></option><?php endforeach; ?>
          </select></div>
        <div id="egQtyWrap"><label style="font-size:13px;font-weight:600" id="egQtyLabel">Quantity</label><input type="number" step="0.5" name="quantity" value="<?= ($eg['quantity'] ?? 0) ? rtrim(rtrim((string)$eg['quantity'],'0'),'.') : '' ?>" style="width:100%;padding:9px;border:1px solid var(--line,#ddd);border-radius:9px"></div>
        <div id="egFreqWrap"><label style="font-size:13px;font-weight:600">Frequency</label><input type="text" name="frequency_note" value="<?= e($eg['frequency_note'] ?? '') ?>" placeholder="e.g. 2 days / week" style="width:100%;padding:9px;border:1px solid var(--line,#ddd);border-radius:9px"></div>
        <div><label style="font-size:13px;font-weight:600">Start date</label><input type="date" name="start_date" value="<?= e(substr((string)($eg['start_date'] ?? ''),0,10)) ?>" style="width:100%;padding:9px;border:1px solid var(--line,#ddd);border-radius:9px"></div>
        <div id="egEndWrap"><label style="font-size:13px;font-weight:600">End date</label><input type="date" name="end_date" value="<?= e(substr((string)($eg['end_date'] ?? ''),0,10)) ?>" style="width:100%;padding:9px;border:1px solid var(--line,#ddd);border-radius:9px"></div>
        <?php
          $curModel = strtoupper((string)($eg['rate_inclusive'] ?? ($req['rate_inclusive'] ?? 'INCLUSIVE')));
          $curCad   = strtoupper((string)($eg['voucher_cadence'] ?? ($req['voucher_cadence'] ?? 'PER_DEPLOYMENT')));
          $rateModels = function_exists('connect_engage_rate_models') ? connect_engage_rate_models() : [];
          $cadences   = function_exists('connect_engage_voucher_cadences') ? connect_engage_voucher_cadences() : [];
        ?>
        <div><label style="font-size:13px;font-weight:600">Rate model</label>
          <select name="rate_inclusive" style="width:100%;padding:9px;border:1px solid var(--line,#ddd);border-radius:9px">
            <?php foreach ($rateModels as $mk=>$mv): ?><option value="<?= e($mk) ?>" <?= $curModel===$mk?'selected':'' ?>><?= e($mv['label']) ?></option><?php endforeach; ?>
          </select></div>
        <div><label style="font-size:13px;font-weight:600">Voucher cadence</label>
          <select name="voucher_cadence" style="width:100%;padding:9px;border:1px solid var(--line,#ddd);border-radius:9px">
            <?php foreach ($cadences as $ck=>$cv): ?><option value="<?= e($ck) ?>" <?= $curCad===$ck?'selected':'' ?>><?= e($cv['label']) ?></option><?php endforeach; ?>
          </select></div>
      </div>
      <p class="cxmeta" style="margin:6px 0 0"><strong>All-inclusive</strong> = the rate covers travel, hotel, conveyance &amp; allowances. <strong>Fee only</strong> = those are reimbursed on the person's voucher against receipts.</p>
      <label style="font-size:13px;font-weight:600;margin-top:8px;display:block">Notes</label>
      <input type="text" name="notes" value="<?= e($eg['notes'] ?? '') ?>" style="width:100%;padding:9px;border:1px solid var(--line,#ddd);border-radius:9px;margin-bottom:10px">
      <button class="btn" type="submit"><?= $engagement ? 'Update booking' : 'Record booking' ?></button>
    </form>
  </details>
</div>
<script>
  var EGB = <?= json_encode(array_map(fn($b)=>['q'=>!empty($b['needs_qty']),'f'=>!empty($b['needs_freq']),'e'=>!empty($b['needs_end']),'ql'=>$b['qty_label']],$engage_bases)) ?>;
  function egSync(){ var v=document.getElementById('egBasis').value, c=EGB[v]||{};
    document.getElementById('egQtyWrap').style.display=c.q?'':'none';
    document.getElementById('egFreqWrap').style.display=c.f?'':'none';
    document.getElementById('egEndWrap').style.display=c.e?'':'none';
    if(c.ql) document.getElementById('egQtyLabel').textContent=c.ql; }
  egSync();
</script>

<?php // K21 — Engagement vouchers: the desk approves/pays claims, and raises one
      // for an on-roll inspector or agency-bench person (who have no portal). A
      // marketplace freelancer raises their own in /pro; the desk still approves. ?>
<?php if ($engagement):
  $engv_vouchers = $engv_vouchers ?? []; $engv_heads = $engv_heads ?? [];
  $exclusiveEng = strtoupper((string)($engagement['rate_inclusive'] ?? 'INCLUSIVE')) === 'EXCLUSIVE';
  $engUnit = (string)($engagement['rate_unit'] ?? 'day');
  $vpill = function ($s) {
    $s = strtoupper((string)$s);
    $m = ['DRAFT'=>['Draft','#5b6b6a'],'SUBMITTED'=>['Submitted','#1858a8'],'APPROVED'=>['Approved','#0f7d5a'],'PAID'=>['Paid','#0f7d5a'],'REJECTED'=>['Sent back','#9a2a2a']];
    [$l,$c] = $m[$s] ?? $m['DRAFT'];
    return '<span style="display:inline-block;padding:2px 9px;border-radius:999px;font-size:12px;font-weight:700;color:'.$c.';background:'.$c.'1a">'.e($l).'</span>';
  };
  $vinr = fn($n) => '₹'.number_format((int)round((float)$n));
?>
<div class="panel" style="margin-top:14px">
  <h3 style="margin:0 0 4px">🧾 Engagement vouchers</h3>
  <p class="cxmeta" style="margin:0 0 10px">
    <?= $exclusiveEng ? 'Fee-only (exclusive) rate — claims carry travel / hotel / conveyance / allowances against receipts.' : 'All-inclusive rate — claims carry the fee only.' ?>
    A marketplace freelancer raises their own in the portal; here the desk approves and pays, and can raise one for an on-roll inspector or bench person.
  </p>

  <form method="post" action="/connect-requirement" style="margin:0 0 12px">
    <input type="hidden" name="id" value="<?= (int)$req['id'] ?>"><input type="hidden" name="action" value="engv_raise">
    <input type="hidden" name="engagement_id" value="<?= (int)$engagement['id'] ?>">
    <button class="btn sec" type="submit">+ Raise a voucher</button>
  </form>

  <?php if (!$engv_vouchers): ?>
    <p class="cxmeta" style="margin:0">No vouchers yet.</p>
  <?php else: foreach ($engv_vouchers as $vv):
    $vex = strtoupper((string)$vv['rate_inclusive']) === 'EXCLUSIVE';
    $vdraft = strtoupper((string)$vv['status']) === 'DRAFT';
    $vsub = strtoupper((string)$vv['status']) === 'SUBMITTED';
    $vapp = strtoupper((string)$vv['status']) === 'APPROVED';
  ?>
    <div style="border:1px solid var(--line,#e3ebea);border-radius:10px;padding:10px 12px;margin-bottom:8px">
      <div style="display:flex;justify-content:space-between;gap:8px;align-items:center;flex-wrap:wrap">
        <div><strong><?= e($vv['period_label'] ?: 'Voucher') ?></strong>
          <span class="cxmeta">· Fee <?= e($vinr($vv['fee_total'])) ?><?php if ($vex): ?> · Exp <?= e($vinr($vv['reimb_total'])) ?><?php endif; ?> · <strong>Total <?= e($vinr($vv['grand_total'])) ?></strong></span></div>
        <?= $vpill($vv['status']) ?>
      </div>

      <?php if ($vdraft): ?>
      <details style="margin-top:8px">
        <summary style="cursor:pointer;font-size:13px;font-weight:600">Add a <?= e($engUnit) ?></summary>
        <form method="post" action="/connect-requirement" style="margin-top:8px;display:grid;grid-template-columns:repeat(2,1fr);gap:8px">
          <input type="hidden" name="id" value="<?= (int)$req['id'] ?>"><input type="hidden" name="action" value="engv_add_line"><input type="hidden" name="voucher_id" value="<?= (int)$vv['id'] ?>">
          <label style="font-size:12px">Date<input type="date" name="work_date" style="width:100%;padding:7px;border:1px solid var(--line,#ddd);border-radius:8px"></label>
          <label style="font-size:12px"><?= e(ucfirst($engUnit)) ?>s<input type="number" name="units" value="1" min="0.5" step="0.5" style="width:100%;padding:7px;border:1px solid var(--line,#ddd);border-radius:8px"></label>
          <?php if ($vex) foreach ($engv_heads as $hk=>$hl): ?>
            <label style="font-size:12px"><?= e($hl) ?><input type="number" name="<?= e($hk) ?>" value="0" min="0" style="width:100%;padding:7px;border:1px solid var(--line,#ddd);border-radius:8px"></label>
          <?php endforeach; ?>
          <div style="grid-column:1/-1"><button class="btn" type="submit">Add <?= e($engUnit) ?></button></div>
        </form>
      </details>
      <form method="post" action="/connect-requirement" style="margin-top:8px" onsubmit="return confirm('Submit this voucher for approval?');">
        <input type="hidden" name="id" value="<?= (int)$req['id'] ?>"><input type="hidden" name="action" value="engv_status"><input type="hidden" name="voucher_id" value="<?= (int)$vv['id'] ?>"><input type="hidden" name="status" value="SUBMITTED">
        <button class="btn" type="submit" style="font-size:13px">Submit for approval</button>
      </form>
      <?php elseif ($vsub || $vapp): ?>
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px">
        <?php if ($vsub): ?>
          <?php foreach (['APPROVED'=>'Approve','REJECTED'=>'Send back'] as $st=>$lbl): ?>
          <form method="post" action="/connect-requirement" style="margin:0">
            <input type="hidden" name="id" value="<?= (int)$req['id'] ?>"><input type="hidden" name="action" value="engv_status"><input type="hidden" name="voucher_id" value="<?= (int)$vv['id'] ?>"><input type="hidden" name="status" value="<?= $st ?>">
            <button class="btn <?= $st==='REJECTED'?'sec':'' ?>" type="submit" style="font-size:13px"><?= $lbl ?></button>
          </form>
          <?php endforeach; ?>
        <?php else: ?>
          <?php foreach (['PAID'=>'Mark paid','REJECTED'=>'Send back'] as $st=>$lbl): ?>
          <form method="post" action="/connect-requirement" style="margin:0">
            <input type="hidden" name="id" value="<?= (int)$req['id'] ?>"><input type="hidden" name="action" value="engv_status"><input type="hidden" name="voucher_id" value="<?= (int)$vv['id'] ?>"><input type="hidden" name="status" value="<?= $st ?>">
            <button class="btn <?= $st==='REJECTED'?'sec':'' ?>" type="submit" style="font-size:13px"><?= $lbl ?></button>
          </form>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  <?php endforeach; endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php $bench_allocs = $bench_allocs ?? []; if ($bench_allocs): ?>
<div class="panel" style="margin-top:12px">
  <h3 style="margin:0 0 6px">🏗️ Agency crew allocated (<?= count($bench_allocs) ?>)</h3>
  <p class="cxmeta" style="margin:0 0 8px">People an agency has put forward from its own bench to fulfil this requirement.</p>
  <?php foreach ($bench_allocs as $ba): ?>
    <div class="approw"><div><strong><?= e($ba['bench_name']) ?></strong>
      <?php if (!empty($ba['job_title'])): ?><span class="cxmeta"> · <?= e($ba['job_title']) ?></span><?php endif; ?>
      <div class="cxmeta"><?= e($ba['agency_name'] ?? 'Agency') ?> · <?= e(ucfirst(strtolower((string)$ba['status']))) ?></div></div></div>
  <?php endforeach; ?>
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
        <?php if (function_exists('connect_msg_staff_can') && connect_msg_staff_can()):
          $mUnread = function_exists('connect_msg_thread_unread') ? connect_msg_thread_unread((int)$a['id'], 'staff', (int)(function_exists('current_user') ? (current_user()['id'] ?? 0) : 0)) : 0; ?>
          <a class="btn secondary" href="/connect-messages?a=<?= (int)$a['id'] ?>">💬 Message<?php if ($mUnread > 0): ?> (<?= (int)$mUnread ?>)<?php endif; ?></a>
        <?php endif; ?>
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
