<?php
// admin/dashboard.php - UPDATED with upgrade banner
require_once '../config/database.php';

// Check if user is admin (salon owner)
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

// Get salon_id from session
$salon_id = $_SESSION['salon_id'] ?? 0;

if ($salon_id <= 0) {
    $user_id = $_SESSION['user_id'];
    $user_query = mysqli_query($conn, "SELECT salon_id FROM users WHERE id = $user_id");
    if ($user_result = mysqli_fetch_assoc($user_query)) {
        $salon_id = $user_result['salon_id'];
        $_SESSION['salon_id'] = $salon_id;
    }
}

// Get salon details for upgrade banner
$salon_query = mysqli_query($conn, "SELECT salon_name, subscription_plan FROM salons WHERE id = $salon_id");
$salon_data = mysqli_fetch_assoc($salon_query);
$current_plan = $salon_data['subscription_plan'] ?? 'basic';
$salon_name = $salon_data['salon_name'] ?? '';

// Check if upgrade is available
$upgrade_info = getUpgradeMessage($current_plan);

// Get statistics for THIS salon only
$total_customers = 0;
$total_appointments = 0;
$total_revenue = 0;
$pending_appointments = 0;

$customers_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'customer' AND salon_id = $salon_id");
if ($customers_query) {
    $total_customers = mysqli_fetch_assoc($customers_query)['count'];
}

$appointments_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments WHERE salon_id = $salon_id");
if ($appointments_query) {
    $total_appointments = mysqli_fetch_assoc($appointments_query)['count'];
}

$revenue_query = mysqli_query($conn, "SELECT SUM(amount) as total FROM payments WHERE payment_status = 'paid' AND salon_id = $salon_id");
if ($revenue_query) {
    $result = mysqli_fetch_assoc($revenue_query);
    $total_revenue = $result['total'] ?? 0;
}

$pending_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments WHERE (status = 'pending' OR status = 'confirmed') AND salon_id = $salon_id");
if ($pending_query) {
    $pending_appointments = mysqli_fetch_assoc($pending_query)['count'];
}

