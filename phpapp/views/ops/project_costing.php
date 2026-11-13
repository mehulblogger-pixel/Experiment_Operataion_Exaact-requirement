<?php
// Project costing — the builder / cost sheet.
$roll = $roll ?? null; if (!$roll) { echo '<p>Not found.</p>'; return; }
$h = $roll['header']; $lines = $roll['lines']; $T = $roll['totals']; $byHead = $roll['by_head'];
$heads = $heads ?? []; $clients = $clients ?? []; $offices = $offices ?? [];
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$csrf = function_exists('csrf_token') ? csrf_token() : '';
$sym = function_exists('cur_sym') ? cur_sym() : '₹';
$locked = function_exists('pc_locked') && pc_locked($h);
$canEdit = function_exists('pc_can_edit') && pc_can_edit() && !$locked;
// Approving follows the quotation levels (owner, 20 Oct 2026): the person who
// may sign is the one the CURRENT level waits for, not anyone who may approve.
$canApprove = function_exists('pc_can_act') ? pc_can_act($h) : (function_exists('pc_can_approve') && pc_can_approve());
$pcSteps = function_exists('pc_steps') ? pc_steps((int)$h['id']) : [];
$pcOpen = function_exists('pc_open_levels') ? pc_open_levels((int)$h['id']) : [];
$sbuOpts = $sbuOpts ?? (function_exists('lk_options_or') ? lk_options_or('sbu', OPS_SBUS) : []);
$forInq  = !empty($h['inquiry_id']) ? ops_one("SELECT id, inquiry_no, subject FROM crm_inquiries WHERE id=?", [(int)$h['inquiry_id']]) : null;
$forLead = !empty($h['lead_id']) && function_exists('lead_row') ? lead_row((int)$h['lead_id']) : null;
// D-11 B — its author never approves it, whatever their role.
$isAuthor = function_exists('pc_is_author') && pc_is_author($h);
$basis = (string)$h['basis'];
$qtyLabel = $basis === 'DAILY' ? 'Man-days' : ($basis === 'FIXED' ? 'Units' : 'Man-months');
$rateLabel = $basis === 'DAILY' ? '/man-day' : ($basis === 'FIXED' ? '/lump' : '/man-month');
$stTone = ['DRAFT'=>'p-mut','SUBMITTED'=>'p-warn','APPROVED'=>'p-ok','REJECTED'=>'p-bad'];
// Only show head COLUMNS that are actually used by a role, so a full catalogue
// (18 heads) does not make the table unreadably wide. The add-role form below
// still offers every head.
$heads = ($roll['labels'] ?? []) + $heads;   // a role's own names for its heads come first
$shownHeads = array_filter($heads, fn($lbl, $hk) => isset($byHead[$hk]) && (float)$byHead[$hk] != 0, ARRAY_FILTER_USE_BOTH);
if (!$shownHeads) $shownHeads = [];
$nCols = count($shownHeads) + ($canEdit ? 9 : 8);
?>
<style>
  .pc .kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:12px 0}
  .pc .kpi{background:var(--card);border:1px solid var(--line);border-left:4px solid var(--brand);border-radius:12px;padding:12px 14px}
  .pc .kpi .l{font-size:10.5px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);font-weight:700}
  .pc .kpi .v{font-size:22px;font-weight:800;font-variant-numeric:tabular-nums;margin-top:3px}
  .pc .kpi.ok{border-left-color:var(--ok)} .pc .kpi.ok .v{color:var(--ok)}
  .pc .kpi.bad{border-left-color:var(--bad)} .pc .kpi.bad .v{color:var(--bad)}
  .pc table.grid{font-size:12.5px} .pc table.grid th{white-space:nowrap}
  .pc .num{text-align:right;font-variant-numeric:tabular-nums}
  .pc .hh input{width:100%;padding:6px 7px;border:1px solid var(--line);border-radius:7px;font-size:12.5px;box-sizing:border-box}
  .pc .addrow td{background:var(--soft)} .pc .grp{font-weight:700;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.4px}
  /* Not .bar: app.css draws .bar as an 8px progress strip (overflow hidden), which
     squeezed the Submit / Approve / Make-a-quotation buttons out of sight. */
  .pc .pc-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:10px 0}
  .pc .lockmsg{background:var(--warn-soft,#fdf1e3);border:1px solid var(--line);border-radius:9px;padding:8px 12px;font-size:12.5px;margin:10px 0}
  @media(max-width:1000px){.pc .kpis{grid-template-columns:repeat(2,1fr)} .pc .wide-scroll{overflow-x:auto}}
</style>

<div class="pc">
<div class="master-head">
  <div><h1 style="margin:0"><?= $e($h['title']) ?> <span class="pill <?= $stTone[$h['status']] ?? 'p-mut' ?>"><?= $e(PC_STATUS[$h['status']] ?? $h['status']) ?></span></h1>
    <p class="sub" style="margin:2px 0 0"><?= $e($h['code']) ?> · <?= $e(PC_BASES[$basis] ?? $basis) ?><?= !empty($h['site']) ? ' · '.$e($h['site']) : '' ?><?= !empty($h['sbu']) ? ' · ' . $e($sbuOpts[$h['sbu']] ?? $h['sbu']) : '' ?></p>
    <p class="sub" style="margin:2px 0 0">For:
      <?php if ($forInq): ?><a href="/inquiry-edit?id=<?= (int)$forInq['id'] ?>">enquiry <?= $e($forInq['inquiry_no']) ?></a><?php endif; ?>
      <?php if ($forInq && $forLead): ?> · <?php endif; ?>
      <?php if ($forLead): ?><a href="/lead?id=<?= (int)$forLead['id'] ?>">lead <?= $e($forLead['ref']) ?> — <?= $e($forLead['company_name']) ?></a><?php endif; ?>
      <?php if (!$forInq && !$forLead): ?><span class="muted">no enquiry or lead tagged<?= !empty($h['opportunity_id']) ? ' (made from a deal)' : (!empty($h['quote_id']) ? ' (made for a quotation)' : '') ?></span><?php endif; ?>
    </p></div>
  <div class="row-actions"><a class="btn secondary" href="/project-costing-print?id=<?= (int)$h['id'] ?>" target="_blank" rel="noopener">🖨 Print / PDF</a></div>
</div>

<?php if ($locked): ?><div class="lockmsg">🔒 This costing is <b><?= $e(PC_STATUS[$h['status']] ?? $h['status']) ?></b> and read-only.<?php if ($h['status']==='APPROVED' && !empty($h['approved_by'])): ?> Approved by <?= $e($h['approved_by']) ?><?= !empty($h['approval_ref']) ? ' · ref '.$e($h['approval_ref']) : '' ?>.<?php endif; ?><?php if ($canEdit===false && (pc_can_edit())): ?> <form method="post" action="/project-costing-reopen" style="display:inline"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><button class="btn btn-ghost" style="padding:4px 10px">Reopen as draft</button></form><?php endif; ?></div><?php endif; ?>
<?php if ($h['status']==='REJECTED' && !empty($h['reject_note'])): ?><div class="lockmsg" style="background:var(--bad-soft,#fdecec)">↩ Sent back: <?= $e($h['reject_note']) ?></div><?php endif; ?>

<!-- Project rollup -->
<div class="kpis">
  <div class="kpi"><div class="l">Project revenue</div><div class="v"><?= fmoney_short($T['revenue']) ?></div></div>
  <div class="kpi"><div class="l">Project cost</div><div class="v"><?= fmoney_short($T['cost']) ?></div></div>
  <div class="kpi <?= $T['profit']>=0?'ok':'bad' ?>"><div class="l">Project profit</div><div class="v"><?= fmoney_short($T['profit']) ?></div></div>
  <div class="kpi <?= $T['margin']>=0?'ok':'bad' ?>"><div class="l">Margin</div><div class="v"><?= number_format($T['margin'],1) ?>%</div></div>
</div>

<?php // Why the figures can read ₹0. The totals are built up from the team roles
      // below — saving the header alone never produces a number. Say so, plainly,
      // instead of leaving a wall of zeros with no explanation. ?>
<?php if ((float)$T['revenue'] == 0 && (float)$T['cost'] == 0): ?>
  <div class="msg msg-info" style="margin:0 0 12px">
    <?php if (empty($lines)): ?>
      <b>The totals are ₹0 because no team roles have been added yet.</b>
      The revenue, cost and margin build up from the roles below — <b>saving the header alone does not produce a figure</b>.
      <?php if ($canEdit): ?>Add a role under <a href="#add-role"><b>＋ Add a role</b></a>, add its cost heads (start from a template, or pick heads from the library) and the BOQ quantity, and the numbers appear here.<?php endif; ?>
    <?php else: ?>
      <b>You have <?= count($lines) ?> role(s) but every figure is still ₹0.</b>
      A role only contributes once it has a <b>cost head</b> amount (e.g. the monthly salary) <i>and</i> a <b>BOQ quantity</b>.
      <?php if ($canEdit): ?>Press ✎ Edit on a role and fill its cost heads and quantity.<?php endif; ?>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- Roles table -->
<div class="panel wide-scroll">
  <h3 class="tab-sub" style="margin-top:0">Team roles <span class="muted">— <?= count($lines) ?> role(s), each built up from the cost heads</span></h3>
  <table class="grid">
    <thead><tr><th>Role</th>
      <?php foreach ($shownHeads as $hk=>$hl): ?><th class="num" title="<?= $e($hl) ?>"><?= $e(mb_strimwidth($hl,0,10,'…')) ?></th><?php endforeach; ?>
      <th class="num">Direct/mo</th><th class="num">Loaded/mo</th><th class="num">Rate<?= $e($rateLabel) ?></th><th class="num"><?= $e($qtyLabel) ?></th><th class="num">Revenue</th><th class="num">Profit</th><th class="num">Margin</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php $lastGrp = null; foreach ($lines as $ln): $c = pc_line_calc($ln, $h); $lh = $c['heads'];
      if (($ln['group_label'] ?? '') !== $lastGrp && ($ln['group_label'] ?? '') !== '') { $lastGrp = $ln['group_label']; echo '<tr><td colspan="'.$nCols.'" class="grp">'.$e($lastGrp).'</td></tr>'; } ?>
      <tr>
        <td><b><?= $e($ln['role_label']) ?></b></td>
        <?php foreach ($shownHeads as $hk=>$hl): ?><td class="num"><?= isset($lh[$hk]) ? number_format((float)$lh[$hk]) : '<span class="muted">—</span>' ?></td><?php endforeach; ?>
        <td class="num"><?= number_format($c['direct']) ?></td>
        <td class="num"><?= number_format($c['loaded']) ?></td>
        <td class="num"><b><?= number_format($c['proposed']) ?></b><?= (float)$ln['target_margin']>0 && (float)$ln['rate_manual']<=0 ? '<br><span class="muted" style="font-size:10px">@'.rtrim(rtrim(number_format((float)$ln['target_margin'],1),'0'),'.').'% marg</span>' : '' ?></td>
        <td class="num"><?= $basis==='FIXED' ? '1' : number_format((float)$ln['boq_qty'],($basis==='DAILY'?0:1)) ?><?= (float)$ln['oneoff']>0 ? '<br><span class="muted" style="font-size:10px">+'.fmoney_short($ln['oneoff']).' 1-off</span>' : '' ?></td>
        <td class="num"><?= fmoney_short($c['revenue']) ?></td>
        <td class="num" style="color:var(--<?= $c['profit']>=0?'ok':'bad' ?>)"><?= fmoney_short($c['profit']) ?></td>
        <td class="num"><?= number_format($c['margin'],1) ?>%</td>
        <?php if ($canEdit): ?><td class="num" style="white-space:nowrap"><a class="btn btn-ghost" style="padding:2px 8px" href="/project-costing?id=<?= (int)$h['id'] ?>&amp;edit=<?= (int)$ln['id'] ?>#add-role" title="Edit this role's heads">✎ Edit</a> <form method="post" action="/project-costing-line-delete" onsubmit="return confirm('Remove this role?')" style="display:inline"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="costing_id" value="<?= (int)$h['id'] ?>"><input type="hidden" name="line_id" value="<?= (int)$ln['id'] ?>"><button class="btn btn-ghost" style="padding:2px 8px" title="Remove">✕</button></form></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($lines)): ?><tr><td colspan="<?= $nCols ?>" class="muted" style="padding:10px">No roles yet.<?= $canEdit ? ' Add the first below.' : '' ?></td></tr><?php endif; ?>
    </tbody>
    <?php if (!empty($lines)): ?>
    <tfoot><tr style="border-top:2px solid var(--line);font-weight:700">
      <td>Totals</td>
      <?php foreach ($shownHeads as $hk=>$hl): ?><td class="num"><?= isset($byHead[$hk]) ? number_format((float)$byHead[$hk]) : '' ?></td><?php endforeach; ?>
      <td class="num"><?= number_format($T['direct']) ?></td><td></td><td></td><td></td>
      <td class="num"><?= fmoney_short($T['revenue']) ?></td>
      <td class="num" style="color:var(--<?= $T['profit']>=0?'ok':'bad' ?>)"><?= fmoney_short($T['profit']) ?></td>
      <td class="num"><?= number_format($T['margin'],1) ?>%</td><?php if ($canEdit): ?><td></td><?php endif; ?>
    </tr></tfoot>
    <?php endif; ?>
  </table>
</div>

<?php // ---- Use this costing: quotation + requirements --------------------------
  $canQuote = function_exists('can') && can('mod.quotes.edit');
  $canHire  = function_exists('can') && can('mod.hiring.edit');
  if (!empty($lines) && ($canQuote || $canHire)): ?>
<div class="panel" id="use" style="scroll-margin-top:70px">
  <h3 class="tab-sub" style="margin-top:0">Use this costing</h3>
  <div class="pc-actions" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:6px">
    <?php if ($canQuote): ?>
      <?php if (!empty($h['quote_id'])): ?>
        <a class="btn secondary" href="/quote?id=<?= (int)$h['quote_id'] ?>">📝 View linked quotation</a>
        <span class="muted" style="font-size:12px">A quotation was generated from this costing.</span>
      <?php else: ?>
        <form method="post" action="/project-costing-to-quote"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><button class="btn primary">📝 Create quotation</button></form>
        <span class="muted" style="font-size:12px">One quote line per role, priced at the proposed rate × <?= $e(strtolower($qtyLabel)) ?>.</span>
      <?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($h['opportunity_id'])): ?><a class="btn secondary" href="/opportunity?id=<?= (int)$h['opportunity_id'] ?>">💡 Linked deal</a><?php endif; ?>
  </div>
  <?php if ($canHire):
    // Owner, 9 Nov 2026: recruitment starts only once the client has accepted
    // the quotation — before that the buttons are not offered, and the reason is shown.
    $gate = function_exists('pc_hire_gate') ? pc_hire_gate($h) : '';
    $direct = !function_exists('hreq_direct_path_allowed') || hreq_direct_path_allowed();
    $accQ = function_exists('pc_accepted_quote') ? pc_accepted_quote($h) : null; ?>
  <h4 style="margin:14px 0 6px;font-size:14px">Recruitment for this costing</h4>
  <?php if ($gate !== ''): ?>
    <div class="msg msg-info" style="margin:0 0 8px">🔒 <?= $e($gate) ?></div>
  <?php else: ?>
    <p class="sub" style="margin:0 0 8px">Quotation <b><?= $e($accQ['quote_no'] ?? '') ?></b> was accepted by the client. Raise a hiring request for each role — it goes through your hiring-request approvals, and its requisition takes this costing's rate and margin as its budget.</p>
  <?php endif; ?>
  <div class="scroll"><table class="grid" style="font-size:12.5px">
    <thead><tr><th>Role</th><th class="num">People</th><th class="num">Rate<?= $e($rateLabel) ?></th><th class="num">Loaded/mo</th><th>Raised</th><th>Last candidate vs costing</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($lines as $ln): $c = pc_line_calc($ln, $h);
          $ppl = function_exists('pc_line_people') ? pc_line_people($ln) : 1;
          $done = function_exists('pc_line_raised') ? pc_line_raised((int)$ln['id']) : 0;
          $lk = function_exists('pc_line_links') ? pc_line_links((int)$ln['id']) : ['hreq' => [], 'req' => []]; ?>
      <tr>
        <td><?= !empty($ln['group_label']) ? '<span class="muted">'.$e($ln['group_label']).' / </span>' : '' ?><b><?= $e($ln['role_label']) ?></b></td>
        <td class="num"><?= (int)$ppl ?></td>
        <td class="num"><?= number_format($c['proposed']) ?></td>
        <td class="num"><?= number_format($c['loaded']) ?></td>
        <td><?php foreach ($lk['hreq'] as $x): ?><a href="/hiring-request?id=<?= (int)$x['id'] ?>"><?= $e($x['req_no']) ?></a> <span class="muted">(<?= (int)$x['quantity'] ?> · <?= $e(strtolower(str_replace('_', ' ', (string)$x['status']))) ?>)</span><br><?php endforeach; ?>
            <?php foreach ($lk['req'] as $x): ?><a href="/requisition?id=<?= (int)$x['id'] ?>"><?= $e($x['req_code']) ?></a> <span class="muted">(<?= (int)$x['quantity'] ?> · <?= $e(strtolower((string)$x['status'])) ?>)</span><br><?php endforeach; ?>
            <?php if (!$lk['hreq'] && !$lk['req']): ?><span class="muted">—</span><?php endif; ?></td>
        <?php // Part 3 — the most recent candidate for this role, measured against it. ?>
        <td style="font-size:12px;max-width:260px"><?php $lc = function_exists('pc_line_last_candidate') ? pc_line_last_candidate((int)$ln['id']) : null;
            if ($lc): $lk3 = pc_cand_check($lc); $vt = ['OK' => 'p-ok', 'LOW' => 'p-warn', 'LOSS' => 'p-bad', 'NONE' => 'p-mut'][$lk3['verdict']] ?? 'p-mut'; ?>
            <a href="/candidate?id=<?= (int)$lc['id'] ?>"><?= $e(trim(($lc['first_name'] ?? '') . ' ' . ($lc['last_name'] ?? ''))) ?></a>
            <span class="pill <?= $vt ?>"><?= $e(rtrim(rtrim(number_format((float)$lk3['margin'], 1), '0'), '.')) ?>% vs <?= $e(rtrim(rtrim(number_format((float)$lk3['target'], 1), '0'), '.')) ?>%</span>
            <?php if (function_exists('can_see_salary') && can_see_salary()): ?><br><span class="muted">up to <?= $e($sym . number_format($lk3['max_ctc'])) ?>/mo keeps the target</span><?php endif; ?>
          <?php else: ?><span class="muted">no offer yet</span><?php endif; ?></td>
        <td class="num" style="white-space:nowrap"><?php if ($gate === '' && $done < $ppl): ?>
          <form method="post" action="/project-costing-to-hreq" style="display:inline"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="costing_id" value="<?= (int)$h['id'] ?>"><input type="hidden" name="line_id" value="<?= (int)$ln['id'] ?>"><button class="btn" style="padding:3px 10px" title="Raise a hiring request for the <?= (int)($ppl - $done) ?> people not yet asked for">→ Hiring request (<?= (int)($ppl - $done) ?>)</button></form>
          <?php if ($direct): ?><form method="post" action="/project-costing-to-req" style="display:inline"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="costing_id" value="<?= (int)$h['id'] ?>"><input type="hidden" name="line_id" value="<?= (int)$ln['id'] ?>"><button class="btn btn-ghost" style="padding:3px 10px" title="Your workspace allows starting recruitment without a hiring request">→ Requisition directly</button></form><?php endif; ?>
        <?php elseif ($gate === ''): ?><span class="muted">All asked for</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($gate === '' && count($lines) > 1): ?>
    <form method="post" action="/project-costing-to-hreq" style="margin-top:8px"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="costing_id" value="<?= (int)$h['id'] ?>"><input type="hidden" name="all" value="1">
      <button class="btn">→ Raise hiring requests for every role</button></form>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($canEdit):
  // ---- The role editor: flexible cost heads (owner, 9 Nov 2026) ------------
  //  Heads are drawn from the library (Set up → Costing heads) or typed fresh;
  //  each one an amount (a calculator), a % of other heads, quantity × rate or a
  //  formula; per month / day / year / one time; with a remark. The figures are
  //  worked out by the server as you type (/project-costing-line-preview), so
  //  what you see is exactly what is saved.
  $chLib = $chLib ?? []; $chTpls = $chTpls ?? []; $chManage = !empty($chManage);
  $draft = $_SESSION['pc_line_draft'][(int)$h['id']] ?? null; unset($_SESSION['pc_line_draft'][(int)$h['id']]);
  $editId = $draft ? (int)$draft['line_id'] : (int)($_GET['edit'] ?? 0);
  $editLn = null; foreach ($lines as $ln) if ((int)$ln['id'] === $editId) $editLn = $ln;
  if (!$editLn) $editId = 0;
  $dpmDefault = function_exists('ch_days_per_month') ? ch_days_per_month() : 26;
  if ($draft) {
    $f = $draft['post']; $initItems = json_decode((string)($f['items_json'] ?? '[]'), true) ?: [];
  } elseif ($editLn) {
    $sp = ch_spec_of($editLn, $basis); $initItems = $sp['items'];
    $f = ['group_label' => $editLn['group_label'], 'role_label' => $editLn['role_label'], 'headcount' => $editLn['headcount'] ?? 1,
          'boq_qty' => $editLn['boq_qty'], 'target_margin' => $editLn['target_margin'], 'rate_manual' => $editLn['rate_manual'], 'dpm' => $sp['dpm']];
  } else {
    $initItems = []; $f = ['headcount' => 1, 'boq_qty' => $basis === 'FIXED' ? 1 : '', 'dpm' => $dpmDefault];
  }
  $fv = fn($k) => $e($f[$k] ?? '');
  $num0 = fn($k) => (float)($f[$k] ?? 0) == 0 ? '' : $e($f[$k]);
  $libJs = []; foreach ($chLib as $code => $r) { $it = ch_library_item($code); if ($it) $libJs[] = $it + ['statutory' => (int)$r['statutory']]; }
  $tplJs = []; foreach ($chTpls as $t) $tplJs[] = ['id' => (int)$t['id'], 'name' => $t['name'], 'note' => $t['note'], 'items' => ch_template_items((int)$t['id'])];
  $unitWord = $basis === 'DAILY' ? 'per man-day' : ($basis === 'FIXED' ? 'per person' : 'per man-month');
