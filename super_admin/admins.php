<?php
// super_admin/admins.php - UPDATED with Expiry Date column
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
$admins = mysqli_query($conn, "SELECT u.*, s.salon_name, s.subscription_plan, s.subscription_status, s.subscription_expiry
                                FROM users u 
                                JOIN salons s ON u.salon_id = s.id 
                                WHERE u.role = 'admin' 
                                ORDER BY u.created_at DESC");

include '../includes/header.php';
?>

<style>
    .super-container { display: flex; min-height: 100vh; }
    .sidebar {
        width: 280px;
        background: #050505;
        border-right: 2px solid #d4af37;
        padding: 2rem 1rem;
        flex-shrink: 0;
        transition: all 0.3s ease;
        position: sticky;
        top: 70px;
        height: calc(100vh - 70px);
        overflow-y: auto;
    }
    .sidebar-menu { list-style: none; padding: 0; }
    .sidebar-menu li { margin-bottom: 0.5rem; }
    .sidebar-menu a {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        padding: 12px 20px;
        color: white;
        text-decoration: none;
        border-radius: 10px;
        transition: all 0.3s;
        font-size: 0.95rem;
    }
    .sidebar-menu a:hover, .sidebar-menu a.active {
        background: #d4af37;
        color: #050505;
    }
    
    .sidebar-toggle {
        display: none;
        background: #d4af37;
        color: #050505;
        border: none;
        padding: 10px 15px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 1rem;
        margin-bottom: 1rem;
        width: 100%;
    }
    .sidebar-toggle:hover { background: #f9e547; }

    .main-content { flex: 1; padding: 2rem; background: #0a0a0a; min-width: 0; }
    h1 { color: #d4af37; margin-bottom: 2rem; }

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
        min-width: 900px;
    }
    th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid rgba(212, 175, 55, 0.15);
        white-space: nowrap;
    }
    th { color: #d4af37; font-weight: 600; }
    tr:hover { background: rgba(212, 175, 55, 0.05); }

    .status-active { color: #28a745; font-weight: bold; }
    .status-inactive { color: #dc3545; font-weight: bold; }

    .alert { padding: 15px; border-radius: 8px; margin-bottom: 1rem; }
    .alert-success { background: rgba(40, 167, 69, 0.2); border: 1px solid #28a745; color: #28a745; }

    .btn-primary {
        background: #d4af37;
        color: #050505;
        border: none;
        padding: 5px 12px;
        border-radius: 5px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        font-size: 0.75rem;
        transition: all 0.3s;
    }
    .btn-primary:hover { background: #f9e547; }
    .btn-warning { background: #d4af37; }
    .btn-danger { background: #dc3545; color: white; }
    .btn-danger:hover { background: #c82333; }
    .btn-success { background: #28a745; color: white; }
    .btn-success:hover { background: #218838; }

    .action-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }

    .expiry-not-set { color: #888; font-style: italic; }
    .expiry-expired { color: #dc3545; font-weight: bold; }
    .expiry-soon { color: #d4af37; font-weight: bold; }
    .expiry-ok { color: #28a745; }

    /* ============================================
       RESPONSIVE
       ============================================ */
    @media (max-width: 1024px) {
        .sidebar { width: 240px; padding: 1.5rem 0.8rem; }
        table { min-width: 800px; font-size: 0.85rem; }
        th, td { padding: 10px; }
    }

    @media (max-width: 768px) {
        .super-container { flex-direction: column; }
        .sidebar {
            width: 100%;
            position: relative;
            top: 0;
            height: auto;
            border-right: none;
            border-bottom: 2px solid #d4af37;
            padding: 1rem;
            display: none;
        }
        .sidebar.open { display: block; }
        .sidebar-toggle { display: block; }

        .main-content { padding: 1rem; }
        h1 { font-size: 1.5rem; }

        table { min-width: 600px; font-size: 0.8rem; }
        th, td { padding: 8px; white-space: nowrap; }

        .action-buttons { flex-direction: column; }
        .action-buttons .btn-primary,
        .action-buttons .btn-danger,
        .action-buttons .btn-success { width: 100%; text-align: center; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        h1 { font-size: 1.2rem; }

        table { min-width: 500px; font-size: 0.7rem; }
        th, td { padding: 6px; }
    }
</style>

<div class="super-container">
    <aside class="sidebar" id="sidebar">
        <button class="sidebar-toggle" id="sidebarToggle">✕ Close Menu</button>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="salons.php">🏢 Salons</a></li>
            <li><a href="admins.php" class="active">👨‍💼 Owners</a></li>
            <li><a href="subscriptions.php">💰 Subscriptions</a></li>
            <li><a href="settings.php">⚙️ Settings</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <button class="sidebar-toggle" id="sidebarOpen" style="display:none; margin-bottom:1rem;">☰ Menu</button>

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
                        <th>Expiry</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($admin = mysqli_fetch_assoc($admins)): 
                        $expiry = $admin['subscription_expiry'];
                        $expiry_class = 'expiry-not-set';
                        $expiry_text = 'Not set';
                        if (!empty($expiry) && $expiry != '0000-00-00') {
                            $today = date('Y-m-d');
                            if ($expiry < $today) {
                                $expiry_class = 'expiry-expired';
                                $expiry_text = date('M d, Y', strtotime($expiry)) . ' ⚠️';
                            } elseif ($expiry <= date('Y-m-d', strtotime('+7 days'))) {
                                $expiry_class = 'expiry-soon';
                                $expiry_text = date('M d, Y', strtotime($expiry)) . ' ⚠️';
                            } else {
                                $expiry_class = 'expiry-ok';
                                $expiry_text = date('M d, Y', strtotime($expiry));
                            }
                        }
                    ?>
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
                        <td class="<?php echo $expiry_class; ?>"><?php echo $expiry_text; ?></td>
                        <td class="action-buttons">
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
    </main>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const sidebarOpen = document.getElementById('sidebarOpen');
        const sidebarToggle = document.getElementById('sidebarToggle');

        function isMobile() { return window.innerWidth <= 768; }

        function handleSidebar() {
            if (isMobile()) {
                sidebar.classList.remove('open');
                sidebarOpen.style.display = 'block';
                sidebarToggle.style.display = 'block';
            } else {
                sidebar.classList.add('open');
                sidebarOpen.style.display = 'none';
                sidebarToggle.style.display = 'none';
            }
        }

        if (sidebarOpen) {
            sidebarOpen.addEventListener('click', function() {
                sidebar.classList.add('open');
            });
        }
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.remove('open');
            });
        }

        document.addEventListener('click', function(event) {
            if (isMobile() && sidebar.classList.contains('open')) {
                if (!sidebar.contains(event.target) && event.target !== sidebarOpen) {
                    sidebar.classList.remove('open');
                }
            }
        });

        window.addEventListener('resize', handleSidebar);
        handleSidebar();
    });
</script>

<?php include '../includes/footer.php'; ?>