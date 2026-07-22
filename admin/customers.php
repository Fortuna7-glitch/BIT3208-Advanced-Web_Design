<?php
// admin/customers.php - ADMIN FULL ACCESS: All actions visible
require_once '../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$error = '';
$success = '';

// ============================================
// HANDLE ACTIONS (Admin has full access)
// ============================================

// Add Customer
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");
    if (mysqli_num_rows($check) > 0) {
        $error = "Email already registered!";
    } else {
        $query = "INSERT INTO users (full_name, email, phone, address, password, role, is_active) 
                  VALUES ('$full_name', '$email', '$phone', '$address', '$password', 'customer', 1)";
        if (mysqli_query($conn, $query)) {
            $success = "Customer added successfully!";
        } else {
            $error = "Failed to add customer: " . mysqli_error($conn);
        }
    }
}

// Update Customer
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    $id = (int)$_POST['id'];
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $query = "UPDATE users SET full_name='$full_name', phone='$phone', address='$address', is_active=$is_active WHERE id=$id AND role='customer'";
    if (mysqli_query($conn, $query)) {
        $success = "Customer updated successfully!";
    } else {
        $error = "Failed to update customer: " . mysqli_error($conn);
    }
}

// Delete Customer
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments WHERE customer_id = $id");
    $appointments = mysqli_fetch_assoc($check)['count'];
    if ($appointments > 0) {
        $error = "Cannot delete customer with $appointments appointments. Deactivate instead.";
    } else {
        $query = "DELETE FROM users WHERE id=$id AND role='customer'";
        if (mysqli_query($conn, $query)) {
            $success = "Customer deleted successfully!";
        } else {
            $error = "Failed to delete customer: " . mysqli_error($conn);
        }
    }
}

// Toggle Customer Status
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $query = "UPDATE users SET is_active = NOT is_active WHERE id=$id AND role='customer'";
    if (mysqli_query($conn, $query)) {
        $success = "Customer status toggled!";
    } else {
        $error = "Failed to toggle status: " . mysqli_error($conn);
    }
}

// ============================================
// SEARCH/FILTER
// ============================================
$search = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// ============================================
// GET CUSTOMERS
// ============================================
$query = "SELECT * FROM users WHERE role = 'customer'";
if ($search) {
    $query .= " AND (full_name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";
}
if ($status_filter == 'active') {
    $query .= " AND is_active = 1";
} elseif ($status_filter == 'inactive') {
    $query .= " AND is_active = 0";
}
$query .= " ORDER BY full_name";
$customers = mysqli_query($conn, $query);

// Get stats
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM users WHERE role = 'customer'";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

include '../includes/header.php';
?>

