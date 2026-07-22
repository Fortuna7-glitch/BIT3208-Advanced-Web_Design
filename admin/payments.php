<?php
// admin/payments.php - ADMIN FULL ACCESS: All actions visible
require_once '../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$error = '';
$success = '';

// ============================================
// HANDLE ACTIONS (Admin has full access)
// ============================================

// Mark payment as paid
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'mark_paid') {
    $payment_id = (int)$_POST['payment_id'];
    $transaction_code = mysqli_real_escape_string($conn, $_POST['transaction_code'] ?? 'ADMIN-' . date('YmdHis'));
    
    $query = "UPDATE payments SET payment_status = 'paid', transaction_code = '$transaction_code' WHERE id = $payment_id";
    if (mysqli_query($conn, $query)) {
        // Update appointment payment status
        $apt_query = "UPDATE appointments SET payment_status = 'paid' WHERE id = (SELECT appointment_id FROM payments WHERE id = $payment_id)";
        mysqli_query($conn, $apt_query);
        $success = "Payment marked as paid!";
    } else {
        $error = "Failed to update payment: " . mysqli_error($conn);
    }
}

// Refund payment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'refund') {
    $payment_id = (int)$_POST['payment_id'];
    $query = "UPDATE payments SET payment_status = 'refunded' WHERE id = $payment_id";
    if (mysqli_query($conn, $query)) {
        $success = "Payment refunded!";
    } else {
        $error = "Failed to refund payment: " . mysqli_error($conn);
    }
}

// Delete payment
if (isset($_GET['delete'])) {
    $payment_id = (int)$_GET['delete'];
    $query = "DELETE FROM payments WHERE id = $payment_id";
    if (mysqli_query($conn, $query)) {
        $success = "Payment record deleted!";
    } else {
        $error = "Failed to delete payment: " . mysqli_error($conn);
    }
}

// ============================================
// SEARCH/FILTER
// ============================================
$search = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// ============================================
// GET PAYMENTS
// ============================================
$query = "SELECT p.*, a.appointment_date, a.appointment_time, u.full_name as customer_name, s.service_name 
          FROM payments p 
          JOIN appointments a ON p.appointment_id = a.id 
          JOIN users u ON a.customer_id = u.id 
          JOIN services s ON a.service_id = s.id 
          WHERE 1=1";
if ($search) {
    $query .= " AND (u.full_name LIKE '%$search%' OR s.service_name LIKE '%$search%' OR p.transaction_code LIKE '%$search%')";
}
if ($status_filter) {
    $query .= " AND p.payment_status = '$status_filter'";
}
if ($date_from) {
    $query .= " AND DATE(p.payment_date) >= '$date_from'";
}
if ($date_to) {
    $query .= " AND DATE(p.payment_date) <= '$date_to'";
}
$query .= " ORDER BY p.payment_date DESC";
$payments = mysqli_query($conn, $query);

// Get stats
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as total_revenue,
    SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
    SUM(CASE WHEN payment_status = 'refunded' THEN 1 ELSE 0 END) as refunded_count
    FROM payments";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

include '../includes/header.php';
?>

