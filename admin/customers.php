<?php
// admin/customers.php - COMPLETE REWRITE with working Deactivate/Activate/Delete
require_once '../config/database.php';

// ============================================
// AUTHENTICATION CHECK
// ============================================
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

// ============================================
// GET SALON_ID DIRECTLY FROM DATABASE
// ============================================
$user_id = $_SESSION['user_id'];
$user_query = mysqli_query($conn, "SELECT salon_id FROM users WHERE id = $user_id");
if ($user_result = mysqli_fetch_assoc($user_query)) {
    $salon_id = $user_result['salon_id'];
    $_SESSION['salon_id'] = $salon_id;
} else {
    $salon_id = 0;
}

// ============================================
// HANDLE DEACTIVATE CUSTOMER
// ============================================
if (isset($_GET['deactivate']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    $check = mysqli_query($conn, "SELECT id FROM users WHERE id = $id AND salon_id = $salon_id AND role = 'customer'");
    if (mysqli_num_rows($check) == 1) {
        $query = "UPDATE users SET is_active = 0 WHERE id = $id AND role = 'customer' AND salon_id = $salon_id";
        if (mysqli_query($conn, $query)) {
            $success = "Customer deactivated successfully!";
        } else {
            $error = "Database error: " . mysqli_error($conn);
        }
    } else {
        $error = "Customer not found or does not belong to your salon.";
    }
}

// ============================================
// HANDLE ACTIVATE CUSTOMER
// ============================================
if (isset($_GET['activate']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    $check = mysqli_query($conn, "SELECT id FROM users WHERE id = $id AND salon_id = $salon_id AND role = 'customer'");
    if (mysqli_num_rows($check) == 1) {
        $query = "UPDATE users SET is_active = 1 WHERE id = $id AND role = 'customer' AND salon_id = $salon_id";
        if (mysqli_query($conn, $query)) {
            $success = "Customer activated successfully!";
        } else {
            $error = "Database error: " . mysqli_error($conn);
        }
    } else {
        $error = "Customer not found or does not belong to your salon.";
    }
}

// ============================================
// HANDLE DELETE CUSTOMER (PERMANENT)
// ============================================
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    $check = mysqli_query($conn, "SELECT id FROM users WHERE id = $id AND salon_id = $salon_id AND role = 'customer'");
    if (mysqli_num_rows($check) == 1) {
        // Delete appointments first (foreign key)
        mysqli_query($conn, "DELETE FROM appointments WHERE customer_id = $id");
        
        // Delete payments (foreign key)
        mysqli_query($conn, "DELETE FROM payments WHERE appointment_id IN (SELECT id FROM appointments WHERE customer_id = $id)");
        
        // Delete customer
        $query = "DELETE FROM users WHERE id = $id AND role = 'customer' AND salon_id = $salon_id";
        if (mysqli_query($conn, $query)) {
            $success = "Customer permanently deleted!";
        } else {
            $error = "Database error: " . mysqli_error($conn);
        }
    } else {
        $error = "Customer not found or does not belong to your salon.";
    }
}

// ============================================
// GET CUSTOMERS LIST
// ============================================
$customers_query = "SELECT * FROM users 
                    WHERE role = 'customer' AND salon_id = $salon_id 
                    ORDER BY is_active DESC, full_name ASC";
$customers = mysqli_query($conn, $customers_query);

// ============================================
// INCLUDE HEADER
// ============================================
include '../includes/header.php';
?>

<style>
    .dashboard-container { display: flex; min-height: 100vh; }
    .sidebar { width: 280px; background: #050505; border-right: 1px solid #d4af37; padding: 2rem 1rem; }
    .sidebar-menu { list-style: none; padding: 0; }
    .sidebar-menu li { margin-bottom: 0.5rem; }
    .sidebar-menu a { display: block; padding: 12px 20px; color: white; text-decoration: none; border-radius: 10px; transition: all 0.3s; }
    .sidebar-menu a:hover, .sidebar-menu a.active { background: #d4af37; color: #050505; }
    .main-content { flex: 1; padding: 2rem; background: #0a0a0a; }
    
    .table-container { overflow-x: auto; background: #1a1a1a; border-radius: 15px; padding: 1rem; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid rgba(212, 175, 55, 0.2); }
    th { color: #d4af37; }
    
    .btn-danger { background: #dc3545; color: white; border: none; padding: 6px 15px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.75rem; }
    .btn-danger:hover { background: #c82333; }
    .btn-warning { background: #d4af37; color: #050505; border: none; padding: 6px 15px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.75rem; }
    .btn-warning:hover { background: #f9e547; }
    .btn-success { background: #28a745; color: white; border: none; padding: 6px 15px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.75rem; }
    .btn-success:hover { background: #218838; }
    
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 1rem; }
    .alert-success { background: rgba(40, 167, 69, 0.2); border: 1px solid #28a745; color: #28a745; }
    .alert-danger { background: rgba(220, 53, 69, 0.2); border: 1px solid #dc3545; color: #dc3545; }
    
    .status-active { color: #28a745; font-weight: bold; }
    .status-inactive { color: #dc3545; font-weight: bold; }
    
    h1 { color: #d4af37; }
    
    @media (max-width: 768px) { .dashboard-container { flex-direction: column; } .sidebar { width: 100%; } }
</style>

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
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if(isset($error)): ?>
            <div class="alert alert-danger">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        
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
                    <?php if($customers && mysqli_num_rows($customers) > 0): ?>
                        <?php while($customer = mysqli_fetch_assoc($customers)): ?>
                        <tr>
                            <td><?php echo $customer['id']; ?></td>
                            <td><?php echo htmlspecialchars($customer['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($customer['email']); ?></td>
                            <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                            <td class="status-<?php echo $customer['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $customer['is_active'] ? '✅ Active' : '❌ Inactive'; ?>
                            </td>
                            <td>
                                <?php if($customer['is_active']): ?>
                                    <a href="?deactivate=1&id=<?php echo $customer['id']; ?>" class="btn-warning" onclick="return confirm('Deactivate this customer? They will not be able to log in.')">⏸️ Deactivate</a>
                                <?php else: ?>
                                    <a href="?activate=1&id=<?php echo $customer['id']; ?>" class="btn-success" onclick="return confirm('Activate this customer?')">▶️ Activate</a>
                                <?php endif; ?>
                                <a href="?delete=1&id=<?php echo $customer['id']; ?>" class="btn-danger" onclick="return confirm('⚠️ PERMANENTLY DELETE this customer and ALL their appointments? This cannot be undone!')">🗑️ Delete</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">No customers found for your salon.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>