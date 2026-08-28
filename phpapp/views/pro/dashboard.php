<?php $me = $me ?? []; $pct = $pct ?? 0; ?>
<h1>Hello, <?= e($me['name'] ?: 'there') ?></h1>
<p class="muted" style="margin:0 0 16px">Your professional profile on the pool.</p>

<div class="card">
  <h2>Profile strength</h2>
  <div style="display:flex;align-items:center;gap:12px">
    <div style="flex:1;height:10px;background:var(--line);border-radius:6px;overflow:hidden"><div style="height:100%;width:<?= (int)$pct ?>%;background:var(--teal)"></div></div>
    <strong><?= (int)$pct ?>%</strong>
  </div>
  <?php if ($pct < 100): ?><p class="muted" style="margin:10px 0 0">Complete your profile so the right jobs find you. <a href="/pro/profile">Finish it →</a></p>
  <?php else: ?><p class="muted" style="margin:10px 0 0">Your profile is complete — you're visible to clients and agencies.</p><?php endif; ?>
</div>

<div class="card">
  <h2>What you can do</h2>
  <p style="margin:0 0 6px">✅ Build your profile — work types, disciplines, locations you'll travel, availability, rates.</p>
  <p style="margin:0 0 6px muted" class="muted">🔜 Browse open requirements and apply (coming next).</p>
  <a class="btn" href="/pro/profile" style="margin-top:8px">Edit my profile</a>
</div>
