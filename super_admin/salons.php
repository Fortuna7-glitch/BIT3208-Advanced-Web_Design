<?php
/**
 * Salon Pro — Super Admin: Manage Salons
 * Luxury gold/black theme
 * Fixed top bar: Breadcrumb | Quick Actions | Search
 */

require_once '../config/database.php';

// Authentication check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

$admin_name = $_SESSION['user_name'] ?? 'Super Admin';
$error = '';
$success = '';

// ============================================
// HANDLE ACTIONS
// ============================================

// Get plan pricing from database
$plan_prices = getAllPlanPrices();

// Create New Salon + Owner
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_salon'])) {
    $salon_name = mysqli_real_escape_string($conn, $_POST['salon_name']);
    $salon_email = mysqli_real_escape_string($conn, $_POST['salon_email']);
    $salon_phone = mysqli_real_escape_string($conn, $_POST['salon_phone']);
    $salon_address = mysqli_real_escape_string($conn, $_POST['salon_address']);
    $subscription_plan = mysqli_real_escape_string($conn, $_POST['subscription_plan']);
    
    // Owner details
    $owner_name = mysqli_real_escape_string($conn, $_POST['owner_name']);
    $owner_email = mysqli_real_escape_string($conn, $_POST['owner_email']);
    $owner_phone = mysqli_real_escape_string($conn, $_POST['owner_phone']);
    $owner_password = password_hash('owner123', PASSWORD_DEFAULT);
    
    // Check if owner email already exists
    $check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$owner_email'");
    if (mysqli_num_rows($check) > 0) {
        $error = "Owner email already registered!";
    } else {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // 1. Create the owner (admin) user
            $owner_query = "INSERT INTO users (full_name, email, phone, password, role, is_active) 
                            VALUES ('$owner_name', '$owner_email', '$owner_phone', '$owner_password', 'admin', 1)";
            mysqli_query($conn, $owner_query);
            $owner_id = mysqli_insert_id($conn);
            
            // 2. Create the salon
            $salon_query = "INSERT INTO salons (salon_name, salon_email, salon_phone, salon_address, subscription_plan, subscription_status, owner_id) 
                            VALUES ('$salon_name', '$salon_email', '$salon_phone', '$salon_address', '$subscription_plan', 'active', $owner_id)";
            mysqli_query($conn, $salon_query);
            $salon_id = mysqli_insert_id($conn);
            
            // 3. Link owner to salon
            $update_owner = "UPDATE users SET salon_id = $salon_id WHERE id = $owner_id";
            mysqli_query($conn, $update_owner);
            
            // 4. Set subscription expiry to 1 month from now
            $expiry_date = date('Y-m-d', strtotime('+1 month'));
            $update_salon = "UPDATE salons SET subscription_expiry = '$expiry_date' WHERE id = $salon_id";
            mysqli_query($conn, $update_salon);
            
            mysqli_commit($conn);
            $success = "Salon and Owner created successfully! Owner can login with email and password: owner123";
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Failed to create salon: " . $e->getMessage();
        }
    }
}

// ============================================
// GET ALL SALONS
// ============================================
$salons_query = "SELECT s.*, u.full_name as owner_name, u.email as owner_email 
                 FROM salons s 
                 LEFT JOIN users u ON s.owner_id = u.id 
                 ORDER BY s.id DESC";
$salons_result = mysqli_query($conn, $salons_query);

include '../includes/header.php';
?>

