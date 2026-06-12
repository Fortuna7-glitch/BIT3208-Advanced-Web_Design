<?php
// create_admin.php - Creates a new admin user with working password
require_once 'config/database.php';

$password = "admin123";
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// First, let's see what's in the database
echo "<h2>Current Users:</h2>";
$users = mysqli_query($conn, "SELECT id, email, password FROM users");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Email</th><th>Password Hash (first 30 chars)</th></tr>";
while ($user = mysqli_fetch_assoc($users)) {
    echo "<tr>";
    echo "<td>" . $user['id'] . "</td>";
    echo "<td>" . $user['email'] . "</td>";
    echo "<td>" . substr($user['password'], 0, 30) . "...</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Creating/Updating Admin User:</h2>";

// Delete existing admin
mysqli_query($conn, "DELETE FROM users WHERE email = 'admin@salonpro.com'");

// Create new admin
$query = "INSERT INTO users (full_name, email, phone, password, role, is_active) VALUES 
          ('Admin User', 'admin@salonpro.com', '0712345678', '$hashed_password', 'admin', 1)";

if (mysqli_query($conn, $query)) {
    echo "<p style='color: green;'>✓ New admin user created successfully!</p>";
    echo "<p>Email: admin@salonpro.com</p>";
    echo "<p>Password: admin123</p>";
} else {
    echo "<p style='color: red;'>✗ Error: " . mysqli_error($conn) . "</p>";
}

// Create staff user
mysqli_query($conn, "DELETE FROM users WHERE email = 'jane@salonpro.com'");
$staff_query = "INSERT INTO users (full_name, email, phone, password, role, is_active) VALUES 
                ('Jane Smith', 'jane@salonpro.com', '0723456789', '$hashed_password', 'staff', 1)";

if (mysqli_query($conn, $staff_query)) {
    $staff_id = mysqli_insert_id($conn);
    mysqli_query($conn, "INSERT INTO staff_details (user_id, specialty, experience_years, bio) VALUES 
                        ($staff_id, 'Hair Stylist', 5, 'Expert stylist')");
    echo "<p style='color: green;'>✓ Staff user created successfully!</p>";
}

echo "<br><a href='auth/login.php' class='btn btn-primary'>Go to Login Page</a>";

// Style
echo "<style>
    body { font-family: Arial, sans-serif; padding: 2rem; background: #0a0a0a; color: white; }
    h2 { color: #d4af37; }
    table { background: #1a1a1a; border-collapse: collapse; }
    th, td { padding: 8px 12px; text-align: left; }
    .btn-primary { display: inline-block; padding: 10px 20px; background: #d4af37; color: black; text-decoration: none; border-radius: 5px; }
</style>";
?>