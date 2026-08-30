<?php
// Connect K0+ — the client's PRIVATE bench / roster. Add from marketplace /
// previous work / by hand; keep private notes, a rating and a preferred flag;
// rehire onto an open requirement. Private data never leaves this client.
$bench = $bench ?? []; $previous = $previous ?? []; $open_reqs = $open_reqs ?? [];
$stars = function ($n) { $n=(int)$n; return str_repeat('★',$n) . str_repeat('☆',5-$n); };
?>
<style>
  .rb-card{border:1px solid var(--line,#e3ebea);border-radius:13px;padding:14px 16px;margin-bottom:11px;background:var(--card,#fff)}
  .rb-top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
  .rb-name{font-size:16px;font-weight:700}
  .rb-src{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;margin-left:6px}
  .rb-src.marketplace{background:#e6f0fb;color:#1858a8}.rb-src.previous{background:#fbf3d8;color:#8a6d0b}.rb-src.manual{background:#eceef1;color:#556}
  .rb-pref{background:#0f7d5a;color:#fff;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700}
  .rb-meta{color:var(--muted,#667);font-size:12.5px;margin-top:2px}
  .rb-note{background:#f7faf9;border:1px solid var(--line,#eef1f0);border-radius:9px;padding:8px 10px;margin-top:9px;font-size:13px}
  .rb-edit{display:grid;gap:8px;grid-template-columns:1fr;margin-top:9px;border-top:1px solid var(--line,#eee);padding-top:10px}
  .rb-edit .rowline{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .rb-edit label{font-size:12px;color:var(--muted,#667)}
  .rb-actions{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
  .rb-add{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(150px,1fr))}
</style>

<h2 class="ptitle">My bench</h2>
<p class="plead">Your private roster of professionals you know and want to reuse. Add them from a search, from people who’ve worked with you, or by hand.
  Your notes, ratings and preferred rates here are <strong>private to you</strong> — the professional and other clients never see them.</p>

<?php if (!$bench): ?>
  <div class="pcard"><p class="muted" style="margin:0">Your bench is empty. <a href="/portal/find">Search the pool →</a> and tap “Add to bench”, or add someone below.</p></div>
<?php else: foreach ($bench as $b): $card = $b['card'] ?? null; ?>
  <div class="rb-card">
    <div class="rb-top">
      <div>
        <span class="rb-name"><?= e($b['display_name']) ?></span>
        <span class="rb-src <?= e($b['source']) ?>"><?= e(ucfirst($b['source'])) ?></span>
        <?php if ((int)$b['preferred']): ?> <span class="rb-pref">★ Preferred</span><?php endif; ?>
        <?php if ($card && (int)$card['tier_rank']>0): ?> <span class="rb-src marketplace" title="<?= e($card['tier_label']) ?>">✓ <?= e($card['tier_label']) ?></span><?php endif; ?>
        <div class="rb-meta">
          <?php if ($b['display_role']): ?><?= e($b['display_role']) ?><?php endif; ?>
          <?php if ($b['display_city']): ?> · <?= e($b['display_city']) ?><?php endif; ?>
          <?php if ((int)$b['client_rating']>0): ?> · <span title="Your rating" style="color:#c99700"><?= $stars($b['client_rating']) ?></span><?php endif; ?>
          <?php if ((float)$b['preferred_rate']>0): ?> · your rate ₹<?= number_format((float)$b['preferred_rate']) ?><?php endif; ?>
        </div>
        <?php if (trim((string)$b['private_note'])!==''): ?><div class="rb-note">📝 <?= e($b['private_note']) ?></div><?php endif; ?>
      </div>
      <div class="rb-actions">
        <?php if ((int)$b['professional_id']>0 && $open_reqs): ?>
          <form method="post" action="/portal/roster" style="margin:0;display:flex;gap:5px;align-items:center">
            <input type="hidden" name="action" value="invite"><input type="hidden" name="pro_id" value="<?= (int)$b['professional_id'] ?>">
            <select name="requirement_id" class="form-control" style="padding:5px 8px;font-size:12.5px;max-width:170px">
              <?php foreach ($open_reqs as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['ref_code']) ?> — <?= e(mb_strimwidth((string)$r['title'],0,24,'…')) ?></option><?php endforeach; ?>
            </select>
            <button class="btn" type="submit" style="padding:5px 12px;font-size:12.5px" title="Rehire onto an open requirement">Rehire</button>
          </form>
        <?php endif; ?>
        <form method="post" action="/portal/roster" style="margin:0" onsubmit="return confirm('Remove from your bench?');"><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="btn sec" type="submit" style="padding:5px 10px;font-size:12px">Remove</button></form>
      </div>
    </div>

    <?php // inline private edit ?>
    <form method="post" action="/portal/roster" class="rb-edit">
      <input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
      <textarea name="private_note" rows="1" class="form-control" placeholder="Private note (only you see this)…"><?= e($b['private_note']) ?></textarea>
      <div class="rowline">
        <label>Rating
          <select name="client_rating" class="form-control" style="padding:5px 8px;width:auto;display:inline-block;margin-left:4px">
            <?php for ($i=0;$i<=5;$i++): ?><option value="<?= $i ?>"<?= (int)$b['client_rating']===$i?' selected':'' ?>><?= $i?$stars($i):'—' ?></option><?php endfor; ?>
          </select>
        </label>
        <label>Your rate ₹ <input name="preferred_rate" type="number" min="0" value="<?= (float)$b['preferred_rate']?(int)$b['preferred_rate']:'' ?>" class="form-control" style="width:110px;display:inline-block;padding:5px 8px"></label>
        <label style="display:flex;align-items:center;gap:5px"><input type="checkbox" name="preferred" value="1"<?= (int)$b['preferred']?' checked':'' ?>> Preferred</label>
        <button class="btn sec" type="submit" style="padding:5px 12px;font-size:12.5px">Save note</button>
        <?php if ((int)$b['professional_id']===0): ?><span class="muted" style="font-size:12px">Manual entry — link to a marketplace profile later from a search.</span><?php endif; ?>
      </div>
    </form>
  </div>
<?php endforeach; endif; ?>

<?php // ---- Add from previous professionals (source B) ---- ?>
<?php if ($previous): ?>
<div class="pcard" style="margin-top:18px">
  <h3 style="margin:0 0 4px;font-size:16px">People who’ve worked with you</h3>
  <p class="muted" style="margin:0 0 12px;font-size:13px">Professionals who applied to or were engaged on your requirements. Add them to reuse in one tap.</p>
  <?php foreach ($previous as $pv): ?>
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;border:1px solid var(--line,#eef1f0);border-radius:10px;padding:9px 12px;margin-bottom:7px">
      <div><strong><?= e($pv['name']) ?></strong><div class="rb-meta"><?= e($pv['headline'] ?? '') ?><?= !empty($pv['base_city'])?' · '.e($pv['base_city']):'' ?> · <?= (int)$pv['reqs'] ?> of your job<?= (int)$pv['reqs']===1?'':'s' ?></div></div>
      <form method="post" action="/portal/roster" style="margin:0"><input type="hidden" name="action" value="add"><input type="hidden" name="professional_id" value="<?= (int)$pv['professional_id'] ?>"><input type="hidden" name="source" value="previous"><button class="btn sec" type="submit" style="padding:5px 12px;font-size:12.5px">☆ Add to bench</button></form>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php // ---- Add manually (source C) ---- ?>
<div class="pcard" style="margin-top:18px">
  <h3 style="margin:0 0 4px;font-size:16px">Add someone by hand</h3>
  <p class="muted" style="margin:0 0 12px;font-size:13px">Know someone who isn’t on the platform yet? Add them here; you can link them to a real profile later if they join.</p>
  <form method="post" action="/portal/roster">
    <input type="hidden" name="action" value="add"><input type="hidden" name="source" value="manual">
    <div class="rb-add">
      <div><label style="display:block;font-size:12px;color:var(--muted)">Name *</label><input class="form-control" name="manual_name" required></div>
      <div><label style="display:block;font-size:12px;color:var(--muted)">Role</label><input class="form-control" name="manual_role" placeholder="e.g. Welding inspector"></div>
      <div><label style="display:block;font-size:12px;color:var(--muted)">Discipline</label><input class="form-control" name="manual_discipline" placeholder="e.g. NDT"></div>
      <div><label style="display:block;font-size:12px;color:var(--muted)">Base city</label><input class="form-control" name="manual_city"></div>
      <div><label style="display:block;font-size:12px;color:var(--muted)">Mobile</label><input class="form-control" name="manual_mobile"></div>
      <div><label style="display:block;font-size:12px;color:var(--muted)">Your rate ₹</label><input class="form-control" name="preferred_rate" type="number" min="0"></div>
    </div>
    <textarea name="private_note" rows="2" class="form-control" style="margin-top:10px" placeholder="Private note…"></textarea>
    <button class="btn" type="submit" style="margin-top:12px">Add to my bench</button>
  </form>
</div>
