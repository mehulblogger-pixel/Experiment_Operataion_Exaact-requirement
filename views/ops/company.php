<?php $p = $p ?? []; ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/settings">Settings</a> › Your company</div>
<div class="master-head"><div>
  <h1>Your company</h1>
  <p class="sub" style="margin:2px 0 0">Enter your business once. It appears on your quotations, invoices, e-mails and records.</p>
</div></div>

<div class="panel settings-form" style="max-width:720px;margin-top:14px">
  <form method="post" action="/company-profile" enctype="multipart/form-data">
    <div class="form-grid">
      <div class="ff ff-wide"><label>Legal name <span class="muted">— as registered</span></label>
        <input class="form-control" name="legal_name" value="<?= e($p['legal_name'] ?? '') ?>" placeholder="e.g. Mystical Home Decor Products"></div>
      <div class="ff ff-wide"><label>Brand / trading name <span class="muted">— shown in the app</span></label>
        <input class="form-control" name="brand" value="<?= e($p['brand'] ?? '') ?>" placeholder="e.g. MGH AI Apps"></div>
      <div class="ff ff-wide"><label>Address</label>
        <textarea class="form-control" name="address" rows="3" placeholder="Street, city, state, PIN"><?= e($p['address'] ?? '') ?></textarea></div>
      <div class="ff"><label>GSTIN</label><input class="form-control" name="gstin" value="<?= e($p['gstin'] ?? '') ?>" placeholder="15-character GSTIN"></div>
      <div class="ff"><label>PAN <span class="muted">— filled from GSTIN if blank</span></label><input class="form-control" name="pan" value="<?= e($p['pan'] ?? '') ?>"></div>
      <div class="ff"><label>State <span class="muted">— for GST</span></label><input class="form-control" name="state" value="<?= e($p['state'] ?? '') ?>" placeholder="e.g. Gujarat"></div>
      <div class="ff"><label>E-mail</label><input class="form-control" type="email" name="email" value="<?= e($p['email'] ?? '') ?>"></div>
      <div class="ff"><label>Phone</label><input class="form-control" name="phone" value="<?= e($p['phone'] ?? '') ?>"></div>
      <div class="ff"><label>Website</label><input class="form-control" name="website" value="<?= e($p['website'] ?? '') ?>" placeholder="www.example.com"></div>
      <div class="ff ff-wide"><label>Footer line for PDFs <span class="muted">— optional, e.g. CIN / bank / tagline</span></label>
        <input class="form-control" name="footer" value="<?= e($p['footer'] ?? '') ?>" placeholder="Appears at the bottom of quotations, reports and endorsements"></div>
      <div class="ff ff-wide"><label>Logo <span class="muted">— shown on quotations and reports</span></label>
        <input class="form-control" type="file" name="logo" accept="image/*">
        <?php if (!empty($p['logo'])): ?>
          <label class="chk" style="margin-top:6px"><input type="checkbox" name="clear_logo" value="1"> Remove the current logo</label>
        <?php endif; ?>
      </div>
    </div>
    <button class="btn" type="submit" style="margin-top:12px">Save company profile</button>
  </form>
  <p class="muted" style="font-size:12px;margin-top:10px">This feeds the quotation letterhead and invoice header. Leaving it blank
    is fine on a fresh install — fill it when you are ready.</p>
</div>
