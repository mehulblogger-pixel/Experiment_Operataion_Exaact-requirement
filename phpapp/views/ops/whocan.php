<?php
// ============================================================================
//  WHO CAN DO WHAT — roles, people, branches and switches on one screen
//  (owner, 21 Nov 2026; approved proposal, pictures 5–8). Logic: lib/whocan.php.
//
//  Look and words only. A role is saved by the existing /access handler (same
//  field names as the old screen); a person by /who-can-person, which uses the
//  person form's own helper. One switch = one right; nothing here decides who
//  may do anything — the handlers and the gates do.
// ============================================================================
require_once __DIR__ . '/_rec_ui.php';
$ic = fn($n, $c = 's') => rec_icon($n, $c);
$tabs = [];
if ($canRoles)  $tabs['roles']   = 'Roles';
if ($canPeople) $tabs['people']  = 'People';
if ($canRoles)  $tabs['compare'] = 'Compare roles';
$tabs['switches'] = 'Company switches';
$tabs['plan']     = 'Plan';
$plural = fn($n, $one, $many) => $n . ' ' . ($n === 1 ? $one : $many);

//  One area block of switches. $on(key) = is it allowed now; $tag(row,on) = the
//  little label beside it; $field(key) = name/value attributes.
$renderTree = function (array $tree, callable $on, callable $tag, callable $field, array $openFeat = [], $openArea = 'Sales') use ($ic) {
    foreach ($tree as $area => $feats):
        $tot = 0; $yes = 0;
        foreach ($feats as $f) foreach ($f['rows'] as $r) if ($r['type'] === 'key') { $tot++; if ($on($r['key'])) $yes++; }
        $isSales = $area === 'Sales'; ?>
  <details class="wc-area<?= $isSales ? ' sales' : '' ?>" data-area="<?= e($area) ?>"<?= $area === $openArea ? ' open' : '' ?>>
    <summary class="wc-ahd">
      <span class="ai"><?= $ic(whocan_area_icon($area), '') ?></span>
      <span class="an"><?= e($area) ?></span>
      <span class="ac" data-count><?= $yes === 0 ? 'Nothing allowed' : ($yes . ' of ' . $tot . ' allowed') ?></span>
      <span class="chev" aria-hidden="true"></span>
    </summary>
    <div class="wc-feats">
    <?php foreach ($feats as $fi => $f):
        $ft = 0; $fy = 0;
        foreach ($f['rows'] as $r) if ($r['type'] === 'key') { $ft++; if ($on($r['key'])) $fy++; }
        $fid = 'wcf-' . substr(md5($area . '|' . $f['f']), 0, 8);
        $state = $fy === 0 ? 'false' : ($fy === $ft ? 'true' : 'mixed'); ?>
      <details class="wc-feat" id="<?= e($fid) ?>"<?= in_array($f['f'], $openFeat, true) ? ' open' : '' ?>>
        <summary class="wc-fhd">
          <span class="chev" aria-hidden="true"></span>
          <span class="fn"><b><?= e($f['f']) ?></b><?php if ($f['d'] !== ''): ?><small><?= e($f['d']) ?></small><?php endif; ?></span>
          <span class="fc" data-count><?= $fy ?> of <?= $ft ?></span>
          <span class="wc-fsw" role="switch" tabindex="0" aria-checked="<?= $state ?>" aria-label="Allow all of <?= e($f['f']) ?>" title="Switch all of <?= e($f['f']) ?> on or off"></span>
        </summary>
        <div class="wc-rows">
        <?php foreach ($f['rows'] as $r):
            if ($r['type'] === 'key'):
              $isOn = $on($r['key']); $t = $tag($r, $isOn); ?>
          <label class="wc-row" data-key="<?= e($r['key']) ?>"<?= $t['attrs'] ?? '' ?> data-text="<?= e(strtolower($area . ' ' . $f['f'] . ' ' . $r['label'] . ' ' . $r['help'])) ?>">
            <span class="rl"><span class="rn"><?= e($r['label']) ?></span><?php if ($r['help'] !== ''): ?><small><?= e($r['help']) ?></small><?php endif; ?></span>
            <span class="rt"><?= $t['html'] ?? '' ?></span>
            <span class="rs" data-st><?= $isOn ? 'Allowed' : 'Not allowed' ?></span>
            <input type="checkbox" class="wc-sw" role="switch" <?= $field($r['key']) ?><?= $isOn ? ' checked' : '' ?>
                   <?= $r['mod'] !== '' ? 'data-mod="' . e($r['mod']) . '" data-verb="' . e($r['verb']) . '"' : '' ?>
                   aria-label="<?= e($f['f'] . ' — ' . $r['label']) ?>">
          </label>
        <?php elseif ($r['type'] === 'rule'): ?>
          <div class="wc-row rule" data-text="<?= e(strtolower($area . ' ' . $f['f'] . ' ' . $r['label'] . ' ' . $r['help'])) ?>">
            <span class="rl"><span class="rn"><?= e($r['label']) ?></span><small><?= e($r['help']) ?></small></span>
            <span class="rt"><span class="wc-tag rule">Set by a rule</span></span>
          </div>
        <?php else: ?>
          <div class="wc-row dec" data-dec data-text="<?= e(strtolower($area . ' ' . $f['f'] . ' ' . $r['label'] . ' ' . $r['help'])) ?>">
            <span class="rl"><span class="rn"><?= e($r['label']) ?></span><small><?= e($r['help']) ?></small></span>
            <span class="rt"><span class="wc-tag dec">Needs your decision</span></span>
          </div>
        <?php endif; endforeach; ?>
        </div>
      </details>
    <?php endforeach; ?>
    </div>
  </details>
<?php endforeach;
};

