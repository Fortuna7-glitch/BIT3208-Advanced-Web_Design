<?php
// admin/services.php - FIXED with salon_id filtering
require_once '../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$salon_id = $_SESSION['salon_id'] ?? 0;

// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $service_name = mysqli_real_escape_string($conn, $_POST['service_name']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $price = mysqli_real_escape_string($conn, $_POST['price']);
            $duration = mysqli_real_escape_string($conn, $_POST['duration']);
            
            // Auto-assign salon_id from session
            $query = "INSERT INTO services (service_name, description, price, duration_minutes, salon_id, is_active) 
                      VALUES ('$service_name', '$description', '$price', '$duration', $salon_id, 1)";
            mysqli_query($conn, $query);
            
        } elseif ($_POST['action'] == 'update') {
            $id = mysqli_real_escape_string($conn, $_POST['id']);
            $service_name = mysqli_real_escape_string($conn, $_POST['service_name']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $price = mysqli_real_escape_string($conn, $_POST['price']);
            $duration = mysqli_real_escape_string($conn, $_POST['duration']);
            
            $query = "UPDATE services SET 
                      service_name='$service_name', 
                      description='$description', 
                      price='$price', 
                      duration_minutes='$duration' 
                      WHERE id=$id AND salon_id = $salon_id";
            mysqli_query($conn, $query);
            
        } elseif ($_POST['action'] == 'delete') {
            $id = mysqli_real_escape_string($conn, $_POST['id']);
            $query = "DELETE FROM services WHERE id=$id AND salon_id = $salon_id";
            mysqli_query($conn, $query);
        }
    }
}

$services = mysqli_query($conn, "SELECT * FROM services WHERE salon_id = $salon_id ORDER BY id DESC");

include '../includes/header.php';
?>

<style>
    .dashboard-container { display: flex; min-height: 100vh; }
    .sidebar { width: 280px; background: #050505; border-right: 1px solid #d4af37; padding: 2rem 1rem; }
    .sidebar-menu { list-style: none; padding: 0; }
    .sidebar-menu li { margin-bottom: 0.5rem; }
    .sidebar-menu a { display: block; padding: 12px 20px; color: white; text-decoration: none; border-radius: 10px; }
    .sidebar-menu a:hover, .sidebar-menu a.active { background: #d4af37; color: #050505; }
    .main-content { flex: 1; padding: 2rem; background: #0a0a0a; }
    .form-card { background: #1a1a1a; border-radius: 15px; padding: 1.5rem; margin-bottom: 2rem; border: 1px solid rgba(212, 175, 55, 0.3); }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; color: #d4af37; font-weight: 500; }
    .form-control { width: 100%; padding: 10px; background: #2a2a2a; border: 1px solid rgba(212, 175, 55, 0.3); border-radius: 8px; color: white; }
    .btn-primary { background: #d4af37; color: #050505; border: none; padding: 10px 25px; border-radius: 25px; cursor: pointer; }
    .table-container { overflow-x: auto; background: #1a1a1a; border-radius: 15px; padding: 1rem; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid rgba(212, 175, 55, 0.2); }
    th { color: #d4af37; }
    h1 { color: #d4af37; }
    .btn-small { padding: 5px 10px; font-size: 0.75rem; margin: 0 2px; }
    @media (max-width: 768px) { .dashboard-container { flex-direction: column; } .sidebar { width: 100%; } }
</style>

<div class="dashboard-container">
    <aside class="sidebar">
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="appointments.php">📅 Appointments</a></li>
            <li><a href="services.php" class="active">💇 Services</a></li>
            <li><a href="staff.php">👥 Staff</a></li>
            <li><a href="customers.php">👤 Customers</a></li>
            <li><a href="payments.php">💰 Payments</a></li>
            <li><a href="reports.php">📈 Reports</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <h1>Service Management 💇</h1>
        
        <div class="form-card">
            <h3 style="color: #d4af37;">➕ Add New Service</h3>
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
        
        <div class="table-container">
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
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="id" value="<?php echo $service['id']; ?>">
                            <td><?php echo $service['id']; ?></td>
                            <td><input type="text" name="service_name" value="<?php echo htmlspecialchars($service['service_name']); ?>" class="form-control" style="width: 150px;"></td>
                            <td><input type="text" name="description" value="<?php echo htmlspecialchars($service['description']); ?>" class="form-control" style="width: 200px;"></td>
                            <td><input type="number" name="price" value="<?php echo $service['price']; ?>" class="form-control" style="width: 100px;" step="0.01"></td>
                            <td><input type="number" name="duration" value="<?php echo $service['duration_minutes']; ?>" class="form-control" style="width: 80px;"></td>
                            <td>
                                <button type="submit" name="action" value="update" class="btn-primary btn-small">Update</button>
                                <button type="submit" name="action" value="delete" class="btn-primary btn-small" style="background: #dc3545;" onclick="return confirm('Delete this service?')">Delete</button>
                            </td>
                        </form>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>