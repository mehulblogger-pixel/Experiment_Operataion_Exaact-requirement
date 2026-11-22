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
  <?php //  Its items are a company setting (Settings → Display & terms → Phone bottom bar,
        //  "Field staff"; owner, 22 Nov 2026: "make it configurable, admin can set that"),
        //  starting from Home · My Jobs · Voucher · My Work. Punch in / out is added for
        //  anyone who may punch today (existing rules, lib/trust.php — answer 3).
        $bnItems = function_exists('area_bn_field_items') ? area_bn_field_items()
                 : [['/', '🏠', 'Home'], ['/my-jobs', '🗂', 'My Jobs'], ['/vouchers', '🧾', 'Voucher']];
        $bnPunch = function_exists('area_bn_punch') ? area_bn_punch() : null;
        $bnMatch = ['/' => [''], '/my-jobs' => ['my-jobs'], '/vouchers' => ['vouchers', 'voucher'], '/my-work' => ['my-work'],
                    '/documents' => ['documents', 'document'], '/endorsements' => ['endorsements', 'endorsement']]; ?>
  <nav class="botnav">
    <?php foreach ($bnItems as [$bnR, $bnI, $bnL]): ?>
    <a class="bn<?= $bon($bnMatch[$bnR] ?? [ltrim($bnR, '/')]) ?>" href="<?= e($bnR) ?>"><span><?= $bnI ?></span><em><?= e($bnL) ?></em></a>
    <?php endforeach; ?>
    <?php if ($bnPunch): ?>
    <a class="bn" href="<?= e($bnPunch['href']) ?>" data-punch="<?= e($bnPunch['next']) ?>"><span>📍</span><em><?= e($bnPunch['label']) ?></em></a>
    <?php endif; ?>
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
