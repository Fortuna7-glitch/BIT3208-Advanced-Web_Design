<?php
// super_admin/subscriptions.php - UPDATED with Add Subscription feature
require_once '../config/database.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

$message = '';

// ============================================
// HANDLE ADD SUBSCRIPTION
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_subscription'])) {
    $salon_id = mysqli_real_escape_string($conn, $_POST['salon_id']);
    $plan = mysqli_real_escape_string($conn, $_POST['plan']);
    $amount = mysqli_real_escape_string($conn, $_POST['amount']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $expiry_date = mysqli_real_escape_string($conn, $_POST['expiry_date']);
    
    $query = "INSERT INTO subscription_history (salon_id, plan, amount, payment_method, expiry_date) 
              VALUES ('$salon_id', '$plan', '$amount', '$payment_method', '$expiry_date')";
    
    if (mysqli_query($conn, $query)) {
        // Update salon subscription
        mysqli_query($conn, "UPDATE salons SET subscription_plan = '$plan', subscription_expiry = '$expiry_date' WHERE id = $salon_id");
        $message = "<div class='alert alert-success'>✅ Subscription added successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger'>❌ Failed to add subscription: " . mysqli_error($conn) . "</div>";
    }
}

// ============================================
// HANDLE DELETE SUBSCRIPTION
// ============================================
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
    .super-container { display: flex; min-height: 100vh; }
    .sidebar { width: 280px; background: #050505; border-right: 2px solid #d4af37; padding: 2rem 1rem; }
    .sidebar-menu { list-style: none; padding: 0; }
    .sidebar-menu li { margin-bottom: 0.5rem; }
    .sidebar-menu a { display: block; padding: 12px 20px; color: white; text-decoration: none; border-radius: 10px; }
    .sidebar-menu a:hover, .sidebar-menu a.active { background: #d4af37; color: #050505; }
    .main-content { flex: 1; padding: 2rem; background: #0a0a0a; }
    
    .form-card { background: #1a1a1a; border-radius: 15px; padding: 1.5rem; margin-bottom: 2rem; border: 1px solid rgba(212, 175, 55, 0.3); }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; color: #d4af37; font-weight: 500; }
    .form-control, select { width: 100%; padding: 10px; background: #2a2a2a; border: 1px solid rgba(212, 175, 55, 0.3); border-radius: 8px; color: white; }
    .btn-primary { background: #d4af37; color: #050505; border: none; padding: 10px 25px; border-radius: 25px; cursor: pointer; }
    .btn-danger { background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; }
    
    .table-wrapper { overflow-x: auto; background: #1a1a1a; border-radius: 15px; padding: 0; border: 1px solid rgba(212, 175, 55, 0.2); }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid rgba(212, 175, 55, 0.15); }
    th { color: #d4af37; }
    
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 1rem; }
    .alert-success { background: rgba(40, 167, 69, 0.2); border: 1px solid #28a745; color: #28a745; }
    .alert-danger { background: rgba(220, 53, 69, 0.2); border: 1px solid #dc3545; color: #dc3545; }
    
    h1 { color: #d4af37; margin-bottom: 2rem; }
    .section-title { color: #d4af37; margin: 1.5rem 0 1rem 0; font-size: 1.1rem; border-left: 3px solid #d4af37; padding-left: 1rem; }
    
    @media (max-width: 768px) { .super-container { flex-direction: column; } .sidebar { width: 100%; } }
</style>

<div class="super-container">
    <aside class="sidebar">
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
        <h1>💰 Subscription Management</h1>
        
        <?php echo $message; ?>
        
        <!-- Add Subscription Form -->
        <div class="form-card">
            <h3 style="color: #d4af37;">➕ Add Subscription Payment</h3>
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

<?php include '../includes/footer.php'; ?>