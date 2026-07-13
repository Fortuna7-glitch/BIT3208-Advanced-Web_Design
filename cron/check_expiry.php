<?php
/**
 * cron/check_expiry.php
 * 
 * DAILY CRON SCRIPT - Check expired subscriptions and auto-deactivate
 * 
 * HOW TO SET UP:
 * - Linux: Add to crontab: 0 0 * * * php /path/to/cron/check_expiry.php
 * - Windows: Use Task Scheduler to run daily
 * - XAMPP: Can be triggered manually or via webhook
 * 
 * USAGE:
 * - Run from browser: http://localhost/saloon_system/cron/check_expiry.php
 * - Run from CLI: php cron/check_expiry.php
 */

// Set execution time to unlimited
set_time_limit(0);

// Determine the correct path to config
$config_path = dirname(__DIR__) . '/config/database.php';
if (!file_exists($config_path)) {
    die("Error: Database config not found.\n");
}

require_once $config_path;

// ============================================
// LOGGING FUNCTION
// ============================================
function logMessage($message, $type = 'INFO') {
    $log_file = dirname(__DIR__) . '/logs/subscription_cron.log';
    
    // Create logs directory if it doesn't exist
    $log_dir = dirname($log_file);
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$type] $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    
    // Also output to console
    echo $log_entry;
}

// ============================================
// START CRON JOB
// ============================================
logMessage("=== STARTING SUBSCRIPTION EXPIRY CHECK ===");

// Get all active salons with expiry dates
$query = "SELECT id, salon_name, subscription_expiry, subscription_status 
          FROM salons 
          WHERE subscription_status = 'active' 
          AND subscription_expiry IS NOT NULL 
          AND subscription_expiry != ''";

$result = mysqli_query($conn, $query);

if (!$result) {
    logMessage("Error fetching salons: " . mysqli_error($conn), 'ERROR');
    exit(1);
}

$total_salons = mysqli_num_rows($result);
logMessage("Found $total_salons active salons with expiry dates.");

$expired_count = 0;
$deactivated_count = 0;
$warning_count = 0;
$errors = [];

$today = date('Y-m-d');
$warning_threshold = 7; // Send warning 7 days before expiry

