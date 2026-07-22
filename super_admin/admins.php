<?php
/**
 * Salon Pro — Super Admin: Manage Owners (Admins)
 * Luxury gold/black theme
 * Fixed top bar: Breadcrumb | Quick Actions | Search
 */

require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

$admin_name = $_SESSION['user_name'] ?? 'Super Admin';
$admin_email = $_SESSION['user_email'] ?? '';

$error = '';
$success = '';

// ============================================
// HANDLE ACTIONS
// ============================================

// Toggle admin status
if (isset($_GET['toggle'])) {
    $admin_id = (int)$_GET['toggle'];
    $query = "SELECT is_active FROM users WHERE id = $admin_id AND role = 'admin'";
    $result = mysqli_query($conn, $query);
    if ($row = mysqli_fetch_assoc($result)) {
        $new_status = $row['is_active'] ? 0 : 1;
        $update = "UPDATE users SET is_active = $new_status WHERE id = $admin_id";
        if (mysqli_query($conn, $update)) {
            $success = "Admin status updated successfully!";
        } else {
            $error = "Failed to update status: " . mysqli_error($conn);
        }
    }
}

// Reset admin password
if (isset($_GET['reset'])) {
    $admin_id = (int)$_GET['reset'];
    $new_password = 'admin123';
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $update = "UPDATE users SET password = '$hashed_password' WHERE id = $admin_id AND role = 'admin'";
    if (mysqli_query($conn, $update)) {
        $success = "Password reset successfully! New password: admin123";
    } else {
        $error = "Failed to reset password: " . mysqli_error($conn);
    }
}

// Delete admin
if (isset($_GET['delete'])) {
    $admin_id = (int)$_GET['delete'];
    // Prevent deleting self
    if ($admin_id == $_SESSION['user_id']) {
        $error = "You cannot delete your own account!";
    } else {
        $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments WHERE staff_id = $admin_id");
        $appointments = mysqli_fetch_assoc($check)['count'];
        if ($appointments > 0) {
            $error = "Cannot delete admin with $appointments appointments. Deactivate instead.";
        } else {
            $delete_query = "DELETE FROM users WHERE id = $admin_id AND role = 'admin'";
            if (mysqli_query($conn, $delete_query)) {
                $success = "Admin deleted successfully!";
            } else {
                $error = "Failed to delete admin: " . mysqli_error($conn);
            }
        }
    }
}

// Add new admin
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_admin'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $salon_id = !empty($_POST['salon_id']) ? (int)$_POST['salon_id'] : 'NULL';
    
    $check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");
    if (mysqli_num_rows($check) > 0) {
        $error = "Email already registered!";
    } else {
        $query = "INSERT INTO users (full_name, email, phone, password, role, is_active, salon_id) 
                  VALUES ('$full_name', '$email', '$phone', '$password', 'admin', 1, $salon_id)";
        if (mysqli_query($conn, $query)) {
            $success = "Admin added successfully!";
        } else {
            $error = "Failed to add admin: " . mysqli_error($conn);
        }
    }
}

// ============================================
// GET ALL ADMINS
// ============================================
$admins_query = "SELECT u.*, s.salon_name 
                 FROM users u 
                 LEFT JOIN salons s ON u.salon_id = s.id 
                 WHERE u.role = 'admin' 
                 ORDER BY u.full_name";
$admins_result = mysqli_query($conn, $admins_query);

