<?php
  // Connect K18 / backlog #7 — the agency bench workspace. Pick an agency, manage
  // its PRIVATE roster, and allocate people to the requirements it fulfils. The
  // bench is never shared to anyone. Zero-Training: pick agency → see utilisation
  // → add people → allocate, plain words, large tap targets.
  $agencies = $agencies ?? []; $orgId = $orgId ?? 0; $org = $org ?? null;
  $bench = $bench ?? []; $summary = $summary ?? []; $allocs = $allocs ?? []; $reqs = $reqs ?? []; $roles = $roles ?? [];
  $avPill = function ($a) {
      $a = strtoupper((string)$a);
      $m = ['AVAILABLE' => ['Available', '#0b7a4a', 'rgba(16,122,74,.14)'],
            'ALLOCATED' => ['Allocated', '#8a6d12', 'rgba(201,162,39,.16)'],
            'OFF'       => ['Off', '#777', 'rgba(0,0,0,.06)']];
      [$l, $c, $bg] = $m[$a] ?? $m['AVAILABLE'];
      return '<span style="display:inline-block;padding:2px 9px;border-radius:999px;font-size:12px;font-weight:700;color:' . $c . ';background:' . $bg . '">' . e($l) . '</span>';
  };
?>
<div class="crumbs"><a href="/">Home</a> › Agency bench</div>
<div class="master-head">
  <div><h1>Agency bench</h1>
    <p class="sub" style="margin:2px 0 0">An agency's own workforce — <strong>private to that agency</strong>, never shared to the marketplace pool.
      Keep the roster here and allocate people to the requirements the agency is fulfilling.</p></div>
</div>

