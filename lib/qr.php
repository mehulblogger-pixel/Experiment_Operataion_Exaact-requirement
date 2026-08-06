<?php
// A QR encoder we own outright.
//
// Why from scratch: this app ships with zero third-party libraries by design
// (it has to unzip onto a shared-hosting cPanel account and just run), so a
// composer package is not an option. A client should be able to point a phone
// camera at an issued report and land on its verification page without typing
// a twenty-character code — that is the whole point of Step 17 — and that means
// we generate the QR ourselves.
//
// Scope: byte (8-bit) mode only. Every URL we encode is ASCII, so byte mode
// covers us and we skip numeric/alphanumeric/kanji modes entirely. Versions 1
// through 10 (21x21 up to 57x57 modules) with error-correction levels L/M/Q/H,
// which is far more than a verification URL needs. The smallest version that
// fits the payload is chosen automatically.
//
// The output is a plain square matrix of 0/1 (qr_matrix). Nothing here draws
// anything — pdf.php turns the matrix into crisp vector squares, and the test
// harness renders it to a PNG and decodes it back to prove it actually scans.

// ---- Galois field GF(256) for Reed-Solomon (primitive polynomial 0x11d) ----
function qr_gf_tables(): array {
    static $exp = null, $log = null;
    if ($exp !== null) return [$exp, $log];
    $exp = array_fill(0, 512, 0);
    $log = array_fill(0, 256, 0);
    $x = 1;
    for ($i = 0; $i < 255; $i++) {
        $exp[$i] = $x;
        $log[$x] = $i;
        $x <<= 1;
        if ($x & 0x100) $x ^= 0x11d;
    }
    for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
    return [$exp, $log];
}

function qr_gf_mul(int $a, int $b): int {
    if ($a === 0 || $b === 0) return 0;
    [$exp, $log] = qr_gf_tables();
    return $exp[$log[$a] + $log[$b]];
}

// Reed-Solomon generator polynomial of the given degree.
function qr_rs_generator(int $degree): array {
    [$exp, ] = qr_gf_tables();
    $g = [1];
    for ($i = 0; $i < $degree; $i++) {
        $ng = array_fill(0, count($g) + 1, 0);
        foreach ($g as $j => $coef) {
            $ng[$j]     ^= qr_gf_mul($coef, 1);
            $ng[$j + 1] ^= qr_gf_mul($coef, $exp[$i]);
        }
        $g = $ng;
    }
    return $g;
}

// Error-correction codewords for one data block.
function qr_rs_encode(array $data, int $ecCount): array {
    // The generator is monic — its leading coefficient gen[0] is 1 and is only
    // used to derive the division factor. The remainder update multiplies by
    // the remaining coefficients gen[1..ecCount], hence the +1 offset below.
    $gen = qr_rs_generator($ecCount);
    $res = array_fill(0, $ecCount, 0);
    foreach ($data as $b) {
        $factor = $b ^ $res[0];
        array_shift($res);
        $res[] = 0;
        for ($j = 0; $j < $ecCount; $j++) {
            $res[$j] ^= qr_gf_mul($gen[$j + 1], $factor);
        }
    }
    return $res;
}

// ---- Capacity + block-structure tables, versions 1..10, byte mode ----
// [ec codewords per block, num blocks group1, data cw per block g1,
//   num blocks group2, data cw per block g2] keyed by version then EC level.
function qr_block_table(): array {
    return [
        1  => ['L' => [7,1,19,0,0],   'M' => [10,1,16,0,0],  'Q' => [13,1,13,0,0],  'H' => [17,1,9,0,0]],
        2  => ['L' => [10,1,34,0,0],  'M' => [16,1,28,0,0],  'Q' => [22,1,22,0,0],  'H' => [28,1,16,0,0]],
        3  => ['L' => [15,1,55,0,0],  'M' => [26,1,44,0,0],  'Q' => [18,2,17,0,0],  'H' => [22,2,13,0,0]],
        4  => ['L' => [20,1,80,0,0],  'M' => [18,2,32,0,0],  'Q' => [26,2,24,0,0],  'H' => [16,4,9,0,0]],
        5  => ['L' => [26,1,108,0,0], 'M' => [24,2,43,0,0],  'Q' => [18,2,15,2,16], 'H' => [22,2,11,2,12]],
        6  => ['L' => [18,2,68,0,0],  'M' => [16,4,27,0,0],  'Q' => [24,4,19,0,0],  'H' => [28,4,15,0,0]],
        7  => ['L' => [20,2,78,0,0],  'M' => [18,4,31,0,0],  'Q' => [18,2,14,4,15], 'H' => [26,4,13,1,14]],
        8  => ['L' => [24,2,97,0,0],  'M' => [22,2,38,2,39], 'Q' => [22,4,18,2,19], 'H' => [26,4,14,2,15]],
        9  => ['L' => [30,2,116,0,0], 'M' => [22,3,36,2,37], 'Q' => [20,4,16,4,17], 'H' => [24,4,12,4,13]],
        10 => ['L' => [18,2,68,2,69], 'M' => [26,4,43,1,44], 'Q' => [24,6,19,2,20], 'H' => [28,6,15,2,16]],
    ];
}

