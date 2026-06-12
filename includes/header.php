<?php
// includes/header.php - COMPLETE FIXED FILE
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$logged_in = false;
$user_role = '';
$user_name = '';

if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    $logged_in = true;
    $user_role = $_SESSION['user_role'];
    $user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
}

// Detect current location
$current_script = $_SERVER['SCRIPT_NAME'];
$is_in_admin = strpos($current_script, '/admin/') !== false;
$is_in_auth = strpos($current_script, '/auth/') !== false;
$is_in_customer = strpos($current_script, '/customer/') !== false;
$is_in_staff = strpos($current_script, '/staff/') !== false;

// Set correct base path
if ($is_in_admin || $is_in_auth || $is_in_customer || $is_in_staff) {
    $base_path = '../';
} else {
    $base_path = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salon Pro - Luxury Beauty Salon</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #0a0a0a; color: #ffffff; line-height: 1.6; }
        .navbar { background: #050505; padding: 1rem 5%; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #d4af37; position: sticky; top: 0; z-index: 1000; }
        .logo { font-size: 1.8rem; font-weight: bold; color: white; }
        .logo span { color: #d4af37; font-family: 'Playfair Display', serif; }
        .nav-links { display: flex; list-style: none; gap: 2rem; }
        .nav-links a { color: white; text-decoration: none; font-weight: 500; transition: color 0.3s; }
        .nav-links a:hover { color: #d4af37; }
        @media (max-width: 768px) { .navbar { flex-direction: column; gap: 1rem; } .nav-links { flex-wrap: wrap; justify-content: center; gap: 1rem; } }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo"><span>SALON</span> PRO <small>✨</small></div>
        <ul class="nav-links">
            <?php if ($logged_in && ($user_role == 'staff' || $user_role == 'admin' || $user_role == 'customer')): ?>
                <li><a href="<?php echo $base_path; ?>index.php">Home</a></li>
            <?php else: ?>
                <li><a href="<?php echo $base_path; ?>index.php">Home</a></li>
            <?php endif; ?>
            
            <?php if ($logged_in && $user_role == 'customer'): ?>
                <li><a href="<?php echo $base_path; ?>customer/book.php">Book Now</a></li>
                <li><a href="<?php echo $base_path; ?>customer/dashboard.php">My Dashboard</a></li>
                <li><a href="<?php echo $base_path; ?>auth/logout.php">Logout (<?php echo htmlspecialchars($user_name); ?>)</a></li>
            <?php elseif ($logged_in && $user_role == 'staff'): ?>
                <li><a href="<?php echo $base_path; ?>staff/dashboard.php">Staff Panel</a></li>
                <li><a href="<?php echo $base_path; ?>auth/logout.php">Logout (<?php echo htmlspecialchars($user_name); ?>)</a></li>
            <?php elseif ($logged_in && $user_role == 'admin'): ?>
                <li><a href="<?php echo $base_path; ?>admin/dashboard.php">Admin Panel</a></li>
                <li><a href="<?php echo $base_path; ?>auth/logout.php">Logout (<?php echo htmlspecialchars($user_name); ?>)</a></li>
            <?php else: ?>
                <li><a href="<?php echo $base_path; ?>services.php">Services</a></li>
                <li><a href="<?php echo $base_path; ?>customer/book.php">Book Now</a></li>
                <li><a href="<?php echo $base_path; ?>auth/login.php">Login</a></li>
                <li><a href="<?php echo $base_path; ?>auth/register.php">Register</a></li>
            <?php endif; ?>
        </ul>
    </nav>
    <main>