<?php
// admin/reports.php - UPDATED with feature access check
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

// ============================================
// FEATURE ACCESS CHECK
// ============================================
if (!hasFeature($salon_id, 'reports')) {
    // Redirect to dashboard with upgrade message
    $_SESSION['upgrade_required'] = 'reports';
    redirect('dashboard.php');
}

// Get date filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Get report data for THIS salon only
$revenue_query = "SELECT DATE(p.payment_date) as date, SUM(p.amount) as daily_revenue, COUNT(*) as transaction_count 
                  FROM payments p
                  JOIN appointments a ON p.appointment_id = a.id 
                  WHERE p.payment_status = 'paid' 
                  AND a.salon_id = $salon_id
                  AND DATE(p.payment_date) BETWEEN '$start_date' AND '$end_date'
                  GROUP BY DATE(p.payment_date) 
                  ORDER BY date DESC";
$revenue_data = mysqli_query($conn, $revenue_query);

// Service popularity for THIS salon
$popular_services = mysqli_query($conn, "SELECT s.service_name, COUNT(a.id) as booking_count, SUM(s.price) as total_revenue
                                         FROM services s
                                         JOIN appointments a ON s.id = a.service_id
                                         WHERE a.salon_id = $salon_id
                                         AND a.appointment_date BETWEEN '$start_date' AND '$end_date'
                                         GROUP BY s.id
                                         ORDER BY booking_count DESC
                                         LIMIT 5");

// Staff performance for THIS salon
$staff_performance = mysqli_query($conn, "SELECT u.full_name, COUNT(a.id) as appointments_done, SUM(s.price) as revenue_generated
                                          FROM users u
                                          JOIN appointments a ON u.id = a.staff_id
                                          JOIN services s ON a.service_id = s.id
                                          WHERE a.status = 'served' 
                                          AND a.salon_id = $salon_id
                                          AND a.appointment_date BETWEEN '$start_date' AND '$end_date'
                                          GROUP BY u.id
                                          ORDER BY appointments_done DESC");

// Total summary for THIS salon
$summary = mysqli_fetch_assoc(mysqli_query($conn, "SELECT 
    COUNT(DISTINCT a.customer_id) as total_customers,
    COUNT(a.id) as total_appointments,
    SUM(CASE WHEN a.status = 'served' THEN 1 ELSE 0 END) as completed_services,
    SUM(p.amount) as total_revenue
    FROM appointments a
    LEFT JOIN payments p ON a.id = p.appointment_id AND p.payment_status = 'paid'
    WHERE a.salon_id = $salon_id
    AND a.appointment_date BETWEEN '$start_date' AND '$end_date'"));

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
    .stat-number { font-size: 2rem; font-weight: bold; color: #d4af37; }
    
    .filter-bar { background: #1a1a1a; border-radius: 15px; padding: 1.5rem; margin-bottom: 2rem; display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; }
    .filter-bar .form-group { margin-bottom: 0; }
    .filter-bar .form-control { width: auto; min-width: 150px; padding: 10px; background: #2a2a2a; border: 1px solid rgba(212, 175, 55, 0.3); border-radius: 8px; color: white; }
    .filter-bar label { display: block; color: #d4af37; margin-bottom: 0.3rem; font-size: 0.8rem; }
    .btn-primary { background: #d4af37; color: #050505; border: none; padding: 10px 25px; border-radius: 25px; cursor: pointer; font-weight: 600; }
    .btn-primary:hover { background: #f9e547; }
    
    .report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem; }
    .report-card { background: #1a1a1a; border-radius: 15px; padding: 1.5rem; border: 1px solid rgba(212, 175, 55, 0.3); }
    .report-card h3 { color: #d4af37; margin-bottom: 1rem; }
    .table-container { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 10px; text-align: left; border-bottom: 1px solid rgba(212, 175, 55, 0.15); }
    th { color: #d4af37; }
    
    h1 { color: #d4af37; margin-bottom: 2rem; }
    .section-title { color: #d4af37; margin: 2rem 0 1rem 0; font-size: 1.2rem; }
    
    @media print {
        .sidebar, .navbar, .filter-bar, .btn-primary, .footer { display: none !important; }
        .main-content { margin: 0; padding: 0; }
        body { background: white; color: black; }
        .stat-card, .report-card { background: #f5f5f5 !important; color: black !important; border: 1px solid #ddd !important; }
        .stat-number { color: #d4af37 !important; }
    }
    
    @media (max-width: 768px) { .dashboard-container { flex-direction: column; } .sidebar { width: 100%; } .report-grid { grid-template-columns: 1fr; } }
</style>

<div class="dashboard-container">
    <aside class="sidebar">
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="appointments.php">📅 Appointments</a></li>
            <li><a href="services.php">💇 Services</a></li>
            <li><a href="staff.php">👥 Staff</a></li>
            <li><a href="customers.php">👤 Customers</a></li>
            <li><a href="payments.php">💰 Payments</a></li>
            <li><a href="reports.php" class="active">📈 Reports</a></li>
            <li><a href="profile.php">⚙️ My Profile</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <h1>📈 Business Reports</h1>
        
        <!-- Date Filter -->
        <div class="filter-bar">
            <form method="GET" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                </div>
                <button type="submit" class="btn-primary">📊 Generate Report</button>
                <button type="button" onclick="window.print()" class="btn-primary" style="background: #2a2a2a;">🖨️ Print</button>
            </form>
        </div>
        
        <!-- Summary Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $summary['total_customers'] ?? 0; ?></div>
                <p>Active Customers</p>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $summary['total_appointments'] ?? 0; ?></div>
                <p>Total Appointments</p>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $summary['completed_services'] ?? 0; ?></div>
                <p>Completed Services</p>
            </div>
            <div class="stat-card">
                <div class="stat-number">KSh <?php echo number_format($summary['total_revenue'] ?? 0, 2); ?></div>
                <p>Total Revenue</p>
            </div>
        </div>
        
        <!-- Report Grid -->
        <div class="report-grid">
            <!-- Daily Revenue -->
            <div class="report-card">
                <h3>📊 Daily Revenue</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr><th>Date</th><th>Revenue</th><th>Transactions</th></tr>
                        </thead>
                        <tbody>
                            <?php if($revenue_data && mysqli_num_rows($revenue_data) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($revenue_data)): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                    <td>KSh <?php echo number_format($row['daily_revenue'], 2); ?></td>
                                    <td><?php echo $row['transaction_count']; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3" style="text-align: center;">No revenue data available</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Popular Services -->
            <div class="report-card">
                <h3>⭐ Popular Services</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr><th>Service</th><th>Bookings</th><th>Revenue</th></tr>
                        </thead>
                        <tbody>
                            <?php if($popular_services && mysqli_num_rows($popular_services) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($popular_services)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['service_name']); ?></td>
                                    <td><?php echo $row['booking_count']; ?></td>
                                    <td>KSh <?php echo number_format($row['total_revenue'], 2); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3" style="text-align: center;">No service data available</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Staff Performance -->
            <div class="report-card" style="grid-column: 1 / -1;">
                <h3>👥 Staff Performance</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr><th>Staff</th><th>Services Done</th><th>Revenue Generated</th></tr>
                        </thead>
                        <tbody>
                            <?php if($staff_performance && mysqli_num_rows($staff_performance) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($staff_performance)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td><?php echo $row['appointments_done']; ?></td>
                                    <td>KSh <?php echo number_format($row['revenue_generated'], 2); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3" style="text-align: center;">No staff performance data available</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>