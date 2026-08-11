<?php
// ============================================================================
//  SimplePDF — a tiny, dependency-free PDF writer (no Composer / no libraries).
//  Enough to lay out a quotation: Helvetica text (built-in font, no embedding),
//  lines, filled rectangles, and an embedded JPEG image (e.g. a signature).
//  A4 portrait; automatic page breaks. Byte-accurate xref so the file opens.
// ============================================================================
class SimplePDF {
    private $W = 595.28, $H = 841.89;                 // A4 in points
    public  $ml = 40, $mr = 40, $mt = 44, $mb = 54;
    private $pages = [];                              // each: content string
    private $cur = '';
    public  $y;                                       // cursor from TOP
    private $images = [];                             // name => ['data','w','h','cs']
    private $imgSeq = 0;

    public $watermark = '';                          // e.g. 'DRAFT' — drawn on every page, behind content
    // Running header / footer — drawn on EVERY page in the margin area by output().
    //   runHead = ['name'=>.., 'sub'=>.., 'band'=>[r,g,b], 'skipFirst'=>bool]
    //   runFoot = ['left'=>.., 'pageNumbers'=>bool, 'band'=>[r,g,b]]
    // Page X of Y is resolved in output() once the total page count is known.
    public $runHead = null;
    public $runFoot = null;
    public function __construct() { $this->y = $this->mt; }
    public function pageW() { return $this->W; }
    // A large, light, 45° watermark laid behind the page content. Returns the
    // content-stream operators; output() prepends them to each page so the text
    // sits under everything.
    private function watermarkOps() {
        if ($this->watermark === '') return '';
        $t = $this->esc(strtoupper($this->watermark));
        $size = 74; $c = 0.7071;
        $x = $this->W * 0.5 - 175; $y = $this->H * 0.5 - 120;   // roughly centred, bottom-origin
        return "q 0.90 0.90 0.90 rg BT /F2 $size Tf "
             . sprintf('%.4f %.4f %.4f %.4f %.2f %.2f', $c, $c, -$c, $c, $x, $y)
             . " Tm ($t) Tj ET Q\n";
    }
    public function contentW() { return $this->W - $this->ml - $this->mr; }
    public function right() { return $this->W - $this->mr; }
    // Pagination helpers used by a measure-then-place builder: how many pages so
    // far, the 0-based index of the page being drawn, and the usable top/bottom.
    public function pageCount() { return count($this->pages) + 1; }
    public function curPageIndex() { return count($this->pages); }
    public function usableTop() { return $this->mt; }
    public function usableBottom() { return $this->H - $this->mb; }

