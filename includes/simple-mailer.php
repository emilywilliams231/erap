<?php
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Send a simple SMTP email using PHPMailer-lite with a dictionary-style config.
 *
 * @param array $config     SMTP + addressing config (host, port, username, password, from_email, from_name, to_email).
 * @param string $subject   Email subject line.
 * @param array $fields     Key/value pairs to include in the message body.
 * @param array $fileLabels Map of $_FILES keys to human-friendly labels for attachments.
 * @param string|null $replyTo Optional reply-to address.
 *
 * @return bool Whether the message was sent successfully.
 */
function send_application_email(array $config, string $subject, array $fields, array $fileLabels = [], ?string $replyTo = null): bool
{
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $config['host'] ?? '';
    $mail->Port = $config['port'] ?? 587;
    $mail->Username = $config['username'] ?? '';
    $mail->Password = $config['password'] ?? '';
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = $config['secure'] ?? 'tls';

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

    return $mail->send();
}
