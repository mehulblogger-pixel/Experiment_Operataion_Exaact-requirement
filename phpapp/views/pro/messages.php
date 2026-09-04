<?php
  // Connect #4 (pro side) — the professional's own message inbox + thread. One
  // conversation per engagement (an application the professional made). Mirrors the
  // staff desk chat; the newest message sits at the bottom.
  $me = $me ?? []; $summaries = $summaries ?? []; $open = $open ?? null; $openId = $openId ?? 0;
  $when = fn($iso) => $iso ? e(date('d M, H:i', strtotime((string)$iso))) : '';
?>
<h1>Messages</h1>
<p class="muted" style="margin:0 0 16px">Talk to the hiring desk about jobs you've applied for — all in one place, kept as your record.</p>

<style>
  .pm{display:grid;grid-template-columns:1fr;gap:14px}
  .pm-item{display:block;padding:12px 14px;text-decoration:none;color:inherit}
  .pm-item .who{font-weight:700}
  .pm-item .req{font-size:12px;color:var(--muted)}
  .pm-item .snip{font-size:13px;color:var(--muted);margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .pm-un{display:inline-block;min-width:20px;height:20px;line-height:20px;text-align:center;border-radius:999px;background:var(--teal);color:#fff;font-size:12px;font-weight:700;padding:0 6px;float:right}
  .bub{max-width:80%;padding:10px 13px;border-radius:14px;font-size:14px;line-height:1.4;margin-bottom:8px}
  .bub .meta{font-size:11px;color:var(--muted);margin-top:4px}
  .bub.them{background:var(--line);border-bottom-left-radius:4px;margin-right:auto}
  .bub.me{background:rgba(15,125,125,.16);border-bottom-right-radius:4px;margin-left:auto}
  .pm-reply{display:flex;gap:8px;margin-top:10px}
  .pm-reply textarea{flex:1;min-height:46px;resize:vertical}
</style>

<?php if ($open):
  $app = $open['app']; ?>
  <div class="card">
    <a href="/pro/messages" class="muted" style="text-decoration:none">← All conversations</a>
    <h2 style="margin:8px 0 2px"><?= e((string)($app['poster_name'] ?? '') ?: 'Hiring desk') ?></h2>
    <div class="muted" style="font-size:12px;margin-bottom:12px"><?= e((string)$app['ref_code']) ?> · <?= e((string)$app['req_title']) ?> · <?= e(strtolower((string)$app['status'])) ?></div>
    <div id="pmbody" style="max-height:420px;overflow-y:auto;display:flex;flex-direction:column">
      <?php if (!$open['thread']): ?>
        <p class="muted">No messages yet — send the first one below.</p>
      <?php else: foreach ($open['thread'] as $m): $mine = ($m['sender_kind'] === 'professional');
        $shown = function_exists('connect_msg_display_body') ? connect_msg_display_body((string)$m['body'], (int)$openId, 'professional') : (string)$m['body']; ?>
        <div class="bub <?= $mine ? 'me' : 'them' ?>">
          <?= nl2br(e($shown)) ?>
          <div class="meta"><?= e($mine ? 'You' : ($m['sender_name'] !== '' ? $m['sender_name'] : 'Desk')) ?> · <?= $when($m['created_at']) ?></div>
        </div>
      <?php endforeach; endif; ?>
      <?php if (function_exists('connect_msg_contacts_revealed') && !connect_msg_contacts_revealed((int)$openId)): ?>
        <p class="muted" style="font-size:12px;margin:6px 0 0">🔒 Phone numbers and emails stay hidden until you're hired for this job — then they're shared so you can coordinate. Keeping the deal here gets you paid on time, with dispute cover.</p>
      <?php endif; ?>
    </div>
    <form class="pm-reply" method="post" action="/pro/messages">
      <input type="hidden" name="application_id" value="<?= (int)$openId ?>">
      <textarea name="body" placeholder="Write a message…" required></textarea>
      <button class="btn" type="submit">Send</button>
    </form>
  </div>
  <script>(function(){var b=document.getElementById('pmbody'); if(b) b.scrollTop=b.scrollHeight;})();</script>
<?php else: ?>
  <div class="card" style="padding:0">
    <?php if (!$summaries): ?>
      <p class="muted" style="padding:18px;margin:0">No conversations yet. When you apply for a job, you can message the hiring desk here.</p>
    <?php else: foreach ($summaries as $s):
      $title = $s['poster_name'] !== '' ? $s['poster_name'] : 'Hiring desk'; ?>
      <a class="pm-item" href="/pro/messages?a=<?= (int)$s['application_id'] ?>" style="border-bottom:1px solid var(--line)">
        <?php if ($s['unread'] > 0): ?><span class="pm-un"><?= (int)$s['unread'] ?></span><?php endif; ?>
        <div class="who"><?= e($title) ?></div>
        <div class="req"><?= e($s['ref_code']) ?> · <?= e($s['req_title']) ?></div>
        <?php if ($s['last']): ?><div class="snip"><?= e(($s['last']['sender_kind'] === 'professional' ? 'You: ' : '') . mb_substr((string)$s['last']['body'], 0, 60)) ?></div><?php endif; ?>
      </a>
    <?php endforeach; endif; ?>
  </div>
<?php endif; ?>
