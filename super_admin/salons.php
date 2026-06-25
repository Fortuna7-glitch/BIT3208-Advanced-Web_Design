<?php
// super_admin/salons.php - RESPONSIVE REWRITE
require_once '../config/database.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

$plan_pricing = getPlanPricing();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_salon'])) {
    $salon_name = mysqli_real_escape_string($conn, $_POST['salon_name']);
    $salon_email = mysqli_real_escape_string($conn, $_POST['salon_email']);
    $salon_phone = mysqli_real_escape_string($conn, $_POST['salon_phone']);
    $salon_address = mysqli_real_escape_string($conn, $_POST['salon_address']);
    $subscription_plan = mysqli_real_escape_string($conn, $_POST['subscription_plan']);
    $owner_name = mysqli_real_escape_string($conn, $_POST['owner_name']);
    $owner_email = mysqli_real_escape_string($conn, $_POST['owner_email']);
    $owner_phone = mysqli_real_escape_string($conn, $_POST['owner_phone']);
    $owner_password = password_hash('owner123', PASSWORD_DEFAULT);

    mysqli_begin_transaction($conn);

    try {
        $salon_query = "INSERT INTO salons (salon_name, salon_email, salon_phone, salon_address, subscription_plan, subscription_status) 
                        VALUES ('$salon_name', '$salon_email', '$salon_phone', '$salon_address', '$subscription_plan', 'active')";
        mysqli_query($conn, $salon_query);
        $salon_id = mysqli_insert_id($conn);

        $owner_query = "INSERT INTO users (full_name, email, phone, password, role, salon_id, is_active) 
                        VALUES ('$owner_name', '$owner_email', '$owner_phone', '$owner_password', 'admin', $salon_id, 1)";
        mysqli_query($conn, $owner_query);

        mysqli_commit($conn);
        $success = "Salon and Owner created successfully! Owner password: owner123 | Plan: " . ucfirst($subscription_plan);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = "Failed to create salon: " . mysqli_error($conn);
    }
}