<style>
  .bn-pick{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:14px 0}
  .bn-pick select{padding:9px 12px;border:1px solid var(--line,#ddd);border-radius:10px;font-size:15px;min-width:260px}
  .bn-kpi{display:flex;flex-wrap:wrap;gap:10px;margin:6px 0 14px}
  .bn-kpi .k{flex:1 1 120px;padding:12px 14px;border:1px solid var(--line,#e5e7eb);border-radius:12px;background:var(--card,#fff)}
  .bn-kpi .lab{font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:var(--muted,#777)}
  .bn-kpi .v{font-size:24px;font-weight:700}
  .bn-card{border:1px solid var(--line,#e5e7eb);border-radius:14px;background:var(--card,#fff);padding:16px;margin-top:14px}
  .bn-tbl{width:100%;border-collapse:collapse;font-size:14px}
  .bn-tbl th,.bn-tbl td{text-align:left;padding:8px;border-bottom:1px solid var(--line,#eee);vertical-align:top}
  .bn-tbl th{font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:var(--muted,#777)}
  .bn-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:10px}
  .bn-grid label{display:block;font-size:12px;font-weight:600;color:var(--muted,#666);margin-bottom:3px}
  .bn-grid input,.bn-grid select{width:100%;padding:8px;border:1px solid var(--line,#ddd);border-radius:8px;font-size:14px;box-sizing:border-box}
  .bn-btn{padding:7px 13px;border-radius:9px;border:1px solid var(--line,#ddd);background:var(--card,#fff);cursor:pointer;font-weight:600;font-size:13px}
  .bn-btn.primary{background:#0f7d7d;border-color:#0f7d7d;color:#fff}
  .bn-off td{opacity:.55}
  details.bn-ed>summary{cursor:pointer;color:#0f7d7d;font-weight:600;font-size:13px;list-style:none}
  .bn-priv{display:inline-block;padding:2px 9px;border-radius:999px;background:rgba(15,125,125,.10);color:#0f7d7d;font-size:12px;font-weight:700}
</style>

<!-- Agency picker -->
<form method="get" action="/connect-bench" class="bn-pick">
  <label style="font-weight:600">Agency</label>
  <select name="org" onchange="this.form.submit()">
    <?php if (!$agencies): ?><option value="">No staffing / recruitment agencies yet</option><?php endif; ?>
    <?php foreach ($agencies as $a): ?>
      <option value="<?= (int)$a['id'] ?>" <?= (int)$a['id'] === (int)$orgId ? 'selected' : '' ?>><?= e($a['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <span class="bn-priv">🔒 Private roster</span>
</form>

<?php if (!$agencies): ?>
  <div class="bn-card"><p style="margin:0;color:var(--muted,#777)">Register a staffing or recruitment agency under
    <a href="/connect-orgs">Organisations</a> first — then its bench appears here.</p></div>
<?php elseif ($orgId): ?>

  <!-- Utilisation -->
  <div class="bn-kpi">
    <div class="k"><div class="lab">On the bench</div><div class="v"><?= (int)($summary['total'] ?? 0) ?></div></div>
    <div class="k"><div class="lab">Available</div><div class="v" style="color:#0b7a4a"><?= (int)($summary['available'] ?? 0) ?></div></div>
    <div class="k"><div class="lab">Allocated</div><div class="v" style="color:#8a6d12"><?= (int)($summary['allocated'] ?? 0) ?></div></div>
    <div class="k"><div class="lab">Off</div><div class="v" style="color:#777"><?= (int)($summary['off'] ?? 0) ?></div></div>
  </div>

  <!-- Add to bench -->
  <div class="bn-card">
    <details class="bn-ed"><summary class="bn-btn primary" style="display:inline-block;list-style:none">＋ Add a person to the bench</summary>
      <form method="post" action="/connect-bench" style="margin-top:10px">
        <input type="hidden" name="action" value="add"><input type="hidden" name="org_id" value="<?= (int)$orgId ?>">
        <div class="bn-grid">
          <div><label>Name *</label><input type="text" name="name" required></div>
          <div><label>Job title</label><input type="text" name="job_title" placeholder="e.g. 6G Welder"></div>
          <div><label>Role</label><select name="role_code"><option value="">—</option>
            <?php foreach ($roles as $r): ?><option value="<?= e($r['code']) ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select></div>
          <div><label>Skills</label><input type="text" name="skills" placeholder="Welding, NDT…"></div>
          <div><label>Base city</label><input type="text" name="base_city"></div>
          <div><label>Day rate (₹)</label><input type="number" name="day_rate"></div>
        </div>
        <div style="margin-top:10px"><button class="bn-btn primary" type="submit">Add to bench</button></div>
      </form>
    </details>
  </div>

  <!-- The roster -->
  <div class="bn-card">
    <h2 style="margin:0 0 8px">Roster — <?= e($org['name'] ?? 'Agency') ?></h2>
    <?php if (!$bench): ?>
      <p style="margin:0;color:var(--muted,#777)">No one on the bench yet. Add your people above.</p>
    <?php else: ?>
      <div style="overflow-x:auto">
      <table class="bn-tbl">
        <thead><tr><th>Name</th><th>Role / skills</th><th>City</th><th>Rate</th><th>Status</th><th>Allocate</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($bench as $b): $active = (int)($b['is_active'] ?? 1) === 1; ?>
            <tr class="<?= $active ? '' : 'bn-off' ?>">
              <td><strong><?= e($b['name']) ?></strong><?php if (!empty($b['job_title'])): ?><div class="cx-detail" style="font-size:12px;color:var(--muted,#777)"><?= e($b['job_title']) ?></div><?php endif; ?></td>
              <td><?= e($b['skills']) ?></td>
              <td><?= e($b['base_city']) ?></td>
              <td><?= (float)$b['day_rate'] ? '₹' . (int)$b['day_rate'] : '—' ?></td>
              <td><?= $avPill($active ? $b['availability'] : 'OFF') ?></td>
              <td>
                <?php if ($active && strtoupper((string)$b['availability']) !== 'ALLOCATED' && $reqs): ?>
                  <form method="post" action="/connect-bench" style="display:flex;gap:4px;align-items:center;flex-wrap:wrap">
                    <input type="hidden" name="action" value="allocate"><input type="hidden" name="org_id" value="<?= (int)$orgId ?>">
                    <input type="hidden" name="bench_id" value="<?= (int)$b['id'] ?>">
                    <select name="requirement_id" style="padding:5px;border:1px solid var(--line,#ddd);border-radius:7px;font-size:12px;max-width:170px">
                      <?php foreach ($reqs as $rq): ?><option value="<?= (int)$rq['id'] ?>"><?= e($rq['ref_code'] ?: ('#' . $rq['id'])) ?> · <?= e(mb_substr((string)$rq['title'], 0, 26)) ?></option><?php endforeach; ?>
                    </select>
                    <button class="bn-btn primary" type="submit">Allocate</button>
                  </form>
                <?php elseif (strtoupper((string)$b['availability']) === 'ALLOCATED'): ?>
                  <span class="cx-detail" style="font-size:12px;color:var(--muted,#777)">on a job</span>
                <?php endif; ?>
              </td>
              <td><form method="post" action="/connect-bench" style="display:inline"><input type="hidden" name="action" value="toggle"><input type="hidden" name="org_id" value="<?= (int)$orgId ?>"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="bn-btn" type="submit"><?= $active ? 'Take off' : 'Put back' ?></button></form></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Allocations -->
  <div class="bn-card">
    <h2 style="margin:0 0 8px">Allocations</h2>
    <?php $live = array_filter($allocs, fn($a) => strtoupper((string)$a['status']) !== 'RELEASED'); ?>
    <?php if (!$live): ?>
      <p style="margin:0;color:var(--muted,#777)">No one allocated yet. Allocate an available person to a requirement above.</p>
    <?php else: ?>
      <div style="overflow-x:auto">
      <table class="bn-tbl">
        <thead><tr><th>Person</th><th>Requirement</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($live as $a): $st = strtoupper((string)$a['status']); ?>
            <tr>
              <td><strong><?= e($a['bench_name']) ?></strong></td>
              <td><?= e(($a['ref_code'] ?? '') ?: ('#' . (int)$a['requirement_id'])) ?> · <?= e($a['req_title'] ?? '') ?></td>
              <td><?= $avPill($st === 'CONFIRMED' ? 'ALLOCATED' : 'AVAILABLE') ?> <span class="cx-detail" style="font-size:12px"><?= e(ucfirst(strtolower($st))) ?></span></td>
              <td style="white-space:nowrap">
                <?php if ($st === 'PROPOSED'): ?>
                  <form method="post" action="/connect-bench" style="display:inline"><input type="hidden" name="action" value="alloc_set"><input type="hidden" name="org_id" value="<?= (int)$orgId ?>"><input type="hidden" name="alloc_id" value="<?= (int)$a['id'] ?>"><button class="bn-btn primary" name="status" value="CONFIRMED" type="submit">Confirm</button></form>
                <?php endif; ?>
                <form method="post" action="/connect-bench" style="display:inline"><input type="hidden" name="action" value="alloc_set"><input type="hidden" name="org_id" value="<?= (int)$orgId ?>"><input type="hidden" name="alloc_id" value="<?= (int)$a['id'] ?>"><button class="bn-btn" name="status" value="RELEASED" type="submit">Release</button></form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </div>

<?php endif; ?>
