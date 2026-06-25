<?php
// customer/appointments.php - UPDATED with new hamburger sidebar layout
require_once '../config/database.php';

if (!isLoggedIn() || !isCustomer()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];

// Handle cancellation
if (isset($_GET['cancel']) && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['cancel']);
    $query = "UPDATE appointments SET status = 'cancelled' WHERE id = $id AND customer_id = $user_id";
    if (mysqli_query($conn, $query)) {
        header("Location: appointments.php?msg=Cancelled");
        exit();
    }
}

// Get all appointments
$appointments = mysqli_query($conn, "SELECT a.*, s.service_name, s.price, u.full_name as staff_name 
                                     FROM appointments a 
                                     JOIN services s ON a.service_id = s.id 
                                     LEFT JOIN users u ON a.staff_id = u.id 
                                     WHERE a.customer_id = $user_id 
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

    .status-completed { color: #28a745; font-weight: bold; }
    .status-cancelled { color: #dc3545; font-weight: bold; }
    .status-pending { color: #d4af37; font-weight: bold; }
    .status-served { color: #28a745; font-weight: bold; }

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
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .section-title { font-size: 1rem; }
        table { min-width: 400px; font-size: 0.7rem; }
        th, td { padding: 6px; }
    }
</style>

<div class="main-content">

    <h1 class="section-title">📅 My Appointments</h1>

    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'Cancelled'): ?>
        <div class="alert alert-success">✅ Appointment cancelled successfully!</div>
    <?php endif; ?>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Service</th>
                    <th>Staff</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Queue</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if($appointments && mysqli_num_rows($appointments) > 0): ?>
                    <?php while($apt = mysqli_fetch_assoc($appointments)): ?>
                    <tr>
                        <td>#<?php echo $apt['id']; ?></td>
                        <td><?php echo htmlspecialchars($apt['service_name']); ?></td>
                        <td><?php echo htmlspecialchars($apt['staff_name'] ?? 'Not Assigned'); ?></td>
                        <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                        <td><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></td>
                        <td>KSh <?php echo number_format($apt['price'], 2); ?></td>
                        <td>
                            <span class="status-<?php echo $apt['status']; ?>">
                                <?php echo ucfirst($apt['status']); ?>
                            </span>
                        </td>
                        <td><?php echo $apt['queue_position'] ?? '-'; ?></td>
                        <td>
                            <?php if($apt['status'] == 'pending' || $apt['status'] == 'confirmed'): ?>
                                <a href="?cancel=<?php echo $apt['id']; ?>" class="btn-cancel" onclick="return confirm('Cancel this appointment?')">Cancel</a>
                            <?php elseif($apt['status'] == 'served' || $apt['status'] == 'completed'): ?>
                                <span style="color: #28a745;">✓ Completed</span>
                            <?php else: ?>
                                <span>—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px;">
                            📭 No appointments found. <a href="book.php" style="color: #d4af37;">Book your first appointment!</a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<?php include '../includes/footer.php'; ?>