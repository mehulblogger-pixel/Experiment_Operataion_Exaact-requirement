<?php
// The QR encoder (Roadmap Step 17 — a scannable verification code on the issued
// report). We cannot run a camera in a unit test, so this pins the encoder to
// external ground truth instead: the canonical Reed-Solomon test vector and the
// QR standard's published format-information strings. Actual scannability was
// proven separately by rendering the matrix and decoding it back with jsQR
// across every EC level and versions 1-10 (see the dev harness); if the two
// checks below hold, the pieces that harness exercised have not drifted.
t_section('QR encoder (Step 17)');

// 1. Reed-Solomon against the canonical "HELLO WORLD" v1-M vector every QR
//    reference uses. A wrong generator or an off-by-one in the division shows
//    up here immediately.
$rs = qr_rs_encode([0x40,0xD2,0x75,0x47,0x76,0x17,0x32,0x06,0x27,0x26,0x96,0xC6,0xC6,0x96,0x70,0xEC], 10);
$rsHex = implode(' ', array_map(fn($v) => sprintf('%02X', $v), $rs));
t_eq($rsHex, 'BC 2A 90 13 6B AF EF FD 4B E0', 'Reed-Solomon matches the canonical QR test vector');

// 2. Format-information strings against the standard's published table.
t_eq(implode('', qr_format_bits('M', 0)), '101010000010010', 'format bits (M, mask 0) match the QR standard');
t_eq(implode('', qr_format_bits('L', 0)), '111011111000100', 'format bits (L, mask 0) match the QR standard');
t_eq(implode('', qr_format_bits('Q', 0)), '011010101011111', 'format bits (Q, mask 0) match the QR standard');
t_eq(implode('', qr_format_bits('H', 0)), '001011010001001', 'format bits (H, mask 0) match the QR standard');

// 3. Structure of a produced matrix: square, odd side, finder patterns in the
//    three corners, timing rows alternating.
$m = qr_matrix('https://inspection.example.com/verify?c=ABCD-EFGH-IJKL-MNOP', 'M');
t_ok(is_array($m) && count($m) > 0, 'a matrix is produced for a verification URL');
$n = count($m);
t_ok($n % 4 === 1 && $n >= 21, "the matrix is a valid QR size ($n x $n)");
$square = true;
foreach ($m as $row) if (count($row) !== $n) $square = false;
t_ok($square, 'the matrix is square');

// A finder pattern is a 7x7 with a solid 3x3 core; check all three corners.
$finderOk = function ($m, $r0, $c0) {
    return !empty($m[$r0][$c0]) && !empty($m[$r0+6][$c0+6])
        && !empty($m[$r0+3][$c0+3]) && empty($m[$r0+1][$c0+1]);
};
t_ok($finderOk($m, 0, 0), 'top-left finder pattern is present');
t_ok($finderOk($m, 0, $n - 7), 'top-right finder pattern is present');
t_ok($finderOk($m, $n - 7, 0), 'bottom-left finder pattern is present');

// Timing pattern on row 6 alternates between the finders.
$timingOk = true;
for ($c = 8; $c < $n - 8; $c++) if (($m[6][$c] ? 1 : 0) !== (($c % 2 === 0) ? 1 : 0)) $timingOk = false;
t_ok($timingOk, 'the timing pattern alternates correctly');

// 4. Version selection grows with payload length, and an over-long payload is
//    refused rather than producing a broken code.
$small = qr_matrix('SHORT', 'M');
$big   = qr_matrix(str_repeat('X', 150), 'L');
t_ok(count($big) > count($small), 'a longer payload selects a larger version');
t_ok(qr_matrix(str_repeat('X', 5000), 'H') === null, 'an over-long payload is refused, not mis-encoded');

// 5. The issued report PDF renders with the QR block without error.
if (function_exists('report_pdf_build')) {
    db()->prepare("INSERT INTO report_docs (irn, status, finalized, created_at, updated_at) VALUES (?,?,?,?,?)")
        ->execute(['TIR/26/0950', 'ISSUED', 1, date('c'), date('c')]);
    $id = (int)db()->lastInsertId();
    t_nothrow('an issued report renders to a PDF carrying the QR', function () use ($id) {
        $doc = ops_one("SELECT * FROM report_docs WHERE id=?", [$id]);
        $pdf = report_pdf_build($doc, [], [], [], [], [], []);
        t_ok(is_string($pdf) && strncmp($pdf, '%PDF', 4) === 0, 'output is a valid PDF document');
    });
}
