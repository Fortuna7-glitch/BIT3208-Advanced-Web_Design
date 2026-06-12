<?php
// staff/reports.php - Staff views reports (limited access)
require_once '../config/database.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

// Check if staff has this permission
if (!hasPermission($_SESSION['user_id'], 'view_reports')) {
    redirect('dashboard.php');
}

$staff_id = $_SESSION['user_id'];

// Get date filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Get staff's own performance data
$my_performance = mysqli_query($conn, "SELECT 
    COUNT(*) as total_appointments,
    SUM(CASE WHEN status = 'served' THEN 1 ELSE 0 END) as completed_services,
    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_appointments
    FROM appointments 
    WHERE staff_id = $staff_id AND appointment_date BETWEEN '$start_date' AND '$end_date'");

$my_stats = mysqli_fetch_assoc($my_performance);

// Get my revenue
$my_revenue = mysqli_query($conn, "SELECT SUM(s.price) as total_revenue
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    WHERE a.staff_id = $staff_id AND a.payment_status = 'paid' AND a.appointment_date BETWEEN '$start_date' AND '$end_date'");
$revenue_data = mysqli_fetch_assoc($my_revenue);

// Get my popular services
$my_services = mysqli_query($conn, "SELECT s.service_name, COUNT(a.id) as count, SUM(s.price) as revenue
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    WHERE a.staff_id = $staff_id AND a.appointment_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY s.id
    ORDER BY count DESC
    LIMIT 5");

// Get my daily appointments
$daily_query = "SELECT DATE(appointment_date) as date, COUNT(*) as count, 
                SUM(CASE WHEN status = 'served' THEN 1 ELSE 0 END) as completed
                FROM appointments 
                WHERE staff_id = $staff_id AND appointment_date BETWEEN '$start_date' AND '$end_date'
                GROUP BY DATE(appointment_date) 
                ORDER BY date DESC
                LIMIT 10";
$daily_stats = mysqli_query($conn, $daily_query);

include '../includes/header.php';
?>

<style>
    .staff-container { display: flex; min-height: 100vh; }
    .sidebar { width: 280px; background: #050505; border-right: 1px solid #d4af37; padding: 2rem 1rem; }
    .sidebar-menu { list-style: none; padding: 0; }
    .sidebar-menu li { margin-bottom: 0.5rem; }
    .sidebar-menu a { display: block; padding: 12px 20px; color: white; text-decoration: none; border-radius: 10px; transition: all 0.3s; }
    .sidebar-menu a:hover, .sidebar-menu a.active { background: #d4af37; color: #050505; }
    .main-content { flex: 1; padding: 2rem; background: #0a0a0a; }
    
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
    .stat-card { background: #1a1a1a; border-radius: 15px; padding: 1.5rem; text-align: center; border-left: 4px solid #d4af37; }
    .stat-number { font-size: 2rem; font-weight: bold; color: #d4af37; }
    
    .filter-bar {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        display: flex;
        gap: 1rem;
        align-items: flex-end;
        flex-wrap: wrap;
    }
    .filter-bar .form-group { margin-bottom: 0; }
    .filter-bar .form-control { width: auto; min-width: 150px; }
    
    .table-wrapper {
        overflow-x: auto;
        background: #1a1a1a;
        border-radius: 15px;
        padding: 0;
        margin-top: 1rem;
        border: 1px solid rgba(212, 175, 55, 0.2);
    }
    .reports-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }
    .reports-table th {
        background: #2a2a2a;
        color: #d4af37;
        padding: 14px 12px;
        text-align: left;
        font-weight: 600;
        border-bottom: 2px solid #d4af37;
    }
    .reports-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid rgba(212, 175, 55, 0.15);
        color: #ffffff;
    }
    
    .btn-primary {
        background: #d4af37;
        color: #050505;
        border: none;
        padding: 10px 25px;
        border-radius: 25px;
        cursor: pointer;
        font-weight: 600;
    }
    .btn-primary:hover { background: #f9e547; }
    
    h1, h2 { color: #d4af37; margin-bottom: 1rem; }
    h2 { font-size: 1.3rem; margin-top: 1.5rem; }
    .form-group label { color: #d4af37; margin-bottom: 0.3rem; display: block; }
    .form-control { padding: 8px 12px; background: #2a2a2a; border: 1px solid rgba(212, 175, 55, 0.3); border-radius: 8px; color: white; }
    
    @media (max-width: 768px) { 
        .staff-container { flex-direction: column; } 
        .sidebar { width: 100%; }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar .form-control { width: 100%; }
    }
</style>

<div class="staff-container">
    <aside class="sidebar">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h3 style="color: #d4af37;">👤 <?php echo htmlspecialchars($_SESSION['user_name']); ?></h3>
            <p>Staff Member</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="appointments.php">📅 My Appointments</a></li>
            <?php if(hasPermission($staff_id, 'book_for_customers')): ?>
                <li><a href="book_for_customer.php">📝 Book for Customer</a></li>
            <?php endif; ?>
            <?php if(hasPermission($staff_id, 'manual_cash_payment')): ?>
                <li><a href="manual_payment.php">💵 Manual Cash Payment</a></li>
            <?php endif; ?>
            <?php if(hasPermission($staff_id, 'view_reports')): ?>
                <li><a href="reports.php" class="active">📈 My Reports</a></li>
            <?php endif; ?>
            <li><a href="profile.php">⚙️ My Profile</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <h1>📈 My Performance Reports</h1>
        
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
        
        <!-- Summary Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $my_stats['total_appointments'] ?? 0; ?></div>
                <p>Total Appointments</p>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $my_stats['completed_services'] ?? 0; ?></div>
                <p>Completed Services</p>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $my_stats['paid_appointments'] ?? 0; ?></div>
                <p>Paid Appointments</p>
            </div>
            <div class="stat-card">
                <div class="stat-number">KSh <?php echo number_format($revenue_data['total_revenue'] ?? 0, 2); ?></div>
                <p>Revenue Generated</p>
            </div>
        </div>
        
        <!-- My Popular Services -->
        <h2>⭐ My Most Booked Services</h2>
        <div class="table-wrapper">
            <table class="reports-table">
                <thead>
                    <tr><th>Service</th><th>Times Booked</th><th>Revenue Generated</th></tr>
                </thead>
                <tbody>
                    <?php if($my_services && mysqli_num_rows($my_services) > 0): ?>
                        <?php while($service = mysqli_fetch_assoc($my_services)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                            <td><?php echo $service['count']; ?> bookings</td>
                            <td>KSh <?php echo number_format($service['revenue'], 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <td><td colspan="3" style="text-align: center; padding: 30px;">No service data available for this period</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Daily Activity -->
        <h2>📅 Daily Activity</h2>
        <div class="table-wrapper">
            <table class="reports-table">
                <thead>
                    <tr><th>Date</th><th>Total Appointments</th><th>Completed</th><th>Completion Rate</th></tr>
                </thead>
                <tbody>
                    <?php if($daily_stats && mysqli_num_rows($daily_stats) > 0): ?>
                        <?php while($day = mysqli_fetch_assoc($daily_stats)): 
                            $rate = $day['count'] > 0 ? round(($day['completed'] / $day['count']) * 100) : 0;
                        ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($day['date'])); ?></td>
                            <td><?php echo $day['count']; ?> bookings</td>
                            <td><?php echo $day['completed']; ?> completed</td>
                            <td>
                                <span style="color: <?php echo $rate >= 80 ? '#28a745' : ($rate >= 50 ? '#d4af37' : '#dc3545'); ?>">
                                    <?php echo $rate; ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align: center; padding: 30px;">No daily data available for this period</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Completion Rate Note -->
        <div style="margin-top: 1.5rem; padding: 1rem; background: #1a1a1a; border-radius: 10px; text-align: center;">
            <p>✨ Great job! Keep up the excellent service! ✨</p>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>