<?php
namespace PHPMailer\PHPMailer;

class Exception extends \Exception {}

class PHPMailer {
    public $Host = 'smtp.example.com';
    public $Port = 587;
    public $Username = '';
    public $Password = '';
    public $SMTPAuth = true;
    public $SMTPSecure = 'tls';
    public $From = '';
    public $FromName = '';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $isHTML = false;
    public $ErrorInfo = '';
    public $Timeout = 15;
    private $to = [];
    private $attachments = [];

    public function isSMTP() {
        // Placeholder for compatibility
    }

    public function setFrom($address, $name = '') {
        $this->From = $address;
        $this->FromName = $name;
    }

    public function addAddress($address, $name = '') {
        $this->to[] = [$address, $name];
    }

    public function addReplyTo($address) {
        $this->ReplyTo = $address;
    }

    public function addAttachment($path, $name = '') {
        $this->attachments[] = ['path' => $path, 'name' => $name ?: basename($path)];
    }

    public function send() {
        $boundary = 'b1_' . bin2hex(random_bytes(8));
        $altBoundary = 'b2_' . bin2hex(random_bytes(8));

        $headers = [];
        $fromName = $this->FromName ?: $this->From;
        $headers[] = 'From: ' . $fromName . ' <' . $this->From . '>';
        if (!empty($this->ReplyTo)) {
            $headers[] = 'Reply-To: ' . $this->ReplyTo;
        }
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n\r\n";
        $body .= "--{$altBoundary}\r\n";
        $body .= "Content-Type: text/plain; charset=\"UTF-8\"\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $this->AltBody . "\r\n\r\n";
        $body .= "--{$altBoundary}\r\n";
        $body .= "Content-Type: " . ($this->isHTML ? 'text/html' : 'text/plain') . "; charset=\"UTF-8\"\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $this->Body . "\r\n\r\n";
        $body .= "--{$altBoundary}--\r\n";

        foreach ($this->attachments as $attach) {
            $fileData = file_get_contents($attach['path']);
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: application/octet-stream; name=\"" . $attach['name'] . "\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"" . $attach['name'] . "\"\r\n\r\n";
            $body .= chunk_split(base64_encode($fileData)) . "\r\n";
        }
        $body .= "--{$boundary}--";

        try {
            $socketHost = ($this->SMTPSecure === 'ssl') ? 'ssl://' . $this->Host : $this->Host;
            $fp = stream_socket_client($socketHost . ':' . $this->Port, $errno, $errstr, $this->Timeout, STREAM_CLIENT_CONNECT);
            if (!$fp) {
                throw new Exception("SMTP connection failed: $errstr");
            }

            $this->expect($fp, 220);
            $this->command($fp, 'EHLO localhost', 250);

            if ($this->SMTPSecure === 'tls') {
                $this->command($fp, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception('Unable to start TLS encryption.');
                }
                $this->command($fp, 'EHLO localhost', 250);
            }

            if ($this->SMTPAuth) {
                $this->command($fp, 'AUTH LOGIN', 334);
                $this->command($fp, base64_encode($this->Username), 334);
                $this->command($fp, base64_encode($this->Password), 235);
            }

            $this->command($fp, 'MAIL FROM: <' . $this->From . '>', 250);
            foreach ($this->to as $recipient) {
                $this->command($fp, 'RCPT TO: <' . $recipient[0] . '>', 250);
            }

            $this->command($fp, 'DATA', 354);
            $toHeader = implode(',', array_map(fn($r) => '<' . $r[0] . '>', $this->to));
            $data  = "To: {$toHeader}\r\n";
            $data .= "Subject: {$this->Subject}\r\n";
            $data .= implode("\r\n", $headers) . "\r\n\r\n";
            $data .= $body . "\r\n.";
            $this->command($fp, $data, 250);
            $this->command($fp, 'QUIT', 221);
            fclose($fp);
            return true;
        } catch (Exception $e) {
            $this->ErrorInfo = $e->getMessage();
            if (isset($fp) && is_resource($fp)) {
                fclose($fp);
            }
            return false;
        }
    }

    private function command($fp, $cmd, $expect) {
        fwrite($fp, $cmd . "\r\n");
        $resp = fgets($fp, 512);
        if (strpos($resp, (string)$expect) !== 0) {
            throw new Exception("SMTP error: $resp");
        }
    }

    private function expect($fp, $code) {
        $resp = fgets($fp, 512);
        if (strpos($resp, (string)$code) !== 0) {
            throw new Exception("SMTP error: $resp");
        }
    }
}