if (isset($_GET['update_status']) && isset($_GET['id']) && isset($_GET['status'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    mysqli_query($conn, "UPDATE salons SET subscription_status = '$status' WHERE id = $id");
    redirect('salons.php');
}

if (isset($_GET['update_plan']) && isset($_GET['id']) && isset($_GET['plan'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $plan = mysqli_real_escape_string($conn, $_GET['plan']);
    mysqli_query($conn, "UPDATE salons SET subscription_plan = '$plan' WHERE id = $id");
    redirect('salons.php');
}

if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    mysqli_query($conn, "DELETE FROM appointments WHERE salon_id = $id");
    mysqli_query($conn, "DELETE FROM products WHERE salon_id = $id");
    mysqli_query($conn, "DELETE FROM services WHERE salon_id = $id");
    mysqli_query($conn, "DELETE FROM users WHERE salon_id = $id");
    mysqli_query($conn, "DELETE FROM subscription_history WHERE salon_id = $id");
    mysqli_query($conn, "DELETE FROM salons WHERE id = $id");
    redirect('salons.php');
}

$salons = mysqli_query($conn, "SELECT * FROM salons ORDER BY created_at DESC");

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
    h1 { color: #d4af37; margin-bottom: 2rem; }

    .form-card { background: #1a1a1a; border-radius: 15px; padding: 1.5rem; margin-bottom: 2rem; border: 1px solid rgba(212, 175, 55, 0.3); }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; color: #d4af37; font-weight: 500; }
    .form-control, select { width: 100%; padding: 10px; background: #2a2a2a; border: 1px solid rgba(212, 175, 55, 0.3); border-radius: 8px; color: white; }
    .btn-primary {
        background: #d4af37;
        color: #050505;
        border: none;
        padding: 10px 25px;
        border-radius: 25px;
        cursor: pointer;
        transition: all 0.3s;
    }
    .btn-primary:hover { background: #f9e547; transform: translateY(-2px); }
    .btn-danger { background: #dc3545; color: white; border: none; padding: 5px 12px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.75rem; }
    .btn-success { background: #28a745; color: white; border: none; padding: 5px 12px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.75rem; }
    .btn-warning { background: #d4af37; color: #050505; border: none; padding: 5px 12px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.75rem; }

    .table-wrapper {
        overflow-x: auto;
        background: #1a1a1a;
        border-radius: 15px;
        padding: 0;
        border: 1px solid rgba(212, 175, 55, 0.2);
        -webkit-overflow-scrolling: touch;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
        min-width: 700px;
    }
    th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid rgba(212, 175, 55, 0.15);
        white-space: nowrap;
    }
    th { color: #d4af37; font-weight: 600; }
    tr:hover { background: rgba(212, 175, 55, 0.05); }

    .status-active { color: #28a745; font-weight: bold; }
    .status-inactive { color: #dc3545; font-weight: bold; }
    .status-suspended { color: #d4af37; font-weight: bold; }

    .plan-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: bold;
    }
    .plan-basic { background: rgba(23, 162, 184, 0.2); color: #17a2b8; border: 1px solid #17a2b8; }
    .plan-premium { background: rgba(212, 175, 55, 0.2); color: #d4af37; border: 1px solid #d4af37; }
    .plan-enterprise { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }

    .alert { padding: 15px; border-radius: 8px; margin-bottom: 1rem; }
    .alert-success { background: rgba(40, 167, 69, 0.2); border: 1px solid #28a745; color: #28a745; }
    .alert-danger { background: rgba(220, 53, 69, 0.2); border: 1px solid #dc3545; color: #dc3545; }
    .alert-info { background: rgba(212, 175, 55, 0.2); border: 1px solid #d4af37; color: #d4af37; }

    .section-title { margin: 1.5rem 0 1rem 0; color: #d4af37; border-left: 3px solid #d4af37; padding-left: 1rem; }
    .action-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }

    /* ============================================
       RESPONSIVE
       ============================================ */
    @media (max-width: 1024px) {
        .sidebar { width: 240px; padding: 1.5rem 0.8rem; }
        table { min-width: 600px; font-size: 0.85rem; }
        th, td { padding: 10px; }
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
        h1 { font-size: 1.5rem; }

        .form-grid { grid-template-columns: 1fr; }

        table { min-width: 500px; font-size: 0.8rem; }
        th, td { padding: 8px; white-space: nowrap; }

        .action-buttons { flex-direction: column; }
        .action-buttons .btn-primary,
        .action-buttons .btn-danger,
        .action-buttons .btn-success,
        .action-buttons .btn-warning {
            width: 100%;
            text-align: center;
        }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        h1 { font-size: 1.2rem; }

        table { min-width: 400px; font-size: 0.7rem; }
        th, td { padding: 6px; }
    }
</style>

<div class="super-container">
    <aside class="sidebar" id="sidebar">
        <button class="sidebar-toggle" id="sidebarToggle">✕ Close Menu</button>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="salons.php" class="active">🏢 Manage Salons</a></li>
            <li><a href="admins.php">👨‍💼 Salon Owners</a></li>
            <li><a href="subscriptions.php">💰 Subscriptions</a></li>
            <li><a href="settings.php">⚙️ System Settings</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <button class="sidebar-toggle" id="sidebarOpen" style="display:none; margin-bottom:1rem;">☰ Menu</button>

        <h1>🏢 Manage Salons</h1>

        <?php if(isset($success)): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if(isset($error)): ?>
            <div class="alert alert-danger">❌ <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="form-card">
            <h3 style="color:#d4af37;">➕ Create New Salon & Owner</h3>
            <form method="POST">
                <div class="section-title">Salon Information</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Salon Name</label>
                        <input type="text" name="salon_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Salon Email</label>
                        <input type="email" name="salon_email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Salon Phone</label>
                        <input type="tel" name="salon_phone" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Subscription Plan</label>
                        <select name="subscription_plan" class="form-control" required>
                            <option value="basic">Basic - KSh <?php echo number_format($plan_pricing['basic'], 2); ?>/month</option>
                            <option value="premium">Premium - KSh <?php echo number_format($plan_pricing['premium'], 2); ?>/month</option>
                            <option value="enterprise">Enterprise - KSh <?php echo number_format($plan_pricing['enterprise'], 2); ?>/month</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Address</label>
                        <textarea name="salon_address" class="form-control" rows="2"></textarea>
                    </div>
                </div>

                <div class="section-title">Salon Owner Information</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Owner Full Name</label>
                        <input type="text" name="owner_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Owner Email</label>
                        <input type="email" name="owner_email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Owner Phone</label>
                        <input type="tel" name="owner_phone" class="form-control" required>
                    </div>
                </div>
                <div class="alert-info" style="padding: 10px; margin-top: 1rem; border-radius: 8px;">
                    🔑 Default password for owner: <strong>owner123</strong> (they can change after first login)
                </div>
                <button type="submit" name="add_salon" class="btn-primary" style="margin-top: 1rem;">➕ Create Salon & Owner</button>
            </form>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Salon Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($salon = mysqli_fetch_assoc($salons)): ?>
                    <tr>
                        <td><?php echo $salon['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($salon['salon_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($salon['salon_email']); ?></td>
                        <td><?php echo htmlspecialchars($salon['salon_phone']); ?></td>
                        <td>
                            <span class="plan-badge plan-<?php echo $salon['subscription_plan']; ?>">
                                <?php echo ucfirst($salon['subscription_plan']); ?>
                            </span>
                        </td>
                        <td class="status-<?php echo $salon['subscription_status']; ?>">
                            <?php echo ucfirst($salon['subscription_status']); ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($salon['created_at'])); ?></td>
                        <td class="action-buttons">
                            <a href="view_salon.php?id=<?php echo $salon['id']; ?>" class="btn-primary" style="padding: 5px 12px;">👁️</a>
                            <a href="edit_salon.php?id=<?php echo $salon['id']; ?>" class="btn-primary" style="padding: 5px 12px;">✏️</a>
                            <?php if($salon['subscription_plan'] == 'basic'): ?>
                                <a href="?update_plan=1&id=<?php echo $salon['id']; ?>&plan=premium" class="btn-success" style="padding: 5px 12px;" onclick="return confirm('Upgrade this salon to Premium?')">⬆️ Premium</a>
                            <?php elseif($salon['subscription_plan'] == 'premium'): ?>
                                <a href="?update_plan=1&id=<?php echo $salon['id']; ?>&plan=enterprise" class="btn-success" style="padding: 5px 12px;" onclick="return confirm('Upgrade this salon to Enterprise?')">⬆️ Enterprise</a>
                            <?php endif; ?>
                            <?php if($salon['subscription_status'] == 'active'): ?>
                                <a href="?update_status=1&id=<?php echo $salon['id']; ?>&status=suspended" class="btn-warning" style="padding: 5px 12px;">⏸️</a>
                            <?php elseif($salon['subscription_status'] == 'suspended'): ?>
                                <a href="?update_status=1&id=<?php echo $salon['id']; ?>&status=active" class="btn-success" style="padding: 5px 12px;">▶️</a>
                            <?php endif; ?>
                            <a href="?delete=<?php echo $salon['id']; ?>" class="btn-danger" style="padding: 5px 12px;" onclick="return confirm('Delete this salon and ALL its data? Cannot be undone!')">🗑️</a>
                        </td>
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