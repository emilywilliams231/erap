# GOV-ASSIST Housing & Financial Portal

A professional-grade, government-inspired web platform for managing Emergency Rental Assistance Program (ERAP) and Home Equity Line of Credit (HELOC) applications.

## Features
- **High-Credibility UI**: Clean "Federal Blue" design palette using modern CSS Grid and Flexbox.
- **Responsive Forms**: Mobile-first application forms with multi-column layouts on desktop.
- **Client-Side Validation**: Robust JavaScript validation for emails, phone numbers, and SSN formats.
- **Secure Processing**: PHP backend scripts with input sanitization and secure data handling (SSN masking).
- **Interactive UX**: Smooth scroll animations using Intersection Observer API.

## Prerequisites
- **Web Server**: Apache or Nginx (Local options: XAMPP, WAMP, or MAMP).
- **PHP Version**: 7.4 or higher.
- **SSL Certificate**: Strongly recommended (HTTPS) before deploying for actual data collection due to sensitive PII (Social Security Numbers).

## Installation

1.  **Clone or Download**: Copy the project files to your web server's root directory (e.g., `htdocs` or `/var/www/html`).
2.  **Directory Structure**:
    - `/css` - Contains style.css
    - `/js` - Contains main.js
    - `/includes` - Contains PHP processing logic
    - `/` - Root contains HTML pages
3.  **Configure Email**:
    - Open `includes/process-erap.php` and `includes/process-heloc.php`.
    - Change the `$to` variable to your administrative email address.

## How to Run
1.  Start your local server (e.g., open XAMPP and start Apache).
2.  Navigate to `http://localhost/project-folder/index.html` in your browser.
3.  Test the forms by filling out the ERAP or HELOC applications.
4.  Submissions will attempt to send an email via the PHP `mail()` function and redirect to `success.html`.

## Security Notes
- **Data Encryption**: While this app masks the SSN in email notifications, for production use, data should be stored in an encrypted database (AES-256) rather than sent via standard email.
- **Form Protection**: Consider adding a CAPTCHA (like Google reCAPTCHA) to the forms to prevent bot spam.
- **Input Sanitization**: The PHP scripts use `filter_var` to sanitize inputs, which helps protect against XSS and basic injection.

## Troubleshooting
- **Emails Not Sending**: If you are running on localhost, the PHP `mail()` function requires a configured SMTP server. Most local environments (XAMPP) require setting up `sendmail` or using a library like PHPMailer with an external SMTP service (Gmail/Outlook).
- **Styles Not Loading**: Ensure the path to `css/style.css` is correct and your browser is not caching an old version.
