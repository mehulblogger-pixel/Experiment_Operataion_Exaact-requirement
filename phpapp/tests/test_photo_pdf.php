<?php
// Photos must actually appear in the report PDF, whatever format they arrive in.
// The PDF writer only embeds baseline JPEG, so every image path funnels through
// image_to_jpeg() (GD, then Imagick for HEIC/TIFF/…). A format nothing can decode
// must degrade to a labelled placeholder — never a silent blank box.
t_section('photos in the report PDF');

// Build a small PNG (with transparency) and a JPEG using GD, as a phone would send.
$mkPng = function () {
    $im = imagecreatetruecolor(60, 40);
    imagesavealpha($im, true);
    $trans = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefill($im, 0, 0, $trans);
    $red = imagecolorallocate($im, 200, 30, 30);
    imagefilledrectangle($im, 10, 10, 50, 30, $red);
    ob_start(); imagepng($im); $b = ob_get_clean(); imagedestroy($im); return $b;
};
$mkJpg = function () {
    $im = imagecreatetruecolor(80, 50);
    $blue = imagecolorallocate($im, 20, 60, 200);
    imagefilledrectangle($im, 0, 0, 80, 50, $blue);
    ob_start(); imagejpeg($im, null, 90); $b = ob_get_clean(); imagedestroy($im); return $b;
};

if (!function_exists('imagecreatefromstring')) { t_ok(true, 'GD not present — skipping photo PDF checks'); return; }

$png = $mkPng(); $jpg = $mkJpg();
t_ok(strncmp($png, "\x89PNG", 4) === 0, 'built a test PNG');
t_ok(strncmp($jpg, "\xFF\xD8", 2) === 0, 'built a test JPEG');

// 1) The shared decoder turns any decodable format into embeddable JPEG.
[$oJpg, $e1] = image_to_jpeg($jpg, 1600, 82);
t_eq($e1, '', 'JPEG decodes with no error');
t_ok(strncmp($oJpg, "\xFF\xD8", 2) === 0, 'JPEG stays JPEG');

[$oPng, $e2] = image_to_jpeg($png, 1600, 82);
t_eq($e2, '', 'PNG decodes with no error');
t_ok(strncmp($oPng, "\xFF\xD8", 2) === 0, 'PNG is converted to JPEG (embeddable)');

// 2) Undecodable bytes must not crash and must report a reason (→ PDF placeholder).
[$oBad, $e3] = image_to_jpeg("not-an-image-\x00\x01\x02", 1600, 82);
t_eq($oBad, '', 'garbage bytes yield no JPEG');
t_ok($e3 !== '', 'garbage bytes yield a human-readable reason (drives the PDF placeholder)');

// 3) Upload compression normalises a PNG to JPEG so it embeds later.
[$cb, $cm, $note] = idems_compress_image($png, 'image/png');
t_eq($cm, 'image/jpeg', 'idems_compress_image converts PNG upload to JPEG');
t_ok(strncmp($cb, "\xFF\xD8", 2) === 0, 'stored bytes are JPEG');

// 4) A non-image upload passes straight through untouched.
[$pb, $pm] = idems_compress_image('%PDF-1.4 ...', 'application/pdf');
t_eq($pm, 'application/pdf', 'a PDF upload is not touched by image compression');

// 5) addJpeg accepts the converted bytes and reports real dimensions.
$p = new SimplePDF();
$nm = $p->addJpeg($oPng);
t_ok($nm !== '', 'the PDF writer accepts the converted photo');
[$iw, $ih] = $p->imgDim($nm);
t_ok($iw > 0 && $ih > 0, 'the embedded photo has real dimensions');

// 6) End-to-end: a report with a photo produces a PDF that embeds an image XObject.
$typeId = idems_build_fire_extinguisher_report();
$pdo = db();
$pdo->prepare("INSERT INTO report_docs (report_type_id,type_code,irn,status,data,created_at)
               VALUES (?,?,?,?,?,?)")
    ->execute([$typeId, 'FEXT', 'PHOTOTEST-1', 'draft', json_encode([]), date('c')]);
$docId = (int)$pdo->lastInsertId();
// find the photo field on this type
$photoKey = ops_val("SELECT fkey FROM report_fields f JOIN report_sections s ON s.id=f.section_id
                     WHERE s.report_type_id=? AND f.ftype='photo' ORDER BY f.id LIMIT 1", [$typeId]);
if ($photoKey) {
    $pdo->prepare("INSERT INTO report_files (report_doc_id,field_key,kind,file_name,mime,data,caption,created_at)
                   VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$docId, $photoKey, 'photo', 'evidence.jpg', 'image/jpeg',
                   'data:image/jpeg;base64,' . base64_encode($oPng), 'Nameplate close-up', date('c')]);
    t_ok(true, "attached a photo to the '$photoKey' field with a caption");
    $doc = ops_one("SELECT * FROM report_docs WHERE id=?", [$docId]);
    [$secs, $flds] = idems_render_schema($doc);
    $pdfBytes = report_pdf_build($doc, $secs, $flds,
        json_decode($doc['data'] ?: '[]', true) ?: [], idems_doc_files($doc['id']),
        ['name' => 'Test'], idems_report_signatures($doc), '');
    t_ok(strlen($pdfBytes) > 800 && strncmp($pdfBytes, '%PDF', 4) === 0, 'a PDF was generated');
    t_ok(strpos($pdfBytes, '/Image') !== false || strpos($pdfBytes, 'DCTDecode') !== false,
         'the PDF embeds an image (photo is inserted, not a blank box)');
} else {
    t_ok(true, 'no photo field on the type — skipping end-to-end PDF check');
}
