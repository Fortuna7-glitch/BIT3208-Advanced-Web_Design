<?php
// includes/header.php - COMPLETE WITH SUPER ADMIN NAVIGATION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set base path for all links
$base_path = '';
$current_file = $_SERVER['SCRIPT_NAME'];

if (strpos($current_file, '/admin/') !== false || 
    strpos($current_file, '/auth/') !== false || 
    strpos($current_file, '/customer/') !== false || 
    strpos($current_file, '/staff/') !== false ||
    strpos($current_file, '/super_admin/') !== false) {
    $base_path = '../';
}

// Get user info if logged in
$logged_in = false;
$user_role = '';
$user_name = '';
$is_super_admin = false;

if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    $logged_in = true;
    $user_role = $_SESSION['user_role'];
    $user_name = $_SESSION['user_name'];
    $is_super_admin = ($user_role == 'super_admin');
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
        .navbar { background: #050505; padding: 1rem 5%; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #d4af37; position: sticky; top: 0; z-index: 1000; flex-wrap: wrap; }
        .logo { font-size: 1.8rem; font-weight: bold; color: white; }
        .logo span { color: #d4af37; font-family: 'Playfair Display', serif; }
        .logo small { color: #d4af37; font-size: 0.8rem; }
        .nav-links { display: flex; list-style: none; gap: 2rem; flex-wrap: wrap; }
        .nav-links li { display: inline-block; }
        .nav-links a { color: white; text-decoration: none; font-weight: 500; transition: color 0.3s; }
        .nav-links a:hover { color: #d4af37; }
        @media (max-width: 768px) { 
            .navbar { flex-direction: column; text-align: center; gap: 1rem; } 
            .nav-links { justify-content: center; gap: 1rem; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">
            <span>SALON</span> PRO <small><?php echo $is_super_admin ? '👑' : '✨'; ?></small>
        </div>
        <ul class="nav-links">
            
            <?php if ($logged_in && $is_super_admin): ?>
                <!-- SUPER ADMIN NAVIGATION -->
                <li><a href="<?php echo $base_path; ?>super_admin/dashboard.php">🏠 Home</a></li>
                <li><a href="<?php echo $base_path; ?>super_admin/salons.php">🏢 Salons</a></li>
                <li><a href="<?php echo $base_path; ?>super_admin/admins.php">👨‍💼 Owners</a></li>
                <li><a href="<?php echo $base_path; ?>super_admin/subscriptions.php">💰 Subscriptions</a></li>
                <li><a href="<?php echo $base_path; ?>super_admin/settings.php">⚙️ Settings</a></li>
                <li><a href="<?php echo $base_path; ?>auth/logout.php">🚪 Logout (<?php echo htmlspecialchars($user_name); ?>)</a></li>
                
            <?php elseif ($logged_in && $user_role == 'admin'): ?>
                <!-- REGULAR ADMIN (SALON OWNER) NAVIGATION -->
                <li><a href="<?php echo $base_path; ?>index.php">🏠 Home</a></li>
                <li><a href="<?php echo $base_path; ?>services.php">💇 Services</a></li>
                <li><a href="<?php echo $base_path; ?>customer/book.php">📅 Book Now</a></li>
                <li><a href="<?php echo $base_path; ?>admin/dashboard.php">👨‍💼 Admin Panel</a></li>
                <li><a href="<?php echo $base_path; ?>auth/logout.php">🚪 Logout (<?php echo htmlspecialchars($user_name); ?>)</a></li>
                
            <?php elseif ($logged_in && $user_role == 'staff'): ?>
                <!-- STAFF NAVIGATION -->
                <li><a href="<?php echo $base_path; ?>index.php">🏠 Home</a></li>
                <li><a href="<?php echo $base_path; ?>services.php">💇 Services</a></li>
                <li><a href="<?php echo $base_path; ?>customer/book.php">📅 Book Now</a></li>
                <li><a href="<?php echo $base_path; ?>staff/dashboard.php">👩‍💼 Staff Panel</a></li>
                <li><a href="<?php echo $base_path; ?>auth/logout.php">🚪 Logout (<?php echo htmlspecialchars($user_name); ?>)</a></li>
                
            <?php elseif ($logged_in && $user_role == 'customer'): ?>
                <!-- CUSTOMER NAVIGATION -->
                <li><a href="<?php echo $base_path; ?>index.php">🏠 Home</a></li>
                <li><a href="<?php echo $base_path; ?>services.php">💇 Services</a></li>
                <li><a href="<?php echo $base_path; ?>customer/book.php">📅 Book Now</a></li>
                <li><a href="<?php echo $base_path; ?>customer/dashboard.php">📊 My Dashboard</a></li>
                <li><a href="<?php echo $base_path; ?>auth/logout.php">🚪 Logout (<?php echo htmlspecialchars($user_name); ?>)</a></li>
                
            <?php else: ?>
                <!-- PUBLIC NAVIGATION (NOT LOGGED IN) -->
                <li><a href="<?php echo $base_path; ?>index.php">🏠 Home</a></li>
                <li><a href="<?php echo $base_path; ?>services.php">💇 Services</a></li>
                <li><a href="<?php echo $base_path; ?>customer/book.php">📅 Book Now</a></li>
                <li><a href="<?php echo $base_path; ?>auth/login.php">🔐 Login</a></li>
                <li><a href="<?php echo $base_path; ?>auth/register.php">📝 Register</a></li>
            <?php endif; ?>
            
        </ul>
    </nav>
    <main>