?>
<style>
  .che{margin-top:6px}
  .che .ch-head{display:grid;grid-template-columns:28px minmax(130px,1.6fr) 100px 136px minmax(190px,2.6fr) 100px minmax(96px,1fr) 32px;gap:8px;align-items:start;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.3px;padding:0 8px 4px}
  .che .ch-row{display:grid;grid-template-columns:28px minmax(130px,1.6fr) 100px 136px minmax(190px,2.6fr) 100px minmax(96px,1fr) 32px;gap:8px;align-items:start;border:1px solid var(--line);border-radius:10px;padding:8px;margin-bottom:6px;background:var(--card)}
  .che .ch-row > *{min-width:0}   /* every column may shrink — nothing pushes past the panel's edge */
  .che .ch-row.err{border-color:var(--red,#dc2626);background:#fef2f2}
  .che input,.che select{width:100%;box-sizing:border-box;padding:7px 8px;border:1px solid var(--field-line,#cbd5e1);border-radius:7px;font-size:13px;background:var(--card)}
  .che .mv{display:flex;flex-direction:column;gap:2px}
  .che .mv button,.che .rm{border:1px solid var(--line);background:var(--soft);border-radius:6px;cursor:pointer;font-size:12px;line-height:1;padding:4px 0;min-height:22px}
  .che .rm{padding:7px 0;color:var(--red,#b91c1c)}
  .che .code{font-size:10.5px;color:var(--muted);margin-top:3px;font-family:ui-monospace,Menlo,monospace}
  .che .val{display:flex;gap:6px;flex-wrap:wrap;align-items:center;font-size:12.5px}
  .che .val > *{flex:1 1 70px}
  .che .val .w{flex:0 0 auto;color:var(--muted)}
  /* "% of other heads" — owner, 13 Nov 2026: "it bleeds and is weird, confusing".
     The picker is a pop-over ABOVE the page (it no longer pushes or spills over the
     row), two columns, with Done; its button always says what is chosen. */
  .che .ofd{flex:1 1 90px;font-size:12.5px;min-width:0;position:relative}
  .che .ofd summary{cursor:pointer;padding:7px 26px 7px 8px;border:1px solid var(--field-line,#cbd5e1);border-radius:7px;background:var(--card);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;list-style:none;position:relative}
  .che .ofd summary::-webkit-details-marker{display:none}
  .che .ofd summary::after{content:'▾';position:absolute;right:8px;top:50%;transform:translateY(-50%);color:var(--muted)}
  .che .ofd summary.none{color:var(--red,#b91c1c);border-color:var(--red,#dc2626)}
  .che .ofd .ofbox{position:absolute;z-index:30;top:calc(100% + 4px);left:0;min-width:300px;max-width:min(440px,80vw);max-height:280px;overflow:auto;
    background:var(--card,#fff);border:1px solid var(--line);border-radius:10px;box-shadow:0 12px 32px rgba(15,23,42,.18);padding:10px 12px;
    display:grid;grid-template-columns:1fr 1fr;gap:6px 14px;font-size:12.5px}
  .che .ofbox .ofh{grid-column:1 / -1;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.3px}
  .che .ofbox label{display:flex;gap:6px;align-items:flex-start;line-height:1.3;cursor:pointer}
  .che .ofbox input{width:auto;margin-top:2px;flex:0 0 auto}
  .che .ofbox .ofdone{grid-column:1 / -1;justify-self:end;margin-top:4px}
  .che .lim{flex:1 1 100%;font-size:11.5px;color:var(--muted)}
  .che .res{text-align:right;font-variant-numeric:tabular-nums;font-weight:700;font-size:13.5px;padding-top:6px}
  .che .why{display:block;font-weight:400;font-size:11px;color:var(--muted);margin-top:2px;text-align:left}
  .che .ch-row.err .why{color:var(--red,#b91c1c)}
  .che details.more{grid-column:2 / -2;font-size:12px}
  .che details.more summary{cursor:pointer;color:var(--muted)}
  .che details.more .mg{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:8px;margin-top:6px}
  .che details.more .mg label{display:flex;flex-direction:column;gap:3px;font-size:11px;font-weight:600;color:var(--muted)}
  .che details.more .mg .wide{grid-column:1 / -1}
  .che .note-chip{grid-column:2 / -2;font-size:12px;color:var(--muted);margin-top:-2px}
  .che .ctcq{margin-top:14px;border:1px solid var(--line);border-left:3px solid var(--brand,#0f5f5a);border-radius:10px;padding:12px 14px;background:var(--soft)}
  .che .ctcq-t{font-size:13.5px;display:flex;flex-wrap:wrap;gap:4px 10px;align-items:baseline}
  .che .ctcq-t .muted{font-size:12.5px}
  .che .ctcq-f{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-top:8px}
  .che .ctcq-f label{display:flex;flex-direction:column;gap:3px;font-size:11.5px;font-weight:600;color:var(--muted);flex:1 1 180px;max-width:260px}
  .che .ctcq-f .btn{flex:0 0 auto}
  .che .ctcq-n{font-size:12px;margin-top:8px}
  .che .ctcq-n.bad{color:var(--red,#b91c1c)}
  .che .ctcq-n.ok{color:var(--ok,#0f7d5a)}
  .che .addbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:8px 0}
  .che .addbar select{width:auto;min-width:240px;max-width:100%}
  .che .totals{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:8px;background:var(--soft);border:1px solid var(--line);border-radius:10px;padding:10px 12px;margin-top:10px}
  .che .totals div{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.3px;font-weight:700}
  .che .totals b{display:block;font-size:16px;color:var(--ink);text-transform:none;letter-spacing:0;font-variant-numeric:tabular-nums}
  .che .errs{display:none;margin:8px 0}
  .che .empty{border:1px dashed var(--line);border-radius:10px;padding:16px;text-align:center;color:var(--muted);font-size:13px}
  @media(max-width:1180px){
    .che .ch-head{display:none}
    .che .ch-row{grid-template-columns:1fr 1fr;padding:10px}
    .che .ch-row > .mv{grid-column:1;flex-direction:row;order:9}
    .che .ch-row > .mv button{flex:1;padding:8px 0}
    .che .ch-row > .nm,.che .ch-row > .vl{grid-column:1 / -1}
    .che .ch-row > .res{grid-column:1 / -1;text-align:left;border-top:1px solid var(--line);padding-top:8px}
    .che .ch-row > .rm{grid-column:2;order:10}
    .che details.more,.che .note-chip{grid-column:1 / -1}
    .che details.more .mg{grid-template-columns:1fr 1fr}
    .che .ofd{flex:1 1 100%}
    .che .ofd .ofbox{position:static;min-width:0;max-width:none;width:auto;box-shadow:none;margin-top:6px;grid-template-columns:1fr}
  }
</style>
<div class="panel" id="add-role" style="scroll-margin-top:70px">
  <h3 class="tab-sub" style="margin-top:0"><?= $editLn ? '✎ Edit role — ' . $e($editLn['role_label']) : '＋ Add a role' ?>
    <?php if ($editLn): ?><a href="/project-costing?id=<?= (int)$h['id'] ?>#add-role" class="muted" style="font-size:12.5px;font-weight:400;margin-left:8px">Cancel — add a new role instead</a><?php endif; ?></h3>
  <form method="post" action="/project-costing-line-save" id="lineForm" class="che">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="costing_id" value="<?= (int)$h['id'] ?>">
    <input type="hidden" name="line_id" value="<?= (int)$editId ?>"><input type="hidden" name="items_json" id="ch_items_json" value="">
    <div class="form-grid">
      <div class="ff"><label for="ch_grp">Group <span class="muted">optional</span></label><input class="form-control" id="ch_grp" name="group_label" value="<?= $fv('group_label') ?>" placeholder="e.g. Module / Cell"></div>
      <div class="ff ff-wide"><label for="ch_role">Role</label><input class="form-control" id="ch_role" name="role_label" required value="<?= $fv('role_label') ?>" placeholder="e.g. Shift Engineer"></div>
      <div class="ff"><label for="ch_ppl">People <span class="muted">for this role</span></label><input class="form-control" id="ch_ppl" type="number" min="1" step="1" name="headcount" value="<?= $fv('headcount') ?: 1 ?>"></div>
      <div class="ff"><label for="ch_dpm">Working days in a month</label><input class="form-control" id="ch_dpm" type="number" min="1" max="31" step="0.5" name="dpm" value="<?= $fv('dpm') ?: $e($dpmDefault) ?>" title="Turns per-day heads into a month, and monthly heads into a day rate"></div>
    </div>

    <?php // Owner, 13 Nov 2026: "we must enter CTC, margin shall be entered and salary structure shall be
          // generated accordingly." Built from the company's own salary components (Compensation setup).
          $ctcOn = function_exists('comp_defs') && function_exists('pc_heads_from_ctc') && comp_defs(true);
          if ($ctcOn): ?>
    <div class="ctcq" id="ch_ctcq">
      <div class="ctcq-t"><b>Quick start — from the CTC</b> <span class="muted">Type what one person costs the company a month and the margin you want; the salary break-up and the rate are worked out for you.</span></div>
      <div class="ctcq-f">
        <label>CTC per person, a month (<?= $e($sym) ?>)<input id="ch_ctc" type="number" min="0" step="any" inputmode="decimal" placeholder="e.g. 45000"></label>
        <label>Margin you want (%)<input id="ch_ctc_tm" type="number" min="0" max="99.9" step="0.1" inputmode="decimal" placeholder="e.g. 22" value="<?= $num0('target_margin') ?>"></label>
        <button type="button" class="btn" id="ch_ctc_go">Build the salary break-up</button>
      </div>
      <div class="ctcq-n muted" id="ch_ctc_note">Uses your company's salary components — Basic is <?= $e(rtrim(rtrim(number_format(comp_basic_pct(), 2), '0'), '.')) ?>% of the CTC<?= (function_exists('hiring_admin_can') && hiring_admin_can()) ? ' (<a href="/comp-setup">change the components</a>)' : '' ?>. You can still change any head afterwards.</div>
    </div>
    <?php endif; ?>

    <p class="sub" style="margin:14px 0 6px">Cost heads — <b><?= $e($sym) ?> <?= $e($unitWord) ?></b>. Each head can be an amount, a % of other heads, quantity × rate or a formula, entered per month, per day, per year or one time. Names can be changed freely — a % keeps pointing at the right head.</p>
    <div class="addbar">
      <?php if ($tplJs): ?><select id="ch_tpl" aria-label="Start from a template"><option value="">Start from a template…</option><?php foreach ($tplJs as $t): ?><option value="<?= (int)$t['id'] ?>"><?= $e($t['name']) ?> (<?= count($t['items']) ?> heads)</option><?php endforeach; ?></select><?php endif; ?>
      <select id="ch_lib" aria-label="Add a head from the library"><option value="">＋ Add a head from the library…</option>
        <?php foreach (CH_GROUPS as $gk => $gl): $opts = array_filter($libJs, fn($x) => $x['grp'] === $gk); if (!$opts) continue; ?>
          <optgroup label="<?= $e($gl) ?>"><?php foreach ($opts as $x): ?><option value="<?= $e($x['code']) ?>"><?= $e($x['label']) ?><?= $x['type'] === 'PCT' ? ' — ' . $e(rtrim(rtrim(number_format((float)$x['pct'], 3), '0'), '.')) . '%' : '' ?></option><?php endforeach; ?></optgroup>
        <?php endforeach; ?></select>
      <button type="button" class="btn secondary" id="ch_blank">＋ Blank head</button>
      <?php if ($chManage): ?><a href="/project-costing-heads" class="muted" style="font-size:12.5px">Manage the library &amp; templates →</a><?php endif; ?>
    </div>
    <div class="ch-head" aria-hidden="true"><span></span><span>Head</span><span>Group</span><span>How</span><span>Value</span><span>Entered</span><span style="text-align:right"><?= $e($unitWord) ?></span><span></span></div>
    <div id="ch_rows"></div>
    <div class="empty" id="ch_empty">No heads yet — <?= $tplJs ? 'start from a template, ' : '' ?>add heads from the library, or a blank head.</div>
    <noscript><div class="msg msg-warning">The heads editor needs JavaScript switched on in your browser.</div></noscript>
    <div class="msg msg-error errs" id="ch_errs" role="alert"></div>

    <div class="form-grid" style="margin-top:12px">
      <div class="ff"><label for="pc_boq"><?= $e($qtyLabel) ?> (BOQ) <span class="muted">all people together</span></label><input class="form-control" type="number" step="0.01" min="0" id="pc_boq" name="boq_qty" value="<?= $fv('boq_qty') ?>"></div>
      <div class="ff"><label for="pc_tm">Target margin (%) <span class="muted">sets the rate</span></label><input class="form-control" type="number" step="0.1" min="0" max="99.9" id="pc_tm" name="target_margin" value="<?= $num0('target_margin') ?>" placeholder="e.g. 22"></div>
      <div class="ff"><label for="pc_rate">…or fixed rate<?= $e($rateLabel) ?> <span class="muted">override</span></label><input class="form-control" type="number" step="0.01" min="0" id="pc_rate" name="rate_manual" value="<?= $num0('rate_manual') ?>" placeholder="overrides margin"></div>
    </div>
    <div class="totals" aria-live="polite">
      <div>Salary<b id="pv_pay">—</b></div><div>Direct cost<b id="pv_direct">—</b></div><div>Loaded cost<b id="pv_loaded">—</b></div>
      <div>One-time / person<b id="pv_once">—</b></div><div>Rate<?= $e($rateLabel) ?><b id="pv_rate">—</b></div><div>Revenue<b id="pv_rev">—</b></div><div>Margin<b id="pv_marg">—</b></div>
    </div>
    <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <button class="btn" type="submit"><?= $editLn ? 'Save role' : 'Add role' ?></button>
      <?php if ($editLn): ?><a class="btn btn-ghost" href="/project-costing?id=<?= (int)$h['id'] ?>">Cancel</a><?php endif; ?>
    </div>
  </form>
  <?php if ($chManage): ?>
  <form method="post" action="/project-costing-heads" id="ch_tplform" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;font-size:12.5px">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="do" value="template_from_role"><input type="hidden" name="items_json" value="">
    <input type="hidden" name="back" value="/project-costing?id=<?= (int)$h['id'] ?><?= $editId ? '&edit=' . (int)$editId : '' ?>#add-role">
    <span class="muted">Use these heads again:</span>
    <input name="name" maxlength="120" required placeholder="Template name, e.g. Security guard — monthly" style="padding:6px 8px;border:1px solid var(--field-line,#cbd5e1);border-radius:7px;min-width:260px">
    <button class="btn btn-ghost" style="padding:5px 12px">Save as a template</button>
  </form>
  <?php endif; ?>
</div>
<script>(function(){
  var f=document.getElementById('lineForm'); if(!f) return;
  var SYM=<?= json_encode($sym) ?>, LIB=<?= json_encode(array_values($libJs), JSON_UNESCAPED_UNICODE) ?>, TPL=<?= json_encode($tplJs, JSON_UNESCAPED_UNICODE) ?>;
  var GROUPS={PAY:'Salary',STAT:'Statutory',COST:'Other cost'}, TYPES=<?= json_encode(CH_TYPES, JSON_UNESCAPED_UNICODE) ?>, PER=<?= json_encode(CH_PER, JSON_UNESCAPED_UNICODE) ?>;
  var FUNCS=<?= json_encode(CH_FUNCS) ?>;
  var rowsEl=document.getElementById('ch_rows'), empty=document.getElementById('ch_empty'), errs=document.getElementById('ch_errs');
  var rows=<?= json_encode(array_values($initItems), JSON_UNESCAPED_UNICODE) ?>;
  function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
  function money(v){ v=+v||0; return SYM+' '+v.toLocaleString('en-IN',{maximumFractionDigits:2}); }
  function codeFrom(label,taken){
    var c=String(label||'').toUpperCase().replace(/[^A-Z0-9]+/g,'_').replace(/^_+|_+$/g,'');
    if(!c||/^[0-9]/.test(c)) c='H_'+c; c=c.slice(0,24); if(FUNCS.indexOf(c)>=0) c+='_H';
    var base=c,k=2; while(taken.indexOf(c)>=0){ c=base.slice(0,21)+'_'+(k++); } return c;
  }
  function codes(except){ return rows.filter(function(r,i){return i!==except;}).map(function(r){return r.code;}); }
  function opt(map,sel,only){ return Object.keys(map).filter(function(k){return !only||only.indexOf(k)>=0;}).map(function(k){return '<option value="'+k+'"'+(k===sel?' selected':'')+'>'+esc(map[k])+'</option>';}).join(''); }
  function num(v){ return (v===undefined||v===null||v===0||v==='0')?'':esc(v); }
  function render(){
    rowsEl.innerHTML='';
    empty.style.display=rows.length?'none':'block';
    // The short names are what a formula uses; with no formula on the role they are only noise.
    var hasFormula=rows.some(function(x){ return x.type==='FORMULA'; });
    rows.forEach(function(r,i){
      var t=r.type||'AMOUNT', pers=(t==='PCT'||t==='FORMULA')?['MONTH','ONCE']:null;
      var d=document.createElement('div'); d.className='ch-row'; d.dataset.i=i; d.dataset.code=r.code;
      var of=(r.of||[]);
      var nameOf=function(c){ var o=rows.filter(function(x){return x.code===c;})[0]; return o?(String(o.label||'').trim()||'Unnamed head'):c; };
      var ofHtml=rows.map(function(o,j){ if(j===i) return ''; return '<label><input type="checkbox" class="f-of" value="'+esc(o.code)+'"'+(of.indexOf(o.code)>=0?' checked':'')+'> <span>'+esc(String(o.label||'').trim()||'Unnamed head')+'</span></label>'; }).join('');
      var lim=[]; if(+r.cap) lim.push('counted up to '+money(r.cap)); if(+r.upto) lim.push('applies only up to '+money(r.upto));
      var val='';
      if(t==='AMOUNT') val='<input class="f-calc" inputmode="decimal" value="'+esc(r.calc!=null?r.calc:(r.amount||''))+'" placeholder="e.g. 18000 or 1500*2+300" aria-label="Amount">';
      if(t==='QTYRATE') val='<input class="f-qty" type="number" step="any" value="'+num(r.qty)+'" placeholder="Qty" aria-label="Quantity"><span class="w">×</span><input class="f-rate" type="number" step="any" value="'+num(r.rate)+'" placeholder="Rate" aria-label="Rate">';
      if(t==='PCT') val='<input class="f-pct" type="number" step="any" value="'+num(r.pct)+'" placeholder="%" aria-label="Percent" style="flex:0 0 70px"><span class="w">% of</span>'
        +'<details class="ofd"><summary class="'+(of.length?'':'none')+'" title="Which heads it is a % of">'+(of.length?esc(of.map(nameOf).join(' + ')):'choose the heads')+'</summary>'
        +'<div class="ofbox" role="group" aria-label="Which heads it is a % of"><div class="ofh">A % of these heads</div>'+(ofHtml||'<span class="muted">Add the heads it is a % of first.</span>')
        +'<button type="button" class="btn small ofdone">Done</button></div></details>'
        +(lim.length?'<div class="lim">'+esc(lim.join(' · '))+'</div>':'');
      if(t==='FORMULA') val='<input class="f-expr" value="'+esc(r.expr||'')+'" placeholder="e.g. MIN(BASIC,15000)*12%" aria-label="Formula" style="flex:1 1 100%"><span class="w" style="font-size:11px">Use the short names: '+esc(codes(i).join(', ')||'—')+' · MIN MAX ROUND IF</span>';
      d.innerHTML='<div class="mv"><button type="button" class="up" title="Move up" aria-label="Move up">▲</button><button type="button" class="dn" title="Move down" aria-label="Move down">▼</button></div>'
        +'<div class="nm"><input class="f-label" value="'+esc(r.label||'')+'" placeholder="Name of the head" aria-label="Head name" title="'+esc(r.label||'')+'">'
        +((hasFormula||r.lib_from)?'<div class="code">'+(hasFormula?'short name '+esc(r.code):'')+(r.lib_from?(hasFormula?' · ':'')+'library value from '+esc(r.lib_from):'')+'</div>':'')+'</div>'
        +'<div><select class="f-grp" aria-label="Group">'+opt(GROUPS,r.grp||'COST')+'</select></div>'
        +'<div><select class="f-type" aria-label="How it is worked out">'+opt(TYPES,t)+'</select></div>'
        +'<div class="val vl">'+val+'</div>'
        +'<div><select class="f-per" aria-label="Entered per">'+opt(PER,r.per||'MONTH',pers)+'</select></div>'
        +'<div class="res"><span class="r-val">—</span><span class="why r-why"></span></div>'
        +'<button type="button" class="rm" title="Remove this head" aria-label="Remove this head">✕</button>'
        +'<details class="more"><summary>'+(t==='PCT'?'＋ Limits, minimum / maximum, remark':'＋ Minimum / maximum, remark')+'</summary><div class="mg">'
        +(t==='PCT'?'<label>Counted up to<input class="f-cap" type="number" step="any" min="0" value="'+num(r.cap)+'" placeholder="no limit" title="Only this much of the base counts — e.g. PF on wages up to 15,000"></label>'
                   +'<label>Applies only up to<input class="f-upto" type="number" step="any" min="0" value="'+num(r.upto)+'" placeholder="always" title="Not applied at all when the base is above this — e.g. ESIC up to 21,000"></label>':'')
        +'<label>Minimum<input class="f-min" type="number" step="any" value="'+(r.min!=null?esc(r.min):'')+'" placeholder="none"></label><label>Maximum<input class="f-max" type="number" step="any" value="'+(r.max!=null?esc(r.max):'')+'" placeholder="none"></label>'
        +'<label class="wide">Remark<input class="f-note" maxlength="300" value="'+esc(r.note||'')+'" placeholder="e.g. as per client tender clause 4.2"></label></div></details>'
        +(r.note?'<div class="note-chip">💬 '+esc(r.note)+'</div>':'');
      rowsEl.appendChild(d);
    });
    schedule();
  }
  function read(){
    rowsEl.querySelectorAll('.ch-row').forEach(function(d){
      var r=rows[+d.dataset.i]; if(!r) return; var q=function(c){var el=d.querySelector(c); return el?el.value:undefined;};
      r.label=q('.f-label'); r.grp=q('.f-grp'); r.type=q('.f-type'); r.per=q('.f-per'); r.note=q('.f-note');
      var mn=q('.f-min'), mx=q('.f-max'); r.min=mn===''?null:mn; r.max=mx===''?null:mx;
      if(r.min===null) delete r.min; if(r.max===null) delete r.max;
      if(d.querySelector('.f-calc')) r.calc=q('.f-calc');
      if(d.querySelector('.f-qty')){ r.qty=q('.f-qty'); r.rate=q('.f-rate'); }
      if(d.querySelector('.f-pct')){ r.pct=q('.f-pct'); r.cap=q('.f-cap'); r.upto=q('.f-upto'); r.of=[].map.call(d.querySelectorAll('.f-of:checked'),function(x){return x.value;}); }
      if(d.querySelector('.f-expr')) r.expr=q('.f-expr');
    });
  }
  function add(item){
    read(); item=JSON.parse(JSON.stringify(item));
    var taken=codes(-1);
    if(item.code && taken.indexOf(item.code)>=0){ alert('"'+(item.label||item.code)+'" is already on this role.'); return; }
    if(!item.code) item.code=codeFrom(item.label||'HEAD',taken);
    if(item.amount!=null && item.calc==null) item.calc=item.amount?String(item.amount):'';
    rows.push(item); render();
    var last=rowsEl.lastElementChild; if(last){ var inp=last.querySelector('.f-calc,.f-pct,.f-qty,.f-expr,.f-label'); if(inp) inp.focus(); }
  }
  rowsEl.addEventListener('click',function(ev){
    var d=ev.target.closest('.ch-row'); if(!d) return; var i=+d.dataset.i;
    if(ev.target.classList.contains('rm')){
      read(); var code=rows[i].code, users=rows.filter(function(r){ return (r.of||[]).indexOf(code)>=0 || (r.type==='FORMULA' && new RegExp('\\b'+code+'\\b','i').test(r.expr||'')); });
      if(users.length && !confirm('"'+rows[i].label+'" is used by '+users.map(function(u){return u.label;}).join(', ')+'. Remove it anyway?')) return;
      rows.splice(i,1); render(); }
    if(ev.target.classList.contains('up') && i>0){ read(); rows.splice(i-1,0,rows.splice(i,1)[0]); render(); }
    if(ev.target.classList.contains('dn') && i<rows.length-1){ read(); rows.splice(i+1,0,rows.splice(i,1)[0]); render(); }
  });
  rowsEl.addEventListener('change',function(ev){
    if(ev.target.classList.contains('f-type')){ read(); var r=rows[+ev.target.closest('.ch-row').dataset.i]; if((r.type==='PCT'||r.type==='FORMULA')&&r.per!=='ONCE') r.per='MONTH'; render(); return; }
    if(ev.target.classList.contains('f-label')){
      read(); var ri=+ev.target.closest('.ch-row').dataset.i, rr=rows[ri];
      // A blank head was given the stand-in short name HEAD / HEAD_2 …; once it has a name it takes one
      // from that name — unless a % or a formula already points at it (it keeps working either way).
      if(rr && /^HEAD(_\d+)?$/.test(rr.code||'') && String(rr.label||'').trim()!==''){
        var used=rows.some(function(x,j){ return j!==ri && ((x.of||[]).indexOf(rr.code)>=0 || (x.type==='FORMULA' && new RegExp('\\b'+rr.code+'\\b','i').test(x.expr||''))); });
        if(!used) rr.code=codeFrom(rr.label, codes(ri));
      }
      render(); return; }   // the % / formula pickers show the new name
    read(); schedule();
  });
  rowsEl.addEventListener('input',function(ev){ if(!ev.target.classList.contains('f-label')){ read(); schedule(); } });
  //  The "% of" picker: its button says what is ticked the moment it is ticked;
  //  Done, a click elsewhere or Esc closes it; only one is open at a time.
  function ofSummary(dd){
    var sm=dd.querySelector('summary'); var names=[].map.call(dd.querySelectorAll('.f-of:checked'),function(x){ return x.parentNode.textContent.trim(); });
    sm.textContent=names.length?names.join(' + '):'choose the heads'; sm.classList.toggle('none',!names.length);
  }
  rowsEl.addEventListener('change',function(ev){ if(ev.target.classList.contains('f-of')) ofSummary(ev.target.closest('.ofd')); });
  rowsEl.addEventListener('click',function(ev){ if(ev.target.classList.contains('ofdone')){ var dd=ev.target.closest('.ofd'); dd.open=false; dd.querySelector('summary').focus(); } });
  rowsEl.addEventListener('toggle',function(ev){ if(ev.target.classList&&ev.target.classList.contains('ofd')&&ev.target.open) rowsEl.querySelectorAll('.ofd[open]').forEach(function(o){ if(o!==ev.target) o.open=false; }); },true);
  document.addEventListener('click',function(ev){ rowsEl.querySelectorAll('.ofd[open]').forEach(function(o){ if(!o.contains(ev.target)) o.open=false; }); });
  document.addEventListener('keydown',function(ev){ if(ev.key==='Escape') rowsEl.querySelectorAll('.ofd[open]').forEach(function(o){ o.open=false; }); });
  document.getElementById('ch_blank').addEventListener('click',function(){ add({label:'',type:'AMOUNT',per:'<?= $basis === 'DAILY' ? 'DAY' : 'MONTH' ?>',grp:'COST'}); });
  document.getElementById('ch_lib').addEventListener('change',function(){ var c=this.value; this.value=''; if(!c) return; for(var k=0;k<LIB.length;k++) if(LIB[k].code===c){ add(LIB[k]); break; } });
  var tpl=document.getElementById('ch_tpl'); if(tpl) tpl.addEventListener('change',function(){
    var id=+this.value; this.value=''; if(!id) return; var t=TPL.filter(function(x){return x.id===id;})[0]; if(!t) return;
    read(); if(rows.length && !confirm('Replace the heads on this role with the template "'+t.name+'"?')) return;
    rows=JSON.parse(JSON.stringify(t.items)); rows.forEach(function(r){ if(r.amount!=null && r.calc==null) r.calc=r.amount?String(r.amount):''; }); render(); });
  ['ch_dpm','pc_boq','pc_tm','pc_rate','ch_ppl'].forEach(function(id){ var el=document.getElementById(id); if(el) el.addEventListener('input',schedule); });
  var timer=null, seq=0;
  function schedule(){ clearTimeout(timer); timer=setTimeout(preview,280); }
  function preview(){
    read(); document.getElementById('ch_items_json').value=JSON.stringify(rows);
    var fd=new FormData(f); fd.delete('_ft');   /* the form's one-shot ticket is for the real save */
    var my=++seq; fd.set('role_label', fd.get('role_label')||'·');
    fetch('/project-costing-line-preview',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
      if(my!==seq) return;
      rowsEl.querySelectorAll('.ch-row').forEach(function(d){
        var r=(j.rows||{})[d.dataset.code]; var v=d.querySelector('.r-val'), w=d.querySelector('.r-why');
        if(!r){ v.textContent='—'; w.textContent=''; d.classList.remove('err'); return; }
        v.textContent=r.once?money(r.once)+' once':money(r.unit); w.textContent=r.why||''; d.classList.toggle('err',!!r.error);
      });
      var shown=(j.errors||[]).filter(function(m){ return m!=='Give the role a name.'; });
      errs.style.display=shown.length?'block':'none'; errs.innerHTML=shown.map(esc).join('<br>');
      var set=function(id,v){ document.getElementById(id).textContent=v; };
      set('pv_pay',j.pay?money(j.pay):'—'); set('pv_direct',j.direct?money(j.direct):'—'); set('pv_loaded',j.loaded?money(j.loaded):'—');
      set('pv_once',j.once?money(j.once):'—'); set('pv_rate',j.rate?money(j.rate):'—'); set('pv_rev',j.revenue?money(j.revenue):'—');
      set('pv_marg',j.revenue?(+j.margin).toFixed(1)+'%':'—');
    }).catch(function(){});
  }
  //  EITHER BUTTON SAVES THE WHOLE PAGE (owner, 13 Nov 2026: "It said costing is
  //  saved but then it shows zero"). "Save settings" used to reload the page and
  //  throw away the role being typed; "Add role" did the same to changed settings.
  function carry(from,to){ new FormData(from).forEach(function(v,k){
    if(k==='_csrf'||k==='_ft'||typeof v!=='string'||to.querySelector('[name="'+k+'"]')) return;
    var i=document.createElement('input'); i.type='hidden'; i.name=k; i.value=v; to.appendChild(i); }); }
  var settingsDirty=false;
  ['input','change'].forEach(function(t){ document.addEventListener(t,function(ev){ if(ev.target.closest && ev.target.closest('#pcSettings')) settingsDirty=true; }); });
  f.addEventListener('submit',function(){
    read(); document.getElementById('ch_items_json').value=JSON.stringify(rows);
    var sf=document.getElementById('pcSettings');
    if(sf && settingsDirty){ carry(sf,f); var w=document.createElement('input'); w.type='hidden'; w.name='with_settings'; w.value='1'; f.appendChild(w); }
  });
  document.addEventListener('submit',function(ev){
    // getAttribute, not .id: this form has a field NAMED "id", which hides the form's own id.
    if(!ev.target || !ev.target.getAttribute || ev.target.getAttribute('id')!=='pcSettings') return;
    read(); var nm=(document.getElementById('ch_role').value||'').trim();
    if(!nm && !rows.length) return;                      // nothing typed in the role box
    document.getElementById('ch_items_json').value=JSON.stringify(rows);
    carry(f,ev.target);
  });
  //  QUICK START — a salary break-up from the CTC, with the company's own components.
  var cq=document.getElementById('ch_ctc_go');
  if(cq){
    var cNote=document.getElementById('ch_ctc_note'), cTm=document.getElementById('ch_ctc_tm'), pTm=document.getElementById('pc_tm');
    cTm.addEventListener('input',function(){ pTm.value=cTm.value; schedule(); });
    pTm.addEventListener('input',function(){ cTm.value=pTm.value; });
    var build=function(){
      var v=parseFloat(document.getElementById('ch_ctc').value);
      if(!(v>0)){ cNote.className='ctcq-n bad'; cNote.textContent='Type the CTC first — what one person costs the company a month.'; document.getElementById('ch_ctc').focus(); return; }
      read(); if(rows.length && !confirm('Replace the heads on this role with the salary break-up for this CTC?')) return;
      var fd=new FormData(); fd.set('_csrf',f.querySelector('[name=_csrf]').value); fd.set('costing_id',f.querySelector('[name=costing_id]').value); fd.set('ctc',v);
      cq.disabled=true;
      fetch('/project-costing-ctc-heads',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
        cq.disabled=false;
        if(!j.ok){ cNote.className='ctcq-n bad'; cNote.textContent=j.err||'It could not be worked out.'; return; }
        rows=j.items; rows.forEach(function(r){ if(r.amount!=null && r.calc==null) r.calc=r.amount?String(r.amount):''; });
        if(cTm.value!=='') pTm.value=cTm.value;
        cNote.className='ctcq-n ok'; cNote.textContent='✓ '+j.note+(cTm.value===''?' Now type the margin you want — the rate follows.':'');
        render();
        var nx=document.getElementById(cTm.value===''?'ch_ctc_tm':(document.getElementById('ch_role').value.trim()===''?'ch_role':'pc_boq')); if(nx) nx.focus();
      }).catch(function(){ cq.disabled=false; cNote.className='ctcq-n bad'; cNote.textContent='It could not be worked out just now — try again.'; });
    };
    cq.addEventListener('click',build);
    document.getElementById('ch_ctc').addEventListener('keydown',function(ev){ if(ev.key==='Enter'){ ev.preventDefault(); build(); } });
    cTm.addEventListener('keydown',function(ev){ if(ev.key==='Enter'){ ev.preventDefault(); build(); } });
  }
  var tf=document.getElementById('ch_tplform'); if(tf) tf.addEventListener('submit',function(ev){ read(); if(!rows.length){ ev.preventDefault(); alert('Add some heads first.'); return; } tf.querySelector('[name=items_json]').value=JSON.stringify(rows); });
  render();
  <?php if ($editLn || $draft): ?>document.getElementById('add-role').scrollIntoView();<?php endif; ?>
})();</script>
<?php endif; ?>

<!-- Settings + notes + approval -->
<div class="g2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start">
  <div class="panel">
    <h3 class="tab-sub" style="margin-top:0">Loadings, basis &amp; details</h3>
    <?php if ($canEdit): ?>
    <form method="post" action="/project-costing-save" id="pcSettings">
      <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
      <div class="form-grid">
        <div class="ff ff-wide"><label>Title</label><input class="form-control" name="title" value="<?= $e($h['title']) ?>"></div>
        <div class="ff"><label>Client</label><select class="form-control" name="client_id"><option value="">—</option>
          <?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$h['client_id']===(int)$c['id']?'selected':'' ?>><?= $e($c['display_name'] ?? $c['legal_name'] ?? ('#'.$c['id'])) ?></option><?php endforeach; ?></select></div>
        <div class="ff"><label>Office</label><select class="form-control" name="office_id"><option value="">—</option>
          <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (int)$h['office_id']===(int)$o['id']?'selected':'' ?>><?= $e($o['name']) ?></option><?php endforeach; ?></select></div>
        <div class="ff"><label>Site</label><input class="form-control" name="site" value="<?= $e($h['site']) ?>"></div>
        <div class="ff"><label>Business unit <span class="muted">— decides which SBU Head approves</span></label><select class="form-control" name="sbu"><option value="">—</option>
          <?php foreach ($sbuOpts as $k=>$v): ?><option value="<?= $e($k) ?>" <?= (string)($h['sbu'] ?? '')===(string)$k?'selected':'' ?>><?= $e($v) ?></option><?php endforeach; ?></select></div>
        <div class="ff"><label>Basis</label><select class="form-control" name="basis"><?php foreach (PC_BASES as $k=>$v): ?><option value="<?= $e($k) ?>" <?= $basis===$k?'selected':'' ?>><?= $e($v) ?></option><?php endforeach; ?></select></div>
        <div class="ff"><label>Loadings applied on</label><select class="form-control" name="load_on"><option value="COST" <?= strtoupper((string)$h['load_on'])!=='RATE'?'selected':'' ?>>Direct cost (standard)</option><option value="RATE" <?= strtoupper((string)$h['load_on'])==='RATE'?'selected':'' ?>>Sell rate (Excel-style)</option></select></div>
        <div class="ff"><label>Overhead %</label><input class="form-control" type="number" step="0.01" name="overhead_pct" value="<?= $e($h['overhead_pct']) ?>"></div>
        <div class="ff"><label>Insurance %</label><input class="form-control" type="number" step="0.01" name="insurance_pct" value="<?= $e($h['insurance_pct']) ?>"></div>
        <div class="ff"><label>Bad debt %</label><input class="form-control" type="number" step="0.01" name="baddebt_pct" value="<?= $e($h['baddebt_pct']) ?>"></div>
        <div class="ff"><label>Contingency %</label><input class="form-control" type="number" step="0.01" name="contingency_pct" value="<?= $e($h['contingency_pct']) ?>"></div>
        <div class="ff"><label>Negotiation %</label><input class="form-control" type="number" step="0.01" name="negotiation_pct" value="<?= $e($h['negotiation_pct']) ?>"></div>
      </div>
      <div class="form-grid" style="margin-top:6px">
        <div class="ff ff-wide"><label>Inclusions</label><textarea class="form-control" name="inclusions" rows="2"><?= $e($h['inclusions']) ?></textarea></div>
        <div class="ff ff-wide"><label>Exclusions <span class="muted">— "Not included…"</span></label><textarea class="form-control" name="exclusions" rows="2"><?= $e($h['exclusions']) ?></textarea></div>
        <div class="ff ff-wide"><label>Notes</label><input class="form-control" name="notes" value="<?= $e($h['notes']) ?>"></div>
      </div>
      <div style="margin-top:8px"><button class="btn" type="submit">Save settings</button>
        <span class="muted" style="font-size:12px;margin-left:8px">Also saves a role you are typing above — nothing on this page is lost.</span></div>
    </form>
    <?php else: ?>
      <div class="kv-grid" style="font-size:13px">
        <div><span class="muted">Loadings</span> OH <?= $e($h['overhead_pct']) ?>% · Ins <?= $e($h['insurance_pct']) ?>% · BD <?= $e($h['baddebt_pct']) ?>% · Cont <?= $e($h['contingency_pct']) ?>% · Neg <?= $e($h['negotiation_pct']) ?>%</div>
        <?php if (!empty($h['inclusions'])): ?><div style="margin-top:6px"><span class="muted">Inclusions:</span> <?= nl2br($e($h['inclusions'])) ?></div><?php endif; ?>
        <?php if (!empty($h['exclusions'])): ?><div style="margin-top:6px"><span class="muted">Exclusions:</span> <?= nl2br($e($h['exclusions'])) ?></div><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h3 class="tab-sub" style="margin-top:0">Approval</h3>
    <div class="pc-actions">
      <?php if (in_array($h['status'],['DRAFT','REJECTED'],true) && function_exists('pc_can_edit') && pc_can_edit() && !empty($lines)): ?>
        <form method="post" action="/project-costing-submit"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><button class="btn primary">Submit for approval</button></form>
      <?php endif; ?>
      <?php if ($h['status']==='SUBMITTED' && $isAuthor): ?>
        <span class="muted" style="font-size:12.5px">You prepared this costing, so someone else approves it.</span>
      <?php elseif ($h['status']==='SUBMITTED' && $canApprove): ?>
        <form method="post" action="/project-costing-approve" style="display:flex;gap:6px;align-items:center"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><input class="form-control" name="approval_ref" placeholder="Approval ref (optional)" style="width:170px"><button class="btn primary">Approve</button></form>
        <form method="post" action="/project-costing-reject" style="display:flex;gap:6px;align-items:center"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><input class="form-control" name="reject_note" placeholder="Reason (needed)" required style="width:150px"><button class="btn btn-ghost">Send back</button></form>
      <?php endif; ?>
      <?php if (!in_array($h['status'],['DRAFT'],true) && function_exists('pc_can_edit') && pc_can_edit()): ?>
        <form method="post" action="/project-costing-reopen"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><button class="btn btn-ghost">Reopen as draft</button></form>
      <?php endif; ?>
      <?php if ($h['status']==='DRAFT' && empty($lines)): ?><span class="muted" style="font-size:12.5px">Add at least one role before submitting.</span><?php endif; ?>
    </div>
    <?php if ($pcSteps): ?>
      <ol style="margin:10px 0 0;padding-left:20px;font-size:13px;line-height:1.6">
        <?php foreach ($pcSteps as $s):
          $who = function_exists('pc_step_people') ? array_map('user_name', array_slice(pc_step_people($s, $h), 0, 3)) : [];
          $tag = $s['status'] === 'APPROVED' ? '✓ approved by ' . $s['acted_by'] : ($s['status'] === 'REJECTED' ? '↩ sent back by ' . $s['acted_by'] : (in_array((int)$s['level'], $pcOpen, true) ? 'waiting' : 'after the level before'));
          $name = !empty($s['approver_user_id']) ? '' : ((string)$s['approver_role'] !== '' ? (ORG_ROLES[$s['approver_role']] ?? $s['approver_role']) : 'anyone who may approve'); ?>
          <li><b>Level <?= (int)$s['level'] ?></b> — <?= $e($name !== '' ? $name . ($who ? ' (' . implode(', ', $who) . ')' : '') : implode(', ', $who)) ?> · <span class="<?= $s['status']==='APPROVED' ? '' : 'muted' ?>"><?= $e($tag) ?></span></li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
    <p class="sub" style="margin-top:6px">Draft → Submitted → Approved. Submitting sets the approval levels the same way as a quotation of this value and business unit (Set up Sales), and locks the sheet. Levels sign <?= function_exists('crm_approval_mode') && crm_approval_mode() === 'together' ? 'all at the same time' : 'in order' ?>; its author never signs. A quotation is made only from an approved costing, and reopening an approved one withdraws its approval.</p>
  </div>
</div>
</div>
