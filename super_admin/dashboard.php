<?php
// super_admin/dashboard.php - REDESIGNED with VLMS-style layout
require_once '../config/database.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

// Get all statistics
$total_salons = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM salons"))['count'];
$active_salons = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM salons WHERE subscription_status = 'active'"))['count'];
$total_admins = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'admin'"))['count'];
$total_staff = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'staff'"))['count'];
$total_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'customer'"))['count'];
$total_revenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM subscription_history"))['total'] ?? 0;

// Plan distribution
$plan_stats = [];
$plans = ['basic', 'premium', 'enterprise'];
foreach ($plans as $plan) {
    $count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM salons WHERE subscription_plan = '$plan'"))['count'];
    $plan_stats[$plan] = $count;
}

// Recent subscriptions
$recent_subs = mysqli_query($conn, "SELECT sh.*, s.salon_name FROM subscription_history sh JOIN salons s ON sh.salon_id = s.id ORDER BY sh.payment_date DESC LIMIT 5");

// Recent salons
$recent_salons = mysqli_query($conn, "SELECT * FROM salons ORDER BY created_at DESC LIMIT 5");

$user_name = $_SESSION['user_name'];

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
    .main-content {
        padding: 2rem;
        background: #0a0a0a;
        min-height: 100vh;
    }

    /* Welcome Banner */
    .welcome-banner {
        background: linear-gradient(135deg, #1a1a1a 0%, #2a1f0a 100%);
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 15px;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }
    .welcome-banner h1 {
        color: #d4af37;
        font-size: 1.8rem;
        font-family: 'Playfair Display', serif;
        margin-bottom: 0.3rem;
    }
    .welcome-banner p {
        color: #aaa;
        font-size: 0.95rem;
    }
    .welcome-banner .highlight {
        color: #d4af37;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    .stat-card {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        text-align: center;
        border-left: 4px solid #d4af37;
        transition: all 0.3s;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(212, 175, 55, 0.1);
    }
    .stat-card .number {
        font-size: 2.2rem;
        font-weight: bold;
        color: #d4af37;
    }
    .stat-card .label {
        color: #aaa;
        margin-top: 0.3rem;
        font-size: 0.85rem;
    }

    /* Section Title */
    .section-title {
        color: #d4af37;
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 1rem;
        border-left: 3px solid #d4af37;
        padding-left: 1rem;
    }

    /* Plan Stats */
    .plan-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    .plan-card {
        background: #1a1a1a;
        border-radius: 12px;
        padding: 1rem;
        text-align: center;
        border: 1px solid rgba(212, 175, 55, 0.2);
    }
    .plan-card .icon { font-size: 1.8rem; }
    .plan-card .count { font-size: 1.8rem; font-weight: bold; color: white; }
    .plan-card .label { color: #aaa; font-size: 0.8rem; }
    .plan-card.basic { border-color: #17a2b8; }
    .plan-card.premium { border-color: #d4af37; }
    .plan-card.enterprise { border-color: #28a745; }

    /* Recent Lists */
    .recent-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    .recent-card {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.2rem;
        border: 1px solid rgba(212, 175, 55, 0.2);
    }
    .recent-card h4 {
        color: #d4af37;
        margin-bottom: 0.8rem;
        font-size: 0.95rem;
    }
    .recent-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0;
        border-bottom: 1px solid rgba(212, 175, 55, 0.1);
        font-size: 0.85rem;
    }
    .recent-item:last-child {
        border-bottom: none;
    }
    .recent-item .name {
        color: white;
    }
    .recent-item .meta {
        color: #aaa;
        font-size: 0.75rem;
    }
    .recent-item .plan-badge {
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: bold;
    }
    .plan-basic { background: rgba(23, 162, 184, 0.2); color: #17a2b8; border: 1px solid #17a2b8; }
    .plan-premium { background: rgba(212, 175, 55, 0.2); color: #d4af37; border: 1px solid #d4af37; }
    .plan-enterprise { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }

    .view-all {
        color: #d4af37;
        text-decoration: none;
        font-size: 0.8rem;
        float: right;
    }
    .view-all:hover {
        text-decoration: underline;
    }

    /* ============================================
    RESPONSIVE
       ============================================ */
    @media (max-width: 1024px) {
        .recent-grid { grid-template-columns: 1fr; }
        .stats-grid { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
    }

    @media (max-width: 768px) {
        .main-content { padding: 1rem; }
        .welcome-banner { padding: 1rem; }
        .welcome-banner h1 { font-size: 1.4rem; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 1rem; }
        .stat-card .number { font-size: 1.8rem; }
        .plan-stats { grid-template-columns: 1fr 1fr 1fr; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .welcome-banner h1 { font-size: 1.2rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .plan-stats { grid-template-columns: 1fr; }
        .recent-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="main-content">

    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <h1>👋 Welcome back, <span class="highlight"><?php echo htmlspecialchars($user_name); ?></span>!</h1>
        <p>You have full control over <strong><?php echo $total_salons; ?></strong> salons with <strong><?php echo $total_admins; ?></strong> owners and <strong><?php echo $total_staff; ?></strong> staff members.</p>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number"><?php echo $total_salons; ?></div>
            <div class="label">🏢 Total Salons</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $active_salons; ?></div>
            <div class="label">✅ Active Salons</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $total_admins; ?></div>
            <div class="label">👨‍💼 Salon Owners</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $total_staff; ?></div>
            <div class="label">👥 Total Staff</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $total_customers; ?></div>
            <div class="label">👤 Total Customers</div>
        </div>
        <div class="stat-card">
            <div class="number">KSh <?php echo number_format($total_revenue, 2); ?></div>
            <div class="label">💰 Total Revenue</div>
        </div>
    </div>

    <!-- Plan Distribution -->
    <h3 class="section-title">📊 Plan Distribution</h3>
    <div class="plan-stats">
        <div class="plan-card basic">
            <div class="icon">📘</div>
            <div class="count"><?php echo $plan_stats['basic']; ?></div>
            <div class="label">Basic</div>
        </div>
        <div class="plan-card premium">
            <div class="icon">📗</div>
            <div class="count"><?php echo $plan_stats['premium']; ?></div>
            <div class="label">Premium</div>
        </div>
        <div class="plan-card enterprise">
            <div class="icon">📕</div>
            <div class="count"><?php echo $plan_stats['enterprise']; ?></div>
            <div class="label">Enterprise</div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="recent-grid">

        <!-- Recent Salons -->
        <div class="recent-card">
            <h4>🏢 Recent Salons <a href="salons.php" class="view-all">View All →</a></h4>
            <?php while($salon = mysqli_fetch_assoc($recent_salons)): ?>
            <div class="recent-item">
                <span class="name"><?php echo htmlspecialchars($salon['salon_name']); ?></span>
                <span>
                    <span class="plan-badge plan-<?php echo $salon['subscription_plan']; ?>">
                        <?php echo ucfirst($salon['subscription_plan']); ?>
                    </span>
                    <span class="meta"><?php echo date('M d', strtotime($salon['created_at'])); ?></span>
                </span>
            </div>
            <?php endwhile; ?>
        </div>

        <!-- Recent Subscriptions -->
        <div class="recent-card">
            <h4>💰 Recent Subscriptions <a href="subscriptions.php" class="view-all">View All →</a></h4>
            <?php while($sub = mysqli_fetch_assoc($recent_subs)): ?>
            <div class="recent-item">
                <span class="name"><?php echo htmlspecialchars($sub['salon_name']); ?></span>
                <span>
                    <span class="plan-badge plan-<?php echo $sub['plan']; ?>">
                        <?php echo ucfirst($sub['plan']); ?>
                    </span>
                    <span class="meta">KSh <?php echo number_format($sub['amount'], 2); ?></span>
                </span>
            </div>
            <?php endwhile; ?>
        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>