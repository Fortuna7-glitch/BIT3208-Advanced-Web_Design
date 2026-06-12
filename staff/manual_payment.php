<?php
// staff/manual_payment.php - Staff marks cash payments as paid
require_once '../config/database.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

// Check if staff has this permission
if (!hasPermission($_SESSION['user_id'], 'manual_cash_payment')) {
    redirect('dashboard.php');
}

$staff_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle marking payment as paid
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_paid'])) {
    $appointment_id = mysqli_real_escape_string($conn, $_POST['appointment_id']);
    $transaction_code = mysqli_real_escape_string($conn, $_POST['transaction_code'] ?? 'CASH-' . date('YmdHis'));
    
    // Update payment status
    $update_payment = "UPDATE payments p 
                       JOIN appointments a ON p.appointment_id = a.id 
                       SET p.payment_status = 'paid', p.transaction_code = '$transaction_code'
                       WHERE a.id = $appointment_id AND a.payment_method = 'cash'";
    
    if (mysqli_query($conn, $update_payment)) {
        // Update appointment payment status
        mysqli_query($conn, "UPDATE appointments SET payment_status = 'paid' WHERE id = $appointment_id");
        
        // Get customer info for notification
        $apt_query = "SELECT a.*, c.full_name, c.phone, c.email, s.service_name 
                      FROM appointments a 
                      JOIN users c ON a.customer_id = c.id 
                      JOIN services s ON a.service_id = s.id 
                      WHERE a.id = $appointment_id";
        $apt_result = mysqli_query($conn, $apt_query);
        $apt = mysqli_fetch_assoc($apt_result);
        
        // Send notification to customer
        $message = "Dear {$apt['full_name']}, your cash payment of KSh {$apt['price']} for {$apt['service_name']} has been received. Thank you for choosing Salon Pro!";
        sendNotification($apt['customer_id'], "Payment Received", $message);
        sendSMS($apt['phone'], $message);
        sendEmail($apt['email'], "Payment Confirmation - Salon Pro", $message);
        
        $success = "Payment marked as paid successfully!";
    } else {
        $error = "Failed to update payment: " . mysqli_error($conn);
    }
}

// Get all cash appointments with pending payment
$pending_query = "SELECT a.*, c.full_name as customer_name, c.phone as customer_phone, s.service_name, s.price 
                  FROM appointments a 
                  JOIN users c ON a.customer_id = c.id 
                  JOIN services s ON a.service_id = s.id 
                  WHERE a.payment_method = 'cash' AND a.payment_status = 'pending'
                  ORDER BY a.appointment_date ASC, a.appointment_time ASC";
$pending_appointments = mysqli_query($conn, $pending_query);

// Get today's cash appointments that need payment
$today = date('Y-m-d');
$today_cash_query = "SELECT a.*, c.full_name as customer_name, c.phone as customer_phone, s.service_name, s.price 
                     FROM appointments a 
                     JOIN users c ON a.customer_id = c.id 
                     JOIN services s ON a.service_id = s.id 
                     WHERE a.payment_method = 'cash' AND a.payment_status = 'pending' AND a.appointment_date = '$today'
                     ORDER BY a.appointment_time ASC";
