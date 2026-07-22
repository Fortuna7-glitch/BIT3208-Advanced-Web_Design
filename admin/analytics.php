<?php
/**
 * Salon Pro — Admin: Advanced Analytics
 * Luxury gold/black theme
 * ENTERPRISE ONLY: Advanced business intelligence and predictive analytics
 */

require_once '../config/database.php';
require_once '../includes/permissions.php';

// Authentication check
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['user_name'] ?? 'Admin';

// Get salon_id from session
$salon_id = getCurrentSalonId();

// ============================================
// PLAN FEATURES CHECK - Enterprise Only
// ============================================
$plan_features = getSalonPlanFeatures($salon_id);
$plan_key = strtolower($plan_features['plan_name']);

if ($plan_key !== 'enterprise') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Feature Unavailable</title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>
    <body>
        <div style="text-align: center; padding: 3rem; background: #1a1a1a; border-radius: 15px; border: 1px solid rgba(212, 175, 55, 0.2); max-width: 500px; margin: 3rem auto;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">📈</div>
            <h2 style="color: #d4af37;">Enterprise Feature</h2>
            <p style="color: #aaa;">Advanced Analytics is available exclusively on the <strong>Enterprise Plan</strong>.</p>
            <p style="color: #7a7568; font-size: 0.85rem;">Upgrade to access AI-powered insights, predictive analytics, and more.</p>
            <a href="upgrade.php" style="display: inline-block; margin-top: 1rem; padding: 10px 25px; background: #d4af37; color: #050505; border-radius: 25px; text-decoration: none; font-weight: 600;">✨ Upgrade to Enterprise</a>
            <a href="dashboard.php" style="display: inline-block; margin-top: 0.5rem; color: #d4af37; text-decoration: none;">← Back to Dashboard</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// ============================================
// DATE RANGE
// ============================================
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// ============================================
// 1. REVENUE FORECAST (Simple linear projection)
// ============================================
$forecast_query = "SELECT 
    DATE(order_date) as date,
    SUM(total_amount) as daily_revenue
    FROM orders
    WHERE salon_id = $salon_id
    AND status != 'cancelled'
    AND order_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY DATE(order_date)
    ORDER BY date ASC";
$forecast_result = mysqli_query($conn, $forecast_query);

$forecast_data = [];
$total_revenue = 0;
$days = 0;
while ($row = mysqli_fetch_assoc($forecast_result)) {
    $forecast_data[] = $row;
    $total_revenue += $row['daily_revenue'];
    $days++;
}
$avg_daily = $days > 0 ? $total_revenue / $days : 0;
$forecast_next_30 = $avg_daily * 30;
$forecast_next_7 = $avg_daily * 7;

// ============================================
// 2. STAFF PERFORMANCE TRENDS
// ============================================
$staff_trends_query = "SELECT 
    u.id,
    u.full_name,
    COUNT(a.id) as total_appointments,
    SUM(CASE WHEN a.status = 'served' THEN 1 ELSE 0 END) as completed,
    SUM(s.price) as total_revenue,
    ROUND(SUM(s.price) / NULLIF(COUNT(a.id), 0), 2) as avg_service_price,
    ROUND(SUM(CASE WHEN a.status = 'served' THEN 1 ELSE 0 END) / NULLIF(COUNT(a.id), 0) * 100, 2) as completion_rate
    FROM users u
    LEFT JOIN appointments a ON u.id = a.staff_id
    LEFT JOIN services s ON a.service_id = s.id
    WHERE u.salon_id = $salon_id
    AND u.role = 'staff'
    AND u.is_active = 1
    AND (a.appointment_date BETWEEN '$start_date' AND '$end_date' OR a.id IS NULL)
    GROUP BY u.id
    ORDER BY total_revenue DESC";
$staff_trends_result = mysqli_query($conn, $staff_trends_query);

