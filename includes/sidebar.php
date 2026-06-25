<?php
// includes/sidebar.php - NEW Role-Specific Sidebar with Logout at Bottom
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base_path = '';
$current_file = $_SERVER['SCRIPT_NAME'];

if (strpos($current_file, '/admin/') !== false || 
    strpos($current_file, '/auth/') !== false || 
    strpos($current_file, '/customer/') !== false || 
    strpos($current_file, '/staff/') !== false ||
    strpos($current_file, '/super_admin/') !== false) {
    $base_path = '../';
}

// Get user info
$logged_in = false;
$user_role = '';
$user_name = '';

if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    $logged_in = true;
    $user_role = $_SESSION['user_role'];
    $user_name = $_SESSION['user_name'];
}
?>
<style>
    /* ============================================
    SIDEBAR STYLES
       ============================================ */
    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        z-index: 999;
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .sidebar-overlay.active {
        display: block;
        opacity: 1;
    }

    .sidebar {
        position: fixed;
        top: 0;
        left: -320px;
        width: 300px;
        height: 100%;
        background: #050505;
        border-right: 2px solid #d4af37;
        z-index: 1000;
        padding: 1.5rem 1rem;
        transition: left 0.3s ease;
        display: flex;
        flex-direction: column;
        overflow-y: auto;
    }
    .sidebar.open {
        left: 0;
    }

    /* Close Button */
    .sidebar-close {
        background: transparent;
        border: none;
        color: #d4af37;
        font-size: 1.5rem;
        cursor: pointer;
        align-self: flex-end;
        padding: 5px 8px;
        transition: color 0.3s;
    }
    .sidebar-close:hover {
        color: #f9e547;
    }

    /* User Info */
    .sidebar-user {
        text-align: center;
        padding: 1rem 0 1.5rem 0;
        border-bottom: 1px solid rgba(212, 175, 55, 0.2);
        margin-bottom: 1.5rem;
    }
    .sidebar-user .avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: #d4af37;
        color: #050505;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: bold;
        margin: 0 auto 0.8rem auto;
    }
    .sidebar-user .name {
        color: white;
        font-weight: 600;
        font-size: 1.1rem;
    }
    .sidebar-user .role {
        color: #d4af37;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Menu Items */
    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 0;
        flex: 1;
    }
    .sidebar-menu li {
        margin-bottom: 0.3rem;
    }
    .sidebar-menu a {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        padding: 12px 16px;
        color: #ccc;
        text-decoration: none;
        border-radius: 10px;
        transition: all 0.3s;
        font-size: 0.95rem;
    }
    .sidebar-menu a:hover {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
    }
    .sidebar-menu a.active {
        background: #d4af37;
        color: #050505;
    }
    .sidebar-menu a .icon {
        width: 24px;
        text-align: center;
        font-size: 1.1rem;
    }
    .sidebar-menu .divider {
        height: 1px;
        background: rgba(212, 175, 55, 0.2);
        margin: 0.8rem 0;
    }

    /* Logout at Bottom */
    .sidebar-footer {
        border-top: 1px solid rgba(212, 175, 55, 0.2);
        padding-top: 1rem;
        margin-top: auto;
    }
    .sidebar-footer a {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        padding: 12px 16px;
        color: #dc3545;
        text-decoration: none;
        border-radius: 10px;
        transition: all 0.3s;
        font-size: 0.95rem;
    }
    .sidebar-footer a:hover {
        background: rgba(220, 53, 69, 0.15);
        color: #ff6b6b;
    }
    .sidebar-footer a .icon {
        width: 24px;
        text-align: center;
        font-size: 1.1rem;
    }

    /* ============================================
    RESPONSIVE
       ============================================ */
    @media (max-width: 768px) {
        .sidebar { width: 280px; left: -300px; }
    }
    @media (max-width: 480px) {
        .sidebar { width: 260px; left: -280px; padding: 1rem; }
        .sidebar-user .avatar { width: 50px; height: 50px; font-size: 1.2rem; }
    }
</style>

