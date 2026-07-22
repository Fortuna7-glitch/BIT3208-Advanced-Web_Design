<?php
require_once 'config/database.php';

// Test email
$to = 'raijoseph9505@gmail.com'; // Replace with your actual email
$subject = '✅ Salon Pro Email Test - PHPMailer Working!';
$body = '
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; background: #0a0a0a; color: #f5f0e1; padding: 20px; }
        .container { max-width: 500px; margin: 0 auto; background: #0e0e0e; border: 1px solid #d4af37; border-radius: 12px; padding: 30px; text-align: center; }
        h1 { color: #d4af37; font-family: "Playfair Display", serif; }
        .success { color: #28a745; font-size: 48px; }
        .footer { color: #7a7568; font-size: 12px; margin-top: 20px; border-top: 1px solid rgba(212,175,55,0.1); padding-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="success">✅</div>
        <h1>Salon Pro</h1>
        <p style="color: #b8b2a0;">PHPMailer is successfully configured!</p>
        <p style="color: #7a7568; font-size: 14px;">This is a test email from your Salon Pro system.</p>
        <div class="footer">© 2026 Salon Pro. All rights reserved.</div>
    </div>
</body>
</html>
';

echo "Sending test email to: $to<br>";

if (sendEmail($to, $subject, $body)) {
    echo "✅ Email sent successfully! Check your inbox (or spam folder).";
} else {
    echo "❌ Failed to send email. Check error logs.";
}
?>