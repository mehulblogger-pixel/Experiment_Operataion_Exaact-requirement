<?php
// D1 (owner, 21 Nov 2026): move an existing branch company under its main
// customer as a billing location. A preview first; nothing changes until the
// button is pressed with a reason.
//   $pv — custid_move_preview()
$b = $pv['branch'] ?? null; $m = $pv['main'] ?? null;
?>
<div class="master-head"><div>
  <h1>Move under the main <?= e(Tl('client')) ?></h1>
  <p class="sub" style="margin:2px 0 0">One PAN is one <?= e(Tl('client')) ?>. A branch company with the same PAN becomes a billing location of its main record.</p>
</div></div>
<?php if (!empty($pv['err'])): ?>
  <div class="msg msg-error" style="margin-top:12px"><?= e($pv['err']) ?></div>
  <?php if ($b): ?><p><a href="/partner?id=<?= (int) $b['id'] ?>">Back to <?= e($b['legal_name']) ?></a></p><?php endif; ?>
<?php else: ?>
  <div class="panel" style="margin-top:12px">
    <dl class="kv">
      <dt>Branch company</dt><dd><a href="/partner?id=<?= (int) $b['id'] ?>"><?= e($b['code'] . ' — ' . $b['legal_name']) ?></a></dd>
      <dt>Main <?= e(Tl('client')) ?></dt><dd><a href="/partner?id=<?= (int) $m['id'] ?>"><?= e($m['code'] . ' — ' . $m['legal_name']) ?></a></dd>
      <dt>PAN</dt><dd><?= e($m['pan'] ?: pan_from_gstin($m['gstin'] ?? '')) ?></dd>
      <dt>Becomes</dt><dd><?= $pv['creates_location'] ? 'A billing location of the main ' . e(Tl('client')) . ': ' . e($pv['state']) . ' — GSTIN ' . e($pv['gstin']) : 'Part of the main ' . e(Tl('client')) . ($pv['gstin'] !== '' ? ' (its GSTIN ' . e($pv['gstin']) . ' becomes the main GSTIN, which was empty)' : '') ?></dd>
    </dl>
    <h3 class="tab-sub">What moves to the main <?= e(Tl('client')) ?></h3>
    <?php if ($pv['moves']): ?>
      <ul style="margin:0 0 0 18px"><?php foreach ($pv['moves'] as $k => $n): ?><li><?= e($k) ?>: <?= (int) $n ?></li><?php endforeach; ?></ul>
    <?php else: ?><p class="muted">Nothing else points at the branch company.</p><?php endif; ?>
    <p style="margin:10px 0 0"><b><?= (int) $pv['invoices'] ?> invoice<?= (int) $pv['invoices'] === 1 ? '' : 's' ?></b> of the branch company keep exactly what they print today (name, GSTIN, address) — a copy is taken before anything moves.</p>
    <p class="muted" style="margin:6px 0 0;font-size:12.5px">The branch company record is kept (marked as moved, with a note saying where it went) — nothing is deleted.</p>
    <form method="post" action="/partner-move-under?id=<?= (int) $b['id'] ?>" style="margin-top:12px">
      <div class="ff" style="max-width:520px"><label for="mv-why">Why these are one company</label>
        <input class="form-control" id="mv-why" name="reason" required maxlength="300" placeholder="e.g. same PAN — Mumbai branch of TB Refinery"></div>
      <label style="display:flex;gap:8px;align-items:center;margin:10px 0"><input type="checkbox" name="confirm" value="1" required> I have checked the list above.</label>
      <button class="btn" type="submit">Move under <?= e($m['legal_name']) ?></button>
      <a class="btn secondary" href="/partner?id=<?= (int) $b['id'] ?>">Cancel</a>
    </form>
  </div>
<?php endif; ?>
