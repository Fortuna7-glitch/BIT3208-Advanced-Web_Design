<?php
// customer/dashboard.php - CUSTOMER FULL ACCESS: No permission checks
require_once '../config/database.php';

if (!isLoggedIn() || !isCustomer()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// ============================================
// GET UPCOMING APPOINTMENTS
// ============================================
$upcoming_query = "SELECT a.*, s.service_name, s.price, u.full_name as staff_name 
                   FROM appointments a 
                   JOIN services s ON a.service_id = s.id 
                   LEFT JOIN users u ON a.staff_id = u.id 
                   WHERE a.customer_id = $user_id AND a.status NOT IN ('completed', 'cancelled', 'served')
                   ORDER BY a.appointment_date ASC, a.appointment_time ASC";
$upcoming = mysqli_query($conn, $upcoming_query);

// ============================================
// GET APPOINTMENT HISTORY
// ============================================
$history_query = "SELECT a.*, s.service_name, s.price, u.full_name as staff_name 
                  FROM appointments a 
                  JOIN services s ON a.service_id = s.id 
                  LEFT JOIN users u ON a.staff_id = u.id 
                  WHERE a.customer_id = $user_id AND a.status IN ('completed', 'served')
                  ORDER BY a.appointment_date DESC LIMIT 5";
$history = mysqli_query($conn, $history_query);

// ============================================
// GET TOTAL SPENT
// ============================================
$spent_query = "SELECT SUM(amount) as total FROM payments p 
                JOIN appointments a ON p.appointment_id = a.id 
                WHERE a.customer_id = $user_id AND p.payment_status = 'paid'";
$spent_result = mysqli_query($conn, $spent_query);
$total_spent = mysqli_fetch_assoc($spent_result)['total'] ?? 0;

// ============================================
// GET CART COUNT
// ============================================
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cart_count = array_sum($_SESSION['cart']);
}

include '../includes/header.php';
?>

