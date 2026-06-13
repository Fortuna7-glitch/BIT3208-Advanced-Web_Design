<?php
// super_admin/settings.php - System settings
require_once '../config/database.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}


$message = '';

// Update settings
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $site_name = mysqli_real_escape_string($conn, $_POST['site_name']);
    $contact_email = mysqli_real_escape_string($conn, $_POST['contact_email']);
    $contact_phone = mysqli_real_escape_string($conn, $_POST['contact_phone']);
    
    // Update or insert settings
    mysqli_query($conn, "INSERT INTO salon_settings (setting_key, setting_value) VALUES 
        ('site_name', '$site_name'),
        ('contact_email', '$contact_email'),
        ('contact_phone', '$contact_phone')
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    
    $message = "<div class='alert alert-success'>✅ Settings saved successfully!</div>";
}

// Get current settings
$settings = [];
$result = mysqli_query($conn, "SELECT setting_key, setting_value FROM salon_settings");
while ($row = mysqli_fetch_assoc($result)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

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
    .form-card { background: #1a1a1a; border-radius: 15px; padding: 2rem; max-width: 600px; border: 1px solid rgba(212, 175, 55, 0.3); }
    .form-group { margin-bottom: 1.5rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; color: #d4af37; font-weight: 500; }
    .form-control { width: 100%; padding: 12px; background: #2a2a2a; border: 1px solid rgba(212, 175, 55, 0.3); border-radius: 8px; color: white; }
    .btn-primary { background: #d4af37; color: #050505; border: none; padding: 12px 30px; border-radius: 25px; cursor: pointer; font-weight: bold; }
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 1rem; }
    .alert-success { background: rgba(40, 167, 69, 0.2); border: 1px solid #28a745; color: #28a745; }
    h1 { color: #d4af37; margin-bottom: 2rem; }
    @media (max-width: 768px) { .super-container { flex-direction: column; } .sidebar { width: 100%; } }
</style>

<div class="super-container">
    <aside class="sidebar">
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="salons.php">🏢 Salons</a></li>
            <li><a href="admins.php">👨‍💼 Owners</a></li>
            <li><a href="subscriptions.php">💰 Subscriptions</a></li>
            <li><a href="settings.php" class="active">⚙️ Settings</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <h1>⚙️ System Settings</h1>
        <?php echo $message; ?>
        <div class="form-card">
            <form method="POST">
                <div class="form-group">
                    <label>Site Name</label>
                    <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($settings['site_name'] ?? 'Salon Pro'); ?>">
                </div>
                <div class="form-group">
                    <label>Contact Email</label>
                    <input type="email" name="contact_email" class="form-control" value="<?php echo htmlspecialchars($settings['contact_email'] ?? 'info@salonpro.com'); ?>">
                </div>
                <div class="form-group">
                    <label>Contact Phone</label>
                    <input type="tel" name="contact_phone" class="form-control" value="<?php echo htmlspecialchars($settings['contact_phone'] ?? '+254 712 345 678'); ?>">
                </div>
                <button type="submit" class="btn-primary">💾 Save Settings</button>
            </form>
        </div>
    </main>
</div>
<?php include '../includes/footer.php'; ?>