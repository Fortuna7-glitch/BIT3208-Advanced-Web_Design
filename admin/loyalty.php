<?php
/**
 * Salon Pro — Admin: Loyalty & Rewards
 * Luxury gold/black theme
 * ENTERPRISE ONLY: Manage customer loyalty points and rewards
 */

require_once '../config/database.php';
require_once '../includes/permissions.php';

// Authentication check
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['user_name'] ?? 'Admin';

// Get salon_id from session
$salon_id = getCurrentSalonId();

// ============================================
// PLAN FEATURES CHECK - Enterprise Only
// ============================================
$plan_features = getSalonPlanFeatures($salon_id);
$plan_key = strtolower($plan_features['plan_name']);

if ($plan_key !== 'enterprise') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Feature Unavailable</title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>
    <body>
        <div style="text-align: center; padding: 3rem; background: #1a1a1a; border-radius: 15px; border: 1px solid rgba(212, 175, 55, 0.2); max-width: 500px; margin: 3rem auto;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">⭐</div>
            <h2 style="color: #d4af37;">Enterprise Feature</h2>
            <p style="color: #aaa;">Loyalty & Rewards management is available exclusively on the <strong>Enterprise Plan</strong>.</p>
            <p style="color: #7a7568; font-size: 0.85rem;">Upgrade to build customer loyalty with points and rewards.</p>
            <a href="upgrade.php" style="display: inline-block; margin-top: 1rem; padding: 10px 25px; background: #d4af37; color: #050505; border-radius: 25px; text-decoration: none; font-weight: 600;">✨ Upgrade to Enterprise</a>
            <a href="dashboard.php" style="display: inline-block; margin-top: 0.5rem; color: #d4af37; text-decoration: none;">← Back to Dashboard</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// ============================================
// GET LOYALTY SETTINGS
// ============================================
$settings_query = "SELECT * FROM loyalty_settings WHERE salon_id = $salon_id";
$settings_result = mysqli_query($conn, $settings_query);
$settings = mysqli_fetch_assoc($settings_result);

// If no settings, create default
if (!$settings) {
    $insert_settings = "INSERT INTO loyalty_settings (salon_id, points_per_ksh, points_to_ksh, minimum_redeem, welcome_points, birthday_points, referral_points) 
                        VALUES ($salon_id, 1.00, 0.10, 100, 50, 100, 50)";
    mysqli_query($conn, $insert_settings);
    $settings_query = "SELECT * FROM loyalty_settings WHERE salon_id = $salon_id";
    $settings_result = mysqli_query($conn, $settings_query);
    $settings = mysqli_fetch_assoc($settings_result);
}

// ============================================
// HANDLE ACTIONS
// ============================================
$error = '';
$success = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// ============================================
// UPDATE SETTINGS
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
    $points_per_ksh = (float)$_POST['points_per_ksh'];
    $points_to_ksh = (float)$_POST['points_to_ksh'];
    $minimum_redeem = (int)$_POST['minimum_redeem'];
    $welcome_points = (int)$_POST['welcome_points'];
    $birthday_points = (int)$_POST['birthday_points'];
    $referral_points = (int)$_POST['referral_points'];
    
    $update_query = "UPDATE loyalty_settings SET 
                     points_per_ksh = $points_per_ksh,
                     points_to_ksh = $points_to_ksh,
                     minimum_redeem = $minimum_redeem,
                     welcome_points = $welcome_points,
                     birthday_points = $birthday_points,
                     referral_points = $referral_points
                     WHERE salon_id = $salon_id";
    
    if (mysqli_query($conn, $update_query)) {
        logAudit('loyalty_settings_updated', 'loyalty', "Updated loyalty settings", $admin_id);
        $success = "Loyalty settings updated successfully!";
        // Refresh settings
        $settings_result = mysqli_query($conn, $settings_query);
        $settings = mysqli_fetch_assoc($settings_result);
    } else {
        $error = "Failed to update settings: " . mysqli_error($conn);
    }
}

