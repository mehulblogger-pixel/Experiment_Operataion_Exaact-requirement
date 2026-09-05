<?php
// =========================================================================
//  MGH Hire — minimal, dependency-free SMTP sender.
//
//  Reliable email delivery via a real mail server (Gmail, Outlook 365,
//  SendGrid, Amazon SES, or any company SMTP) — no PHPMailer, no Composer.
//  Speaks SMTP over a socket: implicit TLS (port 465) or STARTTLS (587/25),
//  with AUTH LOGIN. Returns [ok, errorText] so the outbox can record why a
//  message failed. If SMTP isn't configured the caller falls back to mail().
// =========================================================================

// Are full SMTP settings present?
function smtp_configured() {
    return setting('smtp_host','') !== '' && (int)setting('smtp_port','0') > 0;
}

// Send one HTML message. Returns [bool ok, string error].
function smtp_send($toAddr, $toName, $subject, $htmlBody, $fromAddr, $fromName) {
    $host = setting('smtp_host','');
    $port = (int)setting('smtp_port','587');
    $user = setting('smtp_user','');
    $pass = setting('smtp_pass','');
    $sec  = setting('smtp_security','tls');   // 'ssl' | 'tls' | 'none'
    $timeout = 15;

    $remote = ($sec === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return [false, "connect failed: $errstr ($errno)"];
    stream_set_timeout($fp, $timeout);

    $err = '';
    $read = function() use ($fp) {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;   // last line of reply
        }
        return $data;
    };
    $cmd = function($c, $expect) use ($fp, $read, &$err) {
        if ($c !== null) fwrite($fp, $c . "\r\n");
        $resp = $read();
        $code = (int)substr($resp, 0, 3);
        if ($expect && !in_array($code, (array)$expect, true)) { $err = trim($resp); return false; }
        return true;
    };

    $host_ehlo = $_SERVER['SERVER_NAME'] ?? (gethostname() ?: 'localhost');
    try {
        if (!$cmd(null, 220)) throw new Exception($err);
        if (!$cmd("EHLO $host_ehlo", 250)) throw new Exception($err);

        if ($sec === 'tls') {
            if (!$cmd("STARTTLS", 220)) throw new Exception($err);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT
                    | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT))
                throw new Exception('STARTTLS negotiation failed');
            if (!$cmd("EHLO $host_ehlo", 250)) throw new Exception($err);
        }

        if ($user !== '') {
            if (!$cmd("AUTH LOGIN", 334)) throw new Exception('AUTH not accepted: '.$err);
            if (!$cmd(base64_encode($user), 334)) throw new Exception('username rejected: '.$err);
            if (!$cmd(base64_encode($pass), 235)) throw new Exception('login failed: '.$err);
        }

        if (!$cmd("MAIL FROM:<$fromAddr>", 250)) throw new Exception($err);
        if (!$cmd("RCPT TO:<$toAddr>", [250,251])) throw new Exception($err);
        if (!$cmd("DATA", 354)) throw new Exception($err);

        $headers  = 'From: ' . smtp_hdr($fromName) . " <$fromAddr>\r\n";
        $headers .= 'To: ' . ($toName ? smtp_hdr($toName) . " <$toAddr>" : $toAddr) . "\r\n";
        $headers .= 'Subject: ' . smtp_hdr($subject) . "\r\n";
        $headers .= 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
        $headers .= 'Date: ' . date('r') . "\r\n";
        // Dot-stuffing so a line starting with "." can't end DATA early.
        $body = preg_replace('/^\./m', '..', str_replace("\n", "\r\n", $htmlBody));
        fwrite($fp, $headers . "\r\n" . $body . "\r\n.\r\n");
        if (!$cmd(null, 250)) throw new Exception($err);
        $cmd("QUIT", 0);
        fclose($fp);
        return [true, ''];
    } catch (Throwable $e) {
        @fclose($fp);
        return [false, mb_substr($e->getMessage(), 0, 240)];
    }
}

function smtp_hdr($s) { return '=?UTF-8?B?' . base64_encode($s) . '?='; }