    // One text-drawing op at absolute x / top-origin y (no cursor movement) — used
    // by the running header/footer which are painted outside the normal flow.
    private function textOp($x, $yTop, $str, $size, $bold, $rgb) {
        $f = $bold ? '/F2' : '/F1';
        $col = $rgb ? sprintf('%.3f %.3f %.3f rg ', $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255) : '';
        return "BT $col$f $size Tf " . sprintf('%.2f %.2f', $x, $this->yy($yTop + $size)) . " Td (" . $this->esc((string)$str) . ") Tj ET 0 0 0 rg\n";
    }
    // Build the running-header + running-footer ops for one page. $pageNo is
    // 1-based; $total is the final page count (known only at output() time, so
    // "Page X of Y" is correct). Everything is drawn in the top/bottom margins so
    // it never collides with the page body.
    private function headFootOps($pageNo, $total) {
        $ops = '';
        if (is_array($this->runHead)) {
            $h = $this->runHead; $band = $h['band'] ?? [30, 64, 175];
            $skipFirst = !empty($h['skipFirst']);   // page 1 usually already has the full letterhead
            if (!($skipFirst && $pageNo === 1)) {
                $y = 15;
                $name = trim((string)($h['name'] ?? ''));
                $sub  = trim((string)($h['sub'] ?? ''));
                if ($name !== '') $ops .= $this->textOp($this->ml, $y, $name, 9, true, $band);
                if ($sub !== '')  { $w = $this->strWidth($sub, 7.5, false); $ops .= $this->textOp($this->right() - $w, $y + 1, $sub, 7.5, false, [120, 120, 120]); }
                $ly = $y + 12;
                $ops .= sprintf("%.3f %.3f %.3f RG 0.5 w %.2f %.2f m %.2f %.2f l S 0 0 0 RG\n", $band[0] / 255, $band[1] / 255, $band[2] / 255, $this->ml, $this->yy($ly), $this->right(), $this->yy($ly));
            }
        }
        if (is_array($this->runFoot)) {
            $f = $this->runFoot; $fy = $this->H - 30;
            $ops .= sprintf("0.82 0.82 0.82 RG 0.5 w %.2f %.2f m %.2f %.2f l S 0 0 0 RG\n", $this->ml, $this->yy($fy), $this->right(), $this->yy($fy));
            $left = trim((string)($f['left'] ?? ''));
            if ($left !== '') { if ($this->strWidth($left, 7.5, false) > $this->contentW() * 0.6) $left = mb_substr($left, 0, 90); $ops .= $this->textOp($this->ml, $fy + 3, $left, 7.5, false, [125, 125, 125]); }
            if (!empty($f['pageNumbers'])) {
                $pn = "Page $pageNo of $total"; $w = $this->strWidth($pn, 7.5, false);
                $ops .= $this->textOp($this->right() - $w, $fy + 3, $pn, 7.5, false, [125, 125, 125]);
            }
        }
        return $ops;
    }

