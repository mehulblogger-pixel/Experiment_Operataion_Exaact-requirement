<?php
// Compensation setup — configurable salary headings + statutory. Data: $defs.
// Gated is_admin_level() in the handler. CSRF auto-stamped.
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$defs = $defs ?? [];
$sections = ['EARNING' => 'Earning', 'DEDUCTION' => 'Deduction (employee)', 'EMPLOYER' => 'Employer contribution'];
$calcs = ['FIXED' => 'Fixed amount', 'PCT_BASIC' => '% of Basic', 'PCT_GROSS' => '% of Gross'];
$byType = ['EARNING' => [], 'DEDUCTION' => [], 'EMPLOYER' => []];
foreach ($defs as $d) $byType[$d['section']][] = $d;
?>
<div class="crumbs"><a href="/">Home</a> › Compensation setup</div>
<div class="master-head">
  <div><h1>Compensation setup</h1>
    <p class="sub" style="margin:2px 0 0">Define the salary headings your offers are built from — earnings, employee deductions and employer contributions — with their calculation rule and statutory flag. The salary structure computes CTC, net pay and employer cost from these.</p></div>
</div>

<?php foreach ($sections as $sk => $slabel): ?>
<div class="panel">
  <h3 class="tab-sub"><?= $e($slabel) ?></h3>
  <div style="overflow-x:auto">
  <table style="width:100%;border-collapse:collapse;min-width:720px">
    <tr><th style="text-align:left;font-size:10.5px;text-transform:uppercase;color:var(--muted,#656e7a);padding:6px 8px">Order</th><th style="text-align:left;font-size:10.5px;text-transform:uppercase;color:var(--muted,#656e7a);padding:6px 8px">Heading</th><th style="text-align:left;font-size:10.5px;text-transform:uppercase;color:var(--muted,#656e7a);padding:6px 8px">Code</th><th style="text-align:left;font-size:10.5px;text-transform:uppercase;color:var(--muted,#656e7a);padding:6px 8px">Calculation</th><th style="text-align:left;font-size:10.5px;text-transform:uppercase;color:var(--muted,#656e7a);padding:6px 8px">Rate %</th><th style="text-align:center;font-size:10.5px;text-transform:uppercase;color:var(--muted,#656e7a);padding:6px 8px">Statutory</th><th></th></tr>
    <?php foreach ($byType[$sk] as $d): ?>
    <tr style="border-top:1px solid var(--line,#eef1f5)<?= (int)$d['active']===0?';opacity:.5':'' ?>">
      <form method="post" style="display:contents"><input type="hidden" name="do" value="save"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="section" value="<?= $e($sk) ?>">
        <td style="padding:6px 8px"><input class="form-control" type="number" name="sort" value="<?= (int)$d['sort'] ?>" style="width:60px"></td>
        <td style="padding:6px 8px"><input class="form-control" name="name" value="<?= $e($d['name']) ?>" style="min-width:150px"></td>
        <td style="padding:6px 8px"><input class="form-control" name="code" value="<?= $e($d['code']) ?>" style="width:96px"></td>
        <td style="padding:6px 8px"><select class="form-control" name="calc"><?php foreach ($calcs as $ck=>$cl): ?><option value="<?= $ck ?>" <?= $d['calc']===$ck?'selected':'' ?>><?= $e($cl) ?></option><?php endforeach; ?></select></td>
        <td style="padding:6px 8px"><input class="form-control" type="number" step="any" name="rate" value="<?= rtrim(rtrim(number_format((float)$d['rate'],2,'.',''),'0'),'.') ?>" style="width:74px"></td>
        <td style="padding:6px 8px;text-align:center"><input type="checkbox" name="statutory" value="1" <?= (int)$d['statutory']?'checked':'' ?>></td>
        <td style="padding:6px 8px;white-space:nowrap"><button class="btn secondary" style="padding:4px 9px;font-size:12px">Save</button>
          <button class="btn secondary" style="padding:4px 9px;font-size:12px" name="do" value="toggle"><?= (int)$d['active']===1?'Off':'On' ?></button></td>
      </form>
    </tr>
    <?php endforeach; ?>
    <!-- add row -->
    <tr style="border-top:1px solid var(--line,#eef1f5);background:var(--soft,#f6f8fb)">
      <form method="post" style="display:contents"><input type="hidden" name="do" value="save"><input type="hidden" name="section" value="<?= $e($sk) ?>">
        <td style="padding:6px 8px"><input class="form-control" type="number" name="sort" placeholder="99" style="width:60px"></td>
        <td style="padding:6px 8px"><input class="form-control" name="name" placeholder="New heading" style="min-width:150px"></td>
        <td style="padding:6px 8px"><input class="form-control" name="code" placeholder="CODE" style="width:96px"></td>
        <td style="padding:6px 8px"><select class="form-control" name="calc"><?php foreach ($calcs as $ck=>$cl): ?><option value="<?= $ck ?>"><?= $e($cl) ?></option><?php endforeach; ?></select></td>
        <td style="padding:6px 8px"><input class="form-control" type="number" step="any" name="rate" placeholder="0" style="width:74px"></td>
        <td style="padding:6px 8px;text-align:center"><input type="checkbox" name="statutory" value="1"></td>
        <td style="padding:6px 8px"><button class="btn" style="padding:4px 11px;font-size:12px">Add</button></td>
      </form>
    </tr>
  </table>
  </div>
</div>
<?php endforeach; ?>
<p class="muted" style="margin-top:-6px">“% of Basic” and “% of Gross” compute automatically; “Fixed amount” is entered on each candidate's salary structure. CTC = gross earnings + employer contributions; Net = gross earnings − employee deductions.</p>
