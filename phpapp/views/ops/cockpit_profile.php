<?php
// Cockpit → Business profile. Captures the company name and the multi-select
// "what does your company do?" capabilities. The catalogue is reused from
// connect_cap_catalog(); selections are stored in company_capabilities.
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$chosen = array_flip($chosen ?? []);
?>
<style>
  .ck{max-width:760px}
  .ck .cap-group{margin:0 0 16px}
  .ck .cap-group h3{margin:0 0 8px;font-size:14px;color:#334155}
  .ck .caps{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px}
  .ck .cap{display:flex;gap:10px;align-items:center;border:1px solid var(--line,#e5e7eb);border-radius:12px;
    padding:11px 13px;cursor:pointer;background:var(--card,#fff)}
  .ck .cap:hover{border-color:#94a3b8}
  .ck .cap input{width:18px;height:18px;flex:none}
  .ck .cap.on{border-color:#137a4b;background:#f2fbf6}
  @media (max-width:520px){ .ck .caps{grid-template-columns:1fr} }
</style>

<div class="ck">
  <div class="crumbs"><a href="/">Home</a> › <a href="/workspace/setup">Workspace setup</a> › Business profile</div>
  <h1 style="margin:.2em 0">Business profile</h1>
  <p class="muted" style="margin-top:0">Tell us your company name and what you do. Pick <strong>every</strong> activity that applies — many companies do more than one. This tailors your workspace and, later, what we suggest.</p>

  <form method="post" action="/workspace/setup/profile-save">
    <?php if (function_exists('csrf_field')) echo csrf_field(); ?>

    <div class="ff" style="max-width:420px;margin-bottom:20px">
      <label for="ck_company" style="font-weight:600;display:block;margin-bottom:4px">Company name</label>
      <input id="ck_company" class="form-control" name="company_name" value="<?= $e($company ?? '') ?>" placeholder="e.g. Sachee HR Recruitment Services">
    </div>

    <h2 style="font-size:17px;margin:0 0 4px">What does your company do?</h2>
    <p class="muted" style="margin-top:0;font-size:13px">Select all that apply.</p>

    <?php foreach ($groups as $groupName => $caps): ?>
      <div class="cap-group">
        <h3><?= $e($groupName) ?></h3>
        <div class="caps">
          <?php foreach ($caps as $code => $label): $on = isset($chosen[$code]); ?>
            <label class="cap<?= $on ? ' on' : '' ?>">
              <input type="checkbox" name="caps[]" value="<?= $e($code) ?>" <?= $on ? 'checked' : '' ?> onchange="this.closest('.cap').classList.toggle('on', this.checked)">
              <span><?= $e($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <div style="display:flex;gap:10px;align-items:center;margin-top:8px;flex-wrap:wrap">
      <button class="btn" type="submit">Save profile</button>
      <a class="btn ghost" href="/workspace/setup">Back to setup</a>
      <a class="muted" href="/company-profile" style="font-size:13px">Legal name, logo &amp; address →</a>
    </div>
  </form>
</div>
