<?php
// fix_passwords.php - Run this file once to fix passwords
require_once 'config/database.php';

echo "<h1>Fixing Passwords...</h1>";

// Password to set (admin123)
$password = "admin123";
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

echo "Password hash for 'admin123': " . $hashed_password . "<br><br>";

// Update admin
$admin_query = "UPDATE users SET password = '$hashed_password' WHERE email = 'admin@salonpro.com'";
if (mysqli_query($conn, $admin_query)) {
    echo "✓ Admin password updated<br>";
} else {
    echo "✗ Admin update failed: " . mysqli_error($conn) . "<br>";
}

// Update staff
$staff_query = "UPDATE users SET password = '$hashed_password' WHERE email IN ('jane@salonpro.com', 'mary@salonpro.com')";
if (mysqli_query($conn, $staff_query)) {
    echo "✓ Staff passwords updated<br>";
} else {
    echo "✗ Staff update failed: " . mysqli_error($conn) . "<br>";
}

// If no users exist, insert them
$check_admin = mysqli_query($conn, "SELECT id FROM users WHERE email = 'admin@salonpro.com'");
if (mysqli_num_rows($check_admin) == 0) {
    $insert_admin = "INSERT INTO users (full_name, email, phone, password, role, is_active) VALUES 
                    ('Admin', 'admin@salonpro.com', '0712345678', '$hashed_password', 'admin', 1)";
    if (mysqli_query($conn, $insert_admin)) {
        echo "✓ Admin user created<br>";
    }
}

$check_jane = mysqli_query($conn, "SELECT id FROM users WHERE email = 'jane@salonpro.com'");
if (mysqli_num_rows($check_jane) == 0) {
    $insert_jane = "INSERT INTO users (full_name, email, phone, password, role, is_active) VALUES 
                    ('Jane Smith', 'jane@salonpro.com', '0723456789', '$hashed_password', 'staff', 1)";
    if (mysqli_query($conn, $insert_jane)) {
        $jane_id = mysqli_insert_id($conn);
        mysqli_query($conn, "INSERT INTO staff_details (user_id, specialty, experience_years, bio) VALUES 
                            ($jane_id, 'Hair Stylist & Colorist', 5, 'Expert in modern haircuts')");
        echo "✓ Jane Smith created<br>";
    }
}

echo "<br><h2>Done! You can now login with:</h2>";
echo "<ul>";
echo "<li>Email: admin@salonpro.com</li>";
echo "<li>Email: jane@salonpro.com</li>";
echo "<li>Password: admin123</li>";
echo "</ul>";
echo "<a href='auth/login.php'>Go to Login Page</a>";
?>