<?php
// super_admin/dashboard.php - REDESIGNED: Clean, Modern, Card-Based Layout
require_once '../config/database.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

// ============================================
// STATISTICS
// ============================================
$total_salons = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM salons"))['count'];
$active_salons = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM salons WHERE subscription_status = 'active'"))['count'];
$total_admins = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'admin'"))['count'];
$total_staff = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'staff'"))['count'];
$total_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'customer'"))['count'];
$total_revenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM subscription_history"))['total'] ?? 0;

// ============================================
// PLAN DISTRIBUTION
// ============================================
$plan_stats = [];
$plans = ['basic', 'premium', 'enterprise'];
foreach ($plans as $plan) {
    $count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM salons WHERE subscription_plan = '$plan'"))['count'];
    $plan_stats[$plan] = $count;
}

// ============================================
// RECENT ACTIVITY
// ============================================
$recent_activity = [];

// Recent salon additions
$recent_salons = mysqli_query($conn, "SELECT salon_name, created_at, 'new_salon' as type FROM salons ORDER BY created_at DESC LIMIT 3");
while ($row = mysqli_fetch_assoc($recent_salons)) {
    $row['message'] = "New salon added: <strong>" . htmlspecialchars($row['salon_name']) . "</strong>";
    $row['time'] = time_elapsed_string($row['created_at']);
    $recent_activity[] = $row;
}

// Recent subscriptions
$recent_subs = mysqli_query($conn, "SELECT sh.*, s.salon_name, 'subscription' as type FROM subscription_history sh JOIN salons s ON sh.salon_id = s.id ORDER BY sh.payment_date DESC LIMIT 3");
while ($row = mysqli_fetch_assoc($recent_subs)) {
    $row['message'] = "<strong>" . htmlspecialchars($row['salon_name']) . "</strong> renewed subscription (" . ucfirst($row['plan']) . ")";
    $row['time'] = time_elapsed_string($row['payment_date']);
    $recent_activity[] = $row;
}

// Recent user registrations
$recent_users = mysqli_query($conn, "SELECT full_name, role, created_at, 'new_user' as type FROM users WHERE role != 'super_admin' ORDER BY created_at DESC LIMIT 3");
while ($row = mysqli_fetch_assoc($recent_users)) {
    $row['message'] = "New user registered: <strong>" . htmlspecialchars($row['full_name']) . "</strong> (" . ucfirst($row['role']) . ")";
    $row['time'] = time_elapsed_string($row['created_at']);
    $recent_activity[] = $row;
}

// Sort by time (newest first)
usort($recent_activity, function($a, $b) {
    return strtotime($b['created_at'] ?? $b['payment_date'] ?? $b['created_at']) - strtotime($a['created_at'] ?? $a['payment_date'] ?? $a['created_at']);
});
$recent_activity = array_slice($recent_activity, 0, 10);