// ============================================
// PROCESS EACH SALON
// ============================================
while ($salon = mysqli_fetch_assoc($result)) {
    $salon_id = $salon['id'];
    $salon_name = $salon['salon_name'];
    $expiry_date = $salon['subscription_expiry'];
    
    logMessage("Processing: $salon_name (ID: $salon_id) - Expires: $expiry_date");
    
    // Calculate days remaining
    $today_obj = new DateTime($today);
    $expiry_obj = new DateTime($expiry_date);
    $diff = $today_obj->diff($expiry_obj);
    $days_remaining = $diff->days;
    
    if ($expiry_obj < $today_obj) {
        $days_remaining = -$days_remaining;
    }
    
    logMessage("Days remaining: $days_remaining");
    
    // ============================================
    // CASE 1: EXPIRED - Deactivate
    // ============================================
    if ($expiry_date < $today) {
        logMessage("⚠️ EXPIRED! Deactivating salon: $salon_name", 'WARNING');
        
        // Deactivate the salon owner (admin)
        $admin_query = "UPDATE users SET is_active = 0 
                        WHERE salon_id = $salon_id AND role = 'admin'";
        if (mysqli_query($conn, $admin_query)) {
            logMessage("✅ Admin deactivated for $salon_name");
        } else {
            $error_msg = "Failed to deactivate admin for $salon_name: " . mysqli_error($conn);
            logMessage($error_msg, 'ERROR');
            $errors[] = $error_msg;
        }
        
        // Deactivate staff
        $staff_query = "UPDATE users SET is_active = 0 
                        WHERE salon_id = $salon_id AND role = 'staff'";
        if (mysqli_query($conn, $staff_query)) {
            logMessage("✅ Staff deactivated for $salon_name");
        } else {
            $error_msg = "Failed to deactivate staff for $salon_name: " . mysqli_error($conn);
            logMessage($error_msg, 'ERROR');
            $errors[] = $error_msg;
        }
        
        // Update salon status
        $salon_update = "UPDATE salons SET subscription_status = 'expired' 
                         WHERE id = $salon_id";
        if (mysqli_query($conn, $salon_update)) {
            logMessage("✅ Salon status updated to 'expired' for $salon_name");
        } else {
            $error_msg = "Failed to update salon status for $salon_name: " . mysqli_error($conn);
            logMessage($error_msg, 'ERROR');
            $errors[] = $error_msg;
        }
        
        $expired_count++;
        $deactivated_count++;
        
        // Get admin email for notification
        $admin_email_query = "SELECT email FROM users 
                              WHERE salon_id = $salon_id AND role = 'admin' AND is_active = 1";
        $admin_email_result = mysqli_query($conn, $admin_email_query);
        if ($admin_email_result && $admin = mysqli_fetch_assoc($admin_email_result)) {
            sendEmail($admin['email'], "Subscription Expired - Salon Pro", 
                "Dear Salon Owner,<br><br>Your subscription for <strong>$salon_name</strong> expired on " . date('M d, Y', strtotime($expiry_date)) . ".<br><br>Your account has been deactivated. Please contact the administrator to renew your subscription.<br><br>Thank you,<br>Salon Pro Team");
            logMessage("📧 Expiry notification email sent to: " . $admin['email']);
        }
        
    // ============================================
    // CASE 2: EXPIRING SOON (<= 7 days) - Send Warning
    // ============================================
    } elseif ($days_remaining <= $warning_threshold && $days_remaining >= 0) {
        logMessage("⚠️ EXPIRING SOON! $days_remaining days remaining for $salon_name", 'WARNING');
        
        $warning_count++;
        
        // Get admin email for warning notification
        $admin_email_query = "SELECT email FROM users 
                              WHERE salon_id = $salon_id AND role = 'admin' AND is_active = 1";
        $admin_email_result = mysqli_query($conn, $admin_email_query);
        if ($admin_email_result && $admin = mysqli_fetch_assoc($admin_email_result)) {
            sendEmail($admin['email'], "Subscription Expiring Soon - Salon Pro", 
                "Dear Salon Owner,<br><br>Your subscription for <strong>$salon_name</strong> will expire in <strong>$days_remaining days</strong> on " . date('M d, Y', strtotime($expiry_date)) . ".<br><br>Please renew your subscription to avoid service interruption.<br><br>Thank you,<br>Salon Pro Team");
            logMessage("📧 Warning email sent to: " . $admin['email']);
        }
        
        // Also send SMS if available
        $admin_phone_query = "SELECT phone FROM users 
                              WHERE salon_id = $salon_id AND role = 'admin' AND is_active = 1";
        $admin_phone_result = mysqli_query($conn, $admin_phone_query);
        if ($admin_phone_result && $admin = mysqli_fetch_assoc($admin_phone_result)) {
            sendSMS($admin['phone'], "Salon Pro: Your subscription for $salon_name expires in $days_remaining days. Please renew to avoid service interruption.");
            logMessage("📱 SMS sent to: " . $admin['phone']);
        }
    }
}

// ============================================
// SUMMARY REPORT
// ============================================
logMessage("=== CRON JOB COMPLETED ===");
logMessage("📊 Summary:");
logMessage("   - Total salons checked: $total_salons");
logMessage("   - Expired & deactivated: $expired_count");
logMessage("   - Warning emails sent: $warning_count");
logMessage("   - Errors: " . count($errors));

if (!empty($errors)) {
    logMessage("⚠️ Errors encountered:", 'ERROR');
    foreach ($errors as $error) {
        logMessage("   - $error", 'ERROR');
    }
}

logMessage("=== CRON JOB FINISHED ===");

// ============================================
// SUPER ADMIN NOTIFICATION (if any expirations)
// ============================================
if ($expired_count > 0 || $warning_count > 0) {
    // Get super admin email
    $super_query = "SELECT email FROM users WHERE is_super_admin = 1 LIMIT 1";
    $super_result = mysqli_query($conn, $super_query);
    if ($super_result && $super = mysqli_fetch_assoc($super_result)) {
        $subject = "Salon Pro Subscription Report - " . date('Y-m-d');
        $body = "Dear Super Admin,<br><br>";
        $body .= "Daily subscription check completed.<br><br>";
        $body .= "<strong>Summary:</strong><br>";
        $body .= "• Salons checked: $total_salons<br>";
        $body .= "• Expired & deactivated: $expired_count<br>";
        $body .= "• Warning emails sent: $warning_count<br><br>";
        $body .= "Please check the cron log for details.<br><br>";
        $body .= "Thank you,<br>Salon Pro System";
        
        sendEmail($super['email'], $subject, $body);
        logMessage("📧 Super admin report sent to: " . $super['email']);
    }
}

// Close connection
mysqli_close($conn);

logMessage("✅ CRON JOB FINISHED SUCCESSFULLY");
exit(0);
?>