// Total data codeword capacity for a version+level.
function qr_data_capacity(int $ver, string $ecl): int {
    $t = qr_block_table()[$ver][$ecl];
    return $t[1] * $t[2] + $t[3] * $t[4];
}

// Alignment-pattern centre coordinates by version (empty for v1).
function qr_alignment_positions(int $ver): array {
    $tbl = [
        1 => [], 2 => [6,18], 3 => [6,22], 4 => [6,26], 5 => [6,30],
        6 => [6,34], 7 => [6,22,38], 8 => [6,24,42], 9 => [6,26,46], 10 => [6,28,50],
    ];
    return $tbl[$ver] ?? [];
}

// ---- Bit buffer ----
class QRBits {
    public array $bits = [];
    public function put(int $val, int $len): void {
        for ($i = $len - 1; $i >= 0; $i--) $this->bits[] = ($val >> $i) & 1;
    }
    public function len(): int { return count($this->bits); }
}

// Number of bits in the character-count field for byte mode.
function qr_char_count_bits(int $ver): int {
    return $ver <= 9 ? 8 : 16; // (byte mode: 8 for v1-9, 16 for v10-26)
}

// Build the full data-codeword + EC stream, interleaved per spec.
function qr_build_codewords(string $data, int $ver, string $ecl): array {
    $bb = new QRBits();
    $bb->put(0b0100, 4);                          // byte mode indicator
    $bb->put(strlen($data), qr_char_count_bits($ver));
    foreach (str_split($data) as $ch) $bb->put(ord($ch), 8);

    $capBits = qr_data_capacity($ver, $ecl) * 8;
    // Terminator (up to 4 bits) then pad to a byte boundary.
    $term = min(4, $capBits - $bb->len());
    for ($i = 0; $i < $term; $i++) $bb->bits[] = 0;
    while ($bb->len() % 8 !== 0) $bb->bits[] = 0;

    // Pad bytes alternate 0xEC / 0x11 until full.
    $pads = [0xEC, 0x11]; $pi = 0;
    while ($bb->len() < $capBits) { $bb->put($pads[$pi & 1], 8); $pi++; }

    // Bits -> data codewords.
    $dc = [];
    for ($i = 0; $i < $bb->len(); $i += 8) {
        $b = 0;
        for ($j = 0; $j < 8; $j++) $b = ($b << 1) | $bb->bits[$i + $j];
        $dc[] = $b;
    }

    // Split into blocks, compute EC per block.
    $t = qr_block_table()[$ver][$ecl];
    [$ecPer, $b1, $d1, $b2, $d2] = $t;
    $blocksData = []; $blocksEc = []; $p = 0;
    for ($i = 0; $i < $b1; $i++) { $blk = array_slice($dc, $p, $d1); $p += $d1; $blocksData[] = $blk; $blocksEc[] = qr_rs_encode($blk, $ecPer); }
    for ($i = 0; $i < $b2; $i++) { $blk = array_slice($dc, $p, $d2); $p += $d2; $blocksData[] = $blk; $blocksEc[] = qr_rs_encode($blk, $ecPer); }

    // Interleave data codewords, then EC codewords.
    $out = [];
    $maxD = max($d1, $d2);
    for ($col = 0; $col < $maxD; $col++)
        foreach ($blocksData as $blk)
            if ($col < count($blk)) $out[] = $blk[$col];
    for ($col = 0; $col < $ecPer; $col++)
        foreach ($blocksEc as $blk) $out[] = $blk[$col];

    return $out;
}

// ---- Matrix assembly ----
function qr_new_matrix(int $size): array {
    // -1 = unset/free, 0/1 = fixed module, function-pattern reservation via $fixed.
    return array_fill(0, $size, array_fill(0, $size, -1));
}

