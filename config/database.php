<?php
// ============================================
// config/database.php - COMPLETE
// Updated to match your table structure
// ============================================

// ============================================
// DATABASE CONNECTION
// ============================================
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'saloon_management_system';

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set timezone to Nairobi
date_default_timezone_set('Africa/Nairobi');

// ============================================
// SESSION START
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// ROLE CHECK FUNCTIONS
// ============================================
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin';
}

function isSuperAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'super_admin';
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
// PERMISSION FUNCTIONS (RBAC)
// ============================================

/**
 * Check if a staff member has a specific permission
 * @param int $staff_id - The staff member ID
 * @param string $permission_name - The permission name to check
 * @return bool - True if has permission, False otherwise
 */
function hasPermission($staff_id, $permission_name) {
    global $conn;
    
    // Get user role first
    $user_query = "SELECT role FROM users WHERE id = $staff_id";
    $user_result = mysqli_query($conn, $user_query);
    
    if (!$user_result || mysqli_num_rows($user_result) == 0) {
        return false;
    }
    
    $user = mysqli_fetch_assoc($user_result);
    
    // Super Admin and Admin have ALL permissions
    if ($user['role'] == 'super_admin' || $user['role'] == 'admin') {
        return true;
    }
    
    // For staff, check specific permission
    $query = "SELECT sp.granted 
              FROM staff_permissions sp 
              JOIN permissions p ON sp.permission_id = p.id 
              WHERE sp.staff_id = $staff_id 
              AND p.permission_name = '$permission_name'
              AND (sp.expires_at IS NULL OR sp.expires_at > NOW())";
    
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $data = mysqli_fetch_assoc($result);
        return (bool)$data['granted'];
    }
    
    return false;
}

/**
 * Get all permissions for a staff member
 * @param int $staff_id - The staff member ID
 * @return array - Associative array of permission_name => granted (true/false)
 */
function getStaffPermissions($staff_id) {
    global $conn;
    
    $permissions = [];
    
    // Check if user is admin or super_admin
    $user_query = "SELECT role FROM users WHERE id = $staff_id";
    $user_result = mysqli_query($conn, $user_query);
    $user = mysqli_fetch_assoc($user_result);
    
    if ($user['role'] == 'super_admin' || $user['role'] == 'admin') {
        // Get all permissions and set them to true
        $all_perms = mysqli_query($conn, "SELECT permission_name FROM permissions");
        while ($row = mysqli_fetch_assoc($all_perms)) {
            $permissions[$row['permission_name']] = true;
        }
        return $permissions;
    }
    
    // For staff, get specific permissions
    $query = "SELECT p.permission_name, sp.granted 
              FROM staff_permissions sp 
              JOIN permissions p ON sp.permission_id = p.id 
              WHERE sp.staff_id = $staff_id
              AND (sp.expires_at IS NULL OR sp.expires_at > NOW())";
    $result = mysqli_query($conn, $query);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $permissions[$row['permission_name']] = (bool)$row['granted'];
    }
    
    return $permissions;
}

/**
 * Get all permissions (for admin UI)
 * @return mysqli_result - Result set of permissions
 */
function getAllPermissions() {
    global $conn;
    return mysqli_query($conn, "SELECT * FROM permissions ORDER BY permission_name");
}

/**
 * Get all permissions grouped by category
 * Note: Since your table doesn't have a category column,
 * this groups by the first word of the permission_name
 * @return array - Associative array of category => permissions
 */
function getAllPermissionsGrouped() {
    global $conn;
    
    $groups = [];
    $query = "SELECT * FROM permissions ORDER BY permission_name";
    $result = mysqli_query($conn, $query);
    
    while ($row = mysqli_fetch_assoc($result)) {
        // Extract category from permission_name (everything before first underscore)
        $category = explode('_', $row['permission_name'])[0];
        $category = ucfirst($category);
        
        if (!isset($groups[$category])) {
            $groups[$category] = [];
        }
        $groups[$category][] = $row;
    }
    
    return $groups;
}

/**
 * Grant a permission to a staff member
 * @param int $staff_id - The staff member ID
 * @param int $permission_id - The permission ID
 * @param int $granted_by - The admin who granted this
 * @return bool - True on success
 */
function grantPermission($staff_id, $permission_id, $granted_by) {
    global $conn;
    
    // Check if exists
    $check = mysqli_query($conn, "SELECT id FROM staff_permissions WHERE staff_id = $staff_id AND permission_id = $permission_id");
    
    if (mysqli_num_rows($check) > 0) {
        $query = "UPDATE staff_permissions SET granted = 1, granted_by = $granted_by, granted_at = NOW() 
                  WHERE staff_id = $staff_id AND permission_id = $permission_id";
    } else {
        $query = "INSERT INTO staff_permissions (staff_id, permission_id, granted, granted_by) 
                  VALUES ($staff_id, $permission_id, 1, $granted_by)";
    }
    
    if (mysqli_query($conn, $query)) {
        // Log audit
        $log = "INSERT INTO permission_audit (staff_id, changed_by, permission_id, new_value, action) 
                VALUES ($staff_id, $granted_by, $permission_id, 1, 'grant')";
        mysqli_query($conn, $log);
        return true;
    }
    
    return false;
}

/**
 * Revoke a permission from a staff member
 * @param int $staff_id - The staff member ID
 * @param int $permission_id - The permission ID
 * @param int $revoked_by - The admin who revoked this
 * @return bool - True on success
 */
function revokePermission($staff_id, $permission_id, $revoked_by) {
    global $conn;
    
    $query = "UPDATE staff_permissions SET granted = 0 WHERE staff_id = $staff_id AND permission_id = $permission_id";
    
    if (mysqli_query($conn, $query)) {
        // Log audit
        $log = "INSERT INTO permission_audit (staff_id, changed_by, permission_id, old_value, new_value, action) 
                VALUES ($staff_id, $revoked_by, $permission_id, 1, 0, 'revoke')";
        mysqli_query($conn, $log);
        return true;
    }
    
    return false;
}