//  The search box and the filter, above a tree.
$toolbar = function (array $filters) use ($ic) { ?>
  <div class="wc-tools">
    <label class="wc-srch"><?= $ic('eye') ?><span class="sr-only">Find a feature</span>
      <input type="search" class="form-control" data-wc-search placeholder="Find a feature — e.g. &quot;quotation&quot;, &quot;download&quot;, &quot;payments&quot;" autocomplete="off"></label>
    <div class="sxw-seg wc-filt" role="group" aria-label="Show">
      <?php $first = true; foreach ($filters as $k => $lbl): ?>
        <a href="#" data-wc-filter="<?= e($k) ?>" class="<?= $first ? 'on' : '' ?>" aria-pressed="<?= $first ? 'true' : 'false' ?>"><?= e($lbl) ?></a>
      <?php $first = false; endforeach; ?>
    </div>
  </div>
<?php };

$previewCard = function (array $lines, $title, $sub, array $history, $histTitle = 'Recent changes') use ($ic) { ?>
  <section class="rec-card wc-prev" id="wc-preview" aria-labelledby="wc-prev-h">
    <div class="rec-ch"><span class="ico"><?= $ic('eye', '') ?></span><h3 id="wc-prev-h"><?= e($title) ?></h3></div>
    <p class="wc-sub"><?= e($sub) ?></p>
    <ul class="wc-see">
      <?php foreach ($lines as [$ok, $txt]): ?>
        <li class="<?= $ok ? 'y' : 'n' ?>"><span class="mk" aria-hidden="true"><?= $ok ? '✓' : '✕' ?></span><span><span class="sr-only"><?= $ok ? 'Can: ' : 'Cannot: ' ?></span><?= e($txt) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <div class="wc-hist">
      <h4><?= e($histTitle) ?></h4>
      <?php if (!$history): ?><p class="muted" style="margin:0;font-size:12.5px">No change recorded yet.</p>
      <?php else: ?><ul><?php foreach ($history as $h): ?>
        <li><b><?= e(date('j M', strtotime($h['when']) ?: time())) ?></b> · <?= e($h['who']) ?> · <?= e($h['what']) ?></li>
      <?php endforeach; ?></ul><?php endif; ?>
      <p class="muted" style="margin:6px 0 0;font-size:12px">Every save is kept on the sealed audit trail.</p>
    </div>
  </section>
<?php };
?>
<div class="rec wc" data-wc data-tab="<?= e($tab) ?>">
  <div class="sxw-hd wc-hd">
    <div class="sxw-ttl">
      <div class="tile"><?= $ic('lock', '') ?></div>
      <div><h1>Who can do what</h1>
        <p>Pick a role or a person. Switch each feature on or off. The right-hand side says, in plain words, what they will see.</p></div>
    </div>
    <div class="wc-hd-acts">
      <?php if ($canRoles): ?><a class="btn secondary small" href="/who-can?tab=compare"><?= $ic('swap') ?> Compare two roles</a><?php endif; ?>
      <?php if ($canRoles): ?><a class="btn secondary small" href="/access<?= $tab === 'roles' && !empty($sel) ? '?role=' . e($sel) : '' ?>" title="The previous Roles &amp; permissions screen — the same switches, saved to the same place">Old layout</a><?php endif; ?>
    </div>
  </div>

  <nav class="wc-tabs" aria-label="Who can do what">
    <?php foreach ($tabs as $k => $lbl): ?>
      <a href="/who-can?tab=<?= e($k) ?>" class="<?= $tab === $k ? 'on' : '' ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>><?= e($lbl) ?></a>
    <?php endforeach; ?>
  </nav>

<?php if ($tab === 'roles'):
    $savedOn = fn($k) => in_array($k, $set, true);
    $peopleN = (int) ($counts[$base]['people'] ?? 0);
    $ownN    = (int) ($counts[$base]['own'] ?? 0);
    $recOn   = fn($k) => in_array($k, $recommended, true);
