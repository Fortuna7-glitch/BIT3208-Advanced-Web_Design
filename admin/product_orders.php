<?php
/**
 * Salon Pro — Admin: Product Orders Management
 * Luxury gold/black theme
 * Admin can manage all product orders for their salon
 * Integrates with products.php module
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

$error = '';
$success = '';
$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

// ============================================
// HANDLE ACTIONS
// ============================================

// Update Order Status
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    
    // Verify order belongs to this salon
    $check_query = "SELECT o.id, o.status, u.full_name as customer_name 
                    FROM orders o 
                    JOIN users u ON o.user_id = u.id 
                    WHERE o.id = $order_id AND o.salon_id = $salon_id";
    $check_result = mysqli_query($conn, $check_query);
    
    if ($row = mysqli_fetch_assoc($check_result)) {
        $old_status = $row['status'];
        
        // Update order status
        $update_query = "UPDATE orders SET status = '$new_status' WHERE id = $order_id";
        if (mysqli_query($conn, $update_query)) {
            logAudit('order_status_updated', 'orders', "Updated order #$order_id from $old_status to $new_status", $admin_id);
            
            // Send notification to customer
            $message = "Your order #$order_id status has been updated to: " . ucfirst(str_replace('_', ' ', $new_status));
            if (function_exists('sendNotification')) {
                sendNotification($row['user_id'], "Order Status Update", $message, 'email');
            }
            
            $success = "Order status updated successfully!";
        } else {
            $error = "Failed to update order status: " . mysqli_error($conn);
        }
    } else {
        $error = "Order not found in your salon.";
    }
}

// Delete Order (Only if pending or cancelled)
if (isset($_GET['delete'])) {
    $order_id = (int)$_GET['delete'];
    $check_query = "SELECT o.id, o.status FROM orders o 
                    WHERE o.id = $order_id AND o.salon_id = $salon_id 
                    AND o.status IN ('pending', 'cancelled')";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Delete order items first
        $delete_items = "DELETE FROM order_items WHERE order_id = $order_id";
        mysqli_query($conn, $delete_items);
        
        // Delete order
        $delete_query = "DELETE FROM orders WHERE id = $order_id";
        if (mysqli_query($conn, $delete_query)) {
            logAudit('order_deleted', 'orders', "Deleted order #$order_id", $admin_id);
            $success = "Order deleted successfully!";
        } else {
            $error = "Failed to delete order: " . mysqli_error($conn);
        }
    } else {
        $error = "Order not found or cannot be deleted (only pending/cancelled orders can be deleted).";
    }
}

// ============================================
// GET ORDER DETAILS FOR VIEW
// ============================================
$view_order = null;
$view_items = [];
if ($view_id > 0) {
    $order_query = "SELECT o.*, u.full_name as customer_name, u.email, u.phone 
                    FROM orders o 
                    JOIN users u ON o.user_id = u.id 
                    WHERE o.id = $view_id AND o.salon_id = $salon_id";
    $order_result = mysqli_query($conn, $order_query);
    $view_order = mysqli_fetch_assoc($order_result);
    
    if ($view_order) {
        $items_query = "SELECT oi.*, p.name as product_name, p.price as product_price 
                        FROM order_items oi 
                        JOIN products p ON oi.product_id = p.id 
                        WHERE oi.order_id = $view_id";
        $items_result = mysqli_query($conn, $items_query);
        while ($item = mysqli_fetch_assoc($items_result)) {
            $view_items[] = $item;
        }
    }
}

// ============================================
// SEARCH/FILTER
// ============================================
$search = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// ============================================
// GET ORDERS
// ============================================
$query = "SELECT o.*, u.full_name as customer_name 
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          WHERE o.salon_id = $salon_id";
if ($search) {
    $query .= " AND (u.full_name LIKE '%$search%' OR o.id LIKE '%$search%' OR o.address LIKE '%$search%')";
}
if ($status_filter) {
    $query .= " AND o.status = '$status_filter'";
}
if ($date_from) {
    $query .= " AND DATE(o.order_date) >= '$date_from'";
}
if ($date_to) {
    $query .= " AND DATE(o.order_date) <= '$date_to'";
}
$query .= " ORDER BY o.order_date DESC";
$orders_result = mysqli_query($conn, $query);

// Get stats
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
    SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped,
    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(total_amount) as total_revenue
    FROM orders WHERE salon_id = $salon_id";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get unread notification count
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

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
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
    .stat-card.red { border-left-color: #dc3545; }
    .stat-card.red .stat-number { color: #dc3545; }
    .stat-card.blue { border-left-color: #17a2b8; }
    .stat-card.blue .stat-number { color: #17a2b8; }
    .stat-card.purple { border-left-color: #6f42c1; }
    .stat-card.purple .stat-number { color: #6f42c1; }

    .filter-bar {
        display: flex;
        gap: 0.8rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        align-items: center;
        background: #0e0e0e;
        padding: 0.8rem 1.2rem;
        border-radius: 12px;
        border: 1px solid rgba(212, 175, 55, 0.25);
    }

    .filter-bar input,
    .filter-bar select {
        padding: 8px 14px;
        background: #1a1a1a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        color: #f5f0e1;
        font-size: 0.85rem;
        min-width: 130px;
    }

    .filter-bar input:focus,
    .filter-bar select:focus {
        outline: none;
        border-color: #d4af37;
    }

    .filter-bar .filter-btn {
        padding: 8px 20px;
        background: #d4af37;
        border: none;
        border-radius: 25px;
        color: #050505;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
        white-space: nowrap;
    }

    .filter-bar .filter-btn:hover {
        background: #f0d878;
    }

    .filter-bar .clear-btn {
        padding: 8px 20px;
        background: #1a1a1a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        color: #7a7568;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
        text-decoration: none;
        white-space: nowrap;
    }

    .filter-bar .clear-btn:hover {
        background: #2a2a2a;
        color: #f5f0e1;
    }

    .table-wrapper {
        overflow-x: auto;
        background: #0e0e0e;
        border-radius: 12px;
        padding: 0;
        border: 1px solid rgba(212, 175, 55, 0.25);
        -webkit-overflow-scrolling: touch;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
        min-width: 850px;
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

    .status-badge {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
    }

    .status-badge.pending {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid #d4af37;
    }

    .status-badge.processing {
        background: rgba(23, 162, 184, 0.15);
        color: #17a2b8;
        border: 1px solid #17a2b8;
    }

    .status-badge.shipped {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border: 1px solid #28a745;
    }

    .status-badge.delivered {
        background: rgba(40, 167, 69, 0.2);
        color: #28a745;
        border: 1px solid #28a745;
    }

    .status-badge.cancelled {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
        border: 1px solid #dc3545;
    }

    .action-cell {
        display: flex;
        gap: 0.3rem;
        flex-wrap: wrap;
    }

    .action-cell .btn {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
    }

    .btn-view {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid rgba(212, 175, 55, 0.3);
    }

    .btn-view:hover {
        background: #d4af37;
        color: #050505;
    }

    .btn-delete {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
        border: 1px solid #dc3545;
    }

    .btn-delete:hover {
        background: #dc3545;
        color: white;
    }

    .btn-update {
        background: rgba(23, 162, 184, 0.15);
        color: #17a2b8;
        border: 1px solid #17a2b8;
    }

    .btn-update:hover {
        background: #17a2b8;
        color: white;
    }

    /* Order Detail View */
    .order-detail-container {
        display: none;
        background: #0e0e0e;
        border: 1px solid rgba(212, 175, 55, 0.25);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .order-detail-container.active {
        display: block;
    }

    .order-detail-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(212, 175, 55, 0.1);
    }

    .order-detail-header .order-info h2 {
        color: #f0d878;
        font-size: 1.2rem;
    }

    .order-detail-header .order-info p {
        color: #b8b2a0;
        font-size: 0.85rem;
    }

    .order-detail-header .order-status-form {
        display: flex;
        gap: 0.8rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .order-detail-header .order-status-form select {
        padding: 8px 12px;
        background: #1a1a1a;
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 8px;
        color: white;
    }

    .order-detail-header .order-status-form .btn-update {
        padding: 8px 20px;
        border-radius: 25px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        background: #d4af37;
        color: #050505;
        transition: all 0.3s;
    }

    .order-detail-header .order-status-form .btn-update:hover {
        background: #f0d878;
        transform: translateY(-2px);
    }

    .order-detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
    }

    .order-detail-grid .detail-section {
        background: #1a1a1a;
        border-radius: 8px;
        padding: 1rem;
        border: 1px solid rgba(212, 175, 55, 0.1);
    }

    .order-detail-grid .detail-section h4 {
        color: #d4af37;
        font-size: 0.85rem;
        margin-bottom: 0.8rem;
        border-bottom: 1px solid rgba(212, 175, 55, 0.1);
        padding-bottom: 0.5rem;
    }

    .order-detail-grid .detail-section .row {
        display: flex;
        justify-content: space-between;
        padding: 0.3rem 0;
        font-size: 0.85rem;
        color: #b8b2a0;
    }

    .order-detail-grid .detail-section .row .label {
        color: #7a7568;
    }

    .order-detail-grid .detail-section .row .value {
        color: #f5f0e1;
        font-weight: 500;
    }

    .order-items-table {
        margin-top: 1rem;
    }

    .order-items-table table {
        min-width: 100%;
        font-size: 0.8rem;
    }

    .order-items-table table th {
        font-size: 0.65rem;
        color: #7a7568;
    }

    .order-items-table table td {
        padding: 6px 8px;
    }

    .order-total-row {
        text-align: right;
        padding-top: 0.8rem;
        margin-top: 0.8rem;
        border-top: 2px solid rgba(212, 175, 55, 0.2);
        font-size: 1.1rem;
    }

    .order-total-row .total-label {
        color: #b8b2a0;
    }

    .order-total-row .total-amount {
        color: #d4af37;
        font-weight: bold;
        font-size: 1.3rem;
    }

    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 1rem;
    }

    .alert-success {
        background: rgba(40, 167, 69, 0.2);
        border: 1px solid #28a745;
        color: #28a745;
    }

    .alert-danger {
        background: rgba(220, 53, 69, 0.2);
        border: 1px solid #dc3545;
        color: #dc3545;
    }

    .empty-state {
        text-align: center;
        padding: 3rem 0;
        color: #7a7568;
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

    /* Responsive */
    @media (max-width: 1024px) {
        table { min-width: 600px; }
        .order-detail-grid { grid-template-columns: 1fr; }
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
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar input,
        .filter-bar select { width: 100%; }
        table { min-width: 500px; font-size: 0.75rem; }
        th, td { padding: 6px; }
        .action-cell { flex-direction: column; }
        .action-cell .btn { width: 100%; text-align: center; }
        .order-detail-header { flex-direction: column; align-items: stretch; }
        .order-detail-header .order-status-form { flex-direction: column; }
        .order-detail-header .order-status-form select { width: 100%; }
        .order-detail-header .order-status-form .btn-update { width: 100%; text-align: center; }
        .order-detail-grid { grid-template-columns: 1fr; }
        .order-items-table table { font-size: 0.7rem; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0 0.8rem 0.8rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .quick-links .qlink { font-size: 0.65rem; padding: 0.15rem 0.3rem; }
        .welcome-left h1 { font-size: 1.1rem; }
        .date-badge { font-size: 0.75rem; padding: 0.3rem 0.6rem; }
        .top-bar-right .icon-btn { font-size: 0.8rem; }
        .top-bar-right .icon-btn .badge { width: 14px; height: 14px; font-size: 0.4rem; top: -2px; right: -2px; }
        table { min-width: 400px; font-size: 0.7rem; }
        th, td { padding: 4px; }
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
                    <span class="sub">Product Orders</span>
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
                    <a href="payroll.php" class="qlink"><i class="ti ti-coin"></i> Payroll</a>
                    <span class="link-sep">|</span>
                    <a href="permissions.php" class="qlink"><i class="ti ti-key"></i> Permissions</a>
                    <span class="link-sep">|</span>
                    <a href="products.php" class="qlink"><i class="ti ti-box"></i> Products</a>
                    <span class="link-sep">|</span>
                    <a href="product_orders.php" class="qlink active"><i class="ti ti-shopping-cart"></i> Orders</a>
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
            <h1>🛒 Product Orders</h1>
            <p class="subtitle">Manage all product orders for your salon</p>
        </div>
        <div class="date-badge">
            <i class="ti ti-calendar"></i> <?php echo date('j F Y'); ?>
        </div>
    </div>

    <?php if($success): ?>
        <div class="alert alert-success">✅ <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-danger">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-icon">📦</span>
            <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
            <div class="stat-label">Total Orders</div>
        </div>
        <div class="stat-card orange">
            <span class="stat-icon">⏳</span>
            <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card blue">
            <span class="stat-icon">🔄</span>
            <div class="stat-number"><?php echo $stats['processing'] ?? 0; ?></div>
            <div class="stat-label">Processing</div>
        </div>
        <div class="stat-card green">
            <span class="stat-icon">✅</span>
            <div class="stat-number"><?php echo $stats['delivered'] ?? 0; ?></div>
            <div class="stat-label">Delivered</div>
        </div>
        <div class="stat-card red">
            <span class="stat-icon">❌</span>
            <div class="stat-number"><?php echo $stats['cancelled'] ?? 0; ?></div>
            <div class="stat-label">Cancelled</div>
        </div>
        <div class="stat-card purple">
            <span class="stat-icon">💰</span>
            <div class="stat-number">KSh <?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
    </div>

    <div class="filter-bar">
        <form method="GET" style="display: flex; gap: 0.8rem; flex: 1; flex-wrap: wrap; align-items: center;">
            <input type="text" name="q" placeholder="🔍 Search orders..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="status">
                <option value="">All Status</option>
                <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                <option value="processing" <?php echo ($status_filter == 'processing') ? 'selected' : ''; ?>>Processing</option>
                <option value="shipped" <?php echo ($status_filter == 'shipped') ? 'selected' : ''; ?>>Shipped</option>
                <option value="delivered" <?php echo ($status_filter == 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                <option value="cancelled" <?php echo ($status_filter == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
            </select>
            <input type="date" name="date_from" value="<?php echo $date_from; ?>" placeholder="From">
            <input type="date" name="date_to" value="<?php echo $date_to; ?>" placeholder="To">
            <button type="submit" class="filter-btn">Filter</button>
            <a href="product_orders.php" class="clear-btn">Clear</a>
        </form>
    </div>

    <!-- Order Detail View -->
    <?php if ($view_order): ?>
    <div class="order-detail-container active">
        <div class="order-detail-header">
            <div class="order-info">
                <h2>Order #<?php echo $view_order['id']; ?></h2>
                <p>Placed by <strong><?php echo htmlspecialchars($view_order['customer_name']); ?></strong> on <?php echo date('F d, Y h:i A', strtotime($view_order['order_date'])); ?></p>
            </div>
            <form method="POST" class="order-status-form">
                <input type="hidden" name="order_id" value="<?php echo $view_order['id']; ?>">
                <select name="status">
                    <option value="pending" <?php echo ($view_order['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="processing" <?php echo ($view_order['status'] == 'processing') ? 'selected' : ''; ?>>Processing</option>
                    <option value="shipped" <?php echo ($view_order['status'] == 'shipped') ? 'selected' : ''; ?>>Shipped</option>
                    <option value="delivered" <?php echo ($view_order['status'] == 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                    <option value="cancelled" <?php echo ($view_order['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
                <button type="submit" name="update_status" class="btn-update">✅ Update Status</button>
                <a href="product_orders.php" class="btn btn-view">← Close</a>
            </form>
        </div>

        <div class="order-detail-grid">
            <div class="detail-section">
                <h4>👤 Customer Information</h4>
                <div class="row"><span class="label">Name:</span><span class="value"><?php echo htmlspecialchars($view_order['customer_name']); ?></span></div>
                <div class="row"><span class="label">Email:</span><span class="value"><?php echo htmlspecialchars($view_order['email']); ?></span></div>
                <div class="row"><span class="label">Phone:</span><span class="value"><?php echo htmlspecialchars($view_order['phone']); ?></span></div>
            </div>
            <div class="detail-section">
                <h4>📦 Order Information</h4>
                <div class="row"><span class="label">Order Date:</span><span class="value"><?php echo date('F d, Y h:i A', strtotime($view_order['order_date'])); ?></span></div>
                <div class="row"><span class="label">Status:</span><span class="value"><span class="status-badge <?php echo $view_order['status']; ?>"><?php echo ucfirst($view_order['status']); ?></span></span></div>
                <div class="row"><span class="label">Payment Method:</span><span class="value"><?php echo ucfirst($view_order['payment_method']); ?></span></div>
                <div class="row"><span class="label">Delivery Address:</span><span class="value"><?php echo htmlspecialchars($view_order['address']); ?></span></div>
            </div>
        </div>

        <div class="order-items-table">
            <h4 style="color: #d4af37; margin: 1rem 0 0.5rem 0;">🛒 Order Items</h4>
            <table>
                <thead>
                    <tr><th>Product</th><th>Price</th><th>Quantity</th><th>Subtotal</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($view_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td>KSh <?php echo number_format($item['price'], 2); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td>KSh <?php echo number_format($item['quantity'] * $item['price'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="order-total-row">
                <span class="total-label">Total:</span>
                <span class="total-amount">KSh <?php echo number_format($view_order['total_amount'], 2); ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($orders_result) > 0): ?>
                    <?php while($order = mysqli_fetch_assoc($orders_result)): ?>
                        <tr>
                            <td>#<?php echo $order['id']; ?></td>
                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                            <td>KSh <?php echo number_format($order['total_amount'], 2); ?></td>
                            <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                            <td>
                                <span class="status-badge <?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </td>
                            <td class="action-cell">
                                <a href="product_orders.php?view=<?php echo $order['id']; ?>" class="btn btn-view">👁️ View</a>
                                <?php if ($order['status'] == 'pending' || $order['status'] == 'cancelled'): ?>
                                    <a href="product_orders.php?delete=<?php echo $order['id']; ?>" class="btn btn-delete" onclick="return confirm('Delete order #<?php echo $order['id']; ?>?')">🗑️ Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px;">
                            <div class="empty-state">
                                <p>No product orders found.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<?php include '../includes/footer.php'; ?>