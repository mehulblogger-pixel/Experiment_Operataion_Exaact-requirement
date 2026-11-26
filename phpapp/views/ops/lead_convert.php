<?php
// Lead → customer (owner, 21 Nov 2026: Sales redesign Stage 2, approved design
// s2-convert). The same boxes and the same save (lead_convert() in lib/leads.php),
// with the client list searchable, the source in words, and a plain list of what
// happens when Yes is pressed. The old page is kept in
// tests/fixtures/pre_stage2/lead_convert.php.
require_once __DIR__ . '/_sxw_ui.php';
$src = function_exists('lead_source_label') ? lead_source_label($l['source']) : (string)$l['source'];
//  Already converted (owner test LC-01): say so, and link to what it became.
if (!empty($done)):
  $dp = $done['partner'] ?? null; $di = $done['inquiry'] ?? null;
?>
<div class="rec sxw has-bn sxl sxl-form">
  <?php sxw_nav('/leads'); ?>
  <?php sxw_head($l['company_name'] . ' is already a customer', $l['ref'] . ' · This lead was converted' . (!empty($l['converted_at']) ? ' on ' . e(date('d M Y', strtotime((string) $l['converted_at']))) : '') . '. Nothing more to do here.', 'swap'); ?>
  <div class="sxl-ban ok" role="status"><span class="ic"><?= rec_icon('check') ?></span>
    <div class="tx"><b class="t">Already a customer.</b>
      <?php if ($dp): ?><a href="/client?id=<?= (int) $dp['id'] ?>">Open the <?= e(Tl('client')) ?> — <?= e($dp['display_name'] ?: $dp['legal_name']) ?></a><?php endif; ?>
      <?php if ($dp && $di): ?> · <?php endif; ?>
      <?php if ($di): ?><a href="/inquiry?id=<?= (int) $di['id'] ?>">Open the <?= e(Tl('inquiry')) ?> <?= e((string) ($di['inquiry_no'] ?? '')) ?></a><?php endif; ?>
      · <a href="/lead?id=<?= (int) $l['id'] ?>">Back to the lead</a></div>
  </div>
</div>
<?php return; endif; ?>
<div class="rec sxw has-bn sxl sxl-form">
  <?php sxw_nav('/leads'); ?>
  <?php sxw_head('They said yes — make ' . $l['company_name'] . ' a customer',
      $l['ref'] . ' · Check the details and press the button. ' . $l['company_name'] . ' goes onto your customer list and an ' . Tl('inquiry') . ' is raised for what they want — everything comes from the lead, nothing is typed again.', 'swap'); ?>

<?php if ($dup && $dup['kind'] === 'partner'): ?>
  <div class="sxl-ban warn" role="status"><span class="ic"><?= rec_icon('alert') ?></span>
    <div class="tx"><b class="t">They may already be on the master.</b>
      <?= e($dup['row']['display_name'] ?? $dup['row']['legal_name']) ?> matched on <?= e($dup['by']) ?>.
      Attach the lead to them below rather than creating a second record. An exact match is refused; a similar one is allowed and noted in the <?= e(Tl('client')) ?>'s history.</div>
  </div>
<?php endif; ?>