<!-- Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">

    <!-- Close Button -->
    <button class="sidebar-close" id="sidebarClose" aria-label="Close Menu">
        <i class="fas fa-times"></i>
    </button>

    <?php if ($logged_in): ?>
        <!-- User Info -->
        <div class="sidebar-user">
            <div class="avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
            <div class="name"><?php echo htmlspecialchars($user_name); ?></div>
            <div class="role"><?php echo ucfirst(str_replace('_', ' ', $user_role)); ?></div>
        </div>

        <!-- Menu Items -->
        <ul class="sidebar-menu">

            <?php if ($user_role == 'super_admin'): ?>
                <!-- SUPER ADMIN MENU -->
                <li><a href="<?php echo $base_path; ?>super_admin/dashboard.php" class="active"><span class="icon">📊</span> Dashboard</a></li>
                <li><a href="<?php echo $base_path; ?>super_admin/salons.php"><span class="icon">🏢</span> Salons</a></li>
                <li><a href="<?php echo $base_path; ?>super_admin/admins.php"><span class="icon">👨‍💼</span> Owners</a></li>
                <li><a href="<?php echo $base_path; ?>super_admin/subscriptions.php"><span class="icon">💰</span> Subscriptions</a></li>
                <li><div class="divider"></div></li>
                <li><a href="<?php echo $base_path; ?>super_admin/settings.php"><span class="icon">⚙️</span> Settings</a></li>

            <?php elseif ($user_role == 'admin'): ?>
                <!-- ADMIN MENU -->
                <li><a href="<?php echo $base_path; ?>admin/dashboard.php" class="active"><span class="icon">📊</span> Dashboard</a></li>
                <li><a href="<?php echo $base_path; ?>admin/appointments.php"><span class="icon">📅</span> Appointments</a></li>
                <li><a href="<?php echo $base_path; ?>admin/services.php"><span class="icon">💇</span> Services</a></li>
                <li><a href="<?php echo $base_path; ?>admin/staff.php"><span class="icon">👥</span> Staff</a></li>
                <li><a href="<?php echo $base_path; ?>admin/customers.php"><span class="icon">👤</span> Customers</a></li>
                <li><a href="<?php echo $base_path; ?>admin/payments.php"><span class="icon">💰</span> Payments</a></li>
                <li><a href="<?php echo $base_path; ?>admin/reports.php"><span class="icon">📈</span> Reports</a></li>
                <li><div class="divider"></div></li>
                <li><a href="<?php echo $base_path; ?>admin/profile.php"><span class="icon">👤</span> My Profile</a></li>

            <?php elseif ($user_role == 'staff'): ?>
                <!-- STAFF MENU -->
                <li><a href="<?php echo $base_path; ?>staff/dashboard.php" class="active"><span class="icon">📊</span> Dashboard</a></li>
                <li><a href="<?php echo $base_path; ?>staff/appointments.php"><span class="icon">📅</span> My Appointments</a></li>
                <li><a href="<?php echo $base_path; ?>staff/book_for_customer.php"><span class="icon">📝</span> Book for Customer</a></li>
                <li><a href="<?php echo $base_path; ?>staff/manual_payment.php"><span class="icon">💵</span> Manual Payment</a></li>
                <li><a href="<?php echo $base_path; ?>staff/reports.php"><span class="icon">📈</span>Reports</a></li>
                <li><div class="divider"></div></li>
                <li><a href="<?php echo $base_path; ?>staff/profile.php"><span class="icon">👤</span> My Profile</a></li>

            <?php elseif ($user_role == 'customer'): ?>
                <!-- CUSTOMER MENU -->
                <li><a href="<?php echo $base_path; ?>customer/dashboard.php" class="active"><span class="icon">📊</span> Dashboard</a></li>
                <li><a href="<?php echo $base_path; ?>customer/appointments.php"><span class="icon">📋</span> My Appointments</a></li>
                <li><a href="<?php echo $base_path; ?>customer/book.php"><span class="icon">📅</span> New Booking</a></li>
                <li><div class="divider"></div></li>
                <li><a href="<?php echo $base_path; ?>customer/update-profile.php"><span class="icon">👤</span> My Profile</a></li>

            <?php endif; ?>

        </ul>

        <!-- Logout at Bottom -->
        <div class="sidebar-footer">
            <a href="<?php echo $base_path; ?>auth/logout.php" onclick="return confirm('Are you sure you want to logout?')">
                <span class="icon">🚪</span> Logout
            </a>
        </div>

    <?php else: ?>
        <!-- Not Logged In -->
        <ul class="sidebar-menu">
            <li><a href="<?php echo $base_path; ?>index.php"><span class="icon">🏠</span> Home</a></li>
            <li><a href="<?php echo $base_path; ?>find_salons.php"><span class="icon">📍</span> Find a Salon</a></li>
            <li><div class="divider"></div></li>
            <li><a href="<?php echo $base_path; ?>auth/login.php"><span class="icon">🔐</span> Login</a></li>
            <li><a href="<?php echo $base_path; ?>auth/register.php"><span class="icon">📝</span> Register</a></li>
        </ul>
    <?php endif; ?>

</div>

<script>
    // ============================================
    // SIDEBAR TOGGLE LOGIC
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {

        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const closeBtn = document.getElementById('sidebarClose');

        function openSidebar() {
            sidebar.classList.add('open');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Open via hamburger
        if (hamburgerBtn) {
            hamburgerBtn.addEventListener('click', openSidebar);
        }

        // Close via X button
        if (closeBtn) {
            closeBtn.addEventListener('click', closeSidebar);
        }

        // Close via overlay click
        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }

        // Close via Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        });

        // Close when a menu link is clicked (optional)
        sidebar.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                // Don't close if it's the logout link (it has its own confirm)
                if (!this.href.includes('logout.php')) {
                    closeSidebar();
                }
            });
        });

    });
</script>