<?php
// super_admin/dashboard.php - Fortuna's Super Admin Panel
require_once '../config/database.php';

// Only Super Admin can access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

// Get statistics
$total_salons = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM salons"))['count'];
$active_salons = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM salons WHERE subscription_status = 'active'"))['count'];
$total_admins = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'admin'"))['count'];
$total_staff = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'staff'"))['count'];
$total_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'customer'"))['count'];
$total_revenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM subscription_history"))['total'] ?? 0;

// Get recent subscriptions
$recent_subs = mysqli_query($conn, "SELECT sh.*, s.salon_name 
                                    FROM subscription_history sh 
                                    JOIN salons s ON sh.salon_id = s.id 
                                    ORDER BY sh.payment_date DESC LIMIT 5");

// Get all salons
$salons = mysqli_query($conn, "SELECT * FROM salons ORDER BY created_at DESC");

include '../includes/header.php';
?>

<style>
    .super-container { display: flex; min-height: 100vh; }
    .sidebar { width: 280px; background: #050505; border-right: 2px solid #d4af37; padding: 2rem 1rem; }
    .sidebar-menu { list-style: none; padding: 0; }
    .sidebar-menu li { margin-bottom: 0.5rem; }
    .sidebar-menu a { display: block; padding: 12px 20px; color: white; text-decoration: none; border-radius: 10px; transition: all 0.3s; }
    .sidebar-menu a:hover, .sidebar-menu a.active { background: #d4af37; color: #050505; }
    .main-content { flex: 1; padding: 2rem; background: #0a0a0a; }
    
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
    .stat-card { background: #1a1a1a; border-radius: 15px; padding: 1.5rem; text-align: center; border-left: 4px solid #d4af37; }
    .stat-number { font-size: 2rem; font-weight: bold; color: #d4af37; }
    
    .table-wrapper { overflow-x: auto; background: #1a1a1a; border-radius: 15px; padding: 0; margin-top: 1rem; border: 1px solid rgba(212, 175, 55, 0.2); }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid rgba(212, 175, 55, 0.15); }
    th { color: #d4af37; }
    
    .status-active { color: #28a745; font-weight: bold; }
    .status-inactive { color: #dc3545; font-weight: bold; }
    .status-suspended { color: #d4af37; font-weight: bold; }
    
    .salon-card { background: #1a1a1a; border-radius: 15px; padding: 1.5rem; margin-bottom: 1rem; border: 1px solid rgba(212, 175, 55, 0.3); transition: all 0.3s; }
    .salon-card:hover { border-color: #d4af37; transform: translateY(-2px); }
    .salon-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 1rem; }
    .salon-name { font-size: 1.2rem; font-weight: bold; color: #d4af37; }
    .subscription-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.7rem; }
    .badge-premium { background: rgba(212, 175, 55, 0.2); color: #d4af37; border: 1px solid #d4af37; }
    .badge-basic { background: rgba(23, 162, 184, 0.2); color: #17a2b8; border: 1px solid #17a2b8; }
    .badge-enterprise { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }
    
    h1, h2 { color: #d4af37; margin-bottom: 1rem; }
    .btn-primary { background: #d4af37; color: #050505; border: none; padding: 8px 20px; border-radius: 25px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.85rem; }
    .btn-danger { background: #dc3545; color: white; }
    
    @media (max-width: 768px) { .super-container { flex-direction: column; } .sidebar { width: 100%; } }
</style>

<div class="super-container">
    <aside class="sidebar">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h3 style="color: #d4af37;">👑 FORTUNA</h3>
            <p>Super Administrator</p>
            <small style="color: #888;">System Owner</small>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="active">📊 Dashboard</a></li>
            <li><a href="salons.php">🏢 Manage Salons</a></li>
            <li><a href="admins.php">👨‍💼 Salon Owners</a></li>
            <li><a href="subscriptions.php">💰 Subscriptions</a></li>
            <li><a href="settings.php">⚙️ System Settings</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <h1>👑 Super Admin Dashboard</h1>
        <p>Welcome back, <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong>! You have full control over all salons and owners.</p>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-number"><?php echo $total_salons; ?></div><p>Total Salons</p></div>
            <div class="stat-card"><div class="stat-number"><?php echo $active_salons; ?></div><p>Active Salons</p></div>
            <div class="stat-card"><div class="stat-number"><?php echo $total_admins; ?></div><p>Salon Owners</p></div>
            <div class="stat-card"><div class="stat-number"><?php echo $total_staff; ?></div><p>Total Staff</p></div>
            <div class="stat-card"><div class="stat-number"><?php echo $total_customers; ?></div><p>Total Customers</p></div>
            <div class="stat-card"><div class="stat-number">KSh <?php echo number_format($total_revenue, 2); ?></div><p>Revenue</p></div>
        </div>
        
        <h2>🏢 All Salons</h2>
        <?php while($salon = mysqli_fetch_assoc($salons)): ?>
        <div class="salon-card">
            <div class="salon-header">
                <div>
                    <span class="salon-name">🏢 <?php echo htmlspecialchars($salon['salon_name']); ?></span>
                    <span class="subscription-badge badge-<?php echo $salon['subscription_plan']; ?>">
                        <?php echo ucfirst($salon['subscription_plan']); ?>
                    </span>
                    <span class="status-<?php echo $salon['subscription_status']; ?>">
                        <?php echo ucfirst($salon['subscription_status']); ?>
                    </span>
                </div>
                <div>
                    <a href="view_salon.php?id=<?php echo $salon['id']; ?>" class="btn-primary">👁️ View Details</a>
                    <a href="edit_salon.php?id=<?php echo $salon['id']; ?>" class="btn-primary">✏️ Edit</a>
                </div>
            </div>
            <div style="display: flex; gap: 2rem; flex-wrap: wrap; margin-top: 0.5rem;">
                <div>📧 <?php echo htmlspecialchars($salon['salon_email']); ?></div>
                <div>📞 <?php echo htmlspecialchars($salon['salon_phone']); ?></div>
                <div>📍 <?php echo htmlspecialchars($salon['salon_address']); ?></div>
                <div>📅 Since: <?php echo date('M d, Y', strtotime($salon['created_at'])); ?></div>
            </div>
        </div>
        <?php endwhile; ?>
        
        <h2 style="margin-top: 2rem;">💰 Recent Subscriptions</h2>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Salon</th><th>Plan</th><th>Amount</th><th>Payment Date</th><th>Expiry</th></tr>
                </thead>
                <tbody>
                    <?php while($sub = mysqli_fetch_assoc($recent_subs)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($sub['salon_name']); ?></td>
                        <td><?php echo ucfirst($sub['plan']); ?></td>
                        <td>KSh <?php echo number_format($sub['amount'], 2); ?></td>
                        <td><?php echo date('M d, Y', strtotime($sub['payment_date'])); ?></td>
                        <td><?php echo date('M d, Y', strtotime($sub['expiry_date'])); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>