/**
 * Apply a permission template to a staff member
 * @param int $staff_id - The staff member ID
 * @param int $template_id - The template ID
 * @param int $admin_id - The admin applying this
 * @return bool - True on success
 */
function applyPermissionTemplate($staff_id, $template_id, $admin_id) {
    global $conn;
    
    // Get template permissions
    $template_perms = mysqli_query($conn, "SELECT permission_id, granted FROM template_permissions WHERE template_id = $template_id");
    
    if (mysqli_num_rows($template_perms) == 0) {
        return false;
    }
    
    // Clear existing permissions for this staff
    mysqli_query($conn, "DELETE FROM staff_permissions WHERE staff_id = $staff_id");
    
    // Apply template permissions
    while ($perm = mysqli_fetch_assoc($template_perms)) {
        $query = "INSERT INTO staff_permissions (staff_id, permission_id, granted, granted_by) 
                  VALUES ($staff_id, {$perm['permission_id']}, {$perm['granted']}, $admin_id)";
        mysqli_query($conn, $query);
    }
    
    // Log action
    $log = "INSERT INTO permission_audit (staff_id, changed_by, action) 
            VALUES ($staff_id, $admin_id, 'template_apply')";
    mysqli_query($conn, $log);
    
    return true;
}

/**
 * Get all permission templates
 * @return mysqli_result - Result set of templates
 */
function getPermissionTemplates() {
    global $conn;
    return mysqli_query($conn, "SELECT * FROM permission_templates ORDER BY template_name");
}

/**
 * Get permissions for a specific template
 * @param int $template_id - The template ID
 * @return array - Associative array of permission_id => granted
 */
function getTemplatePermissions($template_id) {
    global $conn;
    
    $permissions = [];
    $query = "SELECT permission_id, granted FROM template_permissions WHERE template_id = $template_id";
    $result = mysqli_query($conn, $query);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $permissions[$row['permission_id']] = (bool)$row['granted'];
    }
    
    return $permissions;
}

/**
 * Get permission ID by name
 * @param string $permission_name - The permission name
 * @return int|false - Permission ID or false if not found
 */
function getPermissionIdByName($permission_name) {
    global $conn;
    $query = "SELECT id FROM permissions WHERE permission_name = '$permission_name'";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['id'];
    }
    return false;
}

/**
 * Check if a user has any of the given permissions (OR logic)
 * @param int $staff_id - The staff member ID
 * @param array $permission_names - Array of permission names
 * @return bool - True if has ANY of the permissions
 */
function hasAnyPermission($staff_id, $permission_names) {
    foreach ($permission_names as $name) {
        if (hasPermission($staff_id, $name)) {
            return true;
        }
    }
    return false;
}

/**
 * Check if a user has ALL of the given permissions (AND logic)
 * @param int $staff_id - The staff member ID
 * @param array $permission_names - Array of permission names
 * @return bool - True if has ALL of the permissions
 */
function hasAllPermissions($staff_id, $permission_names) {
    foreach ($permission_names as $name) {
        if (!hasPermission($staff_id, $name)) {
            return false;
        }
    }
    return true;
}

// ============================================
// NOTIFICATION FUNCTIONS
// ============================================

function sendNotification($user_id, $title, $message, $type = 'email') {
    global $conn;
    $query = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "isss", $user_id, $title, $message, $type);
    return mysqli_stmt_execute($stmt);
}

/**
 * Send SMS (placeholder - integrate with Africa's Talking, Twilio, etc.)
 */
function sendSMS($phone, $message) {
    // Integrate with your SMS provider (Africa's Talking, Twilio, etc.)
    // For now, just log it
    error_log("SMS to $phone: $message");
    return true;
}

/**
 * Send Email using PHPMailer with SMTP
 * @param string $to - Recipient email
 * @param string $subject - Email subject
 * @param string $body - HTML email body
 * @param string $from - Sender email (optional)
 * @param string $from_name - Sender name (optional)
 * @return bool - True on success
 */

/**
 * Send Email using PHPMailer with Gmail SMTP
 * @param string $to - Recipient email address
 * @param string $subject - Email subject
 * @param string $body - HTML email body
 * @param string $from - Sender email (optional)
 * @param string $from_name - Sender name (optional)
 * @return bool - True on success
 */
function sendEmail($to, $subject, $body, $from = '', $from_name = '') {
    // Path to PHPMailer files
    $phpmailer_path = dirname(__DIR__) . '/includes/PHPMailer/PHPMailer.php';
    $smtp_path = dirname(__DIR__) . '/includes/PHPMailer/SMTP.php';
    $exception_path = dirname(__DIR__) . '/includes/PHPMailer/Exception.php';
    
    // Check if PHPMailer exists
    if (!file_exists($phpmailer_path)) {
        error_log("PHPMailer not found at: $phpmailer_path");
        return false;
    }
    
    require_once $exception_path;
    require_once $phpmailer_path;
    require_once $smtp_path;
    
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // ============================================
        // SMTP CONFIGURATION - GMAIL
        // ============================================
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'raijoseph9505@gmail.com';   // ⚠️ CHANGE THIS
        $mail->Password   = 'plke rhxp ucmy yyrx';       // ⚠️ CHANGE THIS
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // ============================================
        // SENDER & RECIPIENT
        // ============================================
        $from_email = $from ?: 'noreply@salonpro.com';
        $from_name = $from_name ?: 'Salon Pro';
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to);
        
        // ============================================
        // EMAIL CONTENT
        // ============================================
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);
        
        // ============================================
        // SEND
        // ============================================
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// ============================================
// PLAN FEATURES FUNCTIONS
// ============================================

