<?php
// super_admin/upgrade_plan.php - RESPONSIVE REWRITE
require_once '../config/database.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

$error = '';
$success = '';

$salon_id = isset($_GET['salon_id']) ? (int)$_GET['salon_id'] : 0;
$target_plan = isset($_GET['target']) ? $_GET['target'] : '';

if ($salon_id <= 0 || empty($target_plan)) {
    redirect('salons.php');
}

$salon_check = mysqli_query($conn, "SELECT id, salon_name, subscription_plan FROM salons WHERE id = $salon_id");
if (mysqli_num_rows($salon_check) == 0) {
    redirect('salons.php');
}
$salon = mysqli_fetch_assoc($salon_check);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_upgrade'])) {
    $plan = mysqli_real_escape_string($conn, $_POST['plan']);
    $amount = mysqli_real_escape_string($conn, $_POST['amount']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);

    $update = "UPDATE salons SET subscription_plan = '$plan' WHERE id = $salon_id";
    if (mysqli_query($conn, $update)) {
        $history = "INSERT INTO subscription_history (salon_id, plan, amount, payment_method, expiry_date) 
                    VALUES ($salon_id, '$plan', '$amount', '$payment_method', DATE_ADD(NOW(), INTERVAL 30 DAY))";
        mysqli_query($conn, $history);

        $success = "✅ {$salon['salon_name']} upgraded to <strong>" . ucfirst($plan) . "</strong> successfully!";
        $salon['subscription_plan'] = $plan;
    } else {
        $error = "Failed to upgrade: " . mysqli_error($conn);
    }
}

