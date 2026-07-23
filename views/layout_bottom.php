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
<script src="/assets/js/app.js"></script>
</body></html>
