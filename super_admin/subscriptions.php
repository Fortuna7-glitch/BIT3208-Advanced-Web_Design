<?php
/**
 * Salon Pro — Super Admin: Subscriptions Management
 * Luxury gold/black theme
 * Fixed top bar: Breadcrumb | Quick Actions | Search
 * Plan prices read from salon_settings
 */

require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

$admin_name = $_SESSION['user_name'] ?? 'Super Admin';

$error = '';
$success = '';

// ============================================
// HANDLE SUBSCRIPTION ACTIONS
// ============================================

// Renew subscription
if (isset($_GET['action']) && $_GET['action'] == 'renew' && isset($_GET['id'])) {
    $salon_id = (int)$_GET['id'];
    $new_expiry = date('Y-m-d', strtotime('+1 month'));
    $update = "UPDATE salons SET subscription_expiry = '$new_expiry', subscription_status = 'active' WHERE id = $salon_id";
    if (mysqli_query($conn, $update)) {
        $success = "Subscription renewed successfully until " . date('M d, Y', strtotime($new_expiry));
    } else {
        $error = "Failed to renew subscription: " . mysqli_error($conn);
    }
}

// Suspend subscription
if (isset($_GET['action']) && $_GET['action'] == 'suspend' && isset($_GET['id'])) {
    $salon_id = (int)$_GET['id'];
    $update = "UPDATE salons SET subscription_status = 'suspended' WHERE id = $salon_id";
    if (mysqli_query($conn, $update)) {
        $success = "Subscription suspended successfully!";
    } else {
        $error = "Failed to suspend subscription: " . mysqli_error($conn);
    }
}

// Activate subscription - MODIFIED with notifications
if (isset($_GET['action']) && $_GET['action'] == 'activate' && isset($_GET['id'])) {
    $salon_id = (int)$_GET['id'];
    $new_expiry = date('Y-m-d', strtotime('+1 month'));
    $update = "UPDATE salons SET subscription_status = 'active', subscription_expiry = '$new_expiry' WHERE id = $salon_id";
    if (mysqli_query($conn, $update)) {
        // Get plan details
        $plan_query = "SELECT subscription_plan FROM salons WHERE id = $salon_id";
        $plan_result = mysqli_query($conn, $plan_query);
        $plan_data = mysqli_fetch_assoc($plan_result);
        $plan = $plan_data['subscription_plan'] ?? 'basic';
        $price = getPlanPrice($plan);
        
        // Send notification to owner
        sendSubscriptionConfirmation($salon_id, $plan, $price, $new_expiry);
        
        $success = "Subscription activated successfully until " . date('M d, Y', strtotime($new_expiry));
    } else {
        $error = "Failed to activate subscription: " . mysqli_error($conn);
    }
}

// Renew subscription - MODIFIED with notifications
if (isset($_GET['action']) && $_GET['action'] == 'renew' && isset($_GET['id'])) {
    $salon_id = (int)$_GET['id'];
    $new_expiry = date('Y-m-d', strtotime('+1 month'));
    $update = "UPDATE salons SET subscription_expiry = '$new_expiry', subscription_status = 'active' WHERE id = $salon_id";
    if (mysqli_query($conn, $update)) {
        // Get plan details for notification
        $plan_query = "SELECT subscription_plan FROM salons WHERE id = $salon_id";
        $plan_result = mysqli_query($conn, $plan_query);
        $plan_data = mysqli_fetch_assoc($plan_result);
        $plan = $plan_data['subscription_plan'] ?? 'basic';
        $price = getPlanPrice($plan);
        
        // Send notification to owner
        sendSubscriptionConfirmation($salon_id, $plan, $price, $new_expiry);
        
        $success = "Subscription renewed successfully until " . date('M d, Y', strtotime($new_expiry));
    } else {
        $error = "Failed to renew subscription: " . mysqli_error($conn);
    }
}

