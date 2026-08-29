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

  <?php
    // K21 — the same engagement / rate terms the ops desk sets, offered to the
    // client at the point of posting. Every deputation shape and rate model the
    // marketplace supports is available here; the engine (cx_requirement_save_terms)
    // stores them on the requirement and the engagement inherits them at booking.
    $bases    = function_exists('connect_engage_bases')             ? connect_engage_bases()             : [];
    $rateM    = function_exists('connect_engage_rate_models')       ? connect_engage_rate_models()       : [];
    $cadences = function_exists('connect_engage_voucher_cadences')  ? connect_engage_voucher_cadences()  : [];
  ?>
  <?php if ($bases || $rateM || $cadences): ?>
  <div style="margin-top:18px;padding-top:16px;border-top:1px solid var(--line,#eee)">
    <div style="font-size:13.5px;font-weight:700;color:var(--ink,#1a2b2a);margin:0 0 3px">How is this engaged &amp; paid?</div>
    <p style="font-size:12.5px;color:var(--muted);margin:0 0 12px">Sets the deputation shape, whether the rate is all-in, and how expense vouchers are claimed. Professionals see this before they apply.</p>
    <div style="display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
      <?php if ($bases): ?>
      <div>
        <label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">Deputation</label>
        <select class="form-control" name="deputation_basis" id="dep_basis">
          <?php foreach ($bases as $k => $b): ?>
            <option value="<?= e($k) ?>"<?= $k === 'MAN_DAYS' ? ' selected' : '' ?>><?= e($b['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <div id="dep_hint" style="font-size:11.5px;color:var(--muted);margin-top:5px"></div>
      </div>
      <?php endif; ?>
      <?php if ($rateM): ?>
      <div>
        <label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">Rate model</label>
        <select class="form-control" name="rate_inclusive">
          <?php foreach ($rateM as $k => $m): ?>
            <option value="<?= e($k) ?>"<?= $k === 'INCLUSIVE' ? ' selected' : '' ?>><?= e($m['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <div style="font-size:11.5px;color:var(--muted);margin-top:5px">All-inclusive = travel, stay &amp; allowances are inside the rate. Fee only = those are claimed as expenses on top.</div>
      </div>
      <?php endif; ?>
      <?php if ($cadences): ?>
      <div>
        <label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">Vouchers</label>
        <select class="form-control" name="voucher_cadence">
          <?php foreach ($cadences as $k => $c): ?>
            <option value="<?= e($k) ?>"<?= $k === 'PER_DEPLOYMENT' ? ' selected' : '' ?>><?= e($c['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <div style="font-size:11.5px;color:var(--muted);margin-top:5px">Per day = one claim each working day. Per deployment = one consolidated claim (or per month).</div>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($bases): ?>
  <script>
    (function () {
      var hints = <?= json_encode(array_map(function ($b) {
          // A plain-language line for each deputation shape.
          $u = $b['unit'] ?? '';
          $map = [
            'day'   => 'A number of man-days — one-off or short assignment.',
            'month' => 'Billed by the month for the length of the posting.',
            'visit' => 'A regular pattern, e.g. two days a week or a monthly visit.',
          ];
          return $map[$u] ?? '';
      }, $bases), JSON_UNESCAPED_UNICODE) ?>;
      var sel = document.getElementById('dep_basis'), out = document.getElementById('dep_hint');
      if (sel && out) {
        var upd = function () { out.textContent = hints[sel.value] || ''; };
        sel.addEventListener('change', upd); upd();
      }
    })();
  </script>
  <?php endif; ?>
  <?php endif; ?>

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
          <?php
            $bits = [];
            if (!empty($r['deputation_basis']) && function_exists('connect_engage_basis_label')) $bits[] = connect_engage_basis_label($r['deputation_basis']);
            if (!empty($r['rate_inclusive']) && function_exists('connect_engage_rate_model_label')) $bits[] = connect_engage_rate_model_label($r['rate_inclusive']);
          ?>
          <?php if ($bits): ?><div style="font-size:11.5px;color:var(--muted);margin-top:2px"><?= e(implode(' · ', $bits)) ?></div><?php endif; ?>
        </div>
        <?= $pill($r['status']) ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
