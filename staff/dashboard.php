<?php
// staff/dashboard.php - REDESIGNED with personal stats, queue, today's schedule
require_once '../config/database.php';

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
                ORDER BY a.appointment_time ASC LIMIT 5";
$today_appointments = mysqli_query($conn, $today_query);

// Get pending queue for this staff (limit 5)
$queue_query = "SELECT a.*, c.full_name as customer_name, s.service_name 
                FROM appointments a 
                JOIN users c ON a.customer_id = c.id 
                JOIN services s ON a.service_id = s.id 
                WHERE a.staff_id = $staff_id AND a.status = 'pending' 
                ORDER BY a.appointment_time ASC, a.queue_position ASC LIMIT 5";
$queue = mysqli_query($conn, $queue_query);

// Get staff statistics
$stats_query = "SELECT 
    COUNT(*) as total_appointments,
    SUM(CASE WHEN status = 'served' THEN 1 ELSE 0 END) as completed_today,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_today
    FROM appointments 
    WHERE staff_id = $staff_id AND appointment_date = '$today'";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get staff details (specialization, experience)
$staff_details_query = "SELECT u.full_name, u.email, u.phone, sd.specialty, sd.experience_years 
                        FROM users u 
                        LEFT JOIN staff_details sd ON u.id = sd.user_id 
                        WHERE u.id = $staff_id";
$staff_details_result = mysqli_query($conn, $staff_details_query);
$staff_details = mysqli_fetch_assoc($staff_details_result);

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
    .welcome-banner .staff-badge {
        display: inline-block;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: bold;
        background: rgba(212, 175, 55, 0.2);
        color: #d4af37;
        border: 1px solid #d4af37;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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

    .btn-serve {
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
    .btn-serve:hover {
        background: #218838;
        transform: scale(1.05);
    }

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

    /* Staff Profile Card */
    .profile-card {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        border: 1px solid rgba(212, 175, 55, 0.2);
        margin-bottom: 2rem;
    }
    .profile-card h3 {
        color: #d4af37;
        margin-bottom: 0.5rem;
    }
    .profile-card .specialty {
        color: #d4af37;
        font-size: 1.1rem;
    }
    .profile-card .detail {
        color: #aaa;
        font-size: 0.9rem;
        margin-top: 0.3rem;
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
        .appointment-card { flex-direction: column; align-items: flex-start; gap: 0.3rem; }
        .queue-item { flex-direction: column; align-items: flex-start; gap: 0.3rem; }
        .profile-card { text-align: center; }
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
            <h1>👋 Welcome back, <?php echo htmlspecialchars($staff_name); ?>!</h1>
            <p>Here's your schedule for today</p>
        </div>
        <div>
            <span class="staff-badge">✂️ <?php echo htmlspecialchars($staff_details['specialty'] ?? 'Stylist'); ?></span>
        </div>
    </div>

    <!-- Profile Card -->
    <div class="profile-card">
        <h3>👤 Your Profile</h3>
        <div class="specialty">✂️ <?php echo htmlspecialchars($staff_details['specialty'] ?? 'Professional Stylist'); ?></div>
        <div class="detail">📅 <?php echo $staff_details['experience_years'] ?? 0; ?>+ years experience</div>
        <div class="detail">📧 <?php echo htmlspecialchars($staff_details['email']); ?></div>
        <div class="detail">📞 <?php echo htmlspecialchars($staff_details['phone']); ?></div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number"><?php echo $stats['total_appointments'] ?? 0; ?></div>
            <div class="label">📅 Today's Appointments</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $stats['completed_today'] ?? 0; ?></div>
            <div class="label">✅ Completed Today</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $stats['pending_today'] ?? 0; ?></div>
            <div class="label">⏳ Pending</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo mysqli_num_rows($queue); ?></div>
            <div class="label">🚀 In Queue</div>
        </div>
    </div>

    <!-- Queue -->
    <h3 class="section-title">🚀 Your Current Queue <a href="appointments.php" class="view-all">View All →</a></h3>
    <?php if($queue && mysqli_num_rows($queue) > 0): ?>
        <?php while($q = mysqli_fetch_assoc($queue)): ?>
        <div class="queue-item">
            <div>
                <span class="position">#<?php echo $q['queue_position']; ?></span>
                <span class="customer"><?php echo htmlspecialchars($q['customer_name']); ?></span>
                <span class="service">· <?php echo htmlspecialchars($q['service_name']); ?></span>
            </div>
            <div>
                <span style="color: #d4af37; font-size: 0.8rem;">⏰ <?php echo date('g:i A', strtotime($q['appointment_time'])); ?></span>
                <form method="POST" action="appointments.php" style="display: inline; margin-left: 0.5rem;">
                    <input type="hidden" name="appointment_id" value="<?php echo $q['id']; ?>">
                    <input type="hidden" name="action" value="serve">
                    <button type="submit" class="btn-serve" onclick="return confirm('Mark this customer as served?')">✓ Serve</button>
                </form>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div style="color: #aaa; padding: 1rem; text-align: center; background: #1a1a1a; border-radius: 12px;">
            ✨ No customers waiting in your queue. Great job!
        </div>
    <?php endif; ?>

    <!-- Today's Schedule -->
    <h3 class="section-title" style="margin-top: 1.5rem;">📅 Today's Schedule <a href="appointments.php" class="view-all">View All →</a></h3>
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
                <?php if($apt['status'] != 'served' && $apt['status'] != 'cancelled'): ?>
                    <form method="POST" action="appointments.php" style="display: inline; margin-left: 0.5rem;">
                        <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                        <input type="hidden" name="action" value="serve">
                        <button type="submit" class="btn-serve" onclick="return confirm('Mark this customer as served?')">✓ Serve</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div style="color: #aaa; padding: 1rem; text-align: center; background: #1a1a1a; border-radius: 12px;">
            🎉 No appointments scheduled for today. Enjoy your free time!
        </div>
    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>