<?php
// Phase 3 §8 — persona preview of a report template. Shows, section by section, what the report
// recipient sees versus what stays internal, and flags conditional / scored fields.
$type = $pv['type']; $c = $pv['counts'];
?>
<div class="crumbs"><a href="/report-types">Report types</a> › <a href="/report-builder?type=<?= (int)$type['id'] ?>">Builder</a> › Persona preview</div>
<div class="master-head">
  <div>
    <h1>Persona preview — <?= e($type['name'] ?: $type['code']) ?></h1>
    <p class="sub" style="margin:2px 0 0">What each audience actually sees on the finished report, before you use this template.</p>
  </div>
  <div><a class="btn secondary" href="/report-builder?type=<?= (int)$type['id'] ?>">← Back to builder</a></div>
</div>

<div class="kpi-row" style="margin-top:14px">
  <div class="kpi"><span class="k-lab">On the recipient's report</span><span class="k-val"><?= (int)$c['recipient'] ?></span></div>
  <div class="kpi"><span class="k-lab">Internal-only fields</span><span class="k-val"><?= (int)$c['internal'] ?></span></div>
  <div class="kpi"><span class="k-lab">Conditional</span><span class="k-val"><?= (int)$c['conditional'] ?></span></div>
  <div class="kpi"><span class="k-lab">Scored</span><span class="k-val"><?= (int)$c['scored'] ?></span></div>
</div>

<?php if ($c['fields'] === 0): ?>
  <div class="panel" style="margin-top:14px"><p class="muted" style="margin:0">This template has no fields yet. Add sections and fields in the <a href="/report-builder?type=<?= (int)$type['id'] ?>">builder</a>, then preview here.</p></div>
<?php else: ?>

<?php foreach ($pv['sections'] as $s): ?>
  <div class="panel" style="margin-top:14px">
    <h3 class="tab-sub" style="margin-top:0"><?= e($s['title']) ?>
      <?php if ($s['conditional']): ?><span class="pill p-warn" style="font-size:10px">conditional section</span><?php endif; ?>
      <span class="muted" style="font-weight:400;font-size:12px">— <?= (int)$s['recipient_fields'] ?> of <?= count($s['fields']) ?> field(s) shown to the recipient</span>
    </h3>
    <?php if (!$s['fields']): ?>
      <p class="muted" style="margin:4px 0 0;font-size:13px">No fields in this section.</p>
    <?php else: ?>
      <div class="tbl-scroll" style="overflow-x:auto">
      <table class="grid" style="width:100%">
        <tr><th>Field</th><th>Type</th><th>Who sees it</th><th>Notes</th></tr>
        <?php foreach ($s['fields'] as $f):
          $recipient = $f['persona'] === 'RECIPIENT'; ?>
          <tr>
            <td><?= e($f['label']) ?></td>
            <td class="muted" style="font-size:12px"><?= e($f['ftype']) ?></td>
            <td>
              <?php if ($recipient): ?>
                <span class="pill p-ok" style="font-size:10px">Recipient + staff</span>
              <?php else: ?>
                <span class="pill p-mut" style="font-size:10px">Internal (staff only)</span>
              <?php endif; ?>
            </td>
            <td class="muted" style="font-size:12px">
              <?php $notes = [];
                if ($f['conditional']) $notes[] = 'shown only when a rule holds';
                if ($f['scored'])      $notes[] = 'carries assessment score';
                echo e(implode(' · ', $notes) ?: '—'); ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<div class="panel" style="margin-top:14px">
  <p class="muted" style="margin:0;font-size:12.5px"><strong>Reading this:</strong> "Recipient + staff" fields print on the report the client or vendor receives; "Internal (staff only)" fields are hidden working/scoring fields they never see. A conditional field appears only when its rule is met — so it may be absent on a given report. If a field you expect the client to see is marked internal, un-hide it in the <a href="/report-builder?type=<?= (int)$type['id'] ?>">builder</a>.</p>
</div>
<?php endif; ?>
