<?php
/**
 * Salon Pro — Super Admin Dashboard
 * Luxury gold/black theme
 * Top bar: Dashboard / Overview | Quick Actions | Search Icon | Bell | Avatar
 * Welcome row: Message left | Date badge right
 */

require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

$admin_name = $_SESSION['user_name'] ?? 'Super Admin';
$admin_email = $_SESSION['user_email'] ?? 'superadmin@salonpro.com';

// ============================================
// REAL DATA QUERIES
// ============================================

// Total Users
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users"))['count'] ?? 0;

// Total Salons
$total_salons = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM salons"))['count'] ?? 0;

// Active Salons
$active_salons = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM salons WHERE subscription_status = 'active'"))['count'] ?? 0;

// Total Staff
$total_staff = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'staff'"))['count'] ?? 0;

// Total Customers
$total_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'customer'"))['count'] ?? 0;

// Total Revenue
$total_revenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM subscription_history"))['total'] ?? 0;

// Plan Distribution
$plan_stats = [];
$plans = ['basic', 'premium', 'enterprise'];
foreach ($plans as $plan) {
    $count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM salons WHERE subscription_plan = '$plan'"))['count'] ?? 0;
    $plan_stats[$plan] = $count;
}

$total_plan_count = array_sum($plan_stats);
$plan_labels = ['Basic', 'Premium', 'Enterprise'];
$plan_colors = ['#17a2b8', '#d4af37', '#28a745'];

// ============================================
// SALON REGISTRATIONS DATA (REAL)
// ============================================

$registration_labels = [];
$registration_data = [];

// Get last 30 days of salon registrations
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('M d', strtotime($date));
    $count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM salons WHERE DATE(created_at) = '$date'"))['count'] ?? 0;
    
    $registration_labels[] = $label;
    $registration_data[] = $count;
}

// ============================================
// RECENT ACTIVITY
// ============================================

$recent_activity = [];

// Recent salon additions
$recent_salons = mysqli_query($conn, "SELECT salon_name, created_at FROM salons ORDER BY created_at DESC LIMIT 3");
while ($row = mysqli_fetch_assoc($recent_salons)) {
    $recent_activity[] = [
        'user' => 'System',
        'action' => 'Added Salon',
        'module' => 'Salon Management',
        'time' => time_elapsed_string($row['created_at']),
        'details' => $row['salon_name']
    ];
}

// Recent subscriptions
$recent_subs = mysqli_query($conn, "SELECT sh.*, s.salon_name FROM subscription_history sh JOIN salons s ON sh.salon_id = s.id ORDER BY sh.payment_date DESC LIMIT 3");
while ($row = mysqli_fetch_assoc($recent_subs)) {
    $recent_activity[] = [
        'user' => $row['salon_name'],
        'action' => 'Renewed Subscription',
        'module' => 'Subscriptions',
        'time' => time_elapsed_string($row['payment_date']),
        'details' => ucfirst($row['plan']) . ' Plan'
    ];
}

// Sort by time
usort($recent_activity, function($a, $b) {
    $time_a = strtotime($a['time']);
    $time_b = strtotime($b['time']);
    return $time_b - $time_a;
});
$recent_activity = array_slice($recent_activity, 0, 6);

// ============================================
// EXPIRING SOON & ALERTS
// ============================================

$expiring_query = "SELECT salon_name, subscription_expiry 
                   FROM salons 
                   WHERE subscription_status = 'active' 
                   AND subscription_expiry IS NOT NULL 
                   AND subscription_expiry <= DATE_ADD(NOW(), INTERVAL 7 DAY)";
$expiring_result = mysqli_query($conn, $expiring_query);
$expiring_count = mysqli_num_rows($expiring_result);

// Failed Payments (placeholder)
$failed_payments = 0;

// ============================================
// HELPER FUNCTIONS
// ============================================

function fmt_ksh($amount) {
    return 'KSh ' . number_format($amount);
}

