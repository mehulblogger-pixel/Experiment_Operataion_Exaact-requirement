<?php
// The connection screen. Deliberately plain: an integration settings page that
// looks clever is one where nobody can tell whether it is actually working.
$c = $cfg;
?>
<div class="crumbs"><a href="/">Home</a> › Ads Pro</div>
<div class="master-head">
  <div><h1>Ads Pro connection</h1>
  <p class="sub" style="margin:2px 0 0">Ads Pro runs the campaigns and knows what they cost. This system knows what was invoiced and what was paid. Joined, the two answer the question neither can answer alone: which advertising brought back money that actually arrived.</p></div>
  <?php if ($on): ?><a class="btn secondary" href="/ads-roi">See what it earned</a><?php endif; ?>
</div>

<?php if (!$on): ?>
  <div class="msg msg-info" style="margin-top:12px">
    Not connected yet. Fill in the three fields below. Until then nothing about Ads Pro appears anywhere else in this system —
    no menu item, no report, no checks.
  </div>
<?php endif; ?>

<div class="panel-split" style="margin-top:16px">
  <div class="panel">
    <h3 style="margin-top:0">The connection</h3>
    <form method="post" action="/adspro-save">
      <div class="form-grid">
        <div class="fg-full">
          <label class="form-label">Ads Pro address</label>
          <input class="form-control" name="url" value="<?= e($c['base']) ?>" placeholder="https://adspro.mghaiapps.com"
                 <?= $envUrl ? 'readonly' : '' ?>>
          <div class="muted" style="font-size:12px;margin-top:3px">
            <?= $envUrl ? 'Fixed by the server environment (ADSPRO_URL), so it cannot be changed from here.'
                        : 'No trailing slash needed. It must be https.' ?>
          </div>
        </div>
        <div class="fg-full">
          <label class="form-label">Workspace id</label>
          <input class="form-control" name="workspace" value="<?= e($c['workspace']) ?>" placeholder="the workspace this company uses in Ads Pro">
          <div class="muted" style="font-size:12px;margin-top:3px">Ads Pro keeps each client in its own workspace. Leads and spend are read from this one only.</div>
        </div>
        <div class="fg-full">
          <label class="form-label">API token</label>
          <input class="form-control" name="token" type="password" autocomplete="new-password"
                 placeholder="<?= $c['has_token'] ? 'A token is stored — leave blank to keep it' : 'Paste the token from Ads Pro' ?>"
                 <?= $envTok ? 'readonly' : '' ?>>
          <div class="muted" style="font-size:12px;margin-top:3px">
            <?= $envTok ? 'Held in the server environment (ADSPRO_TOKEN) and never stored in the database.'
                        : 'Stored once and never shown again — not in this form, not in the page source, not in an export.' ?>
          </div>
          <?php if ($c['has_token'] && !$envTok): ?>
            <label style="display:flex;gap:8px;align-items:center;margin-top:8px;font-size:13px">
              <input type="checkbox" name="forget_token" value="1"><span>Forget the stored token</span>
            </label>
          <?php endif; ?>
        </div>
        <div class="fg-full">
          <label style="display:flex;gap:8px;align-items:center;font-size:13px">
            <input type="checkbox" name="autopush" value="1" <?= !empty($c['auto']) ? 'checked' : '' ?>>
            <span>Tell Ads Pro when money is actually received</span>
          </label>
          <div class="muted" style="font-size:12px;margin-top:3px">
            Ads Pro optimises on whatever conversion value it is given. Left alone that is a figure estimated from a tracking pixel.
            With this on it gets the real one — which is the difference between advertising that looks profitable and advertising that is.
          </div>
        </div>
      </div>
      <?php if ($canManage): ?>
        <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn" type="submit">Save</button>
        </div>
      <?php else: ?>
        <p class="muted" style="margin:12px 0 0;font-size:12.5px">You can see this connection but not change it.</p>
      <?php endif; ?>
    </form>

    <?php if ($canManage): ?>
      <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;border-top:1px solid var(--line);padding-top:12px">
        <form method="post" action="/adspro-test"><button class="btn small secondary" type="submit">Test the connection</button></form>
        <form method="post" action="/adspro-import"><button class="btn small" type="submit">Bring leads across</button></form>
        <form method="post" action="/adspro-spend">
          <input type="hidden" name="days" value="90">
          <button class="btn small secondary" type="submit">Refresh 90 days of spend</button>
        </form>
      </div>
      <p class="muted" style="margin:8px 0 0;font-size:12px">
        Bringing leads across is safe to press twice: each Ads Pro record is matched on its own id, and one already here is left
        exactly as it is — including any edit somebody has made to it since.
      </p>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h3 style="margin-top:0">What has come across</h3>
    <div class="kv-grid">
      <div><span class="k">Leads imported</span><span><?= (int)$counts['leads'] ?></span></div>
      <div><span class="k">Still linked to a live lead</span><span><?= (int)$counts['linked'] ?></span></div>
      <div><span class="k">Campaigns with spend</span><span><?= (int)$counts['campaigns'] ?></span></div>
      <div><span class="k">Spend held locally</span><span><?= e(fmoney((float)$counts['spend'])) ?></span></div>
      <div><span class="k">Outcomes sent back</span><span><?= (int)$counts['pushed'] ?></span></div>
      <div><span class="k">Last successful call</span><span><?= $counts['last'] !== '' ? e(fdate(substr($counts['last'], 0, 10))) : '<span class="muted">never</span>' ?></span></div>
    </div>
    <p class="muted" style="margin:10px 0 0;font-size:12.5px">
      Spend is stored here rather than fetched each time, so the return report still answers for a closed year on a morning when
      Ads Pro is down or a token has expired.
    </p>
  </div>
