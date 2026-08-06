<?php $val = fn($k, $dv = '') => e($s[$k] ?? $dv); ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/satisfaction">Customer satisfaction</a> › Request feedback</div>
<div class="master-head"><div><h1>Request feedback</h1>
  <p class="sub">Record that you have asked a <?= e(T('client')) ?> for feedback. When their answer comes in, open the survey and record the response.</p></div></div>

<form method="post" action="/satisfaction-new" class="settings-form">
  <div class="form-grid">
    <div class="ff" style="grid-column:1/-1"><label><?= e(T('client')) ?> *</label>
      <select class="form-control" name="client_id" required>
        <option value="">— choose —</option>
        <?php foreach ($clients as $c): $nm = trim((string)($c['display_name'] ?? '')) ?: (string)($c['legal_name'] ?? ''); ?>
          <option value="<?= (int)$c['id'] ?>"><?= e($nm) ?></option>
        <?php endforeach; ?>
      </select></div>

    <div class="ff" style="grid-column:1/-1"><label>What this is about</label>
      <input class="form-control" name="about" value="<?= $val('about') ?>"
             placeholder="e.g. Third-party inspection at Bharuch, Aug 2026"></div>
    <div class="ff"><label>Report / job reference</label>
      <input class="form-control" name="report_ref" value="<?= $val('report_ref') ?>" placeholder="e.g. TIR/26/0123"></div>
  </div>

  <?php if (function_exists('render_custom_fields') && !empty($cfields)): ?>
    <h3 style="margin-top:22px">Additional details</h3>
    <?= render_custom_fields('satisfaction', $cvals) ?>
  <?php endif; ?>

  <div style="margin-top:16px"><button class="btn" type="submit">Record the request</button>
    <a class="btn secondary" href="/satisfaction">Cancel</a></div>
</form>
