<h1><?= e($title ?? 'Please try that again') ?></h1>
<div class="card">
  <p class="muted" style="margin:0 0 10px"><?= e($body ?? '') ?></p>
  <a class="btn sec" href="<?= connect_pro_user() ? '/pro' : '/pro/login' ?>">Start again</a>
</div>