<style>
    .main-content {
        padding: 2rem;
        background: #0a0a0a;
        min-height: 100vh;
    }

    /* ============================================
       HEADER WITH QUICK ACTIONS & SEARCH
       ============================================ */
    .dashboard-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }

    .dashboard-header .title-section h1 {
        color: #d4af37;
        font-size: 1.5rem;
        font-family: 'Playfair Display', serif;
    }

    .dashboard-header .title-section p {
        color: #aaa;
        font-size: 0.85rem;
    }

    .dashboard-header .quick-actions {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .dashboard-header .quick-actions .quick-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 8px 16px;
        border-radius: 25px;
        font-size: 0.75rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s;
        background: rgba(212, 175, 55, 0.1);
        color: #d4af37;
        border: 1px solid rgba(212, 175, 55, 0.2);
        position: relative;
    }

    .dashboard-header .quick-actions .quick-btn:hover {
        background: #d4af37;
        color: #050505;
        transform: translateY(-2px);
    }

    .dashboard-header .quick-actions .quick-btn i {
        font-size: 0.8rem;
    }

    .dashboard-header .quick-actions .quick-btn .badge {
        position: absolute;
        top: -6px;
        right: -6px;
        background: #dc3545;
        color: white;
        font-size: 0.55rem;
        font-weight: bold;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .dashboard-header .search-section {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex: 0 1 280px;
        min-width: 160px;
    }

    .dashboard-header .search-section input {
        padding: 8px 14px;
        background: #1a1a1a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        color: white;
        font-size: 0.85rem;
        width: 100%;
        transition: all 0.3s;
    }

    .dashboard-header .search-section input:focus {
        outline: none;
        border-color: #d4af37;
        box-shadow: 0 0 20px rgba(212, 175, 55, 0.1);
    }

    .dashboard-header .search-section input::placeholder {
        color: #666;
    }

    .dashboard-header .search-section .search-btn {
        padding: 8px 14px;
        background: #d4af37;
        border: none;
        border-radius: 25px;
        color: #050505;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
        white-space: nowrap;
    }

    .dashboard-header .search-section .search-btn:hover {
        background: #f9e547;
    }

    /* ============================================
       STATS GRID
       ============================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: #1a1a1a;
        border-radius: 12px;
        padding: 1.2rem 1.5rem;
        border-left: 4px solid #d4af37;
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(212, 175, 55, 0.08);
    }

    .stat-card .stat-icon {
        font-size: 1.5rem;
        opacity: 0.2;
        position: absolute;
        right: 1rem;
        top: 1rem;
    }

    .stat-card .stat-number {
        font-size: 2rem;
        font-weight: bold;
        color: #d4af37;
    }

    .stat-card .stat-label {
        color: #aaa;
        font-size: 0.8rem;
        margin-top: 0.2rem;
    }

    .stat-card.green { border-left-color: #28a745; }
    .stat-card.green .stat-number { color: #28a745; }
    .stat-card.orange { border-left-color: #ffc107; }
    .stat-card.orange .stat-number { color: #ffc107; }

    /* ============================================
       SECTION CARDS
       ============================================ */
    .section-card {
        background: #1a1a1a;
        border-radius: 12px;
        padding: 1.2rem 1.5rem;
        border: 1px solid rgba(212, 175, 55, 0.1);
        margin-bottom: 1.5rem;
    }

    .section-card h3 {
        color: #d4af37;
        font-size: 1rem;
        margin-bottom: 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .section-card h3 .view-all {
        font-size: 0.7rem;
        color: #888;
        text-decoration: none;
        font-weight: 400;
    }

    .section-card h3 .view-all:hover {
        color: #d4af37;
    }

    .appointment-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.6rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        flex-wrap: wrap;
        gap: 0.3rem;
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
        font-size: 0.9rem;
    }

    .appointment-item .appt-details .appt-staff {
        color: #aaa;
        font-size: 0.75rem;
    }

    .appointment-item .appt-status {
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 500;
    }

    .appt-status.confirmed { background: rgba(40, 167, 69, 0.15); color: #28a745; border: 1px solid #28a745; }
    .appt-status.pending { background: rgba(212, 175, 55, 0.15); color: #d4af37; border: 1px solid #d4af37; }
    .appt-status.completed { background: rgba(23, 162, 184, 0.15); color: #17a2b8; border: 1px solid #17a2b8; }
    .appt-status.cancelled { background: rgba(220, 53, 69, 0.15); color: #dc3545; border: 1px solid #dc3545; }
    .appt-status.served { background: rgba(40, 167, 69, 0.15); color: #28a745; border: 1px solid #28a745; }

    .empty-state {
        color: #666;
        text-align: center;
        padding: 1.5rem 0;
        font-size: 0.9rem;
    }

    .action-buttons {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .action-buttons .btn {
        padding: 10px 25px;
        border-radius: 25px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s;
    }

    .btn-primary {
        background: #d4af37;
        color: #050505;
    }

    .btn-primary:hover {
        background: #f9e547;
        transform: translateY(-2px);
    }

    .btn-outline {
        background: transparent;
        color: #d4af37;
        border: 1px solid #d4af37;
    }

    .btn-outline:hover {
        background: #d4af37;
        color: #050505;
        transform: translateY(-2px);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .main-content { padding: 1rem; }
        .dashboard-header {
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
        }
        .dashboard-header .title-section h1 { font-size: 1.2rem; }
        .dashboard-header .quick-actions { justify-content: flex-start; }
        .dashboard-header .search-section { flex: 1; }
        .dashboard-header .search-section input { width: 100%; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 0.8rem; }
        .stat-card { padding: 1rem; }
        .stat-card .stat-number { font-size: 1.4rem; }
        .section-card { padding: 1rem; }
        .action-buttons { flex-direction: column; }
        .action-buttons .btn { text-align: center; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .dashboard-header .quick-actions .quick-btn { font-size: 0.65rem; padding: 6px 12px; }
        .appointment-item { flex-direction: column; align-items: flex-start; }
    }
</style>

<div class="main-content">

    <!-- ============================================
       HEADER: Title + Quick Actions + Search
       ============================================ -->
    <div class="dashboard-header">
        <!-- Left: Title -->
        <div class="title-section">
            <h1>👋 Welcome, <?php echo htmlspecialchars($user_name); ?>!</h1>
            <p>Your beauty journey starts here</p>
        </div>

        <!-- Center/Right: Quick Actions -->
        <div class="quick-actions">
            <a href="book.php" class="quick-btn"><i class="fas fa-calendar-plus"></i> Book</a>
            <a href="shop.php" class="quick-btn"><i class="fas fa-store"></i> Shop</a>
            <a href="cart.php" class="quick-btn">
                <i class="fas fa-shopping-cart"></i> Cart
                <?php if ($cart_count > 0): ?>
                    <span class="badge"><?php echo $cart_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="appointments.php" class="quick-btn"><i class="fas fa-list"></i> Appointments</a>
        </div>

        <!-- Right: Search -->
        <div class="search-section">
            <form method="GET" action="../search.php" style="display: flex; gap: 0.5rem; width: 100%;">
                <input type="text" name="q" placeholder="🔍 Search..." aria-label="Search">
                <button type="submit" class="search-btn">Search</button>
            </form>
        </div>
    </div>

    <!-- ============================================
       STATISTICS
       ============================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-icon">📅</span>
            <div class="stat-number"><?php echo mysqli_num_rows($upcoming); ?></div>
            <div class="stat-label">Upcoming Appointments</div>
        </div>
        <div class="stat-card green">
            <span class="stat-icon">💰</span>
            <div class="stat-number">KSh <?php echo number_format($total_spent, 2); ?></div>
            <div class="stat-label">Total Spent</div>
        </div>
        <div class="stat-card orange">
            <span class="stat-icon">⭐</span>
            <div class="stat-number">✨</div>
            <div class="stat-label">Loyalty Points</div>
        </div>
    </div>

    <!-- ============================================
       UPCOMING APPOINTMENTS
       ============================================ -->
    <div class="section-card">
        <h3>
            📅 Upcoming Appointments
            <a href="appointments.php" class="view-all">View All →</a>
        </h3>
        <?php if(mysqli_num_rows($upcoming) > 0): ?>
            <?php while($apt = mysqli_fetch_assoc($upcoming)): ?>
                <div class="appointment-item">
                    <span class="appt-date"><?php echo date('M d', strtotime($apt['appointment_date'])); ?></span>
                    <div class="appt-details">
                        <div class="appt-service"><?php echo htmlspecialchars($apt['service_name']); ?></div>
                        <div class="appt-staff"><?php echo htmlspecialchars($apt['staff_name'] ?? 'Not Assigned'); ?></div>
                    </div>
                    <span class="appt-status <?php echo $apt['status']; ?>"><?php echo ucfirst($apt['status']); ?></span>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">No upcoming appointments. <a href="book.php" style="color: #d4af37;">Book one now!</a></div>
        <?php endif; ?>
    </div>

    <!-- ============================================
       RECENT HISTORY
       ============================================ -->
    <div class="section-card">
        <h3>
            📋 Recent History
            <a href="appointments.php" class="view-all">View All →</a>
        </h3>
        <?php if(mysqli_num_rows($history) > 0): ?>
            <?php while($apt = mysqli_fetch_assoc($history)): ?>
                <div class="appointment-item">
                    <span class="appt-date"><?php echo date('M d', strtotime($apt['appointment_date'])); ?></span>
                    <div class="appt-details">
                        <div class="appt-service"><?php echo htmlspecialchars($apt['service_name']); ?></div>
                        <div class="appt-staff"><?php echo htmlspecialchars($apt['staff_name'] ?? 'N/A'); ?></div>
                    </div>
                    <span class="appt-status <?php echo $apt['status']; ?>"><?php echo ucfirst($apt['status']); ?></span>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">No appointment history yet.</div>
        <?php endif; ?>
    </div>

    <!-- ============================================
       QUICK ACTION BUTTONS
       ============================================ -->
    <div class="action-buttons">
        <a href="book.php" class="btn btn-primary">✨ Book New Appointment</a>
        <a href="shop.php" class="btn btn-primary" style="background: #6f42c1;">🛍️ Browse Products</a>
        <a href="appointments.php" class="btn btn-outline">View All Appointments</a>
    </div>

</div>

<?php include '../includes/footer.php'; ?>