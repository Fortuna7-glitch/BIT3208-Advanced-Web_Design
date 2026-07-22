<?php
/**
 * Salon Pro — Super Admin System Settings
 * Luxury gold/black theme
 * Tabs: General | Profile | Plan Pricing
 */

require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['user_name'] ?? 'Super Admin';
$admin_email = $_SESSION['user_email'] ?? '';

$error = '';
$success = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';

// ============================================
// GET CURRENT SETTINGS
// ============================================
$settings = [];
$settings_query = "SELECT setting_key, setting_value FROM salon_settings";
$settings_result = mysqli_query($conn, $settings_query);
while ($row = mysqli_fetch_assoc($settings_result)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get current plan prices
$plan_prices = getAllPlanPrices();

// ============================================
// HANDLE GENERAL SETTINGS UPDATE
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_general'])) {
    $site_name = mysqli_real_escape_string($conn, $_POST['site_name']);
    $contact_name = mysqli_real_escape_string($conn, $_POST['contact_name']);
    $contact_phone = mysqli_real_escape_string($conn, $_POST['contact_phone']);
    $contact_email = mysqli_real_escape_string($conn, $_POST['contact_email']);
    $theme_mode = mysqli_real_escape_string($conn, $_POST['theme_mode']);
    $timezone = mysqli_real_escape_string($conn, $_POST['timezone']);
    
    // Update each setting
    $updates = [
        'site_name' => $site_name,
        'contact_name' => $contact_name,
        'contact_phone' => $contact_phone,
        'contact_email' => $contact_email,
        'theme_mode' => $theme_mode,
        'timezone' => $timezone
    ];
    
    $all_success = true;
    foreach ($updates as $key => $value) {
        $query = "UPDATE salon_settings SET setting_value = '$value' WHERE setting_key = '$key'";
        if (!mysqli_query($conn, $query)) {
            $all_success = false;
        }
    }
    
    if ($all_success) {
        $success = "General settings updated successfully!";
        // Refresh settings
        $settings_result = mysqli_query($conn, "SELECT setting_key, setting_value FROM salon_settings");
        $settings = [];
        while ($row = mysqli_fetch_assoc($settings_result)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } else {
        $error = "Failed to update some settings. Please try again.";
    }
}

// ============================================
// HANDLE PROFILE UPDATE
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Get current user data
    $user_query = "SELECT * FROM users WHERE id = $admin_id";
    $user_result = mysqli_query($conn, $user_query);
    $user = mysqli_fetch_assoc($user_result);
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        $error = "Current password is incorrect!";
    } else {
        $update_query = "UPDATE users SET full_name = '$full_name', phone = '$phone'";
        
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
            $update_query .= " WHERE id = $admin_id";
            if (mysqli_query($conn, $update_query)) {
                $_SESSION['user_name'] = $full_name;
                $success = "Profile updated successfully!";
                $admin_name = $full_name;
            } else {
                $error = "Failed to update profile: " . mysqli_error($conn);
            }
        }
    }
}

