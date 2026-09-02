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
    $rHeads   = function_exists('connect_reqterms_heads')           ? connect_reqterms_heads()           : [];
    $rModes   = function_exists('connect_reqterms_cover_modes')     ? connect_reqterms_cover_modes()     : [];
  ?>
  <?php if ($bases || $rateM || $cadences): ?>
  <div style="margin-top:18px;padding-top:16px;border-top:1px solid var(--line,#eee)">
    <div style="font-size:13.5px;font-weight:700;color:var(--ink,#1a2b2a);margin:0 0 3px">How is this engaged &amp; paid?</div>
    <p style="font-size:12.5px;color:var(--muted);margin:0 0 12px">Sets the deputation shape, whether the rate is all-in, and how expenses are handled. Professionals see this before they apply, and it drives the estimate below.</p>
    <div style="display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
      <?php if ($bases): ?>
      <div>
        <label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">Deputation</label>
        <select class="form-control" name="deputation_basis" id="dep_basis">
          <?php foreach ($bases as $k => $b): ?>
            <option value="<?= e($k) ?>" data-unit="<?= e($b['unit']) ?>"<?= $k === 'MAN_DAYS' ? ' selected' : '' ?>><?= e($b['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <div id="dep_hint" style="font-size:11.5px;color:var(--muted);margin-top:5px"></div>
      </div>
      <?php endif; ?>
      <?php if ($rateM): ?>
      <div>
        <label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">Rate model</label>
        <select class="form-control" name="rate_inclusive" id="rate_model">
          <?php foreach ($rateM as $k => $m): ?>
            <option value="<?= e($k) ?>"<?= $k === 'INCLUSIVE' ? ' selected' : '' ?>><?= e($m['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <div style="font-size:11.5px;color:var(--muted);margin-top:5px">All-inclusive = travel, stay &amp; allowances are inside the rate. Fee only = those are handled separately (set below).</div>
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

    <?php // --- Base fee + estimated quantity: the numbers the estimate is built from --- ?>
    <div style="display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-top:14px">
      <div>
        <label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">Base fee (₹ per <span class="unit-word">day</span>) <span id="fee_kind" style="color:#0f7d5a"></span></label>
        <input class="form-control" name="est_rate" id="est_rate" type="number" min="0" step="1" inputmode="numeric" placeholder="e.g. 6000">
      </div>
      <div>
        <label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">Estimated <span class="unit-word">day</span>s</label>
        <input class="form-control" name="est_qty" id="est_qty" type="number" min="0" step="1" inputmode="numeric" placeholder="e.g. 10">
      </div>
      <div>
        <label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px">GST %</label>
        <input class="form-control" name="est_tax_pct" id="est_tax" type="number" min="0" step="0.5" value="18">
        <div style="font-size:11.5px;color:var(--muted);margin-top:5px">Charged on top (extra).</div>
      </div>
    </div>

    <?php // --- Fee-only (EXCLUSIVE): what is covered, and the ceiling YOU set --- ?>
    <?php if ($rHeads && $rModes): ?>
    <div id="reimb_block" style="margin-top:16px;display:none">
      <div style="font-size:12.5px;font-weight:700;color:var(--ink,#1a2b2a);margin:0 0 3px">What is covered on top of the fee?</div>
      <p style="font-size:11.5px;color:var(--muted);margin:0 0 10px">For each item choose how it is handled. If you pick <b>“up to a ceiling”</b>, type the ceiling — that is <b>your</b> number, shown to the professional as the limit. Nothing is auto-capped.</p>
      <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:12.5px">
        <thead><tr style="text-align:left;color:var(--muted)">
          <th style="padding:6px 8px;font-weight:600">Item</th>
          <th style="padding:6px 8px;font-weight:600">How it's handled</th>
          <th style="padding:6px 8px;font-weight:600">Ceiling (₹)</th>
          <th style="padding:6px 8px;font-weight:600">Per</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rHeads as $hk => $hl): ?>
          <tr style="border-top:1px solid var(--line,#eee)">
            <td style="padding:7px 8px;font-weight:600;white-space:nowrap"><?= e($hl) ?></td>
            <td style="padding:7px 8px">
              <select class="form-control reimb-mode" name="reimb[<?= e($hk) ?>][mode]" data-head="<?= e($hk) ?>" style="padding:6px 8px;font-size:12.5px">
                <?php foreach ($rModes as $mk => $ml): ?><option value="<?= e($mk) ?>"><?= e($ml) ?></option><?php endforeach; ?>
              </select>
            </td>
            <td style="padding:7px 8px">
              <input class="form-control reimb-ceiling" name="reimb[<?= e($hk) ?>][ceiling]" data-head="<?= e($hk) ?>" type="number" min="0" step="1" inputmode="numeric" placeholder="—" disabled style="padding:6px 8px;font-size:12.5px;max-width:120px">
            </td>
            <td style="padding:7px 8px">
              <select class="form-control reimb-per" name="reimb[<?= e($hk) ?>][per]" data-head="<?= e($hk) ?>" disabled style="padding:6px 8px;font-size:12.5px">
                <option value="DAY">per day</option><option value="DEPLOYMENT">per deployment</option>
              </select>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
    <?php endif; ?>

    <?php // --- Live estimate --- ?>
    <div id="est_panel" style="margin-top:16px;background:var(--panel-2,#f3f7f6);border:1px solid var(--line,#e3ebea);border-radius:12px;padding:14px 16px;display:none">
      <div style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;font-weight:700;color:var(--muted);margin-bottom:8px">Estimated cost</div>
      <div id="est_rows" style="font-size:13px"></div>
      <div style="display:flex;justify-content:space-between;align-items:baseline;margin-top:9px;padding-top:9px;border-top:1px solid var(--line,#e3ebea)">
        <span style="font-weight:700;font-size:14px;color:var(--ink,#12201f)">Grand total (incl. GST)</span>
        <span id="est_grand" style="font-weight:800;font-size:19px;color:#0a5c5c;font-variant-numeric:tabular-nums">₹0</span>
      </div>
      <div id="est_actuals" style="font-size:11.5px;color:#8a6d0b;margin-top:6px;display:none">＋ items marked “at actuals” are claimed on receipts on top of this estimate.</div>
      <div style="font-size:11px;color:var(--muted);margin-top:6px">An estimate to guide you — the agreed rate is settled when you award someone.</div>
    </div>
  </div>

  <script>
    (function () {
      var basis = document.getElementById('dep_basis'),
          depHint = document.getElementById('dep_hint'),
          rateModel = document.getElementById('rate_model'),
          estRate = document.getElementById('est_rate'),
          estQty = document.getElementById('est_qty'),
          estTax = document.getElementById('est_tax'),
          reimbBlock = document.getElementById('reimb_block'),
          panel = document.getElementById('est_panel'),
          rowsBox = document.getElementById('est_rows'),
          grandBox = document.getElementById('est_grand'),
          actualsNote = document.getElementById('est_actuals');
      var HEADS = <?= json_encode(array_keys($rHeads), JSON_UNESCAPED_UNICODE) ?>;
      var HLABEL = <?= json_encode($rHeads, JSON_UNESCAPED_UNICODE) ?>;
      var depHints = {day:'A number of man-days — one-off or short assignment.', month:'Billed by the month for the length of the posting.', visit:'A regular pattern, e.g. two days a week or a monthly visit.'};
      function unit(){ var o = basis && basis.options[basis.selectedIndex]; return (o && o.getAttribute('data-unit')) || 'day'; }
      function inr(n){ n = Math.round(n||0); return '₹' + n.toLocaleString('en-IN'); }
      function num(el){ var v = parseFloat(el && el.value); return isFinite(v) && v > 0 ? v : 0; }
      function isExclusive(){ return rateModel && rateModel.value === 'EXCLUSIVE'; }

      function syncUnitWords(){
        var u = unit(); document.querySelectorAll('.unit-word').forEach(function(s){ s.textContent = u; });
        if (depHint) depHint.textContent = depHints[u] || '';
      }
      function syncReimbRows(){
        document.querySelectorAll('.reimb-mode').forEach(function(sel){
          var head = sel.getAttribute('data-head'), on = sel.value === 'CEILING';
          var ceil = document.querySelector('.reimb-ceiling[data-head="'+head+'"]');
          var per  = document.querySelector('.reimb-per[data-head="'+head+'"]');
          if (ceil){ ceil.disabled = !on; if(!on) ceil.value=''; }
          if (per){ per.disabled = !on; }
        });
      }
      function compute(){
        var rate = num(estRate), qty = num(estQty), taxPct = Math.max(0, parseFloat(estTax && estTax.value) || 0);
        var fee = rate * qty, reimb = 0, hasActuals = false, rows = [];
        rows.push(['Fee (' + inr(rate) + ' × ' + (qty||0) + ' ' + unit() + (qty===1?'':'s') + ')', fee]);
        if (isExclusive()){
          HEADS.forEach(function(head){
            var mode = (document.querySelector('.reimb-mode[data-head="'+head+'"]')||{}).value || 'IN_RATE';
            if (mode === 'CEILING'){
              var c = num(document.querySelector('.reimb-ceiling[data-head="'+head+'"]'));
              var per = (document.querySelector('.reimb-per[data-head="'+head+'"]')||{}).value || 'DAY';
              var amt = c * (per === 'DAY' ? Math.max(1, qty) : 1);
              if (amt > 0){ reimb += amt; rows.push([HLABEL[head] + ' (≤ ' + inr(c) + (per==='DAY'?'/day':'/deployment') + ')', amt]); }
            } else if (mode === 'ACTUALS'){ hasActuals = true; }
          });
          if (reimb > 0) rows.push(['— reimbursables subtotal', reimb, true]);
        }
        var subtotal = fee + reimb, tax = subtotal * taxPct / 100;
        rows.push(['Subtotal', subtotal, true]);
        rows.push(['GST @ ' + (taxPct||0) + '%', tax]);
        rowsBox.innerHTML = rows.map(function(r){
          return '<div style="display:flex;justify-content:space-between;gap:12px;padding:2px 0;'+(r[2]?'font-weight:700;color:var(--ink,#12201f)':'color:var(--muted)')+'">'
            + '<span>'+r[0]+'</span><span style="font-variant-numeric:tabular-nums">'+inr(r[1])+'</span></div>';
        }).join('');
        grandBox.textContent = inr(subtotal + tax);
        actualsNote.style.display = hasActuals ? 'block' : 'none';
        panel.style.display = (rate > 0 && qty > 0) ? 'block' : 'none';
      }
      function syncModel(){ if (reimbBlock) reimbBlock.style.display = isExclusive() ? 'block' : 'none'; syncReimbRows(); compute(); }

      if (basis) basis.addEventListener('change', function(){ syncUnitWords(); compute(); });
      if (rateModel) rateModel.addEventListener('change', syncModel);
      [estRate, estQty, estTax].forEach(function(el){ if(el){ el.addEventListener('input', compute); } });
      document.querySelectorAll('.reimb-mode').forEach(function(el){ el.addEventListener('change', function(){ syncReimbRows(); compute(); }); });
      document.querySelectorAll('.reimb-ceiling, .reimb-per').forEach(function(el){ el.addEventListener('input', compute); el.addEventListener('change', compute); });
      syncUnitWords(); syncModel();
    })();
  </script>
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
        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
          <?= $pill($r['status']) ?>
          <form method="post" action="/portal/hire" style="margin:0"><input type="hidden" name="action" value="duplicate"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn sec" type="submit" style="padding:4px 9px;font-size:11.5px" title="Copy into a new draft you can edit and post">Duplicate</button></form>
          <form method="post" action="/portal/hire" style="margin:0" onsubmit="this.label.value=prompt('Name this template:', <?= htmlspecialchars(json_encode((string)$r['title']), ENT_QUOTES) ?>)||''; return this.label.value!=='';"><input type="hidden" name="action" value="save_template"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="label" value=""><button class="btn sec" type="submit" style="padding:4px 9px;font-size:11.5px" title="Save this shape as a reusable template">★ Template</button></form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php // ---- Start from a saved template (§49) ---- ?>
<?php $templates = $templates ?? []; if ($templates): ?>
  <h3 class="ptitle" style="font-size:16px;margin-top:26px">Start from a template</h3>
  <div class="pcard" style="max-width:680px">
    <?php foreach ($templates as $t): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--line,#eee)">
        <div><strong><?= e($t['label']) ?></strong></div>
        <div style="display:flex;gap:6px;align-items:center">
          <form method="post" action="/portal/hire" style="margin:0"><input type="hidden" name="action" value="from_template"><input type="hidden" name="template_id" value="<?= (int)$t['id'] ?>"><button class="btn" type="submit" style="padding:5px 12px;font-size:12.5px">Post from template</button></form>
          <form method="post" action="/portal/hire" style="margin:0" onsubmit="return confirm('Delete this template?');"><input type="hidden" name="action" value="template_delete"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="btn sec" type="submit" style="padding:5px 9px;font-size:12px">×</button></form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
