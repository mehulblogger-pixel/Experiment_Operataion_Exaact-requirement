<?php
// Connect K0+ — unified manpower sourcing for one Operations inspection job.
// Ranks internal inspectors, marketplace professionals and the client's bench
// through the existing matcher; assigns under controlled (ISO-safe) rules.
$ctx = $ctx ?? []; $candidates = $candidates ?? [];
$job = $ctx['job'] ?? [];
?>
<style>
  .cs-wrap{max-width:920px}
  .cs-head{background:var(--card,#fff);border:1px solid var(--line,#e3ebea);border-radius:12px;padding:14px 16px;margin-bottom:14px}
  .cs-cand{border:1px solid var(--line,#e3ebea);border-radius:12px;padding:13px 15px;margin-bottom:10px;background:var(--card,#fff)}
  .cs-top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
  .cs-name{font-size:16px;font-weight:700}
  .cs-src{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700}
  .cs-src.internal{background:#e7f5ef;color:#0f7d5a}.cs-src.marketplace{background:#e6f0fb;color:#1858a8}
  .cs-bench{background:#fbf3d8;color:#8a6d0b;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700}
  .cs-score{font-size:22px;font-weight:800;font-variant-numeric:tabular-nums}
  .cs-chip{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11.5px;font-weight:600;margin:0 4px 4px 0;background:#eef4f3;color:#2a5}
  .cs-elig{font-size:11.5px;font-weight:700;padding:2px 8px;border-radius:999px}
  .cs-elig.ELIGIBLE{background:#e7f5ef;color:#0f7d5a}.cs-elig.BLOCKED{background:#f6e6e6;color:#9a2a2a}.cs-elig.EXPIRING,.cs-elig.CHECK,.cs-elig.UNVERIFIED{background:#fbf3d8;color:#8a6d0b}
  .cs-meta{color:var(--muted,#667);font-size:12.5px;margin-top:2px}
</style>

<div class="cs-wrap">
<p style="margin:0 0 8px"><a href="/job?id=<?= (int)$job['id'] ?>" class="muted">← Back to <?= e($job['job_code'] ?? 'job') ?></a></p>
<h1>Source manpower</h1>

<div class="cs-head">
  <strong><?= e($job['job_code'] ?? '') ?></strong>
  <?php if ($job['dep_site'] ?? ''): ?> · <?= e($job['dep_site']) ?><?php endif; ?>
  <?php if ($ctx['client_name'] ?? ''): ?> · <?= e($ctx['client_name']) ?><?php endif; ?>
  <div class="cs-meta">Currently: <?= e($ctx['inspector_name'] ?: 'Unassigned') ?>
    <?php if (($job['inspection_start_date'] ?? '') || ($job['inspection_end_date'] ?? '')): ?> · <?= e(substr((string)$job['inspection_start_date'],0,10)) ?> – <?= e(substr((string)$job['inspection_end_date'],0,10) ?: 'open') ?><?php endif; ?>
  </div>
  <p class="cs-meta" style="margin:8px 0 0">Ranked across your internal inspectors, the marketplace pool and this client’s bench. A marketplace professional can be assigned once linked to an inspector record — competence controls still apply.</p>
</div>

<?php if (!$candidates): ?>
  <div class="cs-head"><p class="muted" style="margin:0">No candidates matched yet. Add a discipline / service to the job, or broaden the pool.</p></div>
<?php endif; ?>

<?php foreach ($candidates as $c): ?>
  <div class="cs-cand">
    <div class="cs-top">
      <div>
        <span class="cs-name"><?= e($c['name'] ?? '') ?></span>
        <span class="cs-src <?= e($c['source']) ?>"><?= $c['source']==='internal' ? 'Internal inspector' : 'Marketplace' ?></span>
        <?php if (!empty($c['on_client_bench'])): ?> <span class="cs-bench">★ Client’s bench</span><?php endif; ?>
        <?php if (!empty($c['also_identity'])): ?> <span class="cs-src internal"><?= e($c['also_identity']) ?></span><?php endif; ?>
        <?php if (!empty($c['eligibility'])): ?> <span class="cs-elig <?= e($c['eligibility']) ?>"><?= e(ucfirst(strtolower((string)$c['eligibility']))) ?></span><?php endif; ?>
        <div class="cs-meta"><?= e($c['designation'] ?? '') ?><?= !empty($c['skills']) ? ' · '.e(mb_strimwidth((string)$c['skills'],0,60,'…')) : '' ?></div>
        <div style="margin-top:7px">
          <?php foreach (array_slice($c['reasons'] ?? [], 0, 5) as $rs): ?><span class="cs-chip"><?= e($rs) ?></span><?php endforeach; ?>
        </div>
      </div>
      <div style="text-align:right">
        <div class="cs-score"><?= (int)($c['score'] ?? 0) ?><span style="font-size:12px;color:var(--muted)">/100</span></div>
        <?php if (!empty($c['assignable'])): ?>
          <form method="post" action="/connect-source" style="margin:6px 0 0">
            <input type="hidden" name="job" value="<?= (int)$job['id'] ?>"><input type="hidden" name="action" value="assign">
            <input type="hidden" name="kind" value="<?= e($c['kind']) ?>"><input type="hidden" name="cand_id" value="<?= (int)$c['id'] ?>">
            <button class="btn" type="submit">Assign</button>
          </form>
        <?php elseif (!empty($c['needs_link'])): ?>
          <a class="btn secondary" href="/connect-identity" style="margin-top:6px" title="Link to an internal inspector record to assign">Link to assign →</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>
