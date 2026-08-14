<?php
// ============================================================================
//  AREA HOME — generic area landing (Sales, Quality & Accreditation, Reporting,
//  Money, Insights, Directory, Admin). Driven entirely by ops_area_def(); see
//  lib/areas.php. Operations has its own richer Home.
// ============================================================================
$d = $def;
// A single section may be unlabelled (small areas); otherwise each section gets
// its own heading so a large area reads as a few groups, not a wall.
$multi = count($d['sections']) > 1 || ($d['sections'] && $d['sections'][0]['label'] !== '');
?>
<style>
  .op-sec{font-size:12px;letter-spacing:.5px;text-transform:uppercase;color:var(--muted,#656e7a);font-weight:700;margin:22px 0 10px;display:flex;align-items:center;gap:10px}
  .op-sec::after{content:"";flex:1;height:1px;background:var(--line,#e5e7eb)}
  .op-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
  .op-tile{background:var(--card,#fff);border:1px solid var(--line,#e5e7eb);border-radius:12px;padding:13px 14px;
    text-decoration:none;color:inherit;box-shadow:var(--shadow-sm,0 1px 2px rgba(18,32,60,.06));display:flex;gap:11px;align-items:flex-start;transition:.12s}
  .op-tile:hover{box-shadow:var(--shadow,0 10px 30px rgba(18,32,60,.10));transform:translateY(-1px)}
  .op-ic{width:36px;height:36px;flex:0 0 36px;border-radius:10px;background:var(--info-bg,#eff6ff);display:grid;place-items:center;font-size:18px;position:relative}
  .op-b{min-width:0;flex:1;display:flex;flex-direction:column}
  .op-t{font-weight:600;font-size:14.5px;display:flex;align-items:center;gap:7px;flex-wrap:wrap}
  .op-d{color:var(--muted,#656e7a);font-size:12.5px;margin-top:2px;line-height:1.45}
  .op-go{color:#c7ccd4;font-size:17px;align-self:center}
  .op-badge{font-size:10.5px;font-weight:700;border-radius:20px;padding:1px 8px;line-height:1.7}
  .op-badge.red{background:var(--bad-bg,#fef2f2);color:var(--bad,#b91c1c)}
  .op-badge.amber{background:var(--warn-bg,#fffbeb);color:var(--warn,#b45309)}
  .op-badge.green{background:var(--ok-bg,#ecfdf5);color:var(--ok,#15803d)}
  .op-badge.info{background:#eef2ff;color:var(--brand,#1e40af)}
  .op-note{background:#fffdf5;border:1px solid #f0e6c8;border-radius:12px;padding:12px 15px;font-size:13px;color:#6b5d2f;margin:20px 0 4px}
  .op-note b{color:#4d4320}
  @media (max-width:820px){ .op-grid{grid-template-columns:1fr} }
</style>

<div class="crumbs"><a href="/">Home</a> › <?= e($d['title']) ?></div>
<div class="master-head">
  <div>
    <h1><?= e($d['title']) ?></h1>
    <p class="sub" style="margin:2px 0 0"><?= e($d['sub']) ?></p>
  </div>
</div>

<?php
  // Two or more sections become tabs at the top (app.js → initSectionTabs),
  // so a big area like Quality is a few short tabs instead of one long scroll.
  // A single section just renders as a plain group.
  $useTabs = count($d['sections']) > 1;
  $renderTiles = function ($tiles) {
      echo '<div class="op-grid">';
      foreach ($tiles as $tl) {
          echo '<a class="op-tile" href="' . e($tl['route']) . '"' . (!empty($tl['ext']) ? ' target="_blank" rel="noopener"' : '') . '>';
          echo '<span class="op-ic">' . $tl['icon'] . '</span><span class="op-b"><span class="op-t">' . e($tl['label']);
          if (!empty($tl['count'])) echo ' <span class="op-badge ' . e($tl['tone'] ?: 'info') . '">' . (int)$tl['count'] . '</span>';
          echo '</span>';
          if (trim((string)($tl['desc'] ?? '')) !== '') echo '<span class="op-d">' . e($tl['desc']) . '</span>';
          echo '</span><span class="op-go">' . (!empty($tl['ext']) ? '↗' : '›') . '</span></a>';
      }
      echo '</div>';
  };
?>
<?php if ($useTabs): ?>
<div data-tabs data-tabs-key="area">
  <?php foreach ($d['sections'] as $s):
      $secCount = array_sum(array_map(fn($t) => (int)($t['count'] ?? 0), $s['tiles'])); ?>
    <section data-tab="<?= e($s['label'] !== '' ? $s['label'] : $d['title']) ?>"<?= $secCount ? ' data-count="' . $secCount . '"' : '' ?>>
      <?php $renderTiles($s['tiles']); ?>
    </section>
  <?php endforeach; ?>
</div>
<?php else: foreach ($d['sections'] as $s): ?>
  <div class="op-sec"><?= e($s['label'] !== '' ? $s['label'] : $d['title']) ?></div>
  <?php $renderTiles($s['tiles']); ?>
<?php endforeach; endif; ?>

<div class="op-note"><b>Nothing is removed.</b> Every screen that lived inside the old “<?= e($d['title']) ?>” menu is still here — it now opens from this page instead of a nested dropdown.</div>
