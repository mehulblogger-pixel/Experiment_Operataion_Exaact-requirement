<?php
// Set up → Costing heads: the library every costing role draws its heads from,
// with the date each value took effect, and the templates a role can start from.
// Owner, 9 Nov 2026 — "highly configurable as it changes with time".
$lib = $lib ?? []; $today = $today ?? []; $templates = $templates ?? []; $canManage = !empty($canManage);
$dpm = $dpm ?? 26; $editCode = $editCode ?? '';
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$csrf = function_exists('csrf_token') ? csrf_token() : '';
$labels = []; foreach ($lib as $c => $r) $labels[$c] = $r['label'];
$todayStr = date('Y-m-d');
$ed = ($editCode !== '' && isset($today[$editCode])) ? $today[$editCode] : (($editCode !== '' && isset($lib[$editCode])) ? $lib[$editCode] : null);
$ev = fn($k, $d = '') => $e($ed[$k] ?? $d);
$tone = ['PAY' => '#0f5f5c', 'STAT' => '#92400e', 'COST' => '#374151'];
?>
<style>
  .chl .tbl{width:100%;border-collapse:collapse;font-size:13px}
  .chl .tbl th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.3px;color:var(--muted);padding:8px;border-bottom:1px solid var(--line);background:var(--soft)}
  .chl .tbl td{padding:9px 8px;border-bottom:1px solid var(--line);vertical-align:top}
  .chl .tbl tr.off td{opacity:.55}
  .chl .code{font-family:ui-monospace,Menlo,monospace;font-size:11px;color:var(--muted)}
  .chl .tag{display:inline-block;font-size:10.5px;font-weight:700;border-radius:999px;padding:1px 8px;background:#fffbeb;color:#92400e;border:1px solid #fcd34d;margin-left:4px}
  .chl .grp{font-size:12px;font-weight:700;margin:14px 0 6px}
  .chl .acts{display:flex;gap:6px;flex-wrap:wrap}
  .chl .ofbox{display:flex;flex-wrap:wrap;gap:4px 12px;font-size:12.5px}
  .chl .ofbox label{display:inline-flex;gap:4px;align-items:center}
  .chl .t-only{display:none}
  @media(max-width:760px){ .chl .tbl thead{display:none} .chl .tbl tr{display:block;border:1px solid var(--line);border-radius:10px;margin-bottom:8px;padding:6px}
    .chl .tbl td{display:block;border:0;padding:4px 6px} }
</style>
<div class="chl">
<div class="master-head">
  <div><h1 style="margin:0">Costing heads</h1>
    <p class="sub" style="margin:2px 0 0">The heads any costing role can use — salary, statutory and other costs — and the ready-made templates a role can start from.</p></div>
  <div class="row-actions"><a class="btn secondary" href="/project-costings">← Project costing</a></div>
</div>

<div class="msg msg-info" style="margin:0 0 14px">
  <b>How it works.</b> A head is copied into a role when it is added, so <b>changing a value here never changes a costing already made</b> — each role keeps the value it was priced with.
  When a rule changes (for example the PF wage ceiling), add the new value <b>from the date it takes effect</b>: roles made from that date get it, and the old value stays in the history.
  The statutory starting values are common Indian figures, given to save typing — <b>confirm each one with your accountant</b>. India's new Labour Codes also change what counts as "wages" for PF; check your base with them too.
</div>

<div class="panel" style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end">
  <div style="flex:1;min-width:240px"><b>Working days in a month</b>
    <p class="sub" style="margin:2px 0 0;font-size:12.5px">Turns a per-day head (e.g. a ₹250 daily food allowance) into a month, and a monthly head into a day rate on a daily sheet. Each role can still change it.</p></div>
  <?php if ($canManage): ?>
  <form method="post" action="/project-costing-heads" style="display:flex;gap:8px;align-items:flex-end">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="do" value="dpm">
    <div class="ff" style="margin:0"><label for="dpm" style="font-size:12px">Days</label><input class="form-control" id="dpm" type="number" name="dpm" min="1" max="31" step="0.5" value="<?= $e($dpm) ?>" style="width:100px"></div>
    <button class="btn secondary">Save</button></form>
  <?php else: ?><b><?= $e($dpm) ?> days</b><?php endif; ?>
</div>

<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">The library <span class="muted">— <?= count($today) ?> heads</span></h3>
  <?php foreach (CH_GROUPS as $gk => $gl): $rowsG = array_filter($lib, fn($r) => ($r['grp'] ?? 'COST') === $gk); if (!$rowsG) continue; ?>
    <div class="grp" style="color:<?= $tone[$gk] ?>"><?= $e($gl) ?></div>
    <div style="overflow-x:auto"><table class="tbl">
      <thead><tr><th style="width:24%">Head</th><th>How it is worked out</th><th style="width:15%">Value from</th><th style="width:18%"></th></tr></thead>
      <tbody>
      <?php foreach ($rowsG as $code => $r):
        $now = $today[$code] ?? null; $shown = $now ?: $r;
        $vers = ch_library_versions($code); $future = array_filter($vers, fn($v) => (string)$v['effective_from'] > $todayStr); ?>
        <tr id="h-<?= $e($code) ?>" class="<?= (int)$r['active'] === 1 ? '' : 'off' ?>" style="scroll-margin-top:80px">
          <td><b><?= $e($shown['label']) ?></b><?= (int)$shown['statutory'] ? '<span class="tag">statutory</span>' : '' ?><?= (int)$r['active'] === 1 ? '' : ' <span class="muted">(retired)</span>' ?><div class="code"><?= $e($code) ?></div></td>
          <td><?= $e(ch_item_summary($shown, $labels)) ?>
            <?php if (!empty($shown['note'])): ?><div class="muted" style="font-size:12px;margin-top:3px">💬 <?= $e($shown['note']) ?></div><?php endif; ?>
            <?php foreach ($future as $fv): $fs = json_decode((string)$fv['spec'], true) ?: []; ?>
              <div style="font-size:12px;margin-top:4px;color:#92400e">From <?= $e(fdate($fv['effective_from'])) ?>: <?= $e(ch_item_summary($fs + ['type' => 'AMOUNT'], $labels)) ?></div>
            <?php endforeach; ?></td>
          <td><?= $now ? ((string)$now['effective_from'] <= '2000-01-01' ? '<span class="muted">from the start</span>' : $e(fdate($now['effective_from']))) : '<span class="muted">not yet</span>' ?>
            <?php if (count($vers) > 1): ?><details style="font-size:12px;margin-top:4px"><summary class="muted" style="cursor:pointer"><?= count($vers) ?> values</summary>
              <?php foreach ($vers as $v): $vs = json_decode((string)$v['spec'], true) ?: []; ?>
                <div style="margin-top:4px"><b><?= (string)$v['effective_from'] <= '2000-01-01' ? 'Start' : $e(fdate($v['effective_from'])) ?></b> — <?= $e(ch_item_summary($vs + ['type' => 'AMOUNT'], $labels)) ?> <span class="muted">· <?= $e($v['updated_by']) ?></span></div>
              <?php endforeach; ?></details><?php endif; ?></td>
          <td><?php if ($canManage): ?><div class="acts">
            <a class="btn small secondary" href="/project-costing-heads?edit=<?= $e($code) ?>#edit">Change</a>
            <form method="post" action="/project-costing-heads"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="do" value="head_active"><input type="hidden" name="code" value="<?= $e($code) ?>"><input type="hidden" name="on" value="<?= (int)$r['active'] === 1 ? '' : '1' ?>">
              <button class="btn small secondary"><?= (int)$r['active'] === 1 ? 'Retire' : 'Bring back' ?></button></form></div><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
  <?php endforeach; ?>
</div>

<?php if ($canManage): ?>
<div class="panel" id="edit" style="scroll-margin-top:70px">
  <h3 class="tab-sub" style="margin-top:0"><?= $ed ? 'Change "' . $e($ed['label']) . '"' : '＋ Add a head to the library' ?>
    <?php if ($ed): ?><a class="muted" href="/project-costing-heads#edit" style="font-size:12.5px;font-weight:400;margin-left:8px">Add a new head instead</a><?php endif; ?></h3>
  <form method="post" action="/project-costing-heads" id="chForm">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="do" value="head_save">
    <div class="form-grid">
      <div class="ff ff-wide"><label for="f_label">Name</label><input class="form-control" id="f_label" name="label" required maxlength="80" value="<?= $ev('label') ?>" placeholder="e.g. Night shift allowance"></div>
      <div class="ff"><label for="f_code">Short name <span class="muted">used in % and formulas</span></label><input class="form-control" id="f_code" name="code" maxlength="24" value="<?= $ev('code') ?>" <?= $ed ? 'readonly' : '' ?> placeholder="made from the name" style="text-transform:uppercase"></div>
      <div class="ff"><label for="f_grp">Group</label><select class="form-control" id="f_grp" name="grp"><?php foreach (CH_GROUPS as $k => $v): ?><option value="<?= $k ?>" <?= ($ed['grp'] ?? 'COST') === $k ? 'selected' : '' ?>><?= $e($v) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label for="f_type">How it is worked out</label><select class="form-control" id="f_type" name="type"><?php foreach (CH_TYPES as $k => $v): ?><option value="<?= $k ?>" <?= ($ed['type'] ?? 'AMOUNT') === $k ? 'selected' : '' ?>><?= $e($v) ?></option><?php endforeach; ?></select></div>
      <div class="ff"><label for="f_per">Entered</label><select class="form-control" id="f_per" name="per"><?php foreach (CH_PER as $k => $v): ?><option value="<?= $k ?>" <?= ($ed['per'] ?? 'MONTH') === $k ? 'selected' : '' ?>><?= $e($v) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-grid t-only" data-t="AMOUNT" style="margin-top:6px">
      <div class="ff"><label for="f_amt">Amount <span class="muted">a calculator: 1500*2 is fine; 0 = filled in on each role</span></label><input class="form-control" id="f_amt" name="amount" value="<?= $e($ed['calc'] ?? ($ed['amount'] ?? '')) ?>"></div>
    </div>
    <div class="t-only" data-t="PCT" style="margin-top:6px">
      <div class="form-grid">
        <div class="ff"><label for="f_pct">Percent</label><input class="form-control" id="f_pct" type="number" step="any" name="pct" value="<?= $ev('pct') ?>"></div>
        <div class="ff"><label for="f_cap">Counted up to <span class="muted">optional — e.g. 15,000 for PF</span></label><input class="form-control" id="f_cap" type="number" step="any" min="0" name="cap" value="<?= (float)($ed['cap'] ?? 0) ? $ev('cap') : '' ?>"></div>
        <div class="ff"><label for="f_upto">Applies only while the base is up to <span class="muted">optional — e.g. 21,000 for ESIC</span></label><input class="form-control" id="f_upto" type="number" step="any" min="0" name="upto" value="<?= (float)($ed['upto'] ?? 0) ? $ev('upto') : '' ?>"></div>
      </div>
      <div class="ff" style="margin-top:6px"><label>Of which heads</label><div class="ofbox">
        <?php foreach ($today as $c => $r): if ($ed && $c === ($ed['code'] ?? '')) continue; ?><label><input type="checkbox" name="of[]" value="<?= $e($c) ?>" <?= in_array($c, (array)($ed['of'] ?? []), true) ? 'checked' : '' ?>> <?= $e($r['label']) ?></label><?php endforeach; ?></div>
        <p class="sub" style="margin:4px 0 0;font-size:12px">A role uses this % only if it also has those heads — a % of Basic takes that person's Basic.</p></div>
    </div>
    <div class="form-grid t-only" data-t="QTYRATE" style="margin-top:6px">
      <div class="ff"><label for="f_qty">Quantity</label><input class="form-control" id="f_qty" type="number" step="any" name="qty" value="<?= $ev('qty') ?>"></div>
      <div class="ff"><label for="f_rate">Rate</label><input class="form-control" id="f_rate" type="number" step="any" name="rate" value="<?= $ev('rate') ?>"></div>
    </div>
    <div class="form-grid t-only" data-t="FORMULA" style="margin-top:6px">
      <div class="ff ff-wide"><label for="f_expr">Formula <span class="muted">short names, numbers, + − * / ( ), 12% and MIN MAX ROUND CEIL FLOOR IF</span></label><input class="form-control" id="f_expr" name="expr" value="<?= $ev('expr') ?>" placeholder="e.g. IF(BASIC+HRA<=21000, (BASIC+HRA)*3.25%, 0)"></div>
    </div>
    <div class="form-grid" style="margin-top:6px">
      <div class="ff"><label for="f_min">Minimum <span class="muted">optional</span></label><input class="form-control" id="f_min" type="number" step="any" name="min" value="<?= isset($ed['min']) ? $ev('min') : '' ?>"></div>
      <div class="ff"><label for="f_max">Maximum <span class="muted">optional</span></label><input class="form-control" id="f_max" type="number" step="any" name="max" value="<?= isset($ed['max']) ? $ev('max') : '' ?>"></div>
      <div class="ff"><label for="f_from">Takes effect from</label><input class="form-control" id="f_from" type="date" name="from" required value="<?= $e($todayStr) ?>"></div>
      <div class="ff" style="justify-content:flex-end"><label style="display:flex;gap:6px;align-items:center;font-weight:400"><input type="checkbox" name="statutory" value="1" <?= !empty($ed['statutory']) ? 'checked' : '' ?>> A statutory (legal) rate</label></div>
      <div class="ff ff-wide"><label for="f_note">Remark <span class="muted">shown on every role that uses it</span></label><input class="form-control" id="f_note" name="note" maxlength="300" value="<?= $ev('note') ?>"></div>
    </div>
    <div style="margin-top:10px"><button class="btn"><?= $ed ? 'Save the new value' : 'Add to the library' ?></button>
      <span class="muted" style="font-size:12.5px;margin-left:8px">Costings already made keep their values.</span></div>
  </form>
</div>
<script>(function(){ var t=document.getElementById('f_type'); if(!t) return;
  function sh(){ document.querySelectorAll('#chForm .t-only').forEach(function(el){ el.style.display = el.dataset.t===t.value ? '' : 'none'; }); }
  t.addEventListener('change',sh); sh(); })();</script>
<?php endif; ?>

<div class="panel" id="templates" style="scroll-margin-top:70px">
  <h3 class="tab-sub" style="margin-top:0">Templates <span class="muted">— a ready list of heads to start a role from</span></h3>
  <p class="sub" style="margin:0 0 8px;font-size:12.5px">To make one, build a role on any costing and press <b>Save as a template</b> under its heads.</p>
  <?php if (!$templates): ?><div class="muted">No templates yet.</div><?php endif; ?>
  <?php foreach ($templates as $t): $ti = json_decode((string)$t['items'], true) ?: []; ?>
    <div style="border:1px solid var(--line);border-radius:10px;padding:10px 12px;margin-bottom:8px;<?= (int)$t['active'] === 1 ? '' : 'opacity:.55' ?>">
      <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap"><div><b><?= $e($t['name']) ?></b> <span class="muted" style="font-size:12px">· <?= count($ti) ?> heads<?= (int)$t['active'] === 1 ? '' : ' · removed' ?></span>
        <?php if ($t['note'] !== ''): ?><div class="muted" style="font-size:12.5px"><?= $e($t['note']) ?></div><?php endif; ?></div>
        <?php if ($canManage): ?><form method="post" action="/project-costing-heads"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="do" value="template_active"><input type="hidden" name="tpl_id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="on" value="<?= (int)$t['active'] === 1 ? '' : '1' ?>">
          <button class="btn small secondary"><?= (int)$t['active'] === 1 ? 'Remove' : 'Bring back' ?></button></form><?php endif; ?></div>
      <div style="font-size:12.5px;margin-top:6px;color:var(--muted)"><?= $e(implode(' · ', array_map(fn($i) => ($i['label'] ?? '') . ' (' . ch_item_summary($i, array_column($ti, 'label', 'code')) . ')', $ti))) ?></div>
    </div>
  <?php endforeach; ?>
</div>
</div>
