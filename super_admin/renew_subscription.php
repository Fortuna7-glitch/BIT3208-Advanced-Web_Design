<?php
/**
 * admin/renew_subscription.php
 * 
 * SUPER ADMIN PAGE - Renew/Reactivate a salon subscription after payment
 * 
 * Allows Super Admin to:
 * - View expired/suspended salons
 * - Renew subscription with new expiry date
 * - Change subscription plan
 * - Reactivate salon owner and staff
 */

require_once '../config/database.php';

// Only Super Admin can access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

$error = '';
$success = '';
$salon_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ============================================
// HANDLE RENEWAL FORM SUBMISSION
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['renew_subscription'])) {
    $salon_id = (int)$_POST['salon_id'];
    $new_expiry = mysqli_real_escape_string($conn, $_POST['new_expiry']);
    $plan = mysqli_real_escape_string($conn, $_POST['plan']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $amount = mysqli_real_escape_string($conn, $_POST['amount']);
    $transaction_code = mysqli_real_escape_string($conn, $_POST['transaction_code']);
    
    if (empty($new_expiry)) {
        $error = "Please select a new expiry date.";
    } else {
        // Reactivate salon using helper function
        if (reactivateSalon($salon_id, $new_expiry, $plan)) {
            // Record payment in subscription history
            $history_query = "INSERT INTO subscription_history 
                              (salon_id, plan, amount, payment_method, transaction_id, expiry_date) 
                              VALUES ($salon_id, '$plan', '$amount', '$payment_method', '$transaction_code', '$new_expiry')";
            mysqli_query($conn, $history_query);
            
            $success = "✅ Subscription renewed successfully! Salon has been reactivated.";
            
            // Get salon name for notification
            $salon_query = mysqli_query($conn, "SELECT salon_name, salon_email FROM salons WHERE id = $salon_id");
            $salon = mysqli_fetch_assoc($salon_query);
            
            // Send notification to salon owner
            $owner_email_query = "SELECT email FROM users WHERE salon_id = $salon_id AND role = 'admin'";
            $owner_email_result = mysqli_query($conn, $owner_email_query);
            if ($owner_email_result && $owner = mysqli_fetch_assoc($owner_email_result)) {
                sendEmail($owner['email'], "Subscription Renewed - Salon Pro", 
                    "Dear Salon Owner,<br><br>Your subscription for <strong>{$salon['salon_name']}</strong> has been renewed.<br><br>New Expiry Date: " . date('M d, Y', strtotime($new_expiry)) . "<br>Plan: " . ucfirst($plan) . "<br><br>You can now log in to your admin panel.<br><br>Thank you,<br>Salon Pro Team");
            }
            
            // Log the action
            logMessage("Super Admin renewed subscription for salon ID: $salon_id, Plan: $plan, Expiry: $new_expiry");
        } else {
            $error = "Failed to renew subscription. Please try again.";
        }
    }
}

// ============================================
// GET SALON DETAILS (if ID provided)
// ============================================
$salon_details = null;
$owner_details = null;

if ($salon_id > 0) {
    $salon_query = mysqli_query($conn, "SELECT * FROM salons WHERE id = $salon_id");
    if ($salon_query && $salon = mysqli_fetch_assoc($salon_query)) {
        $salon_details = $salon;
        
        // Get owner details
        $owner_query = mysqli_query($conn, "SELECT * FROM users WHERE salon_id = $salon_id AND role = 'admin'");
        if ($owner_query) {
            $owner_details = mysqli_fetch_assoc($owner_query);
        }
    }
}

// ============================================
// GET ALL EXPIRED/SUSPENDED SALONS
// ============================================
$expired_salons_query = "SELECT s.*, u.full_name as owner_name, u.email as owner_email 
                         FROM salons s 
                         LEFT JOIN users u ON s.id = u.salon_id AND u.role = 'admin' 
                         WHERE s.subscription_status = 'expired' OR s.subscription_status = 'suspended'
                         ORDER BY s.subscription_expiry ASC";
$expired_salons = mysqli_query($conn, $expired_salons_query);

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
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
        border: 1px solid rgba(212, 175, 55, 0.3);
        max-width: 700px;
        margin-left: auto;
        margin-right: auto;
    }
    .form-card h3 {
        color: #d4af37;
        margin-bottom: 1.5rem;
        text-align: center;
    }

    .form-group {
        margin-bottom: 1.5rem;
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
        font-size: 1rem;
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
        padding: 12px 30px;
        border-radius: 25px;
        font-weight: 600;
        cursor: pointer;
        font-size: 1rem;
        width: 100%;
        transition: all 0.3s;
    }
    .btn-primary:hover {
        background: #f9e547;
        transform: translateY(-2px);
    }

    .btn-secondary {
        background: transparent;
        color: #d4af37;
        border: 1px solid #d4af37;
        padding: 8px 20px;
        border-radius: 25px;
        text-decoration: none;
        display: inline-block;
        transition: all 0.3s;
        font-size: 0.85rem;
    }
    .btn-secondary:hover {
        background: rgba(212, 175, 55, 0.1);
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

    .table-wrapper {
        overflow-x: auto;
        background: #1a1a1a;
        border-radius: 15px;
        padding: 0;
        border: 1px solid rgba(212, 175, 55, 0.2);
        margin-bottom: 2rem;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
        min-width: 600px;
    }
    th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid rgba(212, 175, 55, 0.15);
        white-space: nowrap;
    }
    th { color: #d4af37; font-weight: 600; }
    tr:hover { background: rgba(212, 175, 55, 0.05); }

    .status-expired { color: #dc3545; font-weight: bold; }
    .status-suspended { color: #d4af37; font-weight: bold; }

    .salon-info-box {
        background: #0a0a0a;
        padding: 1rem;
        border-radius: 10px;
        margin-bottom: 1.5rem;
        border-left: 4px solid #d4af37;
    }
    .salon-info-box p {
        color: #aaa;
        margin: 0.3rem 0;
    }
    .salon-info-box .label {
        color: #888;
        font-size: 0.8rem;
    }

    .back-link {
        display: inline-block;
        margin-top: 1rem;
        color: #d4af37;
        text-decoration: none;
    }
    .back-link:hover {
        text-decoration: underline;
    }

    .text-center {
        text-align: center;
    }

    @media (max-width: 768px) {
        .main-content { padding: 1rem; }
        .form-card { padding: 1rem; }
        table { min-width: 500px; font-size: 0.8rem; }
        th, td { padding: 8px; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .form-card { padding: 0.8rem; }
        table { min-width: 400px; font-size: 0.7rem; }
        th, td { padding: 6px; }
    }
</style>

<div class="main-content">

    <h1 class="section-title">🔄 Renew Subscription</h1>

    <!-- ============================================
    RENEWAL FORM (If salon ID provided)
    ============================================ -->
    <?php if ($salon_id > 0 && $salon_details): ?>
    
    <div class="form-card">
        <h3>📋 Renew Subscription for <?php echo htmlspecialchars($salon_details['salon_name']); ?></h3>
        
        <?php if($error): ?>
            <div class="alert alert-danger">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>

        <div class="salon-info-box">
            <p><span class="label">Salon:</span> <strong><?php echo htmlspecialchars($salon_details['salon_name']); ?></strong></p>
            <p><span class="label">Email:</span> <?php echo htmlspecialchars($salon_details['salon_email']); ?></p>
            <p><span class="label">Current Plan:</span> <?php echo ucfirst($salon_details['subscription_plan']); ?></p>
            <p><span class="label">Status:</span> <span class="status-<?php echo $salon_details['subscription_status']; ?>"><?php echo ucfirst($salon_details['subscription_status']); ?></span></p>
            <p><span class="label">Expired on:</span> <?php echo date('M d, Y', strtotime($salon_details['subscription_expiry'])); ?></p>
            <?php if($owner_details): ?>
                <p><span class="label">Owner:</span> <?php echo htmlspecialchars($owner_details['full_name']); ?> (<?php echo htmlspecialchars($owner_details['email']); ?>)</p>
            <?php endif; ?>
        </div>

        <form method="POST">
            <input type="hidden" name="salon_id" value="<?php echo $salon_id; ?>">
            
            <div class="form-group">
                <label>Select Plan</label>
                <select name="plan" class="form-control" required>
                    <option value="basic" <?php echo ($salon_details['subscription_plan'] == 'basic') ? 'selected' : ''; ?>>Basic</option>
                    <option value="premium" <?php echo ($salon_details['subscription_plan'] == 'premium') ? 'selected' : ''; ?>>Premium</option>
                    <option value="enterprise" <?php echo ($salon_details['subscription_plan'] == 'enterprise') ? 'selected' : ''; ?>>Enterprise</option>
                </select>
            </div>

            <div class="form-group">
                <label>New Expiry Date</label>
                <input type="date" name="new_expiry" class="form-control" required min="<?php echo date('Y-m-d', strtotime('+1 month')); ?>">
            </div>

            <div class="form-group">
                <label>Amount Paid (KSh)</label>
                <input type="number" name="amount" class="form-control" required step="0.01" placeholder="e.g., 10000">
            </div>

            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method" class="form-control" required>
                    <option value="cash">💵 Cash</option>
                    <option value="mpesa">📱 M-PESA</option>
                    <option value="bank">🏦 Bank Transfer</option>
                </select>
            </div>

            <div class="form-group">
                <label>Transaction Reference (Optional)</label>
                <input type="text" name="transaction_code" class="form-control" placeholder="e.g., MPESA-123456 or Invoice #001">
            </div>

            <button type="submit" name="renew_subscription" class="btn-primary">🔓 Renew & Reactivate</button>
        </form>

        <div class="text-center">
            <a href="renew_subscription.php" class="back-link">← Back to Expired Salons</a>
        </div>
    </div>

    <!-- ============================================
    LIST OF EXPIRED/SUSPENDED SALONS (If no ID provided)
    ============================================ -->
    <?php else: ?>

    <p style="color: #aaa; margin-bottom: 1.5rem;">
        Below is a list of salons with expired or suspended subscriptions. Click <strong>Renew</strong> to reactivate a salon.
    </p>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Salon</th>
                    <th>Owner</th>
                    <th>Plan</th>
                    <th>Status</th>
                    <th>Expiry Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if($expired_salons && mysqli_num_rows($expired_salons) > 0): ?>
                    <?php while($salon = mysqli_fetch_assoc($expired_salons)): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($salon['salon_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($salon['owner_name'] ?? 'N/A'); ?></td>
                        <td><?php echo ucfirst($salon['subscription_plan']); ?></td>
                        <td class="status-<?php echo $salon['subscription_status']; ?>">
                            <?php echo ucfirst($salon['subscription_status']); ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($salon['subscription_expiry'])); ?></td>
                        <td>
                            <a href="renew_subscription.php?id=<?php echo $salon['id']; ?>" class="btn-secondary" style="padding: 5px 15px; font-size: 0.75rem;">🔄 Renew</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px;">
                            🎉 No expired or suspended salons. All subscriptions are active!
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php endif; ?>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<?php include '../includes/footer.php'; ?>