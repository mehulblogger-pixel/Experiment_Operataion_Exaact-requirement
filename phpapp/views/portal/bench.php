<?php
// Connect K18 — the agency's OWN bench workspace, in its portal. Manage the
// private roster, and put people forward to open requirements on the marketplace.
$org = $org ?? []; $bench = $bench ?? []; $summary = $summary ?? []; $allocs = $allocs ?? [];
$reqs = $reqs ?? []; $disciplines = $disciplines ?? [];
$inr = fn($n) => (float)$n > 0 ? '₹' . number_format((int)round((float)$n)) : '—';
$avPill = function ($s) {
    $s = strtoupper((string)$s);
    $m = ['AVAILABLE'=>['Available','ok'],'ALLOCATED'=>['Allocated','warn'],'OFF'=>['Off','muted']];
    [$l,$c] = $m[$s] ?? $m['AVAILABLE']; return '<span class="bpill '.$c.'">'.e($l).'</span>';
};
$alPill = function ($s) {
    $s = strtoupper((string)$s);
    $m = ['PROPOSED'=>['Proposed','warn'],'CONFIRMED'=>['Confirmed','ok'],'RELEASED'=>['Released','muted']];
    [$l,$c] = $m[$s] ?? $m['PROPOSED']; return '<span class="bpill '.$c.'">'.e($l).'</span>';
};
$avail = array_values(array_filter($bench, fn($b) => (int)($b['is_active'] ?? 1) === 1));
?>
<style>
  .bpill{display:inline-block;padding:3px 9px;border-radius:999px;font-size:12px;font-weight:600}
  .bpill.ok{background:#e7f5ef;color:#0f7d5a}.bpill.warn{background:#fbf3d8;color:#8a6d0b}.bpill.muted{background:#eceff1;color:#5b6b6a}
  .bgrid{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(190px,1fr))}
</style>

<h2 class="ptitle"><?= e($org['name'] ?? 'Your agency') ?> · bench</h2>
<p class="plead">Your private roster — nobody else sees these people. Add your team, keep them up to date, and put them forward to open jobs on the marketplace.</p>

<div class="ptiles">
  <div class="ptile"><div class="n"><?= (int)($summary['total'] ?? 0) ?></div><div class="l">On the bench</div></div>
  <div class="ptile"><div class="n"><?= (int)($summary['available'] ?? 0) ?></div><div class="l">Available</div></div>
  <div class="ptile"><div class="n"><?= (int)($summary['allocated'] ?? 0) ?></div><div class="l">Allocated</div></div>
  <div class="ptile"><div class="n"><?= (int)($summary['off'] ?? 0) ?></div><div class="l">Off the bench</div></div>
</div>

<?php // Add a person ?>
<div class="pcard" style="max-width:760px">
  <h3 class="ptitle" style="font-size:16px">Add to your bench</h3>
  <form method="post" action="/portal/bench">
    <input type="hidden" name="action" value="add">
    <div class="bgrid">
      <div><label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">Name *</label>
        <input class="form-control" name="name" required placeholder="e.g. Ajay Sharma"></div>
      <div><label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">Job title</label>
        <input class="form-control" name="job_title" placeholder="e.g. CSWIP welding inspector"></div>
      <div><label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">Discipline</label>
        <select class="form-control" name="discipline_code"><option value="">— any —</option>
          <?php foreach ($disciplines as $d): ?><option value="<?= e($d['code']) ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
      <div><label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">Base city</label>
        <input class="form-control" name="base_city" placeholder="e.g. Surat"></div>
      <div><label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">Day rate (₹)</label>
        <input class="form-control" name="day_rate" type="number" min="0"></div>
    </div>
    <button class="btn" type="submit" style="margin-top:14px">Add to bench</button>
  </form>
</div>

