<?php
// =========================================================================
//  MGH Hire — notifications / email.
//
//  Every notification is written to the `emails` outbox first (a durable,
//  visible record), then delivery is attempted. On a host where PHP mail() is
//  available and email is switched on, it goes out immediately; otherwise the
//  message waits in the outbox as 'pending' so nothing is ever silently lost.
//  (SMTP can be layered in later without changing any caller.)
// =========================================================================

function mail_enabled() { return setting('mail_enabled','0') === '1'; }

// Queue + attempt one message. Returns the outbox row id.
function notify($toAddr, $toName, $subject, $bodyHtml, $event = '', $refId = 0) {
    $toAddr = trim((string)$toAddr);
    $id = 0;
    try {
        db()->prepare("INSERT INTO emails (to_addr,to_name,subject,body,event,ref_id,status,created_at)
                       VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$toAddr,$toName,$subject,$bodyHtml,$event,$refId,'pending',now()]);
        $id = (int)db()->lastInsertId();
    } catch (Throwable $e) { return 0; }

    if (!$toAddr || !filter_var($toAddr, FILTER_VALIDATE_EMAIL)) {
        mail_mark($id, 'skipped', 'no valid recipient');
        return $id;
    }
    if (!mail_enabled()) return $id;                       // stays 'pending'
    $from = trim((string)setting('mail_from_email',''));
    if (!$from) { mail_mark($id, 'pending', 'sender not configured'); return $id; }

    $fromName = setting('mail_from_name','MGH Hire');
    $headers  = 'MIME-Version: 1.0' . "\r\n"
              . 'Content-Type: text/html; charset=UTF-8' . "\r\n"
              . 'From: ' . mail_encode_name($fromName) . ' <' . $from . '>' . "\r\n"
              . 'Reply-To: ' . $from . "\r\n";
    $ok = false;
    try { $ok = @mail($toAddr, mail_encode_subject($subject), mail_wrap($subject,$bodyHtml), $headers); }
    catch (Throwable $e) { $ok = false; }
    mail_mark($id, $ok ? 'sent' : 'failed', $ok ? '' : 'mail() returned false / not available on this host');
    return $id;
}

function mail_mark($id, $status, $error = '') {
    try {
        db()->prepare("UPDATE emails SET status=?, error=?, sent_at=? WHERE id=?")
            ->execute([$status, mb_substr($error,0,240), $status==='sent'?now():'', $id]);
    } catch (Throwable $e) {}
}

function mail_encode_name($n) { return '=?UTF-8?B?' . base64_encode($n) . '?='; }
function mail_encode_subject($s){ return '=?UTF-8?B?' . base64_encode($s) . '?='; }

// A simple branded HTML wrapper so every email looks consistent.
function mail_wrap($title, $inner) {
    $brand = brand_color();
    $product = e(product_name());
    $company = e(setting('company_name',''));
    return '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden">'
      . '<div style="background:'.e($brand).';color:#fff;padding:14px 20px;font-weight:700;font-size:16px">'.$product.'</div>'
      . '<div style="padding:20px;color:#0f172a;font-size:14px;line-height:1.55">'.$inner.'</div>'
      . '<div style="padding:12px 20px;border-top:1px solid #e2e8f0;color:#94a3b8;font-size:12px">'
      . ($company ?: $product) . ' · automated message, please do not reply.</div></div>';
}

// ---- Ready-made notifications for the key events -------------------------
function notify_requisition_approved($req) {
    if (!$req['raised_by']) return;
    // The raiser is identified by display name; e-mail them if we can match a user.
    $u = db()->prepare("SELECT email,name FROM users WHERE name=? AND email<>'' LIMIT 1");
    $u->execute([$req['raised_by']]); $row = $u->fetch();
    if (!$row) return;
    notify($row['email'], $row['name'],
        'Requisition '.$req['code'].' approved',
        '<p>Hello '.e($row['name']).',</p><p>Your requisition <b>'.e($req['code']).' — '.e($req['title']).'</b> has been <b>approved</b>. You can now add candidates.</p>',
        'requisition_approved', (int)$req['id']);
}
function notify_new_application($cand, $req) {
    $inbox = trim((string)setting('mail_hr_inbox',''));
    if (!$inbox) return;
    notify($inbox, 'Recruitment',
        'New application: '.$cand['name'].' — '.($req['title'] ?? ''),
        '<p>A new application was received via the careers page.</p>'
        .'<p><b>'.e($cand['name']).'</b><br>'.e($cand['email']).' · '.e($cand['phone']).'</p>'
        .'<p>Position: <b>'.e($req['title'] ?? '—').'</b> ('.e($req['code'] ?? '').')</p>',
        'new_application', (int)$cand['id']);
}
function notify_interview($cand, $round, $when) {
    if (!$cand['email']) return;
    notify($cand['email'], $cand['name'],
        'Interview scheduled — '.$round,
        '<p>Dear '.e($cand['name']).',</p><p>Your <b>'.e($round).'</b> interview has been scheduled'
        .($when? ' for <b>'.e(fdate($when,true)).'</b>':'').'. Our team will share the details.</p>',
        'interview', (int)$cand['id']);
}
function notify_offer($cand, $offer) {
    if (!$cand['email']) return;
    notify($cand['email'], $cand['name'],
        'Your offer',
        '<p>Dear '.e($cand['name']).',</p><p>We are pleased to extend an offer'
        .($offer['ctc']? ' with a CTC of <b>'.e(currency().$offer['ctc']).'</b>':'')
        .($offer['joining_date']? ', joining on <b>'.e(fdate($offer['joining_date'])).'</b>':'')
        .'. The formal letter follows.</p>',
        'offer', (int)$cand['id']);
}
function notify_rejected($cand) {
    if (!$cand['email']) return;
    notify($cand['email'], $cand['name'],
        'Update on your application',
        '<p>Dear '.e($cand['name']).',</p><p>Thank you for your interest. After careful consideration we will not be moving forward at this stage. We wish you every success.</p>',
        'rejected', (int)$cand['id']);
}
