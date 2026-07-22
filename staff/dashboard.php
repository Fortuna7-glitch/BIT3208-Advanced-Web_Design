<?php
// staff/dashboard.php - MODIFIED: Quick actions layout + Permission-based visibility
require_once '../config/database.php';
require_once '../includes/permissions.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

$staff_id = $_SESSION['user_id'];
$staff_name = $_SESSION['user_name'];

// Get salon_id from session
$salon_id = getCurrentSalonId();

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
// STATISTICS (Permission-based for staff)
// ============================================

$today = date('Y-m-d');

// Today's Appointments (always visible to staff)
$stats_query = "SELECT 
    COUNT(*) as total_appointments,
    SUM(CASE WHEN status = 'served' THEN 1 ELSE 0 END) as completed_today,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_today
    FROM appointments 
    WHERE staff_id = $staff_id AND appointment_date = '$today'";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Total Customers (if permission granted)
$total_customers = 0;
if (hasPermission($staff_id, 'view_customers')) {
    $customers_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'customer' AND is_active = 1");
    $total_customers = mysqli_fetch_assoc($customers_query)['count'] ?? 0;
}

// Total Revenue (if permission granted)
$total_revenue = 0;
if (hasPermission($staff_id, 'view_payments')) {
    $revenue_query = mysqli_query($conn, "SELECT SUM(amount) as total FROM payments WHERE payment_status = 'paid'");
    $total_revenue = mysqli_fetch_assoc($revenue_query)['total'] ?? 0;
}

// Queue Count (if permission granted)
$queue_count = 0;
if (hasPermission($staff_id, 'view_assigned_appointments') || hasPermission($staff_id, 'view_all_appointments')) {
    $queue_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments WHERE staff_id = $staff_id AND status NOT IN ('completed', 'cancelled', 'served')");
    $queue_count = mysqli_fetch_assoc($queue_query)['count'] ?? 0;
}

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