// Suspend subscription - MODIFIED with notifications
if (isset($_GET['action']) && $_GET['action'] == 'suspend' && isset($_GET['id'])) {
    $salon_id = (int)$_GET['id'];
    $update = "UPDATE salons SET subscription_status = 'suspended' WHERE id = $salon_id";
    if (mysqli_query($conn, $update)) {
        // Get salon details
        $salon_query = "SELECT s.*, u.full_name as owner_name, u.email as owner_email, u.phone as owner_phone 
                        FROM salons s 
                        JOIN users u ON s.owner_id = u.id 
                        WHERE s.id = $salon_id";
        $salon_result = mysqli_query($conn, $salon_query);
        $salon = mysqli_fetch_assoc($salon_result);
        
        if ($salon) {
            // Send notification to owner
            $subject = "⚠️ Your Subscription Has Been Suspended - {$salon['salon_name']}";
            $message = "Dear {$salon['owner_name']},\n\nYour subscription for {$salon['salon_name']} has been suspended. Please contact support to reactivate your account.\n\nSalon Pro Support";
            sendEmail($salon['owner_email'], $subject, $message);
            sendSMS($salon['owner_phone'], "SALON PRO: Your subscription for {$salon['salon_name']} has been suspended. Contact support to reactivate.");
            
            // Notify Super Admin
            notifySuperAdmin(
                'subscription_suspended',
                "{$salon['salon_name']} subscription suspended",
                "Owner: {$salon['owner_name']} | Plan: " . ucfirst($salon['subscription_plan'] ?? 'Basic'),
                "subscriptions.php?view=$salon_id"
            );
        }
        
        $success = "Subscription suspended successfully!";
    } else {
        $error = "Failed to suspend subscription: " . mysqli_error($conn);
    }
}
// ============================================
// GET PLAN PRICES FROM DATABASE
// ============================================
$plan_prices = getAllPlanPrices();

// ============================================
// GET SUBSCRIPTION DATA - FIXED QUERY
// ============================================
$subscriptions_query = "SELECT s.*, u.full_name as owner_name, u.email as owner_email 
                        FROM salons s 
                        LEFT JOIN users u ON s.owner_id = u.id 
                        ORDER BY s.subscription_expiry ASC";
$subscriptions_result = mysqli_query($conn, $subscriptions_query);

// Get stats
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN subscription_status = 'active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN subscription_status = 'expired' THEN 1 ELSE 0 END) as expired,
    SUM(CASE WHEN subscription_status = 'suspended' THEN 1 ELSE 0 END) as suspended
    FROM salons";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

include '../includes/header.php';
?>

