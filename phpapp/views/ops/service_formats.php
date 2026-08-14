<?php
// ============================================================================
//  Admin — Report formats by service line.
//  Each TPIA service line allocates a default report format when a work order
//  is created. This is where an administrator changes those pairings. The
//  format chosen here is the report type; the .docx template (house or
//  client-specific) is still resolved by the template picker at finalize.
// ============================================================================
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/service-scope">Service scope</a> › Report formats</div>
<div class="master-head">
  <div>
    <h1>Report formats by service line</h1>
    <p class="sub" style="margin:2px 0 0">Pick the report format each service line hands the job. It is applied automatically when a work order is created with that service line, and can still be changed on the work order or the job.</p>
  </div>
</div>

<form method="post" action="/service-formats">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <section class="card">
    <table class="grid">
      <tr>
        <th style="width:26%">Service line</th>
        <th style="width:32%">Primary report format</th>
        <th>Also allocate (optional)</th>
      </tr>
      <?php foreach ($services as $s):
        $code = $s['code'];
        $rows = $map[$code] ?? [];
        $primary = '';
        foreach ($rows as $r) { if ($r['primary']) { $primary = $r['code']; break; } }
        if ($primary === '' && $rows) $primary = $rows[0]['code'];
        $also = array_values(array_filter(array_map(fn($r) => $r['code'], $rows), fn($c) => $c !== $primary));
      ?>
      <tr>
        <td><span style="font-size:16px"><?= e($s['icon'] ?: '🧩') ?></span> <strong><?= e($s['name']) ?></strong></td>
        <td>
          <select class="form-control" name="fmt_<?= e($code) ?>">
            <option value="">— none —</option>
            <?php foreach ($types as $t): ?>
              <option value="<?= e($t['code']) ?>" <?= $primary === $t['code'] ? 'selected' : '' ?>><?= e($t['name']) ?> (<?= e($t['code']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </td>
        <td>
          <select class="form-control" name="also_<?= e($code) ?>[]" multiple size="4" style="min-height:96px">
            <?php foreach ($types as $t): if ($t['code'] === $primary) continue; ?>
              <option value="<?= e($t['code']) ?>" <?= in_array($t['code'], $also, true) ? 'selected' : '' ?>><?= e($t['name']) ?> (<?= e($t['code']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <small class="muted">Hold Ctrl / Cmd to pick more than one.</small>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <div class="row-actions" style="margin-top:14px">
      <button class="btn primary" type="submit">Save report formats</button>
      <a class="btn secondary" href="/service-scope">Back to service scope</a>
    </div>
  </section>
</form>