// Get salons for dropdown
$salons_query = "SELECT id, salon_name FROM salons ORDER BY salon_name";
$salons_result = mysqli_query($conn, $salons_query);

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
        gap: 0.8rem;
        flex: 0 0 auto;
    }

    .top-bar-right .search-box {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: #0e0e0e;
        border: 1px solid rgba(212, 175, 55, 0.25);
        border-radius: 20px;
        padding: 0.35rem 1rem;
        color: #7a7568;
        font-size: 0.85rem;
        min-width: 180px;
        position: relative;
    }

    .top-bar-right .search-box input {
        background: none;
        border: none;
        outline: none;
        color: #f5f0e1;
        font-size: 0.85rem;
        flex: 1;
        width: 100px;
    }

    .top-bar-right .search-box input::placeholder {
        color: #7a7568;
    }

    .top-bar-right .search-box .search-icon {
        color: #d4af37;
        cursor: pointer;
    }

    .top-bar-right .icon-btn {
        position: relative;
        color: #f0d878;
        font-size: 1.2rem;
        cursor: pointer;
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
        align-items: flex-start;
        margin: 0.8rem 0 1.2rem 0;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .welcome-row h1 {
        font-size: 1.6rem;
        font-weight: 700;
        color: #f0d878;
        font-family: 'Playfair Display', serif;
    }
    .welcome-row .subtitle {
        font-size: 0.9rem;
        color: #7a7568;
        margin-top: 0.3rem;
    }
    .welcome-row .date-badge {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border: 1px solid rgba(212, 175, 55, 0.25);
        border-radius: 8px;
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
        color: #b8b2a0;
    }
    .welcome-row .date-badge i {
        color: #d4af37;
    }

    /* ============================================
       ADD ADMIN FORM
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

    .plan-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
    }

    .plan-badge.basic { background: rgba(23, 162, 184, 0.15); color: #17a2b8; border: 1px solid #17a2b8; }
    .plan-badge.premium { background: rgba(212, 175, 55, 0.15); color: #d4af37; border: 1px solid #d4af37; }
    .plan-badge.enterprise { background: rgba(40, 167, 69, 0.15); color: #28a745; border: 1px solid #28a745; }

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
        .top-bar-right .search-box { flex: 1; min-width: 120px; }
        .quick-links .qlink { font-size: 0.7rem; padding: 0.2rem 0.4rem; }
        .welcome-row { flex-direction: column; }
        .welcome-row h1 { font-size: 1.3rem; }
        .add-form .form-row { grid-template-columns: 1fr; }
        .add-form .btn-add { width: 100%; }
        table { min-width: 500px; font-size: 0.75rem; }
        th, td { padding: 6px; }
        .action-cell { flex-direction: column; }
        .action-cell .btn { width: 100%; text-align: center; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0 0.8rem 0.8rem; }
        .quick-links .qlink { font-size: 0.65rem; padding: 0.15rem 0.3rem; }
        table { min-width: 400px; font-size: 0.7rem; }
        th, td { padding: 4px; }
    }
</style>

<div class="main-content">

    <!-- ============================================
       STICKY HEADER
       ============================================ -->
    <div class="sticky-header">
        <div class="top-bar">
            <div class="top-bar-left">
                <div class="breadcrumb">
                    <i class="ti ti-menu-2 menu-icon"></i>
                    <span class="current">Dashboard</span>
                    <span class="sep">/</span>
                    <span class="sub">Manage Owners</span>
                </div>
            </div>

            <div class="top-bar-center">
                <div class="quick-links">
                    <a href="salons.php" class="qlink"><i class="ti ti-building-store"></i> Manage Salons</a>
                    <span class="link-sep">|</span>
                    <a href="admins.php" class="qlink active"><i class="ti ti-user-shield"></i> Manage Owners</a>
                    <span class="link-sep">|</span>
                    <a href="subscriptions.php" class="qlink"><i class="ti ti-crown"></i> Subscriptions</a>
                    <span class="link-sep">|</span>
                    <a href="settings.php" class="qlink"><i class="ti ti-settings"></i> System Settings</a>
                    <span class="link-sep">|</span>
                    <a href="products.php" class="qlink"><i class="ti ti-box"></i> Products</a>
                </div>
            </div>

            <div class="top-bar-right">
                <div class="search-box">
                    <i class="ti ti-search search-icon"></i>
                    <input type="text" id="globalSearch" placeholder="Search owners...">
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
            <h1>🛡️ Salon Owners</h1>
            <p class="subtitle">Manage all salon administrators and their accounts</p>
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
       ADD ADMIN FORM
       ============================================ -->
    <div class="add-form">
        <h3>➕ Add New Salon Owner</h3>
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
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <div class="form-group">
                    <label>Assign to Salon</label>
                    <select name="salon_id">
                        <option value="">-- None --</option>
                        <?php while($salon = mysqli_fetch_assoc($salons_result)): ?>
                            <option value="<?php echo $salon['id']; ?>">
                                <?php echo htmlspecialchars($salon['salon_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" name="add_admin" class="btn-add">➕ Add Owner</button>
                </div>
            </div>
        </form>
    </div>

    <!-- ============================================
       ADMINS TABLE
       ============================================ -->
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Owner Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Salon</th>
                    <th>Plan</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Expiry</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($admins_result) > 0): ?>
                    <?php while($admin = mysqli_fetch_assoc($admins_result)): 
                        // Get salon plan if salon exists
                        $plan = 'N/A';
                        $expiry = 'Not set';
                        if ($admin['salon_id']) {
                            $plan_query = "SELECT subscription_plan, subscription_expiry FROM salons WHERE id = {$admin['salon_id']}";
                            $plan_result = mysqli_query($conn, $plan_query);
                            if ($plan_data = mysqli_fetch_assoc($plan_result)) {
                                $plan = ucfirst($plan_data['subscription_plan'] ?? 'Basic');
                                $expiry = $plan_data['subscription_expiry'] ? date('M d, Y', strtotime($plan_data['subscription_expiry'])) : 'Not set';
                            }
                        }
                    ?>
                        <tr>
                            <td><?php echo $admin['id']; ?></td>
                            <td><?php echo htmlspecialchars($admin['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($admin['email']); ?></td>
                            <td><?php echo htmlspecialchars($admin['phone']); ?></td>
                            <td><?php echo htmlspecialchars($admin['salon_name'] ?? 'Unassigned'); ?></td>
                            <td><span class="plan-badge <?php echo strtolower($plan); ?>"><?php echo $plan; ?></span></td>
                            <td>
                                <span class="status-badge <?php echo $admin['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $admin['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></td>
                            <td><?php echo $expiry; ?></td>
                            <td class="action-cell">
                                <a href="admins.php?reset=<?php echo $admin['id']; ?>" class="btn btn-reset" onclick="return confirm('Reset password for <?php echo htmlspecialchars($admin['full_name']); ?>?')">🔑 Reset</a>
                                <a href="admins.php?toggle=<?php echo $admin['id']; ?>" class="btn btn-toggle <?php echo !$admin['is_active'] ? 'inactive' : ''; ?>" onclick="return confirm('Toggle status for <?php echo htmlspecialchars($admin['full_name']); ?>?')">
                                    <?php echo $admin['is_active'] ? '🔴' : '🟢'; ?>
                                </a>
                                <?php if($admin['id'] != $_SESSION['user_id']): ?>
                                    <a href="admins.php?delete=<?php echo $admin['id']; ?>" class="btn btn-delete" onclick="return confirm('Delete <?php echo htmlspecialchars($admin['full_name']); ?>? This cannot be undone.')">🗑️</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 40px;">
                            <div class="empty-state">
                                <p>No salon owners found. Add your first owner above!</p>
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
                window.location.href = 'search.php?q=' + encodeURIComponent(query);
            }
        }
    });
</script>

<?php include '../includes/footer.php'; ?>