<?php
/**
 * includes/subscription_banner.php
 * 
 * REUSABLE COMPONENT - Display subscription status banners
 * 
 * Usage:
 *   include_once 'includes/subscription_banner.php';
 *   renderSubscriptionBanner($salon_id);
 * 
 * Or with custom styling:
 *   renderSubscriptionBanner($salon_id, 'compact', 'warning');
 */

// ============================================
// MAIN FUNCTION
// ============================================

/**
 * Render subscription status banner for a salon
 * 
 * @param int $salon_id - The salon ID
 * @param string $style - 'full', 'compact', 'minimal'
 * @param string $type - 'warning', 'info', 'danger'
 */
function renderSubscriptionBanner($salon_id, $style = 'full', $type = 'warning') {
    global $conn;
    
    if ($salon_id <= 0) {
        return '';
    }
    
    // Get salon subscription data
    $query = "SELECT subscription_expiry, subscription_status, subscription_plan, salon_name 
              FROM salons 
              WHERE id = $salon_id";
    $result = mysqli_query($conn, $query);
    
    if (!$result || !$salon = mysqli_fetch_assoc($result)) {
        return '';
    }
    
    $expiry_date = $salon['subscription_expiry'];
    $status = $salon['subscription_status'];
    $plan = $salon['subscription_plan'];
    $salon_name = $salon['salon_name'];
    
    // Calculate days remaining
    $today = new DateTime();
    $expiry = !empty($expiry_date) && $expiry_date != '0000-00-00' ? new DateTime($expiry_date) : null;
    $days_remaining = null;
    $is_expired = false;
    $is_expiring_soon = false;
    $has_valid_expiry = ($expiry !== null);
    
    if ($expiry) {
        $diff = $today->diff($expiry);
        $days_remaining = $diff->days;
        
        if ($expiry < $today) {
            $days_remaining = -$days_remaining;
            $is_expired = true;
        } elseif ($days_remaining <= 7) {
            $is_expiring_soon = true;
        }
    }
    
    // Check status explicitly
    if ($status == 'expired' || $status == 'suspended') {
        $is_expired = true;
    }
    
    // ============================================
    // DETERMINE BANNER TYPE
    // ============================================
    $banner_class = '';
    $icon = '';
    $title = '';
    $message = '';
    $action_text = '';
    $action_link = '';
    
    if ($is_expired) {
        $banner_class = 'subscription-expired';
        $icon = '🚫';
        $title = 'Subscription Expired';
        $message = 'Your salon subscription has expired. Please contact the administrator to renew.';
        $action_text = '🔓 Renew Now';
        $action_link = '../super_admin/renew_subscription.php?id=' . $salon_id;
    } elseif ($is_expiring_soon) {
        $banner_class = 'subscription-expiring-soon';
        $icon = '⚠️';
        $title = 'Subscription Expiring Soon';
        $message = "Your subscription expires in <strong>$days_remaining days</strong> on " . date('M d, Y', strtotime($expiry_date)) . ". Please renew to avoid service interruption.";
        $action_text = '🔓 Renew Now';
        $action_link = '../super_admin/renew_subscription.php?id=' . $salon_id;
    } elseif ($has_valid_expiry && $days_remaining !== null && $days_remaining <= 30) {
        $banner_class = 'subscription-expiring-soon-30';
        $icon = '📅';
        $title = 'Subscription Reminder';
        $message = "Your subscription expires in <strong>$days_remaining days</strong> on " . date('M d, Y', strtotime($expiry_date)) . ". Renew early to avoid interruption.";
        $action_text = '🔓 Renew Now';
        $action_link = '../super_admin/renew_subscription.php?id=' . $salon_id;
    } elseif ($has_valid_expiry) {
        // Active subscription with valid expiry - show green badge
        if ($style == 'minimal') {
            return '';
        }
        $banner_class = 'subscription-active';
        $icon = '✅';
        $title = 'Subscription Active';
        $message = "Your subscription is active until " . date('M d, Y', strtotime($expiry_date)) . ".";
        $action_text = '';
        $action_link = '';
    } else {
        // No expiry date set - show info message
        if ($style == 'minimal') {
            return '';
        }
        $banner_class = 'subscription-active';
        $icon = 'ℹ️';
        $title = 'Subscription Active';
        $message = "Your subscription is active. (No expiry date set)";
        $action_text = '';
        $action_link = '';
    }
    
    // ============================================
    // RENDER BANNER
    // ============================================
    ?>
    <div class="subscription-banner <?php echo $banner_class; ?> <?php echo $style; ?>">
        <div class="subscription-banner-icon"><?php echo $icon; ?></div>
        <div class="subscription-banner-content">
            <div class="subscription-banner-title"><?php echo $title; ?></div>
            <div class="subscription-banner-message"><?php echo $message; ?></div>
            <?php if (!empty($plan) && $style != 'minimal'): ?>
                <div class="subscription-banner-plan">Plan: <strong><?php echo ucfirst($plan); ?></strong></div>
            <?php endif; ?>
        </div>
        <?php if (!empty($action_text) && !empty($action_link)): ?>
            <div class="subscription-banner-action">
                <a href="<?php echo $action_link; ?>" class="btn-renew"><?php echo $action_text; ?></a>
            </div>
        <?php endif; ?>
    </div>
    
    <style>
        /* ============================================
           SUBSCRIPTION BANNER STYLES
           ============================================ */
        .subscription-banner {
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            border: 1px solid;
            font-size: 0.95rem;
        }
        .subscription-banner .subscription-banner-icon {
            font-size: 1.8rem;
            flex-shrink: 0;
        }
        .subscription-banner .subscription-banner-content {
            flex: 1;
            min-width: 200px;
        }
        .subscription-banner .subscription-banner-title {
            font-weight: 600;
            font-size: 1rem;
        }
        .subscription-banner .subscription-banner-message {
            font-size: 0.85rem;
            opacity: 0.9;
            margin-top: 0.2rem;
        }
        .subscription-banner .subscription-banner-plan {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 0.2rem;
        }
        .subscription-banner .btn-renew {
            background: #d4af37;
            color: #050505;
            padding: 8px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            white-space: nowrap;
            font-size: 0.85rem;
        }
        .subscription-banner .btn-renew:hover {
            background: #f9e547;
            transform: scale(1.05);
        }
        .subscription-banner .btn-renew.outline {
            background: transparent;
            border: 1px solid #d4af37;
            color: #d4af37;
        }
        .subscription-banner .btn-renew.outline:hover {
            background: rgba(212, 175, 55, 0.1);
        }
        
        /* Banner Types */
        .subscription-banner.subscription-expired {
            background: rgba(220, 53, 69, 0.15);
            border-color: #dc3545;
            color: #dc3545;
        }
        .subscription-banner.subscription-expiring-soon {
            background: rgba(212, 175, 55, 0.15);
            border-color: #d4af37;
            color: #d4af37;
        }
        .subscription-banner.subscription-expiring-soon-30 {
            background: rgba(23, 162, 184, 0.15);
            border-color: #17a2b8;
            color: #17a2b8;
        }
        .subscription-banner.subscription-active {
            background: rgba(40, 167, 69, 0.1);
            border-color: #28a745;
            color: #28a745;
        }
        
        /* Styles */
        .subscription-banner.compact {
            padding: 0.6rem 1rem;
            font-size: 0.85rem;
        }
        .subscription-banner.compact .subscription-banner-icon {
            font-size: 1.2rem;
        }
        .subscription-banner.minimal {
            padding: 0.4rem 0.8rem;
            font-size: 0.75rem;
            border-width: 1px;
        }
        .subscription-banner.minimal .subscription-banner-icon {
            font-size: 1rem;
        }
        .subscription-banner.minimal .subscription-banner-title {
            font-size: 0.8rem;
        }
        .subscription-banner.minimal .subscription-banner-message {
            font-size: 0.7rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .subscription-banner {
                flex-direction: column;
                text-align: center;
                gap: 0.8rem;
                padding: 1rem;
            }
            .subscription-banner .subscription-banner-icon {
                font-size: 1.5rem;
            }
            .subscription-banner .btn-renew {
                width: 100%;
                text-align: center;
            }
        }
    </style>
    <?php
}

// ============================================
// USAGE EXAMPLES (Commented out)
// ============================================

/*
// Example 1: Full banner for Admin dashboard
renderSubscriptionBanner($_SESSION['salon_id']);

// Example 2: Compact banner for Staff dashboard
renderSubscriptionBanner($_SESSION['salon_id'], 'compact');

// Example 3: Minimal banner for Customer dashboard
renderSubscriptionBanner($_SESSION['salon_id'], 'minimal');

// Example 4: Show warning banner only
renderSubscriptionBanner($_SESSION['salon_id'], 'full', 'warning');
*/
?>