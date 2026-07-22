<?php
// staff/appointments.php - MODIFIED: Permission-based action buttons
require_once '../config/database.php';
require_once '../includes/permissions.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

$staff_id = $_SESSION['user_id'];

// ============================================
// HANDLE ACTIONS (Permission-based)
// ============================================

// Serve action - requires 'serve_appointments' or 'mark_completed' permission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'serve') {
    if (!hasPermission($staff_id, 'serve_appointments') && !hasPermission($staff_id, 'mark_completed')) {
        $error = "You don't have permission to mark appointments as served.";
    } else {
        $appointment_id = mysqli_real_escape_string($conn, $_POST['appointment_id']);
        $query = "UPDATE appointments SET status = 'served' WHERE id = $appointment_id AND staff_id = $staff_id";
        if (mysqli_query($conn, $query)) {
            $success = "Appointment marked as served!";
            
            // Get customer info for notification
            $apt_query = "SELECT a.*, u.full_name, u.email, u.phone FROM appointments a 
                          JOIN users u ON a.customer_id = u.id WHERE a.id = $appointment_id";
            $apt_result = mysqli_query($conn, $apt_query);
            if ($apt = mysqli_fetch_assoc($apt_result)) {
                sendNotification($apt['customer_id'], "Service Completed", "Your service has been completed. Thank you for choosing Salon Pro!", 'email');
                sendSMS($apt['phone'], "Salon Pro: Your appointment has been completed. Thank you!");
            }
        } else {
            $error = "Failed to mark as served: " . mysqli_error($conn);
        }
    }
}

// Cancel action - requires 'cancel_appointments' permission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'cancel') {
    if (!hasPermission($staff_id, 'cancel_appointments')) {
        $error = "You don't have permission to cancel appointments.";
    } else {
        $appointment_id = mysqli_real_escape_string($conn, $_POST['appointment_id']);
        $query = "UPDATE appointments SET status = 'cancelled' WHERE id = $appointment_id AND staff_id = $staff_id";
        if (mysqli_query($conn, $query)) {
            $success = "Appointment cancelled successfully!";
        } else {
            $error = "Failed to cancel appointment: " . mysqli_error($conn);
        }
    }
}

// ============================================
// GET APPOINTMENTS
// ============================================
$appointments_query = "SELECT a.*, c.full_name as customer_name, c.phone as customer_phone, s.service_name, s.price 
                       FROM appointments a 
                       JOIN users c ON a.customer_id = c.id 
                       JOIN services s ON a.service_id = s.id 
                       WHERE a.staff_id = $staff_id 
                       ORDER BY a.appointment_date DESC, a.appointment_time DESC";
