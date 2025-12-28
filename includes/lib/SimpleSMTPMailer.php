<?php

declare(strict_types=1);

namespace Earnest\Mail;

/**
 * Lightweight SMTP mailer with optional STARTTLS/SSL support and attachment handling.
 * No external dependencies are required.
 */
class SimpleSMTPMailer
{
    /** @var string */
    private $host;
    /** @var int */
    private $port;
    /** @var string|null */
    private $username;
    /** @var string|null */
    private $password;
    /** @var string */
    private $encryption; // '', 'ssl', or 'tls'
    /** @var int */
    private $timeout;

    public function __construct(
        string $host,
        int $port = 587,
        ?string $username = null,
        ?string $password = null,
        string $encryption = '',
        int $timeout = 10
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
     * @param string|array<int,string> $toEmail One or more recipient addresses (comma-separated string or array).
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
        $toEmail,
        string $subject,
        string $htmlBody,
        string $textBody,
        array $attachments = []
    ): void {
        $recipients = $this->normalizeRecipients($toEmail);
        if (empty($recipients)) {
            throw new \InvalidArgumentException('At least one recipient email address is required.');
        }

        $boundaryMixed = 'b1_' . bin2hex(random_bytes(6));
        $boundaryAlt = 'b2_' . bin2hex(random_bytes(6));

        $safeFromName = $this->sanitizeHeader($fromName);
        $safeSubject = $this->sanitizeHeader($subject);

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . ($safeFromName !== '' ? $safeFromName : $fromEmail) . " <{$fromEmail}>",
            'To: ' . implode(', ', array_map(static function ($email) {
                return '<' . $email . '>';
            }, $recipients)),
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

        // Avoid long hangs on hosts that throttle outbound SMTP
        stream_set_timeout($fp, $this->timeout);

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
            foreach ($recipients as $email) {
                $this->command($fp, 'RCPT TO: <' . $email . '>', 250);
            }

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

    /**
     * Send a command and drain multi-line responses until the final line.
     */
    private function command($fp, string $cmd, int $expect): void
    {
        fwrite($fp, $cmd . "\r\n");
        $resp = $this->readResponse($fp, $expect);
        if ($resp === null) {
            throw new \RuntimeException('SMTP error: empty response');
        }
    }

    /**
     * Expect an initial server banner or response code (handles multi-line).
     */
    private function expect($fp, int $code): void
    {
        $resp = $this->readResponse($fp, $code);
        if ($resp === null) {
            throw new \RuntimeException('SMTP error: empty response');
        }
    }

    /**
     * Read an SMTP response, consuming all continuation lines.
     *
     * @return string|null Final response line or null on failure.
     */
    private function readResponse($fp, int $expect): ?string
    {
        $lastLine = null;
        $expectStr = (string) $expect;

        while (($line = fgets($fp, 512)) !== false) {
            $lastLine = $line;
            $code = substr($line, 0, 3);
            $isError = isset($code[0]) && ($code[0] === '4' || $code[0] === '5');
            if ($isError) {
                throw new \RuntimeException('SMTP error: ' . trim($line));
            }
            // Continuation lines use a hyphen after the code (e.g., 250-).
            if (substr($line, 3, 1) !== '-') {
                // If the final line code differs from what we expected but is still 2xx/3xx, accept it.
                if ($code !== $expectStr && isset($code[0]) && ($code[0] === '2' || $code[0] === '3')) {
                    return $line;
                }
                break;
            }
        }

        return $lastLine;
    }

    private function sanitizeHeader(string $value): string
    {
        return trim(preg_replace('/[\r\n]+/', ' ', $value));
    }

    /**
     * Normalize recipient input into a de-duplicated list of sanitized addresses.
     *
     * @param string|array<int, string> $toEmail
     * @return array<int, string>
     */
    private function normalizeRecipients($toEmail): array
    {
        $recipients = is_array($toEmail)
            ? $toEmail
            : preg_split('/\s*,\s*/', (string) $toEmail, -1, PREG_SPLIT_NO_EMPTY);

        $recipients = array_filter(array_map(static function ($email) {
            $cleaned = $email !== null ? trim((string) $email) : '';
            return filter_var($cleaned, FILTER_VALIDATE_EMAIL) ? $cleaned : null;
        }, $recipients));

        return array_values(array_unique($recipients));
    }
}
