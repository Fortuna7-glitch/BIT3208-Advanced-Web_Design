<?php
/**
 * Salon Pro — Admin: Upgrade Subscription
 * Luxury gold/black theme
 * Admin can view plans and upgrade their subscription
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
// GET CURRENT SALON DATA
// ============================================
$salon_query = "SELECT * FROM salons WHERE id = $salon_id";
$salon_result = mysqli_query($conn, $salon_query);
$salon = mysqli_fetch_assoc($salon_result);

$current_plan = $salon['subscription_plan'] ?? 'basic';
$current_plan_name = ucfirst($current_plan);

// ============================================
// PLAN DEFINITIONS
// ============================================
$plans = [
    'basic' => [
        'name' => 'Basic',
        'price' => 5000,
        'color' => '#17a2b8',
        'icon' => '🌱',
        'badge' => 'Starter',
        'features' => [
            'Up to 5 Staff Members',
            'Up to 10 Services',
            'Basic Reports',
            'Staff Management',
            'Email Support',
            '1 Salon Location'
        ],
        'popular' => false,
        'recommended' => false
    ],
    'premium' => [
        'name' => 'Premium',
        'price' => 10000,
        'color' => '#d4af37',
        'icon' => '⭐',
        'badge' => 'Most Popular',
        'features' => [
            'Up to 15 Staff Members',
            'Up to 30 Services',
            'Full Reports & Analytics',
            'Staff Management',
            'Payroll Management',
            'Permissions Management',
            'Product Management',
            'Order Management',
            'Priority Support',
            '2 Salon Locations'
        ],
        'popular' => true,
        'recommended' => true
    ],
    'enterprise' => [
        'name' => 'Enterprise',
        'price' => 20000,
        'color' => '#28a745',
        'icon' => '👑',
        'badge' => 'Best Value',
        'features' => [
            'Unlimited Staff Members',
            'Unlimited Services',
            'Advanced Reports & Analytics',
            'Staff Management',
            'Payroll Management',
            'Permissions Management',
            'Product Management',
            'Order Management',
            'Advanced Inventory Reports',
            'Salon Settings',
            '24/7 Premium Support',
            'Multi-Branch Support',
            'Custom Integrations'
        ],
        'popular' => false,
        'recommended' => false
    ]
];

// ============================================
// CHECK IF CAN UPGRADE
// ============================================
$plan_keys = array_keys($plans);
$current_index = array_search($current_plan, $plan_keys);
$can_upgrade = ($current_index < count($plan_keys) - 1);

// ============================================
// HANDLE UPGRADE
// ============================================
$error = '';
$success = '';
$upgrade_plan = isset($_GET['plan']) ? $_GET['plan'] : '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upgrade'])) {
    $new_plan = mysqli_real_escape_string($conn, $_POST['new_plan']);
    
    // Validate plan exists and is higher than current
    $new_index = array_search($new_plan, $plan_keys);
    $current_index = array_search($current_plan, $plan_keys);
    
    if ($new_index === false || $new_index <= $current_index) {
        $error = "Invalid upgrade plan selected.";
    } else {
        $new_price = $plans[$new_plan]['price'];
        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Update salon subscription
            $update_query = "UPDATE salons SET 
                             subscription_plan = '$new_plan',
                             subscription_status = 'active',
                             subscription_expiry = DATE_ADD(NOW(), INTERVAL 1 MONTH)
                             WHERE id = $salon_id";
            mysqli_query($conn, $update_query);
            
            // Log upgrade
            $log_query = "INSERT INTO subscription_upgrade_logs (salon_id, old_plan, new_plan, amount_paid, payment_method) 
                          VALUES ($salon_id, '$current_plan', '$new_plan', $new_price, '$payment_method')";
            mysqli_query($conn, $log_query);
            
            // Update plan features
            $plan_features = json_encode(getPlanFeatures($new_plan));
            $features_update = "UPDATE salons SET plan_features = '$plan_features' WHERE id = $salon_id";
            mysqli_query($conn, $features_update);
            
            mysqli_commit($conn);
            
            logAudit('plan_upgraded', 'subscription', "Upgraded from $current_plan to $new_plan", $admin_id);
            
            // Send notification to owner
            $owner_query = "SELECT u.email, u.full_name, u.phone FROM users u WHERE u.id = $admin_id";
            $owner_result = mysqli_query($conn, $owner_query);
            $owner = mysqli_fetch_assoc($owner_result);
            
            if ($owner) {
                $subject = "🎉 Subscription Upgraded to " . ucfirst($new_plan) . " Plan!";
                $message = "Dear {$owner['full_name']},<br><br>";
                $message .= "Your salon subscription has been successfully upgraded to the <strong>" . ucfirst($new_plan) . " Plan</strong>.<br><br>";
                $message .= "New features available:<br>";
                foreach ($plans[$new_plan]['features'] as $feature) {
                    $message .= "✅ $feature<br>";
                }
                $message .= "<br>Thank you for choosing Salon Pro! ✨";
                
                sendEmail($owner['email'], $subject, $message);
                sendSMS($owner['phone'], "Salon Pro: Your subscription has been upgraded to " . ucfirst($new_plan) . " Plan. Enjoy new features!");
            }
            
            $success = "Successfully upgraded to " . ucfirst($new_plan) . " Plan!";
            // Refresh salon data
            $salon_query = "SELECT * FROM salons WHERE id = $salon_id";
            $salon_result = mysqli_query($conn, $salon_query);
            $salon = mysqli_fetch_assoc($salon_result);
            $current_plan = $salon['subscription_plan'] ?? 'basic';
            $current_plan_name = ucfirst($current_plan);
            $current_index = array_search($current_plan, $plan_keys);
            $can_upgrade = ($current_index < count($plan_keys) - 1);
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Upgrade failed: " . $e->getMessage();
        }
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

    /* Plans Grid */
    .plans-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 2rem;
        margin-top: 1rem;
    }

    .plan-card {
        background: #0e0e0e;
        border-radius: 16px;
        padding: 2rem 1.5rem;
        border: 2px solid rgba(212, 175, 55, 0.1);
        transition: all 0.4s ease;
        position: relative;
        display: flex;
        flex-direction: column;
        text-align: center;
    }

    .plan-card:hover {
        transform: translateY(-8px);
        border-color: rgba(212, 175, 55, 0.3);
        box-shadow: 0 15px 40px rgba(212, 175, 55, 0.1);
    }

    .plan-card.current {
        border-color: #d4af37;
        background: rgba(212, 175, 55, 0.05);
    }

    .plan-card.current::before {
        content: "✓ Current Plan";
        position: absolute;
        top: -10px;
        right: 20px;
        background: #d4af37;
        color: #050505;
        padding: 2px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
    }

    .plan-card .plan-icon {
        font-size: 2.5rem;
        margin-bottom: 0.5rem;
    }

    .plan-card .plan-name {
        font-size: 1.3rem;
        font-weight: 700;
        color: #f5f0e1;
        margin-bottom: 0.3rem;
    }

    .plan-card .plan-price {
        font-size: 1.8rem;
        font-weight: 700;
        color: #d4af37;
        margin-bottom: 0.2rem;
    }

    .plan-card .plan-price small {
        font-size: 0.8rem;
        color: #7a7568;
        font-weight: 400;
    }

    .plan-card .plan-badge {
        display: inline-block;
        padding: 2px 14px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        margin-bottom: 1rem;
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid #d4af37;
    }

    .plan-card .plan-badge.popular {
        background: #d4af37;
        color: #050505;
        border-color: #d4af37;
    }

    .plan-card .plan-features {
        list-style: none;
        padding: 0;
        margin: 1rem 0 0 0;
        text-align: left;
        flex: 1;
    }

    .plan-card .plan-features li {
        padding: 0.4rem 0;
        color: #b8b2a0;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.6rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    }

    .plan-card .plan-features li:last-child {
        border-bottom: none;
    }

    .plan-card .plan-features li .check {
        color: #28a745;
        font-weight: 700;
        flex-shrink: 0;
    }

    .plan-card .plan-features li .cross {
        color: #dc3545;
        font-weight: 700;
        flex-shrink: 0;
    }

    .plan-card .plan-features li .feature-icon {
        flex-shrink: 0;
    }

    .plan-card .plan-features li .feature-text {
        flex: 1;
    }

    .plan-card .plan-actions {
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid rgba(212, 175, 55, 0.1);
    }

    .btn-upgrade {
        display: inline-block;
        padding: 10px 30px;
        border-radius: 25px;
        font-size: 0.9rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
        width: 100%;
        text-align: center;
    }

    .btn-upgrade:hover {
        transform: translateY(-2px);
    }

    .btn-upgrade.primary {
        background: #d4af37;
        color: #050505;
    }

    .btn-upgrade.primary:hover {
        background: #f0d878;
        box-shadow: 0 8px 25px rgba(212, 175, 55, 0.3);
    }

    .btn-upgrade.disabled {
        background: #2a2a2a;
        color: #555;
        cursor: not-allowed;
    }

    .btn-upgrade.disabled:hover {
        transform: none;
        box-shadow: none;
    }

    .btn-upgrade.current-btn {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border: 1px solid #28a745;
        cursor: default;
    }

    .btn-upgrade.current-btn:hover {
        transform: none;
    }

    /* Upgrade Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.85);
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }

    .modal.active {
        display: flex;
    }

    .modal-content {
        background: #1a1a1a;
        padding: 2rem;
        border-radius: 16px;
        max-width: 450px;
        width: 90%;
        border: 1px solid rgba(212, 175, 55, 0.3);
    }

    .modal-content h3 {
        color: #f0d878;
        font-size: 1.2rem;
        margin-bottom: 0.5rem;
        text-align: center;
    }

    .modal-content .modal-plan {
        text-align: center;
        color: #d4af37;
        font-size: 1.8rem;
        font-weight: 700;
        margin: 0.5rem 0;
    }

    .modal-content .modal-plan small {
        font-size: 0.8rem;
        color: #7a7568;
    }

    .modal-content .modal-features {
        list-style: none;
        padding: 0;
        margin: 1rem 0;
    }

    .modal-content .modal-features li {
        padding: 0.3rem 0;
        color: #b8b2a0;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .modal-content .modal-features li .check {
        color: #28a745;
    }

    .modal-content .form-group {
        margin-top: 1rem;
    }

    .modal-content .form-group label {
        display: block;
        color: #d4af37;
        font-size: 0.85rem;
        margin-bottom: 0.3rem;
    }

    .modal-content .form-group select,
    .modal-content .form-group input {
        width: 100%;
        padding: 10px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 8px;
        color: white;
        font-size: 0.9rem;
    }

    .modal-content .form-group select:focus,
    .modal-content .form-group input:focus {
        outline: none;
        border-color: #d4af37;
    }

    .modal-buttons {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
    }

    .btn-cancel-modal {
        flex: 1;
        padding: 10px;
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
    }

    .btn-cancel-modal:hover {
        background: #c82333;
    }

    .btn-confirm-modal {
        flex: 1;
        padding: 10px;
        background: #d4af37;
        color: #050505;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
    }

    .btn-confirm-modal:hover {
        background: #f0d878;
    }

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

    /* Responsive */
    @media (max-width: 1024px) {
        .plans-grid {
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
        .plans-grid { grid-template-columns: 1fr; max-width: 400px; margin: 0 auto; }
        .plan-card { padding: 1.5rem; }
        .modal-content { padding: 1.5rem; }
        .top-bar-right .icon-btn .badge { width: 14px; height: 14px; font-size: 0.4rem; top: -2px; right: -2px; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0 0.8rem 0.8rem; }
        .quick-links .qlink { font-size: 0.65rem; padding: 0.15rem 0.3rem; }
        .welcome-left h1 { font-size: 1.1rem; }
        .date-badge { font-size: 0.75rem; padding: 0.3rem 0.6rem; }
        .top-bar-right .icon-btn { font-size: 0.8rem; }
        .plan-card { padding: 1.2rem; }
        .plan-card .plan-price { font-size: 1.5rem; }
        .modal-buttons { flex-direction: column; }
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
                    <span class="sub">Upgrade Plan</span>
                </div>
            </div>

            <div class="top-bar-center">
                <div class="quick-links">
                    <?php if (hasPlanFeature($salon_id, 'products')): ?>
                        <a href="../staff/book_for_customer.php" class="qlink"><i class="ti ti-calendar-plus"></i> Book</a>
                        <span class="link-sep">|</span>
                        <a href="services.php" class="qlink"><i class="ti ti-scissors"></i> Services</a>
                        <span class="link-sep">|</span>
                    <?php endif; ?>
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
                    <?php if (hasPlanFeature($salon_id, 'products')): ?>
                        <a href="products.php" class="qlink"><i class="ti ti-box"></i> Products</a>
                        <span class="link-sep">|</span>
                        <a href="product_orders.php" class="qlink"><i class="ti ti-shopping-cart"></i> Orders</a>
                        <span class="link-sep">|</span>
                    <?php endif; ?>
                    <?php if (hasPlanFeature($salon_id, 'reports')): ?>
                        <a href="reports.php" class="qlink"><i class="ti ti-chart-line"></i> Reports</a>
                        <span class="link-sep">|</span>
                    <?php endif; ?>
                    <?php if (hasPlanFeature($salon_id, 'settings')): ?>
                        <a href="settings.php" class="qlink"><i class="ti ti-settings"></i> Settings</a>
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
            <h1>✨ Upgrade Subscription</h1>
            <p class="subtitle">Choose the plan that fits your salon's needs</p>
            <span class="plan-badge <?php echo $current_plan; ?>">
                <?php echo $current_plan_name; ?> Plan
            </span>
        </div>
        <div class="date-badge">
            <i class="ti ti-calendar"></i> <?php echo date('j F Y'); ?>
        </div>
    </div>

    <?php if($error): ?>
        <div class="alert alert-danger">❌ <?php echo $error; ?></div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="alert alert-success">✅ <?php echo $success; ?></div>
    <?php endif; ?>

    <?php if (!$can_upgrade): ?>
        <div style="text-align: center; padding: 2rem; background: #0e0e0e; border-radius: 12px; border: 1px solid rgba(212, 175, 55, 0.25);">
            <div style="font-size: 3rem; margin-bottom: 1rem;">👑</div>
            <h2 style="color: #f0d878;">You're on the Enterprise Plan!</h2>
            <p style="color: #b8b2a0;">You already have access to all features. No upgrades available.</p>
            <a href="dashboard.php" style="display: inline-block; margin-top: 1rem; padding: 10px 25px; background: #d4af37; color: #050505; border-radius: 25px; text-decoration: none; font-weight: 600;">← Back to Dashboard</a>
        </div>
    <?php else: ?>

    <div class="plans-grid">
        <?php foreach ($plans as $key => $plan):
            $is_current = ($key == $current_plan);
            $is_upgrade = array_search($key, $plan_keys) > $current_index;
            $is_locked = !$is_current && !$is_upgrade;
            $btn_class = 'btn-upgrade primary';
            $btn_text = 'Current Plan';
            $btn_disabled = false;
            
            if ($is_current) {
                $btn_class = 'btn-upgrade current-btn';
                $btn_text = '✓ Current Plan';
                $btn_disabled = true;
            } elseif ($is_upgrade) {
                $btn_class = 'btn-upgrade primary';
                $btn_text = '✨ Upgrade Now';
                $btn_disabled = false;
            } else {
                $btn_class = 'btn-upgrade disabled';
                $btn_text = '🔒 Unavailable';
                $btn_disabled = true;
            }
        ?>
            <div class="plan-card <?php echo $is_current ? 'current' : ''; ?>">
                <?php if ($plan['popular']): ?>
                    <span class="plan-badge popular">⭐ Most Popular</span>
                <?php endif; ?>

                <div class="plan-icon"><?php echo $plan['icon']; ?></div>
                <div class="plan-name"><?php echo $plan['name']; ?></div>
                <div class="plan-price">
                    KSh <?php echo number_format($plan['price'], 2); ?>
                    <small>/month</small>
                </div>

                <ul class="plan-features">
                    <?php foreach ($plan['features'] as $feature): ?>
                        <li>
                            <span class="check">✅</span>
                            <span class="feature-text"><?php echo $feature; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="plan-actions">
                    <?php if ($is_current): ?>
                        <button class="<?php echo $btn_class; ?>" disabled>✅ Current Plan</button>
                    <?php elseif ($is_upgrade): ?>
                        <button class="<?php echo $btn_class; ?>" onclick="openUpgradeModal('<?php echo $key; ?>', '<?php echo $plan['name']; ?>', <?php echo $plan['price']; ?>)">
                            ✨ Upgrade to <?php echo $plan['name']; ?>
                        </button>
                    <?php else: ?>
                        <button class="<?php echo $btn_class; ?>" disabled>🔒 Upgrade to <?php echo $plan['name']; ?></button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<!-- Upgrade Confirmation Modal -->
<div id="upgradeModal" class="modal">
    <div class="modal-content">
        <h3>✨ Confirm Upgrade</h3>
        <p style="color: #7a7568; text-align: center; font-size: 0.85rem;">You are about to upgrade your subscription</p>
        <div class="modal-plan">
            <span id="modalPlanName">Premium Plan</span>
            <small>KSh <span id="modalPlanPrice">10,000.00</span>/month</small>
        </div>

        <p style="color: #b8b2a0; font-size: 0.9rem; margin-top: 0.5rem; text-align: center;">New features included:</p>
        <ul class="modal-features" id="modalPlanFeatures">
            <!-- Dynamically populated -->
        </ul>

        <form method="POST" id="upgradeForm">
            <input type="hidden" name="new_plan" id="modalNewPlan">
            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method" required>
                    <option value="mpesa">📱 M-PESA</option>
                    <option value="card">💳 Credit/Debit Card</option>
                    <option value="bank">🏦 Bank Transfer</option>
                </select>
            </div>

            <div class="modal-buttons">
                <button type="button" class="btn-cancel-modal" onclick="closeUpgradeModal()">Cancel</button>
                <button type="submit" name="upgrade" class="btn-confirm-modal">✅ Confirm Upgrade</button>
            </div>
        </form>
    </div>
</div>

<script>
    const plansData = <?php echo json_encode($plans); ?>;

    function openUpgradeModal(planKey, planName, planPrice) {
        const modal = document.getElementById('upgradeModal');
        document.getElementById('modalPlanName').textContent = planName + ' Plan';
        document.getElementById('modalPlanPrice').textContent = planPrice.toFixed(2);
        document.getElementById('modalNewPlan').value = planKey;

        // Populate features
        const featuresList = document.getElementById('modalPlanFeatures');
        featuresList.innerHTML = '';
        const features = plansData[planKey].features;
        features.forEach(function(feature) {
            const li = document.createElement('li');
            li.innerHTML = '<span class="check">✅</span> ' + feature;
            featuresList.appendChild(li);
        });

        modal.classList.add('active');
    }

    function closeUpgradeModal() {
        document.getElementById('upgradeModal').classList.remove('active');
    }

    // Close modal on outside click
    window.onclick = function(event) {
        const modal = document.getElementById('upgradeModal');
        if (event.target === modal) {
            closeUpgradeModal();
        }
    }
</script>

<?php include '../includes/footer.php'; ?>