    // The built-in Helvetica font is declared with WinAnsiEncoding, so the bytes
    // in the content stream must be Windows-1252 — NOT raw UTF-8. Writing UTF-8
    // straight through made "·" show as "Â·" and "—" as "â€"". Convert first, so
    // middots, en/em dashes, curly quotes, ×, °, etc. all render correctly.
    private function toWinAnsi($s) {
        if ($s === '' || preg_match('//u', $s) !== 1) return $s;   // empty or already single-byte
        // Fast path: pure ASCII needs no conversion.
        if (!preg_match('/[\x80-\xFF]/', $s) && mb_check_encoding($s, 'ASCII')) return $s;
        if (function_exists('iconv')) { $r = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s); if ($r !== false) return $r; }
        if (function_exists('mb_convert_encoding')) return mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        return $s;
    }
    private function esc($s) { $s = $this->toWinAnsi((string)$s); return strtr($s, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '']); }
    private function yy($y) { return $this->H - $y; }  // top-origin -> PDF bottom-origin

    public function addPage() { $this->pages[] = $this->cur; $this->cur = ''; $this->y = $this->mt; }
    public function needSpace($h) { if ($this->y + $h > $this->H - $this->mb) { $this->addPage(); return true; } return false; }

    // Draw text at absolute x; returns nothing. $align: 'L'|'R'|'C' within [x, x2].
    public function text($x, $str, $size = 10, $bold = false, $rgb = null, $x2 = null, $align = 'L') {
        $str = (string)$str;
        $f = $bold ? '/F2' : '/F1';
        $w = $this->strWidth($str, $size, $bold);
        if ($x2 !== null) { if ($align === 'R') $x = $x2 - $w; elseif ($align === 'C') $x = $x + (($x2 - $x) - $w) / 2; }
        $col = $rgb ? sprintf('%.3f %.3f %.3f rg ', $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255) : '';
        $reset = $rgb ? ' 0 0 0 rg' : '';
        $this->cur .= "BT $col$f $size Tf " . sprintf('%.2f %.2f', $x, $this->yy($this->y + $size)) . " Td (" . $this->esc($str) . ") Tj ET$reset\n";
    }
    // Line of text that advances the cursor by $lh.
    public function line($str, $size = 10, $bold = false, $lh = null, $rgb = null) {
        $lh = $lh ?: $size + 4; $this->needSpace($lh);
        $this->text($this->ml, $str, $size, $bold, $rgb);
        $this->y += $lh;
    }
    public function gap($h = 6) { $this->y += $h; }
    public function hr($rgb = [210, 210, 210]) {
        $this->needSpace(6);
        $this->cur .= sprintf("%.3f %.3f %.3f RG 0.6 w %.2f %.2f m %.2f %.2f l S 0 0 0 RG\n", $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255, $this->ml, $this->yy($this->y), $this->right(), $this->yy($this->y));
        $this->y += 6;
    }
    public function rectFill($x, $y, $w, $h, $rgb) {
        $this->cur .= sprintf("%.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re f 0 0 0 rg\n", $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255, $x, $this->yy($y + $h), $w, $h);
    }
    // Draw a QR matrix (bool[][], true = dark) as crisp vector squares. $x/$y are
    // the top-left corner in points; $box is the full side length. A quiet zone is
    // NOT added here — callers place the code in whitespace. Every dark module is
    // one rectangle in a single black fill path, so it stays sharp at any zoom and
    // costs nothing in file size beyond the rects themselves.
    public function qr($matrix, $x, $y, $box) {
        if (!is_array($matrix) || !$matrix) return;
        $n = count($matrix);
        $m = $box / $n;                       // module side in points
        $path = "0 0 0 rg\n";
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if (empty($matrix[$r][$c])) continue;
                $px = $x + $c * $m;
                $py = $this->yy($y + ($r + 1) * $m);   // top-origin -> PDF bottom-origin
                $path .= sprintf("%.2f %.2f %.2f %.2f re\n", $px, $py, $m, $m);
            }
        }
        $this->cur .= $path . "f\n";
    }
    public function lineAt($x1, $y1, $x2, $y2, $rgb = [210, 210, 210], $w = 0.5) {
        $this->cur .= sprintf("%.3f %.3f %.3f RG %.2f w %.2f %.2f m %.2f %.2f l S 0 0 0 RG\n", $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255, $w, $x1, $this->yy($y1), $x2, $this->yy($y2));
    }
    // Approx Helvetica width in points (avg per-char factor — good enough for layout).
    public function strWidth($s, $size, $bold = false) { return mb_strlen($s) * $size * ($bold ? 0.56 : 0.52); }
    // Wrap text to a width; returns array of lines.
    public function wrap($s, $size, $w, $bold = false) {
        $out = []; $cur = '';
        foreach (preg_split('/\s+/', trim((string)$s)) as $word) {
            $try = $cur === '' ? $word : "$cur $word";
            if ($this->strWidth($try, $size, $bold) > $w && $cur !== '') { $out[] = $cur; $cur = $word; } else $cur = $try;
        }
        if ($cur !== '') $out[] = $cur;
        return $out ?: [''];
    }

    // Register a JPEG (binary). Returns its resource name, or '' if unusable.
    public function addJpeg($data) {
        if ($data === '' || strncmp($data, "\xFF\xD8", 2) !== 0) return '';
        [$w, $h, $cs] = $this->jpegInfo($data);
        if (!$w || !$h) return '';
        $name = 'Im' . (++$this->imgSeq);
        $this->images[$name] = ['data' => $data, 'w' => $w, 'h' => $h, 'cs' => $cs];
        return $name;
    }
    public function drawImage($name, $x, $y, $w, $h) {
        if (!isset($this->images[$name])) return;
        $this->cur .= sprintf("q %.2f 0 0 %.2f %.2f %.2f cm /%s Do Q\n", $w, $h, $x, $this->yy($y + $h), $name);
    }
    public function imgDim($name) { return isset($this->images[$name]) ? [$this->images[$name]['w'], $this->images[$name]['h']] : [0, 0]; }
    private function jpegInfo($d) {
        $len = strlen($d); $i = 2; $w = 0; $h = 0; $c = 3;
        while ($i < $len) {
            if ($d[$i] !== "\xFF") { $i++; continue; }
            $m = ord($d[$i + 1]); $i += 2;
            if ($m === 0xD8 || $m === 0xD9 || ($m >= 0xD0 && $m <= 0xD7)) continue;
            if ($i + 1 >= $len) break;
            $seg = (ord($d[$i]) << 8) + ord($d[$i + 1]);
            if ($m >= 0xC0 && $m <= 0xCF && $m !== 0xC4 && $m !== 0xC8 && $m !== 0xCC) {
                $h = (ord($d[$i + 3]) << 8) + ord($d[$i + 4]);
                $w = (ord($d[$i + 5]) << 8) + ord($d[$i + 6]);
                $c = ord($d[$i + 7]); break;
            }
            $i += $seg;
        }
        return [$w, $h, $c === 1 ? 'DeviceGray' : ($c === 4 ? 'DeviceCMYK' : 'DeviceRGB')];
    }

    public function output() {
        $this->pages[] = $this->cur;                 // flush current
        $objs = []; $add = function ($s) use (&$objs) { $objs[] = $s; return count($objs); };
        $nPages = count($this->pages);
        $catalog = $add('');                          // 1
        $pagesObj = $add('');                         // 2
        $fRegular = $add("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>");
        $fBold = $add("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>");
        $imgRefs = [];
        foreach ($this->images as $name => $im) {
            $id = $add("<< /Type /XObject /Subtype /Image /Width {$im['w']} /Height {$im['h']} /ColorSpace /{$im['cs']} /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($im['data']) . " >>\nstream\n" . $im['data'] . "\nendstream");
            $imgRefs[$name] = $id;
        }
        $xobj = '';
        foreach ($imgRefs as $name => $id) $xobj .= "/$name $id 0 R ";
        $res = "<< /Font << /F1 $fRegular 0 R /F2 $fBold 0 R >>" . ($xobj ? " /XObject << $xobj>>" : '') . " >>";
        $kids = [];
        $wm = $this->watermarkOps();
        $pageNo = 0;
        foreach ($this->pages as $content) {
            $pageNo++;
            $hf = $this->headFootOps($pageNo, $nPages);   // running header/footer + Page X of Y
            if ($hf !== '') $content = $content . $hf;     // drawn on top, in the margins
            if ($wm !== '') $content = $wm . $content;   // watermark behind the page content
            $cid = $add("<< /Length " . strlen($content) . " >>\nstream\n$content\nendstream");
            $pid = $add("<< /Type /Page /Parent $pagesObj 0 R /MediaBox [0 0 " . sprintf('%.2f %.2f', $this->W, $this->H) . "] /Resources $res /Contents $cid 0 R >>");
            $kids[] = "$pid 0 R";
        }
        $objs[$catalog - 1] = "<< /Type /Catalog /Pages $pagesObj 0 R >>";
        $objs[$pagesObj - 1] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count $nPages >>";

        $out = "%PDF-1.4\n"; $offsets = [];
        foreach ($objs as $i => $body) { $offsets[$i + 1] = strlen($out); $out .= ($i + 1) . " 0 obj\n$body\nendobj\n"; }
        $xrefPos = strlen($out); $n = count($objs) + 1;
        $out .= "xref\n0 $n\n0000000000 65535 f \n";
        for ($i = 1; $i < $n; $i++) $out .= sprintf("%010d 00000 n \n", $offsets[$i]);
        $out .= "trailer\n<< /Size $n /Root $catalog 0 R >>\nstartxref\n$xrefPos\n%%EOF";
        return $out;
    }
}

