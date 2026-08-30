<?php
// Connect K0+ — the client searches the shared professional pool. One keyword +
// filters, privacy-safe ranked cards, request-contact + invite-to-a-job. The
// masking is decided by connect_privacy_resolve — this view only renders it.
$f = $f ?? []; $cards = $cards ?? []; $disciplines = $disciplines ?? []; $work_types = $work_types ?? []; $open_reqs = $open_reqs ?? [];
$qs = http_build_query(array_filter(['q'=>$f['q']??'','discipline'=>$f['discipline']??'','work_type'=>$f['work_type']??'','location'=>$f['location']??'','available_only'=>!empty($f['available_only'])?1:'']));
$rateLbl = ['public'=>'Rate on card','band'=>'Rate range on request','hidden'=>'Rate on enquiry'];
?>
<style>
  .fc-chip{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11.5px;font-weight:600;margin:0 4px 4px 0}
  .fc-chip.hit{background:#e6f0fb;color:#1858a8}.fc-chip.cert{background:#e7f5ef;color:#0f7d5a}.fc-chip.loc{background:#f3eefb;color:#6a3fa8}
  .fc-card{border:1px solid var(--line,#e3ebea);border-radius:13px;padding:15px 16px;margin-bottom:12px;background:var(--card,#fff)}
  .fc-card .top{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}
  .fc-name{font-size:17px;font-weight:700}
  .fc-tier{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11.5px;font-weight:700}
  .fc-tier.t3{background:#0f7d5a;color:#fff}.fc-tier.t2{background:#e7f5ef;color:#0f7d5a}.fc-tier.t1{background:#eef4ff;color:#1858a8}.fc-tier.t0{background:#eceff1;color:#5b6b6a}
  .fc-contact{margin-top:11px;padding-top:11px;border-top:1px solid var(--line,#eef1f0);display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
  .fc-avail{font-size:11.5px;font-weight:700;padding:2px 8px;border-radius:999px}
  .fc-avail.on{background:#e7f5ef;color:#0f7d5a}.fc-avail.off{background:#fbf3d8;color:#8a6d0b}
  .fc-mono{font-variant-numeric:tabular-nums}
  .fc-filters{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-top:10px}
</style>

<h2 class="ptitle">Find technical manpower</h2>
<p class="plead">Search the whole professional pool by one keyword — a role, a skill, a certificate, an activity, a piece of equipment.
  We rank by how well each person fits, and by how close they are to your site. Contact details unlock when you’re engaged or the professional approves your request.</p>

<form method="get" action="/portal/find" class="pcard" style="max-width:820px">
  <label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">Search</label>
  <input class="form-control" name="q" value="<?= e($f['q'] ?? '') ?>" placeholder="e.g. pressure vessel inspector · CSWIP · phased array UT · shutdown">
  <div class="fc-filters">
    <div><label style="display:block;font-size:12px;color:var(--muted);margin:0 0 4px">Discipline</label>
      <select class="form-control" name="discipline"><option value="">— any —</option>
        <?php foreach ($disciplines as $d): ?><option value="<?= e($d['code']) ?>"<?= ($f['discipline']??'')===$d['code']?' selected':'' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
    <div><label style="display:block;font-size:12px;color:var(--muted);margin:0 0 4px">Work type</label>
      <select class="form-control" name="work_type"><option value="">— any —</option>
        <?php foreach ($work_types as $wt): $v=is_array($wt)?($wt['code']??$wt['name']??''):$wt; $l=is_array($wt)?($wt['name']??$v):$wt; ?><option value="<?= e($v) ?>"<?= ($f['work_type']??'')===$v?' selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div><label style="display:block;font-size:12px;color:var(--muted);margin:0 0 4px">Near</label>
      <input class="form-control" name="location" value="<?= e($f['location'] ?? '') ?>" placeholder="e.g. Dahej, Gujarat"></div>
    <div style="display:flex;align-items:flex-end"><label style="font-size:13px;display:flex;align-items:center;gap:7px;padding-bottom:9px">
      <input type="checkbox" name="available_only" value="1"<?= !empty($f['available_only'])?' checked':'' ?>> Available now</label></div>
  </div>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px">
    <button class="btn" type="submit">Search the pool</button>
    <?php if ($qs !== ''): ?>
      <span style="color:var(--muted);font-size:13px">·</span>
      <span class="fc-save"><a href="#" onclick="document.getElementById('saveSearchForm').submit();return false;" style="font-size:13.5px;font-weight:600;color:#0f7d5a;text-decoration:none">☆ Save this search</a></span>
    <?php endif; ?>
  </div>
</form>
<?php if ($qs !== ''): ?>
<form id="saveSearchForm" method="post" action="/portal/find" style="display:none"><input type="hidden" name="action" value="save_search"><input type="hidden" name="qs" value="<?= e($qs) ?>"><input type="hidden" name="label" value=""></form>
<?php endif; ?>

<p class="muted" style="margin:16px 0 10px"><strong><?= count($cards) ?></strong> professional<?= count($cards)===1?'':'s' ?><?= ($f['q']??'')!=='' ? ' matching “'.e($f['q']).'”' : '' ?><?= ($f['location']??'')!=='' ? ' near '.e($f['location']) : '' ?>.</p>

<?php if (!$cards): ?>
  <div class="pcard"><p class="muted" style="margin:0">No one matched. Try a broader keyword (a role or a single skill), or clear the location.</p></div>
<?php endif; ?>

<?php foreach ($cards as $c): ?>
  <div class="fc-card">
    <div class="top">
      <div>
        <span class="fc-name"><?= e($c['display_name']) ?></span>
        <?php if ($c['identity_masked']): ?> <span title="This professional shows their full name once you’re engaged">🔒</span><?php endif; ?>
        <?php $tr=(int)$c['tier_rank']; ?>
        <span class="fc-tier t<?= $tr>=3?3:$tr ?>" title="<?= e($c['tier_label']) ?>"><?= $tr>=3?'✓ Proven':($tr>=2?'✓ Credential-verified':($tr>=1?'✓ ID-verified':'Registered')) ?></span>
        <?php if ($c['headline']): ?><div class="muted" style="font-size:13px;margin-top:3px"><?= e($c['headline']) ?></div><?php endif; ?>
      </div>
      <?php $on = strtoupper((string)$c['availability'])==='AVAILABLE'; ?>
      <span class="fc-avail <?= $on?'on':'off' ?>"><?= $on?'Available':'Busy' ?></span>
    </div>

    <?php if ($c['match_hits']): ?>
      <div style="margin-top:9px"><span class="muted" style="font-size:11.5px">Matches:</span>
        <?php foreach ($c['match_hits'] as $h): ?><span class="fc-chip hit">✓ <?= e($h) ?></span><?php endforeach; ?></div>
    <?php endif; ?>
    <?php if ($c['verified_certs']): ?>
      <div style="margin-top:5px"><span class="muted" style="font-size:11.5px">Verified:</span>
        <?php foreach ($c['verified_certs'] as $vc): ?><span class="fc-chip cert">🎖 <?= e($vc) ?></span><?php endforeach; ?></div>
    <?php endif; ?>

    <div style="margin-top:9px;font-size:12.5px;color:var(--muted)">
      <?php if ($c['loc_label']): ?><span class="fc-chip loc">📍 <?= e($c['loc_label']) ?></span><?php endif; ?>
      <?php if ($c['base_city']): ?><?= e($c['base_city']) ?><?php elseif ($c['pan_india']): ?>Pan-India<?php endif; ?>
      &nbsp;·&nbsp; <?= e($rateLbl[$c['rate_mode']] ?? 'Rate range on request') ?>
    </div>

    <div class="fc-contact">
      <div style="font-size:13px">
        <?php if ($c['contact_state']==='shown'): ?>
          <strong>📞 <?= e($c['mobile']) ?></strong><?php if ($c['email']): ?> &nbsp;·&nbsp; <?= e($c['email']) ?><?php endif; ?>
          <div class="muted" style="font-size:11.5px"><?= $c['contact_reason']==='engaged' ? 'Shared because you’re engaged' : 'Shared with you' ?></div>
        <?php elseif ($c['contact_state']==='requested'): ?>
          <span class="muted">⏳ Contact request pending the professional’s approval</span>
        <?php elseif ($c['contact_state']==='message_only'): ?>
          <span class="muted">🔒 Reach this professional through platform messages</span>
        <?php else: ?>
          <span class="muted">🔒 Contact unlocks when you request it and the professional approves</span>
        <?php endif; ?>
      </div>
      <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
        <?php if ($c['contact_state']==='request'): ?>
          <form method="post" action="/portal/find" style="margin:0"><input type="hidden" name="action" value="reveal_request"><input type="hidden" name="pro_id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="qs" value="<?= e($qs) ?>"><button class="btn sec" type="submit" style="padding:5px 12px;font-size:12.5px">Request contact</button></form>
        <?php endif; ?>
        <?php if ($open_reqs): ?>
          <form method="post" action="/portal/find" style="margin:0;display:flex;gap:5px;align-items:center">
            <input type="hidden" name="action" value="invite"><input type="hidden" name="pro_id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="qs" value="<?= e($qs) ?>">
            <select name="requirement_id" class="form-control" style="padding:5px 8px;font-size:12.5px;max-width:190px">
              <?php foreach ($open_reqs as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['ref_code']) ?> — <?= e(mb_strimwidth((string)$r['title'],0,28,'…')) ?></option><?php endforeach; ?>
            </select>
            <button class="btn" type="submit" style="padding:5px 12px;font-size:12.5px">Invite</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>