<style>
    .main-content {
        padding: 0 2rem 2rem;
        background: #0a0a0a;
        min-height: 100vh;
        margin-top: 0.5rem;
    }

    /* ============================================
       STICKY HEADER - Same as Dashboard
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
        gap: 0.8rem;
        flex: 0 0 auto;
    }

    .top-bar-right .search-box {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: #0e0e0e;
        border: 1px solid rgba(212, 175, 55, 0.25);
        border-radius: 20px;
        padding: 0.35rem 1rem;
        color: #7a7568;
        font-size: 0.85rem;
        min-width: 180px;
        position: relative;
    }

    .top-bar-right .search-box input {
        background: none;
        border: none;
        outline: none;
        color: #f5f0e1;
        font-size: 0.85rem;
        flex: 1;
        width: 100px;
    }

    .top-bar-right .search-box input::placeholder {
        color: #7a7568;
    }

    .top-bar-right .search-box .search-icon {
        color: #d4af37;
        cursor: pointer;
    }

    .top-bar-right .icon-btn {
        position: relative;
        color: #f0d878;
        font-size: 1.2rem;
        cursor: pointer;
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
        align-items: flex-start;
        margin: 0.8rem 0 1.2rem 0;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .welcome-row h1 {
        font-size: 1.6rem;
        font-weight: 700;
        color: #f0d878;
        font-family: 'Playfair Display', serif;
    }
    .welcome-row .subtitle {
        font-size: 0.9rem;
        color: #7a7568;
        margin-top: 0.3rem;
    }
    .welcome-row .date-badge {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border: 1px solid rgba(212, 175, 55, 0.25);
        border-radius: 8px;
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
        color: #b8b2a0;
    }
    .welcome-row .date-badge i {
        color: #d4af37;
    }

    /* ============================================
       STATS GRID
       ============================================ */
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
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(212, 175, 55, 0.08);
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

    /* ============================================
       TABLE
       ============================================ */
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
        min-width: 800px;
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

    .status-badge.active {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border: 1px solid #28a745;
    }

    .status-badge.expired {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
        border: 1px solid #dc3545;
    }

    .status-badge.suspended {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid #d4af37;
    }

    .plan-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
    }

    .plan-badge.basic { background: rgba(23, 162, 184, 0.15); color: #17a2b8; border: 1px solid #17a2b8; }
    .plan-badge.premium { background: rgba(212, 175, 55, 0.15); color: #d4af37; border: 1px solid #d4af37; }
    .plan-badge.enterprise { background: rgba(40, 167, 69, 0.15); color: #28a745; border: 1px solid #28a745; }

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

    .btn-renew {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid #d4af37;
    }

    .btn-renew:hover {
        background: #d4af37;
        color: #050505;
    }

    .btn-suspend {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid #d4af37;
    }

    .btn-suspend:hover {
        background: #d4af37;
        color: #050505;
    }

    .btn-activate {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border: 1px solid #28a745;
    }

    .btn-activate:hover {
        background: #28a745;
        color: white;
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
        .top-bar-right .search-box { flex: 1; min-width: 120px; }
        .quick-links .qlink { font-size: 0.7rem; padding: 0.2rem 0.4rem; }
        .welcome-row { flex-direction: column; }
        .welcome-row h1 { font-size: 1.3rem; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 0.8rem; }
        .stat-card { padding: 0.8rem; }
        .stat-card .stat-number { font-size: 1.4rem; }
        table { min-width: 500px; font-size: 0.75rem; }
        th, td { padding: 6px; }
        .action-cell { flex-direction: column; }
        .action-cell .btn { width: 100%; text-align: center; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0 0.8rem 0.8rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .quick-links .qlink { font-size: 0.65rem; padding: 0.15rem 0.3rem; }
        table { min-width: 400px; font-size: 0.7rem; }
        th, td { padding: 4px; }
    }
</style>

<div class="main-content">

    <!-- ============================================
       STICKY HEADER
       ============================================ -->
    <div class="sticky-header">
        <div class="top-bar">
            <div class="top-bar-left">
                <div class="breadcrumb">
                    <i class="ti ti-menu-2 menu-icon"></i>
                    <span class="current">Dashboard</span>
                    <span class="sep">/</span>
                    <span class="sub">Subscriptions</span>
                </div>
            </div>

            <div class="top-bar-center">
                <div class="quick-links">
                    <a href="salons.php" class="qlink"><i class="ti ti-building-store"></i> Manage Salons</a>
                    <span class="link-sep">|</span>
                    <a href="admins.php" class="qlink"><i class="ti ti-user-shield"></i> Manage Owners</a>
                    <span class="link-sep">|</span>
                    <a href="subscriptions.php" class="qlink active"><i class="ti ti-crown"></i> Subscriptions</a>
                    <span class="link-sep">|</span>
                    <a href="settings.php" class="qlink"><i class="ti ti-settings"></i> System Settings</a>
                    <span class="link-sep">|</span>
                    <a href="products.php" class="qlink"><i class="ti ti-box"></i> Products</a>
                </div>
            </div>

            <div class="top-bar-right">
                <div class="search-box">
                    <i class="ti ti-search search-icon"></i>
                    <input type="text" id="globalSearch" placeholder="Search subscriptions...">
                </div>
                <div class="icon-btn"><i class="ti ti-bell"></i></div>
                <div class="topbar-avatar"><i class="ti ti-crown"></i></div>
            </div>
        </div>
    </div>

    <!-- ============================================
       WELCOME ROW
       ============================================ -->
    <div class="welcome-row">
        <div>
            <h1>👑 Subscriptions</h1>
            <p class="subtitle">Manage all salon subscriptions. <strong>Plan prices are managed in System Settings → Plan Pricing.</strong></p>
        </div>
        <div class="date-badge">
            <i class="ti ti-calendar"></i> <?php echo date('j F Y'); ?>
        </div>
    </div>

    <!-- ============================================
       ALERTS
       ============================================ -->
    <?php if($success): ?>
        <div class="alert alert-success">✅ <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-danger">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- ============================================
       STATISTICS
       ============================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
            <div class="stat-label">Total Subscriptions</div>
        </div>
        <div class="stat-card green">
            <div class="stat-number"><?php echo $stats['active'] ?? 0; ?></div>
            <div class="stat-label">Active</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-number"><?php echo $stats['suspended'] ?? 0; ?></div>
            <div class="stat-label">Suspended</div>
        </div>
        <div class="stat-card red">
            <div class="stat-number"><?php echo $stats['expired'] ?? 0; ?></div>
            <div class="stat-label">Expired</div>
        </div>
    </div>

    <!-- ============================================
       PLAN PRICES REFERENCE
       ============================================ -->
    <div style="background: #0e0e0e; border: 1px solid rgba(212, 175, 55, 0.25); border-radius: 12px; padding: 1rem 1.5rem; margin-bottom: 1.5rem;">
        <div style="display: flex; gap: 2rem; flex-wrap: wrap; align-items: center;">
            <span style="color: #f0d878; font-weight: 600; font-size: 0.85rem;">📊 Current Plan Pricing:</span>
            <span style="color: #b8b2a0; font-size: 0.85rem;">Basic: <span style="color: #17a2b8; font-weight: 600;">KSh <?php echo number_format($plan_prices['basic'], 2); ?></span></span>
            <span style="color: #b8b2a0; font-size: 0.85rem;">Premium: <span style="color: #d4af37; font-weight: 600;">KSh <?php echo number_format($plan_prices['premium'], 2); ?></span></span>
            <span style="color: #b8b2a0; font-size: 0.85rem;">Enterprise: <span style="color: #28a745; font-weight: 600;">KSh <?php echo number_format($plan_prices['enterprise'], 2); ?></span></span>
            <a href="settings.php?tab=pricing" style="color: #d4af37; font-size: 0.8rem; text-decoration: none; margin-left: auto;">✏️ Update Prices</a>
        </div>
    </div>

    <!-- ============================================
       SUBSCRIPTIONS TABLE
       ============================================ -->
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Salon</th>
                    <th>Owner</th>
                    <th>Plan</th>
                    <th>Price/Month</th>
                    <th>Status</th>
                    <th>Expiry</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($subscriptions_result) > 0): ?>
                    <?php while($sub = mysqli_fetch_assoc($subscriptions_result)): 
                        $price = $plan_prices[$sub['subscription_plan'] ?? 'basic'] ?? 0;
                    ?>
                        <tr>
                            <td><?php echo $sub['id']; ?></td>
                            <td><?php echo htmlspecialchars($sub['salon_name'] ?? 'Unnamed'); ?></td>
                            <td><?php echo htmlspecialchars($sub['owner_name'] ?? 'No Owner'); ?></td>
                            <td>
                                <span class="plan-badge <?php echo $sub['subscription_plan'] ?? 'basic'; ?>">
                                    <?php echo ucfirst($sub['subscription_plan'] ?? 'Basic'); ?>
                                </span>
                            </td>
                            <td>KSh <?php echo number_format($price, 2); ?></td>
                            <td>
                                <span class="status-badge <?php echo $sub['subscription_status'] ?? 'inactive'; ?>">
                                    <?php echo ucfirst($sub['subscription_status'] ?? 'Inactive'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($sub['subscription_expiry'])): ?>
                                    <?php echo date('M d, Y', strtotime($sub['subscription_expiry'])); ?>
                                    <?php if (strtotime($sub['subscription_expiry']) < time() && $sub['subscription_status'] == 'active'): ?>
                                        <span style="color: #dc3545; font-size: 0.6rem;">⚠️ Expired</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    Not set
                                <?php endif; ?>
                            </td>
                            <td class="action-cell">
                                <?php if ($sub['subscription_status'] == 'active'): ?>
                                    <a href="subscriptions.php?action=suspend&id=<?php echo $sub['id']; ?>" class="btn btn-suspend" onclick="return confirm('Suspend this subscription?')">⏸️ Suspend</a>
                                    <a href="subscriptions.php?action=renew&id=<?php echo $sub['id']; ?>" class="btn btn-renew" onclick="return confirm('Renew this subscription for another month?')">🔄 Renew</a>
                                <?php elseif ($sub['subscription_status'] == 'suspended' || $sub['subscription_status'] == 'expired'): ?>
                                    <a href="subscriptions.php?action=activate&id=<?php echo $sub['id']; ?>" class="btn btn-activate" onclick="return confirm('Activate this subscription?')">✅ Activate</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            <div class="empty-state">
                                <p>No subscriptions found.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<script>
    // Simple search functionality
    document.getElementById('globalSearch')?.addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            const query = this.value.trim();
            if (query.length > 0) {
                window.location.href = 'search.php?q=' + encodeURIComponent(query);
            }
        }
    });
</script>

<?php include '../includes/footer.php'; ?>