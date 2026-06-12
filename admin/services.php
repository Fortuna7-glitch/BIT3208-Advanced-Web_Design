<?php
// admin/services.php
require_once '../config/database.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $service_name = mysqli_real_escape_string($conn, $_POST['service_name']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $price = mysqli_real_escape_string($conn, $_POST['price']);
            $duration = mysqli_real_escape_string($conn, $_POST['duration']);
            
            $query = "INSERT INTO services (service_name, description, price, duration_minutes) 
                      VALUES ('$service_name', '$description', '$price', '$duration')";
            mysqli_query($conn, $query);
            
        } elseif ($_POST['action'] == 'update') {
            $id = mysqli_real_escape_string($conn, $_POST['id']);
            $service_name = mysqli_real_escape_string($conn, $_POST['service_name']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $price = mysqli_real_escape_string($conn, $_POST['price']);
            $duration = mysqli_real_escape_string($conn, $_POST['duration']);
            
            $query = "UPDATE services SET service_name='$service_name', description='$description', 
                      price='$price', duration_minutes='$duration' WHERE id=$id";
            mysqli_query($conn, $query);
            
        } elseif ($_POST['action'] == 'delete') {
            $id = mysqli_real_escape_string($conn, $_POST['id']);
            $query = "DELETE FROM services WHERE id=$id";
            mysqli_query($conn, $query);
        }
    }
}

$services = mysqli_query($conn, "SELECT * FROM services ORDER BY id DESC");

include '../includes/header.php';
?>

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
        <h1>Service Management ✨</h1>
        
        <!-- Add Service Form -->
        <div style="background: var(--gray); border-radius: 15px; padding: 1.5rem; margin-bottom: 2rem;">
            <h3 style="color: var(--gold);">➕ Add New Service</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <input type="text" name="service_name" placeholder="Service Name" class="form-control" required>
                    <input type="text" name="description" placeholder="Description" class="form-control">
                    <input type="number" name="price" placeholder="Price (KSh)" class="form-control" required step="0.01">
                    <input type="number" name="duration" placeholder="Duration (minutes)" class="form-control" required>
                    <button type="submit" class="btn btn-primary">Add Service</button>
                </div>
            </form>
        </div>
        
        <!-- Services List -->
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
                                <button type="submit" name="action" value="update" class="btn btn-outline" style="padding: 5px 10px;">Update</button>
                                <button type="submit" name="action" value="delete" class="btn btn-outline" style="padding: 5px 10px; background: #dc3545; color: white;" onclick="return confirm('Delete this service?')">Delete</button>
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