function getPlanFeatures($plan) {
    $features = [
        'basic' => [
            'staff_limit' => 5,
            'services_limit' => 10,
            'reports' => false,
            'payroll' => false,
            'permissions' => false,
            'products' => false,
            'orders' => false,
            'advanced_reports' => false,
            'settings' => false,
            'plan_name' => 'Basic',
            'price' => 5000,
            'color' => '#17a2b8'
        ],
        'premium' => [
            'staff_limit' => 15,
            'services_limit' => 30,
            'reports' => true,
            'payroll' => true,
            'permissions' => true,
            'products' => true,
            'orders' => true,
            'advanced_reports' => false,
            'settings' => false,
            'plan_name' => 'Premium',
            'price' => 10000,
            'color' => '#d4af37'
        ],
        'enterprise' => [
            'staff_limit' => 999,
            'services_limit' => 999,
            'reports' => true,
            'payroll' => true,
            'permissions' => true,
            'products' => true,
            'orders' => true,
            'advanced_reports' => true,
            'settings' => true,
            'plan_name' => 'Enterprise',
            'price' => 20000,
            'color' => '#28a745'
        ]
    ];
    return $features[$plan] ?? $features['basic'];
}

function getSalonPlanFeatures($salon_id) {
    global $conn;
    $query = "SELECT subscription_plan, plan_features FROM salons WHERE id = $salon_id";
    $result = mysqli_query($conn, $query);
    $salon = mysqli_fetch_assoc($result);
    if (!$salon) return getPlanFeatures('basic');
    
    // If plan_features is stored, decode it
    if (!empty($salon['plan_features'])) {
        $features = json_decode($salon['plan_features'], true);
        if ($features) return $features;
    }
    
    // Fallback to default plan features
    return getPlanFeatures($salon['subscription_plan'] ?? 'basic');
}

function hasPlanFeature($salon_id, $feature) {
    $features = getSalonPlanFeatures($salon_id);
    return $features[$feature] ?? false;
}

function getNextPlan($current_plan) {
    $plans = ['basic' => 'premium', 'premium' => 'enterprise', 'enterprise' => null];
    return $plans[$current_plan] ?? null;
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Get salon ID for current user
 * @return int - Salon ID
 */
function getCurrentSalonId() {
    if (isset($_SESSION['salon_id']) && $_SESSION['salon_id'] > 0) {
        return (int)$_SESSION['salon_id'];
    }
    
    if (isset($_SESSION['user_id'])) {
        global $conn;
        $query = "SELECT salon_id FROM users WHERE id = {$_SESSION['user_id']}";
        $result = mysqli_query($conn, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $_SESSION['salon_id'] = $row['salon_id'];
            return (int)$row['salon_id'];
        }
    }
    
    return 0;
}

/**
 * Get user name by ID
 * @param int $user_id - The user ID
 * @return string - User full name
 */
function getUserName($user_id) {
    global $conn;
    $query = "SELECT full_name FROM users WHERE id = $user_id";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['full_name'];
    }
    return 'Unknown User';
}

/**
 * Get user role by ID
 * @param int $user_id - The user ID
 * @return string - User role
 */
function getUserRole($user_id) {
    global $conn;
    $query = "SELECT role FROM users WHERE id = $user_id";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['role'];
    }
    return 'unknown';
}

/**
 * Check if staff member has a specific permission using session (for current logged-in user)
 * @param string $permission_name - The permission name to check
 * @return bool - True if has permission
 */
function hasPermissionSession($permission_name) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    return hasPermission($_SESSION['user_id'], $permission_name);
}

/**
 * Convert a timestamp to a human-readable time elapsed string
 * @param string $datetime - The timestamp to convert
 * @return string - Human readable time (e.g., "2 hours ago", "Just now")
 */
function time_elapsed_string($datetime) {
    if (empty($datetime)) return 'Just now';
    
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    
    return 'Just now';
}

// ============================================
// AUDIT LOG FUNCTION
// ============================================

/**
 * Log an audit trail entry
 * @param string $action - The action performed (e.g., 'salon_created', 'user_login')
 * @param string $category - The category (e.g., 'salon', 'user', 'subscription')
 * @param string $details - Detailed description of the action
 * @param int $user_id - The user ID (optional, defaults to current user)
 * @param string $user_name - The user name (optional)
 * @param string $user_role - The user role (optional)
 * @return bool - True on success
 */
function logAudit($action, $category, $details, $user_id = null, $user_name = null, $user_role = null) {
    global $conn;
    
    // Get current user if not provided
    if ($user_id === null && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $user_name = $_SESSION['user_name'] ?? 'Unknown';
        $user_role = $_SESSION['user_role'] ?? 'Unknown';
    }
    
    // Fallback values
    if ($user_id === null) {
        $user_id = 0;
        $user_name = $user_name ?? 'System';
        $user_role = $user_role ?? 'System';
    }
    
    // Get IP address
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // Get user agent
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Clean and escape
    $action = mysqli_real_escape_string($conn, $action);
    $category = mysqli_real_escape_string($conn, $category);
    $details = mysqli_real_escape_string($conn, $details);
    $user_name = mysqli_real_escape_string($conn, $user_name);
    $user_role = mysqli_real_escape_string($conn, $user_role);
    $ip_address = mysqli_real_escape_string($conn, $ip_address);
    $user_agent = mysqli_real_escape_string($conn, $user_agent);
    
    $query = "INSERT INTO audit_logs (user_id, user_name, user_role, action, category, details, ip_address, user_agent) 
              VALUES ($user_id, '$user_name', '$user_role', '$action', '$category', '$details', '$ip_address', '$user_agent')";
    
    return mysqli_query($conn, $query);
}

// ============================================
// SEARCH HISTORY FUNCTIONS
// ============================================