<?php // Put someone forward ?>
<?php if ($avail && $reqs): ?>
<div class="pcard" style="max-width:760px">
  <h3 class="ptitle" style="font-size:16px">Put someone forward</h3>
  <p class="plead" style="margin:-2px 0 12px">Offer one of your people to an open requirement on the marketplace.</p>
  <form method="post" action="/portal/bench">
    <input type="hidden" name="action" value="allocate">
    <div class="bgrid">
      <div><label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">Who</label>
        <select class="form-control" name="bench_id" required>
          <?php foreach ($avail as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?><?= $b['job_title'] ? ' · ' . e($b['job_title']) : '' ?></option><?php endforeach; ?>
        </select></div>
      <div><label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">For which job</label>
        <select class="form-control" name="requirement_id" required>
          <?php foreach ($reqs as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['ref_code']) ?> · <?= e($r['title']) ?></option><?php endforeach; ?>
        </select></div>
    </div>
    <label style="display:block;font-size:13px;color:var(--muted);margin:12px 0 5px">Note (optional)</label>
    <input class="form-control" name="note" placeholder="e.g. available from Monday, 8 yrs pressure-vessel">
    <button class="btn" type="submit" style="margin-top:12px">Put forward</button>
  </form>
</div>
<?php endif; ?>

<?php // Roster ?>
<h3 class="ptitle" style="font-size:16px;margin-top:26px">Your roster</h3>
<?php if (!$bench): ?>
  <p class="pempty">No one on your bench yet — add your first person above.</p>
<?php else: ?>
<div class="pcard pscroll" style="max-width:900px">
  <table class="ptable">
    <thead><tr><th>Name</th><th>Discipline</th><th>City</th><th>Day rate</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($bench as $b): $on = (int)($b['is_active'] ?? 1) === 1; ?>
      <tr>
        <td><strong><?= e($b['name']) ?></strong><?php if (!empty($b['job_title'])): ?><div style="font-size:12.5px;color:var(--muted)"><?= e($b['job_title']) ?></div><?php endif; ?></td>
        <td><?= e($b['discipline_code'] ?: '—') ?></td>
        <td><?= e($b['base_city'] ?: '—') ?></td>
        <td><?= e($inr($b['day_rate'])) ?></td>
        <td><?= $on ? $avPill($b['availability']) : '<span class="bpill muted">Off</span>' ?></td>
        <td style="text-align:right">
          <form method="post" action="/portal/bench" style="margin:0">
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
            <button class="btn secondary" type="submit" style="padding:5px 11px;font-size:12.5px"><?= $on ? 'Take off' : 'Put back' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php // Allocations ?>
<h3 class="ptitle" style="font-size:16px;margin-top:26px">People you've put forward</h3>
<?php if (!$allocs): ?>
  <p class="pempty">Nothing put forward yet.</p>
<?php else: ?>
<div class="pcard pscroll" style="max-width:900px">
  <table class="ptable">
    <thead><tr><th>Who</th><th>Job</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($allocs as $a): $st = strtoupper((string)$a['status']); ?>
      <tr>
        <td><strong><?= e($a['bench_name']) ?></strong><?php if (!empty($a['job_title'])): ?><div style="font-size:12.5px;color:var(--muted)"><?= e($a['job_title']) ?></div><?php endif; ?></td>
        <td><?= e($a['ref_code'] ?? '') ?><?php if (!empty($a['req_title'])): ?> · <?= e($a['req_title']) ?><?php endif; ?></td>
        <td><?= $alPill($a['status']) ?></td>
        <td style="text-align:right">
          <?php if ($st !== 'RELEASED'): ?>
            <div style="display:inline-flex;gap:6px">
              <?php if ($st === 'PROPOSED'): ?>
              <form method="post" action="/portal/bench" style="margin:0">
                <input type="hidden" name="action" value="alloc_set"><input type="hidden" name="alloc_id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="status" value="CONFIRMED">
                <button class="btn" type="submit" style="padding:5px 11px;font-size:12.5px">Confirm</button>
              </form>
              <?php endif; ?>
              <form method="post" action="/portal/bench" style="margin:0" onsubmit="return confirm('Release <?= e(addslashes($a['bench_name'])) ?> back to the bench?');">
                <input type="hidden" name="action" value="alloc_set"><input type="hidden" name="alloc_id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="status" value="RELEASED">
                <button class="btn secondary" type="submit" style="padding:5px 11px;font-size:12.5px">Release</button>
              </form>
            </div>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
