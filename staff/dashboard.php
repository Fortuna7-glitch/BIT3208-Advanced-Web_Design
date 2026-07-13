<?php
// staff/dashboard.php - REDESIGNED: Clean, Modern, Card-Based Layout for Staff
require_once '../config/database.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

$staff_id = $_SESSION['user_id'];
$staff_name = $_SESSION['user_name'];

// Get staff permissions for sidebar
$staff_permissions = getStaffPermissions($staff_id);

// Get salon_id from staff record
$salon_id = $_SESSION['salon_id'] ?? 0;
if ($salon_id <= 0) {
    $user_query = mysqli_query($conn, "SELECT salon_id FROM users WHERE id = $staff_id");
    if ($user_result = mysqli_fetch_assoc($user_query)) {
        $salon_id = $user_result['salon_id'];
        $_SESSION['salon_id'] = $salon_id;
    }
}

// ============================================
// CHECK SUBSCRIPTION STATUS
// ============================================
$subscription_active = true;
$subscription_message = '';
$salon_name = '';

if ($salon_id > 0) {
    $salon_query = mysqli_query($conn, "SELECT salon_name, subscription_expiry, subscription_status FROM salons WHERE id = $salon_id");
    if ($salon_data = mysqli_fetch_assoc($salon_query)) {
        $salon_name = $salon_data['salon_name'];
        $subscription_status = $salon_data['subscription_status'];
        $expiry_date = $salon_data['subscription_expiry'];
        
        if ($subscription_status == 'expired' || $subscription_status == 'suspended') {
            $subscription_active = false;
            $subscription_message = "This salon account is currently " . $subscription_status . ". Please contact the salon administrator.";
        } elseif (!empty($expiry_date) && $expiry_date < date('Y-m-d')) {
            $subscription_active = false;
            $subscription_message = "This salon subscription expired on " . date('M d, Y', strtotime($expiry_date)) . ". Please contact the salon administrator.";
        }
    }
}

// ============================================
// STATISTICS
// ============================================
$today = date('Y-m-d');
$stats_query = "SELECT 
    COUNT(*) as total_appointments,
    SUM(CASE WHEN status = 'served' THEN 1 ELSE 0 END) as completed_today,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_today
    FROM appointments 
    WHERE staff_id = $staff_id AND appointment_date = '$today'";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// ============================================
// TODAY'S APPOINTMENTS
// ============================================
$today_query = "SELECT a.*, c.full_name as customer_name, c.phone as customer_phone, s.service_name, s.price 
                FROM appointments a 
                JOIN users c ON a.customer_id = c.id 
                JOIN services s ON a.service_id = s.id 
                WHERE a.staff_id = $staff_id AND a.appointment_date = '$today' 
                ORDER BY a.appointment_time ASC LIMIT 5";
$today_appointments = mysqli_query($conn, $today_query);

// ============================================
// QUEUE
// ============================================
$queue_query = "SELECT a.*, c.full_name as customer_name, s.service_name 
                FROM appointments a 
                JOIN users c ON a.customer_id = c.id 
                JOIN services s ON a.service_id = s.id 
                WHERE a.staff_id = $staff_id AND a.status = 'pending' 
                ORDER BY a.appointment_time ASC, a.queue_position ASC LIMIT 5";
$queue = mysqli_query($conn, $queue_query);

// ============================================
// STAFF DETAILS
// ============================================
$staff_details_query = "SELECT u.full_name, u.email, u.phone, sd.specialty, sd.experience_years 
                        FROM users u 
                        LEFT JOIN staff_details sd ON u.id = sd.user_id 
                        WHERE u.id = $staff_id";
$staff_details_result = mysqli_query($conn, $staff_details_query);
$staff_details = mysqli_fetch_assoc($staff_details_result);

include '../includes/header.php';
?>

