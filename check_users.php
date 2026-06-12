<?php
// check_users.php - Debug script to verify user roles
require_once 'config/database.php';

echo "<h1>User Database Check</h1>";

$result = mysqli_query($conn, "SELECT id, full_name, email, role, is_active, created_at FROM users ORDER BY id DESC");

if ($result && mysqli_num_rows($result) > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr style='background: #d4af37; color: black;'>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Created</th>
          </tr>";
    
    while ($user = mysqli_fetch_assoc($result)) {
        $status = $user['is_active'] ? 'Active' : 'Inactive';
        $status_color = $user['is_active'] ? 'green' : 'red';
        
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['full_name']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td><strong>" . strtoupper($user['role']) . "</strong></td>";
        echo "<td style='color: $status_color'>$status</td>";
        echo "<td>{$user['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No users found in database!</p>";
}

echo "<h2>Session Information</h2>";
session_start();
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<br><a href='auth/login.php'>Go to Login</a> | ";
echo "<a href='index.php'>Go to Home</a>";
?>