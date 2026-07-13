<?php
// config/database.php - COMPLETE FILE WITH SUBSCRIPTION HELPER FUNCTIONS

// ============================================
// DATABASE CONNECTION
// ============================================
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'saloon_management_system';
$db_port = 3306;

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

// ============================================
// TIMEZONE
// ============================================
date_default_timezone_set('Africa/Nairobi');

// ============================================
// SESSION MANAGEMENT
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// ROLE CHECKING FUNCTIONS
// ============================================
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function isSuperAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'super_admin';
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
// SUBSCRIPTION HELPER FUNCTIONS (NEW)
// ============================================

/**
 * Check if a salon's subscription is active
 * @param int $salon_id - The salon ID
 * @return bool - True if active, false if expired
 */
function isSubscriptionActive($salon_id) {
    global $conn;
    
    if ($salon_id <= 0) {
        return false;
    }
    
    $query = "SELECT subscription_expiry, subscription_status 
              FROM salons 
              WHERE id = $salon_id";
    $result = mysqli_query($conn, $query);
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $expiry_date = $row['subscription_expiry'];
        $status = $row['subscription_status'];
        
        // If status is explicitly expired or suspended
        if ($status == 'expired' || $status == 'suspended') {
            return false;
        }
        
        // If expiry date is set and is in the past
        if (!empty($expiry_date) && $expiry_date < date('Y-m-d')) {
            return false;
        }
        
        return true;
    }
    
    return false;
}

/**
 * Get subscription status details for a salon
 * @param int $salon_id - The salon ID
 * @return array - Status details including expiry date, status, days remaining
 */
function getSubscriptionStatus($salon_id) {
    global $conn;
    
    $result = [
        'status' => 'unknown',
        'expiry_date' => null,
        'days_remaining' => null,
        'is_active' => false,
        'message' => ''
    ];
    
    if ($salon_id <= 0) {
        $result['message'] = 'Invalid salon ID';
        return $result;
    }
    
    $query = "SELECT subscription_expiry, subscription_status 
              FROM salons 
              WHERE id = $salon_id";
    $query_result = mysqli_query($conn, $query);
    
    if ($query_result && $row = mysqli_fetch_assoc($query_result)) {
        $expiry_date = $row['subscription_expiry'];
        $status = $row['subscription_status'];
        
        $result['status'] = $status;
        $result['expiry_date'] = $expiry_date;
        
        if (!empty($expiry_date)) {
            $today = new DateTime();
            $expiry = new DateTime($expiry_date);
            $diff = $today->diff($expiry);
            $days = $diff->days;
            
            if ($expiry < $today) {
                $result['days_remaining'] = -$days;
                $result['is_active'] = false;
                $result['message'] = 'Subscription expired ' . $days . ' days ago';
            } else {
                $result['days_remaining'] = $days;
                $result['is_active'] = ($status != 'expired' && $status != 'suspended');
                $result['message'] = $days . ' days remaining';
            }
        } else {
            $result['message'] = 'No expiry date set';
            $result['is_active'] = ($status != 'expired' && $status != 'suspended');
        }
        
        // Override if status is explicitly expired/suspended
        if ($status == 'expired' || $status == 'suspended') {
            $result['is_active'] = false;
            $result['message'] = 'Account ' . $status;
        }
    } else {
        $result['message'] = 'Salon not found';
    }
    
    return $result;
}

/**
 * Deactivate a salon owner (admin) and optionally their staff
 * @param int $salon_id - The salon ID
 * @param bool $deactivate_staff - Whether to also deactivate staff
 * @return bool - Success or failure
 */
function deactivateSalon($salon_id, $deactivate_staff = true) {
    global $conn;
    
    if ($salon_id <= 0) {
        return false;
    }
    
    // Deactivate the salon owner (admin)
    $admin_query = "UPDATE users SET is_active = 0 
                    WHERE salon_id = $salon_id AND role = 'admin'";
    $admin_result = mysqli_query($conn, $admin_query);
    
    // Optionally deactivate staff
    if ($deactivate_staff) {
        $staff_query = "UPDATE users SET is_active = 0 
                        WHERE salon_id = $salon_id AND role = 'staff'";
        mysqli_query($conn, $staff_query);
    }
    
    // Update salon status
    $salon_query = "UPDATE salons SET subscription_status = 'expired' 
                    WHERE id = $salon_id";
    mysqli_query($conn, $salon_query);
    
    return true;
}

