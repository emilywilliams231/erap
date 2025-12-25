<?php
/**
 * HELOC Application Processor
 * Handles sanitization and notification for home equity requests
 */

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Sanitize Inputs
    $name             = filter_var($_POST['full_name'], FILTER_SANITIZE_STRING);
    $email            = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $prop_value       = filter_var($_POST['property_value'], FILTER_SANITIZE_NUMBER_INT);
    $mortgage_bal     = filter_var($_POST['mortgage_balance'], FILTER_SANITIZE_NUMBER_INT);
    $credit_score     = filter_var($_POST['credit_score'], FILTER_SANITIZE_STRING);
    $requested_amount = filter_var($_POST['loan_amount'], FILTER_SANITIZE_NUMBER_INT);
    $purpose          = filter_var($_POST['purpose'], FILTER_SANITIZE_STRING);

    // 2. Setup Email
    $to = "lending@your-agency-portal.com"; 
    $subject = "NEW HELOC PRE-QUALIFICATION: $name";
    
    // Calculate LTV (Loan to Value) for internal review
    $total_debt = $mortgage_bal + $requested_amount;
    $ltv = ($prop_value > 0) ? ($total_debt / $prop_value) * 100 : 0;

    $message = "Official HELOC Pre-Qualification Request\n";
    $message .= "====================================\n\n";
    $message .= "APPLICANT DETAILS:\n";
    $message .= "Full Name: $name\n";
    $message .= "Email: $email\n\n";
    
    $message .= "FINANCIAL PROFILE:\n";
    $message .= "Est. Home Value: $" . number_format($prop_value) . "\n";
    $message .= "Current Mortgage: $" . number_format($mortgage_bal) . "\n";
    $message .= "Credit Range: $credit_score\n";
    $message .= "Calculated LTV: " . round($ltv, 2) . "%\n\n";
    
    $message .= "LOAN REQUEST:\n";
    $message .= "Requested Line: $" . number_format($requested_amount) . "\n";
    $message .= "Intended Use: $purpose\n\n";
    
    $message .= "====================================\n";
    $message .= "Submission Timestamp: " . date("Y-m-d H:i:s") . "\n";

    $headers = "From: underwriting@gov-assist-portal.com\r\n";
    $headers .= "Reply-To: $email\r\n";

    // 3. Send & Redirect
    if (mail($to, $subject, $message, $headers)) {
        header("Location: ../success.html");
        exit();
    } else {
        echo "An error occurred. Please try again later.";
    }
} else {
    header("Location: ../index.html");
    exit();
}
?>
