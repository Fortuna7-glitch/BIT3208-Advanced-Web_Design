<?php
// super_admin/settings.php - UPDATED with new hamburger sidebar layout
require_once '../config/database.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $site_name = mysqli_real_escape_string($conn, $_POST['site_name']);
    $contact_email = mysqli_real_escape_string($conn, $_POST['contact_email']);
    $contact_phone = mysqli_real_escape_string($conn, $_POST['contact_phone']);
    $basic_price = mysqli_real_escape_string($conn, $_POST['basic_price']);
    $premium_price = mysqli_real_escape_string($conn, $_POST['premium_price']);
    $enterprise_price = mysqli_real_escape_string($conn, $_POST['enterprise_price']);

    mysqli_query($conn, "INSERT INTO salon_settings (setting_key, setting_value) VALUES 
        ('site_name', '$site_name'),
        ('contact_email', '$contact_email'),
        ('contact_phone', '$contact_phone'),
        ('basic_price', '$basic_price'),
        ('premium_price', '$premium_price'),
        ('enterprise_price', '$enterprise_price')
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

    $message = "<div class='alert alert-success'>✅ Settings saved successfully!</div>";
}

// Get current settings
$settings = [];
$result = mysqli_query($conn, "SELECT setting_key, setting_value FROM salon_settings");
while ($row = mysqli_fetch_assoc($result)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$defaults = [
    'site_name' => 'Salon Pro',
    'contact_email' => 'info@salonpro.com',
    'contact_phone' => '+254 712 345 678',
    'basic_price' => '0',
    'premium_price' => '10000',
    'enterprise_price' => '20000'
];

foreach ($defaults as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

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

    .settings-container {
        max-width: 700px;
        margin: 0 auto;
        background: #1a1a1a;
        border-radius: 15px;
        padding: 2rem;
        border: 1px solid rgba(212, 175, 55, 0.3);
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
    .form-control {
        width: 100%;
        padding: 12px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 8px;
        color: white;
        font-size: 1rem;
    }
    .form-control:focus {
        outline: none;
        border-color: #d4af37;
    }

    .btn-primary {
        width: 100%;
        padding: 12px;
        background: #d4af37;
        color: #050505;
        border: none;
        border-radius: 50px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }
    .btn-primary:hover {
        background: #f9e547;
        transform: translateY(-2px);
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

    .sub-title {
        color: #d4af37;
        font-size: 1.1rem;
        margin: 1.5rem 0 1rem 0;
        border-left: 3px solid #d4af37;
        padding-left: 1rem;
    }

    .price-input-group {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .price-input-group .currency {
        color: #d4af37;
        font-weight: bold;
        font-size: 1.1rem;
    }
    .price-input-group .form-control {
        flex: 1;
    }

    .plan-label {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: bold;
    }
    .plan-basic { background: rgba(23, 162, 184, 0.2); color: #17a2b8; border: 1px solid #17a2b8; }
    .plan-premium { background: rgba(212, 175, 55, 0.2); color: #d4af37; border: 1px solid #d4af37; }
    .plan-enterprise { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }

    .back-link {
        display: block;
        text-align: center;
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
    @media (max-width: 768px) {
        .main-content { padding: 1rem; }
        .section-title { font-size: 1.1rem; }
        .settings-container { padding: 1.5rem; }
        .price-input-group { flex-wrap: wrap; }
        .price-input-group .form-control { width: 100%; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .section-title { font-size: 1rem; }
        .settings-container { padding: 1rem; }
        .form-control { padding: 10px; font-size: 0.9rem; }
        .btn-primary { padding: 10px; font-size: 0.9rem; }
    }
</style>

<div class="main-content">

    <h1 class="section-title">⚙️ System Settings</h1>

    <?php echo $message; ?>

    <div class="settings-container">
        <form method="POST">

            <!-- General Settings -->
            <div class="sub-title">General Settings</div>

            <div class="form-group">
                <label>Site Name</label>
                <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($settings['site_name']); ?>">
            </div>

            <div class="form-group">
                <label>Contact Email</label>
                <input type="email" name="contact_email" class="form-control" value="<?php echo htmlspecialchars($settings['contact_email']); ?>">
            </div>

            <div class="form-group">
                <label>Contact Phone</label>
                <input type="tel" name="contact_phone" class="form-control" value="<?php echo htmlspecialchars($settings['contact_phone']); ?>">
            </div>

            <!-- Plan Pricing -->
            <div class="sub-title">💰 Plan Pricing (KSh/month)</div>

            <div class="form-group">
                <label><span class="plan-label plan-basic">Basic</span> Price</label>
                <div class="price-input-group">
                    <span class="currency">KSh</span>
                    <input type="number" name="basic_price" class="form-control" value="<?php echo $settings['basic_price']; ?>" step="0.01" min="0">
                </div>
                <small style="color: #888;">Default: 0 (Free)</small>
            </div>

            <div class="form-group">
                <label><span class="plan-label plan-premium">Premium</span> Price</label>
                <div class="price-input-group">
                    <span class="currency">KSh</span>
                    <input type="number" name="premium_price" class="form-control" value="<?php echo $settings['premium_price']; ?>" step="0.01" min="0">
                </div>
                <small style="color: #888;">Default: 10,000</small>
            </div>

            <div class="form-group">
                <label><span class="plan-label plan-enterprise">Enterprise</span> Price</label>
                <div class="price-input-group">
                    <span class="currency">KSh</span>
                    <input type="number" name="enterprise_price" class="form-control" value="<?php echo $settings['enterprise_price']; ?>" step="0.01" min="0">
                </div>
                <small style="color: #888;">Default: 20,000</small>
            </div>

            <button type="submit" class="btn-primary">💾 Save Settings</button>
        </form>
    </div>

</div>

<?php include '../includes/footer.php'; ?>