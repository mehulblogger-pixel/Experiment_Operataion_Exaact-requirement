<h2 class="ptitle"><?= e($title ?? 'Please try that again') ?></h2>
<p class="plead"><?= e($body ?? '') ?></p>
<p class="pnote"><a href="<?= cvp_vendor_user() ? '/vendor' : '/vendor/login' ?>">Start again</a></p>
