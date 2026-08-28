<?php
// Connect K2a — the manpower marketplace desk: post a technical-manpower
// requirement, and see every requirement with its live status. Zero-Training:
// counts up top, one clear primary action (Post), plain words, big controls.
$summary = $summary ?? []; $rows = $rows ?? []; $sectors = $sectors ?? [];
$disciplines = $disciplines ?? []; $partners = $partners ?? [];
$pill = function ($s) {
    $s = strtoupper((string)$s);
    $map = ['OPEN'=>'ok','SHORTLISTING'=>'ok','AWARDED'=>'info','DRAFT'=>'muted','CLOSED'=>'muted','CANCELLED'=>'bad','EXPIRED'=>'warn'];
    $cls = $map[$s] ?? 'muted';
    return '<span class="cxpill '.$cls.'">'.e(ucfirst(strtolower($s))).'</span>';
};
?>
<style>
  .cxpill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600}
  .cxpill.ok{background:#e7f5ef;color:#0f7d5a}.cxpill.info{background:#e6f0fb;color:#1858a8}
  .cxpill.warn{background:#fbf3d8;color:#8a6d0b}.cxpill.bad{background:#f6e6e6;color:#9a2a2a}.cxpill.muted{background:#eceff1;color:#5b6b6a}
  .cxform label{display:block;font-size:13px;font-weight:600;margin:10px 0 4px}
  .cxform input,.cxform select,.cxform textarea{width:100%;padding:11px;border:1px solid var(--line,#dde3e2);border-radius:10px;font-size:15px;background:var(--card,#fff);color:inherit}
  .cxgrid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  @media(max-width:560px){.cxgrid{grid-template-columns:1fr}}
</style>
<div class="crumbs"><a href="/">Home</a> › Manpower marketplace</div>
<div class="master-head">
  <div><h1>Manpower marketplace</h1>
    <p class="sub" style="margin:2px 0 0">Post a technical-manpower requirement, and manage who applies — from open to awarded. Read-only for the public until posted.</p></div>
</div>

<div class="kpi-row" style="margin-top:12px">
  <div class="kpi"><span class="kic">📋</span><div class="k">Requirements</div><div class="v"><?= (int)($summary['total'] ?? 0) ?></div></div>
  <div class="kpi"><span class="kic">🟢</span><div class="k">Open</div><div class="v"><?= (int)($summary['open'] ?? 0) ?></div></div>
  <div class="kpi"><span class="kic">📝</span><div class="k">Drafts</div><div class="v"><?= (int)($summary['draft'] ?? 0) ?></div></div>
  <div class="kpi"><span class="kic">🤝</span><div class="k">Awarded</div><div class="v"><?= (int)($summary['awarded'] ?? 0) ?></div></div>
  <div class="kpi"><span class="kic">🙋</span><div class="k">Applications</div><div class="v"><?= (int)($summary['apps'] ?? 0) ?></div></div>
</div>

<div style="margin-top:12px"><a class="btn" href="/connect-concierge">💬 Guided post — answer a few questions</a></div>

<details class="panel" style="margin-top:12px">
  <summary style="cursor:pointer;font-weight:600;font-size:16px">➕ Post a requirement (full form)</summary>
  <form class="cxform" method="post" action="/connect-requirements" style="margin-top:10px">
    <label>What do you need? *</label>
    <input type="text" name="title" placeholder="e.g. Welding inspector for a pressure-vessel FAT at Dahej" required>
    <div class="cxgrid">
      <div><label>Posted for (company / agency)</label>
        <select name="poster_party_id"><option value="0">— optional —</option>
          <?php foreach ($partners as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['nm']) ?></option><?php endforeach; ?>
        </select></div>
      <div><label>Work type</label>
        <select name="work_type">
          <?php foreach (['per_visit'=>'Per visit','day_rate'=>'Day rate','short_project'=>'Short project','long_deployment'=>'Long deployment','shutdown'=>'Shutdown / turnaround','remote_review'=>'Remote document review'] as $k=>$v): ?>
            <option value="<?= $k ?>"><?= e($v) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label>Sector</label>
        <select name="sector_code"><option value="">— any —</option>
          <?php foreach ($sectors as $s): ?><option value="<?= e($s['code']) ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
        </select></div>
      <div><label>Discipline</label>
        <select name="discipline_code"><option value="">— any —</option>
          <?php foreach ($disciplines as $d): ?><option value="<?= e($d['code']) ?>"><?= e($d['name']) ?></option><?php endforeach; ?>
        </select></div>
      <div><label>Location</label><input type="text" name="location" placeholder="e.g. Dahej, Gujarat"></div>
      <div><label>Positions</label><input type="number" name="positions" value="1" min="1"></div>
      <div><label>Start date</label><input type="date" name="start_date"></div>
      <div><label>End date</label><input type="date" name="end_date"></div>
      <div><label>Rate from (₹)</label><input type="number" name="rate_min" min="0" step="1"></div>
      <div><label>Rate to (₹)</label><input type="number" name="rate_max" min="0" step="1"></div>
    </div>
    <label>Details</label>
    <textarea name="description" rows="3" placeholder="Standards, stage, material, what the professional will do…"></textarea>
    <div style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap">
      <button type="submit" name="action" value="create_post" class="btn">Post it — open for applications</button>
      <button type="submit" name="action" value="create" class="btn secondary">Save as draft</button>
    </div>
  </form>
</details>

<div class="panel" style="margin-top:12px">
  <h3 style="margin:0 0 8px">All requirements</h3>
  <?php if (!$rows): ?>
    <p class="muted" style="margin:0">No requirements yet. Post the first one above.</p>
  <?php else: ?>
    <div class="tbl-wrap"><table class="tbl">
      <thead><tr><th>Ref</th><th>Requirement</th><th>Location</th><th>Positions</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><a href="/connect-requirement?id=<?= (int)$r['id'] ?>"><?= e($r['ref_code']) ?></a></td>
          <td><a href="/connect-requirement?id=<?= (int)$r['id'] ?>"><?= e($r['title']) ?></a></td>
          <td><?= e($r['location']) ?></td>
          <td><?= (int)$r['positions'] ?></td>
          <td><?= $pill($r['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