/**
 * Save a search query to the user's search history
 * @param int $user_id - The user ID (Super Admin)
 * @param string $query - The search query
 * @param string $category - The category searched (all, salons, owners, staff)
 * @return bool - True on success
 */
function saveRecentSearch($user_id, $query, $category = 'all') {
    global $conn;
    
    // Check if search_history table exists, create if not
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'search_history'");
    if (mysqli_num_rows($table_check) == 0) {
        createSearchHistoryTable();
    }
    
    // Trim and limit query length
    $query = trim(substr($query, 0, 255));
    $category = mysqli_real_escape_string($conn, $category);
    $user_id = (int)$user_id;
    $query_escaped = mysqli_real_escape_string($conn, $query);
    
    // Check if this exact search already exists
    $check = mysqli_query($conn, "SELECT id FROM search_history 
                                  WHERE user_id = $user_id 
                                  AND query = '$query_escaped' 
                                  AND category = '$category'");
    
    if (mysqli_num_rows($check) > 0) {
        // Update timestamp instead of inserting duplicate
        $update = "UPDATE search_history SET created_at = NOW() 
                   WHERE user_id = $user_id 
                   AND query = '$query_escaped' 
                   AND category = '$category'";
        return mysqli_query($conn, $update);
    } else {
        // Insert new search
        $insert = "INSERT INTO search_history (user_id, query, category) 
                   VALUES ($user_id, '$query_escaped', '$category')";
        return mysqli_query($conn, $insert);
    }
}

/**
 * Get recent searches for a user
 * @param int $user_id - The user ID
 * @param int $limit - Number of searches to return
 * @return array - Array of search records
 */
function getRecentSearches($user_id, $limit = 5) {
    global $conn;
    
    // Check if search_history table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'search_history'");
    if (mysqli_num_rows($table_check) == 0) {
        createSearchHistoryTable();
        return [];
    }
    
    $user_id = (int)$user_id;
    $limit = (int)$limit;
    
    $query = "SELECT id, query, category, created_at 
              FROM search_history 
              WHERE user_id = $user_id 
              ORDER BY created_at DESC 
              LIMIT $limit";
    
    $result = mysqli_query($conn, $query);
    $searches = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $searches[] = $row;
        }
    }
    
    return $searches;
}

/**
 * Delete a specific recent search
 * @param int $user_id - The user ID
 * @param int $search_id - The search ID to delete
 * @return bool - True on success
 */
function deleteRecentSearch($user_id, $search_id) {
    global $conn;
    
    $user_id = (int)$user_id;
    $search_id = (int)$search_id;
    
    $query = "DELETE FROM search_history 
              WHERE id = $search_id AND user_id = $user_id";
    return mysqli_query($conn, $query);
}

/**
 * Clear all recent searches for a user
 * @param int $user_id - The user ID
 * @return bool - True on success
 */
function clearRecentSearches($user_id) {
    global $conn;
    
    $user_id = (int)$user_id;
    
    $query = "DELETE FROM search_history WHERE user_id = $user_id";
    return mysqli_query($conn, $query);
}

/**
 * Create the search_history table if it doesn't exist
 */
function createSearchHistoryTable() {
    global $conn;
    
    $sql = "CREATE TABLE IF NOT EXISTS search_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                query VARCHAR(255) NOT NULL,
                category VARCHAR(50) DEFAULT 'all',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_query (user_id, query),
                INDEX idx_created (created_at)
            )";
    
    return mysqli_query($conn, $sql);
}

// ============================================
// PLAN PRICING FUNCTIONS
// ============================================

/**
 * Get the price for a specific plan
 * @param string $plan - 'basic', 'premium', or 'enterprise'
 * @return float - The price in KSh
 */
function getPlanPrice($plan) {
    global $conn;
    $key = 'plan_' . $plan . '_price';
    $query = "SELECT setting_value FROM salon_settings WHERE setting_key = '$key'";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return (float)$row['setting_value'];
    }
    // Fallback defaults if not found in database
    $defaults = ['basic' => 5000, 'premium' => 10000, 'enterprise' => 20000];
    return $defaults[$plan] ?? 0;
}

/**
 * Get all plan prices as an associative array
 * @return array - Associative array of plan => price
 */
function getAllPlanPrices() {
    return [
        'basic' => getPlanPrice('basic'),
        'premium' => getPlanPrice('premium'),
        'enterprise' => getPlanPrice('enterprise')
    ];
}

/**
 * Update a plan price
 * @param string $plan - 'basic', 'premium', or 'enterprise'
 * @param float $price - The new price
 * @return bool - True on success
 */
function updatePlanPrice($plan, $price) {
    global $conn;
    $key = 'plan_' . $plan . '_price';
    $price = (float)$price;
    $query = "UPDATE salon_settings SET setting_value = '$price' WHERE setting_key = '$key'";
    return mysqli_query($conn, $query);
}

/**
 * Get plan pricing with formatted currency
 * @return array - Associative array of plan => formatted price
 */
function getFormattedPlanPrices() {
    $prices = getAllPlanPrices();
    foreach ($prices as $plan => $price) {
        $prices[$plan] = 'KSh ' . number_format($price, 2);
    }
    return $prices;
}

/**
 * Get plan name with proper capitalization
 * @param string $plan - 'basic', 'premium', or 'enterprise'
 * @return string - Properly capitalized plan name
 */
function getPlanDisplayName($plan) {
    $names = [
        'basic' => 'Basic',
        'premium' => 'Premium',
        'enterprise' => 'Enterprise'
    ];
    return $names[$plan] ?? ucfirst($plan);
}

// ============================================
// NOTIFICATION FUNCTIONS
// ============================================

/**
 * Send a subscription confirmation to the salon owner
 * @param int $salon_id - The salon ID
 * @param string $plan - The plan name
 * @param float $amount - The amount paid
 * @param string $expiry_date - The new expiry date
 * @return bool - True on success
 */

