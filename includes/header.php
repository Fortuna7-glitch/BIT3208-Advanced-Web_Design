<?php
// includes/header.php - COMPLETE REWRITE with Mobile Responsiveness
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
        /* ============================================
           RESPONSIVE HEADER STYLES
           ============================================ */
        
        /* Reset & Base */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #0a0a0a; color: #ffffff; line-height: 1.6; }
        
        /* ============================================
           DESKTOP NAVBAR (Default)
           ============================================ */
        .navbar {
            background: #050505;
            padding: 1rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #d4af37;
            position: sticky;
            top: 0;
            z-index: 1000;
            flex-wrap: wrap;
            min-height: 70px;
        }
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: white;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .logo span { color: #d4af37; font-family: 'Playfair Display', serif; }
        .logo small { color: #d4af37; font-size: 0.8rem; }
        
        /* Desktop Navigation Links */
        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
            margin: 0;
            padding: 0;
        }
        .nav-links li { display: inline-block; }
        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            font-size: 0.95rem;
            white-space: nowrap;
        }
        .nav-links a:hover { color: #d4af37; }
        
        /* Mobile Hamburger Button */
        .hamburger {
            display: none;
            background: transparent;
            border: none;
            color: #d4af37;
            font-size: 1.8rem;
            cursor: pointer;
            padding: 5px 10px;
        }
        .hamburger:hover { color: #f9e547; }
        
        /* ============================================
           TABLET & MOBILE RESPONSIVE
           ============================================ */
        
        /* Tablet: Up to 1024px */
        @media (max-width: 1024px) {
            .nav-links { gap: 1.5rem; }
            .nav-links a { font-size: 0.85rem; }
        }
        
        /* Mobile: Up to 768px */
        @media (max-width: 768px) {
            .navbar {
                flex-wrap: wrap;
                padding: 0.8rem 4%;
                min-height: 60px;
            }
            .logo { font-size: 1.4rem; }
            
            /* Show hamburger button */
            .hamburger { display: block; }
            
            /* Hide nav links by default on mobile */
            .nav-links {
                display: none;
                flex-direction: column;
                width: 100%;
                gap: 0;
                padding: 1rem 0;
                border-top: 1px solid rgba(212, 175, 55, 0.3);
                margin-top: 0.8rem;
            }
            
            /* Show when active */
            .nav-links.active {
                display: flex;
            }
            
            .nav-links li {
                width: 100%;
                text-align: center;
                padding: 0.5rem 0;
                border-bottom: 1px solid rgba(255,255,255,0.05);
            }
            .nav-links li:last-child { border-bottom: none; }
            
            .nav-links a {
                font-size: 1rem;
                padding: 8px 0;
                display: block;
                width: 100%;
            }
        }
        
        /* Small Mobile: Up to 480px */
        @media (max-width: 480px) {
            .logo { font-size: 1.2rem; }
            .navbar { padding: 0.6rem 3%; }
            .hamburger { font-size: 1.5rem; }
            .nav-links a { font-size: 0.9rem; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">
            <span>SALON</span> PRO <small><?php echo $is_super_admin ? '👑' : '✨'; ?></small>
        </div>
        
        <!-- Hamburger Button (Mobile) -->
        <button class="hamburger" id="hamburgerBtn" aria-label="Toggle Navigation">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Navigation Links -->
        <ul class="nav-links" id="navLinks">
            
            <?php if ($logged_in && $is_super_admin): ?>
                <!-- SUPER ADMIN NAVIGATION -->
                <li><a href="<?php echo $base_path; ?>super_admin/dashboard.php">🏠 Home</a></li>
                <li><a href="<?php echo $base_path; ?>super_admin/salons.php">🏢 Salons</a></li>
                <li><a href="<?php echo $base_path; ?>super_admin/admins.php">👨‍💼 Owners</a></li>
                <li><a href="<?php echo $base_path; ?>super_admin/subscriptions.php">💰 Subscriptions</a></li>
                <li><a href="<?php echo $base_path; ?>super_admin/settings.php">⚙️ Settings</a></li>
                <li><a href="<?php echo $base_path; ?>auth/logout.php">🚪 Logout (<?php echo htmlspecialchars($user_name); ?>)</a></li>
                
            <?php elseif ($logged_in && $user_role == 'admin'): ?>
                <!-- REGULAR ADMIN (SALON OWNER) -->
                <li><a href="<?php echo $base_path; ?>index.php">🏠 Home</a></li>
                <li><a href="<?php echo $base_path; ?>find_salons.php">📍 Find a Salon</a></li>
                <li><a href="<?php echo $base_path; ?>admin/dashboard.php">👨‍💼 Admin Panel</a></li>
                <li><a href="<?php echo $base_path; ?>auth/logout.php">🚪 Logout (<?php echo htmlspecialchars($user_name); ?>)</a></li>
                
            <?php elseif ($logged_in && $user_role == 'staff'): ?>
                <!-- STAFF NAVIGATION -->
                <li><a href="<?php echo $base_path; ?>index.php">🏠 Home</a></li>
                <li><a href="<?php echo $base_path; ?>find_salons.php">📍 Find a Salon</a></li>
                <li><a href="<?php echo $base_path; ?>staff/dashboard.php">👩‍💼 Staff Panel</a></li>
                <li><a href="<?php echo $base_path; ?>auth/logout.php">🚪 Logout (<?php echo htmlspecialchars($user_name); ?>)</a></li>
                
            <?php elseif ($logged_in && $user_role == 'customer'): ?>
                <!-- CUSTOMER NAVIGATION -->
                <li><a href="<?php echo $base_path; ?>index.php">🏠 Home</a></li>
                <li><a href="<?php echo $base_path; ?>find_salons.php">📍 Find a Salon</a></li>
                <li><a href="<?php echo $base_path; ?>customer/dashboard.php">📊 My Dashboard</a></li>
                <li><a href="<?php echo $base_path; ?>auth/logout.php">🚪 Logout (<?php echo htmlspecialchars($user_name); ?>)</a></li>
                
            <?php else: ?>
                <!-- PUBLIC NAVIGATION (NOT LOGGED IN) -->
                <li><a href="<?php echo $base_path; ?>index.php">🏠 Home</a></li>
                <li><a href="<?php echo $base_path; ?>find_salons.php">📍 Find a Salon</a></li>
                <li><a href="<?php echo $base_path; ?>auth/login.php">🔐 Login</a></li>
                <li><a href="<?php echo $base_path; ?>auth/register.php">📝 Register</a></li>
            <?php endif; ?>
            
        </ul>
    </nav>
    <main>

    <script>
        // ============================================
        // MOBILE HAMBURGER TOGGLE
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const hamburger = document.getElementById('hamburgerBtn');
            const navLinks = document.getElementById('navLinks');
            
            if (hamburger && navLinks) {
                hamburger.addEventListener('click', function() {
                    navLinks.classList.toggle('active');
                    // Toggle icon between bars and times
                    const icon = this.querySelector('i');
                    if (icon) {
                        icon.classList.toggle('fa-bars');
                        icon.classList.toggle('fa-times');
                    }
                });
                
                // Close menu when a link is clicked (optional)
                navLinks.querySelectorAll('a').forEach(function(link) {
                    link.addEventListener('click', function() {
                        navLinks.classList.remove('active');
                        const icon = hamburger.querySelector('i');
                        if (icon) {
                            icon.classList.add('fa-bars');
                            icon.classList.remove('fa-times');
                        }
                    });
                });
            }
        });
    </script>