<?php
// reset_passwords.php - Run this once to fix all passwords
require_once 'config/database.php';

echo "<h1>Resetting Passwords</h1>";

// Define passwords and their hashes
$passwords = [
    'super123' => password_hash('super123', PASSWORD_DEFAULT),
    'owner123' => password_hash('owner123', PASSWORD_DEFAULT),
    'admin123' => password_hash('admin123', PASSWORD_DEFAULT),
];

echo "<pre>";
print_r($passwords);
echo "</pre>";

// Update Super Admin (Fortuna)
$super_query = "UPDATE users SET password = '{$passwords['super123']}' WHERE email = 'fortuna@salonpro.com' OR is_super_admin = 1";
if (mysqli_query($conn, $super_query)) {
    echo "<p style='color: green;'>✅ Super Admin password updated to: <strong>super123</strong></p>";
} else {
    echo "<p style='color: red;'>❌ Error: " . mysqli_error($conn) . "</p>";
}

// Update all admins (salon owners) - any email containing admin or owner
$admin_query = "UPDATE users SET password = '{$passwords['owner123']}' WHERE role = 'admin'";
if (mysqli_query($conn, $admin_query)) {
    echo "<p style='color: green;'>✅ All Admins (Salon Owners) password updated to: <strong>owner123</strong></p>";
} else {
    echo "<p style='color: red;'>❌ Error: " . mysqli_error($conn) . "</p>";
}

// Update staff
$staff_query = "UPDATE users SET password = '{$passwords['admin123']}' WHERE role = 'staff'";
if (mysqli_query($conn, $staff_query)) {
    echo "<p style='color: green;'>✅ All Staff password updated to: <strong>admin123</strong></p>";
} else {
    echo "<p style='color: red;'>❌ Error: " . mysqli_error($conn) . "</p>";
}

// Update customers (optional - they can reset their own)
$customer_query = "UPDATE users SET password = '{$passwords['admin123']}' WHERE role = 'customer'";
if (mysqli_query($conn, $customer_query)) {
    echo "<p style='color: green;'>✅ All Customers password updated to: <strong>admin123</strong></p>";
} else {
    echo "<p style='color: red;'>❌ Error: " . mysqli_error($conn) . "</p>";
}

// Show all users
echo "<h2>Current Users</h2>";
$users = mysqli_query($conn, "SELECT id, full_name, email, role, is_active FROM users ORDER BY role");
echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Active</th></tr>";
while ($user = mysqli_fetch_assoc($users)) {
    echo "<tr>";
    echo "<td>{$user['id']}</td>";
    echo "<td>{$user['full_name']}</td>";
    echo "<td>{$user['email']}</td>";
    echo "<td>{$user['role']}</td>";
    echo "<td>" . ($user['is_active'] ? '✅' : '❌') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<br><a href='auth/login.php' style='background: #d4af37; color: black; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login →</a>";
?>

<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #0a0a0a; color: white; }
    h1, h2 { color: #d4af37; }
    table { background: #1a1a1a; }
    th { background: #d4af37; color: black; }
    td { padding: 8px; }
    a { display: inline-block; margin-top: 20px; }
</style>