</div>

<div class="panel" style="margin-top:16px">
  <h3 style="margin-top:0">What travels, and which way</h3>
  <div class="dt-scroll">
    <table class="dt">
      <caption class="sr-only">Sync directions</caption>
      <thead><tr><th scope="col"></th><th scope="col">Ads Pro → here</th><th scope="col">Here → Ads Pro</th></tr></thead>
      <tbody>
        <tr><td><b>Leads</b></td>
            <td><?= (int)($links['leads_in'] ?? 0) ?> brought across</td>
            <td><?= (int)($links['leads_out'] ?? 0) ?> sent out</td></tr>
        <tr><td><b>Enquiries</b></td>
            <td><?= (int)($links['inq_in'] ?? 0) ?> arrived already qualified</td>
            <td><?= (int)($links['inq_out'] ?? 0) ?> sent out</td></tr>
      </tbody>
    </table>
  </div>
  <p class="muted" style="margin:10px 0 0;font-size:12.5px;max-width:760px">
    A contact Ads Pro has already marked <b>qualified</b> arrives here as an <b>enquiry</b>, not a lead — it has already
    asked us for something, and filing it at the top of a funnel would make somebody work back down to where it already is.
    Going the other way, an enquiry raised here becomes a qualified contact there, so Ads Pro stops advertising to a person
    who is mid-conversation with us.
  </p>
  <p class="muted" style="margin:8px 0 0;font-size:12.5px;max-width:760px">
    <b>Who owns what.</b> Ads Pro owns the campaign, the platform and the UTM — facts about an advertisement, and we do not
    run the advertisements, so we never write them back. We own the status, the stage and the value, so its status is
    recorded but never written over ours. Contact details are shared under the one rule that cannot lose anything:
    <b>an import fills blanks and never overwrites</b> something a person here has typed.
  </p>
  <?php if ($canManage): ?>
    <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;border-top:1px solid var(--line);padding-top:12px">
      <form method="post" action="/adspro-sync"><button class="btn" type="submit">⇄ Sync now, both ways</button></form>
      <form method="post" action="/adspro-backfill">
        <input type="hidden" name="kind" value="LEAD">
        <button class="btn small secondary" type="submit">Queue every lead we already have</button>
      </form>
      <form method="post" action="/adspro-backfill">
        <input type="hidden" name="kind" value="INQUIRY">
        <button class="btn small secondary" type="submit">Queue every enquiry we already have</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<div class="panel" style="margin-top:16px;padding:0;overflow:hidden">
  <div style="padding:12px 16px;background:var(--soft);border-bottom:1px solid var(--line)">
    <b style="font-size:13.5px">Waiting to go out</b>
    <div class="muted" style="font-size:12.5px;margin-top:3px">
      Saving a lead here writes to this queue and returns. It never waits on Ads Pro, and never fails because Ads Pro is
      down — a form that saved perfectly well must not show an error about somebody else's server.
    </div>
    <?php if (!empty($outbox['WILL_RETRY']) || !empty($outbox['GIVEN_UP'])): ?>
      <div style="margin-top:6px;font-size:12.5px">
        <?php if (!empty($outbox['WILL_RETRY'])): ?>
          <b><?= (int)$outbox['WILL_RETRY'] ?></b> failed and will be tried again on the next sync — that is what a
          short outage at the other end looks like, and it needs nothing from you.
        <?php endif; ?>
        <?php if (!empty($outbox['GIVEN_UP'])): ?>
          <b><?= (int)$outbox['GIVEN_UP'] ?></b> have been tried <?= (int)ADS_RETRY_MAX ?> times and stopped. Those are
          not going to fix themselves — read the reason below.
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">
      <?php foreach ($qstatus as $k => $lbl): ?>
        <a class="btn small <?= $qf === $k ? '' : 'secondary' ?>" href="/adspro?q=<?= e($k) ?>">
          <?= e($lbl) ?><?= !empty($outbox[$k]) ? ' (' . (int)$outbox[$k] . ')' : '' ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php if (!$queue): ?>
    <div style="padding:14px 16px" class="muted">Nothing <?= e(strtolower($qstatus[$qf] ?? $qf)) ?>.</div>
  <?php else: ?>
    <div class="dt-scroll">
      <table class="dt">
        <caption class="sr-only">Outbound queue</caption>
        <thead><tr><th scope="col">What</th><th scope="col">Why</th><th scope="col">Queued</th><th scope="col">Result</th></tr></thead>
        <tbody>
        <?php foreach ($queue as $q): ?>
          <tr>
            <td><b><?= e($kinds[$q['kind']] ?? (string)$q['kind']) ?></b>
                <span class="muted">#<?= (int)$q['local_id'] ?></span></td>
            <td><?= e((string)$q['reason'] ?: '—') ?></td>
            <td style="white-space:nowrap"><?= trim((string)$q['queued_at']) !== '' ? e(fdate(substr((string)$q['queued_at'], 0, 10))) : '—' ?>
                <div class="muted" style="font-size:12px"><?= e((string)$q['queued_by'] ?: '') ?></div></td>
            <td>
              <?= (int)$q['attempts'] ? '<span class="muted" style="font-size:12px">' . (int)$q['attempts'] . ' attempt' . ((int)$q['attempts'] === 1 ? '' : 's') . '</span>' : '' ?>
              <?php if (trim((string)$q['last_error']) !== ''): ?>
                <div style="font-size:12px"><?= e((string)$q['last_error']) ?></div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="panel" style="margin-top:16px;padding:0;overflow:hidden">
  <div style="padding:12px 16px;background:var(--soft);border-bottom:1px solid var(--line)">
    <b style="font-size:13.5px">Every call made, and what came back</b>
    <div class="muted" style="font-size:12.5px;margin-top:3px">An integration without a log is an integration nobody can debug at the moment it stops working.</div>
  </div>
  <?php if (!$log): ?>
    <div style="padding:14px 16px" class="muted">Nothing has been attempted yet.</div>
  <?php else: ?>
    <div class="dt-scroll">
      <table class="dt">
        <caption class="sr-only">Ads Pro sync log</caption>
        <thead><tr><th scope="col">When</th><th scope="col">What</th><th scope="col">Result</th><th scope="col">Who</th></tr></thead>
        <tbody>
        <?php foreach ($log as $r): ?>
          <tr>
            <td style="white-space:nowrap"><?= trim((string)$r['ran_at']) !== '' ? e(fdate(substr((string)$r['ran_at'], 0, 10))) . ' ' . e(substr((string)$r['ran_at'], 11, 5)) : '—' ?></td>
            <td><?= e((string)$r['action']) ?></td>
            <td>
              <span class="pill <?= (int)$r['ok'] === 1 ? '' : 'bad' ?>"><?= (int)$r['ok'] === 1 ? 'ok' : 'failed' ?></span>
              <?= (int)$r['http_code'] ? '<span class="muted">HTTP ' . (int)$r['http_code'] . '</span>' : '' ?>
              <div class="muted" style="font-size:12px"><?= e((string)$r['summary']) ?></div>
            </td>
            <td><?= e((string)$r['ran_by'] ?: '—') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
