<?php
// ============================================================================
//  "Open it in my own mailbox"
//
//  Some mail must genuinely come from the person, not from the company robot:
//  a quotation, a follow-up chase, a note to a client. The client should see
//  vishal.shah@example.com, reply to Vishal, and Vishal should find the message
//  in his own Sent Items.
//
//  Doing that by storing every user's mailbox password would put dozens of live
//  credentials in the database on shared hosting. Instead the app hands the
//  finished message to whatever mail client the person already uses:
//
//    compose_mailto()  — a mailto: link. Opens the default mail app with To,
//                        Cc, Subject and Body filled in. Cannot carry a file:
//                        no browser or OS allows an attachment on a mailto.
//    compose_eml()     — a .eml draft. Opens in Outlook as an editable message
//                        WITH the attachment already on it, and (thanks to the
//                        X-Unsent header) a working Send button. This is the
//                        one to use whenever a PDF has to go with the message.
//    compose_owa()     — an Outlook-on-the-web compose deep link, for people
//                        who live in OWA and have no desktop client.
//
//  Nothing here sends anything. The user presses Send in their own client, so
//  the message is theirs — sender, signature, Sent Items and all.
// ============================================================================

// mailto: bodies travel in the URL, and mail clients / browsers truncate long
// ones (Outlook stops around 2 KB). Keep well under that and say so if we cut.
const MAILTO_BODY_MAX = 1800;

function compose_mailto($to, $subject, $body, $cc = '') {
    $body = (string)$body;
    if (strlen($body) > MAILTO_BODY_MAX) {
        $body = substr($body, 0, MAILTO_BODY_MAX) . "\n\n[...continues — use \"Open with attachment\" for the full message]";
    }
    $qs = [];
    if ($cc !== '')      $qs[] = 'cc=' . rawurlencode($cc);
    if ($subject !== '') $qs[] = 'subject=' . rawurlencode($subject);
    if ($body !== '')    $qs[] = 'body=' . rawurlencode($body);
    return 'mailto:' . rawurlencode((string)$to) . ($qs ? '?' . implode('&', $qs) : '');
}

// Outlook on the web. Handy when the browser has no mailto handler registered.
function compose_owa($to, $subject, $body, $cc = '') {
    $p = ['to' => (string)$to, 'subject' => (string)$subject, 'body' => (string)$body];
    if ($cc !== '') $p['cc'] = $cc;
    return 'https://outlook.office.com/mail/deeplink/compose?' . http_build_query($p);
}

// Encode a header value that may contain non-ASCII (names, subjects).
function compose_hdr($s) {
    $s = (string)$s;
    return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}
function compose_addr($email, $name = '') {
    $email = trim((string)$email);
    return $name !== '' ? compose_hdr($name) . ' <' . $email . '>' : $email;
}

// ---------------------------------------------------------------------------
//  A ready-to-send draft, as a .eml file.
//
//  X-Unsent: 1 is what makes Outlook open it as a draft with Send enabled
//  rather than as a received message. From is set to the logged-in user so the
//  right account is selected; Outlook still sends through their own profile.
//  $attachments = [ ['name'=>..., 'data'=>binary, 'type'=>mime], ... ]
// ---------------------------------------------------------------------------
function compose_eml($to, $subject, $body, $cc = '', array $attachments = [], $fromEmail = '', $fromName = '') {
    $eol = "\r\n";
    $boundary = 'x' . bin2hex(random_bytes(12));
    $h  = 'MIME-Version: 1.0' . $eol;
    $h .= 'X-Unsent: 1' . $eol;                       // Outlook: treat as an unsent draft
    if ($fromEmail !== '') $h .= 'From: ' . compose_addr($fromEmail, $fromName) . $eol;
    $h .= 'To: ' . (string)$to . $eol;
    if ($cc !== '') $h .= 'Cc: ' . $cc . $eol;
    $h .= 'Subject: ' . compose_hdr((string)$subject) . $eol;
    $h .= 'Date: ' . date('r') . $eol;

    if (!$attachments) {
        $h .= 'Content-Type: text/plain; charset=UTF-8' . $eol;
        $h .= 'Content-Transfer-Encoding: 8bit' . $eol . $eol;
        return $h . str_replace("\n", $eol, (string)$body);
    }
    $h .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . $eol . $eol;
    $m  = '--' . $boundary . $eol;
    $m .= 'Content-Type: text/plain; charset=UTF-8' . $eol;
    $m .= 'Content-Transfer-Encoding: 8bit' . $eol . $eol;
    $m .= str_replace("\n", $eol, (string)$body) . $eol . $eol;
    foreach ($attachments as $a) {
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', (string)($a['name'] ?? 'attachment'));
        $m .= '--' . $boundary . $eol;
        $m .= 'Content-Type: ' . ($a['type'] ?? 'application/octet-stream') . '; name="' . $name . '"' . $eol;
        $m .= 'Content-Transfer-Encoding: base64' . $eol;
        $m .= 'Content-Disposition: attachment; filename="' . $name . '"' . $eol . $eol;
        $m .= chunk_split(base64_encode((string)($a['data'] ?? '')), 76, $eol) . $eol;
    }
    $m .= '--' . $boundary . '--' . $eol;
    return $h . $m;
}

// Stream a .eml as a download, so the browser hands it to the mail client.
function compose_eml_download($filename, $eml) {
    $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
    if (substr($safe, -4) !== '.eml') $safe .= '.eml';
    header('Content-Type: message/rfc822');
    header('Content-Disposition: attachment; filename="' . $safe . '"');
    header('Content-Length: ' . strlen($eml));
    echo $eml;
}

// The address the current user sends from. Falls back to the company mailbox so
// the draft still opens sensibly for someone with no e-mail on their profile.
function compose_from() {
    $u = current_user();
    $email = trim((string)($u['email'] ?? ''));
    $name  = $u ? user_name($u) : '';
    if ($email === '') $email = (string)(setting_get('smtp_from', '') ?: setting_get('smtp_user', ''));
    return [$email, $name];
}
// True when we can offer "open in my mailbox" as a personal send.
function compose_can_personalise() {
    $u = current_user();
    return $u && trim((string)($u['email'] ?? '')) !== '';
}
