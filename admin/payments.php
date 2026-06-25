<?php
// admin/payments.php - UPDATED with new hamburger sidebar layout
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

// Handle Update Payment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_payment'])) {
    $payment_id = mysqli_real_escape_string($conn, $_POST['payment_id']);
    $status = mysqli_real_escape_string($conn, $_POST['payment_status']);
    $transaction_code = mysqli_real_escape_string($conn, $_POST['transaction_code']);

    // Update payment with salon_id verification
    $query = "UPDATE payments p 
              JOIN appointments a ON p.appointment_id = a.id 
              SET p.payment_status = '$status', p.transaction_code = '$transaction_code' 
              WHERE p.id = $payment_id AND a.salon_id = $salon_id";
    mysqli_query($conn, $query);

    // Also update appointment payment status
    $apt_query = "UPDATE appointments a 
                  JOIN payments p ON p.appointment_id = a.id 
                  SET a.payment_status = '$status' 
                  WHERE p.id = $payment_id AND a.salon_id = $salon_id";
    mysqli_query($conn, $apt_query);

    $success = "Payment updated successfully!";
}

// Get stats for THIS salon only
$total_paid = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(p.amount) as total 
                                                     FROM payments p 
                                                     JOIN appointments a ON p.appointment_id = a.id 
                                                     WHERE p.payment_status = 'paid' AND a.salon_id = $salon_id"))['total'] ?? 0;
$total_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(p.amount) as total 
                                                        FROM payments p 
                                                        JOIN appointments a ON p.appointment_id = a.id 
                                                        WHERE p.payment_status = 'pending' AND a.salon_id = $salon_id"))['total'] ?? 0;
$mpesa_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count 
                                                      FROM payments p 
                                                      JOIN appointments a ON p.appointment_id = a.id 
                                                      WHERE p.payment_method = 'mpesa' AND a.salon_id = $salon_id"))['count'] ?? 0;
$cash_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count 
                                                     FROM payments p 
                                                     JOIN appointments a ON p.appointment_id = a.id 
                                                     WHERE p.payment_method = 'cash' AND a.salon_id = $salon_id"))['count'] ?? 0;

// Get all payments for THIS salon only
$payments = mysqli_query($conn, "SELECT p.*, u.full_name as customer_name, s.service_name, a.appointment_date 
                                 FROM payments p 
                                 JOIN appointments a ON p.appointment_id = a.id 
                                 JOIN users u ON a.customer_id = u.id 
                                 JOIN services s ON a.service_id = s.id 
                                 WHERE a.salon_id = $salon_id
                                 ORDER BY p.payment_date DESC");

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
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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
        font-size: 0.9rem;
        min-width: 900px;
    }
    th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid rgba(212, 175, 55, 0.15);
        white-space: nowrap;
    }
    th { color: #d4af37; font-weight: 600; }
    tr:hover { background: rgba(212, 175, 55, 0.05); }

    .btn-outline {
        display: inline-block;
        padding: 5px 12px;
        border: 1px solid #d4af37;
        color: #d4af37;
        text-decoration: none;
        border-radius: 5px;
        font-size: 0.75rem;
        transition: all 0.3s;
    }
    .btn-outline:hover {
        background: #d4af37;
        color: #050505;
    }

    .form-control-sm {
        padding: 5px 8px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 5px;
        color: white;
        font-size: 0.8rem;
        width: 100px;
        display: inline-block;
    }
    .form-control-sm:focus {
        outline: none;
        border-color: #d4af37;
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

    .action-cell { display: flex; gap: 0.5rem; flex-wrap: wrap; }

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
        table { min-width: 700px; font-size: 0.85rem; }
        th, td { padding: 10px; }
    }

    @media (max-width: 768px) {
        .main-content { padding: 1rem; }
        .section-title { font-size: 1.1rem; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 1rem; }
        .stat-card { padding: 1rem; }
        .stat-card .number { font-size: 1.5rem; }
        table { min-width: 600px; font-size: 0.8rem; }
        th, td { padding: 8px; white-space: nowrap; }
        .action-cell { flex-direction: column; }
        .action-cell .btn-outline { width: 100%; text-align: center; }
        .form-control-sm { width: 100%; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .section-title { font-size: 1rem; }
        .stats-grid { grid-template-columns: 1fr; }
        table { min-width: 500px; font-size: 0.7rem; }
        th, td { padding: 6px; }
    }
</style>

<div class="main-content">

    <h1 class="section-title">💰 Payment Management</h1>

    <?php if(isset($success)): ?>
        <div class="alert alert-success">✅ <?php echo $success; ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number">KSh <?php echo number_format($total_paid, 2); ?></div>
            <div class="label">Total Paid</div>
        </div>
        <div class="stat-card">
            <div class="number">KSh <?php echo number_format($total_pending, 2); ?></div>
            <div class="label">Pending Payments</div>
        </div>
        <div class="stat-card">
            <div class="number">📱 <?php echo $mpesa_count; ?></div>
            <div class="label">M-PESA Payments</div>
        </div>
        <div class="stat-card">
            <div class="number">💵 <?php echo $cash_count; ?></div>
            <div class="label">Cash Payments</div>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Service</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Transaction Code</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if($payments && mysqli_num_rows($payments) > 0): ?>
                    <?php while($payment = mysqli_fetch_assoc($payments)): ?>
                    <tr>
                        <td><?php echo $payment['id']; ?></td>
                        <td><?php echo htmlspecialchars($payment['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars($payment['service_name']); ?></td>
                        <td>KSh <?php echo number_format($payment['amount'], 2); ?></td>
                        <td><?php echo strtoupper($payment['payment_method']); ?></td>
                        <td><?php echo htmlspecialchars($payment['transaction_code'] ?? 'N/A'); ?></td>
                        <td>
                            <span style="color: <?php echo $payment['payment_status'] == 'paid' ? '#28a745' : '#d4af37'; ?>">
                                <?php echo ucfirst($payment['payment_status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y H:i', strtotime($payment['payment_date'])); ?></td>
                        <td class="action-cell">
                            <?php if($payment['payment_status'] != 'paid'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                <select name="payment_status" class="form-control-sm">
                                    <option value="pending">Pending</option>
                                    <option value="paid">Paid</option>
                                    <option value="failed">Failed</option>
                                </select>
                                <input type="text" name="transaction_code" placeholder="M-PESA Code" class="form-control-sm">
                                <button type="submit" name="update_payment" class="btn-outline">Update</button>
                            </form>
                            <?php else: ?>
                                <span style="color: #28a745;">✓ Completed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px;">
                            💰 No payments found for your salon.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<?php include '../includes/footer.php'; ?>