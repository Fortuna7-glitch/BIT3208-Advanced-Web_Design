<?php
require_once 'config/database.php';

$phone = '0790209767'; // Replace with your phone number
$message = 'Test SMS from Salon Pro notification system.';

if (sendSMS($phone, $message)) {
    echo "✅ SMS sent successfully to $phone";
} else {
    echo "❌ Failed to send SMS";
}
?>