<?php
// admin/dashboard.php - REDESIGNED: Clean, Modern, Card-Based Layout for Salon Owners
require_once '../config/database.php';

// ============================================
// INCLUDE SUBSCRIPTION BANNER COMPONENT
// ============================================
include_once '../includes/subscription_banner.php';

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

// ============================================
// RENDER SUBSCRIPTION BANNER
// ============================================
renderSubscriptionBanner($salon_id);

$salon_query = mysqli_query($conn, "SELECT salon_name, subscription_plan, subscription_expiry, subscription_status FROM salons WHERE id = $salon_id");
$salon_data = mysqli_fetch_assoc($salon_query);
$current_plan = $salon_data['subscription_plan'] ?? 'basic';
$salon_name = $salon_data['salon_name'] ?? '';
$subscription_expiry = $salon_data['subscription_expiry'] ?? null;
$subscription_status = $salon_data['subscription_status'] ?? 'active';

$upgrade_info = getUpgradeMessage($current_plan);

// ============================================
// STATISTICS
// ============================================
$total_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'customer' AND salon_id = $salon_id"))['count'] ?? 0;
$total_appointments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments WHERE salon_id = $salon_id"))['count'] ?? 0;
$total_revenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM payments WHERE payment_status = 'paid' AND salon_id = $salon_id"))['total'] ?? 0;
$pending_appointments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments WHERE (status = 'pending' OR status = 'confirmed') AND salon_id = $salon_id"))['count'] ?? 0;

