<?php
// =========================================================================
//  MGH Hire — résumé (CV) auto-reading.
//
//  Turns an uploaded CV or pasted text into structured fields (name, email,
//  phone, skills) so a candidate record fills itself. Dependency-free: plain
//  text and .docx are read directly; simple .pdf text is extracted where
//  possible; anything unreadable degrades to "just the fields we could find",
//  never an error. This is a rules-based reader — an AI extractor can be
//  layered on later behind the same function.
// =========================================================================

// Pull readable text out of an uploaded file.
function cv_text_from_file($tmpPath, $fileName, $mime = '') {
    if (!$tmpPath || !is_readable($tmpPath)) return '';
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $raw = file_get_contents($tmpPath);
    if ($raw === false) return '';

    // Plain text
    if ($ext === 'txt' || strpos($mime, 'text/') === 0) return cv_clean($raw);

    // .docx — a zip; the text lives in word/document.xml
    if ($ext === 'docx' && class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if (@$zip->open($tmpPath) === true) {
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            if ($xml) {
                $xml = preg_replace('/<\/w:p>/', "\n", $xml);
                return cv_clean(strip_tags($xml));
            }
        }
    }

    // .pdf — best-effort text from uncompressed content streams.
    if ($ext === 'pdf' || strpos($mime, 'pdf') !== false) {
        return cv_clean(cv_pdf_text($raw));
    }

    // Unknown — try to salvage any readable ASCII.
    return cv_clean(preg_replace('/[^\P{C}\n]+/u', ' ', $raw));
}

// Naive PDF text: capture strings inside ( ) in text-showing operators.
function cv_pdf_text($raw) {
    $out = '';
    if (preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)/s', $raw, $m)) {
        foreach ($m[0] as $s) {
            $s = substr($s, 1, -1);
            $s = str_replace(['\\(','\\)','\\\\'], ['(',')','\\'], $s);
            $out .= $s . ' ';
        }
    }
    // If almost nothing came back the PDF is compressed — return what we have.
    return $out;
}

function cv_clean($t) {
    $t = preg_replace('/\r\n?/', "\n", $t);
    $t = preg_replace('/[ \t]+/', ' ', $t);
    $t = preg_replace('/\n{3,}/', "\n\n", $t);
    return trim($t);
}

// The extractor: text -> ['name','email','phone','skills'].
function cv_extract($text) {
    $out = ['name' => '', 'email' => '', 'phone' => '', 'skills' => ''];
    if (!$text) return $out;

    // Email
    if (preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $text, $m)) $out['email'] = strtolower($m[0]);

    // Phone — prefer a +country number, else a 10+ digit run.
    if (preg_match('/(\+\d{1,3}[\s\-]?)?(\d[\d\s\-]{8,13}\d)/', $text, $m)) {
        $digits = preg_replace('/[^\d+]/', '', $m[0]);
        if (strlen(preg_replace('/\D/', '', $digits)) >= 10) $out['phone'] = $digits;
    }

    // Name — "Name: X", else the first plausible line.
    if (preg_match('/name\s*[:\-]\s*([A-Z][A-Za-z.\'\-]+(?:\s+[A-Z][A-Za-z.\'\-]+){1,3})/', $text, $m)) {
        $out['name'] = trim($m[1]);
    } else {
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            // 2–4 capitalised words (Title Case OR ALL CAPS), no digits/@,
            // and not an obvious heading or job-title line.
            if (preg_match('/^([A-Z][A-Za-z.\'\-]+(?:\s+[A-Z][A-Za-z.\'\-]+){1,3})$/', $line)
                && !preg_match('/[@\d]/', $line)
                && !preg_match('/\b(resume|curriculum|vitae|profile|address|email|phone|mobile|objective|summary|executive|manager|engineer|accountant|officer|analyst|developer|consultant|assistant|director)\b/i', $line)) {
                $out['name'] = $line; break;
            }
        }
    }

    // Skills — a "Skills" section, else match a common keyword list.
    if (preg_match('/skills?\s*[:\-]?\s*\n?(.{0,300})/i', $text, $m)) {
        $chunk = preg_split('/\n\s*\n/', $m[1])[0];
        $chunk = trim(preg_replace('/\s+/', ' ', $chunk));
        if (strlen($chunk) > 3) $out['skills'] = mb_substr($chunk, 0, 240);
    }
    if (!$out['skills']) {
        $kw = ['php','python','java','javascript','sql','excel','tally','accounting','sap','autocad',
               'marketing','sales','recruitment','hr','payroll','communication','leadership','node','react'];
        $found = [];
        foreach ($kw as $k) if (preg_match('/\b'.preg_quote($k,'/').'\b/i', $text)) $found[] = ucfirst($k);
        if ($found) $out['skills'] = implode(', ', array_slice($found, 0, 12));
    }

    return $out;
}

// Convenience: extract straight from an uploaded file.
function cv_extract_file($tmpPath, $fileName, $mime = '') {
    return cv_extract(cv_text_from_file($tmpPath, $fileName, $mime));
}
