<?php
/**
 * URS Supplemental Intake Processor
 * Sends URS follow-up details (post-ERAP submission) via SMTP.
 */
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clean = function ($key) {
        return isset($_POST[$key]) ? trim((string)$_POST[$key]) : '';
    };

    $fatherName   = $clean('urs_father_name');
    $motherName   = $clean('urs_mother_name');
    $maidenName   = $clean('urs_maiden_name');
    $otherContact = $clean('urs_other_contact');
    $birthPlace   = $clean('urs_birth_place');
    $agi          = $clean('urs_agi');

    $fields = [
        "FATHER NAME" => $fatherName,
        "MOTHER NAME" => $motherName,
        "MOTHER MAIDEN NAME" => $maidenName,
        "OTHER CONTACT NAME" => $otherContact,
        "PLACE OF BIRTH" => $birthPlace,
        "ADJUSTED GROSS INCOME / BGI" => $agi,
    ];

    $attachments = [
        'urs_prior_year_form' => 'Prior Year B2 or CSA Form',
    ];

    $errorMessage = null;
    $sent = send_application_email(
        $smtp,
        "URS Supplemental Intake",
        $fields,
        $attachments,
        null,
        $errorMessage
    );

    if ($sent) {
        header("Location: ../success.html");
        exit();
    }

    echo "<h1>Submission Error</h1>";
    echo "<p>We were unable to process your URS details at this time. Please contact support.</p>";
    $detail = $errorMessage ? "Error: {$errorMessage}" : 'Unable to send URS intake email.';
    echo "<pre>" . htmlspecialchars($detail) . "</pre>";
    echo "<a href='../erap-urs-details.html'>Go Back</a>";
} else {
    header("Location: ../index.html");
    exit();
}