/**
 * Reactivate a salon owner (admin) and their staff
 * @param int $salon_id - The salon ID
 * @param string $new_expiry - New expiry date (YYYY-MM-DD)
 * @param string $plan - Subscription plan (basic, premium, enterprise)
 * @return bool - Success or failure
 */
function reactivateSalon($salon_id, $new_expiry, $plan = 'basic') {
    global $conn;
    
    if ($salon_id <= 0 || empty($new_expiry)) {
        return false;
    }
    
    // Reactivate salon owner
    $admin_query = "UPDATE users SET is_active = 1 
                    WHERE salon_id = $salon_id AND role = 'admin'";
    mysqli_query($conn, $admin_query);
    
    // Reactivate staff
    $staff_query = "UPDATE users SET is_active = 1 
                    WHERE salon_id = $salon_id AND role = 'staff'";
    mysqli_query($conn, $staff_query);
    
    // Update salon status and expiry
    $salon_query = "UPDATE salons SET 
                    subscription_status = 'active',
                    subscription_expiry = '$new_expiry',
                    subscription_plan = '$plan'
                    WHERE id = $salon_id";
    mysqli_query($conn, $salon_query);
    
    return true;
}

// ============================================
// NOTIFICATION FUNCTIONS
// ============================================
function sendNotification($user_id, $title, $message, $type = 'email') {
    global $conn;
    $query = "INSERT INTO notifications (user_id, title, message, type, is_read) 
              VALUES (?, ?, ?, ?, 0)";
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "isss", $user_id, $title, $message, $type);
        return mysqli_stmt_execute($stmt);
    }
    return false;
}

function sendSMS($phone, $message) {
    $log = date('Y-m-d H:i:s') . " - SMS to: $phone - Message: $message\n";
    file_put_contents(__DIR__ . '/../sms_log.txt', $log, FILE_APPEND);
    return true;
}

function sendEmail($to, $subject, $body) {
    $log = date('Y-m-d H:i:s') . " - Email to: $to - Subject: $subject\n";
    file_put_contents(__DIR__ . '/../email_log.txt', $log, FILE_APPEND);
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: Salon Pro <noreply@salonpro.com>\r\n";
    return mail($to, $subject, $body, $headers);
}

// ============================================
// PLAN FEATURE ACCESS FUNCTIONS
// ============================================
function getPlanFeatures($plan) {
    $features = [
        'basic' => ['appointments', 'customers', 'staff', 'services', 'payments'],
        'premium' => ['appointments', 'customers', 'staff', 'services', 'payments', 'reports', 'permissions'],
        'enterprise' => ['appointments', 'customers', 'staff', 'services', 'payments', 'reports', 'permissions', 'multi_branch', 'advanced_analytics', 'priority_support']
    ];
    return isset($features[$plan]) ? $features[$plan] : $features['basic'];
}

function hasFeature($salon_id, $feature) {
    global $conn;
    $query = "SELECT subscription_plan FROM salons WHERE id = $salon_id";
    $result = mysqli_query($conn, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $plan = $row['subscription_plan'];
        $features = getPlanFeatures($plan);
        return in_array($feature, $features);
    }
    return false;
}

function getSalonPlan($salon_id) {
    global $conn;
    $query = "SELECT subscription_plan FROM salons WHERE id = $salon_id";
    $result = mysqli_query($conn, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return $row['subscription_plan'];
    }
    return 'basic';
}

function getUpgradeMessage($current_plan) {
    switch($current_plan) {
        case 'basic':
            return [
                'message' => "You're on the Basic plan.",
                'button' => 'Upgrade to Premium →',
                'target' => 'premium',
                'features' => ['Reports & Analytics', 'Staff Permissions']
            ];
        case 'premium':
            return [
                'message' => "You're on the Premium plan.",
                'button' => 'Upgrade to Enterprise →',
                'target' => 'enterprise',
                'features' => ['Multi-Branch Support', 'Advanced Analytics', 'Priority Support']
            ];
        default:
            return null;
    }
}

function getPlanPricing() {
    return [
        'basic' => 0,
        'premium' => 10000,
        'enterprise' => 20000
    ];
}

function getPlanLabel($plan) {
    $labels = ['basic' => 'Basic', 'premium' => 'Premium', 'enterprise' => 'Enterprise'];
    return isset($labels[$plan]) ? $labels[$plan] : ucfirst($plan);
}
?>