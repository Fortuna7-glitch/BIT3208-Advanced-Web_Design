<?php
// admin/reports.php
require_once '../config/database.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

// Get date filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Get report data
$revenue_query = "SELECT DATE(payment_date) as date, SUM(amount) as daily_revenue, COUNT(*) as transaction_count 
                  FROM payments 
                  WHERE payment_status = 'paid' AND DATE(payment_date) BETWEEN '$start_date' AND '$end_date'
                  GROUP BY DATE(payment_date) 
                  ORDER BY date DESC";
$revenue_data = mysqli_query($conn, $revenue_query);

// Service popularity
$popular_services = mysqli_query($conn, "SELECT s.service_name, COUNT(a.id) as booking_count, SUM(s.price) as total_revenue
                                         FROM services s
                                         JOIN appointments a ON s.id = a.service_id
                                         WHERE a.appointment_date BETWEEN '$start_date' AND '$end_date'
                                         GROUP BY s.id
                                         ORDER BY booking_count DESC
                                         LIMIT 5");

// Staff performance
$staff_performance = mysqli_query($conn, "SELECT u.full_name, COUNT(a.id) as appointments_done, SUM(s.price) as revenue_generated
                                          FROM users u
                                          JOIN appointments a ON u.id = a.staff_id
                                          JOIN services s ON a.service_id = s.id
                                          WHERE a.status = 'served' AND a.appointment_date BETWEEN '$start_date' AND '$end_date'
                                          GROUP BY u.id
                                          ORDER BY appointments_done DESC");

// Total summary
$summary = mysqli_fetch_assoc(mysqli_query($conn, "SELECT 
    COUNT(DISTINCT a.customer_id) as total_customers,
    COUNT(a.id) as total_appointments,
    SUM(CASE WHEN a.status = 'served' THEN 1 ELSE 0 END) as completed_services,
    SUM(p.amount) as total_revenue
    FROM appointments a
    LEFT JOIN payments p ON a.id = p.appointment_id AND p.payment_status = 'paid'
    WHERE a.appointment_date BETWEEN '$start_date' AND '$end_date'"));

include '../includes/header.php';
?>

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
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <h1>Business Reports 📈</h1>
        
        <!-- Date Filter -->
        <div style="background: var(--gray); border-radius: 15px; padding: 1.5rem; margin-bottom: 2rem;">
            <form method="GET" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                </div>
                <button type="submit" class="btn btn-primary">Generate Report</button>
                <button type="button" onclick="window.print()" class="btn btn-outline">🖨️ Print Report</button>
            </form>
        </div>
        
        <!-- Summary Cards -->
        <div class="stats-grid" style="margin-bottom: 2rem;">
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
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem;">
            <!-- Daily Revenue -->
            <div style="background: var(--gray); border-radius: 15px; padding: 1.5rem;">
                <h3 style="color: var(--gold);">📊 Daily Revenue</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr><th>Date</th><th>Revenue</th><th>Transactions</th></tr>
                        </thead>
                        <tbody>
                            <?php while($row = mysqli_fetch_assoc($revenue_data)): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                <td>KSh <?php echo number_format($row['daily_revenue'], 2); ?></td>
                                <td><?php echo $row['transaction_count']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Popular Services -->
            <div style="background: var(--gray); border-radius: 15px; padding: 1.5rem;">
                <h3 style="color: var(--gold);">⭐ Popular Services</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr><th>Service</th><th>Bookings</th><th>Revenue</th></tr>
                        </thead>
                        <tbody>
                            <?php while($row = mysqli_fetch_assoc($popular_services)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['service_name']); ?></td>
                                <td><?php echo $row['booking_count']; ?></td>
                                <td>KSh <?php echo number_format($row['total_revenue'], 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Staff Performance -->
            <div style="background: var(--gray); border-radius: 15px; padding: 1.5rem;">
                <h3 style="color: var(--gold);">👥 Staff Performance</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr><th>Staff</th><th>Services Done</th><th>Revenue Generated</th></tr>
                        </thead>
                        <tbody>
                            <?php while($row = mysqli_fetch_assoc($staff_performance)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td><?php echo $row['appointments_done']; ?></td>
                                <td>KSh <?php echo number_format($row['revenue_generated'], 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<style>
    @media print {
        .sidebar, .navbar, .footer, .btn, form {
            display: none !important;
        }
        .main-content {
            margin: 0;
            padding: 0;
        }
        body {
            background: white;
            color: black;
        }
        .stat-card, [style*="background: var(--gray)"] {
            background: #f5f5f5 !important;
            color: black !important;
        }
    }
</style>

<?php include '../includes/footer.php'; ?>