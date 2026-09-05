<?php
// Document Studio — configurable offer/appointment/other templates + letterhead.
// Data: $tpls, $sel, $tokens. Gated is_admin_level(). CSRF auto-stamped.
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$tpls = $tpls ?? []; $sel = $sel ?? null; $tokens = $tokens ?? [];
$types = ['OFFER' => 'Offer letter', 'APPOINTMENT' => 'Appointment letter', 'OTHER' => 'Other document'];
$lh = function_exists('setting_get') ? setting_get('doc_letterhead', '') : '';
$ft = function_exists('setting_get') ? setting_get('doc_footer', '') : '';
$ca = function_exists('setting_get') ? setting_get('company_address', '') : '';
?>
<div class="crumbs"><a href="/">Home</a> › Document templates</div>
<div class="master-head">
  <div><h1>Document Studio</h1>
    <p class="sub" style="margin:2px 0 0">Configure the letters issued to candidates — offer, appointment and any other document. Type text and drop in <b>{tokens}</b>; every variable is auto-filled from the candidate's data when the letter is generated.</p></div>
  <div class="row-actions"><form method="post" style="display:inline"><input type="hidden" name="do" value="save"><input type="hidden" name="name" value="New template"><button class="btn">＋ New template</button></form></div>
</div>

<div style="display:grid;grid-template-columns:260px 1fr;gap:18px;align-items:start">
  <!-- List -->
  <div class="panel" style="padding:0">
    <div style="padding:12px 15px;border-bottom:1px solid var(--line,#e5e7eb);font-weight:700;font-size:14px">Templates</div>
    <?php foreach ($tpls as $t): $on = $sel && (int)$t['id'] === (int)$sel['id']; ?>
      <a href="/doc-templates?id=<?= (int)$t['id'] ?>" style="display:block;padding:11px 15px;border-bottom:1px solid var(--line,#eef1f5);color:inherit;text-decoration:none;<?= $on?'background:var(--brand,#1e40af);color:#fff':'' ?>">
        <div style="font-weight:600;font-size:13.5px"><?= $e($t['name']) ?><?= (int)$t['active']===0?' <span class="pill p-mut" style="font-size:10px">off</span>':'' ?></div>
        <div style="font-size:11.5px;opacity:.8"><?= $e($types[$t['doc_type']] ?? $t['doc_type']) ?></div>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Editor -->
  <div>
    <div class="panel">
      <h3 class="tab-sub"><?= $sel ? 'Edit template' : 'Select or create a template' ?></h3>
      <?php if ($sel): ?>
      <form method="post"><input type="hidden" name="do" value="save"><input type="hidden" name="id" value="<?= (int)$sel['id'] ?>">
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px">
          <div class="ff"><label>Name</label><input class="form-control" name="name" value="<?= $e($sel['name']) ?>"></div>
          <div class="ff"><label>Code</label><input class="form-control" name="code" value="<?= $e($sel['code']) ?>"></div>
          <div class="ff"><label>Type</label><select class="form-control" name="doc_type"><?php foreach ($types as $k=>$v): ?><option value="<?= $k ?>" <?= $sel['doc_type']===$k?'selected':'' ?>><?= $e($v) ?></option><?php endforeach; ?></select></div>
        </div>
        <label style="display:block;font-size:12px;font-weight:600;margin:10px 0 4px">Body</label>
        <textarea class="form-control" name="body" rows="16" style="font-family:ui-monospace,monospace;font-size:13px"><?= $e($sel['body']) ?></textarea>
        <div style="margin-top:12px;display:flex;gap:8px">
          <button class="btn">Save template</button>
          <button class="btn secondary" name="do" value="toggle"><?= (int)$sel['active']===1?'Disable':'Enable' ?></button>
        </div>
      </form>
      <?php else: ?><p class="muted">Pick a template on the left, or create one.</p><?php endif; ?>
    </div>

    <div class="panel">
      <h3 class="tab-sub">Available variables</h3>
      <p class="muted" style="margin-top:-4px;font-size:12.5px">Type these into the body. Anything we don't have for a candidate is highlighted in the generated letter.</p>
      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:4px 18px">
        <?php foreach ($tokens as $k => $desc): ?>
          <div style="font-size:12.5px"><code style="background:var(--soft,#f1f5f9);padding:1px 5px;border-radius:4px">{<?= $e($k) ?>}</code> — <span class="muted"><?= $e($desc) ?></span></div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="panel">
      <h3 class="tab-sub">Letterhead &amp; footer</h3>
      <form method="post"><input type="hidden" name="do" value="letterhead">
        <label style="display:block;font-size:12px;font-weight:600;margin:6px 0 4px">Company address (used by {company_address})</label>
        <input class="form-control" name="company_address" value="<?= $e($ca) ?>" placeholder="Registered office address">
        <label style="display:block;font-size:12px;font-weight:600;margin:10px 0 4px">Letterhead (HTML, shown at the top of every letter)</label>
        <textarea class="form-control" name="doc_letterhead" rows="4" style="font-family:ui-monospace,monospace;font-size:12.5px"><?= $e($lh) ?></textarea>
        <label style="display:block;font-size:12px;font-weight:600;margin:10px 0 4px">Footer (HTML)</label>
        <textarea class="form-control" name="doc_footer" rows="2" style="font-family:ui-monospace,monospace;font-size:12.5px"><?= $e($ft) ?></textarea>
        <div style="margin-top:12px"><button class="btn">Save letterhead</button></div>
      </form>
    </div>
  </div>
</div>
<style>.ff label{display:block;font-size:12px;font-weight:600;margin-bottom:4px}</style>
