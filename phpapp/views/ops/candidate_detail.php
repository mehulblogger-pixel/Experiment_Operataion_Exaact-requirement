<?php
  $stageBadge = ['RECEIVED'=>'AMBER','SUBMITTED'=>'AMBER','SHORTLISTED'=>'AMBER','INTERVIEW'=>'AMBER',
                'HOLD'=>'AMBER','REJECTED'=>'RED','ACCEPTED'=>'GREEN','WITHDRAWN'=>'RED'];
  $cur = $cand['stage'];
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/candidates"><?= e(TP('candidate')) ?></a> › <?= e($cand['cand_code'] ?: candidate_name($cand)) ?></div>
<div class="master-head">
  <div><h1><?= e(candidate_name($cand)) ?>
      <span class="badge <?= $stageBadge[$cur] ?? 'AMBER' ?>" style="vertical-align:middle"><?= e(lk_options_or('candidate_stage', CAND_STAGES)[$cur] ?? $cur) ?></span></h1>
    <p class="sub"><?= e($cand['cand_code']) ?><?= $cand['trade_label']?' · '.e($cand['trade_label']):'' ?><?= $cand['skill_label']?' / '.e($cand['skill_label']):'' ?> · <?= e(lk_options_or('candidate_source', CAND_SOURCES)[$cand['source']] ?? $cand['source']) ?></p></div>
  <div class="row-actions">
    <a class="btn secondary" href="/candidate-edit?id=<?= (int)$cand['id'] ?>">Edit</a>
    <a class="btn secondary" href="/candidates">← Back</a>
  </div>
</div>

<?php // §11 — heads-up when other records look like the same person.
$dupes = $dupes ?? []; $subDupes = $subDupes ?? [];
if ($dupes): ?>
<div class="panel" style="border-left:4px solid var(--amber,#d97706);background:#fffdf5;padding:12px 15px">
  <b style="color:#92400e">⚠ Possible duplicate<?= count($dupes) > 1 ? 's' : '' ?>:</b>
  <?php foreach ($dupes as $i => $dp): ?><?= $i ? ' · ' : ' ' ?><a href="/candidate?id=<?= (int)$dp['id'] ?>"><?= e(trim(($dp['first_name'] ?? '') . ' ' . ($dp['last_name'] ?? ''))) ?> (<?= e($dp['cand_code']) ?>)</a> <span class="pill <?= $dp['confidence'] >= 90 ? 'p-bad' : 'p-warn' ?>" style="font-size:10.5px"><?= (int)$dp['confidence'] ?>% · <?= e($dp['reasons']) ?></span><?php endforeach; ?>
  <span class="muted"> — same person? Open the other record instead of keeping two.</span>
</div>
<?php endif; ?>

<div data-tabs data-tabs-key="ct" data-tabs-order="Overview,Recruitment,CV,Timeline">
<section data-tab="Overview">
<div class="panel">
  <h3 class="tab-sub">Candidate details</h3>
  <div class="kv-grid">
    <div><span class="k">Client</span><span class="v"><?= e($cand['client_disp'] ?: $cand['client_name'] ?: '—') ?></span></div>
    <div><span class="k">Against call</span><span class="v"><?= $cand['call_id'] ? '<a href="/call?id='.(int)$cand['call_id'].'">'.e($cand['call_code']).'</a>' : '—' ?></span></div>
    <div><span class="k">Proposed site</span><span class="v"><?= e($cand['proposed_site'] ?: '—') ?></span></div>
    <div><span class="k"><?= e(T("sbu")) ?></span><span class="v"><?= e(lk_options_or('sbu', OPS_SBUS)[$cand['sbu']] ?? $cand['sbu'] ?: '—') ?></span></div>
    <div><span class="k">Designation</span><span class="v"><?= e(lk_options_or('designation', DESIGNATIONS)[$cand['designation']] ?? $cand['designation'] ?: '—') ?></span></div>
    <div><span class="k">Experience</span><span class="v"><?= e(rtrim(rtrim((string)($cand['experience_years'] ?? 0), '0'), '.') ?: '0') ?> yrs</span></div>
    <div><span class="k">Agency</span><span class="v"><?= e($cand['agency'] ?: '—') ?></span></div>
    <div><span class="k">Email</span><span class="v"><?= e($cand['email'] ?: '—') ?></span></div>
    <div><span class="k">Mobile</span><span class="v"><?= e($cand['mobile'] ?: '—') ?></span></div>
    <div><span class="k">Expected rate</span><span class="v"><?= $cand['expected_rate']>0 ? cur_sym().number_format((float)$cand['expected_rate'],0).' ('.e(lk_options_or('rate_type', RATE_TYPES)[$cand['rate_type']] ?? $cand['rate_type']).')' : '—' ?></span></div>
    <div><span class="k">CV received</span><span class="v"><?= e($cand['cv_received_date'] ?: '—') ?></span></div>
    <div><span class="k">CV file</span><span class="v"><?= $cand['cv_link'] ? '<a href="'.e($cand['cv_link']).'" target="_blank" rel="noopener">Open CV ↗</a>' : '—' ?></span></div>
    <?php if (function_exists('custom_display')) foreach (custom_display('candidate', $cand['id']) as $cf): ?>
      <div><span class="k"><?= e($cf['label']) ?></span><span class="v"><?= e($cf['value'] ?: '—') ?></span></div>
    <?php endforeach; ?>
  </div>
  <?php if ($cand['remarks']): ?><p class="muted" style="margin-top:8px">Remarks: <?= e($cand['remarks']) ?></p><?php endif; ?>
  <?php if ($cand['inspector_id']): ?>
    <p class="msg msg-success" style="margin-top:10px">Hired — this candidate is now <?= e(Tl('engineer')) ?>
      <a href="/m/inspectors/edit?id=<?= (int)$cand['inspector_id'] ?>">#<?= (int)$cand['inspector_id'] ?></a>. Allocate <?= e(Tlp('job')) ?> from the <?= e(THP('job')) ?> screen.</p>
  <?php endif; ?>
