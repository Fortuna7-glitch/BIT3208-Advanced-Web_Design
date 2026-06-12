<?php
// admin/customers.php
require_once '../config/database.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

// Handle customer status toggle
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $query = "UPDATE users SET is_active = NOT is_active WHERE id = $id AND role = 'customer'";
    mysqli_query($conn, $query);
    redirect('customers.php');
}

// Handle delete customer
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $query = "DELETE FROM users WHERE id = $id AND role = 'customer'";
    mysqli_query($conn, $query);
    redirect('customers.php');
}

$customers = mysqli_query($conn, "SELECT * FROM users WHERE role = 'customer' ORDER BY created_at DESC");

include '../includes/header.php';
?>

<div class="dashboard-container">
    <aside class="sidebar">
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="appointments.php">📅 Appointments</a></li>
            <li><a href="services.php">💇 Services</a></li>
            <li><a href="staff.php">👥 Staff</a></li>
            <li><a href="customers.php" class="active">👤 Customers</a></li>
            <li><a href="payments.php">💰 Payments</a></li>
            <li><a href="reports.php">📈 Reports</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <h1>Customer Management 👤</h1>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Registered</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($customer = mysqli_fetch_assoc($customers)): ?>
                    <tr>
                        <td><?php echo $customer['id']; ?></td>
                        <td><?php echo htmlspecialchars($customer['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                        <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                        <td>
                            <span style="color: <?php echo $customer['is_active'] ? '#28a745' : '#dc3545'; ?>">
                                <?php echo $customer['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <a href="?toggle_status=1&id=<?php echo $customer['id']; ?>" class="btn btn-outline" style="padding: 5px 10px;">
                                <?php echo $customer['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </a>
                            <a href="?delete=1&id=<?php echo $customer['id']; ?>" class="btn btn-outline" style="padding: 5px 10px; background: #dc3545; color: white;" onclick="return confirm('Delete this customer? All their appointments will also be deleted.')">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>