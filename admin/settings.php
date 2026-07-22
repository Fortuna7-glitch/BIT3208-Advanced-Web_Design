<?php
/**
 * Salon Pro — Admin: Settings
 * Luxury gold/black theme
 * Plan-Based Access:
 * - Basic: Profile settings only (name, phone, password, theme)
 * - Premium: Profile + Full salon settings (business hours, address, email, phone)
 * - Enterprise: Profile + Full salon settings (business hours, address, email, phone)
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
// GET PLAN FEATURES
// ============================================
$plan_features = getSalonPlanFeatures($salon_id);
$current_plan = $plan_features['plan_name'];
$plan_key = strtolower($current_plan);

// Check if user has full settings access (Premium+)
$has_full_settings = ($plan_key == 'premium' || $plan_key == 'enterprise');

// ============================================
// GET CURRENT SALON DATA
// ============================================
$salon_query = "SELECT * FROM salons WHERE id = $salon_id";
$salon_result = mysqli_query($conn, $salon_query);
$salon = mysqli_fetch_assoc($salon_result);

// Get user data
$user_query = "SELECT * FROM users WHERE id = $admin_id";
$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);

// Get salon settings from salon_settings table
$settings_query = "SELECT setting_key, setting_value FROM salon_settings WHERE setting_key IN ('theme_mode', 'timezone')";
$settings_result = mysqli_query($conn, $settings_query);
$settings = [];
while ($row = mysqli_fetch_assoc($settings_result)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$error = '';
$success = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';

// ============================================
// HANDLE PROFILE UPDATE (All plans)
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        $error = "Current password is incorrect!";
    } else {
        $update_query = "UPDATE users SET full_name = '$full_name', phone = '$phone' WHERE id = $admin_id";
        
        // Update password if provided
        if (!empty($new_password)) {
            if ($new_password !== $confirm_password) {
                $error = "New passwords do not match!";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query .= ", password = '$hashed_password'";
            }
        }
        
        if (empty($error)) {
            if (mysqli_query($conn, $update_query)) {
                $_SESSION['user_name'] = $full_name;
                logAudit('profile_updated', 'settings', "Updated profile", $admin_id);
                $success = "Profile updated successfully!";
                // Refresh user data
                $user_query = "SELECT * FROM users WHERE id = $admin_id";
                $user_result = mysqli_query($conn, $user_query);
                $user = mysqli_fetch_assoc($user_result);
            } else {
                $error = "Failed to update profile: " . mysqli_error($conn);
            }
        }
    }
}

// ============================================
// HANDLE THEME UPDATE (All plans)
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_theme'])) {
    $theme_mode = mysqli_real_escape_string($conn, $_POST['theme_mode']);
    $timezone = mysqli_real_escape_string($conn, $_POST['timezone']);
    
    // Update theme in salon_settings
    $update_theme = "UPDATE salon_settings SET setting_value = '$theme_mode' WHERE setting_key = 'theme_mode'";
    mysqli_query($conn, $update_theme);
    
    $update_timezone = "UPDATE salon_settings SET setting_value = '$timezone' WHERE setting_key = 'timezone'";
    mysqli_query($conn, $update_timezone);
    
    $success = "Theme preferences updated successfully!";
    $settings['theme_mode'] = $theme_mode;
    $settings['timezone'] = $timezone;
}

// ============================================
// HANDLE SALON SETTINGS (Premium+ only)
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_salon']) && $has_full_settings) {
    $salon_name = mysqli_real_escape_string($conn, $_POST['salon_name']);
    $salon_email = mysqli_real_escape_string($conn, $_POST['salon_email']);
    $salon_phone = mysqli_real_escape_string($conn, $_POST['salon_phone']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $opening_time = mysqli_real_escape_string($conn, $_POST['opening_time']);
    $closing_time = mysqli_real_escape_string($conn, $_POST['closing_time']);
    
    $update_query = "UPDATE salons SET 
                     salon_name = '$salon_name',
                     salon_email = '$salon_email',
                     salon_phone = '$salon_phone',
                     address = '$address',
                     opening_time = '$opening_time',
                     closing_time = '$closing_time'
                     WHERE id = $salon_id";
    
    if (mysqli_query($conn, $update_query)) {
        logAudit('salon_settings_updated', 'settings', "Updated salon settings", $admin_id);
        $success = "Salon settings updated successfully!";
        // Refresh data
        $salon_query = "SELECT * FROM salons WHERE id = $salon_id";
        $salon_result = mysqli_query($conn, $salon_query);
        $salon = mysqli_fetch_assoc($salon_result);
    } else {
        $error = "Failed to update salon settings: " . mysqli_error($conn);
    }
}

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
    }

    .plan-badge.basic { background: rgba(23, 162, 184, 0.15); color: #17a2b8; border: 1px solid #17a2b8; }
    .plan-badge.premium { background: rgba(212, 175, 55, 0.15); color: #d4af37; border: 1px solid #d4af37; }
    .plan-badge.enterprise { background: rgba(40, 167, 69, 0.15); color: #28a745; border: 1px solid #28a745; }

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

    .settings-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        border-bottom: 1px solid rgba(212, 175, 55, 0.2);
        padding-bottom: 0.5rem;
    }

    .settings-tabs .tab-btn {
        padding: 10px 24px;
        border-radius: 25px;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.3s;
        background: transparent;
        color: #888;
        border: 1px solid transparent;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .settings-tabs .tab-btn:hover {
        color: #f0d878;
        background: rgba(212, 175, 55, 0.08);
    }

    .settings-tabs .tab-btn.active {
        background: rgba(212, 175, 55, 0.15);
        color: #f0d878;
        border-color: #d4af37;
    }

    .settings-tabs .tab-btn.locked {
        color: #555;
        cursor: not-allowed;
        opacity: 0.5;
    }

    .settings-panel {
        background: #0e0e0e;
        border: 1px solid rgba(212, 175, 55, 0.25);
        border-radius: 12px;
        padding: 1.5rem 2rem;
        max-width: 700px;
        margin: 0 auto;
    }

    .settings-panel h2 {
        color: #f0d878;
        font-size: 1.1rem;
        margin-bottom: 0.5rem;
        font-family: 'Playfair Display', serif;
    }

    .settings-panel .panel-desc {
        color: #7a7568;
        font-size: 0.85rem;
        margin-bottom: 1.5rem;
    }

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

    .form-group .form-control[readonly] {
        opacity: 0.6;
        cursor: not-allowed;
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

    .plan-notice {
        background: rgba(212, 175, 55, 0.05);
        border: 1px solid rgba(212, 175, 55, 0.15);
        border-radius: 8px;
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .plan-notice .icon {
        font-size: 1.5rem;
    }

    .plan-notice .text {
        flex: 1;
        color: #b8b2a0;
        font-size: 0.85rem;
    }

    .plan-notice .text strong {
        color: #f0d878;
    }

    .plan-notice .upgrade-link {
        color: #d4af37;
        text-decoration: none;
        font-weight: 500;
        padding: 4px 16px;
        border: 1px solid #d4af37;
        border-radius: 20px;
        transition: all 0.3s;
    }

    .plan-notice .upgrade-link:hover {
        background: #d4af37;
        color: #050505;
    }

    .locked-feature {
        position: relative;
        opacity: 0.6;
        pointer-events: none;
    }

    .locked-feature .lock-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.3);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        color: #d4af37;
        gap: 0.5rem;
        z-index: 10;
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
        .settings-tabs .tab-btn { font-size: 0.75rem; padding: 8px 14px; }
        .settings-panel { padding: 1rem; }
        .form-row { grid-template-columns: 1fr; }
        .plan-notice { flex-direction: column; text-align: center; }
        .top-bar-right .icon-btn .badge { width: 14px; height: 14px; font-size: 0.4rem; top: -2px; right: -2px; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0 0.8rem 0.8rem; }
        .quick-links .qlink { font-size: 0.65rem; padding: 0.15rem 0.3rem; }
        .welcome-left h1 { font-size: 1.1rem; }
        .date-badge { font-size: 0.75rem; padding: 0.3rem 0.6rem; }
        .top-bar-right .icon-btn { font-size: 0.8rem; }
        .settings-tabs { flex-direction: column; align-items: stretch; }
        .settings-tabs .tab-btn { text-align: center; justify-content: center; }
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
                    <span class="sub">Settings</span>
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
                    <a href="settings.php" class="qlink active"><i class="ti ti-settings"></i> Settings</a>
                    <?php if ($plan_key === 'enterprise'): ?>
                        <span class="link-sep">|</span>
                        <a href="branches.php" class="qlink"><i class="ti ti-building"></i> Branches</a>
                        <span class="link-sep">|</span>
                        <a href="loyalty.php" class="qlink"><i class="ti ti-star"></i> Loyalty</a>
                        <span class="link-sep">|</span>
                        <a href="analytics.php" class="qlink"><i class="ti ti-chart-bar"></i> Analytics</a>
                    <?php endif; ?>
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
            <h1>⚙️ Settings</h1>
            <p class="subtitle">Manage your profile and salon configuration</p>
            <span class="plan-badge <?php echo $plan_key; ?>">
                <?php echo $current_plan; ?> Plan
            </span>
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

    <!-- Plan Notice -->
    <div class="plan-notice">
        <span class="icon">🔒</span>
        <div class="text">
            You are on the <strong><?php echo $current_plan; ?> Plan</strong>.
            <?php if ($plan_key == 'basic'): ?>
                <span style="color: #7a7568; margin-left: 0.5rem;">
                    (Business settings are available on <strong>Premium</strong> and <strong>Enterprise</strong> Plans)
                </span>
                <a href="upgrade.php" class="upgrade-link">✨ Upgrade to Premium</a>
            <?php elseif ($plan_key == 'premium'): ?>
                <span style="color: #28a745; margin-left: 0.5rem;">
                    ✅ Business settings unlocked!
                </span>
                <span style="color: #7a7568; margin-left: 0.5rem;">
                    Upgrade to <strong>Enterprise</strong> for advanced features.
                </span>
                <a href="upgrade.php" class="upgrade-link">✨ Upgrade to Enterprise</a>
            <?php else: ?>
                <span style="color: #28a745; margin-left: 0.5rem;">
                    ✅ Full settings access enabled!
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Settings Tabs -->
    <div class="settings-tabs">
        <a href="?tab=profile" class="tab-btn <?php echo $active_tab == 'profile' ? 'active' : ''; ?>">
            <i class="ti ti-user"></i> Profile
        </a>
        <a href="?tab=theme" class="tab-btn <?php echo $active_tab == 'theme' ? 'active' : ''; ?>">
            <i class="ti ti-palette"></i> Theme & Preferences
        </a>
        <?php if ($has_full_settings): ?>
            <a href="?tab=salon" class="tab-btn <?php echo $active_tab == 'salon' ? 'active' : ''; ?>">
                <i class="ti ti-building"></i> Salon Settings
            </a>
        <?php else: ?>
            <span class="tab-btn locked">
                <i class="ti ti-building"></i> Salon Settings 🔒
            </span>
        <?php endif; ?>
    </div>

    <!-- ============================================
       TAB: PROFILE SETTINGS (All plans)
       ============================================ -->
    <?php if ($active_tab == 'profile'): ?>
    <div class="settings-panel">
        <h2>👤 Profile Settings</h2>
        <p class="panel-desc">Update your personal information and password.</p>

        <form method="POST">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                <small style="color: #7a7568; font-size: 0.75rem;">Email cannot be changed</small>
            </div>

            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>">
            </div>

            <hr class="hr-divider">

            <h3 style="color: #f0d878; font-size: 1rem; margin-bottom: 1rem;">🔑 Change Password</h3>

            <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="current_password" class="form-control" placeholder="Enter current password" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" placeholder="Enter new password">
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password">
                </div>
            </div>

            <button type="submit" name="update_profile" class="btn-save">💾 Update Profile</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- ============================================
       TAB: THEME & PREFERENCES (All plans)
       ============================================ -->
    <?php if ($active_tab == 'theme'): ?>
    <div class="settings-panel">
        <h2>🎨 Theme & Preferences</h2>
        <p class="panel-desc">Customize your dashboard appearance and preferences.</p>

        <form method="POST">
            <div class="form-group">
                <label>Theme Mode</label>
                <select name="theme_mode" class="form-control">
                    <option value="light" <?php echo ($settings['theme_mode'] ?? 'dark') == 'light' ? 'selected' : ''; ?>>☀️ Light</option>
                    <option value="dark" <?php echo ($settings['theme_mode'] ?? 'dark') == 'dark' ? 'selected' : ''; ?>>🌙 Dark</option>
                    <option value="system" <?php echo ($settings['theme_mode'] ?? 'dark') == 'system' ? 'selected' : ''; ?>>🖥️ System</option>
                </select>
            </div>

            <div class="form-group">
                <label>Timezone</label>
                <select name="timezone" class="form-control">
                    <option value="Africa/Nairobi" <?php echo ($settings['timezone'] ?? 'Africa/Nairobi') == 'Africa/Nairobi' ? 'selected' : ''; ?>>Africa/Nairobi (EAT)</option>
                    <option value="Africa/Lagos" <?php echo ($settings['timezone'] ?? '') == 'Africa/Lagos' ? 'selected' : ''; ?>>Africa/Lagos (WAT)</option>
                    <option value="Africa/Johannesburg" <?php echo ($settings['timezone'] ?? '') == 'Africa/Johannesburg' ? 'selected' : ''; ?>>Africa/Johannesburg (SAST)</option>
                    <option value="UTC" <?php echo ($settings['timezone'] ?? '') == 'UTC' ? 'selected' : ''; ?>>UTC</option>
                </select>
            </div>

            <button type="submit" name="update_theme" class="btn-save">💾 Save Preferences</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- ============================================
       TAB: SALON SETTINGS (Premium+ only)
       ============================================ -->
    <?php if ($active_tab == 'salon'): ?>
        <?php if ($has_full_settings): ?>
            <div class="settings-panel">
                <h2>🏪 Salon Settings</h2>
                <p class="panel-desc">Update your salon business information.</p>

                <form method="POST">
                    <div class="form-group">
                        <label>Salon Name</label>
                        <input type="text" name="salon_name" class="form-control" value="<?php echo htmlspecialchars($salon['salon_name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Salon Email</label>
                            <input type="email" name="salon_email" class="form-control" value="<?php echo htmlspecialchars($salon['salon_email'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Salon Phone</label>
                            <input type="tel" name="salon_phone" class="form-control" value="<?php echo htmlspecialchars($salon['salon_phone'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($salon['address'] ?? ''); ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Opening Time</label>
                            <input type="time" name="opening_time" class="form-control" value="<?php echo $salon['opening_time'] ?? '09:00'; ?>">
                        </div>
                        <div class="form-group">
                            <label>Closing Time</label>
                            <input type="time" name="closing_time" class="form-control" value="<?php echo $salon['closing_time'] ?? '18:00'; ?>">
                        </div>
                    </div>

                    <button type="submit" name="update_salon" class="btn-save">💾 Save Salon Settings</button>
                </form>
            </div>
        <?php else: ?>
            <div class="settings-panel" style="text-align: center; padding: 3rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">🔒</div>
                <h2 style="color: #d4af37;">Premium Feature</h2>
                <p style="color: #b8b2a0;">Salon settings are available on <strong>Premium</strong> and <strong>Enterprise</strong> plans.</p>
                <p style="color: #7a7568; font-size: 0.85rem;">Upgrade to manage your salon business hours, address, and contact details.</p>
                <a href="upgrade.php" style="display: inline-block; margin-top: 1rem; padding: 10px 25px; background: #d4af37; color: #050505; border-radius: 25px; text-decoration: none; font-weight: 600;">✨ Upgrade to Premium</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<?php include '../includes/footer.php'; ?>