?>
  <div class="wc-grid">
    <aside class="wc-list" aria-label="Roles">
      <form method="get" action="/who-can" class="wc-pick">
        <input type="hidden" name="tab" value="roles">
        <label for="wc-role-sel" class="sr-only">Role</label>
        <select id="wc-role-sel" name="role" class="form-control" data-wc-autosubmit>
          <?php foreach ($roles as $k => $lbl): $n = (int) ($counts[$k]['people'] ?? 0); ?>
            <option value="<?= e($k) ?>"<?= $k === $sel ? ' selected' : '' ?>><?= e($lbl) ?> — <?= e($plural($n, 'person', 'people')) ?></option>
          <?php endforeach; ?>
        </select>
        <noscript><button class="btn small" type="submit">Open</button></noscript>
      </form>
      <div class="wc-lhd">Roles · <?= count($roles) ?></div>
      <ul class="wc-roles">
        <?php foreach ($roles as $k => $lbl): $n = (int) ($counts[$k]['people'] ?? 0);
              $b = function_exists('role_effective_key') ? role_effective_key($k) : $k; ?>
          <li><a href="/who-can?tab=roles&amp;role=<?= e($k) ?>" class="<?= $k === $sel ? 'on' : '' ?>"<?= $k === $sel ? ' aria-current="true"' : '' ?>>
            <span class="n"><?= e($lbl) ?><?php if ($b !== $k): ?><small>based on <?= e(role_name($b)) ?></small><?php endif; ?></span>
            <span class="c"><?= e($plural($n, 'person', 'people')) ?></span></a></li>
        <?php endforeach; ?>
      </ul>
      <p class="wc-lfoot">A new role is made on <a href="/user-new">Add a person</a> → “+ Add a role”, copied from one of these.</p>
    </aside>

    <div class="wc-main">
      <section class="rec-card wc-who">
        <div class="wc-who-hd">
          <div class="min0">
            <h2><?= e($roles[$sel]) ?></h2>
            <p class="muted"><?= e(role_blurb($base) ?: 'A role this company added.') ?></p>
            <p class="wc-reach"><?= $ic('users') ?> <b><?= e($plural($peopleN, 'person', 'people')) ?></b> in this role<?= $ownN ? ' · ' . e($plural($ownN, 'has', 'have')) . ' their own exceptions, which stay as they are' : '' ?>.</p>
          </div>
          <?php if (!$isCustom): ?>
          <div class="wc-who-acts">
            <form method="get" action="/who-can" class="wc-copy">
              <input type="hidden" name="tab" value="roles"><input type="hidden" name="role" value="<?= e($sel) ?>">
              <label for="wc-from" class="sr-only">Copy the switches of</label>
              <select id="wc-from" name="from" class="form-control">
                <option value="">Copy from role…</option>
                <?php foreach ($roles as $k => $lbl): if ($k === $sel) continue; ?><option value="<?= e($k) ?>"<?= $k === $from ? ' selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
              </select>
              <button class="btn secondary small" type="submit"><?= $ic('copy') ?> Copy</button>
            </form>
            <form method="post" action="/access" data-wc-confirm="Apply the recommended switches for <?= e($roles[$sel]) ?>? This saves them straight away for <?= e($plural($peopleN, 'person', 'people')) ?>.">
              <input type="hidden" name="role" value="<?= e($sel) ?>"><input type="hidden" name="_do" value="preset"><input type="hidden" name="_back" value="who">
              <button class="btn secondary small" type="submit"><?= $ic('spark') ?> Reset to recommended</button>
            </form>
            <form method="post" action="/access" data-wc-confirm="Put <?= e($roles[$sel]) ?> back to the built-in default? Your company's changes to this role are removed.">
              <input type="hidden" name="role" value="<?= e($sel) ?>"><input type="hidden" name="_do" value="reset"><input type="hidden" name="_back" value="who">
              <button class="btn secondary small" type="submit">Built-in default</button>
            </form>
          </div>
          <?php endif; ?>
        </div>
        <div class="wc-facts">
          <div><span class="k">Branches they see</span><b><?= ($scope['offices'] ?? 'OWN') === 'ALL' ? 'Every ' . e(Tl('office')) : 'Their own ' . e(Tl('office')) ?></b><small>Set for each person under People</small></div>
          <div><span class="k"><?= e(THP('sbu')) ?> they see</span><b><?= ($scope['sbus'] ?? 'OWN') === 'ALL' ? 'All' : 'Their own' ?></b><small>Set for each person under People</small></div>
        </div>
      </section>

      <?php if ($isCustom): ?>
        <div class="wc-note info" role="note"><?= $ic('alert') ?><span><b><?= e($roles[$sel]) ?></b> is a role this company added. It uses the switches of
          <b><?= e(role_name($base)) ?></b> — change them there: <a href="/who-can?tab=roles&amp;role=<?= e($base) ?>">open <?= e(role_name($base)) ?></a>.
          A person in it can still be given their own exceptions under People.</span></div>
      <?php endif; ?>
      <?php if ($from !== ''): ?>
        <div class="wc-note warn" role="status"><?= $ic('copy') ?><span>Showing the switches of <b><?= e($roles[$from]) ?></b>, copied onto <b><?= e($roles[$sel]) ?></b>.
          <b>Nothing is saved</b> until you press Save. <a href="/who-can?tab=roles&amp;role=<?= e($sel) ?>">Cancel the copy</a></span></div>
      <?php endif; ?>

      <?php $toolbar(['all' => 'All', 'on' => 'Allowed', 'off' => 'Not allowed', 'dec' => 'Needs decision']); ?>
      <a class="wc-jump" href="#wc-preview"><?= $ic('eye') ?> What they will see</a>

      <form method="post" action="/access" id="wc-form" class="wc-form" data-wc-form>
        <input type="hidden" name="role" value="<?= e($sel) ?>">
        <input type="hidden" name="_back" value="who">
        <fieldset<?= $isCustom ? ' disabled' : '' ?> class="wc-fs">
        <legend class="sr-only">Switches for <?= e($roles[$sel]) ?></legend>
        <?php if (function_exists('sales_rec_role_mode')): ?>
          <section class="rec-card wc-recbox"><?= whocan_records_ctl('role', sales_rec_role_mode($sel)) ?></section>
        <?php endif; ?>
        <?php $renderTree($tree, $savedOn,
            function ($r, $isOn) use ($recOn) {
                $rec = $recOn($r['key']);
                $html = '';
                if ($isOn && !$rec) $html = '<span class="wc-tag muted">Not in the recommended set</span>';
                if (!$isOn && $rec) $html = '<span class="wc-tag info">Recommended</span>';
                return ['html' => $html, 'attrs' => ' data-rec="' . ($rec ? '1' : '0') . '"'];
            },
            fn($k) => 'name="perms[' . e($k) . ']" value="1"'); ?>
        </fieldset>
        <?php if (!$isCustom): ?>
        <div class="wc-save">
          <span class="wc-dirty" data-wc-dirty aria-live="polite">Nothing changes until you press Save. Every save is written to the history.</span>
          <button type="reset" class="btn secondary">Undo changes</button>
          <button type="submit" class="btn">Save for <?= e($plural($peopleN, 'person', 'people')) ?></button>
        </div>
        <?php endif; ?>
      </form>
    </div>

    <aside class="wc-side">
      <?php $previewCard($preview, 'What a ' . $roles[$sel] . ' will see', 'Worked out from this role\'s saved switches, the plan and the company switches. A person\'s own exceptions and branches are shown on People.', $history); ?>
    </aside>
  </div>

