<?php
// auth/test_login.php - DEBUG LOGIN
require_once '../config/database.php';

echo "<h1>Login Debug Tool</h1>";

// Check super admin user in database
$super_query = "SELECT * FROM users WHERE email = 'fortuna@salonpro.com'";
$super_result = mysqli_query($conn, $super_query);

if ($super_result && $user = mysqli_fetch_assoc($super_result)) {
    echo "<h2>Super Admin User Found:</h2>";
    echo "<pre>";
    print_r([
        'id' => $user['id'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'is_super_admin' => $user['is_super_admin'],
        'is_active' => $user['is_active'],
        'password_hash' => substr($user['password'], 0, 30) . '...'
    ]);
    echo "</pre>";
    
    // Test password
    $test_password = 'super123';
    $verify = password_verify($test_password, $user['password']);
    echo "<p>Password 'super123' verification: " . ($verify ? "✅ CORRECT" : "❌ INCORRECT") . "</p>";
    
    if (!$verify) {
        // Generate new hash
        $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
        echo "<p>New hash for 'super123': <code>" . $new_hash . "</code></p>";
        echo "<p>Run this SQL to fix:</p>";
        echo "<code>UPDATE users SET password = '$new_hash' WHERE email = 'fortuna@salonpro.com';</code>";
    }
} else {
    echo "<p style='color: red;'>❌ Super Admin user NOT FOUND in database!</p>";
}

// Check all roles in database
echo "<h2>All Users in Database:</h2>";
$all_users = mysqli_query($conn, "SELECT id, full_name, email, role, is_super_admin FROM users");
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Is Super Admin</th></tr>";
while ($row = mysqli_fetch_assoc($all_users)) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['full_name']}</td>";
    echo "<td>{$row['email']}</td>";
    echo "<td>{$row['role']}</td>";
    echo "<td>" . ($row['is_super_admin'] ? '✅' : '❌') . "</td>";
    echo "</tr>";
}
echo "</table>";
?>