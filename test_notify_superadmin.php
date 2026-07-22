<?php
require_once 'config/database.php';

echo "Testing Super Admin notification...\n";

$result = notifySuperAdmin(
    'system_alert',
    'Test Notification from System',
    'This is a test notification to verify the notifySuperAdmin function works correctly.',
    'dashboard.php'
);

if ($result) {
    echo "✅ Super Admin notification created!\n";
    
    // Verify
    $super_id = 7; // Your Super Admin ID
    $check = mysqli_query($conn, "SELECT * FROM notifications WHERE user_id = $super_id AND type = 'system_alert' ORDER BY id DESC LIMIT 1");
    
    if (mysqli_num_rows($check) > 0) {
        $notif = mysqli_fetch_assoc($check);
        echo "✅ Verified!\n";
        echo "   Title: " . $notif['title'] . "\n";
        echo "   Message: " . $notif['message'] . "\n";
        echo "   Link: " . $notif['link'] . "\n";
        echo "   Read: " . ($notif['is_read'] ? 'Yes' : 'No') . "\n";
    }
} else {
    echo "❌ Failed to create notification\n";
}
?>