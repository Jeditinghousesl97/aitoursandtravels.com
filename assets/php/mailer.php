<?php

function nt_load_settings(PDO $pdo): array
{
    $rows = $pdo->query('SELECT `key`, `value` FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    return is_array($rows) ? $rows : [];
}

function nt_mail_config_from_settings(array $settings): array
{
    return [
        'host' => trim((string)($settings['smtp_host'] ?? '')),
        'port' => (int)($settings['smtp_port'] ?? 587),
        'username' => trim((string)($settings['smtp_username'] ?? '')),
        'password' => (string)($settings['smtp_password'] ?? ''),
        'encryption' => strtolower(trim((string)($settings['smtp_encryption'] ?? 'tls'))),
        'from_name' => trim((string)($settings['smtp_from_name'] ?? 'ASB Tours')),
        'from_email' => trim((string)($settings['smtp_from_email'] ?? '')),
        'notify_email' => trim((string)($settings['smtp_notify_email'] ?? '')),
    ];
}

function nt_send_mail(PDO $pdo, array $message): array
{
    $settings = nt_load_settings($pdo);
    return nt_send_mail_with_settings($settings, $message);
}

function nt_send_mail_with_settings(array $settings, array $message): array
{
    $config = nt_mail_config_from_settings($settings);

    if ($config['host'] === '' || $config['from_email'] === '') {
        return ['success' => false, 'error' => 'SMTP settings are incomplete.'];
    }

    $to = $message['to'] ?? [];
    if (!is_array($to) || $to === []) {
        return ['success' => false, 'error' => 'No recipient email address provided.'];
    }

    $replyTo = $message['reply_to'] ?? null;
    $subject = trim((string)($message['subject'] ?? ''));
    $htmlBody = (string)($message['html'] ?? '');
    $textBody = (string)($message['text'] ?? strip_tags($htmlBody));

    if ($subject === '' || $htmlBody === '') {
        return ['success' => false, 'error' => 'Email subject or body is empty.'];
    }

    return nt_smtp_send($config, $to, $subject, $htmlBody, $textBody, $replyTo);
}

function nt_send_notification_email(PDO $pdo, array $message): array
{
    $settings = nt_load_settings($pdo);
    $config = nt_mail_config_from_settings($settings);
    $notifyEmail = $config['notify_email'] !== '' ? $config['notify_email'] : $config['from_email'];

    if ($notifyEmail === '') {
        return ['success' => false, 'error' => 'Notification email is not configured.'];
    }

    $message['to'] = [
        [
            'email' => $notifyEmail,
            'name' => $config['from_name'] !== '' ? $config['from_name'] : 'Site Admin',
        ],
    ];

    return nt_send_mail_with_settings($settings, $message);
}

function nt_smtp_send(array $config, array $to, string $subject, string $htmlBody, string $textBody, ?array $replyTo = null): array
{
    $host = $config['host'];
    $port = $config['port'] > 0 ? $config['port'] : 587;
    $encryption = $config['encryption'];
    $transportHost = $encryption === 'ssl' ? 'ssl://' . $host : $host;
    $timeout = 20;
    $ehloHost = nt_smtp_ehlo_host($config);

    $socket = @stream_socket_client($transportHost . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!is_resource($socket)) {
        return ['success' => false, 'error' => 'SMTP connection failed: ' . $errstr . ' (' . $errno . ')'];
    }

    stream_set_timeout($socket, $timeout);

    try {
        nt_smtp_expect($socket, [220]);
        nt_smtp_command($socket, 'EHLO ' . $ehloHost, [250]);

        if ($encryption === 'tls') {
            nt_smtp_command($socket, 'STARTTLS', [220]);
            $cryptoEnabled = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cryptoEnabled !== true) {
                throw new RuntimeException('Unable to start TLS encryption.');
            }
            nt_smtp_command($socket, 'EHLO ' . $ehloHost, [250]);
        }

        if ($config['username'] !== '' || $config['password'] !== '') {
            nt_smtp_command($socket, 'AUTH LOGIN', [334]);
            nt_smtp_command($socket, base64_encode($config['username']), [334]);
            nt_smtp_command($socket, base64_encode($config['password']), [235]);
        }

        nt_smtp_command($socket, 'MAIL FROM:<' . $config['from_email'] . '>', [250]);

        foreach ($to as $recipient) {
            $recipientEmail = trim((string)($recipient['email'] ?? ''));
            if ($recipientEmail === '') {
                continue;
            }
            nt_smtp_command($socket, 'RCPT TO:<' . $recipientEmail . '>', [250, 251]);
        }

        nt_smtp_command($socket, 'DATA', [354]);
        fwrite($socket, nt_build_mime_message($config, $to, $replyTo, $subject, $htmlBody, $textBody) . "\r\n.\r\n");
        nt_smtp_expect($socket, [250]);
        nt_smtp_command($socket, 'QUIT', [221]);
    } catch (Throwable $e) {
        fclose($socket);
        return ['success' => false, 'error' => $e->getMessage()];
    }

    fclose($socket);
    return ['success' => true];
}

