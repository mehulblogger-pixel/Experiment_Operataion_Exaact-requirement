<?php
$plan  = $plan  ?? null;
$flow  = $flow  ?? '';
$aiOn  = $aiOn  ?? false;
$forms = $forms ?? [];
$typeLabel = ['text' => 'Text', 'textarea' => 'Paragraph', 'number' => 'Number', 'date' => 'Date', 'select' => 'Dropdown'];
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/workspace/setup">Workspace setup</a> › Build forms with AI</div>
<div class="master-head"><div>
  <h1>Build forms from your process</h1>
  <p class="sub" style="margin:2px 0 0">Describe how your recruitment works — in your own words — and we’ll suggest the
    extra fields and dropdown lists to capture it. You review and pick what to keep; nothing is added until you approve.</p>
</div></div>

<?php if (!$aiOn): ?>
  <div class="panel" style="max-width:760px;margin-top:14px;border-left:3px solid var(--warn,#b45309)">
    <h3 class="tab-sub" style="margin-top:0">Turn on AI first</h3>
    <p class="sub" style="margin-bottom:10px">This helper uses your own AI provider. Add an API key (OpenAI, Claude, Gemini,
      Perplexity or Copilot) and pick a model, then come back here.</p>
    <a class="btn" href="/ai-settings">Open AI settings</a>
  </div>
<?php else: ?>

<?php if (!empty($pool)):
        $used = (int) ($used ?? 0); $cap = (int) ($cap ?? 0); $left = max(0, $cap - $used);
        $tone = $left === 0 ? 'var(--warn,#b45309)' : 'var(--brand)'; ?>
  <div class="panel" style="max-width:820px;margin-top:14px;border-left:3px solid <?= $tone ?>;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center">
    <div>
      <strong>✨ AI is included in your plan</strong>
      <div class="muted" style="font-size:12.5px;margin-top:2px">
        <?php if ($left === 0): ?>You've used all <?= $cap ?> AI actions this month — they reset on the 1st. Add your own AI key below for unlimited use.
        <?php else: ?><strong><?= $left ?></strong> of <?= $cap ?> AI actions left this month.<?php endif; ?>
      </div>
    </div>
    <div class="muted" style="font-size:12px;white-space:nowrap"><?= $used ?> / <?= $cap ?> used</div>
  </div>
<?php endif; ?>

<div class="panel settings-form" style="max-width:820px;margin-top:14px">
  <form method="post" action="/ai-forms">
    <input type="hidden" name="action" value="suggest">
    <label style="font-weight:600">Your recruitment process</label>
    <p class="muted" style="margin:2px 0 8px;font-size:13px">Paste your process notes, an SOP, or just describe the steps and
      what information you collect at each one — e.g. “We take the client’s budget and notice period, screen on CTC and
      location, then track interview rounds and the reason if a candidate drops.”</p>
    <textarea class="form-control" name="flow" rows="8" placeholder="Describe your hiring flow and the details you capture…"><?= e($flow) ?></textarea>
    <button class="btn" type="submit" style="margin-top:12px">✨ Generate suggestions</button>
    <p class="muted" style="font-size:12px;margin-top:8px">Suggestions are added to your <strong>Requirement</strong> and
      <strong>Candidate</strong> forms. Everything stays editable afterwards under Forms and Masters.</p>
  </form>
</div>

<?php if ($plan && (!empty($plan['dropdowns']) || !empty($plan['fields']))): ?>
  <div class="panel" style="max-width:820px;margin-top:16px;border-left:3px solid var(--brand)">
    <h3 class="tab-sub" style="margin-top:0">Review the suggestions</h3>
    <p class="muted" style="margin:0 0 12px;font-size:13px">Untick anything you don’t want. When you’re happy, add the rest to your forms.</p>
    <form method="post" action="/ai-forms">
      <input type="hidden" name="action" value="apply">
      <input type="hidden" name="plan" value="<?= e(json_encode($plan, JSON_UNESCAPED_UNICODE)) ?>">

      <?php if (!empty($plan['dropdowns'])): ?>
        <div class="muted" style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin:6px 0 8px">Dropdown lists</div>
        <?php foreach ($plan['dropdowns'] as $dd): ?>
          <label class="chk" style="display:flex;gap:10px;align-items:flex-start;border:1px solid var(--line);border-radius:10px;padding:10px 12px;margin-bottom:8px">
            <input type="checkbox" name="dd[]" value="<?= e($dd['name']) ?>" checked style="margin-top:3px">
            <span>
              <strong><?= e($dd['name']) ?></strong>
              <span class="muted" style="display:block;font-size:12.5px;margin-top:3px"><?= e(implode(' · ', array_slice($dd['values'], 0, 12))) ?><?= count($dd['values']) > 12 ? ' …' : '' ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if (!empty($plan['fields'])): ?>
        <div class="muted" style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin:14px 0 8px">Form fields</div>
        <?php foreach ($plan['fields'] as $i => $f): ?>
          <label class="chk" style="display:flex;gap:10px;align-items:flex-start;border:1px solid var(--line);border-radius:10px;padding:10px 12px;margin-bottom:8px">
            <input type="checkbox" name="fld[]" value="<?= (int) $i ?>" checked style="margin-top:3px">
            <span style="flex:1">
              <strong><?= e($f['label']) ?></strong>
              <span class="muted" style="display:block;font-size:12.5px;margin-top:3px">
                on <strong><?= e($forms[$f['form']] ?? $f['form']) ?></strong>
                · <?= e($typeLabel[$f['type']] ?? $f['type']) ?><?php if ($f['type'] === 'select' && $f['dropdown'] !== ''): ?> (<?= e($f['dropdown']) ?>)<?php endif; ?><?= !empty($f['required']) ? ' · required' : '' ?>
              </span>
            </span>
          </label>
        <?php endforeach; ?>
      <?php endif; ?>

      <div style="display:flex;gap:10px;align-items:center;margin-top:14px">
        <button class="btn" type="submit">Add selected to my forms</button>
        <a class="btn ghost" href="/ai-forms">Start over</a>
      </div>
    </form>
  </div>
<?php endif; ?>

<?php endif; // aiOn ?>