// ============================================
// 3. CUSTOMER CHURN PREDICTION
// ============================================
$churn_query = "SELECT 
    u.id,
    u.full_name,
    u.email,
    u.phone,
    MAX(a.appointment_date) as last_visit,
    COUNT(a.id) as total_visits,
    DATEDIFF(NOW(), MAX(a.appointment_date)) as days_since_last_visit,
    CASE 
        WHEN DATEDIFF(NOW(), MAX(a.appointment_date)) > 60 THEN 'High'
        WHEN DATEDIFF(NOW(), MAX(a.appointment_date)) > 30 THEN 'Medium'
        ELSE 'Low'
    END as churn_risk
    FROM users u
    LEFT JOIN appointments a ON u.id = a.customer_id
    WHERE u.salon_id = $salon_id
    AND u.role = 'customer'
    AND u.is_active = 1
    GROUP BY u.id
    HAVING days_since_last_visit > 30 OR last_visit IS NULL
    ORDER BY days_since_last_visit DESC
    LIMIT 10";
$churn_result = mysqli_query($conn, $churn_query);

// ============================================
// 4. SERVICE PERFORMANCE HEATMAP
// ============================================
$heatmap_query = "SELECT 
    s.service_name,
    COUNT(a.id) as bookings,
    SUM(s.price) as revenue,
    AVG(s.price) as avg_price,
    COUNT(DISTINCT a.customer_id) as unique_customers,
    ROUND(COUNT(a.id) / NULLIF(DATEDIFF('$end_date', '$start_date'), 0) * 7, 2) as weekly_avg
    FROM services s
    LEFT JOIN appointments a ON s.id = a.service_id
    WHERE s.salon_id = $salon_id
    AND (a.appointment_date BETWEEN '$start_date' AND '$end_date' OR a.id IS NULL)
    AND (a.status != 'cancelled' OR a.id IS NULL)
    GROUP BY s.id
    ORDER BY revenue DESC";
$heatmap_result = mysqli_query($conn, $heatmap_query);

// ============================================
// 5. PEAK HOURS ANALYSIS
// ============================================
$peak_hours_query = "SELECT 
    HOUR(appointment_time) as hour,
    COUNT(*) as total_appointments,
    SUM(CASE WHEN status = 'served' THEN 1 ELSE 0 END) as served
    FROM appointments
    WHERE salon_id = $salon_id
    AND appointment_date BETWEEN '$start_date' AND '$end_date'
    AND status != 'cancelled'
    GROUP BY HOUR(appointment_time)
    ORDER BY total_appointments DESC";
$peak_hours_result = mysqli_query($conn, $peak_hours_query);

// ============================================
// 6. CUSTOMER LIFETIME VALUE (CLV)
// ============================================
$clv_query = "SELECT 
    u.id,
    u.full_name,
    u.email,
    COUNT(a.id) as total_visits,
    SUM(s.price) as total_spent,
    DATEDIFF(NOW(), MIN(a.appointment_date)) as days_active,
    ROUND(SUM(s.price) / NULLIF(DATEDIFF(NOW(), MIN(a.appointment_date)), 0) * 30, 2) as monthly_value
    FROM users u
    JOIN appointments a ON u.id = a.customer_id
    JOIN services s ON a.service_id = s.id
    WHERE u.salon_id = $salon_id
    AND u.role = 'customer'
    AND u.is_active = 1
    AND a.status = 'served'
    GROUP BY u.id
    HAVING total_visits > 0
    ORDER BY total_spent DESC
    LIMIT 10";
$clv_result = mysqli_query($conn, $clv_query);

// ============================================
// 7. BUSINESS HEALTH SCORE
// ============================================
$health_query = "SELECT 
    (SELECT COUNT(*) FROM users WHERE salon_id = $salon_id AND role = 'customer' AND is_active = 1) as total_customers,
    (SELECT COUNT(*) FROM users WHERE salon_id = $salon_id AND role = 'staff' AND is_active = 1) as total_staff,
    (SELECT COUNT(*) FROM appointments WHERE salon_id = $salon_id AND appointment_date BETWEEN '$start_date' AND '$end_date' AND status != 'cancelled') as total_appointments,
    (SELECT SUM(total_amount) FROM orders WHERE salon_id = $salon_id AND order_date BETWEEN '$start_date' AND '$end_date' AND status != 'cancelled') as total_revenue,
    (SELECT COUNT(*) FROM products WHERE salon_id = $salon_id AND stock <= 5 AND stock > 0) as low_stock_items,
    (SELECT COUNT(*) FROM products WHERE salon_id = $salon_id AND stock <= 0) as out_of_stock_items";