// ============================================
// AWARD POINTS TO CUSTOMER
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['award_points'])) {
    $customer_id = (int)$_POST['customer_id'];
    $points = (int)$_POST['points'];
    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? 'Manual points awarded');
    
    // Check customer exists
    $check_query = "SELECT id FROM users WHERE id = $customer_id AND role = 'customer' AND salon_id = $salon_id";
    $check_result = mysqli_query($conn, $check_query);
    if (mysqli_num_rows($check_result) == 0) {
        $error = "Customer not found.";
    } else {
        // Update or insert loyalty record
        $loyalty_check = "SELECT id FROM customer_loyalty WHERE customer_id = $customer_id AND salon_id = $salon_id";
        $loyalty_result = mysqli_query($conn, $loyalty_check);
        
        if (mysqli_num_rows($loyalty_result) > 0) {
            $update_points = "UPDATE customer_loyalty SET points = points + $points, lifetime_points = lifetime_points + $points WHERE customer_id = $customer_id AND salon_id = $salon_id";
            mysqli_query($conn, $update_points);
        } else {
            $insert_loyalty = "INSERT INTO customer_loyalty (customer_id, salon_id, points, lifetime_points) VALUES ($customer_id, $salon_id, $points, $points)";
            mysqli_query($conn, $insert_loyalty);
        }
        
        // Log transaction
        $log_query = "INSERT INTO loyalty_transactions (customer_id, salon_id, points, type, description) 
                      VALUES ($customer_id, $salon_id, $points, 'bonus', '$description')";
        mysqli_query($conn, $log_query);
        
        logAudit('loyalty_points_awarded', 'loyalty', "Awarded $points points to customer ID $customer_id", $admin_id);
        $success = "Points awarded successfully!";
    }
}

// ============================================
// REDEEM POINTS FOR CUSTOMER
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['redeem_points'])) {
    $customer_id = (int)$_POST['customer_id'];
    $points = (int)$_POST['points'];
    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? 'Points redeemed');
    
    // Check customer exists
    $check_query = "SELECT id FROM users WHERE id = $customer_id AND role = 'customer' AND salon_id = $salon_id";
    $check_result = mysqli_query($conn, $check_query);
    if (mysqli_num_rows($check_result) == 0) {
        $error = "Customer not found.";
    } else {
        // Check current points
        $loyalty_check = "SELECT points FROM customer_loyalty WHERE customer_id = $customer_id AND salon_id = $salon_id";
        $loyalty_result = mysqli_query($conn, $loyalty_check);
        $loyalty = mysqli_fetch_assoc($loyalty_result);
        
        if (!$loyalty || $loyalty['points'] < $points) {
            $error = "Insufficient points. Available: " . ($loyalty['points'] ?? 0);
        } elseif ($points < $settings['minimum_redeem']) {
            $error = "Minimum redeem is " . $settings['minimum_redeem'] . " points.";
        } else {
            // Deduct points
            $update_points = "UPDATE customer_loyalty SET points = points - $points WHERE customer_id = $customer_id AND salon_id = $salon_id";
            mysqli_query($conn, $update_points);
            
            // Log transaction
            $log_query = "INSERT INTO loyalty_transactions (customer_id, salon_id, points, type, description) 
                          VALUES ($customer_id, $salon_id, -$points, 'redeemed', '$description')";
            mysqli_query($conn, $log_query);
            
            $value = $points * $settings['points_to_ksh'];
            logAudit('loyalty_points_redeemed', 'loyalty', "Redeemed $points points for customer ID $customer_id (KSh $value)", $admin_id);
            $success = "Points redeemed successfully! Value: KSh " . number_format($value, 2);
        }
    }
}

// ============================================
// GET CUSTOMERS WITH LOYALTY DATA
// ============================================
$customers_query = "SELECT u.id, u.full_name, u.email, u.phone, 
                    COALESCE(cl.points, 0) as points, 
                    COALESCE(cl.lifetime_points, 0) as lifetime_points,
                    COALESCE(cl.tier, 'bronze') as tier
                    FROM users u
                    LEFT JOIN customer_loyalty cl ON u.id = cl.customer_id AND cl.salon_id = $salon_id
                    WHERE u.salon_id = $salon_id AND u.role = 'customer' AND u.is_active = 1
                    ORDER BY points DESC";
