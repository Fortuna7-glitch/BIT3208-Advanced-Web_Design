<?php
// staff/appointments.php - COMPLETE REWRITE with working Serve
require_once '../config/database.php';

// ============================================
// AUTHENTICATION CHECK
// ============================================
if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

$staff_id = $_SESSION['user_id'];

// ============================================
// HANDLE SERVE APPOINTMENT
// ============================================
if (isset($_GET['serve']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Verify this appointment belongs to this staff member
    $check = mysqli_query($conn, "SELECT id FROM appointments WHERE id = $id AND staff_id = $staff_id");
    if (mysqli_num_rows($check) == 1) {
        $query = "UPDATE appointments SET status = 'served' WHERE id = $id AND staff_id = $staff_id";
        if (mysqli_query($conn, $query)) {
            $success = "Appointment marked as served!";
        } else {
            $error = "Database error: " . mysqli_error($conn);
        }
    } else {
        $error = "Appointment not found or not assigned to you.";
    }
}

// ============================================
// GET APPOINTMENTS FOR THIS STAFF
// ============================================
$appointments_query = "SELECT a.*, c.full_name as customer_name, c.phone as customer_phone, s.service_name, s.price 
                       FROM appointments a 
                       JOIN users c ON a.customer_id = c.id 
                       JOIN services s ON a.service_id = s.id 
                       WHERE a.staff_id = $staff_id 
                       ORDER BY a.appointment_date DESC, a.appointment_time DESC";
$appointments = mysqli_query($conn, $appointments_query);

// ============================================
// INCLUDE HEADER
// ============================================
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
    
    .btn-success { background: #28a745; color: white; border: none; padding: 6px 15px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.75rem; }
    .btn-success:hover { background: #218838; }
    
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 1rem; }
    .alert-success { background: rgba(40, 167, 69, 0.2); border: 1px solid #28a745; color: #28a745; }
    .alert-danger { background: rgba(220, 53, 69, 0.2); border: 1px solid #dc3545; color: #dc3545; }
    
    .status-served { color: #28a745; font-weight: bold; }
    .status-cancelled { color: #dc3545; font-weight: bold; }
    .status-pending { color: #d4af37; font-weight: bold; }
    
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
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if(isset($error)): ?>
            <div class="alert alert-danger">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="table-container">
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
                        <th>Action</th>
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
                            <td class="status-<?php echo $apt['status']; ?>">
                                <?php echo ucfirst($apt['status']); ?>
                            </td>
                            <td><?php echo $apt['queue_position'] ?? '-'; ?></td>
                            <td>
                                <?php if($apt['status'] == 'pending' || $apt['status'] == 'confirmed'): ?>
                                    <a href="?serve=1&id=<?php echo $apt['id']; ?>" class="btn-success" onclick="return confirm('Mark this appointment as served?')">✅ Serve</a>
                                <?php elseif($apt['status'] == 'served'): ?>
                                    <span style="color: #28a745;">✓ Completed</span>
                                <?php else: ?>
                                    <span>—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">No appointments assigned to you.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>