$health_result = mysqli_query($conn, $health_query);
$health = mysqli_fetch_assoc($health_result);

// Calculate health score (0-100)
$health_score = 0;
$score_components = 0;
if ($health['total_customers'] > 10) { $health_score += 15; $score_components++; }
if ($health['total_staff'] > 2) { $health_score += 15; $score_components++; }
if ($health['total_appointments'] > 20) { $health_score += 20; $score_components++; }
if ($health['total_revenue'] > 10000) { $health_score += 20; $score_components++; }
if ($health['low_stock_items'] <= 3) { $health_score += 15; $score_components++; }
if ($health['out_of_stock_items'] == 0) { $health_score += 15; $score_components++; }
if ($score_components > 0) {
    $health_score = round($health_score / $score_components * 100 / 100 * 100);
}

// ============================================
// GET UNREAD NOTIFICATION COUNT
// ============================================
$unread_count = 0;
if (function_exists('getUnreadNotificationCount')) {
    $unread_count = getUnreadNotificationCount();
}

include '../includes/header.php';
?>

<style>
    .main-content {
        padding: 0 2rem 2rem;
        background: #0a0a0a;
        min-height: 100vh;
        margin-top: 0.5rem;
    }

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

    .welcome-left .plan-badge {
        display: inline-block;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        margin-top: 0.5rem;
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border: 1px solid #28a745;
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

    .filter-bar {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        align-items: flex-end;
        background: #0e0e0e;
        padding: 1.2rem;
        border-radius: 12px;
        border: 1px solid rgba(212, 175, 55, 0.25);
    }

    .filter-bar .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
    }

    .filter-bar .form-group label {
        color: #d4af37;
        font-size: 0.8rem;
        font-weight: 500;
    }

    .filter-bar .form-group input {
        padding: 8px 14px;
        background: #1a1a1a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        color: #f5f0e1;
        font-size: 0.85rem;
    }

    .filter-bar .form-group input:focus {
        outline: none;
        border-color: #d4af37;
    }

    .filter-bar .filter-btn {
        padding: 8px 25px;
        background: #d4af37;
        border: none;
        border-radius: 25px;
        color: #050505;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
    }

    .filter-bar .filter-btn:hover {
        background: #f0d878;
    }

    .analytics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .analytics-card {
        background: #0e0e0e;
        border-radius: 12px;
        padding: 1.2rem 1.5rem;
        border: 1px solid rgba(212, 175, 55, 0.1);
        transition: all 0.3s;
        text-align: center;
    }

    .analytics-card:hover {
        border-color: rgba(212, 175, 55, 0.3);
        transform: translateY(-3px);
    }

    .analytics-card .icon {
        font-size: 2rem;
        margin-bottom: 0.5rem;
    }

    .analytics-card .number {
        font-size: 2rem;
        font-weight: 700;
        color: #d4af37;
    }

    .analytics-card .label {
        color: #7a7568;
        font-size: 0.75rem;
        margin-top: 0.2rem;
    }

    .analytics-card .sub {
        color: #b8b2a0;
        font-size: 0.8rem;
        margin-top: 0.3rem;
    }

    .analytics-card.green .number { color: #28a745; }
    .analytics-card.orange .number { color: #ffc107; }
    .analytics-card.red .number { color: #dc3545; }
    .analytics-card.blue .number { color: #17a2b8; }
    .analytics-card.purple .number { color: #6f42c1; }

    .analytics-section {
        background: #0e0e0e;
        border: 1px solid rgba(212, 175, 55, 0.25);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .analytics-section h2 {
        color: #f0d878;
        font-size: 1rem;
        margin-bottom: 1rem;
        font-family: 'Playfair Display', serif;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .analytics-section h2 .view-all {
        font-size: 0.7rem;
        color: #7a7568;
        text-decoration: none;
    }

    .analytics-section h2 .view-all:hover {
        color: #d4af37;
    }

    .table-wrapper {
        overflow-x: auto;
        background: #0e0e0e;
        border-radius: 12px;
        padding: 0;
        border: 1px solid rgba(212, 175, 55, 0.25);
        margin-bottom: 1.5rem;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
        min-width: 500px;
    }

    th, td {
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid rgba(212, 175, 55, 0.1);
    }

    th {
        color: #d4af37;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    tr:hover {
        background: rgba(212, 175, 55, 0.05);
    }

    .churn-risk-high { color: #dc3545; font-weight: 600; }
    .churn-risk-medium { color: #ffc107; font-weight: 600; }
    .churn-risk-low { color: #28a745; font-weight: 600; }

    .back-link {
        display: inline-block;
        margin-top: 1.5rem;
        color: #f0d878;
        text-decoration: none;
        text-align: center;
        width: 100%;
    }

    .back-link:hover {
        text-decoration: underline;
    }

    .health-gauge {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 2.5rem;
        font-weight: 700;
        color: #f5f0e1;
        border: 6px solid #d4af37;
        position: relative;
        background: #1a1a1a;
    }

    .health-gauge .label {
        font-size: 0.7rem;
        color: #7a7568;
        font-weight: 400;
    }

    @media (max-width: 1024px) {
        .analytics-grid { grid-template-columns: 1fr 1fr; }
        table { min-width: 400px; }
    }

    @media (max-width: 768px) {
        .main-content { padding: 0 1rem 1rem; }
        .top-bar {
            flex-direction: column;
            align-items: stretch;
            gap: 0.5rem;
        }
        .top-bar-left { width: 100%; }
        .top-bar-center { width: 100%; justify-content: flex-start; }
        .top-bar-right { width: 100%; justify-content: flex-start; }
        .top-bar-right .icon-btn { font-size: 0.95rem; padding: 0.2rem 0.4rem; }
        .top-bar-right .topbar-avatar { width: 28px; height: 28px; font-size: 0.75rem; }
        .quick-links .qlink { font-size: 0.7rem; padding: 0.2rem 0.4rem; }
        .welcome-row { flex-direction: column; align-items: flex-start; }
        .welcome-left h1 { font-size: 1.3rem; }
        .date-badge { align-self: flex-start; }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar .form-group input { width: 100%; }
        .analytics-grid { grid-template-columns: 1fr; }
        .analytics-section { padding: 1rem; }
        .analytics-card .number { font-size: 1.5rem; }
        table { font-size: 0.75rem; min-width: 300px; }
        th, td { padding: 6px; }
        .top-bar-right .icon-btn .badge { width: 14px; height: 14px; font-size: 0.4rem; top: -2px; right: -2px; }
        .health-gauge { width: 90px; height: 90px; font-size: 2rem; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0 0.8rem 0.8rem; }
        .quick-links .qlink { font-size: 0.65rem; padding: 0.15rem 0.3rem; }
        .welcome-left h1 { font-size: 1.1rem; }
        .date-badge { font-size: 0.75rem; padding: 0.3rem 0.6rem; }
        .top-bar-right .icon-btn { font-size: 0.8rem; }
        .analytics-grid { grid-template-columns: 1fr; }
        .health-gauge { width: 80px; height: 80px; font-size: 1.8rem; }
    }
</style>

<div class="main-content">

    <div class="sticky-header">
        <div class="top-bar">
            <div class="top-bar-left">
                <div class="breadcrumb">
                    <i class="ti ti-menu-2 menu-icon"></i>
                    <span class="current">Dashboard</span>
                    <span class="sep">/</span>
                    <span class="sub">Analytics</span>
                </div>
            </div>

            <div class="top-bar-center">
                <div class="quick-links">
                    <a href="../staff/book_for_customer.php" class="qlink"><i class="ti ti-calendar-plus"></i> Book</a>
                    <span class="link-sep">|</span>
                    <a href="services.php" class="qlink"><i class="ti ti-scissors"></i> Services</a>
                    <span class="link-sep">|</span>
                    <a href="staff.php" class="qlink"><i class="ti ti-users"></i> Staff</a>
                    <span class="link-sep">|</span>
                    <?php if (hasPlanFeature($salon_id, 'payroll')): ?>
                        <a href="payroll.php" class="qlink"><i class="ti ti-coin"></i> Payroll</a>
                        <span class="link-sep">|</span>
                    <?php endif; ?>
                    <?php if (hasPlanFeature($salon_id, 'permissions')): ?>
                        <a href="permissions.php" class="qlink"><i class="ti ti-key"></i> Permissions</a>
                        <span class="link-sep">|</span>
                    <?php endif; ?>
                    <a href="products.php" class="qlink"><i class="ti ti-box"></i> Products</a>
                    <span class="link-sep">|</span>
                    <a href="product_orders.php" class="qlink"><i class="ti ti-shopping-cart"></i> Orders</a>
                    <span class="link-sep">|</span>
                    <a href="reports.php" class="qlink"><i class="ti ti-chart-line"></i> Reports</a>
                    <span class="link-sep">|</span>
                    <a href="settings.php" class="qlink"><i class="ti ti-settings"></i> Settings</a>
                    <span class="link-sep">|</span>
                    <a href="branches.php" class="qlink"><i class="ti ti-building"></i> Branches</a>
                    <span class="link-sep">|</span>
                    <a href="loyalty.php" class="qlink"><i class="ti ti-star"></i> Loyalty</a>
                    <span class="link-sep">|</span>
                    <a href="analytics.php" class="qlink active"><i class="ti ti-chart-bar"></i> Analytics</a>
                </div>
            </div>

            <div class="top-bar-right">
                <a href="#" class="icon-btn" id="searchToggle" title="Search (Ctrl+K)">
                    <i class="ti ti-search"></i>
                </a>
                <a href="notifications.php" class="icon-btn" title="Notifications">
                    <i class="ti ti-bell"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="badge"><?php echo min($unread_count, 99); ?></span>
                    <?php endif; ?>
                </a>
                <div class="topbar-avatar"><i class="ti ti-crown"></i></div>
            </div>
        </div>
    </div>

    <div class="welcome-row">
        <div class="welcome-left">
            <h1>📈 Advanced Analytics</h1>
            <p class="subtitle">AI-powered insights and business intelligence</p>
            <span class="plan-badge">Enterprise</span>
        </div>
        <div class="date-badge">
            <i class="ti ti-calendar"></i> <?php echo date('j F Y'); ?>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; width: 100%;">
            <div class="form-group">
                <label>Start Date</label>
                <input type="date" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            <div class="form-group">
                <label>End Date</label>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            <button type="submit" class="filter-btn">📊 Generate</button>
        </form>
    </div>

    <!-- Key Analytics Cards -->
    <div class="analytics-grid">
        <div class="analytics-card green">
            <div class="icon">💰</div>
            <div class="number">KSh <?php echo number_format($total_revenue, 2); ?></div>
            <div class="label">Total Revenue (<?php echo $days; ?> days)</div>
            <div class="sub">Avg Daily: KSh <?php echo number_format($avg_daily, 2); ?></div>
        </div>
        <div class="analytics-card blue">
            <div class="icon">📈</div>
            <div class="number">KSh <?php echo number_format($forecast_next_30, 2); ?></div>
            <div class="label">Forecasted Revenue (30 days)</div>
            <div class="sub">Based on current trends</div>
        </div>
        <div class="analytics-card purple">
            <div class="icon">❤️</div>
            <div class="number"><?php echo $health_score; ?>%</div>
            <div class="label">Business Health Score</div>
            <div class="sub"><?php echo $health_score >= 80 ? 'Excellent' : ($health_score >= 60 ? 'Good' : 'Needs Attention'); ?></div>
        </div>
        <div class="analytics-card orange">
            <div class="icon">⏰</div>
            <div class="number"><?php echo mysqli_num_rows($peak_hours_result) > 0 ? date('g A', mktime(mysqli_fetch_assoc($peak_hours_result)['hour'], 0, 0)) : 'N/A'; ?></div>
            <div class="label">Peak Booking Hour</div>
            <div class="sub">Most busy hour of the day</div>
        </div>
    </div>

    <!-- Business Health Gauge -->
    <div class="analytics-section">
        <h2>🏥 Business Health Dashboard</h2>
        <div style="display: flex; flex-wrap: wrap; gap: 2rem; align-items: center; justify-content: center;">
            <div style="text-align: center;">
                <div class="health-gauge" style="border-color: <?php echo $health_score >= 80 ? '#28a745' : ($health_score >= 60 ? '#d4af37' : '#dc3545'); ?>;">
                    <?php echo $health_score; ?>%
                </div>
                <div style="color: #7a7568; font-size: 0.8rem;">Health Score</div>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <div style="display: flex; justify-content: space-between; padding: 0.3rem 0; border-bottom: 1px solid rgba(212, 175, 55, 0.05);">
                    <span style="color: #b8b2a0;">👤 Customers</span>
                    <span style="color: #d4af37;"><?php echo $health['total_customers'] ?? 0; ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 0.3rem 0; border-bottom: 1px solid rgba(212, 175, 55, 0.05);">
                    <span style="color: #b8b2a0;">👥 Staff</span>
                    <span style="color: #d4af37;"><?php echo $health['total_staff'] ?? 0; ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 0.3rem 0; border-bottom: 1px solid rgba(212, 175, 55, 0.05);">
                    <span style="color: #b8b2a0;">📅 Appointments</span>
                    <span style="color: #d4af37;"><?php echo $health['total_appointments'] ?? 0; ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 0.3rem 0; border-bottom: 1px solid rgba(212, 175, 55, 0.05);">
                    <span style="color: #b8b2a0;">💰 Revenue</span>
                    <span style="color: #d4af37;">KSh <?php echo number_format($health['total_revenue'] ?? 0, 2); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 0.3rem 0;">
                    <span style="color: #b8b2a0;">📦 Low Stock Items</span>
                    <span style="color: <?php echo ($health['low_stock_items'] ?? 0) > 3 ? '#ffc107' : '#28a745'; ?>;"><?php echo $health['low_stock_items'] ?? 0; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Peak Hours -->
    <div class="analytics-section">
        <h2>⏰ Peak Hours Analysis</h2>
        <?php if (mysqli_num_rows($peak_hours_result) > 0): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 0.5rem; margin-top: 0.5rem;">
                <?php 
                $max_hours = 0;
                $peak_data = [];
                while ($row = mysqli_fetch_assoc($peak_hours_result)) {
                    $peak_data[] = $row;
                    if ($row['total_appointments'] > $max_hours) $max_hours = $row['total_appointments'];
                }
                foreach ($peak_data as $hour):
                    $pct = $max_hours > 0 ? round(($hour['total_appointments'] / $max_hours) * 100) : 0;
                    $color = $pct >= 80 ? '#28a745' : ($pct >= 50 ? '#d4af37' : '#ffc107');
                ?>
                    <div style="text-align: center; background: #1a1a1a; padding: 0.5rem; border-radius: 8px;">
                        <div style="font-size: 0.7rem; color: #7a7568;"><?php echo date('g A', mktime($hour['hour'], 0, 0)); ?></div>
                        <div style="height: 40px; display: flex; align-items: flex-end; justify-content: center;">
                            <div style="width: 20px; height: <?php echo $pct; ?>%; min-height: 4px; background: <?php echo $color; ?>; border-radius: 4px 4px 0 0;"></div>
                        </div>
                        <div style="font-size: 0.6rem; color: #b8b2a0;"><?php echo $hour['total_appointments']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state" style="padding: 1.5rem; text-align: center; color: #7a7568;">No appointment data available.</div>
        <?php endif; ?>
    </div>

    <!-- Customer Churn Risk -->
    <div class="analytics-section">
        <h2>⚠️ Customer Churn Risk</h2>
        <?php if (mysqli_num_rows($churn_result) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>Customer</th><th>Last Visit</th><th>Visits</th><th>Days Since</th><th>Risk</th></tr>
                    </thead>
                    <tbody>
                        <?php while($risk = mysqli_fetch_assoc($churn_result)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($risk['full_name']); ?></td>
                                <td><?php echo $risk['last_visit'] ? date('M d, Y', strtotime($risk['last_visit'])) : 'Never'; ?></td>
                                <td><?php echo $risk['total_visits']; ?></td>
                                <td><?php echo $risk['days_since_last_visit']; ?></td>
                                <td><span class="churn-risk-<?php echo strtolower($risk['churn_risk']); ?>"><?php echo $risk['churn_risk']; ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state" style="padding: 1.5rem; text-align: center; color: #7a7568;">No at-risk customers found. Great job!</div>
        <?php endif; ?>
    </div>

    <!-- Staff Performance Trends -->
    <div class="analytics-section">
        <h2>👥 Staff Performance Trends</h2>
        <?php if (mysqli_num_rows($staff_trends_result) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>Staff</th><th>Appointments</th><th>Completed</th><th>Revenue</th><th>Completion Rate</th></tr>
                    </thead>
                    <tbody>
                        <?php while($staff = mysqli_fetch_assoc($staff_trends_result)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($staff['full_name']); ?></td>
                                <td><?php echo $staff['total_appointments'] ?? 0; ?></td>
                                <td><?php echo $staff['completed'] ?? 0; ?></td>
                                <td>KSh <?php echo number_format($staff['total_revenue'] ?? 0, 2); ?></td>
                                <td><?php echo number_format($staff['completion_rate'] ?? 0, 1); ?>%</td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state" style="padding: 1.5rem; text-align: center; color: #7a7568;">No staff performance data available.</div>
        <?php endif; ?>
    </div>

    <!-- Customer Lifetime Value -->
    <div class="analytics-section">
        <h2>💎 Top Customers by Lifetime Value</h2>
        <?php if (mysqli_num_rows($clv_result) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>Customer</th><th>Visits</th><th>Total Spent</th><th>Monthly Value</th></tr>
                    </thead>
                    <tbody>
                        <?php while($clv = mysqli_fetch_assoc($clv_result)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($clv['full_name']); ?></td>
                                <td><?php echo $clv['total_visits']; ?></td>
                                <td>KSh <?php echo number_format($clv['total_spent'], 2); ?></td>
                                <td>KSh <?php echo number_format($clv['monthly_value'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state" style="padding: 1.5rem; text-align: center; color: #7a7568;">No customer data available.</div>
        <?php endif; ?>
    </div>

    <!-- Service Heatmap -->
    <div class="analytics-section">
        <h2>🔥 Service Performance Heatmap</h2>
        <?php if (mysqli_num_rows($heatmap_result) > 0): 
            $max_revenue = 0;
            $heatmap_data = [];
            while ($row = mysqli_fetch_assoc($heatmap_result)) {
                $heatmap_data[] = $row;
                if ($row['revenue'] > $max_revenue) $max_revenue = $row['revenue'];
            }
            $max_revenue = $max_revenue > 0 ? $max_revenue : 1;
        ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.8rem;">
                <?php foreach ($heatmap_data as $service): 
                    $intensity = round(($service['revenue'] / $max_revenue) * 100);
                    $color = $intensity >= 80 ? '#28a745' : ($intensity >= 50 ? '#d4af37' : '#ffc107');
                    $bg = $intensity >= 80 ? 'rgba(40,167,69,0.15)' : ($intensity >= 50 ? 'rgba(212,175,55,0.15)' : 'rgba(255,193,7,0.15)');
                ?>
                    <div style="background: <?php echo $bg; ?>; border: 1px solid <?php echo $color; ?>; border-radius: 8px; padding: 0.8rem; text-align: center;">
                        <div style="color: #f5f0e1; font-weight: 500; font-size: 0.9rem;"><?php echo htmlspecialchars($service['service_name']); ?></div>
                        <div style="display: flex; justify-content: space-between; margin-top: 0.3rem;">
                            <span style="color: #7a7568; font-size: 0.7rem;">Bookings: <?php echo $service['bookings']; ?></span>
                            <span style="color: <?php echo $color; ?>; font-weight: 600; font-size: 0.8rem;">KSh <?php echo number_format($service['revenue'], 2); ?></span>
                        </div>
                        <div style="margin-top: 0.3rem; background: #1a1a1a; border-radius: 4px; height: 4px; overflow: hidden;">
                            <div style="height: 100%; width: <?php echo $intensity; ?>%; background: <?php echo $color; ?>; border-radius: 4px;"></div>
                        </div>
                        <div style="color: #7a7568; font-size: 0.6rem; margin-top: 0.2rem;">
                            <?php echo $service['unique_customers'] ?? 0; ?> customers • <?php echo $service['weekly_avg'] ?? 0; ?> weekly avg
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state" style="padding: 1.5rem; text-align: center; color: #7a7568;">No service data available.</div>
        <?php endif; ?>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<?php include '../includes/footer.php'; ?>