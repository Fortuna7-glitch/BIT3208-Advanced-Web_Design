<?php
// admin/permissions.php - UPDATED with feature access check
require_once '../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

// Get salon_id from session
$salon_id = $_SESSION['salon_id'] ?? 0;

if ($salon_id <= 0) {
    $user_id = $_SESSION['user_id'];
    $user_query = mysqli_query($conn, "SELECT salon_id FROM users WHERE id = $user_id");
    if ($user_result = mysqli_fetch_assoc($user_query)) {
        $salon_id = $user_result['salon_id'];
        $_SESSION['salon_id'] = $salon_id;
    }
}

// ============================================
// FEATURE ACCESS CHECK
// ============================================
if (!hasFeature($salon_id, 'permissions')) {
    $_SESSION['upgrade_required'] = 'permissions';
    redirect('dashboard.php');
}

// Handle permission updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_permissions'])) {
    $staff_id = $_POST['staff_id'];
    
    // Delete existing permissions
    mysqli_query($conn, "DELETE FROM staff_permissions WHERE staff_id = $staff_id");
    
    // Insert new permissions
    if (isset($_POST['permissions'])) {
        foreach ($_POST['permissions'] as $perm_id) {
            mysqli_query($conn, "INSERT INTO staff_permissions (staff_id, permission_id, can_access, granted_by) 
                                VALUES ($staff_id, $perm_id, 1, {$_SESSION['user_id']})");
        }
    }
    
    $success = "Permissions updated successfully!";
}

// Get all staff members for THIS salon
$staff_list = mysqli_query($conn, "SELECT id, full_name, email, specialization FROM users WHERE role = 'staff' AND salon_id = $salon_id");

// Get all permissions
$all_permissions = mysqli_query($conn, "SELECT * FROM permissions");

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
    
    .permissions-container { padding: 0; }
    .staff-card { background: #1a1a1a; border-radius: 15px; padding: 1.5rem; margin-bottom: 2rem; border: 1px solid rgba(212, 175, 55, 0.3); }
    .staff-card h3 { color: #d4af37; margin-bottom: 1rem; }
    .perm-checkbox { margin-right: 15px; margin-bottom: 10px; display: inline-block; }
    .perm-checkbox label { margin-left: 5px; cursor: pointer; color: #ddd; }
    .perm-checkbox input[type="checkbox"] { cursor: pointer; accent-color: #d4af37; }
    .btn-primary { background: #d4af37; color: #050505; border: none; padding: 10px 25px; border-radius: 25px; cursor: pointer; font-weight: 600; transition: all 0.3s; }
    .btn-primary:hover { background: #f9e547; transform: translateY(-2px); }
    .alert-success { background: rgba(40, 167, 69, 0.2); border: 1px solid #28a745; color: #28a745; padding: 15px; border-radius: 8px; margin-bottom: 1rem; }
    h1 { color: #d4af37; margin-bottom: 2rem; }
    .no-staff { text-align: center; padding: 2rem; color: #888; }
    .staff-specialty { color: #888; font-size: 0.85rem; }
    
    @media (max-width: 768px) { .dashboard-container { flex-direction: column; } .sidebar { width: 100%; } }
</style>

<div class="dashboard-container">
    <aside class="sidebar">
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="appointments.php">📅 Appointments</a></li>
            <li><a href="services.php">💇 Services</a></li>
            <li><a href="staff.php">👥 Staff</a></li>
            <li><a href="customers.php">👤 Customers</a></li>
            <li><a href="payments.php">💰 Payments</a></li>
            <li><a href="reports.php">📈 Reports</a></li>
            <li><a href="permissions.php" class="active">🔐 Permissions</a></li>
            <li><a href="profile.php">⚙️ My Profile</a></li>
            <li><a href="../auth/logout.php">🚪 Logout</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <div class="permissions-container">
            <h1>🔐 Staff Permission Manager</h1>
            
            <?php if(isset($success)): ?>
                <div class="alert-success">✅ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if(mysqli_num_rows($staff_list) > 0): ?>
                <?php while($staff = mysqli_fetch_assoc($staff_list)): 
                    // Get current permissions for this staff
                    $current_perms = [];
                    $perm_result = mysqli_query($conn, "SELECT permission_id FROM staff_permissions WHERE staff_id = {$staff['id']} AND can_access = 1");
                    while($p = mysqli_fetch_assoc($perm_result)) {
                        $current_perms[] = $p['permission_id'];
                    }
                ?>
                <div class="staff-card">
                    <h3>👤 <?php echo htmlspecialchars($staff['full_name']); ?></h3>
                    <p class="staff-specialty">📧 <?php echo htmlspecialchars($staff['email']); ?></p>
                    <?php if(!empty($staff['specialization'])): ?>
                        <p class="staff-specialty">✂️ <?php echo htmlspecialchars($staff['specialization']); ?></p>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                        <h4 style="margin: 1rem 0 0.5rem 0; color: #d4af37;">Permissions:</h4>
                        <?php 
                        mysqli_data_seek($all_permissions, 0);
                        while($perm = mysqli_fetch_assoc($all_permissions)): 
                        ?>
                        <div class="perm-checkbox">
                            <input type="checkbox" name="permissions[]" value="<?php echo $perm['id']; ?>" 
                                id="perm_<?php echo $staff['id'] . '_' . $perm['id']; ?>"
                                <?php echo in_array($perm['id'], $current_perms) ? 'checked' : ''; ?>>
                            <label for="perm_<?php echo $staff['id'] . '_' . $perm['id']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $perm['permission_name'])); ?>
                            </label>
                        </div>
                        <?php endwhile; ?>
                        <div style="margin-top: 1rem;">
                            <button type="submit" name="update_permissions" class="btn-primary">💾 Save Permissions</button>
                        </div>
                    </form>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-staff">
                    <p>👥 No staff members found for your salon.</p>
                    <p style="font-size: 0.85rem; margin-top: 0.5rem;">Add staff members first to manage their permissions.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>