// ============================================
// TIME ELAPSED FUNCTION
// ============================================
function time_elapsed_string($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

$user_name = $_SESSION['user_name'];
include '../includes/header.php';
?>

<style>
    /* ============================================
       DASHBOARD STYLES
       ============================================ */
    .main-content {
        padding: 2rem;
        background: #0a0a0a;
        min-height: 100vh;
    }

    /* ============================================
       WELCOME HEADER
       ============================================ */
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        margin-bottom: 2rem;
    }
    .dashboard-header h1 {
        color: #d4af37;
        font-size: 1.8rem;
        font-family: 'Playfair Display', serif;
    }
    .dashboard-header p {
        color: #aaa;
        font-size: 0.95rem;
    }
    .dashboard-header .date-badge {
        background: #1a1a1a;
        padding: 0.5rem 1.2rem;
        border-radius: 50px;
        border: 1px solid rgba(212, 175, 55, 0.3);
        color: #aaa;
        font-size: 0.85rem;
    }
    .dashboard-header .date-badge i {
        color: #d4af37;
        margin-right: 0.5rem;
    }

    /* ============================================
       STATS GRID
       ============================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    .stat-card {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        border-left: 4px solid #d4af37;
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(212, 175, 55, 0.1);
    }
    .stat-card .stat-icon {
        font-size: 1.8rem;
        opacity: 0.3;
        position: absolute;
        right: 1rem;
        top: 1rem;
    }
    .stat-card .stat-number {
        font-size: 2.2rem;
        font-weight: bold;
        color: #d4af37;
    }
    .stat-card .stat-label {
        color: #aaa;
        font-size: 0.85rem;
        margin-top: 0.2rem;
    }
    .stat-card .stat-trend {
        font-size: 0.75rem;
        margin-top: 0.5rem;
        display: inline-block;
        padding: 2px 10px;
        border-radius: 20px;
    }
    .stat-card .stat-trend.up {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
    }
    .stat-card .stat-trend.down {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
    }
    .stat-card .stat-trend.neutral {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
    }

    /* ============================================
       TWO-COLUMN GRID
       ============================================ */
    .dashboard-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }

    /* ============================================
       PLAN DISTRIBUTION
       ============================================ */
    .plan-distribution {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        border: 1px solid rgba(212, 175, 55, 0.15);
    }
    .plan-distribution h3 {
        color: #d4af37;
        font-size: 1rem;
        margin-bottom: 1.2rem;
    }

    .plan-bar {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 0.8rem;
    }
    .plan-bar .plan-label {
        min-width: 80px;
        color: #aaa;
        font-size: 0.85rem;
    }
    .plan-bar .plan-track {
        flex: 1;
        height: 8px;
        background: #2a2a2a;
        border-radius: 10px;
        overflow: hidden;
    }
    .plan-bar .plan-track .plan-fill {
        height: 100%;
        border-radius: 10px;
        transition: width 0.6s ease;
    }
    .plan-bar .plan-track .plan-fill.basic {
        background: #17a2b8;
    }
    .plan-bar .plan-track .plan-fill.premium {
        background: #d4af37;
    }
    .plan-bar .plan-track .plan-fill.enterprise {
        background: #28a745;
    }
    .plan-bar .plan-count {
        min-width: 30px;
        color: white;
        font-weight: 600;
        font-size: 0.9rem;
        text-align: right;
    }

    /* ============================================
       RECENT ACTIVITY
       ============================================ */
    .activity-feed {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        border: 1px solid rgba(212, 175, 55, 0.15);
    }
    .activity-feed h3 {
        color: #d4af37;
        font-size: 1rem;
        margin-bottom: 1.2rem;
    }

    .activity-item {
        display: flex;
        align-items: flex-start;
        gap: 0.8rem;
        padding: 0.6rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }
    .activity-item:last-child {
        border-bottom: none;
    }
    .activity-item .activity-icon {
        font-size: 1.2rem;
        min-width: 30px;
        margin-top: 2px;
    }
    .activity-item .activity-icon.new_salon { color: #17a2b8; }
    .activity-item .activity-icon.subscription { color: #d4af37; }
    .activity-item .activity-icon.new_user { color: #28a745; }
    .activity-item .activity-content {
        flex: 1;
    }
    .activity-item .activity-content .activity-message {
        color: #ddd;
        font-size: 0.9rem;
        line-height: 1.4;
    }
    .activity-item .activity-content .activity-message strong {
        color: white;
    }
    .activity-item .activity-content .activity-time {
        color: #666;
        font-size: 0.7rem;
        margin-top: 2px;
    }

    /* ============================================
       QUICK ACTIONS
       ============================================ */
    .quick-actions {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        margin-top: 1rem;
    }
    .quick-actions .btn {
        padding: 10px 22px;
        border-radius: 25px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s;
        font-size: 0.85rem;
    }
    .quick-actions .btn-primary {
        background: #d4af37;
        color: #050505;
    }
    .quick-actions .btn-primary:hover {
        background: #f9e547;
        transform: translateY(-2px);
    }
    .quick-actions .btn-outline {
        border: 1px solid #d4af37;
        color: #d4af37;
        background: transparent;
    }
    .quick-actions .btn-outline:hover {
        background: rgba(212, 175, 55, 0.1);
        transform: translateY(-2px);
    }

    /* ============================================
       RESPONSIVE
       ============================================ */
    @media (max-width: 1024px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        }
    }

    @media (max-width: 768px) {
        .main-content { padding: 1rem; }
        .dashboard-header { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
        .dashboard-header h1 { font-size: 1.4rem; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 1rem; }
        .stat-card { padding: 1rem; }
        .stat-card .stat-number { font-size: 1.6rem; }
        .quick-actions { flex-direction: column; }
        .quick-actions .btn { text-align: center; }
        .plan-bar { flex-wrap: wrap; gap: 0.4rem; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .stat-card .stat-number { font-size: 1.4rem; }
        .dashboard-header h1 { font-size: 1.2rem; }
    }
</style>

<div class="main-content">

    <!-- ============================================
       HEADER
       ============================================ -->
    <div class="dashboard-header">
        <div>
            <h1>👑 Super Admin Dashboard</h1>
            <p>Welcome back, <strong><?php echo htmlspecialchars($user_name); ?></strong>! You have full control over all salons.</p>
        </div>
        <div class="date-badge">
            <i class="fas fa-calendar-alt"></i> <?php echo date('l, F d, Y'); ?>
        </div>
    </div>

    <!-- ============================================
       STATS GRID
       ============================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-icon">🏢</span>
            <div class="stat-number"><?php echo $total_salons; ?></div>
            <div class="stat-label">Total Salons</div>
            <span class="stat-trend neutral">+<?php echo $total_salons; ?> total</span>
        </div>
        <div class="stat-card">
            <span class="stat-icon">✅</span>
            <div class="stat-number"><?php echo $active_salons; ?></div>
            <div class="stat-label">Active Salons</div>
            <span class="stat-trend up"><?php echo $active_salons; ?> active</span>
        </div>
        <div class="stat-card">
            <span class="stat-icon">👨‍💼</span>
            <div class="stat-number"><?php echo $total_admins; ?></div>
            <div class="stat-label">Salon Owners</div>
            <span class="stat-trend neutral"><?php echo $total_admins; ?> owners</span>
        </div>
        <div class="stat-card">
            <span class="stat-icon">👥</span>
            <div class="stat-number"><?php echo $total_staff; ?></div>
            <div class="stat-label">Total Staff</div>
            <span class="stat-trend neutral"><?php echo $total_staff; ?> staff</span>
        </div>
        <div class="stat-card">
            <span class="stat-icon">👤</span>
            <div class="stat-number"><?php echo $total_customers; ?></div>
            <div class="stat-label">Total Customers</div>
            <span class="stat-trend neutral"><?php echo $total_customers; ?> customers</span>
        </div>
        <div class="stat-card">
            <span class="stat-icon">💰</span>
            <div class="stat-number">KSh <?php echo number_format($total_revenue, 2); ?></div>
            <div class="stat-label">Total Revenue</div>
            <span class="stat-trend up"><?php echo number_format($total_revenue, 0); ?> revenue</span>
        </div>
    </div>

    <!-- ============================================
       DASHBOARD GRID (Plan Distribution + Activity)
       ============================================ -->
    <div class="dashboard-grid">

        <!-- Plan Distribution -->
        <div class="plan-distribution">
            <h3>📊 Plan Distribution</h3>

            <?php
            $total_plans = array_sum($plan_stats);
            if ($total_plans > 0) {
                $basic_percent = round(($plan_stats['basic'] / $total_plans) * 100);
                $premium_percent = round(($plan_stats['premium'] / $total_plans) * 100);
                $enterprise_percent = round(($plan_stats['enterprise'] / $total_plans) * 100);
            } else {
                $basic_percent = $premium_percent = $enterprise_percent = 0;
            }
            ?>

            <div class="plan-bar">
                <span class="plan-label">📘 Basic</span>
                <div class="plan-track">
                    <div class="plan-fill basic" style="width: <?php echo $basic_percent; ?>%;"></div>
                </div>
                <span class="plan-count"><?php echo $plan_stats['basic']; ?></span>
            </div>

            <div class="plan-bar">
                <span class="plan-label">📗 Premium</span>
                <div class="plan-track">
                    <div class="plan-fill premium" style="width: <?php echo $premium_percent; ?>%;"></div>
                </div>
                <span class="plan-count"><?php echo $plan_stats['premium']; ?></span>
            </div>

            <div class="plan-bar">
                <span class="plan-label">📕 Enterprise</span>
                <div class="plan-track">
                    <div class="plan-fill enterprise" style="width: <?php echo $enterprise_percent; ?>%;"></div>
                </div>
                <span class="plan-count"><?php echo $plan_stats['enterprise']; ?></span>
            </div>

            <div style="margin-top: 1rem; text-align: center; color: #666; font-size: 0.8rem;">
                Total: <strong style="color: #d4af37;"><?php echo $total_plans; ?></strong> salons
            </div>

            <div class="quick-actions" style="margin-top: 1.5rem;">
                <a href="salons.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Salon</a>
                <a href="subscriptions.php" class="btn btn-outline"><i class="fas fa-credit-card"></i> Subscriptions</a>
                <a href="admins.php" class="btn btn-outline"><i class="fas fa-users"></i> Owners</a>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="activity-feed">
            <h3>📋 Recent Activity</h3>

            <?php if (!empty($recent_activity)): ?>
                <?php foreach ($recent_activity as $activity): ?>
                    <div class="activity-item">
                        <span class="activity-icon <?php echo $activity['type']; ?>">
                            <?php
                            if ($activity['type'] == 'new_salon') echo '🏢';
                            elseif ($activity['type'] == 'subscription') echo '💰';
                            elseif ($activity['type'] == 'new_user') echo '👤';
                            else echo '📌';
                            ?>
                        </span>
                        <div class="activity-content">
                            <div class="activity-message"><?php echo $activity['message']; ?></div>
                            <div class="activity-time"><?php echo $activity['time']; ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="color: #666; text-align: center; padding: 1.5rem;">
                    <p>No recent activity to display.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>