/**
 * Send a subscription confirmation to the salon owner (DEBUG VERSION)
 */
function sendSubscriptionConfirmation($salon_id, $plan, $amount, $expiry_date) {
    global $conn;
    
    // DEBUG: Log function call
    error_log("=== sendSubscriptionConfirmation() called ===");
    error_log("salon_id: $salon_id, plan: $plan, amount: $amount, expiry: $expiry_date");
    
    // Get salon details
    $salon_query = "SELECT s.*, u.full_name as owner_name, u.email as owner_email, u.phone as owner_phone 
                    FROM salons s 
                    JOIN users u ON s.owner_id = u.id 
                    WHERE s.id = $salon_id";
    
    error_log("SQL Query: $salon_query");
    
    $salon_result = mysqli_query($conn, $salon_query);
    
    if (!$salon_result) {
        error_log("❌ Query failed: " . mysqli_error($conn));
        return false;
    }
    
    if (mysqli_num_rows($salon_result) == 0) {
        error_log("❌ No salon found with ID: $salon_id");
        return false;
    }
    
    $salon = mysqli_fetch_assoc($salon_result);
    
    error_log("✅ Salon found: " . $salon['salon_name']);
    error_log("   Owner: " . $salon['owner_name']);
    error_log("   Email: " . $salon['owner_email']);
    error_log("   Phone: " . $salon['owner_phone']);
    
    $owner_name = $salon['owner_name'];
    $owner_email = $salon['owner_email'];
    $owner_phone = $salon['owner_phone'];
    $salon_name = $salon['salon_name'];
    $today = date('F d, Y');
    $expiry = date('F d, Y', strtotime($expiry_date));
    
    // Build email body (simplified for testing)
    $subject = "✅ Subscription Renewal Confirmation - $salon_name";
    
    $email_body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background: #0a0a0a; color: #f5f0e1; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #0e0e0e; border: 1px solid rgba(212, 175, 55, 0.25); border-radius: 12px; padding: 30px; }
            .header { text-align: center; border-bottom: 2px solid #d4af37; padding-bottom: 15px; }
            .header h1 { color: #d4af37; font-family: 'Playfair Display', serif; font-size: 28px; margin: 0; }
            .details { background: #1a1a1a; border-radius: 8px; padding: 15px; margin: 15px 0; }
            .details .row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid rgba(212, 175, 55, 0.05); }
            .details .row:last-child { border-bottom: none; }
            .details .label { color: #b8b2a0; }
            .details .value { color: #f0d878; font-weight: 600; }
            .footer { text-align: center; color: #7a7568; font-size: 12px; border-top: 1px solid rgba(212, 175, 55, 0.1); padding-top: 15px; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1><span>SALON</span> PRO</h1>
                <p style='color: #d4af37; margin: 0;'>Where Beauty Meets Excellence</p>
            </div>
            <div class='content'>
                <h2 style='color: #f0d878;'>✅ Subscription Confirmed!</h2>
                <p>Dear <strong>$owner_name</strong>,</p>
                <p>Thank you for renewing your subscription with Salon Pro! Your account is now active.</p>
                
                <div class='details'>
                    <div class='row'><span class='label'>🏪 Salon</span><span class='value'>$salon_name</span></div>
                    <div class='row'><span class='label'>📋 Plan</span><span class='value'>" . ucfirst($plan) . " Plan</span></div>
                    <div class='row'><span class='label'>✅ Status</span><span class='value' style='color: #28a745;'>Active</span></div>
                    <div class='row'><span class='label'>📅 Active From</span><span class='value'>$today</span></div>
                    <div class='row'><span class='label'>⏰ Expiry Date</span><span class='value'>$expiry</span></div>
                    <div class='row'><span class='label'>💰 Amount Paid</span><span class='value'>KSh " . number_format($amount, 2) . "</span></div>
                </div>
                
                <p style='color: #f0d878;'>Thank you for choosing Salon Pro! ✨</p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " Salon Pro. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // ============================================
    // SEND EMAIL
    // ============================================
    error_log("📧 Attempting to send email to: $owner_email");
    $email_sent = sendEmail($owner_email, $subject, $email_body);
    error_log("📧 Email result: " . ($email_sent ? 'SENT' : 'FAILED'));
    
    // ============================================
    // SEND SMS
    // ============================================
    $sms_body = "SALON PRO: ✅ Subscription Renewed!\n\nDear $owner_name, your " . ucfirst($plan) . " Plan for $salon_name is now active.\n\nActive from: $today\nExpiry: $expiry\nAmount: KSh " . number_format($amount, 2) . "\n\nThank you for choosing Salon Pro! ✨";
    
    error_log("📱 Attempting to send SMS to: $owner_phone");
    $sms_sent = sendSMS($owner_phone, $sms_body);
    error_log("📱 SMS result: " . ($sms_sent ? 'SENT' : 'FAILED'));
    
    // ============================================
    // LOG NOTIFICATIONS
    // ============================================
    if ($email_sent) {
        logNotification('owner', $salon['owner_id'], $owner_email, $owner_phone, 'email', $subject, $email_body, 'sent');
        error_log("✅ Email logged to notification_logs");
    } else {
        logNotification('owner', $salon['owner_id'], $owner_email, $owner_phone, 'email', $subject, $email_body, 'failed', 'Email send failed');
        error_log("❌ Email log marked as failed");
    }
    
    if ($sms_sent) {
        logNotification('owner', $salon['owner_id'], $owner_email, $owner_phone, 'sms', $subject, $sms_body, 'sent');
        error_log("✅ SMS logged to notification_logs");
    } else {
        logNotification('owner', $salon['owner_id'], $owner_email, $owner_phone, 'sms', $subject, $sms_body, 'failed', 'SMS send failed');
        error_log("❌ SMS log marked as failed");
    }
    
    // ============================================
    // SUPER ADMIN IN-APP NOTIFICATION
    // ============================================
    $notify_result = notifySuperAdmin(
        'subscription_renewed',
        "$salon_name renewed their " . ucfirst($plan) . " Plan",
        "Owner: $owner_name | Amount: KSh " . number_format($amount, 2) . " | Expiry: $expiry",
        "subscriptions.php?view=$salon_id"
    );
    error_log("🔔 Super Admin notification result: " . ($notify_result ? 'CREATED' : 'FAILED'));
    
    error_log("=== sendSubscriptionConfirmation() completed ===");
    
    return $email_sent || $sms_sent;
}

/**
 * Send a welcome notification to a new salon owner
 * @param int $salon_id - The salon ID
 * @return bool - True on success
 */
function sendWelcomeNotification($salon_id) {
    global $conn;
    
    // Get salon details
    $salon_query = "SELECT s.*, u.full_name as owner_name, u.email as owner_email, u.phone as owner_phone 
                    FROM salons s 
                    JOIN users u ON s.owner_id = u.id 
                    WHERE s.id = $salon_id";
    $salon_result = mysqli_query($conn, $salon_query);
    $salon = mysqli_fetch_assoc($salon_result);
    
    if (!$salon) {
        return false;
    }
    
    $owner_name = $salon['owner_name'];
    $owner_email = $salon['owner_email'];
    $owner_phone = $salon['owner_phone'];
    $salon_name = $salon['salon_name'];
    $plan = ucfirst($salon['subscription_plan'] ?? 'Basic');
    $expiry = date('F d, Y', strtotime($salon['subscription_expiry']));
    
    // Email body for welcome
    $subject = "🎉 Welcome to Salon Pro - $salon_name";
    $email_body = "
    <html>
    <head>
        <style>
            body { font-family: 'Poppins', Arial, sans-serif; background: #0a0a0a; color: #f5f0e1; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #0e0e0e; border: 1px solid rgba(212, 175, 55, 0.25); border-radius: 12px; padding: 30px; }
            .header { text-align: center; border-bottom: 2px solid #d4af37; padding-bottom: 15px; }
            .header h1 { color: #d4af37; font-family: 'Playfair Display', serif; font-size: 28px; margin: 0; }
            .content { padding: 20px 0; }
            .details { background: #1a1a1a; border-radius: 8px; padding: 15px; margin: 15px 0; border: 1px solid rgba(212, 175, 55, 0.15); }
            .footer { text-align: center; color: #7a7568; font-size: 12px; border-top: 1px solid rgba(212, 175, 55, 0.1); padding-top: 15px; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1><span>SALON</span> PRO</h1>
                <p style='color: #d4af37; margin: 0;'>Where Beauty Meets Excellence</p>
            </div>
            <div class='content'>
                <h2 style='color: #f0d878;'>🎉 Welcome to Salon Pro!</h2>
                <p>Dear <strong>$owner_name</strong>,</p>
                <p>Your salon <strong>$salon_name</strong> has been successfully created! We're excited to have you on board.</p>
                
                <div class='details'>
                    <p style='color: #f0d878; font-weight: 600; margin: 0 0 10px 0;'>📋 Your Subscription Details</p>
                    <p><span style='color: #b8b2a0;'>Plan:</span> <span style='color: #f0d878;'>$plan Plan</span></p>
                    <p><span style='color: #b8b2a0;'>Expiry:</span> <span style='color: #f0d878;'>$expiry</span></p>
                </div>
                
                <p style='color: #7a7568;'>🔑 Your login credentials:</p>
                <p style='background: #1a1a1a; padding: 10px; border-radius: 5px;'>
                    <span style='color: #b8b2a0;'>Email:</span> <span style='color: #f0d878;'>$owner_email</span><br>
                    <span style='color: #b8b2a0;'>Password:</span> <span style='color: #f0d878;'>owner123</span>
                </p>
                <p style='color: #dc3545; font-size: 14px;'>⚠️ Please change your password after your first login.</p>
                
                <p style='color: #f0d878;'>Welcome to the family! ✨</p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " Salon Pro. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Send email
    $email_sent = sendEmail($owner_email, $subject, $email_body);
    
    // SMS
    $sms_body = "SALON PRO: 🎉 Welcome $owner_name!\n\nYour salon $salon_name is now active.\nPlan: $plan Plan\nExpiry: $expiry\n\nLogin: $owner_email\nPassword: owner123\n\nChange password after login.\nThank you for choosing Salon Pro! ✨";
    $sms_sent = sendSMS($owner_phone, $sms_body);
    
    // Log
    logNotification('owner', $salon['owner_id'], $owner_email, $owner_phone, 'both', $subject, $email_body, $email_sent ? 'sent' : 'failed');
    logNotification('owner', $salon['owner_id'], $owner_email, $owner_phone, 'sms', $subject, $sms_body, $sms_sent ? 'sent' : 'failed');
    
    // Notify Super Admin
    notifySuperAdmin(
        'salon_created',
        "New Salon Created: $salon_name",
        "Owner: $owner_name | Plan: $plan | Email: $owner_email",
        "salons.php?view=$salon_id"
    );
    
    return true;
}

/**
 * Send an expiry warning notification
 * @param int $salon_id - The salon ID
 * @param int $days_left - Days until expiry
 * @return bool - True on success
 */
function sendExpiryWarning($salon_id, $days_left) {
    global $conn;
    
    // Get salon details
    $salon_query = "SELECT s.*, u.full_name as owner_name, u.email as owner_email, u.phone as owner_phone 
                    FROM salons s 
                    JOIN users u ON s.owner_id = u.id 
                    WHERE s.id = $salon_id";
    $salon_result = mysqli_query($conn, $salon_query);
    $salon = mysqli_fetch_assoc($salon_result);
    
    if (!$salon) {
        return false;
    }
    
    $owner_name = $salon['owner_name'];
    $owner_email = $salon['owner_email'];
    $owner_phone = $salon['owner_phone'];
    $salon_name = $salon['salon_name'];
    $plan = ucfirst($salon['subscription_plan'] ?? 'Basic');
    $expiry = date('F d, Y', strtotime($salon['subscription_expiry']));
    
    // Email subject
    $subject = "⚠️ Your Subscription Expires in $days_left Days - $salon_name";
    
    // Email body
    $email_body = "
    <html>
    <head>
        <style>
            body { font-family: 'Poppins', Arial, sans-serif; background: #0a0a0a; color: #f5f0e1; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #0e0e0e; border: 1px solid rgba(212, 175, 55, 0.25); border-radius: 12px; padding: 30px; }
            .header { text-align: center; border-bottom: 2px solid #d4af37; padding-bottom: 15px; }
            .header h1 { color: #d4af37; font-family: 'Playfair Display', serif; font-size: 28px; margin: 0; }
            .warning { background: rgba(212, 175, 55, 0.1); border: 1px solid #d4af37; border-radius: 8px; padding: 15px; margin: 15px 0; text-align: center; }
            .warning h2 { color: #d4af37; margin: 0; }
            .btn { display: inline-block; padding: 10px 25px; background: #d4af37; color: #050505; border-radius: 25px; text-decoration: none; font-weight: 600; margin-top: 10px; }
            .footer { text-align: center; color: #7a7568; font-size: 12px; border-top: 1px solid rgba(212, 175, 55, 0.1); padding-top: 15px; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1><span>SALON</span> PRO</h1>
            </div>
            <div class='content'>
                <div class='warning'>
                    <h2>⚠️ Subscription Expiring Soon!</h2>
                    <p style='color: #f0d878; font-size: 18px;'>$days_left days remaining</p>
                    <p style='color: #b8b2a0;'>Your <strong>$plan</strong> Plan for <strong>$salon_name</strong> will expire on <strong>$expiry</strong></p>
                    <a href='#' class='btn'>🔄 Renew Now</a>
                </div>
                <p style='color: #7a7568; font-size: 14px;'>To avoid service interruption, please renew your subscription before the expiry date.</p>
                <p style='color: #f0d878;'>Thank you for choosing Salon Pro! ✨</p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " Salon Pro. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Send email
    $email_sent = sendEmail($owner_email, $subject, $email_body);
    
    // SMS
    $sms_body = "SALON PRO: ⚠️ Your $plan Plan for $salon_name expires in $days_left days ($expiry). Renew now to avoid interruption! Thank you for choosing Salon Pro ✨";
    $sms_sent = sendSMS($owner_phone, $sms_body);
    
    // Log
    logNotification('owner', $salon['owner_id'], $owner_email, $owner_phone, 'both', $subject, $email_body, $email_sent ? 'sent' : 'failed');
    logNotification('owner', $salon['owner_id'], $owner_email, $owner_phone, 'sms', $subject, $sms_body, $sms_sent ? 'sent' : 'failed');
    
    // Notify Super Admin
    notifySuperAdmin(
        'subscription_expiring',
        "$salon_name subscription expiring in $days_left days",
        "Plan: $plan | Expiry: $expiry | Renew to avoid interruption",
        "subscriptions.php?view=$salon_id"
    );
    
    return true;
}

/**
 * Send a payment failed notification
 * @param int $salon_id - The salon ID
 * @param float $amount - The failed amount
 * @param string $reason - The failure reason
 * @return bool - True on success
 */
function sendPaymentFailedAlert($salon_id, $amount, $reason) {
    global $conn;
    
    // Get salon details
    $salon_query = "SELECT s.*, u.full_name as owner_name, u.email as owner_email, u.phone as owner_phone 
                    FROM salons s 
                    JOIN users u ON s.owner_id = u.id 
                    WHERE s.id = $salon_id";
    $salon_result = mysqli_query($conn, $salon_query);
    $salon = mysqli_fetch_assoc($salon_result);
    
    if (!$salon) {
        return false;
    }
    
    $owner_name = $salon['owner_name'];
    $owner_email = $salon['owner_email'];
    $owner_phone = $salon['owner_phone'];
    $salon_name = $salon['salon_name'];
    
    // Email subject
    $subject = "❌ Payment Failed - $salon_name";
    
    // Email body
    $email_body = "
    <html>
    <head>
        <style>
            body { font-family: 'Poppins', Arial, sans-serif; background: #0a0a0a; color: #f5f0e1; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #0e0e0e; border: 1px solid rgba(212, 175, 55, 0.25); border-radius: 12px; padding: 30px; }
            .header { text-align: center; border-bottom: 2px solid #d4af37; padding-bottom: 15px; }
            .header h1 { color: #d4af37; font-family: 'Playfair Display', serif; font-size: 28px; margin: 0; }
            .warning { background: rgba(220, 53, 69, 0.1); border: 1px solid #dc3545; border-radius: 8px; padding: 15px; margin: 15px 0; text-align: center; }
            .warning h2 { color: #dc3545; margin: 0; }
            .btn { display: inline-block; padding: 10px 25px; background: #d4af37; color: #050505; border-radius: 25px; text-decoration: none; font-weight: 600; margin-top: 10px; }
            .footer { text-align: center; color: #7a7568; font-size: 12px; border-top: 1px solid rgba(212, 175, 55, 0.1); padding-top: 15px; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1><span>SALON</span> PRO</h1>
            </div>
            <div class='content'>
                <div class='warning'>
                    <h2>❌ Payment Failed</h2>
                    <p style='color: #dc3545; font-size: 18px;'>KSh " . number_format($amount, 2) . "</p>
                    <p style='color: #b8b2a0;'>Reason: <strong>$reason</strong></p>
                    <a href='#' class='btn'>🔄 Retry Payment</a>
                </div>
                <p style='color: #7a7568; font-size: 14px;'>Please update your payment method to continue enjoying Salon Pro services.</p>
                <p style='color: #f0d878;'>If you need assistance, contact our support team.</p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " Salon Pro. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Send email
    $email_sent = sendEmail($owner_email, $subject, $email_body);
    
    // SMS
    $sms_body = "SALON PRO: ❌ Payment Failed! Amount: KSh " . number_format($amount, 2) . " | Reason: $reason. Retry payment to avoid service interruption. Thank you for choosing Salon Pro ✨";
    $sms_sent = sendSMS($owner_phone, $sms_body);
    
    // Log
    logNotification('owner', $salon['owner_id'], $owner_email, $owner_phone, 'both', $subject, $email_body, $email_sent ? 'sent' : 'failed');
    logNotification('owner', $salon['owner_id'], $owner_email, $owner_phone, 'sms', $subject, $sms_body, $sms_sent ? 'sent' : 'failed');
    
    // Notify Super Admin
    notifySuperAdmin(
        'payment_failed',
        "Payment Failed: $salon_name",
        "Amount: KSh " . number_format($amount, 2) . " | Reason: $reason",
        "subscriptions.php?view=$salon_id"
    );
    
    return true;
}

/**
 * Create an in-app notification for Super Admin
 * @param string $type - Notification type (subscription_renewed, salon_created, etc.)
 * @param string $title - Notification title
 * @param string $message - Notification message
 * @param string $link - Link to navigate to
 * @return bool - True on success
 */
function notifySuperAdmin($type, $title, $message, $link = '') {
    global $conn;
    
    // Get Super Admin user ID (usually user with role = 'super_admin')
    $super_query = "SELECT id FROM users WHERE role = 'super_admin' LIMIT 1";
    $super_result = mysqli_query($conn, $super_query);
    $super = mysqli_fetch_assoc($super_result);
    
    if (!$super) {
        return false;
    }
    
    $super_id = $super['id'];
    
    $query = "INSERT INTO notifications (user_id, type, title, message, link) 
              VALUES ($super_id, '$type', '$title', '$message', '$link')";
    return mysqli_query($conn, $query);
}

/**
 * Log a notification (email or SMS) in the notification_logs table
 * @param string $recipient_type - owner, staff, admin, super_admin
 * @param int $recipient_id - User ID
 * @param string $email - Recipient email
 * @param string $phone - Recipient phone
 * @param string $channel - email, sms, both
 * @param string $subject - Email subject
 * @param string $message - Message body
 * @param string $status - sent, failed, pending
 * @param string $error_message - Optional error message
 * @return bool - True on success
 */
function logNotification($recipient_type, $recipient_id, $email, $phone, $channel, $subject, $message, $status, $error_message = '') {
    global $conn;
    
    $email = mysqli_real_escape_string($conn, $email);
    $phone = mysqli_real_escape_string($conn, $phone);
    $subject = mysqli_real_escape_string($conn, $subject);
    $message = mysqli_real_escape_string($conn, $message);
    $error_message = mysqli_real_escape_string($conn, $error_message);
    
    $query = "INSERT INTO notification_logs (recipient_type, recipient_id, recipient_email, recipient_phone, channel, subject, message, status, error_message) 
              VALUES ('$recipient_type', $recipient_id, '$email', '$phone', '$channel', '$subject', '$message', '$status', '$error_message')";
    return mysqli_query($conn, $query);
}

/**
 * Get unread notification count for Super Admin
 * @return int - Number of unread notifications
 */
function getUnreadNotificationCount() {
    global $conn;
    
    // Get Super Admin user ID
    $super_query = "SELECT id FROM users WHERE role = 'super_admin' LIMIT 1";
    $super_result = mysqli_query($conn, $super_query);
    $super = mysqli_fetch_assoc($super_result);
    
    if (!$super) {
        return 0;
    }
    
    $super_id = $super['id'];
    $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = $super_id AND is_read = 0";
    $result = mysqli_query($conn, $query);
    $data = mysqli_fetch_assoc($result);
    
    return $data['count'] ?? 0;
}

/**
 * Get all notifications for Super Admin
 * @param int $limit - Limit of notifications to return
 * @param int $offset - Offset for pagination
 * @return array - Array of notifications
 */
function getNotifications($limit = 10, $offset = 0) {
    global $conn;
    
    // Get Super Admin user ID
    $super_query = "SELECT id FROM users WHERE role = 'super_admin' LIMIT 1";
    $super_result = mysqli_query($conn, $super_query);
    $super = mysqli_fetch_assoc($super_result);
    
    if (!$super) {
        return [];
    }
    
    $super_id = $super['id'];
    $query = "SELECT * FROM notifications 
              WHERE user_id = $super_id 
              ORDER BY created_at DESC 
              LIMIT $limit OFFSET $offset";
    $result = mysqli_query($conn, $query);
    
    $notifications = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = $row;
    }
    
    return $notifications;
}

/**
 * Mark a notification as read
 * @param int $notification_id - The notification ID
 * @return bool - True on success
 */
function markNotificationRead($notification_id) {
    global $conn;
    
    $query = "UPDATE notifications SET is_read = 1 WHERE id = $notification_id";
    return mysqli_query($conn, $query);
}

/**
 * Mark all notifications as read for Super Admin
 * @return bool - True on success
 */
function markAllNotificationsRead() {
    global $conn;
    
    // Get Super Admin user ID
    $super_query = "SELECT id FROM users WHERE role = 'super_admin' LIMIT 1";
    $super_result = mysqli_query($conn, $super_query);
    $super = mysqli_fetch_assoc($super_result);
    
    if (!$super) {
        return false;
    }
    
    $super_id = $super['id'];
    $query = "UPDATE notifications SET is_read = 1 WHERE user_id = $super_id";
    return mysqli_query($conn, $query);
}
?>