</div>

<?php // Phase 6 — the same person's other applications (one row per application; nothing merged).
$personApps = $personApps ?? [];
if ($personApps):
  $stageOpt = lk_options_or('candidate_stage', CAND_STAGES);
  $myRef = trim((string)($cand['person_ref'] ?? ''));
  $stTone = fn($s) => in_array($s, ['ACCEPTED'], true) ? 'p-ok' : (in_array($s, ['REJECTED','WITHDRAWN','OFFER_DECLINED'], true) ? 'p-bad' : 'p-warn');
?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">This person&rsquo;s other applications
    <span class="pill p-info" style="font-size:11px;margin-left:4px"><?= count($personApps) ?></span>
    <?php if ($myRef !== ''): ?><span class="pill p-ok" style="font-size:11px;margin-left:4px">linked · <?= e($myRef) ?></span><?php endif; ?>
  </h3>
  <p class="muted" style="font-size:12px;margin:0 0 8px">One human, several applications — each is kept as its own record so no history is lost. Matched by <?= $myRef !== '' ? 'an explicit link' : 'phone / e-mail' ?>.</p>
  <div style="overflow-x:auto">
  <table class="grid" style="min-width:640px">
    <thead><tr><th>Application</th><th>Role</th><th>Against</th><th>Stage</th><th>When</th><?php if (is_coordinator_level() && $myRef === ''): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
      <?php foreach ($personApps as $a): $an = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')); ?>
      <tr>
        <td><a href="/candidate?id=<?= (int)$a['id'] ?>"><?= e($a['cand_code'] ?: ('#'.$a['id'])) ?></a><?php if ($an && $an !== trim(($cand['first_name'] ?? '').' '.($cand['last_name'] ?? ''))): ?> <span class="muted">· <?= e($an) ?></span><?php endif; ?></td>
        <td><?= e(lk_options_or('designation', DESIGNATIONS)[$a['designation']] ?? $a['designation'] ?: '—') ?></td>
        <td><?= $a['req_code'] ? e($a['req_code']) : e($a['client_name'] ?: '—') ?></td>
        <td><span class="pill <?= $stTone($a['stage']) ?>" style="font-size:11px"><?= e($stageOpt[$a['stage']] ?? $a['stage']) ?></span></td>
        <td class="muted" style="font-size:12px"><?= e(substr((string)($a['created_at'] ?? ''),0,10) ?: '—') ?></td>
        <?php if (is_coordinator_level() && $myRef === ''): ?>
        <td style="text-align:right">
          <?php if (trim((string)($a['person_ref'] ?? '')) === ''): ?>
          <form method="post" action="/candidate-link-person?id=<?= (int)$cand['id'] ?>" style="display:inline" onsubmit="return confirm('Record these two applications as the same person?')">
            <input type="hidden" name="other_id" value="<?= (int)$a['id'] ?>">
            <button class="btn btn-ghost" type="submit" style="padding:2px 9px;font-size:12px">Same person</button>
          </form>
          <?php else: ?><span class="pill p-ok" style="font-size:11px">linked</span><?php endif; ?>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>

<?php // P11 — this person is also a marketplace professional (read-only convergence; nothing merged).
$proMatches = $proMatches ?? []; $proLink = $proLink ?? null;
$linkedProId = $proLink ? (int)$proLink['professional_id'] : 0;
if ($proMatches || $proLink):
  $rLabel = ['mobile' => 'same mobile', 'email' => 'same e-mail', 'name' => 'same name'];
  $rTone  = ['mobile' => 'p-ok', 'email' => 'p-ok', 'name' => 'p-warn'];
  $canLink = is_coordinator_level();
