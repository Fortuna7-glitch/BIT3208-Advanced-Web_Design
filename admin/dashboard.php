<?php
// admin/dashboard.php - Add this near the top
require_once '../config/database.php';

// Check if this is a demo view from super admin
$is_demo = isset($_SESSION['demo_mode']) && $_SESSION['demo_mode'] === true;

// If it's a demo, allow access but show read-only banner
if (!$is_demo) {
    // Regular access control for normal admins
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'super_admin') {
        header("Location: ../super_admin/dashboard.php");
        exit();
    }
    
    if (!isLoggedIn() || !isAdmin()) {
        redirect('../auth/login.php');
    }
}

// Add a demo banner if in demo mode
if ($is_demo) {
    echo '<div style="background: #d4af37; color: #050505; text-align: center; padding: 10px; font-weight: bold;">
            🔍 DEMO MODE: You are viewing this as a Salon Owner would see it. 
            <a href="../super_admin/dashboard.php" style="color: #050505; text-decoration: underline;">Exit Demo</a>
        </div>';
}

// Get statistics
$total_customers = 0;
$total_appointments = 0;
$total_revenue = 0;
$pending_appointments = 0;

$customers_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'customer'");
if ($customers_query) {
    $total_customers = mysqli_fetch_assoc($customers_query)['count'];
}

$appointments_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments");
if ($appointments_query) {
    $total_appointments = mysqli_fetch_assoc($appointments_query)['count'];
}

$revenue_query = mysqli_query($conn, "SELECT SUM(amount) as total FROM payments WHERE payment_status = 'paid'");
if ($revenue_query) {
    $result = mysqli_fetch_assoc($revenue_query);
    $total_revenue = $result['total'] ?? 0;
}

$pending_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments WHERE status = 'pending' OR status = 'confirmed'");
if ($pending_query) {
    $pending_appointments = mysqli_fetch_assoc($pending_query)['count'];
}

