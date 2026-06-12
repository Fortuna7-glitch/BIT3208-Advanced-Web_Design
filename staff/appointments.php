<?php
// staff/appointments.php - COMPLETE FILE
require_once '../config/database.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

$staff_id = $_SESSION['user_id'];

// Handle serve action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'serve') {
    $appointment_id = mysqli_real_escape_string($conn, $_POST['appointment_id']);
    mysqli_query($conn, "UPDATE appointments SET status = 'served' WHERE id = $appointment_id AND staff_id = $staff_id");
    header("Location: appointments.php?msg=updated");
    exit();
}

// Get all appointments assigned to this staff
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
    .staff-container { display: flex; min-height: 100vh; }
    .sidebar { width: 280px; background: #050505; border-right: 1px solid #d4af37; padding: 2rem 1rem; }
    .sidebar-menu { list-style: none; padding: 0; }
    .sidebar-menu li { margin-bottom: 0.5rem; }
    .sidebar-menu a { display: block; padding: 12px 20px; color: white; text-decoration: none; border-radius: 10px; transition: all 0.3s; }
    .sidebar-menu a:hover, .sidebar-menu a.active { background: #d4af37; color: #050505; }
    .main-content { flex: 1; padding: 2rem; background: #0a0a0a; }
    .table-container { overflow-x: auto; background: #1a1a1a; border-radius: 15px; padding: 1rem; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid rgba(212, 175, 55, 0.2); }
    th { color: #d4af37; }
    .btn-serve { background: #28a745; color: white; border: none; padding: 5px 15px; border-radius: 5px; cursor: pointer; }
    .alert-success { background: rgba(40, 167, 69, 0.2); border: 1px solid #28a745; color: #28a745; padding: 15px; border-radius: 8px; margin-bottom: 1rem; }
    h1 { color: #d4af37; }
    @media (max-width: 768px) { .staff-container { flex-direction: column; } .sidebar { width: 100%; } }
</style>

<div class="staff-container">
    <aside class="sidebar">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h3 style="color: #d4af37;">👤 <?php echo htmlspecialchars($_SESSION['user_name']); ?></h3>
            <p>Staff Member</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="appointments.php" class="active">📅 My Appointments</a></li>
            <li><a href="profile.php">⚙️ My Profile</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <h1>My Appointments 📅</h1>
        
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'updated'): ?>
            <div class="alert-success">Appointment marked as served! ✓</div>
        <?php endif; ?>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>Date</th><th>Time</th><th>Customer</th><th>Service</th><th>Phone</th><th>Status</th><th>Queue</th><th>Action</th></tr>
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
                            <td><span style="color: <?php echo $apt['status'] == 'served' ? '#28a745' : '#d4af37'; ?>"><?php echo ucfirst($apt['status']); ?></span></td>
                            <td><?php echo $apt['queue_position'] ?? '-'; ?></td>
                            <td><?php if($apt['status'] != 'served' && $apt['status'] != 'cancelled'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                    <input type="hidden" name="action" value="serve">
                                    <button type="submit" class="btn-serve">✓ Serve</button>
                                </form>
                            <?php elseif($apt['status'] == 'served'): ?>✓ Completed<?php else: ?>—<?php endif; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align: center;">No appointments assigned to you yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>