?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Also on the marketplace
    <span class="pill p-info" style="font-size:11px;margin-left:4px"><?= count($proMatches) ?></span>
    <?php if ($proLink): ?><span class="pill p-ok" style="font-size:11px;margin-left:4px">✓ confirmed same person</span><?php endif; ?>
  </h3>
  <p class="muted" style="font-size:12px;margin:0 0 8px">This candidate matches a known <strong>marketplace professional</strong> — the same person is already on the bench / passport. Matched by mobile / e-mail / name. <strong>Confirming</strong> records them as one person; nothing is merged and each pool keeps its own record, so it can be unlinked any time.</p>
  <?php // Gap-8 — once linked, show the ONE person resolved across every pool (read-view, no merge).
  $person = $person ?? null;
  if ($person && !empty($person['linked'])):
    $pl = []; if (($person['pools']['professional'] ?? 0) > 0) $pl[] = 'marketplace'; if (($person['pools']['inspector'] ?? 0) > 0) $pl[] = 'operations inspector'; if (($person['pools']['candidate'] ?? 0) > 0) $pl[] = 'recruitment'; ?>
  <p class="msg" style="margin:0 0 8px;font-size:12.5px;background:var(--panel-2,#eef1f3);border-radius:6px;padding:8px 12px">
    <strong>🔗 One person across the platform.</strong>
    <?= e($person['name'] ?: 'This person') ?> is present in <?= e(implode(' + ', $pl)) ?>.
    <strong><?= (int)$person['credentials'] ?></strong> credential<?= (int)$person['credentials'] === 1 ? '' : 's' ?> across all pools<?= (int)$person['verified'] > 0 ? ' · <strong>' . (int)$person['verified'] . '</strong> verified' : '' ?>,
    read on the one verification ladder. Nothing is merged.
  </p>
  <?php endif; ?>
  <div style="overflow-x:auto">
  <table class="grid" style="min-width:600px">
    <thead><tr><th>Professional</th><th>Verification</th><th>Availability</th><th>Matched by</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($proMatches as $pm): $isLinked = $linkedProId === (int)$pm['pro_id']; ?>
      <tr>
        <td><?= e($pm['name'] ?: ('#' . $pm['pro_id'])) ?></td>
        <td><span class="pill <?= in_array(strtolower((string)$pm['verification_tier']), ['verified','id_verified','engaged'], true) ? 'p-ok' : 'p-mut' ?>" style="font-size:11px"><?= e($pm['verification_tier'] ?: '—') ?></span></td>
        <td class="muted" style="font-size:12px"><?= e($pm['availability'] ?: '—') ?></td>
        <td><span class="pill <?= $rTone[$pm['reason']] ?? 'p-mut' ?>" style="font-size:11px"><?= e($rLabel[$pm['reason']] ?? $pm['reason']) ?></span></td>
        <td style="text-align:right">
          <?php if ($isLinked): ?>
            <span class="pill p-ok" style="font-size:11px">✓ Confirmed</span>
            <?php if ($canLink): ?>
            <form method="post" action="/candidate-unlink-pro?id=<?= (int)$cand['id'] ?>" style="display:inline" onsubmit="return confirm('Remove the confirmed link? Neither record is deleted.')">
              <input type="hidden" name="link_id" value="<?= (int)$proLink['id'] ?>">
              <button class="btn btn-ghost" type="submit" style="padding:2px 9px;font-size:12px">Unlink</button>
            </form>
            <?php endif; ?>
          <?php elseif ($proLink): ?>
            <span class="muted" style="font-size:11px">another confirmed</span>
          <?php elseif ($canLink): ?>
            <form method="post" action="/candidate-link-pro?id=<?= (int)$cand['id'] ?>" style="display:inline" onsubmit="return confirm('Confirm this candidate and marketplace professional are the same person? Nothing is merged.')">
              <input type="hidden" name="pro_id" value="<?= (int)$pm['pro_id'] ?>">
              <button class="btn btn-ghost" type="submit" style="padding:2px 9px;font-size:12px">Confirm same person</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>

<?php // §16 — explainable workforce fit against the linked requirement.
$fit = $fit ?? null; $readiness = $readiness ?? null; $linkReq = $linkReq ?? null;
if (!empty($fit) && !empty($linkReq)): [$fl, $ftone] = recruit_fit_band($fit['score']); ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Workforce fit <span class="muted">— against <a href="/requisition?id=<?= (int)$linkReq['id'] ?>"><?= e($linkReq['req_code']) ?></a> · <?= e(DESIGNATIONS[$linkReq['designation']] ?? ($linkReq['designation'] ?? '')) ?></span></h3>
  <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
    <div style="text-align:center;min-width:82px"><div style="font-size:30px;font-weight:800;line-height:1;color:var(--<?= $fit['score']>=80?'ok':($fit['score']>=55?'warn':'bad') ?>,#333)"><?= (int)$fit['score'] ?>%</div><div class="pill <?= e($ftone) ?>" style="margin-top:4px"><?= e($fl) ?></div></div>
    <div style="flex:1;min-width:240px;display:flex;flex-wrap:wrap;gap:6px">
      <?php foreach ($fit['factors'] as $f): $ic = $f['state'] === 'ok' ? '✓' : ($f['state'] === 'part' ? '~' : '✕'); $tone = $f['state'] === 'ok' ? 'p-ok' : ($f['state'] === 'part' ? 'p-mut' : 'p-bad'); ?>
        <span class="pill <?= $tone ?>" style="font-size:11px"><?= $ic ?> <?= e($f['label']) ?><?= $f['note'] ? ' · ' . e($f['note']) : '' ?></span>
      <?php endforeach; ?>
    </div>
  </div>
  <p class="muted" style="font-size:11.5px;margin:8px 0 0">A guide to shortlisting from the recorded data — not an automatic hiring decision.</p>
</div>
<?php endif; ?>

<?php // §17 — deployment readiness for a candidate heading to mobilisation.
if (!empty($readiness) && in_array($cand['stage'], ['INTERVIEW','OFFERED','ACCEPTED'], true) && ($readiness['total'] ?? 0) > 0): ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Deployment readiness</h3>
  <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
    <div style="text-align:center;min-width:82px"><div style="font-size:30px;font-weight:800;line-height:1;color:var(--<?= !empty($readiness['ready'])?'ok':'warn' ?>,#333)"><?= (int)$readiness['pct'] ?>%</div><div class="muted" style="font-size:11px;margin-top:4px"><?= (int)$readiness['done'] ?>/<?= (int)$readiness['total'] ?> ready</div></div>
    <div style="flex:1;min-width:240px;display:flex;flex-wrap:wrap;gap:6px">
      <?php foreach ($readiness['items'] as $it): if (empty($it['req']) && !empty($it['ok'])) continue; ?>
        <span class="pill <?= !empty($it['ok']) ? 'p-ok' : 'p-warn' ?>" style="font-size:11px"><?= !empty($it['ok']) ? '✓' : '○' ?> <?= e($it['label']) ?><?= !empty($it['note']) ? ' · ' . e($it['note']) : '' ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php // Phase 5 — this placement's commercials: estimate → approved → actual.