<?php elseif ($tab === 'people'): ?>
  <div class="wc-grid">
    <aside class="wc-list" aria-label="People">
      <form method="get" action="/who-can" class="wc-psrch">
        <input type="hidden" name="tab" value="people">
        <label for="wc-q" class="sr-only">Find a person</label>
        <input id="wc-q" type="search" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Find a person">
      </form>
      <?php if (!empty($user)): ?>
      <form method="get" action="/who-can" class="wc-pick">
        <input type="hidden" name="tab" value="people">
        <label for="wc-person-sel" class="sr-only">Person</label>
        <select id="wc-person-sel" name="id" class="form-control" data-wc-autosubmit>
          <?php foreach ($people as $p): ?>
            <option value="<?= (int) $p['id'] ?>"<?= (int) $p['id'] === (int) $user['id'] ? ' selected' : '' ?>><?= e(whocan_person_name($p)) ?> — <?= e(role_name((string) $p['role'])) ?><?= empty($p['is_active']) ? ' (off)' : '' ?></option>
          <?php endforeach; ?>
        </select>
        <noscript><button class="btn small" type="submit">Open</button></noscript>
      </form>
      <?php endif; ?>
      <div class="wc-lhd">People · <?= count($people) ?><?= $globalMgr ? '' : ' in your ' . e(Tl('office')) ?></div>
      <ul class="wc-roles wc-people">
        <?php foreach ($people as $p): $own = function_exists('user_has_own_access') && user_has_own_access($p); ?>
          <li><a href="/who-can?tab=people&amp;id=<?= (int) $p['id'] ?><?= $q !== '' ? '&amp;q=' . e(urlencode($q)) : '' ?>" class="<?= !empty($user) && (int) $p['id'] === (int) $user['id'] ? 'on' : '' ?><?= empty($p['is_active']) ? ' off' : '' ?>">
            <span class="n"><?= e(whocan_person_name($p)) ?><small><?= e(role_name((string) $p['role'])) ?><?= !empty($p['home_office_name']) ? ' · ' . e($p['home_office_name']) : '' ?></small></span>
            <span class="c"><?= empty($p['is_active']) ? 'off' : ($own ? 'own' : '') ?></span></a></li>
        <?php endforeach; ?>
        <?php if (!$people): ?><li class="muted" style="padding:10px">Nobody matches.</li><?php endif; ?>
      </ul>
      <p class="wc-lfoot">“own” = has exceptions to their role. Names, passwords and roles are changed on <a href="/users">People</a>.</p>
    </aside>

    <div class="wc-main">
    <?php if (empty($user)): ?>
      <section class="rec-card"><p style="margin:0">Pick a person on the left.</p></section>
    <?php elseif (whocan_is_master_row($user)): ?>
      <section class="rec-card wc-who"><h2><?= e(whocan_person_name($user)) ?></h2>
        <p class="muted" style="margin:4px 0 0">Master Admin — always has every right in every <?= e(Tl('office')) ?>. There is nothing to switch.</p></section>
    <?php else:
        $roleOn = fn($k) => in_array($k, $roleSet, true);
        $curOn  = fn($k) => in_array($k, $set, true);
        $exc = 0; $openFeat = [];
        foreach ($tree as $feats) foreach ($feats as $f) foreach ($f['rows'] as $r)
            if ($r['type'] === 'key' && $curOn($r['key']) !== $roleOn($r['key'])) { $exc++; $openFeat[] = $f['f']; }
        $openFeat = array_values(array_unique($openFeat));
        $sw = $scopeWords;
        $scOffRaw = trim((string) ($user['scope_offices'] ?? ''));
        $offSel = array_filter(array_map('trim', explode(',', $scOffRaw)));
        $offAll = in_array('ALL', array_map('strtoupper', $offSel), true);
        $scSbuRaw = trim((string) ($user['scope_sbus'] ?? ''));
        $sbuSel = array_filter(array_map('trim', explode(',', $scSbuRaw)));
        $sbuAll = $scSbuRaw === '' || in_array('ALL', array_map('strtoupper', $sbuSel), true);
        $opsOn = !function_exists('licence_enabled') || licence_enabled('operations');
    ?>
      <form method="post" action="/who-can-person?id=<?= (int) $user['id'] ?>" id="wc-form" class="wc-form" data-wc-form>
      <section class="rec-card wc-who">
        <div class="wc-who-hd">
          <div class="min0">
            <h2><?= e(whocan_person_name($user)) ?><?= empty($user['is_active']) ? ' <span class="wc-tag muted">login switched off</span>' : '' ?></h2>
            <p class="muted"><?= e(role_name($role)) ?><?= !empty($user['home_office_id']) ? ' · ' . e((string) ops_val("SELECT name FROM offices WHERE id=?", [(int) $user['home_office_id']])) : '' ?> · <?= e((string) $user['username']) ?></p>
          </div>
          <div class="wc-who-acts">
            <a class="btn secondary small" href="/user-edit?id=<?= (int) $user['id'] ?>"><?= $ic('pen') ?> Name, role, password</a>
          </div>
        </div>
        <div class="wc-note <?= $exc ? 'warn' : 'ok' ?>" role="status" data-wc-exc-note>
          <?= $ic($exc ? 'alert' : 'checkc') ?>
          <span><?php if ($from !== ''): ?>Showing the switches of the <b><?= e(role_name($from)) ?></b> role for this person. <b>Nothing is saved</b> until you press Save. <a href="/who-can?tab=people&amp;id=<?= (int) $user['id'] ?>">Cancel</a>
          <?php elseif ($exc): ?><b>Follows the <?= e(role_name($role)) ?> role, with <?= e($plural($exc, 'exception', 'exceptions')) ?>.</b> Everything marked “From role” changes when the role changes; the exceptions stay until you reset them.
          <?php else: ?><b>Follows the <?= e(role_name($role)) ?> role exactly.</b> A change to the role reaches this person straight away.<?php endif; ?></span>
          <?php if ($exc && $globalMgr && $from === ''): ?>
            <button class="btn secondary small" type="submit" name="reset_perms" value="1" data-wc-confirm="Give <?= e(whocan_person_name($user)) ?> exactly the <?= e(role_name($role)) ?> role again? The exceptions are removed.">Reset to the role</button>
          <?php endif; ?>
        </div>
        <div class="wc-facts">
          <div><span class="k">Role</span><b><?= e(role_name($role)) ?></b><small><a href="/user-edit?id=<?= (int) $user['id'] ?>">change</a> · <a href="/who-can?tab=roles&amp;role=<?= e($role) ?>">see the role</a></small></div>
          <div class="wide"><span class="k"><?= e(THP('office')) ?> they see</span>
          <?php if ($globalMgr): ?>
            <label class="wc-chk"><input type="checkbox" name="scope_offices_all" value="1" data-wc-all="wc-offs"<?= $offAll ? ' checked' : '' ?>> <b>Every <?= e(Tl('office')) ?></b> — now and any added later</label>
            <div class="wc-chips" id="wc-offs">
              <?php foreach ($offices as $o): ?>
                <label class="wc-chk"><input type="checkbox" name="scope_offices[]" value="<?= (int) $o['id'] ?>"<?= in_array((string) $o['id'], $offSel, true) ? ' checked' : '' ?>> <?= e($o['name']) ?></label>
              <?php endforeach; ?>
            </div>
            <small>None ticked = their home <?= e(Tl('office')) ?> only. Now: <?= e($sw['offices']) ?>.</small>
          <?php else: ?>
            <b><?= e($sw['offices']) ?></b>
            <small>Only a company-wide user manager can change <?= e(Tlp('office')) ?>. Saving here — as on the person form — keeps them on their own <?= e(Tl('office')) ?>.</small>
          <?php endif; ?>
          </div>
          <?php if (function_exists('sales_rec_user_override')):
              $recCur = sales_rec_user_override((int) $user['id']);
              if ($recCur === 'branch' && $offAll) $recCur = 'all'; ?>
          <div class="wide"><?= whocan_records_ctl('person', $recCur, sales_rec_role_mode($role), $globalMgr) ?></div>
          <?php endif; ?>
          <?php if ($globalMgr && $opsOn): ?>
          <div class="wide"><span class="k"><?= e(THP('sbu')) ?> they see</span>
            <label class="wc-chk"><input type="checkbox" name="scope_sbus_all" value="1" data-wc-all="wc-sbus"<?= $sbuAll ? ' checked' : '' ?>> <b>Every <?= e(Tl('sbu')) ?></b></label>
            <div class="wc-chips" id="wc-sbus">
              <?php foreach ($sbuOpts as $code => $lbl): ?>
                <label class="wc-chk"><input type="checkbox" name="scope_sbus[]" value="<?= e($code) ?>"<?= in_array((string) $code, $sbuSel, true) ? ' checked' : '' ?>> <?= e($lbl) ?></label>
              <?php endforeach; ?>
            </div>
          </div>
          <?php elseif ($globalMgr): ?>
            <input type="hidden" name="scope_sbus_all" value="1">
          <?php else: ?>
          <div><span class="k"><?= e(THP('sbu')) ?> they see</span><b><?= e($sw['sbus']) ?></b></div>
          <?php endif; ?>
        </div>
        <div class="wc-copyrow">
          <label for="wc-from-p" class="sr-only">Start from the switches of a role</label>
          <select id="wc-from-p" class="form-control" data-wc-from="/who-can?tab=people&amp;id=<?= (int) $user['id'] ?>&amp;from=">
            <option value="">Copy from role…</option>
            <?php foreach ((function_exists('roles_for_licence') ? roles_for_licence() : ORG_ROLES) as $k => $lbl): if ($k === 'MASTER_ADMIN') continue; ?><option value="<?= e($k) ?>"<?= $k === $from ? ' selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
          </select>
          <noscript><small>Copying needs JavaScript; set the switches below instead.</small></noscript>
        </div>
      </section>

      <?php $toolbar(['all' => 'All', 'exc' => 'Only the exceptions', 'on' => 'Allowed', 'off' => 'Not allowed']); ?>
      <a class="wc-jump" href="#wc-preview"><?= $ic('eye') ?> What they will see</a>
      <?php if (!$globalMgr): ?><p class="wc-sub" style="margin:0 0 8px">You can switch only what a branch user manager may hand out. Everything else this person has stays exactly as it is.</p><?php endif; ?>

      <?php $renderTree($tree, $curOn,
          function ($r, $isOn) use ($roleOn) {
              $base = $roleOn($r['key']);
              $html = $isOn === $base ? '<span class="wc-tag muted">From role</span>' : '<span class="wc-tag warn">Changed for this person</span>';
              return ['html' => $html, 'attrs' => ' data-base="' . ($base ? '1' : '0') . '"'];
          },
          fn($k) => 'name="permissions[]" value="' . e($k) . '"', $openFeat); ?>
        <div class="wc-save">
          <span class="wc-dirty" data-wc-dirty aria-live="polite">Saved changes are written to the history with who made them and when.</span>
          <button type="reset" class="btn secondary">Undo changes</button>
          <button type="submit" class="btn">Save for <?= e(whocan_person_name($user)) ?></button>
        </div>
      </form>
    <?php endif; ?>
    </div>

    <aside class="wc-side">
      <?php if (!empty($user) && !whocan_is_master_row($user)) $previewCard($preview, 'What ' . whocan_person_name($user) . ' will see',
            'Worked out from everything that applies — role, exceptions, ' . Tlp('office') . ', plan and company switches — by asking the same checks the screens ask. Shows what is saved.', $history); ?>
    </aside>
  </div>

