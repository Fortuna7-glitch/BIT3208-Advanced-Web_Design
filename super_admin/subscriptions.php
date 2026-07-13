<?php
// super_admin/subscriptions.php - UPDATED with new hamburger sidebar layout
require_once '../config/database.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

$message = '';

// Handle Add Subscription
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_subscription'])) {
    $salon_id = mysqli_real_escape_string($conn, $_POST['salon_id']);
    $plan = mysqli_real_escape_string($conn, $_POST['plan']);
    $amount = mysqli_real_escape_string($conn, $_POST['amount']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $expiry_date = mysqli_real_escape_string($conn, $_POST['expiry_date']);

    $query = "INSERT INTO subscription_history (salon_id, plan, amount, payment_method, expiry_date) 
            VALUES ('$salon_id', '$plan', '$amount', '$payment_method', '$expiry_date')";

    if (mysqli_query($conn, $query)) {
        // Add this after the INSERT query:
$update_salon = "UPDATE salons SET 
                subscription_plan = '$plan', 
                subscription_expiry = '$expiry_date',
                subscription_status = 'active'
                WHERE id = $salon_id";
mysqli_query($conn, $update_salon);
        mysqli_query($conn, "UPDATE salons SET subscription_plan = '$plan', subscription_expiry = '$expiry_date' WHERE id = $salon_id");
        $message = "<div class='alert alert-success'>✅ Subscription added successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger'>❌ Failed to add subscription: " . mysqli_error($conn) . "</div>";
    }
}

// Handle Delete
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    mysqli_query($conn, "DELETE FROM subscription_history WHERE id = $id");
    redirect('subscriptions.php');
}

// Get all subscriptions with salon info
$subscriptions = mysqli_query($conn, "SELECT sh.*, s.salon_name 
                                    FROM subscription_history sh 
                                    JOIN salons s ON sh.salon_id = s.id 
                                    ORDER BY sh.payment_date DESC");

// Get all salons for dropdown
$salons = mysqli_query($conn, "SELECT id, salon_name, subscription_plan FROM salons ORDER BY salon_name");

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

    .form-card {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        border: 1px solid rgba(212, 175, 55, 0.3);
    }
    .form-card h3 {
        color: #d4af37;
        margin-bottom: 1rem;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
    }
    .form-group {
        margin-bottom: 1rem;
    }
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        color: #d4af37;
        font-weight: 500;
    }
    .form-control,
    select {
        width: 100%;
        padding: 10px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 8px;
        color: white;
    }
    .form-control:focus,
    select:focus {
        outline: none;
        border-color: #d4af37;
    }

    .btn-primary {
        background: #d4af37;
        color: #050505;
        border: none;
        padding: 10px 25px;
        border-radius: 25px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
    }
    .btn-primary:hover {
        background: #f9e547;
        transform: translateY(-2px);
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

    .action-cell { display: flex; gap: 0.5rem; flex-wrap: wrap; }

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
        .form-grid { grid-template-columns: 1fr; }
        table { min-width: 500px; font-size: 0.8rem; }
        th, td { padding: 8px; white-space: nowrap; }
        .action-cell { flex-direction: column; }
        .action-cell .btn-primary,
        .action-cell .btn-danger { width: 100%; text-align: center; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .section-title { font-size: 1rem; }
        table { min-width: 400px; font-size: 0.7rem; }
        th, td { padding: 6px; }
    }
</style>

<div class="main-content">

    <h1 class="section-title">💰 Subscription Management</h1>

    <?php echo $message; ?>

    <!-- Add Subscription Form -->
    <div class="form-card">
        <h3>➕ Add Subscription Payment</h3>
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

    <!-- Subscription History -->
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
                    <td class="action-cell">
                        <a href="?delete=<?php echo $sub['id']; ?>" class="btn-danger" onclick="return confirm('Delete this subscription record?')">🗑️</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<?php include '../includes/footer.php'; ?>