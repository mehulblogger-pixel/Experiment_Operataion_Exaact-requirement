<h2 class="ptitle"><?= e($title ?? 'Please try that again') ?></h2>
<p class="plead"><?= e($body ?? '') ?></p>
<p class="pnote"><a href="<?= portal_user() ? '/portal' : '/portal/login' ?>">Start again</a></p>
