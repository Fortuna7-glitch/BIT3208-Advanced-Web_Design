<?php
// admin/permissions.php - Manage staff permissions
require_once '../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
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

// Get all staff members
$staff_list = mysqli_query($conn, "SELECT id, full_name, email, specialization FROM users WHERE role = 'staff'");

// Get all permissions
$all_permissions = mysqli_query($conn, "SELECT * FROM permissions");

include '../includes/header.php';
?>

<style>
    .permissions-container { padding: 2rem; }
    .staff-card { background: #1a1a1a; border-radius: 15px; padding: 1.5rem; margin-bottom: 2rem; border: 1px solid rgba(212, 175, 55, 0.3); }
    .staff-card h3 { color: #d4af37; margin-bottom: 1rem; }
    .perm-checkbox { margin-right: 15px; margin-bottom: 10px; display: inline-block; }
    .perm-checkbox label { margin-left: 5px; cursor: pointer; }
    .btn-primary { padding: 10px 20px; background: #d4af37; color: #050505; border: none; border-radius: 5px; cursor: pointer; }
    .alert-success { background: rgba(40, 167, 69, 0.2); border: 1px solid #28a745; color: #28a745; padding: 15px; border-radius: 8px; margin-bottom: 1rem; }
</style>

<div class="permissions-container">
    <h1>🔐 Staff Permission Manager</h1>
    
    <?php if(isset($success)): ?>
        <div class="alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
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
        <p>Email: <?php echo htmlspecialchars($staff['email']); ?></p>
        <p>Specialization: <?php echo htmlspecialchars($staff['specialization'] ?? 'Not set'); ?></p>
        
        <form method="POST">
            <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
            <h4 style="margin: 1rem 0 0.5rem 0;">Permissions:</h4>
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
                <button type="submit" name="update_permissions" class="btn-primary">Save Permissions</button>
            </div>
        </form>
    </div>
    <?php endwhile; ?>
</div>

<?php include '../includes/footer.php'; ?>