<style>
    .main-content {
        padding: 2rem;
        background: #0a0a0a;
        min-height: 100vh;
    }

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

    .page-header .search-section {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex: 0 1 280px;
        min-width: 160px;
    }

    .page-header .search-section input {
        padding: 8px 14px;
        background: #1a1a1a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        color: white;
        font-size: 0.85rem;
        width: 100%;
        transition: all 0.3s;
    }

    .page-header .search-section input:focus {
        outline: none;
        border-color: #d4af37;
        box-shadow: 0 0 20px rgba(212, 175, 55, 0.1);
    }

    .page-header .search-section input::placeholder {
        color: #666;
    }

    .page-header .search-section .search-btn {
        padding: 8px 14px;
        background: #d4af37;
        border: none;
        border-radius: 25px;
        color: #050505;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
        white-space: nowrap;
    }

    .page-header .search-section .search-btn:hover {
        background: #f9e547;
    }

    /* Stats */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: #1a1a1a;
        border-radius: 12px;
        padding: 1rem 1.2rem;
        text-align: center;
        border-left: 4px solid #d4af37;
        transition: all 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(212, 175, 55, 0.08);
    }

    .stat-card .stat-number {
        font-size: 1.8rem;
        font-weight: bold;
        color: #d4af37;
    }

    .stat-card .stat-label {
        color: #aaa;
        font-size: 0.75rem;
        margin-top: 0.2rem;
    }

    .stat-card.green { border-left-color: #28a745; }
    .stat-card.green .stat-number { color: #28a745; }
    .stat-card.orange { border-left-color: #ffc107; }
    .stat-card.orange .stat-number { color: #ffc107; }
    .stat-card.red { border-left-color: #dc3545; }
    .stat-card.red .stat-number { color: #dc3545; }
    .stat-card.blue { border-left-color: #17a2b8; }
    .stat-card.blue .stat-number { color: #17a2b8; }

    /* Filter Bar */
    .filter-bar {
        display: flex;
        gap: 0.8rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        align-items: center;
        background: #1a1a1a;
        padding: 0.8rem 1.2rem;
        border-radius: 12px;
        border: 1px solid rgba(212, 175, 55, 0.1);
    }

    .filter-bar input,
    .filter-bar select {
        padding: 8px 14px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        color: white;
        font-size: 0.85rem;
        min-width: 120px;
    }

    .filter-bar input:focus,
    .filter-bar select:focus {
        outline: none;
        border-color: #d4af37;
    }

    .filter-bar .filter-btn {
        padding: 8px 20px;
        background: #d4af37;
        border: none;
        border-radius: 25px;
        color: #050505;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
        white-space: nowrap;
    }

    .filter-bar .filter-btn:hover {
        background: #f9e547;
    }

    .filter-bar .clear-btn {
        padding: 8px 20px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        color: #aaa;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
        text-decoration: none;
        white-space: nowrap;
    }

    .filter-bar .clear-btn:hover {
        background: #333;
        color: white;
    }

    /* Table */
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
        font-size: 0.85rem;
        min-width: 800px;
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

    .payment-status {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
    }

    .payment-status.paid {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border: 1px solid #28a745;
    }

    .payment-status.pending {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid #d4af37;
    }

    .payment-status.failed {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
        border: 1px solid #dc3545;
    }

    .payment-status.refunded {
        background: rgba(23, 162, 184, 0.15);
        color: #17a2b8;
        border: 1px solid #17a2b8;
    }

    .action-cell {
        display: flex;
        gap: 0.3rem;
        flex-wrap: wrap;
    }

    .action-cell .btn {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
    }

    .btn-paid {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border: 1px solid #28a745;
    }

    .btn-paid:hover {
        background: #28a745;
        color: white;
    }

    .btn-refund {
        background: rgba(23, 162, 184, 0.15);
        color: #17a2b8;
        border: 1px solid #17a2b8;
    }

    .btn-refund:hover {
        background: #17a2b8;
        color: white;
    }

    .btn-delete {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
        border: 1px solid #dc3545;
    }

    .btn-delete:hover {
        background: #dc3545;
        color: white;
    }

    .btn-view {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid rgba(212, 175, 55, 0.3);
    }

    .btn-view:hover {
        background: #d4af37;
        color: #050505;
    }

    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 1rem;
    }

    .alert-success {
        background: rgba(40, 167, 69, 0.2);
        border: 1px solid #28a745;
        color: #28a745;
    }

    .alert-danger {
        background: rgba(220, 53, 69, 0.2);
        border: 1px solid #dc3545;
        color: #dc3545;
    }

    .empty-state {
        text-align: center;
        padding: 3rem 0;
        color: #666;
    }

    .back-link {
        display: inline-block;
        margin-top: 1.5rem;
        color: #d4af37;
        text-decoration: none;
    }

    .back-link:hover {
        text-decoration: underline;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        table { min-width: 600px; }
    }

    @media (max-width: 768px) {
        .main-content { padding: 1rem; }
        .page-header {
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
        }
        .page-header .title-section h1 { font-size: 1.1rem; }
        .page-header .quick-actions { justify-content: flex-start; }
        .page-header .search-section { flex: 1; }
        .page-header .search-section input { width: 100%; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 0.8rem; }
        .stat-card { padding: 0.8rem; }
        .stat-card .stat-number { font-size: 1.4rem; }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar input,
        .filter-bar select { width: 100%; }
        table { min-width: 500px; font-size: 0.75rem; }
        th, td { padding: 6px; }
        .action-cell { flex-direction: column; }
        .action-cell .btn { width: 100%; text-align: center; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .page-header .quick-actions .quick-btn { font-size: 0.65rem; padding: 6px 12px; }
        table { min-width: 400px; font-size: 0.7rem; }
        th, td { padding: 4px; }
    }
</style>

<div class="main-content">

    <!-- ============================================
       HEADER: Title + Quick Actions + Search
       ============================================ -->
    <div class="page-header">
        <div class="title-section">
            <h1>💰 Payment Management</h1>
            <p>Track and manage all payments</p>
        </div>
        <div class="quick-actions">
            <a href="appointments.php" class="quick-btn"><i class="fas fa-calendar"></i> Appointments</a>
            <a href="reports.php" class="quick-btn"><i class="fas fa-chart-line"></i> Reports</a>
            <a href="dashboard.php" class="quick-btn"><i class="fas fa-home"></i> Dashboard</a>
        </div>
        <div class="search-section">
            <form method="GET" style="display: flex; gap: 0.5rem; width: 100%;">
                <input type="text" name="q" placeholder="🔍 Search payments..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="search-btn">Search</button>
            </form>
        </div>
    </div>

    <!-- ============================================
       ALERTS
       ============================================ -->
    <?php if($success): ?>
        <div class="alert alert-success">✅ <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-danger">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- ============================================
       STATISTICS
       ============================================ -->
    <div class="stats-grid">
        <div class="stat-card green">
            <div class="stat-number">KSh <?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
            <div class="stat-label">Total Payments</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-number"><?php echo $stats['pending_count'] ?? 0; ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card green">
            <div class="stat-number"><?php echo $stats['paid_count'] ?? 0; ?></div>
            <div class="stat-label">Paid</div>
        </div>
        <div class="stat-card red">
            <div class="stat-number"><?php echo $stats['refunded_count'] ?? 0; ?></div>
            <div class="stat-label">Refunded</div>
        </div>
    </div>

    <!-- ============================================
       FILTER BAR
       ============================================ -->
    <div class="filter-bar">
        <form method="GET" style="display: flex; gap: 0.8rem; flex: 1; flex-wrap: wrap; align-items: center;">
            <input type="hidden" name="q" value="<?php echo htmlspecialchars($search); ?>">
            <select name="status">
                <option value="">All Status</option>
                <option value="paid" <?php echo ($status_filter == 'paid') ? 'selected' : ''; ?>>Paid</option>
                <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                <option value="refunded" <?php echo ($status_filter == 'refunded') ? 'selected' : ''; ?>>Refunded</option>
                <option value="failed" <?php echo ($status_filter == 'failed') ? 'selected' : ''; ?>>Failed</option>
            </select>
            <input type="date" name="date_from" placeholder="From" value="<?php echo $date_from; ?>">
            <input type="date" name="date_to" placeholder="To" value="<?php echo $date_to; ?>">
            <button type="submit" class="filter-btn">Filter</button>
            <a href="payments.php" class="clear-btn">Clear</a>
        </form>
    </div>

    <!-- ============================================
       PAYMENTS TABLE
       ============================================ -->
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Service</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($payments) > 0): ?>
                    <?php while($payment = mysqli_fetch_assoc($payments)): ?>
                    <tr>
                        <td>#<?php echo $payment['id']; ?></td>
                        <td><?php echo htmlspecialchars($payment['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars($payment['service_name']); ?></td>
                        <td>KSh <?php echo number_format($payment['amount'], 2); ?></td>
                        <td><?php echo ucfirst($payment['payment_method']); ?></td>
                        <td>
                            <span class="payment-status <?php echo $payment['payment_status']; ?>">
                                <?php echo ucfirst($payment['payment_status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($payment['payment_date'] ?? $payment['appointment_date'])); ?></td>
                        <td class="action-cell">
                            <?php if($payment['payment_status'] == 'pending'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                    <input type="hidden" name="action" value="mark_paid">
                                    <input type="hidden" name="transaction_code" value="ADMIN-<?php echo date('YmdHis'); ?>">
                                    <button type="submit" class="btn btn-paid" onclick="return confirm('Mark this payment as paid?')">✅ Paid</button>
                                </form>
                            <?php endif; ?>

                            <?php if($payment['payment_status'] == 'paid'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                    <input type="hidden" name="action" value="refund">
                                    <button type="submit" class="btn btn-refund" onclick="return confirm('Refund this payment?')">↩️ Refund</button>
                                </form>
                            <?php endif; ?>

                            <a href="payments.php?delete=<?php echo $payment['id']; ?>" class="btn btn-delete" onclick="return confirm('Delete this payment record?')">🗑️ Delete</a>
                            <a href="payments.php?view=<?php echo $payment['id']; ?>" class="btn btn-view">👁️ View</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            <div class="empty-state">
                                <p>No payments found.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<?php include '../includes/footer.php'; ?>