<?php elseif ($tab === 'compare'):
    $onA = fn($k) => in_array($k, $setA, true); $onB = fn($k) => in_array($k, $setB, true);
    $diff = []; $same = 0;
    foreach ($tree as $area => $feats) foreach ($feats as $f) foreach ($f['rows'] as $r) {
        if ($r['type'] !== 'key') continue;
        if ($onA($r['key']) === $onB($r['key'])) { $same++; continue; }
        $diff[] = [$area, $f['f'], $r['label'], $onA($r['key']), $onB($r['key'])];
    } ?>
  <section class="rec-card">
    <form method="get" action="/who-can" class="wc-cmp">
      <input type="hidden" name="tab" value="compare">
      <label><span class="k">Role</span><select name="a" class="form-control"><?php foreach ($roles as $k => $l): ?><option value="<?= e($k) ?>"<?= $k === $a ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
      <span class="vs">and</span>
      <label><span class="k">Role</span><select name="b" class="form-control"><?php foreach ($roles as $k => $l): ?><option value="<?= e($k) ?>"<?= $k === $b ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
      <button class="btn" type="submit">Compare</button>
    </form>
    <p class="wc-sub" style="margin:10px 0 0"><b><?= e($plural(count($diff), 'difference', 'differences')) ?></b> · the same in both: <?= (int) $same ?>.</p>
  </section>
  <?php if ($diff): ?>
  <section class="rec-card" style="padding:0;overflow:hidden">
    <div class="tbl-scroll"><table class="dt wc-cmpt">
      <caption class="sr-only">Where <?= e($roles[$a]) ?> and <?= e($roles[$b]) ?> differ</caption>
      <thead><tr><th scope="col">Area · feature</th><th scope="col">Sub-feature</th><th scope="col"><?= e($roles[$a]) ?></th><th scope="col"><?= e($roles[$b]) ?></th></tr></thead>
      <tbody>
      <?php foreach ($diff as [$ar, $fe, $lb, $x, $y]): ?>
        <tr><td><?= e($ar) ?> · <?= e($fe) ?></td><td><?= e($lb) ?></td>
          <td><span class="wc-tag <?= $x ? 'ok' : 'muted' ?>"><?= $x ? 'Allowed' : 'Not allowed' ?></span></td>
          <td><span class="wc-tag <?= $y ? 'ok' : 'muted' ?>"><?= $y ? 'Allowed' : 'Not allowed' ?></span></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
  <?php endif; ?>
  <p class="muted" style="font-size:12.5px">Open either role to change it: <a href="/who-can?tab=roles&amp;role=<?= e($a) ?>"><?= e($roles[$a]) ?></a> · <a href="/who-can?tab=roles&amp;role=<?= e($b) ?>"><?= e($roles[$b]) ?></a></p>

