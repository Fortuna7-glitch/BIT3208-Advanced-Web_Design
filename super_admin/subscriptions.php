<?php
// super_admin/subscriptions.php - Manage salon subscriptions
require_once '../config/database.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

// Get all subscriptions with salon info
$subscriptions = mysqli_query($conn, "SELECT sh.*, s.salon_name 
                                    FROM subscription_history sh 
                                    JOIN salons s ON sh.salon_id = s.id 
                                    ORDER BY sh.payment_date DESC");

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
    h1 { color: #d4af37; margin-bottom: 2rem; }
    @media (max-width: 768px) { .super-container { flex-direction: column; } .sidebar { width: 100%; } }
</style>

<div class="super-container">
    <aside class="sidebar">
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="salons.php">🏢 Salons</a></li>
            <li><a href="admins.php">👨‍💼 Owners</a></li>
            <li><a href="subscriptions.php" class="active">💰 Subscriptions</a></li>
            <li><a href="settings.php">⚙️ Settings</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <h1>💰 Subscription History</h1>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Salon</th><th>Plan</th><th>Amount</th><th>Payment Method</th><th>Payment Date</th><th>Expiry Date</th></tr>
                </thead>
                <tbody>
                    <?php while($sub = mysqli_fetch_assoc($subscriptions)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($sub['salon_name']); ?></td>
                        <td><?php echo ucfirst($sub['plan']); ?></td>
                        <td>KSh <?php echo number_format($sub['amount'], 2); ?></td>
                        <td><?php echo strtoupper($sub['payment_method']); ?></td>
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