<div class="sxl-fg">
<form method="post" action="/lead-convert" class="sxl-main">
  <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
  <input type="hidden" name="stage_id" value="<?= (int)$stage_id ?>">

  <section class="rec-card">
    <div class="rec-ch"><span class="ico"><?= rec_icon('building') ?></span><h3>The customer</h3></div>
    <div class="form-grid">
      <div class="ff ff-wide"><label for="cv-partner">Attach to a <?= e(Tl('client')) ?> we already have <?php if (!function_exists('partner_can_add') || partner_can_add()): ?><a href="#" class="addlink" data-qa="client" data-target="select[name='partner_id']">+ Add new</a><?php endif; ?></label>
        <select class="form-control searchable" id="cv-partner" name="partner_id">
          <option value="">— create a new one —</option>
          <?php foreach ($clients as $c): ?>
            <option value="<?= (int)$c['id'] ?>"<?= (int)$l['partner_id'] === (int)$c['id'] ? ' selected' : '' ?>>
              <?= e($c['display_name'] ?: $c['legal_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="ff-help">Type to search. Leave it on “create a new one” to make a new <?= e(Tl('client')) ?> from the name below.</div></div>
      <div class="ff ff-wide"><label for="cv-name">…or the name for the new one</label>
        <input class="form-control" id="cv-name" name="company_name" value="<?= e($l['company_name']) ?>" maxlength="200"></div>
      <?php //  Owner, 26 Nov 2026 (J1-05): the GSTIN / PAN boxes belong to a NEW client only. When
            //  a client already on file is chosen (or the lead is already linked to one), they are
            //  hidden and one line says where the GSTIN comes from — the save ignores them then.
            $cvAttached = (int) ($l['partner_id'] ?? 0) > 0; ?>
      <div class="ff ff-wide" id="cv-cid-wrap" style="min-width:0"<?= $cvAttached ? ' hidden' : '' ?>><?php $cidVals = ['state' => '']; $cidId = 'cv-cid'; $cidNote = 'Needed when this lead becomes a new ' . Tl('client') . ' — not when you attach it to one already on file above.'; include __DIR__ . '/_custid_fields.php'; ?></div>
      <div class="ff ff-wide ff-help" id="cv-cid-onfile"<?= $cvAttached ? '' : ' hidden' ?>>GSTIN is taken from the <?= e(Tl('client')) ?> already on file — nothing to type here.</div>
      <script>
      (function () {
        var sel = document.getElementById('cv-partner'), wrap = document.getElementById('cv-cid-wrap'), note = document.getElementById('cv-cid-onfile');
        if (!sel || !wrap || !note) return;
        function sync() {
          var onFile = sel.value !== '';
          wrap.hidden = onFile; note.hidden = !onFile;
          //  Hidden boxes must not stop the form with a "required" message the person cannot see.
          wrap.querySelectorAll('input,select,textarea').forEach(function (el) { el.disabled = onFile; });
        }
        sel.addEventListener('change', sync); sync();
      })();
      </script>
      <?php if ((int) ($_GET['loc'] ?? 0) > 0 && ($locRow = ops_one("SELECT id, code, legal_name FROM business_partners WHERE id=?", [(int) $_GET['loc']]))): ?>
        <label class="ff ff-wide" style="display:flex;gap:8px;align-items:flex-start;font-weight:600"><input type="checkbox" name="add_location_to" value="<?= (int) $locRow['id'] ?>" style="margin-top:3px"> Yes — it is <?= e($locRow['code'] . ' — ' . $locRow['legal_name']) ?> (same PAN): add this GSTIN as its billing location and convert the lead to that <?= e(Tl('client')) ?></label>
      <?php endif; ?>
      <?php if (trim((string)($l['sbu'] ?? '')) === ''): //  The enquiry needs a business unit; this lead has none. ?>
      <div class="ff"><label for="cv-sbu"><?= e(ucfirst(Tl('sbu'))) ?> for the <?= e(Tl('inquiry')) ?> *</label>
        <select class="form-control" id="cv-sbu" name="sbu" required><option value="">— choose —</option>
          <?php foreach ((function_exists('lk_options_or') ? lk_options_or('sbu', OPS_SBUS) : OPS_SBUS) as $sk => $sv): ?><option value="<?= e($sk) ?>"><?= e($sv) ?></option><?php endforeach; ?></select>
        <div class="ff-help">Asked only because the lead has none.</div></div>
      <?php endif; ?>
    </div>
  </section>

  <section class="rec-card">
    <div class="rec-ch"><span class="ico"><?= rec_icon('list') ?></span><h3>What comes across</h3></div>
    <div class="sxl-kv">
      <div><span>Contact</span><b><?= e($l['contact_name'] ?: '—') ?></b></div>
      <div><span>E-mail</span><b><?= e($l['contact_email'] ?: '—') ?></b></div>
      <div><span>Mobile</span><b><?= e($l['contact_phone'] ?: '—') ?></b></div>
      <div><span>Source</span><b><?= e($src ?: '—') ?></b></div>
      <div><span>Value</span><b><?= $l['value'] ? fmoney($l['value']) : '—' ?></b></div>
      <div><span>Owner</span><b><?= e($l['owner_name'] ?: '—') ?></b></div>
      <div class="w"><span>What they want</span><b style="white-space:pre-wrap;font-weight:500"><?= e($l['requirement'] ?: '—') ?></b></div>
    </div>
    <p class="sxl-hint" style="margin:10px 0 0">The contact becomes the <?= e(Tl('client')) ?>'s primary contact, and the requirement becomes the <?= e(Tl('inquiry')) ?>'s service requirement. From there the existing quotation flow takes over.</p>
  </section>

  <div class="sxl-act">
    <a class="btn secondary" href="/lead?id=<?= (int)$l['id'] ?>">Not yet</a>
    <button class="btn">Yes — make them a customer</button>
  </div>
</form>

<aside class="sxl-side">
  <?php rec_card_open('What happens when you press Yes', 'check'); ?>
    <ol class="sxl-ol">
      <li>The <?= e(Tl('client')) ?> is created with the next code — or the one you picked is used</li>
      <li><?= e($l['contact_name'] ?: 'The contact') ?> becomes its primary contact</li>
      <li>An <?= e(Tl('inquiry')) ?> is raised for the person who handles <?= e(Tlp('inquiry')) ?><?= ($l['office_name'] ?? '') !== '' ? ' in ' . e($l['office_name']) : '' ?></li>
      <li>The lead closes as <b>Won — customer</b></li>
      <li>Any deal from this lead is linked to the <?= e(Tl('client')) ?></li>
    </ol>
    <p class="sxl-hint" style="margin:8px 0 0">Already converted, or not pursued? It is refused. Two people pressing at once: only the first goes through.</p>
  <?php rec_card_close(); ?>
</aside>
</div>
</div>
<?php sxw_bottom('/leads'); ?>
