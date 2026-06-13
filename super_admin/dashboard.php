<?php
// super_admin/dashboard.php - COMPLETE WITH CSS
require_once '../config/database.php';

// Check if user is super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    header("Location: ../auth/login.php");
    exit();
}
include '../includes/header.php';

$user_name = $_SESSION['user_name'];

// Get statistics
$salons_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM salons"))['count'] ?? 0;
$admins_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'admin'"))['count'] ?? 0;
$staff_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'staff'"))['count'] ?? 0;
$customers_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'customer'"))['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin - Salon Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #0a0a0a; color: #ffffff; }
        
        /* Navbar */
        .navbar { background: #050505; padding: 1rem 5%; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #d4af37; flex-wrap: wrap; }
        .logo { font-size: 1.8rem; font-weight: bold; color: white; }
        .logo span { color: #d4af37; font-family: 'Playfair Display', serif; }
        .nav-links { display: flex; list-style: none; gap: 2rem; flex-wrap: wrap; }
        .nav-links a { color: white; text-decoration: none; font-weight: 500; transition: color 0.3s; }
        .nav-links a:hover { color: #d4af37; }
        
        /* Container */
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        h1 { color: #d4af37; margin-bottom: 0.5rem; font-family: 'Playfair Display', serif; }
        .subtitle { color: #888; margin-bottom: 2rem; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: #1a1a1a; border-radius: 15px; padding: 1.5rem; text-align: center; border-left: 4px solid #d4af37; }
        .stat-number { font-size: 2rem; font-weight: bold; color: #d4af37; }
        .stat-label { color: #aaa; margin-top: 0.5rem; }
        
        /* Menu Grid */
        .section-title { color: #d4af37; margin: 2rem 0 1rem 0; font-size: 1.3rem; }
        .menu-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-top: 1rem; }
        .menu-card { background: #1a1a1a; border-radius: 15px; padding: 1.5rem; text-align: center; text-decoration: none; color: white; transition: all 0.3s; border: 1px solid rgba(212, 175, 55, 0.3); display: block; }
        .menu-card:hover { transform: translateY(-5px); border-color: #d4af37; background: #252525; }
        .menu-icon { font-size: 2.5rem; color: #d4af37; margin-bottom: 1rem; display: block; }
        .menu-title { font-size: 1.2rem; font-weight: bold; margin-bottom: 0.5rem; color: #d4af37; }
        .menu-desc { font-size: 0.8rem; color: #888; }
        
        /* Button */
        .logout-btn { background: #dc3545; color: white; border: none; padding: 10px 25px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 2rem; }
        .logout-btn:hover { background: #c82333; }
        hr { border-color: #333; margin: 2rem 0; }
        
        @media (max-width: 768px) { 
            .navbar { flex-direction: column; gap: 1rem; text-align: center; }
            .nav-links { justify-content: center; }
            .container { padding: 1rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>👑 Super Admin Dashboard</h1>
        <p class="subtitle">Welcome back, <strong><?php echo htmlspecialchars($user_name); ?></strong>! You have full control over the entire system.</p>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $salons_count; ?></div>
                <div class="stat-label">🏢 Total Salons</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $admins_count; ?></div>
                <div class="stat-label">👨‍💼 Salon Owners</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $staff_count; ?></div>
                <div class="stat-label">👥 Total Staff</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $customers_count; ?></div>
                <div class="stat-label">👤 Total Customers</div>
            </div>
        </div>
        
        <!-- Management Sections -->
        <div class="section-title">📋 System Management</div>
        <div class="menu-grid">
            <a href="salons.php" class="menu-card">
                <span class="menu-icon">🏢</span>
                <div class="menu-title">Manage Salons</div>
                <div class="menu-desc">Add, edit, or delete salons in the system</div>
            </a>
            <a href="admins.php" class="menu-card">
                <span class="menu-icon">👨‍💼</span>
                <div class="menu-title">Salon Owners</div>
                <div class="menu-desc">Manage all salon owners/admins</div>
            </a>
            <a href="subscriptions.php" class="menu-card">
                <span class="menu-icon">💰</span>
                <div class="menu-title">Subscriptions</div>
                <div class="menu-desc">Manage salon subscription plans</div>
            </a>
            <a href="settings.php" class="menu-card">
                <span class="menu-icon">⚙️</span>
                <div class="menu-title">System Settings</div>
                <div class="menu-desc">Global system configuration</div>
            </a>
            <a href="demo_admin.php" class="menu-card">
                <span class="menu-icon">👑</span>
                <div class="menu-title">Demo: Admin View</div>
                <div class="menu-desc">View system as a salon owner (demo)</div>
            </a>
            <a href="../index.php" class="menu-card">
                <span class="menu-icon">🏠</span>
                <div class="menu-title">Homepage</div>
                <div class="menu-desc">Return to public website</div>
            </a>
        </div>
        
        <hr>
        
        <div style="text-align: center;">
            <a href="../auth/logout.php" class="logout-btn">🚪 Logout</a>
        </div>
    </div>
</body>
</html>