$asgComm = $asgComm ?? null; $asgPacket = $asgPacket ?? null;
$seeSal = function_exists('can_see_salary') ? can_see_salary() : is_coordinator_level();
if (!empty($asgComm) && !empty($linkReq) && $seeSal):
  $C = $asgComm; $sym = cur_sym();
  $basisLbl = REQ_RATE_BASIS[$C['basis']] ?? $C['basis'];
  $tier = function($t, $label, $tone) use ($sym) {
    if (!$t) return '<div class="kpi"><div class="k">'.$label.'</div><div class="v" style="color:var(--muted)">—</div><div class="d">not recorded yet</div></div>';
    $m = $t['margin']; $mc = $m >= 0 ? 'up' : 'down';
    return '<div class="kpi"><div class="k">'.$label.'</div>'
      .'<div class="v">'.fmoney_short($t['rev']).'</div>'
      .'<div class="d">cost '.fmoney_short($t['cost']).' · <b class="'.$mc.'">'.fmoney_short($t['profit']).'</b> · '
      .($m>=0?'':'−').number_format(abs($m),1).'% margin</div></div>';
  };
  $vChip = function($v, $unit='') use ($sym) {
    if ($v === null) return '';
    $s = $v >= 0 ? 'p-ok' : 'p-bad'; $sign = $v > 0 ? '+' : ($v < 0 ? '−' : '');
    return '<span class="pill '.$s.'" style="font-size:11px">'.$sign.fmoney_short(abs($v)).' '.$unit.'</span>';
  };
?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Placement commercials
    <span class="muted">— against <a href="/requisition?id=<?= (int)$linkReq['id'] ?>"><?= e($linkReq['req_code']) ?></a> · <?= rtrim(rtrim(number_format($C['months'],2),'0'),'.') ?> mo · <?= e($basisLbl) ?></span>
    <?php if ($C['status'] === 'APPROVED'): ?><span class="pill p-ok" style="font-size:11px;margin-left:6px">Approved</span>
    <?php elseif ($C['status'] === 'ACTUAL'): ?><span class="pill p-info" style="font-size:11px;margin-left:6px">Actuals in</span>
    <?php else: ?><span class="pill p-warn" style="font-size:11px;margin-left:6px">Estimate only</span><?php endif; ?>
  </h3>
  <div class="kpi-row" style="grid-template-columns:repeat(3,1fr)">
    <?= $tier($C['est'], 'Estimate <span class="muted" style="font-weight:400">(from requirement)</span>', 'mut') ?>
    <?= $tier($C['appr'], 'Approved <span class="muted" style="font-weight:400">(locked for this hire)</span>', 'ok') ?>
    <?= $tier($C['act'], 'Actual <span class="muted" style="font-weight:400">(billed &amp; paid)</span>', 'info') ?>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:8px;font-size:12px">
    <span class="muted">Variance vs estimate:</span> profit <?= $vChip($C['var']['appr_profit']) ?: '<span class="pill p-mut" style="font-size:11px">on plan</span>' ?>
    <?php if ($C['var']['act_profit'] !== null): ?><span class="muted" style="margin-left:8px">actual vs approved:</span> profit <?= $vChip($C['var']['act_profit']) ?><?php endif; ?>
    <?php if ($C['approved_by']): ?><span class="muted" style="margin-left:auto">Approved by <?= e($C['approved_by']) ?><?= $C['approved_at'] ? ' · '.e(substr($C['approved_at'],0,10)) : '' ?><?= $C['ref'] ? ' · ref '.e($C['ref']) : '' ?></span><?php endif; ?>
  </div>

  <?php if (is_coordinator_level()): ?>
  <details style="margin-top:10px"<?= $C['status']==='' ? ' open' : '' ?>>
    <summary style="cursor:pointer;font-weight:600;font-size:13px"><?= $C['approved'] ? 'Revise approved commercials' : 'Approve the commercials for this placement' ?></summary>
    <form method="post" action="/candidate-commercial?id=<?= (int)$cand['id'] ?>" style="margin-top:8px">
      <div class="form-grid">
        <div class="ff"><label>Billing rate to client (<?= e($sym) ?>)</label><input class="form-control" type="number" step="0.01" name="bill_rate" value="<?= $C['bill_rate']>0 ? e(number_format($C['bill_rate'],2,'.','')) : '' ?>" placeholder="<?= e(number_format((float)($linkReq['billing_rate'] ?? 0),0)) ?>"></div>
        <div class="ff"><label>Basis</label><select class="form-control" name="bill_basis"><?php foreach (REQ_RATE_BASIS as $k=>$v): ?><option value="<?= e($k) ?>"<?= $C['basis']===$k?' selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
        <div class="ff"><label>Our cost — monthly (<?= e($sym) ?>)</label><input class="form-control" type="number" step="0.01" name="cost_rate" value="<?= $C['cost_rate']>0 ? e(number_format($C['cost_rate'],2,'.','')) : '' ?>" placeholder="<?= e(number_format((float)($linkReq['budgeted_cost'] ?? 0),0)) ?>"></div>
        <div class="ff"><label>Duration (months)</label><input class="form-control" type="number" step="0.01" name="months" value="<?= $C['months']>0 ? e(rtrim(rtrim(number_format($C['months'],2,'.',''),'0'),'.')) : '' ?>"></div>
        <div class="ff"><label>One-time cost (<?= e($sym) ?>) <span class="muted">placement fee etc.</span></label><input class="form-control" type="number" step="0.01" name="onetime" value="<?= (float)$C['onetime']>0 ? e(number_format((float)$C['onetime'],2,'.','')) : '' ?>"></div>
        <div class="ff"><label>Approval ref <span class="muted">optional</span></label><input class="form-control" name="ref" maxlength="60" value="<?= e($C['ref']) ?>"></div>
      </div>
      <p class="muted" style="font-size:12px;margin:4px 2px">Approving locks the rate we will bill and the cost we will carry for this person. The estimate stays on record for variance.</p>
      <div style="margin-top:6px"><button class="btn" type="submit">Approve commercials</button></div>
    </form>
    <?php if ($C['approved']): ?>
    <form method="post" action="/candidate-commercial?id=<?= (int)$cand['id'] ?>" style="margin-top:10px;padding-top:10px;border-top:1px solid var(--line)">
      <input type="hidden" name="mode" value="actual">
      <p style="font-weight:600;font-size:13px;margin:0 0 6px">Record the actuals once the placement has run</p>
      <div class="form-grid">
        <div class="ff"><label>Actual revenue billed (<?= e($sym) ?>)</label><input class="form-control" type="number" step="0.01" name="act_rev" value="<?= $C['act'] ? e(number_format($C['act']['rev'],2,'.','')) : '' ?>"></div>
        <div class="ff"><label>Actual cost incurred (<?= e($sym) ?>)</label><input class="form-control" type="number" step="0.01" name="act_cost" value="<?= $C['act'] ? e(number_format($C['act']['cost'],2,'.','')) : '' ?>"></div>
      </div>
      <div style="margin-top:6px"><button class="btn btn-ghost" type="submit">Save actuals</button></div>
    </form>
    <?php endif; ?>
  </details>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php // Phase 5 — billing readiness packet (reuses the deputation bill gate).
