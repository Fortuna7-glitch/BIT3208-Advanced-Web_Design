<?php
// super_admin/subscriptions.php - RESPONSIVE REWRITE
require_once '../config/database.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_subscription'])) {
    $salon_id = mysqli_real_escape_string($conn, $_POST['salon_id']);
    $plan = mysqli_real_escape_string($conn, $_POST['plan']);
    $amount = mysqli_real_escape_string($conn, $_POST['amount']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $expiry_date = mysqli_real_escape_string($conn, $_POST['expiry_date']);

    $query = "INSERT INTO subscription_history (salon_id, plan, amount, payment_method, expiry_date) VALUES ('$salon_id', '$plan', '$amount', '$payment_method', '$expiry_date')";
    if (mysqli_query($conn, $query)) {
        mysqli_query($conn, "UPDATE salons SET subscription_plan = '$plan', subscription_expiry = '$expiry_date' WHERE id = $salon_id");
        $message = "<div class='alert alert-success'>✅ Subscription added successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger'>❌ Failed to add subscription: " . mysqli_error($conn) . "</div>";
    }
}

if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    mysqli_query($conn, "DELETE FROM subscription_history WHERE id = $id");
    redirect('subscriptions.php');
}

$subscriptions = mysqli_query($conn, "SELECT sh.*, s.salon_name FROM subscription_history sh JOIN salons s ON sh.salon_id = s.id ORDER BY sh.payment_date DESC");
$salons = mysqli_query($conn, "SELECT id, salon_name, subscription_plan FROM salons ORDER BY salon_name");

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
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; }
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
    .btn-danger {
        background: #dc3545;
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: 5px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        font-size: 0.75rem;
    }
    .btn-danger:hover { background: #c82333; }

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

    .alert { padding: 15px; border-radius: 8px; margin-bottom: 1rem; }
    .alert-success { background: rgba(40, 167, 69, 0.2); border: 1px solid #28a745; color: #28a745; }
    .alert-danger { background: rgba(220, 53, 69, 0.2); border: 1px solid #dc3545; color: #dc3545; }

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
        .action-buttons .btn-danger { width: 100%; text-align: center; }
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
            <li><a href="salons.php">🏢 Salons</a></li>
            <li><a href="admins.php">👨‍💼 Owners</a></li>
            <li><a href="subscriptions.php" class="active">💰 Subscriptions</a></li>
            <li><a href="settings.php">⚙️ Settings</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <button class="sidebar-toggle" id="sidebarOpen" style="display:none; margin-bottom:1rem;">☰ Menu</button>

        <h1>💰 Subscription Management</h1>

        <?php echo $message; ?>

        <div class="form-card">
            <h3 style="color:#d4af37;">➕ Add Subscription Payment</h3>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Select Salon</label>
                        <select name="salon_id" class="form-control" required>
                            <option value="">-- Choose Salon --</option>
                            <?php while($salon = mysqli_fetch_assoc($salons)): ?>
                                <option value="<?php echo $salon['id']; ?>">
                                    <?php echo htmlspecialchars($salon['salon_name']); ?> (Current: <?php echo ucfirst($salon['subscription_plan']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Plan</label>
                        <select name="plan" class="form-control" required>
                            <option value="basic">Basic</option>
                            <option value="premium">Premium</option>
                            <option value="enterprise">Enterprise</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount (KSh)</label>
                        <input type="number" name="amount" class="form-control" required step="0.01" placeholder="e.g., 10000">
                    </div>
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="payment_method" class="form-control" required>
                            <option value="cash">💵 Cash</option>
                            <option value="mpesa">📱 M-PESA</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control" required>
                    </div>
                </div>
                <button type="submit" name="add_subscription" class="btn-primary">💾 Record Payment</button>
            </form>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Salon</th>
                        <th>Plan</th>
                        <th>Amount</th>
                        <th>Payment Method</th>
                        <th>Payment Date</th>
                        <th>Expiry Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($sub = mysqli_fetch_assoc($subscriptions)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($sub['salon_name']); ?></td>
                        <td><?php echo ucfirst($sub['plan']); ?></td>
                        <td>KSh <?php echo number_format($sub['amount'], 2); ?></td>
                        <td><?php echo strtoupper($sub['payment_method']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($sub['payment_date'])); ?></td>
                        <td><?php echo date('M d, Y', strtotime($sub['expiry_date'])); ?></td>
                        <td>
                            <a href="?delete=<?php echo $sub['id']; ?>" class="btn-danger" onclick="return confirm('Delete this subscription record?')">🗑️</a>
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