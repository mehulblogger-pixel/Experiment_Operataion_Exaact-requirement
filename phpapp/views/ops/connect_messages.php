<?php
  // Connect K15 / backlog #4 — the staff marketplace messaging desk. Left: an
  // inbox of engagement threads (unread first). Right: one open conversation with
  // a reply box. Zero-Training: it reads like any chat app; plain words, big tap
  // targets, the newest message at the bottom.
  $summaries = $summaries ?? []; $open = $open ?? null; $openId = $openId ?? 0; $meId = $meId ?? 0;
  $when = fn($iso) => $iso ? e(date('d M, H:i', strtotime((string)$iso))) : '';
?>
<div class="crumbs"><a href="/">Home</a> › Messages</div>
<div class="master-head">
  <div><h1>Messages</h1>
    <p class="sub" style="margin:2px 0 0">Talk to applicants inside the platform — no WhatsApp, no lost context.
      Every conversation is tied to its requirement and kept as part of the hiring and dispute record.</p></div>
</div>

<style>
  .mx{display:grid;grid-template-columns:340px 1fr;gap:16px;margin-top:14px;align-items:start}
  @media(max-width:820px){.mx{grid-template-columns:1fr}}
  .mx-list{border:1px solid var(--line,#e5e7eb);border-radius:14px;overflow:hidden;background:var(--card,#fff)}
  .mx-item{display:block;padding:12px 14px;border-bottom:1px solid var(--line,#eee);text-decoration:none;color:inherit}
  .mx-item:last-child{border-bottom:0}
  .mx-item.on{background:rgba(15,125,125,.08)}
  .mx-item .who{font-weight:700}
  .mx-item .req{font-size:12px;color:var(--muted,#777)}
  .mx-item .snip{font-size:13px;color:var(--muted,#666);margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .mx-unread{display:inline-block;min-width:20px;height:20px;line-height:20px;text-align:center;border-radius:999px;background:#0f7d7d;color:#fff;font-size:12px;font-weight:700;padding:0 6px;float:right}
  .mx-none{padding:14px;color:var(--muted,#777)}
  .mx-thread{border:1px solid var(--line,#e5e7eb);border-radius:14px;background:var(--card,#fff);display:flex;flex-direction:column;min-height:420px}
  .mx-head{padding:14px;border-bottom:1px solid var(--line,#eee)}
  .mx-body{padding:14px;display:flex;flex-direction:column;gap:10px;flex:1}
  .bub{max-width:74%;padding:10px 13px;border-radius:14px;font-size:14px;line-height:1.4}
  .bub .meta{font-size:11px;color:var(--muted,#888);margin-top:4px}
  .bub.them{align-self:flex-start;background:var(--line,#eef2f2);border-bottom-left-radius:4px}
  .bub.me{align-self:flex-end;background:rgba(15,125,125,.14);border-bottom-right-radius:4px}
  .mx-reply{padding:12px;border-top:1px solid var(--line,#eee);display:flex;gap:8px}
  .mx-reply textarea{flex:1;padding:10px;border:1px solid var(--line,#ddd);border-radius:10px;font-size:14px;resize:vertical;min-height:44px;font-family:inherit}
  .mx-send{padding:10px 18px;border-radius:10px;background:#0f7d7d;color:#fff;border:0;font-weight:700;cursor:pointer}
  .mx-empty{flex:1;display:flex;align-items:center;justify-content:center;color:var(--muted,#888);text-align:center;padding:30px}
</style>

<div class="mx">
  <!-- Inbox -->
  <div class="mx-list">
    <?php if (!$summaries): ?>
      <div class="mx-none">No conversations yet. Open a requirement's applicant and say hello.</div>
    <?php else: foreach ($summaries as $s):
      $who = $s['applicant']['name'] !== '' ? $s['applicant']['name'] : ('Applicant #' . $s['application_id']); ?>
      <a class="mx-item <?= $openId === $s['application_id'] ? 'on' : '' ?>" href="/connect-messages?a=<?= (int)$s['application_id'] ?>">
        <?php if ($s['unread'] > 0): ?><span class="mx-unread"><?= (int)$s['unread'] ?></span><?php endif; ?>
        <div class="who"><?= e($who) ?></div>
        <div class="req"><?= e($s['ref_code']) ?> · <?= e($s['req_title']) ?></div>
        <?php if ($s['last']): ?><div class="snip"><?= e(($s['last']['sender_kind'] === 'staff') ? 'You: ' : '') . mb_substr((string)$s['last']['body'], 0, 60) ?></div><?php endif; ?>
      </a>
    <?php endforeach; endif; ?>
  </div>

  <!-- Open thread -->
  <div class="mx-thread">
    <?php if (!$open): ?>
      <div class="mx-empty">Pick a conversation on the left to read and reply.</div>
    <?php else:
      $ap = $open['applicant']; $app = $open['app'];
      $who = $ap['name'] !== '' ? $ap['name'] : ('Applicant #' . $openId); ?>
      <div class="mx-head">
        <strong><?= e($who) ?></strong>
        <div class="req" style="font-size:12px;color:var(--muted,#777)">
          <?= e($app['ref_code']) ?> · <?= e($app['req_title']) ?> · application <?= e(strtolower((string)$app['status'])) ?>
        </div>
      </div>
      <div class="mx-body" id="mxbody">
        <?php if (!$open['thread']): ?>
          <div class="mx-empty">No messages yet — send the first one below.</div>
        <?php else: foreach ($open['thread'] as $m):
          $mine = ($m['sender_kind'] === 'staff'); ?>
          <div class="bub <?= $mine ? 'me' : 'them' ?>">
            <?= nl2br(e((string)$m['body'])) ?>
            <div class="meta"><?= e($mine ? 'You' : ($m['sender_name'] !== '' ? $m['sender_name'] : ucfirst((string)$m['sender_kind']))) ?> · <?= $when($m['created_at']) ?></div>
          </div>
        <?php endforeach; endif; ?>
      </div>
      <?php if (function_exists('connect_msg_contacts_revealed') && !connect_msg_contacts_revealed((int)$openId)): ?>
        <div style="padding:6px 14px;font-size:12px;color:var(--muted,#888)">🔒 Until this requirement is awarded to this applicant, phone numbers and emails you type are hidden from them (you still see the full text). They unlock automatically on award.</div>
      <?php endif; ?>
      <form class="mx-reply" method="post" action="/connect-messages">
        <input type="hidden" name="application_id" value="<?= (int)$openId ?>">
        <textarea name="body" placeholder="Write a message…" required></textarea>
        <button class="mx-send" type="submit">Send</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<script>
  // Keep the latest message in view.
  (function(){ var b=document.getElementById('mxbody'); if(b) b.scrollTop=b.scrollHeight; })();
</script>
