<?php
// staff/dashboard.php - COMPLETE FIXED FILE with proper table formatting and permission checks
require_once '../config/database.php';

// ONLY STAFF can access
if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

$staff_id = $_SESSION['user_id'];
$staff_name = $_SESSION['user_name'];

// Get staff permissions for sidebar
$staff_permissions = getStaffPermissions($staff_id);

// Get today's appointments assigned to this staff
$today = date('Y-m-d');
$today_query = "SELECT a.*, c.full_name as customer_name, c.phone as customer_phone, s.service_name, s.price 
                FROM appointments a 
                JOIN users c ON a.customer_id = c.id 
                JOIN services s ON a.service_id = s.id 
                WHERE a.staff_id = $staff_id AND a.appointment_date = '$today' 
                ORDER BY a.appointment_time ASC";
$today_appointments = mysqli_query($conn, $today_query);

// Get pending queue for this staff
$queue_query = "SELECT a.*, c.full_name as customer_name, s.service_name 
                FROM appointments a 
                JOIN users c ON a.customer_id = c.id 
                JOIN services s ON a.service_id = s.id 
                WHERE a.staff_id = $staff_id AND a.status = 'pending' 
                ORDER BY a.appointment_time ASC, a.queue_position ASC";
$queue = mysqli_query($conn, $queue_query);

