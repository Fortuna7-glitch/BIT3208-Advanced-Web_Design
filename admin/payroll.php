<?php
/**
 * Salon Pro — Admin: Payroll & Salary Management
 * Luxury gold/black theme
 * Admin can manage staff salaries and track payments
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

// ============================================
// HANDLE ACTIONS
// ============================================

// Add or Update Salary
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_salary'])) {
    $staff_id = (int)$_POST['staff_id'];
    $salary_amount = (float)$_POST['salary_amount'];
    $salary_period = mysqli_real_escape_string($conn, $_POST['salary_period']);
    $payment_date = mysqli_real_escape_string($conn, $_POST['payment_date']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $transaction_code = mysqli_real_escape_string($conn, $_POST['transaction_code']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    $salary_id = isset($_POST['salary_id']) ? (int)$_POST['salary_id'] : 0;

    // Verify staff belongs to this salon
    $check_query = "SELECT id FROM users WHERE id = $staff_id AND role = 'staff' AND salon_id = $salon_id";
    $check_result = mysqli_query($conn, $check_query);
    if (mysqli_num_rows($check_result) == 0) {
        $error = "Staff member not found in your salon.";
    } else {
        if ($salary_id > 0) {
            // UPDATE existing salary
            $update_query = "UPDATE staff_salaries SET 
                            salary_amount = '$salary_amount',
                            salary_period = '$salary_period',
                            payment_date = '$payment_date',
                            status = '$status',
                            transaction_code = '$transaction_code',
                            notes = '$notes'
                            WHERE id = $salary_id";
            if (mysqli_query($conn, $update_query)) {
                logAudit('salary_updated', 'payroll', "Updated salary for staff ID $staff_id (Amount: $salary_amount)", $admin_id);
                $success = "Salary updated successfully!";
                $edit_id = 0;
            } else {
                $error = "Failed to update salary: " . mysqli_error($conn);
            }
        } else {
            // INSERT new salary
            $insert_query = "INSERT INTO staff_salaries (staff_id, salary_amount, salary_period, payment_date, status, transaction_code, notes) 
                            VALUES ($staff_id, '$salary_amount', '$salary_period', '$payment_date', '$status', '$transaction_code', '$notes')";
            if (mysqli_query($conn, $insert_query)) {
                logAudit('salary_created', 'payroll', "Created salary for staff ID $staff_id (Amount: $salary_amount)", $admin_id);
                $success = "Salary added successfully!";
            } else {
                $error = "Failed to add salary: " . mysqli_error($conn);
            }
        }
    }
}

// Delete Salary
if (isset($_GET['delete'])) {
    $salary_id = (int)$_GET['delete'];
    $query = "DELETE FROM staff_salaries WHERE id = $salary_id";
    if (mysqli_query($conn, $query)) {
        logAudit('salary_deleted', 'payroll', "Deleted salary record ID $salary_id", $admin_id);
        $success = "Salary record deleted!";
    } else {
        $error = "Failed to delete record: " . mysqli_error($conn);
    }
}

// Mark as Paid
if (isset($_GET['mark_paid'])) {
    $salary_id = (int)$_GET['mark_paid'];
    $update_query = "UPDATE staff_salaries SET status = 'paid' WHERE id = $salary_id";
    if (mysqli_query($conn, $update_query)) {
        logAudit('salary_marked_paid', 'payroll', "Marked salary ID $salary_id as paid", $admin_id);
        $success = "Salary marked as paid!";
    } else {
        $error = "Failed to update: " . mysqli_error($conn);
    }
}

// Mark as Unpaid
if (isset($_GET['mark_unpaid'])) {
    $salary_id = (int)$_GET['mark_unpaid'];
    $update_query = "UPDATE staff_salaries SET status = 'unpaid' WHERE id = $salary_id";
    if (mysqli_query($conn, $update_query)) {
        logAudit('salary_marked_unpaid', 'payroll', "Marked salary ID $salary_id as unpaid", $admin_id);
        $success = "Salary marked as unpaid!";
    } else {
        $error = "Failed to update: " . mysqli_error($conn);
    }
}

// Get edit data
$edit_salary = null;
if ($edit_id > 0) {
    $edit_query = "SELECT * FROM staff_salaries WHERE id = $edit_id";
    $edit_result = mysqli_query($conn, $edit_query);
    $edit_salary = mysqli_fetch_assoc($edit_result);
    if (!$edit_salary) {
        $edit_id = 0;
        $error = "Salary record not found.";
    }
}

// ============================================
// GET DATA
// ============================================

// Get staff list for dropdown
$staff_query = "SELECT id, full_name FROM users WHERE role = 'staff' AND salon_id = $salon_id AND is_active = 1 ORDER BY full_name";
$staff_result = mysqli_query($conn, $staff_query);

// Get salary records with staff names
$salaries_query = "SELECT s.*, u.full_name as staff_name 
                   FROM staff_salaries s 
                   JOIN users u ON s.staff_id = u.id 
                   WHERE u.salon_id = $salon_id 
                   ORDER BY s.payment_date DESC";
$salaries_result = mysqli_query($conn, $salaries_query);

// Get total payroll summary
$summary_query = "SELECT 
    SUM(CASE WHEN status = 'paid' THEN salary_amount ELSE 0 END) as total_paid,
    SUM(CASE WHEN status = 'unpaid' THEN salary_amount ELSE 0 END) as total_unpaid,
    COUNT(*) as total_records
    FROM staff_salaries s 
    JOIN users u ON s.staff_id = u.id 
    WHERE u.salon_id = $salon_id";
$summary_result = mysqli_query($conn, $summary_query);
$summary = mysqli_fetch_assoc($summary_result);

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
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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
        font-size: 2rem;
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
    .stat-card.blue { border-left-color: #17a2b8; }
    .stat-card.blue .stat-number { color: #17a2b8; }

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
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
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
        min-width: 800px;
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

    .status-badge.paid {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border: 1px solid #28a745;
    }

    .status-badge.unpaid {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
        border: 1px solid #dc3545;
    }

    .status-badge.pending {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid #d4af37;
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

    .btn-paid {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border: 1px solid #28a745;
    }

    .btn-paid:hover {
        background: #28a745;
        color: white;
    }

    .btn-unpaid {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
        border: 1px solid #dc3545;
    }

    .btn-unpaid:hover {
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

    .period-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        background: rgba(108, 117, 125, 0.15);
        color: #adb5bd;
        border: 1px solid #adb5bd;
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
                    <span class="sub">Payroll Management</span>
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
                    <a href="payroll.php" class="qlink active"><i class="ti ti-coin"></i> Payroll</a>
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
            <h1>💰 Payroll Management</h1>
            <p class="subtitle">Track staff salaries and payments</p>
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
        <div class="stat-card green">
            <span class="stat-icon">✅</span>
            <div class="stat-number">KSh <?php echo number_format($summary['total_paid'] ?? 0, 2); ?></div>
            <div class="stat-label">Total Paid</div>
        </div>
        <div class="stat-card red">
            <span class="stat-icon">⏳</span>
            <div class="stat-number">KSh <?php echo number_format($summary['total_unpaid'] ?? 0, 2); ?></div>
            <div class="stat-label">Total Unpaid</div>
        </div>
        <div class="stat-card blue">
            <span class="stat-icon">📊</span>
            <div class="stat-number"><?php echo $summary['total_records'] ?? 0; ?></div>
            <div class="stat-label">Total Records</div>
        </div>
    </div>

    <div class="add-form">
        <h3><?php echo $edit_id > 0 ? '✏️ Edit Salary Record' : '➕ Add New Salary Record'; ?></h3>
        <form method="POST">
            <?php if ($edit_id > 0): ?>
                <input type="hidden" name="salary_id" value="<?php echo $edit_id; ?>">
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Staff Member</label>
                    <select name="staff_id" required>
                        <option value="">-- Select Staff --</option>
                        <?php while($staff = mysqli_fetch_assoc($staff_result)): ?>
                            <option value="<?php echo $staff['id']; ?>" <?php echo ($edit_salary && $edit_salary['staff_id'] == $staff['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($staff['full_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Salary Amount (KSh)</label>
                    <input type="number" name="salary_amount" step="0.01" placeholder="0.00" value="<?php echo $edit_salary ? $edit_salary['salary_amount'] : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Period</label>
                    <select name="salary_period">
                        <option value="weekly" <?php echo ($edit_salary && $edit_salary['salary_period'] == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                        <option value="monthly" <?php echo ($edit_salary && $edit_salary['salary_period'] == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Payment Date</label>
                    <input type="date" name="payment_date" value="<?php echo $edit_salary ? $edit_salary['payment_date'] : date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="pending" <?php echo ($edit_salary && $edit_salary['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="unpaid" <?php echo ($edit_salary && $edit_salary['status'] == 'unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                        <option value="paid" <?php echo ($edit_salary && $edit_salary['status'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Transaction Code</label>
                    <input type="text" name="transaction_code" placeholder="TX-001" value="<?php echo $edit_salary ? htmlspecialchars($edit_salary['transaction_code']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" placeholder="Optional notes"><?php echo $edit_salary ? htmlspecialchars($edit_salary['notes']) : ''; ?></textarea>
                </div>
                <div class="form-group" style="display: flex; gap: 0.5rem; align-items: center;">
                    <?php if ($edit_id > 0): ?>
                        <button type="submit" name="save_salary" class="btn-save">💾 Update</button>
                        <a href="payroll.php" class="btn-cancel">Cancel</a>
                    <?php else: ?>
                        <button type="submit" name="save_salary" class="btn-save">➕ Add Record</button>
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
                    <th>Staff</th>
                    <th>Amount</th>
                    <th>Period</th>
                    <th>Payment Date</th>
                    <th>Status</th>
                    <th>Transaction</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($salaries_result) > 0): ?>
                    <?php while($salary = mysqli_fetch_assoc($salaries_result)): ?>
                        <tr>
                            <td><?php echo $salary['id']; ?></td>
                            <td><?php echo htmlspecialchars($salary['staff_name']); ?></td>
                            <td>KSh <?php echo number_format($salary['salary_amount'], 2); ?></td>
                            <td><span class="period-badge"><?php echo ucfirst($salary['salary_period']); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($salary['payment_date'])); ?></td>
                            <td>
                                <span class="status-badge <?php echo $salary['status']; ?>">
                                    <?php echo ucfirst($salary['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($salary['transaction_code'] ?? '-'); ?></td>
                            <td class="action-cell">
                                <a href="payroll.php?edit=<?php echo $salary['id']; ?>" class="btn btn-edit">✏️ Edit</a>
                                <?php if ($salary['status'] != 'paid'): ?>
                                    <a href="payroll.php?mark_paid=<?php echo $salary['id']; ?>" class="btn btn-paid" onclick="return confirm('Mark this as paid?')">✅ Paid</a>
                                <?php else: ?>
                                    <a href="payroll.php?mark_unpaid=<?php echo $salary['id']; ?>" class="btn btn-unpaid" onclick="return confirm('Mark this as unpaid?')">↩️ Unpaid</a>
                                <?php endif; ?>
                                <a href="payroll.php?delete=<?php echo $salary['id']; ?>" class="btn btn-delete" onclick="return confirm('Delete this record?')">🗑️</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            <div class="empty-state">
                                <p>No salary records found. Add your first record above!</p>
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