if (!empty($asgPacket) && $seeSal && !empty($asgPacket['checks'])): $P = $asgPacket; ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Billing readiness
    <?php if ($P['ready']): ?><span class="pill p-ok" style="font-size:11px;margin-left:6px">Ready to bill</span>
    <?php else: ?><span class="pill p-warn" style="font-size:11px;margin-left:6px">Not ready</span><?php endif; ?>
  </h3>
  <div style="display:flex;flex-wrap:wrap;gap:6px">
    <?php foreach ($P['checks'] as $ck): ?>
      <span class="pill <?= !empty($ck['ok']) ? 'p-ok' : 'p-warn' ?>" style="font-size:11px"><?= !empty($ck['ok']) ? '✓' : '○' ?> <?= e($ck['label']) ?><?= !empty($ck['hint']) && empty($ck['ok']) ? ' · ' . e($ck['hint']) : '' ?></span>
    <?php endforeach; ?>
  </div>
  <?php if ($P['ready']): ?>
    <p class="msg msg-success" style="margin-top:10px">This placement is ready to invoice at <?= e(cur_sym()).number_format($P['commercials']['appr']['rev'],0) ?> revenue<?php if (!empty($P['job'])): ?> — <a href="/job?id=<?= (int)$P['job']['id'] ?>">open the deputation</a> to raise the client bill<?php endif; ?>.</p>
  <?php else: ?>
    <p class="muted" style="font-size:12px;margin-top:8px">Clear the open items above, then this placement can be handed to billing. Chargeable-expense bills are enforced on the deputation itself.</p>
  <?php endif; ?>
</div>
<?php endif; ?>

</section>
<section data-tab="CV">
<!-- CV analysis + keyword search -->
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">CV analysis <span class="muted">— keywords are stored for searching</span></h3>
  <?php $kw = array_filter(array_map('trim', explode(',', (string)($cand['cv_keywords'] ?? '')))); ?>
  <?php if ($kw): ?>
    <div class="chip-row" style="margin-bottom:6px">
      <?php foreach ($kw as $k): ?><a class="ct" href="/candidates?q=<?= e(urlencode($k)) ?>" title="Find candidates with this keyword"><?= e($k) ?></a><?php endforeach; ?>
    </div>
    <p class="muted" style="font-size:12px">Analysed <?= e(substr((string)($cand['cv_analyzed_at'] ?? ''),0,10)) ?><?= $cand['cv_file_name']?' · '.e($cand['cv_file_name']):'' ?>. Click a keyword to find similar CVs.</p>
  <?php else: ?>
    <p class="sub">No keywords yet — upload the CV (.docx / .txt) or paste the text below and analyse.</p>
  <?php endif; ?>
  <?php if (is_coordinator_level()): ?>
  <form method="post" action="/candidate-cv?id=<?= (int)$cand['id'] ?>" enctype="multipart/form-data" style="margin-top:8px">
    <div class="form-grid">
      <div class="ff"><label>Upload CV (.docx / .txt / .pdf)</label><input class="form-control" type="file" name="cv_file" accept=".docx,.txt,.pdf"></div>
    </div>
    <div class="ff ff-wide"><label>…or paste CV text</label><textarea class="form-control" name="cv_text" rows="4" placeholder="Paste the CV text here for the most accurate keyword extraction"><?= e($cand['cv_text'] ?? '') ?></textarea></div>
    <div style="margin-top:8px"><button class="btn" type="submit">Analyse &amp; save keywords</button></div>
  </form>
  <?php endif; ?>
</div>

