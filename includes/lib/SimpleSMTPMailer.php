<?php

declare(strict_types=1);

namespace Earnest\Mail;

/**
 * Lightweight SMTP mailer with optional STARTTLS/SSL support and attachment handling.
 * No external dependencies are required.
 */
class SimpleSMTPMailer
{
    private string $host;
    private int $port;
    private ?string $username;
    private ?string $password;
    private string $encryption; // '', 'ssl', or 'tls'
    private int $timeout;

    public function __construct(
        string $host,
        int $port = 587,
        ?string $username = null,
        ?string $password = null,
        string $encryption = '',
        int $timeout = 15
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username ?: null;
        $this->password = $password ?: null;
        $this->encryption = strtolower($encryption);
        $this->timeout = $timeout;
    }

    /**
     * Send an email with both HTML and plain-text bodies and optional attachments.
     *
     * @param string $fromEmail
     * @param string $fromName
     * @param string $toEmail
     * @param string $subject
     * @param string $htmlBody
     * @param string $textBody
     * @param array<int, array{path:string, name?:string}> $attachments
     *
     * @throws \Throwable on failure
     */
    public function send(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $subject,
        string $htmlBody,
        string $textBody,
        array $attachments = []
    ): void {
        $boundaryMixed = 'b1_' . bin2hex(random_bytes(6));
        $boundaryAlt = 'b2_' . bin2hex(random_bytes(6));

        $safeFromName = $this->sanitizeHeader($fromName);
        $safeSubject = $this->sanitizeHeader($subject);

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . ($safeFromName !== '' ? $safeFromName : $fromEmail) . " <{$fromEmail}>",
            "To: <{$toEmail}>",
            "Subject: {$safeSubject}",
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $boundaryMixed . '"',
        ];

        $body = "--{$boundaryMixed}\r\n";
        $body .= 'Content-Type: multipart/alternative; boundary="' . $boundaryAlt . '"' . "\r\n\r\n";
        $body .= "--{$boundaryAlt}\r\n";
        $body .= "Content-Type: text/plain; charset=\"UTF-8\"\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $textBody . "\r\n\r\n";
        $body .= "--{$boundaryAlt}\r\n";
        $body .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $htmlBody . "\r\n\r\n";
        $body .= "--{$boundaryAlt}--\r\n";

        foreach ($attachments as $attachment) {
            if (empty($attachment['path']) || !is_readable($attachment['path'])) {
                continue;
            }
            $path = $attachment['path'];
            $name = $attachment['name'] ?? basename($path);
            $fileData = file_get_contents($path);
            if ($fileData === false) {
                continue;
            }
            $body .= "--{$boundaryMixed}\r\n";
            $body .= 'Content-Type: application/octet-stream; name="' . $this->sanitizeHeader($name) . '"' . "\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= 'Content-Disposition: attachment; filename="' . $this->sanitizeHeader($name) . '"' . "\r\n\r\n";
            $body .= chunk_split(base64_encode($fileData)) . "\r\n";
        }
        $body .= "--{$boundaryMixed}--";

        $socketHost = $this->encryption === 'ssl' ? 'ssl://' . $this->host : $this->host;
        $fp = @stream_socket_client(
            $socketHost . ':' . $this->port,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT
        );

        if (!$fp) {
            throw new \RuntimeException("SMTP connection failed: {$errstr} ({$errno})");
        }

        try {
            $this->expect($fp, 220);
            $this->command($fp, 'EHLO localhost', 250);

            if ($this->encryption === 'tls') {
                $this->command($fp, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('Unable to start TLS encryption.');
                }
                $this->command($fp, 'EHLO localhost', 250);
            }

            if ($this->username) {
                $this->command($fp, 'AUTH LOGIN', 334);
                $this->command($fp, base64_encode($this->username), 334);
                $this->command($fp, base64_encode($this->password ?? ''), 235);
            }

            $this->command($fp, 'MAIL FROM: <' . $fromEmail . '>', 250);
            $this->command($fp, 'RCPT TO: <' . $toEmail . '>', 250);

            $this->command($fp, 'DATA', 354);
            $data = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
            $this->command($fp, $data, 250);
            $this->command($fp, 'QUIT', 221);
        } finally {
            if (is_resource($fp)) {
                fclose($fp);
            }
        }
    }

    private function command($fp, string $cmd, int $expect): void
    {
        fwrite($fp, $cmd . "\r\n");
        $resp = fgets($fp, 512);
        if ($resp === false || strpos($resp, (string) $expect) !== 0) {
            throw new \RuntimeException("SMTP error: " . trim((string) $resp));
        }
    }

    private function expect($fp, int $code): void
    {
        $resp = fgets($fp, 512);
        if ($resp === false || strpos($resp, (string) $code) !== 0) {
            throw new \RuntimeException("SMTP error: " . trim((string) $resp));
        }
    }

    private function sanitizeHeader(string $value): string
    {
        return trim(preg_replace('/[\r\n]+/', ' ', $value));
    }
}
