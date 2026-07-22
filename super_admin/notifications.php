<?php
/**
 * Salon Pro — Super Admin: Notifications Panel
 * Luxury gold/black theme
 */

require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

$error = '';
$success = '';

// ============================================
// HANDLE ACTIONS
// ============================================

// Mark single notification as read
if (isset($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    if (markNotificationRead($notification_id)) {
        $success = "Notification marked as read.";
    } else {
        $error = "Failed to mark notification as read.";
    }
}

// Mark all notifications as read
if (isset($_GET['mark_all_read'])) {
    if (markAllNotificationsRead()) {
        $success = "All notifications marked as read.";
    } else {
        $error = "Failed to mark all notifications as read.";
    }
}

// Delete notification
if (isset($_GET['delete'])) {
    $notification_id = (int)$_GET['delete'];
    $query = "DELETE FROM notifications WHERE id = $notification_id";
    if (mysqli_query($conn, $query)) {
        $success = "Notification deleted.";
    } else {
        $error = "Failed to delete notification.";
    }
}

// ============================================
// GET NOTIFICATIONS
// ============================================
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

$notifications = getNotifications($limit, $offset);
$unread_count = getUnreadNotificationCount();

// Get total count
$super_query = "SELECT id FROM users WHERE role = 'super_admin' LIMIT 1";
$super_result = mysqli_query($conn, $super_query);
$super = mysqli_fetch_assoc($super_result);
$total_count = 0;
if ($super) {
    $count_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = {$super['id']}";
    $count_result = mysqli_query($conn, $count_query);
    $total_count = mysqli_fetch_assoc($count_result)['count'] ?? 0;
}

// ============================================
// NOTIFICATION TYPE ICONS
// ============================================
$type_icons = [
    'subscription_renewed' => '👑',
    'subscription_expiring' => '⚠️',
    'subscription_suspended' => '⏸️',
    'salon_created' => '🏪',
    'owner_registered' => '👤',
    'payment_failed' => '❌',
    'payment_success' => '✅',
    'system_alert' => '🔔',
    'staff_added' => '👥',
    'customer_registered' => '👤',
    'subscription_expired' => '🚫'
];

$type_colors = [
    'subscription_renewed' => '#28a745',
    'subscription_expiring' => '#d4af37',
    'subscription_suspended' => '#ffc107',
    'salon_created' => '#17a2b8',
    'owner_registered' => '#6f42c1',
    'payment_failed' => '#dc3545',
    'payment_success' => '#28a745',
    'system_alert' => '#d4af37',
    'staff_added' => '#17a2b8',
    'customer_registered' => '#6f42c1',
    'subscription_expired' => '#dc3545'
];

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
        text-decoration: none;
    }

    .top-bar-right .icon-btn .badge {
        position: absolute;
        top: -6px;
        right: -8px;
        background: #dc3545;
        color: white;
        font-size: 0.55rem;
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
       NOTIFICATION PANEL
       ============================================ */
    .notification-panel {
        background: #0e0e0e;
        border: 1px solid rgba(212, 175, 55, 0.25);
        border-radius: 12px;
        padding: 0;
        overflow: hidden;
    }

    .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.2rem 1.5rem;
        border-bottom: 1px solid rgba(212, 175, 55, 0.15);
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .notification-header .left {
        display: flex;
        align-items: center;
        gap: 0.8rem;
    }

    .notification-header .left h2 {
        color: #f0d878;
        font-size: 1rem;
        margin: 0;
    }

    .notification-header .left .unread-badge {
        background: #dc3545;
        color: white;
        font-size: 0.7rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
    }

    .notification-header .actions {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .notification-header .actions .btn {
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
    }

    .btn-mark-all {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid #d4af37;
    }

    .btn-mark-all:hover {
        background: #d4af37;
        color: #050505;
    }

    .btn-refresh {
        background: #1a1a1a;
        color: #7a7568;
        border: 1px solid rgba(212, 175, 55, 0.2);
    }

    .btn-refresh:hover {
        background: #2a2a2a;
        color: #f5f0e1;
    }

    /* Notification Item */
    .notification-item {
        display: flex;
        gap: 1rem;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid rgba(212, 175, 55, 0.08);
        transition: all 0.3s;
        text-decoration: none;
        align-items: flex-start;
    }

    .notification-item:hover {
        background: rgba(212, 175, 55, 0.05);
    }

    .notification-item.unread {
        border-left: 3px solid #d4af37;
        background: rgba(212, 175, 55, 0.03);
    }

    .notification-item .icon {
        font-size: 1.5rem;
        flex-shrink: 0;
        width: 40px;
        text-align: center;
    }

    .notification-item .content {
        flex: 1;
    }

    .notification-item .content .title {
        color: #f5f0e1;
        font-weight: 500;
        font-size: 0.95rem;
    }

    .notification-item .content .message {
        color: #b8b2a0;
        font-size: 0.85rem;
        margin-top: 0.2rem;
    }

    .notification-item .content .time {
        color: #7a7568;
        font-size: 0.7rem;
        margin-top: 0.3rem;
        display: inline-block;
    }

    .notification-item .content .time i {
        margin-right: 0.3rem;
    }

    .notification-item .actions {
        display: flex;
        gap: 0.5rem;
        flex-shrink: 0;
        align-items: center;
    }

    .notification-item .actions .btn {
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 0.6rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
    }

    .btn-mark-read {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border: 1px solid #28a745;
    }

    .btn-mark-read:hover {
        background: #28a745;
        color: white;
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

    .btn-view {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid rgba(212, 175, 55, 0.3);
    }

    .btn-view:hover {
        background: #d4af37;
        color: #050505;
    }

    .read-dot {
        display: inline-block;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #d4af37;
        margin-right: 0.5rem;
    }

    /* Footer */
    .notification-footer {
        padding: 1rem 1.5rem;
        text-align: center;
        border-top: 1px solid rgba(212, 175, 55, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .notification-footer .info {
        color: #7a7568;
        font-size: 0.8rem;
    }

    .notification-footer .load-more {
        color: #d4af37;
        text-decoration: none;
        font-size: 0.8rem;
    }

    .notification-footer .load-more:hover {
        text-decoration: underline;
    }

    .empty-state {
        text-align: center;
        padding: 3rem;
        color: #7a7568;
    }

    .empty-state .icon {
        font-size: 3rem;
        margin-bottom: 1rem;
    }
    .empty-state h3 {
        color: #b8b2a0;
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
        .notification-header { flex-direction: column; align-items: flex-start; }
        .notification-header .actions { width: 100%; }
        .notification-header .actions .btn { flex: 1; text-align: center; }
        .notification-item { flex-direction: column; padding: 1rem; }
        .notification-item .actions { width: 100%; justify-content: flex-start; }
        .notification-item .actions .btn { flex: 1; text-align: center; }
        .notification-footer { flex-direction: column; text-align: center; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0 0.8rem 0.8rem; }
        .quick-links .qlink { font-size: 0.65rem; padding: 0.15rem 0.3rem; }
        .notification-item { padding: 0.8rem; }
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
                    <span class="sub">Notifications</span>
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
                    <a href="settings.php" class="qlink"><i class="ti ti-settings"></i> System Settings</a>
                    <span class="link-sep">|</span>
                    <a href="products.php" class="qlink"><i class="ti ti-box"></i> Products</a>
                </div>
            </div>

            <div class="top-bar-right">
                <div class="search-box">
                    <i class="ti ti-search search-icon"></i>
                    <input type="text" id="globalSearch" placeholder="Search notifications...">
                </div>
                <a href="notifications.php" class="icon-btn" title="Notifications">
                    <i class="ti ti-bell"></i>
                    <?php 
                    $unread_count = getUnreadNotificationCount();
                    if ($unread_count > 0): 
                    ?>
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
        <div>
            <h1>🔔 Notifications</h1>
            <p class="subtitle">Stay updated with all system activity</p>
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
       NOTIFICATION PANEL
       ============================================ -->
    <div class="notification-panel">
        <!-- Header -->
        <div class="notification-header">
            <div class="left">
                <h2>📬 Inbox</h2>
                <?php if ($unread_count > 0): ?>
                    <span class="unread-badge"><?php echo $unread_count; ?> unread</span>
                <?php endif; ?>
            </div>
            <div class="actions">
                <?php if ($unread_count > 0): ?>
                    <a href="notifications.php?mark_all_read=1" class="btn btn-mark-all">✅ Mark All Read</a>
                <?php endif; ?>
                <a href="notifications.php" class="btn btn-refresh">🔄 Refresh</a>
            </div>
        </div>

        <!-- Notification List -->
        <?php if (count($notifications) > 0): ?>
            <?php foreach ($notifications as $notification): 
                $icon = $type_icons[$notification['type']] ?? '🔔';
                $color = $type_colors[$notification['type']] ?? '#d4af37';
                $is_unread = $notification['is_read'] == 0;
                $time = time_elapsed_string($notification['created_at']); // ✅ FIXED: time_elapsed_string() now defined in database.php
            ?>
                <div class="notification-item <?php echo $is_unread ? 'unread' : ''; ?>">
                    <div class="icon"><?php echo $icon; ?></div>
                    <div class="content">
                        <div class="title">
                            <?php if ($is_unread): ?>
                                <span class="read-dot"></span>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($notification['title']); ?>
                        </div>
                        <div class="message"><?php echo htmlspecialchars($notification['message']); ?></div>
                        <span class="time"><i class="ti ti-clock"></i> <?php echo $time; ?></span>
                    </div>
                    <div class="actions">
                        <?php if ($is_unread): ?>
                            <a href="notifications.php?mark_read=<?php echo $notification['id']; ?>" class="btn btn-mark-read">✅ Read</a>
                        <?php endif; ?>
                        <?php if (!empty($notification['link'])): ?>
                            <a href="<?php echo $notification['link']; ?>" class="btn btn-view">👁️ View</a>
                        <?php endif; ?>
                        <a href="notifications.php?delete=<?php echo $notification['id']; ?>" class="btn btn-delete" onclick="return confirm('Delete this notification?')">🗑️</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon">🎉</div>
                <h3>All Caught Up!</h3>
                <p>No notifications to show. You're all up to date!</p>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="notification-footer">
            <span class="info">Showing <?php echo count($notifications); ?> of <?php echo $total_count; ?> notifications</span>
            <?php if ($total_count > $limit): ?>
                <a href="notifications.php?limit=<?php echo $limit + 20; ?>" class="load-more">Load More →</a>
            <?php endif; ?>
        </div>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<script>
    // Simple search functionality
    document.getElementById('globalSearch')?.addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            const query = this.value.trim();
            if (query.length > 0) {
                window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&type=notifications';
            }
        }
    });
</script>

<?php include '../includes/footer.php'; ?>