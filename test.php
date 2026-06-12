<?php
// test.php - To test if everything works
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Page</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div style="padding: 2rem; text-align: center;">
        <h1 style="color: #d4af37;">Salon Pro Test Page</h1>
        <p>If you can see this with gold styling, CSS is working!</p>
        
        <?php
        // Test database connection
        $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users");
        if ($result) {
            $count = mysqli_fetch_assoc($result)['count'];
            echo "<p style='color: green;'>✓ Database connected! Total users: $count</p>";
        } else {
            echo "<p style='color: red;'>✗ Database error: " . mysqli_error($conn) . "</p>";
        }
        
        // Test session
        session_start();
        if (isset($_SESSION['user_id'])) {
            echo "<p>✓ You are logged in as: " . htmlspecialchars($_SESSION['user_name']) . "</p>";
        } else {
            echo "<p>ℹ You are not logged in. <a href='auth/login.php'>Login here</a></p>";
        }
        ?>
        
        <div style="margin-top: 2rem;">
            <a href="index.php" class="btn btn-primary">Go to Homepage</a>
            <a href="auth/login.php" class="btn btn-outline">Login</a>
            <a href="auth/register.php" class="btn btn-outline">Register</a>
        </div>
    </div>
</body>
</html>