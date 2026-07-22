<?php
/**
 * Salon Pro — Admin: Reports & Analytics
 * Luxury gold/black theme
 * Plan-Based Access:
 * - Basic: Basic Reports (Sales, Services, Customers)
 * - Premium: Full Reports (Sales, Staff, Services, Customers)
 * - Enterprise: Advanced Reports (Sales, Staff, Services, Customers, Inventory, Analytics)
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
// PLAN FEATURES CHECK
// ============================================
$plan_features = getSalonPlanFeatures($salon_id);
$current_plan = $plan_features['plan_name'];
$plan_key = strtolower($current_plan);

$has_reports = hasPlanFeature($salon_id, 'reports'); // All plans have basic reports
$has_advanced_reports = hasPlanFeature($salon_id, 'advanced_reports'); // Enterprise only

// ============================================
// DATE FILTER
// ============================================
$report_type = isset($_GET['report']) ? $_GET['report'] : 'sales';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$period = isset($_GET['period']) ? $_GET['period'] : 'monthly';

// ============================================
// 1. SALES REPORT (All plans)
// ============================================
$sales_query = "SELECT 
    DATE(order_date) as date,
    COUNT(*) as orders,
    SUM(total_amount) as revenue,
    AVG(total_amount) as average_order_value
    FROM orders
    WHERE salon_id = $salon_id
    AND status != 'cancelled'
    AND order_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY DATE(order_date)
    ORDER BY date ASC";
$sales_result = mysqli_query($conn, $sales_query);

// Sales Summary
$sales_summary_query = "SELECT 
    COUNT(*) as total_orders,
    SUM(total_amount) as total_revenue,
    AVG(total_amount) as avg_order_value,
    MAX(total_amount) as max_order_value,
    MIN(total_amount) as min_order_value
    FROM orders
    WHERE salon_id = $salon_id
    AND status != 'cancelled'
    AND order_date BETWEEN '$start_date' AND '$end_date'";
$sales_summary_result = mysqli_query($conn, $sales_summary_query);
$sales_summary = mysqli_fetch_assoc($sales_summary_result);

// ============================================
// 2. STAFF PERFORMANCE REPORT (Premium+)
// ============================================
$staff_performance_result = null;
if ($has_advanced_reports || $plan_key == 'premium' || $plan_key == 'enterprise') {
    $staff_performance_query = "SELECT 
        u.id,
        u.full_name,
        COUNT(a.id) as total_appointments,
        SUM(CASE WHEN a.status = 'served' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN a.status != 'cancelled' THEN 1 ELSE 0 END) as total_served,
        SUM(s.price) as total_revenue,
        ROUND(COUNT(a.id) * 0.4 + SUM(CASE WHEN a.status = 'served' THEN 1 ELSE 0 END) * 0.3 + SUM(s.price) / 1000 * 0.3, 2) as performance_score
        FROM users u
        LEFT JOIN appointments a ON u.id = a.staff_id
        LEFT JOIN services s ON a.service_id = s.id
        WHERE u.salon_id = $salon_id
        AND u.role = 'staff'
        AND u.is_active = 1
        AND (a.appointment_date BETWEEN '$start_date' AND '$end_date' OR a.id IS NULL)
        GROUP BY u.id
        ORDER BY performance_score DESC";
    $staff_performance_result = mysqli_query($conn, $staff_performance_query);
}

// ============================================
// 3. SERVICE POPULARITY REPORT (All plans)
// ============================================
$service_popularity_query = "SELECT 
    s.id,
    s.service_name,
    COUNT(a.id) as bookings,
    SUM(s.price) as total_revenue,
    AVG(s.price) as avg_price,
    ROUND(COUNT(a.id) * 100.0 / (SELECT COUNT(*) FROM appointments WHERE salon_id = $salon_id AND status != 'cancelled' AND appointment_date BETWEEN '$start_date' AND '$end_date'), 2) as percentage
    FROM services s
    LEFT JOIN appointments a ON s.id = a.service_id
    WHERE s.salon_id = $salon_id
    AND (a.appointment_date BETWEEN '$start_date' AND '$end_date' OR a.id IS NULL)
    AND (a.status != 'cancelled' OR a.id IS NULL)
    GROUP BY s.id
    ORDER BY bookings DESC";
$service_popularity_result = mysqli_query($conn, $service_popularity_query);

// ============================================
// 4. CUSTOMER INSIGHTS (All plans)
// ============================================
$customer_insights_query = "SELECT 
    COUNT(*) as total_customers,
    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_customers,
    COUNT(DISTINCT a.customer_id) as active_customers,
    COUNT(a.id) as total_appointments,
    ROUND(COUNT(a.id) / NULLIF(COUNT(DISTINCT a.customer_id), 0), 1) as avg_appointments_per_customer
    FROM users u
    LEFT JOIN appointments a ON u.id = a.customer_id
    WHERE u.salon_id = $salon_id
    AND u.role = 'customer'
    AND u.is_active = 1
    AND (a.appointment_date BETWEEN '$start_date' AND '$end_date' OR a.id IS NULL)
    AND (a.status != 'cancelled' OR a.id IS NULL)";
$customer_insights_result = mysqli_query($conn, $customer_insights_query);
$customer_insights = mysqli_fetch_assoc($customer_insights_result);

// ============================================
// 5. INVENTORY REPORT (Enterprise only)
// ============================================
$inventory_report = null;
if ($has_advanced_reports) {
    $inventory_report_query = "SELECT 
        COUNT(*) as total_products,
        SUM(stock) as total_stock,
        SUM(price * stock) as inventory_value,
        SUM(CASE WHEN stock > reorder_level THEN 1 ELSE 0 END) as full_stock,
        SUM(CASE WHEN stock <= reorder_level AND stock > 0 THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) as out_of_stock
        FROM products
        WHERE salon_id = $salon_id";
    $inventory_report_result = mysqli_query($conn, $inventory_report_query);
    $inventory_report = mysqli_fetch_assoc($inventory_report_result);
}

// ============================================
// 6. ADVANCED ANALYTICS (Enterprise only)
// ============================================
$advanced_analytics = null;
if ($has_advanced_reports) {
    // Customer retention rate
    $retention_query = "SELECT 
        COUNT(DISTINCT CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN id END) as new_customers,
        COUNT(DISTINCT CASE WHEN created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) AND id IN (SELECT customer_id FROM appointments WHERE appointment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)) THEN id END) as returning_customers,
        COUNT(DISTINCT id) as total_customers
        FROM users
        WHERE salon_id = $salon_id AND role = 'customer' AND is_active = 1";
    $retention_result = mysqli_query($conn, $retention_query);
    $advanced_analytics = mysqli_fetch_assoc($retention_result);
    
    if ($advanced_analytics) {
        $advanced_analytics['retention_rate'] = $advanced_analytics['total_customers'] > 0 
            ? round(($advanced_analytics['returning_customers'] / $advanced_analytics['total_customers']) * 100, 2) 
            : 0;
    }
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

    .report-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        border-bottom: 1px solid rgba(212, 175, 55, 0.2);
        padding-bottom: 0.5rem;
    }

    .report-tabs .tab-btn {
        padding: 10px 24px;
        border-radius: 25px;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.3s;
        background: transparent;
        color: #888;
        border: 1px solid transparent;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .report-tabs .tab-btn:hover {
        color: #f0d878;
        background: rgba(212, 175, 55, 0.08);
    }

    .report-tabs .tab-btn.active {
        background: rgba(212, 175, 55, 0.15);
        color: #f0d878;
        border-color: #d4af37;
    }

    .report-tabs .tab-btn.locked {
        color: #555;
        cursor: not-allowed;
        opacity: 0.5;
        pointer-events: none;
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

    .filter-bar .form-group input,
    .filter-bar .form-group select {
        padding: 8px 14px;
        background: #1a1a1a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        color: #f5f0e1;
        font-size: 0.85rem;
    }

    .filter-bar .form-group input:focus,
    .filter-bar .form-group select:focus {
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

    .filter-bar .print-btn {
        padding: 8px 20px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        color: #aaa;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
    }

    .filter-bar .print-btn:hover {
        background: #333;
        color: white;
    }

    .filter-bar .export-btn {
        padding: 8px 20px;
        background: rgba(40, 167, 69, 0.15);
        border: 1px solid #28a745;
        border-radius: 25px;
        color: #28a745;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
    }

    .filter-bar .export-btn:hover {
        background: #28a745;
        color: white;
    }

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
        font-size: 1.8rem;
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
    .stat-card.red { border-left-color: #dc3545; }
    .stat-card.red .stat-number { color: #dc3545; }

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

    .performance-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
    }

    .performance-badge.high {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border: 1px solid #28a745;
    }

    .performance-badge.medium {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid #d4af37;
    }

    .performance-badge.low {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
        border: 1px solid #dc3545;
    }

    .section-title {
        color: #f0d878;
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 1rem;
        display: block;
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

    .empty-state {
        text-align: center;
        padding: 2rem 0;
        color: #7a7568;
    }

    .no-data {
        color: #555;
        text-align: center;
        padding: 1.5rem;
    }

    .advanced-analytics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .analytics-card {
        background: #0e0e0e;
        border-radius: 12px;
        padding: 1rem 1.2rem;
        border: 1px solid rgba(212, 175, 55, 0.1);
        text-align: center;
    }

    .analytics-card .analytics-number {
        font-size: 2rem;
        font-weight: bold;
        color: #d4af37;
    }

    .analytics-card .analytics-label {
        color: #7a7568;
        font-size: 0.75rem;
        margin-top: 0.2rem;
    }

    .analytics-card .analytics-sub {
        color: #b8b2a0;
        font-size: 0.8rem;
        margin-top: 0.3rem;
    }

    .plan-notice {
        background: rgba(212, 175, 55, 0.05);
        border: 1px solid rgba(212, 175, 55, 0.15);
        border-radius: 8px;
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .plan-notice .icon {
        font-size: 1.5rem;
    }

    .plan-notice .text {
        flex: 1;
        color: #b8b2a0;
        font-size: 0.85rem;
    }

    .plan-notice .text strong {
        color: #f0d878;
    }

    .plan-notice .upgrade-link {
        color: #d4af37;
        text-decoration: none;
        font-weight: 500;
        padding: 4px 16px;
        border: 1px solid #d4af37;
        border-radius: 20px;
        transition: all 0.3s;
    }

    .plan-notice .upgrade-link:hover {
        background: #d4af37;
        color: #050505;
    }

    @media (max-width: 1024px) {
        table { min-width: 400px; }
        .advanced-analytics-grid { grid-template-columns: 1fr 1fr; }
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
        .report-tabs { flex-wrap: wrap; }
        .report-tabs .tab-btn { font-size: 0.7rem; padding: 6px 14px; }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar .form-group input,
        .filter-bar .form-group select { width: 100%; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 0.8rem; }
        .stat-card { padding: 0.8rem; }
        .stat-card .stat-number { font-size: 1.4rem; }
        .table-wrapper { overflow-x: auto; }
        table { font-size: 0.75rem; min-width: 300px; }
        th, td { padding: 6px; }
        .advanced-analytics-grid { grid-template-columns: 1fr; }
        .plan-notice { flex-direction: column; text-align: center; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0 0.8rem 0.8rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .quick-links .qlink { font-size: 0.65rem; padding: 0.15rem 0.3rem; }
        .welcome-left h1 { font-size: 1.1rem; }
        .date-badge { font-size: 0.75rem; padding: 0.3rem 0.6rem; }
        .top-bar-right .icon-btn { font-size: 0.8rem; }
        .top-bar-right .icon-btn .badge { width: 14px; height: 14px; font-size: 0.4rem; top: -2px; right: -2px; }
        .report-tabs { flex-direction: column; align-items: stretch; }
        .report-tabs .tab-btn { text-align: center; justify-content: center; }
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
                    <span class="sub">Reports & Analytics</span>
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
                    <a href="reports.php" class="qlink active"><i class="ti ti-chart-line"></i> Reports</a>
                    <span class="link-sep">|</span>
                    <a href="settings.php" class="qlink"><i class="ti ti-settings"></i> Settings</a>
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
            <h1>📊 Reports & Analytics</h1>
            <p class="subtitle">Comprehensive insights for your salon performance</p>
            <span class="plan-badge <?php echo $plan_key; ?>">
                <?php echo $current_plan; ?> Plan
            </span>
        </div>
        <div class="date-badge">
            <i class="ti ti-calendar"></i> <?php echo date('j F Y'); ?>
        </div>
    </div>

    <!-- Plan Notice -->
    <div class="plan-notice">
        <span class="icon">📊</span>
        <div class="text">
            <strong><?php echo $current_plan; ?> Plan</strong> — 
            <?php if ($plan_key === 'basic'): ?>
                Basic reports (Sales, Services, Customers) available.
                <span style="color: #7a7568; margin-left: 0.5rem;">
                    Upgrade to <strong>Premium</strong> for Staff Performance reports or <strong>Enterprise</strong> for Inventory & Advanced Analytics.
                </span>
                <a href="upgrade.php" class="upgrade-link">✨ Upgrade</a>
            <?php elseif ($plan_key === 'premium'): ?>
                Full reports (Sales, Staff, Services, Customers) available.
                <span style="color: #7a7568; margin-left: 0.5rem;">
                    Upgrade to <strong>Enterprise</strong> for Inventory & Advanced Analytics.
                </span>
                <a href="upgrade.php" class="upgrade-link">✨ Upgrade to Enterprise</a>
            <?php else: ?>
                ✅ All reports and advanced analytics available.
            <?php endif; ?>
        </div>
    </div>

    <!-- Report Tabs -->
    <div class="report-tabs">
        <a href="?report=sales&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="tab-btn <?php echo ($report_type == 'sales' || $report_type == '') ? 'active' : ''; ?>">
            <i class="ti ti-coin"></i> Sales
        </a>
        <?php if ($plan_key == 'premium' || $plan_key == 'enterprise'): ?>
            <a href="?report=staff&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="tab-btn <?php echo ($report_type == 'staff') ? 'active' : ''; ?>">
                <i class="ti ti-users"></i> Staff Performance
            </a>
        <?php else: ?>
            <span class="tab-btn locked">
                <i class="ti ti-users"></i> Staff Performance 🔒
            </span>
        <?php endif; ?>
        <a href="?report=services&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="tab-btn <?php echo ($report_type == 'services') ? 'active' : ''; ?>">
            <i class="ti ti-scissors"></i> Service Popularity
        </a>
        <a href="?report=customers&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="tab-btn <?php echo ($report_type == 'customers') ? 'active' : ''; ?>">
            <i class="ti ti-user"></i> Customer Insights
        </a>
        <?php if ($plan_key === 'enterprise'): ?>
            <a href="?report=inventory&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="tab-btn <?php echo ($report_type == 'inventory') ? 'active' : ''; ?>">
                <i class="ti ti-box"></i> Inventory
            </a>
            <a href="?report=analytics&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="tab-btn <?php echo ($report_type == 'analytics') ? 'active' : ''; ?>">
                <i class="ti ti-chart-bar"></i> Analytics
            </a>
        <?php else: ?>
            <span class="tab-btn locked">
                <i class="ti ti-box"></i> Inventory 🔒
            </span>
            <span class="tab-btn locked">
                <i class="ti ti-chart-bar"></i> Analytics 🔒
            </span>
        <?php endif; ?>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; width: 100%;">
            <input type="hidden" name="report" value="<?php echo $report_type; ?>">
            <div class="form-group">
                <label>Start Date</label>
                <input type="date" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            <div class="form-group">
                <label>End Date</label>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            <button type="submit" class="filter-btn">📊 Generate</button>
            <button type="button" class="print-btn" onclick="window.print()">🖨️ Print</button>
            <button type="button" class="export-btn" onclick="exportReport()">📥 Export CSV</button>
        </form>
    </div>

    <!-- ============================================
       SALES REPORT (All plans)
       ============================================ -->
    <?php if ($report_type == 'sales' || $report_type == ''): ?>
        <div class="stats-grid">
            <div class="stat-card green">
                <span class="stat-icon">💰</span>
                <div class="stat-number">KSh <?php echo number_format($sales_summary['total_revenue'] ?? 0, 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card blue">
                <span class="stat-icon">📦</span>
                <div class="stat-number"><?php echo $sales_summary['total_orders'] ?? 0; ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card orange">
                <span class="stat-icon">📊</span>
                <div class="stat-number">KSh <?php echo number_format($sales_summary['avg_order_value'] ?? 0, 2); ?></div>
                <div class="stat-label">Avg Order Value</div>
            </div>
            <div class="stat-card purple">
                <span class="stat-icon">🏆</span>
                <div class="stat-number">KSh <?php echo number_format($sales_summary['max_order_value'] ?? 0, 2); ?></div>
                <div class="stat-label">Max Order Value</div>
            </div>
        </div>

        <h2 class="section-title">📋 Sales Details</h2>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Date</th><th>Orders</th><th>Revenue</th><th>Avg Order Value</th></tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($sales_result) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($sales_result)): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                <td><?php echo $row['orders']; ?></td>
                                <td>KSh <?php echo number_format($row['revenue'], 2); ?></td>
                                <td>KSh <?php echo number_format($row['average_order_value'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="no-data">No sales data available for this period.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- ============================================
       STAFF PERFORMANCE REPORT (Premium+)
       ============================================ -->
    <?php if ($report_type == 'staff' && ($plan_key == 'premium' || $plan_key == 'enterprise')): ?>
        <div class="stats-grid">
            <div class="stat-card blue">
                <span class="stat-icon">👥</span>
                <div class="stat-number"><?php echo mysqli_num_rows($staff_performance_result); ?></div>
                <div class="stat-label">Active Staff</div>
            </div>
            <div class="stat-card green">
                <span class="stat-icon">⭐</span>
                <div class="stat-number">
                    <?php 
                    $top_staff = mysqli_fetch_assoc($staff_performance_result);
                    mysqli_data_seek($staff_performance_result, 0);
                    echo $top_staff ? htmlspecialchars($top_staff['full_name']) : 'N/A';
                    ?>
                </div>
                <div class="stat-label">Top Performer</div>
            </div>
        </div>

        <h2 class="section-title">👥 Staff Performance Details</h2>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Total</th>
                        <th>Completed</th>
                        <th>Revenue</th>
                        <th>Score</th>
                        <th>Rating</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($staff_performance_result) > 0): 
                        mysqli_data_seek($staff_performance_result, 0);
                        while($staff = mysqli_fetch_assoc($staff_performance_result)):
                            $rating = $staff['performance_score'] >= 80 ? 'high' : ($staff['performance_score'] >= 50 ? 'medium' : 'low');
                            $rating_label = $rating == 'high' ? '⭐ Excellent' : ($rating == 'medium' ? '⭐ Good' : '⭐ Needs Improvement');
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($staff['full_name']); ?></td>
                            <td><?php echo $staff['total_appointments'] ?? 0; ?></td>
                            <td><?php echo $staff['completed'] ?? 0; ?></td>
                            <td>KSh <?php echo number_format($staff['total_revenue'] ?? 0, 2); ?></td>
                            <td><?php echo $staff['performance_score'] ?? 0; ?></td>
                            <td><span class="performance-badge <?php echo $rating; ?>"><?php echo $rating_label; ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="no-data">No staff performance data available.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- ============================================
       SERVICE POPULARITY REPORT (All plans)
       ============================================ -->
    <?php if ($report_type == 'services'): ?>
        <div class="stats-grid">
            <div class="stat-card blue">
                <span class="stat-icon">💇</span>
                <div class="stat-number"><?php echo mysqli_num_rows($service_popularity_result); ?></div>
                <div class="stat-label">Active Services</div>
            </div>
            <div class="stat-card green">
                <span class="stat-icon">🏆</span>
                <div class="stat-number">
                    <?php 
                    $top_service = mysqli_fetch_assoc($service_popularity_result);
                    mysqli_data_seek($service_popularity_result, 0);
                    echo $top_service ? htmlspecialchars($top_service['service_name']) : 'N/A';
                    ?>
                </div>
                <div class="stat-label">Most Popular</div>
            </div>
        </div>

        <h2 class="section-title">💇 Service Popularity Details</h2>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Bookings</th>
                        <th>Revenue</th>
                        <th>Avg Price</th>
                        <th>% of Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($service_popularity_result) > 0): 
                        mysqli_data_seek($service_popularity_result, 0);
                        while($service = mysqli_fetch_assoc($service_popularity_result)):
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                            <td><?php echo $service['bookings'] ?? 0; ?></td>
                            <td>KSh <?php echo number_format($service['total_revenue'] ?? 0, 2); ?></td>
                            <td>KSh <?php echo number_format($service['avg_price'] ?? 0, 2); ?></td>
                            <td><?php echo $service['percentage'] ?? 0; ?>%</td>
                        </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="no-data">No service data available.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- ============================================
       CUSTOMER INSIGHTS (All plans)
       ============================================ -->
    <?php if ($report_type == 'customers'): ?>
        <div class="stats-grid">
            <div class="stat-card blue">
                <span class="stat-icon">👤</span>
                <div class="stat-number"><?php echo $customer_insights['total_customers'] ?? 0; ?></div>
                <div class="stat-label">Total Customers</div>
            </div>
            <div class="stat-card green">
                <span class="stat-icon">🆕</span>
                <div class="stat-number"><?php echo $customer_insights['new_customers'] ?? 0; ?></div>
                <div class="stat-label">New (30 days)</div>
            </div>
            <div class="stat-card orange">
                <span class="stat-icon">✅</span>
                <div class="stat-number"><?php echo $customer_insights['active_customers'] ?? 0; ?></div>
                <div class="stat-label">Active Customers</div>
            </div>
            <div class="stat-card purple">
                <span class="stat-icon">📊</span>
                <div class="stat-number"><?php echo round($customer_insights['avg_appointments_per_customer'] ?? 0, 1); ?></div>
                <div class="stat-label">Avg Appointments/Customer</div>
            </div>
        </div>

        <h2 class="section-title">👤 Customer Insights Details</h2>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Metric</th><th>Value</th></tr>
                </thead>
                <tbody>
                    <tr><td>Total Customers</td><td><?php echo $customer_insights['total_customers'] ?? 0; ?></td></tr>
                    <tr><td>New Customers (Last 30 days)</td><td><?php echo $customer_insights['new_customers'] ?? 0; ?></td></tr>
                    <tr><td>Active Customers</td><td><?php echo $customer_insights['active_customers'] ?? 0; ?></td></tr>
                    <tr><td>Total Appointments</td><td><?php echo $customer_insights['total_appointments'] ?? 0; ?></td></tr>
                    <tr><td>Avg Appointments per Customer</td><td><?php echo round($customer_insights['avg_appointments_per_customer'] ?? 0, 1); ?></td></tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- ============================================
       INVENTORY REPORT (Enterprise only)
       ============================================ -->
    <?php if ($report_type == 'inventory' && $plan_key === 'enterprise'): ?>
        <div class="stats-grid">
            <div class="stat-card blue">
                <span class="stat-icon">📦</span>
                <div class="stat-number"><?php echo $inventory_report['total_products'] ?? 0; ?></div>
                <div class="stat-label">Total Products</div>
            </div>
            <div class="stat-card green">
                <span class="stat-icon">✅</span>
                <div class="stat-number"><?php echo $inventory_report['full_stock'] ?? 0; ?></div>
                <div class="stat-label">Full Stock</div>
            </div>
            <div class="stat-card orange">
                <span class="stat-icon">⚠️</span>
                <div class="stat-number"><?php echo $inventory_report['low_stock'] ?? 0; ?></div>
                <div class="stat-label">Low Stock</div>
            </div>
            <div class="stat-card red">
                <span class="stat-icon">🚫</span>
                <div class="stat-number"><?php echo $inventory_report['out_of_stock'] ?? 0; ?></div>
                <div class="stat-label">Out of Stock</div>
            </div>
            <div class="stat-card purple">
                <span class="stat-icon">💰</span>
                <div class="stat-number">KSh <?php echo number_format($inventory_report['inventory_value'] ?? 0, 2); ?></div>
                <div class="stat-label">Inventory Value</div>
            </div>
        </div>

        <h2 class="section-title">📦 Inventory Details</h2>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Metric</th><th>Value</th></tr>
                </thead>
                <tbody>
                    <tr><td>Total Products</td><td><?php echo $inventory_report['total_products'] ?? 0; ?></td></tr>
                    <tr><td>Total Units in Stock</td><td><?php echo $inventory_report['total_stock'] ?? 0; ?></td></tr>
                    <tr><td>Inventory Value</td><td>KSh <?php echo number_format($inventory_report['inventory_value'] ?? 0, 2); ?></td></tr>
                    <tr><td>Full Stock</td><td><?php echo $inventory_report['full_stock'] ?? 0; ?></td></tr>
                    <tr><td>Low Stock</td><td><?php echo $inventory_report['low_stock'] ?? 0; ?></td></tr>
                    <tr><td>Out of Stock</td><td><?php echo $inventory_report['out_of_stock'] ?? 0; ?></td></tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- ============================================
       ADVANCED ANALYTICS (Enterprise only)
       ============================================ -->
    <?php if ($report_type == 'analytics' && $plan_key === 'enterprise' && $advanced_analytics): ?>
        <h2 class="section-title">📈 Advanced Analytics</h2>
        <div class="advanced-analytics-grid">
            <div class="analytics-card">
                <div class="analytics-number"><?php echo $advanced_analytics['total_customers'] ?? 0; ?></div>
                <div class="analytics-label">Total Customers</div>
            </div>
            <div class="analytics-card">
                <div class="analytics-number"><?php echo $advanced_analytics['new_customers'] ?? 0; ?></div>
                <div class="analytics-label">New Customers (30 days)</div>
            </div>
            <div class="analytics-card">
                <div class="analytics-number"><?php echo $advanced_analytics['returning_customers'] ?? 0; ?></div>
                <div class="analytics-label">Returning Customers</div>
                <div class="analytics-sub"><?php echo $advanced_analytics['retention_rate'] ?? 0; ?>% retention rate</div>
            </div>
            <div class="analytics-card">
                <div class="analytics-number"><?php echo ($advanced_analytics['total_customers'] ?? 0) - ($advanced_analytics['new_customers'] ?? 0); ?></div>
                <div class="analytics-label">Existing Customers</div>
            </div>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Metric</th><th>Value</th></tr>
                </thead>
                <tbody>
                    <tr><td>Total Customers</td><td><?php echo $advanced_analytics['total_customers'] ?? 0; ?></td></tr>
                    <tr><td>New Customers (30 days)</td><td><?php echo $advanced_analytics['new_customers'] ?? 0; ?></td></tr>
                    <tr><td>Returning Customers</td><td><?php echo $advanced_analytics['returning_customers'] ?? 0; ?></td></tr>
                    <tr><td>Customer Retention Rate</td><td><?php echo $advanced_analytics['retention_rate'] ?? 0; ?>%</td></tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<script>
    function exportReport() {
        const tables = document.querySelectorAll('.table-wrapper table');
        if (tables.length === 0) {
            alert('No data to export.');
            return;
        }
        
        let csv = '';
        tables.forEach(function(table) {
            const rows = table.querySelectorAll('tr');
            rows.forEach(function(row) {
                const cells = row.querySelectorAll('th, td');
                const rowData = [];
                cells.forEach(function(cell) {
                    rowData.push('"' + cell.textContent.trim() + '"');
                });
                csv += rowData.join(',') + '\n';
            });
            csv += '\n';
        });
        
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'report_' + '<?php echo $report_type; ?>_' + '<?php echo date('Y-m-d'); ?>.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
</script>

<?php include '../includes/footer.php'; ?>