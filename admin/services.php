<?php
// admin/services.php - RESPONSIVE REWRITE
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $service_name = mysqli_real_escape_string($conn, $_POST['service_name']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $price = mysqli_real_escape_string($conn, $_POST['price']);
            $duration = mysqli_real_escape_string($conn, $_POST['duration']);
            $query = "INSERT INTO services (service_name, description, price, duration_minutes, salon_id, is_active) VALUES ('$service_name', '$description', '$price', '$duration', $salon_id, 1)";
            mysqli_query($conn, $query);
            $success = "Service added successfully!";
        } elseif ($_POST['action'] == 'update') {
            $id = mysqli_real_escape_string($conn, $_POST['id']);
            $service_name = mysqli_real_escape_string($conn, $_POST['service_name']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $price = mysqli_real_escape_string($conn, $_POST['price']);
            $duration = mysqli_real_escape_string($conn, $_POST['duration']);
            $query = "UPDATE services SET service_name='$service_name', description='$description', price='$price', duration_minutes='$duration' WHERE id=$id AND salon_id=$salon_id";
            mysqli_query($conn, $query);
            $success = "Service updated successfully!";
        } elseif ($_POST['action'] == 'delete') {
            $id = mysqli_real_escape_string($conn, $_POST['id']);
            $query = "DELETE FROM services WHERE id=$id AND salon_id=$salon_id";
            mysqli_query($conn, $query);
            $success = "Service deleted successfully!";
        }
    }
}

$services = mysqli_query($conn, "SELECT * FROM services WHERE salon_id = $salon_id ORDER BY id DESC");

include '../includes/header.php';
?>

<style>
    .dashboard-container { display: flex; min-height: 100vh; }
    .sidebar { width: 280px; background: #050505; border-right: 1px solid #d4af37; padding: 2rem 1rem; flex-shrink: 0; }
    .sidebar-menu { list-style: none; padding: 0; }
    .sidebar-menu li { margin-bottom: 0.5rem; }
    .sidebar-menu a { display: flex; align-items: center; gap: 0.8rem; padding: 12px 20px; color: white; text-decoration: none; border-radius: 10px; transition: all 0.3s; }
    .sidebar-menu a:hover, .sidebar-menu a.active { background: #d4af37; color: #050505; }
    .main-content { flex: 1; padding: 2rem; background: #0a0a0a; min-width: 0; }
    h1 { color: #d4af37; margin-bottom: 1.5rem; }

    .form-card { background: #1a1a1a; border-radius: 15px; padding: 1.5rem; margin-bottom: 2rem; border: 1px solid rgba(212, 175, 55, 0.3); }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; color: #d4af37; font-weight: 500; }
    .form-control { width: 100%; padding: 10px; background: #2a2a2a; border: 1px solid rgba(212, 175, 55, 0.3); border-radius: 8px; color: white; }
    .btn-primary { background: #d4af37; color: #050505; border: none; padding: 10px 25px; border-radius: 25px; cursor: pointer; }

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

    .btn-small { padding: 5px 10px; font-size: 0.75rem; margin: 2px 0; }

    /* ============================================
       RESPONSIVE
       ============================================ */
    @media (max-width: 1024px) {
        table { min-width: 600px; font-size: 0.85rem; }
        th, td { padding: 10px; }
        td .form-control { width: 120px; }
    }

    @media (max-width: 768px) {
        .dashboard-container { flex-direction: column; }
        .sidebar { width: 100%; border-right: none; border-bottom: 1px solid #d4af37; padding: 1rem; display: none; }
        .sidebar.open { display: block; }
        .sidebar-toggle { display: block; }
        .main-content { padding: 1rem; }
        h1 { font-size: 1.5rem; }
        .form-grid { grid-template-columns: 1fr 1fr; }

        table { min-width: 500px; font-size: 0.8rem; }
        th, td { padding: 8px; white-space: nowrap; }
        td .form-control { width: 100px; }

        .action-cell { display: flex; flex-direction: column; gap: 5px; }
        .action-cell button { width: 100%; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        h1 { font-size: 1.2rem; }
        .form-grid { grid-template-columns: 1fr; }
        table { min-width: 400px; font-size: 0.7rem; }
        th, td { padding: 6px; }
        td .form-control { width: 80px; font-size: 0.7rem; padding: 6px; }
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

    .alert { padding: 12px; border-radius: 8px; margin-bottom: 1rem; }
    .alert-success { background: rgba(40, 167, 69, 0.2); border: 1px solid #28a745; color: #28a745; }
    .alert-danger { background: rgba(220, 53, 69, 0.2); border: 1px solid #dc3545; color: #dc3545; }
</style>

<div class="dashboard-container">
    <aside class="sidebar" id="sidebar">
        <button class="sidebar-toggle" id="sidebarToggle">✕ Close Menu</button>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="appointments.php">📅 Appointments</a></li>
            <li><a href="services.php" class="active">💇 Services</a></li>
            <li><a href="staff.php">👥 Staff</a></li>
            <li><a href="customers.php">👤 Customers</a></li>
            <li><a href="payments.php">💰 Payments</a></li>
            <li><a href="reports.php">📈 Reports</a></li>
            <li><a href="profile.php">⚙️ My Profile</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <button class="sidebar-toggle" id="sidebarOpen" style="display:none; margin-bottom:1rem;">☰ Menu</button>

        <h1>Service Management 💇</h1>

        <?php if(isset($success)): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>

        <div class="form-card">
            <h3 style="color:#d4af37;">➕ Add New Service</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Service Name</label>
                        <input type="text" name="service_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Price (KSh)</label>
                        <input type="number" name="price" class="form-control" required step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Duration (minutes)</label>
                        <input type="number" name="duration" class="form-control" required>
                    </div>
                </div>
                <button type="submit" class="btn-primary">➕ Add Service</button>
            </form>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Service Name</th>
                        <th>Description</th>
                        <th>Price (KSh)</th>
                        <th>Duration</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($service = mysqli_fetch_assoc($services)): ?>
                    <tr>
                        <form method="POST">
                            <input type="hidden" name="id" value="<?php echo $service['id']; ?>">
                            <td><?php echo $service['id']; ?></td>
                            <td><input type="text" name="service_name" value="<?php echo htmlspecialchars($service['service_name']); ?>" class="form-control" style="width:120px;"></td>
                            <td><input type="text" name="description" value="<?php echo htmlspecialchars($service['description']); ?>" class="form-control" style="width:150px;"></td>
                            <td><input type="number" name="price" value="<?php echo $service['price']; ?>" class="form-control" style="width:80px;" step="0.01"></td>
                            <td><input type="number" name="duration" value="<?php echo $service['duration_minutes']; ?>" class="form-control" style="width:70px;"></td>
                            <td class="action-cell">
                                <button type="submit" name="action" value="update" class="btn-primary btn-small">Update</button>
                                <button type="submit" name="action" value="delete" class="btn-primary btn-small" style="background:#dc3545;" onclick="return confirm('Delete this service?')">Delete</button>
                            </td>
                        </form>
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