function qr_place_finder(array &$m, array &$fixed, int $r, int $c): void {
    for ($dr = -1; $dr <= 7; $dr++) {
        for ($dc = -1; $dc <= 7; $dc++) {
            $rr = $r + $dr; $cc = $c + $dc;
            if ($rr < 0 || $cc < 0 || $rr >= count($m) || $cc >= count($m)) continue;
            $on = ($dr >= 0 && $dr <= 6 && ($dc === 0 || $dc === 6)) ||
                  ($dc >= 0 && $dc <= 6 && ($dr === 0 || $dr === 6)) ||
                  ($dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4);
            $m[$rr][$cc] = $on ? 1 : 0;
            $fixed[$rr][$cc] = true;
        }
    }
}

function qr_place_alignment(array &$m, array &$fixed, int $r, int $c): void {
    for ($dr = -2; $dr <= 2; $dr++) {
        for ($dc = -2; $dc <= 2; $dc++) {
            $on = (abs($dr) === 2 || abs($dc) === 2 || ($dr === 0 && $dc === 0));
            $m[$r + $dr][$c + $dc] = $on ? 1 : 0;
            $fixed[$r + $dr][$c + $dc] = true;
        }
    }
}

// Format-info (EC level + mask) — 15 bits with BCH, XORed with a fixed mask.
function qr_format_bits(string $ecl, int $mask): array {
    $ecBits = ['L' => 0b01, 'M' => 0b00, 'Q' => 0b11, 'H' => 0b10][$ecl];
    $data = ($ecBits << 3) | $mask;                 // 5 bits
    $rem = $data;
    for ($i = 0; $i < 10; $i++) $rem = ($rem << 1) ^ ((($rem >> 9) & 1) ? 0x537 : 0);
    $bits = (($data << 10) | ($rem & 0x3ff)) ^ 0x5412;
    $out = [];
    for ($i = 14; $i >= 0; $i--) $out[] = ($bits >> $i) & 1;
    return $out;
}

// Version-info — 18 bits, versions >= 7 only.
function qr_version_bits(int $ver): array {
    $rem = $ver;
    for ($i = 0; $i < 12; $i++) $rem = ($rem << 1) ^ ((($rem >> 11) & 1) ? 0x1f25 : 0);
    $bits = ($ver << 12) | ($rem & 0xfff);
    $out = [];
    for ($i = 17; $i >= 0; $i--) $out[] = ($bits >> $i) & 1;
    return $out;
}

function qr_reserve_format_areas(array &$fixed, int $size, int $ver): void {
    for ($i = 0; $i < 9; $i++) { $fixed[8][$i] = true; $fixed[$i][8] = true; }
    for ($i = 0; $i < 8; $i++) { $fixed[8][$size - 1 - $i] = true; $fixed[$size - 1 - $i][8] = true; }
    if ($ver >= 7) {
        for ($i = 0; $i < 6; $i++)
            for ($j = 0; $j < 3; $j++) {
                $fixed[$i][$size - 11 + $j] = true;
                $fixed[$size - 11 + $j][$i] = true;
            }
    }
}