</section>
<section data-tab="Recruitment">
<!-- §20 client submission & interview tracking -->
<?php if (is_coordinator_level()): $fb = $cand['client_feedback'] ?? ''; $io = $cand['interview_outcome'] ?? ''; ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Client submission &amp; interview</h3>
  <?php // §12 — this same person already submitted to this client?
  if ($subDupes): ?>
  <div style="border-left:4px solid var(--bad,#b91c1c);background:#fef2f2;border-radius:8px;padding:10px 13px;margin-bottom:12px">
    <b style="color:#991b1b">⚠ Already submitted to this client.</b>
    <?php foreach ($subDupes as $sd): ?>
      <div style="font-size:13px;margin-top:3px"><a href="/candidate?id=<?= (int)$sd['id'] ?>"><?= e(trim(($sd['first_name'] ?? '') . ' ' . ($sd['last_name'] ?? ''))) ?> (<?= e($sd['cand_code']) ?>)</a> — submitted <?= e($sd['submitted_client_date'] ?: '—') ?><?= $sd['client_feedback'] ? ' · ' . e($sd['client_feedback']) : '' ?></div>
    <?php endforeach; ?>
    <p class="muted" style="font-size:12px;margin:6px 0 0">Recording a fresh submission for the same person needs the “submit anyway” tick below.</p>
  </div>
  <?php endif; ?>
  <form method="post" action="/candidate-client?id=<?= (int)$cand['id'] ?>">
    <?php if ($subDupes && trim((string)($cand['submitted_client_date'] ?? '')) === ''): ?>
    <label class="chk" style="display:inline-flex;gap:7px;margin:0 0 8px"><input type="checkbox" name="sub_ack" value="1"> I’ve checked the above — submit anyway</label>
    <?php endif; ?>
    <div class="form-grid">
      <div class="ff"><label>CV submitted to client on</label><input class="form-control" type="date" name="submitted_client_date" value="<?= e($cand['submitted_client_date'] ?? '') ?>"></div>
      <div class="ff"><label>Client feedback</label>
        <select class="form-control" name="client_feedback"><option value="">—</option>
          <?php foreach (['PENDING'=>'Awaiting','SHORTLISTED'=>'Shortlisted','REJECTED'=>'Rejected'] as $k=>$v): ?><option value="<?= $k ?>" <?= $fb===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label>Feedback date</label><input class="form-control" type="date" name="client_feedback_date" value="<?= e($cand['client_feedback_date'] ?? '') ?>"></div>

      <div class="ff" style="align-self:end"><label class="chk"><input type="checkbox" name="interview_required" value="1" <?= !empty($cand['interview_required'])?'checked':'' ?>> Interview required</label></div>
      <div class="ff"><label>Interview planned for</label><input class="form-control" type="date" name="interview_date" value="<?= e($cand['interview_date'] ?? '') ?>"></div>
      <div class="ff"><label>Interview completed on</label><input class="form-control" type="date" name="interview_done_date" value="<?= e($cand['interview_done_date'] ?? '') ?>"></div>

      <div class="ff"><label>Interview outcome</label>
        <select class="form-control" name="interview_outcome"><option value="">—</option>
          <?php foreach (['SELECTED'=>'Selected','REJECTED'=>'Rejected','HOLD'=>'On hold'] as $k=>$v): ?><option value="<?= $k ?>" <?= $io===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></div>
      <div class="ff ff-wide"><label>Client feedback note</label><input class="form-control" name="client_feedback_note" value="<?= e($cand['client_feedback_note'] ?? '') ?>"></div>
    </div>
    <div style="margin-top:8px"><button class="btn" type="submit">Save tracking</button></div>
  </form>
  <?php if ($io === 'SELECTED'): ?>
  <div style="margin-top:10px;padding:10px;border:1px solid var(--ok);border-radius:8px">
    <b style="color:var(--ok)">✓ Selected.</b> Request the candidate's credentials (CV, salary slips, IDs, certificates).
    <form method="post" action="/candidate-credential?id=<?= (int)$cand['id'] ?>" style="display:inline;margin-left:8px"><button class="btn small" type="submit"><?= !empty($cand['credential_requested'])?'Re-send credential request':'Send credential request' ?></button></form>
    <?php if (!empty($cand['credential_requested'])): ?><span class="pill p-ok" style="margin-left:6px">requested</span><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if (is_coordinator_level() && !in_array($cur, ['ACCEPTED','WITHDRAWN'], true)): ?>