// Normalise an uploaded signature image to JPEG bytes (embeddable). Uses GD when
// available (handles PNG/transparent → white bg). Returns [jpegBytes, error].
function signature_to_jpeg($raw) {
    if ($raw === '') return ['', 'empty file'];
    if (strncmp($raw, "\xFF\xD8", 2) === 0) return [$raw, ''];        // already JPEG
    if (!function_exists('imagecreatefromstring')) return ['', 'PNG signatures need the GD extension — upload a JPG instead.'];
    $im = @imagecreatefromstring($raw);
    if (!$im) return ['', 'unreadable image — upload a PNG or JPG.'];
    $w = imagesx($im); $h = imagesy($im);
    $bg = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($bg, 255, 255, 255);
    imagefilledrectangle($bg, 0, 0, $w, $h, $white);
    imagecopy($bg, $im, 0, 0, 0, 0, $w, $h);
    ob_start(); imagejpeg($bg, null, 92); $jpg = ob_get_clean();
    imagedestroy($im); imagedestroy($bg);
    return [$jpg, ''];
}

// Build the client-facing quotation PDF.
//  $sig = ['img'=>jpegBytes,'name'=>..,'desig'=>..]
//  $lh  = ['logo'=>jpegBytes,'name'=>..,'address'=>..,'contact'=>..,'footer'=>..] (letterhead)
function quote_pdf_build($q, $lines, $tpl, $sig = [], $lh = []) {
    $p = new SimplePDF();
    $ml = $p->ml; $right = $p->right();
    // ---- customisable letterhead ----
    $band = !empty($lh['band']) && is_array($lh['band']) ? $lh['band'] : [30, 64, 175];
    $p->rectFill(0, 0, $p->pageW(), 6, $band);
    $p->y = $p->mt;
    $top = $p->y; $nameX = $ml;
    if (!empty($lh['logo'])) {
        $ln = $p->addJpeg($lh['logo']);
        if ($ln) { [$iw, $ih] = $p->imgDim($ln); $lw = 92; $lhh = $ih > 0 ? min(48, $lw * $ih / max(1, $iw)) : 40; $p->drawImage($ln, $ml, $top, $lw, $lhh); $nameX = $ml + $lw + 12; }
    }
    $p->text($nameX, ($lh['name'] ?? '') ?: app_name(), 16, true, $band);
    $ly = $top + 20;
    foreach (preg_split('/\r?\n/', (string)($lh['address'] ?? '')) as $al) { $al = trim($al); if ($al === '') continue; $p->y = $ly; $p->text($nameX, $al, 8.5, false, [90, 90, 90]); $ly += 11; }
    if (!empty($lh['contact'])) { $p->y = $ly; $p->text($nameX, $lh['contact'], 8.5, false, [90, 90, 90]); $ly += 11; }
    if (!empty($lh['gstin'])) { $p->y = $ly; $p->text($nameX, $lh['gstin'], 8.5, false, [90, 90, 90]); $ly += 11; }
    // doc / format numbers (from the uploaded format) on the right
    $docNo = $tpl['document_number'] ?? ''; $fmtNo = $tpl['format_number'] ?? ''; $docRev = $tpl['doc_revision'] ?? '';
    $p->y = $top; $p->text($ml, ($docNo ? "Doc: $docNo" : ''), 8, false, [110, 110, 110], $right, 'R');
    if ($fmtNo) { $p->y = $top + 11; $p->text($ml, "Format: $fmtNo", 8, false, [110, 110, 110], $right, 'R'); }
    if ($docRev) { $p->y = $top + 22; $p->text($ml, $docRev, 8, false, [110, 110, 110], $right, 'R'); }
    $p->y = max($ly, $top + 44); $p->hr();
    // ---- title + meta ----
    $p->line('QUOTATION', 14, true, 20);
    $meta = quote_label($q) . '     Date: ' . date('d M Y', strtotime($q['created_at'] ?: 'now')) . '     Validity: ' . (int)$q['validity_days'] . ' days';
    $p->line($meta, 9, false, 16, [90, 90, 90]);
    $p->gap(4);
    // ---- customer block ----
    $p->line('To,', 10, true, 14);
    $p->line($q['client_name'] ?: '—', 11, true, 15);
    if ($q['contact_name'] || $q['contact_email'] || $q['contact_mobile'])
        $p->line('Kind attn: ' . trim(($q['contact_name'] ?: '') . '   ' . ($q['contact_email'] ?: '') . '   ' . ($q['contact_mobile'] ?: '')), 9, false, 14, [80, 80, 80]);
    if ($q['site_location']) $p->line('Location: ' . $q['site_location'] . ' (' . (lk_options_or('quote_location_type', QUOTE_LOCATION_TYPES)[$q['location_type']] ?? $q['location_type']) . ')', 9, false, 14, [80, 80, 80]);
    $p->gap(4);
    if ($q['subject']) { $p->line('Subject: ' . $q['subject'], 10, true, 16); }
    $p->gap(4);
    // ---- line-item table ----
    $x0 = $ml; $x2 = $right; $tw = $x2 - $x0;
    $cols = [$x0, $x0 + 26, $x0 + $tw * 0.50, $x0 + $tw * 0.60, $x0 + $tw * 0.74, $x2];  // Sr | Desc | Qty | Unit | Rate | Amount(right)
    $hy = $p->y; $rowH = 18;
    $p->rectFill($x0, $hy, $tw, $rowH, [30, 64, 175]);
    $p->y = $hy + 4;
    $p->text($cols[0] + 3, 'Sr', 9, true, [255, 255, 255]);
    $p->text($cols[1] + 3, 'Description', 9, true, [255, 255, 255]);
    $p->text($cols[2] + 3, 'Qty', 9, true, [255, 255, 255], $cols[3] - 3, 'R');
    $p->text($cols[3] + 3, 'Unit', 9, true, [255, 255, 255]);
    $p->text($cols[4] + 3, 'Rate', 9, true, [255, 255, 255], $cols[5] - 3, 'R');
    $p->text($cols[4] + 3, 'Amount', 9, true, [255, 255, 255], $x2 - 3, 'R');
    $p->y = $hy + $rowH;
    $sr = 0;
    foreach ($lines as $l) {
        $sr++;
        $desc = trim(($l['description'] ?: (lk_options_or('inspection_type', INSPECTION_TYPES)[$l['service_type']] ?? $l['service_type'])) . ($l['order_type'] === 'OPEN' ? '  [ARC / open order]' : ''));
        $wrapped = $p->wrap($desc, 9, $cols[2] - $cols[1] - 6);
        $rh = max($rowH, count($wrapped) * 12 + 6);
        $p->needSpace($rh);
        $top = $p->y;
        // single-row cells (all at $top)
        $p->y = $top + 3;
        $p->text($cols[0] + 3, (string)$sr, 9);
        $p->text($cols[2] + 3, rtrim(rtrim(number_format((float)$l['qty'], 2), '0'), '.'), 9, false, null, $cols[3] - 3, 'R');
        $p->text($cols[3] + 3, lk_options_or('charge_unit', CHARGE_UNITS)[$l['unit']] ?? $l['unit'], 9);
        $p->text($cols[4] + 3, number_format((float)$l['rate'], 0), 9, false, null, $cols[5] - 3, 'R');
        $p->text($cols[4] + 3, number_format((float)$l['amount'], 0), 9, false, null, $x2 - 3, 'R');
        // wrapped description lines
        $dy = $top + 3;
        foreach ($wrapped as $wl) { $p->y = $dy; $p->text($cols[1] + 3, $wl, 9); $dy += 12; }
        $p->y = $top + $rh;
        $p->lineAt($x0, $p->y, $x2, $p->y, [225, 225, 225]);
    }
    // ---- totals ----
    $p->gap(4);
    $tx = $x0 + $tw * 0.60;
    $totRow = function ($lbl, $val, $bold = false) use ($p, $tx, $x2) {
        $p->needSpace(15);
        $p->text($tx, $lbl, $bold ? 10 : 9, $bold, null, $x2 - 90, 'R');
        $p->text($x2 - 88, $val, $bold ? 10 : 9, $bold, null, $x2, 'R');
        $p->y += 15;
    };
    $cur = $q['currency'] ?: 'INR';
    $totRow('Subtotal', number_format((float)$q['subtotal'], 2));
    $totRow('GST (' . rtrim(rtrim(number_format((float)$q['gst_pct'], 2), '0'), '.') . '%)', number_format((float)$q['gst_amount'], 2));
    $totRow('Total (' . $cur . ')', number_format((float)$q['total_amount'], 2), true);
    $p->gap(2);
    $p->line('Amount in words: ' . amount_in_words_inr((float)$q['total_amount']), 9, false, 14, [70, 70, 70]);
    $p->gap(6);
    // ---- terms ----
    $p->hr();
    $p->line('Terms & Conditions', 10, true, 15);
    $terms = [];
    if ($q['payment_terms']) $terms[] = 'Payment: ' . $q['payment_terms'] . '.';
    if ((float)$q['advance_pct'] > 0 || $q['advance_required']) $terms[] = 'Advance: ' . rtrim(rtrim(number_format((float)$q['advance_pct'], 2), '0'), '.') . '%' . ($q['advance_required'] ? ' (to be received before scheduling).' : '.');
    if ($q['report_vs_payment']) $terms[] = 'Deliverables / reports are released against payment.';
    $terms[] = 'Validity: ' . (int)$q['validity_days'] . ' days from the date of this quotation.';
    $terms[] = 'Taxes as applicable. Prices in ' . $cur . '.';
    $i = 0;
    foreach ($terms as $t) { $i++; foreach ($p->wrap("$i. $t", 9, $p->contentW()) as $wl) $p->line($wl, 9, false, 12, [70, 70, 70]); }
    // The free-text terms the author typed on the quote form. These were saved but
    // never printed on the PDF — so a clause someone deliberately added silently
    // vanished from the customer's copy. Printed here, keeping the author's own
    // line breaks; their own numbering (if any) is preserved, so no "1." is forced.
    $free = trim((string)($q['terms_conditions'] ?? ''));
    if ($free !== '') {
        $p->gap(4);
        foreach (preg_split('/\r\n|\r|\n/', $free) as $tl) {
            if (rtrim($tl) === '') { $p->gap(3); continue; }
            foreach ($p->wrap(rtrim($tl), 9, $p->contentW()) as $wl) $p->line($wl, 9, false, 12, [70, 70, 70]);
        }
    }
    // ---- signature ----
    $p->gap(18); $p->needSpace(90);
    $sy = $p->y;
    $p->text($ml, 'For ' . (($lh['name'] ?? '') ?: app_name()), 10, true);
    if (!empty($sig['img'])) {
        $name = $p->addJpeg($sig['img']);
        if ($name) { [$iw, $ih] = $p->imgDim($name); $dw = 120; $dh = $ih > 0 ? min(55, $dw * $ih / max(1, $iw)) : 40; $p->drawImage($name, $ml, $sy + 6, $dw, $dh); }
    }
    $p->y = $sy + 66;
    $p->lineAt($ml, $p->y, $ml + 150, $p->y, [120, 120, 120]);
    $p->y += 3;
    if (!empty($sig['name'])) $p->line($sig['name'], 9, true, 12);
    if (!empty($sig['desig'])) $p->line($sig['desig'], 8, false, 12, [90, 90, 90]);
    $p->line('Authorised Signatory', 8, false, 12, [90, 90, 90]);
    if (!empty($lh['footer'])) { $p->gap(10); $p->hr(); foreach ($p->wrap($lh['footer'], 8, $p->contentW()) as $wl) $p->line($wl, 8, false, 11, [120, 120, 120]); }
    return $p->output();
}
