<div class="crumbs"><a href="/">Home</a> › Thread</div>
<div class="master-head">
  <div><h1>The whole thread</h1>
  <p class="sub" style="margin:2px 0 0">From the first enquiry to the money in the bank. A stage with nothing in it is shown too — that is where the handover was skipped.</p></div>
  <a class="btn secondary" href="/flow-gaps">Where the flow is broken</a>
</div>

<div style="margin-top:18px">
<?php foreach (CHAIN_STAGES as $key => [$label, $icon, $url]): $rows = $chain[$key] ?? []; ?>
  <div class="panel" style="margin-bottom:12px;padding:0;overflow:hidden<?= $rows ? '' : ';opacity:.72;border-style:dashed' ?>">
    <div style="padding:11px 16px;background:var(--soft);border-bottom:1px solid var(--line);display:flex;gap:10px;align-items:center">
      <span style="font-size:17px"><?= $icon ?></span>
      <b style="font-size:13.5px"><?= e($label) ?></b>
      <span class="muted" style="font-size:12.5px"><?= $rows ? count($rows) : 'nothing at this stage' ?></span>
    </div>
    <?php if ($rows): ?>
      <ul style="list-style:none;margin:0;padding:0">
      <?php foreach ($rows as $r): [$ref, $sub, $state] = chain_label($key, $r); ?>
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
