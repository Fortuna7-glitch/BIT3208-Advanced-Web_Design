<?php
// super_admin/admins.php - UPDATED with new hamburger sidebar layout
require_once '../config/database.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

// Handle Reset Password
if (isset($_GET['reset_password']) && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $new_password = password_hash('owner123', PASSWORD_DEFAULT);
    mysqli_query($conn, "UPDATE users SET password = '$new_password' WHERE id = $id AND role = 'admin'");
    redirect('admins.php?msg=password_reset');
}

// Handle Deactivate
if (isset($_GET['deactivate']) && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['deactivate']);
    mysqli_query($conn, "UPDATE users SET is_active = 0 WHERE id = $id AND role = 'admin'");
    redirect('admins.php');
}

// Handle Activate
if (isset($_GET['activate']) && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['activate']);
    mysqli_query($conn, "UPDATE users SET is_active = 1 WHERE id = $id AND role = 'admin'");
    redirect('admins.php');
}

// Get all admins with salon info
$admins = mysqli_query($conn, "SELECT u.*, s.salon_name, s.subscription_plan, s.subscription_status
                                FROM users u 
                                JOIN salons s ON u.salon_id = s.id 
                                WHERE u.role = 'admin' 
                                ORDER BY u.created_at DESC");

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

    .table-wrapper {
        overflow-x: auto;
        background: #1a1a1a;
        border-radius: 15px;
        padding: 0;
        border: 1px solid rgba(212, 175, 55, 0.2);
        -webkit-overflow-scrolling: touch;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
        min-width: 800px;
    }
    th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid rgba(212, 175, 55, 0.15);
        white-space: nowrap;
    }
    th { color: #d4af37; font-weight: 600; }
    tr:hover { background: rgba(212, 175, 55, 0.05); }

    .btn-primary {
        background: #d4af37;
        color: #050505;
        border: none;
        padding: 5px 12px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.75rem;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
    }
    .btn-primary:hover {
        background: #f9e547;
        transform: scale(1.05);
    }

    .btn-warning {
        background: #d4af37;
        color: #050505;
        border: none;
        padding: 5px 12px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.75rem;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
    }
    .btn-warning:hover {
        background: #f9e547;
        transform: scale(1.05);
    }

    .btn-danger {
        background: #dc3545;
        color: white;
        border: none;
        padding: 5px 12px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.75rem;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
    }
    .btn-danger:hover {
        background: #c82333;
        transform: scale(1.05);
    }

    .btn-success {
        background: #28a745;
        color: white;
        border: none;
        padding: 5px 12px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.75rem;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
    }
    .btn-success:hover {
        background: #218838;
        transform: scale(1.05);
    }

    .status-active { color: #28a745; font-weight: bold; }
    .status-inactive { color: #dc3545; font-weight: bold; }

    .action-cell { display: flex; gap: 0.5rem; flex-wrap: wrap; }

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

    .back-link {
        display: inline-block;
        margin-top: 1.5rem;
        color: #d4af37;
        text-decoration: none;
    }
    .back-link:hover {
        text-decoration: underline;
    }

    /* ============================================
       RESPONSIVE
       ============================================ */
    @media (max-width: 1024px) {
        table { min-width: 650px; font-size: 0.85rem; }
        th, td { padding: 10px; }
    }

    @media (max-width: 768px) {
        .main-content { padding: 1rem; }
        .section-title { font-size: 1.1rem; }
        table { min-width: 500px; font-size: 0.8rem; }
        th, td { padding: 8px; white-space: nowrap; }
        .action-cell { flex-direction: column; }
        .action-cell .btn-primary,
        .action-cell .btn-danger,
        .action-cell .btn-success,
        .action-cell .btn-warning { width: 100%; text-align: center; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .section-title { font-size: 1rem; }
        table { min-width: 400px; font-size: 0.7rem; }
        th, td { padding: 6px; }
    }
</style>

<div class="main-content">

    <h1 class="section-title">👨‍💼 Salon Owners</h1>

    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'password_reset'): ?>
        <div class="alert alert-success">✅ Password reset to <strong>owner123</strong> successfully!</div>
    <?php endif; ?>

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
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($admin = mysqli_fetch_assoc($admins)): ?>
                <tr>
                    <td><?php echo $admin['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($admin['full_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($admin['email']); ?></td>
                    <td><?php echo htmlspecialchars($admin['phone']); ?></td>
                    <td><?php echo htmlspecialchars($admin['salon_name']); ?></td>
                    <td><?php echo ucfirst($admin['subscription_plan']); ?></td>
                    <td class="status-<?php echo $admin['is_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $admin['is_active'] ? '✅ Active' : '❌ Inactive'; ?>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></td>
                    <td class="action-cell">
                        <a href="?reset_password=1&id=<?php echo $admin['id']; ?>" class="btn-primary btn-warning" onclick="return confirm('Reset password for this owner? New password: owner123')">🔑 Reset</a>
                        <?php if($admin['is_active']): ?>
                            <a href="?deactivate=1&id=<?php echo $admin['id']; ?>" class="btn-primary btn-danger" onclick="return confirm('Deactivate this owner?')">⛔ Deactivate</a>
                        <?php else: ?>
                            <a href="?activate=1&id=<?php echo $admin['id']; ?>" class="btn-primary btn-success" onclick="return confirm('Activate this owner?')">✅ Activate</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<?php include '../includes/footer.php'; ?>