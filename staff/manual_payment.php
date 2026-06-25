<?php
// staff/manual_payment.php - UPDATED with new hamburger sidebar layout
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

// Get salon_id from session
$salon_id = $_SESSION['salon_id'] ?? 0;
if ($salon_id <= 0) {
    $user_query = mysqli_query($conn, "SELECT salon_id FROM users WHERE id = $staff_id");
    if ($user_result = mysqli_fetch_assoc($user_query)) {
        $salon_id = $user_result['salon_id'];
        $_SESSION['salon_id'] = $salon_id;
    }
}

// Handle marking payment as paid
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_paid'])) {
    $appointment_id = mysqli_real_escape_string($conn, $_POST['appointment_id']);
    $transaction_code = mysqli_real_escape_string($conn, $_POST['transaction_code'] ?? 'CASH-' . date('YmdHis'));
    
    // Verify appointment belongs to this salon
    $check = mysqli_query($conn, "SELECT a.id, a.customer_id, a.price, s.service_name, c.full_name, c.phone, c.email 
                                  FROM appointments a 
                                  JOIN services s ON a.service_id = s.id 
                                  JOIN users c ON a.customer_id = c.id 
                                  WHERE a.id = $appointment_id AND a.salon_id = $salon_id AND a.payment_method = 'cash'");
    
    if (mysqli_num_rows($check) == 1) {
        $apt = mysqli_fetch_assoc($check);
        
        // Update payment
        $update_payment = "UPDATE payments p 
                           JOIN appointments a ON p.appointment_id = a.id 
                           SET p.payment_status = 'paid', p.transaction_code = '$transaction_code'
                           WHERE a.id = $appointment_id AND a.salon_id = $salon_id";
        
        if (mysqli_query($conn, $update_payment)) {
            // Update appointment payment status
            mysqli_query($conn, "UPDATE appointments SET payment_status = 'paid' WHERE id = $appointment_id AND salon_id = $salon_id");
            
            // Send notification to customer
            $message = "Dear {$apt['full_name']}, your cash payment of KSh {$apt['price']} for {$apt['service_name']} has been received. Thank you for choosing Salon Pro!";
            sendNotification($apt['customer_id'], "Payment Received", $message);
            sendSMS($apt['phone'], $message);
            sendEmail($apt['email'], "Payment Confirmation - Salon Pro", $message);
            
            $success = "Payment marked as paid successfully!";
        } else {
            $error = "Failed to update payment: " . mysqli_error($conn);
        }
    } else {
        $error = "Appointment not found or does not belong to your salon.";
    }
}

// Get all cash appointments with pending payment for this salon
$pending_query = "SELECT a.*, c.full_name as customer_name, c.phone as customer_phone, s.service_name, s.price 
                  FROM appointments a 
                  JOIN users c ON a.customer_id = c.id 
                  JOIN services s ON a.service_id = s.id 
                  WHERE a.payment_method = 'cash' 
                  AND a.payment_status = 'pending'
                  AND a.salon_id = $salon_id
                  ORDER BY a.appointment_date ASC, a.appointment_time ASC";
$pending_appointments = mysqli_query($conn, $pending_query);

// Get today's cash appointments that need payment for this salon
$today = date('Y-m-d');
$today_cash_query = "SELECT a.*, c.full_name as customer_name, c.phone as customer_phone, s.service_name, s.price 
                     FROM appointments a 
                     JOIN users c ON a.customer_id = c.id 
                     JOIN services s ON a.service_id = s.id 
                     WHERE a.payment_method = 'cash' 
                     AND a.payment_status = 'pending' 
                     AND a.appointment_date = '$today'
                     AND a.salon_id = $salon_id
                     ORDER BY a.appointment_time ASC";
