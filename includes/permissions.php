<?php
// includes/permissions.php - PERMISSION CHECK HELPERS
// Include this file in any file that needs permission checks

if (!function_exists('hasPermission')) {
    require_once dirname(__DIR__) . '/config/database.php';
}

/**
 * Check if the current logged-in user has a specific permission
 * @param string $permission_name - The permission name to check
 * @return bool - True if has permission
 */
function currentUserHasPermission($permission_name) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    return hasPermission($_SESSION['user_id'], $permission_name);
}

/**
 * Check if the current user is a customer
 * @return bool
 */
function isCurrentUserCustomer() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'customer';
}

/**
 * Check if the current user is staff or admin
 * @return bool
 */
function isCurrentUserStaff() {
    return isset($_SESSION['user_role']) && ($_SESSION['user_role'] == 'staff' || $_SESSION['user_role'] == 'admin');
}

/**
 * Check if the current user is admin
 * @return bool
 */
function isCurrentUserAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin';
}

/**
 * Check if the current user is super admin
 * @return bool
 */
function isCurrentUserSuperAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'super_admin';
}

/**
 * Show a permission denied message with back link
 */
function permissionDenied() {
    ?>
    <div style="text-align: center; padding: 3rem; background: #1a1a1a; border-radius: 15px; border: 1px solid rgba(212, 175, 55, 0.2);">
        <div style="font-size: 3rem; margin-bottom: 1rem;">🚫</div>
        <h2 style="color: #dc3545;">Permission Denied</h2>
        <p style="color: #aaa;">You don't have permission to access this page.</p>
        <a href="dashboard.php" style="display: inline-block; margin-top: 1rem; padding: 10px 25px; background: #d4af37; color: #050505; border-radius: 25px; text-decoration: none; font-weight: 600;">Back to Dashboard</a>
    </div>
    <?php
    exit();
}

/**
 * Require a specific permission, otherwise show permission denied
 * @param string $permission_name - The permission required
 */
function requirePermission($permission_name) {
    if (!currentUserHasPermission($permission_name)) {
        permissionDenied();
    }
}

/**
 * Require that the user is logged in, otherwise redirect to login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('../auth/login.php');
    }
}

/**
 * Require that the user is admin, otherwise show permission denied
 */
function requireAdmin() {
    if (!isCurrentUserAdmin() && !isCurrentUserSuperAdmin()) {
        permissionDenied();
    }
}

/**
 * Require that the user is staff or admin, otherwise show permission denied
 */
function requireStaff() {
    if (!isCurrentUserStaff() && !isCurrentUserAdmin() && !isCurrentUserSuperAdmin()) {
        permissionDenied();
    }
}

/**
 * Require that the user is customer, otherwise redirect to login
 */
function requireCustomer() {
    if (!isLoggedIn() || !isCurrentUserCustomer()) {
        redirect('../auth/login.php');
    }
}

/**
 * Get all permission names for the current user
 * @return array - List of permission names the user has
 */
function getCurrentUserPermissionNames() {
    if (!isset($_SESSION['user_id'])) {
        return [];
    }
    $permissions = getStaffPermissions($_SESSION['user_id']);
    return array_keys(array_filter($permissions));
}

/**
 * Get HTML badge for permission status
 * @param bool $has_permission - Whether the user has the permission
 * @return string - HTML badge
 */
function permissionBadge($has_permission) {
    if ($has_permission) {
        return '<span style="display: inline-block; padding: 2px 10px; border-radius: 20px; background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; font-size: 0.7rem; font-weight: 600;">✅ Has</span>';
    } else {
        return '<span style="display: inline-block; padding: 2px 10px; border-radius: 20px; background: rgba(220, 53, 69, 0.2); color: #dc3545; border: 1px solid #dc3545; font-size: 0.7rem; font-weight: 600;">❌ No</span>';
    }
}

/**
 * Get HTML select option for permission
 * @param string $permission_name - The permission name
 * @param bool $has_permission - Whether the user has the permission
 * @return string - HTML option
 */
function permissionSelectOption($permission_name, $has_permission) {
    $selected = $has_permission ? 'selected' : '';
    return "<option value='1' $selected>Granted</option><option value='0' " . (!$has_permission ? 'selected' : '') . ">Denied</option>";
}
?>