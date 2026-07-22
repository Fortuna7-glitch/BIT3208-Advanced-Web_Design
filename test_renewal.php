<?php
// test_renewal.php - DEBUG VERSION
require_once 'config/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

$salon_id = 2; // Salon Pro - Headquarters
$plan = 'premium';
$amount = 10000;
$expiry_date = date('Y-m-d', strtotime('+1 month'));

echo "Testing subscription renewal notification...\n\n";

// Check if salon exists
$check_query = "SELECT s.*, u.email, u.phone FROM salons s JOIN users u ON s.owner_id = u.id WHERE s.id = $salon_id";
$check_result = mysqli_query($conn, $check_query);

if (mysqli_num_rows($check_result) == 0) {
    die("❌ Salon not found!");
}

$salon = mysqli_fetch_assoc($check_result);
echo "Salon Found: " . $salon['salon_name'] . "\n";
echo "Owner Email: " . $salon['email'] . "\n";
echo "Owner Phone: " . $salon['phone'] . "\n\n";

// Send notification
$result = sendSubscriptionConfirmation($salon_id, $plan, $amount, $expiry_date);

if ($result) {
    echo "✅ Subscription confirmation sent!\n";
} else {
    echo "❌ Failed to send subscription confirmation\n";
}
?>