<?php elseif ($tab === 'switches'): ?>
  <section class="rec-card">
    <div class="rec-ch"><span class="ico"><?= $ic('flow', '') ?></span><h3>Company switches</h3></div>
    <p style="margin:0 0 10px">A company switch turns a whole module or screen <b>on or off for everybody</b>. The switches on Roles and People then decide <b>who</b>, among everybody, may use what is switched on.</p>
    <ul class="wc-see">
      <li class="<?= $switches['modules_off'] ? 'n' : 'y' ?>"><span class="mk" aria-hidden="true"><?= $switches['modules_off'] ? '✕' : '✓' ?></span><span><?= $switches['modules_off'] ? 'Modules switched off: ' . e(implode(', ', $switches['modules_off'])) : 'Every module in your plan is switched on' ?></span></li>
      <li class="<?= $switches['features_off'] ? 'n' : 'y' ?>"><span class="mk" aria-hidden="true"><?= $switches['features_off'] ? '✕' : '✓' ?></span><span><?= $switches['features_off'] ? e($plural((int) $switches['features_off'], 'screen is', 'screens are')) . ' switched off for everybody' : 'No screen is switched off' ?></span></li>
    </ul>
    <?php if ($canSwitches): ?><p style="margin:12px 0 0"><a class="btn" href="/workspace/setup/modules">Open Modules &amp; features</a></p>
    <?php else: ?><p class="muted" style="margin:12px 0 0">Changed by an administrator with company settings, on Modules &amp; features.</p><?php endif; ?>
  </section>