// ============================================
// HANDLE PLAN PRICING UPDATE
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_pricing'])) {
    $basic_price = (float)$_POST['basic_price'];
    $premium_price = (float)$_POST['premium_price'];
    $enterprise_price = (float)$_POST['enterprise_price'];
    
    $updates = [
        'basic' => $basic_price,
        'premium' => $premium_price,
        'enterprise' => $enterprise_price
    ];
    
    $all_success = true;
    foreach ($updates as $plan => $price) {
        if (!updatePlanPrice($plan, $price)) {
            $all_success = false;
        }
    }
    
    if ($all_success) {
        $success = "Plan pricing updated successfully!";
        $plan_prices = getAllPlanPrices();
    } else {
        $error = "Failed to update plan pricing. Please try again.";
    }
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

    /* ============================================
       STICKY HEADER - Same as Dashboard
       ============================================ */
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
        color: var(--text-secondary);
        font-size: 0.9rem;
    }
    .breadcrumb .current {
        color: var(--gold-light);
        font-weight: 600;
    }
    .breadcrumb .sep {
        color: var(--text-muted);
    }
    .breadcrumb .sub {
        color: var(--text-muted);
    }
    .breadcrumb .menu-icon {
        font-size: 1.3rem;
        color: var(--gold);
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
        color: var(--text-muted);
        font-size: 0.7rem;
        opacity: 0.4;
        font-weight: 100;
    }

    .quick-links .qlink {
        color: var(--text-secondary);
        text-decoration: none;
        font-size: 0.8rem;
        padding: 0.3rem 0.7rem;
        border-radius: 20px;
        transition: all 0.3s ease;
        white-space: nowrap;
        border: 1px solid transparent;
    }

    .quick-links .qlink:hover {
        color: var(--gold-light);
        background: rgba(212, 175, 55, 0.08);
        border-color: rgba(212, 175, 55, 0.15);
    }

    .quick-links .qlink.active {
        color: var(--gold-light);
        background: rgba(212, 175, 55, 0.12);
        border-color: rgba(212, 175, 55, 0.2);
    }

    .top-bar-right {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        flex: 0 0 auto;
    }

    .top-bar-right .search-box {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: 20px;
        padding: 0.35rem 1rem;
        color: var(--text-muted);
        font-size: 0.85rem;
        min-width: 180px;
        position: relative;
    }

    .top-bar-right .search-box input {
        background: none;
        border: none;
        outline: none;
        color: var(--text-primary);
        font-size: 0.85rem;
        flex: 1;
        width: 100px;
    }

    .top-bar-right .search-box input::placeholder {
        color: var(--text-muted);
    }

    .top-bar-right .search-box .search-icon {
        color: var(--gold);
        cursor: pointer;
    }

    .top-bar-right .icon-btn {
        position: relative;
        color: var(--gold-light);
        font-size: 1.2rem;
        cursor: pointer;
    }

    .top-bar-right .topbar-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: var(--panel);
        border: 1px solid var(--gold);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--gold-light);
        font-size: 0.9rem;
    }

    /* ============================================
       WELCOME ROW
       ============================================ */
    .welcome-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin: 0.8rem 0 1.2rem 0;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .welcome-row h1 {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--gold-light);
        font-family: 'Playfair Display', serif;
    }
    .welcome-row .subtitle {
        font-size: 0.9rem;
        color: var(--text-muted);
        margin-top: 0.3rem;
    }
    .welcome-row .date-badge {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border: 1px solid var(--panel-border);
        border-radius: 8px;
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
        color: var(--text-secondary);
    }
    .welcome-row .date-badge i {
        color: var(--gold);
    }

    /* ============================================
       SETTINGS TABS
       ============================================ */
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
        color: var(--gold-light);
        background: rgba(212, 175, 55, 0.08);
    }

    .settings-tabs .tab-btn.active {
        background: rgba(212, 175, 55, 0.15);
        color: var(--gold-light);
        border-color: var(--gold);
    }

    /* ============================================
       SETTINGS PANELS
       ============================================ */
    .settings-panel {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: 12px;
        padding: 1.5rem 2rem;
        max-width: 700px;
        margin: 0 auto;
    }

    .settings-panel h2 {
        color: var(--gold-light);
        font-size: 1.1rem;
        margin-bottom: 0.5rem;
        font-family: 'Playfair Display', serif;
    }

    .settings-panel .panel-desc {
        color: var(--text-muted);
        font-size: 0.85rem;
        margin-bottom: 1.5rem;
    }

    .form-group {
        margin-bottom: 1.2rem;
    }

    .form-group label {
        display: block;
        color: var(--text-secondary);
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
        color: var(--text-primary);
        font-size: 0.9rem;
        transition: all 0.3s;
    }

    .form-group .form-control:focus,
    .form-group select:focus {
        outline: none;
        border-color: var(--gold);
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
        background: var(--gold);
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
        background: var(--gold-light);
        transform: translateY(-2px);
    }

    .btn-save:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
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

    .price-input-group {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.8rem 0;
        border-bottom: 1px solid rgba(212, 175, 55, 0.08);
    }

    .price-input-group:last-child {
        border-bottom: none;
    }

    .price-input-group .plan-label {
        font-weight: 600;
        color: var(--text-primary);
        width: 120px;
        flex-shrink: 0;
    }

    .price-input-group .plan-input {
        flex: 1;
        padding: 8px 12px;
        background: #1a1a1a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 8px;
        color: var(--text-primary);
        font-size: 0.9rem;
        max-width: 200px;
    }

    .price-input-group .plan-input:focus {
        outline: none;
        border-color: var(--gold);
    }

    .price-input-group .plan-current {
        color: var(--text-muted);
        font-size: 0.8rem;
    }

    .back-link {
        display: inline-block;
        margin-top: 1.5rem;
        color: var(--gold-light);
        text-decoration: none;
        text-align: center;
        width: 100%;
    }

    .back-link:hover {
        text-decoration: underline;
    }

    /* Responsive */
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
        .top-bar-right .search-box { flex: 1; min-width: 120px; }
        .quick-links .qlink { font-size: 0.7rem; padding: 0.2rem 0.4rem; }
        .settings-tabs .tab-btn { font-size: 0.75rem; padding: 8px 14px; }
        .settings-panel { padding: 1rem; }
        .form-row { grid-template-columns: 1fr; }
        .welcome-row { flex-direction: column; }
        .welcome-row h1 { font-size: 1.3rem; }
        .price-input-group { flex-wrap: wrap; }
        .price-input-group .plan-label { width: 100%; }
        .price-input-group .plan-input { max-width: 100%; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0 0.8rem 0.8rem; }
        .settings-tabs { flex-direction: column; align-items: stretch; }
        .settings-tabs .tab-btn { text-align: center; justify-content: center; }
        .btn-save { width: 100%; text-align: center; }
        .quick-links .qlink { font-size: 0.65rem; padding: 0.15rem 0.3rem; }
    }
