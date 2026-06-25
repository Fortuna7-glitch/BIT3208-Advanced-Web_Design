<?php
// admin/dashboard.php - RESPONSIVE REWRITE
require_once '../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$salon_id = $_SESSION['salon_id'] ?? 0;
if ($salon_id <= 0) {
    $user_id = $_SESSION['user_id'];
    $user_query = mysqli_query($conn, "SELECT salon_id FROM users WHERE id = $user_id");
    if ($user_result = mysqli_fetch_assoc($user_query)) {
        $salon_id = $user_result['salon_id'];
        $_SESSION['salon_id'] = $salon_id;
    }
}

$salon_query = mysqli_query($conn, "SELECT salon_name, subscription_plan FROM salons WHERE id = $salon_id");
$salon_data = mysqli_fetch_assoc($salon_query);
$current_plan = $salon_data['subscription_plan'] ?? 'basic';
$salon_name = $salon_data['salon_name'] ?? '';
$upgrade_info = getUpgradeMessage($current_plan);

// Stats
$total_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'customer' AND salon_id = $salon_id"))['count'] ?? 0;
$total_appointments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments WHERE salon_id = $salon_id"))['count'] ?? 0;
$total_revenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM payments WHERE payment_status = 'paid' AND salon_id = $salon_id"))['total'] ?? 0;
$pending_appointments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments WHERE (status = 'pending' OR status = 'confirmed') AND salon_id = $salon_id"))['count'] ?? 0;

