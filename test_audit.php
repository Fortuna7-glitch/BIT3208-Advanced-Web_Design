<?php
// test_audit.php - Test the audit log function
require_once '../config/database.php';  // ✅ FIXED: ../config not ..config

// Make sure user is logged in as Super Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'super_admin') {
    die("Please login as Super Admin first.");
}

echo "Testing Audit Log Function...<br><br>";

// Test 1: Log a manual entry
$result1 = logAudit(
    'test_action',
    'test_category',
    'This is a test audit log entry from the testing script'
);

echo "Test 1 - Manual log entry: " . ($result1 ? "✅ SUCCESS" : "❌ FAILED") . "<br>";

// Test 2: Log a login action
$result2 = logAudit(
    'login',
    'auth',
    "User logged in from test script"
);

echo "Test 2 - Login log entry: " . ($result2 ? "✅ SUCCESS" : "❌ FAILED") . "<br>";

// Test 3: Log a salon action
$result3 = logAudit(
    'salon_created',
    'salon',
    "Test salon created with owner 'Test User'"
);

echo "Test 3 - Salon log entry: " . ($result3 ? "✅ SUCCESS" : "❌ FAILED") . "<br>";

// Check if entries were saved
echo "<br>Checking database...<br>";

$check = mysqli_query($conn, "SELECT * FROM audit_logs ORDER BY id DESC LIMIT 5");
if (mysqli_num_rows($check) > 0) {
    echo "✅ Found " . mysqli_num_rows($check) . " recent log entries:<br><br>";
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
    echo "<tr><th>ID</th><th>User</th><th>Action</th><th>Category</th><th>Details</th><th>Time</th></tr>";
    while ($row = mysqli_fetch_assoc($check)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['user_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['action']) . "</td>";
        echo "<td>" . htmlspecialchars($row['category']) . "</td>";
        echo "<td>" . htmlspecialchars($row['details']) . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ No entries found in audit_logs table.";
}
?>