</style>

<div class="main-content">

    <!-- ============================================
       STICKY HEADER - Same as Dashboard
       ============================================ -->
    <div class="sticky-header">
        <div class="top-bar">
            <div class="top-bar-left">
                <div class="breadcrumb">
                    <i class="ti ti-menu-2 menu-icon"></i>
                    <span class="current">Dashboard</span>
                    <span class="sep">/</span>
                    <span class="sub">System Settings</span>
                </div>
            </div>

            <div class="top-bar-center">
                <div class="quick-links">
                    <a href="salons.php" class="qlink"><i class="ti ti-building-store"></i> Manage Salons</a>
                    <span class="link-sep">|</span>
                    <a href="admins.php" class="qlink"><i class="ti ti-user-shield"></i> Manage Owners</a>
                    <span class="link-sep">|</span>
                    <a href="subscriptions.php" class="qlink"><i class="ti ti-crown"></i> Subscriptions</a>
                    <span class="link-sep">|</span>
                    <a href="settings.php" class="qlink active"><i class="ti ti-settings"></i> System Settings</a>
                    <span class="link-sep">|</span>
                    <a href="products.php" class="qlink"><i class="ti ti-box"></i> Products</a>
                </div>
            </div>

            <div class="top-bar-right">
                <div class="search-box">
                    <i class="ti ti-search search-icon"></i>
                    <input type="text" id="globalSearch" placeholder="Search...">
                </div>
                <div class="icon-btn"><i class="ti ti-bell"></i></div>
                <div class="topbar-avatar"><i class="ti ti-crown"></i></div>
            </div>
        </div>
    </div>

    <!-- ============================================
       WELCOME ROW
       ============================================ -->
    <div class="welcome-row">
        <div>
            <h1>⚙️ System Settings</h1>
            <p class="subtitle">Manage system configuration, profile, and plan pricing</p>
        </div>
        <div class="date-badge">
            <i class="ti ti-calendar"></i> <?php echo date('j F Y'); ?>
        </div>
    </div>

    <!-- ============================================
       SETTINGS TABS
       ============================================ -->
    <div class="settings-tabs">
        <a href="?tab=general" class="tab-btn <?php echo $active_tab == 'general' ? 'active' : ''; ?>">
            <i class="ti ti-settings"></i> General
        </a>
        <a href="?tab=profile" class="tab-btn <?php echo $active_tab == 'profile' ? 'active' : ''; ?>">
            <i class="ti ti-user"></i> Profile
        </a>
        <a href="?tab=pricing" class="tab-btn <?php echo $active_tab == 'pricing' ? 'active' : ''; ?>">
            <i class="ti ti-coin"></i> Plan Pricing
        </a>
    </div>

    <!-- ============================================
       ALERTS
       ============================================ -->
    <?php if($error): ?>
        <div class="alert alert-danger">❌ <?php echo $error; ?></div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="alert alert-success">✅ <?php echo $success; ?></div>
    <?php endif; ?>

    <!-- ============================================
       TAB: GENERAL SETTINGS
       ============================================ -->
    <?php if ($active_tab == 'general'): ?>
    <div class="settings-panel">
        <h2>📋 General Settings</h2>
        <p class="panel-desc">Configure system-wide settings for the Salon Pro platform.</p>

        <form method="POST">
            <div class="form-group">
                <label>Site Name</label>
                <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($settings['site_name'] ?? 'Salon Pro'); ?>">
            </div>

            <div class="form-group">
                <label>Contact Name</label>
                <input type="text" name="contact_name" class="form-control" value="<?php echo htmlspecialchars($settings['contact_name'] ?? 'Super Admin'); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Contact Phone</label>
                    <input type="text" name="contact_phone" class="form-control" value="<?php echo htmlspecialchars($settings['contact_phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Contact Email</label>
                    <input type="email" name="contact_email" class="form-control" value="<?php echo htmlspecialchars($settings['contact_email'] ?? $admin_email); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Theme Mode</label>
                    <select name="theme_mode" class="form-control">
                        <option value="light" <?php echo ($settings['theme_mode'] ?? 'dark') == 'light' ? 'selected' : ''; ?>>Light</option>
                        <option value="dark" <?php echo ($settings['theme_mode'] ?? 'dark') == 'dark' ? 'selected' : ''; ?>>Dark</option>
                        <option value="system" <?php echo ($settings['theme_mode'] ?? 'dark') == 'system' ? 'selected' : ''; ?>>System</option>
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
            </div>

            <button type="submit" name="update_general" class="btn-save">💾 Save General Settings</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- ============================================
       TAB: PROFILE SETTINGS
       ============================================ -->
    <?php if ($active_tab == 'profile'): ?>
    <div class="settings-panel">
        <h2>👤 Profile Settings</h2>
        <p class="panel-desc">Update your personal information and password.</p>

        <?php
        // Get current user data
        $user_query = "SELECT * FROM users WHERE id = $admin_id";
        $user_result = mysqli_query($conn, $user_query);
        $user = mysqli_fetch_assoc($user_result);
        ?>

        <form method="POST">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name'] ?? $admin_name); ?>" required>
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? $admin_email); ?>" readonly>
                <small style="color: var(--text-muted); font-size: 0.75rem;">Email cannot be changed</small>
            </div>

            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
            </div>

            <hr style="border-color: rgba(212, 175, 55, 0.2); margin: 1.5rem 0;">

            <h3 style="color: var(--gold-light); font-size: 1rem; margin-bottom: 1rem;">🔑 Change Password</h3>

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
       TAB: PLAN PRICING
       ============================================ -->
    <?php if ($active_tab == 'pricing'): ?>
    <div class="settings-panel">
        <h2>💰 Plan Pricing</h2>
        <p class="panel-desc">Update subscription plan prices. Changes will reflect immediately on the Subscriptions page.</p>

        <form method="POST">
            <div class="price-input-group">
                <span class="plan-label">🟢 Basic</span>
                <input type="number" name="basic_price" class="plan-input" value="<?php echo $plan_prices['basic']; ?>" step="100" min="0">
                <span class="plan-current">Current: KSh <?php echo number_format($plan_prices['basic'], 2); ?></span>
            </div>

            <div class="price-input-group">
                <span class="plan-label">🟡 Premium</span>
                <input type="number" name="premium_price" class="plan-input" value="<?php echo $plan_prices['premium']; ?>" step="100" min="0">
                <span class="plan-current">Current: KSh <?php echo number_format($plan_prices['premium'], 2); ?></span>
            </div>

            <div class="price-input-group">
                <span class="plan-label">🔴 Enterprise</span>
                <input type="number" name="enterprise_price" class="plan-input" value="<?php echo $plan_prices['enterprise']; ?>" step="100" min="0">
                <span class="plan-current">Current: KSh <?php echo number_format($plan_prices['enterprise'], 2); ?></span>
            </div>

            <div style="margin-top: 1rem; color: var(--text-muted); font-size: 0.8rem;">
                <i class="ti ti-info-circle"></i> Prices are in KSh (Kenya Shillings) per month.
            </div>

            <button type="submit" name="update_pricing" class="btn-save">💾 Update Pricing</button>
        </form>
    </div>
    <?php endif; ?>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<?php include '../includes/footer.php'; ?>