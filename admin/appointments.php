<?php
// admin/appointments.php - UPDATED with new hamburger sidebar layout
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

// Handle Serve/Cancel
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $appointment_id = mysqli_real_escape_string($conn, $_POST['appointment_id']);
    
    if ($_POST['action'] == 'serve') {
        $query = "UPDATE appointments SET status = 'served' WHERE id = $appointment_id AND salon_id = $salon_id";
        if (mysqli_query($conn, $query)) {
            $apt_query = "SELECT a.*, u.full_name, u.email, u.phone FROM appointments a JOIN users u ON a.customer_id = u.id WHERE a.id = $appointment_id AND a.salon_id = $salon_id";
            $apt_result = mysqli_query($conn, $apt_query);
            if ($apt_result && $apt = mysqli_fetch_assoc($apt_result)) {
                sendNotification($apt['customer_id'], "Service Completed", "Your service has been completed. Thank you for choosing Salon Pro!", 'email');
                sendSMS($apt['phone'], "Salon Pro: Your appointment has been completed. Thank you!");
            }
        }
    } elseif ($_POST['action'] == 'cancel') {
        $query = "UPDATE appointments SET status = 'cancelled' WHERE id = $appointment_id AND salon_id = $salon_id";
        mysqli_query($conn, $query);
    }
    redirect('appointments.php');
}

// Get appointments
$appointments = mysqli_query($conn, "SELECT a.*, c.full_name as customer_name, s.service_name, st.full_name as staff_name 
                                    FROM appointments a 
                                    JOIN users c ON a.customer_id = c.id 
                                    JOIN services s ON a.service_id = s.id 
                                    LEFT JOIN users st ON a.staff_id = st.id 
                                    WHERE a.salon_id = $salon_id
                                    ORDER BY a.appointment_date DESC, a.appointment_time DESC");

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

    .btn-serve {
        background: #28a745;
        color: white;
        border: none;
        padding: 5px 12px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.7rem;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
    }
    .btn-serve:hover {
        background: #218838;
        transform: scale(1.05);
    }

    .btn-cancel {
        background: #dc3545;
        color: white;
        border: none;
        padding: 5px 12px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.7rem;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
    }
    .btn-cancel:hover {
        background: #c82333;
        transform: scale(1.05);
    }

    .status-served { color: #28a745; font-weight: bold; }
    .status-cancelled { color: #dc3545; font-weight: bold; }
    .status-pending { color: #d4af37; font-weight: bold; }
    .status-confirmed { color: #d4af37; font-weight: bold; }

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
        table { min-width: 600px; font-size: 0.85rem; }
        th, td { padding: 10px; }
    }

    @media (max-width: 768px) {
        .main-content { padding: 1rem; }
        .section-title { font-size: 1.1rem; }
        table { min-width: 500px; font-size: 0.8rem; }
        th, td { padding: 8px; white-space: nowrap; }
        .action-cell { display: flex; flex-direction: column; gap: 5px; }
        .action-cell .btn-serve,
        .action-cell .btn-cancel { width: 100%; text-align: center; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .section-title { font-size: 1rem; }
        table { min-width: 400px; font-size: 0.7rem; }
        th, td { padding: 6px; }
    }
</style>

<div class="main-content">

    <h1 class="section-title">📅 All Appointments</h1>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Service</th>
                    <th>Staff</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Queue Pos</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if($appointments && mysqli_num_rows($appointments) > 0): ?>
                    <?php while($apt = mysqli_fetch_assoc($appointments)): ?>
                    <tr>
                        <td><?php echo $apt['id']; ?></td>
                        <td><?php echo htmlspecialchars($apt['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars($apt['service_name']); ?></td>
                        <td><?php echo htmlspecialchars($apt['staff_name'] ?? 'Not Assigned'); ?></td>
                        <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                        <td><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></td>
                        <td>
                            <span class="status-<?php echo $apt['status']; ?>">
                                <?php echo ucfirst($apt['status']); ?>
                            </span>
                        </td>
                        <td><?php echo $apt['queue_position'] ?? '-'; ?></td>
                        <td class="action-cell">
                            <?php if($apt['status'] != 'served' && $apt['status'] != 'cancelled'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                    <input type="hidden" name="action" value="serve">
                                    <button type="submit" class="btn-serve" onclick="return confirm('Mark this customer as served?')">✅ Serve</button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <button type="submit" class="btn-cancel" onclick="return confirm('Cancel this appointment?')">❌ Cancel</button>
                                </form>
                            <?php elseif($apt['status'] == 'served'): ?>
                                <span style="color: #28a745;">✅ Completed</span>
                            <?php elseif($apt['status'] == 'cancelled'): ?>
                                <span style="color: #dc3545;">❌ Cancelled</span>
                            <?php else: ?>
                                <span>—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px;">
                            📭 No appointments found for your salon.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<?php include '../includes/footer.php'; ?>