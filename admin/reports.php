<?php
// admin/reports.php - UPDATED with new hamburger sidebar layout
require_once '../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$salon_id = $_SESSION['salon_id'] ?? 0;
if ($salon_id <= 0) {
    $user_id = $_SESSION['user_id'];
    $user_query = mysqli_query($conn, "SELECT salon_id FROM users WHERE id = $user_id");
    if ($user_result = mysqli_fetch_assoc($user_query)) {
        $salon_id = $user_result['salon_id'];
        $_SESSION['salon_id'] = $salon_id;
    }
}

if (!hasFeature($salon_id, 'reports')) {
    $_SESSION['upgrade_required'] = 'reports';
    redirect('dashboard.php');
}

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Revenue data
$revenue_data = mysqli_query($conn, "SELECT DATE(p.payment_date) as date, SUM(p.amount) as daily_revenue, COUNT(*) as transaction_count 
                                     FROM payments p
                                     JOIN appointments a ON p.appointment_id = a.id 
                                     WHERE p.payment_status = 'paid' 
                                     AND a.salon_id = $salon_id
                                     AND DATE(p.payment_date) BETWEEN '$start_date' AND '$end_date'
                                     GROUP BY DATE(p.payment_date) 
                                     ORDER BY date DESC");

// Popular services
$popular_services = mysqli_query($conn, "SELECT s.service_name, COUNT(a.id) as booking_count, SUM(s.price) as total_revenue
                                         FROM services s
                                         JOIN appointments a ON s.id = a.service_id
                                         WHERE a.salon_id = $salon_id
                                         AND a.appointment_date BETWEEN '$start_date' AND '$end_date'
                                         GROUP BY s.id
                                         ORDER BY booking_count DESC
                                         LIMIT 5");

// Staff performance
$staff_performance = mysqli_query($conn, "SELECT u.full_name, COUNT(a.id) as appointments_done, SUM(s.price) as revenue_generated
                                          FROM users u
                                          JOIN appointments a ON u.id = a.staff_id
                                          JOIN services s ON a.service_id = s.id
                                          WHERE a.status = 'served' 
                                          AND a.salon_id = $salon_id
                                          AND a.appointment_date BETWEEN '$start_date' AND '$end_date'
                                          GROUP BY u.id
                                          ORDER BY appointments_done DESC");

// Summary
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
    .main-content {
        padding: 2rem;
        background: #0a0a0a;
        min-height: 100vh;
    }

    .section-title {
        color: #d4af37;
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
        border-left: 3px solid #d4af37;
        padding-left: 1rem;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    .stat-card {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        text-align: center;
        border-left: 4px solid #d4af37;
        transition: all 0.3s;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(212, 175, 55, 0.1);
    }
    .stat-card .number {
        font-size: 2rem;
        font-weight: bold;
        color: #d4af37;
    }
    .stat-card .label {
        color: #aaa;
        margin-top: 0.3rem;
        font-size: 0.85rem;
    }

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
    .filter-bar .form-group {
        margin-bottom: 0;
    }
    .filter-bar .form-control {
        width: auto;
        min-width: 140px;
        padding: 10px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 8px;
        color: white;
    }
    .filter-bar label {
        display: block;
        color: #d4af37;
        margin-bottom: 0.3rem;
        font-size: 0.8rem;
    }

    .btn-primary {
        background: #d4af37;
        color: #050505;
        border: none;
        padding: 10px 20px;
        border-radius: 25px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
    }
    .btn-primary:hover {
        background: #f9e547;
        transform: translateY(-2px);
    }

    .report-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
        gap: 2rem;
    }
    .report-card {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        border: 1px solid rgba(212, 175, 55, 0.3);
    }
    .report-card h3 {
        color: #d4af37;
        margin-bottom: 1rem;
        font-size: 1rem;
    }

    .table-wrapper {
        overflow-x: auto;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
        min-width: 400px;
    }
    th, td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid rgba(212, 175, 55, 0.15);
    }
    th { color: #d4af37; font-weight: 600; }
    tr:hover { background: rgba(212, 175, 55, 0.05); }

    .back-link {
        display: inline-block;
        margin-top: 1.5rem;
        color: #d4af37;
        text-decoration: none;
    }
    .back-link:hover {
        text-decoration: underline;
    }

    /* ============================================
       RESPONSIVE
       ============================================ */
    @media (max-width: 1024px) {
        .report-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 768px) {
        .main-content { padding: 1rem; }
        .section-title { font-size: 1.1rem; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 1rem; }
        .stat-card { padding: 1rem; }
        .stat-card .number { font-size: 1.5rem; }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar .form-control { width: 100%; }
        table { font-size: 0.75rem; min-width: 300px; }
        th, td { padding: 6px; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .section-title { font-size: 1rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .report-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="main-content">

    <h1 class="section-title">📈 Business Reports</h1>

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
            <button type="submit" class="btn-primary">📊 Generate</button>
            <button type="button" onclick="window.print()" class="btn-primary" style="background: #2a2a2a;">🖨️ Print</button>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number"><?php echo $summary['total_customers'] ?? 0; ?></div>
            <div class="label">Active Customers</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $summary['total_appointments'] ?? 0; ?></div>
            <div class="label">Total Appointments</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $summary['completed_services'] ?? 0; ?></div>
            <div class="label">Completed Services</div>
        </div>
        <div class="stat-card">
            <div class="number">KSh <?php echo number_format($summary['total_revenue'] ?? 0, 2); ?></div>
            <div class="label">Total Revenue</div>
        </div>
    </div>

    <!-- Report Grid -->
    <div class="report-grid">
        <!-- Daily Revenue -->
        <div class="report-card">
            <h3>📊 Daily Revenue</h3>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>Date</th><th>Revenue</th><th>Transactions</th></tr></thead>
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
                            <tr><td colspan="3" style="text-align:center;">No revenue data</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Popular Services -->
        <div class="report-card">
            <h3>⭐ Popular Services</h3>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>Service</th><th>Bookings</th><th>Revenue</th></tr></thead>
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
                            <tr><td colspan="3" style="text-align:center;">No service data</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Staff Performance -->
        <div class="report-card" style="grid-column: 1 / -1;">
            <h3>👥 Staff Performance</h3>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>Staff</th><th>Services Done</th><th>Revenue Generated</th></tr></thead>
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
                            <tr><td colspan="3" style="text-align:center;">No staff data</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<?php include '../includes/footer.php'; ?>