<?php
/**
 * ERAP Application Processor
 * Handles sanitization and notification
 */

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Sanitize Inputs
    $name     = filter_var($_POST['full_name'], FILTER_SANITIZE_STRING);
    $email    = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $phone    = filter_var($_POST['phone'], FILTER_SANITIZE_STRING);
    $ssn      = filter_var($_POST['ssn'], FILTER_SANITIZE_STRING);
    $address  = filter_var($_POST['address'], FILTER_SANITIZE_STRING);
    $city     = filter_var($_POST['city'], FILTER_SANITIZE_STRING);
    $zip      = filter_var($_POST['zip'], FILTER_SANITIZE_STRING);
    $income   = filter_var($_POST['income'], FILTER_SANITIZE_NUMBER_INT);
    $amount   = filter_var($_POST['total_assistance'], FILTER_SANITIZE_NUMBER_INT);
    $hardship = filter_var($_POST['hardship'], FILTER_SANITIZE_STRING);

    // 2. Validate essential fields again on server side
    if (empty($name) || empty($email) || empty($ssn)) {
        die("Security error: Missing required fields.");
    }

    // 3. Setup Email Notification
    // NOTE: Replace with your actual administrative email
    $to = "applications@your-agency-portal.com"; 
    $subject = "NEW ERAP APPLICATION: $name";
    
    // Mask SSN for transmission security
    $masked_ssn = "XXX-XX-" . substr(str_replace('-', '', $ssn), -4);

    $message = "Official ERAP Application Received\n";
    $message .= "====================================\n\n";
    $message .= "APPLICANT DETAILS:\n";
    $message .= "Full Name: $name\n";
    $message .= "Email: $email\n";
    $message .= "Phone: $phone\n";
    $message .= "SSN (Masked): $masked_ssn\n\n";
    
    $message .= "PROPERTY DETAILS:\n";
    $message .= "Address: $address\n";
    $message .= "City: $city, Zip: $zip\n\n";
    
    $message .= "FINANCIAL DETAILS:\n";
    $message .= "Annual Income: $" . number_format($income) . "\n";
    $message .= "Assistance Requested: $" . number_format($amount) . "\n\n";
    
    $message .= "HARDSHIP STATEMENT:\n";
    $message .= $hardship . "\n\n";
    
    $message .= "====================================\n";
    $message .= "Submission Timestamp: " . date("Y-m-d H:i:s") . "\n";

    $headers = "From: webmaster@gov-assist-portal.com\r\n";
    $headers .= "Reply-To: $email\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // 4. Send & Redirect
    // In a real production environment, you would use a library like PHPMailer 
    // or an API (SendGrid/Mailgun) for better deliverability.
    if (mail($to, $subject, $message, $headers)) {
        // Log submission success locally (optional)
        header("Location: ../success.html");
        exit();
    } else {
        // If mail fails, usually due to server configuration
        echo "<h1>Application Error</h1>";
        echo "<p>We were unable to process your application at this time. Please contact support.</p>";
        echo "<a href='../erap-apply.html'>Go Back</a>";
    }
} else {
    // Direct access not allowed
    header("Location: ../index.html");
    exit();
}
?>
