</main>
<?php if (current_user()): ?>
  </div><!-- .main -->
</div><!-- .shell -->
<?php
  // Inspector phone-first bottom tab bar (shown on small screens only via CSS)
  if (!empty($isInsp)):
    $bcur = $cur ?? trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    $bon = function($routes) use ($bcur){ foreach($routes as $r){ if($bcur===$r || ($r!==''&&strpos($bcur,$r)===0)) return ' on'; } return ($bcur===''&&in_array('',$routes,true))?' on':''; };
?>
  <nav class="botnav">
    <a class="bn<?= $bon(['']) ?>" href="/"><span>🏠</span><em>Home</em></a>
    <a class="bn<?= $bon(['my-jobs']) ?>" href="/my-jobs"><span>🗂</span><em>My Jobs</em></a>
    <a class="bn<?= $bon(['vouchers','voucher']) ?>" href="/vouchers"><span>🧾</span><em>Voucher</em></a>
  </nav>
<?php endif; ?>
<?php endif; ?>
<?php
  // DEF-SAL-036 — what was typed before a session time-out (or a stale-page refusal) is put back
  // into the boxes of the form it came from. Only for the same screen, within 15 minutes; never
  // passwords or security tokens (form_stash() drops them).
  $__retry = function_exists('form_retry_data') ? form_retry_data() : [];
  if ($__retry): ?>
<script>window.__formRetry = <?= json_encode($__retry, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;</script>
<?php endif; ?>
<script src="/assets/js/app.js"></script>
</body></html>