$plan_pricing = [
    'basic' => 0,
    'premium' => 10000,
    'enterprise' => 20000
];

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

    .upgrade-container {
        max-width: 700px;
        margin: 0 auto;
        padding: 2rem;
        background: #1a1a1a;
        border-radius: 15px;
        border: 1px solid rgba(212, 175, 55, 0.3);
    }
    .upgrade-container h1 {
        color: #d4af37;
        text-align: center;
        margin-bottom: 1.5rem;
    }
    .salon-info {
        background: #2a2a2a;
        padding: 1rem;
        border-radius: 10px;
        margin-bottom: 1.5rem;
        text-align: center;
    }
    .salon-info strong { color: #d4af37; }
    .current-plan-badge {
        display: inline-block;
        padding: 5px 15px;
        border-radius: 20px;
        background: rgba(212, 175, 55, 0.2);
        color: #d4af37;
        font-weight: bold;
    }

    .plan-comparison { margin: 1.5rem 0; }
    .plan-box {
        background: #2a2a2a;
        padding: 1rem;
        border-radius: 10px;
        margin-bottom: 1rem;
        border-left: 4px solid #d4af37;
    }
    .plan-box h3 { color: #d4af37; }
    .plan-box ul { list-style: none; padding: 0; margin-top: 0.5rem; }
    .plan-box ul li { padding: 0.3rem 0; color: #aaa; }
    .plan-box ul li:before { content: "✓ "; color: #28a745; }

    .form-group { margin-bottom: 1.5rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; color: #d4af37; font-weight: 500; }
    .form-control, select {
        width: 100%;
        padding: 10px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 8px;
        color: white;
        font-size: 1rem;
    }
    .btn-primary {
        background: #d4af37;
        color: #050505;
        border: none;
        padding: 12px 30px;
        border-radius: 25px;
        font-weight: 600;
        cursor: pointer;
        font-size: 1rem;
        width: 100%;
        transition: all 0.3s;
    }
    .btn-primary:hover { background: #f9e547; transform: translateY(-2px); }
    .btn-secondary {
        background: transparent;
        color: #d4af37;
        border: 1px solid #d4af37;
        padding: 10px 25px;
        border-radius: 25px;
        text-decoration: none;
        display: inline-block;
        margin-top: 1rem;
        transition: all 0.3s;
    }
    .btn-secondary:hover { background: rgba(212, 175, 55, 0.1); }

    .alert { padding: 15px; border-radius: 8px; margin-bottom: 1rem; }
    .alert-success { background: rgba(40, 167, 69, 0.2); border: 1px solid #28a745; color: #28a745; }
    .alert-danger { background: rgba(220, 53, 69, 0.2); border: 1px solid #dc3545; color: #dc3545; }
    .text-center { text-align: center; }

    /* ============================================
       RESPONSIVE
       ============================================ */
    @media (max-width: 1024px) {
        .sidebar { width: 240px; padding: 1.5rem 0.8rem; }
        .upgrade-container { padding: 1.5rem; }
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
        .upgrade-container { padding: 1rem; }
        .upgrade-container h1 { font-size: 1.5rem; }

        .plan-box { padding: 0.8rem; }
        .plan-box h3 { font-size: 1rem; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .upgrade-container { padding: 0.8rem; }
        .upgrade-container h1 { font-size: 1.2rem; }
        .form-control, select { padding: 8px; font-size: 0.9rem; }
        .btn-primary { padding: 10px 20px; font-size: 0.9rem; }
        .btn-secondary { padding: 8px 18px; font-size: 0.85rem; }
        .plan-box ul li { font-size: 0.85rem; }
    }
</style>

<div class="super-container">
    <aside class="sidebar" id="sidebar">
        <button class="sidebar-toggle" id="sidebarToggle">✕ Close Menu</button>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="salons.php">🏢 Salons</a></li>
            <li><a href="admins.php">👨‍💼 Owners</a></li>
            <li><a href="subscriptions.php">💰 Subscriptions</a></li>
            <li><a href="settings.php">⚙️ Settings</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <button class="sidebar-toggle" id="sidebarOpen" style="display:none; margin-bottom:1rem;">☰ Menu</button>

        <div class="upgrade-container">
            <h1>🔓 Upgrade Salon Plan</h1>

            <div class="salon-info">
                <strong>🏢 <?php echo htmlspecialchars($salon['salon_name']); ?></strong><br>
                Current Plan: <span class="current-plan-badge"><?php echo ucfirst($salon['subscription_plan']); ?></span>
            </div>

            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <div class="text-center">
                    <a href="salons.php" class="btn-secondary">← Back to Salons</a>
                </div>
            <?php else: ?>

            <?php if($error): ?>
                <div class="alert alert-danger">❌ <?php echo $error; ?></div>
            <?php endif; ?>

            <div class="plan-comparison">
                <h3 style="color: #d4af37;">📋 Plan Comparison</h3>

                <div class="plan-box">
                    <h3>🌟 Premium Plan</h3>
                    <ul>
                        <li>All Basic features</li>
                        <li>Reports & Analytics</li>
                        <li>Staff Permissions</li>
                        <li>Payment Tracking (already included)</li>
                    </ul>
                    <p style="color: #d4af37; font-size: 1.2rem; margin-top: 0.5rem;">
                        <strong>KSh <?php echo number_format($plan_pricing['premium'], 2); ?></strong> / month
                    </p>
                </div>

                <div class="plan-box" style="border-color: #28a745;">
                    <h3>👑 Enterprise Plan</h3>
                    <ul>
                        <li>All Basic & Premium features</li>
                        <li>Multi-Branch Support</li>
                        <li>Advanced Analytics</li>
                        <li>Priority Support</li>
                    </ul>
                    <p style="color: #d4af37; font-size: 1.2rem; margin-top: 0.5rem;">
                        <strong>KSh <?php echo number_format($plan_pricing['enterprise'], 2); ?></strong> / month
                    </p>
                </div>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label>Select Plan</label>
                    <select name="plan" class="form-control" required>
                        <option value="premium" <?php echo ($target_plan == 'premium') ? 'selected' : ''; ?>>Premium - KSh <?php echo number_format($plan_pricing['premium'], 2); ?></option>
                        <option value="enterprise" <?php echo ($target_plan == 'enterprise') ? 'selected' : ''; ?>>Enterprise - KSh <?php echo number_format($plan_pricing['enterprise'], 2); ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Amount (KSh)</label>
                    <input type="number" name="amount" class="form-control" value="<?php echo ($target_plan == 'enterprise') ? $plan_pricing['enterprise'] : $plan_pricing['premium']; ?>" required>
                </div>

                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method" class="form-control" required>
                        <option value="cash">💵 Cash</option>
                        <option value="mpesa">📱 M-PESA</option>
                    </select>
                </div>

                <button type="submit" name="confirm_upgrade" class="btn-primary">🔓 Confirm Upgrade</button>
            </form>

            <div class="text-center" style="margin-top: 1rem;">
                <a href="salons.php" class="btn-secondary">← Cancel & Go Back</a>
            </div>

            <?php endif; ?>
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