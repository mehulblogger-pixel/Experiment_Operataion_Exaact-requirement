<?php
// Expenses Phase 1 — screens 5–7 (Master Admin): claim rules, bands and what
// each band may claim. The expense types themselves stay on the existing
// master (Masters → Expense heads); a person's own amounts stay on their
// team-member record (Allowances & rates). Nothing here is preset: every
// "₹ __" is the owner's to fill in.
$cs = cur_sym();
$kindOf = fn($h) => exp_kind($h);
?>
<div class="master-head">
  <div><h1>Expense rules</h1>
    <p class="sub" style="margin:2px 0 0">The claim period, the two deadlines, the usual-distance check, and what each band may claim. Master Admin only.</p></div>
  <a class="btn secondary" href="/voucher-approvals">Approval chain →</a>
</div>

<form method="post" action="/voucher-rules" class="panel" style="max-width:720px">
  <input type="hidden" name="_do" value="rules">
  <h3 class="tab-sub" style="margin-top:0">Claim rules</h3>
  <label class="expd-lab">Claim period</label>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
    <?php foreach (EXP_PERIODS as $k => $lab): ?>
      <label class="pill <?= $rules['period'] === $k ? 'p-ok' : 'p-mut' ?>" style="min-height:40px;display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-size:14px">
        <input type="radio" name="period" value="<?= e($k) ?>" <?= $rules['period'] === $k ? 'checked' : '' ?>> <?= e($lab) ?></label>
    <?php endforeach; ?>
  </div>
  <div class="form-grid">
    <div class="ff"><label>A day's expenses can be added for … days after the day</label><input class="form-control" type="number" min="0" step="1" name="day_days" value="<?= (int)$rules['day_days'] ?>" required></div>
    <div class="ff"><label>A claim must be submitted within … days after its period ends</label><input class="form-control" type="number" min="0" step="1" name="claim_days" value="<?= (int)$rules['claim_days'] ?>" required></div>
    <div class="ff"><label>Usual distance — a difference above … % needs a reason (0 = any difference)</label><input class="form-control" type="number" min="0" step="0.5" name="km_tol" value="<?= e((string)$rules['km_tol']) ?>" required></div>
  </div>
  <p class="muted" style="margin:8px 0 0;font-size:13px">A period never crosses a month end, and everything also closes with the month-end cost run (month freeze). After a deadline the day or claim is locked; only the person's N+1 can reopen it, with a reason.
    Days and claims before <?= e(fdate($rules['from'])) ?> (when these rules started) are never locked by them.</p>
  <button class="btn" type="submit" style="margin-top:12px">Save claim rules</button>
</form>

<div class="panel" style="max-width:720px">
  <h3 class="tab-sub" style="margin-top:0">Expense types</h3>
  <p class="muted" style="margin:0 0 8px">Five kinds: per km, bill with photo, no bill (amount + note), per day, fixed per month. No amount is set on a type — amounts are set per band below, or on a person.</p>
  <?php foreach ($heads as $h): if ($h['code'] === 'KMTRAVEL') continue; ?>
    <div style="display:flex;justify-content:space-between;gap:8px;padding:6px 0;border-bottom:1px solid var(--line)">
      <span><strong><?= e($h['label']) ?></strong><br><span class="muted" style="font-size:13px"><?= e(exp_kind_label($h)) ?></span></span>
      <span class="pill <?= (int)$h['active'] === 1 ? 'p-ok' : 'p-mut' ?>"><?= (int)$h['active'] === 1 ? 'On' : 'Off' ?></span>
    </div>
  <?php endforeach; ?>
  <a class="btn secondary" href="/m/expense-heads" style="margin-top:10px">＋ Add or change an expense type</a>
</div>

<div class="panel" style="max-width:720px">
  <h3 class="tab-sub" style="margin-top:0">Bands / levels</h3>
  <?php if (!$bands): ?><p class="muted">No band yet. Add one (e.g. "Field Inspector", "Office staff"), then set what it may claim.</p><?php endif; ?>
  <?php foreach ($bands as $b): ?>
    <div style="display:flex;justify-content:space-between;gap:8px;padding:8px 0;border-bottom:1px solid var(--line)">
      <span><strong><?= e($b['name']) ?></strong><?= (int)$b['active'] ? '' : ' <span class="pill p-mut">off</span>' ?><br><span class="muted" style="font-size:13px"><?= (int)($bandCount[$b['id']] ?? 0) ?> people<?= $b['notes'] !== '' ? ' · ' . e($b['notes']) : '' ?></span></span>
      <a class="btn small <?= $band && (int)$band['id'] === (int)$b['id'] ? '' : 'secondary' ?>" href="/voucher-rules?band=<?= (int)$b['id'] ?>#band">Open</a>
    </div>
  <?php endforeach; ?>
  <form method="post" action="/voucher-rules" class="inline-add" style="margin-top:10px;align-items:flex-end">
    <input type="hidden" name="_do" value="band_add">
    <div class="ff"><label>New band</label><input class="form-control" name="name" placeholder="e.g. Field Inspector" required></div>
    <div class="ff"><label>Note <span class="muted">— optional</span></label><input class="form-control" name="notes"></div>
    <button class="btn secondary" type="submit">Add band</button>
  </form>
  <p class="muted" style="margin:8px 0 0;font-size:13px">Each person is given a band on their team-member record (Allowances &amp; rates), where you can also give them their own amount or an extra type.</p>
