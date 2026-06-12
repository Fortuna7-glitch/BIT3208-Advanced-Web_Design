<?php
// admin/appointments.php - FIXED VERSION
require_once '../config/database.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

// Handle serve/cancel
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $appointment_id = mysqli_real_escape_string($conn, $_POST['appointment_id']);
    
    if ($_POST['action'] == 'serve') {
        $query = "UPDATE appointments SET status = 'served' WHERE id = $appointment_id";
        if (mysqli_query($conn, $query)) {
            // Get customer info for notification
            $apt_query = "SELECT a.*, u.full_name, u.email, u.phone FROM appointments a 
                          JOIN users u ON a.customer_id = u.id WHERE a.id = $appointment_id";
            $apt_result = mysqli_query($conn, $apt_query);
            if ($apt_result && $apt = mysqli_fetch_assoc($apt_result)) {
                sendNotification($apt['customer_id'], "Service Completed", "Your service has been completed. Thank you for choosing Salon Pro!", 'email');
                sendSMS($apt['phone'], "Salon Pro: Your appointment has been completed. Thank you!");
            }
        }
    } elseif ($_POST['action'] == 'cancel') {
        $query = "UPDATE appointments SET status = 'cancelled' WHERE id = $appointment_id";
        mysqli_query($conn, $query);
    }
    redirect('appointments.php');
}

$appointments = mysqli_query($conn, "SELECT a.*, c.full_name as customer_name, s.service_name, st.full_name as staff_name 
                                    FROM appointments a 
                                    JOIN users c ON a.customer_id = c.id 
                                    JOIN services s ON a.service_id = s.id 
                                    LEFT JOIN users st ON a.staff_id = st.id 
                                    ORDER BY a.appointment_date DESC, a.appointment_time DESC");

include '../includes/header.php';
?>

<style>
    .dashboard-container { display: flex; min-height: 100vh; }
    .sidebar { width: 280px; background: #050505; border-right: 1px solid #d4af37; padding: 2rem 1rem; }
    .sidebar-menu { list-style: none; padding: 0; }
    .sidebar-menu li { margin-bottom: 0.5rem; }
    .sidebar-menu a { display: block; padding: 12px 20px; color: white; text-decoration: none; }
    .sidebar-menu a:hover, .sidebar-menu a.active { background: #d4af37; color: #050505; border-radius: 10px; }
    .main-content { flex: 1; padding: 2rem; background: #0a0a0a; }
    .table-container { overflow-x: auto; background: #1a1a1a; border-radius: 15px; padding: 1rem; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid rgba(212, 175, 55, 0.2); }
    th { color: #d4af37; }
    .btn-outline { display: inline-block; padding: 5px 10px; border: 1px solid #d4af37; color: #d4af37; text-decoration: none; border-radius: 5px; font-size: 0.8rem; margin: 0 2px; }
    .btn-outline:hover { background: #d4af37; color: #050505; }
    h1 { color: #d4af37; }
    @media (max-width: 768px) { .dashboard-container { flex-direction: column; } .sidebar { width: 100%; } }
</style>

<div class="dashboard-container">
    <aside class="sidebar">
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="appointments.php" class="active">📅 Appointments</a></li>
            <li><a href="services.php">💇 Services</a></li>
            <li><a href="staff.php">👥 Staff</a></li>
            <li><a href="customers.php">👤 Customers</a></li>
            <li><a href="payments.php">💰 Payments</a></li>
            <li><a href="reports.php">📈 Reports</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <h1>All Appointments 📅</h1>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th><th>Customer</th><th>Service</th><th>Staff</th><th>Date</th><th>Time</th><th>Status</th><th>Queue Pos</th><th>Actions</th>
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
                                <span style="color: <?php echo $apt['status'] == 'served' ? '#28a745' : ($apt['status'] == 'cancelled' ? '#dc3545' : '#d4af37'); ?>">
                                    <?php echo ucfirst($apt['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $apt['queue_position'] ?? '-'; ?></td>
                            <td>
                                <?php if($apt['status'] != 'served' && $apt['status'] != 'cancelled'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                    <input type="hidden" name="action" value="serve">
                                    <button type="submit" class="btn-outline" style="background: #28a745; color: white; border: none;">Serve</button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <button type="submit" class="btn-outline" style="background: #dc3545; color: white; border: none;" onclick="return confirm('Cancel this appointment?')">Cancel</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="9" style="text-align: center;">No appointments found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>