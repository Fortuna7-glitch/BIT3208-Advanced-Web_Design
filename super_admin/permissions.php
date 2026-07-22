<?php
/**
 * Salon Pro — Super Admin: Global Permissions Management
 * Luxury gold/black theme
 * Super Admin can manage permissions for ALL users across ALL salons
 */

require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['user_name'] ?? 'Super Admin';

$error = '';
$success = '';
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

// ============================================
// GET ALL USERS (Across all salons)
// ============================================
$users_list = [];
$all_users_query = "SELECT u.id, u.full_name, u.email, u.phone, u.role, u.is_active, s.salon_name 
                    FROM users u 
                    LEFT JOIN salons s ON u.salon_id = s.id 
                    WHERE u.role IN ('admin', 'staff', 'customer') 
                    ORDER BY u.role, u.full_name";
$all_users_result = mysqli_query($conn, $all_users_query);
while ($row = mysqli_fetch_assoc($all_users_result)) {
    $users_list[] = $row;
}

// ============================================
// GET SELECTED USER
// ============================================
$selected_user = null;
$user_permissions = [];
if ($user_id > 0) {
    $user_query = "SELECT u.*, s.salon_name 
                   FROM users u 
                   LEFT JOIN salons s ON u.salon_id = s.id 
                   WHERE u.id = $user_id";
    $user_result = mysqli_query($conn, $user_query);
    $selected_user = mysqli_fetch_assoc($user_result);
    
    if ($selected_user) {
        $user_permissions = getStaffPermissions($user_id);
    }
}

// ============================================
// GET ALL PERMISSIONS
// ============================================
$permission_groups = getAllPermissionsGrouped();

// ============================================
// GET TEMPLATES
// ============================================
$templates_result = getPermissionTemplates();

// ============================================
// HANDLE ACTIONS
// ============================================

// Save/Update Permissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_permissions'])) {
    $user_id = (int)$_POST['user_id'];
    $admin_id = $_SESSION['user_id'];
    
    // Check if user exists
    $check_query = "SELECT id FROM users WHERE id = $user_id";
    $check_result = mysqli_query($conn, $check_query);
    if (mysqli_num_rows($check_result) == 0) {
        $error = "User not found.";
    } else {
        // Delete existing permissions
        $delete_query = "DELETE FROM staff_permissions WHERE staff_id = $user_id";
        mysqli_query($conn, $delete_query);
        
        // Insert new permissions
        $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];
        $inserted = 0;
        
        foreach ($permissions as $perm_id => $granted) {
            $perm_id = (int)$perm_id;
            $granted = (int)$granted;
            $query = "INSERT INTO staff_permissions (staff_id, permission_id, granted, granted_by) 
                      VALUES ($user_id, $perm_id, $granted, $admin_id)";
            if (mysqli_query($conn, $query)) {
                $inserted++;
            }
        }
        
        if ($inserted > 0) {
            logAudit('permissions_updated_super', 'permissions', "Super Admin updated permissions for user ID $user_id ($inserted permissions assigned)", $admin_id);
            $success = "Permissions updated successfully! ($inserted permissions assigned)";
            // Refresh permissions
            $user_permissions = getStaffPermissions($user_id);
        } else {
            $error = "No permissions were saved. Please select at least one permission.";
        }
    }
}

