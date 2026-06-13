<?php
// super_admin/demo_admin.php - Read-only demo of admin panel for super admin
require_once '../config/database.php';

// Only super admin can access this demo
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

// Temporarily store original role to restore later
$original_role = $_SESSION['user_role'];

// Set a demo flag so admin pages show in read-only mode
$_SESSION['demo_mode'] = true;

// Now include the regular admin dashboard
// But we need to modify admin/dashboard.php to show read-only
// For now, just include it - we'll update admin/dashboard.php to check for demo_mode
include '../admin/dashboard.php';

// Restore original role (though this won't run until after the included file executes)
// $_SESSION['user_role'] = $original_role;
// unset($_SESSION['demo_mode']);
?>