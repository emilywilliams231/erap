<?php
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Send a simple SMTP email using PHPMailer (full library recommended when available).
 * Falls back to the built-in mail() transport if SMTP is blocked or unreachable.
 *
 * @param array       $config      SMTP + addressing config (host, port, username, password, from_email, from_name, to_email).
 * @param string      $subject     Email subject line.
 * @param array       $fields      Key/value pairs to include in the message body.
 * @param array       $fileLabels  Map of $_FILES keys to human-friendly labels for attachments.
 * @param string|null $replyTo     Optional reply-to address.
 * @param string|null $errorOutput Optional reference to capture an error message.
 *
 * @return bool Whether the message was sent successfully.
 */
function send_application_email(
    array $config,
    string $subject,
    array $fields,
    array $fileLabels = [],
    ?string $replyTo = null,
    ?string &$errorOutput = null
): bool
{
    // Prefer full PHPMailer if installed via Composer; otherwise use the included shim.
    if (!class_exists(PHPMailer::class)) {
        require_once __DIR__ . '/simple-phpmailer-shim.php';
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $config['host'] ?? '';
    $mail->Port = $config['port'] ?? 587;
    $mail->Username = $config['username'] ?? '';
    $mail->Password = $config['password'] ?? '';
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = $config['secure'] ?? 'tls';
    $mail->Timeout = $config['timeout'] ?? 15;

    $mail->setFrom($config['from_email'] ?? '', $config['from_name'] ?? ($config['from_email'] ?? ''));
    $mail->addAddress($config['to_email'] ?? '');
    if (!empty($replyTo)) {
        $mail->addReplyTo($replyTo);
    }

    $body  = "Submitted Application Details\n";
    $body .= "============================\n\n";
    foreach ($fields as $label => $value) {
        $body .= "{$label}: {$value}\n";
    }
    $body .= "\nSubmission Timestamp: " . date("Y-m-d H:i:s") . "\n";

    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->AltBody = strip_tags($body);
    $mail->isHTML(false);

    $success = false;
    foreach ($fileLabels as $field => $label) {
        if (!isset($_FILES[$field])) {
            continue;
        }

        $fileData = $_FILES[$field];
        if (is_array($fileData['tmp_name'])) {
            foreach ($fileData['tmp_name'] as $idx => $tmpName) {
                if ($tmpName && ($fileData['error'][$idx] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
                    $originalName = $fileData['name'][$idx] ?? "{$label}-{$idx}";
                    $mail->addAttachment($tmpName, $originalName);
                }
            }
        } elseif (!empty($fileData['tmp_name']) && ($fileData['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
            $originalName = $fileData['name'] ?? $label;
            $mail->addAttachment($fileData['tmp_name'], $originalName);
        }
    }

    $success = $mail->send();

    if (!$success) {
        $errorOutput = $mail->ErrorInfo ?: 'SMTP send failed without a specific error message.';
        // Fallback: attempt PHP's mail() to avoid total failure on hosts that block SMTP
        $headers = [
            'From: ' . ($config['from_name'] ?? $config['from_email'] ?? 'Mailer') . ' <' . ($config['from_email'] ?? '') . '>',
            'Reply-To: ' . ($replyTo ?: ($config['from_email'] ?? '')),
        ];

        $success = @mail(
            $config['to_email'] ?? '',
            $subject,
            $body,
            implode("\r\n", $headers)
        );

        if (!$success) {
            $errorOutput .= ' | Fallback mail() transport also failed.';
        }
    }

    if (!$success && !empty($errorOutput)) {
        error_log('[GOV-ASSIST MAILER] ' . $errorOutput);
    }

    return $success;
}