<style>
    .main-content {
        padding: 2rem;
        background: #0a0a0a;
        min-height: 100vh;
    }

    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }

    .page-header .title-section h1 {
        color: #d4af37;
        font-size: 1.3rem;
        font-weight: 600;
        border-left: 3px solid #d4af37;
        padding-left: 1rem;
    }

    .page-header .title-section p {
        color: #aaa;
        font-size: 0.85rem;
        margin-top: 0.2rem;
        padding-left: 1rem;
    }

    .page-header .quick-actions {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .page-header .quick-actions .quick-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 8px 16px;
        border-radius: 25px;
        font-size: 0.75rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s;
        background: rgba(212, 175, 55, 0.1);
        color: #d4af37;
        border: 1px solid rgba(212, 175, 55, 0.2);
    }

    .page-header .quick-actions .quick-btn:hover {
        background: #d4af37;
        color: #050505;
        transform: translateY(-2px);
    }

    .page-header .quick-actions .quick-btn i {
        font-size: 0.8rem;
    }

    .page-header .search-section {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex: 0 1 280px;
        min-width: 160px;
    }

    .page-header .search-section input {
        padding: 8px 14px;
        background: #1a1a1a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        color: white;
        font-size: 0.85rem;
        width: 100%;
        transition: all 0.3s;
    }

    .page-header .search-section input:focus {
        outline: none;
        border-color: #d4af37;
        box-shadow: 0 0 20px rgba(212, 175, 55, 0.1);
    }

    .page-header .search-section input::placeholder {
        color: #666;
    }

    .page-header .search-section .search-btn {
        padding: 8px 14px;
        background: #d4af37;
        border: none;
        border-radius: 25px;
        color: #050505;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
        white-space: nowrap;
    }

    .page-header .search-section .search-btn:hover {
        background: #f9e547;
    }

    /* Stats */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: #1a1a1a;
        border-radius: 12px;
        padding: 1rem 1.2rem;
        text-align: center;
        border-left: 4px solid #d4af37;
        transition: all 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(212, 175, 55, 0.08);
    }

    .stat-card .stat-number {
        font-size: 1.8rem;
        font-weight: bold;
        color: #d4af37;
    }

    .stat-card .stat-label {
        color: #aaa;
        font-size: 0.75rem;
        margin-top: 0.2rem;
    }

    .stat-card.green { border-left-color: #28a745; }
    .stat-card.green .stat-number { color: #28a745; }
    .stat-card.red { border-left-color: #dc3545; }
    .stat-card.red .stat-number { color: #dc3545; }

    /* Add Customer Form */
    .add-form {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.2rem 1.5rem;
        border: 1px solid rgba(212, 175, 55, 0.2);
        margin-bottom: 2rem;
    }

    .add-form h3 {
        color: #d4af37;
        font-size: 1rem;
        margin-bottom: 1rem;
    }

    .add-form .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 1rem;
        align-items: flex-end;
    }

    .add-form .form-group label {
        display: block;
        color: #d4af37;
        font-size: 0.8rem;
        margin-bottom: 0.3rem;
    }

    .add-form .form-group input {
        width: 100%;
        padding: 8px 12px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 8px;
        color: white;
        font-size: 0.85rem;
    }

    .add-form .form-group input:focus {
        outline: none;
        border-color: #d4af37;
    }

    .add-form .btn-add {
        padding: 8px 25px;
        background: #d4af37;
        border: none;
        border-radius: 25px;
        color: #050505;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
        white-space: nowrap;
    }

    .add-form .btn-add:hover {
        background: #f9e547;
        transform: translateY(-2px);
    }

    /* Filter Bar */
    .filter-bar {
        display: flex;
        gap: 0.8rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        align-items: center;
        background: #1a1a1a;
        padding: 0.8rem 1.2rem;
        border-radius: 12px;
        border: 1px solid rgba(212, 175, 55, 0.1);
    }

    .filter-bar select {
        padding: 8px 14px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        color: white;
        font-size: 0.85rem;
        min-width: 130px;
    }

    .filter-bar select:focus {
        outline: none;
        border-color: #d4af37;
    }

    .filter-bar .filter-btn {
        padding: 8px 20px;
        background: #d4af37;
        border: none;
        border-radius: 25px;
        color: #050505;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
        white-space: nowrap;
    }

    .filter-bar .filter-btn:hover {
        background: #f9e547;
    }

    .filter-bar .clear-btn {
        padding: 8px 20px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        color: #aaa;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
        text-decoration: none;
        white-space: nowrap;
    }

    .filter-bar .clear-btn:hover {
        background: #333;
        color: white;
    }

    /* Table */
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
        font-size: 0.85rem;
        min-width: 750px;
    }

    th, td {
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid rgba(212, 175, 55, 0.1);
    }

    th {
        color: #d4af37;
        font-weight: 600;
    }

    tr:hover {
        background: rgba(212, 175, 55, 0.05);
    }

    .status-badge {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
    }

    .status-badge.active {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border: 1px solid #28a745;
    }

    .status-badge.inactive {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
        border: 1px solid #dc3545;
    }

    .action-cell {
        display: flex;
        gap: 0.3rem;
        flex-wrap: wrap;
    }

    .action-cell .btn {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
    }

    .btn-update {
        background: rgba(23, 162, 184, 0.15);
        color: #17a2b8;
        border: 1px solid #17a2b8;
    }

    .btn-update:hover {
        background: #17a2b8;
        color: white;
    }

    .btn-toggle {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid #d4af37;
    }

    .btn-toggle:hover {
        background: #d4af37;
        color: #050505;
    }

    .btn-delete {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
        border: 1px solid #dc3545;
    }

    .btn-delete:hover {
        background: #dc3545;
        color: white;
    }

    .btn-view {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid rgba(212, 175, 55, 0.3);
    }

    .btn-view:hover {
        background: #d4af37;
        color: #050505;
    }

    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 1rem;
    }

    .alert-success {
        background: rgba(40, 167, 69, 0.2);
        border: 1px solid #28a745;
        color: #28a745;
    }

    .alert-danger {
        background: rgba(220, 53, 69, 0.2);
        border: 1px solid #dc3545;
        color: #dc3545;
    }

    .empty-state {
        text-align: center;
        padding: 3rem 0;
        color: #666;
    }

    .back-link {
        display: inline-block;
        margin-top: 1.5rem;
        color: #d4af37;
        text-decoration: none;
    }

    .back-link:hover {
        text-decoration: underline;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        table { min-width: 600px; }
    }

    @media (max-width: 768px) {
        .main-content { padding: 1rem; }
        .page-header {
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
        }
        .page-header .title-section h1 { font-size: 1.1rem; }
        .page-header .quick-actions { justify-content: flex-start; }
        .page-header .search-section { flex: 1; }
        .page-header .search-section input { width: 100%; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 0.8rem; }
        .stat-card { padding: 0.8rem; }
        .stat-card .stat-number { font-size: 1.4rem; }
        .add-form .form-row { grid-template-columns: 1fr; }
        .add-form .btn-add { width: 100%; }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar select { width: 100%; }
        table { min-width: 500px; font-size: 0.75rem; }
        th, td { padding: 6px; }
        .action-cell { flex-direction: column; }
        .action-cell .btn { width: 100%; text-align: center; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .page-header .quick-actions .quick-btn { font-size: 0.65rem; padding: 6px 12px; }
        table { min-width: 400px; font-size: 0.7rem; }
        th, td { padding: 4px; }
    }
</style>

<div class="main-content">

    <!-- ============================================
       HEADER: Title + Quick Actions + Search
       ============================================ -->
    <div class="page-header">
        <div class="title-section">
            <h1>👤 Customer Management</h1>
            <p>Manage all salon customers</p>
        </div>
        <div class="quick-actions">
            <a href="appointments.php" class="quick-btn"><i class="fas fa-calendar"></i> Appointments</a>
            <a href="services.php" class="quick-btn"><i class="fas fa-scissors"></i> Services</a>
            <a href="dashboard.php" class="quick-btn"><i class="fas fa-home"></i> Dashboard</a>
        </div>
        <div class="search-section">
            <form method="GET" style="display: flex; gap: 0.5rem; width: 100%;">
                <input type="text" name="q" placeholder="🔍 Search customers..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="search-btn">Search</button>
            </form>
        </div>
    </div>

    <!-- ============================================
       ALERTS
       ============================================ -->
    <?php if($success): ?>
        <div class="alert alert-success">✅ <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-danger">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- ============================================
       STATISTICS
       ============================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
            <div class="stat-label">Total Customers</div>
        </div>
        <div class="stat-card green">
            <div class="stat-number"><?php echo $stats['active'] ?? 0; ?></div>
            <div class="stat-label">Active</div>
        </div>
        <div class="stat-card red">
            <div class="stat-number"><?php echo $stats['inactive'] ?? 0; ?></div>
            <div class="stat-label">Inactive</div>
        </div>
    </div>

    <!-- ============================================
       ADD CUSTOMER FORM
       ============================================ -->
    <div class="add-form">
        <h3>➕ Add New Customer</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" placeholder="Full name" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="email@example.com" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" placeholder="0712345678" required>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address" placeholder="Address">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn-add">➕ Add Customer</button>
                </div>
            </div>
        </form>
    </div>

    <!-- ============================================
       FILTER BAR
       ============================================ -->
    <div class="filter-bar">
        <form method="GET" style="display: flex; gap: 0.8rem; flex: 1; flex-wrap: wrap; align-items: center;">
            <input type="hidden" name="q" value="<?php echo htmlspecialchars($search); ?>">
            <select name="status">
                <option value="">All Status</option>
                <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo ($status_filter == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
            </select>
            <button type="submit" class="filter-btn">Filter</button>
            <a href="customers.php" class="clear-btn">Clear</a>
        </form>
    </div>

    <!-- ============================================
       CUSTOMERS TABLE
       ============================================ -->
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($customers) > 0): ?>
                    <?php while($customer = mysqli_fetch_assoc($customers)): ?>
                    <tr>
                        <form method="POST" style="display: contents;">
                            <input type="hidden" name="id" value="<?php echo $customer['id']; ?>">
                            <input type="hidden" name="action" value="update">
                            <td><?php echo $customer['id']; ?></td>
                            <td><input type="text" name="full_name" value="<?php echo htmlspecialchars($customer['full_name']); ?>" style="background:transparent;border:none;color:white;width:100%;"></td>
                            <td><?php echo htmlspecialchars($customer['email']); ?></td>
                            <td><input type="text" name="phone" value="<?php echo htmlspecialchars($customer['phone']); ?>" style="background:transparent;border:none;color:white;width:100px;"></td>
                            <td><input type="text" name="address" value="<?php echo htmlspecialchars($customer['address'] ?? ''); ?>" style="background:transparent;border:none;color:white;width:120px;"></td>
                            <td>
                                <span class="status-badge <?php echo $customer['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $customer['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="action-cell">
                                <button type="submit" class="btn btn-update">💾 Update</button>
                                <a href="customers.php?toggle=<?php echo $customer['id']; ?>" class="btn btn-toggle" onclick="return confirm('Toggle customer status?')">
                                    <?php echo $customer['is_active'] ? '🔴' : '🟢'; ?>
                                </a>
                                <a href="customers.php?delete=<?php echo $customer['id']; ?>" class="btn btn-delete" onclick="return confirm('Delete this customer?')">🗑️ Delete</a>
                                <a href="customers.php?view=<?php echo $customer['id']; ?>" class="btn btn-view">👁️ View</a>
                            </td>
                        </form>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px;">
                            <div class="empty-state">
                                <p>No customers found.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<?php include '../includes/footer.php'; ?>