$today = date('Y-m-d');
$today_appointments = mysqli_query($conn, "SELECT a.*, c.full_name as customer_name, s.service_name 
                                          FROM appointments a 
                                          JOIN users c ON a.customer_id = c.id 
                                          JOIN services s ON a.service_id = s.id 
                                          WHERE a.appointment_date = '$today' AND a.salon_id = $salon_id 
                                          ORDER BY a.appointment_time ASC");
$queue_query = mysqli_query($conn, "SELECT a.*, u.full_name as customer_name, s.service_name, st.full_name as staff_name 
                                    FROM appointments a 
                                    JOIN users u ON a.customer_id = u.id 
                                    JOIN services s ON a.service_id = s.id 
                                    LEFT JOIN users st ON a.staff_id = st.id 
                                    WHERE a.salon_id = $salon_id AND a.status NOT IN ('completed', 'cancelled', 'served') 
                                    ORDER BY a.appointment_date ASC, a.appointment_time ASC, a.queue_position ASC");

include '../includes/header.php';
?>

<style>
    /* ============================================
       RESPONSIVE DASHBOARD STYLES
       ============================================ */

    .dashboard-container {
        display: flex;
        min-height: 100vh;
    }

    /* ========== SIDEBAR ========== */
    .sidebar {
        width: 280px;
        background: #050505;
        border-right: 1px solid #d4af37;
        padding: 2rem 1rem;
        flex-shrink: 0;
        transition: all 0.3s ease;
        position: sticky;
        top: 70px;
        height: calc(100vh - 70px);
        overflow-y: auto;
    }
    .sidebar-header {
        text-align: center;
        margin-bottom: 2rem;
    }
    .sidebar-header h3 { color: #d4af37; }
    .sidebar-menu {
        list-style: none;
        padding: 0;
    }
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
    .sidebar-menu a:hover,
    .sidebar-menu a.active {
        background: #d4af37;
        color: #050505;
    }
    .plan-badge {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: bold;
        margin-top: 0.5rem;
    }
    .plan-basic { background: rgba(23, 162, 184, 0.2); color: #17a2b8; border: 1px solid #17a2b8; }
    .plan-premium { background: rgba(212, 175, 55, 0.2); color: #d4af37; border: 1px solid #d4af37; }
    .plan-enterprise { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }

    /* Mobile sidebar toggle */
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

    /* ========== MAIN CONTENT ========== */
    .main-content {
        flex: 1;
        padding: 2rem;
        background: #0a0a0a;
        min-width: 0; /* Prevent overflow */
    }
    .main-content h1 { color: #d4af37; margin-bottom: 0.5rem; }

    /* ========== STATS GRID ========== */
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
        text-align: center;
        border-left: 4px solid #d4af37;
    }
    .stat-number { font-size: 2.5rem; font-weight: bold; color: #d4af37; }
    .stat-label { color: #aaa; margin-top: 0.3rem; font-size: 0.9rem; }

    /* ========== QUEUE ========== */
    .queue-container {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }
    .queue-item {
        background: #2a2a2a;
        border-radius: 10px;
        padding: 1rem;
        margin-bottom: 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .queue-number {
        background: #d4af37;
        color: #050505;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        margin-right: 1rem;
    }
    .btn-serve {
        background: #28a745;
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 25px;
        cursor: pointer;
        white-space: nowrap;
    }
    .btn-serve:hover { background: #218838; }

    /* ========== TABLES ========== */
    .table-wrapper {
        overflow-x: auto;
        background: #1a1a1a;
        border-radius: 15px;
        padding: 0;
        margin-top: 1rem;
        border: 1px solid rgba(212, 175, 55, 0.2);
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
        min-width: 500px;
    }
    th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid rgba(212, 175, 55, 0.15);
    }
    th { color: #d4af37; font-weight: 600; }
    tr:hover { background: rgba(212, 175, 55, 0.05); }

    .btn-outline {
        display: inline-block;
        padding: 5px 12px;
        border: 1px solid #d4af37;
        color: #d4af37;
        text-decoration: none;
        border-radius: 5px;
        font-size: 0.8rem;
    }
    .btn-outline:hover {
        background: #d4af37;
        color: #050505;
    }

    /* ========== UPGRADE BANNER ========== */
    .upgrade-banner {
        background: linear-gradient(135deg, #1a1a1a 0%, #2a1f0a 100%);
        border: 2px solid #d4af37;
        border-radius: 15px;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .upgrade-content { display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; }
    .upgrade-icon { font-size: 2.5rem; }
    .upgrade-text h4 { color: #d4af37; margin-bottom: 0.3rem; }
    .upgrade-text p { color: #aaa; font-size: 0.9rem; }
    .upgrade-text ul { list-style: none; padding: 0; display: flex; gap: 1rem; flex-wrap: wrap; }
    .upgrade-text ul li { color: #aaa; font-size: 0.8rem; }
    .upgrade-text ul li:before { content: "✓ "; color: #28a745; font-weight: bold; }
    .btn-upgrade {
        background: #d4af37;
        color: #050505;
        padding: 10px 25px;
        border-radius: 50px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s;
        white-space: nowrap;
    }
    .btn-upgrade:hover { background: #f9e547; transform: scale(1.05); }
    .btn-upgrade:disabled { background: #555; color: #888; cursor: not-allowed; transform: none; }

    /* ============================================
       RESPONSIVE BREAKPOINTS
       ============================================ */

    /* Tablet & Small Desktop */
    @media (max-width: 1024px) {
        .sidebar { width: 240px; padding: 1.5rem 0.8rem; }
        .stats-grid { grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
        .stat-number { font-size: 2rem; }
    }

    /* Mobile */
    @media (max-width: 768px) {
        .dashboard-container { flex-direction: column; }

        .sidebar {
            width: 100%;
            position: relative;
            top: 0;
            height: auto;
            border-right: none;
            border-bottom: 1px solid #d4af37;
            padding: 1rem;
            display: none; /* Hidden by default on mobile */
        }
        .sidebar.open {
            display: block; /* Show when toggled */
        }
        .sidebar-toggle {
            display: block;
        }

        .main-content { padding: 1rem; }
        .main-content h1 { font-size: 1.5rem; }

        .stats-grid {
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .stat-card { padding: 1rem; }
        .stat-number { font-size: 1.5rem; }

        .upgrade-banner { flex-direction: column; text-align: center; }
        .upgrade-content { flex-direction: column; align-items: center; }
        .upgrade-text ul { justify-content: center; }

        .queue-item { flex-direction: column; text-align: center; }
    }

    /* Small Mobile */
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr; }
        .stat-card { padding: 0.8rem; }
        .stat-number { font-size: 1.3rem; }

        .main-content { padding: 0.8rem; }
        .main-content h1 { font-size: 1.2rem; }

        table { font-size: 0.75rem; min-width: 400px; }
        th, td { padding: 8px; }

        .queue-item { padding: 0.8rem; }
        .queue-number { width: 30px; height: 30px; font-size: 0.8rem; }
    }
</style>

<div class="dashboard-container">

    <!-- ========== SIDEBAR ========== -->
    <aside class="sidebar" id="sidebar">
        <button class="sidebar-toggle" id="sidebarToggle">✕ Close Menu</button>
        <div class="sidebar-header">
            <h3>👑 <?php echo htmlspecialchars($_SESSION['user_name']); ?></h3>
            <p>Salon Owner</p>
            <span class="plan-badge plan-<?php echo $current_plan; ?>">
                <?php echo ucfirst($current_plan); ?> Plan
            </span>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="active">📊 Dashboard</a></li>
            <li><a href="appointments.php">📅 Appointments</a></li>
            <li><a href="services.php">💇 Services</a></li>
            <li><a href="staff.php">👥 Staff</a></li>
            <li><a href="customers.php">👤 Customers</a></li>
            <?php if(hasFeature($salon_id, 'payments')): ?>
                <li><a href="payments.php">💰 Payments</a></li>
            <?php endif; ?>
            <?php if(hasFeature($salon_id, 'reports')): ?>
                <li><a href="reports.php">📈 Reports</a></li>
            <?php endif; ?>
            <?php if(hasFeature($salon_id, 'permissions')): ?>
                <li><a href="permissions.php">🔐 Permissions</a></li>
            <?php endif; ?>
            <li><a href="profile.php">⚙️ My Profile</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>

    <!-- ========== MAIN CONTENT ========== -->
    <main class="main-content">
        <button class="sidebar-toggle" id="sidebarOpen" style="display:none; margin-bottom:1rem;">☰ Menu</button>

        <h1>Admin Dashboard ✨</h1>

        <!-- Upgrade Banner -->
        <?php if($upgrade_info): ?>
        <div class="upgrade-banner">
            <div class="upgrade-content">
                <div class="upgrade-icon">💡</div>
                <div class="upgrade-text">
                    <h4><?php echo $upgrade_info['message']; ?></h4>
                    <p>Upgrade to unlock:</p>
                    <ul>
                        <?php foreach($upgrade_info['features'] as $feature): ?>
                            <li><?php echo $feature; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <a href="../super_admin/upgrade_plan.php?salon_id=<?php echo $salon_id; ?>&target=<?php echo $upgrade_info['target']; ?>" class="btn-upgrade">
                🔓 <?php echo $upgrade_info['button']; ?>
            </a>
        </div>
        <?php else: ?>
        <div class="upgrade-banner" style="border-color: #28a745;">
            <div class="upgrade-content">
                <div class="upgrade-icon">🏆</div>
                <div class="upgrade-text">
                    <h4>You're on the Enterprise plan!</h4>
                    <p>You have access to all features. Enjoy the full experience!</p>
                </div>
            </div>
            <button class="btn-upgrade" disabled>🌟 Max Plan</button>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-number"><?php echo $total_customers; ?></div><div class="stat-label">Total Customers</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $total_appointments; ?></div><div class="stat-label">Total Appointments</div></div>
            <div class="stat-card"><div class="stat-number">KSh <?php echo number_format($total_revenue, 2); ?></div><div class="stat-label">Total Revenue</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $pending_appointments; ?></div><div class="stat-label">Pending Appointments</div></div>
        </div>

        <!-- Queue -->
        <div class="queue-container">
            <h3>🚀 Current Queue</h3>
            <?php if($queue_query && mysqli_num_rows($queue_query) > 0): ?>
                <?php while($q = mysqli_fetch_assoc($queue_query)): ?>
                <div class="queue-item">
                    <div>
                        <span class="queue-number">#<?php echo $q['queue_position']; ?></span>
                        <strong><?php echo htmlspecialchars($q['customer_name']); ?></strong><br>
                        <small><?php echo htmlspecialchars($q['service_name']); ?></small><br>
                        <small><?php echo date('M d, Y', strtotime($q['appointment_date'])); ?> at <?php echo date('g:i A', strtotime($q['appointment_time'])); ?></small>
                    </div>
                    <form method="POST" action="appointments.php">
                        <input type="hidden" name="appointment_id" value="<?php echo $q['id']; ?>">
                        <input type="hidden" name="action" value="serve">
                        <button type="submit" class="btn-serve" onclick="return confirm('Mark this customer as served?')">✓ Mark as Served</button>
                    </form>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No customers in queue.</p>
            <?php endif; ?>
        </div>

        <!-- Today's Appointments -->
        <div>
            <h3>📅 Today's Appointments (<?php echo date('F d, Y'); ?>)</h3>
            <?php if($today_appointments && mysqli_num_rows($today_appointments) > 0): ?>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Customer</th><th>Service</th><th>Time</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php while($apt = mysqli_fetch_assoc($today_appointments)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($apt['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($apt['service_name']); ?></td>
                                <td><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></td>
                                <td><?php echo ucfirst($apt['status']); ?></td>
                                <td><a href="appointments.php" class="btn-outline">View</a></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No appointments scheduled for today.</p>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
    // ============================================
    // MOBILE SIDEBAR TOGGLE
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const sidebarOpen = document.getElementById('sidebarOpen');
        const sidebarToggle = document.getElementById('sidebarToggle');

        // Check if we're on mobile (screen width <= 768px)
        function isMobile() {
            return window.innerWidth <= 768;
        }

        // Show/hide sidebar based on screen size
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

        // Open sidebar
        if (sidebarOpen) {
            sidebarOpen.addEventListener('click', function() {
                sidebar.classList.add('open');
            });
        }

        // Close sidebar
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.remove('open');
            });
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (isMobile() && sidebar.classList.contains('open')) {
                if (!sidebar.contains(event.target) && event.target !== sidebarOpen) {
                    sidebar.classList.remove('open');
                }
            }
        });

        // Handle window resize
        window.addEventListener('resize', handleSidebar);

        // Initial setup
        handleSidebar();
    });
</script>

<?php include '../includes/footer.php'; ?>