<?php
// super_admin/admins.php - Manage all Salon Owners (Admins)
require_once '../config/database.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

// Get all salon owners (admins) with their salon info
$admins = mysqli_query($conn, "SELECT u.*, s.salon_name, s.subscription_plan, s.subscription_status
                                FROM users u 
                                JOIN salons s ON u.salon_id = s.id 
                                WHERE u.role = 'admin' 
                                ORDER BY u.created_at DESC");

// Handle Reset Password
if (isset($_GET['reset_password']) && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $new_password = password_hash('owner123', PASSWORD_DEFAULT);
    mysqli_query($conn, "UPDATE users SET password = '$new_password' WHERE id = $id AND role = 'admin'");
    redirect('admins.php?msg=password_reset');
}

// Handle Deactivate Admin
if (isset($_GET['deactivate']) && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['deactivate']);
    mysqli_query($conn, "UPDATE users SET is_active = 0 WHERE id = $id AND role = 'admin'");
    redirect('admins.php');
}

// Handle Activate Admin
if (isset($_GET['activate']) && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['activate']);
    mysqli_query($conn, "UPDATE users SET is_active = 1 WHERE id = $id AND role = 'admin'");
    redirect('admins.php');
}

include '../includes/header.php';
?>

<style>
    .super-container { display: flex; min-height: 100vh; }
    .sidebar { width: 280px; background: #050505; border-right: 2px solid #d4af37; padding: 2rem 1rem; }
    .sidebar-menu { list-style: none; padding: 0; }
    .sidebar-menu li { margin-bottom: 0.5rem; }
    .sidebar-menu a { display: block; padding: 12px 20px; color: white; text-decoration: none; border-radius: 10px; }
    .sidebar-menu a:hover, .sidebar-menu a.active { background: #d4af37; color: #050505; }
    .main-content { flex: 1; padding: 2rem; background: #0a0a0a; }
    
    .table-wrapper { overflow-x: auto; background: #1a1a1a; border-radius: 15px; padding: 0; border: 1px solid rgba(212, 175, 55, 0.2); }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid rgba(212, 175, 55, 0.15); }
    th { color: #d4af37; }
    
    .status-active { color: #28a745; font-weight: bold; }
    .status-inactive { color: #dc3545; font-weight: bold; }
    
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 1rem; }
    .alert-success { background: rgba(40, 167, 69, 0.2); border: 1px solid #28a745; color: #28a745; }
    
    h1 { color: #d4af37; margin-bottom: 2rem; }
    .action-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
    .btn-primary { background: #d4af37; color: #050505; border: none; padding: 5px 12px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.8rem; }
    .btn-warning { background: #d4af37; }
    .btn-danger { background: #dc3545; color: white; }
    .btn-success { background: #28a745; color: white; }
    
    @media (max-width: 768px) { .super-container { flex-direction: column; } .sidebar { width: 100%; } }
</style>

<div class="super-container">
    <aside class="sidebar">
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="salons.php">🏢 Manage Salons</a></li>
            <li><a href="admins.php" class="active">👨‍💼 Salon Owners</a></li>
            <li><a href="subscriptions.php">💰 Subscriptions</a></li>
            <li><a href="settings.php">⚙️ System Settings</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <h1>👨‍💼 Salon Owners</h1>
        
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
                        <td class="action-buttons">
                            <a href="?reset_password=1&id=<?php echo $admin['id']; ?>" class="btn-primary btn-warning" onclick="return confirm('Reset password for this owner? New password: owner123')">🔑 Reset Pass</a>
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
    </main>
</div>

<?php include '../includes/footer.php'; ?>