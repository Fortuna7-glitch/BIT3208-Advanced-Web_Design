<?php
// admin/staff_permissions.php - BULK PERMISSION ASSIGNMENT
require_once '../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$error = '';
$success = '';
$selected_permission = isset($_GET['permission']) ? $_GET['permission'] : '';
$selected_role = isset($_GET['role']) ? $_GET['role'] : '';

// Get all permissions for dropdown
$permissions_result = mysqli_query($conn, "SELECT * FROM permissions ORDER BY permission_name");

// Get all staff members
$staff_query = "SELECT id, full_name, email, phone FROM users WHERE role = 'staff' AND is_active = 1 ORDER BY full_name";
$staff_result = mysqli_query($conn, $staff_query);

// Handle bulk assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_assign'])) {
    $permission_id = (int)$_POST['permission_id'];
    $staff_ids = isset($_POST['staff_ids']) ? $_POST['staff_ids'] : [];
    $admin_id = $_SESSION['user_id'];
    
    if (empty($staff_ids)) {
        $error = "Please select at least one staff member.";
    } else {
        $assigned = 0;
        foreach ($staff_ids as $staff_id) {
            $staff_id = (int)$staff_id;
            if (grantPermission($staff_id, $permission_id, $admin_id)) {
                $assigned++;
            }
        }
        $success = "Permission assigned to $assigned staff member(s) successfully!";
    }
}

// Handle bulk revoke
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_revoke'])) {
    $permission_id = (int)$_POST['permission_id'];
    $staff_ids = isset($_POST['staff_ids']) ? $_POST['staff_ids'] : [];
    $admin_id = $_SESSION['user_id'];
    
    if (empty($staff_ids)) {
        $error = "Please select at least one staff member.";
    } else {
        $revoked = 0;
        foreach ($staff_ids as $staff_id) {
            $staff_id = (int)$staff_id;
            if (revokePermission($staff_id, $permission_id, $admin_id)) {
                $revoked++;
            }
        }
        $success = "Permission revoked from $revoked staff member(s) successfully!";
    }
}

include '../includes/header.php';
?>

<style>
    .main-content {
        padding: 2rem;
        background: #0a0a0a;
        min-height: 100vh;
    }

    .section-title {
        color: #d4af37;
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
        border-left: 3px solid #d4af37;
        padding-left: 1rem;
    }

    .bulk-container {
        max-width: 800px;
        margin: 0 auto;
    }

    .card {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        border: 1px solid rgba(212, 175, 55, 0.2);
        margin-bottom: 1.5rem;
    }

    .card h3 {
        color: #d4af37;
        margin-bottom: 1rem;
        font-size: 1rem;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-group label {
        display: block;
        color: #d4af37;
        margin-bottom: 0.3rem;
        font-size: 0.9rem;
    }

    .form-control {
        width: 100%;
        padding: 10px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 8px;
        color: white;
    }

    .form-control:focus {
        outline: none;
        border-color: #d4af37;
    }

    select.form-control {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23d4af37' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        padding-right: 35px;
    }

    select.form-control[multiple] {
        background-image: none;
        min-height: 150px;
    }

    select.form-control[multiple] option {
        padding: 8px 12px;
        border-radius: 4px;
        cursor: pointer;
    }

    select.form-control[multiple] option:checked {
        background: rgba(212, 175, 55, 0.3);
    }

    .btn-primary {
        padding: 10px 30px;
        background: #d4af37;
        color: #050505;
        border: none;
        border-radius: 25px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }

    .btn-primary:hover {
        background: #f9e547;
        transform: translateY(-2px);
    }

    .btn-danger {
        padding: 10px 30px;
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 25px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }

    .btn-danger:hover {
        background: #c82333;
        transform: translateY(-2px);
    }

    .btn-group {
        display: flex;
        gap: 1rem;
        margin-top: 1rem;
        flex-wrap: wrap;
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

    .staff-count {
        color: #888;
        font-size: 0.8rem;
    }

    .back-link {
        display: inline-block;
        margin-top: 1.5rem;
        color: #d4af37;
        text-decoration: none;
    }

    .back-link:hover {
        text-decoration: underline;
    }

    @media (max-width: 768px) {
        .main-content { padding: 1rem; }
        .section-title { font-size: 1.1rem; }
        .card { padding: 1rem; }
        .btn-group { flex-direction: column; }
        .btn-group .btn-primary,
        .btn-group .btn-danger { width: 100%; text-align: center; }
    }
</style>

<div class="main-content">
    <div class="bulk-container">
        <h1 class="section-title">👥 Bulk Permission Assignment</h1>

        <?php if($error): ?>
            <div class="alert alert-danger">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>

        <div class="card">
            <h3>📋 Assign Permission to Multiple Staff</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Select Permission</label>
                    <select name="permission_id" class="form-control" required>
                        <option value="">-- Choose a permission --</option>
                        <?php while($perm = mysqli_fetch_assoc($permissions_result)): ?>
                            <option value="<?php echo $perm['id']; ?>" <?php echo ($selected_permission == $perm['permission_name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($perm['description']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Select Staff Members (Hold Ctrl/Cmd to select multiple)</label>
                    <select name="staff_ids[]" class="form-control" multiple required>
                        <?php while($staff = mysqli_fetch_assoc($staff_result)): ?>
                            <option value="<?php echo $staff['id']; ?>">
                                <?php echo htmlspecialchars($staff['full_name']); ?> (<?php echo htmlspecialchars($staff['email']); ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <div class="staff-count">
                        <?php $total_staff = mysqli_num_rows($staff_result); ?>
                        Total staff: <?php echo $total_staff; ?>
                    </div>
                </div>

                <div class="btn-group">
                    <button type="submit" name="bulk_assign" class="btn-primary">✅ Grant Permission</button>
                    <button type="submit" name="bulk_revoke" class="btn-danger">❌ Revoke Permission</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h3>💡 Quick Tips</h3>
            <ul style="color: #aaa; list-style: none; padding: 0;">
                <li style="margin-bottom: 0.5rem;">• Hold <strong>Ctrl</strong> (Windows) or <strong>Cmd</strong> (Mac) to select multiple staff members</li>
                <li style="margin-bottom: 0.5rem;">• Use <strong>Grant Permission</strong> to give the selected permission to all chosen staff</li>
                <li style="margin-bottom: 0.5rem;">• Use <strong>Revoke Permission</strong> to remove the selected permission from all chosen staff</li>
                <li style="margin-bottom: 0.5rem;">• For individual permission management, go to the <a href="permissions.php" style="color: #d4af37;">Permissions</a> page</li>
            </ul>
        </div>

        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>