// Apply Template
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_template'])) {
    $user_id = (int)$_POST['user_id'];
    $template_id = (int)$_POST['template_id'];
    $admin_id = $_SESSION['user_id'];
    
    // Check if user exists
    $check_query = "SELECT id FROM users WHERE id = $user_id";
    $check_result = mysqli_query($conn, $check_query);
    if (mysqli_num_rows($check_result) == 0) {
        $error = "User not found.";
    } else {
        if (applyPermissionTemplate($user_id, $template_id, $admin_id)) {
            logAudit('template_applied_super', 'permissions', "Super Admin applied template ID $template_id to user ID $user_id", $admin_id);
            $success = "Template applied successfully!";
            $user_permissions = getStaffPermissions($user_id);
        } else {
            $error = "Failed to apply template.";
        }
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================

function getPermissionStatus($user_permissions, $perm_id) {
    return isset($user_permissions[$perm_id]) && $user_permissions[$perm_id] == 1;
}

function getRoleBadgeClass($role) {
    $classes = [
        'super_admin' => 'role-super',
        'admin' => 'role-admin',
        'staff' => 'role-staff',
        'customer' => 'role-customer'
    ];
    return $classes[$role] ?? 'role-other';
}

// Get unread notification count
$unread_count = 0;
if (function_exists('getUnreadNotificationCount')) {
    $unread_count = getUnreadNotificationCount();
}

include '../includes/header.php';
?>

<style>
    .main-content {
        padding: 0 2rem 2rem;
        background: #0a0a0a;
        min-height: 100vh;
        margin-top: 0.5rem;
    }

    .sticky-header {
        position: sticky;
        top: 65px;
        z-index: 100;
        background: #0a0a0a;
        padding: 0.5rem 0 0.8rem 0;
        border-bottom: 1px solid rgba(212, 175, 55, 0.08);
    }

    .top-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        padding: 0.2rem 0;
    }

    .top-bar-left {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex: 0 0 auto;
    }

    .breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        color: #b8b2a0;
        font-size: 0.9rem;
    }
    .breadcrumb .current {
        color: #f0d878;
        font-weight: 600;
    }
    .breadcrumb .sep {
        color: #7a7568;
    }
    .breadcrumb .sub {
        color: #7a7568;
    }
    .breadcrumb .menu-icon {
        font-size: 1.3rem;
        color: #d4af37;
        cursor: pointer;
    }

    .top-bar-center {
        display: flex;
        align-items: center;
        gap: 0.3rem;
        flex-wrap: wrap;
        flex: 1 1 auto;
        justify-content: center;
    }

    .quick-links {
        display: flex;
        align-items: center;
        gap: 0.1rem;
        flex-wrap: wrap;
    }

    .quick-links .link-sep {
        color: #7a7568;
        font-size: 0.7rem;
        opacity: 0.4;
        font-weight: 100;
    }

    .quick-links .qlink {
        color: #b8b2a0;
        text-decoration: none;
        font-size: 0.8rem;
        padding: 0.3rem 0.7rem;
        border-radius: 20px;
        transition: all 0.3s ease;
        white-space: nowrap;
        border: 1px solid transparent;
    }

    .quick-links .qlink:hover {
        color: #f0d878;
        background: rgba(212, 175, 55, 0.08);
        border-color: rgba(212, 175, 55, 0.15);
    }

    .quick-links .qlink.active {
        color: #f0d878;
        background: rgba(212, 175, 55, 0.12);
        border-color: rgba(212, 175, 55, 0.2);
    }

    .top-bar-right {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex: 0 0 auto;
    }

    .top-bar-right .icon-btn {
        position: relative;
        color: #f0d878;
        font-size: 1.1rem;
        cursor: pointer;
        text-decoration: none;
        padding: 0.3rem 0.5rem;
        border-radius: 6px;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .top-bar-right .icon-btn .badge {
        position: absolute;
        top: -4px;
        right: -4px;
        background: #dc3545;
        color: white;
        font-size: 0.5rem;
        font-weight: 700;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .top-bar-right .topbar-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #0e0e0e;
        border: 1px solid #d4af37;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #f0d878;
        font-size: 0.9rem;
    }

    .welcome-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 0.8rem 0 1.2rem 0;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .welcome-left h1 {
        font-size: 1.6rem;
        font-weight: 700;
        color: #f0d878;
        font-family: 'Playfair Display', serif;
        margin: 0;
    }

    .welcome-left .subtitle {
        font-size: 0.9rem;
        color: #7a7568;
        margin-top: 0.2rem;
    }

    .date-badge {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border: 1px solid rgba(212, 175, 55, 0.25);
        border-radius: 8px;
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
        color: #b8b2a0;
        flex-shrink: 0;
        white-space: nowrap;
    }

    .date-badge i {
        color: #d4af37;
    }

    .permissions-container {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 2rem;
        margin-top: 1rem;
    }

    /* Users List Sidebar */
    .users-list {
        background: #0e0e0e;
        border-radius: 12px;
        padding: 1.5rem;
        border: 1px solid rgba(212, 175, 55, 0.25);
        max-height: 600px;
        overflow-y: auto;
    }

    .users-list h3 {
        color: #f0d878;
        margin-bottom: 1rem;
        font-size: 1rem;
    }

    .users-list .user-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 12px;
        border-radius: 8px;
        margin-bottom: 4px;
        transition: all 0.3s;
        cursor: pointer;
        text-decoration: none;
        color: white;
        border-left: 3px solid transparent;
    }

    .users-list .user-item:hover {
        background: rgba(212, 175, 55, 0.1);
    }

    .users-list .user-item.active {
        background: rgba(212, 175, 55, 0.15);
        border-left-color: #d4af37;
    }

    .users-list .user-item .user-info {
        flex: 1;
    }

    .users-list .user-item .user-name {
        font-weight: 500;
        font-size: 0.85rem;
    }

    .users-list .user-item .user-email {
        font-size: 0.7rem;
        color: #7a7568;
    }

    .users-list .user-item .user-salon {
        font-size: 0.6rem;
        color: #d4af37;
    }

    .role-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.55rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .role-badge.role-super { background: rgba(212, 175, 55, 0.2); color: #d4af37; border: 1px solid #d4af37; }
    .role-badge.role-admin { background: rgba(23, 162, 184, 0.15); color: #17a2b8; border: 1px solid #17a2b8; }
    .role-badge.role-staff { background: rgba(40, 167, 69, 0.15); color: #28a745; border: 1px solid #28a745; }
    .role-badge.role-customer { background: rgba(111, 66, 193, 0.15); color: #6f42c1; border: 1px solid #6f42c1; }

    /* Permissions Panel */
    .permissions-panel {
        background: #0e0e0e;
        border-radius: 12px;
        padding: 1.5rem;
        border: 1px solid rgba(212, 175, 55, 0.25);
    }

    .permissions-panel .user-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(212, 175, 55, 0.1);
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .permissions-panel .user-header h3 {
        color: #f0d878;
        font-size: 1.1rem;
        margin: 0;
    }

    .permissions-panel .user-header .user-info {
        color: #b8b2a0;
        font-size: 0.85rem;
    }

    .permissions-panel .user-header .user-info span {
        color: #7a7568;
    }

    .perm-group {
        margin-bottom: 1.5rem;
    }

    .perm-group .group-title {
        color: #d4af37;
        font-size: 0.9rem;
        font-weight: 600;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid rgba(212, 175, 55, 0.1);
        margin-bottom: 0.8rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .perm-group .group-title .select-all {
        font-size: 0.7rem;
        color: #7a7568;
        cursor: pointer;
        background: none;
        border: none;
    }

    .perm-group .group-title .select-all:hover {
        color: #d4af37;
    }

    .perm-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 5px 12px;
        border-radius: 6px;
        margin-bottom: 2px;
        transition: all 0.3s;
    }

    .perm-item:hover {
        background: rgba(212, 175, 55, 0.05);
    }

    .perm-item .perm-label {
        font-size: 0.85rem;
        color: #ddd;
    }

    .perm-item .perm-checkbox {
        position: relative;
        width: 40px;
        height: 22px;
        cursor: pointer;
        flex-shrink: 0;
    }

    .perm-item .perm-checkbox input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .perm-item .perm-checkbox .slider {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: #2a2a2a;
        border-radius: 34px;
        transition: all 0.3s;
    }

    .perm-item .perm-checkbox .slider:before {
        content: "";
        position: absolute;
        height: 16px;
        width: 16px;
        left: 3px;
        bottom: 3px;
        background: #666;
        border-radius: 50%;
        transition: all 0.3s;
    }

    .perm-item .perm-checkbox input:checked + .slider {
        background: #d4af37;
    }

    .perm-item .perm-checkbox input:checked + .slider:before {
        transform: translateX(18px);
        background: #050505;
    }

    .template-selector {
        display: flex;
        gap: 1rem;
        align-items: center;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        background: #1a1a1a;
        padding: 0.8rem 1.2rem;
        border-radius: 8px;
        border: 1px solid rgba(212, 175, 55, 0.1);
    }

    .template-selector select {
        padding: 8px 12px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 8px;
        color: white;
        flex: 1;
        min-width: 150px;
    }

    .template-selector .btn-apply {
        padding: 8px 20px;
        background: rgba(212, 175, 55, 0.2);
        border: 1px solid #d4af37;
        color: #d4af37;
        border-radius: 25px;
        cursor: pointer;
        transition: all 0.3s;
    }

    .template-selector .btn-apply:hover {
        background: #d4af37;
        color: #050505;
    }

    .btn-save {
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
        margin-top: 1rem;
    }

    .btn-save:hover {
        background: #f0d878;
        transform: translateY(-2px);
    }

    .btn-save:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
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
        color: #7a7568;
    }

    .empty-state .icon {
        font-size: 3rem;
        margin-bottom: 1rem;
    }

    .back-link {
        display: inline-block;
        margin-top: 1.5rem;
        color: #f0d878;
        text-decoration: none;
    }

    @media (max-width: 1024px) {
        .permissions-container {
            grid-template-columns: 1fr;
        }
        .users-list {
            max-height: 250px;
        }
    }

    @media (max-width: 768px) {
        .main-content { padding: 0 1rem 1rem; }
        .top-bar {
            flex-direction: column;
            align-items: stretch;
            gap: 0.5rem;
        }
        .top-bar-left { width: 100%; }
        .top-bar-center { width: 100%; justify-content: flex-start; }
        .top-bar-right { width: 100%; justify-content: flex-start; }
        .top-bar-right .icon-btn { font-size: 0.95rem; padding: 0.2rem 0.4rem; }
        .top-bar-right .topbar-avatar { width: 28px; height: 28px; font-size: 0.75rem; }
        .quick-links .qlink { font-size: 0.7rem; padding: 0.2rem 0.4rem; }
        .welcome-row { flex-direction: column; align-items: flex-start; }
        .welcome-left h1 { font-size: 1.3rem; }
        .date-badge { align-self: flex-start; }
        .permissions-panel { padding: 1rem; }
        .users-list { padding: 1rem; }
        .template-selector { flex-direction: column; align-items: stretch; }
        .perm-item { padding: 4px 8px; }
        .perm-item .perm-label { font-size: 0.8rem; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0 0.8rem 0.8rem; }
        .quick-links .qlink { font-size: 0.65rem; padding: 0.15rem 0.3rem; }
        .welcome-left h1 { font-size: 1.1rem; }
        .date-badge { font-size: 0.75rem; padding: 0.3rem 0.6rem; }
        .top-bar-right .icon-btn { font-size: 0.8rem; }
        .top-bar-right .icon-btn .badge { width: 14px; height: 14px; font-size: 0.4rem; top: -2px; right: -2px; }
        .permissions-container { gap: 1rem; }
        .permissions-panel .user-header { flex-direction: column; align-items: flex-start; }
    }
</style>

<div class="main-content">

    <div class="sticky-header">
        <div class="top-bar">
            <div class="top-bar-left">
                <div class="breadcrumb">
                    <i class="ti ti-menu-2 menu-icon"></i>
                    <span class="current">Dashboard</span>
                    <span class="sep">/</span>
                    <span class="sub">Global Permissions</span>
                </div>
            </div>

            <div class="top-bar-center">
                <div class="quick-links">
                    <a href="salons.php" class="qlink"><i class="ti ti-building-store"></i> Manage Salons</a>
                    <span class="link-sep">|</span>
                    <a href="admins.php" class="qlink"><i class="ti ti-user-shield"></i> Manage Owners</a>
                    <span class="link-sep">|</span>
                    <a href="subscriptions.php" class="qlink"><i class="ti ti-crown"></i> Subscriptions</a>
                    <span class="link-sep">|</span>
                    <a href="staff.php" class="qlink"><i class="ti ti-users"></i> Staff</a>
                    <span class="link-sep">|</span>
                    <a href="settings.php" class="qlink"><i class="ti ti-settings"></i> System Settings</a>
                    <span class="link-sep">|</span>
                    <a href="products.php" class="qlink"><i class="ti ti-box"></i> Products</a>
                    <span class="link-sep">|</span>
                    <a href="permissions.php" class="qlink active"><i class="ti ti-key"></i> Permissions</a>
                </div>
            </div>

            <div class="top-bar-right">
                <a href="#" class="icon-btn" id="searchToggle" title="Search (Ctrl+K)">
                    <i class="ti ti-search"></i>
                </a>
                <a href="notifications.php" class="icon-btn" title="Notifications">
                    <i class="ti ti-bell"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="badge"><?php echo min($unread_count, 99); ?></span>
                    <?php endif; ?>
                </a>
                <div class="topbar-avatar"><i class="ti ti-crown"></i></div>
            </div>
        </div>
    </div>

    <div class="welcome-row">
        <div class="welcome-left">
            <h1>🔑 Global Permissions</h1>
            <p class="subtitle">Manage permissions for ALL users across ALL salons</p>
        </div>
        <div class="date-badge">
            <i class="ti ti-calendar"></i> <?php echo date('j F Y'); ?>
        </div>
    </div>

    <?php if($error): ?>
        <div class="alert alert-danger">❌ <?php echo $error; ?></div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="alert alert-success">✅ <?php echo $success; ?></div>
    <?php endif; ?>

    <div class="permissions-container">

        <!-- LEFT: Users List -->
        <div class="users-list">
            <h3>👥 All Users</h3>

            <?php if(empty($users_list)): ?>
                <p class="empty-state">No users found.</p>
            <?php else: ?>
                <?php foreach($users_list as $user): ?>
                    <a href="permissions.php?user_id=<?php echo $user['id']; ?>" class="user-item <?php echo ($user_id == $user['id']) ? 'active' : ''; ?>">
                        <div class="user-info">
                            <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                            <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                            <div class="user-salon"><?php echo htmlspecialchars($user['salon_name'] ?? 'Unassigned'); ?></div>
                        </div>
                        <span class="role-badge <?php echo getRoleBadgeClass($user['role']); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- RIGHT: Permissions Panel -->
        <div class="permissions-panel">
            <?php if($selected_user): ?>
                <div class="user-header">
                    <div>
                        <h3>👤 <?php echo htmlspecialchars($selected_user['full_name']); ?></h3>
                        <div class="user-info">
                            <span>Role:</span> <?php echo ucfirst(str_replace('_', ' ', $selected_user['role'])); ?>
                            &nbsp;|&nbsp; <span>Salon:</span> <?php echo htmlspecialchars($selected_user['salon_name'] ?? 'Unassigned'); ?>
                            &nbsp;|&nbsp; <span>Status:</span> <?php echo $selected_user['is_active'] ? '✅ Active' : '❌ Inactive'; ?>
                        </div>
                    </div>
                    <span class="role-badge <?php echo getRoleBadgeClass($selected_user['role']); ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $selected_user['role'])); ?>
                    </span>
                </div>

                <!-- Template Selector -->
                <div class="template-selector">
                    <form method="POST" style="display: flex; gap: 1rem; flex: 1; align-items: center; flex-wrap: wrap;">
                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                        <select name="template_id" required>
                            <option value="">-- Apply Template --</option>
                            <?php while($template = mysqli_fetch_assoc($templates_result)): ?>
                                <option value="<?php echo $template['id']; ?>">
                                    <?php echo htmlspecialchars($template['template_name']); ?>
                                    <?php if($template['is_system']): ?> ⭐<?php endif; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <button type="submit" name="apply_template" class="btn-apply" onclick="return confirm('This will replace all current permissions. Continue?')">Apply Template</button>
                    </form>
                </div>

                <!-- Permissions Form -->
                <form method="POST">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">

                    <?php foreach($permission_groups as $group_name => $permissions): ?>
                        <div class="perm-group">
                            <div class="group-title">
                                <?php echo ucfirst($group_name); ?>
                                <button type="button" class="select-all" onclick="toggleGroup(this, '<?php echo $group_name; ?>')">Select All</button>
                            </div>
                            <?php foreach($permissions as $perm): ?>
                                <div class="perm-item">
                                    <span class="perm-label"><?php echo htmlspecialchars($perm['description']); ?></span>
                                    <label class="perm-checkbox">
                                        <input type="checkbox" 
                                               name="permissions[<?php echo $perm['id']; ?>]" 
                                               value="1" 
                                               <?php echo getPermissionStatus($user_permissions, $perm['id']) ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" name="save_permissions" class="btn-save">💾 Save Permissions</button>
                </form>

            <?php else: ?>
                <div class="empty-state">
                    <div class="icon">👈</div>
                    <h3>Select a User</h3>
                    <p>Choose a user from the left to manage their permissions.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<script>
    function toggleGroup(button, groupName) {
        const groupDiv = button.closest('.perm-group');
        const checkboxes = groupDiv.querySelectorAll('.perm-checkbox input[type="checkbox"]');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        
        checkboxes.forEach(cb => {
            cb.checked = !allChecked;
        });
        
        button.textContent = allChecked ? 'Select All' : 'Deselect All';
    }
</script>

<?php include '../includes/footer.php'; ?>