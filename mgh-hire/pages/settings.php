<?php
// Branding & settings — configurable product name, colours, logo, company.
require_can('settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = post('do');
    if ($do === 'brand') {
        setting_set('product_name', post('product_name','MGH Hire') ?: 'MGH Hire');
        setting_set('company_name', post('company_name'));
        setting_set('currency', post('currency','₹') ?: '₹');
        $bc = post('brand_color'); $ac = post('accent_color');
        if (preg_match('/^#[0-9a-fA-F]{6}$/',$bc)) setting_set('brand_color',$bc);
        if (preg_match('/^#[0-9a-fA-F]{6}$/',$ac)) setting_set('accent_color',$ac);
        // Optional logo upload (stored as a data URI).
        if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
            $bytes = file_get_contents($_FILES['logo']['tmp_name']);
            if (strlen($bytes) <= 1024*1024 && @getimagesizefromstring($bytes)) {
                setting_set('logo_data', 'data:'.($_FILES['logo']['type']?:'image/png').';base64,'.base64_encode($bytes));
            } else flash('Logo must be an image under 1 MB.', 'err');
        }
        if (post('remove_logo')) setting_set('logo_data','');
        flash('Branding saved.');
    }
    redirect('?p=settings');
}

$logo = setting('logo_data','');
layout_top('Branding');
?>
<div class="card">
  <h2 class="mt0">Branding &amp; theme</h2>
  <p class="muted">Everything here is white-label. Change the name, colours and logo and the whole product re-themes instantly — no code, no developer.</p>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="do" value="brand"><?= csrf_field() ?>
    <div class="row2">
      <div><label>Product name (shown everywhere)</label><input name="product_name" value="<?= e(product_name()) ?>"></div>
      <div><label>Company / workspace name</label><input name="company_name" value="<?= e(setting('company_name','')) ?>"></div>
    </div>
    <div class="row3">
      <div><label>Primary colour</label><input name="brand_color" type="color" value="<?= e(brand_color()) ?>" style="height:42px;padding:4px"></div>
      <div><label>Accent colour</label><input name="accent_color" type="color" value="<?= e(accent_color()) ?>" style="height:42px;padding:4px"></div>
      <div><label>Currency symbol</label><input name="currency" value="<?= e(currency()) ?>" maxlength="4"></div>
    </div>
    <div class="row2">
      <div>
        <label>Logo (image, under 1 MB — optional)</label>
        <input type="file" name="logo" accept="image/*">
        <?php if ($logo): ?><div style="margin-top:8px;display:flex;align-items:center;gap:10px"><img src="<?= e($logo) ?>" style="height:40px;border-radius:8px"><label style="margin:0"><input type="checkbox" name="remove_logo" style="width:auto;margin-right:6px">Remove logo</label></div><?php endif; ?>
      </div>
      <div></div>
    </div>
    <div style="margin-top:16px"><button class="btn">Save branding</button></div>
  </form>
</div>

<div class="card">
  <h2>About this workspace</h2>
  <p class="muted mt0">
    <b><?= e(product_name()) ?></b> — a standalone Recruitment &amp; Selection product.
    Data is stored in this workspace only. On a cloud hosting we operate for you, your data is encrypted and not readable by us;
    you can request a full export or restore at any time. On your own server, the data never leaves your premises.
  </p>
</div>
<?php layout_bottom();