$today_cash = mysqli_query($conn, $today_cash_query);

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
        min-width: 700px;
    }
    th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid rgba(212, 175, 55, 0.15);
        white-space: nowrap;
    }
    th { color: #d4af37; font-weight: 600; }
    tr:hover { background: rgba(212, 175, 55, 0.05); }

    .btn-primary {
        background: #d4af37;
        color: #050505;
        border: none;
        padding: 8px 16px;
        border-radius: 25px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
        font-size: 0.85rem;
    }
    .btn-primary:hover {
        background: #f9e547;
        transform: translateY(-2px);
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

    .action-cell { display: flex; gap: 0.5rem; flex-wrap: wrap; }
    .action-cell .btn-primary { width: 100%; }

    .back-link {
        display: inline-block;
        margin-top: 1.5rem;
        color: #d4af37;
        text-decoration: none;
    }
    .back-link:hover {
        text-decoration: underline;
    }

    .status-paid { color: #28a745; font-weight: bold; }
    .status-pending { color: #d4af37; font-weight: bold; }

    /* ============================================
       RESPONSIVE
       ============================================ */
    @media (max-width: 1024px) {
        table { min-width: 600px; font-size: 0.85rem; }
        th, td { padding: 10px; }
    }

    @media (max-width: 768px) {
        .main-content { padding: 1rem; }
        .section-title { font-size: 1.1rem; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 1rem; }
        .stat-card { padding: 1rem; }
        .stat-card .number { font-size: 1.5rem; }
        table { min-width: 500px; font-size: 0.8rem; }
        th, td { padding: 8px; white-space: nowrap; }
        .action-cell { flex-direction: column; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .section-title { font-size: 1rem; }
        .stats-grid { grid-template-columns: 1fr; }
        table { min-width: 400px; font-size: 0.7rem; }
        th, td { padding: 6px; }
    }

    /* Modal Styles */
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
    .modal-buttons {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
    }
    .btn-cancel {
        background: #dc3545;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 5px;
        cursor: pointer;
        flex: 1;
    }
    .btn-cancel:hover { background: #c82333; }
    .btn-confirm {
        background: #d4af37;
        color: #050505;
        border: none;
        padding: 10px 20px;
        border-radius: 5px;
        cursor: pointer;
        flex: 1;
        font-weight: 600;
    }
    .btn-confirm:hover { background: #f9e547; }
</style>

<div class="main-content">

    <h1 class="section-title">💵 Manual Cash Payment</h1>

    <?php if($error): ?>
        <div class="alert alert-danger">❌ <?php echo $error; ?></div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="alert alert-success">✅ <?php echo $success; ?></div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number"><?php echo mysqli_num_rows($pending_appointments); ?></div>
            <div class="label">Pending Cash Payments</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo mysqli_num_rows($today_cash); ?></div>
            <div class="label">Today's Pending</div>
        </div>
    </div>

    <!-- Today's Cash Appointments -->
    <h2 class="section-title" style="font-size: 1.1rem; margin-top: 0;">📅 Today's Pending Cash Payments</h2>
    <div class="table-wrapper">
        <table>
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
                        <td class="action-cell">
                            <button class="btn-primary" onclick="openPaymentModal(<?php echo $apt['id']; ?>, '<?php echo htmlspecialchars($apt['customer_name']); ?>', <?php echo $apt['price']; ?>)">
                                💵 Mark as Paid
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 30px;">✨ No pending cash payments for today! ✨</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- All Pending Cash Appointments -->
    <h2 class="section-title" style="font-size: 1.1rem; margin-top: 1.5rem;">📋 All Pending Cash Payments</h2>
    <div class="table-wrapper">
        <table>
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
                        <td class="action-cell">
                            <button class="btn-primary" onclick="openPaymentModal(<?php echo $apt['id']; ?>, '<?php echo htmlspecialchars($apt['customer_name']); ?>', <?php echo $apt['price']; ?>)">
                                💵 Mark as Paid
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 30px;">🎉 No pending cash payments! All caught up! 🎉</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

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
                <button type="submit" name="mark_paid" class="btn-confirm">✓ Confirm Payment</button>
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