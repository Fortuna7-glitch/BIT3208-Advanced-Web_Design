<?php
// fix_database.php - Complete database fix
require_once 'config/database.php';

echo "<h1>Fixing Database Structure...</h1>";

// Fix payments table
$queries = [
    "ALTER TABLE payments ADD COLUMN IF NOT EXISTS payment_status VARCHAR(20) DEFAULT 'pending' AFTER payment_method",
    "ALTER TABLE appointments ADD COLUMN IF NOT EXISTS payment_status VARCHAR(20) DEFAULT 'pending' AFTER payment_method", 
    "ALTER TABLE notifications ADD COLUMN IF NOT EXISTS is_read BOOLEAN DEFAULT FALSE AFTER message",
    "UPDATE payments SET payment_status = 'pending' WHERE payment_status IS NULL",
    "UPDATE appointments SET payment_status = 'pending' WHERE payment_status IS NULL"
];

foreach ($queries as $query) {
    if (mysqli_query($conn, $query)) {
        echo "<p style='color: green;'>✓ Executed: " . substr($query, 0, 50) . "...</p>";
    } else {
        if (strpos(mysqli_error($conn), "Duplicate column") === false) {
            echo "<p style='color: orange;'>⚠ " . mysqli_error($conn) . "</p>";
        }
    }
}

// Check if tables have required columns
echo "<h2>Verifying Tables:</h2>";

$tables = ['payments', 'appointments', 'notifications'];
foreach ($tables as $table) {
    $result = mysqli_query($conn, "DESCRIBE $table");
    if ($result) {
        echo "<p>✓ Table '$table' exists</p>";
    } else {
        echo "<p style='color: red;'>✗ Table '$table' missing</p>";
    }
}

echo "<br><a href='admin/dashboard.php' style='display: inline-block; padding: 10px 20px; background: #d4af37; color: black; text-decoration: none; border-radius: 5px;'>Go to Admin Dashboard</a>";
echo " <a href='index.php' style='display: inline-block; padding: 10px 20px; background: #333; color: white; text-decoration: none; border-radius: 5px;'>Go to Homepage</a>";

echo "<style>
    body { font-family: Arial, sans-serif; padding: 2rem; background: #0a0a0a; color: white; }
    h1, h2 { color: #d4af37; }
</style>";
?>