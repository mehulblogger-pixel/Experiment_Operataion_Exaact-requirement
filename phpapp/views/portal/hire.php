<?php
// Connect K2b — the client posts a technical-manpower requirement to the
// marketplace and sees its own postings. Same cx_requirements engine as the
// staff desk; scoped to this client. Zero-Training: one clear form, one action.
$rows = $rows ?? []; $sectors = $sectors ?? []; $disciplines = $disciplines ?? [];
$pill = function ($s) {
    $s = strtoupper((string)$s);
    $map = ['OPEN'=>'ok','SHORTLISTING'=>'ok','AWARDED'=>'ok','DRAFT'=>'muted','CLOSED'=>'muted','CANCELLED'=>'err','EXPIRED'=>'warn'];
    return '<span class="ppill '.($map[$s] ?? 'muted').'">'.e(ucfirst(strtolower($s))).'</span>';
};
?>
<style>
  .ppill{display:inline-block;padding:3px 9px;border-radius:999px;font-size:12px;font-weight:600}
  .ppill.ok{background:#e7f5ef;color:#0f7d5a}.ppill.warn{background:#fbf3d8;color:#8a6d0b}
  .ppill.err{background:#f6e6e6;color:#9a2a2a}.ppill.muted{background:#eceff1;color:#5b6b6a}
</style>
<h2 class="ptitle">Hire technical manpower</h2>
<p class="plead">Post what you need — inspector, welder, NDT technician, site engineer — and qualified professionals apply.
  Posting is free; you choose who to shortlist and award.</p>

<form method="post" action="/portal/hire" class="pcard" style="max-width:680px">
  <label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">What do you need? *</label>
  <input class="form-control" name="title" maxlength="200" required placeholder="e.g. Welding inspector for a pressure-vessel FAT at Dahej">
  <div style="display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-top:14px">
    <div><label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">Discipline</label>
      <select class="form-control" name="discipline_code"><option value="">— any —</option>
        <?php foreach ($disciplines as $d): ?><option value="<?= e($d['code']) ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
    <div><label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">Sector</label>
      <select class="form-control" name="sector_code"><option value="">— any —</option>
        <?php foreach ($sectors as $s): ?><option value="<?= e($s['code']) ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
    <div><label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">Where</label>
      <input class="form-control" name="location" maxlength="160" placeholder="e.g. Dahej, Gujarat"></div>
    <div><label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">Positions</label>
      <input class="form-control" name="positions" type="number" min="1" value="1"></div>
    <div><label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">Start</label>
      <input class="form-control" name="start_date" type="date"></div>
    <div><label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">Rate up to (₹)</label>
      <input class="form-control" name="rate_max" type="number" min="0"></div>
  </div>
  <label style="display:block;font-size:13px;color:var(--muted);margin:14px 0 5px">Details</label>
  <textarea class="form-control" name="description" rows="3" placeholder="Standards, stage, material, what the professional will do…"></textarea>
  <button class="btn" type="submit" style="margin-top:16px">Post it — open for applications</button>
</form>

<h3 class="ptitle" style="font-size:16px;margin-top:30px">Your requirements</h3>
<?php if (!$rows): ?>
  <p class="pempty">You have not posted anything yet.</p>
<?php else: ?>
  <div class="pcard" style="max-width:680px">
    <?php foreach ($rows as $r): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid var(--line,#eee)">
        <div><a href="/portal/hire-req?id=<?= (int)$r['id'] ?>"><strong><?= e($r['title']) ?></strong></a>
          <div style="font-size:12.5px;color:var(--muted)"><?= e($r['ref_code']) ?><?php if (!empty($r['location'])): ?> · <?= e($r['location']) ?><?php endif; ?> · <?= function_exists('cx_applications_count') ? (int)cx_applications_count($r['id']) : 0 ?> applied</div>
        </div>
        <?= $pill($r['status']) ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