// Get today's appointments for THIS salon
$today = date('Y-m-d');
$today_appointments = mysqli_query($conn, "SELECT a.*, c.full_name as customer_name, s.service_name 
                                        FROM appointments a 
                                        JOIN users c ON a.customer_id = c.id 
                                        JOIN services s ON a.service_id = s.id 
                                        WHERE a.appointment_date = '$today' AND a.salon_id = $salon_id 
                                        ORDER BY a.appointment_time ASC");

// Get queue for THIS salon
$queue_query = mysqli_query($conn, "SELECT a.*, u.full_name as customer_name, s.service_name, st.full_name as staff_name 
                                    FROM appointments a 
                                    JOIN users u ON a.customer_id = u.id 
                                    JOIN services s ON a.service_id = s.id 
                                    LEFT JOIN users st ON a.staff_id = st.id 
                                    WHERE a.salon_id = $salon_id AND a.status NOT IN ('completed', 'cancelled', 'served') 
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
    .btn-serve:hover { background: #218838; }
    
    .table-container { overflow-x: auto; background: #1a1a1a; border-radius: 15px; padding: 1rem; margin-top: 1rem; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid rgba(212, 175, 55, 0.2); }
    th { color: #d4af37; }
    .btn-outline { display: inline-block; padding: 5px 10px; border: 1px solid #d4af37; color: #d4af37; text-decoration: none; border-radius: 5px; }
    
    h1, h3 { color: #d4af37; }
    
    /* Upgrade Banner Styles */
    .upgrade-banner {
        background: linear-gradient(135deg, #1a1a1a 0%, #2a1f0a 100%);
        border: 2px solid #d4af37;
        border-radius: 15px;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
        box-shadow: 0 4px 20px rgba(212, 175, 55, 0.15);
    }
    .upgrade-content {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        flex-wrap: wrap;
    }
    .upgrade-icon {
        font-size: 2.5rem;
    }
    .upgrade-text h4 {
        color: #d4af37;
        margin-bottom: 0.3rem;
    }
    .upgrade-text p {
        color: #aaa;
        font-size: 0.9rem;
        margin-bottom: 0.3rem;
    }
    .upgrade-text ul {
        list-style: none;
        padding: 0;
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .upgrade-text ul li {
        color: #aaa;
        font-size: 0.8rem;
    }
    .upgrade-text ul li:before {
        content: "✓ ";
        color: #28a745;
        font-weight: bold;
    }
    .btn-upgrade {
        background: #d4af37;
        color: #050505;
        padding: 12px 30px;
        border-radius: 50px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s;
        white-space: nowrap;
        border: none;
        cursor: pointer;
    }
    .btn-upgrade:hover {
        background: #f9e547;
        transform: scale(1.05);
        box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
    }
    .btn-upgrade:disabled {
        background: #555;
        color: #888;
        cursor: not-allowed;
        transform: none;
    }
    
    .plan-badge {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: bold;
    }
    .plan-basic { background: rgba(23, 162, 184, 0.2); color: #17a2b8; border: 1px solid #17a2b8; }
    .plan-premium { background: rgba(212, 175, 55, 0.2); color: #d4af37; border: 1px solid #d4af37; }
    .plan-enterprise { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }
    
    @media (max-width: 768px) { 
        .dashboard-container { flex-direction: column; } 
        .sidebar { width: 100%; }
        .upgrade-banner { flex-direction: column; text-align: center; }
        .upgrade-content { flex-direction: column; align-items: center; }
        .upgrade-text ul { justify-content: center; }
    }
</style>

<div class="dashboard-container">
    <aside class="sidebar">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h3 style="color: #d4af37;">👑 <?php echo htmlspecialchars($_SESSION['user_name']); ?></h3>
            <p>Salon Owner</p>
            <span class="plan-badge plan-<?php echo $current_plan; ?>">
                <?php echo ucfirst($current_plan); ?> Plan
            </span>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="active">📊 Dashboard</a></li>
            <li><a href="appointments.php">📅 Appointments</a></li>
            <li><a href="services.php">💇 Services</a></li>
            <li><a href="staff.php">👥 Staff</a></li>
            <li><a href="customers.php">👤 Customers</a></li>
            <?php if(hasFeature($salon_id, 'payments')): ?>
                <li><a href="payments.php">💰 Payments</a></li>
            <?php endif; ?>
            <?php if(hasFeature($salon_id, 'reports')): ?>
                <li><a href="reports.php">📈 Reports</a></li>
            <?php endif; ?>
            <?php if(hasFeature($salon_id, 'permissions')): ?>
                <li><a href="permissions.php">🔐 Permissions</a></li>
            <?php endif; ?>
            <li><a href="profile.php">⚙️ My Profile</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <h1>Admin Dashboard ✨</h1>
        
        <!-- Upgrade Banner -->
        <?php if($upgrade_info): ?>
        <div class="upgrade-banner">
            <div class="upgrade-content">
                <div class="upgrade-icon">💡</div>
                <div class="upgrade-text">
                    <h4><?php echo $upgrade_info['message']; ?></h4>
                    <p>Upgrade to unlock:</p>
                    <ul>
                        <?php foreach($upgrade_info['features'] as $feature): ?>
                            <li><?php echo $feature; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <a href="../super_admin/upgrade_plan.php?salon_id=<?php echo $salon_id; ?>&target=<?php echo $upgrade_info['target']; ?>" class="btn-upgrade">
                🔓 <?php echo $upgrade_info['button']; ?>
            </a>
        </div>
        <?php else: ?>
        <div class="upgrade-banner" style="border-color: #28a745;">
            <div class="upgrade-content">
                <div class="upgrade-icon">🏆</div>
                <div class="upgrade-text">
                    <h4>You're on the Enterprise plan!</h4>
                    <p>You have access to all features. Enjoy the full experience!</p>
                </div>
            </div>
            <button class="btn-upgrade" disabled>🌟 Max Plan</button>
        </div>
        <?php endif; ?>
        
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