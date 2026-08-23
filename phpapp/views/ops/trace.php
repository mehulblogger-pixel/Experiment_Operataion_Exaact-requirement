<div class="crumbs"><a href="/">Home</a> › Thread</div>
<div class="master-head">
  <div><h1>The whole thread</h1>
  <p class="sub" style="margin:2px 0 0">From the first enquiry to the money in the bank. A stage with nothing in it is shown too — that is where the handover was skipped.</p></div>
  <a class="btn secondary" href="/flow-gaps">Where the flow is broken</a>
</div>

<?php
// The field-level companion to the strip above. The strip says whether the next
// record exists; this says whether the data reached it, which is the question
// behind "why am I typing the PO number again". Every ✗ and ⚠ below is both a
// broken hop and a field some screen has to keep asking for.
$STATE = [
  'origin'  => ['●', 'var(--muted)',  'starts here'],
  'carried' => ['✓', '#1a7f37',       'carried'],
  'link'    => ['↗', '#1a7f37',       'inherited by link'],
  'differs' => ['⚠', '#9a6700',       're-typed — disagrees'],
  'missing' => ['✗', '#cf222e',       'dropped here'],
  'blank'   => ['·', 'var(--muted)',  'nothing to carry'],
  'none'    => ['⊘', 'var(--muted)',  'no field for it'],
  'absent'  => ['—', 'var(--muted)',  'no record at this stage'],
];
?>
<div class="panel" style="margin-top:18px;padding:0;overflow:hidden">
  <div style="padding:11px 16px;background:var(--soft);border-bottom:1px solid var(--line);display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <b style="font-size:13.5px">Did the data carry?</b>
    <?php if ($cont['breaks']): ?>
      <span class="pill" style="background:#ffebe9;color:#cf222e;font-size:11px">
        <?= (int)$cont['breaks'] ?> of <?= (int)$cont['checks'] ?> broken</span>
    <?php else: ?>
      <span class="pill" style="background:#dafbe1;color:#1a7f37;font-size:11px">
        all <?= (int)$cont['checks'] ?> carried</span>
    <?php endif; ?>
    <span class="muted" style="font-size:12.5px;flex:1">Each field that should be inherited once and never typed again. A ✗ is the hop that drops it; a ⚠ is two records disagreeing.</span>
  </div>

  <div style="overflow-x:auto">
  <table style="border-collapse:collapse;width:100%;min-width:760px;font-size:12.5px">
    <thead>
      <tr>
        <th style="text-align:left;padding:8px 16px;border-bottom:1px solid var(--line);font-size:11.5px;letter-spacing:.03em;text-transform:uppercase" class="muted">Field</th>
        <?php foreach ($cont['cols'] as $key => $label): ?>
          <th style="text-align:center;padding:8px 6px;border-bottom:1px solid var(--line);font-size:11.5px;white-space:nowrap<?= empty($chain[$key]) ? ';opacity:.5' : '' ?>"><?= e($label) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($cont['rows'] as $r): ?>
      <tr>
        <td style="padding:9px 16px;border-bottom:1px solid var(--line);vertical-align:top">
          <b><?= e($r['label']) ?></b>
          <?php if ($r['compare'] === 'present'): ?><span class="muted" style="font-size:11px"> · presence only</span><?php endif; ?>
          <div class="muted" style="font-size:11.5px;margin-top:2px;max-width:340px"><?= e($r['why']) ?></div>
        </td>
        <?php foreach ($cont['cols'] as $key => $label):
              $c = $r['cells'][$key]; [$gl, $col, $word] = $STATE[$c['state']];
              $tip = $label . ' — ' . $word . ($c['show'] !== '' ? ': ' . $c['show'] : '')
                   . (!empty($c['note']) ? ' (' . $c['note'] . ')' : ''); ?>
          <td style="padding:9px 6px;border-bottom:1px solid var(--line);text-align:center;vertical-align:top" title="<?= e($tip) ?>">
            <div style="color:<?= $col ?>;font-size:14px;line-height:1"><?= $gl ?></div>
            <?php if ($c['show'] !== ''): ?>
              <div class="muted" style="font-size:10.5px;margin-top:3px;max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-left:auto;margin-right:auto"><?= e($c['show']) ?></div>
            <?php endif; ?>
          </td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <div style="padding:9px 16px;border-top:1px solid var(--line);display:flex;gap:14px;flex-wrap:wrap">
    <?php foreach ($STATE as $st => [$gl, $col, $word]): ?>
      <span class="muted" style="font-size:11.5px"><span style="color:<?= $col ?>"><?= $gl ?></span> <?= e($word) ?></span>
    <?php endforeach; ?>
  </div>
</div>

<div style="margin-top:18px">
<?php foreach (chain_stages() as $key => [$label, $icon, $url]): $rows = $chain[$key] ?? []; ?>
  <div class="panel" style="margin-bottom:12px;padding:0;overflow:hidden<?= $rows ? '' : ';opacity:.72;border-style:dashed' ?>">
    <div style="padding:11px 16px;background:var(--soft);border-bottom:1px solid var(--line);display:flex;gap:10px;align-items:center">
      <span style="font-size:17px"><?= $icon ?></span>
      <b style="font-size:13.5px"><?= e($label) ?></b>
      <span class="muted" style="font-size:12.5px"><?= $rows ? count($rows) : 'nothing at this stage' ?></span>
    </div>
    <?php if ($rows): ?>
      <ul style="list-style:none;margin:0;padding:0">
      <?php foreach ($rows as $r): [$ref, $sub, $state] = chain_label_seen($key, $r); ?>
        <li style="border-bottom:1px solid var(--line)">
          <a href="<?= e($url . (int)$r['id']) ?>" style="display:flex;gap:12px;align-items:baseline;padding:10px 16px;text-decoration:none">
            <b style="font-size:13.5px;min-width:150px"><?= e($ref) ?></b>
            <span class="muted" style="font-size:12.5px;flex:1"><?= e($sub) ?></span>
            <span class="pill p-mut" style="font-size:11px"><?= e($state) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="muted" style="padding:12px 16px;margin:0;font-size:13px">
        <?php
          $hint = [
            'LEAD'    => 'This work was never a lead — it came in as an enquiry or straight as an order.',
            'OPPORTUNITY' => 'No opportunity. The deal itself was never tracked — only the paperwork it produced.',
            'INQUIRY' => 'No enquiry was recorded, so the quotation has nothing behind it.',
            'QUOTE'   => 'No quotation. The rate that was agreed is not on file anywhere.',
            'CALL'    => 'No order. Work with no order cannot be traced to a customer or a rate.',
            'JOB'     => 'Nobody has been sent yet.',
            'REPORT'  => 'No report is linked, so what the customer was sent is not in the system.',
            'INVOICE' => 'Nothing has been billed. If the work is closed, this is money earned and not asked for.',
            'RECEIPT' => 'Nothing has been received against it yet.',
          ][$key] ?? '';
          echo e($hint);
        ?>
      </p>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
