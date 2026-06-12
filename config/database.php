<?php
// config/database.php - COMPLETE FILE with permission checking functions

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'saloon_management_system';
$db_port = 3306;

// Try to connect
$conn = null;
$connection_error = '';

try {
    @$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
    
    if (!$conn) {
        @$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
        
        if (!$conn) {
            $connection_error = mysqli_connect_error();
            throw new Exception("MySQL Connection Failed: " . $connection_error);
        }
    }
    
    mysqli_set_charset($conn, "utf8mb4");
    
} catch (Exception $e) {
    die("
    <div style='font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #1a1a1a; border-left: 4px solid #d4af37; color: white;'>
        <h2 style='color: #d4af37;'>⚠️ Database Connection Error</h2>
        <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        <hr style='border-color: #333;'>
        <h3>Solutions:</h3>
        <ol>
            <li><strong>Start MySQL in XAMPP:</strong> Open XAMPP Control Panel → Click 'Start' next to MySQL</li>
            <li><strong>Check if MySQL is running:</strong> Look for green 'Running' label next to MySQL</li>
            <li><strong>Restart XAMPP:</strong> Stop both Apache and MySQL, then start them again</li>
        </ol>
        <p><a href='javascript:location.reload()' style='color: #d4af37;'>↻ Try Again</a></p>
    </div>
    ");
}

// Set timezone
date_default_timezone_set('Africa/Nairobi');

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// USER ROLE CHECKING FUNCTIONS
// ============================================

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin';
}

function isStaff() {
    return isset($_SESSION['user_role']) && ($_SESSION['user_role'] == 'admin' || $_SESSION['user_role'] == 'staff');
}

function isCustomer() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'customer';
}

function redirect($url) {
    header("Location: $url");
    exit();
}

// ============================================
// PERMISSION CHECKING FUNCTIONS
// ============================================

/**
 * Check if a staff member has a specific permission
 * @param int $staff_id - The staff user ID
 * @param string $permission_name - The permission name (e.g., 'book_for_customers')
 * @return bool - True if has permission, false otherwise
 */
function hasPermission($staff_id, $permission_name) {
    global $conn;
    
    // Admin always has all permissions
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin') {
        return true;
    }
    
    $query = "SELECT sp.can_access 
              FROM staff_permissions sp
              JOIN permissions p ON sp.permission_id = p.id
              WHERE sp.staff_id = $staff_id 
              AND p.permission_name = '$permission_name'
              AND sp.can_access = 1";
    
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return true;
    }
    
    return false;
}

/**
 * Get all permissions for a staff member
 * @param int $staff_id - The staff user ID
 * @return array - List of permission names
 */
function getStaffPermissions($staff_id) {
    global $conn;
    
    $permissions = [];
    
    // Admin has all permissions
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin') {
        // Return all possible permissions for admin
        $all_perms = mysqli_query($conn, "SELECT permission_name FROM permissions");
        while ($row = mysqli_fetch_assoc($all_perms)) {
            $permissions[] = $row['permission_name'];
        }
        return $permissions;
    }
    
    $query = "SELECT p.permission_name 
              FROM staff_permissions sp
              JOIN permissions p ON sp.permission_id = p.id
              WHERE sp.staff_id = $staff_id AND sp.can_access = 1";
    
    $result = mysqli_query($conn, $query);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $permissions[] = $row['permission_name'];
    }
    
    return $permissions;
}

/**
 * Grant a permission to a staff member
 * @param int $staff_id - The staff user ID
 * @param string $permission_name - The permission name
 * @param int $granted_by - Admin user ID who grants this
 * @return bool - Success or failure
 */
function grantPermission($staff_id, $permission_name, $granted_by) {
    global $conn;
    
    // Get permission ID
    $perm_query = "SELECT id FROM permissions WHERE permission_name = '$permission_name'";
    $perm_result = mysqli_query($conn, $perm_query);
    
    if ($perm_result && $perm = mysqli_fetch_assoc($perm_result)) {
        $permission_id = $perm['id'];
        
        // Check if already exists
        $check = mysqli_query($conn, "SELECT id FROM staff_permissions WHERE staff_id = $staff_id AND permission_id = $permission_id");
        
        if (mysqli_num_rows($check) > 0) {
            // Update existing
            $query = "UPDATE staff_permissions SET can_access = 1, granted_by = $granted_by WHERE staff_id = $staff_id AND permission_id = $permission_id";
        } else {
            // Insert new
            $query = "INSERT INTO staff_permissions (staff_id, permission_id, can_access, granted_by) VALUES ($staff_id, $permission_id, 1, $granted_by)";
        }
        
        return mysqli_query($conn, $query);
    }
    
    return false;
}

/**
 * Revoke a permission from a staff member
 * @param int $staff_id - The staff user ID
 * @param string $permission_name - The permission name
 * @return bool - Success or failure
 */
function revokePermission($staff_id, $permission_name) {
    global $conn;
    
    $perm_query = "SELECT id FROM permissions WHERE permission_name = '$permission_name'";
    $perm_result = mysqli_query($conn, $perm_query);
    
    if ($perm_result && $perm = mysqli_fetch_assoc($perm_result)) {
        $permission_id = $perm['id'];
        $query = "DELETE FROM staff_permissions WHERE staff_id = $staff_id AND permission_id = $permission_id";
        return mysqli_query($conn, $query);
    }
    
    return false;
}

// ============================================
// NOTIFICATION FUNCTIONS
// ============================================

function sendNotification($user_id, $title, $message, $type = 'email') {
    global $conn;
    $query = "INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())";
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "isss", $user_id, $title, $message, $type);
        return mysqli_stmt_execute($stmt);
    }
    return false;
}

function sendSMS($phone, $message) {
    // Log SMS for testing
    $log = date('Y-m-d H:i:s') . " - SMS to: $phone - Message: $message\n";
    file_put_contents(__DIR__ . '/../sms_log.txt', $log, FILE_APPEND);
    return true;
}

function sendEmail($to, $subject, $body) {
    // Log email for testing
    $log = date('Y-m-d H:i:s') . " - Email to: $to - Subject: $subject\n";
    file_put_contents(__DIR__ . '/../email_log.txt', $log, FILE_APPEND);
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: Salon Pro <noreply@salonpro.com>\r\n";
    return mail($to, $subject, $body, $headers);
}
?>