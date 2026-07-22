<?php
// includes/header.php - COMPLETE: Fixed logo path, no duplicate headers, working logout
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
$user_email = '';
$is_super_admin = false;
$is_customer = false;
$is_staff = false;
$is_admin = false;

if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    $logged_in = true;
    $user_role = $_SESSION['user_role'];
    $user_name = $_SESSION['user_name'];
    $user_email = $_SESSION['user_email'] ?? '';
    $is_super_admin = ($user_role == 'super_admin');
    $is_customer = ($user_role == 'customer');
    $is_staff = ($user_role == 'staff' || $user_role == 'admin');
    $is_admin = ($user_role == 'admin');
}

// Logo file path
$logo_path = $base_path . 'assets/images/logo.png';
$logo_exists = file_exists($logo_path);

// Get unread notification count for Super Admin
$unread_count = 0;
if ($is_super_admin && function_exists('getUnreadNotificationCount')) {
    $unread_count = getUnreadNotificationCount();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="<?php echo $base_path; ?>manifest.json">
    <link rel="apple-touch-icon" href="<?php echo $base_path; ?>assets/images/icon-192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#0a0a0a">
    <title>Salon Pro - Luxury Beauty Salon</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ============================================
           HEADER STYLES
           ============================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #0a0a0a; color: #ffffff; line-height: 1.6; }

        .header {
            background: #050505;
            border-bottom: 2px solid #d4af37;
            padding: 0.6rem 2%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            min-height: 65px;
        }

        /* LEFT SECTION: Logo */
        .header-left {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        /* Logo Image + Text */
        .header-logo {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            text-decoration: none;
        }
        .header-logo img {
            max-height: 40px;
            width: auto;
            display: block;
        }
        .header-logo .logo-text {
            font-size: 1.4rem;
            font-weight: bold;
            color: white;
            font-family: 'Playfair Display', serif;
        }
        .header-logo .logo-text span {
            color: #d4af37;
        }
        .header-logo .logo-tagline {
            font-size: 0.55rem;
            color: #d4af37;
            font-weight: 300;
            letter-spacing: 1px;
            display: block;
            margin-top: -0.1rem;
        }

        /* RIGHT SECTION: User Badge + Icons */
        .header-right {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .header-icon {
            background: transparent;
            border: none;
            color: #aaa;
            font-size: 1.1rem;
            cursor: pointer;
            padding: 5px 8px;
            transition: all 0.3s;
            border-radius: 5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            position: relative;
        }
        .header-icon:hover {
            color: #d4af37;
            background: rgba(212, 175, 55, 0.1);
        }
        .header-icon .icon-label {
            font-size: 0.65rem;
            font-weight: 500;
            display: none;
        }

        /* Notification Badge on Bell */
        .header-icon .badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #dc3545;
            color: white;
            font-size: 0.55rem;
            font-weight: 700;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-badge {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            color: #ccc;
            font-size: 0.85rem;
            background: rgba(212, 175, 55, 0.1);
            padding: 0.3rem 0.8rem 0.3rem 0.3rem;
            border-radius: 50px;
            border: 1px solid rgba(212, 175, 55, 0.3);
            cursor: pointer;
            transition: all 0.3s;
        }
        .user-badge:hover {
            background: rgba(212, 175, 55, 0.2);
            border-color: #d4af37;
        }
        .user-badge .avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #d4af37;
            color: #050505;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        .user-badge .user-info {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }
        .user-badge .user-name {
            color: white;
            font-weight: 500;
            font-size: 0.85rem;
        }
        .user-badge .user-role {
            color: #d4af37;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Show label on larger screens */
        @media (min-width: 769px) {
            .header-icon .icon-label {
                display: inline;
            }
        }

        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 768px) {
            .header { padding: 0.5rem 3%; min-height: 55px; }
            .header-logo img { max-height: 32px; }
            .header-logo .logo-text { font-size: 1.1rem; }
            .header-logo .logo-tagline { font-size: 0.45rem; }
            .user-badge { padding: 0.2rem 0.6rem 0.2rem 0.2rem; }
            .user-badge .user-info .user-name { font-size: 0.7rem; }
            .user-badge .user-info .user-role { font-size: 0.5rem; }
            .user-badge .avatar { width: 25px; height: 25px; font-size: 0.65rem; }
            .header-icon { font-size: 0.95rem; padding: 4px 6px; }
            .header-icon .icon-label { display: none; }
        }

        @media (max-width: 480px) {
            .header { padding: 0.4rem 2%; min-height: 48px; }
            .header-logo img { max-height: 28px; }
            .header-logo .logo-text { font-size: 0.9rem; }
            .header-logo .logo-tagline { font-size: 0.4rem; }
            .user-badge { padding: 0.15rem 0.4rem 0.15rem 0.15rem; gap: 0.3rem; }
            .user-badge .user-info .user-name { font-size: 0.6rem; }
            .user-badge .user-info .user-role { font-size: 0.45rem; }
            .user-badge .avatar { width: 22px; height: 22px; font-size: 0.55rem; }
            .header-icon { font-size: 0.8rem; padding: 3px 5px; }
        }
    </style>
</head>
<body>

<!-- ============================================
   HEADER
   ============================================ -->
<header class="header" id="mainHeader">

    <!-- LEFT: Logo Image + Text -->
    <div class="header-left">
        <a href="<?php echo $base_path; ?>index.php" class="header-logo">
            <?php if ($logo_exists): ?>
                <img src="<?php echo $logo_path; ?>" alt="Salon Pro Logo">
            <?php endif; ?>
            <div>
                <span class="logo-text"><span>SALON</span> PRO</span>
                <span class="logo-tagline">Where Beauty Meets Luxury</span>
            </div>
        </a>
    </div>

    <!-- RIGHT: User Badge + Icons -->
    <div class="header-right">
        
        <!-- SEARCH ICON -->
        <a href="#" class="header-icon" id="searchToggle" title="Search (Ctrl+K)">
            <i class="fas fa-search"></i>
        </a>

        <?php if ($logged_in): ?>
            
            <!-- SHOP & CART ICONS (Only for Customers) -->
            <?php if ($is_customer): ?>
                <!-- Shop Icon -->
                <a href="<?php echo $base_path; ?>customer/shop.php" class="header-icon" title="Shop Products">
                    <i class="fas fa-store"></i>
                    <span class="icon-label">Shop</span>
                </a>

                <!-- Cart Icon with Badge -->
                <?php
                $cart_count = 0;
                if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
                    $cart_count = array_sum($_SESSION['cart']);
                }
                ?>
                <a href="<?php echo $base_path; ?>customer/cart.php" class="header-icon" title="View Cart">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if ($cart_count > 0): ?>
                        <span class="badge"><?php echo $cart_count; ?></span>
                    <?php endif; ?>
                    <span class="icon-label">Cart</span>
                </a>
            <?php endif; ?>

            <!-- User Badge -->
            <div class="user-badge" id="userBadge">
                <div class="avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                <div class="user-info">
                    <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                    <span class="user-role"><?php echo ucfirst(str_replace('_', ' ', $user_role)); ?></span>
                </div>
            </div>

            <!-- NOTIFICATION BELL (Super Admin Only) -->
            <?php if ($is_super_admin): ?>
                <a href="<?php echo $base_path; ?>super_admin/notifications.php" class="header-icon" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="badge"><?php echo min($unread_count, 99); ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <!-- Settings Icon (Super Admin Only) -->
            <?php if ($is_super_admin): ?>
                <a href="<?php echo $base_path; ?>super_admin/settings.php" class="header-icon" title="Settings">
                    <i class="fas fa-cog"></i>
                </a>
            <?php endif; ?>

            <!-- Logout Icon -->
            <a href="<?php echo $base_path; ?>auth/logout.php" class="header-icon" title="Logout" onclick="return confirm('Are you sure you want to logout?')">
                <i class="fas fa-sign-out-alt"></i>
            </a>

        <?php else: ?>
            <!-- Public: Login/Register -->
            <a href="<?php echo $base_path; ?>auth/login.php" class="header-icon" title="Login">
                <i class="fas fa-sign-in-alt"></i>
            </a>
            <a href="<?php echo $base_path; ?>auth/register.php" class="header-icon" title="Register">
                <i class="fas fa-user-plus"></i>
            </a>
        <?php endif; ?>
    </div>

</header>

<!-- ============================================
   SEARCH PANEL OVERLAY
   ============================================ -->
<div id="searchOverlay" class="search-overlay">
    <div id="searchPanel" class="search-panel">
        
        <!-- Search Header -->
        <div class="search-header">
            <span class="search-icon">🔍</span>
            <input type="text" id="searchInput" class="search-input" placeholder="Search salons, owners, or staff..." autocomplete="off">
            <span class="search-shortcut" id="searchShortcut">Ctrl+K</span>
            <button id="searchClose" class="search-close" title="Close (ESC)">✕</button>
        </div>

        <!-- Search Tabs -->
        <div class="search-tabs">
            <button class="search-tab active" data-category="all">All</button>
            <button class="search-tab" data-category="salons">🏪 Salons</button>
            <button class="search-tab" data-category="owners">👤 Owners</button>
            <button class="search-tab" data-category="staff">👥 Staff</button>
        </div>

        <!-- Search Loading -->
        <div id="searchLoading" class="search-loading">
            <div class="spinner"></div>
            <p style="color: #7a7568; margin-top: 0.8rem; font-size: 0.85rem;">Searching...</p>
        </div>

        <!-- Search Recent -->
        <div id="searchRecent" class="search-recent">
            <div class="search-recent-title">📊 Recent Searches</div>
            <div id="searchRecentList"></div>
        </div>

        <!-- Search Results -->
        <div id="searchResults" class="search-results">
            <div class="search-results-header">
                <span id="resultsCount">0 results</span>
            </div>
            <div id="searchResultsTable"></div>
        </div>

    </div>
</div>

<!-- ============================================
   LOAD SEARCH CSS & JS
   ============================================ -->
<link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/search.css">
<script src="<?php echo $base_path; ?>assets/js/search.js"></script>

<main>