<style>
    .main-content {
        padding: 0 2rem 2rem;
        background: #0a0a0a;
        min-height: 100vh;
        margin-top: 0.5rem;
    }

    /* ============================================
       STICKY HEADER - Same as Dashboard
       ============================================ */
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
        gap: 0.8rem;
        flex: 0 0 auto;
    }

    .top-bar-right .search-box {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: #0e0e0e;
        border: 1px solid rgba(212, 175, 55, 0.25);
        border-radius: 20px;
        padding: 0.35rem 1rem;
        color: #7a7568;
        font-size: 0.85rem;
        min-width: 180px;
        position: relative;
    }

    .top-bar-right .search-box input {
        background: none;
        border: none;
        outline: none;
        color: #f5f0e1;
        font-size: 0.85rem;
        flex: 1;
        width: 100px;
    }

    .top-bar-right .search-box input::placeholder {
        color: #7a7568;
    }

    .top-bar-right .search-box .search-icon {
        color: #d4af37;
        cursor: pointer;
    }

    .top-bar-right .icon-btn {
        position: relative;
        color: #f0d878;
        font-size: 1.2rem;
        cursor: pointer;
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

    /* ============================================
       WELCOME ROW
       ============================================ */
    .welcome-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin: 0.8rem 0 1.2rem 0;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .welcome-row h1 {
        font-size: 1.6rem;
        font-weight: 700;
        color: #f0d878;
        font-family: 'Playfair Display', serif;
    }
    .welcome-row .subtitle {
        font-size: 0.9rem;
        color: #7a7568;
        margin-top: 0.3rem;
    }
    .welcome-row .date-badge {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border: 1px solid rgba(212, 175, 55, 0.25);
        border-radius: 8px;
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
        color: #b8b2a0;
    }
    .welcome-row .date-badge i {
        color: #d4af37;
    }

    /* ============================================
       CREATE FORM
       ============================================ */
    .create-form {
        background: #0e0e0e;
        border: 1px solid rgba(212, 175, 55, 0.25);
        border-radius: 12px;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }

    .create-form h2 {
        color: #f0d878;
        font-size: 1.1rem;
        font-family: 'Playfair Display', serif;
        margin-bottom: 0.5rem;
    }

    .create-form .form-desc {
        color: #7a7568;
        font-size: 0.85rem;
        margin-bottom: 1.5rem;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-group label {
        display: block;
        color: #b8b2a0;
        font-size: 0.8rem;
        font-weight: 500;
        margin-bottom: 0.3rem;
    }

    .form-group .form-control,
    .form-group select {
        width: 100%;
        padding: 10px 14px;
        background: #1a1a1a;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 8px;
        color: #f5f0e1;
        font-size: 0.9rem;
        transition: all 0.3s;
    }

    .form-group .form-control:focus,
    .form-group select:focus {
        outline: none;
        border-color: #d4af37;
    }

    .form-group .form-control::placeholder {
        color: #555;
    }

    .form-group .help-text {
        color: #7a7568;
        font-size: 0.75rem;
        margin-top: 0.3rem;
    }

    .form-section-title {
        color: #d4af37;
        font-size: 0.9rem;
        font-weight: 600;
        margin: 0.5rem 0 1rem 0;
        padding-bottom: 0.3rem;
        border-bottom: 1px solid rgba(212, 175, 55, 0.15);
    }

    .btn-create {
        padding: 10px 35px;
        background: #d4af37;
        color: #050505;
        border: none;
        border-radius: 25px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        margin-top: 0.5rem;
    }

    .btn-create:hover {
        background: #f0d878;
        transform: translateY(-2px);
    }

    /* ============================================
       TABLE
       ============================================ */
    .table-wrapper {
        overflow-x: auto;
        background: #0e0e0e;
        border-radius: 12px;
        padding: 0;
        border: 1px solid rgba(212, 175, 55, 0.25);
        -webkit-overflow-scrolling: touch;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
        min-width: 700px;
    }

    th, td {
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid rgba(212, 175, 55, 0.1);
    }

    th {
        color: #d4af37;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    tr:hover {
        background: rgba(212, 175, 55, 0.05);
    }

    .status-badge {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
    }

    .status-badge.active {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border: 1px solid #28a745;
    }

    .status-badge.expired {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
        border: 1px solid #dc3545;
    }

    .status-badge.suspended {
        background: rgba(212, 175, 55, 0.15);
        color: #d4af37;
        border: 1px solid #d4af37;
    }

    .plan-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
    }

    .plan-badge.basic { background: rgba(23, 162, 184, 0.15); color: #17a2b8; border: 1px solid #17a2b8; }
    .plan-badge.premium { background: rgba(212, 175, 55, 0.15); color: #d4af37; border: 1px solid #d4af37; }
    .plan-badge.enterprise { background: rgba(40, 167, 69, 0.15); color: #28a745; border: 1px solid #28a745; }

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

    .back-link {
        display: inline-block;
        margin-top: 1.5rem;
        color: #f0d878;
        text-decoration: none;
    }

    .back-link:hover {
        text-decoration: underline;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        table { min-width: 600px; }
        .form-row { grid-template-columns: 1fr; }
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
        .top-bar-right .search-box { flex: 1; min-width: 120px; }
        .quick-links .qlink { font-size: 0.7rem; padding: 0.2rem 0.4rem; }
        .welcome-row { flex-direction: column; }
        .welcome-row h1 { font-size: 1.3rem; }
        .create-form { padding: 1rem; }
        .form-row { grid-template-columns: 1fr; gap: 0.5rem; }
        table { min-width: 500px; font-size: 0.75rem; }
        th, td { padding: 6px; }
        .btn-create { width: 100%; text-align: center; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0 0.8rem 0.8rem; }
        .quick-links .qlink { font-size: 0.65rem; padding: 0.15rem 0.3rem; }
        table { min-width: 400px; font-size: 0.7rem; }
        th, td { padding: 4px; }
        .create-form { padding: 0.8rem; }
    }
</style>

<div class="main-content">

    <!-- ============================================
       STICKY HEADER
       ============================================ -->
    <div class="sticky-header">
        <div class="top-bar">
            <div class="top-bar-left">
                <div class="breadcrumb">
                    <i class="ti ti-menu-2 menu-icon"></i>
                    <span class="current">Dashboard</span>
                    <span class="sep">/</span>
                    <span class="sub">Manage Salons</span>
                </div>
            </div>

            <div class="top-bar-center">
                <div class="quick-links">
                    <a href="salons.php" class="qlink active"><i class="ti ti-building-store"></i> Manage Salons</a>
                    <span class="link-sep">|</span>
                    <a href="admins.php" class="qlink"><i class="ti ti-user-shield"></i> Manage Owners</a>
                    <span class="link-sep">|</span>
                    <a href="subscriptions.php" class="qlink"><i class="ti ti-crown"></i> Subscriptions</a>
                    <span class="link-sep">|</span>
                    <a href="settings.php" class="qlink"><i class="ti ti-settings"></i> System Settings</a>
                    <span class="link-sep">|</span>
                    <a href="products.php" class="qlink"><i class="ti ti-box"></i> Products</a>
                </div>
            </div>

            <div class="top-bar-right">
                <div class="search-box">
                    <i class="ti ti-search search-icon"></i>
                    <input type="text" id="globalSearch" placeholder="Search salons...">
                </div>
                <div class="icon-btn"><i class="ti ti-bell"></i></div>
                <div class="topbar-avatar"><i class="ti ti-crown"></i></div>
            </div>
        </div>
    </div>

    <!-- ============================================
       WELCOME ROW
       ============================================ -->
    <div class="welcome-row">
        <div>
            <h1>🏪 Manage Salons</h1>
            <p class="subtitle">Create and manage all salons on the platform</p>
        </div>
        <div class="date-badge">
            <i class="ti ti-calendar"></i> <?php echo date('j F Y'); ?>
        </div>
    </div>

    <!-- ============================================
       ALERTS
       ============================================ -->
    <?php if($success): ?>
        <div class="alert alert-success">✅ <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-danger">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- ============================================
       CREATE NEW SALON FORM
       ============================================ -->
    <div class="create-form">
        <h2>➕ Create New Salon &amp; Owner</h2>
        <p class="form-desc">Fill in the details below to create a new salon and its owner account simultaneously.</p>

        <form method="POST">
            <div class="form-section-title">🏪 Salon Information</div>
            <div class="form-row">
                <div class="form-group">
                    <label>Salon Name</label>
                    <input type="text" name="salon_name" class="form-control" placeholder="e.g. Salon Pro - Westlands" required>
                </div>
                <div class="form-group">
                    <label>Salon Email</label>
                    <input type="email" name="salon_email" class="form-control" placeholder="salon@example.com" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Salon Phone</label>
                    <input type="tel" name="salon_phone" class="form-control" placeholder="0712345678" required>
                </div>
                <div class="form-group">
                    <label>Subscription Plan</label>
                    <select name="subscription_plan" class="form-control" required>
                        <option value="basic">Basic - KSh <?php echo number_format($plan_prices['basic'], 2); ?>/month</option>
                        <option value="premium">Premium - KSh <?php echo number_format($plan_prices['premium'], 2); ?>/month</option>
                        <option value="enterprise">Enterprise - KSh <?php echo number_format($plan_prices['enterprise'], 2); ?>/month</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="salon_address" class="form-control" placeholder="123 Luxury Mall, Nairobi" required>
            </div>

            <div class="form-section-title">👤 Salon Owner Information</div>
            <div class="form-row">
                <div class="form-group">
                    <label>Owner Full Name</label>
                    <input type="text" name="owner_name" class="form-control" placeholder="John Doe" required>
                </div>
                <div class="form-group">
                    <label>Owner Email</label>
                    <input type="email" name="owner_email" class="form-control" placeholder="owner@example.com" required>
                </div>
            </div>
            <div class="form-group">
                <label>Owner Phone</label>
                <input type="tel" name="owner_phone" class="form-control" placeholder="0712345678" required>
            </div>
            <div class="form-group">
                <div class="help-text">🔑 Default password for owner: <strong>owner123</strong> (they can change after first login)</div>
            </div>

            <button type="submit" name="create_salon" class="btn-create">✨ Create Salon &amp; Owner</button>
        </form>
    </div>

    <!-- ============================================
       SALONS TABLE
       ============================================ -->
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Salon Name</th>
                    <th>Owner</th>
                    <th>Plan</th>
                    <th>Status</th>
                    <th>Expiry</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($salons_result) > 0): ?>
                    <?php while($salon = mysqli_fetch_assoc($salons_result)): ?>
                        <tr>
                            <td><?php echo $salon['id']; ?></td>
                            <td><?php echo htmlspecialchars($salon['salon_name']); ?></td>
                            <td><?php echo htmlspecialchars($salon['owner_name'] ?? 'No Owner'); ?></td>
                            <td>
                                <span class="plan-badge <?php echo $salon['subscription_plan'] ?? 'basic'; ?>">
                                    <?php echo ucfirst($salon['subscription_plan'] ?? 'Basic'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $salon['subscription_status'] ?? 'inactive'; ?>">
                                    <?php echo ucfirst($salon['subscription_status'] ?? 'Inactive'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($salon['subscription_expiry'])): ?>
                                    <?php echo date('M d, Y', strtotime($salon['subscription_expiry'])); ?>
                                <?php else: ?>
                                    Not set
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($salon['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px;">
                            <div class="empty-state">
                                <p>No salons found. Create your first salon above!</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

<script>
    // Simple search functionality
    document.getElementById('globalSearch')?.addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            const query = this.value.trim();
            if (query.length > 0) {
                window.location.href = 'search.php?q=' + encodeURIComponent(query);
            }
        }
    });
</script>

<?php include '../includes/footer.php'; ?>