function qr_mask_condition(int $mask, int $r, int $c): bool {
    switch ($mask) {
        case 0: return ($r + $c) % 2 === 0;
        case 1: return $r % 2 === 0;
        case 2: return $c % 3 === 0;
        case 3: return ($r + $c) % 3 === 0;
        case 4: return (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0;
        case 5: return (($r * $c) % 2) + (($r * $c) % 3) === 0;
        case 6: return ((($r * $c) % 2) + (($r * $c) % 3)) % 2 === 0;
        case 7: return ((($r + $c) % 2) + (($r * $c) % 3)) % 2 === 0;
    }
    return false;
}

// Penalty scoring to pick the least-ugly mask (spec rules 1-4).
function qr_mask_penalty(array $m): int {
    $n = count($m); $pen = 0;
    // Rule 1: runs of 5+ same-colour in rows and columns.
    for ($i = 0; $i < $n; $i++) {
        for ($orient = 0; $orient < 2; $orient++) {
            $run = 1; $prev = -1;
            for ($j = 0; $j < $n; $j++) {
                $v = $orient === 0 ? $m[$i][$j] : $m[$j][$i];
                if ($v === $prev) { $run++; if ($run === 5) $pen += 3; elseif ($run > 5) $pen++; }
                else { $run = 1; $prev = $v; }
            }
        }
    }
    // Rule 2: 2x2 blocks of same colour.
    for ($r = 0; $r < $n - 1; $r++)
        for ($c = 0; $c < $n - 1; $c++)
            if ($m[$r][$c] === $m[$r][$c+1] && $m[$r][$c] === $m[$r+1][$c] && $m[$r][$c] === $m[$r+1][$c+1]) $pen += 3;
    // Rule 3: finder-like 1:1:3:1:1 patterns with a 4-module quiet run.
    $pat1 = [1,0,1,1,1,0,1,0,0,0,0];
    $pat2 = [0,0,0,0,1,0,1,1,1,0,1];
    for ($i = 0; $i < $n; $i++)
        for ($j = 0; $j <= $n - 11; $j++)
            for ($orient = 0; $orient < 2; $orient++) {
                $seg = [];
                for ($k = 0; $k < 11; $k++) $seg[] = $orient === 0 ? $m[$i][$j+$k] : $m[$j+$k][$i];
                if ($seg === $pat1 || $seg === $pat2) $pen += 40;
            }
    // Rule 4: overall dark-module balance.
    $dark = 0;
    foreach ($m as $row) foreach ($row as $v) if ($v === 1) $dark++;
    $ratio = $dark * 100 / ($n * $n);
    $pen += (int)(min(abs(intdiv((int)floor($ratio) - 50, 5)), abs(intdiv((int)ceil($ratio) - 50, 5)))) * 10;
    return $pen;
}

// Choose the smallest version that holds the payload at the given EC level.
function qr_pick_version(int $byteLen, string $ecl): int {
    for ($ver = 1; $ver <= 10; $ver++) {
        $cap = qr_data_capacity($ver, $ecl);
        $ccBits = qr_char_count_bits($ver);
        $needBits = 4 + $ccBits + $byteLen * 8;
        if ($needBits <= $cap * 8) return $ver;
    }
    return 0; // too long for our supported range
}

// Public entry point: text -> boolean module matrix (true = dark).
// Returns null if the text is too long for versions 1-10.
function qr_matrix(string $text, string $ecl = 'M', ?int $forceMask = null): ?array {
    $ecl = strtoupper($ecl);
    if (!in_array($ecl, ['L','M','Q','H'], true)) $ecl = 'M';
    $ver = qr_pick_version(strlen($text), $ecl);
    if ($ver === 0) {
        // Fall back to a lower EC level rather than fail outright.
        foreach (['Q','M','L'] as $try) {
            $ver = qr_pick_version(strlen($text), $try);
            if ($ver > 0) { $ecl = $try; break; }
        }
        if ($ver === 0) return null;
    }

    $size = 17 + $ver * 4;
    $m = qr_new_matrix($size);
    $fixed = array_fill(0, $size, array_fill(0, $size, false));

    // Finder patterns + their separators.
    qr_place_finder($m, $fixed, 0, 0);
    qr_place_finder($m, $fixed, 0, $size - 7);
    qr_place_finder($m, $fixed, $size - 7, 0);

    // Timing patterns.
    for ($i = 8; $i < $size - 8; $i++) {
        if ($m[6][$i] === -1) { $m[6][$i] = ($i % 2 === 0) ? 1 : 0; $fixed[6][$i] = true; }
        if ($m[$i][6] === -1) { $m[$i][6] = ($i % 2 === 0) ? 1 : 0; $fixed[$i][6] = true; }
    }

    // Alignment patterns (skip where they'd collide with finders).
    $ap = qr_alignment_positions($ver);
    foreach ($ap as $r) foreach ($ap as $c) {
        if (($r <= 8 && $c <= 8) || ($r <= 8 && $c >= $size - 9) || ($r >= $size - 9 && $c <= 8)) continue;
        qr_place_alignment($m, $fixed, $r, $c);
    }

    // Dark module (always set).
    $m[$size - 8][8] = 1; $fixed[$size - 8][8] = true;

    // Reserve format/version areas so data placement skips them.
    qr_reserve_format_areas($fixed, $size, $ver);

    // Data placement: zig-zag up/down in pairs of columns from the right.
    $codewords = qr_build_codewords($text, $ver, $ecl);
    $bitstream = [];
    foreach ($codewords as $cw) for ($b = 7; $b >= 0; $b--) $bitstream[] = ($cw >> $b) & 1;

    $bi = 0; $upward = true;
    for ($col = $size - 1; $col > 0; $col -= 2) {
        if ($col === 6) $col--;                     // skip the vertical timing column
        for ($k = 0; $k < $size; $k++) {
            $row = $upward ? ($size - 1 - $k) : $k;
            for ($c = $col; $c > $col - 2; $c--) {
                if ($fixed[$row][$c]) continue;
                $m[$row][$c] = ($bi < count($bitstream)) ? $bitstream[$bi] : 0;
                $bi++;
            }
        }
        $upward = !$upward;
    }

    // Try all 8 masks, keep the lowest-penalty result.
    $best = null; $bestPen = PHP_INT_MAX; $bestMask = 0;
    $maskRange = $forceMask === null ? range(0, 7) : [$forceMask];
    foreach ($maskRange as $mask) {
        $cand = $m;
        for ($r = 0; $r < $size; $r++)
            for ($c = 0; $c < $size; $c++)
                if (!$fixed[$r][$c] && $cand[$r][$c] !== -1 && qr_mask_condition($mask, $r, $c))
                    $cand[$r][$c] ^= 1;
        qr_apply_format($cand, $size, $ecl, $mask, $ver);
        $pen = qr_mask_penalty($cand);
        if ($pen < $bestPen) { $bestPen = $pen; $best = $cand; $bestMask = $mask; }
    }

    // Convert to boolean (dark = true).
    $out = [];
    foreach ($best as $row) {
        $orow = [];
        foreach ($row as $v) $orow[] = ($v === 1);
        $out[] = $orow;
    }
    return $out;
}

// Render a payload as a self-contained inline SVG QR — crisp at any size, no
// external request, works offline. Ideal for an on-screen code (e.g. a 2FA
// enrolment link a phone scans). $px is the target pixel side; $quiet is the
// quiet-zone width in modules (4 is the spec minimum for reliable scanning).
// Returns '' if the payload is too long to encode. The caller is responsible
// for any surrounding markup; the SVG itself is safe to echo directly.
function qr_svg(string $text, int $px = 200, string $ecl = 'M', int $quiet = 4): string {
    $m = qr_matrix($text, $ecl);
    if ($m === null) return '';
    $n = count($m);
    $total = $n + 2 * $quiet;
    // One black path over a white background; every dark module is a unit rect,
    // so the whole code is a single fill with no per-module colour switching.
    $rects = '';
    for ($r = 0; $r < $n; $r++) {
        for ($c = 0; $c < $n; $c++) {
            if (empty($m[$r][$c])) continue;
            $rects .= 'M' . ($c + $quiet) . ',' . ($r + $quiet) . 'h1v1h-1z';
        }
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $px . '" height="' . $px . '" '
        . 'viewBox="0 0 ' . $total . ' ' . $total . '" shape-rendering="crispEdges" role="img" '
        . 'aria-label="QR code">'
        . '<rect width="' . $total . '" height="' . $total . '" fill="#fff"/>'
        . '<path d="' . $rects . '" fill="#000"/></svg>';
}

// Stamp format + version information into a masked candidate.
function qr_apply_format(array &$m, int $size, string $ecl, int $mask, int $ver): void {
    $f = qr_format_bits($ecl, $mask);
    // Copy 1, around the top-left finder. This module order is the one the
    // spec fixes; bit 0 is the most-significant of the 15-bit format string.
    $seq1 = [[8,0],[8,1],[8,2],[8,3],[8,4],[8,5],[8,7],[8,8],[7,8],[5,8],[4,8],[3,8],[2,8],[1,8],[0,8]];
    foreach ($seq1 as $k => [$r, $c]) $m[$r][$c] = $f[$k];
    // Copy 2, split across the bottom-left and top-right finders. Bits 0-6 run
    // up column 8 from the bottom; bits 7-14 run along row 8 from the right side.
    for ($i = 0; $i < 7; $i++) $m[$size - 1 - $i][8] = $f[$i];
    for ($i = 0; $i < 8; $i++) $m[8][$size - 8 + $i] = $f[7 + $i];

    if ($ver >= 7) {
        $v = qr_version_bits($ver);
        // 18 bits, LSB-first into two 6x3 blocks.
        for ($i = 0; $i < 18; $i++) {
            $bit = $v[17 - $i];
            $r = intdiv($i, 3); $c = $i % 3;
            $m[$r][$size - 11 + $c] = $bit;
            $m[$size - 11 + $c][$r] = $bit;
        }
    }
}
