<?php
// customer/dashboard.php - REDESIGNED: Clean, Modern, Card-Based Layout for Customers
require_once '../config/database.php';

if (!isLoggedIn() || !isCustomer()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get customer details
$customer_query = "SELECT * FROM users WHERE id = $user_id";
$customer_result = mysqli_query($conn, $customer_query);
$customer = mysqli_fetch_assoc($customer_result);

// Get salon_id from customer record
$salon_id = $_SESSION['salon_id'] ?? 0;
if ($salon_id <= 0) {
    $user_query = mysqli_query($conn, "SELECT salon_id FROM users WHERE id = $user_id");
    if ($user_result = mysqli_fetch_assoc($user_query)) {
        $salon_id = $user_result['salon_id'];
        $_SESSION['salon_id'] = $salon_id;
    }
}

// ============================================
// CHECK SUBSCRIPTION STATUS
// ============================================
$salon_active = true;
$salon_status_message = '';

if ($salon_id > 0) {
    $salon_query = mysqli_query($conn, "SELECT salon_name, subscription_expiry, subscription_status FROM salons WHERE id = $salon_id");
    if ($salon_data = mysqli_fetch_assoc($salon_query)) {
        $subscription_status = $salon_data['subscription_status'];
        $expiry_date = $salon_data['subscription_expiry'];
        
        if ($subscription_status == 'expired' || $subscription_status == 'suspended') {
            $salon_active = false;
            $salon_status_message = "This salon is currently unavailable. Please contact the salon owner for assistance.";
        } elseif (!empty($expiry_date) && $expiry_date < date('Y-m-d')) {
            $salon_active = false;
            $salon_status_message = "This salon is currently unavailable. Please contact the salon owner for assistance.";
        }
    }
}

// ============================================
// STATISTICS
// ============================================
$upcoming_query = "SELECT a.*, s.service_name, s.price, u.full_name as staff_name 
                   FROM appointments a 
                   JOIN services s ON a.service_id = s.id 
                   LEFT JOIN users u ON a.staff_id = u.id 
                   WHERE a.customer_id = $user_id AND a.status NOT IN ('completed', 'cancelled', 'served')
                   ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 5";
$upcoming = mysqli_query($conn, $upcoming_query);

$total_bookings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments WHERE customer_id = $user_id"))['count'] ?? 0;

$spent_query = "SELECT SUM(amount) as total FROM payments p 
                JOIN appointments a ON p.appointment_id = a.id 
                WHERE a.customer_id = $user_id AND p.payment_status = 'paid'";
$spent_result = mysqli_query($conn, $spent_query);
$total_spent = ($spent_result && mysqli_fetch_assoc($spent_result)['total']) ?? 0;

// Loyalty points (10 points per KSh 100 spent)
$loyalty_points = floor($total_spent / 100) * 10;
$loyalty_tier = 'Bronze';
if ($loyalty_points >= 500) {
    $loyalty_tier = 'Gold';
} elseif ($loyalty_points >= 200) {
    $loyalty_tier = 'Silver';
}

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

    .loyalty-badge {
        display: inline-block;
        padding: 8px 20px;
        border-radius: 50px;
        font-weight: bold;
        font-size: 0.9rem;
    }
    .loyalty-gold {
        background: linear-gradient(135deg, #d4af37, #f9e547);
        color: #050505;
        border: 1px solid #d4af37;
    }
    .loyalty-silver {
        background: linear-gradient(135deg, #aaa, #ddd);
        color: #050505;
        border: 1px solid #aaa;
    }
    .loyalty-bronze {
        background: linear-gradient(135deg, #cd7f32, #e8a866);
        color: #050505;
        border: 1px solid #cd7f32;
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

    /* ============================================
       UPCOMING APPOINTMENTS
       ============================================ */
    .appointments-card {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        border: 1px solid rgba(212, 175, 55, 0.15);
        margin-bottom: 2rem;
    }
    .appointments-card h3 {
        color: #d4af37;
        font-size: 1rem;
        margin-bottom: 1.2rem;
    }
    .appointments-card .view-all {
        color: #d4af37;
        text-decoration: none;
        font-size: 0.8rem;
        float: right;
    }
    .appointments-card .view-all:hover {
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
    .appointment-item .appt-date {
        color: #d4af37;
        font-weight: 600;
        font-size: 0.8rem;
        min-width: 80px;
    }
    .appointment-item .appt-details {
        flex: 1;
    }
    .appointment-item .appt-details .appt-service {
        font-weight: 500;
        font-size: 0.95rem;
    }
    .appointment-item .appt-details .appt-staff {
        color: #aaa;
        font-size: 0.8rem;
    }
    .appointment-item .appt-price {
        color: #d4af37;
        font-weight: bold;
        font-size: 0.9rem;
        min-width: 70px;
        text-align: right;
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

    .btn-cancel {
        background: #dc3545;
        color: white;
        border: none;
        padding: 4px 12px;
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
    .btn-cancel:disabled {
        background: #555;
        cursor: not-allowed;
        transform: none;
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
    .quick-actions .btn-primary:disabled {
        background: #555;
        color: #888;
        cursor: not-allowed;
        transform: none;
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
       SALON UNAVAILABLE BANNER
       ============================================ */
    .unavailable-banner {
        background: rgba(212, 175, 55, 0.15);
        border: 1px solid #d4af37;
        border-radius: 12px;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
        color: #d4af37;
        text-align: center;
    }
    .unavailable-banner .icon {
        font-size: 2.5rem;
        display: block;
        margin-bottom: 0.5rem;
    }
    .unavailable-banner .message {
        font-size: 1rem;
    }
    .unavailable-banner .message strong {
        font-weight: 600;
    }
    .unavailable-banner .sub-message {
        font-size: 0.9rem;
        color: #d4af37;
        opacity: 0.8;
        margin-top: 0.5rem;
    }

    /* ============================================
       RESPONSIVE
       ============================================ */
    @media (max-width: 1024px) {
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
        .appointment-item .appt-price { text-align: left; }
        .quick-actions { flex-direction: column; }
        .quick-actions .btn { text-align: center; }
        .unavailable-banner { padding: 1rem; }
        .unavailable-banner .message { font-size: 1rem; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .stat-card .stat-number { font-size: 1.4rem; }
        .dashboard-header h1 { font-size: 1.2rem; }
        .unavailable-banner .message { font-size: 0.9rem; }
    }
</style>

<div class="main-content">

    <!-- ============================================
       SALON UNAVAILABLE BANNER (If subscription expired)
       ============================================ -->
    <?php if (!$salon_active): ?>
    <div class="unavailable-banner">
        <div class="icon">⏳</div>
        <div class="message">
            <strong>Salon Temporarily Unavailable</strong>
        </div>
        <div class="sub-message">
            <?php echo $salon_status_message; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================
       HEADER
       ============================================ -->
    <div class="dashboard-header">
        <div>
            <h1>👋 Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h1>
            <p>Here's your appointment overview</p>
        </div>
        <div>
            <span class="loyalty-badge loyalty-<?php echo strtolower($loyalty_tier); ?>">
                ⭐ <?php echo $loyalty_tier; ?> Member (<?php echo $loyalty_points; ?> pts)
            </span>
        </div>
    </div>

    <!-- ============================================
       STATS GRID
       ============================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-icon">📅</span>
            <div class="stat-number"><?php echo $total_bookings; ?></div>
            <div class="stat-label">Total Bookings</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">⏳</span>
            <div class="stat-number"><?php echo mysqli_num_rows($upcoming); ?></div>
            <div class="stat-label">Upcoming</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">💰</span>
            <div class="stat-number">KSh <?php echo number_format($total_spent, 2); ?></div>
            <div class="stat-label">Total Spent</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">⭐</span>
            <div class="stat-number"><?php echo $loyalty_points; ?></div>
            <div class="stat-label">Loyalty Points</div>
        </div>
    </div>

    <!-- ============================================
       QUICK ACTIONS
       ============================================ -->
    <div class="quick-actions">
        <a href="book.php" class="btn btn-primary" <?php echo !$salon_active ? 'disabled' : ''; ?>>
            <i class="fas fa-calendar-plus"></i> Book New Appointment
        </a>
        <a href="appointments.php" class="btn btn-outline">
            <i class="fas fa-list"></i> My Appointments
        </a>
        <a href="update-profile.php" class="btn btn-outline">
            <i class="fas fa-user-cog"></i> Update Profile
        </a>
    </div>

    <!-- ============================================
       UPCOMING APPOINTMENTS
       ============================================ -->
    <div class="appointments-card">
        <h3>
            📅 Upcoming Appointments
            <a href="appointments.php" class="view-all">View All →</a>
        </h3>

        <?php if($upcoming && mysqli_num_rows($upcoming) > 0): ?>
            <?php while($apt = mysqli_fetch_assoc($upcoming)): ?>
                <div class="appointment-item">
                    <span class="appt-date"><?php echo date('M d', strtotime($apt['appointment_date'])); ?></span>
                    <div class="appt-details">
                        <div class="appt-service">💇 <?php echo htmlspecialchars($apt['service_name']); ?></div>
                        <div class="appt-staff"><?php echo htmlspecialchars($apt['staff_name'] ?? 'Not Assigned'); ?> · <?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></div>
                    </div>
                    <div>
                        <span class="appt-price">KSh <?php echo number_format($apt['price'], 2); ?></span>
                        <span class="appt-status <?php echo $apt['status']; ?>"><?php echo ucfirst($apt['status']); ?></span>
                        <?php if(($apt['status'] == 'pending' || $apt['status'] == 'confirmed') && $salon_active): ?>
                            <a href="appointments.php?cancel=<?php echo $apt['id']; ?>" class="btn-cancel" onclick="return confirm('Cancel this appointment?')">Cancel</a>
                        <?php elseif(!$salon_active): ?>
                            <span style="color: #888; font-size: 0.65rem; margin-left: 0.5rem;">Cancellation unavailable</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="color: #666; text-align: center; padding: 1.5rem;">
                <p>📭 No upcoming appointments.</p>
                <a href="book.php" style="color: #d4af37; text-decoration: none; <?php echo !$salon_active ? 'pointer-events: none; opacity: 0.5;' : ''; ?>">
                    Book one now! →
                </a>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php include '../includes/footer.php'; ?>