$today_cash = mysqli_query($conn, $today_cash_query);

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
    
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
    .stat-card { background: #1a1a1a; border-radius: 15px; padding: 1.5rem; text-align: center; border-left: 4px solid #d4af37; }
    .stat-number { font-size: 2rem; font-weight: bold; color: #d4af37; }
    
    .table-wrapper {
        overflow-x: auto;
        background: #1a1a1a;
        border-radius: 15px;
        padding: 0;
        margin-top: 1rem;
        border: 1px solid rgba(212, 175, 55, 0.2);
    }
    .payments-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }
    .payments-table th {
        background: #2a2a2a;
        color: #d4af37;
        padding: 14px 12px;
        text-align: left;
        font-weight: 600;
        border-bottom: 2px solid #d4af37;
    }
    .payments-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid rgba(212, 175, 55, 0.15);
        color: #ffffff;
    }
    .payments-table tr:hover {
        background: rgba(212, 175, 55, 0.05);
    }
    
    .btn-primary {
        background: #d4af37;
        color: #050505;
        border: none;
        padding: 8px 20px;
        border-radius: 25px;
        cursor: pointer;
        font-weight: 600;
    }
    .btn-primary:hover { background: #f9e547; }
    
    .status-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    .status-paid { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }
    .status-pending { background: rgba(212, 175, 55, 0.2); color: #d4af37; border: 1px solid #d4af37; }
    
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 1rem; }
    .alert-success { background: rgba(40, 167, 69, 0.2); border: 1px solid #28a745; color: #28a745; }
    .alert-danger { background: rgba(220, 53, 69, 0.2); border: 1px solid #dc3545; color: #dc3545; }
    
    h1, h2 { color: #d4af37; margin-bottom: 1rem; }
    h2 { font-size: 1.3rem; margin-top: 1.5rem; }
    
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.8);
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }
    .modal-content {
        background: #1a1a1a;
        padding: 2rem;
        border-radius: 15px;
        max-width: 400px;
        width: 90%;
        border: 1px solid #d4af37;
    }
    .modal-content h3 { color: #d4af37; margin-bottom: 1rem; }
    .modal-buttons { display: flex; gap: 1rem; margin-top: 1.5rem; }
    .btn-cancel { background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
    
    @media (max-width: 768px) { 
        .staff-container { flex-direction: column; } 
        .sidebar { width: 100%; }
        .payments-table th, .payments-table td { padding: 8px; font-size: 0.75rem; }
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
                <li><a href="manual_payment.php" class="active">💵 Manual Cash Payment</a></li>
            <?php endif; ?>
            <li><a href="profile.php">⚙️ My Profile</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <h1>💵 Manual Cash Payment</h1>
        
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo mysqli_num_rows($pending_appointments); ?></div>
                <p>Pending Cash Payments</p>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo mysqli_num_rows($today_cash); ?></div>
                <p>Today's Pending</p>
            </div>
        </div>
        
        <!-- Today's Cash Appointments -->
        <h2>📅 Today's Pending Cash Payments</h2>
        <div class="table-wrapper">
            <table class="payments-table">
                <thead>
                    <tr>
                        <th>⏰ Time</th>
                        <th>👤 Customer</th>
                        <th>💇 Service</th>
                        <th>💰 Amount</th>
                        <th>📞 Phone</th>
                        <th>⚡ Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($today_cash && mysqli_num_rows($today_cash) > 0): ?>
                        <?php while($apt = mysqli_fetch_assoc($today_cash)): ?>
                        <tr>
                            <td><strong><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></strong></td>
                            <td><?php echo htmlspecialchars($apt['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($apt['service_name']); ?></td>
                            <td>KSh <?php echo number_format($apt['price'], 2); ?></td>
                            <td><?php echo htmlspecialchars($apt['customer_phone']); ?></td>
                            <td>
                                <button class="btn-primary" onclick="openPaymentModal(<?php echo $apt['id']; ?>, '<?php echo htmlspecialchars($apt['customer_name']); ?>', <?php echo $apt['price']; ?>)">
                                    💵 Mark as Paid
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center; padding: 40px;">✨ No pending cash payments for today! ✨</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- All Pending Cash Appointments -->
        <h2>📋 All Pending Cash Payments</h2>
        <div class="table-wrapper">
            <table class="payments-table">
                <thead>
                    <tr>
                        <th>📅 Date</th>
                        <th>⏰ Time</th>
                        <th>👤 Customer</th>
                        <th>💇 Service</th>
                        <th>💰 Amount</th>
                        <th>📞 Phone</th>
                        <th>⚡ Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($pending_appointments && mysqli_num_rows($pending_appointments) > 0): ?>
                        <?php while($apt = mysqli_fetch_assoc($pending_appointments)): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                            <td><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></td>
                            <td><?php echo htmlspecialchars($apt['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($apt['service_name']); ?></td>
                            <td>KSh <?php echo number_format($apt['price'], 2); ?></td>
                            <td><?php echo htmlspecialchars($apt['customer_phone']); ?></td>
                            <td>
                                <button class="btn-primary" onclick="openPaymentModal(<?php echo $apt['id']; ?>, '<?php echo htmlspecialchars($apt['customer_name']); ?>', <?php echo $apt['price']; ?>)">
                                    💵 Mark as Paid
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align: center; padding: 40px;">🎉 No pending cash payments! All caught up! 🎉</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
        <h3>💵 Confirm Cash Payment</h3>
        <p id="modalCustomerName"></p>
        <p id="modalAmount"></p>
        <form method="POST">
            <input type="hidden" name="appointment_id" id="modalAppointmentId">
            <div class="form-group">
                <label>Transaction Reference (Optional)</label>
                <input type="text" name="transaction_code" class="form-control" placeholder="CASH-001 or receipt number">
            </div>
            <div class="modal-buttons">
                <button type="button" class="btn-cancel" onclick="closePaymentModal()">Cancel</button>
                <button type="submit" name="mark_paid" class="btn-primary">✓ Confirm Payment</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openPaymentModal(appointmentId, customerName, amount) {
        document.getElementById('modalAppointmentId').value = appointmentId;
        document.getElementById('modalCustomerName').innerHTML = '<strong>Customer:</strong> ' + customerName;
        document.getElementById('modalAmount').innerHTML = '<strong>Amount:</strong> KSh ' + amount.toLocaleString('en-KE', {minimumFractionDigits: 2});
        document.getElementById('paymentModal').style.display = 'flex';
    }
    
    function closePaymentModal() {
        document.getElementById('paymentModal').style.display = 'none';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target == document.getElementById('paymentModal')) {
            closePaymentModal();
        }
    }
</script>

<?php include '../includes/footer.php'; ?>