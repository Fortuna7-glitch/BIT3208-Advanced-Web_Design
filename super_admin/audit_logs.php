<?php
/**
 * Salon Pro — Super Admin: Audit Logs
 * Luxury gold/black theme
 * Fixed top bar: Breadcrumb | Quick Actions | Search
 */

require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

$admin_name = $_SESSION['user_name'] ?? 'Super Admin';

// ============================================
// FILTERS
// ============================================
$action_filter = isset($_GET['action']) ? $_GET['action'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$user_filter = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// ============================================
// GET AUDIT LOGS
// ============================================
$query = "SELECT * FROM audit_logs WHERE 1=1";
if ($action_filter) {
    $query .= " AND action = '$action_filter'";
}
if ($category_filter) {
    $query .= " AND category = '$category_filter'";
}
if ($user_filter > 0) {
    $query .= " AND user_id = $user_filter";
}
if ($date_from) {
    $query .= " AND DATE(created_at) >= '$date_from'";
}
if ($date_to) {
    $query .= " AND DATE(created_at) <= '$date_to'";
}
$query .= " ORDER BY created_at DESC LIMIT 100";
$logs_result = mysqli_query($conn, $query);

// ============================================
// GET DISTINCT ACTIONS, CATEGORIES, USERS FOR FILTERS
// ============================================
$actions_result = mysqli_query($conn, "SELECT DISTINCT action FROM audit_logs ORDER BY action");
$categories_result = mysqli_query($conn, "SELECT DISTINCT category FROM audit_logs ORDER BY category");
$users_result = mysqli_query($conn, "SELECT DISTINCT user_id, user_name FROM audit_logs ORDER BY user_name");

// Get unread notification count
$unread_count = getUnreadNotificationCount();

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
       FILTER BAR
       ============================================ */
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
        min-width: 750px;
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

    .action-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
    }

    .action-badge.salon { background: rgba(23, 162, 184, 0.15); color: #17a2b8; border: 1px solid #17a2b8; }
    .action-badge.user { background: rgba(111, 66, 193, 0.15); color: #6f42c1; border: 1px solid #6f42c1; }
    .action-badge.subscription { background: rgba(212, 175, 55, 0.15); color: #d4af37; border: 1px solid #d4af37; }
    .action-badge.settings { background: rgba(40, 167, 69, 0.15); color: #28a745; border: 1px solid #28a745; }
    .action-badge.auth { background: rgba(220, 53, 69, 0.15); color: #dc3545; border: 1px solid #dc3545; }
    .action-badge.other { background: rgba(108, 117, 125, 0.15); color: #adb5bd; border: 1px solid #adb5bd; }

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
        .top-bar-right .icon-btn { font-size: 0.95rem; padding: 0.2rem 0.4rem; }
        .top-bar-right .topbar-avatar { width: 28px; height: 28px; font-size: 0.75rem; }
        .quick-links .qlink { font-size: 0.7rem; padding: 0.2rem 0.4rem; }
        .welcome-row { flex-direction: column; align-items: flex-start; }
        .welcome-left h1 { font-size: 1.3rem; }
        .date-badge { align-self: flex-start; }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar input,
        .filter-bar select { width: 100%; }
        table { min-width: 500px; font-size: 0.75rem; }
        th, td { padding: 6px; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0 0.8rem 0.8rem; }
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
                    <span class="sub">Audit Logs</span>
                </div>
            </div>

            <div class="top-bar-center">
                <div class="quick-links">
                    <a href="salons.php" class="qlink"><i class="ti ti-building-store"></i> Manage Salons</a>
                    <span class="link-sep">|</span>
                    <a href="admins.php" class="qlink"><i class="ti ti-user-shield"></i> Manage Owners</a>
                    <span class="link-sep">|</span>
                    <a href="subscriptions.php" class="qlink"><i class="ti ti-crown"></i> Subscriptions</a>
                    <span class="link-sep">|</span>
                    <a href="staff.php" class="qlink"><i class="ti ti-users"></i> Staff</a>
                    <span class="link-sep">|</span>
                    <a href="settings.php" class="qlink"><i class="ti ti-settings"></i> System Settings</a>
                    <span class="link-sep">|</span>
                    <a href="products.php" class="qlink"><i class="ti ti-box"></i> Products</a>
                    <span class="link-sep">|</span>
                    <a href="audit_logs.php" class="qlink active"><i class="ti ti-file-text"></i> Audit Logs</a>
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

    <!-- ============================================
       WELCOME ROW
       ============================================ -->
    <div class="welcome-row">
        <div class="welcome-left">
            <h1>📋 Audit Logs</h1>
            <p class="subtitle">Track all system activity and user actions</p>
        </div>
        <div class="date-badge">
            <i class="ti ti-calendar"></i> <?php echo date('j F Y'); ?>
        </div>
    </div>

    <!-- ============================================
       FILTER BAR
       ============================================ -->
    <div class="filter-bar">
        <form method="GET" style="display: flex; gap: 0.8rem; flex: 1; flex-wrap: wrap; align-items: center;">
            <select name="action">
                <option value="">All Actions</option>
                <?php while($action = mysqli_fetch_assoc($actions_result)): ?>
                    <option value="<?php echo $action['action']; ?>" <?php echo ($action_filter == $action['action']) ? 'selected' : ''; ?>>
                        <?php echo ucfirst(str_replace('_', ' ', $action['action'])); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <select name="category">
                <option value="">All Categories</option>
                <?php while($category = mysqli_fetch_assoc($categories_result)): ?>
                    <option value="<?php echo $category['category']; ?>" <?php echo ($category_filter == $category['category']) ? 'selected' : ''; ?>>
                        <?php echo ucfirst($category['category']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <select name="user">
                <option value="0">All Users</option>
                <?php while($user = mysqli_fetch_assoc($users_result)): ?>
                    <option value="<?php echo $user['user_id']; ?>" <?php echo ($user_filter == $user['user_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['user_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <input type="date" name="date_from" value="<?php echo $date_from; ?>" placeholder="From">
            <input type="date" name="date_to" value="<?php echo $date_to; ?>" placeholder="To">
            <button type="submit" class="filter-btn">Filter</button>
            <a href="audit_logs.php" class="clear-btn">Clear</a>
        </form>
    </div>

    <!-- ============================================
       AUDIT LOGS TABLE
       ============================================ -->
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>Category</th>
                    <th>Details</th>
                    <th>IP Address</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($logs_result) > 0): ?>
                    <?php while($log = mysqli_fetch_assoc($logs_result)): ?>
                        <?php
                        $category_class = 'other';
                        if (in_array($log['category'], ['salon', 'salons'])) $category_class = 'salon';
                        elseif (in_array($log['category'], ['user', 'users', 'staff', 'owner', 'owners'])) $category_class = 'user';
                        elseif (in_array($log['category'], ['subscription', 'subscriptions', 'plan'])) $category_class = 'subscription';
                        elseif (in_array($log['category'], ['settings', 'config'])) $category_class = 'settings';
                        elseif (in_array($log['category'], ['auth', 'login', 'logout'])) $category_class = 'auth';
                        ?>
                        <tr>
                            <td><?php echo $log['id']; ?></td>
                            <td><?php echo htmlspecialchars($log['user_name']); ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $log['user_role'])); ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?></td>
                            <td>
                                <span class="action-badge <?php echo $category_class; ?>">
                                    <?php echo ucfirst($log['category']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($log['details']); ?></td>
                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            <div class="empty-state">
                                <p>No audit logs found.</p>
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