<?php
// customer/appointments.php - COMPLETE FILE
require_once '../config/database.php';

if (!isLoggedIn() || !isCustomer()) {
    header("Location: ../auth/login.php");
    exit();
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
    .dashboard-container { display: flex; min-height: 100vh; }
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
    .btn-outline { display: inline-block; padding: 5px 10px; border: 1px solid #d4af37; color: #d4af37; text-decoration: none; border-radius: 5px; }
    .btn-outline:hover { background: #d4af37; color: #050505; }
    .status-served { color: #28a745; font-weight: bold; }
    h1 { color: #d4af37; }
    .alert-success { background: rgba(40, 167, 69, 0.2); border: 1px solid #28a745; color: #28a745; padding: 15px; border-radius: 8px; margin-bottom: 1rem; }
    @media (max-width: 768px) { .dashboard-container { flex-direction: column; } .sidebar { width: 100%; } }
</style>

<div class="dashboard-container">
    <aside class="sidebar">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h3 style="color: #d4af37;">👤 <?php echo htmlspecialchars($_SESSION['user_name']); ?></h3>
            <p>Customer</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="book.php">✨ New Booking</a></li>
            <li><a href="appointments.php" class="active">📅 My Appointments</a></li>
            <li><a href="update-profile.php">⚙️ Update Profile</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <h1>My Appointments 📅</h1>
        
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'Cancelled'): ?>
            <div class="alert-success">Appointment cancelled successfully!</div>
        <?php endif; ?>
        
        <div class="table-container">
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
                                <span style="color: <?php echo $apt['status'] == 'served' ? '#28a745' : ($apt['status'] == 'cancelled' ? '#dc3545' : '#d4af37'); ?>">
                                    <?php echo ucfirst($apt['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $apt['queue_position'] ?? '-'; ?></td>
                            <td>
                                <?php if($apt['status'] == 'pending' || $apt['status'] == 'confirmed'): ?>
                                    <a href="?cancel=<?php echo $apt['id']; ?>" class="btn-outline" style="background: #dc3545; color: white; border: none;" onclick="return confirm('Cancel this appointment?')">Cancel</a>
                                <?php elseif($apt['status'] == 'served'): ?>
                                    <span class="status-served">✓ Completed</span>
                                <?php else: ?>
                                    <span>—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center;">No appointments found. <a href="book.php">Book your first appointment!</a></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>