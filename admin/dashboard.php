<?php
/**
 * Salon Pro — Admin Dashboard
 * Luxury gold/black theme
 * Plan-Based Quick Links:
 * Basic: Book, Services, Staff, Reports, Settings
 * Premium: Basic + Payroll, Permissions, Products, Orders
 * Enterprise: Premium + Branches, Loyalty, Analytics
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
// GET PLAN FEATURES
// ============================================
$plan_features = getSalonPlanFeatures($salon_id);
$current_plan = $plan_features['plan_name'];
$plan_key = strtolower($current_plan);
$next_plan = getNextPlan($plan_key);
$can_upgrade = ($next_plan !== null);

// ============================================
// PLAN-BASED FEATURE CHECKS
// ============================================
$has_payroll = hasPlanFeature($salon_id, 'payroll');
$has_permissions = hasPlanFeature($salon_id, 'permissions');
$has_products = hasPlanFeature($salon_id, 'products');
$has_reports = hasPlanFeature($salon_id, 'reports');
$has_settings = hasPlanFeature($salon_id, 'settings');
$has_advanced_reports = hasPlanFeature($salon_id, 'advanced_reports');

// Enterprise-only features
$has_branches = ($plan_key === 'enterprise');
$has_loyalty = ($plan_key === 'enterprise');
$has_analytics = ($plan_key === 'enterprise');

// ============================================
// STATISTICS (Admin sees ALL for their salon)
// ============================================

// Total Customers
$customers_query = "SELECT COUNT(*) as count FROM users WHERE role = 'customer' AND salon_id = $salon_id AND is_active = 1";
$customers_result = mysqli_query($conn, $customers_query);
$total_customers = mysqli_fetch_assoc($customers_result)['count'] ?? 0;

// Total Appointments
$appointments_query = "SELECT COUNT(*) as count FROM appointments WHERE salon_id = $salon_id";
$appointments_result = mysqli_query($conn, $appointments_query);
$total_appointments = mysqli_fetch_assoc($appointments_result)['count'] ?? 0;

// Pending Appointments
$pending_query = "SELECT COUNT(*) as count FROM appointments WHERE salon_id = $salon_id AND status IN ('pending', 'confirmed')";
$pending_result = mysqli_query($conn, $pending_query);
$pending_appointments = mysqli_fetch_assoc($pending_result)['count'] ?? 0;

// Total Revenue
$revenue_query = "SELECT SUM(p.amount) as total FROM payments p JOIN appointments a ON p.appointment_id = a.id WHERE a.salon_id = $salon_id AND p.payment_status = 'paid'";
$revenue_result = mysqli_query($conn, $revenue_query);
$total_revenue = mysqli_fetch_assoc($revenue_result)['total'] ?? 0;

// Today's Appointments
$today = date('Y-m-d');
$today_query = "SELECT COUNT(*) as count FROM appointments WHERE salon_id = $salon_id AND appointment_date = '$today'";
$today_result = mysqli_query($conn, $today_query);
$today_appointments_count = mysqli_fetch_assoc($today_result)['count'] ?? 0;

// Total Staff
$staff_query = "SELECT COUNT(*) as count FROM users WHERE role = 'staff' AND salon_id = $salon_id AND is_active = 1";
$staff_result = mysqli_query($conn, $staff_query);
$total_staff = mysqli_fetch_assoc($staff_result)['count'] ?? 0;

// ============================================
// ADVANCED STATISTICS
// ============================================

// 1. Best Selling Product (last 30 days)
$best_product_query = "SELECT p.id, p.name, p.image, SUM(oi.quantity) as total_sold, SUM(oi.quantity * oi.price) as revenue
                       FROM order_items oi
                       JOIN orders o ON oi.order_id = o.id
                       JOIN products p ON oi.product_id = p.id
                       WHERE o.salon_id = $salon_id
                       AND o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                       AND o.status != 'cancelled'
                       GROUP BY p.id
                       ORDER BY total_sold DESC
                       LIMIT 1";
$best_product_result = mysqli_query($conn, $best_product_query);
$best_product = mysqli_fetch_assoc($best_product_result);

// 2. Most Booked Service (last 30 days)
$most_booked_query = "SELECT s.id, s.service_name, COUNT(a.id) as total_bookings
                      FROM appointments a
                      JOIN services s ON a.service_id = s.id
                      WHERE a.salon_id = $salon_id
                      AND a.appointment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                      AND a.status != 'cancelled'
                      GROUP BY s.id
                      ORDER BY total_bookings DESC
                      LIMIT 1";
$most_booked_result = mysqli_query($conn, $most_booked_query);
$most_booked = mysqli_fetch_assoc($most_booked_result);

// 3. Best Performing Staff (mix of completed appointments + revenue + ratings)
$best_staff_query = "SELECT u.id, u.full_name, 
                     COUNT(a.id) as completed_appointments,
                     SUM(s.price) as total_revenue,
                     ROUND(COUNT(a.id) * 0.5 + SUM(s.price) / 1000 * 0.3 + 0.2, 2) as performance_score
                     FROM appointments a
                     JOIN users u ON a.staff_id = u.id
                     JOIN services s ON a.service_id = s.id
                     WHERE a.salon_id = $salon_id
                     AND a.appointment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                     AND a.status = 'served'
                     GROUP BY u.id
                     ORDER BY performance_score DESC
                     LIMIT 1";
$best_staff_result = mysqli_query($conn, $best_staff_query);
$best_staff = mysqli_fetch_assoc($best_staff_result);

// 4. Monthly Revenue
$monthly_revenue_query = "SELECT 
    DATE_FORMAT(order_date, '%Y-%m') as month,
    SUM(total_amount) as revenue,
    COUNT(*) as orders
    FROM orders
    WHERE salon_id = $salon_id
    AND status != 'cancelled'
    AND order_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(order_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12";
$monthly_revenue_result = mysqli_query($conn, $monthly_revenue_query);
$monthly_revenue_data = [];
while ($row = mysqli_fetch_assoc($monthly_revenue_result)) {
    $monthly_revenue_data[] = $row;
}

// 5. New Customers (last 30 days)
$new_customers_query = "SELECT COUNT(*) as count FROM users WHERE role = 'customer' AND salon_id = $salon_id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$new_customers_result = mysqli_query($conn, $new_customers_query);
$new_customers = mysqli_fetch_assoc($new_customers_result)['count'] ?? 0;

// 6. Service Popularity (last 30 days)
$service_popularity_query = "SELECT s.service_name, COUNT(a.id) as bookings
                             FROM appointments a
                             JOIN services s ON a.service_id = s.id
                             WHERE a.salon_id = $salon_id
                             AND a.appointment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                             AND a.status != 'cancelled'
                             GROUP BY s.id
                             ORDER BY bookings DESC
                             LIMIT 5";
$service_popularity_result = mysqli_query($conn, $service_popularity_query);

// ============================================
// UPCOMING APPOINTMENTS
// ============================================
$upcoming_query = "SELECT a.*, c.full_name as customer_name, s.service_name, u.full_name as staff_name 
                   FROM appointments a 
                   JOIN users c ON a.customer_id = c.id 
                   JOIN services s ON a.service_id = s.id 
                   LEFT JOIN users u ON a.staff_id = u.id 
                   WHERE a.salon_id = $salon_id 
                   AND a.appointment_date >= CURDATE() 
                   AND a.status NOT IN ('completed', 'cancelled') 
                   ORDER BY a.appointment_date ASC, a.appointment_time ASC 
                   LIMIT 5";
$upcoming_appointments = mysqli_query($conn, $upcoming_query);

// ============================================
// CURRENT QUEUE
// ============================================
$queue_query = "SELECT a.*, c.full_name as customer_name, s.service_name 
                FROM appointments a 
                JOIN users c ON a.customer_id = c.id 
                JOIN services s ON a.service_id = s.id 
                WHERE a.salon_id = $salon_id 
                AND a.status = 'pending' 
                AND a.appointment_date = CURDATE()
                ORDER BY a.queue_position ASC, a.appointment_time ASC 
                LIMIT 5";
$queue = mysqli_query($conn, $queue_query);

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

    .quick-links .qlink.locked {
        color: #555;
        cursor: not-allowed;
        opacity: 0.5;
        pointer-events: none;
    }

    .quick-links .qlink .lock-icon {
        font-size: 0.6rem;
        color: #555;
        margin-left: 0.2rem;
    }

    /* Upgrade Button */
    .upgrade-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 6px 18px;
        border-radius: 25px;
        font-size: 0.8rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
        color: #4a90d9;
        background: transparent;
        border: 1px solid transparent;
    }

    .upgrade-btn i {
        color: #4a90d9;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .upgrade-btn:hover {
        background: rgba(74, 144, 217, 0.15);
        border-color: rgba(74, 144, 217, 0.3);
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(74, 144, 217, 0.2);
    }

    .upgrade-btn:hover i {
        color: #4a90d9;
        transform: scale(1.1);
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
    }

    .plan-badge.basic { background: rgba(23, 162, 184, 0.15); color: #17a2b8; border: 1px solid #17a2b8; }
    .plan-badge.premium { background: rgba(212, 175, 55, 0.15); color: #d4af37; border: 1px solid #d4af37; }
    .plan-badge.enterprise { background: rgba(40, 167, 69, 0.15); color: #28a745; border: 1px solid #28a745; }

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

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: #0e0e0e;
        border-radius: 12px;
        padding: 1rem 1.2rem;
        text-align: center;
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
        color: #7a7568;
        font-size: 0.75rem;
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

    /* Advanced Stats */
    .advanced-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .advanced-stat-card {
        background: #0e0e0e;
        border-radius: 12px;
        padding: 1rem 1.2rem;
        border: 1px solid rgba(212, 175, 55, 0.1);
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .advanced-stat-card:hover {
        border-color: rgba(212, 175, 55, 0.3);
        transform: translateY(-2px);
    }

    .advanced-stat-card .icon {
        font-size: 2rem;
        flex-shrink: 0;
    }

    .advanced-stat-card .content {
        flex: 1;
    }

    .advanced-stat-card .content .label {
        color: #7a7568;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .advanced-stat-card .content .value {
        color: #f5f0e1;
        font-size: 1.1rem;
        font-weight: 600;
    }

    .advanced-stat-card .content .sub {
        color: #d4af37;
        font-size: 0.8rem;
    }

    /* Dashboard Grid */
    .dashboard-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .dashboard-grid .card {
        background: #0e0e0e;
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

    /* Service Popularity List */
    .service-pop-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.4rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    }

    .service-pop-item:last-child {
        border-bottom: none;
    }

    .service-pop-item .service-name {
        color: #ddd;
        font-size: 0.85rem;
    }

    .service-pop-item .service-count {
        color: #d4af37;
        font-size: 0.8rem;
        font-weight: 500;
    }

    .service-pop-item .service-bar {
        flex: 1;
        margin: 0 1rem;
        height: 4px;
        background: #1a1a1a;
        border-radius: 4px;
        overflow: hidden;
        max-width: 100px;
    }

    .service-pop-item .service-bar .fill {
        height: 100%;
        background: linear-gradient(90deg, #d4af37, #f0d878);
        border-radius: 4px;
        transition: width 0.3s;
    }

    /* Appointment & Queue Items */
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

    .appt-status.pending { background: rgba(212, 175, 55, 0.15); color: #d4af37; border: 1px solid #d4af37; }
    .appt-status.confirmed { background: rgba(40, 167, 69, 0.15); color: #28a745; border: 1px solid #28a745; }
    .appt-status.completed { background: rgba(23, 162, 184, 0.15); color: #17a2b8; border: 1px solid #17a2b8; }
    .appt-status.cancelled { background: rgba(220, 53, 69, 0.15); color: #dc3545; border: 1px solid #dc3545; }
    .appt-status.served { background: rgba(40, 167, 69, 0.15); color: #28a745; border: 1px solid #28a745; }

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

    .empty-state {
        color: #666;
        text-align: center;
        padding: 1.5rem 0;
        font-size: 0.9rem;
    }

    /* Monthly Revenue */
    .monthly-revenue .revenue-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.3rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        font-size: 0.85rem;
    }

    .monthly-revenue .revenue-row:last-child {
        border-bottom: none;
    }

    .monthly-revenue .revenue-row .month {
        color: #b8b2a0;
    }

    .monthly-revenue .revenue-row .amount {
        color: #d4af37;
        font-weight: 500;
    }

    .monthly-revenue .revenue-row .orders-count {
        color: #7a7568;
        font-size: 0.7rem;
    }

    .back-link {
        display: none;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
        .advanced-stats-grid {
            grid-template-columns: 1fr 1fr;
        }
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
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 0.8rem; }
        .stat-card { padding: 0.8rem; }
        .stat-card .stat-number { font-size: 1.4rem; }
        .advanced-stats-grid { grid-template-columns: 1fr; }
        .advanced-stat-card { padding: 0.8rem; }
        .dashboard-grid .card { padding: 1rem; }
        .appointment-item { flex-direction: column; align-items: flex-start; }
        .queue-item { flex-direction: column; align-items: flex-start; }
        .queue-item .btn-serve { width: 100%; text-align: center; }
        .service-pop-item .service-bar { max-width: 60px; }
        .upgrade-btn { font-size: 0.7rem; padding: 4px 12px; }
        .top-bar-right .icon-btn .badge { width: 14px; height: 14px; font-size: 0.4rem; top: -2px; right: -2px; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0 0.8rem 0.8rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .quick-links .qlink { font-size: 0.65rem; padding: 0.15rem 0.3rem; }
        .welcome-left h1 { font-size: 1.1rem; }
        .date-badge { font-size: 0.75rem; padding: 0.3rem 0.6rem; }
        .top-bar-right .icon-btn { font-size: 0.8rem; }
        .dashboard-grid .card { padding: 0.8rem; }
        .stat-card .stat-number { font-size: 1.2rem; }
        .service-pop-item .service-bar { max-width: 40px; }
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
                    <span class="sub">Overview</span>
                </div>
            </div>

            <!-- ============================================
               PLAN-BASED QUICK LINKS
               ============================================ -->
            <div class="top-bar-center">
                <div class="quick-links">
                    
                    <!-- 1. BOOK - Always visible -->
                    <a href="../staff/book_for_customer.php" class="qlink"><i class="ti ti-calendar-plus"></i> Book</a>
                    <span class="link-sep">|</span>
                    
                    <!-- 2. SERVICES - Always visible -->
                    <a href="services.php" class="qlink"><i class="ti ti-scissors"></i> Services</a>
                    <span class="link-sep">|</span>
                    
                    <!-- 3. STAFF - Always visible -->
                    <a href="staff.php" class="qlink"><i class="ti ti-users"></i> Staff</a>
                    <span class="link-sep">|</span>
                    
                    <!-- 4. PAYROLL - Premium+ -->
                    <?php if ($has_payroll): ?>
                        <a href="payroll.php" class="qlink"><i class="ti ti-coin"></i> Payroll</a>
                        <span class="link-sep">|</span>
                    <?php endif; ?>
                    
                    <!-- 5. PERMISSIONS - Premium+ -->
                    <?php if ($has_permissions): ?>
                        <a href="permissions.php" class="qlink"><i class="ti ti-key"></i> Permissions</a>
                        <span class="link-sep">|</span>
                    <?php endif; ?>
                    
                    <!-- 6. PRODUCTS - All plans (Basic, Premium, Enterprise) -->
                        <a href="products.php" class="qlink"><i class="ti ti-box"></i> Products</a>
                            <span class="link-sep">|</span>
                        <a href="product_orders.php" class="qlink"><i class="ti ti-shopping-cart"></i> Orders</a>
                            <span class="link-sep">|</span>
                            
                    <!-- 7. REPORTS - All plans (Basic, Premium, Enterprise) -->
                    <?php if ($has_reports): ?>
                        <a href="reports.php" class="qlink"><i class="ti ti-chart-line"></i> Reports</a>
                        <span class="link-sep">|</span>
                    <?php endif; ?>
                    
                    <!-- 8. SETTINGS - All plans -->
                    <a href="settings.php" class="qlink"><i class="ti ti-settings"></i> Settings</a>
                    
                    <!-- 9. ENTERPRISE-ONLY LINKS -->
                    <?php if ($plan_key === 'enterprise'): ?>
                        <span class="link-sep">|</span>
                        <a href="branches.php" class="qlink"><i class="ti ti-building"></i> Branches</a>
                        <span class="link-sep">|</span>
                        <a href="loyalty.php" class="qlink"><i class="ti ti-star"></i> Loyalty</a>
                        <span class="link-sep">|</span>
                        <a href="analytics.php" class="qlink"><i class="ti ti-chart-bar"></i> Analytics</a>
                    <?php endif; ?>
                    
                </div>
            </div>

            <div class="top-bar-right">
                <!-- UPGRADE BUTTON -->
                <?php if ($can_upgrade): ?>
                    <a href="upgrade.php" class="upgrade-btn" title="Upgrade to <?php echo ucfirst($next_plan); ?> Plan">
                        <i class="fas fa-star"></i> Upgrade
                    </a>
                <?php endif; ?>
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

    <!-- ============================================
       WELCOME ROW
       ============================================ -->
    <div class="welcome-row">
        <div class="welcome-left">
            <h1>👋 Welcome back, <?php echo htmlspecialchars($admin_name); ?>!</h1>
            <p class="subtitle">Here's what's happening at your salon today.</p>
            <span class="plan-badge <?php echo $plan_key; ?>">
                <?php echo $current_plan; ?> Plan
            </span>
        </div>
        <div class="date-badge">
            <i class="ti ti-calendar"></i> <?php echo date('j F Y'); ?>
        </div>
    </div>

    <!-- ============================================
       STATS GRID (Core Stats)
       ============================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-icon">👤</span>
            <div class="stat-number"><?php echo $total_customers; ?></div>
            <div class="stat-label">Total Customers</div>
        </div>

        <div class="stat-card">
            <span class="stat-icon">📅</span>
            <div class="stat-number"><?php echo $total_appointments; ?></div>
            <div class="stat-label">Total Appointments</div>
        </div>

        <div class="stat-card orange">
            <span class="stat-icon">⏳</span>
            <div class="stat-number"><?php echo $pending_appointments; ?></div>
            <div class="stat-label">Pending</div>
        </div>

        <div class="stat-card green">
            <span class="stat-icon">💰</span>
            <div class="stat-number">KSh <?php echo number_format($total_revenue, 2); ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>

        <div class="stat-card blue">
            <span class="stat-icon">📋</span>
            <div class="stat-number"><?php echo $today_appointments_count; ?></div>
            <div class="stat-label">Today's Appointments</div>
        </div>

        <div class="stat-card purple">
            <span class="stat-icon">👥</span>
            <div class="stat-number"><?php echo $total_staff; ?></div>
            <div class="stat-label">Staff Members</div>
        </div>
    </div>

    <!-- ============================================
       ADVANCED STATS
       ============================================ -->
    <div class="advanced-stats-grid">

        <!-- Best Selling Product -->
        <div class="advanced-stat-card">
            <div class="icon">🏆</div>
            <div class="content">
                <div class="label">Best Selling Product</div>
                <div class="value">
                    <?php if ($best_product): ?>
                        <?php echo htmlspecialchars($best_product['name']); ?>
                    <?php else: ?>
                        No sales yet
                    <?php endif; ?>
                </div>
                <div class="sub">
                    <?php if ($best_product): ?>
                        <?php echo $best_product['total_sold']; ?> units sold
                        <span style="color:#7a7568; margin-left: 0.5rem;">
                            (KSh <?php echo number_format($best_product['revenue'], 2); ?>)
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Most Booked Service -->
        <div class="advanced-stat-card">
            <div class="icon">💇</div>
            <div class="content">
                <div class="label">Most Booked Service</div>
                <div class="value">
                    <?php if ($most_booked): ?>
                        <?php echo htmlspecialchars($most_booked['service_name']); ?>
                    <?php else: ?>
                        No bookings yet
                    <?php endif; ?>
                </div>
                <div class="sub">
                    <?php if ($most_booked): ?>
                        <?php echo $most_booked['total_bookings']; ?> bookings this month
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Best Performing Staff -->
        <div class="advanced-stat-card">
            <div class="icon">⭐</div>
            <div class="content">
                <div class="label">Best Performing Staff</div>
                <div class="value">
                    <?php if ($best_staff): ?>
                        <?php echo htmlspecialchars($best_staff['full_name']); ?>
                    <?php else: ?>
                        No staff data yet
                    <?php endif; ?>
                </div>
                <div class="sub">
                    <?php if ($best_staff): ?>
                        <?php echo $best_staff['completed_appointments']; ?> appointments completed
                        <span style="color:#7a7568; margin-left: 0.5rem;">
                            (KSh <?php echo number_format($best_staff['total_revenue'], 2); ?>)
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- New Customers + Monthly Revenue -->
        <div class="advanced-stat-card">
            <div class="icon">📈</div>
            <div class="content">
                <div class="label">New Customers (30 days)</div>
                <div class="value"><?php echo $new_customers; ?></div>
                <div class="sub">
                    <?php if (!empty($monthly_revenue_data)): ?>
                        This month: KSh <?php echo number_format($monthly_revenue_data[0]['revenue'] ?? 0, 2); ?>
                    <?php else: ?>
                        No revenue data
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- ============================================
       DASHBOARD GRID: Upcoming Appointments + Queue
       ============================================ -->
    <div class="dashboard-grid">

        <!-- Upcoming Appointments -->
        <div class="card">
            <h3>
                📋 Upcoming Appointments
                <a href="appointments.php" class="view-all">View All →</a>
            </h3>
            <?php if (mysqli_num_rows($upcoming_appointments) > 0): ?>
                <?php while($apt = mysqli_fetch_assoc($upcoming_appointments)): ?>
                    <div class="appointment-item">
                        <span class="appt-date"><?php echo date('M d', strtotime($apt['appointment_date'])); ?></span>
                        <div class="appt-details">
                            <div class="appt-service">💇 <?php echo htmlspecialchars($apt['service_name']); ?></div>
                            <div class="appt-customer"><?php echo htmlspecialchars($apt['customer_name']); ?> • <?php echo htmlspecialchars($apt['staff_name'] ?? 'Unassigned'); ?></div>
                        </div>
                        <span class="appt-status <?php echo $apt['status']; ?>"><?php echo ucfirst($apt['status']); ?></span>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">No upcoming appointments scheduled.</div>
            <?php endif; ?>
        </div>

        <!-- Current Queue -->
        <div class="card">
            <h3>
                🚀 Current Queue
                <a href="appointments.php" class="view-all">View All →</a>
            </h3>
            <?php if (mysqli_num_rows($queue) > 0): ?>
                <?php while($q = mysqli_fetch_assoc($queue)): ?>
                    <div class="queue-item">
                        <span class="queue-position">#<?php echo $q['queue_position'] ?? '?'; ?></span>
                        <div class="queue-details">
                            <div class="queue-customer"><?php echo htmlspecialchars($q['customer_name']); ?></div>
                            <div class="queue-service"><?php echo htmlspecialchars($q['service_name']); ?></div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                            <span class="queue-time">⏰ <?php echo date('g:i A', strtotime($q['appointment_time'])); ?></span>
                            <form method="POST" action="appointments.php" style="display: inline;">
                                <input type="hidden" name="appointment_id" value="<?php echo $q['id']; ?>">
                                <input type="hidden" name="action" value="serve">
                                <button type="submit" class="btn-serve" onclick="return confirm('Mark this customer as served?')">✅ Serve</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">🎉 Queue is empty. Great job!</div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ============================================
       SERVICE POPULARITY & MONTHLY REVENUE
       ============================================ -->
    <div class="dashboard-grid">

        <!-- Service Popularity -->
        <div class="card">
            <h3>
                📊 Service Popularity (Last 30 Days)
                <a href="reports.php" class="view-all">View Full Report →</a>
            </h3>
            <?php if (mysqli_num_rows($service_popularity_result) > 0): 
                $max_bookings = 0;
                $pop_data = [];
                while ($row = mysqli_fetch_assoc($service_popularity_result)) {
                    $pop_data[] = $row;
                    if ($row['bookings'] > $max_bookings) $max_bookings = $row['bookings'];
                }
                foreach ($pop_data as $item): 
                    $pct = $max_bookings > 0 ? round(($item['bookings'] / $max_bookings) * 100) : 0;
            ?>
                <div class="service-pop-item">
                    <span class="service-name"><?php echo htmlspecialchars($item['service_name']); ?></span>
                    <div class="service-bar">
                        <div class="fill" style="width: <?php echo $pct; ?>%;"></div>
                    </div>
                    <span class="service-count"><?php echo $item['bookings']; ?></span>
                </div>
            <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">No service data available yet.</div>
            <?php endif; ?>
        </div>

        <!-- Monthly Revenue -->
        <div class="card">
            <h3>
                📈 Monthly Revenue
                <a href="reports.php" class="view-all">View Full Report →</a>
            </h3>
            <div class="monthly-revenue">
                <?php if (!empty($monthly_revenue_data)): ?>
                    <?php foreach ($monthly_revenue_data as $data): ?>
                        <div class="revenue-row">
                            <span class="month"><?php echo date('M Y', strtotime($data['month'] . '-01')); ?></span>
                            <span class="orders-count">(<?php echo $data['orders']; ?> orders)</span>
                            <span class="amount">KSh <?php echo number_format($data['revenue'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">No revenue data available.</div>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>