<style>
    /* ============================================
       DASHBOARD STYLES
       ============================================ */
    .main-content {
        padding: 2rem;
        background: #0a0a0a;
        min-height: 100vh;
    }

    /* ============================================
       WELCOME HEADER
       ============================================ */
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        margin-bottom: 2rem;
    }
    .dashboard-header h1 {
        color: #d4af37;
        font-size: 1.8rem;
        font-family: 'Playfair Display', serif;
    }
    .dashboard-header p {
        color: #aaa;
        font-size: 0.95rem;
    }
    .dashboard-header .staff-badge {
        display: inline-block;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: bold;
        background: rgba(212, 175, 55, 0.2);
        color: #d4af37;
        border: 1px solid #d4af37;
    }

    /* ============================================
       STATS GRID
       ============================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    .stat-card {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        border-left: 4px solid #d4af37;
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(212, 175, 55, 0.1);
    }
    .stat-card .stat-icon {
        font-size: 1.8rem;
        opacity: 0.3;
        position: absolute;
        right: 1rem;
        top: 1rem;
    }
    .stat-card .stat-number {
        font-size: 2.2rem;
        font-weight: bold;
        color: #d4af37;
    }
    .stat-card .stat-label {
        color: #aaa;
        font-size: 0.85rem;
        margin-top: 0.2rem;
    }
    .stat-card .stat-trend {
        font-size: 0.75rem;
        margin-top: 0.5rem;
        display: inline-block;
        padding: 2px 10px;
        border-radius: 20px;
    }
    .stat-card .stat-trend.up {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
    }
    .stat-card .stat-trend.down {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
    }
    .stat-card .stat-trend.neutral {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
    }

    /* ============================================
       TWO-COLUMN GRID
       ============================================ */
    .dashboard-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }

    /* ============================================
       TODAY'S SCHEDULE
       ============================================ */
    .schedule-card {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        border: 1px solid rgba(212, 175, 55, 0.15);
    }
    .schedule-card h3 {
        color: #d4af37;
        font-size: 1rem;
        margin-bottom: 1.2rem;
    }
    .schedule-card .view-all {
        color: #d4af37;
        text-decoration: none;
        font-size: 0.8rem;
        float: right;
    }
    .schedule-card .view-all:hover {
        text-decoration: underline;
    }

    .appointment-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.7rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .appointment-item:last-child {
        border-bottom: none;
    }
    .appointment-item .appt-time {
        color: #d4af37;
        font-weight: 600;
        font-size: 0.85rem;
        min-width: 70px;
    }
    .appointment-item .appt-details {
        flex: 1;
    }
    .appointment-item .appt-details .appt-service {
        font-weight: 500;
        font-size: 0.95rem;
    }
    .appointment-item .appt-details .appt-customer {
        color: #aaa;
        font-size: 0.8rem;
    }
    .appointment-item .appt-status {
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 500;
    }
    .appt-status.confirmed { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }
    .appt-status.pending { background: rgba(212, 175, 55, 0.2); color: #d4af37; border: 1px solid #d4af37; }
    .appt-status.completed { background: rgba(23, 162, 184, 0.2); color: #17a2b8; border: 1px solid #17a2b8; }
    .appt-status.cancelled { background: rgba(220, 53, 69, 0.2); color: #dc3545; border: 1px solid #dc3545; }
    .appt-status.served { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }

    /* ============================================
       QUEUE
       ============================================ */
    .queue-card {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        border: 1px solid rgba(212, 175, 55, 0.15);
    }
    .queue-card h3 {
        color: #d4af37;
        font-size: 1rem;
        margin-bottom: 1.2rem;
    }
    .queue-card .view-all {
        color: #d4af37;
        text-decoration: none;
        font-size: 0.8rem;
        float: right;
    }
    .queue-card .view-all:hover {
        text-decoration: underline;
    }

    .queue-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.7rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .queue-item:last-child {
        border-bottom: none;
    }
    .queue-item .queue-position {
        background: #d4af37;
        color: #050505;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.8rem;
        flex-shrink: 0;
    }
    .queue-item .queue-details {
        flex: 1;
    }
    .queue-item .queue-details .queue-customer {
        font-weight: 500;
        font-size: 0.9rem;
    }
    .queue-item .queue-details .queue-service {
        color: #aaa;
        font-size: 0.8rem;
    }
    .queue-item .queue-time {
        color: #888;
        font-size: 0.75rem;
    }
    .queue-item .btn-serve {
        background: #28a745;
        color: white;
        border: none;
        padding: 5px 12px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.75rem;
        transition: all 0.3s;
        text-decoration: none;
    }
    .queue-item .btn-serve:hover {
        background: #218838;
        transform: scale(1.05);
    }
    .queue-item .btn-serve:disabled {
        background: #555;
        cursor: not-allowed;
        transform: none;
    }

    /* ============================================
       PROFILE CARD
       ============================================ */
    .profile-card {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        border: 1px solid rgba(212, 175, 55, 0.15);
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 1.5rem;
        flex-wrap: wrap;
    }
    .profile-card .avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: #d4af37;
        color: #050505;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: bold;
        flex-shrink: 0;
    }
    .profile-card .profile-info h3 {
        color: #d4af37;
        font-size: 1.1rem;
        margin-bottom: 0.2rem;
    }
    .profile-card .profile-info .specialty {
        color: #d4af37;
        font-size: 0.9rem;
    }
    .profile-card .profile-info .detail {
        color: #aaa;
        font-size: 0.85rem;
        margin-top: 0.2rem;
    }

    /* ============================================
       INACTIVE BANNER
       ============================================ */
    .inactive-banner {
        background: rgba(220, 53, 69, 0.15);
        border: 1px solid #dc3545;
        border-radius: 12px;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
        color: #dc3545;
        text-align: center;
    }
    .inactive-banner .icon {
        font-size: 2.5rem;
        display: block;
        margin-bottom: 0.5rem;
    }
    .inactive-banner .message {
        font-size: 1rem;
    }
    .inactive-banner .message strong {
        font-weight: 600;
    }
    .inactive-banner .sub-message {
        font-size: 0.9rem;
        color: #ff6b6b;
        margin-top: 0.5rem;
    }

    /* ============================================
       RESPONSIVE
       ============================================ */
    @media (max-width: 1024px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        }
    }

    @media (max-width: 768px) {
        .main-content { padding: 1rem; }
        .dashboard-header { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
        .dashboard-header h1 { font-size: 1.4rem; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 1rem; }
        .stat-card { padding: 1rem; }
        .stat-card .stat-number { font-size: 1.6rem; }
        .appointment-item { flex-direction: column; align-items: flex-start; }
        .queue-item { flex-direction: column; align-items: flex-start; }
        .profile-card { flex-direction: column; text-align: center; }
        .inactive-banner { padding: 1rem; }
        .inactive-banner .message { font-size: 1rem; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .stat-card .stat-number { font-size: 1.4rem; }
        .dashboard-header h1 { font-size: 1.2rem; }
        .inactive-banner .message { font-size: 0.9rem; }
    }
</style>

<div class="main-content">

    <!-- ============================================
       INACTIVE BANNER (If subscription expired)
       ============================================ -->
    <?php if (!$subscription_active): ?>
    <div class="inactive-banner">
        <div class="icon">🚫</div>
        <div class="message">
            <strong>Salon Temporarily Unavailable</strong>
        </div>
        <div class="sub-message">
            <?php echo $subscription_message; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================
       HEADER
       ============================================ -->
    <div class="dashboard-header">
        <div>
            <h1>👋 Welcome back, <?php echo htmlspecialchars($staff_name); ?>!</h1>
            <p>Here's your schedule for today</p>
        </div>
        <div>
            <span class="staff-badge">✂️ <?php echo htmlspecialchars($staff_details['specialty'] ?? 'Stylist'); ?></span>
        </div>
    </div>

    <!-- ============================================
       PROFILE CARD
       ============================================ -->
    <div class="profile-card">
        <div class="avatar"><?php echo strtoupper(substr($staff_name, 0, 1)); ?></div>
        <div class="profile-info">
            <h3>👤 <?php echo htmlspecialchars($staff_name); ?></h3>
            <div class="specialty">✂️ <?php echo htmlspecialchars($staff_details['specialty'] ?? 'Professional Stylist'); ?></div>
            <div class="detail">📅 <?php echo $staff_details['experience_years'] ?? 0; ?>+ years experience</div>
            <div class="detail">📧 <?php echo htmlspecialchars($staff_details['email']); ?> &nbsp;|&nbsp; 📞 <?php echo htmlspecialchars($staff_details['phone']); ?></div>
        </div>
    </div>

    <!-- ============================================
       STATS GRID
       ============================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-icon">📅</span>
            <div class="stat-number"><?php echo $stats['total_appointments'] ?? 0; ?></div>
            <div class="stat-label">Today's Appointments</div>
            <span class="stat-trend neutral"><?php echo $stats['total_appointments'] ?? 0; ?> total</span>
        </div>
        <div class="stat-card">
            <span class="stat-icon">✅</span>
            <div class="stat-number"><?php echo $stats['completed_today'] ?? 0; ?></div>
            <div class="stat-label">Completed Today</div>
            <span class="stat-trend up"><?php echo $stats['completed_today'] ?? 0; ?> done</span>
        </div>
        <div class="stat-card">
            <span class="stat-icon">⏳</span>
            <div class="stat-number"><?php echo $stats['pending_today'] ?? 0; ?></div>
            <div class="stat-label">Pending</div>
            <span class="stat-trend <?php echo $stats['pending_today'] > 0 ? 'down' : 'up'; ?>"><?php echo $stats['pending_today'] ?? 0; ?> pending</span>
        </div>
        <div class="stat-card">
            <span class="stat-icon">🚀</span>
            <div class="stat-number"><?php echo mysqli_num_rows($queue); ?></div>
            <div class="stat-label">In Queue</div>
            <span class="stat-trend neutral"><?php echo mysqli_num_rows($queue); ?> waiting</span>
        </div>
    </div>

    <!-- ============================================
       DASHBOARD GRID (Schedule + Queue)
       ============================================ -->
    <div class="dashboard-grid">

        <!-- Today's Schedule -->
        <div class="schedule-card">
            <h3>
                📅 Today's Schedule
                <a href="appointments.php" class="view-all">View All →</a>
            </h3>

            <?php if($today_appointments && mysqli_num_rows($today_appointments) > 0): ?>
                <?php while($apt = mysqli_fetch_assoc($today_appointments)): ?>
                    <div class="appointment-item">
                        <span class="appt-time"><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></span>
                        <div class="appt-details">
                            <div class="appt-service">💇 <?php echo htmlspecialchars($apt['service_name']); ?></div>
                            <div class="appt-customer"><?php echo htmlspecialchars($apt['customer_name']); ?></div>
                        </div>
                        <span class="appt-status <?php echo $apt['status']; ?>"><?php echo ucfirst($apt['status']); ?></span>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="color: #666; text-align: center; padding: 1.5rem;">
                    <p>🎉 No appointments scheduled for today. Enjoy your free time!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Current Queue -->
        <div class="queue-card">
            <h3>
                🚀 Your Current Queue
                <a href="appointments.php" class="view-all">View All →</a>
            </h3>

            <?php if($queue && mysqli_num_rows($queue) > 0): ?>
                <?php while($q = mysqli_fetch_assoc($queue)): ?>
                    <div class="queue-item">
                        <span class="queue-position">#<?php echo $q['queue_position']; ?></span>
                        <div class="queue-details">
                            <div class="queue-customer"><?php echo htmlspecialchars($q['customer_name']); ?></div>
                            <div class="queue-service"><?php echo htmlspecialchars($q['service_name']); ?></div>
                        </div>
                        <div>
                            <span class="queue-time">⏰ <?php echo date('g:i A', strtotime($q['appointment_time'])); ?></span>
                            <form method="POST" action="appointments.php" style="display: inline; margin-left: 0.5rem;">
                                <input type="hidden" name="appointment_id" value="<?php echo $q['id']; ?>">
                                <input type="hidden" name="action" value="serve">
                                <button type="submit" class="btn-serve" onclick="return confirm('Mark this customer as served?')" <?php echo !$subscription_active ? 'disabled' : ''; ?>>✓ Serve</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="color: #666; text-align: center; padding: 1.5rem;">
                    <p>✨ No customers waiting in your queue. Great job!</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>