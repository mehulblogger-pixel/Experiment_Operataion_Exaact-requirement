<?php
// Phase 2 §39 — the unified QUALITY CASE, as a read-only view over the EXISTING modules.
//
// NCR, CAPA, Complaint (and their audit / risk sources) are separate modules with their
// own tables. The non-destructive rule forbids merging them into one table; but an
// assessor asking "what is the full story of this quality issue — the finding, the NCR,
// the corrective action, the root cause, the effectiveness, the closure?" had to open
// each module separately. This assembles the chain from the foreign keys those modules
// ALREADY carry (nonconformities.complaint_id / .capa_id, capa.complaint_id, …). It reads;
// it never writes, and the underlying modules stay exactly as they are.

// Given any anchor (an NCR, a CAPA or a Complaint), the linked members of one case.
function quality_case($kind, $id) {
    $kind = strtoupper((string)$kind); $id = (int)$id;
    $one = function ($sql, $a = []) { try { return ops_one($sql, $a); } catch (Throwable $e) { return null; } };
    $all = function ($sql, $a = []) { try { return ops_all($sql, $a) ?: []; } catch (Throwable $e) { return []; } };

    $complaintId = 0; $ncrIds = []; $capaIds = [];
    if ($kind === 'NCR') {
        $n = $one("SELECT * FROM nonconformities WHERE id=?", [$id]);
        if ($n) { $ncrIds[] = $id; $complaintId = (int)($n['complaint_id'] ?? 0); if (!empty($n['capa_id'])) $capaIds[] = (int)$n['capa_id']; }
    } elseif ($kind === 'CAPA') {
        $c = $one("SELECT * FROM capa WHERE id=?", [$id]);
        if ($c) { $capaIds[] = $id; $complaintId = (int)($c['complaint_id'] ?? 0); }
        foreach ($all("SELECT id FROM nonconformities WHERE capa_id=?", [$id]) as $r) $ncrIds[] = (int)$r['id'];
    } elseif ($kind === 'COMPLAINT') {
        $complaintId = $id;
    }
    // From the complaint, pull its NCRs and CAPAs; from each NCR, its CAPA.
    if ($complaintId) {
        foreach ($all("SELECT id FROM nonconformities WHERE complaint_id=?", [$complaintId]) as $r) $ncrIds[] = (int)$r['id'];
        foreach ($all("SELECT id FROM capa WHERE complaint_id=?", [$complaintId]) as $r) $capaIds[] = (int)$r['id'];
    }
    foreach (array_unique($ncrIds) as $nid) { $nn = $one("SELECT capa_id FROM nonconformities WHERE id=?", [$nid]); if ($nn && !empty($nn['capa_id'])) $capaIds[] = (int)$nn['capa_id']; }

    $members = []; $seen = [];
    $push = function ($k, $row, $url, $refCol, $titleCols, $statusCol) use (&$members, &$seen) {
        if (!$row) return;
        $dk = $k . ':' . (int)$row['id']; if (isset($seen[$dk])) return; $seen[$dk] = 1;
        $title = '';
        foreach ((array)$titleCols as $tc) { $v = trim((string)($row[$tc] ?? '')); if ($v !== '') { $title = $v; break; } }
        $members[] = ['kind'=>$k, 'id'=>(int)$row['id'],
                      'ref'=>trim((string)($row[$refCol] ?? '')) ?: ('#' . (int)$row['id']),
                      'title'=>$title, 'status'=>strtoupper((string)($row[$statusCol] ?? '')),
                      'url'=>$url . (int)$row['id']];
    };
    if ($complaintId) $push('COMPLAINT', $one("SELECT * FROM complaints WHERE id=?", [$complaintId]), '/complaint?id=', 'ref', ['subject','title','description'], 'status');
    foreach (array_unique($ncrIds)  as $nid) $push('NCR',  $one("SELECT * FROM nonconformities WHERE id=?", [$nid]), '/ncr-item?id=',  'ref', ['title','summary'], 'status');
    foreach (array_unique($capaIds) as $cid) $push('CAPA', $one("SELECT * FROM capa WHERE id=?", [$cid]), '/capa-item?id=', 'ref', ['title','description'], 'status');

    // The case outcome, read from the corrective action: is there a root cause, was it
    // found effective, is it closed (verified)?
    $outcome = ['has_capa'=>!empty($capaIds), 'rca'=>false, 'effective'=>'', 'closed'=>false];
    if ($capaIds) {
        $c = $one("SELECT root_cause, effective, verified_on FROM capa WHERE id=?", [(int)array_values(array_unique($capaIds))[0]]);
        if ($c) { $outcome['rca'] = trim((string)($c['root_cause'] ?? '')) !== '';
                  $outcome['effective'] = strtoupper((string)($c['effective'] ?? ''));
                  $outcome['closed'] = trim((string)($c['verified_on'] ?? '')) !== ''; }
    }
    return ['members'=>$members, 'outcome'=>$outcome];
}

// A drop-in "Quality case" panel for an NCR / CAPA / complaint detail view. Shows the
// linked members and the corrective-action outcome. Read-only; renders nothing when the
// case has only the anchor itself (i.e. no links to show).
function quality_case_render($kind, $id, $title = 'Quality case — the full story') {
    if (!function_exists('quality_case')) return;
    $case = quality_case($kind, $id);
    if (count($case['members']) <= 1) return;   // just the anchor, nothing to consolidate
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $tone = ['CLOSED'=>'p-ok','VERIFIED'=>'p-ok','OPEN'=>'p-warn','MAJOR'=>'p-bad'];
    echo '<div class="panel"><h3 class="tab-sub" style="margin-top:0">' . $e($title)
       . ' <span class="muted" style="font-weight:400;font-size:12px">(' . count($case['members']) . ' linked)</span></h3>'
       . '<p class="muted" style="margin:0 0 8px;font-size:12px">The complaint, nonconformity and corrective action for this issue, linked across modules. Each record stays in its own module.</p>'
       . '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px">';
    foreach ($case['members'] as $m) {
        echo '<a class="pill ' . ($tone[$m['status']] ?? 'p-mut') . '" href="' . $e($m['url']) . '">'
           . $e(ucfirst(strtolower($m['kind']))) . ' ' . $e($m['ref'])
           . ($m['status'] !== '' ? ' · ' . $e(ucfirst(strtolower($m['status']))) : '') . '</a>';
    }
    echo '</div>';
    $o = $case['outcome'];
    if ($o['has_capa']) {
        $bits = [$o['rca'] ? 'root cause recorded' : 'no root cause yet',
                 $o['effective'] === 'YES' ? 'verified effective' : ($o['effective'] === 'NO' ? 'found NOT effective' : 'effectiveness not yet judged'),
                 $o['closed'] ? 'closed' : 'open'];
        echo '<div class="muted" style="font-size:12.5px">Corrective action: ' . $e(implode(' · ', $bits)) . '.</div>';
    }
    echo '</div>';
}
