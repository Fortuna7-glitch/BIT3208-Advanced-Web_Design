<?php
// admin/dashboard.php - REDESIGNED with Luxe/Aurora-style layout
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

$salon_query = mysqli_query($conn, "SELECT salon_name, subscription_plan FROM salons WHERE id = $salon_id");
$salon_data = mysqli_fetch_assoc($salon_query);
$current_plan = $salon_data['subscription_plan'] ?? 'basic';
$salon_name = $salon_data['salon_name'] ?? '';

$upgrade_info = getUpgradeMessage($current_plan);

// Stats
$total_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'customer' AND salon_id = $salon_id"))['count'] ?? 0;
$total_appointments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments WHERE salon_id = $salon_id"))['count'] ?? 0;
$total_revenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM payments WHERE payment_status = 'paid' AND salon_id = $salon_id"))['total'] ?? 0;
$pending_appointments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments WHERE (status = 'pending' OR status = 'confirmed') AND salon_id = $salon_id"))['count'] ?? 0;

// Get today's appointments (limit 5)
$today = date('Y-m-d');
$today_appointments = mysqli_query($conn, "SELECT a.*, c.full_name as customer_name, c.phone as customer_phone, s.service_name, s.price 
                                          FROM appointments a 
                                          JOIN users c ON a.customer_id = c.id 
                                          JOIN services s ON a.service_id = s.id 
                                          WHERE a.appointment_date = '$today' AND a.salon_id = $salon_id 
                                          ORDER BY a.appointment_time ASC LIMIT 5");

// Get queue (limit 5)
$queue_query = mysqli_query($conn, "SELECT a.*, u.full_name as customer_name, s.service_name 
                                    FROM appointments a 
                                    JOIN users u ON a.customer_id = u.id 
                                    JOIN services s ON a.service_id = s.id 
                                    WHERE a.salon_id = $salon_id AND a.status NOT IN ('completed', 'cancelled', 'served') 
                                    ORDER BY a.appointment_date ASC, a.appointment_time ASC, a.queue_position ASC LIMIT 5");

$user_name = $_SESSION['user_name'];

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
    .main-content {
        padding: 2rem;
        background: #0a0a0a;
        min-height: 100vh;
    }

    /* Welcome Banner */
    .welcome-banner {
        background: linear-gradient(135deg, #1a1a1a 0%, #2a1f0a 100%);
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 15px;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }
    .welcome-banner h1 {
        color: #d4af37;
        font-size: 1.6rem;
        font-family: 'Playfair Display', serif;
        margin-bottom: 0.3rem;
    }
    .welcome-banner p {
        color: #aaa;
        font-size: 0.9rem;
    }
    .welcome-banner .plan-badge {
        display: inline-block;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: bold;
    }
    .plan-basic { background: rgba(23, 162, 184, 0.2); color: #17a2b8; border: 1px solid #17a2b8; }
    .plan-premium { background: rgba(212, 175, 55, 0.2); color: #d4af37; border: 1px solid #d4af37; }
    .plan-enterprise { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }

    /* Upgrade Banner */
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

    /* Stats Grid */
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
        text-align: center;
        border-left: 4px solid #d4af37;
        transition: all 0.3s;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(212, 175, 55, 0.1);
    }
    .stat-card .number {
        font-size: 2.2rem;
        font-weight: bold;
        color: #d4af37;
    }
    .stat-card .label {
        color: #aaa;
        margin-top: 0.3rem;
        font-size: 0.85rem;
    }

    /* Section Title */
    .section-title {
        color: #d4af37;
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 1rem;
        border-left: 3px solid #d4af37;
        padding-left: 1rem;
    }

    /* Quick Actions */
    .quick-actions {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 2rem;
    }
    .quick-actions .btn {
        padding: 10px 20px;
        border-radius: 25px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s;
        font-size: 0.9rem;
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

    /* Appointment Cards */
    .appointment-card {
        background: #1a1a1a;
        border-radius: 12px;
        padding: 0.8rem 1rem;
        margin-bottom: 0.6rem;
        border: 1px solid rgba(212, 175, 55, 0.15);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        transition: all 0.3s;
    }
    .appointment-card:hover {
        border-color: #d4af37;
        background: #252525;
    }
    .appointment-card .service {
        font-weight: 600;
        font-size: 0.95rem;
    }
    .appointment-card .details {
        color: #aaa;
        font-size: 0.8rem;
    }
    .appointment-card .price {
        color: #d4af37;
        font-weight: bold;
        font-size: 0.9rem;
    }
    .appointment-card .status {
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 500;
    }
    .status-confirmed { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }
    .status-pending { background: rgba(212, 175, 55, 0.2); color: #d4af37; border: 1px solid #d4af37; }
    .status-completed { background: rgba(23, 162, 184, 0.2); color: #17a2b8; border: 1px solid #17a2b8; }
    .status-cancelled { background: rgba(220, 53, 69, 0.2); color: #dc3545; border: 1px solid #dc3545; }
    .status-served { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }

    .view-all {
        color: #d4af37;
        text-decoration: none;
        font-size: 0.8rem;
        float: right;
    }
    .view-all:hover {
        text-decoration: underline;
    }

    /* Queue Item */
    .queue-item {
        background: #1a1a1a;
        border-radius: 10px;
        padding: 0.8rem 1rem;
        margin-bottom: 0.6rem;
        border-left: 3px solid #d4af37;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }
    .queue-item .customer {
        font-weight: 600;
        font-size: 0.95rem;
    }
    .queue-item .service {
        color: #aaa;
        font-size: 0.8rem;
    }
    .queue-item .position {
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
    }

    /* ============================================
       RESPONSIVE
       ============================================ */
    @media (max-width: 1024px) {
        .stats-grid { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
    }

    @media (max-width: 768px) {
        .main-content { padding: 1rem; }
        .welcome-banner { flex-direction: column; text-align: center; gap: 0.5rem; }
        .welcome-banner h1 { font-size: 1.3rem; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 1rem; }
        .stat-card .number { font-size: 1.8rem; }
        .upgrade-banner { flex-direction: column; text-align: center; }
        .quick-actions { flex-direction: column; }
        .quick-actions .btn { text-align: center; }
        .appointment-card { flex-direction: column; align-items: flex-start; gap: 0.3rem; }
        .queue-item { flex-direction: column; align-items: flex-start; gap: 0.3rem; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .welcome-banner h1 { font-size: 1.1rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .stat-card .number { font-size: 1.6rem; }
    }
</style>

<div class="main-content">

    <!-- Welcome Banner -->
    <div class="welcome-banner">
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

    <!-- Upgrade Banner -->
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

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number"><?php echo $total_appointments; ?></div>
            <div class="label">📅 Total Appointments</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $pending_appointments; ?></div>
            <div class="label">⏳ Pending</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $total_customers; ?></div>
            <div class="label">👤 Total Customers</div>
        </div>
        <div class="stat-card">
            <div class="number">KSh <?php echo number_format($total_revenue, 2); ?></div>
            <div class="label">💰 Total Revenue</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="appointments.php" class="btn btn-primary">📅 View All Appointments</a>
        <a href="services.php" class="btn btn-outline">💇 Manage Services</a>
        <a href="staff.php" class="btn btn-outline">👥 Manage Staff</a>
        <a href="reports.php" class="btn btn-outline">📈 View Reports</a>
    </div>

    <!-- Today's Appointments -->
    <h3 class="section-title">📅 Today's Schedule <a href="appointments.php" class="view-all">View All →</a></h3>
    <?php if($today_appointments && mysqli_num_rows($today_appointments) > 0): ?>
        <?php while($apt = mysqli_fetch_assoc($today_appointments)): ?>
        <div class="appointment-card">
            <div>
                <span class="service">💇 <?php echo htmlspecialchars($apt['service_name']); ?></span>
                <span class="details"><?php echo htmlspecialchars($apt['customer_name']); ?> · <?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></span>
            </div>
            <div>
                <span class="price">KSh <?php echo number_format($apt['price'], 2); ?></span>
                <span class="status status-<?php echo $apt['status']; ?>"><?php echo ucfirst($apt['status']); ?></span>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div style="color: #aaa; padding: 1rem; text-align: center; background: #1a1a1a; border-radius: 12px;">
            🎉 No appointments scheduled for today. Enjoy your free time!
        </div>
    <?php endif; ?>

    <!-- Queue -->
    <h3 class="section-title" style="margin-top: 1.5rem;">🚀 Current Queue <a href="appointments.php" class="view-all">View All →</a></h3>
    <?php if($queue_query && mysqli_num_rows($queue_query) > 0): ?>
        <?php while($q = mysqli_fetch_assoc($queue_query)): ?>
        <div class="queue-item">
            <div>
                <span class="position">#<?php echo $q['queue_position']; ?></span>
                <span class="customer"><?php echo htmlspecialchars($q['customer_name']); ?></span>
                <span class="service">· <?php echo htmlspecialchars($q['service_name']); ?></span>
            </div>
            <div>
                <span style="color: #d4af37; font-size: 0.8rem;">⏰ <?php echo date('g:i A', strtotime($q['appointment_time'])); ?></span>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div style="color: #aaa; padding: 1rem; text-align: center; background: #1a1a1a; border-radius: 12px;">
            ✨ No customers waiting in queue. Great job!
        </div>
    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>