// Get staff statistics
$stats_query = "SELECT 
    COUNT(*) as total_appointments,
    SUM(CASE WHEN status = 'served' THEN 1 ELSE 0 END) as completed_today
    FROM appointments 
    WHERE staff_id = $staff_id AND appointment_date = '$today'";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

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
    .queue-item { background: #1a1a1a; border-radius: 10px; padding: 1rem; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; border: 1px solid rgba(212, 175, 55, 0.3); }
    .btn-serve { background: #28a745; color: white; border: none; padding: 8px 20px; border-radius: 25px; cursor: pointer; }
    .btn-serve:hover { background: #218838; }
    
    /* Proper Table Styles */
    .table-wrapper {
        overflow-x: auto;
        background: #1a1a1a;
        border-radius: 15px;
        padding: 0;
        margin-top: 1rem;
        border: 1px solid rgba(212, 175, 55, 0.2);
    }
    .appointments-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }
    .appointments-table th {
        background: #2a2a2a;
        color: #d4af37;
        padding: 14px 12px;
        text-align: left;
        font-weight: 600;
        border-bottom: 2px solid #d4af37;
    }
    .appointments-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid rgba(212, 175, 55, 0.15);
        color: #ffffff;
    }
    .appointments-table tr:hover {
        background: rgba(212, 175, 55, 0.05);
    }
    .appointments-table tr:last-child td {
        border-bottom: none;
    }
    .status-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    .status-served { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }
    .status-pending { background: rgba(212, 175, 55, 0.2); color: #d4af37; border: 1px solid #d4af37; }
    .status-cancelled { background: rgba(220, 53, 69, 0.2); color: #dc3545; border: 1px solid #dc3545; }
    
    .btn-small {
        padding: 5px 12px;
        font-size: 0.75rem;
        background: #28a745;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }
    .btn-small:hover { background: #218838; }
    
    h1, h2 { color: #d4af37; margin-bottom: 1rem; }
    h2 { font-size: 1.3rem; margin-top: 1.5rem; }
    
    @media (max-width: 768px) { 
        .staff-container { flex-direction: column; } 
        .sidebar { width: 100%; }
        .appointments-table th, .appointments-table td { padding: 8px; font-size: 0.75rem; }
    }
</style>

<div class="staff-container">
    <aside class="sidebar">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h3 style="color: #d4af37;">👤 <?php echo htmlspecialchars($staff_name); ?></h3>
            <p>Staff Member</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="active">📊 Dashboard</a></li>
            <li><a href="appointments.php">📅 My Appointments</a></li>
            
            <?php if(in_array('book_for_customers', $staff_permissions)): ?>
                <li><a href="book_for_customer.php">📝 Book for Customer</a></li>
            <?php endif; ?>
            
            <?php if(in_array('manual_cash_payment', $staff_permissions)): ?>
                <li><a href="manual_payment.php">💵 Manual Cash Payment</a></li>
            <?php endif; ?>

            <?php if(in_array('view_reports', $staff_permissions)): ?>
                <li><a href="reports.php">📈 My Reports</a></li>
            <?php endif; ?>
            
            <li><a href="profile.php">⚙️ My Profile</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <h1>Welcome, <?php echo htmlspecialchars($staff_name); ?>! ✨</h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_appointments'] ?? 0; ?></div>
                <p>Today's Appointments</p>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['completed_today'] ?? 0; ?></div>
                <p>Completed Today</p>
            </div>
        </div>
        
        <?php if($queue && mysqli_num_rows($queue) > 0): ?>
        <h2>🚀 Your Current Queue</h2>
        <?php while($q = mysqli_fetch_assoc($queue)): ?>
        <div class="queue-item">
            <div>
                <strong><?php echo htmlspecialchars($q['customer_name']); ?></strong><br>
                <small><?php echo htmlspecialchars($q['service_name']); ?></small><br>
                <small>⏰ Time: <?php echo date('g:i A', strtotime($q['appointment_time'])); ?> | 🔢 Queue #<?php echo $q['queue_position']; ?></small>
            </div>
            <form method="POST" action="appointments.php">
                <input type="hidden" name="appointment_id" value="<?php echo $q['id']; ?>">
                <input type="hidden" name="action" value="serve">
                <button type="submit" class="btn-serve" onclick="return confirm('Mark this customer as served?')">✓ Mark as Served</button>
            </form>
        </div>
        <?php endwhile; ?>
        <?php else: ?>
        <div class="queue-item" style="text-align: center; justify-content: center;">
            <p>✨ No customers waiting in your queue. Great job!</p>
        </div>
        <?php endif; ?>
        
        <h2>📅 Today's Schedule - <?php echo date('l, F d, Y'); ?></h2>
        <div class="table-wrapper">
            <table class="appointments-table">
                <thead>
                    <tr>
                        <th>⏰ Time</th>
                        <th>👤 Customer Name</th>
                        <th>💇 Service</th>
                        <th>📞 Phone</th>
                        <th>💰 Price</th>
                        <th>📌 Status</th>
                        <th>⚡ Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($today_appointments && mysqli_num_rows($today_appointments) > 0): ?>
                        <?php while($apt = mysqli_fetch_assoc($today_appointments)): ?>
                        <tr>
                            <td><strong><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></strong></td>
                            <td><?php echo htmlspecialchars($apt['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($apt['service_name']); ?></td>
                            <td><?php echo htmlspecialchars($apt['customer_phone']); ?></td>
                            <td>KSh <?php echo number_format($apt['price'], 2); ?></td>
                            <td>
                                <?php if($apt['status'] == 'served'): ?>
                                    <span class="status-badge status-served">✓ Served</span>
                                <?php elseif($apt['status'] == 'cancelled'): ?>
                                    <span class="status-badge status-cancelled">✗ Cancelled</span>
                                <?php else: ?>
                                    <span class="status-badge status-pending">⏳ Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($apt['status'] != 'served' && $apt['status'] != 'cancelled'): ?>
                                <form method="POST" action="appointments.php" style="display: inline;">
                                    <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                    <input type="hidden" name="action" value="serve">
                                    <button type="submit" class="btn-small" onclick="return confirm('Mark this customer as served?')">✓ Mark Served</button>
                                </form>
                                <?php elseif($apt['status'] == 'served'): ?>
                                    <span style="color: #28a745;">✅ Completed</span>
                                <?php else: ?>
                                    <span style="color: #dc3545;">❌ Cancelled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">
                                🎉 No appointments scheduled for today. Enjoy your free time!
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>