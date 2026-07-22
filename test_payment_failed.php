<?php
// test_payment_failed.php - Test Payment Failed Notification
require_once 'config/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================
// CONFIGURATION
// ============================================

$salon_id = 2; // Salon Pro - Headquarters
$amount = 5000;
$reason = 'Insufficient funds in account';

// ============================================
// GET SALON AND OWNER DETAILS
// ============================================

$salon_query = "SELECT s.salon_name, u.id as owner_id, u.full_name as owner_name, u.email, u.phone 
                FROM salons s 
                JOIN users u ON s.owner_id = u.id 
                WHERE s.id = $salon_id";

$salon_result = mysqli_query($conn, $salon_query);

if (mysqli_num_rows($salon_result) == 0) {
    die("❌ Salon not found with ID: $salon_id");
}

$salon = mysqli_fetch_assoc($salon_result);

echo "========================================\n";
echo "💰 TEST: PAYMENT FAILED NOTIFICATION\n";
echo "========================================\n\n";

echo "Salon: " . $salon['salon_name'] . "\n";
echo "Owner: " . $salon['owner_name'] . "\n";
echo "Email: " . $salon['email'] . "\n";
echo "Phone: " . $salon['phone'] . "\n";
echo "Amount: KSh " . number_format($amount, 2) . "\n";
echo "Reason: " . $reason . "\n\n";

echo "Sending payment failed notification...\n";

// ============================================
// SEND NOTIFICATION
// ============================================

$result = sendPaymentFailedAlert($salon_id, $amount, $reason);

if ($result) {
    echo "✅ Payment failed alert sent successfully!\n\n";
} else {
    echo "❌ Failed to send payment failed alert\n";
}

// ============================================
// VERIFY IN-APP NOTIFICATION
// ============================================

// Get Super Admin ID
$super_query = "SELECT id FROM users WHERE role = 'super_admin' LIMIT 1";
$super_result = mysqli_query($conn, $super_query);
$super = mysqli_fetch_assoc($super_result);

if ($super) {
    $super_id = $super['id'];
    $check = mysqli_query($conn, "SELECT * FROM notifications WHERE user_id = $super_id AND type = 'payment_failed' ORDER BY id DESC LIMIT 1");
    
    if (mysqli_num_rows($check) > 0) {
        $notif = mysqli_fetch_assoc($check);
        echo "\n✅ In-app notification created for Super Admin:\n";
        echo "   Title: " . $notif['title'] . "\n";
        echo "   Message: " . $notif['message'] . "\n";
        echo "   Link: " . $notif['link'] . "\n";
        echo "   Read: " . ($notif['is_read'] ? 'Yes' : 'No') . "\n";
    } else {
        echo "\n⚠️ No in-app notification found.\n";
    }
}

// ============================================
// VERIFY NOTIFICATION LOGS
// ============================================

$log_check = mysqli_query($conn, "SELECT * FROM notification_logs WHERE recipient_type = 'owner' ORDER BY id DESC LIMIT 3");

if (mysqli_num_rows($log_check) > 0) {
    echo "\n📋 Recent notification logs:\n";
    while ($log = mysqli_fetch_assoc($log_check)) {
        $status_color = $log['status'] == 'sent' ? '✅' : '❌';
        echo "   $status_color Channel: " . $log['channel'] . " | Status: " . $log['status'] . " | To: " . $log['recipient_email'] . "\n";
    }
} else {
    echo "\n⚠️ No notification logs found.\n";
}

echo "\n========================================\n";
echo "✅ TEST COMPLETED\n";
?>