// Top performing salons
$top_salons = mysqli_query($conn, "SELECT s.salon_name, COUNT(sh.id) as renewal_count, SUM(sh.amount) as total_revenue 
                                   FROM salons s 
                                   LEFT JOIN subscription_history sh ON s.id = sh.salon_id 
                                   GROUP BY s.id 
                                   ORDER BY total_revenue DESC LIMIT 5");
$top_salons_data = [];
$top_max = 0;
while ($row = mysqli_fetch_assoc($top_salons)) {
    $top_salons_data[] = $row;
    if ($row['total_revenue'] > $top_max) $top_max = $row['total_revenue'];
}

// System health (static)
$system_health = [
    ['label' => 'Database', 'status' => 'Healthy'],
    ['label' => 'Server', 'status' => 'Healthy'],
    ['label' => 'Storage', 'status' => 'Healthy'],
    ['label' => 'Backup', 'status' => 'Healthy'],
    ['label' => 'SSL Certificate', 'status' => 'Healthy'],
];

// Summary cards data
$summary = [
    ['label' => 'Total Salons', 'value' => $total_salons, 'icon' => 'building-store', 'change' => $active_salons . ' active', 'positive' => true],
    ['label' => 'Total Staff', 'value' => $total_staff, 'icon' => 'users', 'change' => 'Across all salons', 'positive' => null],
    ['label' => 'Total Customers', 'value' => $total_customers, 'icon' => 'user-check', 'change' => 'Registered users', 'positive' => null],
    ['label' => 'Total Revenue', 'value' => fmt_ksh($total_revenue), 'icon' => 'coin', 'change' => 'From subscriptions', 'positive' => true],
    ['label' => 'System Status', 'value' => '99.98%', 'icon' => 'server-2', 'change' => 'Excellent', 'positive' => true],
];

// Alerts
$alerts = [];
if ($expiring_count > 0) {
    $alerts[] = ['icon' => 'alert-triangle', 'type' => 'warning', 'text' => $expiring_count . ' salon(s) expiring soon', 'time' => 'Check now'];
}
if ($failed_payments > 0) {
    $alerts[] = ['icon' => 'x', 'type' => 'danger', 'text' => $failed_payments . ' failed payment(s)', 'time' => 'Review now'];
}
if (empty($alerts)) {
    $alerts[] = ['icon' => 'circle-check', 'type' => 'success', 'text' => 'All systems operational', 'time' => 'Just now'];
}

// Get unread notification count
$unread_count = getUnreadNotificationCount();

include '../includes/header.php';
?>

<style>
    /* ============================================
       SUPER ADMIN DASHBOARD STYLES
       ============================================ */
    :root {
        --gold: #d4af37;
        --gold-light: #f0d878;
        --gold-dark: #a3831f;
        --bg: #050505;
        --panel: #0e0e0e;
        --panel-border: rgba(212, 175, 55, 0.25);
        --text-primary: #f5f0e1;
        --text-secondary: #b8b2a0;
        --text-muted: #7a7568;
        --danger: #e0554a;
        --warning: #e0a63c;
        --success: #5cae7a;
        --accent: #4a90d9;
    }

    .main-content {
        padding: 0 2rem 2rem;
        background: #0a0a0a;
        min-height: 100vh;
        margin-top: 0.5rem;
    }

    /* ============================================
       STICKY HEADER
       ============================================ */
    .sticky-header {
        position: sticky;
        top: 65px;
        z-index: 100;
        background: #0a0a0a;
        padding: 0.5rem 0 0.8rem 0;
        border-bottom: 1px solid rgba(212, 175, 55, 0.08);
    }

    .top-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        padding: 0.2rem 0;
    }

    .top-bar-left {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex: 0 0 auto;
    }

    .breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        color: #b8b2a0;
        font-size: 0.9rem;
        white-space: nowrap;
    }
    .breadcrumb .current {
        color: #f0d878;
        font-weight: 600;
    }
    .breadcrumb .sep {
        color: #7a7568;
    }
    .breadcrumb .sub {
        color: #7a7568;
    }
    .breadcrumb .menu-icon {
        font-size: 1.3rem;
        color: #d4af37;
        cursor: pointer;
    }

    .top-bar-center {
        display: flex;
        align-items: center;
        gap: 0.3rem;
        flex-wrap: wrap;
        flex: 1 1 auto;
        justify-content: center;
    }

    .quick-links {
        display: flex;
        align-items: center;
        gap: 0.1rem;
        flex-wrap: wrap;
    }

    .quick-links .link-sep {
        color: #7a7568;
        font-size: 0.7rem;
        opacity: 0.4;
        font-weight: 100;
    }

    .quick-links .qlink {
        color: #b8b2a0;
        text-decoration: none;
        font-size: 0.8rem;
        padding: 0.3rem 0.7rem;
        border-radius: 20px;
        transition: all 0.3s ease;
        white-space: nowrap;
        border: 1px solid transparent;
    }

    .quick-links .qlink:hover {
        color: #f0d878;
        background: rgba(212, 175, 55, 0.08);
        border-color: rgba(212, 175, 55, 0.15);
    }

    .quick-links .qlink.active {
        color: #f0d878;
        background: rgba(212, 175, 55, 0.12);
        border-color: rgba(212, 175, 55, 0.2);
    }

    .top-bar-right {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex: 0 0 auto;
    }

    .top-bar-right .icon-btn {
        position: relative;
        color: #f0d878;
        font-size: 1.1rem;
        cursor: pointer;
        text-decoration: none;
        padding: 0.3rem 0.5rem;
        border-radius: 6px;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .top-bar-right .icon-btn:hover {
        background: rgba(212, 175, 55, 0.08);
    }

    .top-bar-right .icon-btn .badge {
        position: absolute;
        top: -4px;
        right: -4px;
        background: #dc3545;
        color: white;
        font-size: 0.5rem;
        font-weight: 700;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .top-bar-right .topbar-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #0e0e0e;
        border: 1px solid #d4af37;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #f0d878;
        font-size: 0.9rem;
    }

    /* ============================================
       WELCOME ROW
       ============================================ */
    .welcome-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 0.8rem 0 1.2rem 0;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .welcome-left h1 {
        font-size: 1.6rem;
        font-weight: 700;
        color: #f0d878;
        font-family: 'Playfair Display', serif;
        margin: 0;
    }

    .welcome-left .subtitle {
        font-size: 0.9rem;
        color: #7a7568;
        margin-top: 0.2rem;
    }

    .date-badge {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border: 1px solid rgba(212, 175, 55, 0.25);
        border-radius: 8px;
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
        color: #b8b2a0;
        flex-shrink: 0;
        white-space: nowrap;
    }

    .date-badge i {
        color: #d4af37;
    }

    /* ============================================
       SUMMARY CARDS
       ============================================ */
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .summary-card {
        background: #0e0e0e;
        border: 1px solid rgba(212, 175, 55, 0.25);
        border-radius: 12px;
        padding: 1rem 1.2rem;
        transition: all 0.3s;
    }

    .summary-card:hover {
        transform: translateY(-3px);
        border-color: #d4af37;
    }

    .summary-card .top-row {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        margin-bottom: 0.6rem;
    }

    .summary-card .summary-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: rgba(212, 175, 55, 0.12);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #f0d878;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    .summary-card .label {
        font-size: 0.8rem;
        color: #b8b2a0;
    }

    .summary-card .value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #f5f0e1;
        margin-bottom: 0.3rem;
    }

    .summary-card .change {
        font-size: 0.75rem;
        color: #7a7568;
    }

    .summary-card .change.positive {
        color: #5cae7a;
    }

    /* ============================================
       PANELS
       ============================================ */
    .panels-row {
        display: grid;
        grid-template-columns: 1.6fr 1.2fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    .panels-row-2 {
        display: grid;
        grid-template-columns: 1.4fr 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    .panel {
        background: #0e0e0e;
        border: 1px solid rgba(212, 175, 55, 0.25);
        border-radius: 12px;
        padding: 1.2rem 1.4rem;
    }
    .panel h2 {
        font-size: 0.9rem;
        font-weight: 700;
        color: #f0d878;
        margin-bottom: 1rem;
    }
    .panel h2 .muted {
        font-size: 0.75rem;
        color: #7a7568;
        font-weight: 400;
    }

    /* Donut Chart */
    .donut-wrap {
        display: flex;
        align-items: center;
        gap: 1.2rem;
        flex-wrap: wrap;
    }
    .donut-canvas-holder {
        position: relative;
        width: 130px;
        height: 130px;
        flex-shrink: 0;
    }
    .donut-center {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    .donut-center .num {
        font-size: 1.3rem;
        font-weight: 700;
        color: #f5f0e1;
    }
    .donut-center .lbl {
        font-size: 0.7rem;
        color: #7a7568;
    }
    .legend-list {
        flex: 1;
        min-width: 100px;
    }
    .legend-row {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        font-size: 0.8rem;
        padding: 0.3rem 0;
        color: #b8b2a0;
    }
    .legend-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .legend-row .lname {
        flex: 1;
    }
    .legend-row .lpct {
        color: #f0d878;
        font-weight: 600;
        width: 34px;
    }
    .legend-row .lcount {
        color: #7a7568;
        width: 40px;
        text-align: right;
    }

    /* System Health */
    .health-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0;
        font-size: 0.85rem;
        color: #b8b2a0;
        border-bottom: 1px solid rgba(212, 175, 55, 0.08);
    }
    .health-row:last-child {
        border-bottom: none;
    }
    .health-status {
        display: flex;
        align-items: center;
        gap: 0.3rem;
        color: #5cae7a;
        font-size: 0.8rem;
    }

    /* Tables */
    .table-wrapper {
        overflow-x: auto;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8rem;
        min-width: 450px;
    }
    th {
        text-align: left;
        color: #7a7568;
        font-weight: 500;
        padding: 0.4rem 0.5rem;
        border-bottom: 1px solid rgba(212, 175, 55, 0.25);
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    td {
        padding: 0.6rem 0.5rem;
        border-bottom: 1px solid rgba(212, 175, 55, 0.08);
        color: #b8b2a0;
    }
    tr:last-child td {
        border-bottom: none;
    }

    /* Top Performing Salons */
    .top-user-row {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        padding: 0.5rem 0;
    }
    .top-user-row .uname {
        font-size: 0.8rem;
        color: #b8b2a0;
        width: 100px;
        flex-shrink: 0;
    }
    .bar-track {
        flex: 1;
        height: 6px;
        background: #1c1c1c;
        border-radius: 4px;
        overflow: hidden;
    }
    .bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #a3831f, #f0d878);
        border-radius: 4px;
    }
    .top-user-row .uscore {
        font-size: 0.8rem;
        color: #f0d878;
        font-weight: 600;
        width: 60px;
        text-align: right;
    }

    /* Alerts */
    .alert-row {
        display: flex;
        align-items: flex-start;
        gap: 0.8rem;
        padding: 0.6rem 0;
        border-bottom: 1px solid rgba(212, 175, 55, 0.08);
    }
    .alert-row:last-child {
        border-bottom: none;
    }
    .alert-row i {
        font-size: 1.1rem;
        margin-top: 2px;
        flex-shrink: 0;
    }
    .alert-warning i {
        color: #e0a63c;
    }
    .alert-success i {
        color: #5cae7a;
    }
    .alert-danger i {
        color: #e0554a;
    }
    .alert-text {
        flex: 1;
    }
    .alert-text p {
        font-size: 0.85rem;
        color: #b8b2a0;
        margin: 0;
    }
    .alert-text span {
        font-size: 0.7rem;
        color: #7a7568;
    }

    /* Chart */
    .chart-container {
        height: 140px;
        position: relative;
    }

    .view-all {
        display: block;
        text-align: center;
        margin-top: 0.8rem;
        font-size: 0.8rem;
        color: #f0d878;
        text-decoration: none;
    }
    .view-all:hover {
        text-decoration: underline;
    }

    .back-link {
        display: inline-block;
        margin-top: 1.5rem;
        color: #f0d878;
        text-decoration: none;
    }
    .back-link:hover {
        text-decoration: underline;
    }
    
    .top-bar-right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex: 0 0 auto;
}

.top-bar-right .icon-btn {
    position: relative;
    color: #f0d878;
    font-size: 1.1rem;
    cursor: pointer;
    text-decoration: none;
    padding: 0.3rem 0.5rem;
    border-radius: 6px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.top-bar-right .icon-btn:hover {
    background: rgba(212, 175, 55, 0.08);
}

.top-bar-right .icon-btn .badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background: #dc3545;
    color: white;
    font-size: 0.5rem;
    font-weight: 700;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.top-bar-right .topbar-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #0e0e0e;
    border: 1px solid #d4af37;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #f0d878;
    font-size: 0.9rem;
}

    /* ============================================
       RESPONSIVE
       ============================================ */
    @media (max-width: 1100px) {
        .panels-row, .panels-row-2 {
            grid-template-columns: 1fr;
        }
        .summary-grid {
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        }
        .sticky-header {
            position: relative;
            top: 0;
        }
    }

    @media (max-width: 900px) {
        .top-bar {
            flex-direction: column;
            align-items: stretch;
            gap: 0.5rem;
        }
        .top-bar-left { width: 100%; }
        .top-bar-center { width: 100%; justify-content: flex-start; }
        .top-bar-right { width: 100%; justify-content: flex-start; }
        .quick-links { flex-wrap: wrap; }
        .quick-links .qlink { font-size: 0.75rem; padding: 0.2rem 0.5rem; }
        .welcome-row { flex-direction: column; align-items: flex-start; }
        .welcome-left h1 { font-size: 1.3rem; }
        .date-badge { align-self: flex-start; }
    }

    @media (max-width: 768px) {
        .main-content { padding: 0 1rem 1rem; }
        .breadcrumb { font-size: 0.8rem; }
        .quick-links .qlink { font-size: 0.7rem; padding: 0.2rem 0.4rem; }
        .summary-grid { grid-template-columns: 1fr 1fr; }
        .summary-card .value { font-size: 1.2rem; }
        .panels-row, .panels-row-2 { grid-template-columns: 1fr; }
        .sticky-header { position: relative; top: 0; }
        .top-bar-right .icon-btn { font-size: 0.95rem; padding: 0.2rem 0.4rem; }
        .top-bar-right .topbar-avatar { width: 28px; height: 28px; font-size: 0.75rem; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0 0.8rem 0.8rem; }
        .summary-grid { grid-template-columns: 1fr; }
        .quick-links .qlink { font-size: 0.65rem; padding: 0.15rem 0.3rem; }
        .quick-links .link-sep { font-size: 0.6rem; }
        .welcome-left h1 { font-size: 1.1rem; }
        .date-badge { font-size: 0.75rem; padding: 0.3rem 0.6rem; }
        .donut-canvas-holder { width: 100px; height: 100px; }
        .legend-row { font-size: 0.7rem; }
        .top-bar-right .icon-btn { font-size: 0.8rem; }
        .top-bar-right .icon-btn .badge { width: 14px; height: 14px; font-size: 0.4rem; top: -2px; right: -2px; }
    }
</style>

<div class="main-content">

    <!-- ============================================
       STICKY HEADER - Dashboard / Overview | Quick Actions | Search | Bell | Avatar
       ============================================ -->
    <div class="sticky-header">
        <div class="top-bar">

            <!-- LEFT: Breadcrumb -->
            <div class="top-bar-left">
                <div class="breadcrumb">
                    <i class="ti ti-menu-2 menu-icon"></i>
                    <span class="current">Dashboard</span>
                    <span class="sep">/</span>
                    <span class="sub">Overview</span>
                </div>
            </div>

          <!-- CENTER: Quick Actions -->
<div class="top-bar-center">
    <div class="quick-links">
        <a href="salons.php" class="qlink active"><i class="ti ti-building-store"></i> Manage Salons</a>
        <span class="link-sep">|</span>
        <a href="admins.php" class="qlink"><i class="ti ti-user-shield"></i> Manage Owners</a>
        <span class="link-sep">|</span>
        <a href="subscriptions.php" class="qlink"><i class="ti ti-crown"></i> Subscriptions</a>
        <span class="link-sep">|</span>
        <a href="staff.php" class="qlink"><i class="ti ti-users"></i> Staff</a>
        <span class="link-sep">|</span>
        <a href="products.php" class="qlink"><i class="ti ti-box"></i> Products</a>
        <span class="link-sep">|</span>
        <a href="permissions.php" class="qlink"><i class="ti ti-key"></i> Permissions</a>
        <span class="link-sep">|</span>
        <a href="audit_logs.php" class="qlink"><i class="ti ti-file-text"></i> Audit Logs</a>
        <span class="link-sep">|</span>
        <a href="settings.php" class="qlink"><i class="ti ti-settings"></i> System Settings</a>
        
    </div>
</div>

            <!-- RIGHT: Search Icon | Avatar -->

<div class="top-bar-right">
    <!-- Notification Bell -->
    <a href="notifications.php" class="icon-btn" title="Notifications">
        <i class="ti ti-bell"></i>
        <?php if ($unread_count > 0): ?>
            <span class="badge"><?php echo min($unread_count, 99); ?></span>
        <?php endif; ?>
    </a>
  
<!-- Search Icon -->
    <a href="#" class="icon-btn" id="searchToggle" title="Search (Ctrl+K)">
        <i class="ti ti-search"></i>
    </a>

    <!-- Avatar -->
    <div class="topbar-avatar"><i class="ti ti-crown"></i></div>
</div>

        </div>
    </div>

    <!-- ============================================
       WELCOME ROW - Message Left | Date Badge Right
       ============================================ -->
    <div class="welcome-row">
        <div class="welcome-left">
            <h1>👋 Welcome back, <?php echo htmlspecialchars($admin_name); ?>!</h1>
            <p class="subtitle">Here's what's happening across all salons today.</p>
        </div>
        <div class="date-badge">
            <i class="ti ti-calendar"></i> <?php echo date('j F Y'); ?>
        </div>
    </div>

    <!-- ============================================
       SUMMARY CARDS
       ============================================ -->
    <div class="summary-grid">
        <?php foreach ($summary as $s): ?>
            <div class="summary-card">
                <div class="top-row">
                    <div class="summary-icon"><i class="ti ti-<?php echo $s['icon']; ?>"></i></div>
                    <span class="label"><?php echo htmlspecialchars($s['label']); ?></span>
                </div>
                <p class="value"><?php echo htmlspecialchars($s['value']); ?></p>
                <p class="change <?php echo $s['positive'] === null ? '' : 'positive'; ?>">
                    <?php echo htmlspecialchars($s['change']); ?>
                </p>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ============================================
       PANELS ROW 1
       ============================================ -->
    <div class="panels-row">

        <!-- Registrations Chart -->
        <div class="panel">
            <h2>Salon Registrations <span class="muted">(Last 30 Days)</span></h2>
            <div class="chart-container">
                <canvas id="registrationsChart"></canvas>
            </div>
        </div>

        <!-- Plan Distribution -->
        <div class="panel">
            <h2>Plan Distribution</h2>
            <?php if ($total_plan_count > 0): ?>
            <div class="donut-wrap">
                <div class="donut-canvas-holder">
                    <canvas id="planChart"></canvas>
                    <div class="donut-center">
                        <span class="num"><?php echo $total_plan_count; ?></span>
                        <span class="lbl">Total</span>
                    </div>
                </div>
                <div class="legend-list">
                    <?php foreach ($plan_stats as $plan => $count): 
                        $idx = array_search($plan, $plans);
                        $label = ucfirst($plan);
                        $color = $plan_colors[$idx];
                        $pct = $total_plan_count > 0 ? round(($count / $total_plan_count) * 100) : 0;
                    ?>
                    <div class="legend-row">
                        <span class="legend-dot" style="background:<?php echo $color; ?>;"></span>
                        <span class="lname"><?php echo $label; ?></span>
                        <span class="lpct"><?php echo $pct; ?>%</span>
                        <span class="lcount">(<?php echo $count; ?>)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div style="text-align:center;color:#7a7568;padding:2rem 0;">
                <p>No salon plan data available</p>
                <p style="font-size:0.8rem;">Add salons with subscription plans to see distribution</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- System Health -->
        <div class="panel">
            <h2>System Health</h2>
            <?php foreach ($system_health as $h): ?>
                <div class="health-row">
                    <span><?php echo htmlspecialchars($h['label']); ?></span>
                    <span class="health-status"><i class="ti ti-check"></i><?php echo htmlspecialchars($h['status']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ============================================
       PANELS ROW 2
       ============================================ -->
    <div class="panels-row-2">

        <!-- Recent Activity -->
        <div class="panel">
            <h2>Recent System Activity</h2>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>User</th><th>Action</th><th>Module</th><th>Time</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($recent_activity)): ?>
                        <?php foreach ($recent_activity as $a): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($a['user']); ?></td>
                            <td><?php echo htmlspecialchars($a['action']); ?></td>
                            <td><?php echo htmlspecialchars($a['module']); ?></td>
                            <td><?php echo htmlspecialchars($a['time']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center;color:#7a7568;padding:1rem;">No recent activity</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Performing Salons -->
        <div class="panel">
            <h2>Top Performing Salons</h2>
            <?php if (!empty($top_salons_data)): ?>
                <?php foreach ($top_salons_data as $i => $s): 
                    $pct = $top_max > 0 ? round(($s['total_revenue'] / $top_max) * 100) : 0;
                ?>
                <div class="top-user-row">
                    <span class="uname"><?php echo htmlspecialchars($s['salon_name']); ?></span>
                    <div class="bar-track"><div class="bar-fill" style="width:<?php echo $pct; ?>%;"></div></div>
                    <span class="uscore"><?php echo fmt_ksh($s['total_revenue'] ?? 0); ?></span>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="color:#7a7568;text-align:center;padding:1rem;">No salon data available</div>
            <?php endif; ?>
        </div>

        <!-- Alerts -->
        <div class="panel">
            <h2>Alerts &amp; Notifications</h2>
            <?php foreach ($alerts as $a): ?>
                <div class="alert-row alert-<?php echo $a['type']; ?>">
                    <i class="ti ti-<?php echo $a['icon']; ?>"></i>
                    <div class="alert-text">
                        <p><?php echo htmlspecialchars($a['text']); ?></p>
                        <span><?php echo htmlspecialchars($a['time']); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
            <a href="subscriptions.php" class="view-all">View all subscriptions →</a>
        </div>
    </div>

    <a href="#" class="back-link" style="display:none;">← Back to Dashboard</a>

</div>

<!-- Chart.js CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const goldLight = '#f0d878';
    const goldDark = '#a3831f';
    const textMuted = '#7a7568';
    const gridColor = 'rgba(212, 175, 55, 0.1)';

    // ============================================
    // REGISTRATIONS CHART
    // ============================================
    const registrationsCanvas = document.getElementById('registrationsChart');
    if (registrationsCanvas) {
        try {
            new Chart(registrationsCanvas, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($registration_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($registration_data); ?>,
                        borderColor: goldLight,
                        backgroundColor: 'rgba(212, 175, 55, 0.18)',
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: goldLight,
                        pointRadius: 3,
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + ' salon(s) registered';
                                }
                            }
                        }
                    },
                    scales: {
                        x: { 
                            ticks: { color: textMuted, font: { size: 10 }, maxTicksLimit: 10 },
                            grid: { color: gridColor }
                        },
                        y: { 
                            ticks: { color: textMuted, font: { size: 10 }, stepSize: 1 },
                            grid: { color: gridColor },
                            beginAtZero: true
                        }
                    }
                }
            });
        } catch(e) {
            console.log('Registrations chart error:', e);
            document.getElementById('registrationsChart').parentElement.innerHTML = 
                '<div style="color:#7a7568;text-align:center;padding:1.5rem;">Chart unavailable</div>';
        }
    }

    // ============================================
    // PLAN DISTRIBUTION CHART
    // ============================================
    const planCanvas = document.getElementById('planChart');
    if (planCanvas) {
        try {
            new Chart(planCanvas, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($plan_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_values($plan_stats)); ?>,
                        backgroundColor: <?php echo json_encode($plan_colors); ?>,
                        borderColor: '#0e0e0e',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = total > 0 ? Math.round((context.parsed / total) * 100) : 0;
                                    return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        } catch(e) {
            console.log('Plan chart error:', e);
            document.getElementById('planChart').parentElement.innerHTML = 
                '<div style="color:#7a7568;text-align:center;padding:1.5rem;">Chart unavailable</div>';
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>