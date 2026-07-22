<?php
/**
 * Salon Pro — Super Admin: Staff Management
 * Luxury gold/black theme
 * Fixed top bar: Breadcrumb | Quick Actions | Search
 */

require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

$admin_name = $_SESSION['user_name'] ?? 'Super Admin';
$error = '';
$success = '';

// ============================================
// HANDLE ACTIONS
// ============================================

// Toggle staff status
if (isset($_GET['toggle'])) {
    $staff_id = (int)$_GET['toggle'];
    $query = "SELECT is_active FROM users WHERE id = $staff_id AND role = 'staff'";
    $result = mysqli_query($conn, $query);
    if ($row = mysqli_fetch_assoc($result)) {
        $new_status = $row['is_active'] ? 0 : 1;
        $update = "UPDATE users SET is_active = $new_status WHERE id = $staff_id";
        if (mysqli_query($conn, $update)) {
            $success = "Staff status updated successfully!";
        } else {
            $error = "Failed to update status: " . mysqli_error($conn);
        }
    }
}

// Reset staff password
if (isset($_GET['reset'])) {
    $staff_id = (int)$_GET['reset'];
    $new_password = 'staff123';
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $update = "UPDATE users SET password = '$hashed_password' WHERE id = $staff_id AND role = 'staff'";
    if (mysqli_query($conn, $update)) {
        $success = "Password reset successfully! New password: staff123";
    } else {
        $error = "Failed to reset password: " . mysqli_error($conn);
    }
}

// Delete staff
if (isset($_GET['delete'])) {
    $staff_id = (int)$_GET['delete'];
    $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments WHERE staff_id = $staff_id");
    $appointments = mysqli_fetch_assoc($check)['count'];
    if ($appointments > 0) {
        $error = "Cannot delete staff with $appointments assigned appointments. Deactivate instead.";
    } else {
        $delete_query = "DELETE FROM users WHERE id = $staff_id AND role = 'staff'";
        if (mysqli_query($conn, $delete_query)) {
            $success = "Staff deleted successfully!";
        } else {
            $error = "Failed to delete staff: " . mysqli_error($conn);
        }
    }
}

// Add new staff
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_staff'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $specialty = mysqli_real_escape_string($conn, $_POST['specialty']);
    $experience_years = (int)$_POST['experience_years'];
    $salon_id = !empty($_POST['salon_id']) ? (int)$_POST['salon_id'] : 'NULL';
    
    $check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");
    if (mysqli_num_rows($check) > 0) {
        $error = "Email already registered!";
    } else {
        $query = "INSERT INTO users (full_name, email, phone, password, role, is_active, salon_id) 
                  VALUES ('$full_name', '$email', '$phone', '$password', 'staff', 1, $salon_id)";
        if (mysqli_query($conn, $query)) {
            $new_staff_id = mysqli_insert_id($conn);
            
            // Add staff details
            $detail_query = "INSERT INTO staff_details (user_id, specialty, experience_years) 
                             VALUES ($new_staff_id, '$specialty', $experience_years)";
            mysqli_query($conn, $detail_query);
            
            // Notify Super Admin
            notifySuperAdmin(
                'staff_added',
                "New Staff Added: $full_name",
                "Email: $email | Specialty: $specialty | Salon: " . ($salon_id ? 'Assigned' : 'Unassigned'),
                "staff.php?view=$new_staff_id"
            );
            
            $success = "Staff member added successfully! Default password: " . $_POST['password'];
        } else {
            $error = "Failed to add staff: " . mysqli_error($conn);
        }
    }
}

// ============================================
// SEARCH/FILTER
// ============================================
$search = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$salon_filter = isset($_GET['salon']) ? (int)$_GET['salon'] : 0;

// ============================================
// GET ALL STAFF
// ============================================
$query = "SELECT u.*, sd.specialty, sd.experience_years, s.salon_name 
          FROM users u 
          LEFT JOIN staff_details sd ON u.id = sd.user_id 
          LEFT JOIN salons s ON u.salon_id = s.id 
          WHERE u.role = 'staff'";
if ($search) {
    $query .= " AND (u.full_name LIKE '%$search%' OR u.email LIKE '%$search%' OR u.phone LIKE '%$search%' OR sd.specialty LIKE '%$search%')";
}
if ($status_filter == 'active') {
    $query .= " AND u.is_active = 1";
} elseif ($status_filter == 'inactive') {
    $query .= " AND u.is_active = 0";
}
if ($salon_filter > 0) {
    $query .= " AND u.salon_id = $salon_filter";
}
$query .= " ORDER BY u.full_name ASC";
$staff_result = mysqli_query($conn, $query);

