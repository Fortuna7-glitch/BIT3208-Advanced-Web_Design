<?php
// admin/payments.php
require_once '../config/database.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

// Handle payment status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_payment'])) {
    $payment_id = mysqli_real_escape_string($conn, $_POST['payment_id']);
    $status = mysqli_real_escape_string($conn, $_POST['payment_status']);
    $transaction_code = mysqli_real_escape_string($conn, $_POST['transaction_code']);
    
    $query = "UPDATE payments SET payment_status = '$status', transaction_code = '$transaction_code' WHERE id = $payment_id";
    mysqli_query($conn, $query);
    
    // Also update appointment payment status
    $apt_query = "UPDATE appointments SET payment_status = '$status' WHERE id = (SELECT appointment_id FROM payments WHERE id = $payment_id)";
    mysqli_query($conn, $apt_query);
}

// Get all payments with details
$payments = mysqli_query($conn, "SELECT p.*, u.full_name as customer_name, s.service_name, a.appointment_date 
                                 FROM payments p 
                                 JOIN appointments a ON p.appointment_id = a.id 
                                 JOIN users u ON a.customer_id = u.id 
                                 JOIN services s ON a.service_id = s.id 
                                 ORDER BY p.payment_date DESC");

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
            <li><a href="payments.php" class="active">💰 Payments</a></li>
            <li><a href="reports.php">📈 Reports</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <h1>Payment Management 💰</h1>
        
        <!-- Summary Stats -->
        <?php
        $total_paid = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM payments WHERE payment_status = 'paid'"))['total'] ?? 0;
        $total_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM payments WHERE payment_status = 'pending'"))['total'] ?? 0;
        $mpesa_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM payments WHERE payment_method = 'mpesa'"))['count'] ?? 0;
        $cash_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM payments WHERE payment_method = 'cash'"))['count'] ?? 0;
        ?>
        
        <div class="stats-grid" style="margin-bottom: 2rem;">
            <div class="stat-card">
                <div class="stat-number">KSh <?php echo number_format($total_paid, 2); ?></div>
                <p>Total Paid</p>
            </div>
            <div class="stat-card">
                <div class="stat-number">KSh <?php echo number_format($total_pending, 2); ?></div>
                <p>Pending Payments</p>
            </div>
            <div class="stat-card">
                <div class="stat-number">📱 <?php echo $mpesa_count; ?></div>
                <p>M-PESA Payments</p>
            </div>
            <div class="stat-card">
                <div class="stat-number">💵 <?php echo $cash_count; ?></div>
                <p>Cash Payments</p>
            </div>
        </div>
        
        <div class="table-container">
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
                        <td>
                            <?php if($payment['payment_status'] != 'paid'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                <select name="payment_status" class="form-control" style="width: 100px; display: inline-block;">
                                    <option value="pending">Pending</option>
                                    <option value="paid">Paid</option>
                                    <option value="failed">Failed</option>
                                </select>
                                <input type="text" name="transaction_code" placeholder="M-PESA Code" style="width: 120px; padding: 5px;">
                                <button type="submit" name="update_payment" class="btn btn-outline" style="padding: 5px 10px;">Update</button>
                            </form>
                            <?php else: ?>
                            ✓ Completed
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>