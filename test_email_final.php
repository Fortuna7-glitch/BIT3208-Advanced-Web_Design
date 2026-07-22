<?php
require_once 'config/database.php';

// Replace with your email address
$test_email = 'raijoseph9505@gmail.com';

echo "📧 Sending test email to: $test_email<br><br>";

$subject = '✅ Salon Pro - SMTP Test Successful!';
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
        <p style="color: #b8b2a0;">Your email configuration is working!</p>
        <p style="color: #7a7568; font-size: 14px;">This is a test email from your Salon Pro system.</p>
        <div class="footer">© 2026 Salon Pro. All rights reserved.</div>
    </div>
</body>
</html>
';

if (sendEmail($test_email, $subject, $body)) {
    echo "✅ Email sent successfully!<br>";
    echo "📬 Check your inbox (and spam folder).";
} else {
    echo "❌ Failed to send email.<br>";
    echo "Check error logs for details.";
}
?>