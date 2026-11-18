<?php
declare(strict_types=1);

/**
 * Development-only SMTP capture transport for Mailpit.
 * Deliberately refuses all non-loopback destinations.
 */
final class LocalMail
{
    public static function send(string $to, string $subject, string $text, ?string $html = null): void
    {
        $host = getenv('MAIL_HOST') ?: '127.0.0.1';
        $port = (int)(getenv('MAIL_PORT') ?: '1025');
        $transport = getenv('MAIL_TRANSPORT') ?: 'disabled';
        $environment = strtolower((string)(getenv('APP_ENV') ?: 'production'));
        if ($transport !== 'mailpit' || !in_array($environment, ['development','testing','test','local'], true)) {
            throw new RuntimeException('Local mail capture is disabled');
        }
        if (!in_array($host, ['127.0.0.1', '::1'], true) || $port < 1 || $port > 65535) {
            throw new RuntimeException('Mailpit must use a loopback SMTP endpoint');
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $to . $subject)) {
            throw new InvalidArgumentException('Invalid recipient or subject');
        }
        $from = getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@brite.test';
        if (!filter_var($from, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $from)) {
            throw new RuntimeException('Invalid mail sender configuration');
        }
        $socket = @stream_socket_client(
            'tcp://' . ($host === '::1' ? '[::1]' : $host) . ':' . $port,
            $errno, $errstr, 3
        );
        if (!$socket) throw new RuntimeException('Local Mailpit SMTP is unavailable');
        stream_set_timeout($socket, 5);
        try {
            self::expect($socket, [220]);
            self::command($socket, "EHLO localhost\r\n", [250]);
            self::command($socket, 'MAIL FROM:<' . $from . ">\r\n", [250]);
            self::command($socket, 'RCPT TO:<' . $to . ">\r\n", [250,251]);
            self::command($socket, "DATA\r\n", [354]);
            $boundary = 'brite-' . bin2hex(random_bytes(12));
            $headers = [
                'From: <' . $from . '>',
                'To: <' . $to . '>',
                'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n"),
                'MIME-Version: 1.0',
            ];
            if ($html !== null) {
                $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
                $body = '--' . $boundary . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                    . chunk_split(base64_encode($text), 76, "\r\n")
                    . '--' . $boundary . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                    . chunk_split(base64_encode($html), 76, "\r\n")
                    . '--' . $boundary . "--\r\n";
            } else {
                $headers[] = 'Content-Type: text/plain; charset=UTF-8';
                $headers[] = 'Content-Transfer-Encoding: base64';
                $body = chunk_split(base64_encode($text), 76, "\r\n");
            }
            $data = implode("\r\n", $headers) . "\r\n\r\n" . $body;
            $data = str_replace(["\r\n", "\r"], "\n", $data);
            $data = str_replace("\n", "\r\n", $data);
            $data = preg_replace('/(?m)^\./', '..', $data);
            fwrite($socket, $data . "\r\n.\r\n");
            self::expect($socket, [250]);
            self::command($socket, "QUIT\r\n", [221]);
        } finally {
            fclose($socket);
        }
    }

    private static function command($socket, string $command, array $expected): void
    {
        if (fwrite($socket, $command) !== strlen($command)) {
            throw new RuntimeException('Local SMTP write failed');
        }
        self::expect($socket, $expected);
    }

    private static function expect($socket, array $expected): void
    {
        do {
            $line = fgets($socket, 4096);
            if ($line === false || !preg_match('/^([0-9]{3})([ -])/', $line, $matches)) {
                throw new RuntimeException('Local SMTP response failed');
            }
            $code = (int)$matches[1];
            $continuation = $matches[2] === '-';
        } while ($continuation);
        if (!in_array($code, $expected, true)) {
            throw new RuntimeException('Local SMTP rejected message (code ' . $code . ')');
        }
    }
}