$customers_result = mysqli_query($conn, $customers_query);

// Get stats
$stats_query = "SELECT 
    COUNT(DISTINCT u.id) as total_customers,
    SUM(COALESCE(cl.points, 0)) as total_points,
    AVG(COALESCE(cl.points, 0)) as avg_points
    FROM users u
    LEFT JOIN customer_loyalty cl ON u.id = cl.customer_id AND cl.salon_id = $salon_id
    WHERE u.salon_id = $salon_id AND u.role = 'customer' AND u.is_active = 1";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get recent transactions
$transactions_query = "SELECT lt.*, u.full_name as customer_name 
                       FROM loyalty_transactions lt
                       JOIN users u ON lt.customer_id = u.id
                       WHERE lt.salon_id = $salon_id
                       ORDER BY lt.created_at DESC
                       LIMIT 10";
$transactions_result = mysqli_query($conn, $transactions_query);

// ============================================
// GET UNREAD NOTIFICATION COUNT
// ============================================
$unread_count = 0;
if (function_exists('getUnreadNotificationCount')) {
    $unread_count = getUnreadNotificationCount();
}

include '../includes/header.php';
?>

<style>
    .main-content {
        padding: 0 2rem 2rem;
        background: #0a0a0a;
        min-height: 100vh;
        margin-top: 0.5rem;
    }

    .sticky-header {
        position: sticky;
        top: 65px;
        z-index: 100;
        background: #0a0a0a;
        padding: 0.5rem 0 0.8rem 0;
        border-bottom: 1px solid rgba(212, 175, 55, 0.08);
    }

    .top-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        padding: 0.2rem 0;
    }

    .top-bar-left {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex: 0 0 auto;
    }

    .breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        color: #b8b2a0;
        font-size: 0.9rem;
    }
    .breadcrumb .current {
        color: #f0d878;
        font-weight: 600;
    }
    .breadcrumb .sep {
        color: #7a7568;
    }
    .breadcrumb .sub {
        color: #7a7568;
    }
    .breadcrumb .menu-icon {
        font-size: 1.3rem;
        color: #d4af37;
        cursor: pointer;
    }

    .top-bar-center {
        display: flex;
        align-items: center;
        gap: 0.3rem;
        flex-wrap: wrap;
        flex: 1 1 auto;
        justify-content: center;
    }

    .quick-links {
        display: flex;
        align-items: center;
        gap: 0.1rem;
        flex-wrap: wrap;
    }

    .quick-links .link-sep {
        color: #7a7568;
        font-size: 0.7rem;
        opacity: 0.4;
        font-weight: 100;
    }

    .quick-links .qlink {
        color: #b8b2a0;
        text-decoration: none;
        font-size: 0.8rem;
        padding: 0.3rem 0.7rem;
        border-radius: 20px;
        transition: all 0.3s ease;
        white-space: nowrap;
        border: 1px solid transparent;
    }

    .quick-links .qlink:hover {
        color: #f0d878;
        background: rgba(212, 175, 55, 0.08);
        border-color: rgba(212, 175, 55, 0.15);
    }

    .quick-links .qlink.active {
        color: #f0d878;
        background: rgba(212, 175, 55, 0.12);
        border-color: rgba(212, 175, 55, 0.2);
    }

    .top-bar-right {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex: 0 0 auto;
    }

    .top-bar-right .icon-btn {
        position: relative;
        color: #f0d878;
        font-size: 1.1rem;
        cursor: pointer;
        text-decoration: none;
        padding: 0.3rem 0.5rem;
        border-radius: 6px;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .top-bar-right .icon-btn .badge {
        position: absolute;
        top: -4px;
        right: -4px;
        background: #dc3545;
        color: white;
        font-size: 0.5rem;
        font-weight: 700;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .top-bar-right .topbar-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #0e0e0e;
        border: 1px solid #d4af37;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #f0d878;
        font-size: 0.9rem;
    }

    .welcome-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 0.8rem 0 1.2rem 0;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .welcome-left h1 {
        font-size: 1.6rem;
        font-weight: 700;
        color: #f0d878;
        font-family: 'Playfair Display', serif;
        margin: 0;
    }

    .welcome-left .subtitle {
        font-size: 0.9rem;
        color: #7a7568;
        margin-top: 0.2rem;
    }

    .welcome-left .plan-badge {
        display: inline-block;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        margin-top: 0.5rem;
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border: 1px solid #28a745;
    }

    .date-badge {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border: 1px solid rgba(212, 175, 55, 0.25);
        border-radius: 8px;
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
        color: #b8b2a0;
        flex-shrink: 0;
        white-space: nowrap;
    }

    .date-badge i {
        color: #d4af37;
    }

    .loyalty-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-top: 1rem;
    }

    .loyalty-panel {
        background: #0e0e0e;
        border: 1px solid rgba(212, 175, 55, 0.25);
        border-radius: 12px;
        padding: 1.5rem;
    }

    .loyalty-panel h2 {
        color: #f0d878;
        font-size: 1rem;
        margin-bottom: 1rem;
        font-family: 'Playfair Display', serif;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: #0e0e0e;
        border-radius: 12px;
        padding: 1rem 1.2rem;
        text-align: center;
        border-left: 4px solid #d4af37;
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(212, 175, 55, 0.08);
    }

    .stat-card .stat-icon {
        font-size: 1.5rem;
        opacity: 0.2;
        position: absolute;
        right: 1rem;
        top: 1rem;
    }

    .stat-card .stat-number {
        font-size: 1.8rem;
        font-weight: bold;
        color: #d4af37;
    }

    .stat-card .stat-label {
        color: #7a7568;
        font-size: 0.75rem;
        margin-top: 0.2rem;
    }

    .stat-card.green { border-left-color: #28a745; }
    .stat-card.green .stat-number { color: #28a745; }
    .stat-card.orange { border-left-color: #ffc107; }
    .stat-card.orange .stat-number { color: #ffc107; }
    .stat-card.blue { border-left-color: #17a2b8; }
    .stat-card.blue .stat-number { color: #17a2b8; }
    .stat-card.purple { border-left-color: #6f42c1; }
    .stat-card.purple .stat-number { color: #6f42c1; }

    .form-group {
        margin-bottom: 1.2rem;
    }

    .form-group label {
        display: block;
        color: #b8b2a0;
        font-size: 0.85rem;
        font-weight: 500;
        margin-bottom: 0.3rem;
    }

    .form-group .form-control,
    .form-group select {
        width: 100%;
        padding: 10px 14px;
        background: #1a1a1a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 8px;
        color: #f5f0e1;
        font-size: 0.9rem;
        transition: all 0.3s;
    }

    .form-group .form-control:focus,
    .form-group select:focus {
        outline: none;
        border-color: #d4af37;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .btn-save {
        padding: 10px 35px;
        background: #d4af37;
        color: #050505;
        border: none;
        border-radius: 25px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        margin-top: 0.5rem;
    }

    .btn-save:hover {
        background: #f0d878;
        transform: translateY(-2px);
    }

    .btn-save:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }

    .btn-sm {
        padding: 6px 16px;
        font-size: 0.8rem;
    }

    .hr-divider {
        border: none;
        border-top: 1px solid rgba(212, 175, 55, 0.15);
        margin: 1.5rem 0;
    }

    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 1rem;
    }

    .alert-success {
        background: rgba(40, 167, 69, 0.2);
        border: 1px solid #28a745;
        color: #28a745;
    }

    .alert-danger {
        background: rgba(220, 53, 69, 0.2);
        border: 1px solid #dc3545;
        color: #dc3545;
    }

    .table-wrapper {
        overflow-x: auto;
        background: #0e0e0e;
        border-radius: 12px;
        padding: 0;
        border: 1px solid rgba(212, 175, 55, 0.25);
        margin-bottom: 1.5rem;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
        min-width: 500px;
    }

    th, td {
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid rgba(212, 175, 55, 0.1);
    }

    th {
        color: #d4af37;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    tr:hover {
        background: rgba(212, 175, 55, 0.05);
    }

    .tier-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
    }

    .tier-badge.bronze { background: rgba(205, 127, 50, 0.15); color: #cd7f32; border: 1px solid #cd7f32; }
    .tier-badge.silver { background: rgba(192, 192, 192, 0.15); color: #c0c0c0; border: 1px solid #c0c0c0; }
    .tier-badge.gold { background: rgba(212, 175, 55, 0.15); color: #d4af37; border: 1px solid #d4af37; }
    .tier-badge.platinum { background: rgba(229, 228, 226, 0.15); color: #e5e4e2; border: 1px solid #e5e4e2; }

    .back-link {
        display: inline-block;
        margin-top: 1.5rem;
        color: #f0d878;
        text-decoration: none;
        text-align: center;
        width: 100%;
    }

    .back-link:hover {
        text-decoration: underline;
    }

    @media (max-width: 1024px) {
        .loyalty-grid { grid-template-columns: 1fr; }
        .form-row { grid-template-columns: 1fr; }
        table { min-width: 400px; }
    }

    @media (max-width: 768px) {
        .main-content { padding: 0 1rem 1rem; }
        .top-bar {
            flex-direction: column;
            align-items: stretch;
            gap: 0.5rem;
        }
        .top-bar-left { width: 100%; }
        .top-bar-center { width: 100%; justify-content: flex-start; }
        .top-bar-right { width: 100%; justify-content: flex-start; }
        .top-bar-right .icon-btn { font-size: 0.95rem; padding: 0.2rem 0.4rem; }
        .top-bar-right .topbar-avatar { width: 28px; height: 28px; font-size: 0.75rem; }
        .quick-links .qlink { font-size: 0.7rem; padding: 0.2rem 0.4rem; }
        .welcome-row { flex-direction: column; align-items: flex-start; }
        .welcome-left h1 { font-size: 1.3rem; }
        .date-badge { align-self: flex-start; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 0.8rem; }
        .stat-card { padding: 0.8rem; }
        .stat-card .stat-number { font-size: 1.4rem; }
        .loyalty-panel { padding: 1rem; }
        .form-row { grid-template-columns: 1fr; }
        table { font-size: 0.75rem; min-width: 300px; }
        th, td { padding: 6px; }
        .top-bar-right .icon-btn .badge { width: 14px; height: 14px; font-size: 0.4rem; top: -2px; right: -2px; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0 0.8rem 0.8rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .quick-links .qlink { font-size: 0.65rem; padding: 0.15rem 0.3rem; }
        .welcome-left h1 { font-size: 1.1rem; }
        .date-badge { font-size: 0.75rem; padding: 0.3rem 0.6rem; }
        .top-bar-right .icon-btn { font-size: 0.8rem; }
        .btn-save { width: 100%; text-align: center; }
    }
</style>

<div class="main-content">

    <div class="sticky-header">
        <div class="top-bar">
            <div class="top-bar-left">
                <div class="breadcrumb">
                    <i class="ti ti-menu-2 menu-icon"></i>
                    <span class="current">Dashboard</span>
                    <span class="sep">/</span>
                    <span class="sub">Loyalty & Rewards</span>
                </div>
            </div>

            <div class="top-bar-center">
                <div class="quick-links">
                    <a href="../staff/book_for_customer.php" class="qlink"><i class="ti ti-calendar-plus"></i> Book</a>
                    <span class="link-sep">|</span>
                    <a href="services.php" class="qlink"><i class="ti ti-scissors"></i> Services</a>
                    <span class="link-sep">|</span>
                    <a href="staff.php" class="qlink"><i class="ti ti-users"></i> Staff</a>
                    <span class="link-sep">|</span>
                    <?php if (hasPlanFeature($salon_id, 'payroll')): ?>
                        <a href="payroll.php" class="qlink"><i class="ti ti-coin"></i> Payroll</a>
                        <span class="link-sep">|</span>
                    <?php endif; ?>
                    <?php if (hasPlanFeature($salon_id, 'permissions')): ?>
                        <a href="permissions.php" class="qlink"><i class="ti ti-key"></i> Permissions</a>
                        <span class="link-sep">|</span>
                    <?php endif; ?>
                    <a href="products.php" class="qlink"><i class="ti ti-box"></i> Products</a>
                    <span class="link-sep">|</span>
                    <a href="product_orders.php" class="qlink"><i class="ti ti-shopping-cart"></i> Orders</a>
                    <span class="link-sep">|</span>
                    <a href="reports.php" class="qlink"><i class="ti ti-chart-line"></i> Reports</a>
                    <span class="link-sep">|</span>
                    <a href="settings.php" class="qlink"><i class="ti ti-settings"></i> Settings</a>
                    <span class="link-sep">|</span>
                    <a href="branches.php" class="qlink"><i class="ti ti-building"></i> Branches</a>
                    <span class="link-sep">|</span>
                    <a href="loyalty.php" class="qlink active"><i class="ti ti-star"></i> Loyalty</a>
                    <span class="link-sep">|</span>
                    <a href="analytics.php" class="qlink"><i class="ti ti-chart-bar"></i> Analytics</a>
                </div>
            </div>

            <div class="top-bar-right">
                <a href="#" class="icon-btn" id="searchToggle" title="Search (Ctrl+K)">
                    <i class="ti ti-search"></i>
                </a>
                <a href="notifications.php" class="icon-btn" title="Notifications">
                    <i class="ti ti-bell"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="badge"><?php echo min($unread_count, 99); ?></span>
                    <?php endif; ?>
                </a>
                <div class="topbar-avatar"><i class="ti ti-crown"></i></div>
            </div>
        </div>
    </div>

    <div class="welcome-row">
        <div class="welcome-left">
            <h1>⭐ Loyalty & Rewards</h1>
            <p class="subtitle">Manage customer loyalty points and rewards</p>
            <span class="plan-badge">Enterprise</span>
        </div>
        <div class="date-badge">
            <i class="ti ti-calendar"></i> <?php echo date('j F Y'); ?>
        </div>
    </div>

    <?php if($error): ?>
        <div class="alert alert-danger">❌ <?php echo $error; ?></div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="alert alert-success">✅ <?php echo $success; ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <span class="stat-icon">👤</span>
            <div class="stat-number"><?php echo $stats['total_customers'] ?? 0; ?></div>
            <div class="stat-label">Active Customers</div>
        </div>
        <div class="stat-card green">
            <span class="stat-icon">⭐</span>
            <div class="stat-number"><?php echo number_format($stats['total_points'] ?? 0); ?></div>
            <div class="stat-label">Total Points</div>
        </div>
        <div class="stat-card orange">
            <span class="stat-icon">📊</span>
            <div class="stat-number"><?php echo number_format($stats['avg_points'] ?? 0, 1); ?></div>
            <div class="stat-label">Avg Points/Customer</div>
        </div>
    </div>

    <div class="loyalty-grid">

        <!-- LEFT: Settings Panel -->
        <div class="loyalty-panel">
            <h2>⚙️ Loyalty Settings</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Points per KSh</label>
                        <input type="number" name="points_per_ksh" class="form-control" step="0.01" min="0" value="<?php echo $settings['points_per_ksh']; ?>" required>
                        <small style="color: #7a7568;">Points earned per KSh spent</small>
                    </div>
                    <div class="form-group">
                        <label>Points to KSh</label>
                        <input type="number" name="points_to_ksh" class="form-control" step="0.01" min="0" value="<?php echo $settings['points_to_ksh']; ?>" required>
                        <small style="color: #7a7568;">Value of 1 point in KSh</small>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Minimum Redeem</label>
                        <input type="number" name="minimum_redeem" class="form-control" min="0" value="<?php echo $settings['minimum_redeem']; ?>" required>
                        <small style="color: #7a7568;">Minimum points to redeem</small>
                    </div>
                    <div class="form-group">
                        <label>Welcome Points</label>
                        <input type="number" name="welcome_points" class="form-control" min="0" value="<?php echo $settings['welcome_points']; ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Birthday Points</label>
                        <input type="number" name="birthday_points" class="form-control" min="0" value="<?php echo $settings['birthday_points']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Referral Points</label>
                        <input type="number" name="referral_points" class="form-control" min="0" value="<?php echo $settings['referral_points']; ?>" required>
                    </div>
                </div>
                <button type="submit" name="update_settings" class="btn-save">💾 Save Settings</button>
            </form>
        </div>

        <!-- RIGHT: Award/Redeem Points -->
        <div class="loyalty-panel">
            <h2>➕ Award / Redeem Points</h2>
            
            <!-- Award Points -->
            <h3 style="color: #d4af37; font-size: 0.9rem; margin-bottom: 0.5rem;">Award Points</h3>
            <form method="POST" style="margin-bottom: 1.5rem;">
                <div class="form-row">
                    <div class="form-group">
                        <label>Customer</label>
                        <select name="customer_id" class="form-control" required>
                            <option value="">-- Select Customer --</option>
                            <?php 
                            $all_customers = mysqli_query($conn, "SELECT id, full_name FROM users WHERE salon_id = $salon_id AND role = 'customer' AND is_active = 1 ORDER BY full_name");
                            while($c = mysqli_fetch_assoc($all_customers)): 
                            ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['full_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Points</label>
                        <input type="number" name="points" class="form-control" min="1" placeholder="e.g., 50" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" class="form-control" placeholder="e.g., Birthday bonus" value="Manual points awarded">
                </div>
                <button type="submit" name="award_points" class="btn-save btn-sm">🎁 Award Points</button>
            </form>

            <hr class="hr-divider">

            <!-- Redeem Points -->
            <h3 style="color: #d4af37; font-size: 0.9rem; margin-bottom: 0.5rem;">Redeem Points</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Customer</label>
                        <select name="customer_id" class="form-control" required>
                            <option value="">-- Select Customer --</option>
                            <?php 
                            mysqli_data_seek($all_customers, 0);
                            while($c = mysqli_fetch_assoc($all_customers)): 
                            ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['full_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Points to Redeem</label>
                        <input type="number" name="points" class="form-control" min="<?php echo $settings['minimum_redeem']; ?>" placeholder="Min: <?php echo $settings['minimum_redeem']; ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" class="form-control" placeholder="e.g., Redeemed for discount" value="Points redeemed">
                </div>
                <button type="submit" name="redeem_points" class="btn-save btn-sm">🔄 Redeem Points</button>
            </form>
        </div>

    </div>

    <!-- Customer Loyalty Table -->
    <h2 class="section-title" style="color: #f0d878; font-size: 1rem; margin: 1.5rem 0 1rem 0;">👤 Customer Loyalty</h2>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Points</th>
                    <th>Lifetime</th>
                    <th>Tier</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($customers_result) > 0): ?>
                    <?php while($customer = mysqli_fetch_assoc($customers_result)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($customer['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($customer['email']); ?></td>
                            <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                            <td><strong style="color: #d4af37;"><?php echo number_format($customer['points']); ?></strong></td>
                            <td><?php echo number_format($customer['lifetime_points']); ?></td>
                            <td><span class="tier-badge <?php echo $customer['tier']; ?>"><?php echo ucfirst($customer['tier']); ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="no-data">No customers found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Recent Transactions -->
    <h2 class="section-title" style="color: #f0d878; font-size: 1rem; margin: 0 0 1rem 0;">📋 Recent Transactions</h2>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Points</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($transactions_result) > 0): ?>
                    <?php while($trans = mysqli_fetch_assoc($transactions_result)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($trans['customer_name']); ?></td>
                            <td style="color: <?php echo $trans['points'] > 0 ? '#28a745' : '#dc3545'; ?>; font-weight: 600;">
                                <?php echo $trans['points'] > 0 ? '+' : ''; ?><?php echo $trans['points']; ?>
                            </td>
                            <td><?php echo ucfirst($trans['type']); ?></td>
                            <td><?php echo htmlspecialchars($trans['description']); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($trans['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="no-data">No transactions yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<?php include '../includes/footer.php'; ?>