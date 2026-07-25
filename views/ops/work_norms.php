<?php // Working norms: weekly days + hours per designation per office ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/masters">Masters</a> › Working norms</div>
<div class="master-head">
  <div><h1>Working norms</h1>
    <p class="sub" style="margin:2px 0 0">Standard weekly <strong>days</strong> and <strong>hours</strong> per designation and office. People inherit these unless overridden on their own record. Most-specific wins: a designation+office rule beats a designation-only rule, which beats an office default (blank designation), which beats the global default.</p></div>
</div>

<form method="post" action="/work-norms" class="panel">
  <h3 class="tab-sub" style="margin-top:0">Add / update a norm</h3>
  <div class="form-grid">
    <div class="ff"><label>Designation <span class="muted">(blank = office-wide default)</span></label>
      <select class="form-control searchable" name="designation"><option value="">— any / office default —</option>
        <?php foreach ($designations as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Office <span class="muted">(blank = all offices)</span></label>
      <select class="form-control searchable" name="office_id"><option value="">— all offices —</option>
        <?php foreach ($offices as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="ff"><label>Weekly working days</label>
      <select class="form-control" name="weekly_days"><?php foreach (['6'=>'6 days','5.5'=>'5.5 days','5'=>'5 days'] as $k=>$v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Weekly working hours</label><input class="form-control" type="number" step="0.5" min="1" max="84" name="weekly_hours" value="48"></div>
  </div>
  <div style="margin-top:12px"><button class="btn" type="submit">Save norm</button></div>
</form>

<div class="panel" style="padding:0;overflow:hidden;margin-top:14px">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Designation</th><th>Office</th><th class="num">Weekly days</th><th class="num">Weekly hours</th><th></th></tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="5" class="muted" style="padding:14px">No norms set yet — the global default of 6 days / 48 hours applies everywhere.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= $r['designation'] !== '' ? e($designations[$r['designation']] ?? $r['designation']) : '<span class="muted">(office default)</span>' ?></td>
        <td><?= $r['office_name'] ? e($r['office_name']) : '<span class="muted">All offices</span>' ?></td>
        <td class="num"><?= rtrim(rtrim(number_format((float)$r['weekly_days'],1),'0'),'.') ?></td>
        <td class="num"><?= rtrim(rtrim(number_format((float)$r['weekly_hours'],1),'0'),'.') ?></td>
        <td><form method="post" action="/work-norms" onsubmit="return confirm('Remove this norm?')" style="display:inline"><input type="hidden" name="_do" value="del"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn small secondary" type="submit">Remove</button></form></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
