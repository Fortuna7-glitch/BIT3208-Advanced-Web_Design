<?php
/**
 * Salon Pro — Admin: Staff Management
 * Luxury gold/black theme
 * Admin can manage staff for their salon only
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

$error = '';
$success = '';
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_staff = null;

// ============================================
// HANDLE ACTIONS
// ============================================

// Toggle staff status
if (isset($_GET['toggle'])) {
    $staff_id = (int)$_GET['toggle'];
    $query = "SELECT is_active FROM users WHERE id = $staff_id AND role = 'staff' AND salon_id = $salon_id";
    $result = mysqli_query($conn, $query);
    if ($row = mysqli_fetch_assoc($result)) {
        $new_status = $row['is_active'] ? 0 : 1;
        $update = "UPDATE users SET is_active = $new_status WHERE id = $staff_id";
        if (mysqli_query($conn, $update)) {
            logAudit('staff_status_toggled', 'staff', "Toggled status for staff ID $staff_id to " . ($new_status ? 'Active' : 'Inactive'), $admin_id);
            $success = "Staff status updated successfully!";
        } else {
            $error = "Failed to update status: " . mysqli_error($conn);
        }
    }
}

// Reset staff password
if (isset($_GET['reset'])) {
    $staff_id = (int)$_GET['reset'];
    $query = "SELECT id FROM users WHERE id = $staff_id AND role = 'staff' AND salon_id = $salon_id";
    $result = mysqli_query($conn, $query);
    if (mysqli_num_rows($result) > 0) {
        $new_password = 'staff123';
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update = "UPDATE users SET password = '$hashed_password' WHERE id = $staff_id";
        if (mysqli_query($conn, $update)) {
            logAudit('staff_password_reset', 'staff', "Reset password for staff ID $staff_id", $admin_id);
            $success = "Password reset successfully! New password: staff123";
        } else {
            $error = "Failed to reset password: " . mysqli_error($conn);
        }
    } else {
        $error = "Staff not found in your salon.";
    }
}

// Delete staff
if (isset($_GET['delete'])) {
    $staff_id = (int)$_GET['delete'];
    $query = "SELECT id FROM users WHERE id = $staff_id AND role = 'staff' AND salon_id = $salon_id";
    $result = mysqli_query($conn, $query);
    if (mysqli_num_rows($result) > 0) {
        $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments WHERE staff_id = $staff_id");
        $appointments = mysqli_fetch_assoc($check)['count'];
        if ($appointments > 0) {
            $error = "Cannot delete staff with $appointments assigned appointments. Deactivate instead.";
        } else {
            $delete_query = "DELETE FROM users WHERE id = $staff_id AND role = 'staff'";
            if (mysqli_query($conn, $delete_query)) {
                logAudit('staff_deleted', 'staff', "Deleted staff ID $staff_id", $admin_id);
                $success = "Staff deleted successfully!";
            } else {
                $error = "Failed to delete staff: " . mysqli_error($conn);
            }
        }
    } else {
        $error = "Staff not found in your salon.";
    }
}

// Get edit data
if ($edit_id > 0) {
    $edit_query = "SELECT u.*, sd.specialty, sd.experience_years 
                   FROM users u 
                   LEFT JOIN staff_details sd ON u.id = sd.user_id 
                   WHERE u.id = $edit_id AND u.role = 'staff' AND u.salon_id = $salon_id";
    $edit_result = mysqli_query($conn, $edit_query);
    $edit_staff = mysqli_fetch_assoc($edit_result);
    if (!$edit_staff) {
        $edit_id = 0;
        $error = "Staff not found.";
    }
}

// Add or Update staff
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_staff'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $specialty = mysqli_real_escape_string($conn, $_POST['specialty']);
    $experience_years = (int)$_POST['experience_years'];
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;
    $staff_id = isset($_POST['staff_id']) ? (int)$_POST['staff_id'] : 0;

    // Check if email already exists (excluding current user)
    $check_query = "SELECT id FROM users WHERE email = '$email' AND role = 'staff'";
    if ($staff_id > 0) {
        $check_query .= " AND id != $staff_id";
    }
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        $error = "Email already registered!";
    } else {
        if ($staff_id > 0) {
            // UPDATE existing staff
            $update_query = "UPDATE users SET full_name = '$full_name', email = '$email', phone = '$phone' WHERE id = $staff_id AND salon_id = $salon_id";
            if (mysqli_query($conn, $update_query)) {
                // Update staff details
                $detail_update = "UPDATE staff_details SET specialty = '$specialty', experience_years = $experience_years WHERE user_id = $staff_id";
                mysqli_query($conn, $detail_update);
                
                // Update password if provided
                if (!empty($_POST['password'])) {
                    $pass_update = "UPDATE users SET password = '$password' WHERE id = $staff_id";
                    mysqli_query($conn, $pass_update);
                }
                
                logAudit('staff_updated', 'staff', "Updated staff: $full_name", $admin_id);
                $success = "Staff updated successfully!";
                $edit_id = 0;
                $edit_staff = null;
            } else {
                $error = "Failed to update staff: " . mysqli_error($conn);
            }
        } else {
            // INSERT new staff
            $default_password = !empty($_POST['password']) ? $_POST['password'] : 'staff123';
            $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
            
            $insert_query = "INSERT INTO users (full_name, email, phone, password, role, is_active, salon_id) 
                            VALUES ('$full_name', '$email', '$phone', '$hashed_password', 'staff', 1, $salon_id)";
            if (mysqli_query($conn, $insert_query)) {
                $new_staff_id = mysqli_insert_id($conn);
                
                // Add staff details
                $detail_query = "INSERT INTO staff_details (user_id, specialty, experience_years) 
                                 VALUES ($new_staff_id, '$specialty', $experience_years)";
                mysqli_query($conn, $detail_query);
                
                logAudit('staff_created', 'staff', "Created staff: $full_name (ID: $new_staff_id)", $admin_id);
                $success = "Staff added successfully! Default password: $default_password";
            } else {
                $error = "Failed to add staff: " . mysqli_error($conn);
            }
        }
    }
}

// ============================================
// SEARCH/FILTER
// ============================================
$search = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// ============================================
// GET STAFF
// ============================================
$query = "SELECT u.*, sd.specialty, sd.experience_years 
          FROM users u 
          LEFT JOIN staff_details sd ON u.id = sd.user_id 
          WHERE u.role = 'staff' AND u.salon_id = $salon_id";
if ($search) {
    $query .= " AND (u.full_name LIKE '%$search%' OR u.email LIKE '%$search%' OR u.phone LIKE '%$search%' OR sd.specialty LIKE '%$search%')";
}
if ($status_filter == 'active') {
    $query .= " AND u.is_active = 1";
} elseif ($status_filter == 'inactive') {
    $query .= " AND u.is_active = 0";
}
$query .= " ORDER BY u.full_name ASC";
$staff_result = mysqli_query($conn, $query);

// Get stats
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM users WHERE role = 'staff' AND salon_id = $salon_id";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get unread notification count
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

    .btn-edit {
        background: rgba(23, 162, 184, 0.15);
        color: #17a2b8;
        border: 1px solid #17a2b8;
    }

    .btn-edit:hover {
        background: #17a2b8;
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
                    <a href="../staff/book_for_customer.php" class="qlink"><i class="ti ti-calendar-plus"></i> Book</a>
                    <span class="link-sep">|</span>
                    <a href="services.php" class="qlink"><i class="ti ti-scissors"></i> Services</a>
                    <span class="link-sep">|</span>
                    <a href="staff.php" class="qlink active"><i class="ti ti-users"></i> Staff</a>
                    <span class="link-sep">|</span>
                    <a href="payroll.php" class="qlink"><i class="ti ti-coin"></i> Payroll</a>
                    <span class="link-sep">|</span>
                    <a href="permissions.php" class="qlink"><i class="ti ti-key"></i> Permissions</a>
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
            <h1>👥 Staff Management</h1>
            <p class="subtitle">Manage your salon staff</p>
        </div>
        <div class="date-badge">
            <i class="ti ti-calendar"></i> <?php echo date('j F Y'); ?>
        </div>
    </div>

    <?php if($success): ?>
        <div class="alert alert-success">✅ <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-danger">❌ <?php echo $error; ?></div>
    <?php endif; ?>

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

    <div class="add-form">
        <h3><?php echo $edit_id > 0 ? '✏️ Edit Staff' : '➕ Add New Staff'; ?></h3>
        <form method="POST">
            <?php if ($edit_id > 0): ?>
                <input type="hidden" name="staff_id" value="<?php echo $edit_id; ?>">
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" placeholder="Full name" value="<?php echo $edit_staff ? htmlspecialchars($edit_staff['full_name']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="email@example.com" value="<?php echo $edit_staff ? htmlspecialchars($edit_staff['email']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" placeholder="0712345678" value="<?php echo $edit_staff ? htmlspecialchars($edit_staff['phone']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Password <?php if ($edit_id > 0): ?><span style="color:#7a7568;font-size:0.7rem;">(Leave blank to keep current)</span><?php endif; ?></label>
                    <input type="text" name="password" placeholder="<?php echo $edit_id > 0 ? 'Leave blank' : 'Default: staff123'; ?>">
                </div>
                <div class="form-group">
                    <label>Specialty</label>
                    <select name="specialty">
                        <option value="">-- Select --</option>
                        <option value="Hair Stylist" <?php echo ($edit_staff && $edit_staff['specialty'] == 'Hair Stylist') ? 'selected' : ''; ?>>💇 Hair Stylist</option>
                        <option value="Makeup Artist" <?php echo ($edit_staff && $edit_staff['specialty'] == 'Makeup Artist') ? 'selected' : ''; ?>>💄 Makeup Artist</option>
                        <option value="Nail Technician" <?php echo ($edit_staff && $edit_staff['specialty'] == 'Nail Technician') ? 'selected' : ''; ?>>💅 Nail Technician</option>
                        <option value="Massage Therapist" <?php echo ($edit_staff && $edit_staff['specialty'] == 'Massage Therapist') ? 'selected' : ''; ?>>💆 Massage Therapist</option>
                        <option value="Barber" <?php echo ($edit_staff && $edit_staff['specialty'] == 'Barber') ? 'selected' : ''; ?>>✂️ Barber</option>
                        <option value="Esthetician" <?php echo ($edit_staff && $edit_staff['specialty'] == 'Esthetician') ? 'selected' : ''; ?>>✨ Esthetician</option>
                        <option value="Receptionist" <?php echo ($edit_staff && $edit_staff['specialty'] == 'Receptionist') ? 'selected' : ''; ?>>📋 Receptionist</option>
                        <option value="Cashier" <?php echo ($edit_staff && $edit_staff['specialty'] == 'Cashier') ? 'selected' : ''; ?>>💰 Cashier</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Experience (years)</label>
                    <input type="number" name="experience_years" placeholder="0" min="0" value="<?php echo $edit_staff ? $edit_staff['experience_years'] : '0'; ?>">
                </div>
                <div class="form-group" style="display: flex; gap: 0.5rem; align-items: center;">
                    <?php if ($edit_id > 0): ?>
                        <button type="submit" name="save_staff" class="btn-save">💾 Update Staff</button>
                        <a href="staff.php" class="btn-cancel">Cancel</a>
                    <?php else: ?>
                        <button type="submit" name="save_staff" class="btn-save">➕ Add Staff</button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <div class="filter-bar">
        <form method="GET" style="display: flex; gap: 0.8rem; flex: 1; flex-wrap: wrap; align-items: center;">
            <input type="text" name="q" placeholder="🔍 Search staff..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="status">
                <option value="">All Status</option>
                <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo ($status_filter == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
            </select>
            <button type="submit" class="filter-btn">Filter</button>
            <a href="staff.php" class="clear-btn">Clear</a>
        </form>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Specialty</th>
                    <th>Experience</th>
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
                            <td><?php echo $staff['experience_years'] ?? 0; ?> yrs</td>
                            <td>
                                <span class="status-badge <?php echo $staff['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $staff['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="action-cell">
                                <a href="staff.php?edit=<?php echo $staff['id']; ?>" class="btn btn-edit">✏️ Edit</a>
                                <a href="staff.php?reset=<?php echo $staff['id']; ?>" class="btn btn-reset" onclick="return confirm('Reset password for <?php echo htmlspecialchars($staff['full_name']); ?>?')">🔑 Reset</a>
                                <a href="staff.php?toggle=<?php echo $staff['id']; ?>" class="btn btn-toggle <?php echo !$staff['is_active'] ? 'inactive' : ''; ?>" onclick="return confirm('Toggle status for <?php echo htmlspecialchars($staff['full_name']); ?>?')">
                                    <?php echo $staff['is_active'] ? '🔴' : '🟢'; ?>
                                </a>
                                <a href="staff.php?delete=<?php echo $staff['id']; ?>" class="btn btn-delete" onclick="return confirm('Delete <?php echo htmlspecialchars($staff['full_name']); ?>? This cannot be undone.')">🗑️ Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            <div class="empty-state">
                                <p>No staff members found. Add your first staff member above!</p>
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