function nt_smtp_command($socket, string $command, array $expectedCodes): string
{
    fwrite($socket, $command . "\r\n");
    return nt_smtp_expect($socket, $expectedCodes);
}

function nt_smtp_expect($socket, array $expectedCodes): string
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;

        if (strlen($line) < 4) {
            continue;
        }

        if ($line[3] === ' ') {
            break;
        }
    }

    if ($response === '') {
        throw new RuntimeException('Empty response from SMTP server.');
    }

    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('SMTP error: ' . trim($response));
    }

    return $response;
}

function nt_build_mime_message(array $config, array $to, ?array $replyTo, string $subject, string $htmlBody, string $textBody): string
{
    $boundary = 'b1_' . bin2hex(random_bytes(12));
    $messageId = sprintf(
        '<%s@%s>',
        bin2hex(random_bytes(12)),
        nt_header_domain($config)
    );
    $headers = [];
    $headers[] = 'Date: ' . date(DATE_RFC2822);
    $headers[] = 'From: ' . nt_format_address($config['from_email'], $config['from_name']);
    $headers[] = 'To: ' . implode(', ', array_map(
        static fn(array $recipient): string => nt_format_address((string)($recipient['email'] ?? ''), (string)($recipient['name'] ?? '')),
        $to
    ));

    if (is_array($replyTo) && !empty($replyTo['email'])) {
        $headers[] = 'Reply-To: ' . nt_format_address((string)$replyTo['email'], (string)($replyTo['name'] ?? ''));
    }

    $headers[] = 'Subject: ' . nt_encode_header($subject);
    $headers[] = 'Message-ID: ' . $messageId;
    $headers[] = 'X-Mailer: ASB Tours Website Mailer';
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

    $body = [];
    $body[] = '--' . $boundary;
    $body[] = 'Content-Type: text/plain; charset=UTF-8';
    $body[] = 'Content-Transfer-Encoding: base64';
    $body[] = '';
    $body[] = chunk_split(base64_encode($textBody));
    $body[] = '--' . $boundary;
    $body[] = 'Content-Type: text/html; charset=UTF-8';
    $body[] = 'Content-Transfer-Encoding: base64';
    $body[] = '';
    $body[] = chunk_split(base64_encode($htmlBody));
    $body[] = '--' . $boundary . '--';

    return implode("\r\n", array_merge($headers, [''], $body));
}

function nt_format_address(string $email, string $name = ''): string
{
    $email = trim($email);
    $name = trim($name);

    if ($name === '') {
        return '<' . $email . '>';
    }

    return nt_encode_header($name) . ' <' . $email . '>';
}

function nt_encode_header(string $value): string
{
    if ($value === '') {
        return '';
    }

    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function nt_smtp_ehlo_host(array $config): string
{
    $domain = nt_header_domain($config);
    return $domain !== '' ? $domain : 'localhost';
}

function nt_header_domain(array $config): string
{
    $fromEmail = trim((string)($config['from_email'] ?? ''));
    if (str_contains($fromEmail, '@')) {
        return substr(strrchr($fromEmail, '@'), 1);
    }

    return trim((string)($config['host'] ?? ''));
}
