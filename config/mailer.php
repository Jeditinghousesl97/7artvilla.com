<?php
/**
 * Mailer  -  reads SMTP config from site_settings and sends email.
 * Falls back to PHP mail() if no SMTP host is configured.
 */

function send_mail(
    string $to_email,
    string $to_name,
    string $subject,
    string $html_body,
    string $text_body = ''
): bool {
    // Load SMTP settings once
    static $cfg = null;
    if ($cfg === null) {
        try {
            $pdo  = db();
            $keys = ['smtp_host','smtp_port','smtp_encryption','smtp_user','smtp_pass',
                     'smtp_from_name','smtp_from_email'];
            $ph   = implode(',', array_fill(0, count($keys), '?'));
            $stmt = $pdo->prepare("SELECT setting_key, setting_val FROM site_settings WHERE setting_key IN ($ph)");
            $stmt->execute($keys);
            $cfg  = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e) {
            $cfg = [];
        }
    }

    $from_email = $cfg['smtp_from_email'] ?? 'noreply@7artvilla.com';
    $from_name  = $cfg['smtp_from_name']  ?? '7 Art Villa';
    $text_body  = $text_body ?: strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $html_body));

    if (!empty($cfg['smtp_host'])) {
        return _smtp_send($cfg, $from_email, $from_name, $to_email, $to_name, $subject, $html_body, $text_body);
    }

    return _php_mail($from_email, $from_name, $to_email, $to_name, $subject, $html_body, $text_body);
}

// PHP mail() fallback 
function _php_mail(
    string $from_email, string $from_name,
    string $to_email,   string $to_name,
    string $subject,    string $html,     string $text
): bool {
    $boundary = md5(uniqid('b', true));
    $enc_from = '=?UTF-8?B?' . base64_encode($from_name) . '?=';
    $enc_subj = '=?UTF-8?B?' . base64_encode($subject)   . '?=';

    $headers  = "From: {$enc_from} <{$from_email}>\r\n";
    $headers .= "Reply-To: {$from_email}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "X-Mailer: 7ArtVilla/1.0\r\n";

    $body  = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$text}\r\n";
    $body .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n";
    $body .= "--{$boundary}--";

    return @mail($to_email, $enc_subj, $body, $headers);
}

// SMTP sender 
function _smtp_send(
    array  $cfg,
    string $from_email, string $from_name,
    string $to_email,   string $to_name,
    string $subject,    string $html,     string $text
): bool {
    $host = $cfg['smtp_host'];
    $port = (int)($cfg['smtp_port'] ?? 587);
    $enc  = strtolower($cfg['smtp_encryption'] ?? 'tls');
    $user = $cfg['smtp_user'] ?? '';
    $pass = $cfg['smtp_pass'] ?? '';

    $scheme = ($enc === 'ssl') ? 'ssl' : 'tcp';

    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]
    ]);

    $conn = @stream_socket_client(
        "{$scheme}://{$host}:{$port}", $errno, $errstr, 20,
        STREAM_CLIENT_CONNECT, $ctx
    );
    if (!$conn) return false;

    stream_set_timeout($conn, 15);

    $read = function () use ($conn): string {
        $out = '';
        while ($line = fgets($conn, 1024)) {
            $out .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $out;
    };
    $cmd = function (string $c) use ($conn, $read): string {
        fwrite($conn, $c . "\r\n");
        return $read();
    };

    $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $read();                        // server greeting
    $cmd("EHLO {$domain}");

    // STARTTLS upgrade
    if ($enc === 'tls') {
        $r = $cmd("STARTTLS");
        if (strpos($r, '220') === false) { fclose($conn); return false; }
        stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
        $cmd("EHLO {$domain}");
    }

    // Auth
    if ($user !== '') {
        $cmd("AUTH LOGIN");
        $cmd(base64_encode($user));
        $r = $cmd(base64_encode($pass));
        if (strpos($r, '235') === false) { fclose($conn); return false; }
    }

    // Envelope
    $r = $cmd("MAIL FROM:<{$from_email}>");
    if (strpos($r, '250') === false) { fclose($conn); return false; }
    $r = $cmd("RCPT TO:<{$to_email}>");
    if (strpos($r, '250') === false) { fclose($conn); return false; }
    $cmd("DATA");

    // Build MIME message
    $boundary = md5(uniqid('b', true));
    $enc_from = '=?UTF-8?B?' . base64_encode($from_name) . '?=';
    $enc_to   = empty($to_name) ? $to_email : "=?UTF-8?B?" . base64_encode($to_name) . "?= <{$to_email}>";
    $enc_subj = '=?UTF-8?B?' . base64_encode($subject)   . '?=';

    $msg  = "From: {$enc_from} <{$from_email}>\r\n";
    $msg .= "To: {$enc_to}\r\n";
    $msg .= "Subject: {$enc_subj}\r\n";
    $msg .= "Date: " . date('r') . "\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $msg .= "X-Mailer: 7ArtVilla/1.0\r\n\r\n";
    $msg .= "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$text}\r\n";
    $msg .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n";
    $msg .= "--{$boundary}--\r\n";

    // Dot-stuff lines starting with '.'
    $msg = preg_replace('/^\.$/m', '..', $msg);

    fwrite($conn, $msg . "\r\n.\r\n");
    $r = $read();
    $cmd("QUIT");
    fclose($conn);

    return strpos($r, '250') !== false;
}
