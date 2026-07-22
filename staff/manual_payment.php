<?php
// staff/manual_payment.php - MODIFIED: Permission check for accept_cash
require_once '../config/database.php';
require_once '../includes/permissions.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

$staff_id = $_SESSION['user_id'];

// ============================================
// PERMISSION CHECK
// ============================================
if (!hasPermission($staff_id, 'accept_cash')) {
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
            <p style="color: #aaa;">You don't have permission to process cash payments.</p>
            <a href="dashboard.php" style="display: inline-block; margin-top: 1rem; padding: 10px 25px; background: #d4af37; color: #050505; border-radius: 25px; text-decoration: none; font-weight: 600;">Back to Dashboard</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Get salon_id from session
$salon_id = $_SESSION['salon_id'] ?? 0;
if ($salon_id <= 0) {
    $user_query = mysqli_query($conn, "SELECT salon_id FROM users WHERE id = $staff_id");
    if ($user_result = mysqli_fetch_assoc($user_query)) {
        $salon_id = $user_result['salon_id'];
        $_SESSION['salon_id'] = $salon_id;
    }
}

$error = '';
$success = '';

// ============================================
// HANDLE MARKING PAYMENT AS PAID
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_paid'])) {
    // Double-check permission
    if (!hasPermission($staff_id, 'accept_cash')) {
        $error = "You don't have permission to process cash payments.";
    } else {
        $appointment_id = mysqli_real_escape_string($conn, $_POST['appointment_id']);
        $transaction_code = mysqli_real_escape_string($conn, $_POST['transaction_code'] ?? 'CASH-' . date('YmdHis'));
        
        // Verify appointment belongs to this salon
        $check = mysqli_query($conn, "SELECT a.id, a.customer_id, s.price, s.service_name, c.full_name, c.phone, c.email 
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
}

// ============================================
// GET PENDING CASH APPOINTMENTS
// ============================================
$today = date('Y-m-d');

// Today's cash appointments
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

// All pending cash appointments
$pending_query = "SELECT a.*, c.full_name as customer_name, c.phone as customer_phone, s.service_name, s.price 
                  FROM appointments a 
                  JOIN users c ON a.customer_id = c.id 
                  JOIN services s ON a.service_id = s.id 
                  WHERE a.payment_method = 'cash' 
                  AND a.payment_status = 'pending'
                  AND a.salon_id = $salon_id
                  ORDER BY a.appointment_date ASC, a.appointment_time ASC";
$pending_appointments = mysqli_query($conn, $pending_query);

include '../includes/header.php';
?>

<style>
    .main-content {
        padding: 2rem;
        background: #0a0a0a;
        min-height: 100vh;
    }

    /* ============================================
       HEADER WITH QUICK ACTIONS & SEARCH
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

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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

    /* Table */
    .table-wrapper {
        overflow-x: auto;
        background: #1a1a1a;
        border-radius: 15px;
        padding: 0;
        border: 1px solid rgba(212, 175, 55, 0.2);
        -webkit-overflow-scrolling: touch;
        margin-bottom: 1.5rem;
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

    .btn-primary {
        background: #d4af37;
        color: #050505;
        border: none;
        padding: 6px 16px;
        border-radius: 25px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
        font-size: 0.75rem;
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

    .action-cell {
        display: flex;
        gap: 0.4rem;
        flex-wrap: wrap;
    }

    .empty-state {
        text-align: center;
        padding: 2rem 0;
        color: #666;
    }

    .empty-state .icon {
        font-size: 2rem;
        margin-bottom: 0.5rem;
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

    .modal-content h3 {
        color: #d4af37;
        margin-bottom: 1rem;
    }

    .modal-content .form-group {
        margin-bottom: 1rem;
    }

    .modal-content .form-group label {
        display: block;
        color: #d4af37;
        margin-bottom: 0.3rem;
        font-size: 0.9rem;
    }

    .modal-content .form-control {
        width: 100%;
        padding: 10px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 8px;
        color: white;
    }

    .modal-buttons {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
    }

    .btn-cancel {
        flex: 1;
        padding: 10px;
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }

    .btn-cancel:hover {
        background: #c82333;
    }

    .btn-confirm {
        flex: 1;
        padding: 10px;
        background: #d4af37;
        color: #050505;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-weight: 600;
    }

    .btn-confirm:hover {
        background: #f9e547;
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
    @media (max-width: 1024px) {
        table { min-width: 600px; font-size: 0.85rem; }
        th, td { padding: 10px; }
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
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 0.8rem; }
        table { min-width: 500px; font-size: 0.8rem; }
        th, td { padding: 8px; }
        .action-cell { flex-direction: column; }
        .action-cell .btn-primary { width: 100%; text-align: center; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .page-header .quick-actions .quick-btn { font-size: 0.65rem; padding: 6px 12px; }
        table { min-width: 400px; font-size: 0.7rem; }
        th, td { padding: 6px; }
    }
</style>

<div class="main-content">

    <!-- ============================================
       HEADER: Title + Quick Actions
       ============================================ -->
    <div class="page-header">
        <div class="title-section">
            <h1>💵 Manual Cash Payment</h1>
            <p>Process cash payments for appointments</p>
        </div>
        <div class="quick-actions">
            <a href="appointments.php" class="quick-btn"><i class="fas fa-list"></i> Appointments</a>
            <a href="dashboard.php" class="quick-btn"><i class="fas fa-home"></i> Dashboard</a>
        </div>
    </div>

    <!-- ============================================
       ALERTS
       ============================================ -->
    <?php if($error): ?>
        <div class="alert alert-danger">❌ <?php echo $error; ?></div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="alert alert-success">✅ <?php echo $success; ?></div>
    <?php endif; ?>

    <!-- ============================================
       STATISTICS
       ============================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo mysqli_num_rows($pending_appointments); ?></div>
            <div class="stat-label">Pending Cash Payments</div>
        </div>
        <div class="stat-card" style="border-left-color: #17a2b8;">
            <div class="stat-number" style="color: #17a2b8;"><?php echo mysqli_num_rows($today_cash); ?></div>
            <div class="stat-label">Today's Pending</div>
        </div>
    </div>

    <!-- ============================================
       TODAY'S CASH APPOINTMENTS
       ============================================ -->
    <h2 class="section-title">📅 Today's Pending Cash Payments</h2>
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
                        <td colspan="6" style="text-align: center; padding: 30px;">
                            <div class="empty-state">
                                <div class="icon">✨</div>
                                <p>No pending cash payments for today!</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ============================================
       ALL PENDING CASH APPOINTMENTS
       ============================================ -->
    <h2 class="section-title">📋 All Pending Cash Payments</h2>
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
                        <td colspan="7" style="text-align: center; padding: 30px;">
                            <div class="empty-state">
                                <div class="icon">🎉</div>
                                <p>No pending cash payments! All caught up!</p>
                            </div>
                        </td>
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