<?php else: ?>
  <section class="rec-card">
    <div class="rec-ch"><span class="ico"><?= $ic('tag', '') ?></span><h3>Your plan</h3></div>
    <p style="margin:0 0 10px">Which modules this company has. Nobody — not even a Master Admin — can use a module the plan does not include. Read-only here.</p>
    <ul class="wc-plan">
      <?php foreach ($plan as $p): ?>
        <li><span class="wc-tag <?= $p['on'] ? 'ok' : 'muted' ?>"><?= $p['on'] ? 'In your plan' : 'Not in your plan' ?></span><b><?= e($p['name']) ?></b><small><?= e($p['desc']) ?></small></li>
      <?php endforeach; ?>
    </ul>
    <p class="muted" style="margin:10px 0 0;font-size:12.5px">To add a module, see <a href="/subscription">Your subscription</a> or contact your provider.</p>
  </section>
<?php endif; ?>
</div>

<script>
(function () {
  var root = document.querySelector('[data-wc]'); if (!root) return;
  var IMPLIES = <?= json_encode(PERM_VERB_IMPLIES) ?>;
  var form = root.querySelector('[data-wc-form]');
  var boxes = form ? [].slice.call(form.querySelectorAll('input.wc-sw')) : [];
  var start = boxes.map(function (b) { return b.checked; });

  //  The weaker verbs follow a stronger one, exactly as the old grid does
  //  (_perm_verb_grid_assets.php): ticking Delete ticks Edit and View; unticking
  //  View unticks the verbs that need it. The save closes the set again anyway.
  function cascade(b) {
    if (!b.dataset.mod) return;
    var row = {};
    boxes.forEach(function (x) { if (x.dataset.mod === b.dataset.mod) row[x.dataset.verb] = x; });
    var v = b.dataset.verb;
    if (b.checked) (IMPLIES[v] || []).forEach(function (w) { if (row[w]) row[w].checked = true; });
    else Object.keys(IMPLIES).forEach(function (s) { if (IMPLIES[s].indexOf(v) >= 0 && row[s]) row[s].checked = false; });
  }
  function refresh() {
    var changed = 0;
    boxes.forEach(function (b, i) {
      if (b.checked !== start[i]) changed++;
      var row = b.closest('.wc-row'); if (!row) return;
      row.querySelector('[data-st]').textContent = b.checked ? 'Allowed' : 'Not allowed';
      row.classList.toggle('is-on', b.checked);
      var tag = row.querySelector('.rt');
      if (row.hasAttribute('data-base')) {
        var base = row.getAttribute('data-base') === '1';
        tag.innerHTML = b.checked === base ? '<span class="wc-tag muted">From role</span>' : '<span class="wc-tag warn">Changed for this person</span>';
        row.classList.toggle('is-exc', b.checked !== base);
      } else if (row.hasAttribute('data-rec')) {
        var rec = row.getAttribute('data-rec') === '1';
        tag.innerHTML = b.checked && !rec ? '<span class="wc-tag muted">Not in the recommended set</span>' : (!b.checked && rec ? '<span class="wc-tag info">Recommended</span>' : '');
      }
    });
    [].slice.call(root.querySelectorAll('.wc-feat')).forEach(function (f) {
      var bs = [].slice.call(f.querySelectorAll('input.wc-sw')), on = bs.filter(function (b) { return b.checked; }).length;
      var c = f.querySelector('.wc-fhd [data-count]'); if (c) c.textContent = on + ' of ' + bs.length;
      var sw = f.querySelector('.wc-fsw'); if (sw) sw.setAttribute('aria-checked', on === 0 ? 'false' : (on === bs.length ? 'true' : 'mixed'));
    });
    [].slice.call(root.querySelectorAll('.wc-area')).forEach(function (a) {
      var bs = [].slice.call(a.querySelectorAll('input.wc-sw')), on = bs.filter(function (b) { return b.checked; }).length;
      var c = a.querySelector('.wc-ahd [data-count]'); if (c) c.textContent = on === 0 ? 'Nothing allowed' : on + ' of ' + bs.length + ' allowed';
    });
    var d = root.querySelector('[data-wc-dirty]');
    if (d) {
      if (!d.dataset.idle) d.dataset.idle = d.textContent;
      d.textContent = changed ? (changed === 1 ? '1 switch changed — not saved yet.' : changed + ' switches changed — not saved yet.') : d.dataset.idle;
      d.classList.toggle('on', changed > 0);
    }
    window.__wcDirty = changed > 0;
  }
  boxes.forEach(function (b) { b.addEventListener('change', function () { cascade(b); refresh(); }); });
  if (form) form.addEventListener('reset', function () { setTimeout(refresh, 0); });
  if (form) form.addEventListener('submit', function () { window.__wcDirty = false; });
  window.addEventListener('beforeunload', function (e) { if (window.__wcDirty) { e.preventDefault(); e.returnValue = ''; } });

  //  A feature's own switch: all of it on, or (when all on) all of it off.
  function flipFeature(sw) {
    var f = sw.closest('.wc-feat'); if (!f) return;
    var bs = [].slice.call(f.querySelectorAll('input.wc-sw:not(:disabled)'));
    var allOn = bs.every(function (b) { return b.checked; });
    bs.forEach(function (b) { b.checked = !allOn; });
    bs.forEach(cascade);
    refresh();
  }
  root.addEventListener('click', function (e) {
    var sw = e.target.closest && e.target.closest('.wc-fsw');
    if (!sw) return;
    e.preventDefault();                 // keep the feature open or closed as it was
    if (sw.closest('fieldset:disabled')) return;
    flipFeature(sw);
  });
  root.addEventListener('keydown', function (e) {
    var sw = e.target.closest && e.target.closest('.wc-fsw');
    if (!sw || (e.key !== ' ' && e.key !== 'Enter')) return;
    e.preventDefault(); if (sw.closest('fieldset:disabled')) return; flipFeature(sw);
  });

  //  Find and filter. Matching features open; a feature with nothing to show hides.
  var q = root.querySelector('[data-wc-search]'), mode = 'all';
  function apply() {
    var t = q ? q.value.trim().toLowerCase() : '';
    [].slice.call(root.querySelectorAll('.wc-area')).forEach(function (a) {
      var anyA = false;
      [].slice.call(a.querySelectorAll('.wc-feat')).forEach(function (f) {
        var anyF = false;
        [].slice.call(f.querySelectorAll('.wc-row')).forEach(function (r) {
          var b = r.querySelector('input.wc-sw');
          var ok = !t || (r.getAttribute('data-text') || '').indexOf(t) >= 0;
          if (ok && mode === 'on')  ok = !!b && b.checked;
          if (ok && mode === 'off') ok = !!b && !b.checked;
          if (ok && mode === 'dec') ok = r.hasAttribute('data-dec');
          if (ok && mode === 'exc') ok = r.classList.contains('is-exc');
          r.hidden = !ok; if (ok) anyF = true;
        });
        f.hidden = !anyF; if (anyF) anyA = true;
        if ((t || mode !== 'all') && anyF) f.open = true;
      });
      a.hidden = !anyA;
      if ((t || mode !== 'all') && anyA) a.open = true;
    });
  }
  if (q) q.addEventListener('input', apply);
  [].slice.call(root.querySelectorAll('[data-wc-filter]')).forEach(function (l) {
    l.addEventListener('click', function (e) {
      e.preventDefault(); mode = l.getAttribute('data-wc-filter');
      [].slice.call(root.querySelectorAll('[data-wc-filter]')).forEach(function (x) { x.classList.toggle('on', x === l); x.setAttribute('aria-pressed', x === l ? 'true' : 'false'); });
      apply();
    });
  });

  //  Small conveniences: pick a role/person and go; copy from a role; confirm
  //  the buttons that save straight away; "every branch" greys the list.
  [].slice.call(root.querySelectorAll('[data-wc-autosubmit]')).forEach(function (s) { s.addEventListener('change', function () { s.form.submit(); }); });
  [].slice.call(root.querySelectorAll('[data-wc-from]')).forEach(function (s) {
    s.addEventListener('change', function () { if (s.value) location.href = s.getAttribute('data-wc-from') + encodeURIComponent(s.value); });
  });
  [].slice.call(root.querySelectorAll('[data-wc-confirm]')).forEach(function (el) {
    var ask = function (e) {
      if (el.dataset.ok === '1') return;
      e.preventDefault();
      var bar = document.createElement('div'); bar.className = 'wc-confirm'; bar.setAttribute('role', 'alertdialog');
      bar.innerHTML = '<span></span><button type="button" class="btn small">Yes, go ahead</button><button type="button" class="btn secondary small">Cancel</button>';
      bar.firstChild.textContent = el.getAttribute('data-wc-confirm');
      var host = el.tagName === 'FORM' ? el : el.closest('.wc-note') || el.parentNode;
      host.appendChild(bar);
      bar.querySelector('.btn:not(.secondary)').addEventListener('click', function () {
        el.dataset.ok = '1'; window.__wcDirty = false;
        if (el.tagName === 'FORM') el.submit(); else el.click();
      });
      bar.querySelector('.btn.secondary').addEventListener('click', function () { bar.remove(); });
      bar.querySelector('.btn').focus();
    };
    if (el.tagName === 'FORM') el.addEventListener('submit', ask); else el.addEventListener('click', ask);
  });
  [].slice.call(root.querySelectorAll('[data-wc-all]')).forEach(function (a) {
    var l = document.getElementById(a.getAttribute('data-wc-all'));
    function sync() { if (l) l.classList.toggle('dim', a.checked); }
    a.addEventListener('change', sync); sync();
  });
  refresh();
})();
</script>
