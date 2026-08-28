<?php
// Connect A3 — talent search over the SHARED professional pool (self-listed
// freelancers only; never an org's private staff). Search by discipline, work
// type, location, availability; invite a professional onto an open requirement.
$f = $f ?? []; $rows = $rows ?? []; $pool = $pool ?? 0;
$disciplines = $disciplines ?? []; $work_types = $work_types ?? []; $open_reqs = $open_reqs ?? [];
$qs = http_build_query(array_filter([
    'q' => $f['q'] ?? '', 'discipline' => $f['discipline'] ?? '', 'work_type' => $f['work_type'] ?? '',
    'location' => $f['location'] ?? '', 'available_only' => !empty($f['available_only']) ? 1 : '',
]));
$wtLabel = fn($k) => $work_types[$k] ?? ucfirst(str_replace('_', ' ', $k));
?>
<style>
  .tsearch input,.tsearch select{padding:10px;border:1px solid var(--line,#dde3e2);border-radius:10px;font-size:14px}
  .tchip{display:inline-block;margin:2px;padding:4px 10px;border-radius:999px;background:rgba(15,125,125,.08);border:1px solid rgba(15,125,125,.2);font-size:12.5px}
  .avail{display:inline-block;padding:3px 9px;border-radius:999px;font-size:12px;font-weight:600}
  .avail.on{background:#e7f5ef;color:#0f7d5a}.avail.off{background:#eceff1;color:#5b6b6a}
</style>
<div class="crumbs"><a href="/">Home</a> › <a href="/marketplace">Marketplace</a> › Talent search</div>
<div class="master-head">
  <div><h1>Talent search</h1>
    <p class="sub" style="margin:2px 0 0">Search the shared pool of self-listed professionals — <?= (int)$pool ?> registered. These are individuals who chose to be found; an organisation's own staff are never here.</p></div>
</div>

<form class="panel tsearch" method="get" action="/connect-talent" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
  <input type="text" name="q" value="<?= e($f['q'] ?? '') ?>" placeholder="Name, skill, headline" style="flex:2;min-width:180px">
  <select name="discipline"><option value="">Any discipline</option>
    <?php foreach ($disciplines as $d): ?><option value="<?= e($d['code']) ?>" <?= ($f['discipline']??'')===$d['code']?'selected':'' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select>
  <select name="work_type"><option value="">Any work type</option>
    <?php foreach ($work_types as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($f['work_type']??'')===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select>
  <input type="text" name="location" value="<?= e($f['location'] ?? '') ?>" placeholder="Location" style="min-width:130px">
  <label style="display:flex;align-items:center;gap:5px;font-size:13px"><input type="checkbox" name="available_only" value="1" <?= !empty($f['available_only'])?'checked':'' ?>> Available now</label>
  <button class="btn" type="submit">Search</button>
</form>

<div class="panel" style="margin-top:12px">
  <p class="muted" style="margin:0 0 10px"><?= count($rows) ?> professional<?= count($rows)===1?'':'s' ?> found.</p>
  <?php if (!$rows): ?>
    <p class="muted" style="margin:0">No professionals match. Widen the filters, or invite people to register at <strong>/pro/register</strong>.</p>
  <?php else: ?>
  <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fill,minmax(280px,1fr))">
    <?php foreach ($rows as $p):
      $wt = array_filter(array_map('trim', explode(',', (string)($p['work_types'] ?? ''))));
      $avail = strtoupper((string)($p['availability'] ?? '')) === 'AVAILABLE';
    ?>
      <div style="border:1px solid var(--line,#e3ebea);border-radius:12px;padding:14px">
        <div style="display:flex;justify-content:space-between;gap:8px;align-items:start">
          <div><strong><?= e($p['name']) ?></strong>
            <?php if (!empty($p['headline'])): ?><div class="muted" style="font-size:13px"><?= e($p['headline']) ?></div><?php endif; ?></div>
          <span class="avail <?= $avail?'on':'off' ?>"><?= $avail?'Available':'Busy' ?></span>
        </div>
        <div style="margin:8px 0">
          <?php if (!empty($p['skills'])): ?><div class="muted" style="font-size:13px;margin-bottom:6px"><?= e($p['skills']) ?></div><?php endif; ?>
          <?php foreach ($wt as $k) echo '<span class="tchip">' . e($wtLabel($k)) . '</span>'; ?>
        </div>
        <div class="muted" style="font-size:12.5px">
          <?php if (!empty($p['base_city'])): ?>📍 <?= e($p['base_city']) ?><?php endif; ?>
          <?php if (!empty($p['pan_india'])): ?> · pan-India<?php endif; ?>
          <?php if (!empty($p['overseas'])): ?> · overseas<?php endif; ?>
          <?php if (($p['day_rate_min'] ?? 0) || ($p['day_rate_max'] ?? 0)): ?> · ₹<?= (int)$p['day_rate_min'] ?>–<?= (int)$p['day_rate_max'] ?>/day<?php endif; ?>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px">
          <?php if (!empty($p['passport_token'])): ?><a class="btn secondary" href="/p/<?= e($p['passport_token']) ?>" target="_blank" rel="noopener" style="font-size:13px">Passport ↗</a><?php endif; ?>
          <?php if ($open_reqs): ?>
          <form method="post" action="/connect-talent" style="display:flex;gap:6px">
            <input type="hidden" name="action" value="invite">
            <input type="hidden" name="professional_id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="qs" value="<?= e($qs) ?>">
            <select name="requirement_id" style="font-size:13px;padding:7px;border:1px solid var(--line,#dde3e2);border-radius:8px">
              <option value="">Invite to…</option>
              <?php foreach ($open_reqs as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['ref_code']) ?> — <?= e(mb_strimwidth($r['title'],0,32,'…')) ?></option><?php endforeach; ?>
            </select>
            <button class="btn" type="submit" style="font-size:13px">Add</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