$appointments = mysqli_query($conn, $appointments_query);

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

    /* ============================================
       TABLE
       ============================================ */
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

    th {
        color: #d4af37;
        font-weight: 600;
    }

    tr:hover {
        background: rgba(212, 175, 55, 0.05);
    }

    /* Status Badges */
    .status-badge {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
    }

    .status-badge.served { background: rgba(40, 167, 69, 0.15); color: #28a745; border: 1px solid #28a745; }
    .status-badge.cancelled { background: rgba(220, 53, 69, 0.15); color: #dc3545; border: 1px solid #dc3545; }
    .status-badge.pending { background: rgba(212, 175, 55, 0.15); color: #d4af37; border: 1px solid #d4af37; }
    .status-badge.confirmed { background: rgba(23, 162, 184, 0.15); color: #17a2b8; border: 1px solid #17a2b8; }
    .status-badge.completed { background: rgba(40, 167, 69, 0.15); color: #28a745; border: 1px solid #28a745; }

    /* Action Buttons */
    .action-cell {
        display: flex;
        gap: 0.4rem;
        flex-wrap: wrap;
    }

    .action-cell .btn {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
    }

    .btn-serve {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border: 1px solid #28a745;
    }

    .btn-serve:hover {
        background: #28a745;
        color: white;
    }

    .btn-cancel {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
        border: 1px solid #dc3545;
    }

    .btn-cancel:hover {
        background: #dc3545;
        color: white;
    }

    .btn-edit {
        background: rgba(23, 162, 184, 0.15);
        color: #17a2b8;
        border: 1px solid #17a2b8;
    }

    .btn-edit:hover {
        background: #17a2b8;
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

    .btn-disabled {
        opacity: 0.3;
        cursor: not-allowed;
        pointer-events: none;
    }

    /* Alerts */
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

    .empty-state .icon {
        font-size: 3rem;
        margin-bottom: 1rem;
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
        .page-header .search-section { flex: 1; }
        .page-header .search-section input { width: 100%; }
        table { min-width: 500px; font-size: 0.8rem; }
        th, td { padding: 8px; }
        .action-cell { flex-direction: column; }
        .action-cell .btn { width: 100%; text-align: center; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .page-header .quick-actions .quick-btn { font-size: 0.65rem; padding: 6px 12px; }
        table { min-width: 400px; font-size: 0.7rem; }
        th, td { padding: 6px; }
    }
</style>

<div class="main-content">

    <!-- ============================================
       HEADER: Title + Quick Actions + Search
       ============================================ -->
    <div class="page-header">
        <!-- Left: Title -->
        <div class="title-section">
            <h1>📅 My Appointments</h1>
            <p>Manage your assigned appointments</p>
        </div>

        <!-- Center/Right: Quick Actions -->
        <div class="quick-actions">
            <?php if (hasPermission($staff_id, 'create_appointments')): ?>
                <a href="book_for_customer.php" class="quick-btn"><i class="fas fa-plus-circle"></i> Book</a>
            <?php endif; ?>

            <?php if (hasPermission($staff_id, 'view_all_appointments')): ?>
                <a href="../admin/appointments.php" class="quick-btn"><i class="fas fa-list"></i> All</a>
            <?php endif; ?>

            <?php if (hasPermission($staff_id, 'view_reports')): ?>
                <a href="reports.php" class="quick-btn"><i class="fas fa-chart-line"></i> Reports</a>
            <?php endif; ?>
        </div>

        <!-- Right: Search -->
        <div class="search-section">
            <form method="GET" action="" style="display: flex; gap: 0.5rem; width: 100%;">
                <input type="text" name="q" placeholder="🔍 Search appointments..." aria-label="Search">
                <button type="submit" class="search-btn">Search</button>
            </form>
        </div>
    </div>

    <!-- ============================================
       ALERTS
       ============================================ -->
    <?php if(isset($success)): ?>
        <div class="alert alert-success">✅ <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if(isset($error)): ?>
        <div class="alert alert-danger">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- ============================================
       TABLE
       ============================================ -->
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Customer</th>
                    <th>Service</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Queue</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if($appointments && mysqli_num_rows($appointments) > 0): ?>
                    <?php while($apt = mysqli_fetch_assoc($appointments)): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                        <td><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></td>
                        <td><?php echo htmlspecialchars($apt['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars($apt['service_name']); ?></td>
                        <td><?php echo htmlspecialchars($apt['customer_phone']); ?></td>
                        <td>
                            <span class="status-badge <?php echo $apt['status']; ?>">
                                <?php echo ucfirst($apt['status']); ?>
                            </span>
                        </td>
                        <td><?php echo $apt['queue_position'] ?? '-'; ?></td>
                        <td class="action-cell">
                            <!-- View Button - Always Visible -->
                            <a href="../admin/appointments.php?view=<?php echo $apt['id']; ?>" class="btn btn-view">👁️ View</a>

                            <!-- Serve Button -->
                            <?php if (($apt['status'] != 'served' && $apt['status'] != 'cancelled') && (hasPermission($staff_id, 'serve_appointments') || hasPermission($staff_id, 'mark_completed'))): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                    <input type="hidden" name="action" value="serve">
                                    <button type="submit" class="btn btn-serve" onclick="return confirm('Mark this appointment as served?')">✅ Serve</button>
                                </form>
                            <?php elseif($apt['status'] == 'served'): ?>
                                <span style="color: #28a745; font-size: 0.7rem;">✓ Completed</span>
                            <?php endif; ?>

                            <!-- Cancel Button -->
                            <?php if (($apt['status'] != 'served' && $apt['status'] != 'cancelled') && hasPermission($staff_id, 'cancel_appointments')): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <button type="submit" class="btn btn-cancel" onclick="return confirm('Cancel this appointment?')">❌ Cancel</button>
                                </form>
                            <?php elseif($apt['status'] == 'cancelled'): ?>
                                <span style="color: #dc3545; font-size: 0.7rem;">❌ Cancelled</span>
                            <?php endif; ?>

                            <!-- Edit Button -->
                            <?php if (($apt['status'] != 'served' && $apt['status'] != 'cancelled') && hasPermission($staff_id, 'edit_appointments')): ?>
                                <a href="book_for_customer.php?edit=<?php echo $apt['id']; ?>" class="btn btn-edit">✏️ Edit</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            <div class="empty-state">
                                <div class="icon">📭</div>
                                <p>No appointments assigned to you.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php include '../includes/footer.php'; ?>