<div class="panel">
  <h3 class="tab-sub">Move this candidate</h3>
  <form method="post" action="/candidate-stage?id=<?= (int)$cand['id'] ?>">
    <div class="form-grid">
      <div class="ff"><label>New stage</label>
        <select class="form-control" name="to_stage" id="cand_stage">
          <?php foreach (lk_options_or('candidate_stage', CAND_STAGES) as $k=>$v): if ($k===$cur) continue; ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff ff-wide"><label>Remark (decision note, interview feedback…)</label><input class="form-control" name="remark" placeholder="e.g. Client shortlisted; interview on 25th"></div>
    </div>
    <?php if (empty($cand['inspector_id'])): ?>
    <label class="chk" id="hire_chk" style="margin:8px 2px;display:none"><input type="checkbox" name="make_inspector" id="mk_insp" value="1"> On <strong>Accept</strong>, also add this person to Inspectors</label>
    <div id="hire_details" class="panel" style="display:none;background:var(--soft);margin-top:6px">
      <div class="form-grid">
        <div class="ff"><label>Supplied by agency <span class="muted">(optional)</span></label>
          <select class="form-control" name="agency_id" id="ag_sel"><option value="" data-type="" data-fee="0" data-monthly="0">— none / direct —</option>
            <?php foreach (agencies_list() as $a): ?><option value="<?= (int)$a['id'] ?>" data-type="<?= e($a['agency_type']) ?>" data-fee="<?= e($a['one_time_fee']) ?>" data-monthly="<?= e($a['monthly_rate']) ?>"><?= e($a['name']) ?> · <?= e(lk_options_or('agency_type', AGENCY_TYPES)[$a['agency_type']] ?? $a['agency_type']) ?></option><?php endforeach; ?>
          </select></div>
        <div class="ff"><label>On whose roll?</label>
          <select class="form-control" name="roll_type" id="roll_sel"><?php foreach (lk_options_or('roll_type', ROLL_TYPES) as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
        <div class="ff" id="fee_one"><label>One-time placement fee (<?= e(cur_sym()) ?>) <span class="muted">recruitment</span></label><input class="form-control" type="number" step="0.01" name="placement_fee" value=""></div>
        <div class="ff" id="fee_month"><label>Monthly agency charge (<?= e(cur_sym()) ?>) <span class="muted">manpower</span></label><input class="form-control" type="number" step="0.01" name="agency_cost" value=""></div>
      </div>
      <p class="muted" style="margin:2px 2px 0;font-size:12px">Recruitment → our roll + one-time fee (added to costing, one-time). Manpower → agency roll + monthly charge (their bill; we invoice the client our rate).</p>
    </div>
    <?php endif; ?>
    <?php // Phase 7 — capture WHERE and WHY when a candidate is being lost.
    $rccDropPoints = $rccDropPoints ?? []; $rccDropReasons = $rccDropReasons ?? []; ?>
    <div id="lost_details" class="panel" style="display:none;background:var(--soft);margin-top:6px">
      <div class="form-grid">
        <div class="ff"><label>Drop point <span class="muted">where in the pipeline</span></label>
          <select class="form-control" name="drop_point"><option value="">—</option><?php foreach ($rccDropPoints as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($cand['drop_point'] ?? '')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
        <div class="ff"><label>Drop reason <span class="muted">why</span></label>
          <select class="form-control" name="drop_reason"><option value="">—</option><?php foreach ($rccDropReasons as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($cand['drop_reason'] ?? '')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
      </div>
      <p class="muted" style="margin:2px 2px 0;font-size:12px">Recorded on the candidate and rolled up in the command centre’s “Where they drop off”.</p>
    </div>
    <div style="margin-top:8px"><button class="btn" type="submit">Update stage</button></div>
  </form>
</div>
<script>
  (function(){
    var sel = document.getElementById('cand_stage'), chk = document.getElementById('hire_chk');
    if (!sel) return;
    // Phase 6 — the installation's engagement mode defaults the direct (no-agency) hire.
    var engMode = <?= json_encode(function_exists('recruit_engagement_mode') ? recruit_engagement_mode() : 'BOTH') ?>;
    var mk = document.getElementById('mk_insp'), det = document.getElementById('hire_details');
    var ag = document.getElementById('ag_sel'), roll = document.getElementById('roll_sel');
    var feeOne = document.getElementById('fee_one'), feeMonth = document.getElementById('fee_month');
    var lost = document.getElementById('lost_details');
    var LOST_STAGES = {REJECTED:1, WITHDRAWN:1, OFFER_DECLINED:1, HOLD:1};
    function syncStage(){ if (chk) chk.style.display = (sel.value === 'ACCEPTED') ? 'inline-flex' : 'none'; if (sel.value!=='ACCEPTED' && det) det.style.display='none'; if (lost) lost.style.display = LOST_STAGES[sel.value] ? 'block' : 'none'; }
    function syncHire(){ if (det) det.style.display = (mk && mk.checked && sel.value==='ACCEPTED') ? 'block' : 'none'; }
    function syncAgency(){
      if (!ag) return; var o = ag.options[ag.selectedIndex], t = o.getAttribute('data-type');
      // No agency picked → fall back to the engagement mode (MANPOWER acts like a supply hire).
      var eff = (t === '') ? (engMode === 'MANPOWER' ? 'MANPOWER' : (engMode === 'RECRUITMENT' ? 'RECRUITMENT' : '')) : t;
      if (roll) roll.value = (eff === 'MANPOWER') ? 'AGENCY' : 'OWN';
      if (feeOne)   feeOne.style.display   = (eff === 'RECRUITMENT' || eff==='') ? '' : 'none';
      if (feeMonth) feeMonth.style.display = (eff === 'MANPOWER') ? '' : 'none';
      var f = feeOne && feeOne.querySelector('input'), m = feeMonth && feeMonth.querySelector('input');
      if (f && t==='RECRUITMENT' && !f.value) f.value = o.getAttribute('data-fee')||'';
      if (m && t==='MANPOWER' && !m.value) m.value = o.getAttribute('data-monthly')||'';
    }
    sel.addEventListener('change', function(){ syncStage(); syncHire(); });
    if (mk) mk.addEventListener('change', syncHire);
    if (ag) ag.addEventListener('change', syncAgency);
    syncStage(); syncHire(); syncAgency();
  })();
</script>
<?php endif; ?>

</section>
<section data-tab="Timeline">
<?php
// A single chronological story — stage changes plus the recruitment milestones
// recorded on the candidate (CV, submission, feedback, interview, decision).
$sname = fn($s) => lk_options_or('candidate_stage', CAND_STAGES)[$s] ?? $s;
$tl = [];
foreach ($events as $ev) {
    $tl[] = ['at' => (string)$ev['created_at'], 'icon' => '●',
        'title' => ($ev['from_stage'] ? $sname($ev['from_stage']) . ' → ' : '') . $sname($ev['to_stage']),
        'meta' => (string)($ev['remark'] ?? ''), 'by' => (string)($ev['actor'] ?? ''), 'tone' => 'brand'];
}
$mile = [
    ['cv_analyzed_at', '📄', 'CV analysed', ''],
    ['submitted_client_date', '📤', 'Submitted to client', ''],
    ['client_feedback_date', '💬', 'Client feedback', $cand['client_feedback'] ?? ''],
    ['interview_date', '🗓️', 'Interview planned', ''],
    ['interview_done_date', '✅', 'Interview completed', $cand['interview_outcome'] ?? ''],
    ['decided_at', '⚖️', 'Decision recorded', ''],
];
foreach ($mile as $m) { $d = substr((string)($cand[$m[0]] ?? ''), 0, 10);
    if ($d !== '') $tl[] = ['at' => $d, 'icon' => $m[1], 'title' => $m[2], 'meta' => (string)$m[3], 'by' => '', 'tone' => 'mut']; }
usort($tl, fn($a, $b) => strcmp(substr($b['at'], 0, 10) . $b['at'], substr($a['at'], 0, 10) . $a['at']));
?>
<style>
  .ctl{position:relative;margin:6px 0 0;padding-left:26px}
  .ctl::before{content:"";position:absolute;left:8px;top:6px;bottom:6px;width:2px;background:var(--line,#e5e7eb)}
  .ctl .ev{position:relative;padding:0 0 15px}
  .ctl .ev .dot{position:absolute;left:-24px;top:1px;width:18px;height:18px;border-radius:50%;background:var(--card,#fff);border:2px solid var(--brand,#1e40af);display:grid;place-items:center;font-size:9px}
  .ctl .ev.mut .dot{border-color:var(--muted,#94a3b8)}
  .ctl .ev .t{font-weight:600;font-size:14px}
  .ctl .ev .d{font-size:12px;color:var(--muted,#656e7a)}
  .ctl .ev .m{font-size:13px;color:var(--ink,#374151);margin-top:1px}
</style>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Timeline</h3>
  <?php if ($tl): ?>
  <div class="ctl">
    <?php foreach ($tl as $ev): ?>
    <div class="ev <?= $ev['tone'] === 'mut' ? 'mut' : '' ?>">
      <span class="dot"><?= $ev['tone'] === 'mut' ? e($ev['icon']) : '●' ?></span>
      <div class="t"><?= e($ev['title']) ?></div>
      <div class="d"><?= e(substr($ev['at'], 0, 16)) ?><?= $ev['by'] ? ' · ' . e($ev['by']) : '' ?></div>
      <?php if (trim($ev['meta']) !== ''): ?><div class="m"><?= e($ev['meta']) ?></div><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?><p class="muted" style="margin:0">No history yet.</p><?php endif; ?>
</div>

</section>
</div><!-- /data-tabs -->

<?php
// ---------------------------------------------------------------------------
//  Erasing an applicant's details
//
//  This is the sharpest personal data in the system and it belongs to somebody
//  who may never have worked for you: name, mobile, e-mail, the text of their
//  CV, expected rate. The DPDP Act allows keeping it only while there is a
//  reason to, and "nobody deleted it" is not one.
//
//  Offered on the candidate's own screen rather than buried in a compliance
//  register, because this is where somebody is standing when the request
//  arrives.
// ---------------------------------------------------------------------------
$erasePv = function_exists('candidate_erase_preview') ? candidate_erase_preview((int)$cand['id']) : null;
?>
<?php if ($erasePv && (is_master() || can('settings.manage') || can('person.iddoc.manage'))): ?>
<div class="panel mt-4">
  <div class="form-sec"><h3>Erase this person's details</h3>
    <p>For when an applicant asks you to remove what you hold about them.</p></div>

  <?php if ($erasePv['erased']): ?>
    <div class="msg msg-info">
      <b>Already erased</b> on <?= e(fdate(substr((string)$cand['erased_at'], 0, 10))) ?>
      <?= trim((string)($cand['erased_by'] ?? '')) !== '' ? 'by ' . e($cand['erased_by']) : '' ?>.
      <?php if (trim((string)($cand['erase_reason'] ?? '')) !== ''): ?>
        <div class="mt-1">Reason recorded: <?= e($cand['erase_reason']) ?></div>
      <?php endif; ?>
      <div class="mt-1">The reference and the hiring decision were kept on purpose, so the register still adds up.</div>
    </div>

  <?php elseif ($erasePv['hired']): ?>
    <div class="msg msg-warning">
      <b>This candidate was hired.</b> Their details are now held as a <?= e(Tl('engineer')) ?> — for employment, not
      for recruitment — so erasing them here would not remove them from the system and would only make this register
      disagree with the rest of it. Handle it from their own record instead.
    </div>

  <?php else: ?>
    <div class="row-top gap-4" style="align-items:flex-start">
      <div class="grow" style="min-width:260px">
        <p class="t-md t-mut mb-2"><b>What goes:</b>
          <?= $erasePv['holds'] ? e(implode(', ', array_map(fn($f) => str_replace('_', ' ', $f), $erasePv['holds'])))
                                : 'nothing — no personal details are held on this record' ?>.</p>
        <p class="t-md t-mut mb-0"><b>What stays:</b> <?= e(implode(', ', $erasePv['keeps'])) ?> — a register that
          silently loses rows is one nobody can audit, so the shape of the decision is kept and only the person is
          removed.</p>
      </div>
      <form method="post" action="/candidate-erase" class="col" style="min-width:260px"
            onsubmit="return confirm('Erase the personal details on <?= e(addslashes((string)$cand['cand_code'])) ?>? This cannot be undone.')">
        <input type="hidden" name="id" value="<?= (int)$cand['id'] ?>">
        <div class="ff mb-0"><label>Why <span class="muted">(kept on the record)</span></label>
          <input class="form-control" name="reason" maxlength="255" placeholder="e.g. asked to be removed, 14 Aug 2026"></div>
        <button class="btn danger" type="submit">Erase the details</button>
      </form>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php // Phase 2 §23/24 — the same human may also be an inspector, a portal user or a contact.
      // The canonical mapping layer links them (no merge); this shows the other records. ?>
<?php if (function_exists('party_render_also')) party_render_also('CANDIDATE', (int)$cand['id'], 'This person elsewhere in the system'); ?>

<?php // Phase 2 §17 — this candidate's own activity, from the universal spine (now that CANDIDATE is registered). ?>
<?php if (function_exists('act_render_timeline')) act_render_timeline('CANDIDATE', (int)$cand['id'], 'History'); ?>
