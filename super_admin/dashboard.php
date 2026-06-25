<?php
// super_admin/dashboard.php - RESPONSIVE REWRITE
require_once '../config/database.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

$total_salons = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM salons"))['count'];
$active_salons = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM salons WHERE subscription_status = 'active'"))['count'];
$total_admins = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'admin'"))['count'];
$total_staff = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'staff'"))['count'];
$total_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'customer'"))['count'];
$total_revenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM subscription_history"))['total'] ?? 0;

$plan_stats = [];
$plans = ['basic', 'premium', 'enterprise'];
foreach ($plans as $plan) {
    $count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM salons WHERE subscription_plan = '$plan'"))['count'];
    $plan_stats[$plan] = $count;
}

$recent_subs = mysqli_query($conn, "SELECT sh.*, s.salon_name FROM subscription_history sh JOIN salons s ON sh.salon_id = s.id ORDER BY sh.payment_date DESC LIMIT 5");
$salons = mysqli_query($conn, "SELECT * FROM salons ORDER BY created_at DESC");

$user_name = $_SESSION['user_name'];
include '../includes/header.php';
?>

<style>
    .super-container { display: flex; min-height: 100vh; }
    
    .sidebar {
        width: 280px;
        background: #050505;
        border-right: 2px solid #d4af37;
        padding: 2rem 1rem;
        flex-shrink: 0;
        transition: all 0.3s ease;
        position: sticky;
        top: 70px;
        height: calc(100vh - 70px);
        overflow-y: auto;
    }
    .sidebar-header { text-align: center; margin-bottom: 2rem; }
    .sidebar-header h3 { color: #d4af37; }
    .sidebar-menu { list-style: none; padding: 0; }
    .sidebar-menu li { margin-bottom: 0.5rem; }
    .sidebar-menu a {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        padding: 12px 20px;
        color: white;
        text-decoration: none;
        border-radius: 10px;
        transition: all 0.3s;
        font-size: 0.95rem;
    }
    .sidebar-menu a:hover, .sidebar-menu a.active {
        background: #d4af37;
        color: #050505;
    }
    
    .sidebar-toggle {
        display: none;
        background: #d4af37;
        color: #050505;
        border: none;
        padding: 10px 15px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 1rem;
        margin-bottom: 1rem;
        width: 100%;
    }
    .sidebar-toggle:hover { background: #f9e547; }

    .main-content { flex: 1; padding: 2rem; background: #0a0a0a; min-width: 0; }
    .main-content h1 { color: #d4af37; margin-bottom: 0.5rem; }
    .main-content p { color: #aaa; margin-bottom: 2rem; }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    .stat-card {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        text-align: center;
        border-left: 4px solid #d4af37;
    }
    .stat-number { font-size: 2rem; font-weight: bold; color: #d4af37; }
    .stat-label { color: #aaa; margin-top: 0.3rem; font-size: 0.9rem; }

    .plan-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    .plan-stat-card {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        text-align: center;
        border: 1px solid rgba(212, 175, 55, 0.2);
    }
    .plan-stat-card .plan-icon { font-size: 2rem; }
    .plan-stat-card .plan-count { font-size: 2rem; font-weight: bold; color: white; }
    .plan-stat-card .plan-label { color: #aaa; margin-top: 0.3rem; }
    .plan-stat-card.basic { border-color: #17a2b8; }
    .plan-stat-card.premium { border-color: #d4af37; }
    .plan-stat-card.enterprise { border-color: #28a745; }

    .salon-card {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        border: 1px solid rgba(212, 175, 55, 0.3);
        transition: all 0.3s;
    }
    .salon-card:hover { border-color: #d4af37; transform: translateY(-2px); }
    .salon-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }
    .salon-name { font-size: 1.2rem; font-weight: bold; color: #d4af37; }
    .plan-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: bold;
    }
    .plan-basic { background: rgba(23, 162, 184, 0.2); color: #17a2b8; border: 1px solid #17a2b8; }
    .plan-premium { background: rgba(212, 175, 55, 0.2); color: #d4af37; border: 1px solid #d4af37; }
    .plan-enterprise { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }

    .status-active { color: #28a745; font-weight: bold; }
    .status-inactive { color: #dc3545; font-weight: bold; }
    .status-suspended { color: #d4af37; font-weight: bold; }

    .btn-primary {
        background: #d4af37;
        color: #050505;
        border: none;
        padding: 8px 20px;
        border-radius: 25px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        font-size: 0.85rem;
        transition: all 0.3s;
    }
    .btn-primary:hover { background: #f9e547; transform: translateY(-2px); }

    .table-wrapper {
        overflow-x: auto;
        background: #1a1a1a;
        border-radius: 15px;
        padding: 0;
        margin-top: 1rem;
        border: 1px solid rgba(212, 175, 55, 0.2);
        -webkit-overflow-scrolling: touch;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
        min-width: 600px;
    }
    th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid rgba(212, 175, 55, 0.15);
    }
    th { color: #d4af37; font-weight: 600; }
    tr:hover { background: rgba(212, 175, 55, 0.05); }

    h2 { color: #d4af37; margin: 1.5rem 0 1rem 0; font-size: 1.3rem; }

    /* ============================================
       RESPONSIVE BREAKPOINTS
       ============================================ */
    @media (max-width: 1024px) {
        .sidebar { width: 240px; padding: 1.5rem 0.8rem; }
        .stats-grid { grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); }
        .stat-number { font-size: 1.7rem; }
        table { min-width: 500px; }
    }

    @media (max-width: 768px) {
        .super-container { flex-direction: column; }

        .sidebar {
            width: 100%;
            position: relative;
            top: 0;
            height: auto;
            border-right: none;
            border-bottom: 2px solid #d4af37;
            padding: 1rem;
            display: none;
        }
        .sidebar.open { display: block; }
        .sidebar-toggle { display: block; }

        .main-content { padding: 1rem; }
        .main-content h1 { font-size: 1.5rem; }

        .stats-grid {
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .stat-card { padding: 1rem; }
        .stat-number { font-size: 1.5rem; }

        .plan-stats-grid {
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
        }
        .plan-stat-card { padding: 1rem; }
        .plan-stat-card .plan-count { font-size: 1.5rem; }

        .salon-card { padding: 1rem; }
        .salon-header { flex-direction: column; align-items: flex-start; gap: 0.5rem; }

        table { min-width: 450px; font-size: 0.8rem; }
        th, td { padding: 8px; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .main-content h1 { font-size: 1.2rem; }

        .stats-grid { grid-template-columns: 1fr; }
        .plan-stats-grid { grid-template-columns: 1fr; }

        .salon-card { padding: 0.8rem; }
        .salon-name { font-size: 1rem; }

        table { min-width: 350px; font-size: 0.7rem; }
        th, td { padding: 6px; }
    }
</style>

<div class="super-container">
    <aside class="sidebar" id="sidebar">
        <button class="sidebar-toggle" id="sidebarToggle">✕ Close Menu</button>
        <div class="sidebar-header">
            <h3>👑 FORTUNA</h3>
            <p>Super Administrator</p>
            <small style="color:#888;">System Owner</small>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="active">📊 Dashboard</a></li>
            <li><a href="salons.php">🏢 Salons</a></li>
            <li><a href="admins.php">👨‍💼 Owners</a></li>
            <li><a href="subscriptions.php">💰 Subscriptions</a></li>
            <li><a href="settings.php">⚙️ Settings</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <button class="sidebar-toggle" id="sidebarOpen" style="display:none; margin-bottom:1rem;">☰ Menu</button>

        <h1>👑 Super Admin Dashboard</h1>
        <p>Welcome back, <strong><?php echo htmlspecialchars($user_name); ?></strong>! You have full control over all salons.</p>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-number"><?php echo $total_salons; ?></div><div class="stat-label">🏢 Total Salons</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $active_salons; ?></div><div class="stat-label">✅ Active Salons</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $total_admins; ?></div><div class="stat-label">👨‍💼 Salon Owners</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $total_staff; ?></div><div class="stat-label">👥 Total Staff</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $total_customers; ?></div><div class="stat-label">👤 Total Customers</div></div>
            <div class="stat-card"><div class="stat-number">KSh <?php echo number_format($total_revenue, 2); ?></div><div class="stat-label">💰 Total Revenue</div></div>
        </div>

        <h2>📊 Plan Distribution</h2>
        <div class="plan-stats-grid">
            <div class="plan-stat-card basic">
                <div class="plan-icon">📘</div>
                <div class="plan-count"><?php echo $plan_stats['basic']; ?></div>
                <div class="plan-label">Basic Plan</div>
            </div>
            <div class="plan-stat-card premium">
                <div class="plan-icon">📗</div>
                <div class="plan-count"><?php echo $plan_stats['premium']; ?></div>
                <div class="plan-label">Premium Plan</div>
            </div>
            <div class="plan-stat-card enterprise">
                <div class="plan-icon">📕</div>
                <div class="plan-count"><?php echo $plan_stats['enterprise']; ?></div>
                <div class="plan-label">Enterprise Plan</div>
            </div>
        </div>

        <h2>🏢 All Salons</h2>
        <?php while($salon = mysqli_fetch_assoc($salons)): ?>
        <div class="salon-card">
            <div class="salon-header">
                <div>
                    <span class="salon-name">🏢 <?php echo htmlspecialchars($salon['salon_name']); ?></span>
                    <span class="plan-badge plan-<?php echo $salon['subscription_plan']; ?>">
                        <?php echo ucfirst($salon['subscription_plan']); ?>
                    </span>
                    <span class="status-<?php echo $salon['subscription_status']; ?>">
                        <?php echo ucfirst($salon['subscription_status']); ?>
                    </span>
                </div>
                <div>
                    <a href="view_salon.php?id=<?php echo $salon['id']; ?>" class="btn-primary">👁️ View</a>
                    <a href="edit_salon.php?id=<?php echo $salon['id']; ?>" class="btn-primary">✏️ Edit</a>
                </div>
            </div>
            <div style="display: flex; gap: 2rem; flex-wrap: wrap; margin-top: 0.5rem;">
                <div>📧 <?php echo htmlspecialchars($salon['salon_email']); ?></div>
                <div>📞 <?php echo htmlspecialchars($salon['salon_phone']); ?></div>
                <div>📍 <?php echo htmlspecialchars($salon['salon_address']); ?></div>
                <div>📅 Since: <?php echo date('M d, Y', strtotime($salon['created_at'])); ?></div>
            </div>
        </div>
        <?php endwhile; ?>

        <h2 style="margin-top: 2rem;">💰 Recent Subscriptions</h2>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Salon</th><th>Plan</th><th>Amount</th><th>Payment Date</th><th>Expiry</th></tr>
                </thead>
                <tbody>
                    <?php while($sub = mysqli_fetch_assoc($recent_subs)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($sub['salon_name']); ?></td>
                        <td><?php echo ucfirst($sub['plan']); ?></td>
                        <td>KSh <?php echo number_format($sub['amount'], 2); ?></td>
                        <td><?php echo date('M d, Y', strtotime($sub['payment_date'])); ?></td>
                        <td><?php echo date('M d, Y', strtotime($sub['expiry_date'])); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const sidebarOpen = document.getElementById('sidebarOpen');
        const sidebarToggle = document.getElementById('sidebarToggle');

        function isMobile() { return window.innerWidth <= 768; }

        function handleSidebar() {
            if (isMobile()) {
                sidebar.classList.remove('open');
                sidebarOpen.style.display = 'block';
                sidebarToggle.style.display = 'block';
            } else {
                sidebar.classList.add('open');
                sidebarOpen.style.display = 'none';
                sidebarToggle.style.display = 'none';
            }
        }

        if (sidebarOpen) {
            sidebarOpen.addEventListener('click', function() {
                sidebar.classList.add('open');
            });
        }
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.remove('open');
            });
        }

        document.addEventListener('click', function(event) {
            if (isMobile() && sidebar.classList.contains('open')) {
                if (!sidebar.contains(event.target) && event.target !== sidebarOpen) {
                    sidebar.classList.remove('open');
                }
            }
        });

        window.addEventListener('resize', handleSidebar);
        handleSidebar();
    });
</script>

<?php include '../includes/footer.php'; ?>