<?php
/**
 * Salon Pro — Admin: Branches Management
 * Luxury gold/black theme
 * ENTERPRISE ONLY: Manage multiple salon locations
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
            <div style="font-size: 3rem; margin-bottom: 1rem;">🏪</div>
            <h2 style="color: #d4af37;">Enterprise Feature</h2>
            <p style="color: #aaa;">Multi-branch management is available exclusively on the <strong>Enterprise Plan</strong>.</p>
            <p style="color: #7a7568; font-size: 0.85rem;">Upgrade to manage multiple salon locations.</p>
            <a href="upgrade.php" style="display: inline-block; margin-top: 1rem; padding: 10px 25px; background: #d4af37; color: #050505; border-radius: 25px; text-decoration: none; font-weight: 600;">✨ Upgrade to Enterprise</a>
            <a href="dashboard.php" style="display: inline-block; margin-top: 0.5rem; color: #d4af37; text-decoration: none;">← Back to Dashboard</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// ============================================
// DATABASE TABLE (Run this if not exists)
// ============================================
// CREATE TABLE IF NOT EXISTS branches (
//     id INT AUTO_INCREMENT PRIMARY KEY,
//     salon_id INT NOT NULL,
//     branch_name VARCHAR(100) NOT NULL,
//     address TEXT,
//     phone VARCHAR(20),
//     email VARCHAR(100),
//     manager VARCHAR(100),
//     opening_time TIME DEFAULT '09:00:00',
//     closing_time TIME DEFAULT '18:00:00',
//     is_active BOOLEAN DEFAULT TRUE,
//     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
//     FOREIGN KEY (salon_id) REFERENCES salons(id) ON DELETE CASCADE
// );

// ============================================
// HANDLE ACTIONS
// ============================================
$error = '';
$success = '';
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Add or Update Branch
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_branch'])) {
    $branch_name = mysqli_real_escape_string($conn, $_POST['branch_name']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $manager = mysqli_real_escape_string($conn, $_POST['manager']);
    $opening_time = mysqli_real_escape_string($conn, $_POST['opening_time']);
    $closing_time = mysqli_real_escape_string($conn, $_POST['closing_time']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0;
    
    if ($branch_id > 0) {
        // UPDATE
        $update_query = "UPDATE branches SET 
                         branch_name = '$branch_name',
                         address = '$address',
                         phone = '$phone',
                         email = '$email',
                         manager = '$manager',
                         opening_time = '$opening_time',
                         closing_time = '$closing_time',
                         is_active = $is_active
                         WHERE id = $branch_id AND salon_id = $salon_id";
        if (mysqli_query($conn, $update_query)) {
            logAudit('branch_updated', 'branches', "Updated branch: $branch_name", $admin_id);
            $success = "Branch updated successfully!";
            $edit_id = 0;
        } else {
            $error = "Failed to update branch: " . mysqli_error($conn);
        }
    } else {
        // INSERT
        $insert_query = "INSERT INTO branches (salon_id, branch_name, address, phone, email, manager, opening_time, closing_time, is_active) 
                         VALUES ($salon_id, '$branch_name', '$address', '$phone', '$email', '$manager', '$opening_time', '$closing_time', $is_active)";
        if (mysqli_query($conn, $insert_query)) {
            logAudit('branch_created', 'branches', "Created branch: $branch_name", $admin_id);
            $success = "Branch added successfully!";
        } else {
            $error = "Failed to add branch: " . mysqli_error($conn);
        }
    }
}

// Delete Branch
if (isset($_GET['delete'])) {
    $branch_id = (int)$_GET['delete'];
    $branch_query = "SELECT branch_name FROM branches WHERE id = $branch_id AND salon_id = $salon_id";
    $branch_result = mysqli_query($conn, $branch_query);
    if ($branch = mysqli_fetch_assoc($branch_result)) {
        $delete_query = "DELETE FROM branches WHERE id = $branch_id AND salon_id = $salon_id";
        if (mysqli_query($conn, $delete_query)) {
            logAudit('branch_deleted', 'branches', "Deleted branch: {$branch['branch_name']}", $admin_id);
            $success = "Branch deleted successfully!";
        } else {
            $error = "Failed to delete branch: " . mysqli_error($conn);
        }
    } else {
        $error = "Branch not found.";
    }
}

// Toggle Branch Status
if (isset($_GET['toggle'])) {
    $branch_id = (int)$_GET['toggle'];
    $toggle_query = "UPDATE branches SET is_active = NOT is_active WHERE id = $branch_id AND salon_id = $salon_id";
    if (mysqli_query($conn, $toggle_query)) {
        logAudit('branch_toggled', 'branches', "Toggled branch ID $branch_id", $admin_id);
        $success = "Branch status updated!";
    } else {
        $error = "Failed to update status: " . mysqli_error($conn);
    }
}

// Get edit data
$edit_branch = null;
if ($edit_id > 0) {
    $edit_query = "SELECT * FROM branches WHERE id = $edit_id AND salon_id = $salon_id";
    $edit_result = mysqli_query($conn, $edit_query);
    $edit_branch = mysqli_fetch_assoc($edit_result);
    if (!$edit_branch) {
        $edit_id = 0;
        $error = "Branch not found.";
    }
}

// ============================================
// GET BRANCHES
// ============================================
$branches_query = "SELECT * FROM branches WHERE salon_id = $salon_id ORDER BY is_active DESC, branch_name ASC";
$branches_result = mysqli_query($conn, $branches_query);

// Get stats
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM branches WHERE salon_id = $salon_id";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

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
    .stat-card.red { border-left-color: #dc3545; }
    .stat-card.red .stat-number { color: #dc3545; }

    .add-form {
        background: #0e0e0e;
        border-radius: 12px;
        padding: 1.2rem 1.5rem;
        border: 1px solid rgba(212, 175, 55, 0.25);
        margin-bottom: 2rem;
    }

    .add-form h3 {
        color: #f0d878;
        font-size: 1rem;
        margin-bottom: 1rem;
    }

    .add-form .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
        align-items: flex-end;
    }

    .add-form .form-group label {
        display: block;
        color: #d4af37;
        font-size: 0.8rem;
        margin-bottom: 0.3rem;
    }

    .add-form .form-group input,
    .add-form .form-group select,
    .add-form .form-group textarea {
        width: 100%;
        padding: 8px 12px;
        background: #1a1a1a;
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 8px;
        color: #f5f0e1;
        font-size: 0.85rem;
    }

    .add-form .form-group input:focus,
    .add-form .form-group select:focus,
    .add-form .form-group textarea:focus {
        outline: none;
        border-color: #d4af37;
    }

    .add-form .form-group textarea {
        resize: vertical;
        min-height: 40px;
        font-family: inherit;
    }

    .add-form .btn-save {
        padding: 8px 25px;
        background: #d4af37;
        border: none;
        border-radius: 25px;
        color: #050505;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
        white-space: nowrap;
    }

    .add-form .btn-save:hover {
        background: #f0d878;
        transform: translateY(-2px);
    }

    .add-form .btn-cancel {
        padding: 8px 25px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        color: #aaa;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
        text-decoration: none;
        white-space: nowrap;
    }

    .add-form .btn-cancel:hover {
        background: #333;
        color: white;
    }

    .table-wrapper {
        overflow-x: auto;
        background: #0e0e0e;
        border-radius: 12px;
        padding: 0;
        border: 1px solid rgba(212, 175, 55, 0.25);
        -webkit-overflow-scrolling: touch;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
        min-width: 700px;
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

    .status-badge {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
    }

    .status-badge.active {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border: 1px solid #28a745;
    }

    .status-badge.inactive {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
        border: 1px solid #dc3545;
    }

    .action-cell {
        display: flex;
        gap: 0.3rem;
        flex-wrap: wrap;
    }

    .action-cell .btn {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
    }

    .btn-edit {
        background: rgba(23, 162, 184, 0.15);
        color: #17a2b8;
        border: 1px solid #17a2b8;
    }

    .btn-edit:hover {
        background: #17a2b8;
        color: white;
    }

    .btn-toggle {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid #d4af37;
    }

    .btn-toggle:hover {
        background: #d4af37;
        color: #050505;
    }

    .btn-delete {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
        border: 1px solid #dc3545;
    }

    .btn-delete:hover {
        background: #dc3545;
        color: white;
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

    .empty-state {
        text-align: center;
        padding: 3rem 0;
        color: #7a7568;
    }

    .back-link {
        display: inline-block;
        margin-top: 1.5rem;
        color: #f0d878;
        text-decoration: none;
    }

    @media (max-width: 1024px) {
        table { min-width: 600px; }
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
        .add-form .form-row { grid-template-columns: 1fr; }
        .add-form .btn-save,
        .add-form .btn-cancel { width: 100%; text-align: center; }
        table { min-width: 500px; font-size: 0.75rem; }
        th, td { padding: 6px; }
        .action-cell { flex-direction: column; }
        .action-cell .btn { width: 100%; text-align: center; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0 0.8rem 0.8rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .quick-links .qlink { font-size: 0.65rem; padding: 0.15rem 0.3rem; }
        .welcome-left h1 { font-size: 1.1rem; }
        .date-badge { font-size: 0.75rem; padding: 0.3rem 0.6rem; }
        .top-bar-right .icon-btn { font-size: 0.8rem; }
        .top-bar-right .icon-btn .badge { width: 14px; height: 14px; font-size: 0.4rem; top: -2px; right: -2px; }
        table { min-width: 400px; font-size: 0.7rem; }
        th, td { padding: 4px; }
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
                    <span class="sub">Branches</span>
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
                    <a href="branches.php" class="qlink active"><i class="ti ti-building"></i> Branches</a>
                    <span class="link-sep">|</span>
                    <a href="loyalty.php" class="qlink"><i class="ti ti-star"></i> Loyalty</a>
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
            <h1>🏪 Branches</h1>
            <p class="subtitle">Manage your salon locations</p>
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

    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-icon">🏪</span>
            <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
            <div class="stat-label">Total Branches</div>
        </div>
        <div class="stat-card green">
            <span class="stat-icon">✅</span>
            <div class="stat-number"><?php echo $stats['active'] ?? 0; ?></div>
            <div class="stat-label">Active</div>
        </div>
        <div class="stat-card red">
            <span class="stat-icon">⏸️</span>
            <div class="stat-number"><?php echo $stats['inactive'] ?? 0; ?></div>
            <div class="stat-label">Inactive</div>
        </div>
    </div>

    <div class="add-form">
        <h3><?php echo $edit_id > 0 ? '✏️ Edit Branch' : '➕ Add New Branch'; ?></h3>
        <form method="POST">
            <?php if ($edit_id > 0): ?>
                <input type="hidden" name="branch_id" value="<?php echo $edit_id; ?>">
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Branch Name</label>
                    <input type="text" name="branch_name" placeholder="e.g., Westlands Branch" value="<?php echo $edit_branch ? htmlspecialchars($edit_branch['branch_name']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Manager</label>
                    <input type="text" name="manager" placeholder="Branch manager name" value="<?php echo $edit_branch ? htmlspecialchars($edit_branch['manager']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" placeholder="0712345678" value="<?php echo $edit_branch ? htmlspecialchars($edit_branch['phone']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="branch@salonpro.com" value="<?php echo $edit_branch ? htmlspecialchars($edit_branch['email']) : ''; ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" placeholder="Full address"><?php echo $edit_branch ? htmlspecialchars($edit_branch['address']) : ''; ?></textarea>
                </div>
                <div class="form-group">
                    <label>Opening Time</label>
                    <input type="time" name="opening_time" value="<?php echo $edit_branch ? $edit_branch['opening_time'] : '09:00'; ?>">
                </div>
                <div class="form-group">
                    <label>Closing Time</label>
                    <input type="time" name="closing_time" value="<?php echo $edit_branch ? $edit_branch['closing_time'] : '18:00'; ?>">
                </div>
                <div class="form-group" style="display: flex; align-items: center; gap: 1rem; padding-top: 0.5rem;">
                    <label style="margin-bottom: 0;">
                        <input type="checkbox" name="is_active" value="1" <?php echo ($edit_branch && $edit_branch['is_active']) || !$edit_branch ? 'checked' : ''; ?>>
                        Active
                    </label>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="display: flex; gap: 0.5rem; align-items: center; grid-column: 1 / -1;">
                    <?php if ($edit_id > 0): ?>
                        <button type="submit" name="save_branch" class="btn-save">💾 Update Branch</button>
                        <a href="branches.php" class="btn-cancel">Cancel</a>
                    <?php else: ?>
                        <button type="submit" name="save_branch" class="btn-save">➕ Add Branch</button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Branch Name</th>
                    <th>Manager</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Hours</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($branches_result) > 0): ?>
                    <?php while($branch = mysqli_fetch_assoc($branches_result)): ?>
                        <tr>
                            <td><?php echo $branch['id']; ?></td>
                            <td><?php echo htmlspecialchars($branch['branch_name']); ?></td>
                            <td><?php echo htmlspecialchars($branch['manager'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($branch['phone'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($branch['email'] ?? 'N/A'); ?></td>
                            <td><?php echo date('g:i A', strtotime($branch['opening_time'])); ?> - <?php echo date('g:i A', strtotime($branch['closing_time'])); ?></td>
                            <td>
                                <span class="status-badge <?php echo $branch['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $branch['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="action-cell">
                                <a href="branches.php?edit=<?php echo $branch['id']; ?>" class="btn btn-edit">✏️ Edit</a>
                                <a href="branches.php?toggle=<?php echo $branch['id']; ?>" class="btn btn-toggle" onclick="return confirm('Toggle branch status?')">
                                    <?php echo $branch['is_active'] ? '🔴' : '🟢'; ?>
                                </a>
                                <a href="branches.php?delete=<?php echo $branch['id']; ?>" class="btn btn-delete" onclick="return confirm('Delete this branch?')">🗑️</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            <div class="empty-state">
                                <p>No branches found. Add your first branch above!</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<?php include '../includes/footer.php'; ?>