// Get salons for filter dropdown
$salons_query = "SELECT id, salon_name FROM salons ORDER BY salon_name";
$salons_result = mysqli_query($conn, $salons_query);

// Get stats
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM users WHERE role = 'staff'";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get unread notification count
$unread_count = getUnreadNotificationCount();

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

    .top-bar-right .icon-btn:hover {
        background: rgba(212, 175, 55, 0.08);
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

    /* ============================================
       WELCOME ROW
       ============================================ */
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

    /* ============================================
       STATS GRID
       ============================================ */
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
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(212, 175, 55, 0.08);
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

    /* ============================================
       ADD STAFF FORM
       ============================================ */
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
    .add-form .form-group select {
        width: 100%;
        padding: 8px 12px;
        background: #1a1a1a;
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 8px;
        color: #f5f0e1;
        font-size: 0.85rem;
    }

    .add-form .form-group input:focus,
    .add-form .form-group select:focus {
        outline: none;
        border-color: #d4af37;
    }

    .add-form .btn-add {
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

    .add-form .btn-add:hover {
        background: #f0d878;
        transform: translateY(-2px);
    }

    /* ============================================
       FILTER BAR
       ============================================ */
    .filter-bar {
        display: flex;
        gap: 0.8rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        align-items: center;
        background: #0e0e0e;
        padding: 0.8rem 1.2rem;
        border-radius: 12px;
        border: 1px solid rgba(212, 175, 55, 0.25);
    }

    .filter-bar input,
    .filter-bar select {
        padding: 8px 14px;
        background: #1a1a1a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        color: #f5f0e1;
        font-size: 0.85rem;
        min-width: 130px;
    }

    .filter-bar input:focus,
    .filter-bar select:focus {
        outline: none;
        border-color: #d4af37;
    }

    .filter-bar .filter-btn {
        padding: 8px 20px;
        background: #d4af37;
        border: none;
        border-radius: 25px;
        color: #050505;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
        white-space: nowrap;
    }

    .filter-bar .filter-btn:hover {
        background: #f0d878;
    }

    .filter-bar .clear-btn {
        padding: 8px 20px;
        background: #1a1a1a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        color: #7a7568;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
        text-decoration: none;
        white-space: nowrap;
    }

    .filter-bar .clear-btn:hover {
        background: #2a2a2a;
        color: #f5f0e1;
    }

    /* ============================================
       TABLE
       ============================================ */
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
        min-width: 750px;
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

    .btn-reset {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid #d4af37;
    }

    .btn-reset:hover {
        background: #d4af37;
        color: #050505;
    }

    .btn-toggle {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border: 1px solid #28a745;
    }

    .btn-toggle:hover {
        background: #28a745;
        color: white;
    }

    .btn-toggle.inactive {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
        border-color: #dc3545;
    }

    .btn-toggle.inactive:hover {
        background: #dc3545;
        color: white;
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

    .btn-view {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid rgba(212, 175, 55, 0.3);
    }

    .btn-view:hover {
        background: #d4af37;
        color: #050505;
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

    .back-link:hover {
        text-decoration: underline;
    }

    /* Responsive */
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
        .add-form .btn-add { width: 100%; }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar input,
        .filter-bar select { width: 100%; }
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

    <!-- ============================================
       STICKY HEADER - Dashboard / Staff | Quick Actions | Search | Bell | Avatar
       ============================================ -->
    <div class="sticky-header">
        <div class="top-bar">
            <div class="top-bar-left">
                <div class="breadcrumb">
                    <i class="ti ti-menu-2 menu-icon"></i>
                    <span class="current">Dashboard</span>
                    <span class="sep">/</span>
                    <span class="sub">Staff Management</span>
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
                    <a href="settings.php" class="qlink"><i class="ti ti-settings"></i> System Settings</a>
                    <span class="link-sep">|</span>
                    <a href="products.php" class="qlink"><i class="ti ti-box"></i> Products</a>
                    <span class="link-sep">|</span>
                    <a href="staff.php" class="qlink active"><i class="ti ti-users"></i> Staff</a>
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

    <!-- ============================================
       WELCOME ROW
       ============================================ -->
    <div class="welcome-row">
        <div class="welcome-left">
            <h1>👥 Staff Management</h1>
            <p class="subtitle">View and manage all staff members across all salons</p>
        </div>
        <div class="date-badge">
            <i class="ti ti-calendar"></i> <?php echo date('j F Y'); ?>
        </div>
    </div>

    <!-- ============================================
       ALERTS
       ============================================ -->
    <?php if($success): ?>
        <div class="alert alert-success">✅ <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-danger">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- ============================================
       STATISTICS
       ============================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
            <div class="stat-label">Total Staff</div>
        </div>
        <div class="stat-card green">
            <div class="stat-number"><?php echo $stats['active'] ?? 0; ?></div>
            <div class="stat-label">Active</div>
        </div>
        <div class="stat-card red">
            <div class="stat-number"><?php echo $stats['inactive'] ?? 0; ?></div>
            <div class="stat-label">Inactive</div>
        </div>
    </div>

    <!-- ============================================
       ADD STAFF FORM
       ============================================ -->
    <div class="add-form">
        <h3>➕ Add New Staff Member</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" placeholder="Full name" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="email@example.com" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" placeholder="0712345678" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="text" name="password" placeholder="Default password" required value="staff123">
                </div>
                <div class="form-group">
                    <label>Specialty</label>
                    <select name="specialty">
                        <option value="">-- Select --</option>
                        <option value="Hair Stylist">💇 Hair Stylist</option>
                        <option value="Makeup Artist">💄 Makeup Artist</option>
                        <option value="Nail Technician">💅 Nail Technician</option>
                        <option value="Massage Therapist">💆 Massage Therapist</option>
                        <option value="Barber">✂️ Barber</option>
                        <option value="Esthetician">✨ Esthetician</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Experience (years)</label>
                    <input type="number" name="experience_years" placeholder="0" min="0">
                </div>
                <div class="form-group">
                    <label>Assign to Salon</label>
                    <select name="salon_id">
                        <option value="">-- Unassigned --</option>
                        <?php while($salon = mysqli_fetch_assoc($salons_result)): ?>
                            <option value="<?php echo $salon['id']; ?>">
                                <?php echo htmlspecialchars($salon['salon_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" name="add_staff" class="btn-add">➕ Add Staff</button>
                </div>
            </div>
        </form>
    </div>

    <!-- ============================================
       FILTER BAR
       ============================================ -->
    <div class="filter-bar">
        <form method="GET" style="display: flex; gap: 0.8rem; flex: 1; flex-wrap: wrap; align-items: center;">
            <input type="text" name="q" placeholder="🔍 Search staff..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="status">
                <option value="">All Status</option>
                <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo ($status_filter == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
            </select>
            <select name="salon">
                <option value="0">All Salons</option>
                <?php 
                // Reset pointer and re-fetch salons for filter
                mysqli_data_seek($salons_result, 0);
                while($salon = mysqli_fetch_assoc($salons_result)): 
                ?>
                    <option value="<?php echo $salon['id']; ?>" <?php echo ($salon_filter == $salon['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($salon['salon_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <button type="submit" class="filter-btn">Filter</button>
            <a href="staff.php" class="clear-btn">Clear</a>
        </form>
    </div>

    <!-- ============================================
       STAFF TABLE
       ============================================ -->
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Staff Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Specialty</th>
                    <th>Salon</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($staff_result) > 0): ?>
                    <?php while($staff = mysqli_fetch_assoc($staff_result)): ?>
                        <tr>
                            <td><?php echo $staff['id']; ?></td>
                            <td><?php echo htmlspecialchars($staff['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($staff['email']); ?></td>
                            <td><?php echo htmlspecialchars($staff['phone']); ?></td>
                            <td><?php echo htmlspecialchars($staff['specialty'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($staff['salon_name'] ?? 'Unassigned'); ?></td>
                            <td>
                                <span class="status-badge <?php echo $staff['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $staff['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="action-cell">
                                <a href="staff.php?reset=<?php echo $staff['id']; ?>" class="btn btn-reset" onclick="return confirm('Reset password for <?php echo htmlspecialchars($staff['full_name']); ?>?')">🔑 Reset</a>
                                <a href="staff.php?toggle=<?php echo $staff['id']; ?>" class="btn btn-toggle <?php echo !$staff['is_active'] ? 'inactive' : ''; ?>" onclick="return confirm('Toggle status for <?php echo htmlspecialchars($staff['full_name']); ?>?')">
                                    <?php echo $staff['is_active'] ? '🔴' : '🟢'; ?>
                                </a>
                                <a href="staff.php?delete=<?php echo $staff['id']; ?>" class="btn btn-delete" onclick="return confirm('Delete <?php echo htmlspecialchars($staff['full_name']); ?>? This cannot be undone.')">🗑️</a>
                                <a href="staff.php?view=<?php echo $staff['id']; ?>" class="btn btn-view">👁️</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            <div class="empty-state">
                                <p>No staff members found.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<script>
    // Simple search functionality
    document.getElementById('globalSearch')?.addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            const query = this.value.trim();
            if (query.length > 0) {
                window.location.href = 'staff.php?q=' + encodeURIComponent(query);
            }
        }
    });
</script>

<?php include '../includes/footer.php'; ?>