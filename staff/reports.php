<?php
// staff/reports.php - MODIFIED: Permission check for view_reports
require_once '../config/database.php';
require_once '../includes/permissions.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

$staff_id = $_SESSION['user_id'];

// ============================================
// PERMISSION CHECK
// ============================================
if (!hasPermission($staff_id, 'view_reports')) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Permission Denied</title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>
    <body>
        <div style="text-align: center; padding: 3rem; background: #1a1a1a; border-radius: 15px; border: 1px solid rgba(212, 175, 55, 0.2); max-width: 500px; margin: 3rem auto;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">🚫</div>
            <h2 style="color: #dc3545;">Permission Denied</h2>
            <p style="color: #aaa;">You don't have permission to view reports.</p>
            <a href="dashboard.php" style="display: inline-block; margin-top: 1rem; padding: 10px 25px; background: #d4af37; color: #050505; border-radius: 25px; text-decoration: none; font-weight: 600;">Back to Dashboard</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// ============================================
// DATE FILTER
// ============================================
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// ============================================
// STAFF PERFORMANCE DATA
// ============================================
$my_performance = mysqli_query($conn, "SELECT 
    COUNT(*) as total_appointments,
    SUM(CASE WHEN status = 'served' THEN 1 ELSE 0 END) as completed_services,
    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_appointments
    FROM appointments 
    WHERE staff_id = $staff_id AND appointment_date BETWEEN '$start_date' AND '$end_date'");

$my_stats = mysqli_fetch_assoc($my_performance);

// ============================================
// MY REVENUE
// ============================================
$my_revenue = mysqli_query($conn, "SELECT SUM(s.price) as total_revenue
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    WHERE a.staff_id = $staff_id AND a.payment_status = 'paid' AND a.appointment_date BETWEEN '$start_date' AND '$end_date'");
$revenue_data = mysqli_fetch_assoc($my_revenue);

// ============================================
// MY POPULAR SERVICES
// ============================================
$my_services = mysqli_query($conn, "SELECT s.service_name, COUNT(a.id) as count, SUM(s.price) as revenue
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    WHERE a.staff_id = $staff_id AND a.appointment_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY s.id
    ORDER BY count DESC
    LIMIT 5");

// ============================================
// DAILY ACTIVITY
// ============================================
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
    .main-content {
        padding: 2rem;
        background: #0a0a0a;
        min-height: 100vh;
    }

    /* ============================================
       HEADER WITH QUICK ACTIONS
       ============================================ */
    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }

    .page-header .title-section h1 {
        color: #d4af37;
        font-size: 1.3rem;
        font-weight: 600;
        border-left: 3px solid #d4af37;
        padding-left: 1rem;
    }

    .page-header .title-section p {
        color: #aaa;
        font-size: 0.85rem;
        margin-top: 0.2rem;
        padding-left: 1rem;
    }

    .page-header .quick-actions {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .page-header .quick-actions .quick-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 8px 16px;
        border-radius: 25px;
        font-size: 0.75rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s;
        background: rgba(212, 175, 55, 0.1);
        color: #d4af37;
        border: 1px solid rgba(212, 175, 55, 0.2);
    }

    .page-header .quick-actions .quick-btn:hover {
        background: #d4af37;
        color: #050505;
        transform: translateY(-2px);
    }

    .page-header .quick-actions .quick-btn i {
        font-size: 0.8rem;
    }

    /* Filter Bar */
    .filter-bar {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        align-items: flex-end;
        background: #1a1a1a;
        padding: 1.2rem;
        border-radius: 12px;
        border: 1px solid rgba(212, 175, 55, 0.1);
    }

    .filter-bar .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
    }

    .filter-bar .form-group label {
        color: #d4af37;
        font-size: 0.8rem;
        font-weight: 500;
    }

    .filter-bar .form-group input {
        padding: 8px 14px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        color: white;
        font-size: 0.85rem;
    }

    .filter-bar .form-group input:focus {
        outline: none;
        border-color: #d4af37;
    }

    .filter-bar .filter-btn {
        padding: 8px 25px;
        background: #d4af37;
        border: none;
        border-radius: 25px;
        color: #050505;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
    }

    .filter-bar .filter-btn:hover {
        background: #f9e547;
    }

    .filter-bar .print-btn {
        padding: 8px 20px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        color: #aaa;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
    }

    .filter-bar .print-btn:hover {
        background: #333;
        color: white;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: #1a1a1a;
        border-radius: 12px;
        padding: 1.2rem 1.5rem;
        text-align: center;
        border-left: 4px solid #d4af37;
        transition: all 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(212, 175, 55, 0.08);
    }

    .stat-card .stat-number {
        font-size: 2rem;
        font-weight: bold;
        color: #d4af37;
    }

    .stat-card .stat-label {
        color: #aaa;
        font-size: 0.8rem;
        margin-top: 0.2rem;
    }

    .stat-card.green { border-left-color: #28a745; }
    .stat-card.green .stat-number { color: #28a745; }
    .stat-card.orange { border-left-color: #ffc107; }
    .stat-card.orange .stat-number { color: #ffc107; }
    .stat-card.blue { border-left-color: #17a2b8; }
    .stat-card.blue .stat-number { color: #17a2b8; }

    /* Table */
    .table-wrapper {
        overflow-x: auto;
        background: #1a1a1a;
        border-radius: 12px;
        padding: 0;
        border: 1px solid rgba(212, 175, 55, 0.1);
        margin-bottom: 1.5rem;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
        min-width: 400px;
    }

    th, td {
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid rgba(212, 175, 55, 0.1);
    }

    th {
        color: #d4af37;
        font-weight: 600;
    }

    tr:hover {
        background: rgba(212, 175, 55, 0.05);
    }

    .section-title {
        color: #d4af37;
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 1rem;
        display: block;
    }

    .back-link {
        display: inline-block;
        margin-top: 0.5rem;
        color: #d4af37;
        text-decoration: none;
    }

    .back-link:hover {
        text-decoration: underline;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .main-content { padding: 1rem; }
        .page-header {
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
        }
        .page-header .title-section h1 { font-size: 1.1rem; }
        .page-header .quick-actions { justify-content: flex-start; }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar .form-group input { width: 100%; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 0.8rem; }
        .stat-card { padding: 1rem; }
        .stat-card .stat-number { font-size: 1.5rem; }
        table { font-size: 0.75rem; min-width: 300px; }
        th, td { padding: 6px; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .page-header .quick-actions .quick-btn { font-size: 0.65rem; padding: 6px 12px; }
    }
</style>

<div class="main-content">

    <!-- ============================================
       HEADER: Title + Quick Actions
       ============================================ -->
    <div class="page-header">
        <div class="title-section">
            <h1>📈 My Performance Reports</h1>
            <p>Track your appointments, revenue, and performance</p>
        </div>
        <div class="quick-actions">
            <a href="appointments.php" class="quick-btn"><i class="fas fa-list"></i> Appointments</a>
            <a href="dashboard.php" class="quick-btn"><i class="fas fa-home"></i> Dashboard</a>
        </div>
    </div>

    <!-- ============================================
       FILTER BAR
       ============================================ -->
    <div class="filter-bar">
        <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; width: 100%;">
            <div class="form-group">
                <label>Start Date</label>
                <input type="date" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            <div class="form-group">
                <label>End Date</label>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            <button type="submit" class="filter-btn">📊 Generate</button>
            <button type="button" class="print-btn" onclick="window.print()">🖨️ Print</button>
        </form>
    </div>

    <!-- ============================================
       STATISTICS
       ============================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $my_stats['total_appointments'] ?? 0; ?></div>
            <div class="stat-label">Total Appointments</div>
        </div>
        <div class="stat-card green">
            <div class="stat-number"><?php echo $my_stats['completed_services'] ?? 0; ?></div>
            <div class="stat-label">Completed Services</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-number"><?php echo $my_stats['paid_appointments'] ?? 0; ?></div>
            <div class="stat-label">Paid Appointments</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-number">KSh <?php echo number_format($revenue_data['total_revenue'] ?? 0, 2); ?></div>
            <div class="stat-label">Revenue Generated</div>
        </div>
    </div>

    <!-- ============================================
       POPULAR SERVICES
       ============================================ -->
    <h2 class="section-title">⭐ My Most Booked Services</h2>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Times Booked</th>
                    <th>Revenue Generated</th>
                </tr>
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
                    <tr>
                        <td colspan="3" style="text-align: center; padding: 30px; color: #666;">
                            No service data available for this period
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ============================================
       DAILY ACTIVITY
       ============================================ -->
    <h2 class="section-title">📅 Daily Activity</h2>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Total Appointments</th>
                    <th>Completed</th>
                    <th>Completion Rate</th>
                </tr>
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
                            <span style="color: <?php echo $rate >= 80 ? '#28a745' : ($rate >= 50 ? '#d4af37' : '#dc3545'); ?>; font-weight: 600;">
                                <?php echo $rate; ?>%
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 30px; color: #666;">
                            No daily data available for this period
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<?php include '../includes/footer.php'; ?>