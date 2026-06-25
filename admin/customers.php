<?php
// admin/customers.php - UPDATED with new hamburger sidebar layout
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

// Handle Deactivate
if (isset($_GET['deactivate']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $check = mysqli_query($conn, "SELECT id FROM users WHERE id = $id AND salon_id = $salon_id AND role = 'customer'");
    if (mysqli_num_rows($check) == 1) {
        mysqli_query($conn, "UPDATE users SET is_active = 0 WHERE id = $id AND role = 'customer' AND salon_id = $salon_id");
        $success = "Customer deactivated successfully!";
    } else {
        $error = "Customer not found or does not belong to your salon.";
    }
    redirect('customers.php');
}

// Handle Activate
if (isset($_GET['activate']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $check = mysqli_query($conn, "SELECT id FROM users WHERE id = $id AND salon_id = $salon_id AND role = 'customer'");
    if (mysqli_num_rows($check) == 1) {
        mysqli_query($conn, "UPDATE users SET is_active = 1 WHERE id = $id AND role = 'customer' AND salon_id = $salon_id");
        $success = "Customer activated successfully!";
    } else {
        $error = "Customer not found or does not belong to your salon.";
    }
    redirect('customers.php');
}

// Handle Delete
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $check = mysqli_query($conn, "SELECT id FROM users WHERE id = $id AND salon_id = $salon_id AND role = 'customer'");
    if (mysqli_num_rows($check) == 1) {
        mysqli_query($conn, "DELETE FROM appointments WHERE customer_id = $id");
        mysqli_query($conn, "DELETE FROM payments WHERE appointment_id IN (SELECT id FROM appointments WHERE customer_id = $id)");
        mysqli_query($conn, "DELETE FROM users WHERE id = $id AND role = 'customer' AND salon_id = $salon_id");
        $success = "Customer permanently deleted!";
    } else {
        $error = "Customer not found or does not belong to your salon.";
    }
    redirect('customers.php');
}

$customers = mysqli_query($conn, "SELECT * FROM users WHERE role = 'customer' AND salon_id = $salon_id ORDER BY is_active DESC, full_name ASC");

include '../includes/header.php';
?>

<style>
    .main-content {
        padding: 2rem;
        background: #0a0a0a;
        min-height: 100vh;
    }

    .section-title {
        color: #d4af37;
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
        border-left: 3px solid #d4af37;
        padding-left: 1rem;
    }

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

    .btn-outline {
        display: inline-block;
        padding: 5px 12px;
        border: 1px solid #d4af37;
        color: #d4af37;
        text-decoration: none;
        border-radius: 5px;
        font-size: 0.75rem;
        transition: all 0.3s;
    }
    .btn-outline:hover {
        background: #d4af37;
        color: #050505;
    }

    .btn-danger {
        background: #dc3545;
        color: white;
        border: none;
        padding: 5px 12px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.75rem;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
    }
    .btn-danger:hover {
        background: #c82333;
        transform: scale(1.05);
    }

    .btn-success {
        background: #28a745;
        color: white;
        border: none;
        padding: 5px 12px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.75rem;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
    }
    .btn-success:hover {
        background: #218838;
        transform: scale(1.05);
    }

    .status-active { color: #28a745; font-weight: bold; }
    .status-inactive { color: #dc3545; font-weight: bold; }

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

    .action-cell { display: flex; gap: 0.5rem; flex-wrap: wrap; }

    .back-link {
        display: inline-block;
        margin-top: 1.5rem;
        color: #d4af37;
        text-decoration: none;
    }
    .back-link:hover {
        text-decoration: underline;
    }

    /* ============================================
       RESPONSIVE
       ============================================ */
    @media (max-width: 1024px) {
        table { min-width: 600px; font-size: 0.85rem; }
        th, td { padding: 10px; }
    }

    @media (max-width: 768px) {
        .main-content { padding: 1rem; }
        .section-title { font-size: 1.1rem; }
        table { min-width: 500px; font-size: 0.8rem; }
        th, td { padding: 8px; white-space: nowrap; }
        .action-cell { flex-direction: column; }
        .action-cell .btn-outline,
        .action-cell .btn-danger,
        .action-cell .btn-success { width: 100%; text-align: center; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .section-title { font-size: 1rem; }
        table { min-width: 400px; font-size: 0.7rem; }
        th, td { padding: 6px; }
    }
</style>

<div class="main-content">

    <h1 class="section-title">👤 Customer Management</h1>

    <?php if(isset($success)): ?>
        <div class="alert alert-success">✅ <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if(isset($error)): ?>
        <div class="alert alert-danger">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <div class="table-wrapper">
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
                        <td class="action-cell">
                            <?php if($customer['is_active']): ?>
                                <a href="?deactivate=1&id=<?php echo $customer['id']; ?>" class="btn-outline" onclick="return confirm('Deactivate this customer?')">⏸️ Deactivate</a>
                            <?php else: ?>
                                <a href="?activate=1&id=<?php echo $customer['id']; ?>" class="btn-success" onclick="return confirm('Activate this customer?')">▶️ Activate</a>
                            <?php endif; ?>
                            <a href="?delete=1&id=<?php echo $customer['id']; ?>" class="btn-danger" onclick="return confirm('⚠️ PERMANENTLY DELETE this customer?')">🗑️ Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px;">
                            👤 No customers found for your salon.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<?php include '../includes/footer.php'; ?>