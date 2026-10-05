<?php
declare(strict_types=1);

// Implicit TLS SMTP (port 465); certificates and hostnames are verified.
function sendEnquiry(array $config, string $email, string $html): bool {
    $socket = null;
    try {
        $bcc = $config['mail_bcc'] ?? '';
        if ($bcc !== '' && (!filter_var($bcc, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $bcc))) return false;
        if ($config['smtp_port'] !== 465 || $config['smtp_password'] === ''
            || !preg_match('/^[a-zA-Z0-9.-]+$/D', $config['smtp_host'])
            || !filter_var($config['smtp_username'], FILTER_VALIDATE_EMAIL)) return false;
        $context = stream_context_create(['ssl' => [
            'verify_peer' => true, 'verify_peer_name' => true,
            'peer_name' => $config['smtp_host'], 'allow_self_signed' => false,
        ]]);
        $socket = @stream_socket_client('ssl://' . $config['smtp_host'] . ':465',
            $errorCode, $errorMessage, 5, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) return false;
        stream_set_timeout($socket, 2);
        $deadline = microtime(true) + 12;
        $write = static function (string $data) use ($socket, $deadline): void {
            while ($data !== '') {
                if (microtime(true) > $deadline) throw new RuntimeException('SMTP timeout');
                $written = fwrite($socket, $data);
                if ($written === false || $written === 0) throw new RuntimeException('SMTP write failed');
                $data = substr($data, $written);
            }
        };
        $read = static function (array $expected) use ($socket, $deadline): void {
            for ($i = 0; $i < 100; $i++) {
                if (microtime(true) > $deadline) throw new RuntimeException('SMTP timeout');
                $line = fgets($socket, 4096);
                if ($line === false || !preg_match('/^(\d{3})([ -])/', $line, $match))
                    throw new RuntimeException('SMTP response failed');
                if ($match[2] === ' ') {
                    if (!in_array((int)$match[1], $expected, true)) throw new RuntimeException('SMTP command rejected');
                    return;
                }
            }
            throw new RuntimeException('SMTP response too long');
        };
        $command = static function (string $line, array $expected) use ($write, $read): void {
            $write($line . "\r\n"); $read($expected);
        };
        $read([220]);
        $command('EHLO website.local', [250]);
        $command('AUTH LOGIN', [334]);
        $command(base64_encode($config['smtp_username']), [334]);
        $command(base64_encode($config['smtp_password']), [235]);
        $command('MAIL FROM:<' . $config['mail_from'] . '>', [250]);
        $command('RCPT TO:<' . $config['contact_email'] . '>', [250, 251]);
        // Envelope recipient only: keep the BCC address out of message headers.
        if ($bcc !== '' && strcasecmp($bcc, $config['contact_email']) !== 0) {
            $command('RCPT TO:<' . $bcc . '>', [250, 251]);
        }
        $command('DATA', [354]);
        $headers = [
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . substr(strrchr($config['mail_from'], '@'), 1) . '>',
            'From: =?UTF-8?B?' . base64_encode($config['mail_name']) . '?= <' . $config['mail_from'] . '>',
            'To: <' . $config['contact_email'] . '>',
            'Reply-To: <' . $email . '>',
            'Subject: New website enquiry - Pavan Sai Engineering Services',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        $write(implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($html), 76, "\r\n") . ".\r\n");
        $read([250]);
        // Acceptance above is success even if the server closes before QUIT.
        @fwrite($socket, "QUIT\r\n");
        return true;
    } catch (Throwable $exception) {
        // Never log authentication data or the server's response.
        error_log('Contact form: SMTP delivery failed.');
        return false;
    } finally {
        if (is_resource($socket)) fclose($socket);
    }
}