// ============================================
// LOW STOCK PRODUCTS (if permission granted)
// ============================================
$low_stock = null;
if (hasPermission($staff_id, 'view_inventory')) {
    $low_stock = mysqli_query($conn, "SELECT * FROM products WHERE stock <= 5 ORDER BY stock ASC LIMIT 5");
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

    .dashboard-header .title-section .staff-badge {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: bold;
        background: rgba(212, 175, 55, 0.2);
        color: #d4af37;
        border: 1px solid #d4af37;
        margin-top: 0.3rem;
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
    }

    .dashboard-header .quick-actions .quick-btn:hover {
        background: #d4af37;
        color: #050505;
        transform: translateY(-2px);
    }

    .dashboard-header .quick-actions .quick-btn i {
        font-size: 0.8rem;
    }

    .dashboard-header .quick-actions .quick-btn.disabled {
        opacity: 0.3;
        cursor: not-allowed;
        pointer-events: none;
    }

    .dashboard-header .search-section {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex: 0 1 300px;
        min-width: 180px;
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
    .stat-card.blue { border-left-color: #17a2b8; }
    .stat-card.blue .stat-number { color: #17a2b8; }
    .stat-card.purple { border-left-color: #6f42c1; }
    .stat-card.purple .stat-number { color: #6f42c1; }

    /* ============================================
       PROFILE CARD
       ============================================ */
    .profile-card {
        background: #1a1a1a;
        border-radius: 12px;
        padding: 1.2rem 1.5rem;
        border: 1px solid rgba(212, 175, 55, 0.1);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1.5rem;
        flex-wrap: wrap;
    }

    .profile-card .avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: #d4af37;
        color: #050505;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        font-weight: bold;
        flex-shrink: 0;
    }

    .profile-card .profile-info h3 {
        color: #d4af37;
        font-size: 1rem;
        margin-bottom: 0.1rem;
    }

    .profile-card .profile-info .specialty {
        color: #d4af37;
        font-size: 0.85rem;
    }

    .profile-card .profile-info .detail {
        color: #aaa;
        font-size: 0.8rem;
    }

    /* ============================================
       GRID LAYOUT
       ============================================ */
    .dashboard-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .dashboard-grid .card {
        background: #1a1a1a;
        border-radius: 12px;
        padding: 1.2rem 1.5rem;
        border: 1px solid rgba(212, 175, 55, 0.1);
    }

    .dashboard-grid .card h3 {
        color: #d4af37;
        font-size: 1rem;
        margin-bottom: 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .dashboard-grid .card h3 .view-all {
        font-size: 0.7rem;
        color: #888;
        text-decoration: none;
        font-weight: 400;
    }

    .dashboard-grid .card h3 .view-all:hover {
        color: #d4af37;
    }

    /* Appointment Items */
    .appointment-item,
    .queue-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.6rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        flex-wrap: wrap;
        gap: 0.3rem;
    }

    .appointment-item:last-child,
    .queue-item:last-child {
        border-bottom: none;
    }

    .appointment-item .appt-time {
        color: #d4af37;
        font-weight: 600;
        font-size: 0.8rem;
        min-width: 65px;
    }

    .appointment-item .appt-details {
        flex: 1;
    }

    .appointment-item .appt-details .appt-service {
        font-weight: 500;
        font-size: 0.9rem;
    }

    .appointment-item .appt-details .appt-customer {
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

    /* Queue Items */
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
        font-size: 0.75rem;
        flex-shrink: 0;
    }

    .queue-item .queue-details {
        flex: 1;
    }

    .queue-item .queue-details .queue-customer {
        font-weight: 500;
        font-size: 0.85rem;
    }

    .queue-item .queue-details .queue-service {
        color: #aaa;
        font-size: 0.75rem;
    }

    .queue-item .queue-time {
        color: #888;
        font-size: 0.7rem;
    }

    .queue-item .btn-serve {
        background: #28a745;
        color: white;
        border: none;
        padding: 4px 12px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.7rem;
        transition: all 0.3s;
    }

    .queue-item .btn-serve:hover {
        background: #218838;
    }

    .queue-item .btn-serve:disabled {
        background: #555;
        cursor: not-allowed;
    }

    /* Low Stock Items */
    .stock-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    }

    .stock-item:last-child {
        border-bottom: none;
    }

    .stock-item .stock-name {
        font-size: 0.85rem;
    }

    .stock-item .stock-qty {
        font-size: 0.8rem;
        font-weight: 600;
        color: #dc3545;
    }

    .stock-item .stock-qty.medium {
        color: #d4af37;
    }

    .empty-state {
        color: #666;
        text-align: center;
        padding: 1.5rem 0;
        font-size: 0.9rem;
    }

    /* Low Stock Alert Card */
    .low-stock-card {
        background: #1a1a1a;
        border-radius: 12px;
        padding: 1.2rem 1.5rem;
        border: 1px solid rgba(220, 53, 69, 0.3);
        margin-top: 1.5rem;
    }

    .low-stock-card h3 {
        color: #dc3545;
        font-size: 1rem;
        margin-bottom: 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .low-stock-card h3 .view-all {
        font-size: 0.7rem;
        color: #888;
        text-decoration: none;
        font-weight: 400;
    }

    .low-stock-card h3 .view-all:hover {
        color: #d4af37;
    }

    .low-stock-card .stock-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 0.5rem;
    }

    /* Inactive Banner */
    .inactive-banner {
        background: rgba(220, 53, 69, 0.15);
        border: 1px solid #dc3545;
        border-radius: 12px;
        padding: 1.2rem 1.5rem;
        margin-bottom: 1.5rem;
        color: #dc3545;
        text-align: center;
    }

    .inactive-banner .icon {
        font-size: 2rem;
        display: block;
        margin-bottom: 0.5rem;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
    }

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
        .dashboard-grid .card { padding: 1rem; }
        .profile-card { flex-direction: column; text-align: center; }
        .low-stock-card .stock-grid { grid-template-columns: 1fr; }
        .profile-card .profile-info .detail { font-size: 0.75rem; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .dashboard-header .quick-actions { flex-wrap: wrap; }
        .dashboard-header .quick-actions .quick-btn { font-size: 0.65rem; padding: 6px 12px; }
        .appointment-item { flex-direction: column; align-items: flex-start; }
        .queue-item { flex-direction: column; align-items: flex-start; }
        .queue-item .btn-serve { width: 100%; text-align: center; }
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
        <div style="color: #ff6b6b; font-size: 0.9rem; margin-top: 0.3rem;">
            <?php echo $subscription_message; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================
       HEADER: Title + Quick Actions + Search (Horizontal)
       ============================================ -->
    <div class="dashboard-header">
        <!-- Left: Title -->
        <div class="title-section">
            <h1>👋 Welcome, <?php echo htmlspecialchars($staff_name); ?>!</h1>
            <p>Here's your schedule for today</p>
            <span class="staff-badge">✂️ <?php echo htmlspecialchars($staff_details['specialty'] ?? 'Stylist'); ?></span>
        </div>

        <!-- Center/Right: Quick Actions (Permission-based) -->
        <div class="quick-actions">
            <?php if (hasPermission($staff_id, 'create_appointments')): ?>
                <a href="book_for_customer.php" class="quick-btn"><i class="fas fa-plus-circle"></i> Book</a>
            <?php endif; ?>

            <?php if (hasPermission($staff_id, 'view_services')): ?>
                <a href="../services.php" class="quick-btn"><i class="fas fa-scissors"></i> Services</a>
            <?php endif; ?>

            <?php if (hasPermission($staff_id, 'view_inventory')): ?>
                <a href="products.php" class="quick-btn"><i class="fas fa-box"></i> Products</a>
            <?php endif; ?>

            <?php if (hasPermission($staff_id, 'view_customers')): ?>
                <a href="../admin/customers.php" class="quick-btn"><i class="fas fa-users"></i> Customers</a>
            <?php endif; ?>

            <?php if (hasPermission($staff_id, 'view_payments')): ?>
                <a href="../admin/payments.php" class="quick-btn"><i class="fas fa-credit-card"></i> Payments</a>
            <?php endif; ?>

            <?php if (hasPermission($staff_id, 'view_reports')): ?>
                <a href="reports.php" class="quick-btn"><i class="fas fa-chart-line"></i> Reports</a>
            <?php endif; ?>
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
       PROFILE CARD
       ============================================ -->
    <div class="profile-card">
        <div class="avatar"><?php echo strtoupper(substr($staff_name, 0, 1)); ?></div>
        <div class="profile-info">
            <h3>👤 <?php echo htmlspecialchars($staff_name); ?></h3>
            <div class="specialty">✂️ <?php echo htmlspecialchars($staff_details['specialty'] ?? 'Professional Stylist'); ?></div>
            <div class="detail">📅 <?php echo $staff_details['experience_years'] ?? 0; ?>+ years experience &nbsp;|&nbsp; 📧 <?php echo htmlspecialchars($staff_details['email']); ?></div>
        </div>
    </div>

    <!-- ============================================
       STATISTICS GRID (Permission-based)
       ============================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-icon">📅</span>
            <div class="stat-number"><?php echo $stats['total_appointments'] ?? 0; ?></div>
            <div class="stat-label">Today's Appointments</div>
        </div>

        <div class="stat-card green">
            <span class="stat-icon">✅</span>
            <div class="stat-number"><?php echo $stats['completed_today'] ?? 0; ?></div>
            <div class="stat-label">Completed Today</div>
        </div>

        <div class="stat-card orange">
            <span class="stat-icon">⏳</span>
            <div class="stat-number"><?php echo $stats['pending_today'] ?? 0; ?></div>
            <div class="stat-label">Pending</div>
        </div>

        <div class="stat-card blue">
            <span class="stat-icon">🚀</span>
            <div class="stat-number"><?php echo $queue_count; ?></div>
            <div class="stat-label">In Queue</div>
        </div>

        <?php if (hasPermission($staff_id, 'view_customers')): ?>
            <div class="stat-card purple">
                <span class="stat-icon">👤</span>
                <div class="stat-number"><?php echo $total_customers; ?></div>
                <div class="stat-label">Total Customers</div>
            </div>
        <?php endif; ?>

        <?php if (hasPermission($staff_id, 'view_payments')): ?>
            <div class="stat-card green">
                <span class="stat-icon">💰</span>
                <div class="stat-number">KSh <?php echo number_format($total_revenue, 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================
       DASHBOARD GRID: Schedule + Queue
       ============================================ -->
    <div class="dashboard-grid">

        <!-- Today's Schedule -->
        <div class="card">
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
                <div class="empty-state">🎉 No appointments scheduled for today. Enjoy your free time!</div>
            <?php endif; ?>
        </div>

        <!-- Current Queue -->
        <div class="card">
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
                        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                            <span class="queue-time">⏰ <?php echo date('g:i A', strtotime($q['appointment_time'])); ?></span>
                            <?php if (hasPermission($staff_id, 'mark_completed') || hasPermission($staff_id, 'serve_appointments')): ?>
                                <form method="POST" action="appointments.php" style="display: inline;">
                                    <input type="hidden" name="appointment_id" value="<?php echo $q['id']; ?>">
                                    <input type="hidden" name="action" value="serve">
                                    <button type="submit" class="btn-serve" onclick="return confirm('Mark this customer as served?')" <?php echo !$subscription_active ? 'disabled' : ''; ?>>✓ Serve</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">✨ No customers waiting in your queue. Great job!</div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ============================================
       LOW STOCK ALERT (Permission-based)
       ============================================ -->
    <?php if ($low_stock !== null && mysqli_num_rows($low_stock) > 0): ?>
        <div class="low-stock-card">
            <h3>
                ⚠️ Low Stock Alert
                <a href="../admin/products.php" class="view-all">View All →</a>
            </h3>
            <div class="stock-grid">
                <?php while($product = mysqli_fetch_assoc($low_stock)): ?>
                    <div class="stock-item">
                        <span class="stock-name"><?php echo htmlspecialchars($product['name']); ?></span>
                        <span class="stock-qty <?php echo $product['stock'] > 0 ? 'medium' : ''; ?>">
                            <?php echo $product['stock']; ?> left
                        </span>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>