// Get today's appointments
$today = date('Y-m-d');
$today_appointments = mysqli_query($conn, "SELECT a.*, c.full_name as customer_name, s.service_name 
                                        FROM appointments a 
                                        JOIN users c ON a.customer_id = c.id 
                                        JOIN services s ON a.service_id = s.id 
                                        WHERE a.appointment_date = '$today' 
                                        ORDER BY a.appointment_time ASC");

// Get queue
$queue_query = mysqli_query($conn, "SELECT a.*, u.full_name as customer_name, s.service_name, st.full_name as staff_name 
                                    FROM appointments a 
                                    JOIN users u ON a.customer_id = u.id 
                                    JOIN services s ON a.service_id = s.id 
                                    LEFT JOIN users st ON a.staff_id = st.id 
                                    WHERE a.status NOT IN ('completed', 'cancelled', 'served') 
                                    ORDER BY a.appointment_date ASC, a.appointment_time ASC, a.queue_position ASC");

include '../includes/header.php';
?>

<style>
    .dashboard-container { display: flex; min-height: 100vh; }
    .sidebar { width: 280px; background: #050505; border-right: 1px solid #d4af37; padding: 2rem 1rem; }
    .sidebar-menu { list-style: none; padding: 0; }
    .sidebar-menu li { margin-bottom: 0.5rem; }
    .sidebar-menu a { display: block; padding: 12px 20px; color: white; text-decoration: none; border-radius: 10px; transition: all 0.3s; }
    .sidebar-menu a:hover, .sidebar-menu a.active { background: #d4af37; color: #050505; }
    .main-content { flex: 1; padding: 2rem; background: #0a0a0a; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
    .stat-card { background: #1a1a1a; border-radius: 15px; padding: 1.5rem; text-align: center; border-left: 4px solid #d4af37; }
    .stat-number { font-size: 2.5rem; font-weight: bold; color: #d4af37; }
    .queue-container { background: #1a1a1a; border-radius: 15px; padding: 1.5rem; margin-bottom: 2rem; }
    .queue-item { background: #2a2a2a; border-radius: 10px; padding: 1rem; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
    .queue-number { background: #d4af37; color: #050505; width: 40px; height: 40px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 1rem; }
    .btn-serve { background: #28a745; color: white; border: none; padding: 8px 20px; border-radius: 25px; cursor: pointer; }
    .table-container { overflow-x: auto; background: #1a1a1a; border-radius: 15px; padding: 1rem; margin-top: 1rem; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid rgba(212, 175, 55, 0.2); }
    th { color: #d4af37; }
    .btn-outline { display: inline-block; padding: 5px 10px; border: 1px solid #d4af37; color: #d4af37; text-decoration: none; border-radius: 5px; }
    h1, h3 { color: #d4af37; }
    @media (max-width: 768px) { .dashboard-container { flex-direction: column; } .sidebar { width: 100%; } }
</style>

<div class="dashboard-container">
    <aside class="sidebar">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h3 style="color: #d4af37;">👑 <?php echo htmlspecialchars($_SESSION['user_name']); ?></h3>
            <p>Administrator</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="active">📊 Dashboard</a></li>
            <li><a href="appointments.php">📅 Appointments</a></li>
            <li><a href="services.php">💇 Services</a></li>
            <li><a href="staff.php">👥 Staff</a></li>
            <li><a href="customers.php">👤 Customers</a></li>
            <li><a href="payments.php">💰 Payments</a></li>
            <li><a href="reports.php">📈 Reports</a></li>
            <li><a href="permissions.php">🔐 Permissions</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <h1>Admin Dashboard ✨</h1>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-number"><?php echo $total_customers; ?></div><p>Total Customers</p></div>
            <div class="stat-card"><div class="stat-number"><?php echo $total_appointments; ?></div><p>Total Appointments</p></div>
            <div class="stat-card"><div class="stat-number">KSh <?php echo number_format($total_revenue, 2); ?></div><p>Total Revenue</p></div>
            <div class="stat-card"><div class="stat-number"><?php echo $pending_appointments; ?></div><p>Pending Appointments</p></div>
        </div>
        
        <div class="queue-container">
            <h3>🚀 Current Queue</h3>
            <?php if($queue_query && mysqli_num_rows($queue_query) > 0): ?>
                <?php while($q = mysqli_fetch_assoc($queue_query)): ?>
                <div class="queue-item">
                    <div>
                        <span class="queue-number">#<?php echo $q['queue_position']; ?></span>
                        <strong><?php echo htmlspecialchars($q['customer_name']); ?></strong><br>
                        <small><?php echo htmlspecialchars($q['service_name']); ?></small><br>
                        <small><?php echo date('M d, Y', strtotime($q['appointment_date'])); ?> at <?php echo date('g:i A', strtotime($q['appointment_time'])); ?></small>
                    </div>
                    <div>
                        <form method="POST" action="appointments.php" style="display: inline;">
                            <input type="hidden" name="appointment_id" value="<?php echo $q['id']; ?>">
                            <input type="hidden" name="action" value="serve">
                            <button type="submit" class="btn-serve" onclick="return confirm('Mark this customer as served?')">✓ Mark as Served</button>
                        </form>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No customers in queue.</p>
            <?php endif; ?>
        </div>
        
        <div>
            <h3>📅 Today's Appointments (<?php echo date('F d, Y'); ?>)</h3>
            <?php if($today_appointments && mysqli_num_rows($today_appointments) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead><tr><th>Customer</th><th>Service</th><th>Time</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php while($apt = mysqli_fetch_assoc($today_appointments)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($apt['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($apt['service_name']); ?></td>
                                <td><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></td>
                                <td><?php echo ucfirst($apt['status']); ?></td>
                                <td><a href="appointments.php" class="btn-outline">View</a></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No appointments scheduled for today.</p>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>