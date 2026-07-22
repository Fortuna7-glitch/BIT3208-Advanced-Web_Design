<?php
/**
 * cron/check_expiry.php
 * 
 * DAILY CRON SCRIPT - Check expired subscriptions and auto-deactivate
 * 
 * MODIFIED: Integrated with new notification system
 * - Uses sendExpiryWarning() for 7-day warnings
 * - Uses sendSubscriptionConfirmation() for renewals (not used here)
 * - Creates in-app notifications for Super Admin
 * - Logs to notification_logs table
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
$second_warning_threshold = 3; // Send second warning 3 days before expiry

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
        
        // Get owner details for notification
        $owner_query = "SELECT u.id as user_id, u.full_name, u.email, u.phone 
                        FROM users u 
                        WHERE u.salon_id = $salon_id AND u.role = 'admin'";
        $owner_result = mysqli_query($conn, $owner_query);
        $owner = mysqli_fetch_assoc($owner_result);
        
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
        
        // ============================================
        // SEND EXPIRED NOTIFICATION TO OWNER
        // ============================================
        if ($owner) {
            // Email
            $subject = "❌ Subscription Expired - $salon_name";
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
                            <h2>❌ Subscription Expired</h2>
                            <p style='color: #b8b2a0;'>Your subscription for <strong>$salon_name</strong> expired on " . date('M d, Y', strtotime($expiry_date)) . "</p>
                            <p style='color: #dc3545;'>Your account has been deactivated.</p>
                        </div>
                        <p style='color: #7a7568;'>Please contact the administrator to renew your subscription.</p>
                        <p style='color: #f0d878;'>Thank you for choosing Salon Pro! ✨</p>
                    </div>
                    <div class='footer'>
                        <p>© " . date('Y') . " Salon Pro. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            sendEmail($owner['email'], $subject, $email_body);
            logMessage("📧 Expiry notification email sent to: " . $owner['email']);
            
            // SMS
            $sms_body = "SALON PRO: ❌ Your subscription for $salon_name expired on " . date('M d, Y', strtotime($expiry_date)) . ". Your account has been deactivated. Contact admin to renew. Thank you! ✨";
            sendSMS($owner['phone'], $sms_body);
            logMessage("📱 Expiry SMS sent to: " . $owner['phone']);
            
            // Log to notification_logs
            logNotification('owner', $owner['user_id'], $owner['email'], $owner['phone'], 'both', $subject, $email_body, 'sent');
            logNotification('owner', $owner['user_id'], $owner['email'], $owner['phone'], 'sms', $subject, $sms_body, 'sent');
        }
        
        // ============================================
        // SUPER ADMIN IN-APP NOTIFICATION
        // ============================================
        notifySuperAdmin(
            'subscription_expired',
            "$salon_name subscription has expired",
            "The salon has been deactivated. Owner: " . ($owner['full_name'] ?? 'Unknown') . " | Expired: " . date('M d, Y', strtotime($expiry_date)),
            "subscriptions.php"
        );
        logMessage("🔔 Super Admin in-app notification created for expired: $salon_name");
        
    // ============================================
    // CASE 2: SECOND WARNING (3 days remaining)
    // ============================================
    } elseif ($days_remaining <= $second_warning_threshold && $days_remaining >= 0 && $days_remaining > 0) {
        logMessage("⚠️ SECOND WARNING! $days_remaining days remaining for $salon_name", 'WARNING');
        
        // Get owner details
        $owner_query = "SELECT u.id as user_id, u.full_name, u.email, u.phone 
                        FROM users u 
                        WHERE u.salon_id = $salon_id AND u.role = 'admin'";
        $owner_result = mysqli_query($conn, $owner_query);
        $owner = mysqli_fetch_assoc($owner_result);
        
        // Use the existing sendExpiryWarning function
        sendExpiryWarning($salon_id, $days_remaining);
        $warning_count++;
        logMessage("✅ Second expiry warning sent to $salon_name");
        
        // Additional SMS reminder (functions already handle this, but extra safety)
        if ($owner && $owner['phone']) {
            sendSMS($owner['phone'], "SALON PRO: ⚠️ URGENT! Your subscription for $salon_name expires in $days_remaining days! Renew now to avoid deactivation. Thank you! ✨");
            logMessage("📱 Urgent SMS sent to: " . $owner['phone']);
        }
        
        // Super Admin in-app notification
        notifySuperAdmin(
            'subscription_expiring',
            "$salon_name expires in $days_remaining days (URGENT)",
            "Plan: " . ucfirst($salon['subscription_plan'] ?? 'Basic') . " | Expiry: " . date('M d, Y', strtotime($expiry_date)) . " | Action required",
            "subscriptions.php"
        );
        logMessage("🔔 Super Admin in-app notification created for urgent expiry: $salon_name");
        
    // ============================================
    // CASE 3: FIRST WARNING (7 days remaining)
    // ============================================
    } elseif ($days_remaining <= $warning_threshold && $days_remaining >= 0 && $days_remaining > 0) {
        logMessage("⚠️ FIRST WARNING! $days_remaining days remaining for $salon_name", 'WARNING');
        
        // Use the existing sendExpiryWarning function
        sendExpiryWarning($salon_id, $days_remaining);
        $warning_count++;
        logMessage("✅ First expiry warning sent to $salon_name");
        
        // Super Admin in-app notification (only for first warning, not every day)
        if ($days_remaining == $warning_threshold) {
            notifySuperAdmin(
                'subscription_expiring',
                "$salon_name expires in $days_remaining days",
                "Plan: " . ucfirst($salon['subscription_plan'] ?? 'Basic') . " | Expiry: " . date('M d, Y', strtotime($expiry_date)),
                "subscriptions.php"
            );
            logMessage("🔔 Super Admin in-app notification created for expiry warning: $salon_name");
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
// SUPER ADMIN REPORT (if any expirations)
// ============================================
if ($expired_count > 0 || $warning_count > 0) {
    // Get super admin details
    $super_query = "SELECT id, full_name, email FROM users WHERE role = 'super_admin' LIMIT 1";
    $super_result = mysqli_query($conn, $super_query);
    if ($super_result && $super = mysqli_fetch_assoc($super_result)) {
        $subject = "📊 Salon Pro Subscription Report - " . date('Y-m-d');
        $body = "
        <html>
        <head>
            <style>
                body { font-family: 'Poppins', Arial, sans-serif; background: #0a0a0a; color: #f5f0e1; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: #0e0e0e; border: 1px solid rgba(212, 175, 55, 0.25); border-radius: 12px; padding: 30px; }
                .header { text-align: center; border-bottom: 2px solid #d4af37; padding-bottom: 15px; }
                .header h1 { color: #d4af37; font-family: 'Playfair Display', serif; font-size: 28px; margin: 0; }
                .report { padding: 15px 0; }
                .row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid rgba(212, 175, 55, 0.05); }
                .footer { text-align: center; color: #7a7568; font-size: 12px; border-top: 1px solid rgba(212, 175, 55, 0.1); padding-top: 15px; margin-top: 15px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1><span>SALON</span> PRO</h1>
                    <p style='color: #d4af37;'>Daily Subscription Report</p>
                </div>
                <div class='report'>
                    <h3 style='color: #f0d878;'>📊 Summary</h3>
                    <div class='row'><span>📋 Total salons checked:</span><span>$total_salons</span></div>
                    <div class='row'><span>❌ Expired & deactivated:</span><span style='color: #dc3545;'>$expired_count</span></div>
                    <div class='row'><span>⚠️ Warning emails sent:</span><span style='color: #d4af37;'>$warning_count</span></div>
                    <div class='row'><span>❌ Errors:</span><span style='color: #dc3545;'>" . count($errors) . "</span></div>
                </div>
                <p style='color: #7a7568;'>Please check the cron log for details.</p>
                <a href='#' style='color: #d4af37;'>View in Dashboard →</a>
                <div class='footer'>
                    <p>© " . date('Y') . " Salon Pro. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        sendEmail($super['email'], $subject, $body);
        logMessage("📧 Super admin report sent to: " . $super['email']);
        
        // Create in-app notification for Super Admin
        notifySuperAdmin(
            'system_alert',
            "Daily Subscription Report",
            "$expired_count salons expired | $warning_count warnings sent | Check details",
            "subscriptions.php"
        );
        logMessage("🔔 Super Admin in-app notification created for daily report");
    }
}

// Close connection
mysqli_close($conn);

logMessage("✅ CRON JOB FINISHED SUCCESSFULLY");
exit(0);