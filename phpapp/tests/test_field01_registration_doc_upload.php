<?php
// Field-finding #1 — Client Registration → Registration tab had no way to upload the registration
// DOCUMENT (the scanned GST/PAN/ISO/licence certificate). Now each registration can carry its file:
// on the add form, or attached later to an existing row; a "View" link streams it back. Stored
// in-row as base64 (like a lead/quote file); the list loads metadata only, never the blob.
t_section('Field #1 — upload a document with a client registration');

if (function_exists('contracts_migrate')) contracts_migrate();

// The file columns now exist on partner_registrations.
$cols = array_map(fn($r) => $r['name'], ops_all("PRAGMA table_info(partner_registrations)"));
foreach (['file_name','mime','file_data','uploaded_by','uploaded_at'] as $c)
    t_ok(in_array($c, $cols, true), "partner_registrations.$c column exists");

// A registration can store its document, and it reads back intact.
$pdo = db();
$pdo->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status) VALUES ('RegDoc Co','RegDoc Co',1,'ACTIVE')")->execute();
$pid = (int)$pdo->lastInsertId();
$bytes = "%PDF-1.4 fake gst certificate bytes";
$pdo->prepare("INSERT INTO partner_registrations (partner_id,doc_type,number,valid_to,notes,file_name,mime,file_data,uploaded_by,uploaded_at)
               VALUES (?,?,?,?,?,?,?,?,?,?)")
   ->execute([$pid,'GSTIN','24ADUPL3517E2ZJ','2027-03-31','','gst.pdf','application/pdf', base64_encode($bytes), 'Tester', date('c')]);
$rid = (int)$pdo->lastInsertId();
$stored = ops_one("SELECT file_name, mime, file_data FROM partner_registrations WHERE id=?", [$rid]);
t_eq('gst.pdf', $stored['file_name'], 'the document filename is stored');
t_eq($bytes, base64_decode($stored['file_data']), 'the document bytes round-trip intact');

// The list query loads metadata only — never the (potentially 8 MB) blob.
$listRow = ops_one("SELECT id, partner_id, doc_type, number, valid_to, notes, file_name, mime, uploaded_by, uploaded_at
                    FROM partner_registrations WHERE id=?", [$rid]);
t_ok(!array_key_exists('file_data', $listRow), 'the registration list never loads the file blob');
t_eq('gst.pdf', $listRow['file_name'], 'the list still knows a file is attached (by name)');

// --- Wiring ---
$idx = file_get_contents(__DIR__ . '/../index.php');
t_ok(strpos($idx, "\$route === 'partner-reg-file'") !== false, 'a route serves / attaches the registration document');
t_ok(strpos($idx, "\$kind === 'registration' && !empty(\$_FILES['reg_file']['tmp_name'])") !== false,
     'the add-registration handler stores an uploaded document');
t_ok(strpos($idx, "SELECT id, partner_id, doc_type, number, valid_to, notes, file_name, mime, uploaded_by, uploaded_at") !== false,
     'the registrations are loaded metadata-only (no blob in the list)');
// The serve path is view-gated; the attach path is edit-gated.
$rf = strpos($idx, "\$route === 'partner-reg-file'");
$blk = substr($idx, $rf, 2400);
t_ok(strpos($blk, "can('mod.clients.view')") !== false && strpos($blk, "can('mod.clients.edit')") !== false,
     'viewing needs directory view rights; attaching needs edit rights');
t_ok(strpos($blk, '8388608') !== false, 'an oversized (>8 MB) upload is rejected');

$src = file_get_contents(__DIR__ . '/../lib/contracts.php');
t_ok(strpos($src, "ensure_column('partner_registrations', 'file_data'") !== false,
     'the file columns are added by migration (existing installs upgrade cleanly)');

$view = file_get_contents(__DIR__ . '/../views/detail.php');
t_ok(strpos($view, 'enctype="multipart/form-data"') !== false && strpos($view, 'name="reg_file"') !== false,
     'the Add-registration form accepts a document file');
t_ok(strpos($view, '/partner-reg-file?id=') !== false && strpos($view, '📎 View') !== false,
     'the tab shows a View link (attached) or an Attach form (not yet)');

// Clean up (shared DB).
$pdo->prepare("DELETE FROM partner_registrations WHERE id=?")->execute([$rid]);
$pdo->prepare("DELETE FROM business_partners WHERE id=?")->execute([$pid]);