// ============================================
// TODAY'S APPOINTMENTS
// ============================================
$today = date('Y-m-d');
$today_appointments = mysqli_query($conn, "SELECT a.*, c.full_name as customer_name, c.phone as customer_phone, s.service_name, s.price 
                                          FROM appointments a 
                                          JOIN users c ON a.customer_id = c.id 
                                          JOIN services s ON a.service_id = s.id 
                                          WHERE a.appointment_date = '$today' AND a.salon_id = $salon_id 
                                          ORDER BY a.appointment_time ASC LIMIT 5");

// ============================================
// QUEUE
// ============================================
$queue_query = mysqli_query($conn, "SELECT a.*, u.full_name as customer_name, s.service_name 
                                    FROM appointments a 
                                    JOIN users u ON a.customer_id = u.id 
                                    JOIN services s ON a.service_id = s.id 
                                    WHERE a.salon_id = $salon_id AND a.status NOT IN ('completed', 'cancelled', 'served') 
                                    ORDER BY a.appointment_date ASC, a.appointment_time ASC, a.queue_position ASC LIMIT 5");

// ============================================
// RECENT ACTIVITY
// ============================================
$recent_activity = [];

// Recent appointments
$recent_appointments = mysqli_query($conn, "SELECT a.*, c.full_name as customer_name, s.service_name 
                                            FROM appointments a 
                                            JOIN users c ON a.customer_id = c.id 
                                            JOIN services s ON a.service_id = s.id 
                                            WHERE a.salon_id = $salon_id 
                                            ORDER BY a.created_at DESC LIMIT 5");
while ($row = mysqli_fetch_assoc($recent_appointments)) {
    $row['message'] = "New appointment: <strong>" . htmlspecialchars($row['customer_name']) . "</strong> booked <strong>" . htmlspecialchars($row['service_name']) . "</strong>";
    $row['time'] = time_elapsed_string($row['created_at']);
    $row['type'] = 'appointment';
    $recent_activity[] = $row;
}

// Recent customers
$recent_customers = mysqli_query($conn, "SELECT full_name, created_at FROM users WHERE role = 'customer' AND salon_id = $salon_id ORDER BY created_at DESC LIMIT 3");
while ($row = mysqli_fetch_assoc($recent_customers)) {
    $row['message'] = "New customer registered: <strong>" . htmlspecialchars($row['full_name']) . "</strong>";
    $row['time'] = time_elapsed_string($row['created_at']);
    $row['type'] = 'customer';
    $recent_activity[] = $row;
}

// Sort by time (newest first)
usort($recent_activity, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
$recent_activity = array_slice($recent_activity, 0, 8);

// ============================================
// TIME ELAPSED FUNCTION
// ============================================
function time_elapsed_string($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

$user_name = $_SESSION['user_name'];
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
    .dashboard-header .plan-badge {
        display: inline-block;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: bold;
    }
    .plan-basic { background: rgba(23, 162, 184, 0.2); color: #17a2b8; border: 1px solid #17a2b8; }
    .plan-premium { background: rgba(212, 175, 55, 0.2); color: #d4af37; border: 1px solid #d4af37; }
    .plan-enterprise { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }

    /* ============================================
       STATS GRID
       ============================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
       QUEUE & ACTIVITY
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

    /* ============================================
       QUICK ACTIONS
       ============================================ */
    .quick-actions {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        margin-top: 1rem;
    }
    .quick-actions .btn {
        padding: 10px 22px;
        border-radius: 25px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s;
        font-size: 0.85rem;
    }
    .quick-actions .btn-primary {
        background: #d4af37;
        color: #050505;
    }
    .quick-actions .btn-primary:hover {
        background: #f9e547;
        transform: translateY(-2px);
    }
    .quick-actions .btn-outline {
        border: 1px solid #d4af37;
        color: #d4af37;
        background: transparent;
    }
    .quick-actions .btn-outline:hover {
        background: rgba(212, 175, 55, 0.1);
        transform: translateY(-2px);
    }

    /* ============================================
       UPGRADE BANNER
       ============================================ */
    .upgrade-banner {
        background: linear-gradient(135deg, #1a1a1a 0%, #2a1f0a 100%);
        border: 2px solid #d4af37;
        border-radius: 15px;
        padding: 1rem 1.5rem;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .upgrade-banner .btn-upgrade {
        background: #d4af37;
        color: #050505;
        padding: 8px 20px;
        border-radius: 25px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s;
    }
    .upgrade-banner .btn-upgrade:hover {
        background: #f9e547;
        transform: scale(1.05);
    }
    .upgrade-banner .btn-upgrade:disabled {
        background: #555;
        color: #888;
        cursor: not-allowed;
        transform: none;
    }
    .upgrade-banner .upgrade-text {
        color: #aaa;
        font-size: 0.9rem;
    }
    .upgrade-banner .upgrade-text strong {
        color: #d4af37;
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
        .quick-actions { flex-direction: column; }
        .quick-actions .btn { text-align: center; }
        .upgrade-banner { flex-direction: column; text-align: center; }
        .appointment-item { flex-direction: column; align-items: flex-start; }
        .queue-item { flex-direction: column; align-items: flex-start; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .stat-card .stat-number { font-size: 1.4rem; }
        .dashboard-header h1 { font-size: 1.2rem; }
    }
</style>

<div class="main-content">

    <!-- ============================================
       HEADER
       ============================================ -->
    <div class="dashboard-header">
        <div>
            <h1>👋 Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h1>
            <p>Here's your <?php echo htmlspecialchars($salon_name); ?> overview for today</p>
        </div>
        <div>
            <span class="plan-badge plan-<?php echo $current_plan; ?>">
                <?php echo ucfirst($current_plan); ?> Plan
            </span>
        </div>
    </div>

    <!-- ============================================
       UPGRADE BANNER
       ============================================ -->
    <?php if($upgrade_info): ?>
    <div class="upgrade-banner">
        <div class="upgrade-text">
            💡 <strong><?php echo $upgrade_info['message']; ?></strong>
            Upgrade to unlock: <?php echo implode(', ', $upgrade_info['features']); ?>
        </div>
        <a href="../super_admin/upgrade_plan.php?salon_id=<?php echo $salon_id; ?>&target=<?php echo $upgrade_info['target']; ?>" class="btn-upgrade">
            🔓 <?php echo $upgrade_info['button']; ?>
        </a>
    </div>
    <?php elseif($current_plan == 'enterprise'): ?>
    <div class="upgrade-banner" style="border-color: #28a745;">
        <div class="upgrade-text">
            🏆 <strong>You're on the Enterprise plan!</strong> You have access to all features. Enjoy the full experience!
        </div>
        <button class="btn-upgrade" disabled>🌟 Max Plan</button>
    </div>
    <?php endif; ?>

    <!-- ============================================
       STATS GRID
       ============================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-icon">📅</span>
            <div class="stat-number"><?php echo $total_appointments; ?></div>
            <div class="stat-label">Total Appointments</div>
            <span class="stat-trend neutral"><?php echo $total_appointments; ?> total</span>
        </div>
        <div class="stat-card">
            <span class="stat-icon">⏳</span>
            <div class="stat-number"><?php echo $pending_appointments; ?></div>
            <div class="stat-label">Pending</div>
            <span class="stat-trend <?php echo $pending_appointments > 0 ? 'down' : 'up'; ?>"><?php echo $pending_appointments; ?> pending</span>
        </div>
        <div class="stat-card">
            <span class="stat-icon">👤</span>
            <div class="stat-number"><?php echo $total_customers; ?></div>
            <div class="stat-label">Total Customers</div>
            <span class="stat-trend neutral"><?php echo $total_customers; ?> customers</span>
        </div>
        <div class="stat-card">
            <span class="stat-icon">💰</span>
            <div class="stat-number">KSh <?php echo number_format($total_revenue, 2); ?></div>
            <div class="stat-label">Total Revenue</div>
            <span class="stat-trend up"><?php echo number_format($total_revenue, 0); ?> revenue</span>
        </div>
    </div>

    <!-- ============================================
       QUICK ACTIONS
       ============================================ -->
    <div class="quick-actions">
        <a href="appointments.php" class="btn btn-primary"><i class="fas fa-calendar-plus"></i> View All Appointments</a>
        <a href="services.php" class="btn btn-outline"><i class="fas fa-cut"></i> Manage Services</a>
        <a href="staff.php" class="btn btn-outline"><i class="fas fa-users"></i> Manage Staff</a>
        <a href="reports.php" class="btn btn-outline"><i class="fas fa-chart-bar"></i> View Reports</a>
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
                🚀 Current Queue
                <a href="appointments.php" class="view-all">View All →</a>
            </h3>

            <?php if($queue_query && mysqli_num_rows($queue_query) > 0): ?>
                <?php while($q = mysqli_fetch_assoc($queue_query)): ?>
                    <div class="queue-item">
                        <span class="queue-position">#<?php echo $q['queue_position']; ?></span>
                        <div class="queue-details">
                            <div class="queue-customer"><?php echo htmlspecialchars($q['customer_name']); ?></div>
                            <div class="queue-service"><?php echo htmlspecialchars($q['service_name']); ?></div>
                        </div>
                        <span class="queue-time">⏰ <?php echo date('g:i A', strtotime($q['appointment_time'])); ?></span>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="color: #666; text-align: center; padding: 1.5rem;">
                    <p>✨ No customers waiting in queue. Great job!</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>