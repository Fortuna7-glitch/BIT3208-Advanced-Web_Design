<?php
// includes/header.php - MODIFIED with Left-Aligned Hamburger, Right-Aligned User Badge
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

if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    $logged_in = true;
    $user_role = $_SESSION['user_role'];
    $user_name = $_SESSION['user_name'];
    $user_email = $_SESSION['user_email'] ?? '';
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
        RESET & BASE
           ============================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #0a0a0a; color: #ffffff; line-height: 1.6; }

        /* ============================================
        HEADER (DARK THEME - Like VLMS Layout)
           ============================================ */
        .header {
            background: #050505;
            border-bottom: 2px solid #d4af37;
            padding: 0.8rem 2%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            min-height: 65px;
        }

        /* LEFT SECTION: Hamburger + Logo */
        .header-left {
            display: flex;
            align-items: center;
            gap: 1.2rem;
        }

        /* Hamburger Button */
        .hamburger-btn {
            background: transparent;
            border: none;
            color: #d4af37;
            font-size: 1.6rem;
            cursor: pointer;
            padding: 5px 8px;
            transition: color 0.3s;
            display: flex;
            align-items: center;
        }
        .hamburger-btn:hover {
            color: #f9e547;
        }

        /* Logo */
        .logo {
            font-size: 1.6rem;
            font-weight: bold;
            color: white;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .logo span { color: #d4af37; font-family: 'Playfair Display', serif; }
        .logo small { color: #d4af37; font-size: 0.7rem; }

        /* RIGHT SECTION: User Badge + Icons */
        .header-right {
            display: flex;
            align-items: center;
            gap: 1.2rem;
        }

        /* User Badge */
        .user-badge {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            color: #ccc;
            font-size: 0.85rem;
            background: rgba(212, 175, 55, 0.1);
            padding: 0.4rem 1rem;
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
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Header Icons (Settings, Logout) */
        .header-icon {
            background: transparent;
            border: none;
            color: #aaa;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 5px 8px;
            transition: all 0.3s;
            position: relative;
            border-radius: 5px;
        }
        .header-icon:hover {
            color: #d4af37;
            background: rgba(212, 175, 55, 0.1);
        }

        /* ============================================
        MOBILE RESPONSIVE
           ============================================ */
        @media (max-width: 768px) {
            .header {
                padding: 0.6rem 3%;
                min-height: 55px;
            }
            .logo { font-size: 1.2rem; }
            .hamburger-btn { font-size: 1.4rem; }
            .user-badge { padding: 0.3rem 0.8rem; }
            .user-badge .user-info .user-name { font-size: 0.75rem; }
            .user-badge .user-info .user-role { font-size: 0.55rem; }
            .user-badge .avatar { width: 25px; height: 25px; font-size: 0.65rem; }
            .header-icon { font-size: 1rem; }
        }

        @media (max-width: 480px) {
            .header { padding: 0.5rem 2%; min-height: 48px; }
            .logo { font-size: 1rem; }
            .logo small { font-size: 0.55rem; }
            .hamburger-btn { font-size: 1.2rem; padding: 3px 5px; }
            .user-badge { padding: 0.2rem 0.6rem; gap: 0.4rem; }
            .user-badge .user-info .user-name { font-size: 0.65rem; }
            .user-badge .user-info .user-role { font-size: 0.5rem; }
            .user-badge .avatar { width: 22px; height: 22px; font-size: 0.55rem; }
            .header-icon { font-size: 0.85rem; padding: 3px 5px; }
        }
    </style>
</head>
<body>

<!-- ============================================
HEADER
============================================ -->
<header class="header" id="mainHeader">

    <!-- LEFT: Hamburger + Logo -->
    <div class="header-left">
        <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle Navigation">
            <i class="fas fa-bars"></i>
        </button>
        <div class="logo">
            <span>SALON</span> PRO <small><?php echo $is_super_admin ? '👑' : '✨'; ?></small>
        </div>
    </div>

    <!-- RIGHT: User Badge + Icons -->
    <div class="header-right">
        <?php if ($logged_in): ?>
            <!-- User Badge -->
            <div class="user-badge" id="userBadge">
                <div class="avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                <div class="user-info">
                    <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                    <span class="user-role"><?php echo ucfirst(str_replace('_', ' ', $user_role)); ?></span>
                </div>
            </div>

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

<main>