</div>

<?php if ($band): ?>
<form method="post" action="/voucher-rules?band=<?= (int)$band['id'] ?>" class="panel" id="band" style="max-width:860px">
  <input type="hidden" name="_do" value="band_save"><input type="hidden" name="band_id" value="<?= (int)$band['id'] ?>">
  <h3 class="tab-sub" style="margin-top:0">Band: <?= e($band['name']) ?> — what it may claim, and how much</h3>
  <div class="form-grid">
    <div class="ff"><label>Name</label><input class="form-control" name="name" value="<?= e($band['name']) ?>"></div>
    <div class="ff"><label>Note</label><input class="form-control" name="notes" value="<?= e($band['notes']) ?>"></div>
    <div class="ff"><label>These amounts apply from</label><input class="form-control" type="date" name="allow_from" value="<?= e(date('Y-m-d')) ?>" required></div>
    <div class="ff"><label class="ff-check"><input type="checkbox" name="active" value="1" <?= (int)$band['active'] ? 'checked' : '' ?>> In use</label></div>
  </div>
  <p class="muted" style="font-size:13px;margin:4px 0 10px">Showing the amounts that apply on <?= e(fdate($asOf)) ?><?= $bandDates ? ' · saved from: ' . e(implode(', ', array_map(fn($d) => $d === '' ? 'the start' : fdate($d), $bandDates))) : '' ?>. A new date keeps the older amounts for older claims; lines already confirmed keep their amounts.</p>
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="grid">
    <tr><th>May claim</th><th>Type</th><th>Kind</th><th>Amount / rate (<?= e($cs) ?>)</th><th>Limit (<?= e($cs) ?>)</th></tr>
    <?php foreach ($modes as $m): if ($m['basis'] !== 'PER_KM') continue; $r = $bandRows['MODE'][$m['code']] ?? null; ?>
      <tr><td><input type="checkbox" name="allow_mode[<?= e($m['code']) ?>]" value="1" <?= $r && (int)$r['allowed'] === 1 ? 'checked' : '' ?> aria-label="<?= e($m['label']) ?>" style="width:22px;height:22px"></td>
        <td><strong><?= e($m['label']) ?></strong></td><td class="muted">Per km<?= (int)$m['active'] ? '' : ' · off' ?></td>
        <td><input class="form-control" style="width:120px" type="number" step="0.01" min="0" name="mode_rate[<?= e($m['code']) ?>]" value="<?= e($r && $r['rate_override'] !== null ? (string)(float)$r['rate_override'] : '') ?>" placeholder="<?= e((string)(float)$m['default_rate']) ?> /km"></td>
        <td class="muted">—</td></tr>
    <?php endforeach; ?>
    <?php foreach ($heads as $h): if ($h['code'] === 'KMTRAVEL') continue; $r = $bandRows['HEAD'][$h['code']] ?? null; $k = exp_kind($h); ?>
      <tr><td><input type="checkbox" name="allow_head[<?= e($h['code']) ?>]" value="1" <?= $r && (int)$r['allowed'] === 1 ? 'checked' : '' ?> aria-label="<?= e($h['label']) ?>" style="width:22px;height:22px"></td>
        <td><strong><?= e($h['label']) ?></strong></td><td class="muted"><?= e(exp_kind_label($h)) ?><?= (int)$h['active'] ? '' : ' · off' ?></td>
        <td><?php if (in_array($k, ['PER_DAY', 'MONTHLY'], true)): ?><input class="form-control" style="width:120px" type="number" step="0.01" min="0" name="head_rate[<?= e($h['code']) ?>]" value="<?= e($r && $r['rate_override'] !== null ? (string)(float)$r['rate_override'] : '') ?>" placeholder="₹ __ <?= $k === 'MONTHLY' ? '/month' : '/day' ?>"><?php else: ?><span class="muted">the bill amount</span><?php endif; ?></td>
        <td><?php if (in_array($k, ['BILL', 'NOBILL'], true)): ?><input class="form-control" style="width:120px" type="number" step="0.01" min="0" name="head_limit[<?= e($h['code']) ?>]" value="<?= e($r && $r['limit_amount'] !== null ? (string)(float)$r['limit_amount'] : '') ?>" placeholder="<?= $k === 'NOBILL' ? 'per day' : 'up to' ?>"><?php else: ?><span class="muted">—</span><?php endif; ?></td></tr>
    <?php endforeach; ?>
  </table>
  </div>
  <p class="muted" style="font-size:13px;margin:8px 0 0">Above a limit is not refused — the approver sees it in amber (your E3). A per-day or monthly type with no amount adds nothing.</p>
  <button class="btn" type="submit" style="margin-top:12px">Save band</button>
</form>
<?php endif; ?>
