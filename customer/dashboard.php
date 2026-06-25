<?php
// customer/dashboard.php - REDESIGNED with personal stats, upcoming bookings, loyalty badge
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

// Get upcoming appointments (limit 5)
$upcoming_query = "SELECT a.*, s.service_name, s.price, u.full_name as staff_name 
                FROM appointments a 
                JOIN services s ON a.service_id = s.id 
                LEFT JOIN users u ON a.staff_id = u.id 
                WHERE a.customer_id = $user_id AND a.status NOT IN ('completed', 'cancelled', 'served')
                ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 5";
$upcoming = mysqli_query($conn, $upcoming_query);

// Get total spent
$spent_query = "SELECT SUM(amount) as total FROM payments p 
                JOIN appointments a ON p.appointment_id = a.id 
                WHERE a.customer_id = $user_id AND p.payment_status = 'paid'";
$spent_result = mysqli_query($conn, $spent_query);
$total_spent = ($spent_result && mysqli_fetch_assoc($spent_result)['total']) ?? 0;

// Get total bookings count
$total_bookings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments WHERE customer_id = $user_id"))['count'] ?? 0;

// Get loyalty points (simple calculation: 10 points per KSh 100 spent)
$loyalty_points = floor($total_spent / 100) * 10;
$loyalty_tier = 'Bronze';
if ($loyalty_points >= 500) {
    $loyalty_tier = 'Gold';
} elseif ($loyalty_points >= 200) {
    $loyalty_tier = 'Silver';
}

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

    /* Loyalty Badge */
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

    .view-all {
        color: #d4af37;
        text-decoration: none;
        font-size: 0.8rem;
        float: right;
    }
    .view-all:hover {
        text-decoration: underline;
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
        .quick-actions { flex-direction: column; }
        .quick-actions .btn { text-align: center; }
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
            <p>Here's your appointment overview</p>
        </div>
        <div>
            <span class="loyalty-badge loyalty-<?php echo strtolower($loyalty_tier); ?>">
                ⭐ <?php echo $loyalty_tier; ?> Member (<?php echo $loyalty_points; ?> pts)
            </span>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number"><?php echo $total_bookings; ?></div>
            <div class="label">📅 Total Bookings</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo mysqli_num_rows($upcoming); ?></div>
            <div class="label">⏳ Upcoming</div>
        </div>
        <div class="stat-card">
            <div class="number">KSh <?php echo number_format($total_spent, 2); ?></div>
            <div class="label">💰 Total Spent</div>
        </div>
        <div class="stat-card">
            <div class="number">⭐ <?php echo $loyalty_points; ?></div>
            <div class="label">🎯 Loyalty Points</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="book.php" class="btn btn-primary">📅 Book New Appointment</a>
        <a href="appointments.php" class="btn btn-outline">📋 My Appointments</a>
        <a href="update-profile.php" class="btn btn-outline">⚙️ Update Profile</a>
    </div>

    <!-- Upcoming Appointments -->
    <h3 class="section-title">📅 Upcoming Appointments <a href="appointments.php" class="view-all">View All →</a></h3>
    <?php if($upcoming && mysqli_num_rows($upcoming) > 0): ?>
        <?php while($apt = mysqli_fetch_assoc($upcoming)): ?>
        <div class="appointment-card">
            <div>
                <span class="service">💇 <?php echo htmlspecialchars($apt['service_name']); ?></span>
                <span class="details"><?php echo htmlspecialchars($apt['staff_name'] ?? 'Not Assigned'); ?> · <?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?> · <?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></span>
            </div>
            <div>
                <span class="price">KSh <?php echo number_format($apt['price'], 2); ?></span>
                <span class="status status-<?php echo $apt['status']; ?>"><?php echo ucfirst($apt['status']); ?></span>
                <?php if($apt['status'] == 'pending' || $apt['status'] == 'confirmed'): ?>
                    <a href="appointments.php?cancel=<?php echo $apt['id']; ?>" class="btn-cancel" onclick="return confirm('Cancel this appointment?')">Cancel</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div style="color: #aaa; padding: 1rem; text-align: center; background: #1a1a1a; border-radius: 12px;">
            📭 No upcoming appointments. <a href="book.php" style="color: #d4af37;">Book one now!</a>
        </div>
    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>