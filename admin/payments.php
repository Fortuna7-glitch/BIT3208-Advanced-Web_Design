<?php
// admin/payments.php - FIXED with salon_id filtering
require_once '../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$salon_id = $_SESSION['salon_id'] ?? 0;

// Handle payment status update
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
    
    redirect('payments.php');
}

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
    .dashboard-container { display: flex; min-height: 100vh; }
    .sidebar { width: 280px; background: #050505; border-right: 1px solid #d4af37; padding: 2rem 1rem; }
    .sidebar-menu { list-style: none; padding: 0; }
    .sidebar-menu li { margin-bottom: 0.5rem; }
    .sidebar-menu a { display: block; padding: 12px 20px; color: white; text-decoration: none; border-radius: 10px; transition: all 0.3s; }
    .sidebar-menu a:hover, .sidebar-menu a.active { background: #d4af37; color: #050505; }
    .main-content { flex: 1; padding: 2rem; background: #0a0a0a; }
    
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
    .stat-card { background: #1a1a1a; border-radius: 15px; padding: 1.5rem; text-align: center; border-left: 4px solid #d4af37; }
    .stat-number { font-size: 2rem; font-weight: bold; color: #d4af37; }
    
    .table-container { overflow-x: auto; background: #1a1a1a; border-radius: 15px; padding: 1rem; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid rgba(212, 175, 55, 0.2); }
    th { color: #d4af37; }
    .btn-outline { display: inline-block; padding: 5px 10px; border: 1px solid #d4af37; color: #d4af37; text-decoration: none; border-radius: 5px; }
    .btn-outline:hover { background: #d4af37; color: #050505; }
    
    .form-control { padding: 5px 8px; background: #2a2a2a; border: 1px solid rgba(212, 175, 55, 0.3); border-radius: 5px; color: white; }
    select.form-control { width: 100px; display: inline-block; }
    input.form-control { width: 120px; display: inline-block; }
    
    h1 { color: #d4af37; }
    @media (max-width: 768px) { .dashboard-container { flex-direction: column; } .sidebar { width: 100%; } }
</style>

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
        ?>
        
        <div class="stats-grid">
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
                            <td>
                                <?php if($payment['payment_status'] != 'paid'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                    <select name="payment_status" class="form-control" style="width: 100px;">
                                        <option value="pending">Pending</option>
                                        <option value="paid">Paid</option>
                                        <option value="failed">Failed</option>
                                    </select>
                                    <input type="text" name="transaction_code" placeholder="M-PESA Code" class="form-control" style="width: 120px;">
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
                            <td colspan="9" style="text-align: center;">No payments found for your salon.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>